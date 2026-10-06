<?php
/**
 * Funil de leads (CRM comercial).
 *
 *   /painel/leads        Kanban do funil (Lead → Lead quente → Negociação → Proposta enviada → Fechado / Perdido)
 *   /painel/lead/<id>    ficha: dados, anotações, conversa, lembrete e conversões
 *
 * Lembrete ("chamar o lead X quinta às 10h") vira tarefa com prazo e aparece no dashboard.
 * Lead → orçamento → aprovado e pago → cliente e pedido, e o lead vai para "Fechado".
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function ap_funnel() {
	$out = array();
	foreach ( ap_list( ap_setting( 'funil' ) ) as $name ) {
		$out[ sanitize_title( $name ) ] = $name;
	}
	return $out ? $out : array( 'lead' => 'Lead', 'fechado' => 'Fechado', 'perdido' => 'Perdido' );
}

/**
 * Colunas "ganho" e "perdido" (pelas palavras do nome).
 */
function ap_funnel_won() {
	foreach ( ap_funnel() as $slug => $name ) {
		if ( preg_match( '/fechad|ganh|cliente/i', $name ) ) {
			return $slug;
		}
	}
	return '';
}

function ap_funnel_lost() {
	foreach ( ap_funnel() as $slug => $name ) {
		if ( preg_match( '/perdid/i', $name ) ) {
			return $slug;
		}
	}
	return '';
}

function ap_leads( $where = '1=1', $args = array(), $order = 'l.position, l.id DESC' ) {
	global $wpdb;
	$sql = 'SELECT l.*, s.name AS service_name FROM ' . ap_table( 'leads' ) . ' l LEFT JOIN ' . ap_table( 'services' ) . ' s ON s.id = l.service_id WHERE ' . $where . ' ORDER BY ' . $order;
	return $wpdb->get_results( $args ? $wpdb->prepare( $sql, $args ) : $sql ); // phpcs:ignore WordPress.DB.PreparedSQL
}

function ap_lead_label( $l ) {
	return $l->company ? $l->company : ( $l->name ? $l->name : ( $l->whatsapp ? $l->whatsapp : 'Lead #' . $l->id ) );
}

function ap_lead_log( $lead_id, $body, $type = 'log' ) {
	return ap_insert(
		'activity',
		array(
			'lead_id' => (int) $lead_id,
			'user_id' => get_current_user_id(),
			'type'    => $type,
			'body'    => $body,
		)
	);
}

/* -----------------------------------------------------------------------
 * Formulários
 * -------------------------------------------------------------------- */

function ap_do_lead_save() {
	ap_require( 'leads' );
	$id     = ap_in( 'id', 'int' );
	$funnel = ap_funnel();
	$stage  = ap_in( 'stage' );
	$data   = array(
		'name'       => ap_in( 'name' ),
		'company'    => ap_in( 'company' ),
		'email'      => ap_in( 'email', 'email' ),
		'whatsapp'   => ap_in( 'whatsapp' ),
		'source'     => ap_in( 'source' ),
		'value'      => ap_in( 'value', 'money' ),
		'notes'      => ap_in( 'notes', 'textarea' ),
		'client_id'  => ap_in( 'client_id', 'int' ),
	);
	// Cliente que já existe: completa o que ficou em branco com o cadastro dele.
	$client = $data['client_id'] ? ap_get( 'clients', $data['client_id'] ) : null;
	if ( $data['client_id'] && ! $client ) {
		$data['client_id'] = 0;
	}
	if ( $client ) {
		foreach ( array( 'name', 'company', 'email', 'whatsapp' ) as $f ) {
			if ( ! $data[ $f ] && ! empty( $client->$f ) ) {
				$data[ $f ] = $client->$f;
			}
		}
	}
	if ( isset( $funnel[ $stage ] ) ) {
		$data['stage'] = $stage;
	}
	if ( ! $data['name'] && ! $data['company'] && ! $data['whatsapp'] ) {
		ap_back( 'Preencha pelo menos o nome, a empresa ou o WhatsApp.', 'erro' );
	}
	if ( $id ) {
		$old = ap_get( 'leads', $id );
		ap_update( 'leads', $id, $data );
		if ( isset( $data['stage'] ) && $old && $old->stage !== $data['stage'] ) {
			ap_lead_log( $id, 'Movido para "' . $funnel[ $data['stage'] ] . '".' );
		}
		ap_back( 'Lead salvo.' );
	}
	if ( empty( $data['stage'] ) ) {
		$keys          = array_keys( $funnel );
		$data['stage'] = $keys[0];
	}
	$id = ap_insert( 'leads', $data );
	ap_lead_log( $id, 'Lead criado' . ( $data['source'] ? ' (origem: ' . $data['source'] . ')' : '' ) . ( $client ? ' para o cliente ' . ap_client_label( $client ) : '' ) . '.' );
	ap_back( 'Lead criado.', 'ok', ap_panel_url( 'lead', $id ) );
}

function ap_do_lead_note() {
	ap_require( 'leads' );
	$id   = ap_in( 'lead_id', 'int' );
	$body = ap_in( 'body', 'textarea' );
	if ( $id && $body ) {
		ap_lead_log( $id, $body, 'comment' );
		ap_update( 'leads', $id, array( 'last_contact' => ap_in( 'contact', 'bool' ) ? ap_now() : ap_get( 'leads', $id )->last_contact ) );
	}
	ap_back( 'Anotação salva.' );
}

/**
 * Lembrete de follow-up: guarda no lead e cria a tarefa com prazo.
 */
function ap_do_lead_reminder() {
	ap_require( 'leads' );
	$id   = ap_in( 'lead_id', 'int' );
	$lead = ap_get( 'leads', $id );
	$date = ap_in( 'date', 'date' );
	$time = preg_match( '/^\d{2}:\d{2}$/', ap_in( 'time' ) ) ? ap_in( 'time' ) : '09:00';
	$what = ap_in( 'what' ) ? ap_in( 'what' ) : 'Chamar no WhatsApp';
	if ( ! $lead || ! $date ) {
		ap_back( 'Escolha a data do lembrete.', 'erro' );
	}
	ap_set_lead_reminder( $lead, $what, $date, $time );
	ap_back( 'Lembrete criado para ' . ap_date( $date ) . ' às ' . $time . '.' );
}

function ap_set_lead_reminder( $lead, $what, $date, $time = '09:00' ) {
	global $wpdb;
	// Um lembrete aberto por lead: o anterior é substituído.
	$wpdb->query( $wpdb->prepare( 'DELETE FROM ' . ap_table( 'tasks' ) . " WHERE lead_id = %d AND status <> 'done'", $lead->id ) ); // phpcs:ignore WordPress.DB.PreparedSQL
	ap_update( 'leads', $lead->id, array( 'next_action' => $what, 'next_at' => $date . ' ' . $time . ':00' ) );
	ap_insert(
		'tasks',
		array(
			'lead_id'     => (int) $lead->id,
			'grp'         => 'Lead',
			'title'       => $what . ' · ' . ap_lead_label( $lead ) . ' (' . $time . ')',
			'description' => 'Lead: ' . ap_panel_url( 'lead', $lead->id ) . ( $lead->whatsapp ? "\nWhatsApp: " . ap_wa_link( $lead->whatsapp ) : '' ),
			'due_date'    => $date,
			'priority'    => 'alta',
			'assignee'    => get_current_user_id() ? get_current_user_id() : ap_owner_id(),
			'created_by'  => get_current_user_id(),
		)
	);
	ap_lead_log( $lead->id, 'Lembrete: ' . $what . ' em ' . ap_date( $date ) . ' às ' . $time . '.' );
}

function ap_do_lead_lost() {
	ap_require( 'leads' );
	$id   = ap_in( 'lead_id', 'int' );
	$lost = ap_funnel_lost();
	if ( $id && $lost ) {
		ap_update( 'leads', $id, array( 'stage' => $lost, 'lost_reason' => ap_in( 'reason' ), 'next_at' => null, 'next_action' => '' ) );
		global $wpdb;
		$wpdb->query( $wpdb->prepare( 'DELETE FROM ' . ap_table( 'tasks' ) . " WHERE lead_id = %d AND status <> 'done'", $id ) ); // phpcs:ignore WordPress.DB.PreparedSQL
		ap_lead_log( $id, 'Marcado como perdido' . ( ap_in( 'reason' ) ? ': ' . ap_in( 'reason' ) : '.' ) );
	}
	ap_back( 'Lead marcado como perdido.' );
}

function ap_do_lead_delete() {
	ap_require( 'leads' );
	global $wpdb;
	$id = ap_in( 'id', 'int' );
	$wpdb->delete( ap_table( 'tasks' ), array( 'lead_id' => $id ) );
	$wpdb->delete( ap_table( 'activity' ), array( 'lead_id' => $id ) );
	ap_delete( 'leads', $id );
	ap_back( 'Lead excluído.', 'ok', ap_panel_url( 'leads' ) );
}

/**
 * Lead → cliente (com link de convite).
 */
function ap_lead_make_client( $lead ) {
	if ( $lead->client_id && ap_get( 'clients', $lead->client_id ) ) {
		return (int) $lead->client_id;
	}
	$cid = ap_insert(
		'clients',
		array(
			'name'         => $lead->name,
			'company'      => $lead->company ? $lead->company : $lead->name,
			'email'        => $lead->email,
			'whatsapp'     => $lead->whatsapp,
			'phone'        => $lead->whatsapp,
			'notes'        => $lead->notes,
			'source'       => $lead->source,
			'invite_token' => wp_generate_password( 32, false ),
			'status'       => 'convidado',
		)
	);
	ap_update( 'leads', $lead->id, array( 'client_id' => $cid ) );
	ap_lead_log( $lead->id, 'Virou cliente.' );
	return $cid;
}

function ap_do_lead_to_client() {
	ap_require( 'leads' );
	$lead = ap_get( 'leads', ap_in( 'lead_id', 'int' ) );
	if ( ! $lead ) {
		ap_back();
	}
	$cid = ap_lead_make_client( $lead );
	if ( ap_funnel_won() ) {
		ap_update( 'leads', $lead->id, array( 'stage' => ap_funnel_won() ) );
	}
	ap_back( 'Cliente criado. Mande o link de convite para ele criar o acesso.', 'ok', ap_panel_url( 'cliente', $cid ) );
}

/**
 * Lead → orçamento: abre o editor já ligado ao lead (e ao cliente, se já tiver).
 */
function ap_do_lead_to_quote() {
	ap_require( 'orcamentos' );
	$lead = ap_get( 'leads', ap_in( 'lead_id', 'int' ) );
	if ( ! $lead ) {
		ap_back();
	}
	$cid = ap_lead_make_client( $lead );
	ap_lead_log( $lead->id, 'Orçamento iniciado.' );
	ap_back( '', 'ok', ap_panel_url( 'orcamento', 0, array( 'lead' => $lead->id, 'cliente' => $cid ) ) );
}

// Orçamento do lead aprovado → limpa lembretes pendentes e registra.
add_action(
	'ap_quote_accepted',
	function ( $quote_id ) {
		global $wpdb;
		$q = ap_get( 'quotes', $quote_id );
		if ( ! $q || ! $q->lead_id ) {
			return;
		}
		ap_update( 'leads', $q->lead_id, array( 'next_at' => null, 'next_action' => '' ) );
		$wpdb->query( $wpdb->prepare( 'DELETE FROM ' . ap_table( 'tasks' ) . " WHERE lead_id = %d AND status <> 'done'", $q->lead_id ) ); // phpcs:ignore WordPress.DB.PreparedSQL
		ap_lead_log( $q->lead_id, 'Orçamento aprovado! Virou pedido. 🎉' );
	}
);

/**
 * Números do funil.
 */
function ap_lead_totals() {
	$won    = ap_funnel_won();
	$lost   = ap_funnel_lost();
	$all    = ap_leads();
	$open   = 0;
	$pipe   = 0;
	$wonc   = 0;
	$closed = 0;
	$today  = 0;
	foreach ( $all as $l ) {
		if ( $l->stage === $won ) {
			$wonc++;
			$closed++;
		} elseif ( $l->stage === $lost ) {
			$closed++;
		} else {
			$open++;
			$pipe += (float) $l->value;
			if ( $l->next_at && substr( $l->next_at, 0, 10 ) <= ap_today() ) {
				$today++;
			}
		}
	}
	return array(
		'open'     => $open,
		'pipeline' => $pipe,
		'followup' => $today,
		'rate'     => $closed ? (int) round( $wonc / $closed * 100 ) : 0,
	);
}

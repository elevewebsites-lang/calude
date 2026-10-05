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

function lk_funnel() {
	$out = array();
	foreach ( lk_list( lk_setting( 'funil' ) ) as $name ) {
		$out[ sanitize_title( $name ) ] = $name;
	}
	return $out ? $out : array( 'lead' => 'Lead', 'fechado' => 'Fechado', 'perdido' => 'Perdido' );
}

/**
 * Colunas "ganho" e "perdido" (pelas palavras do nome).
 */
function lk_funnel_won() {
	foreach ( lk_funnel() as $slug => $name ) {
		if ( preg_match( '/fechad|ganh|cliente/i', $name ) ) {
			return $slug;
		}
	}
	return '';
}

function lk_funnel_lost() {
	foreach ( lk_funnel() as $slug => $name ) {
		if ( preg_match( '/perdid/i', $name ) ) {
			return $slug;
		}
	}
	return '';
}

function lk_leads( $where = '1=1', $args = array(), $order = 'l.position, l.id DESC' ) {
	global $wpdb;
	$sql = 'SELECT l.*, s.name AS service_name FROM ' . lk_table( 'leads' ) . ' l LEFT JOIN ' . lk_table( 'services' ) . ' s ON s.id = l.service_id WHERE ' . $where . ' ORDER BY ' . $order;
	return $wpdb->get_results( $args ? $wpdb->prepare( $sql, $args ) : $sql ); // phpcs:ignore WordPress.DB.PreparedSQL
}

function lk_lead_label( $l ) {
	return $l->company ? $l->company : ( $l->name ? $l->name : ( $l->whatsapp ? $l->whatsapp : 'Lead #' . $l->id ) );
}

function lk_lead_log( $lead_id, $body, $type = 'log' ) {
	return lk_insert(
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

function lk_do_lead_save() {
	lk_require( 'leads' );
	$id     = lk_in( 'id', 'int' );
	$funnel = lk_funnel();
	$stage  = lk_in( 'stage' );
	$data   = array(
		'name'       => lk_in( 'name' ),
		'company'    => lk_in( 'company' ),
		'email'      => lk_in( 'email', 'email' ),
		'whatsapp'   => lk_in( 'whatsapp' ),
		'source'     => lk_in( 'source' ),
		'value'      => lk_in( 'value', 'money' ),
		'notes'      => lk_in( 'notes', 'textarea' ),
		'client_id'  => lk_in( 'client_id', 'int' ),
	);
	// Cliente que já existe: completa o que ficou em branco com o cadastro dele.
	$client = $data['client_id'] ? lk_get( 'clients', $data['client_id'] ) : null;
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
		lk_back( 'Preencha pelo menos o nome, a empresa ou o WhatsApp.', 'erro' );
	}
	if ( $id ) {
		$old = lk_get( 'leads', $id );
		lk_update( 'leads', $id, $data );
		if ( isset( $data['stage'] ) && $old && $old->stage !== $data['stage'] ) {
			lk_lead_log( $id, 'Movido para "' . $funnel[ $data['stage'] ] . '".' );
		}
		lk_back( 'Lead salvo.' );
	}
	if ( empty( $data['stage'] ) ) {
		$keys          = array_keys( $funnel );
		$data['stage'] = $keys[0];
	}
	$id = lk_insert( 'leads', $data );
	lk_lead_log( $id, 'Lead criado' . ( $data['source'] ? ' (origem: ' . $data['source'] . ')' : '' ) . ( $client ? ' para o cliente ' . lk_client_label( $client ) : '' ) . '.' );
	lk_back( 'Lead criado.', 'ok', lk_panel_url( 'lead', $id ) );
}

function lk_do_lead_note() {
	lk_require( 'leads' );
	$id   = lk_in( 'lead_id', 'int' );
	$body = lk_in( 'body', 'textarea' );
	if ( $id && $body ) {
		lk_lead_log( $id, $body, 'comment' );
		lk_update( 'leads', $id, array( 'last_contact' => lk_in( 'contact', 'bool' ) ? lk_now() : lk_get( 'leads', $id )->last_contact ) );
	}
	lk_back( 'Anotação salva.' );
}

/**
 * Lembrete de follow-up: guarda no lead e cria a tarefa com prazo.
 */
function lk_do_lead_reminder() {
	lk_require( 'leads' );
	$id   = lk_in( 'lead_id', 'int' );
	$lead = lk_get( 'leads', $id );
	$date = lk_in( 'date', 'date' );
	$time = preg_match( '/^\d{2}:\d{2}$/', lk_in( 'time' ) ) ? lk_in( 'time' ) : '09:00';
	$what = lk_in( 'what' ) ? lk_in( 'what' ) : 'Chamar no WhatsApp';
	if ( ! $lead || ! $date ) {
		lk_back( 'Escolha a data do lembrete.', 'erro' );
	}
	lk_set_lead_reminder( $lead, $what, $date, $time );
	lk_back( 'Lembrete criado para ' . lk_date( $date ) . ' às ' . $time . '.' );
}

function lk_set_lead_reminder( $lead, $what, $date, $time = '09:00' ) {
	global $wpdb;
	// Um lembrete aberto por lead: o anterior é substituído.
	$wpdb->query( $wpdb->prepare( 'DELETE FROM ' . lk_table( 'tasks' ) . " WHERE lead_id = %d AND status <> 'done'", $lead->id ) ); // phpcs:ignore WordPress.DB.PreparedSQL
	lk_update( 'leads', $lead->id, array( 'next_action' => $what, 'next_at' => $date . ' ' . $time . ':00' ) );
	lk_insert(
		'tasks',
		array(
			'lead_id'     => (int) $lead->id,
			'grp'         => 'Lead',
			'title'       => $what . ' · ' . lk_lead_label( $lead ) . ' (' . $time . ')',
			'description' => 'Lead: ' . lk_panel_url( 'lead', $lead->id ) . ( $lead->whatsapp ? "\nWhatsApp: " . lk_wa_link( $lead->whatsapp ) : '' ),
			'due_date'    => $date,
			'priority'    => 'alta',
			'assignee'    => get_current_user_id() ? get_current_user_id() : lk_owner_id(),
			'created_by'  => get_current_user_id(),
		)
	);
	lk_lead_log( $lead->id, 'Lembrete: ' . $what . ' em ' . lk_date( $date ) . ' às ' . $time . '.' );
}

function lk_do_lead_lost() {
	lk_require( 'leads' );
	$id   = lk_in( 'lead_id', 'int' );
	$lost = lk_funnel_lost();
	if ( $id && $lost ) {
		lk_update( 'leads', $id, array( 'stage' => $lost, 'lost_reason' => lk_in( 'reason' ), 'next_at' => null, 'next_action' => '' ) );
		global $wpdb;
		$wpdb->query( $wpdb->prepare( 'DELETE FROM ' . lk_table( 'tasks' ) . " WHERE lead_id = %d AND status <> 'done'", $id ) ); // phpcs:ignore WordPress.DB.PreparedSQL
		lk_lead_log( $id, 'Marcado como perdido' . ( lk_in( 'reason' ) ? ': ' . lk_in( 'reason' ) : '.' ) );
	}
	lk_back( 'Lead marcado como perdido.' );
}

function lk_do_lead_delete() {
	lk_require( 'leads' );
	global $wpdb;
	$id = lk_in( 'id', 'int' );
	$wpdb->delete( lk_table( 'tasks' ), array( 'lead_id' => $id ) );
	$wpdb->delete( lk_table( 'activity' ), array( 'lead_id' => $id ) );
	lk_delete( 'leads', $id );
	lk_back( 'Lead excluído.', 'ok', lk_panel_url( 'leads' ) );
}

/**
 * Lead → cliente (com link de convite).
 */
function lk_lead_make_client( $lead ) {
	if ( $lead->client_id && lk_get( 'clients', $lead->client_id ) ) {
		return (int) $lead->client_id;
	}
	$cid = lk_insert(
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
	lk_update( 'leads', $lead->id, array( 'client_id' => $cid ) );
	lk_lead_log( $lead->id, 'Virou cliente.' );
	return $cid;
}

function lk_do_lead_to_client() {
	lk_require( 'leads' );
	$lead = lk_get( 'leads', lk_in( 'lead_id', 'int' ) );
	if ( ! $lead ) {
		lk_back();
	}
	$cid = lk_lead_make_client( $lead );
	if ( lk_funnel_won() ) {
		lk_update( 'leads', $lead->id, array( 'stage' => lk_funnel_won() ) );
	}
	lk_back( 'Cliente criado. Mande o link de convite para ele criar o acesso.', 'ok', lk_panel_url( 'cliente', $cid ) );
}

/**
 * Lead → orçamento: abre o editor já ligado ao lead (e ao cliente, se já tiver).
 */
function lk_do_lead_to_quote() {
	lk_require( 'orcamentos' );
	$lead = lk_get( 'leads', lk_in( 'lead_id', 'int' ) );
	if ( ! $lead ) {
		lk_back();
	}
	$cid = lk_lead_make_client( $lead );
	lk_lead_log( $lead->id, 'Orçamento iniciado.' );
	lk_back( '', 'ok', lk_panel_url( 'orcamento', 0, array( 'lead' => $lead->id, 'cliente' => $cid ) ) );
}

// Orçamento do lead aprovado → limpa lembretes pendentes e registra.
add_action(
	'lk_quote_accepted',
	function ( $quote_id ) {
		global $wpdb;
		$q = lk_get( 'quotes', $quote_id );
		if ( ! $q || ! $q->lead_id ) {
			return;
		}
		lk_update( 'leads', $q->lead_id, array( 'next_at' => null, 'next_action' => '' ) );
		$wpdb->query( $wpdb->prepare( 'DELETE FROM ' . lk_table( 'tasks' ) . " WHERE lead_id = %d AND status <> 'done'", $q->lead_id ) ); // phpcs:ignore WordPress.DB.PreparedSQL
		lk_lead_log( $q->lead_id, 'Orçamento aprovado! Virou pedido. 🎉' );
	}
);

/**
 * Números do funil.
 */
function lk_lead_totals() {
	$won    = lk_funnel_won();
	$lost   = lk_funnel_lost();
	$all    = lk_leads();
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
			if ( $l->next_at && substr( $l->next_at, 0, 10 ) <= lk_today() ) {
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

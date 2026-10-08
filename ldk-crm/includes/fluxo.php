<?php
/**
 * Fluxo da agência (v1.30):
 *   Social media monta o planejamento → administradora revisa → TAREFA para o atendimento enviar ao cliente
 *   (e-mail + WhatsApp) → cliente aprova/pede ajuste → ajuste vira TAREFA de quem refaz (com áudio e referência)
 *   → "Refiz" avisa o atendimento → semana pronta avisa o atendimento → cliente aprova → agendado.
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/* -----------------------------------------------------------------------
 * Tarefas do fluxo
 * -------------------------------------------------------------------- */

function lk_flow_fallback_user() {
	$a = get_users( array( 'role' => 'administrator', 'number' => 1, 'fields' => 'ID' ) );
	return $a ? (int) $a[0] : 0;
}

/** Cria uma tarefa e avisa a pessoa. $url abre a tela certa. */
function lk_flow_task( $assignee, $title, $desc = '', $url = '', $days = 0, $priority = 'alta', $group = 'Fluxo', $client_id = 0 ) {
	$assignee = (int) $assignee ? (int) $assignee : lk_flow_fallback_user();
	$id       = lk_insert(
		'tasks',
		array(
			'grp'         => $group,
			'title'       => $title,
			'description' => trim( $desc . ( $url ? "\n\n" . $url : '' ) ),
			'status'      => 'todo',
			'priority'    => $priority,
			'due_date'    => gmdate( 'Y-m-d', strtotime( lk_today() . ' +' . (int) $days . ' day' ) ),
			'assignee'    => $assignee,
			'client_id'   => (int) $client_id,
			'created_at'  => lk_now(),
		)
	);
	if ( $assignee ) {
		lk_notify( $assignee, '📌 ' . $title, $url ? $url : lk_panel_url( 'tarefas' ) );
	}
	return $id;
}

/** Fecha as tarefas do fluxo que carregam um marcador, ex.: [plan:12]. */
function lk_flow_task_done( $marker ) {
	global $wpdb;
	$wpdb->query( $wpdb->prepare( 'UPDATE ' . lk_table( 'tasks' ) . " SET status = 'done', done_at = %s WHERE grp = 'Fluxo' AND status <> 'done' AND description LIKE %s", lk_now(), '%' . $wpdb->esc_like( $marker ) . '%' ) ); // phpcs:ignore WordPress.DB.PreparedSQL
}

function lk_flow_atendimento( $client ) {
	return (int) $client->atendimento_id ? (int) $client->atendimento_id : lk_flow_fallback_user();
}

/* -----------------------------------------------------------------------
 * Planejamento: depois da revisão da administradora, o atendimento envia
 * -------------------------------------------------------------------- */

function lk_plan_mark_ready( $plan, $by_note = '' ) {
	$client = lk_get( 'clients', $plan->client_id );
	lk_update( 'plans', $plan->id, array( 'status' => 'pronto', 'reviewed_by' => get_current_user_id(), 'reviewed_at' => lk_now(), 'review_note' => '' ) );
	$url = lk_panel_url( 'planejamento', 0, array( 'cliente' => $client->id, 'mes' => $plan->period ) );
	lk_flow_task(
		lk_flow_atendimento( $client ),
		'Enviar o planejamento de ' . lk_client_label( $client ) . ' (' . lk_month_label( $plan->period ) . ') ao cliente',
		( $by_note ? $by_note . "\n" : '' ) . 'Revisado e aprovado. Abra e clique em "Enviar ao cliente" para mandar por e-mail e WhatsApp. [plan:' . (int) $plan->id . ']',
		$url,
		0
	);
}

/** O atendimento envia o planejamento pronto (e-mail + WhatsApp com mensagem pronta). */
function lk_do_plan_send_ready() {
	lk_require( 'conteudo' );
	$plan = lk_get( 'plans', lk_in( 'id', 'int' ) );
	if ( ! $plan || ! in_array( $plan->status, array( 'pronto', 'enviado', 'ajustes' ), true ) ) {
		lk_back( 'Este planejamento ainda não está pronto para enviar.', 'erro' );
	}
	lk_plan_send_client( lk_get( 'plans', $plan->id ) );
	lk_flow_task_done( '[plan:' . (int) $plan->id . ']' );
	lk_back( 'Enviado ao cliente por e-mail. Agora clique em "Mandar no WhatsApp".' );
}

/** A social media refez um conteúdo pedido pelo cliente no planejamento. */
function lk_do_plan_post_redone() {
	lk_require( 'conteudo' );
	$p = lk_get( 'posts', lk_in( 'id', 'int' ) );
	if ( ! $p || 'ajuste' !== $p->plan_status ) {
		lk_back( 'Este conteúdo não está esperando refazer.', 'erro' );
	}
	$client = lk_get( 'clients', $p->client_id );
	$plan   = $p->plan_id ? lk_get( 'plans', $p->plan_id ) : null;
	lk_update( 'posts', $p->id, array( 'plan_status' => '' ) );
	lk_post_log( $p->id, 'Conteúdo refeito após o pedido do cliente.' );
	if ( $plan ) {
		lk_flow_task_done( '[ajuste:' . (int) $p->id . ']' );
		$left = 0;
		foreach ( lk_plan_posts( $plan ) as $x ) {
			if ( 'ajuste' === $x->plan_status ) {
				$left++;
			}
		}
		$url = lk_panel_url( 'planejamento', 0, array( 'cliente' => $client->id, 'mes' => $plan->period ) );
		$wa  = $client->whatsapp ? lk_wa_link( $client->whatsapp, "Olá, " . strtok( (string) $client->name, ' ' ) . "! 😊\n\nAjustamos o conteúdo *" . $p->title . "* do planejamento, como você pediu.\n\n👉 Dá uma olhada e aprove por aqui:\n" . lk_plan_url( $plan ) ) : '';
		lk_flow_task(
			lk_flow_atendimento( $client ),
			'Refeito: avise ' . lk_client_label( $client ) . ' sobre "' . $p->title . '"',
			'O pedido do cliente foi refeito. Mande o planejamento de novo, falando desse conteúdo.' . ( $wa ? "\nWhatsApp pronto: " . $wa : '' ) . ' [plan:' . (int) $plan->id . ']',
			$url,
			0
		);
		if ( ! $left && 'ajustes' === $plan->status ) {
			lk_update( 'plans', $plan->id, array( 'status' => 'pronto' ) );
		}
	}
	lk_back( 'Refeito! O atendimento foi avisado para mandar de novo ao cliente.' );
}

/** Quando o conteúdo é aprovado no planejamento: a legenda vira tarefa da social media se ainda não existe. */
function lk_flow_after_plan_ok( $p ) {
	if ( '' !== trim( (string) $p->caption ) ) {
		return;
	}
	$client = lk_get( 'clients', $p->client_id );
	lk_flow_task(
		$p->social_id ? (int) $p->social_id : (int) $client->social_id,
		'Escrever a legenda: ' . $p->title . ' · ' . lk_client_label( $client ),
		'O planejamento foi aprovado e este conteúdo ainda não tem legenda. [legenda:' . (int) $p->id . ']',
		lk_panel_url( 'post', $p->id ),
		2,
		'normal'
	);
}

/** O cliente pediu ajuste num conteúdo do planejamento: tarefa para a social media refazer. */
function lk_flow_plan_adjust_task( $p, $body ) {
	$client = lk_get( 'clients', $p->client_id );
	lk_flow_task(
		$p->social_id ? (int) $p->social_id : (int) $client->social_id,
		'Refazer no planejamento: ' . $p->title . ' · ' . lk_client_label( $client ),
		'Pedido do cliente: ' . ( $body ? $body : '(sem comentário)' ) . ' [ajuste:' . (int) $p->id . ']',
		lk_panel_url( 'planejamento', 0, array( 'cliente' => $client->id, 'mes' => $p->plan_id && lk_get( 'plans', $p->plan_id ) ? lk_get( 'plans', $p->plan_id )->period : substr( (string) $p->scheduled_at, 0, 7 ) ) ),
		2
	);
}

/* -----------------------------------------------------------------------
 * Alterações pedidas pelo cliente (arte e/ou legenda) → tarefa com prazo
 * -------------------------------------------------------------------- */

function lk_flow_alter_days() {
	$d = (int) lk_setting( 'prazo_alteracao' );
	return $d > 0 ? $d : 2;
}

/**
 * $about: arte, legenda, ambos ou video. O texto e o anexo (áudio/imagem/link) do cliente vão na tarefa.
 */
function lk_flow_alter_tasks( $p, $about, $text, $attach = null ) {
	$client = lk_get( 'clients', $p->client_id );
	$url    = lk_panel_url( 'post', $p->id ) . '#apontamentos';
	$desc   = 'O cliente pediu: ' . ( $text ? $text : '(veja o áudio ou a referência)' ) . ( $attach ? "\nAnexo do cliente: " . $attach['url'] : '' );
	$to     = array();
	if ( in_array( $about, array( 'arte', 'ambos', 'video' ), true ) ) {
		$to['arte'] = $p->designer_id ? (int) $p->designer_id : (int) $client->designer_id;
	}
	if ( in_array( $about, array( 'legenda', 'ambos' ), true ) ) {
		$to['legenda'] = $p->social_id ? (int) $p->social_id : (int) $client->social_id;
	}
	foreach ( $to as $what => $uid ) {
		lk_flow_task( $uid, 'Alteração na ' . ( 'arte' === $what ? ( 'video' === $about ? 'edição do vídeo' : 'arte' ) : 'legenda' ) . ': ' . $p->title . ' · ' . lk_client_label( $client ), $desc . ' [alt:' . (int) $p->id . ':' . $what . ']', $url, lk_flow_alter_days() );
	}
}

/** Fecha as tarefas de alteração do post (de arte ou legenda) e avisa o atendimento para reenviar. */
function lk_flow_alter_done( $p, $what ) {
	lk_flow_task_done( '[alt:' . (int) $p->id . ':' . $what . ']' );
	$client = lk_get( 'clients', $p->client_id );
	lk_update( 'posts', $p->id, array( 'resend' => 1 ) );
	lk_flow_task(
		lk_flow_atendimento( $client ),
		'Alteração feita: enviar "' . $p->title . '" de novo a ' . lk_client_label( $client ),
		'O ' . ( 'arte' === $what ? 'designer' : 'social media' ) . ' terminou o ajuste da ' . $what . '. Revise e mande ao cliente falando desse conteúdo. [reenviar:' . (int) $p->id . ']',
		lk_panel_url( 'enviar' ),
		0
	);
}

/** A arte foi refeita (chamada quando o designer salva a arte pronta de uma alteração). */
function lk_flow_art_redone( $p ) {
	lk_flow_alter_done( $p, 'arte' );
}

/** "Refiz a legenda": a social media terminou o ajuste da legenda. */
function lk_do_post_redone_caption() {
	lk_require( 'conteudo' );
	$p = lk_get( 'posts', lk_in( 'id', 'int' ) );
	if ( ! $p || ! in_array( $p->change_target, array( 'legenda', 'ambos' ), true ) ) {
		lk_back( 'Este post não está esperando ajuste de legenda.', 'erro' );
	}
	lk_post_log( $p->id, 'Legenda refeita após o pedido do cliente.' );
	if ( 'ambos' === $p->change_target ) {
		lk_update( 'posts', $p->id, array( 'change_target' => 'arte' ) );
		lk_flow_task_done( '[alt:' . (int) $p->id . ':legenda]' );
		lk_back( 'Legenda refeita. Falta só a arte (o designer segue com ela).' );
	}
	lk_update( 'posts', $p->id, array( 'change_target' => '' ) );
	lk_post_move( $p, lk_stage_for( 'revisao' ), 'Legenda ajustada, pronta para revisar.' );
	lk_flow_alter_done( lk_get( 'posts', $p->id ), 'legenda' );
	lk_back( 'Legenda refeita! O atendimento foi avisado para enviar ao cliente.' );
}

/* -----------------------------------------------------------------------
 * Telas: Alterações e Enviar ao cliente (contadores do menu)
 * -------------------------------------------------------------------- */

/** Posts com alteração pendente que são da pessoa (admin vê todos). */
function lk_flow_alterations( $user_id = 0 ) {
	$user_id = $user_id ? $user_id : get_current_user_id();
	$out     = array();
	foreach ( lk_posts( "p.client_status = 'alteracao' OR p.change_target <> ''", array(), 'p.scheduled_at, p.id' ) as $p ) {
		$t = $p->change_target;
		if ( lk_is_admin( $user_id ) ) {
			$out[] = $p;
			continue;
		}
		$mine = ( in_array( $t, array( 'arte', 'ambos' ), true ) && (int) $p->designer_id === (int) $user_id )
			|| ( in_array( $t, array( 'legenda', 'ambos' ), true ) && (int) $p->social_id === (int) $user_id )
			|| ( $t && (int) $p->atendimento_id === (int) $user_id );
		if ( $mine ) {
			$out[] = $p;
		}
	}
	// Apontamentos do cliente ainda abertos.
	$have = array_map( function ( $p ) { return (int) $p->id; }, $out );
	foreach ( lk_rows( 'post_comments', "target = 'apontamento' AND from_client = 1 AND resolved = 0 AND internal = 0", array(), 'id DESC' ) as $n ) {
		if ( in_array( (int) $n->post_id, $have, true ) ) {
			continue;
		}
		$p = lk_get( 'posts', $n->post_id );
		if ( $p && ( lk_is_admin( $user_id ) || (int) $n->assignee === (int) $user_id ) ) {
			$out[] = $p;
			$have[] = (int) $p->id;
		}
	}
	return $out;
}

/** O que está pronto para o atendimento mandar ao cliente. */
function lk_flow_ready_items() {
	$items = array( 'plans' => array(), 'resend' => array(), 'weeks' => array() );
	foreach ( lk_rows( 'plans', "status = 'pronto'", array(), 'id DESC' ) as $pl ) {
		$items['plans'][] = $pl;
	}
	foreach ( lk_posts( 'p.resend = 1', array(), 'p.scheduled_at, p.id' ) as $p ) {
		$items['resend'][] = $p;
	}
	list( $from, $to ) = lk_week_range( gmdate( 'Y-m-d', strtotime( lk_today() . ' +3 day' ) ) );
	foreach ( lk_clients() as $c ) {
		$posts = lk_week_posts( $c->id, $from, $to );
		if ( $posts && lk_week_all_ready( $c->id, $from, $to ) ) {
			$items['weeks'][] = array( 'client' => $c, 'posts' => $posts, 'from' => $from, 'to' => $to );
		}
	}
	return $items;
}

/** Todos os posts da semana do cliente têm arte e legenda e já passaram do design? */
function lk_week_all_ready( $client_id, $from, $to ) {
	$any = false;
	foreach ( lk_posts( 'p.client_id = %d AND p.scheduled_at >= %s AND p.scheduled_at <= %s', array( (int) $client_id, $from . ' 00:00:00', $to . ' 23:59:59' ), 'p.id' ) as $p ) {
		$any = true;
		$ok  = in_array( $p->stage, array( lk_stage_for( 'revisao' ), lk_stage_for( 'aprovacao' ), lk_stage_for( 'agendado' ), lk_stage_for( 'publicado' ) ), true ) && lk_post_media( $p ) && '' !== trim( (string) $p->caption );
		if ( ! $ok ) {
			return false;
		}
	}
	return $any;
}

function lk_flow_menu_count( $slug ) {
	static $c = array();
	if ( isset( $c[ $slug ] ) ) {
		return $c[ $slug ];
	}
	if ( 'alteracoes' === $slug ) {
		$c[ $slug ] = count( lk_flow_alterations() );
	} elseif ( 'enviar' === $slug ) {
		$i          = lk_flow_ready_items();
		$c[ $slug ] = count( $i['plans'] ) + count( $i['resend'] ) + count( $i['weeks'] );
	} else {
		$c[ $slug ] = 0;
	}
	return $c[ $slug ];
}

/* -----------------------------------------------------------------------
 * Semana fechada: avisa o atendimento que o conteúdo da semana está pronto
 * -------------------------------------------------------------------- */

add_action( 'lk_daily', 'lk_flow_week_watch' );
add_action( 'lk_hourly', 'lk_flow_week_watch', 96 );
function lk_flow_week_watch() {
	if ( get_transient( 'lk_flow_week_run' ) ) {
		return;
	}
	set_transient( 'lk_flow_week_run', 1, 3 * HOUR_IN_SECONDS );
	// Quinta em diante olha a semana que vem; segunda a quarta, a semana atual.
	list( $from, $to ) = lk_week_range( gmdate( 'Y-m-d', strtotime( lk_today() . ' +3 day' ) ) );
	foreach ( lk_clients() as $c ) {
		$mark = 'lk_flow_week_' . $c->id . '_' . $from;
		if ( get_option( $mark ) ) {
			continue;
		}
		$posts = lk_week_posts( $c->id, $from, $to );
		if ( ! $posts || ! lk_week_all_ready( $c->id, $from, $to ) ) {
			continue;
		}
		update_option( $mark, 1, false );
		lk_flow_task(
			lk_flow_atendimento( $c ),
			'Semana pronta: enviar o conteúdo de ' . lk_client_label( $c ) . ' (' . lk_date( $from, 'd/m' ) . ' a ' . lk_date( $to, 'd/m' ) . ')',
			count( $posts ) . ' post(s) com arte e legenda prontos. Abra "Enviar a semana" e mande ao cliente por e-mail e WhatsApp.',
			lk_panel_url( 'semana', 0, array( 'cliente' => $c->id, 'semana' => $from ) ),
			0,
			'alta',
			'Fluxo',
			$c->id
		);
	}
}

/* -----------------------------------------------------------------------
 * Roteiros do mesmo dia gravam juntos: um envio só ao cliente
 * -------------------------------------------------------------------- */

function lk_do_script_send_day() {
	lk_require( 'conteudo' );
	$client = lk_get( 'clients', lk_in( 'client_id', 'int' ) );
	$date   = lk_in( 'date', 'date' );
	if ( ! $client || ! $date ) {
		lk_back( 'Cliente ou dia não encontrado.', 'erro' );
	}
	$list = array();
	foreach ( lk_rows( 'scripts', 'client_id = %d AND record_date = %s', array( $client->id, $date ), 'id' ) as $s ) {
		if ( '' !== trim( (string) $s->body ) ) {
			$list[] = $s;
			lk_update( 'scripts', $s->id, array( 'status' => 'aprovado' === $s->status ? 'aprovado' : 'enviado', 'sent_at' => lk_now() ) );
		}
	}
	if ( ! $list ) {
		lk_back( 'Nenhum roteiro com texto nesse dia.', 'erro' );
	}
	$mail = is_email( $client->email ) && ! $client->email_optout && lk_script_mail( $client, $list, 'Roteiros da gravação de ' . lk_date( $date, 'd/m' ), 'Os roteiros da sua gravação' );
	$msg  = 'Olá, ' . strtok( (string) $client->name, ' ' ) . "! 😊\n\nEstes são os roteiros da gravação de *" . lk_date( $date, 'd/m' ) . "*:\n\n";
	foreach ( $list as $i => $s ) {
		$msg .= ( $i + 1 ) . '. *' . $s->title . "*\n" . lk_script_url( $s ) . "\n\n";
	}
	$msg .= '👉 Abra cada link, confira e aprove.';
	$wa   = $client->whatsapp ? lk_wa_link( $client->whatsapp, $msg ) : '';
	set_transient( 'lk_day_wa_' . get_current_user_id(), array( 'client' => $client->id, 'date' => $date, 'wa' => $wa ), 900 );
	lk_back( count( $list ) . ' roteiro(s) do dia ' . lk_date( $date, 'd/m' ) . ( $mail ? ' enviados por e-mail.' : ' marcados como enviados.' ) . ' Agora é só mandar no WhatsApp.' );
}

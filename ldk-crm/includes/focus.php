<?php
/**
 * Modo foco: você escolhe tarefas e projetos, entra numa tela limpa com timer (Pomodoro)
 * e o tempo focado fica registrado por tarefa e projeto.
 *
 *   /painel/foco                          escolher o que fazer
 *   /painel/foco?itens[]=t12&itens[]=p3   tela de foco
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Ciclos do Pomodoro: chave => [rótulo, minutos de foco, pausa curta, pausa longa].
 */
function lk_focus_cycles() {
	return array(
		'25-5'  => array( 'Pomodoro clássico · 25 min + 5 de pausa', 25, 5, 15 ),
		'50-10' => array( 'Bloco longo · 50 min + 10 de pausa', 50, 10, 20 ),
		'90-20' => array( 'Trabalho profundo · 90 min + 20 de pausa', 90, 20, 30 ),
		'livre' => array( 'Cronômetro livre (sem pausas)', 0, 0, 0 ),
	);
}

/**
 * Tarefas que o usuário pode escolher: as dele (ou todas, para o admin), abertas,
 * das mais urgentes para as sem prazo.
 */
function lk_focus_task_options() {
	$where = "t.status <> 'done'";
	$args  = array();
	if ( ! lk_is_admin() && ! lk_can( 'tarefas' ) ) {
		$where .= ' AND t.assignee = %d';
		$args[] = get_current_user_id();
	}
	return lk_tasks( $where, $args, "t.due_date IS NULL, t.due_date, FIELD(t.priority, 'urgente', 'alta', 'normal', 'baixa'), t.id LIMIT 80" );
}

function lk_focus_project_options() {
	// Na LDK, "p" são os posts em que a pessoa é a responsável agora.
	$me = get_current_user_id();
	return array_values(
		array_filter(
			lk_posts( 'p.stage NOT IN (%s, %s)', array( lk_stage_for( 'agendado' ), lk_stage_for( 'publicado' ) ) ),
			function ( $p ) use ( $me ) {
				return lk_post_owner( $p ) === $me || lk_is_admin();
			}
		)
	);
}

/**
 * Itens escolhidos ("t12", "p3") viram a lista da tela de foco.
 */
function lk_focus_items( $raw ) {
	$items = array();
	foreach ( (array) $raw as $code ) {
		$code = sanitize_key( $code );
		if ( ! preg_match( '/^([tp])(\d+)$/', $code, $m ) ) {
			continue;
		}
		if ( 't' === $m[1] ) {
			$rows = lk_tasks( 't.id = %d', array( (int) $m[2] ) );
			$t    = $rows ? $rows[0] : null;
			if ( ! $t || ( ! lk_can( 'projetos' ) && ! lk_can( 'tarefas' ) && (int) $t->assignee !== get_current_user_id() ) ) {
				continue;
			}
			$items[] = array(
				'type'    => 'task',
				'id'      => (int) $t->id,
				'project' => (int) $t->project_id,
				'title'   => $t->title,
				'context' => trim( ( $t->project_title ? $t->project_title : '' ) . ( $t->grp ? ' › ' . $t->grp : '' ), ' ›' ),
				'done'    => 'done' === $t->status,
				'tasks'   => array(),
			);
		} else {
			$rows = lk_posts( 'p.id = %d', array( (int) $m[2] ) );
			$p    = $rows ? $rows[0] : null;
			if ( ! $p ) {
				continue;
			}
			$items[] = array(
				'type'    => 'project',
				'id'      => (int) $p->id,
				'project' => 0,
				'title'   => $p->title . ' (' . ( lk_formats()[ $p->format ] ?? '' ) . ')',
				'context' => lk_post_client_label( $p ) . ' · ' . ( lk_stages()[ $p->stage ] ?? '' ),
				'done'    => false,
				'tasks'   => array(),
			);
		}
	}
	return $items;
}

/**
 * Segundos focados pelo usuário desde uma data (AAAA-MM-DD).
 */
function lk_focus_seconds_since( $since, $user_id = 0 ) {
	global $wpdb;
	return (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COALESCE(SUM(seconds),0) FROM ' . lk_table( 'focus' ) . ' WHERE user_id = %d AND created_at >= %s', $user_id ? $user_id : get_current_user_id(), $since . ' 00:00:00' ) ); // phpcs:ignore WordPress.DB.PreparedSQL
}

function lk_focus_duration( $seconds ) {
	$m = (int) round( $seconds / 60 );
	if ( $m < 60 ) {
		return $m . ' min';
	}
	return floor( $m / 60 ) . 'h' . ( $m % 60 ? ' ' . str_pad( (string) ( $m % 60 ), 2, '0', STR_PAD_LEFT ) : '' );
}

/**
 * "Entrar em foco": cria as tarefas rápidas escritas na hora e abre a tela de foco com tudo junto.
 */
function lk_do_focus_start() {
	if ( ! lk_is_team() ) {
		wp_die( 'Sem permissão.' );
	}
	$items = array();
	foreach ( (array) ( isset( $_POST['itens'] ) ? wp_unslash( $_POST['itens'] ) : array() ) as $code ) { // phpcs:ignore WordPress.Security.NonceVerification,WordPress.Security.ValidatedSanitizedInput
		$code = sanitize_key( $code );
		if ( preg_match( '/^[tp]\d+$/', $code ) ) {
			$items[] = $code;
		}
	}
	foreach ( array_slice( lk_list( lk_in( 'novas', 'textarea' ) ), 0, 10 ) as $line ) {
		$tid     = lk_insert(
			'tasks',
			array(
				'title'      => mb_substr( $line, 0, 250 ),
				'status'     => 'todo',
				'priority'   => 'normal',
				'due_date'   => lk_today(),
				'assignee'   => get_current_user_id(),
				'created_by' => get_current_user_id(),
			)
		);
		$items[] = 't' . $tid;
	}
	if ( ! $items ) {
		lk_back( 'Escolha pelo menos uma tarefa ou projeto.', 'erro' );
	}
	$cycle = sanitize_key( lk_in( 'ciclo' ) );
	wp_safe_redirect( lk_panel_url( 'foco', 0, array( 'itens' => $items, 'ciclo' => $cycle, 'meta' => lk_in( 'meta' ) ) ) );
	exit;
}

add_action(
	'rest_api_init',
	function () {
		register_rest_route(
			'lk/v1',
			'/focus/log',
			array(
				'methods'             => 'POST',
				'permission_callback' => function () {
					return lk_is_team();
				},
				'callback'            => 'lk_api_focus_log',
			)
		);
	}
);

/**
 * Registra um bloco de foco (chamado pelo timer ao fim de cada ciclo ou ao sair).
 */
function lk_api_focus_log( WP_REST_Request $r ) {
	$seconds = min( 4 * HOUR_IN_SECONDS, absint( $r['seconds'] ) );
	if ( $seconds < 30 ) {
		return array( 'ok' => true, 'skipped' => true );
	}
	$task_id    = absint( $r['task_id'] );
	$project_id = absint( $r['project_id'] );
	if ( $task_id && ! $project_id ) {
		$t          = lk_get( 'tasks', $task_id );
		$project_id = $t ? (int) $t->project_id : 0;
	}
	lk_insert(
		'focus',
		array(
			'user_id'    => get_current_user_id(),
			'task_id'    => $task_id,
			'project_id' => $project_id,
			'seconds'    => $seconds,
		)
	);
	return array( 'ok' => true, 'today' => lk_focus_seconds_since( lk_today() ) );
}

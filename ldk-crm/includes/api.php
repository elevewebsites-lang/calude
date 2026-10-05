<?php
/**
 * Endpoints usados pelo JavaScript do painel (salvam na hora, sem recarregar).
 * Namespace: /wp-json/lk/v1
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_action( 'rest_api_init', 'lk_rest_routes' );
function lk_rest_routes() {
	$team = function () {
		return lk_is_team();
	};

	register_rest_route(
		'lk/v1',
		'/move',
		array(
			'methods'             => 'POST',
			'permission_callback' => $team,
			'callback'            => 'lk_api_move',
		)
	);
	register_rest_route(
		'lk/v1',
		'/columns',
		array(
			'methods'             => 'POST',
			'permission_callback' => $team,
			'callback'            => 'lk_api_columns',
		)
	);
	register_rest_route(
		'lk/v1',
		'/task/(?P<id>\d+)/toggle',
		array(
			'methods'             => 'POST',
			'permission_callback' => $team,
			'callback'            => 'lk_api_task_toggle',
		)
	);
	register_rest_route(
		'lk/v1',
		'/task/(?P<id>\d+)/flag',
		array(
			'methods'             => 'POST',
			'permission_callback' => $team,
			'callback'            => 'lk_api_task_flag',
		)
	);
	register_rest_route(
		'lk/v1',
		'/task/(?P<id>\d+)/review',
		array(
			'methods'             => 'POST',
			'permission_callback' => $team,
			'callback'            => 'lk_api_task_review',
		)
	);
	register_rest_route(
		'lk/v1',
		'/search',
		array(
			'methods'             => 'GET',
			'permission_callback' => $team,
			'callback'            => 'lk_api_search',
		)
	);
	register_rest_route(
		'lk/v1',
		'/access/(?P<id>\d+)',
		array(
			'methods'             => 'GET',
			'permission_callback' => 'is_user_logged_in',
			'callback'            => 'lk_api_access_secret',
		)
	);
}

/**
 * Kanban: move um card de coluna e salva a nova ordem.
 * { type: "project"|"task", id, status, order: [ids na coluna] }
 */
function lk_api_move( WP_REST_Request $r ) {
	$type   = in_array( $r['type'], array( 'project', 'lead' ), true ) ? $r['type'] : 'task';
	$id     = absint( $r['id'] );
	$status = sanitize_key( $r['status'] );
	$order  = array_map( 'absint', (array) $r['order'] );

	if ( 'lead' === $type ) {
		$funnel = lk_funnel();
		$lead   = lk_get( 'leads', $id );
		if ( ! lk_can( 'leads' ) || ! $lead || ! isset( $funnel[ $status ] ) ) {
			return new WP_Error( 'lk', 'Sem permissão.', array( 'status' => 403 ) );
		}
		lk_update( 'leads', $id, array( 'stage' => $status ) );
		foreach ( $order as $pos => $lid ) {
			lk_update( 'leads', $lid, array( 'position' => $pos ) );
		}
		if ( $lead->stage !== $status ) {
			lk_lead_log( $id, 'Movido para "' . $funnel[ $status ] . '".' );
		}
		return array( 'ok' => true );
	}

	if ( 'project' === $type ) {
		$internal = lk_board_internal();
		if ( ! lk_can( 'projetos' ) || ( ! isset( lk_columns()[ $status ] ) && ! isset( $internal[ $status ] ) ) ) {
			return new WP_Error( 'lk', 'Sem permissão.', array( 'status' => 403 ) );
		}
		$old = lk_get( 'projects', $id );
		if ( ! $old ) {
			return new WP_Error( 'lk', 'Projeto não encontrado.', array( 'status' => 404 ) );
		}
		// Coluna interna (ex.: "Hoje"): só organiza o quadro, a etapa real não muda.
		if ( isset( $internal[ $status ] ) ) {
			lk_update( 'projects', $id, array( 'board_col' => $status ) );
			foreach ( $order as $pos => $pid ) {
				lk_update( 'projects', $pid, array( 'position' => $pos ) );
			}
			return array( 'ok' => true );
		}
		lk_update( 'projects', $id, array( 'status' => $status, 'board_col' => '' ) );
		foreach ( $order as $pos => $pid ) {
			lk_update( 'projects', $pid, array( 'position' => $pos ) );
		}
		if ( $old->status !== $status ) {
			lk_project_moved( $id, $status );
		}
		return array( 'ok' => true );
	}

	$task = lk_get( 'tasks', $id );
	if ( ! $task || ! array_key_exists( $status, lk_task_statuses() ) ) {
		return new WP_Error( 'lk', 'Tarefa não encontrada.', array( 'status' => 404 ) );
	}
	if ( ! lk_can( 'tarefas' ) && ! lk_can( 'projetos' ) && (int) $task->assignee !== get_current_user_id() ) {
		return new WP_Error( 'lk', 'Sem permissão.', array( 'status' => 403 ) );
	}
	lk_update(
		'tasks',
		$id,
		array(
			'status'  => $status,
			'done_at' => 'done' === $status ? ( $task->done_at ? $task->done_at : lk_now() ) : null,
		)
	);
	foreach ( $order as $pos => $tid ) {
		lk_update( 'tasks', $tid, array( 'position' => $pos ) );
	}
	return array( 'ok' => true );
}

/**
 * Marca/desmarca uma etapa como feita (checklist do projeto).
 */
function lk_api_task_toggle( WP_REST_Request $r ) {
	$task = lk_get( 'tasks', absint( $r['id'] ) );
	if ( ! $task ) {
		return new WP_Error( 'lk', 'Tarefa não encontrada.', array( 'status' => 404 ) );
	}
	if ( ! lk_can( 'projetos' ) && ! lk_can( 'tarefas' ) && (int) $task->assignee !== get_current_user_id() ) {
		return new WP_Error( 'lk', 'Sem permissão.', array( 'status' => 403 ) );
	}
	$done = 'done' !== $task->status;
	lk_update(
		'tasks',
		$task->id,
		array(
			'status'  => $done ? 'done' : 'todo',
			'done_at' => $done ? lk_now() : null,
		)
	);
	if ( $done && $task->lead_id ) {
		lk_update( 'leads', $task->lead_id, array( 'last_contact' => lk_now(), 'next_at' => null, 'next_action' => '' ) );
		lk_lead_log( $task->lead_id, 'Follow-up feito: ' . $task->title );
	}
	if ( $done && $task->project_id && $task->client_visible ) {
		lk_log( $task->project_id, 'Concluído: ' . ( $task->grp ? $task->grp . ' › ' : '' ) . $task->title, true, $task->id );
	}
	$progress = $task->project_id ? lk_progress( $task->project_id ) : null;
	return array(
		'ok'       => true,
		'done'     => $done,
		'progress' => $progress,
	);
}

/**
 * Liga/desliga "o cliente vê" e "precisa de aprovação" direto na lista.
 */
function lk_api_task_flag( WP_REST_Request $r ) {
	$task = lk_get( 'tasks', absint( $r['id'] ) );
	$flag = in_array( $r['flag'], array( 'client_visible', 'needs_approval' ), true ) ? $r['flag'] : '';
	if ( ! $task || ! $flag || ! lk_can( 'projetos' ) ) {
		return new WP_Error( 'lk', 'Sem permissão.', array( 'status' => 403 ) );
	}
	$value = $task->$flag ? 0 : 1;
	$data  = array( $flag => $value );
	if ( 'needs_approval' === $flag && $value ) {
		$data['client_visible'] = 1;
	}
	lk_update( 'tasks', $task->id, $data );
	return array( 'ok' => true, 'value' => $value );
}

/**
 * Envia uma etapa para o cliente aprovar: fica em "Revisão" e aparece em "Precisamos de você".
 */
function lk_api_task_review( WP_REST_Request $r ) {
	$task = lk_get( 'tasks', absint( $r['id'] ) );
	if ( ! $task || ! $task->project_id || ! lk_can( 'projetos' ) ) {
		return new WP_Error( 'lk', 'Sem permissão.', array( 'status' => 403 ) );
	}
	lk_update( 'tasks', $task->id, array( 'status' => 'review', 'needs_approval' => 1, 'client_visible' => 1, 'approved_at' => null, 'done_at' => null ) );
	lk_log( $task->project_id, 'Pronto para a sua aprovação: ' . ( $task->grp ? $task->grp . ' › ' : '' ) . $task->title, true, $task->id );
	do_action( 'lk_task_review', $task->id );
	return array( 'ok' => true );
}

/**
 * Busca global (Ctrl+K): clientes, projetos e tarefas.
 */
function lk_api_search( WP_REST_Request $r ) {
	global $wpdb;
	$q    = trim( sanitize_text_field( $r['q'] ) );
	$like = '%' . $wpdb->esc_like( $q ) . '%';
	$out  = array();
	if ( mb_strlen( $q ) < 2 ) {
		return $out;
	}
	if ( lk_can( 'clientes' ) ) {
		foreach ( $wpdb->get_results( $wpdb->prepare( 'SELECT id, name, company, email FROM ' . lk_table( 'clients' ) . ' WHERE name LIKE %s OR company LIKE %s OR email LIKE %s OR cnpj LIKE %s LIMIT 6', $like, $like, $like, $like ) ) as $c ) { // phpcs:ignore WordPress.DB.PreparedSQL
			$out[] = array( 'type' => 'Cliente', 'title' => lk_client_label( $c ), 'sub' => $c->email, 'url' => lk_panel_url( 'cliente', $c->id ) );
		}
	}
	if ( lk_can( 'projetos' ) ) {
		foreach ( lk_projects( 'p.title LIKE %s OR c.company LIKE %s OR c.name LIKE %s', array( $like, $like, $like ) ) as $i => $p ) {
			if ( $i >= 6 ) {
				break;
			}
			$out[] = array( 'type' => 'Pedido', 'title' => '#' . $p->id . ' ' . $p->title, 'sub' => lk_project_client_label( $p ), 'url' => lk_panel_url( 'pedido', $p->id ) );
		}
	}
	if ( lk_can( 'orcamentos' ) ) {
		foreach ( lk_rows( 'quotes', 'title LIKE %s', array( $like ), 'id DESC LIMIT 5' ) as $q ) {
			$out[] = array( 'type' => 'Orçamento', 'title' => $q->title, 'sub' => lk_money( $q->total ), 'url' => lk_panel_url( 'orcamento', $q->id ) );
		}
	}
	if ( lk_module( 'produtos' ) && lk_can( 'produtos' ) ) {
		foreach ( lk_rows( 'products', 'name LIKE %s OR sku LIKE %s', array( $like, $like ), 'id DESC LIMIT 5' ) as $pr ) {
			$out[] = array( 'type' => 'Produto', 'title' => $pr->name, 'sub' => lk_money( $pr->price ), 'url' => lk_panel_url( 'produto', $pr->id ) );
		}
	}
	if ( lk_module( 'estoque' ) && lk_can( 'estoque' ) ) {
		foreach ( lk_rows( 'filaments', 'brand LIKE %s OR color_name LIKE %s OR material LIKE %s', array( $like, $like, $like ), 'id DESC LIMIT 5' ) as $f ) {
			$out[] = array( 'type' => 'Filamento', 'title' => lk_filament_label( $f ), 'sub' => round( $f->weight_g ) . ' g', 'url' => lk_panel_url( 'filamentos' ) );
		}
	}
	$where = 't.title LIKE %s';
	$args  = array( $like );
	if ( ! lk_can( 'tarefas' ) && ! lk_can( 'projetos' ) ) {
		$where .= ' AND t.assignee = %d';
		$args[] = get_current_user_id();
	}
	foreach ( lk_tasks( $where, $args, "t.status = 'done', t.id DESC" ) as $i => $t ) {
		if ( $i >= 8 ) {
			break;
		}
		$out[] = array( 'type' => 'Tarefa', 'title' => $t->title, 'sub' => $t->project_title ? $t->project_title : 'Tarefa do estúdio', 'url' => lk_panel_url( 'tarefa', $t->id ) );
	}
	return $out;
}

/**
 * Mostra a senha de um acesso (a equipe com permissão, ou o cliente dono do projeto).
 */
function lk_api_access_secret( WP_REST_Request $r ) {
	$row = lk_get( 'access', absint( $r['id'] ) );
	if ( ! $row ) {
		return new WP_Error( 'lk', 'Não encontrado.', array( 'status' => 404 ) );
	}
	$allowed = lk_is_team() ? lk_can( 'acessos' ) : (bool) lk_owns_project( $row->project_id );
	if ( ! $allowed ) {
		return new WP_Error( 'lk', 'Sem permissão.', array( 'status' => 403 ) );
	}
	return array( 'secret' => lk_decrypt( $row->secret ) );
}

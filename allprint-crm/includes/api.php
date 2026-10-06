<?php
/**
 * Endpoints usados pelo JavaScript do painel (salvam na hora, sem recarregar).
 * Namespace: /wp-json/ap/v1
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_action( 'rest_api_init', 'ap_rest_routes' );
function ap_rest_routes() {
	$team = function () {
		return ap_is_team();
	};

	register_rest_route(
		'ap/v1',
		'/move',
		array(
			'methods'             => 'POST',
			'permission_callback' => $team,
			'callback'            => 'ap_api_move',
		)
	);
	register_rest_route(
		'ap/v1',
		'/columns',
		array(
			'methods'             => 'POST',
			'permission_callback' => $team,
			'callback'            => 'ap_api_columns',
		)
	);
	register_rest_route(
		'ap/v1',
		'/task/(?P<id>\d+)/toggle',
		array(
			'methods'             => 'POST',
			'permission_callback' => $team,
			'callback'            => 'ap_api_task_toggle',
		)
	);
	register_rest_route(
		'ap/v1',
		'/task/(?P<id>\d+)/flag',
		array(
			'methods'             => 'POST',
			'permission_callback' => $team,
			'callback'            => 'ap_api_task_flag',
		)
	);
	register_rest_route(
		'ap/v1',
		'/task/(?P<id>\d+)/review',
		array(
			'methods'             => 'POST',
			'permission_callback' => $team,
			'callback'            => 'ap_api_task_review',
		)
	);
	register_rest_route(
		'ap/v1',
		'/search',
		array(
			'methods'             => 'GET',
			'permission_callback' => $team,
			'callback'            => 'ap_api_search',
		)
	);
	register_rest_route(
		'ap/v1',
		'/access/(?P<id>\d+)',
		array(
			'methods'             => 'GET',
			'permission_callback' => 'is_user_logged_in',
			'callback'            => 'ap_api_access_secret',
		)
	);
}

/**
 * Kanban: move um card de coluna e salva a nova ordem.
 * { type: "project"|"task", id, status, order: [ids na coluna] }
 */
function ap_api_move( WP_REST_Request $r ) {
	$type   = in_array( $r['type'], array( 'project', 'lead' ), true ) ? $r['type'] : 'task';
	$id     = absint( $r['id'] );
	$status = sanitize_key( $r['status'] );
	$order  = array_map( 'absint', (array) $r['order'] );

	if ( 'lead' === $type ) {
		$funnel = ap_funnel();
		$lead   = ap_get( 'leads', $id );
		if ( ! ap_can( 'leads' ) || ! $lead || ! isset( $funnel[ $status ] ) ) {
			return new WP_Error( 'ap', 'Sem permissão.', array( 'status' => 403 ) );
		}
		ap_update( 'leads', $id, array( 'stage' => $status ) );
		foreach ( $order as $pos => $lid ) {
			ap_update( 'leads', $lid, array( 'position' => $pos ) );
		}
		if ( $lead->stage !== $status ) {
			ap_lead_log( $id, 'Movido para "' . $funnel[ $status ] . '".' );
		}
		return array( 'ok' => true );
	}

	if ( 'project' === $type ) {
		$internal = ap_board_internal();
		if ( ! ap_can( 'projetos' ) || ( ! isset( ap_columns()[ $status ] ) && ! isset( $internal[ $status ] ) ) ) {
			return new WP_Error( 'ap', 'Sem permissão.', array( 'status' => 403 ) );
		}
		$old = ap_get( 'projects', $id );
		if ( ! $old ) {
			return new WP_Error( 'ap', 'Projeto não encontrado.', array( 'status' => 404 ) );
		}
		// Coluna interna (ex.: "Hoje"): só organiza o quadro, a etapa real não muda.
		if ( isset( $internal[ $status ] ) ) {
			ap_update( 'projects', $id, array( 'board_col' => $status ) );
			foreach ( $order as $pos => $pid ) {
				ap_update( 'projects', $pid, array( 'position' => $pos ) );
			}
			return array( 'ok' => true );
		}
		ap_update( 'projects', $id, array( 'status' => $status, 'board_col' => '' ) );
		foreach ( $order as $pos => $pid ) {
			ap_update( 'projects', $pid, array( 'position' => $pos ) );
		}
		if ( $old->status !== $status ) {
			ap_project_moved( $id, $status );
		}
		return array( 'ok' => true );
	}

	$task = ap_get( 'tasks', $id );
	if ( ! $task || ! array_key_exists( $status, ap_task_statuses() ) ) {
		return new WP_Error( 'ap', 'Tarefa não encontrada.', array( 'status' => 404 ) );
	}
	if ( ! ap_can( 'tarefas' ) && ! ap_can( 'projetos' ) && (int) $task->assignee !== get_current_user_id() ) {
		return new WP_Error( 'ap', 'Sem permissão.', array( 'status' => 403 ) );
	}
	ap_update(
		'tasks',
		$id,
		array(
			'status'  => $status,
			'done_at' => 'done' === $status ? ( $task->done_at ? $task->done_at : ap_now() ) : null,
		)
	);
	foreach ( $order as $pos => $tid ) {
		ap_update( 'tasks', $tid, array( 'position' => $pos ) );
	}
	return array( 'ok' => true );
}

/**
 * Marca/desmarca uma etapa como feita (checklist do projeto).
 */
function ap_api_task_toggle( WP_REST_Request $r ) {
	$task = ap_get( 'tasks', absint( $r['id'] ) );
	if ( ! $task ) {
		return new WP_Error( 'ap', 'Tarefa não encontrada.', array( 'status' => 404 ) );
	}
	if ( ! ap_can( 'projetos' ) && ! ap_can( 'tarefas' ) && (int) $task->assignee !== get_current_user_id() ) {
		return new WP_Error( 'ap', 'Sem permissão.', array( 'status' => 403 ) );
	}
	$done = 'done' !== $task->status;
	ap_update(
		'tasks',
		$task->id,
		array(
			'status'  => $done ? 'done' : 'todo',
			'done_at' => $done ? ap_now() : null,
		)
	);
	if ( $done && $task->lead_id ) {
		ap_update( 'leads', $task->lead_id, array( 'last_contact' => ap_now(), 'next_at' => null, 'next_action' => '' ) );
		ap_lead_log( $task->lead_id, 'Follow-up feito: ' . $task->title );
	}
	if ( $done && $task->project_id && $task->client_visible ) {
		ap_log( $task->project_id, 'Concluído: ' . ( $task->grp ? $task->grp . ' › ' : '' ) . $task->title, true, $task->id );
	}
	$progress = $task->project_id ? ap_progress( $task->project_id ) : null;
	return array(
		'ok'       => true,
		'done'     => $done,
		'progress' => $progress,
	);
}

/**
 * Liga/desliga "o cliente vê" e "precisa de aprovação" direto na lista.
 */
function ap_api_task_flag( WP_REST_Request $r ) {
	$task = ap_get( 'tasks', absint( $r['id'] ) );
	$flag = in_array( $r['flag'], array( 'client_visible', 'needs_approval' ), true ) ? $r['flag'] : '';
	if ( ! $task || ! $flag || ! ap_can( 'projetos' ) ) {
		return new WP_Error( 'ap', 'Sem permissão.', array( 'status' => 403 ) );
	}
	$value = $task->$flag ? 0 : 1;
	$data  = array( $flag => $value );
	if ( 'needs_approval' === $flag && $value ) {
		$data['client_visible'] = 1;
	}
	ap_update( 'tasks', $task->id, $data );
	return array( 'ok' => true, 'value' => $value );
}

/**
 * Envia uma etapa para o cliente aprovar: fica em "Revisão" e aparece em "Precisamos de você".
 */
function ap_api_task_review( WP_REST_Request $r ) {
	$task = ap_get( 'tasks', absint( $r['id'] ) );
	if ( ! $task || ! $task->project_id || ! ap_can( 'projetos' ) ) {
		return new WP_Error( 'ap', 'Sem permissão.', array( 'status' => 403 ) );
	}
	ap_update( 'tasks', $task->id, array( 'status' => 'review', 'needs_approval' => 1, 'client_visible' => 1, 'approved_at' => null, 'done_at' => null ) );
	ap_log( $task->project_id, 'Pronto para a sua aprovação: ' . ( $task->grp ? $task->grp . ' › ' : '' ) . $task->title, true, $task->id );
	do_action( 'ap_task_review', $task->id );
	return array( 'ok' => true );
}

/**
 * Busca global (Ctrl+K): clientes, projetos e tarefas.
 */
function ap_api_search( WP_REST_Request $r ) {
	global $wpdb;
	$q    = trim( sanitize_text_field( $r['q'] ) );
	$like = '%' . $wpdb->esc_like( $q ) . '%';
	$out  = array();
	if ( mb_strlen( $q ) < 2 ) {
		return $out;
	}
	if ( ap_can( 'clientes' ) ) {
		foreach ( $wpdb->get_results( $wpdb->prepare( 'SELECT id, name, company, email FROM ' . ap_table( 'clients' ) . ' WHERE name LIKE %s OR company LIKE %s OR email LIKE %s OR cnpj LIKE %s LIMIT 6', $like, $like, $like, $like ) ) as $c ) { // phpcs:ignore WordPress.DB.PreparedSQL
			$out[] = array( 'type' => 'Cliente', 'title' => ap_client_label( $c ), 'sub' => $c->email, 'url' => ap_panel_url( 'cliente', $c->id ) );
		}
	}
	if ( ap_can( 'projetos' ) ) {
		foreach ( ap_projects( 'p.title LIKE %s OR c.company LIKE %s OR c.name LIKE %s', array( $like, $like, $like ) ) as $i => $p ) {
			if ( $i >= 6 ) {
				break;
			}
			$out[] = array( 'type' => 'Pedido', 'title' => '#' . $p->id . ' ' . $p->title, 'sub' => ap_project_client_label( $p ), 'url' => ap_panel_url( 'pedido', $p->id ) );
		}
	}
	if ( ap_can( 'orcamentos' ) ) {
		foreach ( ap_rows( 'quotes', 'title LIKE %s', array( $like ), 'id DESC LIMIT 5' ) as $q ) {
			$out[] = array( 'type' => 'Orçamento', 'title' => $q->title, 'sub' => ap_money( $q->total ), 'url' => ap_panel_url( 'orcamento', $q->id ) );
		}
	}
	if ( ap_module( 'produtos' ) && ap_can( 'produtos' ) ) {
		foreach ( ap_rows( 'products', 'name LIKE %s OR sku LIKE %s', array( $like, $like ), 'id DESC LIMIT 5' ) as $pr ) {
			$out[] = array( 'type' => 'Produto', 'title' => $pr->name, 'sub' => ap_money( $pr->price ), 'url' => ap_panel_url( 'produto', $pr->id ) );
		}
	}
	if ( ap_module( 'estoque' ) && ap_can( 'estoque' ) ) {
		foreach ( ap_rows( 'filaments', 'brand LIKE %s OR color_name LIKE %s OR material LIKE %s', array( $like, $like, $like ), 'id DESC LIMIT 5' ) as $f ) {
			$out[] = array( 'type' => 'Filamento', 'title' => ap_filament_label( $f ), 'sub' => round( $f->weight_g ) . ' g', 'url' => ap_panel_url( 'filamentos' ) );
		}
	}
	$where = 't.title LIKE %s';
	$args  = array( $like );
	if ( ! ap_can( 'tarefas' ) && ! ap_can( 'projetos' ) ) {
		$where .= ' AND t.assignee = %d';
		$args[] = get_current_user_id();
	}
	foreach ( ap_tasks( $where, $args, "t.status = 'done', t.id DESC" ) as $i => $t ) {
		if ( $i >= 8 ) {
			break;
		}
		$out[] = array( 'type' => 'Tarefa', 'title' => $t->title, 'sub' => $t->project_title ? $t->project_title : 'Tarefa do estúdio', 'url' => ap_panel_url( 'tarefa', $t->id ) );
	}
	return $out;
}

/**
 * Mostra a senha de um acesso (a equipe com permissão, ou o cliente dono do projeto).
 */
function ap_api_access_secret( WP_REST_Request $r ) {
	$row = ap_get( 'access', absint( $r['id'] ) );
	if ( ! $row ) {
		return new WP_Error( 'ap', 'Não encontrado.', array( 'status' => 404 ) );
	}
	$allowed = ap_is_team() ? ap_can( 'acessos' ) : (bool) ap_owns_project( $row->project_id );
	if ( ! $allowed ) {
		return new WP_Error( 'ap', 'Sem permissão.', array( 'status' => 403 ) );
	}
	return array( 'secret' => ap_decrypt( $row->secret ) );
}

<?php
/**
 * Tarefas unificadas: tarefas + posts que estão com você + alterações do cliente, em colunas por prazo
 * (Atrasadas · Hoje · Amanhã · Esta semana · Pendentes). Usada na tela Tarefas, no dashboard e no Modo foco.
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function lk_task_cols() {
	return array(
		'atrasadas' => 'Atrasadas',
		'hoje'      => 'Hoje',
		'amanha'    => 'Amanhã',
		'semana'    => 'Esta semana',
		'pendentes' => 'Pendentes',
	);
}

/** Em qual coluna cai uma data (Y-m-d ou vazio). */
function lk_task_col_for( $date ) {
	if ( ! $date ) {
		return 'pendentes';
	}
	$d = lk_days_until( substr( $date, 0, 10 ) );
	if ( $d < 0 ) {
		return 'atrasadas';
	}
	if ( 0 === $d ) {
		return 'hoje';
	}
	if ( 1 === $d ) {
		return 'amanha';
	}
	$dow  = (int) gmdate( 'N', strtotime( lk_today() . ' 12:00:00' ) );
	$left = 7 - $dow; // dias até domingo
	return $d <= $left ? 'semana' : 'pendentes';
}

/** Cliente de uma tarefa: coluna própria, projeto ou marcador da descrição ([plan:ID], [alt:ID:x]…). */
function lk_task_client_id( $t ) {
	if ( ! empty( $t->client_id ) ) {
		return (int) $t->client_id;
	}
	if ( ! empty( $t->project_client ) ) {
		return (int) $t->project_client;
	}
	$d = (string) $t->description;
	if ( preg_match( '/\[plan:(\d+)\]/', $d, $m ) ) {
		$pl = lk_get( 'plans', (int) $m[1] );
		return $pl ? (int) $pl->client_id : 0;
	}
	if ( preg_match( '/\[(?:alt|reenviar|legenda|ajuste):(\d+)/', $d, $m ) ) {
		$p = lk_get( 'posts', (int) $m[1] );
		return $p ? (int) $p->client_id : 0;
	}
	return 0;
}

function lk_task_post_ids_in_tasks() {
	global $wpdb;
	$out  = array();
	$rows = $wpdb->get_col( 'SELECT description FROM ' . lk_table( 'tasks' ) . " WHERE status <> 'done' AND grp = 'Fluxo'" ); // phpcs:ignore WordPress.DB.PreparedSQL
	foreach ( $rows as $d ) {
		if ( preg_match_all( '/\[(?:alt|reenviar|legenda|ajuste):(\d+)/', (string) $d, $m ) ) {
			foreach ( $m[1] as $id ) {
				$out[ (int) $id ] = true;
			}
		}
	}
	return $out;
}

/**
 * Itens do quadro. $opts: user_id (0 = todos), client_id, kind (''|task|post|alter), done (bool).
 * Cada item: array( kind, id, title, sub, client_id, client, due, prio, assignee, url, col, label ).
 */
function lk_task_board( $opts = array() ) {
	global $wpdb;
	$o     = wp_parse_args( $opts, array( 'user_id' => 0, 'client_id' => 0, 'kind' => '', 'done' => false ) );
	$items = array();
	$cl    = array();
	$cname = function ( $id ) use ( &$cl ) {
		if ( ! $id ) {
			return '';
		}
		if ( ! isset( $cl[ $id ] ) ) {
			$c        = lk_get( 'clients', $id );
			$cl[ $id ] = $c ? lk_client_label( $c ) : '';
		}
		return $cl[ $id ];
	};

	// 1) Tarefas.
	if ( '' === $o['kind'] || 'task' === $o['kind'] ) {
		$where = "( p.archived IS NULL OR p.archived = 0 ) AND t.status <> 'done'";
		$args  = array();
		if ( $o['user_id'] ) {
			$where .= ' AND t.assignee = %d';
			$args[] = (int) $o['user_id'];
		}
		if ( $o['done'] ) {
			$where = "( p.archived IS NULL OR p.archived = 0 ) AND t.status = 'done' AND t.done_at >= %s" . ( $o['user_id'] ? ' AND t.assignee = %d' : '' );
			$args  = $o['user_id'] ? array( gmdate( 'Y-m-d', strtotime( lk_today() . ' -7 day' ) ), (int) $o['user_id'] ) : array( gmdate( 'Y-m-d', strtotime( lk_today() . ' -7 day' ) ) );
		}
		$sql = 'SELECT t.*, p.title AS project_title, p.client_id AS project_client FROM ' . lk_table( 'tasks' ) . ' t LEFT JOIN ' . lk_table( 'projects' ) . ' p ON p.id = t.project_id WHERE ' . $where . ' ORDER BY t.due_date IS NULL, t.due_date, t.id';
		foreach ( $wpdb->get_results( $args ? $wpdb->prepare( $sql, $args ) : $sql ) as $t ) { // phpcs:ignore WordPress.DB.PreparedSQL
			// Etapas geradas por serviços ficam dentro do projeto; entram aqui quando ganham prazo ou responsável.
			if ( $t->project_id && ! $t->due_date && ! $t->assignee && 'doing' !== $t->status && 'review' !== $t->status ) {
				continue;
			}
			$cid = lk_task_client_id( $t );
			$alt = (bool) preg_match( '/\[alt:/', (string) $t->description );
			$items[] = array(
				'kind'     => $alt ? 'alter' : 'task',
				'id'       => (int) $t->id,
				'title'    => $t->title,
				'sub'      => $t->project_title ? $t->project_title : $t->grp,
				'client_id' => $cid,
				'client'   => $cname( $cid ),
				'due'      => $t->due_date ? $t->due_date : '',
				'prio'     => $t->priority,
				'assignee' => (int) $t->assignee,
				'url'      => lk_panel_url( 'tarefa', $t->id ),
				'done'     => 'done' === $t->status,
				'label'    => $alt ? 'Alteração' : '',
			);
		}
	}

	// 2) Posts que estão com a pessoa (arte, legenda, revisão, aprovação do cliente, envio).
	if ( ! $o['done'] && ( '' === $o['kind'] || in_array( $o['kind'], array( 'post', 'alter' ), true ) ) ) {
		$skip = lk_task_post_ids_in_tasks();
		$off  = array( lk_stage_for( 'planejamento' ), lk_stage_for( 'agendado' ), lk_stage_for( 'publicado' ) );
		foreach ( lk_posts( 'p.stage NOT IN (%s, %s, %s)', $off, 'p.scheduled_at IS NULL, p.scheduled_at, p.id' ) as $p ) {
			if ( isset( $skip[ (int) $p->id ] ) ) {
				continue;
			}
			$owner = lk_post_owner( $p );
			if ( $o['user_id'] && (int) $owner !== (int) $o['user_id'] ) {
				continue;
			}
			$alt = in_array( $p->change_target, array( 'arte', 'legenda', 'ambos' ), true );
			if ( 'alter' === $o['kind'] && ! $alt ) {
				continue;
			}
			if ( 'post' === $o['kind'] && $alt ) {
				continue;
			}
			$dl   = function_exists( 'lk_post_deadline' ) ? lk_post_deadline( $p ) : '';
			$due  = $dl ? substr( $dl, 0, 10 ) : ( $alt ? lk_today() : ( $p->scheduled_at ? substr( $p->scheduled_at, 0, 10 ) : '' ) );
			$role = function_exists( 'lk_post_role' ) ? lk_post_role( $p ) : '';
			$what = array( 'design' => 'Fazer a arte', 'revisao' => 'Revisar', 'aprovacao' => 'Cliente aprovando' )[ $role ] ?? ( lk_stages()[ $p->stage ] ?? 'Post' );
			if ( $alt ) {
				$what = 'Alteração (' . array( 'arte' => 'arte', 'legenda' => 'legenda', 'ambos' => 'arte e legenda' )[ $p->change_target ] . ')';
			}
			$items[] = array(
				'kind'     => $alt ? 'alter' : 'post',
				'id'       => (int) $p->id,
				'title'    => $what . ': ' . $p->title,
				'sub'      => lk_formats()[ $p->format ] ?? '',
				'client_id' => (int) $p->client_id,
				'client'   => $cname( (int) $p->client_id ),
				'due'      => $due,
				'prio'     => $alt ? 'alta' : 'normal',
				'assignee' => (int) $owner,
				'url'      => lk_panel_url( 'post', $p->id ),
				'done'     => false,
				'label'    => $alt ? 'Alteração' : 'Post',
			);
		}
	}

	if ( $o['client_id'] ) {
		$items = array_values( array_filter( $items, function ( $i ) use ( $o ) { return (int) $i['client_id'] === (int) $o['client_id']; } ) );
	}
	$rank = array( 'urgente' => 0, 'alta' => 1, 'normal' => 2, 'baixa' => 3 );
	usort(
		$items,
		function ( $a, $b ) use ( $rank ) {
			$c = ( $a['due'] ? $a['due'] : '9999' ) <=> ( $b['due'] ? $b['due'] : '9999' );
			return $c ? $c : ( ( $rank[ $a['prio'] ] ?? 2 ) <=> ( $rank[ $b['prio'] ] ?? 2 ) );
		}
	);
	foreach ( $items as &$it ) {
		$it['col'] = $it['done'] ? 'feitas' : lk_task_col_for( $it['due'] );
	}
	unset( $it );
	return $items;
}

/** Contagem por coluna (para o dashboard e o foco). */
function lk_task_board_counts( $user_id = 0 ) {
	$out = array_fill_keys( array_keys( lk_task_cols() ), 0 );
	foreach ( lk_task_board( array( 'user_id' => $user_id ) ) as $i ) {
		$out[ $i['col'] ]++;
	}
	return $out;
}

/** Cartão de uma tarefa no quadro. */
function lk_task_card_html( $i ) {
	$h  = '<article class="kcard kcard--task tk-card prio-' . esc_attr( $i['prio'] ) . ' tk-' . esc_attr( $i['kind'] ) . '" data-kind="' . esc_attr( $i['kind'] ) . '" data-id="' . (int) $i['id'] . '"' . ( 'task' === $i['kind'] || ( 'alter' === $i['kind'] && ! empty( $i['url'] ) && false !== strpos( $i['url'], '/tarefa/' ) ) ? ' draggable="true" data-task="1"' : '' ) . ' data-href="' . esc_url( $i['url'] ) . '">';
	$h .= '<div class="tk-top">' . ( $i['client'] ? '<span class="tk-client">' . esc_html( $i['client'] ) . '</span>' : '' ) . ( $i['label'] ? '<em class="badge' . ( 'Alteração' === $i['label'] ? ' badge--warn' : '' ) . '">' . esc_html( $i['label'] ) . '</em>' : '' ) . '</div>';
	$h .= '<h4>' . esc_html( $i['title'] ) . '</h4>';
	$h .= '<div class="kcard-foot"><span class="kcard-badges">' . lk_priority_badge( $i['prio'] ) . lk_due_badge( $i['due'] ) . ( $i['sub'] ? '<small class="muted">' . esc_html( $i['sub'] ) . '</small>' : '' ) . '</span>' . lk_avatar( $i['assignee'] ) . '</div>';
	if ( 'task' === $i['kind'] || ( 'alter' === $i['kind'] && false !== strpos( $i['url'], '/tarefa/' ) ) ) {
		$h .= '<button type="button" class="tk-done" data-tk-done title="Concluir">✓</button>';
	}
	return $h . '</article>';
}

/* API: mover prazo (arrastar entre colunas) e concluir */
add_action(
	'rest_api_init',
	function () {
		register_rest_route(
			'lk/v1',
			'/task-board',
			array(
				'methods'             => 'POST',
				'permission_callback' => function () { return lk_is_team(); },
				'callback'            => 'lk_api_task_board',
			)
		);
	}
);

function lk_api_task_board( WP_REST_Request $r ) {
	$t = lk_get( 'tasks', absint( $r['id'] ) );
	if ( ! $t ) {
		return new WP_Error( 'lk', 'Tarefa não encontrada.', array( 'status' => 404 ) );
	}
	if ( ! lk_can( 'tarefas' ) && ! lk_can( 'projetos' ) && (int) $t->assignee !== get_current_user_id() ) {
		return new WP_Error( 'lk', 'Sem permissão.', array( 'status' => 403 ) );
	}
	$op = sanitize_key( $r['op'] );
	if ( 'done' === $op ) {
		lk_update( 'tasks', $t->id, array( 'status' => 'done', 'done_at' => lk_now() ) );
		return array( 'ok' => true );
	}
	$col  = sanitize_key( $r['col'] );
	$dow  = (int) gmdate( 'N', strtotime( lk_today() . ' 12:00:00' ) );
	$map  = array(
		'hoje'      => lk_today(),
		'amanha'    => gmdate( 'Y-m-d', strtotime( lk_today() . ' +1 day' ) ),
		'semana'    => gmdate( 'Y-m-d', strtotime( lk_today() . ' +' . max( 2, min( 7 - $dow, 5 ) ) . ' day' ) ),
		'pendentes' => null,
	);
	if ( ! array_key_exists( $col, $map ) ) {
		return new WP_Error( 'lk', 'Coluna inválida.', array( 'status' => 400 ) );
	}
	// "Esta semana" numa sexta-feira só tem sábado/domingo: sem espaço, vira daqui a 2 dias.
	lk_update( 'tasks', $t->id, array( 'due_date' => $map[ $col ] ) );
	return array( 'ok' => true, 'due' => $map[ $col ] );
}

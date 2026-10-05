<?php
/**
 * Metas da agência (só a dona/admin vê): clientes novos, total de clientes, receita do mês, recorrente (MRR),
 * leads novos e posts publicados. Cada meta tem período (mês atual, ano ou acumulado).
 *
 * Animação: quando uma meta é batida, a dona vê confete + troféu (uma vez por meta/período).
 * Subir de nível (gamificação): a pessoa vê a animação no próximo acesso ao painel.
 * Os dois usam assets/celebrar.js e a fila window.LK_CELEBRATE.
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function lk_goal_types() {
	return array(
		'clientes_novos'  => array( 'Clientes novos', 'clientes', 'flow', false ),
		'clientes_total'  => array( 'Total de clientes', 'clientes', 'snap', false ),
		'receita'         => array( 'Receita recebida', 'R$', 'flow', true ),
		'mrr'             => array( 'Recorrente mensal (MRR)', 'R$', 'snap', true ),
		'leads_novos'     => array( 'Leads novos', 'leads', 'flow', false ),
		'posts_publicados' => array( 'Posts publicados', 'posts', 'flow', false ),
	);
}

function lk_goals() {
	return array_values( (array) get_option( 'lk_goals', array() ) );
}

/** Período da meta: from, to, key (para não comemorar duas vezes), label. */
function lk_goal_period( $g ) {
	$today = lk_today();
	if ( 'ano' === ( $g['period'] ?? 'mes' ) ) {
		$y = substr( $today, 0, 4 );
		return array( $y . '-01-01', $y . '-12-31', $y, 'em ' . $y );
	}
	if ( 'sempre' === ( $g['period'] ?? '' ) ) {
		return array( '2000-01-01', '2999-12-31', 'sempre', 'acumulado' );
	}
	$ym = substr( $today, 0, 7 );
	return array( $ym . '-01', gmdate( 'Y-m-t', strtotime( $ym . '-01' ) ), $ym, 'em ' . lk_month_label( $ym ) );
}

function lk_goal_value( $g ) {
	global $wpdb;
	list( $from, $to ) = lk_goal_period( $g );
	$a = $from . ' 00:00:00';
	$b = $to . ' 23:59:59';
	switch ( $g['type'] ?? '' ) {
		case 'clientes_novos':
			return (float) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM ' . lk_table( 'clients' ) . ' WHERE created_at BETWEEN %s AND %s', $a, $b ) ); // phpcs:ignore WordPress.DB.PreparedSQL
		case 'clientes_total':
			return (float) $wpdb->get_var( 'SELECT COUNT(*) FROM ' . lk_table( 'clients' ) ); // phpcs:ignore WordPress.DB.PreparedSQL
		case 'receita':
			return (float) $wpdb->get_var( $wpdb->prepare( 'SELECT COALESCE(SUM(amount),0) FROM ' . lk_table( 'transactions' ) . " WHERE type = 'in' AND status = 'pago' AND paid_at BETWEEN %s AND %s", $from, $to ) ); // phpcs:ignore WordPress.DB.PreparedSQL
		case 'mrr':
			return function_exists( 'lk_billing_mrr' ) ? (float) lk_billing_mrr() : 0.0;
		case 'leads_novos':
			return (float) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM ' . lk_table( 'leads' ) . ' WHERE created_at BETWEEN %s AND %s', $a, $b ) ); // phpcs:ignore WordPress.DB.PreparedSQL
		case 'posts_publicados':
			return (float) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM ' . lk_table( 'posts' ) . ' WHERE stage = %s AND scheduled_at BETWEEN %s AND %s', lk_stage_for( 'publicado' ), $a, $b ) ); // phpcs:ignore WordPress.DB.PreparedSQL
	}
	return 0.0;
}

function lk_goal_fmt( $g, $n ) {
	$t = lk_goal_types()[ $g['type'] ?? '' ] ?? array( '', '', '', false );
	return $t[3] ? lk_money( $n ) : number_format_i18n( $n ) . ( $t[1] ? ' ' . $t[1] : '' );
}

function lk_goal_progress( $g ) {
	$val    = lk_goal_value( $g );
	$target = max( 0.01, (float) ( $g['target'] ?? 0 ) );
	list( , $to, $key, $label ) = lk_goal_period( $g );
	return array(
		'value'  => $val,
		'target' => $target,
		'pct'    => (int) min( 100, floor( 100 * $val / $target ) ),
		'raw'    => 100 * $val / $target,
		'left'   => max( 0, $target - $val ),
		'days'   => max( 0, (int) floor( ( strtotime( $to ) - strtotime( lk_today() ) ) / DAY_IN_SECONDS ) ),
		'key'    => $key,
		'label'  => $label,
	);
}

/* -----------------------------------------------------------------------
 * Cadastro (só admin)
 * -------------------------------------------------------------------- */

function lk_do_goal_save() {
	lk_require( 'admin' );
	$goals = lk_goals();
	$id    = sanitize_key( lk_in( 'id' ) );
	$type  = array_key_exists( lk_in( 'type' ), lk_goal_types() ) ? lk_in( 'type' ) : 'receita';
	$money = lk_goal_types()[ $type ][3];
	$row   = array(
		'id'     => $id ? $id : 'g' . wp_generate_password( 6, false ),
		'name'   => lk_in( 'name' ) ?: lk_goal_types()[ $type ][0],
		'type'   => $type,
		'target' => $money ? lk_in( 'target', 'money' ) : (float) lk_in( 'target', 'raw' ),
		'period' => in_array( lk_in( 'period' ), array( 'mes', 'ano', 'sempre' ), true ) ? lk_in( 'period' ) : 'mes',
	);
	if ( $row['target'] <= 0 ) {
		lk_back( 'Informe um valor de meta maior que zero.', 'erro' );
	}
	$done = false;
	foreach ( $goals as $i => $g ) {
		if ( $g['id'] === $row['id'] ) {
			$goals[ $i ] = $row;
			$done        = true;
		}
	}
	if ( ! $done ) {
		$goals[] = $row;
	}
	update_option( 'lk_goals', $goals, false );
	lk_back( 'Meta salva.' );
}

function lk_do_goal_delete() {
	lk_require( 'admin' );
	$id = sanitize_key( lk_in( 'id' ) );
	update_option( 'lk_goals', array_values( array_filter( lk_goals(), function ( $g ) use ( $id ) { return $g['id'] !== $id; } ) ), false );
	lk_back( 'Meta removida.' );
}

/* -----------------------------------------------------------------------
 * Comemorações
 * -------------------------------------------------------------------- */

/** Metas batidas ainda não avisadas: notifica a dona (sino) uma vez por meta/período. */
add_action( 'lk_daily', 'lk_goals_notify' );
function lk_goals_notify() {
	$hit = (array) get_option( 'lk_goals_hit', array() );
	foreach ( lk_goals() as $g ) {
		$p = lk_goal_progress( $g );
		$k = $g['id'] . '|' . $p['key'];
		if ( $p['pct'] >= 100 && empty( $hit[ $k ] ) ) {
			$hit[ $k ] = time();
			foreach ( get_users( array( 'role' => 'administrator' ) ) as $a ) {
				lk_notify( $a->ID, '🏆 Meta batida: ' . $g['name'] . ' (' . lk_goal_fmt( $g, $p['value'] ) . ')', lk_panel_url( 'metas' ) );
			}
		}
	}
	update_option( 'lk_goals_hit', $hit, false );
}

/** Fila de comemorações para a pessoa logada (e já marca como vista). */
function lk_celebrate_queue() {
	$uid   = get_current_user_id();
	$queue = array();
	if ( lk_is_admin() ) {
		$seen = (array) get_user_meta( $uid, 'lk_goals_seen', true );
		foreach ( lk_goals() as $g ) {
			$p = lk_goal_progress( $g );
			$k = $g['id'] . '|' . $p['key'];
			if ( $p['pct'] >= 100 && ! in_array( $k, $seen, true ) ) {
				$seen[]  = $k;
				$queue[] = array( 'type' => 'goal', 'title' => 'Meta batida! 🎉', 'sub' => $g['name'], 'detail' => lk_goal_fmt( $g, $p['value'] ) . ' de ' . lk_goal_fmt( $g, $p['target'] ) . ' · ' . $p['label'], 'emoji' => '🏆' );
			}
		}
		update_user_meta( $uid, 'lk_goals_seen', array_slice( $seen, -60 ) );
	}
	$lv = get_user_meta( $uid, 'lk_levelup', true );
	if ( is_array( $lv ) && ! empty( $lv['to'] ) ) {
		delete_user_meta( $uid, 'lk_levelup' );
		$queue[] = array( 'type' => 'level', 'title' => 'Você subiu de nível! ⭐', 'sub' => $lv['to'], 'detail' => (int) $lv['pts'] . ' pontos ganhos', 'emoji' => '🚀', 'from' => $lv['from'], 'to' => $lv['to'] );
	}
	return $queue;
}

function lk_celebrate_html() {
	$q = lk_celebrate_queue();
	return '<script>window.LK_CELEBRATE=' . wp_json_encode( $q ) . ';</script><script src="' . esc_url( LK_URL . 'assets/celebrar.js?ver=' . LK_VERSION ) . '"></script>';
}

/** Chamada pela gamificação quando alguém passa de nível. */
function lk_levelup_mark( $user_id, $from, $to, $pts ) {
	update_user_meta( $user_id, 'lk_levelup', array( 'from' => $from, 'to' => $to, 'pts' => (int) $pts, 'at' => time() ) );
	if ( get_current_user_id() !== (int) $user_id ) {
		lk_notify( $user_id, '🚀 Você subiu para o nível ' . $to . '!', lk_panel_url( 'ranking' ) );
	}
}

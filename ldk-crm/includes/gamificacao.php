<?php
/**
 * Gamificação da equipe: pontos por tarefa concluída e post publicado, níveis, conquistas,
 * ranking (semana / mês / geral) e loja de prêmios. Tudo configurável em Painel → Gamificação.
 *
 * Pontuação (idempotente: cada tarefa/post pontua uma vez por pessoa, mesmo se reabrir):
 *  - tarefa concluída = pontos base × multiplicador da prioridade (+ bônus se no prazo) → para o responsável;
 *  - post publicado = pontos para o designer e para o social media do post.
 * Níveis usam os pontos GANHOS (resgatar prêmio não faz cair de nível). Saldo = ganhos − resgates.
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/* -----------------------------------------------------------------------
 * Tabela (própria, instala sozinha)
 * -------------------------------------------------------------------- */

add_action(
	'init',
	function () {
		if ( get_option( 'lk_game_db' ) === '1' ) {
			return;
		}
		global $wpdb;
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta(
			'CREATE TABLE ' . lk_table( 'game_log' ) . " (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			user_id bigint(20) unsigned NOT NULL DEFAULT 0,
			points int(11) NOT NULL DEFAULT 0,
			kind varchar(20) NOT NULL DEFAULT '',
			ref varchar(60) NOT NULL DEFAULT '',
			note varchar(255) NOT NULL DEFAULT '',
			status varchar(20) NOT NULL DEFAULT '',
			created_at datetime NOT NULL,
			PRIMARY KEY  (id),
			KEY user_id (user_id),
			KEY ref (ref)
		) " . $wpdb->get_charset_collate() . ';'
		);
		update_option( 'lk_game_db', '1', false );
	},
	98
);

/* -----------------------------------------------------------------------
 * Configuração (tudo editável)
 * -------------------------------------------------------------------- */

function lk_game_defaults() {
	return array(
		'on'            => '1',
		'pts_task'      => '10',
		'mult_baixa'    => '0.5',
		'mult_normal'   => '1',
		'mult_alta'     => '1.5',
		'mult_urgente'  => '2',
		'bonus_prazo'   => '5',
		'pts_post_des'  => '20',
		'pts_post_soc'  => '15',
		'niveis'        => "Iniciante | 0\nBronze | 100\nPrata | 300\nOuro | 700\nDiamante | 1500",
		'premios'       => "Vale-lanche | 150 | R$ 30 para o lanche\nSair 1h mais cedo | 400 | combinado com o gestor\nMeio período de folga | 900 | escolha o dia\nBônus em dinheiro | 1500 | valor definido pela agência",
		'conquistas'    => "Mão na massa | tarefas | 10 | 🔨\nImparável | tarefas | 50 | 🚀\nPublicador | posts | 10 | 📣\nCentenário | pontos | 100 | 💯\nLenda | pontos | 1500 | 👑",
		'destaque'      => 'Destaque do mês',
	);
}

function lk_game_cfg( $key = null ) {
	$cfg = array_merge( lk_game_defaults(), (array) get_option( 'lk_game', array() ) );
	return null === $key ? $cfg : ( $cfg[ $key ] ?? '' );
}

function lk_game_on() {
	return '1' === (string) lk_game_cfg( 'on' );
}

/** Linhas "a | b | c" viram listas. */
function lk_game_lines( $key, $cols ) {
	$out = array();
	foreach ( lk_list( lk_game_cfg( $key ) ) as $line ) {
		$p = array_map( 'trim', explode( '|', $line ) );
		if ( $p[0] !== '' ) {
			$out[] = array_pad( $p, $cols, '' );
		}
	}
	return $out;
}

function lk_game_levels() {
	$out = array();
	foreach ( lk_game_lines( 'niveis', 2 ) as $l ) {
		$out[] = array( 'name' => $l[0], 'min' => (int) $l[1] );
	}
	usort( $out, function ( $a, $b ) { return $a['min'] <=> $b['min']; } );
	return $out ? $out : array( array( 'name' => 'Iniciante', 'min' => 0 ) );
}

function lk_game_rewards() {
	$out = array();
	foreach ( lk_game_lines( 'premios', 3 ) as $i => $l ) {
		$out[ $i ] = array( 'name' => $l[0], 'cost' => max( 1, (int) $l[1] ), 'desc' => $l[2] );
	}
	return $out;
}

/* -----------------------------------------------------------------------
 * Pontos
 * -------------------------------------------------------------------- */

function lk_game_award( $user_id, $points, $kind, $ref, $note ) {
	$user_id = (int) $user_id;
	$points  = (int) $points;
	if ( ! $user_id || $points <= 0 || ! lk_game_on() || ! lk_is_team( $user_id ) ) {
		return false;
	}
	if ( $ref && lk_rows( 'game_log', 'user_id = %d AND ref = %s', array( $user_id, $ref ) ) ) {
		return false; // já pontuou por isso
	}
	$before = lk_game_level( lk_game_earned( $user_id ) );
	lk_insert( 'game_log', array( 'user_id' => $user_id, 'points' => $points, 'kind' => $kind, 'ref' => $ref, 'note' => mb_substr( $note, 0, 250 ) ) );
	$after = lk_game_level( lk_game_earned( $user_id ) );
	if ( $after['name'] !== $before['name'] && function_exists( 'lk_levelup_mark' ) ) {
		lk_levelup_mark( $user_id, $before['name'], $after['name'], lk_game_earned( $user_id ) ); // a pessoa vê a animação no próximo acesso
	}
	if ( get_current_user_id() === $user_id ) {
		// Aviso próprio (não some quando a tela mostra "Tarefa salva.").
		$q   = (array) get_transient( 'lk_gtoast_' . $user_id );
		$q[] = '+' . $points . ' pontos! ' . ( $after['name'] !== $before['name'] ? '🎉 Você subiu para ' . $after['name'] . '!' : '' );
		set_transient( 'lk_gtoast_' . $user_id, $q, 300 );
	} else {
		lk_notify( $user_id, '+' . $points . ' pontos: ' . $note, lk_panel_url( 'ranking' ) );
	}
	return true;
}

/** Ganhos (nunca diminuem com resgate). $since = 'Y-m-d H:i:s' ou ''. */
function lk_game_earned( $user_id, $since = '' ) {
	global $wpdb;
	$sql  = 'SELECT COALESCE(SUM(points),0) FROM ' . lk_table( 'game_log' ) . ' WHERE user_id = %d AND points > 0';
	$args = array( $user_id );
	if ( $since ) {
		$sql   .= ' AND created_at >= %s';
		$args[] = $since;
	}
	return (int) $wpdb->get_var( $wpdb->prepare( $sql, $args ) ); // phpcs:ignore WordPress.DB.PreparedSQL
}

/** Saldo para gastar: ganhos − resgates não recusados (ajustes negativos também contam). */
function lk_game_balance( $user_id ) {
	global $wpdb;
	return (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COALESCE(SUM(points),0) FROM ' . lk_table( 'game_log' ) . " WHERE user_id = %d AND NOT (kind = 'redeem' AND status = 'refused')", $user_id ) ); // phpcs:ignore WordPress.DB.PreparedSQL
}

function lk_game_level( $earned ) {
	$cur = null;
	foreach ( lk_game_levels() as $l ) {
		if ( $earned >= $l['min'] ) {
			$cur = $l;
		}
	}
	return $cur;
}

function lk_game_next_level( $earned ) {
	foreach ( lk_game_levels() as $l ) {
		if ( $earned < $l['min'] ) {
			return $l;
		}
	}
	return null;
}

function lk_game_count( $user_id, $kind ) {
	global $wpdb;
	return (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM ' . lk_table( 'game_log' ) . ' WHERE user_id = %d AND kind = %s', $user_id, $kind ) ); // phpcs:ignore WordPress.DB.PreparedSQL
}

function lk_game_badges( $user_id ) {
	$earned = lk_game_earned( $user_id );
	$have   = array( 'tarefas' => lk_game_count( $user_id, 'task' ), 'posts' => lk_game_count( $user_id, 'post' ), 'pontos' => $earned );
	$out    = array();
	foreach ( lk_game_lines( 'conquistas', 4 ) as $c ) {
		$type = sanitize_key( $c[1] );
		$goal = max( 1, (int) $c[2] );
		$out[] = array( 'name' => $c[0], 'emoji' => $c[3] ?: '🏅', 'goal' => $goal, 'type' => $type, 'now' => $have[ $type ] ?? 0, 'done' => ( $have[ $type ] ?? 0 ) >= $goal );
	}
	return $out;
}

function lk_game_period_start( $period ) {
	if ( 'semana' === $period ) {
		return gmdate( 'Y-m-d 00:00:00', strtotime( lk_today() . ' -' . ( (int) gmdate( 'N', strtotime( lk_today() ) ) - 1 ) . ' day' ) );
	}
	if ( 'mes' === $period ) {
		return gmdate( 'Y-m-01 00:00:00', strtotime( lk_today() ) );
	}
	return '';
}

function lk_game_ranking( $period = 'mes' ) {
	$since = lk_game_period_start( $period );
	$rows  = array();
	foreach ( lk_team_users() as $u ) {
		$rows[] = array( 'user' => $u, 'points' => lk_game_earned( $u->ID, $since ), 'total' => lk_game_earned( $u->ID ) );
	}
	usort( $rows, function ( $a, $b ) { return $b['points'] <=> $a['points'] ?: strcmp( $a['user']->display_name, $b['user']->display_name ); } );
	return $rows;
}

/** Quem mais pontuou no mês passado (se alguém pontuou). */
function lk_game_last_month_top() {
	global $wpdb;
	$from = gmdate( 'Y-m-01 00:00:00', strtotime( 'first day of last month', strtotime( lk_today() ) ) );
	$to   = gmdate( 'Y-m-01 00:00:00', strtotime( lk_today() ) );
	$row  = $wpdb->get_row( $wpdb->prepare( 'SELECT user_id, SUM(points) AS pts FROM ' . lk_table( 'game_log' ) . ' WHERE points > 0 AND created_at >= %s AND created_at < %s GROUP BY user_id ORDER BY pts DESC LIMIT 1', $from, $to ) ); // phpcs:ignore WordPress.DB.PreparedSQL
	return $row && $row->pts > 0 ? array( 'user' => get_userdata( $row->user_id ), 'pts' => (int) $row->pts, 'month' => wp_date( 'F/Y', strtotime( $from ) ) ) : null;
}

/* -----------------------------------------------------------------------
 * Gatilhos: tarefa concluída / post publicado
 * -------------------------------------------------------------------- */

add_action( 'lk_updated', 'lk_game_on_update', 10, 3 );
function lk_game_on_update( $table, $id, $data ) {
	if ( ! lk_game_on() ) {
		return;
	}
	if ( 'tasks' === $table && 'done' === ( $data['status'] ?? '' ) ) {
		$t = lk_get( 'tasks', $id );
		if ( ! $t ) {
			return;
		}
		$who = (int) $t->assignee ? (int) $t->assignee : get_current_user_id();
		$pts = (float) lk_game_cfg( 'pts_task' ) * (float) lk_game_cfg( 'mult_' . ( $t->priority ?: 'normal' ) );
		$ont = $t->due_date && substr( (string) ( $t->done_at ?: lk_now() ), 0, 10 ) <= $t->due_date;
		if ( $ont ) {
			$pts += (float) lk_game_cfg( 'bonus_prazo' );
		}
		lk_game_award( $who, (int) round( $pts ), 'task', 'task:' . $id, 'Tarefa: ' . $t->title . ( $ont ? ' (no prazo)' : '' ) );
	}
	if ( 'posts' === $table && function_exists( 'lk_stage_for' ) && ( $data['stage'] ?? '' ) === lk_stage_for( 'publicado' ) ) {
		$p = lk_get( 'posts', $id );
		if ( $p ) {
			lk_game_award( $p->designer_id, (int) lk_game_cfg( 'pts_post_des' ), 'post', 'post:' . $id . ':des', 'Post publicado (arte): ' . $p->title );
			if ( (int) $p->social_id !== (int) $p->designer_id ) {
				lk_game_award( $p->social_id, (int) lk_game_cfg( 'pts_post_soc' ), 'post', 'post:' . $id . ':soc', 'Post publicado (social): ' . $p->title );
			}
		}
	}
}

/* -----------------------------------------------------------------------
 * Ações
 * -------------------------------------------------------------------- */

function lk_do_game_save() {
	lk_require( 'admin' );
	$cfg = array();
	foreach ( lk_game_defaults() as $k => $d ) {
		$cfg[ $k ] = in_array( $k, array( 'niveis', 'premios', 'conquistas' ), true ) ? lk_in( $k, 'textarea' ) : ( 'on' === $k ? ( lk_in( 'on', 'bool' ) ? '1' : '0' ) : sanitize_text_field( (string) lk_in( $k ) ) );
		if ( '' === $cfg[ $k ] && 'on' !== $k ) {
			$cfg[ $k ] = $d;
		}
	}
	update_option( 'lk_game', $cfg );
	lk_back( 'Gamificação salva.' );
}

function lk_do_game_redeem() {
	lk_require( 'tarefas' );
	$uid     = get_current_user_id();
	$rewards = lk_game_rewards();
	$r       = $rewards[ lk_in( 'reward', 'int' ) ] ?? null;
	if ( ! lk_game_on() || ! $r ) {
		lk_back( 'Prêmio não encontrado.', 'erro' );
	}
	if ( lk_game_balance( $uid ) < $r['cost'] ) {
		lk_back( 'Pontos insuficientes para este prêmio.', 'erro' );
	}
	lk_insert( 'game_log', array( 'user_id' => $uid, 'points' => -$r['cost'], 'kind' => 'redeem', 'note' => $r['name'], 'status' => 'pending' ) );
	foreach ( get_users( array( 'role' => 'administrator' ) ) as $a ) {
		lk_notify( $a->ID, wp_get_current_user()->display_name . ' resgatou: ' . $r['name'], lk_panel_url( 'gamificacao' ) );
	}
	lk_back( 'Resgate pedido: ' . $r['name'] . '. A agência vai combinar a entrega.' );
}

function lk_do_game_redeem_set() {
	lk_require( 'admin' );
	$row = lk_get( 'game_log', lk_in( 'id', 'int' ) );
	$st  = 'refused' === lk_in( 'status' ) ? 'refused' : 'delivered';
	if ( $row && 'redeem' === $row->kind ) {
		lk_update( 'game_log', $row->id, array( 'status' => $st ) );
		lk_notify( $row->user_id, 'refused' === $st ? 'Resgate recusado (pontos devolvidos): ' . $row->note : 'Prêmio entregue: ' . $row->note, lk_panel_url( 'ranking' ) );
	}
	lk_back( 'Atualizado.' );
}

function lk_do_game_bonus() {
	lk_require( 'admin' );
	$uid = lk_in( 'user_id', 'int' );
	$pts = (int) lk_in( 'points', 'raw' ); // aceita negativo (tirar pontos)
	if ( ! $uid || ! $pts || ! lk_is_team( $uid ) ) {
		lk_back( 'Escolha a pessoa e os pontos.', 'erro' );
	}
	$note = lk_in( 'note' ) ?: 'Ajuste da agência';
	$lv0  = lk_game_level( lk_game_earned( $uid ) );
	lk_insert( 'game_log', array( 'user_id' => $uid, 'points' => $pts, 'kind' => 'bonus', 'note' => $note ) );
	$lv1  = lk_game_level( lk_game_earned( $uid ) );
	if ( $pts > 0 && $lv1['name'] !== $lv0['name'] && function_exists( 'lk_levelup_mark' ) ) {
		lk_levelup_mark( $uid, $lv0['name'], $lv1['name'], lk_game_earned( $uid ) );
	}
	lk_notify( $uid, ( $pts > 0 ? '+' : '' ) . $pts . ' pontos: ' . $note, lk_panel_url( 'ranking' ) );
	lk_back( 'Pontos lançados.' );
}

/* -----------------------------------------------------------------------
 * Cartão do perfil (Minha conta + topo do ranking)
 * -------------------------------------------------------------------- */

function lk_game_card_html( $uid ) {
	if ( ! lk_game_on() ) {
		return '';
	}
	$earned = lk_game_earned( $uid );
	$lv     = lk_game_level( $earned );
	$next   = lk_game_next_level( $earned );
	$pct    = $next ? min( 100, max( 0, round( ( $earned - $lv['min'] ) / max( 1, $next['min'] - $lv['min'] ) * 100 ) ) ) : 100;
	$h      = '<div class="game-card"><div class="game-top"><span class="game-lv">' . esc_html( $lv['name'] ) . '</span><span class="game-pts"><b>' . (int) $earned . '</b> pontos ganhos · <b>' . lk_game_balance( $uid ) . '</b> para gastar</span></div>';
	$h     .= '<div class="game-bar"><i style="width:' . (int) $pct . '%"></i></div>';
	$h     .= '<p class="muted small">' . ( $next ? 'Faltam <b>' . ( $next['min'] - $earned ) . '</b> pontos para <b>' . esc_html( $next['name'] ) . '</b>.' : 'Nível máximo! 👑' ) . '</p>';
	$h     .= '<div class="game-badges">';
	foreach ( lk_game_badges( $uid ) as $b ) {
		$h .= '<span class="game-badge' . ( $b['done'] ? ' is-on' : '' ) . '" title="' . esc_attr( $b['name'] . ' — ' . min( $b['now'], $b['goal'] ) . '/' . $b['goal'] . ' ' . $b['type'] ) . '">' . esc_html( $b['emoji'] ) . '<small>' . esc_html( $b['name'] ) . '</small></span>';
	}
	return $h . '</div></div>';
}

/** Avisos de pontos pendentes para quem acabou de pontuar. */
function lk_game_toast_html() {
	$key = 'lk_gtoast_' . get_current_user_id();
	$q   = get_transient( $key );
	if ( ! $q ) {
		return '';
	}
	delete_transient( $key );
	$h = '';
	foreach ( (array) $q as $m ) {
		$h .= '<div class="flash flash--ok" role="status">' . esc_html( $m ) . '</div>';
	}
	return $h;
}

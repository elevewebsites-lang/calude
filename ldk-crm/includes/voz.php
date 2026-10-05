<?php
/**
 * Sala de voz da equipe (estilo Discord) + "Chamar atenção" (estilo MSN).
 *
 * Sala de voz:
 *  - /painel/sala/ abre numa janelinha à parte (continua tocando enquanto a pessoa navega no painel).
 *  - Áudio direto entre os navegadores (WebRTC, cada um conectado com todos). O servidor só troca os
 *    "convites" de conexão (tabela lk_voice_signals) e diz quem está na sala (user meta lk_voice_ping).
 *  - Microfone começa DESLIGADO; quem precisa falar liga (ou segura a barra de espaço).
 *  - Redes muito fechadas podem precisar de um servidor TURN (Configurações → Sala de voz).
 *
 * Chamar atenção: treme a tela de todos que estão online no painel, com toque e um aviso.
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_action(
	'rest_api_init',
	function () {
		$team = function () {
			return lk_is_team();
		};
		register_rest_route( 'lk/v1', '/voice/beat', array( 'methods' => 'POST', 'callback' => 'lk_api_voice_beat', 'permission_callback' => $team ) );
		register_rest_route( 'lk/v1', '/voice/signal', array( 'methods' => 'POST', 'callback' => 'lk_api_voice_signal', 'permission_callback' => $team ) );
		register_rest_route( 'lk/v1', '/voice/leave', array( 'methods' => 'POST', 'callback' => 'lk_api_voice_leave', 'permission_callback' => $team ) );
		register_rest_route( 'lk/v1', '/team/nudge', array( 'methods' => 'POST', 'callback' => 'lk_api_nudge', 'permission_callback' => $team ) );
	}
);

/* -----------------------------------------------------------------------
 * Sala de voz
 * -------------------------------------------------------------------- */

define( 'LK_VOICE_TTL', 15 ); // Segundos sem sinal = saiu da sala.

/**
 * Quem está na sala agora.
 */
function lk_voice_members() {
	$out = array();
	$now = time();
	foreach ( lk_team_users() as $u ) {
		$ping = (int) get_user_meta( $u->ID, 'lk_voice_ping', true );
		if ( $ping && $now - $ping <= LK_VOICE_TTL ) {
			$out[] = array(
				'id'   => (int) $u->ID,
				'name' => $u->display_name,
				'ini'  => lk_user_badge( $u ),
				'mic'  => (int) get_user_meta( $u->ID, 'lk_voice_mic', true ),
				'since' => (int) get_user_meta( $u->ID, 'lk_voice_since', true ),
			);
		}
	}
	usort(
		$out,
		function ( $a, $b ) {
			return $a['since'] <=> $b['since'];
		}
	);
	return $out;
}

function lk_api_voice_beat( WP_REST_Request $r ) {
	global $wpdb;
	$me   = get_current_user_id();
	$prev = (int) get_user_meta( $me, 'lk_voice_ping', true );
	if ( ! $prev || time() - $prev > LK_VOICE_TTL ) {
		update_user_meta( $me, 'lk_voice_since', time() );
		// Entrou agora: descarta convites velhos endereçados a esta pessoa.
		$wpdb->delete( lk_table( 'voice_signals' ), array( 'to_user' => $me ) );
	}
	update_user_meta( $me, 'lk_voice_ping', time() );
	update_user_meta( $me, 'lk_voice_mic', $r['mic'] ? 1 : 0 );
	update_user_meta( $me, 'lk_ping', time() ); // Conta como online no painel também.

	$table = lk_table( 'voice_signals' );
	$rows  = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$table} WHERE to_user = %d ORDER BY id", $me ) ); // phpcs:ignore WordPress.DB.PreparedSQL
	if ( $rows ) {
		$ids = implode( ',', array_map( 'intval', wp_list_pluck( $rows, 'id' ) ) );
		$wpdb->query( "DELETE FROM {$table} WHERE id IN ({$ids})" ); // phpcs:ignore WordPress.DB.PreparedSQL
	}
	// Limpeza: convites com mais de 2 minutos.
	$wpdb->query( $wpdb->prepare( "DELETE FROM {$table} WHERE created_at < %s", gmdate( 'Y-m-d H:i:s', time() - 120 ) ) ); // phpcs:ignore WordPress.DB.PreparedSQL

	return array(
		'me'      => $me,
		'members' => lk_voice_members(),
		'signals' => array_map(
			function ( $s ) {
				return array( 'from' => (int) $s->from_user, 'type' => $s->type, 'data' => json_decode( $s->payload, true ) );
			},
			$rows
		),
	);
}

function lk_api_voice_signal( WP_REST_Request $r ) {
	global $wpdb;
	$to   = absint( $r['to'] );
	$type = sanitize_key( (string) $r['type'] );
	if ( ! $to || ! in_array( $type, array( 'offer', 'answer', 'bye' ), true ) || ! lk_is_team( $to ) ) {
		return new WP_Error( 'lk', 'Convite inválido.', array( 'status' => 400 ) );
	}
	$payload = wp_json_encode( $r['data'] );
	if ( strlen( $payload ) > 60000 ) {
		return new WP_Error( 'lk', 'Convite grande demais.', array( 'status' => 400 ) );
	}
	$wpdb->insert(
		lk_table( 'voice_signals' ),
		array(
			'to_user'    => $to,
			'from_user'  => get_current_user_id(),
			'type'       => $type,
			'payload'    => $payload,
			'created_at' => gmdate( 'Y-m-d H:i:s' ),
		)
	);
	return array( 'ok' => true );
}

function lk_api_voice_leave() {
	$me = get_current_user_id();
	delete_user_meta( $me, 'lk_voice_ping' );
	delete_user_meta( $me, 'lk_voice_mic' );
	return array( 'ok' => true );
}

/**
 * Servidores para achar o caminho entre os navegadores (STUN público + TURN opcional).
 */
function lk_voice_ice() {
	$ice = array( array( 'urls' => array( 'stun:stun.l.google.com:19302', 'stun:stun1.l.google.com:19302' ) ) );
	$turn = trim( (string) lk_setting( 'voz_turn_url' ) );
	if ( $turn ) {
		$ice[] = array(
			'urls'       => array_values( array_filter( array_map( 'trim', explode( ',', $turn ) ) ) ),
			'username'   => (string) lk_setting( 'voz_turn_user' ),
			'credential' => (string) lk_decrypt( lk_setting( 'voz_turn_pass' ) ),
		);
	}
	return $ice;
}

/* -----------------------------------------------------------------------
 * Chamar atenção
 * -------------------------------------------------------------------- */

function lk_api_nudge( WP_REST_Request $r ) {
	$me   = get_current_user_id();
	$last = (int) get_user_meta( $me, 'lk_nudge_last', true );
	if ( time() - $last < 20 ) {
		return new WP_Error( 'lk', 'Calma! Espere alguns segundos para chamar atenção de novo.', array( 'status' => 429 ) );
	}
	update_user_meta( $me, 'lk_nudge_last', time() );
	$list   = get_option( 'lk_nudges', array() );
	$list   = is_array( $list ) ? $list : array();
	$id     = (int) ( microtime( true ) * 1000 );
	$list[] = array(
		'id'   => $id,
		'from' => $me,
		'name' => wp_get_current_user()->display_name,
		'ini'  => lk_user_badge( wp_get_current_user() ),
		'msg'  => mb_substr( sanitize_text_field( (string) $r['msg'] ), 0, 200 ),
		'at'   => time(),
	);
	update_option( 'lk_nudges', array_slice( $list, -20 ), false );
	// Quem manda não recebe o próprio.
	update_user_meta( $me, 'lk_nudge_seen', $id );
	return array( 'ok' => true );
}

/**
 * Chamadas de atenção novas para esta pessoa (vão junto no sinal da bolinha).
 */
function lk_nudges_for( $me ) {
	$seen = (int) get_user_meta( $me, 'lk_nudge_seen', true );
	$out  = array();
	$max  = $seen;
	foreach ( (array) get_option( 'lk_nudges', array() ) as $n ) {
		if ( $n['id'] > $seen && (int) $n['from'] !== $me && time() - (int) $n['at'] < 600 ) {
			$out[] = $n;
		}
		$max = max( $max, (int) $n['id'] );
	}
	if ( $max > $seen ) {
		update_user_meta( $me, 'lk_nudge_seen', $max );
	}
	return $out;
}

<?php
/**
 * Chat cliente ↔ equipe, por cliente, com o pedido do assunto (ou "geral").
 * Sininho: contador de mensagens não lidas no topo do painel e da área do cliente (atualiza a cada 20 s).
 * E-mail: a equipe recebe quando chega mensagem (no máx. 1 a cada 15 min por cliente); o cliente recebe quando a equipe responde.
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_action(
	'rest_api_init',
	function () {
		$who = function () {
			return is_user_logged_in() && ( lk_is_team() || lk_current_client() );
		};
		register_rest_route( 'lk/v1', '/chat', array( array( 'methods' => 'GET', 'callback' => 'lk_api_chat_get', 'permission_callback' => $who ), array( 'methods' => 'POST', 'callback' => 'lk_api_chat_send', 'permission_callback' => $who ) ) );
		register_rest_route( 'lk/v1', '/chat/unread', array( 'methods' => 'GET', 'callback' => 'lk_api_chat_unread', 'permission_callback' => $who ) );
	}
);

/**
 * Cliente da conversa: o próprio cliente logado, ou o escolhido pela equipe.
 */
function lk_chat_client( $requested = 0 ) {
	if ( lk_is_team() ) {
		return $requested ? lk_get( 'clients', $requested ) : null;
	}
	return lk_current_client();
}

function lk_chat_unread_count( $client_id = 0 ) {
	global $wpdb;
	$t = lk_table( 'messages' );
	if ( lk_is_team() ) {
		return (int) $wpdb->get_var( "SELECT COUNT(*) FROM $t WHERE from_client = 1 AND read_at IS NULL" ); // phpcs:ignore WordPress.DB.PreparedSQL
	}
	return (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM $t WHERE client_id = %d AND from_client = 0 AND read_at IS NULL", $client_id ) ); // phpcs:ignore WordPress.DB.PreparedSQL
}

function lk_api_chat_unread() {
	$c = lk_is_team() ? null : lk_current_client();
	return array( 'n' => lk_chat_unread_count( $c ? $c->id : 0 ) );
}

function lk_chat_thread( $client_id ) {
	$rows = lk_rows( 'messages', 'client_id = %d', array( $client_id ), 'id DESC LIMIT 200' );
	$out  = array();
	foreach ( array_reverse( $rows ) as $m ) {
		$u     = $m->user_id ? get_userdata( $m->user_id ) : null;
		$out[] = array(
			'id'      => (int) $m->id,
			'mine'    => lk_is_team() ? ! $m->from_client : (bool) $m->from_client,
			'who'     => $m->from_client ? 'Cliente' : ( $u ? $u->display_name : lk_setting( 'empresa' ) ),
			'body'    => $m->body,
			'project' => (int) $m->project_id,
			'at'      => lk_date( $m->created_at, 'd/m H:i' ),
			'read'    => (bool) $m->read_at,
		);
	}
	return $out;
}

function lk_api_chat_get( WP_REST_Request $r ) {
	$c = lk_chat_client( absint( $r['client'] ) );
	if ( ! $c ) {
		return new WP_Error( 'ap', 'Conversa não encontrada.', array( 'status' => 404 ) );
	}
	global $wpdb;
	// Marca como lidas as mensagens do outro lado.
	$wpdb->query( $wpdb->prepare( 'UPDATE ' . lk_table( 'messages' ) . ' SET read_at = %s WHERE client_id = %d AND from_client = %d AND read_at IS NULL', lk_now(), $c->id, lk_is_team() ? 1 : 0 ) ); // phpcs:ignore WordPress.DB.PreparedSQL
	return array( 'messages' => lk_chat_thread( $c->id ), 'unread' => lk_chat_unread_count( $c->id ) );
}

function lk_api_chat_send( WP_REST_Request $r ) {
	$c    = lk_chat_client( absint( $r['client'] ) );
	$body = trim( sanitize_textarea_field( (string) $r['body'] ) );
	if ( ! $c || '' === $body ) {
		return new WP_Error( 'ap', 'Escreva a mensagem.', array( 'status' => 400 ) );
	}
	$pid = absint( $r['project'] );
	if ( $pid ) {
		$p = lk_get( 'posts', $pid );
		if ( ! $p || (int) $p->client_id !== (int) $c->id ) {
			$pid = 0;
		}
	}
	$team = lk_is_team();
	lk_insert(
		'messages',
		array(
			'client_id'   => $c->id,
			'project_id'  => $pid,
			'user_id'     => get_current_user_id(),
			'from_client' => $team ? 0 : 1,
			'body'        => mb_substr( $body, 0, 4000 ),
		)
	);
	if ( $pid ) {
		lk_post_log( $pid, ( $team ? 'Equipe' : 'Cliente' ) . ' no chat: ' . mb_substr( $body, 0, 160 ) );
	}
	lk_chat_email( $c, $body, $pid, $team );
	return array( 'messages' => lk_chat_thread( $c->id ) );
}

function lk_chat_email( $client, $body, $pid, $from_team ) {
	$key = 'lk_chatmail_' . ( $from_team ? 'c' : 't' ) . $client->id;
	if ( get_transient( $key ) ) {
		return;
	}
	set_transient( $key, 1, 15 * MINUTE_IN_SECONDS );
	$ref = $pid ? ' (post #' . $pid . ')' : '';
	if ( $from_team ) {
		if ( is_email( $client->email ) ) {
			lk_mail( $client->email, 'Nova mensagem da ' . lk_setting( 'empresa' ) . $ref, 'Você tem uma mensagem nova', '<p style="padding:12px 14px;background:#f5f5f7;border-radius:10px;">' . nl2br( esc_html( mb_substr( $body, 0, 600 ) ) ) . '</p>', array(), 'Responder', lk_client_entry_url( $client, 'mensagens' ) );
		}
		return;
	}
	$to = lk_setting( 'email' ) ? lk_setting( 'email' ) : get_option( 'admin_email' );
	if ( is_email( $to ) ) {
		lk_mail( $to, 'Mensagem de ' . lk_client_label( $client ) . $ref, 'Mensagem de ' . lk_client_label( $client ), '<p style="padding:12px 14px;background:#f5f5f7;border-radius:10px;">' . nl2br( esc_html( mb_substr( $body, 0, 600 ) ) ) . '</p>', array(), 'Responder no painel', lk_panel_url( 'mensagens', 0, array( 'cliente' => $client->id ) ) );
	}
}

/**
 * Conversas para a caixa de entrada da equipe (última mensagem + não lidas).
 */
function lk_chat_inbox() {
	global $wpdb;
	$t = lk_table( 'messages' );
	return $wpdb->get_results( "SELECT client_id, MAX(id) last_id, SUM(from_client = 1 AND read_at IS NULL) unread FROM $t GROUP BY client_id ORDER BY last_id DESC LIMIT 100" ); // phpcs:ignore WordPress.DB.PreparedSQL
}

/**
 * Sininho (HTML): vai no topo do painel e da área do cliente.
 */
function lk_bell_html() {
	$c   = lk_is_team() ? null : lk_current_client();
	$n   = lk_chat_unread_count( $c ? $c->id : 0 );
	$url = lk_is_team() ? lk_panel_url( 'mensagens' ) : lk_client_link( 'mensagens' );
	return '<a class="bell" href="' . esc_url( $url ) . '" data-bell title="Mensagens">' . lk_icon( 'sino', 18 ) . '<em class="bell-n"' . ( $n ? '' : ' hidden' ) . '>' . (int) $n . '</em></a>';
}

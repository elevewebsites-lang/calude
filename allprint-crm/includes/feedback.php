<?php
/**
 * Apontamentos: quem testa o sistema marca um ponto em qualquer tela e escreve o que quer mudar.
 *
 *  - Botão "Apontar" no canto de baixo, à esquerda → clica no ponto da tela → escreve → salva.
 *  - Os pontos ficam numerados na própria tela (a equipe vê todos; o cliente, só os dele).
 *  - Painel → Apontamentos: lista com filtro, "abrir no ponto", responder, resolver e copiar tudo em texto.
 *  - Aviso por e-mail quando chega apontamento novo (no máx. 1 a cada 10 min).
 *
 * Liga/desliga em Configurações → Apontamentos (equipe e, à parte, área do cliente).
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_action(
	'rest_api_init',
	function () {
		$who = function () {
			return ap_feedback_allowed();
		};
		register_rest_route( 'ap/v1', '/feedback', array( array( 'methods' => 'GET', 'callback' => 'ap_api_feedback_list', 'permission_callback' => $who ), array( 'methods' => 'POST', 'callback' => 'ap_api_feedback_add', 'permission_callback' => $who ) ) );
		register_rest_route( 'ap/v1', '/feedback/(?P<id>\d+)', array( array( 'methods' => 'POST', 'callback' => 'ap_api_feedback_edit', 'permission_callback' => $who ), array( 'methods' => 'DELETE', 'callback' => 'ap_api_feedback_delete', 'permission_callback' => $who ) ) );
	}
);

/**
 * Quem pode apontar agora: equipe (se ligado) e clientes (se ligado para a área do cliente).
 */
function ap_feedback_allowed() {
	if ( ! is_user_logged_in() ) {
		return false;
	}
	if ( ap_is_team() ) {
		return '1' === (string) ap_setting( 'apontamentos' );
	}
	return '1' === (string) ap_setting( 'apontamentos_clientes' ) && ap_current_client();
}

function ap_feedback_open_count() {
	global $wpdb;
	return (int) $wpdb->get_var( 'SELECT COUNT(*) FROM ' . ap_table( 'feedback' ) . " WHERE status = 'aberto'" ); // phpcs:ignore WordPress.DB.PreparedSQL
}

/**
 * Caminho da tela, sem o domínio e sem o ?apontamento= (os pontos são por tela).
 */
function ap_feedback_path( $url ) {
	$path = (string) wp_parse_url( (string) $url, PHP_URL_PATH );
	return '/' . trim( $path, '/' ) . '/';
}

function ap_feedback_author( $f ) {
	$u = $f->user_id ? get_userdata( $f->user_id ) : null;
	$c = $f->client_id ? ap_get( 'clients', $f->client_id ) : null;
	if ( $c ) {
		return ap_client_label( $c ) . ( $u ? ' (' . $u->display_name . ')' : '' );
	}
	return $u ? $u->display_name : '—';
}

/**
 * Pode editar/excluir: quem apontou ou o admin. Resolver e responder: a equipe.
 */
function ap_feedback_mine( $f ) {
	return (int) $f->user_id === get_current_user_id() || ap_is_admin();
}

function ap_feedback_json( $f ) {
	return array(
		'id'      => (int) $f->id,
		'sel'     => (string) $f->sel,
		'ox'      => (float) $f->ox,
		'oy'      => (float) $f->oy,
		'px'      => (float) $f->px,
		'py'      => (int) $f->py,
		'body'    => (string) $f->body,
		'reply'   => (string) $f->reply,
		'status'  => (string) $f->status,
		'who'     => ap_feedback_author( $f ),
		'at'      => ap_ago( $f->created_at ),
		'mine'    => ap_feedback_mine( $f ),
		'device'  => (int) $f->vw < 768 ? 'celular' : ( (int) $f->vw < 1100 ? 'tablet' : 'computador' ),
	);
}

function ap_api_feedback_list( WP_REST_Request $r ) {
	$path  = ap_feedback_path( (string) $r['path'] );
	$where = "path = %s AND status = 'aberto'";
	$args  = array( $path );
	if ( ! ap_is_team() ) {
		$where .= ' AND user_id = %d';
		$args[] = get_current_user_id();
	}
	return array(
		'items' => array_map( 'ap_feedback_json', ap_rows( 'feedback', $where, $args, 'id' ) ),
		'open'  => ap_is_team() ? ap_feedback_open_count() : 0,
	);
}

function ap_api_feedback_add( WP_REST_Request $r ) {
	$body = trim( sanitize_textarea_field( (string) $r['body'] ) );
	if ( '' === $body ) {
		return new WP_Error( 'ap', 'Escreva o que precisa mudar.', array( 'status' => 400 ) );
	}
	// Até 60 apontamentos por hora por pessoa (evita envio em loop).
	$key = 'ap_fb_rate_' . get_current_user_id();
	$n   = (int) get_transient( $key );
	if ( $n >= 60 ) {
		return new WP_Error( 'ap', 'Muitos apontamentos seguidos. Espere um pouco.', array( 'status' => 429 ) );
	}
	set_transient( $key, $n + 1, HOUR_IN_SECONDS );
	$url    = esc_url_raw( (string) $r['url'] );
	$client = ap_is_team() ? null : ap_current_client();
	$id     = ap_insert(
		'feedback',
		array(
			'user_id'   => get_current_user_id(),
			'client_id' => $client ? $client->id : 0,
			'url'       => mb_substr( remove_query_arg( 'apontamento', $url ), 0, 500 ),
			'path'      => ap_feedback_path( $url ),
			'page'      => mb_substr( sanitize_text_field( (string) $r['page'] ), 0, 190 ),
			'sel'       => mb_substr( sanitize_text_field( (string) $r['sel'] ), 0, 1000 ),
			'snippet'   => mb_substr( sanitize_text_field( (string) $r['snippet'] ), 0, 190 ),
			'ox'        => max( 0, min( 1, (float) $r['ox'] ) ),
			'oy'        => max( 0, min( 1, (float) $r['oy'] ) ),
			'px'        => max( 0, min( 1, (float) $r['px'] ) ),
			'py'        => max( 0, (int) $r['py'] ),
			'vw'        => min( 10000, absint( $r['vw'] ) ),
			'body'      => mb_substr( $body, 0, 4000 ),
			'status'    => 'aberto',
		)
	);
	$f = ap_get( 'feedback', $id );
	ap_feedback_email( $f );
	return array( 'item' => ap_feedback_json( $f ), 'open' => ap_is_team() ? ap_feedback_open_count() : 0 );
}

function ap_api_feedback_edit( WP_REST_Request $r ) {
	$f = ap_get( 'feedback', absint( $r['id'] ) );
	if ( ! $f || ( ! ap_is_team() && (int) $f->user_id !== get_current_user_id() ) ) {
		return new WP_Error( 'ap', 'Apontamento não encontrado.', array( 'status' => 404 ) );
	}
	$data = array();
	if ( null !== $r['body'] && ap_feedback_mine( $f ) ) {
		$body = trim( sanitize_textarea_field( (string) $r['body'] ) );
		if ( '' !== $body ) {
			$data['body'] = mb_substr( $body, 0, 4000 );
		}
	}
	if ( ap_is_team() ) {
		if ( null !== $r['reply'] ) {
			$data['reply'] = mb_substr( trim( sanitize_textarea_field( (string) $r['reply'] ) ), 0, 4000 );
		}
		if ( in_array( (string) $r['status'], array( 'aberto', 'resolvido' ), true ) ) {
			$data['status']      = (string) $r['status'];
			$data['resolved_by'] = 'resolvido' === $r['status'] ? get_current_user_id() : 0;
			$data['resolved_at'] = 'resolvido' === $r['status'] ? ap_now() : null;
		}
	}
	if ( $data ) {
		ap_update( 'feedback', $f->id, $data );
	}
	return array( 'item' => ap_feedback_json( ap_get( 'feedback', $f->id ) ), 'open' => ap_is_team() ? ap_feedback_open_count() : 0 );
}

function ap_api_feedback_delete( WP_REST_Request $r ) {
	$f = ap_get( 'feedback', absint( $r['id'] ) );
	if ( ! $f || ! ap_feedback_mine( $f ) ) {
		return new WP_Error( 'ap', 'Só quem apontou pode excluir.', array( 'status' => 403 ) );
	}
	ap_delete( 'feedback', $f->id );
	return array( 'ok' => true, 'open' => ap_is_team() ? ap_feedback_open_count() : 0 );
}

/**
 * E-mail de aviso: no máximo um a cada 10 minutos (quem aponta costuma mandar vários seguidos).
 */
function ap_feedback_email( $f ) {
	if ( get_transient( 'ap_fb_mail' ) ) {
		return;
	}
	$to = ap_setting( 'apontamentos_email' ) ? ap_setting( 'apontamentos_email' ) : get_option( 'admin_email' );
	if ( ! is_email( $to ) ) {
		return;
	}
	set_transient( 'ap_fb_mail', 1, 10 * MINUTE_IN_SECONDS );
	$intro = '<p><strong>' . esc_html( ap_feedback_author( $f ) ) . '</strong> apontou na tela <em>' . esc_html( $f->page ? $f->page : $f->path ) . '</em>:</p>'
		. '<p style="padding:12px 14px;background:#f5f5f7;border-radius:10px;">' . nl2br( esc_html( mb_substr( $f->body, 0, 800 ) ) ) . '</p>'
		. '<p style="font-size:13px;color:#737373;">Se chegarem outros nos próximos minutos, eles aparecem juntos na lista (sem outro e-mail).</p>';
	ap_mail( $to, 'Novo apontamento no sistema: #' . $f->id, 'Novo apontamento', $intro, array(), 'Ver os apontamentos', ap_panel_url( 'apontamentos' ) );
}

/**
 * Link que abre a tela já no ponto.
 */
function ap_feedback_link( $f ) {
	// A URL guardada já traz o caminho completo (inclusive a subpasta do WordPress): junta só com o domínio.
	$h      = wp_parse_url( home_url() );
	$origin = $h['scheme'] . '://' . $h['host'] . ( isset( $h['port'] ) ? ':' . $h['port'] : '' );
	return add_query_arg( 'apontamento', (int) $f->id, $origin . $f->url );
}

/**
 * Lista em texto (para mandar ao desenvolvedor ou colar numa conversa).
 */
function ap_feedback_text( $rows ) {
	$out = array();
	foreach ( $rows as $f ) {
		$line  = '#' . $f->id . ' [' . ( 'resolvido' === $f->status ? 'resolvido' : 'aberto' ) . '] ' . ap_date( $f->created_at, 'd/m H:i' ) . ' · ' . ap_feedback_author( $f ) . "\n";
		$line .= 'Tela: ' . ( $f->page ? $f->page . ' · ' : '' ) . $f->url . ( (int) $f->vw ? ' (tela de ' . (int) $f->vw . ' px)' : '' ) . "\n";
		if ( $f->snippet ) {
			$line .= 'Onde: "' . $f->snippet . '"' . "\n";
		}
		$line .= 'Pedido: ' . $f->body;
		if ( $f->reply ) {
			$line .= "\nResposta: " . $f->reply;
		}
		$out[] = $line;
	}
	return implode( "\n\n", $out );
}

/* -----------------------------------------------------------------------
 * Formulários do painel (lista de apontamentos)
 * -------------------------------------------------------------------- */

function ap_do_feedback_save() {
	if ( ! ap_is_team() ) {
		wp_die( 'Sem permissão.', '', array( 'response' => 403 ) );
	}
	$f = ap_get( 'feedback', ap_in( 'id', 'int' ) );
	if ( ! $f ) {
		ap_back( 'Apontamento não encontrado.', 'erro' );
	}
	$data = array( 'reply' => ap_in( 'reply', 'textarea' ) );
	$st   = ap_in( 'status' );
	if ( in_array( $st, array( 'aberto', 'resolvido' ), true ) ) {
		$data['status']      = $st;
		$data['resolved_by'] = 'resolvido' === $st ? get_current_user_id() : 0;
		$data['resolved_at'] = 'resolvido' === $st ? ap_now() : null;
	}
	ap_update( 'feedback', $f->id, $data );
	ap_back( 'resolvido' === $st ? 'Apontamento #' . $f->id . ' resolvido.' : 'Apontamento #' . $f->id . ' salvo.' );
}

function ap_do_feedback_delete() {
	$f = ap_get( 'feedback', ap_in( 'id', 'int' ) );
	if ( ! $f || ! ap_is_team() || ! ap_feedback_mine( $f ) ) {
		wp_die( 'Só quem apontou (ou o administrador) pode excluir.', '', array( 'response' => 403 ) );
	}
	ap_delete( 'feedback', $f->id );
	ap_back( 'Apontamento excluído.' );
}

/** Admin: liga ou desliga o botão Apontar para TODOS (equipe e clientes) de uma vez. */
function ap_do_feedback_toggle_all() {
	ap_require( 'admin' );
	$on  = ap_in( 'ligar', 'bool' ) ? '1' : '0';
	$st  = get_option( 'ap_settings', array() );
	$st  = is_array( $st ) ? $st : array();
	$st['apontamentos']          = $on;
	$st['apontamentos_clientes'] = $on;
	update_option( 'ap_settings', $st );
	ap_back( $on ? 'Apontamentos ligados para a equipe e para os clientes.' : 'Apontamentos desligados para todos. Os que já foram feitos continuam aqui.' );
}

function ap_do_feedback_resolve_all() {
	ap_require( 'admin' );
	global $wpdb;
	$wpdb->query( $wpdb->prepare( 'UPDATE ' . ap_table( 'feedback' ) . " SET status = 'resolvido', resolved_by = %d, resolved_at = %s WHERE status = 'aberto'", get_current_user_id(), ap_now() ) ); // phpcs:ignore WordPress.DB.PreparedSQL
	ap_back( 'Todos os apontamentos abertos foram marcados como resolvidos.' );
}

/**
 * Script do botão "Apontar" (vai no fim das telas do painel e da área do cliente).
 */
function ap_feedback_script() {
	if ( ! ap_feedback_allowed() || ! in_array( get_query_var( 'ap_route' ), array( 'panel', 'client' ), true ) ) {
		return;
	}
	echo '<script>window.AP_FB = ' . wp_json_encode(
		array(
			'team'  => ap_is_team(),
			'list'  => ap_is_team() ? ap_panel_url( 'apontamentos' ) : '',
			'focus' => isset( $_GET['apontamento'] ) ? absint( $_GET['apontamento'] ) : 0, // phpcs:ignore WordPress.Security.NonceVerification
		)
	) . ';</script>';
	echo '<script src="' . esc_url( AP_URL . 'assets/feedback.js?ver=' . AP_VERSION ) . '"></script>';
}

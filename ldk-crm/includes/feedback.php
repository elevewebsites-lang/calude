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
			return lk_feedback_allowed();
		};
		register_rest_route( 'lk/v1', '/feedback', array( array( 'methods' => 'GET', 'callback' => 'lk_api_feedback_list', 'permission_callback' => $who ), array( 'methods' => 'POST', 'callback' => 'lk_api_feedback_add', 'permission_callback' => $who ) ) );
		register_rest_route( 'lk/v1', '/feedback/(?P<id>\d+)', array( array( 'methods' => 'POST', 'callback' => 'lk_api_feedback_edit', 'permission_callback' => $who ), array( 'methods' => 'DELETE', 'callback' => 'lk_api_feedback_delete', 'permission_callback' => $who ) ) );
	}
);

/**
 * Quem pode apontar agora: equipe (se ligado) e clientes (se ligado para a área do cliente).
 */
function lk_feedback_allowed() {
	if ( ! is_user_logged_in() ) {
		return false;
	}
	if ( lk_is_team() ) {
		return '1' === (string) lk_setting( 'apontamentos' ) && lk_note_can();
	}
	return '1' === (string) lk_setting( 'apontamentos_clientes' ) && lk_current_client() && lk_note_can();
}

/**
 * Manda o apontamento para o Eleve CRM (vira tarefa no projeto). Em segundo plano: não trava a tela.
 * Junto vai o endereço de volta: quando a tarefa for concluída lá, este sistema mostra "resolvido" para o cliente.
 * $evento: novo | status
 */
function lk_feedback_push( $f, $evento = 'novo', $shot = '' ) {
	$url = trim( (string) lk_setting( 'apontamentos_eleve' ) );
	if ( ! $f || ! preg_match( '#^https://#i', $url ) ) {
		return;
	}
	wp_remote_post(
		$url,
		array(
			'timeout'  => 4,
			'blocking' => false,
			'headers'  => array( 'Content-Type' => 'application/json' ),
			'body'     => wp_json_encode(
				array(
					'evento'    => $evento,
					'sistema'   => (string) wp_parse_url( home_url(), PHP_URL_HOST ),
					'ref'       => (int) $f->id,
					'status'    => (string) $f->status,
					'body'      => (string) $f->body,
					'page'      => (string) $f->page,
					'path'      => (string) $f->path,
					'snippet'   => (string) $f->snippet,
					'url'       => (string) $f->url . ( false === strpos( (string) $f->url, '?' ) ? '?' : '&' ) . 'apontamento=' . (int) $f->id,
					'autor'     => lk_feedback_author( $f ),
					'reply'     => (string) $f->reply,
					'retorno'   => rest_url( 'lk/v1/feedback-retorno' ),
					'retorno_k' => lk_feedback_k( (int) $f->id ),
					'shot'      => ( is_string( $shot ) && preg_match( '#^data:image/(jpeg|png);base64,#', $shot ) && strlen( $shot ) < 1200000 ) ? $shot : '',
				)
			),
		)
	);
}

/** Chave que só este sistema sabe fazer: protege o endereço de volta. */
function lk_feedback_k( $ref ) {
	return substr( hash_hmac( 'sha256', 'retorno|' . (int) $ref, wp_salt( 'auth' ) ), 0, 24 );
}

add_action(
	'rest_api_init',
	function () {
		register_rest_route( 'lk/v1', '/feedback-retorno', array( 'methods' => 'POST', 'permission_callback' => '__return_true', 'callback' => 'lk_api_feedback_retorno' ) );
	}
);

/** O Eleve CRM avisa que o apontamento foi resolvido (ou reaberto): aparece para o cliente no botão Apontar. */
function lk_api_feedback_retorno( WP_REST_Request $r ) {
	$j   = json_decode( (string) $r->get_body(), true );
	$j   = is_array( $j ) ? $j : array();
	$ref = absint( $j['ref'] ?? 0 );
	if ( ! $ref || ! hash_equals( lk_feedback_k( $ref ), (string) ( $j['k'] ?? '' ) ) ) {
		return new WP_Error( 'lk', 'Chave inválida.', array( 'status' => 403 ) );
	}
	$f = lk_get( 'feedback', $ref );
	if ( ! $f ) {
		return new WP_Error( 'lk', 'Apontamento não encontrado.', array( 'status' => 404 ) );
	}
	$done = 'resolvido' === ( $j['status'] ?? '' );
	$data = array( 'status' => $done ? 'resolvido' : 'aberto', 'resolved_by' => 0, 'resolved_at' => $done ? lk_now() : null );
	if ( $done && '' === trim( (string) $f->reply ) ) {
		$data['reply'] = 'Resolvido pela equipe da Eleve. ✓';
	}
	lk_update( 'feedback', $ref, $data );
	return array( 'ok' => true );
}

/** Botão "Testar conexão" na configuração. */
function lk_do_feedback_test() {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( 'Sem permissão.' );
	}
	$url = trim( (string) lk_setting( 'apontamentos_eleve' ) );
	if ( ! preg_match( '#^https://#i', $url ) ) {
		lk_back( 'Cole primeiro o endereço do Eleve CRM, salve e depois teste.', 'erro' );
	}
	$res = wp_remote_post( $url, array( 'timeout' => 12, 'headers' => array( 'Content-Type' => 'application/json' ), 'body' => wp_json_encode( array( 'evento' => 'teste', 'sistema' => (string) wp_parse_url( home_url(), PHP_URL_HOST ) ) ) ) );
	if ( is_wp_error( $res ) ) {
		lk_back( 'Não consegui falar com o Eleve CRM: ' . $res->get_error_message(), 'erro' );
	}
	$code = (int) wp_remote_retrieve_response_code( $res );
	$j    = json_decode( wp_remote_retrieve_body( $res ), true );
	if ( 200 === $code && ! empty( $j['ok'] ) ) {
		lk_back( 'Conectado ✓ Projeto: ' . ( $j['projeto'] ?? '' ) . ( ! empty( $j['cliente'] ) ? ' · Cliente: ' . $j['cliente'] : '' ) . '. Os apontamentos já caem lá como tarefa.', 'ok' );
	}
	lk_back( 'O Eleve CRM respondeu: ' . ( is_array( $j ) && ! empty( $j['message'] ) ? $j['message'] : 'erro ' . $code ) . '. Confira o endereço copiado do projeto.', 'erro' );
}

function lk_feedback_open_count() {
	global $wpdb;
	return (int) $wpdb->get_var( 'SELECT COUNT(*) FROM ' . lk_table( 'feedback' ) . " WHERE status = 'aberto' AND path <> 'feedback'" ); // phpcs:ignore WordPress.DB.PreparedSQL
}

/**
 * Caminho da tela, sem o domínio e sem o ?apontamento= (os pontos são por tela).
 */
function lk_feedback_path( $url ) {
	$path = (string) wp_parse_url( (string) $url, PHP_URL_PATH );
	return '/' . trim( $path, '/' ) . '/';
}

function lk_feedback_author( $f ) {
	$u = $f->user_id ? get_userdata( $f->user_id ) : null;
	$c = $f->client_id ? lk_get( 'clients', $f->client_id ) : null;
	if ( $c ) {
		return lk_client_label( $c ) . ( $u ? ' (' . $u->display_name . ')' : '' );
	}
	return $u ? $u->display_name : '—';
}

/**
 * Pode editar/excluir: quem apontou ou o admin. Resolver e responder: a equipe.
 */
function lk_feedback_mine( $f ) {
	return (int) $f->user_id === get_current_user_id() || lk_is_admin();
}

function lk_feedback_json( $f ) {
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
		'who'     => lk_feedback_author( $f ),
		'at'      => lk_ago( $f->created_at ),
		'mine'    => lk_feedback_mine( $f ),
		'device'  => (int) $f->vw < 768 ? 'celular' : ( (int) $f->vw < 1100 ? 'tablet' : 'computador' ),
	);
}

function lk_api_feedback_list( WP_REST_Request $r ) {
	$path  = lk_feedback_path( (string) $r['path'] );
	$where = "path = %s AND status = 'aberto'";
	$args  = array( $path );
	if ( ! lk_is_team() ) {
		$where .= ' AND user_id = %d';
		$args[] = get_current_user_id();
	}
	return array(
		'items' => array_map( 'lk_feedback_json', lk_rows( 'feedback', $where, $args, 'id' ) ),
		'open'  => lk_is_team() ? lk_feedback_open_count() : 0,
	);
}

function lk_api_feedback_add( WP_REST_Request $r ) {
	$body = trim( sanitize_textarea_field( (string) $r['body'] ) );
	if ( '' === $body ) {
		return new WP_Error( 'lk', 'Escreva o que precisa mudar.', array( 'status' => 400 ) );
	}
	// Até 60 apontamentos por hora por pessoa (evita envio em loop).
	$key = 'lk_fb_rate_' . get_current_user_id();
	$n   = (int) get_transient( $key );
	if ( $n >= 60 ) {
		return new WP_Error( 'lk', 'Muitos apontamentos seguidos. Espere um pouco.', array( 'status' => 429 ) );
	}
	set_transient( $key, $n + 1, HOUR_IN_SECONDS );
	$url    = esc_url_raw( (string) $r['url'] );
	$client = lk_is_team() ? null : lk_current_client();
	$id     = lk_insert(
		'feedback',
		array(
			'user_id'   => get_current_user_id(),
			'client_id' => $client ? $client->id : 0,
			'url'       => mb_substr( remove_query_arg( 'apontamento', $url ), 0, 500 ),
			'path'      => lk_feedback_path( $url ),
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
	$f = lk_get( 'feedback', $id );
	lk_feedback_email( $f );
	lk_feedback_push( $f, 'novo', (string) $r['shot'] );
	return array( 'item' => lk_feedback_json( $f ), 'open' => lk_is_team() ? lk_feedback_open_count() : 0 );
}

function lk_api_feedback_edit( WP_REST_Request $r ) {
	$f = lk_get( 'feedback', absint( $r['id'] ) );
	if ( ! $f || ( ! lk_is_team() && (int) $f->user_id !== get_current_user_id() ) ) {
		return new WP_Error( 'lk', 'Apontamento não encontrado.', array( 'status' => 404 ) );
	}
	$data = array();
	if ( null !== $r['body'] && lk_feedback_mine( $f ) ) {
		$body = trim( sanitize_textarea_field( (string) $r['body'] ) );
		if ( '' !== $body ) {
			$data['body'] = mb_substr( $body, 0, 4000 );
		}
	}
	if ( lk_is_team() ) {
		if ( null !== $r['reply'] ) {
			$data['reply'] = mb_substr( trim( sanitize_textarea_field( (string) $r['reply'] ) ), 0, 4000 );
		}
		if ( in_array( (string) $r['status'], array( 'aberto', 'resolvido' ), true ) ) {
			$data['status']      = (string) $r['status'];
			$data['resolved_by'] = 'resolvido' === $r['status'] ? get_current_user_id() : 0;
			$data['resolved_at'] = 'resolvido' === $r['status'] ? lk_now() : null;
		}
	}
	if ( $data ) {
		lk_update( 'feedback', $f->id, $data );
		if ( isset( $data['status'] ) && $data['status'] !== $f->status ) {
			lk_feedback_push( lk_get( 'feedback', $f->id ), 'status' );
		}
	}
	return array( 'item' => lk_feedback_json( lk_get( 'feedback', $f->id ) ), 'open' => lk_is_team() ? lk_feedback_open_count() : 0 );
}

function lk_api_feedback_delete( WP_REST_Request $r ) {
	$f = lk_get( 'feedback', absint( $r['id'] ) );
	if ( ! $f || ! lk_feedback_mine( $f ) ) {
		return new WP_Error( 'lk', 'Só quem apontou pode excluir.', array( 'status' => 403 ) );
	}
	lk_delete( 'feedback', $f->id );
	return array( 'ok' => true, 'open' => lk_is_team() ? lk_feedback_open_count() : 0 );
}

/**
 * E-mail de aviso: no máximo um a cada 10 minutos (quem aponta costuma mandar vários seguidos).
 */
function lk_feedback_email( $f ) {
	if ( get_transient( 'lk_fb_mail' ) ) {
		return;
	}
	$to = lk_setting( 'apontamentos_email' ) ? lk_setting( 'apontamentos_email' ) : get_option( 'admin_email' );
	if ( ! is_email( $to ) ) {
		return;
	}
	set_transient( 'lk_fb_mail', 1, 10 * MINUTE_IN_SECONDS );
	$intro = '<p><strong>' . esc_html( lk_feedback_author( $f ) ) . '</strong> apontou na tela <em>' . esc_html( $f->page ? $f->page : $f->path ) . '</em>:</p>'
		. '<p style="padding:12px 14px;background:#f5f5f7;border-radius:10px;">' . nl2br( esc_html( mb_substr( $f->body, 0, 800 ) ) ) . '</p>'
		. '<p style="font-size:13px;color:#737373;">Se chegarem outros nos próximos minutos, eles aparecem juntos na lista (sem outro e-mail).</p>';
	lk_mail( $to, 'Novo apontamento no sistema: #' . $f->id, 'Novo apontamento', $intro, array(), 'Ver os apontamentos', lk_panel_url( 'apontamentos' ) );
}

/**
 * Link que abre a tela já no ponto.
 */
function lk_feedback_link( $f ) {
	// A URL guardada já traz o caminho completo (inclusive a subpasta do WordPress): junta só com o domínio.
	$h      = wp_parse_url( home_url() );
	$origin = $h['scheme'] . '://' . $h['host'] . ( isset( $h['port'] ) ? ':' . $h['port'] : '' );
	return add_query_arg( 'apontamento', (int) $f->id, $origin . $f->url );
}

/**
 * Lista em texto (para mandar ao desenvolvedor ou colar numa conversa).
 */
function lk_feedback_text( $rows ) {
	$out = array();
	foreach ( $rows as $f ) {
		$line  = '#' . $f->id . ' [' . ( 'resolvido' === $f->status ? 'resolvido' : 'aberto' ) . '] ' . lk_date( $f->created_at, 'd/m H:i' ) . ' · ' . lk_feedback_author( $f ) . "\n";
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

function lk_do_feedback_save() {
	if ( ! lk_is_team() ) {
		wp_die( 'Sem permissão.', '', array( 'response' => 403 ) );
	}
	$f = lk_get( 'feedback', lk_in( 'id', 'int' ) );
	if ( ! $f ) {
		lk_back( 'Apontamento não encontrado.', 'erro' );
	}
	$data = array( 'reply' => lk_in( 'reply', 'textarea' ) );
	$st   = lk_in( 'status' );
	if ( in_array( $st, array( 'aberto', 'resolvido' ), true ) ) {
		$data['status']      = $st;
		$data['resolved_by'] = 'resolvido' === $st ? get_current_user_id() : 0;
		$data['resolved_at'] = 'resolvido' === $st ? lk_now() : null;
	}
	lk_update( 'feedback', $f->id, $data );
	lk_back( 'resolvido' === $st ? 'Apontamento #' . $f->id . ' resolvido.' : 'Apontamento #' . $f->id . ' salvo.' );
}

function lk_do_feedback_delete() {
	$f = lk_get( 'feedback', lk_in( 'id', 'int' ) );
	if ( ! $f || ! lk_is_team() || ! lk_feedback_mine( $f ) ) {
		wp_die( 'Só quem apontou (ou o administrador) pode excluir.', '', array( 'response' => 403 ) );
	}
	lk_delete( 'feedback', $f->id );
	lk_back( 'Apontamento excluído.' );
}

function lk_do_feedback_resolve_all() {
	lk_require( 'admin' );
	global $wpdb;
	$wpdb->query( $wpdb->prepare( 'UPDATE ' . lk_table( 'feedback' ) . " SET status = 'resolvido', resolved_by = %d, resolved_at = %s WHERE status = 'aberto' AND path <> 'feedback'", get_current_user_id(), lk_now() ) ); // phpcs:ignore WordPress.DB.PreparedSQL
	lk_back( 'Todos os apontamentos abertos foram marcados como resolvidos.' );
}

/**
 * Script do botão "Apontar" (vai no fim das telas do painel e da área do cliente).
 */
function lk_feedback_script() {
	if ( ! lk_feedback_allowed() || ! in_array( get_query_var( 'lk_route' ), array( 'panel', 'client' ), true ) ) {
		return;
	}
	echo '<script>window.LK_FB = ' . wp_json_encode(
		array(
			'team'  => lk_is_team(),
			'list'  => lk_is_team() ? lk_panel_url( 'apontamentos' ) : '',
			'focus' => isset( $_GET['apontamento'] ) ? absint( $_GET['apontamento'] ) : 0, // phpcs:ignore WordPress.Security.NonceVerification
		)
	) . ';</script>';
	echo '<script src="' . esc_url( LK_URL . 'assets/feedback.js?ver=' . LK_VERSION ) . '"></script>';
}

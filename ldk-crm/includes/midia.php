<?php
/**
 * Mídia dos posts: limites do Instagram, prévia (play) de arte/vídeo guardados no Drive e apontamentos.
 *
 * Limites (API de publicação do Instagram): imagem JPEG até 8 MB; vídeo/Reels MP4 ou MOV até 300 MB, de 3 s a 15 min;
 * vídeo de Story até 100 MB, de 3 s a 60 s; carrossel até 10 itens. Os números ficam em lk_media_limits() (filtro lk_media_limits).
 *
 * Prévia: o navegador toca o arquivo por /wp-json/lk/v1/media/<post>/<n>?t=<token do post>; o servidor busca no Drive com a
 * conta do sistema (com Range, para o vídeo poder avançar/voltar). O token é o mesmo do link de aprovação do post.
 *
 * Apontamentos: notas sobre arte, legenda ou um instante do vídeo, com "para quem" é. A equipe pode apontar só para dentro
 * (cliente não vê) ou mostrar ao cliente; o cliente aponta pelo link de aprovação.
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function lk_media_limits() {
	return apply_filters(
		'lk_media_limits',
		array(
			'image_mb'     => 8,
			'image_max_w'  => 1440,
			'video_mb'     => 300,
			'video_min_s'  => 3,
			'video_max_s'  => 900,
			'story_mb'     => 100,
			'story_min_s'  => 3,
			'story_max_s'  => 60,
			'carousel_max' => 20,
			'video_ext'    => array( 'mp4', 'mov' ),
		)
	);
}

/* -----------------------------------------------------------------------
 * Prévia: tocar/mostrar a mídia do Drive
 * -------------------------------------------------------------------- */

add_action(
	'rest_api_init',
	function () {
		register_rest_route( 'lk/v1', '/media/(?P<post>\d+)/(?P<i>\d+)', array( 'methods' => 'GET', 'callback' => 'lk_api_media_stream', 'permission_callback' => '__return_true' ) );
	}
);

/** Endereço para o <video>/<img> de um item do post ('' se não houver como mostrar). */
function lk_media_src( $p, $i, $m ) {
	if ( 0 === strpos( (string) $m['id'], 'wp-' ) ) {
		return $m['link'];
	}
	if ( ! function_exists( 'curl_init' ) || empty( $p->approval_token ) ) {
		return '';
	}
	return add_query_arg( 't', $p->approval_token, rest_url( 'lk/v1/media/' . (int) $p->id . '/' . (int) $i ) );
}

function lk_api_media_stream( WP_REST_Request $r ) {
	$p = lk_get( 'posts', (int) $r['post'] );
	$t = sanitize_text_field( (string) $r->get_param( 't' ) );
	if ( ! $p || empty( $p->approval_token ) || ! hash_equals( (string) $p->approval_token, $t ) ) {
		return new WP_Error( 'lk', 'Não encontrado.', array( 'status' => 404 ) );
	}
	$media = lk_post_media( $p );
	$m     = $media[ (int) $r['i'] ] ?? null;
	if ( ! $m ) {
		return new WP_Error( 'lk', 'Não encontrado.', array( 'status' => 404 ) );
	}
	if ( 0 === strpos( (string) $m['id'], 'wp-' ) ) {
		wp_redirect( $m['link'] ); // phpcs:ignore WordPress.Security.SafeRedirect -- link do próprio WordPress.
		exit;
	}
	$tok = lk_google_token();
	if ( is_wp_error( $tok ) || ! function_exists( 'curl_init' ) ) {
		return new WP_Error( 'lk', 'Drive indisponível.', array( 'status' => 502 ) );
	}
	$head = array( 'Authorization: Bearer ' . $tok );
	$rng  = isset( $_SERVER['HTTP_RANGE'] ) ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_RANGE'] ) ) : '';
	if ( $rng && preg_match( '/^bytes=\d*-\d*$/', $rng ) ) {
		$head[] = 'Range: ' . $rng;
	}
	while ( ob_get_level() ) {
		ob_end_clean();
	}
	$status  = 200;
	$hdrs    = array();
	$started = false;
	$ctype   = preg_match( '/\.(mov)$/i', (string) $m['name'] ) ? 'video/quicktime' : ( 'video' === $m['type'] ? 'video/mp4' : '' );
	$ch      = curl_init( 'https://www.googleapis.com/drive/v3/files/' . rawurlencode( $m['id'] ) . '?alt=media' ); // phpcs:ignore WordPress.WP.AlternativeFunctions
	curl_setopt_array( // phpcs:ignore WordPress.WP.AlternativeFunctions
		$ch,
		array(
			CURLOPT_HTTPHEADER     => $head,
			CURLOPT_FOLLOWLOCATION => true,
			CURLOPT_TIMEOUT        => 0,
			CURLOPT_HEADERFUNCTION => function ( $c, $line ) use ( &$status, &$hdrs ) {
				if ( 0 === strpos( $line, 'HTTP/' ) ) {
					$hdrs = array(); // novo bloco (redirecionamento)
					$parts = explode( ' ', $line );
					$status = (int) ( $parts[1] ?? 200 );
				} elseif ( false !== strpos( $line, ':' ) ) {
					list( $k, $v ) = array_map( 'trim', explode( ':', $line, 2 ) );
					$hdrs[ strtolower( $k ) ] = $v;
				}
				return strlen( $line );
			},
			CURLOPT_WRITEFUNCTION  => function ( $c, $data ) use ( &$started, &$status, &$hdrs, $ctype ) {
				if ( ! $started ) {
					$started = true;
					http_response_code( $status );
					foreach ( array( 'content-length', 'content-range', 'accept-ranges', 'etag' ) as $k ) {
						if ( isset( $hdrs[ $k ] ) ) {
							header( ucwords( $k, '-' ) . ': ' . $hdrs[ $k ] );
						}
					}
					$ct = $hdrs['content-type'] ?? '';
					header( 'Content-Type: ' . ( $ctype && 0 !== strpos( $ct, 'video/' ) && 0 !== strpos( $ct, 'image/' ) ? $ctype : ( $ct ?: 'application/octet-stream' ) ) );
					header( 'Cache-Control: private, max-age=3600' );
					header( 'X-Robots-Tag: noindex' );
				}
				echo $data; // phpcs:ignore WordPress.Security.EscapeOutput -- bytes do arquivo.
				flush();
				return strlen( $data );
			},
		)
	);
	curl_exec( $ch ); // phpcs:ignore WordPress.WP.AlternativeFunctions
	curl_close( $ch ); // phpcs:ignore WordPress.WP.AlternativeFunctions
	exit;
}

/* -----------------------------------------------------------------------
 * Validação no servidor (a do navegador pode ser contornada)
 * -------------------------------------------------------------------- */

/** Mensagem de erro se a lista de mídia do post passa dos limites; '' se está ok. */
function lk_media_check( $p, $media ) {
	$lim   = lk_media_limits();
	$story = 'story' === $p->format;
	if ( 'carrossel' === $p->format && count( $media ) > (int) $lim['carousel_max'] ) {
		return 'Carrossel aceita no máximo ' . (int) $lim['carousel_max'] . ' itens (o Instagram recusa mais que isso).';
	}
	foreach ( $media as $m ) {
		$mb = ( (int) ( $m['size'] ?? 0 ) ) / 1048576;
		if ( 'video' === $m['type'] && $mb > (int) $lim[ $story ? 'story_mb' : 'video_mb' ] ) {
			return '"' . $m['name'] . '" tem ' . round( $mb ) . ' MB. O limite para ' . ( $story ? 'Story' : 'vídeo/Reels' ) . ' é ' . (int) $lim[ $story ? 'story_mb' : 'video_mb' ] . ' MB.';
		}
		if ( 'image' === $m['type'] && $mb > (int) $lim['image_mb'] ) {
			return '"' . $m['name'] . '" tem ' . round( $mb, 1 ) . ' MB. O limite de imagem é ' . (int) $lim['image_mb'] . ' MB.';
		}
	}
	return '';
}


/* -----------------------------------------------------------------------
 * Quem pode fazer apontamento (o admin decide, pessoa por pessoa)
 * -------------------------------------------------------------------- */

function lk_note_perm() {
	return array_merge( array( 'users' => array(), 'clients' => array() ), (array) get_option( 'lk_note_perm', array() ) );
}

/** Usuário logado (equipe ou cliente) pode apontar? Padrão: pode. Admin sempre pode. */
function lk_note_can( $user_id = 0 ) {
	$user_id = $user_id ? $user_id : get_current_user_id();
	if ( ! $user_id ) {
		return false;
	}
	if ( lk_is_admin( $user_id ) ) {
		return true;
	}
	$perm = lk_note_perm();
	if ( lk_is_team( $user_id ) ) {
		return '0' !== (string) ( $perm['users'][ $user_id ] ?? '1' );
	}
	$c = lk_client_by_user( $user_id );
	return $c ? lk_note_can_client( $c->id ) : false;
}

function lk_note_can_client( $client_id ) {
	return '0' !== (string) ( lk_note_perm()['clients'][ (int) $client_id ] ?? '1' );
}

function lk_do_note_perm_save() {
	lk_require( 'admin' );
	$on_u = array_map( 'absint', (array) ( $_POST['users'] ?? array() ) ); // phpcs:ignore WordPress.Security.NonceVerification
	$on_c = array_map( 'absint', (array) ( $_POST['clients'] ?? array() ) ); // phpcs:ignore WordPress.Security.NonceVerification
	$perm = array( 'users' => array(), 'clients' => array() );
	foreach ( lk_team_users() as $u ) {
		$perm['users'][ $u->ID ] = in_array( $u->ID, $on_u, true ) ? '1' : '0';
	}
	foreach ( lk_clients() as $c ) {
		$perm['clients'][ $c->id ] = in_array( (int) $c->id, $on_c, true ) ? '1' : '0';
	}
	update_option( 'lk_note_perm', $perm, false );
	lk_back( 'Permissões de apontamento salvas.' );
}

/* -----------------------------------------------------------------------
 * Apontamentos
 * -------------------------------------------------------------------- */

function lk_note_abouts() {
	return array( 'arte' => 'Arte', 'legenda' => 'Legenda', 'video' => 'Vídeo' );
}

function lk_note_time( $sec ) {
	$sec = max( 0, (int) $sec );
	return floor( $sec / 60 ) . ':' . str_pad( (string) ( $sec % 60 ), 2, '0', STR_PAD_LEFT );
}

/** "1:23" ou "83" → segundos; vazio → -1. */
function lk_note_parse_time( $t ) {
	$t = trim( (string) $t );
	if ( '' === $t ) {
		return -1;
	}
	if ( preg_match( '/^(\d+):(\d{1,2})$/', $t, $m ) ) {
		return (int) $m[1] * 60 + (int) $m[2];
	}
	return ctype_digit( $t ) ? (int) $t : -1;
}

function lk_post_notes( $p, $for_client = false ) {
	return lk_rows( 'post_comments', "post_id = %d AND target = 'apontamento'" . ( $for_client ? ' AND internal = 0' : '' ), array( $p->id ), 'resolved, id' );
}

/** Quem normalmente cuida de cada assunto. */
function lk_note_default_assignee( $p, $about ) {
	$id = 'legenda' === $about ? $p->social_id : $p->designer_id;
	return (int) ( $id ? $id : $p->atendimento_id );
}

function lk_note_insert( $p, $body, $about, $media_i, $at, $assignee, $internal, $from_client, $attach = null ) {
	$about = isset( lk_note_abouts()[ $about ] ) ? $about : 'arte';
	return lk_insert(
		'post_comments',
		array(
			'attach_url'  => $attach ? $attach['url'] : null,
			'attach_kind' => $attach ? $attach['kind'] : '',
			'post_id'     => $p->id,
			'user_id'     => get_current_user_id(),
			'from_client' => $from_client ? 1 : 0,
			'internal'    => $internal ? 1 : 0,
			'target'      => 'apontamento',
			'body'        => $body,
			'about'       => $about,
			'media_i'     => (int) $media_i,
			'at_sec'      => (int) $at,
			'assignee'    => (int) $assignee,
			'resolved'    => 0,
		)
	);
}

/** Equipe: botão "Fazer apontamento" na tela do post. */
function lk_do_post_note() {
	lk_require( 'conteudo' );
	if ( ! lk_note_can() ) {
		wp_die( 'O administrador não liberou apontamentos para o seu usuário.' );
	}
	$p      = lk_get( 'posts', lk_in( 'id', 'int' ) );
	$body   = lk_in( 'body', 'textarea' );
	$attach = lk_collect_attach();
	if ( is_wp_error( $attach ) ) {
		lk_back( $attach->get_error_message(), 'erro' );
	}
	if ( ! $p || ( '' === $body && ! $attach ) ) {
		lk_back( 'Escreva o apontamento (ou grave um áudio).', 'erro' );
	}
	if ( '' === $body ) {
		$body = 'audio' === $attach['kind'] ? '🎙️ Áudio' : '📎 Referência anexada';
	}
	$about  = sanitize_key( lk_in( 'about' ) );
	$who    = lk_in( 'assignee', 'int' ) ? lk_in( 'assignee', 'int' ) : lk_note_default_assignee( $p, $about );
	$mi     = '' === (string) lk_in( 'media_i' ) ? -1 : lk_in( 'media_i', 'int' );
	$at     = lk_note_parse_time( lk_in( 'at' ) );
	$show   = lk_in( 'visible', 'bool' );
	lk_note_insert( $p, $body, $about, $mi, $at, $who, ! $show, false, $attach );
	$me = wp_get_current_user()->display_name;
	lk_notify( $who, '📌 Apontamento de ' . $me . ' em "' . $p->title . '": ' . wp_trim_words( $body, 12 ), lk_panel_url( 'post', $p->id ) . '#apontamentos' );
	lk_back( 'Apontamento enviado' . ( get_userdata( $who ) ? ' para ' . get_userdata( $who )->display_name : '' ) . ( $show ? ' (o cliente também vê).' : ' (só a equipe vê).' ) );
}

function lk_do_post_note_resolve() {
	lk_require( 'conteudo' );
	$n = lk_get( 'post_comments', lk_in( 'id', 'int' ) );
	if ( $n && 'apontamento' === $n->target ) {
		lk_update( 'post_comments', $n->id, array( 'resolved' => $n->resolved ? 0 : 1 ) );
	}
	lk_back( $n && ! $n->resolved ? 'Apontamento resolvido.' : 'Apontamento reaberto.' );
}

/** Cliente (página de aprovação): devolve a mensagem para mostrar. */
function lk_note_from_client( $p ) {
	if ( ! lk_note_can_client( $p->client_id ) ) {
		return 'Apontamentos não estão liberados para este acesso.';
	}
	$body   = lk_in( 'comentario', 'textarea' );
	$attach = lk_collect_attach();
	if ( is_wp_error( $attach ) ) {
		return $attach->get_error_message();
	}
	if ( '' === $body && ! $attach ) {
		return 'Escreva (ou grave um áudio) com o que você quer apontar.';
	}
	if ( '' === $body ) {
		$body = 'audio' === $attach['kind'] ? '🎙️ Áudio' : '📎 Referência anexada';
	}
	$client = lk_get( 'clients', $p->client_id );
	$about  = sanitize_key( lk_in( 'alvo' ) );
	$mi     = '' === (string) lk_in( 'media_i' ) ? -1 : lk_in( 'media_i', 'int' );
	lk_note_insert( $p, $body, $about, $mi, lk_note_parse_time( lk_in( 'at' ) ), $p->atendimento_id, false, true, $attach );
	lk_flow_alter_tasks( $p, in_array( $about, array( 'legenda', 'video' ), true ) ? $about : 'arte', in_array( $body, array( '🎙️ Áudio', '📎 Referência anexada' ), true ) ? '' : $body, $attach );
	lk_notify( $p->atendimento_id, '📌 Apontamento de ' . lk_client_label( $client ) . ' em "' . $p->title . '": ' . wp_trim_words( $body, 12 ), lk_panel_url( 'post', $p->id ) . '#apontamentos' );
	return 'Apontamento enviado! A equipe vai ver.';
}

/** HTML de uma lista de apontamentos (equipe ou cliente). */
function lk_notes_html( $p, $notes, $team ) {
	if ( ! $notes ) {
		return '<p class="muted small">Nenhum apontamento ainda.</p>';
	}
	$h = '<ul class="notes">';
	foreach ( $notes as $n ) {
		$u    = $n->user_id ? get_userdata( $n->user_id ) : null;
		$for  = $n->assignee ? get_userdata( $n->assignee ) : null;
		$who  = $n->from_client ? ( $team ? 'Cliente' : 'Você' ) : ( $u ? $u->display_name : 'Equipe' );
		$time = (int) $n->at_sec >= 0 ? '<button type="button" class="note-time" data-seek="' . (int) $n->at_sec . '" data-mi="' . (int) $n->media_i . '">▶ ' . esc_html( lk_note_time( $n->at_sec ) ) . '</button>' : '';
		$h   .= '<li class="note' . ( $n->resolved ? ' is-done' : '' ) . '"><div class="note-top"><strong>' . esc_html( $who ) . '</strong>';
		$h   .= '<em class="badge">' . esc_html( lk_note_abouts()[ $n->about ] ?? 'Arte' ) . '</em>';
		if ( $team && $for ) {
			$h .= '<em class="badge badge--warn">para ' . esc_html( $for->display_name ) . '</em>';
		}
		if ( $team ) {
			$h .= $n->internal ? '<em class="badge">só equipe</em>' : '<em class="badge badge--ok">cliente vê</em>';
		}
		$h .= $time . ( $n->resolved ? '<em class="badge badge--ok">resolvido</em>' : '' ) . '</div><p>' . nl2br( esc_html( $n->body ) ) . '</p>' . lk_attach_html( $n ) . '<small class="muted">' . esc_html( lk_ago( $n->created_at ) ) . '</small>';
		if ( $team ) {
			ob_start();
			lk_action_button( 'post_note_resolve', array( 'id' => $n->id ), $n->resolved ? 'Reabrir' : 'Resolver', 'btn btn--link btn--sm' );
			$h .= ob_get_clean();
		}
		$h .= '</li>';
	}
	return $h . '</ul>';
}

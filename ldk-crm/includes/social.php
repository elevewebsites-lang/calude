<?php
/**
 * Redes sociais: conexão, publicação automática e números (no lugar do mLabs).
 *
 * Instagram: "Instagram API com login do Instagram" (conta Comercial ou Criador; não precisa de Página do Facebook).
 *   O app da LDK fica em modo desenvolvimento e cada Instagram de cliente entra como TESTADOR do app
 *   (aceitam o convite logando na conta) → publica e lê insights sem App Review.
 * Facebook: Página do cliente (login do Facebook, token da página).
 * LinkedIn: por enquanto lembrete manual na hora de postar (a API de páginas exige aprovação do LinkedIn).
 *
 * Mídia: as artes ficam no Google Drive. Na hora de publicar, o servidor copia para uma pasta temporária
 * pública (uploads/lk-tmp), passa a URL para a Meta e apaga depois.
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'LK_GRAPH', 'v21.0' );

function lk_social_redirect( $net ) {
	return admin_url( 'admin-post.php?action=lk_social_cb&net=' . $net );
}

function lk_social_account( $client_id, $net ) {
	$rows = lk_rows( 'social_accounts', 'client_id = %d AND network = %s', array( $client_id, $net ), 'id DESC LIMIT 1' );
	return $rows ? $rows[0] : null;
}

function lk_social_accounts( $client_id ) {
	$out = array();
	foreach ( lk_rows( 'social_accounts', 'client_id = %d', array( $client_id ) ) as $a ) {
		$out[ $a->network ] = $a;
	}
	return $out;
}

/* -----------------------------------------------------------------------
 * Conectar (OAuth)
 * -------------------------------------------------------------------- */

/**
 * Endereço do login da rede (Instagram, Facebook, LinkedIn, Google). Serve ao painel e ao link do cliente.
 */
function lk_social_auth_url( $net, $st ) {
	if ( in_array( $net, lk_social_more_nets(), true ) ) {
		return lk_social_more_auth_url( $net, $st );
	}
	if ( 'instagram' === $net ) {
		if ( ! lk_setting( 'ig_app_id' ) ) {
			return new WP_Error( 'lk', 'Preencha o Instagram App ID e o Secret em Configurações → Instagram, Facebook e Meta Ads.' );
		}
		return add_query_arg(
			array(
				'client_id'     => lk_setting( 'ig_app_id' ),
				'redirect_uri'  => rawurlencode( lk_social_redirect( 'instagram' ) ),
				'response_type' => 'code',
				'scope'         => 'instagram_business_basic,instagram_business_content_publish,instagram_business_manage_insights',
				'state'         => $st,
				'force_reauth'  => 'true',
			),
			'https://www.instagram.com/oauth/authorize'
		);
	}
	if ( ! lk_setting( 'meta_app_id' ) ) {
		return new WP_Error( 'lk', 'Preencha o Meta App ID e o Secret em Configurações → Instagram, Facebook e Meta Ads.' );
	}
	return add_query_arg(
		array(
			'client_id'    => lk_setting( 'meta_app_id' ),
			'redirect_uri' => rawurlencode( lk_social_redirect( 'facebook' ) ),
			'scope'        => 'pages_show_list,pages_manage_posts,pages_read_engagement,read_insights',
			'state'        => $st,
			'auth_type'    => 'rerequest',
		),
		'https://www.facebook.com/' . LK_GRAPH . '/dialog/oauth'
	);
}

add_action( 'admin_post_lk_social_go', 'lk_social_go' );
function lk_social_go() {
	if ( ! lk_can( 'redes' ) ) {
		wp_die( 'Sem permissão.' );
	}
	check_admin_referer( 'lk_social_go' );
	$net = sanitize_key( $_POST['net'] ?? '' ); // phpcs:ignore WordPress.Security.NonceVerification
	$cid = absint( $_POST['client_id'] ?? 0 ); // phpcs:ignore WordPress.Security.NonceVerification
	$st  = wp_generate_password( 20, false );
	set_transient( 'lk_soc_' . $st, array( 'client' => $cid, 'user' => get_current_user_id(), 'net' => $net ), 900 );
	$url = lk_social_auth_url( $net, $st );
	if ( is_wp_error( $url ) ) {
		lk_flash( $url->get_error_message(), 'erro' );
		wp_safe_redirect( lk_panel_url( 'cliente', $cid ) . '#redes' );
		exit;
	}
	wp_redirect( $url ); // phpcs:ignore WordPress.Security.SafeRedirect -- login da Meta.
	exit;
}

add_action( 'admin_post_lk_social_cb', 'lk_social_cb' );
add_action( 'admin_post_nopriv_lk_social_cb', 'lk_social_cb' ); // cliente conectando pelo link (sem login no CRM)
function lk_social_cb() {
	// phpcs:disable WordPress.Security.NonceVerification.Recommended -- conferido pelo "state".
	$net  = sanitize_key( $_GET['net'] ?? '' );
	$code = sanitize_text_field( wp_unslash( $_GET['code'] ?? '' ) );
	$st   = get_transient( 'lk_soc_' . sanitize_text_field( wp_unslash( $_GET['state'] ?? '' ) ) );
	// phpcs:enable
	$pub = $st && ! empty( $st['public'] );
	if ( $pub ) {
		// Fluxo do link do cliente: o "state" sorteado já prova a origem; só vale uma vez.
		delete_transient( 'lk_soc_' . sanitize_text_field( wp_unslash( $_GET['state'] ?? '' ) ) ); // phpcs:ignore WordPress.Security.NonceVerification
		if ( ! $code ) {
			lk_connect_done( (int) $st['client'], 'Conexão cancelada. Você pode tentar de novo.', 'erro' );
		}
	} elseif ( ! $st || (int) $st['user'] !== get_current_user_id() || ! $code ) {
		lk_flash( 'Conexão cancelada ou expirada. Tente de novo.', 'erro' );
		wp_safe_redirect( lk_panel_url( 'clientes' ) );
		exit;
	}
	$cid = (int) $st['client'];
	if ( 'google' === $net || 'linkedin' === $net ) {
		$res = lk_social_more_connect( $st['net'] ?? $net, $cid, $code );
	} else {
		$res = 'instagram' === $net ? lk_ig_connect( $cid, $code ) : lk_fb_connect( $cid, $code );
	}
	if ( $pub ) {
		if ( ! is_wp_error( $res ) && ( get_transient( 'lk_fbpages_' . $cid ) || lk_social_pick_pending( $cid ) ) ) {
			$res = 'Quase lá: escolha abaixo qual é a sua conta.';
		}
		lk_connect_done( $cid, is_wp_error( $res ) ? $res->get_error_message() : $res, is_wp_error( $res ) ? 'erro' : 'ok' );
	}
	lk_flash( is_wp_error( $res ) ? $res->get_error_message() : $res, is_wp_error( $res ) ? 'erro' : 'ok' );
	wp_safe_redirect( lk_panel_url( 'cliente', $cid ) . '#redes' );
	exit;
}

function lk_http_json( $method, $url, $body = null, $headers = array() ) {
	$args = array( 'method' => $method, 'timeout' => 60, 'headers' => $headers );
	if ( null !== $body ) {
		$args['body'] = $body;
	}
	$res = wp_remote_request( $url, $args );
	if ( is_wp_error( $res ) ) {
		return $res;
	}
	$json = json_decode( wp_remote_retrieve_body( $res ), true );
	if ( wp_remote_retrieve_response_code( $res ) >= 300 || isset( $json['error'] ) ) {
		$msg = $json['error']['message'] ?? ( $json['error_message'] ?? ( is_string( $json['error'] ?? null ) ? $json['error'] : 'erro ' . wp_remote_retrieve_response_code( $res ) ) );
		return new WP_Error( 'lk_social', $msg );
	}
	return is_array( $json ) ? $json : array();
}

function lk_ig_connect( $cid, $code ) {
	$short = lk_http_json(
		'POST',
		'https://api.instagram.com/oauth/access_token',
		array(
			'client_id'     => lk_setting( 'ig_app_id' ),
			'client_secret' => lk_decrypt( lk_setting( 'ig_app_secret' ) ),
			'grant_type'    => 'authorization_code',
			'redirect_uri'  => lk_social_redirect( 'instagram' ),
			'code'          => $code,
		)
	);
	if ( is_wp_error( $short ) ) {
		return new WP_Error( 'lk', 'Instagram: ' . $short->get_error_message() . ' A conta foi adicionada como testadora do app?' );
	}
	$long = lk_http_json( 'GET', add_query_arg( array( 'grant_type' => 'ig_exchange_token', 'client_secret' => lk_decrypt( lk_setting( 'ig_app_secret' ) ), 'access_token' => $short['access_token'] ), 'https://graph.instagram.com/access_token' ) );
	if ( is_wp_error( $long ) ) {
		return $long;
	}
	$me = lk_http_json( 'GET', 'https://graph.instagram.com/' . LK_GRAPH . '/me?fields=user_id,username,name,profile_picture_url,account_type,followers_count&access_token=' . rawurlencode( $long['access_token'] ) );
	if ( is_wp_error( $me ) ) {
		return $me;
	}
	lk_social_store( $cid, 'instagram', $me['user_id'] ?? $me['id'], $me['username'] ?? '', $me['name'] ?? '', $me['profile_picture_url'] ?? '', $long['access_token'], time() + (int) ( $long['expires_in'] ?? 5184000 ), array( 'type' => $me['account_type'] ?? '', 'followers' => (int) ( $me['followers_count'] ?? 0 ) ) );
	return 'Instagram @' . ( $me['username'] ?? '' ) . ' conectado.';
}

function lk_fb_connect( $cid, $code ) {
	$tok = lk_http_json( 'GET', add_query_arg( array( 'client_id' => lk_setting( 'meta_app_id' ), 'client_secret' => lk_decrypt( lk_setting( 'meta_app_secret' ) ), 'redirect_uri' => lk_social_redirect( 'facebook' ), 'code' => $code ), 'https://graph.facebook.com/' . LK_GRAPH . '/oauth/access_token' ) );
	if ( is_wp_error( $tok ) ) {
		return $tok;
	}
	$long  = lk_http_json( 'GET', add_query_arg( array( 'grant_type' => 'fb_exchange_token', 'client_id' => lk_setting( 'meta_app_id' ), 'client_secret' => lk_decrypt( lk_setting( 'meta_app_secret' ) ), 'fb_exchange_token' => $tok['access_token'] ), 'https://graph.facebook.com/' . LK_GRAPH . '/oauth/access_token' ) );
	$user  = is_wp_error( $long ) ? $tok['access_token'] : $long['access_token'];
	$pages = lk_http_json( 'GET', 'https://graph.facebook.com/' . LK_GRAPH . '/me/accounts?fields=id,name,access_token,picture{url}&limit=100&access_token=' . rawurlencode( $user ) );
	if ( is_wp_error( $pages ) || empty( $pages['data'] ) ) {
		return new WP_Error( 'lk', 'Nenhuma Página do Facebook encontrada nessa conta.' );
	}
	// Uma página: conecta direto. Várias: guarda e pede para escolher.
	if ( 1 === count( $pages['data'] ) ) {
		$pg = $pages['data'][0];
		lk_social_store( $cid, 'facebook', $pg['id'], '', $pg['name'], $pg['picture']['data']['url'] ?? '', $pg['access_token'], 0 );
		return 'Página "' . $pg['name'] . '" conectada.';
	}
	set_transient( 'lk_fbpages_' . $cid, lk_encrypt( wp_json_encode( $pages['data'] ) ), 900 );
	return 'Escolha a Página do cliente na lista (em Redes sociais, na ficha do cliente).';
}

function lk_do_fb_pick_page() {
	lk_require( 'redes' );
	$cid   = lk_in( 'client_id', 'int' );
	$pages = json_decode( (string) lk_decrypt( get_transient( 'lk_fbpages_' . $cid ) ), true );
	foreach ( (array) $pages as $pg ) {
		if ( $pg['id'] === lk_in( 'page' ) ) {
			lk_social_store( $cid, 'facebook', $pg['id'], '', $pg['name'], $pg['picture']['data']['url'] ?? '', $pg['access_token'], 0 );
			delete_transient( 'lk_fbpages_' . $cid );
			lk_back( 'Página "' . $pg['name'] . '" conectada.' );
		}
	}
	lk_back( 'Página não encontrada. Conecte de novo.', 'erro' );
}

function lk_social_store( $cid, $net, $account, $username, $name, $avatar, $token, $expires, $extra = array() ) {
	$old  = lk_social_account( $cid, $net );
	$data = array(
		'client_id'  => $cid,
		'network'    => $net,
		'account_id' => (string) $account,
		'username'   => (string) $username,
		'name'       => (string) $name,
		'avatar'     => esc_url_raw( $avatar ),
		'token'      => lk_encrypt( $token ),
		'expires_at' => $expires ? gmdate( 'Y-m-d H:i:s', $expires ) : null,
		'extra'      => wp_json_encode( $extra ),
		'status'     => 'ok',
		'error'      => '',
	);
	if ( $old ) {
		lk_update( 'social_accounts', $old->id, $data );
	} else {
		lk_insert( 'social_accounts', $data );
	}
}

function lk_do_social_disconnect() {
	lk_require( 'redes' );
	lk_delete( 'social_accounts', lk_in( 'id', 'int' ) );
	lk_back( 'Rede desconectada.' );
}

/**
 * Renova os tokens do Instagram antes de vencer (60 dias).
 */
add_action( 'lk_daily', 'lk_social_refresh' );
function lk_social_refresh() {
	foreach ( lk_rows( 'social_accounts', "network = 'instagram' AND expires_at IS NOT NULL AND expires_at < %s", array( gmdate( 'Y-m-d H:i:s', time() + 15 * DAY_IN_SECONDS ) ) ) as $a ) {
		$r = lk_http_json( 'GET', 'https://graph.instagram.com/refresh_access_token?grant_type=ig_refresh_token&access_token=' . rawurlencode( lk_decrypt( $a->token ) ) );
		if ( is_wp_error( $r ) ) {
			lk_update( 'social_accounts', $a->id, array( 'status' => 'erro', 'error' => 'Reconecte: ' . $r->get_error_message() ) );
			continue;
		}
		lk_update( 'social_accounts', $a->id, array( 'token' => lk_encrypt( $r['access_token'] ), 'expires_at' => gmdate( 'Y-m-d H:i:s', time() + (int) $r['expires_in'] ), 'status' => 'ok', 'error' => '' ) );
	}
}

/**
 * Atualiza foto e seguidores dos Instagrams conectados (a URL da foto do Instagram expira).
 */
add_action( 'lk_daily', 'lk_ig_profile_refresh' );
function lk_ig_profile_refresh() {
	foreach ( lk_rows( 'social_accounts', "network = 'instagram' AND status = 'ok'" ) as $a ) {
		$me = lk_http_json( 'GET', 'https://graph.instagram.com/' . LK_GRAPH . '/me?fields=username,name,profile_picture_url,followers_count&access_token=' . rawurlencode( lk_decrypt( $a->token ) ) );
		if ( is_wp_error( $me ) ) {
			continue;
		}
		$x              = json_decode( (string) $a->extra, true );
		$x              = is_array( $x ) ? $x : array();
		$x['followers'] = (int) ( $me['followers_count'] ?? 0 );
		lk_update( 'social_accounts', $a->id, array( 'username' => (string) ( $me['username'] ?? $a->username ), 'avatar' => esc_url_raw( $me['profile_picture_url'] ?? $a->avatar ), 'extra' => wp_json_encode( $x ) ) );
	}
}

/* -----------------------------------------------------------------------
 * Mídia temporária pública (a Meta baixa pela URL)
 * -------------------------------------------------------------------- */

function lk_media_public_url( $m ) {
	if ( 0 === strpos( (string) $m['id'], 'wp-' ) ) {
		return $m['link'];
	}
	$up   = wp_upload_dir();
	$dir  = trailingslashit( $up['basedir'] ) . 'lk-tmp';
	wp_mkdir_p( $dir );
	if ( ! file_exists( $dir . '/index.html' ) ) {
		file_put_contents( $dir . '/index.html', '' ); // phpcs:ignore WordPress.WP.AlternativeFunctions
	}
	$ext  = strtolower( pathinfo( (string) $m['name'], PATHINFO_EXTENSION ) ) ?: 'jpg';
	$file = wp_generate_password( 24, false ) . '.' . $ext;
	$tok  = lk_google_token();
	if ( is_wp_error( $tok ) ) {
		return $tok;
	}
	$res = wp_remote_get( 'https://www.googleapis.com/drive/v3/files/' . rawurlencode( $m['id'] ) . '?alt=media', array( 'timeout' => 300, 'stream' => true, 'filename' => $dir . '/' . $file, 'headers' => array( 'Authorization' => 'Bearer ' . $tok ) ) );
	if ( is_wp_error( $res ) || wp_remote_retrieve_response_code( $res ) >= 300 ) {
		return new WP_Error( 'lk', 'Não consegui baixar o arquivo do Drive.' );
	}
	return trailingslashit( $up['baseurl'] ) . 'lk-tmp/' . $file;
}

add_action( 'lk_daily', 'lk_media_tmp_clean' );
function lk_media_tmp_clean() {
	$dir = trailingslashit( wp_upload_dir()['basedir'] ) . 'lk-tmp';
	foreach ( (array) glob( $dir . '/*.*' ) as $f ) {
		if ( basename( $f ) !== 'index.html' && filemtime( $f ) < time() - DAY_IN_SECONDS ) {
			wp_delete_file( $f );
		}
	}
}

/* -----------------------------------------------------------------------
 * Publicar
 * -------------------------------------------------------------------- */

function lk_ig_publish( $acc, $p, $urls ) {
	$tok  = lk_decrypt( $acc->token );
	$base = 'https://graph.instagram.com/' . LK_GRAPH . '/' . $acc->account_id;
	$cap  = (string) $p->caption;
	$isv  = function ( $u ) {
		return (bool) preg_match( '/\.(mp4|mov|m4v|webm)(\?|$)/i', $u );
	};
	$make = function ( $args ) use ( $base, $tok ) {
		return lk_http_json( 'POST', $base . '/media', $args + array( 'access_token' => $tok ) );
	};
	if ( 'story' === $p->format ) {
		$c = $make( array( 'media_type' => 'STORIES', $isv( $urls[0] ) ? 'video_url' : 'image_url' => $urls[0] ) );
	} elseif ( count( $urls ) > 1 || 'carrossel' === $p->format ) {
		$kids = array();
		foreach ( array_slice( $urls, 0, 10 ) as $u ) {
			$k = $make( $isv( $u ) ? array( 'media_type' => 'VIDEO', 'video_url' => $u, 'is_carousel_item' => 'true' ) : array( 'image_url' => $u, 'is_carousel_item' => 'true' ) );
			if ( is_wp_error( $k ) ) {
				return $k;
			}
			$kids[] = $k['id'];
		}
		foreach ( $kids as $kid ) {
			lk_ig_wait( $kid, $tok );
		}
		$c = $make( array( 'media_type' => 'CAROUSEL', 'children' => implode( ',', $kids ), 'caption' => $cap ) );
	} elseif ( $isv( $urls[0] ) ) {
		$args = array( 'media_type' => 'REELS', 'video_url' => $urls[0], 'caption' => $cap, 'share_to_feed' => 'true' );
		if ( ! empty( $p->cover_url ) ) {
			$args['cover_url'] = $p->cover_url;
		}
		$c = $make( $args );
	} else {
		$c = $make( array( 'image_url' => $urls[0], 'caption' => $cap ) );
	}
	if ( is_wp_error( $c ) ) {
		return $c;
	}
	$ok = lk_ig_wait( $c['id'], $tok );
	if ( is_wp_error( $ok ) ) {
		return $ok;
	}
	$pub = lk_http_json( 'POST', $base . '/media_publish', array( 'creation_id' => $c['id'], 'access_token' => $tok ) );
	if ( is_wp_error( $pub ) ) {
		return $pub;
	}
	$info = lk_http_json( 'GET', 'https://graph.instagram.com/' . LK_GRAPH . '/' . $pub['id'] . '?fields=permalink&access_token=' . rawurlencode( $tok ) );
	return array( 'id' => $pub['id'], 'url' => is_wp_error( $info ) ? '' : ( $info['permalink'] ?? '' ) );
}

/**
 * Vídeo/Reels demoram para processar: espera o container ficar pronto (até ~4 min).
 */
function lk_ig_wait( $container, $tok ) {
	for ( $i = 0; $i < 24; $i++ ) {
		$s = lk_http_json( 'GET', 'https://graph.instagram.com/' . LK_GRAPH . '/' . $container . '?fields=status_code,status&access_token=' . rawurlencode( $tok ) );
		if ( is_wp_error( $s ) ) {
			return $s;
		}
		if ( 'FINISHED' === ( $s['status_code'] ?? 'FINISHED' ) ) {
			return true;
		}
		if ( 'ERROR' === ( $s['status_code'] ?? '' ) ) {
			return new WP_Error( 'lk', 'O Instagram não processou o arquivo: ' . ( $s['status'] ?? '' ) );
		}
		sleep( 10 );
	}
	return new WP_Error( 'lk', 'O Instagram demorou demais para processar o vídeo. Tentaremos de novo.' );
}

function lk_fb_publish( $acc, $p, $urls ) {
	$tok  = lk_decrypt( $acc->token );
	$base = 'https://graph.facebook.com/' . LK_GRAPH . '/' . $acc->account_id;
	$isv  = (bool) preg_match( '/\.(mp4|mov|m4v|webm)(\?|$)/i', $urls[0] );
	if ( $isv && 'feed' !== $p->vkind ) {
		// Reel do Facebook: abre o envio, manda o arquivo por URL e publica.
		$st = lk_http_json( 'POST', $base . '/video_reels', array( 'upload_phase' => 'start', 'access_token' => $tok ) );
		if ( is_wp_error( $st ) ) {
			return $st;
		}
		$up = wp_remote_post( $st['upload_url'], array( 'timeout' => 120, 'headers' => array( 'Authorization' => 'OAuth ' . $tok, 'file_url' => $urls[0] ) ) );
		if ( is_wp_error( $up ) || (int) wp_remote_retrieve_response_code( $up ) >= 300 ) {
			return new WP_Error( 'lk', 'O Facebook não recebeu o vídeo do Reel.' );
		}
		$r = lk_http_json( 'POST', $base . '/video_reels', array( 'upload_phase' => 'finish', 'video_id' => $st['video_id'], 'video_state' => 'PUBLISHED', 'description' => $p->caption, 'access_token' => $tok ) );
		if ( ! is_wp_error( $r ) ) {
			$r = array( 'id' => $st['video_id'] );
		}
	} elseif ( $isv ) {
		$r = lk_http_json( 'POST', $base . '/videos', array( 'file_url' => $urls[0], 'description' => $p->caption, 'access_token' => $tok ) );
	} elseif ( count( $urls ) > 1 ) {
		$att = array();
		foreach ( array_slice( $urls, 0, 10 ) as $u ) {
			$ph = lk_http_json( 'POST', $base . '/photos', array( 'url' => $u, 'published' => 'false', 'access_token' => $tok ) );
			if ( is_wp_error( $ph ) ) {
				return $ph;
			}
			$att[] = wp_json_encode( array( 'media_fbid' => $ph['id'] ) );
		}
		$args = array( 'message' => $p->caption, 'access_token' => $tok );
		foreach ( $att as $i => $a ) {
			$args[ 'attached_media[' . $i . ']' ] = $a;
		}
		$r = lk_http_json( 'POST', $base . '/feed', $args );
	} else {
		$r = lk_http_json( 'POST', $base . '/photos', array( 'url' => $urls[0], 'caption' => $p->caption, 'access_token' => $tok ) );
	}
	if ( is_wp_error( $r ) ) {
		return $r;
	}
	$id = $r['post_id'] ?? $r['id'];
	return array( 'id' => $id, 'url' => 'https://www.facebook.com/' . $id );
}

/**
 * A cada 5 minutos: publica o que está Agendado e chegou a hora.
 */
add_filter(
	'cron_schedules',
	function ( $s ) {
		$s['lk_5min'] = array( 'interval' => 300, 'display' => 'A cada 5 minutos' );
		return $s;
	}
);
add_action(
	'init',
	function () {
		if ( ! wp_next_scheduled( 'lk_publish_tick' ) ) {
			wp_schedule_event( time() + 60, 'lk_5min', 'lk_publish_tick' );
		}
	}
);

add_action( 'lk_publish_tick', 'lk_publish_due' );
function lk_publish_due() {
	if ( get_transient( 'lk_publishing' ) ) {
		return;
	}
	set_transient( 'lk_publishing', 1, 10 * MINUTE_IN_SECONDS );
	update_option( 'lk_last_publish_tick', time(), false );
	foreach ( lk_posts( 'p.stage = %s AND p.scheduled_at IS NOT NULL AND p.scheduled_at <= %s', array( lk_stage_for( 'agendado' ), lk_now() ), 'p.scheduled_at LIMIT 5' ) as $p ) {
		lk_publish_post( $p );
	}
	delete_transient( 'lk_publishing' );
}

function lk_publish_post( $p ) {
	$done  = lk_json( $p->published );
	$errs  = array();
	$media = lk_post_media( $p );
	if ( ! $media ) {
		lk_update( 'posts', $p->id, array( 'publish_error' => 'Sem arte.' ) );
		return;
	}
	// Limites do Instagram (legenda e hashtags): não publica; volta para Revisão com o motivo.
	$block = lk_publish_blockers( $p );
	if ( $block ) {
		$why = implode( ' ', $block );
		lk_update( 'posts', $p->id, array( 'publish_error' => $why, 'stage' => lk_stage_for( 'revisao' ) ) );
		lk_post_log( $p->id, 'Não publicou: ' . $why );
		lk_notify( $p->social_id, '⚠️ "' . $p->title . '" não foi publicado: ' . $why, lk_panel_url( 'post', $p->id ) );
		return;
	}
	$p->caption = lk_post_final_caption( $p ); // legenda + hashtags (só em memória; o banco não muda)
	$urls = null;
	foreach ( lk_post_networks( $p ) as $net ) {
		if ( isset( $done[ $net ] ) ) {
			continue;
		}
		$acc = lk_social_account( $p->client_id, $net );
		// LinkedIn sem Página conectada: continua como lembrete manual.
		if ( 'linkedin' === $net && ! $acc ) {
			$done[ $net ] = array( 'manual' => 1, 'at' => lk_now(), 'url' => '' );
			lk_notify( $p->social_id, '📌 Hora de postar no LinkedIn (manual): ' . $p->title, lk_panel_url( 'post', $p->id ) );
			continue;
		}
		if ( ! $acc || 'ok' !== $acc->status ) {
			$errs[] = lk_networks()[ $net ] . ': conta não conectada.';
			continue;
		}
		if ( null === $urls ) {
			$urls = array();
			foreach ( $media as $m ) {
				$u = lk_media_public_url( $m );
				if ( is_wp_error( $u ) ) {
					$errs[] = $u->get_error_message();
					break 2;
				}
				$urls[] = $u;
			}
		}
		if ( in_array( $net, lk_social_more_nets(), true ) ) {
			$r = lk_publish_more( $net, $acc, $p, $urls, $media );
		} else {
			$r = 'instagram' === $net ? lk_ig_publish( $acc, $p, $urls ) : lk_fb_publish( $acc, $p, $urls );
		}
		if ( is_wp_error( $r ) ) {
			$errs[] = lk_networks()[ $net ] . ': ' . $r->get_error_message();
		} else {
			$done[ $net ] = $r + array( 'at' => lk_now() );
		}
	}
	lk_update( 'posts', $p->id, array( 'published' => wp_json_encode( $done ), 'publish_error' => implode( ' ', $errs ) ) );
	if ( ! $errs && count( $done ) >= count( lk_post_networks( $p ) ) ) {
		lk_post_move( $p, lk_stage_for( 'publicado' ), 'Publicado automaticamente.' );
	} elseif ( $errs ) {
		lk_post_log( $p->id, 'Falha ao publicar: ' . implode( ' ', $errs ) );
		lk_notify( $p->social_id, '⚠️ Falha ao publicar "' . $p->title . '": ' . implode( ' ', $errs ), lk_panel_url( 'post', $p->id ) );
		lk_notify( $p->atendimento_id, '⚠️ Falha ao publicar "' . $p->title . '"', lk_panel_url( 'post', $p->id ) );
		// Não tenta de novo em loop: sai de Agendado até alguém resolver.
		lk_update( 'posts', $p->id, array( 'stage' => lk_stage_for( 'revisao' ) ) );
	}
}

function lk_do_post_publish_now() {
	lk_require( 'conteudo' );
	$p = lk_get( 'posts', lk_in( 'id', 'int' ) );
	if ( $p ) {
		lk_update( 'posts', $p->id, array( 'stage' => lk_stage_for( 'agendado' ), 'scheduled_at' => lk_now() ) );
		lk_publish_post( lk_get( 'posts', $p->id ) );
	}
	$p = lk_get( 'posts', lk_in( 'id', 'int' ) );
	lk_back( $p && ! $p->publish_error ? 'Publicado.' : 'Não publicou: ' . ( $p ? $p->publish_error : '' ), $p && ! $p->publish_error ? 'ok' : 'erro' );
}

function lk_do_post_mark_published() {
	lk_require( 'conteudo' );
	$p = lk_get( 'posts', lk_in( 'id', 'int' ) );
	if ( $p ) {
		$done = lk_json( $p->published );
		foreach ( lk_post_networks( $p ) as $n ) {
			$done[ $n ] = $done[ $n ] ?? array( 'manual' => 1, 'at' => lk_now(), 'url' => lk_in( 'url', 'url' ) );
		}
		lk_update( 'posts', $p->id, array( 'published' => wp_json_encode( $done ), 'publish_error' => '' ) );
		lk_post_move( $p, lk_stage_for( 'publicado' ), 'Marcado como publicado (manual).' );
	}
	lk_back( 'Marcado como publicado.' );
}

/* -----------------------------------------------------------------------
 * Números (Instagram) para o relatório
 * -------------------------------------------------------------------- */

/**
 * Todo dia: guarda seguidores e os totais do dia anterior (a API não guarda histórico longo).
 */
add_action( 'lk_daily', 'lk_metrics_collect' );
function lk_metrics_collect() {
	$day = gmdate( 'Y-m-d', strtotime( lk_today() . ' -1 day' ) );
	foreach ( lk_rows( 'social_accounts', "network = 'instagram' AND status = 'ok'" ) as $a ) {
		if ( lk_rows( 'metrics', 'client_id = %d AND network = %s AND day = %s', array( $a->client_id, 'instagram', $day ) ) ) {
			continue;
		}
		$tok  = lk_decrypt( $a->token );
		$me   = lk_http_json( 'GET', 'https://graph.instagram.com/' . LK_GRAPH . '/me?fields=followers_count,media_count&access_token=' . rawurlencode( $tok ) );
		$ins  = lk_ig_account_insights( $a, $day, $day );
		lk_insert( 'metrics', array( 'client_id' => $a->client_id, 'network' => 'instagram', 'day' => $day, 'data' => wp_json_encode( array( 'followers' => is_wp_error( $me ) ? null : (int) ( $me['followers_count'] ?? 0 ), 'media' => is_wp_error( $me ) ? null : (int) ( $me['media_count'] ?? 0 ) ) + ( is_wp_error( $ins ) ? array() : $ins ) ) ) );
	}
}

/**
 * Totais da conta num período (alcance, visualizações, visitas ao perfil, cliques, interações…).
 */
function lk_ig_account_insights( $a, $from, $to ) {
	$tok = lk_decrypt( $a->token );
	$out = array();
	$url = 'https://graph.instagram.com/' . LK_GRAPH . '/' . $a->account_id . '/insights?metric=reach,views,accounts_engaged,total_interactions,likes,comments,shares,saves,profile_links_taps,follows_and_unfollows&period=day&metric_type=total_value&since=' . strtotime( $from . ' 00:00:00' ) . '&until=' . strtotime( $to . ' 23:59:59' ) . '&access_token=' . rawurlencode( $tok );
	$r   = lk_http_json( 'GET', $url );
	if ( is_wp_error( $r ) ) {
		return $r;
	}
	foreach ( (array) ( $r['data'] ?? array() ) as $m ) {
		$out[ $m['name'] ] = (int) ( $m['total_value']['value'] ?? 0 );
	}
	$pv = lk_http_json( 'GET', 'https://graph.instagram.com/' . LK_GRAPH . '/' . $a->account_id . '/insights?metric=profile_views,website_clicks&period=day&metric_type=total_value&since=' . strtotime( $from ) . '&until=' . strtotime( $to . ' 23:59:59' ) . '&access_token=' . rawurlencode( $tok ) );
	if ( ! is_wp_error( $pv ) ) {
		foreach ( (array) ( $pv['data'] ?? array() ) as $m ) {
			$out[ $m['name'] ] = (int) ( $m['total_value']['value'] ?? 0 );
		}
	}
	return $out;
}

/**
 * Posts do período com os números de cada um.
 */
function lk_ig_media_period( $a, $from, $to ) {
	$tok  = lk_decrypt( $a->token );
	$list = lk_http_json( 'GET', 'https://graph.instagram.com/' . LK_GRAPH . '/' . $a->account_id . '/media?fields=id,caption,media_type,media_url,thumbnail_url,permalink,timestamp,like_count,comments_count&limit=60&since=' . strtotime( $from ) . '&until=' . strtotime( $to . ' 23:59:59' ) . '&access_token=' . rawurlencode( $tok ) );
	if ( is_wp_error( $list ) ) {
		return $list;
	}
	$out = array();
	foreach ( (array) ( $list['data'] ?? array() ) as $m ) {
		$ins = lk_http_json( 'GET', 'https://graph.instagram.com/' . LK_GRAPH . '/' . $m['id'] . '/insights?metric=reach,views,saved,shares,total_interactions&access_token=' . rawurlencode( $tok ) );
		$v   = array();
		if ( ! is_wp_error( $ins ) ) {
			foreach ( (array) ( $ins['data'] ?? array() ) as $x ) {
				$v[ $x['name'] ] = (int) ( $x['values'][0]['value'] ?? 0 );
			}
		}
		$out[] = array(
			'id'        => $m['id'],
			'type'      => $m['media_type'],
			'caption'   => mb_substr( (string) ( $m['caption'] ?? '' ), 0, 160 ),
			'img'       => $m['thumbnail_url'] ?? ( $m['media_url'] ?? '' ),
			'url'       => $m['permalink'] ?? '',
			'date'      => substr( (string) $m['timestamp'], 0, 10 ),
			'likes'     => (int) ( $m['like_count'] ?? 0 ),
			'comments'  => (int) ( $m['comments_count'] ?? 0 ),
			'reach'     => $v['reach'] ?? 0,
			'views'     => $v['views'] ?? 0,
			'saves'     => $v['saved'] ?? 0,
			'shares'    => $v['shares'] ?? 0,
			'engaged'   => $v['total_interactions'] ?? ( (int) ( $m['like_count'] ?? 0 ) + (int) ( $m['comments_count'] ?? 0 ) ),
		);
	}
	return $out;
}

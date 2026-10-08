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

// Chave secreta do app (Facebook), fixa no código: preencher entre as aspas.
if ( ! defined( 'LK_META_SECRET_FIXA' ) ) {
	define( 'LK_META_SECRET_FIXA', 'b978a3fe4b634bca4fda290f6f785989' );
}

// Chave secreta do app do Instagram, fixa no código a pedido (não compartilhe este arquivo nem o zip).
if ( ! defined( 'LK_IG_SECRET_FIXA' ) ) {
	define( 'LK_IG_SECRET_FIXA', '01ddf4ec8675f0946f1eb97a1f84df0d' );
}

/** Chaves de redes guardadas em texto puro (a criptografia estava corrompendo o valor). */
function lk_plain_secret_keys() {
	return array( 'ig_app_secret', 'meta_app_secret', 'linkedin_client_secret', 'google_client_secret' );
}

/** Todas as chaves secretas das Configurações (para o botão Apagar). */
function lk_all_secret_keys() {
	return array( 'google_client_secret', 'smtp_pass', 'anthropic_key', 'gemini_key', 'groq_key', 'mistral_key', 'openrouter_key', 'places_key', 'melhorenvio_token', 'ml_secret', 'shopee_key', 'ig_app_secret', 'meta_app_secret', 'meta_ads_token', 'voz_turn_pass', 'linkedin_client_secret' );
}

/** Linha "Chave salva: N caracteres" + botão Apagar, lendo do mesmo lugar que a conexão usa. */
function lk_secret_row( $key ) {
	$stored = (string) lk_setting( $key );
	$len    = strlen( lk_secret( $key ) );
	echo '<p class="muted small" style="margin:4px 0 0">';
	if ( 'meta_app_secret' === $key && defined( 'LK_META_SECRET_FIXA' ) && '' !== LK_META_SECRET_FIXA ) {
		return trim( LK_META_SECRET_FIXA ); // chave fixa no código
	}
	if ( ( 'ig_app_secret' === $key && '' !== LK_IG_SECRET_FIXA ) || ( 'meta_app_secret' === $key && '' !== LK_META_SECRET_FIXA ) ) {
		echo '<strong>Chave fixa no código: ' . (int) $len . ' caracteres</strong> (usada na conexão; o campo acima fica sem efeito).';
	} elseif ( '' === $stored ) {
		echo 'Nenhuma chave salva.';
	} else {
		echo '<strong>Chave salva: ' . (int) $len . ' caracteres</strong>' . ( 0 === $len ? ' (ilegível: cole de novo)' : '' ) . ' · <button type="submit" form="secdel-' . esc_attr( $key ) . '" class="btn btn--ghost btn--sm" onclick="return confirm(\'Apagar a chave salva?\')">Apagar chave salva</button>';
	}
	echo '</p>';
	$GLOBALS['lk_secret_rows'][ $key ] = true;
}

/** Formulários de apagar (ficam fora do formulário principal, ligados pelo atributo form). */
function lk_secret_forms() {
	foreach ( array_keys( (array) ( $GLOBALS['lk_secret_rows'] ?? array() ) ) as $key ) {
		echo '<form id="secdel-' . esc_attr( $key ) . '" method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" hidden><input type="hidden" name="action" value="lk"><input type="hidden" name="do" value="secret_delete"><input type="hidden" name="key" value="' . esc_attr( $key ) . '">';
		wp_nonce_field( 'lk_secret_delete', '_wpnonce', false );
		echo '</form>';
	}
}

function lk_do_secret_delete() {
	lk_require( 'admin' );
	$key = sanitize_key( lk_in( 'key' ) );
	if ( ! in_array( $key, lk_all_secret_keys(), true ) ) {
		lk_back( 'Campo inválido.', 'erro' );
	}
	$s = get_option( 'lk_settings', array() );
	$s = is_array( $s ) ? $s : array();
	$s[ $key ] = '';
	update_option( 'lk_settings', $s );
	lk_back( 'Chave apagada. Cole a nova e salve.' );
}

/**
 * Lê uma chave secreta guardada (criptografada) e devolve o texto puro, sem espaços.
 * Se o valor foi criptografado mais de uma vez, desembrulha até sair o texto de verdade.
 */
function lk_secret( $key ) {
	if ( 'ig_app_secret' === $key && defined( 'LK_IG_SECRET_FIXA' ) && '' !== LK_IG_SECRET_FIXA ) {
		return trim( LK_IG_SECRET_FIXA ); // chave fixa no código: tem prioridade sobre o que estiver salvo
	}
	$v = (string) lk_setting( $key );
	for ( $i = 0; $i < 5 && '' !== $v && preg_match( '/^[so]:[A-Za-z0-9+\/=]{20,}$/', $v ); $i++ ) {
		$v = (string) lk_decrypt( $v );
	}
	return trim( $v );
}


function lk_social_redirect( $net ) {
	// O login do Instagram descarta tudo depois do "?": o retorno usa uma rota REST sem parâmetros (cliente e rede vão dentro do "state").
	if ( 'instagram' === $net ) {
		return rest_url( 'lk/v1/social/instagram' );
	}
	return admin_url( 'admin-post.php?action=lk_social_cb&net=' . $net );
}

add_action(
	'rest_api_init',
	function () {
		register_rest_route(
			'lk/v1',
			'/social/instagram',
			array(
				'methods'             => 'GET',
				'permission_callback' => '__return_true', // o "state" sorteado (15 min) prova a origem
				'callback'            => function ( $request ) {
					$_GET['net'] = 'instagram'; // phpcs:ignore WordPress.Security.NonceVerification
					$GLOBALS['lk_raw_code'] = (string) $request->get_param( 'code' ); // sem sanitize: o código vai ao Instagram como chegou
					$st = get_transient( 'lk_soc_' . sanitize_text_field( wp_unslash( $_GET['state'] ?? '' ) ) ); // phpcs:ignore WordPress.Security.NonceVerification
					if ( $st && empty( $st['public'] ) && ! empty( $st['user'] ) ) {
						wp_set_current_user( (int) $st['user'] ); // a REST zera o usuário sem nonce; o state guarda quem iniciou
					}
					lk_social_cb(); // termina com redirecionamento
				},
			)
		);
	}
);

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
		$ru_now = lk_social_redirect( 'instagram' );
		$saved  = get_transient( 'lk_soc_' . $st );
		if ( is_array( $saved ) ) {
			$saved['ru'] = $ru_now; // a troca do código usa exatamente este texto
			set_transient( 'lk_soc_' . $st, $saved, 900 );
		}
		return add_query_arg(
			array(
				'client_id'     => lk_setting( 'ig_app_id' ),
				'redirect_uri'  => rawurlencode( $ru_now ),
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
	if ( ! lk_can( 'clientes' ) ) {
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
	static $runs = 0;
	$runs++;
	$GLOBALS['lk_cb_runs'] = $runs;
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
		$res = 'instagram' === $net ? lk_ig_connect( $cid, $code, (string) ( $st['ru'] ?? '' ) ) : lk_fb_connect( $cid, $code );
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

/**
 * Diário das chamadas ao Instagram (últimas 20 linhas, visível em Configurações).
 */
function lk_social_log( $line ) {
	$log   = get_option( 'lk_social_log', array() );
	$log   = is_array( $log ) ? $log : array();
	$log[] = gmdate( 'd/m H:i:s' ) . ' · callback#' . (int) ( $GLOBALS['lk_cb_runs'] ?? 0 ) . ' · ' . $line;
	update_option( 'lk_social_log', array_slice( $log, -20 ), false );
}

function lk_ig_connect( $cid, $code, $ru = '' ) {
	$raw   = isset( $GLOBALS['lk_raw_code'] ) ? (string) $GLOBALS['lk_raw_code'] : (string) $code;
	$code  = trim( preg_replace( '/#_.*$/', '', $raw ) );
	$ru    = $ru ? $ru : lk_social_redirect( 'instagram' );
	$cidk  = trim( (string) lk_setting( 'ig_app_id' ) );
	$sec   = lk_secret( 'ig_app_secret' );
	if ( ! preg_match( '/^[0-9a-f]{32}$/i', $sec ) ) {
		$st0 = (string) lk_setting( 'ig_app_secret' );
		lk_social_log( 'client_secret ilegível: lido ' . strlen( $sec ) . ' car. (esperado 32 hexadecimais); guardado ' . strlen( $st0 ) . ' car., começa com "' . substr( $st0, 0, 2 ) . '"; Instagram não foi chamado' );
		return new WP_Error( 'lk', 'Chave secreta do Instagram não pôde ser lida. Cole de novo em Configurações. [secret lido: ' . strlen( $sec ) . ' car.; esperado: 32]' );
	}
	$seen  = 'lk_igcode_' . md5( $code );
	$prev  = get_transient( $seen );
	if ( $prev ) {
		// Mesmo código de novo: não reenvia ao Instagram (só vale uma vez). Mostra o resultado da primeira chamada.
		lk_social_log( 'code repetido ignorado; resultado da 1ª chamada: ' . $prev );
		return new WP_Error( 'lk', 'Instagram (1ª chamada): ' . $prev );
	}
	$resp = wp_remote_post(
		'https://api.instagram.com/oauth/access_token',
		array(
			'timeout' => 60,
			'body'    => array(
				'client_id'     => $cidk,
				'client_secret' => $sec,
				'grant_type'    => 'authorization_code',
				'redirect_uri'  => $ru,
				'code'          => $code,
			),
		)
	);
	$http = is_wp_error( $resp ) ? 'erro de rede' : (int) wp_remote_retrieve_response_code( $resp );
	$body = is_wp_error( $resp ) ? $resp->get_error_message() : (string) wp_remote_retrieve_body( $resp );
	lk_social_log( 'POST api.instagram.com/oauth/access_token · client_id=' . $cidk . ' · redirect_uri=' . $ru . ' · code=' . strlen( $code ) . ' car. (' . substr( $code, 0, 6 ) . '…) · client_secret=' . strlen( $sec ) . ' car. · HTTP ' . $http . ' · ' . mb_substr( $body, 0, 600 ) );
	$json = is_wp_error( $resp ) ? array() : json_decode( $body, true );
	if ( is_array( $json ) && empty( $json['access_token'] ) && ! empty( $json['data'][0]['access_token'] ) ) {
		$json = $json['data'][0]; // formato antigo da resposta
	}
	if ( is_wp_error( $resp ) || (int) $http >= 300 || ! is_array( $json ) || isset( $json['error_message'] ) || isset( $json['error'] ) || empty( $json['access_token'] ) ) {
		set_transient( $seen, 'HTTP ' . $http . ' ' . mb_substr( $body, 0, 500 ), 600 );
		return new WP_Error( 'lk', 'Instagram: HTTP ' . $http . ' · resposta bruta: ' . mb_substr( $body, 0, 500 ) . ' [redirect_uri: ' . $ru . '; app: ' . $cidk . '; secret: ' . strlen( $sec ) . ' car.; code: ' . strlen( $code ) . ' car.; callback#' . (int) ( $GLOBALS['lk_cb_runs'] ?? 0 ) . ']' );
	}
	set_transient( $seen, 'usado com sucesso', 600 );
	$short = $json;
	$long = lk_http_json( 'GET', add_query_arg( array( 'grant_type' => 'ig_exchange_token', 'client_secret' => $sec, 'access_token' => $short['access_token'] ), 'https://graph.instagram.com/access_token' ) );
	if ( is_wp_error( $long ) ) {
		lk_social_log( 'GET graph.instagram.com/access_token (token longo) · ' . $long->get_error_message() );
		return new WP_Error( 'lk', 'Instagram: o token curto veio, mas a troca pelo token longo falhou. Resposta: ' . $long->get_error_message() );
	}
	lk_social_log( 'token longo ok' );
	$me = lk_http_json( 'GET', 'https://graph.instagram.com/' . LK_GRAPH . '/me?fields=user_id,username,name,profile_picture_url,account_type,followers_count&access_token=' . rawurlencode( $long['access_token'] ) );
	if ( is_wp_error( $me ) ) {
		lk_social_log( 'GET graph.instagram.com/me · ' . $me->get_error_message() );
		return new WP_Error( 'lk', 'Instagram: token ok, mas não consegui ler o perfil. Resposta: ' . $me->get_error_message() );
	}
	$saved = lk_social_store( $cid, 'instagram', $me['user_id'] ?? $me['id'], $me['username'] ?? '', $me['name'] ?? '', $me['profile_picture_url'] ?? '', $long['access_token'], time() + (int) ( $long['expires_in'] ?? 5184000 ), array( 'type' => $me['account_type'] ?? '', 'followers' => (int) ( $me['followers_count'] ?? 0 ) ) );
	if ( is_wp_error( $saved ) ) {
		return $saved;
	}
	return 'Instagram @' . ( $me['username'] ?? '' ) . ' conectado e gravado para o cliente #' . (int) $cid . '.';
}

function lk_fb_connect( $cid, $code ) {
	$tok = lk_http_json( 'GET', add_query_arg( array( 'client_id' => lk_setting( 'meta_app_id' ), 'client_secret' => lk_secret( 'meta_app_secret' ), 'redirect_uri' => rawurlencode( lk_social_redirect( 'facebook' ) ), 'code' => $code ), 'https://graph.facebook.com/' . LK_GRAPH . '/oauth/access_token' ) );
	if ( is_wp_error( $tok ) ) {
		return $tok;
	}
	$long  = lk_http_json( 'GET', add_query_arg( array( 'grant_type' => 'fb_exchange_token', 'client_id' => lk_setting( 'meta_app_id' ), 'client_secret' => lk_secret( 'meta_app_secret' ), 'fb_exchange_token' => $tok['access_token'] ), 'https://graph.facebook.com/' . LK_GRAPH . '/oauth/access_token' ) );
	$user  = is_wp_error( $long ) ? $tok['access_token'] : $long['access_token'];
	$pages = lk_http_json( 'GET', 'https://graph.facebook.com/' . LK_GRAPH . '/me/accounts?fields=id,name,access_token,picture{url}&limit=100&access_token=' . rawurlencode( $user ) );
	if ( is_wp_error( $pages ) || empty( $pages['data'] ) ) {
		return new WP_Error( 'lk', 'Nenhuma Página do Facebook encontrada nessa conta.' );
	}
	// Uma página: conecta direto. Várias: guarda e pede para escolher.
	if ( 1 === count( $pages['data'] ) ) {
		$pg = $pages['data'][0];
		$saved = lk_social_store( $cid, 'facebook', $pg['id'], '', $pg['name'], $pg['picture']['data']['url'] ?? '', $pg['access_token'], 0 );
		if ( is_wp_error( $saved ) ) {
			return $saved;
		}
		return 'Página "' . $pg['name'] . '" conectada.';
	}
	set_transient( 'lk_fbpages_' . $cid, lk_encrypt( wp_json_encode( $pages['data'] ) ), 900 );
	return 'Escolha a Página do cliente na lista (em Redes sociais, na ficha do cliente).';
}

function lk_do_fb_pick_page() {
	lk_require( 'clientes' );
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
	global $wpdb;
	for ( $try = 0; $try < 2; $try++ ) {
		$wpdb->last_error = '';
		if ( $old ) {
			$ok = false !== lk_update( 'social_accounts', $old->id, $data );
		} else {
			$ok = (bool) lk_insert( 'social_accounts', $data );
		}
		if ( $ok && '' === (string) $wpdb->last_error ) {
			// Confere lendo de volta, do mesmo lugar que a ficha do cliente usa.
			$back = lk_social_account( $cid, $net );
			if ( $back && (string) $back->account_id === (string) $account && 'ok' === $back->status ) {
				lk_social_log( 'conta gravada: cliente ' . (int) $cid . ' · ' . $net . ' · @' . $username . ' · vence ' . ( $expires ? gmdate( 'd/m/Y', $expires ) : '—' ) );
				return true;
			}
		}
		lk_social_log( 'FALHA ao gravar a conta (tentativa ' . ( $try + 1 ) . '): ' . $wpdb->last_error );
		$data['avatar'] = ''; // endereço da foto muito longo era o suspeito: tenta sem a foto
		$old = lk_social_account( $cid, $net );
	}
	return new WP_Error( 'lk', 'A conta autorizou, mas não foi possível gravar no painel. Erro do banco: ' . ( $wpdb->last_error ? $wpdb->last_error : 'nenhum (a leitura de volta não achou o registro)' ) );
}

function lk_do_social_disconnect() {
	lk_require( 'clientes' );
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
	// Collab: o perfil convidado recebe o convite para aparecer como coautor (até 3, separados por vírgula).
	$collab = array_values( array_filter( array_map( function ( $h ) { return ltrim( trim( $h ), '@' ); }, explode( ',', (string) ( $p->collab ?? '' ) ) ) ) );
	$make = function ( $args ) use ( $base, $tok, $collab ) {
		if ( $collab && empty( $args['is_carousel_item'] ) && 'STORIES' !== ( $args['media_type'] ?? '' ) ) {
			$args['collaborators'] = wp_json_encode( array_slice( $collab, 0, 3 ) );
		}
		return lk_http_json( 'POST', $base . '/media', $args + array( 'access_token' => $tok ) );
	};
	if ( 'story' === $p->format ) {
		$c = $make( array( 'media_type' => 'STORIES', $isv( $urls[0] ) ? 'video_url' : 'image_url' => $urls[0] ) );
	} elseif ( count( $urls ) > 1 || 'carrossel' === $p->format ) {
		$kids = array();
		foreach ( array_slice( $urls, 0, 20 ) as $u ) {
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
	if ( function_exists( 'lk_manage_only' ) && lk_manage_only() ) {
		// Modo gerenciamento: o sistema não publica. O post agendado no mLabs vira "Publicado" sozinho quando chega a hora.
		foreach ( lk_posts( 'p.stage = %s AND p.paused = 0 AND p.scheduled_at IS NOT NULL AND p.scheduled_at <= %s', array( lk_stage_for( 'agendado' ), lk_now() ), 'p.scheduled_at LIMIT 50' ) as $p ) {
			lk_post_move( $p, lk_stage_for( 'publicado' ), 'Marcado como publicado no horário agendado (mLabs).' );
		}
		return;
	}
	if ( get_transient( 'lk_publishing' ) ) {
		return;
	}
	set_transient( 'lk_publishing', 1, 10 * MINUTE_IN_SECONDS );
	update_option( 'lk_last_publish_tick', time(), false );
	foreach ( lk_posts( 'p.stage = %s AND p.paused = 0 AND p.scheduled_at IS NOT NULL AND p.scheduled_at <= %s', array( lk_stage_for( 'agendado' ), lk_now() ), 'p.scheduled_at LIMIT 5' ) as $p ) {
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

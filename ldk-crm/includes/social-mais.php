<?php
/**
 * Mais redes para os clientes: LinkedIn (Página de empresa), YouTube e Google Meu Negócio.
 *
 * LinkedIn: app da LDK em developer.linkedin.com com o produto "Community Management API"
 *   (precisa da aprovação do LinkedIn). Escopos: w_organization_social, r_organization_admin, rw_organization_admin.
 * YouTube e Google Meu Negócio: o mesmo app do Google usado no Drive (Client ID/Secret em Configurações),
 *   com as APIs "YouTube Data API v3" e "Google My Business" ligadas. Cada cliente entra com a conta Google dele.
 *   - YouTube: vídeos enviados por app não verificado pelo Google ficam PRIVADOS até a verificação do app.
 *   - Google Meu Negócio: a API precisa ser liberada pelo Google (formulário de acesso à Business Profile API).
 *
 * Várias Páginas/canais/perfis na mesma conta: guarda a lista e pede para escolher na ficha do cliente.
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'LK_LI_VERSION', '202409' );

function lk_social_more_nets() {
	return array( 'linkedin', 'youtube', 'gmn' );
}

/**
 * Endereço de retorno: um para o LinkedIn e um para as redes do Google.
 */
function lk_social_more_redirect( $net ) {
	return lk_social_redirect( 'linkedin' === $net ? 'linkedin' : 'google' );
}

/**
 * URL de login (chamada por lk_social_go).
 */
function lk_social_more_auth_url( $net, $state ) {
	if ( 'linkedin' === $net ) {
		if ( ! lk_setting( 'linkedin_client_id' ) ) {
			return new WP_Error( 'lk', 'Preencha o Client ID e o Secret do LinkedIn em Configurações → Redes sociais.' );
		}
		return add_query_arg(
			array(
				'response_type' => 'code',
				'client_id'     => rawurlencode( lk_setting( 'linkedin_client_id' ) ),
				'redirect_uri'  => rawurlencode( lk_social_more_redirect( 'linkedin' ) ),
				'state'         => $state,
				'scope'         => rawurlencode( 'w_organization_social r_organization_admin rw_organization_admin' ),
			),
			'https://www.linkedin.com/oauth/v2/authorization'
		);
	}
	if ( ! lk_setting( 'google_client_id' ) ) {
		return new WP_Error( 'lk', 'Preencha o Client ID e o Secret do Google em Configurações → Google.' );
	}
	$scope = 'youtube' === $net
		? 'openid email https://www.googleapis.com/auth/youtube.upload https://www.googleapis.com/auth/youtube.readonly'
		: 'openid email https://www.googleapis.com/auth/business.manage';
	return add_query_arg(
		array(
			'client_id'     => rawurlencode( lk_setting( 'google_client_id' ) ),
			'redirect_uri'  => rawurlencode( lk_social_more_redirect( $net ) ),
			'response_type' => 'code',
			'scope'         => rawurlencode( $scope ),
			'access_type'   => 'offline',
			'prompt'        => 'consent select_account',
			'state'         => $state,
		),
		'https://accounts.google.com/o/oauth2/v2/auth'
	);
}

/**
 * Troca o código pelo token e guarda a conta (chamada por lk_social_cb).
 */
function lk_social_more_connect( $net, $cid, $code ) {
	if ( 'linkedin' === $net ) {
		return lk_li_connect( $cid, $code );
	}
	$tok = lk_http_json(
		'POST',
		'https://oauth2.googleapis.com/token',
		array(
			'code'          => $code,
			'client_id'     => lk_setting( 'google_client_id' ),
			'client_secret' => lk_secret( 'google_client_secret' ),
			'redirect_uri'  => lk_social_more_redirect( $net ),
			'grant_type'    => 'authorization_code',
		)
	);
	if ( is_wp_error( $tok ) ) {
		return new WP_Error( 'lk', 'Google: ' . $tok->get_error_message() );
	}
	if ( empty( $tok['refresh_token'] ) ) {
		return new WP_Error( 'lk', 'O Google não devolveu a autorização permanente. Remova o acesso do app em myaccount.google.com/permissions e conecte de novo.' );
	}
	$hdr = array( 'Authorization' => 'Bearer ' . $tok['access_token'] );
	$opts = array();
	if ( 'youtube' === $net ) {
		$ch = lk_http_json( 'GET', 'https://www.googleapis.com/youtube/v3/channels?part=snippet,statistics&mine=true', null, $hdr );
		if ( is_wp_error( $ch ) || empty( $ch['items'] ) ) {
			return new WP_Error( 'lk', 'Nenhum canal do YouTube nessa conta Google.' );
		}
		foreach ( $ch['items'] as $it ) {
			$opts[] = array(
				'id'     => $it['id'],
				'name'   => $it['snippet']['title'] ?? 'Canal',
				'user'   => ltrim( (string) ( $it['snippet']['customUrl'] ?? '' ), '@' ),
				'avatar' => $it['snippet']['thumbnails']['default']['url'] ?? '',
				'extra'  => array( 'followers' => (int) ( $it['statistics']['subscriberCount'] ?? 0 ) ),
			);
		}
	} else {
		$acc = lk_http_json( 'GET', 'https://mybusinessaccountmanagement.googleapis.com/v1/accounts', null, $hdr );
		if ( is_wp_error( $acc ) ) {
			return new WP_Error( 'lk', 'Google Meu Negócio: ' . $acc->get_error_message() . ' (a API da Business Profile já foi liberada para o app?)' );
		}
		foreach ( (array) ( $acc['accounts'] ?? array() ) as $a ) {
			$locs = lk_http_json( 'GET', 'https://mybusinessbusinessinformation.googleapis.com/v1/' . $a['name'] . '/locations?readMask=name,title&pageSize=100', null, $hdr );
			foreach ( is_wp_error( $locs ) ? array() : (array) ( $locs['locations'] ?? array() ) as $l ) {
				$opts[] = array(
					'id'     => $a['name'] . '/' . $l['name'],
					'name'   => $l['title'] ?? 'Perfil',
					'user'   => '',
					'avatar' => '',
					'extra'  => array(),
				);
			}
		}
		if ( ! $opts ) {
			return new WP_Error( 'lk', 'Nenhum perfil do Google Meu Negócio nessa conta Google.' );
		}
	}
	$token = $tok['refresh_token']; // Guardamos o refresh; o acesso é renovado na hora de publicar.
	if ( 1 === count( $opts ) ) {
		$o = $opts[0];
		lk_social_store( $cid, $net, $o['id'], $o['user'], $o['name'], $o['avatar'], $token, 0, $o['extra'] );
		return lk_networks()[ $net ] . ': "' . $o['name'] . '" conectado.';
	}
	set_transient( 'lk_pick_' . $net . '_' . $cid, lk_encrypt( wp_json_encode( array( 'token' => $token, 'opts' => $opts ) ) ), 900 );
	return 'Escolha qual ' . ( 'youtube' === $net ? 'canal' : 'perfil' ) . ' usar (em Redes sociais, na ficha do cliente).';
}

function lk_li_connect( $cid, $code ) {
	$tok = lk_http_json(
		'POST',
		'https://www.linkedin.com/oauth/v2/accessToken',
		array(
			'grant_type'    => 'authorization_code',
			'code'          => $code,
			'client_id'     => lk_setting( 'linkedin_client_id' ),
			'client_secret' => lk_secret( 'linkedin_client_secret' ),
			'redirect_uri'  => lk_social_more_redirect( 'linkedin' ),
		)
	);
	if ( is_wp_error( $tok ) ) {
		return new WP_Error( 'lk', 'LinkedIn: ' . $tok->get_error_message() );
	}
	$hdr  = lk_li_headers( $tok['access_token'] );
	$acls = lk_http_json( 'GET', 'https://api.linkedin.com/rest/organizationAcls?q=roleAssignee&role=ADMINISTRATOR&state=APPROVED', null, $hdr );
	if ( is_wp_error( $acls ) || empty( $acls['elements'] ) ) {
		return new WP_Error( 'lk', 'Nenhuma Página de empresa do LinkedIn que essa pessoa administre.' . ( is_wp_error( $acls ) ? ' (' . $acls->get_error_message() . ')' : '' ) );
	}
	$opts = array();
	foreach ( $acls['elements'] as $el ) {
		$urn = $el['organization'] ?? '';
		$id  = preg_replace( '/\D/', '', $urn );
		$org = lk_http_json( 'GET', 'https://api.linkedin.com/rest/organizations/' . $id, null, $hdr );
		$opts[] = array(
			'id'     => $urn,
			'name'   => is_wp_error( $org ) ? 'Página ' . $id : ( $org['localizedName'] ?? 'Página' ),
			'user'   => is_wp_error( $org ) ? '' : (string) ( $org['vanityName'] ?? '' ),
			'avatar' => '',
			'extra'  => array( 'refresh' => $tok['refresh_token'] ?? '', 'refresh_expires' => isset( $tok['refresh_token_expires_in'] ) ? time() + (int) $tok['refresh_token_expires_in'] : 0 ),
		);
	}
	$exp = time() + (int) ( $tok['expires_in'] ?? 5184000 );
	if ( 1 === count( $opts ) ) {
		$o = $opts[0];
		lk_social_store( $cid, 'linkedin', $o['id'], $o['user'], $o['name'], '', $tok['access_token'], $exp, lk_li_extra_encrypt( $o['extra'] ) );
		return 'LinkedIn: Página "' . $o['name'] . '" conectada.';
	}
	set_transient( 'lk_pick_linkedin_' . $cid, lk_encrypt( wp_json_encode( array( 'token' => $tok['access_token'], 'expires' => $exp, 'opts' => $opts ) ) ), 900 );
	return 'Escolha a Página do LinkedIn (em Redes sociais, na ficha do cliente).';
}

function lk_li_extra_encrypt( $extra ) {
	if ( ! empty( $extra['refresh'] ) ) {
		$extra['refresh'] = lk_encrypt( $extra['refresh'] );
	}
	return $extra;
}

function lk_li_headers( $token ) {
	return array(
		'Authorization'             => 'Bearer ' . $token,
		'LinkedIn-Version'          => LK_LI_VERSION,
		'X-Restli-Protocol-Version' => '2.0.0',
		'Content-Type'              => 'application/json',
	);
}

/**
 * Escolha pendente (várias Páginas/canais/perfis): lista para a ficha do cliente.
 */
function lk_social_pick_pending( $cid ) {
	$out = array();
	foreach ( lk_social_more_nets() as $net ) {
		$raw = get_transient( 'lk_pick_' . $net . '_' . $cid );
		if ( $raw ) {
			$d = json_decode( (string) lk_decrypt( $raw ), true );
			if ( ! empty( $d['opts'] ) ) {
				$out[ $net ] = $d['opts'];
			}
		}
	}
	return $out;
}

function lk_do_social_pick() {
	lk_require( 'clientes' );
	$cid = lk_in( 'client_id', 'int' );
	$net = sanitize_key( lk_in( 'net' ) );
	$raw = get_transient( 'lk_pick_' . $net . '_' . $cid );
	$d   = $raw ? json_decode( (string) lk_decrypt( $raw ), true ) : null;
	foreach ( (array) ( $d['opts'] ?? array() ) as $o ) {
		if ( (string) $o['id'] === (string) lk_in( 'choice' ) ) {
			$extra = 'linkedin' === $net ? lk_li_extra_encrypt( $o['extra'] ) : $o['extra'];
			lk_social_store( $cid, $net, $o['id'], $o['user'], $o['name'], $o['avatar'], $d['token'], $d['expires'] ?? 0, $extra );
			delete_transient( 'lk_pick_' . $net . '_' . $cid );
			lk_back( lk_networks()[ $net ] . ': "' . $o['name'] . '" conectado.' );
		}
	}
	lk_back( 'Opção não encontrada. Conecte de novo.', 'erro' );
}

/* -----------------------------------------------------------------------
 * Tokens
 * -------------------------------------------------------------------- */

/**
 * Token de acesso do Google de uma conta de cliente (YouTube / Google Meu Negócio).
 */
function lk_gclient_token( $acc ) {
	$cache = get_transient( 'lk_gtok_' . $acc->id );
	if ( $cache ) {
		return lk_decrypt( $cache );
	}
	$r = lk_http_json(
		'POST',
		'https://oauth2.googleapis.com/token',
		array(
			'client_id'     => lk_setting( 'google_client_id' ),
			'client_secret' => lk_secret( 'google_client_secret' ),
			'refresh_token' => lk_decrypt( $acc->token ),
			'grant_type'    => 'refresh_token',
		)
	);
	if ( is_wp_error( $r ) || empty( $r['access_token'] ) ) {
		lk_update( 'social_accounts', $acc->id, array( 'status' => 'erro', 'error' => 'Reconecte a conta Google.' ) );
		return new WP_Error( 'lk', 'A conexão com o Google expirou: reconecte na ficha do cliente.' );
	}
	set_transient( 'lk_gtok_' . $acc->id, lk_encrypt( $r['access_token'] ), max( 60, (int) $r['expires_in'] - 120 ) );
	return $r['access_token'];
}

/**
 * LinkedIn: renova o token antes de vencer (quando o app tem refresh token).
 */
add_action( 'lk_daily', 'lk_li_refresh' );
function lk_li_refresh() {
	foreach ( lk_rows( 'social_accounts', "network = 'linkedin' AND expires_at IS NOT NULL AND expires_at < %s", array( gmdate( 'Y-m-d H:i:s', time() + 10 * DAY_IN_SECONDS ) ) ) as $a ) {
		$x  = json_decode( (string) $a->extra, true );
		$rt = ! empty( $x['refresh'] ) ? lk_decrypt( $x['refresh'] ) : '';
		if ( ! $rt ) {
			lk_update( 'social_accounts', $a->id, array( 'status' => 'erro', 'error' => 'O acesso do LinkedIn vence em breve: reconecte.' ) );
			continue;
		}
		$r = lk_http_json( 'POST', 'https://www.linkedin.com/oauth/v2/accessToken', array( 'grant_type' => 'refresh_token', 'refresh_token' => $rt, 'client_id' => lk_setting( 'linkedin_client_id' ), 'client_secret' => lk_secret( 'linkedin_client_secret' ) ) );
		if ( is_wp_error( $r ) ) {
			lk_update( 'social_accounts', $a->id, array( 'status' => 'erro', 'error' => 'Reconecte: ' . $r->get_error_message() ) );
			continue;
		}
		if ( ! empty( $r['refresh_token'] ) ) {
			$x['refresh'] = lk_encrypt( $r['refresh_token'] );
		}
		lk_update( 'social_accounts', $a->id, array( 'token' => lk_encrypt( $r['access_token'] ), 'expires_at' => gmdate( 'Y-m-d H:i:s', time() + (int) $r['expires_in'] ), 'extra' => wp_json_encode( $x ), 'status' => 'ok', 'error' => '' ) );
	}
}

/* -----------------------------------------------------------------------
 * Publicação
 * -------------------------------------------------------------------- */

/**
 * Caminho do arquivo no servidor a partir da URL pública temporária (ou do anexo do WordPress).
 */
function lk_media_local_path( $m, $url ) {
	if ( 0 === strpos( (string) $m['id'], 'wp-' ) ) {
		$f = get_attached_file( (int) substr( $m['id'], 3 ) );
		if ( $f && file_exists( $f ) ) {
			return $f;
		}
	}
	$up = wp_upload_dir();
	$f  = str_replace( trailingslashit( $up['baseurl'] ), trailingslashit( $up['basedir'] ), $url );
	return file_exists( $f ) ? $f : '';
}

function lk_publish_more( $net, $acc, $p, $urls, $media ) {
	if ( 'linkedin' === $net ) {
		return lk_li_publish( $acc, $p, $urls, $media );
	}
	if ( 'youtube' === $net ) {
		return lk_yt_publish( $acc, $p, $urls, $media );
	}
	return lk_gmn_publish( $acc, $p, $urls, $media );
}

function lk_li_publish( $acc, $p, $urls, $media ) {
	$tok = lk_decrypt( $acc->token );
	$hdr = lk_li_headers( $tok );
	$ids = array();
	foreach ( $media as $i => $m ) {
		$file = lk_media_local_path( $m, $urls[ $i ] );
		if ( ! $file ) {
			return new WP_Error( 'lk', 'arquivo da arte não encontrado.' );
		}
		$video = 'video' === $m['type'];
		if ( $video ) {
			$init = lk_http_json( 'POST', 'https://api.linkedin.com/rest/videos?action=initializeUpload', wp_json_encode( array( 'initializeUploadRequest' => array( 'owner' => $acc->account_id, 'fileSizeBytes' => filesize( $file ), 'uploadCaptions' => false, 'uploadThumbnail' => false ) ) ), $hdr );
			if ( is_wp_error( $init ) ) {
				return $init;
			}
			$etags = array();
			$fh    = fopen( $file, 'rb' ); // phpcs:ignore WordPress.WP.AlternativeFunctions
			foreach ( $init['value']['uploadInstructions'] as $ins ) {
				fseek( $fh, (int) $ins['firstByte'] );
				$chunk = fread( $fh, (int) $ins['lastByte'] - (int) $ins['firstByte'] + 1 ); // phpcs:ignore WordPress.WP.AlternativeFunctions
				$res   = wp_remote_request( $ins['uploadUrl'], array( 'method' => 'PUT', 'timeout' => 300, 'body' => $chunk, 'headers' => array( 'Content-Type' => 'application/octet-stream' ) ) );
				if ( is_wp_error( $res ) || wp_remote_retrieve_response_code( $res ) >= 300 ) {
					fclose( $fh ); // phpcs:ignore WordPress.WP.AlternativeFunctions
					return new WP_Error( 'lk', 'falha ao enviar o vídeo.' );
				}
				$etags[] = trim( (string) wp_remote_retrieve_header( $res, 'etag' ), '"' );
			}
			fclose( $fh ); // phpcs:ignore WordPress.WP.AlternativeFunctions
			$fin = lk_http_json( 'POST', 'https://api.linkedin.com/rest/videos?action=finalizeUpload', wp_json_encode( array( 'finalizeUploadRequest' => array( 'video' => $init['value']['video'], 'uploadToken' => '', 'uploadedPartIds' => $etags ) ) ), $hdr );
			if ( is_wp_error( $fin ) ) {
				return $fin;
			}
			$ids[] = $init['value']['video'];
			break; // LinkedIn: um vídeo por post.
		}
		$init = lk_http_json( 'POST', 'https://api.linkedin.com/rest/images?action=initializeUpload', wp_json_encode( array( 'initializeUploadRequest' => array( 'owner' => $acc->account_id ) ) ), $hdr );
		if ( is_wp_error( $init ) ) {
			return $init;
		}
		$res = wp_remote_request( $init['value']['uploadUrl'], array( 'method' => 'PUT', 'timeout' => 120, 'body' => file_get_contents( $file ), 'headers' => array( 'Authorization' => 'Bearer ' . $tok ) ) ); // phpcs:ignore WordPress.WP.AlternativeFunctions
		if ( is_wp_error( $res ) || wp_remote_retrieve_response_code( $res ) >= 300 ) {
			return new WP_Error( 'lk', 'falha ao enviar a imagem.' );
		}
		$ids[] = $init['value']['image'];
		if ( count( $ids ) >= 20 ) {
			break;
		}
	}
	$content = 1 === count( $ids ) ? array( 'media' => array( 'id' => $ids[0], 'title' => $p->title ) ) : array( 'multiImage' => array( 'images' => array_map( function ( $id ) { return array( 'id' => $id ); }, $ids ) ) );
	$body    = array(
		'author'                    => $acc->account_id,
		'commentary'                => lk_li_escape( (string) $p->caption ),
		'visibility'                => 'PUBLIC',
		'distribution'              => array( 'feedDistribution' => 'MAIN_FEED', 'targetEntities' => array(), 'thirdPartyDistributionChannels' => array() ),
		'content'                   => $content,
		'lifecycleState'            => 'PUBLISHED',
		'isReshareDisabledByAuthor' => false,
	);
	$res = wp_remote_post( 'https://api.linkedin.com/rest/posts', array( 'timeout' => 60, 'headers' => $hdr, 'body' => wp_json_encode( $body ) ) );
	if ( is_wp_error( $res ) || wp_remote_retrieve_response_code( $res ) >= 300 ) {
		$j = is_wp_error( $res ) ? array() : json_decode( wp_remote_retrieve_body( $res ), true );
		return new WP_Error( 'lk', is_wp_error( $res ) ? $res->get_error_message() : ( $j['message'] ?? 'erro ' . wp_remote_retrieve_response_code( $res ) ) );
	}
	$urn = (string) wp_remote_retrieve_header( $res, 'x-restli-id' );
	return array( 'id' => $urn, 'url' => $urn ? 'https://www.linkedin.com/feed/update/' . $urn . '/' : '' );
}

/**
 * O LinkedIn usa alguns caracteres como marcação no texto; escapa para sair igual à legenda.
 */
function lk_li_escape( $text ) {
	return preg_replace( '/([\\\\|{}@\[\]()<>#*_~])/', '\\\\$1', $text );
}

function lk_yt_publish( $acc, $p, $urls, $media ) {
	$video = null;
	foreach ( $media as $i => $m ) {
		if ( 'video' === $m['type'] ) {
			$video = lk_media_local_path( $m, $urls[ $i ] );
			break;
		}
	}
	if ( ! $video ) {
		return new WP_Error( 'lk', 'o YouTube só aceita vídeo (este post não tem vídeo).' );
	}
	$tok = lk_gclient_token( $acc );
	if ( is_wp_error( $tok ) ) {
		return $tok;
	}
	$caption = (string) $p->caption;
	$meta    = array(
		'snippet' => array( 'title' => mb_substr( $p->title, 0, 100 ), 'description' => mb_substr( $caption, 0, 4900 ), 'categoryId' => '22' ),
		'status'  => array( 'privacyStatus' => 'public', 'selfDeclaredMadeForKids' => false ),
	);
	$size = filesize( $video );
	$init = wp_remote_post(
		'https://www.googleapis.com/upload/youtube/v3/videos?uploadType=resumable&part=snippet,status',
		array(
			'timeout' => 30,
			'headers' => array( 'Authorization' => 'Bearer ' . $tok, 'Content-Type' => 'application/json; charset=UTF-8', 'X-Upload-Content-Length' => $size, 'X-Upload-Content-Type' => 'video/*' ),
			'body'    => wp_json_encode( $meta ),
		)
	);
	$loc = is_wp_error( $init ) ? '' : (string) wp_remote_retrieve_header( $init, 'location' );
	if ( ! $loc ) {
		$j = is_wp_error( $init ) ? array() : json_decode( wp_remote_retrieve_body( $init ), true );
		return new WP_Error( 'lk', $j['error']['message'] ?? 'não consegui iniciar o envio para o YouTube.' );
	}
	// Envia em partes de 8 MB.
	$fh    = fopen( $video, 'rb' ); // phpcs:ignore WordPress.WP.AlternativeFunctions
	$start = 0;
	$chunk = 8 * 1024 * 1024;
	$done  = null;
	while ( $start < $size ) {
		$data = fread( $fh, $chunk ); // phpcs:ignore WordPress.WP.AlternativeFunctions
		$end  = $start + strlen( $data ) - 1;
		$res  = wp_remote_request( $loc, array( 'method' => 'PUT', 'timeout' => 300, 'body' => $data, 'headers' => array( 'Authorization' => 'Bearer ' . $tok, 'Content-Range' => 'bytes ' . $start . '-' . $end . '/' . $size ) ) );
		if ( is_wp_error( $res ) ) {
			fclose( $fh ); // phpcs:ignore WordPress.WP.AlternativeFunctions
			return $res;
		}
		$code = wp_remote_retrieve_response_code( $res );
		if ( 308 === $code ) {
			$start = $end + 1;
			continue;
		}
		if ( $code >= 300 ) {
			fclose( $fh ); // phpcs:ignore WordPress.WP.AlternativeFunctions
			$j = json_decode( wp_remote_retrieve_body( $res ), true );
			return new WP_Error( 'lk', $j['error']['message'] ?? 'erro ' . $code . ' no envio.' );
		}
		$done = json_decode( wp_remote_retrieve_body( $res ), true );
		break;
	}
	fclose( $fh ); // phpcs:ignore WordPress.WP.AlternativeFunctions
	if ( empty( $done['id'] ) ) {
		return new WP_Error( 'lk', 'o YouTube não confirmou o vídeo.' );
	}
	return array( 'id' => $done['id'], 'url' => 'https://www.youtube.com/watch?v=' . $done['id'] );
}

function lk_gmn_publish( $acc, $p, $urls, $media ) {
	$tok = lk_gclient_token( $acc );
	if ( is_wp_error( $tok ) ) {
		return $tok;
	}
	$photo = '';
	foreach ( $media as $i => $m ) {
		if ( 'video' !== $m['type'] ) {
			$photo = $urls[ $i ];
			break;
		}
	}
	$body = array(
		'languageCode' => 'pt-BR',
		'summary'      => mb_substr( (string) $p->caption, 0, 1500 ),
		'topicType'    => 'STANDARD',
	);
	if ( $photo ) {
		$body['media'] = array( array( 'mediaFormat' => 'PHOTO', 'sourceUrl' => $photo ) );
	}
	$r = lk_http_json( 'POST', 'https://mybusiness.googleapis.com/v4/' . $acc->account_id . '/localPosts', wp_json_encode( $body ), array( 'Authorization' => 'Bearer ' . $tok, 'Content-Type' => 'application/json' ) );
	if ( is_wp_error( $r ) ) {
		return $r;
	}
	return array( 'id' => (string) ( $r['name'] ?? '' ), 'url' => (string) ( $r['searchUrl'] ?? '' ) );
}

<?php
/**
 * Uploads (fotos e arquivos 3D) + cópia automática no Google Drive.
 *
 * Tudo que sobe pelo painel vai para a biblioteca de mídia do WordPress (para mostrar nas páginas)
 * e entra numa fila que copia para o Drive: "Cardon Studio 3D / Orçamentos / Cliente - Peça",
 * "Cardon Studio 3D / Pedidos / #12 Cliente - Peça", "Cardon Studio 3D / Produtos / …".
 *
 * Conexão OAuth em Configurações → Google Drive (Client ID e Secret de um app do Google Cloud,
 * escopo drive.file: o painel só enxerga os arquivos que ele mesmo criou).
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/* -----------------------------------------------------------------------
 * Uploads
 * -------------------------------------------------------------------- */

function lk_3d_mimes() {
	return array(
		'stl' => 'model/stl',
		'3mf' => 'model/3mf',
		'obj' => 'model/obj',
		'glb' => 'model/gltf-binary',
	);
}

/**
 * Libera STL/3MF/OBJ/GLB só para quem é da equipe.
 */
add_filter(
	'upload_mimes',
	function ( $mimes ) {
		if ( function_exists( 'lk_is_team' ) && lk_is_team() ) {
			$mimes = array_merge( $mimes, lk_3d_mimes() );
		}
		return $mimes;
	}
);
add_filter(
	'wp_check_filetype_and_ext',
	function ( $data, $file, $filename, $mimes ) {
		$ext = strtolower( pathinfo( $filename, PATHINFO_EXTENSION ) );
		$map = lk_3d_mimes();
		if ( isset( $map[ $ext ] ) && function_exists( 'lk_is_team' ) && lk_is_team() ) {
			return array( 'ext' => $ext, 'type' => $map[ $ext ], 'proper_filename' => false );
		}
		return $data;
	},
	10,
	4
);

/**
 * Salva os arquivos de um <input type=file name="campo[]" multiple>.
 * Devolve [ [id, url, name, type], ... ] e põe cada um na fila do Drive.
 */
function lk_store_uploads( $field, $folder = array(), $kind = 'image' ) {
	if ( empty( $_FILES[ $field ] ) || empty( $_FILES[ $field ]['name'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification
		return array();
	}
	require_once ABSPATH . 'wp-admin/includes/file.php';
	require_once ABSPATH . 'wp-admin/includes/media.php';
	require_once ABSPATH . 'wp-admin/includes/image.php';
	$f     = $_FILES[ $field ]; // phpcs:ignore WordPress.Security.NonceVerification,WordPress.Security.ValidatedSanitizedInput
	$names = (array) $f['name'];
	$out   = array();
	foreach ( $names as $i => $name ) {
		if ( ! $name || ( is_array( $f['error'] ) ? $f['error'][ $i ] : $f['error'] ) ) {
			continue;
		}
		$file = array(
			'name'     => sanitize_file_name( $name ),
			'type'     => is_array( $f['type'] ) ? $f['type'][ $i ] : $f['type'],
			'tmp_name' => is_array( $f['tmp_name'] ) ? $f['tmp_name'][ $i ] : $f['tmp_name'],
			'error'    => 0,
			'size'     => is_array( $f['size'] ) ? $f['size'][ $i ] : $f['size'],
		);
		$ext = strtolower( pathinfo( $file['name'], PATHINFO_EXTENSION ) );
		if ( 'image' === $kind && ! in_array( $ext, array( 'jpg', 'jpeg', 'png', 'webp', 'gif', 'heic' ), true ) ) {
			continue;
		}
		if ( '3d' === $kind && ! isset( lk_3d_mimes()[ $ext ] ) ) {
			continue;
		}
		if ( $file['size'] > 60 * MB_IN_BYTES ) {
			continue;
		}
		$id = media_handle_sideload( $file, 0 );
		if ( is_wp_error( $id ) ) {
			continue;
		}
		$item  = array(
			'id'    => (int) $id,
			'url'   => wp_get_attachment_url( $id ),
			'thumb' => 'image' === $kind ? ( wp_get_attachment_image_url( $id, 'medium_large' ) ? wp_get_attachment_image_url( $id, 'medium_large' ) : wp_get_attachment_url( $id ) ) : '',
			'name'  => $file['name'],
			'type'  => $kind,
		);
		$out[] = $item;
		lk_drive_queue( $id, $folder );
	}
	return $out;
}

/* -----------------------------------------------------------------------
 * Google: conexão
 * -------------------------------------------------------------------- */

function lk_google_redirect_uri() {
	return admin_url( 'admin-post.php?action=lk_google_callback' );
}

function lk_google_state() {
	$g = get_option( 'lk_google', array() );
	return is_array( $g ) ? $g : array();
}

function lk_google_connected() {
	$g = lk_google_state();
	return ! empty( $g['refresh'] );
}

add_action( 'admin_post_lk_google_connect', 'lk_google_connect' );
function lk_google_connect() {
	if ( ! lk_is_admin() ) {
		wp_die( 'Sem permissão.' );
	}
	check_admin_referer( 'lk_google_connect' );
	$client_id = trim( (string) lk_setting( 'google_client_id' ) );
	if ( ! $client_id ) {
		lk_flash( 'Preencha o Client ID e o Client Secret e salve antes de conectar.', 'erro' );
		wp_safe_redirect( lk_panel_url( 'config' ) . '#google' );
		exit;
	}
	$state = wp_generate_password( 24, false );
	set_transient( 'lk_google_state_' . get_current_user_id(), $state, 600 );
	$url = add_query_arg(
		array(
			'client_id'     => rawurlencode( $client_id ),
			'redirect_uri'  => rawurlencode( lk_google_redirect_uri() ),
			'response_type' => 'code',
			'scope'         => rawurlencode( 'openid email https://www.googleapis.com/auth/drive.file https://www.googleapis.com/auth/calendar.events' ),
			'access_type'   => 'offline',
			'prompt'        => 'consent',
			'state'         => $state,
		),
		'https://accounts.google.com/o/oauth2/v2/auth'
	);
	wp_redirect( $url ); // phpcs:ignore WordPress.Security.SafeRedirect -- login do Google.
	exit;
}

add_action( 'admin_post_lk_google_callback', 'lk_google_callback' );
function lk_google_callback() {
	if ( ! lk_is_admin() ) {
		wp_die( 'Sem permissão.' );
	}
	// phpcs:disable WordPress.Security.NonceVerification.Recommended -- conferido pelo "state".
	$state = isset( $_GET['state'] ) ? sanitize_text_field( wp_unslash( $_GET['state'] ) ) : '';
	$code  = isset( $_GET['code'] ) ? sanitize_text_field( wp_unslash( $_GET['code'] ) ) : '';
	$error = isset( $_GET['error'] ) ? sanitize_text_field( wp_unslash( $_GET['error'] ) ) : '';
	// phpcs:enable
	$back = lk_panel_url( 'config' ) . '#google';
	if ( $error || ! $code || ! hash_equals( (string) get_transient( 'lk_google_state_' . get_current_user_id() ), $state ) ) {
		lk_flash( 'Conexão com o Google cancelada ou expirada. Tente de novo.', 'erro' );
		wp_safe_redirect( $back );
		exit;
	}
	delete_transient( 'lk_google_state_' . get_current_user_id() );
	$res  = wp_remote_post(
		'https://oauth2.googleapis.com/token',
		array(
			'timeout' => 20,
			'body'    => array(
				'code'          => $code,
				'client_id'     => lk_setting( 'google_client_id' ),
				'client_secret' => lk_decrypt( lk_setting( 'google_client_secret' ) ),
				'redirect_uri'  => lk_google_redirect_uri(),
				'grant_type'    => 'authorization_code',
			),
		)
	);
	$json = is_wp_error( $res ) ? array() : json_decode( wp_remote_retrieve_body( $res ), true );
	if ( empty( $json['access_token'] ) || empty( $json['refresh_token'] ) ) {
		lk_flash( 'O Google não devolveu a autorização. Confira o Client ID, o Secret e o endereço de retorno.', 'erro' );
		wp_safe_redirect( $back );
		exit;
	}
	$st            = lk_google_state();
	$st['access']  = lk_encrypt( $json['access_token'] );
	$st['expires'] = time() + (int) $json['expires_in'] - 60;
	$st['refresh'] = lk_encrypt( $json['refresh_token'] );
	$me            = lk_google_api( 'GET', 'https://www.googleapis.com/oauth2/v3/userinfo', null, $st );
	$st['email']   = ! is_wp_error( $me ) && ! empty( $me['email'] ) ? $me['email'] : '';
	update_option( 'lk_google', $st, false );
	lk_flash( 'Google Drive conectado' . ( $st['email'] ? ' (' . $st['email'] . ')' : '' ) . '. A partir de agora, os arquivos são copiados para lá.' );
	wp_safe_redirect( $back );
	exit;
}

function lk_do_google_disconnect() {
	lk_require( 'admin' );
	delete_option( 'lk_google' );
	delete_option( 'lk_drive_folders' );
	lk_back( 'Google desconectado.' );
}

function lk_google_token( $state = null ) {
	$state = $state ? $state : lk_google_state();
	if ( empty( $state['refresh'] ) ) {
		return new WP_Error( 'lk_google', 'Google não conectado.' );
	}
	if ( ! empty( $state['access'] ) && ! empty( $state['expires'] ) && $state['expires'] > time() ) {
		return lk_decrypt( $state['access'] );
	}
	$res  = wp_remote_post(
		'https://oauth2.googleapis.com/token',
		array(
			'timeout' => 20,
			'body'    => array(
				'client_id'     => lk_setting( 'google_client_id' ),
				'client_secret' => lk_decrypt( lk_setting( 'google_client_secret' ) ),
				'refresh_token' => lk_decrypt( $state['refresh'] ),
				'grant_type'    => 'refresh_token',
			),
		)
	);
	$json = is_wp_error( $res ) ? array() : json_decode( wp_remote_retrieve_body( $res ), true );
	if ( empty( $json['access_token'] ) ) {
		return new WP_Error( 'lk_google', 'A conexão com o Google expirou. Conecte de novo em Configurações.' );
	}
	update_option( 'lk_google', array_merge( lk_google_state(), array( 'access' => lk_encrypt( $json['access_token'] ), 'expires' => time() + (int) $json['expires_in'] - 60 ) ), false );
	return $json['access_token'];
}

function lk_google_api( $method, $url, $body = null, $state = null, $raw = null ) {
	$token = $state && ! empty( $state['access'] ) ? lk_decrypt( $state['access'] ) : lk_google_token();
	if ( is_wp_error( $token ) ) {
		return $token;
	}
	$args = array(
		'method'  => $method,
		'timeout' => 60,
		'headers' => array( 'Authorization' => 'Bearer ' . $token ),
	);
	if ( null !== $raw ) {
		$args['headers']['Content-Type'] = $raw['type'];
		$args['body']                    = $raw['body'];
	} elseif ( null !== $body ) {
		$args['headers']['Content-Type'] = 'application/json';
		$args['body']                    = wp_json_encode( $body );
	}
	$res = wp_remote_request( $url, $args );
	if ( is_wp_error( $res ) ) {
		return $res;
	}
	$code = wp_remote_retrieve_response_code( $res );
	$json = json_decode( wp_remote_retrieve_body( $res ), true );
	if ( $code >= 300 ) {
		return new WP_Error( 'lk_google', 'Google: ' . ( isset( $json['error']['message'] ) ? $json['error']['message'] : 'Erro ' . $code ) );
	}
	return is_array( $json ) ? $json : array();
}

/* -----------------------------------------------------------------------
 * Drive: pastas e envio
 * -------------------------------------------------------------------- */

/**
 * Id da pasta pelo caminho (cria o que faltar e guarda em cache).
 */
function lk_drive_folder( $path ) {
	$cache  = get_option( 'lk_drive_folders', array() );
	$cache  = is_array( $cache ) ? $cache : array();
	$root   = lk_setting( 'google_pasta' ) ? lk_setting( 'google_pasta' ) : lk_setting( 'empresa' );
	$parts  = array_merge( array( $root ), array_filter( array_map( 'trim', (array) $path ) ) );
	$parent = '';
	$key    = '';
	foreach ( $parts as $name ) {
		$name = mb_substr( str_replace( array( '/', '\\' ), '-', $name ), 0, 120 );
		$key .= '/' . $name;
		if ( empty( $cache[ $key ] ) ) {
			$body = array( 'name' => $name, 'mimeType' => 'application/vnd.google-apps.folder' );
			if ( $parent ) {
				$body['parents'] = array( $parent );
			}
			$res = lk_google_api( 'POST', 'https://www.googleapis.com/drive/v3/files?fields=id,webViewLink', $body );
			if ( is_wp_error( $res ) ) {
				return $res;
			}
			$cache[ $key ] = $res['id'];
			update_option( 'lk_drive_folders', $cache, false );
		}
		$parent = $cache[ $key ];
	}
	return $parent;
}

function lk_drive_queue( $attachment_id, $folder ) {
	if ( ! lk_google_connected() ) {
		return;
	}
	$q   = get_option( 'lk_drive_queue', array() );
	$q   = is_array( $q ) ? $q : array();
	$q[] = array( 'id' => (int) $attachment_id, 'folder' => array_values( (array) $folder ), 'tries' => 0 );
	update_option( 'lk_drive_queue', $q, false );
	if ( ! wp_next_scheduled( 'lk_drive_push' ) ) {
		wp_schedule_single_event( time() + 5, 'lk_drive_push' );
	}
}

/**
 * Envia a fila para o Drive (até 10 arquivos por rodada; o que falhar tenta de novo depois).
 */
add_action( 'lk_drive_push', 'lk_drive_push' );
add_action( 'lk_hourly', 'lk_drive_push' );
function lk_drive_push() {
	$q = get_option( 'lk_drive_queue', array() );
	if ( ! $q || ! lk_google_connected() ) {
		return;
	}
	$left = array();
	$done = 0;
	foreach ( $q as $item ) {
		if ( $done >= 10 ) {
			$left[] = $item;
			continue;
		}
		$ok = lk_drive_upload( $item['id'], $item['folder'] );
		$done++;
		if ( is_wp_error( $ok ) && $item['tries'] < 5 ) {
			$item['tries']++;
			$left[] = $item;
		}
	}
	update_option( 'lk_drive_queue', $left, false );
	if ( $left && ! wp_next_scheduled( 'lk_drive_push' ) ) {
		wp_schedule_single_event( time() + 300, 'lk_drive_push' );
	}
}

function lk_drive_upload( $attachment_id, $folder ) {
	$path = get_attached_file( $attachment_id );
	if ( ! $path || ! file_exists( $path ) ) {
		return new WP_Error( 'lk_drive', 'Arquivo não encontrado.' );
	}
	$parent = lk_drive_folder( $folder );
	if ( is_wp_error( $parent ) ) {
		return $parent;
	}
	$boundary = 'lk' . wp_generate_password( 16, false );
	$meta     = wp_json_encode( array( 'name' => basename( $path ), 'parents' => array( $parent ) ) );
	$mime     = get_post_mime_type( $attachment_id ) ? get_post_mime_type( $attachment_id ) : 'application/octet-stream';
	$body     = "--$boundary\r\nContent-Type: application/json; charset=UTF-8\r\n\r\n$meta\r\n--$boundary\r\nContent-Type: $mime\r\n\r\n" . file_get_contents( $path ) . "\r\n--$boundary--"; // phpcs:ignore WordPress.WP.AlternativeFunctions
	$res      = lk_google_api( 'POST', 'https://www.googleapis.com/upload/drive/v3/files?uploadType=multipart&fields=id,webViewLink', null, null, array( 'type' => 'multipart/related; boundary=' . $boundary, 'body' => $body ) );
	if ( ! is_wp_error( $res ) && ! empty( $res['webViewLink'] ) ) {
		update_post_meta( $attachment_id, '_lk_drive', $res['webViewLink'] );
	}
	return $res;
}

/**
 * Link da pasta do Drive de um caminho (para o botão "Abrir no Drive").
 */
function lk_drive_folder_link( $path ) {
	$cache = get_option( 'lk_drive_folders', array() );
	$root  = lk_setting( 'google_pasta' ) ? lk_setting( 'google_pasta' ) : lk_setting( 'empresa' );
	$key   = '/' . implode( '/', array_merge( array( $root ), array_map( function ( $n ) { return mb_substr( str_replace( array( '/', '\\' ), '-', trim( $n ) ), 0, 120 ); }, (array) $path ) ) );
	return ! empty( $cache[ $key ] ) ? 'https://drive.google.com/drive/folders/' . $cache[ $key ] : '';
}

<?php
/**
 * Upload de arquivos pesados (1 GB+) direto do navegador do cliente para o Google Drive da AllPrint.
 *
 * 1. POST /ap/v1/upload/start {name, size, type} → o servidor abre uma sessão "resumable" no Drive
 *    (pasta Clientes/<cliente>/Recebidos) informando a origem do navegador, e devolve a URL da sessão.
 * 2. O navegador manda o arquivo em pedaços de 8 MB direto para o Google (não passa pelo WordPress).
 * 3. POST /ap/v1/upload/done {id} → o servidor confirma e devolve nome, tamanho e link.
 * Sem Drive conectado: POST /ap/v1/upload/small envia pelo WordPress (até o limite do servidor).
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_action(
	'rest_api_init',
	function () {
		$who = function () {
			return is_user_logged_in() && ( ap_is_team() || ap_current_client() );
		};
		register_rest_route( 'ap/v1', '/upload/start', array( 'methods' => 'POST', 'callback' => 'ap_upload_start', 'permission_callback' => $who ) );
		register_rest_route( 'ap/v1', '/upload/done', array( 'methods' => 'POST', 'callback' => 'ap_upload_done', 'permission_callback' => $who ) );
		register_rest_route( 'ap/v1', '/upload/small', array( 'methods' => 'POST', 'callback' => 'ap_upload_small', 'permission_callback' => $who ) );
	}
);

function ap_upload_allowed( $name ) {
	return (bool) preg_match( '/\.(pdf|cdr|jpe?g|png|tiff?|ai|eps|psd|zip|rar)$/i', (string) $name );
}

function ap_upload_client_label() {
	$c = ap_current_client();
	return $c ? ap_client_label( $c ) : 'Equipe';
}

function ap_upload_start( WP_REST_Request $r ) {
	$name = sanitize_file_name( (string) $r['name'] );
	$size = (int) $r['size'];
	$type = sanitize_text_field( (string) $r['type'] ) ?: 'application/octet-stream';
	if ( ! ap_upload_allowed( $name ) ) {
		return new WP_Error( 'ap', 'Formato não aceito. Envie PDF, CDR (em curvas) ou JPG em 300 dpi.', array( 'status' => 400 ) );
	}
	if ( ! ap_google_connected() ) {
		return array( 'mode' => 'small', 'max' => wp_max_upload_size() );
	}
	$folder = ap_drive_folder( array( 'Clientes', ap_upload_client_label(), 'Recebidos' ) );
	if ( is_wp_error( $folder ) ) {
		return new WP_Error( 'ap', $folder->get_error_message(), array( 'status' => 500 ) );
	}
	$token = ap_google_token();
	if ( is_wp_error( $token ) ) {
		return new WP_Error( 'ap', $token->get_error_message(), array( 'status' => 500 ) );
	}
	$origin = wp_parse_url( home_url(), PHP_URL_SCHEME ) . '://' . wp_parse_url( home_url(), PHP_URL_HOST );
	$res    = wp_remote_post(
		'https://www.googleapis.com/upload/drive/v3/files?uploadType=resumable&fields=id,name,size,webViewLink',
		array(
			'timeout' => 20,
			'headers' => array(
				'Authorization'           => 'Bearer ' . $token,
				'Content-Type'            => 'application/json; charset=UTF-8',
				'X-Upload-Content-Type'   => $type,
				'X-Upload-Content-Length' => (string) $size,
				'Origin'                  => $origin,
			),
			'body'    => wp_json_encode( array( 'name' => $name, 'parents' => array( $folder ) ) ),
		)
	);
	$url = is_wp_error( $res ) ? '' : wp_remote_retrieve_header( $res, 'location' );
	if ( ! $url ) {
		return new WP_Error( 'ap', 'O Google Drive não abriu o envio. Tente de novo em instantes.', array( 'status' => 500 ) );
	}
	return array( 'mode' => 'drive', 'url' => $url, 'chunk' => 8 * 1024 * 1024 );
}

function ap_upload_done( WP_REST_Request $r ) {
	$id  = preg_replace( '/[^A-Za-z0-9_-]/', '', (string) $r['id'] );
	$res = ap_google_api( 'GET', 'https://www.googleapis.com/drive/v3/files/' . $id . '?fields=id,name,size,webViewLink' );
	if ( is_wp_error( $res ) ) {
		return new WP_Error( 'ap', 'Não achei o arquivo no Drive.', array( 'status' => 404 ) );
	}
	return array( 'id' => $res['id'], 'name' => $res['name'], 'size' => (int) ( $res['size'] ?? 0 ), 'link' => $res['webViewLink'] ?? '' );
}

/**
 * Plano B sem Drive: arquivo pelo WordPress (limite do servidor). Vira "id" = wp-<anexo>.
 */
function ap_upload_small( WP_REST_Request $r ) {
	$files = $r->get_file_params();
	if ( empty( $files['file']['tmp_name'] ) || ! ap_upload_allowed( $files['file']['name'] ) ) {
		return new WP_Error( 'ap', 'Arquivo inválido. Envie PDF, CDR ou JPG.', array( 'status' => 400 ) );
	}
	add_filter(
		'upload_mimes',
		function ( $m ) {
			return array_merge( $m, array( 'cdr' => 'application/octet-stream', 'ai' => 'application/postscript', 'eps' => 'application/postscript', 'psd' => 'image/vnd.adobe.photoshop' ) );
		}
	);
	require_once ABSPATH . 'wp-admin/includes/file.php';
	require_once ABSPATH . 'wp-admin/includes/media.php';
	require_once ABSPATH . 'wp-admin/includes/image.php';
	$id = media_handle_sideload( $files['file'], 0 );
	if ( is_wp_error( $id ) ) {
		return new WP_Error( 'ap', 'Não deu para enviar: ' . $id->get_error_message(), array( 'status' => 400 ) );
	}
	return array( 'id' => 'wp-' . $id, 'name' => $files['file']['name'], 'size' => (int) $files['file']['size'], 'link' => wp_get_attachment_url( $id ) );
}

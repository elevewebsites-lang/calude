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

/**
 * Espaço livre no Google Drive: true (cabe), false (cheio) ou null (não deu para saber).
 * Guarda a resposta por 5 minutos para não consultar a cada arquivo.
 */
function ap_drive_space_ok( $bytes = 0 ) {
	$c = get_transient( 'ap_drive_quota' );
	if ( false === $c ) {
		$res = ap_google_api( 'GET', 'https://www.googleapis.com/drive/v3/about?fields=storageQuota' );
		$q   = ! is_wp_error( $res ) && ! empty( $res['storageQuota'] ) ? $res['storageQuota'] : array();
		$c   = $q ? array( 'limit' => isset( $q['limit'] ) ? (float) $q['limit'] : 0, 'usage' => (float) ( $q['usage'] ?? 0 ) ) : array( 'unknown' => 1 );
		set_transient( 'ap_drive_quota', $c, 5 * MINUTE_IN_SECONDS );
	}
	if ( ! empty( $c['unknown'] ) || empty( $c['limit'] ) ) {
		return null; // sem limite informado (ex.: Workspace ilimitado) ou consulta indisponível
	}
	return ( $c['limit'] - $c['usage'] ) > ( (float) $bytes + 50 * MB_IN_BYTES );
}

function ap_drive_is_quota_error( $err ) {
	$m = is_wp_error( $err ) ? $err->get_error_message() : (string) $err;
	return (bool) preg_match( '/quota|storage|insufficient|no space|full/i', $m );
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
	$max = wp_max_upload_size();
	// Padrão: o arquivo vai direto do navegador para o Google Drive e não passa pela hospedagem.
	// Só cai na hospedagem se o Drive não estiver conectado ou não tiver espaço.
	if ( ! ap_google_connected() ) {
		return array( 'mode' => 'small', 'max' => $max, 'why' => 'drive-off' );
	}
	if ( false === ap_drive_space_ok( $size ) ) {
		return array( 'mode' => 'small', 'max' => $max, 'why' => 'drive-full' );
	}
	$folder = ap_drive_folder( array( 'Clientes', ap_upload_client_label(), 'Recebidos' ) );
	if ( is_wp_error( $folder ) ) {
		return array( 'mode' => 'small', 'max' => $max, 'why' => 'drive-error' );
	}
	$token = ap_google_token();
	if ( is_wp_error( $token ) ) {
		return array( 'mode' => 'small', 'max' => $max, 'why' => 'drive-error' );
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
		$body = is_wp_error( $res ) ? '' : wp_remote_retrieve_body( $res );
		if ( 403 === (int) ( is_wp_error( $res ) ? 0 : wp_remote_retrieve_response_code( $res ) ) && preg_match( '/quota/i', $body ) ) {
			delete_transient( 'ap_drive_quota' );
			return array( 'mode' => 'small', 'max' => $max, 'why' => 'drive-full' );
		}
		return array( 'mode' => 'small', 'max' => $max, 'why' => 'drive-error' );
	}
	return array( 'mode' => 'drive', 'url' => $url, 'chunk' => 8 * 1024 * 1024, 'max' => $max );
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
 * Arquivo por dentro do WordPress. Tenta o Drive primeiro (o servidor só repassa e não guarda nada).
 * Se o Drive estiver cheio, desconectado ou falhar, o arquivo fica na hospedagem (id "wp-…") marcado para
 * migrar para o Drive assim que houver espaço: depois da migração a cópia local é apagada.
 */
function ap_upload_small( WP_REST_Request $r ) {
	$files = $r->get_file_params();
	if ( empty( $files['file']['tmp_name'] ) || ! ap_upload_allowed( $files['file']['name'] ) ) {
		return new WP_Error( 'ap', 'Arquivo inválido. Envie PDF, CDR ou JPG.', array( 'status' => 400 ) );
	}
	$name   = sanitize_file_name( $files['file']['name'] );
	$path   = array( 'Clientes', ap_upload_client_label(), 'Recebidos' );
	$driveq = ap_google_connected() && false !== ap_drive_space_ok( (int) $files['file']['size'] );
	if ( $driveq ) {
		$folder = ap_drive_folder( $path );
		if ( ! is_wp_error( $folder ) ) {
			$res = ap_drive_upload_path( $files['file']['tmp_name'], $name, $files['file']['type'] ? $files['file']['type'] : 'application/octet-stream', $folder );
			if ( ! is_wp_error( $res ) ) {
				return array( 'id' => $res['id'], 'name' => $res['name'] ?? $name, 'size' => (int) ( $res['size'] ?? $files['file']['size'] ), 'link' => $res['webViewLink'] ?? '' );
			}
			if ( ap_drive_is_quota_error( $res ) ) {
				delete_transient( 'ap_drive_quota' );
			}
		}
	}
	// Plano B: hospedagem (só porque o Drive não pôde receber).
	add_filter(
		'upload_mimes',
		function ( $m ) {
			return array_merge( $m, array( 'cdr' => 'application/octet-stream', 'ai' => 'application/postscript', 'eps' => 'application/postscript', 'psd' => 'image/vnd.adobe.photoshop' ) );
		}
	);
	// O WordPress confere o conteúdo real do arquivo; arquivos de arte (CDR antigo vem como "RIFF") não passam nessa conferência.
	add_filter(
		'wp_check_filetype_and_ext',
		function ( $data, $file, $filename ) {
			$ext = strtolower( pathinfo( (string) $filename, PATHINFO_EXTENSION ) );
			return in_array( $ext, array( 'cdr', 'ai', 'eps', 'psd' ), true ) ? array( 'ext' => $ext, 'type' => 'application/octet-stream', 'proper_filename' => false ) : $data;
		},
		10,
		3
	);
	require_once ABSPATH . 'wp-admin/includes/file.php';
	require_once ABSPATH . 'wp-admin/includes/media.php';
	require_once ABSPATH . 'wp-admin/includes/image.php';
	$id = media_handle_sideload( $files['file'], 0 );
	if ( is_wp_error( $id ) ) {
		return new WP_Error( 'ap', 'Não deu para enviar: ' . $id->get_error_message(), array( 'status' => 400 ) );
	}
	update_post_meta( $id, '_ap_pending_drive', 1 );
	update_post_meta( $id, '_ap_drive_folder', $path );
	return array( 'id' => 'wp-' . $id, 'name' => $files['file']['name'], 'size' => (int) $files['file']['size'], 'link' => wp_get_attachment_url( $id ), 'hosted' => true );
}

/**
 * Migra para o Drive os arquivos que ficaram na hospedagem (por falta de espaço lá).
 * Dá certo → troca o id/link no pedido e apaga a cópia local. Roda de hora em hora (5 por vez).
 */
add_action( 'ap_hourly', 'ap_drive_migrate_hosted' );
function ap_drive_migrate_hosted() {
	if ( ! ap_google_connected() ) {
		return 0;
	}
	$ids  = get_posts( array( 'post_type' => 'attachment', 'post_status' => 'inherit', 'posts_per_page' => 5, 'fields' => 'ids', 'meta_key' => '_ap_pending_drive', 'meta_value' => 1 ) ); // phpcs:ignore WordPress.DB.SlowDBQuery
	$done = 0;
	foreach ( $ids as $aid ) {
		$file = get_attached_file( $aid );
		if ( ! $file || ! is_readable( $file ) ) {
			delete_post_meta( $aid, '_ap_pending_drive' );
			continue;
		}
		if ( false === ap_drive_space_ok( (int) filesize( $file ) ) ) {
			break;
		}
		$path   = (array) get_post_meta( $aid, '_ap_drive_folder', true );
		$folder = ap_drive_folder( $path ? $path : array( 'Clientes', 'Recebidos' ) );
		if ( is_wp_error( $folder ) ) {
			break;
		}
		$mime = get_post_mime_type( $aid ) ? get_post_mime_type( $aid ) : 'application/octet-stream';
		$res  = ap_drive_upload_path( $file, basename( $file ), $mime, $folder );
		if ( is_wp_error( $res ) ) {
			if ( ap_drive_is_quota_error( $res ) ) {
				delete_transient( 'ap_drive_quota' );
				break;
			}
			continue;
		}
		// Atualiza o pedido que apontava para "wp-ID".
		global $wpdb;
		$like = '%"wp-' . (int) $aid . '"%';
		foreach ( $wpdb->get_results( $wpdb->prepare( 'SELECT id, items FROM ' . ap_table( 'projects' ) . ' WHERE items LIKE %s', $like ) ) as $p ) { // phpcs:ignore WordPress.DB.PreparedSQL
			$items = json_decode( (string) $p->items, true );
			$hit   = false;
			foreach ( (array) $items as $i => $it ) {
				foreach ( (array) ( $it['files'] ?? array() ) as $n => $f ) {
					if ( isset( $f['id'] ) && 'wp-' . (int) $aid === $f['id'] ) {
						$items[ $i ]['files'][ $n ]['id']   = $res['id'];
						$items[ $i ]['files'][ $n ]['link'] = $res['webViewLink'] ?? '';
						$hit                                 = true;
					}
				}
			}
			if ( $hit ) {
				ap_update( 'projects', $p->id, array( 'items' => wp_json_encode( $items ) ) );
				ap_log( $p->id, 'Arquivo enviado ao Google Drive e removido da hospedagem.', false );
			}
		}
		wp_delete_attachment( $aid, true );
		$done++;
	}
	return $done;
}

/** Quantos arquivos estão na hospedagem esperando espaço no Drive (para o aviso na Saúde do sistema). */
function ap_hosted_pending() {
	$ids   = get_posts( array( 'post_type' => 'attachment', 'post_status' => 'inherit', 'posts_per_page' => 200, 'fields' => 'ids', 'meta_key' => '_ap_pending_drive', 'meta_value' => 1 ) ); // phpcs:ignore WordPress.DB.SlowDBQuery
	$bytes = 0;
	foreach ( $ids as $aid ) {
		$f      = get_attached_file( $aid );
		$bytes += $f && is_readable( $f ) ? (int) filesize( $f ) : 0;
	}
	return array( 'n' => count( $ids ), 'bytes' => $bytes );
}

<?php
/**
 * Fotos e arquivos do cliente: a equipe sobe (ou o cliente manda por um link) e tudo vai para o
 * Google Drive em Clientes/<cliente>/Fotos[/<pasta ou evento>]. Nada se perde.
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function lk_cfile_allowed( $name ) {
	return (bool) preg_match( '/\.(pdf|jpe?g|png|webp|gif|heic|heif|svg|mp4|mov|m4v|webm|psd|ai|eps|cdr|zip|rar|docx?|xlsx?|pptx?|mp3|wav|m4a|ttf|otf)$/i', (string) $name );
}

function lk_cfile_folder_name( $s ) {
	$s = trim( preg_replace( '/\s+/', ' ', str_replace( array( '/', '\\' ), '-', wp_strip_all_tags( (string) $s ) ) ) );
	return mb_substr( $s, 0, 60 );
}

function lk_cfile_path( $client, $folder = '' ) {
	$path = array( 'Clientes', lk_client_label( $client ), 'Fotos' );
	if ( '' !== $folder ) {
		$path[] = $folder;
	}
	return $path;
}

function lk_cfiles( $client_id ) {
	$l = get_option( 'lk_cfiles_' . (int) $client_id, array() );
	return is_array( $l ) ? $l : array();
}

function lk_cfile_folders( $client_id ) {
	$out = array();
	foreach ( lk_cfiles( $client_id ) as $f ) {
		if ( ! empty( $f['folder'] ) ) {
			$out[ $f['folder'] ] = true;
		}
	}
	return array_keys( $out );
}

function lk_photos_url( $c ) {
	if ( empty( $c->photos_token ) ) {
		$c->photos_token = strtolower( wp_generate_password( 24, false ) );
		lk_update( 'clients', $c->id, array( 'photos_token' => $c->photos_token ) );
	}
	return lk_url( 'fotos/' . $c->photos_token );
}

/** Cliente da requisição: pelo link (token) ou pela equipe logada. */
function lk_cfile_client( WP_REST_Request $r ) {
	$token = preg_replace( '/[^A-Za-z0-9]/', '', (string) $r['token'] );
	if ( $token ) {
		$rows = lk_rows( 'clients', 'photos_token = %s', array( $token ) );
		return $rows ? $rows[0] : null;
	}
	if ( is_user_logged_in() && lk_is_team() ) {
		return lk_get( 'clients', absint( $r['client'] ) );
	}
	return null;
}

add_action(
	'rest_api_init',
	function () {
		foreach ( array( 'start', 'small', 'add' ) as $op ) {
			register_rest_route( 'lk/v1', '/cfile/' . $op, array( 'methods' => 'POST', 'permission_callback' => '__return_true', 'callback' => 'lk_cfile_' . $op ) );
		}
	}
);

/** Limite simples para o link público: até 300 envios por dia por cliente. */
function lk_cfile_rate( $c, $token_mode ) {
	if ( ! $token_mode ) {
		return true;
	}
	$k = 'lk_cfrate_' . $c->id;
	$n = (int) get_transient( $k );
	if ( $n >= 300 ) {
		return false;
	}
	set_transient( $k, $n + 1, DAY_IN_SECONDS );
	return true;
}

function lk_cfile_start( WP_REST_Request $r ) {
	$c = lk_cfile_client( $r );
	if ( ! $c || ! lk_cfile_rate( $c, (bool) $r['token'] ) ) {
		return new WP_Error( 'lk', 'Link inválido ou limite do dia atingido.', array( 'status' => 403 ) );
	}
	$name = sanitize_file_name( (string) $r['name'] );
	if ( ! lk_cfile_allowed( $name ) ) {
		return new WP_Error( 'lk', 'Formato não aceito. Envie fotos, vídeos, PDF ou arquivos de design.', array( 'status' => 400 ) );
	}
	if ( ! lk_google_connected() ) {
		return array( 'mode' => 'small', 'max' => wp_max_upload_size() );
	}
	$folder = lk_drive_folder( lk_cfile_path( $c, lk_cfile_folder_name( $r['folder'] ) ) );
	$token  = is_wp_error( $folder ) ? $folder : lk_google_token();
	if ( is_wp_error( $token ) ) {
		return new WP_Error( 'lk', $token->get_error_message(), array( 'status' => 500 ) );
	}
	$type   = sanitize_text_field( (string) $r['type'] ) ?: 'application/octet-stream';
	$origin = wp_parse_url( home_url(), PHP_URL_SCHEME ) . '://' . wp_parse_url( home_url(), PHP_URL_HOST );
	$res    = wp_remote_post(
		'https://www.googleapis.com/upload/drive/v3/files?uploadType=resumable&fields=id,name,size,webViewLink',
		array(
			'timeout' => 20,
			'headers' => array(
				'Authorization'           => 'Bearer ' . $token,
				'Content-Type'            => 'application/json; charset=UTF-8',
				'X-Upload-Content-Type'   => $type,
				'X-Upload-Content-Length' => (string) (int) $r['size'],
				'Origin'                  => $origin,
			),
			'body'    => wp_json_encode( array( 'name' => $name, 'parents' => array( $folder ) ) ),
		)
	);
	$url = is_wp_error( $res ) ? '' : wp_remote_retrieve_header( $res, 'location' );
	if ( ! $url ) {
		return new WP_Error( 'lk', 'O Google Drive não abriu o envio. Tente de novo em instantes.', array( 'status' => 500 ) );
	}
	return array( 'mode' => 'drive', 'url' => $url, 'chunk' => 8 * 1024 * 1024 );
}

/** Plano B (sem Drive) e fotos pequenas: sobe pelo WordPress e entra na fila do Drive. */
function lk_cfile_small( WP_REST_Request $r ) {
	$c     = lk_cfile_client( $r );
	$files = $r->get_file_params();
	if ( ! $c || ! lk_cfile_rate( $c, (bool) $r['token'] ) || empty( $files['file']['tmp_name'] ) || ! lk_cfile_allowed( $files['file']['name'] ) ) {
		return new WP_Error( 'lk', 'Arquivo inválido ou link expirado.', array( 'status' => 400 ) );
	}
	add_filter(
		'upload_mimes',
		function ( $m ) {
			return array_merge( $m, array( 'mp4' => 'video/mp4', 'mov' => 'video/quicktime', 'webm' => 'video/webm', 'psd' => 'image/vnd.adobe.photoshop', 'heic' => 'image/heic', 'svg' => 'image/svg+xml' ) );
		}
	);
	require_once ABSPATH . 'wp-admin/includes/file.php';
	require_once ABSPATH . 'wp-admin/includes/media.php';
	require_once ABSPATH . 'wp-admin/includes/image.php';
	$id = media_handle_sideload( $files['file'], 0 );
	if ( is_wp_error( $id ) ) {
		return new WP_Error( 'lk', 'Não deu para enviar: ' . $id->get_error_message(), array( 'status' => 400 ) );
	}
	$folder = lk_cfile_folder_name( $r['folder'] );
	update_post_meta( $id, '_lk_cfile_client', (int) $c->id );
	lk_drive_queue( $id, lk_cfile_path( $c, $folder ) );
	return array( 'id' => 'wp-' . $id, 'name' => $files['file']['name'], 'size' => (int) $files['file']['size'] );
}

/** Registra o arquivo na lista do cliente, conferindo no Drive (ou no WordPress) em vez de confiar no navegador. */
function lk_cfile_add( WP_REST_Request $r ) {
	$c = lk_cfile_client( $r );
	if ( ! $c ) {
		return new WP_Error( 'lk', 'Link inválido.', array( 'status' => 403 ) );
	}
	$id     = preg_replace( '/[^A-Za-z0-9_-]/', '', (string) $r['id'] );
	$folder = lk_cfile_folder_name( $r['folder'] );
	$entry  = array( 'folder' => $folder, 'at' => current_time( 'mysql' ), 'by' => $r['token'] ? 'cliente' : wp_get_current_user()->display_name );
	if ( 0 === strpos( $id, 'wp-' ) ) {
		$att = (int) substr( $id, 3 );
		if ( ! $att || (int) get_post_meta( $att, '_lk_cfile_client', true ) !== (int) $c->id ) {
			return new WP_Error( 'lk', 'Arquivo não encontrado.', array( 'status' => 404 ) );
		}
		$entry += array( 'id' => $id, 'name' => get_the_title( $att ) ? basename( get_attached_file( $att ) ) : $id, 'size' => (int) filesize( get_attached_file( $att ) ), 'link' => wp_get_attachment_url( $att ), 'thumb' => (string) wp_get_attachment_image_url( $att, 'thumbnail' ) );
	} else {
		$res = lk_google_api( 'GET', 'https://www.googleapis.com/drive/v3/files/' . $id . '?fields=id,name,size,webViewLink,mimeType,parents' );
		if ( is_wp_error( $res ) ) {
			return new WP_Error( 'lk', 'Não achei o arquivo no Drive.', array( 'status' => 404 ) );
		}
		$entry += array( 'id' => $res['id'], 'name' => $res['name'], 'size' => (int) ( $res['size'] ?? 0 ), 'link' => $res['webViewLink'] ?? '', 'thumb' => '' );
	}
	$td = (string) $r['thumb'];
	if ( empty( $entry['thumb'] ) && 0 === strpos( $td, 'data:image/jpeg;base64,' ) && strlen( $td ) < 220000 ) {
		$bin = base64_decode( substr( $td, 23 ), true ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions
		if ( $bin && 0 === strpos( $bin, "\xFF\xD8" ) ) {
			$up = wp_upload_dir();
			$dir = $up['basedir'] . '/lk-cfiles/' . (int) $c->id;
			wp_mkdir_p( $dir );
			$fn = md5( $entry['id'] ) . '.jpg';
			if ( false !== file_put_contents( $dir . '/' . $fn, $bin ) ) { // phpcs:ignore WordPress.WP.AlternativeFunctions
				$entry['thumb'] = $up['baseurl'] . '/lk-cfiles/' . (int) $c->id . '/' . $fn;
			}
		}
	}
	$list   = lk_cfiles( $c->id );
	$list[] = $entry;
	update_option( 'lk_cfiles_' . (int) $c->id, array_slice( $list, -2000 ), false );
	if ( $r['token'] ) {
		lk_notify_cfile_once( $c );
	}
	return array( 'ok' => true, 'name' => $entry['name'] );
}

/** Avisa a equipe (no máximo uma vez por hora) quando o cliente manda arquivos pelo link. */
function lk_notify_cfile_once( $c ) {
	$k = 'lk_cfnotify_' . $c->id;
	if ( get_transient( $k ) ) {
		return;
	}
	set_transient( $k, 1, HOUR_IN_SECONDS );
	if ( is_email( lk_setting( 'email' ) ) ) {
		lk_mail( lk_setting( 'email' ), 'Fotos novas de ' . lk_client_label( $c ), 'O cliente enviou arquivos', esc_html( lk_client_label( $c ) ) . ' mandou fotos ou arquivos pelo link. Já estão no Drive e na ficha do cliente.', array(), 'Abrir o cliente', lk_panel_url( 'cliente', $c->id ) );
	}
}

function lk_do_cfile_remove() {
	lk_require( 'clientes' );
	$cid  = lk_in( 'client_id', 'int' );
	$id   = lk_in( 'file_id' );
	$list = array_values( array_filter( lk_cfiles( $cid ), function ( $f ) use ( $id ) { return $f['id'] !== $id; } ) );
	update_option( 'lk_cfiles_' . $cid, $list, false );
	lk_back( 'Tirei da lista. O arquivo continua guardado no Drive.' );
}

/* -----------------------------------------------------------------------
 * Página pública /fotos/<token>/
 * -------------------------------------------------------------------- */

add_action(
	'init',
	function () {
		add_rewrite_rule( '^fotos/([A-Za-z0-9]+)/?$', 'index.php?lk_route=photos&lk_token=$matches[1]', 'top' );
	}
);

add_action(
	'template_redirect',
	function () {
		if ( 'photos' !== get_query_var( 'lk_route' ) ) {
			return;
		}
		nocache_headers();
		header( 'X-Robots-Tag: noindex, nofollow', true );
		$token = sanitize_text_field( get_query_var( 'lk_token' ) );
		$rows  = $token ? lk_rows( 'clients', 'photos_token = %s', array( $token ) ) : array();
		if ( ! $rows ) {
			lk_render( 'public/indisponivel' );
		}
		lk_render( 'public/fotos', array( 'c' => $rows[0] ) );
	},
	0
);

/* -----------------------------------------------------------------------
 * Cartão na ficha do cliente
 * -------------------------------------------------------------------- */

function lk_client_files_html( $c ) {
	$files   = array_reverse( lk_cfiles( $c->id ) );
	$folders = lk_cfile_folders( $c->id );
	$link    = lk_photos_url( $c );
	$drive   = function_exists( 'lk_google_connected' ) && lk_google_connected();
	$fl      = $drive ? lk_drive_folder_link( lk_cfile_path( $c ) ) : '';
	$by      = array();
	foreach ( $files as $f ) {
		$by[ $f['folder'] ? $f['folder'] : 'Geral' ][] = $f;
	}
	$msg = 'Olá' . ( $c->name ? ', ' . strtok( $c->name, ' ' ) : '' ) . '! Aqui está o link para você enviar as suas fotos e arquivos para a ' . lk_setting( 'empresa' ) . '. Se for de um evento, dá para separar por pasta: ' . $link;
	ob_start();
	?>
	<section class="card cf" id="arquivos" data-cfiles data-rest="<?php echo esc_url( rest_url( 'lk/v1/cfile/' ) ); ?>" data-nonce="<?php echo esc_attr( wp_create_nonce( 'wp_rest' ) ); ?>" data-client="<?php echo (int) $c->id; ?>">
		<div class="card-head"><h3>Fotos e arquivos <small class="muted">(<?php echo (int) count( $files ); ?>)</small></h3><?php if ( $fl ) : ?><a class="btn btn--ghost btn--sm" href="<?php echo esc_url( $fl ); ?>" target="_blank" rel="noopener">Abrir no Drive</a><?php endif; ?></div>
		<p class="muted small"><?php echo $drive ? 'Tudo que subir aqui vai para o Google Drive, na pasta Fotos do cliente.' : 'O Google Drive não está conectado: os arquivos ficam guardados no site. Conecte em Configurações para irem também para o Drive.'; ?></p>
		<div class="cf-row">
			<label class="field"><span>Pasta</span>
				<select data-cf-folder>
					<option value="">Geral</option>
					<?php foreach ( $folders as $fo ) : ?><option value="<?php echo esc_attr( $fo ); ?>"><?php echo esc_html( $fo ); ?></option><?php endforeach; ?>
					<option value="__novo">+ Nova pasta (evento, campanha…)</option>
				</select></label>
			<label class="field" data-cf-new hidden><span>Nome da pasta</span><input type="text" maxlength="60" placeholder="Ex.: Evento de lançamento"></label>
		</div>
		<label class="cf-drop" data-cf-drop><input type="file" multiple data-cf-input hidden><strong>Arraste as fotos aqui</strong><span class="muted small">ou clique para escolher. Fotos, vídeos, PDF e arquivos de design.</span></label>
		<div class="cf-up" data-cf-list></div>
		<div class="cf-link">
			<strong>Link para o cliente mandar as fotos</strong>
			<div class="copy-row"><input type="text" readonly value="<?php echo esc_attr( $link ); ?>" onclick="this.select()"><button type="button" class="btn btn--ghost" data-copy="<?php echo esc_attr( $link ); ?>"><?php echo lk_icon( 'copiar', 16 ); // phpcs:ignore ?><span>Copiar</span></button>
			<?php if ( $c->whatsapp ) : ?><a class="btn btn--wa" target="_blank" rel="noopener" href="<?php echo esc_url( lk_wa_link( $c->whatsapp, $msg ) ); ?>"><?php echo lk_icon( 'whatsapp', 16 ); // phpcs:ignore ?><span>Enviar</span></a><?php endif; ?></div>
		</div>
		<?php foreach ( $by as $folder => $items ) : ?>
			<h4 class="cf-folder">📁 <?php echo esc_html( $folder ); ?> <small class="muted">(<?php echo (int) count( $items ); ?>)</small></h4>
			<div class="cf-grid">
				<?php foreach ( $items as $f ) : ?>
					<div class="cf-item">
						<a href="<?php echo esc_url( $f['link'] ); ?>" target="_blank" rel="noopener" class="cf-thumb"><?php echo ! empty( $f['thumb'] ) ? '<img src="' . esc_url( $f['thumb'] ) . '" alt="" loading="lazy">' : '<span>' . esc_html( strtoupper( pathinfo( $f['name'], PATHINFO_EXTENSION ) ) ) . '</span>'; // phpcs:ignore ?></a>
						<small title="<?php echo esc_attr( $f['name'] ); ?>"><?php echo esc_html( mb_strimwidth( $f['name'], 0, 26, '…' ) ); ?></small>
						<small class="muted"><?php echo esc_html( lk_date( $f['at'], 'd/m' ) . ' · ' . $f['by'] ); ?></small>
						<?php lk_action_button( 'cfile_remove', array( 'client_id' => $c->id, 'file_id' => $f['id'] ), '×', 'icon-btn', 'Tirar da lista? O arquivo continua no Drive.' ); ?>
					</div>
				<?php endforeach; ?>
			</div>
		<?php endforeach; ?>
	</section>
	<script src="<?php echo esc_url( LK_URL . 'assets/cliente-arquivos.js?ver=' . LK_VERSION ); ?>"></script>
	<?php
	return ob_get_clean();
}


/** Tipo do arquivo (para o post): imagem ou vídeo. */
function lk_cfile_type( $name ) {
	return preg_match( '/\.(mp4|mov|m4v|webm)$/i', (string) $name ) ? 'video' : 'image';
}

/**
 * Galeria com miniaturas e caixinhas de seleção, por pasta. $name = nome do campo (pick[]).
 */
function lk_cfile_gallery_html( $c, $name = 'pick[]' ) {
	$by = array();
	foreach ( array_reverse( lk_cfiles( $c->id ) ) as $f ) {
		if ( 'image' === lk_cfile_type( $f['name'] ) || 'video' === lk_cfile_type( $f['name'] ) ) {
			$by[ $f['folder'] ? $f['folder'] : 'Geral' ][] = $f;
		}
	}
	if ( ! $by ) {
		return '<p class="muted small">Ainda não tem fotos deste cliente. Suba na ficha do cliente ou mande o link para ele enviar.</p>';
	}
	ob_start();
	foreach ( $by as $folder => $items ) {
		echo '<h4 class="cf-folder">📁 ' . esc_html( $folder ) . ' <small class="muted">(' . (int) count( $items ) . ')</small></h4><div class="cf-grid cf-grid--pick">';
		foreach ( $items as $f ) {
			$ext = strtoupper( pathinfo( $f['name'], PATHINFO_EXTENSION ) );
			echo '<label class="cf-item cf-pick"><input type="checkbox" name="' . esc_attr( $name ) . '" value="' . esc_attr( $f['id'] ) . '" data-name="' . esc_attr( $f['name'] ) . '" data-size="' . (int) $f['size'] . '" data-link="' . esc_url( $f['link'] ) . '"><span class="cf-thumb">' . ( ! empty( $f['thumb'] ) ? '<img src="' . esc_url( $f['thumb'] ) . '" alt="" loading="lazy">' : '<span>' . esc_html( $ext ) . '</span>' ) . '</span><small title="' . esc_attr( $f['name'] ) . '">' . esc_html( mb_strimwidth( $f['name'], 0, 24, '…' ) ) . '</small></label>'; // phpcs:ignore
		}
		echo '</div>';
	}
	return ob_get_clean();
}

/** Planejamento: cria um post já com as fotos escolhidas do cliente. */
function lk_do_cfile_to_post() {
	lk_require( 'conteudo' );
	$c = lk_get( 'clients', lk_in( 'client_id', 'int' ) );
	if ( ! $c ) {
		lk_back( 'Cliente não encontrado.', 'erro' );
	}
	$ids   = array_map( 'sanitize_text_field', (array) ( $_POST['pick'] ?? array() ) ); // phpcs:ignore WordPress.Security.NonceVerification
	$media = array();
	foreach ( lk_cfiles( $c->id ) as $f ) {
		if ( in_array( $f['id'], $ids, true ) ) {
			$media[] = array( 'id' => $f['id'], 'name' => $f['name'], 'size' => (int) $f['size'], 'link' => $f['link'], 'type' => lk_cfile_type( $f['name'] ) );
		}
	}
	if ( ! $media ) {
		lk_back( 'Marque pelo menos uma foto.', 'erro' );
	}
	$date   = lk_in( 'date', 'date' ) ? lk_in( 'date', 'date' ) : current_time( 'Y-m-d' );
	$sched  = $date . ' 18:00:00';
	$id     = lk_insert(
		'posts',
		array(
			'client_id'      => $c->id,
			'title'          => lk_in( 'title' ) ? lk_in( 'title' ) : 'Post com ' . count( $media ) . ' foto(s) do cliente',
			'caption'        => '',
			'format'         => count( $media ) > 1 ? 'carrossel' : ( 'video' === $media[0]['type'] ? 'reels' : 'foto' ),
			'vkind'          => 'reel',
			'networks'       => 'instagram,facebook',
			'scheduled_at'   => $sched,
			'designer_id'    => (int) $c->designer_id,
			'social_id'      => (int) $c->social_id,
			'atendimento_id' => (int) $c->atendimento_id,
			'revisor_id'     => (int) $c->revisor_id,
			'deadlines'      => lk_prazos_from_form( $sched ),
			'stage'          => lk_stage_for( 'planejamento' ),
			'approval_token' => strtolower( wp_generate_password( 24, false ) ),
			'created_by'     => get_current_user_id(),
			'media'          => wp_json_encode( $media ),
		)
	);
	lk_post_log( $id, 'Post criado com ' . count( $media ) . ' arquivo(s) das fotos do cliente.' );
	lk_sheet_sync( $id );
	do_action( 'lk_post_saved', $id );
	lk_back( 'Post criado com as fotos. Escreva a legenda e ajuste o dia.', 'ok', lk_in( 'volta_url', 'url' ) ? lk_in( 'volta_url', 'url' ) : lk_panel_url( 'post', $id ) );
}

/** Cartão do planejamento: fotos do cliente com miniaturas, Drive e "criar post". */
function lk_plan_files_html( $c, $here, $ym ) {
	$fl = function_exists( 'lk_google_connected' ) && lk_google_connected() ? lk_drive_folder_link( lk_cfile_path( $c ) ) : '';
	ob_start();
	?>
	<details class="card cf">
		<summary class="card-head" style="cursor:pointer"><h3>📷 Fotos do cliente <small class="muted">(<?php echo (int) count( lk_cfiles( $c->id ) ); ?>)</small></h3><span class="muted small">escolha e já monte o post</span></summary>
		<div class="row-btns" style="margin:10px 0"><?php if ( $fl ) : ?><a class="btn btn--ghost btn--sm" href="<?php echo esc_url( $fl ); ?>" target="_blank" rel="noopener">Abrir pasta no Drive</a><?php endif; ?><a class="btn btn--ghost btn--sm" href="<?php echo esc_url( lk_panel_url( 'cliente', $c->id ) . '#arquivos' ); ?>">Subir ou pedir fotos</a></div>
		<?php lk_form( 'cfile_to_post', 'stack' ); ?>
			<input type="hidden" name="client_id" value="<?php echo (int) $c->id; ?>">
			<input type="hidden" name="volta_url" value="<?php echo esc_attr( $here ); ?>">
			<?php echo lk_cfile_gallery_html( $c ); // phpcs:ignore WordPress.Security.EscapeOutput ?>
			<div class="grid-3">
				<?php lk_input( 'title', 'Título do post (opcional)', '', 'text', 'placeholder="Ex.: Bastidores do evento"' ); ?>
				<?php lk_input( 'date', 'Dia', gmdate( 'Y-m-d', strtotime( $ym . '-01' ) ), 'date' ); ?>
				<div class="form-actions"><button type="submit" class="btn btn--primary">Criar post com as fotos marcadas</button></div>
			</div>
		</form>
	</details>
	<?php
	return ob_get_clean();
}

/** Tela do post: escolher fotos do cliente para a arte (entram na lista e é só salvar). */
function lk_post_picker_html( $c ) {
	ob_start();
	?>
	<details class="cf cf-picker" data-cf-picker>
		<summary class="btn btn--ghost btn--sm" style="display:inline-flex;cursor:pointer">📁 Escolher das fotos do cliente</summary>
		<div class="cf-picker-body">
			<?php echo lk_cfile_gallery_html( $c, 'pickp[]' ); // phpcs:ignore WordPress.Security.EscapeOutput ?>
			<button type="button" class="btn btn--primary btn--sm" data-cf-use>Usar as selecionadas</button>
		</div>
	</details>
	<?php
	return ob_get_clean();
}


/** Bloco "Fotos do cliente" dentro do formulário de novo post: marcar as fotos que entram no post. */
function lk_form_picker_html( $client_id ) {
	$c = $client_id ? lk_get( 'clients', $client_id ) : null;
	ob_start();
	?>
	<details class="cf cf-picker cf-formpick" data-cf-formpick data-rest="<?php echo esc_url( rest_url( 'lk/v1/cfile/gallery' ) ); ?>" data-nonce="<?php echo esc_attr( wp_create_nonce( 'wp_rest' ) ); ?>">
		<summary class="btn btn--ghost btn--sm" style="display:inline-flex;cursor:pointer">📷 Fotos do cliente: escolher para este post</summary>
		<div class="cf-picker-body" data-cf-formpick-body><?php echo $c ? lk_cfile_gallery_html( $c ) : '<p class="muted small">Escolha o cliente para ver as fotos dele.</p>'; // phpcs:ignore WordPress.Security.EscapeOutput ?></div>
	</details>
	<?php
	return ob_get_clean();
}

add_action(
	'rest_api_init',
	function () {
		register_rest_route( 'lk/v1', '/cfile/gallery', array( 'methods' => 'GET', 'permission_callback' => function () { return is_user_logged_in() && lk_is_team(); }, 'callback' => function ( WP_REST_Request $r ) {
			$c = lk_get( 'clients', absint( $r['client'] ) );
			return array( 'html' => $c ? lk_cfile_gallery_html( $c ) : '<p class="muted small">Escolha o cliente para ver as fotos dele.</p>' );
		} ) );
	}
);

/** Fotos marcadas no formulário do post (pick[]) viram arte do post. */
function lk_cfile_pick_media( $client_id ) {
	$ids   = array_map( 'sanitize_text_field', (array) ( $_POST['pick'] ?? array() ) ); // phpcs:ignore WordPress.Security.NonceVerification
	$media = array();
	if ( ! $ids ) {
		return $media;
	}
	foreach ( lk_cfiles( $client_id ) as $f ) {
		if ( in_array( $f['id'], $ids, true ) ) {
			$media[] = array( 'id' => $f['id'], 'name' => $f['name'], 'size' => (int) $f['size'], 'link' => $f['link'], 'type' => lk_cfile_type( $f['name'] ) );
		}
	}
	return $media;
}

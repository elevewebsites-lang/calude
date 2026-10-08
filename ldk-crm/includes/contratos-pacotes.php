<?php
/**
 * Contratos (pacotes): catálogo de pacotes que preenche serviço, valor e artes; contrato já assinado (upload) no cadastro
 * do cliente, na ficha e na lista central; o arquivo vai para a área do cliente e para o Drive.
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function lk_default_packages() {
	return "Essencial | 990 | 12 | Planejamento mensal de conteúdo; Criação de artes e legendas; Programação das postagens; Relatório mensal de resultados | 0\nCrescimento | 1790 | 16 | Planejamento mensal de conteúdo; Criação de artes e legendas; Reels e vídeos curtos; Programação das postagens; Relatório mensal de resultados | 4\nCompleto | 2890 | 20 | Planejamento mensal de conteúdo; Criação de artes e legendas; Reels e vídeos curtos; Programação das postagens; Gestão de tráfego pago (Meta Ads); Relatório mensal de resultados | 8";
}

/** Pacotes de Configurações → Contrato. Linha: Nome | valor mensal | artes por mês | serviço 1; serviço 2 */
function lk_packages() {
	$raw = trim( (string) lk_setting( 'pacotes' ) );
	$raw = '' !== $raw ? $raw : lk_default_packages();
	$out = array();
	foreach ( preg_split( '/\r\n|\r|\n/', $raw ) as $line ) {
		$p = array_map( 'trim', explode( '|', $line ) );
		if ( '' === $p[0] ) {
			continue;
		}
		$val = isset( $p[1] ) ? (float) str_replace( array( '.', ',' ), array( '', '.' ), preg_replace( '/[^\d.,]/', '', $p[1] ) ) : 0;
		$out[ $p[0] ] = array(
			'name'     => $p[0],
			'value'    => $val,
			'arts'     => isset( $p[2] ) ? (int) $p[2] : 0,
			'services' => isset( $p[3] ) ? array_values( array_filter( array_map( 'trim', explode( ';', $p[3] ) ) ) ) : array(),
			'videos'   => isset( $p[4] ) ? (int) $p[4] : 0,
		);
	}
	return $out;
}

/**
 * Seletor de pacote. Ao escolher, preenche os campos do mesmo formulário que existirem
 * (monthly_value / monthly_fee, posts_quota, services). $selected = pacote já gravado.
 */
function lk_package_picker( $selected = '', $label = 'Pacote' ) {
	$pk = lk_packages();
	if ( ! $pk ) {
		return;
	}
	echo '<label class="field"><span>' . esc_html( $label ) . '</span><select name="package" data-lk-package><option value="">— Personalizado (sem pacote) —</option>';
	foreach ( $pk as $p ) {
		$can = lk_can( 'financeiro' );
		echo '<option value="' . esc_attr( $p['name'] ) . '" data-v="' . ( $can ? esc_attr( number_format( $p['value'], 2, ',', '.' ) ) : '' ) . '" data-a="' . (int) $p['arts'] . '" data-vd="' . (int) $p['videos'] . '" data-s="' . esc_attr( implode( "\n", $p['services'] ) ) . '"' . selected( $selected, $p['name'], false ) . '>' . esc_html( $p['name'] . ( $can && $p['value'] > 0 ? ' · ' . lk_money( $p['value'] ) . '/mês' : '' ) . ( $p['arts'] ? ' · ' . $p['arts'] . ' artes' : '' ) . ( $p['videos'] ? ' · ' . $p['videos'] . ' vídeos' : '' ) ) . '</option>';
	}
	echo '</select></label>';
	static $js = false;
	if ( ! $js ) {
		$js = true;
		echo '<script>document.addEventListener("change",function(e){var s=e.target;if(!s.matches||!s.matches("select[data-lk-package]"))return;var o=s.options[s.selectedIndex],f=s.form;if(!f||!o.value)return;var set=function(n,v){var i=f.querySelector("[name="+n+"]");if(i)i.value=v;};if(o.dataset.v){set("monthly_value",o.dataset.v);set("monthly_fee",o.dataset.v);}set("posts_quota",o.dataset.a);set("videos_quota",o.dataset.vd);set("services",o.dataset.s);});</script>';
	}
}

/* -----------------------------------------------------------------------
 * Contrato já assinado (upload)
 * -------------------------------------------------------------------- */

/** Nome de arquivo difícil de adivinhar: o contrato não fica em endereço previsível. */
function lk_contract_private_name( $dir, $name, $ext ) {
	return 'contrato-' . strtolower( wp_generate_password( 24, false ) ) . '.' . $ext;
}

function lk_contract_upload_form( $client = null, $id = 'importar' ) {
	lk_form( 'contract_import', 'stack', true );
	if ( $client ) {
		echo '<input type="hidden" name="client_id" value="' . (int) $client->id . '">';
	} else {
		lk_select( 'client_id', 'Cliente', lk_client_options( 'Escolha o cliente…' ), '', 'required' );
	}
	?>
	<p class="muted small">Para contratos assinados fora da plataforma (papel, PDF, outro sistema). O arquivo fica na área do cliente e no Google Drive dele.</p>
	<label class="field"><span>Arquivo do contrato (PDF, JPG ou PNG, até 15 MB)</span><input type="file" name="contract_file" accept=".pdf,image/png,image/jpeg" required></label>
	<?php lk_input( 'title', 'Título', 'Contrato de prestação de serviços (assinado)' ); ?>
	<?php lk_package_picker(); ?>
	<div class="grid-3">
		<?php lk_input( 'monthly_value', 'Valor mensal (R$)', '', 'text', 'inputmode="decimal" data-money' ); ?>
		<?php lk_input( 'months', 'Duração (meses)', 12, 'number', 'min="1" max="60"' ); ?>
		<?php lk_input( 'start_date', 'Início', lk_today(), 'date' ); ?>
		<?php lk_input( 'signed_date', 'Assinado em', lk_today(), 'date' ); ?>
		<?php lk_input( 'posts_quota', 'Artes por mês', '', 'number', 'min="0"' ); ?>
		<?php lk_input( 'due_day', 'Dia do vencimento', 10, 'number', 'min="1" max="28"' ); ?>
	</div>
	<?php lk_input( 'services', 'Serviços do contrato (um por linha)', '', 'textarea', 'rows="3"' ); ?>
	<div class="form-actions"><button type="button" class="btn btn--ghost" data-close>Cancelar</button><button type="submit" class="btn btn--primary">Salvar contrato assinado</button></div>
	</form>
	<?php
}

/**
 * Cria o contrato assinado a partir do arquivo enviado ($_FILES[$field]) e dos campos do formulário.
 * Devolve o id do contrato ou WP_Error.
 */
function lk_contract_import( $client, $field = 'contract_file' ) {
	if ( empty( $_FILES[ $field ]['name'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification
		return new WP_Error( 'lk', 'Escolha o arquivo do contrato.' );
	}
	$f = $_FILES[ $field ]; // phpcs:ignore WordPress.Security.NonceVerification,WordPress.Security.ValidatedSanitizedInput
	if ( ! empty( $f['size'] ) && $f['size'] > 15 * MB_IN_BYTES ) {
		return new WP_Error( 'lk', 'O arquivo passa de 15 MB.' );
	}
	$ext = strtolower( pathinfo( (string) $f['name'], PATHINFO_EXTENSION ) );
	if ( ! in_array( $ext, array( 'pdf', 'jpg', 'jpeg', 'png' ), true ) ) {
		return new WP_Error( 'lk', 'Envie o contrato em PDF, JPG ou PNG.' );
	}
	require_once ABSPATH . 'wp-admin/includes/file.php';
	require_once ABSPATH . 'wp-admin/includes/image.php';
	require_once ABSPATH . 'wp-admin/includes/media.php';
	$up = wp_handle_upload(
		$f,
		array(
			'test_form'                => false,
			'mimes'                    => array( 'pdf' => 'application/pdf', 'jpg|jpeg' => 'image/jpeg', 'png' => 'image/png' ),
			'unique_filename_callback' => function ( $dir, $name, $e ) use ( $ext ) {
				return lk_contract_private_name( $dir, $name, $ext );
			},
		)
	);
	if ( isset( $up['error'] ) ) {
		return new WP_Error( 'lk', 'Não foi possível enviar o arquivo: ' . $up['error'] );
	}
	$title = lk_in( 'title' ) ? lk_in( 'title' ) : 'Contrato de prestação de serviços (assinado)';
	$att   = wp_insert_attachment( array( 'post_mime_type' => $up['type'], 'post_title' => $title . ' · ' . lk_client_label( $client ), 'post_status' => 'inherit' ), $up['file'] );
	$pk    = lk_in( 'package' );
	$value = lk_in( 'monthly_value', 'money' );
	$quota = lk_in( 'posts_quota', 'int' );
	$signd = lk_in( 'signed_date', 'date' ) ? lk_in( 'signed_date', 'date' ) : lk_today();
	$start = lk_in( 'start_date', 'date' ) ? lk_in( 'start_date', 'date' ) : $signd;
	$srv   = lk_in( 'services', 'textarea' );
	$pks   = lk_packages();
	if ( $pk && isset( $pks[ $pk ] ) ) {
		$value = $value > 0 ? $value : $pks[ $pk ]['value'];
		$quota = $quota > 0 ? $quota : $pks[ $pk ]['arts'];
		$srv   = $srv ? $srv : implode( "\n", $pks[ $pk ]['services'] );
	}
	$due = min( 28, max( 1, lk_in( 'due_day', 'int' ) ? lk_in( 'due_day', 'int' ) : 10 ) );
	$id  = lk_insert(
		'contracts',
		array(
			'client_id'     => $client->id,
			'title'         => $title,
			'services'      => $srv,
			'package'       => $pk,
			'monthly_value' => $value,
			'months'        => max( 1, lk_in( 'months', 'int' ) ),
			'start_date'    => $start,
			'due_day'       => $due,
			'posts_quota'   => $quota,
			'file_url'      => $up['url'],
			'file_att'      => (int) $att,
			'imported'      => 1,
			'status'        => 'concluido',
			'token'         => strtolower( wp_generate_password( 28, false ) ),
			'body'          => 'Contrato assinado fora da plataforma e anexado em ' . lk_date( lk_today() ) . ".\n\nO arquivo original está disponível no botão \"Abrir arquivo\".",
			'signed_at'     => $signd . ' 12:00:00',
			'created_by'    => get_current_user_id(),
		)
	);
	// Ficha do cliente: mensalidade, vencimento e pacote de artes seguem o contrato.
	$upd = array( 'due_day' => $due );
	if ( $value > 0 ) {
		$upd['monthly_fee'] = $value;
	}
	if ( $quota > 0 ) {
		$upd['posts_quota'] = $quota;
	}
	lk_update( 'clients', $client->id, $upd );
	if ( $att ) {
		lk_drive_queue( $att, array( 'Clientes', lk_client_label( $client ), 'Contratos' ) );
	}
	return $id;
}

function lk_do_contract_import() {
	lk_require( 'clientes' );
	$client = lk_get( 'clients', lk_in( 'client_id', 'int' ) );
	if ( ! $client ) {
		lk_back( 'Escolha o cliente.', 'erro' );
	}
	$id = lk_contract_import( $client );
	if ( is_wp_error( $id ) ) {
		lk_back( $id->get_error_message(), 'erro' );
	}
	lk_back( 'Contrato assinado salvo. Ele já aparece na área do cliente e vai para a pasta dele no Drive.', 'ok', lk_panel_url( 'cliente', $client->id ) . '#contratos' );
}

/** Cadastro de cliente com contrato já assinado (campo opcional no formulário). */
add_action(
	'lk_client_created',
	function ( $client_id ) {
		if ( empty( $_FILES['contract_file']['name'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification
			return;
		}
		$client = lk_get( 'clients', $client_id );
		if ( $client ) {
			$r = lk_contract_import( $client );
			if ( is_wp_error( $r ) ) {
				lk_flash( 'Cliente criado, mas o contrato não foi salvo: ' . $r->get_error_message(), 'erro' );
			}
		}
	}
);

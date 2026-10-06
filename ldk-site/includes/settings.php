<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_action(
	'admin_menu',
	function () {
		add_menu_page( 'LDK Site', 'LDK Site', 'manage_options', 'ldk-site', 'ldk_site_settings_page', 'dashicons-welcome-widgets-menus', 58 );
	}
);

function ldk_site_settings_page() {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}
	if ( isset( $_POST['ldk_save'] ) && check_admin_referer( 'ldk_site_save' ) ) {
		$new = array();
		foreach ( array( 'logo', 'instagram', 'painel', 'crm_url' ) as $k ) {
			$new[ $k ] = isset( $_POST[ $k ] ) ? esc_url_raw( wp_unslash( $_POST[ $k ] ) ) : '';
		}
		foreach ( array( 'whatsapp', 'email', 'cidade' ) as $k ) {
			$new[ $k ] = isset( $_POST[ $k ] ) ? sanitize_text_field( wp_unslash( $_POST[ $k ] ) ) : '';
		}
		$new['intro'] = empty( $_POST['intro'] ) ? '0' : '1';
		update_option( 'ldk_site', $new );
		echo '<div class="notice notice-success"><p>Salvo.</p></div>';
	}
	if ( isset( $_POST['ldk_reinstall'] ) && check_admin_referer( 'ldk_site_save' ) ) {
		ldk_site_install( true );
		echo '<div class="notice notice-success"><p>Páginas, menu e imagens conferidos. O que você editou no Elementor foi mantido.</p></div>';
	}
	$f = function ( $k, $label, $hint = '' ) {
		echo '<tr><th><label>' . esc_html( $label ) . '</label></th><td><input class="regular-text" style="width:100%;max-width:520px" name="' . esc_attr( $k ) . '" value="' . esc_attr( ldk_site_opt( $k ) ) . '">' . ( $hint ? '<p class="description">' . esc_html( $hint ) . '</p>' : '' ) . '</td></tr>';
	};
	echo '<div class="wrap"><h1>LDK Site</h1><form method="post">';
	wp_nonce_field( 'ldk_site_save' );
	echo '<table class="form-table">';
	$f( 'crm_url', 'Endereço do CRM (formulários)', 'URL do painel com a chave, ex.: https://painel…/wp-json/lk/v1/site?chave=…  Os leads dos formulários vão para o Funil.' );
	$f( 'whatsapp', 'WhatsApp (só números, com 55)' );
	$f( 'instagram', 'Instagram (link)' );
	$f( 'email', 'E-mail para avisos (plano B)' );
	$f( 'cidade', 'Cidade' );
	$f( 'painel', 'Link de entrada do painel do cliente' );
	$f( 'logo', 'Logo (versão clara)' );
	echo '<tr><th>Animação de entrada</th><td><label><input type="checkbox" name="intro" value="1" ' . checked( ldk_site_opt( 'intro' ), '1', false ) . '> Mostrar a entrada com a logo (uma vez por visita)</label></td></tr></table>';
	echo '<p><button class="button button-primary" name="ldk_save" value="1">Salvar</button> ';
	echo '<button class="button" name="ldk_reinstall" value="1">Recriar páginas que faltam</button> ';
	echo '<button class="button" name="ldk_relayout" value="1" onclick="return confirm(\'Substitui o layout das páginas do plugin pelo mais novo. Continuar?\')">Aplicar layout novo</button> ';
	echo '<button class="button" name="ldk_images" value="1">Baixar imagens dos artigos</button></p></form>';
	echo '<h2>Como editar</h2><p>Abra qualquer página em <b>Páginas → Editar com Elementor</b>. Os blocos da LDK estão na categoria <b>LDK</b>. O menu fica em <b>Aparência → Menus</b> (LDK Principal) e os artigos em <b>Posts</b>.</p></div>';
}

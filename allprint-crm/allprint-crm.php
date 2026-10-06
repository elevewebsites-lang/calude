<?php
/**
 * Plugin Name: Allprint CRM
 * Description: Modelo base de CRM (painel + área do cliente + orçamentos com pagamento + pedidos em Kanban + financeiro), com segurança, tema e módulos opcionais. Identidade em identity.php.
 * Version: 1.2.0
 * Author: Eleve Websites
 * Author URI: https://elevewebsites.com.br
 * Text Domain: allprint-crm
 * Requires at least: 6.0
 * Requires PHP: 7.4
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'AP_VERSION', '1.2.0' );
define( 'AP_FILE', __FILE__ );
define( 'AP_DIR', plugin_dir_path( __FILE__ ) );
define( 'AP_URL', plugin_dir_url( __FILE__ ) );

require_once AP_DIR . 'identity.php';
require_once AP_DIR . 'includes/helpers.php';
require_once AP_DIR . 'includes/brand.php';
require_once AP_DIR . 'includes/security.php';
require_once AP_DIR . 'includes/db.php';
require_once AP_DIR . 'includes/ui.php';
require_once AP_DIR . 'includes/router.php';
require_once AP_DIR . 'includes/actions.php';
require_once AP_DIR . 'includes/api.php';
require_once AP_DIR . 'includes/cron.php';
require_once AP_DIR . 'includes/infinitepay.php';
require_once AP_DIR . 'includes/emails.php';
require_once AP_DIR . 'includes/leads.php';
require_once AP_DIR . 'includes/site-form.php';
require_once AP_DIR . 'includes/health.php';
require_once AP_DIR . 'includes/board.php';
require_once AP_DIR . 'includes/recurring.php';
require_once AP_DIR . 'includes/pricing.php';
require_once AP_DIR . 'includes/quotes.php';
require_once AP_DIR . 'includes/orders.php';
require_once AP_DIR . 'includes/drive.php';
require_once AP_DIR . 'includes/modules.php';
require_once AP_DIR . 'includes/feedback.php';
ap_load_modules();
require_once AP_DIR . 'includes/grafica.php';
require_once AP_DIR . 'includes/bigupload.php';
require_once AP_DIR . 'includes/chat.php';
require_once AP_DIR . 'includes/broadcast.php';
require_once AP_DIR . 'includes/signup.php';
require_once AP_DIR . 'includes/import.php';
require_once AP_DIR . 'includes/estoque.php';
require_once AP_DIR . 'includes/sheets.php';
require_once AP_DIR . 'includes/coupons.php';
require_once AP_DIR . 'includes/paystatus.php';
require_once AP_DIR . 'includes/manual.php';

register_activation_hook( __FILE__, 'ap_activate' );
function ap_activate() {
	ap_install_tables();
	ap_add_roles();
	ap_seed_studio();
	ap_rewrite_rules();
	ap_quote_rewrites();
	add_rewrite_rule( '^cadastro/?$', 'index.php?ap_route=signup', 'top' );
	ap_security_upgrade();
	flush_rewrite_rules();
	if ( ! wp_next_scheduled( 'ap_daily' ) ) {
		wp_schedule_event( strtotime( 'tomorrow 06:00' ), 'daily', 'ap_daily' );
	}
}

register_deactivation_hook( __FILE__, 'ap_deactivate' );
function ap_deactivate() {
	wp_clear_scheduled_hook( 'ap_daily' );
	wp_clear_scheduled_hook( 'ap_hourly' );
	flush_rewrite_rules();
}

// Atualizações: ajusta tabelas e regras de endereço uma vez por versão.
add_action(
	'init',
	function () {
		if ( get_option( 'ap_version' ) !== AP_VERSION ) {
			ap_install_tables();
			ap_add_roles();
			ap_seed_studio();
			ap_logo_reset_once();
			ap_security_upgrade();
			flush_rewrite_rules();
			update_option( 'ap_version', AP_VERSION );
		}
		if ( ! wp_next_scheduled( 'ap_hourly' ) ) {
			wp_schedule_event( time() + 300, 'hourly', 'ap_hourly' );
		}
	},
	99
);

/**
 * Primeiros cadastros (só com o módulo de estoque e só se estiver vazio). Ajuste por projeto.
 */
function ap_seed_studio() {
	// Gráfica: não há cadastros de exemplo (impressora 3D, sacolinha…). Limpa o que a base antiga tenha criado.
	if ( get_option( 'ap_seed_cleaned' ) || ! function_exists( 'ap_rows' ) ) {
		return;
	}
	foreach ( ap_rows( 'supplies', "code = '' AND qty = 0" ) as $s ) {
		if ( in_array( $s->name, array( 'Sacolinha', 'Cartão de agradecimento', 'Caixa de envio pequena', 'Plástico bolha', 'Etiqueta' ), true ) ) {
			ap_delete( 'supplies', $s->id );
		}
	}
	foreach ( ap_rows( 'printers', "name = 'A1' AND model = 'Bambu Lab A1'" ) as $p ) {
		ap_delete( 'printers', $p->id );
	}
	update_option( 'ap_seed_cleaned', 1, false );
	update_option( 'ap_seeded', 1, false );
}

/**
 * A logo antiga guardada nas configurações (versão 1.1) podia ser colorida e era usada em fundo escuro (contraste zero).
 * Uma vez só, limpa as duas; o sistema passa a escolher a logo certa de cada fundo. Quem quiser, envia logos próprias de novo.
 */
function ap_logo_reset_once() {
	if ( get_option( 'ap_logo_reset_120' ) ) {
		return;
	}
	$saved = get_option( 'ap_settings', array() );
	if ( is_array( $saved ) && ( ! empty( $saved['logo'] ) || ! empty( $saved['logo_cor'] ) ) ) {
		unset( $saved['logo'], $saved['logo_cor'] );
		update_option( 'ap_settings', $saved );
	}
	update_option( 'ap_logo_reset_120', 1, false );
}

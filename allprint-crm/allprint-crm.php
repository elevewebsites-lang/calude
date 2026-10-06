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
	if ( get_option( 'ap_seeded' ) || ! ap_module( 'estoque' ) ) {
		return;
	}
	if ( ! ap_rows( 'printers', '1=1' ) ) {
		ap_insert(
			'printers',
			array(
				'name'        => 'A1',
				'model'       => 'Bambu Lab A1',
				'watts'       => 95,
				'price'       => 3500,
				'life_hours'  => 5000,
				'maint_every' => 500,
			)
		);
	}
	if ( ! ap_rows( 'supplies', '1=1' ) ) {
		foreach ( array( array( 'Sacolinha', 'un', 1 ), array( 'Cartão de agradecimento', 'un', 1 ), array( 'Caixa de envio pequena', 'un', 0 ), array( 'Plástico bolha', 'm', 0 ), array( 'Etiqueta', 'un', 0 ) ) as $s ) {
			ap_insert( 'supplies', array( 'name' => $s[0], 'unit' => $s[1], 'per_order' => $s[2] ) );
		}
	}
	update_option( 'ap_seeded', 1, false );
}

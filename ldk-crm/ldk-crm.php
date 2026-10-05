<?php
/**
 * Plugin Name: LDK CRM
 * Description: Modelo base de CRM (painel + área do cliente + orçamentos com pagamento + pedidos em Kanban + financeiro), com segurança, tema e módulos opcionais. Identidade em identity.php.
 * Version: 1.16.0
 * Author: Eleve Websites
 * Author URI: https://elevewebsites.com.br
 * Text Domain: ldk-crm
 * Requires at least: 6.0
 * Requires PHP: 7.4
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'LK_VERSION', '1.16.0' );
define( 'LK_FILE', __FILE__ );
define( 'LK_DIR', plugin_dir_path( __FILE__ ) );
define( 'LK_URL', plugin_dir_url( __FILE__ ) );

require_once LK_DIR . 'identity.php';
require_once LK_DIR . 'includes/helpers.php';
require_once LK_DIR . 'includes/security.php';
require_once LK_DIR . 'includes/db.php';
require_once LK_DIR . 'includes/ui.php';
require_once LK_DIR . 'includes/router.php';
require_once LK_DIR . 'includes/actions.php';
require_once LK_DIR . 'includes/api.php';
require_once LK_DIR . 'includes/cron.php';
require_once LK_DIR . 'includes/infinitepay.php';
require_once LK_DIR . 'includes/emails.php';
require_once LK_DIR . 'includes/leads.php';
require_once LK_DIR . 'includes/site-form.php';
require_once LK_DIR . 'includes/health.php';
require_once LK_DIR . 'includes/board.php';
require_once LK_DIR . 'includes/recurring.php';
require_once LK_DIR . 'includes/pricing.php';
require_once LK_DIR . 'includes/quotes.php';
require_once LK_DIR . 'includes/orders.php';
require_once LK_DIR . 'includes/drive.php';
require_once LK_DIR . 'includes/modules.php';
lk_load_modules();
foreach ( array( 'bigupload', 'chat', 'broadcast', 'content', 'planning', 'briefing', 'social', 'reports', 'ads', 'billing', 'focus', 'google', 'teamchat', 'feedback', 'propostas-painel', 'prazos', 'voz', 'social-mais', 'contratos', 'equipe-cadastro', 'central', 'ldk-ajustes', 'connect-link', 'gamificacao', 'midia', 'planejamento-plus', 'dashboard-ui', 'revisao', 'prospeccao', 'ig-preview', 'metas', 'extras', 'contratos-plus', 'ia', 'dash-widgets' ) as $lk_f ) {
	require_once LK_DIR . 'includes/' . $lk_f . '.php';
}
// Propostas da LDK (plugin LDK Propostas embutido).
require_once LK_DIR . 'modules/propostas/ldk-propostas.php';

register_activation_hook( __FILE__, 'lk_activate' );
function lk_activate() {
	lk_install_tables();
	lk_add_roles();
	lk_seed_studio();
	lk_rewrite_rules();
	lk_quote_rewrites();
	lk_security_upgrade();
	if ( function_exists( 'ldkp_activate' ) ) {
		ldkp_activate();
	}
	flush_rewrite_rules();
	if ( ! wp_next_scheduled( 'lk_daily' ) ) {
		wp_schedule_event( strtotime( 'tomorrow 06:00' ), 'daily', 'lk_daily' );
	}
}

register_deactivation_hook( __FILE__, 'lk_deactivate' );
function lk_deactivate() {
	wp_clear_scheduled_hook( 'lk_daily' );
	wp_clear_scheduled_hook( 'lk_hourly' );
	flush_rewrite_rules();
}

// Atualizações: ajusta tabelas e regras de endereço uma vez por versão.
add_action(
	'init',
	function () {
		if ( get_option( 'lk_version' ) !== LK_VERSION ) {
			lk_install_tables();
			lk_add_roles();
			lk_seed_studio();
			lk_security_upgrade();
			flush_rewrite_rules();
			update_option( 'lk_version', LK_VERSION );
		}
		if ( ! wp_next_scheduled( 'lk_hourly' ) ) {
			wp_schedule_event( time() + 300, 'hourly', 'lk_hourly' );
		}
	},
	99
);

/**
 * Primeiros cadastros (só com o módulo de estoque e só se estiver vazio). Ajuste por projeto.
 */
function lk_seed_studio() {
	if ( get_option( 'lk_seeded' ) || ! lk_module( 'estoque' ) ) {
		return;
	}
	if ( ! lk_rows( 'printers', '1=1' ) ) {
		lk_insert(
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
	if ( ! lk_rows( 'supplies', '1=1' ) ) {
		foreach ( array( array( 'Sacolinha', 'un', 1 ), array( 'Cartão de agradecimento', 'un', 1 ), array( 'Caixa de envio pequena', 'un', 0 ), array( 'Plástico bolha', 'm', 0 ), array( 'Etiqueta', 'un', 0 ) ) as $s ) {
			lk_insert( 'supplies', array( 'name' => $s[0], 'unit' => $s[1], 'per_order' => $s[2] ) );
		}
	}
	update_option( 'lk_seeded', 1, false );
}

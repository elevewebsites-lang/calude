<?php
/**
 * Plugin Name: LDK Propostas
 * Description: Propostas comerciais da LDK Marketing Digital, personalizadas por cliente e nicho, com link próprio e rolagem horizontal.
 * Version: 1.1.1
 * Author: Eleve Websites para LDK Marketing Digital
 * Author URI: https://elevewebsites.com.br
 * Text Domain: ldk-propostas
 * Requires at least: 6.0
 * Requires PHP: 7.4
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'LDKP_VERSION', '1.1.1' );
define( 'LDKP_FILE', __FILE__ );
define( 'LDKP_DIR', plugin_dir_path( __FILE__ ) );
define( 'LDKP_URL', plugin_dir_url( __FILE__ ) );

require_once LDKP_DIR . 'includes/helpers.php';
require_once LDKP_DIR . 'includes/defaults.php';
require_once LDKP_DIR . 'includes/nichos.php';
require_once LDKP_DIR . 'includes/post-types.php';
require_once LDKP_DIR . 'includes/admin.php';
require_once LDKP_DIR . 'includes/front.php';
require_once LDKP_DIR . 'includes/painel.php';

register_activation_hook( __FILE__, 'ldkp_activate' );
function ldkp_activate() {
	ldkp_register_post_types();
	ldkp_painel_rewrite();
	ldkp_migrate_defaults();
	ldkp_seed_defaults();
	flush_rewrite_rules();
}

register_deactivation_hook( __FILE__, 'flush_rewrite_rules' );

<?php
/**
 * Plugin Name: LDK Site
 * Description: Site institucional da LDK Marketing Digital em um único plugin: instala páginas, widgets do Elementor Pro, imagens, menu, animações e formulários ligados ao CRM. Sem tema.
 * Version: 1.0.0
 * Author: Eleve Websites
 * Requires PHP: 7.4
 * Text Domain: ldk-site
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'LDK_SITE_VERSION', '1.0.0' );
define( 'LDK_SITE_FILE', __FILE__ );
define( 'LDK_SITE_DIR', plugin_dir_path( __FILE__ ) );
define( 'LDK_SITE_URL', plugin_dir_url( __FILE__ ) );

// Config local (não vai para o Git): define LDK_SITE_CRM_URL e, se quiser, outros padrões.
if ( is_readable( LDK_SITE_DIR . 'config.local.php' ) ) {
	require_once LDK_SITE_DIR . 'config.local.php';
}

foreach ( array( 'helpers', 'schema', 'render', 'forms', 'settings', 'template', 'elementor', 'installer' ) as $ldk_f ) {
	require_once LDK_SITE_DIR . 'includes/' . $ldk_f . '.php';
}

register_activation_hook( __FILE__, 'ldk_site_activate' );

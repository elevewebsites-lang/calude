<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_action(
	'elementor/elements/categories_registered',
	function ( $mgr ) {
		$mgr->add_category( 'ldk', array( 'title' => 'LDK', 'icon' => 'eicon-font' ) );
	}
);

add_action(
	'elementor/widgets/register',
	function ( $widgets ) {
		require_once LDK_SITE_DIR . 'includes/elementor-widgets.php';
		foreach ( array_keys( ldk_site_schema() ) as $key ) {
			$class = 'LDK_Site_W_' . $key;
			if ( class_exists( $class ) ) {
				$widgets->register( new $class() );
			}
		}
	}
);

// Estilos e animações também dentro do editor do Elementor.
add_action(
	'elementor/preview/enqueue_styles',
	function () {
		wp_enqueue_style( 'ldk-site-font', 'https://fonts.googleapis.com/css2?family=Montserrat:wght@400;500;600;700;800&display=swap', array(), null );
		wp_enqueue_style( 'ldk-site', LDK_SITE_URL . 'assets/css/site.css', array(), LDK_SITE_VERSION );
	}
);
add_action(
	'elementor/preview/enqueue_scripts',
	function () {
		wp_enqueue_script( 'ldk-site', LDK_SITE_URL . 'assets/js/site.js', array(), LDK_SITE_VERSION, true );
	}
);

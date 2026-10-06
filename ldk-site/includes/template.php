<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_action(
	'init',
	function () {
		register_nav_menu( 'ldk-main', 'LDK Principal' );
	}
);

/** Páginas do site e artigos usam o modelo do plugin (sem tema). */
function ldk_site_is_ldk_view() {
	if ( is_admin() ) {
		return false;
	}
	if ( is_singular( 'post' ) || is_home() || is_category() || is_tag() || is_search() || is_404() ) {
		return true;
	}
	return is_page() && get_post_meta( get_the_ID(), '_ldk_site_page', true );
}

add_filter(
	'template_include',
	function ( $tpl ) {
		if ( ! ldk_site_is_ldk_view() ) {
			return $tpl;
		}
		if ( isset( $_GET['elementor-preview'] ) ) { // phpcs:ignore
			return $tpl;
		}
		return LDK_SITE_DIR . 'templates/page.php';
	},
	99
);

add_action(
	'wp_enqueue_scripts',
	function () {
		if ( ! ldk_site_is_ldk_view() ) {
			return;
		}
		wp_enqueue_style( 'ldk-site-font', 'https://fonts.googleapis.com/css2?family=Montserrat:wght@400;500;600;700;800&display=swap', array(), null );
		wp_enqueue_style( 'ldk-site', LDK_SITE_URL . 'assets/css/site.css', array(), LDK_SITE_VERSION );
		wp_enqueue_script( 'ldk-site', LDK_SITE_URL . 'assets/js/site.js', array(), LDK_SITE_VERSION, true );
		wp_localize_script(
			'ldk-site',
			'LDKSITE',
			array(
				'lead'  => esc_url_raw( rest_url( 'ldk-site/v1/lead' ) ),
				'intro' => ldk_site_opt( 'intro' ),
				'wa'    => ldk_site_wa( 'Olá! Fiz o diagnóstico de perfil no site da LDK.' ),
			)
		);
	}
);

function ldk_site_nav_html() {
	$h = '';
	if ( has_nav_menu( 'ldk-main' ) ) {
		return wp_nav_menu( array( 'theme_location' => 'ldk-main', 'container' => false, 'menu_class' => 'ldk-nav', 'echo' => false, 'depth' => 1 ) );
	}
	foreach ( array( 'inicio' => 'Início', 'servicos' => 'Serviços', 'painel-do-cliente' => 'Painel do cliente', 'sobre-nos' => 'Sobre nós', 'blog' => 'Blog', 'contato' => 'Contato' ) as $slug => $l ) {
		$h .= '<li><a href="' . esc_url( ldk_site_page_url( $slug ) ) . '">' . esc_html( $l ) . '</a></li>';
	}
	return '<ul class="ldk-nav">' . $h . '</ul>';
}

function ldk_site_header_html() {
	return '<header class="ldk-header" data-header><div class="ldk-hwrap"><a class="brand" href="' . esc_url( home_url( '/' ) ) . '" aria-label="LDK Marketing Digital">' . ldk_site_logo_html( 'ldk-logo' ) . '</a>' .
		'<nav class="ldk-navwrap" id="ldk-nav" aria-label="Principal">' . ldk_site_nav_html() .
		'<a class="ldk-btn sm" href="' . esc_url( ldk_site_page_url( 'diagnostico' ) ) . '"><span>Diagnóstico grátis</span></a></nav>' .
		'<button class="burger" type="button" aria-label="Abrir menu" aria-expanded="false" aria-controls="ldk-nav" data-burger><i></i><i></i><i></i></button></div><div class="progress" data-progress></div></header>';
}

function ldk_site_footer_html() {
	$ig = ldk_site_opt( 'instagram' );
	return '<footer class="ldk-footer"><div class="ldk-wrap"><div class="fgrid"><div class="fb">' . ldk_site_logo_html( 'ldk-logo' ) .
		'<p>Estratégia, criação e tráfego pago para marcas que querem aparecer. ' . esc_html( ldk_site_opt( 'cidade' ) ) . '.</p>' .
		'<div class="soc"><a href="' . esc_url( $ig ) . '" target="_blank" rel="noopener" aria-label="Instagram">' . ldk_site_ico( 'ig' ) . '</a><a href="' . esc_url( ldk_site_wa() ) . '" aria-label="WhatsApp">' . ldk_site_ico( 'wa' ) . '</a></div></div>' .
		'<div><h4>Site</h4>' . ldk_site_nav_html() . '</div>' .
		'<div><h4>Mais</h4><ul class="ldk-nav"><li><a href="' . esc_url( ldk_site_page_url( 'diagnostico' ) ) . '">Diagnóstico de perfil</a></li><li><a href="' . esc_url( ldk_site_page_url( 'links' ) ) . '">Nossos links</a></li><li><a href="' . esc_url( ldk_site_opt( 'painel' ) ) . '">Entrar no painel</a></li></ul></div></div>' .
		'<p class="copy">© ' . gmdate( 'Y' ) . ' LDK Marketing Digital. Todos os direitos reservados.</p></div></footer>' .
		'<a class="ldk-wafab" href="' . esc_url( ldk_site_wa( 'Olá! Vim pelo site da LDK.' ) ) . '" aria-label="Falar no WhatsApp">' . ldk_site_ico( 'wa' ) . '</a>';
}

function ldk_site_intro_html() {
	if ( '1' !== (string) ldk_site_opt( 'intro' ) ) {
		return '';
	}
	return '<div class="ldk-intro" data-intro aria-hidden="true"><div class="in"><div class="halo"></div>' . ldk_site_logo_html( 'ldk-logo' ) . '<div class="line"><i></i></div></div></div>';
}

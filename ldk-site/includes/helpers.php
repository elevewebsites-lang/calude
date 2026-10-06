<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Configuração salva (com padrões). */
function ldk_site_opt( $key, $default = '' ) {
	$o = get_option( 'ldk_site', array() );
	if ( isset( $o[ $key ] ) && '' !== $o[ $key ] ) {
		return $o[ $key ];
	}
	$defs = ldk_site_defaults();
	return isset( $defs[ $key ] ) && '' !== $defs[ $key ] ? $defs[ $key ] : $default;
}

function ldk_site_defaults() {
	return array(
		'logo'      => 'https://ldkmarketingdigital.com.br/wp-content/uploads/2026/09/Screenshot_2026-08-11_at_13.26.35-removebg-preview.webp',
		'whatsapp'  => '5512988257644',
		'instagram' => 'https://www.instagram.com/ldkmarketingdigital/',
		'email'     => '',
		'cidade'    => 'Taubaté · SP',
		'painel'    => 'https://painel.ldkmarketingdigital.com.br/entrar',
		'crm_url'   => defined( 'LDK_SITE_CRM_URL' ) ? LDK_SITE_CRM_URL : '',
		'intro'     => '1',
	);
}

function ldk_site_logo_fallback() {
	return LDK_SITE_URL . 'assets/img/logo-ldk.svg';
}

/** Texto com *destaque* → <span class="hl">. */
function ldk_site_hl( $t ) {
	$t = esc_html( $t );
	return preg_replace( '/\*([^*]+)\*/u', '<span class="hl">$1</span>', $t );
}

function ldk_site_wa( $msg = '' ) {
	$n = preg_replace( '/\D/', '', ldk_site_opt( 'whatsapp' ) );
	return 'https://wa.me/' . $n . ( $msg ? '?text=' . rawurlencode( $msg ) : '' );
}

/** Imagem de um controle (array do Elementor ou URL). */
function ldk_site_img( $v ) {
	if ( is_array( $v ) ) {
		$v = isset( $v['url'] ) ? $v['url'] : '';
	}
	return (string) $v;
}

/** Link de um item: aceita "#slug" para páginas instaladas, "wa" para o WhatsApp. */
function ldk_site_link( $u ) {
	if ( is_array( $u ) ) {
		$u = isset( $u['url'] ) ? $u['url'] : '';
	}
	$u = (string) $u;
	if ( 'wa' === $u || 'whatsapp' === $u ) {
		return ldk_site_wa( 'Olá! Vim pelo site da LDK e quero conversar.' );
	}
	if ( 0 === strpos( $u, 'pg:' ) ) {
		return ldk_site_page_url( substr( $u, 3 ) );
	}
	return $u;
}

function ldk_site_page_url( $slug ) {
	$ids = get_option( 'ldk_site_pages', array() );
	if ( 'inicio' === $slug && empty( $ids['inicio'] ) ) {
		return home_url( '/' );
	}
	if ( ! empty( $ids[ $slug ] ) ) {
		$u = get_permalink( (int) $ids[ $slug ] );
		if ( $u ) {
			return $u;
		}
	}
	return home_url( '/' . $slug . '/' );
}

function ldk_site_ico( $name ) {
	static $i = null;
	if ( null === $i ) {
		$i = array(
			'social'  => '<path d="M4 5h16v11H8l-4 4V5z"/><path d="M8 9h8M8 12h5"/>',
			'trafego' => '<path d="M4 19V9M10 19V5M16 19v-7M22 19H2"/><path d="m4 7 6-4 6 6 5-4"/>',
			'video'   => '<rect x="3" y="6" width="13" height="12" rx="2"/><path d="m16 10 5-3v10l-5-3z"/>',
			'site'    => '<rect x="3" y="4" width="18" height="14" rx="2"/><path d="M3 9h18M8 21h8M12 18v3"/>',
			'brand'   => '<path d="M12 3 4 7v6c0 4 3.5 6.8 8 8 4.5-1.2 8-4 8-8V7z"/><path d="m9 12 2 2 4-4"/>',
			'check'   => '<path d="m5 12 4 4 10-10"/>',
			'arrow'   => '<path d="M5 12h14M13 6l6 6-6 6"/>',
			'cal'     => '<rect x="3" y="5" width="18" height="16" rx="2"/><path d="M3 10h18M8 3v4M16 3v4"/>',
			'chat'    => '<path d="M21 12a8 8 0 0 1-11.5 7.2L4 20l1-4.4A8 8 0 1 1 21 12z"/>',
			'doc'     => '<path d="M6 3h9l4 4v14H6z"/><path d="M14 3v5h5M9 13h6M9 17h6"/>',
			'chart'   => '<path d="M4 20V4M4 20h16"/><path d="m7 15 4-5 3 3 5-7"/>',
			'form'    => '<rect x="4" y="3" width="16" height="18" rx="2"/><path d="M8 8h8M8 12h8M8 16h5"/>',
			'star'    => '<path d="m12 3 2.7 5.6 6.1.9-4.4 4.3 1 6.1L12 17l-5.4 2.9 1-6.1L3.2 9.5l6.1-.9z"/>',
			'bell'    => '<path d="M6 16V11a6 6 0 1 1 12 0v5l2 2H4zM10 21h4"/>',
			'lock'    => '<rect x="5" y="11" width="14" height="10" rx="2"/><path d="M8 11V8a4 4 0 0 1 8 0v3"/>',
			'pin'     => '<path d="M12 21s7-6.2 7-11a7 7 0 1 0-14 0c0 4.8 7 11 7 11z"/><circle cx="12" cy="10" r="2.5"/>',
			'ig'      => '<rect x="3" y="3" width="18" height="18" rx="5"/><circle cx="12" cy="12" r="4"/><circle cx="17.5" cy="6.5" r=".8"/>',
			'wa'      => '<path d="M21 12a8 8 0 0 1-11.5 7.2L4 20l1-4.4A8 8 0 1 1 21 12z"/><path d="M9 9c0 3 3 6 6 6l1-2-2-1-1 .8c-.8-.4-1.600-1.200-2-2L11.800 10l-1-2z"/>',
			'mail'    => '<rect x="3" y="5" width="18" height="14" rx="2"/><path d="m3 7 9 6 9-6"/>',
			'globe'   => '<circle cx="12" cy="12" r="9"/><path d="M3 12h18M12 3c3 3 3 15 0 18M12 3c-3 3-3 15 0 18"/>',
			'link'    => '<path d="M10 14a4 4 0 0 0 5.700 0l3-3a4 4 0 0 0-5.700-5.700l-1 1M14 10a4 4 0 0 0-5.700 0l-3 3a4 4 0 0 0 5.700 5.700l1-1"/>',
			'play'    => '<path d="M7 4v16l13-8z"/>',
			'target'  => '<circle cx="12" cy="12" r="9"/><circle cx="12" cy="12" r="5"/><circle cx="12" cy="12" r="1"/>',
			'bolt'    => '<path d="M13 2 4 14h7l-1 8 9-12h-7z"/>',
			'users'   => '<circle cx="9" cy="8" r="3.500"/><path d="M2 20c0-3.500 3-6 7-6s7 2.500 7 6M17 5a3.500 3.500 0 0 1 0 7M22 20c0-2.500-1.500-4.500-4-5.500"/>',
		);
	}
	$p = isset( $i[ $name ] ) ? $i[ $name ] : $i['star'];
	return '<svg class="ico" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' . $p . '</svg>';
}

function ldk_site_logo_html( $cls = 'ldk-logo' ) {
	$src = esc_url( ldk_site_opt( 'logo' ) );
	$fb  = esc_url( ldk_site_logo_fallback() );
	return '<img class="' . esc_attr( $cls ) . '" src="' . $src . '" alt="LDK Marketing Digital" onerror="this.onerror=null;this.src=\'' . $fb . '\'">';
}

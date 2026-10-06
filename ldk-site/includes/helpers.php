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
			'ig'      => '<rect x="3" y="3" width="18" height="18" rx="5"/><circle cx="12" cy="12" r="4"/><path d="M17.5 6.5h.01"/>',
			'wa'      => '<path d="M3 21l1.6-4.9A9 9 0 1 1 8 19.5z"/><path d="M9 8.5c.3 3.2 3.300 6.200 6.500 6.500l1.300-1.600-2.300-1.200-.900.900c-1-.4-2.200-1.600-2.600-2.600l.9-.9L10.700 7z"/>',
			'mail'    => '<rect x="3" y="5" width="18" height="14" rx="2"/><path d="m3 7 9 6 9-6"/>',
			'globe'   => '<circle cx="12" cy="12" r="9"/><path d="M3 12h18M12 3c3 3 3 15 0 18M12 3c-3 3-3 15 0 18"/>',
			'link'    => '<path d="M10 14a4 4 0 0 0 5.700 0l3-3a4 4 0 0 0-5.700-5.700l-1 1M14 10a4 4 0 0 0-5.700 0l-3 3a4 4 0 0 0 5.700 5.700l1-1"/>',
			'play'    => '<path d="M7 4v16l13-8z"/>',
			'target'  => '<circle cx="12" cy="12" r="9"/><circle cx="12" cy="12" r="5"/><circle cx="12" cy="12" r="1"/>',
			'bolt'    => '<path d="M13 2 4 14h7l-1 8 9-12h-7z"/>',
			'users'   => '<circle cx="9" cy="8" r="3.500"/><path d="M2 20c0-3.500 3-6 7-6s7 2.500 7 6M17 5a3.500 3.500 0 0 1 0 7M22 20c0-2.500-1.500-4.500-4-5.500"/>',
			'heart'   => '<path d="M12 20.5s-8-4.9-8-11A4.600 4.600 0 0 1 12 7a4.600 4.600 0 0 1 8 2.500c0 6.100-8 11-8 11z"/>',
			'comment' => '<path d="M20 11.500a8 8 0 0 1-11.800 7L4 20l1.300-4A8 8 0 1 1 20 11.500z"/>',
			'send'    => '<path d="M21 3 10 14M21 3l-7 18-4-7-7-4z"/>',
			'save'    => '<path d="M6 3h12v18l-6-4.500L6 21z"/>',
			'phone'   => '<path d="M5 4h4l2 5-2.500 1.500a11 11 0 0 0 5 5L15 13l5 2v4a2 2 0 0 1-2 2A15 15 0 0 1 3 6a2 2 0 0 1 2-2z"/>',
			'clock'   => '<circle cx="12" cy="12" r="9"/><path d="M12 7v5l3.500 2"/>',
			'camera'  => '<path d="M4 8h3l2-3h6l2 3h3v11H4z"/><circle cx="12" cy="13" r="3.500"/>',
			'up'      => '<path d="M7 17 17 7M8 7h9v9"/>',
			'spark'   => '<path d="M12 3v5M12 16v5M3 12h5M16 12h5M6 6l3 3M15 15l3 3M18 6l-3 3M9 15l-3 3"/>',
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

/** Monograma em SVG embutido (nunca quebra, mesmo sem a imagem da logo). */
function ldk_site_mark() {
	return '<svg class="ldk-mark" viewBox="0 0 120 120" aria-label="LDK" role="img"><defs><linearGradient id="ldkg" x1="0" y1="0" x2="1" y2="1"><stop offset="0" stop-color="#14E9EC"/><stop offset="1" stop-color="#2f8cff"/></linearGradient></defs><rect x="3" y="3" width="114" height="114" rx="34" fill="#071826"/><rect x="3" y="3" width="114" height="114" rx="34" fill="none" stroke="url(#ldkg)" stroke-width="3"/><text x="60" y="73" text-anchor="middle" font-family="Montserrat,Arial,sans-serif" font-weight="800" font-size="40" fill="#fff" letter-spacing="-1">LDK</text><circle cx="60" cy="90" r="4" fill="#14E9EC"/></svg>';
}


/** Banco de imagens (Unsplash, uso livre). Se uma falhar, o bloco mostra o degradê da marca. */
function ldk_site_stock( $k, $w = 1400 ) {
	$ids = array(
		'servicos'  => '1460925895917-afdab827c52f',
		'painel'    => '1551288049-bebda4e38f71',
		'sobre'     => '1522202176988-66273c2fd55f',
		'blog'      => '1432888498266-38ffec3eaf0a',
		'contato'   => '1556761175-5973dc0f32e7',
		'diag'      => '1611162617213-7d7a39e9b1d7',
		'social'    => '1611162617213-7d7a39e9b1d7',
		'trafego'   => '1551288049-bebda4e38f71',
		'video'     => '1492691527719-9d1e07e534b4',
		'site'      => '1498050108023-c5249f4df085',
		'brand'     => '1561070791-2526d30994b5',
		'users'     => '1460925895917-afdab827c52f',
		'feed1'     => '1611926653458-09294b3142bf',
		'feed2'     => '1611162617213-7d7a39e9b1d7',
		'feed3'     => '1557804506-669a67965ba0',
		'feed4'     => '1533750349088-cd871a92f312',
		'feed5'     => '1542744173-8e7e53415bb0',
		'feed6'     => '1553877522-43269d4ea984',
		'post0'     => '1432888498266-38ffec3eaf0a',
		'post1'     => '1551288049-bebda4e38f71',
		'post2'     => '1561070791-2526d30994b5',
	);
	$id = isset( $ids[ $k ] ) ? $ids[ $k ] : $ids['servicos'];
	return 'https://images.unsplash.com/photo-' . $id . '?auto=format&fit=crop&q=70&w=' . (int) $w;
}

/** Imagem de reserva para artigos sem destaque (gira entre 3). */
function ldk_site_post_fallback( $i = 0, $w = 1000 ) {
	return ldk_site_stock( 'post' . ( abs( (int) $i ) % 3 ), $w );
}

/** Logo do Instagram em cores (SVG embutido). */
function ldk_site_ig_logo( $cls = 'igl' ) {
	return '<svg class="' . esc_attr( $cls ) . '" viewBox="0 0 48 48" aria-hidden="true"><defs><radialGradient id="ldkig" cx=".3" cy="1.05" r="1.15"><stop offset="0" stop-color="#ffd35a"/><stop offset=".28" stop-color="#ff7a3c"/><stop offset=".55" stop-color="#e1306c"/><stop offset=".85" stop-color="#833ab4"/><stop offset="1" stop-color="#405de6"/></radialGradient></defs><rect width="48" height="48" rx="13" fill="url(#ldkig)"/><rect x="11.500" y="11.500" width="25" height="25" rx="7.500" fill="none" stroke="#fff" stroke-width="2.600"/><circle cx="24" cy="24" r="6" fill="none" stroke="#fff" stroke-width="2.600"/><circle cx="31.500" cy="16.500" r="1.700" fill="#fff"/></svg>';
}

/** Logo do WhatsApp em cores (SVG embutido). */
function ldk_site_wa_logo( $cls = 'wal' ) {
	return '<svg class="' . esc_attr( $cls ) . '" viewBox="0 0 48 48" aria-hidden="true"><rect width="48" height="48" rx="13" fill="#25D366"/><path d="M24 10.500a13.500 13.500 0 0 0-11.600 20.300L10.500 37.500l6.900-1.800A13.500 13.500 0 1 0 24 10.500z" fill="none" stroke="#fff" stroke-width="2.600" stroke-linejoin="round"/><path d="M19.300 18.600c.4 4.300 4.400 8.300 8.700 8.700l1.800-2.100-3-1.600-1.200 1.200c-1.400-.6-3-2.200-3.600-3.600l1.200-1.200-1.600-3z" fill="#fff"/></svg>';
}

/** <img> que nunca deixa buraco: se não carregar, o contêiner mostra o degradê. */
function ldk_site_imgtag( $src, $alt = '', $extra = '' ) {
	return '<img src="' . esc_url( $src ) . '" alt="' . esc_attr( $alt ) . '" loading="lazy" decoding="async" ' . $extra . '>';
}

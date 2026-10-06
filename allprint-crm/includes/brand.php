<?php
/**
 * Marca: cores do tema, contraste (WCAG) e escolha automática da logo.
 *
 * - As cores vêm de identity.php e podem ser trocadas em Configurações → Marca.
 * - Toda cor de texto sobre fundo é calculada para ter contraste mínimo (4,5:1 no texto, 3:1 em ícones/botões).
 * - A logo muda sozinha conforme o fundo onde ela aparece (colorida, branca ou monocromática).
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/* -----------------------------------------------------------------------
 * Matemática de cor
 * -------------------------------------------------------------------- */

function ap_hex_ok( $hex ) {
	return (bool) preg_match( '/^#?([0-9a-f]{3}|[0-9a-f]{6})$/i', trim( (string) $hex ) );
}

function ap_hex_rgb( $hex ) {
	$hex = ltrim( trim( (string) $hex ), '#' );
	if ( 3 === strlen( $hex ) ) {
		$hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2];
	}
	if ( 6 !== strlen( $hex ) || ! ctype_xdigit( $hex ) ) {
		return array( 0, 0, 0 );
	}
	return array( hexdec( substr( $hex, 0, 2 ) ), hexdec( substr( $hex, 2, 2 ) ), hexdec( substr( $hex, 4, 2 ) ) );
}

function ap_rgb_hex( $rgb ) {
	return sprintf( '#%02x%02x%02x', max( 0, min( 255, (int) round( $rgb[0] ) ) ), max( 0, min( 255, (int) round( $rgb[1] ) ) ), max( 0, min( 255, (int) round( $rgb[2] ) ) ) );
}

/** Luminância relativa (WCAG 2.1). */
function ap_luminance( $hex ) {
	$out = array();
	foreach ( ap_hex_rgb( $hex ) as $c ) {
		$c     = $c / 255;
		$out[] = $c <= 0.03928 ? $c / 12.92 : pow( ( $c + 0.055 ) / 1.055, 2.4 );
	}
	return 0.2126 * $out[0] + 0.7152 * $out[1] + 0.0722 * $out[2];
}

/** Razão de contraste entre duas cores (1 a 21). */
function ap_contrast( $a, $b ) {
	$la = ap_luminance( $a );
	$lb = ap_luminance( $b );
	$hi = max( $la, $lb );
	$lo = min( $la, $lb );
	return ( $hi + 0.05 ) / ( $lo + 0.05 );
}

/** Mistura: $amount (0-1) de $fg sobre $bg. */
function ap_mix( $fg, $bg, $amount ) {
	$f = ap_hex_rgb( $fg );
	$b = ap_hex_rgb( $bg );
	return ap_rgb_hex( array( $b[0] + ( $f[0] - $b[0] ) * $amount, $b[1] + ( $f[1] - $b[1] ) * $amount, $b[2] + ( $f[2] - $b[2] ) * $amount ) );
}

/** Texto (preto-azulado ou branco) com o melhor contraste sobre $bg. */
function ap_on_color( $bg, $dark = '#15132b', $light = '#ffffff' ) {
	return ap_contrast( $dark, $bg ) >= ap_contrast( $light, $bg ) ? $dark : $light;
}

/**
 * Escurece (ou clareia) $color até ter contraste $min sobre todos os fundos de $bgs.
 * Mantém o matiz: o amarelo vira um dourado escuro, por exemplo.
 */
function ap_ensure_contrast( $color, $bgs, $min = 4.5, $darken = true ) {
	$bgs  = (array) $bgs;
	$best = $color;
	for ( $i = 0; $i <= 40; $i++ ) {
		$ok = true;
		foreach ( $bgs as $bg ) {
			if ( ap_contrast( $best, $bg ) < $min ) {
				$ok = false;
				break;
			}
		}
		if ( $ok ) {
			return $best;
		}
		$best = ap_mix( $darken ? '#000000' : '#ffffff', $color, ( $i + 1 ) * 0.025 );
	}
	return $darken ? '#000000' : '#ffffff';
}

/* -----------------------------------------------------------------------
 * Cores do tema (identity.php + Configurações)
 * -------------------------------------------------------------------- */

function ap_theme_colors() {
	$t = ap_identity()['tema'];
	$s = get_option( 'ap_settings', array() );
	$s = is_array( $s ) ? $s : array();
	foreach ( array( 'ink', 'bg', 'accent', 'side' ) as $k ) {
		if ( ! empty( $s[ 'cor_' . $k ] ) && ap_hex_ok( $s[ 'cor_' . $k ] ) ) {
			$t[ $k ] = '#' . ltrim( $s[ 'cor_' . $k ], '#' );
		}
	}
	return $t;
}

/**
 * Cores de texto, linhas e destaque para uma superfície fixa (menu lateral, topo do cliente…),
 * calculadas a partir do fundo para sempre ler bem, claro ou escuro.
 */
function ap_surface_tokens( $bg, $accent ) {
	$text    = ap_on_color( $bg, '#15132b', '#ffffff' );
	$light   = '#ffffff' === $text;
	$muted   = ap_ensure_contrast( ap_mix( $text, $bg, 0.72 ), $bg, 4.5, ! $light );
	$faint   = ap_ensure_contrast( ap_mix( $text, $bg, 0.6 ), $bg, 4.5, ! $light );
	$acc     = ap_ensure_contrast( $accent, $bg, 3.0, ! $light );
	return array(
		'bg'     => $bg,
		'text'   => $text,
		'muted'  => $muted,
		'faint'  => $faint,
		'line'   => ap_mix( $text, $bg, 0.16 ),
		'hover'  => ap_mix( $text, $bg, 0.08 ),
		'active' => ap_mix( $text, $bg, 0.15 ),
		'accent' => $acc,
		'on-acc' => ap_on_color( $acc, '#15132b' ),
	);
}

/**
 * Tokens derivados com contraste garantido, para o tema claro e o escuro.
 */
function ap_brand_tokens() {
	static $cache = null;
	if ( null !== $cache ) {
		return $cache;
	}
	$t      = ap_theme_colors();
	$accent = $t['accent'];
	$ink    = $t['ink'];

	// Tema claro
	$card_l = '#ffffff';
	$bg_l   = $t['bg'];
	$on_l   = ap_on_color( $accent, $ink );
	$L      = array(
		'accent'        => $accent,
		'accent-bright' => $accent,
		'on-accent'     => $on_l,
		'accent-hover'  => ap_mix( $on_l, $accent, 0.12 ),
		'accent-text'   => ap_ensure_contrast( $accent, array( $card_l, $bg_l ), 4.5, true ),
		'brand-soft'    => ap_mix( $accent, '#ffffff', 0.16 ),
		'brand-line'    => ap_mix( $accent, '#ffffff', 0.5 ),
		'muted'         => ap_ensure_contrast( '#737373', array( $card_l, $bg_l ), 4.5, true ),
		'accent-ui'     => ap_ensure_contrast( $accent, array( $card_l, $bg_l ), 3.0, true ),
	);
	// Tema escuro
	$card_d = '#171717';
	$bg_d   = '#0d0d0d';
	$acc_d  = ap_contrast( $accent, $card_d ) >= 4.5 ? $accent : ap_ensure_contrast( $accent, array( $card_d, $bg_d ), 4.5, false );
	$on_d   = ap_on_color( $acc_d, '#0a0a0a' );
	$D      = array(
		'accent'        => $acc_d,
		'accent-bright' => $acc_d,
		'on-accent'     => $on_d,
		'accent-hover'  => ap_mix( '#ffffff', $acc_d, 0.15 ),
		'accent-text'   => $acc_d,
		'brand-soft'    => ap_mix( $acc_d, $card_d, 0.16 ),
		'brand-line'    => ap_mix( $acc_d, $card_d, 0.4 ),
		'muted'         => ap_ensure_contrast( '#9a9a96', array( $card_d, $bg_d ), 4.5, false ),
		'accent-ui'     => $acc_d,
	);
	$surf  = array(
		'side' => array( 'light' => ap_surface_tokens( $t['side'], $accent ), 'dark' => ap_surface_tokens( '#080808', $acc_d ) ),
		'ctop' => array( 'light' => ap_surface_tokens( $t['ink'], $accent ), 'dark' => ap_surface_tokens( '#080808', $acc_d ) ),
	);
	$cache = array( 'light' => $L, 'dark' => $D, 'theme' => $t, 'surf' => $surf );
	return $cache;
}

/** CSS dos tokens (vai logo depois do app.css). */
function ap_brand_css() {
	$k   = ap_brand_tokens();
	$out = '';
	$fmt = function ( $arr ) {
		$s = '';
		foreach ( $arr as $name => $v ) {
			$s .= '--' . $name . ':' . esc_attr( $v ) . ';';
		}
		return $s;
	};
	$flat = function ( $mode ) use ( $k ) {
		$o = array();
		foreach ( $k['surf'] as $name => $modes ) {
			foreach ( $modes[ $mode ] as $key => $v ) {
				$o[ $name . '-' . $key ] = $v;
			}
		}
		return $o;
	};
	$out .= ':root{' . $fmt( $k['light'] ) . $fmt( $flat( 'light' ) ) . '}';
	$out .= '[data-theme="dark"]{' . $fmt( $k['dark'] ) . $fmt( $flat( 'dark' ) ) . '}';
	// Superfícies escuras fixas usam o acento "cru" também como texto.
	$out .= '.side,.auth-side,.ctop,.card--dark,.drive,.autofill,.toast,.stat--dark,.fz,.pay-result{--accent-text:' . esc_attr( $k['light']['accent-bright'] ) . ';}';
	$out .= ap_tones_css();
	// Logo: troca conforme o tema (claro/escuro) quando as duas versões diferem.
	$out .= 'html:not([data-theme="dark"]) .lg-dk{display:none!important}[data-theme="dark"] .lg-lt{display:none!important}';
	return $out;
}

/* -----------------------------------------------------------------------
 * Logo
 * -------------------------------------------------------------------- */

/** Cores principais de cada versão da logo (o que precisa contrastar com o fundo). */
function ap_logo_variants() {
	return array(
		'cor'    => array( 'main' => array( '#2b292a', '#312c54' ), 'accent' => '#ffcc2a' ),
		'branca' => array( 'main' => array( '#ffffff' ), 'accent' => null ),
		'mono'   => array( 'main' => array( '#15132b' ), 'accent' => null ),
	);
}

/**
 * Versão da logo com melhor leitura sobre o fundo $bg.
 * Prefere a colorida; se ela perder (fundo escuro, médio ou amarelo), usa a branca ou a monocromática.
 */
function ap_logo_variant( $bg ) {
	$v     = ap_logo_variants();
	$score = function ( $name ) use ( $v, $bg ) {
		$min = 99;
		foreach ( $v[ $name ]['main'] as $c ) {
			$min = min( $min, ap_contrast( $c, $bg ) );
		}
		return $min;
	};
	$cor_ok = $score( 'cor' ) >= 4.5 && ap_contrast( $v['cor']['accent'], $bg ) >= 1.3;
	if ( $cor_ok ) {
		return 'cor';
	}
	return $score( 'branca' ) >= $score( 'mono' ) ? 'branca' : 'mono';
}

/**
 * Endereço da imagem. Logos próprias (Configurações) têm prioridade:
 * "logo" = versão para fundo escuro; "logo_cor" = versão para fundo claro.
 */
function ap_logo_url( $variant, $shape = 'h' ) {
	if ( 'h' === $shape ) {
		if ( 'branca' === $variant && ap_setting( 'logo' ) ) {
			return ap_setting( 'logo' );
		}
		if ( 'cor' === $variant && ap_setting( 'logo_cor' ) ) {
			return ap_setting( 'logo_cor' );
		}
	}
	return AP_URL . 'assets/brand/alprint-' . ( 'icone' === $shape ? 'icone' : $shape ) . '-' . $variant . '.png?ver=' . AP_VERSION;
}

/**
 * <img> da logo para um fundo (ou para dois: tema claro e tema escuro).
 * $bg_light: cor do fundo no tema claro. $bg_dark: no tema escuro (padrão: igual).
 */
function ap_logo_img( $bg_light, $bg_dark = null, $class = '', $alt = '', $shape = 'h' ) {
	$bg_dark = $bg_dark ? $bg_dark : $bg_light;
	$vl      = ap_logo_variant( $bg_light );
	$vd      = ap_logo_variant( $bg_dark );
	$alt     = $alt ? $alt : ap_setting( 'empresa' );
	$img     = function ( $variant, $extra ) use ( $class, $alt, $shape ) {
		return '<img class="' . esc_attr( trim( $class . ' ' . $extra ) ) . '" src="' . esc_url( ap_logo_url( $variant, $shape ) ) . '" alt="' . esc_attr( $alt ) . '" data-logo="' . esc_attr( $variant ) . '">';
	};
	if ( $vl === $vd ) {
		return $img( $vl, '' );
	}
	return $img( $vl, 'lg-lt' ) . $img( $vd, 'lg-dk' );
}

/** Fundos fixos do sistema (usados para escolher a logo e testar contraste). */
function ap_surfaces() {
	$k = ap_brand_tokens();
	$t = $k['theme'];
	return array(
		'side'  => array( 'Menu lateral', $t['side'], '#080808' ),
		'ctop'  => array( 'Topo da área do cliente', $t['ink'], '#080808' ),
		'auth'  => array( 'Login e cadastro', $t['ink'], $t['ink'] ),
		'pay'   => array( 'Pagamento', $t['ink'], $t['ink'] ),
		'oq'    => array( 'Orçamento (página pública)', '#0a0a0a', '#0a0a0a' ),
		'mail'  => array( 'Cabeçalho dos e-mails', $t['ink'], $t['ink'] ),
		'card'  => array( 'Cartões e documentos (fundo claro)', '#ffffff', '#171717' ),
	);
}

/** Logo de uma superfície do sistema, já com a versão certa. */
function ap_logo_for( $surface, $class = '', $shape = 'h' ) {
	$s = ap_surfaces();
	if ( ! isset( $s[ $surface ] ) ) {
		$s[ $surface ] = array( '', '#ffffff', '#171717' );
	}
	return ap_logo_img( $s[ $surface ][1], $s[ $surface ][2], $class, '', $shape );
}

/* -----------------------------------------------------------------------
 * Relatório de contraste (Configurações → Marca)
 * -------------------------------------------------------------------- */

function ap_contrast_report() {
	$k    = ap_brand_tokens();
	$t    = $k['theme'];
	$rows = array();
	$add  = function ( $mode, $label, $fg, $bg, $min ) use ( &$rows ) {
		$r      = ap_contrast( $fg, $bg );
		$rows[] = array( 'mode' => $mode, 'label' => $label, 'fg' => $fg, 'bg' => $bg, 'ratio' => $r, 'min' => $min, 'ok' => $r >= $min );
	};
	foreach ( array( 'light' => array( $t['bg'], '#ffffff', $t['ink'], $t['side'] ), 'dark' => array( '#0d0d0d', '#171717', '#f2f2ef', '#080808' ) ) as $mode => $c ) {
		list( $bg, $card, $text, $side ) = $c;
		$tok = $k[ $mode ];
		$add( $mode, 'Texto principal sobre o fundo', $text, $bg, 4.5 );
		$add( $mode, 'Texto principal sobre o cartão', $text, $card, 4.5 );
		$add( $mode, 'Texto secundário sobre o fundo', $tok['muted'], $bg, 4.5 );
		$add( $mode, 'Texto secundário sobre o cartão', $tok['muted'], $card, 4.5 );
		$add( $mode, 'Cor de destaque como texto (links, rótulos) no cartão', $tok['accent-text'], $card, 4.5 );
		$add( $mode, 'Cor de destaque como texto no fundo', $tok['accent-text'], $bg, 4.5 );
		$add( $mode, 'Texto dentro do botão de destaque', $tok['on-accent'], $tok['accent'], 4.5 );
		$add( $mode, 'Botão principal (texto sobre o botão)', $mode === 'light' ? '#ffffff' : '#0a0a0a', $text, 4.5 );
		$st = $k['surf']['side'][ $mode ];
		$ct = $k['surf']['ctop'][ $mode ];
		$add( $mode, 'Menu lateral: texto', $st['text'], $st['bg'], 4.5 );
		$add( $mode, 'Menu lateral: texto secundário', $st['muted'], $st['bg'], 4.5 );
		$add( $mode, 'Menu lateral: títulos de grupo', $st['faint'], $st['bg'], 4.5 );
		$add( $mode, 'Menu lateral: indicador e ícones de destaque', $st['accent'], $st['bg'], 3.0 );
		$add( $mode, 'Menu lateral: número no contador', $st['on-acc'], $st['accent'], 4.5 );
		$add( $mode, 'Topo da área do cliente: texto', $ct['muted'], $ct['bg'], 4.5 );
		$add( $mode, 'Botão "+ Novo pedido" do topo', $ct['on-acc'], $ct['accent'], 4.5 );
		$add( $mode, 'Barras, abas e indicadores sobre o cartão', $tok['accent-ui'], $card, 3.0 );
	}
	$tone_names = array( 'pay' => 'Aguardando pagamento / sinal', 'rev' => 'Revisão', 'prod' => 'Produção', 'fin' => 'Acabamento', 'ready' => 'Pronto / pago', 'done' => 'Entregue', 'art' => 'Arte', 'pickup' => 'Paga na retirada', 'wait' => 'Pagamento pendente', 'x' => 'Outras etapas' );
	foreach ( array( 'light' => ap_tone_palette(), 'dark' => ap_tone_palette_dark() ) as $mode => $pal ) {
		foreach ( $pal as $tk => $tc ) {
			$add( $mode, 'Tag: ' . $tone_names[ $tk ], $tc['fg'], $tc['bg'], 4.5 );
		}
	}
	foreach ( ap_surfaces() as $key => $s ) {
		foreach ( array( 'light' => $s[1], 'dark' => $s[2] ) as $mode => $bg ) {
			$v   = ap_logo_variant( $bg );
			$def = ap_logo_variants()[ $v ];
			$min = 99;
			foreach ( $def['main'] as $c ) {
				$min = min( $min, ap_contrast( $c, $bg ) );
			}
			$add( $mode, 'Logo (' . $v . ') sobre: ' . $s[0], $def['main'][0], $bg, 3.0 );
			$rows[ count( $rows ) - 1 ]['ratio'] = $min;
			$rows[ count( $rows ) - 1 ]['ok']    = $min >= 3.0;
			$rows[ count( $rows ) - 1 ]['logo']  = $v;
		}
	}
	return $rows;
}

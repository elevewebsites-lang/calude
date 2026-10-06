<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function ldk_site_head( $eyebrow, $title, $text = '', $center = false ) {
	$h = '<header class="ldk-head' . ( $center ? ' c' : '' ) . '">';
	if ( $eyebrow ) {
		$h .= '<span class="ldk-eyebrow" data-r>' . esc_html( $eyebrow ) . '</span>';
	}
	$h .= '<h2 class="ldk-h2" data-r style="--d:.05s">' . ldk_site_hl( $title ) . '</h2>';
	if ( $text ) {
		$h .= '<p class="ldk-lead" data-r style="--d:.1s">' . esc_html( $text ) . '</p>';
	}
	return $h . '</header>';
}

function ldk_site_btn( $text, $url, $ghost = false ) {
	if ( ! $text ) {
		return '';
	}
	return '<a class="ldk-btn' . ( $ghost ? ' ghost' : '' ) . '" href="' . esc_url( ldk_site_link( $url ) ) . '"><span>' . esc_html( $text ) . '</span>' . ldk_site_ico( 'arrow' ) . '</a>';
}

function ldk_site_lines( $t ) {
	return array_values( array_filter( array_map( 'trim', preg_split( '/\R/', (string) $t ) ) ) );
}

/** Despacha para o renderizador do widget. */
function ldk_site_render( $key, $s = array() ) {
	$fn = 'ldk_site_r_' . $key;
	if ( ! function_exists( $fn ) ) {
		return '';
	}
	$tones = array(
		'hero' => 'dark', 'quick' => 'dark', 'pagehead' => 'dark', 'marquee' => 'cyan', 'stats' => 'cyan',
		'services' => 'light', 'clients' => 'light', 'about' => 'light', 'testimonials' => 'light', 'faq' => 'light', 'posts' => 'light', 'contact' => 'light',
		'steps' => 'dark', 'panel' => 'dark', 'feed' => 'dark', 'social' => 'dark', 'pains' => 'dark', 'diag' => 'dark', 'cta' => 'dark', 'links' => 'dark',
	);
	$tone  = isset( $tones[ $key ] ) ? $tones[ $key ] : 'dark';
	return '<div class="ldk ldkw-' . esc_attr( $key ) . '" data-tone="' . esc_attr( $tone ) . '">' . $fn( ldk_site_settings( $key, $s ) ) . '</div>';
}

function ldk_site_r_pagehead( $s ) {
	$img = ldk_site_img( $s['image'] );
	$bg  = $img ? '<div class="phbg" aria-hidden="true">' . ldk_site_imgtag( $img, '', 'fetchpriority="high"' ) . '<i class="fall"></i></div>' : '';
	return '<section class="ldk-sec ldk-ph' . ( $img ? ' hasimg' : '' ) . '">' . $bg . '<div class="ldk-wrap ldk-phin">' .
		( $s['eyebrow'] ? '<span class="ldk-eyebrow">' . esc_html( $s['eyebrow'] ) . '</span>' : '' ) .
		'<h1 class="ldk-h1 sm" data-split>' . ldk_site_hl( $s['title'] ) . '</h1>' .
		( $s['text'] ? '<p class="ldk-lead" data-r style="--d:.35s">' . esc_html( $s['text'] ) . '</p>' : '' ) .
		'</div></section>';
}

function ldk_site_r_hero( $s ) {
	$mark  = ldk_site_mark();
	$story = '<span class="st me">' . $mark . '</span>';
	foreach ( array( 'feed2', 'feed3', 'feed4', 'feed5' ) as $k ) {
		$story .= '<span class="st"><i>' . ldk_site_imgtag( ldk_site_stock( $k, 160 ) ) . '</i></span>';
	}
	return '<section class="ldk-sec ldk-herosec"><div class="ldk-wrap ldk-herogrid">' .
		'<div class="ldk-herotxt">' .
		'<span class="ldk-eyebrow" data-r>' . esc_html( $s['eyebrow'] ) . '</span>' .
		'<h1 class="ldk-h1" data-split>' . ldk_site_hl( $s['title'] ) . '</h1>' .
		'<p class="ldk-lead" data-r style="--d:.3s">' . esc_html( $s['text'] ) . '</p>' .
		'<div class="ldk-actions" data-r style="--d:.4s">' . ldk_site_btn( $s['btn1'], $s['btn1_u'] ) . ldk_site_btn( $s['btn2'], $s['btn2_u'], true ) . '</div>' .
		( $s['proof'] ? '<p class="ldk-proof" data-r style="--d:.5s">' . esc_html( $s['proof'] ) . '</p>' : '' ) .
		'</div>' .
		'<div class="ldk-herovis" data-r="zoom" aria-hidden="true">' .
		'<div class="hv-card" data-par="-.05"><div class="bar"><i></i><i></i><i></i></div>' . ldk_site_imgtag( ldk_site_img( $s['img_main'] ) ) . '</div>' .
		'<div class="hv-phone" data-par=".03"><div class="notch"></div>' .
			'<div class="ig-top"><b>Instagram</b><span>' . ldk_site_ico( 'heart' ) . ldk_site_ico( 'send' ) . '</span></div>' .
			'<div class="ig-stories">' . $story . '</div>' .
			'<div class="ig-post"><div class="ph"><span class="av">' . $mark . '</span><span class="nm"><b>ldkmarketingdigital</b><small>Taubaté, SP</small></span></div>' .
			'<div class="pimg">' . ldk_site_imgtag( ldk_site_img( $s['img_phone'] ) ) . '</div>' .
			'<div class="pact">' . ldk_site_ico( 'heart' ) . ldk_site_ico( 'comment' ) . ldk_site_ico( 'send' ) . '<span>' . ldk_site_ico( 'save' ) . '</span></div>' .
			'<p><b>ldkmarketingdigital</b> Marca forte aparece. Estratégia vira resultado.</p></div></div>' .
		'<div class="fly f-ig" data-par="-.12">' . ldk_site_ig_logo() . '</div>' .
		'<div class="fly f-wa" data-par=".1">' . ldk_site_wa_logo() . '</div>' .
		'<div class="fly f-heart" data-par="-.18">' . ldk_site_ico( 'heart' ) . '</div>' .
		'<div class="fly f-toast" data-par=".06">' . ldk_site_ico( 'check' ) . '<span><b>Post aprovado</b><small>Publicação agendada</small></span></div>' .
		'</div></div></section>';
}

function ldk_site_r_quick( $s ) {
	return '<section class="ldk-sec ldk-quicksec"><div class="ldk-wrap"><div class="ldk-quick" data-r>' .
		'<div class="qt"><h2 class="ldk-h3">' . ldk_site_hl( $s['title'] ) . '</h2><p>' . esc_html( $s['text'] ) . '</p></div>' .
		ldk_site_form_quick( $s['tipo'], $s['btn'] ) . '</div></div></section>';
}

function ldk_site_services_options() {
	$svc = array( 'Gestão de redes sociais', 'Gestão de tráfego pago', 'Gravação de vídeos', 'Criação de site', 'Branding', 'Ainda não sei' );
	$o   = '<option value="">Interesse</option>';
	foreach ( $svc as $v ) {
		$o .= '<option>' . esc_html( $v ) . '</option>';
	}
	return $o;
}

function ldk_site_form_quick( $tipo, $btn ) {
	return '<form class="ldk-form quick" data-ldk-form data-wa="1" data-tipo="' . esc_attr( $tipo ) . '" novalidate>' .
		'<label><span>Nome</span><input name="Nome" required autocomplete="name" placeholder="Seu nome"></label>' .
		'<label><span>WhatsApp</span><input name="WhatsApp" type="tel" required autocomplete="tel" inputmode="tel" placeholder="(12) 90000-0000"></label>' .
		'<label><span>Interesse</span><select name="Interesse">' . ldk_site_services_options() . '</select></label>' .
		'<input class="hp" type="text" name="website" tabindex="-1" autocomplete="off" aria-hidden="true">' .
		'<input type="hidden" name="lgpd" value="1">' .
		'<button class="ldk-btn" type="submit"><span>' . esc_html( $btn ) . '</span>' . ldk_site_ico( 'wa' ) . '</button>' .
		'<p class="fmsg" role="status" aria-live="polite"></p></form>';
}

function ldk_site_r_marquee( $s ) {
	$items = ldk_site_lines( $s['items'] );
	$row   = '';
	foreach ( $items as $it ) {
		$row .= '<span>' . esc_html( $it ) . '</span><b aria-hidden="true">✦</b>';
	}
	return '<div class="ldk-marq" aria-hidden="true"><div class="track">' . $row . $row . $row . $row . '</div></div>';
}

function ldk_site_r_services( $s ) {
	$h = '<section class="ldk-sec"><div class="ldk-wrap">' . ldk_site_head( $s['eyebrow'], $s['title'], $s['text'] ) . '<div class="ldk-grid g3">';
	foreach ( (array) $s['items'] as $i => $it ) {
		$link = ! empty( $it['link'] ) ? ldk_site_link( $it['link'] ) : '';
		$tag  = $link ? 'a' : 'div';
		$img  = ! empty( $it['image'] ) ? ldk_site_img( $it['image'] ) : '';
		$h   .= '<' . $tag . ( $link ? ' href="' . esc_url( $link ) . '"' : '' ) . ' class="ldk-card svc" data-r style="--d:' . ( ( $i % 3 ) * 0.08 ) . 's">' .
			'<span class="im">' . ( $img ? ldk_site_imgtag( $img ) : '' ) . '<span class="ldk-ico">' . ldk_site_ico( isset( $it['icon'] ) ? $it['icon'] : 'star' ) . '</span></span>' .
			'<span class="cb"><h3>' . esc_html( $it['title'] ) . '</h3><p>' . esc_html( $it['text'] ) . '</p>' .
			( $link ? '<span class="more">Saiba mais ' . ldk_site_ico( 'arrow' ) . '</span>' : '' ) . '</span>' .
			'</' . $tag . '>';
	}
	return $h . '</div></div></section>';
}

function ldk_site_r_steps( $s ) {
	$h = '<section class="ldk-sec"><div class="ldk-wrap">' . ldk_site_head( $s['eyebrow'], $s['title'] ) . '<ol class="ldk-steps">';
	foreach ( (array) $s['items'] as $i => $it ) {
		$h .= '<li data-r style="--d:' . ( $i * 0.08 ) . 's"><span class="n">' . sprintf( '%02d', $i + 1 ) . '</span><h3>' . esc_html( $it['title'] ) . '</h3><p>' . esc_html( $it['text'] ) . '</p></li>';
	}
	return $h . '</ol></div></section>';
}

function ldk_site_r_stats( $s ) {
	$h = '<section class="ldk-band"><div class="ldk-wrap"><div class="ldk-stats" data-r><h2>' . esc_html( $s['title'] ) . '</h2><div class="nums">';
	foreach ( (array) $s['items'] as $it ) {
		$n  = (string) $it['num'];
		$h .= '<div><strong><span data-count="' . esc_attr( $n ) . '">' . esc_html( $n ) . '</span>' . esc_html( $it['suffix'] ) . '</strong><small>' . esc_html( $it['label'] ) . '</small></div>';
	}
	return $h . '</div></div></div></section>';
}

function ldk_site_r_clients( $s ) {
	$h = '<section class="ldk-sec tight"><div class="ldk-wrap"><p class="ldk-kicker" data-r>' . esc_html( $s['title'] ) . '</p><div class="ldk-logos" data-r>';
	foreach ( (array) $s['items'] as $it ) {
		$img = ldk_site_img( $it['image'] );
		if ( $img ) {
			$h .= '<span class="lg"><img src="' . esc_url( $img ) . '" alt="' . esc_attr( $it['name'] ) . '" loading="lazy"></span>';
		}
	}
	return $h . '</div></div></section>';
}

function ldk_site_r_panel( $s ) {
	$items = (array) $s['items'];
	$tabs  = '';
	$imgs  = '';
	foreach ( $items as $i => $it ) {
		$on    = 0 === $i ? ' on' : '';
		$tabs .= '<button type="button" class="pt' . $on . '" data-pt="' . $i . '"><span class="ldk-ico">' . ldk_site_ico( isset( $it['icon'] ) ? $it['icon'] : 'check' ) . '</span><span class="tx"><b>' . esc_html( $it['title'] ) . '</b><small>' . esc_html( $it['text'] ) . '</small></span></button>';
		$imgs .= '<img class="ps' . $on . '" data-ps="' . $i . '" src="' . esc_url( ldk_site_img( $it['image'] ) ) . '" alt="' . esc_attr( 'Tela do painel: ' . $it['title'] ) . '" loading="lazy">';
	}
	return '<section class="ldk-sec"><div class="ldk-wrap">' . ldk_site_head( $s['eyebrow'], $s['title'], $s['text'] ) .
		'<div class="ldk-panelgrid" data-panel><div class="ptabs" data-r>' . $tabs . '<div class="pcta">' . ldk_site_btn( $s['cta'], $s['cta_u'] ) . '</div></div>' .
		'<div class="pdev" data-r="right"><div class="bar"><i></i><i></i><i></i><span>painel.ldkmarketingdigital.com.br</span></div><div class="scr">' . $imgs . '</div></div></div></div></section>';
}

function ldk_site_r_about( $s ) {
	$h = '<section class="ldk-sec"><div class="ldk-wrap"><div class="ldk-aboutgrid"><div class="ab-txt">' . ldk_site_head( $s['eyebrow'], $s['title'] ) .
		'<p class="ldk-body" data-r>' . esc_html( $s['text'] ) . '</p><p class="ldk-body" data-r>' . esc_html( $s['text2'] ) . '</p></div>';
	$img = ldk_site_img( $s['image'] );
	$h  .= '<div class="ab-img" data-r="right"><div class="frame">' . ( $img ? '<img src="' . esc_url( $img ) . '" alt="Marília Lüdke, fundadora da LDK" loading="lazy">' : '' ) . '</div></div></div>';
	$h  .= '<div class="ldk-grid g4 mt">';
	foreach ( (array) $s['items'] as $i => $it ) {
		$h .= '<div class="ldk-card flat" data-r style="--d:' . ( $i * 0.07 ) . 's"><span class="ldk-ico">' . ldk_site_ico( isset( $it['icon'] ) ? $it['icon'] : 'star' ) . '</span><h3>' . esc_html( $it['title'] ) . '</h3><p>' . esc_html( $it['text'] ) . '</p></div>';
	}
	return $h . '</div></div></section>';
}

function ldk_site_r_cta( $s ) {
	return '<section class="ldk-sec"><div class="ldk-wrap"><div class="ldk-cta" data-r="zoom"><div class="glow"></div>' .
		'<h2 class="ldk-h2">' . ldk_site_hl( $s['title'] ) . '</h2><p class="ldk-lead">' . esc_html( $s['text'] ) . '</p>' .
		'<div class="ldk-actions c">' . ldk_site_btn( $s['btn1'], $s['btn1_u'] ) . ldk_site_btn( $s['btn2'], $s['btn2_u'], true ) . '</div></div></div></section>';
}

function ldk_site_form_fields_html( $tipo ) {
	return '<form class="ldk-form" data-ldk-form data-wa="1" data-tipo="' . esc_attr( $tipo ) . '" novalidate>' .
		'<div class="row2"><label><span>Nome</span><input name="Nome" required autocomplete="name" placeholder="Seu nome"></label>' .
		'<label><span>Empresa</span><input name="Empresa" autocomplete="organization" placeholder="Nome da empresa"></label></div>' .
		'<div class="row2"><label><span>E-mail</span><input name="E-mail" type="email" autocomplete="email" placeholder="voce@empresa.com"></label>' .
		'<label><span>WhatsApp</span><input name="WhatsApp" type="tel" required autocomplete="tel" inputmode="tel" placeholder="(12) 90000-0000"></label></div>' .
		'<label><span>Interesse</span><select name="Interesse">' . ldk_site_services_options() . '</select></label>' .
		'<label><span>Mensagem</span><textarea name="Mensagem" rows="4" placeholder="Conte um pouco sobre o seu negócio e o que você busca"></textarea></label>' .
		'<input class="hp" type="text" name="website" tabindex="-1" autocomplete="off" aria-hidden="true">' .
		'<label class="chk"><input type="checkbox" name="lgpd" required><span>Concordo em ser contatado pela LDK sobre a minha solicitação.</span></label>' .
		'<button class="ldk-btn" type="submit"><span>Enviar e chamar no WhatsApp</span>' . ldk_site_ico( 'wa' ) . '</button>' .
		'<p class="fmsg" role="status" aria-live="polite"></p></form>';
}

function ldk_site_r_contact( $s ) {
	$ig   = ldk_site_opt( 'instagram' );
	$mail = ldk_site_opt( 'email' );
	$tile = function ( $ico, $label, $val, $href = '', $ext = false ) {
		$in = '<span class="ci">' . ldk_site_ico( $ico ) . '</span><span class="ct"><b>' . esc_html( $label ) . '</b><span>' . esc_html( $val ) . '</span></span>' . ( $href ? '<span class="go">' . ldk_site_ico( 'up' ) . '</span>' : '' );
		return $href ? '<li><a href="' . esc_url( $href ) . '"' . ( $ext ? ' target="_blank" rel="noopener"' : '' ) . '>' . $in . '</a></li>' : '<li><span class="a">' . $in . '</span></li>';
	};
	$list  = $tile( 'wa', 'WhatsApp', ldk_site_opt( 'whatsapp_fmt', '(12) 98825-7644' ), ldk_site_wa( 'Olá! Vim pelo site da LDK.' ) );
	$list .= $tile( 'ig', 'Instagram', '@ldkmarketingdigital', $ig, true );
	if ( $mail ) {
		$list .= $tile( 'mail', 'E-mail', $mail, 'mailto:' . $mail );
	}
	$list .= $tile( 'pin', 'Onde estamos', ldk_site_opt( 'cidade' ) . ' · atendemos todo o Brasil' );
	$list .= $tile( 'clock', 'Retorno', 'Em até 1 dia útil' );
	$map   = '';
	if ( ! empty( $s['mapa'] ) ) {
		$map = '<div class="ldk-map" data-r><iframe title="Mapa" loading="lazy" referrerpolicy="no-referrer-when-downgrade" src="' . esc_url( 'https://www.google.com/maps?q=' . rawurlencode( $s['mapa'] ) . '&output=embed' ) . '"></iframe></div>';
	}
	return '<section class="ldk-sec"><div class="ldk-wrap"><div class="ldk-contactgrid"><div class="cl">' . ldk_site_head( $s['eyebrow'], $s['title'], $s['text'] ) .
		'<ul class="ldk-info" data-r>' . $list . '</ul>' . $map . '</div>' .
		'<div class="ldk-formcard" data-r="right"><h3>Envie sua mensagem</h3><p class="sub">Os dados chegam para a equipe e a conversa abre no WhatsApp.</p>' . ldk_site_form_fields_html( $s['tipo'] ) . '</div></div></div></section>';
}

function ldk_site_diag_questions() {
	return array(
		array( 'Segmento', 'Em qual segmento você atua?', array( 'Saúde e bem-estar', 'Imobiliário e construção', 'Comércio e varejo', 'Serviços e profissionais liberais', 'Indústria e B2B', 'Outro' ), false ),
		array( 'Momento da empresa', 'Como você descreve o momento da empresa?', array( 'Estou começando', 'Vendo, mas de forma irregular', 'Estruturada e quero escalar' ), true ),
		array( 'Frequência de postagem', 'Com que frequência você posta?', array( 'Quase nunca', 'Quando dá tempo', 'Toda semana', 'Com calendário fixo' ), true ),
		array( 'Investimento mensal em anúncios', 'Quanto investe em anúncios por mês?', array( 'Nada ainda', 'Até R$ 500', 'De R$ 500 a R$ 2.000', 'Acima de R$ 2.000' ), true ),
		array( 'Acompanha resultados', 'Como você acompanha os resultados?', array( 'Não acompanho', 'Olho curtidas e seguidores', 'Vejo contatos e vendas', 'Tenho relatório mensal' ), true ),
		array( 'Objetivo principal', 'Qual é o seu principal objetivo agora?', array( 'Mais clientes e contatos', 'Fortalecer a marca', 'Vender online', 'Lançar um produto ou serviço' ), false ),
	);
}

function ldk_site_r_diag( $s ) {
	$qs   = ldk_site_diag_questions();
	$n    = count( $qs );
	$step = '';
	foreach ( $qs as $i => $q ) {
		$step .= '<fieldset class="dq" data-q="' . $i . '" data-label="' . esc_attr( $q[0] ) . '" data-score="' . ( $q[3] ? 1 : 0 ) . '"' . ( $i ? ' hidden' : '' ) . '><legend>' . esc_html( $q[1] ) . '</legend><div class="opts">';
		foreach ( $q[2] as $k => $o ) {
			$step .= '<label class="opt"><input type="radio" name="q' . $i . '" value="' . esc_attr( $o ) . '" data-pts="' . $k . '"><span>' . esc_html( $o ) . '</span></label>';
		}
		$step .= '</div></fieldset>';
	}
	$final = '<fieldset class="dq" data-q="' . $n . '" hidden><legend>Para onde enviamos o seu diagnóstico?</legend>' .
		'<div class="row2"><label>Nome<input name="Nome" required autocomplete="name"></label><label>Empresa<input name="Empresa" autocomplete="organization"></label></div>' .
		'<div class="row2"><label>WhatsApp<input name="WhatsApp" type="tel" required inputmode="tel" autocomplete="tel" placeholder="(12) 90000-0000"></label><label>E-mail<input name="E-mail" type="email" required autocomplete="email"></label></div>' .
		'<label>Link do seu Instagram (opcional)<input name="Instagram" placeholder="@seuperfil"></label>' .
		'<input class="hp" type="text" name="website" tabindex="-1" autocomplete="off" aria-hidden="true">' .
		'<label class="chk"><input type="checkbox" name="lgpd" required><span>Concordo em ser contatado pela LDK sobre o meu diagnóstico.</span></label></fieldset>';
	return '<section class="ldk-sec" id="diagnostico"><div class="ldk-wrap"><div class="ldk-diagbox" data-r="zoom">' .
		'<header class="ldk-head c"><span class="ldk-eyebrow">Diagnóstico gratuito</span><h2 class="ldk-h2">' . ldk_site_hl( $s['title'] ) . '</h2><p class="ldk-lead">' . esc_html( $s['text'] ) . '</p></header>' .
		'<form class="ldk-quiz" data-ldk-quiz data-total="' . $n . '" novalidate>' .
		'<div class="qbar"><i style="width:0"></i></div><p class="qcount"><span data-qn>1</span> de ' . ( $n + 1 ) . '</p>' . $step . $final .
		'<div class="qnav"><button type="button" class="ldk-btn ghost" data-prev hidden>Voltar</button><button type="button" class="ldk-btn" data-next disabled><span>Continuar</span>' . ldk_site_ico( 'arrow' ) . '</button></div>' .
		'<p class="fmsg" role="status" aria-live="polite"></p></form>' .
		'<div class="ldk-result" data-result hidden><div class="gauge"><svg viewBox="0 0 120 120"><circle cx="60" cy="60" r="52"/><circle class="v" cx="60" cy="60" r="52"/></svg><strong data-score>0</strong></div>' .
		'<h3 data-level></h3><p data-leveltxt></p><ul class="steps" data-next-steps></ul>' .
		'<div class="ldk-actions c">' . ldk_site_btn( 'Falar com a equipe no WhatsApp', 'wa' ) . '</div></div>' .
		'</div></div></section>';
}

function ldk_site_r_posts( $s ) {
	$posts = array();
	if ( function_exists( 'get_posts' ) ) {
		$posts = get_posts( array( 'numberposts' => max( 1, (int) $s['count'] ), 'post_status' => 'publish' ) );
	}
	if ( ! $posts ) {
		return '<section class="ldk-sec tight"><div class="ldk-wrap"><p class="ldk-lead c">Em breve, novos artigos por aqui.</p></div></section>';
	}
	$h = '<section class="ldk-sec tight"><div class="ldk-wrap"><div class="ldk-grid g3 posts">';
	foreach ( $posts as $i => $p ) {
		$img = get_the_post_thumbnail_url( $p, 'large' );
		$cat = get_the_category( $p->ID );
		$h  .= '<a class="ldk-post" href="' . esc_url( get_permalink( $p ) ) . '" data-r style="--d:' . ( ( $i % 3 ) * 0.08 ) . 's">' .
			'<span class="th">' . ldk_site_imgtag( $img ) . '</span>' .
			'<span class="bd"><small>' . esc_html( ( $cat ? $cat[0]->name . ' · ' : '' ) . get_the_date( 'd/m/Y', $p ) ) . '</small>' .
			'<h3>' . esc_html( get_the_title( $p ) ) . '</h3><p>' . esc_html( wp_trim_words( get_the_excerpt( $p ), 22 ) ) . '</p></span></a>';
	}
	return $h . '</div></div></section>';
}

function ldk_site_r_links( $s ) {
	$h = '<section class="ldk-linkpage"><div class="lp-card"><div class="lp-av" data-r="zoom">' . ldk_site_mark() . '</div>' .
		'<h1 data-r style="--d:.05s">' . esc_html( $s['name'] ) . '</h1><p data-r style="--d:.1s">' . esc_html( $s['bio'] ) . '</p><div class="lp-list">';
	foreach ( (array) $s['items'] as $i => $it ) {
		$u  = ldk_site_link( $it['url'] );
		$h .= '<a class="lp-link' . ( 0 === $i ? ' main' : '' ) . '" href="' . esc_url( $u ) . '" data-r style="--d:' . ( 0.12 + $i * 0.06 ) . 's"' . ( 0 === strpos( $u, 'http' ) && false === strpos( $u, home_url() ) ? ' target="_blank" rel="noopener"' : '' ) . '>' .
			'<span class="lic">' . ldk_site_ico( isset( $it['icon'] ) ? $it['icon'] : 'link' ) . '</span><span class="tx"><b>' . esc_html( $it['title'] ) . '</b><small>' . esc_html( $it['sub'] ) . '</small></span><span class="go">' . ldk_site_ico( 'arrow' ) . '</span></a>';
	}
	return $h . '</div><p class="lp-foot">© ' . gmdate( 'Y' ) . ' LDK Marketing Digital · ' . esc_html( ldk_site_opt( 'cidade' ) ) . '</p></div></section>';
}

function ldk_site_r_faq( $s ) {
	$h = '<section class="ldk-sec tight"><div class="ldk-wrap narrow"><h2 class="ldk-h2 c" data-r>' . ldk_site_hl( $s['title'] ) . '</h2><div class="ldk-faq">';
	foreach ( (array) $s['items'] as $i => $it ) {
		$h .= '<details data-r style="--d:' . ( $i * 0.05 ) . 's"><summary>' . esc_html( $it['q'] ) . '<i></i></summary><p>' . esc_html( $it['a'] ) . '</p></details>';
	}
	return $h . '</div></div></section>';
}

function ldk_site_r_pains( $s ) {
	$h = '<section class="ldk-sec tight"><div class="ldk-wrap"><h2 class="ldk-h2 c" data-r>' . ldk_site_hl( $s['title'] ) . '</h2><div class="ldk-grid g4 mt">';
	foreach ( (array) $s['items'] as $i => $it ) {
		$h .= '<div class="ldk-card flat" data-r style="--d:' . ( $i * 0.07 ) . 's"><span class="ldk-ico">' . ldk_site_ico( isset( $it['icon'] ) ? $it['icon'] : 'star' ) . '</span><h3>' . esc_html( $it['title'] ) . '</h3><p>' . esc_html( $it['text'] ) . '</p></div>';
	}
	return $h . '</div></div></section>';
}

function ldk_site_r_testimonials( $s ) {
	$h = '<section class="ldk-sec tight"><div class="ldk-wrap"><h2 class="ldk-h2 c" data-r>' . ldk_site_hl( $s['title'] ) . '</h2><div class="ldk-grid ' . ( count( (array) $s['items'] ) > 1 ? 'g2' : 'g1' ) . ' mt">';
	foreach ( (array) $s['items'] as $i => $it ) {
		$h .= '<figure class="ldk-card flat quote" data-r style="--d:' . ( $i * 0.07 ) . 's"><div class="stars" aria-label="5 estrelas">' . str_repeat( ldk_site_ico( 'star' ), 5 ) . '</div><blockquote>' . esc_html( $it['text'] ) . '</blockquote><figcaption><b>' . esc_html( $it['name'] ) . '</b> · ' . esc_html( $it['role'] ) . '</figcaption></figure>';
	}
	return $h . '</div></div></section>';
}

function ldk_site_r_social( $s ) {
	$img = ldk_site_img( $s['image'] );
	return '<section class="ldk-sec tight"><div class="ldk-wrap"><div class="ldk-social" data-r="zoom">' . ( $img ? '<img src="' . esc_url( $img ) . '" alt="" loading="lazy">' : '' ) .
		'<div class="tx"><h2 class="ldk-h2">' . ldk_site_hl( $s['title'] ) . '</h2><p class="ldk-lead">' . esc_html( $s['text'] ) . '</p>' .
		'<a class="ldk-btn" href="' . esc_url( ldk_site_opt( 'instagram' ) ) . '" target="_blank" rel="noopener"><span>' . esc_html( $s['btn'] ) . '</span>' . ldk_site_ico( 'ig' ) . '</a></div></div></div></section>';
}

function ldk_site_r_feed( $s ) {
	$ig   = ldk_site_opt( 'instagram' );
	$grid = '';
	foreach ( (array) $s['items'] as $i => $it ) {
		$im = ldk_site_img( isset( $it['image'] ) ? $it['image'] : '' );
		if ( $im ) {
			$grid .= '<a class="ft" href="' . esc_url( $ig ) . '" target="_blank" rel="noopener" aria-label="Ver no Instagram" data-r style="--d:' . ( ( $i % 3 ) * 0.07 ) . 's">' . ldk_site_imgtag( $im ) . '<span class="ov">' . ldk_site_ico( 'ig' ) . '</span></a>';
		}
	}
	return '<section class="ldk-sec"><div class="ldk-wrap">' . ldk_site_head( $s['eyebrow'], $s['title'], $s['text'] ) .
		'<div class="ldk-feed"><div class="fp" data-r><div class="fh"><span class="av">' . ldk_site_mark() . '</span><span><b>' . esc_html( $s['handle'] ) . '</b><small>LDK Marketing Digital</small></span></div>' .
		'<p>Estratégia, criação e tráfego pago para marcas que querem aparecer.</p>' .
		'<a class="ldk-btn" href="' . esc_url( $ig ) . '" target="_blank" rel="noopener"><span>' . esc_html( $s['btn'] ) . '</span>' . ldk_site_ico( 'ig' ) . '</a>' .
		'<div class="fly f-ig" data-par="-.1">' . ldk_site_ig_logo() . '</div></div>' .
		'<div class="fg">' . $grid . '</div></div></div></section>';
}

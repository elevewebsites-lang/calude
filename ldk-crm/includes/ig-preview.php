<?php
/**
 * Simulação do Instagram para o cliente aprovar: o post como vai aparecer (feed, carrossel, Reels ou Story),
 * com a arte, a legenda final (com hashtags) e a aba "No perfil", que mostra a grade com este post em primeiro.
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Legenda com #hashtags e @menções em destaque (já escapada). */
function lk_ig_caption_html( $text ) {
	$h = esc_html( (string) $text );
	$h = preg_replace( '/(?<![\p{L}\p{N}_&])(#[\p{L}\p{N}_]+|@[\p{L}\p{N}_.]+)/u', '<span class="ig-tag">$1</span>', $h );
	return nl2br( $h );
}

/** Foto do perfil na simulação: logo do cliente > foto do Instagram > iniciais. */
function lk_ig_avatar_html( $client, $ig, $class = '' ) {
	$img = ! empty( $client->logo ) ? $client->logo : ( $ig && $ig->avatar ? $ig->avatar : '' );
	$ini = esc_html( mb_strtoupper( mb_substr( lk_client_label( $client ), 0, 1 ) ) );
	return '<span class="ig-av ' . esc_attr( $class ) . '"><span class="ig-av-in">' . $ini . ( $img ? '<img src="' . esc_url( $img ) . '" alt="" onerror="this.remove()">' : '' ) . '</span></span>';
}

function lk_ig_icon( $name ) {
	$icons = array(
		'heart'   => '<path d="M16.8 3.6c-1.9 0-3.4 1-4.8 2.8C10.6 4.6 9.1 3.6 7.2 3.6 4.3 3.6 2 6 2 9c0 5.2 5.2 9 10 12.4C16.8 18 22 14.200 22 9c0-3-2.300-5.400-5.200-5.400z"/>',
		'comment' => '<path d="M21 11.500a8.400 8.400 0 0 1-.9 3.800 8.500 8.500 0 0 1-7.600 4.700 8.400 8.400 0 0 1-3.800-.9L3 21l1.900-5.700a8.400 8.400 0 0 1-.9-3.800 8.500 8.500 0 0 1 4.700-7.600 8.400 8.400 0 0 1 3.800-.9h.5a8.500 8.500 0 0 1 8 8z"/>',
		'send'    => '<path d="m22 2-7 20-4-9-9-4z"/><path d="M22 2 11 13"/>',
		'save'    => '<path d="M19 21l-7-5-7 5V5a2 2 0 0 1 2-2h10a2 2 0 0 1 2 2z"/>',
		'music'   => '<path d="M9 18V5l12-2v13"/><circle cx="6" cy="18" r="3"/><circle cx="18" cy="16" r="3"/>',
		'dots'    => '<circle cx="5" cy="12" r="1.600"/><circle cx="12" cy="12" r="1.600"/><circle cx="19" cy="12" r="1.600"/>',
	);
	return '<svg viewBox="0 0 24 24" width="24" height="24" fill="none" stroke="currentColor" stroke-width="1.800" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' . ( $icons[ $name ] ?? '' ) . '</svg>';
}

/** Um item de mídia (img/video) para a simulação. */
function lk_ig_media_el( $p, $i, $m, $video_attrs = 'controls playsinline preload="metadata"' ) {
	$src = lk_media_src( $p, $i, $m );
	if ( ! $src ) {
		return '<iframe src="' . esc_url( 'https://drive.google.com/file/d/' . rawurlencode( $m['id'] ) . '/preview' ) . '" allow="autoplay" loading="lazy"></iframe>';
	}
	if ( 'video' === $m['type'] ) {
		return '<video src="' . esc_url( $src ) . '" ' . $video_attrs . ' data-mi="' . (int) $i . '"></video>';
	}
	return '<img src="' . esc_url( $src ) . '" alt="Arte ' . esc_attr( $p->title ) . '">';
}

/**
 * HTML completo da simulação. $nav = array( 'pos' => n, 'total' => n, 'prev' => url, 'next' => url ) (opcional).
 */
function lk_ig_preview_html( $p, $client, $media, $ig, $nav = array() ) {
	$handle = $ig && $ig->username ? $ig->username : ( $client && $client->instagram ? $client->instagram : sanitize_title( lk_client_label( $client ) ) );
	$fmt    = $p->format;
	$cap    = lk_post_final_caption( $p );
	$when   = $p->scheduled_at ? mb_strtoupper( lk_month_name( (int) gmdate( 'n', strtotime( $p->scheduled_at ) ) ) ) : '';
	$when   = $p->scheduled_at ? (int) gmdate( 'j', strtotime( $p->scheduled_at ) ) . ' DE ' . $when : 'EM BREVE';
	$av     = lk_ig_avatar_html( $client, $ig, 'ig-av--ring' );
	$o      = '<div class="ig-wrap" data-ig>';
	$o     .= '<div class="ig-note">✨ É assim que o post vai aparecer no Instagram de <strong>@' . esc_html( $handle ) . '</strong>' . ( $p->scheduled_at ? ' em <strong>' . esc_html( lk_date( $p->scheduled_at, 'd/m' ) . ' às ' . substr( $p->scheduled_at, 11, 5 ) ) . '</strong>' : '' ) . '.</div>';
	if ( ! empty( $nav['total'] ) && $nav['total'] > 1 ) {
		$o .= '<div class="ig-nav">' . ( ! empty( $nav['prev'] ) ? '<a href="' . esc_url( $nav['prev'] ) . '">← anterior</a>' : '<span></span>' ) . '<span>' . (int) $nav['pos'] . ' de ' . (int) $nav['total'] . ' para aprovar</span>' . ( ! empty( $nav['next'] ) ? '<a href="' . esc_url( $nav['next'] ) . '">próximo →</a>' : '<span></span>' ) . '</div>';
	}
	$o .= '<div class="ig-tabs" role="tablist"><button type="button" class="is-on" data-igtab="post">' . esc_html( array( 'reels' => 'Reels', 'story' => 'Story' )[ $fmt ] ?? 'Post' ) . '</button><button type="button" data-igtab="perfil">No perfil</button></div>';
	$o .= '<div data-igpanel="post">';

	if ( ! $media ) {
		$o .= '<div class="ig-empty">A arte ainda não foi enviada.</div>';
	} elseif ( 'story' === $fmt ) {
		$o .= '<div class="ig-phone"><div class="ig-screen ig-story"><div class="ig-bars"><i class="on"></i></div><div class="ig-sh">' . $av . '<b>' . esc_html( $handle ) . '</b><small>agora</small></div><div class="ig-fill">' . lk_ig_media_el( $p, 0, $media[0], 'playsinline preload="metadata" controls' ) . '</div><div class="ig-sf"><span>Enviar mensagem</span>' . lk_ig_icon( 'heart' ) . lk_ig_icon( 'send' ) . '</div></div></div>';
		if ( $cap ) {
			$o .= '<p class="ig-hint">Stories não mostram legenda; ela fica guardada com o post.</p>';
		}
	} elseif ( 'reels' === $fmt ) {
		$o .= '<div class="ig-phone"><div class="ig-screen ig-reel"><div class="ig-fill">' . lk_ig_media_el( $p, 0, $media[0], 'playsinline preload="metadata" controls loop' ) . '</div><div class="ig-rside">' . lk_ig_icon( 'heart' ) . '<small>•</small>' . lk_ig_icon( 'comment' ) . '<small>•</small>' . lk_ig_icon( 'send' ) . lk_ig_icon( 'dots' ) . '</div>';
		$o .= '<div class="ig-rbot"><div class="ig-ru">' . $av . '<b>' . esc_html( $handle ) . '</b><em>Seguir</em></div><div class="ig-rcap" data-caption>' . ( $cap ? lk_ig_caption_html( $cap ) : '<span class="ig-dim">Sem legenda.</span>' ) . '</div><div class="ig-rau">' . lk_ig_icon( 'music' ) . '<span>Áudio original · ' . esc_html( $handle ) . '</span></div></div></div></div>';
	} else {
		$o .= '<article class="ig-card"><header class="ig-h">' . $av . '<span class="ig-hn"><b>' . esc_html( $handle ) . '</b></span><span class="ig-dots">' . lk_ig_icon( 'dots' ) . '</span></header>';
		$o .= '<div class="apx-media ig-media"><div class="apx-slides" data-slides>';
		foreach ( $media as $i => $m ) {
			$o .= '<div class="apx-slide">' . lk_ig_media_el( $p, $i, $m ) . '</div>';
		}
		$o .= '</div>';
		if ( count( $media ) > 1 ) {
			$o .= '<span class="apx-count" data-count>1/' . count( $media ) . '</span>';
		}
		$o .= '</div>';
		if ( count( $media ) > 1 ) {
			$o .= '<div class="apx-pager ig-pager" data-pager>';
			foreach ( $media as $i => $m ) {
				$o .= '<i class="' . ( 0 === $i ? 'is-on' : '' ) . '"></i>';
			}
			$o .= '</div>';
		}
		$o .= '<div class="ig-acts"><span>' . lk_ig_icon( 'heart' ) . lk_ig_icon( 'comment' ) . lk_ig_icon( 'send' ) . '</span><span>' . lk_ig_icon( 'save' ) . '</span></div>';
		$o .= '<div class="ig-likes">Curtido por <b>seus seguidores</b> e <b>outras pessoas</b></div>';
		$o .= '<div class="ig-cap apx-caption" data-caption><b>' . esc_html( $handle ) . '</b> ' . ( $cap ? lk_ig_caption_html( $cap ) : '<span class="ig-dim">Sem legenda.</span>' ) . '</div><button type="button" class="apx-more ig-more" data-more hidden>mais</button>';
		$o .= '<div class="ig-com">Ver todos os comentários</div><div class="ig-date">' . esc_html( $when ) . '</div></article>';
	}
	$o .= '</div>';

	// Aba "No perfil": a grade com este post em primeiro.
	$recent = array();
	foreach ( lk_posts( 'p.client_id = %d AND p.stage = %s AND p.id <> %d', array( $p->client_id, lk_stage_for( 'publicado' ), $p->id ), 'p.scheduled_at DESC LIMIT 8' ) as $rp ) {
		$recent[] = $rp;
	}
	$extra     = $ig ? json_decode( (string) $ig->extra, true ) : array();
	$followers = ! empty( $extra['followers'] ) ? number_format_i18n( (int) $extra['followers'] ) : '—';
	$npub      = count( lk_posts( 'p.client_id = %d AND p.stage = %s', array( $p->client_id, lk_stage_for( 'publicado' ) ) ) );
	$tile      = function ( $rp, $new = false ) {
		$m = lk_post_media( $rp );
		$inner = '<span class="ig-ph0"></span>';
		if ( $m ) {
			$src = lk_media_src( $rp, 0, $m[0] );
			if ( $src ) {
				$inner = 'video' === $m[0]['type'] ? '<video src="' . esc_url( $src ) . '#t=0.1" muted playsinline preload="metadata"></video>' : '<img src="' . esc_url( $src ) . '" alt="" loading="lazy">';
			}
		}
		return '<div class="ig-tile' . ( $new ? ' is-new' : '' ) . '">' . $inner . ( $new ? '<em>Novo</em>' : '' ) . ( $m && 'video' === $m[0]['type'] ? '<i>▶</i>' : '' ) . '</div>';
	};
	$o .= '<div data-igpanel="perfil" hidden><div class="ig-profile"><div class="ig-ph">' . lk_ig_avatar_html( $client, $ig, 'ig-av--xl ig-av--ring' ) . '<div class="ig-stats"><div><b>' . ( $npub + 1 ) . '</b>publicações</div><div><b>' . esc_html( $followers ) . '</b>seguidores</div><div><b>—</b>seguindo</div></div></div>';
	$o .= '<div class="ig-bio"><b>' . esc_html( lk_client_label( $client ) ) . '</b>' . ( $client->site ? '<span>' . esc_html( preg_replace( '#^https?://(www\.)?#', '', rtrim( $client->site, '/' ) ) ) . '</span>' : '' ) . '</div>';
	$o .= '<div class="ig-grid">' . $tile( $p, true );
	foreach ( $recent as $rp ) {
		$o .= $tile( $rp );
	}
	return $o . '</div></div></div><script>(function(){var w=document.currentScript.parentNode;if(!w)return;w.addEventListener("click",function(e){var b=e.target.closest("[data-igtab]");if(!b)return;w.querySelectorAll("[data-igtab]").forEach(function(x){x.classList.toggle("is-on",x===b)});w.querySelectorAll("[data-igpanel]").forEach(function(p){p.hidden=p.getAttribute("data-igpanel")!==b.getAttribute("data-igtab")});});})();</script></div>';
}

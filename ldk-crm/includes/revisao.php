<?php
/**
 * Revisão do texto antes de postar + hashtags.
 *
 *  - Regras locais (sempre): limite de 2.200 caracteres e 30 hashtags do Instagram, espaços duplos, MAIÚSCULAS demais,
 *    link na legenda, hashtag com hífen, "!!!", legenda vazia.
 *  - IA (se houver a chave da Anthropic em Configurações): ortografia, gramática, pontuação, tom e CONTEXTO
 *    (segmento do cliente, briefing, data e feriado da publicação). Devolve o texto corrigido e hashtags sugeridas.
 *  - Hashtags: campo próprio no post (+ padrão por cliente); na publicação entram no fim da legenda.
 *  - Vigia (a cada hora): posts marcados para as próximas 24 h que ainda não foram revisados são revisados e a equipe é avisada.
 *  - Na hora de publicar: estourar o limite do Instagram bloqueia o envio (volta para Revisão com o motivo).
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'LK_CAPTION_MAX', 2200 );
define( 'LK_HASHTAG_MAX', 30 );

/* -----------------------------------------------------------------------
 * Legenda final (texto + hashtags)
 * -------------------------------------------------------------------- */

function lk_hashtags_list( $text ) {
	preg_match_all( '/#[\p{L}\p{N}_]+/u', (string) $text, $m );
	return array_values( array_unique( array_map( 'mb_strtolower', $m[0] ) ) );
}

/** Legenda com as hashtags do campo no fim (sem repetir as que já estão no texto). */
function lk_post_final_caption( $p ) {
	$cap  = trim( (string) $p->caption );
	$tags = array();
	$have = lk_hashtags_list( $cap );
	foreach ( preg_split( '/\s+/', trim( (string) ( $p->hashtags ?? '' ) ) ) as $t ) {
		$t = '#' . ltrim( $t, '#' );
		if ( strlen( $t ) > 1 && ! in_array( mb_strtolower( $t ), $have, true ) ) {
			$tags[] = $t;
			$have[] = mb_strtolower( $t );
		}
	}
	return $tags ? $cap . "\n\n" . implode( ' ', $tags ) : $cap;
}

/* -----------------------------------------------------------------------
 * Regras locais
 * -------------------------------------------------------------------- */

/** @return array de array( 'erro'|'aviso', mensagem ) */
function lk_caption_rules( $caption, $hashtags = '' ) {
	$fake  = (object) array( 'caption' => $caption, 'hashtags' => $hashtags );
	$final = lk_post_final_caption( $fake );
	$out   = array();
	$len   = mb_strlen( $final );
	$tags  = count( lk_hashtags_list( $final ) );
	if ( '' === trim( (string) $caption ) ) {
		$out[] = array( 'aviso', 'A legenda está vazia.' );
	}
	if ( $len > LK_CAPTION_MAX ) {
		$out[] = array( 'erro', 'A legenda (com as hashtags) tem ' . $len . ' caracteres. O Instagram aceita no máximo ' . LK_CAPTION_MAX . '.' );
	}
	if ( $tags > LK_HASHTAG_MAX ) {
		$out[] = array( 'erro', 'São ' . $tags . ' hashtags. O Instagram aceita no máximo ' . LK_HASHTAG_MAX . ' (e o ideal é de 5 a 15).' );
	}
	if ( 0 === $tags && '' !== trim( (string) $caption ) ) {
		$out[] = array( 'aviso', 'Nenhuma hashtag. Quer adicionar algumas?' );
	}
	if ( preg_match( '/ {2,}/u', (string) $caption ) ) {
		$out[] = array( 'aviso', 'Tem espaços duplos no texto.' );
	}
	if ( preg_match( '/([!?])\1{2,}/u', (string) $caption ) ) {
		$out[] = array( 'aviso', 'Muitas exclamações/interrogações seguidas (!!! ou ???).' );
	}
	if ( preg_match( '#https?://#i', (string) $caption ) ) {
		$out[] = array( 'aviso', 'Link na legenda não é clicável no Instagram. Use "link na bio".' );
	}
	if ( preg_match( '/#[\p{L}\p{N}_]+[-.][\p{L}\p{N}_]+/u', (string) $hashtags . ' ' . (string) $caption ) ) {
		$out[] = array( 'aviso', 'Hashtag com hífen ou ponto: o Instagram corta nesse ponto (ex.: #dia-das-maes vira só #dia).' );
	}
	$words = preg_split( '/\s+/u', preg_replace( '/#\S+|@\S+/u', '', (string) $caption ), -1, PREG_SPLIT_NO_EMPTY );
	$caps  = count( array_filter( $words, function ( $w ) { return mb_strlen( $w ) > 3 && $w === mb_strtoupper( $w ) && preg_match( '/\p{L}/u', $w ); } ) );
	if ( $words && $caps / count( $words ) > 0.3 && $caps >= 4 ) {
		$out[] = array( 'aviso', 'Muito texto em MAIÚSCULAS (parece gritar).' );
	}
	return $out;
}

/* -----------------------------------------------------------------------
 * IA
 * -------------------------------------------------------------------- */

function lk_ai_ready() {
	return (bool) lk_decrypt( lk_setting( 'anthropic_key' ) );
}

/** Contexto do cliente para a IA. */
function lk_review_context( $client, $date = '' ) {
	$ctx = array();
	if ( $client ) {
		$ctx[] = 'Cliente: ' . lk_client_label( $client ) . ( $client->site ? ' (' . $client->site . ')' : '' ) . ( $client->instagram ? ', Instagram @' . $client->instagram : '' ) . '.';
		$b = lk_json( $client->briefing );
		if ( $b ) {
			$flat = array();
			array_walk_recursive( $b, function ( $v ) use ( &$flat ) { if ( is_string( $v ) && '' !== trim( $v ) ) { $flat[] = trim( $v ); } } );
			$ctx[] = 'Briefing do cliente: ' . mb_substr( implode( ' | ', $flat ), 0, 1500 );
		}
	}
	if ( $date ) {
		$names = array_map( function ( $d ) { return $d['name'] . ( 'feriado' === $d['type'] ? ' (feriado)' : '' ); }, function_exists( 'lk_dates_on' ) ? lk_dates_on( $date ) : array() );
		$ctx[] = 'Data de publicação: ' . lk_date( $date, 'd/m/Y' ) . ' (' . lk_dow_short( $date ) . ')' . ( $names ? ' — ' . implode( ', ', $names ) : '' ) . '.';
	}
	return implode( "\n", $ctx );
}

function lk_ai_review( $caption, $hashtags, $client, $date ) {
	$ask = "Você é revisor de textos de redes sociais em português do Brasil.\n"
		. lk_review_context( $client, $date ) . "\n\n"
		. "Revise a LEGENDA abaixo (Instagram) quanto a: ortografia, gramática, concordância, pontuação, clareza e CONTEXTO "
		. "(coerência com o cliente e o segmento, tom de voz, datas/feriados/valores/nomes que pareçam errados ou inconsistentes, promessas arriscadas). "
		. "Preserve a voz da marca, emojis, quebras de linha e hashtags; não reescreva o estilo à toa. "
		. "Responda SOMENTE JSON neste formato: {\"erros\":[{\"tipo\":\"ortografia|gramatica|pontuacao|contexto|tom\",\"trecho\":\"trecho exato\",\"problema\":\"o que está errado\",\"sugestao\":\"como ficar\"}],\"texto_corrigido\":\"a legenda inteira já corrigida\",\"hashtags_sugeridas\":[\"#...\"]}. "
		. "Sugira até 10 hashtags relevantes (sem hífen, sem repetir as já usadas). Se estiver tudo certo: erros vazio e texto_corrigido igual ao original.\n\n"
		. "LEGENDA:\n" . $caption . "\n\nHASHTAGS JÁ USADAS: " . ( $hashtags ? $hashtags : '(nenhuma)' );
	$res = wp_remote_post(
		'https://api.anthropic.com/v1/messages',
		array(
			'timeout' => 60,
			'headers' => array( 'x-api-key' => lk_decrypt( lk_setting( 'anthropic_key' ) ), 'anthropic-version' => '2023-06-01', 'content-type' => 'application/json' ),
			'body'    => wp_json_encode( array( 'model' => lk_setting( 'ai_model' ), 'max_tokens' => 2000, 'messages' => array( array( 'role' => 'user', 'content' => $ask ) ) ) ),
		)
	);
	if ( is_wp_error( $res ) ) {
		return $res;
	}
	$json = json_decode( wp_remote_retrieve_body( $res ), true );
	if ( empty( $json['content'][0]['text'] ) ) {
		return new WP_Error( 'lk', $json['error']['message'] ?? 'A IA não respondeu.' );
	}
	if ( ! preg_match( '/\{.*\}/s', $json['content'][0]['text'], $m ) ) {
		return new WP_Error( 'lk', 'Resposta da IA fora do formato.' );
	}
	$d = json_decode( $m[0], true );
	if ( ! is_array( $d ) ) {
		return new WP_Error( 'lk', 'Resposta da IA fora do formato.' );
	}
	$issues = array();
	foreach ( (array) ( $d['erros'] ?? array() ) as $e ) {
		if ( ! empty( $e['problema'] ) ) {
			$issues[] = array( 'tipo' => sanitize_key( $e['tipo'] ?? 'contexto' ), 'trecho' => sanitize_text_field( $e['trecho'] ?? '' ), 'problema' => sanitize_text_field( $e['problema'] ), 'sugestao' => sanitize_text_field( $e['sugestao'] ?? '' ) );
		}
	}
	$tags = array();
	foreach ( (array) ( $d['hashtags_sugeridas'] ?? array() ) as $t ) {
		$t = '#' . preg_replace( '/[^\p{L}\p{N}_]/u', '', ltrim( (string) $t, '#' ) );
		if ( mb_strlen( $t ) > 2 ) {
			$tags[] = $t;
		}
	}
	return array( 'issues' => $issues, 'corrected' => sanitize_textarea_field( $d['texto_corrigido'] ?? $caption ), 'tags' => array_values( array_unique( $tags ) ) );
}

/** Revisão completa (regras + IA) de um texto. */
function lk_review_text( $caption, $hashtags, $client, $date ) {
	$out = array( 'rules' => lk_caption_rules( $caption, $hashtags ), 'ai' => null, 'ai_note' => '', 'at' => lk_now(), 'hash' => md5( $caption . '|' . $hashtags ) );
	if ( '' === trim( (string) $caption ) ) {
		return $out;
	}
	if ( ! lk_ai_ready() ) {
		$out['ai_note'] = 'Para revisar português e contexto com IA, coloque a chave da Anthropic em Configurações.';
		return $out;
	}
	$ai = lk_ai_review( $caption, $hashtags, $client, $date );
	if ( is_wp_error( $ai ) ) {
		$out['ai_note'] = 'A IA não conseguiu revisar agora: ' . $ai->get_error_message();
	} else {
		$out['ai'] = $ai;
	}
	return $out;
}

/** Quantos pontos a revisão achou (regras + IA). */
function lk_review_count( $r ) {
	return count( (array) ( $r['rules'] ?? array() ) ) + count( (array) ( $r['ai']['issues'] ?? array() ) );
}

/* -----------------------------------------------------------------------
 * Botão "Revisar texto" (REST)
 * -------------------------------------------------------------------- */

add_action(
	'rest_api_init',
	function () {
		register_rest_route(
			'lk/v1',
			'/review',
			array(
				'methods'             => 'POST',
				'callback'            => 'lk_api_review',
				'permission_callback' => function () { return lk_can( 'conteudo' ); },
			)
		);
	}
);

function lk_api_review( WP_REST_Request $r ) {
	$caption  = sanitize_textarea_field( (string) $r['caption'] );
	$hashtags = sanitize_textarea_field( (string) $r['hashtags'] );
	$client   = (int) $r['client'] ? lk_get( 'clients', (int) $r['client'] ) : null;
	$date     = preg_match( '/^\d{4}-\d{2}-\d{2}$/', (string) $r['date'] ) ? (string) $r['date'] : '';
	$res      = lk_review_text( $caption, $hashtags, $client, $date );
	$post_id  = (int) $r['post'];
	if ( $post_id && lk_get( 'posts', $post_id ) ) {
		lk_update( 'posts', $post_id, array( 'review' => wp_json_encode( $res ) ) );
	}
	return $res;
}

/* -----------------------------------------------------------------------
 * Vigia (a cada hora) e trava na publicação
 * -------------------------------------------------------------------- */

add_action( 'lk_hourly', 'lk_review_watch' );
function lk_review_watch() {
	$n = 0;
	foreach ( lk_posts( 'p.scheduled_at BETWEEN %s AND %s AND p.caption <> %s', array( lk_now(), gmdate( 'Y-m-d H:i:s', strtotime( lk_now() . ' +24 hour' ) ), '' ), 'p.scheduled_at' ) as $p ) {
		if ( $p->stage === lk_stage_for( 'publicado' ) ) {
			continue;
		}
		$old = lk_json( $p->review );
		if ( ( $old['hash'] ?? '' ) === md5( $p->caption . '|' . ( $p->hashtags ?? '' ) ) ) {
			continue; // já revisado com este texto
		}
		if ( ++$n > 5 ) {
			break; // controla o gasto da IA por rodada
		}
		$res = lk_review_text( $p->caption, (string) $p->hashtags, lk_get( 'clients', $p->client_id ), substr( (string) $p->scheduled_at, 0, 10 ) );
		lk_update( 'posts', $p->id, array( 'review' => wp_json_encode( $res ) ) );
		$count = lk_review_count( $res );
		if ( $count ) {
			foreach ( array_unique( array_filter( array( (int) $p->social_id, (int) $p->atendimento_id ) ) ) as $uid ) {
				lk_notify( $uid, '📝 Revise o texto de "' . $p->title . '" (sai em menos de 24 h): ' . $count . ' ponto(s) para conferir.', lk_panel_url( 'post', $p->id ) );
			}
		}
	}
}

/** Erros que impedem de publicar (limites do Instagram). */
function lk_publish_blockers( $p ) {
	return array_values( array_map( function ( $r ) { return $r[1]; }, array_filter( lk_caption_rules( $p->caption, (string) $p->hashtags ), function ( $r ) { return 'erro' === $r[0]; } ) ) );
}

/** Painel do resultado (usado ao abrir o post com revisão guardada). */
function lk_review_summary_html( $p ) {
	$r = lk_json( $p->review );
	if ( ! $r || ( $r['hash'] ?? '' ) !== md5( $p->caption . '|' . ( $p->hashtags ?? '' ) ) ) {
		return '<p class="muted small">Texto ainda não revisado. Use <strong>Revisar texto</strong> antes de enviar para o cliente.</p>';
	}
	$n = lk_review_count( $r );
	return '<p class="small ' . ( $n ? 'text-late' : '' ) . '">' . ( $n ? '⚠️ A última revisão achou ' . (int) $n . ' ponto(s). Clique em Revisar texto para ver.' : '✅ Texto revisado, sem pontos de atenção.' ) . ' <span class="muted">(' . esc_html( lk_ago( $r['at'] ) ) . ')</span></p>';
}

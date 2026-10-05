<?php
/**
 * IA do CRM (Gemini, com a chave que a agência já tem; Anthropic como alternativa).
 *
 *  - lk_ai_call()/lk_ai_json(): uma camada só; usa o Gemini se houver chave, senão a Anthropic.
 *  - Post: sugerir legendas (3 opções), melhorar o texto, hashtags e briefing da arte para o designer.
 *  - Planejamento: ideias do mês (considera briefing, feriados e o que já foi postado) e adicionar ao planejamento.
 *  - Prospecção: mensagem de primeiro contato (WhatsApp ou e-mail).
 *  - Revisão do texto e ideias de conteúdo também passam por aqui.
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function lk_gemini_key() {
	return (string) lk_decrypt( lk_setting( 'gemini_key' ) );
}

function lk_ai_provider() {
	if ( lk_gemini_key() ) {
		return 'gemini';
	}
	return lk_decrypt( lk_setting( 'anthropic_key' ) ) ? 'anthropic' : '';
}

function lk_ai_ready() {
	return '' !== lk_ai_provider();
}

/** Chama a IA. $o: system, json (bool), temperature, max. Devolve o texto ou WP_Error. */
function lk_ai_call( $prompt, $o = array() ) {
	$o    = $o + array( 'system' => '', 'json' => false, 'temperature' => 0.8, 'max' => 8192 );
	$prov = lk_ai_provider();
	if ( ! $prov ) {
		return new WP_Error( 'lk', 'Coloque a chave do Gemini em Configurações → IA para usar este recurso.' );
	}
	if ( 'gemini' === $prov ) {
		$model = trim( (string) lk_setting( 'gemini_model' ) ) ?: 'gemini-2.5-flash';
		$body  = array(
			'contents'         => array( array( 'role' => 'user', 'parts' => array( array( 'text' => $prompt ) ) ) ),
			'generationConfig' => array( 'temperature' => (float) $o['temperature'], 'maxOutputTokens' => (int) $o['max'] ),
		);
		if ( $o['system'] ) {
			$body['systemInstruction'] = array( 'parts' => array( array( 'text' => $o['system'] ) ) );
		}
		if ( $o['json'] ) {
			$body['generationConfig']['responseMimeType'] = 'application/json';
		}
		$res = wp_remote_post( 'https://generativelanguage.googleapis.com/v1beta/models/' . rawurlencode( $model ) . ':generateContent', array( 'timeout' => 90, 'headers' => array( 'Content-Type' => 'application/json', 'x-goog-api-key' => lk_gemini_key() ), 'body' => wp_json_encode( $body ) ) );
		if ( is_wp_error( $res ) ) {
			return $res;
		}
		$json = json_decode( wp_remote_retrieve_body( $res ), true );
		if ( wp_remote_retrieve_response_code( $res ) >= 300 || isset( $json['error'] ) ) {
			return new WP_Error( 'lk', 'Gemini: ' . ( $json['error']['message'] ?? 'erro ' . wp_remote_retrieve_response_code( $res ) ) );
		}
		if ( ! empty( $json['promptFeedback']['blockReason'] ) ) {
			return new WP_Error( 'lk', 'O Gemini recusou este texto (' . $json['promptFeedback']['blockReason'] . '). Reformule e tente de novo.' );
		}
		$text = '';
		foreach ( (array) ( $json['candidates'][0]['content']['parts'] ?? array() ) as $part ) {
			if ( empty( $part['thought'] ) && isset( $part['text'] ) ) {
				$text .= $part['text'];
			}
		}
		return '' !== trim( $text ) ? trim( $text ) : new WP_Error( 'lk', 'O Gemini não devolveu texto. Tente de novo.' );
	}
	$res = wp_remote_post(
		'https://api.anthropic.com/v1/messages',
		array(
			'timeout' => 90,
			'headers' => array( 'x-api-key' => lk_decrypt( lk_setting( 'anthropic_key' ) ), 'anthropic-version' => '2023-06-01', 'content-type' => 'application/json' ),
			'body'    => wp_json_encode( array_filter( array( 'model' => lk_setting( 'ai_model' ), 'max_tokens' => min( 4096, (int) $o['max'] ), 'temperature' => (float) $o['temperature'], 'system' => $o['system'], 'messages' => array( array( 'role' => 'user', 'content' => $prompt ) ) ) ) ),
		)
	);
	if ( is_wp_error( $res ) ) {
		return $res;
	}
	$json = json_decode( wp_remote_retrieve_body( $res ), true );
	return ! empty( $json['content'][0]['text'] ) ? trim( $json['content'][0]['text'] ) : new WP_Error( 'lk', $json['error']['message'] ?? 'A IA não respondeu.' );
}

/** Igual a lk_ai_call, mas devolve o JSON já decodificado (objeto ou lista). */
function lk_ai_json( $prompt, $o = array() ) {
	$t = lk_ai_call( $prompt, array( 'json' => true ) + $o );
	if ( is_wp_error( $t ) ) {
		return $t;
	}
	$t = preg_replace( '/^```(?:json)?\s*|\s*```$/i', '', trim( $t ) );
	$d = json_decode( $t, true );
	if ( null === $d && preg_match( '/(\{.*\}|\[.*\])/s', $t, $m ) ) {
		$d = json_decode( $m[1], true );
	}
	return is_array( $d ) ? $d : new WP_Error( 'lk', 'A IA respondeu fora do formato. Tente de novo.' );
}

const LK_AI_SYSTEM = 'Você é social media sênior de uma agência brasileira de marketing digital. Escreva sempre em português do Brasil, com linguagem natural, correta e sem exageros. Nunca invente preços, descontos, prazos, endereços ou resultados que não tenham sido informados. Respeite o tom de voz e a identidade da marca do cliente.';

/** Dados do cliente que a IA precisa saber. */
function lk_ai_client_context( $client, $date = '' ) {
	if ( ! $client ) {
		return '';
	}
	$ctx = array( 'Cliente: ' . lk_client_label( $client ) . ( $client->site ? ' — site ' . $client->site : '' ) . ( $client->instagram ? ' — Instagram @' . $client->instagram : '' ) . ( $client->city ? ' — ' . $client->city : '' ) );
	$b = lk_json( $client->briefing );
	if ( $b ) {
		$parts = array();
		foreach ( $b as $r ) {
			if ( is_array( $r ) && ! empty( $r['a'] ) ) {
				$parts[] = $r['q'] . ' ' . trim( (string) $r['a'] );
			}
		}
		$ctx[] = 'Briefing do cliente: ' . mb_substr( implode( ' | ', $parts ), 0, 2500 );
	}
	if ( ! empty( $client->hashtags ) ) {
		$ctx[] = 'Hashtags que o cliente já usa: ' . $client->hashtags;
	}
	if ( $date && function_exists( 'lk_dates_on' ) ) {
		$names = array_map( function ( $d ) { return $d['name'] . ( 'feriado' === $d['type'] ? ' (feriado)' : '' ); }, lk_dates_on( $date ) );
		$ctx[] = 'Data de publicação: ' . lk_date( $date, 'd/m/Y' ) . ( $names ? ' — ' . implode( ', ', $names ) : '' );
	}
	return implode( "\n", $ctx );
}

function lk_ai_tones() {
	return array( '' => 'Tom do briefing', 'profissional' => 'Profissional', 'descontraido' => 'Descontraído', 'inspirador' => 'Inspirador', 'vendedor' => 'Vendedor (com chamada para ação)', 'educativo' => 'Educativo' );
}

/* -----------------------------------------------------------------------
 * REST
 * -------------------------------------------------------------------- */

add_action(
	'rest_api_init',
	function () {
		$post = function () { return lk_can( 'conteudo' ); };
		register_rest_route( 'lk/v1', '/ai/caption', array( 'methods' => 'POST', 'callback' => 'lk_api_ai_caption', 'permission_callback' => $post ) );
		register_rest_route( 'lk/v1', '/ai/plan', array( 'methods' => 'POST', 'callback' => 'lk_api_ai_plan', 'permission_callback' => $post ) );
		register_rest_route( 'lk/v1', '/ai/plan-add', array( 'methods' => 'POST', 'callback' => 'lk_api_ai_plan_add', 'permission_callback' => $post ) );
		register_rest_route( 'lk/v1', '/ai/pitch', array( 'methods' => 'POST', 'callback' => 'lk_api_ai_pitch', 'permission_callback' => function () { return lk_can( 'leads' ); } ) );
	}
);

function lk_ai_err( $e ) {
	return new WP_Error( 'lk', $e->get_error_message(), array( 'status' => 502 ) );
}

function lk_api_ai_caption( WP_REST_Request $r ) {
	$mode    = sanitize_key( (string) $r['mode'] );
	$caption = sanitize_textarea_field( (string) $r['caption'] );
	$idea    = sanitize_textarea_field( (string) $r['idea'] );
	$title   = sanitize_text_field( (string) $r['title'] );
	$fmt     = lk_formats()[ sanitize_key( (string) $r['format'] ) ] ?? 'post';
	$tone    = lk_ai_tones()[ sanitize_key( (string) $r['tone'] ) ] ?? '';
	$client  = (int) $r['client'] ? lk_get( 'clients', (int) $r['client'] ) : null;
	$date    = preg_match( '/^\d{4}-\d{2}-\d{2}$/', (string) $r['date'] ) ? (string) $r['date'] : '';
	$ctx     = lk_ai_client_context( $client, $date ) . "\nFormato do conteúdo: " . $fmt . ( $title ? "\nTítulo do post: " . $title : '' ) . ( $idea ? "\nIdeia do post: " . $idea : '' ) . ( $tone && sanitize_key( (string) $r['tone'] ) ? "\nTom de voz pedido: " . $tone : '' );
	if ( 'sugerir' === $mode ) {
		$out = lk_ai_json( $ctx . ( $caption ? "\nRascunho atual da legenda: " . $caption : '' ) . "\n\nCrie 3 opções DIFERENTES de legenda para Instagram para este post (uma mais curta e direta, uma média e uma mais completa/contando história). Use quebras de linha, poucos emojis, termine com uma chamada para ação adequada. NÃO coloque hashtags na legenda. Responda só JSON: {\"opcoes\":[{\"titulo\":\"nome curto da opção\",\"legenda\":\"texto\"}],\"hashtags\":[\"#...\"]} com até 10 hashtags relevantes (sem hífen).", array( 'system' => LK_AI_SYSTEM ) );
		return is_wp_error( $out ) ? lk_ai_err( $out ) : array( 'options' => array_values( (array) ( $out['opcoes'] ?? array() ) ), 'tags' => array_values( (array) ( $out['hashtags'] ?? array() ) ) );
	}
	if ( 'melhorar' === $mode ) {
		if ( '' === trim( $caption ) ) {
			return new WP_Error( 'lk', 'Escreva (ou cole) a legenda para eu melhorar.', array( 'status' => 400 ) );
		}
		$out = lk_ai_json( $ctx . "\n\nLegenda atual:\n" . $caption . "\n\nMelhore esta legenda: corrija português, deixe mais clara e envolvente, mantenha o sentido, a voz da marca e as informações. Não adicione hashtags. Responda só JSON: {\"texto\":\"legenda melhorada\",\"mudancas\":\"1 frase dizendo o que mudou\"}", array( 'system' => LK_AI_SYSTEM, 'temperature' => 0.6 ) );
		return is_wp_error( $out ) ? lk_ai_err( $out ) : array( 'text' => (string) ( $out['texto'] ?? '' ), 'note' => (string) ( $out['mudancas'] ?? '' ) );
	}
	if ( 'hashtags' === $mode ) {
		$out = lk_ai_json( $ctx . ( $caption ? "\nLegenda: " . $caption : '' ) . "\n\nSugira 12 hashtags para este post: mistura de alcance médio, nicho e local, sem hífen nem espaços, em português quando fizer sentido. Responda só JSON: {\"hashtags\":[\"#...\"]}", array( 'system' => LK_AI_SYSTEM, 'temperature' => 0.5 ) );
		return is_wp_error( $out ) ? lk_ai_err( $out ) : array( 'tags' => array_values( (array) ( $out['hashtags'] ?? array() ) ) );
	}
	if ( 'briefing_arte' === $mode ) {
		$out = lk_ai_call( $ctx . ( $caption ? "\nLegenda: " . $caption : '' ) . "\n\nEscreva o BRIEFING PARA O DESIGNER desta arte: texto principal da arte (título curto e subtítulo), elementos visuais, composição/layout para o formato, cores e fontes conforme a identidade do cliente (se constar no briefing), tom e referências. Seja objetivo, em tópicos curtos.", array( 'system' => LK_AI_SYSTEM, 'temperature' => 0.6 ) );
		return is_wp_error( $out ) ? lk_ai_err( $out ) : array( 'text' => $out );
	}
	return new WP_Error( 'lk', 'Ação desconhecida.', array( 'status' => 400 ) );
}

function lk_api_ai_plan( WP_REST_Request $r ) {
	$client = lk_get( 'clients', (int) $r['client'] );
	$ym     = preg_match( '/^\d{4}-\d{2}$/', (string) $r['ym'] ) ? (string) $r['ym'] : '';
	if ( ! $client || ! $ym ) {
		return new WP_Error( 'lk', 'Escolha o cliente e o mês.', array( 'status' => 400 ) );
	}
	$qty    = max( 1, min( 20, (int) $r['qty'] ?: 8 ) );
	$focus  = sanitize_textarea_field( (string) $r['focus'] );
	$dates  = array_map( function ( $d ) { return ( $d['day'] ? $d['day'] . '/' . substr( $d['date'], 5, 2 ) . ' ' : '' ) . $d['name'] . ( 'feriado' === $d['type'] ? ' (FERIADO)' : '' ); }, lk_month_dates( $ym ) );
	$prev   = gmdate( 'Y-m', strtotime( $ym . '-01 -1 month' ) );
	$recent = array_map( function ( $p ) { return $p->title; }, array_slice( lk_plan_month_posts( $client->id, $prev ), 0, 12 ) );
	$have   = array_map( function ( $p ) { return $p->title; }, lk_plan_month_posts( $client->id, $ym ) );
	$last   = (int) gmdate( 't', strtotime( $ym . '-01' ) );
	$prompt = lk_ai_client_context( $client ) . "\nMês do planejamento: " . lk_month_label( $ym ) . " ($last dias)\nDatas do mês: " . ( $dates ? implode( '; ', $dates ) : 'sem datas especiais' ) . "\nConteúdos do mês passado (não repetir as mesmas ideias): " . ( $recent ? implode( '; ', $recent ) : 'nenhum' ) . "\nJá planejados neste mês: " . ( $have ? implode( '; ', $have ) : 'nenhum' ) . ( $focus ? "\nObjetivo/foco pedido pela agência: " . $focus : '' )
		. "\n\nCrie $qty ideias de conteúdo para o Instagram deste cliente neste mês. Varie os formatos (" . implode( ', ', array_keys( lk_formats() ) ) . ') e os objetivos (autoridade, relacionamento, venda, bastidores, dicas, prova social). Aproveite as datas do mês; em FERIADO prefira conteúdo leve ou de data comemorativa e lembre que o comércio pode ter horário especial. Distribua bem os dias (evite acumular). Responda só JSON: [{"dia":1,"formato":"arte|carrossel|reels|foto|story","titulo":"título interno curto","ideia":"2 frases explicando o conteúdo, objetivo e ideia visual","legenda":"rascunho da legenda sem hashtags"}]';
	$out = lk_ai_json( $prompt, array( 'system' => LK_AI_SYSTEM, 'temperature' => 0.9 ) );
	if ( is_wp_error( $out ) ) {
		return lk_ai_err( $out );
	}
	$items = array();
	foreach ( $out as $it ) {
		if ( ! is_array( $it ) || empty( $it['titulo'] ) ) {
			continue;
		}
		$items[] = array(
			'dia'     => max( 1, min( $last, (int) ( $it['dia'] ?? 1 ) ) ),
			'formato' => isset( lk_formats()[ $it['formato'] ?? '' ] ) ? $it['formato'] : 'arte',
			'titulo'  => sanitize_text_field( $it['titulo'] ),
			'ideia'   => sanitize_textarea_field( $it['ideia'] ?? '' ),
			'legenda' => sanitize_textarea_field( $it['legenda'] ?? '' ),
		);
	}
	usort( $items, function ( $a, $b ) { return $a['dia'] <=> $b['dia']; } );
	return array( 'items' => array_slice( $items, 0, $qty ) );
}

function lk_api_ai_plan_add( WP_REST_Request $r ) {
	$client = lk_get( 'clients', (int) $r['client'] );
	$ym     = preg_match( '/^\d{4}-\d{2}$/', (string) $r['ym'] ) ? (string) $r['ym'] : '';
	if ( ! $client || ! $ym ) {
		return new WP_Error( 'lk', 'Escolha o cliente e o mês.', array( 'status' => 400 ) );
	}
	$nets = array_values( array_intersect( array_keys( lk_networks() ), array_keys( lk_social_accounts( $client->id ) ) ) );
	$nets = $nets ? $nets : array( 'instagram' );
	$last = (int) gmdate( 't', strtotime( $ym . '-01' ) );
	$n    = 0;
	lk_plan_ensure( $client, $ym );
	foreach ( (array) $r['items'] as $it ) {
		$title = sanitize_text_field( $it['titulo'] ?? '' );
		if ( '' === $title ) {
			continue;
		}
		$day = max( 1, min( $last, (int) ( $it['dia'] ?? 1 ) ) );
		$id  = lk_insert(
			'posts',
			array(
				'client_id'      => $client->id,
				'title'          => $title,
				'caption'        => sanitize_textarea_field( $it['legenda'] ?? '' ),
				'idea'           => sanitize_textarea_field( $it['ideia'] ?? '' ),
				'format'         => isset( lk_formats()[ $it['formato'] ?? '' ] ) ? $it['formato'] : 'arte',
				'networks'       => implode( ',', $nets ),
				'scheduled_at'   => sprintf( '%s-%02d 10:00:00', $ym, $day ),
				'designer_id'    => (int) $client->designer_id,
				'social_id'      => (int) $client->social_id,
				'atendimento_id' => (int) $client->atendimento_id,
				'revisor_id'     => (int) $client->revisor_id,
				'stage'          => lk_stage_for( 'planejamento' ),
				'approval_token' => strtolower( wp_generate_password( 24, false ) ),
				'created_by'     => get_current_user_id(),
			)
		);
		lk_post_log( $id, 'Criado a partir das ideias da IA.' );
		do_action( 'lk_post_saved', $id );
		$n++;
	}
	return array( 'added' => $n, 'url' => lk_panel_url( 'planejamento', 0, array( 'cliente' => $client->id, 'mes' => $ym ) ) );
}

function lk_api_ai_pitch( WP_REST_Request $r ) {
	$name    = sanitize_text_field( (string) $r['name'] );
	$niche   = sanitize_text_field( (string) $r['niche'] );
	$site    = esc_url_raw( (string) $r['website'] );
	$city    = sanitize_text_field( (string) $r['city'] );
	$channel = 'email' === $r['channel'] ? 'e-mail' : 'WhatsApp';
	if ( '' === $name ) {
		return new WP_Error( 'lk', 'Marque uma empresa.', array( 'status' => 400 ) );
	}
	$prompt = 'Agência: ' . lk_setting( 'empresa' ) . ( lk_setting( 'site' ) ? ' (' . lk_setting( 'site' ) . ')' : '' ) . ".\nEmpresa a abordar: $name" . ( $niche ? ", segmento: $niche" : '' ) . ( $city ? ", em $city" : '' ) . ( $site ? ", site $site" : '' ) . ".\n\nEscreva uma mensagem de PRIMEIRO CONTATO por $channel, curta (até " . ( 'WhatsApp' === $channel ? '5 linhas' : '120 palavras' ) . '), educada e personalizada para o segmento, oferecendo um diagnóstico gratuito da presença digital/Instagram. Uma única chamada para ação. Não faça promessas de resultado, não invente dados sobre a empresa e termine dizendo que, se preferirem não receber mensagens, é só avisar.' . ( 'e-mail' === $channel ? ' Inclua uma linha de assunto no início ("Assunto: ...").' : '' ) . ' Responda só JSON: {"mensagem":"texto"}';
	$out    = lk_ai_json( $prompt, array( 'system' => LK_AI_SYSTEM, 'temperature' => 0.8 ) );
	return is_wp_error( $out ) ? lk_ai_err( $out ) : array( 'text' => (string) ( $out['mensagem'] ?? '' ) );
}

/** Botão "Testar IA" em Configurações. */
function lk_do_ai_test() {
	lk_require( 'admin' );
	$t = lk_ai_call( 'Responda apenas com a palavra OK.', array( 'temperature' => 0, 'max' => 256 ) );
	if ( is_wp_error( $t ) ) {
		lk_back( 'A IA não respondeu: ' . $t->get_error_message(), 'erro' );
	}
	lk_back( 'IA funcionando ✓ (' . ( 'gemini' === lk_ai_provider() ? 'Gemini · ' . ( lk_setting( 'gemini_model' ) ?: 'gemini-2.5-flash' ) : 'Anthropic' ) . '). Resposta: ' . mb_substr( $t, 0, 40 ) );
}

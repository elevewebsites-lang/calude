<?php
/**
 * Marketing: ideias de post (Reels, carrossel, stories) com modelos prontos para impressão 3D,
 * geradas a partir dos pedidos e produtos, e opcionalmente pela IA do Claude.
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function ap_idea_formats() {
	return array(
		'reels'     => 'Reels / vídeo',
		'carrossel' => 'Carrossel',
		'post'      => 'Post',
		'stories'   => 'Stories',
	);
}

function ap_idea_statuses() {
	return array(
		'ideia'     => 'Ideia',
		'produzir'  => 'Produzir',
		'agendado'  => 'Agendado',
		'publicado' => 'Publicado',
	);
}

/**
 * Modelos de ideia (sem IA): {peca} vira o nome do pedido/produto.
 */
function ap_idea_templates() {
	return array(
		array( 'reels', 'Timelapse: {peca} nascendo camada por camada', 'Grave o timelapse da A1 imprimindo {peca}. Texto na tela: "Do arquivo à peça em X horas". Termine com a peça na mão. Legenda: conte para quem foi e chame para orçar.' ),
		array( 'reels', 'Antes e depois do acabamento: {peca}', 'Mostre a peça saindo da mesa (com suportes) e depois lixada/pintada. Áudio em alta. CTA: "Quer a sua? Link na bio".' ),
		array( 'carrossel', 'Como é feito: {peca}', '1) Ideia/briefing do cliente · 2) Modelo 3D · 3) Fatiamento (mostre o tempo e gramas) · 4) Impressão · 5) Acabamento · 6) Entrega. Último slide: "Peça o seu orçamento".' ),
		array( 'post', 'Entrega: {peca} com o cliente', 'Foto do cliente (ou da embalagem) com a peça. Depoimento curto. Marque o cliente se ele permitir.' ),
		array( 'stories', 'Enquete: qual cor para {peca}?', 'Duas opções de cor do seu estoque. Depois mostre a escolhida sendo impressa.' ),
		array( 'reels', 'Quanto custa imprimir {peca}?', 'Explique rapidamente o que entra no preço (material, tempo de máquina, acabamento). Mostra profissionalismo e educa o cliente.' ),
		array( 'carrossel', '5 presentes impressos em 3D para datas especiais', 'Liste 5 peças do seu portfólio com foto. Último slide com prazo de produção para a próxima data comemorativa.' ),
		array( 'post', 'Brindes corporativos em 3D', 'Mostre um lote (ex.: 50 chaveiros com logo). Destaque personalização e desconto por quantidade. CTA para empresas.' ),
	);
}

function ap_do_idea_save() {
	ap_require( 'leads' );
	$id   = ap_in( 'id', 'int' );
	$data = array(
		'title'      => ap_in( 'title' ),
		'body'       => ap_in( 'body', 'textarea' ),
		'format'     => isset( ap_idea_formats()[ ap_in( 'format' ) ] ) ? ap_in( 'format' ) : 'post',
		'status'     => isset( ap_idea_statuses()[ ap_in( 'status' ) ] ) ? ap_in( 'status' ) : 'ideia',
		'post_date'  => ap_in( 'post_date', 'date' ),
		'product_id' => ap_in( 'product_id', 'int' ),
		'project_id' => ap_in( 'project_id', 'int' ),
	);
	if ( ! $data['title'] ) {
		ap_back( 'Dê um título para a ideia.', 'erro' );
	}
	if ( $id ) {
		ap_update( 'ideas', $id, $data );
	} else {
		ap_insert( 'ideas', $data );
	}
	ap_back( 'Ideia salva.' );
}

function ap_do_idea_delete() {
	ap_require( 'leads' );
	ap_delete( 'ideas', ap_in( 'id', 'int' ) );
	ap_back( 'Ideia excluída.' );
}

/**
 * Gera ideias a partir dos últimos pedidos/produtos com fotos (modelos) ou pela IA.
 */
function ap_do_idea_generate() {
	ap_require( 'leads' );
	$pieces = array();
	foreach ( ap_projects( 'p.archived = 0', array() ) as $p ) {
		$pieces[] = array( $p->title, 0, $p->id );
		if ( count( $pieces ) >= 6 ) {
			break;
		}
	}
	foreach ( ap_rows( 'products', 'active = 1', array(), 'id DESC LIMIT 4' ) as $pr ) {
		$pieces[] = array( $pr->name, $pr->id, 0 );
	}
	if ( ! $pieces ) {
		$pieces[] = array( 'uma peça personalizada', 0, 0 );
	}
	$made = 0;
	if ( ap_in( 'ia', 'bool' ) && ap_decrypt( ap_setting( 'anthropic_key' ) ) ) {
		$made = ap_idea_ai( $pieces );
	}
	if ( ! $made ) {
		$tpl = ap_idea_templates();
		shuffle( $tpl );
		foreach ( array_slice( $tpl, 0, 5 ) as $i => $t ) {
			$pc = $pieces[ $i % count( $pieces ) ];
			ap_insert(
				'ideas',
				array(
					'format'     => $t[0],
					'title'      => str_replace( '{peca}', $pc[0], $t[1] ),
					'body'       => str_replace( '{peca}', $pc[0], $t[2] ),
					'product_id' => $pc[1],
					'project_id' => $pc[2],
				)
			);
			$made++;
		}
	}
	ap_back( $made . ' ideias novas.' );
}

function ap_idea_ai( $pieces ) {
	$list = implode( ', ', array_unique( array_map( function ( $p ) { return $p[0]; }, $pieces ) ) );
	$ask  = 'Você é social media de um estúdio de impressão 3D em Taubaté/SP (' . ap_setting( 'empresa' ) . '). Peças recentes: ' . $list . '. Sugira 5 ideias de conteúdo para Instagram, variando entre reels, carrossel, post e stories, com gancho forte e CTA para orçamento. Responda SOMENTE JSON: [{"format":"reels|carrossel|post|stories","title":"...","body":"roteiro curto"}].';
	$res  = wp_remote_post(
		'https://api.anthropic.com/v1/messages',
		array(
			'timeout' => 60,
			'headers' => array( 'x-api-key' => ap_decrypt( ap_setting( 'anthropic_key' ) ), 'anthropic-version' => '2023-06-01', 'content-type' => 'application/json' ),
			'body'    => wp_json_encode( array( 'model' => ap_setting( 'ai_model' ), 'max_tokens' => 1500, 'messages' => array( array( 'role' => 'user', 'content' => $ask ) ) ) ),
		)
	);
	if ( is_wp_error( $res ) ) {
		return 0;
	}
	$json = json_decode( wp_remote_retrieve_body( $res ), true );
	$text = $json['content'][0]['text'] ?? '';
	if ( ! preg_match( '/\[.*\]/s', $text, $m ) ) {
		return 0;
	}
	$made = 0;
	foreach ( (array) json_decode( $m[0], true ) as $it ) {
		if ( empty( $it['title'] ) ) {
			continue;
		}
		ap_insert(
			'ideas',
			array(
				'format' => isset( ap_idea_formats()[ $it['format'] ?? '' ] ) ? $it['format'] : 'post',
				'title'  => sanitize_text_field( $it['title'] ),
				'body'   => sanitize_textarea_field( $it['body'] ?? '' ),
			)
		);
		$made++;
	}
	return $made;
}

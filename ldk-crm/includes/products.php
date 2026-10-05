<?php
/**
 * Produtos: portfólio do que já foi feito (foto, preço cobrado, custo, gramas, tempo) e catálogo para vender.
 *
 * - Vem de um pedido pronto ("Salvar no portfólio"), da calculadora ou de um link do MakerWorld.
 * - Preço por canal: (preço + taxa fixa) ÷ (1 − comissão), arredondado (Configurações → Canais).
 * - Anúncios: um registro por produto × canal (link do anúncio, preço, status).
 *   Mercado Livre e Shopee: textos e preço prontos para colar + link do anúncio;
 *   a publicação automática pela API entra quando as credenciais de desenvolvedor estiverem cadastradas.
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Canais: slug => [nome, comissão %, taxa fixa R$, link para anunciar].
 */
function lk_channels() {
	$links = array(
		'mercado-livre' => 'https://www.mercadolivre.com.br/anunciar',
		'shopee'        => 'https://seller.shopee.com.br/portal/product/new',
		'elo7'          => 'https://www.elo7.com.br/vender',
		'amazon'        => 'https://sellercentral.amazon.com.br/',
	);
	$out = array();
	foreach ( lk_list( lk_setting( 'taxas_canais' ) ) as $line ) {
		$p = array_map( 'trim', explode( '|', $line ) );
		if ( '' === $p[0] ) {
			continue;
		}
		$slug         = sanitize_title( $p[0] );
		$out[ $slug ] = array(
			'name'  => $p[0],
			'pct'   => isset( $p[1] ) ? (float) str_replace( ',', '.', $p[1] ) : 0,
			'fixed' => isset( $p[2] ) ? lk_parse_money( $p[2] ) : 0,
			'link'  => $links[ $slug ] ?? '',
		);
	}
	return $out;
}

function lk_channel_price( $price, $channel ) {
	$c = lk_channels()[ $channel ] ?? null;
	if ( ! $c || $price <= 0 ) {
		return round( (float) $price, 2 );
	}
	$pct = min( 90, max( 0, $c['pct'] ) ) / 100;
	return lk_round_price( ( $price + $c['fixed'] ) / ( 1 - $pct ) );
}

/**
 * Quanto sobra no bolso vendendo no canal (preço − comissão − taxa − custo).
 */
function lk_channel_profit( $channel_price, $cost, $channel ) {
	$c = lk_channels()[ $channel ] ?? array( 'pct' => 0, 'fixed' => 0 );
	return round( $channel_price * ( 1 - $c['pct'] / 100 ) - $c['fixed'] - $cost, 2 );
}

function lk_product_photos( $p ) {
	return lk_json( $p->photos );
}

function lk_product_cover( $p ) {
	$ph = lk_product_photos( $p );
	if ( ! $ph ) {
		return '';
	}
	return ! empty( $ph[0]['thumb'] ) ? $ph[0]['thumb'] : $ph[0]['url'];
}

function lk_licenses() {
	return array(
		''            => 'Não informado',
		'propria'     => 'Criação própria',
		'comercial'   => 'Licença comercial (pode vender)',
		'pessoal'     => 'Só uso pessoal (não vender)',
		'cliente'     => 'Arquivo do cliente',
	);
}

/* -----------------------------------------------------------------------
 * Salvar
 * -------------------------------------------------------------------- */

function lk_do_product_save() {
	lk_require( 'produtos' );
	$id  = lk_in( 'id', 'int' );
	$old = $id ? lk_get( 'products', $id ) : null;
	$photos = $old ? lk_product_photos( $old ) : array();
	$remove = isset( $_POST['remove_img'] ) ? array_map( 'absint', (array) $_POST['remove_img'] ) : array(); // phpcs:ignore WordPress.Security.NonceVerification
	$photos = array_values(
		array_filter(
			$photos,
			function ( $img ) use ( $remove ) {
				return ! in_array( (int) ( $img['id'] ?? 0 ), $remove, true );
			}
		)
	);
	$name   = lk_in( 'name' );
	if ( ! $name ) {
		lk_back( 'Dê um nome para o produto.', 'erro' );
	}
	$photos = array_merge( $photos, lk_store_uploads( 'photos', array( 'Produtos', $name ), 'image' ) );
	$ext    = lk_in( 'photo_url', 'url' );
	if ( $ext ) {
		$photos[] = array( 'id' => 0, 'url' => $ext, 'thumb' => $ext, 'name' => 'imagem', 'type' => 'image' );
	}
	$data = array(
		'name'        => $name,
		'sku'         => lk_in( 'sku' ),
		'category'    => lk_in( 'category' ),
		'description' => lk_in( 'description', 'textarea' ),
		'photos'      => wp_json_encode( $photos ),
		'cost'        => lk_in( 'cost', 'money' ),
		'price'       => lk_in( 'price', 'money' ),
		'sold_price'  => lk_in( 'sold_price', 'money' ),
		'grams'       => (float) str_replace( ',', '.', lk_in( 'grams' ) ),
		'print_hours' => (float) str_replace( ',', '.', lk_in( 'print_hours' ) ),
		'weight_g'    => lk_in( 'weight_g', 'int' ),
		'dims'        => lk_in( 'dims' ),
		'stock'       => lk_in( 'stock', 'int' ),
		'source_url'  => lk_in( 'source_url', 'url' ),
		'license'     => isset( lk_licenses()[ lk_in( 'license' ) ] ) ? lk_in( 'license' ) : '',
		'portfolio'   => lk_in( 'portfolio', 'bool' ),
		'active'      => lk_in( 'active', 'bool' ),
	);
	$calc_id = lk_in( 'calc_id', 'int' );
	if ( $calc_id && ( $calc = lk_get( 'calcs', $calc_id ) ) ) { // phpcs:ignore
		$data['calc'] = $calc->data;
	}
	if ( $old ) {
		lk_update( 'products', $old->id, $data );
		$id = $old->id;
	} else {
		$id = lk_insert( 'products', $data );
	}
	// Preços por canal (anúncios).
	$ch = isset( $_POST['ch'] ) ? (array) wp_unslash( $_POST['ch'] ) : array(); // phpcs:ignore WordPress.Security.NonceVerification,WordPress.Security.ValidatedSanitizedInput
	foreach ( lk_channels() as $slug => $c ) {
		if ( empty( $ch[ $slug ] ) ) {
			continue;
		}
		$row  = $ch[ $slug ];
		$data = array(
			'price'  => lk_parse_money( $row['price'] ?? 0 ),
			'url'    => esc_url_raw( $row['url'] ?? '' ),
			'status' => in_array( $row['status'] ?? '', array( 'rascunho', 'ativo', 'pausado' ), true ) ? $row['status'] : 'rascunho',
		);
		$have = lk_rows( 'listings', 'product_id = %d AND channel = %s', array( $id, $slug ) );
		if ( $have ) {
			lk_update( 'listings', $have[0]->id, $data );
		} elseif ( $data['url'] || 'ativo' === $data['status'] ) {
			lk_insert( 'listings', $data + array( 'product_id' => $id, 'channel' => $slug ) );
		}
	}
	lk_back( 'Produto salvo.', 'ok', lk_panel_url( 'produto', $id ) );
}

function lk_do_product_delete() {
	lk_require( 'produtos' );
	global $wpdb;
	$id = lk_in( 'id', 'int' );
	$wpdb->delete( lk_table( 'listings' ), array( 'product_id' => $id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
	lk_delete( 'products', $id );
	lk_back( 'Produto excluído.', 'ok', lk_panel_url( 'produtos' ) );
}

/**
 * Importar do MakerWorld (ou outro site de modelos): título, imagem e autor pelo link.
 */
function lk_do_product_import() {
	lk_require( 'produtos' );
	$url = lk_in( 'url', 'url' );
	if ( ! $url ) {
		lk_back( 'Cole o link do modelo.', 'erro' );
	}
	$meta = lk_fetch_meta( $url );
	$host = (string) wp_parse_url( $url, PHP_URL_HOST );
	$name = $meta['title'] ? $meta['title'] : lk_link_title( $url );
	$name = trim( preg_replace( '/\s*[|\-–]\s*(MakerWorld|Printables|Thingiverse|Cults).*$/i', '', $name ) );
	$photos = $meta['image'] ? array( array( 'id' => 0, 'url' => $meta['image'], 'thumb' => $meta['image'], 'name' => 'capa', 'type' => 'image' ) ) : array();
	$id   = lk_insert(
		'products',
		array(
			'name'        => $name ? $name : 'Modelo importado',
			'description' => trim( ( $meta['description'] ? $meta['description'] . "\n\n" : '' ) . 'Modelo: ' . $url ),
			'photos'      => wp_json_encode( $photos ),
			'source_url'  => $url,
			'category'    => false !== stripos( $host, 'makerworld' ) ? 'MakerWorld' : '',
			'portfolio'   => 0,
			'active'      => 1,
		)
	);
	lk_back( $meta['title'] ? 'Modelo importado. Confira a licença (muitos modelos são só para uso pessoal) e faça o cálculo do preço.' : 'Não consegui ler a página do modelo (o site pode bloquear). Preencha nome e foto à mão.', $meta['title'] ? 'ok' : 'erro', lk_panel_url( 'produto', $id ) );
}

function lk_fetch_meta( $url ) {
	$out = array( 'title' => '', 'image' => '', 'description' => '' );
	$res = wp_remote_get(
		$url,
		array(
			'timeout'    => 15,
			'user-agent' => 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/126 Safari/537.36',
			'headers'    => array( 'Accept-Language' => 'pt-BR,pt;q=0.9,en;q=0.8' ),
		)
	);
	if ( is_wp_error( $res ) || wp_remote_retrieve_response_code( $res ) >= 400 ) {
		return $out;
	}
	$html = substr( wp_remote_retrieve_body( $res ), 0, 600000 );
	$get  = function ( $prop ) use ( $html ) {
		if ( preg_match( '/<meta[^>]+(?:property|name)=["\']' . preg_quote( $prop, '/' ) . '["\'][^>]*content=["\']([^"\']+)["\']/i', $html, $m ) ) {
			return html_entity_decode( $m[1], ENT_QUOTES, 'UTF-8' );
		}
		if ( preg_match( '/<meta[^>]+content=["\']([^"\']+)["\'][^>]*(?:property|name)=["\']' . preg_quote( $prop, '/' ) . '["\']/i', $html, $m ) ) {
			return html_entity_decode( $m[1], ENT_QUOTES, 'UTF-8' );
		}
		return '';
	};
	$out['title']       = sanitize_text_field( $get( 'og:title' ) );
	$out['image']       = esc_url_raw( $get( 'og:image' ) );
	$out['description'] = sanitize_textarea_field( mb_substr( $get( 'og:description' ), 0, 500 ) );
	if ( ! $out['title'] && preg_match( '/<title>([^<]+)<\/title>/i', $html, $m ) ) {
		$out['title'] = sanitize_text_field( html_entity_decode( $m[1], ENT_QUOTES, 'UTF-8' ) );
	}
	return $out;
}

/**
 * Texto pronto para o anúncio (título até 60 caracteres + descrição).
 */
function lk_listing_text( $p ) {
	$title = mb_substr( $p->name . ' - Impressão 3D', 0, 60 );
	$lines = array(
		$p->name,
		'',
		$p->description ? $p->description : 'Peça produzida em impressão 3D com acabamento caprichado.',
		'',
		'• Produzido sob encomenda pelo ' . lk_setting( 'empresa' ),
		$p->dims ? '• Medidas: ' . $p->dims : '',
		$p->grams ? '• Material: PLA de alta qualidade' : '',
		'• Cores e tamanhos personalizados: chame no chat',
	);
	return array( 'title' => $title, 'description' => trim( implode( "\n", array_filter( $lines, 'strlen' ) ) ) );
}

/**
 * Venda vinda de marketplace: cria o pedido direto em produção.
 */
function lk_do_product_sale() {
	lk_require( 'projetos' );
	$p = lk_get( 'products', lk_in( 'id', 'int' ) );
	if ( ! $p ) {
		lk_back();
	}
	$qty   = max( 1, lk_in( 'qty', 'int' ) );
	$value = lk_in( 'value', 'money' );
	$cols  = array_keys( lk_columns() );
	$chan  = lk_in( 'channel' );
	$pid   = lk_insert(
		'projects',
		array(
			'client_id'      => 0,
			'product_id'     => $p->id,
			'title'          => $qty . '× ' . $p->name . ( $chan ? ' · ' . ( lk_channels()[ $chan ]['name'] ?? $chan ) : '' ),
			'status'         => $cols[1] ?? $cols[0],
			'value'          => $value,
			'cost_estimated' => round( $p->cost * $qty, 2 ),
			'items'          => wp_json_encode( array( array( 'kind' => 'impressao', 'name' => $p->name, 'qty' => $qty, 'unit' => $qty ? round( $value / $qty, 2 ) : 0, 'cost' => $p->cost, 'grams' => $p->grams, 'hours' => $p->print_hours ) ) ),
			'delivery_mode'  => 'envio',
			'start_date'     => lk_today(),
			'due_date'       => lk_in( 'due_date', 'date' ),
			'notes'          => lk_in( 'buyer' ) ? 'Comprador: ' . lk_in( 'buyer' ) : '',
			'kind'           => $chan ? $chan : 'pedido',
		)
	);
	if ( $value > 0 ) {
		lk_insert(
			'transactions',
			array(
				'type'        => 'in',
				'project_id'  => $pid,
				'category'    => 'Venda marketplace',
				'description' => 'Venda ' . ( lk_channels()[ $chan ]['name'] ?? '' ) . ': ' . $p->name,
				'amount'      => $value,
				'due_date'    => lk_in( 'payout_date', 'date' ) ? lk_in( 'payout_date', 'date' ) : lk_today(),
				'method'      => 'Marketplace',
				'status'      => 'pendente',
			)
		);
	}
	lk_update( 'products', $p->id, array( 'sold_count' => $p->sold_count + $qty ) );
	lk_back( 'Venda registrada: o pedido já está na fila.', 'ok', lk_panel_url( 'pedido', $pid ) );
}

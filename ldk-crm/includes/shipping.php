<?php
/**
 * Calculadora de frete pelo Melhor Envio (Correios PAC/SEDEX, Jadlog, Loggi, J&T, Azul Cargo…).
 *
 * Configurações → Frete: token do Melhor Envio (melhorenvio.com.br → Configurações → Tokens),
 * CEP de origem (Taubaté) e as caixas que você usa (Nome | comprimento | largura | altura em cm).
 *
 * GET /wp-json/lk/v1/frete?cep=12000000&caixa=0&peso=350&valor=80  (peso em gramas)
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function lk_boxes() {
	$out = array();
	foreach ( lk_list( lk_setting( 'caixas' ) ) as $line ) {
		$p = array_map( 'trim', explode( '|', $line ) );
		if ( count( $p ) >= 4 ) {
			$out[] = array( 'name' => $p[0], 'l' => (float) str_replace( ',', '.', $p[1] ), 'w' => (float) str_replace( ',', '.', $p[2] ), 'h' => (float) str_replace( ',', '.', $p[3] ) );
		}
	}
	return $out ? $out : array( array( 'name' => 'Padrão', 'l' => 20, 'w' => 15, 'h' => 10 ) );
}

add_action(
	'rest_api_init',
	function () {
		register_rest_route(
			'lk/v1',
			'/frete',
			array(
				'methods'             => 'GET',
				'callback'            => 'lk_api_freight',
				'permission_callback' => function () {
					return lk_is_team();
				},
			)
		);
	}
);

function lk_api_freight( WP_REST_Request $r ) {
	$boxes = lk_boxes();
	$box   = $boxes[ min( count( $boxes ) - 1, max( 0, (int) $r['caixa'] ) ) ];
	if ( $r['l'] && $r['w'] && $r['h'] ) {
		$box = array( 'name' => 'Personalizada', 'l' => (float) $r['l'], 'w' => (float) $r['w'], 'h' => (float) $r['h'] );
	}
	$res = lk_freight_quote( (string) $r['cep'], (float) $r['peso'], $box, (float) $r['valor'] );
	if ( is_wp_error( $res ) ) {
		return new WP_Error( 'lk', $res->get_error_message(), array( 'status' => 400 ) );
	}
	return array( 'box' => $box, 'options' => $res );
}

/**
 * Cotação: devolve [ [service, company, price, days], ... ] do mais barato ao mais caro.
 */
function lk_freight_quote( $cep_to, $grams, $box, $value = 0 ) {
	$token = trim( (string) lk_decrypt( lk_setting( 'melhorenvio_token' ) ) );
	$from  = preg_replace( '/\D/', '', (string) lk_setting( 'cep_origem' ) );
	$to    = preg_replace( '/\D/', '', $cep_to );
	if ( ! $token ) {
		return new WP_Error( 'lk', 'Coloque o token do Melhor Envio em Configurações → Frete.' );
	}
	if ( strlen( $from ) !== 8 ) {
		return new WP_Error( 'lk', 'Preencha o CEP de origem em Configurações → Frete.' );
	}
	if ( strlen( $to ) !== 8 ) {
		return new WP_Error( 'lk', 'CEP de destino inválido.' );
	}
	$base = '1' === (string) lk_setting( 'melhorenvio_sandbox' ) ? 'https://sandbox.melhorenvio.com.br' : 'https://www.melhorenvio.com.br';
	$res  = wp_remote_post(
		$base . '/api/v2/me/shipment/calculate',
		array(
			'timeout' => 25,
			'headers' => array(
				'Authorization' => 'Bearer ' . $token,
				'Accept'        => 'application/json',
				'Content-Type'  => 'application/json',
				'User-Agent'    => 'LDK CRM (' . ( lk_setting( 'email' ) ? lk_setting( 'email' ) : get_option( 'admin_email' ) ) . ')',
			),
			'body'    => wp_json_encode(
				array(
					'from'    => array( 'postal_code' => $from ),
					'to'      => array( 'postal_code' => $to ),
					'package' => array(
						'height' => max( 1, $box['h'] ),
						'width'  => max( 1, $box['w'] ),
						'length' => max( 1, $box['l'] ),
						'weight' => max( 0.05, $grams / 1000 ),
					),
					'options' => array(
						'insurance_value' => max( 0, $value ),
						'receipt'         => false,
						'own_hand'        => false,
					),
				)
			),
		)
	);
	if ( is_wp_error( $res ) ) {
		return $res;
	}
	$code = wp_remote_retrieve_response_code( $res );
	$json = json_decode( wp_remote_retrieve_body( $res ), true );
	if ( $code >= 300 || ! is_array( $json ) ) {
		return new WP_Error( 'lk', 'Melhor Envio: ' . ( $json['message'] ?? ( 401 === $code ? 'token inválido ou vencido' : 'erro ' . $code ) ) );
	}
	$out = array();
	foreach ( $json as $s ) {
		if ( ! empty( $s['error'] ) || empty( $s['price'] ) ) {
			continue;
		}
		$out[] = array(
			'service' => $s['name'],
			'company' => $s['company']['name'] ?? '',
			'price'   => round( (float) ( $s['custom_price'] ?? $s['price'] ), 2 ),
			'days'    => (int) ( $s['custom_delivery_time'] ?? $s['delivery_time'] ?? 0 ),
			'label'   => trim( ( $s['company']['name'] ?? '' ) . ' ' . $s['name'] ),
		);
	}
	usort(
		$out,
		function ( $a, $b ) {
			return $a['price'] <=> $b['price'];
		}
	);
	return $out;
}

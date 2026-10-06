<?php
/**
 * Motor de preço da impressão 3D.
 *
 * Custo por peça = (filamento + energia + desgaste) ÷ (1 − falha) + mão de obra + insumos + extras
 * Preço por peça = custo × multiplicador do tipo × (1 + dificuldade) × (1 + urgência) × (1 − desconto da quantidade)
 *                  nunca abaixo de custo × piso; arredondado para ,90.
 * Modelagem (horas × R$/h) entra uma vez no total, não por peça.
 *
 * A mesma conta roda no navegador (assets/app.js, bloco "Calculadora") para o resultado ao vivo.
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function ap_num_setting( $key ) {
	return (float) ap_parse_money( ap_setting( $key ) );
}

/**
 * Lista "Nome | número" → [ slug => [label, valor] ].
 */
function ap_price_table( $key ) {
	$out = array();
	foreach ( ap_list( ap_setting( $key ) ) as $line ) {
		$p = array_map( 'trim', explode( '|', $line ) );
		if ( count( $p ) < 2 ) {
			continue;
		}
		$out[ sanitize_title( $p[0] ) ] = array( $p[0], (float) str_replace( ',', '.', $p[1] ) );
	}
	return $out;
}

/**
 * Faixas de quantidade: [ [qtd, desconto%], ... ] em ordem crescente.
 */
function ap_qty_tiers( $audience = 'final' ) {
	$tiers = array();
	foreach ( ap_list( ap_setting( 'revenda' === $audience ? 'revenda_descontos' : 'descontos_qtd' ) ) as $line ) {
		$p = array_map( 'trim', explode( '|', $line ) );
		if ( count( $p ) >= 2 && (int) $p[0] > 0 ) {
			$tiers[] = array( (int) $p[0], (float) str_replace( ',', '.', $p[1] ) );
		}
	}
	usort(
		$tiers,
		function ( $a, $b ) {
			return $a[0] - $b[0];
		}
	);
	return $tiers ? $tiers : array( array( 1, 0 ) );
}

function ap_audiences() {
	return array(
		'final'    => 'Consumidor final',
		'empresa'  => 'Empresa',
		'revenda'  => 'Revendedor / loja',
	);
}

function ap_qty_discount( $qty, $audience = 'final' ) {
	$d = 0;
	foreach ( ap_qty_tiers( $audience ) as $t ) {
		if ( $qty >= $t[0] ) {
			$d = $t[1];
		}
	}
	return $d;
}

/**
 * Configuração que a calculadora do navegador precisa.
 */
function ap_pricing_config() {
	$filaments = array();
	foreach ( ap_rows( 'filaments', 'active = 1', array(), 'material, color_name' ) as $f ) {
		$filaments[ $f->id ] = array(
			'label' => ap_filament_label( $f ),
			'hex'   => $f->color_hex,
			'per_g' => ap_filament_cost_g( $f ),
			'left'  => (float) $f->weight_g,
		);
	}
	$supplies = array();
	foreach ( ap_rows( 'supplies', 'active = 1', array(), 'name' ) as $s ) {
		$supplies[ $s->id ] = array(
			'label'     => $s->name,
			'unit'      => $s->unit,
			'cost'      => (float) $s->unit_cost,
			'per_order' => (int) $s->per_order,
		);
	}
	$printers = array();
	foreach ( ap_rows( 'printers', 'active = 1' ) as $p ) {
		$printers[ $p->id ] = array(
			'label' => $p->name . ( $p->model ? ' · ' . $p->model : '' ),
			'watts' => (int) $p->watts,
			'wear'  => $p->life_hours > 0 ? (float) $p->price / (int) $p->life_hours : 0,
		);
	}
	return array(
		'filaments'  => $filaments,
		'supplies'   => $supplies,
		'printers'   => $printers,
		'kwh'        => ap_num_setting( 'kwh' ),
		'labor'      => ap_num_setting( 'mao_obra_hora' ),
		'model'      => ap_num_setting( 'modelagem_hora' ),
		'fail'       => ap_num_setting( 'falha' ),
		'waste'      => ap_num_setting( 'perda_filamento' ),
		'types'      => ap_price_table( 'tipos_preco' ),
		'difficulty' => ap_price_table( 'dificuldades' ),
		'tiers'      => ap_qty_tiers(),
		'tiersResale' => ap_qty_tiers( 'revenda' ),
		'floor'      => ap_num_setting( 'piso_markup' ),
		'floorResale' => ap_num_setting( 'revenda_piso' ),
		'resaleMult' => ap_num_setting( 'revenda_sugerido' ),
		'urgent'     => ap_num_setting( 'urgencia' ),
		'round'      => (int) ap_setting( 'arredondar' ),
		'minimum'    => ap_num_setting( 'pedido_minimo' ),
		'pixOff'     => ap_num_setting( 'desconto_pix' ),
	);
}

/**
 * Arredonda para cima terminando em ,90 (ou o final configurado; 0 = sem arredondar).
 */
function ap_round_price( $v ) {
	$end = (int) ap_setting( 'arredondar' );
	if ( $end <= 0 || $v <= 0 ) {
		return round( $v, 2 );
	}
	$r = floor( $v ) + $end / 100;
	if ( $r + 0.0001 < $v ) {
		$r += 1;
	}
	return round( $r, 2 );
}

/**
 * Faz a conta.
 *
 * $in = [
 *   'filaments' => [ [id, grams], ... ] (gramas da mesa inteira),
 *   'hours' => tempo da mesa, 'per_plate' => peças por mesa, 'printer' => id,
 *   'labor_min' => min de mão de obra por peça, 'model_hours' => horas de modelagem (uma vez),
 *   'supplies' => [ [id, qty por peça], ... ], 'extras' => [ [label, custo por peça], ... ],
 *   'type' => slug, 'difficulty' => slug, 'urgent' => bool, 'qty' => quantidade pedida
 * ]
 */
function ap_calc( $in ) {
	$cfg       = ap_pricing_config();
	$per_plate = max( 1, (int) ( $in['per_plate'] ?? 1 ) );
	$hours     = max( 0, (float) ( $in['hours'] ?? 0 ) );
	$printer   = $cfg['printers'][ $in['printer'] ?? 0 ] ?? ( $cfg['printers'] ? reset( $cfg['printers'] ) : array( 'watts' => 95, 'wear' => 0.7 ) );

	$fil   = 0;
	$grams = 0;
	foreach ( (array) ( $in['filaments'] ?? array() ) as $row ) {
		$g      = max( 0, (float) ( $row[1] ?? 0 ) );
		$per_g  = isset( $cfg['filaments'][ $row[0] ] ) ? $cfg['filaments'][ $row[0] ]['per_g'] : 0.1;
		$fil   += $g * $per_g;
		$grams += $g;
	}
	$fil    = $fil * ( 1 + $cfg['waste'] / 100 ) / $per_plate;
	$energy = $hours * $printer['watts'] / 1000 * $cfg['kwh'] / $per_plate;
	$wear   = $hours * $printer['wear'] / $per_plate;
	$fail   = min( 0.9, max( 0, $cfg['fail'] / 100 ) );
	$machine = ( $fil + $energy + $wear ) / ( 1 - $fail );
	$labor  = max( 0, (float) ( $in['labor_min'] ?? 0 ) ) / 60 * $cfg['labor'];

	$sup = 0;
	foreach ( (array) ( $in['supplies'] ?? array() ) as $row ) {
		if ( isset( $cfg['supplies'][ $row[0] ] ) ) {
			$sup += $cfg['supplies'][ $row[0] ]['cost'] * max( 0, (float) ( $row[1] ?? 0 ) );
		}
	}
	$ext = 0;
	foreach ( (array) ( $in['extras'] ?? array() ) as $row ) {
		$ext += max( 0, (float) ap_parse_money( $row[1] ?? 0 ) );
	}
	$cost = $machine + $labor + $sup + $ext;

	$type  = $cfg['types'][ $in['type'] ?? '' ] ?? ( $cfg['types'] ? reset( $cfg['types'] ) : array( '', 2.5 ) );
	$diff  = $cfg['difficulty'][ $in['difficulty'] ?? '' ] ?? array( '', 0 );
	$mult  = $type[1] * ( 1 + $diff[1] / 100 ) * ( ! empty( $in['urgent'] ) ? 1 + $cfg['urgent'] / 100 : 1 );
	$model = max( 0, (float) ( $in['model_hours'] ?? 0 ) ) * $cfg['model'];
	$aud   = isset( $in['audience'] ) && isset( ap_audiences()[ $in['audience'] ] ) ? $in['audience'] : 'final';
	$floor = 'revenda' === $aud ? $cfg['floorResale'] : $cfg['floor'];

	$price_for = function ( $q ) use ( $cost, $mult, $floor, $aud ) {
		$p = $cost * $mult * ( 1 - ap_qty_discount( $q, $aud ) / 100 );
		$p = max( $p, $cost * $floor );
		return ap_round_price( $p );
	};
	$tiers = array();
	foreach ( ap_qty_tiers( $aud ) as $t ) {
		$u       = $price_for( $t[0] );
		$tiers[] = array(
			'qty'    => $t[0],
			'unit'   => $u,
			'total'  => round( $u * $t[0] + $model, 2 ),
			'profit' => round( ( $u - $cost ) * $t[0], 2 ),
			'off'    => ap_qty_discount( $t[0], $aud ),
			'resale' => 'revenda' === $aud ? ap_round_price( $u * $cfg['resaleMult'] ) : 0,
		);
	}
	$qty  = max( 1, (int) ( $in['qty'] ?? 1 ) );
	$unit = $price_for( $qty );
	return array(
		'grams_unit'  => round( $grams / $per_plate, 1 ),
		'hours_unit'  => round( $hours / $per_plate, 2 ),
		'filament'    => round( $fil, 2 ),
		'energy'      => round( $energy, 2 ),
		'wear'        => round( $wear, 2 ),
		'fail_extra'  => round( $machine - $fil - $energy - $wear, 2 ),
		'labor'       => round( $labor, 2 ),
		'supplies'    => round( $sup, 2 ),
		'extras'      => round( $ext, 2 ),
		'cost'        => round( $cost, 2 ),
		'mult'        => round( $mult, 3 ),
		'model'       => round( $model, 2 ),
		'qty'         => $qty,
		'unit'        => $unit,
		'total'       => round( max( $unit * $qty + $model, $cfg['minimum'] ), 2 ),
		'profit'      => round( ( $unit - $cost ) * $qty + $model, 2 ),
		'tiers'       => $tiers,
		'audience'    => $aud,
		'resale'      => 'revenda' === $aud ? ap_round_price( $unit * $cfg['resaleMult'] ) : 0,
		'type_label'  => $type[0],
		'diff_label'  => $diff[0],
	);
}

/**
 * Lê os campos da calculadora vindos de um formulário (prefixo c[...]).
 */
function ap_calc_input_from_post( $prefix = 'c' ) {
	$raw = isset( $_POST[ $prefix ] ) ? (array) wp_unslash( $_POST[ $prefix ] ) : array(); // phpcs:ignore WordPress.Security.NonceVerification,WordPress.Security.ValidatedSanitizedInput
	$pairs = function ( $ids, $vals ) {
		$out = array();
		foreach ( (array) $ids as $i => $id ) {
			$v = isset( $vals[ $i ] ) ? $vals[ $i ] : 0;
			if ( '' !== (string) $id && (float) str_replace( ',', '.', (string) $v ) > 0 ) {
				$out[] = array( sanitize_text_field( $id ), (float) str_replace( ',', '.', (string) $v ) );
			}
		}
		return $out;
	};
	$extras = array();
	foreach ( (array) ( $raw['extra_label'] ?? array() ) as $i => $label ) {
		$label = sanitize_text_field( $label );
		$cost  = isset( $raw['extra_cost'][ $i ] ) ? ap_parse_money( $raw['extra_cost'][ $i ] ) : 0;
		if ( $label && $cost > 0 ) {
			$extras[] = array( $label, $cost );
		}
	}
	return array(
		'name'        => sanitize_text_field( $raw['name'] ?? '' ),
		'filaments'   => $pairs( $raw['fil_id'] ?? array(), $raw['fil_g'] ?? array() ),
		'hours'       => (float) str_replace( ',', '.', (string) ( $raw['hours'] ?? 0 ) ) + (float) ( $raw['minutes'] ?? 0 ) / 60,
		'per_plate'   => max( 1, (int) ( $raw['per_plate'] ?? 1 ) ),
		'printer'     => (int) ( $raw['printer'] ?? 0 ),
		'labor_min'   => (float) str_replace( ',', '.', (string) ( $raw['labor_min'] ?? 0 ) ),
		'model_hours' => (float) str_replace( ',', '.', (string) ( $raw['model_hours'] ?? 0 ) ),
		'supplies'    => $pairs( $raw['sup_id'] ?? array(), $raw['sup_qty'] ?? array() ),
		'extras'      => $extras,
		'type'        => sanitize_title( $raw['type'] ?? '' ),
		'difficulty'  => sanitize_title( $raw['difficulty'] ?? '' ),
		'urgent'      => ! empty( $raw['urgent'] ),
		'qty'         => max( 1, (int) ( $raw['qty'] ?? 1 ) ),
		'audience'    => sanitize_key( $raw['audience'] ?? 'final' ),
	);
}

/**
 * Salva um cálculo (para reaproveitar em orçamento/produto).
 */
function ap_do_calc_save() {
	ap_require( 'orcamentos' );
	$in  = ap_calc_input_from_post();
	$res = ap_calc( $in );
	$id  = ap_in( 'calc_id', 'int' );
	$data = array(
		'name'  => $in['name'] ? $in['name'] : 'Cálculo de ' . ap_date( ap_today() ),
		'data'  => wp_json_encode( array( 'in' => $in, 'out' => $res ) ),
		'cost'  => $res['cost'],
		'price' => $res['unit'],
	);
	if ( $id && ap_get( 'calcs', $id ) ) {
		ap_update( 'calcs', $id, $data );
	} else {
		$data['created_by'] = get_current_user_id();
		$id                 = ap_insert( 'calcs', $data );
	}
	$next = ap_in( 'next' );
	if ( 'orcamento' === $next ) {
		ap_back( 'Cálculo salvo. Agora é só montar o orçamento.', 'ok', ap_panel_url( 'orcamento', 0, array( 'calc' => $id, 'qty' => $in['qty'] ) ) );
	}
	if ( 'produto' === $next ) {
		ap_back( 'Cálculo salvo como produto.', 'ok', ap_panel_url( 'produto', 0, array( 'calc' => $id ) ) );
	}
	ap_back( 'Cálculo salvo.', 'ok', ap_panel_url( 'calculadora', $id ) );
}

function ap_do_calc_delete() {
	ap_require( 'orcamentos' );
	ap_delete( 'calcs', ap_in( 'id', 'int' ) );
	ap_back( 'Cálculo excluído.', 'ok', ap_panel_url( 'calculadora' ) );
}

function ap_calc_data( $calc ) {
	$d = $calc && $calc->data ? json_decode( $calc->data, true ) : array();
	return array(
		'in'  => isset( $d['in'] ) ? $d['in'] : array(),
		'out' => isset( $d['out'] ) ? $d['out'] : array(),
	);
}

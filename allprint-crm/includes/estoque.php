<?php
/**
 * Estoque da gráfica: bobinas em rolos (m²), tinta, peças da impressora, revenda e consumo interno.
 * - contagem de estoque (inventário): a equipe conta o que tem na prateleira e o sistema ajusta e registra a diferença;
 * - entrada (compra com custo médio), baixa manual e baixa automática pela produção (com a perda da máquina);
 * - nível e alerta de reposição por item (cai sozinho na lista de compras).
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function ap_supply_cats() {
	return array( 'Bobina' => 'Bobinas (adesivo, lona…)', 'Tinta' => 'Tinta', 'Peças da impressora' => 'Peças da impressora', 'Revenda' => 'Revenda', 'Consumo interno' => 'Consumo interno', '' => 'Sem categoria' );
}

function ap_supply_units() {
	return array( 'm2' => 'm² (bobina)', 'm' => 'metro', 'un' => 'unidade', 'folha' => 'folha', 'ml' => 'ml', 'g' => 'grama', 'rolo' => 'rolo', 'pacote' => 'pacote', 'caixa' => 'caixa' );
}

/** Perda média da máquina (liga e joga um pedaço fora): entra na baixa automática. Padrão 20%. */
function ap_stock_loss_factor() {
	$p = ap_num_setting( 'perda_impressao' );
	return 1 + max( 0, $p ) / 100;
}

/** "2 rolos + 12 m²" para bobina; "340 un" para o resto. */
function ap_supply_qty_label( $s ) {
	if ( 'm2' === $s->unit && (float) $s->roll_m2 > 0 ) {
		$rolls = floor( ( (float) $s->qty + 0.0001 ) / (float) $s->roll_m2 );
		$rest  = round( (float) $s->qty - $rolls * (float) $s->roll_m2, 1 );
		$out   = $rolls > 0 ? $rolls . ( 1 === (int) $rolls ? ' rolo' : ' rolos' ) : '';
		if ( $rest > 0 || ! $out ) {
			$out .= ( $out ? ' + ' : '' ) . rtrim( rtrim( number_format( $rest, 1, ',', '.' ), '0' ), ',' ) . ' m²';
		}
		return $out;
	}
	return ap_qty_label( $s->qty, 'm2' === $s->unit ? 'm²' : $s->unit );
}

/** Nível: ok | low (abaixo do mínimo) | out (zerado). */
function ap_supply_state( $s ) {
	if ( (float) $s->qty <= 0 ) {
		return 'out';
	}
	if ( (float) $s->min_qty > 0 && (float) $s->qty < (float) $s->min_qty ) {
		return 'low';
	}
	return 'ok';
}

/* -----------------------------------------------------------------------
 * Ações
 * -------------------------------------------------------------------- */

/** Cadastro e edição completos (substitui o formulário simples). */
function ap_do_supply_edit() {
	ap_require( 'estoque' );
	$id   = ap_in( 'id', 'int' );
	$data = array(
		'name'       => ap_in( 'name' ),
		'category'   => isset( ap_supply_cats()[ ap_in( 'category' ) ] ) ? ap_in( 'category' ) : '',
		'unit'       => isset( ap_supply_units()[ ap_in( 'unit' ) ] ) ? ap_in( 'unit' ) : 'un',
		'min_qty'    => (float) str_replace( ',', '.', ap_in( 'min_qty' ) ),
		'brand'      => ap_in( 'brand' ),
		'supplier'   => ap_in( 'supplier' ),
		'roll_m2'    => (float) str_replace( ',', '.', ap_in( 'roll_m2' ) ),
		'roll_width' => (float) str_replace( ',', '.', ap_in( 'roll_width' ) ),
		'sale_price' => ap_in( 'sale_price', 'money' ),
		'link'       => ap_in( 'link', 'url' ),
		'per_order'  => ap_in( 'per_order', 'bool' ) ? 1 : 0,
		'notes'      => ap_in( 'notes' ),
	);
	if ( '' !== (string) ap_in( 'unit_cost' ) ) {
		$data['unit_cost'] = ap_parse_money( ap_in( 'unit_cost' ) );
	}
	if ( ! $data['name'] ) {
		ap_back( 'Dê um nome ao item.', 'erro' );
	}
	if ( $id ) {
		ap_update( 'supplies', $id, $data );
		ap_back( 'Item salvo.' );
	}
	$data['qty'] = (float) str_replace( ',', '.', ap_in( 'qty' ) );
	ap_insert( 'supplies', $data );
	ap_back( 'Item cadastrado.' );
}

/** Baixa manual: usou, perdeu, quebrou. */
function ap_do_supply_use() {
	ap_require( 'estoque' );
	$s = ap_get( 'supplies', ap_in( 'id', 'int' ) );
	if ( ! $s ) {
		ap_back( 'Item não encontrado.', 'erro' );
	}
	$q = (float) str_replace( ',', '.', ap_in( 'qty' ) );
	if ( $q <= 0 ) {
		ap_back( 'Informe quanto saiu.', 'erro' );
	}
	ap_update( 'supplies', $s->id, array( 'qty' => max( 0, (float) $s->qty - $q ) ) );
	ap_stock_move( 'supply', $s->id, 'baixa', -$q, $q * (float) $s->unit_cost, ap_in( 'note' ) ? ap_in( 'note' ) : 'Baixa manual' );
	ap_stock_check_low();
	ap_back( 'Baixa registrada: ' . ap_qty_label( $q, 'm2' === $s->unit ? 'm²' : $s->unit ) . ' de ' . $s->name . '.' );
}

/**
 * Contagem de estoque: recebe o que foi contado de cada item (bobina: rolos inteiros + m² do rolo aberto),
 * ajusta a quantidade e registra a diferença como "contagem".
 */
function ap_do_supply_count() {
	ap_require( 'estoque' );
	$counts = isset( $_POST['c'] ) ? (array) wp_unslash( $_POST['c'] ) : array(); // phpcs:ignore WordPress.Security.NonceVerification,WordPress.Security.ValidatedSanitizedInput
	$n      = 0;
	$delta  = 0.0;
	foreach ( $counts as $id => $row ) {
		$s = ap_get( 'supplies', absint( $id ) );
		if ( ! $s || ! is_array( $row ) ) {
			continue;
		}
		$num = function ( $v ) {
			return '' === trim( (string) $v ) ? null : (float) str_replace( ',', '.', (string) $v );
		};
		if ( 'm2' === $s->unit && (float) $s->roll_m2 > 0 ) {
			$r = $num( $row['rolls'] ?? '' );
			$x = $num( $row['extra'] ?? '' );
			if ( null === $r && null === $x ) {
				continue;
			}
			$new = max( 0, (float) $r ) * (float) $s->roll_m2 + max( 0, (float) $x );
		} else {
			$q = $num( $row['qty'] ?? '' );
			if ( null === $q ) {
				continue;
			}
			$new = max( 0, $q );
		}
		$diff = round( $new - (float) $s->qty, 2 );
		ap_update( 'supplies', $s->id, array( 'qty' => $new, 'counted_at' => ap_now() ) );
		if ( 0.0 !== $diff ) {
			ap_stock_move( 'supply', $s->id, 'contagem', $diff, abs( $diff ) * (float) $s->unit_cost, 'Contagem de estoque (antes: ' . ap_qty_label( $s->qty, $s->unit ) . ')' );
			$delta += $diff * (float) $s->unit_cost;
		}
		$n++;
	}
	ap_stock_check_low();
	ap_back( $n ? $n . ' item(ns) contado(s). ' . ( 0.0 !== round( $delta, 2 ) ? 'Diferença no valor do estoque: ' . ( $delta > 0 ? '+' : '' ) . ap_money( $delta ) . '.' : 'Nenhuma diferença.' ) : 'Preencha a contagem de pelo menos um item.', $n ? 'ok' : 'erro', ap_panel_url( 'insumos' ) );
}

/* -----------------------------------------------------------------------
 * Ligação catálogo → bobina (baixa automática em m²)
 * -------------------------------------------------------------------- */

/** Liga cada material do catálogo à bobina que ele consome (pela de maior estoque). Só preenche o que está sem ligação. */
function ap_link_catalog_supplies() {
	$rules = array(
		'Adesivo Padrão'                                   => '/^adesivo (brilho|fosco) .* seywa/i',
		'Adesivo Impresso com corte eletrônico'            => '/^adesivo (brilho|fosco) .* seywa/i',
		'Adesivo Premium'                                  => '/^adesivo (brilho|fosco) .* starpac de/i',
		'Adesivo Stoplight Brilho/Fosco'                   => '/^adesivo stoplight/i',
		'Adesivo Transparente ou Jateado'                  => '/^adesivo transparente/i',
		'Adesivo Perfurado'                                => '/^perfurado promo/i',
		'Adesivo Perfurado Premium'                        => '/^perfurado premiun/i',
		'Lona 440g com Acabamento (banner/faixa)'          => '/^lona 440g/i',
		'Lona 440g sem Acabamento'                         => '/^lona 440g/i',
		'Lona 440g com Acabamento (reforço e ilhós de alumínio)' => '/^lona 440g/i',
		'Lona Backlight Premium 440g'                      => '/^lona backlight/i',
		'Adesivo Padrão Brilho ou Fosco'                   => '/^adesivo (brilho|fosco) .* seywa/i',
		'Adesivo Premium ou Stoplight Brilho ou Fosco'     => '/^(adesivo (brilho|fosco) .* starpac de|adesivo stoplight)/i',
		'Lona Front 440g'                                  => '/^lona 440g/i',
	);
	$sups = ap_rows( 'supplies', "category = 'Bobina' AND active = 1" );
	$n    = 0;
	foreach ( ap_rows( 'catalog', 'supply_id = 0' ) as $c ) {
		if ( empty( $rules[ $c->name ] ) ) {
			continue;
		}
		$best = null;
		foreach ( $sups as $s ) {
			if ( preg_match( $rules[ $c->name ], $s->name ) && ( ! $best || (float) $s->qty > (float) $best->qty ) ) {
				$best = $s;
			}
		}
		if ( $best ) {
			ap_update( 'catalog', $c->id, array( 'supply_id' => $best->id ) );
			$n++;
		}
	}
	return $n;
}

add_action(
	'init',
	function () {
		if ( get_option( 'ap_link_supplies_done' ) || ! get_option( 'ap_import_auto' ) || ! ap_rows( 'supplies', "category = 'Bobina'" ) ) {
			return;
		}
		update_option( 'ap_link_supplies_done', 1, false );
		ap_link_catalog_supplies();
	},
	130
);

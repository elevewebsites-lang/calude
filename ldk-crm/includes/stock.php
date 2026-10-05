<?php
/**
 * Estoque: filamentos (pesados na balança), insumos (custo unitário médio), movimentações,
 * lista de compras e desejos, e impressoras.
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function lk_materials() {
	return array( 'PLA', 'PLA Silk', 'PLA Matte', 'PETG', 'TPU', 'ABS', 'ASA', 'PLA-CF', 'PETG-CF', 'Nylon', 'Resina', 'Outro' );
}

/* -----------------------------------------------------------------------
 * Filamentos
 * -------------------------------------------------------------------- */

function lk_filament_label( $f ) {
	return trim( $f->material . ' ' . $f->color_name ) . ( $f->brand ? ' · ' . $f->brand : '' );
}

/**
 * Custo por grama do carretel: preço pago ÷ gramas do carretel.
 */
function lk_filament_cost_g( $f ) {
	return $f->spool_g > 0 ? (float) $f->price / (int) $f->spool_g : 0;
}

/**
 * % que ainda resta do carretel (para a barrinha).
 */
function lk_filament_pct( $f ) {
	return $f->spool_g > 0 ? max( 0, min( 100, (int) round( $f->weight_g / $f->spool_g * 100 ) ) ) : 0;
}

function lk_do_filament_save() {
	lk_require( 'estoque' );
	$id   = lk_in( 'id', 'int' );
	$data = array(
		'brand'      => lk_in( 'brand' ),
		'material'   => lk_in( 'material' ) ? lk_in( 'material' ) : 'PLA',
		'color_name' => lk_in( 'color_name' ),
		'color_hex'  => sanitize_hex_color( lk_in( 'color_hex' ) ) ? sanitize_hex_color( lk_in( 'color_hex' ) ) : '#cccccc',
		'spool_g'    => max( 1, lk_in( 'spool_g', 'int' ) ),
		'price'      => lk_in( 'price', 'money' ),
		'tare_g'     => lk_in( 'tare_g', 'int' ),
		'min_g'      => lk_in( 'min_g', 'int' ),
		'link'       => lk_in( 'link', 'url' ),
		'notes'      => lk_in( 'notes' ),
	);
	// Peso: o que a balança mostrou menos o carretel vazio (tara), se informado.
	$scale = lk_in( 'scale_g' );
	if ( '' !== (string) $scale ) {
		$data['weight_g'] = max( 0, (float) str_replace( ',', '.', $scale ) - $data['tare_g'] );
	} elseif ( ! $id ) {
		$data['weight_g'] = $data['spool_g'];
	}
	if ( $id ) {
		$old = lk_get( 'filaments', $id );
		lk_update( 'filaments', $id, $data );
		if ( $old && isset( $data['weight_g'] ) && abs( $old->weight_g - $data['weight_g'] ) >= 1 ) {
			lk_stock_move( 'filament', $id, 'ajuste', $data['weight_g'] - $old->weight_g, 0, 'Pesagem na balança' );
		}
		lk_back( 'Filamento salvo.' );
	}
	$id = lk_insert( 'filaments', $data );
	lk_stock_move( 'filament', $id, 'compra', $data['weight_g'], $data['price'], 'Cadastro do carretel' );
	if ( lk_in( 'lancar', 'bool' ) && $data['price'] > 0 ) {
		lk_stock_expense( 'Filamento', 'Filamento ' . lk_filament_label( (object) $data ), $data['price'] );
	}
	lk_back( 'Filamento cadastrado.' );
}

/**
 * Pesagem rápida: só o número da balança.
 */
function lk_do_filament_weigh() {
	lk_require( 'estoque' );
	$f = lk_get( 'filaments', lk_in( 'id', 'int' ) );
	if ( $f ) {
		$new = max( 0, (float) str_replace( ',', '.', lk_in( 'scale_g' ) ) - (int) $f->tare_g );
		lk_update( 'filaments', $f->id, array( 'weight_g' => $new ) );
		lk_stock_move( 'filament', $f->id, 'ajuste', $new - $f->weight_g, 0, 'Pesagem na balança' );
		lk_stock_check_low();
	}
	lk_back( 'Peso atualizado.' );
}

function lk_do_filament_archive() {
	lk_require( 'estoque' );
	$f = lk_get( 'filaments', lk_in( 'id', 'int' ) );
	if ( $f ) {
		lk_update( 'filaments', $f->id, array( 'active' => $f->active ? 0 : 1 ) );
	}
	lk_back( $f && $f->active ? 'Carretel arquivado (acabou).' : 'Carretel reativado.' );
}

/* -----------------------------------------------------------------------
 * Insumos
 * -------------------------------------------------------------------- */

function lk_do_supply_save() {
	lk_require( 'estoque' );
	$id   = lk_in( 'id', 'int' );
	$data = array(
		'name'      => lk_in( 'name' ),
		'unit'      => lk_in( 'unit' ) ? lk_in( 'unit' ) : 'un',
		'min_qty'   => (float) str_replace( ',', '.', lk_in( 'min_qty' ) ),
		'per_order' => lk_in( 'per_order', 'bool' ) ? 1 : 0,
		'link'      => lk_in( 'link', 'url' ),
	);
	if ( '' !== (string) lk_in( 'unit_cost' ) ) {
		$data['unit_cost'] = lk_parse_money( lk_in( 'unit_cost' ) );
	}
	if ( '' !== (string) lk_in( 'qty' ) ) {
		$data['qty'] = (float) str_replace( ',', '.', lk_in( 'qty' ) );
	}
	if ( ! $data['name'] ) {
		lk_back( 'Dê um nome para o insumo.', 'erro' );
	}
	if ( $id ) {
		lk_update( 'supplies', $id, $data );
		lk_back( 'Insumo salvo.' );
	}
	$id = lk_insert( 'supplies', $data );
	if ( wp_doing_ajax() || lk_in( 'ajax', 'bool' ) ) {
		wp_send_json( array( 'id' => $id, 'label' => $data['name'], 'cost' => $data['unit_cost'] ?? 0, 'unit' => $data['unit'] ) );
	}
	lk_back( 'Insumo cadastrado.' );
}

/**
 * Compra de insumo: ex. pacote com 100 sacolinhas por R$ 25 + R$ 10 de frete → R$ 0,35 cada.
 * O custo unitário vira a média ponderada entre o que tinha e o que chegou.
 */
function lk_do_supply_buy() {
	lk_require( 'estoque' );
	$s = lk_get( 'supplies', lk_in( 'id', 'int' ) );
	if ( ! $s ) {
		lk_back( 'Insumo não encontrado.', 'erro' );
	}
	$qty     = (float) str_replace( ',', '.', lk_in( 'qty' ) );
	$total   = lk_parse_money( lk_in( 'total' ) ) + lk_parse_money( lk_in( 'freight' ) );
	if ( $qty <= 0 || $total < 0 ) {
		lk_back( 'Informe a quantidade que veio e quanto pagou.', 'erro' );
	}
	$unit    = $total / $qty;
	$have    = max( 0, (float) $s->qty );
	$avg     = $have + $qty > 0 ? ( $have * (float) $s->unit_cost + $total ) / ( $have + $qty ) : $unit;
	lk_update( 'supplies', $s->id, array( 'qty' => $have + $qty, 'unit_cost' => round( $avg, 4 ) ) );
	$trans = lk_in( 'lancar', 'bool' ) && $total > 0 ? lk_stock_expense( 'Insumos', 'Compra: ' . $s->name . ' (' . lk_qty_label( $qty, $s->unit ) . ')', $total ) : 0;
	lk_stock_move( 'supply', $s->id, 'compra', $qty, $total, lk_in( 'note' ), $trans );
	lk_back( 'Compra registrada: ' . lk_qty_label( $qty, $s->unit ) . ' a ' . lk_money( $unit ) . ' cada (média agora ' . lk_money( $avg ) . ').' );
}

function lk_do_supply_archive() {
	lk_require( 'estoque' );
	$s = lk_get( 'supplies', lk_in( 'id', 'int' ) );
	if ( $s ) {
		lk_update( 'supplies', $s->id, array( 'active' => $s->active ? 0 : 1 ) );
	}
	lk_back( 'Insumo atualizado.' );
}


/* -----------------------------------------------------------------------
 * Movimentações e consumo
 * -------------------------------------------------------------------- */

function lk_stock_move( $type, $item_id, $kind, $qty, $total = 0, $note = '', $trans = 0, $project = 0 ) {
	return lk_insert(
		'stock_moves',
		array(
			'item_type'  => $type,
			'item_id'    => (int) $item_id,
			'project_id' => (int) $project,
			'kind'       => $kind,
			'qty'        => round( (float) $qty, 2 ),
			'total'      => round( (float) $total, 2 ),
			'note'       => mb_substr( (string) $note, 0, 250 ),
			'trans_id'   => (int) $trans,
			'user_id'    => get_current_user_id(),
		)
	);
}

/**
 * Lança uma saída paga no financeiro (compra de material).
 */
function lk_stock_expense( $category, $description, $amount ) {
	return lk_insert(
		'transactions',
		array(
			'type'        => 'out',
			'category'    => $category,
			'description' => $description,
			'amount'      => round( (float) $amount, 2 ),
			'due_date'    => lk_today(),
			'paid_at'     => lk_today(),
			'status'      => 'pago',
		)
	);
}

/**
 * Consumo de um pedido: filamentos (g) e insumos (qtd). Devolve o custo do material usado.
 */
function lk_stock_consume( $project_id, $filaments, $supplies ) {
	$cost = 0;
	foreach ( $filaments as $row ) {
		$f = lk_get( 'filaments', (int) $row[0] );
		$g = max( 0, (float) $row[1] );
		if ( ! $f || $g <= 0 ) {
			continue;
		}
		lk_update( 'filaments', $f->id, array( 'weight_g' => max( 0, $f->weight_g - $g ) ) );
		$c     = $g * lk_filament_cost_g( $f );
		$cost += $c;
		lk_stock_move( 'filament', $f->id, 'uso', -$g, $c, '', 0, $project_id );
	}
	foreach ( $supplies as $row ) {
		$s = lk_get( 'supplies', (int) $row[0] );
		$q = max( 0, (float) $row[1] );
		if ( ! $s || $q <= 0 ) {
			continue;
		}
		lk_update( 'supplies', $s->id, array( 'qty' => max( 0, $s->qty - $q ) ) );
		$c     = $q * (float) $s->unit_cost;
		$cost += $c;
		lk_stock_move( 'supply', $s->id, 'uso', -$q, $c, '', 0, $project_id );
	}
	lk_stock_check_low();
	return round( $cost, 2 );
}

/**
 * Desfaz o consumo de um pedido (devolve ao estoque).
 */
function lk_stock_unconsume( $project_id ) {
	foreach ( lk_rows( 'stock_moves', "project_id = %d AND kind = 'uso'", array( $project_id ) ) as $m ) {
		$table = 'filament' === $m->item_type ? 'filaments' : 'supplies';
		$field = 'filament' === $m->item_type ? 'weight_g' : 'qty';
		$item  = lk_get( $table, $m->item_id );
		if ( $item ) {
			lk_update( $table, $item->id, array( $field => $item->$field - $m->qty ) );
		}
		lk_delete( 'stock_moves', $m->id );
	}
}

/**
 * Abaixo do mínimo → entra sozinho na lista de compras (uma vez por item em aberto).
 */
function lk_stock_check_low() {
	$open = array();
	foreach ( lk_rows( 'shopping', "status = 'aberto' AND item_type <> ''" ) as $sh ) {
		$open[ $sh->item_type . '-' . $sh->item_id ] = true;
	}
	foreach ( lk_rows( 'filaments', 'active = 1 AND min_g > 0 AND weight_g < min_g' ) as $f ) {
		if ( empty( $open[ 'filament-' . $f->id ] ) ) {
			lk_insert(
				'shopping',
				array(
					'kind'      => 'compra',
					'title'     => 'Filamento ' . lk_filament_label( $f ),
					'link'      => $f->link,
					'price'     => $f->price,
					'qty'       => 1,
					'priority'  => $f->weight_g < $f->min_g / 2 ? 'alta' : 'media',
					'item_type' => 'filament',
					'item_id'   => $f->id,
					'notes'     => 'Automático: restam ' . round( $f->weight_g ) . ' g',
				)
			);
		}
	}
	foreach ( lk_rows( 'supplies', 'active = 1 AND min_qty > 0 AND qty < min_qty' ) as $s ) {
		if ( empty( $open[ 'supply-' . $s->id ] ) ) {
			lk_insert(
				'shopping',
				array(
					'kind'      => 'compra',
					'title'     => $s->name,
					'link'      => $s->link,
					'qty'       => 1,
					'priority'  => 'media',
					'item_type' => 'supply',
					'item_id'   => $s->id,
					'notes'     => 'Automático: restam ' . lk_qty_label( $s->qty, $s->unit ),
				)
			);
		}
	}
}
add_action( 'lk_daily', 'lk_stock_check_low' );

function lk_stock_low() {
	return array(
		'filaments' => lk_rows( 'filaments', 'active = 1 AND min_g > 0 AND weight_g < min_g' ),
		'supplies'  => lk_rows( 'supplies', 'active = 1 AND min_qty > 0 AND qty < min_qty' ),
	);
}

/* -----------------------------------------------------------------------
 * Lista de compras e desejos
 * -------------------------------------------------------------------- */

function lk_do_shopping_save() {
	lk_require( 'estoque' );
	$id   = lk_in( 'id', 'int' );
	$data = array(
		'kind'     => 'desejo' === lk_in( 'kind' ) ? 'desejo' : 'compra',
		'title'    => lk_in( 'title' ),
		'link'     => lk_in( 'link', 'url' ),
		'price'    => lk_in( 'price', 'money' ),
		'qty'      => max( 1, (float) str_replace( ',', '.', lk_in( 'qty' ) ) ),
		'priority' => in_array( lk_in( 'priority' ), array( 'baixa', 'media', 'alta' ), true ) ? lk_in( 'priority' ) : 'media',
		'notes'    => lk_in( 'notes' ),
	);
	if ( ! $data['title'] && $data['link'] ) {
		$data['title'] = lk_link_title( $data['link'] );
	}
	if ( ! $data['title'] ) {
		lk_back( 'Dê um nome ou cole o link.', 'erro' );
	}
	if ( $id ) {
		lk_update( 'shopping', $id, $data );
	} else {
		lk_insert( 'shopping', $data );
	}
	lk_back( 'desejo' === $data['kind'] ? 'Desejo salvo.' : 'Item na lista de compras.' );
}


/**
 * Comprado: sai da lista, lança no financeiro e, se for filamento/insumo ligado, entra no estoque.
 */
function lk_do_shopping_bought() {
	lk_require( 'estoque' );
	$sh = lk_get( 'shopping', lk_in( 'id', 'int' ) );
	if ( ! $sh ) {
		lk_back();
	}
	$paid = '' !== (string) lk_in( 'paid' ) ? lk_parse_money( lk_in( 'paid' ) ) : (float) $sh->price * (float) $sh->qty;
	$trans = $paid > 0 && lk_in( 'lancar', 'bool' ) ? lk_stock_expense( 'desejo' === $sh->kind ? 'Equipamento' : ( 'filament' === $sh->item_type ? 'Filamento' : 'Insumos' ), $sh->title, $paid ) : 0;
	lk_update( 'shopping', $sh->id, array( 'status' => 'comprado', 'bought_at' => lk_now() ) );
	$msg = 'Marcado como comprado.';
	if ( 'filament' === $sh->item_type && ( $f = lk_get( 'filaments', $sh->item_id ) ) ) { // phpcs:ignore
		$msg .= ' Cadastre o carretel novo em Filamentos quando chegar (dá para duplicar o antigo).';
	} elseif ( 'supply' === $sh->item_type && ( $s = lk_get( 'supplies', $sh->item_id ) ) && lk_in( 'qty_in' ) ) { // phpcs:ignore
		$qty  = (float) str_replace( ',', '.', lk_in( 'qty_in' ) );
		$have = max( 0, (float) $s->qty );
		$avg  = $have + $qty > 0 ? ( $have * (float) $s->unit_cost + $paid ) / ( $have + $qty ) : 0;
		lk_update( 'supplies', $s->id, array( 'qty' => $have + $qty, 'unit_cost' => round( $avg, 4 ) ) );
		lk_stock_move( 'supply', $s->id, 'compra', $qty, $paid, 'Lista de compras', $trans );
		$msg .= ' Estoque de ' . $s->name . ' atualizado.';
	}
	lk_back( $msg );
}

function lk_do_shopping_delete() {
	lk_require( 'estoque' );
	lk_delete( 'shopping', lk_in( 'id', 'int' ) );
	lk_back( 'Item removido.' );
}

/* -----------------------------------------------------------------------
 * Impressoras
 * -------------------------------------------------------------------- */

function lk_do_printer_save() {
	lk_require( 'estoque' );
	$id   = lk_in( 'id', 'int' );
	$data = array(
		'name'        => lk_in( 'name' ),
		'model'       => lk_in( 'model' ),
		'watts'       => max( 1, lk_in( 'watts', 'int' ) ),
		'price'       => lk_in( 'price', 'money' ),
		'life_hours'  => max( 1, lk_in( 'life_hours', 'int' ) ),
		'hours_used'  => (float) str_replace( ',', '.', lk_in( 'hours_used' ) ),
		'maint_every' => lk_in( 'maint_every', 'int' ),
		'bought_at'   => lk_in( 'bought_at', 'date' ),
		'notes'       => lk_in( 'notes' ),
	);
	if ( ! $data['name'] ) {
		lk_back( 'Dê um nome para a impressora.', 'erro' );
	}
	if ( $id ) {
		lk_update( 'printers', $id, $data );
	} else {
		lk_insert( 'printers', $data );
	}
	lk_back( 'Impressora salva.' );
}

function lk_do_printer_maint() {
	lk_require( 'estoque' );
	$p = lk_get( 'printers', lk_in( 'id', 'int' ) );
	if ( $p ) {
		lk_update( 'printers', $p->id, array( 'maint_last' => $p->hours_used ) );
	}
	lk_back( 'Manutenção registrada.' );
}

function lk_printer_maint_due( $p ) {
	return $p->maint_every > 0 && ( $p->hours_used - $p->maint_last ) >= $p->maint_every;
}

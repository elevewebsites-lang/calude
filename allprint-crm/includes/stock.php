<?php
/**
 * Estoque: filamentos (pesados na balança), insumos (custo unitário médio), movimentações,
 * lista de compras e desejos, e impressoras.
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function ap_materials() {
	return array( 'PLA', 'PLA Silk', 'PLA Matte', 'PETG', 'TPU', 'ABS', 'ASA', 'PLA-CF', 'PETG-CF', 'Nylon', 'Resina', 'Outro' );
}

/* -----------------------------------------------------------------------
 * Filamentos
 * -------------------------------------------------------------------- */

function ap_filament_label( $f ) {
	return trim( $f->material . ' ' . $f->color_name ) . ( $f->brand ? ' · ' . $f->brand : '' );
}

/**
 * Custo por grama do carretel: preço pago ÷ gramas do carretel.
 */
function ap_filament_cost_g( $f ) {
	return $f->spool_g > 0 ? (float) $f->price / (int) $f->spool_g : 0;
}

/**
 * % que ainda resta do carretel (para a barrinha).
 */
function ap_filament_pct( $f ) {
	return $f->spool_g > 0 ? max( 0, min( 100, (int) round( $f->weight_g / $f->spool_g * 100 ) ) ) : 0;
}

function ap_do_filament_save() {
	ap_require( 'estoque' );
	$id   = ap_in( 'id', 'int' );
	$data = array(
		'brand'      => ap_in( 'brand' ),
		'material'   => ap_in( 'material' ) ? ap_in( 'material' ) : 'PLA',
		'color_name' => ap_in( 'color_name' ),
		'color_hex'  => sanitize_hex_color( ap_in( 'color_hex' ) ) ? sanitize_hex_color( ap_in( 'color_hex' ) ) : '#cccccc',
		'spool_g'    => max( 1, ap_in( 'spool_g', 'int' ) ),
		'price'      => ap_in( 'price', 'money' ),
		'tare_g'     => ap_in( 'tare_g', 'int' ),
		'min_g'      => ap_in( 'min_g', 'int' ),
		'link'       => ap_in( 'link', 'url' ),
		'notes'      => ap_in( 'notes' ),
	);
	// Peso: o que a balança mostrou menos o carretel vazio (tara), se informado.
	$scale = ap_in( 'scale_g' );
	if ( '' !== (string) $scale ) {
		$data['weight_g'] = max( 0, (float) str_replace( ',', '.', $scale ) - $data['tare_g'] );
	} elseif ( ! $id ) {
		$data['weight_g'] = $data['spool_g'];
	}
	if ( $id ) {
		$old = ap_get( 'filaments', $id );
		ap_update( 'filaments', $id, $data );
		if ( $old && isset( $data['weight_g'] ) && abs( $old->weight_g - $data['weight_g'] ) >= 1 ) {
			ap_stock_move( 'filament', $id, 'ajuste', $data['weight_g'] - $old->weight_g, 0, 'Pesagem na balança' );
		}
		ap_back( 'Filamento salvo.' );
	}
	$id = ap_insert( 'filaments', $data );
	ap_stock_move( 'filament', $id, 'compra', $data['weight_g'], $data['price'], 'Cadastro do carretel' );
	if ( ap_in( 'lancar', 'bool' ) && $data['price'] > 0 ) {
		ap_stock_expense( 'Filamento', 'Filamento ' . ap_filament_label( (object) $data ), $data['price'] );
	}
	ap_back( 'Filamento cadastrado.' );
}

/**
 * Pesagem rápida: só o número da balança.
 */
function ap_do_filament_weigh() {
	ap_require( 'estoque' );
	$f = ap_get( 'filaments', ap_in( 'id', 'int' ) );
	if ( $f ) {
		$new = max( 0, (float) str_replace( ',', '.', ap_in( 'scale_g' ) ) - (int) $f->tare_g );
		ap_update( 'filaments', $f->id, array( 'weight_g' => $new ) );
		ap_stock_move( 'filament', $f->id, 'ajuste', $new - $f->weight_g, 0, 'Pesagem na balança' );
		ap_stock_check_low();
	}
	ap_back( 'Peso atualizado.' );
}

function ap_do_filament_archive() {
	ap_require( 'estoque' );
	$f = ap_get( 'filaments', ap_in( 'id', 'int' ) );
	if ( $f ) {
		ap_update( 'filaments', $f->id, array( 'active' => $f->active ? 0 : 1 ) );
	}
	ap_back( $f && $f->active ? 'Carretel arquivado (acabou).' : 'Carretel reativado.' );
}

/* -----------------------------------------------------------------------
 * Insumos
 * -------------------------------------------------------------------- */

function ap_do_supply_save() {
	ap_require( 'estoque' );
	$id   = ap_in( 'id', 'int' );
	$data = array(
		'name'      => ap_in( 'name' ),
		'unit'      => ap_in( 'unit' ) ? ap_in( 'unit' ) : 'un',
		'min_qty'   => (float) str_replace( ',', '.', ap_in( 'min_qty' ) ),
		'per_order' => ap_in( 'per_order', 'bool' ) ? 1 : 0,
		'link'      => ap_in( 'link', 'url' ),
	);
	if ( '' !== (string) ap_in( 'unit_cost' ) ) {
		$data['unit_cost'] = ap_parse_money( ap_in( 'unit_cost' ) );
	}
	if ( '' !== (string) ap_in( 'qty' ) ) {
		$data['qty'] = (float) str_replace( ',', '.', ap_in( 'qty' ) );
	}
	if ( ! $data['name'] ) {
		ap_back( 'Dê um nome para o insumo.', 'erro' );
	}
	if ( $id ) {
		ap_update( 'supplies', $id, $data );
		ap_back( 'Insumo salvo.' );
	}
	$id = ap_insert( 'supplies', $data );
	if ( wp_doing_ajax() || ap_in( 'ajax', 'bool' ) ) {
		wp_send_json( array( 'id' => $id, 'label' => $data['name'], 'cost' => $data['unit_cost'] ?? 0, 'unit' => $data['unit'] ) );
	}
	ap_back( 'Insumo cadastrado.' );
}

/**
 * Compra de insumo: ex. pacote com 100 sacolinhas por R$ 25 + R$ 10 de frete → R$ 0,35 cada.
 * O custo unitário vira a média ponderada entre o que tinha e o que chegou.
 */
function ap_do_supply_buy() {
	ap_require( 'estoque' );
	$s = ap_get( 'supplies', ap_in( 'id', 'int' ) );
	if ( ! $s ) {
		ap_back( 'Insumo não encontrado.', 'erro' );
	}
	$qty     = (float) str_replace( ',', '.', ap_in( 'qty' ) );
	$total   = ap_parse_money( ap_in( 'total' ) ) + ap_parse_money( ap_in( 'freight' ) );
	if ( $qty <= 0 || $total < 0 ) {
		ap_back( 'Informe a quantidade que veio e quanto pagou.', 'erro' );
	}
	$unit    = $total / $qty;
	$have    = max( 0, (float) $s->qty );
	$avg     = $have + $qty > 0 ? ( $have * (float) $s->unit_cost + $total ) / ( $have + $qty ) : $unit;
	ap_update( 'supplies', $s->id, array( 'qty' => $have + $qty, 'unit_cost' => round( $avg, 4 ) ) );
	$trans = ap_in( 'lancar', 'bool' ) && $total > 0 ? ap_stock_expense( 'Insumos', 'Compra: ' . $s->name . ' (' . ap_qty_label( $qty, $s->unit ) . ')', $total ) : 0;
	ap_stock_move( 'supply', $s->id, 'compra', $qty, $total, ap_in( 'note' ), $trans );
	ap_back( 'Compra registrada: ' . ap_qty_label( $qty, $s->unit ) . ' a ' . ap_money( $unit ) . ' cada (média agora ' . ap_money( $avg ) . ').' );
}

function ap_do_supply_archive() {
	ap_require( 'estoque' );
	$s = ap_get( 'supplies', ap_in( 'id', 'int' ) );
	if ( $s ) {
		ap_update( 'supplies', $s->id, array( 'active' => $s->active ? 0 : 1 ) );
	}
	ap_back( 'Insumo atualizado.' );
}


/* -----------------------------------------------------------------------
 * Movimentações e consumo
 * -------------------------------------------------------------------- */

function ap_stock_move( $type, $item_id, $kind, $qty, $total = 0, $note = '', $trans = 0, $project = 0 ) {
	return ap_insert(
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
function ap_stock_expense( $category, $description, $amount ) {
	return ap_insert(
		'transactions',
		array(
			'type'        => 'out',
			'category'    => $category,
			'description' => $description,
			'amount'      => round( (float) $amount, 2 ),
			'due_date'    => ap_today(),
			'paid_at'     => ap_today(),
			'status'      => 'pago',
		)
	);
}

/**
 * Consumo de um pedido: filamentos (g) e insumos (qtd). Devolve o custo do material usado.
 */
function ap_stock_consume( $project_id, $filaments, $supplies ) {
	$cost = 0;
	foreach ( $filaments as $row ) {
		$f = ap_get( 'filaments', (int) $row[0] );
		$g = max( 0, (float) $row[1] );
		if ( ! $f || $g <= 0 ) {
			continue;
		}
		ap_update( 'filaments', $f->id, array( 'weight_g' => max( 0, $f->weight_g - $g ) ) );
		$c     = $g * ap_filament_cost_g( $f );
		$cost += $c;
		ap_stock_move( 'filament', $f->id, 'uso', -$g, $c, '', 0, $project_id );
	}
	foreach ( $supplies as $row ) {
		$s = ap_get( 'supplies', (int) $row[0] );
		$q = max( 0, (float) $row[1] );
		if ( ! $s || $q <= 0 ) {
			continue;
		}
		ap_update( 'supplies', $s->id, array( 'qty' => max( 0, $s->qty - $q ) ) );
		$c     = $q * (float) $s->unit_cost;
		$cost += $c;
		ap_stock_move( 'supply', $s->id, 'uso', -$q, $c, '', 0, $project_id );
	}
	ap_stock_check_low();
	return round( $cost, 2 );
}

/**
 * Desfaz o consumo de um pedido (devolve ao estoque).
 */
function ap_stock_unconsume( $project_id ) {
	foreach ( ap_rows( 'stock_moves', "project_id = %d AND kind = 'uso'", array( $project_id ) ) as $m ) {
		$table = 'filament' === $m->item_type ? 'filaments' : 'supplies';
		$field = 'filament' === $m->item_type ? 'weight_g' : 'qty';
		$item  = ap_get( $table, $m->item_id );
		if ( $item ) {
			ap_update( $table, $item->id, array( $field => $item->$field - $m->qty ) );
		}
		ap_delete( 'stock_moves', $m->id );
	}
}

/**
 * Abaixo do mínimo → entra sozinho na lista de compras (uma vez por item em aberto).
 */
function ap_stock_check_low() {
	$open = array();
	foreach ( ap_rows( 'shopping', "status = 'aberto' AND item_type <> ''" ) as $sh ) {
		$open[ $sh->item_type . '-' . $sh->item_id ] = true;
	}
	foreach ( ap_rows( 'filaments', 'active = 1 AND min_g > 0 AND weight_g < min_g' ) as $f ) {
		if ( empty( $open[ 'filament-' . $f->id ] ) ) {
			ap_insert(
				'shopping',
				array(
					'kind'      => 'compra',
					'title'     => 'Filamento ' . ap_filament_label( $f ),
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
	foreach ( ap_rows( 'supplies', 'active = 1 AND min_qty > 0 AND qty < min_qty' ) as $s ) {
		if ( empty( $open[ 'supply-' . $s->id ] ) ) {
			ap_insert(
				'shopping',
				array(
					'kind'      => 'compra',
					'title'     => $s->name,
					'link'      => $s->link,
					'qty'       => 1,
					'priority'  => 'media',
					'item_type' => 'supply',
					'item_id'   => $s->id,
					'notes'     => 'Automático: restam ' . ap_qty_label( $s->qty, $s->unit ),
				)
			);
		}
	}
}
add_action( 'ap_daily', 'ap_stock_check_low' );

function ap_stock_low() {
	return array(
		'filaments' => ap_rows( 'filaments', 'active = 1 AND min_g > 0 AND weight_g < min_g' ),
		'supplies'  => ap_rows( 'supplies', 'active = 1 AND min_qty > 0 AND qty < min_qty' ),
	);
}

/* -----------------------------------------------------------------------
 * Lista de compras e desejos
 * -------------------------------------------------------------------- */

function ap_do_shopping_save() {
	ap_require( 'estoque' );
	$id   = ap_in( 'id', 'int' );
	$data = array(
		'kind'     => 'desejo' === ap_in( 'kind' ) ? 'desejo' : 'compra',
		'title'    => ap_in( 'title' ),
		'link'     => ap_in( 'link', 'url' ),
		'price'    => ap_in( 'price', 'money' ),
		'qty'      => max( 1, (float) str_replace( ',', '.', ap_in( 'qty' ) ) ),
		'priority' => in_array( ap_in( 'priority' ), array( 'baixa', 'media', 'alta' ), true ) ? ap_in( 'priority' ) : 'media',
		'notes'    => ap_in( 'notes' ),
	);
	if ( ! $data['title'] && $data['link'] ) {
		$data['title'] = ap_link_title( $data['link'] );
	}
	if ( ! $data['title'] ) {
		ap_back( 'Dê um nome ou cole o link.', 'erro' );
	}
	if ( $id ) {
		ap_update( 'shopping', $id, $data );
	} else {
		ap_insert( 'shopping', $data );
	}
	ap_back( 'desejo' === $data['kind'] ? 'Desejo salvo.' : 'Item na lista de compras.' );
}


/**
 * Comprado: sai da lista, lança no financeiro e, se for filamento/insumo ligado, entra no estoque.
 */
function ap_do_shopping_bought() {
	ap_require( 'estoque' );
	$sh = ap_get( 'shopping', ap_in( 'id', 'int' ) );
	if ( ! $sh ) {
		ap_back();
	}
	$paid = '' !== (string) ap_in( 'paid' ) ? ap_parse_money( ap_in( 'paid' ) ) : (float) $sh->price * (float) $sh->qty;
	$trans = $paid > 0 && ap_in( 'lancar', 'bool' ) ? ap_stock_expense( 'desejo' === $sh->kind ? 'Equipamento' : ( 'filament' === $sh->item_type ? 'Filamento' : 'Insumos' ), $sh->title, $paid ) : 0;
	ap_update( 'shopping', $sh->id, array( 'status' => 'comprado', 'bought_at' => ap_now() ) );
	$msg = 'Marcado como comprado.';
	if ( 'filament' === $sh->item_type && ( $f = ap_get( 'filaments', $sh->item_id ) ) ) { // phpcs:ignore
		$msg .= ' Cadastre o carretel novo em Filamentos quando chegar (dá para duplicar o antigo).';
	} elseif ( 'supply' === $sh->item_type && ( $s = ap_get( 'supplies', $sh->item_id ) ) && ap_in( 'qty_in' ) ) { // phpcs:ignore
		$qty  = (float) str_replace( ',', '.', ap_in( 'qty_in' ) );
		$have = max( 0, (float) $s->qty );
		$avg  = $have + $qty > 0 ? ( $have * (float) $s->unit_cost + $paid ) / ( $have + $qty ) : 0;
		ap_update( 'supplies', $s->id, array( 'qty' => $have + $qty, 'unit_cost' => round( $avg, 4 ) ) );
		ap_stock_move( 'supply', $s->id, 'compra', $qty, $paid, 'Lista de compras', $trans );
		$msg .= ' Estoque de ' . $s->name . ' atualizado.';
	}
	ap_back( $msg );
}

function ap_do_shopping_delete() {
	ap_require( 'estoque' );
	ap_delete( 'shopping', ap_in( 'id', 'int' ) );
	ap_back( 'Item removido.' );
}

/* -----------------------------------------------------------------------
 * Impressoras
 * -------------------------------------------------------------------- */

function ap_do_printer_save() {
	ap_require( 'estoque' );
	$id   = ap_in( 'id', 'int' );
	$data = array(
		'name'        => ap_in( 'name' ),
		'model'       => ap_in( 'model' ),
		'watts'       => max( 1, ap_in( 'watts', 'int' ) ),
		'price'       => ap_in( 'price', 'money' ),
		'life_hours'  => max( 1, ap_in( 'life_hours', 'int' ) ),
		'hours_used'  => (float) str_replace( ',', '.', ap_in( 'hours_used' ) ),
		'maint_every' => ap_in( 'maint_every', 'int' ),
		'bought_at'   => ap_in( 'bought_at', 'date' ),
		'notes'       => ap_in( 'notes' ),
	);
	if ( ! $data['name'] ) {
		ap_back( 'Dê um nome para a impressora.', 'erro' );
	}
	if ( $id ) {
		ap_update( 'printers', $id, $data );
	} else {
		ap_insert( 'printers', $data );
	}
	ap_back( 'Impressora salva.' );
}

function ap_do_printer_maint() {
	ap_require( 'estoque' );
	$p = ap_get( 'printers', ap_in( 'id', 'int' ) );
	if ( $p ) {
		ap_update( 'printers', $p->id, array( 'maint_last' => $p->hours_used ) );
	}
	ap_back( 'Manutenção registrada.' );
}

function ap_printer_maint_due( $p ) {
	return $p->maint_every > 0 && ( $p->hours_used - $p->maint_last ) >= $p->maint_every;
}

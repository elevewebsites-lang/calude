<?php
/**
 * Pedido manual (balcão, WhatsApp, telefone): a equipe monta o pedido para um cliente.
 * Usa o mesmo catálogo e a mesma conta do pedido do cliente, mas com:
 *  - escolha do cliente (ou cadastro rápido) e da tabela de preço (terceirizado, empresa, pessoa física);
 *  - valor ajustável por linha, desconto, cupom e crédito do cliente;
 *  - forma de pagamento: pago, sinal, na retirada ou aguardando (link de Pix).
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function ap_channels_manual() {
	return array( 'WhatsApp' => 'WhatsApp', 'Balcão' => 'Balcão (presencial)', 'Telefone' => 'Telefone', 'E-mail' => 'E-mail', 'Indicação' => 'Indicação', 'Outro' => 'Outro' );
}

function ap_do_manual_order() {
	ap_require( 'projetos' );
	$cid    = ap_in( 'client_id', 'int' );
	$client = $cid ? ap_get( 'clients', $cid ) : null;
	if ( ! $client ) {
		$name = ap_in( 'new_company' ) ? ap_in( 'new_company' ) : ap_in( 'new_name' );
		if ( ! $name ) {
			ap_back( 'Escolha o cliente ou preencha o nome do novo cliente.', 'erro' );
		}
		$kinds = array( 'Terceirizado', 'Empresa', 'Cliente P/F' );
		$kind  = in_array( ap_in( 'new_kind' ), $kinds, true ) ? ap_in( 'new_kind' ) : 'Terceirizado';
		$cid   = ap_insert(
			'clients',
			array(
				'name'     => ap_in( 'new_name' ) ? ap_in( 'new_name' ) : $name,
				'company'  => ap_in( 'new_company' ),
				'cnpj'     => ap_in( 'new_cnpj' ),
				'email'    => ap_in( 'new_email', 'email' ),
				'phone'    => ap_in( 'new_whatsapp' ),
				'whatsapp' => ap_in( 'new_whatsapp' ),
				'source'   => ap_in( 'canal' ),
				'approved' => 1,
				'status'   => 'ativo',
				'kind'     => $kind,
			)
		);
		$client = ap_get( 'clients', $cid );
	}
	$items = ap_order_items_from_post();
	if ( ! $items ) {
		ap_back( 'Adicione pelo menos um item.', 'erro' );
	}
	$tier  = isset( ap_tiers()[ ap_in( 'tabela' ) ] ) ? ap_in( 'tabela' ) : ap_client_tier( $client );
	$price = ap_price_items( $items, $tier, (bool) ap_in( 'aplicar_minimo', 'bool' ) );
	$d     = ap_discounts( $client->id, $price['total'], ap_in( 'cupom' ), (bool) ap_in( 'usar_credito', 'bool' ), (float) ap_in( 'desconto', 'money' ) );
	if ( ap_in( 'cupom' ) && ! $d['coupon'] ) {
		ap_back( $d['coupon_msg'] . ' Tire o cupom ou corrija e crie o pedido de novo.', 'erro' );
	}
	$cat = array();
	foreach ( ap_catalog( false ) as $c ) {
		$cat[ $c->id ] = $c;
	}
	$sides_pt = array( 't' => 'cima', 'b' => 'baixo', 'l' => 'esquerda', 'r' => 'direita' );
	$saved    = array();
	foreach ( $items as $it ) {
		$c      = $cat[ $it['material'] ];
		$unit   = $c->unit ? $c->unit : 'm2';
		$extras = array_filter(
			array(
				$it['sides'] ? 'reforço e ilhós: ' . implode( '/', array_map( function ( $k ) use ( $sides_pt ) { return $sides_pt[ $k ]; }, array_keys( $it['sides'] ) ) ) : '',
				$it['lam'] ? 'laminação' : '',
				'm2' === $unit ? 'corte ' . $it['cut'] : '',
				$it['finish'],
			)
		);
		$dims    = rtrim( rtrim( number_format( $it['w'], 1, ',', '' ), '0' ), ',' ) . '×' . rtrim( rtrim( number_format( $it['h'], 1, ',', '' ), '0' ), ',' ) . ' cm';
		$saved[] = array(
			'kind'     => 'impressao',
			'name'     => $c->name . ( 'm2' === $unit ? ' · ' . $dims : '' ),
			'desc'     => ucfirst( implode( ' · ', $extras ) ) . ( $it['obs'] ? ( $extras ? ' · ' : '' ) . $it['obs'] : '' ),
			'qty'      => $it['qty'],
			'unit'     => null !== $it['override'] ? round( (float) $it['override'] / max( 1, $it['qty'] ), 2 ) : ( 'm2' === $unit ? round( ap_row_price( $c, $tier ) * $it['w'] * $it['h'] / 10000, 2 ) : ap_row_price( $c, $tier ) ),
			'cost'     => round( (float) $c->cost_material * ( 'm2' === $unit ? $it['w'] * $it['h'] / 10000 * $it['qty'] : $it['qty'] ), 2 ),
			'material' => $it['material'],
			'w'        => $it['w'],
			'h'        => $it['h'],
			'area'     => 'm2' === $unit ? round( $it['w'] * $it['h'] / 10000 * $it['qty'], 4 ) : 0,
			'sides'    => $it['sides'],
			'lam'      => $it['lam'],
			'cut'      => $it['cut'],
			'files'    => $it['files'],
			'override' => $it['override'],
		);
	}
	$mode    = in_array( ap_in( 'pagamento' ), array( 'pago', 'sinal', 'retirada', 'pendente' ), true ) ? ap_in( 'pagamento' ) : 'pendente';
	$cols    = array_keys( ap_columns() );
	$urgent  = ap_in( 'urgente', 'bool' );
	$canal   = ap_in( 'canal' ) ? ap_in( 'canal' ) : 'Balcão';
	$value   = round( $price['total'] - $d['discount'], 2 );
	$due     = ap_in( 'prazo', 'date' ) ? ap_in( 'prazo', 'date' ) : ap_next_business_day( ap_today(), max( 1, (int) ap_setting( 'prazo_dias' ) ) );
	$lines   = array( 'Canal: ' . $canal . ' · tabela: ' . ap_tiers()[ $tier ] );
	foreach ( $price['charges'] as $ch ) {
		$lines[] = $ch['label'] . ': ' . ap_money( $ch['value'] ) . ( ! empty( $ch['min'] ) ? ' (mínimo)' : '' );
	}
	if ( $d['discount'] > 0 ) {
		$lines[] = 'Desconto' . ( $d['coupon'] ? ' (cupom ' . $d['coupon']->code . ')' : '' ) . ': -' . ap_money( $d['discount'] );
	}
	if ( $d['credit_used'] > 0 ) {
		$lines[] = 'Crédito usado: -' . ap_money( $d['credit_used'] );
	}
	foreach ( $price['notes'] as $n ) {
		$lines[] = $n;
	}
	if ( ap_in( 'obs' ) ) {
		$lines[] = 'Obs.: ' . ap_in( 'obs', 'textarea' );
	}
	$title = ap_in( 'titulo' ) ? ap_in( 'titulo' ) : ( ap_client_label( $client ) . ' · ' . $cat[ $items[0]['material'] ]->name . ( count( $items ) > 1 ? ' +' . ( count( $items ) - 1 ) : '' ) );
	$pid   = ap_insert(
		'projects',
		array(
			'client_id'     => $client->id,
			'title'         => mb_substr( $title, 0, 190 ),
			'status'        => 'pendente' === $mode ? $cols[0] : ( $cols[1] ?? $cols[0] ),
			'value'         => $value,
			'items'         => wp_json_encode( $saved ),
			'notes'         => implode( "\n", $lines ),
			'delivery_mode' => 'retirada',
			'urgent'        => $urgent ? 1 : 0,
			'start_date'    => ap_today(),
			'due_date'      => $due,
			'art_status'    => 'pendente',
			'coupon_code'   => $d['coupon'] ? $d['coupon']->code : '',
			'discount'      => $d['discount'],
			'credit_used'   => $d['credit_used'],
		)
	);
	// Financeiro
	$desc   = 'Pedido #' . $pid;
	$method = ap_in( 'metodo' ) ? ap_in( 'metodo' ) : 'Pix';
	$paid_on = ap_in( 'data_pagamento', 'date' ) ? ap_in( 'data_pagamento', 'date' ) : ap_today();
	$tx     = function ( $amount, $status, $m, $date, $due_date ) use ( $pid, $client, $desc ) {
		return ap_insert( 'transactions', array( 'type' => 'in', 'project_id' => $pid, 'client_id' => $client->id, 'category' => 'Pedido', 'description' => $desc, 'amount' => round( $amount, 2 ), 'due_date' => $due_date, 'paid_at' => 'pago' === $status ? $date : null, 'method' => $m, 'status' => $status ) );
	};
	if ( $d['credit_used'] > 0 ) {
		$tx( $d['credit_used'], 'pago', 'Crédito na loja', ap_today(), ap_today() );
	}
	$rest = $d['total'];
	$pay_tx = 0;
	if ( $rest > 0 ) {
		if ( 'pago' === $mode ) {
			$tx( $rest, 'pago', $method, $paid_on, $paid_on );
		} elseif ( 'sinal' === $mode ) {
			$sinal = ap_in( 'sinal', 'money' );
			$sinal = $sinal > 0 && $sinal < $rest ? $sinal : round( $rest / 2, 2 );
			$tx( $sinal, 'pago', $method, $paid_on, $paid_on );
			$tx( $rest - $sinal, 'pendente', 'Na retirada', null, $due );
		} elseif ( 'retirada' === $mode ) {
			$tx( $rest, 'pendente', 'Na retirada', null, $due );
		} else {
			$pay_tx = $tx( $rest, 'pendente', 'Pix', null, ap_today() );
		}
	}
	ap_log( $pid, 'Pedido criado pela equipe (' . $canal . '): ' . ap_money( $value ) . ( $d['discount'] > 0 ? ' após desconto de ' . ap_money( $d['discount'] ) : '' ) . ' · pagamento: ' . $mode . ( $urgent ? ' · URGENTE' : '' ) . '.', true );
	ap_discounts_commit( $d, $pid, $client->id );
	$has_files = false;
	foreach ( $saved as $sv ) {
		$has_files = $has_files || ! empty( $sv['files'] );
	}
	if ( $has_files ) {
		ap_drive_label_order( $pid, $client, $saved );
	}
	if ( ap_in( 'avisar', 'bool' ) && is_email( $client->email ) && function_exists( 'ap_email_welcome' ) ) {
		ap_email_welcome( $pid );
	}
	do_action( 'ap_order_created', $pid );
	$msg = 'Pedido #' . $pid . ' criado para ' . ap_client_label( $client ) . '.';
	if ( $pay_tx ) {
		$msg .= ' Link de pagamento na ficha do pedido.';
	}
	ap_back( $msg, 'ok', ap_panel_url( 'pedido', $pid ) );
}

/** Dados dos clientes para a tela (tabela, saldo de crédito, telefone). */
function ap_manual_clients_js() {
	$out = array();
	foreach ( ap_clients() as $c ) {
		$out[ $c->id ] = array(
			'label'  => ap_client_label( $c ),
			'kind'   => (string) $c->kind,
			'tier'   => ap_client_tier( $c ),
			'credit' => ap_credit_balance( $c->id ),
			'phone'  => $c->whatsapp ? $c->whatsapp : $c->phone,
			'later'  => (int) $c->pay_later,
			'orders' => 0,
		);
	}
	return $out;
}

/** Tipo do cliente (define a tabela de preço): Terceirizado, Empresa, Cliente P/F ou Uso interno. */
function ap_do_client_kind() {
	ap_require( 'clientes' );
	$c = ap_get( 'clients', ap_in( 'id', 'int' ) );
	if ( $c ) {
		$k = in_array( ap_in( 'kind' ), array( 'Terceirizado', 'Empresa', 'Cliente P/F', 'Uso interno', '' ), true ) ? ap_in( 'kind' ) : '';
		ap_update( 'clients', $c->id, array( 'kind' => $k, 'ext_id' => $c->ext_id ) );
	}
	ap_back( 'Tipo de cliente salvo.' );
}

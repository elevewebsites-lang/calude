<?php
/**
 * Veículos da empresa: cadastro e todos os custos (combustível, IPVA, seguro, manutenção…).
 *
 * Cada custo é um lançamento de saída no Financeiro (categoria "Veículos"), então entra sozinho nos totais,
 * no fluxo de caixa e na aba "Custos" da planilha do Google. IPVA e seguro podem ser parcelados.
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

const AP_VEHICLE_CAT = 'Veículos';

function ap_vehicle_types() {
	return array(
		'Combustível'              => 'Combustível (gasolina, etanol, diesel)',
		'IPVA'                     => 'IPVA',
		'Licenciamento'            => 'Licenciamento / DPVAT',
		'Seguro'                   => 'Seguro',
		'Manutenção'               => 'Manutenção e revisão',
		'Pneus'                    => 'Pneus e alinhamento',
		'Multa'                    => 'Multa',
		'Lavagem'                  => 'Lavagem',
		'Pedágio/Estacionamento'   => 'Pedágio e estacionamento',
		'Documentação'             => 'Documentação',
		'Outros'                   => 'Outros',
	);
}

function ap_vehicles( $only_active = true ) {
	return ap_rows( 'vehicles', $only_active ? 'active = 1' : '1=1', array(), 'name' );
}

function ap_vehicle_label( $v ) {
	return $v->name . ( $v->plate ? ' · ' . $v->plate : '' );
}

function ap_do_vehicle_save() {
	ap_require( 'financeiro' );
	$id   = ap_in( 'id', 'int' );
	$data = array(
		'name'     => ap_in( 'name' ),
		'plate'    => strtoupper( preg_replace( '/[^A-Za-z0-9-]/', '', (string) ap_in( 'plate' ) ) ),
		'model'    => ap_in( 'model' ),
		'year'     => ap_in( 'year' ),
		'fuel'     => ap_in( 'fuel' ) ? ap_in( 'fuel' ) : 'Gasolina',
		'odometer' => ap_in( 'odometer', 'int' ),
		'notes'    => ap_in( 'notes' ),
	);
	if ( ! $data['name'] ) {
		ap_back( 'Dê um nome ao veículo (ex.: Fiorino).', 'erro' );
	}
	if ( $id ) {
		ap_update( 'vehicles', $id, $data );
		ap_back( 'Veículo salvo.' );
	}
	$id = ap_insert( 'vehicles', $data + array( 'active' => 1 ) );
	ap_back( 'Veículo cadastrado. Agora lance os custos dele.', 'ok', ap_panel_url( 'veiculos', 0, array( 'v' => $id ) ) );
}

function ap_do_vehicle_archive() {
	ap_require( 'financeiro' );
	$v = ap_get( 'vehicles', ap_in( 'id', 'int' ) );
	if ( $v ) {
		ap_update( 'vehicles', $v->id, array( 'active' => $v->active ? 0 : 1 ) );
	}
	ap_back( 'Veículo atualizado.' );
}

/**
 * Lança um custo (ou várias parcelas, mês a mês) no financeiro.
 */
function ap_do_vehicle_cost() {
	ap_require( 'financeiro' );
	$v = ap_get( 'vehicles', ap_in( 'vehicle_id', 'int' ) );
	if ( ! $v ) {
		ap_back( 'Escolha o veículo.', 'erro' );
	}
	$types  = ap_vehicle_types();
	$type   = isset( $types[ ap_in( 'vtype' ) ] ) ? ap_in( 'vtype' ) : 'Outros';
	$amount = ap_in( 'amount', 'money' );
	$liters = (float) str_replace( ',', '.', (string) ap_in( 'liters' ) );
	$km     = ap_in( 'odometer', 'int' );
	if ( $amount <= 0 ) {
		ap_back( 'Informe o valor.', 'erro' );
	}
	$date   = ap_in( 'date', 'date' ) ? ap_in( 'date', 'date' ) : ap_today();
	$n      = max( 1, min( 12, ap_in( 'parcelas', 'int' ) ) );
	$paid   = (bool) ap_in( 'paid', 'bool' );
	$method = ap_in( 'method' );
	$each   = round( $amount / $n, 2 );
	$label  = ap_vehicle_label( $v );
	$extra  = ap_in( 'note' ) ? ' · ' . ap_in( 'note' ) : '';
	for ( $i = 0; $i < $n; $i++ ) {
		$due  = gmdate( 'Y-m-d', strtotime( $date . ' +' . $i . ' month' ) );
		$last = $i === $n - 1 ? round( $amount - $each * ( $n - 1 ), 2 ) : $each;
		$ok   = 0 === $i ? $paid : false; // só a primeira parcela pode já estar paga
		ap_insert(
			'transactions',
			array(
				'type'        => 'out',
				'category'    => AP_VEHICLE_CAT,
				'description' => $type . ( $n > 1 ? ' ' . ( $i + 1 ) . '/' . $n : '' ) . ' · ' . $label . $extra,
				'amount'      => $last,
				'due_date'    => $due,
				'paid_at'     => $ok ? $date : null,
				'method'      => $method,
				'status'      => $ok ? 'pago' : 'pendente',
				'vehicle_id'  => (int) $v->id,
				'vtype'       => $type,
				'odometer'    => 0 === $i ? $km : 0,
				'liters'      => 0 === $i ? round( $liters, 2 ) : 0,
			)
		);
	}
	if ( $km > (int) $v->odometer ) {
		ap_update( 'vehicles', $v->id, array( 'odometer' => $km ) );
	}
	ap_back( 'Custo lançado no financeiro: ' . $type . ' · ' . ap_money( $amount ) . ( $n > 1 ? ' em ' . $n . ' parcelas' : '' ) . '.', 'ok', ap_panel_url( 'veiculos', 0, array( 'v' => $v->id ) ) );
}

function ap_do_vehicle_cost_delete() {
	ap_require( 'financeiro' );
	$t = ap_get( 'transactions', ap_in( 'id', 'int' ) );
	if ( $t && (int) $t->vehicle_id ) {
		ap_delete( 'transactions', $t->id );
		if ( function_exists( 'ap_sheet_touch_costs' ) ) {
			ap_sheet_touch_costs();
		}
	}
	ap_back( 'Custo removido do financeiro.' );
}

/**
 * Números de um veículo: gasto no mês, no ano, total, consumo (km/l) e custo por km.
 */
function ap_vehicle_stats( $vehicle_id ) {
	global $wpdb;
	$t    = ap_table( 'transactions' );
	$base = $wpdb->prepare( "FROM $t WHERE vehicle_id = %d AND type = 'out'", $vehicle_id ); // phpcs:ignore WordPress.DB.PreparedSQL
	$m    = gmdate( 'Y-m', strtotime( ap_today() ) );
	$y    = gmdate( 'Y', strtotime( ap_today() ) );
	$sum  = function ( $extra = '' ) use ( $wpdb, $base ) {
		return (float) $wpdb->get_var( "SELECT SUM(amount) $base $extra" ); // phpcs:ignore WordPress.DB.PreparedSQL
	};
	$by   = function ( $like ) use ( $wpdb, $sum ) {
		return $sum( $wpdb->prepare( 'AND COALESCE(paid_at, due_date) LIKE %s', $like . '%' ) ); // phpcs:ignore WordPress.DB.PreparedSQL
	};
	$fills = $wpdb->get_results( "SELECT odometer, liters, amount $base AND vtype = 'Combustível' AND odometer > 0 AND liters > 0 ORDER BY odometer" ); // phpcs:ignore WordPress.DB.PreparedSQL
	$kml   = 0;
	if ( count( $fills ) >= 2 ) {
		$km = (int) $fills[ count( $fills ) - 1 ]->odometer - (int) $fills[0]->odometer;
		$l  = 0;
		foreach ( array_slice( $fills, 1 ) as $f ) {
			$l += (float) $f->liters;
		}
		$kml = $l > 0 && $km > 0 ? round( $km / $l, 1 ) : 0;
	}
	$span  = (int) $wpdb->get_var( "SELECT MAX(odometer) - MIN(NULLIF(odometer,0)) $base" ); // phpcs:ignore WordPress.DB.PreparedSQL
	$total = $sum();
	return array(
		'month'  => $by( $m ),
		'year'   => $by( $y ),
		'total'  => $total,
		'open'   => $sum( "AND status = 'pendente'" ),
		'kml'    => $kml,
		'per_km' => $span > 0 ? round( $total / $span, 2 ) : 0,
	);
}

<?php
/**
 * Contas recorrentes (receitas e saídas que se repetem: hospedagem, ferramentas, aluguel…).
 *
 * Cada conta recorrente gera sozinha o lançamento de cada vencimento (status "pendente"),
 * com até 35 dias de antecedência, para aparecer no mês atual e no próximo.
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function ap_recur_cycles() {
	return array( 'mensal' => 'Todo mês', 'trimestral' => 'A cada 3 meses', 'semestral' => 'A cada 6 meses', 'anual' => 'Todo ano' );
}

function ap_recur_step( $date, $cycle ) {
	$months = array( 'mensal' => 1, 'trimestral' => 3, 'semestral' => 6, 'anual' => 12 );
	$m      = isset( $months[ $cycle ] ) ? $months[ $cycle ] : 1;
	$d      = new DateTime( $date );
	$day    = (int) $d->format( 'd' );
	$d->modify( 'first day of +' . $m . ' month' );
	// Dia 31 em mês de 30 dias vira o último dia do mês.
	$d->setDate( (int) $d->format( 'Y' ), (int) $d->format( 'm' ), min( $day, (int) $d->format( 't' ) ) );
	return $d->format( 'Y-m-d' );
}

/**
 * Gera os lançamentos que vencem até a data-limite (padrão: hoje + 35 dias).
 */
function ap_recurring_generate( $only_id = 0 ) {
	$horizon = gmdate( 'Y-m-d', strtotime( ap_today() . ' +35 day' ) );
	$where   = 'active = 1 AND next_date IS NOT NULL AND next_date <= %s';
	$args    = array( $horizon );
	if ( $only_id ) {
		$where .= ' AND id = %d';
		$args[] = (int) $only_id;
	}
	foreach ( ap_rows( 'recurring', $where, $args ) as $r ) {
		$next  = $r->next_date;
		$guard = 0;
		while ( $next && $next <= $horizon && $guard++ < 24 ) {
			if ( $r->until_date && $next > $r->until_date ) {
				ap_update( 'recurring', $r->id, array( 'active' => 0 ) );
				break;
			}
			if ( ! ap_rows( 'transactions', 'recur_id = %d AND due_date = %s', array( $r->id, $next ) ) ) {
				ap_insert(
					'transactions',
					array(
						'type'        => $r->type,
						'project_id'  => $r->project_id,
						'client_id'   => $r->client_id,
						'category'    => $r->category,
						'description' => $r->description,
						'amount'      => $r->amount,
						'due_date'    => $next,
						'method'      => $r->method,
						'status'      => 'pendente',
						'recur_id'    => $r->id,
					)
				);
			}
			$next = ap_recur_step( $next, $r->cycle );
			ap_update( 'recurring', $r->id, array( 'next_date' => $next ) );
		}
	}
}
add_action( 'ap_daily', 'ap_recurring_generate' );

/**
 * Criado a partir do "Novo lançamento" com a chave "Recorrente" ligada.
 * O primeiro lançamento é o do formulário (já pode estar pago); os seguintes nascem sozinhos.
 */
function ap_recurring_create_from_form( $data, $first_id ) {
	$cycle = ap_in( 'cycle' );
	$cycle = array_key_exists( $cycle, ap_recur_cycles() ) ? $cycle : 'mensal';
	$start = $data['due_date'] ? $data['due_date'] : ap_today();
	$id    = ap_insert(
		'recurring',
		array(
			'type'        => $data['type'],
			'description' => $data['description'],
			'category'    => $data['category'],
			'amount'      => $data['amount'],
			'method'      => $data['method'],
			'cycle'       => $cycle,
			'next_date'   => ap_recur_step( $start, $cycle ),
			'until_date'  => ap_in( 'until_date', 'date' ) ? ap_in( 'until_date', 'date' ) : null,
			'project_id'  => $data['project_id'],
			'client_id'   => $data['client_id'],
			'active'      => 1,
		)
	);
	if ( $first_id ) {
		ap_update( 'transactions', $first_id, array( 'recur_id' => $id ) );
	}
	ap_recurring_generate( $id );
	return $id;
}

function ap_do_recurring_toggle() {
	ap_require( 'financeiro' );
	$r = ap_get( 'recurring', ap_in( 'id', 'int' ) );
	if ( $r ) {
		$on   = ! (int) $r->active;
		$next = $r->next_date;
		// Ao retomar, a próxima data não pode ficar no passado.
		while ( $on && $next && $next < ap_today() ) {
			$next = ap_recur_step( $next, $r->cycle );
		}
		ap_update( 'recurring', $r->id, array( 'active' => $on ? 1 : 0, 'next_date' => $next ) );
		if ( $on ) {
			ap_recurring_generate( $r->id );
		}
		ap_back( $on ? 'Conta recorrente retomada.' : 'Conta recorrente pausada. Os lançamentos já criados continuam no financeiro.' );
	}
	ap_back();
}

function ap_do_recurring_save() {
	ap_require( 'financeiro' );
	$r = ap_get( 'recurring', ap_in( 'id', 'int' ) );
	if ( ! $r ) {
		ap_back( 'Conta não encontrada.', 'erro' );
	}
	$cycle = array_key_exists( ap_in( 'cycle' ), ap_recur_cycles() ) ? ap_in( 'cycle' ) : $r->cycle;
	$data  = array(
		'description' => ap_in( 'description' ) ? ap_in( 'description' ) : $r->description,
		'amount'      => ap_in( 'amount', 'money' ) ? ap_in( 'amount', 'money' ) : $r->amount,
		'category'    => ap_in( 'category' ),
		'method'      => ap_in( 'method' ),
		'cycle'       => $cycle,
		'next_date'   => ap_in( 'next_date', 'date' ) ? ap_in( 'next_date', 'date' ) : $r->next_date,
		'until_date'  => ap_in( 'until_date', 'date' ) ? ap_in( 'until_date', 'date' ) : null,
	);
	ap_update( 'recurring', $r->id, $data );
	// Lançamentos futuros ainda pendentes seguem o novo valor e a nova descrição.
	global $wpdb;
	$wpdb->query( $wpdb->prepare( 'UPDATE ' . ap_table( 'transactions' ) . " SET amount = %f, description = %s, category = %s, method = %s WHERE recur_id = %d AND status = 'pendente' AND due_date >= %s", $data['amount'], $data['description'], $data['category'], $data['method'], $r->id, ap_today() ) ); // phpcs:ignore WordPress.DB.PreparedSQL
	ap_recurring_generate( $r->id );
	ap_back( 'Conta recorrente atualizada.' );
}

function ap_do_recurring_delete() {
	ap_require( 'financeiro' );
	$r = ap_get( 'recurring', ap_in( 'id', 'int' ) );
	if ( $r ) {
		global $wpdb;
		// Apaga só os lançamentos futuros ainda pendentes; o histórico fica.
		$wpdb->query( $wpdb->prepare( 'DELETE FROM ' . ap_table( 'transactions' ) . " WHERE recur_id = %d AND status = 'pendente' AND due_date >= %s", $r->id, ap_today() ) ); // phpcs:ignore WordPress.DB.PreparedSQL
		ap_delete( 'recurring', $r->id );
	}
	ap_back( 'Conta recorrente excluída. O histórico de lançamentos foi mantido.' );
}

/**
 * Total mensal das recorrentes ativas (para o card do financeiro).
 */
function ap_recurring_monthly( $type ) {
	$months = array( 'mensal' => 1, 'trimestral' => 3, 'semestral' => 6, 'anual' => 12 );
	$total  = 0;
	foreach ( ap_rows( 'recurring', 'active = 1 AND type = %s', array( $type ) ) as $r ) {
		$total += (float) $r->amount / ( isset( $months[ $r->cycle ] ) ? $months[ $r->cycle ] : 1 );
	}
	return $total;
}

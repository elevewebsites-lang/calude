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

function lk_recur_cycles() {
	return array( 'mensal' => 'Todo mês', 'trimestral' => 'A cada 3 meses', 'semestral' => 'A cada 6 meses', 'anual' => 'Todo ano' );
}

function lk_recur_step( $date, $cycle ) {
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
function lk_recurring_generate( $only_id = 0 ) {
	$horizon = gmdate( 'Y-m-d', strtotime( lk_today() . ' +35 day' ) );
	$where   = 'active = 1 AND next_date IS NOT NULL AND next_date <= %s';
	$args    = array( $horizon );
	if ( $only_id ) {
		$where .= ' AND id = %d';
		$args[] = (int) $only_id;
	}
	foreach ( lk_rows( 'recurring', $where, $args ) as $r ) {
		$next  = $r->next_date;
		$guard = 0;
		while ( $next && $next <= $horizon && $guard++ < 24 ) {
			if ( $r->until_date && $next > $r->until_date ) {
				lk_update( 'recurring', $r->id, array( 'active' => 0 ) );
				break;
			}
			if ( ! lk_rows( 'transactions', 'recur_id = %d AND due_date = %s', array( $r->id, $next ) ) ) {
				lk_insert(
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
			$next = lk_recur_step( $next, $r->cycle );
			lk_update( 'recurring', $r->id, array( 'next_date' => $next ) );
		}
	}
}
add_action( 'lk_daily', 'lk_recurring_generate' );

/**
 * Criado a partir do "Novo lançamento" com a chave "Recorrente" ligada.
 * O primeiro lançamento é o do formulário (já pode estar pago); os seguintes nascem sozinhos.
 */
function lk_recurring_create_from_form( $data, $first_id ) {
	$cycle = lk_in( 'cycle' );
	$cycle = array_key_exists( $cycle, lk_recur_cycles() ) ? $cycle : 'mensal';
	$start = $data['due_date'] ? $data['due_date'] : lk_today();
	$id    = lk_insert(
		'recurring',
		array(
			'type'        => $data['type'],
			'description' => $data['description'],
			'category'    => $data['category'],
			'amount'      => $data['amount'],
			'method'      => $data['method'],
			'cycle'       => $cycle,
			'next_date'   => lk_recur_step( $start, $cycle ),
			'until_date'  => lk_in( 'until_date', 'date' ) ? lk_in( 'until_date', 'date' ) : null,
			'project_id'  => $data['project_id'],
			'client_id'   => $data['client_id'],
			'active'      => 1,
		)
	);
	if ( $first_id ) {
		lk_update( 'transactions', $first_id, array( 'recur_id' => $id ) );
	}
	lk_recurring_generate( $id );
	return $id;
}

function lk_do_recurring_toggle() {
	lk_require( 'financeiro' );
	$r = lk_get( 'recurring', lk_in( 'id', 'int' ) );
	if ( $r ) {
		$on   = ! (int) $r->active;
		$next = $r->next_date;
		// Ao retomar, a próxima data não pode ficar no passado.
		while ( $on && $next && $next < lk_today() ) {
			$next = lk_recur_step( $next, $r->cycle );
		}
		lk_update( 'recurring', $r->id, array( 'active' => $on ? 1 : 0, 'next_date' => $next ) );
		if ( $on ) {
			lk_recurring_generate( $r->id );
		}
		lk_back( $on ? 'Conta recorrente retomada.' : 'Conta recorrente pausada. Os lançamentos já criados continuam no financeiro.' );
	}
	lk_back();
}

function lk_do_recurring_save() {
	lk_require( 'financeiro' );
	$r = lk_get( 'recurring', lk_in( 'id', 'int' ) );
	if ( ! $r ) {
		lk_back( 'Conta não encontrada.', 'erro' );
	}
	$cycle = array_key_exists( lk_in( 'cycle' ), lk_recur_cycles() ) ? lk_in( 'cycle' ) : $r->cycle;
	$data  = array(
		'description' => lk_in( 'description' ) ? lk_in( 'description' ) : $r->description,
		'amount'      => lk_in( 'amount', 'money' ) ? lk_in( 'amount', 'money' ) : $r->amount,
		'category'    => lk_in( 'category' ),
		'method'      => lk_in( 'method' ),
		'cycle'       => $cycle,
		'next_date'   => lk_in( 'next_date', 'date' ) ? lk_in( 'next_date', 'date' ) : $r->next_date,
		'until_date'  => lk_in( 'until_date', 'date' ) ? lk_in( 'until_date', 'date' ) : null,
	);
	lk_update( 'recurring', $r->id, $data );
	// Lançamentos futuros ainda pendentes seguem o novo valor e a nova descrição.
	global $wpdb;
	$wpdb->query( $wpdb->prepare( 'UPDATE ' . lk_table( 'transactions' ) . " SET amount = %f, description = %s, category = %s, method = %s WHERE recur_id = %d AND status = 'pendente' AND due_date >= %s", $data['amount'], $data['description'], $data['category'], $data['method'], $r->id, lk_today() ) ); // phpcs:ignore WordPress.DB.PreparedSQL
	lk_recurring_generate( $r->id );
	lk_back( 'Conta recorrente atualizada.' );
}

function lk_do_recurring_delete() {
	lk_require( 'financeiro' );
	$r = lk_get( 'recurring', lk_in( 'id', 'int' ) );
	if ( $r ) {
		global $wpdb;
		// Apaga só os lançamentos futuros ainda pendentes; o histórico fica.
		$wpdb->query( $wpdb->prepare( 'DELETE FROM ' . lk_table( 'transactions' ) . " WHERE recur_id = %d AND status = 'pendente' AND due_date >= %s", $r->id, lk_today() ) ); // phpcs:ignore WordPress.DB.PreparedSQL
		lk_delete( 'recurring', $r->id );
	}
	lk_back( 'Conta recorrente excluída. O histórico de lançamentos foi mantido.' );
}

/**
 * Total mensal das recorrentes ativas (para o card do financeiro).
 */
function lk_recurring_monthly( $type ) {
	$months = array( 'mensal' => 1, 'trimestral' => 3, 'semestral' => 6, 'anual' => 12 );
	$total  = 0;
	foreach ( lk_rows( 'recurring', 'active = 1 AND type = %s', array( $type ) ) as $r ) {
		$total += (float) $r->amount / ( isset( $months[ $r->cycle ] ) ? $months[ $r->cycle ] : 1 );
	}
	return $total;
}

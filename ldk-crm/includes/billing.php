<?php
/**
 * Mensalidades dos clientes: cada um tem valor e dia de vencimento.
 * Todo dia o sistema gera a cobrança do mês X dias antes do vencimento (Configurações → Pagamento).
 * Pendentes: "Cobrar" manda o e-mail na hora e abre o WhatsApp com a mensagem + link de pagamento.
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function lk_next_due( $client, $from = '' ) {
	$from = $from ? $from : lk_today();
	$day  = max( 1, min( 28, (int) $client->due_day ) );
	$ym   = substr( $from, 0, 7 );
	$date = $ym . '-' . str_pad( (string) $day, 2, '0', STR_PAD_LEFT );
	return $date < $from ? gmdate( 'Y-m', strtotime( $ym . '-01 +1 month' ) ) . '-' . str_pad( (string) $day, 2, '0', STR_PAD_LEFT ) : $date;
}

add_action( 'lk_daily', 'lk_billing_generate', 20 );
function lk_billing_generate() {
	$ahead = max( 0, (int) lk_setting( 'assinatura_antecedencia' ) );
	foreach ( lk_rows( 'clients', 'billing_active = 1 AND monthly_fee > 0' ) as $c ) {
		$due = lk_next_due( $c );
		if ( lk_days_until( $due ) > $ahead ) {
			continue;
		}
		$key = 'lk_bill_' . $c->id . '_' . $due;
		if ( get_option( $key ) ) {
			continue;
		}
		update_option( $key, 1, false );
		lk_insert(
			'transactions',
			array(
				'type'        => 'in',
				'client_id'   => $c->id,
				'category'    => 'Mensalidade',
				'description' => 'Mensalidade ' . lk_month_label( substr( $due, 0, 7 ) ) . ' · ' . lk_client_label( $c ),
				'amount'      => $c->monthly_fee,
				'due_date'    => $due,
				'status'      => 'pendente',
			)
		);
	}
}

function lk_do_billing_charge() {
	lk_require( 'financeiro' );
	$t = lk_get( 'transactions', lk_in( 'id', 'int' ) );
	$c = $t && $t->client_id ? lk_get( 'clients', $t->client_id ) : null;
	if ( ! $t || ! $c ) {
		lk_back();
	}
	$days   = lk_days_until( $t->due_date );
	$moment = null === $days || $days > 0 ? 'antes' : ( 0 === $days ? 'hoje' : 'depois' );
	$ok     = is_email( $c->email ) ? lk_send_payment_email( $t, $c, $moment ) : false;
	update_option( 'lk_charged_' . $t->id, lk_now(), false );
	set_transient( 'lk_last_charge_' . get_current_user_id(), array( 'wa' => lk_payment_wa_link( $t ), 'who' => lk_client_label( $c ) ), 600 );
	lk_back( ( $ok ? 'E-mail de cobrança enviado.' : 'Cliente sem e-mail.' ) . ' Clique em "Mandar no WhatsApp" para enviar a mensagem com o link.' );
}

function lk_do_billing_client() {
	lk_require( 'financeiro' );
	lk_update(
		'clients',
		lk_in( 'client_id', 'int' ),
		array(
			'monthly_fee'    => lk_in( 'monthly_fee', 'money' ),
			'due_day'        => max( 1, min( 28, lk_in( 'due_day', 'int' ) ) ),
			'billing_active' => lk_in( 'billing_active', 'bool' ),
		)
	);
	lk_billing_generate();
	lk_back( 'Mensalidade salva.' );
}

/**
 * Pendentes (vencidas + a vencer em 10 dias) para o financeiro e o dashboard.
 */
function lk_billing_pending() {
	return lk_rows( 'transactions', "type = 'in' AND status = 'pendente' AND due_date IS NOT NULL AND due_date <= %s", array( gmdate( 'Y-m-d', strtotime( lk_today() . ' +10 day' ) ) ), 'due_date' );
}

function lk_billing_mrr() {
	global $wpdb;
	return (float) $wpdb->get_var( 'SELECT SUM(monthly_fee) FROM ' . lk_table( 'clients' ) . ' WHERE billing_active = 1' ); // phpcs:ignore WordPress.DB.PreparedSQL
}

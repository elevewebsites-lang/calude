<?php
/**
 * Cupons de desconto e crédito na loja.
 *
 * - Cupom: código com desconto em % ou em R$, com mínimo, validade, limite de usos e (opcional) um cliente só.
 * - Crédito: saldo por cliente (livro-caixa: entradas positivas, usos negativos). Serve para o presente de boas-vindas,
 *   estorno, bonificação etc. O saldo é usado no pedido (cliente ou manual) e abate do total.
 * Ordem no pedido: subtotal → cupom → crédito → total a pagar.
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/* -----------------------------------------------------------------------
 * Crédito
 * -------------------------------------------------------------------- */

function ap_credit_balance( $client_id ) {
	global $wpdb;
	if ( ! $client_id ) {
		return 0.0;
	}
	$v = $wpdb->get_var( $wpdb->prepare( 'SELECT SUM(amount) FROM ' . ap_table( 'credits' ) . ' WHERE client_id = %d AND (expires_at IS NULL OR expires_at >= %s OR amount < 0)', $client_id, ap_today() ) ); // phpcs:ignore WordPress.DB.PreparedSQL
	return max( 0.0, round( (float) $v, 2 ) );
}

function ap_credit_add( $client_id, $amount, $kind = 'ajuste', $note = '', $project_id = 0, $expires = null, $coupon_id = 0 ) {
	if ( ! $client_id || 0 === round( (float) $amount, 2 ) ) {
		return 0;
	}
	return ap_insert(
		'credits',
		array(
			'client_id'  => (int) $client_id,
			'amount'     => round( (float) $amount, 2 ),
			'kind'       => $kind,
			'note'       => mb_substr( (string) $note, 0, 250 ),
			'project_id' => (int) $project_id,
			'coupon_id'  => (int) $coupon_id,
			'expires_at' => $expires ? $expires : null,
			'user_id'    => get_current_user_id(),
		)
	);
}

/** Presente de boas-vindas: uma vez por cliente, quando o cadastro é aprovado. */
function ap_credit_welcome( $client_id ) {
	global $wpdb;
	$v = ap_num_setting( 'credito_boas_vindas' );
	if ( $v <= 0 || ! $client_id ) {
		return;
	}
	if ( (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM ' . ap_table( 'credits' ) . " WHERE client_id = %d AND kind = 'boas-vindas'", $client_id ) ) ) { // phpcs:ignore WordPress.DB.PreparedSQL
		return;
	}
	$days = (int) ap_setting( 'credito_validade_dias' );
	ap_credit_add( $client_id, $v, 'boas-vindas', 'Boas-vindas: crédito para o primeiro pedido feito pelo sistema', 0, $days > 0 ? gmdate( 'Y-m-d', strtotime( '+' . $days . ' days' ) ) : null );
}

/* -----------------------------------------------------------------------
 * Cupom
 * -------------------------------------------------------------------- */

function ap_coupon_find( $code ) {
	global $wpdb;
	$code = strtoupper( trim( (string) $code ) );
	if ( '' === $code ) {
		return null;
	}
	return $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . ap_table( 'coupons' ) . ' WHERE code = %s', $code ) ); // phpcs:ignore WordPress.DB.PreparedSQL
}

/** Confere o cupom para um cliente e um subtotal. Devolve [ok, mensagem, desconto]. */
function ap_coupon_check( $coupon, $client_id, $subtotal ) {
	global $wpdb;
	if ( ! $coupon || ! $coupon->active ) {
		return array( false, 'Cupom não encontrado ou desligado.', 0.0 );
	}
	$today = ap_today();
	if ( $coupon->starts_at && $coupon->starts_at > $today ) {
		return array( false, 'Este cupom ainda não começou.', 0.0 );
	}
	if ( $coupon->expires_at && $coupon->expires_at < $today ) {
		return array( false, 'Este cupom venceu.', 0.0 );
	}
	if ( $coupon->max_uses && (int) $coupon->uses >= (int) $coupon->max_uses ) {
		return array( false, 'Este cupom já atingiu o limite de usos.', 0.0 );
	}
	if ( $coupon->client_id && (int) $coupon->client_id !== (int) $client_id ) {
		return array( false, 'Este cupom é de outro cliente.', 0.0 );
	}
	if ( $coupon->per_client && $client_id ) {
		$used = (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM ' . ap_table( 'projects' ) . ' WHERE client_id = %d AND coupon_code = %s', $client_id, $coupon->code ) ); // phpcs:ignore WordPress.DB.PreparedSQL
		if ( $used >= (int) $coupon->per_client ) {
			return array( false, 'Você já usou este cupom.', 0.0 );
		}
	}
	if ( $coupon->min_total && $subtotal < (float) $coupon->min_total ) {
		return array( false, 'Este cupom vale a partir de ' . ap_money( $coupon->min_total ) . ' em pedidos.', 0.0 );
	}
	$d = 'percent' === $coupon->kind ? round( $subtotal * (float) $coupon->value / 100, 2 ) : min( (float) $coupon->value, $subtotal );
	return array( true, 'Cupom aplicado: -' . ap_money( $d ) . '.', max( 0.0, $d ) );
}

/**
 * Aplica cupom e crédito a um subtotal. NÃO grava nada (só calcula).
 * Devolve [subtotal, coupon (obj|null), coupon_msg, discount, credit_used, total].
 */
function ap_discounts( $client_id, $subtotal, $code = '', $use_credit = false, $manual_discount = 0.0 ) {
	$out = array(
		'subtotal'    => round( (float) $subtotal, 2 ),
		'coupon'      => null,
		'coupon_msg'  => '',
		'discount'    => 0.0,
		'credit_used' => 0.0,
		'total'       => round( (float) $subtotal, 2 ),
	);
	if ( $code ) {
		$c = ap_coupon_find( $code );
		list( $ok, $msg, $d ) = ap_coupon_check( $c, $client_id, $out['subtotal'] );
		$out['coupon_msg'] = $msg;
		if ( $ok ) {
			$out['coupon']   = $c;
			$out['discount'] = $d;
		}
	}
	if ( $manual_discount > 0 ) {
		$out['discount'] += min( $manual_discount, $out['subtotal'] - $out['discount'] );
	}
	$after = max( 0.0, $out['subtotal'] - $out['discount'] );
	if ( $use_credit && $client_id ) {
		$out['credit_used'] = min( ap_credit_balance( $client_id ), $after );
	}
	$out['total'] = round( max( 0.0, $after - $out['credit_used'] ), 2 );
	return $out;
}

/** Depois de gravar o pedido: soma o uso do cupom e lança o crédito usado. */
function ap_discounts_commit( $d, $project_id, $client_id ) {
	global $wpdb;
	if ( $d['coupon'] ) {
		$wpdb->query( $wpdb->prepare( 'UPDATE ' . ap_table( 'coupons' ) . ' SET uses = uses + 1 WHERE id = %d', $d['coupon']->id ) ); // phpcs:ignore WordPress.DB.PreparedSQL
	}
	if ( $d['credit_used'] > 0 ) {
		ap_credit_add( $client_id, -1 * $d['credit_used'], 'uso', 'Usado no pedido #' . (int) $project_id, $project_id );
	}
}

/* -----------------------------------------------------------------------
 * REST: o cliente confere o cupom na hora (sem recarregar)
 * -------------------------------------------------------------------- */

add_action(
	'rest_api_init',
	function () {
		register_rest_route(
			'ap/v1',
			'/coupon',
			array(
				'methods'             => 'POST',
				'permission_callback' => function () {
					return is_user_logged_in() && ( ap_is_team() || ap_current_client() );
				},
				'callback'            => function ( WP_REST_Request $r ) {
					$client_id = ap_is_team() ? absint( $r['client_id'] ) : (int) ap_current_client()->id;
					$d         = ap_discounts( $client_id, (float) $r['subtotal'], (string) $r['code'], ! empty( $r['use_credit'] ), (float) $r['manual_discount'] );
					return array(
						'ok'          => (bool) $d['coupon'] || '' === (string) $r['code'],
						'message'     => $d['coupon_msg'],
						'discount'    => $d['discount'],
						'credit_used' => $d['credit_used'],
						'credit'      => ap_credit_balance( $client_id ),
						'total'       => $d['total'],
					);
				},
			)
		);
	}
);

/* -----------------------------------------------------------------------
 * Painel: cupons e crédito
 * -------------------------------------------------------------------- */

function ap_do_coupon_save() {
	ap_require( 'clientes' );
	$id   = ap_in( 'id', 'int' );
	$code = strtoupper( preg_replace( '/[^A-Za-z0-9_-]/', '', (string) ap_in( 'code' ) ) );
	if ( ! $code ) {
		ap_back( 'Dê um código ao cupom (letras e números).', 'erro' );
	}
	$dup = ap_coupon_find( $code );
	if ( $dup && (int) $dup->id !== $id ) {
		ap_back( 'Já existe um cupom com esse código.', 'erro' );
	}
	$data = array(
		'code'       => $code,
		'label'      => ap_in( 'label' ),
		'kind'       => 'fixed' === ap_in( 'kind' ) ? 'fixed' : 'percent',
		'value'      => ap_in( 'value', 'money' ),
		'min_total'  => ap_in( 'min_total', 'money' ),
		'client_id'  => ap_in( 'client_id', 'int' ),
		'max_uses'   => ap_in( 'max_uses', 'int' ),
		'per_client' => max( 0, ap_in( 'per_client', 'int' ) ),
		'starts_at'  => ap_in( 'starts_at', 'date' ) ? ap_in( 'starts_at', 'date' ) : null,
		'expires_at' => ap_in( 'expires_at', 'date' ) ? ap_in( 'expires_at', 'date' ) : null,
		'active'     => ap_in( 'active', 'bool' ) ? 1 : 0,
	);
	if ( $data['value'] <= 0 ) {
		ap_back( 'Informe o valor do desconto.', 'erro' );
	}
	if ( 'percent' === $data['kind'] && $data['value'] > 100 ) {
		ap_back( 'Desconto em % não pode passar de 100.', 'erro' );
	}
	if ( $id ) {
		ap_update( 'coupons', $id, $data );
	} else {
		ap_insert( 'coupons', $data );
	}
	ap_back( 'Cupom salvo.' );
}

function ap_do_coupon_delete() {
	ap_require( 'clientes' );
	ap_delete( 'coupons', ap_in( 'id', 'int' ) );
	ap_back( 'Cupom excluído.' );
}

function ap_do_credit_add() {
	ap_require( 'clientes' );
	$cid    = ap_in( 'client_id', 'int' );
	$amount = ap_in( 'amount', 'money' );
	if ( ! $cid || 0.0 === (float) $amount ) {
		ap_back( 'Informe o cliente e o valor.', 'erro' );
	}
	if ( 'tirar' === ap_in( 'op' ) ) {
		$amount = -1 * abs( $amount );
	}
	$days = ap_in( 'validade_dias', 'int' );
	ap_credit_add( $cid, $amount, $amount > 0 ? 'ajuste' : 'retirada', ap_in( 'note' ) ? ap_in( 'note' ) : 'Ajuste manual', 0, $amount > 0 && $days > 0 ? gmdate( 'Y-m-d', strtotime( '+' . $days . ' days' ) ) : null );
	ap_back( 'Crédito atualizado: saldo ' . ap_money( ap_credit_balance( $cid ) ) . '.' );
}

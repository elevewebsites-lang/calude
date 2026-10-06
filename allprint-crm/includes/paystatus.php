<?php
/**
 * Situação de pagamento do pedido, tags coloridas (etapa e pagamento) e registro do pagamento na entrega.
 *
 * - ap_order_payment(): lê os lançamentos do pedido e diz se está pago, com sinal, a pagar na retirada ou aguardando.
 * - Tags: cada etapa tem uma cor (aguardando pagamento, revisão, produção, acabamento, pronto, entregue) e o pagamento também.
 *   As cores saem de ap_tone_palette() e passam pelo teste de contraste (4,5:1) nos temas claro e escuro.
 * - Ao mover para a última etapa com saldo em aberto, o painel pergunta se foi pago e como (Pix, crédito, débito, dinheiro).
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/* -----------------------------------------------------------------------
 * Cores das tags
 * -------------------------------------------------------------------- */

/** Paleta de tons (tema claro). O escuro é calculado a partir do ponto de cor. */
function ap_tone_palette() {
	return array(
		'pay'     => array( 'bg' => '#fff4cc', 'fg' => '#6b4a00', 'bd' => '#f2d675', 'dot' => '#d99a00' ),
		'rev'     => array( 'bg' => '#ede7ff', 'fg' => '#432a94', 'bd' => '#cdbff5', 'dot' => '#7c5ce0' ),
		'prod'    => array( 'bg' => '#ddebff', 'fg' => '#12408f', 'bd' => '#b4d2fa', 'dot' => '#2f7bea' ),
		'fin'     => array( 'bg' => '#d5f5f0', 'fg' => '#0b5a52', 'bd' => '#a6e3da', 'dot' => '#16a596' ),
		'ready'   => array( 'bg' => '#dcf5e3', 'fg' => '#14532d', 'bd' => '#aee3bd', 'dot' => '#22a24f' ),
		'done'    => array( 'bg' => '#e7e9ee', 'fg' => '#323844', 'bd' => '#cbd0d9', 'dot' => '#7b8494' ),
		'art'     => array( 'bg' => '#ffe3f0', 'fg' => '#8a1456', 'bd' => '#f6b7d6', 'dot' => '#d6338a' ),
		'pickup'  => array( 'bg' => '#ffe8d1', 'fg' => '#8a3a00', 'bd' => '#f7c48f', 'dot' => '#e8710a' ),
		'wait'    => array( 'bg' => '#ffe0e0', 'fg' => '#8f1d1d', 'bd' => '#f5b1b1', 'dot' => '#d93636' ),
		'x'       => array( 'bg' => '#efeff2', 'fg' => '#3a3a44', 'bd' => '#d5d5dc', 'dot' => '#8a8a99' ),
	);
}

/** Mesma paleta para o tema escuro (fundo escuro tingido, texto claro com contraste). */
function ap_tone_palette_dark() {
	$out = array();
	foreach ( ap_tone_palette() as $k => $t ) {
		$bg        = ap_mix( $t['dot'], '#171717', 0.22 );
		$out[ $k ] = array(
			'bg'  => $bg,
			'fg'  => ap_ensure_contrast( ap_mix( $t['dot'], '#ffffff', 0.62 ), $bg, 4.5, false ),
			'bd'  => ap_mix( $t['dot'], '#171717', 0.5 ),
			'dot' => $t['dot'],
		);
	}
	return $out;
}

function ap_tones_css() {
	$css = '';
	foreach ( ap_tone_palette() as $k => $t ) {
		$css .= '.tone-' . $k . '{--tn-bg:' . $t['bg'] . ';--tn-fg:' . $t['fg'] . ';--tn-bd:' . $t['bd'] . ';--tn-dot:' . $t['dot'] . ';}';
	}
	foreach ( ap_tone_palette_dark() as $k => $t ) {
		$css .= '[data-theme="dark"] .tone-' . $k . '{--tn-bg:' . $t['bg'] . ';--tn-fg:' . $t['fg'] . ';--tn-bd:' . $t['bd'] . ';--tn-dot:' . $t['dot'] . ';}';
	}
	return $css;
}

/** Tom da etapa pelo nome (slug). Etapas criadas pelo usuário ficam neutras. */
function ap_stage_tone( $slug ) {
	$s = (string) $slug;
	if ( false !== strpos( $s, 'pagamento' ) ) {
		return 'pay';
	}
	if ( false !== strpos( $s, 'arte' ) ) {
		return 'art';
	}
	if ( false !== strpos( $s, 'revis' ) ) {
		return 'rev';
	}
	if ( false !== strpos( $s, 'produ' ) || false !== strpos( $s, 'fila' ) ) {
		return 'prod';
	}
	if ( false !== strpos( $s, 'acabament' ) ) {
		return 'fin';
	}
	if ( false !== strpos( $s, 'pront' ) ) {
		return 'ready';
	}
	if ( false !== strpos( $s, 'entreg' ) ) {
		return 'done';
	}
	return 'x';
}

function ap_stage_badge( $slug, $label = '' ) {
	$cols  = ap_columns();
	$label = $label ? $label : ( $cols[ $slug ] ?? $slug );
	return '<span class="tag tone-' . esc_attr( ap_stage_tone( $slug ) ) . '"><i></i>' . esc_html( $label ) . '</span>';
}

/* -----------------------------------------------------------------------
 * Situação do pagamento
 * -------------------------------------------------------------------- */

function ap_order_payment( $p ) {
	static $cache = array();
	$id = is_object( $p ) ? (int) $p->id : (int) $p;
	if ( isset( $cache[ $id ] ) ) {
		return $cache[ $id ];
	}
	global $wpdb;
	$rows    = $wpdb->get_results( $wpdb->prepare( 'SELECT amount, status, method FROM ' . ap_table( 'transactions' ) . " WHERE project_id = %d AND type = 'in'", $id ) ); // phpcs:ignore WordPress.DB.PreparedSQL
	$paid    = 0.0;
	$cash    = 0.0; // pago de verdade (o crédito na loja não conta como sinal)
	$due     = 0.0;
	$pickup  = false;
	foreach ( $rows as $r ) {
		if ( 'pago' === $r->status ) {
			$paid += (float) $r->amount;
			if ( 'Crédito na loja' !== $r->method ) {
				$cash += (float) $r->amount;
			}
		} else {
			$due += (float) $r->amount;
			if ( 'Na retirada' === $r->method ) {
				$pickup = true;
			}
		}
	}
	$paid = round( $paid, 2 );
	$due  = round( $due, 2 );
	if ( ! $rows ) {
		$st = array( 'state' => 'none', 'tone' => 'x', 'label' => 'Sem cobrança', 'paid' => 0.0, 'due' => 0.0 );
	} elseif ( $due <= 0 ) {
		$st = array( 'state' => 'paid', 'tone' => 'ready', 'label' => 'Pago', 'paid' => $paid, 'due' => 0.0 );
	} elseif ( $cash > 0 ) {
		$st = array( 'state' => 'partial', 'tone' => 'pay', 'label' => 'Sinal pago · falta ' . ap_money( $due ), 'paid' => $paid, 'due' => $due );
	} elseif ( $pickup ) {
		$st = array( 'state' => 'pickup', 'tone' => 'pickup', 'label' => 'Paga na retirada · ' . ap_money( $due ), 'paid' => $paid, 'due' => $due );
	} else {
		$st = array( 'state' => 'wait', 'tone' => 'wait', 'label' => 'Aguardando pagamento', 'paid' => $paid, 'due' => $due );
	}
	$cache[ $id ] = $st;
	return $st;
}

function ap_pay_badge( $p ) {
	$s = ap_order_payment( $p );
	if ( 'none' === $s['state'] ) {
		return '';
	}
	return '<span class="tag tag--pay tone-' . esc_attr( $s['tone'] ) . '" title="' . esc_attr( $s['label'] ) . '"><i></i>' . esc_html( $s['label'] ) . '</span>';
}

function ap_pay_methods() {
	return array( 'Pix' => 'Pix', 'Cartão de crédito' => 'Cartão de crédito', 'Cartão de débito' => 'Cartão de débito', 'Dinheiro' => 'Dinheiro', 'Transferência' => 'Transferência' );
}

/**
 * Marca como pagos os lançamentos em aberto do pedido (no valor total ou parcial) com a forma de pagamento.
 * Devolve o valor recebido.
 */
function ap_order_register_payment( $project_id, $method, $date = '', $amount = 0.0 ) {
	global $wpdb;
	$method = isset( ap_pay_methods()[ $method ] ) ? $method : 'Dinheiro';
	$date   = $date ? $date : ap_today();
	$p      = ap_get( 'projects', $project_id );
	if ( ! $p ) {
		return 0.0;
	}
	$rows   = $wpdb->get_results( $wpdb->prepare( 'SELECT * FROM ' . ap_table( 'transactions' ) . " WHERE project_id = %d AND type = 'in' AND status <> 'pago' ORDER BY id", $project_id ) ); // phpcs:ignore WordPress.DB.PreparedSQL
	$left   = $amount > 0 ? (float) $amount : 1e12;
	$got    = 0.0;
	foreach ( $rows as $r ) {
		if ( $left <= 0 ) {
			break;
		}
		$a = (float) $r->amount;
		if ( $a <= $left + 0.001 ) {
			ap_update( 'transactions', $r->id, array( 'status' => 'pago', 'paid_at' => $date, 'method' => $method ) );
			$got  += $a;
			$left -= $a;
		} else {
			// Pagamento parcial: divide o lançamento.
			ap_update( 'transactions', $r->id, array( 'amount' => round( $a - $left, 2 ) ) );
			ap_insert( 'transactions', array( 'type' => 'in', 'project_id' => $p->id, 'client_id' => $p->client_id, 'category' => $r->category, 'description' => $r->description, 'amount' => round( $left, 2 ), 'due_date' => $r->due_date, 'paid_at' => $date, 'method' => $method, 'status' => 'pago' ) );
			$got += $left;
			$left = 0;
		}
	}
	if ( $got > 0 ) {
		ap_log( $p->id, 'Pagamento recebido: ' . ap_money( $got ) . ' (' . $method . ').', false );
		do_action( 'ap_payment_registered', $p->id, $got, $method );
	}
	return round( $got, 2 );
}

/** Botão "Registrar pagamento" da ficha do pedido. */
function ap_do_order_pay() {
	ap_require( 'projetos' );
	$p = ap_get( 'projects', ap_in( 'id', 'int' ) );
	if ( ! $p ) {
		ap_back();
	}
	$got = ap_order_register_payment( $p->id, ap_in( 'method' ), ap_in( 'date', 'date' ), ap_in( 'amount', 'money' ) );
	ap_back( $got > 0 ? 'Pagamento de ' . ap_money( $got ) . ' registrado.' : 'Não havia valor em aberto neste pedido.', $got > 0 ? 'ok' : 'erro' );
}

/**
 * Antes de entregar: se ainda há saldo, a equipe precisa dizer se foi pago (e como) ou entregar assim mesmo.
 * Devolve null (pode seguir) ou uma mensagem de erro.
 */
function ap_delivery_payment_gate( $p, $to_status, $method, $unpaid ) {
	if ( $to_status !== ap_last_column() || $p->status === $to_status ) {
		return null;
	}
	$pay = ap_order_payment( $p );
	if ( $pay['due'] <= 0 ) {
		return null;
	}
	if ( $method ) {
		ap_order_register_payment( $p->id, $method );
		return null;
	}
	if ( $unpaid ) {
		ap_log( $p->id, 'Entregue com saldo em aberto: ' . ap_money( $pay['due'] ) . '.', false );
		return null;
	}
	return 'Este pedido tem ' . ap_money( $pay['due'] ) . ' em aberto. Informe se foi pago e a forma de pagamento antes de entregar.';
}

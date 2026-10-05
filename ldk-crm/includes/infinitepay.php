<?php
/**
 * Pagamentos pela InfinitePay (checkout: Pix ou cartão em até 12x).
 *
 *   /pagar/<id>/<hash>   gera o link de pagamento da parcela e redireciona
 *   /pagamento/<id>      volta do checkout: confere o pagamento e agradece
 *   /wp-json/lk/v1/infinitepay   aviso automático (webhook) de pagamento
 *
 * A InfinitePay não assina o webhook, então todo aviso é conferido na API
 * (payment_check) antes de dar baixa.
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function lk_ip_handle() {
	return ltrim( trim( (string) lk_setting( 'infinitepay' ) ), '$@' );
}

/**
 * Link público de pagamento de uma parcela (pode ser enviado ao cliente).
 */
function lk_pay_url( $trans_id ) {
	return lk_url( 'pagar/' . (int) $trans_id . '/' . lk_pay_hash( $trans_id ) );
}

function lk_pay_hash( $trans_id ) {
	return substr( wp_hash( 'lk-pay-' . (int) $trans_id ), 0, 16 );
}

function lk_ip_post( $paths, $payload ) {
	$headers = array( 'Content-Type' => 'application/json', 'Accept' => 'application/json' );
	$token   = trim( (string) lk_setting( 'infinitepay_token' ) );
	if ( $token ) {
		$headers['Authorization'] = 'Bearer ' . $token;
	}
	$last = null;
	foreach ( (array) $paths as $url ) {
		$res = wp_remote_post( $url, array( 'headers' => $headers, 'body' => wp_json_encode( $payload ), 'timeout' => 25 ) );
		if ( is_wp_error( $res ) ) {
			$last = $res;
			continue;
		}
		$code = wp_remote_retrieve_response_code( $res );
		$body = json_decode( wp_remote_retrieve_body( $res ), true );
		if ( $code >= 200 && $code < 300 && is_array( $body ) ) {
			return $body;
		}
		$last = new WP_Error( 'lk_ip', 'InfinitePay respondeu ' . $code . ': ' . wp_strip_all_tags( wp_remote_retrieve_body( $res ) ) );
	}
	return $last ? $last : new WP_Error( 'lk_ip', 'Sem resposta da InfinitePay.' );
}

/**
 * Cria o link de pagamento no checkout da InfinitePay.
 */
function lk_ip_create_link( $trans ) {
	$handle = lk_ip_handle();
	if ( ! $handle ) {
		return new WP_Error( 'lk_ip', 'Configure a InfiniteTag em Configurações.' );
	}
	$client  = $trans->client_id ? lk_get( 'clients', $trans->client_id ) : null;
	$payload = array(
		'handle'       => $handle,
		'order_nsu'    => 'lk-' . $trans->id,
		'redirect_url' => lk_payment_return_url( $trans->id ),
		'webhook_url'  => rest_url( 'lk/v1/infinitepay' ),
		'items'        => array(
			array(
				'quantity'    => 1,
				'price'       => (int) round( $trans->amount * 100 ),
				'description' => mb_substr( $trans->description ? $trans->description : 'Projeto ' . lk_setting( 'empresa' ), 0, 120 ),
			),
		),
	);
	if ( $client ) {
		$customer = array_filter(
			array(
				'name'  => $client->name ? $client->name : lk_client_label( $client ),
				'email' => $client->email,
			)
		);
		$phone = preg_replace( '/\D+/', '', $client->whatsapp ? $client->whatsapp : $client->phone );
		if ( strlen( $phone ) >= 10 ) {
			$customer['phone_number'] = '+55' . substr( $phone, -11 );
		}
		if ( $customer ) {
			$payload['customer'] = $customer;
		}
	}
	$body = lk_ip_post( array( 'https://api.checkout.infinitepay.io/links', 'https://api.infinitepay.io/invoices/public/checkout/links' ), $payload );
	if ( is_wp_error( $body ) ) {
		return $body;
	}
	$url = isset( $body['url'] ) ? $body['url'] : ( isset( $body['payment_url'] ) ? $body['payment_url'] : '' );
	if ( ! $url ) {
		return new WP_Error( 'lk_ip', 'A InfinitePay não devolveu o link de pagamento.' );
	}
	lk_update( 'transactions', $trans->id, array( 'pay_url' => esc_url_raw( $url ), 'external_id' => 'lk-' . $trans->id ) );
	return $url;
}

/**
 * Confere na InfinitePay se o pagamento foi feito.
 */
function lk_ip_check( $order_nsu, $transaction_nsu, $slug ) {
	if ( ! $transaction_nsu || ! $slug ) {
		return false;
	}
	$body = lk_ip_post(
		array( 'https://api.checkout.infinitepay.io/payment_check', 'https://api.infinitepay.io/invoices/public/checkout/payment_check' ),
		array(
			'handle'          => lk_ip_handle(),
			'order_nsu'       => $order_nsu,
			'transaction_nsu' => $transaction_nsu,
			'slug'            => $slug,
		)
	);
	return ! is_wp_error( $body ) && ! empty( $body['paid'] ) ? $body : false;
}

/**
 * Pagamento confirmado E com o valor certo.
 * Sem isso, alguém poderia criar um link próprio de R$ 0,01 com o mesmo número de pedido,
 * pagar e usar o comprovante para dar baixa numa parcela maior.
 */
function lk_ip_verified( $trans, $transaction_nsu, $slug ) {
	$check = lk_ip_check( 'lk-' . $trans->id, $transaction_nsu, $slug );
	if ( ! $check ) {
		return false;
	}
	$expected = (int) round( $trans->amount * 100 );
	$has      = isset( $check['paid_amount'] ) || isset( $check['amount'] );
	$paid     = isset( $check['paid_amount'] ) ? (int) $check['paid_amount'] : ( isset( $check['amount'] ) ? (int) $check['amount'] : 0 );
	if ( $paid + 1 < $expected ) {
		// Evita repetir a tarefa se a InfinitePay reenviar o aviso.
		if ( get_transient( 'lk_ip_warn_' . $trans->id . '_' . md5( $transaction_nsu ) ) ) {
			return false;
		}
		set_transient( 'lk_ip_warn_' . $trans->id . '_' . md5( $transaction_nsu ), 1, WEEK_IN_SECONDS );
		if ( function_exists( 'lk_sec_log' ) ) {
			lk_sec_log( 'pagamento suspeito', 'Parcela #' . $trans->id . ': esperado ' . $expected . ', informado ' . $paid . ' (centavos)' );
		}
		lk_insert(
			'tasks',
			array(
				'project_id'  => (int) $trans->project_id,
				'grp'         => 'Financeiro',
				'title'       => 'Conferir pagamento na InfinitePay · ' . $trans->description,
				'description' => ( $has ? 'A InfinitePay confirmou um pagamento de ' . lk_money( $paid / 100 ) . ' para uma parcela de ' . lk_money( $trans->amount ) . '.' : 'A InfinitePay confirmou um pagamento, mas não informou o valor.' ) . ' A baixa NÃO foi dada automaticamente. Confira no app da InfinitePay (transação ' . sanitize_text_field( $transaction_nsu ) . ') e, se estiver certo, dê a baixa manual no Financeiro.',
				'due_date'    => lk_today(),
				'priority'    => 'urgente',
				'assignee'    => lk_owner_id(),
				'created_by'  => 0,
			)
		);
		return false;
	}
	return $check;
}

/**
 * Assinatura do endereço de volta do pagamento (sem ela, a página não mostra valores).
 */
function lk_payment_return_url( $trans_id ) {
	return lk_url( 'pagamento/' . (int) $trans_id . '/' . lk_pay_hash( $trans_id ) );
}

/**
 * Dá baixa na parcela (uma vez só).
 */
function lk_mark_paid( $trans, $capture_method = '', $receipt = '' ) {
	if ( 'pago' === $trans->status ) {
		return false;
	}
	$methods = array( 'pix' => 'Pix', 'credit_card' => 'Cartão de crédito' );
	lk_update(
		'transactions',
		$trans->id,
		array(
			'status'  => 'pago',
			'paid_at' => lk_today(),
			'method'  => isset( $methods[ $capture_method ] ) ? $methods[ $capture_method ] : ( $capture_method ? $capture_method : 'InfinitePay' ),
			'pay_url' => $receipt ? esc_url_raw( $receipt ) : $trans->pay_url,
		)
	);
	do_action( 'lk_payment_received', $trans->id );
	if ( $trans->project_id ) {
		lk_log( $trans->project_id, 'Pagamento recebido: ' . lk_money( $trans->amount ) . ' (' . ( isset( $methods[ $capture_method ] ) ? $methods[ $capture_method ] : ( $capture_method ? $capture_method : 'InfinitePay' ) ) . '). Obrigado!', true );
		lk_insert(
			'tasks',
			array(
				'project_id' => (int) $trans->project_id,
				'grp'        => 'Financeiro',
				'title'      => 'Pagamento recebido: ' . lk_money( $trans->amount ) . ' · ' . $trans->description,
				'priority'   => 'normal',
				'status'     => 'done',
				'done_at'    => lk_now(),
			)
		);
	}
	return true;
}

/* -----------------------------------------------------------------------
 * Rotas
 * -------------------------------------------------------------------- */

function lk_route_pay( $id, $hash ) {
	$trans = lk_get( 'transactions', $id );
	if ( ! $trans || ! hash_equals( lk_pay_hash( $id ), (string) $hash ) ) {
		lk_render( 'public/indisponivel' );
	}
	if ( 'pago' === $trans->status ) {
		wp_safe_redirect( lk_payment_return_url( $trans->id ) );
		exit;
	}
	$url = lk_ip_create_link( $trans );
	if ( is_wp_error( $url ) ) {
		lk_pay_failure_alert( $trans, $url->get_error_message() );
		lk_render( 'public/pagamento', array( 'trans' => $trans, 'error' => $url->get_error_message() ) );
	}
	wp_redirect( $url ); // phpcs:ignore WordPress.Security.SafeRedirect -- domínio da InfinitePay.
	exit;
}

/**
 * O checkout não abriu: avisa o dono com o motivo (uma vez por parcela a cada 6 horas).
 */
function lk_pay_failure_alert( $trans, $reason ) {
	$key = 'lk_payfail_' . (int) $trans->id;
	if ( get_transient( $key ) ) {
		return;
	}
	set_transient( $key, 1, 6 * HOUR_IN_SECONDS );
	$client = $trans->client_id ? lk_get( 'clients', $trans->client_id ) : null;
	$hint   = (float) $trans->amount < 1 ? ' Provável causa: valor abaixo do mínimo da InfinitePay (' . lk_money( $trans->amount ) . ').' : '';
	lk_insert(
		'tasks',
		array(
			'project_id'  => (int) $trans->project_id,
			'grp'         => 'Financeiro',
			'title'       => 'O pagamento não abriu para ' . ( $client ? lk_client_label( $client ) : 'o cliente' ) . ': ' . lk_money( $trans->amount ),
			'description' => 'O cliente tentou pagar e a InfinitePay recusou o link.' . $hint . "\n\nResposta: " . $reason . "\n\nConfira a InfiniteTag em Configurações e mande o link de novo: " . lk_pay_url( $trans->id ),
			'priority'    => 'urgente',
			'due_date'    => lk_today(),
			'assignee'    => lk_owner_id(),
		)
	);
	if ( $trans->project_id ) {
		lk_log( (int) $trans->project_id, 'O link de pagamento não abriu (' . lk_money( $trans->amount ) . '): ' . $reason );
	}
}

function lk_route_payment_return( $id, $hash = '' ) {
	$trans = lk_get( 'transactions', $id );
	if ( ! $trans ) {
		lk_render( 'public/indisponivel' );
	}
	// Links antigos (sem assinatura) só funcionam para quem é dono ou da equipe.
	$signed = $hash && hash_equals( lk_pay_hash( $id ), (string) $hash );
	if ( ! $signed && ! ( is_user_logged_in() && ( lk_can( 'financeiro' ) || ( lk_current_client() && (int) lk_current_client()->id === (int) $trans->client_id ) ) ) ) {
		lk_render( 'public/indisponivel' );
	}
	// phpcs:disable WordPress.Security.NonceVerification.Recommended -- parâmetros de retorno do checkout, conferidos na API.
	$tx      = isset( $_GET['transaction_nsu'] ) ? sanitize_text_field( wp_unslash( $_GET['transaction_nsu'] ) ) : '';
	$slug    = isset( $_GET['slug'] ) ? sanitize_text_field( wp_unslash( $_GET['slug'] ) ) : '';
	$method  = isset( $_GET['capture_method'] ) ? sanitize_key( $_GET['capture_method'] ) : '';
	$receipt = isset( $_GET['receipt_url'] ) ? esc_url_raw( wp_unslash( $_GET['receipt_url'] ) ) : '';
	// phpcs:enable
	if ( 'pago' !== $trans->status && $tx && lk_ip_verified( $trans, $tx, $slug ) ) {
		lk_mark_paid( $trans, $method, $receipt );
		$trans = lk_get( 'transactions', $id );
	}
	lk_render( 'public/pagamento', array( 'trans' => $trans ) );
}

add_action(
	'rest_api_init',
	function () {
		register_rest_route(
			'lk/v1',
			'/infinitepay',
			array(
				'methods'             => 'POST',
				'permission_callback' => '__return_true',
				'callback'            => 'lk_ip_webhook',
			)
		);
	}
);

function lk_ip_webhook( WP_REST_Request $r ) {
	$data  = $r->get_json_params();
	$data  = is_array( $data ) ? $data : $r->get_params();
	$nsu   = isset( $data['order_nsu'] ) ? sanitize_text_field( $data['order_nsu'] ) : '';
	$id    = preg_match( '/^lk-(\d+)$/', $nsu, $m ) ? (int) $m[1] : 0;
	$trans = $id ? lk_get( 'transactions', $id ) : null;
	if ( ! $trans ) {
		return new WP_REST_Response( array( 'ok' => false ), 200 );
	}
	if ( 'pago' === $trans->status ) {
		return new WP_REST_Response( array( 'ok' => true ), 200 );
	}
	$tx   = isset( $data['transaction_nsu'] ) ? sanitize_text_field( $data['transaction_nsu'] ) : '';
	$slug = isset( $data['invoice_slug'] ) ? sanitize_text_field( $data['invoice_slug'] ) : ( isset( $data['slug'] ) ? sanitize_text_field( $data['slug'] ) : '' );
	if ( lk_ip_verified( $trans, $tx, $slug ) ) {
		lk_mark_paid( $trans, isset( $data['capture_method'] ) ? sanitize_key( $data['capture_method'] ) : '', isset( $data['receipt_url'] ) ? $data['receipt_url'] : '' );
		return new WP_REST_Response( array( 'ok' => true ), 200 );
	}
	// 400 faz a InfinitePay tentar de novo mais tarde.
	return new WP_REST_Response( array( 'ok' => false ), 400 );
}

/* -----------------------------------------------------------------------
 * Cobrança automática: lembretes por e-mail com o link de pagamento
 * -------------------------------------------------------------------- */

add_action( 'lk_daily', 'lk_payment_reminders', 30 );
function lk_payment_reminders() {
	if ( '1' !== (string) lk_setting( 'cobranca_auto' ) || ! lk_ip_handle() ) {
		return;
	}
	$n     = max( 1, (int) lk_setting( 'cobranca_dias' ) );
	$today = lk_today();
	$when  = array(
		gmdate( 'Y-m-d', strtotime( $today . ' +' . $n . ' day' ) ) => 'antes',
		$today                                                     => 'hoje',
		gmdate( 'Y-m-d', strtotime( $today . ' -' . $n . ' day' ) ) => 'depois',
	);
	foreach ( lk_rows( 'transactions', "type = 'in' AND status = 'pendente' AND due_date IN (%s, %s, %s)", array_keys( $when ) ) as $t ) {
		$client = $t->client_id ? lk_get( 'clients', $t->client_id ) : null;
		if ( ! $client || ! is_email( $client->email ) ) {
			continue;
		}
		$key = 'lk_remind_' . $t->id . '_' . $when[ $t->due_date ];
		if ( get_option( $key ) ) {
			continue;
		}
		update_option( $key, 1, false );
		lk_send_payment_email( $t, $client, $when[ $t->due_date ] );
	}
}

/**
 * Texto da cobrança (usado no e-mail e no WhatsApp).
 */
function lk_payment_message( $t, $client, $moment = 'antes' ) {
	$first = $client && $client->name ? strtok( $client->name, ' ' ) : '';
	$intro = array(
		'antes'  => 'Passando para lembrar que a mensalidade abaixo vence em ' . lk_date( $t->due_date ) . '.',
		'hoje'   => 'A mensalidade abaixo vence hoje.',
		'depois' => 'Ainda não identificamos o pagamento da mensalidade abaixo, que venceu em ' . lk_date( $t->due_date ) . '. Se já pagou, pode desconsiderar.',
	);
	return 'Olá' . ( $first ? ', ' . $first : '' ) . '! Tudo bem? ' . ( isset( $intro[ $moment ] ) ? $intro[ $moment ] : $intro['antes'] ) . "\n\n"
		. $t->description . "\n" . 'Valor: ' . lk_money( $t->amount ) . "\n\n"
		. 'Você pode pagar no Pix ou no cartão por aqui: ' . lk_pay_url( $t->id ) . "\n\n"
		. 'Qualquer dúvida, é só chamar. ' . lk_setting( 'empresa' );
}

function lk_send_payment_email( $t, $client, $moment ) {
	$subjects = array(
		'antes'  => 'Lembrete: mensalidade vence em ' . lk_date( $t->due_date ),
		'hoje'   => 'Sua mensalidade vence hoje',
		'depois' => 'Pagamento em aberto',
	);
	$sent = wp_mail( $client->email, $subjects[ $moment ] . ' · ' . lk_setting( 'empresa' ), lk_payment_message( $t, $client, $moment ) );
	if ( $sent && $t->project_id ) {
		lk_log( $t->project_id, 'Lembrete de pagamento enviado por e-mail (' . lk_money( $t->amount ) . ', ' . $moment . ' do vencimento).' );
	}
	return $sent;
}

/**
 * Link de WhatsApp com a cobrança pronta.
 */
function lk_payment_wa_link( $t ) {
	$client = $t->client_id ? lk_get( 'clients', $t->client_id ) : null;
	if ( ! $client || ! ( $client->whatsapp || $client->phone ) ) {
		return '';
	}
	$days   = lk_days_until( $t->due_date );
	$moment = null === $days || $days > 0 ? 'antes' : ( 0 === $days ? 'hoje' : 'depois' );
	return lk_wa_link( $client->whatsapp ? $client->whatsapp : $client->phone, lk_payment_message( $t, $client, $moment ) );
}

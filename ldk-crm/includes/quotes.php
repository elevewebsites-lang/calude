<?php
/**
 * Orçamentos: fotos + arquivo 3D + itens (peças impressas e extras), público (final / empresa / revendedor),
 * link público /orcamento/<token>/ com aceite, cadastro do cliente e pagamento na hora
 * (Pix com desconto ou cartão até N vezes). Aceitou → vira pedido no Kanban.
 *
 * Os insumos (sacola, cartão…) entram só no custo interno: o cliente nunca vê.
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function lk_quote_statuses() {
	return array(
		'rascunho' => 'Rascunho',
		'enviado'  => 'Enviado',
		'visto'    => 'Visualizado',
		'aceito'   => 'Aceito',
		'pago'     => 'Pago',
		'recusado' => 'Recusado',
	);
}

/* -----------------------------------------------------------------------
 * Rotas públicas
 * -------------------------------------------------------------------- */

add_action( 'init', 'lk_quote_rewrites' );
function lk_quote_rewrites() {
	add_rewrite_rule( '^orcamento/([A-Za-z0-9-]+)/?$', 'index.php?lk_route=quote&lk_token=$matches[1]', 'top' );
	add_rewrite_rule( '^pagar/([0-9]+)/([a-f0-9]+)/?$', 'index.php?lk_route=pay&lk_id=$matches[1]&lk_token=$matches[2]', 'top' );
	add_rewrite_rule( '^pagamento/([0-9]+)/?$', 'index.php?lk_route=payment&lk_id=$matches[1]', 'top' );
	add_rewrite_rule( '^pagamento/([0-9]+)/([a-f0-9]+)/?$', 'index.php?lk_route=payment&lk_id=$matches[1]&lk_token=$matches[2]', 'top' );
}

function lk_public_route( $route ) {
	if ( 'quote' === $route ) {
		$q = lk_quote_by_token( sanitize_text_field( get_query_var( 'lk_token' ) ) );
		if ( ! $q || ( 'rascunho' === $q->status && ! lk_is_team() ) ) {
			lk_render( 'public/indisponivel' );
		}
		lk_quote_count_view( $q );
		lk_handle_quote_accept( $q );
		lk_render( 'public/orcamento', array( 'q' => $q ) );
	}
	if ( 'pay' === $route ) {
		lk_route_pay( absint( get_query_var( 'lk_id' ) ), sanitize_key( get_query_var( 'lk_token' ) ) );
	}
	if ( 'payment' === $route ) {
		lk_route_payment_return( absint( get_query_var( 'lk_id' ) ), sanitize_key( get_query_var( 'lk_token' ) ) );
	}
}

function lk_quote_by_token( $token ) {
	if ( ! $token ) {
		return null;
	}
	$rows = lk_rows( 'quotes', 'token = %s', array( $token ) );
	return $rows ? $rows[0] : null;
}

function lk_quote_url( $q ) {
	return lk_url( 'orcamento/' . $q->token );
}

function lk_quote_count_view( $q ) {
	if ( is_user_logged_in() && lk_is_team() ) {
		return;
	}
	$ua = isset( $_SERVER['HTTP_USER_AGENT'] ) ? strtolower( sanitize_text_field( wp_unslash( $_SERVER['HTTP_USER_AGENT'] ) ) ) : '';
	if ( '' === $ua || preg_match( '/bot|crawl|spider|preview|facebookexternalhit|whatsapp|telegram|headless/', $ua ) ) {
		return;
	}
	$data = array( 'views' => (int) $q->views + 1, 'last_view' => lk_now() );
	if ( 'enviado' === $q->status ) {
		$data['status'] = 'visto';
	}
	lk_update( 'quotes', $q->id, $data );
}

/* -----------------------------------------------------------------------
 * Leitura
 * -------------------------------------------------------------------- */

function lk_json( $value ) {
	$d = $value ? json_decode( $value, true ) : array();
	return is_array( $d ) ? $d : array();
}

function lk_quote_items( $q ) {
	$out = array();
	foreach ( lk_json( $q->items ) as $it ) {
		$out[] = wp_parse_args(
			$it,
			array( 'kind' => 'impressao', 'name' => '', 'desc' => '', 'qty' => 1, 'unit' => 0, 'cost' => 0, 'calc' => 0, 'resale' => 0, 'grams' => 0, 'hours' => 0 )
		);
	}
	return $out;
}

/**
 * Valores para pagar: Pix/à vista com desconto e cartão (valor cheio).
 */
function lk_quote_amounts( $q, $delivery = '' ) {
	$freight = 'envio' === $delivery ? (float) $q->freight : 0;
	$base    = max( 0, (float) $q->subtotal - (float) $q->discount );
	$off     = lk_num_setting( 'desconto_pix' );
	return array(
		'freight' => round( $freight, 2 ),
		'cartao'  => round( $base + $freight, 2 ),
		'pix'     => round( $base * ( 1 - $off / 100 ) + $freight, 2 ),
		'off'     => $off,
	);
}

function lk_quote_pay_text() {
	$max = max( 1, (int) lk_setting( 'parcelas_max' ) );
	return $max > 1 ? 'Cartão em até ' . $max . 'x sem juros' : 'Cartão de crédito';
}

/* -----------------------------------------------------------------------
 * Painel: salvar
 * -------------------------------------------------------------------- */

function lk_do_quote_save() {
	lk_require( 'orcamentos' );
	$id  = lk_in( 'id', 'int' );
	$old = $id ? lk_get( 'quotes', $id ) : null;

	// Cliente: existente ou novo (com origem).
	$client_id = lk_in( 'client_id', 'int' );
	if ( ! $client_id && lk_in( 'new_name' ) ) {
		$client_id = lk_insert(
			'clients',
			array(
				'name'         => lk_in( 'new_name' ),
				'company'      => lk_in( 'new_company' ),
				'email'        => lk_in( 'new_email', 'email' ),
				'whatsapp'     => lk_in( 'new_whatsapp' ),
				'phone'        => lk_in( 'new_whatsapp' ),
				'source'       => lk_in( 'new_source' ),
				'status'       => 'convidado',
				'invite_token' => wp_generate_password( 32, false ),
			)
		);
	}

	// Itens.
	$in    = isset( $_POST['it'] ) ? (array) wp_unslash( $_POST['it'] ) : array(); // phpcs:ignore WordPress.Security.NonceVerification,WordPress.Security.ValidatedSanitizedInput
	$items = array();
	foreach ( (array) ( $in['name'] ?? array() ) as $i => $name ) {
		$name = sanitize_text_field( $name );
		if ( '' === $name ) {
			continue;
		}
		$qty     = max( 1, (int) ( $in['qty'][ $i ] ?? 1 ) );
		$items[] = array(
			'kind'   => 'extra' === ( $in['kind'][ $i ] ?? '' ) ? 'extra' : 'impressao',
			'name'   => $name,
			'desc'   => sanitize_textarea_field( $in['desc'][ $i ] ?? '' ),
			'qty'    => $qty,
			'unit'   => lk_parse_money( $in['unit'][ $i ] ?? 0 ),
			'cost'   => lk_parse_money( $in['cost'][ $i ] ?? 0 ),
			'resale' => lk_parse_money( $in['resale'][ $i ] ?? 0 ),
			'calc'   => absint( $in['calc'][ $i ] ?? 0 ),
			'grams'  => (float) str_replace( ',', '.', (string) ( $in['grams'][ $i ] ?? 0 ) ),
			'hours'  => (float) str_replace( ',', '.', (string) ( $in['hours'][ $i ] ?? 0 ) ),
		);
	}
	$subtotal = 0;
	$cost     = 0;
	foreach ( $items as $it ) {
		$subtotal += $it['unit'] * $it['qty'];
		$cost     += $it['cost'] * $it['qty'];
	}
	$discount = lk_in( 'discount', 'money' );
	$freight  = lk_in( 'freight', 'money' );

	// Fotos e arquivo 3D.
	$images = $old ? lk_json( $old->images ) : array();
	$remove = isset( $_POST['remove_img'] ) ? array_map( 'absint', (array) $_POST['remove_img'] ) : array(); // phpcs:ignore WordPress.Security.NonceVerification
	$images = array_values(
		array_filter(
			$images,
			function ( $img ) use ( $remove ) {
				return ! in_array( (int) ( $img['id'] ?? 0 ), $remove, true );
			}
		)
	);
	$title  = lk_in( 'title' ) ? lk_in( 'title' ) : ( $items ? $items[0]['name'] : 'Orçamento' );
	$client = $client_id ? lk_get( 'clients', $client_id ) : null;
	$folder = array( 'Orçamentos', ( $client ? lk_client_label( $client ) . ' - ' : '' ) . $title );
	$images = array_merge( $images, lk_store_uploads( 'photos', $folder, 'image' ) );
	$model  = $old ? lk_json( $old->model3d ) : array();
	if ( lk_in( 'remove_model', 'bool' ) ) {
		$model = array();
	}
	$up = lk_store_uploads( 'model3d', $folder, '3d' );
	if ( $up ) {
		$model = $up[0];
	}

	$valid = max( 1, (int) lk_setting( 'validade_dias' ) );
	$data  = array(
		'client_id'     => $client_id,
		'lead_id'       => lk_in( 'lead_id', 'int' ),
		'audience'      => isset( lk_audiences()[ lk_in( 'audience' ) ] ) ? lk_in( 'audience' ) : 'final',
		'title'         => $title,
		'intro'         => lk_in( 'intro', 'textarea' ),
		'items'         => wp_json_encode( $items ),
		'images'        => wp_json_encode( $images ),
		'model3d'       => wp_json_encode( $model ),
		'freight'       => $freight,
		'freight_label' => lk_in( 'freight_label' ),
		'discount'      => $discount,
		'subtotal'      => round( $subtotal, 2 ),
		'total'         => round( max( 0, $subtotal - $discount ), 2 ),
		'cost_total'    => round( $cost, 2 ),
		'deadline_days' => lk_in( 'deadline_days', 'int' ),
		'valid_until'   => lk_in( 'valid_until', 'date' ) ? lk_in( 'valid_until', 'date' ) : gmdate( 'Y-m-d', strtotime( lk_today() . ' +' . $valid . ' day' ) ),
	);
	if ( lk_in( 'publicar', 'bool' ) && ( ! $old || 'rascunho' === $old->status ) ) {
		$data['status'] = 'enviado';
	}
	if ( $old ) {
		lk_update( 'quotes', $old->id, $data );
		$id = $old->id;
	} else {
		$data['token']  = strtolower( wp_generate_password( 10, false ) ) . '-' . sanitize_title( mb_substr( $title, 0, 40 ) );
		$data['status'] = $data['status'] ?? 'rascunho';
		$id             = lk_insert( 'quotes', $data );
	}
	if ( $data['lead_id'] ) {
		lk_update( 'leads', $data['lead_id'], array( 'client_id' => $client_id, 'value' => $data['total'] ) );
	}
	lk_back( 'Orçamento salvo.', 'ok', lk_panel_url( 'orcamento', $id, array( 'pronto' => 1 ) ) );
}

function lk_do_quote_status() {
	lk_require( 'orcamentos' );
	$status = lk_in( 'status' );
	if ( isset( lk_quote_statuses()[ $status ] ) && ! in_array( $status, array( 'aceito', 'pago' ), true ) ) {
		lk_update( 'quotes', lk_in( 'id', 'int' ), array( 'status' => $status ) );
	}
	lk_back( 'Status atualizado.' );
}

function lk_do_quote_duplicate() {
	lk_require( 'orcamentos' );
	$q = lk_get( 'quotes', lk_in( 'id', 'int' ) );
	if ( ! $q ) {
		lk_back();
	}
	$data = (array) $q;
	unset( $data['id'] );
	$data['token']       = strtolower( wp_generate_password( 10, false ) ) . '-' . sanitize_title( mb_substr( $q->title, 0, 40 ) );
	$data['title']       = $q->title . ' (cópia)';
	$data['status']      = 'rascunho';
	$data['views']       = 0;
	$data['last_view']   = null;
	$data['accepted_at'] = null;
	$data['project_id']  = 0;
	$data['pay_option']  = '';
	$data['created_at']  = lk_now();
	$id                  = lk_insert( 'quotes', $data );
	lk_back( 'Orçamento duplicado.', 'ok', lk_panel_url( 'orcamento', $id ) );
}

function lk_do_quote_delete() {
	lk_require( 'orcamentos' );
	lk_delete( 'quotes', lk_in( 'id', 'int' ) );
	lk_back( 'Orçamento excluído.', 'ok', lk_panel_url( 'orcamentos' ) );
}

/**
 * Resumo do funil de orçamentos para o dashboard.
 */
function lk_quote_totals() {
	global $wpdb;
	$t   = lk_table( 'quotes' );
	$out = array( 'open' => 0, 'open_value' => 0, 'won' => 0, 'won_value' => 0 );
	foreach ( $wpdb->get_results( "SELECT status, COUNT(*) n, SUM(total) v FROM $t GROUP BY status" ) as $r ) { // phpcs:ignore WordPress.DB.PreparedSQL
		if ( in_array( $r->status, array( 'enviado', 'visto' ), true ) ) {
			$out['open']       += (int) $r->n;
			$out['open_value'] += (float) $r->v;
		}
		if ( in_array( $r->status, array( 'aceito', 'pago' ), true ) ) {
			$out['won']       += (int) $r->n;
			$out['won_value'] += (float) $r->v;
		}
	}
	return $out;
}

/* -----------------------------------------------------------------------
 * Aceite público
 * -------------------------------------------------------------------- */

function lk_handle_quote_accept( $q ) {
	if ( 'POST' !== ( isset( $_SERVER['REQUEST_METHOD'] ) ? $_SERVER['REQUEST_METHOD'] : '' ) || ! isset( $_POST['lk_quote_accept'] ) ) { // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
		return;
	}
	$fail = function ( $msg ) {
		$GLOBALS['lk_quote_error'] = $msg;
	};
	if ( ! isset( $_POST['_wpnonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['_wpnonce'] ) ), 'lk_quote_' . $q->id ) ) {
		return $fail( 'Sessão expirada. Recarregue a página e tente de novo.' );
	}
	if ( in_array( $q->status, array( 'aceito', 'pago' ), true ) ) {
		return $fail( 'Este orçamento já foi aprovado.' );
	}
	if ( $q->valid_until && $q->valid_until < lk_today() && ! lk_is_team() ) {
		return $fail( 'Este orçamento venceu em ' . lk_date( $q->valid_until ) . '. Fale com a gente para atualizar.' );
	}
	if ( ! lk_in( 'aceite', 'bool' ) ) {
		return $fail( 'Marque que você concorda com o orçamento.' );
	}

	// 1. Cliente e acesso.
	$client  = $q->client_id ? lk_get( 'clients', $q->client_id ) : null;
	$current = is_user_logged_in() ? lk_client_by_user( get_current_user_id() ) : null;
	if ( $current && ( ! $client || (int) $current->id === (int) $client->id ) ) {
		$client = $current;
	} else {
		$email = lk_in( 'email', 'email' );
		$pass  = (string) lk_in( 'senha', 'raw' );
		$name  = lk_in( 'name' );
		if ( ! $name || ! is_email( $email ) ) {
			return $fail( 'Preencha o seu nome e um e-mail válido.' );
		}
		if ( strlen( $pass ) < 8 ) {
			return $fail( 'A senha precisa ter pelo menos 8 caracteres.' );
		}
		if ( $client && $client->user_id ) {
			return $fail( 'Você já tem acesso. Entre com o seu e-mail e senha para aprovar.' );
		}
		if ( email_exists( $email ) ) {
			return $fail( 'Este e-mail já tem cadastro. Entre com a sua senha para aprovar o orçamento.' );
		}
		$user_id = wp_insert_user(
			array(
				'user_login'   => $email,
				'user_email'   => $email,
				'user_pass'    => $pass,
				'display_name' => $name,
				'first_name'   => $name,
				'role'         => 'lk_client',
			)
		);
		if ( is_wp_error( $user_id ) ) {
			return $fail( 'Não foi possível criar o acesso: ' . $user_id->get_error_message() );
		}
		$data = array(
			'user_id'      => $user_id,
			'name'         => $name,
			'company'      => lk_in( 'company' ),
			'cnpj'         => lk_in( 'cnpj' ),
			'phone'        => lk_in( 'whatsapp' ),
			'whatsapp'     => lk_in( 'whatsapp' ),
			'email'        => $email,
			'cep'          => lk_in( 'cep' ),
			'address'      => lk_in( 'address' ),
			'status'       => 'ativo',
			'invite_token' => '',
		);
		if ( $client ) {
			lk_update( 'clients', $client->id, array_filter( $data ) + array( 'invite_token' => '' ) );
			$client = lk_get( 'clients', $client->id );
		} else {
			$client = lk_get( 'clients', lk_insert( 'clients', $data + array( 'source' => 'Orçamento online' ) ) );
		}
		wp_set_current_user( $user_id );
		wp_set_auth_cookie( $user_id, true );
	}

	// 2. Entrega e valor.
	$delivery = 'envio' === lk_in( 'entrega' ) && (float) $q->freight > 0 ? 'envio' : 'retirada';
	if ( 'envio' === $delivery && ! $client->address && lk_in( 'address' ) ) {
		lk_update( 'clients', $client->id, array( 'address' => lk_in( 'address' ), 'cep' => lk_in( 'cep' ) ) );
	}
	$amounts = lk_quote_amounts( $q, $delivery );
	$option  = 'cartao' === lk_in( 'pagamento' ) ? 'cartao' : 'pix';
	$amount  = $amounts[ $option ];

	// 3. Pedido.
	$project = lk_order_from_quote( $q, $client, $delivery, $amount );
	$desc    = 'Pedido #' . $project . ' · ' . $q->title . ( 'pix' === $option ? ' (à vista, ' . rtrim( rtrim( number_format( $amounts['off'], 1, ',', '' ), '0' ), ',' ) . '% off)' : '' );
	$trans   = lk_insert(
		'transactions',
		array(
			'type'        => 'in',
			'project_id'  => $project,
			'client_id'   => $client->id,
			'category'    => 'Pedido',
			'description' => $desc,
			'amount'      => $amount,
			'due_date'    => lk_today(),
			'method'      => 'pix' === $option ? 'Pix' : 'Cartão de crédito',
			'status'      => 'pendente',
		)
	);
	lk_update(
		'quotes',
		$q->id,
		array(
			'status'        => 'aceito',
			'accepted_at'   => lk_now(),
			'client_id'     => $client->id,
			'project_id'    => $project,
			'pay_option'    => $option,
			'delivery_mode' => $delivery,
		)
	);
	if ( $q->lead_id ) {
		$won = lk_funnel_won();
		lk_update( 'leads', $q->lead_id, array_filter( array( 'client_id' => $client->id, 'stage' => $won ) ) );
	}
	lk_log( $project, 'Orçamento aprovado pelo cliente: ' . lk_money( $amount ) . ' (' . ( 'pix' === $option ? 'Pix/à vista' : lk_quote_pay_text() ) . ', ' . ( 'envio' === $delivery ? 'envio' : 'retirada' ) . ').', true );
	do_action( 'lk_quote_accepted', $q->id, $client->id, $project );
	wp_safe_redirect( lk_pay_url( $trans ) );
	exit;
}

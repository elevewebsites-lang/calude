<?php
/**
 * Pedidos (tabela projects): nascem do orçamento aprovado (ou manualmente), andam no Kanban
 * Aguardando pagamento → Na fila → Imprimindo → Acabamento → Fotos → Pronto → Entregue.
 *
 * - Pagou: sai de "Aguardando pagamento" para a coluna seguinte.
 * - Registrar consumo: gramas por carretel + insumos + horas → baixa do estoque, custo real e horas da impressora.
 * - Chegou em "Pronto": e-mail automático para o cliente + botão de WhatsApp com a mensagem pronta.
 * - Salvar no portfólio: vira produto (fotos, preço cobrado, custo, gramas, tempo).
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function ap_order_items( $p ) {
	return ap_json( $p->items );
}

function ap_order_photos( $p ) {
	return ap_json( $p->photos );
}

/**
 * Coluna "Pronto" (configurável; padrão: a que tem "pront" no nome, senão a penúltima).
 */
function ap_ready_column() {
	$cols = array_keys( ap_columns() );
	$set  = sanitize_title( ap_setting( 'coluna_pronto' ) );
	if ( $set && in_array( $set, $cols, true ) ) {
		return $set;
	}
	foreach ( $cols as $c ) {
		if ( false !== strpos( $c, 'pront' ) ) {
			return $c;
		}
	}
	return count( $cols ) > 1 ? $cols[ count( $cols ) - 2 ] : end( $cols );
}

function ap_first_column() {
	$cols = array_keys( ap_columns() );
	return $cols[0];
}

/**
 * Cria o pedido a partir do orçamento aprovado.
 */
function ap_order_from_quote( $q, $client, $delivery, $amount ) {
	$items = ap_quote_items( $q );
	$id = ap_insert(
		'projects',
		array(
			'client_id'      => $client->id,
			'quote_id'       => $q->id,
			'title'          => $q->title,
			'status'         => ap_first_column(),
			'value'          => $amount,
			'cost_estimated' => $q->cost_total,
			'items'          => $q->items,
			'delivery_mode'  => $delivery,
			'ship_service'   => 'envio' === $delivery ? $q->freight_label : '',
			'ship_price'     => 'envio' === $delivery ? $q->freight : 0,
			'start_date'     => ap_today(),
			'due_date'       => $q->deadline_days ? gmdate( 'Y-m-d', strtotime( ap_today() . ' +' . (int) $q->deadline_days . ' day' ) ) : null,
			'notes'          => 'Origem: proposta nº ' . ap_quote_number( $q ) . '.' . ( $q->notes ? "\n" . $q->notes : '' ),
		)
	);
	ap_log( $id, 'Pedido criado a partir da proposta nº ' . ap_quote_number( $q ) . ' "' . $q->title . '".' );
	return $id;
}

/**
 * Pagamento confirmado → pedido vai para a fila de produção.
 */
add_action( 'ap_payment_received', 'ap_order_paid', 5 );
function ap_order_paid( $trans_id ) {
	$t = ap_get( 'transactions', $trans_id );
	if ( ! $t || ! $t->project_id ) {
		return;
	}
	$p = ap_get( 'projects', $t->project_id );
	if ( ! $p ) {
		return;
	}
	$cols = array_keys( ap_columns() );
	if ( $p->status === $cols[0] && isset( $cols[1] ) && preg_match( '/aguard|pagament/', $cols[0] ) ) {
		ap_update( 'projects', $p->id, array( 'status' => $cols[1], 'board_col' => '' ) );
		ap_log( $p->id, 'Pagamento confirmado. Pedido na fila de produção.', true );
		ap_insert(
			'tasks',
			array(
				'project_id' => $p->id,
				'grp'        => 'Produção',
				'title'      => 'Pedido pago: produzir "' . $p->title . '"',
				'priority'   => 'alta',
				'due_date'   => $p->due_date ? $p->due_date : ap_today(),
				'assignee'   => ap_owner_id(),
			)
		);
	}
	if ( $p->quote_id ) {
		ap_update( 'quotes', $p->quote_id, array( 'status' => 'pago' ) );
	}
}

/**
 * Usuário que recebe as tarefas automáticas (o primeiro administrador).
 */
function ap_owner_id() {
	$admins = get_users( array( 'role' => 'administrator', 'number' => 1, 'orderby' => 'ID', 'fields' => 'ID' ) );
	return $admins ? (int) $admins[0] : 0;
}

/* -----------------------------------------------------------------------
 * Pedido manual (venda de balcão, marketplace…)
 * -------------------------------------------------------------------- */

function ap_do_order_create() {
	ap_require( 'projetos' );
	$client_id = ap_in( 'client_id', 'int' );
	if ( ! $client_id && ap_in( 'new_name' ) ) {
		$client_id = ap_insert(
			'clients',
			array(
				'name'     => ap_in( 'new_name' ),
				'whatsapp' => ap_in( 'new_whatsapp' ),
				'email'    => ap_in( 'new_email', 'email' ),
				'source'   => ap_in( 'new_source' ),
				'status'   => 'convidado',
				'invite_token' => wp_generate_password( 32, false ),
			)
		);
	}
	$value = ap_in( 'value', 'money' );
	$paid  = ap_in( 'paid', 'bool' );
	$cols  = array_keys( ap_columns() );
	$id    = ap_insert(
		'projects',
		array(
			'client_id'     => $client_id,
			'product_id'    => ap_in( 'product_id', 'int' ),
			'title'         => ap_in( 'title' ) ? ap_in( 'title' ) : 'Pedido',
			'status'        => $paid && isset( $cols[1] ) ? $cols[1] : $cols[0],
			'value'         => $value,
			'delivery_mode' => ap_in( 'delivery_mode' ) ? ap_in( 'delivery_mode' ) : 'retirada',
			'start_date'    => ap_today(),
			'due_date'      => ap_in( 'due_date', 'date' ),
			'notes'         => ap_in( 'notes', 'textarea' ),
			'kind'          => ap_in( 'channel' ) ? sanitize_key( ap_in( 'channel' ) ) : 'pedido',
		)
	);
	if ( $value > 0 ) {
		ap_insert(
			'transactions',
			array(
				'type'        => 'in',
				'project_id'  => $id,
				'client_id'   => $client_id,
				'category'    => ap_in( 'channel' ) && 'pedido' !== ap_in( 'channel' ) ? 'Venda marketplace' : 'Pedido',
				'description' => 'Pedido #' . $id . ' · ' . ( ap_in( 'title' ) ? ap_in( 'title' ) : 'Pedido' ),
				'amount'      => $value,
				'due_date'    => ap_today(),
				'paid_at'     => $paid ? ap_today() : null,
				'method'      => ap_in( 'method' ),
				'status'      => $paid ? 'pago' : 'pendente',
			)
		);
	}
	ap_log( $id, 'Pedido criado manualmente.' );
	ap_back( 'Pedido criado.', 'ok', ap_panel_url( 'pedido', $id ) );
}

function ap_do_order_save() {
	ap_require( 'projetos' );
	$p = ap_get( 'projects', ap_in( 'id', 'int' ) );
	if ( ! $p ) {
		ap_back();
	}
	ap_update(
		'projects',
		$p->id,
		array(
			'title'         => ap_in( 'title' ) ? ap_in( 'title' ) : $p->title,
			'due_date'      => ap_in( 'due_date', 'date' ),
			'delivery_mode' => ap_in( 'delivery_mode' ) ? ap_in( 'delivery_mode' ) : $p->delivery_mode,
			'ship_service'  => ap_in( 'ship_service' ),
			'ship_price'    => ap_in( 'ship_price', 'money' ),
			'tracking'      => ap_in( 'tracking' ),
			'notes'         => ap_in( 'notes', 'textarea' ),
		)
	);
	if ( ap_in( 'tracking' ) && ap_in( 'tracking' ) !== $p->tracking ) {
		ap_log( $p->id, 'Código de rastreio: ' . ap_in( 'tracking' ), true );
	}
	ap_back( 'Pedido salvo.' );
}

/**
 * Registrar consumo: o que realmente gastou (a balança manda).
 */
function ap_do_order_consume() {
	ap_require( 'projetos' );
	$p = ap_get( 'projects', ap_in( 'id', 'int' ) );
	if ( ! $p ) {
		ap_back();
	}
	$raw  = isset( $_POST['u'] ) ? (array) wp_unslash( $_POST['u'] ) : array(); // phpcs:ignore WordPress.Security.NonceVerification,WordPress.Security.ValidatedSanitizedInput
	$pair = function ( $ids, $vals ) {
		$out = array();
		foreach ( (array) $ids as $i => $id ) {
			$v = (float) str_replace( ',', '.', (string) ( $vals[ $i ] ?? 0 ) );
			if ( absint( $id ) && $v > 0 ) {
				$out[] = array( absint( $id ), $v );
			}
		}
		return $out;
	};
	$fils  = $pair( $raw['fil_id'] ?? array(), $raw['fil_g'] ?? array() );
	$sups  = $pair( $raw['sup_id'] ?? array(), $raw['sup_qty'] ?? array() );
	$hours = (float) str_replace( ',', '.', (string) ( $raw['hours'] ?? 0 ) ) + (float) ( $raw['minutes'] ?? 0 ) / 60;
	$pid   = absint( $raw['printer'] ?? 0 );

	$material = ap_stock_consume( $p->id, $fils, $sups );
	$machine  = 0;
	$printer  = $pid ? ap_get( 'printers', $pid ) : null;
	if ( $printer && $hours > 0 ) {
		ap_update( 'printers', $printer->id, array( 'hours_used' => $printer->hours_used + $hours ) );
		$machine = $hours * ( $printer->watts / 1000 * ap_num_setting( 'kwh' ) + ( $printer->life_hours > 0 ? $printer->price / $printer->life_hours : 0 ) );
	}
	$labor  = (float) str_replace( ',', '.', (string) ( $raw['labor_min'] ?? 0 ) ) / 60 * ap_num_setting( 'mao_obra_hora' );
	$grams  = array_sum( array_column( $fils, 1 ) );
	$cost   = round( $p->cost_real + $material + $machine + $labor, 2 );
	ap_update(
		'projects',
		$p->id,
		array(
			'cost_real'   => $cost,
			'grams_used'  => $p->grams_used + $grams,
			'print_hours' => $p->print_hours + $hours,
			'printer_id'  => $pid ? $pid : $p->printer_id,
		)
	);
	ap_log( $p->id, 'Consumo registrado: ' . round( $grams ) . ' g de filamento' . ( $hours ? ', ' . round( $hours, 1 ) . ' h de impressão' : '' ) . ' · custo ' . ap_money( $material + $machine + $labor ) . '.' );
	ap_back( 'Consumo registrado e estoque atualizado. Custo real do pedido: ' . ap_money( $cost ) . '.' );
}

function ap_do_order_unconsume() {
	ap_require( 'projetos' );
	$p = ap_get( 'projects', ap_in( 'id', 'int' ) );
	if ( $p ) {
		ap_stock_unconsume( $p->id );
		ap_update( 'projects', $p->id, array( 'cost_real' => 0, 'grams_used' => 0 ) );
		ap_log( $p->id, 'Consumo desfeito (material devolvido ao estoque).' );
	}
	ap_back( 'Consumo desfeito.' );
}

/**
 * Fotos da peça pronta (vão para a área do cliente e para o Drive).
 */
function ap_do_order_photos() {
	ap_require( 'projetos' );
	$p = ap_get( 'projects', ap_in( 'id', 'int' ) );
	if ( ! $p ) {
		ap_back();
	}
	$client = $p->client_id ? ap_get( 'clients', $p->client_id ) : null;
	$photos = ap_order_photos( $p );
	$remove = isset( $_POST['remove_img'] ) ? array_map( 'absint', (array) $_POST['remove_img'] ) : array(); // phpcs:ignore WordPress.Security.NonceVerification
	$photos = array_values(
		array_filter(
			$photos,
			function ( $img ) use ( $remove ) {
				return ! in_array( (int) ( $img['id'] ?? 0 ), $remove, true );
			}
		)
	);
	$new    = ap_store_uploads( 'photos', array( 'Pedidos', '#' . $p->id . ' ' . ( $client ? ap_client_label( $client ) . ' - ' : '' ) . $p->title ), 'image' );
	ap_update( 'projects', $p->id, array( 'photos' => wp_json_encode( array_merge( $photos, $new ) ) ) );
	if ( $new ) {
		ap_log( $p->id, count( $new ) . ' foto(s) da peça pronta adicionada(s).', true );
	}
	ap_back( $new ? 'Fotos salvas.' : 'Fotos atualizadas.' );
}

/* -----------------------------------------------------------------------
 * Pronto para retirar / enviar
 * -------------------------------------------------------------------- */

add_action( 'ap_project_stage', 'ap_order_stage_changed', 5, 2 );
function ap_order_stage_changed( $id, $status ) {
	if ( $status === ap_ready_column() ) {
		$p = ap_get( 'projects', $id );
		if ( $p && ! $p->ready_at ) {
			ap_update( 'projects', $id, array( 'ready_at' => ap_now() ) );
		}
		if ( $p && ! $p->ready_notified && '1' === (string) ap_setting( 'email_pronto' ) ) {
			ap_order_send_ready( $id );
		}
	}
	if ( $status === ap_last_column() ) {
		ap_update( 'projects', $id, array( 'delivered_at' => ap_now() ) );
	}
}

function ap_order_ready_message( $p, $client ) {
	$first = $client && $client->name ? strtok( $client->name, ' ' ) : '';
	$how   = 'envio' === $p->delivery_mode ? 'Já vamos despachar' . ( $p->tracking ? ' (rastreio: ' . $p->tracking . ')' : '' ) . '.' : ap_setting( 'retirada_texto' );
	$pay   = ap_order_payment( $p );
	$owe   = $pay['due'] > 0 ? ' Falta pagar ' . ap_money( $pay['due'] ) . ' na retirada (Pix, cartão ou dinheiro).' : '';
	return 'Olá' . ( $first ? ', ' . $first : '' ) . '! Seu pedido "' . $p->title . '" está pronto! 🎉 ' . $how . $owe . ' Veja o pedido na sua área do cliente: ' . ap_client_url( 'projeto', $p->id );
}

function ap_order_wa_link( $p ) {
	$client = $p->client_id ? ap_get( 'clients', $p->client_id ) : null;
	$phone  = $client ? ( $client->whatsapp ? $client->whatsapp : $client->phone ) : '';
	return $phone ? ap_wa_link( $phone, ap_order_ready_message( $p, $client ) ) : '';
}

function ap_order_send_ready( $id ) {
	$p      = ap_get( 'projects', $id );
	$client = $p && $p->client_id ? ap_get( 'clients', $p->client_id ) : null;
	if ( ! $p || ! $client || ! is_email( $client->email ) ) {
		return false;
	}
	$first = $client->name ? strtok( $client->name, ' ' ) : '';
	$rows  = array(
		'Pedido'  => '#' . $p->id . ' · ' . $p->title,
		'Entrega' => 'envio' === $p->delivery_mode ? 'Envio' . ( $p->ship_service ? ' · ' . $p->ship_service : '' ) . ( $p->tracking ? ' · rastreio ' . $p->tracking : '' ) : 'Retirada',
	);
	$after = '';
	foreach ( array_slice( ap_order_photos( $p ), 0, 3 ) as $img ) {
		$after .= '<img src="' . esc_url( $img['url'] ) . '" alt="" style="width:31%;margin:0 1% 8px 0;border-radius:10px;vertical-align:top;">';
	}
	$after = $after ? '<div style="margin-top:18px;">' . $after . '</div>' : '';
	$after .= '<p style="margin:14px 0 0;font-size:13px;color:#737373;">' . esc_html( 'envio' === $p->delivery_mode ? 'Assim que postarmos, você recebe o código de rastreio.' : ap_setting( 'retirada_texto' ) ) . '</p>';
	$ok = ap_mail(
		$client->email,
		'Seu pedido está pronto! · ' . $p->title,
		'Seu pedido está pronto!',
		'Olá' . ( $first ? ', ' . $first : '' ) . '! Terminamos o seu pedido e ele ficou do jeito que planejamos. Confira as fotos:',
		$rows,
		'Ver meu pedido',
		ap_client_entry_url( $client, 'projeto', $p->id ),
		$after
	);
	if ( $ok ) {
		ap_update( 'projects', $p->id, array( 'ready_notified' => ap_now() ) );
		ap_log( $p->id, 'E-mail "pedido pronto" enviado ao cliente.' );
	}
	return $ok;
}

function ap_do_order_notify_ready() {
	ap_require( 'projetos' );
	$ok = ap_order_send_ready( ap_in( 'id', 'int' ) );
	ap_back( $ok ? 'E-mail enviado ao cliente.' : 'Não deu para enviar: confira o e-mail do cliente e o SMTP em Configurações.', $ok ? 'ok' : 'erro' );
}

/* -----------------------------------------------------------------------
 * Portfólio / produto
 * -------------------------------------------------------------------- */

function ap_do_order_to_product() {
	ap_require( 'produtos' );
	$p = ap_get( 'projects', ap_in( 'id', 'int' ) );
	if ( ! $p ) {
		ap_back();
	}
	$items  = ap_order_items( $p );
	$qty    = 0;
	foreach ( $items as $it ) {
		if ( 'impressao' === ( $it['kind'] ?? 'impressao' ) ) {
			$qty += (int) ( $it['qty'] ?? 1 );
		}
	}
	$qty    = max( 1, $qty );
	$photos = ap_order_photos( $p );
	if ( ! $photos && $p->quote_id && ( $q = ap_get( 'quotes', $p->quote_id ) ) ) { // phpcs:ignore
		$photos = ap_json( $q->images );
	}
	$calc_id = 0;
	foreach ( $items as $it ) {
		if ( ! empty( $it['calc'] ) ) {
			$calc_id = (int) $it['calc'];
			break;
		}
	}
	$calc = $calc_id ? ap_get( 'calcs', $calc_id ) : null;
	$id   = ap_insert(
		'products',
		array(
			'name'           => $p->title,
			'description'    => implode( "\n", array_filter( array_map( function ( $it ) { return $it['desc'] ?? ''; }, $items ) ) ),
			'photos'         => wp_json_encode( $photos ),
			'calc'           => $calc ? $calc->data : '',
			'cost'           => round( ( $p->cost_real > 0 ? $p->cost_real : $p->cost_estimated ) / $qty, 2 ),
			'price'          => $calc ? $calc->price : round( $p->value / $qty, 2 ),
			'sold_price'     => round( $p->value / $qty, 2 ),
			'sold_count'     => $qty,
			'grams'          => $p->grams_used > 0 ? round( $p->grams_used / $qty, 1 ) : 0,
			'print_hours'    => $p->print_hours > 0 ? round( $p->print_hours / $qty, 2 ) : 0,
			'source_project' => $p->id,
			'portfolio'      => 1,
		)
	);
	ap_update( 'projects', $p->id, array( 'product_id' => $id ) );
	ap_back( 'Salvo em Produtos. Ajuste o preço de venda e publique nos canais.', 'ok', ap_panel_url( 'produto', $id ) );
}

/**
 * Mudar a etapa pela tela do pedido (o mesmo que arrastar no quadro).
 */
function ap_do_order_stage() {
	ap_require( 'projetos' );
	$p      = ap_get( 'projects', ap_in( 'id', 'int' ) );
	$status = sanitize_key( ap_in( 'status' ) );
	if ( $p && isset( ap_columns()[ $status ] ) && $status !== $p->status ) {
		$gate = ap_delivery_payment_gate( $p, $status, ap_in( 'pay_method' ), (bool) ap_in( 'unpaid', 'bool' ) );
		if ( $gate ) {
			ap_back( $gate, 'erro' );
		}
		ap_update( 'projects', $p->id, array( 'status' => $status, 'board_col' => '' ) );
		ap_project_moved( $p->id, $status );
	}
	ap_back( 'Etapa atualizada.' );
}

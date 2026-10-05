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

function lk_order_items( $p ) {
	return lk_json( $p->items );
}

function lk_order_photos( $p ) {
	return lk_json( $p->photos );
}

/**
 * Coluna "Pronto" (configurável; padrão: a que tem "pront" no nome, senão a penúltima).
 */
function lk_ready_column() {
	$cols = array_keys( lk_columns() );
	$set  = sanitize_title( lk_setting( 'coluna_pronto' ) );
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

function lk_first_column() {
	$cols = array_keys( lk_columns() );
	return $cols[0];
}

/**
 * Cria o pedido a partir do orçamento aprovado.
 */
function lk_order_from_quote( $q, $client, $delivery, $amount ) {
	$items = lk_quote_items( $q );
	$grams = 0;
	$hours = 0;
	foreach ( $items as $it ) {
		$grams += (float) $it['grams'] * (int) $it['qty'];
		$hours += (float) $it['hours'] * (int) $it['qty'];
	}
	$id = lk_insert(
		'projects',
		array(
			'client_id'      => $client->id,
			'quote_id'       => $q->id,
			'title'          => $q->title,
			'status'         => lk_first_column(),
			'value'          => $amount,
			'cost_estimated' => $q->cost_total,
			'items'          => $q->items,
			'delivery_mode'  => $delivery,
			'ship_service'   => 'envio' === $delivery ? $q->freight_label : '',
			'ship_price'     => 'envio' === $delivery ? $q->freight : 0,
			'start_date'     => lk_today(),
			'due_date'       => $q->deadline_days ? gmdate( 'Y-m-d', strtotime( lk_today() . ' +' . (int) $q->deadline_days . ' day' ) ) : null,
			'notes'          => 'Estimativa do orçamento: ' . round( $grams ) . ' g · ' . round( $hours, 1 ) . ' h de impressão.',
		)
	);
	lk_log( $id, 'Pedido criado a partir do orçamento "' . $q->title . '".' );
	return $id;
}

/**
 * Pagamento confirmado → pedido vai para a fila de produção.
 */
add_action( 'lk_payment_received', 'lk_order_paid', 5 );
function lk_order_paid( $trans_id ) {
	$t = lk_get( 'transactions', $trans_id );
	if ( ! $t || ! $t->project_id ) {
		return;
	}
	$p = lk_get( 'projects', $t->project_id );
	if ( ! $p ) {
		return;
	}
	$cols = array_keys( lk_columns() );
	if ( $p->status === $cols[0] && isset( $cols[1] ) && preg_match( '/aguard|pagament/', $cols[0] ) ) {
		lk_update( 'projects', $p->id, array( 'status' => $cols[1], 'board_col' => '' ) );
		lk_log( $p->id, 'Pagamento confirmado. Pedido na fila de produção.', true );
		lk_insert(
			'tasks',
			array(
				'project_id' => $p->id,
				'grp'        => 'Produção',
				'title'      => 'Pedido pago: produzir "' . $p->title . '"',
				'priority'   => 'alta',
				'due_date'   => $p->due_date ? $p->due_date : lk_today(),
				'assignee'   => lk_owner_id(),
			)
		);
	}
	if ( $p->quote_id ) {
		lk_update( 'quotes', $p->quote_id, array( 'status' => 'pago' ) );
	}
}

/**
 * Usuário que recebe as tarefas automáticas (o primeiro administrador).
 */
function lk_owner_id() {
	$admins = get_users( array( 'role' => 'administrator', 'number' => 1, 'orderby' => 'ID', 'fields' => 'ID' ) );
	return $admins ? (int) $admins[0] : 0;
}

/* -----------------------------------------------------------------------
 * Pedido manual (venda de balcão, marketplace…)
 * -------------------------------------------------------------------- */

function lk_do_order_create() {
	lk_require( 'projetos' );
	$client_id = lk_in( 'client_id', 'int' );
	if ( ! $client_id && lk_in( 'new_name' ) ) {
		$client_id = lk_insert(
			'clients',
			array(
				'name'     => lk_in( 'new_name' ),
				'whatsapp' => lk_in( 'new_whatsapp' ),
				'email'    => lk_in( 'new_email', 'email' ),
				'source'   => lk_in( 'new_source' ),
				'status'   => 'convidado',
				'invite_token' => wp_generate_password( 32, false ),
			)
		);
	}
	$value = lk_in( 'value', 'money' );
	$paid  = lk_in( 'paid', 'bool' );
	$cols  = array_keys( lk_columns() );
	$id    = lk_insert(
		'projects',
		array(
			'client_id'     => $client_id,
			'product_id'    => lk_in( 'product_id', 'int' ),
			'title'         => lk_in( 'title' ) ? lk_in( 'title' ) : 'Pedido',
			'status'        => $paid && isset( $cols[1] ) ? $cols[1] : $cols[0],
			'value'         => $value,
			'delivery_mode' => lk_in( 'delivery_mode' ) ? lk_in( 'delivery_mode' ) : 'retirada',
			'start_date'    => lk_today(),
			'due_date'      => lk_in( 'due_date', 'date' ),
			'notes'         => lk_in( 'notes', 'textarea' ),
			'kind'          => lk_in( 'channel' ) ? sanitize_key( lk_in( 'channel' ) ) : 'pedido',
		)
	);
	if ( $value > 0 ) {
		lk_insert(
			'transactions',
			array(
				'type'        => 'in',
				'project_id'  => $id,
				'client_id'   => $client_id,
				'category'    => lk_in( 'channel' ) && 'pedido' !== lk_in( 'channel' ) ? 'Venda marketplace' : 'Pedido',
				'description' => 'Pedido #' . $id . ' · ' . ( lk_in( 'title' ) ? lk_in( 'title' ) : 'Pedido' ),
				'amount'      => $value,
				'due_date'    => lk_today(),
				'paid_at'     => $paid ? lk_today() : null,
				'method'      => lk_in( 'method' ),
				'status'      => $paid ? 'pago' : 'pendente',
			)
		);
	}
	lk_log( $id, 'Pedido criado manualmente.' );
	lk_back( 'Pedido criado.', 'ok', lk_panel_url( 'pedido', $id ) );
}

function lk_do_order_save() {
	lk_require( 'projetos' );
	$p = lk_get( 'projects', lk_in( 'id', 'int' ) );
	if ( ! $p ) {
		lk_back();
	}
	lk_update(
		'projects',
		$p->id,
		array(
			'title'         => lk_in( 'title' ) ? lk_in( 'title' ) : $p->title,
			'due_date'      => lk_in( 'due_date', 'date' ),
			'delivery_mode' => lk_in( 'delivery_mode' ) ? lk_in( 'delivery_mode' ) : $p->delivery_mode,
			'ship_service'  => lk_in( 'ship_service' ),
			'ship_price'    => lk_in( 'ship_price', 'money' ),
			'tracking'      => lk_in( 'tracking' ),
			'notes'         => lk_in( 'notes', 'textarea' ),
		)
	);
	if ( lk_in( 'tracking' ) && lk_in( 'tracking' ) !== $p->tracking ) {
		lk_log( $p->id, 'Código de rastreio: ' . lk_in( 'tracking' ), true );
	}
	lk_back( 'Pedido salvo.' );
}

/**
 * Registrar consumo: o que realmente gastou (a balança manda).
 */
function lk_do_order_consume() {
	lk_require( 'projetos' );
	$p = lk_get( 'projects', lk_in( 'id', 'int' ) );
	if ( ! $p ) {
		lk_back();
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

	$material = lk_stock_consume( $p->id, $fils, $sups );
	$machine  = 0;
	$printer  = $pid ? lk_get( 'printers', $pid ) : null;
	if ( $printer && $hours > 0 ) {
		lk_update( 'printers', $printer->id, array( 'hours_used' => $printer->hours_used + $hours ) );
		$machine = $hours * ( $printer->watts / 1000 * lk_num_setting( 'kwh' ) + ( $printer->life_hours > 0 ? $printer->price / $printer->life_hours : 0 ) );
	}
	$labor  = (float) str_replace( ',', '.', (string) ( $raw['labor_min'] ?? 0 ) ) / 60 * lk_num_setting( 'mao_obra_hora' );
	$grams  = array_sum( array_column( $fils, 1 ) );
	$cost   = round( $p->cost_real + $material + $machine + $labor, 2 );
	lk_update(
		'projects',
		$p->id,
		array(
			'cost_real'   => $cost,
			'grams_used'  => $p->grams_used + $grams,
			'print_hours' => $p->print_hours + $hours,
			'printer_id'  => $pid ? $pid : $p->printer_id,
		)
	);
	lk_log( $p->id, 'Consumo registrado: ' . round( $grams ) . ' g de filamento' . ( $hours ? ', ' . round( $hours, 1 ) . ' h de impressão' : '' ) . ' · custo ' . lk_money( $material + $machine + $labor ) . '.' );
	lk_back( 'Consumo registrado e estoque atualizado. Custo real do pedido: ' . lk_money( $cost ) . '.' );
}

function lk_do_order_unconsume() {
	lk_require( 'projetos' );
	$p = lk_get( 'projects', lk_in( 'id', 'int' ) );
	if ( $p ) {
		lk_stock_unconsume( $p->id );
		lk_update( 'projects', $p->id, array( 'cost_real' => 0, 'grams_used' => 0 ) );
		lk_log( $p->id, 'Consumo desfeito (material devolvido ao estoque).' );
	}
	lk_back( 'Consumo desfeito.' );
}

/**
 * Fotos da peça pronta (vão para a área do cliente e para o Drive).
 */
function lk_do_order_photos() {
	lk_require( 'projetos' );
	$p = lk_get( 'projects', lk_in( 'id', 'int' ) );
	if ( ! $p ) {
		lk_back();
	}
	$client = $p->client_id ? lk_get( 'clients', $p->client_id ) : null;
	$photos = lk_order_photos( $p );
	$remove = isset( $_POST['remove_img'] ) ? array_map( 'absint', (array) $_POST['remove_img'] ) : array(); // phpcs:ignore WordPress.Security.NonceVerification
	$photos = array_values(
		array_filter(
			$photos,
			function ( $img ) use ( $remove ) {
				return ! in_array( (int) ( $img['id'] ?? 0 ), $remove, true );
			}
		)
	);
	$new    = lk_store_uploads( 'photos', array( 'Pedidos', '#' . $p->id . ' ' . ( $client ? lk_client_label( $client ) . ' - ' : '' ) . $p->title ), 'image' );
	lk_update( 'projects', $p->id, array( 'photos' => wp_json_encode( array_merge( $photos, $new ) ) ) );
	if ( $new ) {
		lk_log( $p->id, count( $new ) . ' foto(s) da peça pronta adicionada(s).', true );
	}
	lk_back( $new ? 'Fotos salvas.' : 'Fotos atualizadas.' );
}

/* -----------------------------------------------------------------------
 * Pronto para retirar / enviar
 * -------------------------------------------------------------------- */

add_action( 'lk_project_stage', 'lk_order_stage_changed', 5, 2 );
function lk_order_stage_changed( $id, $status ) {
	if ( $status === lk_ready_column() ) {
		$p = lk_get( 'projects', $id );
		if ( $p && ! $p->ready_at ) {
			lk_update( 'projects', $id, array( 'ready_at' => lk_now() ) );
		}
		if ( $p && ! $p->ready_notified && '1' === (string) lk_setting( 'email_pronto' ) ) {
			lk_order_send_ready( $id );
		}
	}
	if ( $status === lk_last_column() ) {
		lk_update( 'projects', $id, array( 'delivered_at' => lk_now() ) );
	}
}

function lk_order_ready_message( $p, $client ) {
	$first = $client && $client->name ? strtok( $client->name, ' ' ) : '';
	$how   = 'envio' === $p->delivery_mode ? 'Já vamos despachar' . ( $p->tracking ? ' (rastreio: ' . $p->tracking . ')' : '' ) . '.' : lk_setting( 'retirada_texto' );
	return 'Olá' . ( $first ? ', ' . $first : '' ) . '! Seu pedido "' . $p->title . '" está pronto! 🎉 ' . $how . ' Veja as fotos na sua área do cliente: ' . lk_client_url( 'projeto', $p->id );
}

function lk_order_wa_link( $p ) {
	$client = $p->client_id ? lk_get( 'clients', $p->client_id ) : null;
	$phone  = $client ? ( $client->whatsapp ? $client->whatsapp : $client->phone ) : '';
	return $phone ? lk_wa_link( $phone, lk_order_ready_message( $p, $client ) ) : '';
}

function lk_order_send_ready( $id ) {
	$p      = lk_get( 'projects', $id );
	$client = $p && $p->client_id ? lk_get( 'clients', $p->client_id ) : null;
	if ( ! $p || ! $client || ! is_email( $client->email ) ) {
		return false;
	}
	$first = $client->name ? strtok( $client->name, ' ' ) : '';
	$rows  = array(
		'Pedido'  => '#' . $p->id . ' · ' . $p->title,
		'Entrega' => 'envio' === $p->delivery_mode ? 'Envio' . ( $p->ship_service ? ' · ' . $p->ship_service : '' ) . ( $p->tracking ? ' · rastreio ' . $p->tracking : '' ) : 'Retirada',
	);
	$after = '';
	foreach ( array_slice( lk_order_photos( $p ), 0, 3 ) as $img ) {
		$after .= '<img src="' . esc_url( $img['url'] ) . '" alt="" style="width:31%;margin:0 1% 8px 0;border-radius:10px;vertical-align:top;">';
	}
	$after = $after ? '<div style="margin-top:18px;">' . $after . '</div>' : '';
	$after .= '<p style="margin:14px 0 0;font-size:13px;color:#737373;">' . esc_html( 'envio' === $p->delivery_mode ? 'Assim que postarmos, você recebe o código de rastreio.' : lk_setting( 'retirada_texto' ) ) . '</p>';
	$ok = lk_mail(
		$client->email,
		'Seu pedido está pronto! · ' . $p->title,
		'Seu pedido está pronto!',
		'Olá' . ( $first ? ', ' . $first : '' ) . '! Terminamos o seu pedido e ele ficou do jeito que planejamos. Confira as fotos:',
		$rows,
		'Ver meu pedido',
		lk_client_entry_url( $client, 'projeto', $p->id ),
		$after
	);
	if ( $ok ) {
		lk_update( 'projects', $p->id, array( 'ready_notified' => lk_now() ) );
		lk_log( $p->id, 'E-mail "pedido pronto" enviado ao cliente.' );
	}
	return $ok;
}

function lk_do_order_notify_ready() {
	lk_require( 'projetos' );
	$ok = lk_order_send_ready( lk_in( 'id', 'int' ) );
	lk_back( $ok ? 'E-mail enviado ao cliente.' : 'Não deu para enviar: confira o e-mail do cliente e o SMTP em Configurações.', $ok ? 'ok' : 'erro' );
}

/* -----------------------------------------------------------------------
 * Portfólio / produto
 * -------------------------------------------------------------------- */

function lk_do_order_to_product() {
	lk_require( 'produtos' );
	$p = lk_get( 'projects', lk_in( 'id', 'int' ) );
	if ( ! $p ) {
		lk_back();
	}
	$items  = lk_order_items( $p );
	$qty    = 0;
	foreach ( $items as $it ) {
		if ( 'impressao' === ( $it['kind'] ?? 'impressao' ) ) {
			$qty += (int) ( $it['qty'] ?? 1 );
		}
	}
	$qty    = max( 1, $qty );
	$photos = lk_order_photos( $p );
	if ( ! $photos && $p->quote_id && ( $q = lk_get( 'quotes', $p->quote_id ) ) ) { // phpcs:ignore
		$photos = lk_json( $q->images );
	}
	$calc_id = 0;
	foreach ( $items as $it ) {
		if ( ! empty( $it['calc'] ) ) {
			$calc_id = (int) $it['calc'];
			break;
		}
	}
	$calc = $calc_id ? lk_get( 'calcs', $calc_id ) : null;
	$id   = lk_insert(
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
	lk_update( 'projects', $p->id, array( 'product_id' => $id ) );
	lk_back( 'Salvo em Produtos. Ajuste o preço de venda e publique nos canais.', 'ok', lk_panel_url( 'produto', $id ) );
}

/**
 * Mudar a etapa pela tela do pedido (o mesmo que arrastar no quadro).
 */
function lk_do_order_stage() {
	lk_require( 'projetos' );
	$p      = lk_get( 'projects', lk_in( 'id', 'int' ) );
	$status = sanitize_key( lk_in( 'status' ) );
	if ( $p && isset( lk_columns()[ $status ] ) && $status !== $p->status ) {
		lk_update( 'projects', $p->id, array( 'status' => $status, 'board_col' => '' ) );
		lk_project_moved( $p->id, $status );
	}
	lk_back( 'Etapa atualizada.' );
}

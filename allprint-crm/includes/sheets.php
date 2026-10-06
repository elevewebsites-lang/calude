<?php
/**
 * Google Sheets: a planilha "Controle de pedidos" acompanha o sistema.
 *
 * - Cria a planilha na conta Google conectada (aba Pedidos no mesmo formato da planilha antiga + Clientes).
 * - Uma linha por item do pedido. Toda mudança no pedido (criar, etapa, pagamento, arte, edição) atualiza as linhas dele.
 * - A atualização sai da fila logo depois que a página responde (não deixa o usuário esperando) e, se falhar, tenta de novo de hora em hora.
 * - Precisa do escopo "spreadsheets": quem conectou o Google antes da v1.2 precisa conectar de novo (um clique).
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

const AP_SHEETS_API = 'https://sheets.googleapis.com/v4/spreadsheets';

function ap_sheets_state() {
	$s = get_option( 'ap_sheet', array() );
	return is_array( $s ) ? $s : array();
}

function ap_sheets_enabled() {
	$s = ap_sheets_state();
	return ! empty( $s['id'] ) && ap_google_connected();
}

/** O Google conectado tem permissão de planilhas? */
function ap_sheets_scope_ok() {
	$g = ap_google_state();
	return ap_google_connected() && ! empty( $g['scope'] ) && false !== strpos( $g['scope'], '/auth/spreadsheets' );
}

function ap_sheets_headers() {
	return array(
		'Data', 'Cliente', 'Tipo Cliente', 'Produto', 'Dimensões (cm)', 'Quantidade', 'm²', 'Valor Unitário (R$)', 'Valor Cobrado (R$)', 'Custo Material (R$)',
		'Status', 'Data da retirada / instalação', 'Forma de Pagamento', 'Data de Pagamento', 'Pago?', 'Observações', 'ID Pedido',
		'Etapa no sistema', 'Situação do pagamento', 'Valor do pedido (R$)', 'Desconto (R$)', 'A receber (R$)', 'Prazo', 'Pedido no sistema', 'Atualizado em',
	);
}

function ap_sheets_client_headers() {
	return array( 'Nome / Empresa', 'Apelido / Nome curto', 'Tipo', 'CNPJ/CPF', 'Contato (WhatsApp)', 'E-mail', 'Cidade', 'Como chegou', 'Ativo?', 'Observações', 'ID Cliente', 'Crédito na loja (R$)', 'Pedidos', 'Total comprado (R$)', 'Pasta no Drive' );
}

function ap_sheets_cost_headers() {
	return array( 'Data', 'Categoria', 'Descrição', 'Veículo', 'Tipo (veículo)', 'Valor (R$)', 'Status', 'Pago em', 'Vencimento', 'Forma de pagamento', 'Km', 'Litros', 'ID' );
}

/** Todas as saídas do financeiro (custos fixos, insumos, veículos…): é a "planilha de custos". */
function ap_sheets_cost_rows() {
	global $wpdb;
	$veh = array();
	foreach ( ap_vehicles( false ) as $v ) {
		$veh[ $v->id ] = ap_vehicle_label( $v );
	}
	$rows = array();
	foreach ( $wpdb->get_results( 'SELECT * FROM ' . ap_table( 'transactions' ) . " WHERE type = 'out' ORDER BY COALESCE(paid_at, due_date), id" ) as $t ) { // phpcs:ignore WordPress.DB.PreparedSQL
		$rows[] = array(
			(string) ( $t->paid_at ? $t->paid_at : $t->due_date ),
			(string) $t->category,
			(string) $t->description,
			$t->vehicle_id && isset( $veh[ $t->vehicle_id ] ) ? $veh[ $t->vehicle_id ] : '',
			(string) $t->vtype,
			round( (float) $t->amount, 2 ),
			'pago' === $t->status ? 'Pago' : 'A pagar',
			(string) $t->paid_at,
			(string) $t->due_date,
			(string) $t->method,
			$t->odometer ? (int) $t->odometer : '',
			$t->liters > 0 ? (float) $t->liters : '',
			$t->external_id ? $t->external_id : 'CUS-' . $t->id,
		);
	}
	return $rows;
}

/* -----------------------------------------------------------------------
 * Linhas
 * -------------------------------------------------------------------- */

function ap_sheets_key( $p ) {
	return $p->ext_id ? $p->ext_id : 'AP-' . $p->id;
}

function ap_sheets_status_label( $p ) {
	if ( false !== stripos( (string) $p->notes, 'Cancelado' ) && $p->archived ) {
		return 'Cancelado';
	}
	if ( $p->status === ap_ready_column() ) {
		return 'Aguardando retirada';
	}
	if ( $p->status === ap_last_column() ) {
		return false !== stripos( (string) $p->notes, 'Instalado' ) ? 'Instalado' : 'Entregue';
	}
	$cols = ap_columns();
	return $cols[ $p->status ] ?? $p->status;
}

/** Linhas (arrays) do pedido para a aba Pedidos. */
function ap_sheets_rows_for_project( $p ) {
	global $wpdb;
	$client = $p->client_id ? ap_get( 'clients', $p->client_id ) : null;
	$items  = ap_order_items( $p );
	if ( ! $items ) {
		$items = array( array( 'name' => $p->title, 'qty' => 1, 'unit' => $p->value, 'cost' => $p->cost_real, 'w' => 0, 'h' => 0, 'area' => 0 ) );
	}
	$pay   = ap_order_payment( $p );
	$tx    = $wpdb->get_results( $wpdb->prepare( 'SELECT status, method, paid_at FROM ' . ap_table( 'transactions' ) . " WHERE project_id = %d AND type = 'in' ORDER BY id DESC", $p->id ) ); // phpcs:ignore WordPress.DB.PreparedSQL
	$method = '';
	$paid_d = '';
	foreach ( $tx as $t ) {
		if ( 'pago' === $t->status && 'Crédito na loja' !== $t->method ) {
			$method = $method ? $method : $t->method;
			$paid_d = $paid_d ? $paid_d : (string) $t->paid_at;
		}
	}
	if ( ! $method && $tx ) {
		$method = (string) $tx[0]->method;
	}
	$is_paid = 'paid' === $pay['state'] ? 'Sim' : ( 'partial' === $pay['state'] ? 'Parcial' : ( 'none' === $pay['state'] ? '' : 'Não' ) );
	$notes   = trim( preg_replace( '/\s*\n\s*/', ' | ', (string) $p->notes ) );
	$stage   = ap_columns()[ $p->status ] ?? $p->status;
	$one     = 1 === count( $items );
	$rows    = array();
	foreach ( $items as $it ) {
		$qty   = max( 1, (int) ( $it['qty'] ?? 1 ) );
		$unit  = (float) ( $it['unit'] ?? 0 );
		$line  = $one ? (float) $p->value : round( $unit * $qty, 2 );
		$dims  = ( ! empty( $it['w'] ) && ! empty( $it['h'] ) ) ? rtrim( rtrim( number_format( (float) $it['w'], 1, '.', '' ), '0' ), '.' ) . '_' . rtrim( rtrim( number_format( (float) $it['h'], 1, '.', '' ), '0' ), '.' ) : '';
		$name  = preg_replace( '/ · [\d.,]+×[\d.,]+ cm$/u', '', (string) ( $it['name'] ?? $p->title ) );
		$rows[] = array(
			$p->start_date ? $p->start_date : substr( (string) $p->created_at, 0, 10 ),
			$client ? ( $client->name ? $client->name : ap_client_label( $client ) ) : '',
			$client ? (string) $client->kind : '',
			$name,
			$dims,
			$qty,
			round( (float) ( $it['area'] ?? 0 ), 2 ),
			$unit,
			$line,
			round( (float) ( $it['cost'] ?? 0 ), 2 ),
			ap_sheets_status_label( $p ),
			$p->delivered_at ? substr( (string) $p->delivered_at, 0, 10 ) : '',
			$method,
			$paid_d,
			$is_paid,
			mb_substr( $notes, 0, 400 ),
			ap_sheets_key( $p ),
			$stage,
			$pay['label'],
			(float) $p->value,
			(float) $p->discount,
			(float) $pay['due'],
			$p->due_date ? (string) $p->due_date : '',
			ap_panel_url( 'pedido', $p->id ),
			wp_date( 'Y-m-d H:i' ),
		);
	}
	return $rows;
}

function ap_sheets_client_rows() {
	global $wpdb;
	$tot = array();
	foreach ( $wpdb->get_results( 'SELECT client_id, COUNT(*) n, SUM(value) v FROM ' . ap_table( 'projects' ) . ' GROUP BY client_id' ) as $r ) { // phpcs:ignore WordPress.DB.PreparedSQL
		$tot[ $r->client_id ] = $r;
	}
	$rows = array();
	foreach ( ap_clients() as $c ) {
		$rows[] = array(
			$c->company ? $c->company : $c->name,
			$c->name,
			(string) $c->kind,
			(string) $c->cnpj,
			$c->whatsapp ? $c->whatsapp : $c->phone,
			(string) $c->email,
			(string) $c->city,
			(string) $c->source,
			$c->approved ? 'Sim' : 'Em análise',
			mb_substr( (string) $c->notes, 0, 300 ),
			$c->ext_id ? $c->ext_id : 'CLI-' . $c->id,
			ap_credit_balance( $c->id ),
			isset( $tot[ $c->id ] ) ? (int) $tot[ $c->id ]->n : 0,
			isset( $tot[ $c->id ] ) ? round( (float) $tot[ $c->id ]->v, 2 ) : 0,
			ap_drive_folder_link( array( 'Clientes', ap_client_label( $c ) ) ),
		);
	}
	return $rows;
}

/* -----------------------------------------------------------------------
 * Chamadas à API
 * -------------------------------------------------------------------- */

function ap_sheets_call( $method, $path, $body = null ) {
	$s   = ap_sheets_state();
	$url = AP_SHEETS_API . '/' . $s['id'] . $path;
	return ap_google_api( $method, $url, $body );
}

function ap_sheets_col( $n ) {
	$s = '';
	while ( $n > 0 ) {
		$m = ( $n - 1 ) % 26;
		$s = chr( 65 + $m ) . $s;
		$n = (int) ( ( $n - $m ) / 26 );
	}
	return $s;
}

/** Cria a planilha (uma vez) e devolve true/WP_Error. */
function ap_sheets_ensure() {
	$s = ap_sheets_state();
	if ( ! empty( $s['id'] ) ) {
		return true;
	}
	if ( ! ap_sheets_scope_ok() ) {
		return new WP_Error( 'ap_sheets', 'Conecte o Google de novo para liberar o acesso às planilhas (Configurações → Google Drive).' );
	}
	$res = ap_google_api(
		'POST',
		AP_SHEETS_API,
		array(
			'properties' => array( 'title' => ap_setting( 'empresa' ) . ' · Controle de pedidos', 'locale' => 'pt_BR', 'timeZone' => 'America/Sao_Paulo' ),
			'sheets'     => array(
				array( 'properties' => array( 'title' => 'Pedidos', 'gridProperties' => array( 'rowCount' => 20000, 'columnCount' => 26, 'frozenRowCount' => 1 ) ) ),
				array( 'properties' => array( 'title' => 'Clientes', 'gridProperties' => array( 'rowCount' => 3000, 'columnCount' => 16, 'frozenRowCount' => 1 ) ) ),
				array( 'properties' => array( 'title' => 'Custos', 'gridProperties' => array( 'rowCount' => 5000, 'columnCount' => 14, 'frozenRowCount' => 1 ) ) ),
			),
		)
	);
	if ( is_wp_error( $res ) ) {
		return $res;
	}
	$ids = array();
	foreach ( (array) ( $res['sheets'] ?? array() ) as $sh ) {
		$ids[ $sh['properties']['title'] ] = (int) $sh['properties']['sheetId'];
	}
	$state = array( 'id' => $res['spreadsheetId'], 'url' => $res['spreadsheetUrl'] ?? 'https://docs.google.com/spreadsheets/d/' . $res['spreadsheetId'], 'sheets' => $ids, 'created' => ap_now() );
	update_option( 'ap_sheet', $state, false );
	// Guarda a planilha dentro da pasta do sistema no Drive (se der).
	$folder = ap_drive_folder( array( 'Planilhas' ) );
	if ( ! is_wp_error( $folder ) ) {
		ap_google_api( 'PATCH', 'https://www.googleapis.com/drive/v3/files/' . rawurlencode( $state['id'] ) . '?addParents=' . rawurlencode( $folder ) . '&removeParents=root', array() );
	}
	return true;
}

/** Reescreve tudo (carga inicial e "reparar"). */
function ap_sheets_full() {
	@set_time_limit( 300 ); // phpcs:ignore
	$ok = ap_sheets_ensure();
	if ( is_wp_error( $ok ) ) {
		return $ok;
	}
	$rows = array( ap_sheets_headers() );
	foreach ( ap_projects( '1=1' ) as $p ) {
		foreach ( ap_sheets_rows_for_project( $p ) as $r ) {
			$rows[] = $r;
		}
	}
	// Mais antigos primeiro, como na planilha de papel.
	$head = array_shift( $rows );
	usort( $rows, function ( $a, $b ) {
		return strcmp( (string) $a[0] . (string) $a[16], (string) $b[0] . (string) $b[16] );
	} );
	array_unshift( $rows, $head );
	$r = ap_sheets_call( 'POST', '/values:batchClear', array( 'ranges' => array( 'Pedidos!A1:Z', 'Clientes!A1:Z' ) ) );
	if ( is_wp_error( $r ) ) {
		return $r;
	}
	$last = ap_sheets_col( count( ap_sheets_headers() ) );
	foreach ( array_chunk( $rows, 500 ) as $i => $chunk ) {
		$start = $i * 500 + 1;
		$r     = ap_sheets_call( 'PUT', '/values/' . rawurlencode( 'Pedidos!A' . $start . ':' . $last . ( $start + count( $chunk ) - 1 ) ) . '?valueInputOption=USER_ENTERED', array( 'values' => $chunk ) );
		if ( is_wp_error( $r ) ) {
			return $r;
		}
	}
	$c = ap_sheets_sync_clients();
	if ( is_wp_error( $c ) ) {
		return $c;
	}
	$k = ap_sheets_sync_costs();
	if ( is_wp_error( $k ) ) {
		return $k;
	}
	update_option( 'ap_sheet', array_merge( ap_sheets_state(), array( 'full_at' => ap_now(), 'rows' => count( $rows ) - 1 ) ), false );
	return count( $rows ) - 1;
}

function ap_sheets_sync_clients() {
	$rows = array_merge( array( ap_sheets_client_headers() ), ap_sheets_client_rows() );
	$last = ap_sheets_col( count( ap_sheets_client_headers() ) );
	$r    = ap_sheets_call( 'POST', '/values:batchClear', array( 'ranges' => array( 'Clientes!A2:Z' ) ) );
	if ( is_wp_error( $r ) ) {
		return $r;
	}
	foreach ( array_chunk( $rows, 500 ) as $i => $chunk ) {
		$start = $i * 500 + 1;
		$r     = ap_sheets_call( 'PUT', '/values/' . rawurlencode( 'Clientes!A' . $start . ':' . $last . ( $start + count( $chunk ) - 1 ) ) . '?valueInputOption=USER_ENTERED', array( 'values' => $chunk ) );
		if ( is_wp_error( $r ) ) {
			return $r;
		}
	}
	return true;
}

/** Garante a aba "Custos" (planilhas criadas antes dela não têm). */
function ap_sheets_ensure_costs_tab() {
	$st = ap_sheets_state();
	if ( ! empty( $st['sheets']['Custos'] ) ) {
		return true;
	}
	$r = ap_sheets_call( 'POST', ':batchUpdate', array( 'requests' => array( array( 'addSheet' => array( 'properties' => array( 'title' => 'Custos', 'gridProperties' => array( 'rowCount' => 5000, 'columnCount' => 14, 'frozenRowCount' => 1 ) ) ) ) ) ) );
	if ( is_wp_error( $r ) ) {
		return $r;
	}
	$id = (int) ( $r['replies'][0]['addSheet']['properties']['sheetId'] ?? 0 );
	$st['sheets']['Custos'] = $id ? $id : 1;
	update_option( 'ap_sheet', $st, false );
	return true;
}

/** Reescreve a aba Custos com todas as saídas do financeiro. */
function ap_sheets_sync_costs() {
	$ok = ap_sheets_ensure_costs_tab();
	if ( is_wp_error( $ok ) ) {
		return $ok;
	}
	$rows = array_merge( array( ap_sheets_cost_headers() ), ap_sheets_cost_rows() );
	$last = ap_sheets_col( count( ap_sheets_cost_headers() ) );
	$r    = ap_sheets_call( 'POST', '/values:batchClear', array( 'ranges' => array( 'Custos!A1:Z' ) ) );
	if ( is_wp_error( $r ) ) {
		return $r;
	}
	foreach ( array_chunk( $rows, 500 ) as $i => $chunk ) {
		$start = $i * 500 + 1;
		$r     = ap_sheets_call( 'PUT', '/values/' . rawurlencode( 'Custos!A' . $start . ':' . $last . ( $start + count( $chunk ) - 1 ) ) . '?valueInputOption=USER_ENTERED', array( 'values' => $chunk ) );
		if ( is_wp_error( $r ) ) {
			return $r;
		}
	}
	return true;
}

/** Atualiza só as linhas de um pedido (acha pela coluna "ID Pedido"). */
function ap_sheets_sync_project( $pid, &$keys = null ) {
	$p = ap_project( $pid );
	if ( ! $p ) {
		return true;
	}
	$key  = ap_sheets_key( $p );
	$rows = ap_sheets_rows_for_project( $p );
	$last = ap_sheets_col( count( ap_sheets_headers() ) );
	if ( null === $keys ) {
		$res = ap_sheets_call( 'GET', '/values/' . rawurlencode( 'Pedidos!Q2:Q' ) );
		if ( is_wp_error( $res ) ) {
			return $res;
		}
		$keys = array();
		foreach ( (array) ( $res['values'] ?? array() ) as $i => $v ) {
			$k = $v[0] ?? '';
			if ( '' !== $k ) {
				$keys[ $k ][] = $i + 2;
			}
		}
	}
	$have = $keys[ $key ] ?? array();
	$data = array();
	$new  = array();
	foreach ( $rows as $i => $row ) {
		if ( isset( $have[ $i ] ) ) {
			$data[] = array( 'range' => 'Pedidos!A' . $have[ $i ] . ':' . $last . $have[ $i ], 'values' => array( $row ) );
		} else {
			$new[] = $row;
		}
	}
	if ( $data ) {
		$r = ap_sheets_call( 'POST', '/values:batchUpdate', array( 'valueInputOption' => 'USER_ENTERED', 'data' => $data ) );
		if ( is_wp_error( $r ) ) {
			return $r;
		}
	}
	if ( $new ) {
		$r = ap_sheets_call( 'POST', '/values/' . rawurlencode( 'Pedidos!A:' . $last ) . ':append?valueInputOption=USER_ENTERED&insertDataOption=INSERT_ROWS', array( 'values' => $new ) );
		if ( is_wp_error( $r ) ) {
			return $r;
		}
		// Anota as linhas novas (a resposta diz onde caíram) para as próximas atualizações do mesmo lote.
		if ( preg_match( '/!A(\d+):/', (string) ( $r['updates']['updatedRange'] ?? '' ), $m ) ) {
			foreach ( $new as $j => $_ ) {
				$keys[ $key ][] = (int) $m[1] + $j;
			}
		}
	}
	// Sobraram linhas antigas (o pedido ficou com menos itens): limpa.
	$extra = array_slice( $have, count( $rows ) );
	if ( $extra ) {
		$ranges = array();
		foreach ( $extra as $rw ) {
			$ranges[] = 'Pedidos!A' . $rw . ':' . $last . $rw;
		}
		ap_sheets_call( 'POST', '/values:batchClear', array( 'ranges' => $ranges ) );
		$keys[ $key ] = array_slice( $have, 0, count( $rows ) );
	}
	return true;
}

/* -----------------------------------------------------------------------
 * Fila: toda mudança vira uma linha atualizada
 * -------------------------------------------------------------------- */

function ap_sheet_touch( $project_id ) {
	global $ap_sheet_pending;
	if ( ! empty( $GLOBALS['ap_sheets_pause'] ) || ! $project_id ) {
		return;
	}
	if ( ! isset( $ap_sheet_pending ) ) {
		$ap_sheet_pending = array( 'p' => array(), 'c' => false );
	}
	$ap_sheet_pending['p'][ (int) $project_id ] = true;
}

function ap_sheet_touch_costs() {
	global $ap_sheet_pending;
	if ( ! empty( $GLOBALS['ap_sheets_pause'] ) ) {
		return;
	}
	if ( ! isset( $ap_sheet_pending ) ) {
		$ap_sheet_pending = array( 'p' => array(), 'c' => false );
	}
	$ap_sheet_pending['k'] = true;
}

function ap_sheet_touch_clients() {
	global $ap_sheet_pending;
	if ( ! empty( $GLOBALS['ap_sheets_pause'] ) ) {
		return;
	}
	if ( ! isset( $ap_sheet_pending ) ) {
		$ap_sheet_pending = array( 'p' => array(), 'c' => false );
	}
	$ap_sheet_pending['c'] = true;
}

/** Chamado por ap_insert/ap_update. */
function ap_sheet_hook( $table, $id, $data = array() ) {
	if ( 'projects' === $table ) {
		ap_sheet_touch( (int) $id );
	} elseif ( 'transactions' === $table ) {
		$pid = isset( $data['project_id'] ) ? (int) $data['project_id'] : 0;
		if ( ! $pid && $id ) {
			$t   = ap_get( 'transactions', $id );
			$pid = $t ? (int) $t->project_id : 0;
		}
		ap_sheet_touch( $pid );
		$type = isset( $data['type'] ) ? $data['type'] : '';
		if ( ! $type && $id ) {
			$t    = isset( $t ) ? $t : ap_get( 'transactions', $id );
			$type = $t ? $t->type : '';
		}
		if ( 'out' === $type ) {
			ap_sheet_touch_costs();
		}
	} elseif ( 'clients' === $table || 'credits' === $table ) {
		ap_sheet_touch_clients();
	}
}

/** Junta o que mudou nesta requisição com o que ficou pendente e envia. */
add_action( 'shutdown', 'ap_sheets_flush', 99 );
function ap_sheets_flush() {
	global $ap_sheet_pending;
	$queue = get_option( 'ap_sheet_queue', array( 'p' => array(), 'c' => false, 'k' => false ) );
	$queue = is_array( $queue ) ? $queue + array( 'k' => false ) : array( 'p' => array(), 'c' => false, 'k' => false );
	if ( ! empty( $ap_sheet_pending ) && ap_sheets_enabled() ) {
		$queue['p'] = array_unique( array_merge( (array) $queue['p'], array_keys( $ap_sheet_pending['p'] ) ) );
		$queue['c'] = $queue['c'] || $ap_sheet_pending['c'];
		$queue['k'] = ! empty( $queue['k'] ) || ! empty( $ap_sheet_pending['k'] );
		update_option( 'ap_sheet_queue', $queue, false );
		$ap_sheet_pending = null;
	}
	if ( empty( $queue['p'] ) && empty( $queue['c'] ) && empty( $queue['k'] ) ) {
		return;
	}
	if ( ! ap_sheets_enabled() ) {
		return;
	}
	if ( function_exists( 'fastcgi_finish_request' ) ) {
		fastcgi_finish_request();
	}
	ap_sheets_process();
}
add_action( 'ap_hourly', 'ap_sheets_process' );
add_action( 'ap_sheets_process', 'ap_sheets_process' );

function ap_sheets_process() {
	if ( ! ap_sheets_enabled() ) {
		return 0;
	}
	$queue = get_option( 'ap_sheet_queue', array( 'p' => array(), 'c' => false, 'k' => false ) );
	if ( empty( $queue['p'] ) && empty( $queue['c'] ) && empty( $queue['k'] ) ) {
		return 0;
	}
	if ( get_transient( 'ap_sheet_lock' ) ) {
		return 0;
	}
	set_transient( 'ap_sheet_lock', 1, 60 );
	$left = array();
	$done = 0;
	$keys = null;
	foreach ( array_slice( (array) $queue['p'], 0, 40 ) as $pid ) {
		$r = ap_sheets_sync_project( $pid, $keys );
		if ( is_wp_error( $r ) ) {
			update_option( 'ap_sheet', array_merge( ap_sheets_state(), array( 'error' => $r->get_error_message(), 'error_at' => ap_now() ) ), false );
			$left = array_merge( $left, array_slice( (array) $queue['p'], $done ) );
			break;
		}
		$done++;
	}
	$left = array_unique( array_merge( $left, array_slice( (array) $queue['p'], 40 ) ) );
	$cl   = ! empty( $queue['c'] );
	if ( $cl && ! $left ) {
		$r = ap_sheets_sync_clients();
		$cl = is_wp_error( $r );
	}
	$kc = ! empty( $queue['k'] );
	if ( $kc && ! $left ) {
		$r  = ap_sheets_sync_costs();
		$kc = is_wp_error( $r );
	}
	update_option( 'ap_sheet_queue', array( 'p' => array_values( $left ), 'c' => $cl, 'k' => $kc ), false );
	if ( ! $left && ! $cl && ! $kc ) {
		$st = ap_sheets_state();
		unset( $st['error'], $st['error_at'] );
		$st['last_sync'] = ap_now();
		update_option( 'ap_sheet', $st, false );
	}
	delete_transient( 'ap_sheet_lock' );
	return $done;
}

/* -----------------------------------------------------------------------
 * Painel
 * -------------------------------------------------------------------- */

function ap_do_sheets_create() {
	ap_require( 'admin' );
	$n = ap_sheets_full();
	if ( is_wp_error( $n ) ) {
		ap_back( $n->get_error_message(), 'erro' );
	}
	ap_back( 'Planilha pronta: ' . $n . ' linha(s) de pedidos enviadas. Daqui para frente ela se atualiza sozinha.', 'ok', ap_panel_url( 'config' ) . '#google' );
}

function ap_do_sheets_off() {
	ap_require( 'admin' );
	delete_option( 'ap_sheet' );
	delete_option( 'ap_sheet_queue' );
	ap_back( 'Sincronização desligada. A planilha continua no seu Google Drive.', 'ok', ap_panel_url( 'config' ) . '#google' );
}

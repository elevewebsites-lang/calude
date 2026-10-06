<?php
/**
 * E-mail em massa para os clientes: escolhe o público, escreve e envia em lotes (sem travar o servidor).
 * Cada e-mail tem o link "não quero receber" (clients.email_optout).
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function ap_audiences_mail() {
	return array(
		'aprovados'  => 'Todos os clientes aprovados',
		'pendentes'  => 'Cadastros em análise',
		'sem-pedido' => 'Aprovados sem pedido há 30 dias',
		'nunca'      => 'Aprovados que nunca pediram',
		'todos'      => 'Todos os clientes',
		'selecao'    => 'Escolher na lista',
	);
}

function ap_audience_clients( $aud, $ids = array() ) {
	global $wpdb;
	$ct   = ap_table( 'clients' );
	$pt   = ap_table( 'projects' );
	$base = "email <> '' AND email_optout = 0";
	switch ( $aud ) {
		case 'pendentes':
			$where = "$base AND approved = 0";
			break;
		case 'sem-pedido':
			$where = $wpdb->prepare( "$base AND approved = 1 AND id NOT IN (SELECT client_id FROM $pt WHERE created_at >= %s)", gmdate( 'Y-m-d', strtotime( '-30 day' ) ) );
			break;
		case 'nunca':
			$where = "$base AND approved = 1 AND id NOT IN (SELECT client_id FROM $pt)";
			break;
		case 'todos':
			$where = $base;
			break;
		case 'selecao':
			$ids   = array_filter( array_map( 'absint', (array) $ids ) );
			$where = $ids ? $base . ' AND id IN (' . implode( ',', $ids ) . ')' : '1=0';
			break;
		default:
			$where = "$base AND approved = 1";
	}
	return $wpdb->get_results( "SELECT id, name, company, email FROM $ct WHERE $where ORDER BY company, name" ); // phpcs:ignore WordPress.DB.PreparedSQL
}

function ap_do_broadcast_save() {
	ap_require( 'emails' );
	$ids  = isset( $_POST['ids'] ) ? array_map( 'absint', (array) $_POST['ids'] ) : array(); // phpcs:ignore WordPress.Security.NonceVerification
	$aud  = isset( ap_audiences_mail()[ ap_in( 'audience' ) ] ) ? ap_in( 'audience' ) : 'aprovados';
	$list = ap_audience_clients( $aud, $ids );
	$data = array(
		'subject'    => ap_in( 'subject' ),
		'body'       => ap_in( 'body', 'html' ),
		'cta_text'   => ap_in( 'cta_text' ),
		'cta_url'    => ap_in( 'cta_url', 'url' ),
		'audience'   => $aud,
		'recipients' => wp_json_encode( wp_list_pluck( $list, 'id' ) ),
		'total'      => count( $list ),
		'created_by' => get_current_user_id(),
	);
	if ( ! $data['subject'] || ! $data['body'] ) {
		ap_back( 'Preencha o assunto e o texto.', 'erro' );
	}
	if ( ap_in( 'teste', 'bool' ) ) {
		$me = wp_get_current_user()->user_email;
		ap_broadcast_send_one( (object) $data, (object) array( 'id' => 0, 'name' => wp_get_current_user()->display_name, 'email' => $me ) );
		ap_back( 'Teste enviado para ' . $me . '.' );
	}
	if ( ! $list ) {
		ap_back( 'Nenhum cliente nesse público (ou todos pediram para não receber).', 'erro' );
	}
	$data['status'] = 'enviando';
	$id             = ap_insert( 'broadcasts', $data );
	wp_schedule_single_event( time() + 5, 'ap_broadcast_tick' );
	ap_back( 'Envio iniciado para ' . count( $list ) . ' cliente(s). Os e-mails saem em lotes nos próximos minutos.', 'ok', ap_panel_url( 'emails' ) );
}

function ap_broadcast_send_one( $b, $c ) {
	$first = $c->name ? strtok( $c->name, ' ' ) : '';
	$body  = str_replace( array( '{nome}', '{empresa}' ), array( $first, $c->company ?? '' ), (string) $b->body );
	$out   = $c->id ? '<p style="margin-top:22px;font-size:11px;color:#9a9a9a;">Não quer mais receber estes e-mails? <a href="' . esc_url( add_query_arg( array( 'ap_sair' => $c->id, 'k' => substr( hash_hmac( 'sha256', 'out' . $c->id, wp_salt() ), 0, 16 ) ), home_url( '/' ) ) ) . '" style="color:#9a9a9a;">Descadastrar</a></p>' : '';
	return ap_mail( $c->email, str_replace( '{nome}', $first, $b->subject ), str_replace( '{nome}', $first, $b->subject ), wpautop( $body ), array(), $b->cta_text, $b->cta_url, $out );
}

/**
 * Lote: 25 e-mails por minuto até acabar.
 */
add_action( 'ap_broadcast_tick', 'ap_broadcast_tick' );
add_action( 'ap_hourly', 'ap_broadcast_tick' );
function ap_broadcast_tick() {
	foreach ( ap_rows( 'broadcasts', "status = 'enviando'", array(), 'id LIMIT 1' ) as $b ) {
		$ids   = json_decode( (string) $b->recipients, true );
		$slice = array_slice( (array) $ids, (int) $b->sent + (int) $b->failed, 25 );
		$sent  = 0;
		$fail  = 0;
		foreach ( $slice as $cid ) {
			$c = ap_get( 'clients', $cid );
			if ( $c && is_email( $c->email ) && ! $c->email_optout && ap_broadcast_send_one( $b, $c ) ) {
				$sent++;
			} else {
				$fail++;
			}
		}
		$done = (int) $b->sent + $sent + (int) $b->failed + $fail >= (int) $b->total;
		ap_update( 'broadcasts', $b->id, array( 'sent' => (int) $b->sent + $sent, 'failed' => (int) $b->failed + $fail, 'status' => $done ? 'enviado' : 'enviando' ) );
		if ( ! $done ) {
			wp_schedule_single_event( time() + 60, 'ap_broadcast_tick' );
		}
	}
}

/**
 * Link "descadastrar".
 */
add_action(
	'init',
	function () {
		if ( empty( $_GET['ap_sair'] ) || empty( $_GET['k'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification
			return;
		}
		$id = absint( $_GET['ap_sair'] ); // phpcs:ignore WordPress.Security.NonceVerification
		if ( hash_equals( substr( hash_hmac( 'sha256', 'out' . $id, wp_salt() ), 0, 16 ), sanitize_text_field( wp_unslash( $_GET['k'] ) ) ) ) { // phpcs:ignore WordPress.Security.NonceVerification
			ap_update( 'clients', $id, array( 'email_optout' => 1 ) );
			wp_die( 'Pronto: você não vai mais receber os e-mails de novidades. Os avisos dos seus pedidos continuam chegando.', 'Descadastrado', array( 'response' => 200 ) );
		}
	}
);

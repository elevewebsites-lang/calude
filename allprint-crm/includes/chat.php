<?php
/**
 * Chat cliente ↔ equipe, por cliente, com o pedido do assunto (ou "geral").
 * Sininho: contador de mensagens não lidas no topo do painel e da área do cliente (atualiza a cada 20 s).
 * E-mail: a equipe recebe quando chega mensagem (no máx. 1 a cada 15 min por cliente); o cliente recebe quando a equipe responde.
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_action(
	'rest_api_init',
	function () {
		$who = function () {
			return is_user_logged_in() && ( ap_is_team() || ap_current_client() );
		};
		register_rest_route( 'ap/v1', '/chat', array( array( 'methods' => 'GET', 'callback' => 'ap_api_chat_get', 'permission_callback' => $who ), array( 'methods' => 'POST', 'callback' => 'ap_api_chat_send', 'permission_callback' => $who ) ) );
		register_rest_route( 'ap/v1', '/chat/unread', array( 'methods' => 'GET', 'callback' => 'ap_api_chat_unread', 'permission_callback' => $who ) );
		register_rest_route( 'ap/v1', '/chat/hub', array( 'methods' => 'GET', 'callback' => 'ap_api_chat_hub', 'permission_callback' => $who ) );
	}
);

/**
 * Cliente da conversa: o próprio cliente logado, ou o escolhido pela equipe.
 */
function ap_chat_client( $requested = 0 ) {
	if ( ap_is_team() ) {
		return $requested ? ap_get( 'clients', $requested ) : null;
	}
	return ap_current_client();
}

function ap_chat_unread_count( $client_id = 0 ) {
	global $wpdb;
	$t = ap_table( 'messages' );
	if ( ap_is_team() ) {
		return (int) $wpdb->get_var( "SELECT COUNT(*) FROM $t WHERE from_client = 1 AND read_at IS NULL" ); // phpcs:ignore WordPress.DB.PreparedSQL
	}
	return (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM $t WHERE client_id = %d AND from_client = 0 AND read_at IS NULL", $client_id ) ); // phpcs:ignore WordPress.DB.PreparedSQL
}

function ap_api_chat_unread() {
	$c = ap_is_team() ? null : ap_current_client();
	return array( 'n' => ap_chat_unread_count( $c ? $c->id : 0 ) );
}

function ap_chat_thread( $client_id ) {
	$rows = ap_rows( 'messages', 'client_id = %d', array( $client_id ), 'id DESC LIMIT 200' );
	$out  = array();
	foreach ( array_reverse( $rows ) as $m ) {
		$u     = $m->user_id ? get_userdata( $m->user_id ) : null;
		$out[] = array(
			'id'      => (int) $m->id,
			'mine'    => ap_is_team() ? ! $m->from_client : (bool) $m->from_client,
			'who'     => $m->from_client ? 'Cliente' : ( $u ? $u->display_name : ap_setting( 'empresa' ) ),
			'body'    => $m->body,
			'project' => (int) $m->project_id,
			'at'      => ap_date( $m->created_at, 'd/m H:i' ),
			'read'    => (bool) $m->read_at,
		);
	}
	return $out;
}

function ap_api_chat_get( WP_REST_Request $r ) {
	$c = ap_chat_client( absint( $r['client'] ) );
	if ( ! $c ) {
		return new WP_Error( 'ap', 'Conversa não encontrada.', array( 'status' => 404 ) );
	}
	global $wpdb;
	// Marca como lidas as mensagens do outro lado.
	$wpdb->query( $wpdb->prepare( 'UPDATE ' . ap_table( 'messages' ) . ' SET read_at = %s WHERE client_id = %d AND from_client = %d AND read_at IS NULL', ap_now(), $c->id, ap_is_team() ? 1 : 0 ) ); // phpcs:ignore WordPress.DB.PreparedSQL
	$projects = array();
	foreach ( array_slice( ap_projects( 'p.client_id = %d AND p.archived = 0', array( $c->id ) ), 0, 30 ) as $p ) {
		$projects[] = array( 'id' => (int) $p->id, 'title' => 'Pedido #' . $p->id . ' · ' . $p->title );
	}
	return array( 'messages' => ap_chat_thread( $c->id ), 'unread' => ap_chat_unread_count( $c->id ), 'projects' => $projects, 'name' => ap_is_team() ? ap_client_label( $c ) : ap_setting( 'empresa' ) );
}

function ap_api_chat_send( WP_REST_Request $r ) {
	$c    = ap_chat_client( absint( $r['client'] ) );
	$body = trim( sanitize_textarea_field( (string) $r['body'] ) );
	if ( ! $c || '' === $body ) {
		return new WP_Error( 'ap', 'Escreva a mensagem.', array( 'status' => 400 ) );
	}
	$pid = absint( $r['project'] );
	if ( $pid ) {
		$p = ap_get( 'projects', $pid );
		if ( ! $p || (int) $p->client_id !== (int) $c->id ) {
			$pid = 0;
		}
	}
	$team = ap_is_team();
	ap_insert(
		'messages',
		array(
			'client_id'   => $c->id,
			'project_id'  => $pid,
			'user_id'     => get_current_user_id(),
			'from_client' => $team ? 0 : 1,
			'body'        => mb_substr( $body, 0, 4000 ),
		)
	);
	if ( $pid ) {
		ap_log( $pid, ( $team ? 'Equipe' : 'Cliente' ) . ' no chat: ' . mb_substr( $body, 0, 160 ), true );
	}
	ap_chat_email( $c, $body, $pid, $team );
	return array( 'messages' => ap_chat_thread( $c->id ) );
}

function ap_chat_email( $client, $body, $pid, $from_team ) {
	$key = 'ap_chatmail_' . ( $from_team ? 'c' : 't' ) . $client->id;
	if ( get_transient( $key ) ) {
		return;
	}
	set_transient( $key, 1, 15 * MINUTE_IN_SECONDS );
	$ref = $pid ? ' (pedido #' . $pid . ')' : '';
	if ( $from_team ) {
		if ( is_email( $client->email ) ) {
			ap_mail( $client->email, 'Nova mensagem da ' . ap_setting( 'empresa' ) . $ref, 'Você tem uma mensagem nova', '<p style="padding:12px 14px;background:#f5f5f7;border-radius:10px;">' . nl2br( esc_html( mb_substr( $body, 0, 600 ) ) ) . '</p>', array(), 'Responder', ap_client_entry_url( $client, 'mensagens' ) );
		}
		return;
	}
	$to = ap_setting( 'email' ) ? ap_setting( 'email' ) : get_option( 'admin_email' );
	if ( is_email( $to ) ) {
		ap_mail( $to, 'Mensagem de ' . ap_client_label( $client ) . $ref, 'Mensagem de ' . ap_client_label( $client ), '<p style="padding:12px 14px;background:#f5f5f7;border-radius:10px;">' . nl2br( esc_html( mb_substr( $body, 0, 600 ) ) ) . '</p>', array(), 'Responder no painel', ap_panel_url( 'mensagens', 0, array( 'cliente' => $client->id ) ) );
	}
}

/**
 * Conversas para a caixa de entrada da equipe (última mensagem + não lidas).
 */
function ap_chat_inbox() {
	global $wpdb;
	$t = ap_table( 'messages' );
	return $wpdb->get_results( "SELECT client_id, MAX(id) last_id, SUM(from_client = 1 AND read_at IS NULL) unread FROM $t GROUP BY client_id ORDER BY last_id DESC LIMIT 100" ); // phpcs:ignore WordPress.DB.PreparedSQL
}

/**
 * Sininho (HTML): vai no topo do painel e da área do cliente.
 */
function ap_bell_html() {
	$c   = ap_is_team() ? null : ap_current_client();
	$n   = ap_chat_unread_count( $c ? $c->id : 0 );
	$url = ap_is_team() ? ap_panel_url( 'mensagens' ) : ap_client_link( 'mensagens' );
	return '<a class="bell" href="' . esc_url( $url ) . '" data-bell title="Mensagens">' . ap_icon( 'sino', 18 ) . '<em class="bell-n"' . ( $n ? '' : ' hidden' ) . '>' . (int) $n . '</em></a>';
}

/* -----------------------------------------------------------------------
 * Bolinha de mensagens (como no LDK): canto de baixo, à direita, em todas as telas.
 *   - cliente: conversa direto com a equipe, escolhendo o pedido do assunto (ou "geral");
 *   - equipe (com acesso a Clientes): lista das conversas, responde na própria janela.
 * Chegou mensagem nova: contador, aviso na tela com a prévia, som curto e título piscando
 * (e notificação do sistema se a aba estiver escondida e a pessoa deixou).
 * -------------------------------------------------------------------- */

function ap_hub_enabled() {
	if ( ! is_user_logged_in() || 'mensagens' === get_query_var( 'ap_section' ) ) {
		return false;
	}
	$route = get_query_var( 'ap_route' );
	if ( 'panel' === $route ) {
		return ap_can( 'clientes' );
	}
	// Na área do cliente, só o cliente de verdade (a equipe em "ver como cliente" não conversa por ele).
	return 'client' === $route && ! ap_is_team() && ap_current_client();
}

function ap_api_chat_hub() {
	global $wpdb;
	$t    = ap_table( 'messages' );
	$team = ap_is_team();
	if ( $team ) {
		$last = $wpdb->get_row( "SELECT * FROM $t WHERE from_client = 1 AND read_at IS NULL ORDER BY id DESC LIMIT 1" ); // phpcs:ignore WordPress.DB.PreparedSQL
		$out  = array( 'n' => ap_chat_unread_count(), 'convs' => array() );
		foreach ( array_slice( ap_chat_inbox(), 0, 15 ) as $row ) {
			$c = ap_get( 'clients', $row->client_id );
			$m = ap_get( 'messages', $row->last_id );
			if ( ! $c || ! $m ) {
				continue;
			}
			$out['convs'][] = array(
				'id'     => (int) $c->id,
				'name'   => ap_client_label( $c ),
				'ini'    => ap_initials( ap_client_label( $c ) ),
				'last'   => ( $m->from_client ? '' : 'Você: ' ) . wp_trim_words( (string) $m->body, 10 ),
				'at'     => ap_ago( $m->created_at ),
				'unread' => (int) $row->unread,
			);
		}
	} else {
		$c    = ap_current_client();
		$last = $c ? $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $t WHERE client_id = %d AND from_client = 0 AND read_at IS NULL ORDER BY id DESC LIMIT 1", $c->id ) ) : null; // phpcs:ignore WordPress.DB.PreparedSQL
		$out  = array( 'n' => ap_chat_unread_count( $c ? $c->id : 0 ) );
	}
	if ( $last ) {
		$lc          = ap_get( 'clients', $last->client_id );
		$out['last'] = array(
			'id'     => (int) $last->id,
			'client' => (int) $last->client_id,
			'name'   => $team ? ap_client_label( $lc ) : ap_setting( 'empresa' ),
			'body'   => wp_trim_words( (string) $last->body, 18 ),
		);
	}
	return $out;
}

function ap_hub_html() {
	if ( ! ap_hub_enabled() ) {
		return '';
	}
	$team = ap_is_team();
	$full = $team ? ap_panel_url( 'mensagens' ) : ap_client_link( 'mensagens' );
	$me   = $team ? 0 : (int) ap_current_client()->id;
	$x    = '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M18 6 6 18M6 6l12 12"/></svg>';
	ob_start();
	?>
<div class="hub" data-hub data-team="<?php echo $team ? '1' : '0'; ?>" data-client="<?php echo (int) $me; ?>">
	<button type="button" class="hub-toast" data-hub-toast hidden><span class="hub-av" data-hub-toast-av></span><span class="hub-rt"><strong data-hub-toast-name></strong><small data-hub-toast-body></small></span></button>
	<button type="button" class="hub-fab" data-hub-fab aria-label="<?php echo $team ? 'Mensagens dos parceiros' : 'Mandar mensagem para a ' . esc_attr( ap_setting( 'empresa' ) ); ?>" aria-expanded="false"><?php echo ap_icon( 'chat', 24 ); // phpcs:ignore ?><em class="hub-n" data-hub-n hidden></em></button>
	<section class="hub-win" data-hub-win hidden role="dialog" aria-label="Mensagens">
		<header class="hub-head">
			<button type="button" class="hub-ic" data-hub-back hidden aria-label="Voltar"><?php echo ap_icon( 'voltar', 18 ); // phpcs:ignore ?></button>
			<span class="hub-title"><strong data-hub-title><?php echo $team ? 'Mensagens dos parceiros' : esc_html( ap_setting( 'empresa' ) ); ?></strong><small data-hub-sub><?php echo $team ? 'Responda direto por aqui' : 'Respondemos por aqui e avisamos por e-mail'; ?></small></span>
			<a class="hub-ic" href="<?php echo esc_url( $full ); ?>" title="Abrir em tela cheia" aria-label="Abrir em tela cheia"><svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M15 3h6v6M9 21H3v-6M21 3l-7 7M3 21l7-7"/></svg></a>
			<button type="button" class="hub-ic" data-hub-close aria-label="Fechar"><?php echo $x; // phpcs:ignore ?></button>
		</header>
		<?php if ( $team ) : ?>
			<div class="hub-home" data-hub-home><p class="muted small hub-pad">Carregando…</p></div>
		<?php endif; ?>
		<div class="hub-thread" data-hub-thread<?php echo $team ? ' hidden' : ''; ?>>
			<div class="hub-list" data-hub-list><p class="muted small center">Carregando…</p></div>
			<form class="hub-form" data-hub-form>
				<select data-hub-project aria-label="Assunto"><option value="0">Assunto: geral</option></select>
				<div class="hub-row-in">
					<textarea rows="1" data-hub-text placeholder="<?php echo $team ? 'Responder…' : 'Escreva sua mensagem ou observação…'; ?>"></textarea>
					<button type="submit" class="hub-send" aria-label="Enviar"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m22 2-7 20-4-9-9-4z"/><path d="M22 2 11 13"/></svg></button>
				</div>
			</form>
		</div>
	</section>
</div>
	<?php
	return ob_get_clean();
}

<?php
/**
 * Planejamento do mês (por cliente).
 *
 * Social media e atendimento montam as artes do mês (título + legenda + dia) na etapa "Planejamento".
 * "Enviar planejamento" gera um link /planejamento/<token>/ (e-mail + WhatsApp) em que o cliente vê tudo,
 * comenta arte por arte e aprova — ou pede ajustes. Aprovado: os posts vão para o Design e o designer é avisado.
 * A equipe também pode aprovar manualmente (o cliente aprovou por fora).
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function lk_plan_get( $id ) {
	static $cache = array();
	$id = (int) $id;
	if ( ! array_key_exists( $id, $cache ) ) {
		$cache[ $id ] = $id ? lk_get( 'plans', $id ) : null;
	}
	return $cache[ $id ];
}

function lk_plan_status_label( $status ) {
	$map = array(
		'rascunho' => 'montando',
		'revisao'  => 'em revisão (interna)',
		'enviado'  => 'aguardando o cliente',
		'ajustes'  => 'cliente pediu ajustes',
		'aprovado' => 'aprovado',
	);
	return $map[ $status ] ?? $status;
}

function lk_plan_for( $client_id, $ym ) {
	$rows = lk_rows( 'plans', 'client_id = %d AND period = %s', array( $client_id, $ym ), 'id DESC LIMIT 1' );
	return $rows ? $rows[0] : null;
}

function lk_plan_url( $plan ) {
	return lk_url( 'planejamento/' . $plan->token );
}

/**
 * Posts do mês do cliente (todos, em ordem de data).
 */
function lk_plan_month_posts( $client_id, $ym ) {
	return lk_posts( 'p.client_id = %d AND p.scheduled_at BETWEEN %s AND %s', array( $client_id, $ym . '-01 00:00:00', gmdate( 'Y-m-t', strtotime( $ym . '-01' ) ) . ' 23:59:59' ), 'p.scheduled_at, p.id' );
}

/**
 * Posts que o cliente vê no link: os do planejamento (na etapa de planejamento ou já aprovados nele).
 */
function lk_plan_posts( $plan ) {
	return lk_posts( 'p.plan_id = %d', array( $plan->id ), 'p.scheduled_at, p.id' );
}

function lk_plan_message( $plan, $client ) {
	$first = $client->name ? strtok( $client->name, ' ' ) : '';
	return 'Olá' . ( $first ? ', ' . $first : '' ) . '! 😊 O planejamento de conteúdo de ' . lk_month_label( $plan->period ) . ' está pronto. Dá uma olhada nos temas e nas legendas, comente o que quiser e aprove por aqui: ' . lk_plan_url( $plan );
}

/**
 * Quem revisa o planejamento antes de ir para o cliente (Configurações → Conteúdo). Padrão: o primeiro administrador.
 */
function lk_plan_reviewer() {
	$id = (int) lk_setting( 'revisor_planejamento' );
	if ( $id && get_userdata( $id ) ) {
		return $id;
	}
	$admins = get_users( array( 'role' => 'administrator', 'number' => 1, 'orderby' => 'ID', 'fields' => 'ID' ) );
	return $admins ? (int) $admins[0] : 0;
}

function lk_is_plan_reviewer() {
	return get_current_user_id() === lk_plan_reviewer() || lk_is_admin();
}

/**
 * Plano do mês (cria em rascunho se ainda não existe), com as datas comemorativas já nas considerações.
 */
function lk_plan_ensure( $client, $ym ) {
	$plan = lk_plan_for( $client->id, $ym );
	if ( $plan ) {
		return $plan;
	}
	$datas = lk_plan_datas( $ym );
	$id    = lk_insert(
		'plans',
		array(
			'client_id'     => $client->id,
			'period'        => $ym,
			'token'         => strtolower( wp_generate_password( 24, false ) ),
			'status'        => 'rascunho',
			'intro'         => 'Preparamos o conteúdo de ' . lk_month_label( $ym ) . ' pensando no momento da ' . lk_client_label( $client ) . ': datas importantes, o que o seu público mais procura e as ações do mês.',
			'consideracoes' => $datas ? "Datas do mês:\n" . implode( "\n", $datas ) : '',
			'created_by'    => get_current_user_id(),
		)
	);
	if ( function_exists( 'lk_plan_notify_holidays' ) ) {
		lk_plan_notify_holidays( $client, $ym );
	}
	return lk_get( 'plans', $id );
}

/**
 * ▶ Start: abre (ou cria) o planejamento do mês do cliente.
 */
function lk_do_plan_start() {
	lk_require( 'conteudo' );
	$client = lk_get( 'clients', lk_in( 'client_id', 'int' ) );
	$ym     = preg_match( '/^\d{4}-\d{2}$/', lk_in( 'mes' ) ) ? lk_in( 'mes' ) : gmdate( 'Y-m', strtotime( current_time( 'Y-m' ) . '-01 +1 month' ) );
	if ( ! $client ) {
		lk_back( 'Escolha o cliente.', 'erro' );
	}
	lk_plan_ensure( $client, $ym );
	wp_safe_redirect( lk_panel_url( 'planejamento', 0, array( 'cliente' => $client->id, 'mes' => $ym ) ) );
	exit;
}

/**
 * Texto de abertura, considerações do mês e pontos importantes (o que a cliente vê no topo).
 */
function lk_do_plan_meta_save() {
	lk_require( 'conteudo' );
	$plan = lk_get( 'plans', lk_in( 'id', 'int' ) );
	if ( ! $plan ) {
		lk_back( 'Planejamento não encontrado.', 'erro' );
	}
	lk_update(
		'plans',
		$plan->id,
		array(
			'intro'         => lk_in( 'intro', 'textarea' ),
			'consideracoes' => lk_in( 'consideracoes', 'textarea' ),
			'destaques'     => lk_in( 'destaques', 'textarea' ),
		)
	);
	lk_back( 'Informações do planejamento salvas.' );
}

/**
 * Aviso de pacote: faltando ou sobrando artes no mês.
 */
function lk_plan_quota_alert( $client, $ym ) {
	if ( ! $client || (int) $client->posts_quota <= 0 ) {
		return '';
	}
	$n = lk_client_month_count( $client->id, $ym );
	$q = (int) $client->posts_quota;
	if ( $n < $q ) {
		return 'Faltam ' . ( $q - $n ) . ' arte(s) para fechar o pacote de ' . $q . ' do mês.';
	}
	if ( $n > $q ) {
		return 'Passou ' . ( $n - $q ) . ' arte(s) do pacote de ' . $q . ' do mês. Confirme com o atendimento se é extra.';
	}
	return '';
}

/**
 * 1) A equipe termina de montar → vai para a revisão interna (Marília). Quem é a revisora pode mandar direto.
 */
function lk_do_plan_send() {
	lk_require( 'conteudo' );
	$client = lk_get( 'clients', lk_in( 'client_id', 'int' ) );
	$ym     = preg_match( '/^\d{4}-\d{2}$/', lk_in( 'mes' ) ) ? lk_in( 'mes' ) : '';
	if ( ! $client || ! $ym ) {
		lk_back( 'Escolha o cliente e o mês.', 'erro' );
	}
	$todo = lk_plan_pending_posts( $client, $ym );
	if ( ! $todo ) {
		lk_back( 'Não há conteúdos para enviar (crie os posts do mês no planejamento).', 'erro' );
	}
	$plan = lk_plan_ensure( $client, $ym );
	foreach ( $todo as $p ) {
		lk_update( 'posts', $p->id, array( 'plan_id' => $plan->id ) );
	}
	if ( lk_in( 'direto', 'bool' ) && lk_is_plan_reviewer() ) {
		$gate = lk_plan_review_gate( $client, $ym );
		if ( $gate ) {
			lk_back( $gate, 'erro' );
		}
		lk_update( 'plans', $plan->id, array( 'reviewed_by' => get_current_user_id(), 'reviewed_at' => lk_now(), 'review_note' => '' ) );
		lk_plan_send_client( lk_get( 'plans', $plan->id ) );
		lk_back( 'Revisado e enviado para a cliente.' );
	}
	lk_update( 'plans', $plan->id, array( 'status' => 'revisao', 'review_note' => '' ) );
	$url = lk_panel_url( 'planejamento', 0, array( 'cliente' => $client->id, 'mes' => $ym ) );
	lk_notify( lk_plan_reviewer(), '👀 Planejamento para revisar: ' . lk_client_label( $client ) . ' · ' . lk_month_label( $ym ) . ' (' . count( $todo ) . ' conteúdos).', $url );
	$alert = lk_plan_quota_alert( $client, $ym );
	lk_back( 'Enviado para a revisão de ' . ( get_userdata( lk_plan_reviewer() ) ? get_userdata( lk_plan_reviewer() )->display_name : 'quem revisa' ) . '.' . ( $alert ? ' ⚠️ ' . $alert : '' ), $alert ? 'warn' : 'ok' );
}

/**
 * Conteúdos do mês que ainda estão no planejamento e não foram aprovados pela cliente.
 */
function lk_plan_pending_posts( $client, $ym ) {
	return array_values(
		array_filter(
			lk_plan_month_posts( $client->id, $ym ),
			function ( $p ) {
				return $p->stage === lk_stage_for( 'planejamento' ) && 'ok' !== $p->plan_status;
			}
		)
	);
}

/**
 * 2) A revisora dá o ok → vai automático para a cliente (painel dela + e-mail; WhatsApp em 1 clique).
 */
function lk_do_plan_review_ok() {
	lk_require( 'conteudo' );
	$plan = lk_get( 'plans', lk_in( 'id', 'int' ) );
	if ( ! $plan || ! lk_is_plan_reviewer() ) {
		lk_back( 'Só quem revisa o planejamento pode aprovar.', 'erro' );
	}
	$gate = lk_plan_review_gate( lk_get( 'clients', $plan->client_id ), $plan->period );
	if ( $gate ) {
		lk_back( $gate, 'erro' );
	}
	lk_update( 'plans', $plan->id, array( 'reviewed_by' => get_current_user_id(), 'reviewed_at' => lk_now(), 'review_note' => '' ) );
	lk_plan_send_client( lk_get( 'plans', $plan->id ) );
	$client = lk_get( 'clients', $plan->client_id );
	foreach ( array_unique( array_filter( array( (int) $client->social_id, (int) $client->atendimento_id, (int) $plan->created_by ) ) ) as $uid ) {
		lk_notify( $uid, '✅ Planejamento de ' . lk_client_label( $client ) . ' revisado e enviado para a cliente.', lk_panel_url( 'planejamento', 0, array( 'cliente' => $client->id, 'mes' => $plan->period ) ) );
	}
	lk_back( 'Revisado ✓ e enviado para a cliente (painel + e-mail). Reforce pelo WhatsApp.' );
}

/**
 * A revisora devolve para a equipe com uma observação.
 */
function lk_do_plan_review_back() {
	lk_require( 'conteudo' );
	$plan = lk_get( 'plans', lk_in( 'id', 'int' ) );
	if ( ! $plan || ! lk_is_plan_reviewer() ) {
		lk_back( 'Só quem revisa o planejamento pode devolver.', 'erro' );
	}
	$note   = lk_in( 'nota', 'textarea' );
	$client = lk_get( 'clients', $plan->client_id );
	lk_update( 'plans', $plan->id, array( 'status' => 'rascunho', 'review_note' => $note ) );
	foreach ( array_unique( array_filter( array( (int) $client->social_id, (int) $client->atendimento_id, (int) $plan->created_by ) ) ) as $uid ) {
		lk_notify( $uid, '↩️ Planejamento de ' . lk_client_label( $client ) . ' voltou da revisão: ' . wp_trim_words( $note, 18 ), lk_panel_url( 'planejamento', 0, array( 'cliente' => $client->id, 'mes' => $plan->period ) ) );
	}
	lk_back( 'Devolvido para a equipe ajustar.' );
}

/**
 * Envia para a cliente: status "enviado", e-mail com o link e WhatsApp pronto (1 clique).
 */
function lk_plan_send_client( $plan ) {
	$client = lk_get( 'clients', $plan->client_id );
	$todo   = lk_plan_pending_posts( $client, $plan->period );
	foreach ( $todo as $p ) {
		lk_update( 'posts', $p->id, array( 'plan_id' => $plan->id, 'plan_status' => '' ) );
		lk_post_log( $p->id, $plan->sent_at ? 'Planejamento reenviado à cliente.' : 'Planejamento enviado à cliente.' );
	}
	lk_update( 'plans', $plan->id, array( 'status' => 'enviado', 'sent_at' => lk_now() ) );
	if ( is_email( $client->email ) && ! $client->email_optout ) {
		lk_mail(
			$client->email,
			'Planejamento de ' . lk_month_label( $plan->period ),
			'Seu planejamento de ' . lk_month_label( $plan->period ),
			'<p>Olá, ' . esc_html( strtok( (string) $client->name, ' ' ) ) . '! O planejamento de conteúdo de <strong>' . esc_html( lk_month_label( $plan->period ) ) . '</strong> está pronto. Veja cada post, aprove com um 👍 ou peça ajuste no que quiser.</p>',
			array( array( 'Conteúdos no mês', (string) count( lk_plan_posts( $plan ) ) ) ),
			'Ver o planejamento',
			lk_plan_url( $plan )
		);
	}
	$wa = $client->whatsapp ? lk_wa_link( $client->whatsapp, lk_plan_message( $plan, $client ) ) : '';
	set_transient( 'lk_last_plan_' . get_current_user_id(), array( 'plan' => $plan->id, 'wa' => $wa ), 600 );
	return $wa;
}

/**
 * Datas comemorativas do mês (para lembrar no planejamento).
 */
function lk_plan_datas( $ym ) {
	$out = array();
	foreach ( lk_month_dates( $ym ) as $d ) {
		$out[] = ( $d['date'] ? substr( $d['date'], 8, 2 ) . '/' . substr( $d['date'], 5, 2 ) . ' · ' : '' ) . $d['name'] . ( 'feriado' === $d['type'] ? ' (feriado' . ( $d['sub'] ? ' · ' . $d['sub'] : '' ) . ')' : '' );
	}
	return $out;
}

/**
 * Aprovar (cliente ou equipe): as artes do planejamento vão para o Design.
 */
function lk_plan_approve( $plan, $by_client = true ) {
	$client = lk_get( 'clients', $plan->client_id );
	lk_update( 'plans', $plan->id, array( 'status' => 'aprovado', 'approved_at' => lk_now(), 'answered_at' => lk_now() ) );
	$n = 0;
	foreach ( lk_plan_posts( $plan ) as $p ) {
		if ( $p->stage === lk_stage_for( 'planejamento' ) ) {
			lk_update( 'posts', $p->id, array( 'plan_status' => 'ok' ) );
			lk_post_move( $p, lk_stage_for( 'design' ), $by_client ? 'Planejamento aprovado pelo cliente.' : 'Planejamento aprovado manualmente por ' . wp_get_current_user()->display_name . '.' );
			$n++;
		}
	}
	$msg = '✅ ' . lk_client_label( $client ) . ' aprovou o planejamento de ' . lk_month_label( $plan->period ) . ' (' . $n . ' artes foram para o design).';
	foreach ( array_unique( array_filter( array( (int) $client->social_id, (int) $client->atendimento_id ) ) ) as $uid ) {
		lk_notify( $uid, $msg, lk_panel_url( 'planejamento', 0, array( 'cliente' => $client->id, 'mes' => $plan->period ) ) );
	}
	return $n;
}

function lk_do_plan_approve_manual() {
	lk_require( 'conteudo' );
	$plan = lk_get( 'plans', lk_in( 'id', 'int' ) );
	if ( ! $plan ) {
		lk_back();
	}
	$n = lk_plan_approve( $plan, false );
	lk_back( 'Planejamento aprovado. ' . $n . ' arte(s) foram para o design.' );
}

/**
 * Resposta da cliente na página do planejamento: 👍 ou ✏️ em cada post.
 * Aprovados vão direto para o Design; os com ajuste ficam no planejamento e a equipe é avisada.
 */
function lk_plan_answer( $plan, $decision, $comments, $general, $decs = array() ) {
	$client = lk_get( 'clients', $plan->client_id );
	$by     = ! lk_is_team();
	$posts  = lk_plan_posts( $plan );
	$ok     = 0;
	$adj    = 0;
	foreach ( $posts as $p ) {
		if ( $p->stage !== lk_stage_for( 'planejamento' ) || 'ok' === $p->plan_status ) {
			continue;
		}
		$body = isset( $comments[ $p->id ] ) ? sanitize_textarea_field( wp_unslash( $comments[ $p->id ] ) ) : '';
		$dec  = 'aprovar' === $decision ? 'ok' : ( isset( $decs[ $p->id ] ) ? sanitize_key( $decs[ $p->id ] ) : 'ok' );
		if ( '' !== trim( $body ) ) {
			lk_insert( 'post_comments', array( 'post_id' => (int) $p->id, 'user_id' => get_current_user_id(), 'from_client' => $by ? 1 : 0, 'target' => 'planejamento', 'body' => $body ) );
		}
		if ( 'ajuste' === $dec ) {
			lk_update( 'posts', $p->id, array( 'plan_status' => 'ajuste' ) );
			lk_post_log( $p->id, 'A cliente pediu ajuste no planejamento.' );
			$adj++;
		} else {
			lk_update( 'posts', $p->id, array( 'plan_status' => 'ok' ) );
			lk_post_move( $p, lk_stage_for( 'design' ), $by ? 'Aprovado pela cliente no planejamento.' : 'Aprovado no planejamento por ' . wp_get_current_user()->display_name . '.' );
			$ok++;
		}
	}
	if ( $general ) {
		lk_update( 'plans', $plan->id, array( 'client_notes' => trim( ( $plan->client_notes ? $plan->client_notes . "\n\n" : '' ) . lk_date( lk_now(), 'd/m' ) . ': ' . $general ) ) );
	}
	$url  = lk_panel_url( 'planejamento', 0, array( 'cliente' => $client->id, 'mes' => $plan->period ) );
	$team = array_unique( array_filter( array( (int) $client->social_id, (int) $client->atendimento_id, (int) $plan->created_by ) ) );
	if ( $adj ) {
		lk_update( 'plans', $plan->id, array( 'status' => 'ajustes', 'answered_at' => lk_now() ) );
		foreach ( $team as $uid ) {
			lk_notify( $uid, '✏️ ' . lk_client_label( $client ) . ' aprovou ' . $ok . ' e pediu ajuste em ' . $adj . ' post(s) do planejamento de ' . lk_month_label( $plan->period ) . '.', $url );
		}
		return 'Obrigado! ' . ( $ok ? $ok . ' post(s) aprovado(s) já foram para a criação. ' : '' ) . 'Vamos ajustar ' . $adj . ' post(s) e mandar de novo para você.';
	}
	lk_update( 'plans', $plan->id, array( 'status' => 'aprovado', 'approved_at' => lk_now(), 'answered_at' => lk_now() ) );
	foreach ( $team as $uid ) {
		lk_notify( $uid, '✅ ' . lk_client_label( $client ) . ' aprovou o planejamento de ' . lk_month_label( $plan->period ) . ' (' . $ok . ' conteúdos foram para o design).', $url );
	}
	return 'Planejamento aprovado! 🎉 Nossa equipe já começou a criar as artes. Você recebe cada uma para aprovar antes de publicar.';
}

add_action(
	'init',
	function () {
		add_rewrite_rule( '^planejamento/([A-Za-z0-9]+)/?$', 'index.php?lk_route=plan&lk_token=$matches[1]', 'top' );
	}
);

add_action(
	'template_redirect',
	function () {
		if ( 'plan' !== get_query_var( 'lk_route' ) ) {
			return;
		}
		nocache_headers();
		header( 'X-Robots-Tag: noindex, nofollow', true );
		$rows = lk_rows( 'plans', 'token = %s', array( sanitize_text_field( get_query_var( 'lk_token' ) ) ) );
		$plan = $rows ? $rows[0] : null;
		if ( ! $plan || ( in_array( $plan->status, array( 'rascunho', 'revisao' ), true ) && ! lk_is_team() ) ) {
			lk_render( 'public/indisponivel' );
		}
		$msg = '';
		if ( 'POST' === ( isset( $_SERVER['REQUEST_METHOD'] ) ? $_SERVER['REQUEST_METHOD'] : '' ) && isset( $_POST['_wpnonce'] ) && wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['_wpnonce'] ) ), 'lk_plan_' . $plan->id ) ) { // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
			if ( 'enviado' === $plan->status ) {
				$msg  = lk_plan_answer( $plan, lk_in( 'decisao' ), isset( $_POST['comentario'] ) ? (array) $_POST['comentario'] : array(), lk_in( 'geral', 'textarea' ), isset( $_POST['dec'] ) ? (array) $_POST['dec'] : array() ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
				$plan = lk_get( 'plans', $plan->id );
			} else {
				$msg = 'Este planejamento já foi respondido. Obrigado!';
			}
		}
		lk_render( 'public/planejamento', array( 'plan' => $plan, 'msg' => $msg ) );
	},
	0
);

/**
 * O que o cliente tem para aprovar agora: artes + planejamentos.
 */
function lk_client_pending( $client ) {
	$posts = lk_posts( 'p.client_id = %d AND p.client_status = %s', array( $client->id, 'pendente' ), 'p.scheduled_at' );
	$plans = lk_rows( 'plans', 'client_id = %d AND status = %s', array( $client->id, 'enviado' ), 'period' );
	return array( $posts, $plans );
}

/**
 * Sininho da área do cliente: artes e planejamentos esperando aprovação.
 */
function lk_client_alerts_html( $client ) {
	list( $posts, $plans ) = lk_client_pending( $client );
	$n = count( $posts ) + count( $plans );
	return '<a class="bell bell--ap" href="' . esc_url( lk_client_link( 'aprovacoes' ) ) . '" title="' . esc_attr( $n ? $n . ' para aprovar' : 'Nada para aprovar' ) . '">' . lk_icon( 'check', 18 ) . '<em class="bell-n"' . ( $n ? '' : ' hidden' ) . '>' . (int) $n . '</em></a>';
}

function lk_dow_short( $date ) {
	$d = array( 'dom', 'seg', 'ter', 'qua', 'qui', 'sex', 'sáb' );
	return $date ? $d[ (int) gmdate( 'w', strtotime( $date ) ) ] : '';
}

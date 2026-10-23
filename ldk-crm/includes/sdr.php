<?php
/**
 * Time de SDR (pré-vendas com IA).
 *
 * Cada SDR é um agente com nicho + cidade + oferta. Sozinho (a cada hora) ele:
 *   1. busca empresas novas no Google (a mesma busca da Prospecção), sem repetir quem já está no funil ou é cliente;
 *   2. lê o site da empresa (e-mail e Instagram), dá uma nota de 0 a 10 para o encaixe e explica o porquê;
 *   3. cria o lead no funil (origem "SDR IA · <nome>") e escreve a primeira mensagem (WhatsApp ou e-mail);
 *   4. depois de X dias sem resposta, escreve o follow-up (até N), e para quando o lead responde.
 *
 * Nada sai sozinho: tudo cai na fila /painel/sdr e alguém da equipe aprova, edita ou descarta.
 * E-mail sai pelo próprio CRM; WhatsApp abre a conversa com o texto pronto (o envio é da pessoa).
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

const LK_SDR_SYSTEM = 'Você é SDR (pré-vendas) de uma agência brasileira de marketing digital. Escreva sempre em português do Brasil, como uma pessoa real escreveria: curto, educado, direto e sem cara de robô ou de spam. Nunca invente dados sobre a empresa, preços, prazos, cases ou resultados. Nada de promessas de resultado, CAIXA ALTA ou muitos emojis. Uma única chamada para ação por mensagem.';

function lk_sdr_channels() {
	return array( 'auto' => 'Automático (WhatsApp; sem telefone, e-mail)', 'whatsapp' => 'Só WhatsApp', 'email' => 'Só e-mail' );
}

function lk_sdr_status_labels() {
	return array( 'ativo' => 'Em cadência', 'respondeu' => 'Respondeu', 'sem-resposta' => 'Sem resposta', 'parado' => 'Parado' );
}

function lk_sdr_agents( $active_only = false ) {
	return lk_rows( 'sdr_agents', $active_only ? 'active = 1' : '1=1', array(), 'id' );
}

function lk_sdr_label( $a ) {
	return trim( ( $a->emoji ? $a->emoji . ' ' : '' ) . $a->name );
}

/** O que falta para o time trabalhar (vazio = pronto). */
function lk_sdr_missing() {
	$out = array();
	if ( ! lk_places_key() ) {
		$out[] = 'a chave da Google Places API (Configurações → Google)';
	}
	if ( ! lk_ai_ready() ) {
		$out[] = 'a chave de uma IA (Configurações → IA)';
	}
	return $out;
}

/** Primeiros contatos que o agente já colocou na fila hoje. */
function lk_sdr_today_count( $agent_id ) {
	global $wpdb;
	return (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM ' . lk_table( 'sdr_queue' ) . ' WHERE agent_id = %d AND step = 0 AND created_at >= %s', $agent_id, lk_today() . ' 00:00:00' ) ); // phpcs:ignore WordPress.DB.PreparedSQL
}

function lk_sdr_pending_count() {
	global $wpdb;
	return (int) $wpdb->get_var( 'SELECT COUNT(*) FROM ' . lk_table( 'sdr_queue' ) . " WHERE status = 'pendente'" ); // phpcs:ignore WordPress.DB.PreparedSQL
}

/** Empresas que o agente já avaliou (para não gastar IA duas vezes com a mesma). */
function lk_sdr_seen( $agent_id ) {
	$seen = get_option( 'lk_sdr_seen_' . (int) $agent_id, array() );
	return is_array( $seen ) ? $seen : array();
}

function lk_sdr_seen_save( $agent_id, $seen ) {
	update_option( 'lk_sdr_seen_' . (int) $agent_id, array_slice( $seen, -3000, null, true ), false );
}

function lk_sdr_agency_context( $agent ) {
	return 'Agência: ' . lk_setting( 'empresa' ) . ( lk_setting( 'site' ) ? ' (' . lk_setting( 'site' ) . ')' : '' ) . '.'
		. "\nO que oferecemos nesta abordagem: " . ( trim( (string) $agent->offer ) ? $agent->offer : 'um diagnóstico gratuito da presença digital e do Instagram.' )
		. ( trim( (string) $agent->ideal ) ? "\nCliente ideal: " . $agent->ideal : '' );
}

/**
 * A IA avalia a empresa e escreve o primeiro contato. Devolve [nota, motivo, assunto, mensagem] ou WP_Error.
 */
function lk_sdr_first_message( $agent, $p, $channel ) {
	$facts = array(
		'Empresa: ' . $p['name'],
		$p['niche'] ? 'Segmento: ' . $p['niche'] : '',
		$p['address'] ? 'Endereço: ' . $p['address'] : '',
		$p['rating'] ? 'Nota no Google: ' . $p['rating'] . ' com ' . $p['count'] . ' avaliações' : 'Sem avaliações no Google',
		$p['website'] ? 'Site: ' . $p['website'] : 'Não tem site no Google',
		! empty( $p['instagram'] ) ? 'Instagram: @' . $p['instagram'] : ( $p['website'] ? 'Não achamos Instagram no site' : '' ),
	);
	$wa     = 'whatsapp' === $channel;
	$prompt = lk_sdr_agency_context( $agent ) . "\n\nDados públicos da empresa:\n" . implode( "\n", array_filter( $facts ) )
		. "\n\n1) Dê uma nota de 0 a 10 para o quanto esta empresa combina com a nossa oferta e o cliente ideal (10 = encaixe perfeito e com sinais claros de que precisa de ajuda no digital). Explique em 1 frase curta."
		. "\n2) Escreva a mensagem de PRIMEIRO CONTATO por " . ( $wa ? 'WhatsApp, até 5 linhas' : 'e-mail, até 110 palavras, com assunto curto e sem cara de propaganda' ) . '. Personalize com 1 detalhe real dos dados acima (nunca invente). Apresente-se pelo nome "' . $agent->name . '" da ' . lk_setting( 'empresa' ) . '. Termine com uma pergunta simples e com uma linha dizendo que, se preferirem não receber mensagens, é só avisar.'
		. ' Responda só JSON: {"nota":0,"motivo":"...","assunto":"' . ( $wa ? '' : 'assunto do e-mail' ) . '","mensagem":"texto"}';
	$out = lk_ai_json( $prompt, array( 'system' => LK_SDR_SYSTEM, 'temperature' => 0.7 ) );
	if ( is_wp_error( $out ) ) {
		return $out;
	}
	$msg = trim( (string) ( $out['mensagem'] ?? '' ) );
	if ( '' === $msg ) {
		return new WP_Error( 'lk', 'A IA não escreveu a mensagem.' );
	}
	return array( max( 0, min( 10, (int) ( $out['nota'] ?? 0 ) ) ), mb_substr( sanitize_text_field( (string) ( $out['motivo'] ?? '' ) ), 0, 250 ), $wa ? '' : mb_substr( sanitize_text_field( (string) ( $out['assunto'] ?? '' ) ), 0, 180 ), sanitize_textarea_field( $msg ) );
}

/**
 * Um turno de trabalho do agente: busca, avalia, cria leads e coloca o primeiro contato na fila.
 * $budget: segundos que ele pode usar (a hospedagem corta requisições longas). Devolve [novos, recado].
 */
function lk_sdr_run( $agent, $budget = 40 ) {
	$start = microtime( true );
	$miss  = lk_sdr_missing();
	if ( $miss ) {
		return array( 0, 'Falta ' . implode( ' e ', $miss ) . '.' );
	}
	$need = (int) $agent->daily_limit - lk_sdr_today_count( $agent->id );
	if ( $need <= 0 ) {
		return array( 0, 'Meta do dia batida (' . (int) $agent->daily_limit . ' contatos).' );
	}
	$seen  = lk_sdr_seen( $agent->id );
	$known = lk_prospect_known();
	$token = (string) $agent->page_token;
	$new   = 0;
	$low   = 0;
	$note  = '';
	for ( $page = 0; $page < 3 && $new < $need; $page++ ) {
		$req = new WP_REST_Request( 'POST' );
		$req->set_param( 'niche', $agent->niche );
		$req->set_param( 'city', $agent->city );
		$req->set_param( 'token', $token );
		$res = lk_api_prospect_search( $req );
		if ( is_wp_error( $res ) ) {
			$note = $res->get_error_message();
			break;
		}
		$done = true;
		foreach ( $res['places'] as $p ) {
			if ( $new >= $need || microtime( true ) - $start > $budget ) {
				$done = false;
				break;
			}
			if ( $p['known'] || isset( $seen[ $p['id'] ] ) ) {
				continue;
			}
			$seen[ $p['id'] ] = 1;
			if ( $p['website'] ) {
				$c              = lk_prospect_find_contacts( $p['website'] );
				$p['email']     = $c['emails'][0] ?? '';
				$p['instagram'] = $c['instagram'];
			}
			$p += array( 'email' => '', 'instagram' => '' );
			$channel = $agent->channel;
			if ( 'auto' === $channel || ( 'whatsapp' === $channel && ! $p['phone'] ) || ( 'email' === $channel && ! $p['email'] ) ) {
				$channel = $p['phone'] ? 'whatsapp' : ( $p['email'] ? 'email' : '' );
			}
			if ( ! $channel ) {
				continue; // sem telefone nem e-mail: não tem como abordar
			}
			$ai = lk_sdr_first_message( $agent, $p, $channel );
			if ( is_wp_error( $ai ) ) {
				unset( $seen[ $p['id'] ] ); // tenta de novo no próximo turno
				$note = 'IA: ' . $ai->get_error_message();
				$done = false;
				break 2;
			}
			list( $score, $reason, $subject, $body ) = $ai;
			if ( $score < (int) $agent->min_score ) {
				$low++;
				continue;
			}
			$p['query'] = $res['query'];
			$lead_id    = lk_prospect_insert( $p, $known, array( 'source' => 'SDR IA · ' . $agent->name, 'sdr_agent' => (int) $agent->id, 'sdr_status' => 'ativo' ) );
			if ( ! $lead_id ) {
				continue;
			}
			lk_lead_log( $lead_id, 'SDR ' . lk_sdr_label( $agent ) . ': nota ' . $score . '/10. ' . $reason );
			lk_insert( 'sdr_queue', array( 'agent_id' => (int) $agent->id, 'lead_id' => $lead_id, 'step' => 0, 'channel' => $channel, 'subject' => $subject, 'body' => $body, 'score' => $score, 'reason' => $reason ) );
			$new++;
		}
		if ( ! $done ) {
			break; // a página não terminou: o próximo turno continua dela
		}
		$token = (string) $res['next'];
		if ( '' === $token ) {
			if ( ! $new ) {
				$note = 'Acabaram as empresas novas para "' . $res['query'] . '". Troque a cidade ou o nicho deste SDR.';
			}
			break;
		}
	}
	lk_sdr_seen_save( $agent->id, $seen );
	if ( ! $note ) {
		$note = $new ? $new . ' contato(s) novo(s) na fila.' : 'Nenhuma empresa nova com bom encaixe neste turno.';
		$note .= $low ? ' Deixou de fora ' . $low . ' com nota abaixo de ' . (int) $agent->min_score . '.' : '';
	}
	lk_update( 'sdr_agents', $agent->id, array( 'page_token' => $token, 'last_run' => lk_now(), 'last_note' => mb_substr( $note, 0, 250 ) ) );
	return array( $new, $note );
}

/**
 * Follow-ups: lead em cadência, última mensagem enviada há X dias e sem resposta → próxima mensagem na fila.
 * Depois do último follow-up, o lead sai da cadência ("Sem resposta").
 */
function lk_sdr_followups( $budget = 20 ) {
	$start = microtime( true );
	$first = array_keys( lk_funnel() )[0];
	$made  = 0;
	foreach ( lk_rows( 'leads', "sdr_status = 'ativo'", array(), 'id' ) as $lead ) {
		if ( microtime( true ) - $start > $budget ) {
			break;
		}
		// Alguém da equipe mexeu no lead (mudou de etapa): o SDR sai de cena.
		if ( $lead->stage !== $first ) {
			lk_update( 'leads', $lead->id, array( 'sdr_status' => 'parado' ) );
			continue;
		}
		$agent = lk_get( 'sdr_agents', $lead->sdr_agent );
		$sent  = lk_rows( 'sdr_queue', 'lead_id = %d', array( $lead->id ), 'id DESC' );
		$last  = $sent ? $sent[0] : null;
		if ( ! $agent || ! $last || 'enviado' !== $last->status || strtotime( $last->sent_at ) > current_time( 'timestamp' ) - DAY_IN_SECONDS * max( 1, (int) $agent->gap_days ) ) {
			continue;
		}
		if ( (int) $last->step >= (int) $agent->followups ) {
			lk_update( 'leads', $lead->id, array( 'sdr_status' => 'sem-resposta' ) );
			lk_lead_log( $lead->id, 'SDR ' . lk_sdr_label( $agent ) . ': sem resposta depois de ' . ( (int) $last->step + 1 ) . ' mensagem(ns). Saiu da cadência.' );
			continue;
		}
		$step    = (int) $last->step + 1;
		$history = array();
		foreach ( array_reverse( $sent ) as $q ) {
			if ( 'enviado' === $q->status ) {
				$history[] = 'Mensagem ' . ( (int) $q->step + 1 ) . ' (' . lk_date( $q->sent_at ) . '): ' . $q->body;
			}
		}
		$final  = $step >= (int) $agent->followups;
		$wa     = 'whatsapp' === $last->channel;
		$prompt = lk_sdr_agency_context( $agent ) . "\nEmpresa: " . lk_lead_label( $lead ) . "\n" . mb_substr( (string) $lead->notes, 0, 800 )
			. "\n\nMensagens que já mandamos e ficaram sem resposta:\n" . implode( "\n\n", $history )
			. "\n\nEscreva o FOLLOW-UP " . $step . ' de ' . (int) $agent->followups . ' por ' . ( $wa ? 'WhatsApp (até 3 linhas)' : 'e-mail (até 70 palavras)' ) . ', assinando como ' . $agent->name . '. Não repita o texto anterior: traga um ângulo novo de valor, sem pressão e sem culpa por não terem respondido.'
			. ( $final ? ' É a ÚLTIMA mensagem: diga com leveza que não vai mais insistir e deixe a porta aberta.' : '' )
			. ' Responda só JSON: {"assunto":"' . ( $wa ? '' : 'assunto curto (pode ser Re: do anterior)' ) . '","mensagem":"texto"}';
		$out = lk_ai_json( $prompt, array( 'system' => LK_SDR_SYSTEM, 'temperature' => 0.7 ) );
		if ( is_wp_error( $out ) || '' === trim( (string) ( $out['mensagem'] ?? '' ) ) ) {
			break; // IA fora do ar ou no limite: tenta na próxima hora
		}
		lk_insert( 'sdr_queue', array( 'agent_id' => (int) $agent->id, 'lead_id' => (int) $lead->id, 'step' => $step, 'channel' => $last->channel, 'subject' => $wa ? '' : mb_substr( sanitize_text_field( (string) ( $out['assunto'] ?? '' ) ), 0, 180 ), 'body' => sanitize_textarea_field( (string) $out['mensagem'] ), 'score' => (int) $last->score, 'reason' => $final ? 'Último follow-up' : 'Follow-up ' . $step ) );
		$made++;
	}
	return $made;
}

/** A cada hora: follow-ups e um turno de cada SDR ativo (o que rodou há mais tempo primeiro). */
add_action( 'lk_hourly', 'lk_sdr_tick' );
function lk_sdr_tick() {
	if ( lk_sdr_missing() || ! lk_sdr_agents( true ) ) {
		return;
	}
	if ( function_exists( 'set_time_limit' ) ) {
		@set_time_limit( 180 ); // phpcs:ignore WordPress.PHP.NoSilencedErrors
	}
	$before = lk_sdr_pending_count();
	$start  = microtime( true );
	lk_sdr_followups( 25 );
	$agents = lk_sdr_agents( true );
	usort( $agents, function ( $a, $b ) { return strcmp( (string) $a->last_run, (string) $b->last_run ); } );
	foreach ( $agents as $a ) {
		$left = 100 - ( microtime( true ) - $start );
		if ( $left < 15 ) {
			break;
		}
		lk_sdr_run( $a, min( 40, $left ) );
	}
	// Avisa no sininho (uma vez por dia) que tem mensagem esperando aprovação.
	$now = lk_sdr_pending_count();
	if ( $now > $before && get_option( 'lk_sdr_notified' ) !== lk_today() ) {
		update_option( 'lk_sdr_notified', lk_today(), false );
		foreach ( lk_team_users() as $u ) {
			if ( lk_can( 'leads', $u->ID ) ) {
				lk_notify( $u->ID, 'Time de SDR: ' . $now . ' mensagem(ns) esperando a sua aprovação.', lk_panel_url( 'sdr' ) );
			}
		}
	}
}

/* -----------------------------------------------------------------------
 * Formulários
 * -------------------------------------------------------------------- */

function lk_do_sdr_agent_save() {
	lk_require( 'leads' );
	$id   = lk_in( 'id', 'int' );
	$data = array(
		'name'        => lk_in( 'name' ),
		'emoji'       => mb_substr( lk_in( 'emoji' ), 0, 4 ),
		'niche'       => lk_in( 'niche' ),
		'city'        => lk_in( 'city' ),
		'offer'       => lk_in( 'offer', 'textarea' ),
		'ideal'       => lk_in( 'ideal', 'textarea' ),
		'channel'     => isset( lk_sdr_channels()[ lk_in( 'channel' ) ] ) ? lk_in( 'channel' ) : 'auto',
		'daily_limit' => max( 1, min( 50, lk_in( 'daily_limit', 'int' ) ) ),
		'min_score'   => max( 0, min( 10, lk_in( 'min_score', 'int' ) ) ),
		'followups'   => max( 0, min( 5, lk_in( 'followups', 'int' ) ) ),
		'gap_days'    => max( 1, min( 30, lk_in( 'gap_days', 'int' ) ) ),
	);
	if ( '' === $data['name'] || '' === $data['niche'] || '' === $data['city'] ) {
		lk_back( 'Dê um nome ao SDR e diga o nicho e a cidade que ele vai prospectar.', 'erro' );
	}
	$old = $id ? lk_get( 'sdr_agents', $id ) : null;
	if ( $old ) {
		// Mudou a busca: começa do início da lista do Google.
		if ( $old->niche !== $data['niche'] || $old->city !== $data['city'] ) {
			$data['page_token'] = '';
		}
		lk_update( 'sdr_agents', $id, $data );
		lk_back( 'SDR ' . $data['name'] . ' atualizado.' );
	}
	lk_insert( 'sdr_agents', $data + array( 'active' => 1 ) );
	lk_back( 'SDR ' . $data['name'] . ' contratado! Ele começa no próximo turno (a cada hora) ou agora, em "Rodar agora".' );
}

function lk_do_sdr_agent_toggle() {
	lk_require( 'leads' );
	$a = lk_get( 'sdr_agents', lk_in( 'id', 'int' ) );
	if ( $a ) {
		lk_update( 'sdr_agents', $a->id, array( 'active' => $a->active ? 0 : 1 ) );
		lk_back( $a->active ? $a->name . ' pausado.' : $a->name . ' voltou a trabalhar.' );
	}
	lk_back();
}

function lk_do_sdr_agent_delete() {
	lk_require( 'leads' );
	global $wpdb;
	$id = lk_in( 'id', 'int' );
	$wpdb->delete( lk_table( 'sdr_queue' ), array( 'agent_id' => $id, 'status' => 'pendente' ) );
	$wpdb->query( $wpdb->prepare( 'UPDATE ' . lk_table( 'leads' ) . " SET sdr_status = 'parado' WHERE sdr_agent = %d AND sdr_status = 'ativo'", $id ) ); // phpcs:ignore WordPress.DB.PreparedSQL
	delete_option( 'lk_sdr_seen_' . $id );
	lk_delete( 'sdr_agents', $id );
	lk_back( 'SDR removido. Os leads que ele trouxe continuam no funil.' );
}

function lk_do_sdr_run() {
	lk_require( 'leads' );
	$a = lk_get( 'sdr_agents', lk_in( 'id', 'int' ) );
	if ( ! $a ) {
		lk_back();
	}
	if ( function_exists( 'set_time_limit' ) ) {
		@set_time_limit( 120 ); // phpcs:ignore WordPress.PHP.NoSilencedErrors
	}
	list( $new, $note ) = lk_sdr_run( $a, 45 );
	lk_back( lk_sdr_label( $a ) . ': ' . $note, $new ? 'ok' : 'warn' );
}

/** Aprovar: e-mail sai pelo CRM; WhatsApp é aberto no navegador (o JS abre a conversa) e aqui só registramos. */
function lk_do_sdr_send() {
	lk_require( 'leads' );
	$q    = lk_get( 'sdr_queue', lk_in( 'id', 'int' ) );
	$lead = $q ? lk_get( 'leads', $q->lead_id ) : null;
	if ( ! $q || ! $lead || 'pendente' !== $q->status ) {
		lk_back( 'Esta mensagem já foi tratada.', 'warn' );
	}
	$body    = lk_in( 'body', 'textarea' );
	$subject = lk_in( 'subject' );
	$via     = 'email' === lk_in( 'via' ) ? 'email' : 'whatsapp';
	if ( '' === trim( $body ) ) {
		lk_back( 'A mensagem está vazia.', 'erro' );
	}
	if ( 'email' === $via ) {
		if ( ! is_email( $lead->email ) ) {
			lk_back( 'Este lead não tem e-mail válido. Mande pelo WhatsApp ou complete o cadastro.', 'erro' );
		}
		$ok = wp_mail( $lead->email, $subject ? $subject : 'Contato da ' . lk_setting( 'empresa' ), '<div style="font-family:Arial,Helvetica,sans-serif;font-size:15px;line-height:1.6;color:#222;">' . wpautop( esc_html( $body ) ) . '</div>', array( 'Content-Type: text/html; charset=UTF-8' ) );
		if ( ! $ok ) {
			lk_back( 'O e-mail não saiu. Confira o envio de e-mails em Configurações (SMTP) e tente de novo.', 'erro' );
		}
	}
	lk_update( 'sdr_queue', $q->id, array( 'body' => $body, 'subject' => $subject, 'channel' => $via, 'status' => 'enviado', 'sent_at' => lk_now(), 'sent_by' => get_current_user_id() ) );
	lk_update( 'leads', $lead->id, array( 'last_contact' => lk_now() ) );
	lk_lead_log( $lead->id, ( $q->step ? 'Follow-up ' . (int) $q->step : 'Primeiro contato' ) . ' do SDR enviado por ' . ( 'email' === $via ? 'e-mail' : 'WhatsApp' ) . ":\n" . $body, 'comment' );
	lk_back( 'email' === $via ? 'E-mail enviado para ' . lk_lead_label( $lead ) . '.' : 'Registrado como enviado no WhatsApp para ' . lk_lead_label( $lead ) . '.' );
}

function lk_do_sdr_discard() {
	lk_require( 'leads' );
	$q = lk_get( 'sdr_queue', lk_in( 'id', 'int' ) );
	if ( $q && 'pendente' === $q->status ) {
		lk_update( 'sdr_queue', $q->id, array( 'status' => 'descartado' ) );
		lk_update( 'leads', $q->lead_id, array( 'sdr_status' => 'parado' ) );
		lk_lead_log( $q->lead_id, 'Mensagem do SDR descartada; o lead saiu da cadência.' );
	}
	lk_back( 'Mensagem descartada. O lead continua no funil, fora da cadência.' );
}

/** Na ficha do lead ou na fila: respondeu / parar / pediu para não ser contatado / retomar. */
function lk_do_sdr_lead() {
	lk_require( 'leads' );
	global $wpdb;
	$lead = lk_get( 'leads', lk_in( 'lead_id', 'int' ) );
	$what = lk_in( 'what' );
	if ( ! $lead || ! $lead->sdr_agent ) {
		lk_back();
	}
	if ( 'retomar' !== $what ) {
		$wpdb->query( $wpdb->prepare( 'UPDATE ' . lk_table( 'sdr_queue' ) . " SET status = 'descartado' WHERE lead_id = %d AND status = 'pendente'", $lead->id ) ); // phpcs:ignore WordPress.DB.PreparedSQL
	}
	if ( 'respondeu' === $what ) {
		$keys  = array_keys( lk_funnel() );
		$stage = ( isset( $keys[1] ) && ! in_array( $keys[1], array( lk_funnel_won(), lk_funnel_lost() ), true ) ) ? $keys[1] : $lead->stage;
		lk_update( 'leads', $lead->id, array( 'sdr_status' => 'respondeu', 'stage' => $stage, 'last_contact' => lk_now() ) );
		lk_lead_log( $lead->id, 'Respondeu ao SDR! Saiu da cadência' . ( $stage !== $lead->stage ? ' e foi para "' . lk_funnel()[ $stage ] . '"' : '' ) . '. 🎉' );
		lk_set_lead_reminder( lk_get( 'leads', $lead->id ), 'Continuar a conversa (respondeu ao SDR)', lk_today(), gmdate( 'H:i', current_time( 'timestamp' ) + HOUR_IN_SECONDS ) );
		lk_back( 'Boa! O lead foi para a próxima etapa e virou tarefa para hoje.' );
	}
	if ( 'optout' === $what ) {
		$lost = lk_funnel_lost();
		lk_update( 'leads', $lead->id, array( 'sdr_status' => 'parado', 'stage' => $lost ? $lost : $lead->stage, 'lost_reason' => 'Pediu para não ser contatado' ) );
		lk_lead_log( $lead->id, 'Pediu para não ser contatado. Não entra mais em cadência.' );
		lk_back( 'Anotado: ninguém mais aborda esse lead.' );
	}
	if ( 'retomar' === $what ) {
		lk_update( 'leads', $lead->id, array( 'sdr_status' => 'ativo' ) );
		lk_lead_log( $lead->id, 'Voltou para a cadência do SDR.' );
		lk_back( 'O lead voltou para a cadência.' );
	}
	lk_update( 'leads', $lead->id, array( 'sdr_status' => 'parado' ) );
	lk_lead_log( $lead->id, 'Cadência do SDR parada.' );
	lk_back( 'Cadência parada.' );
}

/** Quadro "SDR" na ficha do lead. */
function lk_sdr_lead_box( $l ) {
	if ( empty( $l->sdr_agent ) ) {
		return;
	}
	$a      = lk_get( 'sdr_agents', $l->sdr_agent );
	$status = lk_sdr_status_labels()[ $l->sdr_status ] ?? 'Parado';
	echo '<section class="card"><div class="card-head"><h3>' . esc_html( $a ? lk_sdr_label( $a ) : 'SDR IA' ) . '</h3><span class="badge' . ( 'respondeu' === $l->sdr_status ? ' badge--ok' : ( 'ativo' === $l->sdr_status ? ' badge--wait' : '' ) ) . '">' . esc_html( $status ) . '</span></div><div class="row-btns">';
	if ( 'ativo' === $l->sdr_status ) {
		lk_action_button( 'sdr_lead', array( 'lead_id' => $l->id, 'what' => 'respondeu' ), '💬 Respondeu', 'btn btn--primary btn--sm' );
		lk_action_button( 'sdr_lead', array( 'lead_id' => $l->id, 'what' => 'parar' ), 'Parar cadência', 'btn btn--ghost btn--sm' );
	} elseif ( 'respondeu' !== $l->sdr_status && $l->stage !== lk_funnel_lost() ) {
		lk_action_button( 'sdr_lead', array( 'lead_id' => $l->id, 'what' => 'retomar' ), 'Voltar para a cadência', 'btn btn--ghost btn--sm' );
	}
	if ( $l->stage !== lk_funnel_lost() ) {
		lk_action_button( 'sdr_lead', array( 'lead_id' => $l->id, 'what' => 'optout' ), 'Pediu para não contatar', 'btn btn--ghost btn--sm', 'Marcar como perdido e nunca mais abordar?' );
	}
	echo '</div><p class="muted small"><a href="' . esc_url( lk_panel_url( 'sdr' ) ) . '">Abrir a fila do Time de SDR</a></p></section>';
}

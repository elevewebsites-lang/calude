<?php
/**
 * E-mails automáticos para o cliente (identidade da LDK):
 *   pedido recebido · etapa avançou · pronto (com fotos) · pagamento confirmado
 * Cada um pode ser desligado em Configurações.
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function lk_email_types() {
	return array(
		'email_boasvindas' => 'Pedido recebido (quando o cliente aprova o orçamento)',
		'email_etapas'     => 'Pedido mudou de etapa (na fila, imprimindo, acabamento…)',
		'email_pronto'     => 'Pedido pronto para retirar/enviar (com as fotos)',
		'email_pagamento'  => 'Pagamento confirmado (recibo)',
	);
}

function lk_email_on( $type ) {
	return '0' !== (string) lk_setting( $type );
}

/**
 * Envia um e-mail HTML no padrão visual do painel.
 * $rows: [ [rótulo, valor], ... ] mostrado como tabela.
 */
function lk_mail( $to, $subject, $title, $intro, $rows = array(), $cta_text = '', $cta_url = '', $after = '' ) {
	if ( ! is_email( $to ) ) {
		return false;
	}
	$logo   = lk_setting( 'logo' );
	$accent = '#0a0a0a';
	$html   = '<!DOCTYPE html><html><body style="margin:0;background:#f4f4f2;font-family:Arial,Helvetica,sans-serif;color:#0a0a0a;">';
	$html  .= '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#f4f4f2;padding:32px 12px;"><tr><td align="center">';
	$html  .= '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:560px;background:#ffffff;border-radius:16px;overflow:hidden;">';
	$html  .= '<tr><td style="background:#0a0a0a;padding:26px 32px;">' . ( $logo ? '<img src="' . esc_url( $logo ) . '" alt="' . esc_attr( lk_setting( 'empresa' ) ) . '" height="26" style="display:block;height:26px;">' : '<strong style="color:#fff;">' . esc_html( lk_setting( 'empresa' ) ) . '</strong>' ) . '</td></tr>';
	$html  .= '<tr><td style="padding:32px;">';
	$html  .= '<h1 style="margin:0 0 14px;font-size:24px;line-height:1.25;letter-spacing:-.5px;">' . esc_html( $title ) . '</h1>';
	$html  .= '<div style="font-size:15px;line-height:1.65;color:#404040;">' . wp_kses_post( $intro ) . '</div>';
	if ( $rows ) {
		$html .= '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="margin:22px 0;border-top:1px solid #e7e7e3;">';
		foreach ( $rows as $r ) {
			$html .= '<tr><td style="padding:11px 0;border-bottom:1px solid #e7e7e3;font-size:14px;color:#737373;">' . esc_html( $r[0] ) . '</td><td align="right" style="padding:11px 0;border-bottom:1px solid #e7e7e3;font-size:14px;font-weight:bold;">' . wp_kses_post( $r[1] ) . '</td></tr>';
		}
		$html .= '</table>';
	}
	if ( $cta_url ) {
		$html .= '<p style="margin:26px 0 8px;"><a href="' . esc_url( $cta_url ) . '" style="display:inline-block;background:' . $accent . ';color:#ffffff;text-decoration:none;font-weight:bold;font-size:15px;padding:14px 26px;border-radius:999px;">' . esc_html( $cta_text ) . '</a></p>';
	}
	if ( $after ) {
		$html .= '<div style="margin-top:18px;font-size:14px;line-height:1.6;color:#525252;">' . wp_kses_post( $after ) . '</div>';
	}
	$html .= '</td></tr>';
	$wa    = lk_setting( 'whatsapp' );
	$html .= '<tr><td style="padding:20px 32px;background:#fafaf8;font-size:12px;color:#737373;line-height:1.6;">Dúvidas? ' . ( $wa ? 'Chame no WhatsApp: <a href="' . esc_url( lk_wa_link( $wa ) ) . '" style="color:#0a0a0a;">' . esc_html( $wa ) . '</a> · ' : '' ) . esc_html( lk_setting( 'email' ) ) . '<br>© ' . esc_html( gmdate( 'Y' ) . ' ' . lk_setting( 'empresa' ) ) . '<br><a href="https://elevewebsites.com.br/?utm_source=email&utm_medium=credito" style="color:#9a9a9a;text-decoration:none;font-size:11px;">Sistema personalizado desenvolvido por <strong>Eleve Websites</strong></a></td></tr>';
	$html .= '</table></td></tr></table></body></html>';

	return wp_mail( $to, $subject, $html, array( 'Content-Type: text/html; charset=UTF-8' ) );
}

/**
 * Dados do cliente do projeto (e-mail e primeiro nome).
 */
function lk_project_contact( $project ) {
	$client = $project ? lk_get( 'clients', $project->client_id ) : null;
	if ( ! $client || ! is_email( $client->email ) ) {
		return null;
	}
	return array( $client, $client->name ? strtok( $client->name, ' ' ) : lk_client_label( $client ) );
}

/**
 * Link para o cliente entrar: área do cliente ou, se ainda não tiver acesso, o convite.
 */
function lk_client_entry_url( $client, $section = '', $id = 0 ) {
	if ( ! $client->user_id && $client->invite_token ) {
		return lk_invite_url( $client->invite_token );
	}
	return lk_client_url( $section, $id );
}

/* -----------------------------------------------------------------------
 * Boas-vindas (orçamento aprovado) e etapas do pedido
 * -------------------------------------------------------------------- */

add_action( 'lk_quote_accepted', 'lk_email_welcome_quote', 10, 3 );
function lk_email_welcome_quote( $quote_id, $client_id, $project_id ) {
	lk_email_welcome( $project_id );
}

function lk_email_welcome( $project_id ) {
	if ( ! lk_email_on( 'email_boasvindas' ) || get_option( 'lk_welcome_' . $project_id ) ) {
		return;
	}
	$p       = lk_get( 'projects', $project_id );
	$contact = lk_project_contact( $p );
	if ( ! $contact ) {
		return;
	}
	list( $client, $first ) = $contact;
	update_option( 'lk_welcome_' . $project_id, 1, false );
	$rows = array( array( 'Pedido', '#' . (int) $p->id . ' · ' . esc_html( $p->title ) ) );
	if ( $p->due_date ) {
		$rows[] = array( 'Previsão', esc_html( lk_date( $p->due_date ) ) );
	}
	$rows[] = array( 'Entrega', 'envio' === $p->delivery_mode ? 'Envio' . ( $p->ship_service ? ' · ' . esc_html( $p->ship_service ) : '' ) : 'Retirada' );
	lk_mail(
		$client->email,
		'Recebemos seu pedido! · ' . lk_setting( 'empresa' ),
		'Recebemos seu pedido!',
		'<p>Olá, ' . esc_html( $first ) . '! Obrigado pela confiança. Assim que o pagamento for confirmado, seu pedido entra na fila de produção. Você acompanha cada etapa (impressão, acabamento, fotos) pela sua área do cliente.</p>',
		$rows,
		'Acompanhar meu pedido',
		lk_client_entry_url( $client, 'projeto', $p->id )
	);
	lk_log( $p->id, 'E-mail de boas-vindas enviado para ' . $client->email . '.' );
}

add_action( 'lk_project_stage', 'lk_email_stage', 10, 2 );
function lk_email_stage( $project_id, $status ) {
	// "Pronto" tem e-mail próprio (orders.php); as outras etapas só avisam se estiver ligado.
	if ( ! lk_email_on( 'email_etapas' ) || $status === lk_ready_column() || $status === lk_first_column() ) {
		return;
	}
	$p       = lk_get( 'projects', $project_id );
	$contact = lk_project_contact( $p );
	if ( ! $contact ) {
		return;
	}
	list( $client, $first ) = $contact;
	$cols  = lk_columns();
	$texts = array(
		'na-fila'    => 'Seu pedido entrou na fila de produção.',
		'imprimindo' => 'Sua peça está sendo impressa agora! 🖨️',
		'acabamento' => 'A impressão terminou e sua peça está no acabamento.',
		'fotos'      => 'Estamos fotografando sua peça pronta.',
		'entregue'   => 'Pedido entregue. Esperamos que você ame! 💛',
	);
	lk_mail(
		$client->email,
		'Seu pedido: ' . $cols[ $status ] . ' · ' . lk_setting( 'empresa' ),
		$cols[ $status ],
		'<p>Olá, ' . esc_html( $first ) . '! ' . esc_html( $texts[ $status ] ?? 'Seu pedido avançou para "' . $cols[ $status ] . '".' ) . '</p>',
		array( array( 'Pedido', '#' . (int) $p->id . ' · ' . esc_html( $p->title ) ), array( 'Etapa', esc_html( $cols[ $status ] ) ) ),
		'Ver meu pedido',
		lk_client_entry_url( $client, 'projeto', $p->id )
	);
}

add_action( 'lk_task_review', 'lk_email_approval' );
function lk_email_approval( $task_id ) {
	if ( ! lk_email_on( 'email_aprovacao' ) ) {
		return;
	}
	$t       = lk_get( 'tasks', $task_id );
	$p       = $t ? lk_get( 'projects', $t->project_id ) : null;
	$contact = lk_project_contact( $p );
	if ( ! $contact ) {
		return;
	}
	list( $client, $first ) = $contact;
	lk_mail(
		$client->email,
		'Precisamos da sua aprovação · ' . lk_setting( 'empresa' ),
		'Tem novidade para você aprovar 👀',
		'<p>Olá, ' . esc_html( $first ) . '! Terminamos <strong>' . esc_html( ( $t->grp ? $t->grp . ' › ' : '' ) . $t->title ) . '</strong> do seu projeto. Dá uma olhada e aprove, ou peça os ajustes que quiser.</p>',
		array(),
		'Ver e aprovar',
		lk_client_entry_url( $client, 'projeto', $p->id )
	);
}

add_action( 'lk_payment_received', 'lk_email_payment' );
function lk_email_payment( $trans_id ) {
	if ( ! lk_email_on( 'email_pagamento' ) ) {
		return;
	}
	$t      = lk_get( 'transactions', $trans_id );
	$client = $t && $t->client_id ? lk_get( 'clients', $t->client_id ) : null;
	if ( ! $client || ! is_email( $client->email ) || 'in' !== $t->type ) {
		return;
	}
	$first = $client->name ? strtok( $client->name, ' ' ) : lk_client_label( $client );
	lk_mail(
		$client->email,
		'Pagamento confirmado · ' . lk_setting( 'empresa' ),
		'Pagamento confirmado ✓',
		'<p>Olá, ' . esc_html( $first ) . '! Recebemos o seu pagamento. Obrigado!</p>',
		array(
			array( 'Referente a', esc_html( $t->description ) ),
			array( 'Valor', esc_html( lk_money( $t->amount ) ) ),
			array( 'Data', esc_html( lk_date( $t->paid_at ? $t->paid_at : lk_today() ) ) ),
			array( 'Forma', esc_html( $t->method ? $t->method : '—' ) ),
		),
		'Ver meus pagamentos',
		lk_client_entry_url( $client, 'projeto', $t->project_id )
	);
}

/* -----------------------------------------------------------------------
 * Envio pelo SMTP do seu e-mail (Configurações → E-mail de envio)
 * -------------------------------------------------------------------- */

add_action( 'phpmailer_init', 'lk_smtp' );
function lk_smtp( $mailer ) {
	// Sem isto o PHPMailer espera até 5 minutos por um SMTP que não responde, o PHP estoura o tempo
	// e o WordPress mostra "erro crítico" (era o que travava o login da equipe no envio do código).
	$mailer->Timeout = 12; // phpcs:ignore WordPress.NamingConventions.ValidVariableName
	$host = trim( (string) lk_setting( 'smtp_host' ) );
	if ( ! $host ) {
		return;
	}
	$mailer->isSMTP();
	$mailer->Host       = $host; // phpcs:ignore WordPress.NamingConventions.ValidVariableName
	$mailer->Port       = (int) lk_setting( 'smtp_port' ) ? (int) lk_setting( 'smtp_port' ) : 465; // phpcs:ignore WordPress.NamingConventions.ValidVariableName
	$secure             = lk_setting( 'smtp_secure' );
	// Combinação trocada é o erro mais comum: 465 é SSL e 587 é TLS.
	if ( 465 === $mailer->Port && 'tls' === $secure ) { // phpcs:ignore WordPress.NamingConventions.ValidVariableName
		$secure = 'ssl';
	} elseif ( 587 === $mailer->Port && 'ssl' === $secure ) { // phpcs:ignore WordPress.NamingConventions.ValidVariableName
		$secure = 'tls';
	}
	$mailer->SMTPSecure = in_array( $secure, array( 'ssl', 'tls' ), true ) ? $secure : ''; // phpcs:ignore WordPress.NamingConventions.ValidVariableName
	$mailer->SMTPAutoTLS = 'tls' === $secure; // phpcs:ignore WordPress.NamingConventions.ValidVariableName
	if ( lk_setting( 'smtp_user' ) ) {
		$mailer->SMTPAuth = true; // phpcs:ignore WordPress.NamingConventions.ValidVariableName
		$mailer->Username = lk_setting( 'smtp_user' ); // phpcs:ignore WordPress.NamingConventions.ValidVariableName
		$mailer->Password = lk_decrypt( lk_setting( 'smtp_pass' ) ); // phpcs:ignore WordPress.NamingConventions.ValidVariableName
	}
	$from = lk_setting( 'mail_from' ) ? lk_setting( 'mail_from' ) : lk_setting( 'smtp_user' );
	if ( is_email( $from ) ) {
		try {
			$mailer->setFrom( $from, lk_setting( 'mail_from_name' ) ? lk_setting( 'mail_from_name' ) : lk_setting( 'empresa' ), false );
		} catch ( Exception $e ) { // phpcs:ignore Generic.CodeAnalysis.EmptyStatement
			// Remetente recusado: segue com o padrão do WordPress.
		}
	}
}

add_filter(
	'wp_mail_from',
	function ( $from ) {
		$mine = lk_setting( 'mail_from' ) ? lk_setting( 'mail_from' ) : lk_setting( 'smtp_user' );
		return is_email( $mine ) ? $mine : $from;
	}
);
add_filter(
	'wp_mail_from_name',
	function ( $name ) {
		return lk_setting( 'mail_from_name' ) ? lk_setting( 'mail_from_name' ) : $name;
	}
);

/**
 * Botão "Enviar e-mail de teste".
 */
function lk_do_mail_test() {
	lk_require( 'admin' );
	$to    = lk_in( 'to', 'email' ) ? lk_in( 'to', 'email' ) : wp_get_current_user()->user_email;
	$error = '';
	$catch = function ( $e ) use ( &$error ) {
		$error = $e->get_error_message();
	};
	add_action( 'wp_mail_failed', $catch );
	$ok = lk_mail( $to, 'Teste de envio · ' . lk_setting( 'empresa' ), 'Tudo certo com o envio ✓', '<p>Se você recebeu este e-mail, os e-mails automáticos do painel estão funcionando.</p>', array( array( 'Servidor', esc_html( lk_setting( 'smtp_host' ) ? lk_setting( 'smtp_host' ) : 'envio padrão do WordPress' ) ) ) );
	remove_action( 'wp_mail_failed', $catch );
	if ( $ok ) {
		delete_option( 'lk_2fa_mail_fail' );
		lk_back( 'E-mail de teste enviado para ' . $to . '. Confira a caixa de entrada (e o spam).' );
	}
	lk_back( 'Não foi possível enviar: ' . ( $error ? $error : 'confira os dados do SMTP.' ), 'erro' );
}

/* -----------------------------------------------------------------------
 * Pedido de avaliação no Google (X dias depois da entrega)
 * -------------------------------------------------------------------- */

/**
 * Todo dia: projetos entregues há X dias recebem o pedido de avaliação, uma única vez.
 * Só entregas recentes (até 14 dias além do prazo): quem foi entregue antes desta função existir não recebe.
 */

<?php
/**
 * E-mails automáticos para o cliente (identidade da empresa):
 *   pedido recebido · etapa avançou · pronto (com fotos) · pagamento confirmado
 * Cada um pode ser desligado em Configurações.
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function ap_email_types() {
	return array(
		'email_boasvindas' => 'Pedido recebido (quando o cliente aprova a proposta)',
		'email_etapas'     => 'Pedido mudou de etapa (revisão da arte, produção, acabamento…)',
		'email_pronto'     => 'Pedido pronto para retirar/enviar (com as fotos)',
		'email_pagamento'  => 'Pagamento confirmado (recibo)',
	);
}

function ap_email_on( $type ) {
	return '0' !== (string) ap_setting( $type );
}

/**
 * Envia um e-mail HTML no padrão visual da empresa.
 * $rows: [ [rótulo, valor], ... ] mostrado como tabela.
 */
function ap_mail( $to, $subject, $title, $intro, $rows = array(), $cta_text = '', $cta_url = '', $after = '' ) {
	if ( ! is_email( $to ) ) {
		return false;
	}
	$t      = ap_theme_colors();
	$accent = $t['ink'];
	$head   = $t['ink'];
	$html   = '<!DOCTYPE html><html><body style="margin:0;background:#f4f4f2;font-family:Arial,Helvetica,sans-serif;color:#0a0a0a;">';
	$html  .= '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#f4f4f2;padding:32px 12px;"><tr><td align="center">';
	$html  .= '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:560px;background:#ffffff;border-radius:16px;overflow:hidden;">';
	// Cabeçalho: cor do tema + a versão da logo com contraste para essa cor (sem plaquinha branca).
	$logo   = ap_logo_url( ap_logo_variant( $head ), 'h' );
	$html  .= '<tr><td style="background:' . esc_attr( $head ) . ';padding:26px 32px;border-bottom:4px solid ' . esc_attr( $t['accent'] ) . ';"><img src="' . esc_url( $logo ) . '" alt="' . esc_attr( ap_setting( 'empresa' ) ) . '" width="170" style="display:block;width:170px;max-width:100%;height:auto;border:0;"></td></tr>';
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
	$wa    = ap_setting( 'whatsapp' );
	$html .= '<tr><td style="padding:20px 32px;background:#fafaf8;font-size:12px;color:#737373;line-height:1.6;">Dúvidas? ' . ( $wa ? 'Chame no WhatsApp: <a href="' . esc_url( ap_wa_link( $wa ) ) . '" style="color:#0a0a0a;">' . esc_html( $wa ) . '</a> · ' : '' ) . esc_html( ap_setting( 'email' ) ) . '<br>© ' . esc_html( gmdate( 'Y' ) . ' ' . ap_setting( 'empresa' ) ) . '<br><a href="https://elevewebsites.com.br/?utm_source=email&utm_medium=credito" style="color:#9a9a9a;text-decoration:none;font-size:11px;">Sistema personalizado desenvolvido por <strong>Eleve Websites</strong></a></td></tr>';
	$html .= '</table></td></tr></table></body></html>';

	return wp_mail( $to, $subject, $html, array( 'Content-Type: text/html; charset=UTF-8' ) );
}

/**
 * Dados do cliente do projeto (e-mail e primeiro nome).
 */
function ap_project_contact( $project ) {
	$client = $project ? ap_get( 'clients', $project->client_id ) : null;
	if ( ! $client || ! is_email( $client->email ) ) {
		return null;
	}
	return array( $client, $client->name ? strtok( $client->name, ' ' ) : ap_client_label( $client ) );
}

/**
 * Link para o cliente entrar: área do cliente ou, se ainda não tiver acesso, o convite.
 */
function ap_client_entry_url( $client, $section = '', $id = 0 ) {
	if ( ! $client->user_id && $client->invite_token ) {
		return ap_invite_url( $client->invite_token );
	}
	return ap_client_url( $section, $id );
}

/* -----------------------------------------------------------------------
 * Boas-vindas (orçamento aprovado) e etapas do pedido
 * -------------------------------------------------------------------- */

add_action( 'ap_quote_accepted', 'ap_email_welcome_quote', 10, 3 );
function ap_email_welcome_quote( $quote_id, $client_id, $project_id ) {
	ap_email_welcome( $project_id );
}

function ap_email_welcome( $project_id ) {
	if ( ! ap_email_on( 'email_boasvindas' ) || get_option( 'ap_welcome_' . $project_id ) ) {
		return;
	}
	$p       = ap_get( 'projects', $project_id );
	$contact = ap_project_contact( $p );
	if ( ! $contact ) {
		return;
	}
	list( $client, $first ) = $contact;
	update_option( 'ap_welcome_' . $project_id, 1, false );
	$rows = array( array( 'Pedido', '#' . (int) $p->id . ' · ' . esc_html( $p->title ) ) );
	if ( $p->due_date ) {
		$rows[] = array( 'Previsão', esc_html( ap_date( $p->due_date ) ) );
	}
	$rows[] = array( 'Entrega', 'envio' === $p->delivery_mode ? 'Envio' . ( $p->ship_service ? ' · ' . esc_html( $p->ship_service ) : '' ) : 'Retirada' );
	ap_mail(
		$client->email,
		'Recebemos seu pedido! · ' . ap_setting( 'empresa' ),
		'Recebemos seu pedido!',
		'<p>Olá, ' . esc_html( $first ) . '! Obrigado pela confiança. Assim que o pagamento for confirmado, seu pedido entra na fila de produção. Você acompanha cada etapa (revisão da arte, produção, acabamento) pela sua área do cliente.</p>',
		$rows,
		'Acompanhar meu pedido',
		ap_client_entry_url( $client, 'projeto', $p->id )
	);
	ap_log( $p->id, 'E-mail de boas-vindas enviado para ' . $client->email . '.' );
}

add_action( 'ap_project_stage', 'ap_email_stage', 10, 2 );
function ap_email_stage( $project_id, $status ) {
	// "Pronto" tem e-mail próprio (orders.php); as outras etapas só avisam se estiver ligado.
	if ( ! ap_email_on( 'email_etapas' ) || $status === ap_ready_column() || $status === ap_first_column() ) {
		return;
	}
	$p       = ap_get( 'projects', $project_id );
	$contact = ap_project_contact( $p );
	if ( ! $contact ) {
		return;
	}
	list( $client, $first ) = $contact;
	$cols  = ap_columns();
	$texts = array(
		'revisao-da-arte' => 'Estamos revisando a sua arte para garantir a melhor impressão.',
		'producao'        => 'Seu pedido está em produção.',
		'acabamento'      => 'A impressão terminou e seu pedido está no acabamento.',
		'entregue'        => 'Pedido entregue. Obrigado pela confiança! 💛',
	);
	ap_mail(
		$client->email,
		'Seu pedido: ' . $cols[ $status ] . ' · ' . ap_setting( 'empresa' ),
		$cols[ $status ],
		'<p>Olá, ' . esc_html( $first ) . '! ' . esc_html( $texts[ $status ] ?? 'Seu pedido avançou para "' . $cols[ $status ] . '".' ) . '</p>',
		array( array( 'Pedido', '#' . (int) $p->id . ' · ' . esc_html( $p->title ) ), array( 'Etapa', esc_html( $cols[ $status ] ) ) ),
		'Ver meu pedido',
		ap_client_entry_url( $client, 'projeto', $p->id )
	);
}

add_action( 'ap_task_review', 'ap_email_approval' );
function ap_email_approval( $task_id ) {
	if ( ! ap_email_on( 'email_aprovacao' ) ) {
		return;
	}
	$t       = ap_get( 'tasks', $task_id );
	$p       = $t ? ap_get( 'projects', $t->project_id ) : null;
	$contact = ap_project_contact( $p );
	if ( ! $contact ) {
		return;
	}
	list( $client, $first ) = $contact;
	ap_mail(
		$client->email,
		'Precisamos da sua aprovação · ' . ap_setting( 'empresa' ),
		'Tem novidade para você aprovar 👀',
		'<p>Olá, ' . esc_html( $first ) . '! Terminamos <strong>' . esc_html( ( $t->grp ? $t->grp . ' › ' : '' ) . $t->title ) . '</strong> do seu projeto. Dá uma olhada e aprove, ou peça os ajustes que quiser.</p>',
		array(),
		'Ver e aprovar',
		ap_client_entry_url( $client, 'projeto', $p->id )
	);
}

add_action( 'ap_payment_received', 'ap_email_payment' );
function ap_email_payment( $trans_id ) {
	if ( ! ap_email_on( 'email_pagamento' ) ) {
		return;
	}
	$t      = ap_get( 'transactions', $trans_id );
	$client = $t && $t->client_id ? ap_get( 'clients', $t->client_id ) : null;
	if ( ! $client || ! is_email( $client->email ) || 'in' !== $t->type ) {
		return;
	}
	$first = $client->name ? strtok( $client->name, ' ' ) : ap_client_label( $client );
	ap_mail(
		$client->email,
		'Pagamento confirmado · ' . ap_setting( 'empresa' ),
		'Pagamento confirmado ✓',
		'<p>Olá, ' . esc_html( $first ) . '! Recebemos o seu pagamento. Obrigado!</p>',
		array(
			array( 'Referente a', esc_html( $t->description ) ),
			array( 'Valor', esc_html( ap_money( $t->amount ) ) ),
			array( 'Data', esc_html( ap_date( $t->paid_at ? $t->paid_at : ap_today() ) ) ),
			array( 'Forma', esc_html( $t->method ? $t->method : '—' ) ),
		),
		'Ver meus pagamentos',
		ap_client_entry_url( $client, 'projeto', $t->project_id )
	);
}

/* -----------------------------------------------------------------------
 * Envio pelo SMTP do seu e-mail (Configurações → E-mail de envio)
 * -------------------------------------------------------------------- */

add_action( 'phpmailer_init', 'ap_smtp' );
function ap_smtp( $mailer ) {
	$host = trim( (string) ap_setting( 'smtp_host' ) );
	if ( ! $host ) {
		return;
	}
	$mailer->isSMTP();
	$mailer->Host       = $host; // phpcs:ignore WordPress.NamingConventions.ValidVariableName
	$mailer->Port       = (int) ap_setting( 'smtp_port' ) ? (int) ap_setting( 'smtp_port' ) : 465; // phpcs:ignore WordPress.NamingConventions.ValidVariableName
	$secure             = ap_setting( 'smtp_secure' );
	$mailer->SMTPSecure = in_array( $secure, array( 'ssl', 'tls' ), true ) ? $secure : ''; // phpcs:ignore WordPress.NamingConventions.ValidVariableName
	$mailer->SMTPAutoTLS = 'tls' === $secure; // phpcs:ignore WordPress.NamingConventions.ValidVariableName
	if ( ap_setting( 'smtp_user' ) ) {
		$mailer->SMTPAuth = true; // phpcs:ignore WordPress.NamingConventions.ValidVariableName
		$mailer->Username = ap_setting( 'smtp_user' ); // phpcs:ignore WordPress.NamingConventions.ValidVariableName
		$mailer->Password = ap_decrypt( ap_setting( 'smtp_pass' ) ); // phpcs:ignore WordPress.NamingConventions.ValidVariableName
	}
	$from = ap_setting( 'mail_from' ) ? ap_setting( 'mail_from' ) : ap_setting( 'smtp_user' );
	if ( is_email( $from ) ) {
		$mailer->setFrom( $from, ap_setting( 'mail_from_name' ) ? ap_setting( 'mail_from_name' ) : ap_setting( 'empresa' ), false );
	}
}

add_filter(
	'wp_mail_from',
	function ( $from ) {
		$mine = ap_setting( 'mail_from' ) ? ap_setting( 'mail_from' ) : ap_setting( 'smtp_user' );
		return is_email( $mine ) ? $mine : $from;
	}
);
add_filter(
	'wp_mail_from_name',
	function ( $name ) {
		return ap_setting( 'mail_from_name' ) ? ap_setting( 'mail_from_name' ) : $name;
	}
);

/**
 * Botão "Enviar e-mail de teste".
 */
function ap_do_mail_test() {
	ap_require( 'admin' );
	$to    = ap_in( 'to', 'email' ) ? ap_in( 'to', 'email' ) : wp_get_current_user()->user_email;
	$error = '';
	$catch = function ( $e ) use ( &$error ) {
		$error = $e->get_error_message();
	};
	add_action( 'wp_mail_failed', $catch );
	$ok = ap_mail( $to, 'Teste de envio · ' . ap_setting( 'empresa' ), 'Tudo certo com o envio ✓', '<p>Se você recebeu este e-mail, os e-mails automáticos do painel estão funcionando.</p>', array( array( 'Servidor', esc_html( ap_setting( 'smtp_host' ) ? ap_setting( 'smtp_host' ) : 'envio padrão do WordPress' ) ) ) );
	remove_action( 'wp_mail_failed', $catch );
	if ( $ok ) {
		ap_back( 'E-mail de teste enviado para ' . $to . '. Confira a caixa de entrada (e o spam).' );
	}
	ap_back( 'Não foi possível enviar: ' . ( $error ? $error : 'confira os dados do SMTP.' ), 'erro' );
}

/* -----------------------------------------------------------------------
 * Pedido de avaliação no Google (X dias depois da entrega)
 * -------------------------------------------------------------------- */

/**
 * Todo dia: projetos entregues há X dias recebem o pedido de avaliação, uma única vez.
 * Só entregas recentes (até 14 dias além do prazo): quem foi entregue antes desta função existir não recebe.
 */

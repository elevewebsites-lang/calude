<?php
/**
 * Central de ajuda: Feedback do dia a dia, Novidades (o que mudou em cada versão) e Tutorial.
 * E o atalho para ligar/desligar o "Apontar".
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/* -----------------------------------------------------------------------
 * Novidades (atualize a cada versão nova do plugin)
 * -------------------------------------------------------------------- */

function lk_changelog() {
	return array(
		'1.5.0' => array(
			'date'  => '2026-09-30',
			'title' => 'Planejamento com cara de proposta, contrato online e mais',
			'items' => array(
				array( '🗓️', 'Planejamento novo', 'Botão ▶ Start por cliente, briefing e datas do mês ao lado, revisão interna antes de ir para a cliente e página de aprovação com 👍/✏️ em cada post. Aprovados já vão para o design.' ),
				array( '✍️', 'Contrato com assinatura online', 'Monte o contrato na ficha da cliente (serviços, valor, meses) e mande para assinar. Ela confirma por código no e-mail e desenha a assinatura. Sai com certificado.' ),
				array( '📊', 'Relatório mais completo', 'Gráfico de seguidores do mês, todos os posts com curtidas, comentários, salvamentos e alcance, campanhas e saldo da carteira de anúncios.' ),
				array( '💰', 'Financeiro da agência', 'Categorias de agência e contas fixas em 1 clique: aluguel, softwares, salários da equipe, videomaker.' ),
				array( '👥', 'Equipe', 'Link de cadastro da equipe (você aprova antes de entrar), função Videomaker e "Minha conta" para trocar a senha.' ),
				array( '🔗', 'Cadastro da cliente pelo link', 'Crie só com o nome e o projeto; ela completa os dados do contrato e cria a senha.' ),
				array( '🎯', 'Modo foco por cliente', 'Escolha o cliente e os posts dele já vêm na ordem do prazo mais urgente.' ),
				array( '💬', 'Clima de MSN', 'Aviso "fulano está online" com som, som no Chamar atenção e emojis animados no chat.' ),
				array( '📝', 'Feedback, Novidades e Tutorial', 'Registre o que acontece no dia a dia, veja o que mudou a cada versão e aprenda para que serve cada tela.' ),
			),
		),
		'1.4.0' => array(
			'date'  => '2026-09-29',
			'title' => 'Prazos, sala de voz e aprovação estilo mLabs',
			'items' => array(
				array( '⏰', 'Prazo por etapa', 'Cada post tem prazo de design, revisão e aprovação, antes da publicação.' ),
				array( '🎧', 'Sala de voz', 'A equipe conectada por voz, estilo Discord.' ),
				array( '⚡', 'Chamar atenção', 'Treme a tela de todo mundo online, com um aviso.' ),
				array( '👍', 'Aprovação estilo mLabs', 'A cliente vê o post como no Instagram e aprova com um joinha.' ),
				array( '🔗', 'LinkedIn, YouTube e Google Meu Negócio', 'Conexão e publicação automática.' ),
			),
		),
		'1.3.0' => array(
			'date'  => '2026-09-29',
			'title' => 'Propostas dentro do painel',
			'items' => array(
				array( '📄', 'Propostas', 'Montar, enviar por WhatsApp/e-mail e editar serviços sem sair do painel.' ),
				array( '🔄', 'Botão Atualizar', 'O dashboard atualiza os números sem recarregar.' ),
				array( '📍', 'Apontar', 'Marque ajustes direto na tela.' ),
			),
		),
	);
}

function lk_changelog_unseen() {
	return (string) get_user_meta( get_current_user_id(), 'lk_seen_version', true ) !== LK_VERSION;
}

/* -----------------------------------------------------------------------
 * Feedback do dia a dia (fica na mesma tabela dos apontamentos, com path "feedback")
 * -------------------------------------------------------------------- */

function lk_feedback_kinds() {
	return array( 'problema' => '🐞 Algo deu errado', 'sugestao' => '💡 Sugestão', 'duvida' => '❓ Dúvida', 'elogio' => '💚 Elogio' );
}

function lk_do_fb_new() {
	if ( ! lk_is_team() ) {
		wp_die( 'Sem permissão.' );
	}
	$body = lk_in( 'body', 'textarea' );
	if ( '' === trim( $body ) ) {
		lk_back( 'Escreva o que aconteceu.', 'erro' );
	}
	$kind = isset( lk_feedback_kinds()[ lk_in( 'tipo' ) ] ) ? lk_in( 'tipo' ) : 'sugestao';
	$id   = lk_insert(
		'feedback',
		array(
			'user_id' => get_current_user_id(),
			'url'     => lk_in( 'tela', 'url' ),
			'path'    => 'feedback',
			'page'    => $kind,
			'body'    => $body,
			'status'  => 'aberto',
		)
	);
	foreach ( get_users( array( 'role' => 'administrator', 'fields' => 'ID' ) ) as $admin ) {
		if ( (int) $admin !== get_current_user_id() ) {
			lk_notify( (int) $admin, '📝 Feedback de ' . wp_get_current_user()->display_name . ': ' . wp_trim_words( $body, 12 ), lk_panel_url( 'feedback' ) . '#fb-' . $id );
		}
	}
	lk_back( 'Obrigado! Feedback registrado.' );
}

function lk_do_fb_answer() {
	lk_require( 'admin' );
	$f = lk_get( 'feedback', lk_in( 'id', 'int' ) );
	if ( ! $f || 'feedback' !== $f->path ) {
		lk_back( 'Não encontrado.', 'erro' );
	}
	$st   = 'resolvido' === lk_in( 'status' ) ? 'resolvido' : 'aberto';
	$data = array( 'reply' => lk_in( 'reply', 'textarea' ), 'status' => $st, 'resolved_by' => 'resolvido' === $st ? get_current_user_id() : 0, 'resolved_at' => 'resolvido' === $st ? lk_now() : null );
	lk_update( 'feedback', $f->id, $data );
	if ( (int) $f->user_id !== get_current_user_id() && ( $data['reply'] || 'resolvido' === $st ) ) {
		lk_notify( (int) $f->user_id, ( 'resolvido' === $st ? '✅ Seu feedback foi resolvido' : '💬 Resposta no seu feedback' ) . ( $data['reply'] ? ': ' . wp_trim_words( $data['reply'], 12 ) : '.' ), lk_panel_url( 'feedback' ) . '#fb-' . $f->id );
	}
	lk_back( 'Feedback atualizado.' );
}

function lk_feedback_open_mine() {
	global $wpdb;
	return (int) $wpdb->get_var( 'SELECT COUNT(*) FROM ' . lk_table( 'feedback' ) . " WHERE path = 'feedback' AND status = 'aberto'" ); // phpcs:ignore WordPress.DB.PreparedSQL
}

/* -----------------------------------------------------------------------
 * Ligar/desligar o Apontar sem ir às Configurações
 * -------------------------------------------------------------------- */

function lk_do_fb_toggle() {
	lk_require( 'admin' );
	$key = 'clientes' === lk_in( 'quem' ) ? 'apontamentos_clientes' : 'apontamentos';
	$s   = get_option( 'lk_settings', array() );
	$s   = is_array( $s ) ? $s : array();
	$now = '1' === (string) lk_setting( $key );
	$s[ $key ] = $now ? '0' : '1';
	update_option( 'lk_settings', $s );
	lk_back( 'Botão Apontar ' . ( $now ? 'desligado' : 'ligado' ) . ( 'apontamentos_clientes' === $key ? ' na área das clientes.' : ' para a equipe.' ) );
}

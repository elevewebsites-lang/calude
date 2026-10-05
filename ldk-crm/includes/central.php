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
		'1.14.0' => array(
			'date'  => '2026-10-05',
			'title' => 'Contratos: gerar, assinar e guardar tudo num só lugar',
			'items' => array(
				array( '📄', 'Gerar contrato', 'No lead que aceitou o serviço (e na lista de contratos), o botão Gerar contrato monta tudo com os dados do cliente e da agência. A tela mostra as partes e as condições lado a lado e avisa o que falta preencher.' ),
				array( '✍️', 'Novas cláusulas', 'Campo de observações com novas cláusulas: uma por linha, entram numeradas no contrato como cláusulas adicionais, sem perder o resto do texto.' ),
				array( '📧', 'Cópia por e-mail e no Drive', 'Quando o cliente assina, ele e a agência recebem o contrato completo por e-mail com o arquivo em anexo, e uma cópia vai para Clientes/<cliente>/Contratos no Drive. Ao assinar a agência, sai a versão final para os dois.' ),
				array( '🗂️', 'Contratos no perfil e na área do cliente', 'O contrato assinado fica na ficha do cliente (com link do Drive) e na nova aba Contratos da área do cliente, para abrir ou salvar em PDF.' ),
				array( '📚', 'Menu Contratos', 'Nova página na coluna da esquerda com todos os contratos: rascunhos, aguardando o cliente, falta a agência e assinados, com busca e total das mensalidades em contrato.' ),
				array( '📋', 'Briefing salvo', 'O briefing respondido fica salvo na área do cliente (com impressão em PDF) e uma cópia vai para Clientes/<cliente>/Briefing no Drive.' ),
			),
		),
		'1.13.1' => array(
			'date'  => '2026-10-05',
			'title' => 'Contraste revisado em todo o sistema',
			'items' => array(
				array( '🎨', 'Texto selecionado legível', 'No tema escuro, o texto selecionado fica escuro sobre a barra clara (antes ficava branco sobre branco). No tema claro, usa a cor da marca com texto que contrasta.' ),
				array( '🌓', 'Contraste em todo o projeto', 'Auditoria de todos os pares de texto e fundo nos dois temas: botões, contadores, selos de status, etapas do conteúdo, avisos e cartões agora passam de 4,5:1 (padrão WCAG AA). As cores de destaque e de texto se ajustam sozinhas à cor da marca.' ),
				array( '⌨️', 'Foco e campos', 'Anel de foco visível em links e botões, placeholders legíveis e preenchimento automático do navegador no tema escuro.' ),
			),
		),
		'1.13.0' => array(
			'date'  => '2026-10-05',
			'title' => 'Status das redes dos clientes, revisão obrigatória e logo no Drive',
			'items' => array(
				array( '🔗', 'Redes conectadas (todos os clientes)', 'Tabela com Instagram, Facebook, LinkedIn e YouTube de cada cliente (conectado, pendente ou reconectar), contadores, filtro só pendentes e botão para copiar a mensagem com o link de conexão ou abrir no WhatsApp.' ),
				array( '🛡️', 'Revisão obrigatória', 'Antes de enviar o post ao cliente, o texto é revisado na hora. Limites do Instagram sempre bloqueiam; pontos de português/contexto bloqueiam a menos que você use "Enviar mesmo assim". No planejamento, nenhum texto pode estourar os limites. Liga/desliga em Configurações.' ),
				array( '🗂️', 'Logo do cliente no Drive', 'A logo enviada vai também para Clientes/<cliente>/Identidade visual no Google Drive.' ),
			),
		),
		'1.12.0' => array(
			'date'  => '2026-10-05',
			'title' => 'Metas da agência e comemorações',
			'items' => array(
				array( '🎯', 'Metas', 'Só administradores: receita do mês, recorrente (MRR), clientes novos, total de clientes, leads e posts publicados, por mês, ano ou acumulado. Aparecem no dashboard com o progresso.' ),
				array( '🏆', 'Animação de meta batida', 'Quando uma meta chega a 100%, a dona vê confete, troféu e som no painel (uma vez por meta e período).' ),
				array( '🚀', 'Animação de subir de nível', 'Quem sobe de nível na gamificação vê uma explosão de estrelas no próximo acesso ao painel.' ),
			),
		),
		'1.11.0' => array(
			'date'  => '2026-10-05',
			'title' => 'Aprovação com cara de Instagram e logo do cliente',
			'items' => array(
				array( '📱', 'Prévia como no Instagram', 'O cliente vê o post já "postado": feed, carrossel, Reels ou Story, com a arte, a legenda e as hashtags em destaque, e a aba "No perfil" mostra a grade com o post novo em primeiro. Dá para passar de um post para o outro.' ),
				array( '🖼️', 'Logo do cliente', 'A logo aparece na lista de clientes, no dashboard, no painel do cliente e na página de aprovação, no lugar das iniciais.' ),
			),
		),
		'1.10.0' => array(
			'date'  => '2026-10-05',
			'title' => 'Prospecção pelo Google',
			'items' => array(
				array( '🔎', 'Buscador de empresas', 'Digite o nicho e a cidade (ex.: engenharia + Taubaté SP) e veja nome, nicho, telefone, site, nota e endereço direto do Google. Marque as que interessam e mande para o Funil de leads, sem duplicar quem já está lá ou já é cliente.' ),
				array( '📧', 'E-mail e Instagram', 'O botão lê o site da própria empresa e traz o e-mail de contato e o Instagram que ela publica. Também exporta CSV.' ),
			),
		),
		'1.9.0' => array(
			'date'  => '2026-10-05',
			'title' => 'Revisão do texto antes de postar e hashtags',
			'items' => array(
				array( '🔎', 'Revisar texto', 'Botão no post: confere português, gramática, pontuação e contexto (cliente, briefing, data e feriado da publicação) com IA, mostra o que achou e aplica a correção com um clique. Precisa da chave da Anthropic em Configurações.' ),
				array( '#️⃣', 'Hashtags', 'Campo de hashtags no post e hashtags padrão na ficha de cada cliente. Entram no fim da legenda ao publicar e o cliente vê o texto final na aprovação. A IA sugere hashtags.' ),
				array( '🛡️', 'Trava de limites', 'Mais de 2.200 caracteres ou 30 hashtags não é publicado: volta para Revisão com o motivo. Avisos de espaço duplo, link na legenda, hashtag com hífen e texto em maiúsculas.' ),
				array( '⏰', 'Revisão automática', 'Posts que saem nas próximas 24 h e ainda não foram revisados são conferidos sozinhos, e o social media é avisado se houver pontos.' ),
			),
		),
		'1.8.0' => array(
			'date'  => '2026-10-05',
			'title' => 'Dashboard novo, gamificação, vídeo com play e apontamentos',
			'items' => array(
				array( '📈', 'Dashboard moderno', 'Boas-vindas com progresso do mês, gráfico de publicações (14 dias antes e depois), posts por etapa, suas tarefas da semana, seu nível e os últimos clientes.' ),
				array( '🏆', 'Ranking e prêmios', 'Pontos por tarefa concluída e post publicado, níveis, conquistas, ranking e loja de prêmios. O admin configura tudo em Gamificação.' ),
				array( '🎬', 'Limites do Instagram e vídeo com play', 'Imagem JPG até 8 MB (converte sozinho), vídeo MP4/MOV até 300 MB, Story até 100 MB/60 s e carrossel até 10. Você vê o vídeo e as regras antes de enviar e dá play no post.' ),
				array( '📌', 'Apontamentos nos posts', 'Botão Fazer apontamento: para quem é, sobre o quê e em que segundo do vídeo. A equipe aponta só para dentro antes de chegar no cliente; o cliente aponta pela página de aprovação. O admin decide quem pode.' ),
				array( '🔴', 'Feriados e datas no planejamento', 'Feriados nacionais (com Carnaval, Páscoa e Corpus Christi) em destaque, datas comemorativas, calendário colorido, ideia de cada conteúdo e aviso ao social media 7 dias e 1 dia antes de cada feriado.' ),
				array( '📊', 'Planejamento no Drive', 'Cada planejamento vira uma planilha na pasta do cliente (Clientes/<cliente>/Planejamentos) e se atualiza sozinha.' ),
				array( '🔗', 'Link para o cliente conectar as redes', 'Instagram, Facebook, LinkedIn e YouTube pelo link, sem senha e sem login no CRM, com aviso quando uma conta cair.' ),
				array( '✂️', 'Recorte automático das fotos', 'Feed 4:5, quadrado, paisagem e Story/Reels 9:16, com arrastar e zoom.' ),
			),
		),
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

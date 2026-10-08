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
		'1.36.0' => array(
			'date'  => '2026-10-06',
			'title' => 'Temas festivos (opcionais)',
			'items' => array(
				array( '🎃', 'Halloween', 'Cores de noite roxa e laranja, morcegos e fantasmas passando, aranha pendurada, abóbora no canto e cartões das tarefas que balançam. Ao concluir uma tarefa, um fantasminha sobe.' ),
				array( '🎄', 'Natal', 'Cores vermelho e verde, pisca-pisca no topo da tela, neve que cai de vez em quando e uma "neve" em cima de cada cartão de tarefa.' ),
				array( '🎆', 'Ano Novo', 'Cores azul-noite e dourado, fogos de artifício de tempos em tempos e brilho dourado nos cartões. Concluir uma tarefa solta um fogo.' ),
				array( '🎨', 'Você escolhe', 'No botão "Tema festivo" (menu lateral, ou no canto da tela de login) a pessoa escolhe Nenhum, Halloween, Natal ou Ano Novo, e pode desligar só as animações. É opcional e fica guardado no aparelho de cada um.' ),
			),
		),
		'1.35.0' => array(
			'date'  => '2026-10-06',
			'title' => 'Apontamentos conectados ao Eleve CRM (com resolvido de volta)',
			'items' => array(
				array( '🔌', 'Testar conexão', 'Em Configurações → Apontamentos, cole o endereço do projeto no Eleve CRM e clique em "Testar conexão". Cada apontamento vira tarefa lá; quando for marcado como resolvido lá, aparece resolvido aqui para o cliente.' ),
			),
		),
		'1.34.0' => array(
			'date'  => '2026-10-06',
			'title' => 'Apontamentos vão para o Eleve CRM',
			'items' => array(
				array( '🔗', 'Apontamento vira tarefa lá', 'Em Configurações → Apontamentos, cole o endereço do projeto no Eleve CRM. Cada apontamento feito aqui cria uma tarefa no projeto, e quando é resolvido, a tarefa é concluída.' ),
			),
		),
		'1.33.1' => array(
			'date'  => '2026-10-06',
			'title' => 'Janela da reunião',
			'items' => array(
				array( '🪟', 'Clique na reunião e veja tudo', 'Ao clicar numa reunião (Agenda ou dashboard), abre uma janela com data, horário, cliente, link, participantes, equipe e a pauta/ata. Dentro dela ficam os botões Entrar na reunião, Remarcar, Editar, WhatsApp, E-mail e Excluir. A linha não abre mais o Meet direto.' ),
			),
		),
		'1.33.0' => array(
			'date'  => '2026-10-06',
			'title' => 'Winks no chat e remarcar/excluir reunião',
			'items' => array(
				array( '✨', 'Winks (como no MSN)', 'No chat, o botão ✨ manda uma animação de tela cheia para a pessoa: confete, chuva de corações, foguete ou aplausos. Ela vê na hora, e a mensagem fica no histórico para rever.' ),
				array( '📅', 'Remarcar e excluir reunião', 'Cada reunião da Agenda e do dashboard ganhou os botões Remarcar (outro dia e horário, com aviso ao cliente) e Excluir (com aviso de cancelamento). Valem também para as reuniões do Google Agenda.' ),
			),
		),
		'1.32.1' => array(
			'date'  => '2026-10-06',
			'title' => 'Recados rápidos no chat',
			'items' => array(
				array( '🍽️', 'Fui almoçar, volto já…', 'No chat da equipe, um clique em "Fui almoçar", "Volto já", "Em reunião" ou "Ausente" marca você como ausente com o recado e a hora. O botão "Voltei" limpa tudo. O recado aparece para a equipe na lista do chat.' ),
			),
		),
		'1.32.0' => array(
			'date'  => '2026-10-06',
			'title' => 'Tarefas unificadas por prazo',
			'items' => array(
				array( '🗂️', 'Tudo em Tarefas', 'A tela Tarefas junta as tarefas, os posts que estão com você (fazer arte, revisar…) e as alterações pedidas pelos clientes, em colunas: Atrasadas, Hoje, Amanhã, Esta semana e Pendentes. Arraste uma tarefa para outra coluna para mudar o prazo; o ✓ conclui.' ),
				array( '🔎', 'Escolher o cliente', 'Filtre por cliente, por pessoa (minhas, todas ou alguém da equipe) e por tipo (tarefas, posts, alterações). A tarefa nova também pode ser ligada a um cliente.' ),
				array( '🖱️', 'Dashboard clicável', 'Clique em "Atrasados" (ou em Hoje, Amanhã, Esta semana) no dashboard e abra direto aquela coluna.' ),
				array( '🎯', 'Modo foco por prazo', 'O Modo foco mostra suas tarefas juntas em Hoje, Amanhã e Esta semana, e lista as tarefas para escolher já agrupadas por prazo.' ),
			),
		),
		'1.31.0' => array(
			'date'  => '2026-10-06',
			'title' => 'Nova tela de login',
			'items' => array(
				array( '🔐', 'Login em vidro azul animado', 'Cartão de vidro com a logo da LDK no topo, "Seja bem-vindo", fundo azul com movimento e botão com brilho. Também vale para a tela do código de acesso.' ),
			),
		),
		'1.30.0' => array(
			'date'  => '2026-10-06',
			'title' => 'Novo fluxo: planejamento, alterações e envio ao cliente',
			'items' => array(
				array( '🧭', 'Planejamento → revisão → atendimento', 'A social media monta o planejamento, a administradora revisa e, ao aprovar, o atendimento recebe uma TAREFA para enviar ao cliente (e-mail + WhatsApp com mensagem pronta). O cliente não recebe nada direto da revisão.' ),
				array( '🔁', 'Pedido do cliente vira tarefa', 'Se o cliente pedir ajuste no planejamento, a tarefa vai para a social media com o comentário. Ao clicar em "Refiz", o atendimento recebe a tarefa de mandar de novo falando desse conteúdo.' ),
				array( '✏️', 'Alterações (menu)', 'Pedidos de arte e/ou legenda (por texto, áudio ou referência) viram tarefa com prazo para o designer e/ou a social media, e aparecem na aba Alterações com contador. Ao terminar, o atendimento é avisado para reenviar.' ),
				array( '📤', 'Enviar ao cliente (menu)', 'Uma aba só com tudo que está pronto: planejamentos revisados, ajustes refeitos e a semana fechada, com e-mail e WhatsApp em um clique.' ),
				array( '✅', 'Semana fechada avisa o atendimento', 'Quando todo o conteúdo da semana de um cliente está com arte e legenda, o atendimento ganha a tarefa de enviar a semana. Aprovado tudo, vai para agendado e publica sozinho.' ),
				array( '🎬', 'Vídeo: capa e roteiros do dia', 'Ao salvar um vídeo sem capa, aparece um aviso perguntando se usa a capa automática. Roteiros do mesmo dia seguem juntos para o cliente. A prévia da arte aparece assim que sobe.' ),
				array( '⭕', 'Logo do cliente em círculo perfeito', 'A logo aparece centralizada, sem margem nem fundo branco.' ),
			),
		),
		'1.29.0' => array(
			'date'  => '2026-10-06',
			'title' => 'Pacotes com artes e vídeos, e dinheiro só com permissão',
			'items' => array(
				array( '📦', 'Pacote com artes e vídeos', 'Em Configurações → Contrato, cada pacote ganha a quantidade de vídeos (5º campo). Ao cadastrar o cliente, escolha o pacote e as quantidades de artes e de vídeos se preenchem (dá para ajustar para aquele cliente).' ),
				array( '📊', 'Planejamento mostra o pacote', 'No planejamento aparece o pacote cadastrado (ex.: 16 artes + 4 vídeos por mês) e duas barrinhas: quantas artes e quantos vídeos já foram planejados no mês.' ),
				array( '🔒', 'Dinheiro só com permissão', 'Equipe sem a permissão Financeiro não vê nenhum valor em reais no painel (mensalidade, contratos, propostas, funil, receita). Você libera pessoa por pessoa em Equipe.' ),
			),
		),
		'1.28.0' => array(
			'date'  => '2026-10-06',
			'title' => 'Roteiros de vídeo, pausar, collab e ajustes por áudio',
			'items' => array(
				array( '🎬', 'Aba Roteiros', 'Todo post de vídeo ganha um roteiro, com dia e hora da gravação. O cliente vê todos os roteiros e vídeos dele no painel dele e aprova por link. 3 dias antes da gravação ele recebe o roteiro por e-mail e a atendente ganha uma tarefa para avisar no WhatsApp e pedir a aprovação.' ),
				array( '⏸️', 'Pausar postagem', 'Botão para pausar um post: ele não sai sozinho até você retomar.' ),
				array( '🤝', 'Collab', 'Campo de collab no post: o perfil convidado recebe o convite ao publicar no Instagram.' ),
				array( '🎙️', 'Ajuste por áudio e referência', 'O cliente pode gravar um áudio e anexar imagem, PDF ou vídeo ao pedir ajuste ou fazer apontamento. O ajuste pode ser na arte, na legenda ou nos dois.' ),
				array( '🖼️', 'Carrossel de até 20 e arte salva sozinha', 'A arte é salva assim que termina de subir. Carrossel aceita até 20 itens.' ),
				array( '🗓️', 'Enviar a semana', 'No menu Enviar a semana, escolha o cliente e mande todas as artes prontas da semana de uma vez, com o link de cada uma (e-mail e WhatsApp). O cliente aprova uma e já abre a próxima.' ),
				array( '📲', 'WhatsApp em tudo', 'Botão de enviar no WhatsApp no planejamento, na arte, na reunião e no roteiro, com mensagem organizada.' ),
				array( '📅', 'Equipe na reunião', 'Marque quem da equipe participa; todos são avisados. Reunião registrada pode ser enviada ao cliente por e-mail e WhatsApp.' ),
			),
		),
		'1.16.0' => array(
			'date'  => '2026-10-05',
			'title' => 'Dashboard personalizável',
			'items' => array(
				array( '🧩', 'Escolha o que aparece', 'No botão Personalizar do dashboard, cada pessoa marca os blocos que quer ver e muda a ordem com as setas. Dá para voltar ao padrão quando quiser.' ),
				array( '🔒', 'Respeita as permissões', 'Só aparecem as opções que o acesso da pessoa permite (financeiro, clientes, leads, metas…). Se a permissão mudar, o bloco some sozinho.' ),
				array( '➕', 'Blocos novos', 'Datas e feriados próximos, ranking do mês, contratos pendentes e funil de leads.' ),
			),
		),
		'1.15.0' => array(
			'date'  => '2026-10-05',
			'title' => 'IA do Gemini no CRM',
			'items' => array(
				array( '✨', 'Ajuda na publicação', 'No post: sugerir 3 legendas (com tom de voz à escolha), melhorar o texto, sugerir hashtags e gerar o briefing da arte para o designer.' ),
				array( '🗓️', 'Ideias do mês no planejamento', 'A IA monta as ideias do mês com o briefing do cliente, feriados e datas, e o que já foi postado. Você edita e adiciona as escolhidas direto ao planejamento.' ),
				array( '💬', 'Mensagem de abordagem', 'Na prospecção, gera a primeira mensagem (WhatsApp ou e-mail) para a empresa marcada, pronta para copiar ou abrir no WhatsApp.' ),
				array( '🔎', 'Revisão e ideias com Gemini', 'A revisão de texto e as ideias de conteúdo passam a usar o Gemini. Chave e modelo em Configurações → IA, com botão de teste.' ),
			),
		),
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

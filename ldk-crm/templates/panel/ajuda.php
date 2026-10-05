<?php
/**
 * Tutorial: para que serve cada tela (texto curto, com o caminho do dia a dia).
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
$t = array(
	'O caminho de um post' => array(
		'🔄',
		array(
			'<strong>Planejamento</strong> (social media + atendimento): no começo do mês, clique em <em>▶ Start</em> no cliente, confira o briefing e as datas do mês e crie os posts com tema, legenda e dia. Sem arte.',
			'<strong>Revisão interna</strong>: "Enviar para a revisão" avisa quem revisa (Configurações → Conteúdo). Com o ok dela, o planejamento vai sozinho para o painel e o e-mail da cliente. Mande também pelo WhatsApp com 1 clique.',
			'<strong>A cliente responde</strong>: 👍 ou ✏️ em cada post. Os aprovados já vão para o Design, com prazo e com o designer avisado. Os com ajuste voltam para a equipe, que ajusta e reenvia.',
			'<strong>Design</strong>: o designer sobe a arte e clica "Arte pronta → Revisão".',
			'<strong>Revisão</strong>: quem revisa confere e manda para a cliente aprovar a arte.',
			'<strong>Cliente aprova a arte</strong> (👍 na prévia do post) → fica <strong>Agendado</strong> e publica sozinho no dia e hora.',
		),
	),
	'Dashboard' => array(
		'🏠',
		array(
			'"Comigo agora" mostra o que está com você, em ordem de prazo. "Atrasados" é o que passou do prazo da etapa.',
			'O botão <em>Atualizar</em> traz os números novos sem recarregar (e atualiza sozinho a cada 3 minutos).',
		),
	),
	'Conteúdo e prazos' => array(
		'📅',
		array(
			'Calendário, Kanban e Lista mostram os mesmos posts. Arraste para mudar o dia ou a etapa.',
			'Cada post tem prazo de <strong>Design</strong>, <strong>Revisão</strong> e <strong>Aprovação do cliente</strong>, calculados pela data da publicação (Configurações → Conteúdo). Dá para mudar no post.',
			'O sininho avisa 24 h antes e quando o prazo vence.',
		),
	),
	'Clientes, cadastro e contrato' => array(
		'🤝',
		array(
			'<strong>Novo cliente</strong>: só o nome e o projeto. Copie o link e mande: a cliente preenche os dados, cria a senha e a equipe é avisada.',
			'Na ficha: responsáveis, pacote de artes, mensalidade, redes conectadas e briefing.',
			'<strong>Contratos</strong> (na ficha): "+ Novo contrato" → serviços, valor, meses e início → confira o texto → "Enviar para assinatura". A cliente confirma um código no e-mail e desenha a assinatura; depois você assina pela agência. Os dois recebem a cópia com o certificado.',
		),
	),
	'Propostas' => array(
		'📄',
		array(
			'Monte a proposta com o nicho e os serviços, clique em "Preencher textos automaticamente" e gere o link. Envie por WhatsApp ou e-mail e veja quando a cliente abriu.',
			'Serviços, nichos, perfis de clientes e textos padrão ficam em "Serviços das propostas".',
		),
	),
	'Relatórios' => array(
		'📊',
		array(
			'Gere o relatório do mês: seguidores, alcance, visualizações, todos os posts com curtidas/comentários/salvamentos e, se liberado, o tráfego pago com o saldo da carteira.',
			'Escreva a análise e envie. A cliente vê em telas deslizando, como a proposta.',
		),
	),
	'Financeiro' => array(
		'💰',
		array(
			'<strong>Lançamento</strong>: receita ou saída, com vencimento e status (pago/pendente).',
			'<strong>Contas fixas da agência</strong>: aluguel, softwares, salário de cada pessoa da equipe, videomaker, contador. Um clique abre o lançamento já como conta recorrente: os próximos meses aparecem sozinhos.',
			'<strong>Mensalidades e cobranças</strong>: o valor e o dia de cada cliente. "Cobrar" manda o e-mail e abre o WhatsApp com o link de pagamento.',
			'O gráfico mostra o recebido x gasto dos últimos 6 meses, e "Gastos por categoria" mostra para onde vai o dinheiro.',
		),
	),
	'Equipe' => array(
		'👥',
		array(
			'Mande o <strong>link de cadastro da equipe</strong>: a pessoa preenche tudo e cria a senha. Ela só entra depois que você aprovar (se o link vazar, gere outro).',
			'Cada função (designer, social media, atendimento, videomaker, tráfego, financeiro) já vem com as permissões certas.',
			'Em "Minha conta" (clique no seu nome, embaixo à esquerda) cada pessoa troca a senha e os dados.',
		),
	),
	'Chat, sala de voz e chamar atenção' => array(
		'💬',
		array(
			'A bolinha no canto abre o chat: Geral, conversas individuais e clientes. Dá para mandar áudio e emojis animados.',
			'<strong>Sala de voz</strong>: todo mundo conectado; quem precisa falar liga o microfone (ou segura a barra de espaço).',
			'<strong>⚡ Chamar atenção</strong>: treme a tela de todos que estão online, com som e o seu aviso.',
		),
	),
	'Modo foco' => array(
		'🎯',
		array(
			'Escolha o cliente: os posts dele aparecem na ordem do prazo mais urgente. Marque o que vai fazer e entre em foco com o timer.',
		),
	),
	'Feedback, Apontar e Novidades' => array(
		'📝',
		array(
			'<strong>Feedback</strong>: registre problemas, ideias e dúvidas do dia a dia.',
			'<strong>Apontar</strong>: marca um ponto na tela com um comentário (bom na fase de testes). Liga e desliga em Apontamentos.',
			'<strong>Novidades</strong>: o que mudou a cada atualização do sistema.',
		),
	),
);
lk_panel_start( 'Tutorial', 'ajuda' );
?>
<p class="muted hint">Para que serve cada parte do sistema. Clique para abrir.</p>
<div class="help">
	<?php $i = 0; ?>
	<?php foreach ( $t as $title => $sec ) : ?>
		<details class="card help-item"<?php echo 0 === $i++ ? ' open' : ''; ?>>
			<summary><span class="help-ic" aria-hidden="true"><?php echo esc_html( $sec[0] ); ?></span><strong><?php echo esc_html( $title ); ?></strong></summary>
			<ul><?php foreach ( $sec[1] as $li ) : ?><li><?php echo wp_kses( $li, array( 'strong' => array(), 'em' => array() ) ); ?></li><?php endforeach; ?></ul>
		</details>
	<?php endforeach; ?>
</div>
<?php
lk_panel_end();

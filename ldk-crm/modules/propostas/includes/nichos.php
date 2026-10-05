<?php
/**
 * Nichos que vêm prontos na primeira ativação.
 * Cada texto usa {cliente_curto}, trocado pelo nome do cliente na proposta.
 * A copy fala de presença digital como um todo (redes, vídeos, anúncios e site),
 * então funciona tanto para os pacotes mensais quanto para os projetos de site.
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Monta um nicho a partir dos textos, na ordem dos campos.
 */
function ldkp_niche( $title, $capa, $frase, $cenario, $oportunidade, $diagnostico, $contato_titulo, $contato_texto ) {
	return array(
		'title'          => $title,
		'capa_desc'      => $capa,
		'intro_frase'    => $frase,
		'cenario'        => $cenario,
		'oportunidade'   => $oportunidade,
		'diag_titulo'    => 'O que a presença digital da {cliente_curto} precisa resolver.',
		'diagnostico'    => implode( "\n", $diagnostico ),
		'contato_titulo' => $contato_titulo,
		'contato_texto'  => $contato_texto,
	);
}

function ldkp_default_niches() {
	return array(

		'lojas-de-veiculos' => ldkp_niche(
			'Lojas de veículos',
			'Marketing que coloca o estoque da {cliente_curto} na frente de quem está pronto para trocar de carro: conteúdo que gera desejo, vídeos que vendem e anúncios que fazem o WhatsApp tocar.',
			'a venda de um carro começa muito antes do cliente pisar na loja. Ela começa no celular, e é lá que a sua marca precisa ser a primeira escolha.',
			'Quem vai comprar um carro passa semanas pesquisando: compara preço, fotos, quilometragem e reputação no Instagram, nos portais e no Google. Nesse caminho vence a loja que aparece mais, transmite mais confiança e responde mais rápido. Cada dia sem constância é um cliente que fecha negócio no concorrente.',
			'A {cliente_curto} já tem o que mais importa: estoque, credibilidade e atendimento. Com redes ativas, vídeos que mostram os carros de verdade e anúncios bem segmentados na região, cada veículo ganha vitrine e a loja passa a receber contatos de quem já chega decidido e com o financiamento na cabeça.',
			array(
				'Estoque sem vitrine | Carros bons parados no pátio porque não aparecem com frequência para quem está procurando agora.',
				'Dependência dos portais | Boa parte dos contatos vem de plataformas pagas, onde a {cliente_curto} disputa atenção lado a lado com a concorrência.',
				'Vídeo é o que vende carro | Detalhes, interior, som do motor e test-drive. Sem vídeo, o cliente não sente o carro e não sai de casa.',
				'Anúncios para quem vai comprar | Campanhas no Meta Ads e no Google Ads para quem está na região e pesquisando o modelo, com o contato caindo direto no WhatsApp.',
				'Confiança antes da visita | Entregas, depoimentos e a equipe à mostra, para o cliente saber que está negociando com uma loja séria.',
			),
			'Vamos acelerar as vendas da {cliente_curto}?',
			'Com conteúdo constante, vídeos que mostram cada carro e anúncios bem direcionados, a {cliente_curto} passa a vender também fora do horário da loja. O próximo passo é aprovar a proposta e colocar o plano em movimento.'
		),

		'advocacia' => ldkp_niche(
			'Advocacia',
			'Posicionamento digital sóbrio e estratégico para a {cliente_curto} ser lembrada como referência, gerar contatos qualificados e crescer dentro das regras da OAB.',
			'quem defende os interesses dos clientes precisa transmitir, no digital, a mesma segurança que transmite no escritório.',
			'Quem precisa de um advogado está diante de um problema sério e pesquisa com cuidado antes de ligar. Ele procura o nome no Google, olha o Instagram e decide em quem confiar. Um perfil parado ou um site genérico fazem o cliente seguir para o próximo resultado, muitas vezes um escritório menos preparado que o seu.',
			'Com conteúdo que explica direitos de forma simples, vídeos que aproximam o cliente de quem vai cuidar do caso e um site bem posicionado, a {cliente_curto} vira referência nas suas áreas de atuação e recebe contatos de quem já chega informado. Tudo dentro do Provimento 205/2021 da OAB.',
			array(
				'Autoridade antes do primeiro contato | Conteúdo educativo que prova conhecimento e faz o cliente confiar antes mesmo da consulta.',
				'Ser encontrado por quem precisa | Site e Google Meu Negócio otimizados para aparecer quando alguém pesquisa o problema que você resolve.',
				'Proximidade sem perder a sobriedade | Vídeos curtos que humanizam o escritório com linguagem clara e elegante.',
				'Dentro do Provimento 205/2021 | Comunicação informativa, sem captação indevida e sem promessa de resultado, como exige a OAB.',
			),
			'Vamos posicionar a {cliente_curto} como referência?',
			'Autoridade, clareza e constância: a presença digital que transmite a segurança que o seu cliente procura e traz contatos qualificados para o escritório.'
		),

		'cosmeticos' => ldkp_niche(
			'Cosméticos e perfumaria',
			'Conteúdo que desperta desejo, vídeos que mostram o produto em ação e anúncios que transformam scroll em venda para a {cliente_curto}.',
			'cosmético se vende pelos olhos. E hoje os olhos da sua cliente estão no celular.',
			'A cliente descobre um produto em um vídeo, pesquisa as avaliações, compara preços e compra de quem transmitiu mais confiança e desejo. Marcas que não aparecem com frequência são esquecidas em poucos dias, por melhor que seja a fórmula.',
			'Com fotos e artes que valorizam cada produto, vídeos de textura, aplicação e resultado, e campanhas para o público certo, a {cliente_curto} ganha desejo de marca, aumenta o ticket e vende todos os dias, no site, no WhatsApp ou na loja.',
			array(
				'Produto que dá vontade de ter | Fotos, artes e vídeos que mostram textura, cor e resultado com padrão de marca grande.',
				'Prova social que vende | Avaliações, depoimentos e conteúdo de clientes reais para derrubar a desconfiança na hora da compra.',
				'Anúncios que convertem | Campanhas segmentadas por interesse e comportamento, levando direto para a compra ou o WhatsApp.',
				'Lançamentos e datas especiais | Calendário estratégico para Dia das Mães, Black Friday e Natal, sem deixar dinheiro na mesa.',
			),
			'Vamos colocar a {cliente_curto} no carrinho das clientes?',
			'Desejo de marca, conteúdo que vende e anúncios que convertem. É só aprovar para começarmos.'
		),

		'medicos' => ldkp_niche(
			'Médicos e clínicas',
			'Presença digital que transmite a mesma confiança do consultório: conteúdo que educa, posiciona a {cliente_curto} como referência e leva o paciente direto para o agendamento.',
			'quem cuida da saúde das pessoas precisa de uma comunicação que transmita cuidado e autoridade em cada detalhe.',
			'Antes de marcar a primeira consulta, o paciente pesquisa: olha o Instagram, lê avaliações no Google e compara profissionais. Um perfil parado ou sem padrão passa insegurança, e ele agenda com quem parece mais preparado, mesmo que não seja.',
			'Com conteúdo educativo, vídeos que aproximam o paciente e anúncios para quem busca a especialidade na região, a {cliente_curto} vira referência e enche a agenda, sempre dentro das normas do CFM.',
			array(
				'Autoridade antes da consulta | Conteúdo que mostra formação, especialidade e cuidado, para o paciente confiar antes de entrar no consultório.',
				'Constância nas redes | Perfil sempre ativo, com linha editorial clara e identidade visual consistente.',
				'Pacientes da região | Anúncios segmentados e Google Meu Negócio para quem está perto e procurando a sua especialidade agora.',
				'Dentro das normas do CFM | Comunicação ética e informativa, sem promessa de resultado e sem antes e depois.',
			),
			'Vamos encher a agenda da {cliente_curto}?',
			'Uma comunicação à altura do cuidado que você entrega: transmite confiança, educa o paciente e leva direto para o agendamento.'
		),

		'odontologia' => ldkp_niche(
			'Odontologia',
			'Marketing que faz o paciente escolher a {cliente_curto} antes mesmo da avaliação: conteúdo que gera confiança, vídeos que mostram a estrutura e anúncios para quem busca tratamento na região.',
			'quem transforma sorrisos precisa causar a mesma boa primeira impressão no digital.',
			'Implante, lente, ortodontia e clareamento são decisões caras e pesquisadas. O paciente compara clínicas no Instagram e no Google, procura estrutura, equipe e sinais de que vai ser bem atendido. Quem não aparece, ou aparece mal, perde o paciente para a clínica ao lado.',
			'Com conteúdo que explica cada tratamento, vídeos da estrutura e da equipe e campanhas para quem está procurando o procedimento agora, a {cliente_curto} lota a agenda de avaliações e aumenta o número de tratamentos fechados.',
			array(
				'Cada tratamento bem explicado | Conteúdo que tira dúvidas e tira o medo, aproximando o paciente da decisão.',
				'Estrutura e equipe à mostra | Vídeos que transmitem o padrão da clínica e humanizam o atendimento.',
				'Avaliações agendadas | Anúncios por tratamento e região, com o contato caindo direto no WhatsApp da recepção.',
				'Dentro das normas do CFO | Comunicação ética e informativa, respeitando as regras de publicidade odontológica.',
			),
			'Bora colocar o sorriso da {cliente_curto} em evidência?',
			'Conteúdo que gera confiança, vídeos que mostram a clínica e anúncios que enchem a agenda de avaliações.'
		),

		'estetica' => ldkp_niche(
			'Clínicas de estética',
			'Conteúdo que encanta, vídeos que mostram a experiência e anúncios que transformam seguidoras em clientes agendadas na {cliente_curto}.',
			'quem cuida da autoestima das clientes precisa de um Instagram que encante já no primeiro scroll.',
			'Na estética, a decisão é visual e emocional. A cliente descobre o espaço no Instagram, olha o feed, assiste aos stories e só então chama no direct. Se o perfil não reflete o padrão do atendimento, ela segue rolando e agenda em outro lugar.',
			'Com um feed alinhado à identidade da {cliente_curto}, vídeos de procedimentos e bastidores e anúncios para o público certo da região, o perfil vira a principal porta de entrada de novas clientes e a agenda fica previsível.',
			array(
				'Feed que encanta | Identidade visual consistente e artes que transmitem o padrão do espaço.',
				'Vídeos que geram desejo | Procedimentos, rotina e bastidores que aproximam e dão segurança à cliente.',
				'Prova social | Depoimentos e avaliações que mostram a experiência real de quem já é cliente.',
				'Agenda previsível | Anúncios segmentados por região e interesse, levando direto para o WhatsApp.',
			),
			'Bora colocar a {cliente_curto} em evidência?',
			'Um Instagram que encanta, vídeos que geram desejo e anúncios que lotam a agenda. É só aprovar para começarmos.'
		),

		'saloes-barbearias' => ldkp_niche(
			'Salões de beleza e barbearias',
			'Agenda cheia de segunda a sábado: conteúdo que mostra o talento da equipe, vídeos que viralizam e anúncios que trazem clientes novos para a {cliente_curto}.',
			'o trabalho de vocês já fala por si. Agora ele precisa ser visto por quem ainda não conhece.',
			'Cliente de salão e barbearia escolhe pelo que vê: cortes, cores, ambiente e atendimento. Ele descobre no Instagram, confere os resultados e marca com quem mostra mais. Quem posta de vez em quando fica dependendo só da clientela antiga.',
			'Com vídeos de transformação, bastidores que mostram a vibe do espaço e anúncios para quem está no bairro, a {cliente_curto} lota os horários vazios, ganha clientes fiéis e passa a cobrar pelo valor que entrega.',
			array(
				'Transformações que viralizam | Vídeos curtos de antes e depois do corte, da cor e do penteado, feitos para o Reels.',
				'Horários vazios preenchidos | Campanhas para os dias e horários de menor movimento.',
				'Equipe como vitrine | Cada profissional com o seu estilo em destaque, fortalecendo a marca do espaço.',
				'Agendamento fácil | Anúncios e perfil levando direto para o WhatsApp ou para a agenda online.',
			),
			'Vamos lotar a agenda da {cliente_curto}?',
			'Vídeos que mostram o talento da equipe e anúncios para quem está perto. É só aprovar para começarmos.'
		),

		'academias' => ldkp_niche(
			'Academias e estúdios',
			'Mais matrículas e menos cancelamentos: conteúdo que motiva, vídeos que mostram a estrutura e anúncios que trazem alunos novos para a {cliente_curto} o ano inteiro.',
			'quem transforma a vida dos alunos precisa de uma comunicação com a mesma energia do treino.',
			'A decisão de começar a treinar é emocional e costuma ser adiada. O aluno segue academias no Instagram por meses antes de se matricular, e escolhe a que mais o motiva e transmite resultado. Quem só aparece em janeiro perde os outros onze meses.',
			'Com conteúdo que motiva, vídeos de aulas e resultados de alunos e campanhas para quem mora ou trabalha perto, a {cliente_curto} mantém um fluxo constante de matrículas e fortalece a comunidade que faz o aluno ficar.',
			array(
				'Matrículas o ano inteiro | Campanhas contínuas, não só em janeiro e antes do verão.',
				'Estrutura e equipe à mostra | Vídeos de aulas, equipamentos e professores que tiram a insegurança do aluno novo.',
				'Comunidade que retém | Conteúdo que celebra alunos e cria senso de pertencimento, reduzindo cancelamentos.',
				'Aula experimental agendada | Anúncios levando direto para o WhatsApp com a oferta certa.',
			),
			'Vamos encher a {cliente_curto} de alunos?',
			'Conteúdo com energia, vídeos que mostram a estrutura e anúncios que trazem matrículas o ano inteiro.'
		),

		'nutricao' => ldkp_niche(
			'Nutricionistas',
			'Autoridade que vira consulta: conteúdo que educa, vídeos que aproximam e anúncios que trazem pacientes para a {cliente_curto}, dentro das regras do CFN.',
			'quem muda a relação das pessoas com a comida precisa ser a voz mais confiável no feed dos pacientes.',
			'O paciente consome conteúdo de nutrição todos os dias, muitas vezes de quem não é profissional. Ele escolhe com quem se consultar pela clareza, pela proximidade e pela confiança que o perfil transmite. Sem constância, a sua voz se perde no meio de tanta informação errada.',
			'Com conteúdo educativo e prático, vídeos que mostram o seu jeito de atender e campanhas para o público certo, a {cliente_curto} se destaca como referência e transforma seguidores em pacientes, presenciais ou online.',
			array(
				'Autoridade no meio do ruído | Conteúdo técnico e acessível que diferencia você de quem não é profissional.',
				'Proximidade que gera confiança | Vídeos que mostram o seu método e a sua forma de atender.',
				'Consultas online e presenciais | Anúncios segmentados para atrair pacientes da região e de outras cidades.',
				'Dentro das normas do CFN | Comunicação ética, sem promessa de resultado e sem antes e depois.',
			),
			'Vamos transformar seguidores em pacientes da {cliente_curto}?',
			'Autoridade, proximidade e constância para a sua agenda crescer de forma previsível.'
		),

		'psicologia' => ldkp_niche(
			'Psicólogos e terapeutas',
			'Uma presença digital acolhedora e profissional, que aproxima quem precisa de ajuda e enche a agenda da {cliente_curto} com ética e sensibilidade.',
			'quem cuida da saúde emocional das pessoas precisa de uma comunicação que acolha antes mesmo da primeira sessão.',
			'Buscar terapia é um passo difícil. A pessoa pesquisa em silêncio, lê, assiste e só procura quando sente que vai ser acolhida. Um perfil frio ou parado não cria essa conexão, e ela adia mais uma vez ou procura outro profissional.',
			'Com conteúdo que informa e acolhe, vídeos que mostram quem você é e anúncios cuidadosos para o público certo, a {cliente_curto} cria a conexão que faz a pessoa dar o primeiro passo, no consultório ou no atendimento online.',
			array(
				'Conexão antes da primeira sessão | Conteúdo acolhedor que faz a pessoa se sentir compreendida.',
				'Clareza sobre a abordagem | Explicar como você trabalha e para quem é o seu atendimento.',
				'Atendimento online sem fronteiras | Campanhas para atrair pacientes de outras cidades.',
				'Dentro do Código de Ética do CFP | Comunicação informativa, sem promessa de resultado e com todo o cuidado que o tema exige.',
			),
			'Vamos aproximar a {cliente_curto} de quem precisa?',
			'Uma comunicação acolhedora, ética e constante para a sua agenda crescer com as pessoas certas.'
		),

		'restaurantes' => ldkp_niche(
			'Restaurantes e delivery',
			'Conteúdo que dá fome, vídeos que mostram a experiência e anúncios que enchem as mesas e os pedidos da {cliente_curto}.',
			'comida boa merece ser vista. E quem é visto todos os dias é lembrado na hora da fome.',
			'A decisão de onde comer é tomada em segundos, quase sempre no celular. O cliente vê um vídeo, lembra da marca e pede ou vai até lá. Quem não aparece com frequência simplesmente não entra na lista, por melhor que seja o prato.',
			'Com vídeos que mostram os pratos e o ambiente, promoções bem comunicadas e anúncios para quem está por perto, a {cliente_curto} vira a primeira lembrança do bairro e aumenta o movimento nos dias mais fracos.',
			array(
				'Ser lembrado na hora da fome | Presença diária nas redes, com conteúdo que dá vontade de pedir agora.',
				'Vídeos que vendem o prato | Preparo, bastidores e ambiente em vídeos curtos, feitos para o Reels.',
				'Movimento nos dias fracos | Campanhas e promoções pensadas para os dias e horários de menor movimento.',
				'Público da região | Anúncios para quem está perto, levando para o delivery, a reserva ou o WhatsApp.',
			),
			'Vamos encher as mesas da {cliente_curto}?',
			'Conteúdo que dá fome, vídeos que mostram a experiência e anúncios para quem está por perto. É só aprovar para começarmos.'
		),

		'confeitarias' => ldkp_niche(
			'Confeitarias e doces',
			'Doces que já chegam vendidos: fotos e vídeos de dar água na boca e anúncios que enchem a agenda de encomendas da {cliente_curto}.',
			'doce bonito se vende sozinho. Desde que as pessoas certas vejam.',
			'Bolo de aniversário, doces para casamento e presentes são escolhidos pelo Instagram. A cliente olha o feed, se apaixona e encomenda. Quem posta foto escura ou some das redes perde encomendas que poderiam lotar o mês inteiro.',
			'Com fotos e vídeos que valorizam cada detalhe, calendário estratégico para as datas que mais vendem e anúncios para a sua região, a {cliente_curto} lota a agenda de encomendas e passa a cobrar pelo valor que o seu trabalho tem.',
			array(
				'Foto que dá água na boca | Artes e vídeos que valorizam cada detalhe do doce.',
				'Datas que mais vendem | Páscoa, Dia das Mães, festas e Natal planejados com antecedência.',
				'Encomendas pelo WhatsApp | Anúncios e perfil levando direto para o pedido, com mensagem pronta.',
				'Valor percebido | Uma marca bem apresentada que justifica o preço de um trabalho artesanal.',
			),
			'Vamos lotar a agenda de encomendas da {cliente_curto}?',
			'Conteúdo de dar água na boca e anúncios para quem está perto. É só aprovar para começarmos.'
		),

		'moda' => ldkp_niche(
			'Moda e lojas de roupa',
			'Coleções que esgotam: conteúdo que inspira, vídeos com provador e looks e anúncios que levam clientes para a loja física e o online da {cliente_curto}.',
			'moda é desejo. E desejo nasce no feed, muito antes do provador.',
			'A cliente descobre looks no Instagram, salva, compara e compra de quem inspira mais. Lojas que só postam foto de cabide perdem para marcas que mostram o look no corpo, com estilo e personalidade.',
			'Com conteúdo que inspira, vídeos de looks e novidades e campanhas para o público certo, a {cliente_curto} vira referência de estilo na região, gira o estoque mais rápido e vende todos os dias, na loja e no WhatsApp.',
			array(
				'Looks que inspiram | Fotos e vídeos com modelos e combinações que fazem a cliente se imaginar vestindo.',
				'Estoque girando | Campanhas para lançamentos, reposições e peças paradas.',
				'Venda pelo WhatsApp | Anúncios e catálogo levando direto para a vendedora.',
				'Calendário de vendas | Coleções, liquidações e datas comemorativas planejadas com antecedência.',
			),
			'Vamos colocar a {cliente_curto} no guarda-roupa da cidade?',
			'Conteúdo que inspira, vídeos que vendem e anúncios que giram o estoque. É só aprovar para começarmos.'
		),

		'imobiliarias' => ldkp_niche(
			'Imobiliárias e corretores',
			'Mais leads qualificados para a {cliente_curto}: imóveis apresentados como merecem, vídeos que vendem e anúncios para quem está pronto para comprar ou alugar.',
			'quem ajuda as pessoas a encontrar o lugar certo para viver precisa ser encontrado primeiro.',
			'O cliente passa meses pesquisando imóveis no celular. Ele compara fotos, bairros e imobiliárias e só chama quem transmite confiança. Leads soltos e curiosos ocupam o time e não viram contrato.',
			'Com vídeos e tours de cada imóvel, conteúdo que mostra os bairros e a equipe e campanhas segmentadas por perfil e momento de compra, a {cliente_curto} recebe contatos mais qualificados e encurta o caminho até a assinatura.',
			array(
				'Leads qualificados | Campanhas para quem tem perfil e momento de compra ou locação, não só curiosos.',
				'O imóvel como protagonista | Vídeos e tours que fazem o cliente se imaginar morando ali.',
				'Autoridade na região | Conteúdo sobre bairros, mercado e financiamento que posiciona a {cliente_curto} como especialista.',
				'Atendimento rápido | Anúncios levando direto para o WhatsApp do corretor responsável.',
			),
			'Vamos acelerar os negócios da {cliente_curto}?',
			'Leads mais qualificados, imóveis bem apresentados e uma marca que transmite confiança.'
		),

		'construtoras' => ldkp_niche(
			'Construtoras e incorporadoras',
			'Marketing que vende o empreendimento antes da obra terminar: lançamentos com impacto, vídeos que mostram a evolução e campanhas que geram leads qualificados para a {cliente_curto}.',
			'quem constrói o sonho da casa própria precisa de uma comunicação à altura desse sonho.',
			'Comprar na planta é uma decisão longa e cara, baseada em confiança. O cliente acompanha a construtora por meses, busca histórico de entregas e só assina quando se sente seguro. Sem uma comunicação sólida, o empreendimento compete apenas por preço.',
			'Com planejamento de pré-lançamento e lançamento, vídeos da obra e das entregas e campanhas segmentadas por renda e região, a {cliente_curto} gera uma lista de interessados antes da abertura das vendas e acelera a velocidade de vendas.',
			array(
				'Lançamentos com impacto | Pré-lançamento que gera lista de interessados antes da abertura das vendas.',
				'Solidez que gera confiança | Evolução da obra, entregas e equipe à mostra em conteúdo constante.',
				'Leads qualificados | Campanhas segmentadas por renda, região e momento de compra.',
				'Site do empreendimento | Página própria com plantas, diferenciais e contato direto com o comercial.',
			),
			'Vamos acelerar as vendas da {cliente_curto}?',
			'Lançamentos com impacto, uma marca que transmite solidez e leads que viram contrato.'
		),

		'arquitetura' => ldkp_niche(
			'Arquitetura e interiores',
			'Um portfólio digital à altura dos seus projetos: conteúdo que comunica o seu estilo e atrai clientes com o perfil certo para a {cliente_curto}.',
			'quem projeta espaços com tanto cuidado precisa de uma vitrine digital no mesmo nível.',
			'Na arquitetura, o cliente contrata pelo que vê. Antes de pedir um orçamento, ele analisa o portfólio, o estilo e o acabamento. Um Instagram sem curadoria mistura tudo e atrai quem só quer preço.',
			'Com projetos apresentados com curadoria, vídeos de antes e depois e dos bastidores da obra e campanhas para o público com o perfil certo, a {cliente_curto} filtra quem não combina e atrai clientes que valorizam o projeto.',
			array(
				'O projeto como protagonista | Fotos, vídeos e antes e depois com curadoria e respiro.',
				'Posicionamento de estilo | Conteúdo que deixa claro o seu estilo e o tipo de cliente que você atende.',
				'Processo explicado | Como funciona do briefing à entrega, para o cliente entender o valor do projeto.',
				'Clientes com o perfil certo | Campanhas segmentadas por região, renda e interesse.',
			),
			'Vamos projetar a presença digital da {cliente_curto}?',
			'Uma vitrine à altura dos seus projetos: comunica o seu estilo e atrai o cliente certo.'
		),

		'contabilidade' => ldkp_niche(
			'Contabilidade',
			'Autoridade que gera clientes: conteúdo que simplifica o complexo, vídeos que humanizam o escritório e campanhas que trazem empresas para a {cliente_curto}.',
			'quem cuida da saúde financeira das empresas precisa mostrar que é muito mais do que guias e impostos.',
			'O empresário troca de contador quando sente que não tem um parceiro estratégico. Ele procura quem explica com clareza, responde rápido e ajuda a pagar menos imposto dentro da lei. Escritórios invisíveis no digital competem só por preço.',
			'Com conteúdo que traduz o complexo em linguagem simples, vídeos que mostram a equipe e campanhas para empresários da região, a {cliente_curto} se posiciona como parceira estratégica e atrai clientes que valorizam o serviço.',
			array(
				'Do técnico ao simples | Conteúdo que explica impostos, MEI e abertura de empresa sem juridiquês.',
				'Parceiro, não despachante | Posicionamento que mostra o valor estratégico do escritório.',
				'Empresas da região | Campanhas para quem está abrindo ou quer trocar de contador.',
				'Atendimento humanizado | Vídeos que mostram as pessoas por trás do escritório.',
			),
			'Vamos multiplicar os clientes da {cliente_curto}?',
			'Autoridade, clareza e constância para atrair empresas que valorizam um contador estratégico.'
		),

		'escolas' => ldkp_niche(
			'Escolas e educação',
			'Mais matrículas e famílias encantadas: conteúdo que mostra o dia a dia, vídeos que emocionam e campanhas estratégicas para o período de matrículas da {cliente_curto}.',
			'quem forma pessoas precisa mostrar às famílias, todos os dias, o cuidado que existe dentro da escola.',
			'Escolher uma escola é uma das decisões mais importantes para uma família. Os pais pesquisam por meses, seguem as escolas no Instagram e observam o dia a dia antes de agendar uma visita. Quem não mostra a rotina perde para quem mostra.',
			'Com conteúdo do dia a dia, vídeos de projetos e eventos e campanhas no momento certo do ano, a {cliente_curto} fortalece a relação com as famílias atuais e enche a agenda de visitas no período de matrículas.',
			array(
				'O dia a dia como vitrine | Projetos, aulas e eventos que mostram a proposta pedagógica na prática.',
				'Campanha de matrículas | Planejamento estratégico para o período que define o ano da escola.',
				'Visitas agendadas | Anúncios para famílias da região, levando direto para o agendamento.',
				'Famílias engajadas | Conteúdo que fortalece o vínculo e transforma pais em promotores da escola.',
			),
			'Vamos encher as salas da {cliente_curto}?',
			'Conteúdo que emociona, campanhas no momento certo e mais famílias conhecendo a escola.'
		),

		'cursos-online' => ldkp_niche(
			'Cursos e infoprodutos',
			'Audiência que compra: conteúdo que gera autoridade, vídeos que conectam e campanhas de tráfego que escalam as vendas da {cliente_curto}.',
			'conhecimento que transforma merece chegar a muito mais gente, e vender todos os dias.',
			'O mercado digital está cheio de promessas. O aluno compra de quem ele acompanha, confia e vê entregando valor de graça antes de pedir qualquer coisa. Sem conteúdo constante e tráfego bem feito, até o melhor curso fica sem vendas.',
			'Com linha editorial de autoridade, vídeos que aproximam o especialista do público e campanhas de captação e venda bem estruturadas, a {cliente_curto} constrói uma audiência que compra e vende todos os meses, não só nos lançamentos.',
			array(
				'Autoridade que vende | Conteúdo que entrega valor e posiciona o especialista como referência.',
				'Captação de leads | Campanhas para aulas gratuitas, materiais ricos e lista de espera.',
				'Vendas todos os meses | Estrutura de perpétuo além dos lançamentos.',
				'Página que converte | Landing pages rápidas e persuasivas para cada oferta.',
			),
			'Vamos escalar as vendas da {cliente_curto}?',
			'Audiência qualificada, campanhas bem estruturadas e vendas todos os meses.'
		),

		'pet' => ldkp_niche(
			'Pet shops e veterinárias',
			'Tutores fiéis e agenda cheia: conteúdo fofo e útil, vídeos que conquistam e anúncios para quem mora perto da {cliente_curto}.',
			'quem cuida de quem é da família precisa conquistar a confiança dos tutores em cada post.',
			'O tutor trata o pet como filho e escolhe com o coração, mas pesquisa com a razão. Ele segue perfis, lê avaliações e vai onde sente que o pet vai ser tratado com carinho. Quem não aparece perde banho, tosa e consulta para o concorrente da rua de cima.',
			'Com conteúdo útil e divertido, vídeos dos atendimentos e campanhas para tutores da região, a {cliente_curto} vira a primeira opção do bairro e cria uma base de clientes que volta todo mês.',
			array(
				'Conteúdo que conquista | Vídeos fofos e dicas úteis que geram engajamento e lembrança de marca.',
				'Confiança no cuidado | Bastidores dos atendimentos que mostram carinho e profissionalismo.',
				'Clientes recorrentes | Campanhas de banho, tosa, vacinas e planos mensais.',
				'Tutores da região | Anúncios para quem mora perto, levando direto para o WhatsApp.',
			),
			'Vamos fazer a {cliente_curto} ser a queridinha dos tutores?',
			'Conteúdo que conquista, vídeos que geram confiança e anúncios para quem está perto.'
		),

		'oficinas' => ldkp_niche(
			'Oficinas e auto centers',
			'Confiança que traz o carro para a oficina: conteúdo que educa, vídeos que mostram o serviço bem feito e anúncios para motoristas da região da {cliente_curto}.',
			'o maior medo de quem leva o carro para a oficina é não saber em quem confiar. Essa é a sua grande oportunidade.',
			'O motorista procura oficina quando o problema aparece, e escolhe rápido: pelo Google, pelas avaliações e pela indicação. Oficinas sem presença digital dependem do boca a boca e ficam com o pátio vazio nos meses fracos.',
			'Com conteúdo que ensina e gera confiança, vídeos que mostram o processo com transparência e campanhas para quem está perto e precisando agora, a {cliente_curto} vira a oficina de confiança da região.',
			array(
				'Transparência que gera confiança | Vídeos mostrando diagnóstico e serviço com honestidade.',
				'Ser achado na hora da urgência | Google Meu Negócio e anúncios para quem precisa agora.',
				'Revisões e recorrência | Campanhas de revisão, troca de óleo e pneus que trazem o cliente de volta.',
				'Avaliações que vendem | Estratégia para aumentar e responder as avaliações no Google.',
			),
			'Vamos encher o pátio da {cliente_curto}?',
			'Confiança, visibilidade e recorrência para a oficina trabalhar cheia o ano inteiro.'
		),

		'energia-solar' => ldkp_niche(
			'Energia solar',
			'Orçamentos qualificados todos os dias: conteúdo que prova a economia, vídeos das instalações e campanhas para quem está pronto para investir com a {cliente_curto}.',
			'energia solar é o investimento que se paga sozinho. O desafio é fazer o cliente certo acreditar nisso.',
			'O cliente de energia solar pesquisa muito antes de fechar: compara empresas, desconfia de preços baixos e quer provas de que vai economizar. Leads frios pedem orçamento e somem, e o time comercial perde tempo.',
			'Com conteúdo que mostra economia real, vídeos de instalações e depoimentos e campanhas segmentadas por perfil de consumo, a {cliente_curto} gera orçamentos mais qualificados e aumenta a taxa de fechamento.',
			array(
				'Prova de economia | Depoimentos e casos reais com números de conta de luz antes e depois.',
				'Credibilidade técnica | Vídeos de instalações, equipe e equipamentos que mostram qualidade.',
				'Leads qualificados | Campanhas segmentadas por perfil de consumo, residencial, rural e comercial.',
				'Simulação e orçamento | Página e WhatsApp para capturar a conta de luz e agilizar a proposta.',
			),
			'Vamos iluminar as vendas da {cliente_curto}?',
			'Mais orçamentos qualificados, mais confiança e mais contratos fechados.'
		),

		'turismo' => ldkp_niche(
			'Agências de viagem e turismo',
			'Destinos que viram vendas: conteúdo que inspira, vídeos que fazem sonhar e anúncios que levam viajantes direto para o consultor da {cliente_curto}.',
			'quem vende experiências inesquecíveis precisa de uma comunicação que faça o cliente sonhar já no feed.',
			'O viajante começa a planejar pelo Instagram: salva destinos, compara pacotes e procura quem inspira confiança. Sem constância e sem conteúdo que desperta desejo, ele fecha tudo sozinho pela internet.',
			'Com conteúdo que inspira, vídeos de destinos e depoimentos de clientes e campanhas no momento certo de cada temporada, a {cliente_curto} mostra o valor de um consultor e vende mais pacotes com margem melhor.',
			array(
				'Desejo de viajar | Conteúdo e vídeos que transformam destinos em sonhos possíveis.',
				'Valor do consultor | Mostrar por que comprar com a agência é mais seguro e mais vantajoso.',
				'Temporadas planejadas | Campanhas para férias, feriados e datas especiais com antecedência.',
				'Atendimento direto | Anúncios levando para o WhatsApp com o destino já escolhido.',
			),
			'Vamos levar a {cliente_curto} mais longe?',
			'Conteúdo que faz sonhar e campanhas que viram pacotes vendidos.'
		),

		'hoteis-pousadas' => ldkp_niche(
			'Hotéis e pousadas',
			'Mais reservas diretas e menos comissão: conteúdo que encanta, vídeos da experiência e anúncios para o hóspede certo da {cliente_curto}.',
			'quem oferece dias inesquecíveis precisa mostrar essa experiência antes do check-in.',
			'O hóspede escolhe onde ficar pelas fotos, pelos vídeos e pelas avaliações. Depender só das plataformas de reserva significa pagar comissão alta em cada diária e disputar atenção com dezenas de concorrentes na mesma tela.',
			'Com vídeos que mostram a experiência, conteúdo que desperta desejo e campanhas para o público certo em cada temporada, a {cliente_curto} aumenta as reservas diretas, melhora a margem e ocupa os períodos de baixa.',
			array(
				'Experiência antes do check-in | Vídeos e fotos que mostram quartos, estrutura e arredores.',
				'Reservas diretas | Campanhas e site levando para o WhatsApp ou para o motor de reservas, sem comissão.',
				'Baixa temporada ocupada | Pacotes e promoções para os períodos de menor movimento.',
				'Avaliações em destaque | Prova social de hóspedes reais para gerar confiança.',
			),
			'Vamos lotar a {cliente_curto}?',
			'Experiência em destaque, reservas diretas e ocupação o ano inteiro.'
		),

		'eventos' => ldkp_niche(
			'Eventos, buffets e casamentos',
			'Agenda de datas cheia: conteúdo que emociona, vídeos que mostram cada detalhe e anúncios para quem está planejando o grande dia com a {cliente_curto}.',
			'quem realiza sonhos em forma de festa precisa emocionar no feed antes de emocionar no salão.',
			'Noivos e famílias começam a pesquisar com meses de antecedência. Eles salvam referências, comparam espaços e fornecedores e escolhem quem mais emociona e transmite segurança. Datas vazias no calendário são receita que não volta.',
			'Com vídeos dos eventos, bastidores que mostram o cuidado com cada detalhe e campanhas para quem está no momento de planejar, a {cliente_curto} fecha datas com antecedência e valoriza o seu serviço.',
			array(
				'Emoção que vende | Vídeos e fotos dos eventos que fazem o cliente se imaginar ali.',
				'Detalhes que diferenciam | Bastidores que mostram cuidado, organização e padrão de qualidade.',
				'Datas fechadas com antecedência | Campanhas para quem está no início do planejamento.',
				'Visitas agendadas | Anúncios levando direto para o WhatsApp para marcar a visita.',
			),
			'Vamos encher o calendário da {cliente_curto}?',
			'Conteúdo que emociona e campanhas que transformam sonhos em datas fechadas.'
		),

		'moveis-planejados' => ldkp_niche(
			'Móveis planejados',
			'Projetos que viram contrato: conteúdo que inspira, vídeos de ambientes prontos e campanhas para quem está construindo ou reformando, com a {cliente_curto}.',
			'quem transforma casas em lares precisa mostrar esse resultado para quem ainda está sonhando com ele.',
			'Móveis planejados são uma compra alta e muito pesquisada. O cliente coleciona referências por meses, compara marcenarias e escolhe quem mostra acabamento, pontualidade e ambientes reais. Sem isso, a disputa vira só preço.',
			'Com vídeos de ambientes prontos, conteúdo que mostra material e acabamento e campanhas para quem está construindo ou reformando, a {cliente_curto} atrai clientes com perfil de compra e fecha mais projetos.',
			array(
				'Ambientes que inspiram | Vídeos e fotos de projetos entregues, com detalhes de acabamento.',
				'Qualidade que justifica o preço | Conteúdo sobre materiais, ferragens e processo de produção.',
				'Momento certo | Campanhas para quem está construindo, reformando ou recebendo as chaves.',
				'Orçamento sem atrito | Anúncios levando para o WhatsApp com a planta ou as medidas.',
			),
			'Vamos projetar o crescimento da {cliente_curto}?',
			'Ambientes que inspiram e campanhas que trazem clientes no momento certo.'
		),

		'oticas' => ldkp_niche(
			'Óticas',
			'Estilo que vende: conteúdo que mostra tendências, vídeos de provas e anúncios que trazem clientes para a loja da {cliente_curto}.',
			'óculos é saúde e estilo ao mesmo tempo. E as duas coisas se vendem melhor quando são mostradas.',
			'O cliente escolhe a ótica pela confiança no atendimento e pelo estilo das armações. Ele pesquisa modelos no Instagram antes de sair de casa. Óticas que só postam promoção viram commodity e competem por preço.',
			'Com conteúdo de tendências e estilo, vídeos de provas e atendimento e campanhas para a região, a {cliente_curto} vira referência de estilo e confiança e aumenta o ticket médio com lentes e armações de maior valor.',
			array(
				'Estilo em destaque | Conteúdo de armações e tendências que inspira a troca.',
				'Confiança no atendimento | Vídeos que mostram o cuidado técnico com lentes e medidas.',
				'Ticket maior | Conteúdo que explica o valor das lentes de qualidade.',
				'Clientes da região | Anúncios para quem está perto, levando para a loja ou o WhatsApp.',
			),
			'Vamos fazer a cidade enxergar a {cliente_curto}?',
			'Estilo, confiança e campanhas que trazem clientes para a loja.'
		),

		'farmacias' => ldkp_niche(
			'Farmácias',
			'A farmácia do bairro sempre lembrada: conteúdo útil, vídeos que humanizam o atendimento e campanhas para quem está perto da {cliente_curto}.',
			'quem cuida da saúde da vizinhança precisa ser a primeira lembrança na hora da necessidade.',
			'As grandes redes investem pesado para dominar a atenção do cliente. A farmácia independente vence pela proximidade e pelo atendimento, mas só quando o cliente lembra dela. Sem presença digital, essa vantagem fica invisível.',
			'Com conteúdo útil de saúde e bem-estar, vídeos que mostram a equipe e campanhas de delivery e ofertas para o bairro, a {cliente_curto} fortalece a relação com a vizinhança e aumenta os pedidos pelo WhatsApp.',
			array(
				'Proximidade como diferencial | Conteúdo que mostra a equipe e o atendimento que as grandes redes não têm.',
				'Delivery pelo WhatsApp | Campanhas para o bairro levando direto para o pedido.',
				'Conteúdo útil | Dicas de saúde e bem-estar que geram confiança e lembrança.',
				'Dentro das regras da Anvisa | Comunicação responsável, sem indicação de medicamentos.',
			),
			'Vamos fazer da {cliente_curto} a farmácia do bairro?',
			'Proximidade, confiança e campanhas que trazem pedidos todos os dias.'
		),

		'industria' => ldkp_niche(
			'Indústrias e empresas B2B',
			'Marketing B2B que gera oportunidades: conteúdo técnico com linguagem clara, vídeos da operação e campanhas para os decisores certos da {cliente_curto}.',
			'quem produz com excelência precisa de uma presença digital que mostre essa excelência aos compradores certos.',
			'Compradores e gestores pesquisam fornecedores no Google e no LinkedIn antes de pedir cotação. Eles procuram capacidade, certificações e cases. Empresas sem presença digital sólida nem entram na lista de fornecedores avaliados.',
			'Com site institucional forte, conteúdo técnico com linguagem acessível, vídeos da fábrica e da operação e campanhas para os decisores certos, a {cliente_curto} gera cotações qualificadas e encurta o ciclo de venda.',
			array(
				'Credibilidade de fornecedor | Site e conteúdo que mostram capacidade, certificações e cases.',
				'Operação em vídeo | Fábrica, processos e equipe à mostra para gerar confiança.',
				'Decisores certos | Campanhas no Google e no LinkedIn para compradores e gestores.',
				'Cotações qualificadas | Formulários e WhatsApp que já capturam as informações do pedido.',
			),
			'Vamos gerar mais oportunidades para a {cliente_curto}?',
			'Credibilidade, visibilidade e cotações qualificadas para o comercial fechar mais.'
		),

		'empresas' => ldkp_niche(
			'Empresas (geral)',
			'Planejamento, criatividade e resultado em um só lugar: presença constante, vídeos estratégicos e anúncios que trazem clientes para a {cliente_curto}.',
			'a sua empresa entrega qualidade. Agora ela precisa de presença digital constante e previsibilidade de clientes.',
			'Hoje todo cliente pesquisa antes de comprar. Ele olha o Instagram, confere o site, compara com a concorrência e decide em poucos segundos. Enquanto a sua marca espera, outras estão sendo lembradas todos os dias.',
			'Com uma estratégia feita para a realidade da {cliente_curto}, sem fórmula pronta e sem achismo, a marca ganha constância, autoridade e um fluxo previsível de contatos vindos das redes, do site e dos anúncios.',
			array(
				'Dependência do boca a boca | Sem controle sobre o fluxo de novos clientes, o mês depende de indicação.',
				'Leads desqualificados | Chegam contatos, mas poucos realmente prontos para comprar.',
				'Redes sociais paradas | Um perfil desatualizado passa a impressão de que a empresa parou no tempo.',
				'Concorrentes aparecendo mais | Enquanto você espera, outras marcas estão sendo lembradas todos os dias.',
			),
			'Vamos fazer a {cliente_curto} crescer?',
			'Estratégia, criatividade e resultado caminhando juntos, com uma equipe completa cuidando da sua marca todos os meses.'
		),

	);
}

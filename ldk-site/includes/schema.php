<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Widgets do site: campos editáveis (Elementor) e textos padrão.
 * Campo: chave => array( tipo, rótulo, padrão, extras )   tipos: text, textarea, url, image, number, select, repeater
 * Use *palavra* nos títulos para o destaque em neon.
 */
function ldk_site_schema() {
	$up = 'https://ldkmarketingdigital.com.br/wp-content/uploads/2026/09/';
	$p  = LDK_SITE_URL . 'assets/img/';
	return array(
		'pagehead' => array(
			'title'  => 'LDK · Cabeçalho de página',
			'icon'   => 'eicon-banner',
			'fields' => array(
				'eyebrow' => array( 'text', 'Chamada', 'LDK Marketing Digital' ),
				'title'   => array( 'text', 'Título', 'Título da *página*' ),
				'text'    => array( 'textarea', 'Texto', '' ),
			),
		),
		'hero'     => array(
			'title'  => 'LDK · Hero (topo do site)',
			'icon'   => 'eicon-banner',
			'fields' => array(
				'eyebrow' => array( 'text', 'Chamada', 'Agência de marketing digital · Taubaté' ),
				'title'   => array( 'textarea', 'Título', 'Marcas fortes. *Estratégia* que vira resultado.' ),
				'text'    => array( 'textarea', 'Texto', 'Planejamento, criação, vídeo, sites e tráfego pago trabalhando juntos, com um painel onde você acompanha tudo em tempo real. Sem fórmula pronta, sem achismo.' ),
				'btn1'    => array( 'text', 'Botão 1 (texto)', 'Quero crescer com a LDK' ),
				'btn1_u'  => array( 'url', 'Botão 1 (link)', 'pg:contato' ),
				'btn2'    => array( 'text', 'Botão 2 (texto)', 'Fazer meu diagnóstico grátis' ),
				'btn2_u'  => array( 'url', 'Botão 2 (link)', 'pg:diagnostico' ),
				'img_main'  => array( 'image', 'Imagem principal (tela do painel)', $p . 'painel-relatorios.png' ),
				'img_phone' => array( 'image', 'Imagem do celular', $p . 'celular-perfil.png' ),
				'proof'   => array( 'text', 'Prova (rodapé do hero)', 'Desde 2018 · +30 clientes fixos todo mês' ),
			),
		),
		'marquee'  => array(
			'title'  => 'LDK · Faixa em movimento',
			'icon'   => 'eicon-slider-push',
			'fields' => array(
				'items' => array( 'textarea', 'Palavras (uma por linha)', "Gestão de Redes Sociais\nTráfego Pago\nGravação de Vídeos\nCriação de Sites\nBranding\nEstratégia\nResultados" ),
			),
		),
		'services' => array(
			'title'  => 'LDK · Serviços',
			'icon'   => 'eicon-posts-grid',
			'fields' => array(
				'eyebrow' => array( 'text', 'Chamada', 'O que fazemos' ),
				'title'   => array( 'text', 'Título', 'Tudo o que a sua marca precisa, *em um só lugar*' ),
				'text'    => array( 'textarea', 'Texto', 'Uma equipe multidisciplinar para planejar, criar e publicar. Você fala com uma agência só e acompanha tudo pelo painel do cliente.' ),
				'items'   => array(
					'repeater',
					'Serviços',
					array(
						array( 'icon' => 'social', 'title' => 'Gestão de Redes Sociais', 'text' => 'Planejamento, linha editorial, design e publicação. Conteúdo que fortalece a marca e conversa com o público certo.', 'link' => 'pg:servicos' ),
						array( 'icon' => 'trafego', 'title' => 'Gestão de Tráfego Pago', 'text' => 'Campanhas no Meta Ads e no Google Ads para atrair o público certo, no momento certo, com relatório claro.', 'link' => 'pg:servicos' ),
						array( 'icon' => 'video', 'title' => 'Gravação de Vídeos', 'text' => 'Roteiro, captação e edição de vídeos e reels que passam autoridade e prendem a atenção.', 'link' => 'pg:servicos' ),
						array( 'icon' => 'site', 'title' => 'Criação de Sites', 'text' => 'Sites institucionais e landing pages rápidos, responsivos e prontos para receber tráfego pago.', 'link' => 'pg:servicos' ),
						array( 'icon' => 'brand', 'title' => 'Branding', 'text' => 'Identidade visual e posicionamento para a sua marca ser lembrada e valorizada.', 'link' => 'pg:servicos' ),
						array( 'icon' => 'users', 'title' => 'Painel do cliente', 'text' => 'Aprovações, relatórios, reuniões e contratos em uma área exclusiva, 24 horas por dia.', 'link' => 'pg:painel-do-cliente' ),
					),
					array( 'icon' => 'select', 'title' => 'text', 'text' => 'textarea', 'link' => 'url' ),
				),
			),
		),
		'steps'    => array(
			'title'  => 'LDK · Etapas / Como trabalhamos',
			'icon'   => 'eicon-number-field',
			'fields' => array(
				'eyebrow' => array( 'text', 'Chamada', 'Como trabalhamos' ),
				'title'   => array( 'text', 'Título', 'Do primeiro contato ao *resultado*' ),
				'items'   => array(
					'repeater',
					'Etapas',
					array(
						array( 'title' => 'Diagnóstico', 'text' => 'Entendemos o seu negócio, o público e o momento da marca para só então desenhar a estratégia.' ),
						array( 'title' => 'Planejamento', 'text' => 'Metas, linha editorial, canais e calendário definidos em conjunto, de forma clara.' ),
						array( 'title' => 'Criação e execução', 'text' => 'Design, vídeo, textos e campanhas produzidos pela equipe, com aprovação no painel.' ),
						array( 'title' => 'Medição e evolução', 'text' => 'Relatórios, reuniões de resultado e ajustes constantes para evoluir mês a mês.' ),
					),
					array( 'title' => 'text', 'text' => 'textarea' ),
				),
			),
		),
		'stats'    => array(
			'title'  => 'LDK · Faixa de números (ciano)',
			'icon'   => 'eicon-counter',
			'fields' => array(
				'title' => array( 'text', 'Título da faixa', 'Fazemos história' ),
				'items' => array(
					'repeater',
					'Números',
					array(
						array( 'num' => '2018', 'suffix' => '', 'label' => 'Ano em que a LDK nasceu' ),
						array( 'num' => '30', 'suffix' => '+', 'label' => 'Clientes fixos todo mês' ),
						array( 'num' => '5', 'suffix' => '', 'label' => 'Frentes de trabalho integradas' ),
						array( 'num' => '24', 'suffix' => 'h', 'label' => 'Painel do cliente sempre aberto' ),
					),
					array( 'num' => 'text', 'suffix' => 'text', 'label' => 'text' ),
				),
			),
		),
		'clients'  => array(
			'title'  => 'LDK · Clientes (logos)',
			'icon'   => 'eicon-logo',
			'fields' => array(
				'title' => array( 'text', 'Título', 'Marcas que confiam na LDK' ),
				'items' => array(
					'repeater',
					'Logos',
					array(
						array( 'name' => 'Novolar Absolute', 'image' => $up . 'Camada-1-1.webp' ),
						array( 'name' => 'Mega Motos', 'image' => $up . 'WhatsApp_Image_2023-01-20_at_14.16.53-removebg-preview3.webp' ),
						array( 'name' => 'Terreno Mayno', 'image' => $up . 'mayno-preto2-scaled-1.webp' ),
						array( 'name' => 'Metalquente', 'image' => $up . 'Camada-0.webp' ),
						array( 'name' => 'Humaitá Pneus', 'image' => $up . 'Ativo-3@2x-scaled-1-768x232.webp' ),
						array( 'name' => 'Harmmonie', 'image' => $up . 'logo_harmmonie-1.webp' ),
						array( 'name' => 'Dr. Anderson Maicon', 'image' => $up . 'logonormal_semfundo-1-scaled-1.webp' ),
						array( 'name' => 'Luma Análises Clínicas', 'image' => $up . 'logoazul_semfundo_luma-1.webp' ),
						array( 'name' => 'Aggily', 'image' => $up . 'Logo-Aggily-WHITE.webp' ),
					),
					array( 'name' => 'text', 'image' => 'image' ),
				),
			),
		),
		'panel'    => array(
			'title'  => 'LDK · Painel do cliente (com prints)',
			'icon'   => 'eicon-device-desktop',
			'fields' => array(
				'eyebrow' => array( 'text', 'Chamada', 'Painel do cliente' ),
				'title'   => array( 'text', 'Título', 'Você acompanha *tudo*, sem precisar perguntar' ),
				'text'    => array( 'textarea', 'Texto', 'Cada cliente da LDK recebe um acesso exclusivo. Veja o que está sendo feito, aprove o que vai ao ar e confira os resultados quando quiser. As telas abaixo usam dados fictícios.' ),
				'cta'     => array( 'text', 'Botão (texto)', 'Quero ter o meu painel' ),
				'cta_u'   => array( 'url', 'Botão (link)', 'pg:contato' ),
				'items'   => array(
					'repeater',
					'Funções',
					array(
						array( 'icon' => 'check', 'title' => 'Aprovação de posts', 'text' => 'Veja a arte em tela cheia, aprove ou peça ajuste com um clique. Nada vai ao ar sem o seu ok.', 'image' => $p . 'painel-aprovacoes.png' ),
						array( 'icon' => 'chart', 'title' => 'Relatórios de resultado', 'text' => 'Investimento, alcance, cliques e leads do tráfego pago e das redes, em linguagem simples.', 'image' => $p . 'painel-relatorios.png' ),
						array( 'icon' => 'cal', 'title' => 'Reuniões e agenda', 'text' => 'Reuniões marcadas com link de vídeo, lembrete por e-mail e histórico de tudo o que foi combinado.', 'image' => $p . 'painel-reunioes.png' ),
						array( 'icon' => 'form', 'title' => 'Briefings e pesquisas', 'text' => 'Briefings passo a passo e pesquisa de satisfação direto no painel, tudo salvo para evoluirmos juntos.', 'image' => $p . 'painel-briefing.png' ),
						array( 'icon' => 'doc', 'title' => 'Contratos e documentos', 'text' => 'Contrato, propostas e arquivos organizados e acessíveis a qualquer hora.', 'image' => $p . 'painel-inicio.png' ),
					),
					array( 'icon' => 'select', 'title' => 'text', 'text' => 'textarea', 'image' => 'image' ),
				),
			),
		),
		'about'    => array(
			'title'  => 'LDK · Sobre nós',
			'icon'   => 'eicon-person',
			'fields' => array(
				'eyebrow' => array( 'text', 'Chamada', 'Quem está por trás da LDK' ),
				'title'   => array( 'text', 'Título', 'Uma agência nascida de um *sonho*' ),
				'text'    => array( 'textarea', 'Texto 1', 'A LDK nasceu em 2018, em Taubaté, fundada pela publicitária Marília Lüdke. Começou como um sonho e, ao longo dos anos, evoluiu para uma agência que hoje atende mais de 30 clientes fixos todos os meses, de diferentes segmentos e portes.' ),
				'text2'   => array( 'textarea', 'Texto 2', 'Mais do que produzir conteúdo, construímos marcas fortes, geramos conexões reais com o público e desenvolvemos estratégias que impulsionam negócios de forma consistente. Sem fórmula pronta, sem achismo.' ),
				'image'   => array( 'image', 'Imagem', $up . 'Camada-1.webp' ),
				'items'   => array(
					'repeater',
					'Diferenciais',
					array(
						array( 'icon' => 'target', 'title' => 'Estratégia personalizada', 'text' => 'Cada negócio tem um momento e um objetivo. Criamos estratégias pensadas para a sua realidade.' ),
						array( 'icon' => 'users', 'title' => 'Equipe multidisciplinar', 'text' => 'Planejamento, criação, design, audiovisual, webdesign e tráfego pago de forma integrada.' ),
						array( 'icon' => 'bolt', 'title' => 'Criatividade com propósito', 'text' => 'Ideias bonitas precisam fazer sentido. Conteúdos que fortalecem a marca e conectam com o público.' ),
						array( 'icon' => 'chart', 'title' => 'Compromisso com resultados', 'text' => 'Acompanhamos cada projeto de perto para estratégia e execução caminharem juntas.' ),
					),
					array( 'icon' => 'select', 'title' => 'text', 'text' => 'textarea' ),
				),
			),
		),
		'cta'      => array(
			'title'  => 'LDK · Chamada final (CTA)',
			'icon'   => 'eicon-call-to-action',
			'fields' => array(
				'title'  => array( 'text', 'Título', 'Vamos fazer a sua marca *aparecer*?' ),
				'text'   => array( 'textarea', 'Texto', 'Conte o que você precisa e a nossa equipe volta com um caminho claro, sem compromisso.' ),
				'btn1'   => array( 'text', 'Botão 1', 'Falar no WhatsApp' ),
				'btn1_u' => array( 'url', 'Link 1', 'wa' ),
				'btn2'   => array( 'text', 'Botão 2', 'Enviar mensagem' ),
				'btn2_u' => array( 'url', 'Link 2', 'pg:contato' ),
			),
		),
		'contact'  => array(
			'title'  => 'LDK · Contato (formulário → CRM)',
			'icon'   => 'eicon-form-horizontal',
			'fields' => array(
				'eyebrow' => array( 'text', 'Chamada', 'Contato' ),
				'title'   => array( 'text', 'Título', 'Conte o que você *precisa*' ),
				'text'    => array( 'textarea', 'Texto', 'Preencha o formulário e a equipe da LDK retorna em até 1 dia útil. Se preferir, chame no WhatsApp.' ),
				'tipo'    => array( 'text', 'Origem (aparece no CRM)', 'contato' ),
			),
		),
		'diag'     => array(
			'title'  => 'LDK · Diagnóstico de perfil (quiz → CRM)',
			'icon'   => 'eicon-form-vertical',
			'fields' => array(
				'title' => array( 'text', 'Título', 'Descubra o que está *travando* o seu perfil' ),
				'text'  => array( 'textarea', 'Texto', 'Responda 6 perguntas rápidas e receba um diagnóstico do estágio do seu perfil, com os próximos passos. É gratuito e leva menos de 2 minutos.' ),
			),
		),
		'posts'    => array(
			'title'  => 'LDK · Blog (lista de artigos)',
			'icon'   => 'eicon-post-list',
			'fields' => array(
				'count' => array( 'number', 'Quantidade', 9 ),
			),
		),
		'links'    => array(
			'title'  => 'LDK · Página de links',
			'icon'   => 'eicon-button',
			'fields' => array(
				'name'  => array( 'text', 'Nome', 'LDK Marketing Digital' ),
				'bio'   => array( 'textarea', 'Bio', 'Estratégia, criação e tráfego pago para marcas que querem aparecer. Taubaté · SP' ),
				'items' => array(
					'repeater',
					'Links',
					array(
						array( 'icon' => 'wa', 'title' => 'Falar no WhatsApp', 'sub' => 'Atendimento direto com a equipe', 'url' => 'wa' ),
						array( 'icon' => 'target', 'title' => 'Diagnóstico de perfil grátis', 'sub' => '6 perguntas e um plano de ação', 'url' => 'pg:diagnostico' ),
						array( 'icon' => 'globe', 'title' => 'Conheça o site', 'sub' => 'Serviços, sobre nós e blog', 'url' => 'pg:inicio' ),
						array( 'icon' => 'users', 'title' => 'Painel do cliente', 'sub' => 'Veja como você acompanha tudo', 'url' => 'pg:painel-do-cliente' ),
						array( 'icon' => 'doc', 'title' => 'Blog', 'sub' => 'Dicas de marketing digital', 'url' => 'pg:blog' ),
						array( 'icon' => 'ig', 'title' => 'Instagram', 'sub' => '@ldkmarketingdigital', 'url' => 'https://www.instagram.com/ldkmarketingdigital/' ),
					),
					array( 'icon' => 'select', 'title' => 'text', 'sub' => 'text', 'url' => 'url' ),
				),
			),
		),
		'faq'      => array(
			'title'  => 'LDK · Perguntas frequentes',
			'icon'   => 'eicon-help',
			'fields' => array(
				'title' => array( 'text', 'Título', 'Perguntas *frequentes*' ),
				'items' => array(
					'repeater',
					'Perguntas',
					array(
						array( 'q' => 'Como funciona o painel do cliente?', 'a' => 'Cada cliente recebe um acesso exclusivo para aprovar posts, ver relatórios, acompanhar reuniões, responder briefings e consultar contratos, tudo em um só lugar.' ),
						array( 'q' => 'Preciso de contrato de fidelidade?', 'a' => 'Trabalhamos com contrato de prestação de serviços, que garante entrega e suporte por escrito. Os detalhes de prazo são combinados na proposta.' ),
						array( 'q' => 'Vocês atendem fora de Taubaté?', 'a' => 'Sim. Todo o acompanhamento acontece online, pelo painel, pelo WhatsApp e por videochamada.' ),
						array( 'q' => 'Em quanto tempo o site fica pronto?', 'a' => 'Sites institucionais ficam no ar de 15 a 30 dias úteis, dependendo do conteúdo e das aprovações.' ),
						array( 'q' => 'O diagnóstico de perfil é mesmo gratuito?', 'a' => 'É. Você responde às perguntas, recebe a leitura do seu momento e a equipe pode conversar com você sobre os próximos passos, sem compromisso.' ),
					),
					array( 'q' => 'text', 'a' => 'textarea' ),
				),
			),
		),
		'testimonials' => array(
			'title'  => 'LDK · Depoimentos',
			'icon'   => 'eicon-testimonial',
			'fields' => array(
				'title' => array( 'text', 'Título', 'O que dizem *nossos clientes*' ),
				'items' => array(
					'repeater',
					'Depoimentos',
					array(
						array( 'name' => 'Edgard Faria', 'role' => 'Cliente LDK', 'text' => 'Os roteiros de vídeos sempre bem adaptados e enviados com antecedência, assim como o planejamento e os dados informativos sobre as campanhas. A agência ainda conta com um treinamento para capacitar o meu comercial em como fazer a conversão do lead que vinha através do tráfego pago.' ),
					),
					array( 'name' => 'text', 'role' => 'text', 'text' => 'textarea' ),
				),
			),
		),
		'social'   => array(
			'title'  => 'LDK · Acompanhe nossas redes',
			'icon'   => 'eicon-social-icons',
			'fields' => array(
				'title' => array( 'text', 'Título', 'Acompanhe nossas *redes sociais*' ),
				'text'  => array( 'textarea', 'Texto', 'Bastidores, dicas e cases no nosso Instagram.' ),
				'image' => array( 'image', 'Imagem', $up . 'Camada-1.webp' ),
				'btn'   => array( 'text', 'Botão', 'Seguir @ldkmarketingdigital' ),
			),
		),
		'pains'    => array(
			'title'  => 'LDK · Dores (para a página de diagnóstico)',
			'icon'   => 'eicon-alert',
			'fields' => array(
				'title' => array( 'text', 'Título', 'Seu perfil posta, mas *não vende*?' ),
				'items' => array(
					'repeater',
					'Dores',
					array(
						array( 'icon' => 'social', 'title' => 'Posta sem direção', 'text' => 'Conteúdo aleatório, sem linha editorial e sem objetivo claro para cada publicação.' ),
						array( 'icon' => 'target', 'title' => 'Público errado', 'text' => 'Seguidores que não são o seu cliente e anúncios que gastam sem trazer contato.' ),
						array( 'icon' => 'chart', 'title' => 'Sem medir resultado', 'text' => 'Não sabe o que funciona, o que custa caro e onde ajustar.' ),
						array( 'icon' => 'bolt', 'title' => 'Visual que não passa confiança', 'text' => 'Identidade confusa, que não transmite o valor real do seu negócio.' ),
					),
					array( 'icon' => 'select', 'title' => 'text', 'text' => 'textarea' ),
				),
			),
		),
	);
}

function ldk_site_icon_choices() {
	return array( 'social' => 'Redes', 'trafego' => 'Tráfego', 'video' => 'Vídeo', 'site' => 'Site', 'brand' => 'Branding', 'check' => 'Check', 'chart' => 'Gráfico', 'cal' => 'Agenda', 'form' => 'Formulário', 'doc' => 'Documento', 'users' => 'Pessoas', 'target' => 'Alvo', 'bolt' => 'Raio', 'star' => 'Estrela', 'bell' => 'Sino', 'lock' => 'Cadeado', 'pin' => 'Local', 'ig' => 'Instagram', 'wa' => 'WhatsApp', 'mail' => 'E-mail', 'globe' => 'Globo', 'link' => 'Link', 'play' => 'Play', 'chat' => 'Chat' );
}

/** Mescla o que o Elementor guardou com os padrões. */
function ldk_site_settings( $key, $s = array() ) {
	$schema = ldk_site_schema();
	$out    = array();
	foreach ( $schema[ $key ]['fields'] as $f => $def ) {
		$val = isset( $s[ $f ] ) && ( ! is_string( $s[ $f ] ) || '' !== $s[ $f ] ) && ( ! is_array( $s[ $f ] ) || $s[ $f ] ) ? $s[ $f ] : null;
		if ( 'repeater' === $def[0] ) {
			$out[ $f ] = null !== $val ? $val : $def[2];
		} else {
			$out[ $f ] = null !== $val ? $val : $def[2];
		}
	}
	return $out;
}

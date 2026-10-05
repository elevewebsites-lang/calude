<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function ldkp_default_settings() {
	$up = 'https://ldkmarketingdigital.com.br/wp-content/uploads/';
	return array(
		'logo'          => $up . '2026/09/Screenshot_2026-08-11_at_13.26.35-removebg-preview.webp',
		'whatsapp'      => '12 98825-7644',
		'email'         => '',
		'instagram'     => '@ldkmarketingdigital',
		'responsavel'   => 'Marília Lüdke',
		'stats'         => implode(
			"\n",
			array(
				'+80 | Campanhas mensais',
				'8 | Anos de mercado',
				'+100 | Empresas atendidas',
				'+30 | Clientes fixos todo mês',
			)
		),
		'diferenciais'  => implode(
			"\n",
			array(
				'Estratégia personalizada | Cada negócio tem um momento e um objetivo. Criamos estratégias pensadas para a realidade da sua empresa.',
				'Equipe multidisciplinar | Planejamento, criação, design, audiovisual, webdesign e tráfego pago trabalhando de forma integrada.',
				'Criatividade com propósito | Ideias bonitas precisam fazer sentido. Criamos conteúdos que fortalecem a sua marca e se conectam com o público.',
				'Compromisso com resultados | Acompanhamos cada projeto de perto para que estratégia e execução caminhem sempre na mesma direção.',
			)
		),
		'sobre_img'     => $up . '2026/09/Camada-1.webp',
		'sobre_titulo'  => 'Quem está por trás da LDK Marketing Digital',
		'sobre_texto'   => implode(
			"\n\n",
			array(
				'A LDK nasceu em 2018, em Taubaté, fundada pela publicitária Marília Lüdke. Começou como um sonho e, ao longo dos anos, evoluiu para uma agência que hoje atende mais de 30 clientes fixos todos os meses, de diferentes segmentos e portes.',
				'Mais do que produzir conteúdo, construímos marcas fortes, geramos conexões reais com o público e desenvolvemos estratégias que impulsionam negócios de forma consistente. Sem fórmula pronta, sem achismo.',
			)
		),
		'agencia_servicos' => implode(
			"\n",
			array(
				'Gestão de Redes Sociais',
				'Gestão de Tráfego Pago',
				'Gravação de Vídeos',
				'Criação de Sites',
				'Branding',
			)
		),
		'clientes_texto' => 'De grandes incorporadoras a negócios locais, já ajudamos marcas de diferentes segmentos e portes a fortalecerem a sua presença digital.',
		'clientes'      => implode(
			"\n",
			array(
				'Novolar Absolute | ' . $up . '2026/09/Camada-1-1.webp',
				'Mega Motos | ' . $up . '2026/09/WhatsApp_Image_2023-01-20_at_14.16.53-removebg-preview3.webp',
				'Terreno Mayno | ' . $up . '2026/09/mayno-preto2-scaled-1.webp',
				'Metalquente | ' . $up . '2026/09/Camada-0.webp',
				'Humaitá Pneus | ' . $up . '2026/09/Ativo-3@2x-scaled-1-768x232.webp',
				'Harmmonie | ' . $up . '2026/09/logo_harmmonie-1.webp',
				'Dr. Anderson Maicon | ' . $up . '2026/09/logonormal_semfundo-1-scaled-1.webp',
				'Luma Análises Clínicas | ' . $up . '2026/09/logoazul_semfundo_luma-1.webp',
				'Ludere | ' . $up . '2026/09/Ativo-1.svg',
				'Aggily | ' . $up . '2026/09/Logo-Aggily-WHITE.webp',
			)
		),
		'depoimentos'   => implode(
			"\n",
			array(
				'Luma Análises Clínicas | A Marília e sua equipe são muito responsáveis, fazem tudo com muita seriedade e comprometimento! Sempre que podemos recomendamos a LDK para colegas e conhecidos que desejam investir nesse tipo de serviço.',
				'Edgard Faria | Os roteiros de vídeos sempre bem adaptados e enviados com antecedência, assim como o planejamento e os dados informativos sobre as campanhas. A agência ainda conta com um treinamento para capacitar o meu comercial em como fazer a conversão do lead que vinha através do tráfego pago.',
				'Marina Mara | Muito satisfeita com a LDK, estratégias que têm tudo a ver com a minha empresa, sempre recebo muitos elogios dos clientes. Meu Instagram é de outro nível.',
			)
		),
		'capa_img'      => $up . '2026/09/bgg.webp',
		'metodologia'   => implode(
			"\n",
			array(
				'Diagnóstico e briefing | Reunião para entender o momento da empresa, o público, os concorrentes e onde queremos chegar.',
				'Planejamento estratégico | Linha editorial, calendário de conteúdo e estratégia de anúncios pensados para o objetivo do mês.',
				'Criação e produção | Artes, copys e vídeos com roteiro, gravação e edição, sempre com a identidade da sua marca.',
				'Gestão e resultados | Postagens programadas, campanhas no Meta Ads e no Google Ads e acompanhamento de perto, mês a mês.',
			)
		),
		'passos'        => implode(
			"\n",
			array(
				'Aprovar a proposta | Aprovar até {validade} e escolher o pacote que faz mais sentido para a {cliente_curto}.',
				'Reunião de briefing | Uma conversa para conhecer a fundo a {cliente_curto}, alinhar objetivos e liberar os acessos das redes e das contas de anúncio.',
				'Primeiro mês no ar | Planejamento aprovado, conteúdos produzidos e campanhas rodando. A partir daí, é acompanhar e escalar.',
			)
		),
		'invest_badge'  => '',
		'invest_titulo' => 'Escolha o pacote ideal para a {cliente_curto}.',
		'invest_nota'   => 'Pacotes mensais com planejamento, criação, vídeos e gestão de tráfego em um só lugar. Condição válida até {validade}.',
		'pos_nota'      => '*Será adicionada ajuda de custo para locomoção no dia da gravação dos vídeos, caso seja em outra cidade. A verba dos anúncios é paga direto às plataformas (Meta e Google) e não está inclusa no valor dos pacotes.',
		'perfis'        => '',
		'portfolio'     => implode(
			"\n",
			array(
				'Criativo 01 | Redes sociais | ' . $up . '2026/09/02-8-819x1024.webp | |',
				'Criativo 02 | Redes sociais | ' . $up . '2026/09/041-819x1024.webp | |',
				'Criativo 03 | Redes sociais | ' . $up . '2026/09/12-07-26-819x1024.webp | |',
				'Criativo 04 | Redes sociais | ' . $up . '2026/09/13-07-26-819x1024.webp | |',
				'Criativo 05 | Redes sociais | ' . $up . '2026/09/20-08-25-819x1024.webp | |',
				'Criativo 06 | Redes sociais | ' . $up . '2026/09/23-10-25-819x1024.webp | |',
				'Criativo 07 | Redes sociais | ' . $up . '2026/09/27-05-26-1-819x1024.webp | |',
				'Criativo 08 | Redes sociais | ' . $up . '2026/09/28-07-26-819x1024.webp | |',
				'Criativo 09 | Redes sociais | ' . $up . '2026/09/ARTE-1.1-819x1024.webp | |',
			)
		),
	);
}

/**
 * Campos que cada nicho guarda (usados para pré-preencher a proposta).
 */
function ldkp_niche_fields() {
	return array(
		'capa_desc'      => array( 'textarea', 'Texto da capa' ),
		'intro_frase'    => array( 'textarea', 'Frase de abertura' ),
		'cenario'        => array( 'textarea', '(O cenário)' ),
		'oportunidade'   => array( 'textarea', '(A oportunidade)' ),
		'diag_titulo'    => array( 'text', 'Título do diagnóstico' ),
		'diagnostico'    => array( 'lines', 'Pontos do diagnóstico (Título | descrição)' ),
		'contato_titulo' => array( 'text', 'Título final' ),
		'contato_texto'  => array( 'textarea', 'Texto final' ),
	);
}

/**
 * Campos que cada serviço guarda.
 */
function ldkp_service_fields() {
	return array(
		'escopo'    => array( 'lines', 'Itens de escopo (Título | descrição)' ),
		'tag'       => array( 'text', 'Etiqueta do plano' ),
		'desc'      => array( 'textarea', 'Descrição do plano' ),
		'prefixo'   => array( 'text', 'Antes do preço (ex.: R$ ou a partir de R$)' ),
		'preco_de'  => array( 'text', 'Preço "de" (riscado)' ),
		'preco_por' => array( 'text', 'Preço final' ),
		'pagamento' => array( 'text', 'Condição / prazo' ),
		'itens'     => array( 'textarea', 'Itens do plano (um por linha)' ),
		'cta'       => array( 'text', 'Texto do botão' ),
	);
}

function ldkp_default_services() {
	$escopo = function ( $artes, $videos ) {
		return implode(
			"\n",
			array(
				'Planejamento estratégico | Linha editorial, calendário mensal e metas pensados para o momento da {cliente_curto}.',
				'Artes + gerenciamento | ' . $artes . ' artes exclusivas por mês, com a identidade da {cliente_curto}, e gestão completa do perfil.',
				'Programação de postagens | Posts programados nos melhores horários, com copy estratégica escrita pela nossa equipe.',
				'Gestão de tráfego pago | Campanhas no Meta Ads e no Google Ads para atrair o público certo, no momento certo.',
				'Vídeos estratégicos | ' . $videos . ' vídeos por mês com roteiro, gravação e edição, pensados para gerar conexão e resultado.',
			)
		);
	};
	$itens = function ( $artes, $videos ) {
		return implode(
			"\n",
			array(
				'Planejamento estratégico',
				$artes . ' artes + gerenciamento',
				'Programação de postagens (com copy)',
				'Gestão de tráfego pago (Meta Ads e Google Ads)',
				$videos . ' vídeos (roteiro + gravação + edição)',
			)
		);
	};

	return array(
		'essencial-ldk'   => array(
			'title'     => 'Essencial LDK',
			'escopo'    => $escopo( 10, 4 ),
			'tag'       => 'Essencial',
			'desc'      => 'A base completa para a {cliente_curto} marcar presença com constância e começar a atrair clientes.',
			'prefixo'   => 'R$',
			'preco_de'  => '',
			'preco_por' => '1.997',
			'pagamento' => 'por mês',
			'itens'     => $itens( 10, 4 ),
			'cta'       => 'Quero o Essencial',
		),
		'evolucao-ldk'    => array(
			'title'     => 'Evolução LDK',
			'escopo'    => $escopo( 15, 6 ),
			'tag'       => 'Mais escolhido',
			'desc'      => 'Mais conteúdo e mais vídeos para acelerar o crescimento e ganhar autoridade mais rápido.',
			'prefixo'   => 'R$',
			'preco_de'  => '',
			'preco_por' => '2.297',
			'pagamento' => 'por mês',
			'itens'     => $itens( 15, 6 ),
			'cta'       => 'Quero o Evolução',
		),
		'ldk-performance' => array(
			'title'     => 'LDK Performance',
			'escopo'    => $escopo( 20, 10 ),
			'tag'       => 'Performance',
			'desc'      => 'Presença máxima: volume de conteúdo e vídeos para dominar as redes e escalar os resultados.',
			'prefixo'   => 'R$',
			'preco_de'  => '',
			'preco_por' => '2.997',
			'pagamento' => 'por mês',
			'itens'     => $itens( 20, 10 ),
			'cta'       => 'Quero o Performance',
		),
	) + ldkp_default_site_services();
}

/**
 * Serviços de site (projeto único, sem mensalidade no 1º ano).
 */
function ldkp_default_site_services() {
	$hospedagem = 'Domínio e hospedagem grátis no 1º ano | Hospedagem com servidor dedicado, certificado SSL e e-mails corporativos ilimitados, e domínio .com.br registrado no seu nome. Tudo incluso no valor, sem custo nos primeiros 12 meses.';
	$suporte    = 'Suporte por 6 meses | Ajustes, dúvidas e acompanhamento durante os 6 meses após a entrega, com métricas mensais de resultado e área do cliente para acompanhar tudo.';
	$gmn        = 'Google Meu Negócio | Otimização completa do perfil e monitoramento para a {cliente_curto} aparecer mais no Google Maps e na busca local.';

	return array(
		'landing-page'       => array(
			'title'     => 'Landing Page',
			'escopo'    => "Estratégia e copy | Estrutura de página e textos persuasivos pensados juntos para levar o visitante a uma única ação.\nDesign exclusivo | Layout pensado primeiro no celular e alinhado à identidade da {cliente_curto}. Nada de modelo genérico.\nDesenvolvimento com painel | Página publicada com painel para você editar textos e imagens sozinho.\nWhatsApp e conversão | Botões com mensagem pronta e ferramentas de análise configuradas. Pronta para receber tráfego pago no Google e no Meta.\n" . $gmn . "\n" . $hospedagem . "\n" . $suporte,
			'tag'       => 'Entrada rápida',
			'desc'      => 'Uma página, um objetivo. Ideal para campanhas no Google e no Meta. No ar em 7 a 15 dias úteis.',
			'prefixo'   => 'R$',
			'preco_de'  => '',
			'preco_por' => '1.600',
			'pagamento' => '10x de R$ 160 sem juros · ou R$ 1.400 à vista',
			'itens'     => "Domínio e hospedagem grátis no 1º ano\n1 página de alta conversão com copy estratégica\nOtimização e monitoramento do Google Meu Negócio\nE-mails corporativos e métricas mensais\nÁrea do cliente para acompanhar o projeto\nSuporte por 6 meses",
			'cta'       => 'Quero a Landing Page',
		),
		'site-institucional' => array(
			'title'     => 'Site Institucional',
			'escopo'    => "Arquitetura de informação | Páginas organizadas para responder quem você é, o que faz e por que confiar.\nDesign exclusivo | Layout editorial, pensado primeiro no celular e alinhado à identidade da {cliente_curto}.\nPáginas institucionais e de serviços | Início, sobre, contato e uma página para cada serviço, explicando o que você faz e aparecendo no Google para quem procura.\nBlog e artigos | Blog com artigos otimizados para SEO, para ser encontrado por quem pesquisa o problema que você resolve.\nSEO técnico | Estrutura, velocidade e configurações para aparecer no Google desde o lançamento.\n" . $gmn . "\n" . $hospedagem . "\n" . $suporte,
			'tag'       => 'Recomendado',
			'desc'      => 'Site completo com páginas de serviços, blog e Google Meu Negócio. No ar em 15 a 30 dias úteis.',
			'prefixo'   => 'R$',
			'preco_de'  => '',
			'preco_por' => '4.300',
			'pagamento' => '10x de R$ 430 sem juros · ou R$ 3.870 à vista',
			'itens'     => "Domínio e hospedagem grátis no 1º ano\nPáginas institucionais e de serviços\nBlog com artigos otimizados para SEO\nOtimização e monitoramento do Google Meu Negócio\nE-mails corporativos e métricas mensais\nSuporte por 6 meses",
			'cta'       => 'Quero o Site Institucional',
		),
		'e-commerce'         => array(
			'title'     => 'E-commerce',
			'escopo'    => "Loja virtual completa | Catálogo, carrinho, frete e checkout pensados para o momento em que o cliente decide comprar.\nPagamentos e integrações | Pix, cartão e boleto, cálculo de frete, controle de estoque e integrações com as ferramentas do seu negócio.\nDesign exclusivo | Layout pensado primeiro no celular e alinhado à identidade da {cliente_curto}, com o produto como protagonista.\n" . $gmn . "\n" . $hospedagem . "\n" . $suporte,
			'tag'       => 'Loja online',
			'desc'      => 'Loja completa para vender direto, com pagamentos, frete e estoque integrados.',
			'prefixo'   => 'R$',
			'preco_de'  => '',
			'preco_por' => '5.800',
			'pagamento' => '10x de R$ 580 sem juros · ou R$ 5.200 à vista',
			'itens'     => "Domínio e hospedagem grátis no 1º ano\nLoja com pagamentos, frete e estoque\nOtimização e monitoramento do Google Meu Negócio\nE-mails corporativos e métricas mensais\nÁrea do cliente para acompanhar o projeto\nSuporte por 6 meses",
			'cta'       => 'Quero o E-commerce',
		),
		'sistemas'           => array(
			'title'     => 'Site Institucional com Sistema',
			'escopo'    => "Site institucional com sistema | Site moderno e estratégico, que transmite credibilidade e fortalece a imagem da {cliente_curto}, com um sistema de gestão feito sob medida para a operação.\nPainel de gestão do conteúdo | Cadastro e edição de imóveis, produtos ou serviços, com fotos, informações e listagens sempre organizadas e atualizadas.\nGestão de leads | Todos os contatos gerados pelo site ficam organizados em um banco de dados: interessados, histórico de contatos e oportunidades de venda em um só lugar.\nCopy e design estratégico | Comunicação que conduz o visitante até a decisão e layout pensado para o público da {cliente_curto}, 100% responsivo.\nDesempenho, otimização e integrações | Estrutura rápida, integrada e otimizada para performar desde o lançamento e não desperdiçar verba de anúncios.\nE-mails corporativos | E-mails exclusivos no domínio da {cliente_curto}, transmitindo profissionalismo em cada contato.\nMétricas mensais | Relatórios mensais para acompanhar o desempenho e melhorar as estratégias.\nHospedagem e domínio grátis no 1º ano | Hospedagem com servidor dedicado, certificado SSL e e-mails ilimitados, e domínio .com.br registrado no seu nome. Tudo incluso no primeiro ano.\nSuporte por 12 meses | Ajustes, dúvidas e acompanhamento do site durante os 12 meses após a entrega, com métricas mensais de resultado e área do cliente para acompanhar tudo.\nContrato de prestação de serviços | Garantia de entrega, suporte e cumprimento de tudo o que foi acordado, por escrito.",
			'tag'       => 'Site + sistema',
			'desc'      => 'Site institucional completo com sistema de gestão e de leads sob medida. Hospedagem e domínio grátis no 1º ano e 12 meses de suporte.',
			'prefixo'   => 'R$',
			'preco_de'  => '',
			'preco_por' => '5.800',
			'pagamento' => '10x de R$ 580 sem juros · 50% no início e 50% na entrega · ou R$ 5.200 à vista',
			'itens'     => "Site institucional com sistema sob medida\nPainel de gestão do conteúdo\nGestão de leads em banco de dados\nHospedagem e domínio grátis no 1º ano\nE-mails corporativos e métricas mensais\nÁrea do cliente para acompanhar o projeto\nSuporte por 12 meses",
			'cta'       => 'Quero o site com sistema',
		),
	);
}

/**
 * Cria os nichos e serviços padrão que ainda não existem.
 * Roda na ativação e a cada nova versão do plugin; não mexe nos que você já editou.
 */
function ldkp_seed_defaults() {
	if ( get_option( 'ldkp_seeded' ) === LDKP_VERSION ) {
		return;
	}

	$groups = array(
		'ldkp_nicho'   => array( ldkp_default_niches(), ldkp_niche_fields() ),
		'ldkp_servico' => array( ldkp_default_services(), ldkp_service_fields() ),
	);

	foreach ( $groups as $post_type => $group ) {
		list( $items, $fields ) = $group;
		$order                  = 0;
		foreach ( $items as $slug => $item ) {
			$order++;
			$exists = get_posts(
				array(
					'post_type'   => $post_type,
					'name'        => $slug,
					'post_status' => 'any',
					'numberposts' => 1,
					'fields'      => 'ids',
				)
			);
			if ( $exists ) {
				continue;
			}
			$id = wp_insert_post(
				array(
					'post_type'   => $post_type,
					'post_status' => 'publish',
					'post_title'  => $item['title'],
					'post_name'   => $slug,
					'menu_order'  => $order,
				)
			);
			if ( ! $id || is_wp_error( $id ) ) {
				continue;
			}
			foreach ( $fields as $key => $def ) {
				if ( isset( $item[ $key ] ) ) {
					update_post_meta( $id, '_ldkp_' . $key, $item[ $key ] );
				}
			}
		}
	}

	update_option( 'ldkp_seeded', LDKP_VERSION );
}

/**
 * Atualiza serviços padrão que mudaram de conteúdo entre versões,
 * desde que ainda estejam com o nome antigo (ou seja, não foram editados à mão).
 */
function ldkp_migrate_defaults() {
	$renamed = array(
		// slug => nome antigo.
		'sistemas' => 'Sistemas Personalizados',
	);
	$services = ldkp_default_services();

	foreach ( $renamed as $slug => $old_title ) {
		$ids = get_posts(
			array(
				'post_type'   => 'ldkp_servico',
				'name'        => $slug,
				'post_status' => 'any',
				'numberposts' => 1,
				'fields'      => 'ids',
			)
		);
		if ( ! $ids || get_the_title( $ids[0] ) !== $old_title || empty( $services[ $slug ] ) ) {
			continue;
		}
		wp_update_post(
			array(
				'ID'         => $ids[0],
				'post_title' => $services[ $slug ]['title'],
			)
		);
		foreach ( ldkp_service_fields() as $key => $def ) {
			if ( isset( $services[ $slug ][ $key ] ) ) {
				update_post_meta( $ids[0], '_ldkp_' . $key, $services[ $slug ][ $key ] );
			}
		}
	}
}

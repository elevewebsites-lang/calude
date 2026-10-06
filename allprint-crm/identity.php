<?php
/**
 * IDENTIDADE DO PROJETO: o único arquivo que muda em cada CRM novo.
 *
 * - nome, contatos, logos, cores e fontes (o tema inteiro sai daqui);
 * - módulos ligados (o núcleo é sempre carregado);
 * - configurações padrão (o que vier aqui sobrescreve os padrões do núcleo).
 *
 * Depois de instalado, tudo isso também pode ser mudado em Configurações.
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function ap_identity() {
	return array(
		'nome'     => 'Allprint',
		'site'     => 'https://alprintpro.com.br',
		'whatsapp' => '(12) 99685-7165',
		'email'    => 'contato@alprintpro.com.br',
		// Logos: o sistema traz as versões sem fundo (colorida, branca e monocromática) em assets/brand/
		// e escolhe sozinho a de melhor contraste para cada fundo. Só preencha aqui para usar outras imagens.
		'logo'     => '',   // versão para fundo escuro (opcional)
		'logo_cor' => '',   // versão para fundo claro (opcional)
		'icone'    => '',
		'favicon'  => '',

		// Tema: cores (claro/escuro) e fontes do Google Fonts.
		'tema'     => array(
			'ink'     => '#15132B', // texto e botões principais (azul-marinho AllPrint)
			'bg'      => '#F5F5F7', // fundo do painel
			'accent'  => '#FDCC2A', // amarelo AllPrint
			'side'    => '#15132B', // menu lateral
			'font'    => 'Inter',
			'head'    => 'Inter',
			'google'  => 'Inter:wght@400;500;600;700;800',
			'radius'  => '14px',
		),

		// Módulos opcionais: comente para desligar.
		//   estoque     → filamentos/materiais, insumos com custo médio, lista de compras, máquinas, calculadora, leitura do fatiador
		//   frete       → cotação Melhor Envio (Correios, Jadlog…)
		//   produtos    → portfólio/catálogo, importar por link, preço por canal (Mercado Livre, Shopee…)
		//   marketing   → ideias de post (modelos + IA)
		'modulos'  => array( 'estoque' ),
		'paginas_off' => array( 'filamentos', 'impressoras', 'calculadora' ), // do módulo estoque só ficam Insumos e Compras

		// Configurações padrão específicas do projeto (chaves de ap_default_settings()).
		'config'   => array(
			'colunas'        => "Aguardando pagamento\nRevisão AllPrint\nProdução\nAcabamento\nPronto para retirada\nEntregue",
			'coluna_pronto'  => 'pronto-para-retirada',
			'funil'          => "Novo contato\nCadastro em análise\nParceiro aprovado\nPrimeiro pedido\nPerdido",
			'origens'        => "Indicação\nSite\nWhatsApp\nInstagram\nVisita\nOutro",
			'categorias_in'  => "Pedido\nOrçamento\nOutros",
			'categorias_out' => "Material\nTinta\nManutenção\nEquipamento\nImpostos\nTaxas\nOutros",
			'retirada_texto' => 'Retirada no balcão em horário comercial, sem agendamento. Se preferir, envie um motoboy ou app de entrega: liberamos o material após a confirmação do pagamento.',
			'validade_dias'  => '7',
			'desconto_pix'   => '0',
			'parcelas_max'   => '1',
			'mail_from_name' => 'AllPrint',
			'instagram'      => '@alprintpro',
			'endereco'       => 'Rua Luís Corrêa Viana, 89, Jardim das Bandeiras, Taubaté/SP, CEP 12051-060',
			'area_minima'    => '1',
			'preco_ilhos'    => '10',
			'preco_laminacao' => '10',
			'prazo_dias'     => '1',
			'credito_boas_vindas' => '50', // R$ de presente no primeiro pedido feito pelo sistema (0 = desligado)
			'credito_validade_dias' => '60',
			'apontamentos'   => '1',
			'apontamentos_clientes' => '1',
		),
	);
}

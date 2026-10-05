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

function lk_identity() {
	return array(
		'nome'     => 'LDK',
		'site'     => 'https://ldkmarketingdigital.com.br',
		'whatsapp' => '(12) 98825-7644',
		'email'    => '',
		'logo'     => 'https://ldkmarketingdigital.com.br/wp-content/uploads/2026/09/Screenshot_2026-08-11_at_13.26.35-removebg-preview.webp', // versão clara, para fundo escuro (menu, login, e-mails)
		'icone'    => '',
		'favicon'  => '',

		// Tema: cores (claro/escuro) e fontes do Google Fonts.
		'tema'     => array(
			'ink'     => '#05080B', // texto e botões principais
			'bg'      => '#F4F4F4', // fundo do painel
			'accent'  => '#14E9EC', // ciano LDK
			'side'    => '#05080B', // menu lateral
			'font'    => 'Montserrat',
			'head'    => 'Montserrat',
			'google'  => 'Montserrat:wght@400;500;600;700;800',
			'radius'  => '14px',
		),

		// Módulos opcionais: comente para desligar.
		//   estoque     → filamentos/materiais, insumos com custo médio, lista de compras, máquinas, calculadora, leitura do fatiador
		//   frete       → cotação Melhor Envio (Correios, Jadlog…)
		//   produtos    → portfólio/catálogo, importar por link, preço por canal (Mercado Livre, Shopee…)
		//   marketing   → ideias de post (modelos + IA)
		'modulos'  => array( 'marketing' ),

		// Configurações padrão específicas do projeto (chaves de lk_default_settings()).
		'config'   => array(
			'mail_from_name' => 'LDK Marketing Digital',
			'retirada_texto' => '',
		),
	);
}

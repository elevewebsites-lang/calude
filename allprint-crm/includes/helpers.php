<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/* -----------------------------------------------------------------------
 * Configurações
 * -------------------------------------------------------------------- */

function ap_default_settings() {
	return array(
		'empresa'        => 'Minha Empresa',
		'logo'           => '',
		'logo_cor'       => '',
		'cor_ink'        => '',
		'cor_bg'         => '',
		'cor_accent'     => '',
		'cor_side'       => '',
		'logo_icone'     => '',
		'favicon'        => '',
		'site'           => '',
		'whatsapp'       => '',
		'email'          => '',
		'instagram'      => '',
		'colunas'        => "Aguardando pagamento\nNa fila\nImprimindo\nAcabamento\nFotos\nPronto\nEntregue",
		'coluna_pronto'  => 'pronto',
		'funil'          => "Novo contato\nOrçamento enviado\nNegociando\nFechado\nPerdido",
		'origens'        => "Instagram\nIndicação\nSite\nWhatsApp\nGoogle\nMercado Livre\nShopee\nFeira/evento\nOutro",
		'categorias_in'  => "Pedido\nVenda marketplace\nOutros",
		'categorias_out' => "Filamento\nInsumos\nEquipamento\nManutenção\nFrete\nMarketing\nImpostos\nTaxas\nOutros",
		'metodos'        => "Pix\nCartão de crédito\nDinheiro\nTransferência\nMarketplace",
		'aviso_dias'     => '7',
		// Calculadora.
		'kwh'            => '1,05',
		'mao_obra_hora'  => '35',
		'modelagem_hora' => '50',
		'falha'          => '10',
		'perda_filamento' => '5',
		'tipos_preco'    => "Brinde/corporativo | 2,0\nDecoração e presentes | 2,5\nGeek/colecionável | 3,0\nPeça técnica/protótipo | 3,0",
		'dificuldades'   => "Fácil | 0\nMédia | 10\nDifícil | 25",
		'descontos_qtd'  => "1 | 0\n10 | 10\n20 | 15\n50 | 20\n100 | 25",
		'piso_markup'    => '1,5',
		'revenda_descontos' => "10 | 15\n30 | 25\n50 | 30\n100 | 35",
		'revenda_sugerido' => '2,0',
		'revenda_piso'   => '1,3',
		'empresa_nota'   => 'Emitimos recibo. Prazo de produção a partir da aprovação da arte/modelo.',
		'urgencia'       => '30',
		'pedido_minimo'  => '30',
		'arredondar'     => '90',
		// Pagamento.
		'infinitepay'    => '',
		'infinitepay_token' => '',
		'parcelas_max'   => '3',
		'parcelas_sem_juros' => '3',
		'desconto_pix'   => '5',
		'validade_dias'  => '7',
		'cobranca_auto'  => '1',
		'cobranca_dias'  => '3',
		// Frete (Melhor Envio).
		'cep_origem'     => '',
		'melhorenvio_token' => '',
		'melhorenvio_sandbox' => '0',
		'caixas'         => "Pequena | 16 | 11 | 6\nMédia | 25 | 20 | 10\nGrande | 35 | 30 | 20",
		'retirada_texto' => 'Retirada com horário combinado pelo WhatsApp.',
		// E-mail e integrações.
		'smtp_host'      => '',
		'smtp_port'      => '465',
		'smtp_secure'    => 'ssl',
		'smtp_user'      => '',
		'smtp_pass'      => '',
		'mail_from'      => '',
		'mail_from_name' => '',
		'email_boasvindas' => '1',
		'email_etapas'   => '1',
		'email_pronto'   => '1',
		'email_pagamento' => '1',
		'google_client_id' => '',
		'google_client_secret' => '',
		'google_pasta'   => '',
		'anthropic_key'  => '',
		'ai_model'       => 'claude-sonnet-5',
		'ml_app_id'      => '',
		'ml_secret'      => '',
		'shopee_partner_id' => '',
		'shopee_key'     => '',
		'taxas_canais'   => "Loja própria | 0 | 0\nMercado Livre | 14 | 6,75\nShopee | 20 | 4\nElo7 | 18 | 0\nAmazon | 15 | 0",
		'endereco'       => '',
		'area_minima'    => '1',
		'preco_ilhos'    => '10',
		'preco_laminacao' => '10',
		'prazo_dias'     => '1',
		'seg_2fa'        => '1',
		// Apontamentos (botão "Apontar" para o cliente marcar ajustes na tela).
		'apontamentos'   => '1',
		'apontamentos_clientes' => '0',
		'apontamentos_email' => '',
	);
}

/**
 * Padrões do núcleo + identidade do projeto (identity.php).
 */
function ap_defaults_with_identity() {
	static $d = null;
	if ( null === $d ) {
		$id = ap_identity();
		$d  = array_merge(
			ap_default_settings(),
			array_filter(
				array(
					'empresa'        => $id['nome'],
					'mail_from_name' => $id['nome'],
					'site'           => $id['site'],
					'whatsapp'       => $id['whatsapp'],
					'email'          => $id['email'],
					'logo'           => $id['logo'],
					'logo_cor'       => $id['logo_cor'] ?? '',
					'logo_icone'     => $id['icone'],
					'favicon'        => $id['favicon'],
				),
				'strlen'
			),
			(array) $id['config']
		);
	}
	return $d;
}

function ap_settings() {
	$saved = get_option( 'ap_settings', array() );
	return wp_parse_args( is_array( $saved ) ? $saved : array(), ap_defaults_with_identity() );
}

function ap_setting( $key ) {
	$s = ap_settings();
	return isset( $s[ $key ] ) ? $s[ $key ] : '';
}

/**
 * Lista simples: um item por linha.
 */
function ap_list( $text ) {
	return array_values( array_filter( array_map( 'trim', preg_split( '/\r\n|\r|\n/', (string) $text ) ) ) );
}

/**
 * Linhas "Título | extra" viram pares.
 */
function ap_pairs( $text, $cols = 2 ) {
	$out = array();
	foreach ( ap_list( $text ) as $line ) {
		$out[] = array_pad( array_map( 'trim', explode( '|', $line, $cols ) ), $cols, '' );
	}
	return $out;
}

/**
 * Colunas do Kanban de projetos: slug => nome. A última é "entregue".
 */
function ap_columns() {
	$cols = array();
	foreach ( ap_list( ap_setting( 'colunas' ) ) as $name ) {
		$cols[ sanitize_title( $name ) ] = $name;
	}
	return $cols ? $cols : array( 'pago' => 'Pago', 'pronto' => 'Pronto', 'entregue' => 'Entregue' );
}

/**
 * Coluna sugerida para projeto que já estava em andamento: "Em produção", se existir.
 */
function ap_first_column_after_briefing() {
	$keys = array_keys( ap_columns() );
	foreach ( $keys as $k ) {
		if ( false !== strpos( $k, 'produ' ) ) {
			return $k;
		}
	}
	return isset( $keys[1] ) ? $keys[1] : $keys[0];
}

function ap_last_column() {
	$keys = array_keys( ap_columns() );
	return end( $keys );
}

function ap_task_statuses() {
	return array(
		'todo'   => 'A fazer',
		'doing'  => 'Fazendo',
		'review' => 'Revisão',
		'done'   => 'Feito',
	);
}

function ap_priorities() {
	return array(
		'baixa'   => 'Baixa',
		'normal'  => 'Normal',
		'alta'    => 'Alta',
		'urgente' => 'Urgente',
	);
}

/* -----------------------------------------------------------------------
 * Permissões
 * -------------------------------------------------------------------- */

/**
 * Áreas do painel que podem ser liberadas para a equipe.
 */
function ap_areas() {
	return array(
		'clientes'   => 'Clientes',
		'leads'      => 'Funil de leads',
		'orcamentos' => 'Orçamentos',
		'projetos'   => 'Pedidos',
		'estoque'    => 'Estoque, insumos e compras',
		'produtos'   => 'Produtos e marketplaces',
		'produtos_cat' => 'Tabela de preços',
		'emails'     => 'E-mails para clientes',
		'tarefas'    => 'Tarefas',
		'financeiro' => 'Financeiro',
	);
}

function ap_is_admin( $user_id = 0 ) {
	return user_can( $user_id ? $user_id : get_current_user_id(), 'manage_options' );
}

function ap_is_team( $user_id = 0 ) {
	$user = $user_id ? get_userdata( $user_id ) : wp_get_current_user();
	return $user && $user->exists() && ( ap_is_admin( $user->ID ) || in_array( 'ap_team', (array) $user->roles, true ) );
}

function ap_is_client_user( $user_id = 0 ) {
	$user = $user_id ? get_userdata( $user_id ) : wp_get_current_user();
	return $user && $user->exists() && in_array( 'ap_client', (array) $user->roles, true );
}

/**
 * O usuário da equipe pode ver esta área? Admin vê tudo.
 */
function ap_can( $area, $user_id = 0 ) {
	$user_id = $user_id ? $user_id : get_current_user_id();
	if ( ap_is_admin( $user_id ) ) {
		return true;
	}
	if ( ! ap_is_team( $user_id ) ) {
		return false;
	}
	$perms = (array) get_user_meta( $user_id, 'ap_perms', true );
	return in_array( $area, $perms, true );
}

/**
 * Membro da equipe que só vê as tarefas atribuídas a ele.
 */
function ap_only_own_tasks( $user_id = 0 ) {
	$user_id = $user_id ? $user_id : get_current_user_id();
	return ! ap_is_admin( $user_id ) && (bool) get_user_meta( $user_id, 'ap_only_own', true );
}

function ap_add_roles() {
	// Só leitura: os envios de arquivo passam pelo sistema, que confere o dono do projeto.
	add_role( 'ap_client', 'Cliente (CRM)', array( 'read' => true ) );
	add_role( 'ap_team', 'Equipe (CRM)', array( 'read' => true ) );
}

function ap_team_users() {
	return get_users(
		array(
			'role__in' => array( 'administrator', 'ap_team' ),
			'orderby'  => 'display_name',
		)
	);
}

/* -----------------------------------------------------------------------
 * Endereços
 * -------------------------------------------------------------------- */

function ap_url( $path = '', $args = array() ) {
	$url = home_url( '/' . ltrim( $path, '/' ) );
	if ( $path && '/' !== substr( $url, -1 ) && false === strpos( $path, '?' ) ) {
		$url .= '/';
	}
	return $args ? add_query_arg( $args, $url ) : $url;
}

function ap_panel_url( $section = '', $id = 0, $args = array() ) {
	$path = 'painel' . ( $section ? '/' . $section : '' ) . ( $id ? '/' . (int) $id : '' );
	return ap_url( $path, $args );
}

function ap_client_url( $section = '', $id = 0, $args = array() ) {
	$path = 'cliente' . ( $section ? '/' . $section : '' ) . ( $id ? '/' . (int) $id : '' );
	return ap_url( $path, $args );
}

function ap_invite_url( $token ) {
	return ap_url( 'convite/' . $token );
}

/**
 * Datas de hospedagem e domínio do formulário. Com "same_date" marcado (padrão), a hospedagem
 * vence junto com o domínio; desmarcado, cada uma tem a sua data.
 * Devolve array( host_end, domain_end ).
 */
function ap_linked_dates() {
	$domain = ap_in( 'domain_end', 'date' );
	$host   = ap_in( 'host_end', 'date' );
	if ( ap_in( 'same_date', 'bool' ) && $domain ) {
		$host = $domain;
	}
	return array( $host ? $host : null, $domain ? $domain : null );
}

/**
 * Link de WhatsApp com mensagem pronta.
 */
function ap_wa_link( $phone, $message = '' ) {
	$phone  = trim( (string) $phone );
	$digits = preg_replace( '/\D+/', '', $phone );
	// Número internacional salvo como "+351 912 345 678": usa como está. Sem "+", é do Brasil.
	if ( $digits && '+' !== substr( $phone, 0, 1 ) && strlen( $digits ) <= 11 ) {
		$digits = '55' . $digits;
	}
	$url = 'https://wa.me/' . $digits;
	return $message ? $url . '?text=' . rawurlencode( $message ) : $url;
}

/* -----------------------------------------------------------------------
 * Formatação
 * -------------------------------------------------------------------- */

function ap_money( $value ) {
	return 'R$ ' . number_format( (float) $value, 2, ',', '.' );
}

/**
 * Valor em reais para número: "1.234,56", "4.300", "R$ 1.600" e "1234.56" funcionam.
 */
function ap_parse_money( $value ) {
	$value = preg_replace( '/[^\d,.-]/', '', (string) $value );
	$neg   = 0 === strpos( $value, '-' );
	$value = str_replace( '-', '', $value );
	$last  = max( (int) strrpos( $value, ',' ), (int) strrpos( $value, '.' ) );
	$has   = false !== strpos( $value, ',' ) || false !== strpos( $value, '.' );
	if ( $has ) {
		$sep  = $value[ $last ];
		$tail = substr( $value, $last + 1 );
		// Vírgula é sempre centavos. Ponto é centavos só com 1 ou 2 dígitos depois
		// ("4.300.00" e "4300.5"); com 3 dígitos é milhar ("4.300").
		if ( ',' === $sep || strlen( $tail ) <= 2 ) {
			$value = preg_replace( '/[.,]/', '', substr( $value, 0, $last ) ) . '.' . $tail;
		} else {
			$value = preg_replace( '/[.,]/', '', $value );
		}
	}
	return round( ( $neg ? -1 : 1 ) * (float) $value, 2 );
}

function ap_date( $date, $format = 'd/m/Y' ) {
	if ( ! $date || '0000-00-00' === substr( $date, 0, 10 ) ) {
		return '';
	}
	return date_i18n( $format, strtotime( $date ) );
}

function ap_today() {
	return current_time( 'Y-m-d' );
}

function ap_now() {
	return current_time( 'mysql' );
}

/**
 * Dias até a data (negativo = já passou).
 */
function ap_days_until( $date ) {
	if ( ! $date || '0000-00-00' === substr( $date, 0, 10 ) ) {
		return null;
	}
	$today = new DateTime( ap_today() );
	$then  = new DateTime( substr( $date, 0, 10 ) );
	return (int) $today->diff( $then )->format( '%r%a' );
}

function ap_clean_date( $value ) {
	$value = trim( (string) $value );
	return preg_match( '/^\d{4}-\d{2}-\d{2}$/', $value ) ? $value : null;
}

function ap_initials( $name ) {
	$parts = preg_split( '/\s+/', trim( (string) $name ) );
	$first = $parts ? mb_substr( $parts[0], 0, 1 ) : '';
	$last  = count( $parts ) > 1 ? mb_substr( end( $parts ), 0, 1 ) : '';
	return mb_strtoupper( $first . $last );
}

/* -----------------------------------------------------------------------
 * Senhas guardadas (aba Acessos): criptografadas com a chave do site
 * -------------------------------------------------------------------- */

function ap_crypto_key() {
	return hash( 'sha256', wp_salt( 'secure_auth' ) . 'allprint-crm', true );
}

function ap_encrypt( $plain ) {
	if ( '' === (string) $plain ) {
		return '';
	}
	if ( function_exists( 'sodium_crypto_secretbox' ) ) {
		$nonce = random_bytes( SODIUM_CRYPTO_SECRETBOX_NONCEBYTES );
		return 's:' . base64_encode( $nonce . sodium_crypto_secretbox( $plain, $nonce, ap_crypto_key() ) );
	}
	$iv = random_bytes( 16 );
	return 'o:' . base64_encode( $iv . openssl_encrypt( $plain, 'aes-256-cbc', ap_crypto_key(), OPENSSL_RAW_DATA, $iv ) );
}

function ap_decrypt( $stored ) {
	if ( '' === (string) $stored ) {
		return '';
	}
	$raw = base64_decode( substr( $stored, 2 ) );
	if ( 's:' === substr( $stored, 0, 2 ) && function_exists( 'sodium_crypto_secretbox_open' ) ) {
		$nonce = substr( $raw, 0, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES );
		$plain = sodium_crypto_secretbox_open( substr( $raw, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES ), $nonce, ap_crypto_key() );
		return false === $plain ? '' : $plain;
	}
	if ( 'o:' === substr( $stored, 0, 2 ) ) {
		$plain = openssl_decrypt( substr( $raw, 16 ), 'aes-256-cbc', ap_crypto_key(), OPENSSL_RAW_DATA, substr( $raw, 0, 16 ) );
		return false === $plain ? '' : $plain;
	}
	return '';
}

/* -----------------------------------------------------------------------
 * Avisos entre páginas (depois de salvar um formulário)
 * -------------------------------------------------------------------- */

function ap_flash( $message, $type = 'ok' ) {
	set_transient( 'ap_flash_' . get_current_user_id(), array( $message, $type ), 60 );
}

function ap_take_flash() {
	$key   = 'ap_flash_' . get_current_user_id();
	$flash = get_transient( $key );
	if ( $flash ) {
		delete_transient( $key );
	}
	return $flash;
}

/**
 * Ícones em linha (traço), no estilo do painel.
 */
function ap_icon( $name, $size = 18 ) {
	$paths = array(
		'dashboard' => '<rect x="3" y="3" width="7" height="9" rx="1.5"/><rect x="14" y="3" width="7" height="5" rx="1.5"/><rect x="14" y="12" width="7" height="9" rx="1.5"/><rect x="3" y="16" width="7" height="5" rx="1.5"/>',
		'clientes'  => '<path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M22 21v-2a4 4 0 0 0-3-3.87M16 3.13a4 4 0 0 1 0 7.75"/>',
		'projetos'  => '<rect x="3" y="3" width="18" height="18" rx="2"/><path d="M9 3v18M15 3v18"/>',
		'tarefas'   => '<path d="m9 11 3 3L22 4"/><path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"/>',
		'rotinas'   => '<path d="M21 12a9 9 0 1 1-3-6.7L21 8"/><path d="M21 3v5h-5"/>',
		'financeiro' => '<path d="M12 1v22M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/>',
		'servicos'  => '<path d="M14.7 6.3a1 1 0 0 0 0 1.4l1.6 1.6a1 1 0 0 0 1.4 0l3.77-3.77a6 6 0 0 1-7.94 7.94l-6.91 6.91a2.12 2.12 0 0 1-3-3l6.91-6.91a6 6 0 0 1 7.94-7.94l-3.76 3.76z"/>',
		'equipe'    => '<path d="M12 12a4 4 0 1 0 0-8 4 4 0 0 0 0 8z"/><path d="M4 21v-1a6 6 0 0 1 6-6h4a6 6 0 0 1 6 6v1"/>',
		'config'    => '<circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 1 1-2.83 2.83l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 1 1-4 0v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 1 1-2.83-2.83l.06-.06A1.65 1.65 0 0 0 4.68 15a1.65 1.65 0 0 0-1.51-1H3a2 2 0 1 1 0-4h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 1 1 2.83-2.83l.06.06A1.65 1.65 0 0 0 9 4.68a1.65 1.65 0 0 0 1-1.51V3a2 2 0 1 1 4 0v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 1 1 2.83 2.83l-.06.06A1.65 1.65 0 0 0 19.4 9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 1 1 0 4h-.09a1.65 1.65 0 0 0-1.51 1z"/>',
		'sair'      => '<path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4M16 17l5-5-5-5M21 12H9"/>',
		'busca'     => '<circle cx="11" cy="11" r="8"/><path d="m21 21-4.35-4.35"/>',
		'mais'      => '<path d="M12 5v14M5 12h14"/>',
		'editar'    => '<path d="M12 20h9"/><path d="M16.5 3.5a2.12 2.12 0 0 1 3 3L7 19l-4 1 1-4Z"/>',
		'link'      => '<path d="M10 13a5 5 0 0 0 7.54.54l3-3a5 5 0 0 0-7.07-7.07l-1.72 1.71"/><path d="M14 11a5 5 0 0 0-7.54-.54l-3 3a5 5 0 0 0 7.07 7.07l1.71-1.71"/>',
		'arquivo'   => '<path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><path d="M14 2v6h6"/>',
		'chave'     => '<circle cx="7.5" cy="15.5" r="5.5"/><path d="m21 2-9.6 9.6M15.5 7.5l3 3L22 7l-3-3"/>',
		'relogio'   => '<circle cx="12" cy="12" r="10"/><path d="M12 6v6l4 2"/>',
		'alerta'    => '<path d="M10.29 3.86 1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><path d="M12 9v4M12 17h.01"/>',
		'check'     => '<path d="M20 6 9 17l-5-5"/>',
		'grafico'   => '<path d="M3 3v18h18"/><path d="m7 15 4-4 3 3 5-6"/>',
		'download'  => '<path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><path d="m7 10 5 5 5-5"/><path d="M12 15V3"/>',
		'sol'       => '<circle cx="12" cy="12" r="4"/><path d="M12 2v2M12 20v2M4.93 4.93l1.41 1.41M17.66 17.66l1.41 1.41M2 12h2M20 12h2M6.34 17.66l-1.41 1.41M19.07 4.93l-1.41 1.41"/>',
		'lua'       => '<path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"/>',
		'olho-off'  => '<path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94"/><path d="M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19"/><path d="M14.12 14.12a3 3 0 1 1-4.24-4.24"/><path d="m1 1 22 22"/>',
		'alvo'      => '<circle cx="12" cy="12" r="9"/><circle cx="12" cy="12" r="5"/><circle cx="12" cy="12" r="1"/>',
		'tela'      => '<path d="M8 3H5a2 2 0 0 0-2 2v3M21 8V5a2 2 0 0 0-2-2h-3M3 16v3a2 2 0 0 0 2 2h3M16 21h3a2 2 0 0 0 2-2v-3"/>',
		'som'       => '<path d="M11 5 6 9H2v6h4l5 4V5z"/><path d="M15.54 8.46a5 5 0 0 1 0 7.07M19.07 4.93a10 10 0 0 1 0 14.14"/>',
		'lampada'   => '<path d="M9 18h6M10 22h4"/><path d="M12 2a7 7 0 0 0-4 12.7c.6.5 1 1.2 1 2V17h6v-.3c0-.8.4-1.5 1-2A7 7 0 0 0 12 2z"/>',
		'pulso'     => '<path d="M22 12h-4l-3 9L9 3l-3 9H2"/>',
		'megafone'  => '<path d="m3 11 18-5v12L3 14v-3z"/><path d="M11.6 16.8a3 3 0 1 1-5.8-1.6"/>',
		'whatsapp'  => '<path d="M21 11.5a8.38 8.38 0 0 1-.9 3.8 8.5 8.5 0 0 1-7.6 4.7 8.38 8.38 0 0 1-3.8-.9L3 21l1.9-5.7a8.38 8.38 0 0 1-.9-3.8 8.5 8.5 0 0 1 4.7-7.6 8.38 8.38 0 0 1 3.8-.9h.5a8.48 8.48 0 0 1 8 8v.5z"/>',
		'copiar'    => '<rect x="9" y="9" width="13" height="13" rx="2"/><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"/>',
		'lixo'      => '<path d="M3 6h18M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6M10 11v6M14 11v6M9 6V4a1 1 0 0 1 1-1h4a1 1 0 0 1 1 1v2"/>',
		'olho'      => '<path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/>',
		'seta'      => '<path d="M5 12h14M13 5l7 7-7 7"/>',
		'voltar'    => '<path d="M19 12H5M11 19l-7-7 7-7"/>',
		'menu'      => '<path d="M3 6h18M3 12h18M3 18h18"/>',
		'globo'     => '<circle cx="12" cy="12" r="10"/><path d="M2 12h20M12 2a15.3 15.3 0 0 1 4 10 15.3 15.3 0 0 1-4 10 15.3 15.3 0 0 1-4-10 15.3 15.3 0 0 1 4-10z"/>',
		'estrela'   => '<path d="m12 2 3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01z"/>',
		'funil'     => '<path d="M3 4h18l-7 8v6l-4 2v-8z"/>',
		'proposta'  => '<path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><path d="M14 2v6h6M8 13h8M8 17h5"/>',
		'briefing'  => '<path d="M9 5H7a2 2 0 0 0-2 2v12a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2V7a2 2 0 0 0-2-2h-2"/><rect x="9" y="3" width="6" height="4" rx="1"/><path d="M9 12h6M9 16h4"/>',
		'cubo'      => '<path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"/><path d="M3.3 7 12 12l8.7-5M12 22V12"/>',
		'carretel'  => '<circle cx="12" cy="12" r="9"/><circle cx="12" cy="12" r="3"/><path d="M12 3v6M12 15v6M3 12h6M15 12h6"/>',
		'caixa'     => '<path d="M21 8v13H3V8"/><path d="M1 3h22v5H1z"/><path d="M10 12h4"/>',
		'carrinho'  => '<circle cx="9" cy="21" r="1"/><circle cx="20" cy="21" r="1"/><path d="M1 1h4l2.7 13.4a2 2 0 0 0 2 1.6h9.7a2 2 0 0 0 2-1.6L23 6H6"/>',
		'impressora' => '<path d="M4 3h16v4H4z"/><path d="M6 7v6h12V7"/><path d="M3 13h18v8H3z"/><path d="M10 10h4M7 17h3"/>',
		'calculadora' => '<rect x="4" y="2" width="16" height="20" rx="2"/><path d="M8 6h8M8 11h.01M12 11h.01M16 11h.01M8 15h.01M12 15h.01M16 15h.01M8 19h.01M12 19h4"/>',
		'tag'       => '<path d="M20.6 13.4 13.4 20.6a2 2 0 0 1-2.8 0L2 12V2h10l8.6 8.6a2 2 0 0 1 0 2.8z"/><circle cx="7" cy="7" r="1.5"/>',
		'caminhao'  => '<path d="M1 3h15v13H1z"/><path d="M16 8h4l3 3v5h-7z"/><circle cx="5.5" cy="18.5" r="2.5"/><circle cx="18.5" cy="18.5" r="2.5"/>',
		'imagem'    => '<rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="8.5" cy="8.5" r="1.5"/><path d="m21 15-5-5L5 21"/>',
		'upload'    => '<path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><path d="m17 8-5-5-5 5M12 3v12"/>',
		'sino'      => '<path d="M18 8a6 6 0 0 0-12 0c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.7 21a2 2 0 0 1-3.4 0"/>',
		'chat'      => '<path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/>',
		'email'     => '<rect x="2" y="4" width="20" height="16" rx="2"/><path d="m22 7-10 6L2 7"/>',
		'drive'     => '<path d="M8 3h8l6 10-4 7H6l-4-7z"/><path d="M8 3l6 10H2M16 3l-6 10 4 7M22 13H10"/>',
	);
	$d = isset( $paths[ $name ] ) ? $paths[ $name ] : '';
	return '<svg class="ic" width="' . (int) $size . '" height="' . (int) $size . '" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' . $d . '</svg>';
}

/* -----------------------------------------------------------------------
 * Datas por extenso em português (não depende do idioma do WordPress)
 * -------------------------------------------------------------------- */

function ap_month_name( $n, $short = false ) {
	$names = array( 1 => 'janeiro', 'fevereiro', 'março', 'abril', 'maio', 'junho', 'julho', 'agosto', 'setembro', 'outubro', 'novembro', 'dezembro' );
	$name  = isset( $names[ (int) $n ] ) ? $names[ (int) $n ] : '';
	return $short ? mb_substr( $name, 0, 3 ) : $name;
}

/**
 * "sexta-feira, 25 de setembro".
 */
function ap_date_long( $date = '' ) {
	$ts   = $date ? strtotime( $date ) : current_time( 'timestamp' );
	$days = array( 'domingo', 'segunda-feira', 'terça-feira', 'quarta-feira', 'quinta-feira', 'sexta-feira', 'sábado' );
	return $days[ (int) gmdate( 'w', $ts ) ] . ', ' . gmdate( 'j', $ts ) . ' de ' . ap_month_name( gmdate( 'n', $ts ) );
}

/**
 * "setembro de 2026".
 */
function ap_month_label( $ym ) {
	$ts = strtotime( $ym . '-01' );
	return ap_month_name( gmdate( 'n', $ts ) ) . ' de ' . gmdate( 'Y', $ts );
}

/**
 * "há 5 min", "há 2 dias".
 */
function ap_ago( $datetime ) {
	$diff = max( 0, current_time( 'timestamp' ) - strtotime( $datetime ) );
	if ( $diff < 60 ) {
		return 'agora';
	}
	if ( $diff < 3600 ) {
		return 'há ' . floor( $diff / 60 ) . ' min';
	}
	if ( $diff < 86400 ) {
		$h = floor( $diff / 3600 );
		return 'há ' . $h . ( 1 == $h ? ' hora' : ' horas' ); // phpcs:ignore WordPress.PHP.StrictComparisons
	}
	$d = floor( $diff / 86400 );
	if ( $d < 31 ) {
		return 'há ' . $d . ( 1 == $d ? ' dia' : ' dias' ); // phpcs:ignore WordPress.PHP.StrictComparisons
	}
	return 'em ' . ap_date( $datetime );
}

/**
 * Texto das parcelas no cartão (a regra de juros fica configurada no app da InfinitePay).
 */
function ap_installments_text() {
	$max  = max( 1, (int) ap_setting( 'parcelas_max' ) );
	$free = min( $max, max( 0, (int) ap_setting( 'parcelas_sem_juros' ) ) );
	if ( $max <= 1 ) {
		return 'Pix ou cartão';
	}
	if ( $free >= $max ) {
		return 'Pix ou cartão em até ' . $max . 'x sem juros';
	}
	return 'Pix ou cartão em até ' . $max . 'x' . ( $free > 1 ? ' (até ' . $free . 'x sem juros)' : '' );
}

function ap_qty_label( $qty, $unit ) {
	$q = rtrim( rtrim( number_format( (float) $qty, 2, ',', '.' ), '0' ), ',' );
	return $q . ' ' . $unit;
}

/**
 * Nome a partir do link do Mercado Livre / Shopee (o trecho do endereço com o título).
 */
function ap_link_title( $url ) {
	$path = (string) wp_parse_url( $url, PHP_URL_PATH );
	$best = '';
	foreach ( explode( '/', $path ) as $seg ) {
		$seg = preg_replace( '/^MLB-?\d+-?/i', '', $seg );
		$seg = preg_replace( '/-_JM$|\.html?$|-i\.\d+\.\d+$/i', '', $seg );
		if ( strlen( $seg ) > strlen( $best ) ) {
			$best = $seg;
		}
	}
	$best = trim( str_replace( array( '-', '_' ), ' ', urldecode( $best ) ) );
	return $best ? mb_substr( ucfirst( $best ), 0, 120 ) : (string) wp_parse_url( $url, PHP_URL_HOST );
}

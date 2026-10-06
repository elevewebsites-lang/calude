<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/* -----------------------------------------------------------------------
 * Configurações
 * -------------------------------------------------------------------- */

function lk_default_settings() {
	return array(
		'empresa'        => 'Minha Empresa',
		'logo'           => '',
		'logo_icone'     => '',
		'favicon'        => '',
		'site'           => '',
		'whatsapp'       => '',
		'email'          => '',
		'instagram'      => '',
		'colunas'        => "Aguardando pagamento\nNa fila\nImprimindo\nAcabamento\nFotos\nPronto\nEntregue",
		'coluna_pronto'  => 'pronto',
		'funil'          => "Novo contato\nOrçamento enviado\nNegociando\nFechado\nPerdido",
		'origens'        => "Instagram\nIndicação\nSite\nWhatsApp\nGoogle\nEvento\nOutro",
		'categorias_in'  => "Mensalidade de cliente\nProjeto avulso\nGestão de tráfego\nProdução de vídeo\nOutros",
		'categorias_out' => "Aluguel\nSoftware e ferramentas\nSalários e equipe\nFreelancers (videomaker, designer)\nVerba de anúncios\nImpostos\nContabilidade\nInternet e telefone\nEnergia e água\nEquipamentos\nMarketing da agência\nTaxas bancárias\nOutros",
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
		'places_key'     => '',
		'google_client_secret' => '',
		'google_pasta'   => '',
		'gemini_key'    => '',
		'gemini_model'  => 'gemini-2.5-flash',
		'ai_choice'     => '',
		'groq_key'      => '',
		'groq_model'    => '',
		'mistral_key'   => '',
		'mistral_model' => '',
		'openrouter_key' => '',
		'openrouter_model' => '',
		'anthropic_key'  => '',
		'ai_model'       => 'claude-sonnet-5',
		'ml_app_id'      => '',
		'ml_secret'      => '',
		'shopee_partner_id' => '',
		'shopee_key'     => '',
		'taxas_canais'   => "Loja própria | 0 | 0\nMercado Livre | 14 | 6,75\nShopee | 20 | 4\nElo7 | 18 | 0\nAmazon | 15 | 0",
		'revisao_obrigatoria' => '1',
		'etapas_conteudo' => "Planejamento\nDesign\nRevisão\nAprovação do cliente\nAgendado\nPublicado",
		'etapa_planejamento' => 'planejamento',
		// Prazos de entrega: quantos dias antes da publicação cada etapa precisa estar pronta.
		'prazo_design'    => '5',
		'prazo_revisao'   => '3',
		'prazo_aprovacao' => '2',
		'prazo_hora'      => '18:00',
		'revisor_planejamento' => '',
		// Dados da agência para o contrato.
		'empresa_razao'         => '',
		'empresa_cnpj'          => '',
		'empresa_endereco'      => '',
		'empresa_cidade'        => '',
		'empresa_representante' => '',
		'contrato_modelo'       => '',
		'pacotes'              => '',
		'login_card'           => '1',
		'login_bg'             => '',
		// Sala de voz: servidor TURN opcional (para redes muito fechadas).
		'voz_turn_url'    => '',
		'voz_turn_user'   => '',
		'voz_turn_pass'   => '',
		// LinkedIn (páginas de empresa).
		'linkedin_client_id'     => '',
		'linkedin_client_secret' => '',
		'etapa_design'   => 'design',
		'etapa_revisao'  => 'revisao',
		'etapa_aprovacao' => 'aprovacao-do-cliente',
		'etapa_agendado' => 'agendado',
		'etapa_publicado' => 'publicado',
		'ig_app_id'      => '',
		'ig_app_secret'  => '',
		'meta_app_id'    => '',
		'meta_app_secret' => '',
		'meta_ads_token' => '',
		'assinatura_antecedencia' => '5',
		'briefing_perguntas' => "Conte um pouco sobre a sua empresa: o que faz e há quanto tempo.\nQuais são os principais produtos ou serviços que devemos divulgar?\nQuem é o seu cliente ideal (idade, cidade, interesses)?\nQuais são os seus diferenciais em relação aos concorrentes?\nQuem são os seus principais concorrentes?\nQual tom de voz combina com a marca (descontraído, sério, técnico, acolhedor…)?\nCores, fontes e elementos da marca que devemos usar (ou evitar).\nPerfis que você admira e usa como referência.\nO que você NÃO quer ver nas suas redes?\nObjetivo principal nas redes (vender, ser lembrado, gerar contatos, autoridade…).\nDatas importantes para a empresa (aniversário, promoções, sazonalidades).\nEndereço, horário de atendimento, site e WhatsApp que podem aparecer nos posts.",
		'seg_2fa'        => '1',
		// Apontamentos (botão "Apontar" para marcar ajustes na tela).
		'apontamentos'   => '1',
		'apontamentos_clientes' => '0',
		'apontamentos_email' => '',
	);
}

/**
 * Padrões do núcleo + identidade do projeto (identity.php).
 */
function lk_defaults_with_identity() {
	static $d = null;
	if ( null === $d ) {
		$id = lk_identity();
		$d  = array_merge(
			lk_default_settings(),
			array_filter(
				array(
					'empresa'        => $id['nome'],
					'mail_from_name' => $id['nome'],
					'site'           => $id['site'],
					'whatsapp'       => $id['whatsapp'],
					'email'          => $id['email'],
					'logo'           => $id['logo'],
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

function lk_settings() {
	$saved = get_option( 'lk_settings', array() );
	return wp_parse_args( is_array( $saved ) ? $saved : array(), lk_defaults_with_identity() );
}

function lk_setting( $key ) {
	$s = lk_settings();
	return isset( $s[ $key ] ) ? $s[ $key ] : '';
}

/**
 * Lista simples: um item por linha.
 */
function lk_list( $text ) {
	return array_values( array_filter( array_map( 'trim', preg_split( '/\r\n|\r|\n/', (string) $text ) ) ) );
}

/**
 * Linhas "Título | extra" viram pares.
 */
function lk_pairs( $text, $cols = 2 ) {
	$out = array();
	foreach ( lk_list( $text ) as $line ) {
		$out[] = array_pad( array_map( 'trim', explode( '|', $line, $cols ) ), $cols, '' );
	}
	return $out;
}

/**
 * Colunas do Kanban de projetos: slug => nome. A última é "entregue".
 */
function lk_columns() {
	$cols = array();
	foreach ( lk_list( lk_setting( 'colunas' ) ) as $name ) {
		$cols[ sanitize_title( $name ) ] = $name;
	}
	return $cols ? $cols : array( 'pago' => 'Pago', 'pronto' => 'Pronto', 'entregue' => 'Entregue' );
}

/**
 * Coluna sugerida para projeto que já estava em andamento: "Em produção", se existir.
 */
function lk_first_column_after_briefing() {
	$keys = array_keys( lk_columns() );
	foreach ( $keys as $k ) {
		if ( false !== strpos( $k, 'produ' ) ) {
			return $k;
		}
	}
	return isset( $keys[1] ) ? $keys[1] : $keys[0];
}

function lk_last_column() {
	$keys = array_keys( lk_columns() );
	return end( $keys );
}

function lk_task_statuses() {
	return array(
		'todo'   => 'A fazer',
		'doing'  => 'Fazendo',
		'review' => 'Revisão',
		'done'   => 'Feito',
	);
}

function lk_priorities() {
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
 * Áreas do painel, em grupos (é assim que aparecem na tela Equipe). Admin vê tudo; os demais só o que estiver marcado.
 * As telas abertas a toda a equipe (chat, modo foco, ranking, apontamentos, perfil, ajuda) não precisam de permissão.
 */
function lk_area_groups() {
	$g = array(
		'Dia a dia'            => array(
			'tarefas'   => 'Tarefas',
			'reunioes'  => 'Reuniões e atas',
			'agenda'    => 'Agenda',
		),
		'Conteúdo e entrega'   => array(
			'conteudo'   => 'Conteúdo, posts e planejamento',
			'relatorios' => 'Relatórios dos clientes',
			'trafego'    => 'Tráfego pago',
			'redes'      => 'Redes conectadas',
		),
		'Clientes'             => array(
			'clientes'    => 'Clientes (fichas)',
			'contratos'   => 'Contratos',
			'formularios' => 'Briefings e pesquisas',
			'mensagens'   => 'Mensagens com clientes',
			'acessos'     => 'Acessos e senhas dos clientes',
			'emails'      => 'E-mails para clientes',
		),
		'Comercial'            => array(
			'leads'      => 'Funil de leads e ideias de conteúdo',
			'prospeccao' => 'Prospecção',
			'orcamentos' => 'Propostas e orçamentos',
		),
		'Financeiro'           => array(
			'financeiro' => 'Financeiro, mensalidades e cobranças',
		),
		'Pedidos e estoque'    => array(
			'projetos' => 'Pedidos e projetos',
			'estoque'  => 'Estoque, insumos e compras',
			'produtos' => 'Produtos e marketplaces',
		),
	);
	// Esconde o que o projeto não usa (módulos desligados).
	if ( ! lk_module( 'estoque' ) ) {
		unset( $g['Pedidos e estoque']['estoque'] );
	}
	if ( ! lk_module( 'produtos' ) ) {
		unset( $g['Pedidos e estoque']['produtos'] );
	}
	return $g;
}

/** Lista simples chave → nome (todas as áreas liberáveis). */
function lk_areas() {
	$out = array();
	foreach ( lk_area_groups() as $items ) {
		$out = array_merge( $out, $items );
	}
	return $out;
}

/** Áreas só do administrador (não aparecem para liberar). */
function lk_admin_only_areas() {
	return array( 'Equipe e permissões', 'Configurações', 'Saúde do sistema', 'Metas e prêmios', 'Gamificação' );
}

/**
 * Funções da equipe (perfis): cada uma já marca as permissões certas. Dá para ajustar depois, pessoa por pessoa.
 * Formato: chave => array( nome, áreas, descrição, aparece no cadastro por link? )
 */
function lk_team_roles() {
	return array(
		'gestor'      => array( 'Gestor / coordenação', array_keys( lk_areas() ), 'Vê quase tudo para coordenar a operação. Só não mexe em Equipe, Configurações e Saúde (esses são do admin).', false ),
		'comercial'   => array( 'Comercial / vendas', array( 'leads', 'prospeccao', 'orcamentos', 'clientes', 'contratos', 'agenda', 'reunioes', 'emails', 'tarefas' ), 'Funil, prospecção, propostas e contratos.', true ),
		'atendimento' => array( 'Atendimento / sucesso do cliente', array( 'conteudo', 'clientes', 'contratos', 'formularios', 'mensagens', 'agenda', 'reunioes', 'relatorios', 'emails', 'leads', 'tarefas' ), 'Conversa com o cliente, briefing, contratos e acompanhamento.', true ),
		'social'      => array( 'Social media', array( 'conteudo', 'clientes', 'redes', 'relatorios', 'agenda', 'reunioes', 'tarefas' ), 'Posts, planejamento, redes conectadas e relatórios.', true ),
		'redator'     => array( 'Redator / copywriter', array( 'conteudo', 'reunioes', 'tarefas' ), 'Textos e roteiros dos posts.', true ),
		'designer'    => array( 'Designer', array( 'conteudo', 'reunioes', 'tarefas' ), 'Artes dos posts e peças. Vê o conteúdo que está com ele.', true ),
		'videomaker'  => array( 'Videomaker / editor de vídeo', array( 'conteudo', 'reunioes', 'tarefas' ), 'Vídeos e Reels.', true ),
		'trafego'     => array( 'Gestor de tráfego', array( 'trafego', 'relatorios', 'clientes', 'redes', 'reunioes', 'tarefas' ), 'Campanhas pagas e relatórios.', true ),
		'financeiro'  => array( 'Financeiro e contratos', array( 'financeiro', 'clientes', 'contratos', 'orcamentos', 'leads', 'reunioes', 'tarefas' ), 'Cobranças, mensalidades, contratos e propostas.', true ),
		'outro'       => array( 'Outra função', array( 'tarefas' ), 'Só tarefas. Marque o resto à mão.', true ),
	);
}

/** Áreas de uma função (só as que existem neste projeto). */
function lk_role_areas( $role ) {
	$r = lk_team_roles();
	return isset( $r[ $role ] ) ? array_values( array_intersect( $r[ $role ][1], array_keys( lk_areas() ) ) ) : array( 'tarefas' );
}

function lk_user_role_key( $user_id ) {
	$f = (string) get_user_meta( $user_id, 'lk_func', true );
	return isset( lk_team_roles()[ $f ] ) ? $f : '';
}

function lk_user_role_label( $user_id ) {
	if ( lk_is_admin( $user_id ) ) {
		return 'Administração';
	}
	$f = lk_user_role_key( $user_id );
	return $f ? lk_team_roles()[ $f ][0] : '';
}

function lk_is_admin( $user_id = 0 ) {
	return user_can( $user_id ? $user_id : get_current_user_id(), 'manage_options' );
}

function lk_is_team( $user_id = 0 ) {
	$user = $user_id ? get_userdata( $user_id ) : wp_get_current_user();
	return $user && $user->exists() && ( lk_is_admin( $user->ID ) || in_array( 'lk_team', (array) $user->roles, true ) );
}

function lk_is_client_user( $user_id = 0 ) {
	$user = $user_id ? get_userdata( $user_id ) : wp_get_current_user();
	return $user && $user->exists() && in_array( 'lk_client', (array) $user->roles, true );
}

/**
 * O usuário da equipe pode ver esta área? Admin vê tudo.
 */
function lk_can( $area, $user_id = 0 ) {
	$user_id = $user_id ? $user_id : get_current_user_id();
	if ( lk_is_admin( $user_id ) ) {
		return true;
	}
	if ( ! lk_is_team( $user_id ) ) {
		return false;
	}
	$perms = (array) get_user_meta( $user_id, 'lk_perms', true );
	return in_array( $area, $perms, true );
}

/**
 * Membro da equipe que só vê as tarefas atribuídas a ele.
 */
function lk_only_own_tasks( $user_id = 0 ) {
	$user_id = $user_id ? $user_id : get_current_user_id();
	return ! lk_is_admin( $user_id ) && (bool) get_user_meta( $user_id, 'lk_only_own', true );
}

/**
 * Escopos da pessoa (além das áreas): ver só as próprias tarefas, só os próprios clientes, só as próprias reuniões.
 */
function lk_only_own_clients( $user_id = 0 ) {
	$user_id = $user_id ? $user_id : get_current_user_id();
	return $user_id && ! lk_is_admin( $user_id ) && (bool) get_user_meta( $user_id, 'lk_only_clients', true );
}

function lk_only_own_meetings( $user_id = 0 ) {
	$user_id = $user_id ? $user_id : get_current_user_id();
	return $user_id && ! lk_is_admin( $user_id ) && (bool) get_user_meta( $user_id, 'lk_only_meet', true );
}

/** Colunas da ficha do cliente que dizem "quem cuida": designer, social, atendimento, tráfego e revisor. */
function lk_client_owner_columns() {
	return array( 'designer_id', 'social_id', 'atendimento_id', 'trafego_id', 'revisor_id' );
}

/** O cliente está na carteira desta pessoa? (Admin e quem não tem o escopo "só meus clientes" vê todos.) */
function lk_client_visible( $client_id, $user_id = 0 ) {
	$user_id = $user_id ? $user_id : get_current_user_id();
	if ( ! lk_only_own_clients( $user_id ) ) {
		return true;
	}
	$c = lk_get( 'clients', (int) $client_id );
	if ( ! $c ) {
		return false;
	}
	foreach ( lk_client_owner_columns() as $col ) {
		if ( (int) $c->$col === (int) $user_id ) {
			return true;
		}
	}
	return false;
}

/** WHERE (sem o "WHERE") que limita a lista de clientes à carteira da pessoa; '' quando não há limite. */
function lk_clients_scope_sql( $alias = '' ) {
	if ( ! is_user_logged_in() || ! lk_only_own_clients() ) {
		return '';
	}
	$u   = (int) get_current_user_id();
	$pre = $alias ? $alias . '.' : '';
	$out = array();
	foreach ( lk_client_owner_columns() as $col ) {
		$out[] = $pre . $col . ' = ' . $u;
	}
	return '(' . implode( ' OR ', $out ) . ')';
}

/**
 * Quem já era da equipe antes das novas áreas continua vendo o que via:
 *  clientes → agenda, contratos, briefings, redes e mensagens · leads → prospecção · todos → reuniões.
 * Roda uma vez por versão.
 */
function lk_perms_migrate() {
	if ( get_option( 'lk_perms_v2' ) ) {
		return;
	}
	foreach ( get_users( array( 'role' => 'lk_team', 'fields' => 'ID' ) ) as $uid ) {
		$perms = (array) get_user_meta( $uid, 'lk_perms', true );
		$add   = array( 'reunioes' );
		if ( in_array( 'clientes', $perms, true ) ) {
			$add = array_merge( $add, array( 'agenda', 'contratos', 'formularios', 'redes', 'mensagens' ) );
		}
		if ( in_array( 'leads', $perms, true ) ) {
			$add[] = 'prospeccao';
		}
		update_user_meta( $uid, 'lk_perms', array_values( array_unique( array_merge( $perms, $add ) ) ) );
	}
	update_option( 'lk_perms_v2', 1, false );
}

function lk_add_roles() {
	// Só leitura: os envios de arquivo passam pelo sistema, que confere o dono do projeto.
	add_role( 'lk_client', 'Cliente (CRM)', array( 'read' => true ) );
	add_role( 'lk_team', 'Equipe (CRM)', array( 'read' => true ) );
}

function lk_team_users() {
	return get_users(
		array(
			'role__in' => array( 'administrator', 'lk_team' ),
			'orderby'  => 'display_name',
		)
	);
}

/* -----------------------------------------------------------------------
 * Endereços
 * -------------------------------------------------------------------- */

function lk_url( $path = '', $args = array() ) {
	$url = home_url( '/' . ltrim( $path, '/' ) );
	if ( $path && '/' !== substr( $url, -1 ) && false === strpos( $path, '?' ) ) {
		$url .= '/';
	}
	return $args ? add_query_arg( $args, $url ) : $url;
}

function lk_panel_url( $section = '', $id = 0, $args = array() ) {
	$path = 'painel' . ( $section ? '/' . $section : '' ) . ( $id ? '/' . (int) $id : '' );
	return lk_url( $path, $args );
}

function lk_client_url( $section = '', $id = 0, $args = array() ) {
	$path = 'cliente' . ( $section ? '/' . $section : '' ) . ( $id ? '/' . (int) $id : '' );
	return lk_url( $path, $args );
}

function lk_invite_url( $token ) {
	return lk_url( 'convite/' . $token );
}

/**
 * Datas de hospedagem e domínio do formulário. Com "same_date" marcado (padrão), a hospedagem
 * vence junto com o domínio; desmarcado, cada uma tem a sua data.
 * Devolve array( host_end, domain_end ).
 */
function lk_linked_dates() {
	$domain = lk_in( 'domain_end', 'date' );
	$host   = lk_in( 'host_end', 'date' );
	if ( lk_in( 'same_date', 'bool' ) && $domain ) {
		$host = $domain;
	}
	return array( $host ? $host : null, $domain ? $domain : null );
}

/**
 * Link de WhatsApp com mensagem pronta.
 */
function lk_wa_link( $phone, $message = '' ) {
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

function lk_money( $value ) {
	return 'R$ ' . number_format( (float) $value, 2, ',', '.' );
}

/**
 * Valor em reais para número: "1.234,56", "4.300", "R$ 1.600" e "1234.56" funcionam.
 */
function lk_parse_money( $value ) {
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

function lk_date( $date, $format = 'd/m/Y' ) {
	if ( ! $date || '0000-00-00' === substr( $date, 0, 10 ) ) {
		return '';
	}
	return date_i18n( $format, strtotime( $date ) );
}

function lk_today() {
	return current_time( 'Y-m-d' );
}

function lk_now() {
	return current_time( 'mysql' );
}

/**
 * Dias até a data (negativo = já passou).
 */
function lk_days_until( $date ) {
	if ( ! $date || '0000-00-00' === substr( $date, 0, 10 ) ) {
		return null;
	}
	$today = new DateTime( lk_today() );
	$then  = new DateTime( substr( $date, 0, 10 ) );
	return (int) $today->diff( $then )->format( '%r%a' );
}

function lk_clean_date( $value ) {
	$value = trim( (string) $value );
	return preg_match( '/^\d{4}-\d{2}-\d{2}$/', $value ) ? $value : null;
}

function lk_initials( $name ) {
	$parts = preg_split( '/\s+/', trim( (string) $name ) );
	$first = $parts ? mb_substr( $parts[0], 0, 1 ) : '';
	$last  = count( $parts ) > 1 ? mb_substr( end( $parts ), 0, 1 ) : '';
	return mb_strtoupper( $first . $last );
}

/* -----------------------------------------------------------------------
 * Senhas guardadas (aba Acessos): criptografadas com a chave do site
 * -------------------------------------------------------------------- */

function lk_crypto_key() {
	return hash( 'sha256', wp_salt( 'secure_auth' ) . 'ldk-crm', true );
}

function lk_encrypt( $plain ) {
	if ( '' === (string) $plain ) {
		return '';
	}
	if ( function_exists( 'sodium_crypto_secretbox' ) ) {
		$nonce = random_bytes( SODIUM_CRYPTO_SECRETBOX_NONCEBYTES );
		return 's:' . base64_encode( $nonce . sodium_crypto_secretbox( $plain, $nonce, lk_crypto_key() ) );
	}
	$iv = random_bytes( 16 );
	return 'o:' . base64_encode( $iv . openssl_encrypt( $plain, 'aes-256-cbc', lk_crypto_key(), OPENSSL_RAW_DATA, $iv ) );
}

function lk_decrypt( $stored ) {
	if ( '' === (string) $stored ) {
		return '';
	}
	$raw = base64_decode( substr( $stored, 2 ) );
	if ( 's:' === substr( $stored, 0, 2 ) && function_exists( 'sodium_crypto_secretbox_open' ) ) {
		$nonce = substr( $raw, 0, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES );
		$plain = sodium_crypto_secretbox_open( substr( $raw, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES ), $nonce, lk_crypto_key() );
		return false === $plain ? '' : $plain;
	}
	if ( 'o:' === substr( $stored, 0, 2 ) ) {
		$plain = openssl_decrypt( substr( $raw, 16 ), 'aes-256-cbc', lk_crypto_key(), OPENSSL_RAW_DATA, substr( $raw, 0, 16 ) );
		return false === $plain ? '' : $plain;
	}
	return '';
}

/* -----------------------------------------------------------------------
 * Avisos entre páginas (depois de salvar um formulário)
 * -------------------------------------------------------------------- */

function lk_flash( $message, $type = 'ok' ) {
	set_transient( 'lk_flash_' . get_current_user_id(), array( $message, $type ), 60 );
}

function lk_take_flash() {
	$key   = 'lk_flash_' . get_current_user_id();
	$flash = get_transient( $key );
	if ( $flash ) {
		delete_transient( $key );
	}
	return $flash;
}

/**
 * Ícones em linha (traço), no estilo do painel.
 */
function lk_icon( $name, $size = 18 ) {
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
		'atualizar' => '<path d="M21 12a9 9 0 1 1-2.64-6.36"/><path d="M21 4v5h-5"/>',
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
		'calendario' => '<rect x="3" y="4" width="18" height="18" rx="2"/><path d="M16 2v4M8 2v4M3 10h18"/>',
		'lista'     => '<path d="M8 6h13M8 12h13M8 18h13M3 6h.01M3 12h.01M3 18h.01"/>',
		'drive'     => '<path d="M8 3h8l6 10-4 7H6l-4-7z"/><path d="M8 3l6 10H2M16 3l-6 10 4 7M22 13H10"/>',
	);
	$d = isset( $paths[ $name ] ) ? $paths[ $name ] : '';
	return '<svg class="ic" width="' . (int) $size . '" height="' . (int) $size . '" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' . $d . '</svg>';
}

/* -----------------------------------------------------------------------
 * Datas por extenso em português (não depende do idioma do WordPress)
 * -------------------------------------------------------------------- */

function lk_month_name( $n, $short = false ) {
	$names = array( 1 => 'janeiro', 'fevereiro', 'março', 'abril', 'maio', 'junho', 'julho', 'agosto', 'setembro', 'outubro', 'novembro', 'dezembro' );
	$name  = isset( $names[ (int) $n ] ) ? $names[ (int) $n ] : '';
	return $short ? mb_substr( $name, 0, 3 ) : $name;
}

/**
 * "sexta-feira, 25 de setembro".
 */
function lk_date_long( $date = '' ) {
	$ts   = $date ? strtotime( $date ) : current_time( 'timestamp' );
	$days = array( 'domingo', 'segunda-feira', 'terça-feira', 'quarta-feira', 'quinta-feira', 'sexta-feira', 'sábado' );
	return $days[ (int) gmdate( 'w', $ts ) ] . ', ' . gmdate( 'j', $ts ) . ' de ' . lk_month_name( gmdate( 'n', $ts ) );
}

/**
 * "setembro de 2026".
 */
function lk_month_label( $ym ) {
	$ts = strtotime( $ym . '-01' );
	return lk_month_name( gmdate( 'n', $ts ) ) . ' de ' . gmdate( 'Y', $ts );
}

/**
 * "há 5 min", "há 2 dias".
 */
function lk_ago( $datetime ) {
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
	return 'em ' . lk_date( $datetime );
}

/**
 * Texto das parcelas no cartão (a regra de juros fica configurada no app da InfinitePay).
 */
function lk_installments_text() {
	$max  = max( 1, (int) lk_setting( 'parcelas_max' ) );
	$free = min( $max, max( 0, (int) lk_setting( 'parcelas_sem_juros' ) ) );
	if ( $max <= 1 ) {
		return 'Pix ou cartão';
	}
	if ( $free >= $max ) {
		return 'Pix ou cartão em até ' . $max . 'x sem juros';
	}
	return 'Pix ou cartão em até ' . $max . 'x' . ( $free > 1 ? ' (até ' . $free . 'x sem juros)' : '' );
}

function lk_qty_label( $qty, $unit ) {
	$q = rtrim( rtrim( number_format( (float) $qty, 2, ',', '.' ), '0' ), ',' );
	return $q . ' ' . $unit;
}

/**
 * Nome a partir do link do Mercado Livre / Shopee (o trecho do endereço com o título).
 */
function lk_link_title( $url ) {
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

<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Configurações gerais (Propostas → Configurações), já mescladas com os padrões.
 */
function ldkp_settings() {
	$saved = get_option( 'ldkp_settings', array() );
	return wp_parse_args( is_array( $saved ) ? $saved : array(), ldkp_default_settings() );
}

function ldkp_setting( $key ) {
	$s = ldkp_settings();
	return isset( $s[ $key ] ) ? $s[ $key ] : '';
}

/**
 * Converte um textarea "Título | descrição" (uma linha por item) em lista de arrays.
 */
function ldkp_lines( $text, $cols = 2 ) {
	$out = array();
	foreach ( preg_split( '/\r\n|\r|\n/', (string) $text ) as $line ) {
		$line = trim( $line );
		if ( '' === $line ) {
			continue;
		}
		$parts = array_map( 'trim', explode( '|', $line, $cols ) );
		$out[] = array_pad( $parts, $cols, '' );
	}
	return $out;
}

/**
 * Lista simples: um item por linha.
 */
function ldkp_list( $text ) {
	return array_values( array_filter( array_map( 'trim', preg_split( '/\r\n|\r|\n/', (string) $text ) ) ) );
}

/**
 * Troca os marcadores {cliente}, {cliente_curto}, {nicho}, {validade}, {responsavel}.
 */
function ldkp_fill( $text, $ctx ) {
	$map = array();
	foreach ( $ctx as $k => $v ) {
		$map[ '{' . $k . '}' ] = $v;
	}
	return strtr( (string) $text, $map );
}

/**
 * Campos da proposta: chave => [tipo, rótulo, ajuda].
 * Tipos: text, textarea, lines (Título | descrição), int, url.
 */
function ldkp_proposal_fields() {
	return array(
		'cliente'           => array( 'text', 'Nome do cliente / empresa', 'Aparece na capa. Ex.: Clínica Sorriso Pleno' ),
		'cliente_curto'     => array( 'text', 'Como chamar o cliente', 'Usado na introdução e nos textos. Ex.: Sorriso Pleno ou Dra. Ana' ),
		'nicho_id'          => array( 'int', 'Nicho', '' ),
		'servicos'          => array( 'ids', 'Serviços', '' ),

		'capa_desc'         => array( 'textarea', 'Texto da capa', 'Resumo da proposta em 2 ou 3 frases.' ),
		'data'              => array( 'text', 'Data', 'Ex.: Setembro 2026' ),
		'validade'          => array( 'text', 'Validade', 'Ex.: 30/09 (aparece como "Até 30/09")' ),
		'responsavel'       => array( 'text', 'Responsável', '' ),
		'capa_img'          => array( 'url', 'Imagem da capa (URL)', 'Vazio = imagem padrão das Configurações.' ),

		'intro_frase'       => array( 'textarea', 'Frase de abertura', 'Vem logo depois do nome do cliente. Ex.: quem cuida de sorrisos não pode ter um site amador.' ),
		'cenario'           => array( 'textarea', '(O cenário)', '' ),
		'oportunidade'      => array( 'textarea', '(A oportunidade)', '' ),

		'diag_titulo'       => array( 'text', 'Título do diagnóstico', 'Ex.: O que o site da {cliente_curto} precisa resolver.' ),
		'diagnostico'       => array( 'lines', 'Pontos do diagnóstico', 'Um por linha: Título | descrição' ),

		'escopo'            => array( 'lines', 'Escopo (o que vamos entregar)', 'Um por linha: Título | descrição' ),
		'portfolio'         => array( 'ids', 'Trabalhos', '' ),
		'perfis'            => array( 'keys', 'Perfis de clientes', '' ),

		'invest_badge'      => array( 'text', 'Selo', 'Ex.: Condição especial · 10 vagas (vazio = sem selo)' ),
		'invest_titulo'     => array( 'text', 'Título do investimento', '' ),
		'invest_nota'       => array( 'textarea', 'Texto do investimento', '' ),
		'vagas_total'       => array( 'int', 'Total de vagas', '0 = esconde o contador de vagas' ),
		'vagas_preenchidas' => array( 'int', 'Vagas preenchidas', '' ),
		'agenda'            => array( 'text', 'Texto ao lado das vagas', 'Ex.: agenda de 15/09 a 15/10' ),
		'pos_nota'          => array( 'textarea', 'Observação abaixo dos planos', '' ),

		'passos'            => array( 'lines', 'Próximos passos', 'Um por linha: Título | descrição' ),

		'contato_titulo'    => array( 'text', 'Título final', 'Ex.: Bora colocar a {cliente_curto} no digital.' ),
		'contato_texto'     => array( 'textarea', 'Texto final', '' ),
	);
}

/**
 * Campos de cada plano de investimento.
 */
function ldkp_plan_fields() {
	return array(
		'ativo'     => array( 'bool', 'Mostrar' ),
		'estilo'    => array( 'select', 'Estilo' ),
		'tag'       => array( 'text', 'Etiqueta', 'Ex.: Entrada rápida, Recomendado, Fase 2' ),
		'nome'      => array( 'text', 'Nome do plano' ),
		'desc'      => array( 'textarea', 'Descrição curta' ),
		'prefixo'   => array( 'text', 'Antes do preço', 'Ex.: R$ ou a partir de R$' ),
		'preco_de'  => array( 'text', 'Preço "de" (riscado)', 'Vazio = sem preço riscado' ),
		'preco_por' => array( 'text', 'Preço final' ),
		'pagamento' => array( 'text', 'Condição / prazo', 'Ex.: Até 10x sem juros · No ar em até 10 dias úteis' ),
		'itens'     => array( 'textarea', 'Itens (um por linha)' ),
		'cta'       => array( 'text', 'Texto do botão' ),
	);
}

function ldkp_plan_styles() {
	return array(
		'normal'  => 'Normal',
		'destaque' => 'Destaque (recomendado)',
		'futuro'  => 'Futuro / fase 2 (tracejado)',
	);
}

/**
 * Lê todos os campos de uma proposta.
 */
function ldkp_get_proposal( $post_id ) {
	$data = array();
	foreach ( ldkp_proposal_fields() as $key => $def ) {
		$value = get_post_meta( $post_id, '_ldkp_' . $key, true );
		if ( 'ids' === $def[0] ) {
			$value = is_array( $value ) ? array_map( 'absint', $value ) : array();
		}
		if ( 'keys' === $def[0] ) {
			$value = is_array( $value ) ? array_values( array_filter( array_map( 'sanitize_key', $value ) ) ) : array();
		}
		$data[ $key ] = $value;
	}
	$planos        = get_post_meta( $post_id, '_ldkp_planos', true );
	$data['planos'] = is_array( $planos ) ? $planos : array();
	return $data;
}

/**
 * Link de WhatsApp com mensagem pronta (ou e-mail, se não houver WhatsApp).
 */
function ldkp_contact_link( $message ) {
	$phone = preg_replace( '/\D+/', '', (string) ldkp_setting( 'whatsapp' ) );
	if ( $phone ) {
		return 'https://wa.me/' . $phone . '?text=' . rawurlencode( $message );
	}
	$email = ldkp_setting( 'email' );
	return $email ? 'mailto:' . $email . '?subject=' . rawurlencode( $message ) : '#';
}

/**
 * Trabalhos cadastrados nas Configurações: Nome | tags | imagem | link | nichos.
 */
function ldkp_portfolio_items() {
	$items = array();
	foreach ( ldkp_lines( ldkp_setting( 'portfolio' ), 5 ) as $i => $row ) {
		$items[ $i ] = array(
			'nome'   => $row[0],
			'tags'   => array_filter( array_map( 'trim', explode( ',', $row[1] ) ) ),
			'img'    => $row[2],
			'link'   => $row[3],
			'nichos' => array_filter( array_map( 'sanitize_title', explode( ',', $row[4] ) ) ),
		);
	}
	return $items;
}

/**
 * Perfis de clientes que podem aparecer em cima dos trabalhos.
 *  - Os clientes do painel com Instagram conectado (chave "c<ID>"): foto, @ e seguidores vêm da conexão.
 *  - Os cadastrados à mão em Configurações → "Perfis de clientes" (chave "m<linha>"):
 *    Nome | @usuario | URL da foto | seguidores | nichos
 */
function ldkp_profiles() {
	$out = array();
	if ( function_exists( 'lk_clients' ) && function_exists( 'lk_social_account' ) ) {
		foreach ( lk_clients() as $c ) {
			$a = lk_social_account( $c->id, 'instagram' );
			if ( ! $a || ! $a->username ) {
				continue;
			}
			$x = json_decode( (string) $a->extra, true );
			$out[ 'c' . $c->id ] = array(
				'nome'       => $c->company ? $c->company : ( $a->name ? $a->name : $c->name ),
				'usuario'    => ltrim( $a->username, '@' ),
				'foto'       => $a->avatar,
				'seguidores' => isset( $x['followers'] ) ? ldkp_short_number( $x['followers'] ) : '',
				'nichos'     => array(),
			);
		}
	}
	foreach ( ldkp_lines( ldkp_setting( 'perfis' ), 5 ) as $i => $row ) {
		if ( '' === trim( $row[1] ) ) {
			continue;
		}
		$out[ 'm' . $i ] = array(
			'nome'       => $row[0] ? $row[0] : ltrim( $row[1], '@' ),
			'usuario'    => ltrim( trim( preg_replace( '#^https?://(www\.)?instagram\.com/#', '', $row[1] ), '/ ' ), '@' ),
			'foto'       => $row[2],
			'seguidores' => $row[3],
			'nichos'     => array_filter( array_map( 'sanitize_title', explode( ',', $row[4] ) ) ),
		);
	}
	return $out;
}

function ldkp_short_number( $n ) {
	$n = (float) $n;
	if ( $n >= 1000000 ) {
		return str_replace( '.', ',', rtrim( rtrim( number_format( $n / 1000000, 1, '.', '' ), '0' ), '.' ) ) . ' mi';
	}
	if ( $n >= 1000 ) {
		return str_replace( '.', ',', rtrim( rtrim( number_format( $n / 1000, 1, '.', '' ), '0' ), '.' ) ) . ' mil';
	}
	return (string) (int) $n;
}

/**
 * Números da agência (Configurações → Números): "+80 | Campanhas mensais".
 * Separa o número do que vem antes e depois, para a contagem animada da capa.
 */
function ldkp_stats() {
	$out = array();
	foreach ( ldkp_lines( ldkp_setting( 'stats' ), 2 ) as $row ) {
		preg_match( '/^(\D*)([\d.,]+)(.*)$/u', $row[0], $m );
		$out[] = array(
			'valor'   => $row[0],
			'legenda' => $row[1],
			'antes'   => $m ? trim( $m[1] ) : '',
			'numero'  => $m ? (float) str_replace( array( '.', ',' ), array( '', '.' ), $m[2] ) : 0,
			'depois'  => $m ? trim( $m[3] ) : '',
		);
	}
	return $out;
}

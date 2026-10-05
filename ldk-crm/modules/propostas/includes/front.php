<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_filter( 'template_include', 'ldkp_template_include', 99 );
function ldkp_template_include( $template ) {
	if ( is_singular( 'ldkp_proposta' ) ) {
		ldkp_count_view( get_queried_object_id() );
		return LDKP_DIR . 'templates/proposta.php';
	}
	return $template;
}

/**
 * Conta visitas do cliente (ignora quem está logado e robôs de pré-visualização de link).
 */
function ldkp_count_view( $post_id ) {
	if ( is_user_logged_in() || is_preview() ) {
		return;
	}
	$ua = isset( $_SERVER['HTTP_USER_AGENT'] ) ? strtolower( sanitize_text_field( wp_unslash( $_SERVER['HTTP_USER_AGENT'] ) ) ) : '';
	if ( '' === $ua || preg_match( '/bot|crawl|spider|preview|facebookexternalhit|whatsapp|telegram|slack|discord|skype|linkedin|headless/', $ua ) ) {
		return;
	}
	update_post_meta( $post_id, '_ldkp_views', (int) get_post_meta( $post_id, '_ldkp_views', true ) + 1 );
	update_post_meta( $post_id, '_ldkp_last_view', current_time( 'mysql' ) );
}

/**
 * Monta tudo o que o template precisa, já com os marcadores trocados.
 */
function ldkp_view_model( $post_id ) {
	$d = ldkp_get_proposal( $post_id );
	$s = ldkp_settings();

	$client = $d['cliente'] ? $d['cliente'] : get_the_title( $post_id );
	$short  = $d['cliente_curto'] ? $d['cliente_curto'] : $client;
	$niche  = $d['nicho_id'] ? get_the_title( $d['nicho_id'] ) : '';

	$ctx = array(
		'cliente'       => $client,
		'cliente_curto' => $short,
		'nicho'         => $niche,
		'validade'      => $d['validade'],
		'responsavel'   => $d['responsavel'] ? $d['responsavel'] : $s['responsavel'],
	);

	$t = function ( $text ) use ( $ctx ) {
		return ldkp_fill( $text, $ctx );
	};

	$rows = function ( $text ) use ( $t ) {
		return array_map(
			function ( $r ) use ( $t ) {
				return array( $t( $r[0] ), $t( $r[1] ) );
			},
			ldkp_lines( $text, 2 )
		);
	};

	// Trabalhos escolhidos (ou os 6 primeiros).
	$all       = ldkp_portfolio_items();
	$portfolio = array();
	foreach ( $d['portfolio'] as $i ) {
		if ( isset( $all[ $i ] ) ) {
			$portfolio[] = $all[ $i ];
		}
	}
	if ( ! $portfolio ) {
		$portfolio = array_slice( array_values( $all ), 0, 6 );
	}
	// Cases do nicho do cliente primeiro (o primeiro também vira a imagem da capa).
	$slug = $d['nicho_id'] ? get_post_field( 'post_name', $d['nicho_id'] ) : '';
	if ( $slug ) {
		usort(
			$portfolio,
			function ( $a, $b ) use ( $slug ) {
				return (int) in_array( $slug, $b['nichos'], true ) - (int) in_array( $slug, $a['nichos'], true );
			}
		);
	}
	$portfolio = array_slice( $portfolio, 0, 6 );

	$cover = $d['capa_img'] ? $d['capa_img'] : $s['capa_img'];
	if ( ! $cover && $portfolio ) {
		$cover = $portfolio[0]['img'];
	}

	$plans = array();
	foreach ( $d['planos'] as $p ) {
		if ( empty( $p['ativo'] ) || empty( $p['nome'] ) ) {
			continue;
		}
		$plans[] = array(
			'estilo'    => $p['estilo'],
			'tag'       => $t( $p['tag'] ),
			'nome'      => $t( $p['nome'] ),
			'desc'      => $t( $p['desc'] ),
			'prefixo'   => $p['prefixo'],
			'preco_de'  => $p['preco_de'],
			'preco_por' => $p['preco_por'],
			'pagamento' => $t( $p['pagamento'] ),
			'itens'     => array_map( $t, ldkp_list( $p['itens'] ) ),
			'cta'       => $p['cta'] ? $t( $p['cta'] ) : 'Quero esta opção',
			'link'      => ldkp_contact_link( 'Olá! Vi a proposta da ' . $short . ' e quero seguir com: ' . $p['nome'] ),
		);
	}

	$passos = $rows( $d['passos'] ? $d['passos'] : $s['passos'] );

	return array(
		'client'      => $client,
		'short'       => $short,
		'capa_desc'   => $t( $d['capa_desc'] ),
		'data'        => $d['data'],
		'validade'    => $d['validade'],
		'responsavel' => $ctx['responsavel'],
		'cover'       => $cover,
		'logo'        => $s['logo'],
		'stats'       => ldkp_stats(),
		'intro_frase' => $t( $d['intro_frase'] ),
		'cenario'     => $t( $d['cenario'] ),
		'oportunidade' => $t( $d['oportunidade'] ),
		'diag_titulo' => $t( $d['diag_titulo'] ? $d['diag_titulo'] : 'O que o projeto da {cliente_curto} precisa resolver.' ),
		'diagnostico' => $rows( $d['diagnostico'] ),
		'escopo'      => $rows( $d['escopo'] ),
		'portfolio'   => $portfolio,
		'perfis'      => ldkp_view_profiles( $d ),
		'metodologia' => $rows( $s['metodologia'] ),
		'diferenciais' => $rows( $s['diferenciais'] ),
		'agencia'     => array(
			'img'       => $s['sobre_img'],
			'titulo'    => $s['sobre_titulo'],
			'paragrafos' => array_values( array_filter( array_map( 'trim', preg_split( '/\n\s*\n/', (string) $s['sobre_texto'] ) ) ) ),
			'servicos'  => ldkp_list( $s['agencia_servicos'] ),
		),
		'clientes'    => array_map(
			function ( $r ) {
				return array(
					'nome'   => $r[0],
					'logo'   => $r[1],
					'escuro' => 'escuro' === strtolower( $r[2] ),
				);
			},
			array_filter(
				ldkp_lines( $s['clientes'], 3 ),
				function ( $r ) {
					return '' !== $r[1];
				}
			)
		),
		'clientes_texto' => $t( $s['clientes_texto'] ),
		'depoimentos' => array_slice( $rows( $s['depoimentos'] ), 0, 3 ),
		'invest'      => array(
			'badge'  => $t( $d['invest_badge'] ),
			'titulo' => $t( $d['invest_titulo'] ? $d['invest_titulo'] : $s['invest_titulo'] ),
			'nota'   => $t( $d['invest_nota'] ),
			'total'  => (int) $d['vagas_total'],
			'filled' => min( (int) $d['vagas_preenchidas'], (int) $d['vagas_total'] ),
			'agenda' => $t( $d['agenda'] ),
			'pos'    => $t( $d['pos_nota'] ),
		),
		'plans'       => $plans,
		'passos'      => $passos,
		'contato'     => array(
			'titulo' => $t( $d['contato_titulo'] ? $d['contato_titulo'] : 'Vamos começar?' ),
			'texto'  => $t( $d['contato_texto'] ),
			'link'   => ldkp_contact_link( 'Olá! Vi a proposta da ' . $short . ' e quero começar.' ),
		),
		'contacts'    => array(
			'WhatsApp'  => $s['whatsapp'],
			'E-mail'    => $s['email'],
			'Instagram' => $s['instagram'],
		),
	);
}

/**
 * Perfis de clientes escolhidos na proposta (na ordem da lista).
 */
function ldkp_view_profiles( $d ) {
	$all = ldkp_profiles();
	$out = array();
	foreach ( (array) $d['perfis'] as $k ) {
		if ( isset( $all[ $k ] ) ) {
			$pf         = $all[ $k ];
			$pf['link'] = 'https://www.instagram.com/' . rawurlencode( $pf['usuario'] ) . '/';
			$pf['ini']  = mb_strtoupper( mb_substr( $pf['nome'], 0, 1 ) );
			$out[]      = $pf;
		}
	}
	return $out;
}

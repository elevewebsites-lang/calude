<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function ldk_site_activate() {
	ldk_site_install( false );
}

function ldk_site_uid() {
	return substr( md5( uniqid( '', true ) ), 0, 7 );
}

/** Uma página = seções de largura total, cada uma com um widget LDK. */
function ldk_site_elementor_data( $widgets ) {
	$zero = array( 'unit' => 'px', 'top' => '0', 'right' => '0', 'bottom' => '0', 'left' => '0', 'isLinked' => true );
	$out  = array();
	foreach ( $widgets as $w ) {
		$key = is_array( $w ) ? $w[0] : $w;
		$set = is_array( $w ) ? $w[1] : array();
		$out[] = array(
			'id'       => ldk_site_uid(),
			'elType'   => 'section',
			'settings' => array( 'layout' => 'full_width', 'gap' => 'no', 'padding' => $zero, 'margin' => $zero ),
			'elements' => array(
				array(
					'id'       => ldk_site_uid(),
					'elType'   => 'column',
					'settings' => array( '_column_size' => 100, 'padding' => $zero ),
					'elements' => array(
						array( 'id' => ldk_site_uid(), 'elType' => 'widget', 'widgetType' => 'ldk-' . $key, 'settings' => $set, 'elements' => array() ),
					),
				),
			),
			'isInner'  => false,
		);
	}
	return $out;
}

function ldk_site_ph( $eyebrow, $title, $text, $stock ) {
	return array( 'pagehead', array( 'eyebrow' => $eyebrow, 'title' => $title, 'text' => $text, 'image' => array( 'url' => ldk_site_stock( $stock, 1800 ), 'id' => '' ) ) );
}

function ldk_site_pages_def() {
	return array(
		'inicio'            => array( 'Início', array( 'hero', 'quick', 'marquee', 'services', 'stats', 'panel', 'steps', 'clients', 'feed', 'testimonials', 'cta' ), array() ),
		'servicos'          => array( 'Serviços', array( ldk_site_ph( 'Serviços', 'Tudo para a sua marca *crescer*', 'Estratégia, criação e execução em uma equipe só, com acompanhamento transparente pelo painel do cliente.', 'servicos' ), 'services', 'steps', 'faq', 'cta' ), array() ),
		'painel-do-cliente' => array( 'Painel do cliente', array( ldk_site_ph( 'Painel do cliente', 'Transparência que você *enxerga*', 'Todo cliente LDK tem uma área exclusiva para aprovar, acompanhar e decidir com clareza.', 'painel' ), 'panel', 'stats', 'faq', 'cta' ), array() ),
		'sobre-nos'         => array( 'Sobre nós', array( ldk_site_ph( 'Sobre nós', 'Conheça a *LDK*', 'Uma equipe de Taubaté que cuida da sua marca de ponta a ponta.', 'sobre' ), 'about', 'stats', 'clients', 'testimonials', 'cta' ), array() ),
		'blog'              => array( 'Blog', array( ldk_site_ph( 'Blog', 'Conteúdo para quem quer *crescer de verdade*', 'Dicas práticas de marketing digital, redes sociais e tráfego pago.', 'blog' ), 'posts', 'cta' ), array() ),
		'contato'           => array( 'Contato', array( ldk_site_ph( 'Contato', 'Vamos conversar sobre o *seu projeto*?', 'Fale com a equipe pelo formulário ou direto no WhatsApp.', 'contato' ), 'contact', 'faq' ), array() ),
		'diagnostico'       => array( 'Diagnóstico de perfil', array( ldk_site_ph( 'Diagnóstico gratuito de perfil', 'Seu perfil está *perdendo clientes* todos os dias?', 'Em menos de 2 minutos você descobre o estágio do seu perfil e o que fazer primeiro para transformar seguidores em contatos.', 'diag' ), 'diag', 'pains', 'steps', 'testimonials', 'faq' ), array( '_ldk_site_lp' => 1 ) ),
		'links'             => array( 'Links', array( 'links' ), array( '_ldk_site_bare' => 1 ) ),
	);
}

/** Troca o layout das páginas do plugin pelo da versão atual (guarda cópia do que havia). */
function ldk_site_relayout() {
	$ids = get_option( 'ldk_site_pages', array() );
	$defs = ldk_site_pages_def();
	foreach ( $defs as $slug => $def ) {
		if ( empty( $ids[ $slug ] ) || ! get_post( (int) $ids[ $slug ] ) ) {
			continue;
		}
		$id  = (int) $ids[ $slug ];
		$old = get_post_meta( $id, '_elementor_data', true );
		if ( $old ) {
			update_post_meta( $id, '_ldk_site_prev_data', $old );
		}
		$html = '';
		foreach ( $def[1] as $w ) {
			$html .= ldk_site_render( is_array( $w ) ? $w[0] : $w, is_array( $w ) ? $w[1] : array() );
		}
		wp_update_post( array( 'ID' => $id, 'post_content' => $html ) );
		update_post_meta( $id, '_elementor_data', wp_slash( wp_json_encode( ldk_site_elementor_data( $def[1] ) ) ) );
		update_post_meta( $id, '_elementor_edit_mode', 'builder' );
	}
	if ( class_exists( '\\Elementor\\Plugin' ) ) {
		try {
			\Elementor\Plugin::$instance->files_manager->clear_cache();
		} catch ( \Throwable $e ) { // phpcs:ignore
			unset( $e );
		}
	}
}

add_action(
	'init',
	function () {
		if ( ! is_admin() && ! wp_doing_cron() ) {
			return;
		}
		if ( get_option( 'ldk_site_layout' ) === LDK_SITE_VERSION ) {
			return;
		}
		update_option( 'ldk_site_layout', LDK_SITE_VERSION );
		if ( get_option( 'ldk_site_pages' ) ) {
			ldk_site_relayout();
			if ( ! wp_next_scheduled( 'ldk_site_sideload' ) ) {
				wp_schedule_single_event( time() + 20, 'ldk_site_sideload' );
			}
		}
	},
	30
);

/** Baixa as imagens da internet para a biblioteca de mídia e define como imagem destacada dos artigos que não têm. */
function ldk_site_sideload_posts() {
	require_once ABSPATH . 'wp-admin/includes/file.php';
	require_once ABSPATH . 'wp-admin/includes/media.php';
	require_once ABSPATH . 'wp-admin/includes/image.php';
	$posts = get_posts( array( 'numberposts' => 30, 'post_status' => 'publish', 'meta_query' => array( array( 'key' => '_thumbnail_id', 'compare' => 'NOT EXISTS' ) ) ) ); // phpcs:ignore
	$n     = 0;
	foreach ( $posts as $i => $p ) {
		$tmp = download_url( ldk_site_post_fallback( $i, 1400 ), 25 );
		if ( is_wp_error( $tmp ) ) {
			continue;
		}
		$file = array( 'name' => sanitize_title( $p->post_title ) . '.jpg', 'tmp_name' => $tmp );
		$aid  = media_handle_sideload( $file, $p->ID, $p->post_title );
		if ( is_wp_error( $aid ) ) {
			@unlink( $tmp ); // phpcs:ignore
			continue;
		}
		set_post_thumbnail( $p->ID, $aid );
		++$n;
	}
	return $n;
}
add_action( 'ldk_site_sideload', 'ldk_site_sideload_posts' );

function ldk_site_install( $force = false ) {
	$ids = get_option( 'ldk_site_pages', array() );
	foreach ( ldk_site_pages_def() as $slug => $def ) {
		$id = isset( $ids[ $slug ] ) ? (int) $ids[ $slug ] : 0;
		if ( $id && get_post( $id ) && 'trash' !== get_post_status( $id ) ) {
			continue;
		}
		$exist = get_page_by_path( $slug );
		if ( $exist ) {
			$ids[ $slug ] = (int) $exist->ID;
			update_post_meta( $exist->ID, '_ldk_site_page', 1 );
			continue;
		}
		$html = '';
		foreach ( $def[1] as $w ) {
			$html .= ldk_site_render( is_array( $w ) ? $w[0] : $w, is_array( $w ) ? $w[1] : array() );
		}
		$id = wp_insert_post(
			array(
				'post_type'    => 'page',
				'post_status'  => 'publish',
				'post_title'   => $def[0],
				'post_name'    => $slug,
				'post_content' => $html,
			)
		);
		if ( ! $id || is_wp_error( $id ) ) {
			continue;
		}
		$ids[ $slug ] = (int) $id;
		update_post_meta( $id, '_ldk_site_page', 1 );
		foreach ( $def[2] as $mk => $mv ) {
			update_post_meta( $id, $mk, $mv );
		}
		update_post_meta( $id, '_elementor_edit_mode', 'builder' );
		update_post_meta( $id, '_elementor_template_type', 'wp-page' );
		update_post_meta( $id, '_elementor_version', '3.0.0' );
		update_post_meta( $id, '_wp_page_template', 'elementor_canvas' );
		update_post_meta( $id, '_elementor_data', wp_slash( wp_json_encode( ldk_site_elementor_data( $def[1] ) ) ) );
	}
	update_option( 'ldk_site_pages', $ids );

	if ( ! empty( $ids['inicio'] ) && ( $force || 'page' !== get_option( 'show_on_front' ) ) ) {
		update_option( 'show_on_front', 'page' );
		update_option( 'page_on_front', (int) $ids['inicio'] );
	}
	ldk_site_install_menu( $ids );
	ldk_site_install_posts();
	if ( ! get_option( 'ldk_site' ) ) {
		add_option( 'ldk_site', array() );
	}
	flush_rewrite_rules( false );
}

function ldk_site_install_menu( $ids ) {
	$name = 'LDK Principal';
	$menu = wp_get_nav_menu_object( $name );
	if ( $menu ) {
		$locs            = get_theme_mod( 'nav_menu_locations', array() );
		$locs['ldk-main'] = $menu->term_id;
		set_theme_mod( 'nav_menu_locations', $locs );
		return;
	}
	$mid = wp_create_nav_menu( $name );
	if ( is_wp_error( $mid ) ) {
		return;
	}
	foreach ( array( 'inicio' => 'Início', 'servicos' => 'Serviços', 'painel-do-cliente' => 'Painel do cliente', 'sobre-nos' => 'Sobre nós', 'blog' => 'Blog', 'contato' => 'Contato' ) as $slug => $label ) {
		if ( empty( $ids[ $slug ] ) ) {
			continue;
		}
		wp_update_nav_menu_item(
			$mid,
			0,
			array(
				'menu-item-title'     => $label,
				'menu-item-object'    => 'page',
				'menu-item-object-id' => (int) $ids[ $slug ],
				'menu-item-type'      => 'post_type',
				'menu-item-status'    => 'publish',
			)
		);
	}
	$locs             = get_theme_mod( 'nav_menu_locations', array() );
	$locs['ldk-main'] = $mid;
	set_theme_mod( 'nav_menu_locations', $locs );
}

function ldk_site_sample_posts() {
	return array(
		array(
			'Por que seu perfil posta todo dia e não vende',
			'Postar com frequência não é o mesmo que ter estratégia. Veja os três ajustes que mais mudam o resultado de um perfil empresarial.',
			"<p>Muita empresa chega até nós postando todos os dias e, mesmo assim, sem receber contatos. O problema quase nunca é a frequência: é a falta de direção.</p><h2>1. Defina o que cada post precisa fazer</h2><p>Todo conteúdo deve ter um objetivo: atrair, educar, gerar confiança ou convidar para a conversa. Um calendário em que cada publicação tem função evita o improviso.</p><h2>2. Fale com o cliente certo</h2><p>Seguidor não é cliente. Conhecer quem compra de você, as dúvidas dele e o momento de decisão muda a forma de escrever e de mostrar o serviço.</p><h2>3. Meça e ajuste todo mês</h2><p>Sem olhar alcance, cliques e contatos, você repete o que não funciona. Um relatório simples, revisado todo mês, mostra o que manter e o que cortar.</p><p>Quer saber em que ponto o seu perfil está? Faça o diagnóstico gratuito da LDK.</p>",
		),
		array(
			'Tráfego pago: por onde começar sem desperdiçar verba',
			'Antes de investir em anúncios, a estrutura precisa estar pronta. Confira o que verificar para o dinheiro render.',
			"<p>Anúncios amplificam o que já existe. Se o perfil, a página ou o atendimento não estão preparados, o investimento escorre.</p><h2>Tenha um destino claro</h2><p>Defina para onde o clique vai: WhatsApp, landing page ou site. Cada campanha deve levar a uma única ação.</p><h2>Comece pequeno e teste</h2><p>Teste públicos e criativos com verba controlada, compare e só então aumente o investimento no que trouxe contatos de qualidade.</p><h2>Acompanhe o contato até a venda</h2><p>Clique barato não é resultado. O que importa é quantos contatos viram conversas e quantas conversas viram clientes. Treinar quem atende também faz parte da estratégia.</p>",
		),
		array(
			'Identidade visual: o que sua marca comunica antes de falar',
			'Cores, fontes e estilo constroem confiança em segundos. Entenda por que o branding vem antes das campanhas.',
			"<p>O cliente forma uma opinião sobre a sua empresa em poucos segundos, e boa parte dela vem do visual.</p><h2>Consistência gera lembrança</h2><p>Quando cores, fontes e estilo se repetem em redes sociais, site e materiais, a marca fica reconhecível e passa profissionalismo.</p><h2>Visual combina com posicionamento</h2><p>Uma identidade bonita, mas desalinhada do público e do preço praticado, atrapalha. O branding conecta o que a marca é com o que ela mostra.</p><h2>Comece pelo essencial</h2><p>Logo, paleta, tipografia e um padrão de posts já organizam a comunicação. O resto evolui com o tempo.</p>",
		),
	);
}

function ldk_site_install_posts() {
	if ( get_option( 'ldk_site_posts_done' ) ) {
		return;
	}
	foreach ( ldk_site_sample_posts() as $p ) {
		if ( get_page_by_title( $p[0], OBJECT, 'post' ) ) {
			continue;
		}
		wp_insert_post(
			array(
				'post_type'    => 'post',
				'post_status'  => 'publish',
				'post_title'   => $p[0],
				'post_excerpt' => $p[1],
				'post_content' => $p[2],
			)
		);
	}
	update_option( 'ldk_site_posts_done', 1 );
}

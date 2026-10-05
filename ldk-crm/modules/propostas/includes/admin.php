<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/* -----------------------------------------------------------------------
 * Assets
 * -------------------------------------------------------------------- */

add_action( 'admin_enqueue_scripts', 'ldkp_admin_assets' );
function ldkp_admin_assets( $hook ) {
	$screen = get_current_screen();
	if ( ! $screen || ! in_array( $screen->post_type, array( 'ldkp_proposta', 'ldkp_nicho', 'ldkp_servico' ), true ) ) {
		return;
	}
	wp_enqueue_style( 'ldkp-admin', LDKP_URL . 'assets/admin.css', array(), LDKP_VERSION );
	wp_enqueue_script( 'ldkp-admin', LDKP_URL . 'assets/admin.js', array(), LDKP_VERSION, true );

	if ( 'ldkp_proposta' === $screen->post_type && in_array( $hook, array( 'post.php', 'post-new.php' ), true ) ) {
		wp_add_inline_script( 'ldkp-admin', 'window.LDKP_DATA = ' . wp_json_encode( ldkp_admin_data() ) . ';', 'before' );
	}
}

/**
 * Dados que o botão "Preencher automaticamente" usa.
 */
function ldkp_admin_data() {
	$niches = array();
	foreach ( get_posts( array( 'post_type' => 'ldkp_nicho', 'numberposts' => -1, 'orderby' => 'menu_order title', 'order' => 'ASC' ) ) as $p ) {
		$row = array( 'slug' => $p->post_name, 'title' => $p->post_title );
		foreach ( ldkp_niche_fields() as $key => $def ) {
			$row[ $key ] = (string) get_post_meta( $p->ID, '_ldkp_' . $key, true );
		}
		$niches[ $p->ID ] = $row;
	}

	$services = array();
	foreach ( get_posts( array( 'post_type' => 'ldkp_servico', 'numberposts' => -1, 'orderby' => 'menu_order title', 'order' => 'ASC' ) ) as $p ) {
		$row = array( 'title' => $p->post_title );
		foreach ( ldkp_service_fields() as $key => $def ) {
			$row[ $key ] = (string) get_post_meta( $p->ID, '_ldkp_' . $key, true );
		}
		$services[ $p->ID ] = $row;
	}

	$s      = ldkp_settings();
	$months = array( 'Janeiro', 'Fevereiro', 'Março', 'Abril', 'Maio', 'Junho', 'Julho', 'Agosto', 'Setembro', 'Outubro', 'Novembro', 'Dezembro' );
	$now    = current_time( 'timestamp' );

	return array(
		'niches'    => $niches,
		'services'  => $services,
		'portfolio' => array_map(
			function ( $item ) {
				return $item['nichos'];
			},
			ldkp_portfolio_items()
		),
		'defaults'  => array(
			'data'          => $months[ (int) gmdate( 'n', $now ) - 1 ] . ' ' . gmdate( 'Y', $now ),
			'validade'      => gmdate( 'd/m', $now + 7 * DAY_IN_SECONDS ),
			'responsavel'   => $s['responsavel'],
			'passos'        => $s['passos'],
			'invest_badge'  => $s['invest_badge'],
			'invest_titulo' => $s['invest_titulo'],
			'invest_nota'   => $s['invest_nota'],
			'pos_nota'      => $s['pos_nota'],
		),
	);
}

/* -----------------------------------------------------------------------
 * Campos genéricos
 * -------------------------------------------------------------------- */

function ldkp_field( $name, $type, $label, $value, $help = '', $extra = '' ) {
	$id = 'ldkp_' . sanitize_key( str_replace( array( '[', ']' ), '_', $name ) );
	echo '<div class="ldkp-field ldkp-field--' . esc_attr( $type ) . '">';
	if ( 'bool' === $type ) {
		echo '<label class="ldkp-check"><input type="checkbox" name="' . esc_attr( $name ) . '" value="1" data-key="' . esc_attr( $extra ) . '" ' . checked( ! empty( $value ), true, false ) . '> ' . esc_html( $label ) . '</label></div>';
		return;
	}
	echo '<label for="' . esc_attr( $id ) . '">' . esc_html( $label ) . '</label>';
	$data = $extra ? ' data-key="' . esc_attr( $extra ) . '"' : '';
	if ( in_array( $type, array( 'textarea', 'lines' ), true ) ) {
		$rows = 'lines' === $type ? 5 : 3;
		echo '<textarea id="' . esc_attr( $id ) . '" name="' . esc_attr( $name ) . '" rows="' . (int) $rows . '"' . $data . '>' . esc_textarea( $value ) . '</textarea>';
	} else {
		$input_type = 'int' === $type ? 'number' : ( 'url' === $type ? 'url' : 'text' );
		echo '<input type="' . esc_attr( $input_type ) . '" id="' . esc_attr( $id ) . '" name="' . esc_attr( $name ) . '" value="' . esc_attr( $value ) . '"' . $data . ( 'int' === $type ? ' min="0"' : '' ) . '>';
	}
	if ( $help ) {
		echo '<p class="ldkp-help">' . esc_html( $help ) . '</p>';
	}
	echo '</div>';
}

function ldkp_sanitize( $type, $value ) {
	switch ( $type ) {
		case 'int':
			return absint( $value );
		case 'url':
			return esc_url_raw( trim( $value ) );
		case 'bool':
			return empty( $value ) ? 0 : 1;
		case 'textarea':
		case 'lines':
			return sanitize_textarea_field( $value );
		case 'ids':
			return array_values( array_unique( array_map( 'absint', (array) $value ) ) );
		case 'keys':
			return array_values( array_unique( array_filter( array_map( 'sanitize_key', (array) $value ) ) ) );
		default:
			return sanitize_text_field( $value );
	}
}

/* -----------------------------------------------------------------------
 * Tela da proposta
 * -------------------------------------------------------------------- */

add_action( 'add_meta_boxes_ldkp_proposta', 'ldkp_proposal_boxes' );
function ldkp_proposal_boxes() {
	add_meta_box( 'ldkp_builder', 'Montar proposta', 'ldkp_render_builder', 'ldkp_proposta', 'normal', 'high' );
	add_meta_box( 'ldkp_link', 'Link para o cliente', 'ldkp_render_link_box', 'ldkp_proposta', 'side', 'high' );
}

function ldkp_section_open( $num, $title, $desc = '', $open = true ) {
	echo '<details class="ldkp-section"' . ( $open ? ' open' : '' ) . '><summary><span class="ldkp-num">' . esc_html( $num ) . '</span><span class="ldkp-title">' . esc_html( $title ) . '</span>';
	if ( $desc ) {
		echo '<span class="ldkp-desc">' . esc_html( $desc ) . '</span>';
	}
	echo '</summary><div class="ldkp-section-body">';
}

function ldkp_section_close() {
	echo '</div></details>';
}

function ldkp_render_builder( $post ) {
	wp_nonce_field( 'ldkp_save_proposal', 'ldkp_nonce' );
	$d      = ldkp_get_proposal( $post->ID );
	$f      = ldkp_proposal_fields();
	$is_new = 'auto-draft' === $post->post_status;

	$put = function ( $key ) use ( $d, $f ) {
		ldkp_field( 'ep[' . $key . ']', $f[ $key ][0], $f[ $key ][1], $d[ $key ], $f[ $key ][2], $key );
	};

	echo '<div class="ldkp-builder">';
	echo '<p class="ldkp-tip">Use <code>{cliente}</code>, <code>{cliente_curto}</code>, <code>{validade}</code> e <code>{nicho}</code> em qualquer texto: eles são trocados automaticamente na página.</p>';

	// 1. Cliente, nicho e serviços.
	ldkp_section_open( '01', 'Cliente, nicho e serviços', 'Comece por aqui' );
	echo '<div class="ldkp-grid-2">';
	$put( 'cliente' );
	$put( 'cliente_curto' );
	echo '</div>';

	echo '<div class="ldkp-field"><label for="ldkp_nicho_id">Nicho</label><select id="ldkp_nicho_id" name="ep[nicho_id]" data-key="nicho_id"><option value="">Selecione…</option>';
	foreach ( get_posts( array( 'post_type' => 'ldkp_nicho', 'numberposts' => -1, 'orderby' => 'menu_order title', 'order' => 'ASC' ) ) as $n ) {
		echo '<option value="' . (int) $n->ID . '" ' . selected( (int) $d['nicho_id'], $n->ID, false ) . '>' . esc_html( $n->post_title ) . '</option>';
	}
	echo '</select><p class="ldkp-help">Novo nicho? Cadastre em Serviços das propostas → Nichos.</p></div>';

	echo '<div class="ldkp-field"><label>Serviços desta proposta</label><div class="ldkp-cards">';
	foreach ( get_posts( array( 'post_type' => 'ldkp_servico', 'numberposts' => -1, 'orderby' => 'menu_order title', 'order' => 'ASC' ) ) as $s ) {
		$price = trim( get_post_meta( $s->ID, '_ldkp_prefixo', true ) . ' ' . get_post_meta( $s->ID, '_ldkp_preco_por', true ) );
		echo '<label class="ldkp-card"><input type="checkbox" name="ep[servicos][]" value="' . (int) $s->ID . '" ' . checked( in_array( $s->ID, $d['servicos'], true ), true, false ) . '><span class="ldkp-card-body"><strong>' . esc_html( $s->post_title ) . '</strong><small>' . esc_html( $price ) . '</small></span></label>';
	}
	echo '</div><p class="ldkp-help">Até 3 serviços viram planos na seção de investimento, na ordem em que aparecem aqui.</p></div>';

	echo '<div class="ldkp-autofill"><button type="button" class="button button-primary button-hero" id="ldkp-autofill">Preencher textos automaticamente</button><span>Puxa os textos do nicho e dos serviços marcados. Depois é só revisar e personalizar.</span></div>';
	ldkp_section_close();

	// 2. Capa.
	ldkp_section_open( '02', 'Capa', 'Primeira tela', ! $is_new );
	$put( 'capa_desc' );
	echo '<div class="ldkp-grid-3">';
	$put( 'data' );
	$put( 'validade' );
	$put( 'responsavel' );
	echo '</div>';
	$put( 'capa_img' );
	ldkp_section_close();

	// 3. Introdução.
	ldkp_section_open( '03', 'Introdução', 'O cenário e a oportunidade do cliente', ! $is_new );
	$put( 'intro_frase' );
	echo '<div class="ldkp-grid-2">';
	$put( 'cenario' );
	$put( 'oportunidade' );
	echo '</div>';
	ldkp_section_close();

	// 4. Diagnóstico.
	ldkp_section_open( '04', 'Diagnóstico', 'O que o projeto precisa resolver', ! $is_new );
	$put( 'diag_titulo' );
	$put( 'diagnostico' );
	ldkp_section_close();

	// 5. Escopo.
	ldkp_section_open( '05', 'Escopo', 'O que vamos entregar', ! $is_new );
	$put( 'escopo' );
	ldkp_section_close();

	// 6. Trabalhos (perfis de clientes em cima + criativos).
	ldkp_section_open( '06', 'Trabalhos', 'Perfis de clientes e até 6 criativos', ! $is_new );
	$profiles = ldkp_profiles();
	echo '<div class="ldkp-field"><label>Perfis de clientes (aparecem em cima dos criativos, com link para o Instagram)</label>';
	if ( $profiles ) {
		echo '<div class="ldkp-profiles">';
		foreach ( $profiles as $key => $pf ) {
			echo '<label class="ldkp-prof"><input type="checkbox" name="ep[perfis][]" value="' . esc_attr( $key ) . '" data-nichos="' . esc_attr( implode( ',', $pf['nichos'] ) ) . '" ' . checked( in_array( $key, $d['perfis'], true ), true, false ) . '>';
			echo '<span class="ldkp-prof-av">' . ( $pf['foto'] ? '<img src="' . esc_url( $pf['foto'] ) . '" alt="" loading="lazy" onerror="this.remove()">' : '' ) . esc_html( mb_strtoupper( mb_substr( $pf['nome'], 0, 1 ) ) ) . '</span>';
			echo '<span class="ldkp-prof-t"><strong>' . esc_html( $pf['nome'] ) . '</strong><small>@' . esc_html( $pf['usuario'] ) . ( $pf['seguidores'] ? ' · ' . esc_html( $pf['seguidores'] ) . ' seguidores' : '' ) . '</small></span></label>';
		}
		echo '</div><p class="ldkp-help">Os clientes com Instagram conectado no painel aparecem aqui sozinhos. Outros perfis: Serviços das propostas → Textos padrão, marca e trabalhos → "Perfis de clientes".</p>';
	} else {
		echo '<p class="ldkp-help">Nenhum perfil ainda. Conecte o Instagram dos clientes no painel ou cadastre em Serviços das propostas → Textos padrão, marca e trabalhos → "Perfis de clientes".</p>';
	}
	echo '</div><label class="ldkp-sub">Criativos (até 6)</label>';
	echo '<div class="ldkp-portfolio">';
	foreach ( ldkp_portfolio_items() as $i => $item ) {
		echo '<label class="ldkp-pf"><input type="checkbox" name="ep[portfolio][]" value="' . (int) $i . '" ' . checked( in_array( $i, $d['portfolio'], true ), true, false ) . '>';
		if ( $item['img'] ) {
			echo '<img src="' . esc_url( $item['img'] ) . '" alt="" loading="lazy">';
		}
		echo '<span>' . esc_html( $item['nome'] ) . '</span></label>';
	}
	echo '</div><p class="ldkp-help">A lista de criativos fica em Serviços das propostas → Textos padrão, marca e trabalhos.</p>';
	ldkp_section_close();

	// 7. Investimento.
	ldkp_section_open( '07', 'Investimento', 'Planos e condição', ! $is_new );
	echo '<div class="ldkp-grid-2">';
	$put( 'invest_badge' );
	$put( 'invest_titulo' );
	echo '</div>';
	$put( 'invest_nota' );
	echo '<div class="ldkp-grid-3">';
	$put( 'vagas_total' );
	$put( 'vagas_preenchidas' );
	$put( 'agenda' );
	echo '</div>';

	echo '<div class="ldkp-plans">';
	for ( $i = 0; $i < 3; $i++ ) {
		$plan = isset( $d['planos'][ $i ] ) && is_array( $d['planos'][ $i ] ) ? $d['planos'][ $i ] : array();
		echo '<div class="ldkp-plan" data-slot="' . (int) $i . '"><h4>Plano ' . (int) ( $i + 1 ) . '</h4>';
		foreach ( ldkp_plan_fields() as $key => $def ) {
			$name  = 'ldkp_planos[' . $i . '][' . $key . ']';
			$value = isset( $plan[ $key ] ) ? $plan[ $key ] : '';
			if ( 'select' === $def[0] ) {
				echo '<div class="ldkp-field"><label>' . esc_html( $def[1] ) . '</label><select name="' . esc_attr( $name ) . '" data-key="' . esc_attr( $key ) . '">';
				foreach ( ldkp_plan_styles() as $sk => $sl ) {
					echo '<option value="' . esc_attr( $sk ) . '" ' . selected( $value ? $value : 'normal', $sk, false ) . '>' . esc_html( $sl ) . '</option>';
				}
				echo '</select></div>';
				continue;
			}
			ldkp_field( $name, $def[0], $def[1], $value, isset( $def[2] ) ? $def[2] : '', $key );
		}
		echo '</div>';
	}
	echo '</div>';
	$put( 'pos_nota' );
	ldkp_section_close();

	// 8. Próximos passos e encerramento.
	ldkp_section_open( '08', 'Próximos passos e encerramento', 'Últimas telas', ! $is_new );
	$put( 'passos' );
	$put( 'contato_titulo' );
	$put( 'contato_texto' );
	ldkp_section_close();

	echo '</div>';
}

function ldkp_render_link_box( $post ) {
	if ( 'publish' !== $post->post_status ) {
		echo '<p class="ldkp-help">Clique em <strong>Publicar</strong> para gerar o link.</p>';
		return;
	}
	$url   = get_permalink( $post );
	$views = (int) get_post_meta( $post->ID, '_ldkp_views', true );
	$last  = get_post_meta( $post->ID, '_ldkp_last_view', true );

	echo '<div class="ldkp-linkbox"><input type="text" readonly value="' . esc_attr( $url ) . '" onclick="this.select()">';
	echo '<div class="ldkp-linkbox-actions"><button type="button" class="button button-primary ldkp-copy" data-url="' . esc_attr( $url ) . '">Copiar link</button> <a class="button" href="' . esc_url( $url ) . '" target="_blank" rel="noopener">Abrir</a></div>';
	echo '<p class="ldkp-views"><strong>' . (int) $views . '</strong> ' . ( 1 === $views ? 'visualização' : 'visualizações' );
	if ( $last ) {
		echo '<br><span>Última: ' . esc_html( mysql2date( 'd/m/Y \à\s H:i', $last ) ) . '</span>';
	}
	echo '</p><p class="ldkp-help">Visitas suas (logado) não contam.</p></div>';
}

add_action( 'save_post_ldkp_proposta', 'ldkp_save_proposal', 10, 2 );
function ldkp_save_proposal( $post_id, $post ) {
	if ( ! isset( $_POST['ldkp_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['ldkp_nonce'] ) ), 'ldkp_save_proposal' ) ) {
		return;
	}
	if ( ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) || ! current_user_can( 'edit_post', $post_id ) ) {
		return;
	}

	$in = isset( $_POST['ep'] ) ? wp_unslash( $_POST['ep'] ) : array(); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput -- sanitizado campo a campo.
	foreach ( ldkp_proposal_fields() as $key => $def ) {
		$raw = isset( $in[ $key ] ) ? $in[ $key ] : ( 'ids' === $def[0] ? array() : '' );
		update_post_meta( $post_id, '_ldkp_' . $key, ldkp_sanitize( $def[0], $raw ) );
	}

	$plans_in = isset( $_POST['ldkp_planos'] ) ? wp_unslash( $_POST['ldkp_planos'] ) : array(); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
	$plans    = array();
	for ( $i = 0; $i < 3; $i++ ) {
		$row = isset( $plans_in[ $i ] ) ? (array) $plans_in[ $i ] : array();
		$out = array();
		foreach ( ldkp_plan_fields() as $key => $def ) {
			$value       = isset( $row[ $key ] ) ? $row[ $key ] : '';
			$out[ $key ] = 'select' === $def[0]
				? ( array_key_exists( $value, ldkp_plan_styles() ) ? $value : 'normal' )
				: ldkp_sanitize( $def[0], $value );
		}
		$plans[] = $out;
	}
	update_post_meta( $post_id, '_ldkp_planos', $plans );
}

/**
 * O título e o endereço da proposta vêm do nome do cliente.
 */
add_filter( 'wp_insert_post_data', 'ldkp_title_from_client', 10, 2 );
function ldkp_title_from_client( $data, $postarr ) {
	if ( 'ldkp_proposta' !== $data['post_type'] || empty( $_POST['ep']['cliente'] ) || ! isset( $_POST['ldkp_nonce'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification -- verificado em save_post.
		return $data;
	}
	$client             = sanitize_text_field( wp_unslash( $_POST['ep']['cliente'] ) ); // phpcs:ignore WordPress.Security.NonceVerification
	$data['post_title'] = $client;
	if ( empty( $data['post_name'] ) || 'publish' !== $data['post_status'] || empty( $postarr['ID'] ) || 'publish' !== get_post_status( $postarr['ID'] ) ) {
		$data['post_name'] = sanitize_title( $client );
	}
	return $data;
}

/* -----------------------------------------------------------------------
 * Lista de propostas
 * -------------------------------------------------------------------- */

add_filter(
	'manage_ldkp_proposta_posts_columns',
	function ( $cols ) {
		return array(
			'cb'       => $cols['cb'],
			'title'    => 'Cliente',
			'ldkp_nicho' => 'Nicho',
			'ldkp_link'  => 'Link',
			'ldkp_views' => 'Visualizações',
			'date'     => 'Data',
		);
	}
);

add_action(
	'manage_ldkp_proposta_posts_custom_column',
	function ( $col, $post_id ) {
		if ( 'ldkp_nicho' === $col ) {
			$n = (int) get_post_meta( $post_id, '_ldkp_nicho_id', true );
			echo $n ? esc_html( get_the_title( $n ) ) : '—';
		} elseif ( 'ldkp_link' === $col ) {
			if ( 'publish' === get_post_status( $post_id ) ) {
				echo '<button type="button" class="button button-small ldkp-copy" data-url="' . esc_attr( get_permalink( $post_id ) ) . '">Copiar link</button>';
			} else {
				echo '<span class="ldkp-help">Rascunho</span>';
			}
		} elseif ( 'ldkp_views' === $col ) {
			$views = (int) get_post_meta( $post_id, '_ldkp_views', true );
			$last  = get_post_meta( $post_id, '_ldkp_last_view', true );
			echo '<strong>' . (int) $views . '</strong>';
			if ( $last ) {
				echo '<br><span class="ldkp-help">' . esc_html( mysql2date( 'd/m H:i', $last ) ) . '</span>';
			}
		}
	},
	10,
	2
);

/* Duplicar proposta. */
add_filter(
	'post_row_actions',
	function ( $actions, $post ) {
		if ( 'ldkp_proposta' === $post->post_type && current_user_can( 'edit_posts' ) ) {
			$url                  = wp_nonce_url( admin_url( 'admin-post.php?action=ldkp_duplicate&post=' . $post->ID ), 'ldkp_duplicate_' . $post->ID );
			$actions['ldkp_duplicate'] = '<a href="' . esc_url( $url ) . '">Duplicar</a>';
		}
		return $actions;
	},
	10,
	2
);

add_action(
	'admin_post_ldkp_duplicate',
	function () {
		$id = isset( $_GET['post'] ) ? absint( $_GET['post'] ) : 0;
		check_admin_referer( 'ldkp_duplicate_' . $id );
		if ( ! $id || 'ldkp_proposta' !== get_post_type( $id ) || ! current_user_can( 'edit_post', $id ) ) {
			wp_die( 'Sem permissão.' );
		}
		$new = wp_insert_post(
			array(
				'post_type'   => 'ldkp_proposta',
				'post_status' => 'draft',
				'post_title'  => get_the_title( $id ) . ' (cópia)',
			)
		);
		foreach ( array_merge( array_keys( ldkp_proposal_fields() ), array( 'planos' ) ) as $key ) {
			update_post_meta( $new, '_ldkp_' . $key, get_post_meta( $id, '_ldkp_' . $key, true ) );
		}
		wp_safe_redirect( admin_url( 'post.php?action=edit&post=' . $new ) );
		exit;
	}
);

/* -----------------------------------------------------------------------
 * Nichos e serviços
 * -------------------------------------------------------------------- */

add_action(
	'add_meta_boxes',
	function ( $post_type ) {
		if ( 'ldkp_nicho' === $post_type ) {
			add_meta_box( 'ldkp_fields', 'Textos do nicho', 'ldkp_render_library_box', 'ldkp_nicho', 'normal', 'high', array( 'fields' => ldkp_niche_fields() ) );
		} elseif ( 'ldkp_servico' === $post_type ) {
			add_meta_box( 'ldkp_fields', 'Escopo e plano', 'ldkp_render_library_box', 'ldkp_servico', 'normal', 'high', array( 'fields' => ldkp_service_fields() ) );
		}
	}
);

function ldkp_render_library_box( $post, $box ) {
	wp_nonce_field( 'ldkp_save_library', 'ldkp_lib_nonce' );
	echo '<div class="ldkp-builder"><p class="ldkp-tip">Estes textos são copiados para a proposta quando você clica em "Preencher textos automaticamente". Use <code>{cliente_curto}</code> onde entra o nome do cliente.</p>';
	foreach ( $box['args']['fields'] as $key => $def ) {
		ldkp_field( 'ep[' . $key . ']', $def[0], $def[1], get_post_meta( $post->ID, '_ldkp_' . $key, true ) );
	}
	echo '</div>';
}

add_action( 'save_post_ldkp_nicho', 'ldkp_save_library' );
add_action( 'save_post_ldkp_servico', 'ldkp_save_library' );
function ldkp_save_library( $post_id ) {
	if ( ! isset( $_POST['ldkp_lib_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['ldkp_lib_nonce'] ) ), 'ldkp_save_library' ) ) {
		return;
	}
	if ( ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) || ! current_user_can( 'edit_post', $post_id ) ) {
		return;
	}
	$fields = 'ldkp_nicho' === get_post_type( $post_id ) ? ldkp_niche_fields() : ldkp_service_fields();
	$in     = isset( $_POST['ep'] ) ? wp_unslash( $_POST['ep'] ) : array(); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
	foreach ( $fields as $key => $def ) {
		update_post_meta( $post_id, '_ldkp_' . $key, ldkp_sanitize( $def[0], isset( $in[ $key ] ) ? $in[ $key ] : '' ) );
	}
}

/* -----------------------------------------------------------------------
 * Configurações
 * -------------------------------------------------------------------- */

function ldkp_settings_fields() {
	return array(
		'Contato e marca'      => array(
			'logo'        => array( 'url', 'Logo (versão clara, para fundo escuro)' ),
			'whatsapp'    => array( 'text', 'WhatsApp com DDD', 'Ex.: 12 99999-9999. Os botões da proposta abrem o WhatsApp com mensagem pronta.' ),
			'email'       => array( 'text', 'E-mail' ),
			'instagram'   => array( 'text', 'Instagram' ),
			'responsavel' => array( 'text', 'Responsável padrão' ),
		),
		'Números e diferenciais' => array(
			'stats'        => array( 'lines', 'Números (número | legenda)', 'Os 3 primeiros aparecem na capa, com contagem animada. Ex.: +80 | Campanhas mensais' ),
			'diferenciais' => array( 'lines', 'Diferenciais da LDK (Título | descrição)', 'Aparecem na introdução, ao lado do cenário do cliente. Ideal: 4 itens.' ),
		),
		'A agência (quem somos)' => array(
			'sobre_img'        => array( 'url', 'Foto (URL)', 'Fica ao fundo, à direita. Ideal: foto horizontal com a pessoa à direita.' ),
			'sobre_titulo'     => array( 'text', 'Título', 'O trecho "LDK Marketing Digital" ganha o destaque ciano.' ),
			'sobre_texto'      => array( 'textarea', 'Texto', 'Deixe uma linha em branco entre os parágrafos.' ),
			'agencia_servicos' => array( 'lines', 'Serviços da agência (um por linha)' ),
		),
		'Clientes e depoimentos' => array(
			'clientes_texto' => array( 'textarea', 'Texto abaixo do título' ),
			'clientes'       => array( 'lines', 'Clientes: Nome | URL do logo | escuro', 'Os logos aparecem em blocos brancos. Escreva "escuro" no fim da linha para logos brancos, que precisam de fundo escuro. Vazio = esconde a seção.' ),
			'depoimentos'    => array( 'lines', 'Depoimentos: Nome | texto', 'Até 3 aparecem na proposta, como avaliações do Google.' ),
		),
		'Conteúdo padrão'      => array(
			'capa_img'      => array( 'url', 'Imagem padrão da capa', 'Vazio = usa o primeiro trabalho selecionado na proposta.' ),
			'metodologia'   => array( 'lines', 'Metodologia (Título | descrição)', 'Igual para todas as propostas.' ),
			'passos'        => array( 'lines', 'Próximos passos padrão (Título | descrição)' ),
			'invest_badge'  => array( 'text', 'Selo padrão do investimento', 'Ex.: Condição especial para novos clientes (vazio = sem selo)' ),
			'invest_titulo' => array( 'text', 'Título padrão do investimento' ),
			'invest_nota'   => array( 'textarea', 'Texto padrão do investimento' ),
			'pos_nota'      => array( 'textarea', 'Observação padrão abaixo dos planos' ),
		),
		'Perfis de clientes'   => array(
			'perfis' => array( 'lines', 'Um por linha: Nome | @usuario | URL da foto | seguidores | nichos', 'Aparecem para escolher ao montar a proposta, em cima dos criativos. Ex.: Clínica Bella | @clinicabella | https://…/foto.jpg | 12,5 mil | saude. Os clientes com Instagram conectado no painel já entram sozinhos.' ),
		),
		'Trabalhos (criativos)' => array(
			'portfolio' => array( 'lines', 'Um por linha: Nome | tags | URL da imagem | link | nichos', 'Nichos = slug do nicho (ex.: lojas-de-veiculos, saude). Servem para sugerir os criativos certos em cada proposta. Use imagens verticais (4:5), como os posts do feed.' ),
		),
	);
}

add_action(
	'admin_menu',
	function () {
		add_submenu_page( 'edit.php?post_type=ldkp_proposta', 'Configurações das propostas', 'Configurações', 'manage_options', 'ldkp-settings', 'ldkp_render_settings' );
	}
);

add_action(
	'admin_post_ldkp_save_settings',
	function () {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( 'Sem permissão.' );
		}
		check_admin_referer( 'ldkp_save_settings' );
		$in  = isset( $_POST['ep'] ) ? wp_unslash( $_POST['ep'] ) : array(); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
		$out = array();
		foreach ( ldkp_settings_fields() as $group ) {
			foreach ( $group as $key => $def ) {
				$out[ $key ] = ldkp_sanitize( $def[0], isset( $in[ $key ] ) ? $in[ $key ] : '' );
			}
		}
		update_option( 'ldkp_settings', $out );
		wp_safe_redirect( admin_url( 'edit.php?post_type=ldkp_proposta&page=ldkp-settings&updated=1' ) );
		exit;
	}
);

function ldkp_render_settings() {
	$s = ldkp_settings();
	echo '<div class="wrap ldkp-builder"><h1>Configurações das propostas</h1>';
	if ( isset( $_GET['updated'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification
		echo '<div class="notice notice-success is-dismissible"><p>Configurações salvas.</p></div>';
	}
	echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
	wp_nonce_field( 'ldkp_save_settings' );
	echo '<input type="hidden" name="action" value="ldkp_save_settings">';
	$n = 1;
	foreach ( ldkp_settings_fields() as $title => $group ) {
		ldkp_section_open( str_pad( $n++, 2, '0', STR_PAD_LEFT ), $title );
		foreach ( $group as $key => $def ) {
			ldkp_field( 'ep[' . $key . ']', $def[0], $def[1], $s[ $key ], isset( $def[2] ) ? $def[2] : '' );
		}
		ldkp_section_close();
	}
	submit_button( 'Salvar configurações' );
	echo '</form></div>';
}

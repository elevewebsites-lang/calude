<?php
/**
 * Página /gerar-proposta: o formulário fora do wp-admin, só para quem está logado.
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'LDKP_PAINEL_SLUG', 'gerar-proposta' );

function ldkp_painel_url( $args = array() ) {
	return add_query_arg( $args, home_url( '/' . LDKP_PAINEL_SLUG . '/' ) );
}

add_action( 'init', 'ldkp_painel_rewrite' );
function ldkp_painel_rewrite() {
	add_rewrite_rule( '^' . LDKP_PAINEL_SLUG . '/?$', 'index.php?ldkp_painel=1', 'top' );
}

add_filter(
	'query_vars',
	function ( $vars ) {
		$vars[] = 'ldkp_painel';
		return $vars;
	}
);

// Atualizações do plugin: cria nichos/serviços novos e recria as regras de endereço, uma vez por versão.
add_action(
	'init',
	function () {
		if ( get_option( 'ldkp_rewrite_version' ) !== LDKP_VERSION ) {
			ldkp_migrate_defaults();
			ldkp_seed_defaults();
			flush_rewrite_rules();
			update_option( 'ldkp_rewrite_version', LDKP_VERSION );
		}
	},
	99
);

add_filter( 'template_include', 'ldkp_painel_template', 100 );
function ldkp_painel_template( $template ) {
	if ( ! get_query_var( 'ldkp_painel' ) ) {
		return $template;
	}
	if ( ! is_user_logged_in() ) {
		auth_redirect();
	}
	if ( ! current_user_can( 'edit_posts' ) ) {
		wp_die( 'Você não tem permissão para gerar propostas.', 'Sem permissão', array( 'response' => 403 ) );
	}
	nocache_headers();
	return LDKP_DIR . 'templates/painel.php';
}

// Não deixa o painel aparecer para buscadores nem ser salvo em cache.
add_action(
	'send_headers',
	function () {
		if ( get_query_var( 'ldkp_painel' ) ) {
			header( 'X-Robots-Tag: noindex, nofollow', true );
		}
	}
);

/**
 * Salva a proposta enviada pela página (cria nova ou atualiza a existente).
 * Os campos são gravados por ldkp_save_proposal(), que roda no save_post.
 */
add_action( 'admin_post_ldkp_painel_save', 'ldkp_painel_save' );
function ldkp_painel_save() {
	if ( ! isset( $_POST['ldkp_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['ldkp_nonce'] ) ), 'ldkp_save_proposal' ) ) {
		wp_die( 'Sessão expirada. Volte e tente de novo.' );
	}

	$client = isset( $_POST['ep']['cliente'] ) ? sanitize_text_field( wp_unslash( $_POST['ep']['cliente'] ) ) : '';
	if ( '' === $client ) {
		wp_safe_redirect( ldkp_painel_url( array( 'erro' => 'cliente' ) ) );
		exit;
	}

	$id = isset( $_POST['ldkp_post_id'] ) ? absint( $_POST['ldkp_post_id'] ) : 0;
	if ( $id ) {
		if ( 'ldkp_proposta' !== get_post_type( $id ) || ! current_user_can( 'edit_post', $id ) ) {
			wp_die( 'Sem permissão para editar esta proposta.' );
		}
		wp_update_post(
			array(
				'ID'          => $id,
				'post_status' => 'publish',
			)
		);
	} else {
		if ( ! current_user_can( 'publish_posts' ) ) {
			wp_die( 'Sem permissão para publicar propostas.' );
		}
		$id = wp_insert_post(
			array(
				'post_type'   => 'ldkp_proposta',
				'post_status' => 'publish',
				'post_title'  => $client,
			)
		);
	}

	if ( ! $id || is_wp_error( $id ) ) {
		wp_die( 'Não foi possível salvar a proposta.' );
	}

	wp_safe_redirect( ldkp_painel_url( array( 'pronta' => $id ) ) . '#topo' );
	exit;
}

/* Atalho no menu do wp-admin. */
add_action(
	'admin_menu',
	function () {
		global $submenu;
		$submenu['edit.php?post_type=ldkp_proposta'][] = array( 'Gerar pela página ↗', 'edit_posts', ldkp_painel_url() ); // phpcs:ignore WordPress.WP.GlobalVariablesOverride
	},
	20
);

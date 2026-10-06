<?php
/**
 * Endereços do sistema:
 *   /entrar                 login
 *   /convite/<token>        cadastro do cliente
 *   /painel/<seção>/<id>    painel da Eleve (admin e equipe)
 *   /cliente/<seção>/<id>   área do cliente
 * A página inicial manda cada um para o seu lugar.
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_action( 'init', 'ap_rewrite_rules' );
function ap_rewrite_rules() {
	add_rewrite_rule( '^entrar/?$', 'index.php?ap_route=login', 'top' );
	add_rewrite_rule( '^sair/?$', 'index.php?ap_route=logout', 'top' );
	add_rewrite_rule( '^convite/([A-Za-z0-9]+)/?$', 'index.php?ap_route=invite&ap_token=$matches[1]', 'top' );
	add_rewrite_rule( '^painel/?$', 'index.php?ap_route=panel', 'top' );
	add_rewrite_rule( '^painel/([a-z-]+)/?$', 'index.php?ap_route=panel&ap_section=$matches[1]', 'top' );
	add_rewrite_rule( '^painel/([a-z-]+)/([0-9]+)/?$', 'index.php?ap_route=panel&ap_section=$matches[1]&ap_id=$matches[2]', 'top' );
	add_rewrite_rule( '^cliente/?$', 'index.php?ap_route=client', 'top' );
	add_rewrite_rule( '^cliente/([a-z-]+)/?$', 'index.php?ap_route=client&ap_section=$matches[1]', 'top' );
	add_rewrite_rule( '^cliente/([a-z-]+)/([0-9]+)/?$', 'index.php?ap_route=client&ap_section=$matches[1]&ap_id=$matches[2]', 'top' );
}

add_filter(
	'query_vars',
	function ( $vars ) {
		return array_merge( $vars, array( 'ap_route', 'ap_section', 'ap_id', 'ap_token' ) );
	}
);

/**
 * Para onde cada usuário vai depois de entrar.
 */
function ap_home_for( $user_id ) {
	if ( ap_is_team( $user_id ) ) {
		return ap_panel_url();
	}
	if ( ap_is_client_user( $user_id ) ) {
		return ap_client_url();
	}
	return ap_url( 'entrar' );
}

add_action( 'template_redirect', 'ap_route', 1 );
function ap_route() {
	$route = get_query_var( 'ap_route' );

	// Página inicial: o sistema ocupa o site inteiro.
	if ( ! $route && ( is_front_page() || is_home() ) ) {
		wp_safe_redirect( is_user_logged_in() ? ap_home_for( get_current_user_id() ) : ap_url( 'entrar' ) );
		exit;
	}
	if ( ! $route ) {
		return;
	}

	nocache_headers();
	header( 'X-Robots-Tag: noindex, nofollow', true );

	if ( 'logout' === $route ) {
		wp_logout();
		wp_safe_redirect( ap_url( 'entrar' ) );
		exit;
	}

	if ( 'login' === $route ) {
		if ( is_user_logged_in() ) {
			wp_safe_redirect( ap_home_for( get_current_user_id() ) );
			exit;
		}
		ap_handle_login();
		ap_render( 'login' );
	}

	if ( in_array( $route, array( 'quote', 'pay', 'payment' ), true ) ) {
		ap_public_route( $route );
	}

	if ( 'invite' === $route ) {
		ap_handle_invite();
		ap_render( 'invite' );
	}

	if ( ! is_user_logged_in() ) {
		wp_safe_redirect( add_query_arg( 'volta', rawurlencode( ap_current_path() ), ap_url( 'entrar' ) ) );
		exit;
	}

	$section = sanitize_key( get_query_var( 'ap_section' ) );
	$id      = absint( get_query_var( 'ap_id' ) );

	if ( 'panel' === $route ) {
		if ( ! ap_is_team() ) {
			wp_safe_redirect( ap_home_for( get_current_user_id() ) );
			exit;
		}
		$map = array(
			''            => array( 'dashboard', '' ),
			'clientes'    => array( 'clientes', 'clientes' ),
			'cliente'     => array( 'cliente', 'clientes' ),
			'leads'       => array( 'leads', 'leads' ),
			'lead'        => array( 'lead', 'leads' ),
			'orcamentos'  => array( 'orcamentos', 'orcamentos' ),
			'catalogo'    => array( 'catalogo', 'produtos_cat' ),
			'mensagens'   => array( 'mensagens', 'clientes' ),
			'emails'      => array( 'emails', 'emails' ),
			'orcamento'   => array( 'orcamento', 'orcamentos' ),
			'calculadora' => array( 'calculadora', 'orcamentos' ),
			'pedidos'     => array( 'projetos', 'projetos' ),
			'projetos'    => array( 'projetos', 'projetos' ),
			'pedido'      => array( 'projeto', 'projetos' ),
			'projeto'     => array( 'projeto', 'projetos' ),
			'produtos'    => array( 'produtos', 'produtos' ),
			'produto'     => array( 'produto', 'produtos' ),
			'filamentos'  => array( 'filamentos', 'estoque' ),
			'insumos'     => array( 'insumos', 'estoque' ),
			'compras'     => array( 'compras', 'estoque' ),
			'impressoras' => array( 'impressoras', 'estoque' ),
			'frete'       => array( 'frete', 'projetos' ),
			'marketing'   => array( 'marketing', 'leads' ),
			'tarefas'     => array( 'tarefas', '' ),
			'tarefa'      => array( 'tarefa', 'tarefas' ),
			'financeiro'  => array( 'financeiro', 'financeiro' ),
			'equipe'      => array( 'equipe', 'admin' ),
			'config'      => array( 'config', 'admin' ),
			'saude'       => array( 'saude', 'admin' ),
			'apontamentos' => array( 'apontamentos', '' ),
		);

		if ( ! isset( $map[ $section ] ) || ap_page_off( $section ) ) {
			ap_render( 'panel/404', array( 'section' => $section ) );
		}
		list( $view, $area ) = $map[ $section ];
		$allowed = '' === $area || ( 'admin' === $area ? ap_is_admin() : ap_can( $area ) );
		// Quem só vê as próprias tarefas ainda pode abrir as tarefas atribuídas a ele.
		if ( ! $allowed && 'tarefa' === $view && $id ) {
			$task    = ap_get( 'tasks', $id );
			$allowed = $task && (int) $task->assignee === get_current_user_id();
		}
		if ( ! $allowed ) {
			ap_render( 'panel/sem-acesso' );
		}
		ap_render( 'panel/' . $view, array( 'id' => $id ) );
	}

	if ( 'client' === $route ) {
		$client = ap_viewing_client();
		if ( ! $client ) {
			wp_safe_redirect( ap_home_for( get_current_user_id() ) );
			exit;
		}
		$views = array( '' => 'inicio', 'projeto' => 'projeto', 'pedido' => 'projeto', 'perfil' => 'perfil', 'novo' => 'novo-pedido', 'tabela' => 'tabela', 'mensagens' => 'mensagens' );
		$view  = isset( $views[ $section ] ) ? $views[ $section ] : 'inicio';
		ap_render( 'client/' . $view, array( 'id' => $id, 'client' => $client ) );
	}
}

/**
 * Cliente que está sendo visto: o próprio cliente logado, ou,
 * para o admin, o cliente escolhido em "Ver como cliente" (?como=ID).
 */
function ap_viewing_client() {
	if ( ap_is_team() && isset( $_GET['como'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification
		return ap_can( 'clientes' ) ? ap_get( 'clients', absint( $_GET['como'] ) ) : null; // phpcs:ignore WordPress.Security.NonceVerification
	}
	return ap_is_client_user() ? ap_current_client() : null;
}

/**
 * Mantém o "?como=ID" nos links da área do cliente quando o admin está visualizando.
 */
function ap_client_link( $section = '', $id = 0, $args = array() ) {
	if ( ap_is_team() && isset( $_GET['como'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification
		$args['como'] = absint( $_GET['como'] ); // phpcs:ignore WordPress.Security.NonceVerification
	}
	return ap_client_url( $section, $id, $args );
}

function ap_current_path() {
	return isset( $_SERVER['REQUEST_URI'] ) ? esc_url_raw( wp_unslash( $_SERVER['REQUEST_URI'] ) ) : '/';
}

/**
 * Renderiza uma tela do sistema e encerra.
 */
function ap_render( $view, $vars = array() ) {
	$file = AP_DIR . 'templates/' . $view . '.php';
	if ( ! file_exists( $file ) ) {
		status_header( 404 );
		$file = AP_DIR . 'templates/panel/404.php';
	}
	extract( $vars ); // phpcs:ignore WordPress.PHP.DontExtract
	include $file;
	exit;
}

/* -----------------------------------------------------------------------
 * Login
 * -------------------------------------------------------------------- */

function ap_handle_login() {
	if ( 'POST' !== ( isset( $_SERVER['REQUEST_METHOD'] ) ? $_SERVER['REQUEST_METHOD'] : '' ) || ! isset( $_POST['ap_login_nonce'] ) ) { // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
		return;
	}
	if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['ap_login_nonce'] ) ), 'ap_login' ) ) {
		$GLOBALS['ap_login_error'] = 'Sessão expirada. Tente de novo.';
		return;
	}
	$user = wp_signon(
		array(
			'user_login'    => isset( $_POST['email'] ) ? sanitize_text_field( wp_unslash( $_POST['email'] ) ) : '',
			'user_password' => isset( $_POST['senha'] ) ? wp_unslash( $_POST['senha'] ) : '', // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
			'remember'      => ! empty( $_POST['lembrar'] ),
		),
		is_ssl()
	);
	if ( is_wp_error( $user ) ) {
		$GLOBALS['ap_login_error'] = 'ap_locked' === $user->get_error_code() ? $user->get_error_message() : 'E-mail ou senha incorretos.';
		return;
	}
	$back = isset( $_POST['volta'] ) ? esc_url_raw( wp_unslash( $_POST['volta'] ) ) : '';
	wp_safe_redirect( $back && 0 === strpos( $back, '/' ) ? home_url( $back ) : ap_home_for( $user->ID ) );
	exit;
}

// Login também pelo e-mail (o WordPress já aceita), e sem barra do admin no sistema.
add_filter(
	'show_admin_bar',
	function ( $show ) {
		return get_query_var( 'ap_route' ) || ! ap_is_admin() ? false : $show;
	}
);

// Cliente e equipe não entram no wp-admin: vão para o painel deles.
add_action(
	'admin_init',
	function () {
		if ( wp_doing_ajax() || ap_is_admin() || ! is_user_logged_in() ) {
			return;
		}
		$script = isset( $_SERVER['SCRIPT_NAME'] ) ? basename( sanitize_text_field( wp_unslash( $_SERVER['SCRIPT_NAME'] ) ) ) : '';
		if ( in_array( $script, array( 'admin-post.php', 'async-upload.php', 'media-upload.php' ), true ) ) {
			return;
		}
		wp_safe_redirect( ap_home_for( get_current_user_id() ) );
		exit;
	}
);

// Depois de redefinir a senha pelo "Esqueci a senha", volta para o login do sistema.
add_filter(
	'login_redirect',
	function ( $redirect, $requested, $user ) {
		return $user instanceof WP_User ? ap_home_for( $user->ID ) : $redirect;
	},
	10,
	3
);

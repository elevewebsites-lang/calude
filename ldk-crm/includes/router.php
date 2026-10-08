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

add_action( 'init', 'lk_rewrite_rules' );
function lk_rewrite_rules() {
	add_rewrite_rule( '^entrar/?$', 'index.php?lk_route=login', 'top' );
	add_rewrite_rule( '^sair/?$', 'index.php?lk_route=logout', 'top' );
	add_rewrite_rule( '^convite/([A-Za-z0-9]+)/?$', 'index.php?lk_route=invite&lk_token=$matches[1]', 'top' );
	add_rewrite_rule( '^painel/?$', 'index.php?lk_route=panel', 'top' );
	add_rewrite_rule( '^painel/([a-z-]+)/?$', 'index.php?lk_route=panel&lk_section=$matches[1]', 'top' );
	add_rewrite_rule( '^painel/([a-z-]+)/([0-9]+)/?$', 'index.php?lk_route=panel&lk_section=$matches[1]&lk_id=$matches[2]', 'top' );
	add_rewrite_rule( '^cliente/?$', 'index.php?lk_route=client', 'top' );
	add_rewrite_rule( '^cliente/([a-z-]+)/?$', 'index.php?lk_route=client&lk_section=$matches[1]', 'top' );
	add_rewrite_rule( '^cliente/([a-z-]+)/([0-9]+)/?$', 'index.php?lk_route=client&lk_section=$matches[1]&lk_id=$matches[2]', 'top' );
}

add_filter(
	'query_vars',
	function ( $vars ) {
		return array_merge( $vars, array( 'lk_route', 'lk_section', 'lk_id', 'lk_token' ) );
	}
);

/**
 * Para onde cada usuário vai depois de entrar.
 */
function lk_home_for( $user_id ) {
	if ( lk_is_team( $user_id ) ) {
		return lk_panel_url();
	}
	if ( lk_is_client_user( $user_id ) ) {
		return lk_client_url();
	}
	return lk_url( 'entrar' );
}

add_action( 'template_redirect', 'lk_route', 1 );
function lk_route() {
	$route = get_query_var( 'lk_route' );

	// Página inicial: o sistema ocupa o site inteiro.
	if ( ! $route && ( is_front_page() || is_home() ) ) {
		wp_safe_redirect( is_user_logged_in() ? lk_home_for( get_current_user_id() ) : lk_url( 'entrar' ) );
		exit;
	}
	if ( ! $route ) {
		return;
	}

	nocache_headers();
	header( 'X-Robots-Tag: noindex, nofollow', true );

	if ( 'logout' === $route ) {
		wp_logout();
		wp_safe_redirect( lk_url( 'entrar' ) );
		exit;
	}

	if ( 'login' === $route ) {
		if ( is_user_logged_in() ) {
			wp_safe_redirect( lk_home_for( get_current_user_id() ) );
			exit;
		}
		lk_handle_login();
		lk_render( 'login' );
	}

	if ( in_array( $route, array( 'quote', 'pay', 'payment' ), true ) ) {
		lk_public_route( $route );
	}

	if ( 'invite' === $route ) {
		lk_handle_invite();
		lk_render( 'invite' );
	}

	if ( ! is_user_logged_in() ) {
		wp_safe_redirect( add_query_arg( 'volta', rawurlencode( lk_current_path() ), lk_url( 'entrar' ) ) );
		exit;
	}

	$section = sanitize_key( get_query_var( 'lk_section' ) );
	$id      = absint( get_query_var( 'lk_id' ) );

	if ( 'panel' === $route ) {
		if ( ! lk_is_team() ) {
			wp_safe_redirect( lk_home_for( get_current_user_id() ) );
			exit;
		}
		$map = array(
			''            => array( 'dashboard', '' ),
			'clientes'    => array( 'clientes', 'clientes' ),
			'cliente'     => array( 'cliente', 'clientes' ),
			'leads'       => array( 'leads', 'leads' ),
			'lead'        => array( 'lead', 'leads' ),
			'orcamentos'  => array( 'orcamentos', 'orcamentos' ),
			'conteudo'    => array( 'conteudo', 'conteudo' ),
			'post'        => array( 'post', 'conteudo' ),
			'planejamento' => array( 'planejamento', 'conteudo' ),
			'time'        => array( 'time', '' ),
			'chat'        => array( 'chat', '' ),
			'foco'        => array( 'foco', '' ),
			'agenda'      => array( 'agenda', 'clientes' ),
			'reunioes'    => array( 'reunioes', '' ),
			'formularios' => array( 'formularios', 'clientes' ),
			'formulario'  => array( 'formulario', 'clientes' ),
			'resposta'    => array( 'resposta', 'clientes' ),
			'relatorios'  => array( 'relatorios', 'relatorios' ),
			'relatorio'   => array( 'relatorio', 'relatorios' ),
			'trafego'     => array( 'trafego', 'trafego' ),
			'cobrancas'   => array( 'cobrancas', 'financeiro' ),
			'mensagens'   => array( 'mensagens', 'clientes' ),
			'emails'      => array( 'emails', 'emails' ),
			'redes'       => array( 'redes', 'clientes' ),
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
			'propostas'   => array( 'propostas', 'orcamentos' ),
			'proposta'    => array( 'proposta', 'orcamentos' ),
			'propostas-servicos' => array( 'propostas-servicos', 'orcamentos' ),
			'sala'        => array( 'sala', '' ),
			'conta'       => array( 'conta', '' ),
			'contrato'    => array( 'contrato', 'clientes' ),
			'feedback'    => array( 'feedback', '' ),
			'novidades'   => array( 'novidades', '' ),
			'ajuda'       => array( 'ajuda', '' ),
			'ranking'     => array( 'ranking', '' ),
			'metas'       => array( 'metas', 'admin' ),
			'contratos'   => array( 'contratos', 'clientes' ),
			'vencimentos' => array( 'vencimentos', 'clientes' ),
			'importar'    => array( 'importar', 'conteudo' ),
			'relatorio-importar' => array( 'relatorio-importar', 'relatorios' ),
			'prospeccao'  => array( 'prospeccao', 'leads' ),
			'gamificacao' => array( 'gamificacao', 'admin' ),
		);

		if ( ! isset( $map[ $section ] ) || lk_page_off( $section ) ) {
			lk_render( 'panel/404', array( 'section' => $section ) );
		}
		list( $view, $area ) = $map[ $section ];
		$allowed = '' === $area || ( 'admin' === $area ? lk_is_admin() : lk_can( $area ) );
		// Quem só vê as próprias tarefas ainda pode abrir as tarefas atribuídas a ele.
		if ( ! $allowed && 'tarefa' === $view && $id ) {
			$task    = lk_get( 'tasks', $id );
			$allowed = $task && (int) $task->assignee === get_current_user_id();
		}
		if ( ! $allowed ) {
			lk_render( 'panel/sem-acesso' );
		}
		lk_render( 'panel/' . $view, array( 'id' => $id ) );
	}

	if ( 'client' === $route ) {
		$client = lk_viewing_client();
		if ( ! $client ) {
			wp_safe_redirect( lk_home_for( get_current_user_id() ) );
			exit;
		}
		$views = array( '' => 'inicio', 'perfil' => 'perfil', 'aprovacoes' => 'aprovacoes', 'conteudos' => 'conteudos', 'briefing' => 'briefing', 'relatorios' => 'relatorios', 'mensagens' => 'mensagens', 'projeto' => 'projeto', 'contratos' => 'contratos', 'formulario' => 'formulario' );
		$view  = isset( $views[ $section ] ) ? $views[ $section ] : 'inicio';
		lk_render( 'client/' . $view, array( 'id' => $id, 'client' => $client ) );
	}
}

/**
 * Cliente que está sendo visto: o próprio cliente logado, ou,
 * para o admin, o cliente escolhido em "Ver como cliente" (?como=ID).
 */
function lk_viewing_client() {
	if ( lk_is_team() && isset( $_GET['como'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification
		return lk_can( 'clientes' ) ? lk_get( 'clients', absint( $_GET['como'] ) ) : null; // phpcs:ignore WordPress.Security.NonceVerification
	}
	return lk_is_client_user() ? lk_current_client() : null;
}

/**
 * Mantém o "?como=ID" nos links da área do cliente quando o admin está visualizando.
 */
function lk_client_link( $section = '', $id = 0, $args = array() ) {
	if ( lk_is_team() && isset( $_GET['como'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification
		$args['como'] = absint( $_GET['como'] ); // phpcs:ignore WordPress.Security.NonceVerification
	}
	return lk_client_url( $section, $id, $args );
}

function lk_current_path() {
	return isset( $_SERVER['REQUEST_URI'] ) ? esc_url_raw( wp_unslash( $_SERVER['REQUEST_URI'] ) ) : '/';
}

/**
 * Renderiza uma tela do sistema e encerra.
 */
function lk_render( $view, $vars = array() ) {
	$file = LK_DIR . 'templates/' . $view . '.php';
	if ( ! file_exists( $file ) ) {
		status_header( 404 );
		$file = LK_DIR . 'templates/panel/404.php';
	}
	extract( $vars ); // phpcs:ignore WordPress.PHP.DontExtract
	include $file;
	exit;
}

/* -----------------------------------------------------------------------
 * Login
 * -------------------------------------------------------------------- */

/** Dados do cartão de acesso (nome completo, função, tipo, avatar) de quem está entrando. */
function lk_login_card_data( $user ) {
	$kind = lk_is_team( $user->ID ) ? 'equipe' : 'cliente';
	$name = trim( $user->first_name . ' ' . $user->last_name );
	$name = $name ? $name : $user->display_name;
	$role = 'Cliente';
	if ( 'equipe' === $kind ) {
		$role = lk_user_role_label( $user->ID );
		$role = $role ? $role : 'Equipe';
	} else {
		$c = lk_client_by_user( $user->ID );
		if ( $c ) {
			$name = $c->name ? $c->name : $name;
			$role = $c->company ? 'Cliente · ' . $c->company : 'Cliente';
		}
	}
	$photo = 'equipe' === $kind && function_exists( 'lk_user_photo' ) ? lk_user_photo( $user->ID ) : '';
	$logo  = '';
	if ( 'cliente' === $kind && ! empty( $c ) ) {
		$logo = esc_url_raw( (string) $c->logo );
	}
	return array( 'photo' => $photo ? $photo : $logo, 'photo_is_logo' => (bool) $logo, 'kind' => $kind, 'name' => $name, 'first' => strtok( $name, ' ' ), 'role' => $role, 'emoji' => function_exists( 'lk_avatar_emoji' ) ? lk_avatar_emoji( $user->ID ) : '', 'num' => str_pad( (string) $user->ID, 4, '0', STR_PAD_LEFT ) );
}

/** GET lk/v1/login-card?e=email → { found, ... } (com limite de consultas por IP). */
add_action(
	'rest_api_init',
	function () {
		register_rest_route(
			'lk/v1',
			'/login-card',
			array(
				'methods'             => 'GET',
				'permission_callback' => '__return_true',
				'callback'            => function ( WP_REST_Request $r ) {
					if ( '1' !== (string) lk_setting( 'login_card' ) ) {
						return array( 'found' => false );
					}
					$ip  = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : 'x'; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
					$key = 'lk_lc_' . md5( $ip );
					$n   = (int) get_transient( $key );
					if ( $n >= 12 ) {
						return new WP_Error( 'lk', 'Muitas consultas. Aguarde alguns minutos.', array( 'status' => 429 ) );
					}
					set_transient( $key, $n + 1, 10 * MINUTE_IN_SECONDS );
					$e = sanitize_text_field( (string) $r->get_param( 'e' ) );
					$u = is_email( $e ) ? get_user_by( 'email', $e ) : null;
					if ( ! $u || ! ( lk_is_team( $u->ID ) || lk_is_client_user( $u->ID ) ) ) {
						return array( 'found' => false );
					}
					return array( 'found' => true ) + lk_login_card_data( $u );
				},
			)
		);
	}
);

/** Login pelo cartão animado: o mesmo formulário, respondendo JSON em vez de redirecionar. */
function lk_login_json( $data ) {
	nocache_headers();
	wp_send_json( $data );
}

function lk_handle_login() {
	if ( 'POST' !== ( isset( $_SERVER['REQUEST_METHOD'] ) ? $_SERVER['REQUEST_METHOD'] : '' ) || ! isset( $_POST['lk_login_nonce'] ) ) { // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
		return;
	}
	$ajax = ! empty( $_SERVER['HTTP_X_LK_LOGIN'] );
	if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['lk_login_nonce'] ) ), 'lk_login' ) ) {
		$GLOBALS['lk_login_error'] = 'Sessão expirada. Recarregue a página e tente de novo.';
		if ( $ajax ) {
			lk_login_json( array( 'ok' => false, 'msg' => $GLOBALS['lk_login_error'] ) );
		}
		return;
	}
	$posted = isset( $_POST['email'] ) ? sanitize_text_field( wp_unslash( $_POST['email'] ) ) : '';
	if ( $ajax ) {
		// Qualquer redirecionamento do login (painel, área do cliente ou código em duas etapas) vira JSON para a animação.
		add_filter(
			'wp_redirect',
			function ( $loc ) use ( $posted ) {
				$u = wp_get_current_user();
				if ( ! $u || ! $u->ID ) {
					$u = is_email( $posted ) ? get_user_by( 'email', $posted ) : get_user_by( 'login', $posted );
				}
				$q    = (string) wp_parse_url( $loc, PHP_URL_QUERY );
				parse_str( $q, $qs );
				$data = array( 'ok' => empty( $qs['aviso'] ), 'redirect' => $loc, 'need_code' => isset( $qs['codigo'] ), 'aviso' => $qs['aviso'] ?? '' );
				if ( $u && $u->ID ) {
					$data += lk_login_card_data( $u );
				}
				if ( ! $data['ok'] ) {
					$data['msg'] = 'Não foi possível concluir o acesso. Atualize a página e tente de novo.';
				}
				lk_login_json( $data );
			},
			1
		);
	}
	$user = wp_signon(
		array(
			'user_login'    => $posted,
			'user_password' => isset( $_POST['senha'] ) ? wp_unslash( $_POST['senha'] ) : '', // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
			'remember'      => ! empty( $_POST['lembrar'] ),
		),
		is_ssl()
	);
	if ( is_wp_error( $user ) ) {
		$GLOBALS['lk_login_error'] = 'lk_locked' === $user->get_error_code() ? $user->get_error_message() : 'E-mail ou senha incorretos.';
		if ( $ajax ) {
			lk_login_json( array( 'ok' => false, 'msg' => $GLOBALS['lk_login_error'], 'locked' => 'lk_locked' === $user->get_error_code() ) );
		}
		return;
	}
	$back = isset( $_POST['volta'] ) ? esc_url_raw( wp_unslash( $_POST['volta'] ) ) : '';
	wp_safe_redirect( $back && 0 === strpos( $back, '/' ) ? home_url( $back ) : lk_home_for( $user->ID ) );
	exit;
}

// Login também pelo e-mail (o WordPress já aceita), e sem barra do admin no sistema.
add_filter(
	'show_admin_bar',
	function ( $show ) {
		return get_query_var( 'lk_route' ) || ! lk_is_admin() ? false : $show;
	}
);

// Cliente e equipe não entram no wp-admin: vão para o painel deles.
add_action(
	'admin_init',
	function () {
		if ( wp_doing_ajax() || lk_is_admin() || ! is_user_logged_in() ) {
			return;
		}
		$script = isset( $_SERVER['SCRIPT_NAME'] ) ? basename( sanitize_text_field( wp_unslash( $_SERVER['SCRIPT_NAME'] ) ) ) : '';
		if ( in_array( $script, array( 'admin-post.php', 'async-upload.php', 'media-upload.php' ), true ) ) {
			return;
		}
		wp_safe_redirect( lk_home_for( get_current_user_id() ) );
		exit;
	}
);

// Depois de redefinir a senha pelo "Esqueci a senha", volta para o login do sistema.
add_filter(
	'login_redirect',
	function ( $redirect, $requested, $user ) {
		return $user instanceof WP_User ? lk_home_for( $user->ID ) : $redirect;
	},
	10,
	3
);

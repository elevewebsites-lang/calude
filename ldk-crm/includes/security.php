<?php
/**
 * Segurança do painel.
 *
 *  - Bloqueio de tentativas de senha (força bruta), inclusive no wp-login.php.
 *  - Verificação em duas etapas por e-mail para a equipe e o administrador,
 *    com "confiar neste aparelho por 30 dias".
 *  - Fecha portas que o painel não usa: XML-RPC, senhas de aplicativo, API do WordPress
 *    para quem não é administrador, listagem de usuários, editor de arquivos.
 *  - Cabeçalhos de proteção (clickjacking, sniffing, vazamento de links com token).
 *  - Arquivos enviados com nome imprevisível e pastas sem listagem.
 *  - Registro dos eventos de segurança em Configurações.
 *
 * Emergência (sem acesso ao e-mail): em wp-config.php, define( 'LK_DISABLE_2FA', true );
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/* -----------------------------------------------------------------------
 * Registro
 * -------------------------------------------------------------------- */

function lk_client_ip() {
	return isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '0.0.0.0';
}

function lk_sec_log( $event, $detail = '' ) {
	$log = get_option( 'lk_sec_log', array() );
	$log = is_array( $log ) ? $log : array();
	array_unshift(
		$log,
		array(
			'at'     => lk_now(),
			'event'  => $event,
			'detail' => mb_substr( (string) $detail, 0, 160 ),
			'ip'     => lk_client_ip(),
		)
	);
	update_option( 'lk_sec_log', array_slice( $log, 0, 80 ), false );
}

/* -----------------------------------------------------------------------
 * Força bruta: 5 erros em 15 minutos → bloqueio de 30 minutos (por IP e por usuário)
 * -------------------------------------------------------------------- */

function lk_bf_keys( $username ) {
	return array(
		'lk_bf_ip_' . md5( lk_client_ip() ),
		'lk_bf_u_' . md5( strtolower( trim( (string) $username ) ) ),
	);
}

add_filter(
	'authenticate',
	function ( $user, $username ) {
		if ( '' === (string) $username ) {
			return $user;
		}
		foreach ( lk_bf_keys( $username ) as $key ) {
			$d = get_transient( $key );
			if ( is_array( $d ) && ! empty( $d['until'] ) && $d['until'] > time() ) {
				$min = (int) ceil( ( $d['until'] - time() ) / 60 );
				return new WP_Error( 'lk_locked', 'Muitas tentativas erradas. Por segurança, o acesso foi bloqueado por ' . $min . ' minuto' . ( $min > 1 ? 's' : '' ) . '.' );
			}
		}
		return $user;
	},
	30,
	2
);

add_action(
	'wp_login_failed',
	function ( $username ) {
		$locked = false;
		foreach ( lk_bf_keys( $username ) as $key ) {
			$d = get_transient( $key );
			$d = is_array( $d ) ? $d : array( 'n' => 0, 'until' => 0 );
			++$d['n'];
			if ( $d['n'] >= 5 ) {
				$d['until'] = time() + 30 * MINUTE_IN_SECONDS;
				$locked     = $locked || 5 === $d['n'];
			}
			set_transient( $key, $d, $d['until'] ? 30 * MINUTE_IN_SECONDS : 15 * MINUTE_IN_SECONDS );
		}
		if ( $locked ) {
			lk_sec_log( 'bloqueio', 'Usuário: ' . $username );
		}
	}
);

/* -----------------------------------------------------------------------
 * Verificação em duas etapas (e-mail)
 * -------------------------------------------------------------------- */

function lk_2fa_enabled_for( $user ) {
	if ( defined( 'LK_DISABLE_2FA' ) && LK_DISABLE_2FA ) {
		return false;
	}
	if ( '0' === (string) lk_setting( 'seg_2fa' ) ) {
		return false;
	}
	return $user instanceof WP_User && ( user_can( $user, 'manage_options' ) || in_array( 'lk_team', (array) $user->roles, true ) );
}

/**
 * Cookie de aparelho confiável: expira em 30 dias e cai se a senha mudar.
 */
function lk_trust_sig( $user, $exp ) {
	return hash_hmac( 'sha256', $user->ID . '|' . $exp . '|' . substr( $user->user_pass, -12 ), wp_salt( 'auth' ) );
}

function lk_device_trusted( $user ) {
	$name = 'lk_td_' . $user->ID;
	if ( empty( $_COOKIE[ $name ] ) ) {
		return false;
	}
	$parts = explode( '|', sanitize_text_field( wp_unslash( $_COOKIE[ $name ] ) ) );
	return 2 === count( $parts ) && (int) $parts[0] > time() && hash_equals( lk_trust_sig( $user, (int) $parts[0] ), $parts[1] );
}

function lk_trust_device( $user ) {
	$exp = time() + 30 * DAY_IN_SECONDS;
	setcookie( 'lk_td_' . $user->ID, $exp . '|' . lk_trust_sig( $user, $exp ), $exp, COOKIEPATH ? COOKIEPATH : '/', COOKIE_DOMAIN, is_ssl(), true );
}

add_action( 'wp_login', 'lk_2fa_intercept', 5, 2 );
function lk_2fa_intercept( $login, $user ) {
	if ( defined( 'LK_2FA_PASSED' ) || ! lk_2fa_enabled_for( $user ) || lk_device_trusted( $user ) ) {
		if ( lk_2fa_enabled_for( $user ) || user_can( $user, 'manage_options' ) ) {
			lk_sec_log( 'login', $user->user_email );
		}
		return;
	}
	// Senha certa, mas ainda falta o código: desfaz a sessão e pede o código.
	wp_clear_auth_cookie();
	wp_set_current_user( 0 );
	$token = wp_generate_password( 32, false );
	$code  = (string) wp_rand( 100000, 999999 );
	// phpcs:disable WordPress.Security.NonceVerification.Missing -- nonce conferido no formulário de login.
	$back = isset( $_POST['volta'] ) ? esc_url_raw( wp_unslash( $_POST['volta'] ) ) : '';
	$rem  = ! empty( $_POST['lembrar'] ) || ! empty( $_POST['rememberme'] );
	// phpcs:enable
	set_transient(
		'lk_2fa_' . $token,
		array(
			'uid'   => $user->ID,
			'hash'  => wp_hash_password( $code ),
			'tries' => 0,
			'rem'   => $rem,
			'back'  => $back && 0 === strpos( $back, '/' ) ? $back : '',
		),
		10 * MINUTE_IN_SECONDS
	);
	setcookie( 'lk_2fa', $token, time() + 10 * MINUTE_IN_SECONDS, COOKIEPATH ? COOKIEPATH : '/', COOKIE_DOMAIN, is_ssl(), true );
	$sent = lk_2fa_send( $user, $code );
	lk_sec_log( 'codigo', $user->user_email . ( $sent ? '' : ' (falha ao enviar o e-mail)' ) );
	if ( ! $sent ) {
		// Sem e-mail não há código: avisa em vez de deixar a pessoa esperando um código que não vem.
		delete_transient( 'lk_2fa_' . $token );
		setcookie( 'lk_2fa', '', time() - 3600, COOKIEPATH ? COOKIEPATH : '/', COOKIE_DOMAIN, is_ssl(), true );
		update_option( 'lk_2fa_mail_fail', lk_now(), false );
		wp_safe_redirect( add_query_arg( 'aviso', 'email', lk_url( 'entrar' ) ) );
		exit;
	}
	wp_safe_redirect( add_query_arg( 'codigo', '1', lk_url( 'entrar' ) ) );
	exit;
}

function lk_2fa_send( $user, $code ) {
	$intro = '<p>Olá, ' . esc_html( $user->first_name ? $user->first_name : $user->display_name ) . '! Use o código abaixo para entrar no painel. Ele vale por 10 minutos.</p>'
		. '<p style="font-size:34px;font-weight:bold;letter-spacing:8px;margin:18px 0;">' . esc_html( $code ) . '</p>'
		. '<p style="font-size:13px;color:#737373;">Pedido feito do IP ' . esc_html( lk_client_ip() ) . ' em ' . esc_html( lk_date( lk_now(), 'd/m/Y \à\s H:i' ) ) . '. Se não foi você, troque a sua senha agora.</p>';
	return function_exists( 'lk_mail' ) ? lk_mail( $user->user_email, 'Código de acesso: ' . $code, 'Seu código de acesso', $intro ) : wp_mail( $user->user_email, 'Código de acesso: ' . $code, 'Código: ' . $code );
}

function lk_2fa_pending() {
	if ( empty( $_COOKIE['lk_2fa'] ) ) {
		return null;
	}
	$token = preg_replace( '/[^A-Za-z0-9]/', '', sanitize_text_field( wp_unslash( $_COOKIE['lk_2fa'] ) ) );
	$data  = get_transient( 'lk_2fa_' . $token );
	return is_array( $data ) ? array( $token, $data ) : null;
}

add_action(
	'template_redirect',
	function () {
		if ( 'login' !== get_query_var( 'lk_route' ) || ! isset( $_GET['codigo'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification
			return;
		}
		nocache_headers();
		$pending = lk_2fa_pending();
		if ( ! $pending ) {
			wp_safe_redirect( add_query_arg( 'aviso', 'expirou', lk_url( 'entrar' ) ) );
			exit;
		}
		list( $token, $data ) = $pending;
		$user                 = get_userdata( $data['uid'] );
		if ( ! $user ) {
			wp_safe_redirect( lk_url( 'entrar' ) );
			exit;
		}
		$GLOBALS['lk_2fa_email'] = $user->user_email;
		if ( 'POST' === ( isset( $_SERVER['REQUEST_METHOD'] ) ? $_SERVER['REQUEST_METHOD'] : '' ) ) { // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
			if ( ! isset( $_POST['_wpnonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['_wpnonce'] ) ), 'lk_2fa' ) ) {
				$GLOBALS['lk_login_error'] = 'Sessão expirada. Tente de novo.';
			} else {
				$code = isset( $_POST['code'] ) ? preg_replace( '/\D/', '', sanitize_text_field( wp_unslash( $_POST['code'] ) ) ) : '';
				if ( $code && wp_check_password( $code, $data['hash'] ) ) {
					delete_transient( 'lk_2fa_' . $token );
					setcookie( 'lk_2fa', '', time() - 3600, COOKIEPATH ? COOKIEPATH : '/', COOKIE_DOMAIN, is_ssl(), true );
					define( 'LK_2FA_PASSED', true );
					wp_set_auth_cookie( $user->ID, ! empty( $data['rem'] ) );
					wp_set_current_user( $user->ID );
					if ( ! empty( $_POST['confiar'] ) ) {
						lk_trust_device( $user );
					}
					do_action( 'wp_login', $user->user_login, $user );
					wp_safe_redirect( $data['back'] ? home_url( $data['back'] ) : lk_home_for( $user->ID ) );
					exit;
				}
				++$data['tries'];
				if ( $data['tries'] >= 5 ) {
					delete_transient( 'lk_2fa_' . $token );
					do_action( 'wp_login_failed', $user->user_login, new WP_Error( 'lk_2fa' ) );
					lk_sec_log( 'codigo errado', $user->user_email . ' (5 vezes)' );
					wp_safe_redirect( add_query_arg( 'aviso', 'tentativas', lk_url( 'entrar' ) ) );
					exit;
				}
				set_transient( 'lk_2fa_' . $token, $data, 10 * MINUTE_IN_SECONDS );
				$GLOBALS['lk_login_error'] = 'Código incorreto. Confira o e-mail e tente de novo.';
			}
		}
		lk_render( 'login-code' );
	},
	0
);

/* -----------------------------------------------------------------------
 * Portas que o painel não usa
 * -------------------------------------------------------------------- */

add_filter( 'xmlrpc_enabled', '__return_false' );
add_filter( 'wp_is_application_passwords_available', '__return_false' );
add_filter( 'xmlrpc_methods', '__return_empty_array' );
remove_action( 'wp_head', 'wp_generator' );
remove_action( 'wp_head', 'rsd_link' );
remove_action( 'wp_head', 'wlwmanifest_link' );
if ( ! defined( 'DISALLOW_FILE_EDIT' ) ) {
	define( 'DISALLOW_FILE_EDIT', true );
}

// Pedidos diretos ao xmlrpc.php: responde "proibido" antes de qualquer coisa.
if ( defined( 'XMLRPC_REQUEST' ) && XMLRPC_REQUEST ) {
	status_header( 403 );
	exit;
}

/**
 * API do WordPress (wp/v2 e outras): só para o administrador.
 * O painel usa apenas lk/v1, que tem permissões próprias (e os webhooks da Meta e da InfinitePay).
 */
add_filter(
	'rest_pre_dispatch',
	function ( $result, $server, $request ) {
		$route = $request->get_route();
		if ( 0 === strpos( $route, '/lk/v1' ) || '/' === $route || 0 === strpos( $route, '/oembed/' ) ) {
			return $result;
		}
		if ( current_user_can( 'manage_options' ) ) {
			return $result;
		}
		return new WP_Error( 'rest_forbidden', 'Não disponível.', array( 'status' => 401 ) );
	},
	10,
	3
);

// Sem listagem de rotas e usuários no índice da API para visitantes.
add_filter(
	'rest_index',
	function ( $response ) {
		if ( ! current_user_can( 'manage_options' ) ) {
			$response->set_data( array( 'name' => get_bloginfo( 'name' ) ) );
		}
		return $response;
	}
);

// Descobrir o nome de usuário pelo ?author=1.
add_action(
	'template_redirect',
	function () {
		if ( ! is_user_logged_in() && ( isset( $_GET['author'] ) || is_author() ) ) { // phpcs:ignore WordPress.Security.NonceVerification
			wp_safe_redirect( home_url( '/' ), 301 );
			exit;
		}
	},
	-1
);

// Mensagem de erro do login igual para usuário inexistente e senha errada.
add_filter(
	'login_errors',
	function ( $error ) {
		$action = isset( $_REQUEST['action'] ) ? sanitize_key( $_REQUEST['action'] ) : 'login'; // phpcs:ignore WordPress.Security.NonceVerification
		if ( 'login' !== $action || false !== strpos( (string) $error, 'Muitas tentativas' ) ) {
			return $error;
		}
		return 'E-mail ou senha incorretos.';
	}
);

/* -----------------------------------------------------------------------
 * Cabeçalhos de proteção
 * -------------------------------------------------------------------- */

add_action(
	'send_headers',
	function () {
		if ( headers_sent() ) {
			return;
		}
		// O painel não pode ser aberto dentro de outro site (golpe de "clique falso").
		if ( ! defined( 'LK_ALLOW_FRAME' ) ) {
			header( 'X-Frame-Options: SAMEORIGIN' );
		}
		header( 'X-Content-Type-Options: nosniff' );
		// Links com token (proposta, pagamento, entrega, convite) não vazam para outros sites.
		header( 'Referrer-Policy: same-origin' );
		header( 'Permissions-Policy: camera=(), microphone=(self), geolocation=(), payment=()' ); // microfone: áudio no chat da equipe
		if ( is_ssl() ) {
			header( 'Strict-Transport-Security: max-age=31536000' );
		}
	}
);

/* -----------------------------------------------------------------------
 * Arquivos enviados: nome imprevisível e pastas sem listagem
 * -------------------------------------------------------------------- */

add_filter(
	'wp_handle_upload_prefilter',
	function ( $file ) {
		if ( ! empty( $file['name'] ) && ! preg_match( '/^[a-z0-9]{10}-/', $file['name'] ) ) {
			$file['name'] = strtolower( wp_generate_password( 10, false ) ) . '-' . $file['name'];
		}
		return $file;
	}
);

/**
 * Pasta sem arquivo index = o servidor pode listar tudo o que tem nela.
 * Coloca um index.php vazio na pasta principal e em cada pasta que recebe arquivos.
 */
function lk_silence_dir( $dir ) {
	$dir = trailingslashit( $dir );
	if ( is_dir( $dir ) && ! file_exists( $dir . 'index.php' ) && wp_is_writable( $dir ) ) {
		file_put_contents( $dir . 'index.php', "<?php\n// Silêncio.\n" ); // phpcs:ignore WordPress.WP.AlternativeFunctions
	}
}

add_filter(
	'wp_handle_upload',
	function ( $upload ) {
		if ( ! empty( $upload['file'] ) ) {
			lk_silence_dir( dirname( $upload['file'] ) );
			lk_silence_dir( dirname( dirname( $upload['file'] ) ) );
		}
		return $upload;
	}
);

function lk_protect_uploads() {
	$up = wp_upload_dir();
	lk_silence_dir( $up['basedir'] );
	lk_silence_dir( $up['path'] );
	foreach ( (array) glob( trailingslashit( $up['basedir'] ) . '[0-9][0-9][0-9][0-9]', GLOB_ONLYDIR ) as $year ) {
		lk_silence_dir( $year );
		foreach ( (array) glob( $year . '/[0-9][0-9]', GLOB_ONLYDIR ) as $month ) {
			lk_silence_dir( $month );
		}
	}
}

/* -----------------------------------------------------------------------
 * Atualização: papéis sem permissões desnecessárias
 * -------------------------------------------------------------------- */

function lk_security_upgrade() {
	foreach ( array( 'lk_client', 'lk_team' ) as $r ) {
		$role = get_role( $r );
		if ( $role && $role->has_cap( 'upload_files' ) ) {
			$role->remove_cap( 'upload_files' );
		}
	}
	lk_protect_uploads();
}

/**
 * Checklist mostrado em Configurações → Segurança.
 */
function lk_security_checks() {
	$admins = get_users( array( 'role' => 'administrator', 'fields' => array( 'user_login' ) ) );
	$weak   = array_filter(
		wp_list_pluck( $admins, 'user_login' ),
		function ( $l ) {
			return in_array( strtolower( $l ), array( 'admin', 'administrator', 'administrador', 'root', 'test' ), true );
		}
	);
	return array(
		array( is_ssl(), 'Site em HTTPS (cadeado)', 'Ative o SSL na hospedagem e force https.' ),
		array( ! ( defined( 'LK_DISABLE_2FA' ) && LK_DISABLE_2FA ) && '0' !== (string) lk_setting( 'seg_2fa' ), 'Código por e-mail no login da equipe', 'Ligue em "Verificação em duas etapas" abaixo.' ),
		array( ! $weak, 'Nenhum administrador com usuário óbvio ("admin")', 'Crie outro administrador com o seu e-mail como usuário e exclua o "' . implode( ', ', $weak ) . '".' ),
		array( ! ( defined( 'WP_DEBUG_DISPLAY' ) && WP_DEBUG_DISPLAY && defined( 'WP_DEBUG' ) && WP_DEBUG ), 'Erros do PHP escondidos dos visitantes', 'Em wp-config.php deixe WP_DEBUG como false.' ),
		array( (bool) lk_setting( 'smtp_host' ), 'E-mail SMTP configurado (os códigos de acesso chegam)', 'Configure na seção 02a.' ),
		array( ! get_option( 'users_can_register' ), 'Cadastro público do WordPress desligado', 'Configurações → Geral → desmarque "Qualquer pessoa pode se registrar".' ),
	);
}

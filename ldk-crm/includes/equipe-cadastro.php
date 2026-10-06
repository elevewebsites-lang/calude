<?php
/**
 * Cadastro da equipe pelo link + aprovação do admin, e "Minha conta" (dados e senha).
 *
 *  - /equipe-cadastro/<token>/  a pessoa preenche os dados e cria a senha. Fica PENDENTE.
 *  - Equipe → "Pedidos de cadastro": o admin aprova (vira usuário da equipe com a função escolhida) ou recusa.
 *    Se o link vazar, é só gerar um novo (o antigo para de funcionar) e recusar os pedidos estranhos.
 *  - /painel/conta/  cada pessoa da equipe troca nome, telefone e senha.
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_action(
	'init',
	function () {
		add_rewrite_rule( '^equipe-cadastro/([A-Za-z0-9]+)/?$', 'index.php?lk_route=team_join&lk_token=$matches[1]', 'top' );
	}
);

function lk_team_join_token() {
	$t = (string) get_option( 'lk_team_join_token' );
	if ( ! $t ) {
		$t = strtolower( wp_generate_password( 24, false ) );
		update_option( 'lk_team_join_token', $t, false );
	}
	return $t;
}

function lk_team_join_url() {
	return lk_url( 'equipe-cadastro/' . lk_team_join_token() );
}

function lk_do_team_join_new_link() {
	lk_require( 'admin' );
	update_option( 'lk_team_join_token', strtolower( wp_generate_password( 24, false ) ), false );
	lk_back( 'Link de cadastro da equipe trocado. O anterior parou de funcionar.' );
}

add_action(
	'template_redirect',
	function () {
		if ( 'team_join' !== get_query_var( 'lk_route' ) ) {
			return;
		}
		nocache_headers();
		header( 'X-Robots-Tag: noindex, nofollow', true );
		$ok  = hash_equals( lk_team_join_token(), (string) get_query_var( 'lk_token' ) );
		$msg = '';
		$err = '';
		if ( $ok && 'POST' === ( isset( $_SERVER['REQUEST_METHOD'] ) ? $_SERVER['REQUEST_METHOD'] : '' ) ) { // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
			$res = lk_team_join_submit();
			if ( is_wp_error( $res ) ) {
				$err = $res->get_error_message();
			} else {
				$msg = $res;
			}
		}
		lk_render( 'public/equipe-cadastro', array( 'ok' => $ok, 'msg' => $msg, 'err' => $err ) );
	},
	0
);

function lk_team_join_submit() {
	if ( ! isset( $_POST['_wpnonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['_wpnonce'] ) ), 'lk_team_join' ) ) {
		return new WP_Error( 'lk', 'Sessão expirada. Recarregue a página.' );
	}
	if ( lk_in( 'site_url_hp' ) ) {
		return 'Cadastro enviado.'; // Armadilha para robôs (campo escondido).
	}
	$ip  = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '';
	$key = 'lk_tj_' . md5( $ip );
	if ( (int) get_transient( $key ) >= 5 ) {
		return new WP_Error( 'lk', 'Muitos cadastros deste endereço. Tente mais tarde.' );
	}
	$name  = lk_in( 'name' );
	$email = lk_in( 'email', 'email' );
	$pass  = (string) lk_in( 'senha', 'raw' );
	if ( ! $name || ! is_email( $email ) ) {
		return new WP_Error( 'lk', 'Preencha o nome e um e-mail válido.' );
	}
	if ( strlen( $pass ) < 8 || $pass !== (string) lk_in( 'senha2', 'raw' ) ) {
		return new WP_Error( 'lk', 'A senha precisa ter 8 caracteres ou mais e as duas precisam ser iguais.' );
	}
	if ( email_exists( $email ) || lk_rows( 'team_requests', 'email = %s AND status = %s', array( $email, 'pendente' ) ) ) {
		return new WP_Error( 'lk', 'Este e-mail já tem cadastro ou pedido em análise.' );
	}
	$func = isset( lk_team_roles()[ lk_in( 'funcao' ) ] ) && ! empty( lk_team_roles()[ lk_in( 'funcao' ) ][3] ) ? lk_in( 'funcao' ) : 'outro'; // cargos de chefia só o admin escolhe, na aprovação
	lk_insert(
		'team_requests',
		array(
			'name'      => $name,
			'email'     => $email,
			'phone'     => lk_in( 'phone' ),
			'funcao'    => $func,
			'cpf'       => lk_in( 'cpf' ),
			'pix'       => lk_in( 'pix' ),
			'address'   => lk_in( 'address' ),
			'birthday'  => lk_in( 'birthday', 'date' ) ? lk_in( 'birthday', 'date' ) : null,
			'notes'     => lk_in( 'notes', 'textarea' ),
			'pass_hash' => wp_hash_password( $pass ),
			'ip'        => $ip,
		)
	);
	set_transient( $key, (int) get_transient( $key ) + 1, HOUR_IN_SECONDS );
	foreach ( get_users( array( 'role' => 'administrator', 'fields' => 'ID' ) ) as $admin ) {
		lk_notify( (int) $admin, '🙋 Pedido de cadastro na equipe: ' . $name . ' (' . lk_team_roles()[ $func ][0] . '). Aprove ou recuse em Equipe.', lk_panel_url( 'equipe' ) . '#pedidos' );
	}
	return 'Cadastro enviado! Assim que a ' . lk_setting( 'empresa' ) . ' aprovar, você recebe um e-mail e já pode entrar com o seu e-mail e a senha que criou.';
}

function lk_do_team_request_approve() {
	lk_require( 'admin' );
	global $wpdb;
	$r = lk_get( 'team_requests', lk_in( 'id', 'int' ) );
	if ( ! $r || 'pendente' !== $r->status ) {
		lk_back( 'Pedido não encontrado.', 'erro' );
	}
	if ( email_exists( $r->email ) ) {
		lk_back( 'Já existe um usuário com este e-mail.', 'erro' );
	}
	$func    = isset( lk_team_roles()[ lk_in( 'funcao' ) ] ) ? lk_in( 'funcao' ) : $r->funcao;
	$user_id = wp_insert_user(
		array(
			'user_login'   => $r->email,
			'user_email'   => $r->email,
			'user_pass'    => wp_generate_password( 32 ),
			'display_name' => $r->name,
			'first_name'   => $r->name,
			'role'         => 'lk_team',
		)
	);
	if ( is_wp_error( $user_id ) ) {
		lk_back( $user_id->get_error_message(), 'erro' );
	}
	// A senha é a que a pessoa criou no cadastro (guardada só como hash).
	$wpdb->update( $wpdb->users, array( 'user_pass' => $r->pass_hash ), array( 'ID' => $user_id ) );
	clean_user_cache( $user_id );
	update_user_meta( $user_id, 'lk_func', $func );
	update_user_meta( $user_id, 'lk_perms', lk_role_areas( $func ) );
	update_user_meta( $user_id, 'lk_phone', $r->phone );
	update_user_meta( $user_id, 'lk_cpf', $r->cpf );
	update_user_meta( $user_id, 'lk_pix', $r->pix );
	update_user_meta( $user_id, 'lk_address', $r->address );
	if ( $r->birthday ) {
		update_user_meta( $user_id, 'lk_birthday', $r->birthday );
	}
	lk_update( 'team_requests', $r->id, array( 'status' => 'aprovado', 'decided_by' => get_current_user_id(), 'decided_at' => lk_now(), 'user_id' => $user_id, 'pass_hash' => '' ) );
	lk_mail( $r->email, 'Seu acesso à ' . lk_setting( 'empresa' ) . ' foi liberado', 'Bem-vindo(a) à equipe!', '<p>Olá, ' . esc_html( strtok( $r->name, ' ' ) ) . '! Seu cadastro foi aprovado. Entre com o seu e-mail e a senha que você criou.</p>', array(), 'Entrar no painel', lk_url( 'entrar' ) );
	lk_back( $r->name . ' agora faz parte da equipe (' . lk_team_roles()[ $func ][0] . '). Ajuste as permissões se precisar.' );
}

function lk_do_team_request_reject() {
	lk_require( 'admin' );
	$r = lk_get( 'team_requests', lk_in( 'id', 'int' ) );
	if ( $r ) {
		lk_update( 'team_requests', $r->id, array( 'status' => 'recusado', 'decided_by' => get_current_user_id(), 'decided_at' => lk_now(), 'pass_hash' => '' ) );
	}
	lk_back( 'Pedido recusado.' );
}

/**
 * Bloco "Pedidos de cadastro" + link, para a tela Equipe.
 */
function lk_team_requests_html() {
	$pend = lk_rows( 'team_requests', 'status = %s', array( 'pendente' ), 'id DESC' );
	ob_start();
	?>
	<section class="card" id="pedidos">
		<div class="card-head"><h3>Cadastro da equipe pelo link</h3><span class="muted small">a pessoa preenche tudo e cria a senha; só entra depois que você aprovar</span></div>
		<div class="copy-row"><input type="text" readonly value="<?php echo esc_attr( lk_team_join_url() ); ?>" onclick="this.select()"><button type="button" class="btn btn--ghost" data-copy="<?php echo esc_attr( lk_team_join_url() ); ?>"><?php echo lk_icon( 'copiar', 16 ); // phpcs:ignore ?><span>Copiar link</span></button><a class="btn btn--wa" href="<?php echo esc_url( 'https://wa.me/?text=' . rawurlencode( 'Oi! Faça o seu cadastro na equipe da ' . lk_setting( 'empresa' ) . ' por aqui: ' . lk_team_join_url() ) ); ?>" target="_blank" rel="noopener"><?php echo lk_icon( 'whatsapp', 16 ); // phpcs:ignore ?><span>WhatsApp</span></a></div>
		<p class="muted small" style="margin-top:8px">O link vazou? <?php lk_action_button( 'team_join_new_link', array(), 'Gerar um link novo', 'btn btn--link btn--sm', 'Gerar um link novo? O atual para de funcionar.' ); ?></p>
		<?php if ( $pend ) : ?>
			<h4 style="margin:16px 0 8px">Esperando aprovação (<?php echo count( $pend ); ?>)</h4>
			<div class="table">
				<?php foreach ( $pend as $r ) : ?>
					<div class="table-row team-req">
						<span class="cell-main"><strong><?php echo esc_html( $r->name ); ?></strong><small class="muted"><?php echo esc_html( $r->email . ( $r->phone ? ' · ' . $r->phone : '' ) ); ?></small><small class="muted"><?php echo esc_html( trim( ( $r->cpf ? 'CPF ' . $r->cpf : '' ) . ( $r->pix ? ' · Pix ' . $r->pix : '' ) . ' · ' . lk_ago( $r->created_at ), ' ·' ) ); ?></small><?php echo $r->notes ? '<small>' . esc_html( $r->notes ) . '</small>' : ''; ?></span>
						<span>
							<?php lk_form( 'team_request_approve', 'inline-form' ); ?>
								<input type="hidden" name="id" value="<?php echo (int) $r->id; ?>">
								<select name="funcao"><?php foreach ( lk_team_roles() as $k => $v ) : ?><option value="<?php echo esc_attr( $k ); ?>"<?php selected( $k, $r->funcao ); ?>><?php echo esc_html( $v[0] ); ?></option><?php endforeach; ?></select>
								<button type="submit" class="btn btn--primary btn--sm">Aprovar</button>
							</form>
							<?php lk_action_button( 'team_request_reject', array( 'id' => $r->id ), 'Recusar', 'btn btn--danger btn--sm', 'Recusar o cadastro de ' . $r->name . '?' ); ?>
						</span>
					</div>
				<?php endforeach; ?>
			</div>
		<?php endif; ?>
	</section>
	<?php
	return ob_get_clean();
}

/**
 * Minha conta (equipe): nome, telefone e senha.
 */
function lk_do_my_account() {
	if ( ! lk_is_team() ) {
		wp_die( 'Sem permissão.' );
	}
	$u    = wp_get_current_user();
	$data = array( 'ID' => $u->ID );
	$name = lk_in( 'name' );
	if ( $name ) {
		$data['display_name'] = $name;
		$data['first_name']   = $name;
	}
	update_user_meta( $u->ID, 'lk_phone', lk_in( 'phone' ) );
	update_user_meta( $u->ID, 'lk_pix', lk_in( 'pix' ) );
	$new = (string) lk_in( 'senha', 'raw' );
	if ( '' !== $new ) {
		if ( ! wp_check_password( (string) lk_in( 'senha_atual', 'raw' ), $u->user_pass, $u->ID ) ) {
			lk_back( 'A senha atual não confere.', 'erro' );
		}
		if ( strlen( $new ) < 8 || $new !== (string) lk_in( 'senha2', 'raw' ) ) {
			lk_back( 'A nova senha precisa ter 8 caracteres ou mais e as duas precisam ser iguais.', 'erro' );
		}
		$data['user_pass'] = $new;
	}
	wp_update_user( $data );
	if ( isset( $data['user_pass'] ) ) {
		wp_set_auth_cookie( $u->ID, true ); // Continua logado depois de trocar a senha.
	}
	lk_back( isset( $data['user_pass'] ) ? 'Dados e senha atualizados.' : 'Dados atualizados.' );
}

<?php
/**
 * Link de conexão para o cliente: ele abre, entra no Instagram/Facebook dele e autoriza.
 * Não precisa de login no CRM nem de senha dele com a gente. A conta aparece vinculada na ficha do cliente.
 *
 * Endereço: admin-post.php?action=lk_connect&c=<id>&t=<token>. O token é sorteado por cliente e pode ser trocado.
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Redes que o cliente conecta pelo link => [rótulo, dica]. */
function lk_connect_nets() {
	return array(
		'instagram' => array( 'Instagram', 'Conta Comercial ou Criador de conteúdo.' ),
		'facebook'  => array( 'Facebook (Página)', 'Você precisa ser administrador da Página.' ),
		'linkedin'  => array( 'LinkedIn (Página)', 'Você precisa ser administrador da Página da empresa.' ),
		'youtube'   => array( 'YouTube', 'Entre com a conta Google dona do canal.' ),
	);
}

function lk_connect_token( $cid, $reset = false ) {
	$all = (array) get_option( 'lk_connect_tokens', array() );
	if ( $reset || empty( $all[ $cid ] ) ) {
		$all[ $cid ] = wp_generate_password( 32, false );
		update_option( 'lk_connect_tokens', $all, false );
	}
	return $all[ $cid ];
}

function lk_connect_url( $cid ) {
	return add_query_arg( array( 'action' => 'lk_connect', 'c' => (int) $cid, 't' => lk_connect_token( $cid ) ), admin_url( 'admin-post.php' ) );
}

function lk_do_connect_reset() {
	lk_require( 'clientes' );
	$cid = lk_in( 'client_id', 'int' );
	lk_connect_token( $cid, true );
	lk_back( 'Link novo gerado. O anterior parou de funcionar.', 'ok', lk_panel_url( 'cliente', $cid ) . '#redes' );
}

/** Confere cliente + token; encerra com 404 genérico se não bater. */
function lk_connect_client() {
	$cid = absint( $_GET['c'] ?? $_POST['c'] ?? 0 ); // phpcs:ignore WordPress.Security.NonceVerification
	$tok = sanitize_text_field( wp_unslash( $_GET['t'] ?? $_POST['t'] ?? '' ) ); // phpcs:ignore WordPress.Security.NonceVerification
	$all = (array) get_option( 'lk_connect_tokens', array() );
	$c   = $cid ? lk_get( 'clients', $cid ) : null;
	if ( ! $c || empty( $all[ $cid ] ) || ! hash_equals( (string) $all[ $cid ], $tok ) ) {
		status_header( 404 );
		wp_die( 'Link inválido ou expirado. Peça um novo para a equipe.', 'Link inválido', array( 'response' => 404 ) );
	}
	return $c;
}

add_action( 'admin_post_nopriv_lk_connect', 'lk_connect_page' );
add_action( 'admin_post_lk_connect', 'lk_connect_page' );
function lk_connect_page() {
	$c = lk_connect_client();
	$go = sanitize_key( $_GET['go'] ?? '' ); // phpcs:ignore WordPress.Security.NonceVerification
	if ( isset( lk_connect_nets()[ $go ] ) ) {
		$st = wp_generate_password( 24, false );
		set_transient( 'lk_soc_' . $st, array( 'client' => (int) $c->id, 'user' => 0, 'net' => $go, 'public' => 1 ), 900 );
		$url = lk_social_auth_url( $go, $st );
		if ( is_wp_error( $url ) ) {
			lk_connect_done( (int) $c->id, 'Ainda não está pronto do nosso lado. Avise a equipe.', 'erro' );
		}
		wp_redirect( $url ); // phpcs:ignore WordPress.Security.SafeRedirect -- login da Meta.
		exit;
	}
	$msg   = get_transient( 'lk_ccmsg_' . $c->id );
	$accs  = lk_social_accounts( $c->id );
	$picks = array();
	$fbp   = json_decode( (string) lk_decrypt( (string) get_transient( 'lk_fbpages_' . $c->id ) ), true );
	if ( $fbp ) {
		$picks['facebook'] = $fbp;
	}
	foreach ( lk_social_pick_pending( $c->id ) as $pn => $opts ) {
		$picks[ $pn ] = $opts;
	}
	$base  = array( 'action' => 'lk_connect', 'c' => (int) $c->id, 't' => lk_connect_token( $c->id ) );
	$nome  = lk_identity()['nome'] ?? get_bloginfo( 'name' );
	nocache_headers();
	header( 'Content-Type: text/html; charset=utf-8' );
	header( 'X-Robots-Tag: noindex, nofollow' );
	header( 'Referrer-Policy: no-referrer' ); // o token está na URL: não vaza para a Meta
	?>
<!doctype html><html lang="pt-BR"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="robots" content="noindex,nofollow"><title>Conectar redes sociais · <?php echo esc_html( $nome ); ?></title>
<style>body{margin:0;font:16px/1.5 system-ui,sans-serif;background:#f6f5f2;color:#111;display:flex;min-height:100vh;align-items:center;justify-content:center;padding:16px}main{background:#fff;border-radius:18px;padding:28px;max-width:440px;width:100%;box-shadow:0 8px 30px rgba(0,0,0,.07)}h1{font-size:22px;margin:0 0 6px}p{color:#555;margin:0 0 18px}.row{display:flex;align-items:center;justify-content:space-between;gap:12px;border:1px solid #e5e3de;border-radius:12px;padding:14px;margin-bottom:10px}.row small{display:block;color:#777}.ok{color:#127a3a}a.btn,button{background:#111;color:#fff;border:0;border-radius:999px;padding:10px 18px;font-size:15px;text-decoration:none;cursor:pointer}a.btn.ghost{background:#eee;color:#111}.msg{padding:12px 14px;border-radius:10px;margin-bottom:16px;background:#e8f5ec;color:#0d5c2b}.msg.erro{background:#fdeaea;color:#8a1c1c}select{width:100%;padding:10px;margin:8px 0;border-radius:10px;border:1px solid #ccc}.foot{font-size:13px;color:#888;margin-top:16px}</style></head><body><main>
<h1>Conectar suas redes sociais</h1>
<p>Olá, <?php echo esc_html( lk_client_label( $c ) ); ?>! Para a <?php echo esc_html( $nome ); ?> postar por você, entre na sua conta e autorize. Você não nos passa a senha: o login é feito direto no Instagram/Facebook.</p>
<?php if ( $msg ) : ?><div class="msg <?php echo 'erro' === $msg[1] ? 'erro' : ''; ?>"><?php echo esc_html( $msg[0] ); ?></div><?php endif; ?>
<?php foreach ( lk_connect_nets() as $n => $info ) : $lab = $info[0]; $a = $accs[ $n ] ?? null; ?>
	<div class="row"><span><strong><?php echo esc_html( $lab ); ?></strong><small><?php echo esc_html( $info[1] ); ?></small><small class="<?php echo $a && 'ok' === $a->status ? 'ok' : ''; ?>"><?php echo $a && 'ok' === $a->status ? '✓ conectado' . ( $a->username ? ' · @' . esc_html( $a->username ) : ( $a->name ? ' · ' . esc_html( $a->name ) : '' ) ) : 'não conectado'; ?></small></span>
	<a class="btn<?php echo $a && 'ok' === $a->status ? ' ghost' : ''; ?>" href="<?php echo esc_url( add_query_arg( 'go', $n, admin_url( 'admin-post.php' ) . '?' . http_build_query( $base ) ) ); ?>"><?php echo $a && 'ok' === $a->status ? 'Reconectar' : 'Conectar'; ?></a></div>
<?php endforeach; ?>
<?php foreach ( $picks as $pn => $opts ) : ?>
	<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>"><input type="hidden" name="action" value="lk_connect_pick"><input type="hidden" name="c" value="<?php echo (int) $c->id; ?>"><input type="hidden" name="t" value="<?php echo esc_attr( $base['t'] ); ?>"><input type="hidden" name="net" value="<?php echo esc_attr( $pn ); ?>">
	<strong>Qual é o seu <?php echo esc_html( lk_networks()[ $pn ] ?? $pn ); ?>?</strong><select name="choice"><?php foreach ( $opts as $o ) : ?><option value="<?php echo esc_attr( $o['id'] ); ?>"><?php echo esc_html( $o['name'] ); ?></option><?php endforeach; ?></select><button type="submit">Usar este</button></form>
<?php endforeach; ?>
<p class="foot">Dúvidas? Fale com a gente.</p>
</main></body></html>
	<?php
	exit;
}

add_action( 'admin_post_nopriv_lk_connect_pick', 'lk_connect_pick' );
add_action( 'admin_post_lk_connect_pick', 'lk_connect_pick' );
function lk_connect_pick() {
	$c    = lk_connect_client();
	$net  = sanitize_key( $_POST['net'] ?? '' ); // phpcs:ignore WordPress.Security.NonceVerification -- o token do link autentica.
	$want = sanitize_text_field( wp_unslash( $_POST['choice'] ?? '' ) ); // phpcs:ignore WordPress.Security.NonceVerification
	if ( 'facebook' === $net ) {
		$pages = json_decode( (string) lk_decrypt( (string) get_transient( 'lk_fbpages_' . $c->id ) ), true );
		foreach ( (array) $pages as $pg ) {
			if ( (string) $pg['id'] === $want ) {
				lk_social_store( (int) $c->id, 'facebook', $pg['id'], '', $pg['name'], $pg['picture']['data']['url'] ?? '', $pg['access_token'], 0 );
				delete_transient( 'lk_fbpages_' . $c->id );
				lk_connect_done( (int) $c->id, 'Página "' . $pg['name'] . '" conectada.', 'ok' );
			}
		}
	} elseif ( in_array( $net, lk_social_more_nets(), true ) ) {
		$raw = get_transient( 'lk_pick_' . $net . '_' . $c->id );
		$d   = $raw ? json_decode( (string) lk_decrypt( $raw ), true ) : null;
		foreach ( (array) ( $d['opts'] ?? array() ) as $o ) {
			if ( (string) $o['id'] === $want ) {
				$extra = 'linkedin' === $net ? lk_li_extra_encrypt( $o['extra'] ) : $o['extra'];
				lk_social_store( (int) $c->id, $net, $o['id'], $o['user'], $o['name'], $o['avatar'], $d['token'], $d['expires'] ?? 0, $extra );
				delete_transient( 'lk_pick_' . $net . '_' . $c->id );
				lk_connect_done( (int) $c->id, ( lk_networks()[ $net ] ?? $net ) . ': "' . $o['name'] . '" conectado.', 'ok' );
			}
		}
	}
	lk_connect_done( (int) $c->id, 'Não encontrei essa opção. Conecte de novo.', 'erro' );
}

/** Volta para a página pública do cliente com o resultado. */
function lk_connect_done( $cid, $msg, $type = 'ok' ) {
	set_transient( 'lk_ccmsg_' . $cid, array( $msg, $type ), 300 );
	wp_safe_redirect( lk_connect_url( $cid ) );
	exit;
}

/* -----------------------------------------------------------------------
 * Vigia diário: conta caída ou perto de vencer → avisa o cliente (com o link) e a equipe
 * -------------------------------------------------------------------- */

add_action( 'lk_daily', 'lk_connect_watch' );
function lk_connect_watch() {
	$sent  = (array) get_option( 'lk_connect_alerts', array() );
	$limit = gmdate( 'Y-m-d H:i:s', time() + 5 * DAY_IN_SECONDS );
	$team  = array();
	// As redes que o link do cliente reconecta.
	foreach ( lk_rows( 'social_accounts', "network IN ('instagram','facebook','linkedin','youtube') AND ( status <> 'ok' OR ( expires_at IS NOT NULL AND expires_at < %s ) )", array( $limit ) ) as $a ) {
		if ( ! empty( $sent[ $a->id ] ) && $sent[ $a->id ] > time() - 5 * DAY_IN_SECONDS ) {
			continue; // já avisou há pouco
		}
		$c = lk_get( 'clients', $a->client_id );
		if ( ! $c ) {
			continue;
		}
		$rede = lk_networks()[ $a->network ] ?? $a->network;
		if ( $c->email && ! $c->email_optout ) {
			lk_mail(
				$c->email,
				'Reconecte seu ' . $rede . ' para continuarmos postando',
				'Precisamos reconectar seu ' . $rede,
				'A conexão do seu ' . $rede . ( $a->username ? ' (@' . esc_html( $a->username ) . ')' : '' ) . ' com a ' . esc_html( lk_setting( 'empresa' ) ) . ' venceu ou foi interrompida. Sem ela, as publicações agendadas não saem. Leva menos de um minuto: é só entrar na sua conta e autorizar.',
				array(),
				'Reconectar agora',
				lk_connect_url( $c->id )
			);
		}
		$team[] = lk_client_label( $c ) . ' — ' . $rede . ( $a->error ? ' (' . $a->error . ')' : '' );
		$sent[ $a->id ] = time();
	}
	update_option( 'lk_connect_alerts', $sent, false );
	if ( $team ) {
		lk_mail(
			get_option( 'admin_email' ),
			'Redes sociais para reconectar (' . count( $team ) . ')',
			'Redes para reconectar',
			'Estas contas caíram ou estão perto de vencer. O cliente já recebeu o link por e-mail (quando tem e-mail cadastrado):<br>• ' . implode( '<br>• ', array_map( 'esc_html', $team ) ),
			array(),
			'Abrir clientes',
			lk_panel_url( 'clientes' )
		);
	}
}

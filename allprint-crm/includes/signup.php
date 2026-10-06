<?php
/**
 * Cadastro público de parceiros (/cadastro/) e aprovação pela AllPrint.
 * Antes de aprovar: o cliente entra, vê "cadastro em análise", mas não vê preços nem faz pedidos.
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_action(
	'init',
	function () {
		add_rewrite_rule( '^cadastro/?$', 'index.php?ap_route=signup', 'top' );
	}
);

add_action(
	'template_redirect',
	function () {
		if ( 'signup' !== get_query_var( 'ap_route' ) ) {
			return;
		}
		nocache_headers();
		if ( is_user_logged_in() ) {
			wp_safe_redirect( ap_home_for( get_current_user_id() ) );
			exit;
		}
		ap_handle_signup();
		ap_render( 'public/cadastro' );
	},
	0
);

function ap_handle_signup() {
	if ( 'POST' !== ( isset( $_SERVER['REQUEST_METHOD'] ) ? $_SERVER['REQUEST_METHOD'] : '' ) ) { // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
		return;
	}
	$fail = function ( $m ) {
		$GLOBALS['ap_signup_error'] = $m;
	};
	if ( ! isset( $_POST['_wpnonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['_wpnonce'] ) ), 'ap_signup' ) ) {
		return $fail( 'Sessão expirada. Recarregue a página.' );
	}
	if ( ! empty( $_POST['site_url'] ) ) { // armadilha para robôs
		return $fail( 'Não foi possível concluir.' );
	}
	$ip  = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '';
	$key = 'ap_signup_' . md5( $ip );
	if ( (int) get_transient( $key ) >= 5 ) {
		return $fail( 'Muitas tentativas. Tente de novo mais tarde ou chame no WhatsApp.' );
	}
	set_transient( $key, (int) get_transient( $key ) + 1, HOUR_IN_SECONDS );
	$name  = ap_in( 'name' );
	$email = ap_in( 'email', 'email' );
	$pass  = (string) ap_in( 'senha', 'raw' );
	$doc = preg_replace( '/\D/', '', (string) ap_in( 'cnpj' ) );
	if ( ! $name || ! is_email( $email ) || ! ap_in( 'company' ) || ! ap_in( 'whatsapp' ) ) {
		return $fail( 'Preencha empresa, responsável, telefone e um e-mail válido.' );
	}
	if ( ! in_array( strlen( $doc ), array( 11, 14 ), true ) ) {
		return $fail( 'Informe um CPF (11 dígitos) ou CNPJ (14 dígitos) válido.' );
	}
	if ( strlen( $pass ) < 8 ) {
		return $fail( 'A senha precisa ter pelo menos 8 caracteres.' );
	}
	if ( email_exists( $email ) ) {
		return $fail( 'Este e-mail já tem cadastro. Entre com a sua senha.' );
	}
	$uid = wp_insert_user( array( 'user_login' => $email, 'user_email' => $email, 'user_pass' => $pass, 'display_name' => $name, 'first_name' => $name, 'role' => 'ap_client' ) );
	if ( is_wp_error( $uid ) ) {
		return $fail( 'Não foi possível criar o acesso: ' . $uid->get_error_message() );
	}
	$cid = ap_insert(
		'clients',
		array(
			'user_id'  => $uid,
			'name'     => $name,
			'company'  => ap_in( 'company' ),
			'cnpj'     => ap_in( 'cnpj' ),
			'email'    => $email,
			'whatsapp' => ap_in( 'whatsapp' ),
			'phone'    => ap_in( 'whatsapp' ),
			'address'  => ap_in( 'address' ),
			'source'   => ap_in( 'source' ) ? ap_in( 'source' ) : 'Site',
			'notes'    => ap_in( 'about', 'textarea' ),
			'status'   => 'ativo',
			'approved' => 0,
		)
	);
	ap_insert( 'leads', array( 'name' => $name, 'company' => ap_in( 'company' ), 'email' => $email, 'whatsapp' => ap_in( 'whatsapp' ), 'source' => 'Cadastro no painel', 'stage' => array_keys( ap_funnel() )[1] ?? '', 'client_id' => $cid, 'notes' => ap_in( 'about', 'textarea' ) ) );
	$to = ap_setting( 'email' ) ? ap_setting( 'email' ) : get_option( 'admin_email' );
	if ( is_email( $to ) ) {
		ap_mail( $to, 'Cadastro novo para aprovar: ' . ap_in( 'company' ), 'Cadastro novo para aprovar', '<p>' . esc_html( $name . ' (' . ap_in( 'company' ) . ') se cadastrou no painel e aguarda aprovação para ver os preços e fazer pedidos.' ) . '</p>', array( array( 'Telefone', esc_html( ap_in( 'whatsapp' ) ) ), array( 'CPF/CNPJ', esc_html( ap_in( 'cnpj' ) ) ), array( 'Endereço', esc_html( ap_in( 'address' ) ) ) ), 'Aprovar no painel', ap_panel_url( 'cliente', $cid ) );
	}
	ap_mail( $email, 'Recebemos o seu cadastro · ' . ap_setting( 'empresa' ), 'Recebemos o seu cadastro', '<p>Olá, ' . esc_html( strtok( $name, ' ' ) ) . '! Obrigado pelo interesse em ser parceiro da ' . esc_html( ap_setting( 'empresa' ) ) . '. Vamos analisar os seus dados e te avisamos por e-mail assim que o acesso aos preços e pedidos for liberado.</p>', array(), 'Ver o meu painel', ap_client_url() );
	wp_set_current_user( $uid );
	wp_set_auth_cookie( $uid, true );
	wp_safe_redirect( add_query_arg( 'bemvindo', 1, ap_client_url() ) );
	exit;
}

/**
 * Aprovar / suspender parceiro.
 */
function ap_do_client_approve_partner() {
	ap_require( 'clientes' );
	$c = ap_get( 'clients', ap_in( 'id', 'int' ) );
	if ( ! $c ) {
		ap_back();
	}
	$on = ! $c->approved;
	ap_update( 'clients', $c->id, array( 'approved' => $on ? 1 : 0 ) );
	if ( $on && is_email( $c->email ) ) {
		ap_mail(
			$c->email,
			'Cadastro aprovado! Bem-vindo à ' . ap_setting( 'empresa' ),
			'Seu cadastro foi aprovado 🎉',
			'<p>Olá, ' . esc_html( strtok( (string) $c->name, ' ' ) ) . '! A partir de agora você vê a tabela de preços de parceiros e faz os pedidos direto pelo painel: escolhe o material e as medidas, envia o arquivo e paga no Pix. Você acompanha cada etapa (revisão da arte, produção, acabamento) e recebe um aviso quando estiver pronto para retirar.</p>',
			array( array( 'Prazo normal', 'Até ' . (int) ap_setting( 'prazo_dias' ) . ' dia(s) útil(eis) após a aprovação da arte' ), array( 'Pedido mínimo', '1 m² por material' ), array( 'Arquivos', 'PDF, CDR em curvas (v25) ou JPG 300 dpi' ) ),
			'Fazer meu primeiro pedido',
			ap_client_entry_url( $c, 'novo' )
		);
		$won = ap_funnel_won();
		if ( $won ) {
			global $wpdb;
			$wpdb->update( ap_table( 'leads' ), array( 'stage' => $won ), array( 'client_id' => $c->id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		}
	}
	ap_back( $on ? 'Parceiro aprovado e avisado por e-mail.' : 'Acesso aos preços suspenso.' );
}

function ap_do_client_pay_later() {
	ap_require( 'clientes' );
	$c = ap_get( 'clients', ap_in( 'id', 'int' ) );
	if ( $c ) {
		ap_update( 'clients', $c->id, array( 'pay_later' => $c->pay_later ? 0 : 1 ) );
	}
	ap_back( 'Atualizado.' );
}

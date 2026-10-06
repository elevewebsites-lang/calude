<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_action(
	'rest_api_init',
	function () {
		register_rest_route(
			'ldk-site/v1',
			'/lead',
			array(
				'methods'             => 'POST',
				'permission_callback' => '__return_true',
				'callback'            => 'ldk_site_lead',
			)
		);
	}
);

/** Recebe o formulário do site e repassa ao CRM (a chave fica só no servidor). */
function ldk_site_lead( WP_REST_Request $r ) {
	$p = $r->get_json_params();
	$p = is_array( $p ) ? $p : array();
	// Anti-spam: campo isca e tempo mínimo de preenchimento.
	if ( ! empty( $p['website'] ) || ( isset( $p['_t'] ) && (int) $p['_t'] < 2500 ) ) {
		return array( 'ok' => true );
	}
	$ip   = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '';
	$rate = 'ldk_lead_' . md5( $ip );
	$hits = (int) get_transient( $rate );
	if ( $hits >= 8 ) {
		return new WP_Error( 'ldk', 'Muitos envios. Tente mais tarde.', array( 'status' => 429 ) );
	}
	set_transient( $rate, $hits + 1, HOUR_IN_SECONDS );

	$fields = array();
	foreach ( (array) ( isset( $p['fields'] ) ? $p['fields'] : array() ) as $k => $v ) {
		$k = sanitize_text_field( $k );
		$v = is_array( $v ) ? implode( ', ', array_map( 'sanitize_text_field', $v ) ) : sanitize_textarea_field( $v );
		if ( '' !== $k && '' !== trim( $v ) ) {
			$fields[ $k ] = mb_substr( $v, 0, 2000 );
		}
	}
	if ( empty( $fields['Nome'] ) || ( empty( $fields['WhatsApp'] ) && empty( $fields['E-mail'] ) ) ) {
		return new WP_Error( 'ldk', 'Preencha nome e WhatsApp ou e-mail.', array( 'status' => 400 ) );
	}
	$tipo = isset( $p['tipo'] ) ? sanitize_key( $p['tipo'] ) : 'contato';
	$url  = ldk_site_opt( 'crm_url' );
	$ok   = false;
	if ( $url ) {
		$url  = add_query_arg( 'tipo', $tipo, $url );
		$body = $fields + array(
			'form_name' => 'Site LDK · ' . $tipo,
			'page_url'  => isset( $p['page'] ) ? esc_url_raw( $p['page'] ) : '',
		);
		$res  = wp_remote_post( $url, array( 'timeout' => 15, 'body' => $body ) );
		$ok   = ! is_wp_error( $res ) && (int) wp_remote_retrieve_response_code( $res ) < 300;
	}
	if ( ! $ok ) {
		// Plano B: não perde o lead, avisa por e-mail do WordPress.
		$to = ldk_site_opt( 'email' ) ? ldk_site_opt( 'email' ) : get_option( 'admin_email' );
		$tx = '';
		foreach ( $fields as $k => $v ) {
			$tx .= $k . ': ' . $v . "\n";
		}
		wp_mail( $to, 'Novo contato pelo site (' . $tipo . ')', $tx );
	}
	return array( 'ok' => true );
}

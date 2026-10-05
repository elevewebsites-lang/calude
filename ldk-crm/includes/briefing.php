<?php
/**
 * Briefing do cliente novo.
 *
 * Perguntas em Configurações → Briefing (uma por linha). A equipe manda o link /briefing/<token>/ (e-mail + WhatsApp);
 * o cliente responde sem login ou pela área dele ("Briefing"). As respostas ficam salvas na ficha do cliente
 * e podem ser editadas depois. Ao responder, o atendimento e o social media são avisados.
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function lk_briefing_questions() {
	return lk_list( lk_setting( 'briefing_perguntas' ) );
}

/**
 * Respostas salvas: lista de array( 'q' => pergunta, 'a' => resposta ).
 */
function lk_briefing_answers( $c ) {
	return lk_json( $c->briefing );
}

function lk_briefing_url( $c ) {
	if ( ! $c->briefing_token ) {
		$c->briefing_token = strtolower( wp_generate_password( 24, false ) );
		lk_update( 'clients', $c->id, array( 'briefing_token' => $c->briefing_token ) );
	}
	return lk_url( 'briefing/' . $c->briefing_token );
}

function lk_briefing_message( $c ) {
	$first = $c->name ? strtok( $c->name, ' ' ) : '';
	return 'Olá' . ( $first ? ', ' . $first : '' ) . '! Seja muito bem-vindo(a) à ' . lk_setting( 'empresa' ) . ' 💙 Para começarmos do jeito certo, responda o nosso briefing (leva uns 10 minutos): ' . lk_briefing_url( $c );
}

/**
 * Campos do briefing (perguntas atuais + respostas antigas de perguntas que saíram da lista).
 */
function lk_briefing_fields( $c ) {
	$saved = array();
	foreach ( lk_briefing_answers( $c ) as $row ) {
		$saved[ $row['q'] ] = $row['a'];
	}
	$qs = lk_briefing_questions();
	foreach ( array_keys( $saved ) as $q ) {
		if ( ! in_array( $q, $qs, true ) && '' !== trim( (string) $saved[ $q ] ) ) {
			$qs[] = $q;
		}
	}
	foreach ( $qs as $i => $q ) {
		echo '<label class="field bq"><span><b>' . (int) ( $i + 1 ) . '.</b> ' . esc_html( $q ) . '</span><input type="hidden" name="bq[' . (int) $i . ']" value="' . esc_attr( $q ) . '"><textarea name="ba[' . (int) $i . ']" rows="3">' . esc_textarea( $saved[ $q ] ?? '' ) . '</textarea></label>';
	}
}

function lk_briefing_store( $c ) {
	$qs  = isset( $_POST['bq'] ) ? array_map( 'sanitize_text_field', wp_unslash( (array) $_POST['bq'] ) ) : array(); // phpcs:ignore WordPress.Security.NonceVerification
	$as  = isset( $_POST['ba'] ) ? array_map( 'sanitize_textarea_field', wp_unslash( (array) $_POST['ba'] ) ) : array(); // phpcs:ignore WordPress.Security.NonceVerification
	$out = array();
	foreach ( $qs as $i => $q ) {
		if ( $q ) {
			$out[] = array( 'q' => $q, 'a' => $as[ $i ] ?? '' );
		}
	}
	$first = ! $c->briefing_at;
	lk_update( 'clients', $c->id, array( 'briefing' => wp_json_encode( $out ), 'briefing_at' => lk_now() ) );
	do_action( 'lk_briefing_saved', $c->id ); // cópia no Drive
	if ( ! lk_is_team() ) {
		foreach ( array_unique( array_filter( array( (int) $c->atendimento_id, (int) $c->social_id ) ) ) as $uid ) {
			lk_notify( $uid, '📋 ' . lk_client_label( $c ) . ( $first ? ' respondeu o briefing.' : ' atualizou o briefing.' ), lk_panel_url( 'cliente', $c->id ) . '#briefing' );
		}
	}
}

/**
 * Salvar pela área do cliente (ou pela equipe, na ficha).
 */
function lk_do_briefing_save() {
	if ( lk_is_team() ) {
		lk_require( 'clientes' );
		$c = lk_get( 'clients', lk_in( 'id', 'int' ) );
	} else {
		$c = lk_current_client();
	}
	if ( ! $c ) {
		wp_die( 'Sem permissão.' );
	}
	lk_briefing_store( $c );
	lk_back( 'Briefing salvo. Obrigado!' );
}

/**
 * Mandar o link por e-mail (e deixar o WhatsApp pronto).
 */
function lk_do_briefing_send() {
	lk_require( 'clientes' );
	$c = lk_get( 'clients', lk_in( 'id', 'int' ) );
	if ( ! $c ) {
		lk_back();
	}
	$url = lk_briefing_url( $c );
	if ( is_email( $c->email ) ) {
		lk_mail( $c->email, 'Briefing · ' . lk_setting( 'empresa' ), 'Vamos nos conhecer melhor', '<p>Olá, ' . esc_html( strtok( (string) $c->name, ' ' ) ) . '! Para começarmos do jeito certo, responda o nosso briefing. Leva uns 10 minutos e você pode editar depois pela sua área.</p>', array(), 'Responder o briefing', $url );
	}
	lk_back( is_email( $c->email ) ? 'Briefing enviado por e-mail.' : 'O cliente não tem e-mail: mande pelo WhatsApp.', is_email( $c->email ) ? 'ok' : 'warn' );
}

add_action(
	'init',
	function () {
		add_rewrite_rule( '^briefing/([A-Za-z0-9]+)/?$', 'index.php?lk_route=briefing&lk_token=$matches[1]', 'top' );
	}
);

add_action(
	'template_redirect',
	function () {
		if ( 'briefing' !== get_query_var( 'lk_route' ) ) {
			return;
		}
		nocache_headers();
		header( 'X-Robots-Tag: noindex, nofollow', true );
		$token = sanitize_text_field( get_query_var( 'lk_token' ) );
		$rows  = $token ? lk_rows( 'clients', 'briefing_token = %s', array( $token ) ) : array();
		$c     = $rows ? $rows[0] : null;
		if ( ! $c ) {
			lk_render( 'public/indisponivel' );
		}
		$msg = '';
		if ( 'POST' === ( isset( $_SERVER['REQUEST_METHOD'] ) ? $_SERVER['REQUEST_METHOD'] : '' ) && isset( $_POST['_wpnonce'] ) && wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['_wpnonce'] ) ), 'lk_briefing_' . $c->id ) ) { // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
			lk_briefing_store( $c );
			$c   = lk_get( 'clients', $c->id );
			$msg = 'Recebemos as suas respostas. Muito obrigado! 💙 Se lembrar de mais alguma coisa, é só voltar neste link e editar.';
		}
		lk_render( 'public/briefing', array( 'c' => $c, 'msg' => $msg ) );
	},
	0
);

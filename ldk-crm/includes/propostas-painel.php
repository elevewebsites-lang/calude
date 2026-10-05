<?php
/**
 * Propostas dentro do painel (o modelo da LDK Propostas, em modules/propostas).
 *
 *  - /painel/propostas/            lista, com status de envio e visualizações
 *  - /painel/proposta/[id]         montar/editar + enviar (WhatsApp, e-mail, copiar link)
 *  - /painel/propostas-servicos/   serviços, nichos e configurações do modelo
 *
 * A equipe (papel lk_team) não tem as permissões de editor do WordPress. Por isso, só durante
 * as ações abaixo, quem tem a área "orcamentos" ganha as permissões de post necessárias.
 * A página antiga /gerar-proposta agora leva para o painel.
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Permissão temporária para salvar/duplicar/excluir propostas, serviços e nichos.
 */
add_filter(
	'user_has_cap',
	function ( $allcaps, $caps, $args, $user ) {
		// Só para a pessoa já conferida por lk_require( 'orcamentos' ) na ação (sem chamar lk_can aqui, que consultaria as permissões de novo).
		if ( empty( $GLOBALS['lk_prop_ctx'] ) || ! $user || (int) $GLOBALS['lk_prop_ctx'] !== (int) $user->ID ) {
			return $allcaps;
		}
		foreach ( array( 'edit_posts', 'edit_others_posts', 'edit_published_posts', 'publish_posts', 'delete_posts', 'delete_others_posts', 'delete_published_posts', 'read_private_posts', 'edit_private_posts' ) as $cap ) {
			$allcaps[ $cap ] = true;
		}
		return $allcaps;
	},
	10,
	4
);

function lk_prop_ctx() {
	$GLOBALS['lk_prop_ctx'] = get_current_user_id();
}

/**
 * A página antiga /gerar-proposta abre a mesma coisa dentro do painel.
 */
add_action(
	'template_redirect',
	function () {
		if ( ! get_query_var( 'ldkp_painel' ) || ! lk_is_team() ) {
			return;
		}
		$id = isset( $_GET['editar'] ) ? absint( $_GET['editar'] ) : ( isset( $_GET['pronta'] ) ? absint( $_GET['pronta'] ) : 0 ); // phpcs:ignore WordPress.Security.NonceVerification
		wp_safe_redirect( $id ? lk_panel_url( 'proposta', $id ) : lk_panel_url( 'propostas' ) );
		exit;
	},
	1
);

function lk_prop_types() {
	return array(
		'servico' => array( 'ldkp_servico', 'Serviço', 'Serviços', 'ldkp_service_fields' ),
		'nicho'   => array( 'ldkp_nicho', 'Nicho', 'Nichos', 'ldkp_niche_fields' ),
	);
}

function lk_prop_get( $id ) {
	$p = $id ? get_post( $id ) : null;
	return ( $p && 'ldkp_proposta' === $p->post_type && 'trash' !== $p->post_status ) ? $p : null;
}

function lk_prop_short( $id ) {
	$short = get_post_meta( $id, '_ldkp_cliente_curto', true );
	return $short ? $short : get_the_title( $id );
}

/**
 * Cliente ou lead ligado à proposta (para puxar WhatsApp e e-mail).
 */
function lk_prop_contact( $id ) {
	$ref = (string) get_post_meta( $id, '_lk_contato', true );
	$out = array( 'ref' => $ref, 'phone' => '', 'email' => '', 'label' => '' );
	if ( preg_match( '/^(c|l)(\d+)$/', $ref, $m ) ) {
		$row = lk_get( 'c' === $m[1] ? 'clients' : 'leads', (int) $m[2] );
		if ( $row ) {
			$out['phone'] = ! empty( $row->whatsapp ) ? $row->whatsapp : ( isset( $row->phone ) ? $row->phone : '' );
			$out['email'] = $row->email;
			$out['label'] = $row->company ? $row->company : $row->name;
		}
	}
	$phone = get_post_meta( $id, '_lk_phone', true );
	$email = get_post_meta( $id, '_lk_email', true );
	if ( $phone ) {
		$out['phone'] = $phone;
	}
	if ( $email ) {
		$out['email'] = $email;
	}
	return $out;
}

function lk_prop_message( $id ) {
	$tpl = (string) get_post_meta( $id, '_lk_msg', true );
	if ( ! $tpl ) {
		$tpl = "Olá, {cliente}! Tudo bem?\n\nPreparei a sua proposta comercial com tudo o que conversamos. É só abrir o link:\n{link}\n\nQualquer dúvida, estou por aqui.";
	}
	return str_replace( array( '{cliente}', '{link}' ), array( lk_prop_short( $id ), get_permalink( $id ) ), $tpl );
}

function lk_prop_wa_number( $phone ) {
	$d = preg_replace( '/\D/', '', (string) $phone );
	if ( '' === $d ) {
		return '';
	}
	return ( strlen( $d ) <= 11 ? '55' : '' ) . $d;
}

/**
 * Guarda o histórico de envios (até 20).
 */
function lk_prop_log( $id, $via, $to ) {
	$log   = get_post_meta( $id, '_lk_envios', true );
	$log   = is_array( $log ) ? $log : array();
	$log[] = array(
		'via' => $via,
		'to'  => $to,
		'at'  => lk_now(),
		'by'  => get_current_user_id(),
	);
	update_post_meta( $id, '_lk_envios', array_slice( $log, -20 ) );
	update_post_meta( $id, '_lk_sent_at', lk_now() );
}

/**
 * Guarda os campos de envio que ficam junto da proposta (contato, telefone, e-mail, mensagem).
 */
function lk_prop_save_contact( $id ) {
	$ref = sanitize_text_field( (string) lk_in( 'contato', 'raw' ) );
	update_post_meta( $id, '_lk_contato', preg_match( '/^(c|l)\d+$/', $ref ) ? $ref : '' );
	if ( isset( $_POST['phone'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification
		update_post_meta( $id, '_lk_phone', sanitize_text_field( (string) lk_in( 'phone', 'raw' ) ) );
	}
	if ( isset( $_POST['email'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification
		$e = sanitize_email( (string) lk_in( 'email', 'raw' ) );
		update_post_meta( $id, '_lk_email', $e );
	}
	if ( isset( $_POST['msg'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification
		update_post_meta( $id, '_lk_msg', sanitize_textarea_field( (string) lk_in( 'msg', 'raw' ) ) );
	}
}

/* ----------------------------------------------------------------------
 * Ações (formulários do painel: action=lk&do=<nome>)
 * ------------------------------------------------------------------- */

function lk_do_prop_save() {
	lk_require( 'orcamentos' );
	lk_prop_ctx();
	$client = isset( $_POST['ep']['cliente'] ) ? sanitize_text_field( wp_unslash( $_POST['ep']['cliente'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification -- nonce verificado em lk_handle
	$id     = lk_in( 'id', 'int' );
	if ( '' === $client ) {
		lk_back( 'Preencha o nome do cliente para gerar a proposta.', 'erro' );
	}
	if ( $id ) {
		if ( ! lk_prop_get( $id ) ) {
			lk_back( 'Proposta não encontrada.', 'erro' );
		}
		wp_update_post( array( 'ID' => $id, 'post_status' => 'publish' ) );
	} else {
		$id = wp_insert_post(
			array(
				'post_type'   => 'ldkp_proposta',
				'post_status' => 'publish',
				'post_title'  => $client,
				'post_author' => get_current_user_id(),
			)
		);
		if ( ! $id || is_wp_error( $id ) ) {
			lk_back( 'Não foi possível salvar a proposta.', 'erro' );
		}
		lk_flash( 'Proposta gerada! Agora é só enviar para o cliente.' );
		wp_safe_redirect( lk_panel_url( 'proposta', $id ) . '#enviar' );
		exit;
	}
	lk_back( 'Proposta salva. O link continua o mesmo.', 'ok', lk_panel_url( 'proposta', $id ) );
}

function lk_do_prop_send() {
	$via = sanitize_key( (string) lk_in( 'via', 'raw' ) );
	if ( 'email' === $via ) {
		lk_prop_send_email();
	} elseif ( 'salvar' === $via ) {
		lk_prop_contact_save();
	}
	lk_prop_send_whatsapp();
}

function lk_prop_send_whatsapp() {
	lk_require( 'orcamentos' );
	$id = lk_in( 'id', 'int' );
	if ( ! lk_prop_get( $id ) ) {
		lk_back( 'Proposta não encontrada.', 'erro' );
	}
	lk_prop_save_contact( $id );
	$num = lk_prop_wa_number( get_post_meta( $id, '_lk_phone', true ) ? get_post_meta( $id, '_lk_phone', true ) : lk_prop_contact( $id )['phone'] );
	lk_prop_log( $id, 'whatsapp', $num );
	// Abre o WhatsApp (com o número, se tiver; sem número, o WhatsApp pede para escolher o contato).
	wp_redirect( 'https://wa.me/' . $num . '?text=' . rawurlencode( lk_prop_message( $id ) ) ); // phpcs:ignore WordPress.Security.SafeRedirect -- destino fixo do WhatsApp
	exit;
}

function lk_prop_send_email() {
	lk_require( 'orcamentos' );
	$id = lk_in( 'id', 'int' );
	if ( ! lk_prop_get( $id ) ) {
		lk_back( 'Proposta não encontrada.', 'erro' );
	}
	lk_prop_save_contact( $id );
	$to = sanitize_email( (string) lk_in( 'email', 'raw' ) );
	if ( ! is_email( $to ) ) {
		lk_back( 'Informe um e-mail válido.', 'erro', lk_panel_url( 'proposta', $id ) . '#enviar' );
	}
	$msg   = lk_prop_message( $id );
	$link  = get_permalink( $id );
	$intro = wpautop( esc_html( trim( str_replace( $link, '', $msg ) ) ) );
	$ok    = lk_mail( $to, 'Sua proposta comercial · ' . lk_setting( 'empresa' ), 'Sua proposta está pronta', $intro, array(), 'Abrir a proposta', $link );
	if ( ! $ok ) {
		lk_back( 'O e-mail não saiu. Confira o SMTP em Configurações → E-mail ou envie pelo WhatsApp.', 'erro', lk_panel_url( 'proposta', $id ) . '#enviar' );
	}
	lk_prop_log( $id, 'e-mail', $to );
	lk_back( 'Proposta enviada para ' . $to . '.', 'ok', lk_panel_url( 'proposta', $id ) . '#enviar' );
}

function lk_prop_contact_save() {
	lk_require( 'orcamentos' );
	$id = lk_in( 'id', 'int' );
	if ( ! lk_prop_get( $id ) ) {
		lk_back( 'Proposta não encontrada.', 'erro' );
	}
	lk_prop_save_contact( $id );
	lk_back( 'Dados de envio salvos.', 'ok', lk_panel_url( 'proposta', $id ) . '#enviar' );
}

function lk_do_prop_mark_sent() {
	lk_require( 'orcamentos' );
	$id = lk_in( 'id', 'int' );
	if ( lk_prop_get( $id ) ) {
		lk_prop_log( $id, 'manual', '' );
	}
	lk_back( 'Marcada como enviada.', 'ok', lk_panel_url( 'proposta', $id ) . '#enviar' );
}

function lk_do_prop_duplicate() {
	lk_require( 'orcamentos' );
	lk_prop_ctx();
	$src = lk_prop_get( lk_in( 'id', 'int' ) );
	if ( ! $src ) {
		lk_back( 'Proposta não encontrada.', 'erro' );
	}
	$new = wp_insert_post(
		array(
			'post_type'   => 'ldkp_proposta',
			'post_status' => 'draft',
			'post_title'  => $src->post_title . ' (cópia)',
			'post_author' => get_current_user_id(),
		)
	);
	if ( ! $new || is_wp_error( $new ) ) {
		lk_back( 'Não foi possível duplicar.', 'erro' );
	}
	foreach ( array_keys( ldkp_proposal_fields() ) as $key ) {
		update_post_meta( $new, '_ldkp_' . $key, get_post_meta( $src->ID, '_ldkp_' . $key, true ) );
	}
	update_post_meta( $new, '_ldkp_planos', get_post_meta( $src->ID, '_ldkp_planos', true ) );
	update_post_meta( $new, '_ldkp_cliente', '' );
	update_post_meta( $new, '_ldkp_cliente_curto', '' );
	lk_back( 'Cópia criada. Troque o cliente e gere a proposta.', 'ok', lk_panel_url( 'proposta', $new ) );
}

function lk_do_prop_delete() {
	lk_require( 'orcamentos' );
	lk_prop_ctx();
	$p = lk_prop_get( lk_in( 'id', 'int' ) );
	if ( $p ) {
		wp_trash_post( $p->ID ); // Vai para a lixeira do WordPress (dá para recuperar pelo wp-admin).
	}
	lk_back( 'Proposta excluída. O link parou de funcionar.', 'ok', lk_panel_url( 'propostas' ) );
}

/**
 * Serviços e nichos (a "biblioteca" que preenche as propostas).
 */
function lk_do_prop_lib_save() {
	lk_require( 'orcamentos' );
	lk_prop_ctx();
	$types = lk_prop_types();
	$type  = sanitize_key( (string) lk_in( 'tipo', 'raw' ) );
	if ( ! isset( $types[ $type ] ) ) {
		lk_back( 'Tipo inválido.', 'erro' );
	}
	list( $pt, $label, , $fields_fn ) = $types[ $type ];
	$title = sanitize_text_field( (string) lk_in( 'titulo', 'raw' ) );
	if ( '' === $title ) {
		lk_back( 'Dê um nome para o ' . strtolower( $label ) . '.', 'erro' );
	}
	$id   = lk_in( 'id', 'int' );
	$post = array(
		'post_type'   => $pt,
		'post_status' => 'publish',
		'post_title'  => $title,
		'menu_order'  => lk_in( 'ordem', 'int' ),
	);
	if ( $id ) {
		if ( get_post_type( $id ) !== $pt ) {
			lk_back( 'Item não encontrado.', 'erro' );
		}
		$post['ID'] = $id;
		wp_update_post( $post );
	} else {
		$id = wp_insert_post( $post );
	}
	if ( ! $id || is_wp_error( $id ) ) {
		lk_back( 'Não foi possível salvar.', 'erro' );
	}
	$in = isset( $_POST['ep'] ) ? wp_unslash( $_POST['ep'] ) : array(); // phpcs:ignore WordPress.Security.NonceVerification,WordPress.Security.ValidatedSanitizedInput -- nonce em lk_handle; sanitizado campo a campo
	foreach ( call_user_func( $fields_fn ) as $key => $def ) {
		update_post_meta( $id, '_ldkp_' . $key, ldkp_sanitize( $def[0], isset( $in[ $key ] ) ? $in[ $key ] : '' ) );
	}
	lk_back( $label . ' "' . $title . '" salvo.', 'ok', lk_panel_url( 'propostas-servicos', 0, array( 'aba' => $type ) ) );
}

function lk_do_prop_lib_delete() {
	lk_require( 'orcamentos' );
	lk_prop_ctx();
	$types = lk_prop_types();
	$type  = sanitize_key( (string) lk_in( 'tipo', 'raw' ) );
	$id    = lk_in( 'id', 'int' );
	if ( isset( $types[ $type ] ) && get_post_type( $id ) === $types[ $type ][0] ) {
		wp_trash_post( $id );
	}
	lk_back( 'Excluído.', 'ok', lk_panel_url( 'propostas-servicos', 0, array( 'aba' => $type ) ) );
}

function lk_do_prop_settings_save() {
	lk_require( 'admin' );
	$in  = isset( $_POST['ep'] ) ? wp_unslash( $_POST['ep'] ) : array(); // phpcs:ignore WordPress.Security.NonceVerification,WordPress.Security.ValidatedSanitizedInput
	$out = ldkp_settings();
	foreach ( ldkp_settings_fields() as $group ) {
		foreach ( $group as $key => $def ) {
			$out[ $key ] = ldkp_sanitize( $def[0], isset( $in[ $key ] ) ? $in[ $key ] : '' );
		}
	}
	update_option( 'ldkp_settings', $out );
	lk_back( 'Configurações das propostas salvas.', 'ok', lk_panel_url( 'propostas-servicos', 0, array( 'aba' => 'config' ) ) );
}

/**
 * Estilos do montador da proposta no painel.
 */
function lk_prop_assets() {
	echo '<link rel="stylesheet" href="' . esc_url( LDKP_URL . 'assets/admin.css?ver=' . LDKP_VERSION ) . '">';
	echo '<link rel="stylesheet" href="' . esc_url( LK_URL . 'assets/propostas.css?ver=' . LK_VERSION ) . '">';
}

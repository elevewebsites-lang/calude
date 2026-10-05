<?php
/**
 * Extras: logo do cliente no Drive, trava de revisão antes de enviar ao cliente e status das redes (30 clientes).
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/* -----------------------------------------------------------------------
 * Logo do cliente → pasta dele no Drive (Clientes/<cliente>/Identidade visual)
 * -------------------------------------------------------------------- */

add_action( 'lk_client_logo_saved', 'lk_client_logo_backup', 10, 2 );
function lk_client_logo_backup( $client_id, $logo_url ) {
	if ( ! $logo_url || ! function_exists( 'lk_drive_queue' ) || ! function_exists( 'lk_google_connected' ) || ! lk_google_connected() ) {
		return;
	}
	$client = lk_get( 'clients', $client_id );
	$up     = wp_upload_dir();
	if ( ! $client || 0 !== strpos( $logo_url, $up['baseurl'] ) ) {
		return;
	}
	$path = $up['basedir'] . substr( $logo_url, strlen( $up['baseurl'] ) );
	if ( ! file_exists( $path ) ) {
		return;
	}
	$att = attachment_url_to_postid( $logo_url );
	if ( ! $att ) { // a logo foi enviada fora da biblioteca: registra para o Drive poder ler
		require_once ABSPATH . 'wp-admin/includes/image.php';
		$type = wp_check_filetype( $path );
		$att  = wp_insert_attachment( array( 'post_mime_type' => $type['type'], 'post_title' => 'Logo · ' . lk_client_label( $client ), 'post_status' => 'inherit' ), $path );
		if ( $att && ! is_wp_error( $att ) ) {
			wp_update_attachment_metadata( $att, wp_generate_attachment_metadata( $att, $path ) );
		}
	}
	if ( $att && ! is_wp_error( $att ) ) {
		lk_drive_queue( $att, array( 'Clientes', lk_client_label( $client ), 'Identidade visual' ) );
	}
}

/* -----------------------------------------------------------------------
 * Revisão obrigatória antes de ir para o cliente
 * -------------------------------------------------------------------- */

/**
 * Devolve a mensagem de bloqueio ('' se pode enviar). Revisa na hora se o texto ainda não foi revisado.
 * Erros de limite do Instagram sempre bloqueiam; pontos da IA bloqueiam a menos que $force.
 */
function lk_review_gate( $p, $force = false ) {
	if ( '1' !== (string) lk_setting( 'revisao_obrigatoria' ) || '' === trim( (string) $p->caption ) ) {
		return '';
	}
	$hash = md5( $p->caption . '|' . (string) $p->hashtags );
	$r    = lk_json( $p->review );
	if ( ( $r['hash'] ?? '' ) !== $hash ) {
		$r = lk_review_text( $p->caption, (string) $p->hashtags, lk_get( 'clients', $p->client_id ), substr( (string) $p->scheduled_at, 0, 10 ) );
		lk_update( 'posts', $p->id, array( 'review' => wp_json_encode( $r ) ) );
	}
	$errs = array_map( function ( $x ) { return $x[1]; }, array_filter( (array) ( $r['rules'] ?? array() ), function ( $x ) { return 'erro' === $x[0]; } ) );
	if ( $errs ) {
		return 'Não dá para enviar ainda: ' . implode( ' ', $errs );
	}
	$ai = count( (array) ( $r['ai']['issues'] ?? array() ) );
	if ( $ai && ! $force ) {
		set_transient( 'lk_gate_' . $p->id, 1, 900 ); // libera o botão "Enviar mesmo assim" na tela do post
		return 'A revisão do texto achou ' . $ai . ' ponto(s) de português ou contexto. Abra o post, clique em "Revisar texto" e ajuste, ou use "Enviar mesmo assim".';
	}
	return '';
}

/* -----------------------------------------------------------------------
 * Status das redes dos clientes (tela Redes conectadas)
 * -------------------------------------------------------------------- */

function lk_connect_message( $client ) {
	$first = $client->name ? strtok( $client->name, ' ' ) : '';
	return 'Olá' . ( $first ? ', ' . $first : '' ) . '! 😊 Para a ' . lk_setting( 'empresa' ) . ' postar e acompanhar o seu Instagram sem você precisar enviar nada, é só clicar no link, entrar na sua conta e autorizar (leva 1 minuto, e não pedimos sua senha): ' . lk_connect_url( $client->id );
}

/** Estado de uma rede de um cliente: [codigo, rótulo]. codigo: ok | erro | off */
function lk_net_state( $accs, $net ) {
	$a = $accs[ $net ] ?? null;
	if ( ! $a ) {
		return array( 'off', 'pendente' );
	}
	if ( 'ok' !== $a->status ) {
		return array( 'erro', 'reconectar' );
	}
	return array( 'ok', $a->username ? '@' . $a->username : ( $a->name ?: 'conectado' ) );
}

/** Planejamento: antes de ir para o cliente, nenhum texto pode estourar os limites do Instagram. */
function lk_plan_review_gate( $client, $ym ) {
	if ( '1' !== (string) lk_setting( 'revisao_obrigatoria' ) ) {
		return '';
	}
	$bad = array();
	foreach ( lk_plan_pending_posts( $client, $ym ) as $p ) {
		$errs = array_filter( lk_caption_rules( (string) $p->caption, (string) $p->hashtags ), function ( $x ) { return 'erro' === $x[0]; } );
		if ( $errs ) {
			$bad[] = '"' . $p->title . '": ' . implode( ' ', array_map( function ( $x ) { return $x[1]; }, $errs ) );
		}
	}
	return $bad ? 'Corrija antes de enviar ao cliente — ' . implode( ' | ', $bad ) : '';
}

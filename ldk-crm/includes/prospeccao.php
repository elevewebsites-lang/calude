<?php
/**
 * Prospecção: busca empresas no Google (Places API) por nicho + cidade e traz nome, nicho, telefone, site, nota e endereço.
 * E-mail e Instagram não vêm do Google: o botão "Buscar e-mail e Instagram" lê o site público da empresa (página inicial e de contato) e pega o que ela mesma publica.
 * As empresas escolhidas entram no Funil de leads (origem "Prospecção Google"), sem duplicar quem já está no funil ou é cliente.
 *
 * Precisa de uma chave da Google Places API (Configurações → Google). O Google cobra por busca (há cota gratuita mensal).
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function lk_places_key() {
	return (string) lk_decrypt( lk_setting( 'places_key' ) );
}

add_action(
	'rest_api_init',
	function () {
		$who = function () { return lk_can( 'leads' ); };
		register_rest_route( 'lk/v1', '/prospect/search', array( 'methods' => 'POST', 'callback' => 'lk_api_prospect_search', 'permission_callback' => $who ) );
		register_rest_route( 'lk/v1', '/prospect/email', array( 'methods' => 'POST', 'callback' => 'lk_api_prospect_email', 'permission_callback' => $who ) );
		register_rest_route( 'lk/v1', '/prospect/add', array( 'methods' => 'POST', 'callback' => 'lk_api_prospect_add', 'permission_callback' => $who ) );
	}
);

function lk_digits( $s ) {
	return preg_replace( '/\D+/', '', (string) $s );
}

/** Conjuntos para marcar quem já está no funil / já é cliente. */
function lk_prospect_known() {
	$known = array( 'phones' => array(), 'emails' => array(), 'places' => array(), 'names' => array() );
	foreach ( lk_rows( 'leads', '1=1' ) as $l ) {
		$d = lk_digits( $l->whatsapp );
		if ( strlen( $d ) >= 8 ) {
			$known['phones'][ substr( $d, -8 ) ] = 'lead';
		}
		if ( $l->email ) {
			$known['emails'][ strtolower( $l->email ) ] = 'lead';
		}
		if ( preg_match( '/place:([\w-]+)/', (string) $l->notes, $m ) ) {
			$known['places'][ $m[1] ] = 'lead';
		}
		if ( $l->company ) {
			$known['names'][ sanitize_title( $l->company ) ] = 'lead';
		}
	}
	foreach ( lk_clients() as $c ) {
		foreach ( array( $c->whatsapp, $c->phone ) as $ph ) {
			$d = lk_digits( $ph );
			if ( strlen( $d ) >= 8 ) {
				$known['phones'][ substr( $d, -8 ) ] = 'cliente';
			}
		}
		if ( $c->email ) {
			$known['emails'][ strtolower( $c->email ) ] = 'cliente';
		}
		$known['names'][ sanitize_title( lk_client_label( $c ) ) ] = 'cliente';
	}
	return $known;
}

function lk_api_prospect_search( WP_REST_Request $r ) {
	$key = lk_places_key();
	if ( ! $key ) {
		return new WP_Error( 'lk', 'Falta a chave da Google Places API (Configurações → Google).', array( 'status' => 400 ) );
	}
	$niche = sanitize_text_field( (string) $r['niche'] );
	$city  = sanitize_text_field( (string) $r['city'] );
	if ( '' === $niche || '' === $city ) {
		return new WP_Error( 'lk', 'Informe o nicho (ex.: engenharia) e a cidade (ex.: Taubaté SP).', array( 'status' => 400 ) );
	}
	$body = array( 'textQuery' => $niche . ' em ' . $city, 'languageCode' => 'pt-BR', 'regionCode' => 'BR', 'pageSize' => 20 );
	if ( $r['token'] ) {
		$body['pageToken'] = sanitize_text_field( (string) $r['token'] );
	}
	$res = wp_remote_post(
		'https://places.googleapis.com/v1/places:searchText',
		array(
			'timeout' => 30,
			'headers' => array(
				'Content-Type'     => 'application/json',
				'X-Goog-Api-Key'   => $key,
				'X-Goog-FieldMask' => 'places.id,places.displayName,places.formattedAddress,places.nationalPhoneNumber,places.internationalPhoneNumber,places.websiteUri,places.rating,places.userRatingCount,places.primaryTypeDisplayName,places.googleMapsUri,places.businessStatus,nextPageToken',
			),
			'body'    => wp_json_encode( $body ),
		)
	);
	if ( is_wp_error( $res ) ) {
		return new WP_Error( 'lk', 'Não consegui falar com o Google: ' . $res->get_error_message(), array( 'status' => 502 ) );
	}
	$code = wp_remote_retrieve_response_code( $res );
	$json = json_decode( wp_remote_retrieve_body( $res ), true );
	if ( $code >= 300 ) {
		$msg = $json['error']['message'] ?? ( 'Erro ' . $code );
		return new WP_Error( 'lk', 'Google: ' . $msg . ( 403 === $code ? ' (a Places API (New) está ativada e a cobrança configurada no Google Cloud?)' : '' ), array( 'status' => 502 ) );
	}
	$known = lk_prospect_known();
	$out   = array();
	foreach ( (array) ( $json['places'] ?? array() ) as $pl ) {
		if ( ( $pl['businessStatus'] ?? '' ) === 'CLOSED_PERMANENTLY' ) {
			continue;
		}
		$phone = $pl['nationalPhoneNumber'] ?? ( $pl['internationalPhoneNumber'] ?? '' );
		$name  = $pl['displayName']['text'] ?? '';
		$d     = lk_digits( $phone );
		$state = $known['places'][ $pl['id'] ] ?? ( strlen( $d ) >= 8 ? ( $known['phones'][ substr( $d, -8 ) ] ?? '' ) : '' );
		$state = $state ? $state : ( $known['names'][ sanitize_title( $name ) ] ?? '' );
		$out[] = array(
			'id'      => $pl['id'] ?? '',
			'name'    => $name,
			'niche'   => $pl['primaryTypeDisplayName']['text'] ?? '',
			'phone'   => $phone,
			'wa'      => $phone ? lk_wa_link( $phone, '' ) : '',
			'website' => $pl['websiteUri'] ?? '',
			'address' => $pl['formattedAddress'] ?? '',
			'rating'  => (float) ( $pl['rating'] ?? 0 ),
			'count'   => (int) ( $pl['userRatingCount'] ?? 0 ),
			'maps'    => $pl['googleMapsUri'] ?? '',
			'known'   => $state,
		);
	}
	return array( 'places' => $out, 'next' => $json['nextPageToken'] ?? '', 'query' => $body['textQuery'] );
}

/** Contatos públicos no site da empresa (página inicial e de contato): e-mails e Instagram. */
function lk_prospect_find_contacts( $url ) {
	$out = array( 'emails' => array(), 'instagram' => '' );
	$url = esc_url_raw( $url );
	if ( ! $url || ! preg_match( '#^https?://#i', $url ) ) {
		return $out;
	}
	$parts = wp_parse_url( $url );
	$base  = ( $parts['scheme'] ?? 'https' ) . '://' . ( $parts['host'] ?? '' );
	$host  = preg_replace( '/^www\./i', '', strtolower( $parts['host'] ?? '' ) );
	$pages = array_unique( array( $url, $base . '/contato', $base . '/contato/', $base . '/fale-conosco', $base . '/contact' ) );
	$found = array();
	$igs   = array();
	foreach ( $pages as $i => $page ) {
		if ( $i > 0 && $found && $igs ) {
			break;
		}
		$res = wp_safe_remote_get( $page, array( 'timeout' => 8, 'redirection' => 3, 'limit_response_size' => 300000, 'user-agent' => 'Mozilla/5.0 (compatible; LDK-CRM)' ) );
		if ( is_wp_error( $res ) || wp_remote_retrieve_response_code( $res ) >= 400 ) {
			continue;
		}
		$html = html_entity_decode( (string) wp_remote_retrieve_body( $res ), ENT_QUOTES, 'UTF-8' );
		preg_match_all( '/[A-Za-z0-9._%+\-]+@[A-Za-z0-9\-]+(?:\.[A-Za-z0-9\-]+)*\.[A-Za-z]{2,}/', $html, $m );
		foreach ( $m[0] as $e ) {
			$e = strtolower( rtrim( $e, '.' ) );
			if ( preg_match( '/\.(png|jpe?g|gif|webp|svg|css|js)$/', $e ) || preg_match( '/(example|sentry|wixpress|domain\.com|@2x|seu-?email|email@)/', $e ) ) {
				continue;
			}
			$found[ $e ] = ( substr( $e, -strlen( $host ) ) === $host ) ? 1 : 0; // mesmo domínio vale mais
		}
		// Instagram: link público que o próprio site mostra (não consultamos o Instagram).
		preg_match_all( '#instagram\.com/([A-Za-z0-9._]{2,30})#i', $html, $im );
		foreach ( $im[1] as $h ) {
			$h = strtolower( rtrim( $h, '.' ) );
			if ( ! in_array( $h, array( 'p', 'reel', 'reels', 'explore', 'accounts', 'sharer', 'share', 'tv', 'stories', 'direct', 'about', 'legal', 'developer', 'web', 'embed', 'oauth' ), true ) ) {
				$igs[ $h ] = ( $igs[ $h ] ?? 0 ) + 1;
			}
		}
	}
	arsort( $found );
	arsort( $igs );
	$out['emails']    = array_slice( array_keys( $found ), 0, 3 );
	$out['instagram'] = $igs ? (string) array_key_first( $igs ) : '';
	return $out;
}

function lk_api_prospect_email( WP_REST_Request $r ) {
	return lk_prospect_find_contacts( (string) $r['url'] );
}

function lk_api_prospect_add( WP_REST_Request $r ) {
	$funnel = array_keys( lk_funnel() );
	$known  = lk_prospect_known();
	$added  = 0;
	$skip   = 0;
	$ids    = array();
	foreach ( (array) $r['items'] as $it ) {
		$name  = sanitize_text_field( $it['name'] ?? '' );
		$phone = sanitize_text_field( $it['phone'] ?? '' );
		$email = sanitize_email( $it['email'] ?? '' );
		$pid   = preg_replace( '/[^\w-]/', '', (string) ( $it['id'] ?? '' ) );
		$d     = lk_digits( $phone );
		if ( '' === $name || ( $pid && isset( $known['places'][ $pid ] ) ) || ( strlen( $d ) >= 8 && isset( $known['phones'][ substr( $d, -8 ) ] ) ) || ( $email && isset( $known['emails'][ strtolower( $email ) ] ) ) ) {
			$skip++;
			continue;
		}
		$notes = array(
			'Prospecção Google: "' . sanitize_text_field( $it['query'] ?? '' ) . '"',
			! empty( $it['niche'] ) ? 'Segmento: ' . sanitize_text_field( $it['niche'] ) : '',
			! empty( $it['website'] ) ? 'Site: ' . esc_url_raw( $it['website'] ) : '',
			! empty( $it['instagram'] ) ? 'Instagram: @' . preg_replace( '/[^A-Za-z0-9._]/', '', (string) $it['instagram'] ) . ' (https://instagram.com/' . preg_replace( '/[^A-Za-z0-9._]/', '', (string) $it['instagram'] ) . ')' : '',
			! empty( $it['address'] ) ? 'Endereço: ' . sanitize_text_field( $it['address'] ) : '',
			! empty( $it['rating'] ) ? 'Nota no Google: ' . (float) $it['rating'] . ' (' . (int) ( $it['count'] ?? 0 ) . ' avaliações)' : '',
			! empty( $it['maps'] ) ? 'Maps: ' . esc_url_raw( $it['maps'] ) : '',
			$pid ? 'place:' . $pid : '',
		);
		$id = lk_insert(
			'leads',
			array(
				'name'     => '',
				'company'  => $name,
				'email'    => $email,
				'whatsapp' => $phone,
				'source'   => 'Prospecção Google',
				'stage'    => $funnel[0],
				'notes'    => implode( "\n", array_filter( $notes ) ),
			)
		);
		lk_lead_log( $id, 'Lead criado pela Prospecção Google.' );
		if ( $pid ) {
			$known['places'][ $pid ] = 'lead';
		}
		if ( strlen( $d ) >= 8 ) {
			$known['phones'][ substr( $d, -8 ) ] = 'lead';
		}
		$ids[] = $id;
		$added++;
	}
	return array( 'added' => $added, 'skipped' => $skip, 'funnel' => lk_panel_url( 'leads' ) );
}

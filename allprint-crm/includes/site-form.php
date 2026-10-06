<?php
/**
 * Formulários do site (site da empresa) → painel (funil de leads, origem "Site").
 *
 * No Elementor Pro: Formulário → Ações após o envio → Webhook, com a URL mostrada em
 * Configurações. O envio para o WhatsApp continua funcionando normalmente.
 *
 *   POST /wp-json/ap/v1/site?chave=<token>                  vira lead no Funil
 *
 * Aceita o formato simples do Elementor (campo => valor), o avançado (fields[id][title|value])
 * e JSON. Os campos são reconhecidos pelo nome: nome, empresa, e-mail, WhatsApp/telefone, mensagem.
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function ap_site_form_token() {
	$t = get_option( 'ap_site_form_token' );
	if ( ! $t ) {
		$t = wp_generate_password( 24, false );
		update_option( 'ap_site_form_token', $t, false );
	}
	return $t;
}

function ap_site_form_url( $type = '' ) {
	$args = array( 'chave' => ap_site_form_token() );
	if ( $type ) {
		$args['tipo'] = $type;
	}
	return add_query_arg( $args, rest_url( 'ap/v1/site' ) );
}

add_action(
	'rest_api_init',
	function () {
		register_rest_route(
			'ap/v1',
			'/site',
			array(
				'methods'             => 'POST',
				'permission_callback' => '__return_true', // Conferido pela chave dentro do callback.
				'callback'            => 'ap_api_site_form',
			)
		);
	}
);

/**
 * Transforma o que o formulário mandou em pares "Rótulo" => "valor".
 */
function ap_site_form_fields( WP_REST_Request $r ) {
	$params = $r->get_json_params();
	$params = is_array( $params ) && $params ? $params : $r->get_body_params();
	$out    = array();
	// Formato avançado do Elementor: fields[id][title], fields[id][value].
	if ( isset( $params['fields'] ) && is_array( $params['fields'] ) ) {
		foreach ( $params['fields'] as $id => $f ) {
			if ( is_array( $f ) ) {
				$label         = ! empty( $f['title'] ) ? $f['title'] : $id;
				$out[ $label ] = is_array( $f['value'] ?? '' ) ? implode( ', ', $f['value'] ) : (string) ( $f['value'] ?? '' );
			}
		}
		return $out;
	}
	$skip = array( 'chave', 'tipo', 'date', 'time', 'page url', 'page_url', 'user agent', 'user_agent', 'remote ip', 'remote_ip', 'powered by', 'powered_by', 'form_id', 'form_name', 'referer_title', 'queried_id', 'post_id', 'action', 'referrer' );
	foreach ( (array) $params as $k => $v ) {
		if ( in_array( strtolower( trim( $k ) ), $skip, true ) ) {
			continue;
		}
		// O PHP troca espaços por "_" nos nomes dos campos ("Seu nome" chega como "Seu_nome").
		$out[ str_replace( '_', ' ', (string) $k ) ] = is_array( $v ) ? implode( ', ', array_map( 'strval', $v ) ) : (string) $v;
	}
	return $out;
}

/**
 * Descobre nome, empresa, e-mail, telefone e mensagem pelos rótulos dos campos.
 */
function ap_site_form_parse( $fields ) {
	$d = array( 'name' => '', 'company' => '', 'email' => '', 'phone' => '', 'message' => '', 'service' => '', 'invest' => '', 'moment' => '', 'deadline' => '', 'extra' => array() );
	foreach ( $fields as $label => $value ) {
		$value = trim( sanitize_textarea_field( $value ) );
		if ( '' === $value ) {
			continue;
		}
		$k = remove_accents( strtolower( $label ) );
		// Perguntas do formulário em etapas do site (antes das regras genéricas).
		if ( ! $d['service'] && preg_match( '/servico|tipo de projeto|o que voce precisa|interesse/', $k ) ) {
			$d['service'] = $value;
		} elseif ( ! $d['invest'] && preg_match( '/invest|orcamento|budget/', $k ) ) {
			$d['invest'] = $value;
		} elseif ( ! $d['moment'] && preg_match( '/momento|fase|estagio/', $k ) ) {
			$d['moment'] = $value;
		} elseif ( ! $d['deadline'] && preg_match( '/prazo|quando/', $k ) ) {
			$d['deadline'] = $value;
		} elseif ( ! $d['email'] && ( false !== strpos( $k, 'mail' ) || is_email( $value ) ) ) {
			$d['email'] = sanitize_email( $value );
		} elseif ( ! $d['phone'] && preg_match( '/whats|telefone|celular|phone|fone|contato/', $k ) && preg_match( '/\d{8,}/', preg_replace( '/\D/', '', $value ) ) ) {
			$d['phone'] = $value;
		} elseif ( ! $d['company'] && preg_match( '/empresa|company|negocio|marca|clinica|escritorio|loja/', $k ) ) {
			$d['company'] = $value;
		} elseif ( ! $d['name'] && preg_match( '/nome|name/', $k ) ) {
			$d['name'] = $value;
		} elseif ( ! $d['message'] && preg_match( '/mensag|message|duvida|descri|conte|contar|mais alguma|projeto|observ/', $k ) ) {
			$d['message'] = $value;
		} else {
			$d['extra'][ sanitize_text_field( $label ) ] = $value;
		}
	}
	return $d;
}

function ap_api_site_form( WP_REST_Request $r ) {
	$key = (string) $r->get_param( 'chave' );
	if ( '' === $key || ! hash_equals( ap_site_form_token(), $key ) ) {
		return new WP_Error( 'ap', 'Chave inválida.', array( 'status' => 403 ) );
	}
	// No máximo 30 envios por hora por IP.
	$ip   = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '';
	$rate = 'ap_siteform_' . md5( $ip );
	$hits = (int) get_transient( $rate );
	if ( $hits >= 30 ) {
		return new WP_Error( 'ap', 'Muitos envios.', array( 'status' => 429 ) );
	}
	set_transient( $rate, $hits + 1, HOUR_IN_SECONDS );

	$fields = ap_site_form_fields( $r );
	$d      = ap_site_form_parse( $fields );
	if ( ! $d['name'] && ! $d['company'] && ! $d['email'] && ! $d['phone'] ) {
		return new WP_Error( 'ap', 'Formulário vazio.', array( 'status' => 400 ) );
	}
	$page = (string) ( $r->get_param( 'Page_URL' ) ?: $r->get_param( 'page_url' ) ?: ( $r->get_param( 'meta' )['page_url']['value'] ?? '' ) );
	$form = (string) ( $r->get_param( 'form_name' ) ?: ( $r->get_param( 'form' )['name'] ?? '' ) );
	$text = '';
	foreach ( $fields as $label => $value ) {
		if ( '' !== trim( (string) $value ) ) {
			$text .= $label . ': ' . sanitize_textarea_field( $value ) . "\n";
		}
	}
	$text .= ( $form ? 'Formulário: ' . sanitize_text_field( $form ) . "\n" : '' ) . ( $page ? 'Página: ' . esc_url_raw( $page ) . "\n" : '' );
	$who = $d['company'] ? $d['company'] : ( $d['name'] ? $d['name'] : ( $d['email'] ? $d['email'] : $d['phone'] ) );

	// Já existe lead com o mesmo e-mail ou WhatsApp? Anota nele em vez de duplicar.
	global $wpdb;
	$digits  = preg_replace( '/\D/', '', $d['phone'] );
	$client  = ap_site_form_find_client( $d['email'], $digits );
	$service = ap_site_form_match_service( $d['service'] );
	$value   = ap_site_form_invest_value( $d['invest'] );
	$summary = array_filter(
		array(
			'Serviço'      => $d['service'],
			'Momento'      => $d['moment'],
			'Investimento' => $d['invest'],
			'Prazo'        => $d['deadline'],
		)
	);
	$lead   = null;
	if ( $d['email'] ) {
		$lead = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . ap_table( 'leads' ) . ' WHERE email = %s ORDER BY id DESC LIMIT 1', $d['email'] ) ); // phpcs:ignore WordPress.DB.PreparedSQL
	}
	if ( ! $lead && strlen( $digits ) >= 8 ) {
		$lead = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . ap_table( 'leads' ) . " WHERE REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(whatsapp,' ',''),'-',''),'(',''),')',''),'+','') LIKE %s ORDER BY id DESC LIMIT 1", '%' . $wpdb->esc_like( substr( $digits, -8 ) ) ) ); // phpcs:ignore WordPress.DB.PreparedSQL
	}
	if ( $lead ) {
		ap_lead_log( $lead->id, "Preencheu o formulário do site de novo:\n" . $text, 'comment' );
		ap_update(
			'leads',
			$lead->id,
			array_filter(
				array(
					'email'      => $lead->email ? '' : $d['email'],
					'whatsapp'   => $lead->whatsapp ? '' : $d['phone'],
					'client_id'  => $lead->client_id || ! $client ? 0 : (int) $client->id,
					'service_id' => $lead->service_id || ! $service ? 0 : $service,
					'value'      => (float) $lead->value > 0 ? 0 : $value,
				)
			) + array( 'last_contact' => ap_now() )
		);
		$id = (int) $lead->id;
	} else {
		$keys = array_keys( ap_funnel() );
		$id   = ap_insert(
			'leads',
			array(
				'name'       => $d['name'] ? $d['name'] : ( $client ? $client->name : '' ),
				'company'    => $d['company'] ? $d['company'] : ( $client ? $client->company : '' ),
				'email'      => $d['email'],
				'whatsapp'   => $d['phone'],
				'source'     => 'Site',
				'stage'      => $keys[0],
				'client_id'  => $client ? (int) $client->id : 0,
				'service_id' => $service,
				'value'      => $value,
				'notes'      => trim(
					( $summary ? implode( "\n", array_map( function ( $k, $v ) { return $k . ': ' . $v; }, array_keys( $summary ), $summary ) ) . "\n\n" : '' ) .
					$d['message'] .
					( $d['extra'] ? "\n\n" . implode( "\n", array_map( function ( $k, $v ) { return $k . ': ' . $v; }, array_keys( $d['extra'] ), $d['extra'] ) ) : '' )
				),
			)
		);
		ap_lead_log( $id, 'Lead criado pelo formulário do site' . ( $client ? ' (já é cliente: ' . ap_client_label( $client ) . ')' : '' ) . ".\n" . $text );
	}
	$lead = ap_get( 'leads', $id );
	ap_set_lead_reminder( $lead, 'Responder o contato do site', ap_today(), current_time( 'H:i' ) );

	// Aviso para você por e-mail.
	ap_mail(
		ap_setting( 'email' ),
		'Novo contato pelo site: ' . $who,
		'Novo contato pelo site',
		$who . ' preencheu o formulário do site' . ( $form ? ' (' . $form . ')' : '' ) . '.',
		array_filter(
			array(
				'Nome'     => $d['name'],
				'Empresa'  => $d['company'],
				'E-mail'   => $d['email'],
				'WhatsApp'     => $d['phone'],
				'Serviço'      => $d['service'],
				'Investimento' => $d['invest'],
				'Já é cliente' => $client ? ap_client_label( $client ) : '',
				'Mensagem'     => $d['message'],
			)
		),
		'Abrir o lead',
		ap_panel_url( 'lead', $id )
	);
	return array( 'ok' => true, 'lead' => $id );
}

/**
 * Cliente já cadastrado com o mesmo e-mail ou WhatsApp (últimos 8 dígitos).
 */
function ap_site_form_find_client( $email, $digits ) {
	global $wpdb;
	$t = ap_table( 'clients' );
	if ( $email ) {
		$c = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $t WHERE email = %s ORDER BY id DESC LIMIT 1", $email ) ); // phpcs:ignore WordPress.DB.PreparedSQL
		if ( $c ) {
			return $c;
		}
	}
	if ( strlen( $digits ) >= 8 ) {
		$like = '%' . $wpdb->esc_like( substr( $digits, -8 ) );
		return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $t WHERE REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(whatsapp,' ',''),'-',''),'(',''),')',''),'+','') LIKE %s OR REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(phone,' ',''),'-',''),'(',''),')',''),'+','') LIKE %s ORDER BY id DESC LIMIT 1", $like, $like ) ); // phpcs:ignore WordPress.DB.PreparedSQL
	}
	return null;
}

/**
 * "Landing Page", "Site Institucional"… → id do serviço cadastrado com nome parecido.
 */
function ap_site_form_match_service( $text ) {
	$t = remove_accents( strtolower( (string) $text ) );
	if ( '' === $t ) {
		return 0;
	}
	$best = 0;
	$score = 0;
	foreach ( ap_services() as $svc ) {
		$n = remove_accents( strtolower( $svc->name ) );
		similar_text( $t, $n, $pct );
		$hit = ( false !== strpos( $n, $t ) || false !== strpos( $t, $n ) ) ? 100 : $pct;
		foreach ( array( 'landing', 'institucional', 'commerce', 'loja', 'branding', 'identidade', 'sistema', 'manutenc' ) as $w ) {
			if ( false !== strpos( $t, $w ) && false !== strpos( $n, $w ) ) {
				$hit = max( $hit, 90 );
			}
		}
		if ( $hit > $score ) {
			$score = $hit;
			$best  = (int) $svc->id;
		}
	}
	return $score >= 60 ? $best : 0;
}

/**
 * Faixa de investimento → valor estimado ("Até R$ 2.500" = 2500; "Entre R$ 2.500 e R$ 7.000" = 4750).
 */
function ap_site_form_invest_value( $text ) {
	preg_match_all( '/\d[\d.]*(?:,\d{2})?/', (string) $text, $m );
	$nums = array_values( array_filter( array_map( 'ap_parse_money', $m[0] ) ) );
	if ( ! $nums ) {
		return 0;
	}
	return 2 <= count( $nums ) ? round( ( $nums[0] + $nums[1] ) / 2, 2 ) : $nums[0];
}

function ap_do_site_form_token() {
	ap_require( 'admin' );
	update_option( 'ap_site_form_token', wp_generate_password( 24, false ), false );
	ap_back( 'Chave nova gerada. Atualize a URL do webhook nos formulários do site.' );
}

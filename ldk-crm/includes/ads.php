<?php
/**
 * Tráfego pago.
 * - Meta Ads: automático (token de acesso da LDK com ads_read + a conta de anúncios do cliente, act_XXXX).
 * - Google Ads: a API exige token de desenvolvedor aprovado pelo Google; até lá o gestor lança os números do mês.
 * O cliente só vê quando o gestor liberar (chave na ficha do cliente e no relatório).
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function lk_ads_period( $client_id, $from, $to ) {
	$c   = lk_get( 'clients', $client_id );
	$out = array( 'meta' => null, 'google' => null );
	$tok = lk_decrypt( lk_setting( 'meta_ads_token' ) );
	if ( $c && $c->meta_ad_account && $tok ) {
		$acc  = 'act_' . preg_replace( '/\D/', '', $c->meta_ad_account );
		$tr   = rawurlencode( wp_json_encode( array( 'since' => $from, 'until' => $to ) ) );
		$tot  = lk_http_json( 'GET', 'https://graph.facebook.com/' . LK_GRAPH . '/' . $acc . '/insights?fields=spend,impressions,reach,clicks,cpc,ctr,actions,action_values,purchase_roas&time_range=' . $tr . '&access_token=' . rawurlencode( $tok ) );
		$camp = lk_http_json( 'GET', 'https://graph.facebook.com/' . LK_GRAPH . '/' . $acc . '/insights?level=campaign&fields=campaign_name,spend,clicks,ctr,cpc,actions,action_values&time_range=' . $tr . '&limit=20&access_token=' . rawurlencode( $tok ) );
		if ( ! is_wp_error( $tot ) ) {
			$d      = $tot['data'][0] ?? array();
			$act    = function ( $row, $key ) {
				$n = 0;
				foreach ( (array) ( $row[ $key ] ?? array() ) as $a ) {
					if ( in_array( $a['action_type'], array( 'purchase', 'offsite_conversion.fb_pixel_purchase', 'lead', 'onsite_conversion.messaging_conversation_started_7d', 'offsite_conversion.fb_pixel_lead' ), true ) ) {
						$n += (float) $a['value'];
					}
				}
				return $n;
			};
			$out['meta'] = array(
				'spend'       => (float) ( $d['spend'] ?? 0 ),
				'impressions' => (int) ( $d['impressions'] ?? 0 ),
				'reach'       => (int) ( $d['reach'] ?? 0 ),
				'clicks'      => (int) ( $d['clicks'] ?? 0 ),
				'ctr'         => (float) ( $d['ctr'] ?? 0 ),
				'cpc'         => (float) ( $d['cpc'] ?? 0 ),
				'results'     => $act( $d, 'actions' ),
				'revenue'     => $act( $d, 'action_values' ),
				'campaigns'   => is_wp_error( $camp ) ? array() : array_map(
					function ( $r ) use ( $act ) {
						return array( 'name' => $r['campaign_name'], 'spend' => (float) $r['spend'], 'clicks' => (int) ( $r['clicks'] ?? 0 ), 'results' => $act( $r, 'actions' ), 'revenue' => $act( $r, 'action_values' ) );
					},
					(array) ( $camp['data'] ?? array() )
				),
			);
			// Carteira: saldo/limite da conta de anúncios (quando a Meta informa).
			$acct = lk_http_json( 'GET', 'https://graph.facebook.com/' . LK_GRAPH . '/' . $acc . '?fields=currency,balance,amount_spent,spend_cap,account_status,funding_source_details&access_token=' . rawurlencode( $tok ) );
			if ( ! is_wp_error( $acct ) ) {
				$cap  = (float) ( $acct['spend_cap'] ?? 0 ) / 100;
				$spent = (float) ( $acct['amount_spent'] ?? 0 ) / 100;
				$out['meta']['wallet'] = array(
					'funding'   => (string) ( $acct['funding_source_details']['display_string'] ?? '' ),
					'balance'   => (float) ( $acct['balance'] ?? 0 ) / 100,
					'spent_all' => $spent,
					'cap'       => $cap,
					'left_cap'  => $cap > 0 ? max( 0, $cap - $spent ) : null,
					'status'    => (int) ( $acct['account_status'] ?? 0 ),
				);
			}
		} else {
			$out['meta'] = array( 'error' => $tot->get_error_message() );
		}
	}
	$g = get_option( 'lk_gads_' . $client_id . '_' . substr( $from, 0, 7 ) );
	if ( is_array( $g ) ) {
		$out['google'] = $g;
	}
	return $out;
}

/**
 * Google Ads: lançamento manual do mês pelo gestor.
 */
function lk_do_ads_google_manual() {
	lk_require( 'trafego' );
	$cid = lk_in( 'client_id', 'int' );
	$ym  = preg_match( '/^\d{4}-\d{2}$/', lk_in( 'period' ) ) ? lk_in( 'period' ) : gmdate( 'Y-m' );
	update_option(
		'lk_gads_' . $cid . '_' . $ym,
		array(
			'spend'   => lk_in( 'spend', 'money' ),
			'clicks'  => lk_in( 'clicks', 'int' ),
			'results' => lk_in( 'results', 'int' ),
			'revenue' => lk_in( 'revenue', 'money' ),
			'note'    => lk_in( 'note', 'textarea' ),
		),
		false
	);
	lk_back( 'Números do Google Ads salvos.' );
}

function lk_do_ads_visibility() {
	lk_require( 'trafego' );
	$c = lk_get( 'clients', lk_in( 'client_id', 'int' ) );
	if ( $c ) {
		lk_update( 'clients', $c->id, array( 'ads_visible' => $c->ads_visible ? 0 : 1, 'meta_ad_account' => lk_in( 'meta_ad_account' ) !== '' ? lk_in( 'meta_ad_account' ) : $c->meta_ad_account ) );
	}
	lk_back( 'Atualizado.' );
}

function lk_do_ads_account() {
	lk_require( 'trafego' );
	lk_update( 'clients', lk_in( 'client_id', 'int' ), array( 'meta_ad_account' => lk_in( 'meta_ad_account' ), 'google_ads_id' => lk_in( 'google_ads_id' ), 'ads_visible' => lk_in( 'ads_visible', 'bool' ) ) );
	lk_back( 'Contas de anúncio salvas.' );
}

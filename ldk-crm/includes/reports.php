<?php
/**
 * Relatório mensal por cliente (redes + tráfego quando o gestor liberar), página pública /relatorio/<token>/
 * no estilo carrossel da proposta. Compara com o mês anterior.
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_action(
	'init',
	function () {
		add_rewrite_rule( '^relatorio/([A-Za-z0-9]+)/?$', 'index.php?lk_route=report&lk_token=$matches[1]', 'top' );
	}
);
add_action(
	'template_redirect',
	function () {
		if ( 'report' !== get_query_var( 'lk_route' ) ) {
			return;
		}
		nocache_headers();
		header( 'X-Robots-Tag: noindex, nofollow', true );
		$rows = lk_rows( 'reports', 'token = %s', array( sanitize_text_field( get_query_var( 'lk_token' ) ) ) );
		if ( ! $rows || ( 'rascunho' === $rows[0]->status && ! lk_is_team() ) ) {
			lk_render( 'public/indisponivel' );
		}
		lk_render( 'public/relatorio', array( 'r' => $rows[0] ) );
	},
	0
);

function lk_report_url( $r ) {
	return lk_url( 'relatorio/' . $r->token );
}

function lk_month_range( $ym ) {
	$from = $ym . '-01';
	return array( $from, gmdate( 'Y-m-t', strtotime( $from ) ) );
}

/**
 * Monta (ou refaz) os números do mês.
 */
function lk_report_build( $client_id, $ym ) {
	$acc  = lk_social_account( $client_id, 'instagram' );
	$prev = gmdate( 'Y-m', strtotime( $ym . '-01 -1 month' ) );
	list( $f, $t )   = lk_month_range( $ym );
	list( $pf, $pt ) = lk_month_range( $prev );
	$data = array( 'period' => $ym, 'ig' => null, 'posts' => array(), 'produced' => 0, 'ads' => null );
	if ( $acc ) {
		$cur = lk_ig_account_insights( $acc, $f, $t );
		$old = lk_ig_account_insights( $acc, $pf, $pt );
		$fol = function ( $day ) use ( $client_id ) {
			$m = lk_rows( 'metrics', 'client_id = %d AND network = %s AND day <= %s', array( $client_id, 'instagram', $day ), 'day DESC LIMIT 1' );
			$d = $m ? lk_json( $m[0]->data ) : array();
			return $d['followers'] ?? null;
		};
		$data['ig'] = array(
			'username' => $acc->username,
			'avatar'   => $acc->avatar,
			'cur'      => is_wp_error( $cur ) ? array() : $cur,
			'old'      => is_wp_error( $old ) ? array() : $old,
			'followers' => $fol( $t ),
			'followers_old' => $fol( $pt ),
			'error'    => is_wp_error( $cur ) ? $cur->get_error_message() : '',
		);
		$media = lk_ig_media_period( $acc, $f, $t );
		if ( ! is_wp_error( $media ) ) {
			usort(
				$media,
				function ( $a, $b ) {
					return $b['engaged'] <=> $a['engaged'];
				}
			);
			$data['posts'] = array_slice( $media, 0, 9 );
			$data['all']   = $media;
			$data['count'] = count( $media );
			$data['totals'] = array(
				'likes'    => array_sum( wp_list_pluck( $media, 'likes' ) ),
				'comments' => array_sum( wp_list_pluck( $media, 'comments' ) ),
				'saves'    => array_sum( wp_list_pluck( $media, 'saves' ) ),
				'shares'   => array_sum( wp_list_pluck( $media, 'shares' ) ),
			);
		}
		// Seguidores dia a dia (guardados pela rotina diária), para o gráfico de evolução.
		$series = array();
		foreach ( lk_rows( 'metrics', 'client_id = %d AND network = %s AND day BETWEEN %s AND %s', array( $client_id, 'instagram', $f, $t ), 'day' ) as $m ) {
			$md = lk_json( $m->data );
			if ( isset( $md['followers'] ) && null !== $md['followers'] ) {
				$series[ $m->day ] = (int) $md['followers'];
			}
		}
		$data['ig']['series'] = $series;
	}
	global $wpdb;
	$data['produced'] = (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM ' . lk_table( 'posts' ) . ' WHERE client_id = %d AND scheduled_at BETWEEN %s AND %s', $client_id, $f . ' 00:00:00', $t . ' 23:59:59' ) ); // phpcs:ignore WordPress.DB.PreparedSQL
	if ( function_exists( 'lk_ads_period' ) ) {
		$ads = lk_ads_period( $client_id, $f, $t );
		$data['ads'] = is_wp_error( $ads ) ? null : $ads;
	}
	return $data;
}

function lk_do_report_generate() {
	lk_require( 'relatorios' );
	$cid = lk_in( 'client_id', 'int' );
	$ym  = preg_match( '/^\d{4}-\d{2}$/', lk_in( 'period' ) ) ? lk_in( 'period' ) : gmdate( 'Y-m', strtotime( 'first day of last month' ) );
	$old = lk_rows( 'reports', 'client_id = %d AND period = %s AND kind = %s', array( $cid, $ym, 'social' ) );
	$data = lk_report_build( $cid, $ym );
	if ( $old ) {
		lk_update( 'reports', $old[0]->id, array( 'data' => wp_json_encode( $data ) ) );
		$id = $old[0]->id;
	} else {
		$id = lk_insert( 'reports', array( 'client_id' => $cid, 'kind' => 'social', 'period' => $ym, 'token' => strtolower( wp_generate_password( 20, false ) ), 'data' => wp_json_encode( $data ), 'status' => 'rascunho' ) );
	}
	lk_back( 'Relatório gerado. Revise, escreva a análise e envie.', 'ok', lk_panel_url( 'relatorio', $id ) );
}

function lk_do_report_save() {
	lk_require( 'relatorios' );
	$r = lk_get( 'reports', lk_in( 'id', 'int' ) );
	if ( ! $r ) {
		lk_back();
	}
	$d = lk_json( $r->data );
	$d['show_ads'] = lk_in( 'show_ads', 'bool' );
	lk_update( 'reports', $r->id, array( 'notes' => lk_in( 'notes', 'textarea' ), 'data' => wp_json_encode( $d ), 'status' => lk_in( 'publicar', 'bool' ) ? 'publicado' : $r->status ) );
	if ( lk_in( 'enviar', 'bool' ) ) {
		$c = lk_get( 'clients', $r->client_id );
		if ( $c && is_email( $c->email ) ) {
			lk_update( 'reports', $r->id, array( 'status' => 'publicado', 'sent_at' => lk_now() ) );
			lk_mail( $c->email, 'Seu relatório de ' . lk_month_label( $r->period ), 'Seu relatório de ' . lk_month_label( $r->period ), '<p>Olá, ' . esc_html( strtok( (string) $c->name, ' ' ) ) . '! Preparamos o resumo do mês: resultados, posts que mais engajaram e os próximos passos.</p>', array(), 'Ver o relatório', lk_report_url( lk_get( 'reports', $r->id ) ) );
			lk_back( 'Relatório enviado para o cliente.' );
		}
	}
	lk_back( 'Relatório salvo.' );
}

/**
 * Variação em % (null quando não dá para comparar).
 */
function lk_var( $cur, $old ) {
	if ( ! $old ) {
		return null;
	}
	return round( ( $cur - $old ) / $old * 100 );
}

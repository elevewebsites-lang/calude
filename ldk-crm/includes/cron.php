<?php
/**
 * Tarefas automáticas (uma vez por dia):
 *  - rotinas: criam a tarefa do dia e agendam a próxima;
 *  - renovações: hospedagem, domínio e fim do suporte viram tarefa com antecedência.
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function lk_frequencies() {
	return array(
		'diaria'     => 'Todo dia',
		'dias-uteis' => 'Dias úteis',
		'semanal'    => 'Toda semana',
		'quinzenal'  => 'A cada 15 dias',
		'mensal'     => 'Todo mês',
		'trimestral' => 'A cada 3 meses',
		'anual'      => 'Todo ano',
	);
}

function lk_next_date( $date, $frequency ) {
	$steps = array(
		'diaria'     => '+1 day',
		'semanal'    => '+1 week',
		'quinzenal'  => '+2 weeks',
		'mensal'     => '+1 month',
		'trimestral' => '+3 months',
		'anual'      => '+1 year',
	);
	if ( 'dias-uteis' === $frequency ) {
		$next = strtotime( $date . ' +1 day' );
		while ( (int) gmdate( 'N', $next ) >= 6 ) {
			$next = strtotime( '+1 day', $next );
		}
		return gmdate( 'Y-m-d', $next );
	}
	return gmdate( 'Y-m-d', strtotime( $date . ' ' . ( isset( $steps[ $frequency ] ) ? $steps[ $frequency ] : '+1 week' ) ) );
}

add_action( 'lk_daily', 'lk_daily_jobs' );
function lk_daily_jobs() {
	do_action( 'lk_daily_catchup' );
}

// Garante que as rotinas rodem mesmo se o cron do WordPress atrasar: confere uma vez por dia na primeira visita ao painel.
add_action(
	'template_redirect',
	function () {
		if ( get_query_var( 'lk_route' ) && lk_is_team() && get_option( 'lk_last_daily' ) !== lk_today() ) {
			update_option( 'lk_last_daily', lk_today(), false );
			lk_daily_jobs();
		}
	},
	0
);

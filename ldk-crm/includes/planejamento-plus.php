<?php
/**
 * Planejamento: datas do mês (feriados em destaque), ideia de cada conteúdo, aviso aos social medias
 * e cópia do planejamento numa planilha no Google Drive (para não perder nada se o sistema falhar).
 *
 *  - lk_month_dates(): feriados nacionais (com os móveis: Carnaval, Sexta Santa, Páscoa, Corpus Christi), datas
 *    comemorativas (Dia das Mães, Black Friday…) e campanhas do mês. Feriados locais/extras: Planejamento → "Datas extras".
 *  - Aviso (lk_daily): 7 dias e 1 dia antes de cada feriado, cada social media recebe a lista dos clientes dele,
 *    dizendo quem tem post marcado naquele dia.
 *  - Planilha: Clientes/<cliente>/Planejamentos/"Planejamento AAAA-MM · Cliente" (Google Planilhas, abre/baixa como Excel).
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/* -----------------------------------------------------------------------
 * Datas
 * -------------------------------------------------------------------- */

function lk_easter_ts( $y ) {
	$a = $y % 19; $b = intdiv( $y, 100 ); $c = $y % 100; $d = intdiv( $b, 4 ); $e = $b % 4; $f = intdiv( $b + 8, 25 ); $g = intdiv( $b - $f + 1, 3 );
	$h = ( 19 * $a + $b - $d - $g + 15 ) % 30; $i = intdiv( $c, 4 ); $k = $c % 4; $l = ( 32 + 2 * $e + 2 * $i - $h - $k ) % 7; $m = intdiv( $a + 11 * $h + 22 * $l, 451 );
	$mo = intdiv( $h + $l - 7 * $m + 114, 31 ); $day = ( ( $h + $l - 7 * $m + 114 ) % 31 ) + 1;
	return gmmktime( 0, 0, 0, $mo, $day, $y );
}

/** n-ésimo dia da semana (0=dom) do mês: data Y-m-d. */
function lk_nth_dow( $y, $m, $dow, $n ) {
	$first = (int) gmdate( 'w', gmmktime( 0, 0, 0, $m, 1, $y ) );
	return gmdate( 'Y-m-d', gmmktime( 0, 0, 0, $m, 1 + ( ( $dow - $first + 7 ) % 7 ) + 7 * ( $n - 1 ), $y ) );
}

/** Todas as datas do ano: array de [date, name, type(feriado|data), sub]. */
function lk_dates_year( $y ) {
	$e   = lk_easter_ts( $y );
	$off = function ( $days ) use ( $e ) { return gmdate( 'Y-m-d', $e + $days * DAY_IN_SECONDS ); };
	$f   = function ( $md, $name, $type = 'feriado', $sub = '' ) use ( $y ) { return array( 'date' => $y . '-' . $md, 'name' => $name, 'type' => $type, 'sub' => $sub ); };
	$out = array(
		$f( '01-01', 'Confraternização Universal' ),
		array( 'date' => $off( -48 ), 'name' => 'Carnaval (segunda)', 'type' => 'feriado', 'sub' => 'ponto facultativo' ),
		array( 'date' => $off( -47 ), 'name' => 'Carnaval (terça)', 'type' => 'feriado', 'sub' => 'ponto facultativo' ),
		array( 'date' => $off( -2 ), 'name' => 'Sexta-feira Santa', 'type' => 'feriado', 'sub' => '' ),
		array( 'date' => $off( 0 ), 'name' => 'Páscoa', 'type' => 'data', 'sub' => '' ),
		$f( '04-21', 'Tiradentes' ),
		$f( '05-01', 'Dia do Trabalhador' ),
		array( 'date' => $off( 60 ), 'name' => 'Corpus Christi', 'type' => 'feriado', 'sub' => 'ponto facultativo' ),
		$f( '09-07', 'Independência do Brasil' ),
		$f( '10-12', 'Nossa Senhora Aparecida' ),
		$f( '11-02', 'Finados' ),
		$f( '11-15', 'Proclamação da República' ),
		$f( '11-20', 'Consciência Negra' ),
		$f( '12-25', 'Natal' ),
		$f( '02-14', 'Dia dos Namorados (internacional)', 'data' ),
		$f( '03-08', 'Dia Internacional da Mulher', 'data' ),
		$f( '03-15', 'Dia do Consumidor', 'data' ),
		array( 'date' => lk_nth_dow( $y, 5, 0, 2 ), 'name' => 'Dia das Mães', 'type' => 'data', 'sub' => '' ),
		$f( '06-12', 'Dia dos Namorados', 'data' ),
		array( 'date' => lk_nth_dow( $y, 8, 0, 2 ), 'name' => 'Dia dos Pais', 'type' => 'data', 'sub' => '' ),
		$f( '09-15', 'Dia do Cliente', 'data' ),
		$f( '10-12', 'Dia das Crianças', 'data' ),
		$f( '10-15', 'Dia do Professor', 'data' ),
		$f( '10-31', 'Halloween', 'data' ),
		array( 'date' => gmdate( 'Y-m-d', strtotime( lk_nth_dow( $y, 11, 4, 4 ) . ' +1 day' ) ), 'name' => 'Black Friday', 'type' => 'data', 'sub' => '' ),
		$f( '12-31', 'Réveillon', 'data' ),
	);
	foreach ( lk_dates_extra() as $x ) {
		if ( ! $x['year'] || (int) $x['year'] === (int) $y ) {
			$out[] = array( 'date' => $y . '-' . $x['md'], 'name' => $x['name'], 'type' => $x['type'], 'sub' => 'local' );
		}
	}
	return $out;
}

/** Datas extras do admin: linhas "05/12 | Aniversário de Taubaté | feriado" (ano opcional: 05/12/2026). */
function lk_dates_extra() {
	$out = array();
	foreach ( lk_list( (string) get_option( 'lk_dates_extra', '' ) ) as $line ) {
		$p = array_map( 'trim', explode( '|', $line ) );
		if ( preg_match( '#^(\d{1,2})/(\d{1,2})(?:/(\d{4}))?$#', $p[0] ?? '', $m ) && ! empty( $p[1] ) ) {
			$out[] = array( 'md' => str_pad( $m[2], 2, '0', STR_PAD_LEFT ) . '-' . str_pad( $m[1], 2, '0', STR_PAD_LEFT ), 'year' => (int) ( $m[3] ?? 0 ), 'name' => $p[1], 'type' => ( isset( $p[2] ) && 0 === stripos( $p[2], 'f' ) ) ? 'feriado' : 'data' );
		}
	}
	return $out;
}

function lk_dates_campaigns( $m ) {
	$c = array( 1 => 'Janeiro Branco (saúde mental)', 2 => 'Volta às aulas', 5 => 'Maio Amarelo (trânsito)', 6 => 'Festas Juninas', 7 => 'Férias escolares', 8 => 'Agosto Lilás', 9 => 'Setembro Amarelo', 10 => 'Outubro Rosa', 11 => 'Novembro Azul', 12 => 'Dezembro Laranja' );
	return isset( $c[ $m ] ) ? array( $c[ $m ] ) : array();
}

/** Datas do mês em ordem: [date, day, name, type (feriado|data|campanha), sub]. */
function lk_month_dates( $ym ) {
	$y   = (int) substr( $ym, 0, 4 );
	$m   = (int) substr( $ym, 5, 2 );
	$out = array();
	foreach ( lk_dates_year( $y ) as $d ) {
		if ( substr( $d['date'], 0, 7 ) === $ym ) {
			$d['day'] = (int) substr( $d['date'], 8, 2 );
			$out[]    = $d;
		}
	}
	usort( $out, function ( $a, $b ) { return strcmp( $a['date'], $b['date'] ) ?: strcmp( $a['type'], $b['type'] ); } );
	foreach ( lk_dates_campaigns( $m ) as $c ) {
		$out[] = array( 'date' => '', 'day' => 0, 'name' => $c, 'type' => 'campanha', 'sub' => '' );
	}
	return $out;
}

/** Datas que caem em um dia (Y-m-d). */
function lk_dates_on( $ymd ) {
	$ymd = substr( (string) $ymd, 0, 10 );
	return array_values( array_filter( lk_month_dates( substr( $ymd, 0, 7 ) ), function ( $d ) use ( $ymd ) { return $d['date'] === $ymd; } ) );
}

function lk_is_holiday( $ymd ) {
	foreach ( lk_dates_on( $ymd ) as $d ) {
		if ( 'feriado' === $d['type'] ) {
			return $d;
		}
	}
	return null;
}

function lk_do_dates_extra_save() {
	lk_require( 'admin' );
	update_option( 'lk_dates_extra', lk_in( 'datas_extra', 'textarea' ), false );
	lk_back( 'Datas extras salvas.' );
}

/* -----------------------------------------------------------------------
 * Aviso aos social medias
 * -------------------------------------------------------------------- */

/** Ao abrir o planejamento: avisa quem cuida do cliente dos feriados do mês. */
function lk_plan_notify_holidays( $client, $ym ) {
	$hol = array_filter( lk_month_dates( $ym ), function ( $d ) { return 'feriado' === $d['type']; } );
	if ( ! $hol ) {
		return;
	}
	$txt = array_map( function ( $d ) { return str_pad( (string) $d['day'], 2, '0', STR_PAD_LEFT ) . '/' . substr( $d['date'], 5, 2 ) . ' ' . $d['name']; }, $hol );
	foreach ( array_unique( array_filter( array( (int) $client->social_id, (int) $client->atendimento_id ) ) ) as $uid ) {
		lk_notify( $uid, '🔴 ' . lk_client_label( $client ) . ' · ' . lk_month_label( $ym ) . ' tem feriado: ' . implode( '; ', $txt ) . '. Confira os horários e os posts.', lk_panel_url( 'planejamento', 0, array( 'cliente' => $client->id, 'mes' => $ym ) ) );
	}
}

add_action( 'lk_daily', 'lk_plan_holiday_watch' );
function lk_plan_holiday_watch() {
	$sent  = (array) get_option( 'lk_hol_sent', array() );
	$today = lk_today();
	foreach ( $sent as $k => $t ) { // limpa o que já passou
		if ( $t < time() - 40 * DAY_IN_SECONDS ) {
			unset( $sent[ $k ] );
		}
	}
	foreach ( array( 7, 1 ) as $lead ) {
		$date = gmdate( 'Y-m-d', strtotime( $today . ' +' . $lead . ' day' ) );
		foreach ( lk_dates_on( $date ) as $d ) {
			$key = $date . '|' . $lead;
			if ( 'feriado' !== $d['type'] || ! empty( $sent[ $key ] ) ) {
				continue;
			}
			$sent[ $key ] = time();
			$per          = array();
			foreach ( lk_clients() as $c ) {
				$uid = (int) ( $c->social_id ? $c->social_id : $c->atendimento_id );
				if ( ! $uid ) {
					continue;
				}
				$n           = count( lk_posts( 'p.client_id = %d AND DATE(p.scheduled_at) = %s', array( $c->id, $date ) ) );
				$per[ $uid ][] = lk_client_label( $c ) . ( $n ? ' (' . $n . ' post' . ( $n > 1 ? 's' : '' ) . ')' : ' (sem post)' );
			}
			foreach ( $per as $uid => $list ) {
				lk_notify( $uid, '🔴 Feriado ' . substr( $date, 8, 2 ) . '/' . substr( $date, 5, 2 ) . ' · ' . $d['name'] . ( 1 === $lead ? ' é AMANHÃ' : ' em 7 dias' ) . ': ' . implode( ', ', $list ), lk_panel_url( 'planejamento' ) );
			}
		}
	}
	update_option( 'lk_hol_sent', $sent, false );
}

/* -----------------------------------------------------------------------
 * Planilha no Google Drive
 * -------------------------------------------------------------------- */

function lk_plan_sheet_queue( $plan_id ) {
	if ( ! $plan_id || ! function_exists( 'lk_google_connected' ) || ! lk_google_connected() ) {
		return;
	}
	if ( ! wp_next_scheduled( 'lk_plan_sheet_run', array( (int) $plan_id ) ) ) {
		wp_schedule_single_event( time() + 45, 'lk_plan_sheet_run', array( (int) $plan_id ) );
	}
}

add_action( 'lk_updated', 'lk_plan_sheet_on_update', 20, 3 );
function lk_plan_sheet_on_update( $table, $id, $data ) {
	if ( 'plans' === $table && ! array_key_exists( 'sheet_id', $data ) ) {
		lk_plan_sheet_queue( $id );
	} elseif ( 'posts' === $table ) {
		$p = lk_get( 'posts', $id );
		if ( $p && $p->plan_id ) {
			lk_plan_sheet_queue( $p->plan_id );
		}
	}
}

/** Depois de salvar/criar um post pelo formulário (lk_sheet_sync já roda nesse ponto). */
add_action(
	'lk_post_saved',
	function ( $post_id ) {
		$p = lk_get( 'posts', $post_id );
		if ( $p ) {
			$client = lk_get( 'clients', $p->client_id );
			$plan   = $client && $p->scheduled_at ? lk_plan_for( $client->id, substr( $p->scheduled_at, 0, 7 ) ) : null;
			if ( $plan ) {
				lk_plan_sheet_queue( $plan->id );
			}
		}
	}
);

add_action( 'lk_plan_sheet_run', 'lk_plan_sheet_sync' );
add_action(
	'lk_daily',
	function () {
		foreach ( array( current_time( 'Y-m' ), gmdate( 'Y-m', strtotime( current_time( 'Y-m' ) . '-01 +1 month' ) ) ) as $ym ) {
			foreach ( lk_rows( 'plans', 'period = %s', array( $ym ) ) as $pl ) {
				lk_plan_sheet_sync( $pl->id );
			}
		}
	}
);

function lk_plan_sheet_rows( $plan, $client ) {
	$ym   = $plan->period;
	$rows = array(
		array( 'Planejamento de ' . lk_month_label( $ym ) . ' · ' . lk_client_label( $client ) ),
		array( 'Status: ' . lk_plan_status_label( $plan->status ), 'Atualizado em ' . lk_date( lk_now(), 'd/m/Y H:i' ), 'Link para o cliente: ' . lk_plan_url( $plan ) ),
		array(),
		array( 'Dia', 'Dia da semana', 'Feriado / data especial', 'Formato', 'Redes', 'Título', 'Ideia do conteúdo', 'Legenda', 'Etapa', 'Status do cliente', 'Link de aprovação' ),
	);
	foreach ( lk_plan_month_posts( $client->id, $ym ) as $p ) {
		$on   = $p->scheduled_at ? lk_dates_on( $p->scheduled_at ) : array();
		$rows[] = array(
			$p->scheduled_at ? lk_date( $p->scheduled_at, 'd/m/Y' ) . ' ' . substr( $p->scheduled_at, 11, 5 ) : '',
			$p->scheduled_at ? lk_dow_short( $p->scheduled_at ) : '',
			implode( '; ', array_map( function ( $d ) { return ( 'feriado' === $d['type'] ? 'FERIADO · ' : '' ) . $d['name']; }, $on ) ),
			lk_formats()[ $p->format ] ?? $p->format,
			$p->networks,
			$p->title,
			(string) $p->idea,
			(string) $p->caption,
			lk_stages()[ $p->stage ] ?? $p->stage,
			$p->client_status,
			lk_post_url( $p ),
		);
	}
	$rows[] = array();
	$rows[] = array( 'Datas do mês' );
	foreach ( lk_month_dates( $ym ) as $d ) {
		$rows[] = array( $d['date'] ? lk_date( $d['date'], 'd/m/Y' ) : 'Mês todo', '', ( 'feriado' === $d['type'] ? 'FERIADO' : ( 'campanha' === $d['type'] ? 'Campanha' : 'Data comemorativa' ) ) . ( $d['sub'] ? ' (' . $d['sub'] . ')' : '' ), $d['name'] );
	}
	return $rows;
}

function lk_plan_sheet_sync( $plan_id, $retry = true ) {
	if ( ! function_exists( 'lk_google_connected' ) || ! lk_google_connected() ) {
		return;
	}
	$plan   = lk_get( 'plans', $plan_id );
	$client = $plan ? lk_get( 'clients', $plan->client_id ) : null;
	if ( ! $client ) {
		return;
	}
	$sheet = $plan->sheet_id;
	if ( ! $sheet ) {
		$folder = lk_drive_folder( array( 'Clientes', lk_client_label( $client ), 'Planejamentos' ) );
		if ( is_wp_error( $folder ) ) {
			return;
		}
		$res = lk_google_api( 'POST', 'https://www.googleapis.com/drive/v3/files?fields=id', array( 'name' => 'Planejamento ' . $plan->period . ' · ' . lk_client_label( $client ), 'mimeType' => 'application/vnd.google-apps.spreadsheet', 'parents' => array( $folder ) ) );
		if ( is_wp_error( $res ) || empty( $res['id'] ) ) {
			return;
		}
		$sheet = $res['id'];
		lk_update( 'plans', $plan->id, array( 'sheet_id' => $sheet ) );
	}
	lk_google_api( 'POST', 'https://sheets.googleapis.com/v4/spreadsheets/' . $sheet . '/values/A1:Z500:clear', array() );
	$put = lk_google_api( 'PUT', 'https://sheets.googleapis.com/v4/spreadsheets/' . $sheet . '/values/A1?valueInputOption=RAW', array( 'values' => lk_plan_sheet_rows( $plan, $client ) ) );
	if ( is_wp_error( $put ) && $retry && false !== stripos( $put->get_error_message(), 'not found' ) ) {
		lk_update( 'plans', $plan->id, array( 'sheet_id' => '' ) ); // planilha apagada: recria
		lk_plan_sheet_sync( $plan_id, false );
	}
}

function lk_plan_sheet_url( $plan ) {
	return $plan && $plan->sheet_id ? 'https://docs.google.com/spreadsheets/d/' . rawurlencode( $plan->sheet_id ) : '';
}

/** Cria (se faltar) a pasta do cliente no Drive com as subpastas padrão. */
function lk_do_client_folder() {
	lk_require( 'clientes' );
	$c = lk_get( 'clients', lk_in( 'client_id', 'int' ) );
	if ( ! $c ) {
		lk_back( 'Cliente não encontrado.', 'erro' );
	}
	$ok = true;
	foreach ( array( array(), array( 'Planejamentos' ), array( 'Recebidos' ), array( 'Conteúdos ' . current_time( 'Y-m' ) ) ) as $sub ) {
		$f = lk_drive_folder( array_merge( array( 'Clientes', lk_client_label( $c ) ), $sub ) );
		$ok = $ok && ! is_wp_error( $f );
	}
	lk_back( $ok ? 'Pasta criada no Drive.' : 'Não consegui criar a pasta (o Google Drive está conectado?).', $ok ? 'ok' : 'erro' );
}

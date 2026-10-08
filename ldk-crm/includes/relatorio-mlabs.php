<?php
/**
 * Relatório a partir do PDF do mLabs.
 *
 * Sem vínculo com as redes, os números vêm do relatório que o mLabs envia. A equipe sobe o PDF; o navegador lê
 * (assets/mlabs/*), reconhece indicadores, funis, gráficos e tabelas e manda tudo já estruturado para cá.
 * Aqui o relatório é gravado na tabela de relatórios (tipo "mlabs") e mostrado na página padrão da plataforma
 * (/relatorio/<token>/), com a análise da equipe e o texto editável.
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function lk_mlabs_networks() {
	return array( 'instagram' => 'Instagram', 'facebook' => 'Facebook', 'linkedin' => 'LinkedIn', 'youtube' => 'YouTube' );
}

/* -----------------------------------------------------------------------
 * Limpeza do que chega do navegador
 * -------------------------------------------------------------------- */

function lk_mlabs_s( $v, $max = 300 ) {
	return mb_substr( sanitize_text_field( (string) $v ), 0, $max );
}

function lk_mlabs_series_values( $vals ) {
	$out = array();
	foreach ( array_slice( (array) $vals, 0, 400 ) as $v ) {
		$out[] = is_numeric( $v ) ? (float) $v : null;
	}
	return $out;
}

/** Valida e limpa as seções lidas do PDF. Devolve array no formato guardado. */
function lk_mlabs_clean( $raw, $thumb_urls ) {
	$nets = lk_mlabs_networks();
	$out  = array( 'meta' => array(), 'sections' => array() );
	$m    = (array) ( $raw['meta'] ?? array() );
	$out['meta'] = array(
		'title'  => lk_mlabs_s( $m['title'] ?? '' ),
		'client' => lk_mlabs_s( $m['client'] ?? '' ),
		'from'   => preg_match( '/^\d{4}-\d{2}-\d{2}$/', (string) ( $m['from'] ?? '' ) ) ? $m['from'] : '',
		'to'     => preg_match( '/^\d{4}-\d{2}-\d{2}$/', (string) ( $m['to'] ?? '' ) ) ? $m['to'] : '',
	);
	foreach ( array_slice( (array) ( $raw['sections'] ?? array() ), 0, 60 ) as $s ) {
		if ( ! is_array( $s ) ) {
			continue;
		}
		$net = isset( $nets[ $s['network'] ?? '' ] ) ? $s['network'] : 'instagram';
		$sec = array( 'type' => (string) ( $s['type'] ?? '' ), 'network' => $net, 'title' => lk_mlabs_s( $s['title'] ?? '', 160 ) );
		switch ( $sec['type'] ) {
			case 'kpis':
				$sec['items'] = array();
				foreach ( array_slice( (array) ( $s['items'] ?? array() ), 0, 40 ) as $i ) {
					$sec['items'][] = array( 'label' => lk_mlabs_s( $i['label'] ?? '', 80 ), 'value' => lk_mlabs_s( $i['value'] ?? '', 30 ), 'delta' => lk_mlabs_s( $i['delta'] ?? '', 20 ), 'dir' => (int) ( $i['dir'] ?? 0 ) <=> 0 );
				}
				break;
			case 'funnel':
				$sec['steps'] = array();
				foreach ( array_slice( (array) ( $s['steps'] ?? array() ), 0, 12 ) as $i ) {
					$sec['steps'][] = array( 'label' => lk_mlabs_s( $i['label'] ?? '', 80 ), 'value' => lk_mlabs_s( $i['value'] ?? '', 30 ), 'prev' => lk_mlabs_s( $i['prev'] ?? '', 30 ), 'delta' => lk_mlabs_s( $i['delta'] ?? '', 20 ), 'dir' => (int) ( $i['dir'] ?? 0 ) <=> 0 );
				}
				break;
			case 'text':
				$sec['paragraphs'] = array();
				foreach ( array_slice( (array) ( $s['paragraphs'] ?? array() ), 0, 12 ) as $p ) {
					$sec['paragraphs'][] = mb_substr( sanitize_textarea_field( (string) $p ), 0, 1500 );
				}
				break;
			case 'line':
			case 'bars':
				$sec['labels'] = array_map( function ( $l ) { return lk_mlabs_s( $l, 40 ); }, array_slice( (array) ( $s['labels'] ?? array() ), 0, 400 ) );
				$sec['series'] = array();
				foreach ( array_slice( (array) ( $s['series'] ?? array() ), 0, 4 ) as $se ) {
					$sec['series'][] = array(
						'name'   => lk_mlabs_s( $se['name'] ?? '', 80 ),
						'color'  => preg_match( '/^#[0-9a-f]{6}$/i', (string) ( $se['color'] ?? '' ) ) ? $se['color'] : '#14E9EC',
						'axis'   => 'right' === ( $se['axis'] ?? '' ) ? 'right' : 'left',
						'values' => lk_mlabs_series_values( $se['values'] ?? array() ),
					);
				}
				break;
			case 'pie':
				$sec['slices'] = array();
				foreach ( array_slice( (array) ( $s['slices'] ?? array() ), 0, 12 ) as $sl ) {
					$sec['slices'][] = array( 'label' => lk_mlabs_s( $sl['label'] ?? '', 60 ), 'pct' => (float) ( $sl['pct'] ?? 0 ), 'count' => isset( $sl['count'] ) ? (int) $sl['count'] : null );
				}
				break;
			case 'table':
				$sec['firstLabel'] = lk_mlabs_s( $s['firstLabel'] ?? '', 80 );
				$sec['columns']    = array_map( function ( $c ) { return lk_mlabs_s( $c, 80 ); }, array_slice( (array) ( $s['columns'] ?? array() ), 0, 10 ) );
				$sec['rows']       = array();
				foreach ( array_slice( (array) ( $s['rows'] ?? array() ), 0, 60 ) as $r ) {
					$cells = array();
					foreach ( $sec['columns'] as $col ) {
						$cells[] = lk_mlabs_s( ( (array) ( $r['cells'] ?? array() ) )[ $col ] ?? '', 40 );
					}
					$ti = isset( $r['thumb'] ) && is_numeric( $r['thumb'] ) ? (int) $r['thumb'] : -1;
					$sec['rows'][] = array( 'caption' => lk_mlabs_s( $r['caption'] ?? '', 300 ), 'cells' => $cells, 'thumb' => $thumb_urls[ $ti ] ?? '' );
				}
				break;
			default:
				continue 2;
		}
		$out['sections'][] = $sec;
	}
	return $out;
}

/** Grava as miniaturas (data:image/jpeg;base64,…) em uploads/lk-mlabs e devolve as URLs na mesma ordem. */
function lk_mlabs_save_thumbs( $list ) {
	$up   = wp_upload_dir();
	$dir  = trailingslashit( $up['basedir'] ) . 'lk-mlabs';
	$url  = trailingslashit( $up['baseurl'] ) . 'lk-mlabs';
	$out  = array();
	if ( ! $list ) {
		return $out;
	}
	wp_mkdir_p( $dir );
	foreach ( array_slice( (array) $list, 0, 120 ) as $d ) {
		$out[] = '';
		if ( ! is_string( $d ) || 0 !== strpos( $d, 'data:image/jpeg;base64,' ) || strlen( $d ) > 200000 ) {
			continue;
		}
		$bin = base64_decode( substr( $d, 23 ), true ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions
		if ( ! $bin || 0 !== strpos( $bin, "\xFF\xD8\xFF" ) ) {
			continue;
		}
		$name = 'm-' . strtolower( wp_generate_password( 20, false ) ) . '.jpg';
		if ( false !== file_put_contents( $dir . '/' . $name, $bin ) ) { // phpcs:ignore WordPress.WP.AlternativeFunctions
			$out[ count( $out ) - 1 ] = $url . '/' . $name;
		}
	}
	return $out;
}

/* -----------------------------------------------------------------------
 * Ações
 * -------------------------------------------------------------------- */

function lk_do_mlabs_save() {
	lk_require( 'relatorios' );
	$client = lk_get( 'clients', lk_in( 'client_id', 'int' ) );
	if ( ! $client ) {
		lk_back( 'Escolha o cliente deste relatório.', 'erro' );
	}
	$raw = json_decode( (string) lk_in( 'payload', 'raw' ), true );
	if ( ! is_array( $raw ) || empty( $raw['sections'] ) ) {
		lk_back( 'Não recebi os dados do PDF. Suba o arquivo de novo.', 'erro' );
	}
	$thumbs = lk_mlabs_save_thumbs( json_decode( (string) lk_in( 'thumbs', 'raw' ), true ) );
	$data   = lk_mlabs_clean( $raw, $thumbs );
	if ( ! $data['sections'] ) {
		lk_back( 'O PDF não trouxe nada que eu reconheça.', 'erro' );
	}
	$data['source'] = 'mlabs';
	$ym = preg_match( '/^\d{4}-\d{2}$/', lk_in( 'period' ) ) ? lk_in( 'period' ) : ( $data['meta']['from'] ? substr( $data['meta']['from'], 0, 7 ) : gmdate( 'Y-m', strtotime( 'first day of last month' ) ) );
	$old = lk_rows( 'reports', 'client_id = %d AND period = %s AND kind = %s', array( $client->id, $ym, 'mlabs' ) );
	if ( $old ) {
		$prev = lk_json( $old[0]->data );
		$data['edited_text'] = $prev['edited_text'] ?? array();
		lk_update( 'reports', $old[0]->id, array( 'data' => wp_json_encode( $data ) ) );
		$id = $old[0]->id;
	} else {
		$id = lk_insert( 'reports', array( 'client_id' => $client->id, 'kind' => 'mlabs', 'period' => $ym, 'token' => strtolower( wp_generate_password( 20, false ) ), 'data' => wp_json_encode( $data ), 'status' => 'rascunho' ) );
	}
	lk_back( 'Relatório criado a partir do PDF do mLabs. Revise, escreva a análise e publique.', 'ok', lk_panel_url( 'relatorio', $id ) );
}

/** Edição do relatório do mLabs: análise, textos, seções escondidas e publicação. */
function lk_do_mlabs_edit() {
	lk_require( 'relatorios' );
	$r = lk_get( 'reports', lk_in( 'id', 'int' ) );
	if ( ! $r || 'mlabs' !== $r->kind ) {
		lk_back( 'Relatório não encontrado.', 'erro' );
	}
	$d      = lk_json( $r->data );
	$show = array();
	foreach ( (array) ( $_POST['show'] ?? array() ) as $i ) { // phpcs:ignore WordPress.Security.NonceVerification
		$show[] = (int) $i;
	}
	$d['hidden'] = array_values( array_diff( array_keys( (array) ( $d['sections'] ?? array() ) ), $show ) );
	$texts = array();
	foreach ( (array) ( $_POST['text'] ?? array() ) as $i => $t ) { // phpcs:ignore WordPress.Security.NonceVerification
		$texts[ (int) $i ] = mb_substr( sanitize_textarea_field( wp_unslash( $t ) ), 0, 6000 );
	}
	$d['edited_text'] = $texts;
	lk_update( 'reports', $r->id, array( 'notes' => lk_in( 'notes', 'textarea' ), 'data' => wp_json_encode( $d ), 'status' => lk_in( 'publicar', 'bool' ) ? 'publicado' : 'rascunho' ) );
	if ( lk_in( 'enviar', 'bool' ) ) {
		$c = lk_get( 'clients', $r->client_id );
		if ( $c && is_email( $c->email ) ) {
			lk_update( 'reports', $r->id, array( 'status' => 'publicado', 'sent_at' => lk_now() ) );
			lk_mail( $c->email, 'Seu relatório de ' . lk_month_label( $r->period ), 'Seu relatório de ' . lk_month_label( $r->period ), '<p>Olá, ' . esc_html( strtok( (string) $c->name, ' ' ) ) . '! Preparamos o relatório do mês com os resultados das redes sociais.</p>', array(), 'Ver o relatório', lk_report_url( $r ) );
			lk_back( 'Relatório enviado para o cliente.' );
		}
		lk_back( 'O cliente não tem e-mail na ficha. Copie o link e mande pelo WhatsApp.', 'warn' );
	}
	lk_back( 'Relatório salvo.' );
}

/** Rótulo curto do tipo de relatório (lista de relatórios). */
function lk_report_kind_label( $r ) {
	return 'mlabs' === $r->kind ? 'mLabs' : 'Redes vinculadas';
}

/* -----------------------------------------------------------------------
 * Desenho do relatório (SVG e HTML da página pública)
 * -------------------------------------------------------------------- */

function lk_mlabs_num( $v ) {
	return is_numeric( $v ) ? number_format( (float) $v, ( floor( (float) $v ) === (float) $v ) ? 0 : 1, ',', '.' ) : (string) $v;
}

function lk_mlabs_delta_html( $delta, $dir ) {
	if ( '' === (string) $delta ) {
		return '';
	}
	$cls = $dir > 0 ? 'up' : ( $dir < 0 ? 'down' : 'flat' );
	$ico = $dir > 0 ? '▲ ' : ( $dir < 0 ? '▼ ' : '' );
	return '<em class="rv ' . $cls . '">' . $ico . esc_html( $delta ) . '</em>';
}

function lk_mlabs_nice_max( $v ) {
	if ( $v <= 0 ) {
		return 1;
	}
	$p = pow( 10, floor( log10( $v ) ) );
	foreach ( array( 1, 1.5, 2, 2.5, 3, 4, 5, 6, 8, 10 ) as $m ) {
		if ( $v <= $m * $p ) {
			return $m * $p;
		}
	}
	return 10 * $p;
}

function lk_mlabs_label_short( $l ) {
	if ( preg_match( '#^(\d{2})/(\d{2})/\d{4}$#', $l, $m ) ) {
		return $m[1] . '/' . $m[2];
	}
	return $l;
}

/** Gráfico de linhas (1 ou 2 séries; a 2ª pode ter eixo próprio à direita). */
function lk_mlabs_svg_line( $sec ) {
	$W = 1000; $H = 320; $L = 58; $R = 58; $T = 16; $B = 38;
	$series = array_values( array_filter( $sec['series'], function ( $s ) { return array_filter( $s['values'], 'is_numeric' ); } ) );
	if ( ! $series ) {
		return '';
	}
	$n    = count( $series[0]['values'] );
	$dual = false;
	foreach ( $series as $s ) {
		if ( 'right' === $s['axis'] ) {
			$dual = true;
		}
	}
	$pal  = array( '#14E9EC', '#FF9A3D', '#8FA8FF', '#7BE08A' );
	$max  = array( 'left' => 0, 'right' => 0 );
	$min  = array( 'left' => PHP_INT_MAX, 'right' => PHP_INT_MAX );
	foreach ( $series as $s ) {
		$ax = $dual ? $s['axis'] : 'left';
		foreach ( $s['values'] as $v ) {
			if ( is_numeric( $v ) ) {
				$max[ $ax ] = max( $max[ $ax ], $v );
				$min[ $ax ] = min( $min[ $ax ], $v );
			}
		}
	}
	$o = '<svg viewBox="0 0 ' . $W . ' ' . $H . '" role="img" aria-label="' . esc_attr( $sec['title'] ) . '">';
	$axes = $dual ? array( 'left', 'right' ) : array( 'left' );
	foreach ( $axes as $ax ) {
		if ( PHP_INT_MAX === $min[ $ax ] ) {
			$min[ $ax ] = 0;
		}
		// Séries que ficam muito acima de zero (ex.: seguidores) usam uma faixa mais justa.
		$base = ( $max[ $ax ] > 0 && $min[ $ax ] > $max[ $ax ] * 0.6 ) ? floor( $min[ $ax ] - ( $max[ $ax ] - $min[ $ax ] ) * 0.4 ) : 0;
		$top  = $base + lk_mlabs_nice_max( max( 1, $max[ $ax ] - $base ) );
		if ( $max[ $ax ] === $min[ $ax ] && $max[ $ax ] > 0 ) { // série reta (ex.: seguidores da página que não mudaram)
			$base = floor( $max[ $ax ] ) - 2;
			$top  = $base + 4;
		}
		$min[ $ax ] = $base;
		$max[ $ax ] = $top;
	}
	for ( $g = 0; $g <= 4; $g++ ) {
		$y = $T + ( $H - $T - $B ) * $g / 4;
		$o .= '<line x1="' . $L . '" x2="' . ( $W - $R ) . '" y1="' . round( $y, 1 ) . '" y2="' . round( $y, 1 ) . '" stroke="rgba(255,255,255,.08)"/>';
		foreach ( $axes as $ai => $ax ) {
			$val = $max[ $ax ] - ( $max[ $ax ] - $min[ $ax ] ) * $g / 4;
			$o  .= '<text x="' . ( 0 === $ai ? $L - 8 : $W - $R + 8 ) . '" y="' . round( $y + 4, 1 ) . '" fill="#7f8b95" font-size="12" text-anchor="' . ( 0 === $ai ? 'end' : 'start' ) . '">' . esc_html( lk_mlabs_num( round( $val ) ) ) . '</text>';
		}
	}
	foreach ( $series as $si => $s ) {
		$ax  = $dual ? $s['axis'] : 'left';
		$col = $pal[ $si % 4 ];
		$pts = array();
		foreach ( $s['values'] as $i => $v ) {
			if ( ! is_numeric( $v ) ) {
				continue;
			}
			$x     = $L + ( $W - $L - $R ) * $i / max( 1, $n - 1 );
			$y     = $T + ( $H - $T - $B ) * ( 1 - ( $v - $min[ $ax ] ) / max( 1e-9, $max[ $ax ] - $min[ $ax ] ) );
			$pts[] = round( $x, 1 ) . ',' . round( $y, 1 );
		}
		if ( count( $pts ) < 2 ) {
			continue;
		}
		if ( 0 === $si ) {
			$o .= '<defs><linearGradient id="lg' . esc_attr( substr( md5( $sec['title'] ), 0, 5 ) ) . '" x1="0" x2="0" y1="0" y2="1"><stop offset="0" stop-color="' . $col . '" stop-opacity=".30"/><stop offset="1" stop-color="' . $col . '" stop-opacity="0"/></linearGradient></defs>';
			$o .= '<polygon fill="url(#lg' . esc_attr( substr( md5( $sec['title'] ), 0, 5 ) ) . ')" points="' . $L . ',' . ( $H - $B ) . ' ' . implode( ' ', $pts ) . ' ' . ( $W - $R ) . ',' . ( $H - $B ) . '"/>';
		}
		$o .= '<polyline fill="none" stroke="' . $col . '" stroke-width="3" stroke-linejoin="round" stroke-linecap="round" points="' . implode( ' ', $pts ) . '"/>';
	}
	$labels = $sec['labels'];
	$step   = max( 1, (int) ceil( $n / 8 ) );
	foreach ( $labels as $i => $lab ) {
		if ( '' === $lab || ( ( $i % $step ) || $i > $n - 1 - ceil( $step / 2 ) ) && $i !== $n - 1 ) {
			continue;
		}
		$x  = $L + ( $W - $L - $R ) * $i / max( 1, $n - 1 );
		$o .= '<text x="' . round( $x, 1 ) . '" y="' . ( $H - 10 ) . '" fill="#7f8b95" font-size="12" text-anchor="middle">' . esc_html( lk_mlabs_label_short( $lab ) ) . '</text>';
	}
	$o .= '</svg>';
	$leg = '';
	foreach ( $series as $si => $s ) {
		$leg .= '<span><i style="background:' . $pal[ $si % 4 ] . '"></i>' . esc_html( $s['name'] ? $s['name'] : 'Série ' . ( $si + 1 ) ) . '</span>';
	}
	return '<div class="rep-svg">' . $o . '<div class="rep-legend">' . $leg . '</div></div>';
}

/** Barras: na vertical quando os nomes são curtos; em lista horizontal quando são longos. */
function lk_mlabs_bars( $sec ) {
	$s = $sec['series'][0] ?? null;
	if ( ! $s ) {
		return '';
	}
	$vals = array_map( function ( $v ) { return is_numeric( $v ) ? (float) $v : 0; }, $s['values'] );
	$max  = max( 1, max( $vals ) );
	$long = false;
	foreach ( $sec['labels'] as $l ) {
		if ( mb_strlen( $l ) > 9 ) {
			$long = true;
		}
	}
	if ( $long ) {
		$o = '<div class="rep-svg"><div class="rep-hbars">';
		foreach ( $sec['labels'] as $i => $l ) {
			$o .= '<div class="rep-hb"><span>' . esc_html( preg_replace( '/,\s*(SP|Brazil|Brasil)(,\s*Brazil)?$/', '', $l ) ) . '</span><b style="width:' . round( $vals[ $i ] / $max * 100 ) . '%"></b><span>' . esc_html( lk_mlabs_num( $vals[ $i ] ) ) . '</span></div>';
		}
		return $o . '</div></div>';
	}
	$W = 520; $H = 260; $L = 10; $T = 26; $B = 34; $n = count( $vals );
	$bw = ( $W - $L * 2 ) / max( 1, $n );
	$o  = '<svg viewBox="0 0 ' . $W . ' ' . $H . '" role="img" aria-label="' . esc_attr( $sec['title'] ) . '">';
	foreach ( $vals as $i => $v ) {
		$h  = ( $H - $T - $B ) * $v / $max;
		$x  = $L + $bw * $i + $bw * 0.18;
		$y  = $H - $B - $h;
		$o .= '<rect x="' . round( $x, 1 ) . '" y="' . round( $y, 1 ) . '" width="' . round( $bw * 0.64, 1 ) . '" height="' . round( max( 1, $h ), 1 ) . '" rx="6" fill="#14E9EC" fill-opacity=".85"/>';
		$o .= '<text x="' . round( $x + $bw * 0.32, 1 ) . '" y="' . round( $y - 7, 1 ) . '" fill="#e3eaef" font-size="13" font-weight="700" text-anchor="middle">' . esc_html( lk_mlabs_num( $v ) ) . '</text>';
		$o .= '<text x="' . round( $x + $bw * 0.32, 1 ) . '" y="' . ( $H - 12 ) . '" fill="#7f8b95" font-size="12" text-anchor="middle">' . esc_html( $sec['labels'][ $i ] ?? '' ) . '</text>';
	}
	return '<div class="rep-svg">' . $o . '</svg></div>';
}

function lk_mlabs_pie( $sec ) {
	$cols = array( '#14E9EC', '#FF9A3D', '#8FA8FF', '#7BE08A', '#F36C8A' );
	$tot  = max( 0.0001, array_sum( wp_list_pluck( $sec['slices'], 'pct' ) ) );
	$r    = 70; $c = 2 * M_PI * $r; $off = 0;
	$o    = '<svg viewBox="0 0 200 200" role="img" aria-label="' . esc_attr( $sec['title'] ) . '" style="max-width:260px;margin:0 auto"><g transform="rotate(-90 100 100)">';
	$leg  = '';
	foreach ( $sec['slices'] as $i => $sl ) {
		$len = $c * $sl['pct'] / $tot;
		$o  .= '<circle cx="100" cy="100" r="' . $r . '" fill="none" stroke="' . $cols[ $i % 5 ] . '" stroke-width="34" stroke-dasharray="' . round( $len, 2 ) . ' ' . round( $c - $len, 2 ) . '" stroke-dashoffset="' . round( -$off, 2 ) . '"/>';
		$off += $len;
		$leg .= '<span><i style="background:' . $cols[ $i % 5 ] . '"></i>' . esc_html( $sl['label'] ) . ' · ' . esc_html( lk_mlabs_num( $sl['pct'] ) ) . '%' . ( $sl['count'] ? ' (' . esc_html( lk_mlabs_num( $sl['count'] ) ) . ')' : '' ) . '</span>';
	}
	return '<div class="rep-svg">' . $o . '</g></svg><div class="rep-legend" style="justify-content:center">' . $leg . '</div></div>';
}

function lk_mlabs_funnel( $sec ) {
	$o = '<div class="rep-fun">';
	foreach ( $sec['steps'] as $i => $st ) {
		$w  = max( 38, 100 - $i * 17 );
		$o .= '<div class="rep-fun-step">' . lk_mlabs_delta_html( $st['delta'], $st['dir'] ) . '<div class="rep-fun-v">' . esc_html( $st['value'] ) . '</div><div class="rep-fun-l">' . esc_html( $st['label'] ) . ( '' !== $st['prev'] ? ' · período anterior: ' . esc_html( $st['prev'] ) : '' ) . '</div><div class="rep-fun-bar" style="width:' . $w . '%"></div></div>';
	}
	return $o . '</div>';
}

/** Linhas de uma tabela (com miniatura quando houver). $rows já fatiado. */
function lk_mlabs_table_rows( $sec, $rows ) {
	$cols  = count( $sec['columns'] );
	$thumb = false;
	foreach ( $sec['rows'] as $r ) {
		if ( $r['thumb'] ) {
			$thumb = true;
		}
	}
	$grid = 'grid-template-columns:minmax(220px,2.6fr) repeat(' . max( 1, $cols ) . ',minmax(70px,1fr))';
	$o    = '<div class="rep-table"><div class="rep-mt rep-mt--h" style="' . $grid . '"><span>' . esc_html( $sec['firstLabel'] ) . '</span>';
	foreach ( $sec['columns'] as $c ) {
		$o .= '<span class="num">' . esc_html( $c ) . '</span>';
	}
	$o .= '</div>';
	foreach ( $rows as $r ) {
		$o .= '<div class="rep-mt" style="' . $grid . '"><span class="rep-tp">' . ( $r['thumb'] ? '<img src="' . esc_url( $r['thumb'] ) . '" alt="" loading="lazy">' : ( $thumb ? '<img alt="" style="visibility:hidden">' : '' ) ) . '<span>' . esc_html( $r['caption'] ) . '</span></span>';
		foreach ( $r['cells'] as $v ) {
			$o .= '<span class="num">' . esc_html( $v ) . '</span>';
		}
		$o .= '</div>';
	}
	return $o . '</div>';
}

/** Monta as telas (slides) do relatório. Devolve lista de HTML. */
function lk_mlabs_slides( $r, $d ) {
	$nets   = lk_mlabs_networks();
	$hidden = array_map( 'intval', (array) ( $d['hidden'] ?? array() ) );
	$edit   = (array) ( $d['edited_text'] ?? array() );
	$slides = array();
	$buf    = array(); // gráficos pequenos esperando par
	$flush  = function ( $net, $title ) use ( &$buf, &$slides, $nets ) {
		if ( ! $buf ) {
			return;
		}
		$slides[] = '<section class="rep-s"><span class="rep-eye rep-net rep-net--' . esc_attr( $net ) . '"><i></i>' . esc_html( $nets[ $net ] ?? $net ) . '</span><h2>' . esc_html( $title ) . '</h2><div class="rep-two">' . implode( '', $buf ) . '</div></section>';
		$buf = array();
	};
	$net_prev = '';
	foreach ( $d['sections'] as $i => $s ) {
		if ( in_array( $i, $hidden, true ) ) {
			continue;
		}
		$net   = $s['network'];
		$eye   = '<span class="rep-eye rep-net rep-net--' . esc_attr( $net ) . '"><i></i>' . esc_html( $nets[ $net ] ?? $net ) . '</span>';
		$small = in_array( $s['type'], array( 'bars', 'pie' ), true ) && ( 'pie' === $s['type'] || ! array_filter( $s['labels'], function ( $l ) { return mb_strlen( $l ) > 9; } ) );
		if ( $buf && ( ! $small || $net_prev !== $net ) ) {
			$flush( $net_prev, 'Perfil dos seguidores' );
		}
		$net_prev = $net;
		switch ( $s['type'] ) {
			case 'kpis':
				$k = '';
				foreach ( $s['items'] as $it ) {
					$k .= '<div class="rep-kpi"><strong>' . esc_html( $it['value'] ) . '</strong><span>' . esc_html( $it['label'] ) . '</span><br>' . lk_mlabs_delta_html( $it['delta'], $it['dir'] ) . '</div>';
				}
				$slides[] = '<section class="rep-s">' . $eye . '<h2>' . esc_html( $s['title'] ? $s['title'] : 'Visão geral' ) . '</h2><div class="rep-kpis">' . $k . '</div><p class="rep-note">Variação em relação ao período anterior.</p></section>';
				break;
			case 'funnel':
				$slides[] = '<section class="rep-s">' . $eye . '<h2>' . esc_html( $s['title'] ? $s['title'] : 'Funil de engajamento' ) . '</h2>' . lk_mlabs_funnel( $s ) . '</section>';
				break;
			case 'text':
				$txt = isset( $edit[ $i ] ) && '' !== trim( $edit[ $i ] ) ? array_filter( array_map( 'trim', preg_split( '/\n{2,}|\r\n\r\n/', $edit[ $i ] ) ) ) : $s['paragraphs'];
				$slides[] = '<section class="rep-s">' . $eye . '<h2>' . esc_html( $s['title'] ? $s['title'] : 'Resumo do período' ) . '</h2><div class="rep-paras">' . implode( '', array_map( function ( $p ) { return '<p>' . esc_html( $p ) . '</p>'; }, $txt ) ) . '</div></section>';
				break;
			case 'line':
				$slides[] = '<section class="rep-s">' . $eye . '<h2>' . esc_html( $s['title'] ) . '</h2>' . lk_mlabs_svg_line( $s ) . '</section>';
				break;
			case 'bars':
			case 'pie':
				if ( $small ) {
					$buf[] = '<div><h3 class="rep-cap" style="margin-bottom:8px;font-size:15px;color:#e3eaef">' . esc_html( $s['title'] ) . '</h3>' . ( 'pie' === $s['type'] ? lk_mlabs_pie( $s ) : lk_mlabs_bars( $s ) ) . '</div>';
					if ( count( $buf ) >= 2 ) {
						$flush( $net, $s['title'] ? 'Perfil e atividade' : 'Perfil' );
					}
				} else {
					$slides[] = '<section class="rep-s">' . $eye . '<h2>' . esc_html( $s['title'] ) . '</h2>' . lk_mlabs_bars( $s ) . '</section>';
				}
				break;
			case 'table':
				$per  = $s['rows'] && $s['rows'][0]['thumb'] ? 5 : 10;
				$part = array_chunk( $s['rows'], $per );
				foreach ( $part as $pi => $rows ) {
					$slides[] = '<section class="rep-s">' . $eye . '<h2>' . esc_html( $s['title'] ) . ( count( $part ) > 1 ? ' <small style="font-size:.45em;color:#9aa7b2">' . ( $pi + 1 ) . '/' . count( $part ) . '</small>' : '' ) . '</h2>' . lk_mlabs_table_rows( $s, $rows ) . '</section>';
				}
				break;
		}
	}
	$flush( $net_prev, 'Perfil dos seguidores' );
	return $slides;
}

<?php
/**
 * Peças visuais do dashboard (gráficos em SVG, sem biblioteca): linha/área, rosca e barras.
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Caminho suave (Catmull-Rom → Bézier) por uma lista de pontos [x, y]. */
function lk_dash_path( $pts, $ymin = null, $ymax = null ) {
	$n = count( $pts );
	if ( $n < 2 ) {
		return '';
	}
	$d = 'M' . round( $pts[0][0], 1 ) . ',' . round( $pts[0][1], 1 );
	for ( $i = 0; $i < $n - 1; $i++ ) {
		$p0 = $pts[ max( 0, $i - 1 ) ];
		$p1 = $pts[ $i ];
		$p2 = $pts[ $i + 1 ];
		$p3 = $pts[ min( $n - 1, $i + 2 ) ];
		$c1 = array( $p1[0] + ( $p2[0] - $p0[0] ) / 6, $p1[1] + ( $p2[1] - $p0[1] ) / 6 );
		$c2 = array( $p2[0] - ( $p3[0] - $p1[0] ) / 6, $p2[1] - ( $p3[1] - $p1[1] ) / 6 );
		if ( null !== $ymin ) { // não deixa a curva passar do gráfico (sem valores negativos)
			$c1[1] = min( $ymax, max( $ymin, $c1[1] ) );
			$c2[1] = min( $ymax, max( $ymin, $c2[1] ) );
		}
		$d .= ' C' . round( $c1[0], 1 ) . ',' . round( $c1[1], 1 ) . ' ' . round( $c2[0], 1 ) . ',' . round( $c2[1], 1 ) . ' ' . round( $p2[0], 1 ) . ',' . round( $p2[1], 1 );
	}
	return $d;
}

/**
 * Gráfico de linhas com área. $series = array( array( 'name', 'values'[], 'class' ) ), $labels = rótulos do eixo X.
 * $marker = índice do ponto de "hoje" (linha vertical).
 */
function lk_dash_line( $series, $labels, $marker = -1 ) {
	$w = 640; $h = 220; $pl = 30; $pr = 10; $pt = 14; $pb = 28;
	$max = 1;
	foreach ( $series as $s ) {
		$max = max( $max, max( $s['values'] ) );
	}
	$max = (int) ceil( $max / 2 ) * 2 ?: 2;
	$n   = count( $labels );
	$x   = function ( $i ) use ( $n, $w, $pl, $pr ) { return $pl + ( $w - $pl - $pr ) * ( $n > 1 ? $i / ( $n - 1 ) : 0 ); };
	$y   = function ( $v ) use ( $max, $h, $pt, $pb ) { return $pt + ( $h - $pt - $pb ) * ( 1 - $v / $max ); };
	$o   = '<svg class="dsh-line" viewBox="0 0 ' . $w . ' ' . $h . '" role="img" aria-label="Publicações por dia" preserveAspectRatio="none"><defs>';
	foreach ( $series as $k => $s ) {
		$o .= '<linearGradient id="dg' . $k . '" x1="0" y1="0" x2="0" y2="1"><stop offset="0%" class="dsh-stop dsh-stop--' . esc_attr( $s['class'] ) . '"/><stop offset="100%" class="dsh-stop dsh-stop--' . esc_attr( $s['class'] ) . ' is-end"/></linearGradient>';
	}
	$o .= '</defs>';
	for ( $g = 0; $g <= 4; $g++ ) {
		$gy  = $pt + ( $h - $pt - $pb ) * $g / 4;
		$o  .= '<line class="dsh-grid" x1="' . $pl . '" x2="' . ( $w - $pr ) . '" y1="' . round( $gy, 1 ) . '" y2="' . round( $gy, 1 ) . '"/><text class="dsh-axis" x="' . ( $pl - 6 ) . '" y="' . round( $gy + 4, 1 ) . '" text-anchor="end">' . round( $max * ( 1 - $g / 4 ) ) . '</text>';
	}
	foreach ( $labels as $i => $lab ) {
		if ( '' !== $lab ) {
			$o .= '<text class="dsh-axis" x="' . round( $x( $i ), 1 ) . '" y="' . ( $h - 8 ) . '" text-anchor="' . ( $i >= $n - 3 ? 'end' : 'middle' ) . '">' . esc_html( $lab ) . '</text>';
		}
	}
	if ( $marker >= 0 ) {
		$o .= '<line class="dsh-today" x1="' . round( $x( $marker ), 1 ) . '" x2="' . round( $x( $marker ), 1 ) . '" y1="' . $pt . '" y2="' . ( $h - $pb ) . '"/>';
	}
	foreach ( $series as $k => $s ) {
		$pts = array();
		foreach ( $s['values'] as $i => $v ) {
			$pts[] = array( $x( $i ), $y( $v ) );
		}
		$line = lk_dash_path( $pts, $pt, $h - $pb );
		$o   .= '<path d="' . $line . ' L' . round( $x( $n - 1 ), 1 ) . ',' . ( $h - $pb ) . ' L' . round( $x( 0 ), 1 ) . ',' . ( $h - $pb ) . ' Z" fill="url(#dg' . $k . ')" class="dsh-area"/>';
		$o   .= '<path d="' . $line . '" class="dsh-stroke dsh-stroke--' . esc_attr( $s['class'] ) . '" fill="none"/>';
	}
	return $o . '</svg>';
}

/** Rosca: $slices = array( array( label, value, class ) ). Centro: total. */
function lk_dash_donut( $slices, $center_label = '' ) {
	$total = array_sum( array_column( $slices, 1 ) );
	$r     = 42; $c = 2 * M_PI * $r; $off = 0;
	$o     = '<svg class="dsh-donut" viewBox="0 0 120 120" role="img" aria-label="Posts por etapa"><circle class="dsh-donut-bg" cx="60" cy="60" r="' . $r . '"/>';
	if ( $total > 0 ) {
		foreach ( $slices as $s ) {
			if ( $s[1] <= 0 ) {
				continue;
			}
			$len  = $c * $s[1] / $total;
			$o   .= '<circle class="dsh-donut-seg dsh-seg--' . esc_attr( $s[2] ) . '" cx="60" cy="60" r="' . $r . '" stroke-dasharray="' . round( max( 0, $len - 2 ), 2 ) . ' ' . round( $c, 2 ) . '" stroke-dashoffset="' . round( -$off, 2 ) . '" transform="rotate(-90 60 60)"/>';
			$off += $len;
		}
	}
	return $o . '<text x="60" y="58" text-anchor="middle" class="dsh-donut-n">' . (int) $total . '</text><text x="60" y="74" text-anchor="middle" class="dsh-donut-l">' . esc_html( $center_label ) . '</text></svg>';
}

/** Barras: $bars = array( array( label, value ) ). */
function lk_dash_bars( $bars, $class = 'a' ) {
	$w = 300; $h = 130; $max = max( 1, max( array_column( $bars, 1 ) ) ); $n = count( $bars ); $gap = 10; $bw = ( $w - $gap * ( $n + 1 ) ) / max( 1, $n );
	$o = '<svg class="dsh-bars" viewBox="0 0 ' . $w . ' ' . ( $h + 20 ) . '" role="img" aria-label="Tarefas concluídas por dia">';
	foreach ( $bars as $i => $b ) {
		$bh = $b[1] > 0 ? max( 6, ( $h - 10 ) * $b[1] / $max ) : 3;
		$bx = $gap + $i * ( $bw + $gap );
		$o .= '<rect class="dsh-bar dsh-bar--' . esc_attr( $class ) . ( $b[1] ? '' : ' is-zero' ) . '" x="' . round( $bx, 1 ) . '" y="' . round( $h - $bh, 1 ) . '" width="' . round( $bw, 1 ) . '" height="' . round( $bh, 1 ) . '" rx="6"><title>' . esc_html( $b[0] . ': ' . $b[1] ) . '</title></rect>';
		$o .= '<text class="dsh-axis" x="' . round( $bx + $bw / 2, 1 ) . '" y="' . ( $h + 15 ) . '" text-anchor="middle">' . esc_html( $b[0] ) . '</text>';
	}
	return $o . '</svg>';
}

<?php
/**
 * Leitura do fatiador para a calculadora (gramas por filamento + tempo de impressão).
 *
 * 1. Arquivo fatiado do Bambu Studio / OrcaSlicer (.gcode.3mf ou .3mf exportado "fatiado"):
 *    Metadata/slice_info.config traz, por mesa, o tempo previsto (segundos) e os filamentos (tipo, cor, gramas).
 * 2. G-code (.gcode): comentários do cabeçalho ("total estimated time", "filament used [g]").
 * 3. Print da tela do fatiador: a IA do Claude (visão) lê os números. Precisa da chave da Anthropic.
 *
 * POST /wp-json/ap/v1/slicer (multipart "file") → { hours, minutes, filaments: [ {grams, type, color} ], source }
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_action(
	'rest_api_init',
	function () {
		register_rest_route(
			'ap/v1',
			'/slicer',
			array(
				'methods'             => 'POST',
				'callback'            => 'ap_api_slicer',
				'permission_callback' => function () {
					return ap_is_team();
				},
			)
		);
	}
);

function ap_api_slicer( WP_REST_Request $r ) {
	$files = $r->get_file_params();
	if ( empty( $files['file']['tmp_name'] ) ) {
		return new WP_Error( 'ap', 'Envie o arquivo ou o print.', array( 'status' => 400 ) );
	}
	$f    = $files['file'];
	$name = strtolower( (string) $f['name'] );
	if ( $f['size'] > 80 * MB_IN_BYTES ) {
		return new WP_Error( 'ap', 'Arquivo grande demais (máx. 80 MB).', array( 'status' => 400 ) );
	}
	if ( preg_match( '/\.3mf$/', $name ) ) {
		$res = ap_slicer_3mf( $f['tmp_name'] );
	} elseif ( preg_match( '/\.(gcode|gco|bgcode)$/', $name ) ) {
		$res = ap_slicer_gcode( $f['tmp_name'] );
	} elseif ( preg_match( '/\.(png|jpe?g|webp)$/', $name ) ) {
		$res = ap_slicer_image( $f['tmp_name'], $name );
	} else {
		return new WP_Error( 'ap', 'Formato não reconhecido. Use .gcode.3mf, .gcode ou um print (PNG/JPG).', array( 'status' => 400 ) );
	}
	if ( is_wp_error( $res ) ) {
		$res->add_data( array( 'status' => 400 ) );
		return $res;
	}
	$res['matches'] = ap_slicer_match( $res['filaments'] );
	return $res;
}

function ap_slicer_result( $seconds, $filaments, $source, $plates = 1 ) {
	$seconds = max( 0, (int) round( $seconds ) );
	return array(
		'hours'     => (int) floor( $seconds / 3600 ),
		'minutes'   => (int) round( ( $seconds % 3600 ) / 60 ),
		'seconds'   => $seconds,
		'filaments' => array_values( $filaments ),
		'plates'    => $plates,
		'source'    => $source,
	);
}

/**
 * .gcode.3mf: soma todas as mesas fatiadas.
 */
function ap_slicer_3mf( $path ) {
	if ( ! class_exists( 'ZipArchive' ) ) {
		return new WP_Error( 'ap', 'O servidor não tem ZipArchive para abrir o .3mf. Use o print da tela ou digite os números.' );
	}
	$zip = new ZipArchive();
	if ( true !== $zip->open( $path ) ) {
		return new WP_Error( 'ap', 'Não consegui abrir o arquivo .3mf.' );
	}
	$xml = $zip->getFromName( 'Metadata/slice_info.config' );
	if ( ! $xml ) {
		// Sem fatiamento salvo: tenta o g-code de dentro.
		for ( $i = 0; $i < $zip->numFiles; $i++ ) {
			$n = $zip->getNameIndex( $i );
			if ( preg_match( '#^Metadata/plate_\d+\.gcode$#', $n ) ) {
				require_once ABSPATH . 'wp-admin/includes/file.php';
				$tmp = wp_tempnam( 'ap-gcode' );
				file_put_contents( $tmp, $zip->getFromIndex( $i ) ); // phpcs:ignore WordPress.WP.AlternativeFunctions
				$zip->close();
				$r = ap_slicer_gcode( $tmp );
				wp_delete_file( $tmp );
				return $r;
			}
		}
		$zip->close();
		return new WP_Error( 'ap', 'Esse .3mf não está fatiado. No Bambu Studio, fatie e use "Exportar arquivo de mesa fatiada" (.gcode.3mf).' );
	}
	$zip->close();
	$prev = libxml_use_internal_errors( true );
	$doc  = simplexml_load_string( $xml );
	libxml_use_internal_errors( $prev );
	if ( ! $doc ) {
		return new WP_Error( 'ap', 'Não consegui ler as informações do fatiamento.' );
	}
	$seconds = 0;
	$fils    = array();
	$plates  = 0;
	foreach ( $doc->plate as $plate ) {
		$plates++;
		foreach ( $plate->metadata as $m ) {
			if ( 'prediction' === (string) $m['key'] ) {
				$seconds += (float) $m['value'];
			}
		}
		foreach ( $plate->filament as $fi ) {
			$key = strtolower( (string) $fi['type'] . (string) $fi['color'] );
			if ( ! isset( $fils[ $key ] ) ) {
				$fils[ $key ] = array( 'grams' => 0, 'type' => (string) $fi['type'], 'color' => (string) $fi['color'] );
			}
			$fils[ $key ]['grams'] += (float) $fi['used_g'];
		}
	}
	foreach ( $fils as $k => $v ) {
		$fils[ $k ]['grams'] = round( $v['grams'], 1 );
	}
	return ap_slicer_result( $seconds, $fils, 'Arquivo do fatiador (' . $plates . ' mesa' . ( $plates > 1 ? 's' : '' ) . ')', $plates );
}

/**
 * Converte "1d 2h 3m 4s" / "2h 5m" / "01:02:03" em segundos.
 */
function ap_parse_duration( $text ) {
	$text = strtolower( trim( $text ) );
	if ( preg_match( '/^(\d+):(\d{1,2}):(\d{1,2})$/', $text, $m ) ) {
		return $m[1] * 3600 + $m[2] * 60 + $m[3];
	}
	$s = 0;
	foreach ( array( 'd' => 86400, 'h' => 3600, 'm' => 60, 's' => 1 ) as $u => $mult ) {
		if ( preg_match( '/(\d+(?:[.,]\d+)?)\s*' . $u . '/', $text, $m ) ) {
			$s += (float) str_replace( ',', '.', $m[1] ) * $mult;
		}
	}
	return $s;
}

function ap_slicer_gcode( $path ) {
	$fh = fopen( $path, 'r' ); // phpcs:ignore WordPress.WP.AlternativeFunctions
	if ( ! $fh ) {
		return new WP_Error( 'ap', 'Não consegui abrir o g-code.' );
	}
	$head = fread( $fh, 400000 ); // phpcs:ignore WordPress.WP.AlternativeFunctions
	fseek( $fh, max( 0, filesize( $path ) - 200000 ) );
	$tail = fread( $fh, 200000 ); // phpcs:ignore WordPress.WP.AlternativeFunctions
	fclose( $fh ); // phpcs:ignore WordPress.WP.AlternativeFunctions
	$txt     = $head . "\n" . $tail;
	$seconds = 0;
	if ( preg_match( '/;\s*(?:total estimated time|estimated printing time[^=:]*|model printing time)\s*[:=]\s*([^\n;]+)/i', $txt, $m ) ) {
		$seconds = ap_parse_duration( $m[1] );
	} elseif ( preg_match( '/;TIME:(\d+)/', $txt, $m ) ) {
		$seconds = (int) $m[1];
	}
	$grams = array();
	if ( preg_match( '/;\s*(?:total )?filament used \[g\]\s*=\s*([\d.,\s]+)/i', $txt, $m ) ) {
		$grams = array_map( 'floatval', array_filter( array_map( 'trim', explode( ',', $m[1] ) ), 'strlen' ) );
	} elseif ( preg_match( '/;\s*total filament weight \[g\]\s*:\s*([\d.]+)/i', $txt, $m ) ) {
		$grams = array( (float) $m[1] );
	}
	$types  = preg_match( '/;\s*filament_type\s*=\s*([^\n]+)/i', $txt, $m ) ? array_map( 'trim', explode( ';', $m[1] ) ) : array();
	$colors = preg_match( '/;\s*(?:filament_colou?r|extruder_colour)\s*=\s*([^\n]+)/i', $txt, $m ) ? array_map( 'trim', explode( ';', $m[1] ) ) : array();
	if ( ! $seconds && ! $grams ) {
		return new WP_Error( 'ap', 'Não achei tempo nem gramas no g-code. Use o .gcode.3mf ou o print da tela.' );
	}
	$fils = array();
	foreach ( $grams as $i => $g ) {
		if ( $g > 0 ) {
			$fils[] = array( 'grams' => round( $g, 1 ), 'type' => $types[ $i ] ?? '', 'color' => $colors[ $i ] ?? '' );
		}
	}
	return ap_slicer_result( $seconds, $fils, 'G-code' );
}

/**
 * Print da tela: a IA lê os números. Só roda com a chave da Anthropic.
 */
function ap_slicer_image( $path, $name ) {
	$key = ap_decrypt( ap_setting( 'anthropic_key' ) );
	if ( ! $key ) {
		return new WP_Error( 'ap', 'Para ler prints, coloque a chave da Anthropic (IA do Claude) em Configurações. Ou suba o arquivo .gcode.3mf, que é lido sem IA.' );
	}
	$ext  = strtolower( pathinfo( $name, PATHINFO_EXTENSION ) );
	$mime = 'png' === $ext ? 'image/png' : ( 'webp' === $ext ? 'image/webp' : 'image/jpeg' );
	$data = base64_encode( file_get_contents( $path ) ); // phpcs:ignore WordPress.WP.AlternativeFunctions
	$ask  = 'Este é um print de um fatiador de impressão 3D (Bambu Studio, OrcaSlicer, Cura ou Prusa). Leia o tempo total de impressão e o consumo de filamento em gramas de cada filamento/cor. Responda SOMENTE com JSON no formato {"seconds": número, "filaments": [{"grams": número, "type": "PLA", "color": "#RRGGBB ou nome"}]}. Se só houver o total, devolva um item. Se não conseguir ler, {"error": "motivo"}.';
	$res  = wp_remote_post(
		'https://api.anthropic.com/v1/messages',
		array(
			'timeout' => 60,
			'headers' => array(
				'x-api-key'         => $key,
				'anthropic-version' => '2023-06-01',
				'content-type'      => 'application/json',
			),
			'body'    => wp_json_encode(
				array(
					'model'      => ap_setting( 'ai_model' ) ? ap_setting( 'ai_model' ) : 'claude-sonnet-5',
					'max_tokens' => 600,
					'messages'   => array(
						array(
							'role'    => 'user',
							'content' => array(
								array( 'type' => 'image', 'source' => array( 'type' => 'base64', 'media_type' => $mime, 'data' => $data ) ),
								array( 'type' => 'text', 'text' => $ask ),
							),
						),
					),
				)
			),
		)
	);
	if ( is_wp_error( $res ) ) {
		return new WP_Error( 'ap', 'Não consegui falar com a IA: ' . $res->get_error_message() );
	}
	$json = json_decode( wp_remote_retrieve_body( $res ), true );
	$text = isset( $json['content'][0]['text'] ) ? $json['content'][0]['text'] : '';
	if ( ! preg_match( '/\{.*\}/s', $text, $m ) ) {
		return new WP_Error( 'ap', isset( $json['error']['message'] ) ? 'IA: ' . $json['error']['message'] : 'A IA não conseguiu ler o print.' );
	}
	$out = json_decode( $m[0], true );
	if ( ! is_array( $out ) || ! empty( $out['error'] ) ) {
		return new WP_Error( 'ap', 'A IA não conseguiu ler: ' . ( $out['error'] ?? 'formato inesperado' ) . '. Digite os números.' );
	}
	$fils = array();
	foreach ( (array) ( $out['filaments'] ?? array() ) as $fi ) {
		if ( (float) ( $fi['grams'] ?? 0 ) > 0 ) {
			$fils[] = array( 'grams' => round( (float) $fi['grams'], 1 ), 'type' => (string) ( $fi['type'] ?? '' ), 'color' => (string) ( $fi['color'] ?? '' ) );
		}
	}
	return ap_slicer_result( (float) ( $out['seconds'] ?? 0 ), $fils, 'Print lido pela IA (confira os números)' );
}

/**
 * Sugere o carretel do estoque para cada filamento lido (mesmo material e cor mais próxima).
 */
function ap_slicer_match( $fils ) {
	$stock = ap_rows( 'filaments', 'active = 1' );
	$out   = array();
	foreach ( $fils as $i => $fi ) {
		$best = 0;
		$bd   = PHP_INT_MAX;
		foreach ( $stock as $s ) {
			$d = ap_color_distance( $fi['color'], $s->color_hex );
			if ( $fi['type'] && stripos( $s->material, preg_replace( '/[^a-z]/i', '', $fi['type'] ) ) === false ) {
				$d += 200000;
			}
			if ( $d < $bd ) {
				$bd   = $d;
				$best = (int) $s->id;
			}
		}
		$out[ $i ] = $best;
	}
	return $out;
}

function ap_color_distance( $a, $b ) {
	$rgb = function ( $h ) {
		$h = ltrim( (string) $h, '#' );
		if ( strlen( $h ) >= 6 && ctype_xdigit( substr( $h, 0, 6 ) ) ) {
			return array( hexdec( substr( $h, 0, 2 ) ), hexdec( substr( $h, 2, 2 ) ), hexdec( substr( $h, 4, 2 ) ) );
		}
		return null;
	};
	$x = $rgb( $a );
	$y = $rgb( $b );
	if ( ! $x || ! $y ) {
		return 100000;
	}
	return ( $x[0] - $y[0] ) ** 2 + ( $x[1] - $y[1] ) ** 2 + ( $x[2] - $y[2] ) ** 2;
}

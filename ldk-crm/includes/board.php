<?php
/**
 * Quadro de projetos: colunas editáveis direto no Kanban.
 *
 * - Etapas (Configurações → 02): o cliente vê, mandam e-mail ao avançar.
 * - Colunas internas (ex.: "Hoje"): só organizam o seu quadro. O projeto continua na etapa
 *   real (projects.status) e fica visualmente na coluna interna (projects.board_col).
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Colunas internas: slug => nome.
 */
function lk_board_internal() {
	$saved = get_option( 'lk_board_cols', array() );
	$out   = array();
	foreach ( (array) $saved as $row ) {
		if ( ! empty( $row['slug'] ) && ! empty( $row['name'] ) ) {
			$out[ $row['slug'] ] = $row['name'];
		}
	}
	return $out;
}

function lk_board_internal_save( $cols ) {
	$rows = array();
	foreach ( $cols as $slug => $name ) {
		$rows[] = array( 'slug' => $slug, 'name' => $name );
	}
	update_option( 'lk_board_cols', $rows, false );
}

/**
 * Slug único para uma coluna nova (sem colidir com etapas nem colunas internas).
 */
function lk_board_slug( $name, $ignore = '' ) {
	$base  = 'i-' . sanitize_title( $name );
	$slug  = $base;
	$taken = array_merge( array_keys( lk_columns() ), array_keys( lk_board_internal() ) );
	$n     = 2;
	while ( in_array( $slug, $taken, true ) && $slug !== $ignore ) {
		$slug = $base . '-' . $n++;
	}
	return $slug;
}

/**
 * POST /columns  { op: add|rename|delete|move, kind: stage|internal, slug, name, dir }
 */
function lk_api_columns( WP_REST_Request $r ) {
	if ( ! lk_can( 'projetos' ) ) {
		return new WP_Error( 'lk', 'Sem permissão.', array( 'status' => 403 ) );
	}
	global $wpdb;
	$op   = sanitize_key( $r['op'] );
	$kind = 'stage' === $r['kind'] ? 'stage' : 'internal';
	$slug = sanitize_key( $r['slug'] );
	$name = trim( wp_strip_all_tags( (string) $r['name'] ) );
	$name = mb_substr( preg_replace( '/[\r\n|]+/', ' ', $name ), 0, 40 );
	$pt   = lk_table( 'projects' );

	$stages   = lk_columns();
	$internal = lk_board_internal();

	if ( 'add' === $op ) {
		if ( '' === $name ) {
			return new WP_Error( 'lk', 'Dê um nome para a coluna.', array( 'status' => 400 ) );
		}
		if ( 'internal' === $kind ) {
			$new              = lk_board_slug( $name );
			$internal[ $new ] = $name;
			lk_board_internal_save( $internal );
			return array( 'ok' => true, 'slug' => $new, 'name' => $name, 'kind' => 'internal' );
		}
		// Etapa nova entra antes da última (a última continua sendo "entregue").
		$names = array_values( $stages );
		if ( in_array( sanitize_title( $name ), array_keys( $stages ), true ) ) {
			return new WP_Error( 'lk', 'Já existe uma etapa com esse nome.', array( 'status' => 400 ) );
		}
		array_splice( $names, max( 0, count( $names ) - 1 ), 0, array( $name ) );
		lk_board_save_stages( $names );
		return array( 'ok' => true, 'slug' => sanitize_title( $name ), 'name' => $name, 'kind' => 'stage' );
	}

	if ( 'rename' === $op ) {
		if ( '' === $name ) {
			return new WP_Error( 'lk', 'O nome não pode ficar vazio.', array( 'status' => 400 ) );
		}
		if ( 'internal' === $kind ) {
			if ( ! isset( $internal[ $slug ] ) ) {
				return new WP_Error( 'lk', 'Coluna não encontrada.', array( 'status' => 404 ) );
			}
			$internal[ $slug ] = $name;
			lk_board_internal_save( $internal );
			return array( 'ok' => true, 'slug' => $slug, 'name' => $name );
		}
		if ( ! isset( $stages[ $slug ] ) ) {
			return new WP_Error( 'lk', 'Etapa não encontrada.', array( 'status' => 404 ) );
		}
		$new = sanitize_title( $name );
		if ( $new !== $slug && isset( $stages[ $new ] ) ) {
			return new WP_Error( 'lk', 'Já existe uma etapa com esse nome.', array( 'status' => 400 ) );
		}
		$names = array();
		foreach ( $stages as $s => $n ) {
			$names[] = $s === $slug ? $name : $n;
		}
		lk_board_save_stages( $names );
		if ( $new !== $slug ) {
			$wpdb->update( $pt, array( 'status' => $new ), array( 'status' => $slug ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		}
		return array( 'ok' => true, 'slug' => $new, 'name' => $name );
	}

	if ( 'delete' === $op ) {
		if ( 'internal' === $kind ) {
			unset( $internal[ $slug ] );
			lk_board_internal_save( $internal );
			// Os projetos que estavam nela voltam para a coluna da etapa real.
			$wpdb->update( $pt, array( 'board_col' => '' ), array( 'board_col' => $slug ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			return array( 'ok' => true );
		}
		if ( count( $stages ) <= 2 ) {
			return new WP_Error( 'lk', 'O quadro precisa de pelo menos duas etapas.', array( 'status' => 400 ) );
		}
		$keys = array_keys( $stages );
		if ( end( $keys ) === $slug ) {
			return new WP_Error( 'lk', 'A última etapa marca o projeto como entregue e não pode ser excluída aqui.', array( 'status' => 400 ) );
		}
		$count = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM $pt WHERE status = %s AND archived = 0 AND kind <> 'hospedagem'", $slug ) ); // phpcs:ignore WordPress.DB.PreparedSQL
		if ( $count ) {
			return new WP_Error( 'lk', 'Mova os ' . $count . ' projeto(s) dessa etapa antes de excluir.', array( 'status' => 400 ) );
		}
		unset( $stages[ $slug ] );
		lk_board_save_stages( array_values( $stages ) );
		return array( 'ok' => true );
	}

	if ( 'move' === $op ) {
		$dir = 'left' === $r['dir'] ? -1 : 1;
		if ( 'internal' === $kind ) {
			$keys = array_keys( $internal );
			$i    = array_search( $slug, $keys, true );
			if ( false === $i || ! isset( $keys[ $i + $dir ] ) ) {
				return array( 'ok' => true );
			}
			list( $keys[ $i ], $keys[ $i + $dir ] ) = array( $keys[ $i + $dir ], $keys[ $i ] );
			$re = array();
			foreach ( $keys as $k ) {
				$re[ $k ] = $internal[ $k ];
			}
			lk_board_internal_save( $re );
			return array( 'ok' => true );
		}
		$names = array_values( $stages );
		$keys  = array_keys( $stages );
		$i     = array_search( $slug, $keys, true );
		$last  = count( $keys ) - 1;
		// A última etapa ("entregue") fica sempre no fim.
		if ( false === $i || $i === $last || ! isset( $keys[ $i + $dir ] ) || $i + $dir === $last ) {
			return array( 'ok' => true );
		}
		list( $names[ $i ], $names[ $i + $dir ] ) = array( $names[ $i + $dir ], $names[ $i ] );
		lk_board_save_stages( $names );
		return array( 'ok' => true );
	}

	return new WP_Error( 'lk', 'Ação inválida.', array( 'status' => 400 ) );
}

function lk_board_save_stages( $names ) {
	$s            = get_option( 'lk_settings', array() );
	$s            = is_array( $s ) ? $s : array();
	$s['colunas'] = implode( "\n", array_map( 'trim', $names ) );
	update_option( 'lk_settings', $s );
}

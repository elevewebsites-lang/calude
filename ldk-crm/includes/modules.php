<?php
/**
 * Módulos opcionais: carregamento, páginas de cada um e substitutos quando desligados.
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Módulo => [arquivos em includes/, páginas do painel].
 */
function lk_module_map() {
	return array(
		'estoque'   => array( array( 'stock', 'slicer' ), array( 'filamentos', 'insumos', 'compras', 'impressoras', 'calculadora' ) ),
		'frete'     => array( array( 'shipping' ), array( 'frete' ) ),
		'produtos'  => array( array( 'products' ), array( 'produtos', 'produto' ) ),
		'marketing' => array( array( 'marketing' ), array( 'marketing' ) ),
	);
}

function lk_module( $name ) {
	static $on = null;
	if ( null === $on ) {
		$id = lk_identity();
		$on = array_flip( (array) $id['modulos'] );
	}
	return isset( $on[ $name ] );
}

/**
 * A página do painel pertence a um módulo desligado?
 */
function lk_page_off( $slug ) {
	if ( 'redes' === $slug && function_exists( 'lk_manage_only' ) && lk_manage_only() ) {
		return true; // modo gerenciamento: sem vincular redes
	}
	foreach ( lk_module_map() as $mod => $info ) {
		if ( in_array( $slug, $info[1], true ) && ! lk_module( $mod ) ) {
			return true;
		}
	}
	return false;
}

function lk_load_modules() {
	foreach ( lk_module_map() as $mod => $info ) {
		if ( lk_module( $mod ) ) {
			foreach ( $info[0] as $file ) {
				require_once LK_DIR . 'includes/' . $file . '.php';
			}
		}
	}
	require_once LK_DIR . 'includes/stubs.php';
}

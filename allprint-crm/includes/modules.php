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
function ap_module_map() {
	return array(
		'estoque'   => array( array( 'stock', 'slicer' ), array( 'filamentos', 'insumos', 'compras', 'impressoras', 'calculadora' ) ),
		'frete'     => array( array( 'shipping' ), array( 'frete' ) ),
		'produtos'  => array( array( 'products' ), array( 'produtos', 'produto' ) ),
		'marketing' => array( array( 'marketing' ), array( 'marketing' ) ),
	);
}

function ap_module( $name ) {
	static $on = null;
	if ( null === $on ) {
		$id = ap_identity();
		$on = array_flip( (array) $id['modulos'] );
	}
	return isset( $on[ $name ] );
}

/**
 * A página do painel pertence a um módulo desligado?
 */
function ap_page_off( $slug ) {
	$id = ap_identity();
	if ( in_array( $slug, (array) ( $id['paginas_off'] ?? array() ), true ) ) {
		return true;
	}
	foreach ( ap_module_map() as $mod => $info ) {
		if ( in_array( $slug, $info[1], true ) && ! ap_module( $mod ) ) {
			return true;
		}
	}
	return false;
}

function ap_load_modules() {
	foreach ( ap_module_map() as $mod => $info ) {
		if ( ap_module( $mod ) ) {
			foreach ( $info[0] as $file ) {
				require_once AP_DIR . 'includes/' . $file . '.php';
			}
		}
	}
	require_once AP_DIR . 'includes/stubs.php';
}

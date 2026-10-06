<?php
/**
 * Substitutos das funções dos módulos desligados (o núcleo e as telas chamam sem quebrar).
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! function_exists( 'ap_stock_low' ) ) {
	function ap_stock_low() {
		return array( 'supplies' => array() );
	}
	function ap_stock_consume( $project_id, $supplies ) {
		return 0;
	}
	function ap_stock_unconsume( $project_id ) {
	}
}
if ( ! function_exists( 'ap_boxes' ) ) {
	function ap_boxes() {
		return array();
	}
}

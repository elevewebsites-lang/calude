<?php
/**
 * Substitutos das funções dos módulos desligados (o núcleo e as telas chamam sem quebrar).
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! function_exists( 'lk_filament_label' ) ) {
	function lk_filament_label( $f ) {
		return '';
	}
	function lk_filament_cost_g( $f ) {
		return 0;
	}
	function lk_stock_low() {
		return array( 'filaments' => array(), 'supplies' => array() );
	}
	function lk_stock_consume( $project_id, $filaments, $supplies ) {
		return 0;
	}
	function lk_stock_unconsume( $project_id ) {
	}
	function lk_printer_maint_due( $p ) {
		return false;
	}
}
if ( ! function_exists( 'lk_boxes' ) ) {
	function lk_boxes() {
		return array();
	}
}
if ( ! function_exists( 'lk_channels' ) ) {
	function lk_channels() {
		return array();
	}
	function lk_product_cover( $p ) {
		return '';
	}
}

<?php
/**
 * Substitutos das funções dos módulos desligados (o núcleo e as telas chamam sem quebrar).
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! function_exists( 'ap_filament_label' ) ) {
	function ap_filament_label( $f ) {
		return '';
	}
	function ap_filament_cost_g( $f ) {
		return 0;
	}
	function ap_stock_low() {
		return array( 'filaments' => array(), 'supplies' => array() );
	}
	function ap_stock_consume( $project_id, $filaments, $supplies ) {
		return 0;
	}
	function ap_stock_unconsume( $project_id ) {
	}
	function ap_printer_maint_due( $p ) {
		return false;
	}
}
if ( ! function_exists( 'ap_boxes' ) ) {
	function ap_boxes() {
		return array();
	}
}
if ( ! function_exists( 'ap_channels' ) ) {
	function ap_channels() {
		return array();
	}
	function ap_product_cover( $p ) {
		return '';
	}
}

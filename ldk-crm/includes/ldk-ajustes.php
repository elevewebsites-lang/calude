<?php
/**
 * Ajustes da LDK que rodam uma vez por instalação (migrações da v1.5.0).
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_action(
	'init',
	function () {
		if ( get_option( 'lk_mig_150' ) ) {
			return;
		}
		$s = get_option( 'lk_settings', array() );
		if ( is_array( $s ) ) {
			// Categorias do financeiro: troca as do modelo de impressão 3D pelas de agência (só se ainda eram as originais).
			$old_out = "Filamento\nInsumos\nEquipamento\nManutenção\nFrete\nMarketing\nImpostos\nTaxas\nOutros";
			$old_in  = "Pedido\nVenda marketplace\nOutros";
			$def     = lk_default_settings();
			if ( ! isset( $s['categorias_out'] ) || str_replace( "\r", '', (string) $s['categorias_out'] ) === $old_out ) {
				$s['categorias_out'] = $def['categorias_out'];
			}
			if ( ! isset( $s['categorias_in'] ) || str_replace( "\r", '', (string) $s['categorias_in'] ) === $old_in ) {
				$s['categorias_in'] = $def['categorias_in'];
			}
			update_option( 'lk_settings', $s );
		}
		update_option( 'lk_mig_150', 1, false );
	},
	120
);

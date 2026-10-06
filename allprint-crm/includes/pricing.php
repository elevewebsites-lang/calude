<?php
/**
 * Ajudantes de configuração usados pelas propostas, cupons e estoque.
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function ap_num_setting( $key ) {
	return (float) ap_parse_money( ap_setting( $key ) );
}

/** Tabelas de preço que a proposta aceita (o cliente escolhe a tabela dele; ver ap_client_tier). */
function ap_audiences() {
	return array(
		'final'   => 'Pessoa física',
		'empresa' => 'Empresa',
		'revenda' => 'Terceirizado (revenda)',
	);
}

<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
status_header( 403 );
ap_panel_start( 'Sem acesso' );
echo '<div class="empty empty--big">' . ap_icon( 'chave', 28 ) . '<h3>Você não tem acesso a esta área</h3><p>Peça ao administrador para liberar.</p><a class="btn btn--primary" href="' . esc_url( ap_panel_url() ) . '">Voltar ao dashboard</a></div>'; // phpcs:ignore
ap_panel_end();

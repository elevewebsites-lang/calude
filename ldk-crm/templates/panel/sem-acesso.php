<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
status_header( 403 );
lk_panel_start( 'Sem acesso' );
echo '<div class="empty empty--big">' . lk_icon( 'chave', 28 ) . '<h3>Você não tem acesso a esta área</h3><p>Peça ao administrador para liberar.</p><a class="btn btn--primary" href="' . esc_url( lk_panel_url() ) . '">Voltar ao dashboard</a></div>'; // phpcs:ignore
lk_panel_end();

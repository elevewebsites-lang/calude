<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
status_header( 404 );
if ( ap_is_team() ) {
	ap_panel_start( 'Não encontrado' );
	echo '<div class="empty empty--big">' . ap_icon( 'alerta', 28 ) . '<h3>Página não encontrada</h3><p>O item pode ter sido excluído.</p><a class="btn btn--primary" href="' . esc_url( ap_panel_url() ) . '">Voltar ao dashboard</a></div>'; // phpcs:ignore
	ap_panel_end();
	exit;
}
wp_safe_redirect( is_user_logged_in() ? ap_home_for( get_current_user_id() ) : ap_url( 'entrar' ) );
exit;

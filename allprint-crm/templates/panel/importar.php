<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
$res  = get_transient( 'ap_import_result_' . get_current_user_id() );
$last = get_option( 'ap_import_log' );
$has  = is_readable( AP_DIR . 'data/AlPrint_Controle.xlsx' );
ap_panel_start( 'Importar planilha', 'importar' );
?>
<?php if ( $res ) : delete_transient( 'ap_import_result_' . get_current_user_id() ); ?>
	<section class="card">
		<div class="card-head"><h3><?php echo ! empty( $res['dry'] ) ? 'Simulação (nada foi gravado)' : 'Importação concluída'; ?></h3></div>
		<?php if ( ! empty( $res['errors'] ) ) : ?><p class="flash flash--warn"><?php echo esc_html( implode( ' ', $res['errors'] ) ); ?></p><?php endif; ?>
		<table class="table"><tbody>
			<?php foreach ( $res['counts'] as $k => $n ) : ?><tr><td><?php echo esc_html( ucfirst( $k ) ); ?></td><td align="right"><strong><?php echo (int) $n; ?></strong></td></tr><?php endforeach; ?>
			<?php if ( ! $res['counts'] ) : ?><tr><td class="muted">Nada novo: tudo o que está na planilha já estava no sistema.</td></tr><?php endif; ?>
		</tbody></table>
	</section>
<?php endif; ?>
<section class="card">
	<div class="card-head"><h3>Planilha de controle (.xlsx)</h3></div>
	<p class="muted">Lê as abas <strong>Clientes, Pedidos, Custos Fixos, Tabela de Preços, Estoque (rolos), Estoque - Insumos, Estoque - Revenda e Manutenção - Máquina</strong>. A aba Funcionários (salários) não é importada de propósito. Pode rodar de novo: só entra o que ainda não existe.</p>
	<?php if ( $last ) : ?><p class="muted small">Última importação: <?php echo esc_html( $last['at'] ); ?></p><?php endif; ?>
	<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" enctype="multipart/form-data" class="form-stack">
		<input type="hidden" name="action" value="ap_import">
		<?php wp_nonce_field( 'ap_import' ); ?>
		<label class="field"><span>Arquivo<?php echo $has ? ' (vazio = a planilha que veio no sistema)' : ''; ?></span><input type="file" name="planilha" accept=".xlsx"></label>
		<?php ap_check( 'simular', 'Só simular (mostra o que entraria, sem gravar)', true ); ?>
		<div class="form-actions"><button class="btn btn--primary" type="submit">Executar</button></div>
	</form>
</section>
<?php
ap_panel_end();

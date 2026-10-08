<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
$r = lk_get( 'reports', $id );
if ( ! $r ) {
	lk_render( 'panel/404' );
}
$c = lk_get( 'clients', $r->client_id );
$d = lk_json( $r->data );
lk_panel_start( 'Relatório · ' . lk_client_label( $c ) . ' · ' . lk_month_label( $r->period ), 'relatorios', '<a class="btn btn--ghost" href="' . esc_url( lk_report_url( $r ) ) . '" target="_blank">' . lk_icon( 'olho', 16 ) . '<span>Ver como o cliente</span></a>' );
?>
<a class="back" href="<?php echo esc_url( lk_panel_url( 'relatorios' ) ); ?>"><?php echo lk_icon( 'voltar', 16 ); // phpcs:ignore ?> Relatórios</a>
<?php if ( empty( $d['ig'] ) ) : ?><div class="flash flash--warn">Instagram não conectado para este cliente: o relatório sai sem os números da conta. Conecte na ficha do cliente e gere de novo.</div><?php elseif ( ! empty( $d['ig']['error'] ) ) : ?><div class="flash flash--erro">Instagram: <?php echo esc_html( $d['ig']['error'] ); ?></div><?php endif; ?>
<?php lk_form( 'report_save', 'stack card' ); ?>
	<input type="hidden" name="id" value="<?php echo (int) $r->id; ?>">
	<?php lk_input( 'notes', 'Análise e próximos passos (aparece no relatório)', $r->notes, 'textarea', 'rows="8" placeholder="O que funcionou, o que vamos testar no próximo mês…"' ); ?>
	<?php if ( lk_can( 'trafego' ) ) : ?><?php lk_check( 'show_ads', 'Mostrar tráfego pago neste relatório' . ( $c->ads_visible ? '' : ' (liberado só se o cliente tiver "mostrar tráfego" ligado)' ), ! empty( $d['show_ads'] ) ); ?><?php endif; ?>
	<div class="form-actions">
		<?php lk_check( 'publicar', 'Publicar (o cliente vê na área dele)', 'publicado' === $r->status ); ?>
		<button type="submit" class="btn btn--ghost">Salvar</button>
		<button type="submit" name="enviar" value="1" class="btn btn--primary">Salvar e enviar por e-mail</button>
	</div>
</form>
<?php lk_form( 'report_generate', 'inline-form' ); ?><input type="hidden" name="client_id" value="<?php echo (int) $r->client_id; ?>"><input type="hidden" name="period" value="<?php echo esc_attr( $r->period ); ?>"><button type="submit" class="btn btn--link btn--sm">Atualizar os números</button></form>
<?php
lk_panel_end();

<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
$prns = ap_rows( 'printers', '1=1', array(), 'active DESC, id' );
ap_panel_start( 'Impressoras', 'impressoras', '<button type="button" class="btn btn--primary" data-open="nova-impressora">' . ap_icon( 'mais', 16 ) . '<span>Impressora</span></button>' );

$form = function ( $p = null ) {
	ap_form( 'printer_save', 'stack' );
	?>
		<input type="hidden" name="id" value="<?php echo (int) ( $p ? $p->id : 0 ); ?>">
		<div class="grid-2">
			<?php ap_input( 'name', 'Apelido', $p ? $p->name : '', 'text', 'required placeholder="A1"' ); ?>
			<?php ap_input( 'model', 'Modelo', $p ? $p->model : 'Bambu Lab A1' ); ?>
			<?php ap_input( 'watts', 'Consumo médio (W)', $p ? $p->watts : 95, 'number', 'min="1"' ); ?>
			<?php ap_input( 'price', 'Quanto custou (R$)', $p && $p->price > 0 ? number_format( $p->price, 2, ',', '.' ) : '', 'text', 'inputmode="decimal" data-money' ); ?>
			<?php ap_input( 'life_hours', 'Vida útil estimada (horas)', $p ? $p->life_hours : 5000, 'number', 'min="1"' ); ?>
			<?php ap_input( 'hours_used', 'Horas já impressas', $p ? rtrim( rtrim( $p->hours_used, '0' ), '.' ) : 0, 'text', 'inputmode="decimal"' ); ?>
			<?php ap_input( 'maint_every', 'Manutenção a cada (horas)', $p ? $p->maint_every : 500, 'number', 'min="0"' ); ?>
			<?php ap_input( 'bought_at', 'Comprada em', $p ? $p->bought_at : '', 'date' ); ?>
		</div>
		<?php ap_input( 'notes', 'Observações', $p ? $p->notes : '' ); ?>
		<p class="muted small">Desgaste por hora = preço ÷ vida útil. Ex.: R$ 3.500 ÷ 5.000 h = R$ 0,70/h, que entra no custo de cada impressão. A A1 consome em média ~95 W imprimindo PLA (dado da Bambu Lab).</p>
		<div class="form-actions"><button type="button" class="btn btn--ghost" data-close>Cancelar</button><button type="submit" class="btn btn--primary">Salvar</button></div>
	</form>
	<?php
};
?>
<div class="printer-grid">
	<?php foreach ( $prns as $p ) : ?>
		<?php
		$wear  = $p->life_hours > 0 ? $p->price / $p->life_hours : 0;
		$hourc = $wear + $p->watts / 1000 * ap_num_setting( 'kwh' );
		$pct   = $p->life_hours > 0 ? min( 100, (int) round( $p->hours_used / $p->life_hours * 100 ) ) : 0;
		$due   = ap_printer_maint_due( $p );
		?>
		<article class="card printer<?php echo $due ? ' is-due' : ''; ?>">
			<div class="card-head"><h3><?php echo ap_icon( 'impressora', 18 ); // phpcs:ignore ?> <?php echo esc_html( $p->name ); ?></h3><span class="muted small"><?php echo esc_html( $p->model ); ?></span><button type="button" class="icon-btn" data-open="prn-<?php echo (int) $p->id; ?>"><?php echo ap_icon( 'editar', 15 ); // phpcs:ignore ?></button></div>
			<div class="kv">
				<span>Horas impressas</span><strong><?php echo esc_html( number_format( $p->hours_used, 1, ',', '.' ) ); ?> h</strong>
				<span>Custo por hora</span><strong class="money"><?php echo esc_html( ap_money( $hourc ) ); ?></strong>
				<span>Energia</span><strong><?php echo (int) $p->watts; ?> W</strong>
				<span>Próxima manutenção</span><strong class="<?php echo $due ? 'text-late' : ''; ?>"><?php echo $p->maint_every ? esc_html( $due ? 'agora' : 'em ' . number_format( $p->maint_every - ( $p->hours_used - $p->maint_last ), 0, ',', '.' ) . ' h' ) : '—'; ?></strong>
			</div>
			<div class="spool-bar" title="Vida útil usada"><span style="width:<?php echo (int) $pct; ?>%"></span></div>
			<small class="muted"><?php echo (int) $pct; ?>% da vida útil estimada</small>
			<?php if ( $due ) : ?><p class="small text-late" style="margin-top:8px;">Hora da manutenção: limpeza, lubrificação dos eixos, bico e correias.</p><?php endif; ?>
			<?php ap_action_button( 'printer_maint', array( 'id' => $p->id ), 'Registrar manutenção feita', 'btn btn--ghost btn--sm' ); ?>
		</article>
		<?php ap_modal_start( 'prn-' . $p->id, $p->name ); ?><?php $form( $p ); ?><?php ap_modal_end(); ?>
	<?php endforeach; ?>
</div>
<?php ap_modal_start( 'nova-impressora', 'Nova impressora' ); ?><?php $form(); ?><?php ap_modal_end(); ?>
<p class="muted small hint">Dica: a parcela da impressora vai em Financeiro → novo lançamento → Saída → "Conta recorrente" (mensal, até a última parcela).</p>
<?php
ap_panel_end();

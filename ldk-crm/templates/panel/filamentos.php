<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
$show  = isset( $_GET['ver'] ) && 'arquivados' === $_GET['ver']; // phpcs:ignore WordPress.Security.NonceVerification
$fils  = lk_rows( 'filaments', $show ? 'active = 0' : 'active = 1', array(), 'material, color_name' );
$total = 0;
$value = 0;
$by    = array();
foreach ( $fils as $f ) {
	$total += $f->weight_g;
	$value += $f->weight_g * lk_filament_cost_g( $f );
	$by[ $f->material ] = ( $by[ $f->material ] ?? 0 ) + $f->weight_g;
}
$actions = '<a class="btn btn--ghost" href="' . esc_url( lk_panel_url( 'filamentos', 0, $show ? array() : array( 'ver' => 'arquivados' ) ) ) . '">' . ( $show ? 'Ativos' : 'Acabados' ) . '</a><button type="button" class="btn btn--primary" data-open="novo-filamento">' . lk_icon( 'mais', 16 ) . '<span>Carretel</span></button>';
lk_panel_start( 'Filamentos', 'filamentos', $actions );

$modal = function ( $f = null ) {
	$mid = $f ? 'fil-' . $f->id : 'novo-filamento';
	lk_modal_start( $mid, $f ? lk_filament_label( $f ) : 'Novo carretel' );
	lk_form( 'filament_save', 'stack' );
	?>
		<input type="hidden" name="id" value="<?php echo (int) ( $f ? $f->id : 0 ); ?>">
		<div class="grid-3">
			<?php lk_select( 'material', 'Material', array_combine( lk_materials(), lk_materials() ), $f ? $f->material : 'PLA' ); ?>
			<?php lk_input( 'brand', 'Marca', $f ? $f->brand : '', 'text', 'placeholder="Bambu, Voolt, 3D Fila…"' ); ?>
			<?php lk_input( 'color_name', 'Cor', $f ? $f->color_name : '', 'text', 'required placeholder="Preto, Branco…"' ); ?>
		</div>
		<div class="grid-3">
			<?php lk_input( 'color_hex', 'Tom da cor', $f ? $f->color_hex : '#222222', 'color' ); ?>
			<?php lk_input( 'spool_g', 'Filamento no carretel novo (g)', $f ? $f->spool_g : 1000, 'number', 'min="1"' ); ?>
			<?php lk_input( 'price', 'Quanto pagou (R$)', $f && $f->price > 0 ? number_format( $f->price, 2, ',', '.' ) : '', 'text', 'inputmode="decimal" data-money required' ); ?>
		</div>
		<div class="grid-3">
			<?php lk_input( 'scale_g', 'Peso na balança agora (g)', '', 'text', 'inputmode="decimal" placeholder="' . ( $f ? 'deixe vazio para manter' : 'vazio = carretel cheio' ) . '"' ); ?>
			<?php lk_input( 'tare_g', 'Peso do carretel vazio (g)', $f ? $f->tare_g : 0, 'number', 'min="0"' ); ?>
			<?php lk_input( 'min_g', 'Avisar quando ficar abaixo de (g)', $f ? $f->min_g : 200, 'number', 'min="0"' ); ?>
		</div>
		<p class="muted small">A balança pesa filamento + carretel. Informando a tara (o carretel vazio, em geral 180 a 250 g), o painel desconta sozinho.</p>
		<?php lk_input( 'link', 'Link para comprar de novo', $f ? $f->link : '', 'url', 'placeholder="Mercado Livre, Shopee, loja…"' ); ?>
		<?php if ( ! $f ) : ?><?php lk_check( 'lancar', 'Lançar a compra no financeiro (saída paga hoje)', true ); ?><?php endif; ?>
		<div class="form-actions"><button type="button" class="btn btn--ghost" data-close>Cancelar</button><button type="submit" class="btn btn--primary">Salvar</button></div>
	</form>
	<?php
	lk_modal_end();
};
?>
<section class="stats stats--4">
	<div class="stat"><span class="stat-label">Filamento em estoque</span><strong><?php echo esc_html( number_format( $total / 1000, 2, ',', '.' ) ); ?> kg</strong><small><?php echo count( $fils ); ?> carretéis</small></div>
	<div class="stat stat--dark"><span class="stat-label">Valor em estoque</span><strong class="money"><?php echo esc_html( lk_money( $value ) ); ?></strong><small>pelo preço pago</small></div>
	<?php $i = 0; foreach ( $by as $mat => $g ) : if ( $i++ >= 2 ) { break; } ?>
		<div class="stat"><span class="stat-label"><?php echo esc_html( $mat ); ?></span><strong><?php echo esc_html( number_format( $g / 1000, 2, ',', '.' ) ); ?> kg</strong></div>
	<?php endforeach; ?>
</section>

<?php if ( ! $fils ) : ?>
	<div class="empty empty--big"><?php echo lk_icon( 'carretel', 28 ); // phpcs:ignore ?><h3>Nenhum carretel cadastrado</h3><p>Cadastre cada carretel com a marca, a cor e quanto pagou. A calculadora usa esse preço para o custo real.</p><button type="button" class="btn btn--primary" data-open="novo-filamento">Cadastrar carretel</button></div>
<?php else : ?>
	<div class="spools">
		<?php foreach ( $fils as $f ) : ?>
			<?php $pct = lk_filament_pct( $f ); $low = $f->min_g > 0 && $f->weight_g < $f->min_g; ?>
			<article class="spool<?php echo $low ? ' is-low' : ''; ?>" style="--c:<?php echo esc_attr( $f->color_hex ); ?>">
				<div class="spool-disc"><span></span></div>
				<div class="spool-body">
					<div class="spool-head">
						<strong><?php echo esc_html( trim( $f->material . ' ' . $f->color_name ) ); ?></strong>
						<button type="button" class="icon-btn" data-open="fil-<?php echo (int) $f->id; ?>" title="Editar"><?php echo lk_icon( 'editar', 15 ); // phpcs:ignore ?></button>
					</div>
					<small class="muted"><?php echo esc_html( ( $f->brand ? $f->brand . ' · ' : '' ) . lk_money( lk_filament_cost_g( $f ) * 1000 ) . '/kg' ); ?></small>
					<div class="spool-bar"><span style="width:<?php echo (int) $pct; ?>%"></span></div>
					<div class="spool-foot">
						<b><?php echo esc_html( number_format( $f->weight_g, 0, ',', '.' ) ); ?> g</b><span class="muted small"><?php echo (int) $pct; ?>%<?php echo $low ? ' · <em class="text-late">acabando</em>' : ''; ?></span>
					</div>
					<?php lk_form( 'filament_weigh', 'weigh-form' ); ?>
						<input type="hidden" name="id" value="<?php echo (int) $f->id; ?>">
						<input type="text" name="scale_g" inputmode="decimal" placeholder="Balança (g)" aria-label="Peso na balança" required>
						<button type="submit" class="btn btn--ghost btn--sm">Pesar</button>
					</form>
					<div class="row-btns">
						<?php if ( $f->link ) : ?><a class="small" href="<?php echo esc_url( $f->link ); ?>" target="_blank" rel="noopener">Comprar de novo ↗</a><?php endif; ?>
						<?php lk_action_button( 'filament_archive', array( 'id' => $f->id ), $f->active ? 'Acabou' : 'Reativar', 'btn btn--link btn--sm', $f->active ? 'Marcar este carretel como acabado?' : '' ); ?>
					</div>
				</div>
			</article>
			<?php $modal( $f ); ?>
		<?php endforeach; ?>
	</div>
<?php endif; ?>
<?php
$modal();
lk_panel_end();

<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
$rows = ap_catalog( false );
$sups = function_exists( 'ap_stock_low' ) ? ap_rows( 'supplies', 'active = 1', array(), 'name' ) : array();
ap_panel_start( 'Tabela de preços', 'catalogo', '<button type="button" class="btn btn--primary" data-open="cat-0">' . ap_icon( 'mais', 16 ) . '<span>Material</span></button>' );
$form = function ( $c = null ) use ( $sups ) {
	ap_form( 'catalog_save', 'stack' );
	?>
		<input type="hidden" name="id" value="<?php echo (int) ( $c ? $c->id : 0 ); ?>">
		<div class="grid-2">
			<?php ap_input( 'name', 'Material', $c ? $c->name : '', 'text', 'required' ); ?>
			<?php ap_input( 'price_m2', 'Preço por m² (R$)', $c ? number_format( $c->price_m2, 2, ',', '.' ) : '', 'text', 'inputmode="decimal" data-money required' ); ?>
			<?php ap_select( 'grp', 'Grupo', ap_catalog_groups(), $c ? $c->grp : 'impresso' ); ?>
			<?php ap_select( 'kind', 'Tipo', ap_catalog_kinds(), $c ? $c->kind : 'adesivo' ); ?>
			<?php ap_input( 'widths', 'Larguras da bobina (cm)', $c ? $c->widths : '', 'text', 'placeholder="106 / 127 / 152"' ); ?>
			<?php ap_input( 'position', 'Ordem', $c ? $c->position : 0, 'number' ); ?>
		</div>
		<?php ap_input( 'note', 'Observação (aparece ao lado do nome)', $c ? $c->note : '' ); ?>
		<?php ap_input( 'included', 'O que já está incluso (aparece no pedido)', $c ? $c->included : '', 'textarea', 'rows="2"' ); ?>
		<div class="grid-3">
			<?php ap_check( 'allow_lamination', 'Aceita laminação', $c ? (bool) $c->allow_lamination : false ); ?>
			<?php ap_check( 'allow_eyelets', 'Aceita reforço e ilhós avulso', $c ? (bool) $c->allow_eyelets : false ); ?>
			<?php ap_check( 'active', 'Ativo (aparece para os clientes)', $c ? (bool) $c->active : true ); ?>
		</div>
		<?php if ( $sups ) : ?><?php ap_select( 'supply_id', 'Baixa no estoque (insumo em m²)', array( 0 => 'Não baixar' ) + wp_list_pluck( $sups, 'name', 'id' ), $c ? $c->supply_id : 0 ); ?><?php endif; ?>
		<div class="form-actions"><button type="button" class="btn btn--ghost" data-close>Cancelar</button><button type="submit" class="btn btn--primary">Salvar</button></div>
	</form>
	<?php
};
?>
<p class="muted small hint">É a tabela que os clientes aprovados veem e que calcula os pedidos. Serviços (ilhós, laminação), mínimo de m² e prazo ficam em Configurações → Preço.</p>
<?php foreach ( ap_catalog_groups() as $g => $gl ) : ?>
	<section class="card price-table">
		<div class="card-head"><h3><?php echo esc_html( $gl ); ?></h3></div>
		<?php foreach ( $rows as $c ) : ?>
			<?php if ( $c->grp !== $g ) { continue; } ?>
			<div class="price-row<?php echo $c->active ? '' : ' is-off'; ?>">
				<span><strong><?php echo esc_html( $c->name ); ?></strong><small><?php echo esc_html( $c->widths . ' cm' . ( $c->allow_lamination ? ' · laminação' : '' ) . ( $c->allow_eyelets ? ' · ilhós' : '' ) . ( $c->active ? '' : ' · inativo' ) ); ?></small></span>
				<b class="price-tag"><?php echo esc_html( ap_money( $c->price_m2 ) ); ?></b>
				<button type="button" class="icon-btn" data-open="cat-<?php echo (int) $c->id; ?>" title="Editar"><?php echo ap_icon( 'editar', 15 ); // phpcs:ignore ?></button>
			</div>
			<?php ap_modal_start( 'cat-' . $c->id, $c->name ); $form( $c ); ap_modal_end(); ?>
		<?php endforeach; ?>
	</section>
<?php endforeach; ?>
<?php ap_modal_start( 'cat-0', 'Novo material' ); $form(); ap_modal_end(); ?>
<?php
ap_panel_end();

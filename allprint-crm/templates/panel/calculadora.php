<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
$calc  = $id ? ap_get( 'calcs', $id ) : null;
$d     = ap_calc_data( $calc );
$in    = $d['in'];
$cfg   = ap_pricing_config();
$fils  = ap_rows( 'filaments', 'active = 1', array(), 'material, color_name' );
$sups  = ap_rows( 'supplies', 'active = 1', array(), 'name' );
$prns  = ap_rows( 'printers', 'active = 1' );
$saved = ap_rows( 'calcs', '1=1', array(), 'id DESC LIMIT 12' );
$v     = function ( $k, $def = '' ) use ( $in ) {
	return isset( $in[ $k ] ) && '' !== $in[ $k ] ? $in[ $k ] : $def;
};
$hours_total = (float) $v( 'hours', 0 );
$fil_rows    = $v( 'filaments', array() ) ?: array( array( '', '' ) );
$sup_rows    = $v( 'supplies', array() );
if ( ! $sup_rows ) {
	foreach ( $sups as $s ) {
		if ( $s->per_order ) {
			$sup_rows[] = array( $s->id, 1 );
		}
	}
}
$sup_rows = $sup_rows ?: array( array( '', '' ) );
$ext_rows = $v( 'extras', array() ) ?: array( array( '', '' ) );

ap_panel_start( $calc ? 'Calculadora · ' . $calc->name : 'Calculadora', 'calculadora' );
?>
<script>window.AP_PRICING = <?php echo wp_json_encode( $cfg ); ?>;</script>

<?php if ( ! $fils ) : ?>
	<div class="flash flash--warn">Cadastre seus carretéis em <a href="<?php echo esc_url( ap_panel_url( 'filamentos' ) ); ?>">Filamentos</a> para a conta usar o preço real que você pagou. Sem isso, usa R$ 0,10/g.</div>
<?php endif; ?>

<?php ap_form( 'calc_save', 'calc-grid', false ); ?>
	<input type="hidden" name="calc_id" value="<?php echo (int) ( $calc ? $calc->id : 0 ); ?>">
	<input type="hidden" name="c[result]" value="">
	<div class="calc-form" data-calc>

		<section class="card step">
			<div class="step-head"><span class="step-n">01</span><div><h3>A peça</h3></div></div>
			<div class="grid-3">
				<?php ap_input( 'c[name]', 'Nome', $v( 'name' ), 'text', 'placeholder="Ex.: Capacete do Halo"' ); ?>
				<?php ap_input( 'c[qty]', 'Quantidade', $v( 'qty', isset( $_GET['qty'] ) ? absint( $_GET['qty'] ) : 1 ), 'number', 'min="1"' ); // phpcs:ignore ?>
				<?php ap_select( 'c[audience]', 'Para quem', ap_audiences(), $v( 'audience', 'final' ) ); ?>
			</div>
			<div class="grid-3">
				<?php ap_select( 'c[type]', 'Tipo de peça', array_map( function ( $t ) { return $t[0] . ' (× ' . str_replace( '.', ',', $t[1] ) . ')'; }, $cfg['types'] ), $v( 'type' ) ); ?>
				<?php ap_select( 'c[difficulty]', 'Dificuldade', array_map( function ( $t ) { return $t[0] . ( $t[1] ? ' (+' . str_replace( '.', ',', $t[1] ) . '%)' : '' ); }, $cfg['difficulty'] ), $v( 'difficulty' ) ); ?>
				<div class="field field--check"><?php ap_check( 'c[urgent]', 'Urgente (+' . str_replace( '.', ',', $cfg['urgent'] ) . '%)', (bool) $v( 'urgent' ) ); ?></div>
			</div>
			<p class="muted small">Peça geek (personagens de filmes, games, anime): confira a licença do modelo. Vender réplicas sem licença comercial pode dar problema.</p>
		</section>

		<section class="card step">
			<div class="step-head"><span class="step-n">02</span><div><h3>Impressão</h3><p class="muted small">Suba o arquivo fatiado do Bambu Studio (.gcode.3mf), o .gcode ou um print da tela do fatiador. Ou digite.</p></div></div>
			<label class="drop drop--slicer" data-slicer><input type="file" accept=".3mf,.gcode,.gco,image/*"><?php echo ap_icon( 'upload', 22 ); // phpcs:ignore ?><span><strong>Arraste o arquivo do fatiador aqui</strong><small>.gcode.3mf e .gcode são lidos na hora · print da tela usa a IA</small></span></label>
			<div class="slicer-status" data-slicer-status hidden></div>
			<div class="grid-4">
				<?php ap_input( 'c[hours]', 'Horas', $hours_total ? floor( $hours_total ) : '', 'number', 'min="0" step="1"' ); ?>
				<?php ap_input( 'c[minutes]', 'Minutos', $hours_total ? round( ( $hours_total - floor( $hours_total ) ) * 60 ) : '', 'number', 'min="0" max="59"' ); ?>
				<?php ap_input( 'c[per_plate]', 'Peças por mesa', $v( 'per_plate', 1 ), 'number', 'min="1"' ); ?>
				<?php ap_select( 'c[printer]', 'Impressora', array_combine( wp_list_pluck( $prns, 'id' ), array_map( function ( $p ) { return $p->name . ' · ' . $p->watts . ' W'; }, $prns ) ) ?: array( '' => 'Padrão (95 W)' ), $v( 'printer' ) ); ?>
			</div>
			<p class="muted small">O tempo e as gramas são da mesa inteira; "peças por mesa" divide por peça.</p>
			<h4 class="sub">Filamentos</h4>
			<div class="rows" data-rows="fil">
				<?php foreach ( $fil_rows as $row ) : ?>
					<?php $sel = $row[0] ?? ''; $fcol = $sel && isset( $cfg['filaments'][ $sel ] ) ? $cfg['filaments'][ $sel ]['hex'] : 'transparent'; ?>
					<div class="row-line">
						<span class="swatch" style="background:<?php echo esc_attr( $fcol ); ?>"></span>
						<label class="field"><span>Carretel</span><select name="c[fil_id][]"><option value="">Escolha…</option><?php foreach ( $fils as $f ) : ?><option value="<?php echo (int) $f->id; ?>"<?php selected( (string) $sel, (string) $f->id ); ?>><?php echo esc_html( ap_filament_label( $f ) . ' · ' . ap_money( ap_filament_cost_g( $f ) * 1000 ) . '/kg · ' . round( $f->weight_g ) . ' g' ); ?></option><?php endforeach; ?></select></label>
						<label class="field field--sm"><span>Gramas</span><input type="text" name="c[fil_g][]" inputmode="decimal" value="<?php echo esc_attr( isset( $row[1] ) && $row[1] ? str_replace( '.', ',', $row[1] ) : '' ); ?>"></label>
						<button type="button" class="icon-btn" data-row-del title="Remover">×</button>
					</div>
				<?php endforeach; ?>
			</div>
			<button type="button" class="btn btn--ghost btn--sm" data-row-add="fil"><?php echo ap_icon( 'mais', 14 ); // phpcs:ignore ?><span>Outra cor / filamento</span></button>
		</section>

		<section class="card step">
			<div class="step-head"><span class="step-n">03</span><div><h3>Mão de obra</h3></div></div>
			<div class="grid-2">
				<?php ap_input( 'c[labor_min]', 'Minutos de trabalho por peça (preparo, tirar suporte, lixar, pintar, embalar)', $v( 'labor_min', 5 ), 'number', 'min="0"' ); ?>
				<?php ap_input( 'c[model_hours]', 'Horas de modelagem 3D (cobradas uma vez)', $v( 'model_hours', 0 ), 'text', 'inputmode="decimal"' ); ?>
			</div>
			<p class="muted small">Sua hora: <?php echo esc_html( ap_money( $cfg['labor'] ) ); ?> · modelagem: <?php echo esc_html( ap_money( $cfg['model'] ) ); ?>/h · ajuste em Configurações.</p>
		</section>

		<section class="card step">
			<div class="step-head"><span class="step-n">04</span><div><h3>Insumos</h3><p class="muted small">Sacola, cartão, embalagem, ímã, argola… Entram só no custo: o cliente não vê.</p></div></div>
			<div class="rows" data-rows="sup">
				<?php foreach ( $sup_rows as $row ) : ?>
					<div class="row-line">
						<label class="field"><span>Insumo</span><select name="c[sup_id][]"><option value="">Escolha…</option><?php foreach ( $sups as $s ) : ?><option value="<?php echo (int) $s->id; ?>"<?php selected( (string) ( $row[0] ?? '' ), (string) $s->id ); ?>><?php echo esc_html( $s->name . ' · ' . ap_money( $s->unit_cost ) . '/' . $s->unit ); ?></option><?php endforeach; ?></select></label>
						<label class="field field--sm"><span>Qtd./peça</span><input type="text" name="c[sup_qty][]" inputmode="decimal" value="<?php echo esc_attr( isset( $row[1] ) && $row[1] ? str_replace( '.', ',', $row[1] ) : '' ); ?>"></label>
						<button type="button" class="icon-btn" data-row-del title="Remover">×</button>
					</div>
				<?php endforeach; ?>
			</div>
			<div class="row-btns">
				<button type="button" class="btn btn--ghost btn--sm" data-row-add="sup"><?php echo ap_icon( 'mais', 14 ); // phpcs:ignore ?><span>Outro insumo</span></button>
				<button type="button" class="btn btn--ghost btn--sm" data-open="novo-insumo">Cadastrar insumo novo</button>
			</div>
			<h4 class="sub">Extras avulsos (custo por peça)</h4>
			<div class="rows" data-rows="ext">
				<?php foreach ( $ext_rows as $row ) : ?>
					<div class="row-line">
						<label class="field"><span>Descrição</span><input type="text" name="c[extra_label][]" value="<?php echo esc_attr( $row[0] ?? '' ); ?>" placeholder="Ex.: LED, tinta spray"></label>
						<label class="field field--sm"><span>R$/peça</span><input type="text" name="c[extra_cost][]" inputmode="decimal" value="<?php echo esc_attr( isset( $row[1] ) && $row[1] ? number_format( (float) $row[1], 2, ',', '' ) : '' ); ?>"></label>
						<button type="button" class="icon-btn" data-row-del title="Remover">×</button>
					</div>
				<?php endforeach; ?>
			</div>
			<button type="button" class="btn btn--ghost btn--sm" data-row-add="ext"><?php echo ap_icon( 'mais', 14 ); // phpcs:ignore ?><span>Outro extra</span></button>
		</section>
	</div>

	<aside class="calc-out" data-calc-out>
		<div class="card calc-card">
			<span class="eyebrow">Custo por peça</span>
			<div class="calc-lines">
				<div><span>Filamento</span><b data-o="filament">—</b></div>
				<div><span>Energia</span><b data-o="energy">—</b></div>
				<div><span>Desgaste da impressora</span><b data-o="wear">—</b></div>
				<div><span>Reserva para falhas</span><b data-o="fail">—</b></div>
				<div><span>Mão de obra</span><b data-o="labor">—</b></div>
				<div><span>Insumos</span><b data-o="supplies">—</b></div>
				<div><span>Extras</span><b data-o="extras">—</b></div>
				<div class="calc-total"><span>Custo</span><b data-o="cost">—</b></div>
				<div class="muted small"><span><span data-o="grams"></span> · <span data-o="hours"></span> por peça</span><b data-o="mult"></b></div>
			</div>
			<span class="eyebrow">Preço sugerido</span>
			<div class="calc-price"><strong data-o="unit">—</strong><small>por peça · <span data-o="qty"></span></small></div>
			<div class="calc-lines">
				<div><span>Modelagem</span><b data-o="model">—</b></div>
				<div class="calc-total"><span>Total</span><b data-o="total">—</b></div>
				<div><span>No Pix (−<?php echo esc_html( str_replace( '.', ',', $cfg['pixOff'] ) ); ?>%)</span><b data-o="pix">—</b></div>
				<div><span>Seu lucro</span><b data-o="profit" class="text-ok">—</b></div>
				<div><span>Margem</span><b data-o="margin">—</b></div>
			</div>
			<div class="calc-resale" data-o-resale hidden>
				<span class="eyebrow">Para o revendedor</span>
				<div><span>Preço sugerido de revenda</span><b data-o="resale">—</b></div>
				<small class="muted">Lucro dele: <span data-o="resaleProfit"></span></small>
			</div>
			<div class="calc-warn" data-o="warn" hidden></div>
			<table class="calc-tiers">
				<thead><tr><th>Qtd.</th><th>Desc.</th><th>Unid.</th><th>Total</th><th>Lucro</th><th data-o="resale-col" hidden>Revenda</th></tr></thead>
				<tbody data-o="tiers"></tbody>
			</table>
			<div class="stack-btns">
				<button type="submit" name="next" value="orcamento" class="btn btn--primary btn--block">Criar orçamento com este preço</button>
				<button type="submit" name="next" value="" class="btn btn--ghost btn--block">Salvar cálculo</button>
				<?php if ( ap_can( 'produtos' ) ) : ?><button type="submit" name="next" value="produto" class="btn btn--ghost btn--block">Salvar como produto</button><?php endif; ?>
			</div>
		</div>
	</aside>
</form>

<?php if ( $saved ) : ?>
	<section class="card">
		<div class="card-head"><h3>Cálculos salvos</h3></div>
		<div class="table">
			<?php foreach ( $saved as $c ) : ?>
				<div class="table-row">
					<a class="cell-main" href="<?php echo esc_url( ap_panel_url( 'calculadora', $c->id ) ); ?>"><span><strong><?php echo esc_html( $c->name ); ?></strong><small><?php echo esc_html( ap_date( $c->created_at ) ); ?></small></span></a>
					<span data-label="Custo" class="money"><?php echo esc_html( ap_money( $c->cost ) ); ?></span>
					<span data-label="Preço" class="money"><?php echo esc_html( ap_money( $c->price ) ); ?></span>
					<span class="row-btns"><?php ap_action_button( 'calc_delete', array( 'id' => $c->id ), '×', 'icon-btn', 'Excluir este cálculo?' ); ?></span>
				</div>
			<?php endforeach; ?>
		</div>
	</section>
<?php endif; ?>

<?php ap_modal_start( 'novo-insumo', 'Insumo novo' ); ?>
	<?php ap_form( 'supply_save', 'stack' ); ?>
		<?php echo '<script>document.currentScript.parentNode.setAttribute("data-supply-quick","")</script>'; ?>
		<?php ap_input( 'name', 'Nome', '', 'text', 'required placeholder="Ex.: Argola de chaveiro"' ); ?>
		<div class="grid-2">
			<?php ap_input( 'unit_cost', 'Custo por unidade (R$)', '', 'text', 'inputmode="decimal" data-money' ); ?>
			<?php ap_select( 'unit', 'Unidade', array( 'un' => 'unidade', 'm' => 'metro', 'g' => 'grama', 'ml' => 'ml', 'folha' => 'folha' ) ); ?>
		</div>
		<p class="muted small">Depois, em Insumos, registre as compras para o custo médio e o estoque ficarem certos.</p>
		<div class="form-actions"><button type="button" class="btn btn--ghost" data-close>Cancelar</button><button type="submit" class="btn btn--primary">Cadastrar</button></div>
	</form>
<?php ap_modal_end(); ?>

<script src="<?php echo esc_url( AP_URL . 'assets/calc.js?ver=' . AP_VERSION ); ?>"></script>
<?php
ap_panel_end();

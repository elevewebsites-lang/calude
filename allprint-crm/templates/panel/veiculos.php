<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
global $wpdb;
$all     = ap_vehicles( false );
$vehicles = array_values( array_filter( $all, function ( $v ) { return (int) $v->active; } ) );
$sel_id  = isset( $_GET['v'] ) ? absint( $_GET['v'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification
$sel     = $sel_id ? ap_get( 'vehicles', $sel_id ) : null;
$types   = ap_vehicle_types();
$ftype   = isset( $_GET['tipo'] ) && isset( $types[ wp_unslash( $_GET['tipo'] ) ] ) ? wp_unslash( $_GET['tipo'] ) : ''; // phpcs:ignore WordPress.Security.NonceVerification,WordPress.Security.ValidatedSanitizedInput
$where   = 't.vehicle_id > 0 AND t.type = %s';
$args    = array( 'out' );
if ( $sel ) {
	$where .= ' AND t.vehicle_id = %d';
	$args[] = $sel->id;
}
if ( $ftype ) {
	$where .= ' AND t.vtype = %s';
	$args[] = $ftype;
}
$rows = $wpdb->get_results( $wpdb->prepare( 'SELECT t.*, v.name AS vname, v.plate AS vplate FROM ' . ap_table( 'transactions' ) . ' t LEFT JOIN ' . ap_table( 'vehicles' ) . ' v ON v.id = t.vehicle_id WHERE ' . $where . ' ORDER BY COALESCE(t.paid_at, t.due_date) DESC, t.id DESC LIMIT 300', $args ) ); // phpcs:ignore WordPress.DB.PreparedSQL
$sum_rows = 0;
foreach ( $rows as $r ) {
	$sum_rows += (float) $r->amount;
}
$tot_month = 0;
$tot_year  = 0;
foreach ( $vehicles as $v ) {
	$st = ap_vehicle_stats( $v->id );
	$tot_month += $st['month'];
	$tot_year  += $st['year'];
}
ap_panel_start( 'Veículos', 'veiculos', '<button type="button" class="btn btn--ghost" data-open="vei-0">' . ap_icon( 'mais', 16 ) . '<span>Veículo</span></button><button type="button" class="btn btn--primary" data-open="vei-custo">' . ap_icon( 'mais', 16 ) . '<span>Lançar custo</span></button>' );
?>
<section class="stats stats--4">
	<div class="stat"><span class="stat-label">Gasto no mês</span><strong class="money"><?php echo esc_html( ap_money( $tot_month ) ); ?></strong><small>todos os veículos</small></div>
	<div class="stat"><span class="stat-label">Gasto no ano</span><strong class="money"><?php echo esc_html( ap_money( $tot_year ) ); ?></strong><small>combustível, IPVA, seguro…</small></div>
	<div class="stat"><span class="stat-label">Veículos ativos</span><strong><?php echo count( $vehicles ); ?></strong><small>cadastrados</small></div>
	<div class="stat stat--dark"><span class="stat-label">No financeiro</span><strong>Veículos</strong><small>categoria de saída: tudo entra nos totais e na planilha de custos</small></div>
</section>

<?php if ( ! $vehicles ) : ?>
	<div class="empty empty--big"><?php echo ap_icon( 'caminhao', 28 ); // phpcs:ignore ?><h3>Nenhum veículo cadastrado</h3><p>Cadastre o carro da empresa e lance combustível, IPVA, seguro, manutenção… Tudo vai para o Financeiro e para a planilha de custos.</p><button type="button" class="btn btn--primary" data-open="vei-0">Cadastrar veículo</button></div>
<?php else : ?>
	<div class="veh-grid">
		<?php foreach ( $vehicles as $v ) : ?>
			<?php $st = ap_vehicle_stats( $v->id ); ?>
			<article class="card veh<?php echo $sel && (int) $sel->id === (int) $v->id ? ' is-sel' : ''; ?>">
				<div class="card-head"><h3><?php echo esc_html( ap_vehicle_label( $v ) ); ?></h3><a class="small" href="<?php echo esc_url( ap_panel_url( 'veiculos', 0, array( 'v' => $v->id ) ) ); ?>">ver custos</a></div>
				<p class="muted small"><?php echo esc_html( trim( $v->model . ( $v->year ? ' ' . $v->year : '' ) . ' · ' . $v->fuel . ( $v->odometer ? ' · ' . number_format( $v->odometer, 0, ',', '.' ) . ' km' : '' ), ' ·' ) ); ?></p>
				<div class="veh-nums">
					<div><span>No mês</span><strong class="money"><?php echo esc_html( ap_money( $st['month'] ) ); ?></strong></div>
					<div><span>No ano</span><strong class="money"><?php echo esc_html( ap_money( $st['year'] ) ); ?></strong></div>
					<div><span>Consumo</span><strong><?php echo $st['kml'] ? esc_html( number_format( $st['kml'], 1, ',', '' ) . ' km/l' ) : '—'; ?></strong></div>
					<div><span>Custo por km</span><strong><?php echo $st['per_km'] ? esc_html( ap_money( $st['per_km'] ) ) : '—'; ?></strong></div>
				</div>
				<?php if ( $st['open'] > 0 ) : ?><p class="small text-late">A pagar: <?php echo esc_html( ap_money( $st['open'] ) ); ?> (parcelas em aberto)</p><?php endif; ?>
				<div class="row-btns">
					<button type="button" class="btn btn--ghost btn--sm" data-open="vei-<?php echo (int) $v->id; ?>">Editar</button>
					<button type="button" class="btn btn--ghost btn--sm" data-open="vei-custo" data-veh="<?php echo (int) $v->id; ?>">Lançar custo</button>
				</div>
			</article>
		<?php endforeach; ?>
	</div>

	<section class="card">
		<div class="card-head"><h3>Custos<?php echo $sel ? ' · ' . esc_html( ap_vehicle_label( $sel ) ) : ''; ?></h3>
			<div class="seg">
				<a href="<?php echo esc_url( ap_panel_url( 'veiculos', 0, $sel ? array( 'v' => $sel->id ) : array() ) ); ?>" class="<?php echo '' === $ftype ? 'is-active' : ''; ?>">Todos</a>
				<?php foreach ( array( 'Combustível', 'IPVA', 'Seguro', 'Manutenção', 'Multa' ) as $tk ) : ?><a href="<?php echo esc_url( ap_panel_url( 'veiculos', 0, array_filter( array( 'v' => $sel ? $sel->id : 0, 'tipo' => $tk ) ) ) ); ?>" class="<?php echo $ftype === $tk ? 'is-active' : ''; ?>"><?php echo esc_html( $tk ); ?></a><?php endforeach; ?>
			</div>
			<span class="muted small">Total listado: <strong class="money"><?php echo esc_html( ap_money( $sum_rows ) ); ?></strong></span>
		</div>
		<?php if ( ! $rows ) : ?>
			<p class="muted small">Nenhum custo lançado ainda.</p>
		<?php else : ?>
			<div class="table table--veh">
				<div class="table-row table-head"><span>Data</span><span>Custo</span><span>Veículo</span><span>Km / litros</span><span>Status</span><span>Valor</span><span></span></div>
				<?php foreach ( $rows as $r ) : ?>
					<div class="table-row">
						<span data-label="Data"><?php echo esc_html( ap_date( $r->paid_at ? $r->paid_at : $r->due_date ) ); ?></span>
						<span data-label="Custo"><strong><?php echo esc_html( $r->vtype ); ?></strong><small><?php echo esc_html( $r->description ); ?></small></span>
						<span data-label="Veículo"><?php echo esc_html( $r->vname . ( $r->vplate ? ' · ' . $r->vplate : '' ) ); ?></span>
						<span data-label="Km / litros"><?php echo esc_html( trim( ( $r->odometer ? number_format( $r->odometer, 0, ',', '.' ) . ' km' : '' ) . ( $r->liters > 0 ? ' · ' . number_format( $r->liters, 1, ',', '.' ) . ' L' : '' ), ' ·' ) ?: '—' ); ?></span>
						<span data-label="Status"><em class="badge badge--<?php echo 'pago' === $r->status ? 'ok' : 'warn'; ?>"><?php echo 'pago' === $r->status ? 'Pago' : 'A pagar'; ?></em></span>
						<span data-label="Valor" class="money text-late"><strong>− <?php echo esc_html( ap_money( $r->amount ) ); ?></strong></span>
						<span class="row-btns"><?php ap_action_button( 'vehicle_cost_delete', array( 'id' => $r->id ), 'Remover', 'btn btn--link btn--sm', 'Remover este custo do financeiro?' ); ?></span>
					</div>
				<?php endforeach; ?>
			</div>
		<?php endif; ?>
	</section>
<?php endif; ?>

<?php
$vform = function ( $v = null ) {
	ap_form( 'vehicle_save', 'stack' );
	?>
		<input type="hidden" name="id" value="<?php echo (int) ( $v ? $v->id : 0 ); ?>">
		<div class="grid-2">
			<?php ap_input( 'name', 'Nome (como vocês chamam)', $v ? $v->name : '', 'text', 'required placeholder="Ex.: Fiorino, Moto de entrega"' ); ?>
			<?php ap_input( 'plate', 'Placa', $v ? $v->plate : '', 'text', 'placeholder="ABC1D23" maxlength="8"' ); ?>
			<?php ap_input( 'model', 'Modelo', $v ? $v->model : '' ); ?>
			<?php ap_input( 'year', 'Ano', $v ? $v->year : '' ); ?>
			<?php ap_select( 'fuel', 'Combustível', array( 'Gasolina' => 'Gasolina', 'Etanol' => 'Etanol', 'Flex' => 'Flex', 'Diesel' => 'Diesel', 'GNV' => 'GNV', 'Elétrico' => 'Elétrico' ), $v ? $v->fuel : 'Gasolina' ); ?>
			<?php ap_input( 'odometer', 'Km atual', $v && $v->odometer ? $v->odometer : '', 'number', 'min="0"' ); ?>
		</div>
		<?php ap_input( 'notes', 'Observações', $v ? $v->notes : '' ); ?>
		<div class="form-actions">
			<?php if ( $v ) : ?><?php ap_action_button( 'vehicle_archive', array( 'id' => $v->id ), $v->active ? 'Arquivar' : 'Reativar', 'btn btn--link btn--sm', 'Arquivar este veículo? Os custos continuam no financeiro.' ); ?><?php endif; ?>
			<button type="button" class="btn btn--ghost" data-close>Cancelar</button><button type="submit" class="btn btn--primary">Salvar</button>
		</div>
	</form>
	<?php
};
ap_modal_start( 'vei-0', 'Novo veículo' );
$vform();
ap_modal_end();
foreach ( $all as $v ) {
	ap_modal_start( 'vei-' . $v->id, 'Veículo · ' . $v->name );
	$vform( $v );
	ap_modal_end();
}
ap_modal_start( 'vei-custo', 'Lançar custo do veículo' );
ap_form( 'vehicle_cost', 'stack' );
?>
	<?php ap_select( 'vehicle_id', 'Veículo', array_combine( wp_list_pluck( $vehicles, 'id' ), array_map( 'ap_vehicle_label', $vehicles ) ) ?: array( '' => 'Cadastre um veículo primeiro' ), $sel ? $sel->id : '', 'data-vei-select' ); ?>
	<div class="grid-2">
		<?php ap_select( 'vtype', 'O que foi', $types, 'Combustível', 'data-vei-type' ); ?>
		<?php ap_input( 'amount', 'Valor (R$)', '', 'text', 'inputmode="decimal" data-money required' ); ?>
		<?php ap_input( 'date', 'Data', ap_today(), 'date' ); ?>
		<?php ap_select( 'method', 'Forma de pagamento', ap_list_options( 'metodos' ) ); ?>
	</div>
	<div class="grid-2" data-vei-fuel>
		<?php ap_input( 'liters', 'Litros abastecidos', '', 'text', 'inputmode="decimal" placeholder="0,0"' ); ?>
		<?php ap_input( 'odometer', 'Km no painel', '', 'number', 'min="0"' ); ?>
	</div>
	<div class="grid-2" data-vei-parc hidden>
		<?php ap_input( 'parcelas', 'Parcelas (mês a mês)', '1', 'number', 'min="1" max="12"' ); ?>
		<p class="muted small" style="align-self:end">IPVA e seguro: informe o valor total e o número de parcelas. Cada parcela vira uma conta a pagar no financeiro.</p>
	</div>
	<?php ap_input( 'note', 'Observação (posto, oficina…)', '' ); ?>
	<?php ap_check( 'paid', 'Já está pago', true ); ?>
	<div class="form-actions"><button type="button" class="btn btn--ghost" data-close>Cancelar</button><button type="submit" class="btn btn--primary">Lançar no financeiro</button></div>
</form>
<?php ap_modal_end(); ?>
<script>
(function () {
	var m = document.getElementById('vei-custo'); if (!m) return;
	var t = m.querySelector('[data-vei-type]'), fuel = m.querySelector('[data-vei-fuel]'), parc = m.querySelector('[data-vei-parc]');
	function sync() { fuel.hidden = t.value !== 'Combustível'; parc.hidden = !/^(IPVA|Seguro|Licenciamento|Manutenção|Pneus)$/.test(t.value); }
	t.addEventListener('change', sync); sync();
	document.addEventListener('click', function (e) { var b = e.target.closest('[data-veh]'); if (b) { var s = m.querySelector('[data-vei-select]'); if (s) s.value = b.getAttribute('data-veh'); } });
})();
</script>
<?php
ap_panel_end();

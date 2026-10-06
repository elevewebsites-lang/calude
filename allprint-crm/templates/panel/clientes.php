<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
global $wpdb;
$clients = ap_clients();
$counts  = array();
foreach ( $wpdb->get_results( 'SELECT client_id, COUNT(*) AS n, SUM(archived = 0) AS active FROM ' . ap_table( 'projects' ) . ' GROUP BY client_id' ) as $r ) { // phpcs:ignore WordPress.DB.PreparedSQL
	$counts[ $r->client_id ] = $r;
}

$actions = '<button type="button" class="btn btn--primary" data-open="novo-cliente">' . ap_icon( 'mais', 16 ) . '<span>Novo cliente</span></button>';
$pend = count( array_filter( $clients, function ( $c ) { return ! $c->approved; } ) );
ap_panel_start( 'Clientes', 'clientes', $actions );
?>

<div class="toolbar">
	<input type="search" class="filter" placeholder="Filtrar por nome, empresa ou e-mail…" data-filter=".table-row">
	<span class="muted small"><?php echo (int) count( $clients ); ?> clientes<?php echo $pend ? ' · <strong class="text-warn">' . (int) $pend . ' aguardando aprovação</strong>' : ''; // phpcs:ignore ?></span>
</div>
<p class="muted small hint">Novos clientes se cadastram em <a href="<?php echo esc_url( ap_url( 'cadastro' ) ); ?>" target="_blank"><?php echo esc_html( ap_url( 'cadastro' ) ); ?></a> (coloque esse link no botão "Seja cliente" do site). Aprove na ficha de cada um.</p>

<?php if ( ! $clients ) : ?>
	<div class="empty empty--big">
		<?php echo ap_icon( 'clientes', 28 ); // phpcs:ignore ?>
		<h3>Nenhum cliente ainda</h3>
		<p>Cadastre o primeiro cliente e mande o link de convite para ele criar o acesso.</p>
		<button type="button" class="btn btn--primary" data-open="novo-cliente">Cadastrar cliente</button>
	</div>
<?php else : ?>
	<div class="table">
		<div class="table-row table-head"><span>Cliente</span><span>Contato</span><span>Pedidos</span><span>Status</span><span></span></div>
		<?php foreach ( $clients as $c ) : ?>
			<?php $n = isset( $counts[ $c->id ] ) ? $counts[ $c->id ] : null; ?>
			<a class="table-row" href="<?php echo esc_url( ap_panel_url( 'cliente', $c->id ) ); ?>">
				<span class="cell-main"><span class="avatar"><?php echo esc_html( ap_initials( ap_client_label( $c ) ) ); ?></span><span><strong><?php echo esc_html( ap_client_label( $c ) ); ?></strong><small><?php echo esc_html( $c->company && $c->name ? $c->name : $c->cnpj ); ?></small></span></span>
				<span data-label="Contato"><?php echo esc_html( $c->whatsapp ? $c->whatsapp : $c->phone ); ?><small><?php echo esc_html( $c->email ); ?></small></span>
				<span data-label="Pedidos"><?php echo $n ? (int) $n->active . ' ativo' . ( 1 === (int) $n->active ? '' : 's' ) . ' <small>' . (int) $n->n . ' no total</small>' : '<span class="muted">—</span>'; // phpcs:ignore ?></span>
				<span data-label="Status"><em class="badge badge--<?php echo $c->approved ? 'ok' : 'warn'; ?>"><?php echo $c->approved ? 'aprovado' : 'em análise'; ?></em></span>
				<span class="cell-arrow"><?php echo ap_icon( 'seta', 16 ); // phpcs:ignore ?></span>
			</a>
		<?php endforeach; ?>
	</div>
<?php endif; ?>

<?php ap_modal_start( 'novo-cliente', 'Novo cliente' ); ?>
	<?php ap_form( 'client_save', 'stack' ); ?>
		<p class="muted small">Só a empresa já basta: o cliente completa o resto pelo link de convite.</p>
		<div class="grid-2">
			<?php ap_input( 'company', 'Empresa', '', 'text', 'required' ); ?>
			<?php ap_input( 'name', 'Nome do contato' ); ?>
			<?php ap_input( 'whatsapp', 'WhatsApp', '', 'tel', 'data-mask="phone"' ); ?>
			<?php ap_input( 'email', 'E-mail', '', 'email' ); ?>
		</div>
		<div class="form-actions"><button type="button" class="btn btn--ghost" data-close>Cancelar</button><button type="submit" class="btn btn--primary">Criar e gerar convite</button></div>
	</form>
<?php ap_modal_end(); ?>

<?php
ap_panel_end();

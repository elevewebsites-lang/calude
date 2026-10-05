<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
global $wpdb;
$clients = lk_clients();
$counts  = array();
foreach ( $wpdb->get_results( 'SELECT client_id, COUNT(*) AS n, SUM(archived = 0) AS active FROM ' . lk_table( 'projects' ) . ' GROUP BY client_id' ) as $r ) { // phpcs:ignore WordPress.DB.PreparedSQL
	$counts[ $r->client_id ] = $r;
}

$actions = '<button type="button" class="btn btn--primary" data-open="novo-cliente">' . lk_icon( 'mais', 16 ) . '<span>Novo cliente</span></button>';
lk_panel_start( 'Clientes', 'clientes', $actions );
?>

<div class="toolbar">
	<input type="search" class="filter" placeholder="Filtrar por nome, empresa ou e-mail…" data-filter=".table-row">
	<span class="muted small"><?php echo (int) count( $clients ); ?> clientes</span>
</div>

<?php if ( ! $clients ) : ?>
	<div class="empty empty--big">
		<?php echo lk_icon( 'clientes', 28 ); // phpcs:ignore ?>
		<h3>Nenhum cliente ainda</h3>
		<p>Cadastre o primeiro cliente e mande o link de convite para ele criar o acesso.</p>
		<button type="button" class="btn btn--primary" data-open="novo-cliente">Cadastrar cliente</button>
	</div>
<?php else : ?>
	<div class="table">
		<div class="table-row table-head"><span>Cliente</span><span>Contato</span><span>Projetos</span><span>Acesso</span><span></span></div>
		<?php foreach ( $clients as $c ) : ?>
			<?php $n = isset( $counts[ $c->id ] ) ? $counts[ $c->id ] : null; ?>
			<a class="table-row" href="<?php echo esc_url( lk_panel_url( 'cliente', $c->id ) ); ?>">
				<span class="cell-main"><span class="avatar"><?php echo esc_html( lk_initials( lk_client_label( $c ) ) ); ?></span><span><strong><?php echo esc_html( lk_client_label( $c ) ); ?></strong><small><?php echo esc_html( $c->company && $c->name ? $c->name : $c->cnpj ); ?></small></span></span>
				<span data-label="Contato"><?php echo esc_html( $c->whatsapp ? $c->whatsapp : $c->phone ); ?><small><?php echo esc_html( $c->email ); ?></small></span>
				<span data-label="Projetos"><?php echo $n ? (int) $n->active . ' ativo' . ( 1 === (int) $n->active ? '' : 's' ) . ' <small>' . (int) $n->n . ' no total</small>' : '<span class="muted">—</span>'; // phpcs:ignore ?></span>
				<span data-label="Acesso"><?php echo lk_status_badge( $c->user_id ? 'ativo' : 'convidado' ); // phpcs:ignore ?></span>
				<span class="cell-arrow"><?php echo lk_icon( 'seta', 16 ); // phpcs:ignore ?></span>
			</a>
		<?php endforeach; ?>
	</div>
<?php endif; ?>

<?php lk_modal_start( 'novo-cliente', 'Novo cliente' ); ?>
	<?php lk_form( 'client_save', 'stack' ); ?>
		<p class="muted small">Só a empresa e o projeto já bastam: depois é só copiar o link e mandar. O cliente preenche os dados dele e cria a senha. Se preferir, preencha tudo você mesmo na ficha.</p>
		<div class="grid-2">
			<?php lk_input( 'company', 'Empresa', '', 'text', 'required' ); ?>
			<?php lk_input( 'name', 'Nome do contato' ); ?>
			<?php lk_input( 'whatsapp', 'WhatsApp', '', 'tel', 'data-mask="phone"' ); ?>
			<?php lk_input( 'email', 'E-mail', '', 'email' ); ?>
		</div>
		<?php lk_input( 'projeto', 'Projeto / serviço contratado', '', 'text', 'placeholder="Ex.: Gestão de redes · plano Essencial"' ); ?>
		<div class="form-actions"><button type="button" class="btn btn--ghost" data-close>Cancelar</button><button type="submit" class="btn btn--primary">Criar e gerar convite</button></div>
	</form>
<?php lk_modal_end(); ?>

<?php
lk_panel_end();

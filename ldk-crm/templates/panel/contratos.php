<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
$status = isset( $_GET['ver'] ) ? sanitize_key( $_GET['ver'] ) : 'todos'; // phpcs:ignore WordPress.Security.NonceVerification
$q      = isset( $_GET['q'] ) ? sanitize_text_field( wp_unslash( $_GET['q'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification
$all    = lk_rows( 'contracts', '1=1', array(), 'id DESC' );
$groups = array(
	'todos'     => array( 'Todos', null ),
	'rascunho'  => array( 'Rascunhos', array( 'rascunho' ) ),
	'enviado'   => array( 'Aguardando o cliente', array( 'enviado' ) ),
	'assinado'  => array( 'Falta a agência', array( 'assinado' ) ),
	'concluido' => array( 'Assinados', array( 'concluido' ) ),
	'cancelado' => array( 'Cancelados', array( 'cancelado' ) ),
);
$count = array();
foreach ( $groups as $gk => $g ) {
	$count[ $gk ] = $g[1] ? count( array_filter( $all, function ( $k ) use ( $g ) { return in_array( $k->status, $g[1], true ); } ) ) : count( $all );
}
$status = isset( $groups[ $status ] ) ? $status : 'todos';
$rows   = array_filter(
	$all,
	function ( $k ) use ( $groups, $status, $q ) {
		if ( $groups[ $status ][1] && ! in_array( $k->status, $groups[ $status ][1], true ) ) {
			return false;
		}
		if ( '' !== $q ) {
			$c = lk_get( 'clients', $k->client_id );
			return false !== stripos( $k->title . ' ' . ( $c ? lk_client_label( $c ) : '' ), $q );
		}
		return true;
	}
);
$mrr = 0;
foreach ( $all as $k ) {
	if ( 'concluido' === $k->status ) {
		$mrr += (float) $k->monthly_value;
	}
}
lk_panel_start( 'Contratos', 'contratos', '<button type="button" class="btn btn--ghost" data-open="contrato-assinado-central">Subir contrato assinado</button> <button type="button" class="btn btn--primary" data-open="novo-contrato-central">+ Novo contrato</button>' );
?>
<section class="stats stats--4">
	<div class="stat"><span class="stat-label">Assinados</span><strong><?php echo (int) $count['concluido']; ?></strong><small>pelas duas partes</small></div>
	<div class="stat"><span class="stat-label">Aguardando o cliente</span><strong><?php echo (int) $count['enviado']; ?></strong><small>enviados</small></div>
	<div class="stat"><span class="stat-label">Falta a agência</span><strong class="<?php echo $count['assinado'] ? 'text-late' : ''; ?>"><?php echo (int) $count['assinado']; ?></strong><small>o cliente já assinou</small></div>
	<div class="stat"><span class="stat-label">Mensalidades em contrato</span><strong class="money"><?php echo esc_html( lk_money( $mrr ) ); ?></strong><small>contratos assinados</small></div>
</section>
<section class="card">
	<div class="card-head"><span class="chips"><?php foreach ( $groups as $gk => $g ) : ?><a class="btn btn--sm btn--<?php echo $status === $gk ? 'primary' : 'ghost'; ?>" href="<?php echo esc_url( lk_panel_url( 'contratos', 0, array_filter( array( 'ver' => 'todos' === $gk ? '' : $gk, 'q' => $q ) ) ) ); ?>"><?php echo esc_html( $g[0] . ' (' . $count[ $gk ] . ')' ); ?></a> <?php endforeach; ?></span>
		<form method="get" class="inline-form ctr-search"><input type="search" name="q" value="<?php echo esc_attr( $q ); ?>" placeholder="Buscar por cliente ou título…"></form></div>
	<div class="pros-wrap"><table class="pros-table ctr-table">
		<thead><tr><th>Cliente</th><th>Contrato</th><th>Mensal</th><th>Prazo</th><th>Status</th><th>Assinado em</th><th></th></tr></thead>
		<tbody>
		<?php foreach ( $rows as $k ) : $c = lk_get( 'clients', $k->client_id ); if ( ! $c ) { continue; } ?>
			<tr>
				<td><a class="net-cli" href="<?php echo esc_url( lk_panel_url( 'cliente', $c->id ) ); ?>#contratos"><?php echo lk_client_avatar_html( $c ); // phpcs:ignore ?><strong><?php echo esc_html( lk_client_label( $c ) ); ?></strong></a></td>
				<td><a href="<?php echo esc_url( lk_panel_url( 'contrato', $k->id ) ); ?>"><strong><?php echo esc_html( $k->title ); ?></strong></a></td>
				<td><?php echo esc_html( lk_money( $k->monthly_value ) ); ?></td>
				<td><?php echo (int) $k->months; ?> meses<br><small class="muted"><?php echo esc_html( $k->start_date ? 'desde ' . lk_date( $k->start_date ) : '' ); ?></small></td>
				<td><em class="badge <?php echo 'concluido' === $k->status ? 'badge--ok' : ( 'cancelado' === $k->status ? 'badge--late' : ( 'assinado' === $k->status ? 'badge--warn' : '' ) ); ?>"><?php echo esc_html( lk_contract_status_label( $k->status ) ); ?></em></td>
				<td><?php echo $k->signed_at ? esc_html( lk_date( $k->signed_at, 'd/m/Y' ) ) : '—'; ?></td>
				<td class="net-act">
					<a class="btn btn--ghost btn--sm" href="<?php echo esc_url( lk_panel_url( 'contrato', $k->id ) ); ?>">Abrir</a>
					<?php if ( 'enviado' === $k->status ) : ?><button type="button" class="btn btn--ghost btn--sm" data-copy="<?php echo esc_attr( lk_contract_url( $k ) ); ?>">Copiar link</button><?php endif; ?>
					<?php if ( $k->drive_url ) : ?><a class="btn btn--ghost btn--sm" href="<?php echo esc_url( $k->drive_url ); ?>" target="_blank" rel="noopener">Drive</a><?php endif; ?>
				</td>
			</tr>
		<?php endforeach; ?>
		<?php if ( ! $rows ) : ?><tr><td colspan="7" class="muted">Nenhum contrato por aqui. Use <strong>+ Novo contrato</strong> ou, no <a href="<?php echo esc_url( lk_panel_url( 'leads' ) ); ?>">funil</a>, clique em <strong>Gerar contrato</strong> no lead que aceitou.</td></tr><?php endif; ?>
		</tbody>
	</table></div>
</section>
<?php lk_modal_start( 'novo-contrato-central', 'Novo contrato' ); ?>
	<?php lk_form( 'contract_new', 'stack' ); ?>
		<?php lk_select( 'client_id', 'Cliente', lk_client_options( 'Escolha o cliente…' ), '', 'required' ); ?>
		<p class="muted small">O contrato nasce com os dados do cliente e da agência. Depois você ajusta valores, inclui cláusulas e envia para assinatura.</p>
		<div class="form-actions"><button type="button" class="btn btn--ghost" data-close>Cancelar</button><button type="submit" class="btn btn--primary">Criar contrato</button></div>
	</form>
<?php lk_modal_end(); ?>
<?php lk_modal_start( 'contrato-assinado-central', 'Subir contrato já assinado' ); lk_contract_upload_form( null ); lk_modal_end(); ?>
<?php
lk_panel_end();

<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
$status = isset( $_GET['ver'] ) ? sanitize_key( $_GET['ver'] ) : 'pendentes'; // phpcs:ignore WordPress.Security.NonceVerification
$assunto = isset( $_GET['a'] ) ? sanitize_key( $_GET['a'] ) : ''; // phpcs:ignore WordPress.Security.NonceVerification
$q      = isset( $_GET['q'] ) ? sanitize_text_field( wp_unslash( $_GET['q'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification
$all    = lk_rows( 'contracts', '1=1', array(), 'id DESC' );
$types  = lk_service_types();
$groups = array(
	'pendentes' => array( 'Pendentes', lk_contract_pending_statuses() ),
	'enviado'   => array( 'Aguardando o cliente', array( 'enviado' ) ),
	'assinado'  => array( 'Falta a agência', array( 'assinado' ) ),
	'rascunho'  => array( 'Rascunhos', array( 'rascunho' ) ),
	'concluido' => array( 'Assinados', array( 'concluido' ) ),
	'cancelado' => array( 'Cancelados', array( 'cancelado' ) ),
	'todos'     => array( 'Todos', null ),
);
$in_group = function ( $k, $gk ) use ( $groups ) {
	return ! $groups[ $gk ][1] || in_array( $k->status, $groups[ $gk ][1], true );
};
$count = array();
foreach ( $groups as $gk => $g ) {
	$count[ $gk ] = count( array_filter( $all, function ( $k ) use ( $in_group, $gk ) { return $in_group( $k, $gk ); } ) );
}
$status = isset( $groups[ $status ] ) ? $status : 'pendentes';
$assunto = ( 'sem' === $assunto || isset( $types[ $assunto ] ) ) ? $assunto : '';
$by_type = array( 'sem' => 0 );
foreach ( $types as $tk => $t ) {
	$by_type[ $tk ] = 0;
}
foreach ( $all as $k ) {
	if ( ! $in_group( $k, $status ) ) {
		continue;
	}
	$ks = lk_contract_subjects( $k );
	if ( ! $ks ) {
		$by_type['sem']++;
	}
	foreach ( $ks as $tk ) {
		$by_type[ $tk ]++;
	}
}
$rows = array_filter(
	$all,
	function ( $k ) use ( $in_group, $status, $assunto, $q ) {
		if ( ! $in_group( $k, $status ) ) {
			return false;
		}
		if ( $assunto ) {
			$ks = lk_contract_subjects( $k );
			if ( 'sem' === $assunto ? (bool) $ks : ! in_array( $assunto, $ks, true ) ) {
				return false;
			}
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
$late_days = 0;
foreach ( $all as $k ) {
	if ( lk_contract_days_waiting( $k ) >= 1 ) {
		$late_days++;
	}
}
$url = function ( $ver, $a = '' ) use ( $q ) {
	return lk_panel_url( 'contratos', 0, array_filter( array( 'ver' => 'pendentes' === $ver ? '' : $ver, 'a' => $a, 'q' => $q ) ) );
};
lk_panel_start( 'Contratos', 'contratos', '<button type="button" class="btn btn--ghost" data-open="contrato-assinado-central">Subir contrato assinado</button> <button type="button" class="btn btn--primary" data-open="novo-contrato-central">Gerar contrato</button>' );
?>
<section class="stats stats--4">
	<div class="stat"><span class="stat-label">Pendentes</span><strong><?php echo (int) $count['pendentes']; ?></strong><small>rascunho, enviado ou sem a agência</small></div>
	<div class="stat"><span class="stat-label">Sem assinar há 1 dia ou mais</span><strong class="<?php echo $late_days ? 'text-late' : ''; ?>"><?php echo (int) $late_days; ?></strong><small>avisamos você automaticamente</small></div>
	<div class="stat"><span class="stat-label">Assinados</span><strong><?php echo (int) $count['concluido']; ?></strong><small>pelas duas partes</small></div>
	<div class="stat"><span class="stat-label">Mensalidades em contrato</span><strong class="money"><?php echo esc_html( lk_money( $mrr ) ); ?></strong><small>contratos assinados</small></div>
</section>
<div class="ctr-layout">
	<aside class="ctr-nav card">
		<span class="side-label">Situação</span>
		<?php foreach ( $groups as $gk => $g ) : ?>
			<a class="<?php echo $status === $gk && ! $assunto ? 'is-active' : ''; ?>" href="<?php echo esc_url( $url( $gk ) ); ?>"><span><?php echo esc_html( $g[0] ); ?></span><em><?php echo (int) $count[ $gk ]; ?></em></a>
		<?php endforeach; ?>
		<span class="side-label">Assunto <small>(<?php echo esc_html( mb_strtolower( $groups[ $status ][0] ) ); ?>)</small></span>
		<?php foreach ( $types as $tk => $t ) : ?>
			<a class="<?php echo $assunto === $tk ? 'is-active' : ''; ?>" href="<?php echo esc_url( $url( $status, $tk ) ); ?>"><span><?php echo esc_html( $t['name'] ); ?></span><em><?php echo (int) $by_type[ $tk ]; ?></em></a>
		<?php endforeach; ?>
		<a class="<?php echo 'sem' === $assunto ? 'is-active' : ''; ?>" href="<?php echo esc_url( $url( $status, 'sem' ) ); ?>"><span>Sem assunto</span><em><?php echo (int) $by_type['sem']; ?></em></a>
	</aside>
	<section class="card ctr-main">
		<div class="card-head"><h3><?php echo esc_html( $assunto ? ( 'sem' === $assunto ? 'Sem assunto' : $types[ $assunto ]['name'] ) . ' · ' . $groups[ $status ][0] : $groups[ $status ][0] ); ?></h3>
			<form method="get" class="inline-form ctr-search"><input type="hidden" name="ver" value="<?php echo esc_attr( 'pendentes' === $status ? '' : $status ); ?>"><input type="hidden" name="a" value="<?php echo esc_attr( $assunto ); ?>"><input type="search" name="q" value="<?php echo esc_attr( $q ); ?>" placeholder="Buscar por cliente ou título…"></form></div>
		<div class="pros-wrap"><table class="pros-table ctr-table">
			<thead><tr><th>Cliente</th><th>Contrato</th><th>Assunto</th><th>Mensal</th><th>Status</th><th>Esperando</th><th></th></tr></thead>
			<tbody>
			<?php foreach ( $rows as $k ) : $c = lk_get( 'clients', $k->client_id ); if ( ! $c ) { continue; } $dw = lk_contract_days_waiting( $k ); ?>
				<tr>
					<td><a class="net-cli" href="<?php echo esc_url( lk_panel_url( 'cliente', $c->id ) ); ?>#contratos"><?php echo lk_client_avatar_html( $c ); // phpcs:ignore ?><strong><?php echo esc_html( lk_client_label( $c ) ); ?></strong></a></td>
					<td><a href="<?php echo esc_url( lk_panel_url( 'contrato', $k->id ) ); ?>"><strong><?php echo esc_html( $k->title ); ?></strong></a><br><small class="muted"><?php echo (int) $k->months; ?> meses<?php echo $k->start_date ? ' · desde ' . esc_html( lk_date( $k->start_date ) ) : ''; ?></small></td>
					<td><?php echo esc_html( implode( ', ', lk_contract_subject_names( $k ) ) ?: '—' ); ?></td>
					<td><?php echo esc_html( lk_money( $k->monthly_value ) ); ?></td>
					<td><em class="badge <?php echo 'concluido' === $k->status ? 'badge--ok' : ( 'cancelado' === $k->status ? 'badge--late' : ( 'assinado' === $k->status ? 'badge--warn' : '' ) ); ?>"><?php echo esc_html( lk_contract_status_label( $k->status ) ); ?></em><?php echo $k->signed_at ? '<br><small class="muted">assinado ' . esc_html( lk_date( $k->signed_at, 'd/m/Y' ) ) . '</small>' : ''; ?></td>
					<td><?php if ( 'enviado' === $k->status ) : ?><em class="badge <?php echo $dw >= 1 ? 'badge--late' : 'badge--off'; ?>"><?php echo $dw >= 1 ? (int) $dw . ( 1 === $dw ? ' dia' : ' dias' ) : 'hoje'; ?></em><?php else : ?>—<?php endif; ?></td>
					<td class="net-act">
						<a class="btn btn--ghost btn--sm" href="<?php echo esc_url( lk_panel_url( 'contrato', $k->id ) ); ?>">Abrir</a>
						<?php if ( 'enviado' === $k->status ) : ?><button type="button" class="btn btn--ghost btn--sm" data-copy="<?php echo esc_attr( lk_contract_url( $k ) ); ?>">Copiar link</button><?php endif; ?>
						<?php if ( $k->drive_url ) : ?><a class="btn btn--ghost btn--sm" href="<?php echo esc_url( $k->drive_url ); ?>" target="_blank" rel="noopener">Drive</a><?php endif; ?>
					</td>
				</tr>
			<?php endforeach; ?>
			<?php if ( ! $rows ) : ?><tr><td colspan="7" class="muted">Nenhum contrato aqui. Use <strong>Gerar contrato</strong>, escolha o cliente e marque os serviços: os dados entram sozinhos.</td></tr><?php endif; ?>
			</tbody>
		</table></div>
	</section>
</div>
<?php lk_modal_start( 'novo-contrato-central', 'Gerar contrato' ); lk_contract_generate_form(); lk_modal_end(); ?>
<?php lk_modal_start( 'contrato-assinado-central', 'Subir contrato já assinado' ); lk_contract_upload_form( null ); lk_modal_end(); ?>
<?php
lk_panel_end();

<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
$busca = isset( $_GET['q'] ) ? sanitize_text_field( wp_unslash( $_GET['q'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification
$ver   = isset( $_GET['ver'] ) ? sanitize_key( $_GET['ver'] ) : 'todas'; // phpcs:ignore WordPress.Security.NonceVerification
$all   = get_posts(
	array(
		'post_type'   => 'ldkp_proposta',
		'post_status' => array( 'publish', 'draft' ),
		'numberposts' => 300,
		's'           => $busca,
	)
);
$count = array( 'todas' => count( $all ), 'rascunho' => 0, 'nao' => 0, 'enviada' => 0, 'vista' => 0 );
$rows  = array();
foreach ( $all as $p ) {
	$sent  = get_post_meta( $p->ID, '_lk_sent_at', true );
	$views = (int) get_post_meta( $p->ID, '_ldkp_views', true );
	$st    = 'publish' !== $p->post_status ? 'rascunho' : ( $views ? 'vista' : ( $sent ? 'enviada' : 'nao' ) );
	$count[ $st ]++;
	if ( 'todas' === $ver || $ver === $st ) {
		$rows[] = array( $p, $st, $sent, $views );
	}
}
$labels = array( 'todas' => 'Todas', 'nao' => 'Não enviadas', 'enviada' => 'Enviadas', 'vista' => 'Visualizadas', 'rascunho' => 'Rascunhos' );
$badge  = array(
	'rascunho' => array( 'rascunho', '' ),
	'nao'      => array( 'não enviada', 'badge--wait' ),
	'enviada'  => array( 'enviada', '' ),
	'vista'    => array( 'visualizada', 'badge--ok' ),
);
$acoes  = '<a class="btn btn--ghost" href="' . esc_url( lk_panel_url( 'propostas-servicos' ) ) . '">' . lk_icon( 'config', 16 ) . '<span>Serviços e nichos</span></a>'
	. '<a class="btn btn--primary" href="' . esc_url( lk_panel_url( 'proposta' ) ) . '">' . lk_icon( 'mais', 16 ) . '<span>Nova proposta</span></a>';
lk_panel_start( 'Propostas', 'propostas', $acoes );
?>
<section class="stats stats--4">
	<div class="stat stat--dark"><span class="stat-label">Propostas</span><strong><?php echo (int) $count['todas']; ?></strong><small>no total</small></div>
	<a class="stat" href="<?php echo esc_url( lk_panel_url( 'propostas', 0, array( 'ver' => 'nao' ) ) ); ?>"><span class="stat-label">Não enviadas</span><strong><?php echo (int) $count['nao']; ?></strong><small>prontas para mandar</small></a>
	<a class="stat" href="<?php echo esc_url( lk_panel_url( 'propostas', 0, array( 'ver' => 'enviada' ) ) ); ?>"><span class="stat-label">Enviadas</span><strong><?php echo (int) $count['enviada']; ?></strong><small>o cliente ainda não abriu</small></a>
	<a class="stat" href="<?php echo esc_url( lk_panel_url( 'propostas', 0, array( 'ver' => 'vista' ) ) ); ?>"><span class="stat-label">Visualizadas</span><strong><?php echo (int) $count['vista']; ?></strong><small>o cliente abriu o link</small></a>
</section>

<div class="prop-bar">
	<nav class="tabs">
		<?php foreach ( $labels as $k => $l ) : ?>
			<a class="<?php echo $k === $ver ? 'is-active' : ''; ?>" href="<?php echo esc_url( lk_panel_url( 'propostas', 0, array_filter( array( 'ver' => $k, 'q' => $busca ) ) ) ); ?>"><?php echo esc_html( $l ); ?><em><?php echo (int) $count[ $k ]; ?></em></a>
		<?php endforeach; ?>
	</nav>
	<form method="get" action="<?php echo esc_url( lk_panel_url( 'propostas' ) ); ?>" class="prop-search">
		<input type="search" name="q" value="<?php echo esc_attr( $busca ); ?>" placeholder="Buscar cliente…">
	</form>
</div>

<?php if ( ! $rows ) : ?>
	<section class="card prop-empty">
		<h3><?php echo $count['todas'] ? 'Nada por aqui.' : 'Nenhuma proposta ainda.'; ?></h3>
		<p class="muted">Monte uma proposta com os textos do nicho e os planos dos serviços. Em seguida é só mandar o link pelo WhatsApp ou por e-mail.</p>
		<a class="btn btn--primary" href="<?php echo esc_url( lk_panel_url( 'proposta' ) ); ?>"><?php echo lk_icon( 'mais', 16 ); // phpcs:ignore ?><span>Montar a primeira proposta</span></a>
	</section>
<?php else : ?>
	<div class="table prop-table">
		<div class="table-row table-head"><span>Cliente</span><span>Nicho</span><span>Criada</span><span>Status</span><span>Visualizações</span><span></span></div>
		<?php foreach ( $rows as $r ) : ?>
			<?php
			list( $p, $st, $sent, $views ) = $r;
			$niche = (int) get_post_meta( $p->ID, '_ldkp_nicho_id', true );
			$last  = get_post_meta( $p->ID, '_ldkp_last_view', true );
			?>
			<div class="table-row">
				<a class="cell-main" href="<?php echo esc_url( lk_panel_url( 'proposta', $p->ID ) ); ?>"><strong><?php echo esc_html( $p->post_title ? $p->post_title : '(sem nome)' ); ?></strong><small class="muted"><?php echo esc_html( get_the_author_meta( 'display_name', $p->post_author ) ); ?></small></a>
				<span data-label="Nicho"><?php echo $niche ? esc_html( get_the_title( $niche ) ) : '—'; ?></span>
				<span data-label="Criada"><?php echo esc_html( get_the_date( 'd/m/Y', $p ) ); ?></span>
				<span data-label="Status"><em class="badge <?php echo esc_attr( $badge[ $st ][1] ); ?>"><?php echo esc_html( $badge[ $st ][0] ); ?></em><?php echo $sent ? '<small class="muted"> ' . esc_html( lk_date( $sent, 'd/m' ) ) . '</small>' : ''; ?></span>
				<span data-label="Visualizações"><strong><?php echo (int) $views; ?></strong><?php echo $last ? '<small class="muted"> última ' . esc_html( mysql2date( 'd/m H:i', $last ) ) . '</small>' : ''; ?></span>
				<span class="prop-acts">
					<?php if ( 'publish' === $p->post_status ) : ?>
						<button type="button" class="btn btn--ghost btn--sm" data-copy="<?php echo esc_attr( get_permalink( $p ) ); ?>" title="Copiar o link da proposta"><?php echo lk_icon( 'copiar', 14 ); // phpcs:ignore ?><span>Link</span></button>
					<?php endif; ?>
					<a class="btn btn--primary btn--sm" href="<?php echo esc_url( lk_panel_url( 'proposta', $p->ID ) . '#enviar' ); ?>">Enviar</a>
					<a class="btn btn--ghost btn--sm" href="<?php echo esc_url( lk_panel_url( 'proposta', $p->ID ) . '#montar' ); ?>">Editar</a>
				</span>
			</div>
		<?php endforeach; ?>
	</div>
<?php endif; ?>
<?php
lk_panel_end();

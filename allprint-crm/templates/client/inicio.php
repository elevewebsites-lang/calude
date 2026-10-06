<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
$orders  = ap_projects( 'p.client_id = %d AND p.archived = 0', array( $client->id ) );
$cols    = ap_columns();
$welcome = isset( $_GET['bemvindo'] ); // phpcs:ignore WordPress.Security.NonceVerification
$quotes  = ap_rows( 'quotes', "client_id = %d AND status IN ('enviado','visto')", array( $client->id ), 'id DESC' );

if ( false ) {
	wp_safe_redirect( ap_client_link( 'projeto', $orders[0]->id ) );
	exit;
}
ap_client_start( 'Meus pedidos', $client );
$first = $client->name ? strtok( $client->name, ' ' ) : ap_client_label( $client );
?>
<section class="chello">
	<span class="eyebrow">Área do cliente</span>
	<h1>Olá, <?php echo esc_html( $first ); ?>.</h1>
	<p class="muted"><?php echo $welcome ? 'Seu acesso está pronto. Aqui você acompanha a produção dos seus pedidos e vê as fotos quando ficarem prontos.' : 'Acompanhe seus pedidos.'; ?></p>
</section>

<?php if ( empty( $client->approved ) ) : ?>
	<div class="flash flash--warn"><strong>Cadastro em análise.</strong> A <?php echo esc_html( ap_setting( 'empresa' ) ); ?> está conferindo os seus dados. Assim que aprovar, você recebe um e-mail e já pode ver os preços e fazer pedidos.</div>
<?php else : ?>
	<div class="quick-actions">
		<a class="qa qa--main" href="<?php echo esc_url( ap_client_link( 'novo' ) ); ?>"><?php echo ap_icon( 'mais', 22 ); // phpcs:ignore ?><span><strong>Novo pedido</strong><small>material, medidas, arquivo e pagamento</small></span></a>
		<a class="qa" href="<?php echo esc_url( ap_client_link( 'tabela' ) ); ?>"><?php echo ap_icon( 'tag', 22 ); // phpcs:ignore ?><span><strong>Tabela de preços</strong><small>valores de cliente por m²</small></span></a>
		<a class="qa" href="<?php echo esc_url( ap_client_link( 'mensagens' ) ); ?>"><?php echo ap_icon( 'chat', 22 ); // phpcs:ignore ?><span><strong>Mensagens</strong><small>fale com a produção</small></span></a>
	</div>
<?php endif; ?>
<?php if ( $quotes ) : ?>
	<section class="card">
		<div class="card-head"><h3>Orçamentos para você</h3></div>
		<ul class="mini-list">
			<?php foreach ( $quotes as $q ) : ?><li><a href="<?php echo esc_url( ap_quote_url( $q ) ); ?>"><strong><?php echo esc_html( $q->title ); ?></strong><small><?php echo esc_html( ap_money( $q->total ) . ( $q->valid_until ? ' · válido até ' . ap_date( $q->valid_until ) : '' ) ); ?></small></a></li><?php endforeach; ?>
		</ul>
	</section>
<?php endif; ?>

<?php if ( ! $orders ) : ?>
	<div class="empty empty--big"><?php echo ap_icon( 'cubo', 28 ); // phpcs:ignore ?><h3>Seus pedidos vão aparecer aqui</h3><p>Assim que um orçamento for aprovado, você acompanha tudo por esta área.</p></div>
<?php else : ?>
	<div class="cprojects">
		<?php foreach ( $orders as $p ) : ?>
			<?php
			$keys  = array_keys( $cols );
			$pos   = array_search( $p->status, $keys, true );
			$pct   = false === $pos ? 0 : (int) round( $pos / max( 1, count( $keys ) - 1 ) * 100 );
			$ph    = ap_order_photos( $p );
			?>
			<a class="cproject" href="<?php echo esc_url( ap_client_link( 'projeto', $p->id ) ); ?>">
				<?php if ( $ph ) : ?><img class="cproject-img" src="<?php echo esc_url( $ph[0]['thumb'] ?? $ph[0]['url'] ); ?>" alt=""><?php endif; ?>
				<div class="cproject-top"><span class="badge">Pedido #<?php echo (int) $p->id; ?></span><?php if ( 'problema' === $p->art_status ) : ?><span class="badge badge--late">arte com ajuste</span><?php endif; ?><span class="muted small"><?php echo esc_html( $cols[ $p->status ] ?? '' ); ?></span></div>
				<h3><?php echo esc_html( $p->title ); ?></h3>
				<?php echo ap_progress_bar( $pct ); // phpcs:ignore ?>
			</a>
		<?php endforeach; ?>
	</div>
<?php endif; ?>
<?php
ap_client_end();

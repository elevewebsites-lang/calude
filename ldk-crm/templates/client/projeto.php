<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
$p = $id ? lk_project( $id ) : null;
if ( ! $p || (int) $p->client_id !== (int) $client->id ) {
	lk_render( 'panel/404' );
}
$cols   = lk_columns();
$keys   = array_keys( $cols );
$pos    = array_search( $p->status, $keys, true );
$items  = lk_order_items( $p );
$photos = lk_order_photos( $p );
$trans  = lk_rows( 'transactions', "project_id = %d AND type = 'in'", array( $p->id ), 'due_date, id' );
$log    = lk_rows( 'activity', 'project_id = %d AND client_visible = 1', array( $p->id ), 'id DESC LIMIT 30' );
lk_client_start( $p->title, $client );
?>
<a class="back" href="<?php echo esc_url( lk_client_link() ); ?>"><?php echo lk_icon( 'voltar', 16 ); // phpcs:ignore ?> Meus pedidos</a>
<section class="chello">
	<span class="eyebrow">Pedido #<?php echo (int) $p->id; ?></span>
	<h1><?php echo esc_html( $p->title ); ?></h1>
	<?php if ( $p->due_date && $p->status !== lk_last_column() ) : ?><p class="muted">Previsão: <?php echo esc_html( lk_date( $p->due_date ) ); ?></p><?php endif; ?>
</section>

<section class="card">
	<ol class="csteps">
		<?php foreach ( $keys as $i => $k ) : ?>
			<li class="<?php echo false !== $pos && $i < $pos ? 'is-done' : ( $i === $pos ? 'is-now' : '' ); ?>"><span><?php echo $i < (int) $pos ? '✓' : (int) ( $i + 1 ); ?></span><?php echo esc_html( $cols[ $k ] ); ?></li>
		<?php endforeach; ?>
	</ol>
	<?php if ( $p->status === lk_ready_column() ) : ?>
		<div class="flash flash--ok"><?php echo 'envio' === $p->delivery_mode ? 'Seu pedido está pronto e vai ser despachado.' . ( $p->tracking ? ' Rastreio: ' . esc_html( $p->tracking ) : '' ) : esc_html( 'Seu pedido está pronto! ' . lk_setting( 'retirada_texto' ) ); ?></div>
	<?php endif; ?>
	<?php if ( $p->tracking ) : ?><p class="small">Código de rastreio: <strong><?php echo esc_html( $p->tracking ); ?></strong> · <a href="<?php echo esc_url( 'https://www.linkcorreios.com.br/?id=' . rawurlencode( $p->tracking ) ); ?>" target="_blank" rel="noopener">rastrear ↗</a></p><?php endif; ?>
</section>

<?php if ( $photos ) : ?>
	<section class="card">
		<div class="card-head"><h3>Fotos do seu pedido</h3></div>
		<div class="thumbs thumbs--big">
			<?php foreach ( $photos as $img ) : ?><a href="<?php echo esc_url( $img['url'] ); ?>" target="_blank" rel="noopener"><img src="<?php echo esc_url( $img['thumb'] ?? $img['url'] ); ?>" alt=""></a><?php endforeach; ?>
		</div>
	</section>
<?php endif; ?>

<div class="split">
	<div class="split-main">
		<?php if ( $items ) : ?>
			<section class="card">
				<div class="card-head"><h3>Itens</h3></div>
				<div class="items-list">
					<?php foreach ( $items as $it ) : ?><div class="items-row"><span><strong><?php echo (int) ( $it['qty'] ?? 1 ); ?>× <?php echo esc_html( $it['name'] ?? '' ); ?></strong><?php if ( ! empty( $it['desc'] ) ) : ?><small><?php echo esc_html( $it['desc'] ); ?></small><?php endif; ?></span><span class="money"><?php echo esc_html( lk_money( (float) ( $it['unit'] ?? 0 ) * (int) ( $it['qty'] ?? 1 ) ) ); ?></span></div><?php endforeach; ?>
				</div>
			</section>
		<?php endif; ?>
		<section class="card">
			<div class="card-head"><h3>Novidades</h3></div>
			<ul class="timeline">
				<?php foreach ( $log as $a ) : ?><li class="is-client"><span class="timeline-dot"></span><div><?php echo esc_html( $a->body ); ?><small><?php echo esc_html( lk_ago( $a->created_at ) ); ?></small></div></li><?php endforeach; ?>
			</ul>
			<?php lk_form( 'comment_add', 'stack' ); ?>
				<input type="hidden" name="project_id" value="<?php echo (int) $p->id; ?>">
				<?php lk_input( 'body', 'Mandar uma mensagem', '', 'textarea', 'rows="2" placeholder="Dúvida, ajuste, recado…"' ); ?>
				<div class="form-actions"><button type="submit" class="btn btn--primary btn--sm">Enviar</button></div>
			</form>
		</section>
	</div>
	<aside class="split-side">
		<section class="card">
			<div class="card-head"><h3>Pagamento</h3></div>
			<?php foreach ( $trans as $t ) : ?>
				<div class="pay-row"><span><?php echo esc_html( lk_money( $t->amount ) ); ?><small><?php echo esc_html( $t->method ); ?></small></span>
				<?php if ( 'pago' === $t->status ) : ?><em class="badge badge--ok">pago</em><?php else : ?><a class="btn btn--primary btn--sm" href="<?php echo esc_url( lk_pay_url( $t->id ) ); ?>">Pagar</a><?php endif; ?></div>
			<?php endforeach; ?>
		</section>
		<section class="card">
			<div class="card-head"><h3>Entrega</h3></div>
			<p class="small"><?php echo 'envio' === $p->delivery_mode ? 'Envio' . ( $p->ship_service ? ' · ' . esc_html( $p->ship_service ) : '' ) : esc_html( lk_setting( 'retirada_texto' ) ); ?></p>
		</section>
	</aside>
</div>
<?php
lk_client_end();

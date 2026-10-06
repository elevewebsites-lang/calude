<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
$p = $id ? ap_project( $id ) : null;
if ( ! $p || (int) $p->client_id !== (int) $client->id ) {
	ap_render( 'panel/404' );
}
$cols   = ap_columns();
$keys   = array_keys( $cols );
$pos    = array_search( $p->status, $keys, true );
$items  = ap_order_items( $p );
$photos = ap_order_photos( $p );
$trans  = ap_rows( 'transactions', "project_id = %d AND type = 'in'", array( $p->id ), 'due_date, id' );
$log    = ap_rows( 'activity', 'project_id = %d AND client_visible = 1', array( $p->id ), 'id DESC LIMIT 30' );
ap_client_start( $p->title, $client );
?>
<a class="back" href="<?php echo esc_url( ap_client_link() ); ?>"><?php echo ap_icon( 'voltar', 16 ); // phpcs:ignore ?> Meus pedidos</a>
<section class="chello">
	<span class="eyebrow">Pedido #<?php echo (int) $p->id; ?></span>
	<h1><?php echo esc_html( $p->title ); ?></h1>
	<?php if ( $p->due_date && $p->status !== ap_last_column() ) : ?><p class="muted">Previsão: <?php echo esc_html( ap_date( $p->due_date ) ); ?></p><?php endif; ?>
</section>

<?php if ( 'problema' === $p->art_status ) : ?>
	<section class="card card--warn">
		<div class="card-head"><h3>Precisamos de um ajuste na arte</h3></div>
		<p><?php echo nl2br( esc_html( $p->art_note ) ); ?></p>
		<?php ap_form( 'client_files', 'stack' ); ?>
			<input type="hidden" name="id" value="<?php echo (int) $p->id; ?>">
			<?php foreach ( $items as $k => $it ) : ?>
				<div class="reup" data-reup>
					<span class="small"><strong><?php echo esc_html( $it['name'] ?? '' ); ?></strong></span>
					<label class="drop drop--file"><input type="file" multiple data-reup-input><?php echo ap_icon( 'upload', 18 ); // phpcs:ignore ?><span><strong>Enviar arquivo corrigido</strong></span></label>
					<div class="upfiles" data-upfiles></div>
					<input type="hidden" name="f[<?php echo (int) $k; ?>]" value="[]" data-reup-files>
				</div>
			<?php endforeach; ?>
			<?php ap_input( 'msg', 'Recado (opcional)', '', 'textarea', 'rows="2"' ); ?>
			<div class="form-actions"><button type="submit" class="btn btn--primary">Enviar para conferência</button></div>
		</form>
	</section>
<?php elseif ( 'aprovada' === $p->art_status ) : ?>
	<div class="flash flash--ok">Arte conferida e aprovada pela <?php echo esc_html( ap_setting( 'empresa' ) ); ?>.</div>
<?php endif; ?>

<section class="card">
	<ol class="csteps">
		<?php foreach ( $keys as $i => $k ) : ?>
			<li class="<?php echo false !== $pos && $i < $pos ? 'is-done' : ( $i === $pos ? 'is-now' : '' ); ?>"><span><?php echo $i < (int) $pos ? '✓' : (int) ( $i + 1 ); ?></span><?php echo esc_html( $cols[ $k ] ); ?></li>
		<?php endforeach; ?>
	</ol>
	<?php if ( $p->status === ap_ready_column() ) : ?>
		<div class="flash flash--ok"><?php echo 'envio' === $p->delivery_mode ? 'Seu pedido está pronto e vai ser despachado.' . ( $p->tracking ? ' Rastreio: ' . esc_html( $p->tracking ) : '' ) : esc_html( 'Seu pedido está pronto! ' . ap_setting( 'retirada_texto' ) ); ?></div>
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
					<?php foreach ( $items as $it ) : ?><div class="items-row"><span><strong><?php echo (int) ( $it['qty'] ?? 1 ); ?>× <?php echo esc_html( $it['name'] ?? '' ); ?></strong><?php if ( ! empty( $it['desc'] ) ) : ?><small><?php echo esc_html( $it['desc'] ); ?></small><?php endif; ?><?php foreach ( (array) ( $it['files'] ?? array() ) as $fl ) : ?><small>📎 <?php echo esc_html( $fl['name'] ); ?></small><?php endforeach; ?></span><span class="money"><?php echo esc_html( ap_money( (float) ( $it['unit'] ?? 0 ) * (int) ( $it['qty'] ?? 1 ) ) ); ?></span></div><?php endforeach; ?>
				</div>
			</section>
		<?php endif; ?>
		<section class="card">
			<div class="card-head"><h3>Novidades</h3></div>
			<ul class="timeline">
				<?php foreach ( $log as $a ) : ?><li class="is-client"><span class="timeline-dot"></span><div><?php echo esc_html( $a->body ); ?><small><?php echo esc_html( ap_ago( $a->created_at ) ); ?></small></div></li><?php endforeach; ?>
			</ul>
			<?php ap_form( 'comment_add', 'stack' ); ?>
				<input type="hidden" name="project_id" value="<?php echo (int) $p->id; ?>">
				<?php ap_input( 'body', 'Mandar uma mensagem', '', 'textarea', 'rows="2" placeholder="Dúvida, ajuste, recado…"' ); ?>
				<div class="form-actions"><button type="submit" class="btn btn--primary btn--sm">Enviar</button></div>
			</form>
		</section>
	</div>
	<aside class="split-side">
		<section class="card">
			<div class="card-head"><h3>Pagamento</h3></div>
			<?php foreach ( $trans as $t ) : ?>
				<div class="pay-row"><span><?php echo esc_html( ap_money( $t->amount ) ); ?><small><?php echo esc_html( $t->method ); ?></small></span>
				<?php if ( 'pago' === $t->status ) : ?><em class="badge badge--ok">pago</em><?php else : ?><a class="btn btn--primary btn--sm" href="<?php echo esc_url( ap_pay_url( $t->id ) ); ?>">Pagar</a><?php endif; ?></div>
			<?php endforeach; ?>
		</section>
		<section class="card">
			<div class="card-head"><h3>Entrega</h3></div>
			<p class="small"><?php echo 'envio' === $p->delivery_mode ? 'Envio' . ( $p->ship_service ? ' · ' . esc_html( $p->ship_service ) : '' ) : esc_html( ap_setting( 'retirada_texto' ) ); ?></p>
		</section>
	</aside>
</div>
<?php
?>
<p class="small center"><a href="<?php echo esc_url( ap_client_link( 'mensagens', 0, array( 'pedido' => $p->id ) ) ); ?>">Dúvida sobre este pedido? Mande uma mensagem →</a></p>
<script src="<?php echo esc_url( AP_URL . 'assets/reupload.js?ver=' . AP_VERSION ); ?>"></script>
<?php
ap_client_end();

<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
$hist = ap_rows( 'broadcasts', "status <> 'rascunho'", array(), 'id DESC LIMIT 20' );
$all  = ap_audience_clients( 'todos' );
ap_panel_start( 'E-mails para clientes', 'emails' );
?>
<div class="split">
	<div class="split-main">
		<section class="card">
			<div class="card-head"><h3>Novo e-mail</h3><span class="muted small">sai com a sua logo, pelo SMTP configurado</span></div>
			<?php ap_form( 'broadcast_save', 'stack' ); ?>
				<?php ap_select( 'audience', 'Para quem', ap_audiences_mail(), 'aprovados', 'data-aud' ); ?>
				<div class="pick-clients" data-pick hidden>
					<input type="search" class="filter" placeholder="Filtrar…" data-filter=".pick-clients label">
					<?php foreach ( $all as $c ) : ?><label class="chk"><input type="checkbox" name="ids[]" value="<?php echo (int) $c->id; ?>"> <?php echo esc_html( ap_client_label( $c ) . ' · ' . $c->email ); ?></label><?php endforeach; ?>
				</div>
				<?php ap_input( 'subject', 'Assunto', '', 'text', 'required placeholder="Ex.: {nome}, chegou o adesivo perfurado premium"' ); ?>
				<?php ap_input( 'body', 'Texto', '', 'textarea', 'rows="9" required placeholder="Olá, {nome}! …"' ); ?>
				<p class="muted small">Use {nome} e {empresa} para personalizar. Cada e-mail tem o link para a pessoa se descadastrar (os avisos dos pedidos continuam chegando).</p>
				<div class="grid-2">
					<?php ap_input( 'cta_text', 'Texto do botão (opcional)', '', 'text', 'placeholder="Fazer um pedido"' ); ?>
					<?php ap_input( 'cta_url', 'Link do botão', ap_client_url( 'novo' ), 'url' ); ?>
				</div>
				<div class="form-actions">
					<button type="submit" name="teste" value="1" class="btn btn--ghost" formnovalidate>Enviar teste para mim</button>
					<button type="submit" class="btn btn--primary" data-confirm="Enviar este e-mail para o público escolhido?">Enviar</button>
				</div>
			</form>
		</section>
	</div>
	<aside class="split-side">
		<section class="card">
			<div class="card-head"><h3>Enviados</h3></div>
			<?php if ( ! $hist ) : ?><p class="muted small">Nenhum envio ainda.</p><?php endif; ?>
			<div class="items-list">
				<?php foreach ( $hist as $b ) : ?>
					<div class="items-row"><span><strong><?php echo esc_html( $b->subject ); ?></strong><small><?php echo esc_html( ap_audiences_mail()[ $b->audience ] ?? '' ); ?> · <?php echo esc_html( ap_date( $b->created_at ) ); ?></small></span><span class="small"><?php echo (int) $b->sent; ?>/<?php echo (int) $b->total; ?><?php echo 'enviando' === $b->status ? ' ⏳' : ''; ?></span></div>
				<?php endforeach; ?>
			</div>
		</section>
	</aside>
</div>
<script>document.querySelector('[data-aud]').addEventListener('change',function(){document.querySelector('[data-pick]').hidden=this.value!=='selecao';});</script>
<?php
ap_panel_end();

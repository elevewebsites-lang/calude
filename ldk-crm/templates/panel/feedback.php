<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
$ver   = isset( $_GET['ver'] ) ? sanitize_key( $_GET['ver'] ) : 'aberto'; // phpcs:ignore WordPress.Security.NonceVerification
$ver   = in_array( $ver, array( 'aberto', 'resolvido', 'todos' ), true ) ? $ver : 'aberto';
$where = "path = 'feedback'" . ( 'todos' === $ver ? '' : " AND status = '" . $ver . "'" );
if ( ! lk_is_admin() ) {
	$where .= ' AND user_id = ' . (int) get_current_user_id();
}
$rows  = lk_rows( 'feedback', $where, array(), 'id DESC LIMIT 200' );
$kinds = lk_feedback_kinds();
lk_panel_start( 'Feedback', 'feedback' );
?>
<p class="muted hint">Aconteceu algo estranho, teve uma ideia ou uma dúvida no dia a dia? Registre aqui. <?php echo lk_is_admin() ? 'Você vê o de todo mundo e pode responder.' : 'A administração responde e você é avisado.'; ?></p>
<section class="card">
	<?php lk_form( 'fb_new', 'stack' ); ?>
		<div class="seg seg--radio">
			<?php foreach ( $kinds as $k => $l ) : ?><label><input type="radio" name="tipo" value="<?php echo esc_attr( $k ); ?>"<?php checked( 'problema', $k ); ?>><span><?php echo esc_html( $l ); ?></span></label><?php endforeach; ?>
		</div>
		<?php lk_input( 'body', 'O que aconteceu? (quanto mais detalhe, melhor)', '', 'textarea', 'rows="4" required placeholder="Ex.: ao salvar o post da Clínica Bella, a data voltou para ontem"' ); ?>
		<?php lk_input( 'tela', 'Link da tela (opcional)', '', 'url', 'placeholder="Cole o endereço da página, se ajudar"' ); ?>
		<div class="form-actions"><button type="submit" class="btn btn--primary">Registrar feedback</button></div>
	</form>
</section>
<nav class="tabs">
	<?php foreach ( array( 'aberto' => 'Abertos', 'resolvido' => 'Resolvidos', 'todos' => 'Todos' ) as $k => $l ) : ?><a class="<?php echo $k === $ver ? 'is-active' : ''; ?>" href="<?php echo esc_url( lk_panel_url( 'feedback', 0, array( 'ver' => $k ) ) ); ?>"><?php echo esc_html( $l ); ?></a><?php endforeach; ?>
</nav>
<?php if ( ! $rows ) : ?>
	<section class="card"><p class="muted">Nada por aqui.</p></section>
<?php endif; ?>
<div class="fb-list">
	<?php foreach ( $rows as $f ) : ?>
		<?php $u = get_userdata( $f->user_id ); ?>
		<article class="card fb-item" id="fb-<?php echo (int) $f->id; ?>">
			<div class="fb-head"><strong><?php echo esc_html( $kinds[ $f->page ] ?? '📝 Feedback' ); ?></strong><span class="muted small"><?php echo esc_html( ( $u ? $u->display_name : '—' ) . ' · ' . lk_ago( $f->created_at ) ); ?></span><em class="badge <?php echo 'resolvido' === $f->status ? 'badge--ok' : 'badge--wait'; ?>"><?php echo esc_html( $f->status ); ?></em></div>
			<p><?php echo nl2br( esc_html( $f->body ) ); ?></p>
			<?php if ( $f->url ) : ?><p class="small"><a href="<?php echo esc_url( $f->url ); ?>">abrir a tela</a></p><?php endif; ?>
			<?php if ( $f->reply ) : ?><p class="fb-reply"><strong>Resposta:</strong> <?php echo nl2br( esc_html( $f->reply ) ); ?></p><?php endif; ?>
			<?php if ( lk_is_admin() ) : ?>
				<?php lk_form( 'fb_answer', 'fb-answer' ); ?>
					<input type="hidden" name="id" value="<?php echo (int) $f->id; ?>">
					<textarea name="reply" rows="2" placeholder="Responder…"><?php echo esc_textarea( $f->reply ); ?></textarea>
					<div class="row-btns"><button type="submit" name="status" value="aberto" class="btn btn--ghost btn--sm">Responder</button><button type="submit" name="status" value="resolvido" class="btn btn--primary btn--sm">Responder e resolver</button></div>
				</form>
			<?php endif; ?>
		</article>
	<?php endforeach; ?>
</div>
<?php
lk_panel_end();

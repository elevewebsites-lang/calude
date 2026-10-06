<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
$filtro = isset( $_GET['ver'] ) ? sanitize_key( $_GET['ver'] ) : 'aberto'; // phpcs:ignore WordPress.Security.NonceVerification
$filtro = in_array( $filtro, array( 'aberto', 'resolvido', 'todos' ), true ) ? $filtro : 'aberto';
$rows   = 'todos' === $filtro ? ap_rows( 'feedback', '1=1', array(), 'id DESC' ) : ap_rows( 'feedback', 'status = %s', array( $filtro ), 'id DESC' );
$counts = array(
	'aberto'    => count( ap_rows( 'feedback', "status = 'aberto'" ) ),
	'resolvido' => count( ap_rows( 'feedback', "status = 'resolvido'" ) ),
);
$counts['todos'] = $counts['aberto'] + $counts['resolvido'];
$labels = array( 'aberto' => 'Abertos', 'resolvido' => 'Resolvidos', 'todos' => 'Todos' );
$acoes  = $rows ? '<button type="button" class="btn btn--ghost btn--sm" data-fbl-copy>' . ap_icon( 'copiar', 15 ) . '<span>Copiar lista em texto</span></button>' : '';
ap_panel_start( 'Apontamentos', 'apontamentos', $acoes );
?>
<p class="muted hint">
	<?php if ( '1' === (string) ap_setting( 'apontamentos' ) ) : ?>
		Em qualquer tela, o botão <strong>Apontar</strong> (canto de baixo, à esquerda) marca um ponto e guarda o comentário aqui. Clique em <em>Abrir no ponto</em> para ver exatamente onde foi.
	<?php else : ?>
		O botão Apontar está <strong>desligado</strong>. Ligue em <a href="<?php echo esc_url( ap_panel_url( 'config' ) ); ?>#apontamentos">Configurações → Apontamentos</a>.
	<?php endif; ?>
</p>
<nav class="fbl-tabs">
	<?php foreach ( $labels as $k => $l ) : ?>
		<a class="<?php echo $k === $filtro ? 'is-on' : ''; ?>" href="<?php echo esc_url( ap_panel_url( 'apontamentos', 0, array( 'ver' => $k ) ) ); ?>"><?php echo esc_html( $l . ' (' . $counts[ $k ] . ')' ); ?></a>
	<?php endforeach; ?>
</nav>

<?php if ( ! $rows ) : ?>
	<section class="card"><p class="muted"><?php echo 'aberto' === $filtro ? 'Nenhum apontamento aberto. Tudo em dia!' : 'Nada por aqui ainda.'; ?></p></section>
<?php endif; ?>

<div class="fbl">
	<?php foreach ( $rows as $f ) : ?>
		<?php $done = 'resolvido' === $f->status; ?>
		<article class="card fbl-item<?php echo $done ? ' is-done' : ''; ?>" id="ap-<?php echo (int) $f->id; ?>">
			<span class="fbl-n" aria-hidden="true"><span><?php echo $done ? '✓' : (int) $f->id; ?></span></span>
			<div>
				<div class="fbl-meta">
					<strong>#<?php echo (int) $f->id; ?></strong>
					<span class="pill pill--<?php echo $done ? 'ok' : 'warn'; ?>"><?php echo $done ? 'Resolvido' : 'Aberto'; ?></span>
					<span><?php echo esc_html( ap_feedback_author( $f ) ); ?></span>
					<span>· <?php echo esc_html( ap_ago( $f->created_at ) ); ?></span>
					<span>· <?php echo esc_html( (int) $f->vw < 768 ? 'no celular' : ( (int) $f->vw < 1100 ? 'no tablet' : 'no computador' ) ); ?></span>
				</div>
				<p class="fbl-body"><?php echo esc_html( $f->body ); ?></p>
				<p class="fbl-where">
					<?php echo esc_html( $f->page ? $f->page : $f->path ); ?>
					<?php if ( $f->snippet ) : ?> · em “<?php echo esc_html( mb_substr( $f->snippet, 0, 80 ) ); ?>”<?php endif; ?>
					· <a href="<?php echo esc_url( ap_feedback_link( $f ) ); ?>" target="_blank" rel="noopener"><strong>Abrir no ponto →</strong></a>
				</p>
				<?php ap_form( 'feedback_save', 'fbl-form' ); ?>
					<input type="hidden" name="id" value="<?php echo (int) $f->id; ?>">
					<textarea name="reply" rows="1" placeholder="Resposta para quem apontou (opcional)"><?php echo esc_textarea( $f->reply ); ?></textarea>
					<?php if ( $done ) : ?>
						<button type="submit" name="status" value="aberto" class="btn btn--ghost btn--sm">Reabrir</button>
						<button type="submit" class="btn btn--ghost btn--sm">Salvar resposta</button>
					<?php else : ?>
						<button type="submit" class="btn btn--ghost btn--sm">Salvar resposta</button>
						<button type="submit" name="status" value="resolvido" class="btn btn--primary btn--sm"><?php echo ap_icon( 'check', 14 ); // phpcs:ignore ?><span>Resolvido</span></button>
					<?php endif; ?>
				</form>
				<?php if ( ap_feedback_mine( $f ) ) : ?>
					<div style="margin-top:6px"><?php ap_action_button( 'feedback_delete', array( 'id' => $f->id ), 'Excluir', 'link', 'Excluir o apontamento #' . (int) $f->id . '?' ); ?></div>
				<?php endif; ?>
			</div>
		</article>
	<?php endforeach; ?>
</div>

<?php if ( $counts['aberto'] && ap_is_admin() ) : ?>
	<p style="margin-top:18px"><?php ap_action_button( 'feedback_resolve_all', array(), 'Marcar todos os abertos como resolvidos', 'btn btn--ghost btn--sm', 'Marcar os ' . (int) $counts['aberto'] . ' apontamentos abertos como resolvidos?' ); ?></p>
<?php endif; ?>

<?php if ( $rows ) : ?>
	<textarea hidden data-fbl-text><?php echo esc_textarea( ap_feedback_text( $rows ) ); ?></textarea>
	<script>
	document.addEventListener('click', function (e) {
		var b = e.target.closest('[data-fbl-copy]'); if (!b) return;
		var txt = document.querySelector('[data-fbl-text]').value, span = b.querySelector('span');
		var done = function () { span.textContent = 'Copiado ✓'; setTimeout(function () { span.textContent = 'Copiar lista em texto'; }, 2000); };
		if (navigator.clipboard) navigator.clipboard.writeText(txt).then(done, function () { prompt('Copie:', txt); });
		else prompt('Copie:', txt);
	});
	</script>
<?php endif; ?>
<?php
ap_panel_end();

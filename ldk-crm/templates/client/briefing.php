<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
lk_client_start( 'Briefing', $client );
?>
<?php $lk_forms = lk_rows( 'form_sends', 'client_id = %d', array( $client->id ), 'id DESC' ); if ( $lk_forms ) : ?>
<section class="card" id="formularios"><div class="card-head"><h3>Formulários e pesquisas para você</h3></div><ul class="mini-list"><?php foreach ( $lk_forms as $f ) : ?><li><a href="<?php echo esc_url( lk_form_url( $f ) ); ?>"><strong><?php echo esc_html( $f->title ); ?></strong><small><?php echo esc_html( lk_form_kinds()[ $f->kind ] . ' · ' . lk_form_status_label( $f->status ) ); ?></small></a><em class="badge <?php echo 'respondido' === $f->status ? 'badge--ok' : 'badge--warn'; ?>"><?php echo 'respondido' === $f->status ? 'Respondido ✓' : 'Responder'; ?></em></li><?php endforeach; ?></ul></section>
<?php endif; ?>
<section class="chello"><span class="eyebrow">Briefing</span><h1>Sobre a sua marca</h1><p class="muted">Quanto mais a gente souber, mais certeiros ficam os conteúdos. Dá para editar quando quiser.<?php echo $client->briefing_at ? ' Última atualização: ' . esc_html( lk_date( $client->briefing_at, 'd/m/Y' ) ) . '.' : ''; ?></p></section>
<?php if ( $client->briefing_at ) : ?>
<section class="card card--accent ctr-saved"><div><strong>✓ Suas respostas estão salvas aqui na sua área</strong><br><span class="muted small">Última atualização em <?php echo esc_html( lk_date( $client->briefing_at, 'd/m/Y H:i' ) ); ?>. Você pode editar quando quiser.</span></div><button type="button" class="btn btn--ghost btn--sm" onclick="window.print()">Imprimir / salvar em PDF</button></section>
<?php endif; ?>
<section class="card">
	<?php lk_form( 'briefing_save', 'stack brf-form' ); ?>
		<input type="hidden" name="id" value="<?php echo (int) $client->id; ?>">
		<?php lk_briefing_fields( $client ); ?>
		<div class="form-actions"><button type="submit" class="btn btn--primary">Salvar respostas</button></div>
	</form>
</section>
<?php
lk_client_end();

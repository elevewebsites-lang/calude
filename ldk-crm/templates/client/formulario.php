<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
$s = lk_get( 'form_sends', $id );
if ( ! $s || (int) $s->client_id !== (int) $client->id ) {
	wp_safe_redirect( lk_client_link( 'briefing' ) );
	exit;
}
$schema = lk_form_clean_schema( $s->qschema );
lk_client_start( $s->title, $client );
$done = 'respondido' === $s->status;
$sat  = lk_form_sat_options();
$face = array( '😍', '🙂', '😐', '🙁', '😞' );
?>
<section class="chello"><span class="eyebrow"><?php echo esc_html( lk_form_kinds()[ $s->kind ] ?? 'Formulário' ); ?></span><h1><?php echo esc_html( $s->title ); ?></h1><?php if ( $s->intro ) : ?><p class="muted"><?php echo esc_html( $s->intro ); ?></p><?php endif; ?></section>
<?php if ( $done ) : ?>
	<section class="card card--accent"><strong>✓ Obrigado! Você já respondeu em <?php echo esc_html( lk_date( $s->answered_at, 'd/m/Y' ) ); ?>.</strong><p class="muted small">As suas respostas ficam salvas aqui.</p></section>
	<section class="card fm-answers"><?php echo lk_form_answers_html( $s, false ); // phpcs:ignore WordPress.Security.EscapeOutput ?></section>
<?php elseif ( lk_is_team() ) : ?>
	<div class="flash flash--warn">Você está vendo como o cliente: as respostas não são enviadas neste modo.</div>
<?php endif; ?>
<?php if ( ! $done ) : $total = count( $schema['steps'] ); ?>
<noscript><style>[data-fs-step],[data-fs-send]{display:block!important}[data-fs-next],[data-fs-prev]{display:none!important}</style></noscript>
<?php lk_form( 'form_answer', 'fs-form' ); ?>
	<input type="hidden" name="id" value="<?php echo (int) $s->id; ?>">
	<div class="fs-progress" data-fs-progress><span data-fs-label>Etapa 1 de <?php echo (int) $total; ?></span><i><b data-fs-bar style="width:<?php echo (int) round( 100 / max( 1, $total ) ); ?>%"></b></i></div>
	<?php foreach ( $schema['steps'] as $si => $st ) : ?>
		<section class="card fs-step" data-fs-step="<?php echo (int) $si; ?>" <?php echo $si ? 'hidden' : ''; ?>>
			<h2 class="fs-title"><?php echo esc_html( $st['title'] ); ?></h2>
			<?php foreach ( $st['questions'] as $q ) : $qid = $q['id']; ?>
				<fieldset class="fs-q" data-q="<?php echo esc_attr( $qid ); ?>" data-req="<?php echo $q['required'] ? 1 : 0; ?>" data-type="<?php echo esc_attr( $q['type'] ); ?>">
					<legend><?php echo esc_html( $q['label'] ); ?><?php echo $q['required'] ? ' <b class="fs-req" title="obrigatória">*</b>' : ' <small class="muted">(opcional)</small>'; ?></legend>
					<?php if ( 'short' === $q['type'] ) : ?>
						<input type="text" name="a[<?php echo esc_attr( $qid ); ?>]" maxlength="300">
					<?php elseif ( 'long' === $q['type'] ) : ?>
						<textarea name="a[<?php echo esc_attr( $qid ); ?>]" rows="4" maxlength="3000"></textarea>
					<?php elseif ( in_array( $q['type'], array( 'choice', 'multi' ), true ) ) : $multi = 'multi' === $q['type']; ?>
						<div class="fs-opts">
							<?php foreach ( $q['options'] as $o ) : ?><label class="fs-opt"><input type="<?php echo $multi ? 'checkbox' : 'radio'; ?>" name="a[<?php echo esc_attr( $qid ); ?>]<?php echo $multi ? '[]' : ''; ?>" value="<?php echo esc_attr( $o ); ?>"><span><?php echo esc_html( $o ); ?></span></label><?php endforeach; ?>
							<?php if ( $q['other'] ) : ?><label class="fs-opt fs-opt--other"><input type="<?php echo $multi ? 'checkbox' : 'radio'; ?>" name="a[<?php echo esc_attr( $qid ); ?>]<?php echo $multi ? '[]' : ''; ?>" value="__outro"><span>Outro:</span><input type="text" name="a_other[<?php echo esc_attr( $qid ); ?>]" maxlength="200" placeholder="Escreva aqui"></label><?php endif; ?>
						</div>
					<?php elseif ( 'sat' === $q['type'] ) : ?>
						<div class="fs-opts fs-opts--sat"><?php foreach ( $sat as $i => $o ) : ?><label class="fs-opt fs-opt--sat"><input type="radio" name="a[<?php echo esc_attr( $qid ); ?>]" value="<?php echo esc_attr( $o ); ?>"><span><em><?php echo esc_html( $face[ $i ] ); ?></em><?php echo esc_html( $o ); ?></span></label><?php endforeach; ?></div>
					<?php elseif ( 'scale' === $q['type'] || 'nps' === $q['type'] ) : $from = 'nps' === $q['type'] ? 0 : 1; $to = 'nps' === $q['type'] ? 10 : 5; ?>
						<div class="fs-opts fs-opts--num"><?php for ( $n = $from; $n <= $to; $n++ ) : ?><label class="fs-opt fs-opt--num"><input type="radio" name="a[<?php echo esc_attr( $qid ); ?>]" value="<?php echo (int) $n; ?>"><span><?php echo (int) $n; ?></span></label><?php endfor; ?></div>
						<div class="fs-scale-l muted small"><span><?php echo 'nps' === $q['type'] ? 'Nada provável' : 'Muito ruim'; ?></span><span><?php echo 'nps' === $q['type'] ? 'Muito provável' : 'Excelente'; ?></span></div>
					<?php elseif ( 'yesno' === $q['type'] ) : ?>
						<div class="fs-opts"><?php foreach ( array( 'Sim', 'Não' ) as $o ) : ?><label class="fs-opt"><input type="radio" name="a[<?php echo esc_attr( $qid ); ?>]" value="<?php echo esc_attr( $o ); ?>"><span><?php echo esc_html( $o ); ?></span></label><?php endforeach; ?></div>
					<?php endif; ?>
				</fieldset>
			<?php endforeach; ?>
		</section>
	<?php endforeach; ?>
	<p class="flash flash--erro" data-fs-err hidden></p>
	<div class="fs-nav"><button type="button" class="btn btn--ghost" data-fs-prev hidden>← Voltar</button><button type="button" class="btn btn--primary" data-fs-next>Próxima etapa →</button><button type="submit" class="btn btn--primary" data-fs-send <?php echo $total > 1 ? 'hidden' : ''; ?><?php echo lk_is_team() ? ' disabled' : ''; ?>>Enviar respostas ✓</button></div>
</form>
<script>window.LK_FS=<?php echo wp_json_encode( array( 'key' => 'lkfs' . (int) $s->id, 'total' => $total ) ); ?>;</script>
<script src="<?php echo esc_url( LK_URL . 'assets/forms.js?ver=' . LK_VERSION ); ?>"></script>
<?php endif; ?>
<?php
lk_client_end();

<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
$ideas  = lk_rows( 'ideas', "status <> 'publicado'", array(), "FIELD(status,'agendado','produzir','ideia'), post_date IS NULL, post_date, id DESC" );
$done   = lk_rows( 'ideas', "status = 'publicado'", array(), 'id DESC LIMIT 12' );
$fmts   = lk_idea_formats();
$sts    = lk_idea_statuses();
$ai     = lk_ai_ready();
ob_start();
lk_form( 'idea_generate', 'inline-form' );
echo '<input type="hidden" name="ia" value="' . ( $ai ? 1 : 0 ) . '"><button type="submit" class="btn btn--ghost">' . lk_icon( 'lampada', 16 ) . '<span>' . ( $ai ? 'Gerar ideias com IA' : 'Sugerir ideias' ) . '</span></button></form>';
echo '<button type="button" class="btn btn--primary" data-open="nova-ideia">' . lk_icon( 'mais', 16 ) . '<span>Ideia</span></button>';
lk_panel_start( 'Marketing', 'marketing', ob_get_clean() );

$form = function ( $i = null ) use ( $fmts, $sts ) {
	lk_form( 'idea_save', 'stack' );
	?>
		<input type="hidden" name="id" value="<?php echo (int) ( $i ? $i->id : 0 ); ?>">
		<?php lk_input( 'title', 'Título / gancho', $i ? $i->title : '', 'text', 'required' ); ?>
		<?php lk_input( 'body', 'Roteiro / legenda', $i ? $i->body : '', 'textarea', 'rows="5"' ); ?>
		<div class="grid-3">
			<?php lk_select( 'format', 'Formato', $fmts, $i ? $i->format : 'reels' ); ?>
			<?php lk_select( 'status', 'Status', $sts, $i ? $i->status : 'ideia' ); ?>
			<?php lk_input( 'post_date', 'Data de postar', $i ? $i->post_date : '', 'date' ); ?>
		</div>
		<div class="form-actions">
			<?php if ( $i ) : ?><?php lk_action_button( 'idea_delete', array( 'id' => $i->id ), 'Excluir', 'btn btn--link btn--sm', 'Excluir esta ideia?' ); ?><?php endif; ?>
			<button type="button" class="btn btn--ghost" data-close>Cancelar</button><button type="submit" class="btn btn--primary">Salvar</button>
		</div>
	</form>
	<?php
};
?>
<p class="muted small hint">Ideias de conteúdo para o Instagram a partir dos seus pedidos e produtos: timelapse da impressão, antes e depois do acabamento, bastidores, entrega para o cliente. <?php echo $ai ? '' : 'Com a chave da IA em Configurações, as sugestões ficam personalizadas.'; ?></p>

<?php if ( ! $ideas ) : ?>
	<div class="empty"><?php echo lk_icon( 'megafone', 26 ); // phpcs:ignore ?><h3>Nenhuma ideia na fila</h3><p>Clique em "Sugerir ideias" para começar.</p></div>
<?php else : ?>
	<div class="idea-grid">
		<?php foreach ( $ideas as $i ) : ?>
			<article class="idea idea--<?php echo esc_attr( $i->status ); ?>">
				<div class="idea-head"><em class="badge"><?php echo esc_html( $fmts[ $i->format ] ?? $i->format ); ?></em><em class="badge badge--st-<?php echo esc_attr( $i->status ); ?>"><?php echo esc_html( $sts[ $i->status ] ?? '' ); ?></em><?php if ( $i->post_date ) : ?><span class="muted small"><?php echo esc_html( lk_date( $i->post_date, 'd/m' ) ); ?></span><?php endif; ?></div>
				<h4><?php echo esc_html( $i->title ); ?></h4>
				<p class="muted small"><?php echo esc_html( wp_trim_words( $i->body, 32 ) ); ?></p>
				<div class="row-btns">
					<button type="button" class="btn btn--ghost btn--sm" data-open="idea-<?php echo (int) $i->id; ?>">Abrir</button>
					<button type="button" class="btn btn--ghost btn--sm" data-copy="<?php echo esc_attr( $i->title . "\n\n" . $i->body ); ?>">Copiar</button>
				</div>
			</article>
			<?php lk_modal_start( 'idea-' . $i->id, 'Ideia' ); ?><?php $form( $i ); ?><?php lk_modal_end(); ?>
		<?php endforeach; ?>
	</div>
<?php endif; ?>

<?php if ( $done ) : ?>
	<section class="card"><div class="card-head"><h3>Publicados</h3></div>
		<div class="items-list"><?php foreach ( $done as $i ) : ?><div class="items-row"><span><?php echo esc_html( $i->title ); ?></span><span class="muted small"><?php echo esc_html( $fmts[ $i->format ] ?? '' ); ?></span></div><?php endforeach; ?></div>
	</section>
<?php endif; ?>
<?php lk_modal_start( 'nova-ideia', 'Nova ideia' ); ?><?php $form(); ?><?php lk_modal_end(); ?>
<?php
lk_panel_end();

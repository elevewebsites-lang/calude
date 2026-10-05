<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
lk_client_start( 'Briefing', $client );
?>
<section class="chello"><span class="eyebrow">Briefing</span><h1>Sobre a sua marca</h1><p class="muted">Quanto mais a gente souber, mais certeiros ficam os conteúdos. Dá para editar quando quiser.<?php echo $client->briefing_at ? ' Última atualização: ' . esc_html( lk_date( $client->briefing_at, 'd/m/Y' ) ) . '.' : ''; ?></p></section>
<section class="card">
	<?php lk_form( 'briefing_save', 'stack brf-form' ); ?>
		<input type="hidden" name="id" value="<?php echo (int) $client->id; ?>">
		<?php lk_briefing_fields( $client ); ?>
		<div class="form-actions"><button type="submit" class="btn btn--primary">Salvar respostas</button></div>
	</form>
</section>
<?php
lk_client_end();

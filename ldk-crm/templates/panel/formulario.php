<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
// $id = modelo salvo em edição. ?envio=ID edita um envio ainda não respondido. ?modelo=ID|default:x parte de um modelo. ?novo=briefing|pesquisa começa do zero.
$tpl     = null;
$send    = null;
$kind    = isset( $_GET['novo'] ) && 'pesquisa' === $_GET['novo'] ? 'pesquisa' : 'briefing'; // phpcs:ignore WordPress.Security.NonceVerification
$client  = isset( $_GET['cliente'] ) ? absint( $_GET['cliente'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification
$title   = '';
$intro   = '';
$schema  = array( 'steps' => array( array( 'title' => 'Etapa 1', 'questions' => array( array( 'type' => 'short', 'label' => '', 'options' => array(), 'required' => true, 'other' => false ) ) ) ) );
if ( $id ) {
	$t = lk_get( 'forms', $id );
	if ( ! $t ) {
		lk_render( 'panel/404' );
	}
	$tpl    = $t;
	$kind   = $t->kind;
	$title  = $t->title;
	$intro  = (string) $t->intro;
	$schema = lk_form_clean_schema( $t->qschema );
} elseif ( isset( $_GET['envio'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification
	$send = lk_get( 'form_sends', absint( $_GET['envio'] ) ); // phpcs:ignore WordPress.Security.NonceVerification
	if ( ! $send || 'pendente' !== $send->status ) {
		lk_render( 'panel/404' );
	}
	$kind   = $send->kind;
	$title  = $send->title;
	$intro  = (string) $send->intro;
	$client = (int) $send->client_id;
	$schema = lk_form_clean_schema( $send->qschema );
} elseif ( ! empty( $_GET['modelo'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification
	$m = lk_form_template( sanitize_text_field( wp_unslash( $_GET['modelo'] ) ) ); // phpcs:ignore WordPress.Security.NonceVerification
	if ( $m ) {
		$kind   = $m['kind'];
		$title  = $m['title'];
		$intro  = $m['intro'];
		$schema = $m['schema'];
	}
}
if ( ! $title ) {
	$title = 'pesquisa' === $kind ? 'Pesquisa de satisfação' : 'Briefing personalizado';
}
lk_panel_start( $tpl ? 'Editar modelo' : ( $send ? 'Editar envio' : ( 'pesquisa' === $kind ? 'Montar pesquisa' : 'Montar briefing' ) ), 'formularios' );
?>
<a class="back" href="<?php echo esc_url( $client && ! $tpl ? lk_panel_url( 'cliente', $client ) . '#formularios' : lk_panel_url( 'formularios' ) ); ?>"><?php echo lk_icon( 'voltar', 16 ); // phpcs:ignore ?> Voltar</a>
<?php lk_form( 'form_save', 'stack fb-form' ); ?>
	<input type="hidden" name="template_id" value="<?php echo (int) ( $tpl ? $tpl->id : 0 ); ?>">
	<input type="hidden" name="send_id" value="<?php echo (int) ( $send ? $send->id : 0 ); ?>">
	<input type="hidden" name="schema" value="" data-fb-out>
	<section class="card">
		<div class="grid-2">
			<?php lk_select( 'kind', 'Tipo', lk_form_kinds(), $kind ); ?>
			<?php lk_input( 'title', 'Título do formulário', $title, 'text', 'required' ); ?>
		</div>
		<?php lk_input( 'intro', 'Texto de boas-vindas (o cliente vê no começo e no e-mail)', $intro, 'textarea', 'rows="2" placeholder="Ex.: Vamos alinhar os detalhes da campanha de Dia das Mães."' ); ?>
	</section>
	<div data-fb></div>
	<script type="application/json" data-fb-init><?php echo wp_json_encode( $schema, JSON_HEX_TAG | JSON_HEX_AMP ); // phpcs:ignore WordPress.Security.EscapeOutput ?></script>
	<script type="application/json" data-fb-types><?php echo wp_json_encode( lk_form_types(), JSON_HEX_TAG | JSON_HEX_AMP ); // phpcs:ignore WordPress.Security.EscapeOutput ?></script>
	<section class="card fb-send">
		<h3>Enviar ou salvar</h3>
		<div class="grid-2">
			<?php lk_select( 'client_id', 'Enviar para o cliente', lk_client_options( '— escolher depois / só salvar modelo —' ), $client ); ?>
			<label class="check fb-model"><input type="checkbox" name="salvar_modelo" value="1" <?php checked( (bool) $tpl ); ?>><span>Salvar também como <strong>modelo</strong> para usar de novo</span></label>
		</div>
		<div class="form-actions">
			<button type="submit" name="acao" value="modelo" class="btn btn--ghost">Só salvar o modelo</button>
			<button type="submit" name="acao" value="rascunho" class="btn btn--ghost">Guardar para o cliente (sem avisar)</button>
			<button type="submit" name="acao" value="enviar" class="btn btn--primary">Enviar para o cliente</button>
		</div>
		<p class="muted small">Ao enviar, o cliente recebe um e-mail com o link e vê o formulário na área dele, uma etapa por vez. As respostas ficam salvas na ficha e no Drive.</p>
	</section>
</form>
<script src="<?php echo esc_url( LK_URL . 'assets/forms.js?ver=' . LK_VERSION ); ?>"></script>
<?php
lk_panel_end();

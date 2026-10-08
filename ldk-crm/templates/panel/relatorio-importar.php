<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
$pre  = isset( $_GET['cliente'] ) ? absint( $_GET['cliente'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification
$base = LK_URL . 'assets/mlabs/';
lk_panel_start( 'Relatório do mLabs', 'relatorios', '<a class="btn btn--ghost" href="' . esc_url( lk_panel_url( 'relatorios' ) ) . '">Voltar aos relatórios</a>' );
?>
<section class="card" data-mlabs data-base="<?php echo esc_url( $base ); ?>">
	<div class="card-head"><h3>Suba o relatório do mLabs (PDF)</h3></div>
	<p class="muted">O sistema lê o PDF aqui no seu navegador, reconhece os indicadores, os funis, os gráficos e as tabelas dos posts (com as miniaturas) e monta o relatório no padrão da plataforma. O arquivo não sai do seu computador: só os dados lidos vêm para o CRM.</p>
	<?php lk_form( 'mlabs_save', 'stack' ); ?>
		<input type="hidden" name="payload" data-mlabs-payload>
		<input type="hidden" name="thumbs" data-mlabs-thumbs>
		<input type="hidden" name="period" data-mlabs-period>
		<?php lk_select( 'client_id', 'Cliente', lk_client_options( 'Escolha o cliente…' ), $pre ? (string) $pre : '', 'required' ); ?>
		<label class="field"><span>Relatório em PDF</span><input type="file" accept="application/pdf,.pdf" data-mlabs-file></label>
		<p data-mlabs-status class="small muted">Escolha o PDF para começar.</p>
		<div data-mlabs-preview></div>
		<div class="form-actions"><button type="submit" class="btn btn--primary" data-mlabs-submit disabled><?php echo lk_icon( 'check', 16 ); // phpcs:ignore ?><span>Criar o relatório</span></button></div>
	</form>
</section>
<script src="<?php echo esc_url( $base . 'pdf.min.js?ver=3.11.174' ); ?>"></script>
<script src="<?php echo esc_url( $base . 'pdf-prims.js?ver=' . LK_VERSION ); ?>"></script>
<script src="<?php echo esc_url( $base . 'mlabs-parse.js?ver=' . LK_VERSION ); ?>"></script>
<script src="<?php echo esc_url( $base . 'importar.js?ver=' . LK_VERSION ); ?>"></script>
<?php
lk_panel_end();

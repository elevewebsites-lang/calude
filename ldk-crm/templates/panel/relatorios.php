<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
$last = gmdate( 'Y-m', strtotime( 'first day of last month' ) );
lk_panel_start( 'Relatórios', 'relatorios', '<a class="btn btn--primary" href="' . esc_url( lk_panel_url( 'relatorio-importar' ) ) . '">' . lk_icon( 'upload', 16 ) . '<span>Subir relatório do mLabs</span></a>' );
?>
<?php if ( lk_manage_only() ) : ?>
<section class="card card--accent">
	<div class="card-head"><h3>Relatório a partir do mLabs</h3></div>
	<p class="muted">Sem vínculo com as redes, os números vêm do relatório que o mLabs envia. Suba o PDF e o sistema lê e monta o relatório no padrão da plataforma, pronto para você revisar, escrever a análise e mandar ao cliente.</p>
	<a class="btn btn--primary" href="<?php echo esc_url( lk_panel_url( 'relatorio-importar' ) ); ?>"><?php echo lk_icon( 'upload', 16 ); // phpcs:ignore ?><span>Subir relatório do mLabs</span></a>
</section>
<?php else : ?>
<section class="card">
	<div class="card-head"><h3>Gerar relatório</h3><span class="muted small">puxa os números do Instagram e os posts que mais engajaram</span></div>
	<?php lk_form( 'report_generate', 'inline-form report-gen' ); ?>
		<select name="client_id" required><option value="">Cliente…</option><?php foreach ( lk_clients() as $c ) : ?><option value="<?php echo (int) $c->id; ?>"><?php echo esc_html( lk_client_label( $c ) ); ?></option><?php endforeach; ?></select>
		<input type="month" name="period" value="<?php echo esc_attr( $last ); ?>">
		<button type="submit" class="btn btn--primary">Gerar</button>
	</form>
</section>
<?php endif; ?>
<div class="table">
	<div class="table-row table-head"><span>Cliente</span><span>Mês</span><span>Status</span><span></span></div>
	<?php foreach ( lk_rows( 'reports', '1=1', array(), 'period DESC, id DESC LIMIT 100' ) as $r ) : ?>
		<?php $c = lk_get( 'clients', $r->client_id ); ?>
		<a class="table-row" href="<?php echo esc_url( lk_panel_url( 'relatorio', $r->id ) ); ?>"><span class="cell-main"><strong><?php echo esc_html( lk_client_label( $c ) ); ?></strong><small><?php echo esc_html( lk_report_kind_label( $r ) ); ?></small></span><span><?php echo esc_html( lk_month_label( $r->period ) ); ?></span><span><em class="badge <?php echo 'publicado' === $r->status ? 'badge--ok' : ''; ?>"><?php echo esc_html( $r->sent_at ? 'enviado' : $r->status ); ?></em></span><span class="cell-arrow"><?php echo lk_icon( 'seta', 16 ); // phpcs:ignore ?></span></a>
	<?php endforeach; ?>
</div>
<?php
lk_panel_end();

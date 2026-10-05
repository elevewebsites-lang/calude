<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
$ym  = isset( $_GET['mes'] ) && preg_match( '/^\d{4}-\d{2}$/', $_GET['mes'] ) ? sanitize_text_field( $_GET['mes'] ) : current_time( 'Y-m' ); // phpcs:ignore
list( $f, $t ) = lk_month_range( $ym );
lk_panel_start( 'Tráfego pago', 'trafego' );
?>
<div class="toolbar"><form method="get" class="inline-form"><input type="month" name="mes" value="<?php echo esc_attr( $ym ); ?>" onchange="this.form.submit()"></form><span class="muted small">Meta Ads automático (token em Configurações). Google Ads: lance os números do mês até liberarem a API.</span></div>
<?php foreach ( lk_clients() as $c ) : ?>
	<?php if ( ! $c->meta_ad_account && ! $c->google_ads_id && ! $c->trafego_id ) { continue; } $a = lk_ads_period( $c->id, $f, $t ); $m = $a['meta']; $g = $a['google']; ?>
	<section class="card">
		<div class="card-head"><h3><?php echo esc_html( lk_client_label( $c ) ); ?></h3><em class="badge <?php echo $c->ads_visible ? 'badge--ok' : ''; ?>"><?php echo $c->ads_visible ? 'cliente vê' : 'só a equipe vê'; ?></em></div>
		<div class="stats stats--4">
			<div class="stat"><span class="stat-label">Meta · investido</span><strong class="money"><?php echo $m && ! isset( $m['error'] ) ? esc_html( lk_money( $m['spend'] ) ) : '—'; ?></strong><small><?php echo $m && isset( $m['error'] ) ? esc_html( $m['error'] ) : ( $m ? (int) $m['results'] . ' resultados · ' . (int) $m['clicks'] . ' cliques' : 'sem conta' ); ?></small></div>
			<div class="stat"><span class="stat-label">Meta · receita</span><strong class="money"><?php echo $m && ! empty( $m['revenue'] ) ? esc_html( lk_money( $m['revenue'] ) ) : '—'; ?></strong><small><?php echo $m && ! empty( $m['spend'] ) && ! empty( $m['revenue'] ) ? 'ROAS ' . esc_html( number_format( $m['revenue'] / $m['spend'], 1, ',', '' ) ) . 'x' : ''; ?></small></div>
			<div class="stat"><span class="stat-label">Google · investido</span><strong class="money"><?php echo $g ? esc_html( lk_money( $g['spend'] ) ) : '—'; ?></strong><small><?php echo $g ? (int) $g['results'] . ' resultados' : 'lançar abaixo'; ?></small></div>
			<div class="stat"><span class="stat-label">Google · receita</span><strong class="money"><?php echo $g && $g['revenue'] ? esc_html( lk_money( $g['revenue'] ) ) : '—'; ?></strong></div>
		</div>
		<?php if ( $m && ! empty( $m['campaigns'] ) ) : ?><div class="items-list"><?php foreach ( $m['campaigns'] as $cp ) : ?><div class="items-row"><span><?php echo esc_html( $cp['name'] ); ?></span><span class="small"><?php echo (int) $cp['results']; ?> result.</span><span class="money"><?php echo esc_html( lk_money( $cp['spend'] ) ); ?></span></div><?php endforeach; ?></div><?php endif; ?>
		<details><summary class="small">Contas e Google Ads do mês</summary>
			<div class="grid-2">
				<?php lk_form( 'ads_account', 'stack' ); ?><input type="hidden" name="client_id" value="<?php echo (int) $c->id; ?>"><?php lk_input( 'meta_ad_account', 'Conta de anúncios Meta (act_...)', $c->meta_ad_account ); ?><?php lk_input( 'google_ads_id', 'ID do Google Ads', $c->google_ads_id ); ?><?php lk_check( 'ads_visible', 'O cliente pode ver o tráfego', (bool) $c->ads_visible ); ?><button class="btn btn--ghost btn--sm">Salvar</button></form>
				<?php lk_form( 'ads_google_manual', 'stack' ); ?><input type="hidden" name="client_id" value="<?php echo (int) $c->id; ?>"><input type="hidden" name="period" value="<?php echo esc_attr( $ym ); ?>"><div class="grid-2"><?php lk_input( 'spend', 'Investido (R$)', $g['spend'] ?? '', 'text', 'data-money' ); ?><?php lk_input( 'revenue', 'Receita (R$)', $g['revenue'] ?? '', 'text', 'data-money' ); ?><?php lk_input( 'clicks', 'Cliques', $g['clicks'] ?? '', 'number' ); ?><?php lk_input( 'results', 'Resultados', $g['results'] ?? '', 'number' ); ?></div><button class="btn btn--ghost btn--sm">Salvar Google Ads</button></form>
			</div>
		</details>
	</section>
<?php endforeach; ?>
<p class="muted small hint">Clientes aparecem aqui quando têm conta de anúncios ou gestor de tráfego definido na ficha.</p>
<?php
lk_panel_end();

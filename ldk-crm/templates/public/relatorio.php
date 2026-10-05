<?php
/**
 * Relatório mensal público: /relatorio/<token>/ (telas horizontais, como a proposta da LDK).
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
$c    = lk_get( 'clients', $r->client_id );
$d    = lk_json( $r->data );
$ig   = $d['ig'] ?? null;
$cur  = $ig['cur'] ?? array();
$old  = $ig['old'] ?? array();
$ads  = ( ! empty( $d['show_ads'] ) && $c && $c->ads_visible ) ? ( $d['ads'] ?? null ) : null;
$num  = function ( $v ) {
	return number_format( (float) $v, 0, ',', '.' );
};
$var  = function ( $k ) use ( $cur, $old ) {
	$v = lk_var( $cur[ $k ] ?? 0, $old[ $k ] ?? 0 );
	return null === $v ? '' : '<em class="rv ' . ( $v >= 0 ? 'up' : 'down' ) . '">' . ( $v >= 0 ? '▲ ' : '▼ ' ) . abs( $v ) . '%</em>';
};
$kpis = array( 'reach' => 'Contas alcançadas', 'views' => 'Visualizações', 'total_interactions' => 'Interações', 'profile_views' => 'Visitas ao perfil', 'website_clicks' => 'Cliques no link', 'accounts_engaged' => 'Contas engajadas' );
$fol  = $ig['followers'] ?? null;
$fo   = $ig['followers_old'] ?? null;
lk_head( 'Relatório ' . lk_month_label( $r->period ) . ' · ' . lk_client_label( $c ) );
?>
<body class="lk rep">
<div class="rep-track" data-track>
	<section class="rep-s rep-cover">
		<?php if ( lk_setting( 'logo' ) ) : ?><img class="rep-logo" src="<?php echo esc_url( lk_setting( 'logo' ) ); ?>" alt=""><?php endif; ?>
		<span class="rep-eye">Relatório mensal</span>
		<h1><?php echo esc_html( lk_client_label( $c ) ); ?></h1>
		<p class="rep-big"><?php echo esc_html( ucfirst( lk_month_label( $r->period ) ) ); ?></p>
		<?php if ( ! empty( $ig['username'] ) ) : ?><p class="rep-sub">@<?php echo esc_html( $ig['username'] ); ?></p><?php endif; ?>
		<span class="rep-go">Deslize →</span>
	</section>
	<?php if ( $ig ) : ?>
		<section class="rep-s">
			<span class="rep-eye">Resultados do mês</span>
			<h2>Como o Instagram foi</h2>
			<div class="rep-kpis">
				<?php if ( null !== $fol ) : ?><div class="rep-kpi"><strong><?php echo esc_html( $num( $fol ) ); ?></strong><span>Seguidores</span><?php echo null !== $fo ? '<em class="rv ' . ( $fol - $fo >= 0 ? 'up' : 'down' ) . '">' . ( $fol - $fo >= 0 ? '+' : '' ) . esc_html( $num( $fol - $fo ) ) . ' no mês</em>' : ''; ?></div><?php endif; ?>
				<?php foreach ( $kpis as $k => $lab ) : ?><?php if ( isset( $cur[ $k ] ) ) : ?><div class="rep-kpi"><strong><?php echo esc_html( $num( $cur[ $k ] ) ); ?></strong><span><?php echo esc_html( $lab ); ?></span><?php echo $var( $k ); // phpcs:ignore ?></div><?php endif; ?><?php endforeach; ?>
			</div>
			<p class="rep-note">Comparação com <?php echo esc_html( lk_month_label( gmdate( 'Y-m', strtotime( $r->period . '-01 -1 month' ) ) ) ); ?>. <?php echo (int) ( $d['produced'] ?? 0 ); ?> conteúdos produzidos no mês.</p>
		</section>
		<?php $series = $ig['series'] ?? array(); ?>
		<?php if ( count( $series ) >= 2 || ! empty( $d['totals'] ) ) : ?>
			<section class="rep-s">
				<span class="rep-eye">Evolução</span>
				<h2>Seguidores e engajamento no mês</h2>
				<?php if ( count( $series ) >= 2 ) : ?>
					<?php
					$vals = array_values( $series );
					$mn   = min( $vals );
					$mx   = max( $vals );
					$rng  = max( 1, $mx - $mn );
					$w    = 1000;
					$h    = 260;
					$pts  = array();
					$i    = 0;
					$cnt  = count( $vals ) - 1;
					foreach ( $vals as $v ) {
						$pts[] = round( $i / max( 1, $cnt ) * $w, 1 ) . ',' . round( $h - 20 - ( $v - $mn ) / $rng * ( $h - 60 ), 1 );
						$i++;
					}
					$gain = end( $vals ) - reset( $vals );
					?>
					<div class="rep-chart">
						<div class="rep-chart-head"><strong><?php echo esc_html( ( $gain >= 0 ? '+' : '' ) . $num( $gain ) ); ?></strong><span>seguidores em <?php echo count( $vals ); ?> dias (de <?php echo esc_html( $num( reset( $vals ) ) ); ?> para <?php echo esc_html( $num( end( $vals ) ) ); ?>)</span></div>
						<svg viewBox="0 0 <?php echo (int) $w; ?> <?php echo (int) $h; ?>" preserveAspectRatio="none" role="img" aria-label="Seguidores ao longo do mês">
							<defs><linearGradient id="rg" x1="0" x2="0" y1="0" y2="1"><stop offset="0" stop-color="#14E9EC" stop-opacity=".35"/><stop offset="1" stop-color="#14E9EC" stop-opacity="0"/></linearGradient></defs>
							<polygon fill="url(#rg)" points="0,<?php echo (int) $h; ?> <?php echo esc_attr( implode( ' ', $pts ) ); ?> <?php echo (int) $w; ?>,<?php echo (int) $h; ?>"/>
							<polyline fill="none" stroke="#14E9EC" stroke-width="3" vector-effect="non-scaling-stroke" points="<?php echo esc_attr( implode( ' ', $pts ) ); ?>"/>
						</svg>
						<div class="rep-chart-x"><span><?php echo esc_html( lk_date( array_key_first( $series ), 'd/m' ) ); ?></span><span><?php echo esc_html( lk_date( array_key_last( $series ), 'd/m' ) ); ?></span></div>
					</div>
				<?php endif; ?>
				<?php if ( ! empty( $d['totals'] ) ) : ?>
					<div class="rep-kpis rep-kpis--sm">
						<div class="rep-kpi"><strong>❤ <?php echo esc_html( $num( $d['totals']['likes'] ) ); ?></strong><span>Curtidas</span></div>
						<div class="rep-kpi"><strong>💬 <?php echo esc_html( $num( $d['totals']['comments'] ) ); ?></strong><span>Comentários</span></div>
						<div class="rep-kpi"><strong>🔖 <?php echo esc_html( $num( $d['totals']['saves'] ) ); ?></strong><span>Salvamentos</span></div>
						<div class="rep-kpi"><strong>↗ <?php echo esc_html( $num( $d['totals']['shares'] ) ); ?></strong><span>Compartilhamentos</span></div>
					</div>
				<?php endif; ?>
			</section>
		<?php endif; ?>
		<?php if ( ! empty( $d['posts'] ) ) : ?>
			<section class="rep-s">
				<span class="rep-eye">Destaques</span>
				<h2>Os posts que mais engajaram</h2>
				<div class="rep-posts">
					<?php foreach ( array_slice( $d['posts'], 0, 6 ) as $i => $p ) : ?>
						<a class="rep-post" href="<?php echo esc_url( $p['url'] ); ?>" target="_blank" rel="noopener">
							<span class="rep-rank"><?php echo (int) ( $i + 1 ); ?></span>
							<?php if ( $p['img'] ) : ?><img src="<?php echo esc_url( $p['img'] ); ?>" alt="" loading="lazy"><?php endif; ?>
							<span class="rep-pm"><b>❤ <?php echo esc_html( $num( $p['likes'] ) ); ?></b><b>💬 <?php echo esc_html( $num( $p['comments'] ) ); ?></b><b>🔖 <?php echo esc_html( $num( $p['saves'] ) ); ?></b><b>👁 <?php echo esc_html( $num( $p['reach'] ) ); ?></b></span>
						</a>
					<?php endforeach; ?>
				</div>
			</section>
		<?php endif; ?>
	<?php endif; ?>
	<?php if ( ! empty( $d['all'] ) && count( $d['all'] ) > 1 ) : ?>
		<section class="rep-s">
			<span class="rep-eye">Todos os posts</span>
			<h2>Post a post (<?php echo count( $d['all'] ); ?>)</h2>
			<div class="rep-table">
				<div class="rep-tr rep-th"><span>Post</span><span>❤</span><span>💬</span><span>🔖</span><span>↗</span><span>Alcance</span><span>Visualiz.</span></div>
				<?php foreach ( $d['all'] as $p ) : ?>
					<a class="rep-tr" href="<?php echo esc_url( $p['url'] ); ?>" target="_blank" rel="noopener">
						<span class="rep-tp"><?php if ( $p['img'] ) : ?><img src="<?php echo esc_url( $p['img'] ); ?>" alt="" loading="lazy"><?php endif; ?><span><b><?php echo esc_html( lk_date( $p['date'], 'd/m' ) ); ?></b> <?php echo esc_html( wp_trim_words( $p['caption'], 8 ) ); ?></span></span>
						<span><?php echo esc_html( $num( $p['likes'] ) ); ?></span><span><?php echo esc_html( $num( $p['comments'] ) ); ?></span><span><?php echo esc_html( $num( $p['saves'] ) ); ?></span><span><?php echo esc_html( $num( $p['shares'] ) ); ?></span><span><?php echo esc_html( $num( $p['reach'] ) ); ?></span><span><?php echo esc_html( $num( $p['views'] ) ); ?></span>
					</a>
				<?php endforeach; ?>
			</div>
		</section>
	<?php endif; ?>
	<?php if ( $ads && ( ! empty( $ads['meta'] ) || ! empty( $ads['google'] ) ) ) : ?>
		<section class="rep-s">
			<span class="rep-eye">Tráfego pago</span>
			<h2>Investimento e resultados</h2>
			<div class="rep-kpis">
				<?php foreach ( array( 'meta' => 'Meta Ads', 'google' => 'Google Ads' ) as $k => $lab ) : ?>
					<?php $a = $ads[ $k ] ?? null; if ( ! $a || isset( $a['error'] ) ) { continue; } ?>
					<div class="rep-kpi"><strong><?php echo esc_html( lk_money( $a['spend'] ?? 0 ) ); ?></strong><span><?php echo esc_html( $lab ); ?> · investido</span></div>
					<div class="rep-kpi"><strong><?php echo esc_html( $num( $a['results'] ?? 0 ) ); ?></strong><span>Resultados (contatos/vendas)</span></div>
					<?php if ( ! empty( $a['revenue'] ) ) : ?><div class="rep-kpi"><strong><?php echo esc_html( lk_money( $a['revenue'] ) ); ?></strong><span>Receita gerada</span><?php echo ! empty( $a['spend'] ) ? '<em class="rv up">ROAS ' . esc_html( number_format( $a['revenue'] / $a['spend'], 1, ',', '' ) ) . 'x</em>' : ''; ?></div><?php endif; ?>
				<?php endforeach; ?>
			</div>
			<?php $wl = $ads['meta']['wallet'] ?? null; ?>
			<?php if ( $wl ) : ?>
				<div class="rep-wallet">
					<strong>Carteira de anúncios (Meta)</strong>
					<?php if ( $wl['funding'] ) : ?><span><?php echo esc_html( $wl['funding'] ); ?></span><?php endif; ?>
					<?php if ( null !== $wl['left_cap'] ) : ?><span>Disponível até o limite: <b><?php echo esc_html( lk_money( $wl['left_cap'] ) ); ?></b></span><?php endif; ?>
					<?php if ( $wl['balance'] > 0 ) : ?><span>Saldo a pagar: <b><?php echo esc_html( lk_money( $wl['balance'] ) ); ?></b></span><?php endif; ?>
				</div>
			<?php endif; ?>
			<?php if ( ! empty( $ads['meta']['campaigns'] ) ) : ?>
				<div class="rep-table rep-table--camp">
					<div class="rep-tr rep-th"><span>Campanha</span><span>Investido</span><span>Cliques</span><span>Resultados</span></div>
					<?php foreach ( $ads['meta']['campaigns'] as $cp ) : ?>
						<div class="rep-tr"><span><?php echo esc_html( $cp['name'] ); ?></span><span><?php echo esc_html( lk_money( $cp['spend'] ) ); ?></span><span><?php echo esc_html( $num( $cp['clicks'] ) ); ?></span><span><?php echo esc_html( $num( $cp['results'] ) ); ?></span></div>
					<?php endforeach; ?>
				</div>
			<?php endif; ?>
		</section>
	<?php endif; ?>
	<?php if ( $r->notes ) : ?>
		<section class="rep-s">
			<span class="rep-eye">Análise</span>
			<h2>O que aprendemos e próximos passos</h2>
			<div class="rep-text"><?php echo wpautop( esc_html( $r->notes ) ); // phpcs:ignore ?></div>
		</section>
	<?php endif; ?>
	<section class="rep-s rep-end">
		<h2>Vamos juntos para o próximo mês 🚀</h2>
		<a class="rep-btn" href="<?php echo esc_url( lk_wa_link( lk_setting( 'whatsapp' ), 'Olá! Vi o relatório de ' . lk_month_label( $r->period ) . ' e queria conversar.' ) ); ?>" target="_blank" rel="noopener">Falar com a <?php echo esc_html( lk_setting( 'empresa' ) ); ?></a>
		<div class="rep-credit"><?php echo lk_credit_html( 'dark' ); // phpcs:ignore ?></div>
	</section>
</div>
<div class="rep-nav"><button type="button" data-prev>‹</button><button type="button" data-next>›</button></div>
<script>
(function(){var t=document.querySelector('[data-track]');var go=function(d){t.scrollBy({left:d*t.clientWidth,behavior:'smooth'});};document.querySelector('[data-prev]').onclick=function(){go(-1)};document.querySelector('[data-next]').onclick=function(){go(1)};document.addEventListener('keydown',function(e){if(e.key==='ArrowRight')go(1);if(e.key==='ArrowLeft')go(-1);});})();
</script>
<style>
body.rep{margin:0;background:#05080B;color:#eef2f5;overflow:hidden;font-family:var(--font)}
.rep-track{display:flex;height:100vh;overflow-x:auto;scroll-snap-type:x mandatory;scrollbar-width:none}
.rep-track::-webkit-scrollbar{display:none}
.rep-s{flex:0 0 100vw;height:100vh;scroll-snap-align:start;box-sizing:border-box;padding:7vh 7vw;display:flex;flex-direction:column;justify-content:center;gap:14px;overflow-y:auto;position:relative}
.rep-cover{background:radial-gradient(circle at 80% 20%,rgba(20,233,236,.25),transparent 55%),#05080B}
.rep-logo{height:46px;width:auto;position:absolute;top:6vh;left:7vw}
.rep-eye{color:#14E9EC;font-weight:700;letter-spacing:.14em;text-transform:uppercase;font-size:13px}
.rep h1{font-size:clamp(40px,7vw,96px);line-height:1;margin:0;font-weight:800}
.rep h2{font-size:clamp(28px,4vw,54px);margin:0 0 10px;font-weight:800}
.rep-big{font-size:clamp(22px,3vw,36px);margin:0;color:#9aa7b2}
.rep-sub{color:#14E9EC;margin:0}
.rep-go{position:absolute;bottom:6vh;left:7vw;color:#9aa7b2}
.rep-kpis{display:grid;grid-template-columns:repeat(auto-fill,minmax(220px,1fr));gap:14px}
.rep-kpi{background:rgba(255,255,255,.05);border:1px solid rgba(255,255,255,.08);border-radius:20px;padding:22px}
.rep-kpi strong{display:block;font-size:clamp(28px,3vw,42px);font-weight:800}
.rep-kpi span{color:#9aa7b2;font-size:14px}
.rv{display:inline-block;margin-top:8px;font-style:normal;font-weight:700;font-size:13px;padding:3px 10px;border-radius:999px}
.rv.up{background:rgba(20,233,236,.15);color:#14E9EC}.rv.down{background:rgba(255,99,99,.15);color:#ff8a8a}
.rep-note{color:#9aa7b2}
.rep-posts{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:14px}
.rep-post{position:relative;border-radius:18px;overflow:hidden;background:#0e141a;aspect-ratio:4/5;display:block}
.rep-post img{width:100%;height:100%;object-fit:cover}
.rep-rank{position:absolute;top:10px;left:10px;background:#14E9EC;color:#05080B;font-weight:800;width:30px;height:30px;border-radius:50%;display:grid;place-items:center}
.rep-pm{position:absolute;left:0;right:0;bottom:0;display:flex;gap:10px;flex-wrap:wrap;padding:10px;background:linear-gradient(transparent,rgba(0,0,0,.85));font-size:13px;color:#fff}
.rep-text{max-width:820px;font-size:18px;line-height:1.7;color:#d7dee4}
.rep-end{align-items:flex-start}
.rep-btn{background:#14E9EC;color:#05080B;padding:16px 28px;border-radius:999px;font-weight:800;text-decoration:none}
.rep-credit{position:absolute;bottom:5vh;left:7vw}
.rep-nav{position:fixed;right:24px;bottom:24px;display:flex;gap:8px}
.rep-nav button{width:46px;height:46px;border-radius:50%;border:1px solid rgba(255,255,255,.2);background:rgba(0,0,0,.4);color:#fff;font-size:22px;cursor:pointer}
.rep-chart{background:rgba(255,255,255,.04);border:1px solid rgba(255,255,255,.08);border-radius:20px;padding:20px}
.rep-chart svg{width:100%;height:220px;display:block}
.rep-chart-head strong{font-size:clamp(28px,3vw,40px);font-weight:800;color:#14E9EC;margin-right:10px}
.rep-chart-head span{color:#9aa7b2}
.rep-chart-x{display:flex;justify-content:space-between;color:#9aa7b2;font-size:12px;margin-top:6px}
.rep-kpis--sm .rep-kpi strong{font-size:26px}
.rep-table{background:rgba(255,255,255,.03);border:1px solid rgba(255,255,255,.08);border-radius:18px;overflow:auto;max-height:62vh}
.rep-tr{display:grid;grid-template-columns:minmax(220px,2.6fr) repeat(6,minmax(60px,.8fr));gap:10px;align-items:center;padding:10px 16px;border-top:1px solid rgba(255,255,255,.06);color:#e3eaef;text-decoration:none;font-size:14px}
.rep-tr:hover{background:rgba(20,233,236,.06)}
.rep-th{position:sticky;top:0;background:#0b1117;color:#9aa7b2;font-size:12px;text-transform:uppercase;letter-spacing:.08em;border-top:0}
.rep-tp{display:flex;align-items:center;gap:10px;min-width:0}
.rep-tp img{width:42px;height:52px;object-fit:cover;border-radius:8px;flex:none}
.rep-tp span{overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
.rep-table--camp .rep-tr{grid-template-columns:minmax(200px,2.4fr) repeat(3,minmax(80px,1fr))}
.rep-wallet{display:flex;flex-wrap:wrap;gap:8px 18px;align-items:center;margin-top:14px;padding:14px 18px;border-radius:16px;background:rgba(20,233,236,.08);border:1px solid rgba(20,233,236,.25)}
.rep-wallet strong{color:#14E9EC}
.rep-wallet span{color:#d7dee4}
@media(max-width:760px){.rep-posts{grid-template-columns:repeat(2,minmax(0,1fr))}.rep-s{padding:9vh 6vw}}
</style>
</body>
</html>

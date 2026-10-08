<?php
/**
 * Relatório mensal público feito a partir do PDF do mLabs: /relatorio/<token>/ (telas horizontais, padrão da plataforma).
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
$c      = lk_get( 'clients', $r->client_id );
$d      = lk_json( $r->data );
$d['sections'] = $d['sections'] ?? array();
$slides = lk_mlabs_slides( $r, $d );
$nets   = array_unique( wp_list_pluck( $d['sections'], 'network' ) );
$from   = $d['meta']['from'] ?? '';
$to     = $d['meta']['to'] ?? '';
lk_head( 'Relatório ' . lk_month_label( $r->period ) . ' · ' . lk_client_label( $c ) );
?>
<body class="lk rep">
<link rel="stylesheet" href="<?php echo esc_url( LK_URL . 'assets/relatorio-mlabs.css?ver=' . LK_VERSION ); ?>">
<div class="rep-track" data-track>
	<section class="rep-s rep-cover">
		<?php if ( lk_setting( 'logo' ) ) : ?><img class="rep-logo" src="<?php echo esc_url( lk_setting( 'logo' ) ); ?>" alt=""><?php endif; ?>
		<span class="rep-eye">Relatório mensal</span>
		<h1><?php echo esc_html( lk_client_label( $c ) ); ?></h1>
		<p class="rep-big"><?php echo esc_html( ucfirst( lk_month_label( $r->period ) ) ); ?></p>
		<?php if ( $from && $to ) : ?><p class="rep-sub">Período: <?php echo esc_html( lk_date( $from ) . ' a ' . lk_date( $to ) ); ?></p><?php endif; ?>
		<span class="rep-go">Deslize →</span>
	</section>
	<?php foreach ( $slides as $sl ) { echo $sl; } // phpcs:ignore WordPress.Security.EscapeOutput -- montado com esc_* em lk_mlabs_slides ?>
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
</body>
</html>

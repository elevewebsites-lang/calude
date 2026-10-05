<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
update_user_meta( get_current_user_id(), 'lk_seen_version', LK_VERSION );
lk_panel_start( 'Novidades', 'novidades' );
?>
<p class="muted hint">O que mudou no sistema a cada atualização, em poucas palavras.</p>
<div class="news">
	<?php foreach ( lk_changelog() as $ver => $cl ) : ?>
		<section class="card news-ver<?php echo LK_VERSION === $ver ? ' is-current' : ''; ?>">
			<div class="news-head"><span class="news-tag">v<?php echo esc_html( $ver ); ?></span><strong><?php echo esc_html( $cl['title'] ); ?></strong><span class="muted small"><?php echo esc_html( lk_date( $cl['date'] ) ); ?></span><?php echo LK_VERSION === $ver ? '<em class="badge badge--ok">versão atual</em>' : ''; ?></div>
			<ol class="news-items">
				<?php foreach ( $cl['items'] as $it ) : ?><li><span class="news-ic" aria-hidden="true"><?php echo esc_html( $it[0] ); ?></span><span><strong><?php echo esc_html( $it[1] ); ?></strong><small><?php echo esc_html( $it[2] ); ?></small></span></li><?php endforeach; ?>
			</ol>
		</section>
	<?php endforeach; ?>
</div>
<?php
lk_panel_end();

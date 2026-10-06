<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
$ldk_single = is_singular( 'post' );
?><!doctype html>
<html <?php language_attributes(); ?>>
<head>
<meta charset="<?php bloginfo( 'charset' ); ?>">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="theme-color" content="#05080B">
<script>document.documentElement.className+=' ldk-js'</script>
<?php wp_head(); ?>
</head>
<?php $ldk_bare = is_page() && get_post_meta( get_the_ID(), '_ldk_site_bare', true ); ?>
<body <?php body_class( 'ldk-site' . ( $ldk_bare ? ' ldk-bare' : '' ) ); ?>>
<?php wp_body_open(); ?>
<?php echo ldk_site_intro_html(); // phpcs:ignore ?>
<?php echo ldk_site_header_html(); // phpcs:ignore ?>
<main id="ldk-main" class="ldk-main">
<?php
if ( $ldk_single ) {
	while ( have_posts() ) {
		the_post();
		$img = get_the_post_thumbnail_url( null, 'full' );
		$cat = get_the_category();
		?>
<article class="ldk-article">
	<div class="ldk" data-tone="dark"><section class="ldk-sec ldk-ph"><div class="ldk-wrap narrow">
		<a class="ldk-back" href="<?php echo esc_url( ldk_site_page_url( 'blog' ) ); ?>">← Blog</a>
		<span class="ldk-eyebrow"><?php echo esc_html( ( $cat ? $cat[0]->name . ' · ' : '' ) . get_the_date( 'd/m/Y' ) ); ?></span>
		<h1 class="ldk-h1 sm"><?php the_title(); ?></h1>
		<p class="ldk-lead"><?php echo esc_html( get_the_excerpt() ); ?></p>
	</div></section></div>
	<div class="ldk" data-tone="light"><div class="ldk-wrap narrow ldk-artbody">
		<div class="ldk-cover"><?php echo ldk_site_imgtag( $img ? $img : ldk_site_post_fallback( get_the_ID() ), get_the_title() ); // phpcs:ignore ?></div>
		<div class="ldk-prose"><?php the_content(); ?></div>
		<div class="ldk-share"><span>Compartilhe</span>
			<a href="<?php echo esc_url( 'https://wa.me/?text=' . rawurlencode( get_the_title() . ' ' . get_permalink() ) ); ?>" target="_blank" rel="noopener">WhatsApp</a>
			<a href="<?php echo esc_url( 'https://www.linkedin.com/sharing/share-offsite/?url=' . rawurlencode( get_permalink() ) ); ?>" target="_blank" rel="noopener">LinkedIn</a>
		</div>
	</div></div>
	<?php echo ldk_site_render( 'cta', array( 'title' => 'Gostou? Vamos aplicar isso na *sua marca*' ) ); // phpcs:ignore ?>
</article>
		<?php
	}
} elseif ( is_home() || is_category() || is_tag() || is_search() ) {
	echo ldk_site_render( 'pagehead', array( 'eyebrow' => 'Blog', 'title' => 'Conteúdo para quem quer *crescer de verdade*' ) ); // phpcs:ignore
	echo ldk_site_render( 'posts', array( 'count' => 12 ) ); // phpcs:ignore
} elseif ( is_404() ) {
	echo ldk_site_render( 'pagehead', array( 'eyebrow' => '404', 'title' => 'Página *não encontrada*', 'text' => 'O endereço pode ter mudado. Volte ao início e continue por lá.' ) ); // phpcs:ignore
} else {
	while ( have_posts() ) {
		the_post();
		the_content();
	}
}
?>
</main>
<?php echo ldk_site_footer_html(); // phpcs:ignore ?>
<?php wp_footer(); ?>
</body>
</html>

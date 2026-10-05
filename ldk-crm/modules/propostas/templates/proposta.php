<?php
/**
 * Página da proposta (sem o tema, para ficar fiel ao layout).
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$v   = ldkp_view_model( get_queried_object_id() );
$num = 1;
$sec = function () use ( &$num ) {
	return str_pad( $num++, 2, '0', STR_PAD_LEFT );
};
$top = function ( $n, $label ) {
	echo '<div class="section-top-bar"><span class="section-label"><b>#</b> ' . esc_html( $label ) . '</span><span class="section-number">(' . esc_html( $n ) . ')</span></div>';
};
// Destaca um trecho (por padrão, o nome do cliente) com a caixa ciano da LDK.
$hl = function ( $text, $needle = null ) use ( $v ) {
	$safe = esc_html( $text );
	$name = esc_html( null === $needle ? $v['short'] : $needle );
	if ( '' === $name || false === strpos( $safe, $name ) ) {
		return $safe;
	}
	return preg_replace( '/' . preg_quote( $name, '/' ) . '/', '<mark class="hl">' . $name . '</mark>', $safe, 1 );
};
$arrow = '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M7 17 17 7M8 7h9v9"/></svg>';
$wa    = '<svg width="18" height="18" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M17.47 14.38c-.3-.15-1.76-.87-2.03-.97-.27-.1-.47-.15-.67.15-.2.3-.77.97-.94 1.17-.17.2-.35.22-.64.07-.3-.15-1.26-.46-2.4-1.48-.89-.79-1.49-1.77-1.66-2.07-.17-.3-.02-.46.13-.61.13-.13.3-.35.45-.52.15-.17.2-.3.3-.5.1-.2.05-.37-.02-.52-.08-.15-.67-1.62-.92-2.22-.24-.58-.49-.5-.67-.51h-.57c-.2 0-.52.07-.79.37-.27.3-1.04 1.02-1.04 2.48s1.07 2.88 1.21 3.08c.15.2 2.1 3.2 5.08 4.49.71.31 1.26.49 1.69.63.71.23 1.36.2 1.87.12.57-.09 1.76-.72 2.01-1.41.25-.7.25-1.29.17-1.41-.07-.13-.27-.2-.57-.35M12.05 21.5h-.01a9.5 9.5 0 0 1-4.84-1.33l-.35-.21-3.6.94.96-3.5-.23-.36a9.46 9.46 0 0 1-1.45-5.05c0-5.24 4.27-9.5 9.52-9.5 2.54 0 4.93.99 6.72 2.79a9.43 9.43 0 0 1 2.78 6.72c0 5.24-4.27 9.5-9.5 9.5m8.09-17.59A11.36 11.36 0 0 0 12.05.55C5.74.55.6 5.68.6 11.99c0 2.02.53 3.99 1.53 5.72L.5 23.65l6.08-1.6a11.4 11.4 0 0 0 5.46 1.39h.01c6.3 0 11.44-5.13 11.44-11.44 0-3.06-1.19-5.93-3.35-8.09"/></svg>';
$title = 'Proposta comercial · ' . $v['client'];
?><!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
<meta name="robots" content="noindex, nofollow">
<title><?php echo esc_html( $title . ' | LDK Marketing Digital' ); ?></title>
<meta property="og:title" content="<?php echo esc_attr( $title ); ?>">
<meta property="og:description" content="<?php echo esc_attr( wp_trim_words( $v['capa_desc'], 30 ) ); ?>">
<?php if ( $v['cover'] ) : ?>
<meta property="og:image" content="<?php echo esc_url( $v['cover'] ); ?>">
<?php endif; ?>
<meta name="theme-color" content="#010a13">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="<?php echo esc_url( LDKP_URL . 'assets/proposta.css?ver=' . LDKP_VERSION ); ?>">
</head>
<body class="on-dark">

<div class="progress-bar"><div class="progress-fill" id="progressFill"></div></div>
<div class="section-counter"><span id="currentSection">01</span><span class="counter-separator">/</span><span id="totalSections">09</span></div>
<div class="scroll-hint" id="scrollHint">Role <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M5 12h14M13 5l7 7-7 7"/></svg></div>

<main class="horizontal-scroll" id="horizontalScroll">

	<?php /* 01 · CAPA */ $sec(); ?>
	<section class="panel panel-cover" data-theme="dark">
		<div class="cover-bg" aria-hidden="true">
			<?php if ( $v['cover'] ) : ?><img class="cover-bg-img" src="<?php echo esc_url( $v['cover'] ); ?>" alt=""><?php endif; ?>
			<span class="cover-glow"></span>
		</div>
		<div class="cover-main">
			<div class="cover-content">
				<?php if ( $v['logo'] ) : ?>
					<div class="cover-logo"><img class="cover-logo-img" src="<?php echo esc_url( $v['logo'] ); ?>" alt="LDK Marketing Digital"></div>
				<?php endif; ?>
				<span class="tag-hash cover-tag"><b>#</b> Proposta comercial</span>
				<h1 class="cover-client"><?php echo esc_html( $v['client'] ); ?><i>.</i></h1>
				<?php if ( $v['capa_desc'] ) : ?>
					<p class="cover-desc"><?php echo esc_html( $v['capa_desc'] ); ?></p>
				<?php endif; ?>
				<div class="cover-meta">
					<?php if ( $v['data'] ) : ?>
						<div class="cover-meta-item"><span class="cover-meta-label">Data</span><span class="cover-meta-value"><?php echo esc_html( $v['data'] ); ?></span></div>
					<?php endif; ?>
					<?php if ( $v['validade'] ) : ?>
						<div class="cover-meta-item"><span class="cover-meta-label">Validade</span><span class="cover-meta-value">Até <?php echo esc_html( $v['validade'] ); ?></span></div>
					<?php endif; ?>
					<div class="cover-meta-item"><span class="cover-meta-label">Responsável</span><span class="cover-meta-value"><?php echo esc_html( $v['responsavel'] ); ?></span></div>
				</div>
			</div>
		</div>
		<?php if ( $v['stats'] ) : ?>
			<div class="cover-band">
				<div class="cover-band-inner">
					<span class="cover-band-title">Fazemos história</span>
					<div class="cover-stats">
						<?php foreach ( array_slice( $v['stats'], 0, 3 ) as $st ) : ?>
							<div class="cover-stat">
								<span class="cover-stat-number"><?php echo esc_html( $st['antes'] ); ?><span data-count="<?php echo esc_attr( $st['numero'] ); ?>"><?php echo esc_html( $st['numero'] ); ?></span><?php echo esc_html( $st['depois'] ); ?></span>
								<span class="cover-stat-label"><?php echo esc_html( $st['legenda'] ); ?></span>
							</div>
						<?php endforeach; ?>
					</div>
				</div>
			</div>
		<?php endif; ?>
	</section>

	<?php /* 02 · INTRODUÇÃO */ $n = $sec(); ?>
	<section class="panel panel-navy" data-theme="dark">
		<div class="panel-inner">
			<?php $top( $n, 'Introdução' ); ?>
			<div class="statement">
				<p class="statement-text"><mark class="hl"><?php echo esc_html( $v['short'] ); ?>,</mark> <?php echo esc_html( $v['intro_frase'] ); ?></p>
			</div>
			<div class="about-grid">
				<div>
					<?php if ( $v['cenario'] ) : ?>
						<div class="about-item"><p class="about-item-title">(O cenário)</p><p class="about-item-desc"><?php echo esc_html( $v['cenario'] ); ?></p></div>
					<?php endif; ?>
					<?php if ( $v['oportunidade'] ) : ?>
						<div class="about-item"><p class="about-item-title">(A oportunidade)</p><p class="about-item-desc"><?php echo esc_html( $v['oportunidade'] ); ?></p></div>
					<?php endif; ?>
				</div>
				<?php if ( $v['diferenciais'] ) : ?>
					<div class="about-right">
						<p class="about-right-title">Por que a <b>LDK</b></p>
						<div class="diff-grid">
							<?php foreach ( $v['diferenciais'] as $row ) : ?>
								<div class="diff-card"><h3><?php echo esc_html( $row[0] ); ?></h3><p><?php echo esc_html( $row[1] ); ?></p></div>
							<?php endforeach; ?>
						</div>
					</div>
				<?php endif; ?>
			</div>
		</div>
	</section>

	<?php if ( $v['diagnostico'] ) : $n = $sec(); ?>
	<section class="panel panel-deep" data-theme="dark">
		<div class="panel-inner">
			<?php $top( $n, 'Diagnóstico' ); ?>
			<h2 class="heading-lg"><?php echo $hl( $v['diag_titulo'] ); // phpcs:ignore -- escapado em $hl. ?></h2>
			<div class="pain-list<?php echo count( $v['diagnostico'] ) > 6 ? ' pain-list--compact' : ''; ?>">
				<?php foreach ( $v['diagnostico'] as $i => $row ) : ?>
					<div class="pain-item">
						<span class="pain-number"><?php echo esc_html( str_pad( $i + 1, 2, '0', STR_PAD_LEFT ) ); ?></span>
						<div class="pain-content"><p class="pain-quote"><?php echo esc_html( $row[0] ); ?></p><?php if ( $row[1] ) : ?><p class="pain-answer"><?php echo esc_html( $row[1] ); ?></p><?php endif; ?></div>
					</div>
				<?php endforeach; ?>
			</div>
		</div>
	</section>
	<?php endif; ?>

	<?php $ag = $v['agencia']; if ( $ag['paragrafos'] ) : $n = $sec(); ?>
	<section class="panel panel-agency" data-theme="dark">
		<?php if ( $ag['img'] ) : ?><div class="agency-bg" aria-hidden="true"><img src="<?php echo esc_url( $ag['img'] ); ?>" alt="" loading="lazy"></div><?php endif; ?>
		<div class="panel-inner">
			<?php $top( $n, 'Quem somos' ); ?>
			<div class="agency-content">
				<h2 class="heading-lg"><?php echo $hl( $ag['titulo'], 'LDK Marketing Digital' ); // phpcs:ignore -- escapado em $hl. ?></h2>
				<div class="agency-text">
					<?php foreach ( $ag['paragrafos'] as $par ) : ?><p><?php echo esc_html( $par ); ?></p><?php endforeach; ?>
				</div>
				<?php if ( $ag['servicos'] ) : ?>
					<ul class="agency-services"><?php foreach ( $ag['servicos'] as $sv ) : ?><li><?php echo esc_html( $sv ); ?></li><?php endforeach; ?></ul>
				<?php endif; ?>
				<?php if ( $v['stats'] ) : ?>
					<div class="agency-stats">
						<?php foreach ( $v['stats'] as $st ) : ?>
							<div class="agency-stat"><strong><?php echo esc_html( $st['valor'] ); ?></strong><span><?php echo esc_html( $st['legenda'] ); ?></span></div>
						<?php endforeach; ?>
					</div>
				<?php endif; ?>
			</div>
		</div>
	</section>
	<?php endif; ?>

	<?php if ( $v['escopo'] ) : $n = $sec(); ?>
	<section class="panel panel-navy" data-theme="dark">
		<div class="panel-inner">
			<?php $top( $n, 'Escopo' ); ?>
			<h2 class="heading-lg">O que vamos <mark class="hl">entregar</mark>.</h2>
			<?php $n_esc = count( $v['escopo'] ); ?>
			<div class="services-list<?php echo $n_esc > 8 ? ' services-list--grid' : ( $n_esc > 6 ? ' services-list--compact' : '' ); ?>">
				<?php foreach ( $v['escopo'] as $i => $row ) : ?>
					<div class="service-row">
						<div class="service-row-left"><span class="service-num"><?php echo esc_html( str_pad( $i + 1, 2, '0', STR_PAD_LEFT ) ); ?></span><span class="service-name"><?php echo esc_html( $row[0] ); ?></span></div>
						<p class="service-desc"><?php echo esc_html( $row[1] ); ?></p>
					</div>
				<?php endforeach; ?>
			</div>
		</div>
	</section>
	<?php endif; ?>

	<?php if ( $v['portfolio'] || $v['perfis'] ) : $n = $sec(); ?>
	<section class="panel panel-deep" data-theme="dark">
		<div class="panel-inner panel-inner-wide">
			<?php $top( $n, 'Trabalhos' ); ?>
			<h2 class="heading-lg">Conheça alguns dos <mark class="hl">nossos trabalhos</mark></h2>
			<?php if ( $v['perfis'] ) : ?>
				<div class="profiles" aria-label="Perfis que cuidamos">
					<span class="profiles-label">Perfis que cuidamos</span>
					<div class="profiles-row">
						<?php foreach ( $v['perfis'] as $pf ) : ?>
							<a class="profile" href="<?php echo esc_url( $pf['link'] ); ?>" target="_blank" rel="noopener">
								<span class="profile-av"><span class="profile-ring"><?php echo esc_html( $pf['ini'] ); ?><?php if ( $pf['foto'] ) : ?><img src="<?php echo esc_url( $pf['foto'] ); ?>" alt="" loading="lazy" onerror="this.remove()"><?php endif; ?></span></span>
								<span class="profile-t"><strong><?php echo esc_html( $pf['nome'] ); ?></strong><small>@<?php echo esc_html( $pf['usuario'] ); ?><?php echo $pf['seguidores'] ? ' · ' . esc_html( $pf['seguidores'] ) . ' seguidores' : ''; ?></small></span>
								<span class="profile-go">Ver perfil <?php echo $arrow; // phpcs:ignore -- SVG fixo. ?></span>
							</a>
						<?php endforeach; ?>
					</div>
				</div>
			<?php endif; ?>
			<?php if ( $v['portfolio'] ) : ?>
			<div class="project-grid" style="--cols: <?php echo (int) count( $v['portfolio'] ); ?>">
				<?php foreach ( $v['portfolio'] as $item ) : ?>
					<?php $tag = $item['link'] ? 'a' : 'div'; ?>
					<<?php echo $tag; // phpcs:ignore -- 'a' ou 'div' fixos. ?> class="project-card"<?php echo $item['link'] ? ' href="' . esc_url( $item['link'] ) . '" target="_blank" rel="noopener"' : ''; ?>>
						<?php if ( $item['img'] ) : ?><img class="project-img" src="<?php echo esc_url( $item['img'] ); ?>" alt="<?php echo esc_attr( $item['nome'] ); ?>" loading="lazy"><?php endif; ?>
						<?php if ( $item['link'] ) : ?><span class="project-arrow"><?php echo $arrow; // phpcs:ignore -- SVG fixo. ?></span><?php endif; ?>
					</<?php echo $tag; // phpcs:ignore ?>>
				<?php endforeach; ?>
			</div>
			<?php endif; ?>
		</div>
	</section>
	<?php endif; ?>

	<?php if ( $v['clientes'] || $v['depoimentos'] ) : $n = $sec(); ?>
	<section class="panel panel-navy" data-theme="dark">
		<div class="panel-inner panel-inner-wide">
			<?php $top( $n, 'Clientes' ); ?>
			<div class="clients-head">
				<h2 class="heading-lg">Marcas que confiam no <mark class="hl">nosso trabalho</mark></h2>
				<?php if ( $v['clientes_texto'] ) : ?><p class="clients-sub"><?php echo esc_html( $v['clientes_texto'] ); ?></p><?php endif; ?>
			</div>
			<?php if ( $v['clientes'] ) : ?>
				<div class="clients-grid">
					<?php foreach ( $v['clientes'] as $c ) : ?>
						<div class="client-logo<?php echo $c['escuro'] ? ' client-logo--dark' : ''; ?>"><img src="<?php echo esc_url( $c['logo'] ); ?>" alt="<?php echo esc_attr( $c['nome'] ); ?>" title="<?php echo esc_attr( $c['nome'] ); ?>" loading="lazy"></div>
					<?php endforeach; ?>
				</div>
			<?php endif; ?>
			<?php if ( $v['depoimentos'] ) : ?>
				<div class="reviews" style="--cols: <?php echo (int) count( $v['depoimentos'] ); ?>">
					<?php foreach ( $v['depoimentos'] as $rv ) : ?>
						<figure class="review">
							<div class="review-stars" aria-label="5 estrelas"><svg width="14" height="14" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="m12 2 3.1 6.3 6.9 1-5 4.9 1.2 6.8L12 17.8 5.8 21l1.2-6.8-5-4.9 6.9-1z"/></svg><svg width="14" height="14" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="m12 2 3.1 6.3 6.9 1-5 4.9 1.2 6.8L12 17.8 5.8 21l1.2-6.8-5-4.9 6.9-1z"/></svg><svg width="14" height="14" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="m12 2 3.1 6.3 6.9 1-5 4.9 1.2 6.8L12 17.8 5.8 21l1.2-6.8-5-4.9 6.9-1z"/></svg><svg width="14" height="14" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="m12 2 3.1 6.3 6.9 1-5 4.9 1.2 6.8L12 17.8 5.8 21l1.2-6.8-5-4.9 6.9-1z"/></svg><svg width="14" height="14" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="m12 2 3.1 6.3 6.9 1-5 4.9 1.2 6.8L12 17.8 5.8 21l1.2-6.8-5-4.9 6.9-1z"/></svg></div>
							<blockquote><?php echo esc_html( $rv[1] ); ?></blockquote>
							<figcaption><strong><?php echo esc_html( $rv[0] ); ?></strong><span>Avaliação no Google</span></figcaption>
						</figure>
					<?php endforeach; ?>
				</div>
			<?php endif; ?>
		</div>
	</section>
	<?php endif; ?>

	<?php if ( $v['metodologia'] ) : $n = $sec(); ?>
	<section class="panel panel-deep" data-theme="dark">
		<div class="panel-inner">
			<?php $top( $n, 'Metodologia' ); ?>
			<h2 class="heading-lg">Como funciona o <mark class="hl">nosso trabalho</mark></h2>
			<div class="method-grid" style="--cols: <?php echo (int) min( 4, count( $v['metodologia'] ) ); ?>">
				<?php foreach ( $v['metodologia'] as $i => $row ) : ?>
					<div class="method-step">
						<div class="method-num"><?php echo esc_html( str_pad( $i + 1, 2, '0', STR_PAD_LEFT ) ); ?></div>
						<h3><?php echo esc_html( $row[0] ); ?></h3>
						<p><?php echo esc_html( $row[1] ); ?></p>
					</div>
				<?php endforeach; ?>
			</div>
		</div>
	</section>
	<?php endif; ?>

	<?php if ( $v['plans'] ) : $n = $sec(); $inv = $v['invest']; ?>
	<section class="panel panel-navy panel-pricing" data-theme="dark">
		<div class="panel-inner panel-inner-pricing">
			<?php $top( $n, 'Investimento' ); ?>
			<div class="invest-head">
				<div>
					<?php if ( $inv['badge'] ) : ?><span class="invest-badge"><?php echo esc_html( $inv['badge'] ); ?></span><?php endif; ?>
					<h2 class="invest-heading"><?php echo $hl( $inv['titulo'] ); // phpcs:ignore -- escapado em $hl. ?></h2>
					<?php if ( $inv['nota'] ) : ?><p class="invest-note"><?php echo esc_html( $inv['nota'] ); ?></p><?php endif; ?>
				</div>
				<?php if ( $inv['total'] > 0 ) : ?>
					<div class="vagas-tracker">
						<div class="vagas-blocks"><?php for ( $i = 0; $i < $inv['total']; $i++ ) : ?><span class="vaga<?php echo $i < $inv['filled'] ? ' filled' : ''; ?>"></span><?php endfor; ?></div>
						<span class="vagas-label"><strong><?php echo (int) $inv['filled']; ?> de <?php echo (int) $inv['total']; ?> vagas preenchidas</strong><?php echo $inv['agenda'] ? ' · ' . esc_html( $inv['agenda'] ) : ''; ?></span>
					</div>
				<?php endif; ?>
			</div>
			<div class="plans" style="--cols: <?php echo (int) count( $v['plans'] ); ?>">
				<?php foreach ( $v['plans'] as $p ) : ?>
					<div class="plan plan--<?php echo esc_attr( $p['estilo'] ); ?>">
						<?php if ( $p['tag'] ) : ?><span class="plan-tag"><?php echo esc_html( $p['tag'] ); ?></span><?php endif; ?>
						<div class="plan-name"><?php echo esc_html( $p['nome'] ); ?></div>
						<?php if ( $p['desc'] ) : ?><p class="plan-desc"><?php echo esc_html( $p['desc'] ); ?></p><?php endif; ?>
						<?php if ( $p['preco_de'] ) : ?><div class="plan-from">de <s>R$ <?php echo esc_html( $p['preco_de'] ); ?></s> por</div><?php endif; ?>
						<div class="plan-price"><?php if ( $p['prefixo'] ) : ?><span><?php echo esc_html( $p['prefixo'] ); ?></span><?php endif; ?><?php echo esc_html( $p['preco_por'] ); ?><?php if ( $p['pagamento'] ) : ?><small><?php echo esc_html( $p['pagamento'] ); ?></small><?php endif; ?></div>
						<?php if ( $p['itens'] ) : ?>
							<ul class="plan-list"><?php foreach ( $p['itens'] as $it ) : ?><li><?php echo esc_html( $it ); ?></li><?php endforeach; ?></ul>
						<?php endif; ?>
						<a class="plan-cta<?php echo 'destaque' === $p['estilo'] ? '' : ' plan-cta--ghost'; ?>" href="<?php echo esc_url( $p['link'] ); ?>" target="_blank" rel="noopener"><?php echo esc_html( $p['cta'] ); ?></a>
					</div>
				<?php endforeach; ?>
			</div>
			<?php if ( $inv['pos'] ) : ?><p class="after-year"><?php echo esc_html( $inv['pos'] ); ?></p><?php endif; ?>
		</div>
	</section>
	<?php endif; ?>

	<?php if ( $v['passos'] ) : $n = $sec(); ?>
	<section class="panel panel-deep" data-theme="dark">
		<div class="panel-inner">
			<?php $top( $n, 'Próximos passos' ); ?>
			<h2 class="heading-lg">Simples e <mark class="hl">direto</mark>.</h2>
			<div class="steps-list">
				<?php foreach ( $v['passos'] as $i => $row ) : ?>
					<div class="step-item">
						<span class="step-item-num"><?php echo esc_html( str_pad( $i + 1, 2, '0', STR_PAD_LEFT ) ); ?></span>
						<div class="step-item-content"><h3><?php echo esc_html( $row[0] ); ?></h3><p><?php echo esc_html( $row[1] ); ?></p></div>
						<span class="step-item-arrow"><?php echo $arrow; // phpcs:ignore ?></span>
					</div>
				<?php endforeach; ?>
			</div>
		</div>
	</section>
	<?php endif; ?>

	<?php /* CONTATO */ $sec(); ?>
	<section class="panel panel-deep panel-contact" data-theme="dark">
		<div class="contact-inner">
			<?php if ( $v['logo'] ) : ?>
				<div class="contact-top"><img class="contact-logo" src="<?php echo esc_url( $v['logo'] ); ?>" alt="LDK Marketing Digital"></div>
			<?php endif; ?>
			<div class="contact-center">
				<h2 class="contact-heading"><?php echo $hl( $v['contato']['titulo'] ); // phpcs:ignore -- escapado em $hl. ?></h2>
				<?php if ( $v['contato']['texto'] ) : ?><p class="contact-sub"><?php echo esc_html( $v['contato']['texto'] ); ?></p><?php endif; ?>
				<a class="btn-cta" href="<?php echo esc_url( $v['contato']['link'] ); ?>" target="_blank" rel="noopener"><?php echo $wa; // phpcs:ignore -- SVG fixo. ?> Fale com a nossa equipe</a>
			</div>
			<div class="contact-bottom">
				<div class="contact-details">
					<?php foreach ( $v['contacts'] as $label => $value ) : ?>
						<?php if ( $value ) : ?>
							<div class="contact-detail"><span class="contact-detail-label"><?php echo esc_html( $label ); ?></span><span class="contact-detail-value"><?php echo esc_html( $value ); ?></span></div>
						<?php endif; ?>
					<?php endforeach; ?>
				</div>
				<p class="contact-copy">© <?php echo esc_html( gmdate( 'Y' ) ); ?> LDK Marketing Digital. Todos os direitos reservados.</p>
			</div>
		</div>
	</section>

</main>

<nav class="nav-dots" id="navDots" aria-label="Seções"></nav>

<script src="<?php echo esc_url( LDKP_URL . 'assets/proposta.js?ver=' . LDKP_VERSION ); ?>"></script>
</body>
</html>

<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
ap_client_start( 'Tabela de preços', $client );
if ( empty( $client->approved ) ) {
	echo '<div class="empty empty--big">' . ap_icon( 'relogio', 28 ) . '<h3>Cadastro em análise</h3><p>Os preços de cliente aparecem aqui assim que o seu cadastro for aprovado.</p></div>'; // phpcs:ignore
	ap_client_end();
	return;
}
$prazo = max( 1, (int) ap_setting( 'prazo_dias' ) );
?>
<section class="chello">
	<span class="eyebrow">Clientes cadastrados · <?php echo esc_html( gmdate( 'Y' ) ); ?></span>
	<h1>Tabela de preços</h1>
	<p class="muted">Valores por m². Mínimo de <?php echo esc_html( str_replace( '.', ',', ap_setting( 'area_minima' ) ) ); ?> m² por material no pedido. Corte simples incluso. Prazo: até <?php echo (int) $prazo; ?> dia útil após o pagamento e a aprovação da arte.</p>
	<a class="btn btn--primary" href="<?php echo esc_url( ap_client_link( 'novo' ) ); ?>">Fazer um pedido</a>
</section>
<?php foreach ( ap_catalog_groups() as $g => $gl ) : ?>
	<section class="card price-table">
		<div class="card-head"><h3><?php echo esc_html( $gl ); ?></h3><span class="muted small">R$ / m²</span></div>
		<?php foreach ( ap_catalog() as $c ) : ?>
			<?php if ( $c->grp !== $g ) { continue; } ?>
			<div class="price-row">
				<span><strong><?php echo esc_html( $c->name ); ?></strong><?php if ( $c->note ) : ?><small><?php echo esc_html( $c->note ); ?></small><?php endif; ?><small>Larguras: <?php echo esc_html( $c->widths ); ?> cm<?php echo $c->allow_lamination ? ' · aceita laminação' : ''; ?><?php echo $c->allow_eyelets ? ' · aceita reforço e ilhós' : ''; ?></small></span>
				<b class="price-tag"><?php echo esc_html( ap_money( $c->price_m2 ) ); ?></b>
			</div>
		<?php endforeach; ?>
	</section>
<?php endforeach; ?>
<section class="card price-table">
	<div class="card-head"><h3>Serviços e acabamentos</h3></div>
	<div class="price-row"><span><strong>Reforço e ilhós</strong><small>por metro linear, nos lados que você escolher</small></span><b class="price-tag"><?php echo esc_html( ap_money( ap_num_setting( 'preco_ilhos' ) ) ); ?></b></div>
	<div class="price-row"><span><strong>Laminação</strong><small>por m²</small></span><b class="price-tag"><?php echo esc_html( ap_money( ap_num_setting( 'preco_laminacao' ) ) ); ?></b></div>
	<div class="price-row"><span><strong>Corte simples</strong><small>incluso em qualquer produto</small></span><b class="price-tag">incluso</b></div>
	<div class="price-row"><span><strong>Corte complexo ou de quantidade · Urgência</strong><small>sem custo a mais; informe no pedido</small></span><b class="price-tag">informe</b></div>
</section>
<section class="card">
	<div class="card-head"><h3>Arquivos</h3></div>
	<ul class="terms">
		<li>PDF, CDR (convertido em curvas, versão 25) ou JPG com 300 dpi no tamanho real.</li>
		<li>Nome do arquivo: Quantidade_Material_Acabamento_Tamanho (ex.: <code>1x_Adesivo_fosco_corte simples_100x100cm.pdf</code>). Pelo painel, renomeamos sozinhos.</li>
		<li>Não nos responsabilizamos por arquivos em baixa qualidade. Não fazemos troca de produto.</li>
		<li><?php echo esc_html( ap_setting( 'retirada_texto' ) ); ?></li>
	</ul>
</section>
<?php
ap_client_end();

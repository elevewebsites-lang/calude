<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
$rows = lk_client_contract_rows( $client );
lk_client_start( 'Contratos', $client );
?>
<section class="chello"><span class="eyebrow">Contratos</span><h1>Seus contratos</h1><p class="muted">Aqui ficam guardados os contratos com a <?php echo esc_html( lk_setting( 'empresa' ) ); ?>. Você pode abrir, imprimir ou salvar em PDF quando quiser.</p></section>
<?php if ( ! $rows ) : ?>
	<div class="empty"><?php echo lk_icon( 'proposta', 26 ); // phpcs:ignore ?><h3>Nenhum contrato por aqui ainda</h3><p class="muted">Quando a equipe enviar o seu contrato para assinatura, ele aparece neste lugar.</p></div>
<?php else : ?>
	<div class="ctr-cards">
		<?php foreach ( $rows as $k ) : $signed = in_array( $k->status, array( 'assinado', 'concluido' ), true ); ?>
			<section class="card ctr-card">
				<div class="card-head"><h3><?php echo esc_html( $k->title ); ?></h3><em class="badge <?php echo 'concluido' === $k->status ? 'badge--ok' : ( 'enviado' === $k->status ? 'badge--warn' : '' ); ?>"><?php echo 'enviado' === $k->status ? 'Aguardando a sua assinatura' : ( 'concluido' === $k->status ? 'Assinado pelas duas partes ✓' : 'Assinado por você · falta a ' . esc_html( lk_setting( 'empresa' ) ) ); ?></em></div>
				<p class="muted small"><?php echo esc_html( lk_money( $k->monthly_value ) . '/mês · ' . (int) $k->months . ' meses' . ( $k->start_date ? ' · início ' . lk_date( $k->start_date ) : '' ) ); ?><?php echo $k->signed_at ? esc_html( ' · assinado em ' . lk_date( $k->signed_at, 'd/m/Y H:i' ) ) : ''; ?></p>
				<div class="row-btns">
					<a class="btn btn--<?php echo $signed ? 'ghost' : 'primary'; ?>" href="<?php echo esc_url( lk_contract_url( $k ) ); ?>" target="_blank" rel="noopener"><?php echo $signed ? 'Abrir contrato' : 'Ler e assinar'; ?></a>
					<?php if ( $signed ) : ?><a class="btn btn--ghost" href="<?php echo esc_url( lk_contract_url( $k ) ); ?>" target="_blank" rel="noopener">Salvar em PDF</a><?php endif; ?>
				</div>
			</section>
		<?php endforeach; ?>
	</div>
<?php endif; ?>
<?php
lk_client_end();

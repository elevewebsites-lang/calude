<?php
/**
 * Contrato para a cliente ler e assinar: /contrato/<token>/  (e, depois de assinado, a cópia com o certificado).
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
$client = lk_get( 'clients', $k->client_id );
$signed = in_array( $k->status, array( 'assinado', 'concluido' ), true );
$step2  = 'code_sent' === $msg || ( $err && 'enviado' === $k->status && $k->otp_hash );
$mask   = $client && $client->email ? preg_replace( '/(?<=.).(?=[^@]*@)/', '•', $client->email ) : '';
lk_head( $k->title . ' · ' . lk_client_label( $client ) );
?>
<body class="lk ctr-page">
<div class="ctr">
	<header class="ctr-top">
		<?php if ( lk_setting( 'logo' ) ) : ?><img src="<?php echo esc_url( lk_setting( 'logo' ) ); ?>" alt="<?php echo esc_attr( lk_setting( 'empresa' ) ); ?>"><?php endif; ?>
		<span class="ctr-st ctr-st--<?php echo esc_attr( $k->status ); ?>"><?php echo esc_html( $signed ? ( 'concluido' === $k->status ? 'Assinado pelas duas partes ✓' : 'Assinado pela cliente ✓' ) : 'Aguardando a sua assinatura' ); ?></span>
		<button type="button" class="ctr-print" onclick="window.print()">Salvar em PDF / imprimir</button>
	</header>

	<?php if ( $msg && 'code_sent' !== $msg ) : ?><div class="ctr-msg"><?php echo esc_html( $msg ); ?></div><?php endif; ?>
	<?php if ( $err ) : ?><div class="ctr-msg ctr-msg--err"><?php echo esc_html( $err ); ?></div><?php endif; ?>

	<article class="ctr-doc"><?php echo lk_contract_html( $k->body ); // phpcs:ignore ?></article>

	<?php if ( 'enviado' === $k->status ) : ?>
		<section class="ctr-sign" id="assinar">
			<h2>Assinar o contrato</h2>
			<?php if ( ! $step2 ) : ?>
				<p>Para confirmar que é você, enviamos um código para <strong><?php echo esc_html( $mask ); ?></strong>.</p>
				<form method="post">
					<?php wp_nonce_field( 'lk_contract_' . $k->id ); ?>
					<input type="hidden" name="passo" value="codigo">
					<button type="submit" class="ctr-btn">Receber o código no e-mail</button>
				</form>
			<?php else : ?>
				<p>Enviamos o código para <strong><?php echo esc_html( $mask ); ?></strong> (vale 10 minutos; confira o spam).</p>
				<form method="post" class="ctr-form">
					<?php wp_nonce_field( 'lk_contract_' . $k->id ); ?>
					<input type="hidden" name="passo" value="assinar">
					<input type="hidden" data-sig-required value="1">
					<div class="ctr-grid">
						<label><span>Código recebido</span><input type="text" name="codigo" inputmode="numeric" maxlength="6" required autocomplete="one-time-code" placeholder="000000"></label>
						<label><span>Nome completo</span><input type="text" name="nome" required value="<?php echo esc_attr( $client ? $client->name : '' ); ?>"></label>
						<label><span>CPF</span><input type="text" name="doc" required inputmode="numeric" value="<?php echo esc_attr( $client ? $client->rep_cpf : '' ); ?>"></label>
					</div>
					<?php lk_sig_pad(); ?>
					<label class="ctr-check"><input type="checkbox" name="aceite" value="1" required> Li e concordo com todas as cláusulas deste contrato e com a assinatura eletrônica.</label>
					<button type="submit" class="ctr-btn">✍️ Assinar contrato</button>
				</form>
				<form method="post" class="ctr-resend"><?php wp_nonce_field( 'lk_contract_' . $k->id ); ?><input type="hidden" name="passo" value="codigo"><button type="submit" class="ctr-link">Não chegou? Enviar outro código</button></form>
			<?php endif; ?>
		</section>
	<?php endif; ?>

	<?php if ( $signed || $k->agency_signed_at ) : ?>
		<section class="ctr-sigs">
			<div>
				<?php if ( $k->signed_at ) : ?><img src="<?php echo esc_attr( $k->signer_sig ); ?>" alt="Assinatura"><?php endif; ?>
				<strong><?php echo esc_html( $k->signer_name ? $k->signer_name : lk_client_label( $client ) ); ?></strong>
				<small>CONTRATANTE · <?php echo esc_html( lk_client_label( $client ) ); ?></small>
			</div>
			<div>
				<?php if ( $k->agency_signed_at ) : ?><img src="<?php echo esc_attr( $k->agency_sig ); ?>" alt="Assinatura"><?php endif; ?>
				<strong><?php echo esc_html( $k->agency_name ? $k->agency_name : lk_setting( 'empresa' ) ); ?></strong>
				<small>CONTRATADA · <?php echo esc_html( lk_setting( 'empresa_razao' ) ? lk_setting( 'empresa_razao' ) : lk_setting( 'empresa' ) ); ?></small>
			</div>
		</section>
		<section class="ctr-cert">
			<h3>Certificado de assinatura eletrônica</h3>
			<dl>
				<dt>Documento</dt><dd><?php echo esc_html( $k->title ); ?></dd>
				<dt>Código do documento (SHA-256)</dt><dd class="mono"><?php echo esc_html( $k->hash ); ?></dd>
				<dt>Enviado para assinatura</dt><dd><?php echo esc_html( lk_date( $k->sent_at, 'd/m/Y H:i:s' ) ); ?> (horário de Brasília)</dd>
				<?php if ( $k->signed_at ) : ?>
					<dt>Contratante</dt><dd><?php echo esc_html( $k->signer_name . ' · CPF ' . $k->signer_doc ); ?><br>E-mail confirmado por código: <?php echo esc_html( $k->signer_email ); ?><br>Assinado em <?php echo esc_html( lk_date( $k->signed_at, 'd/m/Y H:i:s' ) ); ?> · IP <?php echo esc_html( $k->signer_ip ); ?><br><small><?php echo esc_html( $k->signer_ua ); ?></small></dd>
				<?php endif; ?>
				<?php if ( $k->agency_signed_at ) : ?>
					<dt>Contratada</dt><dd><?php echo esc_html( $k->agency_name . ( $k->agency_doc ? ' · CPF ' . $k->agency_doc : '' ) ); ?><br>Assinado em <?php echo esc_html( lk_date( $k->agency_signed_at, 'd/m/Y H:i:s' ) ); ?> · IP <?php echo esc_html( $k->agency_ip ); ?></dd>
				<?php endif; ?>
				<dt>Base legal</dt><dd>Assinatura eletrônica nos termos da Lei 14.063/2020 e da MP 2.200-2/2001, art. 10, § 2º, aceita pelas partes na cláusula de assinatura.</dd>
				<dt>Conferir</dt><dd><?php echo esc_html( lk_contract_url( $k ) ); ?></dd>
			</dl>
		</section>
	<?php endif; ?>
	<footer class="ctr-foot"><?php echo lk_credit_html( 'dark' ); // phpcs:ignore ?></footer>
</div>
<script src="<?php echo esc_url( LK_URL . 'assets/assinatura.js?ver=' . LK_VERSION ); ?>"></script>
<?php if ( $step2 ) : ?><script>document.getElementById('assinar').scrollIntoView();</script><?php endif; ?>
</body>
</html>

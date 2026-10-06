<?php
/**
 * Volta do checkout (ou erro ao gerar o link). Recebe $trans e, opcionalmente, $error.
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
$paid    = 'pago' === $trans->status;
$error   = isset( $error ) ? $error : '';
$project = $trans->project_id ? ap_get( 'projects', $trans->project_id ) : null;
$next    = $project ? ap_client_url( 'projeto', $project->id ) : ap_client_url();
$service = $project && $project->service_id ? ap_get( 'services', $project->service_id ) : null;
$brief   = $project && $service && $service->briefing && ! $project->briefing_at ? ap_client_url( 'briefing', $project->id ) : '';
$nextlbl = 'Acompanhar meu pedido';
$brief   = '';
ap_head( $paid ? 'Pagamento confirmado' : 'Pagamento' );
?>
<body class="ap ap-auth">
<div class="pay-result">
	<?php echo ap_logo_for( 'pay', 'pay-logo' ); // phpcs:ignore ?>
	<div class="pay-card">
		<?php if ( $paid ) : ?>
			<span class="pay-ic pay-ic--ok"><?php echo ap_icon( 'check', 30 ); // phpcs:ignore ?></span>
			<h1>Pagamento confirmado!</h1>
			<p class="muted"><?php echo esc_html( ap_money( $trans->amount ) ); ?> recebido<?php echo $trans->method ? ' via ' . esc_html( $trans->method ) : ''; ?>. Obrigado pela confiança.</p>
			<?php if ( $brief ) : ?>
				<p>O próximo passo é o <strong>briefing</strong>: leva uns 10 minutos e guia todo o projeto.</p>
				<a class="btn btn--primary btn--block" href="<?php echo esc_url( $brief ); ?>">Preencher o briefing</a>
				<a class="btn btn--ghost btn--block" href="<?php echo esc_url( $next ); ?>"><?php echo esc_html( $nextlbl ); ?></a>
			<?php else : ?>
				<a class="btn btn--primary btn--block" href="<?php echo esc_url( $next ); ?>"><?php echo esc_html( $nextlbl ); ?></a>
			<?php endif; ?>
		<?php elseif ( $error ) : ?>
			<span class="pay-ic pay-ic--warn"><?php echo ap_icon( 'alerta', 30 ); // phpcs:ignore ?></span>
			<h1>Não conseguimos abrir o pagamento</h1>
			<p class="muted">Tente de novo em alguns minutos ou fale com a gente pelo WhatsApp.</p>
			<?php if ( ap_is_team() ) : ?><p class="small text-late"><?php echo esc_html( $error ); ?></p><?php endif; ?>
			<a class="btn btn--primary btn--block" href="<?php echo esc_url( ap_pay_url( $trans->id ) ); ?>">Tentar de novo</a>
			<a class="btn btn--wa btn--block" href="<?php echo esc_url( ap_wa_link( ap_setting( 'whatsapp' ), 'Olá! Tive um problema para pagar: ' . $trans->description ) ); ?>" target="_blank" rel="noopener">Falar no WhatsApp</a>
		<?php else : ?>
			<span class="pay-ic pay-ic--warn"><?php echo ap_icon( 'relogio', 30 ); // phpcs:ignore ?></span>
			<h1>Aguardando a confirmação</h1>
			<p class="muted">Se você acabou de pagar, a confirmação chega em instantes. Pix costuma ser na hora.</p>
			<a class="btn btn--primary btn--block" href="<?php echo esc_url( ap_pay_url( $trans->id ) ); ?>">Pagar <?php echo esc_html( ap_money( $trans->amount ) ); ?></a>
			<a class="btn btn--ghost btn--block" href="<?php echo esc_url( $next ); ?>"><?php echo esc_html( $nextlbl ); ?></a>
		<?php endif; ?>
 	</div>
	<?php echo ap_credit_html( 'light' ); // phpcs:ignore ?>
</div>
</body>
</html>

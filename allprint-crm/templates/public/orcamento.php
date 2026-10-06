<?php
/**
 * Página pública do orçamento: /orcamento/<token>/
 * Recebe $q.
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
$items    = ap_quote_items( $q );
$images   = ap_json( $q->images );
$client   = $q->client_id ? ap_get( 'clients', $q->client_id ) : null;
$current  = is_user_logged_in() ? ap_client_by_user( get_current_user_id() ) : null;
$known    = $current && ( ! $client || (int) $current->id === (int) $client->id );
$error    = isset( $GLOBALS['ap_quote_error'] ) ? $GLOBALS['ap_quote_error'] : '';
$done     = in_array( $q->status, array( 'aceito', 'pago' ), true );
$expired  = $q->valid_until && $q->valid_until < ap_today() && ! $done;
$pickup   = ap_quote_amounts( $q, 'retirada' );
$ship     = ap_quote_amounts( $q, 'envio' );
$resale   = 'revenda' === $q->audience;
$first    = $client && $client->name ? strtok( $client->name, ' ' ) : '';
$old      = function ( $k, $def = '' ) {
	return isset( $_POST[ $k ] ) ? sanitize_text_field( wp_unslash( $_POST[ $k ] ) ) : $def; // phpcs:ignore WordPress.Security.NonceVerification
};
$pending  = null;
if ( $done && $q->project_id ) {
	$tr      = ap_rows( 'transactions', "project_id = %d AND type = 'in' AND status = 'pendente'", array( $q->project_id ), 'id' );
	$pending = $tr ? $tr[0] : null;
}
$wa       = ap_wa_link( ap_setting( 'whatsapp' ), 'Olá! Tenho uma dúvida sobre a proposta nº ' . ap_quote_number( $q ) . ' "' . $q->title . '".' );
$print    = isset( $_GET['pdf'] );
$number   = ap_quote_number( $q );
$total_qty = 0;
foreach ( $items as $it ) {
	$total_qty += (int) $it['qty'];
}
$addr     = ap_setting( 'endereco' );
$phone    = ap_setting( 'whatsapp' );
$mail     = ap_setting( 'email' );
$site     = preg_replace( '#^https?://#', '', rtrim( (string) ap_setting( 'site' ), '/' ) );
$parcelas = max( 1, (int) ap_setting( 'parcelas_max' ) );
$days_left = $q->valid_until ? (int) floor( ( strtotime( $q->valid_until ) - strtotime( ap_today() ) ) / DAY_IN_SECONDS ) : null;
$off      = str_replace( '.', ',', rtrim( rtrim( number_format( $pickup['off'], 1, '.', '' ), '0' ), '.' ) );
?><!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
<meta name="robots" content="noindex, nofollow">
<meta name="theme-color" content="<?php echo esc_attr( ap_surfaces()['oq'][1] ); ?>">
<title><?php echo esc_html( 'Proposta nº ' . $number . ' · ' . $q->title . ' · ' . ap_setting( 'empresa' ) ); ?></title>
<meta property="og:title" content="<?php echo esc_attr( 'Proposta comercial: ' . $q->title ); ?>">
<?php if ( $images ) : ?><meta property="og:image" content="<?php echo esc_url( $images[0]['url'] ); ?>"><?php endif; ?>
<link rel="icon" href="<?php echo esc_url( ap_setting( 'favicon' ) ); ?>">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<?php ap_theme_head( 'proposta.css', true ); ?>
</head>
<body class="pp<?php echo $print ? ' pp--print' : ''; ?>">

<header class="pp-bar">
	<div class="pp-wrap pp-bar-in">
		<?php echo ap_logo_for( 'oq', 'oq-logo' ); // phpcs:ignore ?>
		<div class="pp-actions">
			<button type="button" class="pp-btn pp-btn--ghost" data-print><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><path d="m7 10 5 5 5-5"/><path d="M12 15V3"/></svg><span>Baixar PDF</span></button>
			<a class="pp-btn pp-btn--ghost" href="<?php echo esc_url( $wa ); ?>" target="_blank" rel="noopener"><span>Dúvidas? WhatsApp</span></a>
		</div>
	</div>
</header>

<main class="pp-wrap pp-main">
	<article class="pp-doc">

		<section class="pp-cover">
			<div class="pp-cover-top">
				<span class="pp-tag">Proposta comercial</span>
				<span class="pp-num">Nº <?php echo esc_html( $number ); ?></span>
			</div>
			<h1><?php echo esc_html( $q->title ); ?></h1>
			<?php if ( $q->intro ) : ?><p class="pp-intro"><?php echo nl2br( esc_html( $q->intro ) ); ?></p><?php endif; ?>
			<dl class="pp-meta">
				<div><dt>Preparada para</dt><dd><?php echo esc_html( $client ? ap_client_label( $client ) : 'Cliente' ); ?></dd>
					<?php if ( $client && $client->name && $client->company ) : ?><dd class="pp-sub">A/C <?php echo esc_html( $client->name ); ?></dd><?php endif; ?>
					<?php if ( $client && $client->cnpj ) : ?><dd class="pp-sub"><?php echo esc_html( $client->cnpj ); ?></dd><?php endif; ?></div>
				<div><dt>Emitida em</dt><dd><?php echo esc_html( ap_date( $q->created_at ) ); ?></dd><dd class="pp-sub">por <?php echo esc_html( ap_setting( 'empresa' ) ); ?></dd></div>
				<div><dt>Válida até</dt><dd><?php echo $q->valid_until ? esc_html( ap_date( $q->valid_until ) ) : '—'; ?></dd><?php if ( ! $done && ! $expired && null !== $days_left && $days_left <= 3 ) : ?><dd class="pp-sub pp-warn"><?php echo $days_left <= 0 ? 'Vence hoje' : 'Faltam ' . (int) $days_left . ' dia(s)'; ?></dd><?php endif; ?></div>
				<?php if ( $q->deadline_days ) : ?><div><dt>Prazo de produção</dt><dd><?php echo (int) $q->deadline_days; ?> dias</dd><dd class="pp-sub">após a aprovação da arte e do pagamento</dd></div><?php endif; ?>
			</dl>
		</section>

		<section class="pp-sec">
			<h2><span>01</span> Serviços e valores</h2>
			<div class="pp-table" role="table">
				<div class="pp-row pp-row--head" role="row"><span>Item</span><span class="pp-r">Qtd.</span><span class="pp-r">Unitário</span><span class="pp-r">Total</span></div>
				<?php foreach ( $items as $n => $it ) : ?>
					<?php
					$mat  = $it['material'] ? ap_get( 'catalog', $it['material'] ) : null;
					$dims = $it['w'] && $it['h'] ? rtrim( rtrim( number_format( (float) $it['w'], 1, ',', '' ), '0' ), ',' ) . ' × ' . rtrim( rtrim( number_format( (float) $it['h'], 1, ',', '' ), '0' ), ',' ) . ' cm' : '';
					?>
					<div class="pp-row" role="row">
						<span class="pp-item">
							<i><?php echo (int) $n + 1; ?></i>
							<span>
								<strong><?php echo esc_html( $it['name'] ); ?></strong>
								<?php if ( $mat || $dims ) : ?><em><?php $mn = $mat && trim( $mat->name ) !== trim( $it['name'] ) ? $mat->name : ''; echo esc_html( trim( $mn . ( $mn && $dims ? ' · ' : '' ) . $dims ) ); ?></em><?php endif; ?>
								<?php if ( $it['desc'] ) : ?><small><?php echo nl2br( esc_html( $it['desc'] ) ); ?></small><?php endif; ?>
								<?php if ( $mat && $mat->included ) : ?><small class="pp-inc">Incluso: <?php echo esc_html( $mat->included ); ?></small><?php endif; ?>
							</span>
						</span>
						<span class="pp-r" data-l="Qtd."><?php echo (int) $it['qty']; ?></span>
						<span class="pp-r" data-l="Unitário"><?php echo esc_html( ap_money( $it['unit'] ) ); ?></span>
						<span class="pp-r pp-strong" data-l="Total"><?php echo esc_html( ap_money( $it['unit'] * $it['qty'] ) ); ?></span>
					</div>
				<?php endforeach; ?>
			</div>

			<div class="pp-totals">
				<?php if ( $q->discount > 0 ) : ?>
					<div><span>Subtotal</span><b><?php echo esc_html( ap_money( $q->subtotal ) ); ?></b></div>
					<div><span>Desconto</span><b>− <?php echo esc_html( ap_money( $q->discount ) ); ?></b></div>
				<?php endif; ?>
				<div class="pp-total"><span>Valor total</span><b><?php echo esc_html( ap_money( $pickup['cartao'] ) ); ?></b></div>
				<div class="pp-pix"><span>À vista no Pix<?php echo $pickup['off'] > 0 ? ' <em>−' . esc_html( $off ) . '%</em>' : ''; ?></span><b><?php echo esc_html( ap_money( $pickup['pix'] ) ); ?></b></div>
				<?php if ( $parcelas > 1 ) : ?><div><span>Cartão de crédito</span><b><?php echo esc_html( $parcelas . 'x de ' . ap_money( $pickup['cartao'] / $parcelas ) ); ?></b></div><?php endif; ?>
			</div>
		</section>

		<?php if ( $images ) : ?>
			<section class="pp-sec">
				<h2><span>02</span> Arte e referências</h2>
				<div class="pp-gallery pp-gallery--<?php echo min( 3, count( $images ) ); ?>">
					<?php foreach ( $images as $img ) : ?>
						<a href="<?php echo esc_url( $img['url'] ); ?>" target="_blank" rel="noopener"><img src="<?php echo esc_url( $img['thumb'] ?? $img['url'] ); ?>" alt="" loading="lazy"></a>
					<?php endforeach; ?>
				</div>
			</section>
		<?php endif; ?>

		<section class="pp-sec">
			<h2><span><?php echo $images ? '03' : '02'; ?></span> Condições</h2>
			<ul class="pp-terms">
				<?php if ( $q->deadline_days ) : ?><li><b>Prazo:</b> produção em até <strong><?php echo (int) $q->deadline_days; ?> dias</strong> após a confirmação do pagamento e a aprovação da arte.</li><?php endif; ?>
				<li><b>Retirada:</b> <?php echo esc_html( ap_setting( 'retirada_texto' ) ); ?></li>
				<?php if ( $q->freight > 0 ) : ?><li><b>Entrega:</b> envio para o seu endereço por <strong><?php echo esc_html( ap_money( $q->freight ) ); ?></strong><?php echo $q->freight_label ? ' (' . esc_html( $q->freight_label ) . ')' : ''; ?>.</li><?php endif; ?>
				<li><b>Pagamento:</b> Pix à vista<?php echo $pickup['off'] > 0 ? ' com ' . esc_html( $off ) . '% de desconto' : ''; ?><?php echo $parcelas > 1 ? ' ou cartão de crédito em até ' . (int) $parcelas . 'x sem juros' : ''; ?>.</li>
				<?php if ( 'final' !== $q->audience && ap_setting( 'empresa_nota' ) ) : ?><li><?php echo esc_html( ap_setting( 'empresa_nota' ) ); ?></li><?php endif; ?>
				<?php if ( $q->notes ) : ?><li><?php echo nl2br( esc_html( $q->notes ) ); ?></li><?php endif; ?>
				<?php if ( $q->valid_until ) : ?><li><b>Validade:</b> esta proposta é válida até <strong><?php echo esc_html( ap_date( $q->valid_until ) ); ?></strong>.</li><?php endif; ?>
			</ul>
		</section>

		<footer class="pp-sign">
			<div>
				<strong><?php echo esc_html( ap_setting( 'empresa' ) ); ?></strong>
				<?php if ( $addr ) : ?><span><?php echo esc_html( $addr ); ?></span><?php endif; ?>
				<span><?php echo esc_html( trim( $phone . ( $phone && $mail ? ' · ' : '' ) . $mail ) ); ?></span>
				<?php if ( $site ) : ?><span><?php echo esc_html( $site ); ?></span><?php endif; ?>
			</div>
			<div class="pp-ok"><span class="pp-line"></span><small>De acordo · <?php echo esc_html( $client ? ap_client_label( $client ) : 'Cliente' ); ?></small></div>
		</footer>
	</article>

	<div class="pp-side">
		<aside class="pp-card oq-pay">
			<?php if ( $done ) : ?>
				<h2>Proposta aprovada ✓</h2>
				<p class="oq-muted">Aprovado em <?php echo esc_html( ap_date( $q->accepted_at ) ); ?>. <?php echo 'pago' === $q->status ? 'Pagamento confirmado: seu pedido já está na fila de produção.' : 'Falta só o pagamento.'; ?></p>
				<?php if ( $pending ) : ?><a class="oq-btn" href="<?php echo esc_url( ap_pay_url( $pending->id ) ); ?>">Pagar <?php echo esc_html( ap_money( $pending->amount ) ); ?></a><?php endif; ?>
				<a class="oq-btn oq-btn--ghost" href="<?php echo esc_url( ap_client_url( 'projeto', $q->project_id ) ); ?>">Acompanhar meu pedido</a>
			<?php elseif ( $expired ) : ?>
				<h2>Proposta vencida</h2>
				<p class="oq-muted">Esta proposta valia até <?php echo esc_html( ap_date( $q->valid_until ) ); ?>. Chame no WhatsApp que a gente atualiza rapidinho.</p>
				<a class="oq-btn" href="<?php echo esc_url( $wa ); ?>" target="_blank" rel="noopener">Falar no WhatsApp</a>
			<?php else : ?>
				<h2>Aprovar e pagar</h2>
				<?php if ( $error ) : ?><div class="oq-error"><?php echo esc_html( $error ); ?></div><?php endif; ?>
				<form method="post" class="oq-form" data-accept data-pix-pickup="<?php echo esc_attr( $pickup['pix'] ); ?>" data-card-pickup="<?php echo esc_attr( $pickup['cartao'] ); ?>" data-pix-ship="<?php echo esc_attr( $ship['pix'] ); ?>" data-card-ship="<?php echo esc_attr( $ship['cartao'] ); ?>" data-parcelas="<?php echo (int) ap_setting( 'parcelas_max' ); ?>">
					<?php wp_nonce_field( 'ap_quote_' . $q->id ); ?>
					<input type="hidden" name="ap_quote_accept" value="1">

					<?php if ( $q->freight > 0 ) : ?>
						<fieldset class="oq-opts">
							<legend>Como você quer receber?</legend>
							<label class="oq-opt"><input type="radio" name="entrega" value="retirada"<?php checked( $old( 'entrega', 'retirada' ), 'retirada' ); ?>><span><strong>Retirar na loja</strong><small>Grátis · combinamos o horário</small></span></label>
							<label class="oq-opt"><input type="radio" name="entrega" value="envio"<?php checked( $old( 'entrega' ), 'envio' ); ?>><span><strong>Receber em casa</strong><small>+ <?php echo esc_html( ap_money( $q->freight ) . ( $q->freight_label ? ' · ' . $q->freight_label : '' ) ); ?></small></span></label>
						</fieldset>
					<?php endif; ?>

					<fieldset class="oq-opts">
						<legend>Forma de pagamento</legend>
						<label class="oq-opt"><input type="radio" name="pagamento" value="pix"<?php checked( $old( 'pagamento', 'pix' ), 'pix' ); ?>><span><strong>Pix à vista<?php echo $pickup['off'] > 0 ? ' <em>−' . esc_html( $off ) . '%</em>' : ''; ?></strong><small data-price="pix"><?php echo esc_html( ap_money( $pickup['pix'] ) ); ?></small></span></label>
						<label class="oq-opt"><input type="radio" name="pagamento" value="cartao"<?php checked( $old( 'pagamento' ), 'cartao' ); ?>><span><strong><?php echo esc_html( ap_quote_pay_text() ); ?></strong><small data-price="cartao"><?php echo esc_html( ap_money( $pickup['cartao'] ) ); ?></small></span></label>
					</fieldset>

					<?php if ( ! $known ) : ?>
						<div class="oq-fields">
							<label><span>Seu nome</span><input type="text" name="name" required autocomplete="name" value="<?php echo esc_attr( $old( 'name', $client ? $client->name : '' ) ); ?>"></label>
							<label><span>WhatsApp</span><input type="tel" name="whatsapp" required autocomplete="tel" value="<?php echo esc_attr( $old( 'whatsapp', $client ? $client->whatsapp : '' ) ); ?>"></label>
							<label><span>E-mail (seu login)</span><input type="email" name="email" required autocomplete="email" value="<?php echo esc_attr( $old( 'email', $client ? $client->email : '' ) ); ?>"></label>
							<label><span>Crie uma senha</span><input type="password" name="senha" required minlength="8" autocomplete="new-password"></label>
							<?php if ( 'final' !== $q->audience ) : ?>
								<label><span>Empresa / loja</span><input type="text" name="company" value="<?php echo esc_attr( $old( 'company', $client ? $client->company : '' ) ); ?>"></label>
								<label><span>CNPJ ou CPF</span><input type="text" name="cnpj" inputmode="numeric" value="<?php echo esc_attr( $old( 'cnpj', $client ? $client->cnpj : '' ) ); ?>"></label>
							<?php endif; ?>
						</div>
						<p class="oq-muted oq-small">Com esse acesso você acompanha a produção e vê as fotos da peça pronta. Já tem? <a href="<?php echo esc_url( add_query_arg( 'volta', rawurlencode( wp_parse_url( ap_quote_url( $q ), PHP_URL_PATH ) ), ap_url( 'entrar' ) ) ); ?>">Entrar</a></p>
					<?php else : ?>
						<p class="oq-muted oq-small">Olá, <?php echo esc_html( strtok( (string) $current->name, ' ' ) ); ?>! Você já está conectado.</p>
					<?php endif; ?>
					<?php if ( $q->freight > 0 ) : ?>
						<div class="oq-fields oq-ship" data-ship-fields hidden>
							<label><span>CEP</span><input type="text" name="cep" inputmode="numeric" value="<?php echo esc_attr( $old( 'cep', $client ? $client->cep : '' ) ); ?>"></label>
							<label class="oq-full"><span>Endereço completo</span><input type="text" name="address" placeholder="Rua, número, bairro, cidade/UF" value="<?php echo esc_attr( $old( 'address', $client ? $client->address : '' ) ); ?>"></label>
						</div>
					<?php endif; ?>

					<label class="oq-check"><input type="checkbox" name="aceite" value="1" required><span>Li e aprovo esta proposta.</span></label>
					<button type="submit" class="oq-btn">Aprovar e pagar <span data-price="btn"><?php echo esc_html( ap_money( $pickup['pix'] ) ); ?></span></button>
					<p class="oq-muted oq-small oq-center">Pagamento seguro pela InfinitePay.</p>
				</form>
			<?php endif; ?>
		</aside>
	</div>
</main>

<footer class="pp-foot"><div class="pp-wrap"><?php echo ap_credit_html( 'light' ); // phpcs:ignore ?> © <?php echo esc_html( gmdate( 'Y' ) . ' ' . ap_setting( 'empresa' ) ); ?><?php echo $site ? ' · <a href="' . esc_url( ap_setting( 'site' ) ) . '" target="_blank" rel="noopener">' . esc_html( $site ) . '</a>' : ''; ?></div></footer>

<script>
(function () {
	var f = document.querySelector('[data-accept]');
	if (!f) return;
	var money = function (v) { return 'R$ ' + (Math.round(v * 100) / 100).toFixed(2).replace('.', ',').replace(/\B(?=(\d{3})+(?!\d))/g, '.'); };
	function upd() {
		var ship = (f.querySelector('[name=entrega]:checked') || {}).value === 'envio';
		var pay = (f.querySelector('[name=pagamento]:checked') || {}).value || 'pix';
		var pix = +f.getAttribute(ship ? 'data-pix-ship' : 'data-pix-pickup'), card = +f.getAttribute(ship ? 'data-card-ship' : 'data-card-pickup'), n = +f.getAttribute('data-parcelas') || 1;
		f.querySelector('[data-price=pix]').textContent = money(pix);
		f.querySelector('[data-price=cartao]').textContent = money(card) + (n > 1 ? ' · ' + n + 'x de ' + money(card / n) : '');
		f.querySelector('[data-price=btn]').textContent = money(pay === 'pix' ? pix : card);
		var sf = f.querySelector('[data-ship-fields]'); if (sf) { sf.hidden = !ship; sf.querySelectorAll('input').forEach(function (i) { i.required = ship; }); }
	}
	f.addEventListener('change', upd);
	f.addEventListener('submit', function () { var b = f.querySelector('button[type=submit]'); b.disabled = true; b.textContent = 'Abrindo o pagamento…'; });
	upd();
})();
</script>
<script>
(function () {
	var b = document.querySelector('[data-print]');
	if (b) b.addEventListener('click', function () { window.print(); });
	<?php if ( $print ) : ?>window.addEventListener('load', function () { setTimeout(function () { window.print(); }, 500); });<?php endif; ?>
})();
</script>
</body>
</html>

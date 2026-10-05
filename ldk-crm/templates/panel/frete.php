<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
$ready = lk_setting( 'melhorenvio_token' ) && lk_setting( 'cep_origem' );
lk_panel_start( 'Frete', 'frete' );
?>
<?php if ( ! $ready ) : ?>
	<div class="flash flash--warn">Para calcular, crie uma conta grátis no <a href="https://melhorenvio.com.br" target="_blank" rel="noopener">Melhor Envio</a>, gere um token (Configurações → Tokens) e cole em <a href="<?php echo esc_url( lk_panel_url( 'config' ) ); ?>#frete">Configurações → Frete</a>, com o CEP de origem.</div>
<?php endif; ?>
<section class="card">
	<div class="card-head"><h3>Calcular frete</h3><span class="muted small">Correios (PAC/SEDEX), Jadlog, Loggi, J&amp;T e outras, numa consulta só</span></div>
	<form class="stack" data-freight-page>
		<div class="grid-4">
			<?php lk_input( 'f_cep', 'CEP de destino', isset( $_GET['cep'] ) ? sanitize_text_field( wp_unslash( $_GET['cep'] ) ) : '', 'text', 'inputmode="numeric" required placeholder="00000-000"' ); // phpcs:ignore ?>
			<?php lk_input( 'f_peso', 'Peso com embalagem (g)', '', 'number', 'min="1" required placeholder="300"' ); ?>
			<?php lk_select( 'f_caixa', 'Caixa', array_map( function ( $b ) { return $b['name'] . ' (' . $b['l'] . '×' . $b['w'] . '×' . $b['h'] . ' cm)'; }, lk_boxes() ) ); ?>
			<?php lk_input( 'f_valor', 'Valor declarado (R$)', isset( $_GET['valor'] ) ? sanitize_text_field( wp_unslash( $_GET['valor'] ) ) : '', 'text', 'inputmode="decimal"' ); // phpcs:ignore ?>
		</div>
		<div class="form-actions"><button type="submit" class="btn btn--primary">Cotar</button></div>
	</form>
	<div class="freight-table" data-freight-page-out></div>
</section>
<p class="muted small hint">As caixas ficam em Configurações → Frete (Nome | comprimento | largura | altura). Pese a peça já embalada na sua balança para o valor bater.</p>
<script>
(function () {
	var f = document.querySelector('[data-freight-page]'); if (!f) return;
	var out = document.querySelector('[data-freight-page-out]');
	var m = function (v) { return 'R$ ' + (+v).toFixed(2).replace('.', ','); };
	f.addEventListener('submit', function (e) {
		e.preventDefault(); out.innerHTML = '<p class="muted small">Cotando…</p>';
		var qs = ['cep', 'peso', 'caixa', 'valor'].map(function (k) { return k + '=' + encodeURIComponent(f.querySelector('[name=f_' + k + ']').value.replace(',', '.')); }).join('&');
		fetch(LK.rest + 'frete?' + qs, { headers: { 'X-WP-Nonce': LK.nonce }, credentials: 'same-origin' }).then(function (r) { return r.json().then(function (j) { return { ok: r.ok, j: j }; }); }).then(function (res) {
			if (!res.ok) throw new Error(res.j.message);
			out.innerHTML = res.j.options.length ? '<div class="table"><div class="table-row table-head"><span>Serviço</span><span>Prazo</span><span>Preço</span><span></span></div>' + res.j.options.map(function (o) { return '<div class="table-row"><span class="cell-main"><strong>' + o.label + '</strong></span><span>' + o.days + ' dias úteis</span><span class="money">' + m(o.price) + '</span><span><button type="button" class="btn btn--ghost btn--sm" data-copy="' + (o.label + ' · ' + m(o.price) + ' · ' + o.days + ' dias úteis').replace(/"/g, '') + '">Copiar</button></span></div>'; }).join('') + '</div>' : '<p class="muted">Nenhuma opção para esse CEP e caixa.</p>';
		}).catch(function (err) { out.innerHTML = '<p class="text-late">' + err.message + '</p>'; });
	});
})();
</script>
<?php
lk_panel_end();

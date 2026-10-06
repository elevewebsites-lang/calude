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
$model    = ap_json( $q->model3d );
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
$wa       = ap_wa_link( ap_setting( 'whatsapp' ), 'Olá! Tenho uma dúvida sobre o orçamento "' . $q->title . '".' );
$off      = str_replace( '.', ',', rtrim( rtrim( number_format( $pickup['off'], 1, '.', '' ), '0' ), '.' ) );
?><!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
<meta name="robots" content="noindex, nofollow">
<meta name="theme-color" content="#0a0a0a">
<title><?php echo esc_html( 'Orçamento · ' . $q->title . ' · ' . ap_setting( 'empresa' ) ); ?></title>
<meta property="og:title" content="<?php echo esc_attr( 'Orçamento: ' . $q->title ); ?>">
<?php if ( $images ) : ?><meta property="og:image" content="<?php echo esc_url( $images[0]['url'] ); ?>"><?php endif; ?>
<link rel="icon" href="<?php echo esc_url( ap_setting( 'favicon' ) ); ?>">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<?php ap_theme_head( 'orcamento.css', false ); ?>
</head>
<body class="oq">

<header class="oq-top">
	<div class="oq-wrap oq-top-in">
		<?php echo ap_logo_for( 'oq', 'oq-logo' ); // phpcs:ignore ?>
		<a class="oq-pill oq-pill--ghost" href="<?php echo esc_url( $wa ); ?>" target="_blank" rel="noopener">Dúvidas? WhatsApp</a>
	</div>
</header>

<section class="oq-hero">
	<div class="oq-wrap">
		<span class="oq-eyebrow">Orçamento<?php echo $resale ? ' para revenda' : ''; ?> · <?php echo esc_html( ap_date( $q->created_at ) ); ?></span>
		<h1><?php echo $first ? '<span class="oq-thin">' . esc_html( $first ) . ',</span><br>' : ''; ?><?php echo esc_html( $q->title ); ?></h1>
		<?php if ( $q->intro ) : ?><p class="oq-intro"><?php echo nl2br( esc_html( $q->intro ) ); ?></p><?php endif; ?>
	</div>
</section>

<main class="oq-wrap oq-main">
	<?php if ( $images || $model ) : ?>
		<section class="oq-media">
			<?php if ( $model ) : ?>
				<div class="oq-viewer" data-model="<?php echo esc_url( $model['url'] ); ?>" data-ext="<?php echo esc_attr( strtolower( pathinfo( $model['name'], PATHINFO_EXTENSION ) ) ); ?>">
					<canvas></canvas>
					<div class="oq-viewer-hint">Arraste para girar · role ou pince para aproximar</div>
					<div class="oq-viewer-load">Carregando o modelo 3D…</div>
				</div>
			<?php endif; ?>
			<?php if ( $images ) : ?>
				<div class="oq-gallery<?php echo count( $images ) === 1 && ! $model ? ' oq-gallery--one' : ''; ?>">
					<?php foreach ( $images as $img ) : ?>
						<a href="<?php echo esc_url( $img['url'] ); ?>" target="_blank" rel="noopener" class="oq-shot"><img src="<?php echo esc_url( $img['thumb'] ?? $img['url'] ); ?>" alt="" loading="lazy"></a>
					<?php endforeach; ?>
				</div>
			<?php endif; ?>
		</section>
	<?php endif; ?>

	<div class="oq-grid">
		<section class="oq-card">
			<h2>O que está incluso</h2>
			<div class="oq-items">
				<?php foreach ( $items as $it ) : ?>
					<div class="oq-item">
						<div>
							<strong><?php echo esc_html( $it['name'] ); ?></strong>
							<?php if ( $it['desc'] ) : ?><p><?php echo nl2br( esc_html( $it['desc'] ) ); ?></p><?php endif; ?>
							<?php if ( $resale && $it['resale'] > $it['unit'] ) : ?>
								<div class="oq-resale">
									<span>Preço sugerido de revenda <b><?php echo esc_html( ap_money( $it['resale'] ) ); ?></b></span>
									<span>Seu lucro <b><?php echo esc_html( ap_money( $it['resale'] - $it['unit'] ) ); ?>/un.</b> · margem <b><?php echo (int) round( ( $it['resale'] - $it['unit'] ) / $it['resale'] * 100 ); ?>%</b></span>
									<span>No lote: <b><?php echo esc_html( ap_money( ( $it['resale'] - $it['unit'] ) * $it['qty'] ) ); ?></b> de lucro</span>
								</div>
							<?php endif; ?>
						</div>
						<div class="oq-item-n">
							<small><?php echo (int) $it['qty']; ?> × <?php echo esc_html( ap_money( $it['unit'] ) ); ?></small>
							<b><?php echo esc_html( ap_money( $it['unit'] * $it['qty'] ) ); ?></b>
						</div>
					</div>
				<?php endforeach; ?>
			</div>
			<div class="oq-sum">
				<?php if ( $q->discount > 0 ) : ?>
					<div><span>Subtotal</span><b><?php echo esc_html( ap_money( $q->subtotal ) ); ?></b></div>
					<div><span>Desconto</span><b>− <?php echo esc_html( ap_money( $q->discount ) ); ?></b></div>
				<?php endif; ?>
				<div class="oq-sum-total"><span>Total</span><b><?php echo esc_html( ap_money( $pickup['cartao'] ) ); ?></b></div>
				<div class="oq-sum-pix"><span>À vista no Pix (−<?php echo esc_html( $off ); ?>%)</span><b><?php echo esc_html( ap_money( $pickup['pix'] ) ); ?></b></div>
				<div><span><?php echo esc_html( ap_quote_pay_text() ); ?></span><b><?php echo (int) ap_setting( 'parcelas_max' ) > 1 ? esc_html( (int) ap_setting( 'parcelas_max' ) . 'x de ' . ap_money( $pickup['cartao'] / max( 1, (int) ap_setting( 'parcelas_max' ) ) ) ) : esc_html( ap_money( $pickup['cartao'] ) ); ?></b></div>
			</div>
			<ul class="oq-terms">
				<?php if ( $q->deadline_days ) : ?><li>Produção em até <strong><?php echo (int) $q->deadline_days; ?> dias</strong> após a confirmação do pagamento.</li><?php endif; ?>
				<li><?php echo esc_html( ap_setting( 'retirada_texto' ) ); ?></li>
				<?php if ( $q->freight > 0 ) : ?><li>Envio para o seu endereço: <strong><?php echo esc_html( ap_money( $q->freight ) ); ?></strong><?php echo $q->freight_label ? ' (' . esc_html( $q->freight_label ) . ')' : ''; ?>.</li><?php endif; ?>
				<?php if ( 'final' !== $q->audience && ap_setting( 'empresa_nota' ) ) : ?><li><?php echo esc_html( ap_setting( 'empresa_nota' ) ); ?></li><?php endif; ?>
				<?php if ( $q->valid_until ) : ?><li>Orçamento válido até <strong><?php echo esc_html( ap_date( $q->valid_until ) ); ?></strong>.</li><?php endif; ?>
			</ul>
		</section>

		<aside class="oq-card oq-pay">
			<?php if ( $done ) : ?>
				<h2>Orçamento aprovado ✓</h2>
				<p class="oq-muted">Aprovado em <?php echo esc_html( ap_date( $q->accepted_at ) ); ?>. <?php echo 'pago' === $q->status ? 'Pagamento confirmado: seu pedido já está na fila de produção.' : 'Falta só o pagamento.'; ?></p>
				<?php if ( $pending ) : ?><a class="oq-btn" href="<?php echo esc_url( ap_pay_url( $pending->id ) ); ?>">Pagar <?php echo esc_html( ap_money( $pending->amount ) ); ?></a><?php endif; ?>
				<a class="oq-btn oq-btn--ghost" href="<?php echo esc_url( ap_client_url( 'projeto', $q->project_id ) ); ?>">Acompanhar meu pedido</a>
			<?php elseif ( $expired ) : ?>
				<h2>Orçamento vencido</h2>
				<p class="oq-muted">Este orçamento valia até <?php echo esc_html( ap_date( $q->valid_until ) ); ?>. Chame no WhatsApp que a gente atualiza rapidinho.</p>
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
							<label class="oq-opt"><input type="radio" name="entrega" value="retirada"<?php checked( $old( 'entrega', 'retirada' ), 'retirada' ); ?>><span><strong>Retirar em Taubaté</strong><small>Grátis · combinamos o horário</small></span></label>
							<label class="oq-opt"><input type="radio" name="entrega" value="envio"<?php checked( $old( 'entrega' ), 'envio' ); ?>><span><strong>Receber em casa</strong><small>+ <?php echo esc_html( ap_money( $q->freight ) . ( $q->freight_label ? ' · ' . $q->freight_label : '' ) ); ?></small></span></label>
						</fieldset>
					<?php endif; ?>

					<fieldset class="oq-opts">
						<legend>Forma de pagamento</legend>
						<label class="oq-opt"><input type="radio" name="pagamento" value="pix"<?php checked( $old( 'pagamento', 'pix' ), 'pix' ); ?>><span><strong>Pix à vista <em>−<?php echo esc_html( $off ); ?>%</em></strong><small data-price="pix"><?php echo esc_html( ap_money( $pickup['pix'] ) ); ?></small></span></label>
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

					<label class="oq-check"><input type="checkbox" name="aceite" value="1" required><span>Li e aprovo o orçamento.</span></label>
					<button type="submit" class="oq-btn">Aprovar e pagar <span data-price="btn"><?php echo esc_html( ap_money( $pickup['pix'] ) ); ?></span></button>
					<p class="oq-muted oq-small oq-center">Pagamento seguro pela InfinitePay.</p>
				</form>
			<?php endif; ?>
		</aside>
	</div>
</main>

<footer class="oq-foot"><div class="oq-wrap"><?php echo ap_credit_html( 'dark' ); // phpcs:ignore ?> © <?php echo esc_html( gmdate( 'Y' ) . ' ' . ap_setting( 'empresa' ) ); ?> · <a href="<?php echo esc_url( ap_setting( 'site' ) ); ?>" target="_blank" rel="noopener"><?php echo esc_html( preg_replace( '#^https?://#', '', ap_setting( 'site' ) ) ); ?></a></div></footer>

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
<?php if ( $model ) : ?>
<script type="importmap">{"imports":{"three":"https://cdn.jsdelivr.net/npm/three@0.160.0/build/three.module.js","three/addons/":"https://cdn.jsdelivr.net/npm/three@0.160.0/examples/jsm/"}}</script>
<script type="module">
import * as THREE from 'three';
import { OrbitControls } from 'three/addons/controls/OrbitControls.js';
const box = document.querySelector('.oq-viewer');
const canvas = box.querySelector('canvas');
const renderer = new THREE.WebGLRenderer({ canvas, antialias: true, alpha: true });
renderer.setPixelRatio(Math.min(window.devicePixelRatio, 2));
const scene = new THREE.Scene();
const camera = new THREE.PerspectiveCamera(40, 1, 0.1, 10000);
scene.add(new THREE.HemisphereLight(0xffffff, 0x222222, 1.6));
const key = new THREE.DirectionalLight(0xffffff, 2.2); key.position.set(3, 5, 4); scene.add(key);
const rim = new THREE.DirectionalLight(0xffffff, 0.8); rim.position.set(-4, 2, -3); scene.add(rim);
const controls = new OrbitControls(camera, canvas);
controls.enableDamping = true; controls.autoRotate = true; controls.autoRotateSpeed = 1.4;
canvas.addEventListener('pointerdown', () => { controls.autoRotate = false; });
function size() { const w = box.clientWidth, h = box.clientHeight; renderer.setSize(w, h, false); camera.aspect = w / h; camera.updateProjectionMatrix(); }
window.addEventListener('resize', size); size();
const mat = new THREE.MeshStandardMaterial({ color: 0xe8e8e8, roughness: 0.55, metalness: 0.05 });
function frame(obj) {
	const b = new THREE.Box3().setFromObject(obj), c = b.getCenter(new THREE.Vector3()), s = b.getSize(new THREE.Vector3());
	obj.position.sub(c);
	const r = Math.max(s.x, s.y, s.z);
	camera.position.set(r * 1.1, r * 0.8, r * 1.4); camera.near = r / 100; camera.far = r * 100; camera.updateProjectionMatrix();
	controls.target.set(0, 0, 0); controls.update();
	scene.add(obj); box.querySelector('.oq-viewer-load').remove();
}
const url = box.dataset.model, ext = box.dataset.ext;
const fail = () => { box.querySelector('.oq-viewer-load').textContent = 'Não deu para carregar o modelo 3D.'; };
try {
	if (ext === 'stl') {
		const { STLLoader } = await import('three/addons/loaders/STLLoader.js');
		new STLLoader().load(url, g => { g.computeVertexNormals(); const m = new THREE.Mesh(g, mat); m.rotation.x = -Math.PI / 2; const w = new THREE.Group(); w.add(m); frame(w); }, undefined, fail);
	} else if (ext === '3mf') {
		const { ThreeMFLoader } = await import('three/addons/loaders/3MFLoader.js');
		new ThreeMFLoader().load(url, o => { o.rotation.x = -Math.PI / 2; o.traverse(ch => { if (ch.isMesh && (!ch.material || !ch.material.color)) ch.material = mat; }); const w = new THREE.Group(); w.add(o); frame(w); }, undefined, fail);
	} else if (ext === 'obj') {
		const { OBJLoader } = await import('three/addons/loaders/OBJLoader.js');
		new OBJLoader().load(url, o => { o.traverse(ch => { if (ch.isMesh) ch.material = mat; }); frame(o); }, undefined, fail);
	} else {
		const { GLTFLoader } = await import('three/addons/loaders/GLTFLoader.js');
		new GLTFLoader().load(url, g => frame(g.scene), undefined, fail);
	}
} catch (e) { fail(); }
(function loop() { requestAnimationFrame(loop); controls.update(); renderer.render(scene, camera); })();
</script>
<?php endif; ?>
</body>
</html>

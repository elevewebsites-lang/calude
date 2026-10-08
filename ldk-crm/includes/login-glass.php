<?php
/**
 * Moldura do login em vidro (usada pelo login e pelo código de acesso).
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function lk_glass_icon( $name ) {
	$p = array(
		'mail' => '<rect x="3" y="5" width="18" height="14" rx="2"/><path d="m3 7 9 6 9-6"/>',
		'lock' => '<rect x="5" y="11" width="14" height="10" rx="2"/><path d="M8 11V8a4 4 0 0 1 8 0v3"/>',
		'eye'  => '<path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/>',
	);
	return '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' . ( $p[ $name ] ?? '' ) . '</svg>';
}

function lk_glass_start( $gate = false ) {
	?>
<link rel="stylesheet" href="<?php echo esc_url( LK_URL . 'assets/login-glass.css?ver=' . LK_VERSION ); ?>">
<link rel="stylesheet" href="<?php echo esc_url( LK_URL . 'assets/season.css?ver=' . LK_VERSION ); ?>">
<script>(function(){try{var s=localStorage.getItem('lk-season');if(s==='halloween'||s==='natal'||s==='anonovo'){document.documentElement.setAttribute('data-season',s);}}catch(e){}})();</script>
<body class="lk lk-auth">
<div class="lgx<?php echo $gate ? ' lobby' : ''; ?>"<?php echo $gate ? ' data-gate data-endpoint="' . esc_url( rest_url( 'lk/v1/login-card' ) ) . '" data-preview="' . ( '1' === (string) lk_setting( 'login_card' ) ? 1 : 0 ) . '"' : ''; // phpcs:ignore WordPress.Security.EscapeOutput ?>>
	<i class="lg-blob lg-blob--1"></i><i class="lg-blob lg-blob--2"></i><i class="lg-blob lg-blob--3"></i><i class="lg-dots"></i>
	<div class="lg-wrap">
		<div class="lg-card">
			<div class="lg-logo"><?php echo lk_lobby_logo(); // phpcs:ignore WordPress.Security.EscapeOutput ?></div>
	<?php
}

function lk_glass_end( $script = '' ) {
	?>
		</div>
		<p class="lg-foot">© <?php echo esc_html( gmdate( 'Y' ) . ' ' . lk_setting( 'empresa' ) ); ?> · <?php echo lk_credit_html( 'dark' ); // phpcs:ignore ?></p>
	</div>
	<span class="lobby-loading" data-g-loading hidden><i></i>Abrindo o seu painel…</span>
</div>
<?php echo $script; // phpcs:ignore WordPress.Security.EscapeOutput ?>
<script>
(function () {
	var eye = document.querySelector('[data-lg-eye]');
	if (eye) eye.addEventListener('click', function () { var i = eye.parentNode.querySelector('input'); i.type = i.type === 'password' ? 'text' : 'password'; });
	var f = document.querySelector('[data-lg-form]'), b = document.querySelector('[data-lg-btn]');
	if (f && b && !f.hasAttribute('data-login-form')) f.addEventListener('submit', function () { b.classList.add('is-loading'); b.textContent = 'Entrando…'; });
})();
</script>
</body>
</html>
	<?php
}

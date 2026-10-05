<?php
/**
 * Tela de foco: relógio à esquerda, o que você escolheu à direita.
 * Fundo preto ou branco (botão no topo, fica lembrado) e o chat da equipe, que dá para ocultar.
 * Recebe $items, $cycle e $goal.
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
$cycles = lk_focus_cycles();
$cfg    = $cycles[ $cycle ];
$data   = array(
	'rest'  => esc_url_raw( rest_url( 'lk/v1/' ) ),
	'nonce' => wp_create_nonce( 'wp_rest' ),
	'cycle' => $cycle,
	'focus' => (int) $cfg[1],
	'short' => (int) $cfg[2],
	'long'  => (int) $cfg[3],
	'items' => $items,
	'today' => lk_focus_seconds_since( lk_today() ),
	'exit'  => lk_panel_url( 'foco' ),
	'key'   => md5( wp_json_encode( wp_list_pluck( $items, 'id' ) ) . $cycle ),
);
$tasks_total = 0;
$tasks_done  = 0;
foreach ( $items as $it ) {
	if ( 'task' === $it['type'] ) {
		++$tasks_total;
		$tasks_done += $it['done'] ? 1 : 0;
	}
}
$first = $cfg[1] ? str_pad( (string) $cfg[1], 2, '0', STR_PAD_LEFT ) . ':00' : '00:00';
lk_head( 'Modo foco' );
?>
<body class="lk lk-focus">
<script>(function(){try{var t=localStorage.getItem('lk-focus-theme')||'escuro';document.body.setAttribute('data-fz-theme',t==='claro'?'claro':'escuro');if(localStorage.getItem('lk-focus-chat')==='0'){document.body.classList.add('fz-chat-off');}}catch(e){document.body.setAttribute('data-fz-theme','escuro');}})();</script>
<div class="fz" data-focus-app>
	<header class="fz-top">
		<div class="fz-brand">
			<span class="fz-badge"><?php echo lk_icon( 'alvo', 15 ); // phpcs:ignore ?>Modo foco</span>
			<?php if ( $goal ) : ?><span class="fz-goal" title="<?php echo esc_attr( $goal ); ?>"><?php echo esc_html( $goal ); ?></span><?php endif; ?>
		</div>
		<div class="fz-tools">
			<span class="fz-now" data-fz-now aria-label="Hora agora"></span>
			<button type="button" class="fz-icon" data-fz-theme-btn title="Fundo branco" aria-label="Trocar para fundo branco"><span class="fz-ic-sun"><?php echo lk_icon( 'sol', 18 ); // phpcs:ignore ?></span><span class="fz-ic-moon"><?php echo lk_icon( 'lua', 18 ); // phpcs:ignore ?></span></button>
			<button type="button" class="fz-icon" data-fz-chat-btn title="Ocultar o chat" aria-label="Ocultar o chat" aria-pressed="true"><?php echo lk_icon( 'chat', 18 ); // phpcs:ignore ?></button>
			<button type="button" class="fz-icon" data-fz-sound title="Som ao terminar cada ciclo" aria-label="Som ao terminar cada ciclo" aria-pressed="true"><?php echo lk_icon( 'som', 18 ); // phpcs:ignore ?></button>
			<button type="button" class="fz-icon fz-hide-sm" data-fz-full title="Tela cheia" aria-label="Tela cheia"><?php echo lk_icon( 'tela', 18 ); // phpcs:ignore ?></button>
			<button type="button" class="fz-exit" data-fz-exit>Sair<span class="fz-hide-sm"> do foco</span></button>
		</div>
	</header>

	<main class="fz-main">
		<section class="fz-clock" aria-label="Relógio do foco">
			<p class="fz-phase" data-fz-phase>Foco</p>
			<div class="fz-ring" data-fz-ring style="--p:0">
				<svg viewBox="0 0 120 120" aria-hidden="true"><circle class="fz-track" cx="60" cy="60" r="54"/><circle class="fz-bar" cx="60" cy="60" r="54"/></svg>
				<div class="fz-center">
					<div class="fz-time" data-fz-time role="timer" aria-live="off"><?php echo esc_html( $first ); ?></div>
					<p class="fz-meta" data-fz-meta></p>
				</div>
			</div>
			<div class="fz-controls">
				<button type="button" class="fz-btn fz-btn--main" data-fz-toggle>Começar</button>
				<button type="button" class="fz-btn" data-fz-skip<?php echo $cfg[1] ? '' : ' hidden'; ?>>Pular</button>
				<button type="button" class="fz-btn" data-fz-reset>Reiniciar</button>
			</div>
			<p class="fz-hint"><kbd>Espaço</kbd> pausa e continua</p>
		</section>

		<section class="fz-side" aria-label="O que você escolheu">
			<div class="fz-side-head">
				<h2>Agora</h2>
				<?php if ( $tasks_total ) : ?>
					<span class="fz-count" data-fz-count data-total="<?php echo (int) $tasks_total; ?>"><?php echo (int) $tasks_done; ?> de <?php echo (int) $tasks_total; ?> feitas</span>
				<?php endif; ?>
			</div>
			<?php if ( $tasks_total ) : ?>
				<div class="fz-progress" aria-hidden="true"><span data-fz-progress style="width:<?php echo (int) round( 100 * $tasks_done / $tasks_total ); ?>%"></span></div>
			<?php endif; ?>
			<div class="fz-list">
				<?php foreach ( $items as $i => $it ) : ?>
					<div class="fz-item<?php echo 0 === $i ? ' is-current' : ''; ?><?php echo $it['done'] ? ' is-done' : ''; ?>" data-fz-item="<?php echo (int) $i; ?>">
						<div class="fz-item-head">
							<?php if ( 'task' === $it['type'] ) : ?>
								<button type="button" class="fz-tick<?php echo $it['done'] ? ' is-done' : ''; ?>" data-fz-done="<?php echo (int) $it['id']; ?>" aria-label="Concluir: <?php echo esc_attr( $it['title'] ); ?>"></button>
							<?php else : ?>
								<span class="fz-dot" aria-hidden="true"></span>
							<?php endif; ?>
							<button type="button" class="fz-item-title" data-fz-pick="<?php echo (int) $i; ?>"><strong><?php echo esc_html( $it['title'] ); ?></strong><?php if ( $it['context'] ) : ?><small><?php echo esc_html( $it['context'] ); ?></small><?php endif; ?></button>
							<span class="fz-now-tag">agora</span>
							<?php if ( 'project' === $it['type'] ) : ?>
								<a class="fz-open" href="<?php echo esc_url( lk_panel_url( 'post', $it['id'] ) ); ?>" target="_blank" rel="noopener" title="Abrir o post em outra aba" aria-label="Abrir o post"><?php echo lk_icon( 'seta', 15 ); // phpcs:ignore ?></a>
							<?php endif; ?>
						</div>
						<?php if ( $it['tasks'] ) : ?>
							<ul class="fz-sub">
								<?php foreach ( $it['tasks'] as $st ) : ?>
									<li><button type="button" class="fz-tick fz-tick--sm" data-fz-done="<?php echo (int) $st['id']; ?>" aria-label="Concluir"></button><span><?php echo esc_html( $st['title'] ); ?></span></li>
								<?php endforeach; ?>
							</ul>
						<?php endif; ?>
					</div>
				<?php endforeach; ?>
			</div>
			<p class="fz-tip">Clique no nome para dizer em que está trabalhando. O tempo focado vai para o item marcado como <em>agora</em>.</p>
		</section>
	</main>
</div>
<?php
if ( lk_is_team() ) {
	echo lk_hub_html(); // phpcs:ignore WordPress.Security.EscapeOutput
}
?>
<script>window.LK = <?php echo wp_json_encode( array( 'rest' => esc_url_raw( rest_url( 'lk/v1/' ) ), 'nonce' => wp_create_nonce( 'wp_rest' ) ) ); ?>; window.LK_FOCUS = <?php echo wp_json_encode( $data ); ?>;</script>
<script src="<?php echo esc_url( LK_URL . 'assets/foco.js?ver=' . LK_VERSION ); ?>"></script>
<?php if ( lk_is_team() ) : ?><script src="<?php echo esc_url( LK_URL . 'assets/hub.js?ver=' . LK_VERSION ); ?>"></script><?php endif; ?>
</body>
</html>

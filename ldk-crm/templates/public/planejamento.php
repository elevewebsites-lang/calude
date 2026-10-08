<?php
/**
 * Planejamento do mês para a cliente: /planejamento/<token>/ (sem login; também aberto pela área da cliente).
 * Visual de proposta: capa, pontos importantes, datas do mês, calendário e cada post com 👍 aprovar / ✏️ ajustar.
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
$client = lk_get( 'clients', $plan->client_id );
$posts  = lk_plan_posts( $plan );
$open   = in_array( $plan->status, array( 'enviado' ), true );
$ym     = $plan->period;
$pend   = array_filter( $posts, function ( $p ) { return $p->stage === lk_stage_for( 'planejamento' ) && 'ok' !== $p->plan_status; } );
$ig     = $client ? lk_social_account( $client->id, 'instagram' ) : null;
$nets   = array();
$fmts   = array();
$days   = array();
foreach ( $posts as $p ) {
	foreach ( lk_manage_only() ? array() : lk_post_networks( $p ) as $n ) {
		$nets[ $n ] = lk_networks()[ $n ] ?? $n;
	}
	$fmts[ $p->format ] = ( $fmts[ $p->format ] ?? 0 ) + 1;
	$days[ (int) substr( (string) $p->scheduled_at, 8, 2 ) ][] = $p;
}
$destaques = lk_list( (string) $plan->destaques );
$consid    = array_filter( array_map( 'trim', preg_split( '/\r\n|\r|\n/', (string) $plan->consideracoes ) ) );
$mdates    = lk_month_dates( $ym );
$hol_by    = array();
$date_by   = array();
foreach ( $mdates as $md ) {
	if ( $md['day'] ) {
		if ( 'feriado' === $md['type'] ) {
			$hol_by[ $md['day'] ][] = $md['name'];
		} else {
			$date_by[ $md['day'] ][] = $md['name'];
		}
	}
}
$holidays = array_values( array_filter( $mdates, function ( $d ) { return 'feriado' === $d['type']; } ) );
$specials = array_values( array_filter( $mdates, function ( $d ) { return 'data' === $d['type']; } ) );
$camps    = array_values( array_filter( $mdates, function ( $d ) { return 'campanha' === $d['type']; } ) );
$first_dow = (int) gmdate( 'w', strtotime( $ym . '-01' ) );
$last_day  = (int) gmdate( 't', strtotime( $ym . '-01' ) );
lk_head( 'Planejamento · ' . lk_month_label( $ym ) . ' · ' . lk_client_label( $client ) );
?>
<body class="lk plx-page">
<div class="plx">
	<header class="plx-cover">
		<div class="plx-cover-in">
			<?php if ( lk_setting( 'logo' ) ) : ?><img class="plx-logo" src="<?php echo esc_url( lk_setting( 'logo' ) ); ?>" alt="<?php echo esc_attr( lk_setting( 'empresa' ) ); ?>"><?php endif; ?>
			<span class="plx-tag"># Planejamento de conteúdo</span>
			<button type="button" class="plx-print" onclick="window.print()">⬇ Salvar em PDF</button>
			<h1><?php echo esc_html( ucfirst( lk_month_label( $ym ) ) ); ?><i>.</i></h1>
			<p class="plx-client">
				<?php if ( $ig && $ig->avatar ) : ?><img src="<?php echo esc_url( $ig->avatar ); ?>" alt="" onerror="this.remove()"><?php endif; ?>
				<strong><?php echo esc_html( lk_client_label( $client ) ); ?></strong><?php echo $ig && $ig->username ? ' · @' . esc_html( $ig->username ) : ''; ?>
			</p>
			<?php if ( $plan->intro ) : ?><p class="plx-intro"><?php echo nl2br( esc_html( $plan->intro ) ); ?></p><?php endif; ?>
			<div class="plx-stats">
				<div><strong><?php echo count( $posts ); ?></strong><span>conteúdos</span></div>
				<?php foreach ( $fmts as $f => $n ) : ?><div><strong><?php echo (int) $n; ?></strong><span><?php echo esc_html( mb_strtolower( lk_formats()[ $f ] ?? $f ) ); ?></span></div><?php endforeach; ?>
				<?php if ( $nets ) : ?><div class="plx-nets"><span><?php echo esc_html( implode( ' · ', $nets ) ); ?></span></div><?php endif; ?>
			</div>
		</div>
	</header>

	<main class="plx-main">
		<?php if ( $msg ) : ?><div class="plx-msg"><?php echo esc_html( $msg ); ?></div><?php endif; ?>
		<?php if ( ! $open && ! $msg ) : ?>
			<div class="plx-msg"><?php echo 'aprovado' === $plan->status ? 'Planejamento aprovado ✓ Nossa equipe já está criando as artes. Você recebe cada uma para aprovar.' : ( 'ajustes' === $plan->status ? 'Recebemos os seus comentários. Estamos ajustando e mandamos de novo para você.' : 'Este planejamento ainda está com a nossa equipe.' ); ?></div>
		<?php endif; ?>

		<?php if ( $destaques ) : ?>
			<section class="plx-sec">
				<span class="plx-eye">(01) O foco do mês</span>
				<h2>Pontos <mark>importantes</mark></h2>
				<ul class="plx-checks">
					<?php foreach ( $destaques as $d ) : ?><li><i aria-hidden="true">✓</i><span><?php echo esc_html( $d ); ?></span></li><?php endforeach; ?>
				</ul>
			</section>
		<?php endif; ?>

		<?php if ( $holidays || $specials || $camps ) : ?>
			<section class="plx-sec">
				<span class="plx-eye">(02) Calendário de datas</span>
				<h2>Feriados e <mark>datas importantes</mark></h2>
				<?php if ( $holidays ) : ?>
					<div class="plx-hols">
						<?php foreach ( $holidays as $h ) : ?>
							<div class="plx-hol"><b><?php echo esc_html( (string) $h['day'] ); ?></b><span><i>Feriado<?php echo $h['sub'] ? ' · ' . esc_html( $h['sub'] ) : ''; ?></i><strong><?php echo esc_html( $h['name'] ); ?></strong><small><?php echo esc_html( lk_dow_short( $h['date'] ) ); ?> · atenção ao horário de funcionamento e às entregas</small></span></div>
						<?php endforeach; ?>
					</div>
				<?php endif; ?>
				<?php if ( $specials ) : ?>
					<div class="plx-chips"><?php foreach ( $specials as $sp ) : ?><span><b><?php echo esc_html( (string) $sp['day'] ); ?></b> <?php echo esc_html( $sp['name'] ); ?></span><?php endforeach; ?></div>
				<?php endif; ?>
				<?php if ( $camps ) : ?>
					<div class="plx-chips plx-chips--camp"><?php foreach ( $camps as $cp ) : ?><span>🎗️ <?php echo esc_html( $cp['name'] ); ?></span><?php endforeach; ?></div>
				<?php endif; ?>
			</section>
		<?php endif; ?>

		<?php if ( $consid ) : ?>
			<section class="plx-sec">
				<span class="plx-eye">(03) Contexto</span>
				<h2>Ações e <mark>observações do mês</mark></h2>
				<ul class="plx-dates">
					<?php foreach ( $consid as $c ) : ?>
						<?php if ( preg_match( '/:\s*$/', $c ) ) : ?><li class="plx-dates-h"><?php echo esc_html( rtrim( $c, ': ' ) ); ?></li><?php continue; endif; ?>
						<li><?php echo esc_html( $c ); ?></li>
					<?php endforeach; ?>
				</ul>
			</section>
		<?php endif; ?>

		<section class="plx-sec">
			<span class="plx-eye">(04) Calendário</span>
			<h2>O mês <mark>em um olhar</mark></h2>
			<div class="plx-cal">
				<?php foreach ( array( 'dom', 'seg', 'ter', 'qua', 'qui', 'sex', 'sáb' ) as $w ) : ?><b><?php echo esc_html( $w ); ?></b><?php endforeach; ?>
				<?php for ( $i = 0; $i < $first_dow; $i++ ) : ?><span class="is-empty"></span><?php endfor; ?>
				<?php for ( $d = 1; $d <= $last_day; $d++ ) : ?>
					<?php $hl = ! empty( $hol_by[ $d ] ) ? ' is-hol' : ( ! empty( $date_by[ $d ] ) ? ' is-date' : '' ); $tt = implode( ' · ', array_merge( $hol_by[ $d ] ?? array(), $date_by[ $d ] ?? array() ) ); ?>
					<?php if ( ! empty( $days[ $d ] ) ) : ?>
						<a class="has<?php echo esc_attr( $hl ); ?>" title="<?php echo esc_attr( $tt ); ?>" href="#post-<?php echo (int) $days[ $d ][0]->id; ?>"><?php echo (int) $d; ?><i><?php echo count( $days[ $d ] ); ?></i></a>
					<?php else : ?>
						<span class="<?php echo esc_attr( trim( $hl ) ); ?>" title="<?php echo esc_attr( $tt ); ?>"><?php echo (int) $d; ?></span>
					<?php endif; ?>
				<?php endfor; ?>
			</div>
			<?php if ( $holidays ) : ?><p class="plx-legend"><i class="is-hol"></i> feriado <i class="is-date"></i> data comemorativa <i class="is-post"></i> post planejado</p><?php endif; ?>
		</section>

		<form method="post" class="plx-form" data-plx>
			<?php wp_nonce_field( 'lk_plan_' . $plan->id ); ?>
			<section class="plx-sec">
				<span class="plx-eye">(05) Conteúdos</span>
				<h2>Post a <mark>post</mark></h2>
				<?php if ( $open ) : ?><p class="plx-help">Em cada post: <strong>👍 Aprovar</strong> ou <strong>✏️ Ajustar</strong> (conte o que mudar). Os aprovados já vão para a criação da arte.</p><?php endif; ?>
				<ol class="plx-list">
					<?php foreach ( $posts as $i => $p ) : ?>
						<?php
						$notes  = lk_rows( 'post_comments', 'post_id = %d AND target = %s AND internal = 0', array( $p->id, 'planejamento' ), 'id' );
						$locked = 'ok' === $p->plan_status || $p->stage !== lk_stage_for( 'planejamento' );
						$can    = $open && ! $locked && ! $msg;
						?>
						<li class="plx-post<?php echo $locked ? ' is-ok' : ''; ?>" id="post-<?php echo (int) $p->id; ?>" data-post>
							<div class="plx-when"><b><?php echo esc_html( lk_date( $p->scheduled_at, 'd' ) ); ?></b><span><?php echo esc_html( lk_month_name( (int) gmdate( 'n', strtotime( (string) $p->scheduled_at ) ) ) ); ?></span><small><?php echo esc_html( lk_dow_short( $p->scheduled_at ) . ' · ' . substr( (string) $p->scheduled_at, 11, 5 ) ); ?></small></div>
							<div class="plx-body">
								<div class="plx-meta"><span class="plx-fmt"><?php echo esc_html( lk_formats()[ $p->format ] ?? '' ); ?></span><?php foreach ( lk_post_networks( $p ) as $n ) : ?><span class="plx-net plx-net--<?php echo esc_attr( $n ); ?>"><?php echo esc_html( lk_networks()[ $n ] ?? $n ); ?></span><?php endforeach; ?><?php $hd = $p->scheduled_at ? lk_is_holiday( $p->scheduled_at ) : null; if ( $hd ) : ?><span class="plx-holb">🔴 <?php echo esc_html( $hd['name'] ); ?></span><?php endif; ?><?php if ( $locked ) : ?><span class="plx-okb">✓ aprovado</span><?php endif; ?></div>
								<h3><?php echo esc_html( ( $i + 1 ) . '. ' . $p->title ); ?></h3>
								<?php if ( $p->idea ) : ?><div class="plx-idea"><span>💡 A ideia</span><p><?php echo nl2br( esc_html( $p->idea ) ); ?></p></div><?php endif; ?>
								<div class="plx-cap"><?php echo $p->caption ? nl2br( esc_html( lk_post_final_caption( $p ) ) ) : '<em>Legenda em produção.</em>'; ?></div>
								<?php foreach ( $notes as $n ) : ?><p class="plx-note"><strong><?php echo $n->from_client ? 'Você' : esc_html( lk_setting( 'empresa' ) ); ?>:</strong> <?php echo esc_html( $n->body ); ?></p><?php endforeach; ?>
								<?php if ( $can ) : ?>
									<div class="plx-dec" role="radiogroup" aria-label="Sua decisão sobre este post">
										<label class="plx-ok"><input type="radio" name="dec[<?php echo (int) $p->id; ?>]" value="ok" data-dec><span>👍 Aprovar</span></label>
										<label class="plx-adj"><input type="radio" name="dec[<?php echo (int) $p->id; ?>]" value="ajuste" data-dec><span>✏️ Ajustar</span></label>
									</div>
									<textarea class="plx-cm" name="comentario[<?php echo (int) $p->id; ?>]" rows="3" placeholder="O que você quer mudar neste post?" hidden></textarea>
								<?php endif; ?>
							</div>
						</li>
					<?php endforeach; ?>
				</ol>
			</section>

			<?php if ( $open && ! $msg && $pend ) : ?>
				<section class="plx-sec plx-end">
					<label class="plx-general"><span>Algum comentário geral? (opcional)</span><textarea name="geral" rows="3" placeholder="Datas especiais, promoções, algo que não pode faltar…"></textarea></label>
				</section>
				<div class="plx-bar">
					<span class="plx-count" data-count><?php echo count( $pend ); ?> para responder</span>
					<button type="submit" name="decisao" value="aprovar" class="plx-all" data-all>👍 Aprovar tudo</button>
					<button type="submit" name="decisao" value="responder" class="plx-send" data-send>Enviar respostas</button>
				</div>
			<?php endif; ?>
		</form>
	</main>
	<footer class="plx-foot"><?php echo lk_credit_html( 'dark' ); // phpcs:ignore ?></footer>
</div>
<script>
(function () {
	var f = document.querySelector('[data-plx]'); if (!f) return;
	var posts = f.querySelectorAll('[data-post]'), count = f.querySelector('[data-count]');
	function upd() {
		var left = 0, adj = 0;
		posts.forEach(function (li) {
			var r = li.querySelector('[data-dec]:checked'), cm = li.querySelector('.plx-cm');
			if (!li.querySelector('[data-dec]')) return;
			if (!r) left++;
			li.classList.toggle('is-picked-ok', !!r && r.value === 'ok');
			li.classList.toggle('is-picked-adj', !!r && r.value === 'ajuste');
			if (cm) cm.hidden = !(r && r.value === 'ajuste');
			if (r && r.value === 'ajuste') adj++;
		});
		if (count) count.textContent = left ? left + ' sem resposta' : (adj ? adj + ' com ajuste' : 'Tudo aprovado 👍');
	}
	f.addEventListener('change', function (e) { if (e.target.matches('[data-dec]')) { upd(); var cm = e.target.closest('[data-post]').querySelector('.plx-cm'); if (e.target.value === 'ajuste' && cm) cm.focus(); } });
	f.addEventListener('submit', function (e) {
		var d = e.submitter && e.submitter.value;
		if (d === 'aprovar') { if (!confirm('Aprovar todos os posts que faltam?')) e.preventDefault(); return; }
		var miss = [];
		posts.forEach(function (li) { var r = li.querySelector('[data-dec]:checked'); if (r && r.value === 'ajuste' && !li.querySelector('.plx-cm').value.trim()) miss.push(li); });
		if (miss.length) { e.preventDefault(); alert('Conte o que mudar nos posts marcados com ✏️ Ajustar.'); miss[0].querySelector('.plx-cm').focus(); return; }
		var none = Array.prototype.every.call(posts, function (li) { return !li.querySelector('[data-dec]') || !li.querySelector('[data-dec]:checked'); });
		if (none) { e.preventDefault(); alert('Marque 👍 ou ✏️ nos posts (ou use "Aprovar tudo").'); return; }
		var left = Array.prototype.filter.call(posts, function (li) { return li.querySelector('[data-dec]') && !li.querySelector('[data-dec]:checked'); }).length;
		if (left && !confirm(left + ' post(s) sem resposta serão considerados aprovados. Enviar?')) e.preventDefault();
	});
	upd();
})();
</script>
</body>
</html>

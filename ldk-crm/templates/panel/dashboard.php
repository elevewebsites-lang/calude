<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
$today  = lk_today();
$me     = get_current_user_id();
$fin    = lk_can( 'financeiro' );
$stages = lk_stages();
$open   = lk_posts( 'p.stage <> %s', array( lk_stage_for( 'publicado' ) ), 'p.scheduled_at' );
$mine   = array();
$late   = array();
$wait   = array();
$by     = array_fill_keys( array_keys( $stages ), 0 );
foreach ( $open as $p ) {
	if ( isset( $by[ $p->stage ] ) ) {
		$by[ $p->stage ]++;
	}
	if ( lk_post_owner( $p ) === $me && $p->stage !== lk_stage_for( 'agendado' ) ) {
		$mine[] = $p;
	}
	if ( lk_post_late( $p ) ) {
		$late[] = $p;
	}
	if ( $p->stage === lk_stage_for( 'aprovacao' ) ) {
		$wait[] = $p;
	}
}
usort(
	$mine,
	function ( $a, $b ) {
		$x = lk_post_deadline( $a ) ? lk_post_deadline( $a ) : '9999';
		$y = lk_post_deadline( $b ) ? lk_post_deadline( $b ) : '9999';
		return strcmp( $x, $y );
	}
);
$today_posts = lk_posts( 'DATE(p.scheduled_at) = %s', array( $today ), 'p.scheduled_at' );
$where = "t.status <> 'done' AND t.due_date IS NOT NULL AND t.due_date <= %s AND t.assignee = %d";
$tasks = lk_tasks( $where, array( $today, $me ), "t.due_date, FIELD(t.priority, 'urgente', 'alta', 'normal', 'baixa'), t.id" );
$pend  = $fin ? lk_billing_pending() : array();
$nocon = array_filter( lk_clients(), function ( $c ) { return ! isset( lk_social_accounts( $c->id )['instagram'] ); } );

// Gráficos: publicações 14 dias atrás → 14 dias à frente, tarefas concluídas (7 dias) e posts por etapa.
$win_from = gmdate( 'Y-m-d', strtotime( $today . ' -14 day' ) );
$win_to   = gmdate( 'Y-m-d', strtotime( $today . ' +14 day' ) );
$win      = lk_posts( 'p.scheduled_at BETWEEN %s AND %s', array( $win_from . ' 00:00:00', $win_to . ' 23:59:59' ), 'p.scheduled_at' );
$ser_done = array_fill( 0, 29, 0 );
$ser_plan = array_fill( 0, 29, 0 );
$labels   = array();
for ( $i = 0; $i < 29; $i++ ) {
	$d        = gmdate( 'Y-m-d', strtotime( $win_from . ' +' . $i . ' day' ) );
	$labels[] = ( 14 === $i ) ? 'hoje' : ( 0 === $i % 7 ? gmdate( 'd/m', strtotime( $d ) ) : '' );
}
foreach ( $win as $wp ) {
	$i = (int) round( ( strtotime( substr( $wp->scheduled_at, 0, 10 ) ) - strtotime( $win_from ) ) / DAY_IN_SECONDS );
	if ( $i >= 0 && $i < 29 ) {
		if ( $wp->stage === lk_stage_for( 'publicado' ) ) {
			$ser_done[ $i ]++;
		} else {
			$ser_plan[ $i ]++;
		}
	}
}
$done_week = array();
for ( $i = 6; $i >= 0; $i-- ) {
	$d               = gmdate( 'Y-m-d', strtotime( $today . ' -' . $i . ' day' ) );
	$done_week[ $d ] = array( lk_dow_short( $d ), 0 );
}
foreach ( lk_tasks( "t.status = 'done' AND t.done_at >= %s AND t.assignee = %d", array( gmdate( 'Y-m-d', strtotime( $today . ' -6 day' ) ) . ' 00:00:00', $me ), 't.done_at' ) as $dt ) {
	$k = substr( (string) $dt->done_at, 0, 10 );
	if ( isset( $done_week[ $k ] ) ) {
		$done_week[ $k ][1]++;
	}
}
$month_posts = lk_posts( 'DATE_FORMAT(p.scheduled_at, %s) = %s', array( '%Y-%m', substr( $today, 0, 7 ) ) );
$month_done  = count( array_filter( $month_posts, function ( $mp ) { return $mp->stage === lk_stage_for( 'publicado' ); } ) );
$month_pct   = $month_posts ? (int) round( 100 * $month_done / count( $month_posts ) ) : 0;
$slice_cls   = array( 'a', 'b', 'c', 'd', 'e', 'f' );
$slices      = array();
$si          = 0;
foreach ( $stages as $sk => $sn ) {
	$slices[] = array( $sn, (int) ( $by[ $sk ] ?? 0 ), $slice_cls[ $si % 6 ] );
	$si++;
}
$me_user   = wp_get_current_user();
$last_cli  = array_slice( array_reverse( lk_clients() ), 0, 5 );
$game_on   = function_exists( 'lk_game_on' ) && lk_game_on();
$earned    = $game_on ? lk_game_earned( $me ) : 0;
$glv       = $game_on ? lk_game_level( $earned ) : null;
$gnext     = $game_on ? lk_game_next_level( $earned ) : null;
$gpct      = $game_on && $gnext && $glv ? min( 100, max( 0, round( ( $earned - $glv['min'] ) / max( 1, $gnext['min'] - $glv['min'] ) * 100 ) ) ) : 100;
$todo_n    = count( $mine ) + count( $tasks );
$hour  = (int) current_time( 'G' );
$hello = $hour < 12 ? 'Bom dia' : ( $hour < 18 ? 'Boa tarde' : 'Boa noite' );
$name  = wp_get_current_user()->first_name ? wp_get_current_user()->first_name : wp_get_current_user()->display_name;
$refresh = '<button type="button" class="btn btn--ghost" data-open="dash-custom" title="Escolher o que aparece no dashboard">' . lk_icon( 'config', 16 ) . '<span>Personalizar</span></button><button type="button" class="btn btn--ghost dash-refresh" data-dash-refresh title="Atualizar os números e as listas do dashboard (também atualiza sozinho a cada 3 minutos)">' . lk_icon( 'atualizar', 16 ) . '<span>Atualizar</span></button>';
lk_panel_start( $hello . ', ' . $name, 'dashboard', $refresh . '<a class="btn btn--primary" href="' . esc_url( lk_panel_url( 'conteudo', 0, array( 'abrir' => 'novo-post' ) ) ) . '">' . lk_icon( 'mais', 16 ) . '<span>Post</span></a>' );
$mail_fail = lk_is_admin() ? get_option( 'lk_2fa_mail_fail' ) : '';
?>
<?php if ( $mail_fail && strtotime( $mail_fail ) > strtotime( lk_now() ) - 3 * DAY_IN_SECONDS ) : ?>
	<div class="flash flash--erro">O painel não conseguiu mandar o código de acesso para alguém da equipe (<?php echo esc_html( lk_ago( $mail_fail ) ); ?>), então a pessoa não conseguiu entrar. Confira o SMTP e use "Enviar e-mail de teste" em <a href="<?php echo esc_url( lk_panel_url( 'config' ) ); ?>#email">Configurações → E-mail</a>. Enquanto isso, dá para desligar a "Verificação em duas etapas" lá mesmo.</div>
<?php endif; ?>
<?php if ( lk_changelog_unseen() ) : ?>
	<a class="news-banner" href="<?php echo esc_url( lk_panel_url( 'novidades' ) ); ?>"><span>✨</span><strong>Novidades da versão <?php echo esc_html( LK_VERSION ); ?></strong><small><?php echo esc_html( lk_changelog()[ LK_VERSION ]['title'] ?? '' ); ?></small><em>ver o que mudou →</em></a>
<?php endif; ?>
<p class="dash-stamp muted small" data-dash-stamp aria-live="polite"></p>
<?php
$W = array();
$vis = array();
foreach ( array_keys( lk_dash_zones() ) as $zn ) {
	foreach ( lk_dash_zone( $zn ) as $zid ) {
		$vis[ $zid ] = true;
	}
}
?>
<?php if ( isset( $vis['hero'] ) ) : ob_start(); ?>
	<section class="dsh-hero" data-dash="hero">
		<div class="dsh-hero-txt">
			<h2><?php echo esc_html( $hello . ', ' . $name ); ?>! 👋</h2>
			<p><?php echo $todo_n ? 'Você tem <strong>' . (int) $todo_n . ' ' . ( 1 === $todo_n ? 'item' : 'itens' ) . '</strong> na sua fila' . ( $today_posts ? ' e <strong>' . count( $today_posts ) . '</strong> publicação(ões) hoje' : '' ) . '.' : 'Nada pendente com você agora' . ( $today_posts ? ', e <strong>' . count( $today_posts ) . '</strong> publicação(ões) hoje' : '' ) . '. ✨'; // phpcs:ignore ?></p>
			<div class="dsh-prog"><span>Mês de <?php echo esc_html( lk_month_label( substr( $today, 0, 7 ) ) ); ?>: <strong><?php echo (int) $month_pct; ?>%</strong> publicado (<?php echo (int) $month_done; ?>/<?php echo count( $month_posts ); ?>)</span><i><b style="width:<?php echo (int) $month_pct; ?>%"></b></i></div>
		</div>
		<svg class="dsh-hero-art" viewBox="0 0 220 150" aria-hidden="true"><rect x="22" y="30" width="130" height="86" rx="12" class="a1"/><rect x="34" y="44" width="62" height="8" rx="4" class="a2"/><rect x="34" y="60" width="96" height="6" rx="3" class="a3"/><rect x="34" y="72" width="80" height="6" rx="3" class="a3"/><path d="M34 104 L58 88 L76 98 L102 78 L128 92" class="a4" fill="none"/><circle cx="170" cy="52" r="26" class="a5"/><path d="M158 52 l9 9 l16 -18" class="a6" fill="none"/><rect x="150" y="86" width="52" height="30" rx="10" class="a2"/><circle cx="164" cy="101" r="5" class="a1"/><rect x="174" y="97" width="22" height="5" rx="2.5" class="a3"/></svg>
	</section>
<?php $W['hero'] = ob_get_clean(); endif; ?>
<?php if ( isset( $vis['stats'] ) ) : ob_start(); ?>
	<section class="dsh-stats" data-dash="stats">
		<a class="dsh-stat dsh-stat--a" href="<?php echo esc_url( lk_panel_url( 'conteudo', 0, array( 'meus' => 1, 'ver' => 'lista' ) ) ); ?>"><span class="dsh-ic"><?php echo lk_icon( 'alvo', 20 ); // phpcs:ignore ?></span><strong><?php echo count( $mine ); ?></strong><span>Comigo agora</span><small>posts na minha etapa</small></a>
		<div class="dsh-stat dsh-stat--b"><span class="dsh-ic"><?php echo lk_icon( 'relogio', 20 ); // phpcs:ignore ?></span><strong><?php echo count( $wait ); ?></strong><span>Aguardando cliente</span><small>em aprovação</small></div>
		<div class="dsh-stat dsh-stat--c<?php echo $late ? ' is-alert' : ''; ?>"><span class="dsh-ic"><?php echo lk_icon( 'sino', 20 ); // phpcs:ignore ?></span><strong><?php echo count( $late ); ?></strong><span>Atrasados</span><small>passou da data e não está agendado</small></div>
		<?php if ( $fin ) : ?>
			<a class="dsh-stat dsh-stat--d" href="<?php echo esc_url( lk_panel_url( 'cobrancas' ) ); ?>"><span class="dsh-ic"><?php echo lk_icon( 'financeiro', 20 ); // phpcs:ignore ?></span><strong class="money"><?php echo esc_html( lk_money( lk_billing_mrr() ) ); ?></strong><span>Recorrente</span><small><?php echo count( $pend ); ?> cobrança(s) pendente(s)</small></a>
		<?php else : ?>
			<div class="dsh-stat dsh-stat--d"><span class="dsh-ic"><?php echo lk_icon( 'check', 20 ); // phpcs:ignore ?></span><strong><?php echo count( $today_posts ); ?></strong><span>Hoje</span><small>publicações</small></div>
		<?php endif; ?>
	</section>
<?php $W['stats'] = ob_get_clean(); endif; ?>
<?php if ( isset( $vis['chart'] ) ) : ob_start(); ?>
	<section class="dsh-card" data-dash="chart">
		<div class="dsh-card-head"><h3>Publicações</h3><span class="dsh-legend"><i class="a"></i> publicados <i class="b"></i> planejados/agendados</span><span class="dsh-range">14 dias atrás → 14 dias à frente</span></div>
		<?php echo lk_dash_line( array( array( 'name' => 'Publicados', 'values' => $ser_done, 'class' => 'a' ), array( 'name' => 'Planejados', 'values' => $ser_plan, 'class' => 'b' ) ), $labels, 14 ); // phpcs:ignore ?>
	</section>
<?php $W['chart'] = ob_get_clean(); endif; ?>
<?php if ( isset( $vis['etapas'] ) ) : ob_start(); ?>
		<section class="dsh-card" data-dash="etapas">
			<div class="dsh-card-head"><h3>Posts por etapa</h3><a class="small" href="<?php echo esc_url( lk_panel_url( 'conteudo', 0, array( 'ver' => 'kanban' ) ) ); ?>">Kanban</a></div>
			<div class="dsh-donut-wrap">
				<?php echo lk_dash_donut( $slices, 'em aberto' ); // phpcs:ignore ?>
				<ul class="dsh-keys"><?php foreach ( $slices as $sl ) : ?><li><i class="seg-<?php echo esc_attr( $sl[2] ); ?>"></i><span><?php echo esc_html( $sl[0] ); ?></span><b><?php echo (int) $sl[1]; ?></b></li><?php endforeach; ?></ul>
			</div>
		</section>
<?php $W['etapas'] = ob_get_clean(); endif; ?>
<?php if ( isset( $vis['tarefas_semana'] ) ) : ob_start(); ?>
		<section class="dsh-card" data-dash="tarefas_semana">
			<div class="dsh-card-head"><h3>Minhas tarefas</h3><span class="dsh-range">concluídas nos últimos 7 dias</span></div>
			<?php echo lk_dash_bars( array_values( $done_week ), 'a' ); // phpcs:ignore ?>
		</section>
<?php $W['tarefas_semana'] = ob_get_clean(); endif; ?>
<?php if ( isset( $vis['comigo'] ) ) : ob_start(); ?>
	<section class="card">
		<div class="card-head"><h3>Comigo agora</h3></div>
		<?php if ( ! $mine && ! $tasks ) : ?><p class="muted small">Nada pendente com você. ✨</p><?php endif; ?>
		<ul class="mini-list">
			<?php foreach ( array_slice( $mine, 0, 10 ) as $p ) : ?>
				<li><a href="<?php echo esc_url( lk_panel_url( 'post', $p->id ) ); ?>"><strong><?php echo esc_html( $p->title ); ?></strong><small><?php echo esc_html( lk_post_client_label( $p ) . ' · ' . ( $stages[ $p->stage ] ?? '' ) ); ?> <?php echo lk_post_deadline( $p ) ? lk_deadline_badge( $p ) : ( $p->scheduled_at ? lk_due_badge( substr( $p->scheduled_at, 0, 10 ) ) : '' ); // phpcs:ignore ?></small></a></li>
			<?php endforeach; ?>
			<?php foreach ( array_slice( $tasks, 0, 6 ) as $t ) : ?>
				<li><a href="<?php echo esc_url( lk_panel_url( 'tarefa', $t->id ) ); ?>"><strong><?php echo esc_html( $t->title ); ?></strong><small>tarefa <?php echo lk_due_badge( $t->due_date ); // phpcs:ignore ?></small></a></li>
			<?php endforeach; ?>
		</ul>
	</section>
<?php $W['comigo'] = ob_get_clean(); endif; ?>
<?php if ( isset( $vis['hoje'] ) ) : ob_start(); ?>
	<section class="card">
		<div class="card-head"><h3>Publicações de hoje</h3></div>
		<?php if ( ! $today_posts ) : ?><p class="muted small">Nada programado para hoje.</p><?php endif; ?>
		<ul class="mini-list">
			<?php foreach ( $today_posts as $p ) : ?>
				<li><a href="<?php echo esc_url( lk_panel_url( 'post', $p->id ) ); ?>"><strong><?php echo esc_html( lk_date( $p->scheduled_at, 'H:i' ) . ' · ' . $p->title ); ?></strong><small><?php echo esc_html( lk_post_client_label( $p ) . ' · ' . ( $stages[ $p->stage ] ?? '' ) ); ?><?php echo $p->publish_error ? ' · erro ao publicar' : ''; ?></small></a></li>
			<?php endforeach; ?>
		</ul>
	</section>
<?php $W['hoje'] = ob_get_clean(); endif; ?>
<?php if ( isset( $vis['atrasados'] ) ) : ob_start(); ?>
	<?php if ( $late ) : ?>
		<section class="card">
			<div class="card-head"><h3>Atrasados</h3><a class="small" href="<?php echo esc_url( lk_panel_url( 'time' ) ); ?>">ver equipe</a></div>
			<ul class="mini-list">
				<?php foreach ( array_slice( $late, 0, 8 ) as $p ) : ?><?php $o = get_userdata( lk_post_owner( $p ) ); ?>
					<li><a href="<?php echo esc_url( lk_panel_url( 'post', $p->id ) ); ?>"><strong><?php echo esc_html( $p->title ); ?></strong><small><?php echo esc_html( lk_post_client_label( $p ) . ' · com ' . ( $o ? $o->display_name : 'ninguém' ) ); ?></small></a></li>
				<?php endforeach; ?>
			</ul>
		</section>
	<?php endif; ?>
<?php $W['atrasados'] = ob_get_clean(); endif; ?>
<?php if ( isset( $vis['aguardando'] ) ) : ob_start(); ?>
	<?php if ( $wait ) : ?>
		<section class="card">
			<div class="card-head"><h3>Aguardando o cliente</h3></div>
			<ul class="mini-list">
				<?php foreach ( array_slice( $wait, 0, 8 ) as $p ) : ?>
					<li><a href="<?php echo esc_url( lk_panel_url( 'post', $p->id ) ); ?>"><strong><?php echo esc_html( $p->title ); ?></strong><small><?php echo esc_html( lk_post_client_label( $p ) . ( $p->sent_at ? ' · enviado ' . lk_ago( $p->sent_at ) : ' · ainda não enviado' ) ); ?></small></a></li>
				<?php endforeach; ?>
			</ul>
		</section>
	<?php endif; ?>
<?php $W['aguardando'] = ob_get_clean(); endif; ?>
<?php if ( isset( $vis['cobrancas'] ) ) : ob_start(); ?>
	<?php if ( $pend ) : ?>
		<section class="card">
			<div class="card-head"><h3>Cobranças</h3><a class="small" href="<?php echo esc_url( lk_panel_url( 'cobrancas' ) ); ?>">cobrar</a></div>
			<ul class="mini-list">
				<?php foreach ( array_slice( $pend, 0, 6 ) as $t ) : ?><li><a href="<?php echo esc_url( lk_panel_url( 'cobrancas' ) ); ?>"><strong><?php echo esc_html( $t->description ); ?></strong><small><?php echo esc_html( lk_money( $t->amount ) ); ?> <?php echo lk_due_badge( $t->due_date ); // phpcs:ignore ?></small></a></li><?php endforeach; ?>
			</ul>
		</section>
	<?php endif; ?>
<?php $W['cobrancas'] = ob_get_clean(); endif; ?>
<?php if ( isset( $vis['instagram'] ) ) : ob_start(); ?>
	<?php if ( $nocon && lk_can( 'clientes' ) ) : ?>
		<section class="card">
			<div class="card-head"><h3>Instagram não vinculado</h3><a class="small" href="<?php echo esc_url( lk_panel_url( 'redes' ) ); ?>">passo a passo</a></div>
			<ul class="mini-list"><?php foreach ( array_slice( $nocon, 0, 8 ) as $c ) : ?><li><a href="<?php echo esc_url( lk_panel_url( 'cliente', $c->id ) ); ?>#redes"><strong><?php echo esc_html( lk_client_label( $c ) ); ?></strong><small>conectar para agendar e ter relatório</small></a></li><?php endforeach; ?></ul>
		</section>
	<?php endif; ?>
<?php $W['instagram'] = ob_get_clean(); endif; ?>
<?php if ( isset( $vis['chat'] ) ) : ob_start(); ?>
	<section class="card" data-teamchat data-channel="geral">
		<div class="card-head"><h3>Chat da equipe</h3><a class="small" href="<?php echo esc_url( lk_panel_url( 'chat' ) ); ?>">abrir</a></div>
		<div class="chat-list chat-list--mini" data-chat-list></div>
		<form class="chat-form"><textarea rows="1" placeholder="Mensagem para a equipe…"></textarea><button type="submit" class="btn btn--primary btn--sm">Enviar</button></form>
	</section>
<?php $W['chat'] = ob_get_clean(); endif; ?>
<?php if ( isset( $vis['perfil'] ) ) : ob_start(); ?>
	<section class="dsh-card dsh-profile">
		<div class="dsh-av"><?php echo esc_html( lk_initials( $me_user->display_name ) ); ?></div>
		<h3><?php echo esc_html( $me_user->display_name ); ?></h3>
		<p class="muted small"><?php echo esc_html( lk_user_role_label( $me ) ); ?></p>
		<?php if ( $game_on && $glv ) : ?>
			<a class="dsh-level" href="<?php echo esc_url( lk_panel_url( 'ranking' ) ); ?>"><span><b><?php echo esc_html( $glv['name'] ); ?></b> · <?php echo (int) $earned; ?> pts</span><i><b style="width:<?php echo (int) $gpct; ?>%"></b></i><small><?php echo $gnext ? 'faltam ' . (int) ( $gnext['min'] - $earned ) . ' para ' . esc_html( $gnext['name'] ) : 'nível máximo 👑'; ?></small></a>
		<?php endif; ?>
		<div class="dsh-quick"><a href="<?php echo esc_url( lk_panel_url( 'tarefas' ) ); ?>">Tarefas</a><a href="<?php echo esc_url( lk_panel_url( 'agenda' ) ); ?>">Agenda</a><a href="<?php echo esc_url( lk_panel_url( 'conta' ) ); ?>">Minha conta</a></div>
	</section>
<?php $W['perfil'] = ob_get_clean(); endif; ?>
<?php if ( isset( $vis['metas'] ) ) : ob_start(); ?>
	<?php if ( lk_is_admin() && lk_goals() ) : ?>
	<section class="dsh-card">
		<div class="dsh-card-head"><h3>Metas</h3><a class="small" href="<?php echo esc_url( lk_panel_url( 'metas' ) ); ?>">ver todas</a></div>
		<?php foreach ( array_slice( lk_goals(), 0, 4 ) as $gg ) : $gp = lk_goal_progress( $gg ); ?>
			<div class="dsh-goal<?php echo $gp['pct'] >= 100 ? ' is-hit' : ''; ?>"><span><b><?php echo esc_html( $gg['name'] ); ?></b><em><?php echo (int) $gp['pct']; ?>%</em></span><i><b style="width:<?php echo (int) $gp['pct']; ?>%"></b></i><small><?php echo esc_html( lk_goal_fmt( $gg, $gp['value'] ) . ' de ' . lk_goal_fmt( $gg, $gp['target'] ) ); ?></small></div>
		<?php endforeach; ?>
	</section>
	<?php endif; ?>
<?php $W['metas'] = ob_get_clean(); endif; ?>
<?php if ( isset( $vis['andamento'] ) ) : ob_start(); ?>
	<section class="dsh-card">
		<div class="dsh-card-head"><h3>Em andamento comigo</h3><a class="small" href="<?php echo esc_url( lk_panel_url( 'conteudo', 0, array( 'meus' => 1, 'ver' => 'lista' ) ) ); ?>">ver todos</a></div>
		<?php if ( ! $mine ) : ?><p class="muted small">Nada na sua etapa agora. ✨</p><?php endif; ?>
		<div class="dsh-cards">
			<?php $keys = array_keys( $stages ); foreach ( array_slice( $mine, 0, 2 ) as $mp ) : $pc = (int) round( 100 * ( 1 + (int) array_search( $mp->stage, $keys, true ) ) / max( 1, count( $keys ) ) ); $mc = lk_get( 'clients', $mp->client_id ); ?>
				<?php $pc_col = $mc && $mc->color ? $mc->color : '#6c5ce7'; ?><a class="dsh-pcard<?php echo '#0a0a0a' === lk_on_color( $pc_col ) ? ' is-light' : ''; ?>" style="--c:<?php echo esc_attr( $pc_col ); ?>" href="<?php echo esc_url( lk_panel_url( 'post', $mp->id ) ); ?>"><strong><?php echo esc_html( wp_trim_words( $mp->title, 5, '…' ) ); ?></strong><small><?php echo esc_html( lk_post_client_label( $mp ) ); ?></small><em><?php echo (int) $pc; ?>%</em></a>
			<?php endforeach; ?>
		</div>
	</section>
<?php $W['andamento'] = ob_get_clean(); endif; ?>
<?php if ( isset( $vis['clientes_ult'] ) ) : ob_start(); ?>
	<?php if ( $last_cli ) : ?>
	<section class="dsh-card">
		<div class="dsh-card-head"><h3>Últimos clientes</h3><a class="small" href="<?php echo esc_url( lk_panel_url( 'clientes' ) ); ?>">ver todos</a></div>
		<ul class="dsh-clients">
			<?php foreach ( $last_cli as $lc ) : $ig = lk_social_account( $lc->id, 'instagram' ); ?>
				<li><a href="<?php echo esc_url( lk_panel_url( 'cliente', $lc->id ) ); ?>"><?php echo lk_client_avatar_html( $lc, 'dsh-cav', ( $ig && $ig->avatar ) ? $ig->avatar : '' ); // phpcs:ignore ?><span><strong><?php echo esc_html( lk_client_label( $lc ) ); ?></strong><small><?php echo esc_html( $lc->company && $lc->name ? $lc->name : ( $lc->email ?: '' ) ); ?></small></span></a><?php if ( $lc->whatsapp ) : ?><a class="dsh-wa" href="<?php echo esc_url( lk_wa_link( $lc->whatsapp, '' ) ); ?>" target="_blank" rel="noopener" title="WhatsApp"><?php echo lk_icon( 'whatsapp', 16 ); // phpcs:ignore ?></a><?php endif; ?></li>
			<?php endforeach; ?>
		</ul>
	</section>
	<?php endif; ?>
<?php $W['clientes_ult'] = ob_get_clean(); endif; ?>

<?php if ( isset( $vis['reunioes'] ) ) : ob_start(); ?>
<?php echo lk_meetings_widget_html(); // phpcs:ignore WordPress.Security.EscapeOutput ?>
<?php $W['reunioes'] = ob_get_clean(); endif; ?>

<?php if ( isset( $vis['datas'] ) ) : ob_start(); ?>
<?php
$near = array();
for ( $i = 0; $i < 2; $i++ ) {
	foreach ( lk_month_dates( gmdate( 'Y-m', strtotime( substr( $today, 0, 7 ) . '-01 +' . $i . ' month' ) ) ) as $nd ) {
		if ( $nd['date'] && $nd['date'] >= $today && $nd['date'] <= gmdate( 'Y-m-d', strtotime( $today . ' +14 day' ) ) ) {
			$near[] = $nd;
		}
	}
}
?>
<section class="card">
	<div class="card-head"><h3>Datas e feriados próximos</h3><a class="small" href="<?php echo esc_url( lk_panel_url( 'planejamento' ) ); ?>">planejamento</a></div>
	<?php if ( ! $near ) : ?><p class="muted small">Nenhuma data especial nos próximos 14 dias.</p><?php endif; ?>
	<ul class="mini-list"><?php foreach ( $near as $nd ) : ?><li><span><strong><?php echo ( 'feriado' === $nd['type'] ? '🔴 ' : '📌 ' ) . esc_html( $nd['name'] ); ?></strong><small><?php echo esc_html( lk_date( $nd['date'], 'd/m' ) . ' · ' . lk_dow_short( $nd['date'] ) . ( 'feriado' === $nd['type'] ? ' · feriado' : '' ) ); ?></small></span></li><?php endforeach; ?></ul>
</section>
<?php $W['datas'] = ob_get_clean(); endif; ?>
<?php if ( isset( $vis['ranking'] ) ) : ob_start(); ?>
<section class="card">
	<div class="card-head"><h3>🏆 Ranking do mês</h3><a class="small" href="<?php echo esc_url( lk_panel_url( 'ranking' ) ); ?>">ver tudo</a></div>
	<?php if ( ! function_exists( 'lk_game_on' ) || ! lk_game_on() ) : ?><p class="muted small">A gamificação está desligada.</p><?php else : $medals = array( '🥇', '🥈', '🥉' ); ?>
		<ul class="mini-list"><?php foreach ( array_slice( lk_game_ranking( 'mes' ), 0, 3 ) as $ri => $rr ) : ?><li><span><strong><?php echo esc_html( $medals[ $ri ] . ' ' . $rr['user']->display_name ); ?></strong><small><?php echo (int) $rr['points']; ?> pontos no mês</small></span></li><?php endforeach; ?></ul>
	<?php endif; ?>
</section>
<?php $W['ranking'] = ob_get_clean(); endif; ?>
<?php if ( isset( $vis['contratos'] ) ) : ob_start(); ?>
<?php $kpend = lk_rows( 'contracts', "status IN ('enviado','assinado')", array(), 'id DESC LIMIT 6' ); ?>
<section class="card">
	<div class="card-head"><h3>Contratos pendentes</h3><a class="small" href="<?php echo esc_url( lk_panel_url( 'contratos' ) ); ?>">ver todos</a></div>
	<?php if ( ! $kpend ) : ?><p class="muted small">Nenhum contrato pendente. ✨</p><?php endif; ?>
	<ul class="mini-list"><?php foreach ( $kpend as $kk ) : $kc = lk_get( 'clients', $kk->client_id ); ?><li><a href="<?php echo esc_url( lk_panel_url( 'contrato', $kk->id ) ); ?>"><strong><?php echo esc_html( $kc ? lk_client_label( $kc ) : '—' ); ?></strong><small><?php echo esc_html( lk_contract_status_label( $kk->status ) ); ?></small></a></li><?php endforeach; ?></ul>
</section>
<?php $W['contratos'] = ob_get_clean(); endif; ?>
<?php if ( isset( $vis['funil'] ) ) : ob_start(); ?>
<?php
$fun   = lk_funnel();
$fcnt  = array();
foreach ( lk_rows( 'leads', '1=1' ) as $fl ) {
	$fcnt[ $fl->stage ] = ( $fcnt[ $fl->stage ] ?? 0 ) + 1;
}
$fmax = max( 1, max( array_merge( array( 0 ), array_values( $fcnt ) ) ) );
?>
<section class="card">
	<div class="card-head"><h3>Funil de leads</h3><a class="small" href="<?php echo esc_url( lk_panel_url( 'leads' ) ); ?>">abrir funil</a></div>
	<?php foreach ( $fun as $fs => $fn ) : ?><div class="dsh-goal"><span><b><?php echo esc_html( $fn ); ?></b><em><?php echo (int) ( $fcnt[ $fs ] ?? 0 ); ?></em></span><i><b style="width:<?php echo (int) round( 100 * ( $fcnt[ $fs ] ?? 0 ) / $fmax ); ?>%"></b></i></div><?php endforeach; ?>
</section>
<?php $W['funil'] = ob_get_clean(); endif; ?>


<div class="dsh-ct"><div class="dsh">
<div class="dsh-main">
<?php
$half = false;
foreach ( lk_dash_zone( 'main' ) as $mid ) {
	$is_half = lk_dash_widgets()[ $mid ][4];
	if ( $is_half && ! $half ) {
		echo '<div class="dsh-row">';
		$half = true;
	} elseif ( ! $is_half && $half ) {
		echo '</div>';
		$half = false;
	}
	echo $W[ $mid ] ?? ''; // phpcs:ignore WordPress.Security.EscapeOutput -- HTML montado acima com esc_*.
}
if ( $half ) {
	echo '</div>';
}
?>
<div class="grid-2 dash-grid" data-dash-grid>
<?php foreach ( lk_dash_zone( 'grid' ) as $gid ) { echo $W[ $gid ] ?? ''; } // phpcs:ignore WordPress.Security.EscapeOutput ?>
</div>
</div>
<aside class="dsh-side" data-dash="side">
<?php foreach ( lk_dash_zone( 'side' ) as $sid ) { echo $W[ $sid ] ?? ''; } // phpcs:ignore WordPress.Security.EscapeOutput ?>
</aside>
</div></div>
<?php echo lk_dash_customize_html(); // phpcs:ignore WordPress.Security.EscapeOutput ?>
<script src="<?php echo esc_url( LK_URL . 'assets/dash.js?ver=' . LK_VERSION ); ?>"></script>
<?php
lk_panel_end();

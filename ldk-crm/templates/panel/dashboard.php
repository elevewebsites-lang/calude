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

$hour  = (int) current_time( 'G' );
$hello = $hour < 12 ? 'Bom dia' : ( $hour < 18 ? 'Boa tarde' : 'Boa noite' );
$name  = wp_get_current_user()->first_name ? wp_get_current_user()->first_name : wp_get_current_user()->display_name;
$refresh = '<button type="button" class="btn btn--ghost dash-refresh" data-dash-refresh title="Atualizar os números e as listas do dashboard (também atualiza sozinho a cada 3 minutos)">' . lk_icon( 'atualizar', 16 ) . '<span>Atualizar</span></button>';
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
<section class="stats stats--4" data-dash="stats">
	<a class="stat stat--dark" href="<?php echo esc_url( lk_panel_url( 'conteudo', 0, array( 'meus' => 1, 'ver' => 'lista' ) ) ); ?>"><span class="stat-label">Comigo agora</span><strong><?php echo count( $mine ); ?></strong><small>posts na minha etapa</small></a>
	<div class="stat"><span class="stat-label">Aguardando cliente</span><strong><?php echo count( $wait ); ?></strong><small>em aprovação</small></div>
	<div class="stat"><span class="stat-label">Atrasados</span><strong class="<?php echo $late ? 'text-late' : ''; ?>"><?php echo count( $late ); ?></strong><small>passou da data e não está agendado</small></div>
	<?php if ( $fin ) : ?>
		<a class="stat" href="<?php echo esc_url( lk_panel_url( 'cobrancas' ) ); ?>"><span class="stat-label">Recorrente</span><strong class="money"><?php echo esc_html( lk_money( lk_billing_mrr() ) ); ?></strong><small><?php echo count( $pend ); ?> cobrança(s) pendente(s)</small></a>
	<?php else : ?>
		<div class="stat"><span class="stat-label">Hoje</span><strong><?php echo count( $today_posts ); ?></strong><small>publicações</small></div>
	<?php endif; ?>
</section>

<section class="card" data-dash="esteira">
	<div class="card-head"><h3>Esteira</h3><a class="small" href="<?php echo esc_url( lk_panel_url( 'conteudo', 0, array( 'ver' => 'kanban' ) ) ); ?>">abrir Kanban</a></div>
	<div class="pipe">
		<?php foreach ( $stages as $s => $n ) : ?><div class="pipe-step"><strong><?php echo (int) $by[ $s ]; ?></strong><span><?php echo esc_html( $n ); ?></span></div><?php endforeach; ?>
	</div>
</section>

<div class="grid-2 dash-grid" data-dash-grid>
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
	<section class="card">
		<div class="card-head"><h3>Publicações de hoje</h3></div>
		<?php if ( ! $today_posts ) : ?><p class="muted small">Nada programado para hoje.</p><?php endif; ?>
		<ul class="mini-list">
			<?php foreach ( $today_posts as $p ) : ?>
				<li><a href="<?php echo esc_url( lk_panel_url( 'post', $p->id ) ); ?>"><strong><?php echo esc_html( lk_date( $p->scheduled_at, 'H:i' ) . ' · ' . $p->title ); ?></strong><small><?php echo esc_html( lk_post_client_label( $p ) . ' · ' . ( $stages[ $p->stage ] ?? '' ) ); ?><?php echo $p->publish_error ? ' · erro ao publicar' : ''; ?></small></a></li>
			<?php endforeach; ?>
		</ul>
	</section>
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
	<?php if ( $pend ) : ?>
		<section class="card">
			<div class="card-head"><h3>Cobranças</h3><a class="small" href="<?php echo esc_url( lk_panel_url( 'cobrancas' ) ); ?>">cobrar</a></div>
			<ul class="mini-list">
				<?php foreach ( array_slice( $pend, 0, 6 ) as $t ) : ?><li><a href="<?php echo esc_url( lk_panel_url( 'cobrancas' ) ); ?>"><strong><?php echo esc_html( $t->description ); ?></strong><small><?php echo esc_html( lk_money( $t->amount ) ); ?> <?php echo lk_due_badge( $t->due_date ); // phpcs:ignore ?></small></a></li><?php endforeach; ?>
			</ul>
		</section>
	<?php endif; ?>
	<?php if ( $nocon && lk_can( 'clientes' ) ) : ?>
		<section class="card">
			<div class="card-head"><h3>Instagram não vinculado</h3><a class="small" href="<?php echo esc_url( lk_panel_url( 'redes' ) ); ?>">passo a passo</a></div>
			<ul class="mini-list"><?php foreach ( array_slice( $nocon, 0, 8 ) as $c ) : ?><li><a href="<?php echo esc_url( lk_panel_url( 'cliente', $c->id ) ); ?>#redes"><strong><?php echo esc_html( lk_client_label( $c ) ); ?></strong><small>conectar para agendar e ter relatório</small></a></li><?php endforeach; ?></ul>
		</section>
	<?php endif; ?>
	<section class="card" data-teamchat data-channel="geral">
		<div class="card-head"><h3>Chat da equipe</h3><a class="small" href="<?php echo esc_url( lk_panel_url( 'chat' ) ); ?>">abrir</a></div>
		<div class="chat-list chat-list--mini" data-chat-list></div>
		<form class="chat-form"><textarea rows="1" placeholder="Mensagem para a equipe…"></textarea><button type="submit" class="btn btn--primary btn--sm">Enviar</button></form>
	</section>
</div>
<script src="<?php echo esc_url( LK_URL . 'assets/dash.js?ver=' . LK_VERSION ); ?>"></script>
<?php
lk_panel_end();

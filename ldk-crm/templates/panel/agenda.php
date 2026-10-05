<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
$connected = lk_google_connected();
$actions   = '<button type="button" class="btn btn--primary" data-open="nova-reuniao">' . lk_icon( 'mais', 16 ) . '<span>Marcar reunião</span></button>';
lk_panel_start( 'Agenda', 'agenda', $actions );
?>
<?php echo lk_meetings_card_html(); // phpcs:ignore WordPress.Security.EscapeOutput ?>

<?php if ( ! $connected ) : ?>
	<div class="empty empty--big">
		<?php echo lk_icon( 'relogio', 28 ); // phpcs:ignore ?>
		<h3>Conecte o seu Google Agenda</h3>
		<p>Veja as próximas reuniões aqui e marque reuniões com Google Meet, já com o convite para o cliente.</p>
		<?php if ( lk_is_admin() ) : ?><a class="btn btn--primary" href="<?php echo esc_url( lk_panel_url( 'config' ) . '#google' ); ?>">Conectar Google</a><?php endif; ?>
	</div>
<?php else : ?>
	<div class="dash-grid">
		<section class="card">
			<div class="card-head"><h3>Próximas reuniões (30 dias)</h3><a class="link" href="https://calendar.google.com/" target="_blank" rel="noopener">Abrir o Google Agenda</a></div>
			<?php echo lk_meetings_html( lk_calendar_upcoming( 30, 25 ) ); // phpcs:ignore -- escapado na função. ?>
		</section>
		<section class="card">
			<div class="card-head"><h3>Prazos desta semana</h3><a class="link" href="<?php echo esc_url( lk_panel_url( 'tarefas' ) ); ?>">Tarefas</a></div>
			<?php
			$week  = gmdate( 'Y-m-d', strtotime( lk_today() . ' +7 day' ) );
			$items = lk_tasks( "t.status <> 'done' AND t.due_date IS NOT NULL AND t.due_date <= %s", array( $week ), 't.due_date, t.id' );
			?>
			<?php if ( ! $items ) : ?>
				<p class="muted small">Nada vencendo nos próximos 7 dias.</p>
			<?php else : ?>
				<ul class="mini-list">
					<?php foreach ( array_slice( $items, 0, 12 ) as $t ) : ?>
						<li><a href="<?php echo esc_url( lk_panel_url( 'tarefa', $t->id ) ); ?>"><strong><?php echo esc_html( $t->title ); ?></strong><span><?php echo esc_html( $t->project_title ? $t->project_title : 'Agência' ); ?></span></a><?php echo lk_due_badge( $t->due_date ); // phpcs:ignore ?></li>
					<?php endforeach; ?>
				</ul>
			<?php endif; ?>
		</section>
	</div>
<?php endif; ?>

<?php
lk_meeting_modal();
lk_panel_end();

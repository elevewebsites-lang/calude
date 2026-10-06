<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
$checks   = ap_health_checks();
$problems = ap_health_problems( $checks );
$bad      = array_filter( $problems, function ( $c ) { return 'bad' === $c[0]; } );
$fails    = array_slice( (array) get_option( 'ap_mail_fails', array() ), 0, 8 );
$labels   = array( 'ok' => 'Tudo certo', 'warn' => 'Atenção', 'bad' => 'Parado', 'info' => 'Informação' );
delete_transient( 'ap_health_bad' );

ob_start();
ap_action_button( 'health_run', array(), ap_icon( 'relogio', 16 ) . '<span>Rodar tarefas agora</span>', 'btn btn--ghost' );
ap_panel_start( 'Saúde do sistema', 'saude', ob_get_clean() );
?>

<section class="health-hero health-hero--<?php echo $bad ? 'bad' : ( $problems ? 'warn' : 'ok' ); ?>">
	<span class="health-big"><?php echo $bad ? '!' : ( $problems ? '•' : '✓' ); ?></span>
	<div>
		<h2><?php echo $bad ? esc_html( count( $bad ) . ( 1 === count( $bad ) ? ' coisa parada' : ' coisas paradas' ) ) : ( $problems ? esc_html( count( $problems ) . ( 1 === count( $problems ) ? ' ponto de atenção' : ' pontos de atenção' ) ) : 'Tudo funcionando' ); ?></h2>
		<p><?php echo $bad ? 'Resolva os itens em vermelho: alguma coisa automática deixou de funcionar.' : ( $problems ? 'Nada parado, mas vale resolver os itens em amarelo para não virar problema.' : 'Tarefas em dia, e-mails saindo e backup recente.' ); ?> Você recebe um e-mail por dia enquanto houver item em vermelho.</p>
	</div>
</section>

<div class="health-list">
	<?php foreach ( $checks as $key => $c ) : ?>
		<article class="health-item health-item--<?php echo esc_attr( $c[0] ); ?>" id="saude-<?php echo esc_attr( $key ); ?>">
			<span class="health-dot" aria-hidden="true"></span>
			<div class="health-body">
				<div class="health-head"><h3><?php echo esc_html( $c[1] ); ?></h3><span class="pill pill--<?php echo esc_attr( array( 'ok' => 'ok', 'warn' => 'warn', 'bad' => 'late', 'info' => '' )[ $c[0] ] ); ?>"><?php echo esc_html( $labels[ $c[0] ] ); ?></span></div>
				<p><?php echo esc_html( $c[2] ); ?></p>
				<?php if ( $c[3] && 'ok' !== $c[0] ) : ?><p class="health-fix"><strong>Como resolver:</strong> <?php echo esc_html( $c[3] ); ?></p><?php endif; ?>
				<?php if ( 'cron' === $key && 'ok' !== $c[0] ) : ?>
					<div class="copy-row"><input type="text" readonly value="<?php echo esc_attr( 'wget -q -O - "' . ap_health_cron_url() . '" >/dev/null 2>&1' ); ?>" onclick="this.select()"><button type="button" class="btn btn--ghost" data-copy="<?php echo esc_attr( 'wget -q -O - "' . ap_health_cron_url() . '" >/dev/null 2>&1' ); ?>"><?php echo ap_icon( 'copiar', 16 ); // phpcs:ignore ?><span>Copiar comando</span></button></div>
				<?php endif; ?>
				<?php if ( 'mail' === $key ) : ?>
					<?php ap_form( 'mail_test', 'copy-row' ); ?>
						<input type="email" name="to" value="<?php echo esc_attr( wp_get_current_user()->user_email ); ?>" placeholder="Para qual e-mail?">
						<button type="submit" class="btn btn--ghost">Enviar e-mail de teste</button>
					</form>
				<?php endif; ?>
				<?php if ( $c[4] && 'ok' !== $c[0] ) : ?><a class="link" href="<?php echo esc_url( $c[4] ); ?>">Abrir</a><?php endif; ?>
			</div>
		</article>
	<?php endforeach; ?>
</div>

<?php if ( $fails ) : ?>
	<section class="card">
		<div class="card-head"><h3>Últimos e-mails que falharam</h3></div>
		<ul class="sec-log">
			<?php foreach ( $fails as $f ) : ?>
				<li><span class="badge badge--urgente"><?php echo esc_html( ap_health_ago( $f['t'] ) ); ?></span> <?php echo esc_html( $f['subject'] . ( $f['to'] ? ' → ' . $f['to'] : '' ) ); ?> <small class="muted">— <?php echo esc_html( $f['error'] ); ?></small></li>
			<?php endforeach; ?>
		</ul>
	</section>
<?php endif; ?>

<?php
ap_panel_end();

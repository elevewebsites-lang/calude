<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
$ch  = lk_team_channel( $_GET['canal'] ?? 'geral' ); // phpcs:ignore WordPress.Security.NonceVerification
if ( ! lk_team_channel_allowed( $ch ) ) {
	$ch = 'geral';
}
$chs = lk_team_channels();
$me  = get_current_user_id();
$dms = array();
foreach ( lk_team_users() as $u ) {
	if ( (int) $u->ID !== $me ) {
		$dms[ lk_dm_channel( $me, $u->ID ) ] = $u;
	}
}
lk_panel_start( 'Chat da equipe', 'chat' );
?>
<div class="inbox">
	<aside class="inbox-list">
		<?php foreach ( $chs as $k => $label ) : ?>
			<a class="inbox-item<?php echo $k === $ch ? ' is-active' : ''; ?>" href="<?php echo esc_url( lk_panel_url( 'chat', 0, array( 'canal' => $k ) ) ); ?>"><strong># <?php echo esc_html( $label ); ?></strong></a>
		<?php endforeach; ?>
		<?php if ( $dms ) : ?>
			<p class="hub-sec">Conversas individuais</p>
			<?php foreach ( $dms as $k => $u ) : ?>
				<?php $st = lk_presence( $u->ID ); ?>
				<a class="inbox-item<?php echo $k === $ch ? ' is-active' : ''; ?>" href="<?php echo esc_url( lk_panel_url( 'chat', 0, array( 'canal' => $k ) ) ); ?>"><strong><i class="pres pres--<?php echo esc_attr( $st ); ?>" title="<?php echo esc_attr( lk_presence_labels()[ $st ] ); ?>"></i><?php echo esc_html( $u->display_name ); ?></strong><?php $nt = 'offline' === $st ? '' : (string) get_user_meta( $u->ID, 'lk_status_note', true ); if ( $nt ) : ?><span class="pres-note"><?php echo esc_html( $nt ); ?></span><?php endif; ?></a>
			<?php endforeach; ?>
		<?php endif; ?>
	</aside>
	<section class="card chat" data-teamchat data-channel="<?php echo esc_attr( $ch ); ?>">
		<div class="chat-head"><strong><?php echo esc_html( lk_team_channel_label( $ch ) ); ?></strong><span class="muted small">Enter envia · Shift+Enter quebra linha · @nome chama a pessoa</span></div>
		<div class="chat-list" data-chat-list><p class="muted small center">Carregando…</p></div>
		<form class="chat-form"><textarea rows="2" placeholder="Escreva… (@<?php echo esc_attr( sanitize_title( strtok( wp_get_current_user()->display_name, ' ' ) ) ); ?>)"></textarea><button type="submit" class="btn btn--primary">Enviar</button></form>
	</section>
</div>
<?php
lk_panel_end();

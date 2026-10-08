<?php
/**
 * Área do cliente: todos os roteiros dos vídeos dele, por dia de gravação.
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
$labels = lk_script_status_labels();
$list   = lk_rows( 'scripts', "client_id = %d AND ( status <> 'rascunho' OR record_date IS NOT NULL )", array( (int) $client->id ), 'record_date IS NULL, record_date DESC, id' );
$todo   = array_filter( $list, function ( $s ) { return 'aprovado' !== $s->status; } );
lk_client_start( 'Roteiros', $client );
?>
<section class="chello"><span class="eyebrow">Roteiros</span><h1>Roteiros dos seus vídeos</h1><p class="muted">Aqui estão todos os roteiros e as datas de gravação. Abra, confira e aprove ou peça ajustes.</p></section>
<?php if ( $todo ) : ?><div class="apx-msg"><?php echo (int) count( $todo ); ?> roteiro(s) aguardando a sua resposta.</div><?php endif; ?>
<?php if ( ! $list ) : ?>
	<div class="empty empty--big"><h3>Nenhum roteiro por enquanto</h3><p>Quando houver um vídeo para gravar, o roteiro aparece aqui.</p></div>
<?php else : ?>
	<div class="cprojects">
		<?php foreach ( $list as $s ) : ?>
			<a class="cproject" href="<?php echo esc_url( lk_script_url( $s ) ); ?>">
				<div class="cproject-top"><span class="muted small"><?php echo $s->record_date ? '📅 ' . esc_html( lk_date( $s->record_date, 'd/m/Y' ) . ( $s->record_time ? ' às ' . $s->record_time : '' ) ) : 'Sem dia de gravação'; ?></span><span class="badge <?php echo 'aprovado' === $s->status ? 'badge--ok' : 'badge--warn'; ?>"><?php echo esc_html( $labels[ $s->status ] ?? $s->status ); ?></span></div>
				<h3><?php echo esc_html( $s->title ); ?></h3>
				<?php if ( $s->place ) : ?><p class="muted small">📍 <?php echo esc_html( $s->place ); ?></p><?php endif; ?>
			</a>
		<?php endforeach; ?>
	</div>
<?php endif; ?>
<?php
lk_client_end();

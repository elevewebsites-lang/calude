<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
// phpcs:disable WordPress.Security.NonceVerification.Recommended
$view   = isset( $_GET['ver'] ) ? sanitize_key( $_GET['ver'] ) : 'calendario';
$cid    = isset( $_GET['cliente'] ) ? absint( $_GET['cliente'] ) : 0;
$ym     = isset( $_GET['mes'] ) && preg_match( '/^\d{4}-\d{2}$/', $_GET['mes'] ) ? sanitize_text_field( $_GET['mes'] ) : current_time( 'Y-m' );
// Designer abre já no que está com ele (dá para desmarcar).
$mine   = isset( $_GET['meus'] ) ? ! empty( $_GET['meus'] ) : 'designer' === lk_user_role_key( get_current_user_id() );
// phpcs:enable
$client = $cid ? lk_get( 'clients', $cid ) : null;
$stages = lk_stages();
$where  = '1=1';
$args   = array();
if ( $cid ) {
	$where .= ' AND p.client_id = %d';
	$args[] = $cid;
}
if ( 'calendario' === $view ) {
	$where .= ' AND ( p.scheduled_at BETWEEN %s AND %s OR p.scheduled_at IS NULL )';
	$args[] = $ym . '-01 00:00:00';
	$args[] = gmdate( 'Y-m-t', strtotime( $ym . '-01' ) ) . ' 23:59:59';
}
$posts = lk_posts( $where, $args );
if ( $mine ) {
	$me    = get_current_user_id();
	$posts = array_values( array_filter( $posts, function ( $p ) use ( $me ) { return lk_post_owner( $p ) === $me; } ) );
}
$accs = $cid ? lk_social_accounts( $cid ) : array();
$base = array_filter( array( 'cliente' => $cid ) ) + array( 'meus' => $mine ? 1 : 0 );
$tab  = function ( $v, $label, $icon ) use ( $view, $base, $ym ) {
	return '<a class="' . ( $view === $v ? 'is-active' : '' ) . '" href="' . esc_url( lk_panel_url( 'conteudo', 0, $base + array( 'ver' => $v, 'mes' => $ym ) ) ) . '">' . lk_icon( $icon, 15 ) . ' ' . $label . '</a>';
};
$actions = '<a class="btn btn--ghost" href="' . esc_url( lk_panel_url( 'importar', 0, array_filter( array( 'cliente' => $cid ) ) ) ) . '">' . lk_icon( 'upload', 16 ) . '<span>Importar planilha</span></a>' . ( $cid ? '<a class="btn btn--ghost" href="' . esc_url( lk_panel_url( 'planejamento', 0, array( 'cliente' => $cid, 'mes' => $ym ) ) ) . '">' . lk_icon( 'lista', 16 ) . '<span>Planejamento</span></a>' : '' ) . '<div class="seg">' . $tab( 'calendario', 'Calendário', 'calendario' ) . $tab( 'kanban', 'Kanban', 'projetos' ) . $tab( 'lista', 'Lista', 'lista' ) . '</div><button type="button" class="btn btn--primary" data-open="novo-post" data-date="">' . lk_icon( 'mais', 16 ) . '<span>Post</span></button>';
lk_panel_start( $client ? 'Conteúdo · ' . lk_client_label( $client ) : 'Conteúdo', 'conteudo', $actions );
$badge = function ( $p ) use ( $stages ) {
	$late = lk_post_late( $p );
	$st   = $p->client_status ? array( 'pendente' => 'aguardando cliente', 'aprovado' => 'aprovado', 'alteracao' => 'ajuste: ' . $p->change_target )[ $p->client_status ] ?? '' : '';
	return '<span class="pbadges">' . lk_deadline_badge( $p ) . ( $late ? '<em class="badge badge--late">atrasado</em>' : '' ) . ( $st ? '<em class="badge badge--cs-' . esc_attr( $p->client_status ) . '">' . esc_html( $st ) . '</em>' : '' ) . ( $p->publish_error ? '<em class="badge badge--late">erro ao publicar</em>' : '' ) . '</span>';
};
$nets = function ( $p ) {
	$h = '';
	foreach ( lk_post_networks( $p ) as $n ) {
		$h .= '<i class="net net--' . esc_attr( $n ) . '" title="' . esc_attr( lk_networks()[ $n ] ) . '">' . esc_html( strtoupper( $n[0] ) ) . '</i>';
	}
	return $h;
};
?>
<div class="toolbar">
	<form class="inline-form content-filter" method="get">
		<select name="cliente" onchange="this.form.submit()"><option value="">Todos os clientes</option><?php foreach ( lk_clients() as $c ) : ?><option value="<?php echo (int) $c->id; ?>"<?php selected( $cid, (int) $c->id ); ?>><?php echo esc_html( lk_client_label( $c ) ); ?></option><?php endforeach; ?></select>
		<input type="hidden" name="ver" value="<?php echo esc_attr( $view ); ?>"><input type="hidden" name="mes" value="<?php echo esc_attr( $ym ); ?>">
		<input type="hidden" name="meus" value="0"><label class="chk"><input type="checkbox" name="meus" value="1" onchange="this.form.submit()"<?php checked( $mine ); ?>> Só o que está comigo</label>
	</form>
	<?php echo $client ? lk_quota_html( $client, $ym ) : ''; // phpcs:ignore ?>
	<?php if ( $client && ! lk_manage_only() ) : ?>
		<span class="acc-row"><?php foreach ( lk_networks() as $n => $nl ) : ?><em class="badge <?php echo isset( $accs[ $n ] ) ? 'badge--ok' : 'badge--off'; ?>"><?php echo esc_html( $nl . ( isset( $accs[ $n ] ) ? '' : ': não vinculado' ) ); ?></em><?php endforeach; ?></span>
	<?php endif; ?>
</div>

<?php if ( 'calendario' === $view ) : ?>
	<?php
	$first  = strtotime( $ym . '-01' );
	$start  = strtotime( '-' . ( (int) gmdate( 'N', $first ) - 1 ) . ' day', $first );
	$by_day = array();
	$undated = array();
	foreach ( $posts as $p ) {
		if ( $p->scheduled_at ) {
			$by_day[ substr( $p->scheduled_at, 0, 10 ) ][] = $p;
		} else {
			$undated[] = $p;
		}
	}
	$prev = gmdate( 'Y-m', strtotime( $ym . '-01 -1 month' ) );
	$next = gmdate( 'Y-m', strtotime( $ym . '-01 +1 month' ) );
	?>
	<div class="cal-head">
		<a class="icon-btn" href="<?php echo esc_url( lk_panel_url( 'conteudo', 0, $base + array( 'ver' => 'calendario', 'mes' => $prev ) ) ); ?>">‹</a>
		<h3><?php echo esc_html( ucfirst( lk_month_label( $ym ) ) ); ?></h3>
		<a class="icon-btn" href="<?php echo esc_url( lk_panel_url( 'conteudo', 0, $base + array( 'ver' => 'calendario', 'mes' => $next ) ) ); ?>">›</a>
		<a class="btn btn--ghost btn--sm" href="<?php echo esc_url( lk_panel_url( 'conteudo', 0, $base + array( 'ver' => 'calendario' ) ) ); ?>">Hoje</a>
	</div>
	<div class="cal" data-cal>
		<?php foreach ( array( 'Seg', 'Ter', 'Qua', 'Qui', 'Sex', 'Sáb', 'Dom' ) as $d ) : ?><div class="cal-dow"><?php echo esc_html( $d ); ?></div><?php endforeach; ?>
		<?php for ( $i = 0; $i < 42; $i++ ) : ?>
			<?php $day = gmdate( 'Y-m-d', strtotime( '+' . $i . ' day', $start ) ); $out = substr( $day, 0, 7 ) !== $ym; ?>
			<?php if ( $i >= 35 && $out ) { break; } ?>
			<div class="cal-day<?php echo $out ? ' is-out' : ''; ?><?php echo $day === lk_today() ? ' is-today' : ''; ?>" data-day="<?php echo esc_attr( $day ); ?>">
				<button type="button" class="cal-add" data-open="novo-post" data-date="<?php echo esc_attr( $day ); ?>" title="Novo post neste dia"><span><?php echo (int) substr( $day, 8 ); ?></span><i>+</i></button>
				<?php foreach ( $by_day[ $day ] ?? array() as $p ) : ?>
					<a class="cal-post" draggable="true" data-post="<?php echo (int) $p->id; ?>" href="<?php echo esc_url( lk_panel_url( 'post', $p->id ) ); ?>" style="--c:<?php echo esc_attr( $p->client_color ?: '#14E9EC' ); ?>">
						<?php echo lk_thumb_html( $p, 'cal-thumb' ); // phpcs:ignore ?>
						<b><?php echo esc_html( substr( $p->scheduled_at, 11, 5 ) ); ?></b> <?php echo esc_html( $p->title ); ?>
						<small><?php echo esc_html( ( $cid ? '' : lk_post_client_label( $p ) . ' · ' ) . ( lk_formats()[ $p->format ] ?? '' ) ); ?></small>
						<?php echo lk_stage_chip( $p ) . $nets( $p ) . $badge( $p ); // phpcs:ignore ?>
					</a>
				<?php endforeach; ?>
			</div>
		<?php endfor; ?>
	</div>
	<?php if ( $undated ) : ?>
		<section class="card"><div class="card-head"><h3>Sem data</h3><span class="muted small">arraste para um dia do calendário</span></div><div class="undated"><?php foreach ( $undated as $p ) : ?><a class="cal-post" draggable="true" data-post="<?php echo (int) $p->id; ?>" href="<?php echo esc_url( lk_panel_url( 'post', $p->id ) ); ?>" style="--c:<?php echo esc_attr( $p->client_color ?: '#14E9EC' ); ?>"><?php echo lk_thumb_html( $p, 'cal-thumb' ); // phpcs:ignore ?><?php echo esc_html( $p->title ); ?><small><?php echo esc_html( lk_post_client_label( $p ) ); ?></small><?php echo lk_stage_chip( $p ); // phpcs:ignore ?></a><?php endforeach; ?></div></section>
	<?php endif; ?>

<?php elseif ( 'kanban' === $view ) : ?>
	<div class="kanban" data-post-kanban>
		<?php foreach ( $stages as $slug => $name ) : ?>
			<?php $col = array_filter( $posts, function ( $p ) use ( $slug ) { return $p->stage === $slug; } ); ?>
			<section class="kcol" data-col="<?php echo esc_attr( $slug ); ?>">
				<header class="kcol-head"><h3 class="kcol-title"><?php echo esc_html( $name ); ?></h3><span class="kcount"><?php echo count( $col ); ?></span></header>
				<div class="kcol-body" data-status="<?php echo esc_attr( $slug ); ?>">
					<?php foreach ( $col as $p ) : ?>
						<?php $owner = get_userdata( lk_post_owner( $p ) ); $med = lk_post_media( $p ); ?>
						<article class="kcard" draggable="true" data-id="<?php echo (int) $p->id; ?>" data-href="<?php echo esc_url( lk_panel_url( 'post', $p->id ) ); ?>" style="border-left:4px solid <?php echo esc_attr( $p->client_color ?: '#14E9EC' ); ?>">
							<?php echo lk_thumb_html( $p, 'kcard-thumb' ); // phpcs:ignore ?>
							<div class="kcard-top"><span class="badge"><?php echo esc_html( lk_formats()[ $p->format ] ?? '' ); ?></span><?php echo $nets( $p ); // phpcs:ignore ?></div>
							<h4><?php echo esc_html( $p->title ); ?></h4>
							<p class="kcard-sub"><?php echo esc_html( lk_post_client_label( $p ) ); ?></p>
							<?php echo $badge( $p ); // phpcs:ignore ?>
							<div class="kcard-foot"><span class="muted small"><?php echo $owner ? esc_html( '👤 ' . strtok( $owner->display_name, ' ' ) ) : ''; ?></span><?php echo $p->scheduled_at ? lk_due_badge( substr( $p->scheduled_at, 0, 10 ), $p->stage === lk_stage_for( 'publicado' ) ) : ''; // phpcs:ignore ?></div>
						</article>
					<?php endforeach; ?>
				</div>
			</section>
		<?php endforeach; ?>
	</div>
	<p class="muted small hint">As etapas se editam em Configurações → Conteúdo.</p>

<?php else : ?>
	<div class="toolbar"><input type="search" class="filter" placeholder="Filtrar…" data-filter=".table-row:not(.table-head)"></div>
	<div class="table table--posts">
		<div class="table-row table-head"><span>Post</span><span>Data</span><span>Etapa</span><span>Com</span><span>Redes</span></div>
		<?php foreach ( $posts as $p ) : ?>
			<?php $owner = get_userdata( lk_post_owner( $p ) ); ?>
			<a class="table-row" href="<?php echo esc_url( lk_panel_url( 'post', $p->id ) ); ?>">
				<span class="cell-main"><?php echo lk_thumb_html( $p, 'row-thumb' ) ? lk_thumb_html( $p, 'row-thumb' ) : '<span class="row-thumb row-thumb--ph" style="background:' . esc_attr( $p->client_color ?: '#14E9EC' ) . '"></span>'; // phpcs:ignore ?><span><strong><?php echo esc_html( $p->title ); ?></strong><small><?php echo esc_html( lk_post_client_label( $p ) . ' · ' . ( lk_formats()[ $p->format ] ?? '' ) ); ?></small></span></span>
				<span data-label="Data"><?php echo $p->scheduled_at ? esc_html( lk_date( $p->scheduled_at, 'd/m H:i' ) ) : '—'; ?></span>
				<span data-label="Etapa"><?php echo lk_stage_chip( $p ) . $badge( $p ); // phpcs:ignore ?></span>
				<span data-label="Com"><?php echo $owner ? esc_html( $owner->display_name ) : '—'; ?></span>
				<span data-label="Redes"><?php echo $nets( $p ); // phpcs:ignore ?></span>
			</a>
		<?php endforeach; ?>
	</div>
<?php endif; ?>

<?php
lk_modal_start( 'novo-post', 'Novo post' );
lk_post_form( null, $cid );
lk_modal_end();
?>
<script src="<?php echo esc_url( LK_URL . 'assets/content.js?ver=' . LK_VERSION ); ?>"></script>
<?php
?>
<script src="<?php echo esc_url( LK_URL . 'assets/cliente-arquivos.js?ver=' . LK_VERSION ); ?>"></script>
<?php
lk_panel_end();

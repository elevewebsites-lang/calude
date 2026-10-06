<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
$ver = isset( $_GET['ver'] ) ? sanitize_key( $_GET['ver'] ) : 'mes'; // phpcs:ignore WordPress.Security.NonceVerification
$ver = in_array( $ver, array( 'mes', 'semana', 'lista' ), true ) ? $ver : 'mes';
$d   = isset( $_GET['d'] ) && preg_match( '/^\d{4}-\d{2}-\d{2}$/', sanitize_text_field( wp_unslash( $_GET['d'] ) ) ) ? sanitize_text_field( wp_unslash( $_GET['d'] ) ) : lk_today(); // phpcs:ignore WordPress.Security.NonceVerification
$today = lk_today();
$ts    = strtotime( $d );
$mnames = array( '', 'janeiro', 'fevereiro', 'março', 'abril', 'maio', 'junho', 'julho', 'agosto', 'setembro', 'outubro', 'novembro', 'dezembro' );
$dnames = array( 'dom', 'seg', 'ter', 'qua', 'qui', 'sex', 'sáb' );
$url    = function ( $v, $date ) { return lk_panel_url( 'reunioes', 0, array( 'ver' => $v, 'd' => $date ) ); };

if ( 'mes' === $ver ) {
	$first = gmdate( 'Y-m-01', $ts );
	$start = gmdate( 'Y-m-d', strtotime( $first . ' -' . (int) gmdate( 'w', strtotime( $first ) ) . ' day' ) );
	$end   = gmdate( 'Y-m-d', strtotime( $start . ' +42 day' ) );
	$prev  = gmdate( 'Y-m-d', strtotime( $first . ' -1 month' ) );
	$next  = gmdate( 'Y-m-d', strtotime( $first . ' +1 month' ) );
	$title = ucfirst( $mnames[ (int) gmdate( 'n', $ts ) ] ) . ' de ' . gmdate( 'Y', $ts );
} elseif ( 'semana' === $ver ) {
	$start = gmdate( 'Y-m-d', strtotime( $d . ' -' . (int) gmdate( 'w', $ts ) . ' day' ) );
	$end   = gmdate( 'Y-m-d', strtotime( $start . ' +7 day' ) );
	$prev  = gmdate( 'Y-m-d', strtotime( $start . ' -7 day' ) );
	$next  = gmdate( 'Y-m-d', strtotime( $start . ' +7 day' ) );
	$title = lk_date( $start, 'd/m' ) . ' a ' . lk_date( gmdate( 'Y-m-d', strtotime( $start . ' +6 day' ) ), 'd/m/Y' );
} else {
	$start = $today;
	$end   = gmdate( 'Y-m-d', strtotime( $today . ' +60 day' ) );
	$prev  = $next = $d;
	$title = 'Próximos 60 dias';
}
$all = lk_rows( 'meetings', 'starts_at >= %s AND starts_at < %s' . lk_meetings_scope_sql(), array( $start . ' 00:00:00', $end . ' 00:00:00' ), 'starts_at ASC' );
$by  = array();
foreach ( $all as $m ) {
	$by[ gmdate( 'Y-m-d', strtotime( $m->starts_at ) ) ][] = $m;
}
$chip = function ( $m, $full = false ) {
	$c = $m->client_id ? lk_get( 'clients', $m->client_id ) : null;
	$t = gmdate( 'H:i', strtotime( $m->starts_at ) );
	return '<button type="button" class="rm rm--' . esc_attr( $m->status ) . '" data-open="editar-reuniao-crm" data-reuniao="' . esc_attr( wp_json_encode( lk_meeting_data( $m ) ) ) . '" title="' . esc_attr( $t . ' · ' . $m->title . ( $c ? ' · ' . lk_client_label( $c ) : '' ) ) . '"><b>' . esc_html( $t ) . '</b> <span>' . esc_html( $c ? lk_client_label( $c ) : $m->title ) . '</span>' . ( $full && $c ? '<small>' . esc_html( $m->title ) . '</small>' : '' ) . '</button>';
};
$gev = lk_gcal_events( $start, $end );
$gby = array();
foreach ( $gev as $e ) {
	$gby[ wp_date( 'Y-m-d', $e['start'] ) ][] = $e;
}
$gchip = function ( $e, $full = false ) {
	$t = wp_date( 'H:i', $e['start'] );
	return '<a class="rm rm--google" href="' . esc_url( $e['meet'] ? $e['meet'] : $e['link'] ) . '" target="_blank" rel="noopener" title="' . esc_attr( $t . ' · ' . $e['title'] . ' (Google Agenda)' ) . '"><b>' . esc_html( $t ) . '</b> <span>' . esc_html( $e['title'] ) . '</span>' . ( $full ? '<small>Google Agenda' . ( $e['meet'] ? ' · Meet' : '' ) . '</small>' : '' ) . '</a>';
};
$gconn = function_exists( 'lk_google_connected' ) && lk_google_connected();
lk_panel_start( 'Reuniões', 'reunioes', '<button type="button" class="btn btn--primary" data-open="nova-reuniao-crm" data-reuniao-date="' . esc_attr( $today ) . '">' . lk_icon( 'mais', 16 ) . '<span>Marcar reunião</span></button>' );
?>
<?php if ( ! $gconn ) : ?><div class="flash flash--warn">O <strong>Google Agenda não está conectado</strong> (ou a conexão expirou). Sem ele, as reuniões não ganham link do Meet e as antigas do Google não aparecem aqui. <?php if ( lk_is_admin() ) : ?><a class="btn btn--primary btn--sm" href="<?php echo esc_url( lk_panel_url( 'config' ) . '#google' ); ?>">Reconectar o Google</a><?php endif; ?></div><?php endif; ?>
<section class="card rcal">
	<div class="rcal-bar">
		<span class="rcal-nav">
			<?php if ( 'lista' !== $ver ) : ?><a class="btn btn--ghost btn--sm" href="<?php echo esc_url( $url( $ver, $prev ) ); ?>" aria-label="Anterior">‹</a><a class="btn btn--ghost btn--sm" href="<?php echo esc_url( $url( $ver, $today ) ); ?>">Hoje</a><a class="btn btn--ghost btn--sm" href="<?php echo esc_url( $url( $ver, $next ) ); ?>" aria-label="Próximo">›</a><?php endif; ?>
			<strong class="rcal-title"><?php echo esc_html( $title ); ?></strong>
		</span>
		<span class="rcal-views"><?php foreach ( array( 'mes' => 'Mês', 'semana' => 'Semana', 'lista' => 'Lista' ) as $vk => $vl ) : ?><a class="btn btn--sm btn--<?php echo $vk === $ver ? 'primary' : 'ghost'; ?>" href="<?php echo esc_url( $url( $vk, $d ) ); ?>"><?php echo esc_html( $vl ); ?></a><?php endforeach; ?></span>
	</div>

	<?php if ( 'mes' === $ver ) : ?>
		<div class="rmonth">
			<?php foreach ( $dnames as $dn ) : ?><div class="rmonth-h"><?php echo esc_html( $dn ); ?></div><?php endforeach; ?>
			<?php for ( $i = 0; $i < 42; $i++ ) : $day = gmdate( 'Y-m-d', strtotime( $start . ' +' . $i . ' day' ) ); $in = gmdate( 'Y-m', strtotime( $day ) ) === gmdate( 'Y-m', $ts ); $list = $by[ $day ] ?? array(); ?>
				<div class="rday<?php echo $in ? '' : ' is-out'; ?><?php echo $day === $today ? ' is-today' : ''; ?>">
					<span class="rday-n"><?php echo esc_html( (int) gmdate( 'j', strtotime( $day ) ) ); ?></span>
					<button type="button" class="rday-add" data-open="nova-reuniao-crm" data-reuniao-date="<?php echo esc_attr( $day ); ?>" aria-label="Marcar reunião em <?php echo esc_attr( lk_date( $day ) ); ?>" title="Marcar reunião neste dia">+</button>
					<?php
					$cells = array();
					foreach ( $list as $m ) {
						$cells[] = array( strtotime( $m->starts_at ), $chip( $m ) );
					}
					foreach ( ( $gby[ $day ] ?? array() ) as $e ) {
						$cells[] = array( (int) strtotime( wp_date( 'Y-m-d H:i:s', $e['start'] ) ), $gchip( $e ) );
					}
					usort( $cells, function ( $a, $b ) { return $a[0] <=> $b[0]; } );
					foreach ( array_slice( $cells, 0, 3 ) as $cell ) { echo $cell[1]; // phpcs:ignore WordPress.Security.EscapeOutput
					}
					?>
					<?php if ( count( $cells ) > 3 ) : ?><a class="rday-more" href="<?php echo esc_url( $url( 'semana', $day ) ); ?>">+<?php echo (int) ( count( $cells ) - 3 ); ?> mais</a><?php endif; ?>
				</div>
			<?php endfor; ?>
		</div>

	<?php elseif ( 'semana' === $ver ) : $h0 = 7; $h1 = 21; $px = 46; ?>
		<div class="rweek" style="--px:<?php echo (int) $px; ?>px;--hrs:<?php echo (int) ( $h1 - $h0 ); ?>">
			<div class="rweek-head"><span></span><?php for ( $i = 0; $i < 7; $i++ ) : $day = gmdate( 'Y-m-d', strtotime( $start . ' +' . $i . ' day' ) ); ?><a class="rweek-d<?php echo $day === $today ? ' is-today' : ''; ?>" href="<?php echo esc_url( $url( 'semana', $day ) ); ?>"><small><?php echo esc_html( $dnames[ $i ] ); ?></small><b><?php echo esc_html( (int) gmdate( 'j', strtotime( $day ) ) ); ?></b></a><?php endfor; ?></div>
			<div class="rweek-body">
				<div class="rweek-hours"><?php for ( $h = $h0; $h < $h1; $h++ ) : ?><span><?php echo esc_html( sprintf( '%02d:00', $h ) ); ?></span><?php endfor; ?></div>
				<?php for ( $i = 0; $i < 7; $i++ ) : $day = gmdate( 'Y-m-d', strtotime( $start . ' +' . $i . ' day' ) ); ?>
					<div class="rweek-col<?php echo $day === $today ? ' is-today' : ''; ?>">
						<?php for ( $h = $h0; $h < $h1; $h++ ) : ?><button type="button" class="rweek-slot" data-open="nova-reuniao-crm" data-reuniao-date="<?php echo esc_attr( $day ); ?>" data-reuniao-time="<?php echo esc_attr( sprintf( '%02d:00', $h ) ); ?>" aria-label="Marcar às <?php echo esc_attr( sprintf( '%02d:00', $h ) ); ?>"></button><?php endfor; ?>
						<?php foreach ( ( $by[ $day ] ?? array() ) as $m ) : $mt = strtotime( $m->starts_at ); $mins = ( (int) gmdate( 'G', $mt ) - $h0 ) * 60 + (int) gmdate( 'i', $mt ); if ( $mins < 0 ) { $mins = 0; } ?>
							<div class="rweek-ev" style="top:calc(<?php echo (int) $mins; ?> / 60 * var(--px));height:calc(<?php echo (int) max( 30, (int) $m->duration ); ?> / 60 * var(--px) - 2px)"><?php echo $chip( $m, true ); // phpcs:ignore WordPress.Security.EscapeOutput ?></div>
						<?php endforeach; ?>
						<?php foreach ( ( $gby[ $day ] ?? array() ) as $e ) : $mins = max( 0, ( (int) wp_date( 'G', $e['start'] ) - $h0 ) * 60 + (int) wp_date( 'i', $e['start'] ) ); $dur = max( 30, (int) round( ( $e['end'] - $e['start'] ) / 60 ) ); ?>
							<div class="rweek-ev" style="top:calc(<?php echo (int) $mins; ?> / 60 * var(--px));height:calc(<?php echo (int) $dur; ?> / 60 * var(--px) - 2px)"><?php echo $gchip( $e, true ); // phpcs:ignore WordPress.Security.EscapeOutput ?></div>
						<?php endforeach; ?>
					</div>
				<?php endfor; ?>
			</div>
		</div>

	<?php else : ?>
		<?php if ( ! $all && ! $gev ) : ?><div class="empty"><h3>Nenhuma reunião nos próximos 60 dias</h3><p class="muted">Clique em <strong>Marcar reunião</strong> para começar.</p></div><?php endif; ?>
		<?php $last = ''; $rows = array();
		foreach ( $all as $m ) {
			$rows[] = array( strtotime( $m->starts_at ), gmdate( 'Y-m-d', strtotime( $m->starts_at ) ), lk_meeting_row_html( $m ) );
		}
		foreach ( $gev as $e ) {
			$rows[] = array( (int) strtotime( wp_date( 'Y-m-d H:i:s', $e['start'] ) ), wp_date( 'Y-m-d', $e['start'] ), '<li class="meet"><span class="meet-time">' . esc_html( wp_date( 'H:i', $e['start'] ) ) . '</span><span class="meet-main"><strong>' . esc_html( $e['title'] ) . '</strong><small>Google Agenda</small></span><span class="meet-btns">' . ( $e['meet'] ? '<a class="btn btn--primary btn--sm" href="' . esc_url( $e['meet'] ) . '" target="_blank" rel="noopener">Entrar no Meet</a>' : '' ) . '</span></li>' );
		}
		usort( $rows, function ( $a, $b ) { return $a[0] <=> $b[0]; } );
		foreach ( $rows as $row ) : $day = $row[1]; if ( $day !== $last ) { if ( $last ) { echo '</ul>'; } $dd = lk_days_until( $day ); echo '<h4 class="rlist-day">' . esc_html( 0 === $dd ? 'Hoje' : ( 1 === $dd ? 'Amanhã' : ucfirst( lk_date_long( $day ) ) ) ) . '</h4><ul class="meetings">'; $last = $day; } echo $row[2]; // phpcs:ignore WordPress.Security.EscapeOutput ?><?php endforeach; if ( $last ) { echo '</ul>'; } ?>
	<?php endif; ?>
</section>
<p class="muted small">Dica: no <strong>Mês</strong> clique no <strong>+</strong> do dia; na <strong>Semana</strong> clique no horário vazio. Ao marcar com um cliente, ele recebe o e-mail com o link e o lembrete 10 minutos antes.</p>
<?php echo lk_meeting_modals_html(); // phpcs:ignore WordPress.Security.EscapeOutput ?>
<?php
lk_panel_end();

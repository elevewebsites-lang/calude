<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
$view  = isset( $_GET['ver'] ) ? sanitize_key( $_GET['ver'] ) : ''; // phpcs:ignore WordPress.Security.NonceVerification
$cols  = ap_columns();
$ready = ap_ready_column();

$actions  = '<div class="seg"><a href="' . esc_url( ap_panel_url( 'pedidos' ) ) . '" class="' . ( '' === $view ? 'is-active' : '' ) . '">Quadro</a><a href="' . esc_url( ap_panel_url( 'pedidos', 0, array( 'ver' => 'todos' ) ) ) . '" class="' . ( 'todos' === $view ? 'is-active' : '' ) . '">Lista</a><a href="' . esc_url( ap_panel_url( 'pedidos', 0, array( 'ver' => 'arquivados' ) ) ) . '" class="' . ( 'arquivados' === $view ? 'is-active' : '' ) . '">Arquivados</a></div>';
$actions .= '<a class="btn btn--ghost" href="' . esc_url( ap_panel_url( 'novo-pedido' ) ) . '">' . ap_icon( 'mais', 16 ) . '<span>Pedido manual</span></a>';
$actions .= '<a class="btn btn--primary" href="' . esc_url( ap_panel_url( 'orcamento' ) ) . '">' . ap_icon( 'mais', 16 ) . '<span>Novo orçamento</span></a>';
ap_panel_start( 'Pedidos', 'pedidos', $actions );

if ( '' === $view ) :
	$projects = ap_projects( 'p.archived = 0' );
	$internal = ap_board_internal();
	$board    = array();
	foreach ( $internal as $slug => $name ) {
		$board[ $slug ] = array( $name, 'internal' );
	}
	foreach ( $cols as $slug => $name ) {
		$board[ $slug ] = array( $name, 'stage' );
	}
	$by_col = array_fill_keys( array_keys( $board ), array() );
	$first  = array_keys( $cols )[0];
	foreach ( $projects as $p ) {
		if ( ! empty( $p->board_col ) && isset( $internal[ $p->board_col ] ) ) {
			$by_col[ $p->board_col ][] = $p;
		} else {
			$by_col[ isset( $cols[ $p->status ] ) ? $p->status : $first ][] = $p;
		}
	}
	$last_stage = ap_last_column();
	?>
	<p class="muted small hint">Arraste os cards entre as etapas. Em <strong><?php echo esc_html( $cols[ $ready ] ?? 'Pronto' ); ?></strong>, o cliente recebe o e-mail "seu pedido está pronto" com as fotos, e o card mostra o botão de WhatsApp.</p>
	<div class="kanban kanban--editable" data-kanban="project">
		<?php foreach ( $board as $slug => $info ) : ?>
			<?php list( $name, $kind ) = $info; ?>
			<section class="kcol tone-<?php echo esc_attr( 'internal' === $kind ? 'x' : ap_stage_tone( $slug ) ); ?><?php echo 'internal' === $kind ? ' kcol--internal' : ''; ?><?php echo $slug === $ready ? ' kcol--ready' : ''; ?>" data-col="<?php echo esc_attr( $slug ); ?>" data-kind="<?php echo esc_attr( $kind ); ?>"<?php echo $slug === $last_stage ? ' data-last' : ''; ?>>
				<header class="kcol-head">
					<h3 class="kcol-title" data-col-name title="Clique para renomear"><?php echo esc_html( $name ); ?></h3>
					<?php if ( 'internal' === $kind ) : ?><span class="kcol-tag" title="Coluna interna: só organiza o seu quadro.">interna</span><?php endif; ?>
					<span class="kcount"><?php echo (int) count( $by_col[ $slug ] ); ?></span>
					<span class="kcol-tools">
						<button type="button" class="kcol-btn" data-col-op="left" title="Mover para a esquerda">‹</button>
						<button type="button" class="kcol-btn" data-col-op="right" title="Mover para a direita">›</button>
						<?php if ( $slug !== $last_stage ) : ?><button type="button" class="kcol-btn" data-col-op="delete" title="Excluir coluna">×</button><?php endif; ?>
					</span>
				</header>
				<div class="kcol-body" data-status="<?php echo esc_attr( $slug ); ?>">
					<?php foreach ( $by_col[ $slug ] as $p ) : ?>
						<?php
						$photos = ap_order_photos( $p );
						$thumb  = $photos ? ( $photos[0]['thumb'] ?? $photos[0]['url'] ) : '';
						if ( ! $thumb && $p->quote_id && ( $q = ap_get( 'quotes', $p->quote_id ) ) ) { // phpcs:ignore
							$qi    = ap_json( $q->images );
							$thumb = $qi ? ( $qi[0]['thumb'] ?? $qi[0]['url'] ) : '';
						}
						if ( ! $thumb ) {
							foreach ( ap_json( $p->items ) as $oi ) {
								foreach ( (array) ( $oi['files'] ?? array() ) as $fl ) {
									if ( ! empty( $fl['thumb'] ) ) {
										$thumb = $fl['thumb'];
										break 2;
									}
								}
							}
						}
						$wa = $slug === $ready ? ap_order_wa_link( $p ) : '';
						?>
						<?php $pay = ap_order_payment( $p ); ?>
						<article class="kcard kcard--order tone-<?php echo esc_attr( ap_stage_tone( $p->status ) ); ?>" draggable="true" data-id="<?php echo (int) $p->id; ?>" data-due="<?php echo esc_attr( $pay['due'] ); ?>" data-ptitle="<?php echo esc_attr( '#' . $p->id . ' · ' . $p->title ); ?>" data-client="<?php echo esc_attr( ap_project_client_label( $p ) ); ?>" data-href="<?php echo esc_url( ap_panel_url( 'pedido', $p->id ) ); ?>">
							<?php if ( $thumb ) : ?><img class="kcard-thumb" src="<?php echo esc_url( $thumb ); ?>" alt="" loading="lazy"><?php endif; ?>
							<div class="kcard-top">
								<span class="badge">#<?php echo (int) $p->id; ?></span>
								<?php if ( $p->urgent ) : ?><span class="badge badge--late">URGENTE</span><?php endif; ?>
								<?php if ( 'problema' === $p->art_status ) : ?><span class="badge badge--warn">arte com ajuste</span><?php elseif ( 'aprovada' === $p->art_status ) : ?><span class="badge badge--ok">arte ok</span><?php endif; ?>
								<span class="badge badge--<?php echo 'envio' === $p->delivery_mode ? 'ship' : 'pickup'; ?>"><?php echo 'envio' === $p->delivery_mode ? 'Envio' : 'Retirada'; ?></span>
								<?php if ( 'internal' === $kind && isset( $cols[ $p->status ] ) ) : ?><span class="badge"><?php echo esc_html( $cols[ $p->status ] ); ?></span><?php endif; ?>
							</div>
							<div class="kcard-tags"><?php echo ap_stage_badge( $p->status ); // phpcs:ignore ?><?php echo ap_pay_badge( $p ); // phpcs:ignore ?></div>
							<h4><?php echo esc_html( $p->title ); ?></h4>
							<p class="kcard-sub"><?php echo esc_html( ap_project_client_label( $p ) ); ?></p>
							<?php if ( $slug === $ready && $pay['due'] > 0 ) : ?><p class="kcard-charge">💰 Cobrar <?php echo esc_html( ap_money( $pay['due'] ) ); ?> na retirada</p><?php endif; ?>
							<div class="kcard-foot">
								<span class="money small"><?php echo ap_can( 'financeiro' ) ? esc_html( ap_money( $p->value ) ) : ''; ?></span>
								<?php echo ap_due_badge( $p->due_date, $last_stage === $p->status || $ready === $p->status ); // phpcs:ignore ?>
							</div>
							<?php if ( $wa ) : ?>
								<a class="btn btn--wa btn--sm btn--block kcard-wa" href="<?php echo esc_url( $wa ); ?>" target="_blank" rel="noopener"><?php echo ap_icon( 'whatsapp', 14 ); // phpcs:ignore ?><span>Avisar que está pronto</span></a>
								<?php if ( $p->ready_notified ) : ?><small class="muted kcard-note">E-mail enviado <?php echo esc_html( ap_ago( $p->ready_notified ) ); ?></small><?php endif; ?>
							<?php endif; ?>
						</article>
					<?php endforeach; ?>
				</div>
			</section>
		<?php endforeach; ?>
		<section class="kcol kcol--add">
			<button type="button" class="kcol-add-btn" data-open="nova-coluna"><?php echo ap_icon( 'mais', 16 ); // phpcs:ignore ?><span>Coluna</span></button>
		</section>
	</div>

	<?php ap_modal_start( 'nova-coluna', 'Nova coluna' ); ?>
		<form class="stack" data-col-add>
			<?php ap_input( 'col_name', 'Nome da coluna', '', 'text', 'required maxlength="40" placeholder="Ex.: Hoje"' ); ?>
			<div class="choice-list">
				<label class="choice"><input type="radio" name="col_kind" value="internal" checked><span><strong>Coluna interna</strong><small>Só organiza o seu quadro (ex.: "Hoje", "Esta semana"). A etapa do pedido não muda e o cliente não recebe e-mail.</small></span></label>
				<label class="choice"><input type="radio" name="col_kind" value="stage"><span><strong>Etapa do pedido</strong><small>O cliente vê na área dele. Entra antes da última etapa.</small></span></label>
			</div>
			<div class="form-actions"><button type="button" class="btn btn--ghost" data-close>Cancelar</button><button type="submit" class="btn btn--primary">Criar coluna</button></div>
		</form>
	<?php ap_modal_end(); ?>
	<?php
else :
	$projects = ap_projects( 'arquivados' === $view ? 'p.archived = 1' : '1=1' );
	?>
	<div class="toolbar"><input type="search" class="filter" placeholder="Filtrar pedidos…" data-filter=".table-row"></div>
	<?php if ( ! $projects ) : ?>
		<div class="empty empty--big"><?php echo ap_icon( 'projetos', 28 ); // phpcs:ignore ?><h3>Nada por aqui</h3></div>
	<?php else : ?>
		<div class="table table--orders">
			<div class="table-row table-head"><span>Pedido</span><span>Etapa</span><span>Entrega</span><span>Valor</span><span>Lucro</span></div>
			<?php foreach ( $projects as $p ) : ?>
				<?php $cost = $p->cost_real > 0 ? $p->cost_real : $p->cost_estimated; ?>
				<a class="table-row" href="<?php echo esc_url( ap_panel_url( 'pedido', $p->id ) ); ?>">
					<span class="cell-main"><span><strong>#<?php echo (int) $p->id; ?> · <?php echo esc_html( $p->title ); ?></strong><small><?php echo esc_html( ap_project_client_label( $p ) ); ?></small></span></span>
					<span data-label="Etapa"><?php echo ap_stage_badge( $p->status ); // phpcs:ignore ?> <?php echo ap_pay_badge( $p ); // phpcs:ignore ?><?php echo $p->archived ? ' <small>arquivado</small>' : ''; ?></span>
					<span data-label="Entrega"><?php echo esc_html( $p->due_date ? ap_date( $p->due_date ) : '—' ); ?></span>
					<span data-label="Valor" class="money"><?php echo ap_can( 'financeiro' ) ? esc_html( ap_money( $p->value ) ) : '—'; ?></span>
					<span data-label="Lucro" class="money"><?php echo ap_can( 'financeiro' ) && $cost > 0 ? esc_html( ap_money( $p->value - $p->ship_price - $cost ) ) : '—'; ?></span>
				</a>
			<?php endforeach; ?>
		</div>
	<?php endif; ?>
	<?php
endif;

ap_modal_start( 'novo-pedido', 'Pedido manual' );
ap_form( 'order_create', 'stack' );
?>
	<p class="muted small">Para venda de balcão ou WhatsApp (sem proposta online).</p>
	<?php ap_input( 'title', 'O que é', '', 'text', 'required placeholder="Ex.: 20 cartões de visita"' ); ?>
	<?php ap_select( 'client_id', 'Cliente', ap_client_options( '+ Cliente novo / sem cadastro' ), isset( $_GET['cliente'] ) ? absint( $_GET['cliente'] ) : '', 'data-new-client' ); // phpcs:ignore ?>
	<div class="new-client grid-2">
		<?php ap_input( 'new_name', 'Nome' ); ?>
		<?php ap_input( 'new_whatsapp', 'WhatsApp', '', 'tel', 'data-mask="phone"' ); ?>
		<?php ap_input( 'new_email', 'E-mail', '', 'email' ); ?>
		<?php ap_select( 'new_source', 'Origem', ap_list_options( 'origens' ) ); ?>
	</div>
	<div class="grid-2">
		<?php ap_input( 'value', 'Valor (R$)', '', 'text', 'inputmode="decimal" data-money' ); ?>
		<?php ap_select( 'method', 'Forma de pagamento', ap_list_options( 'metodos' ) ); ?>
		<?php ap_select( 'delivery_mode', 'Entrega', array( 'retirada' => 'Retirada', 'envio' => 'Envio' ) ); ?>
		<?php ap_input( 'due_date', 'Prazo', '', 'date' ); ?>
	</div>
	<?php ap_check( 'paid', 'Já está pago (vai direto para a fila)', true ); ?>
	<?php ap_input( 'notes', 'Observações', '', 'textarea', 'rows="3"' ); ?>
	<div class="form-actions"><button type="button" class="btn btn--ghost" data-close>Cancelar</button><button type="submit" class="btn btn--primary">Criar pedido</button></div>
</form>
<?php
ap_modal_end();
ap_panel_end();

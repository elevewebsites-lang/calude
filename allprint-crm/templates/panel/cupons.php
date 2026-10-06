<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
global $wpdb;
$coupons = ap_rows( 'coupons', '1=1', array(), 'active DESC, id DESC' );
$edit    = isset( $_GET['editar'] ) ? ap_get( 'coupons', absint( $_GET['editar'] ) ) : null; // phpcs:ignore WordPress.Security.NonceVerification
$clients = ap_clients();
$bal     = $wpdb->get_results( 'SELECT client_id, SUM(amount) AS s FROM ' . ap_table( 'credits' ) . ' GROUP BY client_id HAVING s <> 0' ); // phpcs:ignore WordPress.DB.PreparedSQL
$ledger  = $wpdb->get_results( 'SELECT cr.*, c.company, c.name FROM ' . ap_table( 'credits' ) . ' cr LEFT JOIN ' . ap_table( 'clients' ) . ' c ON c.id = cr.client_id ORDER BY cr.id DESC LIMIT 30' ); // phpcs:ignore WordPress.DB.PreparedSQL
ap_panel_start( 'Cupons e crédito', 'cupons' );
?>
<div class="grid-2 cup">
	<section class="card">
		<div class="card-head"><h3><?php echo $edit ? 'Editar cupom' : 'Novo cupom'; ?></h3></div>
		<?php ap_form( 'coupon_save', 'form-stack' ); ?>
			<input type="hidden" name="id" value="<?php echo (int) ( $edit->id ?? 0 ); ?>">
			<div class="grid-2">
				<?php ap_input( 'code', 'Código (o cliente digita)', $edit->code ?? '', 'text', 'required placeholder="BEMVINDO10" style="text-transform:uppercase"' ); ?>
				<?php ap_input( 'label', 'Descrição interna', $edit->label ?? '', 'text', 'placeholder="Campanha de outubro"' ); ?>
				<?php ap_select( 'kind', 'Tipo de desconto', array( 'percent' => 'Percentual (%)', 'fixed' => 'Valor fixo (R$)' ), $edit->kind ?? 'percent' ); ?>
				<?php ap_input( 'value', 'Valor', isset( $edit ) ? str_replace( '.', ',', $edit->value ) : '', 'text', 'required inputmode="decimal"' ); ?>
				<?php ap_input( 'min_total', 'Pedido mínimo (R$)', isset( $edit ) ? str_replace( '.', ',', $edit->min_total ) : '', 'text', 'inputmode="decimal" placeholder="0"' ); ?>
				<?php ap_select( 'client_id', 'Só para este cliente (opcional)', array( 0 => 'Qualquer cliente' ) + ap_client_options( '' ), $edit->client_id ?? 0 ); ?>
				<?php ap_input( 'max_uses', 'Limite de usos no total (0 = sem limite)', $edit->max_uses ?? 0, 'number', 'min="0"' ); ?>
				<?php ap_input( 'per_client', 'Usos por cliente (0 = sem limite)', $edit->per_client ?? 1, 'number', 'min="0"' ); ?>
				<?php ap_input( 'starts_at', 'Começa em', $edit->starts_at ?? '', 'date' ); ?>
				<?php ap_input( 'expires_at', 'Vence em', $edit->expires_at ?? '', 'date' ); ?>
			</div>
			<?php ap_check( 'active', 'Cupom ligado', ! $edit || $edit->active ); ?>
			<div class="form-actions"><button class="btn btn--primary" type="submit">Salvar cupom</button><?php if ( $edit ) : ?> <a class="btn btn--ghost" href="<?php echo esc_url( ap_panel_url( 'cupons' ) ); ?>">Cancelar</a><?php endif; ?></div>
		</form>
	</section>

	<section class="card">
		<div class="card-head"><h3>Dar ou tirar crédito na loja</h3></div>
		<p class="muted small">O crédito vira desconto automático no próximo pedido do cliente. Boas-vindas (<?php echo esc_html( ap_money( ap_num_setting( 'credito_boas_vindas' ) ) ); ?>) é lançado sozinho quando o cadastro é aprovado: ajuste em Configurações.</p>
		<?php ap_form( 'credit_add', 'form-stack' ); ?>
			<?php ap_select( 'client_id', 'Cliente', ap_client_options( 'Escolha…' ), isset( $_GET['cliente'] ) ? absint( $_GET['cliente'] ) : '' ); // phpcs:ignore WordPress.Security.NonceVerification ?>
			<div class="grid-2">
				<?php ap_select( 'op', 'Operação', array( 'dar' => 'Dar crédito', 'tirar' => 'Tirar crédito' ) ); ?>
				<?php ap_input( 'amount', 'Valor (R$)', '', 'text', 'required inputmode="decimal"' ); ?>
				<?php ap_input( 'validade_dias', 'Vale por (dias, 0 = sem validade)', '0', 'number', 'min="0"' ); ?>
				<?php ap_input( 'note', 'Motivo', '', 'text', 'placeholder="Ex.: estorno do pedido #123"' ); ?>
			</div>
			<div class="form-actions"><button class="btn btn--primary" type="submit">Lançar</button></div>
		</form>
	</section>
</div>

<section class="card">
	<div class="card-head"><h3>Cupons</h3><span class="muted small"><?php echo count( $coupons ); ?></span></div>
	<?php if ( ! $coupons ) : ?><p class="muted">Nenhum cupom ainda.</p><?php else : ?>
	<table class="table"><thead><tr><th>Código</th><th>Desconto</th><th>Regras</th><th>Usos</th><th></th></tr></thead><tbody>
		<?php foreach ( $coupons as $c ) : ?>
			<tr>
				<td><strong><?php echo esc_html( $c->code ); ?></strong><br><small class="muted"><?php echo esc_html( $c->label ); ?></small><?php echo $c->active ? '' : ' <span class="pill pill--late">desligado</span>'; // phpcs:ignore ?></td>
				<td><?php echo 'percent' === $c->kind ? esc_html( rtrim( rtrim( number_format( $c->value, 2, ',', '' ), '0' ), ',' ) . '%' ) : esc_html( ap_money( $c->value ) ); ?></td>
				<td class="small muted"><?php echo esc_html( implode( ' · ', array_filter( array( $c->min_total > 0 ? 'mín. ' . ap_money( $c->min_total ) : '', $c->client_id ? 'só ' . ap_client_label( ap_get( 'clients', $c->client_id ) ) : '', $c->expires_at ? 'vence ' . date_i18n( 'd/m/Y', strtotime( $c->expires_at ) ) : '', $c->per_client ? $c->per_client . ' uso(s) por cliente' : '' ) ) ) ); ?></td>
				<td><?php echo (int) $c->uses . ( $c->max_uses ? ' / ' . (int) $c->max_uses : '' ); ?></td>
				<td align="right"><a class="btn btn--ghost btn--sm" href="<?php echo esc_url( add_query_arg( 'editar', $c->id, ap_panel_url( 'cupons' ) ) ); ?>">Editar</a> <?php ap_action_button( 'coupon_delete', array( 'id' => $c->id ), 'Excluir', 'btn btn--ghost btn--sm', 'Excluir este cupom?' ); ?></td>
			</tr>
		<?php endforeach; ?>
	</tbody></table>
	<?php endif; ?>
</section>

<section class="card">
	<div class="card-head"><h3>Saldos de crédito</h3></div>
	<?php if ( ! $bal ) : ?><p class="muted">Ninguém tem crédito agora.</p><?php else : ?>
	<table class="table"><tbody>
		<?php foreach ( $bal as $b ) : $cl = ap_get( 'clients', $b->client_id ); ?>
			<tr><td><a href="<?php echo esc_url( ap_panel_url( 'cliente', $b->client_id ) ); ?>"><?php echo esc_html( ap_client_label( $cl ) ); ?></a></td><td align="right"><strong><?php echo esc_html( ap_money( ap_credit_balance( $b->client_id ) ) ); ?></strong></td></tr>
		<?php endforeach; ?>
	</tbody></table>
	<?php endif; ?>
	<?php if ( $ledger ) : ?>
		<h4 class="brand-h">Últimos lançamentos</h4>
		<table class="table"><tbody>
			<?php foreach ( $ledger as $l ) : ?>
				<tr><td class="small muted"><?php echo esc_html( date_i18n( 'd/m/Y', strtotime( $l->created_at ) ) ); ?></td><td><?php echo esc_html( $l->company ? $l->company : $l->name ); ?></td><td class="small"><?php echo esc_html( $l->note ); ?></td><td align="right" class="<?php echo $l->amount < 0 ? 'text-late' : ''; ?>"><?php echo esc_html( ( $l->amount > 0 ? '+' : '' ) . ap_money( $l->amount ) ); ?></td></tr>
			<?php endforeach; ?>
		</tbody></table>
	<?php endif; ?>
</section>
<?php
ap_panel_end();

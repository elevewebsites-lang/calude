<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
$tab    = isset( $_GET['aba'] ) && 'desejos' === $_GET['aba'] ? 'desejo' : 'compra'; // phpcs:ignore WordPress.Security.NonceVerification
$items  = lk_rows( 'shopping', "status = 'aberto' AND kind = %s", array( $tab ), "FIELD(priority,'alta','media','baixa'), id DESC" );
$bought = lk_rows( 'shopping', "status = 'comprado' AND kind = %s", array( $tab ), 'bought_at DESC LIMIT 10' );
$sum    = 0;
foreach ( $items as $it ) {
	$sum += $it->price * $it->qty;
}
$prio = array( 'alta' => 'Alta', 'media' => 'Média', 'baixa' => 'Baixa' );
lk_panel_start( 'Compras e desejos', 'compras', '<button type="button" class="btn btn--primary" data-open="novo-item">' . lk_icon( 'mais', 16 ) . '<span>' . ( 'desejo' === $tab ? 'Desejo' : 'Item' ) . '</span></button>' );
?>
<div class="toolbar">
	<div class="seg">
		<a href="<?php echo esc_url( lk_panel_url( 'compras' ) ); ?>" class="<?php echo 'compra' === $tab ? 'is-active' : ''; ?>">Preciso comprar</a>
		<a href="<?php echo esc_url( lk_panel_url( 'compras', 0, array( 'aba' => 'desejos' ) ) ); ?>" class="<?php echo 'desejo' === $tab ? 'is-active' : ''; ?>">Lista de desejos</a>
	</div>
	<span class="muted small">Total da lista: <strong class="money"><?php echo esc_html( lk_money( $sum ) ); ?></strong></span>
</div>
<p class="muted small hint"><?php echo 'desejo' === $tab ? 'Upgrades e equipamentos que você quer (outra impressora, AMS, secador de filamento, bico endurecido…). Cole o link do Mercado Livre ou da Shopee e o nome vem sozinho.' : 'Reposição: filamentos e insumos abaixo do mínimo entram aqui sozinhos. Ao marcar "Comprei", sai daqui, vai para o financeiro e o estoque é atualizado.'; ?></p>

<?php if ( ! $items ) : ?>
	<div class="empty"><?php echo lk_icon( 'carrinho', 26 ); // phpcs:ignore ?><h3><?php echo 'desejo' === $tab ? 'Nenhum desejo por enquanto' : 'Nada para comprar agora'; ?></h3></div>
<?php else : ?>
	<div class="wish-grid">
		<?php foreach ( $items as $it ) : ?>
			<article class="wish wish--<?php echo esc_attr( $it->priority ); ?>">
				<div class="wish-head">
					<em class="badge badge--prio-<?php echo esc_attr( $it->priority ); ?>"><?php echo esc_html( $prio[ $it->priority ] ?? '' ); ?></em>
					<?php if ( $it->item_type ) : ?><em class="badge">automático</em><?php endif; ?>
					<button type="button" class="icon-btn" data-open="sh-<?php echo (int) $it->id; ?>" title="Editar"><?php echo lk_icon( 'editar', 14 ); // phpcs:ignore ?></button>
				</div>
				<h4><?php echo esc_html( $it->title ); ?></h4>
				<?php if ( $it->notes ) : ?><p class="muted small"><?php echo esc_html( $it->notes ); ?></p><?php endif; ?>
				<div class="wish-foot">
					<b class="money"><?php echo $it->price > 0 ? esc_html( lk_money( $it->price * $it->qty ) ) : '—'; ?></b>
					<?php if ( $it->qty > 1 ) : ?><span class="muted small"><?php echo esc_html( rtrim( rtrim( $it->qty, '0' ), '.' ) ); ?> un.</span><?php endif; ?>
				</div>
				<div class="row-btns">
					<?php if ( $it->link ) : ?><a class="btn btn--ghost btn--sm" href="<?php echo esc_url( $it->link ); ?>" target="_blank" rel="noopener">Abrir link ↗</a><?php endif; ?>
					<button type="button" class="btn btn--primary btn--sm" data-open="buy-sh-<?php echo (int) $it->id; ?>">Comprei</button>
				</div>
			</article>

			<?php lk_modal_start( 'buy-sh-' . $it->id, 'Comprei: ' . $it->title ); ?>
				<?php lk_form( 'shopping_bought', 'stack' ); ?>
					<input type="hidden" name="id" value="<?php echo (int) $it->id; ?>">
					<?php lk_input( 'paid', 'Quanto pagou no total (R$)', $it->price > 0 ? number_format( $it->price * $it->qty, 2, ',', '.' ) : '', 'text', 'inputmode="decimal" data-money' ); ?>
					<?php if ( 'supply' === $it->item_type ) : ?><?php lk_input( 'qty_in', 'Quantas unidades vieram (entra no estoque)', '', 'text', 'inputmode="decimal"' ); ?><?php endif; ?>
					<?php lk_check( 'lancar', 'Lançar no financeiro', true ); ?>
					<div class="form-actions"><button type="button" class="btn btn--ghost" data-close>Cancelar</button><button type="submit" class="btn btn--primary">Confirmar</button></div>
				</form>
			<?php lk_modal_end(); ?>

			<?php lk_modal_start( 'sh-' . $it->id, 'Editar' ); ?>
				<?php lk_form( 'shopping_save', 'stack' ); ?>
					<input type="hidden" name="id" value="<?php echo (int) $it->id; ?>">
					<input type="hidden" name="kind" value="<?php echo esc_attr( $it->kind ); ?>">
					<?php lk_input( 'title', 'Nome', $it->title ); ?>
					<?php lk_input( 'link', 'Link', $it->link, 'url' ); ?>
					<div class="grid-3">
						<?php lk_input( 'price', 'Preço (R$)', $it->price > 0 ? number_format( $it->price, 2, ',', '.' ) : '', 'text', 'inputmode="decimal" data-money' ); ?>
						<?php lk_input( 'qty', 'Qtd.', rtrim( rtrim( $it->qty, '0' ), '.' ), 'text', 'inputmode="decimal"' ); ?>
						<?php lk_select( 'priority', 'Prioridade', $prio, $it->priority ); ?>
					</div>
					<?php lk_input( 'notes', 'Observação', $it->notes ); ?>
					<div class="form-actions">
						<?php lk_action_button( 'shopping_delete', array( 'id' => $it->id ), 'Excluir', 'btn btn--link btn--sm', 'Remover da lista?' ); ?>
						<button type="button" class="btn btn--ghost" data-close>Cancelar</button><button type="submit" class="btn btn--primary">Salvar</button>
					</div>
				</form>
			<?php lk_modal_end(); ?>
		<?php endforeach; ?>
	</div>
<?php endif; ?>

<?php if ( $bought ) : ?>
	<section class="card">
		<div class="card-head"><h3>Comprados recentemente</h3></div>
		<div class="items-list">
			<?php foreach ( $bought as $b ) : ?>
				<div class="items-row"><span><?php echo esc_html( $b->title ); ?><small><?php echo esc_html( lk_date( $b->bought_at ) ); ?></small></span><span class="money"><?php echo $b->price > 0 ? esc_html( lk_money( $b->price * $b->qty ) ) : ''; ?></span></div>
			<?php endforeach; ?>
		</div>
	</section>
<?php endif; ?>

<?php lk_modal_start( 'novo-item', 'desejo' === $tab ? 'Novo desejo' : 'Adicionar à lista de compras' ); ?>
	<?php lk_form( 'shopping_save', 'stack' ); ?>
		<input type="hidden" name="kind" value="<?php echo esc_attr( $tab ); ?>">
		<?php lk_input( 'link', 'Link (Mercado Livre, Shopee, loja…)', '', 'url', 'placeholder="https://produto.mercadolivre.com.br/…"' ); ?>
		<?php lk_input( 'title', 'Nome (vazio = pega do link)', '' ); ?>
		<div class="grid-3">
			<?php lk_input( 'price', 'Preço (R$)', '', 'text', 'inputmode="decimal" data-money' ); ?>
			<?php lk_input( 'qty', 'Qtd.', '1', 'text', 'inputmode="decimal"' ); ?>
			<?php lk_select( 'priority', 'Prioridade', $prio, 'media' ); ?>
		</div>
		<?php lk_input( 'notes', 'Observação', '' ); ?>
		<div class="form-actions"><button type="button" class="btn btn--ghost" data-close>Cancelar</button><button type="submit" class="btn btn--primary">Adicionar</button></div>
	</form>
<?php lk_modal_end(); ?>
<?php
lk_panel_end();

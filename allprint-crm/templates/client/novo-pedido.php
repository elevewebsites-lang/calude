<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
if ( empty( $client->approved ) ) {
	ap_client_start( 'Novo pedido', $client );
	echo '<div class="empty empty--big">' . ap_icon( 'relogio', 28 ) . '<h3>Cadastro em análise</h3><p>Assim que a ' . esc_html( ap_setting( 'empresa' ) ) . ' aprovar o seu cadastro, você vê os preços e faz pedidos por aqui.</p></div>'; // phpcs:ignore
	ap_client_end();
	return;
}
$cfg    = ap_catalog_js();
$groups = ap_catalog_groups();
$prazo  = max( 1, (int) ap_setting( 'prazo_dias' ) );
$prev   = ap_next_business_day( ap_today(), $prazo );
ap_client_start( 'Novo pedido', $client );
?>
<script>window.AP_CAT = <?php echo wp_json_encode( $cfg ); ?>; window.AP_DRIVE = <?php echo ap_google_connected() ? 'true' : 'false'; ?>;</script>
<section class="chello">
	<span class="eyebrow">Novo pedido</span>
	<h1>Monte o seu pedido</h1>
	<p class="muted">Escolha o material, as medidas e os acabamentos, envie o arquivo e pague no Pix. Mínimo de <?php echo esc_html( str_replace( '.', ',', $cfg['minArea'] ) ); ?> m² por material (somando as peças do mesmo material). Corte simples incluso.</p>
</section>

<?php ap_form( 'client_order', 'order-grid' ); ?>
	<div class="order-items" data-order>
		<div class="oitem" data-oitem>
			<div class="oitem-head"><strong>Item <span data-n>1</span></strong><button type="button" class="icon-btn" data-oitem-del title="Remover">×</button></div>
			<label class="field"><span>Material</span>
				<select name="i[material][]" required data-f="material">
					<option value="">Escolha o material…</option>
					<?php foreach ( $groups as $g => $gl ) : ?>
						<optgroup label="<?php echo esc_attr( $gl ); ?>">
							<?php foreach ( $cfg['items'] as $id => $m ) : ?>
								<?php if ( $m['grp'] === $g ) : ?><option value="<?php echo (int) $id; ?>"><?php echo esc_html( $m['name'] . ' · ' . ap_money( $m['price'] ) . '/m²' ); ?></option><?php endif; ?>
							<?php endforeach; ?>
						</optgroup>
					<?php endforeach; ?>
				</select>
			</label>
			<p class="muted small" data-f="info"></p>
			<div class="grid-3">
				<label class="field"><span>Largura (cm)</span><input type="text" inputmode="decimal" name="i[w][]" required data-f="w" placeholder="100"></label>
				<label class="field"><span>Altura (cm)</span><input type="text" inputmode="decimal" name="i[h][]" required data-f="h" placeholder="100"></label>
				<label class="field"><span>Quantidade</span><input type="number" min="1" name="i[qty][]" value="1" data-f="qty"></label>
			</div>
			<div class="oitem-opts">
				<div class="oitem-eyelets" data-f="eyelets" hidden>
					<span class="small"><strong>Reforço e ilhós</strong> (<?php echo esc_html( ap_money( $cfg['eyelet'] ) ); ?>/m linear) nos lados:</span>
					<label class="chk"><input type="checkbox" name="i[st][0]" value="1" data-side="st"> Cima</label>
					<label class="chk"><input type="checkbox" name="i[sb][0]" value="1" data-side="sb"> Baixo</label>
					<label class="chk"><input type="checkbox" name="i[sl][0]" value="1" data-side="sl"> Esquerda</label>
					<label class="chk"><input type="checkbox" name="i[sr][0]" value="1" data-side="sr"> Direita</label>
				</div>
				<label class="chk" data-f="lamwrap" hidden><input type="checkbox" name="i[lam][0]" value="1" data-f="lam"> <strong>Laminação</strong> (+<?php echo esc_html( ap_money( $cfg['lam'] ) ); ?>/m²)</label>
				<label class="field field--inline" data-f="finishwrap" hidden><span>Acabamento</span><select name="i[finish][]" data-f="finish"><option value="Banner">Banner (bastão, ponteira e cordinha)</option><option value="Faixa">Faixa (ilhós nas pontas e bastão)</option></select></label>
				<label class="field field--inline"><span>Corte</span><select name="i[cut][]" data-f="cut"><option value="simples">Simples (incluso)</option><option value="complexo">Complexo / de quantidade</option></select></label>
			</div>
			<label class="field"><span>Observações para a produção (opcional)</span><textarea name="i[obs][]" rows="2" placeholder="Ex.: sangria de 2 cm, aplicar em vidro…"></textarea></label>
			<div class="upbox" data-upbox>
				<label class="drop drop--file"><input type="file" multiple accept=".pdf,.cdr,.jpg,.jpeg,.tif,.tiff,.png,.ai,.eps,.zip" data-upload><?php echo ap_icon( 'upload', 20 ); // phpcs:ignore ?><span><strong>Enviar o arquivo deste item</strong><small>PDF, CDR em curvas (v25) ou JPG 300 dpi no tamanho real · arquivos grandes são bem-vindos</small></span></label>
				<div class="upfiles" data-upfiles></div>
				<input type="hidden" name="i[files][]" value="[]" data-f="files">
			</div>
			<div class="oitem-foot"><span class="muted small" data-f="area"></span><strong data-f="price"></strong></div>
			<div class="oitem-warn" data-f="warn" hidden></div>
		</div>
		<button type="button" class="btn btn--ghost" data-oitem-add><?php echo ap_icon( 'mais', 16 ); // phpcs:ignore ?><span>Adicionar outro item</span></button>
	</div>

	<aside class="order-sum">
		<div class="card">
			<span class="eyebrow">Resumo</span>
			<div class="calc-lines" data-sum-lines><p class="muted small">Escolha o material e as medidas.</p></div>
			<div class="calc-lines"><div class="calc-total"><span>Total</span><b data-sum-total>R$ 0,00</b></div></div>
			<div class="order-eta">
				<?php echo ap_icon( 'relogio', 16 ); // phpcs:ignore ?>
				<span>Previsão de retirada: <strong><?php echo esc_html( ucfirst( date_i18n( 'l, d/m', strtotime( $prev ) ) ) ); ?></strong><small>até <?php echo (int) $prazo; ?> dia útil após o pagamento e a aprovação da arte</small></span>
			</div>
			<?php ap_input( 'titulo', 'Nome do pedido (opcional, para você se achar)', '', 'text', 'placeholder="Ex.: Fachada loja do João"' ); ?>
			<label class="chk"><input type="checkbox" name="urgente" value="1"> <strong>É urgente</strong> <small class="muted">(sem custo a mais: só avisa a produção para priorizar)</small></label>
			<?php if ( ! empty( $client->pay_later ) ) : ?>
				<div class="choice-list">
					<label class="choice"><input type="radio" name="pagamento" value="agora" checked><span><strong>Pagar agora (Pix)</strong><small>O pedido entra na fila assim que o pagamento cai.</small></span></label>
					<label class="choice"><input type="radio" name="pagamento" value="retirada"><span><strong>Pagar na retirada</strong><small>Liberado para o seu cadastro.</small></span></label>
				</div>
			<?php endif; ?>
			<label class="chk"><input type="checkbox" name="sem_arquivo" value="1" data-no-file> Vou enviar o arquivo depois</label>
			<label class="chk"><input type="checkbox" required> Conferi as medidas e o material. Sei que não há troca de produto e que a <?php echo esc_html( ap_setting( 'empresa' ) ); ?> não se responsabiliza por arquivo em baixa qualidade.</label>
			<button type="submit" class="btn btn--primary btn--block" data-submit>Finalizar pedido</button>
			<p class="muted small center">Pagamento seguro. O material só é liberado após a confirmação.</p>
		</div>
	</aside>
</form>
<script src="<?php echo esc_url( AP_URL . 'assets/pedido.js?ver=' . AP_VERSION ); ?>"></script>
<?php
ap_client_end();

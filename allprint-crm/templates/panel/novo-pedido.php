<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
$cfg         = ap_catalog_js( true );
$cfg['tier'] = 'parceiro';
$groups      = ap_catalog_groups();
$clients     = ap_manual_clients_js();
$pre         = isset( $_GET['cliente'] ) ? absint( $_GET['cliente'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification
$prev        = ap_next_business_day( ap_today(), max( 1, (int) ap_setting( 'prazo_dias' ) ) );
ap_panel_start( 'Novo pedido manual', 'novo-pedido' );
?>
<script>window.AP_CAT = <?php echo wp_json_encode( $cfg ); ?>; window.AP_CLIENTS = <?php echo wp_json_encode( $clients ); ?>; window.AP_DRIVE = <?php echo ap_google_connected() ? 'true' : 'false'; ?>;</script>
<p class="muted">Para o cliente que pediu pelo WhatsApp, no balcão ou por telefone. Escolha o cliente, monte os itens (o preço vem da tabela dele e você pode ajustar), diga como foi o pagamento e pronto: o pedido cai no quadro e no financeiro.</p>

<?php ap_form( 'manual_order', 'order-grid mo' ); ?>
	<div class="order-items" data-order>
		<section class="card mo-client">
			<span class="eyebrow">Cliente</span>
			<label class="field"><span>Quem está pedindo?</span>
				<select name="client_id" data-client>
					<option value="">+ Cliente novo (preencher abaixo)</option>
					<?php foreach ( $clients as $cid => $c ) : ?>
						<option value="<?php echo (int) $cid; ?>"<?php selected( $pre, $cid ); ?>><?php echo esc_html( $c['label'] . ( $c['kind'] ? ' · ' . $c['kind'] : '' ) ); ?></option>
					<?php endforeach; ?>
				</select>
			</label>
			<p class="mo-info" data-client-info hidden></p>
			<div class="mo-new grid-2" data-newclient>
				<?php ap_input( 'new_company', 'Empresa / nome' ); ?>
				<?php ap_input( 'new_name', 'Contato (quem pediu)' ); ?>
				<?php ap_input( 'new_whatsapp', 'WhatsApp', '', 'tel', 'data-mask="phone"' ); ?>
				<?php ap_select( 'new_kind', 'Tipo de cliente', array( 'Terceirizado' => 'Terceirizado (revenda)', 'Empresa' => 'Empresa', 'Cliente P/F' => 'Pessoa física' ) ); ?>
				<?php ap_input( 'new_cnpj', 'CNPJ ou CPF (opcional)' ); ?>
				<?php ap_input( 'new_email', 'E-mail (opcional)', '', 'email' ); ?>
			</div>
		</section>

		<div class="oitem" data-oitem>
			<div class="oitem-head"><strong>Item <span data-n>1</span></strong><button type="button" class="icon-btn" data-oitem-del title="Remover">×</button></div>
			<label class="field"><span>Produto ou serviço</span>
				<select name="i[material][]" required data-f="material">
					<option value="">Escolha…</option>
					<?php foreach ( $groups as $g => $gl ) : ?>
						<optgroup label="<?php echo esc_attr( $gl ); ?>">
							<?php foreach ( $cfg['items'] as $id => $m ) : ?>
								<?php if ( $m['grp'] === $g ) : ?><option value="<?php echo (int) $id; ?>"><?php echo esc_html( $m['name'] ); ?></option><?php endif; ?>
							<?php endforeach; ?>
						</optgroup>
					<?php endforeach; ?>
				</select>
			</label>
			<p class="muted small" data-f="info"></p>
			<div class="grid-3">
				<label class="field" data-sizes><span>Largura (cm)</span><input type="text" inputmode="decimal" name="i[w][]" required data-f="w" placeholder="100"></label>
				<label class="field" data-sizes><span>Altura (cm)</span><input type="text" inputmode="decimal" name="i[h][]" required data-f="h" placeholder="100"></label>
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
				<label class="field field--inline"><span>Corte</span><select name="i[cut][]" data-f="cut"><option value="simples">Simples</option><option value="complexo">Complexo / de quantidade</option></select></label>
				<label class="field field--inline"><span>Valor deste item (R$)</span><input type="text" inputmode="decimal" name="i[ov][]" data-f="ov" placeholder="automático"></label>
			</div>
			<label class="field"><span>Observações para a produção (opcional)</span><textarea name="i[obs][]" rows="2" placeholder="Ex.: sangria de 2 cm, emenda vertical, aplicar em vidro…"></textarea></label>
			<div class="upbox" data-upbox>
				<label class="drop drop--file"><input type="file" multiple accept=".pdf,.cdr,.jpg,.jpeg,.tif,.tiff,.png,.ai,.eps,.zip" data-upload><?php echo ap_icon( 'upload', 20 ); // phpcs:ignore ?><span><strong>Anexar o arquivo deste item</strong><small>PDF, CDR, JPG… (opcional: pode anexar depois na ficha do pedido)</small></span></label>
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
			<label class="field"><span>Tabela de preço</span><select name="tabela" data-tier><?php foreach ( ap_tiers() as $k => $l ) : ?><option value="<?php echo esc_attr( $k ); ?>"><?php echo esc_html( $l ); ?></option><?php endforeach; ?></select></label>
			<label class="chk"><input type="checkbox" name="aplicar_minimo" value="1" checked data-nomin> Aplicar o mínimo de <?php echo esc_html( str_replace( '.', ',', $cfg['minArea'] ) ); ?> m² por material</label>
			<div class="calc-lines" data-sum-lines><p class="muted small">Escolha o produto e as medidas.</p></div>
			<div class="calc-lines">
				<div class="calc-sub"><span>Subtotal</span><b data-sum-total>R$ 0,00</b></div>
				<div class="calc-sub" data-row-discount hidden><span>Desconto <small data-discount-label></small></span><b data-sum-discount>R$ 0,00</b></div>
				<div class="calc-sub" data-row-credit hidden><span>Crédito do cliente</span><b data-sum-credit>R$ 0,00</b></div>
				<div class="calc-total"><span>A pagar</span><b data-sum-pay>R$ 0,00</b></div>
			</div>
			<div class="mo-disc">
				<label class="field"><span>Desconto (R$)</span><input type="text" inputmode="decimal" name="desconto" data-discount placeholder="0,00"></label>
				<label class="field"><span>Cupom</span><span class="mo-coupon"><input type="text" name="cupom" data-coupon placeholder="CÓDIGO" autocomplete="off"><small data-coupon-msg class="muted"></small></span></label>
				<label class="chk" data-credit-row hidden><input type="checkbox" name="usar_credito" value="1" data-usecredit> Usar o crédito do cliente (<b data-credit-balance>R$ 0,00</b>)</label>
			</div>
			<fieldset class="choice-list mo-pay">
				<legend>Pagamento</legend>
				<label class="choice"><input type="radio" name="pagamento" value="pago" checked><span><strong>Já pagou tudo</strong><small>Entra no financeiro como recebido.</small></span></label>
				<label class="choice"><input type="radio" name="pagamento" value="sinal"><span><strong>Pagou um sinal</strong><small>O resto fica para a retirada.</small></span></label>
				<label class="choice"><input type="radio" name="pagamento" value="retirada"><span><strong>Paga na retirada</strong><small>Fica marcado "a receber" no pedido.</small></span></label>
				<label class="choice"><input type="radio" name="pagamento" value="pendente"><span><strong>Ainda não pagou</strong><small>Gera o link de Pix para mandar ao cliente.</small></span></label>
			</fieldset>
			<div class="grid-2" data-pay-fields>
				<label class="field" data-sinal-row hidden><span>Valor do sinal (R$)</span><input type="text" inputmode="decimal" name="sinal" data-sinal placeholder="50%"></label>
				<?php ap_select( 'metodo', 'Forma de pagamento', array( 'Pix' => 'Pix', 'Dinheiro' => 'Dinheiro', 'Cartão' => 'Cartão', 'Transferência' => 'Transferência' ) ); ?>
				<?php ap_input( 'data_pagamento', 'Data do pagamento', ap_today(), 'date' ); ?>
			</div>
			<div class="grid-2">
				<?php ap_select( 'canal', 'Por onde pediu', ap_channels_manual() ); ?>
				<?php ap_input( 'prazo', 'Retirada prevista', $prev, 'date' ); ?>
			</div>
			<?php ap_input( 'titulo', 'Nome do pedido (opcional)', '', 'text', 'placeholder="Ex.: Fachada loja do João"' ); ?>
			<?php ap_input( 'obs', 'Observação interna', '', 'textarea', 'rows="2"' ); ?>
			<label class="chk"><input type="checkbox" name="urgente" value="1"> <strong>É urgente</strong> <small class="muted">(só avisa a produção)</small></label>
			<label class="chk"><input type="checkbox" name="avisar" value="1"> Enviar e-mail de pedido recebido ao cliente</label>
			<label class="chk" hidden><input type="checkbox" name="sem_arquivo" value="1" checked data-no-file></label>
			<button type="submit" class="btn btn--primary btn--block" data-submit data-keep-label>Criar pedido</button>
		</div>
	</aside>
</form>
<script src="<?php echo esc_url( AP_URL . 'assets/artpreview.js?ver=' . AP_VERSION ); ?>"></script>
<script src="<?php echo esc_url( AP_URL . 'assets/pedido.js?ver=' . AP_VERSION ); ?>"></script>
<script src="<?php echo esc_url( AP_URL . 'assets/manual.js?ver=' . AP_VERSION ); ?>"></script>
<?php
ap_panel_end();

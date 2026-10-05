<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
$ready = (bool) lk_places_key();
lk_panel_start( 'Prospecção', 'prospeccao', '<a class="btn btn--ghost" href="' . esc_url( lk_panel_url( 'leads' ) ) . '">Funil de leads</a>' );
?>
<p class="muted hint">Busque empresas no Google por <strong>nicho + cidade</strong>, veja nome, telefone, site e nota, escolha as que interessam e mande para o <strong>Funil de leads</strong>. O <strong>e-mail e o Instagram</strong> vêm do site da própria empresa (botão <em>Buscar e-mail e Instagram</em>): pegamos o que ela publica lá.</p>
<?php if ( ! $ready ) : ?>
	<section class="card card--accent"><h3>Falta ligar a busca do Google</h3>
		<ol class="steps small">
			<li>No <strong>Google Cloud</strong> (o mesmo projeto do Drive), ative a <strong>Places API (New)</strong> e deixe a cobrança configurada (o Google cobra por busca; há cota gratuita mensal, confira os preços atuais).</li>
			<li>Em <strong>Credenciais → Criar credenciais → Chave de API</strong>, copie a chave (restrinja à Places API).</li>
			<li>Cole em <a href="<?php echo esc_url( lk_panel_url( 'config' ) ); ?>#google">Configurações → Google → Chave da Places API</a> e salve.</li>
		</ol>
	</section>
<?php endif; ?>
<section class="card">
	<form class="pros-form" data-pros-form>
		<label class="field"><span>Nicho / segmento</span><input type="text" name="niche" placeholder="engenharia" required<?php echo $ready ? '' : ' disabled'; ?>></label>
		<label class="field"><span>Cidade</span><input type="text" name="city" placeholder="Taubaté SP" required<?php echo $ready ? '' : ' disabled'; ?>></label>
		<button type="submit" class="btn btn--primary"<?php echo $ready ? '' : ' disabled'; ?>>🔎 Buscar empresas</button>
	</form>
	<p class="muted small" data-pros-msg></p>
</section>
<section class="card" data-pros-box hidden>
	<div class="card-head"><h3 data-pros-title>Resultados</h3>
		<span class="row-btns">
			<button type="button" class="btn btn--ghost btn--sm" data-pros-all>Selecionar todos</button>
			<button type="button" class="btn btn--ghost btn--sm" data-pros-emails>📧 Buscar e-mail e Instagram</button>
			<button type="button" class="btn btn--ghost btn--sm" data-pros-csv>⬇ CSV</button>
			<button type="button" class="btn btn--primary btn--sm" data-pros-add>➕ Adicionar ao funil</button>
		</span>
	</div>
	<?php if ( lk_ai_ready() ) : ?>
	<div class="ai-pitch"><span class="ai-tag">✨ IA</span> Mensagem de abordagem para a empresa marcada:
		<select data-pros-channel><option value="whatsapp">WhatsApp</option><option value="email">E-mail</option></select>
		<button type="button" class="btn btn--ghost btn--sm" data-pros-pitch>Gerar mensagem</button>
		<div class="ai-out" data-pros-pitch-out></div></div>
	<?php endif; ?>
	<div class="pros-wrap"><table class="pros-table"><thead><tr><th></th><th>Empresa</th><th>Nicho</th><th>Telefone</th><th>E-mail</th><th>Instagram</th><th>Site</th><th>Nota</th><th>Endereço</th></tr></thead><tbody data-pros-body></tbody></table></div>
	<p><button type="button" class="btn btn--ghost" data-pros-more hidden>Carregar mais resultados</button></p>
</section>
<p class="muted small">Use os contatos para abordagem comercial transparente (LGPD): diga de onde veio o contato e permita que a empresa peça para não ser contatada de novo.</p>
<script src="<?php echo esc_url( LK_URL . 'assets/prospeccao.js?ver=' . LK_VERSION ); ?>"></script>
<?php
lk_panel_end();

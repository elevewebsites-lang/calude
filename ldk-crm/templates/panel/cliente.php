<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
$c = lk_get( 'clients', $id );
if ( ! $c ) {
	lk_render( 'panel/404' );
}
$user     = $c->user_id ? get_userdata( $c->user_id ) : null;
$invite   = $c->invite_token ? lk_invite_url( $c->invite_token ) : '';
$label    = lk_client_label( $c );

$actions  = '<a class="btn btn--ghost" href="' . esc_url( lk_client_url( '', 0, array( 'como' => $c->id ) ) ) . '">' . lk_icon( 'olho', 16 ) . '<span>Ver como cliente</span></a>';
$actions .= lk_can( 'leads' ) ? '<a class="btn btn--ghost" href="' . esc_url( lk_panel_url( 'leads', 0, array( 'abrir' => 'novo-lead', 'cliente' => $c->id ) ) ) . '">' . lk_icon( 'mais', 16 ) . '<span>Novo lead</span></a>' : '';
$actions .= '<button type="button" class="btn btn--ghost" data-open="status-cliente">' . lk_icon( 'whatsapp', 16 ) . '<span>Status para o cliente</span></button>';
$actions .= '<a class="btn btn--primary" href="' . esc_url( lk_panel_url( 'planejamento', 0, array( 'cliente' => $c->id ) ) ) . '">' . lk_icon( 'lista', 16 ) . '<span>Planejamento</span></a>';
lk_panel_start( $label, 'clientes', $actions );
?>

<a class="back" href="<?php echo esc_url( lk_panel_url( 'clientes' ) ); ?>"><?php echo lk_icon( 'voltar', 16 ); // phpcs:ignore ?> Clientes</a>

<?php $svc_tags = lk_client_service_tags_html( $c ); if ( $svc_tags ) : ?><div class="svc-bar"><span class="muted small">Serviços ativos</span><?php echo $svc_tags; // phpcs:ignore WordPress.Security.EscapeOutput ?></div><?php endif; ?>

<div class="detail-grid">
	<div class="detail-main">
		<?php if ( $invite ) : ?>
			<section class="card card--accent">
				<div class="card-head"><h3>Link de convite</h3><?php echo lk_status_badge( $user ? 'ativo' : 'convidado' ); // phpcs:ignore ?></div>
				<p class="muted small">Mande este link para o cliente. Ele completa os dados (nome, telefone, CNPJ, WhatsApp) e cria a senha dele.</p>
				<div class="copy-row">
					<input type="text" readonly value="<?php echo esc_attr( $invite ); ?>" onclick="this.select()">
					<button type="button" class="btn btn--primary" data-copy="<?php echo esc_attr( $invite ); ?>"><?php echo lk_icon( 'copiar', 16 ); // phpcs:ignore ?><span>Copiar</span></button>
					<?php if ( $c->whatsapp ) : ?>
						<a class="btn btn--wa" target="_blank" rel="noopener" href="<?php echo esc_url( lk_wa_link( $c->whatsapp, 'Olá' . ( $c->name ? ', ' . $c->name : '' ) . '! Aqui está o link para criar o seu acesso à área do cliente do ' . lk_setting( 'empresa' ) . ', onde você aprova os conteúdos e vê os relatórios: ' . $invite ) ); ?>"><?php echo lk_icon( 'whatsapp', 16 ); // phpcs:ignore ?><span>Enviar</span></a>
					<?php endif; ?>
				</div>
			</section>
		<?php endif; ?>

		<?php
		$accs  = lk_social_accounts( $c->id );
		$picks = json_decode( (string) lk_decrypt( (string) get_transient( 'lk_fbpages_' . $c->id ) ), true );
		$nets  = lk_networks();
		$more  = lk_social_pick_pending( $c->id );
		?>
		<?php echo lk_client_contracts_html( $c ); // phpcs:ignore WordPress.Security.EscapeOutput ?>
		<?php echo lk_client_renewals_html( $c ); // phpcs:ignore WordPress.Security.EscapeOutput ?>
		<?php echo lk_client_meetings_html( $c ); // phpcs:ignore WordPress.Security.EscapeOutput ?>
		<?php echo lk_client_forms_html( $c ); // phpcs:ignore WordPress.Security.EscapeOutput ?>
		<?php if ( function_exists( 'lk_google_connected' ) && lk_google_connected() ) : $fl = lk_drive_folder_link( array( 'Clientes', lk_client_label( $c ) ) ); ?>
		<section class="card"><div class="pay-row"><span><strong>Pasta do cliente no Google Drive</strong><small>Fotos, artes, vídeos e as planilhas de conteúdo e planejamento ficam guardados aqui.</small></span>
			<?php if ( $fl ) : ?><a class="btn btn--primary btn--sm" href="<?php echo esc_url( $fl ); ?>" target="_blank" rel="noopener">Abrir pasta</a><?php else : ?><?php lk_action_button( 'client_folder', array( 'client_id' => $c->id ), 'Criar pasta no Drive', 'btn btn--primary btn--sm' ); ?><?php endif; ?></div></section>
		<?php endif; ?>
		<section class="card" id="redes">
			<div class="card-head"><h3>Redes sociais</h3><a class="small" href="<?php echo esc_url( lk_panel_url( 'redes' ) ); ?>">passo a passo</a></div>
			<?php foreach ( $nets as $n => $lab ) : ?>
				<?php $a = $accs[ $n ] ?? null; ?>
				<div class="pay-row">
					<span><strong><?php echo esc_html( $lab ); ?></strong><small><?php echo $a ? esc_html( ( $a->username ? '@' . $a->username : $a->name ) . ( 'ok' === $a->status ? ' · conectado' : ' · ' . $a->error ) ) : 'não vinculado'; ?></small></span>
					<em class="badge <?php echo $a && 'ok' === $a->status ? 'badge--ok' : ( $a ? 'badge--late' : 'badge--off' ); ?>"><?php echo $a ? ( 'ok' === $a->status ? 'ok' : 'reconectar' ) : 'não vinculado'; ?></em>
					<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="inline-form"><input type="hidden" name="action" value="lk_social_go"><input type="hidden" name="net" value="<?php echo esc_attr( $n ); ?>"><input type="hidden" name="client_id" value="<?php echo (int) $c->id; ?>"><?php wp_nonce_field( 'lk_social_go' ); ?><button type="submit" class="btn btn--<?php echo $a ? 'ghost' : 'primary'; ?> btn--sm"><?php echo $a ? 'Reconectar' : 'Conectar ' . esc_html( $lab ); ?></button></form>
					<?php if ( $a ) : ?><?php lk_action_button( 'social_disconnect', array( 'id' => $a->id ), 'Desconectar', 'btn btn--link btn--sm', 'Desconectar esta conta?' ); ?><?php endif; ?>
				</div>
			<?php endforeach; ?>
			<?php if ( $picks ) : ?>
				<?php lk_form( 'fb_pick_page', 'inline-form' ); ?><input type="hidden" name="client_id" value="<?php echo (int) $c->id; ?>"><select name="page"><?php foreach ( $picks as $pg ) : ?><option value="<?php echo esc_attr( $pg['id'] ); ?>"><?php echo esc_html( $pg['name'] ); ?></option><?php endforeach; ?></select><button class="btn btn--primary btn--sm">Usar esta Página</button></form>
			<?php endif; ?>
			<?php foreach ( $more as $pn => $opts ) : ?>
				<?php lk_form( 'social_pick', 'inline-form' ); ?><input type="hidden" name="client_id" value="<?php echo (int) $c->id; ?>"><input type="hidden" name="net" value="<?php echo esc_attr( $pn ); ?>"><span class="small"><?php echo esc_html( lk_networks()[ $pn ] ); ?>:</span><select name="choice"><?php foreach ( $opts as $o ) : ?><option value="<?php echo esc_attr( $o['id'] ); ?>"><?php echo esc_html( $o['name'] ); ?></option><?php endforeach; ?></select><button class="btn btn--primary btn--sm">Usar este</button></form>
			<?php endforeach; ?>
			<div class="pay-row">
				<span><strong>Link para o cliente conectar sozinho</strong><small>Manda no WhatsApp: ele entra no Instagram/Facebook dele e autoriza. Sem senha, sem login no CRM.</small></span>
				<button type="button" class="btn btn--primary btn--sm" data-copy="<?php echo esc_attr( lk_connect_url( $c->id ) ); ?>">Copiar link</button>
				<?php lk_action_button( 'connect_reset', array( 'client_id' => $c->id ), 'Trocar link', 'btn btn--link btn--sm', 'Gerar um link novo? O atual deixa de funcionar.' ); ?>
			</div>
			<p class="muted small">LinkedIn sem Página conectada: o sistema avisa na hora de postar (manual).</p>
		</section>

		<?php
		$ym_now = current_time( 'Y-m' );
		$ym_nxt = gmdate( 'Y-m', strtotime( $ym_now . '-01 +1 month' ) );
		$cposts = lk_posts( 'p.client_id = %d AND ( p.scheduled_at >= %s OR p.stage <> %s )', array( $c->id, $ym_now . '-01', lk_stage_for( 'publicado' ) ), 'p.scheduled_at IS NULL, p.scheduled_at LIMIT 12' );
		?>
		<section class="card">
			<div class="card-head"><h3>Conteúdo</h3><span class="row-btns"><a class="btn btn--ghost btn--sm" href="<?php echo esc_url( lk_panel_url( 'planejamento', 0, array( 'cliente' => $c->id, 'mes' => $ym_nxt ) ) ); ?>">Planejar <?php echo esc_html( lk_month_name( gmdate( 'n', strtotime( $ym_nxt . '-01' ) ) ) ); ?></a><a class="btn btn--ghost btn--sm" href="<?php echo esc_url( lk_panel_url( 'conteudo', 0, array( 'cliente' => $c->id ) ) ); ?>">Calendário</a></span></div>
			<div class="quota-row"><?php echo lk_quota_html( $c, $ym_now ) . lk_quota_html( $c, $ym_nxt ); // phpcs:ignore ?></div>
			<?php if ( ! $cposts ) : ?><p class="muted small">Nenhum post ainda.</p><?php endif; ?>
			<ul class="mini-list mini-list--thumbs"><?php foreach ( $cposts as $p ) : ?><li><a class="ml-row" href="<?php echo esc_url( lk_panel_url( 'post', $p->id ) ); ?>"><?php echo lk_thumb_html( $p, 'row-thumb' ); // phpcs:ignore ?><span class="ml-txt"><strong><?php echo esc_html( $p->title ); ?></strong><small><?php echo lk_stage_chip( $p ); // phpcs:ignore ?> <?php echo esc_html( $p->scheduled_at ? lk_date( $p->scheduled_at, 'd/m H:i' ) : 'sem data' ); ?></small></span></a></li><?php endforeach; ?></ul>
		</section>

		<?php $ans = lk_briefing_answers( $c ); ?>
		<section class="card" id="briefing">
			<div class="card-head"><h3>Briefing</h3><?php echo $c->briefing_at ? '<em class="badge badge--ok">respondido ' . esc_html( lk_date( $c->briefing_at, 'd/m/Y' ) ) . '</em>' : '<em class="badge badge--off">não respondido</em>'; ?></div>
			<div class="row-btns">
				<?php lk_action_button( 'briefing_send', array( 'id' => $c->id ), lk_icon( 'email', 15 ) . '<span>' . ( $c->briefing_at ? 'Mandar de novo' : 'Mandar briefing' ) . '</span>', 'btn btn--primary btn--sm' ); ?>
				<?php if ( $c->whatsapp ) : ?><a class="btn btn--wa btn--sm" target="_blank" rel="noopener" href="<?php echo esc_url( lk_wa_link( $c->whatsapp, lk_briefing_message( $c ) ) ); ?>"><?php echo lk_icon( 'whatsapp', 15 ); // phpcs:ignore ?><span>WhatsApp</span></a><?php endif; ?>
				<button type="button" class="btn btn--ghost btn--sm" data-copy="<?php echo esc_attr( lk_briefing_url( $c ) ); ?>">Copiar link</button>
				<button type="button" class="btn btn--ghost btn--sm" data-open="briefing-edit"><?php echo $c->briefing_at ? 'Editar respostas' : 'Preencher com o cliente'; ?></button>
			</div>
			<?php if ( $ans ) : ?>
				<details class="brf-answers"<?php echo $c->briefing_at ? ' open' : ''; ?>><summary class="small">Ver respostas</summary>
					<dl class="brf-dl"><?php foreach ( $ans as $row ) : ?><?php if ( '' !== trim( (string) $row['a'] ) ) : ?><dt><?php echo esc_html( $row['q'] ); ?></dt><dd><?php echo nl2br( esc_html( $row['a'] ) ); ?></dd><?php endif; ?><?php endforeach; ?></dl>
				</details>
			<?php endif; ?>
		</section>

		<section class="card" id="contrato">
			<div class="card-head"><h3>Equipe e contrato</h3></div>
			<?php lk_form( 'client_team', 'stack' ); ?>
				<input type="hidden" name="id" value="<?php echo (int) $c->id; ?>">
				<?php $team = lk_team_options( '—' ); ?>
				<div class="grid-2">
					<?php lk_select( 'designer_id', 'Designer', $team, $c->designer_id ); ?>
					<?php lk_select( 'social_id', 'Social media', $team, $c->social_id ); ?>
					<?php lk_select( 'atendimento_id', 'Atendimento', $team, $c->atendimento_id ); ?>
					<?php lk_select( 'trafego_id', 'Gestor de tráfego', $team, $c->trafego_id ); ?>
					<?php lk_select( 'revisor_id', 'Revisão (quem confere a arte)', array( '' => '— o atendimento —' ) + array_slice( $team, 1, null, true ), $c->revisor_id ); ?>
					<?php lk_input( 'posts_quota', 'Pacote: artes por mês', $c->posts_quota ?: '', 'number', 'min="0" max="200" placeholder="Ex.: 12"' ); ?>
				</div>
				<?php if ( lk_can( 'financeiro' ) ) : ?>
					<div class="grid-2">
						<?php lk_input( 'monthly_fee', 'Mensalidade (R$)', $c->monthly_fee > 0 ? number_format( $c->monthly_fee, 2, ',', '.' ) : '', 'text', 'inputmode="decimal"' ); ?>
						<?php lk_input( 'due_day', 'Dia do vencimento', $c->due_day ?: '', 'number', 'min="1" max="28"' ); ?>
					</div>
					<?php lk_check( 'billing_active', 'Gerar mensalidade automática', (bool) $c->billing_active ); ?>
				<?php endif; ?>
				<div class="grid-2">
					<?php lk_input( 'color', 'Cor no calendário', $c->color ?: '#14E9EC', 'color' ); ?>
					<?php lk_input( 'meta_ad_account', 'Conta de anúncios Meta', $c->meta_ad_account, 'text', 'placeholder="act_123..."' ); ?>
				</div>
				<?php lk_check( 'ads_visible', 'O cliente vê o tráfego pago', (bool) $c->ads_visible ); ?>
				<?php lk_check( 'email_optout', 'O cliente NÃO quer receber avisos por e-mail', (bool) $c->email_optout ); ?>
				<div class="form-actions"><button type="submit" class="btn btn--primary">Salvar</button></div>
			</form>
		</section>
	</div>

	<aside class="detail-side">
		<section class="card">
			<div class="card-head"><h3>Dados</h3></div>
			<?php lk_form( 'client_save', 'stack', true ); ?>
				<input type="hidden" name="id" value="<?php echo (int) $c->id; ?>">
				<div class="logo-field">
					<span class="logo-plate"><?php if ( $c->logo ) : ?><img src="<?php echo esc_url( $c->logo ); ?>" alt="Logo de <?php echo esc_attr( lk_client_label( $c ) ); ?>"><?php else : ?><em><?php echo esc_html( lk_initials( lk_client_label( $c ) ) ); ?></em><?php endif; ?></span>
					<label class="field"><span>Logo do cliente (PNG, JPG ou WebP)</span><input type="file" name="logo_file" accept="image/png,image/jpeg,image/webp"></label>
				</div>
				<?php if ( $c->logo ) : ?><?php lk_check( 'logo_remove', 'Remover a logo' ); ?><?php endif; ?>
				<p class="muted small">Útil para empresas e revendedores.</p>
				<?php lk_input( 'company', 'Empresa', $c->company ); ?>
				<?php lk_input( 'name', 'Contato', $c->name ); ?>
				<?php lk_input( 'cnpj', 'CNPJ / CPF', $c->cnpj, 'text', 'data-mask="doc"' ); ?>
				<?php lk_input( 'rep_cpf', 'CPF do responsável (contrato)', $c->rep_cpf, 'text', 'data-mask="doc"' ); ?>
				<?php lk_input( 'instagram', 'Instagram', $c->instagram, 'text', 'placeholder="@empresa"' ); ?>
				<?php lk_input( 'hashtags', 'Hashtags padrão do cliente (para os posts)', (string) $c->hashtags, 'textarea', 'rows="2" placeholder="#suaempresa #nicho #cidade"' ); ?>
				<?php lk_input( 'site', 'Site', $c->site, 'url', 'placeholder="https://"' ); ?>
				<div class="grid-2">
					<?php lk_input( 'whatsapp', 'WhatsApp', $c->whatsapp, 'tel', 'data-mask="phone"' ); ?>
					<?php lk_input( 'phone', 'Telefone', $c->phone, 'tel', 'data-mask="phone"' ); ?>
				</div>
				<?php lk_input( 'email', 'E-mail', $c->email, 'email' ); ?>
				<div class="grid-2">
					<?php lk_input( 'cep', 'CEP', $c->cep, 'text', 'inputmode="numeric"' ); ?>
					<?php lk_input( 'city', 'Cidade/UF', $c->city ); ?>
				</div>
				<?php lk_input( 'address', 'Endereço', $c->address ); ?>
				<div class="grid-2">
					<?php lk_select( 'source', 'Como chegou (origem)', array( '' => '—' ) + lk_list_options( 'origens' ), $c->source ); ?>
					<?php lk_input( 'birthday', 'Aniversário', $c->birthday, 'date' ); ?>
				</div>
				<?php lk_input( 'notes', 'Anotações internas', $c->notes, 'textarea', 'rows="3"' ); ?>
				<?php if ( $user ) : ?>
					<div class="support-box">
						<strong>Suporte ao acesso</strong>
						<p class="muted small">Login: <?php echo esc_html( $user->user_email ); ?>. Para redefinir, digite uma nova senha e salve.</p>
						<?php lk_input( 'new_password', 'Nova senha do cliente', '', 'text', 'autocomplete="off" minlength="8"' ); ?>
					</div>
				<?php endif; ?>
				<div class="form-actions">
					<?php if ( $c->whatsapp ) : ?><a class="btn btn--ghost" target="_blank" rel="noopener" href="<?php echo esc_url( lk_wa_link( $c->whatsapp ) ); ?>"><?php echo lk_icon( 'whatsapp', 16 ); // phpcs:ignore ?></a><?php endif; ?>
					<button type="submit" class="btn btn--primary">Salvar</button>
				</div>
			</form>
		</section>
		<div class="side-actions">
			<?php lk_action_button( 'client_new_invite', array( 'id' => $c->id ), $user ? 'Gerar novo link de acesso' : 'Gerar novo convite', 'btn btn--ghost btn--sm', $user ? 'O cliente já tem acesso. Gerar um novo link permite recadastrar e trocar a senha. Continuar?' : '' ); ?>
			<?php if ( lk_is_admin() ) : ?>
				<?php lk_action_button( 'client_delete', array( 'id' => $c->id ), 'Excluir cliente', 'btn btn--danger btn--sm', 'Excluir este cliente e o acesso dele? Isso não pode ser desfeito.' ); ?>
			<?php endif; ?>
		</div>
	</aside>
</div>

<?php
lk_modal_start( 'briefing-edit', 'Briefing · ' . $label );
lk_form( 'briefing_save', 'stack brf-form' );
echo '<input type="hidden" name="id" value="' . (int) $c->id . '">';
lk_briefing_fields( $c );
echo '<div class="form-actions"><button type="button" class="btn btn--ghost" data-close>Cancelar</button><button type="submit" class="btn btn--primary">Salvar</button></div></form>';
lk_modal_end();

lk_modal_start( 'status-cliente', 'Status para o cliente' );
?>
<p class="muted small">Resumo de onde está cada arte deste mês e do próximo. Ajuste o texto se quiser e mande.</p>
<?php lk_form( 'status_email', 'stack' ); ?>
	<input type="hidden" name="id" value="<?php echo (int) $c->id; ?>">
	<?php lk_input( 'mensagem', 'Mensagem', lk_status_message( $c ), 'textarea', 'rows="12" data-status-msg' ); ?>
	<div class="form-actions">
		<button type="button" class="btn btn--ghost" data-copy-from="[data-status-msg]">Copiar</button>
		<?php if ( is_email( $c->email ) ) : ?><button type="submit" class="btn btn--ghost"><?php echo lk_icon( 'email', 15 ); // phpcs:ignore ?><span>Mandar por e-mail</span></button><?php endif; ?>
		<?php if ( $c->whatsapp ) : ?><a class="btn btn--wa" href="#" target="_blank" rel="noopener" data-wa-status="<?php echo esc_attr( lk_wa_link( $c->whatsapp ) ); ?>"><?php echo lk_icon( 'whatsapp', 16 ); // phpcs:ignore ?><span>Abrir no WhatsApp</span></a><?php endif; ?>
	</div>
</form>
<script>
(function () {
	var ta = document.querySelector('[data-status-msg]'), wa = document.querySelector('[data-wa-status]'), cp = document.querySelector('[data-copy-from]');
	if (wa) wa.addEventListener('click', function () { wa.href = wa.getAttribute('data-wa-status') + '?text=' + encodeURIComponent(ta.value); });
	if (cp) cp.addEventListener('click', function () { navigator.clipboard.writeText(ta.value); cp.textContent = 'Copiado ✓'; });
})();
</script>
<?php
lk_modal_end();
lk_panel_end();

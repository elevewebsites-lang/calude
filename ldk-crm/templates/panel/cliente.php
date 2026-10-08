<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
global $wpdb;
$c = lk_get( 'clients', $id );
if ( ! $c ) {
	lk_render( 'panel/404' );
}
$user     = $c->user_id ? get_userdata( $c->user_id ) : null;
$invite   = $c->invite_token ? lk_invite_url( $c->invite_token ) : '';
$label    = lk_client_label( $c );
$manage   = lk_manage_only();

$n_posts  = (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM ' . lk_table( 'posts' ) . ' WHERE client_id = %d', $c->id ) ); // phpcs:ignore WordPress.DB.PreparedSQL
$n_ctr    = count( lk_rows( 'contracts', "client_id = %d AND status IN ('rascunho','enviado','assinado')", array( $c->id ) ) );
$ren      = lk_rows( 'renewals', 'client_id = %d', array( $c->id ), 'due_date IS NULL, due_date ASC' );
$n_late   = 0;
foreach ( $ren as $r ) {
	$d = lk_renewal_days( $r );
	if ( null !== $d && $d <= 30 && ! $r->paid ) {
		$n_late++;
	}
}
$n_acc    = lk_can( 'acessos' ) ? count( lk_rows( 'access', 'client_id = %d', array( $c->id ) ) ) : 0;
$n_files  = count( lk_rows( 'files', 'client_id = %d', array( $c->id ) ) );

$tabs = array(
	'resumo'      => array( 'Resumo', 'dashboard', 0 ),
	'dados'       => array( 'Dados', 'clientes', 0 ),
	'contratos'   => array( 'Contratos', 'proposta', $n_ctr ),
	'briefing'    => array( 'Briefing e fichas', 'lista', 0 ),
	'posts'       => array( 'Posts', 'projetos', $n_posts ),
	'drive'       => array( 'Drive e arquivos', 'drive', $n_files ),
	'acessos'     => array( 'Acessos', 'chave', $n_acc ),
	'vencimentos' => array( 'Vencimentos', 'globo', $n_late ),
	'reunioes'    => array( 'Reuniões', 'relogio', 0 ),
);
if ( ! $manage ) {
	$tabs['redes'] = array( 'Redes sociais', 'megafone', 0 );
}

$actions  = '<a class="btn btn--ghost" href="' . esc_url( lk_client_url( '', 0, array( 'como' => $c->id ) ) ) . '">' . lk_icon( 'olho', 16 ) . '<span>Ver como cliente</span></a>';
$actions .= '<button type="button" class="btn btn--ghost" data-open="status-cliente">' . lk_icon( 'whatsapp', 16 ) . '<span>Status para o cliente</span></button>';
$actions .= '<button type="submit" form="client-main" class="btn btn--primary">' . lk_icon( 'check', 16 ) . '<span>Salvar tudo</span></button>';
lk_panel_start( $label, 'clientes', $actions );
$ym_now = current_time( 'Y-m' );
$ym_nxt = gmdate( 'Y-m', strtotime( $ym_now . '-01 +1 month' ) );
?>

<a class="back" href="<?php echo esc_url( lk_panel_url( 'clientes' ) ); ?>"><?php echo lk_icon( 'voltar', 16 ); // phpcs:ignore ?> Clientes</a>

<header class="chead card">
	<?php echo lk_client_avatar_html( $c, 'avatar avatar--xl' ); // phpcs:ignore WordPress.Security.EscapeOutput ?>
	<div class="chead-main">
		<h2><?php echo esc_html( $label ); ?></h2>
		<p class="muted"><?php echo esc_html( implode( ' · ', array_filter( array( $c->company && $c->name ? $c->name : '', $c->whatsapp, $c->email, $c->city ) ) ) ); ?></p>
		<?php echo lk_client_service_tags_html( $c ); // phpcs:ignore WordPress.Security.EscapeOutput ?>
	</div>
	<div class="chead-act">
		<?php if ( $c->whatsapp ) : ?><a class="btn btn--wa btn--sm" target="_blank" rel="noopener" href="<?php echo esc_url( lk_wa_link( $c->whatsapp ) ); ?>"><?php echo lk_icon( 'whatsapp', 15 ); // phpcs:ignore ?><span>WhatsApp</span></a><?php endif; ?>
		<a class="btn btn--ghost btn--sm" href="<?php echo esc_url( lk_panel_url( 'planejamento', 0, array( 'cliente' => $c->id ) ) ); ?>"><?php echo lk_icon( 'lista', 15 ); // phpcs:ignore ?><span>Planejamento</span></a>
		<?php if ( lk_can( 'leads' ) ) : ?><a class="btn btn--ghost btn--sm" href="<?php echo esc_url( lk_panel_url( 'leads', 0, array( 'abrir' => 'novo-lead', 'cliente' => $c->id ) ) ); ?>"><?php echo lk_icon( 'mais', 15 ); // phpcs:ignore ?><span>Novo lead</span></a><?php endif; ?>
	</div>
</header>

<nav class="ctabs" data-ctabs role="tablist">
	<?php foreach ( $tabs as $key => $t ) : ?>
		<button type="button" role="tab" class="ctab-btn" data-tab-btn="<?php echo esc_attr( $key ); ?>"><?php echo lk_icon( $t[1], 16 ); // phpcs:ignore ?><span><?php echo esc_html( $t[0] ); ?></span><?php if ( $t[2] ) : ?><em class="ctab-n<?php echo 'vencimentos' === $key ? ' ctab-n--warn' : ''; ?>"><?php echo (int) $t[2]; ?></em><?php endif; ?></button>
	<?php endforeach; ?>
</nav>

<div class="ctab-panel" data-tab-panel="resumo">
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
	<div class="ov-grid">
		<a class="ov-card" href="#posts" data-goto="posts"><span class="ov-ic"><?php echo lk_icon( 'projetos', 20 ); // phpcs:ignore ?></span><span class="ov-t">Posts do mês</span><strong><?php echo (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM ' . lk_table( 'posts' ) . ' WHERE client_id = %d AND scheduled_at BETWEEN %s AND %s', $c->id, $ym_now . '-01 00:00:00', $ym_now . '-31 23:59:59' ) ); // phpcs:ignore WordPress.DB.PreparedSQL ?><?php echo $c->posts_quota ? ' / ' . (int) $c->posts_quota : ''; ?></strong></a>
		<a class="ov-card" href="#contratos" data-goto="contratos"><span class="ov-ic"><?php echo lk_icon( 'proposta', 20 ); // phpcs:ignore ?></span><span class="ov-t">Contratos pendentes</span><strong><?php echo (int) $n_ctr; ?></strong></a>
		<a class="ov-card" href="#vencimentos" data-goto="vencimentos"><span class="ov-ic"><?php echo lk_icon( 'globo', 20 ); // phpcs:ignore ?></span><span class="ov-t">Vencimentos em 30 dias</span><strong class="<?php echo $n_late ? 'text-late' : ''; ?>"><?php echo (int) $n_late; ?></strong></a>
		<a class="ov-card" href="#briefing" data-goto="briefing"><span class="ov-ic"><?php echo lk_icon( 'lista', 20 ); // phpcs:ignore ?></span><span class="ov-t">Briefing</span><strong><?php echo $c->briefing_at ? 'Respondido' : 'Pendente'; ?></strong></a>
	</div>
	<div class="ov-cols">
		<section class="card">
			<div class="card-head"><h3>Próximos posts</h3><a class="small" href="#posts" data-goto="posts">ver todos</a></div>
			<?php $next = lk_posts( 'p.client_id = %d AND p.stage <> %s', array( $c->id, lk_stage_for( 'publicado' ) ), 'p.scheduled_at IS NULL, p.scheduled_at LIMIT 5' ); ?>
			<?php if ( ! $next ) : ?><p class="muted small">Nenhum post em andamento.</p><?php endif; ?>
			<ul class="mini-list"><?php foreach ( $next as $p ) : ?><li><a href="<?php echo esc_url( lk_panel_url( 'post', $p->id ) ); ?>"><strong><?php echo esc_html( $p->title ); ?></strong><small><?php echo lk_stage_chip( $p ); // phpcs:ignore ?> <?php echo esc_html( $p->scheduled_at ? lk_date( $p->scheduled_at, 'd/m H:i' ) : 'sem data' ); ?></small></a></li><?php endforeach; ?></ul>
		</section>
		<section class="card">
			<div class="card-head"><h3>Vencimentos</h3><a class="small" href="#vencimentos" data-goto="vencimentos">ver todos</a></div>
			<?php if ( ! $ren ) : ?><p class="muted small">Cadastre hospedagem e domínio na aba Vencimentos.</p><?php endif; ?>
			<ul class="mini-list"><?php foreach ( array_slice( $ren, 0, 4 ) as $r ) : ?><li><a href="#vencimentos" data-goto="vencimentos"><strong><?php echo esc_html( lk_renewal_name( $r ) ); ?></strong><small><?php echo esc_html( $r->due_date ? 'vence ' . lk_date( $r->due_date ) : 'sem data' ); ?> · <?php echo $r->paid ? 'pago' : 'não pago'; ?></small></a><?php echo lk_renewal_badge( $r ); // phpcs:ignore WordPress.Security.EscapeOutput ?></li><?php endforeach; ?></ul>
		</section>
	</div>
</div>

<div class="ctab-panel" data-tab-panel="dados" hidden>
	<?php lk_form( 'client_save_all', 'stack cform', true ); ?>
		<input type="hidden" name="id" value="<?php echo (int) $c->id; ?>">
		<section class="card">
			<div class="card-head"><h3>Identificação</h3></div>
			<div class="logo-field">
				<span class="logo-plate"><?php if ( $c->logo ) : ?><img src="<?php echo esc_url( $c->logo ); ?>" alt="Logo de <?php echo esc_attr( $label ); ?>"><?php else : ?><em><?php echo esc_html( lk_initials( $label ) ); ?></em><?php endif; ?></span>
				<label class="field"><span>Logo do cliente (PNG, JPG ou WebP)</span><input type="file" name="logo_file" accept="image/png,image/jpeg,image/webp"></label>
			</div>
			<?php if ( $c->logo ) : ?><?php lk_check( 'logo_remove', 'Remover a logo' ); ?><?php endif; ?>
			<div class="grid-2">
				<?php lk_input( 'company', 'Empresa', $c->company ); ?>
				<?php lk_input( 'name', 'Contato', $c->name ); ?>
				<?php lk_input( 'cnpj', 'CNPJ / CPF', $c->cnpj, 'text', 'data-mask="doc"' ); ?>
				<?php lk_input( 'rep_cpf', 'CPF do responsável (contrato)', $c->rep_cpf, 'text', 'data-mask="doc"' ); ?>
				<?php lk_input( 'instagram', 'Instagram (@, só para referência)', $c->instagram, 'text', 'placeholder="@empresa"' ); ?>
				<?php lk_input( 'site', 'Site', $c->site, 'url', 'placeholder="https://"' ); ?>
			</div>
			<?php lk_input( 'hashtags', 'Hashtags padrão do cliente (para os posts)', (string) $c->hashtags, 'textarea', 'rows="2" placeholder="#suaempresa #nicho #cidade"' ); ?>
		</section>
		<section class="card">
			<div class="card-head"><h3>Contato e endereço</h3></div>
			<div class="grid-2">
				<?php lk_input( 'whatsapp', 'WhatsApp', $c->whatsapp, 'tel', 'data-mask="phone"' ); ?>
				<?php lk_input( 'phone', 'Telefone', $c->phone, 'tel', 'data-mask="phone"' ); ?>
				<?php lk_input( 'email', 'E-mail', $c->email, 'email' ); ?>
				<?php lk_input( 'birthday', 'Aniversário', $c->birthday, 'date' ); ?>
				<?php lk_input( 'cep', 'CEP', $c->cep, 'text', 'inputmode="numeric"' ); ?>
				<?php lk_input( 'city', 'Cidade/UF', $c->city ); ?>
			</div>
			<?php lk_input( 'address', 'Endereço', $c->address ); ?>
		</section>
		<section class="card">
			<div class="card-head"><h3>Equipe e pacote</h3></div>
			<?php $team = lk_team_options( '—' ); ?>
			<div class="grid-2">
				<?php lk_select( 'designer_id', 'Designer', $team, $c->designer_id ); ?>
				<?php lk_select( 'social_id', 'Social media', $team, $c->social_id ); ?>
				<?php lk_select( 'atendimento_id', 'Atendimento', $team, $c->atendimento_id ); ?>
				<?php lk_select( 'trafego_id', 'Gestor de tráfego', $team, $c->trafego_id ); ?>
				<?php lk_select( 'revisor_id', 'Revisão (quem confere a arte)', array( '' => '— o atendimento —' ) + array_slice( $team, 1, null, true ), $c->revisor_id ); ?>
				<?php lk_input( 'posts_quota', 'Pacote: artes por mês', $c->posts_quota ?: '', 'number', 'min="0" max="200" placeholder="Ex.: 12"' ); ?>
			</div>
		</section>
		<?php if ( lk_can( 'financeiro' ) ) : ?>
		<section class="card">
			<div class="card-head"><h3>Cobrança</h3></div>
			<div class="grid-2">
				<?php lk_input( 'monthly_fee', 'Mensalidade (R$)', $c->monthly_fee > 0 ? number_format( $c->monthly_fee, 2, ',', '.' ) : '', 'text', 'inputmode="decimal"' ); ?>
				<?php lk_input( 'due_day', 'Dia do vencimento', $c->due_day ?: '', 'number', 'min="1" max="28"' ); ?>
			</div>
			<?php lk_check( 'billing_active', 'Gerar mensalidade automática', (bool) $c->billing_active ); ?>
		</section>
		<?php endif; ?>
		<section class="card">
			<div class="card-head"><h3>Preferências</h3></div>
			<div class="grid-2">
				<?php lk_input( 'color', 'Cor no calendário', $c->color ?: '#14E9EC', 'color' ); ?>
				<?php lk_select( 'source', 'Como chegou (origem)', array( '' => '—' ) + lk_list_options( 'origens' ), $c->source ); ?>
				<?php lk_input( 'meta_ad_account', 'Conta de anúncios Meta', $c->meta_ad_account, 'text', 'placeholder="act_123..."' ); ?>
			</div>
			<?php lk_check( 'ads_visible', 'O cliente vê o tráfego pago', (bool) $c->ads_visible ); ?>
			<?php lk_check( 'email_optout', 'O cliente NÃO quer receber avisos por e-mail', (bool) $c->email_optout ); ?>
			<?php lk_input( 'notes', 'Anotações internas', $c->notes, 'textarea', 'rows="3"' ); ?>
			<?php if ( $user ) : ?>
			<div class="support-box">
			<strong>Suporte ao acesso</strong>
			<p class="muted small">Login: <?php echo esc_html( $user->user_email ); ?>. Para redefinir, digite uma nova senha e salve.</p>
			<?php lk_input( 'new_password', 'Nova senha do cliente', '', 'text', 'autocomplete="off" minlength="8"' ); ?>
			</div>
			<?php endif; ?>
		</section>
		<div class="form-actions form-actions--sticky"><span class="muted small">Dados, equipe, cobrança e preferências são salvos juntos.</span><button type="submit" class="btn btn--primary"><?php echo lk_icon( 'check', 16 ); // phpcs:ignore ?><span>Salvar tudo</span></button></div>
	</form>
	<div class="side-actions">
		<?php lk_action_button( 'client_new_invite', array( 'id' => $c->id ), $user ? 'Gerar novo link de acesso' : 'Gerar novo convite', 'btn btn--ghost btn--sm', $user ? 'O cliente já tem acesso. Gerar um novo link permite recadastrar e trocar a senha. Continuar?' : '' ); ?>
		<?php if ( lk_is_admin() ) : ?>
			<?php lk_action_button( 'client_delete', array( 'id' => $c->id ), 'Excluir cliente', 'btn btn--danger btn--sm', 'Excluir este cliente e o acesso dele? Isso não pode ser desfeito.' ); ?>
		<?php endif; ?>
	</div>
</div>

<div class="ctab-panel" data-tab-panel="contratos" hidden>
	<?php echo lk_client_contracts_html( $c ); // phpcs:ignore WordPress.Security.EscapeOutput ?>
</div>

<div class="ctab-panel" data-tab-panel="briefing" hidden>
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
	<?php echo lk_client_forms_html( $c ); // phpcs:ignore WordPress.Security.EscapeOutput ?>
</div>

<div class="ctab-panel" data-tab-panel="posts" hidden>
	<?php
	$cposts = lk_posts( 'p.client_id = %d AND ( p.scheduled_at >= %s OR p.stage <> %s )', array( $c->id, $ym_now . '-01', lk_stage_for( 'publicado' ) ), 'p.scheduled_at IS NULL, p.scheduled_at LIMIT 12' );
	?>
	<section class="card">
		<div class="card-head"><h3>Conteúdo</h3><span class="row-btns"><a class="btn btn--ghost btn--sm" href="<?php echo esc_url( lk_panel_url( 'planejamento', 0, array( 'cliente' => $c->id, 'mes' => $ym_nxt ) ) ); ?>">Planejar <?php echo esc_html( lk_month_name( gmdate( 'n', strtotime( $ym_nxt . '-01' ) ) ) ); ?></a><a class="btn btn--ghost btn--sm" href="<?php echo esc_url( lk_panel_url( 'conteudo', 0, array( 'cliente' => $c->id ) ) ); ?>">Calendário</a></span></div>
		<div class="quota-row"><?php echo lk_quota_html( $c, $ym_now ) . lk_quota_html( $c, $ym_nxt ); // phpcs:ignore ?></div>
		<?php if ( ! $cposts ) : ?><p class="muted small">Nenhum post ainda.</p><?php endif; ?>
		<ul class="mini-list mini-list--thumbs"><?php foreach ( $cposts as $p ) : ?><li><a class="ml-row" href="<?php echo esc_url( lk_panel_url( 'post', $p->id ) ); ?>"><?php echo lk_thumb_html( $p, 'row-thumb' ); // phpcs:ignore ?><span class="ml-txt"><strong><?php echo esc_html( $p->title ); ?></strong><small><?php echo lk_stage_chip( $p ); // phpcs:ignore ?> <?php echo esc_html( $p->scheduled_at ? lk_date( $p->scheduled_at, 'd/m H:i' ) : 'sem data' ); ?></small></span></a></li><?php endforeach; ?></ul>
	</section>
	<?php echo lk_client_import_html( $c ); // phpcs:ignore WordPress.Security.EscapeOutput ?>
</div>

<div class="ctab-panel" data-tab-panel="drive" hidden>
	<?php if ( function_exists( 'lk_google_connected' ) && lk_google_connected() ) : $fl = lk_drive_folder_link( array( 'Clientes', $label ) ); ?>
	<section class="card"><div class="pay-row"><span><strong>Pasta do cliente no Google Drive</strong><small>Fotos, artes, vídeos e as planilhas de conteúdo e planejamento ficam guardados aqui.</small></span>
		<?php if ( $fl ) : ?><a class="btn btn--primary btn--sm" href="<?php echo esc_url( $fl ); ?>" target="_blank" rel="noopener">Abrir pasta</a><?php else : ?><?php lk_action_button( 'client_folder', array( 'client_id' => $c->id ), 'Criar pasta no Drive', 'btn btn--primary btn--sm' ); ?><?php endif; ?></div></section>
	<?php endif; ?>
	<?php echo lk_client_files_html( $c ); // phpcs:ignore WordPress.Security.EscapeOutput ?>
</div>

<div class="ctab-panel" data-tab-panel="acessos" hidden>
	<?php echo lk_client_access_html( $c ); // phpcs:ignore WordPress.Security.EscapeOutput ?>
</div>

<div class="ctab-panel" data-tab-panel="vencimentos" hidden>
	<?php echo lk_client_renewals_html( $c ); // phpcs:ignore WordPress.Security.EscapeOutput ?>
</div>

<div class="ctab-panel" data-tab-panel="reunioes" hidden>
	<?php echo lk_client_meetings_html( $c ); // phpcs:ignore WordPress.Security.EscapeOutput ?>
</div>

<?php if ( ! $manage ) : ?>
<div class="ctab-panel" data-tab-panel="redes" hidden>
	<?php
	$accs  = lk_social_accounts( $c->id );
	$picks = json_decode( (string) lk_decrypt( (string) get_transient( 'lk_fbpages_' . $c->id ) ), true );
	$nets  = lk_networks();
	$more  = lk_social_pick_pending( $c->id );
	?>
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
</div>
<?php endif; ?>

<script src="<?php echo esc_url( LK_URL . 'assets/cliente-abas.js?ver=' . LK_VERSION ); ?>"></script>

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

<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
$c = ap_get( 'clients', $id );
if ( ! $c ) {
	ap_render( 'panel/404' );
}
$projects = ap_projects( 'p.client_id = %d', array( $c->id ) );
$user     = $c->user_id ? get_userdata( $c->user_id ) : null;
$invite   = $c->invite_token ? ap_invite_url( $c->invite_token ) : '';
$label    = ap_client_label( $c );
$cols     = ap_columns();

$actions  = '<a class="btn btn--ghost" href="' . esc_url( ap_client_url( '', 0, array( 'como' => $c->id ) ) ) . '">' . ap_icon( 'olho', 16 ) . '<span>Ver como cliente</span></a>';
$actions .= ap_can( 'leads' ) ? '<a class="btn btn--ghost" href="' . esc_url( ap_panel_url( 'leads', 0, array( 'abrir' => 'novo-lead', 'cliente' => $c->id ) ) ) . '">' . ap_icon( 'mais', 16 ) . '<span>Novo lead</span></a>' : '';
$actions .= ap_can( 'projetos' ) ? '<a class="btn btn--ghost" href="' . esc_url( ap_panel_url( 'pedidos', 0, array( 'abrir' => 'novo-pedido', 'cliente' => $c->id ) ) ) . '">' . ap_icon( 'mais', 16 ) . '<span>Pedido manual</span></a>' : '';
$actions .= ap_can( 'orcamentos' ) ? '<a class="btn btn--primary" href="' . esc_url( ap_panel_url( 'orcamento', 0, array( 'cliente' => $c->id ) ) ) . '">' . ap_icon( 'mais', 16 ) . '<span>Novo orçamento</span></a>' : '';
ap_panel_start( $label, 'clientes', $actions );
?>

<a class="back" href="<?php echo esc_url( ap_panel_url( 'clientes' ) ); ?>"><?php echo ap_icon( 'voltar', 16 ); // phpcs:ignore ?> Clientes</a>

<div class="detail-grid">
	<div class="detail-main">
		<section class="card <?php echo $c->approved ? '' : 'card--warn'; ?>">
			<div class="card-head"><h3>Parceiro</h3><em class="badge badge--<?php echo $c->approved ? 'ok' : 'warn'; ?>"><?php echo $c->approved ? 'Aprovado: vê preços e faz pedidos' : 'Em análise: ainda não vê preços'; ?></em></div>
			<div class="row-btns">
				<?php ap_action_button( 'client_approve_partner', array( 'id' => $c->id ), $c->approved ? 'Suspender acesso aos preços' : 'Aprovar parceiro (envia e-mail de boas-vindas)', $c->approved ? 'btn btn--ghost btn--sm' : 'btn btn--primary', $c->approved ? 'Suspender o acesso aos preços e pedidos?' : '' ); ?>
				<?php ap_action_button( 'client_pay_later', array( 'id' => $c->id ), $c->pay_later ? '✓ Pode pagar na retirada' : 'Permitir pagar na retirada', 'btn btn--ghost btn--sm' ); ?>
				<a class="btn btn--ghost btn--sm" href="<?php echo esc_url( ap_panel_url( 'mensagens', 0, array( 'cliente' => $c->id ) ) ); ?>"><?php echo ap_icon( 'chat', 14 ); // phpcs:ignore ?><span>Mensagens</span></a>
			</div>
		</section>
		<?php if ( $invite ) : ?>
			<section class="card card--accent">
				<div class="card-head"><h3>Link de convite</h3><?php echo ap_status_badge( $user ? 'ativo' : 'convidado' ); // phpcs:ignore ?></div>
				<p class="muted small">Mande este link para o cliente. Ele completa os dados (nome, telefone, CNPJ, WhatsApp) e cria a senha dele.</p>
				<div class="copy-row">
					<input type="text" readonly value="<?php echo esc_attr( $invite ); ?>" onclick="this.select()">
					<button type="button" class="btn btn--primary" data-copy="<?php echo esc_attr( $invite ); ?>"><?php echo ap_icon( 'copiar', 16 ); // phpcs:ignore ?><span>Copiar</span></button>
					<?php if ( $c->whatsapp ) : ?>
						<a class="btn btn--wa" target="_blank" rel="noopener" href="<?php echo esc_url( ap_wa_link( $c->whatsapp, 'Olá' . ( $c->name ? ', ' . $c->name : '' ) . '! Aqui está o link para criar o seu acesso à área do cliente do ' . ap_setting( 'empresa' ) . ', onde você acompanha os seus pedidos: ' . $invite ) ); ?>"><?php echo ap_icon( 'whatsapp', 16 ); // phpcs:ignore ?><span>Enviar</span></a>
					<?php endif; ?>
				</div>
			</section>
		<?php endif; ?>

		<?php
		$cq    = ap_rows( 'quotes', 'client_id = %d', array( $c->id ), 'id DESC LIMIT 10' );
		$spent = 0;
		foreach ( $projects as $pp ) {
			$spent += (float) $pp->value;
		}
		?>
		<?php if ( ap_can( 'financeiro' ) && $projects ) : ?>
			<section class="stats stats--3">
				<div class="stat"><span class="stat-label">Pedidos</span><strong><?php echo count( $projects ); ?></strong></div>
				<div class="stat"><span class="stat-label">Total comprado</span><strong class="money"><?php echo esc_html( ap_money( $spent ) ); ?></strong></div>
				<div class="stat"><span class="stat-label">Ticket médio</span><strong class="money"><?php echo esc_html( ap_money( $spent / max( 1, count( $projects ) ) ) ); ?></strong></div>
			</section>
		<?php endif; ?>
		<?php if ( $cq && ap_can( 'orcamentos' ) ) : ?>
			<section class="card">
				<div class="card-head"><h3>Orçamentos</h3></div>
				<ul class="mini-list">
					<?php foreach ( $cq as $q ) : ?><li><a href="<?php echo esc_url( ap_panel_url( 'orcamento', $q->id ) ); ?>"><strong><?php echo esc_html( $q->title ); ?></strong><small><?php echo esc_html( ( ap_quote_statuses()[ $q->status ] ?? $q->status ) . ' · ' . ap_money( $q->total ) ); ?></small></a></li><?php endforeach; ?>
				</ul>
			</section>
		<?php endif; ?>

		<section class="card">
			<div class="card-head"><h3>Pedidos</h3></div>
			<?php if ( ! $projects ) : ?>
				<p class="muted small">Nenhum pedido ainda.</p>
			<?php else : ?>
				<ul class="project-list">
					<?php foreach ( $projects as $p ) : ?>
						<li><a href="<?php echo esc_url( ap_panel_url( 'pedido', $p->id ) ); ?>">
							<span class="pl-main"><strong>#<?php echo (int) $p->id; ?> · <?php echo esc_html( $p->title ); ?></strong><small><?php echo esc_html( ( isset( $cols[ $p->status ] ) ? $cols[ $p->status ] : $p->status ) . ( $p->archived ? ' · arquivado' : '' ) ); ?></small></span>
							<span class="pl-progress muted small"><?php echo esc_html( ap_date( $p->created_at ) ); ?></span>
							<?php if ( ap_can( 'financeiro' ) ) : ?><span class="pl-value"><?php echo esc_html( ap_money( $p->value ) ); ?></span><?php endif; ?>
						</a></li>
					<?php endforeach; ?>
				</ul>
			<?php endif; ?>
		</section>
	</div>

	<aside class="detail-side">
		<section class="card">
			<div class="card-head"><h3>Dados</h3></div>
			<?php ap_form( 'client_save', 'stack', true ); ?>
				<input type="hidden" name="id" value="<?php echo (int) $c->id; ?>">
				<div class="logo-field">
					<span class="logo-plate"><?php if ( $c->logo ) : ?><img src="<?php echo esc_url( $c->logo ); ?>" alt="Logo de <?php echo esc_attr( ap_client_label( $c ) ); ?>"><?php else : ?><em><?php echo esc_html( ap_initials( ap_client_label( $c ) ) ); ?></em><?php endif; ?></span>
					<label class="field"><span>Logo do cliente (PNG, JPG ou WebP)</span><input type="file" name="logo_file" accept="image/png,image/jpeg,image/webp"></label>
				</div>
				<?php if ( $c->logo ) : ?><?php ap_check( 'logo_remove', 'Remover a logo' ); ?><?php endif; ?>
				<p class="muted small">Útil para empresas e revendedores.</p>
				<?php ap_input( 'company', 'Empresa', $c->company ); ?>
				<?php ap_input( 'name', 'Contato', $c->name ); ?>
				<?php ap_input( 'cnpj', 'CNPJ / CPF', $c->cnpj, 'text', 'data-mask="doc"' ); ?>
				<div class="grid-2">
					<?php ap_input( 'whatsapp', 'WhatsApp', $c->whatsapp, 'tel', 'data-mask="phone"' ); ?>
					<?php ap_input( 'phone', 'Telefone', $c->phone, 'tel', 'data-mask="phone"' ); ?>
				</div>
				<?php ap_input( 'email', 'E-mail', $c->email, 'email' ); ?>
				<div class="grid-2">
					<?php ap_input( 'cep', 'CEP', $c->cep, 'text', 'inputmode="numeric"' ); ?>
					<?php ap_input( 'city', 'Cidade/UF', $c->city ); ?>
				</div>
				<?php ap_input( 'address', 'Endereço', $c->address ); ?>
				<div class="grid-2">
					<?php ap_select( 'source', 'Como chegou (origem)', array( '' => '—' ) + ap_list_options( 'origens' ), $c->source ); ?>
					<?php ap_input( 'birthday', 'Aniversário', $c->birthday, 'date' ); ?>
				</div>
				<?php ap_input( 'notes', 'Anotações internas', $c->notes, 'textarea', 'rows="3"' ); ?>
				<?php if ( $user ) : ?>
					<div class="support-box">
						<strong>Suporte ao acesso</strong>
						<p class="muted small">Login: <?php echo esc_html( $user->user_email ); ?>. Para redefinir, digite uma nova senha e salve.</p>
						<?php ap_input( 'new_password', 'Nova senha do cliente', '', 'text', 'autocomplete="off" minlength="8"' ); ?>
					</div>
				<?php endif; ?>
				<div class="form-actions">
					<?php if ( $c->whatsapp ) : ?><a class="btn btn--ghost" target="_blank" rel="noopener" href="<?php echo esc_url( ap_wa_link( $c->whatsapp ) ); ?>"><?php echo ap_icon( 'whatsapp', 16 ); // phpcs:ignore ?></a><?php endif; ?>
					<button type="submit" class="btn btn--primary">Salvar</button>
				</div>
			</form>
		</section>
		<div class="side-actions">
			<?php ap_action_button( 'client_new_invite', array( 'id' => $c->id ), $user ? 'Gerar novo link de acesso' : 'Gerar novo convite', 'btn btn--ghost btn--sm', $user ? 'O cliente já tem acesso. Gerar um novo link permite recadastrar e trocar a senha. Continuar?' : '' ); ?>
			<?php if ( ap_is_admin() ) : ?>
				<?php ap_action_button( 'client_delete', array( 'id' => $c->id ), 'Excluir cliente', 'btn btn--danger btn--sm', 'Excluir este cliente e o acesso dele? Isso não pode ser desfeito.' ); ?>
			<?php endif; ?>
		</div>
	</aside>
</div>

<?php
ap_panel_end();

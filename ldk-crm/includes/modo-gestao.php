<?php
/**
 * Modo gerenciamento: usar o CRM só para organizar o trabalho, sem publicar e sem vincular redes sociais.
 *
 * Ligado (Configurações → Modo de uso), o sistema:
 *  - esconde tudo sobre vincular Instagram/Facebook/LinkedIn/YouTube e "Onde publicar";
 *  - não publica nada sozinho (a publicação automática fica parada);
 *  - mostra, em cada post, o pacote para agendar no mLabs: legenda para copiar, arte para baixar e o botão
 *    "Marcar como agendado no mLabs" (o agendamento é feito à mão por enquanto).
 *
 * Também traz os acessos (logins e senhas) guardados por cliente (aba Acessos da ficha do cliente).
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function lk_manage_only() {
	return '1' === (string) lk_setting( 'modo_gestao' );
}

/** Liga/desliga o modo com um clique (só administrador). */
function lk_do_modo_gestao_toggle() {
	lk_require( 'admin' );
	$on    = ! lk_manage_only();
	$saved = get_option( 'lk_settings', array() );
	$saved = is_array( $saved ) ? $saved : array();
	$saved['modo_gestao'] = $on ? '1' : '0';
	update_option( 'lk_settings', $saved );
	lk_back( $on ? 'Pronto: sem postar e sem vincular. Tudo que depende das redes ficou escondido.' : 'Modo completo de volta: vincular redes e publicar pelo sistema.', 'ok', lk_panel_url( 'config' ) . '#modo' );
}

/** Aviso discreto no topo do painel (só para o administrador) quando o modo está ligado. */
function lk_manage_pill_html() {
	if ( ! lk_manage_only() || ! lk_is_admin() ) {
		return '';
	}
	return '<a class="manage-pill" href="' . esc_url( lk_panel_url( 'config' ) . '#modo' ) . '" title="Sem postar e sem vincular redes. Clique para mudar.">Sem postar / vincular</a>';
}

// Bloco do dashboard que só faz sentido com redes vinculadas.
add_filter(
	'lk_dash_widget_allowed',
	function ( $ok, $id ) {
		return lk_manage_only() && in_array( $id, array( 'instagram', 'trafego_resumo' ), true ) ? false : $ok;
	},
	10,
	2
);

// Link público para o cliente vincular a rede dele: fora do ar no modo gerenciamento.
add_action(
	'admin_post_nopriv_lk_connect',
	function () {
		if ( lk_manage_only() ) {
			wp_die( 'Este link não está disponível agora.', 'Indisponível', array( 'response' => 404 ) );
		}
	},
	1
);
add_action(
	'admin_post_lk_connect',
	function () {
		if ( lk_manage_only() ) {
			wp_die( 'Este link não está disponível agora.', 'Indisponível', array( 'response' => 404 ) );
		}
	},
	1
);

/* -----------------------------------------------------------------------
 * Agendamento manual no mLabs
 * -------------------------------------------------------------------- */

function lk_do_post_mlabs() {
	lk_require( 'conteudo' );
	$p = lk_get( 'posts', lk_in( 'id', 'int' ) );
	if ( ! $p ) {
		lk_back( 'Post não encontrado.', 'erro' );
	}
	lk_post_move( $p, lk_stage_for( 'agendado' ), 'Agendado manualmente no mLabs por ' . wp_get_current_user()->display_name . '.' );
	lk_back( 'Marcado como agendado no mLabs.' );
}

/** Cartão "Para agendar no mLabs" na página do post. */
function lk_post_mlabs_html( $p ) {
	$media   = lk_post_media( $p );
	$caption = function_exists( 'lk_post_final_caption' ) ? lk_post_final_caption( $p ) : (string) $p->caption;
	ob_start();
	?>
	<section class="card card--mlabs">
		<div class="card-head"><h3>Para agendar no mLabs</h3><?php echo $p->stage === lk_stage_for( 'agendado' ) ? '<em class="badge badge--ok">agendado</em>' : ( $p->stage === lk_stage_for( 'publicado' ) ? '<em class="badge badge--ok">publicado</em>' : '<em class="badge badge--wait">falta agendar</em>' ); ?></div>
		<p class="muted small">Pegue a arte e a legenda daqui, agende no mLabs e depois marque. Por enquanto o agendamento é manual.</p>
		<?php if ( $media ) : ?>
			<div class="mlabs-files">
				<?php foreach ( $media as $i => $m ) : ?>
					<a class="btn btn--ghost btn--sm" href="<?php echo esc_url( $m['link'] ); ?>" target="_blank" rel="noopener" download><?php echo lk_icon( 'download', 15 ); // phpcs:ignore ?><span><?php echo esc_html( ( $i + 1 ) . ' · ' . $m['name'] ); ?></span></a>
				<?php endforeach; ?>
			</div>
		<?php else : ?>
			<p class="muted small">Ainda não tem arte neste post. Suba em "Arte" ao lado.</p>
		<?php endif; ?>
		<label class="field"><span>Legenda (com hashtags)</span><textarea readonly rows="6" onclick="this.select()"><?php echo esc_textarea( $caption ); ?></textarea></label>
		<div class="row-btns">
			<button type="button" class="btn btn--ghost btn--sm" data-copy="<?php echo esc_attr( $caption ); ?>">Copiar legenda</button>
			<?php if ( $p->scheduled_at ) : ?><span class="muted small">Data prevista: <strong><?php echo esc_html( lk_date( $p->scheduled_at, 'd/m/Y H:i' ) ); ?></strong></span><?php endif; ?>
		</div>
		<div class="row-btns" style="margin-top:10px">
			<?php if ( $p->stage !== lk_stage_for( 'agendado' ) && $p->stage !== lk_stage_for( 'publicado' ) ) : ?>
				<?php lk_action_button( 'post_mlabs', array( 'id' => $p->id ), lk_icon( 'check', 16 ) . '<span>Marcar como agendado no mLabs</span>', 'btn btn--primary' ); ?>
			<?php endif; ?>
			<?php if ( $p->stage !== lk_stage_for( 'publicado' ) ) : ?><?php lk_action_button( 'post_mark_published', array( 'id' => $p->id ), 'Marcar como publicado', 'btn btn--ghost' ); ?><?php endif; ?>
		</div>
	</section>
	<?php
	return ob_get_clean();
}

/* -----------------------------------------------------------------------
 * Arquivos e acessos por cliente
 * -------------------------------------------------------------------- */

function lk_do_cacc_save() {
	lk_require( 'clientes' );
	if ( ! lk_can( 'acessos' ) ) {
		wp_die( 'Sem permissão.' );
	}
	$client = lk_get( 'clients', lk_in( 'client_id', 'int' ) );
	if ( ! $client ) {
		lk_back( 'Cliente não encontrado.', 'erro' );
	}
	$id   = lk_in( 'id', 'int' );
	$data = array(
		'client_id' => $client->id,
		'label'     => lk_in( 'label' ),
		'url'       => lk_in( 'url' ),
		'login'     => lk_in( 'login' ),
		'notes'     => lk_in( 'notes', 'textarea' ),
	);
	$secret = (string) lk_in( 'secret', 'raw' );
	if ( '' !== $secret || ! $id ) {
		$data['secret'] = lk_encrypt( $secret );
	}
	$row = $id ? lk_get( 'access', $id ) : null;
	if ( $row && (int) $row->client_id === (int) $client->id ) {
		lk_update( 'access', $id, $data );
	} else {
		$data['created_by'] = get_current_user_id();
		lk_insert( 'access', $data );
	}
	lk_back( 'Acesso salvo.', 'ok', lk_panel_url( 'cliente', $client->id ) . '#acessos' );
}

function lk_do_cacc_delete() {
	lk_require( 'clientes' );
	$row = lk_get( 'access', lk_in( 'id', 'int' ) );
	if ( $row && (int) $row->client_id && lk_can( 'acessos' ) ) {
		lk_delete( 'access', $row->id );
	}
	lk_back( 'Acesso removido.' );
}

function lk_client_access_html( $client ) {
	if ( ! lk_can( 'acessos' ) ) {
		return '<section class="card"><p class="muted">Seu usuário não tem permissão para ver os acessos dos clientes. O administrador libera em Equipe.</p></section>';
	}
	$rows = lk_rows( 'access', 'client_id = %d', array( $client->id ), 'id DESC' );
	ob_start();
	?>
	<section class="card" id="acessos">
		<div class="card-head"><h3>Acessos do cliente</h3><button type="button" class="btn btn--primary btn--sm" data-open="novo-acesso"><?php echo lk_icon( 'mais', 15 ); // phpcs:ignore ?><span>Adicionar</span></button></div>
		<p class="muted small">Logins e senhas (Registro.br, hospedagem, painel do site, Instagram…). As senhas ficam criptografadas e só aparecem quando você clica em <strong>Ver</strong>.</p>
		<?php if ( ! $rows ) : ?><p class="muted small">Nenhum acesso guardado ainda.</p><?php endif; ?>
		<?php foreach ( $rows as $a ) : ?>
			<div class="pay-row">
				<span><strong><?php echo esc_html( $a->label ); ?></strong><small><?php echo esc_html( trim( ( $a->url ? $a->url : '' ) . ( $a->login ? ' · ' . $a->login : '' ), ' ·' ) ); ?><?php echo $a->notes ? ' · ' . esc_html( $a->notes ) : ''; ?></small></span>
				<span class="secret-cell"><code data-secret="<?php echo (int) $a->id; ?>">••••••••</code></span>
				<button type="button" class="btn btn--ghost btn--sm" data-reveal="<?php echo (int) $a->id; ?>">Ver</button>
				<button type="button" class="btn btn--ghost btn--sm" data-open="acesso-<?php echo (int) $a->id; ?>">Editar</button>
				<?php lk_action_button( 'cacc_delete', array( 'id' => $a->id ), 'Excluir', 'btn btn--link btn--sm', 'Excluir este acesso?' ); ?>
			</div>
		<?php endforeach; ?>
	</section>
	<?php
	$form = function ( $client, $a = null ) {
		lk_form( 'cacc_save', 'stack' );
		?>
		<input type="hidden" name="client_id" value="<?php echo (int) $client->id; ?>">
		<input type="hidden" name="id" value="<?php echo (int) ( $a ? $a->id : 0 ); ?>">
		<div class="grid-2">
			<?php lk_select( 'label', 'Tipo', array_combine( lk_access_types(), lk_access_types() ), $a ? $a->label : 'Hospedagem' ); ?>
			<?php lk_input( 'url', 'Endereço de acesso', $a ? $a->url : '', 'text', 'placeholder="https://"' ); ?>
			<?php lk_input( 'login', 'Login / e-mail', $a ? $a->login : '' ); ?>
			<?php lk_input( 'secret', $a ? 'Nova senha (em branco = manter)' : 'Senha', '', 'text', 'autocomplete="off"' ); ?>
		</div>
		<?php lk_input( 'notes', 'Observações', $a ? (string) $a->notes : '', 'textarea', 'rows="2"' ); ?>
		<div class="form-actions"><button type="button" class="btn btn--ghost" data-close>Cancelar</button><button type="submit" class="btn btn--primary">Salvar</button></div>
		</form>
		<?php
	};
	lk_modal_start( 'novo-acesso', 'Novo acesso · ' . lk_client_label( $client ) );
	$form( $client );
	lk_modal_end();
	foreach ( $rows as $a ) {
		lk_modal_start( 'acesso-' . $a->id, 'Editar acesso · ' . $a->label );
		$form( $client, $a );
		lk_modal_end();
	}
	return ob_get_clean();
}

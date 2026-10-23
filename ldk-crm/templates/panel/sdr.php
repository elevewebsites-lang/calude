<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
global $wpdb;
$miss    = lk_sdr_missing();
$agents  = lk_sdr_agents();
$by_id   = array();
foreach ( $agents as $a ) {
	$by_id[ (int) $a->id ] = $a;
}
$queue   = lk_rows( 'sdr_queue', "status = 'pendente'", array(), 'step DESC, id' );
$qt      = lk_table( 'sdr_queue' );
$sent7   = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM $qt WHERE status = 'enviado' AND sent_at >= %s", gmdate( 'Y-m-d 00:00:00', strtotime( lk_today() . ' -6 day' ) ) ) ); // phpcs:ignore WordPress.DB.PreparedSQL
$replied = (int) $wpdb->get_var( 'SELECT COUNT(*) FROM ' . lk_table( 'leads' ) . " WHERE sdr_agent > 0 AND sdr_status = 'respondeu'" ); // phpcs:ignore WordPress.DB.PreparedSQL
$touched = (int) $wpdb->get_var( "SELECT COUNT(DISTINCT lead_id) FROM $qt WHERE status = 'enviado'" ); // phpcs:ignore WordPress.DB.PreparedSQL

/** Formulário do agente (novo ou edição). */
$agent_form = function ( $a = null ) {
	lk_form( 'sdr_agent_save', 'stack' );
	echo '<input type="hidden" name="id" value="' . (int) ( $a ? $a->id : 0 ) . '">';
	echo '<div class="grid-2">';
	lk_input( 'name', 'Nome do SDR', $a ? $a->name : '', 'text', 'placeholder="Ana" required maxlength="60"' );
	lk_input( 'emoji', 'Emoji (opcional)', $a ? $a->emoji : '', 'text', 'placeholder="🦊" maxlength="4"' );
	echo '</div><div class="grid-2">';
	lk_input( 'niche', 'Nicho que ele prospecta', $a ? $a->niche : '', 'text', 'placeholder="clínica odontológica" required' );
	lk_input( 'city', 'Cidade', $a ? $a->city : '', 'text', 'placeholder="Taubaté SP" required' );
	echo '</div>';
	lk_input( 'offer', 'O que ele oferece na abordagem', $a ? $a->offer : '', 'textarea', 'rows="2" placeholder="Diagnóstico gratuito do Instagram, com 3 melhorias práticas em 15 minutos de conversa."' );
	lk_input( 'ideal', 'Cliente ideal (ajuda na nota de encaixe)', $a ? $a->ideal : '', 'textarea', 'rows="2" placeholder="Clínicas com boa nota no Google mas Instagram fraco ou parado; evitar franquias grandes."' );
	echo '<div class="grid-2">';
	lk_select( 'channel', 'Canal', lk_sdr_channels(), $a ? $a->channel : 'auto' );
	lk_input( 'daily_limit', 'Novos contatos por dia', $a ? $a->daily_limit : 10, 'number', 'min="1" max="50"' );
	echo '</div><div class="grid-3">';
	lk_input( 'min_score', 'Nota mínima (0 a 10)', $a ? $a->min_score : 5, 'number', 'min="0" max="10"' );
	lk_input( 'followups', 'Follow-ups', $a ? $a->followups : 2, 'number', 'min="0" max="5"' );
	lk_input( 'gap_days', 'Dias entre mensagens', $a ? $a->gap_days : 3, 'number', 'min="1" max="30"' );
	echo '</div><div class="form-actions"><button type="submit" class="btn btn--primary">' . ( $a ? 'Salvar' : 'Contratar SDR' ) . '</button></div></form>';
};

lk_panel_start( 'Time de SDR', 'sdr', '<a class="btn btn--ghost" href="' . esc_url( lk_panel_url( 'leads' ) ) . '">Funil de leads</a>' );
?>
<p class="muted hint">Cada <strong>SDR</strong> é um agente de IA que prospecta um nicho numa cidade. A cada hora ele busca empresas novas no Google, lê o site, dá uma <strong>nota de encaixe</strong>, cria o lead no funil e escreve a mensagem. <strong>Nada sai sozinho</strong>: a mensagem espera aqui na fila até alguém aprovar. Sem resposta, ele escreve os follow-ups.</p>

<?php if ( $miss ) : ?>
	<section class="card card--accent"><h3>Falta pouco para o time começar</h3>
		<p>Coloque <?php echo esc_html( implode( ' e ', $miss ) ); ?>.</p>
		<?php if ( lk_is_admin() ) : ?><p><a class="btn btn--primary btn--sm" href="<?php echo esc_url( lk_panel_url( 'config' ) ); ?>">Abrir Configurações</a></p><?php endif; ?>
	</section>
<?php endif; ?>

<section class="stats stats--4">
	<div class="stat"><span class="stat-label">SDRs trabalhando</span><strong><?php echo (int) count( array_filter( $agents, function ( $a ) { return (int) $a->active; } ) ); ?></strong><small>de <?php echo (int) count( $agents ); ?> no time</small></div>
	<div class="stat"><span class="stat-label">Esperando aprovação</span><strong><?php echo (int) count( $queue ); ?></strong><small>mensagens na fila</small></div>
	<div class="stat"><span class="stat-label">Enviadas</span><strong><?php echo (int) $sent7; ?></strong><small>nos últimos 7 dias</small></div>
	<div class="stat"><span class="stat-label">Responderam</span><strong><?php echo (int) $replied; ?></strong><small><?php echo $touched ? (int) round( $replied / $touched * 100 ) . '% de quem recebeu' : 'ainda sem envios'; ?></small></div>
</section>

<section class="card">
	<div class="card-head"><h3>Fila de aprovação</h3><span class="muted small">Edite o texto se quiser e aprove. O WhatsApp abre com a mensagem pronta; o e-mail sai pelo CRM.</span></div>
	<?php if ( ! $queue ) : ?>
		<div class="empty"><?php echo lk_icon( 'megafone', 28 ); // phpcs:ignore ?><p>Nenhuma mensagem esperando. <?php echo $agents ? 'Os SDRs trabalham a cada hora; para ver agora, use "Rodar agora".' : 'Contrate o primeiro SDR logo abaixo.'; ?></p></div>
	<?php endif; ?>
	<div class="sdr-queue">
	<?php foreach ( $queue as $q ) : ?>
		<?php
		$lead = lk_get( 'leads', $q->lead_id );
		if ( ! $lead ) {
			continue;
		}
		$ag    = $by_id[ (int) $q->agent_id ] ?? null;
		$phone = $lead->whatsapp;
		?>
		<article class="sdr-item">
			<header class="sdr-item-head">
				<span><strong><a href="<?php echo esc_url( lk_panel_url( 'lead', $lead->id ) ); ?>"><?php echo esc_html( lk_lead_label( $lead ) ); ?></a></strong>
					<small class="muted"><?php echo esc_html( ( $ag ? lk_sdr_label( $ag ) . ' · ' : '' ) . ( $q->step ? 'Follow-up ' . (int) $q->step : 'Primeiro contato' ) . ' · ' . lk_ago( $q->created_at ) ); ?></small></span>
				<span class="badge<?php echo $q->score >= 8 ? ' badge--ok' : ( $q->score >= 5 ? ' badge--wait' : '' ); ?>" title="<?php echo esc_attr( $q->reason ); ?>">Nota <?php echo (int) $q->score; ?>/10</span>
			</header>
			<?php if ( $q->reason && ! $q->step ) : ?><p class="muted small sdr-why">💡 <?php echo esc_html( $q->reason ); ?></p><?php endif; ?>
			<?php lk_form( 'sdr_send', 'stack sdr-form' ); ?>
				<input type="hidden" name="id" value="<?php echo (int) $q->id; ?>">
				<?php if ( 'email' === $q->channel ) : ?>
					<input type="text" name="subject" value="<?php echo esc_attr( $q->subject ); ?>" placeholder="Assunto" aria-label="Assunto">
				<?php endif; ?>
				<textarea name="body" rows="6" data-sdr-body><?php echo esc_textarea( $q->body ); ?></textarea>
				<div class="row-btns sdr-btns">
					<?php if ( $phone ) : ?><button type="submit" name="via" value="whatsapp" class="btn btn--wa btn--sm" data-sdr-wa="<?php echo esc_attr( lk_wa_link( $phone ) ); ?>"><?php echo lk_icon( 'whatsapp', 15 ); // phpcs:ignore ?><span>Abrir WhatsApp e marcar enviado</span></button><?php endif; ?>
					<?php if ( is_email( $lead->email ) ) : ?><button type="submit" name="via" value="email" class="btn btn--<?php echo 'email' === $q->channel ? 'primary' : 'ghost'; ?> btn--sm" data-confirm="Enviar este e-mail para <?php echo esc_attr( $lead->email ); ?>?"><?php echo lk_icon( 'email', 15 ); // phpcs:ignore ?><span>Enviar e-mail</span></button><?php endif; ?>
				</div>
			</form>
			<div class="row-btns sdr-more">
				<?php lk_action_button( 'sdr_lead', array( 'lead_id' => $lead->id, 'what' => 'respondeu' ), '💬 Já respondeu', 'btn btn--ghost btn--sm' ); ?>
				<?php lk_action_button( 'sdr_discard', array( 'id' => $q->id ), 'Descartar', 'btn btn--ghost btn--sm' ); ?>
				<?php lk_action_button( 'sdr_lead', array( 'lead_id' => $lead->id, 'what' => 'optout' ), 'Não contatar', 'btn btn--ghost btn--sm', 'Marcar como perdido e nunca mais abordar?' ); ?>
			</div>
		</article>
	<?php endforeach; ?>
	</div>
</section>

<section class="card">
	<div class="card-head"><h3>O time</h3></div>
	<div class="sdr-agents">
	<?php foreach ( $agents as $a ) : ?>
		<article class="sdr-agent<?php echo $a->active ? '' : ' is-off'; ?>">
			<header class="sdr-item-head">
				<span><strong><?php echo esc_html( lk_sdr_label( $a ) ); ?></strong><small class="muted"><?php echo esc_html( $a->niche . ' · ' . $a->city ); ?></small></span>
				<span class="badge<?php echo $a->active ? ' badge--ok' : ''; ?>"><?php echo $a->active ? 'Trabalhando' : 'Pausado'; ?></span>
			</header>
			<p class="small">Hoje: <strong><?php echo (int) lk_sdr_today_count( $a->id ); ?>/<?php echo (int) $a->daily_limit; ?></strong> contatos · <?php echo esc_html( lk_sdr_channels()[ $a->channel ] ?? '' ); ?> · <?php echo (int) $a->followups; ?> follow-up(s) a cada <?php echo (int) $a->gap_days; ?> dia(s)</p>
			<?php if ( $a->last_run ) : ?><p class="muted small">Último turno <?php echo esc_html( lk_ago( $a->last_run ) ); ?>: <?php echo esc_html( $a->last_note ); ?></p><?php endif; ?>
			<div class="row-btns">
				<?php if ( ! $miss ) : ?><?php lk_action_button( 'sdr_run', array( 'id' => $a->id ), '▶ Rodar agora', 'btn btn--primary btn--sm' ); ?><?php endif; ?>
				<?php lk_action_button( 'sdr_agent_toggle', array( 'id' => $a->id ), $a->active ? 'Pausar' : 'Ativar', 'btn btn--ghost btn--sm' ); ?>
				<?php lk_action_button( 'sdr_agent_delete', array( 'id' => $a->id ), 'Remover', 'btn btn--danger btn--sm', 'Remover este SDR? Os leads continuam no funil.' ); ?>
			</div>
			<details class="sdr-edit"><summary class="btn btn--ghost btn--sm">Editar</summary><?php $agent_form( $a ); ?></details>
		</article>
	<?php endforeach; ?>
	</div>
	<details class="sdr-new"<?php echo $agents ? '' : ' open'; ?>><summary class="btn btn--primary"><?php echo lk_icon( 'mais', 16 ); // phpcs:ignore ?><span>Contratar SDR</span></summary>
		<?php $agent_form(); ?>
	</details>
</section>

<p class="muted small">Abordagem transparente (LGPD): toda primeira mensagem diz quem somos e oferece a saída ("se preferirem não receber, é só avisar"). Quem pedir, marque <em>Não contatar</em>. A busca do Google é cobrada por consulta (há cota grátis) e cada empresa avaliada usa uma chamada de IA.</p>
<script src="<?php echo esc_url( LK_URL . 'assets/sdr.js?ver=' . LK_VERSION ); ?>"></script>
<?php
lk_panel_end();

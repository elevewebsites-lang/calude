<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
$sel    = isset( $_GET['cliente'] ) ? absint( $_GET['cliente'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification
$inbox  = lk_chat_inbox();
if ( ! $sel && $inbox ) {
	$sel = (int) $inbox[0]->client_id;
}
$client = $sel ? lk_get( 'clients', $sel ) : null;
lk_panel_start( 'Mensagens', 'mensagens' );
?>
<div class="inbox">
	<aside class="inbox-list">
		<?php if ( ! $inbox ) : ?><p class="muted small">Nenhuma conversa ainda. Quando um parceiro mandar mensagem pelo painel dele, aparece aqui (e o sininho avisa).</p><?php endif; ?>
		<?php foreach ( $inbox as $row ) : ?>
			<?php $c = lk_get( 'clients', $row->client_id ); $m = lk_get( 'messages', $row->last_id ); if ( ! $c || ! $m ) { continue; } ?>
			<a class="inbox-item<?php echo (int) $row->client_id === $sel ? ' is-active' : ''; ?>" href="<?php echo esc_url( lk_panel_url( 'mensagens', 0, array( 'cliente' => $c->id ) ) ); ?>">
				<strong><?php echo esc_html( lk_client_label( $c ) ); ?></strong><?php if ( (int) $row->unread ) : ?><em class="bell-n"><?php echo (int) $row->unread; ?></em><?php endif; ?>
				<small><?php echo esc_html( ( $m->from_client ? '' : 'Você: ' ) . wp_trim_words( $m->body, 9 ) ); ?></small>
				<small class="muted"><?php echo esc_html( lk_ago( $m->created_at ) ); ?></small>
			</a>
		<?php endforeach; ?>
		<label class="field"><span>Nova conversa com</span><select onchange="if(this.value)location.href='<?php echo esc_url( lk_panel_url( 'mensagens' ) ); ?>?cliente='+this.value"><?php foreach ( lk_client_options( 'Escolha o parceiro…' ) as $k => $v ) : ?><option value="<?php echo esc_attr( $k ); ?>"><?php echo esc_html( $v ); ?></option><?php endforeach; ?></select></label>
	</aside>
	<?php if ( $client ) : ?>
		<?php $orders = lk_posts( "p.client_id = %d", array( $client->id ), "p.id DESC LIMIT 30" ); ?>
		<section class="card chat" data-chat data-client="<?php echo (int) $client->id; ?>">
			<div class="chat-head"><strong><a href="<?php echo esc_url( lk_panel_url( 'cliente', $client->id ) ); ?>"><?php echo esc_html( lk_client_label( $client ) ); ?></a></strong><?php if ( $client->whatsapp ) : ?><a class="btn btn--wa btn--sm" href="<?php echo esc_url( lk_wa_link( $client->whatsapp ) ); ?>" target="_blank" rel="noopener"><?php echo lk_icon( 'whatsapp', 14 ); // phpcs:ignore ?><span>WhatsApp</span></a><?php endif; ?></div>
			<div class="chat-list" data-chat-list><p class="muted small center">Carregando…</p></div>
			<form class="chat-form" data-chat-form>
				<select name="project"><option value="0">Assunto geral</option><?php foreach ( $orders as $o ) : ?><option value="<?php echo (int) $o->id; ?>">Post #<?php echo (int) $o->id; ?> · <?php echo esc_html( $o->title ); ?></option><?php endforeach; ?></select>
				<textarea rows="3" placeholder="Responder… (Ctrl + Enter envia)" required></textarea>
				<button type="submit" class="btn btn--primary">Enviar</button>
			</form>
		</section>
	<?php endif; ?>
</div>
<?php
lk_panel_end();

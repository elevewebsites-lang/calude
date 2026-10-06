<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
$orders = ap_projects( 'p.client_id = %d', array( $client->id ) );
$wa     = ap_setting( 'whatsapp' );
ap_client_start( 'Mensagens', $client );
?>
<section class="chello">
	<span class="eyebrow">Mensagens</span>
	<h1>Fale com a <?php echo esc_html( ap_setting( 'empresa' ) ); ?></h1>
	<p class="muted">Tire dúvidas sobre um pedido ou qualquer assunto. Respondemos por aqui e você recebe um aviso por e-mail.<?php if ( $wa ) : ?> Se for urgente, <a href="<?php echo esc_url( ap_wa_link( $wa, 'Olá! Sou parceiro (' . ap_client_label( $client ) . ') e preciso de suporte.' ) ); ?>" target="_blank" rel="noopener">chame no WhatsApp</a>.<?php endif; ?></p>
</section>
<section class="card chat" data-chat data-client="<?php echo (int) $client->id; ?>">
	<div class="chat-list" data-chat-list><p class="muted small center">Carregando…</p></div>
	<form class="chat-form" data-chat-form>
		<select name="project"><option value="0">Assunto geral</option><?php foreach ( $orders as $o ) : ?><option value="<?php echo (int) $o->id; ?>"<?php selected( isset( $_GET['pedido'] ) ? absint( $_GET['pedido'] ) : 0, (int) $o->id ); // phpcs:ignore ?>>Pedido #<?php echo (int) $o->id; ?> · <?php echo esc_html( $o->title ); ?></option><?php endforeach; ?></select>
		<textarea rows="3" placeholder="Escreva a sua mensagem… (Ctrl + Enter envia)" required></textarea>
		<button type="submit" class="btn btn--primary">Enviar</button>
	</form>
</section>
<?php
ap_client_end();

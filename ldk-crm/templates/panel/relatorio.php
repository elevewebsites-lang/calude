<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
$r = lk_get( 'reports', $id );
if ( ! $r ) {
	lk_render( 'panel/404' );
}
$c = lk_get( 'clients', $r->client_id );
$d = lk_json( $r->data );
lk_panel_start( 'Relatório · ' . lk_client_label( $c ) . ' · ' . lk_month_label( $r->period ), 'relatorios', '<a class="btn btn--ghost" href="' . esc_url( lk_report_url( $r ) ) . '" target="_blank">' . lk_icon( 'olho', 16 ) . '<span>Ver como o cliente</span></a>' );
?>
<a class="back" href="<?php echo esc_url( lk_panel_url( 'relatorios' ) ); ?>"><?php echo lk_icon( 'voltar', 16 ); // phpcs:ignore ?> Relatórios</a>
<?php if ( 'mlabs' === $r->kind ) : ?>
	<?php $secs = $d['sections'] ?? array(); $hid = array_map( 'intval', (array) ( $d['hidden'] ?? array() ) ); $edt = (array) ( $d['edited_text'] ?? array() ); ?>
	<?php lk_form( 'mlabs_edit', 'stack' ); ?>
		<input type="hidden" name="id" value="<?php echo (int) $r->id; ?>">
		<section class="card">
			<div class="card-head"><h3>Link do relatório</h3><span class="muted small">feito a partir do PDF do mLabs</span></div>
			<div class="copy-row"><input type="text" readonly value="<?php echo esc_attr( lk_report_url( $r ) ); ?>" onclick="this.select()"><button type="button" class="btn btn--primary" data-copy="<?php echo esc_attr( lk_report_url( $r ) ); ?>">Copiar</button></div>
		</section>
		<section class="card">
			<div class="card-head"><h3>O que aparece no relatório</h3><span class="muted small">desmarque o que não quiser mostrar ao cliente</span></div>
			<ul class="mini-list">
				<?php foreach ( $secs as $i => $s ) : ?>
					<li>
						<label class="check"><input type="checkbox" name="show[]" value="<?php echo (int) $i; ?>" <?php checked( ! in_array( $i, $hid, true ) ); ?>><span><strong><?php echo esc_html( ( lk_mlabs_networks()[ $s['network'] ] ?? '' ) . ' · ' . ( $s['title'] ? $s['title'] : $s['type'] ) ); ?></strong></span></label>
					</li>
					<?php if ( 'text' === $s['type'] ) : ?><li><label class="field" style="width:100%"><span>Texto (você pode reescrever; linha em branco separa parágrafos)</span><textarea name="text[<?php echo (int) $i; ?>]" rows="6"><?php echo esc_textarea( isset( $edt[ $i ] ) ? $edt[ $i ] : implode( "

", $s['paragraphs'] ) ); ?></textarea></label></li><?php endif; ?>
				<?php endforeach; ?>
			</ul>
			<p class="muted small">Marcado = aparece no relatório.</p>
		</section>
		<section class="card">
			<?php lk_input( 'notes', 'Análise e próximos passos (aparece no fim do relatório)', $r->notes, 'textarea', 'rows="8" placeholder="O que funcionou, o que vamos testar no próximo mês…"' ); ?>
			<div class="form-actions">
				<?php lk_check( 'publicar', 'Publicar (o cliente vê na área dele)', 'publicado' === $r->status ); ?>
				<button type="submit" class="btn btn--ghost">Salvar</button>
				<button type="submit" name="enviar" value="1" class="btn btn--primary">Salvar e enviar por e-mail</button>
			</div>
		</section>
	</form>
	<?php lk_panel_end(); return; ?>
<?php endif; ?>
<?php if ( empty( $d['ig'] ) ) : ?><div class="flash flash--warn">Instagram não conectado para este cliente: o relatório sai sem os números da conta. Conecte na ficha do cliente e gere de novo.</div><?php elseif ( ! empty( $d['ig']['error'] ) ) : ?><div class="flash flash--erro">Instagram: <?php echo esc_html( $d['ig']['error'] ); ?></div><?php endif; ?>
<?php lk_form( 'report_save', 'stack card' ); ?>
	<input type="hidden" name="id" value="<?php echo (int) $r->id; ?>">
	<?php lk_input( 'notes', 'Análise e próximos passos (aparece no relatório)', $r->notes, 'textarea', 'rows="8" placeholder="O que funcionou, o que vamos testar no próximo mês…"' ); ?>
	<?php if ( lk_can( 'trafego' ) ) : ?><?php lk_check( 'show_ads', 'Mostrar tráfego pago neste relatório' . ( $c->ads_visible ? '' : ' (liberado só se o cliente tiver "mostrar tráfego" ligado)' ), ! empty( $d['show_ads'] ) ); ?><?php endif; ?>
	<div class="form-actions">
		<?php lk_check( 'publicar', 'Publicar (o cliente vê na área dele)', 'publicado' === $r->status ); ?>
		<button type="submit" class="btn btn--ghost">Salvar</button>
		<button type="submit" name="enviar" value="1" class="btn btn--primary">Salvar e enviar por e-mail</button>
	</div>
</form>
<?php lk_form( 'report_generate', 'inline-form' ); ?><input type="hidden" name="client_id" value="<?php echo (int) $r->client_id; ?>"><input type="hidden" name="period" value="<?php echo esc_attr( $r->period ); ?>"><button type="submit" class="btn btn--link btn--sm">Atualizar os números</button></form>
<?php
lk_panel_end();

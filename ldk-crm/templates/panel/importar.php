<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
$token = isset( $_GET['t'] ) ? preg_replace( '/[^a-z0-9]/', '', strtolower( sanitize_text_field( wp_unslash( $_GET['t'] ) ) ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification
$data  = $token ? get_transient( 'lk_import_' . $token ) : false;
if ( $data && (int) $data['user'] !== get_current_user_id() ) {
	$data = false;
}
$pre = isset( $_GET['cliente'] ) ? absint( $_GET['cliente'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification
lk_panel_start( 'Importar planilha', 'conteudo', '<a class="btn btn--ghost" href="' . esc_url( lk_panel_url( 'conteudo' ) ) . '">Voltar ao conteúdo</a>' );
if ( ! $data ) :
	?>
<section class="card">
	<div class="card-head"><h3>Suba o Excel e o sistema preenche tudo</h3></div>
	<p class="muted">Vale a exportação do Monday (.xlsx) ou qualquer planilha com as colunas <strong>Name</strong> (ou Nome), <strong>Data</strong>, <strong>Produto</strong>, <strong>Arte</strong>, <strong>Legenda</strong>, <strong>Vídeo</strong> e <strong>Pessoa</strong>. O sistema reconhece as colunas sozinho e mostra uma prévia antes de importar.</p>
	<?php lk_form( 'import_upload', 'stack', true ); ?>
		<label class="field"><span>Arquivo (.xlsx ou .csv, até 15 MB)</span><input type="file" name="planilha" accept=".xlsx,.csv" required></label>
		<div class="form-actions"><button type="submit" class="btn btn--primary"><?php echo lk_icon( 'upload', 16 ); // phpcs:ignore ?><span>Ler a planilha</span></button></div>
	</form>
	<ul class="mini-list" style="margin-top:14px">
		<li><span><strong>O que entra:</strong> só as linhas com texto no nome. Linhas vazias, só com "." e os títulos de mês são ignorados, assim como os itens cancelados.</span></li>
		<li><span><strong>Sem duplicar:</strong> se você subir a mesma planilha de novo, o que já existe (mesmo cliente, título e data) é pulado.</span></li>
		<li><span><strong>Nada é publicado:</strong> os posts entram só para consulta e acompanhamento, sem disparar publicação nas redes.</span></li>
	</ul>
</section>
	<?php
else :
	$items   = $data['items'];
	$stages  = lk_stages();
	$by_st   = array();
	$by_mo   = array();
	$by_fmt  = array();
	foreach ( $items as $it ) {
		$st = lk_import_stage( (int) $it['rank'] );
		$by_st[ $st ] = ( $by_st[ $st ] ?? 0 ) + 1;
		$mo = $it['date'] ? substr( $it['date'], 0, 7 ) : '';
		$by_mo[ $mo ] = ( $by_mo[ $mo ] ?? 0 ) + 1;
		$by_fmt[ $it['format'] ] = ( $by_fmt[ $it['format'] ] ?? 0 ) + 1;
	}
	ksort( $by_mo );
	$guess  = $pre ? $pre : ( ! empty( $data['client_id'] ) ? (int) $data['client_id'] : lk_import_guess_client( $data['board'] ) );
	$gc     = $guess ? lk_get( 'clients', $guess ) : null;
	?>
<section class="stats stats--4">
	<div class="stat"><span class="stat-label">Posts para importar</span><strong><?php echo (int) count( $items ); ?></strong><small><?php echo esc_html( $data['file'] ); ?></small></div>
	<div class="stat"><span class="stat-label">Linhas vazias ignoradas</span><strong><?php echo (int) $data['skipped']['vazios']; ?></strong><small>sem texto ou só com "."</small></div>
	<div class="stat"><span class="stat-label">Cancelados ignorados</span><strong><?php echo (int) $data['skipped']['cancelados']; ?></strong><small>não entram</small></div>
	<div class="stat"><span class="stat-label">Quadro</span><strong style="font-size:20px"><?php echo esc_html( $data['board'] ? $data['board'] : '—' ); ?></strong><small><?php echo esc_html( strtok( (string) $data['about'], "\n" ) ); ?></small></div>
</section>
<div class="dash-grid">
	<div class="dash-col">
		<section class="card">
			<div class="card-head"><h3>Receber no cliente</h3></div>
			<?php lk_form( 'import_commit', 'stack' ); ?>
				<input type="hidden" name="token" value="<?php echo esc_attr( $token ); ?>">
				<?php lk_select( 'client_id', 'Cliente', lk_client_options( 'Escolha o cliente…' ), $guess ? (string) $guess : '', 'required' ); ?>
				<?php if ( $gc && ! empty( $data['client_id'] ) ) : ?><p class="muted small">Os posts vão para <strong><?php echo esc_html( lk_client_label( $gc ) ); ?></strong>, de onde você subiu a planilha.</p><?php elseif ( $gc ) : ?><p class="muted small">O nome do quadro combina com <strong><?php echo esc_html( lk_client_label( $gc ) ); ?></strong>. Confira se está certo.</p><?php endif; ?>
				<?php if ( ! empty( $data['instagram'] ) ) : lk_check( 'usar_instagram', 'Preencher o Instagram do cliente com @' . $data['instagram'] . ' (só se estiver em branco)', true ); endif; ?>
				<div class="form-actions"><a class="btn btn--ghost" href="<?php echo esc_url( lk_panel_url( 'importar' ) ); ?>">Cancelar</a><button type="submit" class="btn btn--primary">Importar <?php echo (int) count( $items ); ?> posts</button></div>
			</form>
		</section>
		<section class="card">
			<div class="card-head"><h3>Como ficam as etapas</h3></div>
			<ul class="mini-list">
				<?php foreach ( $by_st as $st => $n ) : ?><li><span><strong><?php echo esc_html( $stages[ $st ] ?? $st ); ?></strong></span><em class="badge"><?php echo (int) $n; ?></em></li><?php endforeach; ?>
			</ul>
			<p class="muted small">"Postado/Agendado" vira Publicado. "p/ aprovação" vai para a aprovação do cliente. "Para entregar" e "para gravação" vão para Design. Item sem status e com data passada entra como Publicado; com data futura, no Planejamento.</p>
		</section>
		<section class="card">
			<div class="card-head"><h3>Por formato</h3></div>
			<ul class="mini-list"><?php foreach ( $by_fmt as $f => $n ) : ?><li><span><strong><?php echo esc_html( lk_formats()[ $f ] ?? $f ); ?></strong></span><em class="badge"><?php echo (int) $n; ?></em></li><?php endforeach; ?></ul>
		</section>
	</div>
	<div class="dash-col">
		<section class="card">
			<div class="card-head"><h3>Por mês</h3></div>
			<ul class="mini-list">
				<?php foreach ( $by_mo as $mo => $n ) : ?><li><span><strong><?php echo esc_html( $mo ? lk_month_name( (int) substr( $mo, 5, 2 ) ) . ' ' . substr( $mo, 0, 4 ) : 'Sem data' ); ?></strong></span><em class="badge"><?php echo (int) $n; ?></em></li><?php endforeach; ?>
			</ul>
		</section>
	</div>
</div>
<section class="card">
	<div class="card-head"><h3>Prévia (primeiros 25)</h3></div>
	<div class="pros-wrap"><table class="pros-table"><thead><tr><th>Data</th><th>Título</th><th>Formato</th><th>Etapa</th><th>Observações</th></tr></thead><tbody>
		<?php foreach ( array_slice( $items, 0, 25 ) as $it ) : ?>
			<tr><td><?php echo esc_html( $it['date'] ? lk_date( $it['date'] ) : '—' ); ?></td><td><strong><?php echo esc_html( $it['title'] ); ?></strong></td><td><?php echo esc_html( lk_formats()[ $it['format'] ] ?? $it['format'] ); ?></td><td><?php echo esc_html( $stages[ lk_import_stage( (int) $it['rank'] ) ] ?? '' ); ?></td><td class="muted small"><?php echo esc_html( str_replace( 'Importado da planilha · ', '', $it['notes'] ) ); ?></td></tr>
		<?php endforeach; ?>
	</tbody></table></div>
</section>
	<?php
endif;
lk_panel_end();

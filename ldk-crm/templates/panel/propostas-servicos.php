<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
// phpcs:disable WordPress.Security.NonceVerification.Recommended -- só leitura de parâmetros de navegação.
$types = lk_prop_types();
$aba   = isset( $_GET['aba'] ) ? sanitize_key( $_GET['aba'] ) : 'servico';
$aba   = ( isset( $types[ $aba ] ) || ( 'config' === $aba && lk_is_admin() ) ) ? $aba : 'servico';
$edit  = isset( $_GET['editar'] ) ? absint( $_GET['editar'] ) : 0;
$novo  = isset( $_GET['novo'] );
// phpcs:enable

$acoes = '<a class="btn btn--ghost" href="' . esc_url( lk_panel_url( 'propostas' ) ) . '">' . lk_icon( 'lista', 16 ) . '<span>Propostas</span></a>'
	. '<a class="btn btn--primary" href="' . esc_url( lk_panel_url( 'proposta' ) ) . '">' . lk_icon( 'mais', 16 ) . '<span>Nova proposta</span></a>';
lk_panel_start( 'Serviços e nichos das propostas', 'propostas-servicos', $acoes );
lk_prop_assets();
?>
<nav class="tabs prop-tabs">
	<?php foreach ( $types as $k => $t ) : ?>
		<a class="<?php echo $k === $aba ? 'is-active' : ''; ?>" href="<?php echo esc_url( lk_panel_url( 'propostas-servicos', 0, array( 'aba' => $k ) ) ); ?>"><?php echo esc_html( $t[2] ); ?><em><?php echo (int) wp_count_posts( $t[0] )->publish; ?></em></a>
	<?php endforeach; ?>
	<?php if ( lk_is_admin() ) : ?>
		<a class="<?php echo 'config' === $aba ? 'is-active' : ''; ?>" href="<?php echo esc_url( lk_panel_url( 'propostas-servicos', 0, array( 'aba' => 'config' ) ) ); ?>">Textos padrão, marca e trabalhos</a>
	<?php endif; ?>
</nav>

<?php if ( 'config' === $aba ) : ?>
	<?php $s = ldkp_settings(); ?>
	<p class="muted prop-intro">Isto vale para todas as propostas: logo, contatos, números, quem somos, clientes, depoimentos, metodologia e a lista de trabalhos (criativos).</p>
	<?php lk_form( 'prop_settings_save', 'prop-lib-form' ); ?>
		<div class="ldkp-builder">
			<?php
			$n = 1;
			foreach ( ldkp_settings_fields() as $title => $group ) {
				ldkp_section_open( str_pad( $n++, 2, '0', STR_PAD_LEFT ), $title, '', 1 === $n - 1 );
				foreach ( $group as $key => $def ) {
					ldkp_field( 'ep[' . $key . ']', $def[0], $def[1], $s[ $key ], isset( $def[2] ) ? $def[2] : '' );
				}
				ldkp_section_close();
			}
			?>
		</div>
		<div class="prop-submit"><span></span><button type="submit" class="btn btn--primary btn--lg">Salvar configurações</button></div>
	</form>

<?php elseif ( $edit || $novo ) : ?>
	<?php
	list( $pt, $label, , $fields_fn ) = $types[ $aba ];
	$item = $edit ? get_post( $edit ) : null;
	if ( $edit && ( ! $item || $item->post_type !== $pt ) ) {
		$item = null;
	}
	?>
	<div class="prop-head">
		<h2><?php echo $item ? esc_html( 'Editar ' . strtolower( $label ) . ': ' . $item->post_title ) : esc_html( 'Novo ' . strtolower( $label ) ); ?></h2>
		<p class="muted"><?php echo 'servico' === $aba ? 'O serviço vira um plano na seção de investimento e traz os itens de escopo. Use {cliente_curto} para o nome do cliente.' : 'Os textos do nicho preenchem a capa, a introdução, o diagnóstico e o encerramento. Use {cliente}, {cliente_curto} e {nicho}.'; ?></p>
	</div>
	<?php lk_form( 'prop_lib_save', 'prop-lib-form' ); ?>
		<input type="hidden" name="tipo" value="<?php echo esc_attr( $aba ); ?>">
		<input type="hidden" name="id" value="<?php echo (int) ( $item ? $item->ID : 0 ); ?>">
		<div class="ldkp-builder">
			<section class="ldkp-section prop-plain">
				<div class="ldkp-section-body">
					<div class="ldkp-grid-2">
						<div class="ldkp-field"><label for="prop_titulo">Nome</label><input type="text" id="prop_titulo" name="titulo" value="<?php echo esc_attr( $item ? $item->post_title : '' ); ?>" required></div>
						<div class="ldkp-field"><label for="prop_ordem">Ordem na lista</label><input type="number" id="prop_ordem" name="ordem" min="0" value="<?php echo (int) ( $item ? $item->menu_order : 0 ); ?>"><p class="ldkp-help">Menor aparece primeiro.</p></div>
					</div>
					<?php
					foreach ( call_user_func( $fields_fn ) as $key => $def ) {
						ldkp_field( 'ep[' . $key . ']', $def[0], $def[1], $item ? get_post_meta( $item->ID, '_ldkp_' . $key, true ) : '', isset( $def[2] ) ? $def[2] : '' );
					}
					?>
				</div>
			</section>
		</div>
		<div class="prop-submit">
			<a class="btn btn--ghost" href="<?php echo esc_url( lk_panel_url( 'propostas-servicos', 0, array( 'aba' => $aba ) ) ); ?>">Cancelar</a>
			<button type="submit" class="btn btn--primary btn--lg">Salvar <?php echo esc_html( strtolower( $label ) ); ?></button>
		</div>
	</form>

<?php else : ?>
	<?php
	list( $pt, $label, $plural ) = $types[ $aba ];
	$items = get_posts( array( 'post_type' => $pt, 'numberposts' => -1, 'orderby' => 'menu_order title', 'order' => 'ASC' ) );
	?>
	<div class="prop-libhead">
		<p class="muted"><?php echo 'servico' === $aba ? 'Os serviços que você marca ao montar a proposta. Cada um vira um plano (preço, itens e condição).' : 'Cada nicho tem os textos prontos da proposta (capa, cenário, oportunidade, diagnóstico e encerramento).'; ?></p>
		<a class="btn btn--primary" href="<?php echo esc_url( lk_panel_url( 'propostas-servicos', 0, array( 'aba' => $aba, 'novo' => 1 ) ) ); ?>"><?php echo lk_icon( 'mais', 16 ); // phpcs:ignore ?><span>Novo <?php echo esc_html( strtolower( $label ) ); ?></span></a>
	</div>
	<?php if ( ! $items ) : ?>
		<section class="card"><p class="muted">Nenhum <?php echo esc_html( strtolower( $label ) ); ?> ainda.</p></section>
	<?php else : ?>
		<div class="table prop-table prop-table--lib">
			<div class="table-row table-head"><span><?php echo esc_html( $label ); ?></span><span><?php echo 'servico' === $aba ? 'Preço' : 'Título do diagnóstico'; ?></span><span>Usado em</span><span></span></div>
			<?php foreach ( $items as $it ) : ?>
				<?php
				if ( 'servico' === $aba ) {
					$info = trim( get_post_meta( $it->ID, '_ldkp_prefixo', true ) . ' ' . get_post_meta( $it->ID, '_ldkp_preco_por', true ) . ' ' . get_post_meta( $it->ID, '_ldkp_pagamento', true ) );
					$used = count( get_posts( array( 'post_type' => 'ldkp_proposta', 'post_status' => array( 'publish', 'draft' ), 'numberposts' => -1, 'fields' => 'ids', 'meta_query' => array( array( 'key' => '_ldkp_servicos', 'value' => 'i:' . $it->ID . ';', 'compare' => 'LIKE' ) ) ) ) ); // phpcs:ignore WordPress.DB.SlowDBQuery
				} else {
					$info = get_post_meta( $it->ID, '_ldkp_diag_titulo', true );
					$used = count( get_posts( array( 'post_type' => 'ldkp_proposta', 'post_status' => array( 'publish', 'draft' ), 'numberposts' => -1, 'fields' => 'ids', 'meta_key' => '_ldkp_nicho_id', 'meta_value' => $it->ID ) ) ); // phpcs:ignore WordPress.DB.SlowDBQuery
				}
				$edit_url = lk_panel_url( 'propostas-servicos', 0, array( 'aba' => $aba, 'editar' => $it->ID ) );
				?>
				<div class="table-row">
					<a class="cell-main" href="<?php echo esc_url( $edit_url ); ?>"><strong><?php echo esc_html( $it->post_title ); ?></strong></a>
					<span><?php echo esc_html( $info ? $info : '—' ); ?></span>
					<span><?php echo (int) $used; ?> proposta(s)</span>
					<span class="prop-acts">
						<a class="btn btn--ghost btn--sm" href="<?php echo esc_url( $edit_url ); ?>">Editar</a>
						<?php lk_action_button( 'prop_lib_delete', array( 'id' => $it->ID, 'tipo' => $aba ), 'Excluir', 'btn btn--danger btn--sm', 'Excluir "' . $it->post_title . '"? As propostas já geradas não mudam.' ); ?>
					</span>
				</div>
			<?php endforeach; ?>
		</div>
	<?php endif; ?>
<?php endif; ?>
<?php
lk_panel_end();

<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
$members = lk_team_users();
$areas   = lk_areas();
$actions = '<button type="button" class="btn btn--primary" data-open="membro-0">' . lk_icon( 'mais', 16 ) . '<span>Adicionar pessoa</span></button>';
lk_panel_start( 'Equipe', 'equipe', $actions );
echo lk_team_requests_html(); // phpcs:ignore WordPress.Security.EscapeOutput

$modal = function ( $u = null ) use ( $areas ) {
	$uid   = $u ? (int) $u->ID : 0;
	$perms = $u ? (array) get_user_meta( $uid, 'lk_perms', true ) : array( 'conteudo', 'tarefas' );
	$roles = lk_team_roles();
	$opts  = array( '' => 'Escolha a função…' );
	$map   = array();
	foreach ( $roles as $k => $r ) {
		$opts[ $k ] = $r[0];
		$map[ $k ]  = array_values( array_intersect( $r[1], array_keys( $areas ) ) );
	}
	lk_modal_start( 'membro-' . $uid, $u ? 'Editar ' . $u->display_name : 'Adicionar pessoa' );
	lk_form( 'team_save', 'stack' );
	echo '<input type="hidden" name="user_id" value="' . (int) $uid . '">';
	echo '<div class="grid-2">';
	lk_input( 'name', 'Nome', $u ? $u->display_name : '', 'text', 'required' );
	if ( $u ) {
		echo '<label class="field"><span>E-mail (login)</span><input type="text" value="' . esc_attr( $u->user_email ) . '" disabled></label>';
	} else {
		lk_input( 'email', 'E-mail (login)', '', 'email', 'required' );
	}
	echo '</div>';
	lk_input( 'senha', $u ? 'Nova senha (deixe em branco para manter)' : 'Senha', '', 'text', ( $u ? '' : 'required ' ) . 'minlength="8" autocomplete="off"' );
	lk_select( 'func', 'Função', $opts, $u ? lk_user_role_key( $uid ) : '', 'data-func-preset="' . esc_attr( wp_json_encode( $map ) ) . '"' );
	echo '<p class="muted small">A função já marca o que a pessoa vê. Dá para ajustar abaixo.</p>';
	echo '<fieldset class="perms"><legend>O que esta pessoa pode ver</legend>';
	foreach ( $areas as $key => $label ) {
		echo '<label class="check"><input type="checkbox" name="perms[]" value="' . esc_attr( $key ) . '"' . checked( in_array( $key, $perms, true ), true, false ) . '><span>' . esc_html( $label ) . '</span></label>';
	}
	echo '</fieldset>';
	lk_check( 'only_own', 'Ver só as tarefas atribuídas a ela', $u ? get_user_meta( $uid, 'lk_only_own', true ) : false );
	echo '<div class="form-actions"><button type="button" class="btn btn--ghost" data-close>Cancelar</button><button type="submit" class="btn btn--primary">Salvar</button></div></form>';
	lk_modal_end();
};
?>

<p class="muted small hint">Crie o acesso de cada pessoa e escolha a função (designer, social media, atendimento, tráfego, financeiro): as permissões já vêm marcadas. Designers abrem o conteúdo já filtrado no que está com eles.</p>

<div class="table">
	<div class="table-row table-head"><span>Pessoa</span><span>Função</span><span>Permissões</span><span></span></div>
	<?php foreach ( $members as $u ) : ?>
		<?php
		$is_admin = lk_is_admin( $u->ID );
		$perms    = (array) get_user_meta( $u->ID, 'lk_perms', true );
		?>
		<div class="table-row"<?php echo $is_admin ? '' : ' data-open="membro-' . (int) $u->ID . '" role="button" tabindex="0"'; ?>>
			<span class="cell-main"><span class="avatar"><?php echo esc_html( lk_initials( $u->display_name ) ); ?></span><span><strong><?php echo esc_html( $u->display_name ); ?></strong><small><?php echo esc_html( $u->user_email ); ?></small></span></span>
			<span data-label="Função"><?php echo $is_admin ? '<span class="pill pill--ok">Administração</span>' : '<span class="pill">' . esc_html( lk_user_role_label( $u->ID ) ? lk_user_role_label( $u->ID ) : 'Equipe' ) . '</span>'; ?></span>
			<span data-label="Permissões" class="small"><?php echo $is_admin ? 'Tudo' : esc_html( implode( ', ', array_intersect_key( $areas, array_flip( $perms ) ) ) ); ?><?php echo ! $is_admin && get_user_meta( $u->ID, 'lk_only_own', true ) ? ' · <em>só as tarefas dela</em>' : ''; ?></span>
			<span class="row-btns"><?php if ( ! $is_admin ) { lk_action_button( 'team_delete', array( 'user_id' => $u->ID ), lk_icon( 'lixo', 15 ), 'icon-btn', 'Remover o acesso desta pessoa? As tarefas dela continuam.' ); } ?></span>
		</div>
	<?php endforeach; ?>
</div>

<?php
$modal();
foreach ( $members as $u ) {
	if ( ! lk_is_admin( $u->ID ) ) {
		$modal( $u );
	}
}
?>
<script>
document.addEventListener('change', function (e) {
	var s = e.target.closest('[data-func-preset]');
	if (!s || !s.value) return;
	var map = JSON.parse(s.getAttribute('data-func-preset')), on = map[s.value] || [];
	s.closest('form').querySelectorAll('input[name="perms[]"]').forEach(function (c) { c.checked = on.indexOf(c.value) > -1; });
});
</script>
<?php
lk_panel_end();

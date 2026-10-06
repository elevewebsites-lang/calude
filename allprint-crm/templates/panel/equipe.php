<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
$members = ap_team_users();
$areas   = ap_areas();
$actions = '<button type="button" class="btn btn--primary" data-open="membro-0">' . ap_icon( 'mais', 16 ) . '<span>Adicionar pessoa</span></button>';
ap_panel_start( 'Equipe', 'equipe', $actions );

$modal = function ( $u = null ) use ( $areas ) {
	$uid   = $u ? (int) $u->ID : 0;
	$perms = $u ? (array) get_user_meta( $uid, 'ap_perms', true ) : array( 'projetos', 'tarefas' );
	ap_modal_start( 'membro-' . $uid, $u ? 'Editar ' . $u->display_name : 'Adicionar pessoa' );
	ap_form( 'team_save', 'stack' );
	echo '<input type="hidden" name="user_id" value="' . (int) $uid . '">';
	echo '<div class="grid-2">';
	ap_input( 'name', 'Nome', $u ? $u->display_name : '', 'text', 'required' );
	if ( $u ) {
		echo '<label class="field"><span>E-mail (login)</span><input type="text" value="' . esc_attr( $u->user_email ) . '" disabled></label>';
	} else {
		ap_input( 'email', 'E-mail (login)', '', 'email', 'required' );
	}
	echo '</div>';
	ap_input( 'senha', $u ? 'Nova senha (deixe em branco para manter)' : 'Senha', '', 'text', ( $u ? '' : 'required ' ) . 'minlength="8" autocomplete="off"' );
	echo '<fieldset class="perms"><legend>O que esta pessoa pode ver</legend>';
	foreach ( $areas as $key => $label ) {
		echo '<label class="check"><input type="checkbox" name="perms[]" value="' . esc_attr( $key ) . '"' . checked( in_array( $key, $perms, true ), true, false ) . '><span>' . esc_html( $label ) . '</span></label>';
	}
	echo '</fieldset>';
	ap_check( 'only_own', 'Ver só as tarefas atribuídas a ela', $u ? get_user_meta( $uid, 'ap_only_own', true ) : false );
	echo '<div class="form-actions"><button type="button" class="btn btn--ghost" data-close>Cancelar</button><button type="submit" class="btn btn--primary">Salvar</button></div></form>';
	ap_modal_end();
};
?>

<p class="muted small hint">Por enquanto é só você, mas quando entrar alguém, é aqui que você cria o acesso e escolhe o que a pessoa pode ver.</p>

<div class="table">
	<div class="table-row table-head"><span>Pessoa</span><span>Acesso</span><span>Permissões</span><span></span></div>
	<?php foreach ( $members as $u ) : ?>
		<?php
		$is_admin = ap_is_admin( $u->ID );
		$perms    = (array) get_user_meta( $u->ID, 'ap_perms', true );
		?>
		<div class="table-row"<?php echo $is_admin ? '' : ' data-open="membro-' . (int) $u->ID . '" role="button" tabindex="0"'; ?>>
			<span class="cell-main"><span class="avatar"><?php echo esc_html( ap_initials( $u->display_name ) ); ?></span><span><strong><?php echo esc_html( $u->display_name ); ?></strong><small><?php echo esc_html( $u->user_email ); ?></small></span></span>
			<span data-label="Acesso"><?php echo $is_admin ? '<span class="pill pill--ok">Administrador</span>' : '<span class="pill">Equipe</span>'; ?></span>
			<span data-label="Permissões" class="small"><?php echo $is_admin ? 'Tudo' : esc_html( implode( ', ', array_intersect_key( $areas, array_flip( $perms ) ) ) ); ?><?php echo ! $is_admin && get_user_meta( $u->ID, 'ap_only_own', true ) ? ' · <em>só as tarefas dela</em>' : ''; ?></span>
			<span class="row-btns"><?php if ( ! $is_admin ) { ap_action_button( 'team_delete', array( 'user_id' => $u->ID ), ap_icon( 'lixo', 15 ), 'icon-btn', 'Remover o acesso desta pessoa? As tarefas dela continuam.' ); } ?></span>
		</div>
	<?php endforeach; ?>
</div>

<?php
$modal();
foreach ( $members as $u ) {
	if ( ! ap_is_admin( $u->ID ) ) {
		$modal( $u );
	}
}
ap_panel_end();

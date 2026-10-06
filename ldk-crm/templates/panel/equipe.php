<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
$members = lk_team_users();
$groups  = lk_area_groups();
$areas   = lk_areas();
$roles   = lk_team_roles();
$actions = '<button type="button" class="btn btn--primary" data-open="membro-0">' . lk_icon( 'mais', 16 ) . '<span>Adicionar pessoa</span></button>';
lk_panel_start( 'Equipe e permissões', 'equipe', $actions );
echo lk_team_requests_html(); // phpcs:ignore WordPress.Security.EscapeOutput

// Pré-seleção de cada função (para o formulário marcar sozinho).
$map = array();
foreach ( $roles as $k => $r ) {
	$map[ $k ] = lk_role_areas( $k );
}

$modal = function ( $u = null ) use ( $groups, $roles, $map, $members ) {
	$uid   = $u ? (int) $u->ID : 0;
	$perms = $u ? (array) get_user_meta( $uid, 'lk_perms', true ) : lk_role_areas( 'outro' );
	$opts  = array( '' => 'Escolha o perfil…' );
	foreach ( $roles as $k => $r ) {
		$opts[ $k ] = $r[0];
	}
	// Cópia das permissões de outra pessoa.
	$copy = array( '' => 'Copiar de…' );
	$cmap = array();
	foreach ( $members as $m ) {
		if ( (int) $m->ID !== $uid && ! lk_is_admin( $m->ID ) ) {
			$copy[ $m->ID ] = $m->display_name;
			$cmap[ $m->ID ]  = array(
				'perms' => array_values( (array) get_user_meta( $m->ID, 'lk_perms', true ) ),
				'tasks' => (bool) get_user_meta( $m->ID, 'lk_only_own', true ),
				'cli'   => (bool) get_user_meta( $m->ID, 'lk_only_clients', true ),
				'meet'  => (bool) get_user_meta( $m->ID, 'lk_only_meet', true ),
			);
		}
	}
	lk_modal_start( 'membro-' . $uid, $u ? 'Editar ' . $u->display_name : 'Adicionar pessoa' );
	lk_form( 'team_save', 'stack' );
	echo '<div data-team-form data-presets="' . esc_attr( wp_json_encode( $map ) ) . '" data-copies="' . esc_attr( wp_json_encode( $cmap ) ) . '">';
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
	echo '<div class="grid-2">';
	lk_select( 'func', 'Perfil', $opts, $u ? lk_user_role_key( $uid ) : '', 'data-func-preset' );
	lk_select( 'copy_from', 'Ou copiar de outra pessoa', $copy, '', 'data-copy-from' );
	echo '</div>';
	echo '<p class="muted small" data-role-desc>O perfil marca as permissões certas. Dá para ajustar abaixo, pessoa por pessoa.</p>';
	echo '<div class="perm-groups">';
	foreach ( $groups as $gname => $items ) {
		echo '<fieldset class="perms"><legend>' . esc_html( $gname ) . ' <button type="button" class="btn btn--link btn--sm" data-perm-group>marcar todos</button></legend>';
		foreach ( $items as $key => $label ) {
			echo '<label class="check"><input type="checkbox" name="perms[]" value="' . esc_attr( $key ) . '"' . checked( in_array( $key, $perms, true ), true, false ) . '><span>' . esc_html( $label ) . '</span></label>';
		}
		echo '</fieldset>';
	}
	echo '</div>';
	echo '<fieldset class="perms"><legend>Limitar ao que é dela</legend>';
	lk_check( 'only_own', 'Ver só as tarefas atribuídas a ela', $u ? get_user_meta( $uid, 'lk_only_own', true ) : false );
	lk_check( 'only_clients', 'Ver só os clientes em que ela é a responsável (designer, social, atendimento, tráfego ou revisão)', $u ? get_user_meta( $uid, 'lk_only_clients', true ) : false );
	lk_check( 'only_meet', 'Ver só as reuniões que ela marcou', $u ? get_user_meta( $uid, 'lk_only_meet', true ) : false );
	echo '</fieldset>';
	echo '<p class="muted small">Sempre abertos para toda a equipe: chat da equipe, modo foco, ranking, apontamentos, tutorial e a própria conta. Equipe, Configurações, Saúde, Metas e Gamificação são só do administrador.</p>';
	echo '<div class="form-actions"><button type="button" class="btn btn--ghost" data-close>Cancelar</button><button type="submit" class="btn btn--primary">Salvar</button></div>';
	echo '</div></form>';
	lk_modal_end();
};
?>

<section class="card">
	<div class="card-head"><h3>Perfis</h3><span class="muted small">cada perfil já vem com as permissões certas; clique numa pessoa para ajustar</span></div>
	<div class="table table--roles">
		<?php foreach ( $roles as $k => $r ) : ?>
			<div class="table-row">
				<span class="cell-main"><strong><?php echo esc_html( $r[0] ); ?></strong><small class="muted"><?php echo esc_html( $r[2] ?? '' ); ?></small></span>
				<span class="small"><?php echo esc_html( implode( ', ', array_intersect_key( $areas, array_flip( lk_role_areas( $k ) ) ) ) ); ?></span>
			</div>
		<?php endforeach; ?>
	</div>
</section>

<div class="table">
	<div class="table-row table-head"><span>Pessoa</span><span>Perfil</span><span>Pode ver</span><span></span></div>
	<?php foreach ( $members as $u ) : ?>
		<?php
		$is_admin = lk_is_admin( $u->ID );
		$perms    = (array) get_user_meta( $u->ID, 'lk_perms', true );
		$limits   = array();
		if ( ! $is_admin ) {
			if ( get_user_meta( $u->ID, 'lk_only_own', true ) ) {
				$limits[] = 'só as tarefas dela';
			}
			if ( get_user_meta( $u->ID, 'lk_only_clients', true ) ) {
				$limits[] = 'só os clientes dela';
			}
			if ( get_user_meta( $u->ID, 'lk_only_meet', true ) ) {
				$limits[] = 'só as reuniões dela';
			}
		}
		?>
		<div class="table-row"<?php echo $is_admin ? '' : ' data-open="membro-' . (int) $u->ID . '" role="button" tabindex="0"'; ?>>
			<span class="cell-main"><?php echo lk_avatar_circle( $u ); // phpcs:ignore ?><span><strong><?php echo esc_html( $u->display_name ); ?></strong><?php echo lk_user_online( $u->ID ) ? ' <em class="online-dot" title="Online agora"></em>' : ''; ?><small class="muted"><?php echo esc_html( $u->user_email ); ?></small></span></span>
			<span data-label="Perfil"><?php echo $is_admin ? '<span class="pill pill--ok">Administração</span>' : '<span class="pill">' . esc_html( lk_user_role_label( $u->ID ) ? lk_user_role_label( $u->ID ) : 'Equipe' ) . '</span>'; ?></span>
			<span data-label="Pode ver" class="small"><?php echo $is_admin ? 'Tudo' : esc_html( implode( ', ', array_intersect_key( $areas, array_flip( $perms ) ) ) ); ?><?php echo $limits ? '<br><em class="text-late">Limitado: ' . esc_html( implode( ' · ', $limits ) ) . '</em>' : ''; ?></span>
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
<style>
.perm-groups{display:grid;grid-template-columns:repeat(auto-fit,minmax(240px,1fr));gap:12px}
.perms{border:1px solid var(--line,#ddd);border-radius:12px;padding:10px 12px}
.perms legend{font-weight:600;font-size:.85rem;padding:0 6px}
.perms{display:block}
.perms .check{display:flex!important;width:100%;gap:8px;align-items:flex-start;margin:6px 0}
.table--roles .table-row{grid-template-columns:minmax(0,1fr) minmax(0,2fr)}
</style>
<script>
(function () {
	var roles = <?php echo wp_json_encode( array_map( function ( $r ) { return $r[2] ?? ''; }, $roles ) ); ?>;
	function boxes(f) { return f.querySelectorAll('input[name="perms[]"]'); }
	document.addEventListener('change', function (e) {
		var f = e.target.closest('[data-team-form]'); if (!f) return;
		var presets = JSON.parse(f.getAttribute('data-presets') || '{}'), copies = JSON.parse(f.getAttribute('data-copies') || '{}');
		if (e.target.matches('[data-func-preset]') && e.target.value) {
			var on = presets[e.target.value] || [];
			boxes(f).forEach(function (c) { c.checked = on.indexOf(c.value) > -1; });
			var d = f.querySelector('[data-role-desc]'); if (d) d.textContent = roles[e.target.value] || '';
		}
		if (e.target.matches('[data-copy-from]') && e.target.value) {
			var c = copies[e.target.value]; if (!c) return;
			boxes(f).forEach(function (b) { b.checked = c.perms.indexOf(b.value) > -1; });
			var set = function (n, v) { var el = f.querySelector('[name="' + n + '"]'); if (el) el.checked = !!v; };
			set('only_own', c.tasks); set('only_clients', c.cli); set('only_meet', c.meet);
		}
	});
	document.addEventListener('click', function (e) {
		var b = e.target.closest('[data-perm-group]'); if (!b) return;
		var fs = b.closest('fieldset'), cs = fs.querySelectorAll('input[type=checkbox]'), all = Array.prototype.every.call(cs, function (c) { return c.checked; });
		cs.forEach(function (c) { c.checked = !all; });
	});
})();
</script>
<?php
lk_panel_end();

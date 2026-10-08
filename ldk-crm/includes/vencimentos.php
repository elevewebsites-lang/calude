<?php
/**
 * Vencimentos do cliente: hospedagem, domínio, certificado SSL, e-mail profissional, suporte…
 * Cada item tem data de vencimento, fornecedor, valor e ciclo. O item "Outro" aceita qualquer nome,
 * então dá para cadastrar o que mais a agência renova pelo cliente. A equipe é avisada 30, 15, 7 e 1 dia antes e no dia.
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function lk_renewal_kinds() {
	return array(
		'hospedagem' => 'Hospedagem',
		'dominio'    => 'Domínio',
		'ssl'        => 'Certificado SSL',
		'email'      => 'E-mail profissional',
		'suporte'    => 'Suporte / manutenção',
		'licenca'    => 'Licença de plugin ou software',
		'outro'      => 'Outro (digite o nome)',
	);
}

function lk_renewal_cycles() {
	return array(
		'mensal'     => 'Mensal',
		'trimestral' => 'Trimestral',
		'semestral'  => 'Semestral',
		'anual'      => 'Anual',
		'bienal'     => 'A cada 2 anos',
		'unico'      => 'Único (não renova)',
	);
}

function lk_renewal_name( $r ) {
	$kinds = lk_renewal_kinds();
	if ( 'outro' === $r->kind || ! isset( $kinds[ $r->kind ] ) ) {
		return $r->label ? $r->label : 'Outro';
	}
	return $kinds[ $r->kind ] . ( $r->label ? ' · ' . $r->label : '' );
}

/** Dias até vencer (negativo = vencido). */
function lk_renewal_days( $r ) {
	if ( ! $r->due_date ) {
		return null;
	}
	return (int) floor( ( strtotime( $r->due_date ) - strtotime( lk_today() ) ) / DAY_IN_SECONDS );
}

function lk_renewal_badge( $r ) {
	$d = lk_renewal_days( $r );
	if ( null === $d ) {
		return '<em class="badge badge--off">sem data</em>';
	}
	if ( $d < 0 ) {
		return '<em class="badge badge--late">venceu há ' . (int) abs( $d ) . ' d</em>';
	}
	if ( 0 === $d ) {
		return '<em class="badge badge--late">vence hoje</em>';
	}
	if ( $d <= 30 ) {
		return '<em class="badge badge--warn">vence em ' . (int) $d . ' d</em>';
	}
	return '<em class="badge badge--ok">em ' . (int) $d . ' dias</em>';
}

/** Próxima data depois de renovar, conforme o ciclo. */
function lk_renewal_next( $date, $cycle ) {
	$step = array( 'mensal' => '+1 month', 'trimestral' => '+3 months', 'semestral' => '+6 months', 'anual' => '+1 year', 'bienal' => '+2 years' );
	if ( ! isset( $step[ $cycle ] ) ) {
		return $date;
	}
	$base = strtotime( $date ) < strtotime( lk_today() ) ? lk_today() : $date;
	return gmdate( 'Y-m-d', strtotime( $base . ' ' . $step[ $cycle ] ) );
}

/* -----------------------------------------------------------------------
 * Ações
 * -------------------------------------------------------------------- */

function lk_do_renewal_save() {
	lk_require( 'clientes' );
	$client = lk_get( 'clients', lk_in( 'client_id', 'int' ) );
	if ( ! $client ) {
		lk_back( 'Cliente não encontrado.', 'erro' );
	}
	$kinds = lk_renewal_kinds();
	$kind  = isset( $kinds[ lk_in( 'kind' ) ] ) ? lk_in( 'kind' ) : 'outro';
	$label = lk_in( 'label' );
	if ( 'outro' === $kind && '' === $label ) {
		lk_back( 'Dê um nome ao item (ex.: Backup, Google Workspace).', 'erro' );
	}
	$cycles = lk_renewal_cycles();
	$data   = array(
		'client_id' => $client->id,
		'kind'      => $kind,
		'label'     => $label,
		'provider'  => lk_in( 'provider' ),
		'due_date'  => lk_in( 'due_date', 'date' ) ? lk_in( 'due_date', 'date' ) : null,
		'value'     => lk_in( 'value', 'money' ),
		'cycle'     => isset( $cycles[ lk_in( 'cycle' ) ] ) ? lk_in( 'cycle' ) : 'anual',
		'notes'     => lk_in( 'notes', 'textarea' ),
		'notified_days' => 999, // data nova: os avisos recomeçam
	);
	$id  = lk_in( 'id', 'int' );
	$old = $id ? lk_get( 'renewals', $id ) : null;
	if ( $old && (int) $old->client_id === (int) $client->id ) {
		lk_update( 'renewals', $old->id, $data );
	} else {
		lk_insert( 'renewals', $data );
	}
	lk_back( 'Vencimento salvo.', 'ok', lk_panel_url( 'cliente', $client->id ) . '#vencimentos' );
}

function lk_do_renewal_delete() {
	lk_require( 'clientes' );
	$r = lk_get( 'renewals', lk_in( 'id', 'int' ) );
	if ( $r ) {
		lk_delete( 'renewals', $r->id );
	}
	lk_back( 'Vencimento removido.' );
}

/** Marca como renovado: empurra a data pelo ciclo e zera os avisos. */
function lk_do_renewal_renew() {
	lk_require( 'clientes' );
	$r = lk_get( 'renewals', lk_in( 'id', 'int' ) );
	if ( ! $r || ! $r->due_date ) {
		lk_back( 'Defina a data de vencimento primeiro.', 'erro' );
	}
	if ( 'unico' === $r->cycle ) {
		lk_back( 'Este item é único e não renova. Edite a data se precisar.', 'erro' );
	}
	lk_update( 'renewals', $r->id, array( 'due_date' => lk_renewal_next( $r->due_date, $r->cycle ), 'notified_days' => 999 ) );
	lk_back( 'Renovado. Nova data: ' . lk_date( lk_renewal_next( $r->due_date, $r->cycle ) ) . '.' );
}

/* -----------------------------------------------------------------------
 * Avisos (uma checagem por dia)
 * -------------------------------------------------------------------- */

add_action( 'lk_daily', 'lk_renewals_alert', 30 );
add_action( 'lk_daily_catchup', 'lk_renewals_alert', 30 );
function lk_renewals_alert() {
	static $ran = false;
	if ( $ran ) {
		return;
	}
	$ran    = true;
	$stages = array( 30, 15, 7, 1, 0 ); // dias antes
	foreach ( lk_rows( 'renewals', 'due_date IS NOT NULL AND due_date <= %s', array( gmdate( 'Y-m-d', strtotime( lk_today() . ' +30 days' ) ) ) ) as $r ) {
		$d = lk_renewal_days( $r );
		if ( null === $d || $d < 0 ) {
			continue;
		}
		// Menor etapa já alcançada que ainda não foi avisada.
		$stage = null;
		foreach ( $stages as $s ) {
			if ( $d <= $s ) {
				$stage = $s;
			}
		}
		if ( null === $stage || $stage >= (int) $r->notified_days ) {
			continue;
		}
		lk_update( 'renewals', $r->id, array( 'notified_days' => $stage ) );
		$client = lk_get( 'clients', $r->client_id );
		if ( ! $client ) {
			continue;
		}
		$text = '🔔 ' . lk_renewal_name( $r ) . ' de ' . lk_client_label( $client ) . ( 0 === $d ? ' vence hoje.' : ' vence em ' . $d . ( 1 === $d ? ' dia' : ' dias' ) . ' (' . lk_date( $r->due_date ) . ').' );
		foreach ( get_users( array( 'role' => 'administrator', 'fields' => 'ID' ) ) as $admin ) {
			lk_notify( (int) $admin, $text, lk_panel_url( 'cliente', $client->id ) . '#vencimentos' );
		}
		if ( (int) $client->atendimento_id ) {
			lk_notify( (int) $client->atendimento_id, $text, lk_panel_url( 'cliente', $client->id ) . '#vencimentos' );
		}
	}
}

/* -----------------------------------------------------------------------
 * Bloco na ficha do cliente
 * -------------------------------------------------------------------- */

function lk_renewal_form( $client, $r = null ) {
	lk_form( 'renewal_save', 'stack' );
	?>
	<input type="hidden" name="client_id" value="<?php echo (int) $client->id; ?>">
	<input type="hidden" name="id" value="<?php echo (int) ( $r ? $r->id : 0 ); ?>">
	<div class="grid-2">
		<?php lk_select( 'kind', 'O que vence', lk_renewal_kinds(), $r ? $r->kind : 'hospedagem' ); ?>
		<?php lk_input( 'label', 'Nome / detalhe (obrigatório em "Outro")', $r ? $r->label : '', 'text', 'placeholder="Ex.: meusite.com.br"' ); ?>
		<?php lk_input( 'provider', 'Fornecedor', $r ? $r->provider : '', 'text', 'placeholder="Ex.: Registro.br, Hostinger"' ); ?>
		<?php lk_input( 'due_date', 'Vence em', $r && $r->due_date ? $r->due_date : '', 'date', 'required' ); ?>
		<?php lk_input( 'value', 'Valor (R$)', $r && (float) $r->value ? number_format( (float) $r->value, 2, ',', '.' ) : '', 'text', 'inputmode="decimal" data-money' ); ?>
		<?php lk_select( 'cycle', 'Renova', lk_renewal_cycles(), $r ? $r->cycle : 'anual' ); ?>
	</div>
	<?php lk_input( 'notes', 'Observações (login guardado, quem paga…)', $r ? (string) $r->notes : '', 'textarea', 'rows="2"' ); ?>
	<div class="form-actions"><button type="button" class="btn btn--ghost" data-close>Cancelar</button><button type="submit" class="btn btn--primary">Salvar</button></div>
	</form>
	<?php
}

function lk_client_renewals_html( $client ) {
	$rows = lk_rows( 'renewals', 'client_id = %d', array( $client->id ), 'due_date IS NULL, due_date ASC' );
	ob_start();
	?>
	<section class="card" id="vencimentos">
		<div class="card-head"><h3>Hospedagem, domínio e vencimentos</h3><button type="button" class="btn btn--primary btn--sm" data-open="novo-vencimento-<?php echo (int) $client->id; ?>">+ Adicionar</button></div>
		<?php if ( ! $rows ) : ?>
			<p class="muted small">Cadastre a hospedagem, o domínio e o que mais vence para este cliente. A equipe é avisada 30, 15, 7 e 1 dia antes.</p>
		<?php else : ?>
			<?php foreach ( $rows as $r ) : ?>
				<div class="pay-row">
					<span><strong><?php echo esc_html( lk_renewal_name( $r ) ); ?></strong><small><?php echo esc_html( trim( ( $r->provider ? $r->provider . ' · ' : '' ) . ( $r->due_date ? 'vence ' . lk_date( $r->due_date ) : 'sem data' ) . ( (float) $r->value > 0 ? ' · ' . lk_money( $r->value ) : '' ) . ' · ' . ( lk_renewal_cycles()[ $r->cycle ] ?? '' ) ) ); ?></small></span>
					<?php echo lk_renewal_badge( $r ); // phpcs:ignore WordPress.Security.EscapeOutput ?>
					<?php if ( 'unico' !== $r->cycle ) { lk_action_button( 'renewal_renew', array( 'id' => $r->id ), 'Renovado', 'btn btn--ghost btn--sm', 'Marcar como renovado e empurrar a data?' ); } ?>
					<button type="button" class="btn btn--ghost btn--sm" data-open="vencimento-<?php echo (int) $r->id; ?>">Editar</button>
					<?php lk_action_button( 'renewal_delete', array( 'id' => $r->id ), 'Excluir', 'btn btn--link btn--sm', 'Remover este vencimento?' ); ?>
				</div>
			<?php endforeach; ?>
		<?php endif; ?>
	</section>
	<?php
	lk_modal_start( 'novo-vencimento-' . $client->id, 'Novo vencimento · ' . lk_client_label( $client ) );
	lk_renewal_form( $client );
	lk_modal_end();
	foreach ( $rows as $r ) {
		lk_modal_start( 'vencimento-' . $r->id, 'Editar · ' . lk_renewal_name( $r ) );
		lk_renewal_form( $client, $r );
		lk_modal_end();
	}
	return ob_get_clean();
}

/** Todos os vencimentos da agência, do mais próximo ao mais distante (usado no Dashboard e na lista). */
function lk_renewals_upcoming( $days = 60 ) {
	return lk_rows( 'renewals', 'due_date IS NOT NULL AND due_date <= %s', array( gmdate( 'Y-m-d', strtotime( lk_today() . ' +' . (int) $days . ' days' ) ) ), 'due_date ASC' );
}

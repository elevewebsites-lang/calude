<?php
/**
 * Tráfego pago: visão geral de todos os clientes (uma linha por cliente) e relatório bonito do cliente.
 * Com ?demo=1 mostra uma simulação com clientes fictícios.
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Clientes fictícios para ver como fica. */
function lk_ads_demo_rows() {
	$mk = function ( $name, $spend, $reach, $clicks, $results, $revenue, $camps, $wallet, $trend, $logo ) {
		return array(
			'id' => 0, 'name' => $name, 'logo' => $logo, 'spend' => $spend, 'reach' => $reach, 'clicks' => $clicks, 'impressions' => (int) ( $reach * 1.7 ),
			'results' => $results, 'revenue' => $revenue, 'campaigns' => $camps, 'wallet' => $wallet, 'trend' => $trend, 'error' => '',
		);
	};
	return array(
		$mk( 'Clínica Aurora Odontologia', 4280.50, 186400, 5120, 214, 0, array( array( 'name' => 'Implantes · Leads WhatsApp', 'spend' => 1980.0, 'clicks' => 2310, 'results' => 104, 'revenue' => 0 ), array( 'name' => 'Clareamento · Promoção Outubro', 'spend' => 1420.5, 'clicks' => 1880, 'results' => 82, 'revenue' => 0 ), array( 'name' => 'Institucional · Remarketing', 'spend' => 880.0, 'clicks' => 930, 'results' => 28, 'revenue' => 0 ) ), array( 'left' => 5200, 'days' => 36 ), array( 120, 128, 140, 132, 150, 158, 166, 160, 172, 181 ), 'AU' ),
		$mk( 'Studio Bella Estética', 2150.00, 98200, 3010, 96, 0, array( array( 'name' => 'Limpeza de pele · Leads', 'spend' => 1250.0, 'clicks' => 1700, 'results' => 61, 'revenue' => 0 ), array( 'name' => 'Pacote noivas', 'spend' => 900.0, 'clicks' => 1310, 'results' => 35, 'revenue' => 0 ) ), array( 'left' => 410, 'days' => 5 ), array( 70, 72, 69, 74, 77, 71, 73, 76, 75, 78 ), 'SB' ),
		$mk( 'Construtora Vale Verde', 6480.00, 241900, 4390, 71, 0, array( array( 'name' => 'Lançamento Residencial Vale · Leads', 'spend' => 4200.0, 'clicks' => 2860, 'results' => 52, 'revenue' => 0 ), array( 'name' => 'Visita ao decorado', 'spend' => 2280.0, 'clicks' => 1530, 'results' => 19, 'revenue' => 0 ) ), array( 'left' => 9800, 'days' => 45 ), array( 200, 215, 210, 224, 231, 240, 236, 248, 255, 262 ), 'VV' ),
		$mk( 'Pizzaria Nonna Rosa', 1320.80, 74600, 2840, 388, 18420.0, array( array( 'name' => 'Delivery · Pedidos', 'spend' => 960.8, 'clicks' => 2210, 'results' => 301, 'revenue' => 14220.0 ), array( 'name' => 'Combo família · Sexta', 'spend' => 360.0, 'clicks' => 630, 'results' => 87, 'revenue' => 4200.0 ) ), array( 'left' => 1900, 'days' => 43 ), array( 30, 34, 38, 36, 41, 44, 47, 45, 49, 52 ), 'NR' ),
		$mk( 'Auto Center Triunfo', 980.00, 41200, 1210, 0, 0, array( array( 'name' => 'Revisão completa', 'spend' => 0.0, 'clicks' => 0, 'results' => 0, 'revenue' => 0 ) ), array( 'left' => 220, 'days' => 0 ), array( 40, 38, 35, 20, 8, 0, 0, 0, 0, 0 ), 'AT' ),
		$mk( 'Moda Lume Boutique', 3560.00, 133800, 4670, 142, 12980.0, array( array( 'name' => 'Coleção Primavera · Catálogo', 'spend' => 2240.0, 'clicks' => 3120, 'results' => 98, 'revenue' => 9100.0 ), array( 'name' => 'Remarketing carrinho', 'spend' => 1320.0, 'clicks' => 1550, 'results' => 44, 'revenue' => 3880.0 ) ), array( 'left' => 2600, 'days' => 22 ), array( 110, 118, 121, 119, 126, 131, 128, 135, 138, 142 ), 'ML' ),
	);
}

/** Linhas reais: um item por cliente com conta de anúncios. Cache de 3 horas por cliente e período. */
function lk_ads_overview_rows( $from, $to ) {
	$rows = array();
	foreach ( lk_clients() as $c ) {
		if ( ! $c->meta_ad_account ) {
			continue;
		}
		$key = 'lk_adsov_' . $c->id . '_' . $from . '_' . $to;
		$a   = get_transient( $key );
		if ( false === $a ) {
			$a = lk_ads_period( $c->id, $from, $to );
			set_transient( $key, $a, 3 * HOUR_IN_SECONDS );
		}
		$m    = $a['meta'] ?? null;
		$days = max( 1, (int) ( ( strtotime( $to ) - strtotime( $from ) ) / DAY_IN_SECONDS ) + 1 );
		$w    = $m && ! empty( $m['wallet'] ) ? $m['wallet'] : null;
		$left = $w ? ( null !== $w['left_cap'] ? (float) $w['left_cap'] : null ) : null;
		$rows[] = array(
			'id' => (int) $c->id, 'name' => lk_client_label( $c ), 'logo' => lk_initials( lk_client_label( $c ) ), 'spend' => (float) ( $m['spend'] ?? 0 ), 'reach' => (int) ( $m['reach'] ?? 0 ),
			'clicks' => (int) ( $m['clicks'] ?? 0 ), 'impressions' => (int) ( $m['impressions'] ?? 0 ), 'results' => (float) ( $m['results'] ?? 0 ), 'revenue' => (float) ( $m['revenue'] ?? 0 ),
			'campaigns' => (array) ( $m['campaigns'] ?? array() ), 'wallet' => null !== $left ? array( 'left' => $left, 'days' => ( $m['spend'] ?? 0 ) > 0 ? (int) floor( $left / ( $m['spend'] / $days ) ) : 0 ) : null,
			'trend' => array(), 'error' => $m['error'] ?? '', 'client' => $c,
		);
	}
	return $rows;
}

function lk_ads_calc( $r ) {
	$r['ctr']  = $r['impressions'] ? $r['clicks'] / $r['impressions'] * 100 : 0;
	$r['cpc']  = $r['clicks'] ? $r['spend'] / $r['clicks'] : 0;
	$r['cpr']  = $r['results'] ? $r['spend'] / $r['results'] : 0;
	$r['roas'] = $r['spend'] && $r['revenue'] ? $r['revenue'] / $r['spend'] : 0;
	$flags     = array();
	if ( $r['error'] ) {
		$flags[] = array( 'late', 'Erro na conta' );
	} elseif ( $r['spend'] <= 0 || ( $r['trend'] && 0 === (int) end( $r['trend'] ) ) ) {
		$flags[] = array( 'late', 'Parado' );
	}
	if ( ! empty( $r['wallet'] ) && null !== $r['wallet']['left'] && $r['wallet']['days'] > 0 && $r['wallet']['days'] <= 7 ) {
		$flags[] = array( 'warn', 'Saldo acaba em ~' . (int) $r['wallet']['days'] . ' d' );
	}
	if ( $r['revenue'] > 0 && $r['roas'] < 1 ) {
		$flags[] = array( 'warn', 'Retorno abaixo de 1×' );
	}
	$r['flags'] = $flags;
	return $r;
}

function lk_ads_spark( $pts, $w = 90, $h = 26 ) {
	if ( count( $pts ) < 2 ) {
		return '<span class="muted small">—</span>';
	}
	$mx = max( $pts );
	$mn = min( $pts );
	$d  = array();
	foreach ( $pts as $i => $v ) {
		$x   = round( $i * ( $w / ( count( $pts ) - 1 ) ), 1 );
		$y   = round( $h - 3 - ( $mx > $mn ? ( $v - $mn ) / ( $mx - $mn ) * ( $h - 6 ) : ( $h / 2 ) ), 1 );
		$d[] = $x . ',' . $y;
	}
	$up = end( $pts ) >= reset( $pts );
	return '<svg class="ads-spark ' . ( $up ? 'is-up' : 'is-down' ) . '" viewBox="0 0 ' . $w . ' ' . $h . '" width="' . $w . '" height="' . $h . '" aria-hidden="true"><polyline points="' . esc_attr( implode( ' ', $d ) ) . '" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>';
}

function lk_ads_n( $n ) {
	return number_format( (float) $n, 0, ',', '.' );
}

/** Visão geral: totais da agência + tabela com uma linha por cliente. */
function lk_ads_overview_html( $rows, $demo = false ) {
	$rows = array_map( 'lk_ads_calc', $rows );
	$tot  = array( 'spend' => 0, 'results' => 0, 'revenue' => 0, 'clicks' => 0, 'alerts' => 0 );
	foreach ( $rows as $r ) {
		$tot['spend']   += $r['spend'];
		$tot['results'] += $r['results'];
		$tot['revenue'] += $r['revenue'];
		$tot['clicks']  += $r['clicks'];
		$tot['alerts']  += $r['flags'] ? 1 : 0;
	}
	ob_start();
	?>
	<?php if ( $demo ) : ?><div class="flash flash--warn ads-demo"><strong>Simulação:</strong> clientes e números fictícios, só para você ver como fica. <a href="<?php echo esc_url( lk_panel_url( 'trafego' ) ); ?>">Voltar aos dados reais</a></div><?php endif; ?>
	<section class="stats stats--4 ads-tot">
		<div class="stat"><span class="stat-label">Investido (todos os clientes)</span><strong class="money"><?php echo esc_html( lk_money( $tot['spend'] ) ); ?></strong><small><?php echo (int) count( $rows ); ?> clientes com anúncios</small></div>
		<div class="stat"><span class="stat-label">Resultados (leads / vendas)</span><strong><?php echo esc_html( lk_ads_n( $tot['results'] ) ); ?></strong><small>custo médio <?php echo esc_html( $tot['results'] ? lk_money( $tot['spend'] / $tot['results'] ) : '—' ); ?></small></div>
		<div class="stat"><span class="stat-label">Receita gerada</span><strong class="money"><?php echo esc_html( lk_money( $tot['revenue'] ) ); ?></strong><small>retorno <?php echo esc_html( $tot['spend'] && $tot['revenue'] ? number_format( $tot['revenue'] / $tot['spend'], 1, ',', '' ) . '×' : '—' ); ?></small></div>
		<div class="stat <?php echo $tot['alerts'] ? 'stat--alert' : ''; ?>"><span class="stat-label">Precisam de atenção</span><strong class="<?php echo $tot['alerts'] ? 'text-late' : ''; ?>"><?php echo (int) $tot['alerts']; ?></strong><small>saldo baixo, parado ou retorno fraco</small></div>
	</section>
	<section class="card ads-ov">
		<div class="card-head"><h3>Todos os clientes</h3><span class="muted small">clique no cabeçalho para ordenar</span></div>
		<div class="pros-wrap"><table class="pros-table ads-table" data-sort-table>
			<thead><tr><th data-s="t">Cliente</th><th data-s="n">Investido</th><th data-s="n">Alcance</th><th data-s="n">Cliques</th><th data-s="n">CTR</th><th data-s="n">CPC</th><th data-s="n">Resultados</th><th data-s="n">Custo / resultado</th><th data-s="n">Retorno</th><th>Tendência</th><th data-s="n">Saldo</th><th>Situação</th></tr></thead>
			<tbody>
			<?php foreach ( $rows as $r ) : $link = $r['id'] ? lk_panel_url( 'cliente', $r['id'] ) . '#redes' : '#'; ?>
				<tr>
					<td data-v="<?php echo esc_attr( $r['name'] ); ?>"><a class="net-cli" href="<?php echo esc_url( $link ); ?>"><span class="avatar avatar--sm"><?php echo esc_html( $r['logo'] ); ?></span><strong><?php echo esc_html( $r['name'] ); ?></strong></a></td>
					<td data-v="<?php echo esc_attr( $r['spend'] ); ?>" class="money"><?php echo esc_html( lk_money( $r['spend'] ) ); ?></td>
					<td data-v="<?php echo esc_attr( $r['reach'] ); ?>"><?php echo esc_html( lk_ads_n( $r['reach'] ) ); ?></td>
					<td data-v="<?php echo esc_attr( $r['clicks'] ); ?>"><?php echo esc_html( lk_ads_n( $r['clicks'] ) ); ?></td>
					<td data-v="<?php echo esc_attr( $r['ctr'] ); ?>"><?php echo esc_html( number_format( $r['ctr'], 2, ',', '' ) ); ?>%</td>
					<td data-v="<?php echo esc_attr( $r['cpc'] ); ?>" class="money"><?php echo esc_html( lk_money( $r['cpc'] ) ); ?></td>
					<td data-v="<?php echo esc_attr( $r['results'] ); ?>"><strong><?php echo esc_html( lk_ads_n( $r['results'] ) ); ?></strong></td>
					<td data-v="<?php echo esc_attr( $r['cpr'] ); ?>" class="money"><?php echo esc_html( $r['results'] ? lk_money( $r['cpr'] ) : '—' ); ?></td>
					<td data-v="<?php echo esc_attr( $r['roas'] ); ?>"><?php echo $r['roas'] ? '<strong class="' . ( $r['roas'] >= 1 ? 'text-ok' : 'text-late' ) . '">' . esc_html( number_format( $r['roas'], 1, ',', '' ) ) . '×</strong>' : '<span class="muted">—</span>'; // phpcs:ignore ?></td>
					<td><?php echo lk_ads_spark( $r['trend'] ); // phpcs:ignore ?></td>
					<td data-v="<?php echo esc_attr( $r['wallet']['days'] ?? 9999 ); ?>"><?php echo $r['wallet'] ? '<span class="money">' . esc_html( lk_money( $r['wallet']['left'] ) ) . '</span><small class="muted"> ~' . (int) $r['wallet']['days'] . ' dias</small>' : '<span class="muted">—</span>'; // phpcs:ignore ?></td>
					<td><?php if ( ! $r['flags'] ) : ?><em class="badge badge--ok">Tudo certo</em><?php else : foreach ( $r['flags'] as $f ) : ?><em class="badge badge--<?php echo esc_attr( $f[0] ); ?>"><?php echo esc_html( $f[1] ); ?></em> <?php endforeach; endif; ?></td>
				</tr>
			<?php endforeach; ?>
			<?php if ( ! $rows ) : ?><tr><td colspan="12" class="muted">Nenhum cliente com conta de anúncios ainda. Cadastre o ID da conta na ficha do cliente.</td></tr><?php endif; ?>
			</tbody>
		</table></div>
	</section>
	<script>document.querySelectorAll('[data-sort-table] thead th[data-s]').forEach(function(th){th.style.cursor='pointer';th.addEventListener('click',function(){var t=th.closest('table'),i=[].indexOf.call(th.parentNode.children,th),n=th.dataset.s==='n',dir=th.dataset.dir==='a'?-1:1;th.dataset.dir=dir===1?'a':'d';var rows=[].slice.call(t.tBodies[0].rows);rows.sort(function(a,b){var x=a.cells[i].dataset.v,y=b.cells[i].dataset.v;return n?(parseFloat(x)-parseFloat(y))*dir:String(x).localeCompare(String(y))*dir;});rows.forEach(function(r){t.tBodies[0].appendChild(r);});});});</script>
	<?php
	return ob_get_clean();
}

/** Relatório do cliente (como ele veria): números grandes, leitura simples e campanhas. */
function lk_ads_client_report_html( $r, $period_label ) {
	$r   = lk_ads_calc( $r );
	$max = 0;
	foreach ( $r['campaigns'] as $c ) {
		$max = max( $max, (float) $c['spend'] );
	}
	$max   = $max ? $max : 1;
	$daily = $r['trend'] ? $r['trend'] : array();
	ob_start();
	?>
	<section class="ads-rep">
		<header class="ads-rep-h"><div><span class="eyebrow">Relatório de tráfego pago</span><h2><?php echo esc_html( $r['name'] ); ?></h2><p class="muted"><?php echo esc_html( $period_label ); ?> · Instagram e Facebook</p></div><span class="avatar avatar--lg"><?php echo esc_html( $r['logo'] ); ?></span></header>
		<div class="ads-kpis">
			<div class="ads-kpi"><span>Investido</span><strong class="money"><?php echo esc_html( lk_money( $r['spend'] ) ); ?></strong><small>no período</small></div>
			<div class="ads-kpi ads-kpi--hl"><span><?php echo $r['revenue'] ? 'Vendas geradas' : 'Contatos gerados'; ?></span><strong><?php echo esc_html( lk_ads_n( $r['results'] ) ); ?></strong><small><?php echo $r['revenue'] ? esc_html( lk_money( $r['revenue'] ) . ' em vendas' ) : 'mensagens e formulários'; ?></small></div>
			<div class="ads-kpi"><span>Custo por <?php echo $r['revenue'] ? 'venda' : 'contato'; ?></span><strong class="money"><?php echo esc_html( $r['results'] ? lk_money( $r['cpr'] ) : '—' ); ?></strong><small>quanto custou cada um</small></div>
			<div class="ads-kpi"><span>Pessoas alcançadas</span><strong><?php echo esc_html( lk_ads_n( $r['reach'] ) ); ?></strong><small><?php echo esc_html( lk_ads_n( $r['clicks'] ) ); ?> cliques no anúncio</small></div>
		</div>
		<?php if ( $r['roas'] ) : ?><p class="ads-say">💡 Para cada <strong>R$ 1</strong> investido, voltaram <strong>R$ <?php echo esc_html( number_format( $r['roas'], 2, ',', '' ) ); ?></strong> em vendas.</p>
		<?php elseif ( $r['results'] ) : ?><p class="ads-say">💡 Cada contato novo custou em média <strong><?php echo esc_html( lk_money( $r['cpr'] ) ); ?></strong>. Os anúncios apareceram para <strong><?php echo esc_html( lk_ads_n( $r['reach'] ) ); ?></strong> pessoas diferentes.</p><?php endif; ?>
		<?php if ( count( $daily ) > 1 ) : ?>
			<div class="ads-chart"><div class="ads-chart-h"><strong>Resultados ao longo do mês</strong><span class="muted small">por semana</span></div><?php echo lk_ads_spark( $daily, 600, 110 ); // phpcs:ignore ?><div class="ads-chart-w"><?php foreach ( $daily as $i => $v ) : ?><span>S<?php echo (int) ( $i + 1 ); ?></span><?php endforeach; ?></div></div>
		<?php endif; ?>
		<h3 class="ads-h3">Campanhas</h3>
		<div class="ads-camps">
			<?php foreach ( $r['campaigns'] as $c ) : $cpr = $c['results'] ? $c['spend'] / $c['results'] : 0; ?>
				<div class="ads-camp"><div class="ads-camp-n"><strong><?php echo esc_html( $c['name'] ); ?></strong><small class="muted"><?php echo esc_html( lk_ads_n( $c['clicks'] ) ); ?> cliques · <?php echo esc_html( lk_ads_n( $c['results'] ) ); ?> <?php echo $r['revenue'] ? 'vendas' : 'contatos'; ?><?php echo $cpr ? ' · ' . esc_html( lk_money( $cpr ) ) . ' cada' : ''; ?></small></div><div class="ads-camp-b"><i><b style="width:<?php echo (int) round( 100 * $c['spend'] / $max ); ?>%"></b></i><span class="money"><?php echo esc_html( lk_money( $c['spend'] ) ); ?></span></div></div>
			<?php endforeach; ?>
		</div>
		<p class="muted small ads-foot">Dados do Gerenciador de Anúncios da Meta. Resultados = mensagens iniciadas, formulários e compras registradas.</p>
	</section>
	<?php
	return ob_get_clean();
}

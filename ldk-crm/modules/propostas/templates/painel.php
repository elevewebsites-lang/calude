<?php
/**
 * Página /gerar-proposta.
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// phpcs:disable WordPress.Security.NonceVerification.Recommended -- só leitura de parâmetros de navegação.
$editing = isset( $_GET['editar'] ) ? absint( $_GET['editar'] ) : 0;
$ready   = isset( $_GET['pronta'] ) ? absint( $_GET['pronta'] ) : 0;
$error   = isset( $_GET['erro'] ) ? sanitize_key( $_GET['erro'] ) : '';
// phpcs:enable

$post = null;
if ( $editing && 'ldkp_proposta' === get_post_type( $editing ) && current_user_can( 'edit_post', $editing ) ) {
	$post = get_post( $editing );
} else {
	$editing = 0;
	$post    = (object) array(
		'ID'          => 0,
		'post_status' => 'auto-draft',
	);
}

if ( $ready && ( 'ldkp_proposta' !== get_post_type( $ready ) || ! current_user_can( 'edit_post', $ready ) ) ) {
	$ready = 0;
}

$logo   = ldkp_setting( 'logo' );
$recent = get_posts(
	array(
		'post_type'   => 'ldkp_proposta',
		'post_status' => array( 'publish', 'draft' ),
		'numberposts' => 30,
	)
);
?><!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="robots" content="noindex, nofollow">
<title><?php echo $editing ? 'Editar proposta' : 'Gerar proposta'; ?> | LDK</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Archivo:wght@400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="<?php echo esc_url( LDKP_URL . 'assets/admin.css?ver=' . LDKP_VERSION ); ?>">
<link rel="stylesheet" href="<?php echo esc_url( LDKP_URL . 'assets/painel.css?ver=' . LDKP_VERSION ); ?>">
</head>
<body id="topo">

<header class="pn-bar">
	<div class="pn-bar-inner">
		<a class="pn-brand" href="<?php echo esc_url( ldkp_painel_url() ); ?>">
			<?php if ( $logo ) : ?><img src="<?php echo esc_url( $logo ); ?>" alt="LDK Marketing Digital"><?php endif; ?>
			<span>Propostas</span>
		</a>
		<nav class="pn-nav">
			<a href="<?php echo esc_url( ldkp_painel_url() ); ?>" class="<?php echo $editing ? '' : 'is-active'; ?>">Nova proposta</a>
			<a href="#lista">Minhas propostas</a>
			<a href="<?php echo esc_url( admin_url( 'edit.php?post_type=ldkp_proposta&page=ldkp-settings' ) ); ?>">Configurações</a>
		</nav>
	</div>
</header>

<main class="pn-main">

	<?php if ( $ready ) : ?>
		<?php
		$url   = get_permalink( $ready );
		$short = get_post_meta( $ready, '_ldkp_cliente_curto', true );
		$short = $short ? $short : get_the_title( $ready );
		$wa    = 'https://wa.me/?text=' . rawurlencode( 'Olá, ' . $short . '! Preparei a sua proposta comercial: ' . $url );
		?>
		<section class="pn-ready">
			<span class="pn-ready-tag">Proposta pronta</span>
			<h2><?php echo esc_html( get_the_title( $ready ) ); ?></h2>
			<div class="pn-ready-link">
				<input type="text" readonly value="<?php echo esc_attr( $url ); ?>" onclick="this.select()">
				<button type="button" class="pn-btn pn-btn--accent ldkp-copy" data-url="<?php echo esc_attr( $url ); ?>">Copiar link</button>
			</div>
			<div class="pn-ready-actions">
				<a class="pn-btn pn-btn--light" href="<?php echo esc_url( $url ); ?>" target="_blank" rel="noopener">Abrir proposta</a>
				<a class="pn-btn pn-btn--wa" href="<?php echo esc_url( $wa ); ?>" target="_blank" rel="noopener">Enviar pelo WhatsApp</a>
				<a class="pn-btn pn-btn--ghost" href="<?php echo esc_url( ldkp_painel_url( array( 'editar' => $ready ) ) ); ?>">Editar</a>
			</div>
		</section>
	<?php endif; ?>

	<?php if ( 'cliente' === $error ) : ?>
		<div class="notice notice-error"><p>Preencha o nome do cliente para gerar a proposta.</p></div>
	<?php endif; ?>

	<div class="pn-head">
		<h1><?php echo $editing ? 'Editando: ' . esc_html( get_the_title( $editing ) ) : 'Nova proposta'; ?></h1>
		<p><?php echo $editing ? 'O link continua o mesmo: o cliente vê as mudanças assim que você salvar.' : 'Preencha o cliente, escolha o nicho e os serviços, clique em "Preencher textos automaticamente", revise e gere o link.'; ?></p>
	</div>

	<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="pn-form">
		<input type="hidden" name="action" value="ldkp_painel_save">
		<input type="hidden" name="ldkp_post_id" value="<?php echo (int) $editing; ?>">
		<div id="ldkp_builder"<?php echo $editing ? ' data-editing="1"' : ''; ?>>
			<?php ldkp_render_builder( $post ); ?>
		</div>
		<div class="pn-submit">
			<div class="pn-submit-inner">
				<?php if ( $editing ) : ?>
					<a class="pn-btn pn-btn--ghost" href="<?php echo esc_url( ldkp_painel_url() ); ?>">Cancelar</a>
				<?php endif; ?>
				<button type="submit" class="pn-btn pn-btn--accent pn-btn--lg"><?php echo $editing ? 'Salvar alterações' : 'Gerar proposta'; ?></button>
			</div>
		</div>
	</form>

	<section class="pn-list" id="lista">
		<h2>Minhas propostas</h2>
		<?php if ( ! $recent ) : ?>
			<p class="pn-empty">Nenhuma proposta ainda. A primeira aparece aqui assim que você gerar.</p>
		<?php else : ?>
			<div class="pn-table">
				<div class="pn-row pn-row--head"><span>Cliente</span><span>Nicho</span><span>Criada</span><span>Visualizações</span><span></span></div>
				<?php foreach ( $recent as $p ) : ?>
					<?php
					$niche = (int) get_post_meta( $p->ID, '_ldkp_nicho_id', true );
					$views = (int) get_post_meta( $p->ID, '_ldkp_views', true );
					$last  = get_post_meta( $p->ID, '_ldkp_last_view', true );
					$live  = 'publish' === $p->post_status;
					?>
					<div class="pn-row">
						<span class="pn-client"><strong><?php echo esc_html( $p->post_title ? $p->post_title : '(sem nome)' ); ?></strong><?php echo $live ? '' : ' <em>rascunho</em>'; ?></span>
						<span data-label="Nicho"><?php echo $niche ? esc_html( get_the_title( $niche ) ) : '—'; ?></span>
						<span data-label="Criada"><?php echo esc_html( get_the_date( 'd/m/Y', $p ) ); ?></span>
						<span data-label="Visualizações"><strong class="pn-views<?php echo $views ? ' is-seen' : ''; ?>"><?php echo (int) $views; ?></strong><?php echo $last ? '<small>última ' . esc_html( mysql2date( 'd/m H:i', $last ) ) . '</small>' : ''; ?></span>
						<span class="pn-actions">
							<?php if ( $live ) : ?>
								<button type="button" class="pn-btn pn-btn--sm pn-btn--accent ldkp-copy" data-url="<?php echo esc_attr( get_permalink( $p ) ); ?>">Copiar link</button>
								<a class="pn-btn pn-btn--sm pn-btn--ghost" href="<?php echo esc_url( get_permalink( $p ) ); ?>" target="_blank" rel="noopener">Abrir</a>
							<?php endif; ?>
							<a class="pn-btn pn-btn--sm pn-btn--ghost" href="<?php echo esc_url( ldkp_painel_url( array( 'editar' => $p->ID ) ) ); ?>">Editar</a>
						</span>
					</div>
				<?php endforeach; ?>
			</div>
		<?php endif; ?>
	</section>

</main>

<script>window.LDKP_DATA = <?php echo wp_json_encode( ldkp_admin_data() ); ?>;</script>
<script src="<?php echo esc_url( LDKP_URL . 'assets/admin.js?ver=' . LDKP_VERSION ); ?>"></script>
</body>
</html>

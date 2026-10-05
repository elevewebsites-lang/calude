<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_action( 'init', 'ldkp_register_post_types' );
function ldkp_register_post_types() {
	register_post_type(
		'ldkp_proposta',
		array(
			'labels'              => array(
				'name'               => 'Propostas',
				'singular_name'      => 'Proposta',
				'add_new'            => 'Nova proposta',
				'add_new_item'       => 'Nova proposta',
				'edit_item'          => 'Editar proposta',
				'view_item'          => 'Ver proposta',
				'all_items'          => 'Todas as propostas',
				'search_items'       => 'Buscar propostas',
				'not_found'          => 'Nenhuma proposta ainda.',
				'not_found_in_trash' => 'Nenhuma proposta na lixeira.',
				'menu_name'          => 'Propostas',
			),
			'public'              => true,
			'publicly_queryable'  => true,
			'exclude_from_search' => true,
			'show_in_nav_menus'   => false,
			'show_in_rest'        => false,
			'has_archive'         => false,
			'menu_icon'           => 'dashicons-media-document',
			'menu_position'       => 25,
			'supports'            => array( 'title' ),
			'rewrite'             => array(
				'slug'       => 'proposta',
				'with_front' => false,
			),
		)
	);

	$internal = array(
		'public'            => false,
		'show_ui'           => true,
		'show_in_menu'      => 'edit.php?post_type=ldkp_proposta',
		'show_in_rest'      => false,
		'supports'          => array( 'title', 'page-attributes' ),
		'hierarchical'      => false,
	);

	register_post_type(
		'ldkp_nicho',
		array_merge(
			$internal,
			array(
				'labels' => array(
					'name'          => 'Nichos',
					'singular_name' => 'Nicho',
					'add_new'       => 'Novo nicho',
					'add_new_item'  => 'Novo nicho',
					'edit_item'     => 'Editar nicho',
					'all_items'     => 'Nichos',
				),
			)
		)
	);

	register_post_type(
		'ldkp_servico',
		array_merge(
			$internal,
			array(
				'labels' => array(
					'name'          => 'Serviços',
					'singular_name' => 'Serviço',
					'add_new'       => 'Novo serviço',
					'add_new_item'  => 'Novo serviço',
					'edit_item'     => 'Editar serviço',
					'all_items'     => 'Serviços',
				),
			)
		)
	);
}

// Propostas nunca entram nos sitemaps.
add_filter(
	'wp_sitemaps_post_types',
	function ( $types ) {
		unset( $types['ldkp_proposta'] );
		return $types;
	}
);
add_filter(
	'wpseo_sitemap_exclude_post_type',
	function ( $exclude, $type ) {
		return 'ldkp_proposta' === $type ? true : $exclude;
	},
	10,
	2
);
add_filter(
	'rank_math/sitemap/exclude_post_type',
	function ( $exclude, $type ) {
		return 'ldkp_proposta' === $type ? true : $exclude;
	},
	10,
	2
);

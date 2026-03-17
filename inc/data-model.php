<?php
/**
 * Core content model and query behavior.
 */

if (!defined('ABSPATH')) {
	exit;
}

const WJ_ITEM_META = [
	'item_identifier'   => 'string',
	'item_year'         => 'integer',
	'item_date_display' => 'string',
	'item_condition'    => 'string',
	'item_materials'    => 'string',
	'item_dimensions'   => 'string',
	'item_rights'       => 'string',
	'item_source'       => 'string',
	'item_inscription'  => 'string',
	'item_event_link'   => 'string',
	'item_gallery_ids'  => 'string',
	'item_dropbox_path' => 'string',
];

const WJ_ITEM_META_UI = [
	'item_identifier'   => [
		'label'       => 'Identifier',
		'description' => 'Local identifier or source-system ID.',
	],
	'item_year'         => [
		'label'       => 'Year',
		'description' => 'Primary sortable year for the item.',
	],
	'item_date_display' => [
		'label'       => 'Display Date',
		'description' => 'Human-readable date such as 1994 or 1994-03-08.',
	],
	'item_condition'    => [
		'label'       => 'Condition',
		'description' => 'Brief physical condition note.',
	],
	'item_materials'    => [
		'label'       => 'Materials',
		'description' => 'Materials or fabrication details.',
	],
	'item_dimensions'   => [
		'label'       => 'Dimensions',
		'description' => 'Size or dimensions, for example L or 8 x 10 in.',
	],
	'item_rights'       => [
		'label'       => 'Rights',
		'description' => 'Rights, credit, or reuse note.',
	],
	'item_source'       => [
		'label'       => 'Source',
		'description' => 'Where the metadata or object came from.',
	],
	'item_inscription'  => [
		'label'       => 'Inscription / Notes',
		'description' => 'Free-text notes, inscriptions, or distinguishing marks.',
	],
	'item_event_link'   => [
		'label'       => 'Event Link',
		'description' => 'External link for the specific concert or event, for example a Setlist.fm page.',
	],
	'item_gallery_ids'  => [
		'label'       => 'Additional Images',
		'description' => 'Comma-separated attachment IDs for alternate views such as front/back, detail shots, or reverse side.',
	],
	'item_dropbox_path' => [
		'label'       => 'Dropbox Path',
		'description' => 'Original Dropbox path for ingest tracking.',
	],
];

function wj_register_content_model(): void {
	register_post_type(
		'collection_item',
		[
			'labels' => [
				'name'               => __('Collection Items', 'twentytwentyfive-child'),
				'singular_name'      => __('Collection Item', 'twentytwentyfive-child'),
				'add_new_item'       => __('Add New Collection Item', 'twentytwentyfive-child'),
				'edit_item'          => __('Edit Collection Item', 'twentytwentyfive-child'),
				'new_item'           => __('New Collection Item', 'twentytwentyfive-child'),
				'view_item'          => __('View Collection Item', 'twentytwentyfive-child'),
				'search_items'       => __('Search Collection Items', 'twentytwentyfive-child'),
				'not_found'          => __('No collection items found', 'twentytwentyfive-child'),
				'not_found_in_trash' => __('No collection items found in Trash', 'twentytwentyfive-child'),
			],
			'public'       => true,
			'has_archive'  => 'items',
			'menu_icon'    => 'dashicons-archive',
			'show_in_rest' => true,
			'rewrite'      => ['slug' => 'item'],
			'supports'     => ['title', 'editor', 'excerpt', 'thumbnail', 'custom-fields', 'revisions'],
		]
	);

	$taxonomy_args = [
		'public'            => true,
		'show_admin_column' => true,
		'show_in_rest'      => true,
	];

	register_taxonomy(
		'collection',
		['collection_item'],
		array_merge(
			$taxonomy_args,
			[
				'labels'       => [
					'name'          => __('Collections', 'twentytwentyfive-child'),
					'singular_name' => __('Collection', 'twentytwentyfive-child'),
				],
				'hierarchical' => true,
				'rewrite'      => ['slug' => 'collections', 'hierarchical' => true],
			]
		)
	);

	$flat_taxonomies = [
		'artist'   => __('Artists', 'twentytwentyfive-child'),
		'venue'    => __('Venues', 'twentytwentyfive-child'),
		'location' => __('Locations', 'twentytwentyfive-child'),
		'item_tag' => __('Subjects', 'twentytwentyfive-child'),
	];

	foreach ($flat_taxonomies as $slug => $label) {
		register_taxonomy(
			$slug,
			['collection_item'],
			array_merge(
				$taxonomy_args,
				[
					'labels'       => [
						'name'          => $label,
						'singular_name' => rtrim($label, 's'),
					],
					'hierarchical' => false,
					'rewrite'      => ['slug' => $slug],
				]
			)
		);
	}

	foreach (WJ_ITEM_META as $meta_key => $type) {
		register_post_meta(
			'collection_item',
			$meta_key,
			[
				'type'              => $type,
				'single'            => true,
				'show_in_rest'      => true,
				'sanitize_callback' => static fn($value) => wj_sanitize_meta_input($meta_key, $value, $type),
				'auth_callback'     => static fn() => current_user_can('edit_posts'),
			]
		);
	}
}
add_action('init', 'wj_register_content_model');

function wj_seed_collection_terms(): void {
	$terms = [
		't-shirts'                => 'T-Shirts',
		'ticket-stubs-and-flyers' => 'Ticket Stubs & Flyers',
	];

	foreach ($terms as $slug => $name) {
		if (!term_exists($slug, 'collection')) {
			wp_insert_term($name, 'collection', ['slug' => $slug]);
		}
	}
}
add_action('init', 'wj_seed_collection_terms', 20);

function wj_get_filter_values(): array {
	$item_year = 0;
	if (isset($_GET['item_year'])) {
		$item_year = absint($_GET['item_year']);
	} elseif (isset($_GET['year'])) {
		$item_year = absint($_GET['year']);
	}

	return [
		'search'     => isset($_GET['s']) ? sanitize_text_field(wp_unslash($_GET['s'])) : '',
		'artist'     => isset($_GET['artist']) ? sanitize_title(wp_unslash($_GET['artist'])) : '',
		'venue'      => isset($_GET['venue']) ? sanitize_title(wp_unslash($_GET['venue'])) : '',
		'location'   => isset($_GET['location']) ? sanitize_title(wp_unslash($_GET['location'])) : '',
		'item_tag'   => isset($_GET['item_tag']) ? sanitize_title(wp_unslash($_GET['item_tag'])) : '',
		'year'       => $item_year,
		'collection' => isset($_GET['collection']) ? sanitize_title(wp_unslash($_GET['collection'])) : '',
		'sort'       => isset($_GET['sort']) ? sanitize_key(wp_unslash($_GET['sort'])) : 'year_desc',
	];
}

function wj_is_collection_query(WP_Query $query): bool {
	if (is_admin() || !$query->is_main_query()) {
		return false;
	}

	if ($query->is_post_type_archive('collection_item')) {
		return true;
	}

	return $query->is_tax(['collection', 'artist', 'venue', 'location', 'item_tag']);
}

function wj_filter_collection_queries(WP_Query $query): void {
	if (!wj_is_collection_query($query)) {
		return;
	}

	$filters = wj_get_filter_values();
	$query->set('post_type', 'collection_item');
	$query->set('posts_per_page', 24);

	if ($filters['search']) {
		$query->set('s', $filters['search']);
	}

	$meta_query = [];
	if ($filters['year']) {
		$meta_query[] = [
			'key'   => 'item_year',
			'value' => $filters['year'],
			'type'  => 'NUMERIC',
		];
	}
	if ($meta_query) {
		$query->set('meta_query', $meta_query);
	}

	$tax_query = $query->get('tax_query');
	$tax_query = is_array($tax_query) ? $tax_query : [];

	foreach (['artist', 'venue', 'location', 'item_tag', 'collection'] as $taxonomy) {
		if (!$filters[$taxonomy]) {
			continue;
		}

		if ($query->is_tax($taxonomy) && $query->get_queried_object() instanceof WP_Term) {
			continue;
		}

		$tax_query[] = [
			'taxonomy' => $taxonomy,
			'field'    => 'slug',
			'terms'    => [$filters[$taxonomy]],
		];
	}

	if ($tax_query) {
		$query->set('tax_query', $tax_query);
	}

	switch ($filters['sort']) {
		case 'title_asc':
			$query->set('orderby', 'title');
			$query->set('order', 'ASC');
			break;
		case 'title_desc':
			$query->set('orderby', 'title');
			$query->set('order', 'DESC');
			break;
		case 'year_asc':
			$query->set('meta_key', 'item_year');
			$query->set('orderby', 'meta_value_num');
			$query->set('order', 'ASC');
			break;
		case 'recent':
			$query->set('orderby', 'date');
			$query->set('order', 'DESC');
			break;
		default:
			$query->set('meta_key', 'item_year');
			$query->set('orderby', 'meta_value_num');
			$query->set('order', 'DESC');
	}
}
add_action('pre_get_posts', 'wj_filter_collection_queries');

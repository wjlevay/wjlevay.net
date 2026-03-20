<?php
/**
 * Front-end rendering helpers and shortcodes.
 */

if (!defined('ABSPATH')) {
	exit;
}

function wj_register_shortcodes(): void {
	add_shortcode('collections_index', 'wj_render_collections_index');
	add_shortcode('item_browser', 'wj_render_item_browser');
	add_shortcode('item_card_meta', 'wj_render_item_card_meta');
	add_shortcode('item_meta', 'wj_render_item_meta');
	add_shortcode('item_viewer', 'wj_render_item_viewer');
	add_shortcode('term_intro', 'wj_render_term_intro');
}
add_action('init', 'wj_register_shortcodes');

function wj_get_collections_page_url(): string {
	$page = get_page_by_path('collections');
	if ($page instanceof WP_Post) {
		$url = get_permalink($page);
		if ($url) {
			return $url;
		}
	}

	return home_url('/collections/');
}

function wj_render_breadcrumbs(array $crumbs): string {
	$parts = [];

	foreach ($crumbs as $crumb) {
		$label = trim((string) ($crumb['label'] ?? ''));
		if ('' === $label) {
			continue;
		}

		$url = trim((string) ($crumb['url'] ?? ''));
		if ('' !== $url) {
			$parts[] = sprintf('<a href="%s">%s</a>', esc_url($url), esc_html($label));
			continue;
		}

		$parts[] = sprintf('<span aria-current="page">%s</span>', esc_html($label));
	}

	if (!$parts) {
		return '';
	}

	return '<nav class="wj-breadcrumbs" aria-label="' . esc_attr__('Breadcrumb', 'twentytwentyfive-child') . '">' . implode('<span class="wj-breadcrumbs__sep" aria-hidden="true">/</span>', $parts) . '</nav>';
}

function wj_get_single_item_breadcrumbs(int $post_id): string {
	$crumbs = [
		[
			'label' => __('All Collections', 'twentytwentyfive-child'),
			'url'   => wj_get_collections_page_url(),
		],
	];

	$collections = get_the_terms($post_id, 'collection');
	if ($collections && !is_wp_error($collections)) {
		$collection = $collections[0];
		$link = get_term_link($collection);
		$crumbs[] = [
			'label' => $collection->name,
			'url'   => is_wp_error($link) ? '' : $link,
		];
	}

	return wj_render_breadcrumbs($crumbs);
}

function wj_get_taxonomy_breadcrumbs(WP_Term $term): string {
	$crumbs = [];

	if ('collection' === $term->taxonomy) {
		$crumbs[] = [
			'label' => __('All Collections', 'twentytwentyfive-child'),
			'url'   => wj_get_collections_page_url(),
		];
		$crumbs[] = [
			'label' => $term->name,
			'url'   => '',
		];

		return wj_render_breadcrumbs($crumbs);
	}

	$active_collection = get_query_var('collection');
	if (is_string($active_collection) && '' !== $active_collection) {
		$collection = get_term_by('slug', sanitize_title($active_collection), 'collection');
		if ($collection instanceof WP_Term) {
			$link = get_term_link($collection);
			$crumbs[] = [
				'label' => __('All Collections', 'twentytwentyfive-child'),
				'url'   => wj_get_collections_page_url(),
			];
			$crumbs[] = [
				'label' => $collection->name,
				'url'   => is_wp_error($link) ? '' : $link,
			];
			$crumbs[] = [
				'label' => $term->name,
				'url'   => '',
			];

			return wj_render_breadcrumbs($crumbs);
		}
	}

	$archive_link = get_post_type_archive_link('collection_item');
	$crumbs[] = [
		'label' => __('Items', 'twentytwentyfive-child'),
		'url'   => $archive_link ?: '',
	];
	$crumbs[] = [
		'label' => $term->name,
		'url'   => '',
	];

	return wj_render_breadcrumbs($crumbs);
}

function wj_render_compact_pagination(): string {
	global $wp_query;

	if (!$wp_query instanceof WP_Query) {
		return '';
	}

	$total_pages = (int) $wp_query->max_num_pages;
	if ($total_pages < 2) {
		return '';
	}

	$current_page = max(1, (int) get_query_var('paged'));
	$base_url = html_entity_decode(get_pagenum_link(1));
	$query_args = $_GET;
	unset($query_args['paged']);

	$page_items = [];

	if ($total_pages <= 7) {
		for ($i = 1; $i <= $total_pages; $i++) {
			$page_items[] = $i;
		}
	} else {
		$page_items[] = 1;

		if ($current_page <= 4) {
			$page_items = array_merge($page_items, [2, 3, 4, 5, 'ellipsis', $total_pages]);
		} elseif ($current_page >= $total_pages - 3) {
			$page_items = array_merge(
				$page_items,
				[
					'ellipsis',
					$total_pages - 4,
					$total_pages - 3,
					$total_pages - 2,
					$total_pages - 1,
					$total_pages,
				]
			);
		} else {
			$page_items = array_merge(
				$page_items,
				[
					'ellipsis',
					$current_page - 1,
					$current_page,
					$current_page + 1,
					'ellipsis',
					$total_pages,
				]
			);
		}
	}

	$page_items = array_values(array_unique($page_items, SORT_REGULAR));

	$build_page_url = static function (int $page_number) use ($base_url, $query_args): string {
		$url = $page_number > 1 ? get_pagenum_link($page_number) : $base_url;
		if ($query_args) {
			$url = add_query_arg($query_args, $url);
		}

		return $url;
	};

	ob_start();
	?>
	<div class="nav-links">
		<?php if ($current_page > 1) : ?>
			<a class="prev page-numbers" href="<?php echo esc_url($build_page_url($current_page - 1)); ?>"><?php esc_html_e('Previous', 'twentytwentyfive-child'); ?></a>
		<?php endif; ?>

		<?php foreach ($page_items as $item) : ?>
			<?php if ('ellipsis' === $item) : ?>
				<span class="page-numbers dots">&hellip;</span>
			<?php elseif ((int) $item === $current_page) : ?>
				<span aria-current="page" class="page-numbers current"><?php echo esc_html((string) $item); ?></span>
			<?php else : ?>
				<a class="page-numbers" href="<?php echo esc_url($build_page_url((int) $item)); ?>"><?php echo esc_html((string) $item); ?></a>
			<?php endif; ?>
		<?php endforeach; ?>

		<?php if ($current_page < $total_pages) : ?>
			<a class="next page-numbers" href="<?php echo esc_url($build_page_url($current_page + 1)); ?>"><?php esc_html_e('Next', 'twentytwentyfive-child'); ?></a>
		<?php endif; ?>
	</div>
	<?php

	return (string) ob_get_clean();
}

function wj_get_current_browse_url(): string {
	$request_uri = isset($_SERVER['REQUEST_URI']) ? wp_unslash((string) $_SERVER['REQUEST_URI']) : '';
	if ('' === $request_uri) {
		return '';
	}

	return home_url($request_uri);
}

function wj_get_current_browse_filters(): array {
	$filters = wj_get_filter_values();

	if (is_tax()) {
		$term = get_queried_object();
		if ($term instanceof WP_Term && array_key_exists($term->taxonomy, $filters) && '' === (string) $filters[$term->taxonomy]) {
			$filters[$term->taxonomy] = $term->slug;
		}
	}

	return $filters;
}

function wj_get_browse_link_args(array $filters, string $browse_url = ''): array {
	$args = [];
	$map = [
		'search'     => 'browse_s',
		'agent'      => 'browse_agent',
		'production' => 'browse_production',
		'collection' => 'browse_collection',
		'venue'      => 'browse_venue',
		'location'   => 'browse_location',
		'item_tag'   => 'browse_item_tag',
		'year'       => 'browse_item_year',
		'sort'       => 'browse_sort',
	];

	foreach ($map as $key => $query_key) {
		$value = $filters[$key] ?? '';
		$is_empty_numeric = is_numeric($value) && 0 === (int) $value;
		if ('' === (string) $value || $is_empty_numeric) {
			continue;
		}

		if ('sort' === $key && 'date_desc' === $value) {
			continue;
		}

		$args[$query_key] = $value;
	}

	if ($browse_url) {
		$args['browse_url'] = rawurlencode($browse_url);
	}

	return $args;
}

function wj_get_item_permalink_with_context(int $post_id, array $filters = [], string $browse_url = ''): string {
	$url = get_permalink($post_id);
	if (!$url) {
		return '';
	}

	$link_args = wj_get_browse_link_args($filters, $browse_url);
	return $link_args ? add_query_arg($link_args, $url) : $url;
}

function wj_get_browse_context_from_request(): array {
	$context = [
		'browse_url' => '',
		'filters'    => [
			'search'     => '',
			'agent'      => '',
			'production' => '',
			'collection' => '',
			'venue'      => '',
			'location'   => '',
			'item_tag'   => '',
			'year'       => 0,
			'sort'       => 'date_desc',
		],
	];

	$raw_browse_url = isset($_GET['browse_url']) ? (string) wp_unslash($_GET['browse_url']) : '';
	if ($raw_browse_url) {
		$context['browse_url'] = esc_url_raw(rawurldecode($raw_browse_url));
	}

	$context['filters']['search'] = isset($_GET['browse_s']) ? sanitize_text_field(wp_unslash($_GET['browse_s'])) : '';
	$context['filters']['agent'] = isset($_GET['browse_agent']) ? sanitize_title(wp_unslash($_GET['browse_agent'])) : '';
	$context['filters']['production'] = isset($_GET['browse_production']) ? sanitize_title(wp_unslash($_GET['browse_production'])) : '';
	$context['filters']['collection'] = isset($_GET['browse_collection']) ? sanitize_title(wp_unslash($_GET['browse_collection'])) : '';
	$context['filters']['venue'] = isset($_GET['browse_venue']) ? sanitize_title(wp_unslash($_GET['browse_venue'])) : '';
	$context['filters']['location'] = isset($_GET['browse_location']) ? sanitize_title(wp_unslash($_GET['browse_location'])) : '';
	$context['filters']['item_tag'] = isset($_GET['browse_item_tag']) ? sanitize_title(wp_unslash($_GET['browse_item_tag'])) : '';
	$context['filters']['year'] = isset($_GET['browse_item_year']) ? absint($_GET['browse_item_year']) : 0;
	$context['filters']['sort'] = isset($_GET['browse_sort']) ? sanitize_key(wp_unslash($_GET['browse_sort'])) : 'date_desc';

	return $context;
}

function wj_get_context_item_ids(array $filters): array {
	$query_args = [
		'post_type'              => 'collection_item',
		'post_status'            => 'publish',
		'posts_per_page'         => -1,
		'fields'                 => 'ids',
		'ignore_sticky_posts'    => true,
		'no_found_rows'          => true,
		'update_post_meta_cache' => false,
		'update_post_term_cache' => false,
	];

	if (!empty($filters['search'])) {
		$query_args['s'] = $filters['search'];
	}

	$meta_query = [];
	if (!empty($filters['year'])) {
		$meta_query[] = [
			'key'   => 'item_year',
			'value' => (int) $filters['year'],
			'type'  => 'NUMERIC',
		];
	}
	if ($meta_query) {
		$query_args['meta_query'] = $meta_query;
	}

	$tax_query = [];
	foreach (['agent', 'production', 'venue', 'location', 'item_tag', 'collection'] as $taxonomy) {
		if (empty($filters[$taxonomy])) {
			continue;
		}

		$tax_query[] = [
			'taxonomy' => $taxonomy,
			'field'    => 'slug',
			'terms'    => [$filters[$taxonomy]],
		];
	}
	if ($tax_query) {
		$query_args['tax_query'] = $tax_query;
	}

	switch ($filters['sort'] ?? 'date_desc') {
		case 'title_asc':
			$query_args['orderby'] = 'title';
			$query_args['order'] = 'ASC';
			break;
		case 'title_desc':
			$query_args['orderby'] = 'title';
			$query_args['order'] = 'DESC';
			break;
		case 'date_asc':
			$query_args['meta_key'] = 'item_sort_date';
			$query_args['orderby'] = 'meta_value';
			$query_args['order'] = 'ASC';
			break;
		case 'recent':
			$query_args['orderby'] = 'date';
			$query_args['order'] = 'DESC';
			break;
		default:
			$query_args['meta_key'] = 'item_sort_date';
			$query_args['orderby'] = 'meta_value';
			$query_args['order'] = 'DESC';
			break;
	}

	$query = new WP_Query($query_args);
	return array_map('intval', $query->posts);
}

function wj_get_collection_fallback_ids(int $post_id): array {
	$collections = get_the_terms($post_id, 'collection');
	if (!$collections || is_wp_error($collections)) {
		return [];
	}

	$query = new WP_Query(
		[
			'post_type'              => 'collection_item',
			'post_status'            => 'publish',
			'posts_per_page'         => -1,
			'fields'                 => 'ids',
			'ignore_sticky_posts'    => true,
			'no_found_rows'          => true,
			'update_post_meta_cache' => false,
			'update_post_term_cache' => false,
			'meta_key'               => 'item_sort_date',
			'orderby'                => 'meta_value',
			'order'                  => 'DESC',
			'tax_query'              => [
				[
					'taxonomy' => 'collection',
					'field'    => 'term_id',
					'terms'    => [($collections[0])->term_id],
				],
			],
		]
	);

	return array_map('intval', $query->posts);
}

function wj_render_item_navigation(int $post_id): string {
	$context = wj_get_browse_context_from_request();
	$item_ids = [];
	$back_url = '';
	$is_filtered_context = false;

	foreach ($context['filters'] as $key => $value) {
		if ('sort' === $key) {
			if ('date_desc' !== $value) {
				$is_filtered_context = true;
				break;
			}
			continue;
		}

		$is_empty_numeric = is_numeric($value) && 0 === (int) $value;
		if ('' !== (string) $value && !$is_empty_numeric) {
			$is_filtered_context = true;
			break;
		}
	}

	if ($context['browse_url']) {
		$back_url = $context['browse_url'];
	}

	if ($is_filtered_context || $back_url) {
		$item_ids = wj_get_context_item_ids($context['filters']);
	} else {
		$item_ids = wj_get_collection_fallback_ids($post_id);
		$collections = get_the_terms($post_id, 'collection');
		if ($collections && !is_wp_error($collections)) {
			$collection_link = get_term_link($collections[0]);
			if (!is_wp_error($collection_link)) {
				$back_url = $collection_link;
			}
		}
	}

	if (!$item_ids) {
		return '';
	}

	$current_index = array_search($post_id, $item_ids, true);
	if (false === $current_index) {
		return '';
	}

	$prev_id = $item_ids[$current_index - 1] ?? 0;
	$next_id = $item_ids[$current_index + 1] ?? 0;

	$link_filters = $context['filters'];
	$link_url = $back_url;

	ob_start();
	?>
	<nav class="wj-item-nav" aria-label="<?php esc_attr_e('Item navigation', 'twentytwentyfive-child'); ?>">
		<div class="wj-item-nav__back">
			<?php if ($back_url) : ?>
				<a href="<?php echo esc_url($back_url); ?>"><?php esc_html_e('Back to results', 'twentytwentyfive-child'); ?></a>
			<?php endif; ?>
		</div>
		<div class="wj-item-nav__links">
			<?php if ($prev_id) : ?>
				<a class="wj-item-nav__link" href="<?php echo esc_url(wj_get_item_permalink_with_context((int) $prev_id, $link_filters, $link_url)); ?>"><?php esc_html_e('Previous', 'twentytwentyfive-child'); ?></a>
			<?php endif; ?>
			<?php if ($next_id) : ?>
				<a class="wj-item-nav__link" href="<?php echo esc_url(wj_get_item_permalink_with_context((int) $next_id, $link_filters, $link_url)); ?>"><?php esc_html_e('Next', 'twentytwentyfive-child'); ?></a>
			<?php endif; ?>
		</div>
	</nav>
	<?php

	return (string) ob_get_clean();
}

function wj_render_collections_index(): string {
	$terms = get_terms(
		[
			'taxonomy'   => 'collection',
			'hide_empty' => false,
			'parent'     => 0,
			'orderby'    => 'count',
			'order'      => 'DESC',
		]
	);

	if (is_wp_error($terms) || !$terms) {
		return '';
	}

	ob_start();
	?>
	<div class="wj-collection-sections">
		<?php foreach ($terms as $term) : ?>
			<?php
			$link = get_term_link($term);
			$count = (int) $term->count;
			$preview_filters = [
				'search'     => '',
				'agent'      => '',
				'production' => '',
				'collection' => $term->slug,
				'venue'      => '',
				'location'   => '',
				'item_tag'   => '',
				'year'       => 0,
				'sort'       => 'date_desc',
			];
			$browse_url = wj_get_current_browse_url();
			$items = get_posts(
				[
					'post_type'              => 'collection_item',
					'post_status'            => 'publish',
					'posts_per_page'         => 3,
					'ignore_sticky_posts'    => true,
					'no_found_rows'          => true,
					'update_post_meta_cache' => false,
					'update_post_term_cache' => true,
					'meta_key'               => 'item_sort_date',
					'orderby'                => [
						'meta_value' => 'DESC',
						'date'       => 'DESC',
					],
					'tax_query'              => [
						[
							'taxonomy' => 'collection',
							'field'    => 'term_id',
							'terms'    => [$term->term_id],
						],
					],
				]
			);
			?>
			<section class="wj-collection-section">
				<div class="wj-collection-section__header">
					<div class="wj-collection-section__intro">
						<p class="wj-eyebrow"><?php esc_html_e('Collection', 'twentytwentyfive-child'); ?></p>
						<h2><a href="<?php echo esc_url($link); ?>"><?php echo esc_html($term->name); ?></a></h2>
					</div>
					<div class="wj-collection-section__meta">
						<p class="wj-card-meta">
							<span class="wj-card-meta__count"><?php echo esc_html((string) $count); ?></span>
							<span class="wj-card-meta__label"><?php echo esc_html(_n('item', 'items', $count, 'twentytwentyfive-child')); ?></span>
						</p>
						<a class="wj-collection-section__link" href="<?php echo esc_url($link); ?>"><?php esc_html_e('View collection', 'twentytwentyfive-child'); ?></a>
					</div>
				</div>

				<div class="wj-collection-rail" aria-label="<?php echo esc_attr(sprintf(__('Preview items from %s', 'twentytwentyfive-child'), $term->name)); ?>">
					<?php foreach ($items as $post) : ?>
						<?php
						setup_postdata($post);
						$post_id = (int) $post->ID;
						?>
						<article class="wj-collection-preview">
							<a class="wj-collection-preview__image" href="<?php echo esc_url(wj_get_item_permalink_with_context($post_id, $preview_filters, $browse_url)); ?>">
								<?php
								if (has_post_thumbnail($post_id)) {
									echo get_the_post_thumbnail($post_id, 'wj-card');
								}
								?>
							</a>
							<div class="wj-collection-preview__body">
								<h3><a href="<?php echo esc_url(wj_get_item_permalink_with_context($post_id, $preview_filters, $browse_url)); ?>"><?php echo esc_html(get_the_title($post_id)); ?></a></h3>
								<?php echo wj_render_item_card_meta(); ?>
							</div>
						</article>
					<?php endforeach; ?>
					<?php wp_reset_postdata(); ?>

					<a class="wj-collection-preview wj-collection-preview--more" href="<?php echo esc_url($link); ?>">
						<span class="wj-collection-preview__more-copy"><?php esc_html_e('Browse the full collection', 'twentytwentyfive-child'); ?></span>
						<span class="wj-collection-preview__more-action"><?php esc_html_e('View all', 'twentytwentyfive-child'); ?></span>
					</a>
				</div>
			</section>
		<?php endforeach; ?>
	</div>
	<?php
	return (string) ob_get_clean();
}

function wj_get_filter_term_options(string $taxonomy): array {
	$terms = get_terms(
		[
			'taxonomy'   => $taxonomy,
			'hide_empty' => true,
			'orderby'    => 'name',
			'order'      => 'ASC',
			'number'     => 200,
		]
	);

	return is_wp_error($terms) ? [] : $terms;
}

function wj_render_collection_filter_select(array $filters): string {
	if (is_tax('collection')) {
		return '';
	}

	$terms = wj_get_filter_term_options('collection');
	if (!$terms) {
		return '';
	}

	ob_start();
	?>
	<label>
		<span><?php esc_html_e('Collection', 'twentytwentyfive-child'); ?></span>
		<select name="collection">
			<option value=""><?php esc_html_e('All collections', 'twentytwentyfive-child'); ?></option>
			<?php foreach ($terms as $term) : ?>
				<option value="<?php echo esc_attr($term->slug); ?>" <?php selected($filters['collection'], $term->slug); ?>><?php echo esc_html($term->name); ?></option>
			<?php endforeach; ?>
		</select>
	</label>
	<?php

	return (string) ob_get_clean();
}

function wj_should_render_filter(string $taxonomy): bool {
	return !is_tax($taxonomy);
}

function wj_filter_panel_should_open(array $filters): bool {
	$active_keys = [
		'search',
		'agent',
		'production',
		'collection',
		'venue',
		'location',
		'item_tag',
		'year',
	];

	foreach ($active_keys as $key) {
		if (!empty($filters[$key])) {
			return true;
		}
	}

	return !empty($filters['sort']) && 'date_desc' !== $filters['sort'];
}

function wj_render_item_browser(): string {
	$filters = wj_get_filter_values();
	$action = '';

	if (is_tax()) {
		$term = get_queried_object();
		if ($term instanceof WP_Term) {
			$action = get_term_link($term);
		}
	}

	if (!$action) {
		$action = get_post_type_archive_link('collection_item');
	}

	ob_start();
	?>
	<details class="wj-filter-panel" <?php echo wj_filter_panel_should_open($filters) ? 'open' : ''; ?>>
		<summary class="wj-filter-toggle">
			<span><?php esc_html_e('Filter and sort', 'twentytwentyfive-child'); ?></span>
		</summary>
		<form class="wj-filter-bar" method="get" action="<?php echo esc_url($action); ?>">
			<label class="wj-filter-field wj-filter-field--search">
				<span><?php esc_html_e('Search', 'twentytwentyfive-child'); ?></span>
				<input type="search" name="s" value="<?php echo esc_attr($filters['search']); ?>" placeholder="<?php esc_attr_e('Band, tour, city, design...', 'twentytwentyfive-child'); ?>">
			</label>
			<?php if (wj_should_render_filter('agent')) : ?>
				<label>
					<span><?php esc_html_e('Agent', 'twentytwentyfive-child'); ?></span>
					<select name="agent">
						<option value=""><?php esc_html_e('All agents', 'twentytwentyfive-child'); ?></option>
						<?php foreach (wj_get_filter_term_options('agent') as $term) : ?>
							<option value="<?php echo esc_attr($term->slug); ?>" <?php selected($filters['agent'], $term->slug); ?>><?php echo esc_html($term->name); ?></option>
						<?php endforeach; ?>
					</select>
				</label>
			<?php endif; ?>
			<?php if (wj_should_render_filter('production')) : ?>
				<label>
					<span><?php esc_html_e('Production', 'twentytwentyfive-child'); ?></span>
					<select name="production">
						<option value=""><?php esc_html_e('All productions', 'twentytwentyfive-child'); ?></option>
						<?php foreach (wj_get_filter_term_options('production') as $term) : ?>
							<option value="<?php echo esc_attr($term->slug); ?>" <?php selected($filters['production'], $term->slug); ?>><?php echo esc_html($term->name); ?></option>
						<?php endforeach; ?>
					</select>
				</label>
			<?php endif; ?>
			<?php echo wj_render_collection_filter_select($filters); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
			<?php if (wj_should_render_filter('venue')) : ?>
				<label>
					<span><?php esc_html_e('Venue', 'twentytwentyfive-child'); ?></span>
					<select name="venue">
						<option value=""><?php esc_html_e('All venues', 'twentytwentyfive-child'); ?></option>
						<?php foreach (wj_get_filter_term_options('venue') as $term) : ?>
							<option value="<?php echo esc_attr($term->slug); ?>" <?php selected($filters['venue'], $term->slug); ?>><?php echo esc_html($term->name); ?></option>
						<?php endforeach; ?>
					</select>
				</label>
			<?php endif; ?>
			<?php if (wj_should_render_filter('location')) : ?>
				<label>
					<span><?php esc_html_e('Location', 'twentytwentyfive-child'); ?></span>
					<select name="location">
						<option value=""><?php esc_html_e('All locations', 'twentytwentyfive-child'); ?></option>
						<?php foreach (wj_get_filter_term_options('location') as $term) : ?>
							<option value="<?php echo esc_attr($term->slug); ?>" <?php selected($filters['location'], $term->slug); ?>><?php echo esc_html($term->name); ?></option>
						<?php endforeach; ?>
					</select>
				</label>
			<?php endif; ?>
			<?php if (wj_should_render_filter('item_tag')) : ?>
				<label>
					<span><?php esc_html_e('Subject', 'twentytwentyfive-child'); ?></span>
					<select name="item_tag">
						<option value=""><?php esc_html_e('All subjects', 'twentytwentyfive-child'); ?></option>
						<?php foreach (wj_get_filter_term_options('item_tag') as $term) : ?>
							<option value="<?php echo esc_attr($term->slug); ?>" <?php selected($filters['item_tag'], $term->slug); ?>><?php echo esc_html($term->name); ?></option>
						<?php endforeach; ?>
					</select>
				</label>
			<?php endif; ?>
			<label>
				<span><?php esc_html_e('Year', 'twentytwentyfive-child'); ?></span>
				<input type="number" min="1900" max="<?php echo esc_attr((string) ((int) gmdate('Y') + 5)); ?>" name="item_year" value="<?php echo esc_attr($filters['year'] ?: ''); ?>" placeholder="1994">
			</label>
			<label>
				<span><?php esc_html_e('Sort', 'twentytwentyfive-child'); ?></span>
				<select name="sort">
					<option value="date_desc" <?php selected($filters['sort'], 'date_desc'); ?>><?php esc_html_e('Date, newest first', 'twentytwentyfive-child'); ?></option>
					<option value="date_asc" <?php selected($filters['sort'], 'date_asc'); ?>><?php esc_html_e('Date, oldest first', 'twentytwentyfive-child'); ?></option>
					<option value="title_asc" <?php selected($filters['sort'], 'title_asc'); ?>><?php esc_html_e('Title A-Z', 'twentytwentyfive-child'); ?></option>
					<option value="title_desc" <?php selected($filters['sort'], 'title_desc'); ?>><?php esc_html_e('Title Z-A', 'twentytwentyfive-child'); ?></option>
					<option value="recent" <?php selected($filters['sort'], 'recent'); ?>><?php esc_html_e('Recently added', 'twentytwentyfive-child'); ?></option>
				</select>
			</label>
			<div class="wj-filter-actions">
				<button class="wj-filter-submit" type="submit"><?php esc_html_e('Apply Filters', 'twentytwentyfive-child'); ?></button>
				<a class="wj-filter-reset" href="<?php echo esc_url($action); ?>"><?php esc_html_e('Reset', 'twentytwentyfive-child'); ?></a>
			</div>
		</form>
	</details>
	<?php

	return (string) ob_get_clean();
}

function wj_render_item_card_meta(): string {
	$post_id = get_the_ID();
	$year = (int) get_post_meta($post_id, 'item_year', true);
	$locations = get_the_terms($post_id, 'location');

	ob_start();
	?>
	<ul class="wj-item-card-meta">
		<?php if ($year) : ?>
			<li><?php echo esc_html((string) $year); ?></li>
		<?php endif; ?>
		<?php if ($locations && !is_wp_error($locations)) : ?>
			<li><?php echo esc_html($locations[0]->name); ?></li>
		<?php endif; ?>
	</ul>
	<?php

	return (string) ob_get_clean();
}

function wj_get_collection_context_slug(int $post_id): string {
	$collections = get_the_terms($post_id, 'collection');
	if (!$collections || is_wp_error($collections)) {
		return '';
	}

	return $collections[0]->slug;
}

function wj_get_prefiltered_term_link(WP_Term $term, int $post_id): string {
	$collection_slug = wj_get_collection_context_slug($post_id);
	if ($collection_slug) {
		$base = get_term_link($collection_slug, 'collection');
		if (!is_wp_error($base)) {
			return add_query_arg($term->taxonomy, $term->slug, $base);
		}
	}

	$url = get_post_type_archive_link('collection_item');
	return add_query_arg($term->taxonomy, $term->slug, $url);
}

function wj_render_linked_term_list(int $post_id, string $taxonomy, string $label): string {
	$terms = get_the_terms($post_id, $taxonomy);
	if (!$terms || is_wp_error($terms)) {
		return '';
	}

	$links = [];
	foreach ($terms as $term) {
		$links[] = sprintf(
			'<a href="%s">%s</a>',
			esc_url(wj_get_prefiltered_term_link($term, $post_id)),
			esc_html($term->name)
		);
	}

	return sprintf('<li><span>%s</span><div>%s</div></li>', esc_html($label), wp_kses_post(implode(', ', $links)));
}

function wj_render_agent_term_list(int $post_id): string {
	$terms = get_the_terms($post_id, 'agent');
	if (!$terms || is_wp_error($terms)) {
		return '';
	}

	$items = [];
	foreach ($terms as $term) {
		$agent_link = get_term_link($term);
		$filter_link = wj_get_prefiltered_term_link($term, $post_id);
		if (is_wp_error($agent_link) || is_wp_error($filter_link)) {
			continue;
		}

		$items[] = sprintf(
			'<div class="wj-agent-chip"><a class="wj-agent-chip__primary" href="%1$s">%2$s<span>%3$s</span></a><a class="wj-agent-chip__secondary" href="%4$s">%5$s</a></div>',
			esc_url($agent_link),
			wj_get_term_avatar_markup($term, 'wj-agent-avatar'),
			esc_html($term->name),
			esc_url($filter_link),
			esc_html__('Filter in collection', 'twentytwentyfive-child')
		);
	}

	if (!$items) {
		return '';
	}

	return sprintf('<li><span>%s</span><div class="wj-agent-chip-list">%s</div></li>', esc_html__('Agent', 'twentytwentyfive-child'), wp_kses_post(implode('', $items)));
}

function wj_render_item_meta(): string {
	$post_id = get_the_ID();
	$display_date = (string) get_post_meta($post_id, 'item_date_display', true);
	$sort_date = (string) get_post_meta($post_id, 'item_sort_date', true);
	$lines = [];
	$meta_values = [
		'item_identifier'   => get_post_meta($post_id, 'item_identifier', true),
		'item_date_display' => $display_date ?: $sort_date,
		'item_publisher'    => get_post_meta($post_id, 'item_publisher', true),
		'item_materials'    => get_post_meta($post_id, 'item_materials', true),
		'item_dimensions'   => get_post_meta($post_id, 'item_dimensions', true),
		'item_condition'    => get_post_meta($post_id, 'item_condition', true),
		'item_rights'       => get_post_meta($post_id, 'item_rights', true),
		'item_inscription'  => get_post_meta($post_id, 'item_inscription', true),
	];

	$meta_labels = [
		'item_identifier'   => __('Identifier', 'twentytwentyfive-child'),
		'item_date_display' => __('Date', 'twentytwentyfive-child'),
		'item_publisher'    => __('Publisher', 'twentytwentyfive-child'),
		'item_materials'    => __('Materials', 'twentytwentyfive-child'),
		'item_dimensions'   => __('Dimensions', 'twentytwentyfive-child'),
		'item_condition'    => __('Condition', 'twentytwentyfive-child'),
		'item_rights'       => __('Rights', 'twentytwentyfive-child'),
		'item_inscription'  => __('Inscription / Notes', 'twentytwentyfive-child'),
	];

	$append_meta_line = static function (array &$lines, string $label, mixed $value): void {
		if ('' === (string) $value || null === $value) {
			return;
		}

		$lines[] = sprintf(
			'<li><span>%s</span><div>%s</div></li>',
			esc_html($label),
			esc_html((string) $value)
		);
	};

	$append_meta_line($lines, $meta_labels['item_identifier'], $meta_values['item_identifier']);
	$append_meta_line($lines, $meta_labels['item_date_display'], $meta_values['item_date_display']);

	$lines[] = wj_render_agent_term_list($post_id);
	$append_meta_line($lines, $meta_labels['item_publisher'], $meta_values['item_publisher']);
	$lines[] = wj_render_linked_term_list($post_id, 'production', __('Production', 'twentytwentyfive-child'));
	$lines[] = wj_render_linked_term_list($post_id, 'venue', __('Venue', 'twentytwentyfive-child'));
	$lines[] = wj_render_linked_term_list($post_id, 'location', __('Location', 'twentytwentyfive-child'));
	$event_link = get_post_meta($post_id, 'item_event_link', true);
	if ($event_link) {
		$lines[] = sprintf(
			'<li><span>%s</span><div><a href="%s" target="_blank" rel="noreferrer noopener">%s</a></div></li>',
			esc_html__('Related Link', 'twentytwentyfive-child'),
			esc_url((string) $event_link),
			esc_html__('Open link', 'twentytwentyfive-child')
		);
	}
	$lines[] = wj_render_linked_term_list($post_id, 'item_tag', __('Subject', 'twentytwentyfive-child'));
	$lines[] = wj_render_linked_term_list($post_id, 'collection', __('Collection', 'twentytwentyfive-child'));
	$append_meta_line($lines, $meta_labels['item_materials'], $meta_values['item_materials']);
	$append_meta_line($lines, $meta_labels['item_dimensions'], $meta_values['item_dimensions']);
	$append_meta_line($lines, $meta_labels['item_condition'], $meta_values['item_condition']);
	$append_meta_line($lines, $meta_labels['item_rights'], $meta_values['item_rights']);
	$append_meta_line($lines, $meta_labels['item_inscription'], $meta_values['item_inscription']);

	ob_start();
	?>
	<div class="wj-item-meta-wrap">
		<ul class="wj-item-meta"><?php echo wp_kses_post(implode('', array_filter($lines))); ?></ul>
	</div>
	<?php

	return (string) ob_get_clean();
}

function wj_get_item_image_ids(int $post_id): array {
	$ids = [];
	$featured_id = get_post_thumbnail_id($post_id);
	if ($featured_id) {
		$ids[] = $featured_id;
	}

	$gallery = get_post_meta($post_id, 'item_gallery_ids', true);
	if ($gallery) {
		$ids = array_merge($ids, array_filter(array_map('absint', explode(',', (string) $gallery))));
	}

	return array_values(array_unique(array_filter($ids)));
}

function wj_render_item_viewer(): string {
	$post_id = get_the_ID();
	$image_ids = wj_get_item_image_ids($post_id);

	if (!$image_ids) {
		return '';
	}

	$viewer_id = 'wj-viewer-' . $post_id;
	$images = [];

	foreach ($image_ids as $image_id) {
		$url = wp_get_attachment_image_url($image_id, 'full');
		if ($url) {
			$images[] = $url;
		}
	}

	if (!$images) {
		return '';
	}

	$primary_image = $images[0];

	ob_start();
	?>
	<div class="wj-viewer-shell" data-wj-viewer-shell>
		<button
			type="button"
			class="wj-mobile-viewer-trigger"
			data-wj-modal-open
			data-modal-target="<?php echo esc_attr($viewer_id . '-modal'); ?>"
			aria-label="<?php esc_attr_e('Open image viewer', 'twentytwentyfive-child'); ?>"
		>
			<img src="<?php echo esc_url($primary_image); ?>" alt="">
			<span class="wj-mobile-viewer-trigger__hint"><?php esc_html_e('Tap to enlarge', 'twentytwentyfive-child'); ?></span>
		</button>
		<div
			id="<?php echo esc_attr($viewer_id); ?>"
			class="wj-viewer"
			data-wj-viewer
			data-viewer-id="<?php echo esc_attr($viewer_id); ?>"
			data-images="<?php echo esc_attr(wp_json_encode($images)); ?>"
		></div>
		<?php if (count($images) > 1) : ?>
			<div class="wj-thumb-strip" aria-label="<?php esc_attr_e('Image thumbnails', 'twentytwentyfive-child'); ?>">
				<?php foreach ($image_ids as $index => $image_id) : ?>
					<?php $image_url = wp_get_attachment_image_url($image_id, 'full'); ?>
					<?php if (!$image_url) : ?>
						<?php continue; ?>
					<?php endif; ?>
					<button
						class="wj-thumb-button"
						type="button"
						data-wj-thumb
						data-index="<?php echo esc_attr((string) $index); ?>"
						data-image-src="<?php echo esc_url($image_url); ?>"
					>
						<?php echo wp_get_attachment_image($image_id, 'wj-thumb'); ?>
					</button>
				<?php endforeach; ?>
			</div>
		<?php endif; ?>
	</div>
	<div class="wj-viewer-modal" id="<?php echo esc_attr($viewer_id . '-modal'); ?>" data-wj-viewer-modal hidden>
		<div class="wj-viewer-modal__backdrop" data-wj-modal-close></div>
		<div class="wj-viewer-modal__dialog" role="dialog" aria-modal="true" aria-label="<?php esc_attr_e('Image viewer', 'twentytwentyfive-child'); ?>">
			<div class="wj-viewer-modal__toolbar">
				<button type="button" class="wj-viewer-modal__close" data-wj-modal-close><?php esc_html_e('Close', 'twentytwentyfive-child'); ?></button>
			</div>
			<div class="wj-viewer-modal__stage">
				<img
					class="wj-viewer-modal__image"
					src="<?php echo esc_url($primary_image); ?>"
					alt=""
					data-wj-modal-image
				>
			</div>
			<?php if (count($images) > 1) : ?>
				<div class="wj-viewer-modal__nav">
					<button type="button" class="wj-viewer-modal__step" data-wj-modal-prev><?php esc_html_e('Previous image', 'twentytwentyfive-child'); ?></button>
					<div class="wj-viewer-modal__count" data-wj-modal-count>
						<?php
						echo esc_html(
							sprintf(
								/* translators: 1: current image number, 2: total image count */
								__('Image %1$d of %2$d', 'twentytwentyfive-child'),
								1,
								count($images)
							)
						);
						?>
					</div>
					<button type="button" class="wj-viewer-modal__step" data-wj-modal-next><?php esc_html_e('Next image', 'twentytwentyfive-child'); ?></button>
				</div>
			<?php endif; ?>
			<?php if (count($images) > 1) : ?>
				<div class="wj-thumb-strip wj-thumb-strip--modal" aria-label="<?php esc_attr_e('Image thumbnails', 'twentytwentyfive-child'); ?>">
					<?php foreach ($image_ids as $index => $image_id) : ?>
						<?php $image_url = wp_get_attachment_image_url($image_id, 'full'); ?>
						<?php if (!$image_url) : ?>
							<?php continue; ?>
						<?php endif; ?>
						<button
							class="wj-thumb-button"
							type="button"
							data-wj-modal-thumb
							data-index="<?php echo esc_attr((string) $index); ?>"
							data-image-src="<?php echo esc_url($image_url); ?>"
							<?php echo 0 === $index ? 'aria-pressed="true"' : 'aria-pressed="false"'; ?>
						>
							<?php echo wp_get_attachment_image($image_id, 'wj-thumb'); ?>
						</button>
					<?php endforeach; ?>
				</div>
			<?php endif; ?>
		</div>
	</div>
	<?php

	return (string) ob_get_clean();
}

function wj_render_term_intro(): string {
	if (!is_tax()) {
		return '';
	}

	$term = get_queried_object();
	if (!$term instanceof WP_Term) {
		return '';
	}

	$copy = term_description($term, $term->taxonomy);
	$linked_data = '';
	if ('agent' === $term->taxonomy) {
		$linked_data = wj_render_wikidata_card(wj_get_wikidata_entity_for_term($term));
	}

	if (!$copy && !$linked_data) {
		return '';
	}

	$output = '<div class="wj-term-intro">';
	if ($copy) {
		$output .= wp_kses_post(wpautop($copy));
	}
	$output .= $linked_data;
	$output .= '</div>';

	return $output;
}

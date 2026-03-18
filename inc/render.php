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
	<div class="wj-collection-grid">
		<?php foreach ($terms as $term) : ?>
			<?php
			$link = get_term_link($term);
			$count = (int) $term->count;
			?>
			<article class="wj-collection-card">
				<p class="wj-eyebrow"><?php esc_html_e('Collection', 'twentytwentyfive-child'); ?></p>
				<h2><a href="<?php echo esc_url($link); ?>"><?php echo esc_html($term->name); ?></a></h2>
				<p class="wj-card-meta">
					<span class="wj-card-meta__count"><?php echo esc_html((string) $count); ?></span>
					<span class="wj-card-meta__label"><?php echo esc_html(_n('item', 'items', $count, 'twentytwentyfive-child')); ?></span>
				</p>
			</article>
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
			<label>
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
	$fields = [
		'item_identifier'   => __('Identifier', 'twentytwentyfive-child'),
		'item_date_display' => __('Date', 'twentytwentyfive-child'),
		'item_inscription'  => __('Inscription / Notes', 'twentytwentyfive-child'),
		'item_materials'    => __('Materials', 'twentytwentyfive-child'),
		'item_dimensions'   => __('Dimensions', 'twentytwentyfive-child'),
		'item_condition'    => __('Condition', 'twentytwentyfive-child'),
		'item_rights'       => __('Rights', 'twentytwentyfive-child'),
	];

	$lines = [];
	foreach ($fields as $meta_key => $label) {
		$value = 'item_date_display' === $meta_key ? ($display_date ?: $sort_date) : get_post_meta($post_id, $meta_key, true);
		if ('' === $value || null === $value) {
			continue;
		}

		if ('item_event_link' === $meta_key) {
			$lines[] = sprintf(
				'<li><span>%s</span><div><a href="%s" target="_blank" rel="noreferrer noopener">%s</a></div></li>',
				esc_html($label),
				esc_url((string) $value),
				esc_html__('View event resource', 'twentytwentyfive-child')
			);
			continue;
		}

		$lines[] = sprintf(
			'<li><span>%s</span><div>%s</div></li>',
			esc_html($label),
			esc_html((string) $value)
		);
	}

	$lines[] = wj_render_agent_term_list($post_id);
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

	ob_start();
	?>
	<div class="wj-viewer-shell" data-wj-viewer-shell>
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

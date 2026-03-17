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

function wj_render_collections_index(): string {
	$terms = get_terms(
		[
			'taxonomy'   => 'collection',
			'hide_empty' => false,
			'parent'     => 0,
			'orderby'    => 'name',
			'order'      => 'ASC',
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
				<p class="wj-card-meta"><?php echo esc_html(sprintf(_n('%d item', '%d items', $count, 'twentytwentyfive-child'), $count)); ?></p>
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
	<form class="wj-filter-bar" method="get" action="<?php echo esc_url($action); ?>">
		<label>
			<span><?php esc_html_e('Search', 'twentytwentyfive-child'); ?></span>
			<input type="search" name="s" value="<?php echo esc_attr($filters['search']); ?>" placeholder="<?php esc_attr_e('Band, tour, city, design...', 'twentytwentyfive-child'); ?>">
		</label>
		<?php if (wj_should_render_filter('artist')) : ?>
			<label>
				<span><?php esc_html_e('Artist', 'twentytwentyfive-child'); ?></span>
				<select name="artist">
					<option value=""><?php esc_html_e('All artists', 'twentytwentyfive-child'); ?></option>
					<?php foreach (wj_get_filter_term_options('artist') as $term) : ?>
						<option value="<?php echo esc_attr($term->slug); ?>" <?php selected($filters['artist'], $term->slug); ?>><?php echo esc_html($term->name); ?></option>
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
				<option value="year_desc" <?php selected($filters['sort'], 'year_desc'); ?>><?php esc_html_e('Year, newest first', 'twentytwentyfive-child'); ?></option>
				<option value="year_asc" <?php selected($filters['sort'], 'year_asc'); ?>><?php esc_html_e('Year, oldest first', 'twentytwentyfive-child'); ?></option>
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
	<?php

	return (string) ob_get_clean();
}

function wj_render_item_card_meta(): string {
	$post_id = get_the_ID();
	$year = (int) get_post_meta($post_id, 'item_year', true);
	$collections = get_the_terms($post_id, 'collection');
	$artists = get_the_terms($post_id, 'artist');

	ob_start();
	?>
	<ul class="wj-item-card-meta">
		<?php if ($year) : ?>
			<li><?php echo esc_html((string) $year); ?></li>
		<?php endif; ?>
		<?php if ($collections && !is_wp_error($collections)) : ?>
			<li><?php echo esc_html($collections[0]->name); ?></li>
		<?php endif; ?>
		<?php if ($artists && !is_wp_error($artists)) : ?>
			<li><?php echo esc_html($artists[0]->name); ?></li>
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

function wj_render_item_meta(): string {
	$post_id = get_the_ID();
	$fields = [
		'item_identifier'   => __('Identifier', 'twentytwentyfive-child'),
		'item_date_display' => __('Date', 'twentytwentyfive-child'),
		'item_condition'    => __('Condition', 'twentytwentyfive-child'),
		'item_materials'    => __('Materials', 'twentytwentyfive-child'),
		'item_dimensions'   => __('Dimensions', 'twentytwentyfive-child'),
		'item_inscription'  => __('Inscription / Notes', 'twentytwentyfive-child'),
		'item_rights'       => __('Rights', 'twentytwentyfive-child'),
		'item_event_link'   => __('Event Link', 'twentytwentyfive-child'),
	];

	$lines = [];
	foreach ($fields as $meta_key => $label) {
		$value = get_post_meta($post_id, $meta_key, true);
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

	$lines[] = wj_render_linked_term_list($post_id, 'collection', __('Collection', 'twentytwentyfive-child'));
	$lines[] = wj_render_linked_term_list($post_id, 'artist', __('Artist', 'twentytwentyfive-child'));
	$lines[] = wj_render_linked_term_list($post_id, 'venue', __('Venue', 'twentytwentyfive-child'));
	$lines[] = wj_render_linked_term_list($post_id, 'location', __('Location', 'twentytwentyfive-child'));
	$lines[] = wj_render_linked_term_list($post_id, 'item_tag', __('Subject', 'twentytwentyfive-child'));

	$artists = get_the_terms($post_id, 'artist');
	$cross_links = [];
	if ($artists && !is_wp_error($artists)) {
		foreach ($artists as $artist) {
			$term_link = get_term_link($artist);
			if (is_wp_error($term_link)) {
				continue;
			}

			$cross_links[] = sprintf(
				'<a href="%s">%s</a>',
				esc_url($term_link),
				esc_html(sprintf(__('See all items for %s', 'twentytwentyfive-child'), $artist->name))
			);
		}
	}

	ob_start();
	?>
	<div class="wj-item-meta-wrap">
		<ul class="wj-item-meta"><?php echo wp_kses_post(implode('', array_filter($lines))); ?></ul>
		<?php if ($cross_links) : ?>
			<div class="wj-cross-links"><?php echo wp_kses_post(implode('', $cross_links)); ?></div>
		<?php endif; ?>
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
	if ('artist' === $term->taxonomy) {
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

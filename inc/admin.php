<?php
/**
 * Admin UI for item metadata and artist linked-data fields.
 */

if (!defined('ABSPATH')) {
	exit;
}

function wj_register_item_metabox(): void {
	add_meta_box(
		'wj-item-details',
		__('Item Details', 'twentytwentyfive-child'),
		'wj_render_item_metabox',
		'collection_item',
		'normal',
		'default'
	);
}
add_action('add_meta_boxes', 'wj_register_item_metabox');

function wj_render_item_metabox(WP_Post $post): void {
	wp_nonce_field('wj_save_item_meta', 'wj_item_meta_nonce');
	?>
	<div class="wj-admin-grid">
		<?php foreach (WJ_ITEM_META as $meta_key => $type) : ?>
			<?php $value = get_post_meta($post->ID, $meta_key, true); ?>
			<?php $ui = WJ_ITEM_META_UI[$meta_key] ?? ['label' => $meta_key, 'description' => '']; ?>
			<p>
				<label for="<?php echo esc_attr($meta_key); ?>"><?php echo esc_html($ui['label']); ?></label><br>
				<input
					type="<?php echo 'integer' === $type ? 'number' : 'text'; ?>"
					class="widefat"
					id="<?php echo esc_attr($meta_key); ?>"
					name="<?php echo esc_attr($meta_key); ?>"
					value="<?php echo esc_attr((string) $value); ?>"
				>
				<?php if (!empty($ui['description'])) : ?>
					<span class="description"><?php echo esc_html($ui['description']); ?></span>
				<?php endif; ?>
			</p>
		<?php endforeach; ?>
	</div>
	<p><?php esc_html_e('Use the featured image for the primary image. Put front/back images, reverse sides, and detail shots into Additional Images so the single-item viewer can switch between them.', 'twentytwentyfive-child'); ?></p>
	<?php
}

function wj_sanitize_meta_input(string $meta_key, $value, string $type) {
	if ('item_sort_date' === $meta_key) {
		return wj_normalize_sort_date((string) $value);
	}

	if ('integer' === $type) {
		$year = absint($value);
		return $year ?: '';
	}

	if ('item_event_link' === $meta_key) {
		return esc_url_raw((string) $value);
	}

	if ('item_gallery_ids' === $meta_key) {
		$ids = array_filter(array_map('absint', explode(',', (string) $value)));
		return implode(',', $ids);
	}

	return sanitize_text_field((string) $value);
}

function wj_save_item_meta(int $post_id): void {
	if (!isset($_POST['wj_item_meta_nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['wj_item_meta_nonce'])), 'wj_save_item_meta')) {
		return;
	}

	if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
		return;
	}

	if (!current_user_can('edit_post', $post_id)) {
		return;
	}

	foreach (WJ_ITEM_META as $meta_key => $type) {
		if (!array_key_exists($meta_key, $_POST)) {
			continue;
		}

		$value = wp_unslash($_POST[$meta_key]);
		update_post_meta($post_id, $meta_key, wj_sanitize_meta_input($meta_key, $value, $type));
	}

	$sort_date = (string) get_post_meta($post_id, 'item_sort_date', true);
	$display_date = (string) get_post_meta($post_id, 'item_date_display', true);
	$item_year = (string) get_post_meta($post_id, 'item_year', true);

	if (!$sort_date) {
		$sort_date = wj_derive_sort_date($display_date, $item_year);
		if ($sort_date) {
			update_post_meta($post_id, 'item_sort_date', $sort_date);
		}
	}

	if ($sort_date) {
		update_post_meta($post_id, 'item_year', wj_get_sort_date_year($sort_date));
	}
}
add_action('save_post_collection_item', 'wj_save_item_meta');

function wj_collection_item_admin_columns(array $columns): array {
	$updated_columns = [];

	foreach ($columns as $key => $label) {
		$updated_columns[$key] = $label;

		if ('title' === $key) {
			$updated_columns['item_sort_date'] = __('Sort Date', 'twentytwentyfive-child');
			$updated_columns['item_date_display'] = __('Display Date', 'twentytwentyfive-child');
		}
	}

	return $updated_columns;
}
add_filter('manage_edit-collection_item_columns', 'wj_collection_item_admin_columns');

function wj_render_collection_item_admin_column(string $column, int $post_id): void {
	if ('item_sort_date' === $column) {
		$value = get_post_meta($post_id, 'item_sort_date', true);
		echo esc_html($value ?: '-');
		return;
	}

	if ('item_date_display' === $column) {
		$value = get_post_meta($post_id, 'item_date_display', true);
		echo esc_html($value ?: '-');
	}
}
add_action('manage_collection_item_posts_custom_column', 'wj_render_collection_item_admin_column', 10, 2);

function wj_collection_item_sortable_columns(array $columns): array {
	$columns['item_sort_date'] = 'item_sort_date';
	$columns['item_date_display'] = 'item_sort_date';
	return $columns;
}
add_filter('manage_edit-collection_item_sortable_columns', 'wj_collection_item_sortable_columns');

function wj_default_collection_item_admin_sort(WP_Query $query): void {
	if (!is_admin() || !$query->is_main_query()) {
		return;
	}

	if ('collection_item' !== $query->get('post_type')) {
		return;
	}

	$orderby = $query->get('orderby');

	if (!$orderby) {
		$query->set('meta_key', 'item_sort_date');
		$query->set('orderby', 'meta_value');
		$query->set('order', 'DESC');
		return;
	}

	if (in_array($orderby, ['item_date_display', 'item_sort_date'], true)) {
		$query->set('meta_key', 'item_sort_date');
		$query->set('orderby', 'meta_value');
	}
}
add_action('pre_get_posts', 'wj_default_collection_item_admin_sort');

function wj_add_artist_term_fields(): void {
	?>
	<div class="form-field term-wikidata-wrap">
		<label for="wikidata_id"><?php esc_html_e('Wikidata ID', 'twentytwentyfive-child'); ?></label>
		<input type="text" name="wikidata_id" id="wikidata_id" value="" placeholder="Q11647">
		<p><?php esc_html_e('Optional. Used to enrich artist pages with Wikidata and Wikipedia links.', 'twentytwentyfive-child'); ?></p>
	</div>
	<?php
}
add_action('artist_add_form_fields', 'wj_add_artist_term_fields');

function wj_edit_artist_term_fields(WP_Term $term): void {
	$wikidata_id = get_term_meta($term->term_id, 'wikidata_id', true);
	?>
	<tr class="form-field term-wikidata-wrap">
		<th scope="row"><label for="wikidata_id"><?php esc_html_e('Wikidata ID', 'twentytwentyfive-child'); ?></label></th>
		<td>
			<input type="text" name="wikidata_id" id="wikidata_id" value="<?php echo esc_attr((string) $wikidata_id); ?>" placeholder="Q11647">
			<p class="description"><?php esc_html_e('Optional. Used to enrich artist pages with Wikidata and Wikipedia links.', 'twentytwentyfive-child'); ?></p>
		</td>
	</tr>
	<?php
}
add_action('artist_edit_form_fields', 'wj_edit_artist_term_fields');

function wj_save_artist_term_fields(int $term_id): void {
	if (!array_key_exists('wikidata_id', $_POST)) {
		return;
	}

	update_term_meta($term_id, 'wikidata_id', sanitize_text_field(wp_unslash($_POST['wikidata_id'])));
}
add_action('created_artist', 'wj_save_artist_term_fields');
add_action('edited_artist', 'wj_save_artist_term_fields');

<?php
/**
 * One-off data cleanup helpers for collection items.
 *
 * Run with:
 * ddev wp eval-file /var/www/html/wp/wp-content/themes/twentytwentyfive-child/scripts/cleanup_collection_data.php
 */

if (!defined('ABSPATH')) {
	exit;
}

$updated_dates = 0;
$deleted_terms = 0;

$omeka_posts = get_posts(
	[
		'post_type'      => 'collection_item',
		'post_status'    => 'any',
		'posts_per_page' => -1,
		'meta_query'     => [
			[
				'key'     => '_wj_source_uri',
				'compare' => 'EXISTS',
			],
		],
	]
);

foreach ($omeka_posts as $post) {
	$display_date = (string) get_post_meta($post->ID, 'item_date_display', true);
	$sort_date = (string) get_post_meta($post->ID, 'item_sort_date', true);

	if (!$sort_date || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $sort_date)) {
		continue;
	}

	if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $display_date)) {
		continue;
	}

	$datetime = DateTime::createFromFormat('Y-m-d', $sort_date);
	if (!$datetime) {
		continue;
	}

	$normalized = $datetime->format('F j, Y');
	update_post_meta($post->ID, 'item_date_display', $normalized);
	$updated_dates++;
	echo 'Updated display date: ' . $post->ID . ' | ' . $post->post_title . ' | ' . $normalized . PHP_EOL;
}

foreach (['location', 'venue'] as $taxonomy) {
	$terms = get_terms(
		[
			'taxonomy'   => $taxonomy,
			'hide_empty' => false,
		]
	);

	if (is_wp_error($terms)) {
		continue;
	}

	foreach ($terms as $term) {
		if ((int) $term->count !== 0) {
			continue;
		}

		$result = wp_delete_term($term->term_id, $taxonomy);
		if (false === $result || is_wp_error($result)) {
			continue;
		}

		$deleted_terms++;
		echo 'Deleted empty term: ' . $taxonomy . ' | ' . $term->name . PHP_EOL;
	}
}

echo 'Summary: updated_dates=' . $updated_dates . ' deleted_terms=' . $deleted_terms . PHP_EOL;

<?php
/**
 * Normalize obvious duplicate/variant subject terms.
 *
 * Run with:
 * ddev wp eval-file /var/www/html/wp/wp-content/themes/twentytwentyfive-child/scripts/normalize_subject_terms.php
 */

if (!defined('ABSPATH')) {
	exit;
}

$taxonomy = 'item_tag';

$merge_map = [
	'18 and over'       => '18+',
	'alternative'       => 'alternative rock',
	'Classical music'   => 'classical',
	'concert tickets'   => 'concert ticket',
	'Concerts'          => 'concert',
	'Funk music'        => 'funk',
	'Music festivals'   => 'music festival',
	'Outdoor concerts'  => 'outdoor concert',
	'Rock music'        => 'rock',
	'Theatre'           => 'theater',
	'concert ticket'    => 'ticket',
	'Game ticket'       => 'ticket',
	'Music ticket'      => 'ticket',
	'Season Ticket'     => 'ticket',
	'sports ticket'     => 'ticket',
	'ticket stub'       => 'ticket',
	'Band flyer'        => 'flyer',
	'club flyer'        => 'flyer',
	'concert flyer'     => 'flyer',
];

$merged = 0;
$renamed = 0;
$flyer_cleanups = 0;

foreach ($merge_map as $source_name => $target_name) {
	$source = get_term_by('name', $source_name, $taxonomy);
	if (!$source instanceof WP_Term) {
		echo 'Missing source term: ' . $source_name . PHP_EOL;
		continue;
	}

	$target = get_term_by('name', $target_name, $taxonomy);
	if (!$target instanceof WP_Term) {
		$result = wp_insert_term($target_name, $taxonomy);
		if (is_wp_error($result)) {
			echo 'Could not create target term: ' . $target_name . ' | ' . $result->get_error_message() . PHP_EOL;
			continue;
		}

		$target = get_term((int) $result['term_id'], $taxonomy);
	}

	if (!$target instanceof WP_Term) {
		continue;
	}

	$object_ids = get_objects_in_term($source->term_id, $taxonomy);
	if (is_wp_error($object_ids)) {
		continue;
	}

	foreach ($object_ids as $object_id) {
		$current = wp_get_object_terms((int) $object_id, $taxonomy, ['fields' => 'names']);
		if (is_wp_error($current)) {
			continue;
		}

		$current = array_values(array_unique(array_filter($current)));
		$current = array_diff($current, [$source->name]);
		$current[] = $target->name;
		wp_set_object_terms((int) $object_id, array_values(array_unique($current)), $taxonomy, false);
	}

	$result = wp_delete_term($source->term_id, $taxonomy);
	if (false === $result || is_wp_error($result)) {
		echo 'Could not delete source term: ' . $source_name . PHP_EOL;
		continue;
	}

	$merged++;
	echo 'Merged subject term: ' . $source_name . ' -> ' . $target->name . PHP_EOL;
}

$rename_map = [
	'Music festival'  => 'music festival',
	'Outdoor concert' => 'outdoor concert',
	'Theater'         => 'theater',
	'Ticket'          => 'ticket',
];

foreach ($rename_map as $source_name => $target_name) {
	$term = get_term_by('name', $source_name, $taxonomy);
	if (!$term instanceof WP_Term) {
		continue;
	}

	$existing = get_term_by('name', $target_name, $taxonomy);
	if ($existing instanceof WP_Term && (int) $existing->term_id !== (int) $term->term_id) {
		continue;
	}

	$result = wp_update_term($term->term_id, $taxonomy, ['name' => $target_name]);
	if (is_wp_error($result)) {
		echo 'Could not rename term: ' . $source_name . ' | ' . $result->get_error_message() . PHP_EOL;
		continue;
	}

	$renamed++;
	echo 'Renamed subject term: ' . $source_name . ' -> ' . $target_name . PHP_EOL;
}

$flyer_term = get_term_by('name', 'flyer', $taxonomy);
if ($flyer_term instanceof WP_Term) {
	$flyer_posts = get_posts(
		[
			'post_type'      => 'collection_item',
			'post_status'    => 'any',
			'posts_per_page' => -1,
			'fields'         => 'ids',
			'tax_query'      => [
				[
					'taxonomy' => $taxonomy,
					'field'    => 'term_id',
					'terms'    => [$flyer_term->term_id],
				],
			],
		]
	);

	foreach ($flyer_posts as $post_id) {
		$current = wp_get_object_terms((int) $post_id, $taxonomy, ['fields' => 'names']);
		if (is_wp_error($current) || !$current) {
			continue;
		}

		$updated = array_values(array_unique(array_filter(array_diff($current, ['ticket']))));
		if ($updated === $current) {
			continue;
		}

		wp_set_object_terms((int) $post_id, $updated, $taxonomy, false);
		$flyer_cleanups++;
		echo 'Removed ticket from flyer item: ' . $post_id . PHP_EOL;
	}
}

echo 'Summary: merged=' . $merged . ' renamed=' . $renamed . ' flyer_cleanups=' . $flyer_cleanups . PHP_EOL;

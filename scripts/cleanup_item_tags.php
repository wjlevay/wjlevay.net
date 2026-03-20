<?php
/**
 * Cleanup the item_tag vocabulary:
 * - merge obvious duplicate/variant terms
 * - standardize generic capitalization
 * - decode HTML entities in names
 * - delete zero-count terms
 *
 * Run with:
 * wp eval-file wp-content/themes/twentytwentyfive-child/scripts/cleanup_item_tags.php
 */

if (!defined('ABSPATH')) {
	exit;
}

$taxonomy = 'item_tag';

/**
 * Merge one term into another, preserving assignments.
 */
function wj_merge_item_tag_term(string $source_name, string $target_name, string $taxonomy): bool {
	$source = get_term_by('name', $source_name, $taxonomy);
	if (!$source instanceof WP_Term) {
		echo 'Missing source term: ' . $source_name . PHP_EOL;
		return false;
	}

	$target = get_term_by('name', $target_name, $taxonomy);
	if (!$target instanceof WP_Term) {
		$result = wp_insert_term($target_name, $taxonomy);
		if (is_wp_error($result)) {
			echo 'Could not create target term: ' . $target_name . ' | ' . $result->get_error_message() . PHP_EOL;
			return false;
		}

		$target = get_term((int) $result['term_id'], $taxonomy);
	}

	if (!$target instanceof WP_Term) {
		return false;
	}

	$object_ids = get_objects_in_term($source->term_id, $taxonomy);
	if (is_wp_error($object_ids)) {
		echo 'Could not read objects for term: ' . $source_name . PHP_EOL;
		return false;
	}

	foreach ($object_ids as $object_id) {
		$current = wp_get_object_terms((int) $object_id, $taxonomy, ['fields' => 'names']);
		if (is_wp_error($current)) {
			continue;
		}

		$current = array_values(array_unique(array_filter($current)));
		$current = array_values(array_diff($current, [$source->name]));
		$current[] = $target->name;
		wp_set_object_terms((int) $object_id, array_values(array_unique($current)), $taxonomy, false);
	}

	$deleted = wp_delete_term($source->term_id, $taxonomy);
	if (false === $deleted || is_wp_error($deleted)) {
		echo 'Could not delete source term: ' . $source_name . PHP_EOL;
		return false;
	}

	echo 'Merged term: ' . $source_name . ' -> ' . $target->name . PHP_EOL;
	return true;
}

$merge_map = [
	'Stand-up'   => 'stand-up comedy',
	'CD Release' => 'record release party',
];

$rename_map = [
	'Adolescence'       => 'adolescence',
	'Anthropology'      => 'anthropology',
	'Archaeology'       => 'archaeology',
	'Broadway'          => 'broadway',
	'Biblical archaeology' => 'biblical archaeology',
	'Blues'             => 'blues',
	'Comedy'            => 'comedy',
	'Coming-of-age'     => 'coming-of-age',
	'Convocation'       => 'convocation',
	'DJ'                => 'dj',
	'Dance'             => 'dance',
	'Dub'               => 'dub',
	'Electronic music'  => 'electronic music',
	'Ethnography'       => 'ethnography',
	'Ethology'          => 'ethology',
	'Folklore'          => 'folklore',
	'Football'          => 'football',
	'Gala'              => 'gala',
	'Human evolution'   => 'human evolution',
	'Human nature'      => 'human nature',
	'Magic'             => 'magic',
	'Musical'           => 'musical',
	'Opera'             => 'opera',
	'Orchestra'         => 'orchestra',
	'Outdoor Music'     => 'outdoor music',
	'Performance'       => 'performance',
	'Play'              => 'play',
	'Primatology'       => 'primatology',
	'Quintet'           => 'quintet',
	'Religion'          => 'religion',
	'Science'           => 'science',
	'Singer-songwriter' => 'singer-songwriter',
	'Social psychology' => 'social psychology',
	'Sports'            => 'sports',
	'Trio'              => 'trio',
	'Violinist'         => 'violinist',
	'Vocal jazz'        => 'vocal jazz',
	'World music'       => 'world music',
];

$merged = 0;
$renamed = 0;
$decoded = 0;
$deleted = 0;

foreach ($merge_map as $source_name => $target_name) {
	if (wj_merge_item_tag_term($source_name, $target_name, $taxonomy)) {
		$merged++;
	}
}

foreach ($rename_map as $source_name => $target_name) {
	$term = get_term_by('name', $source_name, $taxonomy);
	if (!$term instanceof WP_Term) {
		continue;
	}

	$existing = get_term_by('name', $target_name, $taxonomy);
	if ($existing instanceof WP_Term && (int) $existing->term_id !== (int) $term->term_id) {
		if (wj_merge_item_tag_term($source_name, $target_name, $taxonomy)) {
			$merged++;
		}
		continue;
	}

	$result = wp_update_term($term->term_id, $taxonomy, ['name' => $target_name]);
	if (is_wp_error($result)) {
		echo 'Could not rename term: ' . $source_name . ' | ' . $result->get_error_message() . PHP_EOL;
		continue;
	}

	$renamed++;
	echo 'Renamed term: ' . $source_name . ' -> ' . $target_name . PHP_EOL;
}

$terms = get_terms([
	'taxonomy'   => $taxonomy,
	'hide_empty' => false,
]);

foreach ($terms as $term) {
	$decoded_name = html_entity_decode($term->name, ENT_QUOTES | ENT_HTML5, 'UTF-8');
	if ($decoded_name === $term->name) {
		continue;
	}

	$existing = get_term_by('name', $decoded_name, $taxonomy);
	if ($existing instanceof WP_Term && (int) $existing->term_id !== (int) $term->term_id) {
		if (wj_merge_item_tag_term($term->name, $decoded_name, $taxonomy)) {
			$merged++;
		}
		continue;
	}

	$result = wp_update_term($term->term_id, $taxonomy, ['name' => $decoded_name]);
	if (is_wp_error($result)) {
		echo 'Could not decode term: ' . $term->name . ' | ' . $result->get_error_message() . PHP_EOL;
		continue;
	}

	$decoded++;
	echo 'Decoded term: ' . $term->name . ' -> ' . $decoded_name . PHP_EOL;
}

$terms = get_terms([
	'taxonomy'   => $taxonomy,
	'hide_empty' => false,
]);

foreach ($terms as $term) {
	if ((int) $term->count !== 0) {
		continue;
	}

	$result = wp_delete_term($term->term_id, $taxonomy);
	if (false === $result || is_wp_error($result)) {
		echo 'Could not delete zero-count term: ' . $term->name . PHP_EOL;
		continue;
	}

	$deleted++;
	echo 'Deleted zero-count term: ' . $term->name . PHP_EOL;
}

echo 'Summary: merged=' . $merged . ' renamed=' . $renamed . ' decoded=' . $decoded . ' deleted=' . $deleted . PHP_EOL;

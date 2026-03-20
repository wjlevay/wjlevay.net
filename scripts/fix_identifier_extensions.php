<?php
/**
 * Remove file extensions from collection item identifiers.
 *
 * Usage:
 * wp eval-file scripts/fix_identifier_extensions.php [--dry-run]
 */

if (!defined('ABSPATH')) {
	exit(1);
}

$dry_run = in_array('--dry-run', $_SERVER['argv'] ?? [], true);
$all = get_posts(
	[
		'post_type'      => 'collection_item',
		'post_status'    => 'any',
		'posts_per_page' => -1,
		'fields'         => 'ids',
	]
);

$updated = 0;
$matches = [];

foreach ($all as $id) {
	$value = get_post_meta($id, 'item_identifier', true);
	if (!is_string($value) || '' === $value) {
		continue;
	}

	$new = preg_replace('/\.(jpg|jpeg|png|gif|tif|tiff|pdf)$/i', '', $value);
	if ($new === $value) {
		continue;
	}

	$matches[] = [$id, $value, $new];
	if (!$dry_run) {
		update_post_meta($id, 'item_identifier', $new);
		$updated++;
	}
}

echo 'MATCHES=' . count($matches) . PHP_EOL;
foreach (array_slice($matches, 0, 25) as $row) {
	echo $row[0] . "\t" . $row[1] . "\t=>\t" . $row[2] . PHP_EOL;
}

if (!$dry_run) {
	echo 'UPDATED=' . $updated . PHP_EOL;
}

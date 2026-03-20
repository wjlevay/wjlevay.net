<?php
/**
 * Force-sync Yoast title settings from the theme helper.
 *
 * Usage:
 * wp eval-file scripts/sync_yoast_settings.php
 */

if (!defined('ABSPATH')) {
	exit(1);
}

if (!function_exists('wj_sync_yoast_titles_settings') && function_exists('get_stylesheet_directory')) {
	require_once get_stylesheet_directory() . '/inc/seo.php';
}

if (!function_exists('wj_sync_yoast_titles_settings')) {
	echo "WJ Yoast sync helper is not available.\n";
	exit(1);
}

wj_sync_yoast_titles_settings();

echo 'VERSION=' . (int) get_option('wj_yoast_settings_version', 0) . PHP_EOL;

$titles = get_option('wpseo_titles');
$keys = [
	'title-collection_item',
	'display-metabox-pt-collection_item',
	'noindex-collection_item',
	'title-ptarchive-collection_item',
	'title-tax-collection',
	'title-tax-agent',
	'title-tax-production',
	'title-tax-venue',
	'title-tax-location',
	'title-tax-item_tag',
];

foreach ($keys as $key) {
	echo $key . '=' . (is_array($titles) && array_key_exists($key, $titles) ? wp_json_encode($titles[$key]) : 'null') . PHP_EOL;
}

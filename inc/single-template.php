<?php
/**
 * Dedicated PHP template routing for collection items.
 */

if (!defined('ABSPATH')) {
	exit;
}

function wj_use_collection_item_php_template(string $template): string {
	if (!is_singular('collection_item')) {
		return $template;
	}

	$custom_template = get_stylesheet_directory() . '/single-collection-item.php';
	if (file_exists($custom_template)) {
		return $custom_template;
	}

	return $template;
}
add_filter('template_include', 'wj_use_collection_item_php_template', 99);

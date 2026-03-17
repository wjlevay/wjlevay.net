<?php
/**
 * Dedicated PHP templates for collection access-point taxonomies.
 *
 * @package twentytwentyfive-child
 */

if (!defined('ABSPATH')) {
	exit;
}

function wj_use_taxonomy_php_template(string $template): string {
	if (is_tax('artist')) {
		$artist_template = get_stylesheet_directory() . '/taxonomy-artist-landing.php';
		if (file_exists($artist_template)) {
			return $artist_template;
		}
	}

	if (is_tax(['venue', 'location', 'item_tag', 'collection'])) {
		$taxonomy_template = get_stylesheet_directory() . '/taxonomy-collection-access-point.php';
		if (file_exists($taxonomy_template)) {
			return $taxonomy_template;
		}
	}

	if (is_post_type_archive('collection_item')) {
		$archive_template = get_stylesheet_directory() . '/archive-collection-items.php';
		if (file_exists($archive_template)) {
			return $archive_template;
		}
	}

	return $template;
}
add_filter('template_include', 'wj_use_taxonomy_php_template', 98);

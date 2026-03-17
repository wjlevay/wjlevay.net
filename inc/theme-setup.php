<?php
/**
 * Theme setup and asset loading.
 */

if (!defined('ABSPATH')) {
	exit;
}

function wj_theme_enqueue_assets(): void {
	$theme = wp_get_theme();
	$version = $theme->get('Version');

	wp_enqueue_style(
		'twentytwentyfive-parent-style',
		get_template_directory_uri() . '/style.css',
		[],
		wp_get_theme(get_template())->get('Version')
	);

	wp_enqueue_style(
		'wj-theme-style',
		get_stylesheet_directory_uri() . '/assets/css/site.css',
		['twentytwentyfive-parent-style'],
		$version
	);

	wp_enqueue_script(
		'open-seadragon',
		'https://cdn.jsdelivr.net/npm/openseadragon@5.0.1/build/openseadragon/openseadragon.min.js',
		[],
		'5.0.1',
		true
	);

	wp_enqueue_script(
		'wj-theme-app',
		get_stylesheet_directory_uri() . '/assets/js/app.js',
		['open-seadragon'],
		$version,
		true
	);

	wp_localize_script(
		'wj-theme-app',
		'wjTheme',
		[
			'archiveUrl' => get_post_type_archive_link('collection_item'),
			'viewerTileSource' => [
				'type' => 'image',
			],
		]
	);
}
add_action('wp_enqueue_scripts', 'wj_theme_enqueue_assets');

function wj_theme_setup(): void {
	add_theme_support('post-thumbnails');
	add_image_size('wj-card', 960, 960, false);
	add_image_size('wj-thumb', 320, 320, true);
}
add_action('after_setup_theme', 'wj_theme_setup');

function wj_flush_rewrite_rules_on_switch(): void {
	wj_register_content_model();
	flush_rewrite_rules();
}
add_action('after_switch_theme', 'wj_flush_rewrite_rules_on_switch');

function wj_ensure_collections_page(): void {
	$page = get_page_by_path('collections', OBJECT, 'page');
	if ($page instanceof WP_Post) {
		if ('' === trim((string) $page->post_content)) {
			wp_update_post(
				[
					'ID'           => $page->ID,
					'post_content' => wj_get_default_collections_page_content(),
				]
			);
		}

		if ('publish' !== $page->post_status) {
			wp_update_post(
				[
					'ID'          => $page->ID,
					'post_status' => 'publish',
				]
			);
		}
		return;
	}

	wp_insert_post(
		[
			'post_type'   => 'page',
			'post_status' => 'publish',
			'post_title'  => __('Collections', 'twentytwentyfive-child'),
			'post_name'   => 'collections',
			'post_content'=> wj_get_default_collections_page_content(),
		]
	);
}
add_action('init', 'wj_ensure_collections_page', 30);

function wj_get_default_collections_page_content(): string {
	return <<<HTML
<!-- wp:group {"className":"wj-page-intro wj-page-intro--collections","layout":{"type":"constrained"}} -->
<div class="wp-block-group wj-page-intro wj-page-intro--collections">
<!-- wp:heading {"level":1} -->
<h1 class="wp-block-heading">Collections</h1>
<!-- /wp:heading -->

<!-- wp:group {"className":"wj-page-intro-copy","layout":{"type":"constrained"}} -->
<div class="wp-block-group wj-page-intro-copy">
<!-- wp:paragraph -->
<p>Material culture from concerts, tours, and fandom. Browse top-level collections and enter each one to explore items and shared access points.</p>
<!-- /wp:paragraph -->
</div>
<!-- /wp:group -->
</div>
<!-- /wp:group -->

<!-- wp:shortcode -->
[collections_index]
<!-- /wp:shortcode -->
HTML;
}

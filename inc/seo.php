<?php
/**
 * Supplemental SEO and structured-data helpers.
 *
 * @package twentytwentyfive-child
 */

if (!defined('ABSPATH')) {
	exit;
}

const WJ_YOAST_SETTINGS_VERSION = 1;

function wj_sync_yoast_titles_settings(): void {
	$stored_version = (int) get_option('wj_yoast_settings_version', 0);
	if ($stored_version >= WJ_YOAST_SETTINGS_VERSION) {
		return;
	}

	$titles = get_option('wpseo_titles');
	if (!is_array($titles)) {
		return;
	}

	$defaults = [
		'title-collection_item'               => '%%title%% %%page%% %%sep%% %%sitename%%',
		'metadesc-collection_item'            => '',
		'noindex-collection_item'             => false,
		'display-metabox-pt-collection_item'  => true,
		'schema-page-type-collection_item'    => 'WebPage',
		'schema-article-type-collection_item' => 'None',
		'title-ptarchive-collection_item'     => 'All Items %%page%% %%sep%% %%sitename%%',
		'metadesc-ptarchive-collection_item'  => '',
		'noindex-ptarchive-collection_item'   => false,
	];

	$taxonomies = ['collection', 'agent', 'production', 'venue', 'location', 'item_tag'];
	foreach ($taxonomies as $taxonomy) {
		$defaults["title-tax-{$taxonomy}"] = '%%term_title%% %%page%% %%sep%% %%sitename%%';
		$defaults["metadesc-tax-{$taxonomy}"] = '';
		$defaults["display-metabox-tax-{$taxonomy}"] = true;
		$defaults["noindex-tax-{$taxonomy}"] = false;
		$defaults["social-title-tax-{$taxonomy}"] = '%%term_title%%';
		$defaults["social-description-tax-{$taxonomy}"] = '';
		$defaults["social-image-url-tax-{$taxonomy}"] = '';
		$defaults["social-image-id-tax-{$taxonomy}"] = 0;
	}

	$updated = false;
	foreach ($defaults as $key => $value) {
		if (!array_key_exists($key, $titles) || $titles[$key] !== $value) {
			$titles[$key] = $value;
			$updated = true;
		}
	}

	if ($updated) {
		update_option('wpseo_titles', $titles, false);
	}

	update_option('wj_yoast_settings_version', WJ_YOAST_SETTINGS_VERSION, false);
}
add_action('init', 'wj_sync_yoast_titles_settings', 60);

function wj_get_item_schema_type(int $post_id): string {
	$publisher = (string) get_post_meta($post_id, 'item_publisher', true);
	if ('' !== trim($publisher)) {
		return 'Book';
	}

	$collections = get_the_terms($post_id, 'collection');
	if ($collections && !is_wp_error($collections)) {
		foreach ($collections as $collection) {
			if ('anthropology-paperbacks' === $collection->slug) {
				return 'Book';
			}
		}
	}

	return 'CreativeWork';
}

function wj_get_item_schema_description(int $post_id): string {
	$excerpt = trim((string) get_post_field('post_excerpt', $post_id));
	if ('' !== $excerpt) {
		return wp_strip_all_tags($excerpt);
	}

	$content = trim((string) get_post_field('post_content', $post_id));
	if ('' !== $content) {
		return wp_strip_all_tags($content);
	}

	$notes = trim((string) get_post_meta($post_id, 'item_inscription', true));
	return wp_strip_all_tags($notes);
}

function wj_get_schema_image(int $post_id): string {
	$image = get_the_post_thumbnail_url($post_id, 'full');
	return $image ? esc_url_raw($image) : '';
}

function wj_get_schema_term_names(int $post_id, string $taxonomy): array {
	$terms = get_the_terms($post_id, $taxonomy);
	if (!$terms || is_wp_error($terms)) {
		return [];
	}

	return array_values(array_filter(array_map(static fn($term) => $term->name, $terms)));
}

function wj_get_collection_term_for_post(int $post_id): ?WP_Term {
	$terms = get_the_terms($post_id, 'collection');
	if (!$terms || is_wp_error($terms)) {
		return null;
	}

	return $terms[0] instanceof WP_Term ? $terms[0] : null;
}

function wj_output_item_jsonld(): void {
	if (!is_singular('collection_item')) {
		return;
	}

	$post_id = get_queried_object_id();
	if (!$post_id) {
		return;
	}

	$type = wj_get_item_schema_type($post_id);
	$schema = [
		'@context'    => 'https://schema.org',
		'@type'       => $type,
		'@id'         => get_permalink($post_id) . '#primary',
		'url'         => get_permalink($post_id),
		'name'        => get_the_title($post_id),
		'description' => wj_get_item_schema_description($post_id),
	];

	$image = wj_get_schema_image($post_id);
	if ($image) {
		$schema['image'] = [$image];
	}

	$sort_date = (string) get_post_meta($post_id, 'item_sort_date', true);
	$display_date = (string) get_post_meta($post_id, 'item_date_display', true);
	$publisher = (string) get_post_meta($post_id, 'item_publisher', true);
	$materials = (string) get_post_meta($post_id, 'item_materials', true);
	$event_link = (string) get_post_meta($post_id, 'item_event_link', true);

	if ($sort_date) {
		if ('Book' === $type) {
			$schema['datePublished'] = $sort_date;
		} else {
			$schema['dateCreated'] = $sort_date;
		}
	}

	if ($display_date && 'Book' !== $type) {
		$schema['temporalCoverage'] = $display_date;
	}

	$subjects = wj_get_schema_term_names($post_id, 'item_tag');
	if ($subjects) {
		$schema['keywords'] = implode(', ', $subjects);
	}

	$locations = wj_get_schema_term_names($post_id, 'location');
	if ($locations) {
		$schema['contentLocation'] = array_map(
			static fn($name) => [
				'@type' => 'Place',
				'name'  => $name,
			],
			$locations
		);
	}

	$collection = wj_get_collection_term_for_post($post_id);
	if ($collection) {
		$collection_link = get_term_link($collection);
		if (!is_wp_error($collection_link)) {
			$schema['isPartOf'] = [
				'@type' => 'CollectionPage',
				'name'  => $collection->name,
				'url'   => $collection_link,
			];
		}
	}

	if ($event_link) {
		$schema['sameAs'] = [esc_url_raw($event_link)];
	}

	if ('Book' === $type) {
		$agents = wj_get_schema_term_names($post_id, 'agent');
		if ($agents) {
			$schema['author'] = array_map(
				static fn($name) => [
					'@type' => 'Person',
					'name'  => $name,
				],
				$agents
			);
		}

		if ($publisher) {
			$schema['publisher'] = [
				'@type' => 'Organization',
				'name'  => $publisher,
			];
		}

		if ($materials) {
			$schema['bookFormat'] = $materials;
		}
	} else {
		$agents = wj_get_schema_term_names($post_id, 'agent');
		if ($agents) {
			$schema['about'] = array_map(
				static fn($name) => [
					'@type' => 'Thing',
					'name'  => $name,
				],
				$agents
			);
		}
	}

	echo '<script type="application/ld+json">' . wp_json_encode($schema, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . '</script>';
}
add_action('wp_head', 'wj_output_item_jsonld', 40);

function wj_output_archive_jsonld(): void {
	$schema = null;

	if (is_post_type_archive('collection_item')) {
		$schema = [
			'@context' => 'https://schema.org',
			'@type'    => 'CollectionPage',
			'@id'      => get_post_type_archive_link('collection_item') . '#primary',
			'url'      => get_post_type_archive_link('collection_item'),
			'name'     => __('All Items', 'twentytwentyfive-child'),
		];
	} elseif (is_tax('collection')) {
		$term = get_queried_object();
		if ($term instanceof WP_Term) {
			$link = get_term_link($term);
			if (!is_wp_error($link)) {
				$schema = [
					'@context' => 'https://schema.org',
					'@type'    => 'CollectionPage',
					'@id'      => $link . '#primary',
					'url'      => $link,
					'name'     => $term->name,
				];

				if ($term->description) {
					$schema['description'] = wp_strip_all_tags($term->description);
				}
			}
		}
	}

	if (!$schema) {
		return;
	}

	echo '<script type="application/ld+json">' . wp_json_encode($schema, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . '</script>';
}
add_action('wp_head', 'wj_output_archive_jsonld', 41);

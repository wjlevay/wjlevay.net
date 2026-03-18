<?php
/**
 * One-time taxonomy schema migration and legacy URL redirects.
 */

if (!defined('ABSPATH')) {
	exit;
}

const WJ_TAXONOMY_SCHEMA_VERSION = 1;

function wj_get_production_seed_names(): array {
	return [
		'Beauty and the Beast',
		'Beetlejuice',
		'Book of Mormon',
		'Chaplin (The Musical)',
		'Death of a Salesman',
		'Disney\'s Newsies',
		'Fela!',
		'Fish in the Dark',
		'La Cage Aux Folles',
		'Opry at the Ryman',
		'Sweeney Todd',
		'The Curious Incident of the Dog in the Night-Time',
	];
}

function wj_is_production_term_name(string $name): bool {
	$normalized = strtolower(trim($name));
	$production_names = array_map(
		static fn(string $value): string => strtolower(trim($value)),
		wj_get_production_seed_names()
	);

	return in_array($normalized, $production_names, true);
}

function wj_ensure_migrated_term(WP_Term $source_term, string $target_taxonomy): ?WP_Term {
	$existing = get_term_by('slug', $source_term->slug, $target_taxonomy);
	if (!$existing) {
		$existing = get_term_by('name', $source_term->name, $target_taxonomy);
	}

	if (!$existing) {
		$result = wp_insert_term(
			$source_term->name,
			$target_taxonomy,
			[
				'slug'        => $source_term->slug,
				'description' => $source_term->description,
			]
		);

		if (is_wp_error($result)) {
			return null;
		}

		$existing = get_term($result['term_id'], $target_taxonomy);
	}

	if (!$existing instanceof WP_Term) {
		return null;
	}

	$wikidata_id = (string) get_term_meta($source_term->term_id, 'wikidata_id', true);
	if ($wikidata_id && !get_term_meta($existing->term_id, 'wikidata_id', true)) {
		update_term_meta($existing->term_id, 'wikidata_id', $wikidata_id);
	}

	if ($source_term->description && !$existing->description) {
		wp_update_term(
			$existing->term_id,
			$target_taxonomy,
			[
				'description' => $source_term->description,
			]
		);
	}

	return get_term($existing->term_id, $target_taxonomy);
}

function wj_migrate_artist_taxonomy_schema(): void {
	$stored_version = (int) get_option('wj_taxonomy_schema_version', 0);
	if ($stored_version >= WJ_TAXONOMY_SCHEMA_VERSION) {
		return;
	}

	if (!taxonomy_exists('agent') || !taxonomy_exists('production')) {
		return;
	}

	$legacy_terms = get_terms(
		[
			'taxonomy'   => 'artist',
			'hide_empty' => false,
		]
	);

	if (is_wp_error($legacy_terms)) {
		return;
	}

	$term_targets = [];
	foreach ($legacy_terms as $legacy_term) {
		if (!$legacy_term instanceof WP_Term) {
			continue;
		}

		$target_taxonomy = wj_is_production_term_name($legacy_term->name) ? 'production' : 'agent';
		$target_term = wj_ensure_migrated_term($legacy_term, $target_taxonomy);
		if ($target_term instanceof WP_Term) {
			$term_targets[$legacy_term->term_id] = [
				'taxonomy' => $target_taxonomy,
				'name'     => $target_term->name,
			];
		}
	}

	$post_ids = get_posts(
		[
			'post_type'      => 'collection_item',
			'post_status'    => 'any',
			'posts_per_page' => -1,
			'fields'         => 'ids',
		]
	);

	foreach ($post_ids as $post_id) {
		$legacy_post_terms = wp_get_post_terms($post_id, 'artist');
		if (is_wp_error($legacy_post_terms)) {
			continue;
		}

		$agent_terms = wp_get_post_terms($post_id, 'agent', ['fields' => 'names']);
		$production_terms = wp_get_post_terms($post_id, 'production', ['fields' => 'names']);

		$agent_terms = is_wp_error($agent_terms) ? [] : $agent_terms;
		$production_terms = is_wp_error($production_terms) ? [] : $production_terms;

		foreach ($legacy_post_terms as $legacy_term) {
			$target = $term_targets[$legacy_term->term_id] ?? null;
			if (!$target) {
				continue;
			}

			if ('production' === $target['taxonomy']) {
				$production_terms[] = $target['name'];
			} else {
				$agent_terms[] = $target['name'];
			}
		}

		$agent_terms = array_values(array_unique(array_filter($agent_terms)));
		$production_terms = array_values(array_unique(array_filter($production_terms)));

		if ($agent_terms) {
			wp_set_object_terms($post_id, $agent_terms, 'agent', false);
		}

		if ($production_terms) {
			wp_set_object_terms($post_id, $production_terms, 'production', false);
		}
	}

	update_option('wj_taxonomy_schema_version', WJ_TAXONOMY_SCHEMA_VERSION, false);
}
add_action('init', 'wj_migrate_artist_taxonomy_schema', 40);

function wj_redirect_legacy_artist_urls(): void {
	if (is_admin()) {
		return;
	}

	$request_uri = isset($_SERVER['REQUEST_URI']) ? (string) $_SERVER['REQUEST_URI'] : '';
	$path = trim((string) wp_parse_url($request_uri, PHP_URL_PATH), '/');
	if (!preg_match('#^artist/([^/]+)/?$#', $path, $matches)) {
		return;
	}

	$term = get_term_by('slug', sanitize_title($matches[1]), 'agent');
	if (!$term instanceof WP_Term) {
		return;
	}

	$link = get_term_link($term);
	if (is_wp_error($link)) {
		return;
	}

	wp_safe_redirect($link, 301);
	exit;
}
add_action('template_redirect', 'wj_redirect_legacy_artist_urls', 1);

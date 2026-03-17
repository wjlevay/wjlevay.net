<?php
/**
 * Linked-data helpers for artist vocabularies.
 */

if (!defined('ABSPATH')) {
	exit;
}

function wj_get_wikidata_entity_for_term(WP_Term $term): array {
	$wikidata_id = get_term_meta($term->term_id, 'wikidata_id', true);
	if (!$wikidata_id) {
		$wikidata_id = wj_lookup_wikidata_id_for_term($term);
	}

	if (!$wikidata_id) {
		return [];
	}

	$cache_key = 'wj_wikidata_' . $wikidata_id;
	$cached = get_transient($cache_key);
	if (is_array($cached)) {
		return $cached;
	}

	$response = wp_remote_get(
		sprintf('https://www.wikidata.org/wiki/Special:EntityData/%s.json', rawurlencode($wikidata_id)),
		[
			'timeout' => 10,
			'headers' => [
				'Accept' => 'application/json',
			],
		]
	);

	if (is_wp_error($response) || 200 !== wp_remote_retrieve_response_code($response)) {
		return [];
	}

	$body = json_decode(wp_remote_retrieve_body($response), true);
	$entity = $body['entities'][$wikidata_id] ?? [];
	if (!$entity) {
		return [];
	}

	$payload = [
		'id'          => $wikidata_id,
		'label'       => $entity['labels']['en']['value'] ?? $term->name,
		'description' => $entity['descriptions']['en']['value'] ?? '',
		'wikipedia'   => $entity['sitelinks']['enwiki']['title'] ?? '',
		'image'       => wj_get_wikidata_commons_image($entity),
	];

	set_transient($cache_key, $payload, DAY_IN_SECONDS);

	return $payload;
}

function wj_lookup_wikidata_id_for_term(WP_Term $term): string {
	$cache_key = 'wj_wikidata_lookup_' . md5($term->taxonomy . ':' . $term->name);
	$cached = get_transient($cache_key);
	if (is_string($cached)) {
		return $cached;
	}

	$response = wp_remote_get(
		add_query_arg(
			[
				'action'   => 'wbsearchentities',
				'search'   => $term->name,
				'language' => 'en',
				'format'   => 'json',
				'limit'    => 5,
				'type'     => 'item',
			],
			'https://www.wikidata.org/w/api.php'
		),
		[
			'timeout' => 10,
			'headers' => [
				'Accept' => 'application/json',
			],
		]
	);

	if (is_wp_error($response) || 200 !== wp_remote_retrieve_response_code($response)) {
		return '';
	}

	$body = json_decode(wp_remote_retrieve_body($response), true);
	$results = $body['search'] ?? [];
	if (!$results) {
		return '';
	}

	$needle = strtolower(trim($term->name));
	$match = '';

	foreach ($results as $result) {
		$label = strtolower(trim((string) ($result['label'] ?? '')));
		if ($label === $needle) {
			$match = (string) ($result['id'] ?? '');
			break;
		}
	}

	if (!$match) {
		$match = (string) ($results[0]['id'] ?? '');
	}

	if ($match) {
		set_transient($cache_key, $match, WEEK_IN_SECONDS);
	}

	return $match;
}

function wj_get_wikidata_commons_image(array $entity): string {
	$filename = $entity['claims']['P18'][0]['mainsnak']['datavalue']['value'] ?? '';
	if (!$filename || !is_string($filename)) {
		return '';
	}

	return 'https://commons.wikimedia.org/wiki/Special:FilePath/' . rawurlencode($filename);
}

function wj_render_wikidata_card(array $entity): string {
	if (!$entity) {
		return '';
	}

	$links = [];
	$links[] = sprintf(
		'<a href="%s" target="_blank" rel="noreferrer noopener">%s</a>',
		esc_url('https://www.wikidata.org/wiki/' . $entity['id']),
		esc_html__('Wikidata', 'twentytwentyfive-child')
	);

	if (!empty($entity['wikipedia'])) {
		$links[] = sprintf(
			'<a href="%s" target="_blank" rel="noreferrer noopener">%s</a>',
			esc_url('https://en.wikipedia.org/wiki/' . rawurlencode(str_replace(' ', '_', $entity['wikipedia']))),
			esc_html__('Wikipedia', 'twentytwentyfive-child')
		);
	}

	$image = '';
	if (!empty($entity['image'])) {
		$image = sprintf(
			'<div class="wj-wikidata-card-media"><img src="%s" alt="%s" loading="lazy"></div>',
			esc_url($entity['image']),
			esc_attr($entity['label'])
		);
	}

	return sprintf(
		'<aside class="wj-wikidata-card">%s<div class="wj-wikidata-card-copy"><p class="wj-eyebrow">%s</p><h2>%s</h2><p>%s</p><div class="wj-cross-links">%s</div></div></aside>',
		$image,
		esc_html__('Linked Data', 'twentytwentyfive-child'),
		esc_html($entity['label']),
		esc_html($entity['description']),
		wp_kses_post(implode('', $links))
	);
}

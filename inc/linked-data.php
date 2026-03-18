<?php
/**
 * Linked-data helpers for agent vocabularies.
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
		'facts'       => wj_get_wikidata_fact_rows($entity),
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

function wj_get_wikidata_claim_values(array $entity, string $property): array {
	$claims = $entity['claims'][$property] ?? [];
	if (!is_array($claims)) {
		return [];
	}

	$values = [];
	foreach ($claims as $claim) {
		$value = $claim['mainsnak']['datavalue']['value'] ?? null;
		if (null === $value) {
			continue;
		}
		$values[] = $value;
	}

	return $values;
}

function wj_get_wikidata_entity_ids_for_property(array $entity, string $property): array {
	$ids = [];
	foreach (wj_get_wikidata_claim_values($entity, $property) as $value) {
		if (is_array($value) && isset($value['id']) && is_string($value['id'])) {
			$ids[] = $value['id'];
		}
	}

	return array_values(array_unique($ids));
}

function wj_get_wikidata_labels(array $ids): array {
	$ids = array_values(array_unique(array_filter($ids)));
	if (!$ids) {
		return [];
	}

	$cache_key = 'wj_wikidata_labels_' . md5(implode('|', $ids));
	$cached = get_transient($cache_key);
	if (is_array($cached)) {
		return $cached;
	}

	$response = wp_remote_get(
		add_query_arg(
			[
				'action'   => 'wbgetentities',
				'ids'      => implode('|', $ids),
				'props'    => 'labels',
				'languages'=> 'en',
				'format'   => 'json',
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
		return [];
	}

	$body = json_decode(wp_remote_retrieve_body($response), true);
	$entities = $body['entities'] ?? [];
	$labels = [];

	foreach ($ids as $id) {
		$labels[$id] = $entities[$id]['labels']['en']['value'] ?? $id;
	}

	set_transient($cache_key, $labels, WEEK_IN_SECONDS);

	return $labels;
}

function wj_get_wikidata_labels_for_property(array $entity, string $property, int $limit = 3): array {
	$ids = array_slice(wj_get_wikidata_entity_ids_for_property($entity, $property), 0, $limit);
	if (!$ids) {
		return [];
	}

	return array_values(wj_get_wikidata_labels($ids));
}

function wj_get_wikidata_first_string_claim(array $entity, string $property): string {
	foreach (wj_get_wikidata_claim_values($entity, $property) as $value) {
		if (is_string($value) && '' !== trim($value)) {
			return trim($value);
		}
	}

	return '';
}

function wj_get_wikidata_first_year_claim(array $entity, string $property): string {
	foreach (wj_get_wikidata_claim_values($entity, $property) as $value) {
		$time = is_array($value) ? (string) ($value['time'] ?? '') : '';
		if (preg_match('/([+-]\d{4})-/', $time, $matches)) {
			return ltrim($matches[1], '+');
		}
	}

	return '';
}

function wj_get_wikidata_fact_rows(array $entity): array {
	$types = wj_get_wikidata_labels_for_property($entity, 'P31', 2);
	$genres = wj_get_wikidata_labels_for_property($entity, 'P136', 3);
	$occupations = wj_get_wikidata_labels_for_property($entity, 'P106', 3);
	$sports = wj_get_wikidata_labels_for_property($entity, 'P641', 2);

	$origin = wj_get_wikidata_labels_for_property($entity, 'P740', 1);
	if (!$origin) {
		$origin = wj_get_wikidata_labels_for_property($entity, 'P495', 1);
	}
	if (!$origin) {
		$origin = wj_get_wikidata_labels_for_property($entity, 'P19', 1);
	}
	if (!$origin) {
		$origin = wj_get_wikidata_labels_for_property($entity, 'P27', 1);
	}

	$start_year = wj_get_wikidata_first_year_claim($entity, 'P2031');
	if (!$start_year) {
		$start_year = wj_get_wikidata_first_year_claim($entity, 'P571');
	}
	if (!$start_year) {
		$start_year = wj_get_wikidata_first_year_claim($entity, 'P569');
	}

	$end_year = wj_get_wikidata_first_year_claim($entity, 'P2032');
	if (!$end_year) {
		$end_year = wj_get_wikidata_first_year_claim($entity, 'P576');
	}
	if (!$end_year) {
		$end_year = wj_get_wikidata_first_year_claim($entity, 'P570');
	}

	$active_years = '';
	if ($start_year && $end_year) {
		$active_years = $start_year . ' to ' . $end_year;
	} elseif ($start_year) {
		$active_years = $start_year . ' to present';
	}

	$discipline = $genres ?: $occupations;
	if (!$discipline) {
		$discipline = $sports;
	}

	$facts = [];
	if ($types) {
		$facts[] = ['label' => __('Type', 'twentytwentyfive-child'), 'value' => implode(', ', $types)];
	}
	if ($origin) {
		$facts[] = ['label' => __('Origin', 'twentytwentyfive-child'), 'value' => implode(', ', $origin)];
	}
	if ($active_years) {
		$facts[] = ['label' => __('Active', 'twentytwentyfive-child'), 'value' => $active_years];
	}
	if ($discipline) {
		$facts[] = ['label' => __('Discipline', 'twentytwentyfive-child'), 'value' => implode(', ', $discipline)];
	}

	$website = wj_get_wikidata_first_string_claim($entity, 'P856');
	if ($website) {
		$facts[] = ['label' => __('Website', 'twentytwentyfive-child'), 'url' => $website, 'value' => preg_replace('#^https?://#', '', $website)];
	}

	return $facts;
}

function wj_get_term_avatar_markup(WP_Term $term, string $class = 'wj-agent-avatar'): string {
	$image = '';
	if ('agent' === $term->taxonomy) {
		$entity = wj_get_wikidata_entity_for_term($term);
		$image = (string) ($entity['image'] ?? '');
	}

	if ($image) {
		return sprintf(
			'<span class="%1$s"><img src="%2$s" alt="%3$s" loading="lazy"></span>',
			esc_attr($class),
			esc_url($image),
			esc_attr($term->name)
		);
	}

	$words = preg_split('/\s+/', trim($term->name)) ?: [];
	$initials = '';
	foreach (array_slice($words, 0, 2) as $word) {
		$initials .= mb_strtoupper(mb_substr($word, 0, 1));
	}
	$initials = $initials ?: mb_strtoupper(mb_substr($term->name, 0, 1));

	return sprintf(
		'<span class="%1$s %1$s--fallback" aria-hidden="true">%2$s</span>',
		esc_attr($class),
		esc_html($initials)
	);
}

function wj_get_agent_collection_context(WP_Term $term): array {
	$post_ids = get_objects_in_term($term->term_id, 'agent');
	if (is_wp_error($post_ids) || !$post_ids) {
		return [
			'collections'   => [],
			'venues'        => [],
			'locations'     => [],
			'related'       => [],
			'productions'   => [],
			'earliest_year' => '',
			'latest_year'   => '',
		];
	}

	$collections = [];
	$venues = [];
	$locations = [];
	$related = [];
	$productions = [];
	$earliest = '';
	$latest = '';

	foreach (array_unique(array_map('intval', $post_ids)) as $post_id) {
		$sort_date = (string) get_post_meta($post_id, 'item_sort_date', true);
		if ($sort_date) {
			if (!$earliest || $sort_date < $earliest) {
				$earliest = $sort_date;
			}
			if (!$latest || $sort_date > $latest) {
				$latest = $sort_date;
			}
		}

		foreach (['collection' => &$collections, 'venue' => &$venues, 'location' => &$locations, 'production' => &$productions] as $taxonomy => &$bucket) {
			$terms = get_the_terms($post_id, $taxonomy);
			if (!$terms || is_wp_error($terms)) {
				continue;
			}
			foreach ($terms as $item_term) {
				if (!isset($bucket[$item_term->term_id])) {
					$bucket[$item_term->term_id] = ['term' => $item_term, 'count' => 0];
				}
				$bucket[$item_term->term_id]['count']++;
			}
		}
		unset($bucket);

		$agents = get_the_terms($post_id, 'agent');
		if ($agents && !is_wp_error($agents)) {
			foreach ($agents as $agent) {
				if ((int) $agent->term_id === (int) $term->term_id) {
					continue;
				}
				if (!isset($related[$agent->term_id])) {
					$related[$agent->term_id] = ['term' => $agent, 'count' => 0];
				}
				$related[$agent->term_id]['count']++;
			}
		}
	}

	foreach ([$collections, $venues, $locations, $related, $productions] as &$bucket) {
		uasort(
			$bucket,
			static function (array $left, array $right): int {
				if ($left['count'] === $right['count']) {
					return strcmp($left['term']->name, $right['term']->name);
				}
				return $right['count'] <=> $left['count'];
			}
		);
	}

	return [
		'collections'   => array_slice($collections, 0, 6, true),
		'venues'        => array_slice($venues, 0, 6, true),
		'locations'     => array_slice($locations, 0, 6, true),
		'related'       => array_slice($related, 0, 6, true),
		'productions'   => array_slice($productions, 0, 6, true),
		'earliest_year' => $earliest ? substr($earliest, 0, 4) : '',
		'latest_year'   => $latest ? substr($latest, 0, 4) : '',
	];
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

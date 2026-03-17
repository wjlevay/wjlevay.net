<?php
/**
 * WP-CLI importer, exporter, and migrator for collection items.
 */

if (!defined('ABSPATH')) {
	exit;
}

if (defined('WP_CLI') && WP_CLI) {
	/**
	 * Imports collection items from CSV and migrates legacy content.
	 */
	class WJ_Collections_Importer_Command {
		private const CSV_HEADERS = [
			'title',
			'content',
			'excerpt',
			'collection',
			'artists',
			'venue',
			'location',
			'subjects',
			'item_identifier',
			'item_year',
			'item_date_display',
			'item_condition',
			'item_materials',
			'item_dimensions',
			'item_rights',
			'item_source',
			'item_inscription',
			'item_event_link',
			'item_dropbox_path',
			'featured_image',
			'gallery_images',
		];

		private const OMEKA_COLLECTION_MAP = [
			'ticket stubs & flyers' => 'Ticket Stubs & Flyers',
			't-shirts'              => 'T-Shirts',
			't shirts'              => 'T-Shirts',
			'tshirts'               => 'T-Shirts',
		];

		/**
		 * Imports a CSV file into collection items.
		 *
		 * ## OPTIONS
		 *
		 * <file>
		 * : Absolute or relative path to a CSV file.
		 *
		 * [--images-base=<path>]
		 * : Optional base path used to resolve featured_image and gallery_images columns.
		 *
		 * ## EXAMPLES
		 *
		 *     wp wj import-items docs/sample-import.csv --images-base=/mnt/c/Users/you/Dropbox/archive
		 *
		 * @when after_wp_load
		 */
		public function import_items(array $args, array $assoc_args): void {
			$file = $args[0] ?? '';
			$path = realpath($file) ?: $file;

			if (!$path || !file_exists($path)) {
				WP_CLI::error('CSV file not found.');
			}

			$images_base = $assoc_args['images-base'] ?? '';
			$handle = fopen($path, 'r');
			if (!$handle) {
				WP_CLI::error('Could not open CSV file.');
			}

			$headers = fgetcsv($handle);
			if (!$headers) {
				fclose($handle);
				WP_CLI::error('CSV file is empty.');
			}

			$headers = array_map('trim', $headers);
			$count = 0;
			$skipped = 0;

			while (($row = fgetcsv($handle)) !== false) {
				$data = array_combine($headers, $row);
				if (!$data || empty($data['title'])) {
					continue;
				}

				$existing_post_id = $this->find_existing_item($data);
				if ($existing_post_id) {
					WP_CLI::log(sprintf('Skipping existing item %d: %s', $existing_post_id, $data['title']));
					$skipped++;
					continue;
				}

				$post_id = wp_insert_post(
					[
						'post_type'    => 'collection_item',
						'post_status'  => 'publish',
						'post_title'   => sanitize_text_field($data['title']),
						'post_content' => wp_kses_post($data['content'] ?? ''),
						'post_excerpt' => sanitize_text_field($data['excerpt'] ?? ''),
					]
				);

				if (is_wp_error($post_id)) {
					WP_CLI::warning('Skipping row due to post insert failure: ' . $data['title']);
					continue;
				}

				$this->apply_item_data($post_id, $data, $images_base);
				$count++;
			}

			fclose($handle);
			WP_CLI::success(sprintf('Imported %d collection items. Skipped %d existing items.', $count, $skipped));
		}

		/**
		 * Imports an Omeka CSV export directly.
		 *
		 * ## OPTIONS
		 *
		 * <file>
		 * : Absolute or relative path to an Omeka export CSV.
		 *
		 * ## EXAMPLES
		 *
		 *     wp wj import-omeka /mnt/c/Users/you/Downloads/export.csv
		 *
		 * @when after_wp_load
		 */
		public function import_omeka(array $args, array $assoc_args): void {
			$file = $args[0] ?? '';
			$path = realpath($file) ?: $file;

			if (!$path || !file_exists($path)) {
				WP_CLI::error('Omeka CSV file not found.');
			}

			$handle = fopen($path, 'r');
			if (!$handle) {
				WP_CLI::error('Could not open Omeka CSV file.');
			}

			$headers = fgetcsv($handle);
			if (!$headers) {
				fclose($handle);
				WP_CLI::error('Omeka CSV file is empty.');
			}

			$headers = array_map('trim', $headers);
			$count = 0;
			$skipped = 0;

			while (($row = fgetcsv($handle)) !== false) {
				$raw = array_combine($headers, $row);
				if (!$raw) {
					continue;
				}

				$data = $this->map_omeka_row($raw);
				if (empty($data['title'])) {
					continue;
				}

				$existing_post_id = $this->find_existing_item($data);
				if ($existing_post_id) {
					WP_CLI::log(sprintf('Skipping existing item %d: %s', $existing_post_id, $data['title']));
					$skipped++;
					continue;
				}

				$post_id = wp_insert_post(
					[
						'post_type'    => 'collection_item',
						'post_status'  => !empty($raw['public']) && '1' === (string) $raw['public'] ? 'publish' : 'draft',
						'post_title'   => sanitize_text_field($data['title']),
						'post_content' => wp_kses_post($data['content'] ?? ''),
						'post_excerpt' => sanitize_text_field($data['excerpt'] ?? ''),
					]
				);

				if (is_wp_error($post_id)) {
					WP_CLI::warning('Skipping Omeka row due to post insert failure: ' . ($data['title'] ?? '[untitled]'));
					continue;
				}

				$this->apply_item_data($post_id, $data, '');
				$count++;
			}

			fclose($handle);
			WP_CLI::success(sprintf('Imported %d Omeka items. Skipped %d existing items.', $count, $skipped));
		}

		/**
		 * Writes an empty CSV template with the expected headers.
		 *
		 * ## OPTIONS
		 *
		 * <file>
		 * : Destination CSV path.
		 *
		 * [--with-sample]
		 * : Include one sample row.
		 *
		 * ## EXAMPLES
		 *
		 *     wp wj make-import-template /tmp/wj-import.csv --with-sample
		 *
		 * @when after_wp_load
		 */
		public function make_import_template(array $args, array $assoc_args): void {
			$file = $args[0] ?? '';
			if (!$file) {
				WP_CLI::error('Destination CSV path is required.');
			}

			$handle = fopen($file, 'w');
			if (!$handle) {
				WP_CLI::error('Could not open destination file for writing.');
			}

			fputcsv($handle, self::CSV_HEADERS);

			if (isset($assoc_args['with-sample'])) {
				fputcsv(
					$handle,
					[
						'Nine Inch Nails Tour Shirt',
						'Black tour shirt with front and back print.',
						'1994 shirt from the Self Destruct era.',
						'T-Shirts',
						'Nine Inch Nails',
						'',
						'',
						'tour merch|screenprint',
						'shirt-0001',
						'1994',
						'1994',
						'Very good',
						'Cotton',
						'L',
						'Personal collection',
						'Dropbox import',
						'',
						'https://www.setlist.fm/',
						'/Collections/T-Shirts/NIN/front.jpg',
						'/Collections/T-Shirts/NIN/front.jpg',
						'/Collections/T-Shirts/NIN/back.jpg',
					]
				);
			}

			fclose($handle);
			WP_CLI::success('Import template written to ' . $file);
		}

		/**
		 * Migrates legacy ticket stubs and t-shirts into collection items.
		 *
		 * ## OPTIONS
		 *
		 * [--dry-run]
		 * : Show what would be migrated without writing changes.
		 *
		 * [--keep-legacy]
		 * : Leave legacy posts published instead of setting them to draft.
		 *
		 * ## EXAMPLES
		 *
		 *     wp wj migrate-legacy --dry-run
		 *     wp wj migrate-legacy
		 *
		 * @when after_wp_load
		 */
		public function migrate_legacy(array $args, array $assoc_args): void {
			$dry_run = isset($assoc_args['dry-run']);
			$keep_legacy = isset($assoc_args['keep-legacy']);

			$legacy_posts = get_posts(
				[
					'post_type'      => ['ticket_stub', 't_shirt'],
					'post_status'    => ['publish', 'draft', 'pending', 'private'],
					'posts_per_page' => -1,
					'orderby'        => 'date',
					'order'          => 'ASC',
				]
			);

			if (!$legacy_posts) {
				WP_CLI::success('No legacy ticket stubs or t-shirts were found.');
				return;
			}

			$migrated = 0;

			foreach ($legacy_posts as $legacy_post) {
				$mapped = $this->map_legacy_post($legacy_post);

				if ($dry_run) {
					WP_CLI::log(sprintf('Would migrate %s (%d) to collection "%s".', $legacy_post->post_title, $legacy_post->ID, $mapped['collection']));
					continue;
				}

				$existing = get_posts(
					[
						'post_type'      => 'collection_item',
						'post_status'    => 'any',
						'posts_per_page' => 1,
						'meta_query'     => [
							[
								'key'   => '_wj_legacy_post_id',
								'value' => $legacy_post->ID,
							],
						],
					]
				);

				if ($existing) {
					WP_CLI::warning(sprintf('Skipping legacy post %d because it already has a migrated counterpart.', $legacy_post->ID));
					continue;
				}

				$post_id = wp_insert_post(
					[
						'post_type'    => 'collection_item',
						'post_status'  => $legacy_post->post_status,
						'post_title'   => $legacy_post->post_title,
						'post_content' => $legacy_post->post_content,
						'post_excerpt' => $legacy_post->post_excerpt,
						'post_date'    => $legacy_post->post_date,
					]
				);

				if (is_wp_error($post_id)) {
					WP_CLI::warning(sprintf('Could not migrate legacy post %d.', $legacy_post->ID));
					continue;
				}

				$this->apply_item_data($post_id, $mapped, '');
				update_post_meta($post_id, '_wj_legacy_post_id', $legacy_post->ID);
				update_post_meta($post_id, '_wj_legacy_post_type', $legacy_post->post_type);

				$thumbnail_id = get_post_thumbnail_id($legacy_post->ID);
				if ($thumbnail_id) {
					set_post_thumbnail($post_id, $thumbnail_id);
				}

				if (!$keep_legacy) {
					wp_update_post(
						[
							'ID'          => $legacy_post->ID,
							'post_status' => 'draft',
						]
					);
				}

				$migrated++;
			}

			if ($dry_run) {
				WP_CLI::success(sprintf('Dry run complete. %d legacy posts would be migrated.', count($legacy_posts)));
				return;
			}

			WP_CLI::success(sprintf('Migrated %d legacy posts.', $migrated));
		}

		private function apply_item_data(int $post_id, array $data, string $images_base): void {
			$this->assign_terms($post_id, 'collection', $data['collection'] ?? '');
			$this->assign_terms($post_id, 'artist', $data['artists'] ?? '');
			$this->assign_terms($post_id, 'venue', $data['venue'] ?? '');
			$this->assign_terms($post_id, 'location', $data['location'] ?? '');
			$this->assign_terms($post_id, 'item_tag', $data['subjects'] ?? '');

			foreach (array_keys(WJ_ITEM_META) as $meta_key) {
				if (!array_key_exists($meta_key, $data) || '' === $data[$meta_key]) {
					continue;
				}
				update_post_meta($post_id, $meta_key, wj_sanitize_meta_input($meta_key, $data[$meta_key], WJ_ITEM_META[$meta_key]));
			}

			if (!empty($data['source_uri'])) {
				update_post_meta($post_id, '_wj_source_uri', esc_url_raw((string) $data['source_uri']));
			}

			if (!empty($data['featured_image'])) {
				$attachment_id = $this->import_image($data['featured_image'], $images_base, $post_id);
				if ($attachment_id) {
					set_post_thumbnail($post_id, $attachment_id);
				}
			}

			if (!empty($data['gallery_images'])) {
				$gallery_ids = [];
				foreach (explode('|', $data['gallery_images']) as $image) {
					$attachment_id = $this->import_image($image, $images_base, $post_id);
					if ($attachment_id) {
						$gallery_ids[] = $attachment_id;
					}
				}
				if ($gallery_ids) {
					update_post_meta($post_id, 'item_gallery_ids', implode(',', $gallery_ids));
				}
			}
		}

		private function map_legacy_post(WP_Post $legacy_post): array {
			$artists = wp_get_post_terms($legacy_post->ID, 'artist', ['fields' => 'names']);
			$tags = wp_get_post_terms($legacy_post->ID, 'post_tag', ['fields' => 'names']);
			$gallery = get_post_meta($legacy_post->ID, 'gallery_ids', true);

			$mapped = [
				'title'            => $legacy_post->post_title,
				'content'          => $legacy_post->post_content,
				'excerpt'          => $legacy_post->post_excerpt,
				'collection'       => 'ticket_stub' === $legacy_post->post_type ? 'Ticket Stubs & Flyers' : 'T-Shirts',
				'artists'          => implode('|', $artists ?: []),
				'venue'            => '',
				'location'         => '',
				'subjects'         => implode('|', $tags ?: []),
				'item_identifier'  => (string) $legacy_post->ID,
				'item_year'        => '',
				'item_date_display'=> '',
				'item_condition'   => '',
				'item_materials'   => '',
				'item_dimensions'  => '',
				'item_rights'      => '',
				'item_source'      => 'Legacy migration',
				'item_inscription' => '',
				'item_event_link'  => '',
				'item_dropbox_path'=> '',
				'featured_image'   => '',
				'gallery_images'   => '',
				'item_gallery_ids' => $gallery ?: '',
			];

			if ('ticket_stub' === $legacy_post->post_type) {
				$mapped['venue'] = get_post_meta($legacy_post->ID, 'ticket_venue', true);
				$mapped['location'] = get_post_meta($legacy_post->ID, 'ticket_location', true);
				$mapped['item_date_display'] = get_post_meta($legacy_post->ID, 'ticket_date', true);
				$mapped['item_year'] = substr((string) $mapped['item_date_display'], 0, 4);
			}

			if ('t_shirt' === $legacy_post->post_type) {
				$mapped['item_year'] = get_post_meta($legacy_post->ID, 'shirt_year', true);
				$color = get_post_meta($legacy_post->ID, 'shirt_color', true);
				if ($color) {
					$subjects = array_filter(explode('|', $mapped['subjects']));
					$subjects[] = 'Color: ' . $color;
					$mapped['subjects'] = implode('|', $subjects);
				}
			}

			return $mapped;
		}

		private function map_omeka_row(array $raw): array {
			$coverage = trim((string) ($raw['Dublin Core:Coverage'] ?? ''));
			[$venue, $location] = $this->split_coverage($coverage);
			$source_uri = trim((string) ($raw['Item URI'] ?? ''));
			$item_id = trim((string) ($raw['Item Id'] ?? ''));
			$date = trim((string) ($raw['Dublin Core:Date'] ?? ''));
			$collection = $this->normalize_omeka_collection((string) ($raw['collection'] ?? ''));
			$images = $this->normalize_delimited_list((string) ($raw['file'] ?? ''), ',');
			$featured_image = $images ? array_shift($images) : '';
			$gallery_images = $images ? implode('|', $images) : '';

			return [
				'title'             => trim((string) ($raw['Dublin Core:Title'] ?? '')),
				'content'           => trim((string) ($raw['Dublin Core:Description'] ?? '')),
				'excerpt'           => '',
				'collection'        => $collection,
				'artists'           => $this->normalize_delimited_terms((string) ($raw['Dublin Core:Creator'] ?? ''), ','),
				'venue'             => $venue,
				'location'          => $location,
				'subjects'          => $this->normalize_delimited_terms((string) ($raw['tags'] ?? ''), ','),
				'item_identifier'   => $item_id,
				'item_year'         => preg_match('/^\d{4}/', $date, $matches) ? $matches[0] : '',
				'item_date_display' => $date,
				'item_condition'    => '',
				'item_materials'    => trim((string) ($raw['Item Type Metadata:Materials'] ?? '')),
				'item_dimensions'   => trim((string) ($raw['Item Type Metadata:Physical Dimensions'] ?? '')),
				'item_rights'       => trim((string) ($raw['Dublin Core:Rights'] ?? '')),
				'item_source'       => trim((string) ($raw['Dublin Core:Source'] ?? '')) ?: 'Omeka import',
				'item_inscription'  => '',
				'item_event_link'   => trim((string) ($raw['Item Type Metadata:URL'] ?? '')),
				'item_dropbox_path' => '',
				'featured_image'    => $featured_image,
				'gallery_images'    => $gallery_images,
				'source_uri'        => $source_uri,
			];
		}

		private function normalize_omeka_collection(string $collection): string {
			$normalized = strtolower(trim(html_entity_decode($collection, ENT_QUOTES | ENT_HTML5)));
			return self::OMEKA_COLLECTION_MAP[$normalized] ?? ucwords($normalized);
		}

		private function normalize_delimited_terms(string $raw, string $delimiter): string {
			$terms = $this->normalize_delimited_list($raw, $delimiter);
			return implode('|', $terms);
		}

		private function normalize_delimited_list(string $raw, string $delimiter): array {
			return array_values(
				array_filter(
					array_map(
						'trim',
						explode($delimiter, html_entity_decode($raw, ENT_QUOTES | ENT_HTML5))
					)
				)
			);
		}

		private function split_coverage(string $coverage): array {
			if ('' === $coverage) {
				return ['', ''];
			}

			$parts = array_values(array_filter(array_map('trim', explode(',', $coverage))));
			if (!$parts) {
				return ['', ''];
			}

			$venue = array_shift($parts);
			$location = implode(', ', $parts);
			return [$venue, $location];
		}

		private function find_existing_item(array $data): int {
			$meta_queries = [];

			if (!empty($data['item_identifier'])) {
				$meta_queries[] = [
					'key'   => 'item_identifier',
					'value' => (string) $data['item_identifier'],
				];
			}

			if (!empty($data['source_uri'])) {
				$meta_queries[] = [
					'key'   => '_wj_source_uri',
					'value' => esc_url_raw((string) $data['source_uri']),
				];
			}

			foreach ($meta_queries as $meta_query) {
				$existing = get_posts(
					[
						'post_type'      => 'collection_item',
						'post_status'    => 'any',
						'posts_per_page' => 1,
						'fields'         => 'ids',
						'meta_query'     => [$meta_query],
					]
				);

				if ($existing) {
					return (int) $existing[0];
				}
			}

			if (!empty($data['title'])) {
				$existing_by_title = get_posts(
					[
						'post_type'      => 'collection_item',
						'post_status'    => 'any',
						'posts_per_page' => 1,
						'fields'         => 'ids',
						'title'          => sanitize_text_field((string) $data['title']),
					]
				);

				if ($existing_by_title) {
					return (int) $existing_by_title[0];
				}
			}

			return 0;
		}

		private function assign_terms(int $post_id, string $taxonomy, string $raw): void {
			if ('' === trim($raw)) {
				return;
			}

			$terms = array_filter(array_map('trim', explode('|', $raw)));
			if ($terms) {
				wp_set_object_terms($post_id, $terms, $taxonomy, false);
			}
		}

		private function import_image(string $image, string $images_base, int $post_id): int {
			$raw_path = trim($image);
			if (preg_match('#^https?://#i', $raw_path)) {
				return $this->import_remote_image($raw_path, $post_id);
			}

			$full_path = $raw_path;

			if ($images_base && !preg_match('#^([a-zA-Z]:[\\\\/]|/)#', $raw_path)) {
				$full_path = trailingslashit($images_base) . ltrim($raw_path, '\\/');
			}

			if (!file_exists($full_path)) {
				WP_CLI::warning(sprintf('Image not found for post %d: %s', $post_id, $full_path));
				return 0;
			}

			$filename = wp_basename($full_path);
			$upload = wp_upload_bits($filename, null, file_get_contents($full_path));
			if ($upload['error']) {
				WP_CLI::warning(sprintf('Upload failed for %s: %s', $full_path, $upload['error']));
				return 0;
			}

			$filetype = wp_check_filetype($filename, null);
			$attachment_id = wp_insert_attachment(
				[
					'post_mime_type' => $filetype['type'],
					'post_title'     => preg_replace('/\.[^.]+$/', '', $filename),
					'post_status'    => 'inherit',
				],
				$upload['file'],
				$post_id
			);

			if (is_wp_error($attachment_id)) {
				return 0;
			}

			require_once ABSPATH . 'wp-admin/includes/image.php';
			$metadata = wp_generate_attachment_metadata($attachment_id, $upload['file']);
			wp_update_attachment_metadata($attachment_id, $metadata);

			return (int) $attachment_id;
		}

		private function import_remote_image(string $url, int $post_id): int {
			$response = wp_remote_get(
				$url,
				[
					'timeout' => 20,
				]
			);

			if (is_wp_error($response) || 200 !== wp_remote_retrieve_response_code($response)) {
				WP_CLI::warning(sprintf('Remote image download failed for post %d: %s', $post_id, $url));
				return 0;
			}

			$body = wp_remote_retrieve_body($response);
			if ('' === $body) {
				WP_CLI::warning(sprintf('Remote image was empty for post %d: %s', $post_id, $url));
				return 0;
			}

			$path = (string) wp_parse_url($url, PHP_URL_PATH);
			$filename = wp_basename($path) ?: ('remote-image-' . $post_id . '.jpg');
			$upload = wp_upload_bits($filename, null, $body);
			if ($upload['error']) {
				WP_CLI::warning(sprintf('Upload failed for %s: %s', $url, $upload['error']));
				return 0;
			}

			$content_type = (string) wp_remote_retrieve_header($response, 'content-type');
			$filetype = wp_check_filetype($filename, null);
			$mime_type = $filetype['type'] ?: $content_type;

			$attachment_id = wp_insert_attachment(
				[
					'post_mime_type' => $mime_type,
					'post_title'     => preg_replace('/\.[^.]+$/', '', $filename),
					'post_status'    => 'inherit',
				],
				$upload['file'],
				$post_id
			);

			if (is_wp_error($attachment_id)) {
				return 0;
			}

			require_once ABSPATH . 'wp-admin/includes/image.php';
			$metadata = wp_generate_attachment_metadata($attachment_id, $upload['file']);
			wp_update_attachment_metadata($attachment_id, $metadata);
			update_post_meta($attachment_id, '_wj_source_image_url', esc_url_raw($url));

			return (int) $attachment_id;
		}
	}

	WP_CLI::add_command('wj', 'WJ_Collections_Importer_Command');
}

# Collections ingest

The theme now includes three WP-CLI commands:

```bash
wp wj make-import-template /tmp/wj-import.csv --with-sample
wp wj import-items docs/sample-import.csv --images-base=/absolute/path/to/Dropbox
wp wj import-omeka /path/to/omeka-export.csv
wp wj migrate-legacy --dry-run
```

There is also a Dropbox review-stage script for raw shared folders:

```bash
python3 scripts/build_dropbox_review.py \
  --dropbox-url "https://www.dropbox.com/scl/fo/...&dl=1" \
  --collection "Ticket Stubs & Flyers" \
  --slug ticket-stubs-and-flyers
```

That script:

- downloads the shared Dropbox folder as a ZIP
- extracts source files into `staging/dropbox-review/<slug>/raw/`
- converts TIFF/TIF to JPG and PDF to first-page PNG in `staging/dropbox-review/<slug>/processed/`
- parses filenames into best-effort `title`, `artists` (used as agents on import), `item_sort_date`, `item_year`, and `item_date_display`
- writes a manual-review CSV to `staging/dropbox-review/<slug>/review/<slug>-review.csv`

The review CSV includes extra helper columns:

- `source_filename`
- `conversion_status`
- `parse_notes`
- `review_status`

Those extra columns are for cleanup and can remain during review; the final ingest CSV can be pared back to the standard importer columns afterward.

There is also a Tumblr review-stage script for public blogs:

```bash
python3 scripts/build_tumblr_review.py \
  --blog audiolitter.tumblr.com \
  --slug audio-litter \
  --collection "audiolitter"
```

That script:

- fetches public Tumblr posts through the official API
- writes a raw JSON dump to `staging/tumblr-review/<slug>/raw/`
- downloads the highest-resolution images to `staging/tumblr-review/<slug>/processed/`
- parses post dates into `item_sort_date`, `item_year`, and `item_date_display`
- splits Tumblr tags into best-effort `location`, `object_type`, and remaining `subjects`
- writes a manual-review CSV to `staging/tumblr-review/<slug>/review/<slug>-review.csv`
- writes a tag frequency report to `staging/tumblr-review/<slug>/review/<slug>-tag-report.txt`

## Optional OpenAI enrichment pass

If you want image-based metadata suggestions after the Dropbox review CSV is generated, create a local `.env` file from `.env.example`:

```bash
cp .env.example .env
```

Set:

```env
OPENAI_API_KEY=your_api_key_here
OPENAI_MODEL=gpt-5-mini
```

Then run:

```bash
python3 scripts/enrich_review_with_openai.py \
  --csv staging/dropbox-review/ticket-stubs-and-flyers/review/ticket-stubs-and-flyers-review.csv \
  --images-base staging/dropbox-review/ticket-stubs-and-flyers
```

That script reads the processed image plus the current parsed row and outputs a second review CSV with suggested fields such as:

- `suggested_title`
- `suggested_artists`
- `suggested_venue`
- `suggested_location`
- `suggested_subjects`
- `suggested_item_date_display`
- `suggested_event_type`
- `suggested_description`
- `model_confidence`
- `needs_human_review`

Useful flags:

```bash
python3 scripts/enrich_review_with_openai.py ... --limit 10
python3 scripts/enrich_review_with_openai.py ... --only-review-notes
```

`--only-review-notes` is a good first pass because it limits API calls to the rows already flagged as ambiguous by filename parsing.

## Metadata structure

The content model is:

- One post type: `collection_item`
- One collection taxonomy: `collection`
- Five access-point taxonomies: `agent`, `production`, `venue`, `location`, `item_tag`

Core post fields:

- `title`
- `content`
- `excerpt`
- featured image

Controlled-vocabulary fields:

- `collection`
  Values like `T-Shirts` or `Ticket Stubs & Flyers`
- `artists`
  Pipe-delimited names, for example `Nine Inch Nails|David Bowie`
- `venue`
  Pipe-delimited names if needed
- `location`
  Pipe-delimited names if needed
- `subjects`
  Pipe-delimited topical terms

Item metadata fields:

- `item_identifier`
  Local ID or source-system identifier
- `item_sort_date`
  Machine-readable date in `YYYY-MM-DD` format used for sorting
- `item_year`
  Integer year derived from `item_sort_date` and used for filtering/card metadata
- `item_date_display`
  Human-readable date string shown on the front end
- `item_condition`
- `item_materials`
- `item_dimensions`
- `item_rights`
- `item_source`
- `item_inscription`
- `item_event_link`
  External URL for a concert-specific resource such as Setlist.fm
- `item_dropbox_path`
- `item_gallery_ids`
  Internal WordPress attachment IDs for alternate views such as front/back, reverse side, or details

Import-only image columns:

- `featured_image`
  Absolute path, path relative to `--images-base`, or remote image URL
- `gallery_images`
  Pipe-delimited image paths or remote URLs for alternate views such as front/back, reverse side, or details

## CSV columns

Expected CSV columns:

- `title`
- `content`
- `excerpt`
- `collection`
- `artists`
- `venue`
- `location`
- `subjects`
- `item_identifier`
- `item_sort_date`
- `item_year`
- `item_date_display`
- `item_condition`
- `item_materials`
- `item_dimensions`
- `item_rights`
- `item_source`
- `item_inscription`
- `item_event_link`
- `item_dropbox_path`
- `featured_image`
- `gallery_images`

The generic importer now skips rows that already exist when it can match either:

- `item_identifier`
- `source_uri` stored internally as `_wj_source_uri`

If a row includes remote image URLs in `featured_image` or `gallery_images`, the importer downloads them into the WordPress media library automatically.

## Omeka direct import

If you have an Omeka CSV export, you can import it directly with:

```bash
wp wj import-omeka /path/to/export.csv
```

Current Omeka mapping:

- `Item Id` -> `item_identifier`
- `Item URI` -> internal source URI used for duplicate detection
- `Dublin Core:Title` -> `title`
- `Dublin Core:Description` -> `content`
- `Dublin Core:Date` -> `item_date_display`
- normalized date from `Dublin Core:Date` -> `item_sort_date`
- year derived from `item_sort_date` -> `item_year`
- `Dublin Core:Coverage` -> `venue` + `location`
- `tags` -> `subjects`
- `collection` -> `collection`
- `file` -> featured image, downloaded from the remote URL
- `Item Type Metadata:URL` -> `item_event_link`

Re-running `wp wj import-omeka ...` is safe for already-imported records because the command skips items that already match by Omeka `Item Id` or `Item URI`.

## Legacy migration

`wp wj migrate-legacy` migrates old `ticket_stub` and `t_shirt` posts into `collection_item` posts.

Mapping rules:

- `ticket_stub` becomes collection `Ticket Stubs & Flyers`
- `t_shirt` becomes collection `T-Shirts`
- old `artist` terms map to new `agent`
- old core tags map to new `item_tag`
- `ticket_venue` maps to `venue`
- `ticket_location` maps to `location`
- `ticket_date` maps to `item_date_display`
- `ticket_date` also derives `item_sort_date`
- `shirt_year` derives `item_sort_date` and `item_year`
- old `gallery_ids` maps to `item_gallery_ids`
- featured images are retained
- legacy source post ID/type are stored in `_wj_legacy_post_id` and `_wj_legacy_post_type`

By default, legacy posts are set to draft after migration. Use `--keep-legacy` to leave them published.

## Recommended workflow

1. Run `wp wj migrate-legacy --dry-run`.
2. Run `wp wj migrate-legacy` after reviewing the output.
3. Export structured metadata from Omeka or ResourceSpace to CSV.
4. Generate a fresh CSV template with `wp wj make-import-template`.
5. Map source columns into the schema above.
6. Import images and rows with `wp wj import-items ...`.
7. Add `wikidata_id` values to agent terms where linked-data enrichment is desired.

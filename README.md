# Twenty Twenty-Five Child Theme for `wjlevay.net`

This child theme turns a stock WordPress block theme into a collection-driven site for browsing, describing, and ingesting personal archival materials.

It was built primarily for:

- concert ephemera like ticket stubs and flyers
- apparel and other collection objects
- book-like materials such as `Anthropology Paperbacks`
- Tumblr-sourced visual-diary material such as `audiolitter`

The theme is not just a visual layer. It includes a custom data model, archive templates, linked-data enrichment, ingest tooling, and SEO/schema extensions.

## What This Theme Adds

- A single shared item post type: `collection_item`
- Collection-level browsing at `/collections/`
- Cross-collection browsing at `/items/`
- Dedicated archives and landing pages for:
  - `collection`
  - `agent`
  - `production`
  - `venue`
  - `location`
  - `item_tag`
- A responsive single-item experience with:
  - desktop OpenSeadragon viewer
  - mobile full-screen image modal
  - support for supplemental images / alternate views
- Filtered browsing with context-aware `Back to results`, `Previous`, and `Next` navigation
- Wikidata enrichment for agent pages
- JSON-LD / Yoast SEO configuration for custom content
- WP-CLI and Python-based ingest pipelines for Dropbox, Omeka, Tumblr, and review-stage OpenAI enrichment

## Theme Structure

Main bootstrap:

- [functions.php](/home/wjlevay/projects/wjlevay-net/wp/wp-content/themes/twentytwentyfive-child/functions.php)

Core modules:

- [inc/theme-setup.php](/home/wjlevay/projects/wjlevay-net/wp/wp-content/themes/twentytwentyfive-child/inc/theme-setup.php)
  Theme setup, asset loading, cache-busting, page seeding
- [inc/data-model.php](/home/wjlevay/projects/wjlevay-net/wp/wp-content/themes/twentytwentyfive-child/inc/data-model.php)
  Post type, taxonomies, filtering behavior, date normalization
- [inc/render.php](/home/wjlevay/projects/wjlevay-net/wp/wp-content/themes/twentytwentyfive-child/inc/render.php)
  Front-end rendering helpers, shortcodes, cards, viewers, breadcrumbs, browse context
- [inc/admin.php](/home/wjlevay/projects/wjlevay-net/wp/wp-content/themes/twentytwentyfive-child/inc/admin.php)
  Admin metaboxes, term fields, list-table customization
- [inc/importer.php](/home/wjlevay/projects/wjlevay-net/wp/wp-content/themes/twentytwentyfive-child/inc/importer.php)
  WP-CLI import and migration commands
- [inc/linked-data.php](/home/wjlevay/projects/wjlevay-net/wp/wp-content/themes/twentytwentyfive-child/inc/linked-data.php)
  Wikidata lookup, caching, facts, agent context
- [inc/seo.php](/home/wjlevay/projects/wjlevay-net/wp/wp-content/themes/twentytwentyfive-child/inc/seo.php)
  Yoast option syncing and JSON-LD output

Templates:

- [single-collection-item.php](/home/wjlevay/projects/wjlevay-net/wp/wp-content/themes/twentytwentyfive-child/single-collection-item.php)
- [archive-collection-items.php](/home/wjlevay/projects/wjlevay-net/wp/wp-content/themes/twentytwentyfive-child/archive-collection-items.php)
- [taxonomy-agent-landing.php](/home/wjlevay/projects/wjlevay-net/wp/wp-content/themes/twentytwentyfive-child/taxonomy-agent-landing.php)
- [taxonomy-collection-access-point.php](/home/wjlevay/projects/wjlevay-net/wp/wp-content/themes/twentytwentyfive-child/taxonomy-collection-access-point.php)
- [parts/header.html](/home/wjlevay/projects/wjlevay-net/wp/wp-content/themes/twentytwentyfive-child/parts/header.html)
- [parts/footer.html](/home/wjlevay/projects/wjlevay-net/wp/wp-content/themes/twentytwentyfive-child/parts/footer.html)

Front-end assets:

- [assets/css/site.css](/home/wjlevay/projects/wjlevay-net/wp/wp-content/themes/twentytwentyfive-child/assets/css/site.css)
- [assets/js/app.js](/home/wjlevay/projects/wjlevay-net/wp/wp-content/themes/twentytwentyfive-child/assets/js/app.js)

## Content Model

### Post Type

- `collection_item`

All collection objects live in a single post type so they can be browsed together or split by collection/access point.

### Taxonomies

- `collection`
  Top-level collection buckets such as `Ticket Stubs & Flyers`, `T-Shirts`, `audiolitter`, and `Anthropology Paperbacks`
- `agent`
  Broad participant/creator taxonomy for people, bands, ensembles, teams, organizations, etc.
- `production`
  Works / productions / shows such as Broadway productions and similar titles
- `venue`
  Specific venues / buildings / sites
- `location`
  Place access points, generally city or borough/city/state level
- `item_tag`
  Topical tags / subjects

### Item Metadata

Important meta fields include:

- `item_identifier`
- `item_sort_date`
- `item_date_display`
- `item_year`
- `item_publisher`
- `item_condition`
- `item_materials`
- `item_dimensions`
- `item_rights`
- `item_source`
- `item_inscription`
- `item_event_link`
- `item_dropbox_path`
- `item_gallery_ids`

### Date Strategy

Dates are intentionally split:

- `item_sort_date`
  Machine-readable `YYYY-MM-DD` date used for sorting
- `item_date_display`
  Human-readable front-end date
- `item_year`
  Derived helper used in filters and card metadata

This allows approximate / display-friendly dates while preserving reliable chronology.

## Front-End Features

### Collections Landing Page

The `/collections/` page is no longer a simple stat-card grid.

It now shows each collection as a section with:

- collection title
- large item count
- `View collection` link
- preview rail of representative items
- final `View all` tile

On mobile, the preview rail becomes a horizontally scrollable, swipe-friendly strip with visible next-card peeking.

### Archive and Filter UI

The theme provides a shared item browser for archives and taxonomies with filters for:

- search
- agent
- production
- collection
- venue
- location
- subject
- year
- sort

Behavior:

- filters collapse by default
- active filters auto-open the panel
- mobile reorders controls so search actions are reachable earlier
- width and box-model fixes were added for laptop and phone layouts

### Single Item Pages

Single-item pages include:

- breadcrumbs back to collections
- item title and metadata panel
- collection-aware / filter-aware navigation
- alternate image support

Metadata order is designed to foreground access points first:

1. Identifier
2. Date
3. Agent
4. Publisher
5. Production
6. Venue
7. Location
8. Related Link
9. Subject
10. Collection
11. Materials
12. Dimensions
13. Condition
14. Rights
15. Inscription / Notes

### Image Viewer

Desktop:

- inline OpenSeadragon viewer
- supplemental image thumbnails

Mobile:

- inline trigger image instead of inline OpenSeadragon
- full-screen modal with plain image viewing
- native mobile zoom behavior
- visible pre-launch thumbnails for alternate views
- previous/next image controls and image counter in the modal

### Context-Aware Item Navigation

If a user enters an item from a filtered result set, the theme preserves browse context and renders:

- `Back to results`
- `Previous`
- `Next`

This works for:

- filtered collection pages
- `/items/`
- agent pages
- other taxonomy archives

If there is no incoming browse context, the theme falls back to collection-level previous/next ordering.

## Agent Pages and Linked Data

Agent pages are more than simple term archives.

They can include:

- Wikidata image
- short description
- structured fact rows
  - type
  - origin
  - active years
  - genres / disciplines / sport
  - website where available
- collection-specific context derived from local holdings
  - item count
  - collection span
  - top collections
  - venues
  - locations
  - related agents
  - productions

Wikidata IDs are stored in term meta as:

- `wikidata_id`

The code can also attempt lookup-by-name when an explicit ID is missing, but explicit IDs are preferred for accuracy.

## SEO and Structured Data

The theme supplements Yoast rather than replacing it.

### Yoast Settings from Code

[inc/seo.php](/home/wjlevay/projects/wjlevay-net/wp/wp-content/themes/twentytwentyfive-child/inc/seo.php) syncs core Yoast title/indexing settings into the `wpseo_titles` option for:

- `collection_item`
- `collection`
- `agent`
- `production`
- `venue`
- `location`
- `item_tag`

This avoids relying on the plugin UI for the custom model defaults.

### JSON-LD

The theme outputs custom JSON-LD for:

- single `collection_item` pages
- collection archives / item archives

Schema behavior:

- most items emit `CreativeWork`
- book-like items emit `Book`
- collection/archive pages emit `CollectionPage`

Supported fields include:

- title
- description
- image
- date
- publisher
- author / about
- keywords
- content location
- collection membership
- related external link

## Admin Enhancements

The theme adds admin-side support for:

- collection item metaboxes for the custom metadata
- `wikidata_id` field on agent terms
- normalized sort date handling
- collection item list-table columns for date handling
- default admin ordering by normalized sort date

## Ingest and Migration Tooling

This theme includes substantial ingest support.

Primary documentation:

- [docs/ingest.md](/home/wjlevay/projects/wjlevay-net/wp/wp-content/themes/twentytwentyfive-child/docs/ingest.md)

### WP-CLI Commands

Implemented in [inc/importer.php](/home/wjlevay/projects/wjlevay-net/wp/wp-content/themes/twentytwentyfive-child/inc/importer.php):

- `wp wj make-import-template`
- `wp wj import-items`
- `wp wj import-omeka`
- `wp wj migrate-legacy`

These support:

- direct CSV ingest
- duplicate skipping
- remote image download
- Omeka import
- migration from older custom post types

### Dropbox Review Workflow

[scripts/build_dropbox_review.py](/home/wjlevay/projects/wjlevay-net/wp/wp-content/themes/twentytwentyfive-child/scripts/build_dropbox_review.py)

This script:

- downloads a Dropbox share ZIP
- extracts source files
- converts TIFF/TIF to JPG
- converts PDF first pages to PNG
- carries through existing JPG/PNG assets
- parses filenames into best-effort metadata
- writes a review CSV for manual cleanup before import

### Tumblr Review Workflow

[scripts/build_tumblr_review.py](/home/wjlevay/projects/wjlevay-net/wp/wp-content/themes/twentytwentyfive-child/scripts/build_tumblr_review.py)

This script:

- fetches a public Tumblr blog through the Tumblr API
- downloads images
- builds `item_sort_date`, `item_date_display`, and `item_year`
- splits Tumblr tags into location/object-type/subject suggestions
- creates a review CSV and tag report

### OpenAI Review-Stage Enrichment

[scripts/enrich_review_with_openai.py](/home/wjlevay/projects/wjlevay-net/wp/wp-content/themes/twentytwentyfive-child/scripts/enrich_review_with_openai.py)

This script can enrich review-stage CSV rows using processed images plus the Responses API, suggesting improved:

- titles
- agents
- venue / location
- subjects
- display dates
- short notes

Related `.env` variables:

- `OPENAI_API_KEY`
- `OPENAI_MODEL`
- `TUMBLR_CONSUMER_KEY`
- `TUMBLR_CONSUMER_SECRET`

See [.env.example](/home/wjlevay/projects/wjlevay-net/wp/wp-content/themes/twentytwentyfive-child/.env.example).

## Data Cleanup / Utility Scripts

The repository also includes one-off or semi-reusable maintenance scripts for:

- subject normalization
- item tag cleanup
- artist/agent Wikidata backfill
- Yoast setting sync
- collection data cleanup

These live in [scripts/](/home/wjlevay/projects/wjlevay-net/wp/wp-content/themes/twentytwentyfive-child/scripts).

Some are best treated as maintenance utilities rather than part of the normal runtime.

## Visual / UX Direction

The child theme diverges from the stock Twenty Twenty-Five feel with:

- a warmer editorial color palette
- `Sora` for display typography
- `Manrope` for body/UI text
- simplified header/footer shell
- compact but more intentionally styled collection/item cards
- mobile-specific refinement of header, filters, pagination, and single-item image viewing

## Current Collections / Supported Use Cases

The data model is currently designed to support:

- `Ticket Stubs & Flyers`
- `T-Shirts`
- `audiolitter`
- `Anthropology Paperbacks`

The same model can support other object-based collections as long as they fit the shared `collection_item` structure.

## Development Notes

### Local Development

Development was done against a local DDEV-backed WordPress instance, with this theme directory as the main code workspace.

### Production Strategy

Recommended working model:

- content edits happen in production
- code changes happen locally first
- deploy code-only changes to production after testing
- use scripted / repeatable data operations for bulk cleanup or migration

### Deploy Pattern

Typical deploy pattern used for this project:

1. commit locally
2. `git push origin main`
3. sync the child theme directory to production
4. flush WordPress cache
5. purge SiteGround cache

### Cache Busting

Theme CSS/JS are versioned by file modification time so browser cache invalidation is automatic when assets change.

## Important Docs and Reference Files

- [docs/ingest.md](/home/wjlevay/projects/wjlevay-net/wp/wp-content/themes/twentytwentyfive-child/docs/ingest.md)
- [docs/sample-import.csv](/home/wjlevay/projects/wjlevay-net/wp/wp-content/themes/twentytwentyfive-child/docs/sample-import.csv)
- [.env.example](/home/wjlevay/projects/wjlevay-net/wp/wp-content/themes/twentytwentyfive-child/.env.example)

## Summary

This theme is effectively a small collection-management application built inside WordPress:

- shared object model
- rich taxonomy browsing
- linked-data enrichment
- import/migration pipelines
- custom single-item UX
- SEO / JSON-LD extensions

It is tailored to `wjlevay.net`, but the structure is reusable for other personal digital collection projects with similar archival materials.

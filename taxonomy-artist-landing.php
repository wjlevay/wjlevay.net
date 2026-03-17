<?php
/**
 * Artist landing page template.
 *
 * @package twentytwentyfive-child
 */

if (!defined('ABSPATH')) {
	exit;
}

$term = get_queried_object();
if (!$term instanceof WP_Term) {
	status_header(404);
	nocache_headers();
	include get_query_template('404');
	exit;
}

$entity = wj_get_wikidata_entity_for_term($term);
$collections = get_terms(
	[
		'taxonomy'   => 'collection',
		'hide_empty' => true,
		'object_ids' => get_objects_in_term($term->term_id, 'artist'),
	]
);

$collections = is_wp_error($collections) ? [] : $collections;
?><!doctype html>
<html <?php language_attributes(); ?>>
<head>
	<meta charset="<?php bloginfo('charset'); ?>">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<?php wp_head(); ?>
</head>
<body <?php body_class('wj-artist-template'); ?>>
<?php wp_body_open(); ?>
<div class="wp-site-blocks">
	<header class="wp-block-template-part">
		<?php
		if (function_exists('block_template_part')) {
			block_template_part('header');
		} else {
			echo do_blocks('<!-- wp:template-part {"slug":"header","tagName":"header"} /-->');
		}
		?>
	</header>

	<main class="wp-block-group has-global-padding is-layout-constrained wp-block-group-is-layout-constrained wj-tax-shell">
		<div class="alignwide wj-tax-inner">
			<section class="wj-artist-hero">
				<div class="wj-artist-copy">
					<p class="wj-eyebrow"><?php esc_html_e('Artist', 'twentytwentyfive-child'); ?></p>
					<h1><?php single_term_title(); ?></h1>
					<?php if (!empty($entity['description'])) : ?>
						<p class="wj-artist-summary"><?php echo esc_html($entity['description']); ?></p>
					<?php elseif ($term->description) : ?>
						<p class="wj-artist-summary"><?php echo esc_html($term->description); ?></p>
					<?php endif; ?>

					<div class="wj-artist-stats">
						<div><span><?php esc_html_e('Items', 'twentytwentyfive-child'); ?></span><strong><?php echo esc_html((string) (int) $term->count); ?></strong></div>
						<div><span><?php esc_html_e('Collections', 'twentytwentyfive-child'); ?></span><strong><?php echo esc_html((string) count($collections)); ?></strong></div>
					</div>

					<?php if ($collections) : ?>
						<div class="wj-cross-links">
							<?php foreach ($collections as $collection) : ?>
								<a href="<?php echo esc_url(add_query_arg('artist', $term->slug, get_term_link($collection))); ?>">
									<?php echo esc_html($collection->name); ?>
								</a>
							<?php endforeach; ?>
						</div>
					<?php endif; ?>

					<?php if (!empty($entity['id']) || !empty($entity['wikipedia'])) : ?>
						<div class="wj-cross-links">
							<?php if (!empty($entity['id'])) : ?>
								<a href="<?php echo esc_url('https://www.wikidata.org/wiki/' . $entity['id']); ?>" target="_blank" rel="noreferrer noopener"><?php esc_html_e('View Wikidata', 'twentytwentyfive-child'); ?></a>
							<?php endif; ?>
							<?php if (!empty($entity['wikipedia'])) : ?>
								<a href="<?php echo esc_url('https://en.wikipedia.org/wiki/' . rawurlencode(str_replace(' ', '_', $entity['wikipedia']))); ?>" target="_blank" rel="noreferrer noopener"><?php esc_html_e('View Wikipedia', 'twentytwentyfive-child'); ?></a>
							<?php endif; ?>
						</div>
					<?php endif; ?>
				</div>

				<?php if (!empty($entity['image'])) : ?>
					<div class="wj-artist-media">
						<img src="<?php echo esc_url($entity['image']); ?>" alt="<?php echo esc_attr($term->name); ?>" loading="lazy">
					</div>
				<?php endif; ?>
			</section>

			<?php if (have_posts()) : ?>
				<section class="wj-tax-results" aria-label="<?php esc_attr_e('Artist items', 'twentytwentyfive-child'); ?>">
					<div class="wj-section-heading">
						<p class="wj-eyebrow"><?php esc_html_e('Browse Items', 'twentytwentyfive-child'); ?></p>
						<h2><?php esc_html_e('Items across all collections', 'twentytwentyfive-child'); ?></h2>
					</div>
					<div class="wj-tax-grid">
						<?php
						while (have_posts()) :
							the_post();
							?>
							<article <?php post_class('wj-item-record'); ?>>
								<a class="wj-item-record-image" href="<?php the_permalink(); ?>">
									<?php if (has_post_thumbnail()) : ?>
										<?php the_post_thumbnail('wj-card'); ?>
									<?php endif; ?>
								</a>
								<div class="wj-item-record-body">
									<h2 class="wj-item-record-title"><a href="<?php the_permalink(); ?>"><?php the_title(); ?></a></h2>
									<?php echo wj_render_item_card_meta(); ?>
									<?php if (has_excerpt()) : ?>
										<div class="wj-item-record-excerpt"><?php the_excerpt(); ?></div>
									<?php endif; ?>
								</div>
							</article>
							<?php
						endwhile;
						?>
					</div>

					<nav class="wj-tax-pagination" aria-label="<?php esc_attr_e('Pagination', 'twentytwentyfive-child'); ?>">
						<?php
						the_posts_pagination(
							[
								'mid_size'  => 1,
								'prev_text' => __('Previous', 'twentytwentyfive-child'),
								'next_text' => __('Next', 'twentytwentyfive-child'),
							]
						);
						?>
					</nav>
				</section>
			<?php else : ?>
				<section class="wj-tax-empty">
					<p><?php esc_html_e('No items matched the current filters.', 'twentytwentyfive-child'); ?></p>
				</section>
			<?php endif; ?>
		</div>
	</main>

	<footer class="wp-block-template-part">
		<?php
		if (function_exists('block_template_part')) {
			block_template_part('footer');
		} else {
			echo do_blocks('<!-- wp:template-part {"slug":"footer","tagName":"footer"} /-->');
		}
		?>
	</footer>
</div>
<?php wp_footer(); ?>
</body>
</html>

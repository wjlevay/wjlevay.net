<?php
/**
 * Agent landing page template.
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
$facts = $entity['facts'] ?? [];
$context = wj_get_agent_collection_context($term);
$collections = get_terms(
	[
		'taxonomy'   => 'collection',
		'hide_empty' => true,
		'object_ids' => get_objects_in_term($term->term_id, 'agent'),
	]
);

$collections = is_wp_error($collections) ? [] : $collections;

if (!function_exists('wj_render_agent_context_links')) {
	function wj_render_agent_context_links(array $items, array $query_args = []): string {
		if (!$items) {
			return '';
		}

		$base = get_post_type_archive_link('collection_item');
		$links = [];
		foreach ($items as $item) {
			$term = $item['term'];
			$url = $base ? add_query_arg(array_merge($query_args, [$term->taxonomy => $term->slug]), $base) : get_term_link($term);
			if (is_wp_error($url)) {
				continue;
			}
			$links[] = sprintf(
				'<li><a href="%s">%s</a><span>%d</span></li>',
				esc_url($url),
				esc_html($term->name),
				(int) $item['count']
			);
		}

		return $links ? '<ul class="wj-agent-context-list">' . implode('', $links) . '</ul>' : '';
	}
}
?><!doctype html>
<html <?php language_attributes(); ?>>
<head>
	<meta charset="<?php bloginfo('charset'); ?>">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<?php wp_head(); ?>
</head>
<body <?php body_class('wj-artist-template wj-agent-template'); ?>>
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
					<p class="wj-eyebrow"><?php esc_html_e('Agent', 'twentytwentyfive-child'); ?></p>
					<h1><?php single_term_title(); ?></h1>
					<?php if (!empty($entity['description'])) : ?>
						<p class="wj-artist-summary"><?php echo esc_html($entity['description']); ?></p>
					<?php elseif ($term->description) : ?>
						<p class="wj-artist-summary"><?php echo esc_html($term->description); ?></p>
					<?php endif; ?>

					<div class="wj-artist-stats">
						<div><span><?php esc_html_e('Items', 'twentytwentyfive-child'); ?></span><strong><?php echo esc_html((string) (int) $term->count); ?></strong></div>
						<div><span><?php esc_html_e('Collections', 'twentytwentyfive-child'); ?></span><strong><?php echo esc_html((string) count($collections)); ?></strong></div>
						<?php if (!empty($context['earliest_year']) && !empty($context['latest_year'])) : ?>
							<div><span><?php esc_html_e('Collection Span', 'twentytwentyfive-child'); ?></span><strong><?php echo esc_html($context['earliest_year'] . ' to ' . $context['latest_year']); ?></strong></div>
						<?php endif; ?>
					</div>

					<?php if ($facts) : ?>
						<div class="wj-agent-facts">
							<?php foreach ($facts as $fact) : ?>
								<div class="wj-agent-fact">
									<span><?php echo esc_html($fact['label']); ?></span>
									<?php if (!empty($fact['url'])) : ?>
										<a href="<?php echo esc_url($fact['url']); ?>" target="_blank" rel="noreferrer noopener"><?php echo esc_html($fact['value']); ?></a>
									<?php else : ?>
										<strong><?php echo esc_html($fact['value']); ?></strong>
									<?php endif; ?>
								</div>
							<?php endforeach; ?>
						</div>
					<?php endif; ?>

					<?php if ($collections) : ?>
						<div class="wj-cross-links">
							<?php foreach ($collections as $collection) : ?>
								<a href="<?php echo esc_url(add_query_arg('agent', $term->slug, get_term_link($collection))); ?>">
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

			<section class="wj-agent-context-grid" aria-label="<?php esc_attr_e('Collection context', 'twentytwentyfive-child'); ?>">
				<?php if (!empty($context['collections'])) : ?>
					<article class="wj-agent-context-card">
						<p class="wj-eyebrow"><?php esc_html_e('In This Collection', 'twentytwentyfive-child'); ?></p>
						<h2><?php esc_html_e('Collections', 'twentytwentyfive-child'); ?></h2>
						<?php echo wp_kses_post(wj_render_agent_context_links($context['collections'], ['agent' => $term->slug])); ?>
					</article>
				<?php endif; ?>

				<?php if (!empty($context['venues'])) : ?>
					<article class="wj-agent-context-card">
						<p class="wj-eyebrow"><?php esc_html_e('Seen At', 'twentytwentyfive-child'); ?></p>
						<h2><?php esc_html_e('Venues', 'twentytwentyfive-child'); ?></h2>
						<?php echo wp_kses_post(wj_render_agent_context_links($context['venues'], ['agent' => $term->slug])); ?>
					</article>
				<?php endif; ?>

				<?php if (!empty($context['locations'])) : ?>
					<article class="wj-agent-context-card">
						<p class="wj-eyebrow"><?php esc_html_e('Places', 'twentytwentyfive-child'); ?></p>
						<h2><?php esc_html_e('Locations', 'twentytwentyfive-child'); ?></h2>
						<?php echo wp_kses_post(wj_render_agent_context_links($context['locations'], ['agent' => $term->slug])); ?>
					</article>
				<?php endif; ?>

				<?php if (!empty($context['related'])) : ?>
					<article class="wj-agent-context-card">
						<p class="wj-eyebrow"><?php esc_html_e('Also With', 'twentytwentyfive-child'); ?></p>
						<h2><?php esc_html_e('Related Agents', 'twentytwentyfive-child'); ?></h2>
						<?php echo wp_kses_post(wj_render_agent_context_links($context['related'])); ?>
					</article>
				<?php endif; ?>

				<?php if (!empty($context['productions'])) : ?>
					<article class="wj-agent-context-card">
						<p class="wj-eyebrow"><?php esc_html_e('Works', 'twentytwentyfive-child'); ?></p>
						<h2><?php esc_html_e('Productions', 'twentytwentyfive-child'); ?></h2>
						<?php echo wp_kses_post(wj_render_agent_context_links($context['productions'], ['agent' => $term->slug])); ?>
					</article>
				<?php endif; ?>
			</section>

			<?php if (have_posts()) : ?>
				<section class="wj-tax-results" aria-label="<?php esc_attr_e('Agent items', 'twentytwentyfive-child'); ?>">
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

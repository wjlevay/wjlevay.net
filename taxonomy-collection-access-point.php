<?php
/**
 * Clean taxonomy archive template for collection access points.
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

$count = (int) $term->count;
?><!doctype html>
<html <?php language_attributes(); ?>>
<head>
	<meta charset="<?php bloginfo('charset'); ?>">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<?php wp_head(); ?>
</head>
<body <?php body_class('wj-taxonomy-template'); ?>>
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
			<header class="wj-tax-header">
				<p class="wj-eyebrow"><?php echo esc_html(get_taxonomy($term->taxonomy)->labels->singular_name ?? __('Archive', 'twentytwentyfive-child')); ?></p>
				<div class="wj-tax-header-grid">
					<div class="wj-tax-heading">
						<h1><?php single_term_title(); ?></h1>
						<?php if ($count > 0) : ?>
							<p class="wj-tax-count"><?php echo esc_html(sprintf(_n('%d item', '%d items', $count, 'twentytwentyfive-child'), $count)); ?></p>
						<?php endif; ?>
					</div>
					<div class="wj-tax-intro">
						<?php echo wp_kses_post(wj_render_term_intro()); ?>
					</div>
				</div>
			</header>

			<section class="wj-tax-controls" aria-label="<?php esc_attr_e('Browse and filter items', 'twentytwentyfive-child'); ?>">
				<?php echo wj_render_item_browser(); ?>
			</section>

			<?php if (have_posts()) : ?>
				<section class="wj-tax-results" aria-label="<?php esc_attr_e('Collection items', 'twentytwentyfive-child'); ?>">
					<div class="wj-tax-grid">
						<?php
						while (have_posts()) :
							the_post();
							?>
							<article <?php post_class('wj-item-record'); ?>>
								<a class="wj-item-record-image" href="<?php the_permalink(); ?>">
									<?php
									if (has_post_thumbnail()) {
										the_post_thumbnail('wj-card');
									}
									?>
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

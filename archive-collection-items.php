<?php
/**
 * All-items archive template.
 *
 * @package twentytwentyfive-child
 */

if (!defined('ABSPATH')) {
	exit;
}
?><!doctype html>
<html <?php language_attributes(); ?>>
<head>
	<meta charset="<?php bloginfo('charset'); ?>">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<?php wp_head(); ?>
</head>
<body <?php body_class('wj-taxonomy-template wj-items-template'); ?>>
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
				<p class="wj-eyebrow"><?php esc_html_e('Archive', 'twentytwentyfive-child'); ?></p>
				<div class="wj-tax-header-grid">
					<div class="wj-tax-heading">
						<h1><?php esc_html_e('All Items', 'twentytwentyfive-child'); ?></h1>
						<p class="wj-tax-count"><?php esc_html_e('Search and sort across shirts, ticket stubs, flyers, and related ephemera.', 'twentytwentyfive-child'); ?></p>
					</div>
				</div>
			</header>

			<section class="wj-tax-controls" aria-label="<?php esc_attr_e('Browse all items', 'twentytwentyfive-child'); ?>">
				<?php echo wj_render_item_browser(); ?>
			</section>

			<?php if (have_posts()) : ?>
				<section class="wj-tax-results" aria-label="<?php esc_attr_e('All collection items', 'twentytwentyfive-child'); ?>">
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
						<?php echo wj_render_compact_pagination(); ?>
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

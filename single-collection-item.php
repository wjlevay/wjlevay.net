<?php
/**
 * Clean single template for collection items.
 *
 * @package twentytwentyfive-child
 */

if (!defined('ABSPATH')) {
	exit;
}

global $post;

?><!doctype html>
<html <?php language_attributes(); ?>>
<head>
	<meta charset="<?php bloginfo('charset'); ?>">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<?php wp_head(); ?>
</head>
<body <?php body_class('single-collection-item-template'); ?>>
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

	<main class="wp-block-group has-global-padding is-layout-constrained wp-block-group-is-layout-constrained wj-single-shell">
		<div class="alignwide wj-single-inner">
			<header class="wj-single-header">
				<?php echo wp_kses_post(wj_get_single_item_breadcrumbs((int) $post->ID)); ?>
				<h1><?php the_title(); ?></h1>
			</header>

			<?php echo wj_render_item_navigation((int) $post->ID); ?>

			<div class="wj-single-layout">
				<section class="wj-single-viewer-panel" aria-label="<?php esc_attr_e('Item images', 'twentytwentyfive-child'); ?>">
					<?php echo wj_render_item_viewer(); ?>
				</section>

				<aside class="wj-single-meta-panel" aria-label="<?php esc_attr_e('Item metadata', 'twentytwentyfive-child'); ?>">
					<?php echo wj_render_item_meta(); ?>
				</aside>
			</div>

			<?php if (trim((string) get_the_content())) : ?>
				<div class="wj-single-content">
					<?php the_content(); ?>
				</div>
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

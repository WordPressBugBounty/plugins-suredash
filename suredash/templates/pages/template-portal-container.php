<?php
/**
 * Template Name: SureDash: Portal Layout
 * Template Post Type: page, post
 * Description: Display your page/post content within SureDash portal layout
 *
 * @package SureDash
 * @since 1.6.0
 */

defined( 'ABSPATH' ) || exit;

?>
<!DOCTYPE html>
<html <?php language_attributes(); ?>>
	<head>
		<meta charset="<?php bloginfo( 'charset' ); ?>" />
		<meta name="viewport" content="width=device-width, initial-scale=1" />
		<?php
		wp_head();
		do_action( 'suredash_enqueue_scripts' );
		?>
	</head>

	<body <?php body_class(); ?>>
		<?php wp_body_open(); ?>

		<div class="wp-site-blocks portal-container">
			<?php
			// Get the portal template part.
			$portal_template_part = get_block_template( 'suredash/suredash//portal', 'wp_template_part' );

			if ( $portal_template_part && ! empty( $portal_template_part->content ) ) {
				// Parse portal template part blocks.
				$blocks = parse_blocks( $portal_template_part->content );

				// Replace suredash/content with placeholders.
				$modified_blocks = suredash_replace_content_with_post_content( $blocks );

				// Get layout details for container type and style.
				$layout_details = \SureDashboard\Inc\Utils\Helper::get_layout_details();
				$layout_class   = $layout_details['layout'] ?? 'normal';
				$layout_style   = $layout_details['style'] ?? 'boxed';

				// Render the blocks and capture output.
				ob_start();
				foreach ( $modified_blocks as $block ) {
					// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- render_block() handles escaping internally.
					echo render_block( $block );
				}
				$rendered_content   = ob_get_clean();
				$main_content_open  = '';
				$post_content_html  = '';
				$main_content_close = '';
				// Build the content structure matching single posts exactly.
				while ( have_posts() ) {
					the_post();
					$current_post_id = get_the_ID();

					// Opening portal-main-content wrapper (from Content block view.php line 91).
					$main_content_open = sprintf(
						'<div id="portal-main-content" class="portal-layout-%s wp-block-suredash-content">',
						esc_attr( $layout_class )
					);

					// Inner content area wrapper (from single-content.php line 169).
					$post_content_html  = sprintf(
						'<div id="portal-post-%s" class="portal-content-area sd-%s-post">',
						esc_attr( (string) $current_post_id ),
						esc_attr( $layout_style )
					);
					$post_content_html .= '<div class="sd-overflow-hidden suredash-single-content portal-space-post-content portal-page-content">';
					ob_start();
					the_content();
					$post_content_html .= ob_get_clean();
					$post_content_html .= '</div></div>';

					// Closing portal-main-content wrapper.
					$main_content_close = '</div>';
				}

				// Replace placeholders in order.
				$rendered_content = str_replace( '<!--SUREDASH_MAIN_CONTENT_START-->', $main_content_open, (string) $rendered_content );
				$rendered_content = str_replace( '<!--SUREDASH_POST_CONTENT_PLACEHOLDER-->', $post_content_html, $rendered_content );
				$rendered_content = str_replace( '<!--SUREDASH_MAIN_CONTENT_END-->', $main_content_close, $rendered_content );

				// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Content already escaped via render_block() and WordPress core functions.
				echo $rendered_content;
			} else {
				// Fallback: just render the content.
				while ( have_posts() ) {
					the_post();
					the_content();
				}
			}
			?>
		</div>

		<?php
		do_action( 'suredash_footer' );
		wp_footer();
		?>
	</body>
</html>

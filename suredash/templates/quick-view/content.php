<?php
/**
 * The template for displaying space content area view.
 *
 * @see     https://developer.wordpress.org/themes/basics/template-hierarchy/
 * @package SureDashboard\Templates
 * @version 1.0.0
 */

defined( 'ABSPATH' ) || exit;

$p_id       = absint( ! empty( $_GET['post_id'] ) ? $_GET['post_id'] : 0 ); // phpcs:ignore
$p_comments = absint( ! empty( $_GET['comments'] ) ? $_GET['comments'] : 0 ); // phpcs:ignore

if ( ! $p_id ) {
	return;
}

?>
<!DOCTYPE html>
<html <?php language_attributes(); ?> class="suredash-quick-post suredash-editor-preview-content">
	<head>
		<meta charset="<?php bloginfo( 'charset' ); ?>" />
		<meta name="viewport" content="width=device-width, initial-scale=1" />
		<?php do_action( 'suredash_enqueue_scripts' ); ?>
		<?php wp_head(); ?>
		<style type="text/css">
			.suredash-editor-preview-content body {
				overflow: hidden;
			}
			.suredash-editor-preview-content .sd-post-title,
			.suredash-editor-preview-content #portal-comment {
				display: none;
			}
			.suredash-editor-preview-content.suredash-quick-post .portal-qv-post {
				padding: 0 !important;
			}
		</style>
	</head>

	<body <?php body_class( 'suredash-quick-view-content' ); ?>>
		<?php wp_body_open(); ?>

		<div id="portal-post-<?php echo esc_attr( (string) $p_id ); ?>" class="portal-qv-post portal-content portal-container">
			<?php do_action( 'suredashboard_quick_view_post_content', $p_id, $p_comments, '' ); ?>
		</div>
		<?php wp_footer(); ?>
	</body>
</html>

<?php wp_reset_postdata(); ?>

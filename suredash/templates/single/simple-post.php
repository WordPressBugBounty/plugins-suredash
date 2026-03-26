<?php
/**
 * The template for displaying archive portals view.
 *
 * This template can be overridden by copying it to yourTheme/suredashboard/single/post.php.
 *
 * @see     https://developer.wordpress.org/themes/basics/template-hierarchy/
 * @package SureDash\Templates
 * @version 1.3.0
 */

defined( 'ABSPATH' ) || exit;

use SureDashboard\Inc\Utils\Helper;
use SureDashboard\Inc\Utils\Labels;

$default_args = [
	'post'         => null,
	'base_post_id' => 0,
	'is_pinned'    => false,
	'comments'     => true,
];

if ( ! isset( $args ) ) {
	$args = [];
}

$args = wp_parse_args( $args, $default_args );

if ( is_null( $args['post'] ) || ! isset( $post ) ) {
	return;
}

$post_title = $post['post_title'] ?? '';
$p_id       = absint( $post['ID'] );

$bookmarked   = suredash_is_item_bookmarked( $p_id );
$bookmarked   = $bookmarked ? 'bookmarked' : '';
$content_type = 'full_content';
$queried_page = suredash_get_sub_queried_page();

// Enforce excerpt content type for user-view & feeds page.
$content_type = $queried_page === 'user-view' || $queried_page === 'feeds' ? 'excerpt' : $content_type;

if ( apply_filters( 'suredash_post_enforce_excerpt_content', false ) ) {
	$content_type = 'excerpt';
}

$post_content = Helper::get_post_content( $p_id, $content_type );

do_action( 'suredashboard_single_post_template', $p_id );

?>
<div id="portal-post-<?php echo esc_attr( (string) $p_id ); ?>" class="portal-store-list-post sd-max-w-full sd-box-shadow portal-content sd-relative sd-bg-content sd-border sd-radius-8 sd-overflow-hidden sd-transition-fast">

	<section class="portal-store-post-header sd-p-container">
		<div class="portal-store-post-author-data sd-flex sd-justify-between sd-items-start">
			<div class="portal-store-post-author-wrap sd-w-full sd-flex sd-items-center sd-justify-between">
				<h3 class="portal-store-post-title sd-m-0"><?php echo esc_html( $post_title ); ?></h3>
			</div>
			<div class="portal-store-post-actions sd-flex sd-relative sd-gap-4 sd-items-center">
				<?php
				if ( isset( $args['is_pinned'] ) && $args['is_pinned'] ) {
					?>
						<div class="portal-pinned-post-wrapper sd-nowrap">
							<?php
								Helper::show_badge(
									'neutral',
									'Pin',
									Labels::get_label( 'pinned_post' ),
									'sm',
									'sd-color-text-tertiary'
								);
							?>
						</div>
					<?php
				}
				?>
				<?php if ( is_user_logged_in() ) { ?>
					<button class="portal-post-bookmark-trigger portal-button button-ghost sd-flex sd-items-center <?php echo esc_attr( $bookmarked ); ?>" data-item_id="<?php echo esc_attr( (string) $p_id ); ?>" title="<?php esc_attr_e( 'Bookmark Post', 'suredash' ); ?>">
						<?php Helper::get_library_icon( 'Bookmark', true ); ?>
					</button>
				<?php } ?>
			</div>
		</div>
	</section>

	<?php Helper::suredash_featured_cover( $p_id ); ?>

	<div class="sd-p-container sd-pt-0">
		<div class="portal-space-post-content">
			<?php
				echo do_shortcode( suredash_render_post_content( $post_content ) );
			?>
		</div>
	</div>
</div>
<?php

<?php
/**
 * The template for displaying archive portals view.
 *
 * This template can be overridden by copying it to yourTheme/suredashboard/single/post.php.
 *
 * @see     https://developer.wordpress.org/themes/basics/template-hierarchy/
 * @package SureDash\Templates
 * @version 1.0.0
 */

defined( 'ABSPATH' ) || exit;

use SureDashboard\Inc\Utils\Helper;
use SureDashboard\Inc\Utils\Labels;
use SureDashboard\Inc\Utils\PostMeta;

$default_args = [
	'post'         => null,
	'base_post_id' => 0,
	'is_pinned'    => false,
	'comments'     => true,
	'is_feeds'     => false,
];

if ( ! isset( $args ) ) {
	$args = [];
}

$args = wp_parse_args( $args, $default_args );

if ( is_null( $args['post'] ) || ! isset( $post ) ) {
	return;
}

$author_id  = $post['post_author'] ?? '';
$post_title = $post['post_title'] ?? '';
$p_id       = absint( $post['ID'] );
$p_type     = $post['post_type'] ?? '';

if ( is_string( $args['comments'] ) ) {
	$p_comments = $args['comments'] === 'open' ? '1' : '0';
} else {
	$p_comments = boolval( $args['comments'] ?? true );
}

$permalink    = (string) get_the_permalink( $p_id );
$bookmarked   = suredash_is_item_bookmarked( $p_id );
$bookmarked   = $bookmarked ? 'bookmarked' : '';
$content_type = isset( $base_post_id ) && $base_post_id ? PostMeta::get_post_meta_value( $base_post_id, 'post_content_type' ) : 'full_content';
$user_view    = suredash_get_user_view_link( $author_id );
$queried_page = suredash_get_sub_queried_page();

// Enforce excerpt content type for user-view page.
if ( $queried_page === 'user-view' ) {
	$content_type = 'excerpt';
}

// For feeds page, use the feeds_content_type setting.
if ( $queried_page === 'feeds' ) {
	$feeds_content_type = Helper::get_option( 'feeds_content_type' );
	$content_type       = ! empty( $feeds_content_type ) ? $feeds_content_type : 'excerpt';
}

if ( apply_filters( 'suredash_post_enforce_excerpt_content', false ) ) {
	$content_type = 'excerpt';
}

$post_content = Helper::get_post_content( $p_id, $content_type );

do_action( 'suredashboard_single_post_template', $p_id );

?>
<div id="portal-post-<?php echo esc_attr( (string) $p_id ); ?>" class="portal-store-list-post portal-content sd-relative sd-bg-content sd-overflow-hidden sd-transition-fast">

	<section class="portal-store-post-header sd-p-container">
		<div class="portal-store-post-author-data sd-flex sd-justify-between sd-items-start">
			<div class="portal-store-post-author-wrap sd-w-full sd-flex sd-items-center">
				<?php suredash_get_user_avatar( $author_id ); ?>

				<div class="portal-post-author sd-flex-col sd-gap-6 sd-font-base sd-line-20">
					<a href="<?php echo esc_url( $user_view ); ?>" class="sd-mobile-flex-wrap">
						<span class="portal-store-post-author" aria-label="<?php echo 'Post author ' . esc_attr( suredash_get_author_name( get_the_author_meta( 'display_name', $author_id ) ) ); ?>">
							<?php echo esc_html( suredash_get_author_name( get_the_author_meta( 'display_name', $author_id ) ) ); ?>
						</span>
						<?php
						do_action( 'suredash_before_user_badges', $author_id );
						suredash_get_user_badges( $author_id, 2 );
						?>
					</a>
					<a href="<?php echo esc_url( $permalink ); ?>" target="_self" class="portal-thread-details">
						<?php
							$user_headline = sd_get_user_meta( $author_id, 'headline', true );
						if ( ! empty( $user_headline ) ) {
							?>
								<span class="sd-font-12 sd-user-headline"><?php echo esc_html( strval( $user_headline ) ); ?></span><span class="portal-reaction-separator sd-no-space"></span>
								<?php
						}
							suredash_get_relative_time( $p_id, true, isset( $args['is_feeds'] ) ? boolval( $args['is_feeds'] ) : false );
							$edited_time = sd_get_post_meta( $p_id, 'suredash_post_edited', true );
						if ( ! empty( $edited_time ) ) {
							?>
								<span class="portal-reaction-separator sd-no-space"></span> <span class="portal-comment-edited sd-font-12">(<?php echo esc_attr( __( 'Edited', 'suredash' ) ); ?>)</span>
								<?php
						}
						?>
					</a>
				</div>
			</div>
			<div class="portal-store-post-actions sd-flex sd-relative sd-gap-4 sd-items-center">
				<?php
				/**
				 * Fires to render post visibility button.
				 *
				 * @since 1.6.0
				 *
				 * @param int $p_id Post ID.
				 */
				do_action( 'suredash_post_visibility_button', $p_id );
				?>
				<?php
				if ( isset( $is_pinned ) && $is_pinned ) {
					?>
					<div class="portal-pinned-post-wrapper sd-flex sd-nowrap">
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
			<?php
			if ( is_user_logged_in() && ! suredash_content_post() ) {
						$bookmarked       = suredash_is_item_bookmarked( absint( $p_id ) );
						$bookmarked       = $bookmarked ? 'bookmarked' : '';
						$author_id_post   = sd_get_post_field( absint( $p_id ), 'post_author' );
						$portal_post_type = sd_get_post_field( absint( $p_id ), 'post_type' );
				?>
						<div class="sd-flex sd-items-center sd-justify-center sd-gap-6">
							<button class="portal-post-bookmark-trigger portal-button button-ghost sd-p-6 sd-flex sd-items-center <?php echo esc_attr( $bookmarked ); ?>" data-item_id="<?php echo esc_attr( (string) $p_id ); ?>" title="<?php esc_attr_e( 'Bookmark Post', 'suredash' ); ?>">
								<?php Helper::get_library_icon( 'Bookmark', true ); ?>
							</button>
							<?php
							$current_user_id    = get_current_user_id();
							$is_post_author     = absint( $author_id_post ) === $current_user_id;
							$is_portal_manager  = function_exists( 'suredash_is_user_manager' ) && suredash_is_user_manager( $current_user_id );
							$is_discussion_post = $portal_post_type === SUREDASHBOARD_FEED_POST_TYPE;
							$space_id           = isset( $base_post_id ) ? absint( $base_post_id ) : 0;
							$show_share_button  = $space_id ? PostMeta::get_post_meta_value( $space_id, 'show_share_button' ) : true;
							$show_copy_url      = $show_share_button;
							$has_menu_items     = ( $is_discussion_post && ( $is_post_author || $is_portal_manager ) ) || $show_copy_url;
							?>
							<?php if ( $has_menu_items ) { ?>
							<button class="portal-post-menu-trigger portal-button button-ghost sd-p-6 sd-hover-bg-secondary" aria-label="<?php esc_attr_e( 'Post actions', 'suredash' ); ?>" data-post-id="<?php echo esc_attr( (string) $p_id ); ?>">
								<?php Helper::get_library_icon( 'Ellipsis' ); ?>
							</button>
							<div class="portal-thread-dropdown portal-content portal-post-dropdown" data-post-id="<?php echo esc_attr( (string) $p_id ); ?>" style="display: none;">
								<?php if ( $is_discussion_post && ( $is_post_author || $is_portal_manager ) ) { ?>
									<button class="portal-thread-edit" data-post-id="<?php echo esc_attr( (string) $p_id ); ?>">
										<?php esc_html_e( 'Edit', 'suredash' ); ?>
									</button>
								<?php } ?>
								<?php if ( $is_discussion_post && ( $is_post_author || $is_portal_manager ) ) { ?>
									<button class="portal-thread-delete" data-post-id="<?php echo esc_attr( (string) $p_id ); ?>">
										<?php esc_html_e( 'Delete', 'suredash' ); ?>
									</button>
								<?php } ?>
								<?php if ( $show_copy_url ) { ?>
								<button class="portal-thread-copy-url" data-post-id="<?php echo esc_attr( (string) $p_id ); ?>" data-post-url="<?php echo esc_url( (string) get_permalink( absint( $p_id ) ) ); ?>">
									<?php esc_html_e( 'Copy URL', 'suredash' ); ?>
								</button>
								<?php } ?>
							</div>
							<?php } ?>
						</div>
						<?php
			}
			?>
			</div>
		</div>
	</section>

	<?php Helper::suredash_featured_cover( $p_id ); ?>

	<div class="sd-p-container sd-force-pt-0">
		<div class="portal-space-post-content">
			<h3 class="portal-store-post-title"><?php echo esc_html( $post_title ); ?></h3>
			<?php
			// Process content based on whether it's excerpt or full content.
			if ( $content_type === 'excerpt' ) {
				// Build the Read More link.
				$read_more_link = sprintf(
					'<a href="%1$s" data-post_id="%2$s" data-post_type="%3$s" data-comments="%4$s" class="portal-read-more-post more-link sd-font-16 sd-font-semibold" style="text-decoration: none !important;">%5$s</a>',
					esc_url( $permalink ),
					esc_attr( (string) $p_id ),
					esc_attr( $p_type ),
					esc_attr( (string) $p_comments ),
					sprintf( /* translators: %1$s Read More, %2$s Post title markup */'%1$s %2$s', esc_html( Labels::get_label( 'read_more' ) ), '<span class="screen-reader-text">' . esc_html( $post_title ) . '</span>' )
				);

				// Add ellipsis and Read More link inline.
				$ellipsis_and_link = '<span class="more-link-ellipsis"> ... </span>' . $read_more_link;

				// Find the last closing tag and insert before it to keep inline.
				$pos = strrpos( $post_content, '</' );
				if ( $pos !== false ) {
					$post_content = substr_replace( $post_content, $ellipsis_and_link, $pos, 0 );
				} else {
					$post_content .= $ellipsis_and_link;
				}

				// Allow custom data attributes in the content.
				$allowed_html                        = wp_kses_allowed_html( 'post' );
				$allowed_html['a']['data-post_id']   = true;
				$allowed_html['a']['data-post_type'] = true;
				$allowed_html['a']['data-comments']  = true;

				echo '<div class="sd-m-0">' . wp_kses( $post_content, $allowed_html ) . '</div>';
			} else {
				echo do_shortcode( suredash_render_post_content( $post_content ) );
			}
			?>
		</div>

		<?php
		if ( is_user_logged_in() ) {
			ob_start();
			Helper::render_post_reaction( $p_id, 'portal-comments-block', boolval( $p_comments ) );
			$reaction_html = (string) ob_get_clean();

			if ( ! empty( trim( $reaction_html ) ) ) {
				?>
				<div class="portal-comments-wrapper sd-w-full sd-mt-16 sd-border-t">
					<?php echo $reaction_html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Output from Helper::render_post_reaction is already escaped. ?>
				</div>
				<?php
			}
		} elseif ( boolval( $p_comments ) ) {
			?>
					<div class="portal-comments-wrapper sd-w-full sd-mt-16 sd-border-t">
					<?php Helper::get_login_notice( 'comment' ); ?>
					</div>
				<?php
		}
		?>
	</div>
</div>
<?php

<?php
/**
 * The template for displaying space content area view.
 *
 * @see     https://developer.wordpress.org/themes/basics/template-hierarchy/
 * @package SureDash\Templates
 * @version 1.0.0
 */

defined( 'ABSPATH' ) || exit;

use SureDashboard\Inc\Utils\Helper;

$p_id       = absint( ! empty( $_GET['post_id'] ) ? $_GET['post_id'] : 0 ); // phpcs:ignore

if ( ! $p_id ) {
	return;
}

$p_title     = get_the_title( $p_id );
$p_comments = absint( ! empty( $_GET['comments'] ) ? $_GET['comments'] : 0 ); // phpcs:ignore
$integration = ! empty( $_GET['integration'] ) ? sanitize_text_field( $_GET['integration'] ) : ''; // phpcs:ignore
$post_author = sd_get_post_field( $p_id, 'post_author' );
$author_id   = absint( $post_author );
$user_view   = suredash_get_user_view_link( $author_id );

// Identify space first for further use.
$integration_resource_library = $integration === 'resource_library' ? true : false;

$permalink     = (string) get_permalink( $p_id );
$show_comments = comments_open( $p_id );

// Hide fullscreen button for resource posts with empty content.
$post_content    = sd_get_post_field( $p_id, 'post_content' );
$hide_fullscreen = $integration_resource_library && empty( trim( $post_content ) );

?>
<!DOCTYPE html>
<html <?php language_attributes(); ?> class="suredash-quick-post">
	<head>
		<meta charset="<?php bloginfo( 'charset' ); ?>" />
		<meta name="viewport" content="width=device-width, initial-scale=1" />
		<?php do_action( 'suredash_enqueue_scripts' ); ?>
		<?php wp_head(); ?>
	</head>

	<body <?php body_class( 'suredash-quick-view-content' ); ?>>
		<?php wp_body_open(); ?>

		<div id="portal-post-<?php echo esc_attr( (string) $p_id ); ?>" class="portal-qv-post portal-content portal-container sd-force-p-0">
			<div class="sd-flex sd-justify-between sd-px-20 sd-py-16 sd-border-b sd-max-w-full" style="--sd-pt-custom: 100px;">
				<div class="sd-flex sd-items-center sd-no-space sd-gap-8 sd-flex-1 sd-min-w-0">
					<div class="sd-no-space sd-max-w-custom" style="--sd-max-w-custom: 90%;">
						<?php
						if ( ! $integration_resource_library ) {
							?>
									<div class="portal-qv-author-header">
										<div class="portal-store-post-author-wrap sd-w-full sd-flex sd-items-center">
										<?php suredash_get_user_avatar( $author_id ); ?>

											<div class="portal-post-author sd-flex-col sd-gap-6 sd-font-base sd-line-20">
												<a href="<?php echo esc_url( $user_view ); ?>" target="_blank" class="sd-mobile-flex-wrap">
													<span class="portal-store-post-author" aria-label="<?php /* translators: %s: author name */ echo esc_attr( sprintf( __( 'Post author %s', 'suredash' ), suredash_get_author_name( get_the_author_meta( 'display_name', $author_id ) ) ) ); ?>" ><?php echo esc_html( suredash_get_author_name( get_the_author_meta( 'display_name', $author_id ) ) ); ?></span>
												<?php
												do_action( 'suredash_before_user_badges', $author_id );
												suredash_get_user_badges( $author_id, 2 );
												?>
												</a>
												<a href="<?php echo esc_url( $permalink ); ?>" target="_blank" class="portal-thread-details">
												<?php
													$user_headline = sd_get_user_meta( $author_id, 'headline', true );
												if ( ! empty( $user_headline ) ) {
													?>
															<span class="sd-font-12 sd-user-headline" title="<?php echo esc_attr( strval( $user_headline ) ); ?>"><?php echo esc_html( strval( $user_headline ) ); ?></span><span class="portal-reaction-separator sd-no-space"></span>
														<?php
												}

													suredash_get_relative_time( $p_id );

													$edited_time = sd_get_post_meta( $p_id, 'suredash_post_edited', true );
												if ( ! empty( $edited_time ) ) {
													?>
															<span class="portal-reaction-separator sd-no-space"></span>  <span class="portal-comment-edited sd-font-12">(<?php echo esc_attr( __( 'Edited', 'suredash' ) ); ?>)</span>
															<?php
												}
												?>
												</a>
											</div>
										</div>
									</div>
								<?php
						}

						if ( $integration_resource_library ) {
							?>
									<h2 class="sd-no-space" style="font-size: 20px;" title="<?php echo esc_attr( $p_title ); ?>">
									<?php echo esc_html( $p_title ); ?>
									</h2>
								<?php
						}
						?>
					</div>
				</div>

				<div class="sd-flex sd-items-center sd-shrink-0">
					<?php
					do_action( 'suredash_post_visibility_button', $p_id );

					if ( is_user_logged_in() && ! suredash_content_post() ) {
						$bookmarked = suredash_is_item_bookmarked( absint( $p_id ) );
						$bookmarked = $bookmarked ? 'bookmarked' : '';
						?>
						<div class="sd-flex sd-items-center sd-justify-center">
							<button class="portal-post-bookmark-trigger portal-button button-ghost sd-flex sd-items-center <?php echo esc_attr( $bookmarked ); ?>" data-item_id="<?php echo esc_attr( (string) $p_id ); ?>" title="<?php esc_attr_e( 'Bookmark Post', 'suredash' ); ?>">
								<?php Helper::get_library_icon( 'Bookmark', true ); ?>
							</button>
						</div>
						<?php
					}
					if ( ! $hide_fullscreen ) {
						?>
						<a href="<?php echo esc_url( $permalink ); ?>" id="ast-quick-view-fullscreen" role="button" class="ast-quick-view-fullscreen-btn portal-button button-ghost sd-flex sd-items-center sd-text-color tooltip-trigger" target="_blank" rel="noopener noreferrer" data-tooltip-description="<?php echo esc_attr__( 'Open in full page', 'suredash' ); ?>" data-tooltip-position="bottom">
							<?php Helper::get_library_icon( 'Maximize', true, 'sm' ); ?>
						</a>
						<?php
					}
					Helper::get_library_icon(
						'X',
						true,
						'sm',
						'portal-button button-ghost ast-quick-view-close-btn tooltip-trigger',
						[
							'tooltip-description' => esc_attr__( 'Close', 'suredash' ),
							'tooltip-position'    => 'bottom',
						]
					);
					?>
				</div>
			</div>

			<?php
			if ( $integration_resource_library ) {
				do_action( 'suredash_quick_view_resource_content', $p_id, $hide_fullscreen );
			}

			if ( ! $hide_fullscreen ) {
				do_action( 'suredashboard_quick_view_post_content', $p_id, $p_comments, $integration );
			}
			?>
		</div>

		<?php if ( ! is_user_logged_in() && $show_comments ) { ?>
			<div class="sd-pt-16">
				<div class="comment-modal-login-notice sd-w-full sd-flex sd-items-center sd-justify-center sd-radius-6 sd-px-20 sd-py-10">
					<?php Helper::get_login_notice( 'comment' ); ?>
				</div>
			</div>
		<?php } ?>

		<?php
		// Render the post reaction modal inside the quick-view iframe so visibility/likes/comments popups work.
		\SureDashboard\Core\RewriteRules::get_instance()->add_post_reaction_modal();

		wp_footer();
		?>
		<script>
			document.addEventListener('DOMContentLoaded', function() {
				const closeBtn = document.querySelector('.ast-quick-view-close-btn');
				if (closeBtn) {
					closeBtn.style.cursor = 'pointer';
					closeBtn.addEventListener('click', function(e) {
						e.preventDefault();
						// Send message to parent window to close the modal
						window.parent.postMessage('closeQuickView', '*');
					});
				}

				// Scroll to comments and focus the editor.
				function scrollToComments() {
					var commentsSection = document.querySelector('.portal-qv-post .portal-comments-wrapper') || document.querySelector('.portal-qv-post .portal-comment-list');
					if (commentsSection) {
						commentsSection.scrollIntoView({ behavior: 'smooth', block: 'start' });
					}
					var focusInterval = setInterval(function() {
						var joditEditor = document.querySelector('.portal-qv-post .comment-markup:not(.hidden-comment-markup) .jodit-wysiwyg');
						if (joditEditor) {
							clearInterval(focusInterval);
							joditEditor.focus();
						}
					}, 200);
					setTimeout(function() { clearInterval(focusInterval); }, 5000);
				}

				// Listen for messages from parent window.
				window.addEventListener('message', function(e) {
					if (e.origin !== window.location.origin) {
						return;
					}
					if (e.data && e.data.action === 'focusComments') {
						scrollToComments();
					} else if (e.data && e.data.action === 'scrollToTop') {
						// The actual scroll container is .sd-post-content-wrapper (html/body have overflow:hidden).
						var scrollContainer = document.querySelector('.sd-post-content-wrapper');
						if (scrollContainer) {
							scrollContainer.scrollTop = 0;
						}
						// Blur Jodit editor so the comment box collapses.
						var activeJodit = document.querySelector('.portal-qv-post .comment-markup:not(.hidden-comment-markup) .jodit-wysiwyg');
						if (activeJodit) {
							activeJodit.blur();
						}
					}
				});

				// Backward compatibility: also check URL parameter.
				var params = new URLSearchParams(window.location.search);
				if (params.get('focus_comment') === '1') {
					scrollToComments();
				}
			});
		</script>
	</body>
</html>

<?php wp_reset_postdata(); ?>

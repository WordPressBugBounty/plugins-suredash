<?php
/**
 * Post Widget Renderer.
 *
 * @package SureDash
 * @since 1.6.0
 */

namespace SureDashboard\Core\Shortcodes\Widgets;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Post Widget Class.
 */
class Post_Widget {
	/**
	 * Render the Post widget.
	 *
	 * @param array<mixed> $settings Widget settings.
	 * @param int          $space_id Space ID.
	 * @return void
	 * @since 1.6.0
	 */
	public static function render( $settings, $space_id ): void {
		$post_ids     = $settings['postIds'] ?? [];
		$custom_title = $settings['customTitle'] ?? '';

		// Support legacy single postId.
		if ( empty( $post_ids ) && ! empty( $settings['postId'] ) ) {
			$post_ids = [ $settings['postId'] ];
		}

		// Ensure post_ids is an array.
		if ( ! is_array( $post_ids ) ) {
			$post_ids = [ $post_ids ];
		}

		// Extract post IDs from the array structure.
		$clean_post_ids = [];
		foreach ( $post_ids as $post_data ) {
			if ( is_array( $post_data ) && isset( $post_data['value'] ) ) {
				$clean_post_ids[] = absint( $post_data['value'] );
			} elseif ( is_numeric( $post_data ) ) {
				$clean_post_ids[] = absint( $post_data );
			}
		}

		?>
		<div class="portal-widget-posts">
			<?php if ( ! empty( $custom_title ) ) { ?>
			<span class="portal-widget-section-title">
				<?php echo esc_html( $custom_title ); ?>
			</span>
			<?php } ?>

			<div class="portal-widget-posts-list sd-flex-col sd-gap-16">
				<?php
				if ( empty( $clean_post_ids ) ) {
					?>
					<div class="portal-widget-empty">
						<p><?php esc_html_e( 'No posts available.', 'suredash' ); ?></p>
					</div>
					<?php
				} else {
					$has_posts = false;
					foreach ( $clean_post_ids as $post_id ) {
						$post = get_post( $post_id );

						if ( ! $post || $post->post_status !== 'publish' ) {
							continue;
						}

						// Skip posts the current user cannot access (visibility_scope or SureMembers).
						if ( function_exists( 'suredash_is_post_protected' ) && suredash_is_post_protected( $post_id ) ) {
							continue;
						}

						$has_posts      = true;
						$post_title     = get_the_title( $post );
						$featured_image = get_the_post_thumbnail( $post, 'thumbnail' );
						$permalink      = get_permalink( $post );
						$placeholder    = SUREDASHBOARD_URL . 'assets/images/placeholder.jpg';
						?>

						<div class="portal-widget-post">
							<div class="portal-widget-post-image">
								<a href="<?php echo esc_url( $permalink ); ?>">
									<?php if ( $featured_image ) { ?>
										<?php // phpcs:ignore
										echo $featured_image; ?>
									<?php } else { ?>
										<img src="<?php echo esc_url( $placeholder ); ?>" alt="<?php echo esc_attr( $post_title ); ?>" />
									<?php } ?>
								</a>
							</div>

							<div class="portal-widget-post-content">
								<span class="portal-widget-post-title">
									<a href="<?php echo esc_url( $permalink ); ?>">
										<?php echo esc_html( $post_title ); ?>
									</a>
								</span>
								<div class="portal-widget-post-meta">
									<span class="portal-widget-post-author">
										<?php echo esc_html( get_the_author_meta( 'display_name', (int) $post->post_author ) ); ?>
									</span>
									<span class="portal-widget-separator">•</span>
									<span class="portal-widget-post-date">
										<?php suredash_get_relative_time( $post_id ); ?>
									</span>
								</div>
							</div>
						</div>

						<?php
					}

					if ( ! $has_posts ) {
						?>
						<div class="portal-widget-empty">
							<p><?php esc_html_e( 'No posts available.', 'suredash' ); ?></p>
						</div>
						<?php
					}
				}
				?>
			</div>
		</div>
		<?php
	}
}

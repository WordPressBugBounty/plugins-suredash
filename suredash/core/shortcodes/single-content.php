<?php
/**
 * Portals Single Content Shortcode Initialize.
 *
 * @package SureDash
 */

namespace SureDashboard\Core\Shortcodes;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

use SureDashboard\Core\Integrations\Feeds;
use SureDashboard\Core\Integrations\SinglePost;
use SureDashboard\Inc\Traits\Get_Instance;
use SureDashboard\Inc\Traits\Shortcode;
use SureDashboard\Inc\Utils\Helper;
use SureDashboard\Inc\Utils\PostMeta;

/**
 * Class SingleContent Shortcode.
 */
class SingleContent {
	use Shortcode;
	use Get_Instance;

	/**
	 * Register_shortcode_event.
	 *
	 * @return void
	 */
	public function register_shortcode_event(): void {
		$this->add_shortcode( 'single_content' );
	}

	/**
	 * Load integration type wise content.
	 *
	 * @param array<mixed> $atts Array of attributes.
	 * @since 1.0.0
	 */
	public function render_single_content_markup( $atts ): void {
		$post_id     = absint( $atts['post_id'] );
		$integration = $atts['integration'];
		$layout      = strval( $atts['layout'] );
		$style       = strval( $atts['layout_style'] );

		$layout_details = Helper::get_layout_details( $layout, $style );
		$layout         = $layout_details['layout'];
		$layout_style   = $layout_details['style'];

		$is_preview = is_singular( SUREDASHBOARD_FEED_POST_TYPE );

		// Get featured image markup for use in switch cases.
		$image_markup = Helper::get_space_banner_image( $post_id, false );

		remove_filter( 'the_content', 'wpautop' );

		switch ( $integration ) {
			case 'course':
				if ( is_callable( 'suredash_pro_course_integration_content' ) ) {
					echo '<div id="portal-post-' . esc_attr( (string) $post_id ) . '" class="portal-content-area portal-content portal-content-type-' . esc_attr( $integration ) . '">';
					$this->render_banner( $image_markup );
					echo do_shortcode( apply_filters( 'the_content', suredash_course_integration_content( $post_id ), $post_id ) );
				} else {
					suredash_get_template_part(
						'parts',
						'404',
						[
							'404_heading'    => __( 'Premium Integration', 'suredash' ),
							'not_found_text' => __( 'This feature is available in the premium version of SureDash.', 'suredash' ),
						]
					);
					return;
				}
				break;

			case 'collection':
				// Get layout style from collection space settings.
				$layout_type = sd_get_post_meta( $post_id, 'content_layout_style', true );
				if ( empty( $layout_type ) ) {
					$layout_type = 'grid'; // Default to grid/card layout.
				}

				if ( is_callable( 'suredash_pro_get_collection_space_content' ) ) {
					echo '<div id="portal-post-' . esc_attr( (string) $post_id ) . '" class="portal-content-area sd-box-shadow portal-content portal-content-type-collection' . esc_attr( $layout_type === 'grid' ? ' narrow-grid-row' : '' ) . '">';
					$this->render_banner( $image_markup );
					echo do_shortcode( apply_filters( 'the_content', suredash_collection_space_integration_content( $post_id ), $post_id ) );
				} else {
					suredash_get_template_part(
						'parts',
						'404',
						[
							'404_heading'    => __( 'Premium Integration', 'suredash' ),
							'not_found_text' => __( 'This feature is available in the premium version of SureDash.', 'suredash' ),
						]
					);
					return;
				}
				break;

			case 'events':
				// Get layout style from collection space settings.
				$layout_type = sd_get_post_meta( $post_id, 'content_layout_style', true );
				if ( empty( $layout_type ) ) {
					$layout_type = 'grid'; // Default to grid/card layout.
				}

				if ( function_exists( 'suredash_pro_get_event_space_content' ) ) {
					echo '<div id="portal-post-' . esc_attr( (string) $post_id ) . '" class="portal-content-area sd-box-shadow portal-content portal-content-type-events ' . esc_attr( $layout_type === 'grid' ? 'narrow-grid-row' : '' ) . '">';
					$this->render_banner( $image_markup );
					echo do_shortcode( apply_filters( 'the_content', suredash_event_space_integration_content( $post_id ), $post_id ) );
				} else {
					suredash_get_template_part(
						'parts',
						'404',
						[
							'404_heading'    => __( 'Premium Integration', 'suredash' ),
							'not_found_text' => __( 'This feature is available in the premium version of SureDash.', 'suredash' ),
						]
					);
					return;
				}
				break;

			case 'single_post': // phpcs:ignore -- Spell auto-corrects to 'WordPress' which is not intended here.
				$wrapper_class = $layout === 'full_width' && $layout_style === 'unboxed' ? '' : 'portal-content-area sd-' . esc_attr( $layout_style ) . '-post'; // If layout is full width and layout style is unboxed then let user design their own content-section.
				echo '<div id="portal-post-' . esc_attr( (string) $post_id ) . '" class="' . esc_attr( $wrapper_class ) . '">';
				$this->render_banner( $image_markup );
				if ( method_exists( SinglePost::get_instance(), 'get_integration_content' ) ) {
					echo do_shortcode( apply_filters( 'the_content', SinglePost::get_instance()->get_integration_content( $post_id ), $post_id ) );
				}
				break;

			case 'posts_discussion':
				echo '<div id="portal-post-' . esc_attr( (string) $post_id ) . '" class="portal-content-area portal-content-type-' . esc_attr( $integration ) . '">';
				if ( apply_filters( 'suredash_show_discussion_space_content', true, $post_id ) ) {
					$this->render_banner( $image_markup );
					if ( method_exists( Feeds::get_instance(), 'get_integration_content' ) ) {
						echo do_shortcode( apply_filters( 'the_content', Feeds::get_instance()->get_integration_content( $post_id ), $post_id ) );
					}
				} else {
					$restriction_html = apply_filters( 'suredash_discussion_space_restriction_content', '', $post_id );
					if ( ! empty( $restriction_html ) ) {
						echo do_shortcode( $restriction_html ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- trusted template output, may contain shortcodes
					} else {
						suredash_get_restricted_template_part(
							0,
							'parts',
							'restricted',
							[
								'icon'                   => 'Lock',
								'label'                  => 'restricted_content',
								'description'            => 'restricted_content_description',
								'skip_restriction_check' => true,
							]
						);
					}
				}
				break;

			case 'resource_library':
				// Get layout style from collection space settings.
				$layout_type = sd_get_post_meta( $post_id, 'content_layout_style', true );
				if ( empty( $layout_type ) ) {
					$layout_type = 'grid'; // Default to grid/card layout.
				}
				if ( function_exists( 'suredash_pro_resource_space_integration_content' ) ) {
					echo '<div id="portal-post-' . esc_attr( (string) $post_id ) . '" class="portal-content-area portal-content-type-resource-library' . esc_attr( $layout_type === 'grid' ? ' narrow-grid-row' : '' ) . '">';
					$this->render_banner( $image_markup );
					echo do_shortcode( apply_filters( 'the_content', suredash_resource_space_integration_content( $post_id ), $post_id ) );
				} else {
					suredash_get_template_part(
						'parts',
						'404',
						[
							'404_heading'    => __( 'Premium Integration', 'suredash' ),
							'not_found_text' => __( 'Resource Space is available in the premium version of SureDash.', 'suredash' ),
						]
					);
					return;
				}
				break;

			default:
				add_action( 'suredash_footer', [ Feeds::get_instance(), 'add_post_edit_modal' ] );
				echo '<div id="portal-post-' . esc_attr( (string) $post_id ) . '" class="portal-content-area portal-content sd-' . esc_attr( $layout_style ) . '-post">';
				$this->render_banner( $image_markup );
				$this->render_post_title_with_menu( $post_id );
				if ( method_exists( SinglePost::get_instance(), 'render_content' ) ) {
					echo do_shortcode( apply_filters( 'the_content', SinglePost::get_instance()->render_content( $post_id ), $post_id ) );
				}
				break;
		}

		$space_id    = sd_get_space_id_by_post( $post_id );
		$id          = $space_id ? $space_id : $post_id;
		$integration = $space_id ? sd_get_post_meta( (int) $space_id, 'integration', true ) : $integration;
		$is_comment  = $integration === 'single_post' ? sd_get_post_meta( (int) $id, 'comments', true ) : sd_get_post_field( (int) $id, 'comment_status' ) === 'open';

		if ( $integration === 'single_post' || $is_preview ) {
			echo do_shortcode( '[portal_single_comments comments="' . esc_attr( $is_comment ) . '"]' );
		}
		echo '</div>'; // End of portal-content-area.

		add_filter( 'the_content', 'wpautop' );
	}

	/**
	 * Display Single Post Content.
	 *
	 * @param array<mixed> $atts Array of attributes.
	 * @since 1.0.0
	 *
	 * @return string|null
	 */
	public function render_single_content( $atts ) {
		if ( ! empty( $atts['post_id'] ) ) {
			$post_id = absint( $atts['post_id'] );
		} else {
			global $post;
			$post_id = ! empty( $post->ID ) ? absint( $post->ID ) : 0;
		}

		$integration  = PostMeta::get_post_meta_value( $post_id, 'integration' );
		$emoji        = PostMeta::get_post_meta_value( $post_id, 'item_emoji' );
		$layout       = PostMeta::get_post_meta_value( $post_id, 'layout' );
		$layout_style = PostMeta::get_post_meta_value( $post_id, 'layout_style' );

		// Community posts should always use 'boxed' style regardless of global settings.
		$post_type = get_post_type( $post_id );
		if ( $post_type === SUREDASHBOARD_FEED_POST_TYPE ) {
			$layout_style = 'boxed';
		} else {
			$layout_style = Helper::get_layout_style( $layout_style );
		}

		$defaults = [
			'integration'        => $integration,
			'emoji'              => $emoji,
			'post_id'            => $post_id,
			'use_passed_post_id' => false,
			'skip_comments'      => $integration === 'course' ? true : false,
			'skip_header'        => false,
			'layout'             => $layout,
			'layout_style'       => $layout_style,
		];

		$atts        = shortcode_atts( $defaults, $atts );
		$skip_header = boolval( $atts['skip_header'] );
		$emoji       = $atts['emoji'];
		$post_id     = absint( $atts['post_id'] );

		if ( ! $post_id ) {
			return null;
		}

		// If its singular post get the post title or consider as archive tax title.
		$post_title = is_singular() ? get_the_title( $post_id ) : single_term_title( '', false );

		ob_start();

		if ( ! $skip_header ) {
			echo do_shortcode( '[portal_content_header emoji="' . $emoji . '" title="' . $post_title . '"]' );
		}

		do_action( 'suredashboard_before_single_content_load', $post_id );

		if ( suredash_is_post_protected( $post_id ) ) {
			suredash_get_restricted_template_part(
				$post_id,
				'parts',
				'restricted',
				[
					'icon'        => 'Lock',
					'label'       => 'restricted_content',
					'description' => 'restricted_content_description',
				]
			);
		} else {
			$this->render_single_content_markup( $atts );
		}

		$content = apply_filters( 'suredashboard_single_view_content', ob_get_clean() );

		do_action( 'suredashboard_after_single_content_load', $post_id );

		return $content;
	}

	/**
	 * Render banner.
	 *
	 * @param string $image_markup Image markup.
	 * @return void
	 */
	private function render_banner( $image_markup ): void {
		if ( ! empty( $image_markup ) ) {
			echo sprintf(
				'<div class="portal-item-featured-image-wrap"> %1$s </div>',
				wp_kses_post( $image_markup )
			);
		}
	}

	/**
	 * Render the post title with three-dot menu for single content view.
	 *
	 * @param int $post_id The post ID.
	 * @return void
	 * @since 1.4.0
	 */
	private function render_post_title_with_menu( $post_id ): void {
		if ( ! suredash_cpt() ) {
			return;
		}

		$post_title  = sd_get_post_field( $post_id );
		$post_author = sd_get_post_field( $post_id, 'post_author' );
		$author_id   = absint( $post_author );
		$user_view   = suredash_get_user_view_link( $author_id );
		$permalink   = strval( get_permalink( $post_id ) );

		?>
		<section class="portal-store-post-header sd-mb-20">
			<div class="portal-store-post-author-data sd-flex sd-justify-between sd-items-start">
				<div class="portal-store-post-author-wrap sd-w-full sd-flex sd-items-center">
					<?php suredash_get_user_avatar( $author_id ); ?>

					<div class="portal-post-author sd-flex-col sd-gap-6 sd-font-base sd-line-20">
						<a href="<?php echo esc_url( $user_view ); ?>" class="sd-mobile-flex-wrap">
							<span class="portal-store-post-author" aria-label="<?php /* translators: %s: author name */ echo esc_attr( sprintf( __( 'Post author %s', 'suredash' ), suredash_get_author_name( get_the_author_meta( 'display_name', $author_id ) ) ) ); ?>">
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

								suredash_get_relative_time( $post_id );

								$edited_time = sd_get_post_meta( $post_id, 'suredash_post_edited', true );
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
					/** Fires to render post visibility button (members popup). */
					do_action( 'suredash_post_visibility_button', $post_id );
					?>
					<?php
					if ( is_user_logged_in() && ! suredash_content_post() ) {
						$bookmarked       = suredash_is_item_bookmarked( absint( $post_id ) );
						$bookmarked       = $bookmarked ? 'bookmarked' : '';
						$portal_post_type = sd_get_post_field( absint( $post_id ), 'post_type' );
						?>
						<div class="sd-flex sd-items-center sd-justify-center sd-gap-6">
							<button class="portal-post-bookmark-trigger portal-button button-ghost sd-p-6 sd-flex sd-items-center <?php echo esc_attr( $bookmarked ); ?>" data-item_id="<?php echo esc_attr( (string) $post_id ); ?>" title="<?php esc_attr_e( 'Bookmark Post', 'suredash' ); ?>">
								<?php Helper::get_library_icon( 'Bookmark', true ); ?>
							</button>
							<?php
							$current_user_id    = get_current_user_id();
							$is_post_author     = absint( $author_id ) === $current_user_id;
							$is_portal_manager  = function_exists( 'suredash_is_user_manager' ) && suredash_is_user_manager( $current_user_id );
							$is_discussion_post = $portal_post_type === SUREDASHBOARD_FEED_POST_TYPE;
							$space_id           = sd_get_space_id_by_post( $post_id );
							$show_share_button  = $space_id ? PostMeta::get_post_meta_value( (int) $space_id, 'show_share_button' ) : true;
							$show_copy_url      = $show_share_button;
							$has_menu_items     = ( $is_discussion_post && ( $is_post_author || $is_portal_manager ) ) || $show_copy_url;
							?>
							<?php if ( $has_menu_items ) { ?>
							<button class="portal-post-menu-trigger portal-button button-ghost sd-p-6 sd-hover-bg-secondary" aria-label="<?php esc_attr_e( 'Post actions', 'suredash' ); ?>" data-post-id="<?php echo esc_attr( (string) $post_id ); ?>">
								<?php Helper::get_library_icon( 'Ellipsis' ); ?>
							</button>
							<div class="portal-thread-dropdown portal-content portal-post-dropdown" data-post-id="<?php echo esc_attr( (string) $post_id ); ?>" style="display: none;">
								<?php if ( $is_discussion_post && ( $is_post_author || $is_portal_manager ) ) { ?>
									<button class="portal-thread-edit" data-post-id="<?php echo esc_attr( (string) $post_id ); ?>">
										<?php esc_html_e( 'Edit', 'suredash' ); ?>
									</button>
								<?php } ?>
								<?php if ( $is_discussion_post && ( $is_post_author || $is_portal_manager ) ) { ?>
									<button class="portal-thread-delete" data-post-id="<?php echo esc_attr( (string) $post_id ); ?>">
										<?php esc_html_e( 'Delete', 'suredash' ); ?>
									</button>
								<?php } ?>
								<?php if ( $show_copy_url ) { ?>
								<button class="portal-thread-copy-url" data-post-id="<?php echo esc_attr( (string) $post_id ); ?>" data-post-url="<?php echo esc_url( $permalink ); ?>">
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

		<?php Helper::suredash_featured_cover( $post_id ); ?>

		<div class="portal-post-title-header">
			<div class="portal-space-post-content">
				<h1 class="portal-store-post-title"><?php echo esc_html( $post_title ); ?></h1>
			</div>
		</div>
		<?php
	}
}

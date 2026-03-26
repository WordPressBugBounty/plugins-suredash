<?php
/**
 * Portals Docs EndpointNavigation Shortcode Initialize.
 *
 * @package SureDash
 */

namespace SureDashboard\Core\Shortcodes;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

use SureDashboard\Inc\Traits\Get_Instance;
use SureDashboard\Inc\Traits\Shortcode;
use SureDashboard\Inc\Utils\Helper;
use SureDashboard\Inc\Utils\Labels;

/**
 * Class EndpointNavigation Shortcode.
 */
class EndpointNavigation {
	use Shortcode;
	use Get_Instance;

	/**
	 * Register_shortcode_event.
	 *
	 * @return void
	 */
	public function register_shortcode_event(): void {
		$this->add_shortcode( 'endpoint_navigation' );
	}

	/**
	 * Display docs endpoint navigation.
	 *
	 * @param array<mixed> $atts Array of attributes.
	 * @since 1.0.0
	 * @return string|false markup.
	 */
	public function render_endpoint_navigation( $atts ) {
		$defaults = [
			'endpoint' => '',
		];

		$atts = shortcode_atts( $defaults, $atts );

		if ( empty( $atts['endpoint'] ) ) {
			return '';
		}

		ob_start();

		$this->process_endpoint_nav_query( $atts['endpoint'] );

		return ob_get_clean();
	}

	/**
	 * Update the site navigation based on the endpoint data.
	 *
	 * @param string $endpoint The endpoint type (lesson, resource, etc.).
	 * @since 1.0.0
	 * @return mixed
	 */
	public function process_endpoint_nav_query( $endpoint ) {
		if ( ! is_singular( SUREDASHBOARD_SUB_CONTENT_POST_TYPE ) ) {
			return;
		}

		$content_id = get_the_ID();
		if ( ! $content_id ) {
			return;
		}

		// Configuration for different endpoint types.
		$endpoint_config = [
			'lesson'   => [
				'meta_key'           => 'belong_to_course',
				'data_key'           => 'course_loop',
				'render_method'      => 'render_lesson_navigation',
				'header'             => class_exists( '\SureDashboardPro\Inc\Utils\Labels' )
					? \SureDashboardPro\Inc\Utils\Labels::get_label( 'lesson_plural_text' )
					: Labels::get_label( 'lesson_plural_text' ),
				'bookmark_item_type' => 'lesson',
				'has_progress'       => true,
			],
			'resource' => [
				'meta_key'           => 'space_id',
				'data_key'           => 'resource_loop',
				'render_method'      => 'render_resource_navigation',
				'header'             => __( 'Resources', 'suredash' ),
				'bookmark_item_type' => 'resource',
				'has_progress'       => false,
			],
			'event'    => [
				'meta_key'           => 'space_id',
				'data_key'           => 'event_loop',
				'render_method'      => 'render_event_navigation',
				'header'             => __( 'Events', 'suredash' ),
				'bookmark_item_type' => 'event',
				'has_progress'       => false,
			],
		];

		// Validate endpoint type.
		if ( ! isset( $endpoint_config[ $endpoint ] ) ) {
			return;
		}

		$config = $endpoint_config[ $endpoint ];

		// Get base ID using the appropriate meta key.
		$base_id = absint( sd_get_post_meta( (int) $content_id, $config['meta_key'], true ) );
		if ( empty( $base_id ) ) {
			return;
		}

		// Get endpoint data.
		$endpoint_data          = suredash_endpoint_data( (string) $endpoint, $content_id, $base_id );
		$endpoint               = ! empty( $endpoint_data['endpoint'] ) ? $endpoint_data['endpoint'] : $endpoint;
		$collapsible_navigation = apply_filters( 'suredashboard_enable_collapsible_navigation', false );

		// Validate endpoint data using the appropriate data key.
		$loop_data = is_array( $endpoint_data ) && ! empty( $endpoint_data[ $config['data_key'] ] ) ? $endpoint_data[ $config['data_key'] ] : [];
		if ( empty( $loop_data ) ) {
			return;
		}

		// Process lesson progress if needed.
		$lesson_data       = null;
		$lessons_completed = [];
		if ( $config['has_progress'] ) {
			$lessons_completed = sd_get_user_meta( get_current_user_id(), 'portal_course_' . $base_id . '_completed_lessons', true );
			$lessons_completed = is_array( $lessons_completed ) ? $lessons_completed : [];

			$existing_lesson_ids = suredash_get_lesson_ids_from_course_loop( $loop_data );
			$updated_completed   = array_intersect( $lessons_completed, $existing_lesson_ids );

			if ( is_user_logged_in() && $lessons_completed !== $updated_completed ) {
				sd_update_user_meta( get_current_user_id(), 'portal_course_' . $base_id . '_completed_lessons', $updated_completed );
				$lessons_completed = $updated_completed;
			}

			$lesson_data = suredash_get_lesson_oriented_data( $content_id, $loop_data );
		}

		$bookmarked = is_callable( 'suredash_is_item_bookmarked' ) ? suredash_is_item_bookmarked( $content_id ) : false;
		$bookmarked = $bookmarked ? 'bookmarked' : '';

		?>
			<div class="portal-aside-list-wrapper portal-aside-endpoint-navigation">
				<div class="portal-aside-group-wrap <?php echo esc_attr( $collapsible_navigation ? 'pfd-collapsible-enabled' : '' ); ?>">
					<div class="portal-lesson-aside-header">
						<div class="sd-flex sd-justify-between sd-items-center sd-pt-4 sd-pb-20 sd-px-10">
							<h2 class="portal-lesson-aside-title sd-text-color sd-no-space"><?php echo esc_html( $config['header'] ); ?></h2>
							<?php if ( is_user_logged_in() ) { ?>
								<button id="portal-lesson-bookmark" class="portal-post-bookmark-trigger portal-sidebar-bookmark-trigger sd-flex sd-cursor-pointer portal-button button-ghost <?php echo esc_attr( $bookmarked ); ?>"
									data-course_id="<?php echo esc_attr( (string) $base_id ); ?>"
									data-item_id="<?php echo esc_attr( (string) $content_id ); ?>"
									data-item_type="<?php echo esc_attr( $config['bookmark_item_type'] ); ?>"
									aria-label="<?php echo $bookmarked ? esc_attr__( 'Remove bookmark', 'suredash' ) : esc_attr__( 'Add bookmark', 'suredash' ); ?>"
									aria-pressed="<?php echo $bookmarked ? 'true' : 'false'; ?>">
									<?php Helper::get_library_icon( 'Bookmark', true ); ?>
								</button>
							<?php } ?>
						</div>
					</div>
					<?php $this->{ $config['render_method'] }( $endpoint_data ); ?>
				</div>
			</div>
		<?php

		if ( ! is_user_logged_in() ) {
			?>
				<div class="portal-progress-wrapper portal-content">
					<?php
						echo do_shortcode( User_Profile::get_instance()->get_non_logged_in_user_view( [] ) );
					?>
				</div>
			<?php
			return;
		}

		if ( $config['has_progress'] && $lesson_data ) {
			?>
				<div class="portal-progress-wrapper portal-content">
					<span class="sd-no-space"><?php Labels::get_label( 'course_progress', true ); ?></span>
					<?php echo do_shortcode( suredash_get_course_progress_bar( $lesson_data, $lessons_completed ) ); ?>
				</div>
			<?php
		}
	}

	/**
	 * Render lesson navigation.
	 *
	 * @param array<mixed> $endpoint_data Array of endpoint data.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function render_lesson_navigation( $endpoint_data ): void {
		if ( function_exists( 'suredash_pro_render_lesson_navigation' ) ) {
			suredash_pro_render_lesson_navigation( $endpoint_data );
		}
	}

	/**
	 * Render resource navigation.
	 *
	 * @param array<mixed> $endpoint_data Array of endpoint data.
	 *
	 * @since 1.3.0
	 * @return void
	 */
	public function render_resource_navigation( $endpoint_data ): void {
		if ( function_exists( 'suredash_pro_render_resource_navigation' ) ) {
			suredash_pro_render_resource_navigation( $endpoint_data );
		}
	}

	/**
	 * Render event navigation.
	 *
	 * @param array<mixed> $endpoint_data Array of endpoint data.
	 *
	 * @since 1.4.0
	 * @return void
	 */
	public function render_event_navigation( $endpoint_data ): void {
		if ( function_exists( 'suredash_pro_render_event_navigation' ) ) {
			suredash_pro_render_event_navigation( $endpoint_data );
		}
	}
}

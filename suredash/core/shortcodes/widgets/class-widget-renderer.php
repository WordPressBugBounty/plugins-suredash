<?php
/**
 * Widget Renderer - Central class to render sidebar widgets.
 *
 * @package SureDash
 * @since 1.6.0
 */

namespace SureDashboard\Core\Shortcodes\Widgets;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use SureDashboard\Inc\Traits\Get_Instance;

/**
 * Widget Renderer Class.
 */
class Widget_Renderer {
	use Get_Instance;

	/**
	 * Render a widget based on its type.
	 *
	 * @param string       $widget_slug Widget slug/type.
	 * @param array<mixed> $widget      Widget data including settings.
	 * @param int          $space_id    Current space ID.
	 * @return void
	 * @since 1.6.0
	 */
	public static function render( $widget_slug, $widget, $space_id ): void {
		// Allow other plugins (like suredash-pro) to render widgets.
		ob_start();
		do_action( "suredash_render_sidebar_widget_{$widget_slug}", $widget, $space_id );
		$custom_output = ob_get_clean();

		// If action produced output, use it. Otherwise, use built-in renderers.
		if ( ! empty( $custom_output ) ) {
			echo $custom_output; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		} else {
			self::render_builtin_widget( $widget_slug, $widget, $space_id );
		}
	}

	/**
	 * Render built-in widgets.
	 *
	 * @param string       $widget_slug Widget slug/type.
	 * @param array<mixed> $widget      Widget data including settings.
	 * @param int          $space_id    Current space ID.
	 * @return void
	 * @since 1.6.0
	 */
	private static function render_builtin_widget( $widget_slug, $widget, $space_id ): void {
		$settings = $widget['settings'] ?? [];

		switch ( $widget_slug ) {
			case 'about-space':
				self::render_about_space( $space_id );
				break;

			case 'recent-activities':
				self::render_recent_activities( $space_id );
				break;

			case 'content-list':
				self::render_post_widget( $settings, $space_id );
				break;

			case 'info-box':
				self::render_cta_info_box( $settings );
				break;

			case 'profiles':
				self::render_user_details( $settings );
				break;

			default:
				// Widget type not found.
				break;
		}
	}

	/**
	 * Render About Space widget.
	 *
	 * @param int $space_id Space ID.
	 * @return void
	 * @since 1.6.0
	 */
	private static function render_about_space( $space_id ): void {
		require_once __DIR__ . '/about-space.php';
		About_Space::render( $space_id );
	}

	/**
	 * Render Recent Activities widget.
	 *
	 * @param int $space_id Space ID.
	 * @return void
	 * @since 1.6.0
	 */
	private static function render_recent_activities( $space_id ): void {
		require_once __DIR__ . '/recent-activities.php';
		Recent_Activities::render( $space_id );
	}

	/**
	 * Render Post Widget.
	 *
	 * @param array<mixed> $settings Widget settings.
	 * @param int          $space_id Space ID.
	 * @return void
	 * @since 1.6.0
	 */
	private static function render_post_widget( $settings, $space_id ): void {
		require_once __DIR__ . '/post-widget.php';
		Post_Widget::render( $settings, $space_id );
	}

	/**
	 * Render CTA/Info Box widget.
	 *
	 * @param array<mixed> $settings Widget settings.
	 * @return void
	 * @since 1.6.0
	 */
	private static function render_cta_info_box( $settings ): void {
		require_once __DIR__ . '/cta-info-box.php';
		CTA_Info_Box::render( $settings );
	}

	/**
	 * Render User Details widget.
	 *
	 * @param array<mixed> $settings Widget settings.
	 * @return void
	 * @since 1.6.0
	 */
	private static function render_user_details( $settings ): void {
		require_once __DIR__ . '/user-details.php';
		User_Details::render( $settings );
	}
}

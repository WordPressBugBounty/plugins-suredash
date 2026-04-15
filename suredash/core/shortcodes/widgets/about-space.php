<?php
/**
 * About Space Widget Renderer.
 *
 * @package SureDash
 * @since 1.6.0
 */

namespace SureDashboard\Core\Shortcodes\Widgets;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * About Space Widget Class.
 */
class About_Space {
	/**
	 * Render the About Space widget.
	 *
	 * @param int          $space_id Space ID.
	 * @param array<mixed> $settings Widget settings.
	 * @return void
	 * @since 1.6.0
	 */
	public static function render( $space_id, $settings = [] ): void {
		// Get space description from meta.
		$space_description = function_exists( 'sd_get_post_meta' )
			? sd_get_post_meta( $space_id, 'space_description', true )
			: get_post_meta( $space_id, 'space_description', true );

		$title = ! empty( $settings['customTitle'] )
			? $settings['customTitle']
			: __( 'About Space', 'suredash' );

		?>
		<div class="portal-widget-about-space">
			<span class="portal-widget-section-title">
				<?php echo esc_html( $title ); ?>
			</span>
			<div class="portal-widget-content sd-flex-col sd-gap-8">
				<?php
				if ( ! empty( $space_description ) ) {
					echo wp_kses_post( wpautop( $space_description ) );
				} else {
					?>
					<div class="portal-widget-empty">
						<p><?php esc_html_e( 'No overview available.', 'suredash' ); ?></p>
					</div>
					<?php
				}
				?>
			</div>
		</div>
		<?php
	}
}

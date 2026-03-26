<?php
/**
 * CTA/Info Box Widget Renderer.
 *
 * @package SureDash
 * @since 1.6.0
 */

namespace SureDashboard\Core\Shortcodes\Widgets;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * CTA Info Box Widget Class.
 */
class CTA_Info_Box {
	/**
	 * Render the CTA/Info Box widget.
	 *
	 * @param array<mixed> $settings Widget settings.
	 * @return void
	 * @since 1.6.0
	 */
	public static function render( $settings ): void {
		$title       = $settings['title'] ?? '';
		$description = $settings['description'] ?? '';
		$button_text = $settings['buttonText'] ?? '';
		$button_url  = $settings['buttonUrl'] ?? '';
		$image_url   = $settings['imageUrl'] ?? '';

		?>
		<div class="portal-widget-cta sd-flex-col">
			<?php if ( ! empty( $title ) ) { ?>
			<span class="portal-widget-section-title">
				<?php echo esc_html( $title ); ?>
			</span>
			<?php } ?>
			<div class="portal-widget-content sd-flex-col sd-gap-8">
				<?php
				$has_content = false;
				if ( ! empty( $image_url ) ) {
					$has_content = true;
					?>
					<div class="portal-widget-cta-image">
						<img src="<?php echo esc_url( $image_url ); ?>" alt="<?php echo esc_attr( ! empty( $title ) ? $title : __( 'Info', 'suredash' ) ); ?>" />
					</div>
					<?php
				}

				if ( ! empty( $description ) ) {
					$has_content = true;
					?>
					<div class="portal-widget-cta-description">
						<?php echo wp_kses_post( wpautop( $description ) ); ?>
					</div>
					<?php
				}

				if ( ! empty( $button_text ) && ! empty( $button_url ) ) {
					$has_content = true;
					?>
					<div class="portal-widget-cta-button sd-flex">
						<a
							href="<?php echo esc_url( $button_url ); ?>"
							class="portal-button button-primary sd-p-10 sd-text-center sd-flex sd-items-center sd-gap-4"
							<?php
							if ( strpos( $button_url, home_url() ) === false ) {
								echo 'target="_blank" rel="noopener noreferrer"'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
							}
							?>
						>
							<?php echo esc_html( $button_text ); ?>
						</a>
					</div>
					<?php
				}

				if ( ! $has_content ) {
					?>
					<div class="portal-widget-empty">
						<p><?php esc_html_e( 'No info available.', 'suredash' ); ?></p>
					</div>
					<?php
				}
				?>
			</div>
		</div>
		<?php
	}
}

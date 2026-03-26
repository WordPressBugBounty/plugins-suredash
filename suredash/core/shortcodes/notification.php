<?php
/**
 * Portals notification Shortcode Initialize.
 *
 * @package SureDash
 */

namespace SureDashboard\Core\Shortcodes;

use SureDashboard\Core\Notifier\Base as Notifier_Base;
use SureDashboard\Inc\Traits\Get_Instance;
use SureDashboard\Inc\Traits\Shortcode;
use SureDashboard\Inc\Utils\Helper;
use SureDashboard\Inc\Utils\Labels;

/**
 * Class Notification Shortcode.
 */
class Notification {
	use Shortcode;
	use Get_Instance;

	/**
	 * Register_shortcode_event.
	 *
	 * @return void
	 */
	public function register_shortcode_event(): void {
		$this->add_shortcode( 'notification' );
	}

	/**
	 * Display notification.
	 *
	 * @param array<mixed> $atts Array of attributes.
	 * @since 1.0.0
	 * @return string|false
	 */
	public function render_notification( $atts ) {
		$atts = apply_filters(
			'suredash_notification_attributes',
			shortcode_atts(
				[],
				$atts
			)
		);

		ob_start();
		$this->render_notification_markup( $atts );
		return ob_get_clean();
	}

	/**
	 * Shortcode callback.
	 *
	 * @param array<mixed> $atts Array of attributes.
	 * @return void
	 */
	public function render_notification_markup( $atts ): void {
		$icon_color_css = '';
		if ( ! empty( $atts['iconcolor'] ) ) {
			$icon_color_css = '--portal-notification-icon-color: ' . esc_attr( $atts['iconcolor'] ) . ';';
		}

		$highlighter = '<span class="notification-unread-count sd-absolute sd-flex sd-items-center sd-justify-center sd-font-12 sd-px-8 sd-max-h-20 sd-font-medium sd-bg-danger sd-color-white sd-min-w-20 sd-nowrap sd-radius-9999"></span>';
		?>
		<a href="<?php echo esc_url( home_url() ); ?>" class="portal-notification-trigger" title="<?php Labels::get_label( 'notifications', true ); ?>" style="<?php echo esc_attr( $icon_color_css ); ?>" aria-label="<?php echo esc_attr( Labels::get_label( 'notifications', true ) ); ?>">
			<?php Helper::get_library_icon( 'Bell', true, 'md' ); ?>
			<?php echo wp_kses_post( $highlighter ); ?>
		</a>
		<?php

		echo do_shortcode( $this->get_user_notification_drawer( $atts ) );
	}

	/**
	 * Get notification list markup.
	 *
	 * @param bool $close_button_needed Whether to show close button.
	 * @since 1.5.0
	 * @return void
	 */
	public function get_user_notification_list( $close_button_needed = true ): void {
		$html_markup = method_exists( Notifier_Base::get_instance(), 'get_notifications_markup' ) ? Notifier_Base::get_instance()->get_notifications_markup() : '';

		?>
			<div class="portal-notification-drawer-header sd-p-16 sd-flex sd-items-center sd-justify-between sd-font-18 sd-font-semibold sd-top-0">
				<h4 class="portal-notification-header-title sd-font-semibold sd-m-0"><?php Labels::get_label( 'notifications', true ); ?></h4>

				<?php if ( $close_button_needed ) { ?>
					<button class="portal-notification-drawer-close sd-flex sd-force-p-0 sd-pointer portal-button button-ghost" aria-label="<?php echo esc_attr( __( 'Close Notification', 'suredash' ) ); ?>"> <?php Helper::get_library_icon( 'X', true, 'md' ); ?> </button>
				<?php } ?>
			</div>

			<div class="portal-notification-drawer-content-type sd-flex sd-justify-between sd-px-16 sd-font-14">
				<div class="notification-title-wrap sd-relative sd-w-full sd-flex sd-justify-between sd-gap-12 sd-font-14 sd-font-medium sd-border-b">
					<span>
						<span
						class="notification-subtitle notification-all active sd-px-8 sd-py-4 sd-pointer sd-relative sd-transition sd-text-color"
						tabindex="0"
						role="button"
						aria-label="<?php echo esc_attr( __( 'All Notification', 'suredash' ) ); ?>"
						onclick="this.click()" onkeypress="if(event.key === 'Enter') { this.click(); }">
							<?php Labels::get_label( 'all_notifications', true ); ?>
						</span>
						<span
						class="notification-subtitle notification-unread sd-px-8 sd-py-4 sd-pointer sd-relative sd-transition"
						tabindex="0"
						role="button"
						aria-label="<?php echo esc_attr( __( 'Unread Notification', 'suredash' ) ); ?>"
						onclick="this.click()" onkeypress="if(event.key === 'Enter') { this.click(); }">
							<span class="notification-unread-text">
								<?php Labels::get_label( 'unread', true ); ?>
							</span>
							<span class="notification-unread-count sd-font-medium sd-font-12 sd-radius-9999 sd-px-6 sd-py-2 sd-ml-2 sd-bg-danger sd-color-light">
								<!-- this count will be updated via JS -->
							</span>
						</span>
					</span>
					<div class="notification-actions sd-flex sd-items-center sd-gap-8">
						<span
						class="notification-mark-all-read sd-flex sd-items-center sd-p-4 sd-gap-4 sd-font-semibold sd-cursor-pointer"
						tabindex="0"
						role="button"
						aria-label="<?php echo esc_attr( __( 'Mark all notifications as read', 'suredash' ) ); ?>"
						onkeypress="if(event.key === 'Enter') { this.click(); }">
							<span class="notification-mark-all-read-icon sd-flex sd-items-center sd-p-4 sd-gap-8 sd-font-semibold">
								<?php Helper::get_library_icon( 'CheckCheck', true, 'md' ); ?>
							</span>
							<span class="notification-mark-all-read-text">
								<?php echo esc_html__( 'Mark All as Read', 'suredash' ); ?>
							</span>
						</span>
						<a
						href="<?php echo esc_url( home_url( 'portal/user-profile/?tab=notifications' ) ); ?>"
						class="notification-settings sd-flex sd-items-center sd-p-4 sd-gap-8 sd-font-semibold sd-cursor-pointer sd-text-color sd-transition sd-hover-bg-subtle sd-rounded"
						title="<?php echo esc_attr( __( 'Notification Settings', 'suredash' ) ); ?>"
						aria-label="<?php echo esc_attr( __( 'Open notification settings in new tab', 'suredash' ) ); ?>">
							<?php Helper::get_library_icon( 'Settings', true, 'sm' ); ?>
						</a>
					</div>
				</div>
			</div>

			<div class="portal-notification-drawer-content sd-py-12 sd-px-8 sd-overflow-y-auto has-notifications">
				<?php
				if ( empty( $html_markup ) ) {
					?>
					<div class="no-notification sd-flex sd-flex-col sd-gap-8 sd-w-full sd-justify-center sd-items-center sd-p-20">
						<?php
						Helper::get_library_icon( 'Bell', true, 'md' );
						echo '<div class="no-notification-text" aria-label="' . esc_attr( Labels::get_label( 'no_notifications_title' ) ) . '">' . esc_html( Labels::get_label( 'no_notifications_title' ) ) . '</div>';
						Labels::get_label( 'no_notifications', true );
						?>
					</div>
					<?php
				} else {
					echo do_shortcode( $html_markup );
				}
				?>
			</div>
		<?php
	}

	/**
	 * Display notifications drawer.
	 *
	 * @param array<mixed> $atts Array of attributes.
	 * @since 1.0.0
	 * @return string
	 */
	public function get_user_notification_drawer( $atts ) {
		ob_start();

		$notification_block_css = '';

		// Check if any unit added to this offset otherwise add 'px' unit by default.
		$vertical_position_offset   = suredash_get_default_value_with_unit( $atts['drawerverpositionoffset'] );
		$horizontal_position_offset = suredash_get_default_value_with_unit( $atts['drawerhorpositionoffset'] );

		if ( isset( $atts['draweropenverposition'] ) ) {
			$notification_block_css .= $atts['draweropenverposition'] . ':' . $vertical_position_offset . ';';
		}
		if ( isset( $atts['draweropenhorposition'] ) ) {
			$notification_block_css .= $atts['draweropenhorposition'] . ':' . $horizontal_position_offset . ';';
		}

		?>
		<div class="portal-notification-drawer portal-content sd-bg-content sd-absolute sd-flex sd-flex-col sd-bg-content sd-radius-12 sd-shadow-lg sd-border sd-overflow-hidden sd-hidden sd-notification-list" style="<?php echo do_shortcode( $notification_block_css ); ?>">
			<?php $this->get_user_notification_list(); ?>
		</div>
		<?php

		return ob_get_clean(); // @phpstan-ignore-line
	}
}

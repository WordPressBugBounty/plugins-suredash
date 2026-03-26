<?php
/**
 * User Details Widget Renderer.
 *
 * @package SureDash
 * @since 1.6.0
 */

namespace SureDashboard\Core\Shortcodes\Widgets;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * User Details Widget Class.
 */
class User_Details {
	/**
	 * Render the User Details widget.
	 *
	 * @param array<mixed> $settings Widget settings.
	 * @return void
	 * @since 1.6.0
	 */
	public static function render( $settings ): void {
		$title    = $settings['title'] ?? '';
		$user_ids = $settings['userIds'] ?? [];

		// Ensure user_ids is an array.
		if ( ! is_array( $user_ids ) ) {
			$user_ids = empty( $user_ids ) ? [] : [ $user_ids ];
		}

		// Buffer the content to check if there are valid users.
		ob_start();
		$has_users = false;
		foreach ( $user_ids as $user_id ) {
			$user = get_userdata( $user_id );
			if ( ! $user ) {
				continue;
			}
			$has_users = true;
			$user_view = suredash_get_user_view_link( (int) $user->ID );
			?>
			<a href="<?php echo esc_url( $user_view ); ?>" class="portal-widget-user-item">
				<div class="portal-widget-user-avatar">
					<?php echo wp_kses_post( suredash_get_user_avatar( $user->ID, true, 32 ) ); ?>
				</div>
				<div class="portal-widget-user-info sd-flex sd-items-center sd-gap-12">
					<div class="portal-widget-user-name">
						<?php echo esc_html( $user->display_name ); ?>
					</div>
					<?php
					$user_roles_list = $user->roles;
					if ( ! empty( $user_roles_list ) ) {
						$role_name = ucfirst( str_replace( '_', ' ', $user_roles_list[0] ) );
						if ( $role_name === 'Administrator' ) {
							?>
						<div class="portal-widget-user-role">
							<?php echo '(' . esc_html( $role_name ) . ')'; ?>
						</div>
							<?php
						}
					}
					?>
				</div>
			</a>
			<?php
		}
		$users_content = ob_get_clean();

		?>
		<div class="portal-widget-users">
			<?php if ( ! empty( $title ) ) { ?>
			<span class="portal-widget-section-title">
				<?php echo esc_html( $title ); ?>
			</span>
			<?php } ?>

			<div class="portal-widget-users-list">
				<?php
				if ( $has_users ) {
					echo $users_content; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
				} else {
					?>
					<div class="portal-widget-empty">
						<p><?php esc_html_e( 'No profiles available.', 'suredash' ); ?></p>
					</div>
					<?php
				}
				?>
			</div>
		</div>
		<?php
	}
}

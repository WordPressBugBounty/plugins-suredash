<?php
/**
 * "Already signed in" screen — shown inside the Login / Register blocks when
 * the visitor is already authenticated and no explicit redirect is configured.
 *
 * Styles live in `assets/css/unminified/logged-in-screen.css` and are
 * conditionally enqueued by `Login::enqueue_logged_in_screen_style()`.
 *
 * Template variables (passed via $args by suredash_get_template_part()):
 * - string $user_name   Current user's display name.
 * - string $portal_url  Destination for the primary CTA.
 * - string $logout_url  Destination for the secondary logout link.
 *
 * @package SureDash
 * @since 1.8.1
 */

defined( 'ABSPATH' ) || exit;

/**
 * Current user's display name.
 *
 * @var string $user_name
 */
$user_name = $user_name ?? '';
/**
 * Destination for the primary CTA.
 *
 * @var string $portal_url
 */
$portal_url = $portal_url ?? '';
/**
 * Destination for the secondary logout link.
 *
 * @var string $logout_url
 */
$logout_url = $logout_url ?? '';

$portal_name_raw = \SureDashboard\Inc\Utils\Helper::get_option( 'portal_name' );
$portal_name     = is_string( $portal_name_raw ) && $portal_name_raw !== '' ? $portal_name_raw : get_bloginfo( 'name' );
?>
<div class="suredash-logged-in-screen">
	<div class="suredash-logged-in-screen__icon" aria-hidden="true">
		<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
			<polyline points="20 6 9 17 4 12"></polyline>
		</svg>
	</div>
	<h2 class="suredash-logged-in-screen__heading">
		<?php esc_html_e( "You're already signed in", 'suredash' ); ?>
	</h2>
	<p class="suredash-logged-in-screen__subtext">
		<?php
			printf(
				/* translators: 1: user display name, 2: portal name (e.g. site/community name) */
				esc_html__( 'Welcome back, %1$s. Head back to %2$s to pick up where you left off.', 'suredash' ),
				'<strong>' . esc_html( $user_name ) . '</strong>', // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
				'<strong>' . esc_html( $portal_name ) . '</strong>' // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			);
			?>
	</p>
	<a class="suredash-logged-in-screen__primary" href="<?php echo esc_url( $portal_url ); ?>">
		<?php
			printf(
				/* translators: %s: portal name (e.g. site/community name) */
				esc_html__( 'Go to %s', 'suredash' ),
				esc_html( $portal_name )
			);
			?>
	</a>
	<a class="suredash-logged-in-screen__secondary" href="<?php echo esc_url( $logout_url ); ?>">
		<?php esc_html_e( 'Not you? Sign out', 'suredash' ); ?>
	</a>
</div>

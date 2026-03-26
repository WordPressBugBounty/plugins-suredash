<?php
/**
 * Email functions.
 *
 * @package SureDash
 * @since 1.0.0
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use SureDashboard\Inc\Utils\Helper;

/**
 * Send email.
 *
 * @since 1.0.0
 *
 * @param string $to          Email address.
 * @param string $subject     Email subject.
 * @param string $message     Email message.
 *
 * @return bool               Email status.
 */
function suredash_send_email( $to, $subject, $message ) {
	// Get email settings.
	$from_name = Helper::get_option( 'portal_name' );
	$from_mail = Helper::get_option( 'email_from_mail_id' );

	$headers = [
		'Reply-To: ' . $from_name . ' <' . $from_mail . '>',
		'Content-Type: text/html; charset=UTF-8',
		'Content-Transfer-Encoding: 8bit',
		'From: ' . $from_name . ' <' . $from_mail . '>',
	];

	// Send email.
	return wp_mail( $to, $subject, $message, $headers );
}

/**
 * Get the forgot password mail template.
 *
 * @since 1.0.0
 *
 * @return string
 */
function suredash_forgot_password_mail_body() {
	ob_start();

	?>

	<p> <?php echo esc_html__( 'Someone has requested a password reset for the following account:', 'suredash' ); ?> </p>

	<p>
		<?php
			echo sprintf(
				// translators: %s: Username.
				'<strong>' . esc_html__( 'Username:', 'suredash' ) . '</strong> %s',
				'{{user_login}}'
			);
		?>
	</p>

	<p> <?php echo esc_html__( 'If this was a mistake, just ignore this email and nothing will happen.', 'suredash' ); ?> </p>

	<p> <?php echo esc_html__( 'To reset your password, visit the following URL:', 'suredash' ); ?> </p>

	<p> {{password_reset_url}} </p>

	<?php

	$message = ob_get_clean();

	return apply_filters( 'suredashboard_forgot_password_mail_message', $message );
}

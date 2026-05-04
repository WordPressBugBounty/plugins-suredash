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
 * Wraps the body in a full HTML document so clients that require DOCTYPE/<html>
 * render it correctly, and scopes wp_mail_from / wp_mail_from_name filters to
 * this call so PHPMailer handles RFC 2047 encoding of non-ASCII / special
 * characters in the From name (commas, accents, etc.).
 *
 * @since 1.0.0
 *
 * @param string $to          Email address.
 * @param string $subject     Email subject.
 * @param string $message     Email message (HTML fragment or full document).
 *
 * @return bool               Email status.
 */
function suredash_send_email( $to, $subject, $message ) {
	$message = suredash_wrap_email_html( (string) $message );

	$from_email_cb = static function () {
		$email = Helper::get_option( 'email_from_mail_id', get_option( 'admin_email' ) );
		return is_string( $email ) && $email !== '' ? $email : (string) get_option( 'admin_email' );
	};
	$from_name_cb  = static function () {
		$name = Helper::get_option( 'portal_name', get_option( 'blogname' ) );
		return is_string( $name ) && $name !== '' ? $name : (string) get_option( 'blogname' );
	};

	add_filter( 'wp_mail_from', $from_email_cb );
	add_filter( 'wp_mail_from_name', $from_name_cb );

	$sent = wp_mail( $to, $subject, $message, [ 'Content-Type: text/html; charset=UTF-8' ] );

	remove_filter( 'wp_mail_from', $from_email_cb );
	remove_filter( 'wp_mail_from_name', $from_name_cb );

	return $sent;
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

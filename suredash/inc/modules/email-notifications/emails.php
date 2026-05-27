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

/**
 * Build the admin email body for a new user-submitted community post.
 *
 * Returns an HTML fragment that `suredash_send_email()` will wrap with the
 * shared email document template (`email-templates/wrapper.php`).
 *
 * @since 1.9.0
 *
 * @param int $post_id The newly submitted post ID.
 *
 * @return string HTML fragment, or empty string if the post is missing.
 */
function suredash_new_post_admin_email_body( int $post_id ): string {
	$post = get_post( $post_id );
	if ( ! $post ) {
		return '';
	}

	$portal_name  = Helper::get_option( 'portal_name', get_bloginfo( 'name' ) );
	$author       = get_userdata( (int) $post->post_author );
	$author_name  = $author instanceof \WP_User ? $author->display_name : __( 'A community member', 'suredash' );
	$post_title   = get_the_title( $post );
	$post_link    = get_permalink( $post );
	$excerpt      = ! empty( $post->post_excerpt )
		? $post->post_excerpt
		: wp_trim_words( wp_strip_all_tags( $post->post_content ), 40 );
	$settings_url = admin_url( 'admin.php?page=portal&tab=settings&section=email' );

	ob_start();
	?>
	<p><?php echo esc_html__( 'Hi Admin,', 'suredash' ); ?></p>
	<p>
		<?php
		echo wp_kses_post(
			sprintf(
				/* translators: %1$s: author name, %2$s: portal name. */
				__( '%1$s just submitted a new community post on <strong>%2$s</strong>.', 'suredash' ),
				'<strong>' . esc_html( $author_name ) . '</strong>',
				esc_html( $portal_name )
			)
		);
		?>
	</p>
	<p><strong><?php echo esc_html( $post_title ); ?></strong></p>
	<?php if ( ! empty( $excerpt ) ) { ?>
		<p><?php echo esc_html( $excerpt ); ?></p>
	<?php } ?>
	<p>
		<a href="<?php echo esc_url( $post_link ); ?>" style="display:inline-block;padding:10px 16px;background-color:#2563eb;color:#ffffff;text-decoration:none;border-radius:6px;">
			<?php echo esc_html__( 'View the post', 'suredash' ); ?>
		</a>
	</p>
	<p style="margin-top:24px;padding-top:16px;border-top:1px solid #e5e7eb;color:#6b7280;font-size:12px;line-height:1.5;">
		<?php
		echo wp_kses(
			sprintf(
				/* translators: %s: URL to the email notification settings. */
				__( 'You are receiving this because you are an admin or portal manager. <a href="%s" style="color:#2563eb;text-decoration:underline;">Change recipients or disable these emails</a> from the SureDash email settings.', 'suredash' ),
				esc_url( $settings_url )
			),
			[
				'a' => [
					'href'  => true,
					'style' => true,
				],
			]
		);
		?>
	</p>
	<?php
	$message = (string) ob_get_clean();

	/**
	 * Filter the body HTML of the new-post admin notification email.
	 *
	 * @since 1.9.0
	 *
	 * @param string $message HTML body fragment.
	 * @param int    $post_id Post ID.
	 */
	return apply_filters( 'suredashboard_new_post_admin_email_message', $message, $post_id );
}

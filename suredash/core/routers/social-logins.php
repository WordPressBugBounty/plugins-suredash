<?php
/**
 * User Router Initialize.
 *
 * @package SureDash
 * @since 1.0.0
 */

namespace SureDashboard\Core\Routers;

use SureDashboard\Core\Blocks\Register;
use SureDashboard\Core\Blocks\SocialLogin;
use SureDashboard\Core\Notifier\Base as Notifier_Base;
use SureDashboard\Inc\Traits\Get_Instance;
use SureDashboard\Inc\Traits\Rest_Errors;
use SureDashboard\Inc\Utils\Helper;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Class User Router.
 *
 * @method static Social_Logins get_instance()
 */
class Social_Logins {
	use Get_Instance;
	use Rest_Errors;

	/**
	 * Hold Email Settings.
	 *
	 * @since 0.0.1
	 * @var array<mixed> $email_settings
	 */
	private $email_settings = [];

	/**
	 * Handler to login via block.
	 *
	 * @param \WP_REST_Request $request Request object.
	 *
	 * @since 0.0.1
	 * @return void
	 */
	public function block_login( $request ): void {
		$nonce = (string) $request->get_header( 'X-WP-Nonce' );
		if ( ! wp_verify_nonce( sanitize_text_field( $nonce ), 'wp_rest' ) ) {
			wp_send_json_error( [ 'message' => $this->get_rest_event_error( 'nonce' ) ] );
		}

		$captcha_type     = isset( $_POST['captchaType'] ) ? sanitize_text_field( wp_unslash( $_POST['captchaType'] ) ) : 'none';
		$recaptcha_status = ( isset( $_POST['recaptchaStatus'] ) ? filter_var( sanitize_text_field( wp_unslash( $_POST['recaptchaStatus'] ) ), FILTER_VALIDATE_BOOLEAN ) : false );

		if ( $captcha_type === 'recaptcha' && $recaptcha_status ) {

			$recaptcha_type   = ( isset( $_POST['reCaptchaType'] ) ? sanitize_text_field( wp_unslash( $_POST['reCaptchaType'] ) ) : 'v2' );
			$recaptcha_secret = '';

			if ( $recaptcha_type === 'v2' ) {
				$recaptcha_secret = Helper::get_option( 'recaptcha_secret_key_v2' );
			} else {
				$recaptcha_secret = Helper::get_option( 'recaptcha_secret_key_v3' );
			}

			if ( ! is_string( $recaptcha_secret ) ) {
				$recaptcha_secret = '';
			}

			$g_recaptcha_response = ( isset( $_POST['g-recaptcha-response'] ) ? sanitize_text_field( wp_unslash( $_POST['g-recaptcha-response'] ) ) : '' );
			$remote_addr          = filter_input( INPUT_SERVER, 'REMOTE_ADDR', FILTER_VALIDATE_IP );
			$remote_addr          = is_string( $remote_addr ) ? $remote_addr : '';
			$verify               = $this->verify_recaptcha( $g_recaptcha_response, $remote_addr, $recaptcha_secret );
			if ( $verify === false ) {
				wp_send_json_error( __( 'Captcha is not matching, please try again.', 'suredash' ) );
			}
		} elseif ( $captcha_type === 'turnstile' ) {

			$turnstile_secret = Helper::get_option( 'turnstile_secret_key', '' );
			if ( ! is_string( $turnstile_secret ) ) {
				$turnstile_secret = '';
			}
			$token       = isset( $_POST['cf-turnstile-response'] ) ? sanitize_text_field( wp_unslash( $_POST['cf-turnstile-response'] ) ) : '';
			$remote_addr = filter_input( INPUT_SERVER, 'REMOTE_ADDR', FILTER_VALIDATE_IP );
			$remote_addr = is_string( $remote_addr ) ? $remote_addr : '';
			$verify      = $this->verify_turnstile( $token, $remote_addr, $turnstile_secret );
			if ( $verify === false ) {
				wp_send_json_error( __( 'Captcha is not matching, please try again.', 'suredash' ) );
			}
		}

		$username   = ( isset( $_POST['username'] ) ? sanitize_text_field( wp_unslash( $_POST['username'] ) ) : '' );
		$password   = ( isset( $_POST['password'] ) ? wp_unslash( $_POST['password'] ) : '' ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Passwords should not be sanitized as it can strip valid characters. wp_signon handles validation.
		$rememberme = ( isset( $_POST['rememberme'] ) ? true : false );

		// Use original username for authentication (wp_signon handles validation).
		$user = wp_signon(
			[
				'user_login'    => $username,
				'user_password' => $password,
				'remember'      => $rememberme,
			],
			false
		);

		if ( is_wp_error( $user ) ) {

			// Customize error messages to remove WordPress default "Lost your password?" link.
			$error_code    = $user->get_error_code();
			$error_message = $user->get_error_message();

			if ( $error_code === 'incorrect_password' ) {
				// Custom error message for incorrect password without WordPress link.
				$forgot_password_url  = add_query_arg( 'action', 'lostpassword', suredash_get_login_page_url() );
				$forgot_password_link = '<a href="' . esc_url( $forgot_password_url ) . '">' . esc_html__( 'Lost your password?', 'suredash' ) . '</a>';
				$error_message        = sprintf(
					// translators: %1$s: Username, %2$s: Forgot password link.
					__( 'The password you entered for the username %1$s is incorrect. %2$s', 'suredash' ),
					esc_html( $username ),
					$forgot_password_link
				);
			}

			wp_send_json_error( $error_message );
		}

		wp_set_auth_cookie( $user->ID, $rememberme );  // Ensures there is seamless experience while navigating to WP Dashboard (without reauth=1).

		wp_send_json_success( esc_html__( 'You have successfully logged in. Redirecting...', 'suredash' ) );
	}

	/**
	 * Verify reCaptcha
	 *
	 * @param string $form_recaptcha_response reCaptcha token.
	 * @param string $server_remote_ip server IP.
	 * @param string $recaptcha_secret_key secret key.
	 * @return bool
	 * @since 0.0.1
	 */
	public function verify_recaptcha( $form_recaptcha_response, $server_remote_ip, $recaptcha_secret_key ) {
		$google_url      = 'https://www.google.com/recaptcha/api/siteverify';
		$google_response = add_query_arg(
			[
				'secret'   => $recaptcha_secret_key,
				'response' => $form_recaptcha_response,
				'remoteip' => $server_remote_ip,
			],
			$google_url
		);

		$google_response = wp_remote_get( $google_response );

		if ( is_wp_error( $google_response ) ) {
			return false;
		}

		$decode_google_response = json_decode( $google_response['body'] );
		return is_object( $decode_google_response ) && isset( $decode_google_response->success ) ? $decode_google_response->success : false;
	}

	/**
	 * Verify Cloudflare Turnstile token.
	 *
	 * @param string $form_token turnstile token.
	 * @param string $server_remote_ip server IP.
	 * @param string $secret_key secret key.
	 * @return bool
	 */
	public function verify_turnstile( $form_token, $server_remote_ip, $secret_key ) {
		$response = wp_remote_post(
			'https://challenges.cloudflare.com/turnstile/v0/siteverify',
			[
				'body' => [
					'secret'   => $secret_key,
					'response' => $form_token,
					'remoteip' => $server_remote_ip,
				],
			]
		);

		if ( is_wp_error( $response ) ) {
			return false;
		}

		$body = json_decode( wp_remote_retrieve_body( $response ), true );
		return is_array( $body ) && ! empty( $body['success'] );
	}

	/**
	 * Modify Email Template.
	 *
	 * @param array<mixed> $wp_new_user_notification_email email data.
	 * @param object       $user User object.
	 * @param string       $blogname website name.
	 * @return array<mixed>
	 * @since 0.0.1
	 * @phpcsSuppress SlevomatCodingStandard.Functions.UnusedParameter
	 */
	public function custom_wp_new_user_notification_email( $wp_new_user_notification_email, $user, $blogname ) {
		if ( is_array( $this->email_settings ) && ! empty( $this->email_settings ) ) {
			$wp_new_user_notification_email['subject'] = preg_replace_callback(
				'/\{{site_title}}/',
				static function() use ( $blogname ) {
					return $blogname;
				},
				$this->email_settings['subject']
			);

			$message = $this->email_settings['message'];

			$replacements = [
				'/\{{login_url}}/'       => esc_url( suredash_get_login_page_url() ),
				'/\[field=password\]/'   => $this->email_settings['user_pass'],
				'/\[field=username\]/'   => $this->email_settings['user_login'],
				'/\[field=email\]/'      => $this->email_settings['user_email'],
				'/\[field=first_name\]/' => $this->email_settings['first_name'],
				'/\[field=last_name\]/'  => $this->email_settings['last_name'],
				'/\{{site_title}}/'      => $blogname,
			];

			if ( isset( $this->email_settings['user_pass'] ) ) {
				foreach ( $replacements as $pattern => $replacement ) {
					$message = preg_replace_callback(
						$pattern,
						static function() use ( $replacement ) {
							return $replacement;
						},
						$message
					);
				}
			}

			$wp_new_user_notification_email['message'] = $message;

			$wp_new_user_notification_email['headers'] = $this->email_settings['headers'];
		}

		return $wp_new_user_notification_email;
	}

	/**
	 * Handler to forgot password via block.
	 *
	 * @param \WP_REST_Request $request Request object.
	 *
	 * @since 0.0.1
	 * @return void
	 */
	public function block_login_forgot_password( $request ): void {
		$nonce = (string) $request->get_header( 'X-WP-Nonce' );
		if ( empty( $nonce ) && isset( $_POST['security'] ) ) {
			$nonce = sanitize_text_field( wp_unslash( $_POST['security'] ) );
		}

		if ( ! wp_verify_nonce( $nonce, 'suredash_forgot_password' ) && ! wp_verify_nonce( $nonce, 'wp_rest' ) ) {
			wp_send_json_error( [ 'message' => $this->get_rest_event_error( 'nonce' ) ] );
		}

		if ( empty( $_POST['username'] ) ) {
			wp_send_json_error( esc_html__( 'The username/password field is empty. Please add a valid username/email to reset your password.', 'suredash' ) );
		}

		$user_login = sanitize_text_field( wp_unslash( $_POST['username'] ) );

		$user_data = get_user_by( 'login', $user_login );

		// If user data is not found by username, then find by email.
		if ( ! $user_data instanceof \WP_User ) {
			$user_data = get_user_by( 'email', $user_login );
		}

		// We need to check $user_data again since get_user_by() used above might return false value.
		if ( ! $user_data instanceof \WP_User ) {
			wp_send_json_error( esc_html__( 'No user found. Please add a registered username/email to reset your password, else create an account.', 'suredash' ) );
		}

		$user_login = $user_data->user_login;
		$user_email = $user_data->user_email;

		$key = get_password_reset_key( $user_data );

		if ( is_wp_error( $key ) ) {
			wp_send_json_error( $key );
		}

		$reset_url = suredash_get_login_page_url();
		$reset_url = add_query_arg(
			[
				'action' => 'resetpassword',
				'key'    => $key,
				'login'  => rawurlencode( $user_login ),
			],
			$reset_url
		);
		$key       = ! is_string( $key ) ? '' : $key;
		$message   = (string) Helper::get_option( 'forgot_password_mail_body' );
		$message   = str_replace( '{{user_login}}', esc_html( $user_login ), $message );
		$message   = str_replace( 'user_login', esc_html( $user_login ), $message );

		$message = str_replace( '{{password_reset_key}}', $key, $message );
		$message = str_replace( '{{password_reset_url}}', '<a href="' . esc_url( $reset_url ) . '">' . esc_html__( 'Reset your password here', 'suredash' ) . '</a>', $message );

		$message = str_replace( 'password_reset_key', $key, $message );
		$message = str_replace( 'password_reset_url', '<a href="' . esc_url( $reset_url ) . '">' . esc_html__( 'Reset your password here', 'suredash' ) . '</a>', $message );
		// Get site name and ensure it's a string.
		$blog_name = Helper::get_option( 'portal_name' );

		// Send email.
		$send_wp_mail = suredash_send_email(
			$user_email,
			sprintf(
				// translators: %s: Password reset.
				__( '[%s] Password Reset', 'suredash' ),
				wp_specialchars_decode( $blog_name )  // strval() - we use this function as wp_specialchars_decode() expects 'string' type parameter (and not 'mixed').
			),
			$message
		);

		// Check if email is sent and reply accordingly.
		if ( $send_wp_mail ) {
			wp_send_json_success( esc_html__( 'Please check your email for the password reset link.', 'suredash' ) );
		} else {
			wp_send_json_error( esc_html__( 'Email failed to send.', 'suredash' ) );
		}
	}

	/**
	 * Handler to forgot password via block.
	 *
	 * @param \WP_REST_Request $request Request object.
	 *
	 * @since 0.0.1
	 * @return void
	 */
	public function block_register( $request ): void {
		$nonce = (string) $request->get_header( 'X-WP-Nonce' );
		if ( ! wp_verify_nonce( sanitize_text_field( $nonce ), 'wp_rest' ) ) {
			wp_send_json_error( [ 'message' => $this->get_rest_event_error( 'nonce' ) ] );
		}

		$wp_registration_enabled = get_option( 'users_can_register' );

		if ( ! $wp_registration_enabled && ! Helper::get_option( 'override_wp_registration' ) ) {
			wp_send_json_error( esc_html__( 'Sorry, the site admin has disabled new user registration', 'suredash' ) );
		}

		$error      = [];
		$post_id    = ( isset( $_POST['post_id'] ) ? absint( $_POST['post_id'] ) : '' );
		$block_id   = ( isset( $_POST['block_id'] ) ? sanitize_text_field( wp_unslash( $_POST['block_id'] ) ) : '' );
		$first_name = isset( $_POST['first_name'] ) ? sanitize_text_field( wp_unslash( $_POST['first_name'] ) ) : '';
		$last_name  = isset( $_POST['last_name'] ) ? sanitize_text_field( wp_unslash( $_POST['last_name'] ) ) : '';
		$username   = isset( $_POST['username'] ) ? sanitize_user( wp_unslash( $_POST['username'] ), true ) : '';
		$email      = isset( $_POST['email'] ) ? sanitize_text_field( wp_unslash( $_POST['email'] ) ) : '';
		$password   = isset( $_POST['password'] ) ? wp_unslash( $_POST['password'] ) : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Passwords should not be sanitized as it can strip valid characters.

		$content_post = sd_get_post( absint( $post_id ) );
		if ( ! sd_post_exists( absint( $post_id ) ) ) {
			wp_send_json_error( __( 'Not a valid post.', 'suredash' ) );
			die; // @phpstan-ignore-line
		}

		$email_settings          = [];
		$register_block_instance = Register::get_instance();
		$block_name              = 'suredash/register';
		$saved_attributes        = is_object( $content_post ) && isset( $content_post->post_content ) && method_exists( $register_block_instance, 'get_block_attributes' ) ? $register_block_instance->get_block_attributes( $content_post->post_content, $block_name, $block_id ) : [];
		$default_attributes      = method_exists( $register_block_instance, 'get_default_attributes' ) ? $register_block_instance->get_default_attributes() : [];

		// verify captcha.
		$captcha_type     = $saved_attributes[ $block_name ]['spamProtection'] ?? $default_attributes['spamProtection']['default'];
		$recaptcha_enable = $saved_attributes[ $block_name ]['reCaptchaEnable'] ?? $default_attributes['reCaptchaEnable']['default'];

		if ( $captcha_type === 'recaptcha' && $recaptcha_enable ) {

			$recaptcha_type   = $saved_attributes[ $block_name ]['reCaptchaType'] ?? $default_attributes['reCaptchaType']['default'];
			$recaptcha_secret = '';

			if ( $recaptcha_type === 'v2' ) {
				$recaptcha_secret = Helper::get_option( 'recaptcha_secret_key_v2', '' );
			} else {
				$recaptcha_secret = Helper::get_option( 'recaptcha_secret_key_v3', '' );
			}

			if ( ! is_string( $recaptcha_secret ) ) {
				$recaptcha_secret = '';
			}

			$g_recaptcha_response = ( isset( $_POST['g-recaptcha-response'] ) ? sanitize_text_field( wp_unslash( $_POST['g-recaptcha-response'] ) ) : '' );
			$remote_addr          = filter_input( INPUT_SERVER, 'REMOTE_ADDR', FILTER_VALIDATE_IP );
			$remote_addr          = is_string( $remote_addr ) ? $remote_addr : '';
			$verify               = $this->verify_recaptcha( $g_recaptcha_response, $remote_addr, $recaptcha_secret );
			if ( $verify === false ) {
				wp_send_json_error( __( 'Captcha is not matching, please try again.', 'suredash' ) );
			}
		} elseif ( $captcha_type === 'turnstile' ) {

			$turnstile_secret = Helper::get_option( 'turnstile_secret_key', '' );
			if ( ! is_string( $turnstile_secret ) ) {
				$turnstile_secret = '';
			}
			$token       = isset( $_POST['cf-turnstile-response'] ) ? sanitize_text_field( wp_unslash( $_POST['cf-turnstile-response'] ) ) : '';
			$remote_addr = filter_input( INPUT_SERVER, 'REMOTE_ADDR', FILTER_VALIDATE_IP );
			$remote_addr = is_string( $remote_addr ) ? $remote_addr : '';
			$verify      = $this->verify_turnstile( $token, $remote_addr, $turnstile_secret );
			if ( $verify === false ) {
				wp_send_json_error( __( 'Captcha is not matching, please try again.', 'suredash' ) );
			}
		}

		// Password.
		if ( empty( $password ) ) {
			$password = wp_generate_password();
		} elseif ( isset( $_POST['reenter_password'] ) && $password !== $_POST['reenter_password'] ) {
			$error['password'] = $saved_attributes[ $block_name ]['messagePasswordConfirmError'] ?? $default_attributes['messagePasswordConfirmError']['default'];
		} elseif ( strpos( wp_unslash( $password ), '\\' ) !== false ) {
			$error['password'] = __( 'Password may not contain the character "\\"', 'suredash' );
		}

		// check required field.
		if ( empty( $first_name ) && isset( $saved_attributes['first_name']['required'] ) && $saved_attributes['first_name']['required'] ) {
			$error['first_name'] = esc_html__( 'This field is required.', 'suredash' );
		}
		if ( empty( $last_name ) && isset( $saved_attributes['last_name']['required'] ) && $saved_attributes['last_name']['required'] ) {
			$error['last_name'] = esc_html__( 'This field is required.', 'suredash' );
		}

		// User.
		if ( isset( $saved_attributes['username']['required'] ) && $saved_attributes['username']['required'] ) {
			if ( empty( $username ) ) {
				$error['username'] = $saved_attributes[ $block_name ]['messageInvalidUsernameError'] ?? $default_attributes['messageInvalidUsernameError']['default'];
			} elseif ( username_exists( $username ) ) {
				$error['username'] = $saved_attributes[ $block_name ]['messageUsernameAlreadyUsed'] ?? $default_attributes['messageUsernameAlreadyUsed']['default'];
			}
		}

		// Email.
		if ( empty( $email ) ) {
			$error['email'] = $saved_attributes[ $block_name ]['messageEmailMissingError'] ?? $default_attributes['messageEmailMissingError']['default'];
		} elseif ( ! is_email( $email ) ) {
			$error['email'] = $saved_attributes[ $block_name ]['messageInvalidEmailError'] ?? $default_attributes['messageInvalidEmailError']['default'];
		} elseif ( email_exists( $email ) ) {
			$error['email'] = $saved_attributes[ $block_name ]['messageEmailAlreadyUsedError'] ?? $default_attributes['messageEmailAlreadyUsedError']['default'];
		}

		// terms.
		if ( isset( $saved_attributes['terms']['required'] ) && $saved_attributes['terms']['required'] ) {
			$terms = (bool) isset( $_POST['terms'] ) ? sanitize_text_field( wp_unslash( $_POST['terms'] ) ) : false;
			if ( ! $terms ) {
				$error['terms'] = $saved_attributes[ $block_name ]['messageTermsError'] ?? $default_attributes['messageTermsError']['default'];
			}
		}

		// role.
		$role = Helper::get_option( 'default_role', 'suredash_user' );
		$role = apply_filters( 'suredashboard_social_registration_form_change_new_user_role', $role );

		// Email.
		if (
			isset( $saved_attributes[ $block_name ]['afterRegisterActions'] ) &&
			in_array( 'sendMail', $saved_attributes[ $block_name ]['afterRegisterActions'], true ) &&
			$saved_attributes[ $block_name ]['emailTemplateType'] === 'custom'
		) {
			// form data.
			$email_settings['user_login'] = $username;
			$email_settings['user_pass']  = $password;
			$email_settings['user_email'] = $email;
			$email_settings['first_name'] = $first_name;
			$email_settings['last_name']  = $last_name;

			// email.
			$email_settings['subject'] = $saved_attributes[ $block_name ]['emailTemplateSubject'] ?? $default_attributes['emailTemplateSubject']['default'];
			$email_settings['message'] = $saved_attributes[ $block_name ]['emailTemplateMessage'] ?? $default_attributes['emailTemplateMessage']['default'];
			$headers                   = $saved_attributes[ $block_name ]['emailTemplateMessageType'] ?? $default_attributes['emailTemplateMessageType']['default'];

			$email_settings['headers'] = 'Content-Type: text/' . ( $headers === 'plain' ? $headers : 'html; charset=UTF-8\r\n' );
		}

		// Create username from email.
		if ( empty( $username ) ) {
			$username = method_exists( $register_block_instance, 'create_username' ) ? $register_block_instance->create_username( $email, '' ) : '';
			$username = sanitize_user( $username );
		}

		// have error.
		if ( count( $error ) ) {
			wp_send_json_error( $error );
		}

		add_filter( 'wp_new_user_notification_email', [ $this, 'custom_wp_new_user_notification_email' ], 10, 3 );

		$user_args = apply_filters(
			'suredashboard_block_register_user_args',
			[
				'user_login'      => $username,
				'user_pass'       => $password,
				'user_email'      => $email,
				'first_name'      => $first_name,
				'last_name'       => $last_name,
				'user_registered' => gmdate( 'Y-m-d H:i:s' ),
				'role'            => $role,
			]
		);

		add_action( 'user_register', 'suredash_grant_capabilities_to_user', 10, 1 );

		$result = wp_insert_user( $user_args );

		if ( is_wp_error( $result ) ) {
			wp_send_json_error( esc_html__( 'Error in creating user.', 'suredash' ) );
		}

		wp_set_auth_cookie( $result );  // Ensures there is seamless experience while navigating to WP Dashboard (without reauth=1).

		remove_action( 'user_register', 'suredash_grant_capabilities_to_user', 10 );

		/**
		 * Fires after a new user has been created.
		 *
		 * @since 1.18.0
		 *
		 * @param int    $user_id ID of the newly created user.
		 * @param string $notify  Type of notification that should happen. See wp_send_new_user_notifications()
		 *                        for more information on possible values.
		 */
		do_action( 'edit_user_created_user', $result, 'both' );

		/**
		 * Fires after a new user registration via SureDash.
		 *
		 * @since 1.6.0
		 *
		 * @param int $user_id ID of the newly registered user.
		 */
		do_action( 'suredash_user_registered', $result );

		if ( is_wp_error( $result ) ) { // @phpstan-ignore-line
			wp_send_json_error( $result );
		}

		if ( method_exists( Notifier_Base::get_instance(), 'dispatch_admin_notification' ) ) {
			// Dispatch notification.
			Notifier_Base::get_instance()->dispatch_admin_notification( 'suredashboard_user_registered', [ 'caller' => $result ] );
		}

		$message      = $saved_attributes[ $block_name ]['messageSuccessRegistration'] ?? $default_attributes['messageSuccessRegistration']['default'];
		$redirect_url = isset( $saved_attributes[ $block_name ]['autoLoginRedirectURL']['url'] ) && $saved_attributes[ $block_name ]['autoLoginRedirectURL']['url'] ? esc_url( $saved_attributes[ $block_name ]['autoLoginRedirectURL']['url'] ) : esc_url( home_url( '/' . suredash_get_community_slug() . '/' ) );

		/* Login user after registration and redirect to home page if not currently logged in */
		$after_register_actions = $saved_attributes[ $block_name ]['afterRegisterActions'] ?? $default_attributes['afterRegisterActions']['default'];
		if ( in_array( 'autoLogin', $after_register_actions, true ) ) {
			$creds                  = [];
			$creds['user_login']    = $username;
			$creds['user_password'] = $password;
			$creds['remember']      = true;
			$login_user             = wp_signon( $creds, false );
			if ( ! is_wp_error( $login_user ) ) {
				wp_send_json_success(
					[
						'message'      => $message,
						'redirect_url' => $redirect_url,
					]
				);
			}

			$error['other'] = $saved_attributes[ $block_name ]['messageOtherError'] ?? $default_attributes['messageOtherError']['default'];
			wp_send_json_error( $error );
		}

		wp_send_json_success(
			[
				'message'      => $message,
				'redirect_url' => $redirect_url,
			]
		);
	}

	/**
	 * Handler to forgot password via block.
	 *
	 * @param \WP_REST_Request $request Request object.
	 *
	 * @since 0.0.1
	 * @return void
	 */
	public function register_unique_username_and_email( $request ): void {
		$nonce = (string) $request->get_header( 'X-WP-Nonce' );
		if ( ! wp_verify_nonce( sanitize_text_field( $nonce ), 'wp_rest' ) ) {
			wp_send_json_error( [ 'message' => $this->get_rest_event_error( 'nonce' ) ] );
		}

		$field_name  = ( isset( $_POST['field_name'] ) ? sanitize_key( $_POST['field_name'] ) : '' );
		$field_value = ( isset( $_POST['field_value'] ) ? sanitize_text_field( wp_unslash( $_POST['field_value'] ) ) : '' );

		if ( $field_name === 'username' ) {
			if ( username_exists( $field_value ) ) {
				wp_send_json_success(
					[
						'has_error' => true,
						'attribute' => 'messageUsernameAlreadyUsed',
					]
				);
			}
		} elseif ( $field_name === 'email' ) {
			if ( ! is_email( $field_value ) ) {
				wp_send_json_success(
					[
						'has_error' => true,
						'attribute' => 'messageInvalidEmailError',
					]
				);
			} elseif ( email_exists( $field_value ) ) {
				wp_send_json_success(
					[
						'has_error' => true,
						'attribute' => 'messageEmailAlreadyUsedError',
					]
				);
			}
		}

		wp_send_json_success(
			[
				'has_error' => false,
				'attribute' => '',
			]
		);
	}

	/**
	 * Get Facebook Form Data via AJAX call.
	 *
	 * @param \WP_REST_Request $request Request object.
	 *
	 * @return void
	 * @since 0.0.1
	 */
	public function login_form_facebook( $request ): void {
		$nonce = (string) $request->get_header( 'X-WP-Nonce' );
		if ( ! wp_verify_nonce( sanitize_text_field( $nonce ), 'wp_rest' ) ) {
			wp_send_json_error( [ 'message' => $this->get_rest_event_error( 'nonce' ) ] );
		}
		if ( isset( $_POST['data'] ) ) {
			$data = $_POST['data']; // phpcs:ignore -- Data is sanitized in the SocialLogin class.

			$login_register = SocialLogin::get_instance();

			$response = [
				'success' => false,
			];

			if ( method_exists( $login_register, 'facebook_login_register' ) ) {
				$response = $login_register->facebook_login_register( $data );
			}

			if ( $response['success'] ) {
				wp_send_json_success( $response['message'] );
			}

			wp_send_json_error( $response['message'] );
		}

		wp_send_json_error( __( 'You are not registered. Please register first then try to login.', 'suredash' ) );
	}

	/**
	 * Get Google Form Data via AJAX call.
	 *
	 * @param \WP_REST_Request $request Request object.
	 *
	 * @return void
	 * @since 0.0.1
	 */
	public function login_form_google( $request ): void {
		$nonce = (string) $request->get_header( 'X-WP-Nonce' );
		if ( ! wp_verify_nonce( sanitize_text_field( $nonce ), 'wp_rest' ) ) {
			wp_send_json_error( [ 'message' => $this->get_rest_event_error( 'nonce' ) ] );
		}

		$id_token = ! empty( $_POST['id_token'] ) ? sanitize_text_field( wp_unslash( $_POST['id_token'] ) ) : '';

		if ( empty( $id_token ) ) {
			wp_send_json_error( [ 'message' => __( 'Invalid credentials.', 'suredash' ) ] );
		}

		$login_register = SocialLogin::get_instance();

		$google_response = add_query_arg(
			[
				'client_id'     => Helper::get_option( 'google_token_id' ),
				'client_secret' => Helper::get_option( 'google_token_secret' ),
				'grant_type'    => 'authorization_code',
			],
			'https://oauth2.googleapis.com/tokeninfo?access_token=' . $id_token
		);

		$request = wp_remote_get( $google_response );

		if ( is_wp_error( $request ) || wp_remote_retrieve_response_code( $request ) !== 200 ) {
			wp_send_json_error( [ 'message' => __( 'Token verification failed.', 'suredash' ) ] );
		}

		$payload = wp_remote_retrieve_body( $request );

		if ( ! $payload ) {
			wp_send_json_error( [ 'message' => __( 'Token verification failed.', 'suredash' ) ] );
		}

		$payload = json_decode( $payload, true );

		if ( is_array( $payload ) && ! empty( $payload['aud'] ) ) {
			$parent_client_id = Helper::get_option( 'google_token_id', '' );

			/**
			 * Once you get these claims, you still need to check that the aud claim contains one of your app's client IDs.
			 * If it does, then the token is both valid and intended for your client,
			 * and you can safely retrieve and use the user's unique Google ID from the sub claim.
			 */
			if ( $payload['aud'] !== $parent_client_id ) {
				wp_send_json_error( [ 'message' => __( 'Invalid credentials.', 'suredash' ) ] );
			}

			// Check the token's expiration.
			if ( $payload['exp'] < time() ) {
				wp_send_json_error( [ 'message' => __( 'Invalid credentials.', 'suredash' ) ] );
			}

			// Fetch user info from Google to get name fields.
			if ( method_exists( $login_register, 'get_google_user_info' ) ) {
				$user_info = $login_register->get_google_user_info( $id_token );
				if ( is_array( $user_info ) ) {
					// Merge user info with payload to include name fields.
					$payload = array_merge( $payload, $user_info );
				}
			}

			$wp_user = get_user_by( 'email', sanitize_email( $payload['email'] ) );

			if ( $wp_user && method_exists( $login_register, 'login_user' ) ) {
				$login_register->login_user( $wp_user->ID, $payload );
				wp_send_json_success( [ 'message' => __( 'Login Successful', 'suredash' ) ] );
			}

			if ( method_exists( $login_register, 'register_user' ) ) {
				$login_register->register_user( $payload );
			}
			wp_send_json_success( [ 'message' => __( 'Registered & Login Successful', 'suredash' ) ] );
		}

		wp_send_json_error( [ 'message' => __( 'Google API Error.', 'suredash' ) ] );
	}

	/**
	 * Updates the read status of a user notification.
	 *
	 * This method handles an AJAX request to mark a notification as read for the current user.
	 * It stores the notification timestamp in the user's meta data to track which notifications
	 * have been read.
	 *
	 * @param \WP_REST_Request $request Request object.
	 *
	 * @since 0.0.1
	 * @return void
	 */
	public function user_notification_status( $request ): void {
		$nonce = (string) $request->get_header( 'X-WP-Nonce' );
		if ( ! wp_verify_nonce( sanitize_text_field( $nonce ), 'wp_rest' ) ) {
			wp_send_json_error( [ 'message' => $this->get_rest_event_error( 'nonce' ) ] );
		}

		$decoded_timestamps = ! empty( $_POST['notification_timestamps'] ) ? json_decode( wp_unslash( $_POST['notification_timestamps'] ), true ) : []; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Data is sanitized further down the line with array_map.

		if ( empty( $decoded_timestamps ) || $decoded_timestamps === 'undefined' ) {
			wp_send_json_error( [ 'message' => $this->get_rest_event_error( 'default' ) ] );
		}
		$user_id = get_current_user_id();

		// Get notification status and ensure proper structure.
		$notification_status = sd_get_user_meta( $user_id, 'portal_user_notification_status', true );
		if ( ! is_array( $notification_status ) ) {
			$notification_status = [
				'read' => [],
			];
		}

		// Ensure 'read' key exists.
		if ( ! isset( $notification_status['read'] ) ) {
			$notification_status['read'] = [];
		}

		if ( is_array( $decoded_timestamps ) ) {
			$notification_timestamps = array_map(
				static function( $timestamp ) {
					return intval( $timestamp );
				},
				$decoded_timestamps
			);

			// Add timestamp if not already present.
			foreach ( $notification_timestamps as $timestamp ) {
				if ( ! in_array( $timestamp, $notification_status['read'], true ) ) {
					$notification_status['read'][] = $timestamp;
				}
			}
			sd_update_user_meta( $user_id, 'portal_user_notification_status', $notification_status );
			wp_send_json_success( [ 'status' => 'success' ] );
		}

		wp_send_json_error( [ 'message' => $this->get_rest_event_error( 'default' ) ] );
	}

	/**
	 * Get Google Form Data via AJAX call.
	 *
	 * @param \WP_REST_Request $request Request object.
	 *
	 * @return void
	 * @since 1.2.0
	 */
	public function register_form_google( $request ): void {
		$nonce = (string) $request->get_header( 'X-WP-Nonce' );
		if ( ! wp_verify_nonce( sanitize_text_field( $nonce ), 'wp_rest' ) ) {
			wp_send_json_error( [ 'message' => $this->get_rest_event_error( 'nonce' ) ] );
		}

		$post_id  = ( isset( $_POST['post_id'] ) ? sanitize_text_field( wp_unslash( $_POST['post_id'] ) ) : '' );
		$block_id = ( isset( $_POST['block_id'] ) ? sanitize_text_field( wp_unslash( $_POST['block_id'] ) ) : '' );
		$id_token = ( isset( $_POST['id_token'] ) ? sanitize_text_field( wp_unslash( $_POST['id_token'] ) ) : '' );

		if ( empty( $id_token ) ) {
			wp_send_json_error( [ 'message' => __( 'Invalid credentials.', 'suredash' ) ] );
		}

		$login_register = SocialLogin::get_instance();

		$request = wp_remote_get( 'https://oauth2.googleapis.com/tokeninfo?access_token=' . $id_token );

		if ( is_wp_error( $request ) || wp_remote_retrieve_response_code( $request ) !== 200 ) {
			$error_message = is_wp_error( $request ) ? $request->get_error_message() : __( 'Token verification failed.', 'suredash' );
			wp_send_json_error( [ 'message' => $error_message ] );
		}

		$payload = wp_remote_retrieve_body( $request );

		if ( ! $payload ) {
			wp_send_json_error( [ 'message' => __( 'Token verification failed.', 'suredash' ) ] );
		}

		$payload = json_decode( $payload, true );

		if ( ! empty( $payload['aud'] ) ) {

			$parent_client_id = Helper::get_option( 'google_token_id', '' );

			/**
			 * Once you get these claims, you still need to check that the aud claim contains one of your app's client IDs.
			 * If it does, then the token is both valid and intended for your client,
			 * and you can safely retrieve and use the user's unique Google ID from the sub claim.
			 */
			if ( $payload['aud'] !== $parent_client_id ) {
				wp_send_json_error( [ 'message' => __( 'Invalid credentials.', 'suredash' ) ] );
			}

			// Check the token's expiration.
			if ( $payload['exp'] < time() ) {
				wp_send_json_error( [ 'message' => __( 'Invalid credentials.', 'suredash' ) ] );
			}

			// Fetch user info from Google to get name fields.
			if ( is_callable( [ $login_register, 'get_google_user_info' ] ) ) {
				$user_info = $login_register->get_google_user_info( $id_token );
				if ( is_array( $user_info ) ) {
					// Merge user info with payload to include name fields.
					$payload = array_merge( $payload, $user_info );
				}
			}

			$email   = sanitize_email( $payload['email'] );
			$wp_user = get_user_by( 'email', $email );

			if ( $wp_user && is_callable( [ $login_register, 'login_user' ] ) ) {
				$login_register->login_user( $wp_user->ID, $payload );
				wp_send_json_success( [ 'message' => __( 'Login Successful', 'suredash' ) ] );
			}

			if ( is_callable( [ $login_register, 'register_user' ] ) ) {
				$login_register->register_user( $payload );
				wp_send_json_success( [ 'message' => __( 'Registered & Login Successful', 'suredash' ) ] );
			}

			// Email.
			$content_post            = get_post( (int) $post_id );
			$register_block_instance = Register::get_instance();
			$block_name              = 'suredash/register';

			$saved_attributes   = [];
			$default_attributes = [];

			if ( is_callable( [ $register_block_instance, 'get_block_attributes' ] ) && ! empty( $content_post ) ) {
				$saved_attributes = $register_block_instance->get_block_attributes( $content_post->post_content, $block_name, $block_id );
			}
			if ( is_callable( [ $register_block_instance, 'get_default_attributes' ] ) ) {
				$default_attributes = $register_block_instance->get_default_attributes();
			}

			if (
				isset( $saved_attributes[ $block_name ]['afterRegisterActions'] ) &&
				in_array( 'sendMail', $saved_attributes[ $block_name ]['afterRegisterActions'], true ) &&
				$saved_attributes[ $block_name ]['emailTemplateType'] === 'custom'
			) {
				$this->email_settings['subject'] = $saved_attributes[ $block_name ]['emailTemplateSubject'] ?? $default_attributes['emailTemplateSubject']['default'];
				$this->email_settings['message'] = $saved_attributes[ $block_name ]['emailTemplateMessage'] ?? $default_attributes['emailTemplateMessage']['default'];
				$headers                         = $saved_attributes[ $block_name ]['emailTemplateMessageType'] ?? $default_attributes['emailTemplateMessageType']['default'];
				$this->email_settings['headers'] = 'Content-Type: text/' . ( $headers ? $headers : 'html' ) . '; charset=UTF-8' . "\r\n";
			}

			$redirect_url = isset( $saved_attributes[ $block_name ]['autoLoginRedirectURL']['url'] ) && $saved_attributes[ $block_name ]['autoLoginRedirectURL']['url'] ? esc_url( $saved_attributes[ $block_name ]['autoLoginRedirectURL']['url'] ) : esc_url( home_url( '/' . suredash_get_community_slug() . '/' ) );

			wp_send_json_success(
				[
					'message'      => __( 'Registered & Login Successful', 'suredash' ),
					'redirect_url' => $redirect_url,
				]
			);
		}

		wp_send_json_error();
	}
}

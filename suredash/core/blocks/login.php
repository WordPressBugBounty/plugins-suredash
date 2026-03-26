<?php
/**
 * Login Block.
 *
 * @package SureDash
 */

namespace SureDashboard\Core\Blocks;

use SureDashboard\Core\Routers\Social_Logins;
use SureDashboard\Inc\Traits\Get_Instance;
use SureDashboard\Inc\Utils\Helper;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Class Login.
 */
class Login {
	use Get_Instance;

	/**
	 * Initialize AJAX handlers for login-related actions.
	 *
	 * Registers both authenticated (`wp_ajax_`) and unauthenticated (`wp_ajax_nopriv_`)
	 * WordPress AJAX hooks for:
	 * - Lost password processing (`suredash_lost_password`)
	 * - Password reset handling (`suredash_reset_password`)
	 *
	 * This method should be called once during plugin initialization to ensure
	 * that AJAX endpoints are correctly mapped to their respective handler methods.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public static function init(): void {
		add_action( 'wp_ajax_suredash_lost_password', [ self::class, 'process_lost_password' ] );
		add_action( 'wp_ajax_nopriv_suredash_lost_password', [ self::class, 'process_lost_password' ] );

		add_action( 'wp_ajax_suredash_reset_password', [ self::class, 'process_reset_password' ] );
		add_action( 'wp_ajax_nopriv_suredash_reset_password', [ self::class, 'process_reset_password' ] );
	}

	/**
	 * Process the lost password request via AJAX.
	 *
	 * Validates the nonce and email, and delegates to the Social_Logins handler
	 * to send the password reset email using the provided email address.
	 *
	 * Terminates with a JSON response using wp_send_json_success() or wp_send_json_error().
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public static function process_lost_password(): void {
		check_ajax_referer( 'suredash_forgot_password', 'security' );

		$email = isset( $_POST['email'] ) ? sanitize_email( $_POST['email'] ) : '';

		if ( empty( $email ) || ! is_email( $email ) ) {
			wp_send_json_error( [ 'message' => 'Please enter a valid email address.' ] );
		}

		// Make sure the username field is present for block_login_forgot_password.
		$_POST['username'] = $email;

		// Create a dummy request object (not used in current handler but required by signature).
		$request = new \WP_REST_Request();

		// Call the actual email sending logic.
		Social_Logins::get_instance()->block_login_forgot_password( $request );
	}

	/**
	 *  Process reset password.
	 *
	 *  @since 1.0.0
	 */
	public static function process_reset_password(): void {
		check_ajax_referer( 'suredash_forgot_password', 'security' );

		if ( ! isset( $_POST['data'] ) ) {
			wp_die( esc_html( __( 'Invalid request.', 'suredash' ) ), 400 );
		}

		$data = array_map( 'sanitize_text_field', $_POST['data'] );
		if ( ! is_array( $data ) || empty( $data ) ) {
			wp_send_json_error( [ 'message' => __( 'Invalid data.', 'suredash' ) ] );
		}
		$required_fields = [ 'sd_reset_pw', 'sd_confirm_pass', 'reset_key', 'reset_login' ];
		$values          = [];

		foreach ( $required_fields as $field ) {
			if ( ! isset( $data[ $field ] ) ) {
				wp_send_json_error( [ 'message' => __( 'Invalid data.', 'suredash' ) ] );
			}
			$values[ $field ] = in_array( $field, [ 'sd_reset_pw', 'sd_confirm_pass' ], true )
				? $data[ $field ] // keep raw.
				: wp_unslash( $data[ $field ] );
		}

		$form_error = '';

		if ( empty( $values['sd_reset_pw'] ) ) {
			$form_error = __( 'Please enter your password.', 'suredash' );
		} elseif ( $values['sd_reset_pw'] !== $values['sd_confirm_pass'] ) {
			$form_error = __( 'Passwords do not match.', 'suredash' );
		}

		$user = check_password_reset_key( $values['reset_key'], $values['reset_login'] );

		if ( is_wp_error( $user ) ) {
			wp_send_json_error( [ 'message' => $user->get_error_message() ] );
		}

		if ( is_object( $user ) && empty( $form_error ) ) {
			$errors = new \WP_Error();
			do_action( 'validate_password_reset', $errors, $user );

			if ( $errors->has_errors() ) {
				foreach ( $errors->get_error_messages() as $error ) {
					$form_error .= $error . "\n";
				}
			} else {
				reset_password( $user, $values['sd_reset_pw'] );
				do_action( 'suredash_login_form_user_reset_password', $user );
				wp_send_json_success( [ 'message' => __( 'Your password has been reset successfully. Redirecting to login...', 'suredash' ) ] );
			}
		}

		wp_send_json_error( [ 'message' => ! empty( $form_error ) ? $form_error : __( 'Unknown error', 'suredash' ) ] );
	}

	/**
	 * Registers the `core/login` block on server.
	 *
	 * @since 1.0.0
	 */
	public function register_blocks(): void {

		// Check if the register function exists.
		if ( ! function_exists( 'register_block_type' ) ) {
			return;
		}

		self::init();
		add_action( 'suredash_enqueue_login_block_scripts', [ $this, 'enqueue_front_assets' ], 10, 2 );

		register_block_type(
			'suredash/login',
			[
				'attributes'      => $this->get_default_attributes(),
				'render_callback' => [ $this, 'render_html' ],
				'style'           => 'portal-login-block',
			]
		);
	}

	/**
	 * Get login block attributes.
	 *
	 * @since 1.0.0
	 *
	 * @return array<string, array<string, mixed>>
	 */
	public function get_default_attributes() {
		return apply_filters(
			'suredash_login_block_attributes',
			[
				'block_id'                => [
					'type'    => 'string',
					'default' => '',
				],
				'usernameLabel'           => [
					'type'    => 'string',
					'default' => 'Username or Email Address',
				],
				'usernamePlaceholder'     => [
					'type'    => 'string',
					'default' => 'Username',
				],
				'passwordLabel'           => [
					'type'    => 'string',
					'default' => 'Password',
				],
				'passwordPlaceholder'     => [
					'type'    => 'string',
					'default' => 'Password',
				],
				'rememberMeLabel'         => [
					'type'    => 'string',
					'default' => 'Remember Me',
				],
				'forgotPasswordLabel'     => [
					'type'    => 'string',
					'default' => 'Forgot Password',
				],
				'loginButtonLabel'        => [
					'type'    => 'string',
					'default' => 'Login',
				],
				'showRegisterInfo'        => [
					'type'    => 'boolean',
					'default' => true,
				],
				'registerInfo'            => [
					'type'    => 'string',
					'default' => 'Don\'t have an account?',
				],
				'registerButtonLabel'     => [
					'type'    => 'string',
					'default' => 'Register',
				],
				'registerButtonLink'      => [
					'type'    => 'object',
					'default' => '',
				],

				// social login.
				'enableGoogleLogin'       => [
					'type'    => 'boolean',
					'default' => false,
				],
				'googleLoginButtonText'   => [
					'type'    => 'string',
					'default' => 'Google',
				],
				'enableFacebookLogin'     => [
					'type'    => 'boolean',
					'default' => false,
				],
				'facebookLoginButtonText' => [
					'type'    => 'string',
					'default' => 'Facebook',
				],

				// settings.
				'disableFormFields'       => [
					'type'    => 'boolean',
					'default' => false,
				],
				'enableLoggedInMessage'   => [
					'type'    => 'boolean',
					'default' => true,
				],
				'redirectAfterLoginURL'   => [
					'type'    => 'object',
					'default' => '',
				],
				'redirectAfterLogoutURL'  => [
					'type'    => 'object',
					'default' => '',
				],
				// recaptcha.
				'spamProtection'          => [
					'type'    => 'string',
					'default' => 'none',
				],
				'reCaptchaEnable'         => [
					'type'    => 'boolean',
					'default' => false,
				],
				'hidereCaptchaBatch'      => [
					'type'    => 'boolean',
					'default' => false,
				],
				'reCaptchaType'           => [
					'type'    => 'string',
					'default' => 'v2',
				],
				'turnstileAppearance'     => [
					'type'    => 'string',
					'default' => 'auto',
				],
				// icon.
				'isHideIcon'              => [
					'type'    => 'boolean',
					'default' => true,
				],
				// Form Heading.
				'showFormHeading'         => [
					'type'    => 'boolean',
					'default' => true,
				],
				'formHeadingText'         => [
					'type'    => 'string',
					'default' => '',
				],
				'formHeadingTag'          => [
					'type'    => 'string',
					'default' => 'h3',
				],

				// reverse control.
				'socialReverseToogle'     => [
					'type'    => 'boolean',
					'default' => false,
				],

				// button size.
				'loginSize'               => [
					'type'    => 'string',
					'default' => 'full',
				],
				'googleSize'              => [
					'type'    => 'string',
					'default' => 'full',
				],
				'facebookSize'            => [
					'type'    => 'string',
					'default' => 'full',
				],
				'socialLoginPosition'     => [
					'type'    => 'string',
					'default' => 'below-form',
				],
				'formWidth'               => [
					'type'    => 'number',
					'default' => 100,
				],
				'formWidthTablet'         => [
					'type'    => 'number',
					'default' => 100,
				],
				'formWidthMobile'         => [
					'type'    => 'number',
					'default' => 100,
				],
				'formWidthType'           => [
					'type'    => 'string',
					'default' => '%',
				],
				'formWidthTypeTablet'     => [
					'type'    => 'string',
					'default' => '%',
				],
				'formWidthTypeMobile'     => [
					'type'    => 'string',
					'default' => '%',
				],
				'ctaIcon'                 => [
					'type'    => 'string',
					'default' => '',
				],
				'ctaIconPosition'         => [
					'type'    => 'string',
					'default' => 'after',
				],
				'ctaIconSpace'            => [
					'type'    => 'number',
					'default' => 5,
				],
				'ctaIconSpaceTablet'      => [
					'type'    => 'number',
					'default' => '',
				],
				'ctaIconSpaceMobile'      => [
					'type'    => 'number',
					'default' => '',
				],
				'ctaIconSpaceType'        => [
					'type'    => 'string',
					'default' => 'px',
				],

				'formBorderStyle'         => [
					'type'    => 'string',
					'default' => 'default',
				],
				'fieldsBorderStyle'       => [
					'type'    => 'string',
					'default' => 'default',
				],
				'loginBorderStyle'        => [
					'type'    => 'string',
					'default' => 'default',
				],
				// reset password.
				'enterYourEmailAddress'   => [
					'type'    => 'string',
					'default' => 'Enter your Email Address',
				],
				'backToLogin'             => [
					'type'    => 'string',
					'default' => 'Back to Login',
				],
				'getNewPassword'          => [
					'type'    => 'string',
					'default' => 'Get New Password',
				],
				'newPassword'             => [
					'type'    => 'string',
					'default' => 'New Password',
				],
				'confirmNewPassword'      => [
					'type'    => 'string',
					'default' => 'Confirm New Password',
				],
				'hint'                    => [
					'type'    => 'string',
					'default' => 'Hint: The password should be at least twelve characters long. To make it stronger, use upper and lower case letters, numbers, and symbols like ! " ? $ % ^ & ).',
				],
				'restPassword'            => [
					'type'    => 'string',
					'default' => 'Reset Password',
				],
				'lostPasswordHeading'     => [
					'type'    => 'string',
					'default' => 'Lost Password',
				],
				'resetPasswordHeading'    => [
					'type'    => 'string',
					'default' => 'Reset Password',
				],
				'emailPlaceholder'        => [
					'type'    => 'string',
					'default' => 'Email',
				],
			]
		);
	}

	/**
	 * Get login block style attributes.
	 *
	 * @since 1.0.0
	 * @return array<mixed>
	 */
	public function get_style_block_attributes() {
		$form_border_attribute     = Helper::generate_border_attribute( 'form' );
		$fields_border_attribute   = Helper::generate_border_attribute( 'fields' );
		$login_border_attribute    = Helper::generate_border_attribute( 'login' );
		$facebook_border_attribute = Helper::generate_border_attribute( 'facebook' );
		$google_border_attribute   = Helper::generate_border_attribute( 'google' );

		return array_merge(
			[
				// form.
				'formWidth'                       => '100',
				'formWidthType'                   => '%',
				'formWidthTablet'                 => '100',
				'formWidthTypeTablet'             => '%',
				'formWidthMobile'                 => '100',
				'formWidthTypeMobile'             => '%',
				'formTopPadding'                  => '',
				'formRightPadding'                => '',
				'formLeftPadding'                 => '',
				'formBottomPadding'               => '',
				'formTopPaddingTablet'            => '',
				'formRightPaddingTablet'          => '',
				'formLeftPaddingTablet'           => '',
				'formBottomPaddingTablet'         => '',
				'formTopPaddingMobile'            => 20,
				'formRightPaddingMobile'          => 30,
				'formLeftPaddingMobile'           => 30,
				'formBottomPaddingMobile'         => 20,
				'formPaddingUnit'                 => 'px',
				'formPaddingUnitTablet'           => 'px',
				'formPaddingUnitMobile'           => 'px',
				'formPaddingLink'                 => '',
				'formRowsGapSpace'                => 20,
				'formRowsGapSpaceTablet'          => '',
				'formRowsGapSpaceMobile'          => '',
				'formRowsGapSpaceUnit'            => 'px',
				'eyeIconColor'                    => '',
				// checkbox.
				'checkboxSize'                    => 20,
				'checkboxBackgroundColor'         => '',
				'checkboxColor'                   => '',
				'checkboxBorderWidth'             => 1,
				'checkboxBorderRadius'            => 2,
				'checkboxBorderColor'             => '',
				'checkboxGlowEnable'              => true,
				'checkboxGlowColor'               => '#2271b1',
				// reCaptcha.
				'spamProtection'                  => 'none',
				'reCaptchaEnable'                 => false,
				'reCaptchaType'                   => 'v2',
				'turnstileAppearance'             => 'auto',

				// social.
				'enableFacebookLogin'             => false,
				'enableGoogleLogin'               => false,

				// alignment.
				// google and facebook alignment.
				'alignGooleFacebookBtn'           => '',
				'alignGooleFacebookBtnTablet'     => '',
				'alignGooleFacebookBtnMobile'     => '',
				// stack options.
				'stackGoogleFacebookButton'       => 'off',
				'stackGoogleFacebookButtonTablet' => 'on',
				'stackGoogleFacebookButtonMobile' => 'on',
				// gap between social logins.
				'gapSocialLogin'                  => '15',
				'gapSocialLoginTablet'            => '15',
				'gapSocialLoginMobile'            => '15',

				// label.
				'labelColor'                      => '',
				'labelHoverColor'                 => '',
				'labelFontFamily'                 => 'Default',
				'labelFontWeight'                 => '',
				'labelFontStyle'                  => 'normal',
				'labelTransform'                  => '',
				'labelDecoration'                 => '',
				'labelLetterSpacing'              => '',
				'labelLetterSpacingTablet'        => '',
				'labelLetterSpacingMobile'        => '',
				'labelLetterSpacingType'          => 'px',
				'labelFontSizeType'               => 'px',
				'labelLineHeightType'             => 'em',
				'labelFontSize'                   => '',
				'labelFontSizeTablet'             => '',
				'labelFontSizeMobile'             => '',
				'labelLineHeight'                 => '',
				'labelLineHeightTablet'           => '',
				'labelLineHeightMobile'           => '',
				'labelTopMargin'                  => '',
				'labelRightMargin'                => '',
				'labelLeftMargin'                 => '',
				'labelBottomMargin'               => '',
				'labelTopMarginTablet'            => '',
				'labelRightMarginTablet'          => '',
				'labelLeftMarginTablet'           => '',
				'labelBottomMarginTablet'         => '',
				'labelTopMarginMobile'            => '',
				'labelRightMarginMobile'          => '',
				'labelLeftMarginMobile'           => '',
				'labelBottomMarginMobile'         => '',
				'labelMarginUnit'                 => 'px',
				'labelMarginUnitTablet'           => 'px',
				'labelMarginUnitMobile'           => 'px',
				// heading.
				'headingColor'                    => '',
				'headingHoverColor'               => '',
				'headingFontFamily'               => 'Default',
				'headingFontWeight'               => '',
				'headingFontStyle'                => 'normal',
				'headingTransform'                => '',
				'headingDecoration'               => '',
				'headingLetterSpacing'            => '',
				'headingLetterSpacingTablet'      => '',
				'headingLetterSpacingMobile'      => '',
				'headingLetterSpacingType'        => 'px',
				'headingFontSizeType'             => 'px',
				'headingLineHeightType'           => 'em',
				'headingFontSize'                 => '',
				'headingFontSizeTablet'           => '',
				'headingFontSizeMobile'           => '',
				'headingLineHeight'               => '',
				'headingLineHeightTablet'         => '',
				'headingLineHeightMobile'         => '',
				'headingTopMargin'                => '',
				'headingRightMargin'              => '',
				'headingLeftMargin'               => '',
				'headingBottomMargin'             => '',
				'headingTopMarginTablet'          => '',
				'headingRightMarginTablet'        => '',
				'headingLeftMarginTablet'         => '',
				'headingBottomMarginTablet'       => '',
				'headingTopMarginMobile'          => '',
				'headingRightMarginMobile'        => '',
				'headingLeftMarginMobile'         => '',
				'headingBottomMarginMobile'       => '',
				'headingMarginUnit'               => 'px',
				'headingMarginUnitTablet'         => 'px',
				'headingMarginUnitMobile'         => 'px',
				// fields.
				'fieldsBackground'                => '',
				'fieldsBackgroundHover'           => '',
				'fieldsBackgroundActive'          => '',
				'placeholderColor'                => '',
				'placeholderColorHover'           => '',
				'placeholderColorActive'          => '',
				'fieldsColor'                     => '',
				'fieldsFontFamily'                => 'Default',
				'fieldsFontWeight'                => '',
				'fieldsFontStyle'                 => 'normal',
				'fieldsTransform'                 => '',
				'fieldsDecoration'                => '',
				'fieldsLetterSpacing'             => '',
				'fieldsLetterSpacingTablet'       => '',
				'fieldsLetterSpacingMobile'       => '',
				'fieldsLetterSpacingType'         => 'px',
				'fieldsFontSizeType'              => 'px',
				'fieldsLineHeightType'            => 'em',
				'fieldsFontSize'                  => '',
				'fieldsFontSizeTablet'            => '',
				'fieldsFontSizeMobile'            => '',
				'fieldsLineHeight'                => '',
				'fieldsLineHeightTablet'          => '',
				'fieldsLineHeightMobile'          => '',
				// fields padding.
				'paddingFieldTop'                 => '',
				'paddingFieldRight'               => '',
				'paddingFieldBottom'              => '',
				'paddingFieldLeft'                => '',
				'paddingFieldTopTablet'           => '',
				'paddingFieldRightTablet'         => '',
				'paddingFieldBottomTablet'        => '',
				'paddingFieldLeftTablet'          => '',
				'paddingFieldTopMobile'           => '',
				'paddingFieldRightMobile'         => '',
				'paddingFieldBottomMobile'        => '',
				'paddingFieldLeftMobile'          => '',
				'paddingFieldUnit'                => 'px',
				'paddingFieldUnitmobile'          => 'px',
				'paddingFieldUnitTablet'          => 'px',
				// icon.
				'fieldsIconSize'                  => 12,
				'fieldsIconSizeType'              => 'px',
				'fieldsIconColor'                 => '#555D66',
				'fieldsIconBorderWidth'           => 1,
				'fieldsIconBorderColor'           => '',
				// link.
				'linkColor'                       => true,
				'linkHColor'                      => true,
				// login button.
				'loginSize'                       => 'default',
				'loginBackground'                 => '',
				'loginColor'                      => '',
				'loginHBackground'                => '',
				'loginHColor'                     => '',
				'loginFontFamily'                 => 'Default',
				'loginFontWeight'                 => '',
				'loginFontStyle'                  => 'normal',
				'loginDecoration'                 => '',
				'loginFontSizeType'               => 'px',
				'loginLineHeightType'             => 'em',
				'loginFontSize'                   => '',
				'loginTransform'                  => '',
				'loginFontSizeTablet'             => '',
				'loginFontSizeMobile'             => '',
				'loginLetterSpacing'              => '',
				'loginLetterSpacingTablet'        => '',
				'loginLetterSpacingMobile'        => '',
				'loginLetterSpacingType'          => 'px',
				'loginLineHeight'                 => '',
				'loginLineHeightTablet'           => '',
				'loginLineHeightMobile'           => '',
				'loginTopPadding'                 => '',
				'loginRightPadding'               => '',
				'loginLeftPadding'                => '',
				'loginBottomPadding'              => '',
				'loginTopPaddingTablet'           => '',
				'loginRightPaddingTablet'         => '',
				'loginLeftPaddingTablet'          => '',
				'loginBottomPaddingTablet'        => '',
				'loginTopPaddingMobile'           => '',
				'loginRightPaddingMobile'         => '',
				'loginLeftPaddingMobile'          => '',
				'loginBottomPaddingMobile'        => '',
				'loginPaddingUnit'                => 'px',
				'loginPaddingUnitTablet'          => 'px',
				'loginPaddingUnitMobile'          => 'px',

				// facebook.
				'facebookSize'                    => 'full',
				'facebookBackground'              => '',
				'facebookColor'                   => '',
				'facebookHBackground'             => '',
				'facebookHColor'                  => '',
				'facebookFontFamily'              => 'Default',
				'facebookFontWeight'              => '',
				'facebookFontStyle'               => 'normal',
				'facebookDecoration'              => '',
				'facebookFontSizeType'            => 'px',
				'facebookLineHeightType'          => 'em',
				'facebookFontSize'                => '',
				'facebookTransform'               => '',
				'facebookFontSizeTablet'          => '',
				'facebookFontSizeMobile'          => '',
				'facebookLetterSpacing'           => '',
				'facebookLetterSpacingTablet'     => '',
				'facebookLetterSpacingMobile'     => '',
				'facebookLetterSpacingType'       => 'px',
				'facebookLineHeight'              => '',
				'facebookLineHeightTablet'        => '',
				'facebookLineHeightMobile'        => '',
				'facebookTopPadding'              => '',
				'facebookRightPadding'            => '',
				'facebookLeftPadding'             => '',
				'facebookBottomPadding'           => '',
				'facebookTopPaddingTablet'        => '',
				'facebookRightPaddingTablet'      => '',
				'facebookLeftPaddingTablet'       => '',
				'facebookBottomPaddingTablet'     => '',
				'facebookTopPaddingMobile'        => '',
				'facebookRightPaddingMobile'      => '',
				'facebookLeftPaddingMobile'       => '',
				'facebookBottomPaddingMobile'     => '',
				'facebookPaddingUnit'             => 'px',
				'facebookPaddingUnitTablet'       => 'px',
				'facebookPaddingUnitMobile'       => 'px',
				// google.
				'googleSize'                      => 'full',
				'googleBackground'                => '',
				'googleColor'                     => '',
				'googleHBackground'               => '',
				'googleHColor'                    => '',
				'googleFontFamily'                => 'Default',
				'googleFontWeight'                => '',
				'googleFontStyle'                 => 'normal',
				'googleDecoration'                => '',
				'googleFontSizeType'              => 'px',
				'googleLineHeightType'            => 'em',
				'googleFontSize'                  => '',
				'googleTransform'                 => '',
				'googleFontSizeTablet'            => '',
				'googleFontSizeMobile'            => '',
				'googleLetterSpacing'             => '',
				'googleLetterSpacingTablet'       => '',
				'googleLetterSpacingMobile'       => '',
				'googleLetterSpacingType'         => 'px',
				'googleLineHeight'                => '',
				'googleLineHeightTablet'          => '',
				'googleLineHeightMobile'          => '',
				'googleTopPadding'                => '',
				'googleRightPadding'              => '',
				'googleLeftPadding'               => '',
				'googleBottomPadding'             => '',
				'googleTopPaddingTablet'          => '',
				'googleRightPaddingTablet'        => '',
				'googleLeftPaddingTablet'         => '',
				'googleBottomPaddingTablet'       => '',
				'googleTopPaddingMobile'          => '',
				'googleRightPaddingMobile'        => '',
				'googleLeftPaddingMobile'         => '',
				'googleBottomPaddingMobile'       => '',
				'googlePaddingUnit'               => 'px',
				'googlePaddingUnitTablet'         => 'px',
				'googlePaddingUnitMobile'         => 'px',

				// settings.
				'redirectAfterLoginURL'           => '',
				'redirectAfterLogoutURL'          => '',
				// message.
				'errorMessageBackground'          => '#f8d7da',
				'errorMessageColor'               => '#721c24',
				'errorMessageBorderColor'         => '#ff0000',
				'successMessageBackground'        => '#d4edda',
				'successMessageColor'             => '#155724',
				'successMessageBorderColor'       => '#008000',
				// register Info style.
				'registerInfoLoadGoogleFonts'     => false,
				'registerInfoFontFamily'          => 'Default',
				'registerInfoFontWeight'          => '',
				'registerInfoFontSize'            => '',
				'registerInfoFontSizeType'        => 'px',
				'registerInfoFontSizeTablet'      => '',
				'registerInfoFontSizeMobile'      => '',
				'registerInfoLineHeight'          => '',
				'registerInfoLineHeightType'      => 'em',
				'registerInfoLineHeightTablet'    => '',
				'registerInfoLineHeightMobile'    => '',
				'registerInfoFontStyle'           => 'normal',
				'registerInfoLetterSpacing'       => '',
				'registerInfoLetterSpacingTablet' => '',
				'registerInfoLetterSpacingMobile' => '',
				'registerInfoLetterSpacingType'   => 'px',
				'registerInfoColor'               => '',
				'registerInfoHoverColor'          => '',
				'registerInfoDecoration'          => '',
				'registerInfoTransform'           => '',
				'overallAlignment'                => '',
				'alignLoginBtn'                   => 'full',
				'alignLoginBtnTablet'             => 'full',
				'alignLoginBtnMobile'             => 'full',
				'eyeIconSize'                     => 20,
				'eyeIconSizeType'                 => 'px',
				'ctaIcon'                         => '',
				'ctaIconPosition'                 => 'after',
				'ctaIconSpace'                    => 5,
				'ctaIconSpaceTablet'              => '',
				'ctaIconSpaceMobile'              => '',
				'ctaIconSpaceType'                => 'px',
				'backgroundType'                  => 'none',
				'backgroundImageDesktop'          => '',
				'backgroundImageTablet'           => '',
				'backgroundImageMobile'           => '',
				'backgroundPositionDesktop'       => [
					'x' => 0.5,
					'y' => 0.5,
				],
				'backgroundPositionTablet'        => '',
				'backgroundPositionMobile'        => '',
				'backgroundSizeDesktop'           => 'cover',
				'backgroundSizeTablet'            => '',
				'backgroundSizeMobile'            => '',
				'backgroundRepeatDesktop'         => 'no-repeat',
				'backgroundRepeatTablet'          => '',
				'backgroundRepeatMobile'          => '',
				'backgroundAttachmentDesktop'     => 'scroll',
				'backgroundAttachmentTablet'      => '',
				'backgroundAttachmentMobile'      => '',
				'backgroundColor'                 => '',
				'backgroundOpacity'               => '',
				'backgroundImageColor'            => '#FFFFFF75',
				'gradientValue'                   => 'linear-gradient(90deg, rgba(6, 147, 227, 0.5) 0%, rgba(155, 81, 224, 0.5) 100%)',
				'gradientColor1'                  => '#06558a',
				'gradientColor2'                  => '#0063a1',
				'gradientType'                    => 'linear',
				'gradientLocation1'               => 0,
				'gradientLocation2'               => 100,
				'gradientAngle'                   => 0,
				'selectGradient'                  => 'basic',
				'backgroundCustomSizeDesktop'     => 100,
				'backgroundCustomSizeTablet'      => '',
				'backgroundCustomSizeMobile'      => '',
				'backgroundCustomSizeType'        => '%',
				'overlayType'                     => 'none',
				'customPosition'                  => 'default',
				'xPositionDesktop'                => '',
				'xPositionTablet'                 => '',
				'xPositionMobile'                 => '',
				'xPositionType'                   => 'px',
				'xPositionTypeTablet'             => 'px',
				'xPositionTypeMobile'             => 'px',
				'yPositionDesktop'                => '',
				'yPositionTablet'                 => '',
				'yPositionMobile'                 => '',
				'yPositionType'                   => 'px',
				'yPositionTypeTablet'             => 'px',
				'yPositionTypeMobile'             => 'px',
				// shadow.
				'boxShadowColor'                  => '#00000070',
				'boxShadowHOffset'                => 0,
				'boxShadowVOffset'                => 0,
				'boxShadowBlur'                   => '',
				'boxShadowSpread'                 => '',
				'boxShadowPosition'               => 'outset',
				'boxShadowColorHover'             => '',
				'boxShadowHOffsetHover'           => 0,
				'boxShadowVOffsetHover'           => 0,
				'boxShadowBlurHover'              => '',
				'boxShadowSpreadHover'            => '',
				'boxShadowPositionHover'          => 'outset',
				'usernameLabel'                   => __( 'Username or Email Address', 'suredash' ),
				'passwordLabel'                   => __( 'Password', 'suredash' ),
			],
			$form_border_attribute,
			$fields_border_attribute,
			$login_border_attribute,
			$facebook_border_attribute,
			$google_border_attribute,
			[
				'formBorderStyle'   => 'default',
				'fieldsBorderStyle' => 'default',
				'loginBorderStyle'  => 'default',
			]
		);
	}

	/**
	 * Get block default values.
	 *
	 * @param array<string, string> $attrs Attributes.
	 * @return array<string, string>
	 * @since 1.0.0
	 */
	public function get_block_parsed_values( $attrs = [] ) {
		$block_attrs        = $this->get_default_attributes();
		$block_key_defaults = [];

		foreach ( $block_attrs as $key => $value ) {
			$block_key_defaults[ $key ] = $value['default'];
		}

		// Merge the parsed values with the default values.
		$attrs = wp_parse_args( $attrs, $block_key_defaults );

		// Backwards compatibility: if spam protection option is missing.
		// but reCAPTCHA was previously enabled, default to reCAPTCHA.
		if ( isset( $attrs['reCaptchaEnable'] ) && $attrs['reCaptchaEnable'] &&
			( ! isset( $attrs['spamProtection'] ) || $attrs['spamProtection'] === 'none' ) ) {
			$attrs['spamProtection'] = 'recaptcha';
		}

		return $attrs;
	}

	/**
	 * Enqueue Google Fonts based on block attributes.
	 *
	 * @param array<string, string> $block_attr The block attributes containing font settings.
	 * @param string                $block_id   The unique ID for the block, used to enqueue the style.
	 *
	 * @since 0.0.1
	 */
	public function enqueue_front_assets( $block_attr, $block_id ): void {
		// Initialize an array to hold the font family and weight combinations.
		$font_families = [];

		// Define the entities to check for Google Fonts loading.
		$entities = [ 'fields', 'label', 'login', 'registerInfo', 'heading' ];

		// Iterate over each entity to check and collect font family and weight information.
		foreach ( $entities as $entity ) {
			// Get the font family and weight from block attributes if set.
			$font_family = ! empty( $block_attr[ $entity . 'FontFamily' ] ) ? $block_attr[ $entity . 'FontFamily' ] : '';
			$font_weight = ! empty( $block_attr[ $entity . 'FontWeight' ] ) ? $block_attr[ $entity . 'FontWeight' ] : '';

			// If a font family is specified, add it to the array, optionally including the weight.
			if ( $font_family ) {
				$font_families[] = $font_weight ? "{$font_family}:{$font_weight}" : $font_family;
			}
		}

		// Enqueue the Google Fonts stylesheet if there are any font families to load.
		if ( ! empty( $font_families ) ) {
			$font_families_str = str_replace( ' ', '+', implode( '|', $font_families ) );
			wp_enqueue_style( 'sd-google-font-' . $block_id, esc_url( 'https://fonts.googleapis.com/css?family=' . $font_families_str . '&subset=latin,latin-ext&display=fallback' ), [], SUREDASHBOARD_VER );
		}

		$this->render_block_script( $block_attr, $block_id );
	}

	/**
	 * Render block script.
	 *
	 * @param array<string, string> $block_attr The block attributes.
	 * @param string                $block_id   The block ID.
	 *
	 * @since 1.0.0
	 */
	public function render_block_script( $block_attr, $block_id ): void {
		wp_enqueue_style( 'dashicons' );
		wp_enqueue_script( 'google-gsi-client', esc_url( 'https://accounts.google.com/gsi/client' ), [], SUREDASHBOARD_VER, true );

		$selector   = '.uagb-block-' . $block_id;  // Block selector.
		$block_attr = $this->get_block_parsed_values( $block_attr );

		// Generate and output dynamic block CSS.
		$block_css = $this->get_dynamic_block_css( $block_attr, $block_id );

		$this_field_error_msg = [
			'username' => sprintf(
				// translators: %1$s: User Name Field.
				__( 'Field "%1$s" cannot be blank.', 'suredash' ),
				is_string( $block_attr['usernameLabel'] ) && ! empty( $block_attr['usernameLabel'] ) ? esc_html( $block_attr['usernameLabel'] ) : __( 'Username Field', 'suredash' )
			),
			'password' => sprintf(
				// translators: %1$s: Password Field.
				__( 'Field "%1$s" cannot be blank.', 'suredash' ),
				is_string( $block_attr['passwordLabel'] ) && ! empty( $block_attr['passwordLabel'] ) ? esc_html( $block_attr['passwordLabel'] ) : __( 'Password Field', 'suredash' )
			),
		];

		$login_block_options = apply_filters(
			'suredashboard_login_options',
			[
				'ajax_url'             => esc_url( admin_url( 'admin-ajax.php' ) ),
				'nonce'                => wp_create_nonce( 'suredash_forgot_password' ),
				'post_id'              => get_the_ID(),
				'block_id'             => $block_id,
				'captchaType'          => $block_attr['spamProtection'],
				'enableReCaptcha'      => $block_attr['reCaptchaEnable'],
				'recaptchaVersion'     => $block_attr['reCaptchaType'],
				'recaptchaSiteKey'     => Helper::get_option( 'recaptcha_site_key_' . $block_attr['reCaptchaType'], '' ),
				'turnstileSiteKey'     => Helper::get_option( 'turnstile_site_key', '' ),
				'turnstileTheme'       => $block_attr['turnstileAppearance'],
				'loggedInMessage'      => esc_html__( 'You have logged in successfully. Redirecting…', 'suredash' ),
				'enableFacebookLogin'  => $block_attr['enableFacebookLogin'],
				'enableGoogleLogin'    => $block_attr['enableGoogleLogin'],
				'loginRedirectURL'     => esc_url( ( is_array( $block_attr['redirectAfterLoginURL'] ) && $block_attr['redirectAfterLoginURL']['url'] ? $block_attr['redirectAfterLoginURL']['url'] : home_url( '/' . suredash_get_community_slug() . '/' ) ) ),
				'logoutRedirectURL'    => esc_url( ( is_array( $block_attr['redirectAfterLogoutURL'] ) && $block_attr['redirectAfterLogoutURL']['url'] ? $block_attr['redirectAfterLogoutURL']['url'] : home_url( '/' ) ) ),
				'this_field_error_msg' => $this_field_error_msg,
			],
			$block_id
		);

		ob_start();
		?>
			<script>
				window.addEventListener( 'load', function() {
					SureDashLogin.init( '<?php echo esc_attr( $selector ); ?>', <?php echo wp_json_encode( $login_block_options ); ?> );

					<?php
					// phpcs:ignore WordPress.PHP.YodaConditions -- Non-Yoda required by PHP Insights (Slevomat) standard.
					if ( $block_attr['spamProtection'] === 'turnstile' ) {
						?>
					/**
					 * Cloudflare Turnstile Auto-Reset on Login Error
					 *
					 * Automatically resets the Turnstile captcha widget when a login error occurs,
					 * ensuring users can retry without page refresh.
					 *
					 * @since 1.5.4
					 */
					(function () {
						'use strict';

						var selector      = '<?php echo esc_js( $selector ); ?>';
						var containerSel  = selector + ' .cf-turnstile';
						var statusSel     = selector + ' .spectra-pro-login-form-status';
						var errorSel      = '.spectra-pro-login-form-status__error, .spectra-pro-login-form-status__error-item';

						var maxApiRetries = 10;
						var retryDelay    = 200;
						var resetDelay    = 150;

						var apiRetryCount = 0;
						var resetInProgress = false;
						var observer      = null;

						/**
						 * Reset Turnstile captcha safely.
						 */
						function resetTurnstile() {
							if ( typeof window.turnstile === 'undefined' ) {
								if ( apiRetryCount++ < maxApiRetries ) {
									setTimeout( resetTurnstile, retryDelay );
								}
								return;
							}

							var container = document.querySelector( containerSel );
							if ( ! container ) {
								return;
							}

							try {
								window.turnstile.reset( container );
							} catch ( error ) {
								if ( window.console && console.warn ) {
									console.warn( 'SureDash: Turnstile reset failed', error );
								}
							}
						}

						/**
						 * Debounced reset trigger.
						 */
						function triggerReset() {
							if ( resetInProgress ) {
								return;
							}

							resetInProgress = true;

							setTimeout( function () {
								resetTurnstile();
								resetInProgress = false;
							}, resetDelay );
						}

						/**
						 * Initialize MutationObserver for login error detection.
						 */
						function initObserver() {
							var statusEl = document.querySelector( statusSel );

							if ( ! statusEl || typeof MutationObserver === 'undefined' ) {
								setTimeout( initObserver, 300 );
								return;
							}

							observer = new MutationObserver( function () {
								var errorEl = statusEl.querySelector( errorSel );

								if ( errorEl && errorEl.textContent.trim() !== '' ) {
									triggerReset();
								}
							} );

							observer.observe( statusEl, {
								childList: true,
								subtree: true,
								characterData: true,
							} );
						}

						// Initialize observer
						initObserver();
					})();
					<?php } ?>
				});
			</script>
		<?php
		if ( ! empty( $block_css ) ) {
			// Handle both string and array returns from generate_all_css.
			$css_output = is_array( $block_css ) ? implode( ' ', $block_css ) : $block_css;
			if ( ! empty( $css_output ) ) {
				?>
				<style>
					<?php echo $css_output; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
				</style>
				<?php
			}
		}
		echo do_shortcode( ob_get_clean() ); // @phpstan-ignore-line
	}

	/**
	 * Render Categories Block HTML.
	 *
	 * @param array<string, string> $attributes Array of block attributes.
	 * @param string                $content String of block Markup.
	 *
	 * @since 1.0.0
	 *
	 * @return string|false
	 */
	public function render_html( $attributes, $content ) {

		$desktop_class = '';
		$tab_class     = '';
		$mob_class     = '';

		$sd_common_selector_class = ''; // Required for z-index.

		if ( ! is_array( $attributes ) ) {
			$attributes = [];
		}

		if ( array_key_exists( 'UAGHideDesktop', $attributes ) || array_key_exists( 'UAGHideTab', $attributes ) || array_key_exists( 'UAGHideMob', $attributes ) ) {

			$desktop_class = isset( $attributes['UAGHideDesktop'] ) ? 'uag-hide-desktop' : '';

			$tab_class = isset( $attributes['UAGHideTab'] ) ? 'uag-hide-tab' : '';

			$mob_class = isset( $attributes['UAGHideMob'] ) ? 'uag-hide-mob' : '';
		}

		$z_index_wrap = [];
		if ( array_key_exists( 'zIndex', $attributes ) || array_key_exists( 'zIndexTablet', $attributes ) || array_key_exists( 'zIndexMobile', $attributes ) ) {
			$sd_common_selector_class = 'uag-blocks-common-selector';
			$z_index_desktop          = array_key_exists( 'zIndex', $attributes ) && ( $attributes['zIndex'] !== '' ) ? '--z-index-desktop:' . $attributes['zIndex'] . ';' : false;
			$z_index_tablet           = array_key_exists( 'zIndexTablet', $attributes ) && ( $attributes['zIndexTablet'] !== '' ) ? '--z-index-tablet:' . $attributes['zIndexTablet'] . ';' : false;
			$z_index_mobile           = array_key_exists( 'zIndexMobile', $attributes ) && ( $attributes['zIndexMobile'] !== '' ) ? '--z-index-mobile:' . $attributes['zIndexMobile'] . ';' : false;

			if ( $z_index_desktop ) {
				array_push( $z_index_wrap, $z_index_desktop );
			}

			if ( $z_index_tablet ) {
				array_push( $z_index_wrap, $z_index_tablet );
			}

			if ( $z_index_mobile ) {
				array_push( $z_index_wrap, $z_index_mobile );
			}
		}

		$wrapper_classes = [
			'uagb-block-' . $attributes['block_id'],
			'wp-block-spectra-pro-login',
			'suredash-login-active',
			$desktop_class,
			$tab_class,
			$mob_class,
			$sd_common_selector_class,
		];

		if ( is_user_logged_in() && ! $attributes['enableLoggedInMessage'] ) {
			return false;
		}

		$recaptcha_site_key = Helper::get_option( 'recaptcha_site_key_v2', '' );
		if ( ! is_string( $recaptcha_site_key ) ) {
			$recaptcha_site_key = '';
		}

		$turnstile_site_key = Helper::get_option( 'turnstile_site_key', '' );
		if ( ! is_string( $turnstile_site_key ) ) {
			$turnstile_site_key = '';
		}

		ob_start();

		?>
			<div class="<?php echo esc_attr( implode( ' ', $wrapper_classes ) ); ?>" style="<?php echo esc_attr( implode( '', $z_index_wrap ) ); ?>">
				<?php
				if ( is_user_logged_in() ) {
					?>
					<div class="wp-block-spectra-pro-login__logged-in-message">
						<?php
							$user_name   = suredash_get_user_display_name();
							$a_tag       = '<a href="' . esc_url( wp_logout_url( is_array( $attributes['redirectAfterLogoutURL'] ) && $attributes['redirectAfterLogoutURL']['url'] ? $attributes['redirectAfterLogoutURL']['url'] : home_url( suredash_get_community_slug() ) ) ) . '">';
							$close_a_tag = '</a>';
							/* translators: %1$s user name */
							printf( esc_html__( 'You are logged in as %1$s (%2$sLogout%3$s)', 'suredash' ), wp_kses_post( $user_name ), wp_kses_post( $a_tag ), wp_kses_post( $close_a_tag ) );
						?>
					</div>
					<?php
				} else {
					// inner block content will be here.
					echo wp_kses_post( $content );
					// phpcs:ignore WordPress.Security.NonceVerification.Recommended
					$action = isset( $_GET['action'] ) ? sanitize_text_field( $_GET['action'] ) : 'login';

					switch ( $action ) {
						case 'lostpassword':
							// Render forgot password form.
							?>
								<?php if ( ! empty( $attributes['showFormHeading'] ) ) { ?>
									<<?php echo esc_attr( $attributes['formHeadingTag'] ); ?> class="spectra-pro-login-form__heading">
										<?php echo esc_html( $attributes['lostPasswordHeading'] ); ?>
									</<?php echo esc_attr( $attributes['formHeadingTag'] ); ?>>
								<?php } ?>
								<div class="suredash-forgot-password-form-wrap">
									<form
										class="suredash-forgot-password-form"
										data-url="<?php echo esc_url( admin_url( 'admin-ajax.php' ) ); ?>"
										data-nonce="<?php echo esc_attr( wp_create_nonce( 'suredash_forgot_password' ) ); ?>"
										data-redirect="<?php echo esc_attr( $attributes['redirectAfterLoginURL']['url'] ?? '' ); ?>"
									>
										<label for="suredash-reset-email"><?php echo wp_kses_post( $attributes['enterYourEmailAddress'] ); ?></label>
										<div>
											<input type="email" id="suredash-reset-email" placeholder="<?php echo esc_attr( $attributes['emailPlaceholder'] ); ?>" />
										</div>
										<div class="suredash-reset-password-submit-container">
											<a href="<?php echo esc_url( remove_query_arg( 'action' ) ); ?>" class="suredash-back-to-login-link"><?php echo wp_kses_post( $attributes['backToLogin'] ); ?></a>
											<div class="suredash-reset-password-submit-wrap">
												<button type="button" class="suredash-reset-password-submit"><?php echo wp_kses_post( $attributes['getNewPassword'] ); ?></button>
											</div>
										</div>
										<div class="suredash-reset-status"></div>
									</form>
								</div>
								<?php
							break;
						case 'resetpassword':
							// Render reset password form.
							?>
							<?php if ( ! empty( $attributes['showFormHeading'] ) ) { ?>
								<<?php echo esc_attr( $attributes['formHeadingTag'] ); ?> class="spectra-pro-login-form__heading">
									<?php echo esc_html( $attributes['resetPasswordHeading'] ); ?>
								</<?php echo esc_attr( $attributes['formHeadingTag'] ); ?>>
							<?php } ?>
							<div class="suredash-forgot-password-form-wrap">
								<form
									class="suredash-forgot-password-form"
									name="resetpassform"
									id="resetpassform"
									action="<?php echo esc_url( admin_url( 'admin-ajax.php' ) ); ?>"
									method="post"
									autocomplete="off"
									data-url="<?php echo esc_url( admin_url( 'admin-ajax.php' ) ); ?>"
									data-nonce="<?php echo esc_attr( wp_create_nonce( 'suredash_forgot_password' ) ); ?>"
									data-redirect="<?php echo esc_attr( suredash_get_login_page_url() ); ?>"
								>
								<?php
									// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized, WordPress.Security.NonceVerification.Recommended
									$reset_key = isset( $_GET['key'] ) ? sanitize_text_field( $_GET['key'] ) : '';
									// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized, WordPress.Security.NonceVerification.Recommended
									$reset_login = isset( $_GET['login'] ) ? sanitize_text_field( $_GET['login'] ) : '';
								?>

									<!-- Hidden fields for reset logic -->
									<input type="hidden" name="reset_key" value="<?php echo esc_attr( $reset_key ); ?>">
									<input type="hidden" name="reset_login" value="<?php echo esc_attr( $reset_login ); ?>">

									<label for="pass1"><?php echo wp_kses_post( $attributes['newPassword'] ); ?></label>
									<div>
										<input class="suredash-forgot-password-form-input" type="password" name="pass1" id="pass1" size="24" autocomplete="new-password" />
									</div>

									<div class="suredash-forgot-password-form-input-wrap">
										<label for="pass2"><?php echo wp_kses_post( $attributes['confirmNewPassword'] ); ?></label>
										<input class="suredash-forgot-password-form-input" type="password" name="pass2" id="pass2" size="24" autocomplete="new-password" />
									</div>

									<p class="description indicator-hint">
										<?php echo wp_kses_post( $attributes['hint'] ); ?>
									</p>

									<div class="suredash-reset-password-submit-container">
										<a href="<?php echo esc_url( remove_query_arg( 'action' ) ); ?>" class="suredash-back-to-login-link">
											<?php echo wp_kses_post( $attributes['backToLogin'] ); ?>
										</a>
										<div class="suredash-reset-password-submit-wrap">
											<button type="submit" class="suredash-reset-password-submit">
												<?php echo wp_kses_post( $attributes['restPassword'] ); ?>
											</button>
										</div>
									</div>
								</form>
								<div class="suredash-reset-status"></div>
							</div>
							<?php
							break;

						case 'login':
							if ( ! $attributes['disableFormFields'] ) {
								?>
									<?php if ( ! empty( $attributes['showFormHeading'] ) && ! empty( $attributes['formHeadingText'] ) ) { ?>
										<<?php echo esc_attr( $attributes['formHeadingTag'] ); ?> class="spectra-pro-login-form__heading">
											<?php echo wp_kses_post( $attributes['formHeadingText'] ); ?>
										</<?php echo esc_attr( $attributes['formHeadingTag'] ); ?>>
										<?php
										if ( $attributes['socialLoginPosition'] === 'above-form' ) {
											echo do_shortcode( $this->social_render( $attributes ) ); // @phpstan-ignore-line
										}
									}
									?>
									<div class='suredash-login-fields'>
										<form
											id="<?php echo esc_attr( 'spectra-pro-login-form-' . $attributes['block_id'] ); ?>" action="#" method="post"
											class="spectra-pro-login-form">
										<?php wp_nonce_field( 'portal_social_google_login', '_nonce' ); ?>

											<div class="spectra-pro-login-form__user-login">
											<?php
											if ( ! empty( $attributes['usernameLabel'] ) ) {
												?>
												<label for="<?php echo esc_attr( 'username-' . $attributes['block_id'] ); ?>"><?php echo wp_kses_post( $attributes['usernameLabel'] ); ?></label>
											<?php } ?>

												<div class='<?php echo ! $attributes['isHideIcon'] ? 'spectra-pro-login-form-username-wrap spectra-pro-login-form-username-wrap--have-icon' : 'spectra-pro-login-form-username-wrap'; ?>'>
													<?php
													if ( ! $attributes['isHideIcon'] ) {
														Helper::get_library_icon( 'User', true );
													}
													?>
													<input id="<?php echo esc_attr( 'username-' . $attributes['block_id'] ); ?>" type="text" name="username" placeholder="<?php echo esc_attr( $attributes['usernamePlaceholder'] ); ?>" />
												</div>
											</div>
											<div class="spectra-pro-login-form__user-pass">
													<?php
													if ( ! empty( $attributes['passwordLabel'] ) ) {
														?>
												<label for="<?php echo esc_attr( 'password-' . $attributes['block_id'] ); ?>"><?php echo wp_kses_post( $attributes['passwordLabel'] ); ?></label>
														<?php
													}
													?>
												<div class='<?php echo ! $attributes['isHideIcon'] ? 'spectra-pro-login-form-pass-wrap spectra-pro-login-form-pass-wrap--have-icon' : 'spectra-pro-login-form-pass-wrap'; ?>'>
													<?php
													if ( ! $attributes['isHideIcon'] ) {
														Helper::get_library_icon( 'Lock', true );
													}
													?>
													<input id="<?php echo esc_attr( 'password-' . $attributes['block_id'] ); ?>" type="password" name="password" placeholder="<?php echo esc_attr( $attributes['passwordPlaceholder'] ); ?>" />
													<button id="<?php echo esc_attr( 'password-visibility-' . $attributes['block_id'] ); ?>" type='button' aria-label="<?php echo esc_attr( __( 'Show Password', 'suredash' ) ); ?>" ><span class="dashicons dashicons-visibility"></span></button>
												</div>
											</div>
											<div class="spectra-pro-login-form__forgetmenot">
												<div class="spectra-pro-login-form-rememberme">
													<label for="<?php echo esc_attr( 'rememberme-' . $attributes['block_id'] ); ?>">
														<input name="rememberme" type="checkbox" id="<?php echo esc_attr( 'rememberme-' . $attributes['block_id'] ); ?>" />
														<span class="spectra-pro-login-form-rememberme__checkmark"></span>
													<?php
													if ( ! empty( $attributes['rememberMeLabel'] ) ) {
														// The div below ensures that the label is unaffected by flex styling on it's parent.
														// Flex styling strips away the spaces in rich-text.
														?>
																<div class="spectra-pro-login-form-rememberme__checkmark-label">
																<?php
																	echo wp_kses_post( $attributes['rememberMeLabel'] );
																?>
																</div>
															<?php
													}
													?>
													</label>
												</div>
													<?php
													if ( ! empty( $attributes['forgotPasswordLabel'] ) ) {
														?>
												<div class="spectra-pro-login-form-forgot-password">
													<a href="<?php echo esc_url( add_query_arg( 'action', 'lostpassword', get_permalink() ) ); ?>">
														<?php echo esc_html( $attributes['forgotPasswordLabel'] ); ?>
													</a>
												</div>
													<?php } ?>
											</div>
											<?php if ( $attributes['spamProtection'] === 'recaptcha' && $attributes['reCaptchaType'] === 'v2' ) { ?>
											<div class="spectra-pro-login-form__recaptcha">
													<div class="g-recaptcha" data-sitekey="<?php echo esc_attr( $recaptcha_site_key ); ?>"></div>
													<input
															type="hidden"
															id="g-recaptcha-response"
													/>
											</div>
											<?php } elseif ( $attributes['spamProtection'] === 'turnstile' ) { ?>
											<div class="spectra-pro-login-form__turnstile">
													<div class="cf-turnstile" data-sitekey="<?php echo esc_attr( $turnstile_site_key ); ?>" data-theme="<?php echo esc_attr( $attributes['turnstileAppearance'] ); ?>"></div>
											</div>
											<?php } ?>
											<div class="spectra-pro-login-form__submit wp-block-button">
												<button class="spectra-pro-login-form-submit-button wp-block-button__link" type="submit">
													<?php
													if ( $attributes['ctaIconPosition'] === 'before' ) {
														Helper::get_library_icon( $attributes['ctaIcon'], true );
													}
													?>

													<span className='label-wrap'>
													<?php echo esc_attr( $attributes['loginButtonLabel'] ); ?>
													</span>

													<?php
													if ( $attributes['ctaIconPosition'] === 'after' ) {
														Helper::get_library_icon( $attributes['ctaIcon'], true );
													}
													?>
												</button>
											</div>
										</form>
									</div>
									<?php
							}
							if ( $attributes['socialLoginPosition'] === 'below-form' ) {
								echo do_shortcode( $this->social_render( $attributes ) ); // @phpstan-ignore-line
							}
							break;
					}
					?>

					<div id="<?php echo esc_attr( 'spectra-pro-login-form-status-' . $attributes['block_id'] ); ?>" class="spectra-pro-login-form-status"></div>

					<?php

					if ( $attributes['showRegisterInfo'] ) {
						?>
						<div class='wp-block-spectra-pro-login__footer'>
							<p class='wp-block-spectra-pro-login-info'><?php echo esc_html( $attributes['registerInfo'] ); ?>
								<a
									class="spectra-pro-login-form-register"
									href="<?php echo is_array( $attributes['registerButtonLink'] ) && ! empty( $attributes['registerButtonLink']['url'] ) ? esc_url( $attributes['registerButtonLink']['url'] ) : esc_url( wp_registration_url() ); ?>"
									<?php
										echo is_array( $attributes['registerButtonLink'] ) && isset( $attributes['registerButtonLink']['opensInNewTab'] ) ? ' target="_blank"' : '';
									?>
									<?php
										echo is_array( $attributes['registerButtonLink'] ) && isset( $attributes['registerButtonLink']['noFollow'] ) ? ' rel="noFollow"' : '';
									?>
								>
									<?php echo esc_html( $attributes['registerButtonLabel'] ); ?>
								</a>
							</p>
						</div>
						<?php
					}
				}
				?>
			</div>
		<?php

		return ob_get_clean();
	}

	/**
	 * Render Social Markup
	 *
	 * @param array<mixed> $attributes Attributes.
	 * @return string|false
	 * @since 1.0.0
	 */
	public function social_render( $attributes ) {
		ob_start();

		if ( $attributes['enableFacebookLogin'] || $attributes['enableGoogleLogin'] ) {

			$social_connect = [
				'google_token_id'       => Helper::get_option( 'google_token_id' ),
				'google_token_secret'   => Helper::get_option( 'google_token_secret' ),
				'facebook_token_id'     => Helper::get_option( 'facebook_token_id' ),
				'facebook_token_secret' => Helper::get_option( 'facebook_token_secret' ),
			];

			if ( empty( $social_connect['facebook_token_id'] ) && empty( $social_connect['facebook_token_secret'] ) && empty( $social_connect['google_token_id'] ) ) {
				return ob_get_clean();
			}

			if ( ! $attributes['socialReverseToogle'] ) {
				?>
				<div class="spectra-pro-login-form__social">
					<?php
					if ( $attributes['enableGoogleLogin'] && is_string( $social_connect['google_token_id'] ) && ! empty( $social_connect['google_token_id'] ) ) {
						?>
					<button type="button" class="spectra-pro-login-form__social-google" id="uagb-pro-login-googleLink" data-clientid="<?php echo esc_attr( $social_connect['google_token_id'] ); ?>">
						<svg width="17" height="16" viewBox="0 0 17 16" fill="none" xmlns="http://www.w3.org/2000/svg">
							<g clip-path="url(#clip0_1327_4903)">
							<path d="M15.29 8.16667C15.29 7.64667 15.2433 7.14667 15.1567 6.66667H8.25V9.50333H12.1967C12.0267 10.42 11.51 11.1967 10.7333 11.7167V13.5567H13.1033C14.49 12.28 15.29 10.4 15.29 8.16667Z" fill="#4285F4"/>
							<path d="M8.24962 15.3333C10.2296 15.3333 11.8896 14.6767 13.103 13.5567L10.733 11.7167C10.0763 12.1567 9.23629 12.4167 8.24962 12.4167C6.33962 12.4167 4.72296 11.1267 4.14629 9.39333H1.69629V11.2933C2.90296 13.69 5.38296 15.3333 8.24962 15.3333Z" fill="#34A853"/>
							<path d="M4.14602 9.39333C3.99935 8.95333 3.91602 8.48333 3.91602 8C3.91602 7.51667 3.99935 7.04667 4.14602 6.60667V4.70667H1.69602C1.18268 5.72857 0.915563 6.85641 0.916016 8C0.916016 9.18333 1.19935 10.3033 1.69602 11.2933L4.14602 9.39333Z" fill="#FBBC05"/>
							<path d="M8.24962 3.58333C9.32629 3.58333 10.293 3.95333 11.053 4.68L13.1563 2.57667C11.8863 1.39333 10.2263 0.666666 8.24962 0.666666C5.38296 0.666666 2.90296 2.31 1.69629 4.70667L4.14629 6.60667C4.72296 4.87333 6.33962 3.58333 8.24962 3.58333Z" fill="#EA4335"/>
							</g>
							<defs>
							<clipPath id="clip0_1327_4903">
							<rect width="16" height="16" fill="white" transform="translate(0.25)"/>
							</clipPath>
							</defs>
						</svg>
						<?php echo esc_html( $attributes['googleLoginButtonText'] ); ?>
					</button>
						<?php
					}

					if ( $attributes['enableFacebookLogin'] && ! empty( $social_connect['facebook_token_id'] ) && is_string( $social_connect['facebook_token_id'] ) && ! empty( $social_connect['facebook_token_secret'] ) ) {
						?>
					<button type="button" class="spectra-pro-login-form__social-facebook" id="uagb-pro-login-fbLink" data-appid="<?php echo esc_attr( $social_connect['facebook_token_id'] ); ?>">
						<svg width="17" height="16" viewBox="0 0 17 16" fill="none" xmlns="http://www.w3.org/2000/svg">
							<g clip-path="url(#clip0_1327_4911)">
							<path d="M16.75 8C16.75 3.58172 13.1683 -2.86102e-06 8.75 -2.86102e-06C4.33172 -2.86102e-06 0.75 3.58172 0.75 8C0.75 11.993 3.67548 15.3027 7.5 15.9028V10.3125H5.46875V8H7.5V6.2375C7.5 4.2325 8.69434 3.125 10.5217 3.125C11.397 3.125 12.3125 3.28125 12.3125 3.28125V5.25H11.3037C10.3099 5.25 10 5.86667 10 6.49933V8H12.2188L11.8641 10.3125H10V15.9028C13.8245 15.3027 16.75 11.993 16.75 8Z" fill="#1877F2"/>
							<path d="M11.8641 10.3125L12.2188 8H10V6.49933C10 5.86667 10.3099 5.25 11.3037 5.25H12.3125V3.28125C12.3125 3.28125 11.397 3.125 10.5217 3.125C8.69434 3.125 7.5 4.2325 7.5 6.2375V8H5.46875V10.3125H7.5V15.9028C7.9073 15.9667 8.32475 16 8.75 16C9.17525 16 9.5927 15.9667 10 15.9028V10.3125H11.8641Z" fill="white"/>
							</g>
							<defs>
							<clipPath id="clip0_1327_4911">
							<rect width="16" height="16" fill="white" transform="translate(0.75)"/>
							</clipPath>
							</defs>
						</svg>
						<?php echo esc_html( $attributes['facebookLoginButtonText'] ); ?>
					</button>
						<?php
					}
					?>
				</div>
				<?php
			} else {
				?>
				<div class="spectra-pro-login-form__social">
					<?php
					if ( $attributes['enableFacebookLogin'] && ! empty( $social_connect['facebook_token_id'] ) && is_string( $social_connect['facebook_token_id'] ) && ! empty( $social_connect['facebook_token_secret'] ) ) {
						?>

					<button type="button" class="spectra-pro-login-form__social-facebook" id="uagb-pro-login-fbLink" data-appid="<?php echo esc_attr( $social_connect['facebook_token_id'] ); ?>">
						<svg width="17" height="16" viewBox="0 0 17 16" fill="none" xmlns="http://www.w3.org/2000/svg">
							<g clip-path="url(#clip0_1327_4911)">
							<path d="M16.75 8C16.75 3.58172 13.1683 -2.86102e-06 8.75 -2.86102e-06C4.33172 -2.86102e-06 0.75 3.58172 0.75 8C0.75 11.993 3.67548 15.3027 7.5 15.9028V10.3125H5.46875V8H7.5V6.2375C7.5 4.2325 8.69434 3.125 10.5217 3.125C11.397 3.125 12.3125 3.28125 12.3125 3.28125V5.25H11.3037C10.3099 5.25 10 5.86667 10 6.49933V8H12.2188L11.8641 10.3125H10V15.9028C13.8245 15.3027 16.75 11.993 16.75 8Z" fill="#1877F2"/>
							<path d="M11.8641 10.3125L12.2188 8H10V6.49933C10 5.86667 10.3099 5.25 11.3037 5.25H12.3125V3.28125C12.3125 3.28125 11.397 3.125 10.5217 3.125C8.69434 3.125 7.5 4.2325 7.5 6.2375V8H5.46875V10.3125H7.5V15.9028C7.9073 15.9667 8.32475 16 8.75 16C9.17525 16 9.5927 15.9667 10 15.9028V10.3125H11.8641Z" fill="white"/>
							</g>
							<defs>
							<clipPath id="clip0_1327_4911">
							<rect width="16" height="16" fill="white" transform="translate(0.75)"/>
							</clipPath>
							</defs>
						</svg>
						<?php echo esc_html( $attributes['facebookLoginButtonText'] ); ?>
					</button>
						<?php
					}

					if ( $attributes['enableGoogleLogin'] && ! empty( $social_connect['google_token_id'] ) && is_string( $social_connect['google_token_id'] ) ) {
						?>
						<button type="button" class="spectra-pro-login-form__social-google" id="uagb-pro-login-googleLink" data-clientid="<?php echo esc_attr( $social_connect['google_token_id'] ); ?>">
							<svg width="17" height="16" viewBox="0 0 17 16" fill="none" xmlns="http://www.w3.org/2000/svg">
								<g clip-path="url(#clip0_1327_4903)">
								<path d="M15.29 8.16667C15.29 7.64667 15.2433 7.14667 15.1567 6.66667H8.25V9.50333H12.1967C12.0267 10.42 11.51 11.1967 10.7333 11.7167V13.5567H13.1033C14.49 12.28 15.29 10.4 15.29 8.16667Z" fill="#4285F4"/>
								<path d="M8.24962 15.3333C10.2296 15.3333 11.8896 14.6767 13.103 13.5567L10.733 11.7167C10.0763 12.1567 9.23629 12.4167 8.24962 12.4167C6.33962 12.4167 4.72296 11.1267 4.14629 9.39333H1.69629V11.2933C2.90296 13.69 5.38296 15.3333 8.24962 15.3333Z" fill="#34A853"/>
								<path d="M4.14602 9.39333C3.99935 8.95333 3.91602 8.48333 3.91602 8C3.91602 7.51667 3.99935 7.04667 4.14602 6.60667V4.70667H1.69602C1.18268 5.72857 0.915563 6.85641 0.916016 8C0.916016 9.18333 1.19935 10.3033 1.69602 11.2933L4.14602 9.39333Z" fill="#FBBC05"/>
								<path d="M8.24962 3.58333C9.32629 3.58333 10.293 3.95333 11.053 4.68L13.1563 2.57667C11.8863 1.39333 10.2263 0.666666 8.24962 0.666666C5.38296 0.666666 2.90296 2.31 1.69629 4.70667L4.14629 6.60667C4.72296 4.87333 6.33962 3.58333 8.24962 3.58333Z" fill="#EA4335"/>
								</g>
								<defs>
								<clipPath id="clip0_1327_4903">
								<rect width="16" height="16" fill="white" transform="translate(0.25)"/>
								</clipPath>
								</defs>
							</svg>
						<?php echo esc_html( $attributes['googleLoginButtonText'] ); ?>
						</button>
						<?php
					}
					?>
				</div>
				<?php
			}
		}

		return ob_get_clean();
	}

	/**
	 * Get Search block CSS
	 *
	 * @since 1.0.0
	 * @param array<mixed> $attr The block attributes.
	 * @param string       $id The selector ID.
	 * @return array<string, string> The Widget List.
	 */
	public function get_dynamic_block_css( $attr, $id ) {
		$defaults = $this->get_style_block_attributes();
		$attr     = array_merge( $defaults, (array) $attr );

		$is_rtl = is_rtl();

		$form_border_css        = Helper::generate_border_css( $attr, 'form' );
		$form_border_css_tablet = Helper::generate_border_css( $attr, 'form', 'tablet' );
		$form_border_css_mobile = Helper::generate_border_css( $attr, 'form', 'mobile' );

		$fields_border_css        = Helper::generate_border_css( $attr, 'fields' );
		$fields_border_css_tablet = Helper::generate_border_css( $attr, 'fields', 'tablet' );
		$fields_border_css_mobile = Helper::generate_border_css( $attr, 'fields', 'mobile' );

		$login_border_css        = Helper::generate_border_css( $attr, 'login' );
		$login_border_css_tablet = Helper::generate_border_css( $attr, 'login', 'tablet' );
		$login_border_css_mobile = Helper::generate_border_css( $attr, 'login', 'mobile' );

		$facebook_border_css        = Helper::generate_border_css( $attr, 'facebook' );
		$facebook_border_css_mobile = Helper::generate_border_css( $attr, 'facebook', 'mobile' );

		$google_border_css        = Helper::generate_border_css( $attr, 'google' );
		$google_border_css_tablet = Helper::generate_border_css( $attr, 'google', 'tablet' );

		$full_width_login_btn        = $attr['alignLoginBtn'] === 'full' ? [ 'width' => '100%' ] : [];
		$full_width_login_btn_tablet = $attr['alignLoginBtnTablet'] === 'full' ? [ 'width' => '100%' ] : [];
		$full_width_login_btn_mobile = $attr['alignLoginBtnMobile'] === 'full' ? [ 'width' => '100%' ] : [];

		// google facebook btn justify content alignment.
		$postition_google_facebook_button        = ( $attr['alignGooleFacebookBtn'] === 'right' ? 'end' : ( $attr['alignGooleFacebookBtn'] === 'center' ? 'center' : 'start' ) );
		$postition_google_facebook_button_tablet = ( $attr['alignGooleFacebookBtnTablet'] === 'right' ? 'end' : ( $attr['alignGooleFacebookBtnTablet'] === 'center' ? 'center' : 'start' ) );
		$postition_google_facebook_button_mobile = ( $attr['alignGooleFacebookBtnMobile'] === 'right' ? 'end' : ( $attr['alignGooleFacebookBtnMobile'] === 'center' ? 'center' : 'start' ) );
		// google facebook btn  alignment.
		$align_items_google_facebook_button        = $attr['stackGoogleFacebookButton'] === 'off' ? 'center' : $postition_google_facebook_button;
		$align_items_google_facebook_button_tablet = $attr['stackGoogleFacebookButtonTablet'] === 'off' ? 'center' : $postition_google_facebook_button_tablet;
		$align_items_google_facebook_button_mobile = $attr['stackGoogleFacebookButtonMobile'] === 'off' ? 'center' : $postition_google_facebook_button_mobile;

		// stack options.
		$apply_stack        = $attr['stackGoogleFacebookButton'] === 'off' ? 'row' : 'column';
		$apply_stack_tablet = $attr['stackGoogleFacebookButtonTablet'] === 'off' ? 'row' : 'column';
		$apply_stack_mobile = $attr['stackGoogleFacebookButtonMobile'] === 'off' ? 'row' : 'column';

		$field_icon_css = [
			'width'        => Helper::get_css_value( $attr['fieldsIconSize'], $attr['fieldsIconSizeType'] ),
			'height'       => array_key_exists( 'border-top-width', $fields_border_css ) && array_key_exists( 'border-bottom-width', $fields_border_css ) ?
								'calc( 100% - ' . $fields_border_css['border-top-width'] . ' - ' . $fields_border_css['border-bottom-width'] . ' )'
								: '',
			'top'          => array_key_exists( 'border-top-width', $fields_border_css ) ? $fields_border_css['border-top-width'] : '',
			'bottom'       => array_key_exists( 'border-bottom-width', $fields_border_css ) ? $fields_border_css['border-bottom-width'] : '',
			'left'         => array_key_exists( 'border-left-width', $fields_border_css ) ? $fields_border_css['border-left-width'] : '',
			'right'        => array_key_exists( 'border-right-width', $fields_border_css ) ? $fields_border_css['border-right-width'] : '',
			'border-width' => Helper::get_css_value( $attr['fieldsIconBorderWidth'], 'px' ),
			'border-color' => $attr['fieldsIconBorderColor'],
			'stroke'       => $attr['fieldsIconColor'],
		];

		$field_icon_css_tablet = [
			'height' => array_key_exists( 'border-top-width', $fields_border_css_tablet ) && array_key_exists( 'border-bottom-width', $fields_border_css_tablet ) ?
						'calc( 100% - ' . $fields_border_css_tablet['border-top-width'] . ' - ' . $fields_border_css_tablet['border-bottom-width'] . ' )' : '',
			'top'    => array_key_exists( 'border-top-width', $fields_border_css_tablet ) ? $fields_border_css_tablet['border-top-width'] : '',
			'bottom' => array_key_exists( 'border-bottom-width', $fields_border_css_tablet ) ? $fields_border_css_tablet['border-bottom-width'] : '',
			'left'   => array_key_exists( 'border-left-width', $fields_border_css_tablet ) ? $fields_border_css_tablet['border-left-width'] : '',
			'right'  => array_key_exists( 'border-right-width', $fields_border_css_tablet ) ? $fields_border_css_tablet['border-right-width'] : '',
		];

		$field_icon_css_mobile = [
			'height' => array_key_exists( 'border-top-width', $fields_border_css_tablet ) && array_key_exists( 'border-bottom-width', $fields_border_css_tablet ) ?
						'calc( 100% - ' . $fields_border_css_mobile['border-top-width'] . ' - ' . $fields_border_css_mobile['border-bottom-width'] . ' )' : '',
			'top'    => array_key_exists( 'border-top-width', $fields_border_css_mobile ) ? $fields_border_css_mobile['border-top-width'] : '',
			'bottom' => array_key_exists( 'border-bottom-width', $fields_border_css_mobile ) ? $fields_border_css_mobile['border-bottom-width'] : '',
			'left'   => array_key_exists( 'border-left-width', $fields_border_css_mobile ) ? $fields_border_css_mobile['border-left-width'] : '',
			'right'  => array_key_exists( 'border-right-width', $fields_border_css_mobile ) ? $fields_border_css_mobile['border-right-width'] : '',
		];

		$username_icon_input_selector = '.wp-block-spectra-pro-login.wp-block-spectra-pro-login .spectra-pro-login-form__user-login .spectra-pro-login-form-username-wrap.spectra-pro-login-form-username-wrap--have-icon input';
		$password_icon_input_selector = '.wp-block-spectra-pro-login.wp-block-spectra-pro-login .spectra-pro-login-form__user-pass .spectra-pro-login-form-pass-wrap.spectra-pro-login-form-pass-wrap--have-icon input';

		// shadow.
		$box_shadow_position_css = $attr['boxShadowPosition'];

		if ( $attr['boxShadowPosition'] === 'outset' ) {
			$box_shadow_position_css = '';
		}

		$box_shadow_position_css_hover = $attr['boxShadowPositionHover'];

		if ( $attr['boxShadowPositionHover'] === 'outset' ) {
			$box_shadow_position_css_hover = '';
		}

		$m_selectors = [];
		$t_selectors = [];

		$common_gradient_obj = [
			'gradientValue'     => $attr['gradientValue'],
			'gradientColor1'    => $attr['gradientColor1'],
			'gradientColor2'    => $attr['gradientColor2'],
			'gradientType'      => $attr['gradientType'],
			'gradientLocation1' => $attr['gradientLocation1'],
			'gradientLocation2' => $attr['gradientLocation2'],
			'gradientAngle'     => $attr['gradientAngle'],
			'selectGradient'    => $attr['selectGradient'],
		];

		// Background.
		$bg_obj_desktop           = array_merge(
			[
				'backgroundType'           => $attr['backgroundType'],
				'backgroundImage'          => $attr['backgroundImageDesktop'],
				'backgroundColor'          => $attr['backgroundColor'],
				'backgroundRepeat'         => $attr['backgroundRepeatDesktop'],
				'backgroundPosition'       => $attr['backgroundPositionDesktop'],
				'backgroundSize'           => $attr['backgroundSizeDesktop'],
				'backgroundAttachment'     => $attr['backgroundAttachmentDesktop'],
				'backgroundImageColor'     => $attr['backgroundImageColor'],
				'overlayType'              => $attr['overlayType'],
				'backgroundCustomSize'     => $attr['backgroundCustomSizeDesktop'],
				'backgroundCustomSizeType' => $attr['backgroundCustomSizeType'],
				'customPosition'           => $attr['customPosition'],
				'xPosition'                => $attr['xPositionDesktop'],
				'xPositionType'            => $attr['xPositionType'],
				'yPosition'                => $attr['yPositionDesktop'],
				'yPositionType'            => $attr['yPositionType'],
			],
			$common_gradient_obj
		);
		$container_bg_css_desktop = Helper::get_background_obj( $bg_obj_desktop );

		$bg_obj_tablet           = array_merge(
			[
				'backgroundType'           => $attr['backgroundType'],
				'backgroundImage'          => $attr['backgroundImageTablet'],
				'backgroundColor'          => $attr['backgroundColor'],
				'backgroundRepeat'         => $attr['backgroundRepeatTablet'],
				'backgroundPosition'       => $attr['backgroundPositionTablet'],
				'backgroundSize'           => $attr['backgroundSizeTablet'],
				'backgroundAttachment'     => $attr['backgroundAttachmentTablet'],
				'backgroundImageColor'     => $attr['backgroundImageColor'],
				'overlayType'              => $attr['overlayType'],
				'backgroundCustomSize'     => $attr['backgroundCustomSizeTablet'],
				'backgroundCustomSizeType' => $attr['backgroundCustomSizeType'],
				'customPosition'           => $attr['customPosition'],
				'xPosition'                => $attr['xPositionTablet'],
				'xPositionType'            => $attr['xPositionTypeTablet'],
				'yPosition'                => $attr['yPositionTablet'],
				'yPositionType'            => $attr['yPositionTypeTablet'],
			],
			$common_gradient_obj
		);
		$container_bg_css_tablet = Helper::get_background_obj( $bg_obj_tablet );

		$bg_obj_mobile           = array_merge(
			[
				'backgroundType'           => $attr['backgroundType'],
				'backgroundImage'          => $attr['backgroundImageMobile'],
				'backgroundColor'          => $attr['backgroundColor'],
				'backgroundRepeat'         => $attr['backgroundRepeatMobile'],
				'backgroundPosition'       => $attr['backgroundPositionMobile'],
				'backgroundSize'           => $attr['backgroundSizeMobile'],
				'backgroundAttachment'     => $attr['backgroundAttachmentMobile'],
				'backgroundImageColor'     => $attr['backgroundImageColor'],
				'overlayType'              => $attr['overlayType'],
				'backgroundCustomSize'     => $attr['backgroundCustomSizeMobile'],
				'backgroundCustomSizeType' => $attr['backgroundCustomSizeType'],
				'customPosition'           => $attr['customPosition'],
				'xPosition'                => $attr['xPositionMobile'],
				'xPositionType'            => $attr['xPositionTypeMobile'],
				'yPosition'                => $attr['yPositionMobile'],
				'yPositionType'            => $attr['yPositionTypeMobile'],
			],
			$common_gradient_obj
		);
		$container_bg_css_mobile = Helper::get_background_obj( $bg_obj_mobile );

		$form_label_style = [
			'font-family'     => $attr['labelFontFamily'],
			'font-style'      => $attr['labelFontStyle'],
			'text-decoration' => $attr['labelDecoration'],
			'text-transform'  => $attr['labelTransform'],
			'font-weight'     => $attr['labelFontWeight'],
			'font-size'       => Helper::get_css_value( $attr['labelFontSize'], $attr['labelFontSizeType'] ),
			'line-height'     => Helper::get_css_value(
				$attr['labelLineHeight'],
				$attr['labelLineHeightType']
			),
			'letter-spacing'  => Helper::get_css_value( $attr['labelLetterSpacing'], $attr['labelLetterSpacingType'] ),
			'color'           => $attr['labelColor'],
			'margin-top'      => Helper::get_css_value(
				$attr['labelTopMargin'],
				$attr['labelMarginUnit']
			),
			'margin-right'    => Helper::get_css_value(
				$attr['labelRightMargin'],
				$attr['labelMarginUnit']
			),
			'margin-bottom'   => Helper::get_css_value(
				$attr['labelBottomMargin'],
				$attr['labelMarginUnit']
			),
			'margin-left'     => Helper::get_css_value(
				$attr['labelLeftMargin'],
				$attr['labelMarginUnit']
			),
		];

		$form_title_style = [
			'font-family'     => $attr['headingFontFamily'],
			'font-style'      => $attr['headingFontStyle'],
			'text-decoration' => $attr['headingDecoration'],
			'text-transform'  => $attr['headingTransform'],
			'font-weight'     => $attr['headingFontWeight'],
			'font-size'       => Helper::get_css_value( $attr['headingFontSize'], $attr['headingFontSizeType'] ),
			'line-height'     => Helper::get_css_value(
				$attr['headingLineHeight'],
				$attr['headingLineHeightType']
			),
			'letter-spacing'  => Helper::get_css_value( $attr['headingLetterSpacing'], $attr['headingLetterSpacingType'] ),
			'color'           => $attr['headingColor'],
			'margin-top'      => Helper::get_css_value(
				$attr['headingTopMargin'],
				$attr['headingMarginUnit']
			),
			'margin-right'    => Helper::get_css_value(
				$attr['headingRightMargin'],
				$attr['headingMarginUnit']
			),
			'margin-bottom'   => Helper::get_css_value(
				$attr['headingBottomMargin'],
				$attr['headingMarginUnit']
			),
			'margin-left'     => Helper::get_css_value(
				$attr['headingLeftMargin'],
				$attr['headingMarginUnit']
			),
		];

		$form_input_style = array_merge(
			[
				'font-family'     => $attr['fieldsFontFamily'],
				'font-style'      => $attr['fieldsFontStyle'],
				'text-decoration' => $attr['fieldsDecoration'],
				'text-transform'  => $attr['fieldsTransform'],
				'font-weight'     => $attr['fieldsFontWeight'],
				'font-size'       => Helper::get_css_value( $attr['fieldsFontSize'], $attr['fieldsFontSizeType'] ),
				'letter-spacing'  => Helper::get_css_value( $attr['fieldsLetterSpacing'], $attr['fieldsLetterSpacingType'] ),
				'line-height'     => Helper::get_css_value( $attr['fieldsLineHeight'], $attr['fieldsLineHeightType'] ),
				'background'      => $attr['fieldsBackground'],
				'color'           => $attr['fieldsColor'],
				'padding-top'     => Helper::get_css_value( $attr['paddingFieldTop'], $attr['paddingFieldUnit'] ),
				'padding-bottom'  => Helper::get_css_value( $attr['paddingFieldBottom'], $attr['paddingFieldUnit'] ),
				'padding-left'    => Helper::get_css_value( $attr['paddingFieldLeft'], $attr['paddingFieldUnit'] ),
				'padding-right'   => Helper::get_css_value( $attr['paddingFieldRight'], $attr['paddingFieldUnit'] ),
				'text-align'      => $attr['overallAlignment'],
			],
			$fields_border_css
		);

		$form_input_style_tablet = array_merge(
			[
				'font-size'      => Helper::get_css_value( $attr['fieldsFontSizeTablet'], $attr['fieldsFontSizeType'] ),
				'letter-spacing' => Helper::get_css_value( $attr['fieldsLetterSpacingTablet'], $attr['fieldsLetterSpacingType'] ),
				'line-height'    => Helper::get_css_value( $attr['fieldsLineHeightTablet'], $attr['fieldsLineHeightType'] ),
				'padding-top'    => Helper::get_css_value( $attr['paddingFieldTopTablet'], $attr['paddingFieldUnitTablet'] ),
				'padding-bottom' => Helper::get_css_value( $attr['paddingFieldBottomTablet'], $attr['paddingFieldUnitTablet'] ),
				'padding-left'   => Helper::get_css_value( $attr['paddingFieldLeftTablet'], $attr['paddingFieldUnitTablet'] ),
				'padding-right'  => Helper::get_css_value( $attr['paddingFieldRightTablet'], $attr['paddingFieldUnitTablet'] ),
			],
			$fields_border_css_tablet
		);

		$form_input_style_mobile = array_merge(
			[
				'font-size'      => Helper::get_css_value( $attr['fieldsFontSizeMobile'], $attr['fieldsFontSizeType'] ),
				'letter-spacing' => Helper::get_css_value( $attr['fieldsLetterSpacingMobile'], $attr['fieldsLetterSpacingType'] ),
				'line-height'    => Helper::get_css_value( $attr['fieldsLineHeightMobile'], $attr['fieldsLineHeightType'] ),
				'padding-top'    => Helper::get_css_value( $attr['paddingFieldTopMobile'], $attr['paddingFieldUnitmobile'] ),
				'padding-bottom' => Helper::get_css_value( $attr['paddingFieldBottomMobile'], $attr['paddingFieldUnitmobile'] ),
				'padding-left'   => Helper::get_css_value( $attr['paddingFieldLeftMobile'], $attr['paddingFieldUnitmobile'] ),
				'padding-right'  => Helper::get_css_value( $attr['paddingFieldRightMobile'], $attr['paddingFieldUnitmobile'] ),
			],
			$fields_border_css_mobile
		);

		$selectors = [
			'.wp-block-spectra-pro-login'             => array_merge(
				[
					'width'          => Helper::get_css_value( $attr['formWidth'], $attr['formWidthType'] ),
					'padding-top'    => Helper::get_css_value( $attr['formTopPadding'], $attr['formPaddingUnit'] ),
					'padding-right'  => Helper::get_css_value( $attr['formRightPadding'], $attr['formPaddingUnit'] ),
					'padding-bottom' => Helper::get_css_value( $attr['formBottomPadding'], $attr['formPaddingUnit'] ),
					'padding-left'   => Helper::get_css_value( $attr['formLeftPadding'], $attr['formPaddingUnit'] ),
					'text-align'     => $attr['overallAlignment'],
					'box-shadow'     => Helper::get_css_value( $attr['boxShadowHOffset'], 'px' ) .
						' ' .
						Helper::get_css_value( $attr['boxShadowVOffset'], 'px' ) .
						' ' .
						Helper::get_css_value( $attr['boxShadowBlur'], 'px' ) .
						' ' .
						Helper::get_css_value( $attr['boxShadowSpread'], 'px' ) .
						' ' .
						$attr['boxShadowColor'] .
						' ' .
						$box_shadow_position_css,
				],
				$form_border_css,
				$container_bg_css_desktop
			),
			'.wp-block-spectra-pro-login:hover'       => [
				'border-color' => $attr['formBorderHColor'],
			],
			' .spectra-pro-login-form__field-error-message' => [
				'text-align' => $attr['overallAlignment'],
			],
			'.wp-block-spectra-pro-login .spectra-pro-login-form__user-login' => [
				'margin-bottom' => Helper::get_css_value( $attr['formRowsGapSpace'], $attr['formRowsGapSpaceUnit'] ),
			],
			'.wp-block-spectra-pro-login .wp-block-spectra-pro-login__logged-in-message' => [
				'font-family'     => $attr['labelFontFamily'],
				'font-style'      => $attr['labelFontStyle'],
				'text-decoration' => $attr['labelDecoration'],
				'text-transform'  => $attr['labelTransform'],
				'font-weight'     => $attr['labelFontWeight'],
				'font-size'       => Helper::get_css_value( $attr['labelFontSize'], $attr['labelFontSizeType'] ),
				'line-height'     => Helper::get_css_value(
					$attr['labelLineHeight'],
					$attr['labelLineHeightType']
				),
				'letter-spacing'  => Helper::get_css_value( $attr['labelLetterSpacing'], $attr['labelLetterSpacingType'] ),
				'color'           => $attr['labelColor'],
			],
			'.wp-block-spectra-pro-login .spectra-pro-login-form__heading' => $form_title_style,
			'.wp-block-spectra-pro-login .spectra-pro-login-form__heading:hover' => [
				'color' => $attr['headingHoverColor'],
			],
			'.wp-block-spectra-pro-login .spectra-pro-login-form__user-login label' => $form_label_style,
			'.wp-block-spectra-pro-login .spectra-pro-login-form__user-login label:hover' => [
				'color' => $attr['labelHoverColor'],
			],
			'.wp-block-spectra-pro-login .spectra-pro-login-form__user-login input' => $form_input_style,
			'.wp-block-spectra-pro-login .spectra-pro-login-form__user-login input:hover' => [
				'border-color' => $attr['fieldsBorderHColor'],
				'background'   => $attr['fieldsBackgroundHover'],
			],
			'.wp-block-spectra-pro-login .spectra-pro-login-form__user-login input:focus' => [
				'background' => $attr['fieldsBackgroundActive'],
			],
			'.wp-block-spectra-pro-login .spectra-pro-login-form__user-login input::placeholder' => [
				'color' => $attr['placeholderColor'],
			],
			'.wp-block-spectra-pro-login .spectra-pro-login-form__user-login input:hover::placeholder' => [
				'color' => $attr['placeholderColorHover'],
			],
			'.wp-block-spectra-pro-login .spectra-pro-login-form__user-login input:focus::placeholder' => [
				'color' => $attr['placeholderColorActive'],
			],
			'.wp-block-spectra-pro-login .spectra-pro-login-form__user-pass input::placeholder' => [
				'color' => $attr['placeholderColor'],
			],
			'.wp-block-spectra-pro-login .spectra-pro-login-form__user-pass input:hover::placeholder' => [
				'color' => $attr['placeholderColorHover'],
			],
			'.wp-block-spectra-pro-login .spectra-pro-login-form__user-pass input:focus::placeholder' => [
				'color' => $attr['placeholderColorActive'],
			],
			'.wp-block-spectra-pro-login .spectra-pro-login-form__recaptcha' => [
				'margin-bottom' => Helper::get_css_value( $attr['formRowsGapSpace'], $attr['formRowsGapSpaceUnit'] ),
			],
			'.wp-block-spectra-pro-login .spectra-pro-login-form__turnstile' => [
				'margin-bottom' => Helper::get_css_value( $attr['formRowsGapSpace'], $attr['formRowsGapSpaceUnit'] ),
			],
			'.wp-block-spectra-pro-login .spectra-pro-login-form__user-pass' => [
				'margin-bottom' => Helper::get_css_value( $attr['formRowsGapSpace'], $attr['formRowsGapSpaceUnit'] ),
			],
			'.wp-block-spectra-pro-login .spectra-pro-login-form__user-pass button' => [
				'color'        => $attr['eyeIconColor'],
				'margin-right' => array_key_exists( 'border-right-width', $fields_border_css ) && ( ! $is_rtl ) ? 'calc( ' . $fields_border_css['border-right-width'] . ' + 5px )' : '',
				'margin-left'  => array_key_exists( 'border-left-width', $fields_border_css ) && $is_rtl ? 'calc( ' . $fields_border_css['border-left-width'] . ' + 5px )' : '',
			],
			'.wp-block-spectra-pro-login .spectra-pro-login-form__user-pass button span' => [
				'font-size' => Helper::get_css_value( $attr['eyeIconSize'], $attr['eyeIconSizeType'] ),
			],
			'.wp-block-spectra-pro-login .spectra-pro-login-form__user-pass label' => $form_label_style,
			'.wp-block-spectra-pro-login .spectra-pro-login-form__user-pass label:hover' => [
				'color' => $attr['labelHoverColor'],
			],
			'.wp-block-spectra-pro-login .spectra-pro-login-form__user-pass input' => $form_input_style,
			'.wp-block-spectra-pro-login .spectra-pro-login-form__user-pass input:hover' => [
				'color'        => $attr['labelHoverColor'],
				'border-color' => $attr['fieldsBorderHColor'],
				'background'   => $attr['fieldsBackgroundHover'],
			],
			'.wp-block-spectra-pro-login .spectra-pro-login-form__user-pass input:focus' => [
				'background' => $attr['fieldsBackgroundActive'],
			],
			'.wp-block-spectra-pro-login .spectra-pro-login-form__forgetmenot' => [
				'margin-bottom' => Helper::get_css_value( $attr['formRowsGapSpace'], $attr['formRowsGapSpaceUnit'] ),
			],
			'.wp-block-spectra-pro-login .spectra-pro-login-form-forgot-password' => [
				'margin-top'    => Helper::get_css_value( $attr['labelTopMargin'], $attr['labelMarginUnit'] ),
				'margin-right'  => Helper::get_css_value( $attr['labelRightMargin'], $attr['labelMarginUnit'] ),
				'margin-bottom' => Helper::get_css_value( $attr['labelBottomMargin'], $attr['labelMarginUnit'] ),
				// Left margin not required.
			],
			'.wp-block-spectra-pro-login .spectra-pro-login-form-forgot-password a' => array_merge(
				$form_label_style,
				[
					'margin' => 'unset',
					'color'  => $attr['linkColor'],
				]
			),
			'.wp-block-spectra-pro-login .spectra-pro-login-form-forgot-password a:hover' => [
				'color' => $attr['linkHColor'],
			],

			$username_icon_input_selector             => [
				'padding-left' => Helper::get_css_value( $attr['paddingFieldLeft'], $attr['paddingFieldUnit'] ),
			],

			$password_icon_input_selector             => [
				'padding-left' => Helper::get_css_value( $attr['paddingFieldLeft'], $attr['paddingFieldUnit'] ),
			],

			// Field icon - Username.
			'.wp-block-spectra-pro-login .spectra-pro-login-form .spectra-pro-login-form-username-wrap--have-icon svg' => array_merge(
				$field_icon_css
			),

			// Field icon - Password.
			'.wp-block-spectra-pro-login .spectra-pro-login-form .spectra-pro-login-form__user-pass .spectra-pro-login-form-pass-wrap--have-icon svg' => array_merge(
				$field_icon_css
			),

			'.wp-block-spectra-pro-login .spectra-pro-login-form-rememberme label' => array_merge(
				$form_label_style,
				[
					'margin' => 'unset',
				]
			),
			'.wp-block-spectra-pro-login .spectra-pro-login-form-rememberme label:hover' => [
				'color' => $attr['labelHoverColor'],
			],
			'.wp-block-spectra-pro-login .spectra-pro-login-form-rememberme' => [
				'margin-top'    => Helper::get_css_value( $attr['labelTopMargin'], $attr['labelMarginUnit'] ),
				// Right margin not required.
				'margin-bottom' => Helper::get_css_value( $attr['labelBottomMargin'], $attr['labelMarginUnit'] ),
				'margin-left'   => Helper::get_css_value( $attr['labelLeftMargin'], $attr['labelMarginUnit'] ),
			],
			// checkbox.
			' .spectra-pro-login-form-rememberme .spectra-pro-login-form-rememberme__checkmark' => [
				'width'         => Helper::get_css_value( $attr['checkboxSize'], 'px' ),
				'height'        => Helper::get_css_value( $attr['checkboxSize'], 'px' ),
				'background'    => $attr['checkboxBackgroundColor'],
				'border-width'  => Helper::get_css_value( $attr['checkboxBorderWidth'], 'px' ),
				'border-radius' => Helper::get_css_value( $attr['checkboxBorderRadius'], 'px' ),
				'border-color'  => $attr['checkboxBorderColor'],
			],
			' .spectra-pro-login-form-rememberme .spectra-pro-login-form-rememberme__checkmark:after' => [
				'font-size' => Helper::get_css_value( strval( floatval( $attr['checkboxSize'] ) / 2 ), 'px' ),
				'color'     => $attr['checkboxColor'],
			],
			// If the user clicks on the checkbox, light it up with some box shadow to portray some interaction!
			' .spectra-pro-login-form-rememberme input[type="checkbox"]:focus + .spectra-pro-login-form-rememberme__checkmark' => [
				'box-shadow' => $attr['checkboxGlowEnable'] && $attr['checkboxGlowColor'] ? '0 0 0 1px ' . $attr['checkboxGlowColor'] : '',
			],
			'.wp-block-spectra-pro-login .spectra-pro-login-form-register:hover' => [
				'color' => $attr['linkHColor'],
			],
			'.wp-block-spectra-pro-login .spectra-pro-login-form__submit' => [
				'justify-content' => $attr['alignLoginBtn'],
				'margin-bottom'   => Helper::get_css_value( $attr['formRowsGapSpace'], $attr['formRowsGapSpaceUnit'] ),
			],
			'.wp-block-spectra-pro-login .spectra-pro-login-form-submit-button' => array_merge(
				[
					'font-family'     => $attr['loginFontFamily'],
					'font-style'      => $attr['loginFontStyle'],
					'text-decoration' => $attr['loginDecoration'],
					'text-transform'  => $attr['loginTransform'],
					'font-weight'     => $attr['loginFontWeight'],
					'font-size'       => Helper::get_css_value( $attr['loginFontSize'], $attr['loginFontSizeType'] ),
					'letter-spacing'  => Helper::get_css_value( $attr['loginLetterSpacing'], $attr['loginLetterSpacingType'] ),
					'line-height'     => Helper::get_css_value( $attr['loginLineHeight'], $attr['loginLineHeightType'] ),
					'background'      => $attr['loginBackground'],
					'color'           => $attr['loginColor'],
					'padding-top'     => Helper::get_css_value( $attr['loginTopPadding'], $attr['loginPaddingUnit'] ),
					'padding-right'   => Helper::get_css_value( $attr['loginRightPadding'], $attr['loginPaddingUnit'] ),
					'padding-bottom'  => Helper::get_css_value( $attr['loginBottomPadding'], $attr['loginPaddingUnit'] ),
					'padding-left'    => Helper::get_css_value( $attr['loginLeftPadding'], $attr['loginPaddingUnit'] ),
					'column-gap'      => Helper::get_css_value( $attr['ctaIconSpace'], $attr['ctaIconSpaceType'] ),

				],
				$full_width_login_btn,
				$login_border_css
			),
			'.wp-block-spectra-pro-login .suredash-reset-password-submit' => array_merge(
				[
					'font-family'     => $attr['loginFontFamily'],
					'font-style'      => $attr['loginFontStyle'],
					'text-decoration' => $attr['loginDecoration'],
					'text-transform'  => $attr['loginTransform'],
					'font-weight'     => $attr['loginFontWeight'],
					'font-size'       => Helper::get_css_value( $attr['loginFontSize'], $attr['loginFontSizeType'] ),
					'letter-spacing'  => Helper::get_css_value( $attr['loginLetterSpacing'], $attr['loginLetterSpacingType'] ),
					'line-height'     => Helper::get_css_value( $attr['loginLineHeight'], $attr['loginLineHeightType'] ),
					'background'      => $attr['loginBackground'],
					'color'           => $attr['loginColor'],
					'padding-top'     => Helper::get_css_value( $attr['loginTopPadding'], $attr['loginPaddingUnit'] ),
					'padding-right'   => Helper::get_css_value( $attr['loginRightPadding'], $attr['loginPaddingUnit'] ),
					'padding-bottom'  => Helper::get_css_value( $attr['loginBottomPadding'], $attr['loginPaddingUnit'] ),
					'padding-left'    => Helper::get_css_value( $attr['loginLeftPadding'], $attr['loginPaddingUnit'] ),
					'column-gap'      => Helper::get_css_value( $attr['ctaIconSpace'], $attr['ctaIconSpaceType'] ),

				],
				$full_width_login_btn,
				$login_border_css
			),
			'.wp-block-spectra-pro-login .spectra-pro-login-form-submit-button:hover' => [
				'border-color' => $attr['loginBorderHColor'],
				'background'   => $attr['loginHBackground'],
				'color'        => $attr['loginHColor'],
			],
			'.wp-block-spectra-pro-login .suredash-reset-password-submit:hover' => [
				'border-color' => $attr['loginBorderHColor'],
				'background'   => $attr['loginHBackground'],
				'color'        => $attr['loginHColor'],
			],
			'.wp-block-spectra-pro-login .spectra-pro-login-form-submit-button:hover svg' => [
				'stroke' => $attr['loginHColor'],
			],
			'.wp-block-spectra-pro-login .suredash-reset-password-submit:hover svg' => [
				'stroke' => $attr['loginHColor'],
			],

			'.wp-block-spectra-pro-login .spectra-pro-login-form__social-google' => array_merge(
				[
					'width'           => $attr['googleSize'] === 'full' ? '100%' : '',
					'font-family'     => $attr['googleFontFamily'],
					'font-style'      => $attr['googleFontStyle'],
					'text-decoration' => $attr['googleDecoration'],
					'text-transform'  => $attr['googleTransform'],
					'font-weight'     => $attr['googleFontWeight'],
					'font-size'       => Helper::get_css_value( $attr['googleFontSize'], $attr['googleFontSizeType'] ),
					'letter-spacing'  => Helper::get_css_value( $attr['googleLetterSpacing'], $attr['googleLetterSpacingType'] ),
					'line-height'     => Helper::get_css_value( $attr['googleLineHeight'], $attr['googleLineHeightType'] ),
					'background'      => $attr['googleBackground'],
					'color'           => $attr['googleColor'],
					'padding-top'     => Helper::get_css_value( $attr['googleTopPadding'], $attr['googlePaddingUnit'] ),
					'padding-right'   => Helper::get_css_value( $attr['googleRightPadding'], $attr['googlePaddingUnit'] ),
					'padding-bottom'  => Helper::get_css_value( $attr['googleBottomPadding'], $attr['googlePaddingUnit'] ),
					'padding-left'    => Helper::get_css_value( $attr['googleLeftPadding'], $attr['googlePaddingUnit'] ),
				],
				$google_border_css
			),
			'.wp-block-spectra-pro-login .spectra-pro-login-form__social-google:hover' => [
				'border-color' => $attr['googleBorderHColor'],
				'background'   => $attr['googleHBackground'],
				'color'        => $attr['googleHColor'],
			],
			'.wp-block-spectra-pro-login .spectra-pro-login-form__social-facebook' => array_merge(
				[
					'width'           => $attr['facebookSize'] === 'full' ? '100%' : '',
					'font-family'     => $attr['facebookFontFamily'],
					'font-style'      => $attr['facebookFontStyle'],
					'text-decoration' => $attr['facebookDecoration'],
					'text-transform'  => $attr['facebookTransform'],
					'font-weight'     => $attr['facebookFontWeight'],
					'font-size'       => Helper::get_css_value( $attr['facebookFontSize'], $attr['facebookFontSizeType'] ),
					'letter-spacing'  => Helper::get_css_value( $attr['facebookLetterSpacing'], $attr['facebookLetterSpacingType'] ),
					'line-height'     => Helper::get_css_value( $attr['facebookLineHeight'], $attr['facebookLineHeightType'] ),
					'background'      => $attr['facebookBackground'],
					'color'           => $attr['facebookColor'],
					'padding-top'     => Helper::get_css_value( $attr['facebookTopPadding'], $attr['facebookPaddingUnit'] ),
					'padding-right'   => Helper::get_css_value( $attr['facebookRightPadding'], $attr['facebookPaddingUnit'] ),
					'padding-bottom'  => Helper::get_css_value( $attr['facebookBottomPadding'], $attr['facebookPaddingUnit'] ),
					'padding-left'    => Helper::get_css_value( $attr['facebookLeftPadding'], $attr['facebookPaddingUnit'] ),
				],
				$facebook_border_css
			),
			'.wp-block-spectra-pro-login .spectra-pro-login-form__social-facebook:hover' => [
				'border-color' => $attr['facebookBorderHColor'],
				'background'   => $attr['facebookHBackground'],
				'color'        => $attr['facebookHColor'],
			],
			'.wp-block-spectra-pro-login .spectra-pro-login-form-status .spectra-pro-login-form-status__success' => [
				'border-left-color' => $attr['successMessageBorderColor'],
				'background-color'  => $attr['successMessageBackground'],
				'color'             => $attr['successMessageColor'],
			],
			'.wp-block-spectra-pro-login .spectra-pro-login-form-status .spectra-pro-login-form-status__error' => [
				'border-left-color' => $attr['errorMessageBorderColor'],
				'background-color'  => $attr['errorMessageBackground'],
				'color'             => $attr['errorMessageColor'],
			],

			// google and facebook alignment.
			' .spectra-pro-login-form__social'        => [
				'justify-content' => $postition_google_facebook_button,
				'align-items'     => $align_items_google_facebook_button,
				'flex-direction'  => $apply_stack,
				'gap'             => $attr['gapSocialLogin'] . 'px',
				'margin-bottom'   => Helper::get_css_value( $attr['formRowsGapSpace'], $attr['formRowsGapSpaceUnit'] ),
			],

			// Info Link.
			' .wp-block-spectra-pro-login-info'       => [
				'color'           => $attr['labelColor'],
				'font-family'     => $attr['labelFontFamily'],
				'font-style'      => $attr['labelFontStyle'],
				'text-decoration' => $attr['labelDecoration'],
				'text-transform'  => $attr['labelTransform'],
				'font-weight'     => $attr['labelFontWeight'],
				'font-size'       => Helper::get_css_value( $attr['labelFontSize'], $attr['labelFontSizeType'] ),
				'letter-spacing'  => Helper::get_css_value( $attr['labelLetterSpacing'], $attr['labelLetterSpacingType'] ),
				'margin-top'      => Helper::get_css_value( $attr['labelTopMargin'], $attr['labelMarginUnit'] ),
				'margin-right'    => Helper::get_css_value( $attr['labelRightMargin'], $attr['labelMarginUnit'] ),
				'margin-bottom'   => Helper::get_css_value( $attr['labelBottomMargin'], $attr['labelMarginUnit'] ),
				'margin-left'     => Helper::get_css_value( $attr['labelLeftMargin'], $attr['labelMarginUnit'] ),
			],
			' .wp-block-spectra-pro-login-info a'     => [
				'color' => $attr['linkColor'],
			],
			' .wp-block-spectra-pro-login__logged-in-message a' => [
				'color' => $attr['linkColor'],
			],
			' .wp-block-spectra-pro-login-info:hover' => [
				'color' => $attr['labelHoverColor'],
			],
			' .wp-block-spectra-pro-login__logged-in-message a:hover' => [
				'color' => $attr['linkHColor'],
			],
		];

		// If hover blur or hover color are set, show the hover shadow.
		if ( ( $attr['boxShadowBlurHover'] !== '' ) && ( $attr['boxShadowBlurHover'] !== null ) || $attr['boxShadowColorHover'] !== '' ) {
			$selectors['.wp-block-spectra-pro-login:hover']['box-shadow'] = Helper::get_css_value( $attr['boxShadowHOffsetHover'], 'px' ) . ' ' . Helper::get_css_value( $attr['boxShadowVOffsetHover'], 'px' ) . ' ' . Helper::get_css_value( $attr['boxShadowBlurHover'], 'px' ) . ' ' . Helper::get_css_value( $attr['boxShadowSpreadHover'], 'px' ) . ' ' . $attr['boxShadowColorHover'] . ' ' . $box_shadow_position_css_hover;
		}

		// tablet.
		$t_selectors['.wp-block-spectra-pro-login'] = array_merge(
			[
				'width'          => Helper::get_css_value( $attr['formWidthTablet'], $attr['formWidthTypeTablet'] ),
				'padding-top'    => Helper::get_css_value( $attr['formTopPaddingTablet'], $attr['formPaddingUnitTablet'] ),
				'padding-right'  => Helper::get_css_value( $attr['formRightPaddingTablet'], $attr['formPaddingUnitTablet'] ),
				'padding-bottom' => Helper::get_css_value( $attr['formBottomPaddingTablet'], $attr['formPaddingUnitTablet'] ),
				'padding-left'   => Helper::get_css_value( $attr['formLeftPaddingTablet'], $attr['formPaddingUnitTablet'] ),
			],
			$container_bg_css_tablet,
			$form_border_css_tablet
		);

		$t_selectors[' .wp-block-spectra-pro-login-info']                                     = [
			'font-size'      => Helper::get_css_value( $attr['labelFontSizeTablet'], $attr['labelFontSizeType'] ),
			'letter-spacing' => Helper::get_css_value( $attr['labelLetterSpacingTablet'], $attr['labelLetterSpacingType'] ),
			'line-height'    => Helper::get_css_value( $attr['labelLineHeightTablet'], $attr['labelLineHeightType'] ),
			'margin-top'     => Helper::get_css_value( $attr['labelTopMarginTablet'], $attr['labelMarginUnitTablet'] ),
			'margin-right'   => Helper::get_css_value( $attr['labelRightMarginTablet'], $attr['labelMarginUnitTablet'] ),
			'margin-bottom'  => Helper::get_css_value( $attr['labelBottomMarginTablet'], $attr['labelMarginUnitTablet'] ),
			'margin-left'    => Helper::get_css_value( $attr['labelLeftMarginTablet'], $attr['labelMarginUnitTablet'] ),
		];
		$t_selectors['.wp-block-spectra-pro-login .spectra-pro-login-form__user-login label'] = [
			'font-size'      => Helper::get_css_value(
				$attr['labelFontSizeTablet'],
				$attr['labelFontSizeType']
			),
			'letter-spacing' => Helper::get_css_value( $attr['labelLetterSpacingTablet'], $attr['labelLetterSpacingType'] ),
			'line-height'    => Helper::get_css_value(
				$attr['labelLineHeightTablet'],
				$attr['labelLineHeightType']
			),
			'margin-top'     => Helper::get_css_value(
				$attr['labelTopMarginTablet'],
				$attr['labelMarginUnitTablet']
			),
			'margin-right'   => Helper::get_css_value(
				$attr['labelRightMarginTablet'],
				$attr['labelMarginUnitTablet']
			),
			'margin-bottom'  => Helper::get_css_value(
				$attr['labelBottomMarginTablet'],
				$attr['labelMarginUnitTablet']
			),
			'margin-left'    => Helper::get_css_value(
				$attr['labelLeftMarginTablet'],
				$attr['labelMarginUnitTablet']
			),
		];

		$t_selectors['.wp-block-spectra-pro-login .spectra-pro-login-form__user-pass label'] = [
			'font-size'      => Helper::get_css_value(
				$attr['labelFontSizeTablet'],
				$attr['labelFontSizeType']
			),
			'letter-spacing' => Helper::get_css_value( $attr['labelLetterSpacingTablet'], $attr['labelLetterSpacingType'] ),
			'line-height'    => Helper::get_css_value(
				$attr['labelLineHeightTablet'],
				$attr['labelLineHeightType']
			),
			'margin-top'     => Helper::get_css_value(
				$attr['labelTopMarginTablet'],
				$attr['labelMarginUnitTablet']
			),
			'margin-right'   => Helper::get_css_value(
				$attr['labelRightMarginTablet'],
				$attr['labelMarginUnitTablet']
			),
			'margin-bottom'  => Helper::get_css_value(
				$attr['labelBottomMarginTablet'],
				$attr['labelMarginUnitTablet']
			),
			'margin-left'    => Helper::get_css_value(
				$attr['labelLeftMarginTablet'],
				$attr['labelMarginUnitTablet']
			),
		];

		$t_selectors['.wp-block-spectra-pro-login .spectra-pro-login-form__heading'] = [
			'font-size'      => Helper::get_css_value(
				$attr['headingFontSizeTablet'],
				$attr['headingFontSizeType']
			),
			'letter-spacing' => Helper::get_css_value( $attr['headingLetterSpacingTablet'], $attr['headingLetterSpacingType'] ),
			'line-height'    => Helper::get_css_value(
				$attr['headingLineHeightTablet'],
				$attr['headingLineHeightType']
			),
			'margin-top'     => Helper::get_css_value(
				$attr['headingTopMarginTablet'],
				$attr['headingMarginUnitTablet']
			),
			'margin-right'   => Helper::get_css_value(
				$attr['headingRightMarginTablet'],
				$attr['headingMarginUnitTablet']
			),
			'margin-bottom'  => Helper::get_css_value(
				$attr['headingBottomMarginTablet'],
				$attr['headingMarginUnitTablet']
			),
			'margin-left'    => Helper::get_css_value(
				$attr['headingLeftMarginTablet'],
				$attr['headingMarginUnitTablet']
			),
		];

		$t_selectors['.wp-block-spectra-pro-login .spectra-pro-login-form__user-login input'] = $form_input_style_tablet;
		$t_selectors['.wp-block-spectra-pro-login .spectra-pro-login-form__user-pass input']  = $form_input_style_tablet;

		$t_selectors[ $username_icon_input_selector ] = [
			'padding-left' => Helper::get_css_value( $attr['paddingFieldLeftTablet'], $attr['paddingFieldUnitTablet'] ),
		];

		$t_selectors[ $password_icon_input_selector ] = [
			'padding-left' => Helper::get_css_value( $attr['paddingFieldLeftTablet'], $attr['paddingFieldUnitTablet'] ),
		];

		// Field Icon - Username.
		$t_selectors['.wp-block-spectra-pro-login .spectra-pro-login-form-username-wrap--have-icon svg'] = array_merge(
			$field_icon_css_tablet
		);

		// Field Icon - Password.
		$t_selectors['.wp-block-spectra-pro-login .spectra-pro-login-form__user-pass .spectra-pro-login-form-pass-wrap--have-icon svg'] = array_merge(
			$field_icon_css_tablet
		);

		$t_selectors['.wp-block-spectra-pro-login .spectra-pro-login-form__user-pass button'] = [
			'margin-right' => array_key_exists( 'border-right-width', $fields_border_css_tablet ) && ( ! $is_rtl ) ? $fields_border_css_tablet['border-right-width'] : '',
			'margin-left'  => array_key_exists( 'border-left-width', $fields_border_css_tablet ) && $is_rtl ? $fields_border_css_tablet['border-left-width'] : '',
		];

		$t_selectors['.wp-block-spectra-pro-login .spectra-pro-login-form__forgetmenot .spectra-pro-login-form-rememberme'] = [
			'margin-top'    => Helper::get_css_value(
				$attr['labelTopMarginTablet'],
				$attr['labelMarginUnitTablet']
			),
			// Right margin not required.
			'margin-bottom' => Helper::get_css_value(
				$attr['labelBottomMarginTablet'],
				$attr['labelMarginUnitTablet']
			),
			'margin-left'   => Helper::get_css_value(
				$attr['labelLeftMarginTablet'],
				$attr['labelMarginUnitTablet']
			),
		];

		$t_selectors['.wp-block-spectra-pro-login .spectra-pro-login-form__forgetmenot .spectra-pro-login-form-rememberme label'] = [
			'font-size'      => Helper::get_css_value(
				$attr['labelFontSizeTablet'],
				$attr['labelFontSizeType']
			),
			'letter-spacing' => Helper::get_css_value( $attr['labelLetterSpacingTablet'], $attr['labelLetterSpacingType'] ),
			'line-height'    => Helper::get_css_value(
				$attr['labelLineHeightTablet'],
				$attr['labelLineHeightType']
			),
		];

		$t_selectors['.wp-block-spectra-pro-login .spectra-pro-login-form__forgetmenot .spectra-pro-login-form-forgot-password'] = [
			'margin-top'    => Helper::get_css_value(
				$attr['labelTopMarginTablet'],
				$attr['labelMarginUnitTablet']
			),
			'margin-right'  => Helper::get_css_value(
				$attr['labelRightMarginTablet'],
				$attr['labelMarginUnitTablet']
			),
			'margin-bottom' => Helper::get_css_value(
				$attr['labelBottomMarginTablet'],
				$attr['labelMarginUnitTablet']
			),
			// Margin left not required.
		];

		$t_selectors['.wp-block-spectra-pro-login .spectra-pro-login-form__forgetmenot .spectra-pro-login-form-forgot-password a'] = [
			'font-size'      => Helper::get_css_value(
				$attr['labelFontSizeTablet'],
				$attr['labelFontSizeType']
			),
			'letter-spacing' => Helper::get_css_value( $attr['labelLetterSpacingTablet'], $attr['labelLetterSpacingType'] ),
			'line-height'    => Helper::get_css_value(
				$attr['labelLineHeightTablet'],
				$attr['labelLineHeightType']
			),
		];
		$t_selectors['.wp-block-spectra-pro-login .spectra-pro-login-form__submit'] = [
			'justify-content' => $attr['alignLoginBtnTablet'],
		];

		$t_selectors['.wp-block-spectra-pro-login .spectra-pro-login-form-submit-button'] = array_merge(
			[
				'font-size'      => Helper::get_css_value(
					$attr['loginFontSizeTablet'],
					$attr['loginFontSizeType']
				),
				'letter-spacing' => Helper::get_css_value( $attr['loginLetterSpacingTablet'], $attr['loginLetterSpacingType'] ),
				'line-height'    => Helper::get_css_value(
					$attr['loginLineHeightTablet'],
					$attr['loginLineHeightType']
				),
				'padding-top'    => Helper::get_css_value(
					$attr['loginTopPaddingTablet'],
					$attr['loginPaddingUnitTablet']
				),
				'padding-right'  => Helper::get_css_value(
					$attr['loginRightPaddingTablet'],
					$attr['loginPaddingUnitTablet']
				),
				'padding-bottom' => Helper::get_css_value(
					$attr['loginBottomPaddingTablet'],
					$attr['loginPaddingUnitTablet']
				),
				'padding-left'   => Helper::get_css_value(
					$attr['loginLeftPaddingTablet'],
					$attr['loginPaddingUnitTablet']
				),
				'column-gap'     => Helper::get_css_value( $attr['ctaIconSpaceTablet'], $attr['ctaIconSpaceType'] ),
			],
			$full_width_login_btn_tablet,
			$login_border_css_tablet
		);
		$t_selectors['.wp-block-spectra-pro-login .suredash-reset-password-submit']       = array_merge(
			[
				'font-size'      => Helper::get_css_value(
					$attr['loginFontSizeTablet'],
					$attr['loginFontSizeType']
				),
				'letter-spacing' => Helper::get_css_value( $attr['loginLetterSpacingTablet'], $attr['loginLetterSpacingType'] ),
				'line-height'    => Helper::get_css_value(
					$attr['loginLineHeightTablet'],
					$attr['loginLineHeightType']
				),
				'padding-top'    => Helper::get_css_value(
					$attr['loginTopPaddingTablet'],
					$attr['loginPaddingUnitTablet']
				),
				'padding-right'  => Helper::get_css_value(
					$attr['loginRightPaddingTablet'],
					$attr['loginPaddingUnitTablet']
				),
				'padding-bottom' => Helper::get_css_value(
					$attr['loginBottomPaddingTablet'],
					$attr['loginPaddingUnitTablet']
				),
				'padding-left'   => Helper::get_css_value(
					$attr['loginLeftPaddingTablet'],
					$attr['loginPaddingUnitTablet']
				),
				'column-gap'     => Helper::get_css_value( $attr['ctaIconSpaceTablet'], $attr['ctaIconSpaceType'] ),
			],
			$full_width_login_btn_tablet,
			$login_border_css_tablet
		);

		$t_selectors['.wp-block-spectra-pro-login .spectra-pro-login-form__social-facebook'] = array_merge(
			[
				'font-size'      => Helper::get_css_value(
					$attr['facebookFontSizeTablet'],
					$attr['facebookFontSizeType']
				),
				'letter-spacing' => Helper::get_css_value( $attr['facebookLetterSpacingTablet'], $attr['facebookLetterSpacingType'] ),
				'line-height'    => Helper::get_css_value(
					$attr['facebookLineHeightTablet'],
					$attr['facebookLineHeightType']
				),
				'padding-top'    => Helper::get_css_value(
					$attr['facebookTopPaddingTablet'],
					$attr['facebookPaddingUnitTablet']
				),
				'padding-right'  => Helper::get_css_value(
					$attr['facebookRightPaddingTablet'],
					$attr['facebookPaddingUnitTablet']
				),
				'padding-bottom' => Helper::get_css_value(
					$attr['facebookBottomPaddingTablet'],
					$attr['facebookPaddingUnitTablet']
				),
				'padding-left'   => Helper::get_css_value(
					$attr['facebookLeftPaddingTablet'],
					$attr['facebookPaddingUnitTablet']
				),
			],
			$google_border_css_tablet
		);
		$t_selectors['.wp-block-spectra-pro-login .spectra-pro-login-form__social-google']   = array_merge(
			[
				'font-size'      => Helper::get_css_value(
					$attr['googleFontSizeTablet'],
					$attr['googleFontSizeType']
				),
				'letter-spacing' => Helper::get_css_value( $attr['googleLetterSpacingTablet'], $attr['googleLetterSpacingType'] ),
				'line-height'    => Helper::get_css_value(
					$attr['googleLineHeightTablet'],
					$attr['googleLineHeightType']
				),
				'padding-top'    => Helper::get_css_value(
					$attr['googleTopPaddingTablet'],
					$attr['googlePaddingUnitTablet']
				),
				'padding-right'  => Helper::get_css_value(
					$attr['googleRightPaddingTablet'],
					$attr['googlePaddingUnitTablet']
				),
				'padding-bottom' => Helper::get_css_value(
					$attr['googleBottomPaddingTablet'],
					$attr['googlePaddingUnitTablet']
				),
				'padding-left'   => Helper::get_css_value(
					$attr['googleLeftPaddingTablet'],
					$attr['googlePaddingUnitTablet']
				),
			],
			$google_border_css_tablet
		);
		$t_selectors[' .spectra-pro-login-form__social']                                     = [
			'justify-content' => $postition_google_facebook_button_tablet,
			'align-items'     => $align_items_google_facebook_button_tablet,
			'flex-direction'  => $apply_stack_tablet,
			'gap'             => $attr['gapSocialLoginTablet'] . 'px',
		];

		// mobile.
		$m_selectors['.wp-block-spectra-pro-login'] = array_merge(
			[
				'width'          => Helper::get_css_value( $attr['formWidthMobile'], $attr['formWidthTypeMobile'] ),
				'padding-top'    => Helper::get_css_value(
					$attr['formTopPaddingMobile'],
					$attr['formPaddingUnitMobile']
				),
				'padding-right'  => Helper::get_css_value(
					$attr['formRightPaddingMobile'],
					$attr['formPaddingUnitMobile']
				),
				'padding-bottom' => Helper::get_css_value(
					$attr['formBottomPaddingMobile'],
					$attr['formPaddingUnitMobile']
				),
				'padding-left'   => Helper::get_css_value(
					$attr['formLeftPaddingMobile'],
					$attr['formPaddingUnitMobile']
				),
			],
			$container_bg_css_mobile,
			$form_border_css_mobile
		);

		$m_selectors[' .wp-block-spectra-pro-login-info']                                     = [
			'font-size'      => Helper::get_css_value( $attr['labelFontSizeMobile'], $attr['labelFontSizeType'] ),
			'letter-spacing' => Helper::get_css_value( $attr['labelLetterSpacingMobile'], $attr['labelLetterSpacingType'] ),
			'line-height'    => Helper::get_css_value( $attr['labelLineHeightMobile'], $attr['labelLineHeightType'] ),
			'margin-top'     => Helper::get_css_value( $attr['labelTopMarginMobile'], $attr['labelMarginUnitMobile'] ),
			'margin-right'   => Helper::get_css_value( $attr['labelRightMarginMobile'], $attr['labelMarginUnitMobile'] ),
			'margin-bottom'  => Helper::get_css_value( $attr['labelBottomMarginMobile'], $attr['labelMarginUnitMobile'] ),
			'margin-left'    => Helper::get_css_value( $attr['labelLeftMarginMobile'], $attr['labelMarginUnitMobile'] ),
		];
		$m_selectors['.wp-block-spectra-pro-login .spectra-pro-login-form__user-login label'] = [
			'font-size'      => Helper::get_css_value(
				$attr['labelFontSizeMobile'],
				$attr['labelFontSizeType']
			),
			'letter-spacing' => Helper::get_css_value( $attr['labelLetterSpacingMobile'], $attr['labelLetterSpacingType'] ),
			'line-height'    => Helper::get_css_value(
				$attr['labelLineHeightMobile'],
				$attr['labelLineHeightType']
			),
			'margin-top'     => Helper::get_css_value(
				$attr['labelTopMarginMobile'],
				$attr['labelMarginUnitMobile']
			),
			'margin-right'   => Helper::get_css_value(
				$attr['labelRightMarginMobile'],
				$attr['labelMarginUnitMobile']
			),
			'margin-bottom'  => Helper::get_css_value(
				$attr['labelBottomMarginMobile'],
				$attr['labelMarginUnitMobile']
			),
			'margin-left'    => Helper::get_css_value(
				$attr['labelLeftMarginMobile'],
				$attr['labelMarginUnitMobile']
			),
		];

		$m_selectors['.wp-block-spectra-pro-login .spectra-pro-login-form__user-pass label'] = [
			'font-size'      => Helper::get_css_value(
				$attr['labelFontSizeMobile'],
				$attr['labelFontSizeType']
			),
			'letter-spacing' => Helper::get_css_value( $attr['labelLetterSpacingMobile'], $attr['labelLetterSpacingType'] ),
			'line-height'    => Helper::get_css_value(
				$attr['labelLineHeightMobile'],
				$attr['labelLineHeightType']
			),
			'margin-top'     => Helper::get_css_value(
				$attr['labelTopMarginMobile'],
				$attr['labelMarginUnitMobile']
			),
			'margin-right'   => Helper::get_css_value(
				$attr['labelRightMarginMobile'],
				$attr['labelMarginUnitMobile']
			),
			'margin-bottom'  => Helper::get_css_value(
				$attr['labelBottomMarginMobile'],
				$attr['labelMarginUnitMobile']
			),
			'margin-left'    => Helper::get_css_value(
				$attr['labelLeftMarginMobile'],
				$attr['labelMarginUnitMobile']
			),
		];

		$m_selectors['.wp-block-spectra-pro-login .spectra-pro-login-form__heading'] = [
			'font-size'      => Helper::get_css_value(
				$attr['headingFontSizeMobile'],
				$attr['headingFontSizeType']
			),
			'letter-spacing' => Helper::get_css_value( $attr['headingLetterSpacingMobile'], $attr['headingLetterSpacingType'] ),
			'line-height'    => Helper::get_css_value(
				$attr['headingLineHeightMobile'],
				$attr['headingLineHeightType']
			),
			'margin-top'     => Helper::get_css_value(
				$attr['headingTopMarginMobile'],
				$attr['headingMarginUnitMobile']
			),
			'margin-right'   => Helper::get_css_value(
				$attr['headingRightMarginMobile'],
				$attr['headingMarginUnitMobile']
			),
			'margin-bottom'  => Helper::get_css_value(
				$attr['headingBottomMarginMobile'],
				$attr['headingMarginUnitMobile']
			),
			'margin-left'    => Helper::get_css_value(
				$attr['headingLeftMarginMobile'],
				$attr['headingMarginUnitMobile']
			),
		];

		$m_selectors['.wp-block-spectra-pro-login .spectra-pro-login-form__user-login input'] = $form_input_style_mobile;
		$m_selectors['.wp-block-spectra-pro-login .spectra-pro-login-form__user-pass input']  = $form_input_style_mobile;

		$m_selectors[ $username_icon_input_selector ] = [
			'padding-left' => Helper::get_css_value( $attr['paddingFieldLeftMobile'], $attr['paddingFieldUnitmobile'] ),
		];

		$m_selectors[ $password_icon_input_selector ] = [
			'padding-left' => Helper::get_css_value( $attr['paddingFieldLeftMobile'], $attr['paddingFieldUnitmobile'] ),
		];

		// Field Icon - Username.
		$m_selectors['.wp-block-spectra-pro-login .spectra-pro-login-form-username-wrap--have-icon svg'] = array_merge(
			$field_icon_css_mobile
		);

		// Field Icon - Password.
		$m_selectors['.wp-block-spectra-pro-login .spectra-pro-login-form__user-pass .spectra-pro-login-form-pass-wrap--have-icon svg'] = array_merge(
			$field_icon_css_mobile
		);

		$m_selectors['.wp-block-spectra-pro-login .spectra-pro-login-form__user-pass button'] = [
			'margin-right' => array_key_exists( 'border-right-width', $fields_border_css_mobile ) && ( ! $is_rtl ) ? $fields_border_css_mobile['border-right-width'] : '',
			'margin-left'  => array_key_exists( 'border-left-width', $fields_border_css_mobile ) && $is_rtl ? $fields_border_css_mobile['border-left-width'] : '',
		];

		$m_selectors['.wp-block-spectra-pro-login .spectra-pro-login-form__forgetmenot .spectra-pro-login-form-rememberme'] = [
			'margin-top'    => Helper::get_css_value(
				$attr['labelTopMarginMobile'],
				$attr['labelMarginUnitMobile']
			),
			// Right margin not required.
			'margin-bottom' => Helper::get_css_value(
				$attr['labelBottomMarginMobile'],
				$attr['labelMarginUnitMobile']
			),
			'margin-left'   => Helper::get_css_value(
				$attr['labelLeftMarginMobile'],
				$attr['labelMarginUnitMobile']
			),
		];

		$m_selectors['.wp-block-spectra-pro-login .spectra-pro-login-form__forgetmenot .spectra-pro-login-form-rememberme label'] = [
			'font-size'      => Helper::get_css_value(
				$attr['labelFontSizeMobile'],
				$attr['labelFontSizeType']
			),
			'letter-spacing' => Helper::get_css_value( $attr['labelLetterSpacingMobile'], $attr['labelLetterSpacingType'] ),
			'line-height'    => Helper::get_css_value(
				$attr['labelLineHeightMobile'],
				$attr['labelLineHeightType']
			),
		];

		$m_selectors['.wp-block-spectra-pro-login .spectra-pro-login-form__forgetmenot .spectra-pro-login-form-forgot-password'] = [
			'margin-top'    => Helper::get_css_value(
				$attr['labelTopMarginMobile'],
				$attr['labelMarginUnitMobile']
			),
			'margin-right'  => Helper::get_css_value(
				$attr['labelRightMarginMobile'],
				$attr['labelMarginUnitMobile']
			),
			'margin-bottom' => Helper::get_css_value(
				$attr['labelBottomMarginMobile'],
				$attr['labelMarginUnitMobile']
			),
			// Margin left not required.
		];

		$m_selectors['.wp-block-spectra-pro-login .spectra-pro-login-form__forgetmenot .spectra-pro-login-form-forgot-password a'] = [
			'font-size'      => Helper::get_css_value(
				$attr['labelFontSizeMobile'],
				$attr['labelFontSizeType']
			),
			'letter-spacing' => Helper::get_css_value( $attr['labelLetterSpacingMobile'], $attr['labelLetterSpacingType'] ),
			'line-height'    => Helper::get_css_value(
				$attr['labelLineHeightMobile'],
				$attr['labelLineHeightType']
			),
		];
		$m_selectors['.wp-block-spectra-pro-login .spectra-pro-login-form__submit']       = [
			'justify-content' => $attr['alignLoginBtnMobile'],
		];
		$m_selectors['.wp-block-spectra-pro-login .spectra-pro-login-form-submit-button'] = array_merge(
			[
				'font-size'      => Helper::get_css_value(
					$attr['loginFontSizeMobile'],
					$attr['loginFontSizeType']
				),
				'letter-spacing' => Helper::get_css_value( $attr['loginLetterSpacingMobile'], $attr['loginLetterSpacingType'] ),
				'line-height'    => Helper::get_css_value(
					$attr['loginLineHeightMobile'],
					$attr['loginLineHeightType']
				),
				'padding-top'    => Helper::get_css_value(
					$attr['loginTopPaddingMobile'],
					$attr['loginPaddingUnitMobile']
				),
				'padding-right'  => Helper::get_css_value(
					$attr['loginRightPaddingMobile'],
					$attr['loginPaddingUnitMobile']
				),
				'padding-bottom' => Helper::get_css_value(
					$attr['loginBottomPaddingMobile'],
					$attr['loginPaddingUnitMobile']
				),
				'padding-left'   => Helper::get_css_value(
					$attr['loginLeftPaddingMobile'],
					$attr['loginPaddingUnitMobile']
				),
				'column-gap'     => Helper::get_css_value( $attr['ctaIconSpaceMobile'], $attr['ctaIconSpaceType'] ),
			],
			$full_width_login_btn_mobile,
			$login_border_css_mobile
		);

		$m_selectors['.wp-block-spectra-pro-login .suredash-reset-password-submit'] = array_merge(
			[
				'font-size'      => Helper::get_css_value(
					$attr['loginFontSizeMobile'],
					$attr['loginFontSizeType']
				),
				'letter-spacing' => Helper::get_css_value( $attr['loginLetterSpacingMobile'], $attr['loginLetterSpacingType'] ),
				'line-height'    => Helper::get_css_value(
					$attr['loginLineHeightMobile'],
					$attr['loginLineHeightType']
				),
				'padding-top'    => Helper::get_css_value(
					$attr['loginTopPaddingMobile'],
					$attr['loginPaddingUnitMobile']
				),
				'padding-right'  => Helper::get_css_value(
					$attr['loginRightPaddingMobile'],
					$attr['loginPaddingUnitMobile']
				),
				'padding-bottom' => Helper::get_css_value(
					$attr['loginBottomPaddingMobile'],
					$attr['loginPaddingUnitMobile']
				),
				'padding-left'   => Helper::get_css_value(
					$attr['loginLeftPaddingMobile'],
					$attr['loginPaddingUnitMobile']
				),
				'column-gap'     => Helper::get_css_value( $attr['ctaIconSpaceMobile'], $attr['ctaIconSpaceType'] ),
			],
			$full_width_login_btn_mobile,
			$login_border_css_mobile
		);

		$m_selectors['.wp-block-spectra-pro-login .spectra-pro-login-form__social-facebook'] = array_merge(
			[
				'font-size'      => Helper::get_css_value(
					$attr['facebookFontSizeMobile'],
					$attr['facebookFontSizeType']
				),
				'letter-spacing' => Helper::get_css_value( $attr['facebookLetterSpacingMobile'], $attr['facebookLetterSpacingType'] ),
				'line-height'    => Helper::get_css_value(
					$attr['facebookLineHeightMobile'],
					$attr['facebookLineHeightType']
				),
				'padding-top'    => Helper::get_css_value(
					$attr['facebookTopPaddingMobile'],
					$attr['facebookPaddingUnitMobile']
				),
				'padding-right'  => Helper::get_css_value(
					$attr['facebookRightPaddingMobile'],
					$attr['facebookPaddingUnitMobile']
				),
				'padding-bottom' => Helper::get_css_value(
					$attr['facebookBottomPaddingMobile'],
					$attr['facebookPaddingUnitMobile']
				),
				'padding-left'   => Helper::get_css_value(
					$attr['facebookLeftPaddingMobile'],
					$attr['facebookPaddingUnitMobile']
				),
			],
			$facebook_border_css_mobile
		);
		$m_selectors['.wp-block-spectra-pro-login .spectra-pro-login-form__social-google']   = array_merge(
			[
				'font-size'      => Helper::get_css_value(
					$attr['googleFontSizeMobile'],
					$attr['googleFontSizeType']
				),
				'letter-spacing' => Helper::get_css_value( $attr['googleLetterSpacingMobile'], $attr['googleLetterSpacingType'] ),
				'line-height'    => Helper::get_css_value(
					$attr['googleLineHeightMobile'],
					$attr['googleLineHeightType']
				),
				'padding-top'    => Helper::get_css_value(
					$attr['googleTopPaddingMobile'],
					$attr['googlePaddingUnitMobile']
				),
				'padding-right'  => Helper::get_css_value(
					$attr['googleRightPaddingMobile'],
					$attr['googlePaddingUnitMobile']
				),
				'padding-bottom' => Helper::get_css_value(
					$attr['googleBottomPaddingMobile'],
					$attr['googlePaddingUnitMobile']
				),
				'padding-left'   => Helper::get_css_value(
					$attr['googleLeftPaddingMobile'],
					$attr['googlePaddingUnitMobile']
				),
			],
			$facebook_border_css_mobile
		);
		$m_selectors[' .spectra-pro-login-form__social']                                     = [
			'justify-content' => $postition_google_facebook_button_mobile,
			'align-items'     => $align_items_google_facebook_button_mobile,
			'flex-direction'  => $apply_stack_mobile,
			'gap'             => $attr['gapSocialLoginMobile'] . 'px',
		];

		if ( $attr['ctaIconPosition'] === 'before' ) {
			$selectors['.wp-block-spectra-pro-login .spectra-pro-login-form-submit-button svg']   = [
				'width'  => Helper::get_css_value(
					$attr['loginFontSize'],
					$attr['loginFontSizeType']
				),
				'height' => Helper::get_css_value(
					$attr['loginFontSize'],
					$attr['loginFontSizeType']
				),
				'stroke' => $attr['loginColor'],
			];
			$t_selectors['.wp-block-spectra-pro-login .spectra-pro-login-form-submit-button svg'] = [
				'width'  => Helper::get_css_value(
					$attr['loginFontSizeTablet'],
					$attr['loginFontSizeType']
				),
				'height' => Helper::get_css_value(
					$attr['loginFontSizeTablet'],
					$attr['loginFontSizeType']
				),
			];
			$m_selectors['.wp-block-spectra-pro-login .spectra-pro-login-form-submit-button svg'] = [
				'width'  => Helper::get_css_value(
					$attr['loginFontSizeMobile'],
					$attr['loginFontSizeType']
				),
				'height' => Helper::get_css_value(
					$attr['loginFontSizeMobile'],
					$attr['loginFontSizeType']
				),
			];
		}

		if ( $attr['ctaIconPosition'] === 'after' ) {
			$selectors['.wp-block-spectra-pro-login .spectra-pro-login-form-submit-button svg'] = [
				'width'  => Helper::get_css_value(
					$attr['loginFontSize'],
					$attr['loginFontSizeType']
				),
				'height' => Helper::get_css_value(
					$attr['loginFontSize'],
					$attr['loginFontSizeType']
				),
				'stroke' => $attr['loginColor'],
			];

			$t_selectors['.wp-block-spectra-pro-login .spectra-pro-login-form-submit-button svg'] = [
				'width'  => Helper::get_css_value(
					$attr['loginFontSizeTablet'],
					$attr['loginFontSizeType']
				),
				'height' => Helper::get_css_value(
					$attr['loginFontSizeTablet'],
					$attr['loginFontSizeType']
				),
			];
			$m_selectors['.wp-block-spectra-pro-login .spectra-pro-login-form-submit-button svg'] = [
				'width'  => Helper::get_css_value(
					$attr['loginFontSizeMobile'],
					$attr['loginFontSizeType']
				),
				'height' => Helper::get_css_value(
					$attr['loginFontSizeMobile'],
					$attr['loginFontSizeType']
				),
			];
		}

		// Grouping together Row Gap Selectors - Tablet.
		$t_selectors['.wp-block-spectra-pro-login .spectra-pro-login-form__user-login']  = [
			'margin-bottom' => Helper::get_css_value( $attr['formRowsGapSpaceTablet'], $attr['formRowsGapSpaceUnit'] ),
		];
		$t_selectors['.wp-block-spectra-pro-login .spectra-pro-login-form__recaptcha']   = [
			'margin-bottom' => Helper::get_css_value( $attr['formRowsGapSpaceTablet'], $attr['formRowsGapSpaceUnit'] ),
		];
		$t_selectors['.wp-block-spectra-pro-login .spectra-pro-login-form__turnstile']   = [
			'margin-bottom' => Helper::get_css_value( $attr['formRowsGapSpaceTablet'], $attr['formRowsGapSpaceUnit'] ),
		];
		$t_selectors['.wp-block-spectra-pro-login .spectra-pro-login-form__user-pass']   = [
			'margin-bottom' => Helper::get_css_value( $attr['formRowsGapSpaceTablet'], $attr['formRowsGapSpaceUnit'] ),
		];
		$t_selectors['.wp-block-spectra-pro-login .spectra-pro-login-form__forgetmenot'] = [
			'margin-bottom' => Helper::get_css_value( $attr['formRowsGapSpaceTablet'], $attr['formRowsGapSpaceUnit'] ),
		];
		$t_selectors['.wp-block-spectra-pro-login .spectra-pro-login-form__submit']      = [
			'margin-bottom' => Helper::get_css_value( $attr['formRowsGapSpaceTablet'], $attr['formRowsGapSpaceUnit'] ),
		];

		// Grouping together Row Gap Selectors - Mobile.
		$m_selectors['.wp-block-spectra-pro-login .spectra-pro-login-form__user-login']  = [
			'margin-bottom' => Helper::get_css_value( $attr['formRowsGapSpaceMobile'], $attr['formRowsGapSpaceUnit'] ),
		];
		$m_selectors['.wp-block-spectra-pro-login .spectra-pro-login-form__recaptcha']   = [
			'margin-bottom' => Helper::get_css_value( $attr['formRowsGapSpaceMobile'], $attr['formRowsGapSpaceUnit'] ),
		];
		$m_selectors['.wp-block-spectra-pro-login .spectra-pro-login-form__turnstile']   = [
			'margin-bottom' => Helper::get_css_value( $attr['formRowsGapSpaceMobile'], $attr['formRowsGapSpaceUnit'] ),
		];
		$m_selectors['.wp-block-spectra-pro-login .spectra-pro-login-form__user-pass']   = [
			'margin-bottom' => Helper::get_css_value( $attr['formRowsGapSpaceMobile'], $attr['formRowsGapSpaceUnit'] ),
		];
		$m_selectors['.wp-block-spectra-pro-login .spectra-pro-login-form__forgetmenot'] = [
			'margin-bottom' => Helper::get_css_value( $attr['formRowsGapSpaceMobile'], $attr['formRowsGapSpaceUnit'] ),
		];
		$m_selectors['.wp-block-spectra-pro-login .spectra-pro-login-form__submit']      = [
			'margin-bottom' => Helper::get_css_value( $attr['formRowsGapSpaceMobile'], $attr['formRowsGapSpaceUnit'] ),
		];

		$combined_selectors = [
			'desktop' => $selectors,
			'tablet'  => $t_selectors,
			'mobile'  => $m_selectors,
		];

		$base_selector = '.uagb-block-';

		return Helper::generate_all_css( $combined_selectors, $base_selector . $id );
	}
}

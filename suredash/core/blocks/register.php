<?php
/**
 * Register Block.
 *
 * @package SureDash
 */

namespace SureDashboard\Core\Blocks;

use SureDashboard\Inc\Traits\Get_Instance;
use SureDashboard\Inc\Utils\Helper;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Class Register.
 */
class Register {
	use Get_Instance;

	/**
	 * Registers the `core/latest-posts` block on server.
	 *
	 * @since 1.0.0
	 */
	public function register_blocks(): void {
		if ( ! function_exists( 'register_block_type' ) ) {
			return;
		}

		add_action( 'suredash_enqueue_register_block_scripts', [ $this, 'enqueue_front_assets' ], 10, 2 );

		register_block_type(
			'suredash/register',
			[
				'attributes'      => $this->get_default_attributes(),
				'render_callback' => [ $this, 'render_html' ],
				'style'           => 'portal-register-block',
			]
		);
	}

	/**
	 * Block Default Attributes
	 *
	 * @return array<string, array<string, mixed>>
	 * @since 1.0.0
	 */
	public function get_default_attributes() {
		return [
			'block_id'                     => [
				'type'    => 'string',
				'default' => '',
			],
			'newUserRole'                  => [
				'type'    => 'string',
				'default' => '',
			],
			'afterRegisterActions'         => [
				'type'    => 'array',
				'default' => [ 'autoLogin' ],
			],
			// email.
			'emailTemplateType'            => [
				'type'    => 'string',
				'default' => 'default',
			],
			'emailTemplateSubject'         => [
				'type'    => 'string',
				'default' => 'Thank you for registering with "{{site_title}}"!',
			],
			'emailTemplateMessage'         => [
				'type'    => 'string',
				'default' => '',
			],
			'emailTemplateMessageType'     => [
				'type'    => 'string',
				'default' => 'html',
			],
			// Email - error.
			'messageInvalidEmailError'     => [
				'type'    => 'string',
				'default' => __( 'You have used an invalid email.', 'suredash' ),
			],
			'messageEmailMissingError'     => [
				'type'    => 'string',
				'default' => __( 'Email is missing or invalid.', 'suredash' ),
			],
			'messageEmailAlreadyUsedError' => [
				'type'    => 'string',
				'default' => __( 'The provided email is already registered with another account. Please login or reset password or use another email.', 'suredash' ),
			],
			// Username - error.
			'messageInvalidUsernameError'  => [
				'type'    => 'string',
				'default' => __( 'You have used an invalid username.', 'suredash' ),
			],
			'messageUsernameAlreadyUsed'   => [
				'type'    => 'string',
				'default' => __( 'Invalid username provided or the username is already registered.', 'suredash' ),
			],
			// Password - error.
			'messageInvalidPasswordError'  => [
				'type'    => 'string',
				'default' => __( 'Your password is invalid.', 'suredash' ),
			],
			'messagePasswordConfirmError'  => [
				'type'    => 'string',
				'default' => __( 'Your passwords do not match.', 'suredash' ),
			],
			// Spam protection.
			'spamProtection'               => [
				'type'    => 'string',
				'default' => 'none',
			],
			// Form Heading.
			'showFormHeading'              => [
				'type'    => 'boolean',
				'default' => true,
			],
			'formHeadingText'              => [
				'type'    => 'string',
				'default' => '',
			],
			'formHeadingTag'               => [
				'type'    => 'string',
				'default' => 'h3',
			],
			'reCaptchaEnable'              => [
				'type'    => 'boolean',
				'default' => false,
			],
			'reCaptchaType'                => [
				'type'    => 'string',
				'default' => 'v2',
			],
			'turnstileAppearance'          => [
				'type'    => 'string',
				'default' => 'auto',
			],
			'hidereCaptchaBatch'           => [
				'type'    => 'boolean',
				'default' => false,
			],
			// Terms - error.
			'messageTermsError'            => [
				'type'    => 'string',
				'default' => __( 'Please accept the Terms and Conditions, and try again.', 'suredash' ),
			],
			'messageOtherError'            => [
				'type'    => 'string',
				'default' => __( 'Something went wrong!', 'suredash' ),
			],
			// success - message.
			'messageSuccessRegistration'   => [
				'type'    => 'string',
				'default' => __( 'Registration completed successfully. Check your inbox for password if you did not provide it while registering.', 'suredash' ),
			],

			// social.
			'enableGoogleLogin'            => [
				'type'    => 'boolean',
				'default' => false,
			],
			'enableFacebookLogin'          => [
				'type'    => 'boolean',
				'default' => false,
			],

			// fields border defaults.
			'fieldBorderStyle'             => [
				'type'    => 'string',
				'default' => 'solid',
			],
			'fieldBorderTopLeftRadius'     => [
				'type'    => 'number',
				'default' => 3,
			],
			'fieldBorderTopRightRadius'    => [
				'type'    => 'number',
				'default' => 3,
			],
			'fieldBorderBottomLeftRadius'  => [
				'type'    => 'number',
				'default' => 3,
			],
			'fieldBorderBottomRightRadius' => [
				'type'    => 'number',
				'default' => 3,
			],
			'fieldBorderTopWidth'          => [
				'type'    => 'number',
				'default' => 1,
			],
			'fieldBorderRightWidth'        => [
				'type'    => 'number',
				'default' => 1,
			],
			'fieldBorderBottomWidth'       => [
				'type'    => 'number',
				'default' => 1,
			],
			'fieldBorderLeftWidth'         => [
				'type'    => 'number',
				'default' => 1,
			],
			'fieldBorderColor'             => [
				'type'    => 'string',
				'default' => '#E9E9E9',
			],
		];
	}

	/**
	 * Generate User name from email.
	 *
	 * @param string $email email.
	 * @param string $suffix emial suffix.
	 * @return string
	 * @since 1.0.0
	 */
	public function create_username( $email, $suffix ) {
		$username_parts = [];

		// If there are no parts, e.g. name had unicode chars, or was not provided, fallback to email.
		$email_parts    = explode( '@', $email );
		$email_username = $email_parts[0];

		// Exclude common prefixes.
		if ( in_array(
			$email_username,
			[
				'sales',
				'hello',
				'mail',
				'contact',
				'info',
			],
			true
		) ) {
			// Get the domain part.
			$email_username = $email_parts[1];
		}

		$username_parts[] = sanitize_user( $email_username, true );

		$username = strtolower( implode( '', $username_parts ) );

		if ( $suffix ) {
			$username .= $suffix;
		}

		if ( username_exists( $username ) ) {
			// Generate something unique to append to the username in case of a conflict with another user.
			$suffix = '-' . zeroise( wp_rand( 0, 9999 ), 4 );
			return $this->create_username( $email, $suffix );
		}

		return $username;
	}

	/**
	 * Get Register block attributes.
	 *
	 * @param string $content   The block content.
	 * @param string $block_name The block name.
	 * @param string $block_id   The block ID.
	 * @since 1.0.0
	 * @return array<int, array<string, mixed>>
	 */
	public function get_block_attributes( $content, $block_name, $block_id ) {
		$blocks = parse_blocks( $content );
		if ( empty( $blocks ) ) {
			return [];
		}
		return $this->get_block_attributes_recursive( $blocks, $block_name, $block_id ); // @phpstan-ignore-line
	}

	/**
	 * Get all attributes from post content recursively.
	 *
	 * @param array<int, array<string, mixed>> $blocks     Blocks array.
	 * @param string                           $block_name Block Name.
	 * @param string                           $block_id   Block ID.
	 * @return array<int, array<string, mixed>>
	 * @since 0.0.1
	 */
	public function get_block_attributes_recursive( $blocks, $block_name, $block_id ) {
		$attributes = [];

		if ( ! is_array( $blocks ) ) {
			return $attributes;
		}

		foreach ( $blocks as $block ) {
			if ( $block['blockName'] === $block_name && is_array( $block['attrs'] ) && $block['attrs']['block_id'] === $block_id ) {
				$attributes[ $block_name ] = $block['attrs'];
				if ( is_array( $block['innerBlocks'] ) && count( $block['innerBlocks'] ) ) {
					foreach ( $block['innerBlocks'] as $inner_block ) {
						if ( isset( $inner_block['attrs']['name'] ) ) {
							$attributes[ $inner_block['attrs']['name'] ] = $inner_block['attrs'];
						}
					}
				}
				return $attributes; // Found the block, return its attributes.
			}
			if ( is_array( $block['innerBlocks'] ) && count( $block['innerBlocks'] ) ) {
				// If the block is not found at this level, check inner blocks recursively.
				$inner_attributes = $this->get_block_attributes_recursive( $block['innerBlocks'], $block_name, $block_id );
				if ( ! empty( $inner_attributes ) ) {
					return $inner_attributes; // Found the block in inner blocks, return its attributes.
				}
			}
		}

		return $attributes; // Block not found in this branch.
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
		$entities = [ 'loginInfo', 'label', 'input', 'registerBtn' ];

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

		$register_options = apply_filters(
			'suredashboard_register_options',
			[
				'cannot_be_blank'              => esc_html__( 'cannot be blank.', 'suredash' ),
				'first_name'                   => esc_html__( 'First Name', 'suredash' ),
				'last_name'                    => esc_html__( 'Last Name', 'suredash' ),
				'this_field'                   => esc_html__( 'This field', 'suredash' ),
				'redirect_url'                 => home_url( '/' . suredash_get_community_slug() . '/' ),
				'ajax_url'                     => esc_url( admin_url( 'admin-ajax.php' ) ),
				'post_id'                      => get_the_ID(),
				'block_id'                     => $block_id,
				'afterRegisterActions'         => $block_attr['afterRegisterActions'],
				'captchaType'                  => $block_attr['spamProtection'],
				'reCaptchaEnable'              => $block_attr['reCaptchaEnable'],
				'reCaptchaType'                => $block_attr['reCaptchaType'],
				'hidereCaptchaBatch'           => $block_attr['hidereCaptchaBatch'],
				'recaptchaSiteKey'             => Helper::get_option( 'recaptcha_site_key_' . $block_attr['reCaptchaType'], '' ),
				'turnstileSiteKey'             => Helper::get_option( 'turnstile_site_key', '' ),
				'turnstileTheme'               => $block_attr['turnstileAppearance'],
				'enableGoogleLogin'            => $block_attr['enableGoogleLogin'],
				'enableFacebookLogin'          => $block_attr['enableFacebookLogin'],
				'wp_version'                   => version_compare( get_bloginfo( 'version' ), '5.4.99', '>=' ),
				'messageInvalidEmailError'     => $block_attr['messageInvalidEmailError'],
				'messageEmailMissingError'     => $block_attr['messageEmailMissingError'],
				'messageEmailAlreadyUsedError' => $block_attr['messageEmailAlreadyUsedError'],
				'messageInvalidUsernameError'  => $block_attr['messageInvalidUsernameError'],
				'messageUsernameAlreadyUsed'   => $block_attr['messageUsernameAlreadyUsed'],
				'messageInvalidPasswordError'  => $block_attr['messageInvalidPasswordError'],
				'messagePasswordConfirmError'  => $block_attr['messagePasswordConfirmError'],
				'messageTermsError'            => $block_attr['messageTermsError'],
				'messageOtherError'            => $block_attr['messageOtherError'],
				'messageSuccessRegistration'   => $block_attr['messageSuccessRegistration'],
				'loggedInMessage'              => esc_html__( 'You have logged in successfully. Redirecting…', 'suredash' ),
			],
			$block_id
		);

		ob_start();
		?>
			<script>
				window.addEventListener( 'load', function() {
					SureDashRegister.init( '<?php echo esc_attr( $selector ); ?>', <?php echo wp_json_encode( $register_options ); ?> );
				});
			</script>
		<?php
		echo do_shortcode( ob_get_clean() ); // @phpstan-ignore-line
	}

	/**
	 * Render Registration Block HTML.
	 *
	 * @param array<string, string> $attributes Array of block attributes.
	 * @param string                $content String of block Content.
	 *
	 * @since 1.0.0
	 *
	 * @return string|false|null
	 */
	public function render_html( $attributes, $content ) {
		$wrapper_classes = [
			'uagb-block-' . $attributes['block_id'],
			'wp-block-spectra-pro-register',
			'wp-block-spectra-pro-register__logged-in-message',
		];

		$wp_registration_enabled = get_option( 'users_can_register' );

		if ( ! $wp_registration_enabled && ! Helper::get_option( 'override_wp_registration' ) ) {
			return false;
		}

		if ( is_user_logged_in() ) {
			ob_start();
			?>
				<div class="<?php echo esc_attr( implode( ' ', $wrapper_classes ) ); ?>">
					<?php
					$user_name   = suredash_get_user_display_name();
					$a_tag       = '<a href="' . esc_url( wp_logout_url( ! empty( $attributes['redirectAfterLogoutURL'] ) ? $attributes['redirectAfterLogoutURL'] : home_url( suredash_get_community_slug() ) ) ) . '">';
					$close_a_tag = '</a>';
					/* translators: %1$s user name */
					printf( esc_html__( 'You are logged in as %1$s (%2$sLogout%3$s)', 'suredash' ), wp_kses_post( $user_name ), wp_kses_post( $a_tag ), wp_kses_post( $close_a_tag ) );
					?>
				</div>
			<?php
			return ob_get_clean();
		}

		// Replace the value of the input tag with the actual value.
		$actual_value = wp_create_nonce( 'portal-register-block-nonce' );

		// add nonce.
		$content = str_replace( '<input type="hidden" name="_nonce" value="ssr_nonce_replace"/>', '<input type="hidden" name="_nonce" value="' . $actual_value . '"/>', $content );

		// Render heading server-side to handle HTML formatting properly.
		if ( ! empty( $attributes['showFormHeading'] ) && ! empty( $attributes['formHeadingText'] ) ) {
			$heading_tag  = ! empty( $attributes['formHeadingTag'] ) ? $attributes['formHeadingTag'] : 'h3';
			$heading_html = sprintf(
				'<%1$s class="spectra-pro-register-form__heading">%2$s</%1$s>',
				esc_attr( $heading_tag ),
				wp_kses_post( $attributes['formHeadingText'] )
			);

			// Replace the escaped heading with properly formatted heading.
			$escaped_heading_pattern = '/<(h[1-6]|p|div) class="spectra-pro-register-form__heading">.*?<\/\1>/s';
			if ( preg_match( $escaped_heading_pattern, $content ) ) {
				$content = (string) preg_replace_callback(
					$escaped_heading_pattern,
					static function() use ( $heading_html ) {
						return $heading_html;
					},
					$content
				);
			}
		}
		// add recaptcha sitekey.

		$captcha_type = $attributes['spamProtection'] ?? 'none';
		if ( $captcha_type === 'recaptcha' ) {
			$recaptcha_type = ( $attributes['reCaptchaType'] ?? 'v2' );
			if ( $recaptcha_type === 'v2' ) {
				$recaptcha_site_key_v2 = Helper::get_option( 'recaptcha_site_key_v2', '' );

				if ( ! is_string( $recaptcha_site_key_v2 ) ) {
					$recaptcha_site_key_v2 = '';
				}

				$content = str_replace( 'ssr_sitekey_replace', $recaptcha_site_key_v2, $content );
			}
		} elseif ( $captcha_type === 'turnstile' ) {
			$turnstile_site_key = Helper::get_option( 'turnstile_site_key', '' );
			if ( ! is_string( $turnstile_site_key ) ) {
				$turnstile_site_key = '';
			}
			$content = str_replace( 'ssr_turnstile_sitekey_replace', $turnstile_site_key, $content );
		}

		// Add social appId/clientId.
		$enable_facebook_login = (bool) ( $attributes['enableFacebookLogin'] ?? false );
		$enable_google_login   = (bool) ( $attributes['enableGoogleLogin'] ?? false );
		if ( $enable_facebook_login || $enable_google_login ) {
			$social_connect = [
				'google_token_id'       => Helper::get_option( 'google_token_id' ),
				'google_token_secret'   => Helper::get_option( 'google_token_secret' ),
				'facebook_token_id'     => Helper::get_option( 'facebook_token_id' ),
				'facebook_token_secret' => Helper::get_option( 'facebook_token_secret' ),
			];

			if ( $enable_google_login && is_string( $social_connect['google_token_id'] ) ) {
				$content = str_replace( 'ssr_google_clientid_replace', $social_connect['google_token_id'], $content );
			}
			if ( $enable_facebook_login && is_string( $social_connect['facebook_token_id'] ) ) {
				$content = str_replace( 'ssr_facebook_appid_replace', $social_connect['facebook_token_id'], $content );
			}

			// hide social div.
			if ( empty( $social_connect['google_token_id'] ) && empty( $social_connect['facebook_token_id'] ) ) {
				$content = str_replace( '<div class="spectra-pro-register-form__social">', '<div class="spectra-pro-register-form__social" style="display:none;">', $content );
			} elseif ( empty( $social_connect['google_token_id'] ) ) {
				$content = str_replace( 'data-clientid', 'data-clientid style="display:none;"', $content );
			} elseif ( empty( $social_connect['facebook_token_id'] ) ) {
				$content = str_replace( 'data-appid', 'data-appid style="display:none;"', $content );
			}
		}

		return $content;
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
	 * Get login block style attributes.
	 *
	 * @since 1.0.0
	 * @return array<mixed>
	 */
	public function get_style_block_attributes() {
		$form__border_attribute     = Helper::generate_border_attribute( 'form' );
		$input_border_attribute     = Helper::generate_border_attribute( 'field' );
		$register__border_attribute = Helper::generate_border_attribute( 'btn' );
		$google__border_attribute   = Helper::generate_border_attribute(
			'google'
		);
		$facebook__border_attribute = Helper::generate_border_attribute(
			'facebook',
		);

		return array_merge(
			[
				'afterRegisterActions'            => [],
				'spamProtection'                  => 'none',
				'reCaptchaEnable'                 => false,
				'reCaptchaType'                   => 'v2',
				'turnstileAppearance'             => 'auto',
				'hidereCaptchaBatch'              => false,

				'enableGoogleLogin'               => false,
				'enableFacebookLogin'             => false,

				'formStyle'                       => 'boxed',
				'overallAlignment'                => '',

				// alignment.
				'alignRegisterBtn'                => 'full',
				'alignRegisterBtnTablet'          => 'full',
				'alignRegisterBtnMobile'          => 'full',

				// google and facebook alignment.
				'alignGooleFacebookBtn'           => 'center',
				'alignGooleFacebookBtnTablet'     => 'center',
				'alignGooleFacebookBtnMobile'     => 'center',
				// stack options.
				'stackGoogleFacebookButton'       => 'off',
				'stackGoogleFacebookButtonTablet' => 'off',
				'stackGoogleFacebookButtonMobile' => 'off',
				// gap between social logins.
				'gapSocialLogin'                  => 15,
				'gapSocialLoginTablet'            => 15,
				'gapSocialLoginMobile'            => 15,

				// form styling.
				'formWidth'                       => '100',
				'formWidthTablet'                 => '100',
				'formWidthMobile'                 => '100',
				'formWidthType'                   => '%',
				'formWidthTypeTablet'             => '%',
				'formWidthTypeMobile'             => '%',

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

				// row gap.
				'rowGap'                          => '20',
				'rowGapTablet'                    => '',
				'rowGapMobile'                    => '',
				'rowGapUnit'                      => 'px',
				// form padding.
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

				// heading style.
				'headingLoadGoogleFonts'          => false,
				'headingFontFamily'               => 'Default',
				'headingFontWeight'               => '',
				'headingFontSize'                 => '',
				'headingFontSizeType'             => 'px',
				'headingFontSizeTablet'           => '',
				'headingFontSizeMobile'           => '',
				'headingLineHeight'               => '',
				'headingLineHeightType'           => 'em',
				'headingLineHeightTablet'         => '',
				'headingLineHeightMobile'         => '',
				'headingFontStyle'                => 'normal',
				'headingLetterSpacing'            => '',
				'headingLetterSpacingTablet'      => '',
				'headingLetterSpacingMobile'      => '',
				'headingLetterSpacingType'        => 'px',
				'headingColor'                    => '',
				'headingHoverColor'               => '',
				'headingDecoration'               => '',
				'headingTransform'                => '',
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
				'headingMarginLink'               => false,

				'loginInfoLinkColor'              => '',
				'loginInfoLinkHoverColor'         => '',
				// label style.
				'labelloadGoogleFonts'            => false,
				'labelFontFamily'                 => 'Default',
				'labelFontWeight'                 => '',
				'labelFontSize'                   => '',
				'labelFontSizeType'               => 'px',
				'labelFontSizeTablet'             => '',
				'labelFontSizeMobile'             => '',
				'labelLineHeight'                 => '',
				'labelLineHeightType'             => 'em',
				'labelLineHeightTablet'           => '',
				'labelLineHeightMobile'           => '',
				'labelGap'                        => '',
				'labelGapTablet'                  => '',
				'labelGapMobile'                  => '',
				'labelGapUnit'                    => 'px',
				'labelFontStyle'                  => 'normal',
				'labelLetterSpacing'              => '',
				'labelLetterSpacingTablet'        => '',
				'labelLetterSpacingMobile'        => '',
				'labelLetterSpacingType'          => 'px',
				'labelColor'                      => '',
				'labelHoverColor'                 => '',
				'labelDecoration'                 => '',
				'labelTransform'                  => '',
				// input style.
				'inputloadGoogleFonts'            => false,
				'inputFontFamily'                 => 'Default',
				'inputFontWeight'                 => '',
				'inputFontSize'                   => '',
				'inputFontSizeType'               => 'px',
				'inputFontSizeTablet'             => '',
				'inputFontSizeMobile'             => '',
				'inputLineHeightType'             => 'em',
				'inputLineHeight'                 => '',
				'inputLineHeightTablet'           => '',
				'inputLineHeightMobile'           => '',
				'inputColor'                      => '',
				'inputplaceholderColor'           => '',
				'inputplaceholderHoverColor'      => '',
				'inputplaceholderActiveColor'     => '',
				'inputBGColor'                    => '',
				'inputBGHoverColor'               => '',
				'fieldBorderHColor'               => '',
				'bgActiveColor'                   => '',
				'inputFontStyle'                  => '',
				'inputLetterSpacing'              => '',
				'inputLetterSpacingTablet'        => '',
				'inputLetterSpacingMobile'        => '',
				'inputLetterSpacingType'          => 'px',
				'inputTransform'                  => '',
				'inputDecoration'                 => '',
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
				'fieldIconSize'                   => 12,
				'fieldIconSizeType'               => 'px',
				'fieldIconColor'                  => '#555D66',
				'fieldIconBorderRightWidth'       => 1,
				'fieldIconBorderColor'            => '',
				// checkbox.
				'checkboxSize'                    => 20,
				'checkboxBackgroundColor'         => '',
				'checkboxColor'                   => '',
				'checkboxBorderWidth'             => 1,
				'checkboxBorderRadius'            => 2,
				'checkboxBorderColor'             => '',
				'checkboxGlowEnable'              => true,
				'checkboxGlowColor'               => '#2271b1',

				// register styling.
				'registerButtonSize'              => 'default',
				'registerPaddingBtnTop'           => '',
				'registerPaddingBtnRight'         => '',
				'registerPaddingBtnBottom'        => '',
				'registerPaddingBtnLeft'          => '',
				'registerPaddingBtnTopTablet'     => '12',
				'registerPaddingBtnRightTablet'   => '24',
				'registerPaddingBtnBottomTablet'  => '12',
				'registerPaddingBtnLeftTablet'    => '24',
				'registerPaddingBtnTopMobile'     => '',
				'registerPaddingBtnRightMobile'   => '',
				'registerPaddingBtnBottomMobile'  => '',
				'registerPaddingBtnLeftMobile'    => '',
				'registerPaddingBtnUnit'          => 'px',
				'registerMobilePaddingBtnUnit'    => 'px',
				'registerTabletPaddingBtnUnit'    => 'px',
				'registerBtnColor'                => '',
				'registerBtnColorHover'           => '',
				'registerBtnBgColor'              => '',
				'registerBtnBgColorHover'         => '',
				'registerBtnloadGoogleFonts'      => false,
				'registerBtnFontFamily'           => 'Default',
				'registerBtnFontWeight'           => '',
				'registerBtnFontSize'             => '',
				'registerBtnFontSizeType'         => 'px',
				'registerBtnFontSizeTablet'       => '',
				'registerBtnFontSizeMobile'       => '',
				'registerBtnLineHeight'           => '',
				'registerBtnLineHeightType'       => 'em',
				'registerBtnLineHeightTablet'     => '',
				'registerBtnLineHeightMobile'     => '',
				'registerBtnFontStyle'            => '',
				'registerBtnLetterSpacing'        => '',
				'registerBtnLetterSpacingTablet'  => '',
				'registerBtnLetterSpacingMobile'  => '',
				'registerBtnLetterSpacingType'    => 'px',
				'registerBtnTransform'            => '',
				'registerBtnDecoration'           => '',

				// Register Button Icon.
				'ctaIcon'                         => '',
				'ctaIconPosition'                 => 'after',
				'ctaIconSpace'                    => 5,
				'ctaIconSpaceTablet'              => '',
				'ctaIconSpaceMobile'              => '',
				'ctaIconSpaceType'                => 'px',

				// google styling.
				'googleButtonSize'                => 'full',
				'googlePaddingBtnTop'             => '12',
				'googlePaddingBtnRight'           => '24',
				'googlePaddingBtnBottom'          => '12',
				'googlePaddingBtnLeft'            => '24',
				'googlePaddingBtnTopTablet'       => '',
				'googlePaddingBtnRightTablet'     => '',
				'googlePaddingBtnBottomTablet'    => '',
				'googlePaddingBtnLeftTablet'      => '',
				'googlePaddingBtnTopMobile'       => '',
				'googlePaddingBtnRightMobile'     => '',
				'googlePaddingBtnBottomMobile'    => '',
				'googlePaddingBtnLeftMobile'      => '',
				'googlePaddingBtnUnit'            => 'px',
				'googleMobilePaddingBtnUnit'      => 'px',
				'googleTabletPaddingBtnUnit'      => 'px',
				'googleBtnColor'                  => '',
				'googleBtnColorHover'             => '',
				'googleBtnBgColor'                => '',
				'googleBtnBgColorHover'           => '',
				'googleBtnloadGoogleFonts'        => false,
				'googleBtnFontFamily'             => 'Default',
				'googleBtnFontWeight'             => '',
				'googleBtnFontSize'               => '',
				'googleBtnFontSizeType'           => 'px',
				'googleBtnFontSizeTablet'         => '',
				'googleBtnFontSizeMobile'         => '',
				'googleBtnLineHeight'             => '',
				'googleBtnLineHeightType'         => 'em',
				'googleBtnLineHeightTablet'       => '',
				'googleBtnLineHeightMobile'       => '',
				'googleBtnFontStyle'              => '',
				'googleBtnLetterSpacing'          => '',
				'googleBtnLetterSpacingTablet'    => '',
				'googleBtnLetterSpacingMobile'    => '',
				'googleBtnLetterSpacingType'      => 'px',
				'googleBtnTransform'              => '',
				'googleBtnDecoration'             => '',

				// facebook styling.
				'facebookButtonSize'              => 'full',
				'facebookPaddingBtnTop'           => '12',
				'facebookPaddingBtnRight'         => '24',
				'facebookPaddingBtnBottom'        => '12',
				'facebookPaddingBtnLeft'          => '24',
				'facebookPaddingBtnTopTablet'     => '',
				'facebookPaddingBtnRightTablet'   => '',
				'facebookPaddingBtnBottomTablet'  => '',
				'facebookPaddingBtnLeftTablet'    => '',
				'facebookPaddingBtnTopMobile'     => '',
				'facebookPaddingBtnRightMobile'   => '',
				'facebookPaddingBtnBottomMobile'  => '',
				'facebookPaddingBtnLeftMobile'    => '',
				'facebookPaddingBtnUnit'          => 'px',
				'facebookMobilePaddingBtnUnit'    => 'px',
				'facebookTabletPaddingBtnUnit'    => 'px',
				'facebookBtnColor'                => '',
				'facebookBtnColorHover'           => '',
				'facebookBtnBgColor'              => '',
				'facebookBtnBgColorHover'         => '',
				'facebookBtnloadGoogleFonts'      => false,
				'facebookBtnFontFamily'           => 'Default',
				'facebookBtnFontWeight'           => '',
				'facebookBtnFontSize'             => '',
				'facebookBtnFontSizeType'         => 'px',
				'facebookBtnFontSizeTablet'       => '',
				'facebookBtnFontSizeMobile'       => '',
				'facebookBtnLineHeight'           => '',
				'facebookBtnLineHeightType'       => 'em',
				'facebookBtnLineHeightTablet'     => '',
				'facebookBtnLineHeightMobile'     => '',
				'facebookBtnFontStyle'            => '',
				'facebookBtnLetterSpacing'        => '',
				'facebookBtnLetterSpacingTablet'  => '',
				'facebookBtnLetterSpacingMobile'  => '',
				'facebookBtnLetterSpacingType'    => 'px',
				'facebookBtnTransform'            => '',
				'facebookBtnDecoration'           => '',

				// message.
				'errorMessageBackground'          => '#f8d7da',
				'errorMessageColor'               => '#721c24',
				'errorMessageBorderColor'         => '#ff0000',
				'errorFieldColor'                 => '#ff0000',
				'successMessageBackground'        => '#d4edda',
				'successMessageColor'             => '#155724',
				'successMessageBorderColor'       => '#008000',

				// error message.
				'messageInvalidEmailError'        => __( 'You have used an invalid email.', 'suredash' ),
				'messageEmailMissingError'        => __( 'Please enter a valid email address.', 'suredash' ),
				'messageEmailAlreadyUsedError'    => __( 'Email already in use. Please try to sign in.', 'suredash' ),
				'messageInvalidUsernameError'     => __( 'Invalid user name. Please try again.', 'suredash' ),
				'messageUsernameAlreadyUsed'      => __( 'Username is already taken.', 'suredash' ),
				'messageInvalidPasswordError'     => __( 'Password cannot be accepted. Please try something else.', 'suredash' ),
				'messagePasswordConfirmError'     => __( 'Passwords do not match.', 'suredash' ),
				'messageTermsError'               => __( 'Please try again after accepting terms & conditions.', 'suredash' ),
				'messageOtherError'               => __( 'Something went wrong! Please try again.', 'suredash' ),
				'messageSuccessRegistration'      => __( 'Registration successful. Please check your email inbox.', 'suredash' ),
			],
			$form__border_attribute,
			$register__border_attribute,
			$input_border_attribute,
			$google__border_attribute,
			$facebook__border_attribute,
			[
				'formBorderStyle'  => 'default',
				'fieldBorderStyle' => 'default',
				'btnBorderStyle'   => 'default',
			]
		);
	}

	/**
	 * Get Registration block CSS
	 *
	 * @since 1.0.0
	 * @param array<string, string> $attr The block attributes.
	 * @param string                $id The selector ID.
	 * @return array<string, string> The Widget List.
	 */
	public function get_dynamic_block_css( $attr, $id ) {
		$defaults = $this->get_style_block_attributes();
		$attr     = array_merge( $defaults, (array) $attr );

		// form border.
		$form_border        = Helper::generate_border_css( $attr, 'form' );
		$form_border_tablet = Helper::generate_border_css( $attr, 'form', 'tablet' );
		$form_border_mobile = Helper::generate_border_css( $attr, 'form', 'mobile' );

		// input border.
		$input_border        = Helper::generate_border_css( $attr, 'field' );
		$input_border_tablet = Helper::generate_border_css( $attr, 'field', 'tablet' );
		$input_border_mobile = Helper::generate_border_css( $attr, 'field', 'mobile' );

		// register border.
		$register_btn_border        = Helper::generate_border_css( $attr, 'btn' );
		$register_btn_border_tablet = Helper::generate_border_css( $attr, 'btn', 'tablet' );
		$register_btn_border_mobile = Helper::generate_border_css( $attr, 'btn', 'mobile' );

		// google border.
		$google_btn_border        = Helper::generate_border_css( $attr, 'google' );
		$google_btn_border_tablet = Helper::generate_border_css( $attr, 'google', 'tablet' );
		$google_btn_border_mobile = Helper::generate_border_css( $attr, 'google', 'mobile' );

		// facebook border.
		$facebook_btn_border        = Helper::generate_border_css( $attr, 'facebook' );
		$facebook_btn_border_tablet = Helper::generate_border_css( $attr, 'facebook', 'tablet' );
		$facebook_btn_border_mobile = Helper::generate_border_css( $attr, 'facebook', 'mobile' );

		/**
		 * Alignment Control.
		 */
		$align_register_btn_margin        = $attr['alignRegisterBtn'] === 'right' ? '0 0 0 auto' : ( $attr['alignRegisterBtn'] === 'center' ? '0 auto' : ( $attr['alignRegisterBtn'] === 'full' ? '' : 0 ) );
		$align_register_btn_margin_tablet = $attr['alignRegisterBtnTablet'] === 'right' ? '0 0 0 auto' : ( $attr['alignRegisterBtnTablet'] === 'center' ? '0 auto' : ( $attr['alignRegisterBtnTablet'] === 'full' ? '' : 0 ) );
		$align_register_btn_margin_mobile = $attr['alignRegisterBtnMobile'] === 'right' ? '0 0 0 auto' : ( $attr['alignRegisterBtnMobile'] === 'center' ? '0 auto' : ( $attr['alignRegisterBtnMobile'] === 'full' ? '' : 0 ) );

		// google facebook btn justify content alignment.
		$postition_google_facebook_button        = $attr['alignGooleFacebookBtn'] === 'right' ? 'end' : ( $attr['alignGooleFacebookBtn'] === 'center' ? 'center' : 'start' );
		$postition_google_facebook_button_tablet = $attr['alignGooleFacebookBtnTablet'] === 'right' ? 'end' : ( $attr['alignGooleFacebookBtnTablet'] === 'center' ? 'center' : 'start' );
		$postition_google_facebook_button_mobile = $attr['alignGooleFacebookBtnMobile'] === 'right' ? 'end' : ( $attr['alignGooleFacebookBtnMobile'] === 'center' ? 'center' : 'start' );
		// google facebook btn  alignment.
		$align_items_google_facebook_button        = $attr['stackGoogleFacebookButton'] === 'off' ? 'center' : $postition_google_facebook_button;
		$align_items_google_facebook_button_tablet = $attr['stackGoogleFacebookButtonTablet'] === 'off' ? 'center' : $postition_google_facebook_button_tablet;
		$align_items_google_facebook_button_mobile = $attr['stackGoogleFacebookButtonMobile'] === 'off' ? 'center' : $postition_google_facebook_button_mobile;

		/**
		 * Stack options.
		 */
		$apply_stack        = $attr['stackGoogleFacebookButton'] === 'off' ? 'row' : 'column';
		$apply_stack_tablet = $attr['stackGoogleFacebookButtonTablet'] === 'off' ? 'row' : 'column';
		$apply_stack_mobile = $attr['stackGoogleFacebookButtonMobile'] === 'off' ? 'row' : 'column';

		/**
		 * Stack Control.
		 */
		$box_shadow_position_css = $attr['boxShadowPosition'];

		if ( $attr['boxShadowPosition'] === 'outset' ) {
			$box_shadow_position_css = '';
		}

		$box_shadow_position_css_hover = $attr['boxShadowPositionHover'];

		if ( $attr['boxShadowPositionHover'] === 'outset' ) {
			$box_shadow_position_css_hover = '';
		}

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

		$bg_obj_desktop      = array_merge(
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
		$form_bg_css_desktop = Helper::get_background_obj( $bg_obj_desktop );

		$overall_flex_alignment = '';
		if ( $attr['overallAlignment'] === 'left' ) {
			$overall_flex_alignment = 'flex-start';
		} elseif ( $attr['overallAlignment'] === 'right' ) {
			$overall_flex_alignment = 'flex-end';
		} else {
			$overall_flex_alignment = $attr['overallAlignment'];
		}

		$m_selectors = [];
		$t_selectors = [];

		$selectors = [
			// form.
			'.wp-block-spectra-pro-register'              => array_merge(
				$form_bg_css_desktop,
				[
					'width'          => Helper::get_css_value( $attr['formWidth'], $attr['formWidthType'] ),
					'padding-top'    => Helper::get_css_value( $attr['formTopPadding'], $attr['formPaddingUnit'] ),
					'padding-right'  => Helper::get_css_value( $attr['formRightPadding'], $attr['formPaddingUnit'] ),
					'padding-bottom' => Helper::get_css_value( $attr['formBottomPadding'], $attr['formPaddingUnit'] ),
					'padding-left'   => Helper::get_css_value( $attr['formLeftPadding'], $attr['formPaddingUnit'] ),
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
					'text-align'     => $attr['overallAlignment'],
				],
				$form_border
			),
			'.wp-block-spectra-pro-register:hover'        => [
				'border-color' => $attr['formBorderHColor'],
			],
			' .spectra-pro-register-form label, .spectra-pro-register-form input, .spectra-pro-register-form textarea' => [
				'text-align' => $attr['overallAlignment'],
			],
			' .spectra-pro-register-form .spectra-pro-register-form__terms-wrap' => [
				'justify-content' => $overall_flex_alignment,
			],

			// google and facebook alignment.
			' .spectra-pro-register-form__social'         => [
				'justify-content' => $postition_google_facebook_button,
				'align-items'     => $align_items_google_facebook_button,
				'flex-direction'  => $apply_stack,
				'gap'             => $attr['gapSocialLogin'] . 'px',
			],

			'.wp-block-spectra-pro-register .spectra-pro-register-form' => [
				'gap' => Helper::get_css_value( $attr['rowGap'], $attr['rowGapUnit'] ),
			],
			// input.
			' .spectra-pro-register-form input::placeholder' => [
				'font-size' => Helper::get_css_value( $attr['inputFontSize'], $attr['inputFontSizeType'] ),
				'color'     => $attr['inputplaceholderColor'],
			],
			' .spectra-pro-register-form input:not([type="checkbox"])' => array_merge(
				[
					'font-size'        => Helper::get_css_value( $attr['inputFontSize'], $attr['inputFontSizeType'] ),
					'padding-top'      => Helper::get_css_value( $attr['paddingFieldTop'], $attr['paddingFieldUnit'] ) . ' !important',
					'padding-bottom'   => Helper::get_css_value( $attr['paddingFieldBottom'], $attr['paddingFieldUnit'] ) . ' !important',
					'padding-left'     => Helper::get_css_value( $attr['paddingFieldLeft'], $attr['paddingFieldUnit'] ) . ' !important',
					'padding-right'    => Helper::get_css_value( $attr['paddingFieldRight'], $attr['paddingFieldUnit'] ) . ' !important',
					'color'            => $attr['inputColor'],
					'background-color' => $attr['inputBGColor'],
				],
				$input_border
			),
			' .spectra-pro-register-form input:hover::placeholder' => [
				'color' => $attr['inputplaceholderHoverColor'] . '!important',
			],
			' .spectra-pro-register-form input:focus::placeholder' => [
				'color' => $attr['inputplaceholderActiveColor'] . '!important',
			],
			' .spectra-pro-register-form input:hover'     => [
				'background-color' => $attr['inputBGHoverColor'],
				'border-color'     => $attr['fieldBorderHColor'],
			],
			' .spectra-pro-register-form input:focus'     => [
				'background-color' => $attr['bgActiveColor'],
			],
			' form.spectra-pro-register-form .spectra-pro-register-form__field-wrapper>svg' => array_merge(
				[
					'width'  => Helper::get_css_value( $attr['fieldIconSize'], $attr['fieldIconSizeType'] ),
					'stroke' => $attr['fieldIconColor'],
					'height' => array_key_exists( 'border-top-width', $input_border ) && array_key_exists( 'border-bottom-width', $input_border ) ?
								'calc( 100% - ' . $input_border['border-top-width'] . ' - ' . $input_border['border-bottom-width'] . ' )'
								: '',
					'top'    => array_key_exists( 'border-top-width', $input_border ) ? $input_border['border-top-width'] : '',
					'bottom' => array_key_exists( 'border-bottom-width', $input_border ) ? $input_border['border-bottom-width'] : '',
					'left'   => array_key_exists( 'border-left-width', $input_border ) ? $input_border['border-left-width'] : '',
					'right'  => array_key_exists( 'border-right-width', $input_border ) ? $input_border['border-right-width'] : '',
				],
				[
					'border-width' => Helper::get_css_value( $attr['fieldIconBorderRightWidth'], 'px' ),
					'border-color' => $attr['fieldIconBorderColor'],
				]
			),
			' .spectra-pro-register-form .spectra-pro-register-form__field-wrapper:hover > svg' => [
				'border-color' => $attr['fieldBorderHColor'],
			],
			// checkbox.
			' .spectra-pro-register-form .spectra-pro-register-form__terms-checkbox-checkmark' => [
				'width'         => Helper::get_css_value( $attr['checkboxSize'], 'px' ),
				'height'        => Helper::get_css_value( $attr['checkboxSize'], 'px' ),
				'background'    => $attr['checkboxBackgroundColor'],
				'border-width'  => Helper::get_css_value( $attr['checkboxBorderWidth'], 'px' ),
				'border-radius' => Helper::get_css_value( $attr['checkboxBorderRadius'], 'px' ),
				'border-color'  => $attr['checkboxBorderColor'],
			],
			' .spectra-pro-register-form__terms-checkbox .spectra-pro-register-form__terms-checkbox-checkmark:after' => [
				'font-size' => Helper::get_css_value( strval( floatval( $attr['checkboxSize'] ) / 2 ), 'px' ),
				'color'     => $attr['checkboxColor'],
			],
			// If the user clicks on the checkbox, light it up with some box shadow to portray some interaction!
			' .spectra-pro-register-form__terms-checkbox input[type="checkbox"]:focus + .spectra-pro-register-form__terms-checkbox-checkmark' => [
				'box-shadow' => $attr['checkboxGlowEnable'] && $attr['checkboxGlowColor'] ? '0 0 0 1px ' . $attr['checkboxGlowColor'] : '',
			],

			// Info Link.
			'.wp-block-spectra-pro-register .spectra-pro-register-login-info' => [
				'color'           => $attr['labelColor'],
				'font-size'       => Helper::get_css_value( $attr['labelFontSize'], $attr['labelFontSizeType'] ),
				'font-family'     => $attr['labelFontFamily'],
				'font-style'      => $attr['labelFontStyle'],
				'text-decoration' => $attr['labelDecoration'],
				'text-transform'  => $attr['labelTransform'],
				'font-weight'     => $attr['labelFontWeight'],
				'letter-spacing'  => Helper::get_css_value( $attr['labelLetterSpacing'], $attr['labelLetterSpacingType'] ),
				'line-height'     => Helper::get_css_value( $attr['labelLineHeight'], $attr['labelLineHeightType'] ),

			],
			'.wp-block-spectra-pro-register .spectra-pro-register-login-info a' => [
				'color' => $attr['loginInfoLinkColor'],
			],
			'.wp-block-spectra-pro-register .spectra-pro-register-login-info:hover' => [
				'color' => $attr['labelHoverColor'],
			],
			'.wp-block-spectra-pro-register .spectra-pro-register-login-info a:hover' => [
				'color' => $attr['loginInfoLinkHoverColor'],
			],

			// heading.
			'.wp-block-spectra-pro-register .spectra-pro-register-form__heading' => [
				'color'           => $attr['headingColor'],
				'font-size'       => Helper::get_css_value( $attr['headingFontSize'], $attr['headingFontSizeType'] ),
				'line-height'     => Helper::get_css_value( $attr['headingLineHeight'], $attr['headingLineHeightType'] ),
				'font-family'     => $attr['headingFontFamily'],
				'font-style'      => $attr['headingFontStyle'],
				'text-transform'  => $attr['headingTransform'],
				'text-decoration' => $attr['headingDecoration'],
				'font-weight'     => $attr['headingFontWeight'],
				'letter-spacing'  => Helper::get_css_value( $attr['headingLetterSpacing'], $attr['headingLetterSpacingType'] ),
				'margin-top'      => Helper::get_css_value( $attr['headingTopMargin'], $attr['headingMarginUnit'] ),
				'margin-right'    => Helper::get_css_value( $attr['headingRightMargin'], $attr['headingMarginUnit'] ),
				'margin-bottom'   => Helper::get_css_value( $attr['headingBottomMargin'], $attr['headingMarginUnit'] ),
				'margin-left'     => Helper::get_css_value( $attr['headingLeftMargin'], $attr['headingMarginUnit'] ),
			],
			'.wp-block-spectra-pro-register .spectra-pro-register-form__heading:hover' => [
				'color' => $attr['headingHoverColor'],
			],

			// label.
			' .spectra-pro-register-form label'           => [
				'color'         => $attr['labelColor'],
				'font-size'     => Helper::get_css_value( $attr['labelFontSize'], $attr['labelFontSizeType'] ),
				'margin-bottom' => Helper::get_css_value( $attr['labelGap'], $attr['labelGapUnit'] ),
			],
			' .spectra-pro-register-form label:hover'     => [
				'color' => $attr['labelHoverColor'],
			],
			' .spectra-pro-register-form .spectra-pro-register-form__terms-label' => [
				'color'           => $attr['labelColor'],
				'font-size'       => Helper::get_css_value( $attr['labelFontSize'], $attr['labelFontSizeType'] ),
				'line-height'     => Helper::get_css_value( $attr['labelLineHeight'], $attr['labelLineHeightType'] ),
				'font-family'     => $attr['labelFontFamily'],
				'font-style'      => $attr['labelFontStyle'],
				'text-transform'  => $attr['labelTransform'],
				'text-decoration' => $attr['labelDecoration'],
				'font-weight'     => $attr['labelFontWeight'],
				'letter-spacing'  => Helper::get_css_value( $attr['labelLetterSpacing'], $attr['labelLetterSpacingType'] ),
			],
			' .spectra-pro-register-form .spectra-pro-register-form__terms-label:hover' => [
				'color' => $attr['labelHoverColor'],
			],

			'.wp-block-spectra-pro-register.wp-block-spectra-pro-register__logged-in-message' => [
				'font-family'     => $attr['labelFontFamily'],
				'font-style'      => $attr['labelFontStyle'],
				'text-decoration' => $attr['labelDecoration'],
				'text-transform'  => $attr['labelTransform'],
				'font-weight'     => $attr['labelFontWeight'],
				'font-size'       => Helper::get_css_value( $attr['labelFontSize'], $attr['labelFontSizeType'] ),
				'line-height'     => Helper::get_css_value( $attr['labelLineHeight'], $attr['labelLineHeightType'] ),
				'letter-spacing'  => Helper::get_css_value( $attr['labelLetterSpacing'], $attr['labelLetterSpacingType'] ),
				'color'           => $attr['labelColor'],
			],

			'.wp-block-spectra-pro-register.wp-block-spectra-pro-register__logged-in-message a' => [
				'color' => $attr['loginInfoLinkColor'],
			],

			'.wp-block-spectra-pro-register.wp-block-spectra-pro-register__logged-in-message a:hover' => [
				'color' => $attr['loginInfoLinkHoverColor'],
			],

			// regisgter button.
			' .spectra-pro-register-form .spectra-pro-register-form__submit.wp-block-button__link' => array_merge(
				[
					'font-size'        => Helper::get_css_value( $attr['registerBtnFontSize'], $attr['registerBtnFontSizeType'] ),
					'color'            => $attr['registerBtnColor'],
					'background-color' => $attr['registerBtnBgColor'],
					'padding-top'      => Helper::get_css_value( $attr['registerPaddingBtnTop'], $attr['registerPaddingBtnUnit'] ),
					'padding-bottom'   => Helper::get_css_value( $attr['registerPaddingBtnBottom'], $attr['registerPaddingBtnUnit'] ),
					'padding-left'     => Helper::get_css_value( $attr['registerPaddingBtnLeft'], $attr['registerPaddingBtnUnit'] ),
					'padding-right'    => Helper::get_css_value( $attr['registerPaddingBtnRight'], $attr['registerPaddingBtnUnit'] ),
					// alignment styling.
					'margin'           => $align_register_btn_margin,
					'column-gap'       => Helper::get_css_value( $attr['ctaIconSpace'], $attr['ctaIconSpaceType'] ),
				],
				$register_btn_border
			),

			' .spectra-pro-register-form .spectra-pro-register-form__submit.wp-block-button__link svg' => [
				'width'  => Helper::get_css_value( $attr['registerBtnFontSize'], $attr['registerBtnFontSizeType'] ),
				'height' => Helper::get_css_value( $attr['registerBtnFontSize'], $attr['registerBtnFontSizeType'] ),
			],

			' .spectra-pro-register-form .spectra-pro-register-form__submit:hover' => [
				'color'            => $attr['registerBtnColorHover'],
				'background-color' => $attr['registerBtnBgColorHover'],
				'border-color'     => $attr['btnBorderHColor'],
			],

			// google button.
			' .spectra-pro-register-form__social .spectra-pro-register-form__social-google' => array_merge(
				[
					'width'            => $attr['googleButtonSize'] === 'full' ? '100%' : '',
					'font-size'        => Helper::get_css_value( $attr['googleBtnFontSize'], $attr['googleBtnFontSizeType'] ),
					'color'            => $attr['googleBtnColor'],
					'background-color' => $attr['googleBtnBgColor'],
					'padding-top'      => Helper::get_css_value( $attr['googlePaddingBtnTop'], $attr['googleTabletPaddingBtnUnit'] ),
					'padding-bottom'   => Helper::get_css_value( $attr['googlePaddingBtnBottom'], $attr['googleTabletPaddingBtnUnit'] ),
					'padding-left'     => Helper::get_css_value( $attr['googlePaddingBtnLeft'], $attr['googleTabletPaddingBtnUnit'] ),
					'padding-right'    => Helper::get_css_value( $attr['googlePaddingBtnRight'], $attr['googleTabletPaddingBtnUnit'] ),
				],
				$google_btn_border
			),
			'  .spectra-pro-register-form__social .spectra-pro-register-form__social-google:hover' => [
				'color'            => $attr['googleBtnColorHover'],
				'background-color' => $attr['googleBtnBgColorHover'],
				'border-color'     => $attr['googleBorderHColor'],
			],

			// facebook button.
			'  .spectra-pro-register-form__social .spectra-pro-register-form__social-facebook' => array_merge(
				[
					'width'            => $attr['facebookButtonSize'] === 'full' ? '100%' : '',
					'font-size'        => Helper::get_css_value( $attr['facebookBtnFontSize'], $attr['facebookBtnFontSizeType'] ),
					'color'            => $attr['facebookBtnColor'],
					'background-color' => $attr['facebookBtnBgColor'],
					'padding-top'      => Helper::get_css_value( $attr['facebookPaddingBtnTop'], $attr['facebookTabletPaddingBtnUnit'] ),
					'padding-bottom'   => Helper::get_css_value( $attr['facebookPaddingBtnBottom'], $attr['facebookTabletPaddingBtnUnit'] ),
					'padding-left'     => Helper::get_css_value( $attr['facebookPaddingBtnLeft'], $attr['facebookTabletPaddingBtnUnit'] ),
					'padding-right'    => Helper::get_css_value( $attr['facebookPaddingBtnRight'], $attr['facebookTabletPaddingBtnUnit'] ),
				],
				$facebook_btn_border
			),
			' .spectra-pro-register-form__social .spectra-pro-register-form__social-facebook:hover' => [
				'color'            => $attr['facebookBtnColorHover'],
				'background-color' => $attr['facebookBtnBgColorHover'],
				'border-color'     => $attr['facebookBorderHColor'],
			],

			// message color control.
			' .spectra-pro-register-form-status__success' => [
				'border-left-color' => $attr['successMessageBorderColor'],
				'background-color'  => $attr['successMessageBackground'],
				'color'             => $attr['successMessageColor'],
				'text-align'        => $attr['overallAlignment'],
			],
			' .spectra-pro-register-form-status__error'   => [
				'border-left-color' => $attr['errorMessageBorderColor'],
				'background-color'  => $attr['errorMessageBackground'],
				'color'             => $attr['errorMessageColor'],
				'text-align'        => $attr['overallAlignment'],
			],
			' .spectra-pro-register-form-status__error-item' => [
				'border-left-color' => $attr['errorMessageBorderColor'],
				'background-color'  => $attr['errorMessageBackground'],
				'color'             => $attr['errorMessageColor'],
				'text-align'        => $attr['overallAlignment'],
			],
			' .spectra-pro-register-form__input-error'    => [
				'border-color' => $attr['errorFieldColor'] . '!important',
			],
			' .spectra-pro-register-form__field-error-message' => [
				'color'      => $attr['errorFieldColor'],
				'text-align' => $attr['overallAlignment'],
			],
			' .spectra-pro-register-form__field-success-message' => [
				'text-align' => $attr['overallAlignment'],
			],
		];

		// If hover blur or hover color are set, show the hover shadow.
		if ( ( $attr['boxShadowBlurHover'] !== '' ) && ( $attr['boxShadowBlurHover'] !== null ) || $attr['boxShadowColorHover'] !== '' ) {
			$selectors['.wp-block-spectra-pro-register:hover']['box-shadow'] = Helper::get_css_value( $attr['boxShadowHOffsetHover'], 'px' ) . ' ' . Helper::get_css_value( $attr['boxShadowVOffsetHover'], 'px' ) . ' ' . Helper::get_css_value( $attr['boxShadowBlurHover'], 'px' ) . ' ' . Helper::get_css_value( $attr['boxShadowSpreadHover'], 'px' ) . ' ' . $attr['boxShadowColorHover'] . ' ' . $box_shadow_position_css_hover;
		}

		$bg_obj_tablet      = array_merge(
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
		$form_bg_css_tablet = Helper::get_background_obj( $bg_obj_tablet );

		$t_selectors = [
			// google and facebook alignment.
			' .spectra-pro-register-form__social' => [
				'justify-content' => $postition_google_facebook_button_tablet,
				'align-items'     => $align_items_google_facebook_button_tablet,
				'flex-direction'  => $apply_stack_tablet,
				'gap'             => $attr['gapSocialLoginTablet'] . 'px',
			],

			// form.
			'.wp-block-spectra-pro-register'      => array_merge(
				$form_bg_css_tablet,
				[
					'width'          => Helper::get_css_value( $attr['formWidthTablet'], $attr['formWidthTypeTablet'] ),
					'padding-top'    => Helper::get_css_value( $attr['formTopPaddingTablet'], $attr['formPaddingUnitTablet'] ),
					'padding-right'  => Helper::get_css_value( $attr['formRightPaddingTablet'], $attr['formPaddingUnitTablet'] ),
					'padding-bottom' => Helper::get_css_value( $attr['formBottomPaddingTablet'], $attr['formPaddingUnitTablet'] ),
					'padding-left'   => Helper::get_css_value( $attr['formLeftPaddingTablet'], $attr['formPaddingUnitTablet'] ),
				],
				$form_border_tablet
			),
			'.wp-block-spectra-pro-register .spectra-pro-register-form' => [
				'gap' => Helper::get_css_value( $attr['rowGapTablet'], $attr['rowGapUnit'] ),
			],

			// input.
			' .spectra-pro-register-form .spectra-pro-register-form__field-wrapper>svg' => array_merge(
				[
					'height' => array_key_exists( 'border-top-width', $input_border_tablet ) && array_key_exists( 'border-bottom-width', $input_border_tablet ) ?
								'calc( 100% - ' . $input_border_tablet['border-top-width'] . ' - ' . $input_border_tablet['border-bottom-width'] . ' )'
								: '',
					'top'    => array_key_exists( 'border-top-width', $input_border_tablet ) ? $input_border_tablet['border-top-width'] : '',
					'bottom' => array_key_exists( 'border-bottom-width', $input_border_tablet ) ? $input_border_tablet['border-bottom-width'] : '',
					'left'   => array_key_exists( 'border-left-width', $input_border_tablet ) ? $input_border_tablet['border-left-width'] : '',
					'right'  => array_key_exists( 'border-right-width', $input_border_tablet ) ? $input_border_tablet['border-right-width'] : '',
				]
			),
			' .spectra-pro-register-form input::placeholder' => [
				'font-size' => Helper::get_css_value( $attr['inputFontSizeTablet'], $attr['inputFontSizeType'] ),
			],
			' .spectra-pro-register-form input:not([type="checkbox"])' => array_merge(
				[
					'padding-top'    => Helper::get_css_value( $attr['paddingFieldTopTablet'], $attr['paddingFieldUnitTablet'] ) . ' !important',
					'padding-bottom' => Helper::get_css_value( $attr['paddingFieldBottomTablet'], $attr['paddingFieldUnitTablet'] ) . ' !important',
					'padding-left'   => Helper::get_css_value( $attr['paddingFieldLeftTablet'], $attr['paddingFieldUnitTablet'] ) . ' !important',
					'padding-right'  => Helper::get_css_value( $attr['paddingFieldRightTablet'], $attr['paddingFieldUnitTablet'] ) . ' !important',
				],
				$input_border_tablet
			),

			// Login Information.
			'.wp-block-spectra-pro-register .spectra-pro-register-login-info' => [
				'font-size'   => Helper::get_css_value( $attr['labelFontSizeTablet'], $attr['labelFontSizeType'] ),
				'line-height' => Helper::get_css_value( $attr['labelLineHeightTablet'], $attr['labelLineHeightType'] ),
			],

			// label.
			' .spectra-pro-register-form label'   => [
				'font-size'     => Helper::get_css_value( $attr['labelFontSizeTablet'], $attr['labelFontSizeType'] ),
				'margin-bottom' => Helper::get_css_value( $attr['labelGapTablet'], $attr['labelGapUnit'] ),

			],

			' .spectra-pro-register-form .spectra-pro-register-form__terms-label' => [
				'font-size'      => Helper::get_css_value( $attr['labelFontSizeTablet'], $attr['labelFontSizeType'] ),
				'line-height'    => Helper::get_css_value( $attr['labelLineHeightTablet'], $attr['labelLineHeightType'] ),
				'letter-spacing' => Helper::get_css_value( $attr['labelLetterSpacingTablet'], $attr['labelLetterSpacingType'] ),
			],

			// register button.
			' .spectra-pro-register-form .spectra-pro-register-form__submit.wp-block-button__link' => array_merge(
				[
					'padding-top'    => Helper::get_css_value( $attr['registerPaddingBtnTopTablet'], $attr['registerTabletPaddingBtnUnit'] ),
					'padding-bottom' => Helper::get_css_value( $attr['registerPaddingBtnBottomTablet'], $attr['registerTabletPaddingBtnUnit'] ),
					'padding-left'   => Helper::get_css_value( $attr['registerPaddingBtnLeftTablet'], $attr['registerTabletPaddingBtnUnit'] ),
					'padding-right'  => Helper::get_css_value( $attr['registerPaddingBtnRightTablet'], $attr['registerTabletPaddingBtnUnit'] ),
					// alignment styling.
					'margin'         => $align_register_btn_margin_tablet,
					'column-gap'     => Helper::get_css_value( $attr['ctaIconSpaceTablet'], $attr['ctaIconSpaceType'] ),
				],
				$register_btn_border_tablet
			),

			' .spectra-pro-register-form .spectra-pro-register-form__submit.wp-block-button__link svg' => [
				'width'  => Helper::get_css_value( $attr['registerBtnFontSizeTablet'], $attr['registerBtnFontSizeType'] ),
				'height' => Helper::get_css_value( $attr['registerBtnFontSizeTablet'], $attr['registerBtnFontSizeType'] ),
			],

			// google button.
			' .spectra-pro-google-form .spectra-pro-google-form__submit' => array_merge(
				[
					'padding-top'    => Helper::get_css_value( $attr['googlePaddingBtnTopTablet'], $attr['googleTabletPaddingBtnUnit'] ),
					'padding-bottom' => Helper::get_css_value( $attr['googlePaddingBtnBottomTablet'], $attr['googleTabletPaddingBtnUnit'] ),
					'padding-left'   => Helper::get_css_value( $attr['googlePaddingBtnLeftTablet'], $attr['googleTabletPaddingBtnUnit'] ),
					'padding-right'  => Helper::get_css_value( $attr['googlePaddingBtnRightTablet'], $attr['googleTabletPaddingBtnUnit'] ),
				],
				$google_btn_border_tablet
			),

			// facebook button.
			' .spectra-pro-register-form__social .spectra-pro-register-form__social-facebook' => array_merge(
				[
					'padding-top'    => Helper::get_css_value( $attr['facebookPaddingBtnTopTablet'], $attr['facebookTabletPaddingBtnUnit'] ),
					'padding-bottom' => Helper::get_css_value( $attr['facebookPaddingBtnBottomTablet'], $attr['facebookTabletPaddingBtnUnit'] ),
					'padding-left'   => Helper::get_css_value( $attr['facebookPaddingBtnLeftTablet'], $attr['facebookTabletPaddingBtnUnit'] ),
					'padding-right'  => Helper::get_css_value( $attr['facebookPaddingBtnRightTablet'], $attr['facebookTabletPaddingBtnUnit'] ),
				],
				$facebook_btn_border_tablet
			),
		];

		$bg_obj_mobile        = array_merge(
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
		$mobile_bg_css_mobile = Helper::get_background_obj( $bg_obj_mobile );

		$m_selectors = [
			// google and facebook alignment.
			' .spectra-pro-register-form__social' => [
				'justify-content' => $postition_google_facebook_button_mobile,
				'align-items'     => $align_items_google_facebook_button_mobile,
				'flex-direction'  => $apply_stack_mobile,
				'gap'             => $attr['gapSocialLoginMobile'] . 'px',
				'flex-wrap'       => 'wrap',
			],

			// form.
			'.wp-block-spectra-pro-register'      => array_merge(
				$mobile_bg_css_mobile,
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
				$form_border_mobile
			),
			'.wp-block-spectra-pro-register .spectra-pro-register-form' => [
				'gap' => Helper::get_css_value( $attr['rowGapMobile'], $attr['rowGapUnit'] ),
			],

			// input.
			' .spectra-pro-register-form .spectra-pro-register-form__field-wrapper>svg' => array_merge(
				[
					'height' => array_key_exists( 'border-top-width', $input_border_mobile ) && array_key_exists( 'border-bottom-width', $input_border_mobile ) ?
								'calc( 100% - ' . $input_border_mobile['border-top-width'] . ' - ' . $input_border_mobile['border-bottom-width'] . ' )'
								: '',
					'top'    => array_key_exists( 'border-top-width', $input_border_mobile ) ? $input_border_mobile['border-top-width'] : '',
					'bottom' => array_key_exists( 'border-bottom-width', $input_border_mobile ) ? $input_border_mobile['border-bottom-width'] : '',
					'left'   => array_key_exists( 'border-left-width', $input_border_mobile ) ? $input_border_mobile['border-left-width'] : '',
					'right'  => array_key_exists( 'border-right-width', $input_border_mobile ) ? $input_border_mobile['border-right-width'] : '',
				]
			),
			' .spectra-pro-register-form input::placeholder' => [
				'font-size' => Helper::get_css_value( $attr['inputFontSizeMobile'], $attr['inputFontSizeType'] ),
			],
			' .spectra-pro-register-form input:not([type="checkbox"])' => array_merge(
				[
					'padding-top'    => Helper::get_css_value( $attr['paddingFieldTopMobile'], $attr['paddingFieldUnitmobile'] ) . ' !important',
					'padding-bottom' => Helper::get_css_value( $attr['paddingFieldBottomMobile'], $attr['paddingFieldUnitmobile'] ) . ' !important',
					'padding-left'   => Helper::get_css_value( $attr['paddingFieldLeftMobile'], $attr['paddingFieldUnitmobile'] ) . ' !important',
					'padding-right'  => Helper::get_css_value( $attr['paddingFieldRightMobile'], $attr['paddingFieldUnitmobile'] ) . ' !important',
				],
				$input_border_tablet
			),

			// Login Information.
			'.wp-block-spectra-pro-register .spectra-pro-register-login-info' => [
				'font-size'   => Helper::get_css_value( $attr['labelFontSizeMobile'], $attr['labelFontSizeType'] ),
				'line-height' => Helper::get_css_value( $attr['labelLineHeightMobile'], $attr['labelLineHeightType'] ),
			],

			// heading.
			'.wp-block-spectra-pro-register .spectra-pro-register-form__heading' => [
				'font-size'      => Helper::get_css_value( $attr['headingFontSizeMobile'], $attr['headingFontSizeType'] ),
				'line-height'    => Helper::get_css_value( $attr['headingLineHeightMobile'], $attr['headingLineHeightType'] ),
				'letter-spacing' => Helper::get_css_value( $attr['headingLetterSpacingMobile'], $attr['headingLetterSpacingType'] ),
				'margin-top'     => Helper::get_css_value( $attr['headingTopMarginMobile'], $attr['headingMarginUnitMobile'] ),
				'margin-right'   => Helper::get_css_value( $attr['headingRightMarginMobile'], $attr['headingMarginUnitMobile'] ),
				'margin-bottom'  => Helper::get_css_value( $attr['headingBottomMarginMobile'], $attr['headingMarginUnitMobile'] ),
				'margin-left'    => Helper::get_css_value( $attr['headingLeftMarginMobile'], $attr['headingMarginUnitMobile'] ),
			],

			// label.
			' .spectra-pro-register-form label'   => [
				'font-size'     => Helper::get_css_value( $attr['labelFontSizeMobile'], $attr['labelFontSizeType'] ),
				'margin-bottom' => Helper::get_css_value( $attr['labelGapMobile'], $attr['labelGapUnit'] ),
			],

			' .spectra-pro-register-form .spectra-pro-register-form__terms-label' => [
				'font-size'      => Helper::get_css_value( $attr['labelFontSizeMobile'], $attr['labelFontSizeType'] ),
				'line-height'    => Helper::get_css_value( $attr['labelLineHeightMobile'], $attr['labelLineHeightType'] ),
				'letter-spacing' => Helper::get_css_value( $attr['labelLetterSpacingMobile'], $attr['labelLetterSpacingType'] ),
			],

			// register button.
			' .spectra-pro-register-form .spectra-pro-register-form__submit.wp-block-button__link' => array_merge(
				[
					'padding-top'    => Helper::get_css_value( $attr['registerPaddingBtnTopMobile'], $attr['registerMobilePaddingBtnUnit'] ),
					'padding-bottom' => Helper::get_css_value( $attr['registerPaddingBtnBottomMobile'], $attr['registerMobilePaddingBtnUnit'] ),
					'padding-left'   => Helper::get_css_value( $attr['registerPaddingBtnLeftMobile'], $attr['registerMobilePaddingBtnUnit'] ),
					'padding-right'  => Helper::get_css_value( $attr['registerPaddingBtnRightMobile'], $attr['registerMobilePaddingBtnUnit'] ),

					// alignmnet style.
					'margin'         => $align_register_btn_margin_mobile,
					'column-gap'     => Helper::get_css_value( $attr['ctaIconSpaceMobile'], $attr['ctaIconSpaceType'] ),
				],
				$register_btn_border_mobile
			),

			' .spectra-pro-register-form .spectra-pro-register-form__submit.wp-block-button__link svg' => [
				'width'  => Helper::get_css_value( $attr['registerBtnFontSizeMobile'], $attr['registerBtnFontSizeType'] ),
				'height' => Helper::get_css_value( $attr['registerBtnFontSizeMobile'], $attr['registerBtnFontSizeType'] ),
			],

			// google button.
			' .spectra-pro-google-form .spectra-pro-google-form__submit' => array_merge(
				[
					'padding-top'    => Helper::get_css_value( $attr['googlePaddingBtnTopMobile'], $attr['googleMobilePaddingBtnUnit'] ),
					'padding-bottom' => Helper::get_css_value( $attr['googlePaddingBtnBottomMobile'], $attr['googleMobilePaddingBtnUnit'] ),
					'padding-left'   => Helper::get_css_value( $attr['googlePaddingBtnLeftMobile'], $attr['googleMobilePaddingBtnUnit'] ),
					'padding-right'  => Helper::get_css_value( $attr['googlePaddingBtnRightMobile'], $attr['googleMobilePaddingBtnUnit'] ),
				],
				$google_btn_border_mobile
			),

			// facebook button.
			' .spectra-pro-register-form__social .spectra-pro-register-form__social-facebook' => array_merge(
				[
					'padding-top'    => Helper::get_css_value( $attr['facebookPaddingBtnTopMobile'], $attr['facebookMobilePaddingBtnUnit'] ),
					'padding-bottom' => Helper::get_css_value( $attr['facebookPaddingBtnBottomMobile'], $attr['facebookMobilePaddingBtnUnit'] ),
					'padding-left'   => Helper::get_css_value( $attr['facebookPaddingBtnLeftMobile'], $attr['facebookMobilePaddingBtnUnit'] ),
					'padding-right'  => Helper::get_css_value( $attr['facebookPaddingBtnRightMobile'], $attr['facebookMobilePaddingBtnUnit'] ),
				],
				$facebook_btn_border_mobile
			),
		];

		if ( $attr['alignRegisterBtn'] === 'full' ) {
			$selectors[' .spectra-pro-register-form .spectra-pro-register-form__submit.wp-block-button__link']['width'] = '100%';
		}
		if ( $attr['alignRegisterBtnTablet'] === 'full' ) {
			$t_selectors[' .spectra-pro-register-form .spectra-pro-register-form__submit.wp-block-button__link']['width'] = '100%';
		}
		if ( $attr['alignRegisterBtnMobile'] === 'full' ) {
			$m_selectors[' .spectra-pro-register-form .spectra-pro-register-form__submit.wp-block-button__link']['width'] = '100%';
		}

		// Tablet heading styles.
		$t_selectors['.wp-block-spectra-pro-register .spectra-pro-register-form__heading'] = [
			'font-size'      => Helper::get_css_value( $attr['headingFontSizeTablet'], $attr['headingFontSizeType'] ),
			'line-height'    => Helper::get_css_value( $attr['headingLineHeightTablet'], $attr['headingLineHeightType'] ),
			'letter-spacing' => Helper::get_css_value( $attr['headingLetterSpacingTablet'], $attr['headingLetterSpacingType'] ),
			'margin-top'     => Helper::get_css_value( $attr['headingTopMarginTablet'], $attr['headingMarginUnitTablet'] ),
			'margin-right'   => Helper::get_css_value( $attr['headingRightMarginTablet'], $attr['headingMarginUnitTablet'] ),
			'margin-bottom'  => Helper::get_css_value( $attr['headingBottomMarginTablet'], $attr['headingMarginUnitTablet'] ),
			'margin-left'    => Helper::get_css_value( $attr['headingLeftMarginTablet'], $attr['headingMarginUnitTablet'] ),
		];

		$combined_selectors = [
			'desktop' => $selectors,
			'tablet'  => $t_selectors,
			'mobile'  => $m_selectors,
		];

		$base_selector      = '.uagb-block-';
		$combined_selectors = Helper::get_typography_css( $attr, 'input', ' .spectra-pro-register-form input::placeholder', $combined_selectors );
		$combined_selectors = Helper::get_typography_css( $attr, 'input', ' .spectra-pro-register-form input:not([type="checkbox"])', $combined_selectors );
		$combined_selectors = Helper::get_typography_css( $attr, 'heading', '.wp-block-spectra-pro-register .spectra-pro-register-form__heading', $combined_selectors );
		$combined_selectors = Helper::get_typography_css( $attr, 'registerBtn', ' .spectra-pro-register-form .spectra-pro-register-form__submit.wp-block-button__link', $combined_selectors );
		$combined_selectors = Helper::get_typography_css( $attr, 'googleBtn', ' .spectra-pro-register-form__social .spectra-pro-register-form__social-google', $combined_selectors );
		$combined_selectors = Helper::get_typography_css( $attr, 'facebookBtn', ' .spectra-pro-register-form__social .spectra-pro-register-form__social-facebook', $combined_selectors );
		$combined_selectors = Helper::get_typography_css( $attr, 'label', ' .spectra-pro-register-form label', $combined_selectors );

		return Helper::generate_all_css( $combined_selectors, $base_selector . $id );
	}
}

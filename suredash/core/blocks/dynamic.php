<?php
/**
 * SureDashboard Blocks Initialize.
 *
 * @package SureDash
 */

namespace SureDashboard\Core\Blocks;

use SureDashboard\Inc\Traits\Get_Instance;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Class Dynamic.
 */
class Dynamic {
	use Get_Instance;

	/**
	 * Stylesheet
	 *
	 * @since 1.0.0
	 * @var string $stylesheet
	 */
	public static $stylesheet;

	/**
	 * Script
	 *
	 * @since 1.0.0
	 * @var string $script
	 */
	public static $script;

	/**
	 * Page Blocks Variable
	 *
	 * @since 1.0.0
	 * @var array<string, mixed> $page_blocks
	 */
	public static $page_blocks;

	/**
	 * Member Variable
	 *
	 * @since 1.0.0
	 * @var array<mixed> instance
	 */
	public static $block_list;

	/**
	 * Pfd Block Flag
	 *
	 * @since 1.0.0
	 * @var bool $suredash_flag
	 */
	public static $suredash_flag = false;

	/**
	 * Current Block List
	 *
	 * @since 1.0.0
	 * @var array<mixed> $current_block_list
	 */
	public static $current_block_list = [];

	/**
	 * Constructor
	 *
	 * @since 1.0.0
	 */
	public function __construct() {
		add_action( 'wp', [ $this, 'wp_action' ], 10 );

		add_action( 'wp_head', [ $this, 'print_stylesheet' ], 80 );
		add_action( 'suredash_footer', [ $this, 'print_script' ], 1000 );
	}

	/**
	 * WP Actions.
	 *
	 * @since 1.0.0
	 */
	public function wp_action(): void {
		$this->generate_assets();
	}

	/**
	 * Generates stylesheet and appends in head tag.
	 *
	 * @since 1.0.0
	 */
	public function generate_assets(): void {
		global $post;
		$this_post = $post;

		if ( ! is_object( $this_post ) ) {
			return;
		}

		/**
		 * Filters the post to build stylesheet for.
		 *
		 * @param \WP_Post $this_post The global post.
		 */
		$this_post = apply_filters( 'suredashboard_post_for_stylesheet', $this_post ); // @phpstan-ignore-line

		$this->get_generated_stylesheet( $this_post );
	}

	/**
	 * Print the Script in footer.
	 */
	public function print_script(): void {
		if ( is_null( self::$script ) || self::$script === '' ) {
			return;
		}

		ob_start();
		?>
			<script type="text/javascript" id="sd-script-frontend">
				<?php
					echo self::$script; //phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
				?>
			</script>
		<?php
		ob_end_flush();
	}

	/**
	 * Print the Stylesheet in header.
	 */
	public function print_stylesheet(): void {
		if ( is_null( self::$stylesheet ) || self::$stylesheet === '' ) {
			return;
		}

		ob_start();
		?>
			<style id="sd-style-frontend">
				<?php
					echo self::$stylesheet; //phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
				?>
			</style>
		<?php
		ob_end_flush();
	}

	/**
	 * Parse Gutenberg Block.
	 *
	 * @param string $content the content string.
	 * @since 1.0.0
	 *
	 * @return array<int|string, array<string, array<mixed>|string|null>>
	 */
	public function parse( $content ) {
		return parse_blocks( $content );
	}

	/**
	 * Generates stylesheet in loop.
	 *
	 * @param object $this_post Current Post Object.
	 * @since 1.0.0
	 */
	public function get_generated_stylesheet( $this_post ): void {
		if ( is_object( $this_post ) && isset( $this_post->ID ) && has_blocks( $this_post->ID ) && isset( $this_post->post_content ) ) {
			$blocks = $this->parse( $this_post->post_content );

			self::$page_blocks = $blocks; // @phpstan-ignore-line

			if ( ! is_array( $blocks ) || empty( $blocks ) ) {
				return;
			}

			$assets = $this->get_assets( $blocks );

			self::$stylesheet .= $assets['css'];
			self::$script     .= $assets['js'];
		}
	}

	/**
	 * Generates stylesheet for reusable blocks.
	 *
	 * @param array<mixed> $blocks Blocks array.
	 * @since 1.0.0
	 *
	 * @return array<string, string>
	 */
	public function get_assets( $blocks ) {
		$desktop = '';
		$tablet  = '';
		$mobile  = '';

		$tab_styling_css = '';
		$mob_styling_css = '';

		$js = '';

		foreach ( $blocks as $block ) {
			if ( is_array( $block ) ) {
				if ( $block['blockName'] === '' ) {
					continue;
				}
				if ( $block['blockName'] === 'core/block' ) {
					$id = $block['attrs']['ref'] ?? 0;

					if ( $id ) {
						$content = get_post_field( 'post_content', $id );
						$content = is_string( $content ) ? $content : '';

						$reusable_blocks = $this->parse( $content );

						$assets = $this->get_assets( $reusable_blocks );

						self::$stylesheet .= $assets['css'];
						self::$script     .= $assets['js'];
					}
				} else {
					$block_assets = $this->get_block_css_and_js( $block );
					// Get CSS for the Block.
					$css = $block_assets['css'];

					if ( is_array( $css ) && isset( $css['desktop'] ) ) {
						$desktop .= $css['desktop'];
						$tablet  .= $css['tablet'];
						$mobile  .= $css['mobile'];
					}

					$js .= $block_assets['js'];
				}
			}
		}

		if ( ! empty( $tablet ) ) {
			$tab_styling_css .= '@media only screen and (max-width: ' . SUREDASHBOARD_TABLET_BREAKPOINT . 'px) {';
			$tab_styling_css .= $tablet;
			$tab_styling_css .= '}';
		}

		if ( ! empty( $mobile ) ) {
			$mob_styling_css .= '@media only screen and (max-width: ' . SUREDASHBOARD_MOBILE_BREAKPOINT . 'px) {';
			$mob_styling_css .= $mobile;
			$mob_styling_css .= '}';
		}

		return [
			'css' => $desktop . $tab_styling_css . $mob_styling_css,
			'js'  => $js,
		];
	}

	/**
	 * Generates CSS recursively.
	 *
	 * @param array<mixed> $block The block object.
	 * @since 1.0.0
	 *
	 * @return array<string, array<string, string>>
	 */
	public function get_block_css_and_js( $block ) {
		$block = (array) $block;

		$name       = $block['blockName'];
		$block_attr = [];
		$css        = [];
		$js         = '';
		$block_id   = '';

		if ( isset( $name ) ) {
			$block_attr = [];
			if ( isset( $block['attrs'] ) && is_array( $block['attrs'] ) ) {
				$block_attr = $block['attrs'];
				if ( isset( $block_attr['block_id'] ) ) {
					$block_id = $block_attr['block_id'];
				}
			}

			self::$current_block_list[] = $name;

			if ( strpos( $name, 'suredash/' ) !== false ) {
				self::$suredash_flag = true;
			}

			wp_enqueue_script( 'wp-api-fetch' );

			switch ( $name ) {
				case 'suredash/login':
					wp_enqueue_script( 'portal-login-block', SUREDASHBOARD_JS_ASSETS_FOLDER . 'login' . SUREDASHBOARD_JS_SUFFIX, [], SUREDASHBOARD_VER, true );
					wp_set_script_translations( 'portal-login-block', 'suredash', SUREDASHBOARD_DIR . 'languages' );

					if ( method_exists( Login::get_instance(), 'get_dynamic_block_css' ) ) {
						$css = Login::get_instance()->get_dynamic_block_css( $block_attr, $block_id );
					}

					/**
					 * Fires an action to enqueue additional scripts or styles for the search block.
					 *
					 * @param array  $block_attr The block attributes containing font settings.
					 * @param string $block_id   The unique ID for the block.
					 */
					do_action( 'suredash_enqueue_login_block_scripts', $block_attr, $block_id );
					break;

				case 'suredash/register':
					wp_enqueue_script( 'portal-register-block', SUREDASHBOARD_JS_ASSETS_FOLDER . 'register' . SUREDASHBOARD_JS_SUFFIX, [ 'password-strength-meter' ], SUREDASHBOARD_VER, true );

					if ( method_exists( Register::get_instance(), 'get_dynamic_block_css' ) ) {
						$css = Register::get_instance()->get_dynamic_block_css( $block_attr, $block_id );
					}

					/**
					 * Fires an action to enqueue additional scripts or styles for the categories block.
					 *
					 * @param array  $block_attr The block attributes containing font settings.
					 * @param string $block_id   The unique ID for the block.
					 */
					do_action( 'suredash_enqueue_register_block_scripts', $block_attr, $block_id );
					break;

				default:
					// If any other block is loading from 'suredash' then it seems to be portal block, then load the necessary assets.
					if ( self::$suredash_flag ) {
						do_action( 'suredash_enqueue_scripts' );
					}
					break;
			}

			if ( isset( $block['innerBlocks'] ) ) {
				foreach ( $block['innerBlocks'] as $inner_block ) {
					if ( $inner_block['blockName'] === 'core/block' ) {
						$id = $inner_block['attrs']['ref'] ?? 0;

						if ( $id ) {
							$content = get_post_field( 'post_content', $id );
							$content = is_string( $content ) ? $content : '';

							$reusable_blocks = $this->parse( $content );

							$assets = $this->get_assets( $reusable_blocks );

							self::$stylesheet .= $assets['css'];
							self::$script     .= $assets['js'];
						}
					} else {
						// Get CSS for the Block.
						$inner_assets    = $this->get_block_css_and_js( $inner_block );
						$inner_block_css = $inner_assets['css'];

						$css_desktop = ( $css['desktop'] ?? '' );
						$css_tablet  = ( $css['tablet'] ?? '' );
						$css_mobile  = ( $css['mobile'] ?? '' );

						if ( ! empty( $inner_block_css['desktop'] ) ) {
							$css['desktop'] = $css_desktop . $inner_block_css['desktop'];
							$css['tablet']  = $css_tablet . $inner_block_css['tablet'];
							$css['mobile']  = $css_mobile . $inner_block_css['mobile'];
						}

						$js .= $inner_assets['js'];
					}
				}
			}

			self::$current_block_list = array_unique( self::$current_block_list );
		}

		return [
			'css' => $css,
			'js'  => $js,
		];
	}
}

<?php
/**
 * SureDash Blocks Initialize.
 *
 * @package SureDash
 */

namespace SureDashboard\Core\Blocks;

use SureDashboard\Core\Assets;
use SureDashboard\Inc\Templator\Block_Supports_Extended;
use SureDashboard\Inc\Traits\Get_Instance;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Class Do_Blocks.
 */
class Do_Blocks {
	use Get_Instance;

	/**
	 * Block patterns to register.
	 *
	 * @var array<mixed>
	 * @since 0.0.6
	 */
	protected $patterns = [];

	/**
	 * Block patterns categories to register.
	 *
	 * @var array<mixed>
	 * @since 0.0.6
	 */
	protected $categories = [];

	/**
	 * Constructor
	 *
	 * @since 1.0.0
	 */
	public function __construct() {
		$this->categories = [
			'suredash_portal' => [ 'label' => __( 'Community Portal', 'suredash' ) ],
			'suredash_auth'   => [ 'label' => 'SureDash' ],
		];

		$this->patterns = [
			'default',
			'modern',
			'login-centered-column',
			'register-centered-column',
			'login-two-column',
			'register-two-column',
		];

		add_action( 'init', [ $this, 'initialize_blocks' ] );
		add_action( 'init', [ $this, 'register_patterns_categories' ], 9 );

		$this->init_blocks_setup();
	}

	/**
	 * Init Hooks.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function initialize_blocks(): void {
		$this->register_core_blocks();
		$this->register_interactive_blocks();
	}

	/**
	 * Do_Blocks routes.
	 */
	public function register_core_blocks(): void {
		$blocks_namespace = 'SureDashboard\Core\Blocks\\';

		$controllers = [
			$blocks_namespace . 'Login',
			$blocks_namespace . 'Register',
		];

		foreach ( $controllers as $controller ) {
			call_user_func( [ $controller::get_instance(), 'register_blocks' ] );
		}
	}

	/**
	 * Registers the block using the metadata loaded from the `block.json` file.
	 * Behind the scenes, it registers also all assets so they can be enqueued
	 * through the block editor in the corresponding context.
	 *
	 * @since 0.0.6
	 * @see https://developer.wordpress.org/reference/functions/register_block_type/
	 */
	public function register_interactive_blocks(): void {

		if ( ! function_exists( 'register_block_type_from_metadata' ) ) {
			return;
		}

		Block_Supports_Extended::get_instance()->register(
			'color',
			'spaceactivetext',
			[
				'label'    => __( 'Active Space Text', 'suredash' ),
				'property' => 'color',
				'selector' => '.%1$s ul .portal-aside-group-link.active, .%1$s ul .portal-aside-group-link.active *',
				'blocks'   => [
					'suredash/navigation',
				],
			]
		);

		Block_Supports_Extended::get_instance()->register(
			'color',
			'spaceactivebackground',
			[
				'label'    => __( 'Active Space Background', 'suredash' ),
				'property' => 'background',
				'selector' => '.%1$s .portal-aside-group-link.active, .%1$s .portal-aside-group-link.active *',
				'blocks'   => [
					'suredash/navigation',
				],
			]
		);

		Block_Supports_Extended::get_instance()->register(
			'color',
			'spacegrouptext',
			[
				'label'    => __( 'Space Group Text', 'suredash' ),
				'property' => 'color',
				'selector' => '.%1$s .portal-aside-group-header .portal-aside-group-title',
				'blocks'   => [
					'suredash/navigation',
				],
			]
		);

		Block_Supports_Extended::get_instance()->register(
			'color',
			'spacegroupbackground',
			[
				'label'    => __( 'Space Group Background', 'suredash' ),
				'property' => 'background',
				'selector' => '.%1$s .portal-aside-group-header',
				'blocks'   => [
					'suredash/navigation',
				],
			]
		);

		Block_Supports_Extended::get_instance()->register(
			'color',
			'iconcolor',
			[
				'label'    => __( 'Icon Color', 'suredash' ),
				'property' => 'stroke',
				'selector' => '.%1$s .portal-notification-trigger svg',
				'blocks'   => [
					'suredash/notification',
				],
			]
		);

		$block_entries = glob( SUREDASHBOARD_INTERACTIVE_BLOCKS_DIR . '**/block.json' );
		if ( is_array( $block_entries ) && ! empty( $block_entries ) ) {
			foreach ( $block_entries as $file ) {
				register_block_type_from_metadata( dirname( $file ) );
			}
		}
	}

	/**
	 * Register block patterns and categories.
	 *
	 * @since 0.0.6
	 */
	public function register_patterns_categories(): void {
		$this->register_categories();
		$this->register_patterns();
	}

	/**
	 * Register block pattern categories.
	 *
	 * @return void
	 * @since 0.0.6
	 */
	public function register_categories(): void {
		/**
		 * Filters the block pattern categories.
		 *
		 * @param array<array> $categories {
		 *     An associative array of block pattern categories, keyed by category name.
		 *
		 *     @type array[] $properties {
		 *         An array of block category properties.
		 *
		 *         @type string $label A human-readable label for the pattern category.
		 *     }
		 * }
		 */
		$this->categories = apply_filters( 'suredash_blocks_pattern_categories', $this->categories );

		foreach ( $this->categories as $name => $properties ) {
			if ( ! \WP_Block_Pattern_Categories_Registry::get_instance()->is_registered( $name ) ) {
				register_block_pattern_category( $name, $properties );
			}
		}
	}

	/**
	 * Register our block patterns.
	 *
	 * @return void
	 * @since 0.0.6
	 */
	public function register_patterns(): void {
		// register the block patterns from patterns directory.
		$patterns = glob( plugin_dir_path( SUREDASHBOARD_FILE ) . 'templates/patterns/*.php' );

		if ( ! is_array( $patterns ) ) {
			return;
		}

		// sort by priority key.
		usort(
			$patterns,
			static function ( $a, $b ) {
				$a = require $a;
				$b = require $b;
				return ( $a['priority'] ?? 0 ) <=> ( $b['priority'] ?? 0 );
			}
		);

		foreach ( $patterns as $pattern_file ) {
			register_block_pattern(
				'suredash-' . basename( $pattern_file, '.php' ),
				require $pattern_file
			);
		}
	}

	/**
	 * Init Blocks Setup.
	 *
	 * @since 0.0.6
	 * @return void
	 */
	public function init_blocks_setup(): void {
		/**
		 * Use our controller view pattern.
		 */
		add_filter(
			'block_type_metadata_settings',
			static function ( $settings, $metadata ) {
				// if there is a controller file, use it.
				$controller_path = wp_normalize_path(
					strval(
						realpath(
							dirname( $metadata['file'] ) . '/' .
							remove_block_asset_path_prefix( 'file:./controller.php' )
						)
					)
				);

				if ( ! file_exists( $controller_path ) ) {
					return $settings;
				}

				/**
				 * Renders the block on the server.
				 *
				 * @since 6.1.0
				 *
				 * @param array    $attributes Block attributes.
				 * @param string   $content    Block default content.
				 * @param WP_Block $block      Block instance.
				 *
				 * @return string Returns the block content.
				 */
				$settings['render_callback'] = static function ( $attributes, $content, $block ) use ( $controller_path, $metadata ) {
					$view = require $controller_path;

					if ( ! $view ) {
						return '';
					}

					// if not using 'file:', then it's content output.
					$view_path = remove_block_asset_path_prefix( $view );
					if ( $view_path === $view ) {
						return $view;
					}

					$template_path = wp_normalize_path(
						strval(
							realpath(
								dirname( $metadata['file'] ) . '/' .
								remove_block_asset_path_prefix( $view )
							)
						)
					);

					ob_start();
					require $template_path;
					return ob_get_clean();
				};

				return $settings;
			},
			11,
			2
		);

		$action = is_admin() ? 'init' : 'wp';

		/**
		 * Register all css at src/styles folder.
		 */
		add_action(
			$action,
			static function (): void {
				// Only register styles on SureDash frontend pages or auth pages.
				if ( ! is_admin() && ! suredash_is_auth_page() ) {
					return;
				}

				$dir_suffix = is_rtl() ? '-rtl' : '';
				$css_files  = glob( SUREDASHBOARD_DIR . 'assets/css/' . ( SUREDASHBOARD_DEVELOPMENT_MODE ? 'unminified/' : 'minified/' ) . '*' . SUREDASHBOARD_CSS_SUFFIX ) ?? []; // @phpstan-ignore-line

				if ( is_array( $css_files ) && ! empty( $css_files ) ) {
					foreach ( $css_files as $css_file ) {
						$css_filename = SUREDASHBOARD_DEVELOPMENT_MODE ? basename( $css_file, '.css' ) : basename( $css_file, '.min.css' ); // @phpstan-ignore-line

						// Strip the 'rtl' suffix from the file name if the site is not RTL.
						$plain_name = str_replace( '-rtl', '', $css_filename );

						// Extract the file name without the extension and prepend with 'portal-'.
						$handle = 'portal-' . $plain_name;

						wp_register_style(
							$handle,
							SUREDASHBOARD_CSS_ASSETS_FOLDER . $plain_name . $dir_suffix . SUREDASHBOARD_CSS_SUFFIX,
							[],
							SUREDASHBOARD_VER
						);
					}
				}

				wp_add_inline_style( 'portal-global', Assets::get_global_css() );
			}
		);
	}
}

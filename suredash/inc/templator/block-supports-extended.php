<?php
/**
 * Portals block template service.
 *
 * @credits https://github.com/humanmade/block-supports-extended
 * @package SureDash
 */

namespace SureDashboard\Inc\Templator;

use SureDashboard\Inc\Traits\Get_Instance;
use WP_Block_Type_Registry;
use WP_HTML_Tag_Processor;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * The block extended support.
 */
class Block_Supports_Extended {
	use Get_Instance;

	/**
	 * BlockTemplatesService constructor.
	 */
	public function __construct() {
		add_action( 'enqueue_block_editor_assets', [ $this, 'enqueue_block_editor_assets' ] );
		add_filter( 'render_block', [ $this, 'render_elements_color_support' ], 10, 2 );
		add_filter( 'pre_render_block', [ $this, 'render_elements_support_styles' ], 10, 2 );
		add_filter( 'block_type_metadata', [ $this, 'filter_metadata_registration' ] );
	}

	/**
	 * Queue up the client side UI script.
	 * @since 0.0.6
	 */
	public function enqueue_block_editor_assets(): void {
		global $block_supports_extended;

		$build_path        = SUREDASHBOARD_URL . 'assets/build/';
		$script_asset_path = SUREDASHBOARD_DIR . 'assets/build/extended-block-supports.asset.php';

		$script_info = file_exists( $script_asset_path )
			? include $script_asset_path
			: [
				'dependencies' => [],
				'version'      => SUREDASHBOARD_VER,
			];

		wp_enqueue_script(
			'suredash-block-supports-extended',
			esc_url( $build_path . 'extended-block-supports.js' ),
			$script_info['dependencies'] ?? [],
			SUREDASHBOARD_VER,
			[
				'strategy' => 'async',
			]
		);

		wp_localize_script(
			'suredash-block-supports-extended',
			'blockSupportsExtended',
			(object) ( $block_supports_extended ?? [] )
		);
	}

	/**
	 * Register a new block supports sub-feature.
	 *
	 * @param string $feature Primary feature name e.g. color, border, typography.
	 * @param string $name Subfeature name.
	 * @param array $args {
	 *     Optional. An array of options. Default empty array.
	 *
	 *     @type string $label    A human readable label for the field.
	 *     @type string $selector The CSS selector, use %s or %1$s for the generated block class name.
	 *     @type string $default  A default value for the CSS property to use if none is set.
	 *     @type string $property The CSS property being set, e.g. text, background or gradient for color sub features.
	 *     @type array $blocks    A list of default blocks that support this sub feature.
	 * }
	 * @return void
	 */
	public function register( string $feature, string $name, array $args = [] ): void {
		global $block_supports_extended;

		if ( empty( $block_supports_extended ) ) {
			$block_supports_extended = [ $feature => [] ];
		}

		if ( ! isset( $block_supports_extended[ $feature ] ) ) {
			$block_supports_extended[ $feature ] = [];
		}

		$key = $this->_to_camelcase( $name, true );

		$block_supports_extended[ $feature ][ $key ] = wp_parse_args( $args, [
			'name' => $name,
			'label' => $name,
			'selector' => '.%s',
			'default' => '',
			'property' => 'text',
			'blocks' => [],
		] );
	}

	/**
	 * Add support for a custom feature to a block / blocks.
	 *
	 * @param string $feature The top level feature name.
	 * @param string $name The sub-feature property name.
	 * @param array $blocks The blocks to add support to.
	 * @return void
	 */
	public function add_support( string $feature, string $name, array $blocks ): void {
		global $block_supports_extended;

		$key = $this->_to_camelcase( $name, true );

		if ( ! isset( $block_supports_extended[ $feature ][ $key ] ) ) {
			return;
		}

		$block_supports_extended[ $feature ][ $key ]['blocks'] = array_merge(
			$block_supports_extended[ $feature ][ $key ]['blocks'] ?? [],
			$blocks
		);

		$block_supports_extended[ $feature ][ $key ]['blocks'] = array_unique(
			$block_supports_extended[ $feature ][ $key ]['blocks']
		);
	}

	/**
	 * Convert a string to camel case.
	 *
	 * @param string $str
	 * @param bool $lower_case_first
	 * @return string
	 */
	public function _to_camelcase( string $str, bool $lower_case_first = false ): string {
		$camel_case = implode( '', array_map( static function ( $part ) {
			return ucwords( $part );
		}, explode( '-', sanitize_title_with_dashes( $str ) ) ) );

		if ( $lower_case_first ) {
			$camel_case = lcfirst( $camel_case );
		}

		return $camel_case;
	}

	/**
	 * Updates the block content with elements class names.
	 *
	 * @since 5.8.0
	 * @access private
	 *
	 * @param string $block_content Rendered block content.
	 * @param array  $block         Block object.
	 * @return string Filtered block content.
	 */
	public function render_elements_color_support( $block_content, $block ) {
		global $block_supports_extended;

		if ( ! $block_content ) {
			return $block_content;
		}

		if ( ! isset( $block_supports_extended['color'] ) ) {
			$block_supports_extended['color'] = [];
		}

		$block_type = WP_Block_Type_Registry::get_instance()->get_registered( $block['blockName'] );

		$color = null;

		foreach ( $block_supports_extended['color'] as $name => $settings ) {
			if ( ! block_has_support( $block_type, [ 'color', $name ] ) ) {
				continue;
			}

			$skip_serialization = wp_should_skip_block_supports_serialization( $block_type, 'color', $name );

			if ( $skip_serialization ) {
				continue;
			}

			if ( ! empty( $block['attrs'] ) ) {
				$property = $settings['property'] ?? 'text';
				$color = _wp_array_get( $block['attrs'], [ 'style', 'elements', $name, 'color', $property ], null );
			}
		}

		if ( $color === null ) {
			return $block_content;
		}

		// Like the layout hook this assumes the hook only applies to blocks with a single wrapper.
		// Add the class name to the first element, presuming it's the wrapper, if it exists.
		$tags = new WP_HTML_Tag_Processor( $block_content );
		if ( $tags->next_tag() ) {
			$tags->add_class( wp_get_elements_class_name( $block ) );
		}

		return $tags->get_updated_html();
	}

	/**
	 * Renders the elements stylesheet.
	 *
	 * In the case of nested blocks we want the parent element styles to be rendered before their descendants.
	 * This solves the issue of an element (e.g.: link color) being styled in both the parent and a descendant:
	 * we want the descendant style to take priority, and this is done by loading it after, in DOM order.
	 *
	 * @since 6.0.0
	 * @since 6.1.0 Implemented the style engine to generate CSS and classnames.
	 * @access private
	 *
	 * @param string|null $pre_render The pre-rendered content. Default null.
	 * @param array       $block      The block being rendered.
	 * @return null
	 */
	public function render_elements_support_styles( $pre_render, $block ) {
		global $block_supports_extended;

		if ( ! isset( $block_supports_extended['color'] ) ) {
			$block_supports_extended['color'] = [];
		}

		$block_type = WP_Block_Type_Registry::get_instance()->get_registered( $block['blockName'] );

		foreach ( $block_supports_extended as $feature => $properties ) {
			foreach ( $properties as $name => $settings ) {

				if ( ! block_has_support( $block_type, [ $feature, $name ] ) ) {
					continue;
				}

				$element_block_styles = $block['attrs']['style']['elements'] ?? null;
				$skip_serialization = wp_should_skip_block_supports_serialization( $block_type, $feature, $name );

				if ( $skip_serialization ) {
					continue;
				}

				$class_name = wp_get_elements_class_name( $block );
				$block_styles = $element_block_styles[ $name ] ?? null;

				wp_style_engine_get_styles(
					$block_styles,
					[
						// Make the selector extra specific to support having a default value in the main stylesheet.
						'selector' => sprintf( $settings['selector'], $class_name ),
						'context'  => 'block-supports',
					]
				);
			}
		}

		return $pre_render;
	}

	/**
	 * Modify the block registration data to add support for custom features.
	 *
	 * @param array $metadata The block.json regstration metadata.
	 * @return array
	 */
	public function filter_metadata_registration( $metadata ) {
		global $block_supports_extended;

		if ( empty( $block_supports_extended ) ) {
			$block_supports_extended = [];
		}

		foreach ( $block_supports_extended as $feature => $properties ) {

			foreach ( $properties as $name => $settings ) {
				if ( ! in_array( $metadata['name'], $settings['blocks'] ?? [], true ) ) {
					continue;
				}

				if ( ! is_array( $metadata['supports'][ $feature ] ?? null ) ) {
					continue;
				}

				if ( ! isset( $metadata['supports'] ) ) {
					$metadata['supports'] = [ $feature => [] ];
				}

				if ( ! isset( $metadata['supports'][ $feature ] ) ) {
					$metadata['supports'][ $feature ] = [];
				}

				if ( ! is_array( $metadata['supports'][ $feature ] ) ) {
					$metadata['supports'][ $feature ] = [];
				}

				$metadata['supports'][ $feature ][ $name ] = true;
			}
		}

		return $metadata;
	}
}

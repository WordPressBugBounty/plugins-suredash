<?php
/**
 * Initialize some template layout setup
 *
 * @package SureDashboard
 * @since 1.5.0
 */

namespace SureDashboard\Inc\Compatibility;

use SureDashboard\Inc\Traits\Get_Instance;
use SureDashboard\Inc\Utils\Helper;

defined( 'ABSPATH' ) || exit;

/**
 * This class setup admin init
 *
 * @class Layout
 */
class Layout {
	use Get_Instance;

	/**
	 * Constructor
	 */
	public function __construct() {
		add_filter( 'render_block', [ $this, 'update_portal_application_footer_block_content' ], 10, 2 );
	}

	/**
	 * Update portal application footer block content.
	 *
	 * Case: On single community content we can add sticky CTA according to the post content type.
	 *
	 * @param string               $block_content Block content.
	 * @param array<string, mixed> $block Block attributes.
	 *
	 * @return string
	 * @since 1.5.0
	 */
	public function update_portal_application_footer_block_content( $block_content, $block ) {
		if ( ! empty( $block['attrs']['className'] ) && is_singular( SUREDASHBOARD_SUB_CONTENT_POST_TYPE ) && strpos( $block['attrs']['className'], 'portal-application-footer' ) !== false ) {
			$dynamic_content = Helper::get_community_content_cta();
			$cta_markup      = '<div class="portal-dynamic-application-cta-wrap">' . $dynamic_content . '</div>';
			if ( ! empty( $dynamic_content ) ) {
				// Regex: capture <div ...> ... </div>.
				return (string) preg_replace_callback(
					'/(<div[^>]*portal-application-footer[^>]*>)(.*?)(<\/div>)/s',
					static function( $matches ) use ( $cta_markup ) {
						return $matches[1] . $cta_markup . $matches[3];
					},
					$block_content
				);
			}
		}

		return $block_content;
	}
}

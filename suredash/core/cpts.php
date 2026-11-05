<?php
/**
 * Portals CPTs Initialize.
 *
 * @package SureDash
 */

namespace SureDashboard\Core;

use SureDashboard\Inc\Traits\Get_Instance;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Class CPTs.
 */
class CPTs {
	use Get_Instance;

	/**
	 * Option holding the signature of the rewrite rules from the last flush.
	 *
	 * @since 1.13.0
	 */
	public const REWRITE_SIGNATURE_OPTION = 'suredash_rewrite_signature';

	/**
	 * Constructor
	 *
	 * @since 1.0.0
	 */
	public function __construct() {
		$this->initialize_hooks();
	}

	/**
	 * Init Hooks.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function initialize_hooks(): void {
		$this->register_all_cpt();
		add_action( 'wp_loaded', [ $this, 'maybe_flush_rewrite_rules' ] );
	}

	/**
	 * Flush rewrite rules only when the registered rules changed.
	 *
	 * The stored rules go stale when a portal slug, a post type, an endpoint, a
	 * content type or the plugin version changes, which shows as 404s on portal pages (#691).
	 * Flushing on every request fixed that but wrote the rules to the database
	 * on every page load. Instead, hash the rules registered on this request and
	 * flush once when the hash differs from the one saved with the last flush.
	 *
	 * @since 1.13.0
	 * @return void
	 */
	public function maybe_flush_rewrite_rules(): void {
		global $wp_rewrite;

		if ( ! $wp_rewrite instanceof \WP_Rewrite ) {
			return;
		}

		// Only SureDash's own rules: other plugins may add theirs in some
		// contexts only, which would change the hash between requests. Every
		// portal rule targets the portal post type; `suredash-resource-id` is
		// the Pro resource download rule.
		$own_rule_pattern = '/post_type=' . preg_quote( SUREDASHBOARD_POST_TYPE, '/' ) . '(&|$)|suredash-resource-id=/';
		$own_rules        = array_filter(
			$wp_rewrite->extra_rules_top + $wp_rewrite->extra_rules,
			static function( $target ) use ( $own_rule_pattern ) {
				return is_string( $target ) && preg_match( $own_rule_pattern, $target );
			}
		);
		$own_permastructs = array_intersect_key(
			$wp_rewrite->extra_permastructs,
			array_flip( [ SUREDASHBOARD_POST_TYPE, SUREDASHBOARD_TAXONOMY, SUREDASHBOARD_FEED_POST_TYPE, SUREDASHBOARD_FEED_TAXONOMY, SUREDASHBOARD_SUB_CONTENT_POST_TYPE ] )
		);

		$signature = md5(
			(string) wp_json_encode(
				[
					SUREDASHBOARD_VER,
					defined( 'SUREDASH_PRO_VER' ) ? SUREDASH_PRO_VER : '',
					$wp_rewrite->permalink_structure,
					$own_rules,
					$own_permastructs,
					// Content type slugs feed the rewrite_rules_array filter in RewriteRules.
					suredash_all_content_types( true ),
				]
			)
		);

		// A missing rules option is rebuilt by WordPress itself on the next
		// request, and it is empty by design with plain permalinks.
		// Missing or changed SureDash rules are repaired even when the signature
		// matches: another plugin may have flushed on a request without SureDash.
		$saved_signature = get_option( self::REWRITE_SIGNATURE_OPTION );
		$rules_missing   = $this->own_rules_missing( $own_rules );
		if ( $saved_signature === $signature && ! $rules_missing ) {
			return;
		}

		// A cleared signature (settings save, activation) rebuilds now, so new
		// slugs work on first load. Other changes wait for a wp-admin page: a front
		// end request can lack plugins (per-page loaders), and a flush would drop
		// their rules.
		$signature_cleared = $saved_signature === false;
		$is_admin_page     = is_admin() && ! wp_doing_ajax();
		if ( ! $signature_cleared && ! $is_admin_page && ! $rules_missing ) {
			return;
		}

		flush_rewrite_rules( false );
		update_option( self::REWRITE_SIGNATURE_OPTION, $signature, true );
	}

	/**
	 * Register CPT routes.
	 *
	 * @since 1.0.0
	 */
	public function register_all_cpt(): void {
		$codes_namespace = 'SureDashboard\Core\CPT\\';

		$controllers = [
			$codes_namespace . 'Portal',
			$codes_namespace . 'Posts',
			$codes_namespace . 'Content',
		];

		foreach ( $controllers as $controller ) {
			$instance = $controller::get_instance();
			if ( method_exists( $instance, 'create_cpt_n_taxonomy' ) ) {
				$instance->create_cpt_n_taxonomy();
			}
		}
	}

	/**
	 * Whether any of SureDash's own rules is missing from the stored rules, or
	 * stored with a different target.
	 *
	 * @param array<string, string> $own_rules SureDash rules registered on this request.
	 * @return bool
	 * @since 1.13.0
	 */
	private function own_rules_missing( $own_rules ): bool {
		$stored_rules = get_option( 'rewrite_rules' );
		if ( ! is_array( $stored_rules ) ) {
			return false;
		}

		return ! empty( array_diff_assoc( $own_rules, $stored_rules ) );
	}
}

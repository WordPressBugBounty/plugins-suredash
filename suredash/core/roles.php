<?php
/**
 * Portal Role and Capability Management
 *
 * Complete system to manage portal_manager role and capabilities.
 * This replaces the old Portal Manager option system with a proper WordPress role-based system.
 *
 * @package SureDash
 * @since 1.4.0
 */

namespace SureDashboard\Core;

use SureDashboard\Inc\Traits\Get_Instance;

defined( 'ABSPATH' ) || exit;

/**
 * Portal Roles Class
 *
 * @since 1.4.0
 */
class Roles {
	use Get_Instance;

	/**
	 * Constructor
	 *
	 * @since 1.4.0
	 */
	public function __construct() {
		add_action( 'init', [ $this, 'init_hooks' ] );
	}

	/**
	 * Initialize hooks
	 *
	 * @since 1.4.0
	 */
	public function init_hooks(): void {
		$this->create_roles();
	}

	/**
	 * Create roles and capabilities
	 *
	 * @since 1.4.0
	 */
	public static function create_roles(): void {
		global $wp_roles;

		if ( ! class_exists( 'WP_Roles' ) ) {
			return;
		}

		if ( ! isset( $wp_roles ) ) {
			$wp_roles = new \WP_Roles(); // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
		}

		if ( ! get_role( 'portal_manager' ) ) {
			add_role(
				'portal_manager',
				__( 'Portal Manager', 'suredash' ),
				[
					// Basic WordPress capabilities needed for portal management.
					'read'                   => true,
					'upload_files'           => true,
					'edit_posts'             => true,
					'edit_published_posts'   => true,
					'delete_posts'           => true,
					'delete_published_posts' => true,
					'edit_pages'             => true,
					'edit_published_pages'   => true,
					'delete_pages'           => true,
					'delete_published_pages' => true,
					'moderate_comments'      => true,
					'manage_categories'      => true,
					'manage_links'           => true,
					'edit_others_posts'      => true,
					'edit_others_pages'      => true,
					'delete_others_posts'    => true,
					'delete_others_pages'    => true,
					'delete_private_posts'   => true,
					'delete_private_pages'   => true,
					'edit_private_posts'     => true,
					'edit_private_pages'     => true,
				]
			);
		}

		$capabilities = self::get_core_capabilities();

		foreach ( $capabilities as $cap_group ) {
			foreach ( $cap_group as $cap ) {
				$portal_manager_role = get_role( 'portal_manager' );
				if ( $portal_manager_role ) {
					$portal_manager_role->add_cap( $cap );
				}

				$admin_role = get_role( 'administrator' );
				if ( $admin_role ) {
					$admin_role->add_cap( $cap );
				}
			}
		}

		if ( function_exists( 'wp_cache_delete' ) ) {
			wp_cache_delete( 'user_meta', 'users' );
		}
	}

	/**
	 * Get core portal capabilities (similar to WooCommerce's get_core_capabilities)
	 *
	 * @since 1.4.0
	 * @return array<string, array<int, string>>
	 */
	public static function get_core_capabilities() {
		$capabilities = [];

		// Core portal management capabilities.
		$capabilities['core'] = [
			'manage_portal_dashboard',
			'view_portal_analytics',
			'manage_portal_settings',
		];

		// Custom post type capabilities.
		$capability_types = [ SUREDASHBOARD_POST_TYPE, SUREDASHBOARD_SUB_CONTENT_POST_TYPE ];

		foreach ( $capability_types as $capability_type ) {
			$capabilities[ $capability_type ] = [
				// Post type capabilities.
				"edit_{$capability_type}",
				"read_{$capability_type}",
				"delete_{$capability_type}",
				"edit_{$capability_type}s",
				"edit_others_{$capability_type}s",
				"publish_{$capability_type}s",
				"read_private_{$capability_type}s",
				"delete_{$capability_type}s",
				"delete_private_{$capability_type}s",
				"delete_published_{$capability_type}s",
				"delete_others_{$capability_type}s",
				"edit_private_{$capability_type}s",
				"edit_published_{$capability_type}s",
				"create_{$capability_type}s",
			];
		}

		// Taxonomy capabilities for portal groups and forums.
		$taxonomies = [ SUREDASHBOARD_TAXONOMY, SUREDASHBOARD_FEED_TAXONOMY ];
		foreach ( $taxonomies as $taxonomy ) {
			$capabilities[ $taxonomy ] = [
				"manage_{$taxonomy}_terms",
				"edit_{$taxonomy}_terms",
				"delete_{$taxonomy}_terms",
				"assign_{$taxonomy}_terms",
			];
		}

		return $capabilities;
	}
}

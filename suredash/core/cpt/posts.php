<?php
/**
 * Feed CPT
 *
 * This class will holds the Feed related CPT data.
 *
 * @package SureDash
 * @since 1.0.0
 */

namespace SureDashboard\Core\CPT;

use SureDashboard\Inc\Traits\Get_Instance;
use SureDashboard\Inc\Traits\Post_Type;
use SureDashboard\Inc\Utils\Activity_Tracker;

defined( 'ABSPATH' ) || exit;

/**
 * Posts CPT
 *
 * @since 1.0.0
 */
class Posts {
	use Post_Type;
	use Get_Instance;

	/**
	 * Constructor
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function __construct() {
		$this->post_type = SUREDASHBOARD_FEED_POST_TYPE;
		$this->taxonomy  = SUREDASHBOARD_FEED_TAXONOMY;

		$this->post_type_labels = apply_filters(
			'suredashboard_portal_posts_cpt_labels',
			[
				'name'               => esc_html_x( 'Community Posts', 'content general name', 'suredash' ),
				'singular_name'      => esc_html_x( 'Community Post', 'content singular name', 'suredash' ),
				'search_items'       => esc_html__( 'Search Community Post', 'suredash' ),
				'all_items'          => esc_html__( 'Community Posts', 'suredash' ),
				'edit_item'          => esc_html__( 'Edit Community Post', 'suredash' ),
				'view_item'          => esc_html__( 'View Community Post', 'suredash' ),
				'add_new'            => esc_html__( 'Add New', 'suredash' ),
				'update_item'        => esc_html__( 'Update Community Post', 'suredash' ),
				'add_new_item'       => esc_html__( 'Add New', 'suredash' ),
				'new_item_name'      => esc_html__( 'New Community Post Name', 'suredash' ),
				'not_found'          => esc_html__( 'No Community Post found.', 'suredash' ),
				'not_found_in_trash' => esc_html__( 'No Community Post found.', 'suredash' ),
			]
		);
		$this->post_type_args   = apply_filters(
			'suredash_posts_cpt_args',
			[
				'labels'              => $this->post_type_labels,
				'public'              => true,
				'show_ui'             => true,
				'show_in_menu'        => false,
				'query_var'           => true,
				'can_export'          => true,
				'show_in_admin_bar'   => true,
				'show_in_nav_menus'   => true,
				'exclude_from_search' => true,
				'has_archive'         => true,
				'show_in_rest'        => true,
				'rewrite'             => apply_filters(
					'suredash_community_post_rewrite_rules',
					[
						'slug'       => apply_filters( 'suredash_community_post_slug', $this->post_type ),
						'with_front' => false,
					]
				),
				'map_meta_cap'        => true,
				'supports'            => [ 'title', 'editor', 'thumbnail', 'slug', 'comments', 'excerpt', 'author' ],
			]
		);

		$this->taxonomy_args = apply_filters(
			'suredash_feed_category_args',
			[
				'hierarchical'      => true,
				'label'             => __( 'Forums', 'suredash' ),
				'labels'            => [
					'name'              => __( 'Forums', 'suredash' ),
					'singular_name'     => __( 'Forum', 'suredash' ),
					'menu_name'         => _x( 'Forums', 'Admin menu name', 'suredash' ),
					'search_items'      => __( 'Search Forum', 'suredash' ),
					'all_items'         => __( 'All Forums', 'suredash' ),
					'parent_item'       => __( 'Parent Forum', 'suredash' ),
					'parent_item_colon' => __( 'Parent Forum:', 'suredash' ),
					'edit_item'         => __( 'Edit Forum', 'suredash' ),
					'update_item'       => __( 'Update Forum', 'suredash' ),
					'add_new_item'      => __( 'Add new forum', 'suredash' ),
					'new_item_name'     => __( 'New forum name', 'suredash' ),
					'not_found'         => __( 'No forum found', 'suredash' ),
				],
				'show_ui'           => true,
				'query_var'         => true,
				'show_in_rest'      => true,
				'show_in_nav_menus' => true,
				'show_admin_column' => true,
				'rewrite'           => apply_filters(
					'suredash_community_forum_rewrite_rules',
					[
						'slug'         => $this->taxonomy,
						'with_front'   => false,
						'hierarchical' => true,
					]
				),
				'capabilities'      => [
					'manage_terms' => 'do_not_allow',
					'edit_terms'   => 'do_not_allow',
					'delete_terms' => 'do_not_allow',
					'assign_terms' => 'edit_posts',
				],
			]
		);
	}

	/**
	 * Function to load the admin area actions.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function create_cpt_n_taxonomy(): void {
		$this->init_register_post_type();
		$this->init_register_taxonomy();
		$this->init_taxonomy_filter();

		$this->initialize_hooks();
	}

	/**
	 * Function to init compatibility hooks.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function initialize_hooks(): void {
		add_action( 'save_post_' . $this->post_type, [ $this, 'attach_post' ], 10, 3 );
		// Use set_object_terms hook to invalidate cache AFTER terms are assigned.
		// This is more reliable than save_post because terms may be set separately via REST API.
		add_action( 'set_object_terms', [ $this, 'invalidate_unread_cache_on_term_set' ], 10, 6 );
		// Also keep the save_post hook for updates to existing posts.
		add_action( 'save_post_' . $this->post_type, [ $this, 'invalidate_unread_cache' ], 20, 3 );
	}

	/**
	 * Function to save the post.
	 *
	 * @param int    $post_id Post ID.
	 * @param object $post Post object.
	 * @param bool   $update Update status.
	 * @since 1.0.0
	 * @return void
	 */
	public function attach_post( $post_id, $post, $update ): void {
		if ( $update || wp_is_post_revision( $post_id ) ) {
			return;
		}

		$forum_set = isset( $_GET['forum'] ) ? absint( $_GET['forum'] ) : false; // phpcs:ignore WordPress.Security.NonceVerification

		if ( $forum_set ) {
			wp_set_post_terms( $post_id, [ $forum_set ], SUREDASHBOARD_FEED_TAXONOMY );
		}
	}

	/**
	 * Invalidate unread count cache when a post is published.
	 *
	 * @param int    $post_id Post ID.
	 * @param object $post Post object.
	 * @param bool   $update Update status.
	 * @return void
	 * @since 1.6.0
	 */
	public function invalidate_unread_cache( $post_id, $post, $update ): void {
		// Only invalidate for published posts.
		if ( ! $post instanceof \WP_Post || $post->post_status !== 'publish' ) {
			return;
		}

		// Skip revisions and autosaves.
		if ( wp_is_post_revision( $post_id ) || wp_is_post_autosave( $post_id ) ) {
			return;
		}

		$tracker = Activity_Tracker::get_instance();
		$tracker->invalidate_cache_on_new_post( $post_id );
	}

	/**
	 * Invalidate unread count cache when terms are set on a post.
	 * This hook fires AFTER wp_set_post_terms/wp_set_object_terms is called,
	 * ensuring the taxonomy terms are already saved.
	 *
	 * @param int             $object_id  Object ID.
	 * @param array<int, int> $terms      An array of object term IDs or slugs.
	 * @param array<int, int> $tt_ids     An array of term taxonomy IDs.
	 * @param string          $taxonomy   Taxonomy slug.
	 * @param bool            $append     Whether to append new terms to the old terms.
	 * @param array<int, int> $old_tt_ids Old array of term taxonomy IDs.
	 * @return void
	 * @since 1.6.0
	 */
	public function invalidate_unread_cache_on_term_set( $object_id, $terms, $tt_ids, $taxonomy, $append, $old_tt_ids ): void {
		// Only process for our feed taxonomy.
		if ( $taxonomy !== SUREDASHBOARD_FEED_TAXONOMY ) {
			return;
		}

		// Only process for our post type.
		$post = get_post( $object_id );
		if ( ! $post instanceof \WP_Post || $post->post_type !== $this->post_type ) {
			return;
		}

		// Only invalidate for published posts.
		if ( $post->post_status !== 'publish' ) {
			return;
		}

		// Invalidate cache for the assigned terms (forums).
		$tracker = Activity_Tracker::get_instance();

		// Clear cache for newly assigned terms.
		if ( ! empty( $terms ) ) {
			foreach ( $terms as $term_id ) {
				$tracker->invalidate_cache_on_new_post( $object_id );
				break; // Only need to call once per post.
			}
		}
	}
}

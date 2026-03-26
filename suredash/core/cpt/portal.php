<?php
/**
 * Portal CPT
 *
 * This class will holds the Portal related to the admin area modification
 * along with the plugin functionalities.
 *
 * @package SureDash
 * @since 1.0.0
 */

namespace SureDashboard\Core\CPT;

use SureDashboard\Inc\Traits\Get_Instance;
use SureDashboard\Inc\Traits\Post_Type;
use SureDashboard\Inc\Utils\Helper;

defined( 'ABSPATH' ) || exit;

/**
 * Account Portal CPT
 *
 * @since 1.0.0
 */
class Portal {
	use Post_Type;
	use Get_Instance;

	/**
	 * Constructor
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function __construct() {
		$this->post_type = SUREDASHBOARD_POST_TYPE;
		$this->taxonomy  = SUREDASHBOARD_TAXONOMY;

		$this->post_type_labels = apply_filters(
			'suredashboard_portal_cpt_labels',
			[
				'name'               => esc_html_x( 'Portal Spaces', 'portals general name', 'suredash' ),
				'singular_name'      => esc_html_x( 'Portal Space', 'portal singular name', 'suredash' ),
				'search_items'       => esc_html__( 'Search Space', 'suredash' ),
				'all_items'          => esc_html__( 'Portal Spaces', 'suredash' ),
				'edit_item'          => esc_html__( 'Edit Space', 'suredash' ),
				'view_item'          => esc_html__( 'View Space', 'suredash' ),
				'add_new'            => esc_html__( 'Add New', 'suredash' ),
				'update_item'        => esc_html__( 'Update Space', 'suredash' ),
				'add_new_item'       => esc_html__( 'Add New', 'suredash' ),
				'new_item_name'      => esc_html__( 'New Space Name', 'suredash' ),
				'not_found'          => esc_html__( 'No space found', 'suredash' ),
				'not_found_in_trash' => esc_html__( 'No space found', 'suredash' ),
			]
		);
		$this->post_type_args   = apply_filters(
			'suredashboard_portal_cpt_args',
			[
				'labels'              => $this->post_type_labels,
				'public'              => true,
				'show_ui'             => true,
				'show_in_menu'        => false,
				'query_var'           => true,
				'can_export'          => true,
				'show_in_admin_bar'   => false,
				'show_in_nav_menus'   => true,
				'exclude_from_search' => true,
				'has_archive'         => true,
				'show_in_rest'        => true,
				'rewrite'             => apply_filters(
					'suredash_portal_rewrite_rules',
					[
						'slug'       => apply_filters( 'suredash_portal_slug', $this->post_type ),
						'with_front' => false,
					]
				),
				'map_meta_cap'        => true,
				'supports'            => [ 'title', 'thumbnail', 'slug', 'comments', 'editor' ],
				'capability_type'     => $this->post_type,
			]
		);

		$this->taxonomy_args = apply_filters(
			'portal_group_category_args',
			[
				'hierarchical'      => true,
				'label'             => __( 'Portal Space Group', 'suredash' ),
				'labels'            => [
					'name'              => __( 'Portal Space Group', 'suredash' ),
					'singular_name'     => __( 'Portal Space Group', 'suredash' ),
					'menu_name'         => _x( 'Portal Space Groups', 'Admin menu name', 'suredash' ),
					'search_items'      => __( 'Search Space Group', 'suredash' ),
					'all_items'         => __( 'All Space Groups', 'suredash' ),
					'parent_item'       => __( 'Parent Space Group', 'suredash' ),
					'parent_item_colon' => __( 'Parent Space Group:', 'suredash' ),
					'edit_item'         => __( 'Edit Space Group', 'suredash' ),
					'update_item'       => __( 'Update Space Group', 'suredash' ),
					'add_new_item'      => __( 'Add new Space Group', 'suredash' ),
					'new_item_name'     => __( 'New Space Group name', 'suredash' ),
					'not_found'         => __( 'No Space Group found', 'suredash' ),
				],
				'show_ui'           => true,
				'query_var'         => true,
				'show_in_rest'      => true,
				'show_admin_column' => true,
				'rewrite'           => apply_filters(
					'suredash_portal_group_rewrite_rules',
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
					'assign_terms' => 'do_not_allow',
				],
			]
		);
	}

	/**
	 * Function to initialize the CPT registration.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function create_cpt_n_taxonomy(): void {
		$this->init_register_post_type();
		$this->init_register_taxonomy();

		$this->initialize_hooks();
	}

	/**
	 * Function to load the admin area actions.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function initialize_hooks(): void {
		add_action( 'created_' . SUREDASHBOARD_TAXONOMY, [ $this, 'save_category_meta' ], 11, 2 );
		add_action( 'edited_' . SUREDASHBOARD_TAXONOMY, [ $this, 'updated_category_meta' ], 11, 1 );

		// Update the docs order meta on docs update.
		add_action( 'wp_after_insert_post', [ $this, 'update_item_order_meta' ], 11, 3 );
	}

	/**
	 * Save group category fields.
	 *
	 * @since 1.0.0
	 * @param int $term_id to store term id.
	 * @param int $tt_id   The term taxonomy ID.
	 * @return void
	 */
	public function save_category_meta( $term_id, $tt_id ): void {
		$post_term_meta = ! empty( $_POST['term_meta'] ) ? wp_unslash( $_POST['term_meta'] ) : []; // phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Nonce verification is not required here & input is sanitized below using sanitize_text_field.
		if ( ! empty( $post_term_meta ) ) {
			$cat_keys = array_keys( $post_term_meta );
			foreach ( $cat_keys as $key ) {
				if ( ! empty( $post_term_meta[ $key ] ) ) {
					add_term_meta( $term_id, "group_tax_{$key}", sanitize_text_field( $post_term_meta[ $key ] ) );
				}
			}
		}

		$this->set_term_order( $term_id );
	}

	/**
	 * Set the term order for the term.
	 *
	 * @param int $term_id The term ID.
	 */
	public function set_term_order( $term_id ): void {
		$order = $this->get_max_taxonomy_order( SUREDASHBOARD_TAXONOMY );
		$order++;
		update_term_meta( $term_id, 'group_tax_position', $order );
	}

	/**
	 * Save group category custom fields.
	 *
	 * @since 1.0.0
	 * @param int $term_id to store term id.
	 */
	public function updated_category_meta( $term_id ): void {
		$post_term_meta = ! empty( $_POST['term_meta'] ) ? wp_unslash( $_POST['term_meta'] ) : []; // phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Nonce verification is not required here & input is sanitized below using sanitize_text_field.
		if ( ! empty( $post_term_meta ) ) {
			$cat_keys = array_keys( $post_term_meta );
			foreach ( $cat_keys as $key ) {
				if ( ! empty( $post_term_meta[ $key ] ) ) {
					update_term_meta( $term_id, "group_tax_{$key}", sanitize_text_field( $post_term_meta[ $key ] ) );
				}
			}
		}
	}

	/**
	 * Check if a substring exists inside a string.
	 *
	 * @param int      $item_id    Item Id / Post ID.
	 * @param \WP_Post $post   WP Post object.
	 * @param bool     $update Whether doc/post is being updated.
	 *
	 * @since 1.0.0
	 */
	public function update_item_order_meta( $item_id, $post, $update ): void {
		// Check if it's not an auto-save and if it's not a revision.
		if ( wp_is_post_autosave( $item_id ) || wp_is_post_revision( $item_id ) ) {
			return;
		}

		if ( ! $post instanceof \WP_Post ) {
			return;
		}

		// Assuming the custom post type is 'portal'.
		if ( $post->post_type !== SUREDASHBOARD_POST_TYPE ) {
			return;
		}

		// Get all categories assigned to this document.
		$categories = wp_get_object_terms( $item_id, SUREDASHBOARD_TAXONOMY, [ 'fields' => 'ids' ] );

		if ( is_array( $categories ) ) {
			// Loop through each category and update the _link_order meta.
			foreach ( $categories as $category_id ) {
				// Assign this new doc to the category.
				$item_order = Helper::get_items_order_sequence( $category_id );
				if ( ! in_array( $item_id, $item_order, true ) ) {
					$item_order[] = (string) $item_id;
					$item_order   = implode( ',', $item_order );
					update_term_meta( $category_id, '_link_order', $item_order );
				}
			}
		}
	}

	/**
	 * Get the maximum group_tax_position for this taxonomy.
	 * This will be applied to terms that don't have a tax position.
	 *
	 * @param string $tax_slug The taxonomy slug.
	 *
	 * @since 1.0.0
	 * @return int The maximum order.
	 */
	private function get_max_taxonomy_order( $tax_slug ) {
		global $wpdb;

        // phpcs:disable
        $max_term_order = $wpdb->get_col(
            $wpdb->prepare(
                "SELECT MAX( CAST( tm.meta_value AS UNSIGNED ) )
				FROM {$wpdb->terms} t
				JOIN {$wpdb->term_taxonomy} tt ON t.term_id = tt.term_id AND tt.taxonomy = '%s'
				JOIN {$wpdb->termmeta} tm ON tm.term_id = t.term_id WHERE tm.meta_key = 'group_tax_position'",
                $tax_slug
            )
        );
        // phpcs:enable

		$max_term_order = is_array( $max_term_order ) ? current( $max_term_order ) : 0;

		return $max_term_order === (int) 0 || empty( $max_term_order ) ? 1 : (int) $max_term_order + 1;
	}
}

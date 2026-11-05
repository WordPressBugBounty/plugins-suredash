<?php
/**
 * Post_Type all styles and scripts
 *
 * @package SureDash
 * @since 1.0.0
 */

namespace SureDashboard\Inc\Traits;

defined( 'ABSPATH' ) || exit;

/**
 * Trait Post_Type.
 *
 * @since 1.0.0
 */
trait Post_Type {
	/**
	 * Post type name.
	 *
	 * @var string
	 */
	public $post_type;

	/**
	 * Taxonomy name.
	 *
	 * @since 1.0.0
	 * @var string
	 */
	public $taxonomy;

	/**
	 * Post type labels.
	 *
	 * @since 1.0.0
	 * @var array<string, string>
	 */
	public $post_type_labels = [];

	/**
	 * Post type args.
	 *
	 * @since 1.0.0
	 * @var array<string, mixed>
	 */
	public $post_type_args = [];

	/**
	 * Taxonomy args.
	 *
	 * @since 1.0.0
	 * @var array<string, mixed>
	 */
	public $taxonomy_args = [];

	/**
	 * Register post type.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function init_register_post_type(): void {
		add_action( 'init', [ $this, 'register_post_type' ], 5 );
	}

	/**
	 * Register taxonomies.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function init_register_taxonomy(): void {
		add_action( 'init', [ $this, 'register_taxonomies' ], 5 );
	}

	/**
	 * Add taxonomy filter.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function init_taxonomy_filter(): void {
		add_action( 'parse_query', [ $this, 'queried_taxonomy_filter_posts' ] );
		add_action( 'restrict_manage_posts', [ $this, 'add_taxonomy_filter' ] );
	}

	/**
	 * Register the post type for the plugin.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function register_post_type(): void {
		do_action( 'suredash_before_register_' . $this->post_type . '_post_type' );

		$args = $this->post_type_args;

		register_post_type( $this->post_type, $args ); // @phpstan-ignore-line

		do_action( 'suredash_after_register_' . $this->post_type . '_post_type' );
	}

	/**
	 * Register taxonomies.
	 *
	 * @since 1.0.0
	 */
	public function register_taxonomies(): void {
		if ( ! is_blog_installed() ) {
			return;
		}

		do_action( 'suredash_before_register_' . $this->taxonomy . '_taxonomy' );

		register_taxonomy(
			$this->taxonomy,
			[ $this->post_type ],
			$this->taxonomy_args // @phpstan-ignore-line
		);

		do_action( 'suredash_after_register_' . $this->taxonomy . '_taxonomy' );
	}

	/**
	 * Add taxonomy filter.
	 *
	 * @since 1.0.0
	 */
	public function add_taxonomy_filter(): void {
		global $typenow;

		if ( $typenow === $this->post_type ) {
			$selected  = ! empty( $_GET[ $this->taxonomy ] ) ? sanitize_text_field( wp_unslash( $_GET[ $this->taxonomy ] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Nonce verification is not required.
			$tax_label = is_array( $this->taxonomy_args['labels'] ) && ! empty( $this->taxonomy_args['labels']['name'] ) ? $this->taxonomy_args['labels']['name'] : $this->taxonomy;
			wp_dropdown_categories(
				[
					'show_option_all' => __( 'All', 'suredash' ) . ' ' . $tax_label,
					'taxonomy'        => $this->taxonomy,
					'name'            => $this->taxonomy,
					'orderby'         => 'name',
					'selected'        => $selected,
					'show_count'      => true,
					'hide_empty'      => true,
				]
			);
		}
	}

	/**
	 * Filter posts by taxonomy in admin.
	 *
	 * @param object $query WP_Query object.
	 * @since 1.0.0
	 * @return void
	 */
	public function queried_taxonomy_filter_posts( $query ): void {
		global $pagenow;

		$qv = &$query->query_vars;

		if (
			$pagenow === 'edit.php' &&
			isset( $qv['post_type'] ) &&
			$qv['post_type'] === $this->post_type &&
			isset( $qv[ $this->taxonomy ] ) &&
			is_numeric( $qv[ $this->taxonomy ] )
		) {
			$term                  = get_term_by( 'id', absint( $qv[ $this->taxonomy ] ), $this->taxonomy );
			$qv[ $this->taxonomy ] = $term->slug ?? '';
		}
	}
}

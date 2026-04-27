<?php
/**
 * Portals block template service.
 *
 * @package SureDash
 */

namespace SureDashboard\Inc\Templator;

use SureDashboard\Inc\Traits\Get_Instance;

/**
 * The block templates service.
 */
class Service {
	use Get_Instance;

	/**
	 * The utility service.
	 *
	 * @var \SureDashboard\Inc\Templator\Utility
	 */
	private $utility;

	/**
	 * BlockTemplatesService constructor.
	 */
	public function __construct() {
		add_theme_support( 'block-templates' );
		add_theme_support( 'block-template-parts' );
		add_theme_support( 'appearance-tools' );
		add_theme_support( 'custom-spacing' );
		add_theme_support( 'custom-line-height' );
		add_theme_support( 'wp-block-styles' );

		$this->utility = new Utility(
			SUREDASHBOARD_DIR . 'templates/parts',  // templates_directory                                                          
			SUREDASHBOARD_DIR . 'templates/parts'   // template_parts_directory    
		);

		$this->register();
	}

	/**
	 * Check if we're in site editor context.
	 *
	 * @return bool
	 * @since 1.3.0
	 * @access private
	 */
	private function is_site_editor_context() {
		global $pagenow;

		// Check for portal template in URL or POST data.
		$template = isset( $_GET['p'] ) ? sanitize_text_field( $_GET['p'] ) : '';
		$canvas_id = isset( $_GET['canvas'] ) ? sanitize_text_field( $_GET['canvas'] ) : '';

		// Check if it's our portal template.
		return is_admin() && $pagenow === 'site-editor.php' && 'edit' === $canvas_id && strpos( $template, 'suredash/suredash//portal' ) !== false;
	}

	/**
	 * Bootstrap the block templates service.
	 *
	 * @return void
	 */
	public function register(): void {
		add_filter( 'get_block_templates', [ $this, 'addBlockTemplates' ], 10, 3 );
		add_filter( 'pre_get_block_file_template', [ $this, 'getBlockFileTemplate' ], 10, 3 );

		// Process portal-container template.
		add_filter( 'get_block_templates', [ $this, 'process_portal_container_on_load' ], 20, 3 );

		// Register classic template for non-block themes (Astra, etc.).
		// Hook to init with late priority to ensure CPTs are registered first.
		add_action( 'init', [ $this, 'register_classic_templates_for_all_post_types' ], 999 );
		add_filter( 'template_include', [ $this, 'load_classic_template' ], 99 );

		// Normalize classic template slug to block slug on block themes.
		// This ensures Quick Edit and other admin UI correctly recognize the template
		// when switching from a classic theme to a block theme.
		add_filter( 'get_post_metadata', [ $this, 'normalize_template_slug' ], 10, 4 );

		// Replace post ID placeholder during content rendering.
		add_filter( 'the_content', [ $this, 'replace_post_id_placeholder' ], 1 );
		add_filter( 'render_block', [ $this, 'replace_post_id_in_block' ], 10, 2 );

		// Fire suredash_footer action for block themes using portal-container template.
		add_action( 'wp_footer', [ $this, 'fire_suredash_footer_for_block_themes' ], 5 );

		$this->extend_blocks_palette_support();
	}

	/**
	 * Replace post ID placeholder in content.
	 *
	 * @param string $content The content.
	 * @return string Modified content.
	 * @since 1.6.0
	 */
	public function replace_post_id_placeholder( $content ) {
		$post_id = get_the_ID();
		if ( $post_id ) {
			$content = str_replace( '{{POST_ID}}', esc_attr( (string) $post_id ), $content );
		}
		return $content;
	}

	/**
	 * Replace post ID placeholder in rendered blocks.
	 *
	 * @param string $block_content The block content.
	 * @param array  $_block The block data (unused).
	 * @return string Modified block content.
	 * @since 1.6.0
	 */
	public function replace_post_id_in_block( $block_content, $_block ) {
		// Only process if the content contains our placeholder.
		if ( strpos( $block_content, '{{POST_ID}}' ) !== false ) {
			$post_id = get_the_ID();
			if ( $post_id ) {
				// Replace placeholder with actual post ID in data attribute.
				$block_content = str_replace(
					'data-post-id="{{POST_ID}}"',
					sprintf( 'id="portal-post-%s" data-post-id="%s"', esc_attr( (string) $post_id ), esc_attr( (string) $post_id ) ),
					$block_content
				);
			}
		}
		return $block_content;
	}

	/**
	 * Fire suredash_footer action for block themes using portal-container template.
	 *
	 * This ensures the search block and other footer functionality works correctly
	 * in block themes, mimicking the behavior of the classic template.
	 *
	 * @since 1.6.0
	 * @return void
	 */
	public function fire_suredash_footer_for_block_themes(): void {
		// Only run for block themes.
		if ( ! function_exists( 'wp_is_block_theme' ) || ! wp_is_block_theme() ) {
			return;
		}

		// Check if the current page is using portal-container template.
		$page_template = get_page_template_slug();
		if ( 'portal-container' !== $page_template ) {
			return;
		}

		// Fire the suredash_footer action.
		do_action( 'suredash_footer' );
	}

	/**
	 * Extend block support after setup theme.
	 *
	 * @return void
	 * @since 1.3.0
	 */
	public function extend_blocks_palette_support() {
		add_theme_support(
			'editor-color-palette',
			suredash_editor_formatted_palette_colors()
		);

		// Only add editor color palette support if we're in the site editor context.
		add_filter( 'block_editor_settings_all', [ $this, 'add_portal_palette_to_editor_settings' ], 99999, 2 );

		// Modify theme.json data for the portal.
		add_filter( 'wp_theme_json_data_theme', array( $this, 'modify_theme_palette_settings' ), 99 );
	}

	/**
	 * Add the portal color palette to the block editor settings.
	 *
	 * @param array  $settings Block editor settings.
	 * @param string $_post_type Current post type (unused).
	 * @return array
	 */
	public function add_portal_palette_to_editor_settings( $settings, $_post_type ) {
		if ( ! $this->is_site_editor_context() ) {
			return $settings;
		}

		if ( ! isset( $settings['colors'] ) ) {
			$settings['colors'] = [];
		}

		$settings['colors'] = suredash_editor_formatted_palette_colors();

		if ( ! isset( $settings['__experimentalFeatures']['color']['palette'] ) ) {
			$settings['__experimentalFeatures']['color']['palette'] = [];
		}

		$settings['__experimentalFeatures']['color']['palette'] = suredash_editor_formatted_palette_colors();

		return $settings;
	}

	/**
	 * Modify theme palette settings.
	 *
	 * @param WP_Theme_JSON_Data $theme_json settings.
	 * @return WP_Theme_JSON_Data
	 * @since 1.3.0
	 */
	public function modify_theme_palette_settings( $theme_json ) {
		if ( ! $this->is_site_editor_context() ) {
			return $theme_json;
		}

		$new_palette_data = suredash_editor_formatted_palette_colors();

		if ( ! empty( $new_palette_data ) ) {
			$new_data = array(
				'version'  => 1,
				'settings' => array(
					'color' => array(
						'palette' => $new_palette_data,
					),
				),
			);

			$theme_json->update_with( $new_data );
		}

		return $theme_json;
	}

	/**
	 * Process portal container template and replace content block.
	 *
	 * @param object $template Block template object.
	 * @return object Modified template.
	 * @since 1.6.0
	 */
	public function process_portal_container_template( $template ) {
		// Only process portal-container template.
		if ( $template->slug !== 'portal-container' ) {
			return $template;
		}

		// Get the portal template part.
		$portal_template_part = get_block_template( 'suredash/suredash//portal', 'wp_template_part' );

		if ( ! $portal_template_part || empty( $portal_template_part->content ) ) {
			return $template;
		}

		// Parse portal template part blocks.
		$blocks = parse_blocks( $portal_template_part->content );

		// Replace suredash/content with core/post-content.
		$modified_blocks = $this->replace_content_block_recursive( $blocks );

		// Serialize blocks back to HTML.
		$modified_content = serialize_blocks( $modified_blocks );

		// Replace the template part placeholder in the main template.
		$final_content = str_replace(
			'<!-- wp:template-part {"slug":"portal","theme":"suredash/suredash"} /-->',
			$modified_content,
			$template->content
		);

		$template->content = $final_content;

		return $template;
	}

	/**
	 * Recursively find and replace suredash/content block with core/post-content.
	 *
	 * @param array $blocks Array of parsed blocks.
	 * @return array Modified blocks.
	 * @since 1.6.0
	 */
	private function replace_content_block_recursive( $blocks ) {
		// Get layout details for container type and style.
		$layout_details = \SureDashboard\Inc\Utils\Helper::get_layout_details();
		$layout_class   = $layout_details['layout'] ?? 'normal';
		$layout_style   = $layout_details['style'] ?? 'boxed';
		foreach ( $blocks as &$block ) {
			// Found the content block - replace it with wrapped structure matching classic template.
			if ( $block['blockName'] === 'suredash/content' ) {
				// Outer wrapper: portal-main-content with dynamic layout classes (from Content block view.php line 91).
				$main_content_open = sprintf(
					'<div id="portal-main-content" class="portal-layout-%s wp-block-suredash-content">',
					esc_attr( $layout_class )
				);

				// Inner wrapper: portal-content-area with post ID (from single-content.php line 169).
				// Note: We use data-post-id placeholder that will be populated at render time.
				$content_area_open = sprintf(
					'<div class="portal-content-area sd-%s-post" data-post-id="{{POST_ID}}">',
					esc_attr( $layout_style )
				);
				$content_area_open .= '<div class="sd-overflow-hidden suredash-single-content portal-space-post-content portal-page-content">';

				// Closing wrappers.
				$content_area_close = '</div></div>';
				$main_content_close = '</div>';

				// Replace with a group block containing both wrappers, post-content, and closing tags.
				// This matches the structure used in classic template (template-portal-container.php lines 59-77).
				$block = [
					'blockName'    => 'core/group',
					'attrs'        => [
						'style' => [
							'spacing' => [
								'padding'  => [ 'top' => '0', 'bottom' => '0', 'left' => '0', 'right' => '0' ],
								'blockGap' => '0',
							],
						],
					],
					'innerBlocks'  => [
						[
							'blockName'    => 'core/html',
							'attrs'        => [],
							'innerHTML'    => $main_content_open,
							'innerContent' => [ $main_content_open ],
							'innerBlocks'  => [],
						],
						[
							'blockName'    => 'core/html',
							'attrs'        => [],
							'innerHTML'    => $content_area_open,
							'innerContent' => [ $content_area_open ],
							'innerBlocks'  => [],
						],
						[
							'blockName'    => 'core/post-content',
							'attrs'        => [],
							'innerBlocks'  => [],
							'innerHTML'    => '',
							'innerContent' => [],
						],
						[
							'blockName'    => 'core/html',
							'attrs'        => [],
							'innerHTML'    => $content_area_close,
							'innerContent' => [ $content_area_close ],
							'innerBlocks'  => [],
						],
						[
							'blockName'    => 'core/html',
							'attrs'        => [],
							'innerHTML'    => $main_content_close,
							'innerContent' => [ $main_content_close ],
							'innerBlocks'  => [],
						],
					],
					'innerHTML'    => '<div class="wp-block-group" style="padding-top:0;padding-right:0;padding-bottom:0;padding-left:0"></div>',
					'innerContent' => [ '<div class="wp-block-group" style="padding-top:0;padding-right:0;padding-bottom:0;padding-left:0">', null, null, null, null, null, '</div>' ],
				];
			}

			// Process nested blocks recursively.
			if ( ! empty( $block['innerBlocks'] ) ) {
				$block['innerBlocks'] = $this->replace_content_block_recursive( $block['innerBlocks'] );
			}
		}

		return $blocks;
	}

	/**
	 * Process templates before they're returned to WordPress.
	 *
	 * @param array  $templates Array of template objects.
	 * @param array  $_query Query parameters (unused).
	 * @param string $template_type Template type (wp_template or wp_template_part).
	 * @return array Modified templates.
	 * @since 1.6.0
	 */
	public function process_portal_container_on_load( $templates, $_query, $template_type ) {
		// Only process wp_template type.
		if ( 'wp_template' !== $template_type ) {
			return $templates;
		}

		foreach ( $templates as &$template ) {
			if ( $template->slug === 'portal-container' ) {
				$template = $this->process_portal_container_template( $template );
			}
		}

		return $templates;
	}

	/**
	 * Register template filters for all eligible post types.
	 *
	 * WordPress uses different filters for different post types:
	 * - theme_page_templates (for pages)
	 * - theme_post_templates (for posts)
	 * - theme_{$post_type}_templates (for custom post types)
	 *
	 * @since 1.6.0
	 * @return void
	 */
	public function register_classic_templates_for_all_post_types(): void {
		// Get eligible post types for portal-container template.
		$eligible_post_types = Utility::get_portal_container_post_types();

		// Register template for each eligible post type.
		foreach ( $eligible_post_types as $post_type ) {
			// Use the appropriate filter based on post type.
			if ( 'page' === $post_type ) {
				add_filter( 'theme_page_templates', [ $this, 'register_classic_template' ], 10, 4 );
			} elseif ( 'post' === $post_type ) {
				add_filter( 'theme_post_templates', [ $this, 'register_classic_template' ], 10, 4 );
			} 
			else {
				// For custom post types, use dynamic filter.
				add_filter( "theme_{$post_type}_templates", [ $this, 'register_classic_template' ], 10, 4 );
			}
		}
	}

	/**
	 * Register the Portal Layout template for classic/hybrid themes.
	 *
	 * @param array  $templates Array of page templates. Keys are filenames, values are translated names.
	 * @param object $theme The theme object.
	 * @param object $post The post being edited, provided for context or null.
	 * @param string $post_type Post type to get the templates for.
	 * @return array Modified templates array.
	 * @since 1.6.0
	 */
	public function register_classic_template( $templates, $theme, $post, $post_type ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed
		// Add our template to the dropdown.
		$templates['templates/pages/template-portal-container.php'] = __( 'SureDash: Portal Layout', 'suredash' );

		return $templates;
	}

	/**
	 * Load the Portal Layout template file for classic/hybrid themes.
	 *
	 * @param string $template Path to the template.
	 * @return string Modified template path.
	 * @since 1.6.0
	 */
	public function load_classic_template( $template ) {
		// Block themes use the block template system — don't override with classic PHP template.
		if ( function_exists( 'wp_is_block_theme' ) && wp_is_block_theme() ) {
			return $template;
		}

		// Get the selected template.
		$page_template = get_page_template_slug();

		// Check if our template is selected.
		if ( 'templates/pages/template-portal-container.php' === $page_template ) {
			$plugin_template = SUREDASHBOARD_DIR . 'templates/pages/template-portal-container.php';

			if ( file_exists( $plugin_template ) ) {
				return $plugin_template;
			}
		}

		return $template;
	}

	/**
	 * Normalize the portal-container template slug based on context.
	 *
	 * The block editor saves templates as 'portal-container', while Quick Edit uses
	 * 'templates/pages/template-portal-container.php'. This filter ensures consistency:
	 * - Admin: converts block slug → classic path (for Quick Edit dropdown matching)
	 * - Frontend (block theme): converts classic path → block slug (for block template system)
	 *
	 * @param mixed  $value     The meta value to return.
	 * @param int    $object_id The object ID.
	 * @param string $meta_key  The meta key.
	 * @param bool   $single    Whether to return a single value.
	 *
	 * @since 1.7.3
	 * @return mixed The filtered meta value.
	 */
	public function normalize_template_slug( $value, $object_id, $meta_key, $single ) {
		if ( '_wp_page_template' !== $meta_key || ! $single ) {
			return $value;
		}

		// Avoid infinite loop — unhook before calling get_post_meta.
		remove_filter( 'get_post_metadata', [ $this, 'normalize_template_slug' ], 10 );
		$raw = get_post_meta( $object_id, '_wp_page_template', true );
		add_filter( 'get_post_metadata', [ $this, 'normalize_template_slug' ], 10, 4 );

		if ( is_admin() ) {
			// Admin: convert block slug to classic path so Quick Edit dropdown matches.
			if ( 'portal-container' === $raw ) {
				return 'templates/pages/template-portal-container.php';
			}
		} elseif ( function_exists( 'wp_is_block_theme' ) && wp_is_block_theme() ) {
			// Frontend (block theme): convert classic path to block slug so block template system picks it up.
			if ( 'templates/pages/template-portal-container.php' === $raw ) {
				return 'portal-container';
			}
		}

		return $value;
	}

	/**
	 * Checks whether a block template with that name exists in Woo Blocks
	 *
	 * @param string $template_name Template to check.
	 * @param array  $template_type wp_template or wp_template_part.
	 *
	 * @return bool
	 */
	public function blockTemplateIsAvailable( $template_name, $template_type = 'wp_template' ) {
		if ( ! $template_name ) {
			return false;
		}

		$directory = $this->utility->getTemplatesDirectory( $template_type ) . '/' . $template_name . '.html';

		return is_readable( $directory ) || $this->getBlockTemplates( [ $template_name ], $template_type );
	}

	/**
	 * This function is used on the `pre_get_block_template` hook to return the fallback template from the db in case
	 * the template is eligible for it.
	 *
	 * @param \WP_Block_Template|null $template Block template object to short-circuit the default query,
	 *                                          or null to allow WP to run its normal queries.
	 * @param string                  $id Template unique identifier (example: theme_slug//template_slug).
	 * @param string                  $template_type wp_template or wp_template_part.
	 *
	 * @return object|null
	 */
	public function getBlockFileTemplate( $template, $id, $template_type ) {
		$template_name_parts = explode( '//', $id );

		if ( count( $template_name_parts ) < 2 ) {
			return $template;
		}

		[ $template_id, $template_slug ] = $template_name_parts;

		// If we are not dealing with a Plugin template let's return early and let it continue through the process.
		if ( $this->utility::PLUGIN_SLUG !== $template_id ) {
			return $template;
		}

		// If we don't have a template let Gutenberg do its thing.
		if ( ! $this->blockTemplateIsAvailable( $template_slug, $template_type ) ) {
			return $template;
		}

		$directory          = $this->utility->getTemplatesDirectory( $template_type );
		$template_file_path = $directory . '/' . $template_slug . '.html';
		$template_object    = $this->utility->createNewBlockTemplateObject( $template_file_path, $template_type, $template_slug );
		$template_built     = $this->utility->buildTemplateResultFromFile( $template_object, $template_type );

		if ( $template_built !== null ) {
			return $template_built;
		}

		// Hand back over to Gutenberg if we can't find a template.
		return $template;
	}

	/**
	 * Add the block template objects to be used.
	 *
	 * @param array  $query_result Array of template objects.
	 * @param array  $query Optional. Arguments to retrieve templates.
	 * @param string $template_type wp_template or wp_template_part.
	 * @return array
	 */
	public function addBlockTemplates( $query_result, $query, $template_type ) {
		// does not support block templates.
		if ( $template_type === 'wp_template' && ! $this->utility->supportsBlockTemplates() ) {
			return $query_result;
		}

		$post_type = $query['post_type'] ?? '';
		$slugs     = $query['slug__in'] ?? [];

		$template_files = $this->getBlockTemplates( $slugs, $template_type );
		foreach ( $template_files as $template_file ) {
			// If the current $post_type is set (e.g. on an Edit Post screen), and isn't included in the available post_types
			// on the template file, then lets skip it so that it doesn't get added. This is typically used to hide templates
			// in the template dropdown on the Edit Post page.
			if ( $post_type &&
				isset( $template_file->post_types ) &&
				! in_array( $post_type, $template_file->post_types, true )
			) {
				continue;
			}

			// this supports block templates and the template is not available in the site editor.
			if ( $this->utility->supportsBlockTemplates() && ! $this->utility->isBlockAvailableInSiteEditor( $template_file->slug ) ) {
				continue;
			}

			// It would be custom if the template was modified in the editor, so if it's not custom we can load it from
			// the filesystem.
			if ( $template_file->source !== 'custom' ) {
				$template = $this->utility->buildTemplateResultFromFile( $template_file, $template_type );
			} else {
				$template_file->title       = $this->utility->getBlockTemplateTitle( $template_file->slug );
				$template_file->description = $this->utility->getBlockTemplateDescription( $template_file->slug );
				$query_result[]             = $template_file;
				continue;
			}

			$is_not_custom   = array_search(
				wp_get_theme()->get_stylesheet() . '//' . $template_file->slug,
				array_column( $query_result, 'id' ),
				true
			) === false;
			$fits_slug_query =
				! isset( $query['slug__in'] ) || in_array( $template_file->slug, $query['slug__in'], true );
			$fits_area_query =
				! isset( $query['area'] ) || $template_file->area === $query['area'];
			$should_include  = $is_not_custom && $fits_slug_query && $fits_area_query;
			if ( $should_include ) {
				$query_result[] = $template;
			}
		}

		// We need to remove theme (i.e. filesystem) templates that have the same slug as a customised one.
		// This only affects saved templates that were saved BEFORE a theme template with the same slug was added.
		$query_result = $this->utility->removeThemeTemplatesWithCustomAlternative( $query_result );

		/**
		 * Plugin templates from theme aren't included in `$this->get_block_templates()` but are handled by Gutenberg.
		 * We need to do additional search through all templates file to update title and description for Plugin
		 * templates that aren't listed in theme.json.
		 */
		return array_map(
			function ( $template ) {
				if ( $template->origin === 'theme' && $this->utility->templateHasTitle( $template ) ) {
					return $template;
				}
				if ( $template->title === $template->slug ) {
					$template->title = $this->utility->getBlockTemplateTitle( $template->slug );
				}
				if ( ! $template->description ) {
					$template->description = $this->utility->getBlockTemplateDescription( $template->slug );
				}

				return $this->setTemplateName( $template );
			},
			$query_result
		);
	}

	/**
	 * Set the template name
	 *
	 * @param [type] $template
	 *
	 * @return void
	 */
	public function setTemplateName( $template ) {
		// Handle portal-container template specifically.
		if ( $template->slug === 'portal-container' ) {
			$template->title       = _x( 'SureDash: Portal Layout', 'SureDash Portal Layout', 'suredash' );
			$template->description = __( 'Display your page/post content within SureDash portal layout.', 'suredash' );
			return $template;
		}

		if ( preg_match( '/(portal)-(.+)/', $template->slug, $matches ) ) {
			$type = $matches[1];

			if ( $type === 'portal' ) {
				$template->title = sprintf(
					// translators: Represents the title of a user's custom template in the Site Editor, where %s is the author's name, e.g. "Author: Jane Doe".
					__( 'SureDash: %s', 'suredash' ),
					__( 'Community Portal', 'suredash' )
				);
				$template->description = __( 'Template for your SureDash community.', 'suredash' );
			}
		}

		return $template;
	}

	/**
	 * Get and build the block template objects from the block template files.
	 *
	 * @param array  $slugs An array of slugs to retrieve templates for.
	 * @param string $template_type wp_template or wp_template_part.
	 *
	 * @return array WP_Block_Template[] An array of block template objects.
	 */
	public function getBlockTemplates( $slugs = [], $template_type = 'wp_template' ) {
		$templates_from_db     = $this->getBlockTemplatesFromDB( $slugs, $template_type );
		$templates_from_plugin = $this->getBlockTemplatesFromPlugin( $slugs, $templates_from_db, $template_type );
		return array_merge( $templates_from_db, $templates_from_plugin );
	}

	/**
	 * Gets the templates saved in the database.
	 *
	 * @param array  $slugs An array of slugs to retrieve templates for.
	 * @param string $template_type wp_template or wp_template_part.
	 *
	 * @return array<int>|array<\WP_Post> An array of found templates.
	 */
	public function getBlockTemplatesFromDB( $slugs = [], $template_type = 'wp_template' ) {
		$check_query_args = [
			'post_type'      => $template_type,
			'posts_per_page' => -1,
			'no_found_rows'  => true,
			'tax_query'      => [ // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query
				[
					'taxonomy' => 'wp_theme',
					'field'    => 'name',
					'terms'    => [ $this->utility::PLUGIN_SLUG ],
				],
			],
		];

		if ( is_array( $slugs ) && count( $slugs ) > 0 ) {
			$check_query_args['post_name__in'] = $slugs;
		}

		$check_query     = new \WP_Query( $check_query_args );
		$saved_templates = $check_query->posts;

		return array_map(
			function ( $saved_template ) {
				return $this->utility->buildTemplateResultsFromPost( $saved_template );
			},
			$saved_templates
		);
	}

	/**
	 * Gets the templates from the Plugin blocks directory, skipping those for which a template already exists
	 * in the theme directory.
	 *
	 * @param array<string> $slugs An array of slugs to filter templates by. Templates whose slug does not match will not be returned.
	 * @param array         $already_found_templates Templates that have already been found, these are customised templates that are loaded from the database.
	 * @param string        $template_type wp_template or wp_template_part.
	 *
	 * @return array Templates from the Plugin blocks plugin directory.
	 */
	public function getBlockTemplatesFromPlugin( $slugs, $already_found_templates, $template_type = 'wp_template' ) {
		$directory      = $this->utility->getTemplatesDirectory( $template_type );
		$template_files = $this->utility->getTemplatePaths( $directory );

		$templates = [];
		foreach ( $template_files as $template_file ) {
			$template_slug = $this->utility->generateTemplateSlugFromPath( $template_file );

			// This template does not have a slug we're looking for. Skip it.
			if ( is_array( $slugs ) && count( $slugs ) > 0 && ! in_array( $template_slug, $slugs, true ) ) {
				continue;
			}

			// If the theme already has a template, or the template is already in the list (i.e. it came from the
			// database) then we should not overwrite it with the one from the filesystem.
			if (
				$this->utility->themeHasTemplate( $template_slug ) ||
				count(
					array_filter(
						$already_found_templates,
						static function ( $template ) use ( $template_slug ) {
							$template_obj = (object) $template; //phpcs:ignore WordPress.CodeAnalysis.AssignmentInCondition.Found
							return $template_obj->slug === $template_slug;
						}
					)
				) > 0 ) {
				continue;
			}

			// At this point the template only exists in the Blocks filesystem and has not been saved in the DB,
			// or superseded by the theme.
			$templates[] = $this->utility->createNewBlockTemplateObject( $template_file, $template_type, $template_slug );
		}

		return $templates;
	}
}

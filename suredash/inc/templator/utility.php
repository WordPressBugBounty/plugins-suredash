<?php
/**
 * Portals block template utility.
 *
 * @package SureDash
 */

namespace SureDashboard\Inc\Templator;

/**
 * The block templates service.
 */
class Utility {
	/**
	 * Plugin slug
	 *
	 * This is used to save templates to the DB which are stored against this value in the wp_terms table.
	 */
	public const PLUGIN_SLUG = 'suredash/suredash';

	/**
	 * Holds the path for the directory where the block templates will be kept.
	 *
	 * @var string
	 */
	private $templates_directory;

	/**
	 * Holds the path for the directory where the block template parts will be kept.
	 *
	 * @var string
	 */
	private $template_parts_directory;

	/**
	 * The product template types.
	 *
	 * @var array
	 */
	private $plugin_template_types;

	/**
	 * Set the directories where the block templates will be kept.
	 *
	 * @param string $templates_directory      The path for the directory where the block templates will be kept.
	 * @param string $template_parts_directory The path for the directory where the block template parts will be kept.
	 */
	public function __construct( $templates_directory, $template_parts_directory ) {
		$this->templates_directory      = $templates_directory;
		$this->template_parts_directory = $template_parts_directory;

		$this->plugin_template_types    = [
			'portal'           => [
				'title'       => _x( 'SureDash: Portal', 'SureDash Portal', 'suredash' ),
				'description' => __( 'Template for your SureDash community.', 'suredash' ),
			],
			'portal-container' => [
				'title'       => _x( 'SureDash: Portal Layout', 'SureDash Portal Layout', 'suredash' ),
				'description' => __( 'Display your page/post content within SureDash portal layout.', 'suredash' ),
			],
		];

		add_filter( 'body_class', [ $this, 'template_based_body_class' ], 99 );
	}

	/**
	 * Is one of our templates active?
	 *
	 * @return bool
	 * @since 0.0.6
	 */
	public function is_template_active() {
		$template = get_page_template_slug();
		return $template !== false && array_key_exists( $template, $this->plugin_template_types );
	}

	/**
	 * Add a class to the body tag based on the current template.
	 *
	 * @param array $body_class The current body body_class.
	 *
	 * @return array
	 */
	public function template_based_body_class( $body_class ) {
		$template = get_page_template_slug();
		$content_post = suredash_content_post();

		if ( $content_post ) {
			$body_class[] = 'suredash-' . $content_post . '-view';
		}

		if ( $template !== false && $this->is_template_active() ) {
			$body_class[] = 'suredash-template';
			$body_class[] = 'portal-container';
			$body_class[] = 'suredash-template-' . get_template();
		}

		return $body_class;
	}

	/**
	 * Gets the first matching template part within themes directories
	 *
	 * Since [Gutenberg 12.1.0](https://github.com/WordPress/gutenberg/releases/tag/v12.1.0), the conventions for
	 * block templates and parts directory has changed from `block-templates` and `block-templates-parts`
	 * to `templates` and `parts` respectively.
	 *
	 * This function traverses all possible combinations of directory paths where a template or part
	 * could be located and returns the first one which is readable, prioritizing the new convention
	 * over the deprecated one, but maintaining that one for backwards compatibility.
	 *
	 * @param string $template_slug  The slug of the template (i.e. without the file extension).
	 * @param string $template_type  Either `wp_template` or `wp_template_part`.
	 *
	 * @return string|null  The matched path or `null` if no match was found.
	 */
	public function getThemeTemplatePath( $template_slug, $template_type = 'wp_template' ) {
		$template_filename = $template_slug . '.html';
		$templates_dir     = $template_type === 'wp_template' ? 'templates' : 'parts';

		$filepath = DIRECTORY_SEPARATOR . $templates_dir . DIRECTORY_SEPARATOR . $template_filename;

		$possible_paths = [
			get_stylesheet_directory() . $filepath,
			get_template_directory() . $filepath,
		];

		// Return the first matching.
		foreach ( $possible_paths as $path ) {
			if ( is_readable( $path ) ) {
				return $path;
			}
		}

		return null;
	}

	/**
	 * Gets the directory where templates of a specific template type can be found.
	 *
	 * @param string $template_type wp_template or wp_template_part.
	 *
	 * @return string
	 */
	public function getTemplatesDirectory( $template_type = 'wp_template' ) {
		return $template_type === 'wp_template_part' ? $this->template_parts_directory : $this->templates_directory;
	}

	/**
	 * Finds all nested template part file paths in a theme's directory.
	 *
	 * @param string $base_directory The theme's file path.
	 * @return array $path_list A list of paths to all template part files.
	 */
	public function getTemplatePaths( $base_directory ) {
		$path_list = [];
		if ( file_exists( $base_directory ) ) {
			$nested_files      = new \RecursiveIteratorIterator( new \RecursiveDirectoryIterator( $base_directory ) );
			$nested_html_files = new \RegexIterator( $nested_files, '/^.+\.html$/i', \RecursiveRegexIterator::GET_MATCH );
			foreach ( $nested_html_files as $path => $file ) {
				$path_list[] = $path;
			}
		}
		return $path_list;
	}

	/**
	 * Converts template paths into a slug
	 *
	 * @param string $path The template's path.
	 * @return string slug
	 */
	public function generateTemplateSlugFromPath( $path ) {
		return basename( $path, '.html' );
	}

	/**
	 * Check if the theme has a template. So we know if to load our own in or not.
	 *
	 * @param string $template_name name of the template file without .html extension e.g. 'taxonomy-portal'.
	 * @return bool
	 */
	public function themeHasTemplate( $template_name ) {
		return (bool) $this->getThemeTemplatePath( $template_name, 'wp_template' );
	}

	/**
	 * Check if the theme has a template. So we know if to load our own in or not.
	 *
	 * @param string $template_name name of the template file without .html extension e.g. 'taxonomy-portal'.
	 * @return bool
	 */
	public function themeHasTemplatePart( $template_name ) {
		return (bool) $this->getThemeTemplatePath( $template_name, 'wp_template_part' );
	}

	/**
	 * Is this a FSE theme?
	 *
	 * @return bool
	 */
	public function isFSETheme() {
		if ( function_exists( 'wp_is_block_theme' ) ) {
			return (bool) \wp_is_block_theme();
		}
		if ( function_exists( 'gutenberg_is_fse_theme' ) ) {
			return (bool) \gutenberg_is_fse_theme();
		}
		return false;
	}

	/**
	 * Does this theme support block templates?
	 *
	 * @return bool
	 */
	public function supportsBlockTemplates() {
		return $this->isFSETheme() || ( function_exists( 'gutenberg_supports_block_templates' ) && \gutenberg_supports_block_templates() );
	}

	/**
	 * Build a unified template object based a post Object.
	 * Important: This method is an almost identical duplicate from wp-includes/block-template-utils.php as it was not intended for public use. It has been modified to build templates from plugins rather than themes.
	 *
	 * @param \WP_Post $post Template post.
	 *
	 * @return \WP_Block_Template|\WP_Error Template.
	 */
	public function buildTemplateResultsFromPost( $post ) {
		$terms = get_the_terms( $post, 'wp_theme' );

		if ( is_wp_error( $terms ) ) {
			return $terms;
		}

		if ( ! $terms ) {
			return new \WP_Error( 'template_missing_theme', __( 'No theme is defined for this template.', 'suredash' ) );
		}

		$theme          = $terms[0]->name;
		$has_theme_file = true;

		$template                 = new \WP_Block_Template();
		$template->wp_id          = $post->ID;
		$template->id             = $theme . '//' . $post->post_name;
		$template->theme          = $theme;
		$template->content        = $post->post_content;
		$template->slug           = $post->post_name;
		$template->source         = 'custom';
		$template->type           = $post->post_type;
		$template->description    = $post->post_excerpt;
		$template->title          = $post->post_title;
		$template->status         = $post->post_status;
		$template->has_theme_file = $has_theme_file;
		$template->is_custom      = true;
		// Set post types based on template slug.
		if ( $template->slug === 'portal-container' ) {
			// Portal container template: Available for all public post types except excluded ones.
			$template->post_types = self::get_portal_container_post_types();
		} else {
			// Other templates (like 'portal'): Only for SureDash-specific post types.
			$template->post_types = [ SUREDASHBOARD_FEED_POST_TYPE, SUREDASHBOARD_SUB_CONTENT_POST_TYPE ];
		}

		if ( $post->post_type === 'wp_template_part' ) {
			$type_terms = get_the_terms( $post, 'wp_template_part_area' );
			if ( ! is_wp_error( $type_terms ) && $type_terms !== false ) {
				$template->area = $type_terms[0]->name;
			}
		}

		if ( $theme === 'suredash/suredash' ) {
			$template->origin = 'plugin';
		}

		return $template;
	}

	/**
	 * Returns an array containing the references of
	 * the passed blocks and their inner blocks.
	 *
	 * @param array $blocks array of blocks.
	 *
	 * @return array block references to the passed blocks and their inner blocks.
	 */
	public function flattenBlocks( &$blocks ) {
		$all_blocks = [];
		$queue      = [];
		foreach ( $blocks as &$block ) {
			$queue[] = &$block;
		}
		$queue_count = count( $queue );

		while ( $queue_count > 0 ) {
			$block = &$queue[0];
			array_shift( $queue );
			$all_blocks[] = &$block;

			if ( ! empty( $block['innerBlocks'] ) ) {
				foreach ( $block['innerBlocks'] as &$inner_block ) {
					$queue[] = &$inner_block;
				}
			}

			$queue_count = count( $queue );
		}

		return $all_blocks;
	}

	/**
	 * Parses wp_template content and injects the current theme's
	 * stylesheet as a theme attribute into each wp_template_part
	 *
	 * @param string $template_content serialized wp_template content.
	 *
	 * @return string Updated wp_template content.
	 */
	public function injectThemeAttributeInContent( $template_content ) {
		$has_updated_content = false;
		$new_content         = '';
		$template_blocks     = parse_blocks( $template_content );

		$blocks = $this->flattenBlocks( $template_blocks );
		foreach ( $blocks as &$block ) {
			if (
				$block['blockName'] === 'core/template-part' &&
				! isset( $block['attrs']['theme'] )
			) {
				$block['attrs']['theme'] = wp_get_theme()->get_stylesheet();
				$has_updated_content     = true;
			}
		}

		if ( $has_updated_content ) {
			foreach ( $template_blocks as &$block ) {
				$new_content .= serialize_block( $block );
			}

			return $new_content;
		}

		return $template_content;
	}

	/**
	 * Build a unified template object based on a theme file.
	 * Important: This method is an almost identical duplicate from wp-includes/block-template-utils.php as it was not intended for public use. It has been modified to build templates from plugins rather than themes.
	 *
	 * @param array|object $template_file Theme file.
	 * @param string       $template_type wp_template or wp_template_part.
	 *
	 * @return \WP_Block_Template Template.
	 */
	public function buildTemplateResultFromFile( $template_file, $template_type ) {
		$default_template_types = get_default_block_template_types();
		$template_file          = (object) $template_file;

		// If the theme has an archive-products.html template but does not have product taxonomy templates.
		// then we will load in the archive-product.html template from the theme to use for product taxonomies on the frontend.
		$template_is_from_theme = $template_file->source === 'theme';
		$theme_name             = wp_get_theme()->get( 'TextDomain' );

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents.
		$template_content  = file_get_contents( $template_file->path );
		$template          = new \WP_Block_Template();
		$template->id      = $template_is_from_theme ? $theme_name . '//' . $template_file->slug : self::PLUGIN_SLUG . '//' . $template_file->slug;
		$template->theme   = $template_is_from_theme ? $theme_name : self::PLUGIN_SLUG;
		$template->content = $this->injectThemeAttributeInContent( $template_content );
		// Plugin was agreed as a valid source value despite existing inline docs at the time of creating: https://github.com/WordPress/gutenberg/issues/36597#issuecomment-976232909.
		$template->source         = $template_file->source ? $template_file->source : 'plugin';
		$template->slug           = $template_file->slug;
		$template->type           = $template_type;
		$template->title          = ! empty( $template_file->title ) ? $template_file->title : $this->getBlockTemplateTitle( $template_file->slug );
		$template->description    = ! empty( $template_file->description ) ? $template_file->description : $this->getBlockTemplateDescription( $template_file->slug );
		$template->status         = 'publish';
		$template->has_theme_file = true;
		$template->origin         = $template_file->source;
		$template->is_custom      = true; // Templates loaded from the filesystem aren't custom, ones that have been edited and loaded from the DB are.
		// Set post types based on template slug.
		if ( $template_file->slug === 'portal-container' ) {
			// Portal container template: Available for all public post types except excluded ones.
			$template->post_types = self::get_portal_container_post_types();
		} elseif ( $template_file->slug === 'portal' ) {
			// Portal template: Only for portal CPT (spaces), not for pages or other post types.
			$template->post_types = [ SUREDASHBOARD_POST_TYPE ];
		} else {
			// Other templates: For SureDash-specific post types.
			$template->post_types = [ SUREDASHBOARD_FEED_POST_TYPE, SUREDASHBOARD_SUB_CONTENT_POST_TYPE ];
		}
		$template->area           = 'uncategorized';

		if ( $template_type === 'wp_template' && isset( $default_template_types[ $template_file->slug ] ) ) {
			$template->is_custom = false;
		}

		return $template;
	}

	/**
	 * Returns template titles.
	 *
	 * @param string $template_slug The templates slug (e.g. taxonomy-portal).
	 * @return string Human friendly title.
	 */
	public function getBlockTemplateTitle( $template_slug ) {
		if ( isset( $this->plugin_template_types[ $template_slug ] ) ) {
			return $this->plugin_template_types[ $template_slug ]['title'];
		}
		// Human friendly title converted from the slug.
		return ucwords( preg_replace( '/[\-_]/', ' ', $template_slug ) );
	}

	/**
	 * Returns template descriptions.
	 *
	 * @param string $template_slug The templates slug (e.g. taxonomy-portal).
	 * @return string Template description.
	 */
	public function getBlockTemplateDescription( $template_slug ) {
		if ( isset( $this->plugin_template_types[ $template_slug ] ) ) {
			return $this->plugin_template_types[ $template_slug ]['description'];
		}
		return '';
	}

	/**
	 * Returns whether a block template is available in the site editor.
	 *
	 * @param string $template_slug The templates slug (e.g. taxonomy-portal).
	 *
	 * @return bool
	 */
	public function isBlockAvailableInSiteEditor( $template_slug ) {
		if ( isset( $this->plugin_template_types[ $template_slug ] ) ) {
			return ! isset( $this->plugin_template_types[ $template_slug ]['site-editor'] ) || $this->plugin_template_types[ $template_slug ]['site-editor'];
		}
		return true;
	}

	/**
	 * Build a new template object so that we can make Plugin Blocks default templates available in the current theme should they not have any.
	 *
	 * @param string $template_file Block template file path.
	 * @param string $template_type wp_template or wp_template_part.
	 * @param string $template_slug Block template slug e.g. taxonomy-portal.
	 * @param bool   $template_is_from_theme If the block template file is being loaded from the current theme instead of Plugin Blocks.
	 *
	 * @return object Block template object.
	 */
	public function createNewBlockTemplateObject( $template_file, $template_type, $template_slug, $template_is_from_theme = false ) {
		$theme_name = wp_get_theme()->get( 'TextDomain' );

		// Set post types based on template slug.
		if ( $template_slug === 'portal-container' ) {
			// Portal container template: Available for all public post types except excluded ones.
			$post_types = self::get_portal_container_post_types();
		} else {
			// Other templates (like 'portal'): Only for SureDash-specific post types.
			$post_types = [ 'sc_product', 'sc_collection', 'sc_bump' ];
		}

		$new_template_item = [
			'slug'        => $template_slug,
			'id'          => $template_is_from_theme ? $theme_name . '//' . $template_slug : self::PLUGIN_SLUG . '//' . $template_slug,
			'path'        => $template_file,
			'type'        => $template_type,
			'theme'       => $template_is_from_theme ? $theme_name : self::PLUGIN_SLUG,
			// Plugin was agreed as a valid source value despite existing inline docs at the time of creating: https://github.com/WordPress/gutenberg/issues/36597#issuecomment-976232909.
			'source'      => $template_is_from_theme ? 'theme' : 'plugin',
			'title'       => $this->getBlockTemplateTitle( $template_slug ),
			'description' => $this->getBlockTemplateDescription( $template_slug ),
			'post_types'  => $post_types,
		];

		return (object) $new_template_item;
	}

	/**
	 * Removes templates that were added to a theme's block-templates directory, but already had a customized version saved in the database.
	 *
	 * @param array<\WP_Block_Template>|array<\stdClass> $templates List of templates to run the filter on.
	 *
	 * @return array List of templates with duplicates removed. The customized alternative is preferred over the theme default.
	 */
	public static function removeThemeTemplatesWithCustomAlternative( $templates ) {

		// Get the slugs of all templates that have been customized and saved in the database.
		$customized_template_slugs = array_map(
			static function ( $template ) {
				return $template->slug;
			},
			array_values(
				array_filter(
					$templates,
					static function ( $template ) {
						// This template has been customized and saved as a post.
						return $template->source === 'custom';
					}
				)
			)
		);

		// Remove theme (i.e. filesystem) templates that have the same slug as a customized one. We don't need to check
		// for `woocommerce` in $template->source here because woocommerce templates won't have been added to $templates
		// if a saved version was found in the db. This only affects saved templates that were saved BEFORE a theme
		// template with the same slug was added.
		return array_values(
			array_filter(
				$templates,
				static function ( $template ) use ( $customized_template_slugs ) {
					// This template has been customized and saved as a post, so return it.
					return ! ( $template->source === 'theme' && in_array( $template->slug, $customized_template_slugs, true ) );
				}
			)
		);
	}

	/**
	 * Returns whether the passed `$template` has a title, and it's different from the slug.
	 *
	 * @param object $template The template object.
	 * @return bool
	 */
	public function templateHasTitle( $template ) {
		return ! empty( $template->title ) && $template->title !== $template->slug;
	}

	/**
	 * Get eligible public post types for portal-container template.
	 *
	 * Returns all public post types except for excluded ones like attachments
	 * and SureCart product-related post types.
	 *
	 * @since 1.6.0
	 *
	 * @return array<string> Array of post type names eligible for portal-container template.
	 */
	public static function get_portal_container_post_types(): array {
		$public_post_types   = get_post_types( [ 'public' => true ], 'names' );
		$excluded_post_types = [ 'attachment', 'sc_product', 'sc_collection', 'sc_bump' ];
		return array_values( array_diff( $public_post_types, $excluded_post_types ) );
	}
}

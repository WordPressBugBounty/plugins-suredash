<?php
/**
 * PageBuilder.
 *
 * @package SureDash
 * @since 1.0.0
 */

namespace SureDashboard\Inc\Compatibility;

use SureDashboard\Inc\Traits\Get_Instance;

defined( 'ABSPATH' ) || exit;

/**
 * Have compatibility with page builders & themes.
 *
 * @since 1.0.0
 */
class PageBuilder {
	use Get_Instance;

	/**
	 * Page builder.
	 *
	 * @var mixed
	 */
	private $page_builder = '';

	/**
	 * Constructor
	 *
	 * @since 1.0.0
	 */
	public function __construct() {
	}

	/**
	 * Get page builder.
	 *
	 * @param int $post_id Page ID.
	 *
	 * @since 1.0.0
	 * @return string
	 */
	public function get_page_builder( $post_id ) {
		if ( empty( $this->page_builder ) ) {
			return $this->detect( $post_id );
		}

		return $this->page_builder;
	}

	/**
	 * Detect page builder.
	 *
	 * 1. Elementor
	 * 2. Thrive
	 * 3. Beaver Builder
	 * 4. WPBakery
	 * 5. Divi
	 * 6. Brizy
	 * 7. Bricks
	 * 8. Breakdance
	 * 9. Block Editor
	 *
	 * @param int $post_id Page ID.
	 *
	 * @since 1.0.0
	 * @return string
	 */
	public function detect( $post_id ) {
		$this->page_builder = suredash_detect_page_builder( $post_id );
		return $this->page_builder;
	}

	/**
	 * Get remote Page content.
	 *
	 * @param int $post_id remote Page ID.
	 * @since 1.0.0
	 */
	public function get_page_content( $post_id ): void {
		$page_builder = $this->get_page_builder( $post_id );
		$current_post = sd_get_post( $post_id );

		if ( ! $current_post ) {
			return;
		}

		switch ( $page_builder ) {
			case 'elementor':
				$elementor_instance = class_exists( '\Elementor\Plugin' ) ? \Elementor\Plugin::instance() : null;
				if ( $elementor_instance ) {
					echo do_shortcode( suredash_dynamic_content_support( $elementor_instance->frontend->get_builder_content_for_display( $post_id ) ) );
				}
				break;

			case 'thrive':
				// set the main wp query for the post.
				wp( 'p=' . $post_id );

				$tve_content = apply_filters( 'the_content', $current_post->post_content ?? '' ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound

				if ( isset( $_REQUEST[ TVE_EDITOR_FLAG ] ) ) {  // phpcs:ignore WordPress.Security.NonceVerification.Recommended
					$tve_content = str_replace( 'id="tve_editor"', '', $tve_content );
				}

				echo do_shortcode( suredash_dynamic_content_support( $tve_content ) );

				wp_reset_postdata();
				break;

			case 'beaver-builder':
				if ( ! apply_filters( 'suredashboard_bb_render_content_by_id', false ) ) {
					if ( class_exists( 'FLBuilderShortcodes' ) && is_callable( 'FLBuilderShortcodes::insert_layout' ) ) {
						echo do_shortcode(
							\FLBuilderShortcodes::insert_layout(
								[ // WPCS: XSS OK.
									'id' => $post_id,
								]
							)
						);
					}
				} else {
					if ( class_exists( 'FLBuilder' ) && is_callable( 'FLBuilder::render_content_by_id' ) ) {
						\FLBuilder::render_content_by_id(
							$post_id,
							'div',
							[]
						);
					}
				}
				break;

			case 'wpbakery':
				echo do_shortcode( suredash_dynamic_content_support( $current_post->post_content ?? '' ) );
				break;

			case 'divi':
				if ( isset( $current_post->post_content ) ) {
					$current_post->post_content = $this->add_divi_wrap( $current_post->post_content );
					$current_post->post_content = apply_filters( 'the_content', $current_post->post_content );// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound

					if ( strpos( $current_post->post_content, '<div id="et-boc" class="et-boc">' ) === false ) {
						$current_post->post_content = $this->add_main_divi_wrapper( $current_post->post_content );
					}

					echo do_shortcode( suredash_dynamic_content_support( $current_post->post_content ) );
					wp_reset_postdata();
				}
				break;

			case 'brizy':
				$post = class_exists( 'Brizy_Editor_Post' ) ? \Brizy_Editor_Post::get( $post_id ) : 0;
				if ( $post && $post->uses_editor() && class_exists( 'Brizy_Editor_Project' ) ) {
					$content = apply_filters( 'brizy_content', $post->get_compiled_html(), \Brizy_Editor_Project::get(), $post->get_wp_post() ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound

					echo do_shortcode( suredash_dynamic_content_support( $content ) );
				}
				break;

			case 'bricks':
				if ( defined( 'BRICKS_DB_PAGE_CONTENT' ) && class_exists( 'Bricks\Frontend' ) ) {
					if ( absint( get_the_ID() ) !== $post_id ) {
						echo do_shortcode( '[bricks_template id="' . $post_id . '"]' );
					} else {
						$bricks_data = get_post_meta( $post_id, BRICKS_DB_PAGE_CONTENT, true );
						if ( $bricks_data ) {
							echo do_shortcode( suredash_dynamic_content_support( \Bricks\Frontend::render_content( $bricks_data ) ) );
						}
					}
				}
				break;

			case 'breakdance':
				if ( function_exists( '\Breakdance\Render\render' ) ) {
					try {
						$content = \Breakdance\Render\render( $post_id );
						if ( $content ) {
							echo do_shortcode( suredash_dynamic_content_support( $content ) );
						}
					} catch ( \Exception $e ) {
						if ( function_exists( '\Breakdance\Data\get_tree_as_html' ) ) {
							echo do_shortcode( suredash_dynamic_content_support( \Breakdance\Data\get_tree_as_html( $post_id ) ) );
						}
					}
				} elseif ( function_exists( '\Breakdance\Data\get_tree_as_html' ) ) {
					echo do_shortcode( suredash_dynamic_content_support( \Breakdance\Data\get_tree_as_html( $post_id ) ) );
				}
				break;

			default:
			case 'block-editor':
				$post_content = $current_post->post_content ?? '';
				$output       = suredash_render_post_content( $post_content );

				ob_start();
				echo do_shortcode( suredash_dynamic_content_support( $output ) );
				echo do_shortcode( (string) ob_get_clean() );
				break;
		}
	}

	/**
	 * Adds Divi main wrapper container to post content.
	 *
	 * @since 0.0.4
	 *
	 * @param string $content Post content.
	 * @return string         Post content.
	 */
	public function add_main_divi_wrapper( $content ) {
		return sprintf(
			'<div id="%2$s" class="%2$s">
				%1$s
			</div>',
			$content,
			esc_attr( 'et-boc' )
		);
	}

	/**
	 * Adds Divi wrapper container to post content.
	 *
	 * @since 1.0.0
	 *
	 * @param string $content Post content.
	 * @return string         Post content.
	 */
	public function add_divi_wrap( $content ) {
		$outer_class   = apply_filters( 'et_builder_outer_content_class', [ 'et_builder_outer_content' ] );// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound
		$outer_classes = implode( ' ', $outer_class );

		$outer_id = apply_filters( 'et_builder_outer_content_id', 'et_builder_outer_content' );// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound

		$inner_class   = apply_filters( 'et_builder_inner_content_class', [ 'et_builder_inner_content' ] );// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound
		$inner_classes = implode( ' ', $inner_class );

		return sprintf(
			'<div class="%2$s" id="%4$s">
				<div class="%3$s">
					%1$s
				</div>
			</div>',
			$content,
			esc_attr( $outer_classes ),
			esc_attr( $inner_classes ),
			esc_attr( $outer_id )
		);
	}

	/**
	 * Get remote content assets.
	 *
	 * @param int $post_id remote content ID.
	 * @since 1.0.0
	 */
	public function enqueue_page_assets( $post_id ): void {
		$page_builder = $this->get_page_builder( $post_id );
		$css_file     = null;

		switch ( $page_builder ) {
			case 'elementor':
				if ( class_exists( '\Elementor\Core\Files\CSS\Post' ) ) {
					$css_file = new \Elementor\Core\Files\CSS\Post( $post_id );
					$css_file->enqueue();
				} elseif ( class_exists( '\Elementor\Post_CSS_File' ) ) {
					$css_file = new \Elementor\Post_CSS_File( $post_id );
					$css_file->enqueue();
				}
				break;

			case 'thrive':
				break;

			case 'beaver-builder':
				if ( class_exists( 'FLBuilder' ) && is_callable( 'FLBuilder::enqueue_layout_styles_scripts_by_id' ) ) {
					\FLBuilder::enqueue_layout_styles_scripts_by_id( $post_id );
				}
				break;

			case 'wpbakery':
			case 'divi':
				// Nothing to do.
				break;

			case 'brizy':
				$prefix = class_exists( 'Brizy_Editor' ) ? \Brizy_Editor::prefix() : 'brizy';

				if ( isset( $_GET[ "{$prefix}-edit-iframe" ] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
					return;
				}

				try {
					$post = class_exists( 'Brizy_Editor_Post' ) ? \Brizy_Editor_Post::get( $post_id ) : 0;
					$main = class_exists( 'Brizy_Public_Main' ) ? \Brizy_Public_Main::get( $post ) : new \Brizy_Public_Main( $post ); // @phpstan-ignore-line
				} catch ( \Exception $e ) {
					return;
				}

				$needs_compile = ! $post->isCompiledWithCurrentVersion() || $post->get_needs_compile();

				if ( $needs_compile ) {
					try {
						$post->compile_page();
						$post->saveStorage();
						$post->savePost();
					} catch ( \Exception $e ) {
						return;
					}
				}

				// Add page CSS.
				add_filter( 'body_class', [ $main, 'body_class_frontend' ] ); // @phpstan-ignore-line
				add_action(
					'wp_enqueue_scripts',
					static function () use ( $main ): void {
						if ( ! wp_script_is( 'brizy-preview' ) ) {
							add_action( 'wp_enqueue_scripts', [ $main, '_action_enqueue_preview_assets' ], 10001 ); // @phpstan-ignore-line
						}
					},
					10000
				);

				add_action(
					'wp_head',
					static function () use ( $post ): void {
						if ( class_exists( 'Brizy_Editor_CompiledHtml' ) ) {
							$html = new \Brizy_Editor_CompiledHtml( $post->get_compiled_html() );
							echo do_shortcode( $html->get_head() );
						}
					}
				);

				if ( $post && $post->uses_editor() ) {
					// Add page admin edit menu.
					add_action(
						'admin_bar_menu',
						static function ( $wp_admin_bar ) use ( $post ): void {
							$wp_post_id = $post->get_wp_post()->ID;
							$args       = [
								'id'    => 'brizy_Edit_page_' . $wp_post_id . '_link',
								/* translators: %s is the page title */
								'title' => sprintf( __( 'Edit %1$s with %2$s', 'suredash' ), get_the_title( $wp_post_id ), class_exists( 'Brizy_Editor' ) && is_callable( 'Brizy_Editor::get' ) ? \Brizy_Editor::get()->get_name() : 'Brizy' ),
								'href'  => $post->edit_url(),
								'meta'  => [],
							];

							if ( $wp_admin_bar->get_node( 'brizy_Edit_page_link' ) === true ) {
								$args['parent'] = 'brizy_Edit_page_link';
							}

							$wp_admin_bar->add_node( $args );
						},
						1000
					);
				}
				break;

			case 'bricks':
				$inline_style = '
					.portal-content-type-wordpress > .brxe-container {
						width: auto !important;
					}
				';
				wp_add_inline_style( 'portal-global', $inline_style );
				break;

			case 'breakdance':
				// Simple approach: trigger Breakdance's asset loading.
				if ( function_exists( '\Breakdance\Render\render' ) ) {
					\Breakdance\Render\render( $post_id );
				}

				if ( function_exists( '\Breakdance\Render\renderHtmlFromScriptAndStyleHolder' ) && class_exists( '\Breakdance\Render\ScriptAndStyleHolder' ) ) {
					$holder        = \Breakdance\Render\ScriptAndStyleHolder::getInstance();
					$assets_result = \Breakdance\Render\renderHtmlFromScriptAndStyleHolder( $holder );

					// Output the complete assets HTML to wp_head for better font handling.
					add_action(
						'wp_head',
						static function() use ( $assets_result ): void {
							// Rendering only, not making changes.
							echo $assets_result['headerHtml']; //phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
						},
						5
					);

					// Output footer assets to wp_footer.
					if ( ! empty( $assets_result['footerHtml'] ) ) {
						add_action(
							'wp_footer',
							static function() use ( $assets_result ): void {
								echo $assets_result['footerHtml']; //phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
							},
							5
						);
					}
				}
				break;

			case 'spectra':
				wp_enqueue_style( 'wp-block-library' );
				$spectra_content = get_post_field( 'post_content', $post_id );
				if ( defined( 'UAGB_VER' ) && class_exists( 'UAGB_Post_Assets' ) && is_string( $spectra_content ) && strpos( $spectra_content, '<!-- wp:uagb/' ) !== false ) {
					$post_assets = new \UAGB_Post_Assets( $post_id );
					$post_assets->enqueue_scripts();
				}
				break;

			default:
			case 'block-editor':
				wp_enqueue_style( 'wp-block-library' );

				break;
		}
	}
}

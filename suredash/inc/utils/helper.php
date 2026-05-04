<?php
/**
 * Helper.
 *
 * @package SureDash
 * @since 0.0.1
 */

namespace SureDashboard\Inc\Utils;

use SureDashboard\Core\Models\Controller;
use WP_Comment;

/**
 * Initialize setup
 *
 * @since 0.0.1
 * @package SureDash
 */

defined( 'ABSPATH' ) || exit;

/**
 * This class setup all AJAX action
 *
 * @class Ajax
 */
class Helper {
	/**
	 * Returns an option from the database for the admin settings.
	 *
	 * @param  string $key     The option key.
	 * @param  mixed  $default Option default value if option is not available.
	 * @return mixed   Returns the option value
	 *
	 * @since 0.0.1
	 */
	public static function get_option( $key, $default = false ) {
		$portal_settings = Settings::get_suredash_settings();

		if ( empty( $portal_settings ) || ! is_array( $portal_settings ) || ! array_key_exists( $key, $portal_settings ) ) {
			$portal_settings[ $key ] = '';
		}

		// Get the setting option if we're in the admin panel.
		$value = $portal_settings[ $key ];

		if ( $value === '' && $default !== false ) {
			return $default;
		}

		return $value;
	}

	/**
	 * Update option from the database for the admin settings.
	 *
	 * @param  string $key      The option key.
	 * @param  mixed  $value    Option value to update.
	 * @return string           Return the option value
	 *
	 * @since 0.0.1
	 */
	public static function update_option( $key, $value = true ) {
		$portal_settings = Settings::get_suredash_settings( false );

		if ( ! is_array( $portal_settings ) ) {
			$portal_settings = [];
		}

		// If the value is same as default then remove it from the DB.
		// This will help in the translatable strings.
		if ( Settings::get_default_option( $key ) === $value ) {
			unset( $portal_settings[ $key ] );
		} else {
			$portal_settings[ $key ] = $value;
		}

		update_option( SUREDASHBOARD_SETTINGS, $portal_settings );

		// Invalidate the static cache so subsequent get_option calls read fresh data.
		Settings::$dashboard_options = [];

		return $value;
	}

	/**
	 * Delete option from the database for the admin settings.
	 *
	 * @param  string $key The option key.
	 * @return bool        Returns true if the option was deleted, false otherwise.
	 *
	 * @since 1.0.0
	 */
	public static function delete_option( $key ) {
		$portal_settings = get_option( SUREDASHBOARD_SETTINGS );

		if ( empty( $portal_settings ) || ! is_array( $portal_settings ) ) {
			return false;
		}

		// If the key does not exist, return false.
		if ( ! isset( $portal_settings[ $key ] ) ) {
			return false;
		}

		if ( isset( $portal_settings[ $key ] ) ) {
			unset( $portal_settings[ $key ] );
			update_option( SUREDASHBOARD_SETTINGS, $portal_settings );
		}

		return true;
	}

	/**
	 * Resolve a media URL to a safe absolute path inside the WordPress uploads directory.
	 *
	 * Prevents path traversal in user-supplied media URLs. Returns the canonical
	 * filesystem path only when the URL maps to a real file strictly inside the uploads
	 * basedir. Any traversal sequence, encoded traversal, mismatched base URL, symlink,
	 * or out-of-range realpath returns null.
	 *
	 * @param string        $url                 Media URL to resolve.
	 * @param array<string> $allowed_extensions  Optional. Lowercase file extensions to allow. Empty array allows any extension.
	 * @return string|null Canonical absolute path inside the uploads directory, or null if unsafe.
	 * @since 1.8.1
	 */
	public static function get_safe_uploads_path( $url, array $allowed_extensions = [] ) {
		if ( ! is_string( $url ) || $url === '' ) {
			return null;
		}

		$upload_dir = wp_upload_dir();
		if ( ! empty( $upload_dir['error'] ) ) {
			return null;
		}

		$upload_baseurl = (string) $upload_dir['baseurl'];
		$upload_basedir = wp_normalize_path( (string) $upload_dir['basedir'] );

		$real_basedir = realpath( $upload_basedir );
		if ( $real_basedir === false ) {
			return null;
		}
		$real_basedir_norm = trailingslashit( wp_normalize_path( $real_basedir ) );

		// Decode percent-encoded sequences once so an encoded `..%2F` cannot bypass the check.
		$decoded_url = rawurldecode( $url );

		// Reject any traversal marker in the original or decoded URL.
		if ( strpos( $url, '..' ) !== false || strpos( $decoded_url, '..' ) !== false ) {
			return null;
		}

		// Strict prefix match — scheme/host/path of the uploads base URL must match exactly.
		if ( strpos( $decoded_url, $upload_baseurl ) !== 0 ) {
			return null;
		}

		// Restrict to expected extensions when an allowlist is supplied.
		$url_path = (string) wp_parse_url( $decoded_url, PHP_URL_PATH );
		if ( ! empty( $allowed_extensions ) ) {
			$extension = strtolower( pathinfo( $url_path, PATHINFO_EXTENSION ) );
			if ( ! in_array( $extension, $allowed_extensions, true ) ) {
				return null;
			}
		}

		$relative  = substr( $decoded_url, strlen( $upload_baseurl ) );
		$candidate = wp_normalize_path( $upload_basedir . $relative );

		if ( ! file_exists( $candidate ) || is_link( $candidate ) ) {
			return null;
		}

		$real_candidate = realpath( $candidate );
		if ( $real_candidate === false ) {
			return null;
		}

		$real_candidate_norm = wp_normalize_path( $real_candidate );
		if ( strpos( $real_candidate_norm, $real_basedir_norm ) !== 0 ) {
			return null;
		}

		return $real_candidate;
	}

	/**
	 * Get placeholder image URL.
	 *
	 * @access public
	 *
	 * @return string Placeholder image URL.
	 * @since 0.0.1
	 */
	public static function get_placeholder_image() {
		return apply_filters( 'suredash_placeholder_image', SUREDASHBOARD_URL . 'assets/images/placeholder.jpg' );
	}

	/**
	 * Get placeholder image.
	 *
	 * @access public
	 *
	 * @param string $icon Icon name.
	 * @param string $color Color name.
	 * @param string $label Optional label to display instead of icon.
	 *
	 * @return string Placeholder image.
	 * @since 1.0.0
	 */
	public static function get_solid_color_placeholder_image( $icon, $color, $label = '' ) {
		if ( ! empty( $label ) ) {
			// Use custom label if provided, split words onto separate lines.
			$words           = explode( ' ', trim( $label ) );
			$formatted_words = array_map(
				static function( $word ) {
					return '<span>' . esc_html( $word ) . '</span>';
				},
				$words
			);
			$label_html      = implode( '<br>', $formatted_words );
			$inner_content   = '<span class="sd-flex-col sd-items-center sd-bg-' . $color . '-100 sd-p-16 sd-shadow-2xl sd-radius-8 sd-color-dark sd-font-bold sd-font-16" style="line-height: 0.9; text-align: center;">' . $label_html . '</span>';
		} else {
			// Fallback to icon.
			$inner_content = self::get_library_icon( $icon, false, 'md', 'sd-bg-' . $color . '-100 sd-p-16 sd-shadow-2xl sd-radius-9999 sd-stroke-dark', );
		}

		$solid_background_markup = sprintf(
			'<div class="sd-thumbnail-image sd-bg-' . $color . '-50">%s</div>',
			$inner_content
		);

		return apply_filters( 'suredash_solid_color_placeholder_image', $solid_background_markup );
	}
	/**
	 * Get banner placeholder image URL.
	 *
	 * @access public
	 * @return string Banner placeholder image URL.
	 * @since 0.0.1
	 */
	public static function get_banner_placeholder_image() {
		return apply_filters( 'suredash_banner_placeholder_image', SUREDASHBOARD_URL . 'assets/images/banner-placeholder.jpg' );
	}

	/**
	 * Get default space featured image markup.
	 *
	 * @access public
	 * @param int    $post_id Post ID.
	 * @param bool   $fallback_placeholder Fallback to placeholder image.
	 * @param string $placeholder_bg_color Placeholder background color.
	 * @param string $icon Icon name for the placeholder.
	 * @param string $label Optional label to display instead of icon.
	 * @param bool   $banner Whether to use banner mode.
	 *
	 * @since 1.0.0
	 * @return string
	 */
	public static function get_space_featured_image( $post_id, $fallback_placeholder = true, $placeholder_bg_color = 'red', $icon = 'Link', $label = '', $banner = false ) {
		$featured_link = PostMeta::get_post_meta_value( $post_id, 'image_url' );

		// Fallback to WordPress featured image if image_url is empty.
		if ( empty( $featured_link ) ) {
			$thumbnail_id = get_post_thumbnail_id( $post_id );
			if ( $thumbnail_id ) {
				$featured_link = wp_get_attachment_image_url( $thumbnail_id, 'large' );
			}
		}

		if ( empty( $featured_link ) && $fallback_placeholder ) {
			$is_private = function_exists( 'suredash_is_post_protected' ) ? suredash_is_post_protected( $post_id, true ) : false;
			if ( $is_private ) {
				return self::get_solid_color_placeholder_image( 'lock', $placeholder_bg_color, $label );
			}
			return self::get_solid_color_placeholder_image( $icon, $placeholder_bg_color, $label );
		}

		if ( empty( $featured_link ) && $fallback_placeholder === false ) {
			return '';
		}

		return '<img src="' . esc_url( $featured_link ) . '" alt="' . esc_attr( get_the_title( $post_id ) ) . '" class="portal-item-featured-image ' . ( $banner ? '' : 'sd-thumbnail-image' ) . '">';
	}

	/**
	 * Get default space banner image markup.
	 *
	 * @access public
	 * @param int  $post_id Post ID.
	 * @param bool $fallback_placeholder Fallback to placeholder image.
	 * @since 0.0.1
	 * @return string
	 */
	public static function get_space_banner_image( $post_id, $fallback_placeholder = true ) {
		$featured_link = PostMeta::get_post_meta_value( $post_id, 'banner_url' );

		if ( empty( $featured_link ) && $fallback_placeholder ) {
			$featured_link = self::get_placeholder_image();
		}

		if ( empty( $featured_link ) ) {
			return '';
		}

		return '<img src="' . esc_url( $featured_link ) . '" alt="' . esc_attr( get_the_title( $post_id ) ) . '" class="portal-item-featured-image">';
	}

	/**
	 * Get course space featured image markup.
	 *
	 * @access public
	 * @param int  $post_id Post ID.
	 * @param bool $fallback_placeholder Fallback to placeholder image.
	 * @since 0.0.6
	 * @return string
	 */
	public static function get_course_featured_image( $post_id, $fallback_placeholder = true ) {
		$featured_link = PostMeta::get_post_meta_value( $post_id, 'course_thumbnail_url' );

		if ( empty( $featured_link ) && $fallback_placeholder ) {
			$featured_link = self::get_placeholder_image();
		}

		if ( empty( $featured_link ) ) {
			return '';
		}

		return '<img src="' . esc_url( $featured_link ) . '" alt="' . esc_attr( get_the_title( $post_id ) ) . '" class="portal-item-featured-image">';
	}

	/**
	 * Get CSS value
	 *
	 * Syntax:
	 *
	 *  get_css_value( VALUE, UNIT );
	 *
	 * E.g.
	 *
	 *  get_css_value( VALUE, 'em' );
	 *
	 * @param string $value  CSS value.
	 * @param string $unit  CSS unit.
	 * @since 0.0.1
	 *
	 * @return string
	 */
	public static function get_css_value( $value = '', $unit = '' ) {
		$css_val = '';

		if ( is_array( $value ) || is_array( $unit ) ) {
			return $css_val;
		}

		if ( $value !== '' ) {
			$css_val = esc_attr( $value ) . $unit;
		}

		return $css_val;
	}

	/**
	 * Parse blocks CSS into correct CSS syntax.
	 *
	 * @param array<mixed> $combined_selectors The combined selector array.
	 * @param string       $id The selector ID.
	 * @since 0.0.1
	 *
	 * @return array<string, string>
	 */
	public static function generate_all_css( $combined_selectors, $id ) {
		return [
			'desktop' => self::generate_css( $combined_selectors['desktop'], $id ),
			'tablet'  => self::generate_css( $combined_selectors['tablet'], $id ),
			'mobile'  => self::generate_css( $combined_selectors['mobile'], $id ),
		];
	}

	/**
	 * Parse blocks CSS into correct CSS syntax.
	 *
	 * @param array<mixed> $selectors The block selectors.
	 * @param string       $id The selector ID.
	 * @since 0.0.1
	 *
	 * @return string
	 */
	public static function generate_css( $selectors, $id ) {
		$styling_css = '';

		if ( ! empty( $selectors ) ) {
			foreach ( $selectors as $key => $value ) {
				$css = '';

				foreach ( $value as $j => $val ) {
					if ( $j === 'font-family' && $val === 'Default' ) {
						continue;
					}

					if ( ! empty( $val ) && is_array( $val ) ) {
						foreach ( $val as $key => $css_value ) {
							$css .= $key . ': ' . $css_value . ';';
						}
					} elseif ( ! empty( $val ) || $val === 0 ) {
						if ( $j === 'font-family' ) {
							$css .= $j . ': "' . $val . '";';
						} else {
							$css .= $j . ': ' . $val . ';';
						}
					}
				}

				if ( ! empty( $css ) ) {
					$styling_css .= $id;
					$styling_css .= $key . '{';
					$styling_css .= $css . '}';
				}
			}
		}

		return $styling_css;
	}

	/**
	 * Method to get the skeleton
	 *
	 * @param string $for Argument for skeleton type.
	 *
	 * @return string|false Returns skeleton HTML.
	 *
	 * @since 0.0.1
	 */
	public static function get_skeleton( $for ) {
		$structure = [];
		switch ( $for ) {
			case 'search':
				$structure = [
					[
						'type' => 'paragraphs',
						'data' => [
							[ 'count' => [ 20, 40, 50, 40, 30, 40 ] ],
						],
					],
				];
				break;
			default:
				break;
		}

		ob_start();
		?>
		<div class="portal-skeleton-container">
			<div class="portal-skeleton-content portal-skeleton-<?php echo esc_attr( $for ); ?>">
			<?php
			foreach ( $structure as $section ) {
				switch ( $section['type'] ) {
					case 'paragraphs':
						if ( is_array( $section['data'] ) ) {
							foreach ( $section['data'] as $row ) {
								if ( is_array( $row['count'] ) ) {
									$widths = $row['count'];
									foreach ( $widths as $key => $width ) {
										$last_row = ( $key === count( $widths ) - 1 );
										echo wp_kses_post( '<div class="portal-skeleton-row" style="width: ' . $width . '%; height: 16px; margin-bottom: ' . ( $last_row ? '32px' : '12px' ) . '"></div>' );
									}
								} else {
									/**
									 * Defining the row count.
									 *
									 * @var int $count row count
									 */
									$count = (int) $row['count'];

									for ( $j = 0; $j < $count; $j++ ) {
										$last_row = $j === $count - 1;
										$min      = $last_row ? 50 : 90;
										$max      = $last_row ? 80 : 100;
										$width    = wp_rand( $min, $max );
										echo wp_kses_post( '<div class="portal-skeleton-row" style="width: ' . $width . '%; height: 16px; margin-bottom: ' . ( $last_row ? '32px' : '12px' ) . '"></div>' );
									}
								}
							}
						}
						break;
				}
			}
			?>
			</div>
		</div>
			<?php
			return ob_get_clean();
	}

	/**
	 * Method to get the doc order.
	 *
	 * @param int|WP_term|object $term Doc category term ID, instance or object.
	 * @param array<mixed>       $args Default docs arguments.
	 *
	 * @return array<mixed> Returns array or docs order.
	 * @since 0.0.1
	 */
	public static function get_items_order_sequence( $term, $args = [] ) {
		$term = get_term( $term );
		if ( ! is_object( $term ) || is_wp_error( $term ) ) {
			return [];
		}

		$order_sequence   = [];
		$order_posts_meta = get_term_meta( $term->term_id, '_link_order', true );
		if ( ! empty( $order_posts_meta ) ) {
			$order_sequence = explode( ',', $order_posts_meta );
		}

		// Fetch unordered docs ( old or newly created docs ) which are not there is docs order.
		global $wpdb;
		$query = "SELECT p.ID FROM {$wpdb->posts} p
			JOIN {$wpdb->term_relationships} tr ON p.ID = tr.object_id
			JOIN {$wpdb->term_taxonomy} tt ON tr.term_taxonomy_id = tt.term_taxonomy_id
			WHERE tt.term_id = %d
			AND p.post_type = %s";

		$query_values = [ $term->term_id, SUREDASHBOARD_POST_TYPE ];

		// Post status to include.
		if ( isset( $args['post_status'] ) ) {
			$query         .= ' AND p.post_status = %s';
			$query_values[] = $args['post_status'];
		}

		// Avoid docs that are already in docs order sequence meta.
		if ( ! empty( $order_sequence ) ) {
			$placeholders = implode( ',', array_fill( 0, count( $order_sequence ), '%d' ) );
			$query       .= " AND p.ID NOT IN ({$placeholders})";
			$query_values = array_merge( $query_values, array_map( 'intval', $order_sequence ) );
		}

		// phpcs:disable
		$unordered_spaces = $wpdb->get_col($wpdb->prepare($query, ...$query_values));
		// phpcs:enable

		// Add unordered spaces at the end.
		if ( ! empty( $unordered_spaces ) ) {
			$order_sequence = array_merge( $order_sequence, $unordered_spaces );
		}

		return $order_sequence;
	}

	/**
	 * Return search results only by post title.
	 *
	 * @param string $search   Search SQL for WHERE clause.
	 * @param object $wp_query The current WP_Query object.
	 *
	 * @return string The Modified Search SQL for WHERE clause.
	 */
	public static function search_only_titles( $search, $wp_query ) {
		if ( ! empty( $search ) && is_object( $wp_query ) && ! empty( $wp_query->query_vars['search_terms'] ) ) {
			global $wpdb;

			$q = $wp_query->query_vars;
			$n = ! empty( $q['exact'] ) ? '' : '%';

			$search = [];

			foreach ( (array) $q['search_terms'] as $term ) {
				$search[] = $wpdb->prepare( "{$wpdb->posts}.post_title LIKE %s", $n . $wpdb->esc_like( $term ) . $n );
			}

			if ( ! is_user_logged_in() ) {
				$search[] = "{$wpdb->posts}.post_password = ''";
			}

			$search = ' AND ' . implode( ' AND ', $search );
		}

		return $search;
	}

	/**
	 * Get the post excerpt by post ID.
	 *
	 * @param int  $post_id Post ID.
	 * @param bool $echo Whether to echo or return.
	 *
	 * @return string Post excerpt.
	 * @since 0.0.1
	 */
	public static function get_read_more_link( $post_id, $echo = true ) {
		$read_more_text = Labels::get_label( 'read_more' );
		$read_more_link = apply_filters( 'suredashboard_read_more_link', get_permalink( $post_id ), $post_id );
		$markup         = '<a href="' . esc_url( $read_more_link ) . '" class="portal-read-more-link">' . esc_html( $read_more_text ) . '</a>';

		if ( $echo ) {
			echo wp_kses_post( $markup );
			return '';
		}

		return $markup;
	}

	/**
	 * Returns an array of logo svg icons.
	 *
	 * @return array<mixed>
	 * @since 0.0.1
	 */
	public static function get_icon_library() {
		static $portal_all_svg_icons = [];

		if ( $portal_all_svg_icons ) {
			return $portal_all_svg_icons;
		}

		$icons_dir = SUREDASHBOARD_DIR . 'assets/icon-library';
		$file      = "{$icons_dir}/lucide-icons.php";

		if ( file_exists( $file ) ) {
			$icons                = include_once $file;
			$portal_all_svg_icons = $icons;
		}

		return apply_filters( 'suredashboard_icons_chunks', $portal_all_svg_icons );
	}

	/**
	 * Get the emoji library.
	 *
	 * @return array<string, array<string, string>> Emojis array.
	 * @since 1.5.0
	 */
	public static function get_emoji_library() {
		static $portal_all_emojis = [];

		if ( $portal_all_emojis ) {
			return $portal_all_emojis;
		}

		$emojis_dir = SUREDASHBOARD_DIR . 'assets/icon-library';
		$file       = "{$emojis_dir}/emojis.php";

		if ( file_exists( $file ) ) {
			$emojis            = include_once $file;
			$portal_all_emojis = $emojis;
		}

		return apply_filters( 'suredashboard_emojis_library', $portal_all_emojis );
	}

	/**
	 * Get the SVG icon markup by icon name.
	 *
	 * @param string $icon_handle Icon name.
	 * @param bool   $echo Whether to echo or return.
	 *
	 * @return mixed Icon markup or void.
	 * @since 0.0.1
	 */
	public static function get_library_svg( $icon_handle, $echo = false ) {
		$svg_icons = self::get_icon_library();
		$svg_logo  = ! empty( $svg_icons[ $icon_handle ]['rendered'] ) ? $svg_icons[ $icon_handle ]['rendered'] : '';

		if ( ! $svg_logo ) {
			return '';
		}

		if ( $echo ) {
			echo do_shortcode( $svg_logo );
		} else {
			return $svg_logo;
		}
	}

	/**
	 * Get the icon markup by icon name.
	 *
	 * @param string                $icon_handle Icon name.
	 * @param bool                  $echo Whether to echo or return.
	 * @param string                $size Icon size (sm, md, lg, xl).
	 * @param string                $classes Additional CSS classes.
	 * @param array<string, string> $data_attributes Additional data attributes.
	 * @param bool                  $skip_emoji_check Whether to skip emoji check.
	 *
	 * @return mixed Icon markup or void.
	 * @since 0.0.1
	 */
	public static function get_library_icon( $icon_handle, $echo = true, $size = 'sm', $classes = '', $data_attributes = [], $skip_emoji_check = false ) {
		// Check if it's a custom SVG URL (starts with http:// or https://).
		if ( is_string( $icon_handle ) && ( strpos( $icon_handle, 'http://' ) === 0 || strpos( $icon_handle, 'https://' ) === 0 ) ) {
			$data_attr_html = '';
			if ( ! empty( $data_attributes ) ) {
				foreach ( $data_attributes as $key => $value ) {
					$data_attr_html .= sprintf( ' data-%s="%s"', esc_attr( $key ), esc_attr( $value ) );
				}
			}

			$custom_svg_markup = '<span class="portal-custom-svg-icon portal-icon-' . $size . ' ' . esc_attr( $classes ) . '" aria-hidden="true" aria-label="Custom icon"' . do_shortcode( $data_attr_html ) . '><img src="' . esc_url( $icon_handle ) . '" alt="Custom icon" /></span>';

			if ( $echo ) {
				echo do_shortcode( apply_filters( 'suredashboard_library_icon', $custom_svg_markup, $icon_handle ) );
			} else {
				return apply_filters( 'suredashboard_library_icon', $custom_svg_markup, $icon_handle );
			}
			return;
		}

		// Check if it's an emoji.
		$emojis = self::get_emoji_library();
		if ( isset( $emojis[ $icon_handle ] ) && ! empty( $emojis[ $icon_handle ]['emoji'] ) && ! $skip_emoji_check ) {
			$emoji       = $emojis[ $icon_handle ]['emoji'];
			$emoji_label = ! empty( $emojis[ $icon_handle ]['label'] ) ? $emojis[ $icon_handle ]['label'] : 'Emoji';

			$data_attr_html = '';
			if ( ! empty( $data_attributes ) ) {
				foreach ( $data_attributes as $key => $value ) {
					$data_attr_html .= sprintf( ' data-%s="%s"', esc_attr( $key ), esc_attr( $value ) );
				}
			}

			$emoji_markup = '<span class="portal-emoji-icon portal-icon-' . $size . ' ' . esc_attr( $classes ) . '" aria-hidden="true" aria-label="' . esc_attr( $emoji_label ) . '"' . do_shortcode( $data_attr_html ) . '>' . $emoji . '</span>';

			if ( $echo ) {
				echo do_shortcode( apply_filters( 'suredashboard_library_icon', $emoji_markup, $icon_handle ) );
			} else {
				return apply_filters( 'suredashboard_library_icon', $emoji_markup, $icon_handle );
			}
			return;
		}

		// Fall back to icon rendering.
		$svg_icons   = self::get_icon_library();
		$icon_handle = ucfirst( $icon_handle );
		$svg_logo    = self::get_library_svg( $icon_handle );
		$svg_label   = ! empty( $svg_icons[ $icon_handle ]['label'] ) ? $svg_icons[ $icon_handle ]['label'] : 'Link';

		$data_attr_html = '';
		if ( ! empty( $data_attributes ) ) {
			foreach ( $data_attributes as $key => $value ) {
				$data_attr_html .= sprintf( ' data-%s="%s"', esc_attr( $key ), esc_attr( $value ) );
			}
		}

		if ( $svg_logo ) {
			$svg_logo = '<span class="portal-svg-icon portal-icon-' . $size . ' ' . esc_attr( $classes ) . '" aria-hidden="true" aria-label="' . esc_attr( $svg_label ) . '"' . do_shortcode( $data_attr_html ) . '>' . $svg_logo . '</span>';
		}

		if ( $echo ) {
			echo do_shortcode( apply_filters( 'suredashboard_library_icon', $svg_logo, $icon_handle ) );
		} else {
			return apply_filters( 'suredashboard_library_icon', $svg_logo, $icon_handle );
		}
	}

	/**
	 * Generate a badge with icon and text.
	 *
	 * @param string       $type Badge type/style (primary, secondary, neutral, success, danger, warning).
	 * @param string       $icon Icon handle for the badge.
	 * @param string       $text Text to display in the badge.
	 * @param string       $icon_size Size variant of the badge (sm, md, lg).
	 * @param string       $custom_classes Additional CSS classes for the badge.
	 * @param array<mixed> $data_attributes Optional. Array of data attributes ['attribute' => 'value'].
	 * @param bool         $omit_icon Whether to omit the icon from the badge.
	 *
	 * @return void.
	 * @since 0.0.2
	 */
	public static function show_badge( $type, $icon, $text, $icon_size = 'md', $custom_classes = '', $data_attributes = [], $omit_icon = false ): void {
		// Define allowed badge types.
		$allowed_types = [ 'primary', 'secondary', 'neutral', 'success', 'danger', 'warning', 'custom' ];

		// Validate and fallback for badge type.
		$type = in_array( $type, $allowed_types ) ? $type : 'primary';

		// Get icon markup.
		$icon_markup = self::get_library_icon( $icon, false, $icon_size );

		// Fallback to info icon if no icon found.
		if ( empty( $icon_markup ) && ! $omit_icon ) {
			$icon_markup = self::get_library_icon( 'info', false, $icon_size );
		}

		// Build data attributes string.
		$data_attrs = '';
		$style_vars = 'style="';
		foreach ( $data_attributes as $key => $value ) {
			$data_attrs .= sprintf( ' data-%s="%s"', esc_attr( $key ), esc_attr( $value ) );
			$style_vars .= sprintf( '--%s: %s;', 'portal-badge-' . esc_attr( $key ), esc_attr( $value ) );
		}
		$style_vars .= '"';

		// Build badge markup.
		$badge_text = '';
		if ( ! empty( $text ) ) {
			$badge_text_class = 'portal-badge-text';
			$badge_text       = sprintf( '<span class="%s">%s</span>', esc_attr( $badge_text_class ), esc_html( $text ) );
		}

		$badge = sprintf(
			'<span class="portal-badge sd-w-full sd-inline-flex sd-items-center sd-justify-center sd-max-w-fit sd-max-h-fit sd-radius-9999 sd-font-medium sd-cursor-default sd-nowrap portal-badge-%s %s" data-type="%s"%s%s>%s%s</span>',
			esc_attr( $icon_size ),
			esc_attr( $custom_classes ),
			esc_attr( $type ),
			$data_attrs,
			$style_vars,
			$icon_markup, // phpstan:ignore.
			$badge_text
		);

		// Allow filtering of final badge markup.
		echo do_shortcode( apply_filters( 'suredashboard_badge_markup', $badge, $type ) );
	}

	/**
	 * Method to return attribute type, default array.
	 *
	 * @param string $type    Attribute type.
	 * @param string $default Attribute default value.
	 *
	 * @return array<mixed> Returns attribute type, default array.
	 *
	 * @since 0.0.1
	 */
	public static function block_attr( $type = 'string', $default = '' ) {
		return [
			'type'    => $type,
			'default' => $default,
		];
	}

	/**
	 * Get Typography Dynamic CSS.
	 *
	 * @param  array<mixed> $attr The Attribute array.
	 * @param  string       $slug The field slug.
	 * @param  string       $selector The selector array.
	 * @param  array<mixed> $combined_selectors The combined selector array.
	 * @since  0.0.1
	 * @return array<mixed>
	 */
	public static function get_typography_css( $attr, $slug, $selector, $combined_selectors ) {
		$typo_css_desktop = [];
		$typo_css_tablet  = [];
		$typo_css_mobile  = [];

		$already_selectors_desktop = $combined_selectors['desktop'][ $selector ] ?? [];
		$already_selectors_tablet  = $combined_selectors['tablet'][ $selector ] ?? [];
		$already_selectors_mobile  = $combined_selectors['mobile'][ $selector ] ?? [];

		$family_slug     = $slug === '' ? 'fontFamily' : $slug . 'FontFamily';
		$weight_slug     = $slug === '' ? 'fontWeight' : $slug . 'FontWeight';
		$transform_slug  = $slug === '' ? 'fontTransform' : $slug . 'Transform';
		$decoration_slug = $slug === '' ? 'fontDecoration' : $slug . 'Decoration';
		$style_slug      = $slug === '' ? 'fontStyle' : $slug . 'FontStyle';

		$l_ht_slug        = $slug === '' ? 'lineHeight' : $slug . 'LineHeight';
		$f_sz_slug        = $slug === '' ? 'fontSize' : $slug . 'FontSize';
		$l_ht_type_slug   = $slug === '' ? 'lineHeightType' : $slug . 'LineHeightType';
		$f_sz_type_slug   = $slug === '' ? 'fontSizeType' : $slug . 'FontSizeType';
		$f_sz_type_t_slug = $slug === '' ? 'fontSizeTypeTablet' : $slug . 'FontSizeTypeTablet';
		$f_sz_type_m_slug = $slug === '' ? 'fontSizeTypeMobile' : $slug . 'FontSizeTypeMobile';
		$l_sp_slug        = $slug === '' ? 'letterSpacing' : $slug . 'LetterSpacing';
		$l_sp_type_slug   = $slug === '' ? 'letterSpacingType' : $slug . 'LetterSpacingType';

		$text_transform  = $attr[ $transform_slug ] ?? 'normal';
		$text_decoration = $attr[ $decoration_slug ] ?? 'none';
		$font_style      = $attr[ $style_slug ] ?? 'normal';

		$typo_css_desktop[ $selector ] = [
			'font-family'     => $attr[ $family_slug ],
			'text-transform'  => $text_transform,
			'text-decoration' => $text_decoration,
			'font-style'      => $font_style,
			'font-weight'     => $attr[ $weight_slug ],
			'font-size'       => isset( $attr[ $f_sz_slug ] ) ? self::get_css_value( $attr[ $f_sz_slug ], $attr[ $f_sz_type_slug ] ) : '',
			'line-height'     => isset( $attr[ $l_ht_slug ] ) ? self::get_css_value( $attr[ $l_ht_slug ], $attr[ $l_ht_type_slug ] ) : '',
			'letter-spacing'  => isset( $attr[ $l_sp_slug ] ) ? self::get_css_value( $attr[ $l_sp_slug ], $attr[ $l_sp_type_slug ] ) : '',
		];

		$typo_css_desktop[ $selector ] = array_merge(
			$typo_css_desktop[ $selector ],
			$already_selectors_desktop
		);

		$typo_css_tablet[ $selector ] = [
			'font-size'      => isset( $attr[ $f_sz_slug . 'Tablet' ] ) ? self::get_css_value( $attr[ $f_sz_slug . 'Tablet' ], $attr[ $f_sz_type_t_slug ] ?? $attr[ $f_sz_type_slug ] ) : '',
			'line-height'    => isset( $attr[ $l_ht_slug . 'Tablet' ] ) ? self::get_css_value( $attr[ $l_ht_slug . 'Tablet' ], $attr[ $l_ht_type_slug ] ) : '',
			'letter-spacing' => isset( $attr[ $l_sp_slug . 'Tablet' ] ) ? self::get_css_value( $attr[ $l_sp_slug . 'Tablet' ], $attr[ $l_sp_type_slug ] ) : '',
		];

		$typo_css_tablet[ $selector ] = array_merge(
			$typo_css_tablet[ $selector ],
			$already_selectors_tablet
		);

		$typo_css_mobile[ $selector ] = [
			'font-size'      => isset( $attr[ $f_sz_slug . 'Mobile' ] ) ? self::get_css_value( $attr[ $f_sz_slug . 'Mobile' ], $attr[ $f_sz_type_m_slug ] ?? $attr[ $f_sz_type_slug ] ) : '',
			'line-height'    => isset( $attr[ $l_ht_slug . 'Mobile' ] ) ? self::get_css_value( $attr[ $l_ht_slug . 'Mobile' ], $attr[ $l_ht_type_slug ] ) : '',
			'letter-spacing' => isset( $attr[ $l_sp_slug . 'Mobile' ] ) ? self::get_css_value( $attr[ $l_sp_slug . 'Mobile' ], $attr[ $l_sp_type_slug ] ) : '',
		];

		$typo_css_mobile[ $selector ] = array_merge(
			$typo_css_mobile[ $selector ],
			$already_selectors_mobile
		);

		return [
			'desktop' => array_merge(
				$combined_selectors['desktop'],
				$typo_css_desktop
			),
			'tablet'  => array_merge(
				$combined_selectors['tablet'],
				$typo_css_tablet
			),
			'mobile'  => array_merge(
				$combined_selectors['mobile'],
				$typo_css_mobile
			),
		];
	}

	/**
	 * Border attribute generation Function.
	 *
	 * @since 0.0.1
	 * @param  string $prefix   Attribute Prefix.
	 * @return array<string, string>
	 */
	public static function generate_border_attribute( $prefix ) {
		$border_attr = [];

		$device = [ '', 'Tablet', 'Mobile' ];

		foreach ( $device as $data ) {
			$border_attr[ "{$prefix}BorderTopWidth{$data}" ]          = '';
			$border_attr[ "{$prefix}BorderLeftWidth{$data}" ]         = '';
			$border_attr[ "{$prefix}BorderRightWidth{$data}" ]        = '';
			$border_attr[ "{$prefix}BorderBottomWidth{$data}" ]       = '';
			$border_attr[ "{$prefix}BorderTopLeftRadius{$data}" ]     = '';
			$border_attr[ "{$prefix}BorderTopRightRadius{$data}" ]    = '';
			$border_attr[ "{$prefix}BorderBottomLeftRadius{$data}" ]  = '';
			$border_attr[ "{$prefix}BorderBottomRightRadius{$data}" ] = '';
			$border_attr[ "{$prefix}BorderRadiusUnit{$data}" ]        = 'px';
		}

		$border_attr[ "{$prefix}BorderStyle" ]  = '';
		$border_attr[ "{$prefix}BorderColor" ]  = '';
		$border_attr[ "{$prefix}BorderHColor" ] = '';
		return $border_attr;
	}

	/**
	 * Background Control CSS Generator Function.
	 *
	 * @param array<string, mixed> $bg_obj          The background object with all CSS properties.
	 * @param string               $css_for_overlay The overlay option ('no' or 'yes') to determine whether to include overlay CSS. Leave empty for blocks that do not use the '::before' overlay.
	 *
	 * @return array<string, mixed>                  The formatted CSS properties for the background.
	 */
	public static function get_background_obj( $bg_obj, $css_for_overlay = '' ) {
		$gen_bg_css         = [];
		$gen_bg_overlay_css = [];

		$bg_type             = $bg_obj['backgroundType'] ?? '';
		$bg_img              = isset( $bg_obj['backgroundImage'] ) && isset( $bg_obj['backgroundImage']['url'] ) ? $bg_obj['backgroundImage']['url'] : '';
		$bg_color            = $bg_obj['backgroundColor'] ?? '';
		$gradient_value      = $bg_obj['gradientValue'] ?? '';
		$gradient_color1     = $bg_obj['gradientColor1'] ?? '';
		$gradient_color2     = $bg_obj['gradientColor2'] ?? '';
		$gradient_type       = $bg_obj['gradientType'] ?? '';
		$gradient_location1  = $bg_obj['gradientLocation1'] ?? '';
		$gradient_location2  = $bg_obj['gradientLocation2'] ?? '';
		$gradient_angle      = $bg_obj['gradientAngle'] ?? '';
		$select_gradient     = $bg_obj['selectGradient'] ?? '';
		$repeat              = $bg_obj['backgroundRepeat'] ?? '';
		$position            = $bg_obj['backgroundPosition'] ?? '';
		$size                = $bg_obj['backgroundSize'] ?? '';
		$attachment          = $bg_obj['backgroundAttachment'] ?? '';
		$overlay_type        = $bg_obj['overlayType'] ?? '';
		$overlay_opacity     = $bg_obj['overlayOpacity'] ?? '';
		$bg_image_color      = $bg_obj['backgroundImageColor'] ?? '';
		$bg_custom_size      = $bg_obj['backgroundCustomSize'] ?? '';
		$bg_custom_size_type = $bg_obj['backgroundCustomSizeType'] ?? '';
		$bg_video            = $bg_obj['backgroundVideo'] ?? '';
		$bg_video_color      = $bg_obj['backgroundVideoColor'] ?? '';

		$custom_position = $bg_obj['customPosition'] ?? '';
		$x_position      = $bg_obj['xPosition'] ?? '';
		$x_position_type = $bg_obj['xPositionType'] ?? '';
		$y_position      = $bg_obj['yPosition'] ?? '';
		$y_position_type = $bg_obj['yPositionType'] ?? '';

		$bg_overlay_img              = $bg_obj['backgroundOverlayImage']['url'] ?? '';
		$overlay_repeat              = $bg_obj['backgroundOverlayRepeat'] ?? '';
		$overlay_position            = $bg_obj['backgroundOverlayPosition'] ?? '';
		$overlay_size                = $bg_obj['backgroundOverlaySize'] ?? '';
		$overlay_attachment          = $bg_obj['backgroundOverlayAttachment'] ?? '';
		$blend_mode                  = $bg_obj['blendMode'] ?? '';
		$bg_overlay_custom_size      = $bg_obj['backgroundOverlayCustomSize'] ?? '';
		$bg_overlay_custom_size_type = $bg_obj['backgroundOverlayCustomSizeType'] ?? '';

		$custom_overlay__position = $bg_obj['customOverlayPosition'] ?? '';
		$x_overlay_position       = $bg_obj['xOverlayPosition'] ?? '';
		$x_overlay_position_type  = $bg_obj['xOverlayPositionType'] ?? '';
		$y_overlay_position       = $bg_obj['yOverlayPosition'] ?? '';
		$y_overlay_position_type  = $bg_obj['yOverlayPositionType'] ?? '';

		$custom_x_position = self::get_css_value( $x_position, $x_position_type );
		$custom_y_position = self::get_css_value( $y_position, $y_position_type );

		$gradient = '';
		if ( $size === 'custom' ) {
			$size = $bg_custom_size . $bg_custom_size_type;
		}
		if ( $select_gradient === 'basic' ) {
			$gradient = $gradient_value;
		} elseif ( $gradient_type === 'linear' && $select_gradient === 'advanced' ) {
			$gradient = 'linear-gradient(' . $gradient_angle . 'deg, ' . $gradient_color1 . ' ' . $gradient_location1 . '%, ' . $gradient_color2 . ' ' . $gradient_location2 . '%)';
		} elseif ( $gradient_type === 'radial' && $select_gradient === 'advanced' ) {
			$gradient = 'radial-gradient( at center center, ' . $gradient_color1 . ' ' . $gradient_location1 . '%, ' . $gradient_color2 . ' ' . $gradient_location2 . '%)';
		}

		if ( $bg_type !== '' ) {
			switch ( $bg_type ) {
				case 'color':
					if ( $bg_img !== '' && $bg_color !== '' ) {
						$gen_bg_css['background-image'] = 'linear-gradient(to right, ' . $bg_color . ', ' . $bg_color . '), url(' . $bg_img . ');';
					} elseif ( $bg_img === '' ) {
						$gen_bg_css['background-color'] = $bg_color . ';';
					}
					break;

				case 'image':
					$gen_bg_css['background-repeat'] = esc_attr( $repeat );

					if ( $custom_position !== 'custom' && isset( $position['x'] ) && isset( $position['y'] ) ) {
						$position_value                    = $position['x'] * 100 . '% ' . $position['y'] * 100 . '%';
						$gen_bg_css['background-position'] = $position_value;
					} elseif ( $custom_position === 'custom' ) {
						$position_value                    = $bg_obj['centralizedPosition'] === false ? $custom_x_position . ' ' . $custom_y_position : 'calc(50% +  ' . $custom_x_position . ') calc(50% + ' . $custom_y_position . ')';
						$gen_bg_css['background-position'] = $position_value;
					}

					$gen_bg_css['background-size'] = esc_attr( $size );

					$gen_bg_css['background-attachment'] = esc_attr( $attachment );

					if ( $overlay_type === 'color' && $bg_img !== '' && $bg_image_color !== '' ) {
						if ( ! empty( $css_for_overlay ) ) {
							$gen_bg_css['background-image']   = 'url(' . $bg_img . ');';
							$gen_bg_overlay_css['background'] = $bg_image_color;
							$gen_bg_overlay_css['opacity']    = $overlay_opacity;
						} else {
							$gen_bg_css['background-image'] = 'linear-gradient(to right, ' . $bg_image_color . ', ' . $bg_image_color . '), url(' . $bg_img . ');';
						}
					}
					if ( $overlay_type === 'gradient' && $bg_img !== '' && $gradient !== '' ) {
						if ( ! empty( $css_for_overlay ) ) {
							$gen_bg_css['background-image']         = 'url(' . $bg_img . ');';
							$gen_bg_overlay_css['background-image'] = $gradient;
							$gen_bg_overlay_css['opacity']          = $overlay_opacity;
						} else {
							$gen_bg_css['background-image'] = $gradient . ', url(' . $bg_img . ');';
						}
					}
					if ( $bg_img !== '' && in_array( $overlay_type, [ '', 'none', 'image' ] ) ) {
						$gen_bg_css['background-image'] = 'url(' . $bg_img . ');';
					}

					$gen_bg_css['background-clip'] = 'padding-box';
					break;

				case 'gradient':
					if ( isset( $gradient ) ) {
						$gen_bg_css['background']      = $gradient . ';';
						$gen_bg_css['background-clip'] = 'padding-box';
					}
					break;
				case 'video':
					if ( $overlay_type === 'color' && $bg_video !== '' && $bg_video_color !== '' ) {
						$gen_bg_css['background'] = $bg_video_color . ';';
					}
					if ( $overlay_type === 'gradient' && $bg_video !== '' && $gradient !== '' ) {
						$gen_bg_css['background-image'] = $gradient . ';';
					}
					break;

				default:
					break;
			}
		} elseif ( $bg_color !== '' ) {
			$gen_bg_css['background-color'] = $bg_color . ';';
		}

		// image overlay.
		if ( $overlay_type === 'image' ) {
			if ( $overlay_size === 'custom' ) {
				$overlay_size = $bg_overlay_custom_size . $bg_overlay_custom_size_type;
			}

			if ( $overlay_repeat ) {
				$gen_bg_overlay_css['background-repeat'] = esc_attr( $overlay_repeat );
			}
			if ( $custom_overlay__position !== 'custom' && $overlay_position && isset( $overlay_position['x'] ) && isset( $overlay_position['y'] ) ) {
				$position_overlay_value                    = $overlay_position['x'] * 100 . '% ' . $overlay_position['y'] * 100 . '%';
				$gen_bg_overlay_css['background-position'] = $position_overlay_value;
			} elseif ( $custom_overlay__position === 'custom' && $x_overlay_position && $y_overlay_position && $x_overlay_position_type && $y_overlay_position_type ) {
				$position_overlay_value                    = $x_overlay_position . $x_overlay_position_type . ' ' . $y_overlay_position . $y_overlay_position_type;
				$gen_bg_overlay_css['background-position'] = $position_overlay_value;
			}

			if ( $overlay_size ) {
				$gen_bg_overlay_css['background-size'] = esc_attr( $overlay_size );
			}

			if ( $overlay_attachment ) {
				$gen_bg_overlay_css['background-attachment'] = esc_attr( $overlay_attachment );
			}
			if ( $blend_mode ) {
				$gen_bg_overlay_css['mix-blend-mode'] = esc_attr( $blend_mode );
			}
			if ( $bg_overlay_img !== '' ) {
				$gen_bg_overlay_css['background-image'] = 'url(' . $bg_overlay_img . ');';
			}
			$gen_bg_overlay_css['background-clip'] = 'padding-box';
			$gen_bg_overlay_css['opacity']         = $overlay_opacity;
		}

		return $css_for_overlay === 'yes' ? $gen_bg_overlay_css : $gen_bg_css;
	}

	/**
	 * Border CSS generation Function.
	 *
	 * @since 0.0.1
	 * @param  array<string, string> $attr   Attribute List.
	 * @param  string                $prefix Attribute prefix .
	 * @param  string                $device Responsive.
	 * @return array<string, string>         border css array.
	 */
	public static function generate_border_css( $attr, $prefix, $device = 'desktop' ) {
		$gen_border_css = [];
		// ucfirst function is used to transform text into first letter capital.
		$device = $device === 'desktop' ? '' : ucfirst( $device );
		if ( $attr[ $prefix . 'BorderStyle' ] !== 'none' && ! empty( $attr[ $prefix . 'BorderStyle' ] ) ) {
			$gen_border_css['border-top-width']    = self::get_css_value( $attr[ $prefix . 'BorderTopWidth' . $device ], 'px' );
			$gen_border_css['border-left-width']   = self::get_css_value( $attr[ $prefix . 'BorderLeftWidth' . $device ], 'px' );
			$gen_border_css['border-right-width']  = self::get_css_value( $attr[ $prefix . 'BorderRightWidth' . $device ], 'px' );
			$gen_border_css['border-bottom-width'] = self::get_css_value( $attr[ $prefix . 'BorderBottomWidth' . $device ], 'px' );
		}
		$gen_border_unit                              = $attr[ $prefix . 'BorderRadiusUnit' . $device ] ?? 'px';
		$gen_border_css['border-top-left-radius']     = self::get_css_value( $attr[ $prefix . 'BorderTopLeftRadius' . $device ], $gen_border_unit );
		$gen_border_css['border-top-right-radius']    = self::get_css_value( $attr[ $prefix . 'BorderTopRightRadius' . $device ], $gen_border_unit );
		$gen_border_css['border-bottom-left-radius']  = self::get_css_value( $attr[ $prefix . 'BorderBottomLeftRadius' . $device ], $gen_border_unit );
		$gen_border_css['border-bottom-right-radius'] = self::get_css_value( $attr[ $prefix . 'BorderBottomRightRadius' . $device ], $gen_border_unit );

		$gen_border_css['border-style'] = $attr[ $prefix . 'BorderStyle' ];
		$gen_border_css['border-color'] = $attr[ $prefix . 'BorderColor' ];

		if ( $attr[ $prefix . 'BorderStyle' ] === 'default' ) {
			return [];
		}

		return $gen_border_css;
	}

	/**
	 * Returns recent docs stored in user's browser cookie.
	 *
	 * @return array<int, int|string> Returns first n recent docs.
	 *
	 * @since 0.0.1
	 */
	public static function get_recent_searched_items() {
		global $post;

		$recent_items_limit = apply_filters( 'portal_search_recent_items_limit', 4 );

		if ( ! is_int( $recent_items_limit ) || $recent_items_limit < 1 ) {
			return [];
		}

		// Validate and sanitize the recent docs cookie.
		$recent_items_cookie = isset( $_COOKIE['portal_recently_viewed'] ) ? explode( 'portal', sanitize_text_field( wp_unslash( $_COOKIE['portal_recently_viewed'] ) ) ) : [];

		if ( is_single() && is_object( $post ) && isset( $post->post_type ) ) {
			// Remove the current item ID if it's already in the list, add fetch first n-1 recent docs.
			$recent_items_cookie = array_diff( $recent_items_cookie, [ strval( $post->ID ?? 0 ) ] );

			// Insert the current item ID at the beginning of the recent docs list.
			array_unshift( $recent_items_cookie, intval( $post->ID ?? 0 ) );
		}

		// Fetch the first n recent docs.
		return array_slice( $recent_items_cookie, 0, $recent_items_limit );
	}

	/**
	 * Method to get pagination markup for internal categories.
	 *
	 * @param int  $category_id Category ID.
	 * @param int  $base_id Base ID.
	 * @param bool $echo Whether to echo or return.
	 *
	 * @return string Returns pagination markup.
	 *
	 * @since 0.0.1
	 */
	public static function get_archive_pagination_markup( $category_id, $base_id = 0, $echo = true ) {
		$term_object = get_term( $category_id );
		$taxonomy    = ! empty( $term_object->taxonomy ) ? $term_object->taxonomy : '';
		$post_type   = SUREDASHBOARD_FEED_POST_TYPE;
		$markup      = sprintf(
			'</div>
			<div class="portal-pagination-loader">
				<div class="portal-pagination-loader-1"></div>
				<div class="portal-pagination-loader-2"></div>
				<div class="portal-pagination-loader-3"></div>
			</div>
			<div class="portal-infinite-trigger" data-category="%d" data-base_id="%d" data-post_type="%s" data-taxonomy="%s">', // Closed div first for .portal-content-area and opened a new div for .portal-infinite-trigger, to get this after the content area.
			absint( $category_id ),
			absint( $base_id ),
			$post_type,
			$taxonomy
		);

		if ( $echo ) {
			echo wp_kses_post( $markup );
			return '';
		}

		return wp_kses_post( $markup );
	}

	/**
	 * Method to get the post excerpt by post ID.
	 *
	 * @param string $post_link Post link.
	 * @param string $post_title Post title.
	 *
	 * @return array<string, array<string, mixed>> social triggers.
	 * @since 0.0.1
	 */
	public static function suredash_social_triggers( $post_link, $post_title ) {
		return apply_filters(
			'portal_social_triggers',
			[
				'facebook' => [
					'label' => Labels::get_label( 'share_on_facebook' ),
					'icon'  => '<span class="portal-svg-icon portal-icon-sm" aria-hidden="true" aria-label="Facebook"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 512 512" style="fill: currentColor;"><path d="M504 256C504 119 393 8 256 8S8 119 8 256c0 123.8 90.69 226.4 209.3 245V327.7h-63V256h63v-54.64c0-62.15 37-96.48 93.67-96.48 27.14 0 55.52 4.84 55.52 4.84v61h-31.28c-30.8 0-40.41 19.12-40.41 38.73V256h68.78l-11 71.69h-57.78V501C413.3 482.4 504 379.8 504 256z"></path></svg></span>',
					'link'  => 'https://www.facebook.com/sharer/sharer.php?u=' . urlencode( $post_link ),
				],
				'x'        => [
					'label' => Labels::get_label( 'share_on_twitter' ),
					'icon'  => '<span class="portal-svg-icon portal-icon-sm" aria-hidden="true" aria-label="X"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 512 512" style="fill: currentColor;"><path d="M389.2 48h70.6L305.6 224.2 487 464H345L233.7 318.6 106.5 464H35.8L200.7 275.5 26.8 48H172.4L272.9 180.9 389.2 48zM364.4 421.8h39.1L151.1 88h-42L364.4 421.8z"></path></svg></span>',
					'link'  => add_query_arg(
						[
							'url'  => $post_link,
							'text' => rawurlencode( html_entity_decode( wp_strip_all_tags( $post_title ), ENT_COMPAT, 'UTF-8' ) ),
						],
						'https://x.com/intent/tweet'
					),
				],
				'linkedin' => [
					'label' => Labels::get_label( 'share_on_linkedin' ),
					'icon'  => '<span class="portal-svg-icon portal-icon-sm" aria-hidden="true" aria-label="LinkedIn"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 448 512" style="fill: currentColor;"><path d="M416 32H31.9C14.3 32 0 46.5 0 64.3v383.4C0 465.5 14.3 480 31.9 480H416c17.6 0 32-14.5 32-32.3V64.3c0-17.8-14.4-32.3-32-32.3zM135.4 416H69V202.2h66.5V416zm-33.2-243c-21.3 0-38.5-17.3-38.5-38.5S80.9 96 102.2 96c21.2 0 38.5 17.3 38.5 38.5 0 21.3-17.2 38.5-38.5 38.5zm282.1 243h-66.4V312c0-24.8-.5-56.7-34.5-56.7-34.6 0-39.9 27-39.9 54.9V416h-66.4V202.2h63.7v29.2h.9c8.9-16.8 30.6-34.5 62.9-34.5 67.2 0 79.7 44.3 79.7 101.9V416z"></path></svg></span>',
					'link'  => 'https://www.linkedin.com/shareArticle?mini=true&url=' . urlencode( $post_link ) . '&title=' . rawurlencode( $post_title ) . '&source=' . urlencode( get_bloginfo( 'name' ) ),
				],
			]
		);
	}

	/**
	 * Method to get the post excerpt length.
	 *
	 * @param int $length Post excerpt length (default value).
	 * @param int $post_id Optional. Post ID for context-specific filtering.
	 *
	 * @return int Returns the filtered post excerpt length.
	 * @since 0.0.1
	 */
	public static function suredash_excerpt_length( $length = 20, $post_id = 0 ) {
		return apply_filters( 'suredash_excerpt_length', absint( $length ), $post_id );
	}

	/**
	 * Method to get the post content by post ID.
	 *
	 * @param int    $post_id Post ID.
	 * @param string $content_type Content type.
	 *
	 * @return string Returns the post content.
	 * @since 0.0.1
	 */
	public static function get_post_content( $post_id, $content_type ) {
		if ( $content_type === 'excerpt' ) {
			// Get excerpt with HTML preservation if enabled.
			$post_content = suredash_get_excerpt( $post_id );
		} else {
			$post_content = get_post_field( 'post_content', $post_id );
		}

		$processed_content = do_shortcode( is_string( $post_content ) ? $post_content : '' );

		// Apply image preview processing for full content only (not excerpts).
		if ( $content_type !== 'excerpt' ) {
			$height_threshold  = apply_filters( 'suredash_image_preview_height_threshold', 300 );
			$processed_content = self::process_image_previews( $processed_content, $height_threshold );
		}

		/**
		 * Filter the post content before returning.
		 *
		 * This filter allows modification of post content for modal/REST API rendering.
		 * Use this to add custom HTML elements, glossary tooltips, or other content modifications.
		 *
		 * @since 1.5.4
		 * @param string $processed_content The processed content.
		 * @param int    $post_id           The post ID.
		 * @param string $content_type      The content type ('excerpt' or 'full_content').
		 */
		return apply_filters( 'suredash_post_content', $processed_content, $post_id, $content_type );
	}

	/**
	 * Render Post.
	 *
	 * @param array<int, array<string, mixed>> $post Post array.
	 * @param int                              $base_post_id Base Post ID.
	 * @param bool                             $is_pinned Is Pinned.
	 * @param bool                             $is_feeds Is Feeds.
	 * @return mixed HTML content.
	 * @since 0.0.1
	 */
	public static function render_post( $post, $base_post_id = 0, $is_pinned = false, $is_feeds = false ) {
		$args = [
			'post'         => $post,
			'base_post_id' => $base_post_id,
			'is_pinned'    => $is_pinned,
			'comments'     => sd_get_post_field( $post['ID'] ?? 0, 'comment_status' ),
			'is_feeds'     => $is_feeds,
		];

		$post_id = absint( $post['ID'] ?? 0 );
		if ( ! $post_id || ! sd_post_exists( $post_id ) ) {
			return '';
		}
		if ( ! sd_is_post_publish( $post_id ) ) {
			return '';
		}

		ob_start();

		if ( suredash_is_post_protected( $post_id ) ) {
			if ( ! apply_filters( 'suredash_skip_restricted_post', false, $post_id ) ) {
				suredash_get_restricted_template_part(
					$post_id,
					'parts',
					'restricted',
					[
						'icon'        => 'Lock',
						'label'       => 'restricted_content',
						'description' => 'restricted_content_description',
					]
				);
			}
		} else {
			suredash_get_template_part(
				'single',
				'post',
				$args
			);
		}

		$content = (string) preg_replace_callback(
			'/<p>\s*<\/p>/',
			static function() {
				return '';
			},
			(string) ob_get_clean()
		);

		$content = suredash_dynamic_content_support( $content );

		echo do_shortcode( $content );
	}

	/**
	 * Render feeds/discussion controls (view toggle and sort dropdown).
	 *
	 * @param string $current_sort Current sort option (e.g., 'date_desc', 'popular').
	 * @param string $initial_view Initial view type ('grid' or 'list').
	 * @param int    $base_id      Base/Space ID.
	 * @param int    $category_id  Category/Term ID.
	 * @param string $post_type    Post type.
	 * @param string $taxonomy     Taxonomy name.
	 * @param int    $space_id     Space ID.
	 * @param int    $user_id      User ID.
	 * @param string $classes      Additional CSS classes for the controls container.
	 *
	 * @return void
	 * @since 1.6.3
	 */
	public static function render_feeds_controls( $current_sort = 'date_desc', $initial_view = 'grid', $base_id = 0, $category_id = 0, $post_type = '', $taxonomy = '', $space_id = 0, $user_id = 0, $classes = '' ): void {
		// Define sort options with labels and icons.
		$sort_options = [
			'date_desc'    => [
				'label' => __( 'Latest', 'suredash' ),
				'icon'  => 'Clock',
			],
			'date_asc'     => [
				'label' => __( 'Oldest', 'suredash' ),
				'icon'  => 'History',
			],
			'popular'      => [
				'label' => __( 'Popular', 'suredash' ),
				'icon'  => 'MessageCircle',
			],
			'likes'        => [
				'label' => __( 'Likes', 'suredash' ),
				'icon'  => 'Heart',
			],
			'new_activity' => [
				'label' => __( 'New Activity', 'suredash' ),
				'icon'  => 'Activity',
			],
			'alphabetical' => [
				'label' => __( 'Alphabetical', 'suredash' ),
				'icon'  => 'ArrowDownAZ',
			],
		];

		$current_sort_label = $sort_options[ $current_sort ]['label'] ?? __( 'Latest', 'suredash' );
		$current_sort_icon  = $sort_options[ $current_sort ]['icon'] ?? 'Clock';
		?>
		<!-- Feeds Controls -->
		<div class="portal-feeds-controls sd-flex sd-justify-end sd-items-center sd-mb-24 sd-p-8 <?php echo esc_attr( $classes ); ?>">
			<div class="sd-flex-1 sd-border-t sd-mr-8"></div>
			<!-- Sort Dropdown -->
			<div class="sd-flex sd-items-center sd-gap-8">
				<span class="sd-font-14">
					<?php echo esc_html__( 'Sort by:', 'suredash' ); ?>
				</span>
				<div class="portal-feeds-sort-wrapper sd-relative">
					<button
						type="button"
						class="portal-button button-ghost portal-feeds-sort-trigger sd-flex sd-items-center sd-gap-8 sd-pointer"
						aria-haspopup="true"
						aria-expanded="false"
						aria-label="<?php echo esc_attr__( 'Sort options', 'suredash' ); ?>"
					>
						<?php self::get_library_icon( $current_sort_icon, true, 'sm' ); ?>
						<span class="portal-feeds-sort-label"><?php echo esc_html( $current_sort_label ); ?></span>
						<?php self::get_library_icon( 'ChevronDown', true, 'sm', '' ); ?>
					</button>
					<div class="portal-feeds-sort-dropdown sd-absolute sd-display-none sd-bg-content sd-border sd-radius-12 sd-shadow-lg sd-mt-8 sd-z-10 sd-min-w-200"
						data-base_id="<?php echo esc_attr( (string) $base_id ); ?>"
						data-category="<?php echo esc_attr( (string) $category_id ); ?>"
						data-post_type="<?php echo esc_attr( $post_type ); ?>"
						data-taxonomy="<?php echo esc_attr( $taxonomy ); ?>"
						data-space_id="<?php echo esc_attr( (string) $space_id ); ?>"
						data-user_id="<?php echo esc_attr( (string) $user_id ); ?>">
					<?php foreach ( $sort_options as $sort_key => $sort_data ) { ?>
						<?php
						$is_active    = $sort_key === $current_sort;
						$active_class = $is_active ? 'active' : '';
						$check_class  = $is_active ? 'portal-feeds-sort-check' : 'portal-feeds-sort-check sd-hidden';
						?>
						<button type="button" class="portal-button button-ghost sd-hover-bg-secondary portal-feeds-sort-option sd-font-normal <?php echo esc_attr( $active_class ); ?> sd-w-full sd-justify-between sd-pointer" data-sort="<?php echo esc_attr( $sort_key ); ?>">
							<div class="sd-flex sd-items-center sd-gap-8">
								<?php self::get_library_icon( $sort_data['icon'], true, 'sm' ); ?>
								<span class="portal-feeds-sort-option-label"><?php echo esc_html( $sort_data['label'] ); ?></span>
							</div>
							<?php self::get_library_icon( 'Check', true, 'sm', $check_class ); ?>
						</button>
					<?php } ?>
					</div>
				</div>
			</div>

			<!-- View Toggle -->
			<button
				type="button"
				class="portal-button button-ghost portal-feeds-view-toggle-btn sd-flex sd-items-center sd-gap-8 sd-pointer"
				data-view="<?php echo esc_attr( $initial_view ); ?>"
				aria-label="<?php echo esc_attr__( 'Toggle view', 'suredash' ); ?>"
				data-base_id="<?php echo esc_attr( (string) $base_id ); ?>"
		data-category="<?php echo esc_attr( (string) $category_id ); ?>"
		data-post_type="<?php echo esc_attr( $post_type ); ?>"
		data-taxonomy="<?php echo esc_attr( $taxonomy ); ?>"
		data-space_id="<?php echo esc_attr( (string) $space_id ); ?>"
				data-user_id="<?php echo esc_attr( (string) $user_id ); ?>"
			>
				<?php self::get_library_icon( 'Rows2', true, 'sm', 'grid-icon ' . ( $initial_view === 'list' ? '' : 'sd-display-none' ) ); ?>
				<?php self::get_library_icon( 'List', true, 'sm', 'list-icon ' . ( $initial_view === 'grid' ? '' : 'sd-display-none' ) ); ?>
			</button>

		</div>
		<?php
	}

	/**
	 * Process post content to add image preview functionality for large images.
	 *
	 * @param string $content Post content.
	 * @param int    $height_threshold Height threshold in pixels (default: 500).
	 *
	 * @return string Processed content with image previews.
	 * @since 1.1.0
	 */
	public static function process_image_previews( $content, $height_threshold = 500 ) {
		if ( empty( $content ) ) {
			return $content;
		}

		// Pattern to match img tags with src and optional dimensions.
		$pattern = '/<img([^>]*?)src="([^"]*?)"([^>]*?)>/i';

		return (string) preg_replace_callback(
			$pattern,
			static function( $matches ) use ( $height_threshold ) {
				$full_tag   = $matches[0];
				$before_src = $matches[1];
				$src_url    = $matches[2];
				$after_src  = $matches[3];

				// Skip if image already has preview wrapper.
				if ( strpos( $full_tag, 'data-preview-processed' ) !== false ) {
					return $full_tag;
				}

				// Get image dimensions.
				$image_id     = attachment_url_to_postid( $src_url );
				$image_height = 0;

				if ( $image_id ) {
					$image_meta   = wp_get_attachment_metadata( $image_id );
					$image_height = $image_meta['height'] ?? 0;
				} else {
					// Try to get dimensions from image attributes.
					if ( preg_match( '/height=["\'](\d+)["\']/', $full_tag, $height_matches ) ) {
						$image_height = (int) $height_matches[1];
					} else {
						// Fallback: get actual image dimensions (safely check for external images).
						$image_size = false;
						if ( function_exists( 'wp_check_filetype' ) && wp_check_filetype( $src_url )['ext'] ) {
							// Only attempt to get image size if it appears to be a valid image file.
							$image_size = getimagesize( $src_url );
						}
						$image_height = $image_size ? $image_size[1] : 0;
					}
				}

				// If image height exceeds threshold, wrap in preview with lightbox support.
				if ( $image_height > $height_threshold ) {
					$preview_id = 'img-preview-' . wp_rand( 1000, 9999 );

					// Clean up spacing in attributes to ensure valid HTML.
					$before_src = trim( $before_src );
					$after_src  = trim( $after_src );

					// Normalize whitespace in attributes to prevent rendering issues.
					$before_src = preg_replace_callback(
						'/\s+/',
						static function () {
							return ' ';
						},
						$before_src
					);
					$after_src  = preg_replace_callback(
						'/\s+/',
						static function () {
							return ' ';
						},
						$after_src
					);

					$attr_sep = ! empty( $before_src ) ? ' ' : '';

					// Use lightbox for preview functionality with proper attribute handling.
					return '<div class="suredash-image-preview-wrapper" data-preview-id="' . esc_attr( $preview_id ) . '"><div class="suredash-image-preview suredash-lightbox-preview-' . esc_attr( $preview_id ) . '" style="max-height: ' . (int) $height_threshold . 'px; overflow: hidden; position: relative;"><img' . $attr_sep . $before_src . ' src="' . esc_url( $src_url ) . '"' . ( ! empty( $after_src ) ? ' ' . $after_src : '' ) . ' style="width: 100%; height: auto; cursor: zoom-in;"><div class="suredash-expand-overlay" style="position: absolute; bottom: 0; left: 0; right: 0; background: linear-gradient(transparent, rgba(0,0,0,0.7)); color: white; padding: 10px; text-align: center; font-size: 14px; pointer-events: none; transition: opacity 0.3s ease;"><span>🔍 Click for expanded view</span></div></div></div>';
				}

				// Return original image if below threshold.
				return $full_tag;
			},
			$content
		);
	}

	/**
	 * Method to get lightbox filtered selectors.
	 *
	 * @param string $type Selector type.
	 *
	 * @return string Returns lightbox filtered selectors.
	 * @since 0.0.1
	 */
	public static function get_lightbox_selector( $type ) {
		$selectors = '';
		switch ( $type ) {
			case 'single':
				$selectors = apply_filters( 'suredash_lightbox_single_selectors', '.wp-block-media-text, .wp-block-image, .wp-block-uagb-image__figure, .suredash-image-preview-wrapper' );
				break;
			case 'gallery':
				$selectors = apply_filters( 'suredash_lightbox_gallery_selectors', '.wp-block-gallery' );
				break;
			default:
				break;
		}

		// filter selectors.
		$selectors = explode( ',', $selectors );
		$selectors = array_map( 'trim', $selectors );
		return implode( ', ', $selectors );
	}

	/**
	 * Returns array in format required by React Select dropdown
	 * passed array should have id in key and label in value.
	 *
	 * @param array<string, string> $data_array The data array to be converted to React Select format.
	 * @return array<int, array<string, string>>
	 */
	public static function get_react_select_format( $data_array = [] ) {
		$response = [];
		if ( empty( $data_array ) ) {
			return $response;
		}

		foreach ( $data_array as $id => $title ) {
			$response[] = [
				'id'   => $id,
				'name' => $title,
			];
		}

		return $response;
	}

	/**
	 * Get the space ID for a given post.
	 * This handles various post types and integrations to find the correct space.
	 *
	 * @param int $post_id Post ID.
	 * @return int|false Space ID or false if not found.
	 * @since 1.3.0
	 */
	public static function get_space_id_for_post( $post_id ) {

		$space_id  = sd_get_space_id_by_post( $post_id );
		$post_type = sd_get_post_field( $post_id, 'post_type' );

		// For single_post integration, check if we're looking at a remote post.
		if ( ! $space_id && $post_type !== SUREDASHBOARD_POST_TYPE ) {
			global $post;
			if ( $post && is_object( $post ) && property_exists( $post, 'ID' ) && ! empty( $post->post_type ) && $post->post_type === SUREDASHBOARD_POST_TYPE ) {
				$space_id = $post->ID;
			}
		}

		// For single_post integration, if the post_id is a SureDash space post, use it as space_id.
		if ( ! $space_id && $post_type === SUREDASHBOARD_POST_TYPE ) {
			$integration = sd_get_post_meta( $post_id, 'integration', true );
			if ( $integration === 'single_post' ) {
				$space_id = $post_id;
			}
		}

		// For course lessons (community-content posts), get the parent course space.
		if ( ! $space_id && $post_type === SUREDASHBOARD_SUB_CONTENT_POST_TYPE ) {
			$course_id = sd_get_post_meta( $post_id, 'belong_to_course', true );
			if ( $course_id ) {
				$space_id = absint( $course_id );
			}
			$resource_lib_id = sd_get_post_meta( $post_id, 'space_id', true );
			if ( $resource_lib_id ) {
				$space_id = absint( $resource_lib_id );
			}
		}

		return $space_id ? $space_id : false;
	}

	/**
	 * Method to render post reaction markup. It includes like and comment buttons.
	 *
	 * @param int    $post_id Post ID.
	 * @param string $wrapper Wrapper class.
	 * @param bool   $show_comments Show comments.
	 * @param bool   $preview Preview mode.
	 * @param bool   $show_inline_comments Whether to show inline comments below the reaction bar.
	 *
	 * @since 0.0.1
	 * @return void
	 */
	public static function render_post_reaction( $post_id, $wrapper, $show_comments = true, $preview = false, $show_inline_comments = true ): void {
		$space_id          = self::get_space_id_for_post( $post_id );
		$show_like_button  = true;
		$show_share_button = true;
		$show_like_share   = true; // Keep for backward compatibility.

		if ( $space_id ) {
			$show_like_button  = PostMeta::get_post_meta_value( $space_id, 'show_like_button' );
			$show_share_button = PostMeta::get_post_meta_value( $space_id, 'show_share_button' );
			$show_like_share   = PostMeta::get_post_meta_value( $space_id, 'show_like_share' );

			// Backward compatibility: if old option exists but new ones don't, use old value.
			if ( ! metadata_exists( 'post', $space_id, 'show_like_button' ) && metadata_exists( 'post', $space_id, 'show_like_share' ) ) {
				$show_like_button = $show_like_share;
			}
			if ( ! metadata_exists( 'post', $space_id, 'show_share_button' ) && metadata_exists( 'post', $space_id, 'show_like_share' ) ) {
				$show_share_button = $show_like_share;
			}
		}

		$comments_open   = $show_comments && comments_open( $post_id );
		$current_user_id = get_current_user_id();

		if ( ! $show_like_button && ! $show_share_button && ! $comments_open ) {
			return;
		}

		if ( ! $current_user_id && $comments_open ) {
			?>
				<div class="<?php echo esc_attr( $wrapper ); ?> sd-relative">
					<div class="sd-flex sd-gap-6 sd-items-center sd-justify-center sd-font-14">
						<?php Helper::get_login_notice( 'comment' ); ?>
					</div>
				</div>
			<?php
			// Exit early if user is not logged in and comments are open.
			return;
		}

		$user_liked_posts = sd_get_user_meta( $current_user_id, 'portal_user_liked_posts', true );
		$user_liked_posts = is_array( $user_liked_posts ) ? $user_liked_posts : [];
		$is_user_liked    = in_array( $post_id, $user_liked_posts, true );

		$permalink  = (string) get_the_permalink( $post_id );
		$post_title = get_the_title( $post_id );
		$post_id    = (string) $post_id;

		?>
		<div class="<?php echo esc_attr( $wrapper ); ?> sd-relative">
			<div class="portal-comment-header">
				<?php
				if ( $current_user_id ) {
					// Show Like button only if show_like_button is enabled.
					if ( $show_like_button ) {
						$likes_count = sd_get_post_meta( absint( $post_id ), 'portal_post_likes', true );
						$likes_count = is_array( $likes_count ) ? $likes_count : [];
						$likes_count = (string) count( $likes_count );
						?>
						<div class="sd-flex sd-items-center sd-p-4">
							<button class="sd-post-reaction portal-button button-ghost sd-active-shadow-none sd-p-2 sd-radius-9999" data-state="<?php echo esc_attr( $is_user_liked ? 'liked' : 'unliked' ); ?>" data-post_id="<?php echo esc_attr( $post_id ); ?>" title="<?php echo esc_attr__( 'Like', 'suredash' ); ?>" role="button" aria-label="<?php echo esc_attr__( 'Like', 'suredash' ); ?>" data-reaction_type="like"><?php self::get_library_icon( 'Heart', true ); ?></button>

							<?php
							if ( suredash_simply_content() ) {
								$user_list       = suredash_get_thread_liked_users( absint( $post_id ), 'post' );
								$post_likes      = (string) $user_list['thread_likes'];
								$tooltip_content = ! empty( $user_list['tooltip_content'] ) ? $user_list['tooltip_content'] : __( 'No likes yet.', 'suredash' );
								?>
									<span class="tooltip-trigger portal-likes-count sd-pointer" data-tooltip-description="<?php echo esc_attr( (string) $tooltip_content ); ?>" data-count="<?php echo esc_attr( $post_likes ); ?>">
										<span class="counter"><?php echo esc_html( $likes_count ); ?></span>
									</span>
									<?php
							} else {
								?>
									<span class="portal-likes-count" data-count="<?php echo esc_attr( $likes_count ); ?>" data-type="like" data-post_id="<?php echo esc_attr( (string) $post_id ); ?>">
									<?php echo '<span class="counter">' . esc_html( $likes_count ) . '</span>'; ?>
									</span>
									<?php
							}
							?>
						</div>
					<?php } ?>

					<?php
					// Show Comment button if comments are open (regardless of show_like_share setting).
					if ( $comments_open ) {
						?>
						<div class="sd-flex sd-items-center sd-p-4 portal-comment-qv-trigger" data-post_id="<?php echo esc_attr( $post_id ); ?>" data-href="<?php echo esc_url( $permalink ); ?>" data-focus_comment="1" data-comments="1">
							<button class="sd-post-reaction portal-button button-ghost sd-active-shadow-none sd-p-2 sd-radius-9999" title="<?php echo esc_attr__( 'Comment', 'suredash' ); ?>" role="button" aria-label="<?php echo esc_attr__( 'Comment', 'suredash' ); ?>" data-reaction_type="comment" data-type="comment">
								<?php self::get_library_icon( 'MessageCircle', true ); ?>
							</button>
							<?php $comments_count = get_comments_number( absint( $post_id ) ); ?>
							<span class="portal-comments-count" data-count="<?php echo esc_attr( (string) $comments_count ); ?>">
								<span class="counter"><?php echo esc_html( (string) $comments_count ); ?></span>
							</span>
						</div>
					<?php } ?>

					<?php if ( $show_share_button ) { ?>
						<div class="sd-flex sd-items-center sd-p-4 sd-relative">
							<button class="sd-post-reaction portal-post-share-trigger sd-flex sd-pointer portal-button button-ghost sd-active-shadow-none sd-gap-4 sd-p-2 sd-radius-9999 " data-post-id="<?php echo esc_attr( $post_id ); ?>" title="<?php esc_attr_e( 'Share Post', 'suredash' ); ?>" data-reaction_type="share">
								<?php Helper::get_library_icon( 'Share2', true ); ?>
								<span class="sd-reaction-name"><?php esc_html_e( 'Share', 'suredash' ); ?></span>
							</button>

							<div class="portal-post-sharing-wrapper">
								<div class="portal-post-sharing-links sd-box-shadow sd-flex sd-items-center sd-gap-12 sd-border sd-p-8 sd-radius-9999 sd-overflow-visible">
									<?php
									foreach ( Helper::suredash_social_triggers( $permalink, $post_title ) as $trigger ) {
										?>
										<a href="<?php echo esc_url( $trigger['link'] ); ?>"
											class="portal-post-sharing-link sd-flex sd-items-center sd-hover-scale-110 sd-text-color"
											title="<?php echo esc_attr( $trigger['label'] ); ?>"
											target="_blank">
											<?php echo do_shortcode( $trigger['icon'] ); ?>
										</a>
										<?php
									}
									?>
								</div>
							</div>
						</div>
						<?php
					}
				}
				?>
			</div>
		</div>

		<?php
		if ( $comments_open && $current_user_id && $show_inline_comments ) {
			$target_count = 2;

			$params = [
				[
					'post_id'    => absint( $post_id ),
					'number'     => 1,
					'orderby'    => 'comment_date',
					'order'      => 'DESC',
					'meta_query' => [
						[
							'key'     => 'portal_comment_likes',
							'value'   => sprintf( ':%d;', (int) get_post_field( 'post_author', absint( $post_id ) ) ),
							'compare' => 'LIKE',
						],
					],
				],
				[
					'post_id' => (int) $post_id,
					'user_id' => $current_user_id,
					'parent'  => 0,
					'number'  => 1,
					'status'  => 'approve',
					'orderby' => 'comment_date',
					'order'   => 'DESC',
				],
			];

			// Count unique comments from curated queries to determine if backfill is needed.
			$curated_ids = [];
			foreach ( $params as $param ) {
				$comments = get_comments( $param );
				if ( is_array( $comments ) ) {
					foreach ( $comments as $comment ) {
						/**
						 * Comment object.
						 *
						 * @var \WP_Comment $comment
						 */
						$curated_ids[ $comment->comment_ID ] = true;
					}
				}
			}

			// Backfill with latest comments if curated results are less than target.
			if ( count( $curated_ids ) < $target_count ) {
				$params[] = [
					'post_id' => absint( $post_id ),
					'parent'  => 0,
					'number'  => $target_count,
					'status'  => 'approve',
					'orderby' => 'comment_date',
					'order'   => 'DESC',
				];
			}

			suredash_comments_markup( (int) $post_id, true, $params, '', '' );
		}
	}

	/**
	 * Unified layout rendering function.
	 *
	 * @param string                         $layout Layout type: 'list', 'card', 'stacked_list'.
	 * @param array<int,array<string,mixed>> $items  Array of item data.
	 * @param array<string,mixed>            $args   {
	 *     Optional. Array of arguments for the layout.
	 *
	 *     @type string $title       Optional. Layout title/heading.
	 *     @type string $class       Optional. Additional CSS classes.
	 *     @type string $style       Optional. Inline styles (card layout only).
	 *     @type bool   $show_title  Optional. Whether to show title. Default true.
	 *     @type int    $columns     Optional. Number of columns (card layout only).
	 * }
	 * @since 1.0.0
	 * @return void
	 */
	public static function render_layout( $layout = 'list', $items = [], $args = [] ): void {
		if ( empty( $items ) || ! is_array( $items ) ) {
			return;
		}

		// Validate layout type.
		$valid_layouts = [ 'list', 'grid', 'stacked' ];
		if ( ! in_array( $layout, $valid_layouts, true ) ) {
			$layout = 'list';
		}

		// Route to appropriate render function.
		switch ( $layout ) {
			case 'grid':
				suredash_render_card_grid( $items, $args );
				break;

			case 'stacked':
				suredash_render_stacked_list( $items, $args );
				break;

			case 'list':
			default:
				suredash_render_list( $items, $args );
				break;
		}
	}

	/**
	 * Method to render featured excerpt if available.
	 *
	 * @param int $post_id Post ID.
	 *
	 * @return void
	 * @since 0.0.1
	 */
	public static function suredash_featured_cover( $post_id ): void {
		$cover_image = sd_get_post_meta( $post_id, 'custom_post_cover_image', true );
		$cover_embed = sd_get_post_meta( $post_id, 'custom_post_embed_media', true );
		$post_title  = sd_get_post_field( $post_id, 'post_title' );
		$post_type   = sd_get_post_field( $post_id, 'post_type' );

		if ( $post_type !== SUREDASHBOARD_FEED_POST_TYPE ) {
			return;
		}

		if ( $cover_image ) {
			?>
			<div class="portal-post-cover-image sd-overflow-hidden sd-mb-20">
				<img src="<?php echo esc_url( $cover_image ); ?>" alt="<?php echo esc_attr( $post_title ); ?>" class="w-full">
			</div>
			<?php
		} elseif ( $cover_embed ) {
			?>
			<div class="portal-post-cover-embed sd-mb-20">
				<?php
				$embed_html = wp_oembed_get( $cover_embed, [ 'width' => 600 ] );
				$html       = $embed_html ? $embed_html : '';
				wp_maybe_enqueue_oembed_host_js( $html );
				echo do_shortcode( $html );
				?>
			</div>
			<?php
		}
	}

	/**
	 * Method to get all posts with internal CPTs associated with a user.
	 *
	 * @param int $user_id User ID.
	 *
	 * @return void
	 * @since 0.0.1
	 */
	public static function suredash_user_posts( $user_id ): void {
		$post_types = apply_filters(
			'suredashboard_user_posts_within',
			[
				SUREDASHBOARD_FEED_POST_TYPE,
			]
		);

		$user_posts_query = Controller::get_user_query_data(
			'Feeds',
			apply_filters(
				'suredashboard_user_queried_post_args',
				[
					'post_types'     => $post_types,
					'user_id'        => $user_id,
					'posts_per_page' => 5,
					'order'          => 'DESC',
				]
			)
		);

		if ( ! empty( $user_posts_query ) && is_array( $user_posts_query ) ) {
			foreach ( $user_posts_query as $post ) {
				// Ensure the post object is valid.
				if ( empty( $post ) || ! isset( $post['ID'] ) ) {
					continue;
				}

				// If the topic is private, skip rendering.
				if ( suredash_is_post_protected( absint( $post['ID'] ) ) ) {
					continue;
				}

				// Render the post.
				self::render_post( $post );
			}
		} else {
			suredash_get_template_part( 'parts', '404' );
		}

		wp_reset_postdata();
	}

	/**
	 * Method to get all comments associated with a user on internal CPTs.
	 *
	 * @param int                  $user_id User ID.
	 * @param array<string, mixed> $args args.
	 * @return void
	 * @since 0.0.1
	 */
	public static function suredash_user_comments( $user_id, $args = [] ): void {
		$post_types = apply_filters(
			'suredashboard_user_posts_within',
			[
				SUREDASHBOARD_FEED_POST_TYPE,
				SUREDASHBOARD_POST_TYPE,
				SUREDASHBOARD_SUB_CONTENT_POST_TYPE,
			]
		);

		// Use 8 comments to match load_more_comments for consistency.
		$no_of_comments = isset( $args['number'] ) ? absint( $args['number'] ) : 8;
		$offset         = isset( $args['offset'] ) ? absint( $args['offset'] ) : 0;

		// Get user comments with full thread context.
		$comments_with_context = self::get_user_comments_with_threading( $user_id, $no_of_comments, $offset, $post_types );

		if ( ! empty( $comments_with_context ) ) {
			foreach ( $comments_with_context as $comment ) {
				if ( ! $comment instanceof \WP_Comment ) {
					continue;
				}

				$post_id = absint( $comment->comment_post_ID );

				if ( suredash_is_post_protected( $post_id ) ) {
					continue;
				}

				$post_title = get_the_title( $post_id );

				$args = [
					'author_id'       => $comment->user_id, // Use the actual comment author, not the profile user.
					'post_id'         => $post_id,
					'post_title'      => $post_title,
					'comment_content' => $comment->comment_content,
					'comment'         => $comment, // Pass full comment object for threading context.
				];

				ob_start();

				suredash_get_template_part(
					'single',
					'comment',
					$args
				);

				$content = (string) preg_replace_callback(
					'/<p>\s*<\/p>/',
					static function() {
						return '';
					},
					(string) ob_get_clean()
				);

				echo do_shortcode( $content );
			}
		} else {
			$show_empty_message = $args['show_empty_message'] ?? true;
			if ( $show_empty_message ) {
				suredash_get_template_part(
					'parts',
					'404',
					[
						'not_found_text' => Labels::get_label( 'no_comments_found' ),
					]
				);
			}
		}

		wp_reset_postdata();
	}

	/**
	 * Get user comments with full threading context using optimized ORM queries.
	 *
	 * This method gets user comments and includes parent/child comments for proper thread context
	 * using optimized database queries instead of multiple WordPress API calls.
	 *
	 * @param int                $user_id The user ID.
	 * @param int                $limit Number of user comments to get as base.
	 * @param int                $offset Offset for pagination.
	 * @param array<int, string> $post_types Post types to include.
	 * @return array<\WP_Comment> Array of comments with threading context.
	 * @since 1.3.2
	 */
	public static function get_user_comments_with_threading( $user_id, $limit = 8, $offset = 0, $post_types = [] ): array {
		// Step 1: Get user's base comments with ORM.
		$user_base_comments = self::get_user_base_comments( $user_id, $limit, $offset, $post_types );

		if ( empty( $user_base_comments ) ) {
			return [];
		}

		$user_comment_ids = array_column( $user_base_comments, 'comment_ID' );

		// Step 2: Get threading context with optimized ORM queries.
		$threading_context = self::get_threading_context( $user_comment_ids, $post_types );

		// Step 3: Combine and deduplicate.
		$all_comments    = array_merge( $user_base_comments, $threading_context );
		$unique_comments = self::deduplicate_comments( $all_comments );

		// Sort by date (newest first).
		usort(
			$unique_comments,
			static function( $a, $b ) {
				return strtotime( $b->comment_date ) - strtotime( $a->comment_date );
			}
		);

		return $unique_comments;
	}

	/**
	 * Get all community posts.
	 *
	 * @param array<mixed> $args Args.
	 *
	 * @return mixed
	 */
	public static function get_community_posts( $args = [] ) {
		$posts    = [];
		$category = $args['category'] ?? 0;
		if ( $category ) {
			$query = sd_query()->select( 'p.ID, p.post_title, p.post_type, p.post_status, p.post_date, p.post_author, p.comment_count' )
				->from( 'posts AS p' )
				->join( 'term_relationships AS tr', 'p.ID', '=', 'tr.object_id' )
				->join( 'term_taxonomy AS tt', 'tr.term_taxonomy_id', '=', 'tt.term_taxonomy_id' )
				->join( 'terms AS t', 'tt.term_id', '=', 't.term_id' ) // Ensure the join with 'terms' to filter based on terms.
				->where( 'p.post_type', '=', SUREDASHBOARD_FEED_POST_TYPE )
				->where( 'tt.taxonomy', '=', SUREDASHBOARD_FEED_TAXONOMY ) // Match the taxonomy.
				->where( 'tt.term_id', '=', $category ) // Match the category ID.
				->where( 'p.post_status', '!=', 'auto-draft' )
				->order_by( 'p.post_date', 'DESC' )
				->get( ARRAY_A );
		} else {
			$query = sd_query()->select( 'p.ID, p.post_title, p.post_type, p.post_status, p.post_date, p.post_author, p.comment_count' )
				->from( 'posts AS p' )
				->where( 'p.post_type', '=', SUREDASHBOARD_FEED_POST_TYPE )
				->where( 'p.post_status', '!=', 'auto-draft' )
				->order_by( 'p.post_date', 'DESC' )
				->get( ARRAY_A );
		}

		if ( ! empty( $query ) && is_array( $query ) ) {
			foreach ( $query as $post ) {
				if ( empty( $post['ID'] ) ) {
					continue;
				}

				$feed_id = absint( $post['ID'] );
				$group   = get_the_terms( $feed_id, SUREDASHBOARD_FEED_TAXONOMY ) ?? null;

				if ( is_array( $group ) ) {
					$group = $group[0];
				}

				$posts[] = [
					'id'                      => $feed_id,
					'name'                    => html_entity_decode( $post['post_title'] ),
					'author'                  => [
						'id'   => $post['post_author'],
						'name' => get_the_author_meta( 'display_name', $post['post_author'] ),
					],
					'view_url'                => get_permalink( $feed_id ),
					'edit_url'                => get_edit_post_link( $feed_id ),
					'group'                   => [
						'id'   => $group->term_id ?? 0,
						'name' => $group->name ?? __( 'Uncategorized', 'suredash' ),
					],
					'post_date'               => $post['post_date'],
					'status'                  => $post['post_status'],
					'comments'                => [
						'count' => $post['comment_count'],
						'url'   => admin_url( 'edit-comments.php?p=' . $feed_id ),
					],
					'is_restricted'           => suredash_get_post_backend_restriction( $feed_id ),
					'comment_status'          => get_post_field( 'comment_status', $post['ID'] ) ?? 'closed',
					'visibility_scope'        => sd_get_post_meta( $feed_id, 'visibility_scope', true ) ?? '',
					'custom_post_cover_image' => sd_get_post_meta( $feed_id, 'custom_post_cover_image', true ) ?? '',
					'custom_post_embed_media' => sd_get_post_meta( $feed_id, 'custom_post_embed_media', true ) ?? '',
				];
			}
		}

		return $posts;
	}

	/**
	 * Method to get a login notice.
	 * This method is used to display a login notice for users who are not logged in.
	 *
	 * @param string $type Notice type.
	 * @param bool   $sure_member SureMembers status.
	 *
	 * @return void
	 * @since 0.0.2
	 */
	public static function get_login_notice( $type = 'comment', $sure_member = false ): void {
		if ( $sure_member ) {
			?>
			<div class="sd-flex-col sd-items-start sd-justify-center sd-font-14">
				<span class="portal-public-login-link"><?php esc_html_e( 'This discussion is only available for VIP members.', 'suredash' ); ?></span>
				<span class="sd-flex sd-gap-6">
					<span class="sd-text-color"><?php esc_html_e( 'Please upgrade to add response.', 'suredash' ); ?></span>
					<a href="<?php echo esc_url( suredash_get_login_page_url() ); ?>"><?php esc_html_e( 'Upgrade Now', 'suredash' ); ?></a>
				</span>
			</div>
			<?php
			return;
		}

		if ( $type === 'comment' ) {
			?>
			<div class="sd-flex sd-gap-6 sd-font-14 sd-font-medium sd-w-full sd-justify-between sd-mobile-flex-wrap">
				<span class="sd-text-color"><?php Labels::get_label( 'login_or_join', true ); ?></span>
				<a href="<?php echo esc_url( suredash_get_login_page_url() ); ?>" class="sd-font-semibold sd-text-custom sd-hover-text-custom:hover" style="--sd-text-custom: var( --portal-link-color ); --sd-hover-text-custom: var( --portal-link-active-color );"><?php esc_html_e( 'Login', 'suredash' ); ?></a>
			</div>
			<?php
		}
	}

	/**
	 * Method to set/unset wp core interactions.
	 * This method is used to bypass the default wp functions, hooks, and filters. And to use SureDash own REST + ORM functions.
	 *
	 * @return bool
	 */
	public static function bypass_wp_interfere() {
		return boolval( Helper::get_option( 'bypass_wp_interactions' ) );
	}

	/**
	 * Remove other template includes.
	 *
	 * 1. Breakdance.
	 *
	 * @since 0.0.3
	 * @return void
	 */
	public static function remove_other_template_includes(): void {
		if ( function_exists( 'Breakdance\ActionsFilters\template_include' ) ) {
			remove_action( 'template_include', 'Breakdance\ActionsFilters\template_include', 1000000 );
		}
	}

	/**
	 * Method to check if the post is third-party restricted.
	 *
	 * @param int $post_id Post ID.
	 *
	 * @return array<string, mixed> Array with status and content.
	 * @since 1.0.0
	 */
	public static function maybe_third_party_restricted( $post_id ) {
		$ruleset = suredash_restriction_defaults();

		do_action( 'suredash_post_restriction_before_check', $ruleset, $post_id );

		$restriction = apply_filters(
			'suredash_post_restriction_ruleset',
			$ruleset,
			$post_id
		);

		do_action( 'suredash_post_restriction_after_check', $ruleset, $post_id );

		return $restriction;
	}

	/**
	 * Method to get the pinned posts.
	 *
	 * @param int $space_id Space ID.
	 *
	 * @return array<int, int> Pinned posts.
	 * @since 1.0.0
	 */
	public static function get_pinned_posts( $space_id ) {
		$pinned_posts = PostMeta::get_post_meta_value( $space_id, 'pinned_posts' );
		$pinned_posts = ! is_array( $pinned_posts ) ? [] : $pinned_posts;
		$pinned_posts = array_column( $pinned_posts, 'value' );
		$pinned_posts = array_map( 'absint', $pinned_posts );

		if ( empty( $pinned_posts ) ) {
			return [];
		}

		// Verify the posts under pinned posts are publish.
		return array_filter(
			$pinned_posts,
			static function ( $post_id ) {
				return sd_is_post_publish( $post_id );
			}
		);
	}

	/**
	 * Get the first available space ID from the first group.
	 *
	 * @since 1.0.0
	 * @return int Post ID or 0 when not found.
	 */
	public static function get_first_space_id() {
		$portal_settings = Settings::get_suredash_settings();

		if ( is_array( $portal_settings ) && ! empty( $portal_settings['first_space'] ) && absint( $portal_settings['first_space'] ) > 0 ) {
			return absint( $portal_settings['first_space'] );
		}

		$results      = Controller::get_query_data( 'Navigation' );
		$space_groups = array_reduce(
			$results,
			static function ( $carry, $item ) {
				if ( is_array( $item ) && is_array( $carry ) ) {
					$carry[ $item['space_group_position'] ][] = $item;
				}

				return $carry;
			},
			[]
		);

		ksort( $space_groups );

		foreach ( $space_groups as &$group ) {
			$id_sequence = array_unique( explode( ',', strval( $group[0]['space_position'] ) ) );
			usort(
				$group,
				static function ( $a, $b ) use ( $id_sequence ) {
					$a_index = array_search( $a['ID'], $id_sequence );
					$b_index = array_search( $b['ID'], $id_sequence );
					return $a_index - $b_index;
				}
			);
		}

		unset( $group );

		foreach ( $space_groups as $space_group ) {
			foreach ( $space_group as $space_item ) {
				if ( $space_item['post_status'] !== 'publish' ) {
					continue;
				}
				if ( isset( $space_item['integration'] ) && $space_item['integration'] === 'link' ) {
					continue;
				}

				$portal_settings                = Settings::get_suredash_settings();
				$portal_settings['first_space'] = absint( $space_item['ID'] );
				Settings::update_suredash_settings( $portal_settings );
				return absint( $space_item['ID'] );
			}
		}

		return 0;
	}

	/**
	 * Method to get the layout details, like type, content width, and aside spacing.
	 *
	 * @param string $layout Layout.
	 * @param string $style Layout style.
	 *
	 * @return array<string, string> Layout details.
	 * @since 1.0.0
	 */
	public static function get_layout_details( $layout = '', $style = '' ) {
		if ( empty( $layout ) || $layout === 'global' ) {
			$global_layout = Helper::get_option( 'global_layout' );
			$layout        = $global_layout;
		}

		$style = self::get_layout_style( $style );

		switch ( $layout ) {
			default:
			case $layout === 'full_width':
				$layout_details = [
					'layout'        => 'full_width',
					'style'         => $style,
					'content_width' => '100%',
					'aside_spacing' => $style === 'unboxed' ? '0' : '32px',
				];
				break;

			case $layout === 'normal':
				$layout_details = [
					'layout'        => 'normal',
					'style'         => $style,
					'content_width' => 'var(--portal-normal-container-width)',
					'aside_spacing' => '0 auto 32px',
				];
				break;

			case $layout === 'narrow':
				$layout_details = [
					'layout'        => 'narrow',
					'style'         => $style,
					'content_width' => 'var(--portal-narrow-container-width)',
					'aside_spacing' => '0 auto 32px',
				];
				break;
		}

		return $layout_details;
	}

	/**
	 * Method to get synced layout style.
	 *
	 * @param string $style Layout.
	 *
	 * @return string Layout Style.
	 * @since 1.0.0
	 */
	public static function get_layout_style( $style = '' ) {
		if ( empty( $style ) || $style === 'global' ) {
			$global_layout_style = Helper::get_option( 'global_layout_style' );
			$style               = $global_layout_style;
		}

		if ( $style === 'unboxed' ) {
			return 'unboxed';
		}

		return 'boxed';
	}

	/**
	 * Check if admin has created more than 4 spaces.
	 *
	 * @return bool True if admin has more than 4 spaces, false otherwise.
	 * @since 1.4.0
	 */
	public static function has_more_than_four_spaces() {
		$space_count  = wp_count_posts( SUREDASHBOARD_POST_TYPE );
		$total_spaces = ( $space_count->publish ?? 0 ) + ( $space_count->draft ?? 0 );
		return (int) $total_spaces > 4;
	}

	/**
	 * Get SureMembers access groups.
	 *
	 * @since 1.6.0
	 * @return array<int, array<string, string>> Formatted access groups.
	 */
	public static function get_suremembers_access_groups(): array {
		if ( ! suredash_is_suremembers_active() ) {
			return [];
		}

		if ( ! class_exists( '\SureMembers\Inc\Access_Groups' ) || ! is_callable( [ '\SureMembers\Inc\Access_Groups', 'get_active' ] ) ) {
			return [];
		}

		$formatted_groups = [];
		$access_groups    = \SureMembers\Inc\Access_Groups::get_active();

		foreach ( $access_groups as $key => $value ) {
			$formatted_groups[] = [
				'value' => $key,
				'label' => $value,
			];
		}

		return $formatted_groups;
	}

	/**
	 * Check if a user has access to a post based on its visibility scope.
	 *
	 * @param array<string,mixed> $args Configuration array with post_id, user_id, visibility_scope, and detailed_result options.
	 * @return bool|array{access: bool, via_user_id: bool, via_group: bool} True/false if $detailed_result is false, or array with access details if true.
	 * @since 1.6.0
	 */
	public static function is_post_visible_to_user( $args = [] ) {
		$defaults = [
			'post_id'          => 0,
			'user_id'          => null,
			'visibility_scope' => null,
			'detail'           => false,
		];

		$base_return_array = [
			'access'      => false,
			'via_user_id' => false,
			'via_group'   => false,
			'total_count' => 0,
		];

		$args = wp_parse_args( $args, $defaults );

		$post_id                  = absint( $args['post_id'] );
		$user_id                  = $args['user_id'];
		$visibility_scope         = $args['visibility_scope'];
		$detailed_result          = $args['detail'];
		$suremember_plugin_active = suredash_is_suremembers_active();

		if ( ! $post_id ) {
			return $detailed_result ? $base_return_array : false;
		}

		if ( $visibility_scope === null || ! is_array( $visibility_scope ) ) {
			$visibility_scope = get_post_meta( $post_id, 'visibility_scope', true );
		}

		// Separate users and groups from visibility scope.
		$users_in_scope  = [];
		$groups_in_scope = [];

		// If no visibility scope is set, assume public access/open to all.
		if ( empty( $visibility_scope ) || ! is_array( $visibility_scope ) ) {
			return $detailed_result ? array_merge( $base_return_array, [ 'access' => true ] ) : true;
		}
			// Parse visibility scope to separate users and groups.
		foreach ( $visibility_scope as $scope_item ) {
			if ( is_string( $scope_item ) ) {
				if ( str_starts_with( $scope_item, 'user-' ) ) {
					$users_in_scope[] = intval( substr( $scope_item, 5 ) );
				} elseif ( str_starts_with( $scope_item, 'ag-' ) ) {
					$groups_in_scope[] = intval( substr( $scope_item, 3 ) );
				}
			}
		}

		// Admins have full access.
		if ( is_user_logged_in() && current_user_can( 'administrator' ) ) {
			if ( $detailed_result ) {
				if ( $suremember_plugin_active ) {
					$count = count( $visibility_scope );
				} else {
					$count = count( $users_in_scope ); // Count only users if SureMembers is not active.
				}
				return array_merge(
					$base_return_array,
					[
						'access'      => true,
						'total_count' => $count,
					]
				);
			}
			return true;
		}

		// Use current user if no user ID provided.
		if ( ! $user_id ) {
			$user_id = get_current_user_id();
		}

		if ( ! $user_id ) {
			return $detailed_result ? $base_return_array : false;
		}

		$access_via_user_id = false;
		$access_via_group   = false;

		// Check if user has direct access via user ID.
		if ( in_array( $user_id, $users_in_scope, true ) ) {
			$access_via_user_id = true;
		}

		// Check if user has access via any access groups.
		if ( $suremember_plugin_active && ! $access_via_user_id ) {
			// Use SureMembers check_if_user_has_access if available and we have group IDs.
			if ( ! empty( $groups_in_scope ) && is_callable( [ '\SureMembers\Inc\Access_Groups', 'check_if_user_has_access' ] ) ) {
				// @phpstan-ignore-next-line
				$access_via_group = \SureMembers\Inc\Access_Groups::check_if_user_has_access( $groups_in_scope );
			} else {
				// Fallback to existing logic if SureMembers function is not available.
				$access_groups_meta_key = defined( 'SUREMEMBERS_USER_META' ) ? SUREMEMBERS_USER_META : 'suremembers_user_access_group';
				$user_access_groups     = get_user_meta( $user_id, $access_groups_meta_key, true );
				if ( ! empty( $user_access_groups ) && is_array( $user_access_groups ) ) {
					foreach ( $user_access_groups as $group_id ) {
						if ( ! empty( $groups_in_scope ) && in_array( intval( $group_id ), $groups_in_scope, true ) ) {
							$access_via_group = true;
							break;
						}
					}
				}
			}
		}

		// Final access determination.
		$access = $access_via_user_id || $access_via_group;

		if ( $detailed_result ) {
			return array_merge(
				$base_return_array,
				[
					'access'      => $access,
					'via_user_id' => $access_via_user_id,
					'via_group'   => $access_via_group,
					'total_count' => count( $visibility_scope ),
				]
			);
		}

		return $access;
	}

	/**
	 * Get community content CTA.
	 *
	 * @return string Community content CTA.
	 * @since 1.5.0
	 */
	public static function get_community_content_cta() {
		if ( ! function_exists( 'suredash_pro_community_content_cta' ) ) {
			return '';
		}

		return suredash_pro_community_content_cta();
	}

	/**
	 * Get file type icon based on file extension.
	 *
	 * @param string $file_extension File extension.
	 * @return string Lucide icon name.
	 * @since 1.3.2
	 */
	public static function get_file_type_icon( string $file_extension ): string {
		$extension = strtolower( $file_extension );

		// Image files.
		$image_extensions = [ 'jpg', 'jpeg', 'png', 'gif', 'bmp', 'webp', 'svg', 'ico', 'tiff', 'tif' ];
		if ( in_array( $extension, $image_extensions, true ) ) {
			return 'image';
		}

		// Archive/Zip files.
		$archive_extensions = [ 'zip', 'rar', '7z', 'tar', 'gz', 'bz2', 'xz', 'ace', 'arc', 'cab' ];
		if ( in_array( $extension, $archive_extensions, true ) ) {
			return 'archive';
		}

		// Video files.
		$video_extensions = [ 'mp4', 'avi', 'mkv', 'mov', 'wmv', 'flv', 'webm', 'm4v', '3gp', 'ogv' ];
		if ( in_array( $extension, $video_extensions, true ) ) {
			return 'video';
		}

		// Audio files.
		$audio_extensions = [ 'mp3', 'wav', 'ogg', 'flac', 'aac', 'm4a', 'wma', 'opus' ];
		if ( in_array( $extension, $audio_extensions, true ) ) {
			return 'volume2';
		}

		// PDF files.
		if ( $extension === 'pdf' ) {
			return 'fileText';
		}

		// Document files.
		$document_extensions = [ 'doc', 'docx', 'odt', 'rtf', 'txt', 'md' ];
		if ( in_array( $extension, $document_extensions, true ) ) {
			return 'fileText';
		}

		// Spreadsheet files.
		$spreadsheet_extensions = [ 'xls', 'xlsx', 'ods', 'csv' ];
		if ( in_array( $extension, $spreadsheet_extensions, true ) ) {
			return 'table';
		}

		// Presentation files.
		$presentation_extensions = [ 'ppt', 'pptx', 'odp' ];
		if ( in_array( $extension, $presentation_extensions, true ) ) {
			return 'presentation';
		}

		// Code files.
		$code_extensions = [ 'html', 'css', 'js', 'php', 'py', 'java', 'cpp', 'c', 'h', 'json', 'xml', 'sql' ];
		if ( in_array( $extension, $code_extensions, true ) ) {
			return 'code';
		}

		// Default file icon.
		return 'file';
	}

	/**
	 * Parse feeds sort parameter into query parameters.
	 *
	 * @param string $feeds_sort The sort option (date_desc, date_asc, popular, likes, new_activity, alphabetical).
	 * @return array{order_by: string, order: string, meta_key: string} Query parameters.
	 * @since 1.5.0
	 */
	public static function parse_feeds_sort_params( $feeds_sort ): array {
		$order_by = 'post_date';
		$order    = 'DESC';
		$meta_key = '';

		switch ( $feeds_sort ) {
			case 'date_asc':
				$order_by = 'post_date';
				$order    = 'ASC';
				break;

			case 'popular':
				$order_by = 'comment_count';
				$order    = 'DESC';
				break;

			case 'likes':
				$order_by = 'meta_value_num';
				$meta_key = 'portal_post_likes';
				$order    = 'DESC';
				break;

			case 'new_activity':
				$order_by = 'meta_value';
				$meta_key = 'portal_last_comment_date';
				$order    = 'DESC';
				break;

			case 'alphabetical':
				$order_by = 'title';
				$order    = 'ASC';
				break;

			case 'date_desc':
			default:
				$order_by = 'post_date';
				$order    = 'DESC';
				break;
		}

		return [
			'order_by' => $order_by,
			'order'    => $order,
			'meta_key' => $meta_key,
		];
	}

	/**
	 * Get feeds sort preference with proper validation and fallback.
	 * Reads from cookies (for page loads) or AJAX request parameters.
	 *
	 * @param string $default_sort Admin default sort option.
	 * @param int    $space_id     Space ID for per-space preference (0 for global feed).
	 * @return string Validated sort option.
	 * @since 1.5.0
	 */
	public static function get_feeds_sort_preference( string $default_sort = 'date_desc', int $space_id = 0 ): string {
		// Valid sort options.
		$valid_sorts = [ 'date_desc', 'date_asc', 'popular', 'likes', 'new_activity', 'alphabetical' ];

		// Priority: AJAX request parameter > Space-scoped cookie > Legacy global cookie > Admin default > Fallback.
		$user_preference = '';

		// Check AJAX request first (takes precedence for dynamic updates).
		if ( ! empty( $_REQUEST['feeds_sort'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$user_preference = sanitize_text_field( wp_unslash( $_REQUEST['feeds_sort'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		} else {
			// Check space-scoped cookie (works for both specific spaces and feed page with space_id=0).
			$cookie_key = 'suredash_feeds_sort_' . (int) $space_id;
			if ( ! empty( $_COOKIE[ $cookie_key ] ) ) {
				$user_preference = sanitize_text_field( wp_unslash( $_COOKIE[ $cookie_key ] ) );
			} elseif ( ! empty( $_COOKIE['suredash_feeds_sort'] ) ) {
				// Fallback to legacy global cookie for backwards compatibility.
				$user_preference = sanitize_text_field( wp_unslash( $_COOKIE['suredash_feeds_sort'] ) );
			}
		}

		// Validate user preference.
		if ( ! empty( $user_preference ) && in_array( $user_preference, $valid_sorts, true ) ) {
			return $user_preference;
		}

		// Validate admin default.
		if ( in_array( $default_sort, $valid_sorts, true ) ) {
			return $default_sort;
		}

		// Fallback to date_desc.
		return 'date_desc';
	}

	/**
	 * Get feeds view preference with proper validation and fallback.
	 * Reads from cookies (for page loads) or AJAX request parameters.
	 *
	 * @param string $default_view Admin default view option.
	 * @param int    $space_id     Space ID for per-space preference (0 for global feed).
	 * @return string Validated view option ('grid' or 'list').
	 * @since 1.5.0
	 */
	public static function get_feeds_view_preference( string $default_view = 'grid', int $space_id = 0 ): string {
		$valid_views = [ 'grid', 'list' ];

		// Priority: AJAX request parameter > Space-scoped cookie > Legacy global cookie > Admin default > Fallback.
		$user_preference = '';

		// Check AJAX request first (takes precedence for dynamic updates).
		if ( ! empty( $_REQUEST['feeds_view'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$user_preference = sanitize_text_field( wp_unslash( $_REQUEST['feeds_view'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		} else {
			// Check space-scoped cookie (works for both specific spaces and feed page with space_id=0).
			$cookie_key = 'suredash_feeds_view_' . (int) $space_id;
			if ( ! empty( $_COOKIE[ $cookie_key ] ) ) {
				$user_preference = sanitize_text_field( wp_unslash( $_COOKIE[ $cookie_key ] ) );
			} elseif ( ! empty( $_COOKIE['suredash_feeds_view'] ) ) {
				// Fallback to legacy global cookie for backwards compatibility.
				$user_preference = sanitize_text_field( wp_unslash( $_COOKIE['suredash_feeds_view'] ) );
			}
		}

		// Validate user preference.
		if ( ! empty( $user_preference ) && in_array( $user_preference, $valid_views, true ) ) {
			return $user_preference;
		}

		// Validate admin default.
		if ( in_array( $default_view, $valid_views, true ) ) {
			return $default_view;
		}

		// Fallback to grid.
		return 'grid';
	}

	/**
	 * Get user's base comments using ORM.
	 *
	 * @param int                $user_id The user ID.
	 * @param int                $limit Comment limit.
	 * @param int                $offset Comment offset.
	 * @param array<int, string> $post_types Post types to include.
	 * @return array<\WP_Comment> User's base comments.
	 * @since 1.3.2
	 */
	private static function get_user_base_comments( $user_id, $limit, $offset, $post_types ): array {
		$query_result = sd_query()
			->select( 'c.*' )
			->from( 'comments AS c' )
			->leftJoin( 'posts AS p', 'c.comment_post_ID', '=', 'p.ID' )
			->where( 'c.user_id', '=', $user_id )
			->where( 'c.comment_approved', '=', '1' )
			->whereIn( 'p.post_type', $post_types )
			->order_by( 'c.comment_date', 'DESC' )
			->limit( $limit )
			->offset( $offset )
			->get( ARRAY_A );

		return self::convert_to_wp_comments( $query_result );
	}

	/**
	 * Get threading context (parent and child comments) using ORM.
	 *
	 * @param array<int, string> $user_comment_ids User's comment IDs.
	 * @param array<int, string> $post_types Post types to include.
	 * @return array<\WP_Comment> Threading context comments.
	 * @since 1.3.2
	 */
	private static function get_threading_context( $user_comment_ids, $post_types ): array {
		if ( empty( $user_comment_ids ) ) {
			return [];
		}

		global $wpdb;

		// First get parent comment IDs that user replied to.
		$parent_comment_ids = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT DISTINCT comment_parent FROM {$wpdb->comments} WHERE comment_ID IN (" . implode( ',', array_fill( 0, count( $user_comment_ids ), '%d' ) ) . ') AND comment_parent > 0',
				...$user_comment_ids
			)
		);

		$all_context_comments = [];

		// Get parent comments if any exist.
		if ( ! empty( $parent_comment_ids ) ) {
			$parent_comments = sd_query()
				->select( 'c.*' )
				->from( 'comments AS c' )
				->leftJoin( 'posts AS p', 'c.comment_post_ID', '=', 'p.ID' )
				->whereIn( 'c.comment_ID', $parent_comment_ids )
				->where( 'c.comment_approved', '=', '1' )
				->whereIn( 'p.post_type', $post_types )
				->get( ARRAY_A );

			$all_context_comments = array_merge( $all_context_comments, $parent_comments );
		}

		// Get child comments (replies to user's comments).
		$child_comments = sd_query()
			->select( 'c.*' )
			->from( 'comments AS c' )
			->leftJoin( 'posts AS p', 'c.comment_post_ID', '=', 'p.ID' )
			->whereIn( 'c.comment_parent', $user_comment_ids )
			->where( 'c.comment_approved', '=', '1' )
			->whereIn( 'p.post_type', $post_types )
			->limit( 20 ) // Limit child comments to prevent performance issues.
			->get( ARRAY_A );

		$all_context_comments = array_merge( $all_context_comments, $child_comments );

		return self::convert_to_wp_comments( $all_context_comments );
	}

	/**
	 * Convert array results to WP_Comment objects.
	 *
	 * @param array<int, array<string, mixed>> $comment_data_array Array of comment data.
	 * @return array<\WP_Comment> Array of WP_Comment objects.
	 * @since 1.3.2
	 */
	private static function convert_to_wp_comments( $comment_data_array ): array {
		if ( empty( $comment_data_array ) ) {
			return [];
		}

		$comments = [];
		foreach ( $comment_data_array as $comment_data ) {
			$comments[] = new \WP_Comment( (object) $comment_data ); // @phpstan-ignore-line
		}

		return $comments;
	}

	/**
	 * Remove duplicate comments from array.
	 *
	 * @param array<\WP_Comment> $comments Array of comments.
	 * @return array<\WP_Comment> Deduplicated comments.
	 * @since 1.3.2
	 */
	private static function deduplicate_comments( $comments ): array {
		$unique_comments = [];
		$seen_ids        = [];

		foreach ( $comments as $comment ) {
			$comment_id = (int) $comment->comment_ID;
			if ( ! in_array( $comment_id, $seen_ids, true ) ) {
				$unique_comments[] = $comment;
				$seen_ids[]        = $comment_id;
			}
		}

		return $unique_comments;
	}
}

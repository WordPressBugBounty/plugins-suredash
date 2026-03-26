<?php
/**
 * Font Manager
 *
 * @package SureDash
 * @since 1.0.0
 */

namespace SureDashboard\Core;

use SureDashboard\Inc\Traits\Get_Instance;
use SureDashboard\Inc\Utils\Settings;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Font Manager
 */
final class FontManager {
	use Get_Instance;

	/**
	 * Base path for font files
	 *
	 * @var string
	 */
	protected $base_path;

	/**
	 * Base URL for font files
	 *
	 * @var string
	 */
	protected $base_url;

	/**
	 * Remote styles from Google Fonts
	 *
	 * @var string
	 */
	protected $remote_styles;

	/**
	 * Font format
	 *
	 * @var string
	 */
	protected $font_format = 'woff2';

	/**
	 * Google Fonts array
	 *
	 * @var array<int, array<string, array<string, string>>>
	 */
	protected $google_fonts;

	/**
	 * WP Filesystem
	 *
	 * @var object
	 */
	protected $wp_filesystem;

	/**
	 * Constructor
	 */
	public function __construct() {
		require_once ABSPATH . 'wp-admin/includes/file.php';
		WP_Filesystem();
		global $wp_filesystem;
		$this->wp_filesystem = $wp_filesystem;  // wordpress filesystem object.

		$this->base_path = wp_upload_dir()['basedir'] . '/suredashboard/fonts/';
		$this->base_url  = wp_upload_dir()['baseurl'] . '/suredashboard/fonts/';

		// Create the fonts directory if it doesn't exist.
		if ( ! file_exists( $this->base_path ) ) {
			wp_mkdir_p( $this->base_path );
		}

		// Hook into a custom action to process fonts.
		add_action( 'suredash_process_fonts', [ $this, 'process_fonts' ], 10, 1 );

		// Hook into WordPress site URL changes to regenerate fonts.
		add_action( 'update_option_home', [ $this, 'regenerate_fonts_on_url_change' ], 10, 2 );
		add_action( 'update_option_siteurl', [ $this, 'regenerate_fonts_on_url_change' ], 10, 2 );
	}

	/**
	 * Process fonts
	 *
	 * @param string|null $font_family Font family name to process.
	 * @param bool        $force       Force regeneration even if files exist.
	 * @return void
	 */
	public function process_fonts( $font_family = null, $force = false ): void {
		if ( is_null( $font_family ) ) {
			return;
		}
		// Load Google Fonts array.
		$google_fonts = $this->load_google_fonts();

		// Find the specific font in the array.
		$font_data = [];
		foreach ( $google_fonts as $font_entry ) {
			if ( isset( $font_entry[ $font_family ] ) ) {
				$font_data = $font_entry[ $font_family ];
				break;
			}
		}

		// If font not found, return.
		if ( ! is_array( $font_data ) ) {
			return;
		}

		// Get available variants.
		$variants = is_array( $font_data['variants'] ) ? $font_data['variants'] : [];

		// Check if fonts.css exists and contains current font.
		$css_file        = $this->base_path . 'fonts.css';
		$all_files_exist = false;

		if ( ! $force && file_exists( $css_file ) ) {
			$css_content = (string) file_get_contents( $css_file );

			if ( strpos( $css_content, "font-family: '" . $font_family . "'" ) !== false ) {
				// Verify all font files mentioned in CSS actually exist.
				$all_files_exist = $this->verify_font_files( $css_content );
			}
		}

		// If all files exist for current font, skip processing.
		if ( ! $force && $all_files_exist ) {
			return;
		}

		// Clean up old font files since we need to regenerate.
		$this->cleanup_old_fonts();

		if ( is_array( $variants ) ) {
			// Process each variant.
			foreach ( $variants as $variant ) { // @phpstan-ignore-line
				// Determine weight and style.
				$weight = 'regular';
				$style  = 'normal';

				// Handle numeric weights.
				if ( is_numeric( $variant ) ) {
					$weight = $variant;
				}

				// Handle italic variants.
				if ( strpos( $variant, 'italic' ) !== false ) {
					$style = 'italic';
					// Extract weight if present.
					if ( strpos( $variant, 'italic' ) !== 0 ) {
						$weight = str_replace( 'italic', '', $variant );
					}
				}
				// Attempt to download font files.
				if ( is_string( $weight ) && ! $this->get_fonts_file_url( $font_family, $weight, $style ) ) {
					continue;
				}
			}
		}
	}

	/**
	 * Get font files URL
	 *
	 * @param string $font_family  The font family name.
	 * @param string $font_weight  The font weight value.
	 * @param string $font_style   The font style (normal/italic).
	 * @return bool Returns true on success, false on failure.
	 */
	public function get_fonts_file_url( $font_family, $font_weight, $font_style ) {
		$font_family_key = sanitize_key( strtolower( str_replace( ' ', '-', $font_family ) ) ); // Convert to lowercase and replace spaces with hyphens.
		$fonts_attr      = str_replace( ' ', '+', $font_family );                                // Replace spaces with plus signs for Google Fonts API.
		$fonts_file_name = $font_family_key;

		$fonts_attr           .= ':' . $font_weight;  // Add weight to Google Fonts API.
		$fonts_file_name      .= '-' . $font_weight;
		$fonts_file_name_final = '';

		if ( $font_style === 'italic' ) {    // handle italic fonts.
			$fonts_attr      .= 'italic';
			$fonts_file_name .= '-italic';
		}

		// Include extended Latin subset for special characters (š, č, ž, etc.).
		$fonts_link = 'https://fonts.googleapis.com/css?family=' . esc_attr( $fonts_attr ) . '&subset=latin,latin-ext';

		$this->remote_styles = $this->get_remote_url_contents( $fonts_link );     // Fetch remote styles from Google Fonts API.
		if ( empty( $this->remote_styles ) ) {                                    // If no styles found, return false.
			return false;
		}

		$font_files = $this->get_remote_files_from_css();              // Extract font files from remote styles.

		if ( ! is_array( $font_files ) || empty( $font_files ) || empty( $font_family_key ) ) {  // If no font files found,.
			return false;
		}

		$success = true;

		if ( ! method_exists( $this->wp_filesystem, 'move' ) || ! method_exists( $this->wp_filesystem, 'delete' ) ) {
			return false;
		}

		foreach ( $font_files[ $font_family_key ] as $font_file ) { // For each font file, download and save.
			$tmp_path = download_url( $font_file );
			if ( is_wp_error( $tmp_path ) ) {   // If download failed, continue.
				$success = false;
				continue;
			}

			$fonts_file_name_final = $fonts_file_name . '.' . $this->font_format;   // Final font file name.

			if ( ! $this->wp_filesystem->move( $tmp_path, $this->base_path . $fonts_file_name_final ) ) {  // Move the downloaded file to the fonts directory.
				$this->wp_filesystem->delete( $tmp_path );  // Delete the temporary file.
				$success = false;
				continue;
			}
		}

		$local_css = preg_replace_callback(  // Replace remote URLs with local URLs.
			'/https:\/\/fonts.gstatic.com\/s\/[^)]+\)(.*?)format\([\'"].*?[\'"]\)/',
			function( $matches ) use ( $fonts_file_name_final ) {
				return $this->base_url . $fonts_file_name_final . ') format(\'' . $this->font_format . '\')';
			},
			strval( $this->remote_styles )
		);

		$css_file = $this->base_path . 'fonts.css';

		if ( ! method_exists( $this->wp_filesystem, 'get_contents' ) || ! method_exists( $this->wp_filesystem, 'put_contents' ) ) {
			return false;
		}

		if ( method_exists( $this->wp_filesystem, 'exists' ) && $this->wp_filesystem->exists( $css_file ) ) {
			$existing_content = $this->wp_filesystem->get_contents( $css_file );
			$new_content      = $existing_content . $local_css . "\n";
		} else {
			$new_content = $local_css . "\n";
		}

		$this->wp_filesystem->put_contents(
			$css_file,
			$new_content,
			FS_CHMOD_FILE
		);

		return $success;
	}

	/**
	 * Get remote URL contents
	 *
	 * @param string $url URL to fetch content from.
	 * @return string Retrieved content or empty string on failure.
	 */
	public function get_remote_url_contents( $url ) {
		$response = wp_remote_get( $url );
		if ( is_wp_error( $response ) ) {
			return '';
		}
		return wp_remote_retrieve_body( $response );
	}

	/**
	 * Get remote files from CSS
	 *
	 * @return array<string, array<string>>
	 */
	public function get_remote_files_from_css() {
		if ( ! is_string( $this->remote_styles ) || empty( $this->remote_styles ) ) { // If no remote styles found, return empty array.
			return [];
		}

		$font_faces = explode( '@font-face', $this->remote_styles );
		$result     = [];

		foreach ( $font_faces as $font_face ) { // Loop through each font face.
			if ( ! is_string( $font_face ) || empty( $font_face ) ) {
				continue;
			}

			$style_array = explode( '}', $font_face );
			$style       = $style_array[0];

			if ( strpos( $style, 'font-family' ) === false ) {
				continue;
			}

			preg_match_all( '/font-family.*?\;/', $style, $matched_font_families );
			preg_match_all( '/url\(.*?\)/i', $style, $matched_font_files );

			$font_family = 'unknown';
			if ( isset( $matched_font_families[0][0] ) ) {  // Extract font family.
				$font_family = rtrim( ltrim( $matched_font_families[0][0], 'font-family:' ), ';' );
				$font_family = trim( str_replace( [ "'", ';' ], '', $font_family ) );
				$font_family = sanitize_key( strtolower( str_replace( ' ', '-', $font_family ) ) );
			}

			if ( ! isset( $result[ $font_family ] ) ) {    // Initialize font family array.
				$result[ $font_family ] = [];
			}

			foreach ( $matched_font_files as $match ) {   // Extract font files.
				if ( isset( $match[0] ) ) {
					$result[ $font_family ][] = rtrim( ltrim( $match[0], 'url(' ), ')' );
				}
			}

			$result[ $font_family ] = array_flip( array_flip( $result[ $font_family ] ) );  // Remove duplicates.
		}

		return $result;
	}

	/**
	 * Load Google Fonts
	 *
	 * @return array<int, array<string, array<string, string>>>
	 */
	public function load_google_fonts() {
		$fonts_file_path = SUREDASHBOARD_DIR . 'assets/fonts/google-fonts.php';

		if ( ! file_exists( $fonts_file_path ) ) {
			return [];
		}

		// Safely include the file and get the array.
		$google_fonts = require_once $fonts_file_path;

		// If invalid or empty array, return empty array.
		if ( ! is_array( $google_fonts ) || empty( $google_fonts ) ) {
			return [];
		}

		return $google_fonts;
	}

	/**
	 * Regenerate fonts when site URL changes
	 *
	 * @param mixed $old_value The old option value.
	 * @param mixed $new_value The new option value.
	 * @return void
	 */
	public function regenerate_fonts_on_url_change( $old_value, $new_value ): void {
		// Only regenerate if the URL actually changed.
		if ( $old_value === $new_value ) {
			return;
		}

		// Update base_url to reflect the new site URL.
		$this->base_url = wp_upload_dir()['baseurl'] . '/suredashboard/fonts/';

		// Get current font family from settings.
		$settings    = Settings::get_suredash_settings( false );
		$font_family = $settings['font_family'] ?? '';

		// If no font family is set, return.
		if ( empty( $font_family ) ) {
			return;
		}

		// Force regeneration with the new URL.
		$this->process_fonts( $font_family, true );
	}

	/**
	 * Verify font files
	 *
	 * @param string $css_content CSS content to verify.
	 * @return bool Returns true if all font files exist.
	 */
	protected function verify_font_files( $css_content ) {
		// Extract all font URLs from CSS.
		preg_match_all( '/url\([\'"]?([^\'")\s]+)[\'"]?\)/', $css_content, $matches );

		if ( empty( $matches[1] ) ) {
			return false;
		}

		$all_exist = true;
		foreach ( $matches[1] as $font_url ) {
			// Convert URL to file path.
			$file_path = str_replace( $this->base_url, $this->base_path, $font_url );

			if ( ! file_exists( $file_path ) ) {
				$all_exist = false;
				break;
			}
		}

		return $all_exist;
	}

	/**
	 * Cleanup old font files
	 */
	protected function cleanup_old_fonts(): void {
		// Remove old font files.
		$font_files = glob( $this->base_path . '*.{woff2,woff,ttf,otf}', GLOB_BRACE );

		if ( ! is_array( $font_files ) ) {
			return;
		}

		if ( ! method_exists( $this->wp_filesystem, 'exists' ) || ! method_exists( $this->wp_filesystem, 'delete' ) ) {
			return;
		}

		foreach ( $font_files as $file ) {
			if ( $this->wp_filesystem->exists( $file ) ) {
				$this->wp_filesystem->delete( $file );
			}
		}

		// Remove old CSS file if exists.
		$css_file = $this->base_path . 'fonts.css';
		if ( $this->wp_filesystem->exists( $css_file ) ) {
			$this->wp_filesystem->delete( $css_file );
		}
	}
}

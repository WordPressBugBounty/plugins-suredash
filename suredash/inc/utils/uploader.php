<?php
/**
 * Uploader.
 *
 * @package SureDash
 * @since 1.0.0
 */

namespace SureDashboard\Inc\Utils;

use SureDashboard\Inc\Traits\Get_Instance;

defined( 'ABSPATH' ) || exit;

/**
 * Update Compatibility
 *
 * @package SureDash
 */
class Uploader {
	use Get_Instance;

	/**
	 * User ID.
	 *
	 * @var int
	 */
	private static $user_id;

	/**
	 * Subfolder.
	 *
	 * @var string
	 */
	private static $subfolder;

	/**
	 *  Constructor
	 */
	public function __construct() {
	}

	/**
	 * Handle media upload.
	 *
	 * Case: Topic submission cover image.
	 *
	 * @param string       $value value.
	 * @param array<mixed> $file files data.
	 * @param int          $user_id user id.
	 * @param string       $subfolder subfolder.
	 * @return string $uploaded_url uploaded url.
	 * @since 0.0.1
	 */
	public function process_media( string $value, array $file, int $user_id, string $subfolder = '' ): string {
		$uploaded_url = '';

		if ( ! empty( $file ) ) {
			if ( $user_id ) {
				self::$user_id = $user_id;
			}

			self::$subfolder = sanitize_file_name( $subfolder );

			add_filter( 'upload_dir', [ $this, 'change_upload_directory' ] );

			$filename   = $file['name'];
			$temp_path  = $file['tmp_name'];
			$file_size  = $file['size'];
			$file_type  = $file['type'];
			$file_error = $file['error'];

			// Check $file_name aligns with the $value.
			if ( strpos( $value, $filename ) === false ) {
				wp_send_json_error( __( 'File does not match', 'suredash' ) );
			}

			$uploaded_file = [
				'name'     => $filename,
				'type'     => $file_type,
				'tmp_name' => $temp_path,
				'error'    => $file_error,
				'size'     => $file_size,
			];

			$upload_overrides = [
				'test_form' => false,
			];

			require_once wp_normalize_path( ABSPATH . 'wp-admin/includes/file.php' );

			$move_file = wp_handle_upload( $uploaded_file, $upload_overrides );
			remove_filter( 'upload_dir', [ $this, 'change_upload_directory' ] );

			if ( $move_file && ! isset( $move_file['error'] ) ) {
				$uploaded_url = $move_file['url'];

				/**
				 * Fires after a file is successfully uploaded via SureDash.
				 *
				 * This hook allows developers to perform additional processing on uploaded files,
				 * such as registering them as WordPress media attachments for compatibility with
				 * media offload plugins (e.g., Cloudflare R2, S3, etc.).
				 *
				 * @since 1.6.3
				 *
				 * @param string $uploaded_url The URL of the uploaded file.
				 * @param string $file_path    The absolute server path of the uploaded file.
				 * @param string $file_type    The MIME type of the uploaded file.
				 * @param int    $user_id      The ID of the user who uploaded the file.
				 */
				do_action( 'suredash_after_file_upload', $uploaded_url, $move_file['file'], $move_file['type'], self::$user_id );
			} else {
				wp_send_json_error( __( 'File is not uploaded', 'suredash' ) );
			}
		}

		return $uploaded_url;
	}

	/**
	 * Change the upload directory
	 *
	 * @param array<mixed> $dirs upload directory.
	 * @return array<mixed>
	 * @since 0.0.1
	 */
	public function change_upload_directory( array $dirs ): array {
		$dirs['subdir'] = apply_filters( 'suredashboard_uploads_base', '/suredashboard/' . self::$user_id );

		if ( ! empty( self::$subfolder ) ) {
			$dirs['subdir'] .= '/' . self::$subfolder;
		}

		$dirs['path'] = $dirs['basedir'] . $dirs['subdir'];
		$dirs['url']  = $dirs['baseurl'] . $dirs['subdir'];
		return $dirs;
	}
}

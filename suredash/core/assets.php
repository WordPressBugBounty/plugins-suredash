<?php
/**
 * Frontend Assets.
 *
 * @package SureDash
 * @since 1.0.0
 */

namespace SureDashboard\Core;

use SureDashboard\Inc\Traits\Get_Instance;
use SureDashboard\Inc\Utils\Helper;
use SureDashboard\Inc\Utils\Labels;
use SureDashboard\Inc\Utils\PostMeta;

defined( 'ABSPATH' ) || exit;

/**
 * Frontend Assets Compatibility
 *
 * @package SureDash
 */

/**
 * Assets setup
 *
 * @since 1.0.0
 */
class Assets {
	use Get_Instance;

	/**
	 * Global CSS.
	 *
	 * @var string
	 * @since 1.3.0
	 */
	private static $global_css = '';

	/**
	 * Enqueue global assets.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function enqueue_global_assets(): void {

		$localized_data = apply_filters(
			'portal_localized_frontend_data',
			[
				'ajax_url'                      => admin_url( 'admin-ajax.php' ),
				'password_mismatch_message'     => Labels::get_label( 'password_mismatch_message' ),
				'notification_dataset'          => [
					'success' => [
						'icon'    => Helper::get_library_icon( 'CircleCheck', false, 'md' ),
						'message' => Labels::get_label( 'notification_success_message' ),
					],
					'error'   => [
						'icon'    => Helper::get_library_icon( 'CircleX', false, 'md' ),
						'message' => Labels::get_label( 'notification_error_message' ),
					],
					'warning' => [
						'icon'    => Helper::get_library_icon( 'TriangleAlert', false, 'md' ),
						'message' => Labels::get_label( 'notification_warning_message' ),
					],
					'info'    => [
						'icon'    => Helper::get_library_icon( 'Info', false, 'md' ),
						'message' => Labels::get_label( 'notification_info_message' ),
					],
					'neutral' => [
						'icon'    => Helper::get_library_icon( 'Info', false, 'md' ),
						'message' => Labels::get_label( 'notification_neutral_message' ),
					],
				],
				'notification_messages'         => [
					'post_submitted'              => Labels::get_label( 'notify_message_post_submitted' ),
					'please_fill_required_fields' => Labels::get_label( 'notify_message_please_fill_required_fields' ),
					'space_selection'             => Labels::get_label( 'notify_message_space_selection' ),
					'item_bookmarked'             => Labels::get_label( 'notify_message_item_bookmarked' ),
					'item_un_bookmarked'          => Labels::get_label( 'notify_message_item_un_bookmarked' ),
					'comment_liked'               => Labels::get_label( 'notify_message_comment_liked' ),
					'comment_disliked'            => Labels::get_label( 'notify_message_comment_disliked' ),
					'post_liked'                  => Labels::get_label( 'notify_message_post_liked' ),
					'post_disliked'               => Labels::get_label( 'notify_message_post_disliked' ),
					'profile_updated'             => Labels::get_label( 'notify_message_profile_updated' ),
					'comment_posted'              => Labels::get_label( 'notify_message_comment_posted' ),
					'comment_invalid'             => Labels::get_label( 'notify_message_comment_invalid' ),
					'comment_duplicate'           => Labels::get_label( 'notify_message_comment_duplicate' ),
					'error_occurred'              => Labels::get_label( 'notify_message_error_occurred' ),
					'notification_marked_read'    => Labels::get_label( 'notification_marked_read' ),
					'url_copied'                  => Labels::get_label( 'url_copied' ),
					'copy_failed'                 => Labels::get_label( 'copy_failed' ),
					'comment_delete_confirmation' => Labels::get_label( 'comment_delete_confirmation' ),
					'post_delete_confirmation'    => Labels::get_label( 'post_delete_confirmation' ),
				],
				'jodit_messages'                => [
					'bold_tooltip'                       => __( 'Bold', 'suredash' ),
					'italic_tooltip'                     => __( 'Italic', 'suredash' ),
					'underline_tooltip'                  => __( 'Underline', 'suredash' ),
					'image_tooltip'                      => __( 'Attach an Image', 'suredash' ),
					'video_tooltip'                      => __( 'Attach a Video', 'suredash' ),
					'image_url_text'                     => __( 'Image URL', 'suredash' ),
					'image_alt_text'                     => __( 'Image Alt Text', 'suredash' ),
					'attach_image_text'                  => __( 'Attach Image', 'suredash' ),
					'attach_gif_text'                    => __( 'Attach GIF', 'suredash' ),
					'choose_images_text'                 => __( 'Drag and drop or browse files', 'suredash' ),
					'upload_text'                        => __( 'Upload', 'suredash' ),
					'from_url_text'                      => __( 'From URL', 'suredash' ),
					'attach_video_text'                  => __( 'Attach Video', 'suredash' ),
					'video_url_text'                     => __( 'Video URL', 'suredash' ),
					'videos_placeholder'                 => __( 'Supports YouTube, Vimeo, Dailymotion, VideoPress, and more', 'suredash' ),
					'search_user'                        => Labels::get_label( 'jodit_search_user' ),
					'search_gif'                         => Labels::get_label( 'jodit_search_gif' ),
					'mention_tooltip'                    => Labels::get_label( 'jodit_mention_tooltip' ),
					'emoji_tooltip'                      => Labels::get_label( 'jodit_emoji_tooltip' ),
					'gif_tooltip'                        => Labels::get_label( 'jodit_gif_tooltip' ),
					'no_gif_found'                       => Labels::get_label( 'jodit_no_gif_found' ),
					'no_user_found'                      => Labels::get_label( 'jodit_no_user_found' ),
					'minimum_3_characters'               => Labels::get_label( 'jodit_minimum_3_characters' ),
					'api_error'                          => Labels::get_label( 'jodit_api_error' ),
					'url_info_text'                      => __( 'Direct URL to image file (JPG, PNG, GIF, WebP, SVG). Must start with http:// or https:// and end with image extension.', 'suredash' ),
					// Error messages.
					'error_invalid_url'                  => __( 'Please enter a valid URL.', 'suredash' ),
					'error_invalid_protocol'             => __( 'Invalid protocol. Please use HTTP or HTTPS URLs only.', 'suredash' ),
					'error_failed_to_load'               => __( 'Failed to load image. Please check the URL and try again.', 'suredash' ),
					'error_loading_timeout'              => __( 'Image loading timed out. Please try again.', 'suredash' ),
					'error_invalid_dimensions'           => __( 'Invalid image dimensions.', 'suredash' ),
					'error_invalid_video_url'            => __( 'Please enter a valid video URL.', 'suredash' ),
					// File upload error messages.
					/* translators: %1$s: file name, %2$s: allowed file types */
					'error_invalid_file_type'            => __( 'File "%1$s" has an invalid type. Please upload %2$s', 'suredash' ),
					/* translators: %1$s: file name, %2$s: maximum file size in MB */
					'error_file_too_large'               => __( 'File "%1$s" exceeds the maximum size of %2$s MB.', 'suredash' ),
					'error_summary_invalid_types'        => __( 'Invalid file type', 'suredash' ),
					'error_summary_invalid_types_plural' => __( 'Invalid file types', 'suredash' ),
					'error_summary_size_limit'           => __( 'Exceeds size limit', 'suredash' ),
					/* translators: %s: list of allowed file formats */
					'error_summary_allowed_formats'      => __( 'Allowed formats: %s', 'suredash' ),
					/* translators: %s: maximum file size in MB */
					'error_summary_max_size'             => __( 'Maximum file size: %s MB', 'suredash' ),
					'error_details_show'                 => __( 'Show details', 'suredash' ),
					'loading_text'                       => __( 'Loading...', 'suredash' ),
				],
				'jodit_icons'                   => [
					'bold'        => Helper::get_library_icon( 'Bold', false, 'sm' ),
					'italic'      => Helper::get_library_icon( 'Italic', false, 'sm' ),
					'underline'   => Helper::get_library_icon( 'Underline', false, 'sm' ),
					'image'       => Helper::get_library_icon( 'Image', false, 'sm' ),
					'video'       => Helper::get_library_icon( 'Video', false, 'sm' ),
					'emoji'       => Helper::get_library_icon( 'Smile', false, 'sm' ),
					'mention'     => Helper::get_library_icon( 'ImagePlay', false, 'sm' ),
					'upload_file' => Helper::get_library_icon( 'CloudUpload', false, 'md' ),
					'upload'      => Helper::get_library_icon( 'Upload', false, 'sm' ),
					'link'        => Helper::get_library_icon( 'Link2', false, 'sm' ),
					'close'       => Helper::get_library_icon( 'X', false, 'sm' ),
					'check'       => Helper::get_library_icon( 'Check', false, 'sm' ),
				],
				'close_icon'                    => Helper::get_library_icon( 'X', false, 'sm' ),
				'loading_icon'                  => Helper::get_library_icon( 'LoaderCircle', false, 'md', 'loader-classes' ),
				'liked_text'                    => Labels::get_label( 'liked' ),
				'like_text'                     => Labels::get_label( 'like' ),
				'likes_text'                    => Labels::get_label( 'likes' ),
				'wp_rest_nonce'                 => is_user_logged_in() ? wp_create_nonce( 'wp_rest' ) : '',
				'social_login_nonce'            => wp_create_nonce( 'social_login_nonce' ),
				'comment_box_placeholder'       => Labels::get_label( 'comment_box_placeholder' ),
				'comment_reply_box_placeholder' => Labels::get_label( 'comment_reply_box_placeholder' ),
				'no_likes_message'              => __( 'No likes yet, be the first to like!', 'suredash' ),
				'no_comments_message'           => __( 'No comments yet, be the first to comment!', 'suredash' ),
				'create_post_placeholder'       => Labels::get_label( 'create_post_placeholder' ),
				'giphy_api_key'                 => Helper::get_option( 'giphy_api_key' ),
				'user_logged_in'                => is_user_logged_in(),
				'max_file_size'                 => Helper::get_option( 'user_upload_limit' ),
				'color_palette'                 => Helper::get_option( 'color_palette' ),
				'default_palette'               => Helper::get_option( 'default_palette' ),
				'portal_url'                    => esc_url_raw( home_url( suredash_get_community_slug() ) ),
				'suremembers_active'            => suredash_is_suremembers_active(),
				'suremembers_access_groups'     => Helper::get_suremembers_access_groups(),
				'user_access_strings'           => [
					'all_members'            => __( 'All Members', 'suredash' ),
					'searching_users'        => __( 'Searching users...', 'suredash' ),
					'no_members_found'       => __( 'No members found', 'suredash' ),
					'error_searching_users'  => __( 'Error searching users', 'suredash' ),
					'no_access_groups_found' => __( 'No access groups found', 'suredash' ),
				],
			],
		);

		// Get upload directory information.
		$upload_dir     = wp_upload_dir();
		$font_file_url  = $upload_dir['baseurl'] . '/suredashboard/fonts/fonts.css';
		$font_file_path = $upload_dir['basedir'] . '/suredashboard/fonts/fonts.css';

		if ( file_exists( $font_file_path ) ) {
			wp_enqueue_style( 'portal-fonts', esc_url( $font_file_url ), [], SUREDASHBOARD_VER );
		}

		wp_enqueue_script( 'wp-api-fetch' );

		wp_enqueue_script( 'portal-common', SUREDASHBOARD_JS_ASSETS_FOLDER . 'common' . SUREDASHBOARD_JS_SUFFIX, [], SUREDASHBOARD_VER, true );
		wp_enqueue_script( 'jodit-custom', SUREDASHBOARD_JS_ASSETS_FOLDER . 'jodit-custom' . SUREDASHBOARD_JS_SUFFIX, [ 'portal-common' ], SUREDASHBOARD_VER, true );
		wp_enqueue_script( 'portal-global', SUREDASHBOARD_JS_ASSETS_FOLDER . 'global' . SUREDASHBOARD_JS_SUFFIX, [ 'portal-common' ], SUREDASHBOARD_VER, true );
		wp_localize_script( 'portal-global', 'portal_global', $localized_data );
		wp_enqueue_script( 'portal-comments', SUREDASHBOARD_JS_ASSETS_FOLDER . 'comments' . SUREDASHBOARD_JS_SUFFIX, [ 'portal-common', 'jodit-custom', 'portal-global' ], SUREDASHBOARD_VER, true );
		wp_enqueue_script( 'portal-highlight-comments', SUREDASHBOARD_JS_ASSETS_FOLDER . 'highlight-comments' . SUREDASHBOARD_JS_SUFFIX, [ 'portal-common', 'portal-global' ], SUREDASHBOARD_VER, true );
		wp_enqueue_script( 'portal-post-actions', SUREDASHBOARD_JS_ASSETS_FOLDER . 'post-actions' . SUREDASHBOARD_JS_SUFFIX, [ 'portal-common', 'portal-global', 'jodit-custom' ], SUREDASHBOARD_VER, true );

		wp_enqueue_style( 'portal-font', esc_url( SUREDASHBOARD_CSS_ASSETS_FOLDER . ( is_rtl() ? 'font-rtl' : 'font' ) . SUREDASHBOARD_CSS_SUFFIX ), [], SUREDASHBOARD_VER );
		wp_enqueue_style( 'portal-global', esc_url( SUREDASHBOARD_CSS_ASSETS_FOLDER . ( is_rtl() ? 'global-rtl' : 'global' ) . SUREDASHBOARD_CSS_SUFFIX ), [ 'portal-font' ], SUREDASHBOARD_VER );
		wp_add_inline_style( 'portal-global', self::get_global_css() );
		wp_enqueue_style( 'portal-utility', esc_url( SUREDASHBOARD_CSS_ASSETS_FOLDER . ( is_rtl() ? 'utility-rtl' : 'utility' ) . SUREDASHBOARD_CSS_SUFFIX ), [ 'portal-global' ], SUREDASHBOARD_VER );
		wp_enqueue_style( 'portal-badges', esc_url( SUREDASHBOARD_CSS_ASSETS_FOLDER . ( is_rtl() ? 'badges-rtl' : 'badges' ) . SUREDASHBOARD_CSS_SUFFIX ), [ 'portal-utility' ], SUREDASHBOARD_VER );

		wp_enqueue_style( 'portal-blocks', esc_url( SUREDASHBOARD_CSS_ASSETS_FOLDER . ( is_rtl() ? 'blocks-rtl' : 'blocks' ) . SUREDASHBOARD_CSS_SUFFIX ), [], SUREDASHBOARD_VER );

		wp_enqueue_script(
			'portal-jodit',
			esc_url( SUREDASHBOARD_JS_ASSETS_FOLDER . 'jodit' . SUREDASHBOARD_JS_SUFFIX ),
			[],
			SUREDASHBOARD_VER,
			true
		);

		wp_enqueue_style(
			'portal-jodit',
			esc_url( SUREDASHBOARD_CSS_ASSETS_FOLDER . ( is_rtl() ? 'jodit-rtl' : 'jodit' ) . SUREDASHBOARD_CSS_SUFFIX ),
			[],
			SUREDASHBOARD_VER
		);

		wp_enqueue_style(
			'portal-jodit-custom',
			esc_url( SUREDASHBOARD_CSS_ASSETS_FOLDER . ( is_rtl() ? 'jodit-custom-rtl' : 'jodit-custom' ) . SUREDASHBOARD_CSS_SUFFIX ),
			[ 'portal-jodit' ],
			SUREDASHBOARD_VER
		);
	}

	/**
	 * Enqueue : Search specific assets.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function enqueue_search_assets(): void {
		$searchable_post_types = apply_filters(
			'suredash_searchable_post_types',
			[
				SUREDASHBOARD_POST_TYPE             => __( 'Portal Spaces', 'suredash' ),
				SUREDASHBOARD_FEED_POST_TYPE        => __( 'Portal Posts', 'suredash' ),
				SUREDASHBOARD_SUB_CONTENT_POST_TYPE => __( 'Portal Contents', 'suredash' ),
			]
		);
		$post_type             = array_keys( $searchable_post_types );
		$post_type             = implode( ',', $post_type );
		$localize_data         = [
			'portal_search_result'  => wp_create_nonce( 'portal_search_result' ),
			'ajaxurl'               => admin_url( 'admin-ajax.php' ),
			'least_char'            => Labels::get_label( 'least_search_chars_require' ),
			'end_point_error'       => Labels::get_label( 'end_point_error' ),
			'searchable_post_types' => $searchable_post_types,
			'post_type'             => $post_type,
			'recent_items'          => [
				'enabled'         => apply_filters( 'portal_search_recent_items_default_option', true ),
				'cookie_duration' => apply_filters( 'portal_search_recent_items_cookie_duration', 7 * 86400 ), // default to 7 days.
			],
		];

		wp_enqueue_style( 'portal-search', esc_url( SUREDASHBOARD_CSS_ASSETS_FOLDER . ( is_rtl() ? 'search-rtl' : 'search' ) . SUREDASHBOARD_CSS_SUFFIX ), [], SUREDASHBOARD_VER );

		wp_enqueue_script( 'underscore' );
		wp_enqueue_script( 'portal-search', SUREDASHBOARD_JS_ASSETS_FOLDER . 'search' . SUREDASHBOARD_JS_SUFFIX, [ 'jquery' ], SUREDASHBOARD_VER, true );
		wp_localize_script( 'portal-search', 'portal_search', $localize_data );
	}

	/**
	 * Enqueue single item assets.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function enqueue_single_item_assets(): void {
		$localize_data = [
			'ajax_url'             => admin_url( 'admin-ajax.php' ),
			'track_redirect_nonce' => wp_create_nonce( 'portal_update_redirect_count' ),
			'is_portal_home_view'  => suredash_is_home(),
		];

		wp_enqueue_script(
			'portal-single',
			esc_url( SUREDASHBOARD_JS_ASSETS_FOLDER . 'single' . SUREDASHBOARD_JS_SUFFIX ),
			[ 'portal-common', 'portal-comments' ],
			SUREDASHBOARD_VER,
			true
		);

		wp_localize_script( 'portal-single', 'portal_item', $localize_data );

		wp_enqueue_style(
			'portal-single',
			esc_url( SUREDASHBOARD_CSS_ASSETS_FOLDER . ( is_rtl() ? 'single-rtl' : 'single' ) . SUREDASHBOARD_CSS_SUFFIX ),
			[],
			SUREDASHBOARD_VER
		);

		wp_add_inline_style( 'portal-single', self::get_css( 'single' ) );

		wp_enqueue_style(
			'portal-responsive',
			esc_url( SUREDASHBOARD_CSS_ASSETS_FOLDER . ( is_rtl() ? 'responsive-rtl' : 'responsive' ) . SUREDASHBOARD_CSS_SUFFIX ),
			[ 'portal-single' ],
			SUREDASHBOARD_VER
		);

		wp_enqueue_style(
			'portal-archive-group',
			esc_url( SUREDASHBOARD_CSS_ASSETS_FOLDER . ( is_rtl() ? 'archive-rtl' : 'archive' ) . SUREDASHBOARD_CSS_SUFFIX ),
			[],
			SUREDASHBOARD_VER
		);

		wp_add_inline_style( 'portal-archive-group', self::get_css( 'archive' ) );

		// Enqueue lightbox assets.
		if ( Helper::get_option( 'enable_lightbox', true ) ) {

			wp_enqueue_style(
				'portal-lightbox',
				esc_url( SUREDASHBOARD_CSS_ASSETS_FOLDER . ( is_rtl() ? 'lightbox-rtl' : 'lightbox' ) . SUREDASHBOARD_CSS_SUFFIX ),
				[],
				SUREDASHBOARD_VER
			);

			$localize_data = [
				'images_selector'  => [
					'single_selectors'  => Helper::get_lightbox_selector( 'single' ),
					'gallery_selectors' => Helper::get_lightbox_selector( 'gallery' ),
				],
				'lightbox_options' => [],
			];

			wp_enqueue_script( 'portal-lightbox', SUREDASHBOARD_JS_ASSETS_FOLDER . 'lightbox' . SUREDASHBOARD_JS_SUFFIX, [ 'portal-common', 'portal-comments' ], SUREDASHBOARD_VER, true );
			wp_localize_script( 'portal-lightbox', 'portal_lightbox', $localize_data );

			wp_add_inline_style( 'portal-lightbox', $this->get_lightbox_css() );
		}
	}

	/**
	 * Get lightbox specific dynamic assets.
	 *
	 * @since 1.0.0
	 * @return string
	 */
	public function get_lightbox_css() {
		$css             = '';
		$single_selector = Helper::get_lightbox_selector( 'single' );
		if ( is_string( $single_selector ) && $single_selector ) {
			$css .= $single_selector . ' img { cursor: zoom-in;';
		}

		$gallery_selector = Helper::get_lightbox_selector( 'galley' );
		if ( is_string( $gallery_selector ) && $gallery_selector ) {
			$css .= $gallery_selector . ' img { cursor: zoom-in;';
		}

		return apply_filters( 'suredashboard_lightbox_dynamic_css', $css );
	}

	/**
	 * Enqueue archive item assets.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function enqueue_archive_group_assets(): void {
		$localized_data = apply_filters(
			'portal_localized_frontend_data',
			[
				'ajax_url'                => admin_url( 'admin-ajax.php' ),
				'category'                => get_queried_object_id(),
				'page'                    => 1,
				'posts_loaded_message'    => '<div class="portal-no-more-posts">' . Labels::get_label( 'no_more_posts_to_load' ) . '</div>',
				'comments_loaded_message' => '<div class="portal-no-more-posts">' . Labels::get_label( 'no_more_comments_to_load' ) . '</div>',
				'insufficient_data_error' => Labels::get_label( 'insufficient_data_error' ),
				'infinite_scroll_loading' => false,
				'user_logged_in'          => is_user_logged_in(),
				'sub_queried_page'        => suredash_get_sub_queried_page(),
				'feeds_default_view'      => Helper::get_option( 'feeds_default_view', 'grid' ),
			]
		);

		wp_enqueue_script(
			'portal-archive-view',
			esc_url( SUREDASHBOARD_JS_ASSETS_FOLDER . 'archive' . SUREDASHBOARD_JS_SUFFIX ),
			[ 'portal-common', 'portal-comments' ],
			SUREDASHBOARD_VER,
			true
		);

		wp_localize_script( 'portal-archive-view', 'portal_archive', $localized_data );

		wp_enqueue_script( 'wp-a11y' );

		wp_enqueue_script(
			'portal-upload-media',
			esc_url( SUREDASHBOARD_JS_ASSETS_FOLDER . 'upload' . SUREDASHBOARD_JS_SUFFIX ),
			[],
			SUREDASHBOARD_VER,
			true
		);

		wp_localize_script( 'portal-upload-media', 'portal_upload', [] );

		wp_enqueue_style(
			'portal-archive-group',
			esc_url( SUREDASHBOARD_CSS_ASSETS_FOLDER . ( is_rtl() ? 'archive-rtl' : 'archive' ) . SUREDASHBOARD_CSS_SUFFIX ),
			[],
			SUREDASHBOARD_VER
		);
		wp_add_inline_style( 'portal-archive-group', self::get_css( 'archive' ) );
	}

	/**
	 * Get global dynamic Assets.
	 *
	 * @since 1.0.0
	 * @return string
	 */
	public static function get_global_css() {
		if ( ! empty( self::$global_css ) ) {
			return self::$global_css;
		}

		$primary_color = Helper::get_option( 'primary_color' );
		$primary_color = ! empty( $primary_color ) ? $primary_color : '#FFFFFF';

		$secondary_color   = Helper::get_option( 'secondary_color' );
		$content_bg_color  = Helper::get_option( 'content_bg_color' );
		$heading_color     = Helper::get_option( 'heading_color' );
		$text_color        = Helper::get_option( 'text_color' );
		$link_color        = Helper::get_option( 'link_color' );
		$link_active_color = Helper::get_option( 'link_active_color' );
		$selection_color   = Helper::get_option( 'selection_color' );
		$content_width     = Helper::get_option( 'container_width', '100%' );
		$narrow_width      = Helper::get_option( 'narrow_container_width' );
		$normal_width      = Helper::get_option( 'normal_container_width' );
		$course_width      = Helper::get_option( 'course_container_width' );
		$border_color      = Helper::get_option( 'border_color' );

		$primary_button_color              = Helper::get_option( 'primary_button_color' );
		$primary_button_background_color   = Helper::get_option( 'primary_button_background_color' );
		$secondary_button_color            = Helper::get_option( 'secondary_button_color' );
		$secondary_button_background_color = Helper::get_option( 'secondary_button_background_color' );

		$aside_navigation_width = Helper::get_option( 'aside_navigation_width' );
		$container_padding      = Helper::get_option( 'container_padding' );

		$font_family     = Helper::get_option( 'font_family' );
		$secondary_color = is_string( $secondary_color ) ? $secondary_color : '';

		$palette_css    = '';
		$active_palette = Helper::get_option( 'default_palette' );

		// Only for site editor -- Light mode.
		global $pagenow;
		if ( $pagenow === 'site-editor.php' ) {
			$active_palette = 'light';
		}

		if ( $active_palette === 'light' || $active_palette === 'dark' ) {
			$palette_colors = suredash_get_active_palette_colors();
			$palette_css    = ':root {
				--portal-global-color-1: ' . ( $palette_colors[0] ?? '' ) . ';
				--portal-global-color-2: ' . ( $palette_colors[1] ?? '' ) . ';
				--portal-global-color-3: ' . ( $palette_colors[2] ?? '' ) . ';
				--portal-global-color-4: ' . ( $palette_colors[3] ?? '' ) . ';
				--portal-global-color-5: ' . ( $palette_colors[4] ?? '' ) . ';
				--portal-global-color-6: ' . ( $palette_colors[5] ?? '' ) . ';
				--portal-global-color-7: ' . ( $palette_colors[6] ?? '' ) . ';
				--portal-global-color-8: ' . ( $palette_colors[7] ?? '' ) . ';
				--portal-global-color-9: ' . ( $palette_colors[8] ?? '' ) . ';
			}';
		}

		self::$global_css = $palette_css . ':root {
			--portal-accent-color: var( --portal-global-color-1 );
			--portal-primary-color: var( --portal-global-color-5, ' . $primary_color . ' );
			--portal-secondary-color: var( --portal-global-color-6, ' . $secondary_color . ' );
			--portal-content-bg-color: var( --portal-global-color-5, ' . $content_bg_color . ' );
			--portal-secondary-foreground-color: ' . suredash_get_foreground_color( $secondary_color ) . ';
			--portal-text-color: var( --portal-global-color-4, ' . $text_color . ' );
			--portal-heading-color: var( --portal-global-color-3, ' . $heading_color . ' );
			--portal-link-color: var( --portal-global-color-2, ' . $link_color . ' );
			--portal-border-color: var( --portal-global-color-8, ' . $border_color . ' );
			--portal-link-active-color: var( --portal-global-color-2, ' . $link_active_color . ' );
			--portal-placeholder-color: var( --portal-global-color-9, #9CA3AF );

			--portal-primary-button-color: ' . $primary_button_color . ';
			--portal-primary-button-bg-color: ' . $primary_button_background_color . ';
			--portal-primary-button-hover-color: ' . $primary_button_color . ';
			--portal-primary-button-hover-bg-color: ' . $primary_button_background_color . ';

			--portal-secondary-button-color: ' . $secondary_button_color . ';
			--portal-secondary-button-bg-color: ' . $secondary_button_background_color . ';
			--portal-secondary-button-hover-color: ' . $secondary_button_color . ';
			--portal-secondary-button-hover-bg-color: ' . $secondary_button_background_color . ';

			--portal-danger-button-bg-color: #DC2626;

			--portal-navigation-width: ' . $aside_navigation_width . 'px;
			--portal-content-width: ' . $content_width . ';
			--portal-narrow-container-width: ' . $narrow_width . 'px;
			--portal-normal-container-width: ' . $normal_width . 'px;
			--portal-course-container-width: ' . $course_width . 'px;
			--portal-container-spacing: ' . $container_padding . 'px;
			--portal-home-grid-width: 938px;
			--portal-narrow-grid-width: 1030px;
			--portal-wide-grid-width: 1336px;
			--portal-grid-card-width: 296px;

			--portal-success-notification-background: #DCFCE7;
			--portal-error-notification-background: #FEE2E2;
			--portal-warning-notification-background: #FEF9C3;
			--portal-info-notification-background: #E0F2FE;
			--portal-neutral-notification-background: #FFFFFF;

			--portal-placeholder-blue-primary: #E0F2FE;
			--portal-placeholder-yellow-primary: #FEF3C7;
			--portal-placeholder-green-primary: #D1FAE5;

			--portal-placeholder-blue-secondary: #F0F9FF;
			--portal-placeholder-yellow-secondary: #FFFBEB;
			--portal-placeholder-green-secondary: #ECFDF5;

			--portal-placeholder-color-blue: #0EA5E9;
			--portal-placeholder-color-yellow: #F59E0B;
			--portal-placeholder-color-green: #10B981;

			--portal-font-family: ' . $font_family . ';
		}
		';

		// Get other dependent CSS vars.
		self::$global_css .= self::get_dependent_css_vars();

		// Add backward compatibility dynamic styles.
		self::$global_css .= self::get_backward_compatibility_css();

		// Text selection background color style.
		if ( is_string( $selection_color ) && $selection_color !== 'inherit' ) {
			$selection_text_color = 'var( --portal-accent-color, ' . suredash_get_foreground_color( $selection_color ) . ' )';
			self::$global_css    .= '
				.portal-wrapper *::-moz-selection { /* Code for Firefox */
					color: ' . $selection_text_color . ';
					background: ' . $selection_color . ';
				}
				.portal-wrapper *::selection {
					color: ' . $selection_text_color . ';
					background: ' . $selection_color . ';
				}
			';
		}

		// Add global color palette support for dynamic styles.
		self::$global_css .= self::get_palette_supporting_css();

		// Custom CSS from settings.
		$custom_css = Helper::get_option( 'custom_css' );
		if ( ! empty( $custom_css ) ) {
			self::$global_css .= "\n/* Custom CSS */\n" . $custom_css;
		}

		return apply_filters( 'suredashboard_global_dynamic_css', self::$global_css );
	}

	/**
	 * Get dependent CSS variables.
	 *
	 * @since 1.3.0
	 * @return string
	 */
	public static function get_dependent_css_vars() {
		$active_palette = Helper::get_option( 'default_palette' );

		if ( $active_palette === 'light' || $active_palette === 'dark' ) {
			$global_palette = Helper::get_option( 'color_palette' );
			$light_palette  = ! empty( $global_palette['light'] ) ? $global_palette['light'] : [];
			$dark_palette   = ! empty( $global_palette['dark'] ) ? $global_palette['dark'] : [];

			$light_text_color = $light_palette[3] ?? '';
			$dark_text_color  = $dark_palette[3] ?? '';

			$light_text_secondary_color = self::get_color_variation( $light_text_color, 40 );
			$light_text_tertiary_color  = self::get_color_variation( $light_text_color, 80 );

			$dark_text_secondary_color = self::get_color_variation( $dark_text_color, 40 );
			$dark_text_tertiary_color  = self::get_color_variation( $dark_text_color, 80 );

			return ':root {
				--portal-text-light-secondary-color: ' . ( ! empty( $light_text_color ) ? $light_text_secondary_color : '#4b5563' ) . ';
				--portal-text-light-tertiary-color: ' . ( ! empty( $light_text_color ) ? $light_text_tertiary_color : '#6b7280' ) . ';
				--portal-text-dark-secondary-color: ' . ( ! empty( $dark_text_color ) ? $dark_text_secondary_color : '#21262e' ) . ';
				--portal-text-dark-tertiary-color: ' . ( ! empty( $dark_text_color ) ? $dark_text_tertiary_color : '#3B424F' ) . ';
				--portal-text-tertiary-color: var( --portal-text-light-tertiary-color );
				--portal-text-secondary-color: var( --portal-text-light-secondary-color );
			}
			body.palette-dark {
				--portal-text-tertiary-color: var( --portal-text-dark-tertiary-color );
				--portal-text-secondary-color: var( --portal-text-dark-secondary-color );
			}
			.portal-svg-icon {
				color: var(--portal-text-color);
			}
			.wp-block-suredash-navigation .portal-aside-group-link *:not(.sd-unread-badge) {
				color: inherit !important;
			}
			';
		}
		if ( $active_palette === 'custom' ) {
			return '
				.suredash-legacy-colors-setup .has-portal-global-color-5-background-color {
					background-color: var(--wp--preset--color--white) !important;
				}
				.suredash-legacy-colors-setup {
					--wp--preset--color--portal-global-color-8: var( --portal-border-color );
				}
			';
		}

		return '';
	}

	/**
	 * Get color variation.
	 *
	 * @since 1.3.0
	 * @param string $color Hex color code.
	 * @param int    $amount Amount to adjust the color.
	 * @return string Adjusted color.
	 */
	public static function get_color_variation( $color, $amount ) {
		if ( ! preg_match( '/^#([a-f0-9]{3}|[a-f0-9]{6})$/i', $color ) ) {
			return $color; // Return original if not a valid hex color.
		}

		$color = ltrim( $color, '#' );
		if ( strlen( $color ) === 3 ) {
			$color = str_repeat( substr( $color, 0, 1 ), 2 ) . str_repeat( substr( $color, 1, 1 ), 2 ) . str_repeat( substr( $color, 2, 1 ), 2 );
		}

		$rgb = [
			hexdec( substr( $color, 0, 2 ) ),
			hexdec( substr( $color, 2, 2 ) ),
			hexdec( substr( $color, 4, 2 ) ),
		];

		// Calculate perceived brightness using the luminance formula.
		$brightness = 0.299 * $rgb[0] + 0.587 * $rgb[1] + 0.114 * $rgb[2];

		// For bright colors (>128), subtract the amount to make them darker.
		// For dark colors (<=128), add the amount to make them lighter.
		$adjustment = $brightness > 128 ? -$amount : $amount;

		foreach ( $rgb as &$value ) {
			$value = max( 0, min( 255, $value + $adjustment ) );
		}

		return '#' . str_pad( dechex( absint( $rgb[0] ) ), 2, '0', STR_PAD_LEFT )
			. str_pad( dechex( absint( $rgb[1] ) ), 2, '0', STR_PAD_LEFT )
			. str_pad( dechex( absint( $rgb[2] ) ), 2, '0', STR_PAD_LEFT );
	}

	/**
	 * Get backward compatibility dynamic Assets.
	 *
	 * @since 1.3.0
	 * @return string
	 */
	public static function get_backward_compatibility_css() {
		$active_palette = Helper::get_option( 'default_palette' );

		if ( $active_palette !== 'custom' ) {
			return '';
		}

		return ':root {
			--portal-backward-navigation-color: var(--portal-link-color);
		}';
	}

	/**
	 * Get palette supporting CSS.
	 *
	 * @since 1.3.0
	 * @return string
	 */
	public static function get_palette_supporting_css() {
		$active_palette = Helper::get_option( 'default_palette' );

		if ( $active_palette !== 'light' && $active_palette !== 'dark' ) {
			return '';
		}

		$editor_formatted_palette = suredash_editor_formatted_palette_colors();

		$css = '';
		if ( ! empty( $editor_formatted_palette ) && is_array( $editor_formatted_palette ) ) {
			foreach ( $editor_formatted_palette as $color_data ) {
				$color_slug = is_array( $color_data ) && ! empty( $color_data['slug'] ) ? $color_data['slug'] : '';
				$color      = is_array( $color_data ) && ! empty( $color_data['color'] ) ? $color_data['color'] : '';

				// Skip if slug or color is empty.
				if ( empty( $color_slug ) || empty( $color ) ) {
					continue;
				}
				$css .= '.has-' . esc_attr( $color_slug ) . '-color { color: ' . esc_attr( $color ) . ' !important; }';
				$css .= '.has-' . esc_attr( $color_slug ) . '-background-color { background-color: ' . esc_attr( $color ) . ' !important; }';
				$css .= '.has-' . esc_attr( $color_slug ) . '-border-color { border-color: ' . esc_attr( $color ) . ' !important; }';
			}
		}

		return $css;
	}

	/**
	 * Get Assets.
	 *
	 * @param string $type Type of css.
	 *
	 * @since 1.0.0
	 * @return string
	 */
	public static function get_css( $type = 'single' ) {
		$css = '';
		switch ( $type ) {
			case 'single':
				$css .= self::get_single_item_css();
				break;

			case 'archive':
				$css .= self::get_archive_group_css();
				break;

			default:
				break;
		}

		$css = suredash_trim_css( $css );
		return apply_filters( 'suredashboard_single_item_css', $css );
	}

	/**
	 * Get single docs specific dynamic Assets.
	 *
	 * @since 1.0.0
	 * @return string
	 */
	public static function get_single_item_css() {
		$layout        = Helper::get_option( 'global_layout' );
		$content_width = '100%';
		$aside_margin  = '32px';

		if ( suredash_is_home() || ( is_front_page() && Helper::get_option( 'portal_as_homepage' ) ) ) {
			$content_width = '100%';
			$aside_margin  = '0 32px 32px';
		} else {
			if ( suredash_cpt() ) {
				if ( isset( $_SERVER['HTTP_REFERER'] ) ) {
					$post = suredash_get_referer_post();
					if ( ! empty( $post ) && isset( $post['ID'] ) ) {
						$post_id             = $post['ID'];
						$post_type           = sd_get_post_field( $post_id, 'post_type' );
						$func_caller         = $post_type === SUREDASHBOARD_SUB_CONTENT_POST_TYPE ? 'sd_get_content_space_by_post' : 'sd_get_feed_space_by_post';
						$space_id            = sd_get_space_id_by_post( $post_id, $func_caller );
						$layout_post_id      = $space_id ? $space_id : $post_id;
						$referer_post_layout = sd_get_post_meta( $layout_post_id, 'layout', true );

						// Only use global layout style for single_post spaces that support Container Style option.
						$integration  = sd_get_post_meta( $layout_post_id, 'integration', true );
						$layout_style = self::get_space_layout_style( $integration, absint( $layout_post_id ) );

						$layout_details = Helper::get_layout_details( $referer_post_layout, $layout_style );

						$content_width = $layout_details['content_width'];
						$aside_margin  = $layout_details['aside_spacing'];
					}
				} else {
					// Community post or community content - always use 'boxed' style.
					$post_type    = get_post_type();
					$layout_style = in_array( $post_type, [ SUREDASHBOARD_FEED_POST_TYPE, SUREDASHBOARD_SUB_CONTENT_POST_TYPE ], true ) ? 'boxed' : '';

					$layout_details = Helper::get_layout_details( '', $layout_style );

					$content_width = $layout_details['content_width'];
					$aside_margin  = $layout_details['aside_spacing'];
				}
			} elseif ( is_singular( SUREDASHBOARD_POST_TYPE ) ) {
				// Portal space - check integration type for layout style.
				$space_id     = get_the_ID();
				$layout       = $space_id ? sd_get_post_meta( $space_id, 'layout', true ) : '';
				$integration  = $space_id ? sd_get_post_meta( $space_id, 'integration', true ) : '';
				$layout_style = self::get_space_layout_style( $integration, absint( $space_id ) );

				$layout_details = Helper::get_layout_details( $layout, $layout_style );

				$content_width = $layout_details['content_width'];
				$aside_margin  = $layout_details['aside_spacing'];
			} elseif ( is_singular( SUREDASHBOARD_FEED_POST_TYPE ) || is_singular( SUREDASHBOARD_SUB_CONTENT_POST_TYPE ) ) {
				// Community post or community content - always use 'boxed' style.
				$layout_details = Helper::get_layout_details( '', 'boxed' );

				$content_width = $layout_details['content_width'];
				$aside_margin  = $layout_details['aside_spacing'];
			} else {
				$item_id = get_the_ID();
				if ( ! $item_id ) {
					return '';
				}

				$layout         = PostMeta::get_post_meta_value( $item_id, 'layout' );
				$layout_style   = PostMeta::get_post_meta_value( $item_id, 'layout_style' );
				$layout_details = Helper::get_layout_details( $layout, $layout_style );

				$content_width = $layout_details['content_width'];
				$aside_margin  = $layout_details['aside_spacing'];
			}
		}

		// Override for the simply_content Quick view.
		if ( suredash_simply_content() ) {
			$content_width = '100%';
			$aside_margin  = '20px';
		}

		$sub_query = suredash_get_sub_queried_page();

		if ( Helper::get_option( 'enable_feeds' ) && $sub_query === 'feeds' ) {
			// Feeds page should always use 'boxed' style (similar to posts_discussion).
			$layout_details = Helper::get_layout_details( '', 'boxed' );

			$content_width = $layout_details['content_width'];
			$aside_margin  = $layout_details['aside_spacing'];
		}

		$css = '
			:root {
				--portal-content-width: ' . $content_width . ';
				--portal-content-aside-margin: ' . $aside_margin . ';
			}
		';

		return apply_filters( 'suredashboard_single_item_dynamic_css', $css );
	}

	/**
	 * Get archive docs specific dynamic Assets.
	 *
	 * @since 1.0.0
	 * @return string
	 */
	public static function get_archive_group_css() {
		$css = '
			:root {
				--portal-content-aside-margin: 0 auto 32px;
			}
		';
		return apply_filters( 'suredashboard_archive_group_dynamic_css', $css );
	}

	/**
	 * Get layout style for a space based on its integration type.
	 * Only single_post spaces support Container Style option.
	 * Other space types (posts_discussion, events, resource_library, collection, course) should always use 'boxed'.
	 *
	 * @param string $integration Integration type of the space.
	 * @param int    $space_id Space ID.
	 *
	 * @return string Layout style ('boxed' or 'unboxed').
	 * @since 1.6.2
	 */
	public static function get_space_layout_style( $integration, $space_id ) {
		// Space types that do NOT have Container Style option - always use 'boxed'.
		$non_container_style_types = [ 'posts_discussion', 'events', 'resource_library', 'collection', 'course' ];

		if ( in_array( $integration, $non_container_style_types, true ) ) {
			return 'boxed';
		}

		// For single_post and other types, use the space's layout_style setting.
		return $space_id ? sd_get_post_meta( $space_id, 'layout_style', true ) : '';
	}

	/**
	 * Dequeue external assets.
	 * Will maintain the list of external assets that need to be dequeued same can go for scripts as well.
	 *
	 * @since 1.0.1
	 * @return void
	 */
	public function dequeue_external_assets(): void {
		$style_assets = apply_filters(
			'suredash_queue_style_assets',
			[
				'kadence-global',
				'bricks-frontend',
				'kadence-content',
			]
		);

		foreach ( $style_assets as $handle ) {
			wp_dequeue_style( $handle );
		}
	}
}

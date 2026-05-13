<?php
/**
 * Learn module — chapters structure.
 *
 * Defines the ordered list of chapters and steps shown inside the Learn tab.
 * The structure is filterable via `suredash_learn_chapters` so that
 * SureDash Pro (or other extensions) can inject additional content.
 *
 * @package SureDash
 * @since 1.7.2
 */

namespace SureDashboard\Inc\Modules\Learn;

use SureDashboard\Inc\Utils\Helper;

defined( 'ABSPATH' ) || exit;

/**
 * Static chapters registry.
 *
 * @since 1.7.2
 */
class Chapters {
	/**
	 * Build and return the chapters structure.
	 *
	 * Each chapter is an associative array with:
	 *  - id          string Unique chapter identifier (kebab-case).
	 *  - title       string Localized chapter title.
	 *  - description string Localized chapter description.
	 *  - docsUrl     string External documentation URL.
	 *  - steps       array  List of step arrays.
	 *
	 * Each step is an associative array with:
	 *  - id           string Unique step identifier within the chapter.
	 *  - title        string Localized step title.
	 *  - description  string Localized step description.
	 *  - screenshot   array  { url: string, alt: string } — preview image.
	 *  - docsUrl      string External documentation URL for the step.
	 *  - headerAction array  { label: string, url: string } CTA button.
	 *  - isPro        bool   Whether the step requires SureDash Pro.
	 *
	 * @since 1.7.2
	 * @return array<int, array<string, mixed>>
	 */
	public static function get_structure(): array {
		$dashboard_url         = admin_url( 'admin.php?page=portal' );
		$spaces_url            = $dashboard_url . '&tab=spaces';
		$notifications         = $dashboard_url . '&tab=notifications';
		$settings_base         = $dashboard_url . '&tab=settings';
		$general_section       = $settings_base . '&section=general';
		$feeds_content_section = $settings_base . '&section=feeds-content';
		$integrations_section  = $settings_base . '&section=integrations';
		$mcp_section           = $settings_base . '&section=mcp';

		$socials_section = $settings_base . '&section=socials';

		$is_pro_active        = defined( 'SUREDASH_PRO_VER' );
		$is_registration_open = get_option( 'users_can_register' ) || Helper::get_option( 'override_wp_registration' );

		$chapters = [
			[
				'id'          => 'setting-up-portal',
				'title'       => __( 'Set Up Your Portal', 'suredash' ),
				'description' => __( 'Lay the foundation for your community — your branding, spaces, and layout.', 'suredash' ),
				// phpcs:ignore Squiz.PHP.CommentedOutCode.Found -- Docs URL reserved for future use.
				// 'docsUrl'     => 'https://suredash.com/docs/getting-started/',
				'steps'       => [
					[
						'id'           => 'configure-portal',
						'title'        => __( 'Configure Portal Settings', 'suredash' ),
						'description'  => __( 'Set your portal name, font family, community visibility, and homepage to get started.', 'suredash' ),
						'screenshot'   => [
							'url' => 'https://suredash.com/wp-content/uploads/2026/05/suredash-portal-identity.jpg',
							'alt' => __( 'Configure portal settings', 'suredash' ),
						],
						'docsUrl'      => '',
						'headerAction' => [
							'label' => __( 'Open Portal Settings', 'suredash' ),
							'url'   => $general_section . '&source=learn-portal',
						],
						'isPro'        => false,
					],
					...( ! $is_registration_open ? [
						[
							'id'           => 'enable-registration',
							'title'        => __( 'Enable Site Registration', 'suredash' ),
							'description'  => __( 'WordPress registration is currently disabled. Enable it or let SureDash override the setting so new members can sign up.', 'suredash' ),
							'screenshot'   => [
								'url' => 'https://suredash.com/wp-content/uploads/2026/05/suredash-login-registration-settings.jpg',
								'alt' => __( 'Enable site registration', 'suredash' ),
							],
							'docsUrl'      => '',
							'headerAction' => [
								'label' => __( 'Open Login & Registration', 'suredash' ),
								'url'   => $socials_section . '&source=learn-registration',
							],
							'isPro'        => false,
						],
					] : [] ),
					[
						'id'           => 'create-spaces',
						'title'        => __( 'Create Space Groups & Spaces', 'suredash' ),
						'description'  => __( 'Spaces are where members interact — Discussions, Posts, and more. Organize them into groups.', 'suredash' ),
						'screenshot'   => [
							'url' => 'https://suredash.com/wp-content/uploads/2026/05/suredash-create-space-groups-Spaces.jpg',
							'alt' => __( 'Create spaces and groups', 'suredash' ),
						],
						'docsUrl'      => 'https://suredash.com/docs/understanding-spaces-and-space-groups-in-suredash/',
						'headerAction' => [
							'label' => __( 'Go to Spaces', 'suredash' ),
							'url'   => $spaces_url . '&source=learn-spaces',
						],
						'isPro'        => false,
					],
					[
						'id'           => 'customize-layout',
						'title'        => __( 'Customize Portal Layout', 'suredash' ),
						'description'  => __( 'Configure the header, sidebar, container width, and navigation style.', 'suredash' ),
						'screenshot'   => [
							'url' => 'https://suredash.com/wp-content/uploads/2026/05/suredash-customize-portal-layout-scaled.jpg',
							'alt' => __( 'Customize portal layout', 'suredash' ),
						],
						'docsUrl'      => 'https://suredash.com/docs/understanding-customize-portal/',
						'headerAction' => [
							'label' => __( 'Customize Portal', 'suredash' ),
							'url'   => admin_url(
								wp_is_block_theme()
									? 'site-editor.php?postId=suredash%2Fsuredash%2F%2Fportal&postType=wp_template&canvas=edit'
									: 'site-editor.php?postType=wp_template_part&postId=suredash%2Fsuredash%2F%2Fportal&canvas=edit'
							),
						],
						'isPro'        => false,
					],
				],
			],
			[
				'id'          => 'community-settings',
				'title'       => __( 'Configure Community Settings', 'suredash' ),
				'description' => __( 'Reduce signup friction, keep members engaged, and show a unified activity feed.', 'suredash' ),
				// phpcs:ignore Squiz.PHP.CommentedOutCode.Found -- Docs URL reserved for future use.
				// 'docsUrl'     => 'https://suredash.com/docs/community-settings/',
				'steps'       => [
					[
						'id'           => 'enable-social-logins',
						'title'        => __( 'Enable Social Logins', 'suredash' ),
						'description'  => __( 'Let members sign in with Google or Facebook to reduce signup friction.', 'suredash' ),
						'screenshot'   => [
							'url' => 'https://suredash.com/wp-content/uploads/2026/05/suredash-social-logins.jpg',
							'alt' => __( 'Enable social logins', 'suredash' ),
						],
						'docsUrl'      => 'https://suredash.com/docs/how-to-set-up-google-login-in-suredash-a-step-by-step-guide/',
						'headerAction' => [
							'label' => __( 'Configure Logins', 'suredash' ),
							'url'   => $integrations_section . '&source=learn-social-logins',
						],
						'isPro'        => false,
					],
					[
						'id'           => 'email-notifications',
						'title'        => __( 'Set Up Email Notifications', 'suredash' ),
						'description'  => __( 'Configure welcome emails and engagement triggers for new posts, comments, and sign-ups.', 'suredash' ),
						'screenshot'   => [
							'url' => 'https://suredash.com/wp-content/uploads/2026/05/suredash-setup-notifications.jpg',
							'alt' => __( 'Set up email notifications', 'suredash' ),
						],
						'docsUrl'      => 'https://suredash.com/docs/notifications-system-in-suredash/',
						'headerAction' => [
							'label' => __( 'Open Notifications', 'suredash' ),
							'url'   => $notifications . '&source=learn-notifications',
						],
						'isPro'        => false,
					],
					[
						'id'           => 'enable-feeds',
						'title'        => __( 'Enable Community Feeds', 'suredash' ),
						'description'  => __( 'Aggregate posts from all your Discussion spaces into a single feed so members see activity at a glance.', 'suredash' ),
						'screenshot'   => [
							'url' => 'https://suredash.com/wp-content/uploads/2026/05/suredash-community-feeds.jpg',
							'alt' => __( 'Enable community feeds', 'suredash' ),
						],
						'docsUrl'      => 'https://suredash.com/docs/how-to-create-a-feed-space/',
						'headerAction' => [
							'label' => __( 'Open Feeds Settings', 'suredash' ),
							'url'   => $feeds_content_section . '&source=learn-feeds',
						],
						'isPro'        => false,
					],
				],
			],
			[
				'id'          => 'connect-integrations',
				'title'       => __( 'Connect Integrations', 'suredash' ),
				'description' => __( 'Link third-party services and AI tools to extend your portal.', 'suredash' ),
				// phpcs:ignore Squiz.PHP.CommentedOutCode.Found -- Docs URL reserved for future use.
				// 'docsUrl'     => 'https://suredash.com/docs/integrations/',
				'steps'       => [
					[
						'id'           => 'setup-integrations',
						'title'        => __( 'Set Up Integrations', 'suredash' ),
						'description'  => __( 'Connect Google, Facebook, reCAPTCHA, Giphy, and other third-party services to your portal.', 'suredash' ),
						'screenshot'   => [
							'url' => 'https://suredash.com/wp-content/uploads/2026/05/suredash-integrations.jpg',
							'alt' => __( 'Set up integrations', 'suredash' ),
						],
						'docsUrl'      => '',
						'headerAction' => [
							'label' => __( 'Open Integrations', 'suredash' ),
							'url'   => $integrations_section . '&source=learn-integrations',
						],
						'isPro'        => false,
					],
					[
						'id'           => 'configure-mcp',
						'title'        => __( 'Configure MCP Connection', 'suredash' ),
						'description'  => __( 'Set up Model Context Protocol to let AI clients interact with your community data.', 'suredash' ),
						'screenshot'   => [
							'url' => 'https://suredash.com/wp-content/uploads/2026/05/suredash-mcp-connection.jpg',
							'alt' => __( 'Configure MCP connection', 'suredash' ),
						],
						'docsUrl'      => '',
						'headerAction' => [
							'label' => __( 'Open MCP Settings', 'suredash' ),
							'url'   => $mcp_section . '&source=learn-mcp',
						],
						'isPro'        => false,
					],
				],
			],
			[
				'id'          => 'grow-with-pro',
				'title'       => __( 'Grow with Pro', 'suredash' ),
				'description' => __( 'Scale engagement with advanced features available in SureDash Pro.', 'suredash' ),
				// phpcs:ignore Squiz.PHP.CommentedOutCode.Found -- Docs URL reserved for future use.
				// 'docsUrl'     => 'https://suredash.com/pricing/',
				'steps'       => [
					[
						'id'           => 'create-events',
						'title'        => __( 'Create an Events Space', 'suredash' ),
						'description'  => __( 'Host webinars, meetups, and live Q&As with RSVP tracking for your members.', 'suredash' ),
						'screenshot'   => [
							'url' => 'https://suredash.com/wp-content/uploads/2026/05/suredash-event-space.jpg',
							'alt' => __( 'Create an events space', 'suredash' ),
						],
						'docsUrl'      => 'https://suredash.com/docs/setup-event-space-in-suredash/',
						'headerAction' => [
							'label' => $is_pro_active ? __( 'Create Events Space', 'suredash' ) : __( 'Upgrade to Pro', 'suredash' ),
							'url'   => $is_pro_active ? $spaces_url . '&source=learn-events' : 'https://suredash.com/pricing/?source=learn-events',
						],
						'isPro'        => ! $is_pro_active,
					],
					[
						'id'           => 'create-collection',
						'title'        => __( 'Create a Collection Space', 'suredash' ),
						'description'  => __( 'Curate and bundle content from across your portal into focused collections for your members.', 'suredash' ),
						'screenshot'   => [
							'url' => 'https://suredash.com/wp-content/uploads/2026/05/suredash-collection-space.jpg',
							'alt' => __( 'Create a collection space', 'suredash' ),
						],
						'docsUrl'      => 'https://suredash.com/docs/create-collection-space/',
						'headerAction' => [
							'label' => $is_pro_active ? __( 'Create Collection Space', 'suredash' ) : __( 'Upgrade to Pro', 'suredash' ),
							'url'   => $is_pro_active ? $spaces_url . '&source=learn-collection' : 'https://suredash.com/pricing/?source=learn-collection',
						],
						'isPro'        => ! $is_pro_active,
					],
					[
						'id'           => 'setup-leaderboard',
						'title'        => __( 'Set Up the Leaderboard', 'suredash' ),
						'description'  => __( 'Gamify engagement — award points for posts, comments, and reactions.', 'suredash' ),
						'screenshot'   => [
							'url' => 'https://suredash.com/wp-content/uploads/2026/05/suredash-leaderboard.jpg',
							'alt' => __( 'Set up the leaderboard', 'suredash' ),
						],
						'docsUrl'      => 'https://suredash.com/docs/introducing-leaderboard-and-user-levels/',
						'headerAction' => [
							'label' => $is_pro_active ? __( 'Open Leaderboard', 'suredash' ) : __( 'Upgrade to Pro', 'suredash' ),
							'url'   => $is_pro_active ? $settings_base . '&section=leaderboard&source=learn-leaderboard' : 'https://suredash.com/pricing/?source=learn-leaderboard',
						],
						'isPro'        => ! $is_pro_active,
					],
				],
			],
		];

		/**
		 * Filter the Learn tab chapters structure.
		 *
		 * SureDash Pro uses this to replace Pro-gated steps with live CTAs
		 * that point to real admin pages when Pro is active.
		 *
		 * @since 1.7.2
		 * @param array<int, array<string, mixed>> $chapters Default chapters.
		 */
		return apply_filters( 'suredash_learn_chapters', $chapters );
	}
}

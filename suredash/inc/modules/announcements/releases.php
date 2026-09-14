<?php
/**
 * Announcement releases registry.
 *
 * Defines the "What's New" announcements shown in the dashboard after a plugin
 * update. Each release is a code-defined entry (no DB storage of content). Other
 * plugins (e.g. SureDash Pro) register their own entries via the
 * `suredash_announcements` filter, so an announcement can be shipped from any
 * plugin independently of a free SureDash release.
 *
 * @package SureDash
 * @since 1.9.0
 */

namespace SureDashboard\Inc\Modules\Announcements;

defined( 'ABSPATH' ) || exit;

/**
 * Static announcement registry.
 *
 * @since 1.9.0
 */
class Releases {
	/**
	 * Return the full list of announcement entries.
	 *
	 * Free (SureDash) entries are defined inline below and gated to versions
	 * that have actually shipped (`version <= SUREDASHBOARD_VER`). Other plugins
	 * append their own WHOLE entries through the `suredash_announcements`
	 * filter and are responsible for gating their entries against their own
	 * plugin version.
	 *
	 * Entry schema:
	 *
	 *     [
	 *         'id'            => 'suredash-1.9.2',    // Required. Unique, stable identifier. Hardcoded, never built from a version constant.
	 *         'source'        => 'suredash',          // Required. Owning plugin slug.
	 *         'version'       => '1.9.2',             // Required. Hardcoded shipped-gate + reference version.
	 *         'changelog_url' => 'https://…',          // Optional. Full changelog link shown alongside the pages.
	 *         'pages'         => [                    // Required, non-empty. One or more announcement slides.
	 *             [
	 *                 'eyebrow'  => 'New in SureDash', // Optional small label above the title.
	 *                 'title'    => 'Introducing X',   // Required.
	 *                 'body'     => 'What changed.',   // Optional paragraph.
	 *                 'points'   => [                  // Optional compact highlights.
	 *                     [ 'icon' => 'check', 'label' => 'Did a thing' ],
	 *                 ],
	 *                 'media'    => [ 'src' => 'https://…', 'type' => 'image' ], // Optional. type: image|video.
	 *                 'docs_url' => 'https://…',        // Optional.
	 *                 'target'   => [                  // Optional. Points the user at a spot in the dashboard.
	 *                     'route'     => '/settings',
	 *                     'anchor_id' => 'course-quiz',
	 *                     'label'     => 'Course Quizzes',
	 *                     'hint'      => 'Add a quiz from any course space.',
	 *                 ],
	 *             ],
	 *         ],
	 *     ]
	 *
	 * Point `icon` keys: megaphone, sparkles, settings, zap, users, bell,
	 * rocket, gift, star, eye, layout, check, shield, lock, mail, heart, globe.
	 *
	 * @since 1.9.0
	 * @return array<int, array<string, mixed>>
	 */
	public static function get_structure(): array {
		/**
		 * Announcement entries defined for the free SureDash plugin.
		 *
		 * Add one entry per SureDash release that should surface a
		 * "What's New" modal (see the entry schema in the method docblock
		 * above). Older entries can stay here across releases — each is
		 * gated independently by `version` and dismissed independently by `id`.
		 *
		 * @var array<int, array<string, mixed>> $releases
		 */
		$releases = [
			[
				'id'            => 'suredash-1.12.0',
				'source'        => 'suredash',
				'version'       => '1.12.0',
				'changelog_url' => 'https://suredash.com/whats-new/',
				'pages'         => [
					[
						'eyebrow'  => __( 'New in SureDash', 'suredash' ),
						'title'    => __( 'Introducing AI Post Summaries', 'suredash' ),
						'body'     => __( 'Turn long discussion posts into a quick, AI-written recap. Members tap Summarize and read the key points right above the post. The summary is generated once and reused for everyone until the post is edited.', 'suredash' ),
						'points'   => [
							[
								'icon'  => 'sparkles',
								'label' => __( 'Summarize button on long posts', 'suredash' ),
							],
							[
								'icon'  => 'layout',
								'label' => __( 'AI recap sits above the post content', 'suredash' ),
							],
							[
								'icon'  => 'zap',
								'label' => __( 'Generated once, reused until edited', 'suredash' ),
							],
						],
						'media'    => [
							'src'  => SUREDASHBOARD_URL . 'assets/images/ai-summary-banner.webp',
							'type' => 'image',
						],
						'docs_url' => '',
						'target'   => [
							'route'     => '/settings/mcp',
							'anchor_id' => 'post-summary',
							'label'     => __( 'Summarize Posts', 'suredash' ),
							'hint'      => __( 'Switch this on to add a Summarize button to long discussion posts.', 'suredash' ),
						],
					],
				],
			],
		];

		// Gate free entries to versions that have actually shipped, so a
		// prematurely added (future-version) entry is never shown.
		$releases = array_values(
			array_filter(
				$releases,
				static function ( $entry ) {
					$version = (string) ( $entry['version'] ?? '' );
					return $version !== '' && version_compare( $version, SUREDASHBOARD_VER, '<=' );
				}
			)
		);

		/**
		 * Filter the announcement entries shown in the dashboard.
		 *
		 * Other plugins should append their own WHOLE entries (own `id`,
		 * `source`, `version`, `pages`) and gate them against their own
		 * plugin version.
		 *
		 * @since 1.9.0
		 * @param array<int, array<string, mixed>> $releases Announcement entries.
		 */
		$releases = apply_filters( Announcements::FILTER, $releases );

		return is_array( $releases ) ? $releases : [];
	}
}

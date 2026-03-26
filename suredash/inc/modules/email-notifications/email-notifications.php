<?php
/**
 * Email notification functions.
 *
 * @package SureDash
 * @since 1.5.0
 */

namespace SureDashboard\Inc\Modules\EmailNotifications;

use SureDashboard\Inc\Traits\Get_Instance;
use SureDashboard\Inc\Utils\Helper;

defined( 'ABSPATH' ) || exit;

/**
 * Email Notifications Manager
 *
 * Handles email notification functionality and configuration.
 *
 * @since 1.5.0
 */
class Email_Notifications {
	use Get_Instance;

	/**
	 * Constructor
	 *
	 * @since 1.5.0
	 */
	public function __construct() {
		// Initialize any needed hooks or setup.
	}

	/**
	 * Get all available email triggers.
	 *
	 * @since 1.5.0
	 * @return array<string, array<string, mixed>>
	 */
	public function get_available_email_triggers(): array {
		$basic_triggers = [
			'user_onboarding'      => [
				'title'    => __( 'Welcome New Users', 'suredash' ),
				'isPro'    => false,
				'template' => [
					'subject' => __( 'Welcome to {{portal_name}}, {{user_name}}! 🎉', 'suredash' ),
					'body'    => __( '<p>Hi {{user_first_name}},</p><p>Welcome to <strong>{{portal_name}}</strong>! 🎉 We\'re thrilled to have you join our community.</p><p>Your account has been successfully created and you\'re all set to explore everything we have to offer. Whether you\'re here to learn, connect, or collaborate, you\'ve come to the right place.</p><p>👉 <strong>Access Your Dashboard:</strong> <a href="{{portal_url}}">{{portal_url}}</a></p><p><strong>What\'s next?</strong></p><ul><li>Complete your profile to help others get to know you</li><li>Explore the different spaces and join conversations</li><li>Connect with other community members</li><li>Check out our resources and latest updates</li></ul><p>If you have any questions or need assistance getting started, don\'t hesitate to reach out. We\'re here to help!</p><p>Welcome aboard!<br>The {{portal_name}} Team</p>', 'suredash' ),
				],
			],
			'admin_space_created'  => [
				'title'    => __( 'New Space Announcement', 'suredash' ),
				'template' => [
					'subject' => __( '🚀 New space available: {{space_name}}', 'suredash' ),
					'body'    => __( '<p>Hi {{user_first_name}},</p><p>Exciting news! We\'ve just launched a new space on <strong>{{portal_name}}</strong>.</p><p><strong>{{space_name}}</strong> is now open and ready for exploration. This space has been carefully designed to provide you with valuable content, discussions, and resources.</p><p>👉 <strong>Explore {{space_name}}:</strong> <a href="{{space_url}}">{{space_url}}</a></p><p><strong>What you\'ll find:</strong></p><ul><li>Fresh content and discussions</li><li>Valuable resources and materials</li><li>Opportunities to connect with like-minded members</li><li>Expert insights and community knowledge</li></ul><p>Don\'t miss out on being among the first to engage with this new space. Your participation helps build a vibrant community for everyone.</p><p>See you inside!<br>The {{portal_name}} Team</p>', 'suredash' ),
				],
			],
			'admin_post_published' => [
				'title'    => __( 'Admin Post Notification', 'suredash' ),
				'template' => [
					'subject' => __( '📰 New Post: {{post_title}}', 'suredash' ),
					'body'    => __( '<p>Hi {{user_first_name}},</p><p>We\'ve just published something new that we think you\'ll find valuable on <strong>{{portal_name}}</strong>.</p><p><strong>{{post_title}}</strong></p><p>{{post_excerpt}}</p><p>This content is fresh off the press and contains insights that could be exactly what you need right now.</p><p>👉 <strong>Read the full post:</strong> <a href="{{post_url}}">{{post_url}}</a></p><p><strong>Why this matters:</strong></p><ul><li>Stay updated with the latest developments</li><li>Gain valuable insights from our community</li><li>Join the conversation with your thoughts and questions</li><li>Connect with others who share your interests</li></ul><p>We love hearing your thoughts and feedback, so don\'t hesitate to engage with the post and share your perspective.</p><p>Stay connected,<br>The {{portal_name}} Team</p>', 'suredash' ),
				],
			],

			/*
			On hold triggers
				// 'new_lesson_introduced'   => [
				// 'title'    => __( 'New Lesson Added', 'suredash' ),
				// 'isPro'    => true,
				// 'template' => [
				// 'subject' => __( '📘 New lessons available in {{course_title}}', 'suredash' ),
				// 'body'    => __( "Hi {{user_first_name}},\n\nGreat news! We've just added new lessons to *{{course_title}}* on *{{portal_name}}*.\n\nThese fresh lessons are packed with valuable insights and practical knowledge that will help you advance your learning journey. Whether you're just starting or looking to deepen your expertise, these new additions are designed with you in mind.\n\n👉 Continue your learning: {{course_url}}\n\n*What's new:*\n• Updated content with the latest insights\n• Interactive elements to enhance your learning\n• Practical examples you can apply immediately\n• Expert tips and best practices\n\nDon't let this opportunity pass by. The sooner you dive in, the sooner you'll benefit from these valuable new resources.\n\nHappy learning!\nThe {{portal_name}} Team", 'suredash' ),
				// ],
				// ],
				// 'new_resource_introduced' => [
				// 'title'    => __( 'New Resources Available', 'suredash' ),
				// 'isPro'    => true,
				// 'template' => [
				// 'subject' => __( '📂 Fresh resources added to {{space_name}}', 'suredash' ),
				// 'body'    => __( "Hi {{user_first_name}},\n\nWe've just enriched *{{space_name}}* with valuable new resources on *{{portal_name}}*.\n\nThese carefully curated materials are designed to provide you with the tools, insights, and knowledge you need to succeed. Whether you're looking for reference materials, templates, guides, or interactive content, you'll find something valuable in this latest addition.\n\n👉 Access the new resources: {{space_url}}\n\n*What you'll discover:*\n• Expertly crafted materials and templates\n• Step-by-step guides and tutorials\n• Reference documents and checklists\n• Interactive tools and resources\n• Community-contributed content\n\nThese resources complement your learning journey and provide practical support for your goals. We encourage you to explore, download, and share your feedback with the community.\n\nDive in and discover what's new!\n\nBest regards,\nThe {{portal_name}} Team", 'suredash' ),
				// ],
				// ],
			*/
			'course_completed'     => [
				'title'    => __( 'Course Completion Celebration', 'suredash' ),
				'isPro'    => true,
				'template' => [
					'subject' => __( '🎓 Congratulations! You completed {{course_title}}', 'suredash' ),
					'body'    => __( '<p>Hi {{user_first_name}},</p><p>👏 <strong>Congratulations!</strong> You\'ve successfully completed <strong>{{course_title}}</strong> on <strong>{{portal_name}}</strong>.</p><p>This is a significant achievement that represents your dedication, persistence, and commitment to learning. You should be proud of reaching this milestone!</p><p><strong>Your achievement:</strong></p><ul><li>Course: {{course_title}}</li><li>Completion date: {{user_registered}}</li><li>Learning platform: {{portal_name}}</li></ul><p><strong>What\'s next?</strong></p><ul><li>Apply what you\'ve learned in real-world scenarios</li><li>Share your knowledge with other community members</li><li>Explore related courses to expand your expertise</li><li>Consider mentoring others who are just starting their journey</li></ul><p>Your success inspires others in our community. Thank you for being an active learner and contributing to the growth of our learning environment.</p><p>We\'re excited to see what you\'ll achieve next!</p><p>Celebrating your success,<br>The {{portal_name}} Team</p>', 'suredash' ),
				],
			],

			'event_new_introduced' => [
				'title'    => __( 'Upcoming Event Announcement', 'suredash' ),
				'isPro'    => true,
				'template' => [
					'subject' => __( '📅 Don\'t miss: {{event_title}} is coming up!', 'suredash' ),
					'body'    => __( '<p>Hi {{user_first_name}},</p><p>Mark your calendar! We have an exciting event coming up on <strong>{{portal_name}}</strong> that you won\'t want to miss.</p><p><strong>{{event_title}}</strong></p><p>This event promises to be both informative and engaging, bringing together our community for valuable discussions, learning opportunities, and networking.</p><p>📅 <strong>Event Details:</strong></p><ul><li>Date: {{event_date}}</li><li>Time: {{event_start_time}}</li><li>Platform: {{portal_name}}</li></ul><p>👉 <strong>View full details and register:</strong> <a href="{{event_url}}">{{event_url}}</a></p><p><strong>Why attend?</strong></p><ul><li>Connect with like-minded community members</li><li>Learn from experts and industry leaders</li><li>Participate in interactive discussions</li><li>Get your questions answered in real-time</li><li>Access exclusive content and resources</li></ul><p>Spaces are limited, so we encourage you to secure your spot early. This is a great opportunity to enhance your knowledge and network with peers who share your interests.</p><p>See you there!<br>The {{portal_name}} Team</p>', 'suredash' ),
				],
			],
			'event_updated'        => [
				'title'    => __( 'Event Update Notification', 'suredash' ),
				'isPro'    => true,
				'template' => [
					'subject' => __( '🔄 Important update for {{event_title}}', 'suredash' ),
					'body'    => __( '<p>Hi {{user_first_name}},</p><p>We have an important update regarding the upcoming event <strong>{{event_title}}</strong> on <strong>{{portal_name}}</strong>.</p><p>We want to make sure you have the latest information to ensure you don\'t miss out on this valuable opportunity. Please take a moment to review the updated details.</p><p><strong>Event: {{event_title}}</strong></p><p>👉 <strong>View the complete updated details:</strong> <a href="{{event_url}}">{{event_url}}</a></p><p><strong>Important reminders:</strong></p><ul><li>Check the updated date and time</li><li>Review any changes to the agenda or format</li><li>Note any new requirements or materials needed</li><li>Verify your registration status</li></ul><p>If you have any questions about these updates or need assistance, please don\'t hesitate to reach out. We\'re here to help ensure you have the best possible experience.</p><p>Thank you for your understanding, and we look forward to seeing you at the event!</p><p>Stay informed,<br>The {{portal_name}} Team</p>', 'suredash' ),
				],
			],
		];

		/**
		 * Filter to allow external plugins (like suredash-pro) to add more email triggers.
		 *
		 * @since 1.5.0
		 * @param array $all_triggers The available email triggers.
		 * @return array
		 */
		return apply_filters( 'suredash_available_email_triggers', $basic_triggers );
	}

	/**
	 * Get configured email triggers from database.
	 *
	 * @since 1.5.0
	 * @return array<string, array<string, mixed>>
	 */
	public function get_email_triggers_data(): array {
		return get_option( 'suredash_emails_triggers_data', [] );
	}

	/**
	 * Add new email trigger configuration.
	 *
	 * @since 1.5.0
	 * @param string               $trigger_key The trigger key.
	 * @param array<string, mixed> $config The trigger configuration.
	 * @return bool
	 */
	public function add_email_trigger( string $trigger_key, array $config ): bool {
		$configured_triggers = $this->get_email_triggers_data();
		$available_triggers  = $this->get_available_email_triggers();

		// Check if trigger exists in available triggers.
		if ( ! isset( $available_triggers[ $trigger_key ] ) ) {
			return false;
		}

		// Generate unique ID for this configuration.
		$config_id = uniqid( $trigger_key . '_' );

		// Add trigger configuration.
		$configured_triggers[ $config_id ] = [
			'trigger_key'  => $trigger_key,
			'title'        => $available_triggers[ $trigger_key ]['title'],
			'custom_title' => $config['custom_title'] ?? '',
			'template'     => $config['template'],
			'user_roles'   => $config['user_roles'] ?? [],
			'status'       => $config['status'] ?? false,
			'created_at'   => current_time( 'mysql' ),
		];

		update_option( 'suredash_emails_triggers_data', $configured_triggers );
		return true;
	}

	/**
	 * Update email trigger configuration.
	 *
	 * @since 1.5.0
	 * @param string               $config_id The configuration ID.
	 * @param array<string, mixed> $config The updated configuration.
	 * @return void|bool
	 */
	public function update_email_trigger( string $config_id, array $config ) {
		$configured_triggers = $this->get_email_triggers_data();

		if ( ! isset( $configured_triggers[ $config_id ] ) ) {
			return false;
		}

		$configured_triggers[ $config_id ] = array_merge( $configured_triggers[ $config_id ], $config );

		update_option( 'suredash_emails_triggers_data', $configured_triggers );
	}

	/**
	 * Delete email trigger configuration.
	 *
	 * @since 1.5.0
	 * @param string $config_id The configuration ID.
	 * @return bool
	 */
	public function delete_email_trigger( string $config_id ): bool {
		$configured_triggers = $this->get_email_triggers_data();

		if ( ! isset( $configured_triggers[ $config_id ] ) ) {
			return false;
		}

		unset( $configured_triggers[ $config_id ] );

		update_option( 'suredash_emails_triggers_data', $configured_triggers );
		return true;
	}

	/**
	 * Get available user roles for email targeting.
	 *
	 * @since 1.5.0
	 * @return array<string, string>
	 */
	public function get_user_roles_for_emails(): array {
		global $wp_roles;

		if ( ! isset( $wp_roles ) ) {
			return [];
		}

		return $wp_roles->get_names();
	}

	/**
	 * Initialize default email triggers for new installations.
	 *
	 * @since 1.5.0
	 * @return bool
	 */
	public function initialize_default_email_triggers(): bool {
		// Check if the option exists at all.
		$option_exists = get_option( 'suredash_emails_triggers_data', null );

		// If option exists (even if empty), don't initialize defaults.
		if ( $option_exists !== null ) {
			return false;
		}

		$available_triggers   = $this->get_available_email_triggers();
		$default_trigger_keys = [ 'user_onboarding' ];

		foreach ( $default_trigger_keys as $trigger_key ) {
			if ( isset( $available_triggers[ $trigger_key ] ) ) {
				$config = [
					'template'     => $available_triggers[ $trigger_key ]['template'],
					'user_roles'   => [ 'suredash_user' ], // Default to SureDash User role.
					'custom_title' => $available_triggers[ $trigger_key ]['title'] ?? '',
				];

				$this->add_email_trigger( $trigger_key, $config );
			}
		}

		return true;
	}

	/**
	 * Get available dynamic tags for email templates.
	 * Tags are grouped by trigger type for better organization.
	 *
	 * @since 1.5.0
	 * @param string $trigger_key Optional trigger key to get tags for specific trigger type.
	 * @return array<string, string> Array of dynamic tags with descriptions.
	 */
	public function get_available_dynamic_tags( string $trigger_key = '' ): array {
		// Common tags used across all triggers.
		$common_tags = [
			'{{user_name}}'         => __( 'User display name', 'suredash' ),
			'{{user_email}}'        => __( 'User email address', 'suredash' ),
			'{{user_login}}'        => __( 'User login username', 'suredash' ),
			'{{user_display_name}}' => __( 'User full display name', 'suredash' ),
			'{{user_first_name}}'   => __( 'User first name', 'suredash' ),
			'{{user_last_name}}'    => __( 'User last name', 'suredash' ),
			'{{user_registered}}'   => __( 'User registration date', 'suredash' ),
			'{{portal_name}}'       => __( 'Portal/Site name', 'suredash' ),
			'{{portal_url}}'        => __( 'Portal/Site URL', 'suredash' ),
		];

		// Tags grouped by trigger type.
		// External plugins can add their tags via the filter below.
		$trigger_tags = [
			'user_onboarding'      => $common_tags,
			'admin_space_created'  => $common_tags,
			'admin_post_published' => $common_tags,
			'course_completed'     => $common_tags,
			'event_new_introduced' => $common_tags,
			'event_updated'        => $common_tags,
		];

		// Space tags for admin_space_created trigger.
		$trigger_tags['admin_space_created'] = array_merge(
			$trigger_tags['admin_space_created'],
			[
				'{{space_name}}' => __( 'Space name', 'suredash' ),
				'{{space_url}}'  => __( 'Space URL', 'suredash' ),
			]
		);

		// Post tags for admin_post_published trigger.
		$trigger_tags['admin_post_published'] = array_merge(
			$trigger_tags['admin_post_published'],
			[
				'{{post_title}}'   => __( 'Post title', 'suredash' ),
				'{{post_url}}'     => __( 'Post URL', 'suredash' ),
				'{{post_excerpt}}' => __( 'Post excerpt', 'suredash' ),
			]
		);

		/**
		 * Filter dynamic tags grouped by trigger.
		 * External plugins (like suredash-pro) can add their own tags to specific triggers.
		 *
		 * @since 1.5.0
		 * @param array<string, array<string, string>> $trigger_tags Tags grouped by trigger key.
		 * @param string                               $trigger_key The current trigger key being requested.
		 */
		$trigger_tags = apply_filters( 'suredash_email_trigger_tags', $trigger_tags, $trigger_key );

		// If trigger specified, return tags for that trigger.
		if ( ! empty( $trigger_key ) && isset( $trigger_tags[ $trigger_key ] ) ) {
			return $trigger_tags[ $trigger_key ];
		}

		// If no trigger specified, return all unique tags from all triggers.
		$all_tags = [];
		foreach ( $trigger_tags as $tags ) {
			$all_tags = array_merge( $all_tags, $tags );
		}

		return $all_tags;
	}
}

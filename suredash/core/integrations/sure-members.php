<?php
/**
 * SureMembers Integration.
 *
 * @package SureDash
 */

namespace SureDashboard\Core\Integrations;

use SureDashboard\Inc\Traits\Get_Instance;
use SureDashboard\Inc\Utils\Helper;
use SureDashboard\Inc\Utils\Labels;

defined( 'ABSPATH' ) || exit;

/**
 * SureMembers Integration.
 *
 * @since 1.0.0
 */
class SureMembers extends Base {
	use Get_Instance;

	/**
	 * Stores the current restriction dataset for filter callback.
	 *
	 * @var array<string, mixed>
	 * @since 1.2.0
	 */
	private $current_restriction_dataset = [];

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->name        = 'SureMembers';
		$this->slug        = 'sure-members';
		$this->description = __( 'SureMembers Integration', 'suredash' );
		$this->is_active   = suredash_is_suremembers_active();
		// Initialize restriction dataset.
		$this->current_restriction_dataset = [
			'status'  => false,
			'content' => '',
		];

		parent::__construct( $this->name, $this->slug, $this->description, $this->is_active );

		if ( ! $this->is_active ) {
			return;
		}

		add_filter( 'suremembers_login_wrapper_class', [ $this, 'add_portal_content_wrapper' ], 10, 1 );
		add_filter( 'suredash_home_content', [ $this, 'maybe_restrict_feeds_page_content' ], 10, 2 );
		add_filter( 'suredash_user_view_show_posts', [ $this, 'maybe_restrict_user_view_posts' ], 10, 1 );
		add_filter( 'suredash_user_view_show_comments', [ $this, 'maybe_restrict_user_view_posts' ], 10, 1 );
		add_filter( 'suredash_show_discussion_space_content', [ $this, 'maybe_restrict_discussion_space_content' ], 10, 1 );
		add_filter( 'suredash_discussion_space_restriction_content', [ $this, 'get_discussion_space_restriction_html' ], 10, 1 );

		add_action( 'suredash_before_title_block', [ $this, 'check_restriction_navigation_space_icon' ], 10, 1 );
		add_action( 'suredash_after_title_block', [ $this, 'revert_navigation_space_icon' ], 10, 1 );
		add_action( 'suredash_before_aside_navigation_item', [ $this, 'check_restriction_navigation_space_icon' ], 10, 1 );
		add_action( 'suredash_after_aside_navigation_item', [ $this, 'revert_navigation_space_icon' ], 10, 1 );

		add_filter( 'suredash_post_backend_restriction_details', [ $this, 'check_suremembers_restriction_status' ], 10, 2 );

		add_action( 'template_redirect', [ $this, 'init_suremembers_integration' ], 1 );

		/**
		 * Process restriction content.
		 *
		 * @since 1.0.0
		 */
		if ( class_exists( '\SureMembers\Inc\Template_Redirect' ) && is_callable( [ \SureMembers\Inc\Template_Redirect::get_instance(), 'processed_content' ] ) ) {
			add_action( 'suredash_post_restriction_before_check', [ $this, 'check_post_restrictions' ], 10, 2 );
			add_action( 'suredash_post_restriction_after_check', [ $this, 'revert_post_restrictions' ], 10, 2 );
		}

		/**
		 * Support of user badges to assign if any access group granted or revoked.
		 *
		 * @since 1.5.0
		 */
		add_action( 'suremembers_user_access_group_granted', [ $this, 'assign_badge_on_access_grant' ], 10, 3 );
		add_action( 'suremembers_user_access_group_revoked', [ $this, 'revoke_badge_on_access_revoke' ], 10, 3 );

		/**
		 * Auto-assign new users to selected access groups on SureDash registration.
		 *
		 * @since 1.6.0
		 */
		add_action( 'suredash_user_registered', [ $this, 'auto_assign_access_group_on_registration' ], 20, 1 );
	}

	/**
	 * Add portal content wrapper class.
	 *
	 * @param string $class Class.
	 *
	 * @return string
	 * @since 1.0.0
	 */
	public function add_portal_content_wrapper( $class ) {
		return $class . ' portal-content';
	}

	/**
	 * Replace feeds page content with restriction banner when portal is globally restricted.
	 *
	 * @param string $content Rendered content for the current home-content type.
	 * @param string $type    Content type ('feeds', 'home', 'bookmarks', etc.).
	 * @return string
	 * @since 1.6.3
	 */
	public function maybe_restrict_feeds_page_content( $content, $type ) {
		if ( $type !== 'feeds' ) {
			return $content;
		}

		$access = $this->current_user_has_global_portal_access();

		if ( $access === null || $access ) {
			return $content;
		}

		$restriction_html = $this->get_global_restriction_content();
		if ( ! empty( $restriction_html ) ) {
			return $restriction_html;
		}

		return suredash_get_restricted_template_part(
			0,
			'parts',
			'restricted',
			[
				'icon'                   => 'Lock',
				'label'                  => 'restricted_content',
				'description'            => 'restricted_content_description',
				'skip_restriction_check' => true,
			],
			true
		);
	}

	/**
	 * Hide posts and comments on user-view profile page when portal is globally restricted.
	 *
	 * @param bool $show Current visibility state.
	 * @return bool
	 * @since 1.6.3
	 */
	public function maybe_restrict_user_view_posts( $show ) {
		if ( ! $show ) {
			return $show;
		}

		$access = $this->current_user_has_global_portal_access();

		return $access === null ? $show : $access;
	}

	/**
	 * Hide discussion space content when portal is globally restricted.
	 *
	 * Prevents the banner image, sort/view controls, posts container, and
	 * infinite-scroll trigger from rendering — all of which would otherwise
	 * expose restricted content or fire unprotected API calls.
	 *
	 * @param bool $show Current visibility state.
	 * @return bool
	 * @since 1.6.3
	 */
	public function maybe_restrict_discussion_space_content( $show ) {
		if ( ! $show ) {
			return $show;
		}

		$access = $this->current_user_has_global_portal_access();

		return $access === null ? $show : $access;
	}

	/**
	 * Provide restriction HTML for the discussion space when portal is globally restricted.
	 *
	 * Callback for `suredash_discussion_space_restriction_content`. Only fires when
	 * `suredash_show_discussion_space_content` has already returned false, so there is
	 * no need to re-check global access here.
	 *
	 * @param string $content Existing content from earlier filter callbacks (passed through if non-empty).
	 * @return string Restriction HTML or empty string (caller falls back to generic template).
	 * @since 1.6.3
	 */
	public function get_discussion_space_restriction_html( $content ) {
		if ( ! empty( $content ) ) {
			return $content;
		}

		return $this->get_global_restriction_content();
	}

	/**
	 * Update navigation space icon base on following cases.
	 *
	 * 1. Padlock: If the post is restricted by SureMembers.
	 * 2. Clock: If the post is dripped by SureMembers.
	 * 3. Default: If the post is not restricted by SureMembers.
	 *
	 * @param string $icon Icon.
	 * @param int    $post_id Post ID.
	 *
	 * @return string
	 * @since 1.0.0
	 */
	public function update_navigation_space_icon( $icon, $post_id ) {
		if ( is_user_logged_in() && current_user_can( 'manage_options' ) ) {
			return $icon;
		}

		if ( ! class_exists( '\SureMembers\Inc\Restricted' ) ) {
			return $icon;
		}

		$user_id    = intval( get_current_user_id() );
		$post_type  = sd_get_post_field( $post_id, 'post_type' );
		$post_title = get_the_title( $post_id );

		$option = [
			'include'           => SUREMEMBERS_PLAN_INCLUDE,
			'exclusion'         => SUREMEMBERS_PLAN_EXCLUDE,
			'priority'          => SUREMEMBERS_PLAN_PRIORITY,
			'current_post_type' => $post_type,
			'current_page_type' => 'is_singular',
			'current_post_id'   => $post_id,
		];

		$access_groups = \SureMembers\Inc\Restricted::by_access_groups( SUREMEMBERS_POST_TYPE, $option );
		if ( empty( $access_groups ) || empty( $access_groups[ SUREMEMBERS_POST_TYPE ] ) ) {
			return $icon;
		}

		$original_icon = $icon;
		$icon          = Helper::get_library_icon( 'Lock', false );

		// Set up accessibility attributes.
		$is_restricted    = true;
		$restriction_type = esc_attr__( 'content requires membership access', 'suredash' );

		if ( is_user_logged_in() && class_exists( '\SureMembers\Inc\Access_Groups' ) && is_callable( [ '\SureMembers\Inc\Access_Groups', 'check_user_has_post_access' ] ) ) {
			$has_access = \SureMembers\Inc\Access_Groups::check_user_has_post_access( $post_id, $access_groups, $user_id );
			if ( ! $has_access ) {
				$icon = Helper::get_library_icon( 'Lock', false );
			} else {
				$icon          = $original_icon;
				$is_restricted = false;
			}
		}

		$post_drip = false;
		if ( class_exists( '\SureMembers\Inc\Access_Groups' ) && is_callable( [ '\SureMembers\Inc\Access_Groups', 'check_is_post_is_dripping' ] ) ) {
			$post_drip = \SureMembers\Inc\Access_Groups::check_is_post_is_dripping( $post_id, $access_groups, $user_id );
			if ( $post_drip['status'] ?? false ) {
				$icon             = Helper::get_library_icon( 'Clock', false );
				$restriction_type = 'scheduled content';
			}
		}

		// Add accessibility attributes to navigation item if content is restricted.
		if ( $is_restricted ) {
			add_filter(
				'suredash_navigation_item_attributes_' . $post_id,
				static function( $attributes ) use ( $post_title, $restriction_type ) {
					$attributes['aria-label'] = sprintf(
					/* translators: 1: Post title, 2: Restriction type */
						esc_attr__( '%1$s, %2$s', 'suredash' ),
						esc_attr( $post_title ),
						esc_attr( $restriction_type )
					);
					return $attributes;
				}
			);
		}
		return $icon;
	}

	/**
	 * Check if the post is restricted by SureMembers.
	 *
	 * @param array<string, mixed> $dataset Dataset.
	 * @param int                  $post_id Post ID.
	 * @return array<string, mixed>
	 * @since 1.0.0
	 */
	public function is_restricted( $dataset, $post_id ) {
		if ( is_user_logged_in() && current_user_can( 'manage_options' ) ) {
			return $dataset;
		}

		if ( ! class_exists( '\SureMembers\Inc\Restricted' ) ) {
			return $dataset;
		}

		$user_id   = intval( get_current_user_id() );
		$post_type = sd_get_post_field( $post_id, 'post_type' );

		$consider_post_types = [];
		if ( suredash_content_post() || is_singular( SUREDASHBOARD_SUB_CONTENT_POST_TYPE ) ) {
			$post_type             = SUREDASHBOARD_SUB_CONTENT_POST_TYPE;
			$consider_post_types[] = SUREDASHBOARD_SUB_CONTENT_POST_TYPE;
		}
		if ( is_singular( SUREDASHBOARD_FEED_POST_TYPE ) ) {
			$post_type             = SUREDASHBOARD_FEED_POST_TYPE;
			$consider_post_types[] = SUREDASHBOARD_FEED_POST_TYPE;
		}

		$option = [
			'include'           => SUREMEMBERS_PLAN_INCLUDE,
			'exclusion'         => SUREMEMBERS_PLAN_EXCLUDE,
			'priority'          => SUREMEMBERS_PLAN_PRIORITY,
			'current_post_type' => is_singular( $consider_post_types ) ? $post_type : 'is_singular',
			'current_page_type' => 'is_singular',
			'current_post_id'   => $post_id,
		];

		$access_groups = \SureMembers\Inc\Restricted::by_access_groups( SUREMEMBERS_POST_TYPE, $option );
		if ( empty( $access_groups ) || empty( $access_groups[ SUREMEMBERS_POST_TYPE ] ) ) {
			return $dataset;
		}

		$sm_restricted            = false;
		$considered_access_groups = [];
		$original_dataset         = $dataset;

		if ( is_array( $access_groups ) ) {
			if ( isset( $access_groups[ SUREMEMBERS_POST_TYPE ] ) && ! empty( $access_groups[ SUREMEMBERS_POST_TYPE ] ) ) {
				foreach ( $access_groups[ SUREMEMBERS_POST_TYPE ] as $id => $plan ) {
					$access_group_details = class_exists( '\SureMembers\Inc\Access_Groups' ) && is_callable( [ '\SureMembers\Inc\Access_Groups', 'get_restriction_detail' ] ) ? \SureMembers\Inc\Access_Groups::get_restriction_detail( $id ) : [];
					if ( ! $sm_restricted ) {
						$sm_restricted            = true;
						$considered_access_groups = $access_group_details;
						$dataset                  = [
							'status'  => true,
							'content' => $this->get_restricted_message( '', $access_group_details ),
						];
					}
				}
			}
		}

		if ( is_user_logged_in() && class_exists( '\SureMembers\Inc\Access_Groups' ) && is_callable( [ '\SureMembers\Inc\Access_Groups', 'check_user_has_post_access' ] ) ) {
			$has_access = \SureMembers\Inc\Access_Groups::check_user_has_post_access( $post_id, $access_groups, $user_id );
			if ( ! $has_access ) {
				$dataset = [
					'status'  => true,
					'content' => $this->get_restricted_message( '', $considered_access_groups ),
				];
			} else {
				$dataset = $original_dataset;
			}
		}

		$post_drip = false;
		if ( class_exists( '\SureMembers\Inc\Access_Groups' ) && is_callable( [ '\SureMembers\Inc\Access_Groups', 'check_is_post_is_dripping' ] ) ) {
			$post_drip = \SureMembers\Inc\Access_Groups::check_is_post_is_dripping( $post_id, $access_groups, $user_id );
			if ( $post_drip['status'] ?? false ) {
				$dataset = [
					'status'  => true,
					'content' => $this->get_dripped_message( '', $post_drip['time'] ?? '' ),
				];
			}
		}

		return $dataset;
	}

	/**
	 * Check restriction navigation space icon.
	 *
	 * @param int $post_id Post ID.
	 *
	 * @return void
	 * @since 1.0.0
	 */
	public function check_restriction_navigation_space_icon( $post_id ): void {
		add_filter( 'suredash_aside_navigation_space_icon_' . $post_id, [ $this, 'update_navigation_space_icon' ], 10, 2 );
	}

	/**
	 * Revert navigation space icon.
	 *
	 * @param int $post_id Post ID.
	 *
	 * @return void
	 * @since 1.0.0
	 */
	public function revert_navigation_space_icon( $post_id ): void {
		remove_filter( 'suredash_aside_navigation_space_icon_' . $post_id, [ $this, 'update_navigation_space_icon' ] );
	}

	/**
	 * Check post restrictions & return the restriction details with status, content.
	 *
	 * @param array<string, mixed> $dataset Dataset.
	 * @param int                  $post_id Post ID.
	 * @return void
	 * @since 1.2.0
	 */
	public function check_post_restrictions( $dataset, $post_id ): void {
		$restriction_details = $this->is_restricted( [], $post_id );
		$status              = $restriction_details['status'] ?? $dataset['status'] ?? false;

		// Always update the current restriction dataset.
		$this->current_restriction_dataset = [
			'status'  => $status,
			'content' => $status ? ( $restriction_details['content'] ?? '' ) : '',
		];

		// Only add the filter if there's actually a restriction.
		if ( $status ) {
			add_filter(
				'suredash_post_restriction_ruleset',
				[ $this, 'apply_restriction_ruleset' ],
				10,
				2
			);
		}
	}

	/**
	 * Apply restriction ruleset filter callback.
	 *
	 * @param array<string, mixed> $default_ruleset Default ruleset.
	 * @param int                  $post_id Post ID.
	 * @return array<string, mixed>
	 * @since 1.2.0
	 */
	public function apply_restriction_ruleset( $default_ruleset, $post_id ) {
		return $this->current_restriction_dataset;
	}

	/**
	 * Add SureMembers sign to portal items.
	 *
	 * @param array<mixed> $dataset Status.
	 * @param int          $post_id Post ID.
	 *
	 * @return mixed
	 * @since 1.0.0
	 */
	public function check_suremembers_restriction_status( $dataset, $post_id ) {
		$option = [
			'include'           => SUREMEMBERS_PLAN_INCLUDE,
			'exclusion'         => SUREMEMBERS_PLAN_EXCLUDE,
			'priority'          => SUREMEMBERS_PLAN_PRIORITY,
			'current_post_id'   => $post_id,
			'current_post_type' => get_post_type( $post_id ),
			'current_page_type' => 'is_singular',
		];

		$access_groups = class_exists( '\SureMembers\Inc\Restricted' ) ? \SureMembers\Inc\Restricted::by_access_groups( SUREMEMBERS_POST_TYPE, $option ) : [];

		if ( ! empty( $access_groups ) && is_array( $access_groups ) ) {
			if ( isset( $access_groups[ SUREMEMBERS_POST_TYPE ] ) && ! empty( $access_groups[ SUREMEMBERS_POST_TYPE ] ) ) {
				foreach ( $access_groups[ SUREMEMBERS_POST_TYPE ] as $id => $plan ) {
					$access_group_instance = sd_get_post( $id );
					$post_title            = is_object( $access_group_instance ) && isset( $access_group_instance->post_title ) ? $access_group_instance->post_title : '';
					$redirection           = class_exists( '\SureMembers\Inc\Access_Groups' ) ? \SureMembers\Inc\Access_Groups::get_admin_url(
						[
							'page'    => 'suremembers_rules',
							'post_id' => $id,
						]
					) : '';
					$access_group_details  = class_exists( '\SureMembers\Inc\Access_Groups' ) && is_callable( [ '\SureMembers\Inc\Access_Groups', 'get_restriction_detail' ] ) ? \SureMembers\Inc\Access_Groups::get_restriction_detail( $id ) : [];
					$dataset               = [
						'status'              => true,
						'title'               => __( 'Access Group: ', 'suredash' ) . $post_title,
						'redirection'         => $redirection,
						'restriction_details' => $access_group_details,
					];
				}
			}
		}

		return $dataset;
	}

	/**
	 * Revert post restrictions.
	 *
	 * @param array<mixed> $dataset Status.
	 * @param int          $post_id Post ID.
	 *
	 * @return void
	 * @since 1.0.0
	 */
	public function revert_post_restrictions( $dataset, $post_id ): void {
		// Reset the dataset to default state.
		$this->current_restriction_dataset = [
			'status'  => false,
			'content' => '',
		];

		// Always try to remove the filter to ensure cleanup, even if it wasn't added.
		remove_filter(
			'suredash_post_restriction_ruleset',
			[ $this, 'apply_restriction_ruleset' ],
			10
		);
	}

	/**
	 * Remove SureMembers template action. As we are handling their "processed_content()" separately. We need to remove their action.
	 *
	 * @return void
	 * @since 1.0.0
	 */
	public function init_suremembers_integration(): void {
		if ( ! suredash_frontend() ) {
			return;
		}

		add_filter( 'suremembers_only_process_redirection', '__return_true' );
		add_filter( 'suremembers_load_restricted_page_template', '__return_false' );
	}

	/**
	 * Get the restricted message.
	 *
	 * @param string               $content Content.
	 * @param array<string, mixed> $restriction Restriction Rule.
	 * @since 1.0.0
	 * @return string|false
	 */
	public function get_restricted_message( $content, $restriction = [] ) {
		$is_in_content   = $restriction['in_content'] ?? true;
		$enable_login    = $restriction['enablelogin'] ?? false;
		$preview_button  = $restriction['preview_button'] ?? '';
		$redirect_url    = $restriction['redirect_url'] ?? '';
		$preview_content = $restriction['preview_content'] ?? Labels::get_label( 'restricted_content_notice' );
		$preview_heading = $restriction['preview_heading'] ?? Labels::get_label( 'restricted_content_heading' );

		if ( ! $is_in_content ) {
			return $content;
		}

		ob_start();

		suredash_get_template_part(
			'parts',
			'sm-restriction',
			[
				'icon'            => 'Lock',
				'heading'         => $preview_heading,
				'preview_button'  => $preview_button,
				'redirect_url'    => $redirect_url,
				'preview_content' => $preview_content,
				'enable_login'    => $enable_login,
			]
		);

		return ob_get_clean();
	}

	/**
	 * Get the restricted message.
	 *
	 * @param string $content Content.
	 * @param string $time Time.
	 * @since 1.0.0
	 * @return string|false
	 */
	public function get_dripped_message( $content, $time ) {
		ob_start();

		suredash_get_template_part(
			'parts',
			'restricted',
			[
				'icon'          => 'Clock',
				'label'         => 'dripped_content_heading',
				'description'   => 'dripped_content_notice',
				'extra_content' => $time,
			]
		);

		return shortcode_unautop( strval( ob_get_clean() ) );
	}

	/**
	 * Assign badge to user when access is granted.
	 *
	 * @param int   $user_id current user.
	 * @param int   $access_group_id array of multiple access groups or single access group can be provided.
	 * @param mixed $access_group_ids array of multiple access groups or single access group can be provided.
	 *
	 * @since 1.5.0
	 */
	public function assign_badge_on_access_grant( $user_id, $access_group_id, $access_group_ids ): void {
		$portal_badges = Helper::get_option( 'user_badges' );

		if ( empty( $portal_badges ) ) {
			return;
		}

		if ( is_array( $portal_badges ) ) {
			foreach ( $portal_badges as $badge ) {
				if ( ! empty( $badge['sm_access_rule'] ) && is_array( $badge['sm_access_rule'] ) ) {
					foreach ( $badge['sm_access_rule'] as $access_group_rule ) {
						$sm_access_gr_id = absint( $access_group_rule['value'] ?? 0 );
						if ( $sm_access_gr_id === $access_group_id ) {
							$user_badges = sd_get_user_meta( $user_id, 'portal_badges', true );
							if ( empty( $user_badges ) ) {
								sd_update_user_meta(
									$user_id,
									'portal_badges',
									[
										[
											'id'   => $badge['id'],
											'name' => $badge['name'],
										],
									]
								);
							} else {
								$user_already_with_badge = false;
								foreach ( $user_badges as $user_badge ) {
									if ( $user_badge['id'] === $badge['id'] ) {
										$user_already_with_badge = true;
										break;
									}
								}

								if ( ! $user_already_with_badge ) {
									array_push(
										$user_badges,
										[
											'id'   => $badge['id'],
											'name' => $badge['name'],
										]
									);
									sd_update_user_meta(
										$user_id,
										'portal_badges',
										$user_badges,
									);
								}
							}
						}
					}
				}
			}
		}
	}

	/**
	 * Revoke badge from user when access is revoked.
	 *
	 * @param int   $user_id current user.
	 * @param int   $access_group_id array of multiple access groups or single access group can be provided.
	 * @param mixed $access_group_ids array of multiple access groups or single access group can be provided.
	 *
	 * @since 1.5.0
	 */
	public function revoke_badge_on_access_revoke( $user_id, $access_group_id, $access_group_ids ): void {
		$user_badges   = sd_get_user_meta( $user_id, 'portal_badges', true );
		$portal_badges = Helper::get_option( 'user_badges' );

		if ( empty( $portal_badges ) && empty( $user_badges ) ) {
			return;
		}

		if ( ! empty( $user_badges ) ) {
			$badges_modified = false;

			foreach ( $user_badges as $index => $badge ) {
				$badge_id            = $badge['id'];
				$should_remove_badge = false;
				$to_verify_group_ids = [];

				foreach ( $portal_badges as $portal_badge ) {
					if ( $badge_id !== $portal_badge['id'] || empty( $portal_badge['sm_access_rule'] ) ) {
						continue;
					}
					foreach ( $portal_badge['sm_access_rule'] as $access_group_rule ) {
						$sm_access_gr_id = absint( $access_group_rule['value'] ?? 0 );
						if ( $sm_access_gr_id === $access_group_id ) {
							$should_remove_badge = true;
						} else {
							$to_verify_group_ids[] = $sm_access_gr_id;
						}
					}
				}

				// If badge is linked to the revoked access group, check if user still has other linked access groups.
				if ( $should_remove_badge ) {
					// If there are other access groups linked to this badge, verify user still has access to them.
					if ( ! empty( $to_verify_group_ids ) ) {
						$user_has_access = class_exists( '\SureMembers\Inc\Access_Groups' ) && is_callable( [ '\SureMembers\Inc\Access_Groups', 'check_user_access_by_id' ] ) ? \SureMembers\Inc\Access_Groups::check_user_access_by_id( $user_id, $to_verify_group_ids ) : false;
						if ( $user_has_access ) {
							// User still has access to other groups linked to this badge, don't remove it.
							$should_remove_badge = false;
						}
					}

					// Remove the badge if user no longer has access to any linked access groups.
					if ( $should_remove_badge ) {
						unset( $user_badges[ $index ] );
						$badges_modified = true;
					}
				}
			}

			// Update user meta only if badges were actually modified.
			if ( $badges_modified ) {
				sd_update_user_meta( $user_id, 'portal_badges', $user_badges );
			}
		}
	}

	/**
	 * Auto-assign new users to selected access groups on registration.
	 *
	 * @param int $user_id The ID of the newly registered user.
	 *
	 * @since 1.6.0
	 * @return void
	 */
	public function auto_assign_access_group_on_registration( $user_id ): void {

		$selected_access_groups = Helper::get_option( 'suredash_register_user_access_groups', [] );

		if ( ! is_array( $selected_access_groups ) || empty( $selected_access_groups ) ) {
			return;
		}

		if ( ! class_exists( '\SureMembers\Inc\Access' ) ) {
			return;
		}

		$access_group_ids = array_filter(
			array_map( 'absint', $selected_access_groups ),
			static function( $id ) {
				return $id > 0;
			}
		);

		if ( empty( $access_group_ids ) ) {
			return;
		}

		\SureMembers\Inc\Access::grant(
			$user_id,
			$access_group_ids,
			'suredash', // Integration name.
			[], // No expiration.
			true // Send email notification.
		);
	}

	/**
	 * Build the SureMembers restriction HTML for a globally-restricted content area.
	 *
	 * Reads the restriction settings from the first (highest-priority) access group
	 * that is applying a global rule and delegates to get_restricted_message() so the
	 * output respects the "In Content" toggle and "Enable Login Button" toggle
	 * configured by the site admin.
	 *
	 * Returns an empty string when no global restriction exists or when the access
	 * group has "In Content" turned off — callers should fall back to the generic
	 * parts/restricted template in that case.
	 *
	 * @return string Rendered restriction HTML, or empty string.
	 * @since 1.6.3
	 */
	private function get_global_restriction_content(): string {
		$merged_groups = $this->get_portal_restricting_access_groups();

		if ( empty( $merged_groups ) ) {
			return '';
		}

		$first_group_id      = (int) array_key_first( $merged_groups );
		$restriction_details = is_callable( [ '\SureMembers\Inc\Access_Groups', 'get_restriction_detail' ] )
			? \SureMembers\Inc\Access_Groups::get_restriction_detail( $first_group_id ) // @phpstan-ignore class.notFound (SureMembers external plugin)
			: [];

		return (string) $this->get_restricted_message( '', $restriction_details );
	}

	/**
	 * Get SureMembers access groups that restrict all portal content globally.
	 *
	 * Covers three rule types:
	 *   - portal|all                 (All Portal singular content)
	 *   - portal_group|all|archive   (All Portal Space Group Archive)
	 *   - community-post|all         (All Community Posts)
	 *
	 * @return array<int|string, mixed> Merged access group data keyed by group ID, or empty array.
	 * @since 1.6.3
	 */
	private function get_portal_restricting_access_groups(): array {
		if ( ! class_exists( '\SureMembers\Inc\Restricted' ) ) {
			return [];
		}

		$base_option = [
			'include'         => SUREMEMBERS_PLAN_INCLUDE,
			'exclusion'       => SUREMEMBERS_PLAN_EXCLUDE,
			'priority'        => SUREMEMBERS_PLAN_PRIORITY,
			'current_post_id' => 0,
		];

		$checks = [
			array_merge(
				$base_option,
				[
					'current_post_type' => SUREDASHBOARD_POST_TYPE,
					'current_page_type' => 'is_singular',
				]
			),
			array_merge(
				$base_option,
				[
					'current_post_type' => SUREDASHBOARD_TAXONOMY,
					'current_page_type' => 'is_archive',
				]
			),
			array_merge(
				$base_option,
				[
					'current_post_type' => SUREDASHBOARD_FEED_POST_TYPE,
					'current_page_type' => 'is_singular',
				]
			),
		];

		$merged_groups = [];

		foreach ( $checks as $option ) {
			$result = \SureMembers\Inc\Restricted::by_access_groups( SUREMEMBERS_POST_TYPE, $option );
			if ( ! empty( $result[ SUREMEMBERS_POST_TYPE ] ) ) {
				$merged_groups += $result[ SUREMEMBERS_POST_TYPE ];
			}
		}

		return $merged_groups;
	}

	/**
	 * Check whether the current user has access to globally-restricted portal content.
	 *
	 * Returns null when no global restriction exists (callers should treat as "no restriction").
	 * Returns true when a restriction exists and the user has access.
	 * Returns false when a restriction exists and the user is restricted.
	 * Admins always get null (bypass).
	 *
	 * @return bool|null null = no global restriction, true = has access, false = restricted.
	 * @since 1.6.3
	 */
	private function current_user_has_global_portal_access(): ?bool {
		if ( ! $this->is_active ) {
			return null;
		}

		if ( is_user_logged_in() && current_user_can( 'manage_options' ) ) {
			return null;
		}

		$merged_groups = $this->get_portal_restricting_access_groups();

		if ( empty( $merged_groups ) ) {
			return null;
		}

		if ( ! is_user_logged_in() ) {
			return false;
		}

		if ( class_exists( '\SureMembers\Inc\Access_Groups' ) && is_callable( [ '\SureMembers\Inc\Access_Groups', 'check_user_has_post_access' ] ) ) {
			return (bool) \SureMembers\Inc\Access_Groups::check_user_has_post_access(
				0,
				[ SUREMEMBERS_POST_TYPE => $merged_groups ],
				get_current_user_id()
			);
		}

		return false;
	}
}

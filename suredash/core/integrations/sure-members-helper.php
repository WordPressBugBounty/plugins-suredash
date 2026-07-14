<?php
/**
 * SureMembers restriction engine.
 *
 * One place that answers two questions for ANY space type:
 *   - get_item_state()  : should a single child item show, hide, or behave normally?
 *   - get_space_state() : should a space render normally, as a doorway, or as a locked banner?
 *
 * Vocabulary used throughout:
 *   - "block"     : a SureMembers access group protects the content AND the current user has no access.
 *   - "exception" : a child the admin added to a group's exclude list. It stays visible and turns
 *                   its space into a "doorway" (the space renders, listing only the freed children).
 *
 * Exceptions are honored for every block type — broad, specific-space, or taxonomy alike.
 *
 * @package SureDash
 */

namespace SureDashboard\Core\Integrations;

use SureDashboard\Inc\Traits\Get_Instance;

defined( 'ABSPATH' ) || exit;

/**
 * SureMembers restriction engine.
 *
 * @since 1.10.0
 */
class SureMembers_Helper {
	use Get_Instance;

	/**
	 * SureMembers stores a page-post target as "post-{id}-{slug}". Matches the leading ID.
	 * Coupled to SureMembers' internal rule format — revisit if that storage format changes.
	 *
	 * @since 1.10.0
	 */
	private const SM_PAGE_POST_PATTERN = '/post-(\d+)-/';

	/**
	 * SureMembers stores a single excepted post in an exclude rule as "post-{id}-|".
	 * Coupled to SureMembers' internal rule format — revisit if that storage format changes.
	 *
	 * @since 1.10.0
	 */
	private const SM_EXCLUDE_RULE_PATTERN = '/^post-(\d+)-\|$/';

	/**
	 * Per-request memo cache (group lookups are expensive; results never change mid-request).
	 *
	 * @var array<string, mixed>
	 * @since 1.10.0
	 */
	private $cache = [];

	/**
	 * User the engine currently evaluates as. Null = the logged-in user (the normal front-end
	 * case). Set via as_user() so a background context (email batch, cron) can ask the same
	 * questions on behalf of an arbitrary recipient.
	 *
	 * @var int|null
	 * @since 1.10.0
	 */
	private $acting_user = null;

	// === Public API ===

	/**
	 * Run a callback with the engine evaluating as a specific user, then restore context.
	 *
	 * Lets background contexts (email batches, cron) reuse the whole restriction engine — every
	 * public method funnels through is_admin() / user_blocked_by(), which honour the acting user.
	 * The per-user memo is flushed on entry and exit: one request may evaluate many recipients in
	 * turn, and collection_index caches a per-user "blocked" verdict that would otherwise leak from
	 * one recipient to the next (the rest of the cache is user-independent config and stays warm).
	 *
	 * @param int      $user_id  User to evaluate as.
	 * @param callable $callback Receives $this; its return value is passed straight back.
	 * @return mixed
	 * @since 1.10.0
	 */
	public function as_user( int $user_id, callable $callback ) {
		$previous          = $this->acting_user;
		$this->acting_user = $user_id;
		unset( $this->cache['collection_index'] );
		try {
			return $callback( $this );
		} finally {
			$this->acting_user = $previous;
			unset( $this->cache['collection_index'] );
		}
	}

	/**
	 * Restriction state for a single child item (lesson, event, resource, discussion thread).
	 *
	 * @param int $item_id  Child post ID.
	 * @param int $space_id Optional parent space ID (resolved from the item when 0).
	 * @return string 'normal' | 'show' | 'hidden'
	 * @since 1.10.0
	 */
	public function get_item_state( $item_id, $space_id = 0 ): string {
		$item_id = absint( $item_id );
		if ( $item_id <= 0 || $this->is_admin() ) {
			return 'normal';
		}

		$space_id     = $space_id ? absint( $space_id ) : $this->space_of( $item_id );
		$space_groups = $this->blocking_groups( $space_id, (string) get_post_type( $item_id ) );
		$groups       = $space_groups + $this->post_groups( $item_id );

		if ( ! $this->user_blocked_by( $groups ) ) {
			// Not blocked by its own space's rules — but a restricted collection may cascade a lock
			// down. The cascade gate stays keyed to the SPACE-level groups: an item-targeting rule
			// the user satisfies must not exempt the item from its collections' lock.
			return empty( $space_groups ) && $this->cascade_restricted( $space_id ) ? 'hidden' : 'normal';
		}

		// Blocked: the item is freed ('show') ONLY if every blocking group also excludes it. If any
		// blocking group restricts it without an exclude, that group still locks it — an exclude in
		// one access group must not open content another group protects (multi-group correctness).
		foreach ( array_keys( $groups ) as $group_id ) {
			if ( ! in_array( $item_id, $this->group_exception_ids( (int) $group_id ), true ) ) {
				return 'hidden';
			}
		}
		return 'show';
	}

	/**
	 * Resolve the configured restriction action for a locked item.
	 *
	 * Reads the highest-priority blocking group's SureMembers restriction settings and
	 * normalizes them into one payload the front-end can act on.
	 *
	 * @param int $item_id  Child post ID.
	 * @param int $space_id Optional parent space ID.
	 * @return array<string, mixed> Empty when the item is not locked.
	 * @since 1.10.0
	 */
	public function get_item_action( $item_id, $space_id = 0 ): array {
		if ( $this->get_item_state( $item_id, $space_id ) !== 'hidden' ) {
			return [];
		}

		$space_id = $space_id ? absint( $space_id ) : $this->space_of( absint( $item_id ) );
		$groups   = $this->blocking_groups( $space_id, (string) get_post_type( $item_id ) )
			+ $this->post_groups( absint( $item_id ) );
		$action   = $this->build_action( $groups );

		// A cascade-only lock leaves the item's own group set empty (the block lives on the
		// collections) — take the payload from the space, which resolves the collections' groups.
		return ! empty( $action ) ? $action : $this->get_space_action( $space_id );
	}

	/**
	 * Resolve the restriction action for a locked SPACE (e.g. a collection card / referenced space).
	 *
	 * Space-level counterpart to get_item_action(): the lock comes from the space's own blocking
	 * groups, or — when the space is only cascade-locked by a collection — the restricting
	 * collection's groups.
	 *
	 * @param int $space_id Space (portal) post ID.
	 * @return array<string, mixed> Empty when the space is not locked.
	 * @since 1.10.0
	 */
	public function get_space_action( $space_id ): array {
		$space_id = absint( $space_id );
		if ( $space_id <= 0 || $this->is_admin() || $this->get_space_state( $space_id ) !== 'locked' ) {
			return [];
		}

		$config = $this->config_for_space( $space_id );
		$groups = $this->blocking_groups( $space_id, (string) ( $config['child_post_type'] ?? '' ) );

		// Cascade-only lock (no rule on the space itself): use the restricting collections' groups.
		if ( empty( $groups ) ) {
			$index = $this->collection_index();
			foreach ( $index['by_space'][ $space_id ] ?? [] as $collection_id ) {
				if ( ! empty( $index['blocked'][ $collection_id ] ) ) {
					$groups += $index['groups'][ $collection_id ];
				}
			}
		}

		return $this->build_action( $groups );
	}

	/**
	 * Whether listings should OMIT restricted items instead of locking them.
	 *
	 * Default false — the model is restriction, not concealment. Sites opt in via the filter.
	 *
	 * @return bool
	 * @since 1.10.0
	 */
	public function should_hide_restricted(): bool {
		return (bool) apply_filters( 'suredash_sm_hide_restricted', false );
	}

	/**
	 * Whether a locked child item should be omitted from listings entirely (hide-mode) instead of
	 * shown as a locked card. Single source of truth for the "locked AND hide-mode" listing rule —
	 * call this from any listing filter so the hide-mode check can't be forgotten.
	 *
	 * @param int $item_id  Child post ID.
	 * @param int $space_id Optional parent space ID.
	 * @return bool
	 * @since 1.10.0
	 */
	public function should_hide_item( $item_id, $space_id = 0 ): bool {
		// Short-circuit when hide-mode is off (the default) so the action lookup is skipped.
		return $this->should_hide_restricted() && ! empty( $this->get_item_action( $item_id, $space_id ) );
	}

	/**
	 * Space-level counterpart of should_hide_item(), for listings of spaces (home grid, collections).
	 *
	 * @param int $space_id Space (portal) post ID.
	 * @return bool
	 * @since 1.10.0
	 */
	public function should_hide_space( $space_id ): bool {
		return $this->should_hide_restricted() && ! empty( $this->get_space_action( $space_id ) );
	}

	/**
	 * Restriction state for a space (course, events, resource library, discussion, collection).
	 *
	 * @param int $space_id Space (portal) post ID.
	 * @return string 'normal' | 'show' (doorway) | 'locked' (banner)
	 * @since 1.10.0
	 */
	public function get_space_state( $space_id ): string {
		$space_id = absint( $space_id );
		if ( $space_id <= 0 || $this->is_admin() ) {
			return 'normal';
		}

		$config = $this->config_for_space( $space_id );
		$groups = $this->blocking_groups( $space_id, (string) ( $config['child_post_type'] ?? '' ) );

		if ( ! $this->user_blocked_by( $groups ) ) {
			// Not blocked directly. A space with its own (satisfied) rule keeps access; a space with no
			// rule of its own is locked when every collection listing it is restricted (cascade).
			return empty( $groups ) && $this->cascade_restricted( $space_id ) ? 'locked' : 'normal';
		}

		// Blocked. Content space types (no child listing — e.g. single_post) always banner: there are
		// no items to lock individually, so opening the space would leak its body.
		$can_doorway = ! empty( $config['doorway'] ) || ( $config['mode'] ?? '' ) === 'aggregate';

		// A broad block (portal-wide / all-community-posts / all-community-content) keeps a listing
		// space open: the space renders and each blocked child shows as a locked (blurred) card. The
		// full-page banner is reserved for rules that target THIS space directly — a specific-space
		// entry or one of the space's own taxonomy terms (post_groups minus the broad portal set).
		if ( $can_doorway && ! $this->user_blocked_by( array_diff_key( $this->post_groups( $space_id ), $this->portal_wide_groups() ) ) ) {
			return 'show';
		}

		// Directly locked: still a doorway when a freed child exists. Otherwise show the banner — the
		// freed item stays reachable directly, we just don't expose the listing of a space whose own
		// rule the admin pointed at it.
		return $can_doorway && $this->space_has_freed_child( $space_id, $config ) ? 'show' : 'locked';
	}

	/**
	 * Whether the wholesale feed/profile listing should render at all.
	 *
	 * Always true: broad blocks no longer banner these listings. The listing renders and the
	 * per-post filters lock (blur) each post the viewer can't access — matching how broad blocks
	 * behave inside spaces. Kept as the single gate point so call sites don't change if a
	 * wholesale case ever returns.
	 *
	 * @return bool
	 * @since 1.10.0
	 */
	public function can_view_feed_listing(): bool {
		return true;
	}

	/**
	 * Rules that lock EVERY space: "All Portal" + "All Portal Group" archive.
	 *
	 * Note: "All Community Posts" (community-post|all) is deliberately NOT here — it targets
	 * discussion posts only, and is added per-discussion-space via the registry and to the
	 * feed-level set below. Putting it here would wrongly lock courses/events/resources too.
	 *
	 * @return array<int|string, mixed>
	 * @since 1.10.0
	 */
	public function portal_wide_groups(): array {
		if ( isset( $this->cache['portal_wide'] ) ) {
			return $this->cache['portal_wide'];
		}

		$groups  = $this->lookup( SUREDASHBOARD_POST_TYPE, 'is_singular' );
		$groups += $this->lookup( SUREDASHBOARD_TAXONOMY, 'is_archive' );

		$this->cache['portal_wide'] = $groups;
		return $groups;
	}

	/**
	 * Groups that gate community-post surfaces (home feed, profile posts, discussion banner):
	 * the portal-wide rules plus "All Community Posts".
	 *
	 * @return array<int|string, mixed>
	 * @since 1.10.0
	 */
	public function feed_blocking_groups(): array {
		if ( isset( $this->cache['feed_blocking'] ) ) {
			return $this->cache['feed_blocking'];
		}

		$groups  = $this->portal_wide_groups();
		$groups += $this->type_all_groups( SUREDASHBOARD_FEED_POST_TYPE );

		$this->cache['feed_blocking'] = $groups;
		return $groups;
	}

	/**
	 * Enforce the collection cascade at the per-post layer (filter: suredash_post_protection).
	 *
	 * SureMembers has no rule on a space that is only locked by cascade, so a direct deep-link
	 * to its child would otherwise slip through. This forces "protected" for exactly that gap,
	 * leaving every SureMembers-driven decision untouched.
	 *
	 * @param mixed $protection Current protection result (bool status, or restriction array).
	 * @param int   $post_id    Post being checked.
	 * @return mixed
	 * @since 1.10.0
	 */
	public function enforce_cascade_protection( $protection, $post_id ) {
		$already = is_array( $protection ) ? ! empty( $protection['status'] ) : (bool) $protection;
		if ( $already || ! $this->is_cascade_locked_post( absint( $post_id ) ) ) {
			return $protection;
		}
		return is_array( $protection ) ? [
			'status'  => true,
			'content' => '',
		] : true;
	}

	/**
	 * Build the normalized action payload from a set of blocking groups (highest priority wins).
	 *
	 * @param array<int|string, mixed> $groups Blocking access groups keyed by ID.
	 * @return array<string, mixed> Empty when no group applies.
	 * @since 1.10.0
	 */
	private function build_action( array $groups ): array {
		$group_id = $this->highest_priority_group( $groups );
		if ( $group_id <= 0 ) {
			return [];
		}

		$rules     = get_post_meta( $group_id, 'suremembers_plan_rules', true );
		$restrict  = is_array( $rules ) && ! empty( $rules['restrict'] ) ? $rules['restrict'] : [];
		$sm_action = (string) ( $restrict['unauthorized_action'] ?? 'preview' );

		// Map SureMembers actions onto our three. Unknown/empty falls back to the message.
		$action = 'message';
		if ( $sm_action === 'redirect' ) {
			$action = 'redirect';
		} elseif ( $sm_action === 'page_post' ) {
			$action = 'page';
		}

		$page_id = 0;
		if ( $action === 'page' && ! empty( $restrict['restrict_page_post'] ) && preg_match( self::SM_PAGE_POST_PATTERN, (string) $restrict['restrict_page_post'], $matches ) ) {
			$page_id = (int) $matches[1];
		}

		// A page action with an unusable target falls back to the message modal.
		if ( $action === 'page' && ( $page_id <= 0 || get_post_status( $page_id ) !== 'publish' ) ) {
			$action = 'message';
		}

		return [
			'action'       => $action,
			'redirect_url' => (string) ( $restrict['redirect_url'] ?? '' ),
			'heading'      => (string) ( $restrict['preview_heading'] ?? '' ),
			'message'      => (string) ( $restrict['preview_content'] ?? '' ),
			'button'       => (string) ( $restrict['preview_button'] ?? '' ),
			'login'        => ! empty( $restrict['enablelogin'] ),
			'in_content'   => ! empty( $restrict['in_content'] ),
			'page_id'      => $page_id,
			// Raw SureMembers restriction settings, so the message modal can render the exact
			// same template the banner uses via SureMembers::get_restricted_message().
			'restrict'     => is_array( $restrict ) ? $restrict : [],
		];
	}

	// === Space-type registry ===

	/**
	 * Map of integration => how to find its child items. Pro adds course/events/resources/collection.
	 *
	 * Entry keys:
	 *   - child_post_type : post type of the space's children.
	 *   - content_type    : optional discriminator meta value for the shared community-content type.
	 *   - link_meta       : meta key on the child holding its space ID (default linkage).
	 *   - link_callback   : callable( child_id ) => space_id (used when linkage is not a plain meta).
	 *   - mode            : 'aggregate' for spaces (collections) whose children are other spaces.
	 *
	 * @return array<string, array<string, mixed>>
	 * @since 1.10.0
	 */
	private function registry(): array {
		if ( isset( $this->cache['registry'] ) ) {
			return $this->cache['registry'];
		}

		// Free default: discussion spaces. Threads are community-post, linked via the forum taxonomy.
		// 'doorway' => true: the feed query constrains to freed thread IDs, so a partial listing is safe.
		$registry = [
			'posts_discussion' => [
				'child_post_type' => SUREDASHBOARD_FEED_POST_TYPE,
				'link_callback'   => 'sd_get_space_id_by_post',
				'doorway'         => true,
			],
		];

		$this->cache['registry'] = (array) apply_filters( 'suredash_sm_space_type_registry', $registry );
		return $this->cache['registry'];
	}

	/**
	 * Registry entry for a space, looked up by its integration meta.
	 *
	 * @param int $space_id Space (portal) post ID.
	 * @return array<string, mixed>
	 * @since 1.10.0
	 */
	private function config_for_space( $space_id ): array {
		$integration = (string) sd_get_post_meta( $space_id, 'integration', true );
		return $this->registry()[ $integration ] ?? [];
	}

	/**
	 * Registry entry for a child item, matched by post type (and content_type for community-content).
	 *
	 * @param int $item_id Child post ID.
	 * @return array<string, mixed>
	 * @since 1.10.0
	 */
	private function config_for_item( $item_id ): array {
		$post_type = (string) get_post_type( $item_id );

		// Lessons, events and resources share one post type; tell them apart by content_type.
		$content_type = '';
		if ( $post_type === SUREDASHBOARD_SUB_CONTENT_POST_TYPE ) {
			$content_type = (string) sd_get_post_meta( $item_id, 'content_type', true );
			$content_type = $content_type !== '' ? $content_type : 'lesson'; // legacy lessons store no content_type.
			$content_type = $content_type === 'quiz' ? 'lesson' : $content_type; // quizzes are course children via the same linkage.
		}

		foreach ( $this->registry() as $config ) {
			if ( ( $config['child_post_type'] ?? '' ) !== $post_type ) {
				continue;
			}
			if ( $content_type !== '' && ! empty( $config['content_type'] ) && $config['content_type'] !== $content_type ) {
				continue;
			}
			return $config;
		}

		return [];
	}

	// === Block detection ===

	/**
	 * Every access group that protects a space's children, ignoring exceptions.
	 *
	 * Combines three sources so broad and specific rules are treated identically:
	 *   1. portal-wide rules (cover everything),
	 *   2. the child post type's "all" rule (e.g. community-content|all),
	 *   3. groups targeting the space itself (specific-space or taxonomy).
	 *
	 * @param int    $space_id        Space (portal) post ID (0 when unknown).
	 * @param string $child_post_type Post type of the space's children.
	 * @return array<int|string, mixed> Groups keyed by group ID (union dedupes overlaps).
	 * @since 1.10.0
	 */
	private function blocking_groups( $space_id, $child_post_type ): array {
		$key = 'blocking_' . $space_id . '_' . $child_post_type;
		if ( isset( $this->cache[ $key ] ) ) {
			return $this->cache[ $key ];
		}

		$groups  = $this->portal_wide_groups();
		$groups += $this->type_all_groups( $child_post_type );
		if ( $space_id > 0 ) {
			$groups += $this->post_groups( $space_id );

			// Honor the space's own exclude entry. The portal-wide and type-all lookups run with
			// post_id 0, so SureMembers cannot drop a group that excludes THIS space there — a group
			// listing "post-{space_id}-|" on its exclude list would otherwise still count as blocking.
			// Remove it here so an excepted space (and its children) reads as free, matching
			// SureMembers' native "an excluded post is unprotected by that group" rule.
			foreach ( array_keys( $groups ) as $group_id ) {
				if ( in_array( (int) $space_id, $this->group_exception_ids( (int) $group_id ), true ) ) {
					unset( $groups[ $group_id ] );
				}
			}
		}

		$this->cache[ $key ] = $groups;
		return $groups;
	}

	/**
	 * The blocking group with the highest SureMembers priority (lowest number = highest).
	 *
	 * @param array<int|string, mixed> $groups Blocking groups keyed by ID.
	 * @return int Group ID, or 0.
	 * @since 1.10.0
	 */
	private function highest_priority_group( array $groups ): int {
		$best_id  = 0;
		$best_pri = null;
		foreach ( array_keys( $groups ) as $group_id ) {
			$raw_priority = get_post_meta( (int) $group_id, 'suremembers_plan_priority', true );
			// A group with no explicit priority must rank LAST, not first — an empty meta cast to 0
			// would otherwise win as the highest priority over groups with real priorities.
			$priority = $raw_priority === '' || $raw_priority === null ? PHP_INT_MAX : (int) $raw_priority;
			if ( $best_pri === null || $priority < $best_pri ) {
				$best_pri = $priority;
				$best_id  = (int) $group_id;
			}
		}
		return $best_id;
	}

	/**
	 * Groups applying an "all {post type}" rule (e.g. community-content|all).
	 *
	 * @param string $post_type Child post type.
	 * @return array<int|string, mixed>
	 * @since 1.10.0
	 */
	private function type_all_groups( $post_type ): array {
		if ( $post_type === '' ) {
			return [];
		}
		$key = 'type_all_' . $post_type;
		if ( ! isset( $this->cache[ $key ] ) ) {
			$this->cache[ $key ] = $this->lookup( $post_type, 'is_singular' );
		}
		return $this->cache[ $key ];
	}

	/**
	 * Groups whose rules target a post directly (specific pages/posts, or its taxonomy terms).
	 *
	 * Works for spaces and child items alike — SureMembers resolves the post's type, ID, parent,
	 * and terms from the ID. For child items this is what surfaces "Specific Pages / Posts /
	 * Taxonomies" rules: the other sources in blocking_groups() run with post_id 0 or the SPACE id,
	 * so a rule aimed straight at an item never appears there — the item would fall through to the
	 * legacy protection path and be omitted from listings instead of rendered as a locked card.
	 * SureMembers natively drops groups whose exclude list carries the post, so no extra exception
	 * handling is needed.
	 *
	 * @param int $post_id Space or child post ID.
	 * @return array<int|string, mixed>
	 * @since 1.10.0
	 */
	private function post_groups( $post_id ): array {
		$key = 'post_groups_' . $post_id;
		if ( ! isset( $this->cache[ $key ] ) ) {
			$this->cache[ $key ] = $this->lookup( (string) get_post_type( $post_id ), 'is_singular', $post_id );
		}
		return $this->cache[ $key ];
	}

	/**
	 * Raw SureMembers lookup for one (post type, page type, post ID) tuple.
	 *
	 * @param string $post_type Post type.
	 * @param string $page_type SureMembers page type ('is_singular' | 'is_archive').
	 * @param int    $post_id   Post ID (0 matches "all {post type}" rules).
	 * @return array<int|string, mixed> Matching groups keyed by group ID.
	 * @since 1.10.0
	 */
	private function lookup( $post_type, $page_type, $post_id = 0 ): array {
		if ( ! class_exists( '\SureMembers\Inc\Restricted' ) ) {
			return [];
		}

		$result = \SureMembers\Inc\Restricted::by_access_groups(
			SUREMEMBERS_POST_TYPE,
			[
				'include'           => SUREMEMBERS_PLAN_INCLUDE,
				'exclusion'         => SUREMEMBERS_PLAN_EXCLUDE,
				'priority'          => SUREMEMBERS_PLAN_PRIORITY,
				'current_post_id'   => $post_id,
				'current_post_type' => $post_type,
				'current_page_type' => $page_type,
			]
		);

		return ! empty( $result[ SUREMEMBERS_POST_TYPE ] ) ? $result[ SUREMEMBERS_POST_TYPE ] : [];
	}

	/**
	 * Whether the resolved user is blocked by the given groups.
	 *
	 * Uses check_user_access_by_id(), which honours the explicit user ID. The post-oriented
	 * check_user_has_post_access() must NOT be used here: in SureMembers 3.x its user-ID argument
	 * is ignored and it evaluates the *current* user, so in a background context (email batch, cron)
	 * with no current user it blocks everyone — including legitimate members.
	 *
	 * @param array<int|string, mixed> $groups Access groups keyed by group ID.
	 * @return bool True when a group exists and the user has no access to any of them.
	 * @since 1.10.0
	 */
	private function user_blocked_by( array $groups ): bool {
		if ( empty( $groups ) || $this->is_admin() ) {
			return false;
		}
		$user_id = $this->resolve_user();
		if ( $user_id <= 0 ) {
			return true;
		}
		if ( ! is_callable( [ '\SureMembers\Inc\Access_Groups', 'check_user_access_by_id' ] ) ) {
			return true;
		}

		// Drop groups whose access has time-expired for this user. check_user_access_by_id() only
		// tests the stored 'active' status, which SureMembers revokes lazily on the affected
		// member's own visit — so a time-expired member (status still 'active', revoke not yet run)
		// would otherwise keep access here and in every front-end listing that shares this method.
		$group_ids = array_keys( $groups );
		if ( is_callable( [ '\SureMembers\Inc\Access_Groups', 'is_expired' ] ) ) {
			$group_ids = array_values(
				array_filter(
					$group_ids,
					static fn( $group_id ) => ! \SureMembers\Inc\Access_Groups::is_expired( (int) $group_id, $user_id ) // @phpstan-ignore class.notFound (SureMembers external plugin)
				)
			);
		}
		if ( empty( $group_ids ) ) {
			return true; // Every protecting group has expired for this user.
		}

		return ! \SureMembers\Inc\Access_Groups::check_user_access_by_id( // @phpstan-ignore class.notFound (SureMembers external plugin)
			$user_id,
			$group_ids
		);
	}

	// === Exceptions & children ===

	/**
	 * Post IDs on the exclude (exception) list of ONE group.
	 *
	 * @param int $group_id Access group ID.
	 * @return array<int, int>
	 * @since 1.10.0
	 */
	private function group_exception_ids( int $group_id ): array {
		$key = 'gexc_' . $group_id;
		if ( isset( $this->cache[ $key ] ) ) {
			return $this->cache[ $key ];
		}

		$ids     = [];
		$exclude = get_post_meta( $group_id, SUREMEMBERS_PLAN_EXCLUDE, true );
		$rules   = is_array( $exclude ) && ! empty( $exclude['rules'] ) && is_array( $exclude['rules'] ) ? $exclude['rules'] : [];
		foreach ( $rules as $rule ) {
			if ( is_string( $rule ) && preg_match( self::SM_EXCLUDE_RULE_PATTERN, $rule, $matches ) ) {
				$ids[] = (int) $matches[1];
			}
		}

		$this->cache[ $key ] = array_values( array_unique( $ids ) );
		return $this->cache[ $key ];
	}

	/**
	 * Post IDs excluded by ANY of the given groups (union). Used for doorway detection only — the
	 * authoritative per-item decision is in get_item_state(), which requires exclusion by ALL
	 * blocking groups.
	 *
	 * @param array<int|string, mixed> $groups Access groups.
	 * @return array<int, int>
	 * @since 1.10.0
	 */
	private function exception_ids( array $groups ): array {
		$ids = [];
		foreach ( array_keys( $groups ) as $group_id ) {
			$ids = array_merge( $ids, $this->group_exception_ids( (int) $group_id ) );
		}
		return array_values( array_unique( $ids ) );
	}

	/**
	 * Whether a blocked space holds at least one freed child (so it should be a doorway).
	 *
	 * @param int                  $space_id Space (portal) post ID.
	 * @param array<string, mixed> $config   Registry entry for the space.
	 * @return bool
	 * @since 1.10.0
	 */
	private function space_has_freed_child( $space_id, array $config ): bool {
		// Collections own no content; a referenced space that is still visible acts as the freed child.
		if ( ( $config['mode'] ?? '' ) === 'aggregate' ) {
			foreach ( $this->referenced_spaces( $space_id ) as $referenced_id ) {
				if ( in_array( $this->get_space_state( $referenced_id ), [ 'normal', 'show' ], true ) ) {
					return true;
				}
			}
			return false;
		}

		$groups = $this->blocking_groups( $space_id, (string) ( $config['child_post_type'] ?? '' ) );
		// exception_ids() is the union (doorway candidates); confirm each is genuinely freed via
		// get_item_state() (excluded by ALL blocking groups), so a doorway only opens for a child
		// that is actually accessible.
		foreach ( $this->exception_ids( $groups ) as $item_id ) {
			if ( $this->child_belongs_to( $item_id, $space_id, $config )
				&& $this->get_item_state( $item_id, $space_id ) === 'show' ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Resolve a child item's parent space ID via its registry linkage.
	 *
	 * @param int $item_id Child post ID.
	 * @return int
	 * @since 1.10.0
	 */
	private function space_of( $item_id ): int {
		$config = $this->config_for_item( $item_id );
		if ( empty( $config ) ) {
			return 0;
		}
		if ( ! empty( $config['link_callback'] ) && is_callable( $config['link_callback'] ) ) {
			return (int) call_user_func( $config['link_callback'], $item_id );
		}
		return (int) sd_get_post_meta( $item_id, (string) ( $config['link_meta'] ?? '' ), true );
	}

	/**
	 * Whether a child item belongs to a given space, per the registry linkage.
	 *
	 * @param int                  $item_id  Child post ID.
	 * @param int                  $space_id Space (portal) post ID.
	 * @param array<string, mixed> $config   Registry entry for the space.
	 * @return bool
	 * @since 1.10.0
	 */
	private function child_belongs_to( $item_id, $space_id, array $config ): bool {
		if ( get_post_type( $item_id ) !== ( $config['child_post_type'] ?? '' ) ) {
			return false;
		}
		if ( ! empty( $config['link_callback'] ) && is_callable( $config['link_callback'] ) ) {
			return (int) call_user_func( $config['link_callback'], $item_id ) === (int) $space_id;
		}
		return (int) sd_get_post_meta( $item_id, (string) ( $config['link_meta'] ?? '' ), true ) === (int) $space_id;
	}

	/**
	 * Spaces referenced by a collection space.
	 *
	 * @param int $space_id Collection space ID.
	 * @return array<int, int>
	 * @since 1.10.0
	 */
	private function referenced_spaces( $space_id ): array {
		$raw = sd_get_post_meta( $space_id, 'collection_space_ids', true );
		$ids = is_array( $raw ) ? $raw : ( $raw !== '' ? explode( ',', (string) $raw ) : [] );
		return array_values( array_filter( array_map( 'absint', $ids ) ) );
	}

	// === Collection cascade ===

	/**
	 * Whether a space is locked because every collection listing it is restricted.
	 *
	 * Cascade carve-outs (the space stays visible) — handled by the callers and here:
	 *   - it is reachable through at least one collection that is NOT restricted,
	 *   - it is individually excepted on a restricting collection's exclude list,
	 *   - it has its own (satisfied) access rule — callers only ask when the space has none.
	 *
	 * @param int $space_id Referenced space (portal) post ID.
	 * @return bool
	 * @since 1.10.0
	 */
	private function cascade_restricted( $space_id ): bool {
		$index  = $this->collection_index();
		$owners = $index['by_space'][ (int) $space_id ] ?? [];
		if ( empty( $owners ) ) {
			return false;
		}

		$groups = [];
		foreach ( $owners as $collection_id ) {
			if ( empty( $index['blocked'][ $collection_id ] ) ) {
				return false; // reachable via a collection that is not restricted.
			}
			$groups += $index['groups'][ $collection_id ];
		}

		// Every owning collection is restricted — locked unless this space is excepted there.
		return ! in_array( (int) $space_id, $this->exception_ids( $groups ), true );
	}

	/**
	 * Build, once per request, the collection lookup used by the cascade.
	 *
	 * Returns:
	 *   - by_space[ space_id ] => [ collection_id, ... ]
	 *   - blocked[ collection_id ] => bool (collection container restricted for this user)
	 *   - groups[ collection_id ]  => the collection's blocking groups
	 *
	 * @return array{by_space: array<int, array<int, int>>, blocked: array<int, bool>, groups: array<int, array<int|string, mixed>>}
	 * @since 1.10.0
	 */
	private function collection_index(): array {
		if ( isset( $this->cache['collection_index'] ) ) {
			return $this->cache['collection_index'];
		}

		$index = [
			'by_space' => [],
			'blocked'  => [],
			'groups'   => [],
		];

		$collection_ids = get_posts(
			[
				'post_type'   => SUREDASHBOARD_POST_TYPE,
				'post_status' => 'publish',
				'numberposts' => -1,
				'fields'      => 'ids',
				'meta_key'    => 'integration', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
				'meta_value'  => 'collection',  // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value
			]
		);

		foreach ( $collection_ids as $collection_id ) {
			$collection_id                      = (int) $collection_id;
			$groups                             = $this->blocking_groups( $collection_id, '' );
			$index['groups'][ $collection_id ]  = $groups;
			$index['blocked'][ $collection_id ] = $this->user_blocked_by( $groups );
			foreach ( $this->referenced_spaces( $collection_id ) as $referenced_id ) {
				$index['by_space'][ $referenced_id ][] = $collection_id;
			}
		}

		$this->cache['collection_index'] = $index;
		return $index;
	}

	/**
	 * Whether a post is locked only because a restricted collection cascades down to it.
	 *
	 * Resolves the post to its space (the post itself for a space, the parent for a child item)
	 * and applies the same gate as get_space_state(): cascade matters only when the space has no
	 * rule of its own.
	 *
	 * @param int $post_id Post ID (a space, or a child item).
	 * @return bool
	 * @since 1.10.0
	 */
	private function is_cascade_locked_post( $post_id ): bool {
		if ( $post_id <= 0 || $this->is_admin() ) {
			return false;
		}

		$space_id = get_post_type( $post_id ) === SUREDASHBOARD_POST_TYPE ? (int) $post_id : $this->space_of( $post_id );
		if ( $space_id <= 0 ) {
			return false;
		}

		$config = $this->config_for_space( $space_id );
		if ( ! empty( $this->blocking_groups( $space_id, (string) ( $config['child_post_type'] ?? '' ) ) ) ) {
			return false; // The space has its own rule — SureMembers already governs it.
		}

		return $this->cascade_restricted( $space_id );
	}

	/**
	 * The user the engine evaluates as: the acting user when set (background context), otherwise
	 * the logged-in user. Single funnel so every restriction check honours as_user().
	 *
	 * @return int
	 * @since 1.10.0
	 */
	private function resolve_user(): int {
		return $this->acting_user === null ? get_current_user_id() : $this->acting_user;
	}

	/**
	 * Whether the resolved user is a portal admin (always bypasses restrictions).
	 *
	 * @return bool
	 * @since 1.10.0
	 */
	private function is_admin(): bool {
		$user_id = $this->resolve_user();
		return $user_id > 0 && user_can( $user_id, 'manage_options' );
	}
}

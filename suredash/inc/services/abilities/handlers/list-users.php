<?php
/**
 * List Users Ability.
 *
 * @package SureDash
 * @since 1.6.3
 */

namespace SureDashboard\Inc\Services\Abilities\Handlers;

use SureDashboard\Core\Routers\Backend as BackendRoute;
use SureDashboard\Inc\Services\Abilities\Ability;

defined( 'ABSPATH' ) || exit;

/**
 * List_Users class.
 *
 * @since 1.6.3
 */
class List_Users extends Ability {
	/**
	 * Get the unique identifier for this ability.
	 *
	 * @since 1.6.3
	 *
	 * @return string
	 */
	public function get_id(): string {
		return 'list-users';
	}

	/**
	 * Get the human-readable name.
	 *
	 * @since 1.6.3
	 *
	 * @return string
	 */
	public function get_name(): string {
		return __( 'List Users', 'suredash' );
	}

	/**
	 * Get the ability description.
	 *
	 * @since 1.6.3
	 *
	 * @return string
	 */
	public function get_description(): string {
		return __( 'Lists portal users with pagination, search, sorting, and role filtering. Returns user names, emails, roles, and registration dates.', 'suredash' );
	}

	/**
	 * Get the ability category.
	 *
	 * @since 1.6.3
	 *
	 * @return string
	 */
	public function get_category(): string {
		return 'users';
	}

	/**
	 * {@inheritDoc}
	 */
	public function get_parameters(): array {
		return [
			'per_page' => [
				'type'        => 'integer',
				'required'    => false,
				'default'     => 10,
				'description' => __( 'Number of users per page.', 'suredash' ),
			],
			'page'     => [
				'type'        => 'integer',
				'required'    => false,
				'default'     => 1,
				'description' => __( 'Page number for pagination.', 'suredash' ),
			],
			'search'   => [
				'type'        => 'string',
				'required'    => false,
				'default'     => '',
				'description' => __( 'Search query to filter users by name or email.', 'suredash' ),
			],
			'order_by' => [
				'type'        => 'string',
				'required'    => false,
				'default'     => 'display_name',
				'description' => __( 'Field to sort by.', 'suredash' ),
				'enum'        => [ 'display_name', 'user_email', 'user_registered', 'ID' ],
			],
			'order'    => [
				'type'        => 'string',
				'required'    => false,
				'default'     => 'ASC',
				'description' => __( 'Sort direction.', 'suredash' ),
				'enum'        => [ 'ASC', 'DESC' ],
			],
			'roles'    => [
				'type'        => 'string',
				'required'    => false,
				'default'     => 'suredash_user',
				'description' => __( 'Comma-separated role slugs to filter by.', 'suredash' ),
			],
		];
	}

	/**
	 * {@inheritDoc}
	 */
	public function get_returns(): array {
		return [
			'type'       => 'object',
			'properties' => [
				'users'       => [
					'type'        => 'array',
					'description' => __( 'Array of user objects.', 'suredash' ),
				],
				'total'       => [
					'type'        => 'integer',
					'description' => __( 'Total number of matching users.', 'suredash' ),
				],
				'total_pages' => [
					'type'        => 'integer',
					'description' => __( 'Total number of pages.', 'suredash' ),
				],
			],
		];
	}

	/**
	 * {@inheritDoc}
	 */
	public function get_annotations(): array {
		return [
			'readOnlyHint'    => true,
			'destructiveHint' => false,
			'idempotentHint'  => true,
		];
	}

	/**
	 * Get usage instructions for AI agents.
	 *
	 * @since 1.6.3
	 *
	 * @return string
	 */
	public function get_instructions(): string {
		return 'Paginated user list. Default role filter is suredash_user. Use user IDs with get-user for full profile.';
	}

	/**
	 * {@inheritDoc}
	 *
	 * @param array<string, mixed> $params Validated parameters.
	 */
	public function execute( array $params ): array {
		$request = $this->build_request( [], 'GET' );

		$request->set_param( 'per_page', $params['per_page'] ?? 10 );
		$request->set_param( 'page', $params['page'] ?? 1 );
		$request->set_param( 'search', $params['search'] ?? '' );
		$request->set_param( 'order_by', $params['order_by'] ?? 'display_name' );
		$request->set_param( 'order', $params['order'] ?? 'ASC' );
		$request->set_param( 'roles', $params['roles'] ?? 'suredash_user' );

		// get_users returns WP_REST_Response directly.
		$response = BackendRoute::get_instance()->get_users( $request );

		if ( $response instanceof \WP_REST_Response ) {
			$response_data = $response->get_data()['data'] ?? $response->get_data();
		} else {
			$response_data = $response;
		}

		if ( isset( $response_data['users'] ) && is_array( $response_data['users'] ) ) {
			$response_data['users'] = array_map(
				static function ( $user ) {
					unset( $user['avatar_markup'], $user['banner_placeholder'] );
					return $user;
				},
				$response_data['users']
			);
		}

		return [
			'success' => true,
			'data'    => $response_data,
		];
	}
}

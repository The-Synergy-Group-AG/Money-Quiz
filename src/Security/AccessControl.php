<?php
/**
 * Access Control
 *
 * Manages role-based access control for the Money Quiz plugin.
 *
 * @package MoneyQuiz\Security
 * @since   7.0.0
 */

namespace MoneyQuiz\Security;

// Security: Prevent direct access.
defined( 'ABSPATH' ) || exit;

/**
 * Access control class.
 *
 * @since 7.0.0
 */
class AccessControl {

	/**
	 * Plugin capabilities.
	 *
	 * @var array
	 */
	private array $capabilities = [
		'manage_money_quiz'          => [ 'administrator' ],
		'edit_money_quiz'            => [ 'administrator', 'editor' ],
		'delete_money_quiz'          => [ 'administrator' ],
		'publish_money_quiz'         => [ 'administrator', 'editor' ],
		'view_money_quiz_analytics'  => [ 'administrator', 'editor' ],
		'export_money_quiz_data'     => [ 'administrator' ],
		'manage_money_quiz_settings' => [ 'administrator' ],
	];

	/**
	 * Check if current user has capability.
	 *
	 * @since 7.0.0
	 *
	 * @param string $capability Capability to check.
	 * @param mixed  $object_id  Optional object ID for context.
	 * @return bool True if user has capability.
	 */
	public function can( string $capability, $object_id = null ): bool {
		if ( ! is_user_logged_in() ) {
			return false;
		}

		// Check if user has the specific capability.
		if ( current_user_can( $capability, $object_id ) ) {
			return true;
		}

		// Check if user has any of the roles with this capability.
		$user = wp_get_current_user();
		if ( isset( $this->capabilities[ $capability ] ) ) {
			foreach ( $this->capabilities[ $capability ] as $role ) {
				if ( in_array( $role, $user->roles, true ) ) {
					return true;
				}
			}
		}

		return false;
	}

	/**
	 * Require capability or die.
	 *
	 * @since 7.0.0
	 *
	 * @param string $capability Capability to require.
	 * @param mixed  $object_id  Optional object ID for context.
	 * @return void
	 */
	public function require( string $capability, $object_id = null ): void {
		if ( ! $this->can( $capability, $object_id ) ) {
			wp_die(
				esc_html__( 'Sorry, you are not allowed to access this page.', 'money-quiz' ),
				403
			);
		}
	}

	/**
	 * Check if user can manage plugin.
	 *
	 * @since 7.0.0
	 *
	 * @return bool True if user can manage.
	 */
	public function can_manage(): bool {
		return $this->can( 'manage_money_quiz' );
	}

	/**
	 * Check if user can edit quizzes.
	 *
	 * @since 7.0.0
	 *
	 * @param int|null $quiz_id Quiz ID.
	 * @return bool True if user can edit.
	 */
	public function can_edit( ?int $quiz_id = null ): bool {
		return $this->can( 'edit_money_quiz', $quiz_id );
	}

	/**
	 * Check if user can delete quizzes.
	 *
	 * @since 7.0.0
	 *
	 * @param int|null $quiz_id Quiz ID.
	 * @return bool True if user can delete.
	 */
	public function can_delete( ?int $quiz_id = null ): bool {
		return $this->can( 'delete_money_quiz', $quiz_id );
	}

	/**
	 * Check if user can publish quizzes.
	 *
	 * @since 7.0.0
	 *
	 * @param int|null $quiz_id Quiz ID.
	 * @return bool True if user can publish.
	 */
	public function can_publish( ?int $quiz_id = null ): bool {
		return $this->can( 'publish_money_quiz', $quiz_id );
	}

	/**
	 * Check if user can view analytics.
	 *
	 * @since 7.0.0
	 *
	 * @return bool True if user can view.
	 */
	public function can_view_analytics(): bool {
		return $this->can( 'view_money_quiz_analytics' );
	}

	/**
	 * Check if user can export data.
	 *
	 * @since 7.0.0
	 *
	 * @return bool True if user can export.
	 */
	public function can_export(): bool {
		return $this->can( 'export_money_quiz_data' );
	}

	/**
	 * Add plugin capabilities to roles.
	 *
	 * @since 7.0.0
	 *
	 * @return void
	 */
	public function add_capabilities(): void {
		foreach ( $this->capabilities as $capability => $roles ) {
			foreach ( $roles as $role_name ) {
				$role = get_role( $role_name );
				if ( $role && ! $role->has_cap( $capability ) ) {
					$role->add_cap( $capability );
				}
			}
		}
	}

	/**
	 * Remove plugin capabilities from roles.
	 *
	 * @since 7.0.0
	 *
	 * @return void
	 */
	public function remove_capabilities(): void {
		foreach ( $this->capabilities as $capability => $roles ) {
			foreach ( $roles as $role_name ) {
				$role = get_role( $role_name );
				if ( $role && $role->has_cap( $capability ) ) {
					$role->remove_cap( $capability );
				}
			}
		}
	}

	/**
	 * Filter content based on user permissions.
	 *
	 * @since 7.0.0
	 *
	 * @param mixed  $content    Content to filter.
	 * @param string $capability Required capability.
	 * @param mixed  $default    Default value if no permission.
	 * @return mixed Filtered content.
	 */
	public function filter_content( $content, string $capability, $default = null ) {
		return $this->can( $capability ) ? $content : $default;
	}

	/**
	 * Get user's highest role.
	 *
	 * @since 7.0.0
	 *
	 * @param int|null $user_id User ID (null for current user).
	 * @return string|null Highest role name.
	 */
	public function get_user_role( ?int $user_id = null ): ?string {
		$user = $user_id ? get_user_by( 'id', $user_id ) : wp_get_current_user();
		
		if ( ! $user || ! $user->exists() ) {
			return null;
		}

		// Priority order for roles.
		$role_priority = [
			'administrator',
			'editor',
			'author',
			'contributor',
			'subscriber',
		];

		foreach ( $role_priority as $role ) {
			if ( in_array( $role, $user->roles, true ) ) {
				return $role;
			}
		}

		// Return first role if none match priority.
		return ! empty( $user->roles ) ? $user->roles[0] : null;
	}

	/**
	 * Check if user owns a quiz.
	 *
	 * @since 7.0.0
	 *
	 * @param int      $quiz_id Quiz ID.
	 * @param int|null $user_id User ID (null for current user).
	 * @return bool True if user owns quiz.
	 */
	public function user_owns_quiz( int $quiz_id, ?int $user_id = null ): bool {
		$user_id = $user_id ?? get_current_user_id();
		
		if ( ! $user_id ) {
			return false;
		}

		// Administrator can access all quizzes.
		if ( user_can( $user_id, 'manage_money_quiz' ) ) {
			return true;
		}

		// Check quiz ownership (to be implemented with quiz system).
		// For now, return false as quiz system isn't implemented yet.
		return false;
	}

	/**
	 * Validate nonce with capability check.
	 *
	 * @since 7.0.0
	 *
	 * @param string $nonce      Nonce value.
	 * @param string $action     Nonce action.
	 * @param string $capability Required capability.
	 * @return bool True if valid and authorized.
	 */
	public function verify_nonce_with_cap( string $nonce, string $action, string $capability ): bool {
		// First check capability.
		if ( ! $this->can( $capability ) ) {
			return false;
		}

		// Then verify nonce.
		return wp_verify_nonce( $nonce, $action ) !== false;
	}

	/**
	 * Get allowed actions for current user.
	 *
	 * @since 7.0.0
	 *
	 * @param int|null $quiz_id Optional quiz ID for context.
	 * @return array List of allowed actions.
	 */
	public function get_allowed_actions( ?int $quiz_id = null ): array {
		$actions = [];

		if ( $this->can_manage() ) {
			$actions[] = 'manage';
			$actions[] = 'settings';
		}

		if ( $this->can_edit( $quiz_id ) ) {
			$actions[] = 'edit';
		}

		if ( $this->can_delete( $quiz_id ) ) {
			$actions[] = 'delete';
		}

		if ( $this->can_publish( $quiz_id ) ) {
			$actions[] = 'publish';
		}

		if ( $this->can_view_analytics() ) {
			$actions[] = 'analytics';
		}

		if ( $this->can_export() ) {
			$actions[] = 'export';
		}

		return $actions;
	}
}
<?php
/**
 * Authorization Interface
 *
 * Defines the contract for authorization services.
 *
 * @package MoneyQuiz\Security\Contracts
 * @since   7.0.0
 */

namespace MoneyQuiz\Security\Contracts;

// Security: Prevent direct access.
defined('ABSPATH') || exit;

/**
 * Authorization interface.
 *
 * This interface enables different authorization strategies
 * (RBAC, ABAC, etc.) while maintaining consistent behavior.
 *
 * @since 7.0.0
 */
interface AuthorizationInterface {
    
    /**
     * Check if user can perform action.
     *
     * @param int    $user_id     User ID.
     * @param string $permission  Permission name.
     * @param mixed  $resource_id Resource ID (optional).
     * @return bool True if authorized.
     */
    public function can(int $user_id, string $permission, $resource_id = null): bool;
    
    /**
     * Register custom policy.
     *
     * @param string   $permission Permission name.
     * @param callable $callback   Policy callback.
     * @return void
     */
    public function register_policy(string $permission, callable $callback): void;
    
    /**
     * Add permission definition.
     *
     * @param string $permission Permission name.
     * @param array  $roles      Allowed roles.
     * @return void
     */
    public function add_permission(string $permission, array $roles): void;
    
    /**
     * Get user permissions.
     *
     * @param int $user_id User ID.
     * @return array List of permissions.
     */
    public function get_user_permissions(int $user_id): array;
}
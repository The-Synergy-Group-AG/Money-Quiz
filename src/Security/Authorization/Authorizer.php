<?php
/**
 * Authorization Engine
 *
 * Handles permission checks and access control.
 *
 * @package MoneyQuiz\Security\Authorization
 * @since   7.0.0
 */

namespace MoneyQuiz\Security\Authorization;

use MoneyQuiz\Core\Logging\Logger;
use MoneyQuiz\Security\Contracts\AuthorizationInterface;

// Security: Prevent direct access.
defined('ABSPATH') || exit;

/**
 * Authorizer class.
 *
 * @since 7.0.0
 */
class Authorizer implements AuthorizationInterface {
    
    /**
     * Logger instance.
     *
     * @var Logger
     */
    private Logger $logger;
    
    /**
     * Permission definitions.
     *
     * @var array
     */
    private array $permissions = [
        // Quiz permissions
        'create_quiz' => ['administrator', 'money_quiz_manager'],
        'edit_quiz' => ['administrator', 'money_quiz_manager'],
        'delete_quiz' => ['administrator'],
        'view_quiz' => ['administrator', 'money_quiz_manager', 'money_quiz_viewer'],
        
        // Result permissions
        'view_results' => ['administrator', 'money_quiz_manager'],
        'export_results' => ['administrator', 'money_quiz_manager'],
        'delete_results' => ['administrator'],
        
        // Settings permissions
        'manage_settings' => ['administrator'],
        'view_settings' => ['administrator', 'money_quiz_manager'],
        
        // User permissions
        'manage_users' => ['administrator'],
        'view_analytics' => ['administrator', 'money_quiz_manager']
    ];
    
    /**
     * Custom policies.
     *
     * @var array<callable>
     */
    private array $policies = [];
    
    /**
     * Constructor.
     *
     * @param Logger $logger Logger instance.
     */
    public function __construct(Logger $logger) {
        $this->logger = $logger;
        $this->register_default_policies();
    }
    
    /**
     * Check if user can perform action.
     *
     * This method implements a three-tier authorization system:
     * 1. Super admins bypass all checks (WordPress multisite)
     * 2. Custom policies for complex business logic
     * 3. Role-based permissions for standard access control
     * 4. Falls back to WordPress capabilities if no match
     *
     * @since 7.0.0
     *
     * @param int    $user_id     The WordPress user ID to check permissions for.
     * @param string $permission  The permission name (e.g., 'edit_quiz', 'view_results').
     * @param mixed  $resource_id Optional resource ID for resource-based permissions.
     *                           Used by policies like 'edit_own_quiz' to check ownership.
     * 
     * @return bool True if the user has the requested permission, false otherwise.
     * 
     * @example
     * ```php
     * // Check if user can edit any quiz
     * $can_edit = $authorizer->can($user_id, 'edit_quiz');
     * 
     * // Check if user can edit specific quiz (ownership check)
     * $can_edit_own = $authorizer->can($user_id, 'edit_own_quiz', $quiz_id);
     * ```
     */
    public function can(int $user_id, string $permission, $resource_id = null): bool {
        // Super admin always has access
        if (is_super_admin($user_id)) {
            return true;
        }
        
        // Check custom policy first
        // Policies allow complex business logic beyond simple role checks
        // Example: "Users can only edit their own quizzes"
        if (isset($this->policies[$permission])) {
            $result = call_user_func($this->policies[$permission], $user_id, $resource_id);
            
            $this->log_authorization($user_id, $permission, $resource_id, $result);
            return $result;
        }
        
        // Check role-based permissions
        // This uses the $permissions array to map permissions to WordPress roles
        // Example: 'edit_quiz' => ['administrator', 'money_quiz_manager']
        if (isset($this->permissions[$permission])) {
            $user = get_userdata($user_id);
            if (!$user) {
                return false;
            }
            
            // Check if user has any of the allowed roles
            // array_intersect returns common values between user roles and allowed roles
            $allowed_roles = $this->permissions[$permission];
            $has_permission = !empty(array_intersect($allowed_roles, $user->roles));
            
            $this->log_authorization($user_id, $permission, $resource_id, $has_permission);
            return $has_permission;
        }
        
        // Check WordPress capabilities
        $result = user_can($user_id, $permission);
        
        $this->log_authorization($user_id, $permission, $resource_id, $result);
        return $result;
    }
    
    /**
     * Register custom policy.
     *
     * Policies allow complex authorization logic beyond simple role checks.
     * The callback receives the user ID and optional resource ID, and must
     * return a boolean indicating whether access is granted.
     *
     * @since 7.0.0
     *
     * @param string   $permission The unique permission identifier.
     * @param callable $callback   The policy callback function.
     *                            Signature: function(int $user_id, mixed $resource_id): bool
     * 
     * @return void
     * 
     * @example
     * ```php
     * // Register policy that checks quiz ownership
     * $authorizer->register_policy('edit_own_quiz', function($user_id, $quiz_id) {
     *     $quiz = get_quiz($quiz_id);
     *     return $quiz && $quiz->author_id === $user_id;
     * });
     * ```
     */
    public function register_policy(string $permission, callable $callback): void {
        $this->policies[$permission] = $callback;
    }
    
    /**
     * Add permission definition.
     *
     * @param string $permission Permission name.
     * @param array  $roles      Allowed roles.
     */
    public function add_permission(string $permission, array $roles): void {
        $this->permissions[$permission] = $roles;
    }
    
    /**
     * Register default policies.
     *
     * Sets up the default authorization policies for Money Quiz:
     * - edit_own_quiz: Users can edit their own quizzes
     * - view_own_results: Users can view their own quiz results
     * 
     * These policies implement resource-based access control where
     * permissions depend on resource ownership.
     *
     * @since 7.0.0
     * @access private
     * 
     * @return void
     */
    private function register_default_policies(): void {
        // Policy: Users can only edit their own quizzes
        // This implements ownership-based access control
        $this->register_policy('edit_own_quiz', function($user_id, $quiz_id) {
            if (!$quiz_id) {
                return false; // Cannot edit without specifying a quiz
            }
            
            // Query database to check quiz ownership
            // Using prepared statement to prevent SQL injection
            global $wpdb;
            $author_id = $wpdb->get_var($wpdb->prepare(
                "SELECT author_id FROM {$wpdb->prefix}money_quiz_quizzes WHERE id = %d",
                $quiz_id
            ));
            
            // Loose comparison handles string/int type differences
            return $author_id == $user_id;
        });
        
        // Policy: Users can view their own results
        $this->register_policy('view_own_results', function($user_id, $result_id) {
            if (!$result_id) {
                return true; // Can view own results list
            }
            
            global $wpdb;
            $result_user_id = $wpdb->get_var($wpdb->prepare(
                "SELECT user_id FROM {$wpdb->prefix}money_quiz_results WHERE id = %d",
                $result_id
            ));
            
            return $result_user_id == $user_id;
        });
    }
    
    /**
     * Log authorization attempt.
     *
     * @param int    $user_id     User ID.
     * @param string $permission  Permission.
     * @param mixed  $resource_id Resource ID.
     * @param bool   $result      Authorization result.
     */
    private function log_authorization(int $user_id, string $permission, $resource_id, bool $result): void {
        $level = $result ? 'debug' : 'info';
        
        $this->logger->log($level, 'Authorization check', [
            'user_id' => $user_id,
            'permission' => $permission,
            'resource_id' => $resource_id,
            'result' => $result ? 'granted' : 'denied'
        ]);
    }
    
    /**
     * Get user permissions.
     *
     * Returns a complete list of permissions granted to the user,
     * including both Money Quiz specific permissions and WordPress
     * capabilities. Useful for frontend permission checks and
     * building user interfaces based on available actions.
     *
     * @since 7.0.0
     *
     * @param int $user_id The WordPress user ID.
     * 
     * @return array Indexed array of permission strings the user has.
     *               Empty array if user not found.
     * 
     * @example
     * ```php
     * $permissions = $authorizer->get_user_permissions($user_id);
     * if (in_array('edit_quiz', $permissions)) {
     *     // Show edit button
     * }
     * ```
     */
    public function get_user_permissions(int $user_id): array {
        $user = get_userdata($user_id);
        if (!$user) {
            return [];
        }
        
        $user_permissions = [];
        
        // Check each permission
        foreach ($this->permissions as $permission => $roles) {
            if (!empty(array_intersect($roles, $user->roles))) {
                $user_permissions[] = $permission;
            }
        }
        
        // Add WordPress capabilities
        foreach ($user->allcaps as $cap => $granted) {
            if ($granted && !in_array($cap, $user_permissions)) {
                $user_permissions[] = $cap;
            }
        }
        
        return $user_permissions;
    }
}
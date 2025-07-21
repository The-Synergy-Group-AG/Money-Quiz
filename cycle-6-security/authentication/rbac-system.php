<?php
/**
 * Role-Based Access Control System
 * 
 * Implements granular permissions and role management
 * 
 * @package MoneyQuiz\Security\Authentication
 * @version 1.0.0
 */

namespace MoneyQuiz\Security\Authentication;

use Exception;

class RBACSystem {
    private $db;
    private $roles = [];
    private $permissions = [];
    private $cache_group = 'money_quiz_rbac';
    private $cache_ttl = 3600; // 1 hour
    
    public function __construct() {
        $this->db = $GLOBALS['wpdb'];
        $this->init_default_roles();
        $this->init_permissions();
    }
    
    /**
     * Initialize default roles
     */
    private function init_default_roles() {
        $this->roles = [
            'quiz_admin' => [
                'name' => 'Quiz Administrator',
                'capabilities' => [
                    'manage_quizzes',
                    'create_quiz',
                    'edit_any_quiz',
                    'delete_any_quiz',
                    'view_all_results',
                    'export_results',
                    'manage_users',
                    'manage_settings',
                    'view_analytics',
                    'manage_security'
                ]
            ],
            'quiz_creator' => [
                'name' => 'Quiz Creator',
                'capabilities' => [
                    'create_quiz',
                    'edit_own_quiz',
                    'delete_own_quiz',
                    'view_own_results',
                    'duplicate_quiz',
                    'preview_quiz'
                ]
            ],
            'quiz_moderator' => [
                'name' => 'Quiz Moderator',
                'capabilities' => [
                    'edit_any_quiz',
                    'moderate_results',
                    'view_all_results',
                    'export_limited_results',
                    'manage_comments',
                    'flag_content'
                ]
            ],
            'quiz_taker' => [
                'name' => 'Quiz Taker',
                'capabilities' => [
                    'take_quiz',
                    'view_own_results',
                    'save_progress',
                    'share_results',
                    'rate_quiz'
                ]
            ],
            'guest' => [
                'name' => 'Guest',
                'capabilities' => [
                    'view_public_quizzes',
                    'take_public_quiz'
                ]
            ]
        ];
    }
    
    /**
     * Initialize permission definitions
     */
    private function init_permissions() {
        $this->permissions = [
            // Quiz Management
            'manage_quizzes' => [
                'description' => 'Full control over all quizzes',
                'resource' => 'quiz',
                'actions' => ['create', 'read', 'update', 'delete']
            ],
            'create_quiz' => [
                'description' => 'Create new quizzes',
                'resource' => 'quiz',
                'actions' => ['create']
            ],
            'edit_own_quiz' => [
                'description' => 'Edit own quizzes',
                'resource' => 'quiz',
                'actions' => ['update'],
                'condition' => 'is_owner'
            ],
            'edit_any_quiz' => [
                'description' => 'Edit any quiz',
                'resource' => 'quiz',
                'actions' => ['update']
            ],
            'delete_own_quiz' => [
                'description' => 'Delete own quizzes',
                'resource' => 'quiz',
                'actions' => ['delete'],
                'condition' => 'is_owner'
            ],
            'delete_any_quiz' => [
                'description' => 'Delete any quiz',
                'resource' => 'quiz',
                'actions' => ['delete']
            ],
            
            // Results Management
            'view_own_results' => [
                'description' => 'View own quiz results',
                'resource' => 'result',
                'actions' => ['read'],
                'condition' => 'is_owner'
            ],
            'view_all_results' => [
                'description' => 'View all quiz results',
                'resource' => 'result',
                'actions' => ['read']
            ],
            'export_results' => [
                'description' => 'Export quiz results',
                'resource' => 'result',
                'actions' => ['export']
            ],
            
            // User Management
            'manage_users' => [
                'description' => 'Manage user accounts and roles',
                'resource' => 'user',
                'actions' => ['create', 'read', 'update', 'delete']
            ],
            
            // Settings
            'manage_settings' => [
                'description' => 'Manage system settings',
                'resource' => 'settings',
                'actions' => ['read', 'update']
            ],
            
            // Analytics
            'view_analytics' => [
                'description' => 'View system analytics',
                'resource' => 'analytics',
                'actions' => ['read']
            ],
            
            // Security
            'manage_security' => [
                'description' => 'Manage security settings',
                'resource' => 'security',
                'actions' => ['read', 'update']
            ]
        ];
    }
    
    /**
     * Check if user has permission
     */
    public function user_can($user_id, $capability, $resource_id = null) {
        // Check cache first
        $cache_key = $this->get_cache_key($user_id, $capability, $resource_id);
        $cached = wp_cache_get($cache_key, $this->cache_group);
        
        if ($cached !== false) {
            return $cached;
        }
        
        // Get user roles
        $user_roles = $this->get_user_roles($user_id);
        
        // Check each role
        foreach ($user_roles as $role) {
            if ($this->role_has_capability($role, $capability, $user_id, $resource_id)) {
                wp_cache_set($cache_key, true, $this->cache_group, $this->cache_ttl);
                return true;
            }
        }
        
        // Check custom permissions
        if ($this->has_custom_permission($user_id, $capability, $resource_id)) {
            wp_cache_set($cache_key, true, $this->cache_group, $this->cache_ttl);
            return true;
        }
        
        wp_cache_set($cache_key, false, $this->cache_group, $this->cache_ttl);
        return false;
    }
    
    /**
     * Check if role has capability
     */
    private function role_has_capability($role, $capability, $user_id = null, $resource_id = null) {
        if (!isset($this->roles[$role])) {
            return false;
        }
        
        if (!in_array($capability, $this->roles[$role]['capabilities'])) {
            return false;
        }
        
        // Check conditions if any
        if (isset($this->permissions[$capability]['condition'])) {
            return $this->check_condition(
                $this->permissions[$capability]['condition'],
                $user_id,
                $resource_id
            );
        }
        
        return true;
    }
    
    /**
     * Check permission conditions
     */
    private function check_condition($condition, $user_id, $resource_id) {
        switch ($condition) {
            case 'is_owner':
                return $this->is_resource_owner($user_id, $resource_id);
                
            case 'is_published':
                return $this->is_resource_published($resource_id);
                
            case 'is_active':
                return $this->is_user_active($user_id);
                
            default:
                // Custom condition callback
                if (is_callable($condition)) {
                    return call_user_func($condition, $user_id, $resource_id);
                }
                return false;
        }
    }
    
    /**
     * Check if user owns resource
     */
    private function is_resource_owner($user_id, $resource_id) {
        if (!$resource_id) {
            return false;
        }
        
        // Check quiz ownership
        $quiz = $this->db->get_row($this->db->prepare(
            "SELECT author_id FROM {$this->db->prefix}money_quiz_quizzes WHERE id = %d",
            $resource_id
        ));
        
        return $quiz && $quiz->author_id == $user_id;
    }
    
    /**
     * Check if resource is published
     */
    private function is_resource_published($resource_id) {
        if (!$resource_id) {
            return false;
        }
        
        $quiz = $this->db->get_row($this->db->prepare(
            "SELECT status FROM {$this->db->prefix}money_quiz_quizzes WHERE id = %d",
            $resource_id
        ));
        
        return $quiz && $quiz->status === 'published';
    }
    
    /**
     * Check if user is active
     */
    private function is_user_active($user_id) {
        $user = get_user_by('id', $user_id);
        return $user && !get_user_meta($user_id, '_account_disabled', true);
    }
    
    /**
     * Get user roles
     */
    public function get_user_roles($user_id) {
        $user = get_user_by('id', $user_id);
        
        if (!$user) {
            return ['guest'];
        }
        
        // Get WordPress roles
        $wp_roles = $user->roles;
        
        // Get custom Money Quiz roles
        $custom_roles = get_user_meta($user_id, '_money_quiz_roles', true) ?: [];
        
        // Map WordPress roles to Money Quiz roles
        $mapped_roles = $this->map_wp_roles($wp_roles);
        
        return array_unique(array_merge($mapped_roles, $custom_roles));
    }
    
    /**
     * Map WordPress roles to Money Quiz roles
     */
    private function map_wp_roles($wp_roles) {
        $role_map = [
            'administrator' => 'quiz_admin',
            'editor' => 'quiz_moderator',
            'author' => 'quiz_creator',
            'contributor' => 'quiz_creator',
            'subscriber' => 'quiz_taker'
        ];
        
        $mapped = [];
        foreach ($wp_roles as $wp_role) {
            if (isset($role_map[$wp_role])) {
                $mapped[] = $role_map[$wp_role];
            }
        }
        
        return $mapped;
    }
    
    /**
     * Assign role to user
     */
    public function assign_role($user_id, $role) {
        if (!isset($this->roles[$role])) {
            throw new Exception('Invalid role: ' . $role);
        }
        
        $current_roles = get_user_meta($user_id, '_money_quiz_roles', true) ?: [];
        
        if (!in_array($role, $current_roles)) {
            $current_roles[] = $role;
            update_user_meta($user_id, '_money_quiz_roles', $current_roles);
            
            // Clear cache
            $this->clear_user_cache($user_id);
            
            // Log role assignment
            $this->log_role_change($user_id, 'assign', $role);
        }
        
        return true;
    }
    
    /**
     * Remove role from user
     */
    public function remove_role($user_id, $role) {
        $current_roles = get_user_meta($user_id, '_money_quiz_roles', true) ?: [];
        
        if (($key = array_search($role, $current_roles)) !== false) {
            unset($current_roles[$key]);
            update_user_meta($user_id, '_money_quiz_roles', array_values($current_roles));
            
            // Clear cache
            $this->clear_user_cache($user_id);
            
            // Log role removal
            $this->log_role_change($user_id, 'remove', $role);
        }
        
        return true;
    }
    
    /**
     * Grant custom permission
     */
    public function grant_permission($user_id, $capability, $resource_id = null, $expiry = null) {
        $permission = [
            'capability' => $capability,
            'resource_id' => $resource_id,
            'granted_at' => current_time('mysql'),
            'granted_by' => get_current_user_id(),
            'expires_at' => $expiry
        ];
        
        // Store in database
        $this->db->insert(
            $this->db->prefix . 'money_quiz_permissions',
            [
                'user_id' => $user_id,
                'capability' => $capability,
                'resource_id' => $resource_id,
                'granted_at' => $permission['granted_at'],
                'granted_by' => $permission['granted_by'],
                'expires_at' => $expiry
            ]
        );
        
        // Clear cache
        $this->clear_user_cache($user_id);
        
        // Log permission grant
        $this->log_permission_change($user_id, 'grant', $capability, $resource_id);
        
        return true;
    }
    
    /**
     * Revoke custom permission
     */
    public function revoke_permission($user_id, $capability, $resource_id = null) {
        $where = [
            'user_id' => $user_id,
            'capability' => $capability
        ];
        
        if ($resource_id !== null) {
            $where['resource_id'] = $resource_id;
        }
        
        $this->db->delete(
            $this->db->prefix . 'money_quiz_permissions',
            $where
        );
        
        // Clear cache
        $this->clear_user_cache($user_id);
        
        // Log permission revoke
        $this->log_permission_change($user_id, 'revoke', $capability, $resource_id);
        
        return true;
    }
    
    /**
     * Check custom permissions
     */
    private function has_custom_permission($user_id, $capability, $resource_id = null) {
        $query = $this->db->prepare(
            "SELECT * FROM {$this->db->prefix}money_quiz_permissions 
            WHERE user_id = %d AND capability = %s",
            $user_id,
            $capability
        );
        
        if ($resource_id !== null) {
            $query .= $this->db->prepare(" AND (resource_id = %d OR resource_id IS NULL)", $resource_id);
        }
        
        $query .= " AND (expires_at IS NULL OR expires_at > NOW())";
        
        $permission = $this->db->get_row($query);
        
        return $permission !== null;
    }
    
    /**
     * Get cache key
     */
    private function get_cache_key($user_id, $capability, $resource_id = null) {
        return sprintf(
            'rbac_%d_%s_%s',
            $user_id,
            $capability,
            $resource_id ?: 'null'
        );
    }
    
    /**
     * Clear user cache
     */
    private function clear_user_cache($user_id) {
        // Clear all cached permissions for user
        wp_cache_delete($user_id, $this->cache_group . '_user');
    }
    
    /**
     * Log role changes
     */
    private function log_role_change($user_id, $action, $role) {
        $this->db->insert(
            $this->db->prefix . 'money_quiz_audit_log',
            [
                'user_id' => $user_id,
                'action' => 'role_' . $action,
                'object_type' => 'role',
                'object_id' => $role,
                'performed_by' => get_current_user_id(),
                'timestamp' => current_time('mysql'),
                'ip_address' => $_SERVER['REMOTE_ADDR']
            ]
        );
    }
    
    /**
     * Log permission changes
     */
    private function log_permission_change($user_id, $action, $capability, $resource_id = null) {
        $this->db->insert(
            $this->db->prefix . 'money_quiz_audit_log',
            [
                'user_id' => $user_id,
                'action' => 'permission_' . $action,
                'object_type' => 'permission',
                'object_id' => $capability,
                'resource_id' => $resource_id,
                'performed_by' => get_current_user_id(),
                'timestamp' => current_time('mysql'),
                'ip_address' => $_SERVER['REMOTE_ADDR']
            ]
        );
    }
    
    /**
     * Get user permissions summary
     */
    public function get_user_permissions($user_id) {
        $roles = $this->get_user_roles($user_id);
        $permissions = [];
        
        // Get role-based permissions
        foreach ($roles as $role) {
            if (isset($this->roles[$role])) {
                $permissions = array_merge($permissions, $this->roles[$role]['capabilities']);
            }
        }
        
        // Get custom permissions
        $custom = $this->db->get_results($this->db->prepare(
            "SELECT capability, resource_id FROM {$this->db->prefix}money_quiz_permissions 
            WHERE user_id = %d AND (expires_at IS NULL OR expires_at > NOW())",
            $user_id
        ));
        
        foreach ($custom as $perm) {
            $permissions[] = $perm->capability;
        }
        
        return array_unique($permissions);
    }
    
    /**
     * Create database tables
     */
    public static function create_tables() {
        global $wpdb;
        
        $charset_collate = $wpdb->get_charset_collate();
        
        // Custom permissions table
        $sql = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}money_quiz_permissions (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            user_id bigint(20) NOT NULL,
            capability varchar(100) NOT NULL,
            resource_id bigint(20) DEFAULT NULL,
            granted_at datetime DEFAULT CURRENT_TIMESTAMP,
            granted_by bigint(20) NOT NULL,
            expires_at datetime DEFAULT NULL,
            PRIMARY KEY (id),
            KEY user_capability (user_id, capability),
            KEY resource (resource_id),
            KEY expires (expires_at)
        ) $charset_collate;";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
        
        // Audit log table
        $sql = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}money_quiz_audit_log (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            user_id bigint(20) NOT NULL,
            action varchar(50) NOT NULL,
            object_type varchar(50) NOT NULL,
            object_id varchar(100),
            resource_id bigint(20) DEFAULT NULL,
            performed_by bigint(20) NOT NULL,
            timestamp datetime DEFAULT CURRENT_TIMESTAMP,
            ip_address varchar(45),
            data text,
            PRIMARY KEY (id),
            KEY user_id (user_id),
            KEY action (action),
            KEY timestamp (timestamp)
        ) $charset_collate;";
        
        dbDelta($sql);
    }
}

// Initialize on plugin activation
register_activation_hook(__FILE__, ['MoneyQuiz\Security\Authentication\RBACSystem', 'create_tables']);
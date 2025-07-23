<?php
/**
 * Enhanced Session Manager
 *
 * Manages sessions with automatic regeneration on security events.
 *
 * @package MoneyQuiz\Frontend
 * @since   7.0.0
 */

namespace MoneyQuiz\Frontend;

use MoneyQuiz\Core\Logging\Logger;

// Security: Prevent direct access.
defined('ABSPATH') || exit;

/**
 * Enhanced session manager with security event regeneration.
 *
 * @since 7.0.0
 */
class EnhancedSessionManager extends SessionManager {
    
    /**
     * Events that trigger session regeneration.
     *
     * @var array
     */
    private array $regeneration_events = [
        'wp_login',                    // User login
        'wp_logout',                   // User logout
        'set_auth_cookie',             // Authentication cookie set
        'clear_auth_cookie',           // Authentication cookie cleared
        'password_reset',              // Password reset
        'profile_update',              // Profile updated
        'set_user_role',               // Role changed
        'grant_super_admin',           // Super admin granted
        'revoke_super_admin',          // Super admin revoked
        'money_quiz_settings_updated', // Plugin settings changed
        'money_quiz_quiz_deleted',     // Quiz deleted (privilege escalation)
        'money_quiz_data_exported',    // Data exported (sensitive action)
    ];
    
    /**
     * Constructor.
     *
     * @param string $prefix Session prefix.
     * @param Logger $logger Logger instance.
     */
    public function __construct(string $prefix, Logger $logger) {
        parent::__construct($prefix, $logger);
        
        // Hook into security events
        $this->register_regeneration_hooks();
    }
    
    /**
     * Register hooks for session regeneration.
     */
    private function register_regeneration_hooks(): void {
        foreach ($this->regeneration_events as $event) {
            add_action($event, [$this, 'regenerate_on_event'], 10, 2);
        }
    }
    
    /**
     * Regenerate session on security event.
     *
     * @param mixed ...$args Event arguments.
     */
    public function regenerate_on_event(...$args): void {
        $event = current_action();
        
        $this->logger->info('Security event triggered session regeneration', [
            'event' => $event,
            'user_id' => get_current_user_id(),
            'old_session_id' => session_id()
        ]);
        
        // Regenerate session
        $this->regenerate();
        
        // Additional security for specific events
        switch ($event) {
            case 'wp_login':
                $this->handle_login_regeneration($args[0] ?? '', $args[1] ?? null);
                break;
                
            case 'wp_logout':
                $this->handle_logout_regeneration();
                break;
                
            case 'set_user_role':
                $this->handle_role_change_regeneration($args[0] ?? 0, $args[1] ?? '');
                break;
        }
    }
    
    /**
     * Handle session regeneration on login.
     *
     * @param string   $user_login Username.
     * @param \WP_User $user       User object.
     */
    private function handle_login_regeneration(string $user_login, ?\WP_User $user): void {
        if (!$user) {
            return;
        }
        
        // Clear any existing quiz session data
        $this->clear_namespace('quiz');
        
        // Set new session fingerprint
        $fingerprint = $this->generate_fingerprint();
        $this->set('fingerprint', $fingerprint);
        
        // Store login timestamp
        $this->set('login_time', time());
        
        $this->logger->info('Session regenerated on login', [
            'user_id' => $user->ID,
            'username' => $user_login,
            'new_session_id' => session_id()
        ]);
    }
    
    /**
     * Handle session regeneration on logout.
     */
    private function handle_logout_regeneration(): void {
        // Destroy entire session
        $this->destroy();
        
        // Start fresh session for anonymous user
        $this->start();
        
        $this->logger->info('Session destroyed and regenerated on logout');
    }
    
    /**
     * Handle session regeneration on role change.
     *
     * @param int    $user_id  User ID.
     * @param string $new_role New role.
     */
    private function handle_role_change_regeneration(int $user_id, string $new_role): void {
        // Clear any cached permissions
        $this->clear_namespace('permissions');
        
        // Update session with new role info
        $this->set('role_changed', true);
        $this->set('role_change_time', time());
        
        $this->logger->warning('Session regenerated due to role change', [
            'user_id' => $user_id,
            'new_role' => $new_role
        ]);
    }
    
    /**
     * Generate session fingerprint.
     *
     * @return string Session fingerprint.
     */
    private function generate_fingerprint(): string {
        $components = [
            $_SERVER['HTTP_USER_AGENT'] ?? '',
            $_SERVER['HTTP_ACCEPT_LANGUAGE'] ?? '',
            $_SERVER['HTTP_ACCEPT_ENCODING'] ?? '',
        ];
        
        return hash('sha256', implode('|', $components));
    }
    
    /**
     * Validate session fingerprint.
     *
     * @return bool True if valid.
     */
    public function validate_fingerprint(): bool {
        $stored = $this->get('fingerprint');
        if (!$stored) {
            return true; // No fingerprint stored yet
        }
        
        $current = $this->generate_fingerprint();
        
        if ($stored !== $current) {
            $this->logger->warning('Session fingerprint mismatch detected', [
                'stored' => substr($stored, 0, 8) . '...',
                'current' => substr($current, 0, 8) . '...',
                'user_id' => get_current_user_id()
            ]);
            
            // Possible session hijacking
            $this->destroy();
            return false;
        }
        
        return true;
    }
    
    /**
     * Check if session needs regeneration.
     *
     * @return bool True if regeneration needed.
     */
    public function needs_regeneration(): bool {
        // Check session age
        $start_time = $this->get('start_time', 0);
        if (time() - $start_time > 3600) { // 1 hour
            return true;
        }
        
        // Check if role was changed
        if ($this->get('role_changed', false)) {
            return true;
        }
        
        // Validate fingerprint
        if (!$this->validate_fingerprint()) {
            return true;
        }
        
        return false;
    }
}
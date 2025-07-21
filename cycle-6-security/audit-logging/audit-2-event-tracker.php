<?php
/**
 * Audit Event Tracker
 * 
 * @package MoneyQuiz\Security\Audit
 * @version 1.0.0
 */

namespace MoneyQuiz\Security\Audit;

/**
 * Event Types
 */
class EventType {
    // Authentication Events
    const LOGIN_SUCCESS = 'auth.login.success';
    const LOGIN_FAILED = 'auth.login.failed';
    const LOGOUT = 'auth.logout';
    const PASSWORD_RESET = 'auth.password.reset';
    const PASSWORD_CHANGED = 'auth.password.changed';
    
    // Authorization Events
    const ACCESS_GRANTED = 'authz.access.granted';
    const ACCESS_DENIED = 'authz.access.denied';
    const PERMISSION_CHANGED = 'authz.permission.changed';
    
    // Data Events
    const DATA_CREATED = 'data.created';
    const DATA_UPDATED = 'data.updated';
    const DATA_DELETED = 'data.deleted';
    const DATA_EXPORTED = 'data.exported';
    
    // Security Events
    const SECURITY_THREAT = 'security.threat';
    const SECURITY_BLOCKED = 'security.blocked';
    const RATE_LIMIT_EXCEEDED = 'security.rate_limit';
    const INVALID_INPUT = 'security.invalid_input';
    
    // System Events
    const SYSTEM_ERROR = 'system.error';
    const SYSTEM_WARNING = 'system.warning';
    const CONFIG_CHANGED = 'system.config.changed';
    const PLUGIN_ACTIVATED = 'system.plugin.activated';
    const PLUGIN_DEACTIVATED = 'system.plugin.deactivated';
}

/**
 * Event Tracker
 */
class AuditEventTracker {
    
    private $logger;
    private $tracked_events = [];
    
    public function __construct(AuditLogInterface $logger) {
        $this->logger = $logger;
        $this->setupTracking();
    }
    
    /**
     * Setup event tracking
     */
    private function setupTracking() {
        // Authentication tracking
        add_action('wp_login', [$this, 'trackLogin'], 10, 2);
        add_action('wp_login_failed', [$this, 'trackLoginFailed']);
        add_action('wp_logout', [$this, 'trackLogout']);
        add_action('password_reset', [$this, 'trackPasswordReset'], 10, 2);
        add_action('profile_update', [$this, 'trackProfileUpdate'], 10, 2);
        
        // Data tracking
        add_action('save_post', [$this, 'trackPostSave'], 10, 3);
        add_action('delete_post', [$this, 'trackPostDelete']);
        add_action('wp_insert_comment', [$this, 'trackCommentInsert'], 10, 2);
        
        // Plugin specific tracking
        add_action('money_quiz_quiz_created', [$this, 'trackQuizCreated']);
        add_action('money_quiz_quiz_completed', [$this, 'trackQuizCompleted']);
        add_action('money_quiz_settings_updated', [$this, 'trackSettingsUpdate']);
        
        // Security tracking
        add_action('money_quiz_security_threat', [$this, 'trackSecurityThreat']);
        add_action('money_quiz_rate_limit_exceeded', [$this, 'trackRateLimit']);
    }
    
    /**
     * Track login
     */
    public function trackLogin($user_login, $user) {
        $this->logger->info(EventType::LOGIN_SUCCESS, [
            'username' => $user_login,
            'user_id' => $user->ID,
            'user_email' => $user->user_email,
            'roles' => $user->roles
        ]);
    }
    
    /**
     * Track failed login
     */
    public function trackLoginFailed($username) {
        $this->logger->warning(EventType::LOGIN_FAILED, [
            'username' => $username,
            'authentication_method' => 'password'
        ]);
    }
    
    /**
     * Track logout
     */
    public function trackLogout() {
        $user = wp_get_current_user();
        
        $this->logger->info(EventType::LOGOUT, [
            'username' => $user->user_login,
            'user_id' => $user->ID,
            'session_duration' => $this->getSessionDuration()
        ]);
    }
    
    /**
     * Track password reset
     */
    public function trackPasswordReset($user, $new_pass) {
        $this->logger->notice(EventType::PASSWORD_RESET, [
            'user_id' => $user->ID,
            'username' => $user->user_login,
            'reset_method' => 'email'
        ]);
    }
    
    /**
     * Track profile update
     */
    public function trackProfileUpdate($user_id, $old_user_data) {
        $user = get_user_by('id', $user_id);
        
        // Check if password changed
        if ($user->user_pass !== $old_user_data->user_pass) {
            $this->logger->notice(EventType::PASSWORD_CHANGED, [
                'user_id' => $user_id,
                'username' => $user->user_login
            ]);
        }
    }
    
    /**
     * Track post save
     */
    public function trackPostSave($post_id, $post, $update) {
        if (wp_is_post_autosave($post_id) || wp_is_post_revision($post_id)) {
            return;
        }
        
        $event = $update ? EventType::DATA_UPDATED : EventType::DATA_CREATED;
        
        $this->logger->info($event, [
            'object_type' => 'post',
            'object_id' => $post_id,
            'post_type' => $post->post_type,
            'post_title' => $post->post_title,
            'post_status' => $post->post_status
        ]);
    }
    
    /**
     * Track post delete
     */
    public function trackPostDelete($post_id) {
        $post = get_post($post_id);
        
        if (!$post) {
            return;
        }
        
        $this->logger->notice(EventType::DATA_DELETED, [
            'object_type' => 'post',
            'object_id' => $post_id,
            'post_type' => $post->post_type,
            'post_title' => $post->post_title
        ]);
    }
    
    /**
     * Track security threat
     */
    public function trackSecurityThreat($threat_data) {
        $this->logger->critical(EventType::SECURITY_THREAT, $threat_data);
    }
    
    /**
     * Track rate limit
     */
    public function trackRateLimit($limit_data) {
        $this->logger->warning(EventType::RATE_LIMIT_EXCEEDED, $limit_data);
    }
    
    /**
     * Get session duration
     */
    private function getSessionDuration() {
        if (isset($_SESSION['login_time'])) {
            return time() - $_SESSION['login_time'];
        }
        return 0;
    }
    
    /**
     * Track custom event
     */
    public function trackCustom($event, $level = LogLevel::INFO, array $context = []) {
        $this->logger->log($event, $level, $context);
    }
}
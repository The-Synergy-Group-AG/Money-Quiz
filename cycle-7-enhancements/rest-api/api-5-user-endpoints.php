<?php
/**
 * User REST API Endpoints
 * 
 * @package MoneyQuiz\API
 * @version 1.0.0
 */

namespace MoneyQuiz\API;

/**
 * User API Endpoints
 */
class UserEndpoints extends ApiEndpointBase {
    
    protected $resource = 'users';
    
    /**
     * Setup user endpoints
     */
    protected function setupEndpoints() {
        // Current user
        $this->router->addRoute('/users/me', 'GET', [$this, 'getCurrentUser'], [
            'permission_callback' => 'is_user_logged_in'
        ]);
        
        // User profile
        $this->router->addRoute('/users/(?P<id>[\d]+)', 'GET', [$this, 'getUser'], [
            'permission_callback' => [$this, 'getUserPermission'],
            'args' => ['id' => ['validate_callback' => [$this, 'validateId']]]
        ]);
        
        // User statistics
        $this->router->addRoute('/users/(?P<id>[\d]+)/stats', 'GET', [$this, 'getUserStats'], [
            'permission_callback' => [$this, 'getUserPermission'],
            'args' => ['id' => ['validate_callback' => [$this, 'validateId']]]
        ]);
        
        // User progress
        $this->router->addRoute('/users/(?P<id>[\d]+)/progress', 'GET', [$this, 'getUserProgress'], [
            'permission_callback' => [$this, 'getUserPermission'],
            'args' => ['id' => ['validate_callback' => [$this, 'validateId']]]
        ]);
        
        // Update user preferences
        $this->router->addRoute('/users/me/preferences', 'PUT', [$this, 'updatePreferences'], [
            'permission_callback' => 'is_user_logged_in',
            'args' => $this->getPreferencesParams()
        ]);
        
        // Leaderboard
        $this->router->addRoute('/users/leaderboard', 'GET', [$this, 'getLeaderboard'], [
            'permission_callback' => '__return_true',
            'args' => $this->getLeaderboardParams()
        ]);
    }
    
    /**
     * Get current user
     */
    public function getCurrentUser($request) {
        $user = wp_get_current_user();
        
        if (!$user->ID) {
            return $this->error('not_logged_in', 'User not logged in', 401);
        }
        
        return rest_ensure_response($this->prepareUser($user));
    }
    
    /**
     * Get user by ID
     */
    public function getUser($request) {
        $user_id = (int) $request->get_param('id');
        $user = get_user_by('id', $user_id);
        
        if (!$user) {
            return $this->error('user_not_found', 'User not found', 404);
        }
        
        return rest_ensure_response($this->prepareUser($user));
    }
    
    /**
     * Get user statistics
     */
    public function getUserStats($request) {
        global $wpdb;
        
        $user_id = (int) $request->get_param('id');
        
        // Total quizzes taken
        $total_quizzes = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(DISTINCT quiz_id) FROM {$wpdb->prefix}money_quiz_results WHERE user_id = %d",
            $user_id
        ));
        
        // Total attempts
        $total_attempts = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->prefix}money_quiz_results WHERE user_id = %d",
            $user_id
        ));
        
        // Average score
        $avg_score = $wpdb->get_var($wpdb->prepare(
            "SELECT AVG(score) FROM {$wpdb->prefix}money_quiz_results WHERE user_id = %d",
            $user_id
        ));
        
        // Best score
        $best_score = $wpdb->get_var($wpdb->prepare(
            "SELECT MAX(score) FROM {$wpdb->prefix}money_quiz_results WHERE user_id = %d",
            $user_id
        ));
        
        // Total time spent
        $total_time = $wpdb->get_var($wpdb->prepare(
            "SELECT SUM(time_taken) FROM {$wpdb->prefix}money_quiz_results WHERE user_id = %d",
            $user_id
        ));
        
        // Recent activity
        $recent_results = $wpdb->get_results($wpdb->prepare("
            SELECT r.*, q.title as quiz_title
            FROM {$wpdb->prefix}money_quiz_results r
            LEFT JOIN {$wpdb->prefix}money_quiz_quizzes q ON r.quiz_id = q.id
            WHERE r.user_id = %d
            ORDER BY r.completed_at DESC
            LIMIT 5
        ", $user_id));
        
        return rest_ensure_response([
            'user_id' => $user_id,
            'total_quizzes' => (int) $total_quizzes,
            'total_attempts' => (int) $total_attempts,
            'average_score' => round($avg_score ?: 0, 1),
            'best_score' => (int) $best_score,
            'total_time_minutes' => round(($total_time ?: 0) / 60),
            'recent_activity' => array_map([$this, 'prepareRecentActivity'], $recent_results)
        ]);
    }
    
    /**
     * Get user progress
     */
    public function getUserProgress($request) {
        global $wpdb;
        
        $user_id = (int) $request->get_param('id');
        
        // Get all quizzes with user's best score
        $quizzes = $wpdb->get_results($wpdb->prepare("
            SELECT 
                q.id,
                q.title,
                q.description,
                MAX(r.score) as best_score,
                COUNT(r.id) as attempts,
                MAX(r.completed_at) as last_attempt
            FROM {$wpdb->prefix}money_quiz_quizzes q
            LEFT JOIN {$wpdb->prefix}money_quiz_results r ON q.id = r.quiz_id AND r.user_id = %d
            WHERE q.status = 'published'
            GROUP BY q.id
            ORDER BY q.title
        ", $user_id));
        
        $completed = 0;
        $in_progress = 0;
        $not_started = 0;
        
        foreach ($quizzes as $quiz) {
            if ($quiz->best_score >= 70) {
                $completed++;
            } elseif ($quiz->attempts > 0) {
                $in_progress++;
            } else {
                $not_started++;
            }
        }
        
        return rest_ensure_response([
            'summary' => [
                'total_quizzes' => count($quizzes),
                'completed' => $completed,
                'in_progress' => $in_progress,
                'not_started' => $not_started,
                'completion_rate' => count($quizzes) > 0 ? 
                    round(($completed / count($quizzes)) * 100) : 0
            ],
            'quizzes' => array_map([$this, 'prepareProgressItem'], $quizzes)
        ]);
    }
    
    /**
     * Update user preferences
     */
    public function updatePreferences($request) {
        $user_id = get_current_user_id();
        
        $preferences = [
            'email_notifications' => $request->get_param('email_notifications'),
            'show_in_leaderboard' => $request->get_param('show_in_leaderboard'),
            'preferred_difficulty' => $request->get_param('preferred_difficulty')
        ];
        
        update_user_meta($user_id, 'money_quiz_preferences', $preferences);
        
        do_action('money_quiz_preferences_updated', $user_id, $preferences);
        
        return $this->success($preferences, 'Preferences updated successfully');
    }
    
    /**
     * Get leaderboard
     */
    public function getLeaderboard($request) {
        global $wpdb;
        
        $period = $request->get_param('period');
        $quiz_id = $request->get_param('quiz_id');
        $limit = min((int) $request->get_param('limit'), 100);
        
        // Build date filter
        $date_filter = '';
        switch ($period) {
            case 'today':
                $date_filter = "AND DATE(r.completed_at) = CURDATE()";
                break;
            case 'week':
                $date_filter = "AND r.completed_at >= DATE_SUB(NOW(), INTERVAL 1 WEEK)";
                break;
            case 'month':
                $date_filter = "AND r.completed_at >= DATE_SUB(NOW(), INTERVAL 1 MONTH)";
                break;
        }
        
        // Build quiz filter
        $quiz_filter = '';
        if ($quiz_id) {
            $quiz_filter = $wpdb->prepare("AND r.quiz_id = %d", $quiz_id);
        }
        
        // Get leaderboard data
        $leaderboard = $wpdb->get_results($wpdb->prepare("
            SELECT 
                u.ID as user_id,
                u.display_name,
                u.user_email,
                AVG(r.score) as average_score,
                COUNT(DISTINCT r.quiz_id) as quizzes_completed,
                COUNT(r.id) as total_attempts
            FROM {$wpdb->users} u
            INNER JOIN {$wpdb->prefix}money_quiz_results r ON u.ID = r.user_id
            INNER JOIN {$wpdb->usermeta} m ON u.ID = m.user_id
            WHERE m.meta_key = 'money_quiz_preferences'
                AND m.meta_value LIKE '%%\"show_in_leaderboard\":true%%'
                {$date_filter}
                {$quiz_filter}
            GROUP BY u.ID
            ORDER BY average_score DESC
            LIMIT %d
        ", $limit));
        
        return rest_ensure_response([
            'period' => $period,
            'quiz_id' => $quiz_id,
            'entries' => array_map([$this, 'prepareLeaderboardEntry'], $leaderboard)
        ]);
    }
    
    /**
     * Prepare user data
     */
    private function prepareUser($user) {
        $preferences = get_user_meta($user->ID, 'money_quiz_preferences', true) ?: [];
        
        return [
            'id' => $user->ID,
            'username' => $user->user_login,
            'display_name' => $user->display_name,
            'email' => $user->user_email,
            'avatar_url' => get_avatar_url($user->ID),
            'registered' => $user->user_registered,
            'preferences' => $preferences,
            'capabilities' => [
                'can_create_quiz' => user_can($user, 'edit_posts'),
                'can_view_all_results' => user_can($user, 'manage_options')
            ]
        ];
    }
    
    /**
     * Prepare recent activity item
     */
    private function prepareRecentActivity($item) {
        return [
            'quiz_id' => (int) $item->quiz_id,
            'quiz_title' => $item->quiz_title,
            'score' => (int) $item->score,
            'completed_at' => $item->completed_at
        ];
    }
    
    /**
     * Prepare progress item
     */
    private function prepareProgressItem($item) {
        $status = 'not_started';
        if ($item->best_score >= 70) {
            $status = 'completed';
        } elseif ($item->attempts > 0) {
            $status = 'in_progress';
        }
        
        return [
            'quiz_id' => (int) $item->id,
            'title' => $item->title,
            'description' => $item->description,
            'status' => $status,
            'best_score' => (int) $item->best_score,
            'attempts' => (int) $item->attempts,
            'last_attempt' => $item->last_attempt
        ];
    }
    
    /**
     * Prepare leaderboard entry
     */
    private function prepareLeaderboardEntry($entry) {
        return [
            'user_id' => (int) $entry->user_id,
            'display_name' => $entry->display_name,
            'avatar_url' => get_avatar_url($entry->user_email),
            'average_score' => round($entry->average_score, 1),
            'quizzes_completed' => (int) $entry->quizzes_completed,
            'total_attempts' => (int) $entry->total_attempts
        ];
    }
    
    /**
     * Permission callback
     */
    public function getUserPermission($request) {
        $user_id = (int) $request->get_param('id');
        return current_user_can('manage_options') || get_current_user_id() === $user_id;
    }
    
    /**
     * Get preferences parameters
     */
    private function getPreferencesParams() {
        return [
            'email_notifications' => [
                'type' => 'boolean',
                'default' => true
            ],
            'show_in_leaderboard' => [
                'type' => 'boolean',
                'default' => true
            ],
            'preferred_difficulty' => [
                'enum' => ['easy', 'medium', 'hard'],
                'default' => 'medium'
            ]
        ];
    }
    
    /**
     * Get leaderboard parameters
     */
    private function getLeaderboardParams() {
        return [
            'period' => [
                'default' => 'all',
                'enum' => ['all', 'today', 'week', 'month']
            ],
            'quiz_id' => [
                'validate_callback' => function($value) {
                    return empty($value) || (is_numeric($value) && $value > 0);
                }
            ],
            'limit' => [
                'default' => 10,
                'validate_callback' => function($value) {
                    return is_numeric($value) && $value > 0 && $value <= 100;
                }
            ]
        ];
    }
}
<?php
/**
 * Result REST API Endpoints
 * 
 * @package MoneyQuiz\API
 * @version 1.0.0
 */

namespace MoneyQuiz\API;

/**
 * Result API Endpoints
 */
class ResultEndpoints extends ApiEndpointBase {
    
    protected $resource = 'results';
    
    /**
     * Setup result endpoints
     */
    protected function setupEndpoints() {
        // List results
        $this->router->addRoute('/results', 'GET', [$this, 'getResults'], [
            'permission_callback' => [$this, 'getResultsPermission'],
            'args' => $this->getCollectionParams()
        ]);
        
        // Submit result
        $this->router->addRoute('/results', 'POST', [$this, 'submitResult'], [
            'permission_callback' => '__return_true',
            'args' => $this->getSubmitParams()
        ]);
        
        // Get single result
        $this->router->addRoute('/results/(?P<id>[\d]+)', 'GET', [$this, 'getResult'], [
            'permission_callback' => [$this, 'getResultPermission'],
            'args' => ['id' => ['validate_callback' => [$this, 'validateId']]]
        ]);
        
        // Get user results
        $this->router->addRoute('/results/user/(?P<user_id>[\d]+)', 'GET', [$this, 'getUserResults'], [
            'permission_callback' => [$this, 'getUserResultsPermission'],
            'args' => [
                'user_id' => ['validate_callback' => [$this, 'validateId']],
                'quiz_id' => ['validate_callback' => [$this, 'validateId']]
            ]
        ]);
        
        // Export results
        $this->router->addRoute('/results/export', 'GET', [$this, 'exportResults'], [
            'permission_callback' => [$this, 'exportResultsPermission'],
            'args' => [
                'format' => [
                    'default' => 'csv',
                    'enum' => ['csv', 'json', 'excel']
                ]
            ]
        ]);
    }
    
    /**
     * Get results
     */
    public function getResults($request) {
        global $wpdb;
        
        $page = (int) $request->get_param('page');
        $per_page = (int) $request->get_param('per_page');
        $quiz_id = $request->get_param('quiz_id');
        $user_id = $request->get_param('user_id');
        
        $offset = ($page - 1) * $per_page;
        
        // Build where clause
        $where = '1=1';
        if ($quiz_id) {
            $where .= $wpdb->prepare(' AND quiz_id = %d', $quiz_id);
        }
        if ($user_id) {
            $where .= $wpdb->prepare(' AND user_id = %d', $user_id);
        }
        
        // Get results
        $results = $wpdb->get_results($wpdb->prepare("
            SELECT r.*, q.title as quiz_title, u.display_name as user_name
            FROM {$wpdb->prefix}money_quiz_results r
            LEFT JOIN {$wpdb->prefix}money_quiz_quizzes q ON r.quiz_id = q.id
            LEFT JOIN {$wpdb->users} u ON r.user_id = u.ID
            WHERE {$where}
            ORDER BY r.completed_at DESC
            LIMIT %d OFFSET %d
        ", $per_page, $offset));
        
        $total = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}money_quiz_results WHERE {$where}");
        
        return $this->prepareCollectionResponse($results, $total, $request);
    }
    
    /**
     * Submit quiz result
     */
    public function submitResult($request) {
        global $wpdb;
        
        $quiz_id = (int) $request->get_param('quiz_id');
        $answers = $request->get_param('answers');
        $time_taken = (int) $request->get_param('time_taken');
        
        // Validate quiz exists
        $quiz = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}money_quiz_quizzes WHERE id = %d AND status = 'published'",
            $quiz_id
        ));
        
        if (!$quiz) {
            return $this->error('quiz_not_found', 'Quiz not found or not published', 404);
        }
        
        // Calculate score
        $score = $this->calculateScore($quiz_id, $answers);
        
        // Save result
        $result_data = [
            'quiz_id' => $quiz_id,
            'user_id' => get_current_user_id() ?: null,
            'score' => $score['percentage'],
            'answers' => json_encode($answers),
            'time_taken' => $time_taken,
            'ip_address' => $_SERVER['REMOTE_ADDR'],
            'user_agent' => $_SERVER['HTTP_USER_AGENT'],
            'completed_at' => current_time('mysql')
        ];
        
        $wpdb->insert($wpdb->prefix . 'money_quiz_results', $result_data);
        
        if ($wpdb->insert_id) {
            $result_id = $wpdb->insert_id;
            
            // Trigger completion action
            do_action('money_quiz_result_submitted', $result_id, $score);
            
            return $this->success([
                'result_id' => $result_id,
                'score' => $score,
                'feedback' => $this->getFeedback($score['percentage'])
            ], 'Result submitted successfully');
        }
        
        return $this->error('submit_failed', 'Failed to submit result', 500);
    }
    
    /**
     * Get single result
     */
    public function getResult($request) {
        global $wpdb;
        
        $id = (int) $request->get_param('id');
        
        $result = $wpdb->get_row($wpdb->prepare("
            SELECT r.*, q.title as quiz_title, u.display_name as user_name
            FROM {$wpdb->prefix}money_quiz_results r
            LEFT JOIN {$wpdb->prefix}money_quiz_quizzes q ON r.quiz_id = q.id
            LEFT JOIN {$wpdb->users} u ON r.user_id = u.ID
            WHERE r.id = %d
        ", $id));
        
        if (!$result) {
            return $this->error('result_not_found', 'Result not found', 404);
        }
        
        // Check permission
        if (!current_user_can('manage_options') && 
            $result->user_id != get_current_user_id()) {
            return $this->error('forbidden', 'Access denied', 403);
        }
        
        return rest_ensure_response($this->prepareItem($result));
    }
    
    /**
     * Get user results
     */
    public function getUserResults($request) {
        global $wpdb;
        
        $user_id = (int) $request->get_param('user_id');
        $quiz_id = $request->get_param('quiz_id');
        
        // Check permission
        if (!current_user_can('manage_options') && 
            $user_id != get_current_user_id()) {
            return $this->error('forbidden', 'Access denied', 403);
        }
        
        $where = $wpdb->prepare('user_id = %d', $user_id);
        if ($quiz_id) {
            $where .= $wpdb->prepare(' AND quiz_id = %d', $quiz_id);
        }
        
        $results = $wpdb->get_results("
            SELECT r.*, q.title as quiz_title
            FROM {$wpdb->prefix}money_quiz_results r
            LEFT JOIN {$wpdb->prefix}money_quiz_quizzes q ON r.quiz_id = q.id
            WHERE {$where}
            ORDER BY r.completed_at DESC
        ");
        
        return rest_ensure_response([
            'results' => array_map([$this, 'prepareItem'], $results),
            'total' => count($results)
        ]);
    }
    
    /**
     * Export results
     */
    public function exportResults($request) {
        $format = $request->get_param('format');
        $quiz_id = $request->get_param('quiz_id');
        
        // Get results
        global $wpdb;
        $where = '1=1';
        if ($quiz_id) {
            $where .= $wpdb->prepare(' AND quiz_id = %d', $quiz_id);
        }
        
        $results = $wpdb->get_results("
            SELECT r.*, q.title as quiz_title, u.display_name as user_name
            FROM {$wpdb->prefix}money_quiz_results r
            LEFT JOIN {$wpdb->prefix}money_quiz_quizzes q ON r.quiz_id = q.id
            LEFT JOIN {$wpdb->users} u ON r.user_id = u.ID
            WHERE {$where}
            ORDER BY r.completed_at DESC
        ");
        
        switch ($format) {
            case 'csv':
                return $this->exportCsv($results);
            case 'json':
                return rest_ensure_response($results);
            case 'excel':
                return $this->exportExcel($results);
        }
    }
    
    /**
     * Calculate score
     */
    private function calculateScore($quiz_id, $answers) {
        global $wpdb;
        
        // Get questions
        $questions = $wpdb->get_results($wpdb->prepare(
            "SELECT id, correct_answer, points FROM {$wpdb->prefix}money_quiz_questions WHERE quiz_id = %d",
            $quiz_id
        ));
        
        $total_points = 0;
        $earned_points = 0;
        $correct_count = 0;
        
        foreach ($questions as $question) {
            $total_points += $question->points;
            
            if (isset($answers[$question->id]) && 
                $answers[$question->id] == $question->correct_answer) {
                $earned_points += $question->points;
                $correct_count++;
            }
        }
        
        return [
            'correct' => $correct_count,
            'total' => count($questions),
            'points' => $earned_points,
            'total_points' => $total_points,
            'percentage' => $total_points > 0 ? round(($earned_points / $total_points) * 100) : 0
        ];
    }
    
    /**
     * Get feedback based on score
     */
    private function getFeedback($percentage) {
        if ($percentage >= 90) {
            return 'Excellent! You have mastered this topic.';
        } elseif ($percentage >= 70) {
            return 'Good job! You have a solid understanding.';
        } elseif ($percentage >= 50) {
            return 'Not bad, but there is room for improvement.';
        } else {
            return 'Keep practicing! Review the material and try again.';
        }
    }
    
    /**
     * Export as CSV
     */
    private function exportCsv($results) {
        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="quiz-results.csv"');
        
        $output = fopen('php://output', 'w');
        
        // Headers
        fputcsv($output, ['ID', 'Quiz', 'User', 'Score', 'Date']);
        
        // Data
        foreach ($results as $result) {
            fputcsv($output, [
                $result->id,
                $result->quiz_title,
                $result->user_name ?: 'Guest',
                $result->score . '%',
                $result->completed_at
            ]);
        }
        
        fclose($output);
        exit;
    }
    
    /**
     * Export as Excel (simplified)
     */
    private function exportExcel($results) {
        // For now, return CSV with Excel headers
        header('Content-Type: application/vnd.ms-excel');
        header('Content-Disposition: attachment; filename="quiz-results.xls"');
        
        return $this->exportCsv($results);
    }
    
    /**
     * Prepare result item
     */
    protected function prepareItem($item) {
        return [
            'id' => (int) $item->id,
            'quiz_id' => (int) $item->quiz_id,
            'quiz_title' => $item->quiz_title,
            'user_id' => (int) $item->user_id,
            'user_name' => $item->user_name ?: 'Guest',
            'score' => (int) $item->score,
            'answers' => json_decode($item->answers, true),
            'time_taken' => (int) $item->time_taken,
            'completed_at' => $item->completed_at
        ];
    }
    
    /**
     * Permission callbacks
     */
    public function getResultsPermission() {
        return current_user_can('edit_posts');
    }
    
    public function getResultPermission() {
        return is_user_logged_in();
    }
    
    public function getUserResultsPermission() {
        return is_user_logged_in();
    }
    
    public function exportResultsPermission() {
        return current_user_can('manage_options');
    }
    
    /**
     * Get submit parameters
     */
    protected function getSubmitParams() {
        return [
            'quiz_id' => [
                'required' => true,
                'validate_callback' => [$this, 'validateId']
            ],
            'answers' => [
                'required' => true,
                'type' => 'object'
            ],
            'time_taken' => [
                'type' => 'integer',
                'default' => 0
            ]
        ];
    }
}
<?php
/**
 * Quiz REST API Endpoints
 * 
 * @package MoneyQuiz\API
 * @version 1.0.0
 */

namespace MoneyQuiz\API;

/**
 * Quiz API Endpoints
 */
class QuizEndpoints extends ApiEndpointBase {
    
    protected $resource = 'quizzes';
    
    /**
     * Setup quiz endpoints
     */
    protected function setupEndpoints() {
        $this->registerCrudEndpoints();
        
        // Additional quiz-specific endpoints
        $this->router->addRoute('/quizzes/(?P<id>[\d]+)/publish', 'POST', [$this, 'publishQuiz'], [
            'permission_callback' => [$this, 'updateItemPermission'],
            'args' => ['id' => ['validate_callback' => [$this, 'validateId']]]
        ]);
        
        $this->router->addRoute('/quizzes/(?P<id>[\d]+)/duplicate', 'POST', [$this, 'duplicateQuiz'], [
            'permission_callback' => [$this, 'createItemPermission'],
            'args' => ['id' => ['validate_callback' => [$this, 'validateId']]]
        ]);
        
        $this->router->addRoute('/quizzes/(?P<id>[\d]+)/statistics', 'GET', [$this, 'getStatistics'], [
            'permission_callback' => [$this, 'getItemPermission'],
            'args' => ['id' => ['validate_callback' => [$this, 'validateId']]]
        ]);
    }
    
    /**
     * Get quizzes
     */
    public function getItems($request) {
        global $wpdb;
        
        $page = (int) $request->get_param('page');
        $per_page = (int) $request->get_param('per_page');
        $search = $request->get_param('search');
        $orderby = $request->get_param('orderby');
        $order = strtoupper($request->get_param('order'));
        
        $offset = ($page - 1) * $per_page;
        
        // Build query
        $where = '1=1';
        if ($search) {
            $where .= $wpdb->prepare(' AND (title LIKE %s OR description LIKE %s)', 
                "%{$search}%", "%{$search}%");
        }
        
        // Get items
        $items = $wpdb->get_results($wpdb->prepare("
            SELECT * FROM {$wpdb->prefix}money_quiz_quizzes 
            WHERE {$where}
            ORDER BY {$orderby} {$order}
            LIMIT %d OFFSET %d
        ", $per_page, $offset));
        
        // Get total
        $total = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}money_quiz_quizzes WHERE {$where}");
        
        return $this->prepareCollectionResponse($items, $total, $request);
    }
    
    /**
     * Get single quiz
     */
    public function getItem($request) {
        global $wpdb;
        
        $id = (int) $request->get_param('id');
        $quiz = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}money_quiz_quizzes WHERE id = %d",
            $id
        ));
        
        if (!$quiz) {
            return $this->error('quiz_not_found', 'Quiz not found', 404);
        }
        
        // Get questions
        $questions = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}money_quiz_questions WHERE quiz_id = %d ORDER BY order_num",
            $id
        ));
        
        $quiz->questions = $questions;
        
        return rest_ensure_response($this->prepareItem($quiz));
    }
    
    /**
     * Create quiz
     */
    public function createItem($request) {
        global $wpdb;
        
        $data = [
            'title' => sanitize_text_field($request->get_param('title')),
            'description' => wp_kses_post($request->get_param('description')),
            'settings' => json_encode($request->get_param('settings') ?: []),
            'status' => 'draft',
            'created_at' => current_time('mysql'),
            'created_by' => get_current_user_id()
        ];
        
        $wpdb->insert($wpdb->prefix . 'money_quiz_quizzes', $data);
        
        if ($wpdb->insert_id) {
            $quiz = $wpdb->get_row($wpdb->prepare(
                "SELECT * FROM {$wpdb->prefix}money_quiz_quizzes WHERE id = %d",
                $wpdb->insert_id
            ));
            
            do_action('money_quiz_api_quiz_created', $quiz);
            
            return $this->success($this->prepareItem($quiz), 'Quiz created successfully');
        }
        
        return $this->error('create_failed', 'Failed to create quiz', 500);
    }
    
    /**
     * Update quiz
     */
    public function updateItem($request) {
        global $wpdb;
        
        $id = (int) $request->get_param('id');
        
        // Check quiz exists
        $quiz = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}money_quiz_quizzes WHERE id = %d",
            $id
        ));
        
        if (!$quiz) {
            return $this->error('quiz_not_found', 'Quiz not found', 404);
        }
        
        $data = [];
        if ($request->has_param('title')) {
            $data['title'] = sanitize_text_field($request->get_param('title'));
        }
        if ($request->has_param('description')) {
            $data['description'] = wp_kses_post($request->get_param('description'));
        }
        if ($request->has_param('settings')) {
            $data['settings'] = json_encode($request->get_param('settings'));
        }
        
        $data['updated_at'] = current_time('mysql');
        
        $wpdb->update(
            $wpdb->prefix . 'money_quiz_quizzes',
            $data,
            ['id' => $id]
        );
        
        do_action('money_quiz_api_quiz_updated', $id, $data);
        
        return $this->success([], 'Quiz updated successfully');
    }
    
    /**
     * Delete quiz
     */
    public function deleteItem($request) {
        global $wpdb;
        
        $id = (int) $request->get_param('id');
        
        // Check quiz exists
        $quiz = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}money_quiz_quizzes WHERE id = %d",
            $id
        ));
        
        if (!$quiz) {
            return $this->error('quiz_not_found', 'Quiz not found', 404);
        }
        
        // Delete quiz and related data
        $wpdb->delete($wpdb->prefix . 'money_quiz_quizzes', ['id' => $id]);
        $wpdb->delete($wpdb->prefix . 'money_quiz_questions', ['quiz_id' => $id]);
        $wpdb->delete($wpdb->prefix . 'money_quiz_results', ['quiz_id' => $id]);
        
        do_action('money_quiz_api_quiz_deleted', $id);
        
        return $this->success([], 'Quiz deleted successfully');
    }
    
    /**
     * Publish quiz
     */
    public function publishQuiz($request) {
        global $wpdb;
        
        $id = (int) $request->get_param('id');
        
        $wpdb->update(
            $wpdb->prefix . 'money_quiz_quizzes',
            ['status' => 'published', 'published_at' => current_time('mysql')],
            ['id' => $id]
        );
        
        do_action('money_quiz_api_quiz_published', $id);
        
        return $this->success([], 'Quiz published successfully');
    }
    
    /**
     * Duplicate quiz
     */
    public function duplicateQuiz($request) {
        global $wpdb;
        
        $id = (int) $request->get_param('id');
        
        // Get original quiz
        $quiz = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}money_quiz_quizzes WHERE id = %d",
            $id
        ), ARRAY_A);
        
        if (!$quiz) {
            return $this->error('quiz_not_found', 'Quiz not found', 404);
        }
        
        // Create duplicate
        unset($quiz['id']);
        $quiz['title'] .= ' (Copy)';
        $quiz['status'] = 'draft';
        $quiz['created_at'] = current_time('mysql');
        $quiz['created_by'] = get_current_user_id();
        
        $wpdb->insert($wpdb->prefix . 'money_quiz_quizzes', $quiz);
        $new_id = $wpdb->insert_id;
        
        // Duplicate questions
        $questions = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}money_quiz_questions WHERE quiz_id = %d",
            $id
        ), ARRAY_A);
        
        foreach ($questions as $question) {
            unset($question['id']);
            $question['quiz_id'] = $new_id;
            $wpdb->insert($wpdb->prefix . 'money_quiz_questions', $question);
        }
        
        return $this->success(['id' => $new_id], 'Quiz duplicated successfully');
    }
    
    /**
     * Get quiz statistics
     */
    public function getStatistics($request) {
        global $wpdb;
        
        $id = (int) $request->get_param('id');
        
        $stats = [
            'total_attempts' => $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM {$wpdb->prefix}money_quiz_results WHERE quiz_id = %d",
                $id
            )),
            'completion_rate' => 0,
            'average_score' => 0,
            'last_attempt' => null
        ];
        
        return rest_ensure_response($stats);
    }
    
    /**
     * Prepare quiz item
     */
    protected function prepareItem($item) {
        return [
            'id' => (int) $item->id,
            'title' => $item->title,
            'description' => $item->description,
            'status' => $item->status,
            'settings' => json_decode($item->settings, true),
            'questions' => isset($item->questions) ? $item->questions : [],
            'created_at' => $item->created_at,
            'updated_at' => $item->updated_at,
            'created_by' => (int) $item->created_by
        ];
    }
    
    /**
     * Get create parameters
     */
    protected function getCreateParams() {
        return [
            'title' => [
                'required' => true,
                'sanitize_callback' => 'sanitize_text_field'
            ],
            'description' => [
                'sanitize_callback' => 'wp_kses_post'
            ],
            'settings' => [
                'type' => 'object'
            ]
        ];
    }
    
    /**
     * Get update parameters
     */
    protected function getUpdateParams() {
        return [
            'title' => [
                'sanitize_callback' => 'sanitize_text_field'
            ],
            'description' => [
                'sanitize_callback' => 'wp_kses_post'
            ],
            'settings' => [
                'type' => 'object'
            ]
        ];
    }
}
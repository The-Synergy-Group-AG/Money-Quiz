<?php
/**
 * Modern Handler - Routes requests to new Money Quiz implementation
 * 
 * @package MoneyQuiz
 * @subpackage Routing\Handlers
 * @since 1.5.0
 */

namespace MoneyQuiz\Routing\Handlers;

if (!defined('ABSPATH')) {
    exit;
}

class ModernHandler {
    
    /**
     * Modern service instances
     */
    private $quiz_service;
    private $archetype_service;
    private $statistics_service;
    
    /**
     * Constructor
     */
    public function __construct() {
        $this->init_services();
    }
    
    /**
     * Initialize modern services
     */
    private function init_services() {
        // These would be the new modern implementations
        if (class_exists('\\MoneyQuiz\\Modern\\Services\\QuizService')) {
            $this->quiz_service = new \MoneyQuiz\Modern\Services\QuizService();
        }
        
        if (class_exists('\\MoneyQuiz\\Modern\\Services\\ArchetypeService')) {
            $this->archetype_service = new \MoneyQuiz\Modern\Services\ArchetypeService();
        }
        
        if (class_exists('\\MoneyQuiz\\Modern\\Services\\StatisticsService')) {
            $this->statistics_service = new \MoneyQuiz\Modern\Services\StatisticsService();
        }
    }
    
    /**
     * Handle request using modern system
     * 
     * @param string $action
     * @param array $data
     * @return array
     */
    public function handle($action, $data) {
        $method_map = [
            'quiz_display' => [$this, 'handle_quiz_display'],
            'quiz_list' => [$this, 'handle_quiz_list'],
            'quiz_submit' => [$this, 'handle_quiz_submit'],
            'quiz_results' => [$this, 'handle_quiz_results'],
            'archetype_fetch' => [$this, 'handle_archetype_fetch'],
            'archetype_list' => [$this, 'handle_archetype_list'],
            'statistics_view' => [$this, 'handle_statistics_view'],
            'statistics_summary' => [$this, 'handle_statistics_summary']
        ];
        
        $handler = $method_map[$action] ?? null;
        
        if (!$handler || !is_callable($handler)) {
            return [
                'success' => false,
                'error' => 'Modern handler not found',
                'action' => $action
            ];
        }
        
        return call_user_func($handler, $data);
    }
    
    /**
     * Handle quiz display
     * 
     * @param array $data
     * @return array
     */
    private function handle_quiz_display($data) {
        if (!$this->quiz_service) {
            throw new \Exception('Quiz service not available');
        }
        
        $quiz_id = intval($data['quiz_id'] ?? 0);
        
        // Use modern quiz service
        $quiz = $this->quiz_service->get_quiz($quiz_id);
        $rendered = $this->quiz_service->render_quiz($quiz);
        
        return [
            'success' => true,
            'data' => $quiz,
            'output' => $rendered,
            'handler' => 'modern',
            'cache_enabled' => true,
            'cache_ttl' => 3600
        ];
    }
    
    /**
     * Handle quiz list
     * 
     * @param array $data
     * @return array
     */
    private function handle_quiz_list($data) {
        if (!$this->quiz_service) {
            throw new \Exception('Quiz service not available');
        }
        
        $args = [
            'status' => $data['status'] ?? 'active',
            'per_page' => intval($data['per_page'] ?? 10),
            'page' => intval($data['page'] ?? 1)
        ];
        
        $quizzes = $this->quiz_service->list_quizzes($args);
        
        return [
            'success' => true,
            'data' => $quizzes,
            'handler' => 'modern',
            'pagination' => [
                'page' => $args['page'],
                'per_page' => $args['per_page'],
                'total' => $quizzes['total'] ?? 0
            ]
        ];
    }
    
    /**
     * Handle quiz submission
     * 
     * @param array $data
     * @return array
     */
    private function handle_quiz_submit($data) {
        if (!$this->quiz_service) {
            throw new \Exception('Quiz service not available');
        }
        
        // Validate submission
        $validation = $this->quiz_service->validate_submission($data);
        if (!$validation['valid']) {
            return [
                'success' => false,
                'errors' => $validation['errors'],
                'handler' => 'modern'
            ];
        }
        
        // Process submission
        $result = $this->quiz_service->process_submission($data);
        
        return [
            'success' => true,
            'data' => $result,
            'handler' => 'modern',
            'redirect' => $result['redirect_url'] ?? null
        ];
    }
    
    /**
     * Handle quiz results
     * 
     * @param array $data
     * @return array
     */
    private function handle_quiz_results($data) {
        if (!$this->quiz_service) {
            throw new \Exception('Quiz service not available');
        }
        
        $result_id = intval($data['result_id'] ?? 0);
        
        $result = $this->quiz_service->get_result($result_id);
        $rendered = $this->quiz_service->render_result($result);
        
        return [
            'success' => true,
            'data' => $result,
            'output' => $rendered,
            'handler' => 'modern'
        ];
    }
    
    /**
     * Handle archetype fetch
     * 
     * @param array $data
     * @return array
     */
    private function handle_archetype_fetch($data) {
        if (!$this->archetype_service) {
            throw new \Exception('Archetype service not available');
        }
        
        $archetype_id = $data['archetype_id'] ?? null;
        $score = $data['score'] ?? null;
        
        if ($archetype_id) {
            $archetype = $this->archetype_service->get_archetype($archetype_id);
        } elseif ($score !== null) {
            $archetype = $this->archetype_service->get_archetype_by_score($score);
        } else {
            throw new \InvalidArgumentException('Archetype ID or score required');
        }
        
        return [
            'success' => true,
            'data' => $archetype,
            'handler' => 'modern'
        ];
    }
    
    /**
     * Handle archetype list
     * 
     * @param array $data
     * @return array
     */
    private function handle_archetype_list($data) {
        if (!$this->archetype_service) {
            throw new \Exception('Archetype service not available');
        }
        
        $archetypes = $this->archetype_service->list_archetypes();
        
        return [
            'success' => true,
            'data' => $archetypes,
            'handler' => 'modern'
        ];
    }
    
    /**
     * Handle statistics view
     * 
     * @param array $data
     * @return array
     */
    private function handle_statistics_view($data) {
        if (!$this->statistics_service) {
            throw new \Exception('Statistics service not available');
        }
        
        $args = [
            'date_from' => $data['date_from'] ?? date('Y-m-d', strtotime('-30 days')),
            'date_to' => $data['date_to'] ?? date('Y-m-d'),
            'quiz_id' => $data['quiz_id'] ?? null
        ];
        
        $stats = $this->statistics_service->get_statistics($args);
        $rendered = $this->statistics_service->render_statistics($stats);
        
        return [
            'success' => true,
            'data' => $stats,
            'output' => $rendered,
            'handler' => 'modern'
        ];
    }
    
    /**
     * Handle statistics summary
     * 
     * @param array $data
     * @return array
     */
    private function handle_statistics_summary($data) {
        if (!$this->statistics_service) {
            throw new \Exception('Statistics service not available');
        }
        
        $summary = $this->statistics_service->get_summary();
        
        return [
            'success' => true,
            'data' => $summary,
            'handler' => 'modern',
            'cache_enabled' => true,
            'cache_ttl' => 300 // 5 minutes
        ];
    }
    
    /**
     * Handle quiz display using modern system (URI-based)
     * 
     * @param string $uri
     * @return array
     */
    public function handle_quiz_display_modern($uri) {
        preg_match('/\/money-quiz\/display\/([0-9]+)/', $uri, $matches);
        $quiz_id = $matches[1] ?? 0;
        
        return $this->handle('quiz_display', ['quiz_id' => $quiz_id]);
    }
    
    /**
     * Handle quiz submission using modern system (URI-based)
     * 
     * @param string $uri
     * @return array
     */
    public function handle_quiz_submit_modern($uri) {
        return $this->handle('quiz_submit', $_POST);
    }
    
    /**
     * Handle quiz results using modern system (URI-based)
     * 
     * @param string $uri
     * @return array
     */
    public function handle_quiz_results_modern($uri) {
        preg_match('/\/money-quiz\/results\/([0-9]+)/', $uri, $matches);
        $result_id = $matches[1] ?? 0;
        
        return $this->handle('quiz_results', ['result_id' => $result_id]);
    }
}
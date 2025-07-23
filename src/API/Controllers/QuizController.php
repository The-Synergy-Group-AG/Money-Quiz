<?php
/**
 * Quiz REST API Controller
 *
 * Handles quiz-related REST API endpoints.
 *
 * @package MoneyQuiz\API\Controllers
 * @since   7.0.0
 */

namespace MoneyQuiz\API\Controllers;

use MoneyQuiz\Application\Services\QuizService;
use MoneyQuiz\Core\Logging\Logger;
use WP_REST_Request;
use WP_REST_Response;
use WP_Error;

// Security: Prevent direct access.
defined('ABSPATH') || exit;

/**
 * Quiz controller class.
 *
 * @since 7.0.0
 */
class QuizController {
    
    /**
     * API namespace.
     *
     * @var string
     */
    private const NAMESPACE = 'money-quiz/v1';
    
    /**
     * Quiz service.
     *
     * @var QuizService
     */
    private QuizService $quiz_service;
    
    /**
     * Logger instance.
     *
     * @var Logger
     */
    private Logger $logger;
    
    /**
     * Constructor.
     *
     * @param QuizService $quiz_service Quiz service.
     * @param Logger      $logger       Logger instance.
     */
    public function __construct(QuizService $quiz_service, Logger $logger) {
        $this->quiz_service = $quiz_service;
        $this->logger = $logger;
    }
    
    /**
     * Register routes.
     *
     * @return void
     */
    public function register_routes(): void {
        // List quizzes
        register_rest_route(self::NAMESPACE, '/quizzes', [
            'methods' => 'GET',
            'callback' => [$this, 'get_quizzes'],
            'permission_callback' => [$this, 'check_read_permission'],
            'args' => [
                'page' => [
                    'type' => 'integer',
                    'default' => 1,
                    'minimum' => 1
                ],
                'per_page' => [
                    'type' => 'integer',
                    'default' => 10,
                    'minimum' => 1,
                    'maximum' => 100
                ],
                'status' => [
                    'type' => 'string',
                    'enum' => ['draft', 'published', 'archived']
                ]
            ]
        ]);
        
        // Get single quiz
        register_rest_route(self::NAMESPACE, '/quizzes/(?P<id>\d+)', [
            'methods' => 'GET',
            'callback' => [$this, 'get_quiz'],
            'permission_callback' => [$this, 'check_read_permission'],
            'args' => [
                'id' => [
                    'type' => 'integer',
                    'required' => true,
                    'minimum' => 1
                ]
            ]
        ]);
        
        // Create quiz
        register_rest_route(self::NAMESPACE, '/quizzes', [
            'methods' => 'POST',
            'callback' => [$this, 'create_quiz'],
            'permission_callback' => [$this, 'check_create_permission'],
            'args' => [
                'title' => [
                    'type' => 'string',
                    'required' => true,
                    'minLength' => 3,
                    'maxLength' => 200
                ],
                'description' => [
                    'type' => 'string',
                    'required' => true,
                    'minLength' => 10,
                    'maxLength' => 1000
                ],
                'settings' => [
                    'type' => 'object',
                    'default' => []
                ]
            ]
        ]);
        
        // Update quiz
        register_rest_route(self::NAMESPACE, '/quizzes/(?P<id>\d+)', [
            'methods' => 'PUT',
            'callback' => [$this, 'update_quiz'],
            'permission_callback' => [$this, 'check_update_permission'],
            'args' => [
                'id' => [
                    'type' => 'integer',
                    'required' => true,
                    'minimum' => 1
                ],
                'title' => [
                    'type' => 'string',
                    'minLength' => 3,
                    'maxLength' => 200
                ],
                'description' => [
                    'type' => 'string',
                    'minLength' => 10,
                    'maxLength' => 1000
                ]
            ]
        ]);
        
        // Publish quiz
        register_rest_route(self::NAMESPACE, '/quizzes/(?P<id>\d+)/publish', [
            'methods' => 'POST',
            'callback' => [$this, 'publish_quiz'],
            'permission_callback' => [$this, 'check_publish_permission'],
            'args' => [
                'id' => [
                    'type' => 'integer',
                    'required' => true,
                    'minimum' => 1
                ]
            ]
        ]);
    }
    
    /**
     * Get quizzes.
     *
     * @param WP_REST_Request $request Request object.
     * @return WP_REST_Response|WP_Error Response.
     */
    public function get_quizzes(WP_REST_Request $request) {
        try {
            $page = $request->get_param('page');
            $per_page = $request->get_param('per_page');
            $status = $request->get_param('status');
            
            $filters = [];
            if ($status) {
                $filters['status'] = $status;
            }
            
            $offset = ($page - 1) * $per_page;
            
            $quizzes = $this->quiz_service->list_quizzes(
                $filters,
                get_current_user_id(),
                $per_page,
                $offset
            );
            
            $data = array_map(fn($quiz) => $this->prepare_quiz_response($quiz), $quizzes);
            
            return new WP_REST_Response($data, 200);
            
        } catch (\Exception $e) {
            $this->logger->error('Failed to get quizzes', [
                'error' => $e->getMessage()
            ]);
            
            return new WP_Error(
                'get_quizzes_failed',
                __('Failed to retrieve quizzes.', 'money-quiz'),
                ['status' => 500]
            );
        }
    }
    
    /**
     * Get single quiz.
     *
     * @param WP_REST_Request $request Request object.
     * @return WP_REST_Response|WP_Error Response.
     */
    public function get_quiz(WP_REST_Request $request) {
        try {
            $id = (int) $request->get_param('id');
            
            $quiz = $this->quiz_service->get_quiz($id, get_current_user_id());
            
            if (!$quiz) {
                return new WP_Error(
                    'quiz_not_found',
                    __('Quiz not found.', 'money-quiz'),
                    ['status' => 404]
                );
            }
            
            return new WP_REST_Response($this->prepare_quiz_response($quiz), 200);
            
        } catch (\Exception $e) {
            $this->logger->error('Failed to get quiz', [
                'quiz_id' => $request->get_param('id'),
                'error' => $e->getMessage()
            ]);
            
            return new WP_Error(
                'get_quiz_failed',
                __('Failed to retrieve quiz.', 'money-quiz'),
                ['status' => 500]
            );
        }
    }
    
    /**
     * Create quiz.
     *
     * @param WP_REST_Request $request Request object.
     * @return WP_REST_Response|WP_Error Response.
     */
    public function create_quiz(WP_REST_Request $request) {
        try {
            $data = [
                'title' => $request->get_param('title'),
                'description' => $request->get_param('description'),
                'settings' => $request->get_param('settings')
            ];
            
            $quiz = $this->quiz_service->create_quiz($data, get_current_user_id());
            
            return new WP_REST_Response($this->prepare_quiz_response($quiz), 201);
            
        } catch (\Exception $e) {
            $this->logger->error('Failed to create quiz', [
                'error' => $e->getMessage()
            ]);
            
            return new WP_Error(
                'create_quiz_failed',
                $e->getMessage(),
                ['status' => 400]
            );
        }
    }
    
    /**
     * Update quiz.
     *
     * @param WP_REST_Request $request Request object.
     * @return WP_REST_Response|WP_Error Response.
     */
    public function update_quiz(WP_REST_Request $request) {
        try {
            $id = (int) $request->get_param('id');
            $data = [];
            
            if ($request->has_param('title')) {
                $data['title'] = $request->get_param('title');
            }
            
            if ($request->has_param('description')) {
                $data['description'] = $request->get_param('description');
            }
            
            $quiz = $this->quiz_service->update_quiz($id, $data, get_current_user_id());
            
            return new WP_REST_Response($this->prepare_quiz_response($quiz), 200);
            
        } catch (\Exception $e) {
            $this->logger->error('Failed to update quiz', [
                'quiz_id' => $request->get_param('id'),
                'error' => $e->getMessage()
            ]);
            
            return new WP_Error(
                'update_quiz_failed',
                $e->getMessage(),
                ['status' => 400]
            );
        }
    }
    
    /**
     * Publish quiz.
     *
     * @param WP_REST_Request $request Request object.
     * @return WP_REST_Response|WP_Error Response.
     */
    public function publish_quiz(WP_REST_Request $request) {
        try {
            $id = (int) $request->get_param('id');
            
            $quiz = $this->quiz_service->publish_quiz($id, get_current_user_id());
            
            return new WP_REST_Response($this->prepare_quiz_response($quiz), 200);
            
        } catch (\Exception $e) {
            $this->logger->error('Failed to publish quiz', [
                'quiz_id' => $request->get_param('id'),
                'error' => $e->getMessage()
            ]);
            
            return new WP_Error(
                'publish_quiz_failed',
                $e->getMessage(),
                ['status' => 400]
            );
        }
    }
    
    /**
     * Check read permission.
     *
     * @return bool True if allowed.
     */
    public function check_read_permission(): bool {
        return true; // Public quizzes can be read by anyone
    }
    
    /**
     * Check create permission.
     *
     * @return bool True if allowed.
     */
    public function check_create_permission(): bool {
        return current_user_can('create_quiz');
    }
    
    /**
     * Check update permission.
     *
     * @return bool True if allowed.
     */
    public function check_update_permission(): bool {
        return current_user_can('edit_quiz');
    }
    
    /**
     * Check publish permission.
     *
     * @return bool True if allowed.
     */
    public function check_publish_permission(): bool {
        return current_user_can('publish_quiz');
    }
    
    /**
     * Prepare quiz response.
     *
     * @param \MoneyQuiz\Domain\Entities\Quiz $quiz Quiz entity.
     * @return array Response data.
     */
    private function prepare_quiz_response($quiz): array {
        return [
            'id' => $quiz->get_id(),
            'title' => $quiz->get_title(),
            'description' => $quiz->get_description(),
            'status' => $quiz->get_status(),
            'settings' => $quiz->get_settings()->to_array(),
            'created_by' => $quiz->get_created_by(),
            'created_at' => $quiz->get_created_at()?->format('c'),
            'updated_at' => $quiz->get_updated_at()?->format('c'),
            '_links' => [
                'self' => [
                    'href' => rest_url(self::NAMESPACE . '/quizzes/' . $quiz->get_id())
                ],
                'collection' => [
                    'href' => rest_url(self::NAMESPACE . '/quizzes')
                ]
            ]
        ];
    }
}
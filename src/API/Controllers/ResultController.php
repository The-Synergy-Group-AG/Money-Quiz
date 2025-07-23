<?php
/**
 * Result REST API Controller
 *
 * Handles result-related REST API endpoints.
 *
 * @package MoneyQuiz\API\Controllers
 * @since   7.0.0
 */

namespace MoneyQuiz\API\Controllers;

use MoneyQuiz\Application\Services\ResultCalculationService;
use MoneyQuiz\Application\Services\AttemptService;
use MoneyQuiz\Core\Logging\Logger;
use MoneyQuiz\Core\ErrorHandling\ErrorHandler;
use MoneyQuiz\Security\Authorization;
use MoneyQuiz\API\RateLimit\RateLimiter;
use WP_REST_Request;
use WP_REST_Response;
use WP_Error;

// Security: Prevent direct access.
defined('ABSPATH') || exit;

/**
 * Result controller class.
 *
 * @since 7.0.0
 */
class ResultController {
    
    /**
     * API namespace.
     *
     * @var string
     */
    private const NAMESPACE = 'money-quiz/v1';
    
    /**
     * Result calculation service.
     *
     * @var ResultCalculationService
     */
    private ResultCalculationService $result_service;
    
    /**
     * Attempt service.
     *
     * @var AttemptService
     */
    private AttemptService $attempt_service;
    
    /**
     * Authorization service.
     *
     * @var Authorization
     */
    private Authorization $authorization;
    
    /**
     * Logger instance.
     *
     * @var Logger
     */
    private Logger $logger;
    
    /**
     * Rate limiter.
     *
     * @var RateLimiter
     */
    private RateLimiter $rate_limiter;
    
    /**
     * Error handler.
     *
     * @var ErrorHandler
     */
    private ErrorHandler $error_handler;
    
    /**
     * Constructor.
     *
     * @param ResultCalculationService $result_service  Result calculation service.
     * @param AttemptService          $attempt_service Attempt service.
     * @param Authorization           $authorization   Authorization service.
     * @param Logger                  $logger          Logger instance.
     */
    public function __construct(
        ResultCalculationService $result_service,
        AttemptService $attempt_service,
        Authorization $authorization,
        Logger $logger
    ) {
        $this->result_service = $result_service;
        $this->attempt_service = $attempt_service;
        $this->authorization = $authorization;
        $this->logger = $logger;
        $this->rate_limiter = new RateLimiter($logger);
        $this->error_handler = new ErrorHandler($logger);
    }
    
    /**
     * Register routes.
     *
     * @return void
     */
    public function register_routes(): void {
        // Start quiz attempt
        register_rest_route(self::NAMESPACE, '/attempts', [
            'methods' => 'POST',
            'callback' => [$this, 'start_attempt'],
            'permission_callback' => [$this, 'check_take_permission'],
            'args' => [
                'quiz_id' => [
                    'type' => 'integer',
                    'required' => true,
                    'minimum' => 1
                ],
                'user_email' => [
                    'type' => 'string',
                    'format' => 'email'
                ]
            ]
        ]);
        
        // Submit answers
        register_rest_route(self::NAMESPACE, '/attempts/(?P<id>\d+)/answers', [
            'methods' => 'POST',
            'callback' => [$this, 'submit_answers'],
            'permission_callback' => [$this, 'check_attempt_access'],
            'args' => [
                'id' => [
                    'type' => 'integer',
                    'required' => true,
                    'minimum' => 1
                ],
                'answers' => [
                    'type' => 'array',
                    'required' => true,
                    'items' => [
                        'type' => 'object',
                        'properties' => [
                            'question_id' => [
                                'type' => 'integer',
                                'required' => true
                            ],
                            'answer_id' => [
                                'type' => 'integer'
                            ],
                            'answer_text' => [
                                'type' => 'string'
                            ]
                        ]
                    ]
                ]
            ]
        ]);
        
        // Complete attempt and calculate result
        register_rest_route(self::NAMESPACE, '/attempts/(?P<id>\d+)/complete', [
            'methods' => 'POST',
            'callback' => [$this, 'complete_attempt'],
            'permission_callback' => [$this, 'check_attempt_access'],
            'args' => [
                'id' => [
                    'type' => 'integer',
                    'required' => true,
                    'minimum' => 1
                ]
            ]
        ]);
        
        // Get result
        register_rest_route(self::NAMESPACE, '/results/(?P<id>\d+)', [
            'methods' => 'GET',
            'callback' => [$this, 'get_result'],
            'permission_callback' => [$this, 'check_result_access'],
            'args' => [
                'id' => [
                    'type' => 'integer',
                    'required' => true,
                    'minimum' => 1
                ]
            ]
        ]);
        
        // List user results
        register_rest_route(self::NAMESPACE, '/results', [
            'methods' => 'GET',
            'callback' => [$this, 'get_user_results'],
            'permission_callback' => [$this, 'check_authenticated'],
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
                'quiz_id' => [
                    'type' => 'integer',
                    'minimum' => 1
                ]
            ]
        ]);
    }
    
    /**
     * Start quiz attempt.
     *
     * @param WP_REST_Request $request Request object.
     * @return WP_REST_Response|WP_Error Response.
     */
    public function start_attempt(WP_REST_Request $request) {
        // Check rate limit
        $identifier = $this->get_rate_limit_identifier($request);
        $rate_check = $this->rate_limiter->check($identifier, 'start_attempt', 'default', $request);
        
        if (is_array($rate_check)) {
            return new WP_Error(
                $rate_check['code'],
                $rate_check['message'],
                $rate_check['data']
            );
        }
        
        // Add rate limit headers
        $headers = $this->rate_limiter->get_headers($identifier, 'start_attempt');
        foreach ($headers as $header => $value) {
            header("$header: $value");
        }
        
        try {
            $quiz_id = (int) $request->get_param('quiz_id');
            $user_email = $request->get_param('user_email');
            $user_id = get_current_user_id();
            
            // For anonymous users, validate email
            if (!$user_id && !$user_email) {
                return new WP_Error(
                    'missing_user_info',
                    __('Email address is required for anonymous users.', 'money-quiz'),
                    ['status' => 400]
                );
            }
            
            $attempt = $this->attempt_service->start_attempt(
                $quiz_id,
                $user_id ?: null,
                $user_email ?: null
            );
            
            return new WP_REST_Response([
                'id' => $attempt->get_id(),
                'quiz_id' => $attempt->get_quiz_id(),
                'status' => $attempt->get_status(),
                'started_at' => $attempt->get_started_at()->format('c'),
                'questions' => $attempt->get_questions()
            ], 201);
            
        } catch (\Exception $e) {
            $this->error_handler->log_exception($e, [
                'quiz_id' => $request->get_param('quiz_id'),
                'user_id' => $user_id
            ]);
            
            return $this->error_handler->exception_to_wp_error($e);
        }
    }
    
    /**
     * Submit answers.
     *
     * @param WP_REST_Request $request Request object.
     * @return WP_REST_Response|WP_Error Response.
     */
    public function submit_answers(WP_REST_Request $request) {
        // Check rate limit (stricter for answer submission)
        $identifier = $this->get_rate_limit_identifier($request);
        $rate_check = $this->rate_limiter->check($identifier, 'submit_answers', 'strict', $request);
        
        if (is_array($rate_check)) {
            return new WP_Error(
                $rate_check['code'],
                $rate_check['message'],
                $rate_check['data']
            );
        }
        
        // Add rate limit headers
        $headers = $this->rate_limiter->get_headers($identifier, 'submit_answers', 'strict');
        foreach ($headers as $header => $value) {
            header("$header: $value");
        }
        
        try {
            $attempt_id = (int) $request->get_param('id');
            $answers = $request->get_param('answers');
            
            $this->attempt_service->submit_answers(
                $attempt_id,
                $answers,
                get_current_user_id() ?: null
            );
            
            return new WP_REST_Response([
                'message' => __('Answers submitted successfully.', 'money-quiz')
            ], 200);
            
        } catch (\Exception $e) {
            $this->error_handler->log_exception($e, [
                'attempt_id' => $request->get_param('id')
            ]);
            
            return $this->error_handler->exception_to_wp_error($e);
        }
    }
    
    /**
     * Complete attempt and calculate result.
     *
     * @param WP_REST_Request $request Request object.
     * @return WP_REST_Response|WP_Error Response.
     */
    public function complete_attempt(WP_REST_Request $request) {
        try {
            $attempt_id = (int) $request->get_param('id');
            
            $result = $this->attempt_service->complete_attempt(
                $attempt_id,
                get_current_user_id() ?: null
            );
            
            return new WP_REST_Response([
                'result_id' => $result->get_id(),
                'score' => $result->get_score()->to_array(),
                'archetype' => $result->get_archetype()?->to_array(),
                'recommendations' => array_map(
                    fn($rec) => $rec->to_array(),
                    $result->get_recommendations()
                )
            ], 200);
            
        } catch (\Exception $e) {
            $this->logger->error('Failed to complete attempt', [
                'attempt_id' => $request->get_param('id'),
                'error' => $e->getMessage()
            ]);
            
            return new WP_Error(
                'complete_attempt_failed',
                $e->getMessage(),
                ['status' => 400]
            );
        }
    }
    
    /**
     * Get result.
     *
     * @param WP_REST_Request $request Request object.
     * @return WP_REST_Response|WP_Error Response.
     */
    public function get_result(WP_REST_Request $request) {
        try {
            $result_id = (int) $request->get_param('id');
            
            $result = $this->result_service->get_result(
                $result_id,
                get_current_user_id() ?: null
            );
            
            if (!$result) {
                return new WP_Error(
                    'result_not_found',
                    __('Result not found.', 'money-quiz'),
                    ['status' => 404]
                );
            }
            
            return new WP_REST_Response($this->prepare_result_response($result), 200);
            
        } catch (\Exception $e) {
            $this->logger->error('Failed to get result', [
                'result_id' => $request->get_param('id'),
                'error' => $e->getMessage()
            ]);
            
            return new WP_Error(
                'get_result_failed',
                __('Failed to retrieve result.', 'money-quiz'),
                ['status' => 500]
            );
        }
    }
    
    /**
     * Get user results.
     *
     * @param WP_REST_Request $request Request object.
     * @return WP_REST_Response|WP_Error Response.
     */
    public function get_user_results(WP_REST_Request $request) {
        try {
            $page = $request->get_param('page');
            $per_page = $request->get_param('per_page');
            $quiz_id = $request->get_param('quiz_id');
            $user_id = get_current_user_id();
            
            $filters = [];
            if ($quiz_id) {
                $filters['quiz_id'] = $quiz_id;
            }
            
            $offset = ($page - 1) * $per_page;
            
            $results = $this->result_service->get_user_results(
                $user_id,
                $filters,
                $per_page,
                $offset
            );
            
            $data = array_map([$this, 'prepare_result_response'], $results);
            
            return new WP_REST_Response($data, 200);
            
        } catch (\Exception $e) {
            $this->logger->error('Failed to get user results', [
                'user_id' => $user_id,
                'error' => $e->getMessage()
            ]);
            
            return new WP_Error(
                'get_user_results_failed',
                __('Failed to retrieve results.', 'money-quiz'),
                ['status' => 500]
            );
        }
    }
    
    /**
     * Check if user can take quiz.
     *
     * @return bool True if allowed.
     */
    public function check_take_permission(): bool {
        // Allow both authenticated and anonymous users
        return true;
    }
    
    /**
     * Check if user can access attempt.
     *
     * @param WP_REST_Request $request Request object.
     * @return bool|WP_Error True if allowed, error otherwise.
     */
    public function check_attempt_access(WP_REST_Request $request) {
        $attempt_id = (int) $request->get_param('id');
        $user_id = get_current_user_id();
        
        // Check if user owns the attempt or has admin access
        if ($this->authorization->can_manage_quizzes($user_id)) {
            return true;
        }
        
        // For anonymous users, check session
        // This would need to be implemented based on session handling
        
        return true; // Simplified for now
    }
    
    /**
     * Check if user can access result.
     *
     * @param WP_REST_Request $request Request object.
     * @return bool|WP_Error True if allowed, error otherwise.
     */
    public function check_result_access(WP_REST_Request $request) {
        $result_id = (int) $request->get_param('id');
        $user_id = get_current_user_id();
        
        // Check if user owns the result or has admin access
        if ($this->authorization->can_manage_quizzes($user_id)) {
            return true;
        }
        
        // For result owners, check ownership
        // This would need to be implemented
        
        return true; // Simplified for now
    }
    
    /**
     * Check if user is authenticated.
     *
     * @return bool True if authenticated.
     */
    public function check_authenticated(): bool {
        return is_user_logged_in();
    }
    
    /**
     * Prepare result response.
     *
     * @param \MoneyQuiz\Domain\Entities\Result $result Result entity.
     * @return array Prepared response.
     */
    private function prepare_result_response($result): array {
        return [
            'id' => $result->get_id(),
            'quiz_id' => $result->get_quiz_id(),
            'score' => $result->get_score()->to_array(),
            'archetype' => $result->get_archetype()?->to_array(),
            'recommendations' => array_map(
                fn($rec) => $rec->to_array(),
                $result->get_recommendations()
            ),
            'calculated_at' => $result->get_calculated_at()->format('c')
        ];
    }
    
    /**
     * Get rate limit identifier.
     *
     * @param WP_REST_Request $request Request object.
     * @return string Identifier for rate limiting.
     */
    private function get_rate_limit_identifier(WP_REST_Request $request): string {
        $user_id = get_current_user_id();
        
        if ($user_id) {
            return 'user_' . $user_id;
        }
        
        // For anonymous users, use IP address
        return 'ip_' . $this->get_client_ip();
    }
    
    /**
     * Get client IP address.
     *
     * @return string Client IP address.
     */
    private function get_client_ip(): string {
        $ip_keys = ['HTTP_X_FORWARDED_FOR', 'REMOTE_ADDR', 'HTTP_X_REAL_IP'];
        
        foreach ($ip_keys as $key) {
            if (!empty($_SERVER[$key])) {
                $ip = $_SERVER[$key];
                
                // Handle comma-separated IPs
                if (strpos($ip, ',') !== false) {
                    $ip = trim(explode(',', $ip)[0]);
                }
                
                // Validate IP
                if (filter_var($ip, FILTER_VALIDATE_IP)) {
                    return $ip;
                }
            }
        }
        
        return '0.0.0.0';
    }
}
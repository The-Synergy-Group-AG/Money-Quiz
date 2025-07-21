<?php
/**
 * Money Quiz Plugin - API Controller
 * Worker 3: MVC Implementation - REST API Controller
 * 
 * Implements REST API endpoints for the Money Quiz plugin following
 * WordPress REST API best practices.
 * 
 * @package MoneyQuiz
 * @subpackage Controllers
 * @since 4.0.0
 */

namespace MoneyQuiz\Controllers;

use MoneyQuiz\Services\QuizService;
use MoneyQuiz\Services\ValidationService;
use WP_REST_Server;
use WP_REST_Request;
use WP_REST_Response;
use WP_Error;

/**
 * API Controller Class
 * 
 * Handles REST API endpoints
 */
class ApiController extends BaseController {
    
    /**
     * REST namespace
     * 
     * @var string
     */
    const NAMESPACE = 'money-quiz/v1';
    
    /**
     * Quiz service instance
     * 
     * @var QuizService
     */
    protected $quiz_service;
    
    /**
     * Validation service instance
     * 
     * @var ValidationService
     */
    protected $validation_service;
    
    /**
     * Constructor
     * 
     * @param QuizService       $quiz_service
     * @param ValidationService $validation_service
     */
    public function __construct( QuizService $quiz_service, ValidationService $validation_service ) {
        $this->quiz_service = $quiz_service;
        $this->validation_service = $validation_service;
    }
    
    /**
     * Register REST routes
     */
    public function register_rest_routes() {
        // Quiz endpoints
        register_rest_route( self::NAMESPACE, '/quizzes', array(
            array(
                'methods' => WP_REST_Server::READABLE,
                'callback' => array( $this, 'get_quizzes' ),
                'permission_callback' => array( $this, 'public_permission' ),
                'args' => $this->get_collection_params()
            )
        ));
        
        register_rest_route( self::NAMESPACE, '/quizzes/(?P<id>\d+)', array(
            array(
                'methods' => WP_REST_Server::READABLE,
                'callback' => array( $this, 'get_quiz' ),
                'permission_callback' => array( $this, 'public_permission' ),
                'args' => array(
                    'id' => array(
                        'validate_callback' => function( $param ) {
                            return is_numeric( $param );
                        }
                    )
                )
            )
        ));
        
        // Submission endpoint
        register_rest_route( self::NAMESPACE, '/submissions', array(
            array(
                'methods' => WP_REST_Server::CREATABLE,
                'callback' => array( $this, 'create_submission' ),
                'permission_callback' => array( $this, 'public_permission' ),
                'args' => $this->get_submission_args()
            )
        ));
        
        // Results endpoints
        register_rest_route( self::NAMESPACE, '/results/(?P<id>\d+)', array(
            array(
                'methods' => WP_REST_Server::READABLE,
                'callback' => array( $this, 'get_result' ),
                'permission_callback' => array( $this, 'public_permission' ),
                'args' => array(
                    'id' => array(
                        'validate_callback' => function( $param ) {
                            return is_numeric( $param );
                        }
                    )
                )
            )
        ));
        
        // Admin endpoints
        register_rest_route( self::NAMESPACE, '/admin/questions', array(
            array(
                'methods' => WP_REST_Server::READABLE,
                'callback' => array( $this, 'get_questions' ),
                'permission_callback' => array( $this, 'admin_permission' ),
                'args' => $this->get_collection_params()
            ),
            array(
                'methods' => WP_REST_Server::CREATABLE,
                'callback' => array( $this, 'create_question' ),
                'permission_callback' => array( $this, 'admin_permission' ),
                'args' => $this->get_question_args()
            )
        ));
        
        register_rest_route( self::NAMESPACE, '/admin/questions/(?P<id>\d+)', array(
            array(
                'methods' => WP_REST_Server::EDITABLE,
                'callback' => array( $this, 'update_question' ),
                'permission_callback' => array( $this, 'admin_permission' ),
                'args' => $this->get_question_args()
            ),
            array(
                'methods' => WP_REST_Server::DELETABLE,
                'callback' => array( $this, 'delete_question' ),
                'permission_callback' => array( $this, 'admin_permission' )
            )
        ));
        
        // Statistics endpoints
        register_rest_route( self::NAMESPACE, '/admin/stats', array(
            array(
                'methods' => WP_REST_Server::READABLE,
                'callback' => array( $this, 'get_stats' ),
                'permission_callback' => array( $this, 'admin_permission' ),
                'args' => array(
                    'period' => array(
                        'default' => '7days',
                        'validate_callback' => function( $param ) {
                            return in_array( $param, array( '7days', '30days', '90days', 'all' ) );
                        }
                    )
                )
            )
        ));
        
        // Export endpoint
        register_rest_route( self::NAMESPACE, '/admin/export', array(
            array(
                'methods' => WP_REST_Server::READABLE,
                'callback' => array( $this, 'export_data' ),
                'permission_callback' => array( $this, 'admin_permission' ),
                'args' => array(
                    'type' => array(
                        'required' => true,
                        'validate_callback' => function( $param ) {
                            return in_array( $param, array( 'results', 'leads', 'questions' ) );
                        }
                    ),
                    'format' => array(
                        'default' => 'csv',
                        'validate_callback' => function( $param ) {
                            return in_array( $param, array( 'csv', 'json', 'xlsx' ) );
                        }
                    )
                )
            )
        ));
    }
    
    /**
     * Get quizzes
     * 
     * @param WP_REST_Request $request
     * @return WP_REST_Response|WP_Error
     */
    public function get_quizzes( $request ) {
        try {
            $params = $request->get_query_params();
            
            $quizzes = $this->quiz_service->get_quizzes( array(
                'page' => $params['page'],
                'per_page' => $params['per_page'],
                'orderby' => $params['orderby'],
                'order' => $params['order']
            ));
            
            $response = new WP_REST_Response( $quizzes );
            
            // Add pagination headers
            $total = $this->quiz_service->get_total_quizzes();
            $response->header( 'X-WP-Total', $total );
            $response->header( 'X-WP-TotalPages', ceil( $total / $params['per_page'] ) );
            
            return $response;
            
        } catch ( \Exception $e ) {
            return new WP_Error( 'quiz_error', $e->getMessage(), array( 'status' => 400 ) );
        }
    }
    
    /**
     * Get single quiz
     * 
     * @param WP_REST_Request $request
     * @return WP_REST_Response|WP_Error
     */
    public function get_quiz( $request ) {
        try {
            $quiz_id = $request->get_param( 'id' );
            $quiz = $this->quiz_service->get_quiz_data( $quiz_id );
            
            if ( ! $quiz ) {
                return new WP_Error( 'quiz_not_found', __( 'Quiz not found', 'money-quiz' ), array( 'status' => 404 ) );
            }
            
            return new WP_REST_Response( $quiz );
            
        } catch ( \Exception $e ) {
            return new WP_Error( 'quiz_error', $e->getMessage(), array( 'status' => 400 ) );
        }
    }
    
    /**
     * Create submission
     * 
     * @param WP_REST_Request $request
     * @return WP_REST_Response|WP_Error
     */
    public function create_submission( $request ) {
        try {
            // Validate submission data
            $data = $this->validate_submission_data( $request->get_json_params() );
            
            // Process submission
            $result_id = $this->quiz_service->process_submission( $data );
            
            // Get result data
            $result = $this->quiz_service->get_result_data( $result_id );
            
            // Return response
            return new WP_REST_Response( array(
                'success' => true,
                'result_id' => $result_id,
                'result' => $result,
                'message' => __( 'Quiz submitted successfully', 'money-quiz' )
            ), 201 );
            
        } catch ( \Exception $e ) {
            return new WP_Error( 'submission_error', $e->getMessage(), array( 'status' => 400 ) );
        }
    }
    
    /**
     * Get result
     * 
     * @param WP_REST_Request $request
     * @return WP_REST_Response|WP_Error
     */
    public function get_result( $request ) {
        try {
            $result_id = $request->get_param( 'id' );
            $result = $this->quiz_service->get_result_data( $result_id );
            
            if ( ! $result ) {
                return new WP_Error( 'result_not_found', __( 'Result not found', 'money-quiz' ), array( 'status' => 404 ) );
            }
            
            // Add archetype details
            $result['archetype'] = $this->quiz_service->get_archetype( $result['archetype_id'] );
            $result['recommendations'] = $this->quiz_service->get_recommendations( $result['archetype_id'] );
            
            return new WP_REST_Response( $result );
            
        } catch ( \Exception $e ) {
            return new WP_Error( 'result_error', $e->getMessage(), array( 'status' => 400 ) );
        }
    }
    
    /**
     * Get questions (admin)
     * 
     * @param WP_REST_Request $request
     * @return WP_REST_Response|WP_Error
     */
    public function get_questions( $request ) {
        try {
            $params = $request->get_query_params();
            
            $questions = $this->quiz_service->get_questions( array(
                'page' => $params['page'],
                'per_page' => $params['per_page'],
                'orderby' => $params['orderby'],
                'order' => $params['order'],
                'category' => isset( $params['category'] ) ? $params['category'] : ''
            ));
            
            return new WP_REST_Response( $questions );
            
        } catch ( \Exception $e ) {
            return new WP_Error( 'questions_error', $e->getMessage(), array( 'status' => 400 ) );
        }
    }
    
    /**
     * Create question (admin)
     * 
     * @param WP_REST_Request $request
     * @return WP_REST_Response|WP_Error
     */
    public function create_question( $request ) {
        try {
            $data = $request->get_json_params();
            $question_id = $this->quiz_service->create_question( $data );
            
            return new WP_REST_Response( array(
                'id' => $question_id,
                'message' => __( 'Question created successfully', 'money-quiz' )
            ), 201 );
            
        } catch ( \Exception $e ) {
            return new WP_Error( 'create_error', $e->getMessage(), array( 'status' => 400 ) );
        }
    }
    
    /**
     * Update question (admin)
     * 
     * @param WP_REST_Request $request
     * @return WP_REST_Response|WP_Error
     */
    public function update_question( $request ) {
        try {
            $question_id = $request->get_param( 'id' );
            $data = $request->get_json_params();
            
            $this->quiz_service->update_question( $question_id, $data );
            
            return new WP_REST_Response( array(
                'message' => __( 'Question updated successfully', 'money-quiz' )
            ));
            
        } catch ( \Exception $e ) {
            return new WP_Error( 'update_error', $e->getMessage(), array( 'status' => 400 ) );
        }
    }
    
    /**
     * Delete question (admin)
     * 
     * @param WP_REST_Request $request
     * @return WP_REST_Response|WP_Error
     */
    public function delete_question( $request ) {
        try {
            $question_id = $request->get_param( 'id' );
            $this->quiz_service->delete_question( $question_id );
            
            return new WP_REST_Response( array(
                'message' => __( 'Question deleted successfully', 'money-quiz' )
            ));
            
        } catch ( \Exception $e ) {
            return new WP_Error( 'delete_error', $e->getMessage(), array( 'status' => 400 ) );
        }
    }
    
    /**
     * Get statistics (admin)
     * 
     * @param WP_REST_Request $request
     * @return WP_REST_Response|WP_Error
     */
    public function get_stats( $request ) {
        try {
            $period = $request->get_param( 'period' );
            $stats = $this->quiz_service->get_statistics( $period );
            
            return new WP_REST_Response( $stats );
            
        } catch ( \Exception $e ) {
            return new WP_Error( 'stats_error', $e->getMessage(), array( 'status' => 400 ) );
        }
    }
    
    /**
     * Export data (admin)
     * 
     * @param WP_REST_Request $request
     * @return WP_REST_Response|WP_Error
     */
    public function export_data( $request ) {
        try {
            $type = $request->get_param( 'type' );
            $format = $request->get_param( 'format' );
            
            $export_url = $this->quiz_service->export_data( $type, array(
                'format' => $format,
                'start_date' => $request->get_param( 'start_date' ),
                'end_date' => $request->get_param( 'end_date' )
            ));
            
            return new WP_REST_Response( array(
                'download_url' => $export_url,
                'message' => __( 'Export generated successfully', 'money-quiz' )
            ));
            
        } catch ( \Exception $e ) {
            return new WP_Error( 'export_error', $e->getMessage(), array( 'status' => 400 ) );
        }
    }
    
    /**
     * Public permission callback
     * 
     * @return bool
     */
    public function public_permission() {
        return true;
    }
    
    /**
     * Admin permission callback
     * 
     * @return bool
     */
    public function admin_permission() {
        return current_user_can( 'manage_options' );
    }
    
    /**
     * Get collection parameters
     * 
     * @return array
     */
    protected function get_collection_params() {
        return array(
            'page' => array(
                'description' => __( 'Current page of the collection.', 'money-quiz' ),
                'type' => 'integer',
                'default' => 1,
                'sanitize_callback' => 'absint',
                'validate_callback' => 'rest_validate_request_arg',
                'minimum' => 1
            ),
            'per_page' => array(
                'description' => __( 'Maximum number of items to be returned in result set.', 'money-quiz' ),
                'type' => 'integer',
                'default' => 10,
                'minimum' => 1,
                'maximum' => 100,
                'sanitize_callback' => 'absint',
                'validate_callback' => 'rest_validate_request_arg'
            ),
            'orderby' => array(
                'description' => __( 'Sort collection by attribute.', 'money-quiz' ),
                'type' => 'string',
                'default' => 'date',
                'enum' => array( 'date', 'id', 'title', 'menu_order' ),
                'validate_callback' => 'rest_validate_request_arg'
            ),
            'order' => array(
                'description' => __( 'Order sort attribute ascending or descending.', 'money-quiz' ),
                'type' => 'string',
                'default' => 'desc',
                'enum' => array( 'asc', 'desc' ),
                'validate_callback' => 'rest_validate_request_arg'
            )
        );
    }
    
    /**
     * Get submission arguments
     * 
     * @return array
     */
    protected function get_submission_args() {
        return array(
            'quiz_id' => array(
                'required' => true,
                'type' => 'integer',
                'validate_callback' => function( $param ) {
                    return is_numeric( $param ) && $param > 0;
                }
            ),
            'answers' => array(
                'required' => true,
                'type' => 'object',
                'validate_callback' => function( $param ) {
                    return is_array( $param ) && ! empty( $param );
                }
            ),
            'email' => array(
                'type' => 'string',
                'format' => 'email',
                'validate_callback' => function( $param ) {
                    return empty( $param ) || is_email( $param );
                }
            ),
            'first_name' => array(
                'type' => 'string',
                'sanitize_callback' => 'sanitize_text_field'
            ),
            'last_name' => array(
                'type' => 'string',
                'sanitize_callback' => 'sanitize_text_field'
            ),
            'phone' => array(
                'type' => 'string',
                'sanitize_callback' => 'sanitize_text_field'
            )
        );
    }
    
    /**
     * Get question arguments
     * 
     * @return array
     */
    protected function get_question_args() {
        return array(
            'question' => array(
                'required' => true,
                'type' => 'string',
                'sanitize_callback' => 'sanitize_text_field'
            ),
            'category' => array(
                'required' => true,
                'type' => 'string',
                'sanitize_callback' => 'sanitize_text_field'
            ),
            'answers' => array(
                'required' => true,
                'type' => 'array',
                'items' => array(
                    'type' => 'string',
                    'sanitize_callback' => 'sanitize_text_field'
                )
            ),
            'weights' => array(
                'required' => true,
                'type' => 'array',
                'items' => array(
                    'type' => 'integer',
                    'minimum' => 0,
                    'maximum' => 10
                )
            ),
            'order' => array(
                'type' => 'integer',
                'default' => 0,
                'sanitize_callback' => 'absint'
            )
        );
    }
    
    /**
     * Validate submission data
     * 
     * @param array $data
     * @return array
     * @throws \Exception
     */
    protected function validate_submission_data( $data ) {
        // Basic validation is handled by REST API args
        // Additional business logic validation here
        
        $quiz_id = $data['quiz_id'];
        $questions = $this->quiz_service->get_quiz_questions( $quiz_id );
        
        // Ensure all questions are answered
        foreach ( $questions as $question ) {
            if ( ! isset( $data['answers'][ $question['id'] ] ) ) {
                throw new \Exception(
                    sprintf( __( 'Missing answer for question: %s', 'money-quiz' ), $question['text'] )
                );
            }
        }
        
        // Add metadata
        $data['ip_address'] = $this->get_client_ip();
        $data['user_agent'] = isset( $_SERVER['HTTP_USER_AGENT'] ) ? $_SERVER['HTTP_USER_AGENT'] : '';
        
        return $data;
    }
}
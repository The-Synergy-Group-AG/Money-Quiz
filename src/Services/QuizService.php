<?php
/**
 * Quiz Service
 *
 * @package MoneyQuiz
 * @since 4.0.0
 */

namespace MoneyQuiz\Services;

use MoneyQuiz\Database\Repositories\QuizRepository;
use MoneyQuiz\Exceptions\QuizException;

/**
 * Business logic for quiz operations
 */
class QuizService {
    
    /**
     * @var QuizRepository
     */
    private QuizRepository $quiz_repository;
    
    /**
     * @var mixed|null
     */
    private $archetype_repository;
    
    /**
     * @var CacheService
     */
    private CacheService $cache;
    
    /**
     * Constructor
     * 
     * @param QuizRepository      $quiz_repository
     * @param mixed|null          $archetype_repository
     * @param CacheService        $cache
     */
    public function __construct(
        QuizRepository $quiz_repository,
        $archetype_repository,
        CacheService $cache
    ) {
        $this->quiz_repository = $quiz_repository;
        $this->archetype_repository = $archetype_repository;
        $this->cache = $cache;
    }
    
    /**
     * Get quiz by ID or slug
     * 
     * @param int|string $identifier Quiz ID or slug
     * @return object|null
     */
    public function get_quiz( $identifier ): ?object {
        $cache_key = 'quiz_' . $identifier;
        
        return $this->cache->remember( $cache_key, function() use ( $identifier ) {
            if ( is_numeric( $identifier ) ) {
                // Try new structure first
                $quiz = $this->quiz_repository->get_with_questions( (int) $identifier );
                if ( $quiz ) {
                    return $quiz;
                }
                // Fall back to legacy
                return $this->get_legacy_quiz( (int) $identifier );
            }
            
            $quiz = $this->quiz_repository->find_by_slug( $identifier );
            return $quiz ? $this->quiz_repository->get_with_questions( $quiz->id ) : null;
        }, 3600 ); // Cache for 1 hour
    }
    
    /**
     * Process quiz submission
     * 
     * @param int   $quiz_id Quiz ID
     * @param array $answers User answers
     * @param array $user_data Optional user data
     * @return array
     * @throws QuizException
     */
    public function process_submission( int $quiz_id, array $answers, array $user_data = [] ): array {
        $quiz = $this->get_quiz( $quiz_id );
        
        if ( ! $quiz ) {
            throw new QuizException( 'Quiz not found.' );
        }
        
        // Validate answers
        $this->validate_answers( $quiz, $answers );
        
        // Calculate archetype scores
        $archetype_scores = $this->calculate_archetype_scores( $quiz, $answers );
        
        // Determine dominant archetype
        $dominant_archetype_id = $this->get_dominant_archetype( $archetype_scores );
        
        // Calculate overall score
        $overall_score = $this->calculate_overall_score( $archetype_scores );
        
        // Save result
        $result = $this->save_result( [
            'quiz_id' => $quiz_id,
            'user_id' => get_current_user_id() ?: null,
            'archetype_id' => $dominant_archetype_id,
            'answers' => json_encode( $answers ),
            'score' => $overall_score,
            'ip_address' => $this->get_client_ip(),
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? '',
            'completed_at' => current_time( 'mysql' ),
        ] );
        
        // Process user data if provided
        if ( ! empty( $user_data['email'] ) ) {
            $this->process_prospect( $result['id'], $user_data );
        }
        
        // Clear quiz cache
        $this->cache->delete( 'quiz_' . $quiz_id );
        
        return $result;
    }
    
    /**
     * Validate quiz answers
     * 
     * @param object $quiz Quiz object
     * @param array  $answers User answers
     * @throws QuizException
     */
    private function validate_answers( object $quiz, array $answers ): void {
        foreach ( $quiz->questions as $question ) {
            if ( $question->is_required && ! isset( $answers[ $question->id ] ) ) {
                throw new QuizException( 
                    sprintf( 'Question %d is required.', $question->id ) 
                );
            }
            
            if ( isset( $answers[ $question->id ] ) ) {
                $this->validate_answer_format( $question, $answers[ $question->id ] );
            }
        }
    }
    
    /**
     * Validate answer format
     * 
     * @param object $question Question object
     * @param mixed  $answer User answer
     * @throws QuizException
     */
    private function validate_answer_format( object $question, $answer ): void {
        switch ( $question->question_type ) {
            case 'single_choice':
                if ( ! is_string( $answer ) && ! is_numeric( $answer ) ) {
                    throw new QuizException( 'Invalid answer format for single choice question.' );
                }
                break;
                
            case 'multiple_choice':
                if ( ! is_array( $answer ) ) {
                    throw new QuizException( 'Invalid answer format for multiple choice question.' );
                }
                break;
                
            case 'scale':
                if ( ! is_numeric( $answer ) ) {
                    throw new QuizException( 'Invalid answer format for scale question.' );
                }
                break;
        }
    }
    
    /**
     * Calculate archetype scores
     * 
     * @param object $quiz Quiz object
     * @param array  $answers User answers
     * @return array
     */
    private function calculate_archetype_scores( object $quiz, array $answers ): array {
        $scores = [];
        
        foreach ( $quiz->questions as $question ) {
            if ( ! isset( $answers[ $question->id ] ) ) {
                continue;
            }
            
            $answer = $answers[ $question->id ];
            $weights = $question->archetype_weights;
            
            // Apply weights based on answer
            foreach ( $weights as $archetype_id => $weight_data ) {
                if ( ! isset( $scores[ $archetype_id ] ) ) {
                    $scores[ $archetype_id ] = 0;
                }
                
                $scores[ $archetype_id ] += $this->calculate_answer_weight( 
                    $question, 
                    $answer, 
                    $weight_data 
                );
            }
        }
        
        // Normalize scores
        $max_score = max( $scores ) ?: 1;
        foreach ( $scores as &$score ) {
            $score = ( $score / $max_score ) * 100;
        }
        
        return $scores;
    }
    
    /**
     * Calculate weight for a specific answer
     * 
     * @param object $question Question object
     * @param mixed  $answer User answer
     * @param mixed  $weight_data Weight configuration
     * @return float
     */
    private function calculate_answer_weight( object $question, $answer, $weight_data ): float {
        if ( is_numeric( $weight_data ) ) {
            return (float) $weight_data;
        }
        
        if ( is_array( $weight_data ) && isset( $weight_data[ $answer ] ) ) {
            return (float) $weight_data[ $answer ];
        }
        
        return 0.0;
    }
    
    /**
     * Get dominant archetype from scores
     * 
     * @param array $scores Archetype scores
     * @return int
     */
    private function get_dominant_archetype( array $scores ): int {
        arsort( $scores );
        return (int) key( $scores );
    }
    
    /**
     * Calculate overall score
     * 
     * @param array $scores Archetype scores
     * @return float
     */
    private function calculate_overall_score( array $scores ): float {
        return round( array_sum( $scores ) / count( $scores ), 2 );
    }
    
    /**
     * Save quiz result
     * 
     * @param array $data Result data
     * @return array
     */
    private function save_result( array $data ): array {
        global $wpdb;
        
        $wpdb->insert(
            $wpdb->prefix . 'money_quiz_results',
            $data,
            [ '%d', '%d', '%d', '%s', '%f', '%s', '%s', '%s' ]
        );
        
        $result_id = $wpdb->insert_id;
        
        // Return result as array instead of QuizResult object
        $result = [
            'id' => $result_id,
            'quiz_id' => $data['quiz_id'],
            'archetype_id' => $data['archetype_id'],
            'score' => $data['score'],
        ];
        
        // Get archetype data if repository is available
        if ( $this->archetype_repository ) {
            $result['archetype'] = $this->archetype_repository->find( $data['archetype_id'] );
        }
        
        return $result;
    }
    
    /**
     * Process prospect data
     * 
     * @param int   $result_id Result ID
     * @param array $user_data User data
     * @return void
     */
    private function process_prospect( int $result_id, array $user_data ): void {
        global $wpdb;
        
        $prospect_data = [
            'result_id' => $result_id,
            'email' => sanitize_email( $user_data['email'] ),
            'first_name' => sanitize_text_field( $user_data['first_name'] ?? '' ),
            'last_name' => sanitize_text_field( $user_data['last_name'] ?? '' ),
            'phone' => sanitize_text_field( $user_data['phone'] ?? '' ),
            'company' => sanitize_text_field( $user_data['company'] ?? '' ),
            'consent_marketing' => ! empty( $user_data['consent_marketing'] ) ? 1 : 0,
            'consent_terms' => ! empty( $user_data['consent_terms'] ) ? 1 : 0,
            'gdpr_consent_date' => ! empty( $user_data['consent_terms'] ) ? current_time( 'mysql' ) : null,
        ];
        
        // Check if email already exists
        $existing = $wpdb->get_var( $wpdb->prepare(
            "SELECT id FROM {$wpdb->prefix}money_quiz_prospects WHERE email = %s",
            $prospect_data['email']
        ) );
        
        if ( $existing ) {
            // Update existing prospect
            $wpdb->update(
                $wpdb->prefix . 'money_quiz_prospects',
                $prospect_data,
                [ 'id' => $existing ]
            );
        } else {
            // Insert new prospect
            $wpdb->insert(
                $wpdb->prefix . 'money_quiz_prospects',
                $prospect_data
            );
        }
        
        // Trigger email notifications
        do_action( 'money_quiz_prospect_created', $result_id, $prospect_data );
    }
    
    /**
     * Get client IP address
     * 
     * @return string
     */
    private function get_client_ip(): string {
        $ip_keys = [ 'HTTP_CF_CONNECTING_IP', 'HTTP_CLIENT_IP', 'HTTP_X_FORWARDED_FOR', 'REMOTE_ADDR' ];
        
        foreach ( $ip_keys as $key ) {
            if ( ! empty( $_SERVER[ $key ] ) ) {
                $ip = filter_var( $_SERVER[ $key ], FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE );
                if ( $ip !== false ) {
                    return $ip;
                }
            }
        }
        
        return '0.0.0.0';
    }
    
    /**
     * Get quiz statistics
     * 
     * @param int $quiz_id Quiz ID
     * @return array
     */
    public function get_statistics( int $quiz_id ): array {
        $cache_key = 'quiz_stats_' . $quiz_id;
        
        return $this->cache->remember( $cache_key, function() use ( $quiz_id ) {
            return $this->quiz_repository->get_statistics( $quiz_id );
        }, 3600 ); // Cache for 1 hour
    }
    
    /**
     * Get quiz from legacy tables
     * 
     * @param int $quiz_id Quiz ID
     * @return object|null
     */
    private function get_legacy_quiz( int $quiz_id ): ?object {
        global $wpdb;
        
        // For legacy, quiz_id is always 1 (single quiz system)
        $table_master = $wpdb->prefix . 'mq_master';
        $table_answers = $wpdb->prefix . 'mq_answer_master';
        
        // Check if legacy tables exist
        $table_exists = $wpdb->get_var( 
            "SHOW TABLES LIKE '{$table_master}'"
        );
        
        if ( ! $table_exists ) {
            return null;
        }
        
        // Get questions from legacy table
        $questions = $wpdb->get_results( 
            "SELECT * FROM {$table_master} WHERE Status = 'Active' ORDER BY Master_ID ASC" 
        );
        
        if ( empty( $questions ) ) {
            return null;
        }
        
        // Format as modern quiz object
        $quiz = new \stdClass();
        $quiz->id = 1; // Legacy has single quiz
        $quiz->title = get_option( 'money_quiz_title', __( 'Money Quiz', 'money-quiz' ) );
        $quiz->description = get_option( 'money_quiz_description', '' );
        $quiz->questions = [];
        
        foreach ( $questions as $question ) {
            $q = new \stdClass();
            $q->id = $question->Master_ID;
            $q->text = $question->Question;
            $q->archetype = $question->Archetype;
            $q->options = [];
            
            // Get answers for this question
            $answers = $wpdb->get_results( 
                $wpdb->prepare(
                    "SELECT * FROM {$table_answers} 
                    WHERE Question_ID = %d AND Status = 'Active' 
                    ORDER BY Answer_ID ASC",
                    $question->Master_ID
                )
            );
            
            foreach ( $answers as $answer ) {
                $option = new \stdClass();
                $option->id = $answer->Answer_ID;
                $option->value = $answer->Answer_Value;
                $option->label = $answer->Answer_Label;
                $q->options[] = $option;
            }
            
            $quiz->questions[] = $q;
        }
        
        return $quiz;
    }
}
<?php
/**
 * Money Quiz Plugin - Quiz and Validation Services
 * Worker 6: Service Layer - Quiz Logic and Validation
 * 
 * Implements core quiz functionality and comprehensive validation
 * services for the Money Quiz plugin.
 * 
 * @package MoneyQuiz
 * @subpackage Services
 * @since 4.0.0
 */

namespace MoneyQuiz\Services;

use Exception;

/**
 * Quiz Service Class
 * 
 * Handles all quiz-related business logic
 */
class QuizService {
    
    /**
     * Database service instance
     * 
     * @var DatabaseService
     */
    protected $database;
    
    /**
     * Validation service instance
     * 
     * @var ValidationService
     */
    protected $validation;
    
    /**
     * Cache for quiz data
     * 
     * @var array
     */
    protected $cache = array();
    
    /**
     * Constructor
     * 
     * @param DatabaseService   $database
     * @param ValidationService $validation
     */
    public function __construct( DatabaseService $database, ValidationService $validation ) {
        $this->database = $database;
        $this->validation = $validation;
    }
    
    /**
     * Get quiz data
     * 
     * @param int    $quiz_id Quiz ID (default quiz if 0)
     * @param string $version Quiz version
     * @return array
     */
    public function get_quiz_data( $quiz_id = 0, $version = 'default' ) {
        $cache_key = "quiz_{$quiz_id}_{$version}";
        
        if ( isset( $this->cache[ $cache_key ] ) ) {
            return $this->cache[ $cache_key ];
        }
        
        // For now, we have a single quiz
        $quiz_data = array(
            'id' => 1,
            'title' => get_option( 'money_quiz_title', __( 'Money Personality Quiz', 'money-quiz' ) ),
            'description' => get_option( 'money_quiz_description', '' ),
            'questions' => $this->get_quiz_questions( $quiz_id ),
            'settings' => array(
                'show_progress' => get_option( 'money_quiz_show_progress', 'yes' ),
                'randomize_questions' => get_option( 'money_quiz_randomize', 'no' ),
                'require_email' => get_option( 'money_quiz_require_email', 'yes' ),
                'collect_name' => get_option( 'money_quiz_collect_name', 'yes' ),
                'collect_phone' => get_option( 'money_quiz_collect_phone', 'no' )
            )
        );
        
        $this->cache[ $cache_key ] = $quiz_data;
        return $quiz_data;
    }
    
    /**
     * Get quiz questions
     * 
     * @param int $quiz_id Quiz ID
     * @return array
     */
    public function get_quiz_questions( $quiz_id = 0 ) {
        $questions = $this->database->get_results( 'questions', array(
            'where' => array( 'Is_Active' => 1 ),
            'orderby' => 'Display_Order',
            'order' => 'ASC'
        ));
        
        // Format questions
        $formatted = array();
        foreach ( $questions as $question ) {
            $formatted[] = array(
                'id' => $question->Question_ID,
                'text' => $question->Question_Text,
                'category' => $question->Question_Category,
                'type' => $question->Question_Type,
                'answers' => $this->get_answer_scale()
            );
        }
        
        return $formatted;
    }
    
    /**
     * Get answer scale
     * 
     * @return array
     */
    protected function get_answer_scale() {
        return array(
            1 => __( 'Strongly Disagree', 'money-quiz' ),
            2 => __( 'Disagree', 'money-quiz' ),
            3 => __( 'Somewhat Disagree', 'money-quiz' ),
            4 => __( 'Neutral', 'money-quiz' ),
            5 => __( 'Somewhat Agree', 'money-quiz' ),
            6 => __( 'Agree', 'money-quiz' ),
            7 => __( 'Strongly Agree', 'money-quiz' ),
            8 => __( 'Completely Agree', 'money-quiz' )
        );
    }
    
    /**
     * Process quiz submission
     * 
     * @param array $submission_data Validated submission data
     * @return int Result ID
     * @throws Exception
     */
    public function process_submission( $submission_data ) {
        $this->database->start_transaction();
        
        try {
            // Create or update prospect
            $prospect_id = $this->create_or_update_prospect( $submission_data );
            
            // Create quiz taken record
            $taken_id = $this->database->insert( 'taken', array(
                'Prospect_ID' => $prospect_id,
                'Quiz_ID' => $submission_data['quiz_id'] ?? 1,
                'Started' => current_time( 'mysql' ),
                'Status' => 'incomplete'
            ));
            
            if ( ! $taken_id ) {
                throw new Exception( __( 'Failed to create quiz record', 'money-quiz' ) );
            }
            
            // Process answers and calculate scores
            $scores = $this->process_answers( $taken_id, $prospect_id, $submission_data['answers'] );
            
            // Determine archetype
            $archetype_id = $this->determine_archetype( $scores['total'] );
            
            // Update taken record with results
            $this->database->update( 'taken', 
                array(
                    'Completed' => current_time( 'mysql' ),
                    'Score_Total' => $scores['total'],
                    'Archetype_ID' => $archetype_id,
                    'Status' => 'completed'
                ),
                array( 'Taken_ID' => $taken_id )
            );
            
            // Log activity
            $this->log_activity( 'quiz_completed', array(
                'prospect_id' => $prospect_id,
                'taken_id' => $taken_id,
                'archetype_id' => $archetype_id,
                'score' => $scores['total']
            ));
            
            $this->database->commit();
            
            // Trigger completion action
            do_action( 'money_quiz_completed', $taken_id, $prospect_id, $archetype_id );
            
            return $taken_id;
            
        } catch ( Exception $e ) {
            $this->database->rollback();
            throw $e;
        }
    }
    
    /**
     * Create or update prospect
     * 
     * @param array $data Submission data
     * @return int Prospect ID
     * @throws Exception
     */
    protected function create_or_update_prospect( $data ) {
        $email = $data['email'] ?? '';
        
        if ( empty( $email ) ) {
            // Create anonymous prospect
            $prospect_id = $this->database->insert( 'prospects', array(
                'Email' => 'anonymous_' . uniqid() . '@example.com',
                'IP_Address' => $data['ip_address'] ?? '',
                'User_Agent' => $data['user_agent'] ?? ''
            ));
        } else {
            // Check if prospect exists
            $existing = $this->database->get_row( 'prospects', array(
                'Email' => $email
            ));
            
            if ( $existing ) {
                // Update existing prospect
                $this->database->update( 'prospects',
                    array(
                        'FirstName' => $data['first_name'] ?? $existing->FirstName,
                        'LastName' => $data['last_name'] ?? $existing->LastName,
                        'Phone' => $data['phone'] ?? $existing->Phone,
                        'Updated' => current_time( 'mysql' )
                    ),
                    array( 'Prospect_ID' => $existing->Prospect_ID )
                );
                
                $prospect_id = $existing->Prospect_ID;
            } else {
                // Create new prospect
                $prospect_id = $this->database->insert( 'prospects', array(
                    'Email' => $email,
                    'FirstName' => $data['first_name'] ?? '',
                    'LastName' => $data['last_name'] ?? '',
                    'Phone' => $data['phone'] ?? '',
                    'IP_Address' => $data['ip_address'] ?? '',
                    'User_Agent' => $data['user_agent'] ?? '',
                    'Referrer' => $data['referrer'] ?? ''
                ));
            }
        }
        
        if ( ! $prospect_id ) {
            throw new Exception( __( 'Failed to create prospect record', 'money-quiz' ) );
        }
        
        return $prospect_id;
    }
    
    /**
     * Process quiz answers
     * 
     * @param int   $taken_id Taken ID
     * @param int   $prospect_id Prospect ID
     * @param array $answers Answer data
     * @return array Scores
     */
    protected function process_answers( $taken_id, $prospect_id, $answers ) {
        $total_score = 0;
        $category_scores = array();
        
        foreach ( $answers as $question_id => $answer_value ) {
            // Get question details
            $question = $this->database->get_row( 'questions', array(
                'Question_ID' => $question_id
            ));
            
            if ( ! $question ) {
                continue;
            }
            
            // Calculate weighted score
            $weight = 1.0; // Could be customized per question
            $score = $answer_value * $weight;
            
            // Insert result record
            $this->database->insert( 'results', array(
                'Taken_ID' => $taken_id,
                'Prospect_ID' => $prospect_id,
                'Question_ID' => $question_id,
                'Answer_Value' => $answer_value,
                'Weight' => $weight
            ));
            
            // Update scores
            $total_score += $score;
            
            if ( $question->Question_Category ) {
                if ( ! isset( $category_scores[ $question->Question_Category ] ) ) {
                    $category_scores[ $question->Question_Category ] = 0;
                }
                $category_scores[ $question->Question_Category ] += $score;
            }
        }
        
        return array(
            'total' => $total_score,
            'categories' => $category_scores
        );
    }
    
    /**
     * Determine archetype based on score
     * 
     * @param float $score Total score
     * @return int Archetype ID
     */
    protected function determine_archetype( $score ) {
        $archetype = $this->database->get_row( 'archetypes', array(
            'where' => array( 'Is_Active' => 1 ),
            'orderby' => 'Score_Range_Min',
            'order' => 'ASC'
        ), ARRAY_A );
        
        // Find matching archetype based on score range
        $archetypes = $this->database->get_results( 'archetypes', array(
            'where' => array( 'Is_Active' => 1 ),
            'orderby' => 'Score_Range_Min',
            'order' => 'ASC'
        ));
        
        foreach ( $archetypes as $archetype ) {
            if ( $score >= $archetype->Score_Range_Min && $score <= $archetype->Score_Range_Max ) {
                return $archetype->Archetype_ID;
            }
        }
        
        // Default to first archetype if no match
        return $archetypes[0]->Archetype_ID ?? 1;
    }
    
    /**
     * Get result data
     * 
     * @param int $result_id Result/Taken ID
     * @return array|null
     */
    public function get_result_data( $result_id ) {
        $taken = $this->database->get_row( 'taken', array(
            'Taken_ID' => $result_id
        ));
        
        if ( ! $taken ) {
            return null;
        }
        
        $prospect = $this->database->get_row( 'prospects', array(
            'Prospect_ID' => $taken->Prospect_ID
        ));
        
        $archetype = $this->database->get_row( 'archetypes', array(
            'Archetype_ID' => $taken->Archetype_ID
        ));
        
        // Get individual answers
        $answers = $this->database->get_results( 'results', array(
            'where' => array( 'Taken_ID' => $result_id ),
            'orderby' => 'Question_ID'
        ));
        
        return array(
            'result_id' => $result_id,
            'quiz_id' => $taken->Quiz_ID,
            'completed_date' => $taken->Completed,
            'score' => $taken->Score_Total,
            'archetype_id' => $taken->Archetype_ID,
            'archetype' => array(
                'id' => $archetype->Archetype_ID,
                'name' => $archetype->Name,
                'description' => $archetype->Description,
                'recommendations' => $archetype->Recommendations,
                'color' => $archetype->Color,
                'icon' => $archetype->Icon
            ),
            'prospect' => array(
                'email' => $prospect->Email,
                'first_name' => $prospect->FirstName,
                'last_name' => $prospect->LastName,
                'phone' => $prospect->Phone
            ),
            'answers' => $answers
        );
    }
    
    /**
     * Get statistics
     * 
     * @param string $period Time period
     * @return array
     */
    public function get_statistics( $period = '7days' ) {
        $stats = array();
        
        // Calculate date range
        $end_date = current_time( 'mysql' );
        switch ( $period ) {
            case '7days':
                $start_date = date( 'Y-m-d H:i:s', strtotime( '-7 days' ) );
                break;
            case '30days':
                $start_date = date( 'Y-m-d H:i:s', strtotime( '-30 days' ) );
                break;
            case '90days':
                $start_date = date( 'Y-m-d H:i:s', strtotime( '-90 days' ) );
                break;
            default:
                $start_date = '2000-01-01 00:00:00';
        }
        
        // Total quizzes taken
        $stats['total_quizzes'] = $this->database->count( 'taken', array(
            'Status' => 'completed'
        ));
        
        // Quizzes in period
        $stats['period_quizzes'] = $this->database->query(
            "SELECT COUNT(*) FROM {$this->database->get_table('taken')} 
             WHERE Status = 'completed' AND Completed BETWEEN %s AND %s",
            array( $start_date, $end_date )
        );
        
        // Total leads
        $stats['total_leads'] = $this->database->count( 'prospects' );
        
        // Conversion rate
        $total_started = $this->database->count( 'taken' );
        $total_completed = $stats['total_quizzes'];
        $stats['conversion_rate'] = $total_started > 0 
            ? round( ( $total_completed / $total_started ) * 100, 2 ) 
            : 0;
        
        // Archetype distribution
        $stats['archetype_distribution'] = $this->get_archetype_distribution();
        
        // Average score
        $avg_score = $this->database->get_var( 'taken', 'AVG(Score_Total)', array(
            'Status' => 'completed'
        ));
        $stats['average_score'] = round( $avg_score, 2 );
        
        return $stats;
    }
    
    /**
     * Get archetype distribution
     * 
     * @return array
     */
    public function get_archetype_distribution() {
        $results = $this->database->query(
            "SELECT a.Name, COUNT(t.Taken_ID) as count
             FROM {$this->database->get_table('archetypes')} a
             LEFT JOIN {$this->database->get_table('taken')} t ON a.Archetype_ID = t.Archetype_ID
             WHERE t.Status = 'completed'
             GROUP BY a.Archetype_ID
             ORDER BY count DESC"
        );
        
        $distribution = array();
        foreach ( $results as $result ) {
            $distribution[] = array(
                'name' => $result->Name,
                'count' => $result->count,
                'percentage' => 0 // Calculate after getting total
            );
        }
        
        // Calculate percentages
        $total = array_sum( array_column( $distribution, 'count' ) );
        if ( $total > 0 ) {
            foreach ( $distribution as &$item ) {
                $item['percentage'] = round( ( $item['count'] / $total ) * 100, 2 );
            }
        }
        
        return $distribution;
    }
    
    /**
     * Log activity
     * 
     * @param string $action Action name
     * @param array  $data Activity data
     */
    protected function log_activity( $action, $data ) {
        $this->database->insert( 'activity_log', array(
            'Action' => $action,
            'Data' => json_encode( $data ),
            'IP_Address' => $_SERVER['REMOTE_ADDR'] ?? '',
            'User_Agent' => $_SERVER['HTTP_USER_AGENT'] ?? '',
            'Created' => current_time( 'mysql' )
        ));
    }
    
    /**
     * Export data
     * 
     * @param string $type Export type
     * @param array  $options Export options
     * @return string Export file URL
     * @throws Exception
     */
    public function export_data( $type, $options = array() ) {
        $data = array();
        
        switch ( $type ) {
            case 'results':
                $data = $this->export_results( $options );
                break;
            case 'leads':
                $data = $this->export_leads( $options );
                break;
            case 'questions':
                $data = $this->export_questions( $options );
                break;
            default:
                throw new Exception( __( 'Invalid export type', 'money-quiz' ) );
        }
        
        // Generate export file
        $format = $options['format'] ?? 'csv';
        $filename = $this->generate_export_file( $data, $type, $format );
        
        return $filename;
    }
    
    /**
     * Export results data
     * 
     * @param array $options Export options
     * @return array
     */
    protected function export_results( $options ) {
        $where = array( 'Status' => 'completed' );
        
        // Add date range filter
        if ( ! empty( $options['start_date'] ) && ! empty( $options['end_date'] ) ) {
            // This would need custom WHERE clause building
        }
        
        $results = $this->database->get_results( 'taken', array(
            'where' => $where,
            'orderby' => 'Completed',
            'order' => 'DESC'
        ));
        
        $export_data = array();
        foreach ( $results as $result ) {
            $prospect = $this->database->get_row( 'prospects', array(
                'Prospect_ID' => $result->Prospect_ID
            ));
            
            $archetype = $this->database->get_row( 'archetypes', array(
                'Archetype_ID' => $result->Archetype_ID
            ));
            
            $export_data[] = array(
                'Date' => $result->Completed,
                'Email' => $prospect->Email,
                'First Name' => $prospect->FirstName,
                'Last Name' => $prospect->LastName,
                'Score' => $result->Score_Total,
                'Archetype' => $archetype->Name
            );
        }
        
        return $export_data;
    }
    
    /**
     * Generate export file
     * 
     * @param array  $data Export data
     * @param string $type Export type
     * @param string $format File format
     * @return string File URL
     */
    protected function generate_export_file( $data, $type, $format ) {
        $upload_dir = wp_upload_dir();
        $export_dir = $upload_dir['basedir'] . '/money-quiz-exports';
        
        // Create export directory
        if ( ! file_exists( $export_dir ) ) {
            wp_mkdir_p( $export_dir );
        }
        
        $filename = sprintf( 'money-quiz-%s-%s.%s', 
            $type, 
            date( 'Y-m-d-His' ), 
            $format 
        );
        
        $filepath = $export_dir . '/' . $filename;
        
        switch ( $format ) {
            case 'csv':
                $this->generate_csv( $filepath, $data );
                break;
            case 'json':
                file_put_contents( $filepath, json_encode( $data, JSON_PRETTY_PRINT ) );
                break;
            // Add more formats as needed
        }
        
        return $upload_dir['baseurl'] . '/money-quiz-exports/' . $filename;
    }
    
    /**
     * Generate CSV file
     * 
     * @param string $filepath File path
     * @param array  $data Data to export
     */
    protected function generate_csv( $filepath, $data ) {
        $file = fopen( $filepath, 'w' );
        
        // Write headers
        if ( ! empty( $data ) ) {
            fputcsv( $file, array_keys( $data[0] ) );
        }
        
        // Write data
        foreach ( $data as $row ) {
            fputcsv( $file, $row );
        }
        
        fclose( $file );
    }
}

/**
 * Validation Service Class
 * 
 * Provides comprehensive validation functionality
 */
class ValidationService {
    
    /**
     * Validation rules
     * 
     * @var array
     */
    protected $rules = array();
    
    /**
     * Validation errors
     * 
     * @var array
     */
    protected $errors = array();
    
    /**
     * Constructor
     */
    public function __construct() {
        $this->init_rules();
    }
    
    /**
     * Initialize validation rules
     */
    protected function init_rules() {
        $this->rules = array(
            'email' => array(
                'pattern' => '/^[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}$/',
                'message' => __( 'Please enter a valid email address', 'money-quiz' )
            ),
            'phone' => array(
                'pattern' => '/^[\d\s\-\+\(\)]+$/',
                'min_length' => 10,
                'message' => __( 'Please enter a valid phone number', 'money-quiz' )
            ),
            'name' => array(
                'pattern' => '/^[a-zA-Z\s\-\']+$/',
                'min_length' => 2,
                'max_length' => 50,
                'message' => __( 'Please enter a valid name', 'money-quiz' )
            ),
            'url' => array(
                'filter' => FILTER_VALIDATE_URL,
                'message' => __( 'Please enter a valid URL', 'money-quiz' )
            )
        );
    }
    
    /**
     * Validate email
     * 
     * @param string $email Email address
     * @return bool
     */
    public function validate_email( $email ) {
        // Basic validation
        if ( ! filter_var( $email, FILTER_VALIDATE_EMAIL ) ) {
            $this->add_error( 'email', $this->rules['email']['message'] );
            return false;
        }
        
        // Check for disposable email
        if ( $this->is_disposable_email( $email ) ) {
            $this->add_error( 'email', __( 'Disposable email addresses are not allowed', 'money-quiz' ) );
            return false;
        }
        
        // Check blacklist
        if ( $this->is_blacklisted( $email ) ) {
            $this->add_error( 'email', __( 'This email address has been blocked', 'money-quiz' ) );
            return false;
        }
        
        return true;
    }
    
    /**
     * Validate phone number
     * 
     * @param string $phone Phone number
     * @return bool
     */
    public function validate_phone( $phone ) {
        // Remove common formatting
        $cleaned = preg_replace( '/[\s\-\(\)]/', '', $phone );
        
        // Check length
        if ( strlen( $cleaned ) < 10 || strlen( $cleaned ) > 15 ) {
            $this->add_error( 'phone', $this->rules['phone']['message'] );
            return false;
        }
        
        // Check pattern
        if ( ! preg_match( '/^\+?\d+$/', $cleaned ) ) {
            $this->add_error( 'phone', $this->rules['phone']['message'] );
            return false;
        }
        
        return true;
    }
    
    /**
     * Validate name
     * 
     * @param string $name Name
     * @return bool
     */
    public function validate_name( $name ) {
        $length = strlen( $name );
        
        if ( $length < $this->rules['name']['min_length'] || 
             $length > $this->rules['name']['max_length'] ) {
            $this->add_error( 'name', $this->rules['name']['message'] );
            return false;
        }
        
        if ( ! preg_match( $this->rules['name']['pattern'], $name ) ) {
            $this->add_error( 'name', $this->rules['name']['message'] );
            return false;
        }
        
        return true;
    }
    
    /**
     * Validate URL
     * 
     * @param string $url URL
     * @return bool
     */
    public function validate_url( $url ) {
        if ( ! filter_var( $url, FILTER_VALIDATE_URL ) ) {
            $this->add_error( 'url', $this->rules['url']['message'] );
            return false;
        }
        
        return true;
    }
    
    /**
     * Validate required field
     * 
     * @param mixed  $value Field value
     * @param string $field Field name
     * @return bool
     */
    public function validate_required( $value, $field ) {
        if ( empty( $value ) ) {
            $this->add_error( $field, 
                sprintf( __( '%s is required', 'money-quiz' ), ucfirst( $field ) )
            );
            return false;
        }
        
        return true;
    }
    
    /**
     * Validate numeric value
     * 
     * @param mixed $value Value
     * @param int   $min Minimum value
     * @param int   $max Maximum value
     * @param string $field Field name
     * @return bool
     */
    public function validate_numeric( $value, $min, $max, $field ) {
        if ( ! is_numeric( $value ) ) {
            $this->add_error( $field, 
                sprintf( __( '%s must be a number', 'money-quiz' ), ucfirst( $field ) )
            );
            return false;
        }
        
        $num = floatval( $value );
        
        if ( $num < $min || $num > $max ) {
            $this->add_error( $field, 
                sprintf( __( '%s must be between %d and %d', 'money-quiz' ), 
                    ucfirst( $field ), $min, $max )
            );
            return false;
        }
        
        return true;
    }
    
    /**
     * Check if email is disposable
     * 
     * @param string $email Email address
     * @return bool
     */
    protected function is_disposable_email( $email ) {
        $disposable_domains = array(
            'tempmail.com', 'throwaway.email', 'guerrillamail.com',
            'mailinator.com', '10minutemail.com', 'trashmail.com'
            // Add more as needed
        );
        
        $domain = substr( strrchr( $email, '@' ), 1 );
        
        return in_array( $domain, $disposable_domains );
    }
    
    /**
     * Check if email is blacklisted
     * 
     * @param string $email Email address
     * @return bool
     */
    protected function is_blacklisted( $email ) {
        global $wpdb;
        
        $table = $wpdb->prefix . 'mq_blacklist';
        $exists = $wpdb->get_var( $wpdb->prepare(
            "SELECT COUNT(*) FROM {$table} WHERE email = %s AND is_active = 1",
            $email
        ));
        
        return $exists > 0;
    }
    
    /**
     * Add validation error
     * 
     * @param string $field Field name
     * @param string $message Error message
     */
    public function add_error( $field, $message ) {
        if ( ! isset( $this->errors[ $field ] ) ) {
            $this->errors[ $field ] = array();
        }
        
        $this->errors[ $field ][] = $message;
    }
    
    /**
     * Get validation errors
     * 
     * @return array
     */
    public function get_errors() {
        return $this->errors;
    }
    
    /**
     * Clear validation errors
     */
    public function clear_errors() {
        $this->errors = array();
    }
    
    /**
     * Has errors
     * 
     * @return bool
     */
    public function has_errors() {
        return ! empty( $this->errors );
    }
    
    /**
     * Sanitize input
     * 
     * @param mixed  $value Value to sanitize
     * @param string $type Sanitization type
     * @return mixed
     */
    public function sanitize( $value, $type = 'text' ) {
        switch ( $type ) {
            case 'email':
                return sanitize_email( $value );
                
            case 'url':
                return esc_url_raw( $value );
                
            case 'int':
                return intval( $value );
                
            case 'float':
                return floatval( $value );
                
            case 'textarea':
                return sanitize_textarea_field( $value );
                
            case 'key':
                return sanitize_key( $value );
                
            case 'html':
                return wp_kses_post( $value );
                
            case 'text':
            default:
                return sanitize_text_field( $value );
        }
    }
}
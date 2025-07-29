<?php
/**
 * Legacy Function Router
 * 
 * Routes legacy function calls to modern implementations
 * 
 * @package MoneyQuiz
 * @since 4.1.0
 */

namespace MoneyQuiz\Legacy;

class Legacy_Function_Router {
    
    /**
     * @var array Function mappings
     */
    private $function_map = [];
    
    /**
     * @var array Call statistics
     */
    private $call_stats = [];
    
    /**
     * @var bool Enable gradual migration
     */
    private $gradual_migration = true;
    
    /**
     * Initialize router
     */
    public function init() {
        $this->setup_function_mappings();
        
        // Load legacy functions using safe loader instead of eval()
        require_once __DIR__ . '/class-legacy-function-loader.php';
        Legacy_Function_Loader::load();
    }
    
    /**
     * Setup function mappings
     */
    private function setup_function_mappings() {
        $this->function_map = [
            // Quiz functions
            'mq_get_quiz_questions' => [
                'modern' => [ $this, 'get_quiz_questions_modern' ],
                'legacy' => 'mq_get_quiz_questions_legacy',
                'enabled' => true
            ],
            'mq_save_quiz_result' => [
                'modern' => [ $this, 'save_quiz_result_modern' ],
                'legacy' => 'mq_save_quiz_result_legacy',
                'enabled' => true
            ],
            'mq_calculate_archetype' => [
                'modern' => [ $this, 'calculate_archetype_modern' ],
                'legacy' => 'mq_calculate_archetype_legacy',
                'enabled' => true
            ],
            
            // Email functions
            'mq_send_result_email' => [
                'modern' => [ $this, 'send_result_email_modern' ],
                'legacy' => 'mq_send_result_email_legacy',
                'enabled' => true
            ],
            
            // Database functions
            'mq_get_prospects' => [
                'modern' => [ $this, 'get_prospects_modern' ],
                'legacy' => 'mq_get_prospects_legacy',
                'enabled' => true
            ],
            
            // Settings functions
            'mq_get_setting' => [
                'modern' => [ $this, 'get_setting_modern' ],
                'legacy' => 'mq_get_setting_legacy',
                'enabled' => true
            ]
        ];
    }
    
    
    
    /**
     * Route a function call
     */
    public function route_call( $function_name, $args ) {
        // Track call statistics
        $this->track_call( $function_name );
        
        // Check if function mapping exists
        if ( ! isset( $this->function_map[ $function_name ] ) ) {
            throw new \Exception( "Unknown legacy function: {$function_name}" );
        }
        
        $config = $this->function_map[ $function_name ];
        
        // Check if modern implementation is enabled
        if ( $config['enabled'] && $this->should_use_modern( $function_name ) ) {
            try {
                // Use modern implementation
                return call_user_func_array( $config['modern'], $args );
            } catch ( \Exception $e ) {
                // Log error and fall back to legacy
                $this->log_routing_error( $function_name, $e );
                
                if ( isset( $config['legacy'] ) && function_exists( $config['legacy'] ) ) {
                    return call_user_func_array( $config['legacy'], $args );
                }
                
                throw $e;
            }
        }
        
        // Use legacy implementation
        if ( isset( $config['legacy'] ) && function_exists( $config['legacy'] ) ) {
            return call_user_func_array( $config['legacy'], $args );
        }
        
        throw new \Exception( "No implementation found for: {$function_name}" );
    }
    
    /**
     * Determine if modern implementation should be used
     */
    private function should_use_modern( $function_name ) {
        // Check feature flags
        $feature_flags = get_option( 'money_quiz_feature_flags', [] );
        if ( isset( $feature_flags[ $function_name ] ) ) {
            return $feature_flags[ $function_name ];
        }
        
        // Check gradual rollout percentage
        if ( $this->gradual_migration ) {
            $rollout_percentage = get_option( 'money_quiz_modern_rollout', 100 );
            return rand( 1, 100 ) <= $rollout_percentage;
        }
        
        return true;
    }
    
    /**
     * Track function calls
     */
    private function track_call( $function_name ) {
        if ( ! isset( $this->call_stats[ $function_name ] ) ) {
            $this->call_stats[ $function_name ] = [
                'count' => 0,
                'modern' => 0,
                'legacy' => 0,
                'errors' => 0
            ];
        }
        
        $this->call_stats[ $function_name ]['count']++;
    }
    
    /**
     * Log routing errors
     */
    private function log_routing_error( $function_name, $exception ) {
        $this->call_stats[ $function_name ]['errors']++;
        
        if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
            error_log( sprintf(
                '[MoneyQuiz Router] Error in %s: %s',
                $function_name,
                $exception->getMessage()
            ) );
        }
    }
    
    // Modern implementations
    
    /**
     * Modern implementation of get_quiz_questions
     */
    private function get_quiz_questions_modern( $quiz_id = null ) {
        if ( class_exists( '\MoneyQuiz\Core\Plugin' ) ) {
            $container = \MoneyQuiz\Core\Plugin::instance()->get_container();
            $quiz_service = $container->get( 'quiz_service' );
            
            if ( $quiz_id ) {
                return $quiz_service->get_quiz_questions( $quiz_id );
            }
            
            return $quiz_service->get_active_quiz_questions();
        }
        
        // Fallback to legacy
        if ( function_exists( 'mq_get_quiz_questions_legacy' ) ) {
            return mq_get_quiz_questions_legacy( $quiz_id );
        }
        
        return [];
    }
    
    /**
     * Modern implementation of save_quiz_result
     */
    private function save_quiz_result_modern( $data ) {
        // Sanitize input first
        $sanitized_data = mq_sanitize_input( $data );
        
        if ( class_exists( '\MoneyQuiz\Core\Plugin' ) ) {
            $container = \MoneyQuiz\Core\Plugin::instance()->get_container();
            $quiz_service = $container->get( 'quiz_service' );
            
            return $quiz_service->process_submission( $sanitized_data );
        }
        
        // Fallback to legacy
        if ( function_exists( 'mq_save_quiz_result_legacy' ) ) {
            return mq_save_quiz_result_legacy( $sanitized_data );
        }
        
        return false;
    }
    
    /**
     * Modern implementation of calculate_archetype
     */
    private function calculate_archetype_modern( $answers ) {
        if ( class_exists( '\MoneyQuiz\Core\Plugin' ) ) {
            $container = \MoneyQuiz\Core\Plugin::instance()->get_container();
            $quiz_service = $container->get( 'quiz_service' );
            
            return $quiz_service->calculate_archetype_scores( $answers );
        }
        
        // Fallback to legacy
        if ( function_exists( 'mq_calculate_archetype_legacy' ) ) {
            return mq_calculate_archetype_legacy( $answers );
        }
        
        return null;
    }
    
    /**
     * Modern implementation of send_result_email
     */
    private function send_result_email_modern( $email, $result_data ) {
        // Validate email
        $clean_email = mq_sanitize_field( 'email', $email );
        if ( ! is_email( $clean_email ) ) {
            return false;
        }
        
        if ( class_exists( '\MoneyQuiz\Core\Plugin' ) ) {
            $container = \MoneyQuiz\Core\Plugin::instance()->get_container();
            $email_service = $container->get( 'email_service' );
            
            return $email_service->send_quiz_result( $clean_email, $result_data );
        }
        
        // Fallback to legacy
        if ( function_exists( 'mq_send_result_email_legacy' ) ) {
            return mq_send_result_email_legacy( $clean_email, $result_data );
        }
        
        return false;
    }
    
    /**
     * Modern implementation of get_prospects
     */
    private function get_prospects_modern( $args = [] ) {
        if ( class_exists( '\MoneyQuiz\Core\Plugin' ) ) {
            $container = \MoneyQuiz\Core\Plugin::instance()->get_container();
            $prospect_repo = $container->get( 'prospect_repository' );
            
            return $prospect_repo->find_all( $args );
        }
        
        // Fallback to legacy
        if ( function_exists( 'mq_get_prospects_legacy' ) ) {
            return mq_get_prospects_legacy( $args );
        }
        
        return [];
    }
    
    /**
     * Modern implementation of get_setting
     */
    private function get_setting_modern( $key, $default = null ) {
        if ( class_exists( '\MoneyQuiz\Core\Plugin' ) ) {
            $container = \MoneyQuiz\Core\Plugin::instance()->get_container();
            $settings_manager = $container->get( 'settings_manager' );
            
            return $settings_manager->get( $key, $default );
        }
        
        // Fallback to legacy
        return get_option( 'mq_' . $key, $default );
    }
    
    /**
     * Get routing statistics
     */
    public function get_stats() {
        return $this->call_stats;
    }
    
    /**
     * Enable/disable modern implementation for a function
     */
    public function toggle_modern_implementation( $function_name, $enabled = true ) {
        if ( isset( $this->function_map[ $function_name ] ) ) {
            $this->function_map[ $function_name ]['enabled'] = $enabled;
            
            // Persist to feature flags
            $flags = get_option( 'money_quiz_feature_flags', [] );
            $flags[ $function_name ] = $enabled;
            update_option( 'money_quiz_feature_flags', $flags );
            
            return true;
        }
        
        return false;
    }
    
    /**
     * Get singleton instance
     */
    public static function instance() {
        static $instance = null;
        if ( null === $instance ) {
            $instance = new self();
        }
        return $instance;
    }
}
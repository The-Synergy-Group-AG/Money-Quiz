<?php
/**
 * Legacy Function Loader
 * 
 * Strategic solution for loading legacy functions without eval()
 * 
 * @package MoneyQuiz
 * @since 4.0.0
 */

namespace MoneyQuiz\Legacy;

/**
 * Loads legacy function definitions from a safe registry
 */
class Legacy_Function_Loader {
    
    /**
     * Load all legacy function definitions
     */
    public static function load() {
        // Only load if not already loaded
        if ( defined( 'MONEY_QUIZ_LEGACY_FUNCTIONS_LOADED' ) ) {
            return;
        }
        
        // Get router instance
        $router = Legacy_Function_Router::instance();
        
        // Define each legacy function explicitly
        self::define_quiz_functions( $router );
        self::define_email_functions( $router );
        self::define_database_functions( $router );
        self::define_settings_functions( $router );
        
        // Mark as loaded
        define( 'MONEY_QUIZ_LEGACY_FUNCTIONS_LOADED', true );
    }
    
    /**
     * Define quiz-related legacy functions
     */
    private static function define_quiz_functions( $router ) {
        if ( ! function_exists( 'mq_get_quiz_questions' ) ) {
            function mq_get_quiz_questions( $quiz_id = null ) {
                $router = Legacy_Function_Router::instance();
                return $router->route_call( 'mq_get_quiz_questions', [ $quiz_id ] );
            }
        }
        
        if ( ! function_exists( 'mq_save_quiz_result' ) ) {
            function mq_save_quiz_result( $data ) {
                $router = Legacy_Function_Router::instance();
                return $router->route_call( 'mq_save_quiz_result', [ $data ] );
            }
        }
        
        if ( ! function_exists( 'mq_calculate_archetype' ) ) {
            function mq_calculate_archetype( $answers ) {
                $router = Legacy_Function_Router::instance();
                return $router->route_call( 'mq_calculate_archetype', [ $answers ] );
            }
        }
    }
    
    /**
     * Define email-related legacy functions
     */
    private static function define_email_functions( $router ) {
        if ( ! function_exists( 'mq_send_result_email' ) ) {
            function mq_send_result_email( $email, $result_data ) {
                $router = Legacy_Function_Router::instance();
                return $router->route_call( 'mq_send_result_email', [ $email, $result_data ] );
            }
        }
    }
    
    /**
     * Define database-related legacy functions
     */
    private static function define_database_functions( $router ) {
        if ( ! function_exists( 'mq_get_prospects' ) ) {
            function mq_get_prospects( $args = [] ) {
                $router = Legacy_Function_Router::instance();
                return $router->route_call( 'mq_get_prospects', [ $args ] );
            }
        }
    }
    
    /**
     * Define settings-related legacy functions
     */
    private static function define_settings_functions( $router ) {
        if ( ! function_exists( 'mq_get_setting' ) ) {
            function mq_get_setting( $key, $default = null ) {
                $router = Legacy_Function_Router::instance();
                return $router->route_call( 'mq_get_setting', [ $key, $default ] );
            }
        }
    }
    
    /**
     * Add a new legacy function definition
     * 
     * @param string $function_name Function name
     * @param array  $param_names   Parameter names for function signature
     * @return bool Success
     */
    public static function add_function( $function_name, $param_names = [] ) {
        if ( function_exists( $function_name ) ) {
            return false;
        }
        
        // Include in a separate file to avoid eval()
        $loader_file = MONEY_QUIZ_PLUGIN_DIR . 'includes/legacy-functions/' . $function_name . '.php';
        
        if ( file_exists( $loader_file ) ) {
            require_once $loader_file;
            return true;
        }
        
        return false;
    }
}
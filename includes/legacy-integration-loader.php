<?php
/**
 * Legacy Integration Loader
 * 
 * Loads safety wrappers and integrations for legacy code
 * 
 * @package MoneyQuiz
 * @since 4.1.0
 */

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Initialize all safety wrappers and integrations
 */
function mq_initialize_safety_integrations() {
    
    // Load safety wrappers
    $safety_files = [
        'class-legacy-db-wrapper.php',
        'class-legacy-input-sanitizer.php',
        'class-legacy-function-router.php',
        'class-version-manager.php',
        'class-enhanced-error-logger.php'
    ];
    
    foreach ( $safety_files as $file ) {
        $file_path = MONEY_QUIZ_PLUGIN_DIR . 'includes/' . $file;
        if ( file_exists( $file_path ) ) {
            require_once $file_path;
        }
    }
    
    // Initialize version manager
    if ( class_exists( '\MoneyQuiz\Core\Version_Manager' ) ) {
        $version_manager = new \MoneyQuiz\Core\Version_Manager();
        $version_manager->init();
    }
    
    // Initialize function router
    if ( class_exists( '\MoneyQuiz\Legacy\Legacy_Function_Router' ) ) {
        $router = \MoneyQuiz\Legacy\Legacy_Function_Router::instance();
        $router->init();
    }
    
    // Enable error logging if in debug mode
    if ( defined( 'WP_DEBUG' ) && WP_DEBUG && ! defined( 'MONEY_QUIZ_ERROR_LOGGING' ) ) {
        define( 'MONEY_QUIZ_ERROR_LOGGING', true );
    }
    
    // Add global safety hooks
    add_action( 'init', 'mq_add_safety_hooks', 1 );
    
    // Add input sanitization to all requests
    add_action( 'init', 'mq_sanitize_global_inputs', 2 );
}

/**
 * Add safety hooks
 */
function mq_add_safety_hooks() {
    // Add CSRF protection to AJAX actions
    $ajax_actions = [
        'mq_process_quiz',
        'mq_save_settings',
        'mq_delete_prospect',
        'mq_export_data'
    ];
    
    foreach ( $ajax_actions as $action ) {
        add_action( 'wp_ajax_' . $action, 'mq_verify_ajax_security', 1 );
        add_action( 'wp_ajax_nopriv_' . $action, 'mq_verify_ajax_security', 1 );
    }
    
    // Add capability checks to admin actions
    add_action( 'admin_init', 'mq_enforce_admin_capabilities' );
}

/**
 * Sanitize global inputs
 */
function mq_sanitize_global_inputs() {
    if ( ! empty( $_POST ) ) {
        $_POST = mq_sanitize_input( $_POST );
    }
    
    if ( ! empty( $_GET ) ) {
        $_GET = mq_sanitize_input( $_GET, [
            '_wpnonce' => 'none',
            'action' => 'key',
            'page' => 'key',
            'tab' => 'key'
        ] );
    }
    
    if ( ! empty( $_REQUEST ) ) {
        $_REQUEST = array_merge( $_GET, $_POST );
    }
}

/**
 * Verify AJAX security
 */
function mq_verify_ajax_security() {
    // Check nonce
    if ( ! isset( $_REQUEST['_wpnonce'] ) || ! wp_verify_nonce( $_REQUEST['_wpnonce'], 'mq_ajax_nonce' ) ) {
        wp_die( __( 'Security check failed', 'money-quiz' ), 403 );
    }
    
    // Additional CSRF check if available
    if ( class_exists( '\MoneyQuiz\Security\CsrfManager' ) ) {
        try {
            $csrf = new \MoneyQuiz\Security\CsrfManager();
            $csrf->verify_request( 'ajax' );
        } catch ( \Exception $e ) {
            wp_die( __( 'CSRF validation failed', 'money-quiz' ), 403 );
        }
    }
}

/**
 * Enforce admin capabilities
 */
function mq_enforce_admin_capabilities() {
    if ( ! is_admin() ) {
        return;
    }
    
    $page = isset( $_GET['page'] ) ? $_GET['page'] : '';
    
    // Define required capabilities for each page
    $page_capabilities = [
        'moneyquiz' => 'manage_options',
        'moneyquiz-prospects' => 'manage_options',
        'moneyquiz-settings' => 'manage_options',
        'moneyquiz-export' => 'export',
        'moneyquiz-import' => 'import'
    ];
    
    if ( isset( $page_capabilities[ $page ] ) ) {
        if ( ! current_user_can( $page_capabilities[ $page ] ) ) {
            wp_die( __( 'You do not have sufficient permissions to access this page.', 'money-quiz' ), 403 );
        }
    }
}

/**
 * Safe wrapper for legacy database queries
 * 
 * @param string $query SQL query
 * @param array $args Query arguments
 * @return mixed Query result
 */
function mq_safe_query( $query, $args = [] ) {
    return mq_safe_db()->safe_query( $query, $args );
}

/**
 * Safe wrapper for get_results
 * 
 * @param string $query SQL query
 * @param array $args Query arguments
 * @param string $output Output type
 * @return array Query results
 */
function mq_safe_get_results( $query, $args = [], $output = OBJECT ) {
    return mq_safe_db()->safe_get_results( $query, $args, $output );
}

/**
 * Safe wrapper for get_row
 * 
 * @param string $query SQL query
 * @param array $args Query arguments
 * @param string $output Output type
 * @return mixed Query result
 */
function mq_safe_get_row( $query, $args = [], $output = OBJECT ) {
    return mq_safe_db()->safe_get_row( $query, $args, $output );
}

/**
 * Safe wrapper for get_var
 * 
 * @param string $query SQL query
 * @param array $args Query arguments
 * @return mixed Query result
 */
function mq_safe_get_var( $query, $args = [] ) {
    return mq_safe_db()->safe_get_var( $query, $args );
}

/**
 * Add nonce field to forms
 * 
 * @param string $action Nonce action
 * @param string $name Nonce name
 * @return void
 */
function mq_nonce_field( $action = 'mq_form_submit', $name = '_wpnonce' ) {
    wp_nonce_field( $action, $name );
    
    // Also add CSRF token if available
    if ( class_exists( '\MoneyQuiz\Security\CsrfManager' ) ) {
        $csrf = new \MoneyQuiz\Security\CsrfManager();
        echo $csrf->get_token_field( $action );
    }
}

/**
 * Verify nonce and CSRF
 * 
 * @param string $action Nonce action
 * @param string $name Nonce name
 * @return bool
 */
function mq_verify_security( $action = 'mq_form_submit', $name = '_wpnonce' ) {
    // Check WordPress nonce
    if ( ! isset( $_REQUEST[ $name ] ) || ! wp_verify_nonce( $_REQUEST[ $name ], $action ) ) {
        return false;
    }
    
    // Check CSRF if available
    if ( class_exists( '\MoneyQuiz\Security\CsrfManager' ) ) {
        try {
            $csrf = new \MoneyQuiz\Security\CsrfManager();
            $csrf->verify_request( $action );
        } catch ( \Exception $e ) {
            return false;
        }
    }
    
    return true;
}

/**
 * Log security events
 * 
 * @param string $event_type Event type
 * @param array $data Event data
 * @return void
 */
function mq_log_security_event( $event_type, $data = [] ) {
    if ( class_exists( '\MoneyQuiz\Debug\Enhanced_Error_Logger' ) ) {
        $logger = new \MoneyQuiz\Debug\Enhanced_Error_Logger();
        $logger->log_security_event( $event_type, $data );
    }
    
    // Also log to WordPress debug.log
    if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
        error_log( sprintf(
            '[Money Quiz Security] %s: %s',
            $event_type,
            json_encode( $data )
        ) );
    }
}

// Initialize safety integrations
mq_initialize_safety_integrations();
<?php
/**
 * Global helper functions
 *
 * @package MoneyQuiz
 * @since 4.0.0
 */

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Get the plugin instance
 *
 * @return \MoneyQuiz\Core\Plugin
 */
function money_quiz() {
    return \MoneyQuiz\Core\Plugin::get_instance();
}

/**
 * Get a service from the container
 *
 * @param string $service Service name
 * @return mixed
 */
function money_quiz_service( string $service ) {
    return money_quiz()->get_container()->get( $service );
}

/**
 * Debug logger
 *
 * @param mixed  $data  Data to log
 * @param string $label Optional label
 * @return void
 */
function money_quiz_log( $data, string $label = '' ) {
    if ( ! defined( 'MONEY_QUIZ_DEBUG' ) || ! MONEY_QUIZ_DEBUG ) {
        return;
    }
    
    $message = $label ? $label . ': ' : '';
    $message .= is_string( $data ) ? $data : print_r( $data, true );
    
    error_log( '[Money Quiz] ' . $message );
}

/**
 * Get plugin URL
 *
 * @param string $path Optional path to append
 * @return string
 */
function money_quiz_url( string $path = '' ) {
    $url = MONEY_QUIZ_PLUGIN_URL;
    
    if ( $path ) {
        $url .= ltrim( $path, '/' );
    }
    
    return $url;
}

/**
 * Get plugin path
 *
 * @param string $path Optional path to append
 * @return string
 */
function money_quiz_path( string $path = '' ) {
    $plugin_path = MONEY_QUIZ_PLUGIN_DIR;
    
    if ( $path ) {
        $plugin_path .= ltrim( $path, '/' );
    }
    
    return $plugin_path;
}

/**
 * Get template path
 *
 * @param string $template Template name
 * @param string $type     Template type (frontend/admin)
 * @return string
 */
function money_quiz_template_path( string $template, string $type = 'frontend' ) {
    $path = money_quiz_path( "templates/{$type}/{$template}" );
    
    // Allow theme override
    $theme_path = get_stylesheet_directory() . "/money-quiz/{$type}/{$template}";
    if ( file_exists( $theme_path ) ) {
        return $theme_path;
    }
    
    return $path;
}

/**
 * Load a template
 *
 * @param string $template Template name
 * @param array  $args     Arguments to pass to template
 * @param string $type     Template type
 * @return void
 */
function money_quiz_template( string $template, array $args = [], string $type = 'frontend' ) {
    $path = money_quiz_template_path( $template, $type );
    
    if ( file_exists( $path ) ) {
        extract( $args );
        include $path;
    }
}

/**
 * Get a template as string
 *
 * @param string $template Template name
 * @param array  $args     Arguments
 * @param string $type     Template type
 * @return string
 */
function money_quiz_get_template( string $template, array $args = [], string $type = 'frontend' ) {
    ob_start();
    money_quiz_template( $template, $args, $type );
    return ob_get_clean();
}

/**
 * Check if Money Quiz page
 *
 * @return bool
 */
function is_money_quiz_page() {
    global $post;
    
    if ( ! $post ) {
        return false;
    }
    
    // Check for shortcode
    if ( has_shortcode( $post->post_content, 'money_quiz' ) || 
         has_shortcode( $post->post_content, 'mq_questions' ) ) {
        return true;
    }
    
    // Check if viewing results
    if ( isset( $_GET['money_quiz_results'] ) ) {
        return true;
    }
    
    return apply_filters( 'money_quiz_is_quiz_page', false );
}

/**
 * Get current quiz ID
 *
 * @return int|null
 */
function money_quiz_current_quiz_id() {
    // From URL parameter
    if ( isset( $_GET['quiz_id'] ) ) {
        return absint( $_GET['quiz_id'] );
    }
    
    // From shortcode attribute
    global $money_quiz_current_quiz;
    if ( $money_quiz_current_quiz ) {
        return $money_quiz_current_quiz;
    }
    
    // Default quiz
    $quiz_service = money_quiz_service( 'quiz_service' );
    $default = $quiz_service->get_default_quiz();
    
    return $default ? $default->id : null;
}

/**
 * Format archetype score
 *
 * @param float $score Score value
 * @return string
 */
function money_quiz_format_score( float $score ) {
    return number_format( $score, 1 ) . '%';
}

/**
 * Get archetype color
 *
 * @param string $archetype Archetype slug
 * @return string
 */
function money_quiz_archetype_color( string $archetype ) {
    $colors = [
        'ruler' => '#ff6b6b',
        'magician' => '#4ecdc4',
        'warrior' => '#45b7d1',
        'lover' => '#f7b731',
        'creator' => '#5f27cd',
        'caregiver' => '#00d2d3',
        'innocent' => '#ff9ff3',
        'sage' => '#54a0ff',
    ];
    
    return $colors[ $archetype ] ?? '#666666';
}

/**
 * Sanitize quiz answers
 *
 * @param array $answers Raw answers
 * @return array
 */
function money_quiz_sanitize_answers( array $answers ) {
    $sanitized = [];
    
    foreach ( $answers as $question_id => $answer ) {
        $question_id = absint( $question_id );
        if ( $question_id ) {
            $sanitized[ $question_id ] = sanitize_text_field( $answer );
        }
    }
    
    return $sanitized;
}

/**
 * Get quiz progress percentage
 *
 * @param int $current  Current question
 * @param int $total    Total questions
 * @return int
 */
function money_quiz_progress( int $current, int $total ) {
    if ( $total === 0 ) {
        return 0;
    }
    
    return round( ( $current / $total ) * 100 );
}

/**
 * Check if user can manage quiz
 *
 * @param int $user_id User ID (optional)
 * @return bool
 */
function money_quiz_can_manage( int $user_id = 0 ) {
    if ( ! $user_id ) {
        $user_id = get_current_user_id();
    }
    
    return user_can( $user_id, 'manage_money_quiz' );
}

/**
 * Get quiz result URL
 *
 * @param int $result_id Result ID
 * @return string
 */
function money_quiz_result_url( int $result_id ) {
    $base_url = get_permalink( get_option( 'money_quiz_results_page' ) );
    
    if ( ! $base_url ) {
        $base_url = home_url( '/money-quiz-results/' );
    }
    
    return add_query_arg( [
        'result_id' => $result_id,
        'key' => wp_hash( $result_id ),
    ], $base_url );
}

/**
 * Time ago format
 *
 * @param string $datetime DateTime string
 * @return string
 */
function money_quiz_time_ago( string $datetime ) {
    $time = strtotime( $datetime );
    $diff = time() - $time;
    
    if ( $diff < 60 ) {
        return __( 'just now', 'money-quiz' );
    } elseif ( $diff < 3600 ) {
        $mins = round( $diff / 60 );
        return sprintf( _n( '%d minute ago', '%d minutes ago', $mins, 'money-quiz' ), $mins );
    } elseif ( $diff < 86400 ) {
        $hours = round( $diff / 3600 );
        return sprintf( _n( '%d hour ago', '%d hours ago', $hours, 'money-quiz' ), $hours );
    } else {
        $days = round( $diff / 86400 );
        return sprintf( _n( '%d day ago', '%d days ago', $days, 'money-quiz' ), $days );
    }
}
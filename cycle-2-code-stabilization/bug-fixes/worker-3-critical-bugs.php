<?php
/**
 * Worker 3: Critical Bug Fixes
 * Focus: Division by zero and other critical runtime errors
 */

// PATCH 1: Fix division by zero in get_percentage function (line 1446)
// OLD: return $cal_percentage = ($score_total_value/$ques_total_value*100);
// NEW: Safe division with validation
function get_percentage($Initiator_question, $score_total_value) {
    // Validate inputs
    $Initiator_question = absint($Initiator_question);
    $score_total_value = floatval($score_total_value);
    
    // Calculate total possible value
    $ques_total_value = ($Initiator_question * 8);
    
    // Prevent division by zero
    if ($ques_total_value <= 0) {
        MoneyQuizErrorHandler::getInstance()->logError(array(
            'type' => 'Calculation Error',
            'message' => 'Division by zero prevented in get_percentage()',
            'file' => __FILE__,
            'line' => __LINE__,
            'timestamp' => current_time('mysql'),
            'context' => array(
                'initiator_question' => $Initiator_question,
                'score_total_value' => $score_total_value
            )
        ));
        
        return 0; // Return 0% when no questions
    }
    
    // Calculate percentage safely
    $cal_percentage = ($score_total_value / $ques_total_value) * 100;
    
    // Ensure percentage is within valid range (0-100)
    $cal_percentage = max(0, min(100, $cal_percentage));
    
    // Round to 2 decimal places
    return round($cal_percentage, 2);
}

// PATCH 2: Fix similar division by zero issues throughout the plugin
class MoneyQuizMathHelper {
    
    /**
     * Safe division operation
     */
    public static function safeDivide($numerator, $denominator, $default = 0) {
        // Convert to numeric values
        $numerator = is_numeric($numerator) ? floatval($numerator) : 0;
        $denominator = is_numeric($denominator) ? floatval($denominator) : 0;
        
        // Check for division by zero
        if ($denominator == 0) {
            if (WP_DEBUG) {
                trigger_error('Division by zero attempted', E_USER_NOTICE);
            }
            return $default;
        }
        
        return $numerator / $denominator;
    }
    
    /**
     * Safe percentage calculation
     */
    public static function calculatePercentage($value, $total, $decimals = 2) {
        if ($total <= 0) {
            return 0;
        }
        
        $percentage = self::safeDivide($value, $total) * 100;
        
        // Ensure within 0-100 range
        $percentage = max(0, min(100, $percentage));
        
        return round($percentage, $decimals);
    }
    
    /**
     * Safe average calculation
     */
    public static function calculateAverage($values, $decimals = 2) {
        if (!is_array($values) || empty($values)) {
            return 0;
        }
        
        $sum = array_sum($values);
        $count = count($values);
        
        $average = self::safeDivide($sum, $count);
        
        return round($average, $decimals);
    }
}

// PATCH 3: Fix undefined index warnings in quiz result calculations
function mq_get_archetype_scores($results, $archetypes) {
    $scores = array();
    
    // Initialize all archetype scores to prevent undefined index
    $archetype_ids = array(1, 5, 9, 13, 17, 21, 25, 29); // Warrior, Initiator, etc.
    foreach ($archetype_ids as $id) {
        $scores[$id] = 0;
    }
    
    // Safe result processing
    if (is_array($results) || is_object($results)) {
        foreach ($results as $result) {
            // Safely access object properties
            $archetype = mq_get_object_property($result, 'Archetype', 0);
            $score = mq_get_object_property($result, 'Score', 0);
            
            if (isset($scores[$archetype])) {
                $scores[$archetype] += intval($score);
            }
        }
    }
    
    return $scores;
}

// PATCH 4: Fix array access issues in report generation
function mq_safe_array_sum($array, $key = null) {
    if (!is_array($array)) {
        return 0;
    }
    
    if ($key === null) {
        return array_sum($array);
    }
    
    $sum = 0;
    foreach ($array as $item) {
        if (is_array($item) && isset($item[$key])) {
            $sum += floatval($item[$key]);
        } elseif (is_object($item) && property_exists($item, $key)) {
            $sum += floatval($item->$key);
        }
    }
    
    return $sum;
}

// PATCH 5: Fix database result handling to prevent errors
function mq_process_quiz_results($taken_id) {
    global $wpdb, $table_prefix;
    
    // Validate input
    $taken_id = absint($taken_id);
    if ($taken_id <= 0) {
        return new WP_Error('invalid_id', __('Invalid quiz ID', 'money-quiz'));
    }
    
    // Safe database query
    $results = mq_safe_db_operation(function() use ($wpdb, $table_prefix, $taken_id) {
        return $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$table_prefix}" . TABLE_MQ_RESULTS . " WHERE Taken_ID = %d",
            $taken_id
        ));
    }, array());
    
    // Check if results exist
    if (empty($results)) {
        return new WP_Error('no_results', __('No results found for this quiz', 'money-quiz'));
    }
    
    // Process results safely
    $processed = array(
        'total_score' => 0,
        'question_count' => 0,
        'archetype_scores' => array(),
        'percentage' => 0
    );
    
    foreach ($results as $result) {
        // Safe property access
        $score = mq_get_object_property($result, 'Score', 0);
        $archetype = mq_get_object_property($result, 'Archetype', 0);
        
        $processed['total_score'] += intval($score);
        $processed['question_count']++;
        
        if (!isset($processed['archetype_scores'][$archetype])) {
            $processed['archetype_scores'][$archetype] = 0;
        }
        $processed['archetype_scores'][$archetype] += intval($score);
    }
    
    // Calculate percentage safely
    $max_possible_score = $processed['question_count'] * 10; // Assuming max 10 per question
    $processed['percentage'] = MoneyQuizMathHelper::calculatePercentage(
        $processed['total_score'],
        $max_possible_score
    );
    
    return $processed;
}

// PATCH 6: Fix email generation calculation errors
function mq_calculate_archetype_percentages($archetype_scores, $total_questions) {
    $percentages = array();
    
    // Validate input
    if (!is_array($archetype_scores) || $total_questions <= 0) {
        return $percentages;
    }
    
    // Calculate max possible score per archetype
    $questions_per_archetype = MoneyQuizMathHelper::safeDivide($total_questions, 8); // 8 archetypes
    $max_score_per_archetype = $questions_per_archetype * 10; // Max 10 points per question
    
    foreach ($archetype_scores as $archetype_id => $score) {
        $percentages[$archetype_id] = MoneyQuizMathHelper::calculatePercentage(
            $score,
            $max_score_per_archetype
        );
    }
    
    return $percentages;
}

// PATCH 7: Fix session handling bugs
function mq_safe_session_start() {
    if (session_status() === PHP_SESSION_NONE) {
        // Set secure session parameters
        $secure = is_ssl();
        $httponly = true;
        
        session_set_cookie_params(array(
            'lifetime' => 0,
            'path' => COOKIEPATH,
            'domain' => COOKIE_DOMAIN,
            'secure' => $secure,
            'httponly' => $httponly,
            'samesite' => 'Lax'
        ));
        
        session_start();
    }
}

function mq_get_session_value($key, $default = null) {
    mq_safe_session_start();
    return isset($_SESSION[$key]) ? $_SESSION[$key] : $default;
}

function mq_set_session_value($key, $value) {
    mq_safe_session_start();
    $_SESSION[$key] = $value;
}

// PATCH 8: Fix URL parameter handling
function mq_get_query_var($var, $default = null) {
    // First check GET
    if (isset($_GET[$var])) {
        return sanitize_text_field($_GET[$var]);
    }
    
    // Then check POST
    if (isset($_POST[$var])) {
        return sanitize_text_field($_POST[$var]);
    }
    
    // Finally check REQUEST
    if (isset($_REQUEST[$var])) {
        return sanitize_text_field($_REQUEST[$var]);
    }
    
    return $default;
}

// PATCH 9: Fix date/time handling bugs
function mq_get_current_timestamp() {
    return current_time('mysql');
}

function mq_format_date($date, $format = null) {
    if (empty($date) || $date === '0000-00-00 00:00:00') {
        return '';
    }
    
    if ($format === null) {
        $format = get_option('date_format') . ' ' . get_option('time_format');
    }
    
    $timestamp = strtotime($date);
    if ($timestamp === false) {
        return $date; // Return original if parsing fails
    }
    
    return date_i18n($format, $timestamp);
}

// PATCH 10: Memory leak prevention
function mq_free_result_memory(&$result) {
    if (is_array($result)) {
        $result = null;
    } elseif (is_object($result)) {
        if (method_exists($result, 'free')) {
            $result->free();
        }
        $result = null;
    }
    
    // Force garbage collection for large operations
    if (memory_get_usage() > 100 * 1024 * 1024) { // 100MB
        gc_collect_cycles();
    }
}
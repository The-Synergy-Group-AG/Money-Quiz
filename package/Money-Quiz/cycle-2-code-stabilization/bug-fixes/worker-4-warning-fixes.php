<?php
/**
 * Worker 4: Warning Fixes
 * Focus: Undefined indexes, array access issues, missing checks
 */

// PATCH 1: Fix undefined $_REQUEST indexes in moneyquiz.php
// Line 1027: License key handling
function mq_get_license_key() {
    return isset($_REQUEST['plugin_license_key']) 
        ? sanitize_text_field($_REQUEST['plugin_license_key']) 
        : '';
}

// PATCH 2: Fix $_SERVER array access issues
// Lines 1034, 1108, 1174, quiz.moneycoach.php line 245
function mq_get_server_name() {
    // Check multiple possible sources for server name
    if (isset($_SERVER['SERVER_NAME'])) {
        return $_SERVER['SERVER_NAME'];
    } elseif (isset($_SERVER['HTTP_HOST'])) {
        return $_SERVER['HTTP_HOST'];
    } else {
        // Fallback to site URL
        $parsed = parse_url(home_url());
        return isset($parsed['host']) ? $parsed['host'] : 'localhost';
    }
}

// PATCH 3: Fix REMOTE_ADDR access (quiz.moneycoach.php line 1132)
function mq_get_user_ip() {
    // Check for various IP headers (considering proxies)
    $ip_keys = array(
        'HTTP_CF_CONNECTING_IP',     // Cloudflare
        'HTTP_CLIENT_IP',             // Proxy
        'HTTP_X_FORWARDED_FOR',       // Load balancer
        'HTTP_X_FORWARDED',           // Proxy
        'HTTP_X_CLUSTER_CLIENT_IP',   // Cluster
        'HTTP_FORWARDED_FOR',         // Proxy
        'HTTP_FORWARDED',             // Proxy
        'REMOTE_ADDR'                 // Standard
    );
    
    foreach ($ip_keys as $key) {
        if (isset($_SERVER[$key]) && filter_var($_SERVER[$key], FILTER_VALIDATE_IP)) {
            return $_SERVER[$key];
        }
    }
    
    return '0.0.0.0'; // Default if no IP found
}

// PATCH 4: Fix questions.admin.php line 30 and 72
// Safe question ID retrieval
function mq_get_question_id() {
    if (isset($_REQUEST['questionid'])) {
        return absint($_REQUEST['questionid']);
    }
    return 0;
}

// PATCH 5: Fix quiz.moneycoach.php lines 2324-2325
// Safe parameter retrieval
function mq_get_request_param($param, $default = '', $sanitize = 'text') {
    if (!isset($_REQUEST[$param])) {
        return $default;
    }
    
    $value = $_REQUEST[$param];
    
    switch ($sanitize) {
        case 'int':
            return absint($value);
        case 'float':
            return floatval($value);
        case 'email':
            return sanitize_email($value);
        case 'url':
            return esc_url_raw($value);
        case 'key':
            return sanitize_key($value);
        case 'text':
        default:
            return sanitize_text_field($value);
    }
}

// PATCH 6: Safe $_GET access wrapper
function mq_get($key, $default = null, $sanitize = 'text') {
    if (!isset($_GET[$key])) {
        return $default;
    }
    
    switch ($sanitize) {
        case 'int':
            return intval($_GET[$key]);
        case 'bool':
            return (bool) $_GET[$key];
        case 'array':
            return (array) $_GET[$key];
        case 'raw':
            return $_GET[$key];
        default:
            return sanitize_text_field($_GET[$key]);
    }
}

// PATCH 7: Safe $_POST access wrapper
function mq_post($key, $default = null, $sanitize = 'text') {
    if (!isset($_POST[$key])) {
        return $default;
    }
    
    switch ($sanitize) {
        case 'int':
            return intval($_POST[$key]);
        case 'email':
            return sanitize_email($_POST[$key]);
        case 'textarea':
            return sanitize_textarea_field($_POST[$key]);
        case 'array':
            return (array) $_POST[$key];
        case 'raw':
            return $_POST[$key];
        default:
            return sanitize_text_field($_POST[$key]);
    }
}

// PATCH 8: Fix array key warnings in archetype calculations
function mq_safe_increment(&$array, $key, $increment = 1) {
    if (!is_array($array)) {
        $array = array();
    }
    
    if (!isset($array[$key])) {
        $array[$key] = 0;
    }
    
    $array[$key] += $increment;
}

// PATCH 9: Fix object property warnings
function mq_has_property($object, $property) {
    return is_object($object) && property_exists($object, $property);
}

function mq_get_property_safe($object, $property, $default = null) {
    if (mq_has_property($object, $property)) {
        return $object->$property;
    }
    return $default;
}

// PATCH 10: Fix array merge warnings
function mq_safe_array_merge($array1, $array2) {
    if (!is_array($array1)) {
        $array1 = array();
    }
    if (!is_array($array2)) {
        $array2 = array();
    }
    return array_merge($array1, $array2);
}

// PATCH 11: Fix empty() checks on function returns
function mq_is_empty($value) {
    // Handle different types safely
    if (is_null($value)) {
        return true;
    }
    if (is_string($value)) {
        return trim($value) === '';
    }
    if (is_array($value)) {
        return empty($value);
    }
    if (is_object($value)) {
        return empty((array) $value);
    }
    return empty($value);
}

// PATCH 12: Fix JSON decode warnings
function mq_json_decode_safe($json, $assoc = true) {
    if (!is_string($json)) {
        return null;
    }
    
    $decoded = json_decode($json, $assoc);
    
    if (json_last_error() !== JSON_ERROR_NONE) {
        MoneyQuizErrorHandler::getInstance()->logError(array(
            'type' => 'JSON Error',
            'message' => 'JSON decode error: ' . json_last_error_msg(),
            'file' => __FILE__,
            'line' => __LINE__,
            'timestamp' => current_time('mysql'),
            'context' => substr($json, 0, 100) . '...'
        ));
        return null;
    }
    
    return $decoded;
}

// PATCH 13: Fix file_get_contents warnings
function mq_file_get_contents_safe($filename) {
    if (!file_exists($filename)) {
        return false;
    }
    
    if (!is_readable($filename)) {
        return false;
    }
    
    $contents = @file_get_contents($filename);
    
    if ($contents === false) {
        MoneyQuizErrorHandler::getInstance()->logError(array(
            'type' => 'File Error',
            'message' => 'Failed to read file: ' . $filename,
            'file' => __FILE__,
            'line' => __LINE__,
            'timestamp' => current_time('mysql')
        ));
    }
    
    return $contents;
}

// PATCH 14: Fix undefined constants
function mq_define_safe($constant, $value) {
    if (!defined($constant)) {
        define($constant, $value);
    }
}

// PATCH 15: Fix in_array strict warnings
function mq_in_array_safe($needle, $haystack, $strict = false) {
    if (!is_array($haystack)) {
        return false;
    }
    return in_array($needle, $haystack, $strict);
}

// PATCH 16: Updated license check with proper validation
function mq_check_license_safe() {
    $license_key = mq_get_license_key();
    
    if (empty($license_key)) {
        return array('status' => 'empty', 'message' => 'No license key provided');
    }
    
    $api_params = array(
        'slm_action' => 'slm_check',
        'secret_key' => MoneyQuizConfig::get('special_secret_key', ''),
        'license_key' => $license_key,
        'registered_domain' => mq_get_server_name(),
        'item_reference' => urlencode(MONEYQUIZ_ITEM_REFERENCE),
    );
    
    // Validate required parameters
    foreach ($api_params as $key => $value) {
        if (empty($value) && $key !== 'license_key') {
            return array(
                'status' => 'error',
                'message' => sprintf('Missing required parameter: %s', $key)
            );
        }
    }
    
    return $api_params;
}

// PATCH 17: Fix cookie warnings
function mq_set_cookie_safe($name, $value, $expire = 0) {
    if (headers_sent()) {
        return false;
    }
    
    $secure = is_ssl();
    $httponly = true;
    
    return setcookie(
        $name,
        $value,
        $expire,
        COOKIEPATH,
        COOKIE_DOMAIN,
        $secure,
        $httponly
    );
}

function mq_get_cookie_safe($name, $default = null) {
    return isset($_COOKIE[$name]) ? $_COOKIE[$name] : $default;
}

// PATCH 18: Fix header already sent warnings
function mq_safe_redirect($location, $status = 302) {
    if (headers_sent($filename, $linenum)) {
        MoneyQuizErrorHandler::getInstance()->logError(array(
            'type' => 'Redirect Error',
            'message' => sprintf('Cannot redirect, headers already sent in %s on line %d', $filename, $linenum),
            'file' => __FILE__,
            'line' => __LINE__,
            'timestamp' => current_time('mysql')
        ));
        
        // JavaScript redirect as fallback
        echo '<script type="text/javascript">window.location.href="' . esc_url($location) . '";</script>';
        echo '<noscript><meta http-equiv="refresh" content="0;url=' . esc_url($location) . '" /></noscript>';
        return false;
    }
    
    wp_safe_redirect($location, $status);
    exit;
}
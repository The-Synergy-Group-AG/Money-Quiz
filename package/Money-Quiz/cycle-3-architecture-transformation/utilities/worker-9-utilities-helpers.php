<?php
/**
 * Money Quiz Plugin - Utility Functions and Helpers
 * Worker 9: Utilities and Helper Functions
 * 
 * Provides common utility functions and helper classes used throughout
 * the Money Quiz plugin for various operations.
 * 
 * @package MoneyQuiz
 * @subpackage Utilities
 * @since 4.0.0
 */

namespace MoneyQuiz\Utilities;

use Exception;
use DateTime;
use DateTimeZone;

/**
 * Array Utility Class
 * 
 * Provides array manipulation functions
 */
class ArrayUtil {
    
    /**
     * Get value from array using dot notation
     * 
     * @param array  $array Array to search
     * @param string $key Key in dot notation
     * @param mixed  $default Default value
     * @return mixed
     */
    public static function get( array $array, $key, $default = null ) {
        if ( is_null( $key ) ) {
            return $array;
        }
        
        if ( array_key_exists( $key, $array ) ) {
            return $array[ $key ];
        }
        
        if ( strpos( $key, '.' ) === false ) {
            return $default;
        }
        
        foreach ( explode( '.', $key ) as $segment ) {
            if ( is_array( $array ) && array_key_exists( $segment, $array ) ) {
                $array = $array[ $segment ];
            } else {
                return $default;
            }
        }
        
        return $array;
    }
    
    /**
     * Set array value using dot notation
     * 
     * @param array  $array Array to modify
     * @param string $key Key in dot notation
     * @param mixed  $value Value to set
     * @return array
     */
    public static function set( array &$array, $key, $value ) {
        if ( is_null( $key ) ) {
            return $array = $value;
        }
        
        $keys = explode( '.', $key );
        
        while ( count( $keys ) > 1 ) {
            $key = array_shift( $keys );
            
            if ( ! isset( $array[ $key ] ) || ! is_array( $array[ $key ] ) ) {
                $array[ $key ] = array();
            }
            
            $array = &$array[ $key ];
        }
        
        $array[ array_shift( $keys ) ] = $value;
        
        return $array;
    }
    
    /**
     * Check if array has key using dot notation
     * 
     * @param array  $array Array to check
     * @param string $key Key in dot notation
     * @return bool
     */
    public static function has( array $array, $key ) {
        if ( empty( $array ) || is_null( $key ) ) {
            return false;
        }
        
        if ( array_key_exists( $key, $array ) ) {
            return true;
        }
        
        foreach ( explode( '.', $key ) as $segment ) {
            if ( is_array( $array ) && array_key_exists( $segment, $array ) ) {
                $array = $array[ $segment ];
            } else {
                return false;
            }
        }
        
        return true;
    }
    
    /**
     * Pluck array of values from an array
     * 
     * @param array  $array Array to pluck from
     * @param string $value Value key
     * @param string $key Key for result array
     * @return array
     */
    public static function pluck( array $array, $value, $key = null ) {
        $results = array();
        
        foreach ( $array as $item ) {
            $item_value = is_object( $item ) ? $item->{$value} : $item[ $value ];
            
            if ( is_null( $key ) ) {
                $results[] = $item_value;
            } else {
                $item_key = is_object( $item ) ? $item->{$key} : $item[ $key ];
                $results[ $item_key ] = $item_value;
            }
        }
        
        return $results;
    }
    
    /**
     * Filter array by callback
     * 
     * @param array    $array Array to filter
     * @param callable $callback Filter callback
     * @return array
     */
    public static function where( array $array, callable $callback ) {
        return array_filter( $array, $callback, ARRAY_FILTER_USE_BOTH );
    }
    
    /**
     * Get only specified keys from array
     * 
     * @param array $array Array to filter
     * @param array $keys Keys to keep
     * @return array
     */
    public static function only( array $array, array $keys ) {
        return array_intersect_key( $array, array_flip( $keys ) );
    }
    
    /**
     * Get all except specified keys from array
     * 
     * @param array $array Array to filter
     * @param array $keys Keys to exclude
     * @return array
     */
    public static function except( array $array, array $keys ) {
        return array_diff_key( $array, array_flip( $keys ) );
    }
}

/**
 * String Utility Class
 * 
 * Provides string manipulation functions
 */
class StringUtil {
    
    /**
     * Convert string to slug
     * 
     * @param string $string String to convert
     * @param string $separator Separator character
     * @return string
     */
    public static function slug( $string, $separator = '-' ) {
        $string = sanitize_title_with_dashes( $string, '', 'save' );
        return str_replace( '_', $separator, $string );
    }
    
    /**
     * Convert string to camel case
     * 
     * @param string $string String to convert
     * @return string
     */
    public static function camel( $string ) {
        return lcfirst( static::studly( $string ) );
    }
    
    /**
     * Convert string to studly case
     * 
     * @param string $string String to convert
     * @return string
     */
    public static function studly( $string ) {
        $string = ucwords( str_replace( array( '-', '_' ), ' ', $string ) );
        return str_replace( ' ', '', $string );
    }
    
    /**
     * Convert string to snake case
     * 
     * @param string $string String to convert
     * @param string $delimiter Delimiter character
     * @return string
     */
    public static function snake( $string, $delimiter = '_' ) {
        if ( ! ctype_lower( $string ) ) {
            $string = preg_replace( '/\s+/u', '', ucwords( $string ) );
            $string = strtolower( preg_replace( '/(.)(?=[A-Z])/u', '$1' . $delimiter, $string ) );
        }
        
        return $string;
    }
    
    /**
     * Limit string length
     * 
     * @param string $string String to limit
     * @param int    $limit Character limit
     * @param string $end End string
     * @return string
     */
    public static function limit( $string, $limit = 100, $end = '...' ) {
        if ( mb_strlen( $string ) <= $limit ) {
            return $string;
        }
        
        return rtrim( mb_substr( $string, 0, $limit, 'UTF-8' ) ) . $end;
    }
    
    /**
     * Check if string contains substring
     * 
     * @param string $haystack String to search
     * @param string $needle Substring to find
     * @return bool
     */
    public static function contains( $haystack, $needle ) {
        return $needle !== '' && mb_strpos( $haystack, $needle ) !== false;
    }
    
    /**
     * Check if string starts with substring
     * 
     * @param string $haystack String to check
     * @param string $needle Substring to find
     * @return bool
     */
    public static function starts_with( $haystack, $needle ) {
        return substr( $haystack, 0, strlen( $needle ) ) === $needle;
    }
    
    /**
     * Check if string ends with substring
     * 
     * @param string $haystack String to check
     * @param string $needle Substring to find
     * @return bool
     */
    public static function ends_with( $haystack, $needle ) {
        return substr( $haystack, -strlen( $needle ) ) === $needle;
    }
    
    /**
     * Generate random string
     * 
     * @param int $length String length
     * @return string
     */
    public static function random( $length = 16 ) {
        $string = '';
        
        while ( ( $len = strlen( $string ) ) < $length ) {
            $size = $length - $len;
            $bytes = random_bytes( $size );
            $string .= substr( str_replace( array( '/', '+', '=' ), '', base64_encode( $bytes ) ), 0, $size );
        }
        
        return $string;
    }
}

/**
 * Date Utility Class
 * 
 * Provides date and time manipulation functions
 */
class DateUtil {
    
    /**
     * Format date for display
     * 
     * @param string $date Date string
     * @param string $format Output format
     * @return string
     */
    public static function format( $date, $format = null ) {
        if ( empty( $date ) ) {
            return '';
        }
        
        if ( is_null( $format ) ) {
            $format = get_option( 'date_format' ) . ' ' . get_option( 'time_format' );
        }
        
        try {
            $datetime = new DateTime( $date );
            return $datetime->format( $format );
        } catch ( Exception $e ) {
            return $date;
        }
    }
    
    /**
     * Get relative time string
     * 
     * @param string $date Date string
     * @return string
     */
    public static function relative( $date ) {
        if ( empty( $date ) ) {
            return '';
        }
        
        try {
            $datetime = new DateTime( $date );
            $now = new DateTime();
            $interval = $now->diff( $datetime );
            
            if ( $interval->y > 0 ) {
                return sprintf( _n( '%d year ago', '%d years ago', $interval->y, 'money-quiz' ), $interval->y );
            } elseif ( $interval->m > 0 ) {
                return sprintf( _n( '%d month ago', '%d months ago', $interval->m, 'money-quiz' ), $interval->m );
            } elseif ( $interval->d > 0 ) {
                return sprintf( _n( '%d day ago', '%d days ago', $interval->d, 'money-quiz' ), $interval->d );
            } elseif ( $interval->h > 0 ) {
                return sprintf( _n( '%d hour ago', '%d hours ago', $interval->h, 'money-quiz' ), $interval->h );
            } elseif ( $interval->i > 0 ) {
                return sprintf( _n( '%d minute ago', '%d minutes ago', $interval->i, 'money-quiz' ), $interval->i );
            } else {
                return __( 'just now', 'money-quiz' );
            }
        } catch ( Exception $e ) {
            return $date;
        }
    }
    
    /**
     * Add time to date
     * 
     * @param string $date Date string
     * @param string $interval Interval to add
     * @return string
     */
    public static function add( $date, $interval ) {
        try {
            $datetime = new DateTime( $date );
            $datetime->modify( $interval );
            return $datetime->format( 'Y-m-d H:i:s' );
        } catch ( Exception $e ) {
            return $date;
        }
    }
    
    /**
     * Get date range
     * 
     * @param string $start Start date
     * @param string $end End date
     * @param string $interval Date interval
     * @return array
     */
    public static function range( $start, $end, $interval = 'P1D' ) {
        $dates = array();
        
        try {
            $start_date = new DateTime( $start );
            $end_date = new DateTime( $end );
            $interval_obj = new \DateInterval( $interval );
            
            $period = new \DatePeriod( $start_date, $interval_obj, $end_date );
            
            foreach ( $period as $date ) {
                $dates[] = $date->format( 'Y-m-d' );
            }
            
            // Include end date
            $dates[] = $end_date->format( 'Y-m-d' );
            
        } catch ( Exception $e ) {
            return array();
        }
        
        return $dates;
    }
}

/**
 * Format Utility Class
 * 
 * Provides data formatting functions
 */
class FormatUtil {
    
    /**
     * Format currency
     * 
     * @param float  $amount Amount to format
     * @param string $currency Currency code
     * @return string
     */
    public static function currency( $amount, $currency = 'USD' ) {
        $symbols = array(
            'USD' => '$',
            'EUR' => '€',
            'GBP' => '£',
            'JPY' => '¥',
            'CHF' => 'CHF'
        );
        
        $symbol = $symbols[ $currency ] ?? $currency . ' ';
        
        // Format based on currency
        if ( $currency === 'EUR' ) {
            return number_format( $amount, 2, ',', '.' ) . ' ' . $symbol;
        } elseif ( $currency === 'CHF' ) {
            return $symbol . ' ' . number_format( $amount, 2, '.', "'" );
        } else {
            return $symbol . number_format( $amount, 2 );
        }
    }
    
    /**
     * Format percentage
     * 
     * @param float $value Value to format
     * @param int   $decimals Number of decimals
     * @return string
     */
    public static function percentage( $value, $decimals = 0 ) {
        return number_format( $value, $decimals ) . '%';
    }
    
    /**
     * Format number
     * 
     * @param float $number Number to format
     * @param int   $decimals Number of decimals
     * @return string
     */
    public static function number( $number, $decimals = 0 ) {
        return number_format( $number, $decimals );
    }
    
    /**
     * Format file size
     * 
     * @param int $bytes Size in bytes
     * @param int $decimals Number of decimals
     * @return string
     */
    public static function filesize( $bytes, $decimals = 2 ) {
        $units = array( 'B', 'KB', 'MB', 'GB', 'TB' );
        
        $bytes = max( $bytes, 0 );
        $pow = floor( ( $bytes ? log( $bytes ) : 0 ) / log( 1024 ) );
        $pow = min( $pow, count( $units ) - 1 );
        
        $bytes /= pow( 1024, $pow );
        
        return round( $bytes, $decimals ) . ' ' . $units[ $pow ];
    }
    
    /**
     * Format phone number
     * 
     * @param string $phone Phone number
     * @param string $format Format pattern
     * @return string
     */
    public static function phone( $phone, $format = '($1) $2-$3' ) {
        // Remove non-numeric characters
        $phone = preg_replace( '/[^0-9]/', '', $phone );
        
        // Format based on length
        if ( strlen( $phone ) === 10 ) {
            return preg_replace( '/(\d{3})(\d{3})(\d{4})/', $format, $phone );
        } elseif ( strlen( $phone ) === 11 ) {
            return preg_replace( '/(\d{1})(\d{3})(\d{3})(\d{4})/', '+$1 ($2) $3-$4', $phone );
        }
        
        return $phone;
    }
}

/**
 * Security Utility Class
 * 
 * Provides security-related functions
 */
class SecurityUtil {
    
    /**
     * Generate nonce
     * 
     * @param string $action Nonce action
     * @return string
     */
    public static function create_nonce( $action ) {
        return wp_create_nonce( 'money_quiz_' . $action );
    }
    
    /**
     * Verify nonce
     * 
     * @param string $nonce Nonce value
     * @param string $action Nonce action
     * @return bool
     */
    public static function verify_nonce( $nonce, $action ) {
        return wp_verify_nonce( $nonce, 'money_quiz_' . $action );
    }
    
    /**
     * Check user capability
     * 
     * @param string $capability Capability to check
     * @param int    $user_id User ID (optional)
     * @return bool
     */
    public static function can( $capability, $user_id = null ) {
        if ( is_null( $user_id ) ) {
            return current_user_can( $capability );
        }
        
        return user_can( $user_id, $capability );
    }
    
    /**
     * Sanitize input array
     * 
     * @param array $data Data to sanitize
     * @param array $rules Sanitization rules
     * @return array
     */
    public static function sanitize_array( array $data, array $rules ) {
        $sanitized = array();
        
        foreach ( $rules as $field => $type ) {
            if ( isset( $data[ $field ] ) ) {
                $sanitized[ $field ] = static::sanitize_value( $data[ $field ], $type );
            }
        }
        
        return $sanitized;
    }
    
    /**
     * Sanitize single value
     * 
     * @param mixed  $value Value to sanitize
     * @param string $type Sanitization type
     * @return mixed
     */
    public static function sanitize_value( $value, $type ) {
        switch ( $type ) {
            case 'email':
                return sanitize_email( $value );
            case 'url':
                return esc_url_raw( $value );
            case 'int':
                return intval( $value );
            case 'float':
                return floatval( $value );
            case 'bool':
                return filter_var( $value, FILTER_VALIDATE_BOOLEAN );
            case 'textarea':
                return sanitize_textarea_field( $value );
            case 'html':
                return wp_kses_post( $value );
            case 'key':
                return sanitize_key( $value );
            case 'text':
            default:
                return sanitize_text_field( $value );
        }
    }
    
    /**
     * Generate secure token
     * 
     * @param int $length Token length
     * @return string
     */
    public static function generate_token( $length = 32 ) {
        return bin2hex( random_bytes( $length / 2 ) );
    }
}

/**
 * Cache Utility Class
 * 
 * Provides caching functions
 */
class CacheUtil {
    
    /**
     * Cache prefix
     * 
     * @var string
     */
    const PREFIX = 'money_quiz_';
    
    /**
     * Get cached value
     * 
     * @param string $key Cache key
     * @param mixed  $default Default value
     * @return mixed
     */
    public static function get( $key, $default = null ) {
        $value = get_transient( self::PREFIX . $key );
        return $value !== false ? $value : $default;
    }
    
    /**
     * Set cached value
     * 
     * @param string $key Cache key
     * @param mixed  $value Value to cache
     * @param int    $expiration Expiration in seconds
     * @return bool
     */
    public static function set( $key, $value, $expiration = 3600 ) {
        return set_transient( self::PREFIX . $key, $value, $expiration );
    }
    
    /**
     * Delete cached value
     * 
     * @param string $key Cache key
     * @return bool
     */
    public static function delete( $key ) {
        return delete_transient( self::PREFIX . $key );
    }
    
    /**
     * Clear all cache
     * 
     * @return bool
     */
    public static function clear() {
        global $wpdb;
        
        $transients = $wpdb->get_col(
            $wpdb->prepare(
                "SELECT option_name FROM {$wpdb->options} 
                WHERE option_name LIKE %s 
                OR option_name LIKE %s",
                '_transient_' . self::PREFIX . '%',
                '_transient_timeout_' . self::PREFIX . '%'
            )
        );
        
        foreach ( $transients as $transient ) {
            delete_option( $transient );
        }
        
        return true;
    }
    
    /**
     * Remember value in cache
     * 
     * @param string   $key Cache key
     * @param callable $callback Callback to get value
     * @param int      $expiration Expiration in seconds
     * @return mixed
     */
    public static function remember( $key, callable $callback, $expiration = 3600 ) {
        $value = self::get( $key );
        
        if ( is_null( $value ) ) {
            $value = $callback();
            self::set( $key, $value, $expiration );
        }
        
        return $value;
    }
}

/**
 * Debug Utility Class
 * 
 * Provides debugging functions
 */
class DebugUtil {
    
    /**
     * Log debug message
     * 
     * @param mixed  $message Message to log
     * @param string $level Log level
     */
    public static function log( $message, $level = 'info' ) {
        if ( ! defined( 'WP_DEBUG' ) || ! WP_DEBUG ) {
            return;
        }
        
        if ( is_array( $message ) || is_object( $message ) ) {
            $message = print_r( $message, true );
        }
        
        $backtrace = debug_backtrace();
        $file = $backtrace[0]['file'] ?? 'unknown';
        $line = $backtrace[0]['line'] ?? 0;
        
        error_log( sprintf(
            '[Money Quiz %s] %s in %s:%d',
            strtoupper( $level ),
            $message,
            $file,
            $line
        ));
    }
    
    /**
     * Timer start
     * 
     * @param string $name Timer name
     */
    public static function timer_start( $name ) {
        if ( ! defined( 'WP_DEBUG' ) || ! WP_DEBUG ) {
            return;
        }
        
        $GLOBALS['money_quiz_timers'][ $name ] = microtime( true );
    }
    
    /**
     * Timer stop
     * 
     * @param string $name Timer name
     * @return float Elapsed time
     */
    public static function timer_stop( $name ) {
        if ( ! defined( 'WP_DEBUG' ) || ! WP_DEBUG ) {
            return 0;
        }
        
        if ( ! isset( $GLOBALS['money_quiz_timers'][ $name ] ) ) {
            return 0;
        }
        
        $elapsed = microtime( true ) - $GLOBALS['money_quiz_timers'][ $name ];
        unset( $GLOBALS['money_quiz_timers'][ $name ] );
        
        self::log( sprintf( 'Timer %s: %f seconds', $name, $elapsed ) );
        
        return $elapsed;
    }
    
    /**
     * Memory usage
     * 
     * @param string $label Label for the measurement
     */
    public static function memory( $label = '' ) {
        if ( ! defined( 'WP_DEBUG' ) || ! WP_DEBUG ) {
            return;
        }
        
        $usage = memory_get_usage( true );
        $peak = memory_get_peak_usage( true );
        
        self::log( sprintf(
            'Memory %s: Current %s, Peak %s',
            $label,
            FormatUtil::filesize( $usage ),
            FormatUtil::filesize( $peak )
        ));
    }
}

/**
 * Response Utility Class
 * 
 * Provides response helper functions
 */
class ResponseUtil {
    
    /**
     * Send JSON success response
     * 
     * @param mixed $data Response data
     * @param int   $status_code HTTP status code
     */
    public static function success( $data = null, $status_code = 200 ) {
        wp_send_json_success( $data, $status_code );
    }
    
    /**
     * Send JSON error response
     * 
     * @param string $message Error message
     * @param int    $status_code HTTP status code
     * @param array  $data Additional data
     */
    public static function error( $message, $status_code = 400, $data = array() ) {
        wp_send_json_error( array_merge( array(
            'message' => $message
        ), $data ), $status_code );
    }
    
    /**
     * Send redirect response
     * 
     * @param string $url Redirect URL
     * @param int    $status Status code
     */
    public static function redirect( $url, $status = 302 ) {
        wp_redirect( $url, $status );
        exit;
    }
    
    /**
     * Send file download
     * 
     * @param string $file File path
     * @param string $filename Download filename
     */
    public static function download( $file, $filename = null ) {
        if ( ! file_exists( $file ) ) {
            wp_die( __( 'File not found', 'money-quiz' ) );
        }
        
        if ( is_null( $filename ) ) {
            $filename = basename( $file );
        }
        
        header( 'Content-Type: application/octet-stream' );
        header( 'Content-Disposition: attachment; filename="' . $filename . '"' );
        header( 'Content-Length: ' . filesize( $file ) );
        
        readfile( $file );
        exit;
    }
}

/**
 * URL Utility Class
 * 
 * Provides URL manipulation functions
 */
class UrlUtil {
    
    /**
     * Build URL with query parameters
     * 
     * @param string $url Base URL
     * @param array  $params Query parameters
     * @return string
     */
    public static function build( $url, array $params = array() ) {
        if ( empty( $params ) ) {
            return $url;
        }
        
        $query = http_build_query( $params );
        $separator = strpos( $url, '?' ) !== false ? '&' : '?';
        
        return $url . $separator . $query;
    }
    
    /**
     * Get current URL
     * 
     * @return string
     */
    public static function current() {
        $protocol = isset( $_SERVER['HTTPS'] ) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
        $host = $_SERVER['HTTP_HOST'] ?? '';
        $uri = $_SERVER['REQUEST_URI'] ?? '';
        
        return $protocol . '://' . $host . $uri;
    }
    
    /**
     * Parse URL query parameters
     * 
     * @param string $url URL to parse
     * @return array
     */
    public static function parse_query( $url ) {
        $parts = parse_url( $url );
        
        if ( ! isset( $parts['query'] ) ) {
            return array();
        }
        
        parse_str( $parts['query'], $params );
        return $params;
    }
    
    /**
     * Remove query parameter from URL
     * 
     * @param string $url URL
     * @param string $param Parameter to remove
     * @return string
     */
    public static function remove_query_param( $url, $param ) {
        $params = self::parse_query( $url );
        unset( $params[ $param ] );
        
        $parts = parse_url( $url );
        $base = $parts['scheme'] . '://' . $parts['host'] . $parts['path'];
        
        return self::build( $base, $params );
    }
}

/**
 * Helper Functions
 * 
 * Global helper functions for convenience
 */

if ( ! function_exists( 'money_quiz_array_get' ) ) {
    /**
     * Get array value using dot notation
     * 
     * @param array  $array Array to search
     * @param string $key Key in dot notation
     * @param mixed  $default Default value
     * @return mixed
     */
    function money_quiz_array_get( array $array, $key, $default = null ) {
        return ArrayUtil::get( $array, $key, $default );
    }
}

if ( ! function_exists( 'money_quiz_format_currency' ) ) {
    /**
     * Format currency
     * 
     * @param float  $amount Amount
     * @param string $currency Currency code
     * @return string
     */
    function money_quiz_format_currency( $amount, $currency = 'USD' ) {
        return FormatUtil::currency( $amount, $currency );
    }
}

if ( ! function_exists( 'money_quiz_cache' ) ) {
    /**
     * Cache helper
     * 
     * @param string   $key Cache key
     * @param callable $callback Callback if not cached
     * @param int      $expiration Expiration in seconds
     * @return mixed
     */
    function money_quiz_cache( $key, callable $callback = null, $expiration = 3600 ) {
        if ( is_null( $callback ) ) {
            return CacheUtil::get( $key );
        }
        
        return CacheUtil::remember( $key, $callback, $expiration );
    }
}

if ( ! function_exists( 'money_quiz_log' ) ) {
    /**
     * Debug log helper
     * 
     * @param mixed  $message Message to log
     * @param string $level Log level
     */
    function money_quiz_log( $message, $level = 'info' ) {
        DebugUtil::log( $message, $level );
    }
}

if ( ! function_exists( 'money_quiz_response' ) ) {
    /**
     * Response helper
     * 
     * @param bool   $success Success status
     * @param mixed  $data Response data
     * @param string $message Error message
     * @param int    $status_code HTTP status code
     */
    function money_quiz_response( $success = true, $data = null, $message = '', $status_code = null ) {
        if ( $success ) {
            ResponseUtil::success( $data, $status_code ?? 200 );
        } else {
            ResponseUtil::error( $message ?: __( 'An error occurred', 'money-quiz' ), $status_code ?? 400, (array) $data );
        }
    }
}
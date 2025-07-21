<?php
/**
 * Security Headers Core Definitions
 * 
 * @package MoneyQuiz\Security\Headers
 * @version 1.0.0
 */

namespace MoneyQuiz\Security\Headers;

/**
 * Security Header Constants
 */
interface SecurityHeaderConstants {
    // Standard Security Headers
    const STRICT_TRANSPORT_SECURITY = 'Strict-Transport-Security';
    const CONTENT_SECURITY_POLICY = 'Content-Security-Policy';
    const X_FRAME_OPTIONS = 'X-Frame-Options';
    const X_CONTENT_TYPE_OPTIONS = 'X-Content-Type-Options';
    const X_XSS_PROTECTION = 'X-XSS-Protection';
    const REFERRER_POLICY = 'Referrer-Policy';
    const PERMISSIONS_POLICY = 'Permissions-Policy';
    const EXPECT_CT = 'Expect-CT';
    const FEATURE_POLICY = 'Feature-Policy';
    
    // CORS Headers
    const ACCESS_CONTROL_ALLOW_ORIGIN = 'Access-Control-Allow-Origin';
    const ACCESS_CONTROL_ALLOW_METHODS = 'Access-Control-Allow-Methods';
    const ACCESS_CONTROL_ALLOW_HEADERS = 'Access-Control-Allow-Headers';
    const ACCESS_CONTROL_MAX_AGE = 'Access-Control-Max-Age';
}

/**
 * Header Configuration
 */
class HeaderConfig {
    
    /**
     * Default security headers
     */
    private static $defaults = [
        SecurityHeaderConstants::STRICT_TRANSPORT_SECURITY => [
            'value' => 'max-age=31536000; includeSubDomains',
            'condition' => 'is_ssl'
        ],
        SecurityHeaderConstants::CONTENT_SECURITY_POLICY => [
            'value' => "default-src 'self'; script-src 'self' 'unsafe-inline' 'unsafe-eval'; style-src 'self' 'unsafe-inline'; img-src 'self' data: https:; font-src 'self' data:;",
            'condition' => 'always'
        ],
        SecurityHeaderConstants::X_FRAME_OPTIONS => [
            'value' => 'SAMEORIGIN',
            'condition' => 'always'
        ],
        SecurityHeaderConstants::X_CONTENT_TYPE_OPTIONS => [
            'value' => 'nosniff',
            'condition' => 'always'
        ],
        SecurityHeaderConstants::X_XSS_PROTECTION => [
            'value' => '1; mode=block',
            'condition' => 'always'
        ],
        SecurityHeaderConstants::REFERRER_POLICY => [
            'value' => 'strict-origin-when-cross-origin',
            'condition' => 'always'
        ],
        SecurityHeaderConstants::PERMISSIONS_POLICY => [
            'value' => 'geolocation=(), microphone=(), camera=()',
            'condition' => 'always'
        ]
    ];
    
    /**
     * Get all default headers
     */
    public static function getDefaults() {
        return apply_filters('money_quiz_security_headers', self::$defaults);
    }
    
    /**
     * Get header value
     */
    public static function getHeaderValue($header) {
        $headers = self::getDefaults();
        return $headers[$header]['value'] ?? null;
    }
    
    /**
     * Check if header should be applied
     */
    public static function shouldApplyHeader($header) {
        $headers = self::getDefaults();
        
        if (!isset($headers[$header])) {
            return false;
        }
        
        $condition = $headers[$header]['condition'];
        
        switch ($condition) {
            case 'always':
                return true;
            case 'is_ssl':
                return is_ssl();
            case 'is_admin':
                return is_admin();
            case 'is_api':
                return defined('REST_REQUEST') && REST_REQUEST;
            default:
                return apply_filters('money_quiz_header_condition', true, $header, $condition);
        }
    }
}
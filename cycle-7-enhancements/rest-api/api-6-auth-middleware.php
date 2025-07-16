<?php
/**
 * REST API Authentication Middleware
 * 
 * @package MoneyQuiz\API
 * @version 1.0.0
 */

namespace MoneyQuiz\API;

/**
 * Authentication Middleware
 */
class AuthMiddleware {
    
    /**
     * Verify authentication
     */
    public static function verify($request) {
        // Check nonce for cookie auth
        if (is_user_logged_in()) {
            return self::verifyCookieAuth($request);
        }
        
        // Check for API key
        $api_key = $request->get_header('X-API-Key');
        if ($api_key) {
            return self::verifyApiKey($api_key);
        }
        
        // Check for JWT token
        $auth_header = $request->get_header('Authorization');
        if ($auth_header) {
            return self::verifyJwt($auth_header);
        }
        
        // No authentication provided
        return true; // Allow public access
    }
    
    /**
     * Verify cookie authentication
     */
    private static function verifyCookieAuth($request) {
        $nonce = $request->get_header('X-WP-Nonce');
        
        if (!$nonce) {
            return new \WP_Error(
                'missing_nonce',
                'Nonce required for cookie authentication',
                ['status' => 403]
            );
        }
        
        if (!wp_verify_nonce($nonce, 'wp_rest')) {
            return new \WP_Error(
                'invalid_nonce',
                'Invalid nonce',
                ['status' => 403]
            );
        }
        
        return true;
    }
    
    /**
     * Verify API key
     */
    private static function verifyApiKey($api_key) {
        $valid_keys = get_option('money_quiz_api_keys', []);
        
        foreach ($valid_keys as $key_data) {
            if (hash_equals($key_data['key'], $api_key)) {
                // Set current user if associated
                if ($key_data['user_id']) {
                    wp_set_current_user($key_data['user_id']);
                }
                
                // Log API key usage
                do_action('money_quiz_api_key_used', $key_data);
                
                return true;
            }
        }
        
        return new \WP_Error(
            'invalid_api_key',
            'Invalid API key',
            ['status' => 401]
        );
    }
    
    /**
     * Verify JWT token
     */
    private static function verifyJwt($auth_header) {
        if (!preg_match('/Bearer\s+(.*)$/i', $auth_header, $matches)) {
            return new \WP_Error(
                'invalid_auth_header',
                'Invalid authorization header format',
                ['status' => 401]
            );
        }
        
        $token = $matches[1];
        
        // Decode JWT (simplified - use proper JWT library in production)
        $parts = explode('.', $token);
        if (count($parts) !== 3) {
            return new \WP_Error(
                'invalid_token',
                'Invalid token format',
                ['status' => 401]
            );
        }
        
        // Verify token
        $payload = json_decode(base64_decode($parts[1]), true);
        
        if (!$payload || !isset($payload['user_id'])) {
            return new \WP_Error(
                'invalid_token_payload',
                'Invalid token payload',
                ['status' => 401]
            );
        }
        
        // Check expiration
        if (isset($payload['exp']) && $payload['exp'] < time()) {
            return new \WP_Error(
                'token_expired',
                'Token has expired',
                ['status' => 401]
            );
        }
        
        // Set current user
        wp_set_current_user($payload['user_id']);
        
        return true;
    }
    
    /**
     * Require authentication
     */
    public static function requireAuth($request) {
        if (!is_user_logged_in()) {
            return new \WP_Error(
                'authentication_required',
                'Authentication required',
                ['status' => 401]
            );
        }
        
        return self::verify($request);
    }
    
    /**
     * Check capability
     */
    public static function checkCapability($capability) {
        return function($request) use ($capability) {
            $auth_result = self::verify($request);
            
            if (is_wp_error($auth_result)) {
                return $auth_result;
            }
            
            if (!current_user_can($capability)) {
                return new \WP_Error(
                    'insufficient_permissions',
                    'Insufficient permissions',
                    ['status' => 403]
                );
            }
            
            return true;
        };
    }
}
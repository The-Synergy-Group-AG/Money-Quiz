<?php
/**
 * CSRF Token Manager
 *
 * @package MoneyQuiz
 * @since 4.1.0
 */

namespace MoneyQuiz\Security;

use MoneyQuiz\Interfaces\SecurityInterface;
use MoneyQuiz\Exceptions\SecurityException;

/**
 * Manages CSRF token generation, validation, and middleware
 */
class CsrfManager implements SecurityInterface {
    
    /**
     * @var string Token session key
     */
    private const SESSION_KEY = 'money_quiz_csrf_tokens';
    
    /**
     * @var string Token field name
     */
    private const TOKEN_FIELD = '_mq_csrf_token';
    
    /**
     * @var int Token lifetime in seconds (4 hours)
     */
    private const TOKEN_LIFETIME = 14400;
    
    /**
     * @var int Maximum tokens per session
     */
    private const MAX_TOKENS = 20;
    
    /**
     * @var array<string, array{token: string, expires: int}> Active tokens
     */
    private array $tokens = [];
    
    /**
     * Constructor
     */
    public function __construct() {
        $this->initialize_session();
        $this->load_tokens();
        $this->cleanup_expired();
    }
    
    /**
     * Generate a new CSRF token
     * 
     * @param string $action Action identifier
     * @return string
     */
    public function generate_token( string $action = 'default' ): string {
        $token = $this->create_token();
        $expires = time() + self::TOKEN_LIFETIME;
        
        // Store token with expiration
        $this->tokens[ $action ][] = [
            'token' => $token,
            'expires' => $expires,
        ];
        
        // Limit tokens per action
        if ( count( $this->tokens[ $action ] ) > self::MAX_TOKENS ) {
            array_shift( $this->tokens[ $action ] );
        }
        
        $this->save_tokens();
        
        return $token;
    }
    
    /**
     * Validate a CSRF token
     * 
     * @param string      $token Token to validate
     * @param string      $action Action identifier
     * @param bool        $remove Remove token after validation
     * @return bool
     */
    public function validate_token( string $token, string $action = 'default', bool $remove = true ): bool {
        if ( empty( $token ) ) {
            return false;
        }
        
        // Check if action has tokens
        if ( ! isset( $this->tokens[ $action ] ) ) {
            return false;
        }
        
        // Look for valid token
        foreach ( $this->tokens[ $action ] as $key => $stored ) {
            if ( hash_equals( $stored['token'], $token ) && $stored['expires'] > time() ) {
                if ( $remove ) {
                    unset( $this->tokens[ $action ][ $key ] );
                    $this->tokens[ $action ] = array_values( $this->tokens[ $action ] );
                    $this->save_tokens();
                }
                return true;
            }
        }
        
        return false;
    }
    
    /**
     * Get token field HTML
     * 
     * @param string $action Action identifier
     * @return string
     */
    public function get_token_field( string $action = 'default' ): string {
        $token = $this->generate_token( $action );
        
        return sprintf(
            '<input type="hidden" name="%s" value="%s" />',
            esc_attr( self::TOKEN_FIELD ),
            esc_attr( $token )
        );
    }
    
    /**
     * Verify request has valid CSRF token
     * 
     * @param string $action Action identifier
     * @throws SecurityException If token is invalid
     */
    public function verify_request( string $action = 'default' ): void {
        $token = $this->get_request_token();
        
        if ( ! $this->validate_token( $token, $action ) ) {
            throw new SecurityException( 'Invalid CSRF token.' );
        }
    }
    
    /**
     * Get token from request
     * 
     * @return string
     */
    public function get_request_token(): string {
        // Check POST
        if ( isset( $_POST[ self::TOKEN_FIELD ] ) ) {
            return sanitize_text_field( wp_unslash( $_POST[ self::TOKEN_FIELD ] ) );
        }
        
        // Check GET
        if ( isset( $_GET[ self::TOKEN_FIELD ] ) ) {
            return sanitize_text_field( wp_unslash( $_GET[ self::TOKEN_FIELD ] ) );
        }
        
        // Check headers
        $headers = $this->get_all_headers();
        if ( isset( $headers['X-CSRF-Token'] ) ) {
            return sanitize_text_field( $headers['X-CSRF-Token'] );
        }
        
        return '';
    }
    
    /**
     * Add CSRF token to URL
     * 
     * @param string $url URL to add token to
     * @param string $action Action identifier
     * @return string
     */
    public function add_token_to_url( string $url, string $action = 'default' ): string {
        $token = $this->generate_token( $action );
        
        return add_query_arg( self::TOKEN_FIELD, $token, $url );
    }
    
    /**
     * Get JavaScript for AJAX requests
     * 
     * @param string $action Action identifier
     * @return string
     */
    public function get_ajax_script( string $action = 'default' ): string {
        $token = $this->generate_token( $action );
        
        return sprintf(
            'window.moneyQuizCSRF = { token: "%s", field: "%s" };',
            esc_js( $token ),
            esc_js( self::TOKEN_FIELD )
        );
    }
    
    /**
     * Middleware for automatic CSRF validation
     * 
     * @param array  $actions Actions to protect
     * @param string $token_action Token action identifier
     * @return void
     */
    public function protect_actions( array $actions, string $token_action = 'default' ): void {
        foreach ( $actions as $action ) {
            add_action( 'wp_ajax_' . $action, function() use ( $token_action ) {
                $this->verify_request( $token_action );
            }, 1 );
            
            add_action( 'wp_ajax_nopriv_' . $action, function() use ( $token_action ) {
                $this->verify_request( $token_action );
            }, 1 );
        }
    }
    
    /**
     * Clear all tokens for an action
     * 
     * @param string $action Action identifier
     * @return void
     */
    public function clear_tokens( string $action = '' ): void {
        if ( $action ) {
            unset( $this->tokens[ $action ] );
        } else {
            $this->tokens = [];
        }
        
        $this->save_tokens();
    }
    
    /**
     * Create a cryptographically secure token
     * 
     * @return string
     */
    private function create_token(): string {
        return bin2hex( random_bytes( 32 ) );
    }
    
    /**
     * Initialize session if needed
     * 
     * @return void
     */
    private function initialize_session(): void {
        if ( ! session_id() && ! headers_sent() ) {
            session_start();
        }
    }
    
    /**
     * Load tokens from session
     * 
     * @return void
     */
    private function load_tokens(): void {
        if ( isset( $_SESSION[ self::SESSION_KEY ] ) ) {
            $this->tokens = $_SESSION[ self::SESSION_KEY ];
        }
    }
    
    /**
     * Save tokens to session
     * 
     * @return void
     */
    private function save_tokens(): void {
        $_SESSION[ self::SESSION_KEY ] = $this->tokens;
    }
    
    /**
     * Clean up expired tokens
     * 
     * @return void
     */
    private function cleanup_expired(): void {
        $now = time();
        $cleaned = false;
        
        foreach ( $this->tokens as $action => &$tokens ) {
            $tokens = array_filter( $tokens, function( $token ) use ( $now ) {
                return $token['expires'] > $now;
            } );
            
            if ( empty( $tokens ) ) {
                unset( $this->tokens[ $action ] );
                $cleaned = true;
            }
        }
        
        if ( $cleaned ) {
            $this->save_tokens();
        }
    }
    
    /**
     * Get all request headers
     * 
     * @return array<string, string>
     */
    private function get_all_headers(): array {
        if ( function_exists( 'getallheaders' ) ) {
            return getallheaders();
        }
        
        $headers = [];
        foreach ( $_SERVER as $name => $value ) {
            if ( substr( $name, 0, 5 ) === 'HTTP_' ) {
                $headers[ str_replace( ' ', '-', ucwords( strtolower( str_replace( '_', ' ', substr( $name, 5 ) ) ) ) ) ] = $value;
            }
        }
        
        return $headers;
    }
    
    /**
     * Get token statistics
     * 
     * @return array{total: int, by_action: array<string, int>, expired: int}
     */
    public function get_stats(): array {
        $total = 0;
        $by_action = [];
        $expired = 0;
        $now = time();
        
        foreach ( $this->tokens as $action => $tokens ) {
            $by_action[ $action ] = count( $tokens );
            $total += count( $tokens );
            
            foreach ( $tokens as $token ) {
                if ( $token['expires'] <= $now ) {
                    $expired++;
                }
            }
        }
        
        return [
            'total' => $total,
            'by_action' => $by_action,
            'expired' => $expired,
        ];
    }
}
<?php
/**
 * Security Headers Manager
 *
 * Manages security headers including CSP with nonce support.
 *
 * @package MoneyQuiz\Security
 * @since   7.0.0
 */

namespace MoneyQuiz\Security;

// Security: Prevent direct access.
defined('ABSPATH') || exit;

/**
 * Security headers class.
 *
 * @since 7.0.0
 */
class SecurityHeaders {
    
    /**
     * CSP nonce for inline scripts/styles.
     *
     * @var string
     */
    private string $csp_nonce;
    
    /**
     * Constructor.
     */
    public function __construct() {
        $this->csp_nonce = wp_create_nonce('money_quiz_csp');
    }
    
    /**
     * Initialize security headers.
     */
    public function init(): void {
        add_action('send_headers', [$this, 'send_headers']);
        add_filter('wp_inline_script_attributes', [$this, 'add_script_nonce']);
        add_filter('wp_inline_style_attributes', [$this, 'add_style_nonce']);
    }
    
    /**
     * Send security headers.
     */
    public function send_headers(): void {
        // Only on frontend and our admin pages
        if (!$this->should_send_headers()) {
            return;
        }
        
        // Content Security Policy with nonce
        $this->send_csp_header();
        
        // Other security headers
        header('X-Content-Type-Options: nosniff');
        header('X-Frame-Options: SAMEORIGIN');
        header('X-XSS-Protection: 1; mode=block');
        header('Referrer-Policy: strict-origin-when-cross-origin');
        header('Permissions-Policy: geolocation=(), microphone=(), camera=()');
        
        // HSTS for HTTPS sites
        if (is_ssl()) {
            header('Strict-Transport-Security: max-age=31536000; includeSubDomains');
        }
    }
    
    /**
     * Send Content Security Policy header.
     */
    private function send_csp_header(): void {
        $directives = [
            "default-src 'self'",
            "script-src 'self' 'nonce-{$this->csp_nonce}'",
            "style-src 'self' 'nonce-{$this->csp_nonce}'",
            "img-src 'self' data: https:",
            "font-src 'self' data:",
            "connect-src 'self'",
            "media-src 'self'",
            "object-src 'none'",
            "frame-src 'none'",
            "base-uri 'self'",
            "form-action 'self'",
            "upgrade-insecure-requests"
        ];
        
        // Allow specific CDNs if needed
        $directives = apply_filters('money_quiz_csp_directives', $directives);
        
        $csp = implode('; ', $directives);
        header("Content-Security-Policy: $csp");
    }
    
    /**
     * Check if headers should be sent.
     *
     * @return bool True if headers should be sent.
     */
    private function should_send_headers(): bool {
        // Skip on AJAX requests
        if (wp_doing_ajax()) {
            return false;
        }
        
        // Skip on REST API
        if (defined('REST_REQUEST') && REST_REQUEST) {
            return false;
        }
        
        // Only on our pages
        if (is_admin()) {
            $screen = get_current_screen();
            return $screen && strpos($screen->id, 'money-quiz') !== false;
        }
        
        return true;
    }
    
    /**
     * Add nonce to inline scripts.
     *
     * @param array $attributes Script attributes.
     * @return array Modified attributes.
     */
    public function add_script_nonce(array $attributes): array {
        $attributes['nonce'] = $this->csp_nonce;
        return $attributes;
    }
    
    /**
     * Add nonce to inline styles.
     *
     * @param array $attributes Style attributes.
     * @return array Modified attributes.
     */
    public function add_style_nonce(array $attributes): array {
        $attributes['nonce'] = $this->csp_nonce;
        return $attributes;
    }
    
    /**
     * Get CSP nonce for templates.
     *
     * @return string CSP nonce.
     */
    public function get_csp_nonce(): string {
        return $this->csp_nonce;
    }
    
    /**
     * Generate script tag with nonce.
     *
     * @param string $content Script content.
     * @return string Script tag with nonce.
     */
    public function script_tag(string $content): string {
        return sprintf(
            '<script nonce="%s">%s</script>',
            esc_attr($this->csp_nonce),
            $content
        );
    }
    
    /**
     * Generate style tag with nonce.
     *
     * @param string $content Style content.
     * @return string Style tag with nonce.
     */
    public function style_tag(string $content): string {
        return sprintf(
            '<style nonce="%s">%s</style>',
            esc_attr($this->csp_nonce),
            $content
        );
    }
}
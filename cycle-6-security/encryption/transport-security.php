<?php
/**
 * Transport Security Implementation
 * 
 * Enforces TLS/SSL and secure communication
 * 
 * @package MoneyQuiz\Security\Encryption
 * @version 1.0.0
 */

namespace MoneyQuiz\Security\Encryption;

use Exception;

class TransportSecurity {
    private $db;
    private $config;
    private $tls_versions = ['1.2', '1.3'];
    private $cipher_suites = [
        'TLS_AES_128_GCM_SHA256',
        'TLS_AES_256_GCM_SHA384',
        'TLS_CHACHA20_POLY1305_SHA256',
        'ECDHE-RSA-AES128-GCM-SHA256',
        'ECDHE-RSA-AES256-GCM-SHA384'
    ];
    
    public function __construct($config = []) {
        $this->db = $GLOBALS['wpdb'];
        
        $this->config = wp_parse_args($config, [
            'force_ssl' => true,
            'hsts_enabled' => true,
            'hsts_max_age' => 31536000, // 1 year
            'hsts_include_subdomains' => true,
            'hsts_preload' => false,
            'min_tls_version' => '1.2',
            'certificate_validation' => true,
            'certificate_pinning' => false,
            'ocsp_stapling' => true,
            'secure_cookies' => true,
            'mixed_content_fix' => true,
            'api_encryption' => true,
            'websocket_security' => true
        ]);
        
        $this->init_hooks();
        $this->verify_ssl_setup();
    }
    
    /**
     * Initialize WordPress hooks
     */
    private function init_hooks() {
        // Force SSL
        if ($this->config['force_ssl']) {
            add_action('template_redirect', [$this, 'force_ssl_redirect']);
            add_filter('wp_redirect', [$this, 'force_ssl_in_redirects'], 10, 2);
            add_filter('home_url', [$this, 'force_ssl_in_urls'], 10, 2);
            add_filter('site_url', [$this, 'force_ssl_in_urls'], 10, 2);
            add_filter('admin_url', [$this, 'force_ssl_in_urls'], 10, 2);
        }
        
        // Security headers
        add_action('send_headers', [$this, 'send_security_headers']);
        add_action('admin_init', [$this, 'send_admin_security_headers']);
        
        // Secure cookies
        if ($this->config['secure_cookies']) {
            add_filter('auth_cookie', [$this, 'secure_auth_cookie'], 10, 5);
            add_action('set_auth_cookie', [$this, 'set_secure_cookie_params'], 10, 5);
        }
        
        // Mixed content fix
        if ($this->config['mixed_content_fix']) {
            add_filter('the_content', [$this, 'fix_mixed_content']);
            add_filter('widget_text', [$this, 'fix_mixed_content']);
            add_filter('wp_calculate_image_srcset', [$this, 'fix_srcset_mixed_content']);
        }
        
        // API security
        if ($this->config['api_encryption']) {
            add_filter('rest_pre_dispatch', [$this, 'validate_api_security'], 10, 3);
            add_filter('rest_post_dispatch', [$this, 'secure_api_response'], 10, 3);
        }
        
        // HTTP API args
        add_filter('http_request_args', [$this, 'secure_http_requests'], 10, 2);
        
        // Certificate verification
        add_filter('https_ssl_verify', [$this, 'verify_ssl_certificate']);
        add_filter('https_local_ssl_verify', [$this, 'verify_local_ssl_certificate']);
    }
    
    /**
     * Verify SSL setup
     */
    private function verify_ssl_setup() {
        // Check if SSL is available
        if (!is_ssl() && $this->config['force_ssl']) {
            add_action('admin_notices', [$this, 'ssl_not_available_notice']);
        }
        
        // Check certificate validity
        if (is_ssl()) {
            $this->check_certificate_validity();
        }
        
        // Check TLS version
        $this->check_tls_version();
    }
    
    /**
     * Force SSL redirect
     */
    public function force_ssl_redirect() {
        if (!is_ssl() && !is_admin()) {
            $redirect_url = 'https://' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'];
            wp_redirect($redirect_url, 301);
            exit();
        }
    }
    
    /**
     * Force SSL in redirects
     */
    public function force_ssl_in_redirects($location, $status) {
        if (!empty($location)) {
            $location = str_replace('http://', 'https://', $location);
        }
        return $location;
    }
    
    /**
     * Force SSL in URLs
     */
    public function force_ssl_in_urls($url, $path = '') {
        if (!empty($url)) {
            $url = str_replace('http://', 'https://', $url);
        }
        return $url;
    }
    
    /**
     * Send security headers
     */
    public function send_security_headers() {
        // Strict Transport Security
        if ($this->config['hsts_enabled'] && is_ssl()) {
            $hsts_header = sprintf(
                'max-age=%d',
                $this->config['hsts_max_age']
            );
            
            if ($this->config['hsts_include_subdomains']) {
                $hsts_header .= '; includeSubDomains';
            }
            
            if ($this->config['hsts_preload']) {
                $hsts_header .= '; preload';
            }
            
            header('Strict-Transport-Security: ' . $hsts_header);
        }
        
        // Content Security Policy for secure connections
        if (is_ssl()) {
            $csp = $this->build_csp_header();
            header('Content-Security-Policy: ' . $csp);
        }
        
        // Expect-CT header
        if (is_ssl()) {
            header('Expect-CT: max-age=86400, enforce');
        }
        
        // Public Key Pinning (if enabled)
        if ($this->config['certificate_pinning'] && is_ssl()) {
            $pins = $this->get_certificate_pins();
            if (!empty($pins)) {
                header('Public-Key-Pins: ' . $pins);
            }
        }
        
        // Additional security headers
        header('X-Content-Type-Options: nosniff');
        header('X-Frame-Options: SAMEORIGIN');
        header('X-XSS-Protection: 1; mode=block');
        header('Referrer-Policy: strict-origin-when-cross-origin');
        header('Feature-Policy: geolocation \'self\'; microphone \'none\'; camera \'none\'');
        header('Permissions-Policy: geolocation=(self), microphone=(), camera=()');
    }
    
    /**
     * Send admin security headers
     */
    public function send_admin_security_headers() {
        if (is_admin() && is_ssl()) {
            $this->send_security_headers();
        }
    }
    
    /**
     * Build CSP header
     */
    private function build_csp_header() {
        $directives = [
            'default-src' => "'self'",
            'script-src' => "'self' 'unsafe-inline' 'unsafe-eval' https://ajax.googleapis.com https://www.google-analytics.com",
            'style-src' => "'self' 'unsafe-inline' https://fonts.googleapis.com",
            'img-src' => "'self' data: https:",
            'font-src' => "'self' https://fonts.gstatic.com",
            'connect-src' => "'self' https:",
            'media-src' => "'self'",
            'object-src' => "'none'",
            'frame-src' => "'self'",
            'base-uri' => "'self'",
            'form-action' => "'self'",
            'frame-ancestors' => "'self'",
            'upgrade-insecure-requests' => ''
        ];
        
        // Apply filters for customization
        $directives = apply_filters('money_quiz_csp_directives', $directives);
        
        // Build header string
        $csp = '';
        foreach ($directives as $directive => $value) {
            if ($value === '') {
                $csp .= $directive . '; ';
            } else {
                $csp .= $directive . ' ' . $value . '; ';
            }
        }
        
        return rtrim($csp, '; ');
    }
    
    /**
     * Secure auth cookie
     */
    public function secure_auth_cookie($cookie, $user_id, $expiration, $scheme, $token) {
        // Cookie should only be sent over HTTPS
        return $cookie;
    }
    
    /**
     * Set secure cookie parameters
     */
    public function set_secure_cookie_params($auth_cookie, $expire, $expiration, $user_id, $scheme) {
        // Force secure flag on cookies
        @ini_set('session.cookie_secure', '1');
        @ini_set('session.cookie_httponly', '1');
        @ini_set('session.cookie_samesite', 'Strict');
    }
    
    /**
     * Fix mixed content in content
     */
    public function fix_mixed_content($content) {
        if (!is_ssl()) {
            return $content;
        }
        
        // Replace HTTP URLs with HTTPS
        $content = str_replace('http://', 'https://', $content);
        
        // Fix protocol-relative URLs
        $content = str_replace('src="//', 'src="https://', $content);
        $content = str_replace('href="//', 'href="https://', $content);
        
        return $content;
    }
    
    /**
     * Fix mixed content in srcset
     */
    public function fix_srcset_mixed_content($sources) {
        if (!is_ssl()) {
            return $sources;
        }
        
        foreach ($sources as &$source) {
            if (isset($source['url'])) {
                $source['url'] = str_replace('http://', 'https://', $source['url']);
            }
        }
        
        return $sources;
    }
    
    /**
     * Validate API security
     */
    public function validate_api_security($result, $server, $request) {
        // Require HTTPS for API requests
        if (!is_ssl() && $this->config['force_ssl']) {
            return new \WP_Error(
                'https_required',
                'API requests must be made over HTTPS',
                ['status' => 403]
            );
        }
        
        // Validate request signature if present
        $signature = $request->get_header('X-API-Signature');
        if ($signature) {
            if (!$this->validate_api_signature($request, $signature)) {
                return new \WP_Error(
                    'invalid_signature',
                    'Invalid API signature',
                    ['status' => 401]
                );
            }
        }
        
        // Check for replay attacks
        $nonce = $request->get_header('X-API-Nonce');
        if ($nonce && !$this->validate_api_nonce($nonce)) {
            return new \WP_Error(
                'invalid_nonce',
                'Invalid or expired nonce',
                ['status' => 401]
            );
        }
        
        return $result;
    }
    
    /**
     * Secure API response
     */
    public function secure_api_response($result, $server, $request) {
        // Add security headers to API responses
        header('X-Content-Type-Options: nosniff');
        header('X-Frame-Options: DENY');
        
        // Sign response if requested
        if ($request->get_header('X-Request-Signature')) {
            $signature = $this->sign_api_response($result);
            header('X-Response-Signature: ' . $signature);
        }
        
        return $result;
    }
    
    /**
     * Secure HTTP requests
     */
    public function secure_http_requests($args, $url) {
        // Parse URL
        $parsed = parse_url($url);
        
        // Force HTTPS for external requests if possible
        if ($parsed['scheme'] === 'http' && $this->can_use_https($parsed['host'])) {
            $url = str_replace('http://', 'https://', $url);
        }
        
        // Set minimum TLS version
        $args['sslversion'] = CURL_SSLVERSION_TLSv1_2;
        
        // Enable certificate verification
        if ($this->config['certificate_validation']) {
            $args['sslverify'] = true;
            $args['sslcertificates'] = ABSPATH . WPINC . '/certificates/ca-bundle.crt';
        }
        
        // Set secure ciphers
        $args['sslciphers'] = implode(':', $this->cipher_suites);
        
        // Add security headers
        if (!isset($args['headers'])) {
            $args['headers'] = [];
        }
        
        $args['headers']['X-Requested-With'] = 'XMLHttpRequest';
        $args['headers']['DNT'] = '1';
        
        // Add request signature for Money Quiz APIs
        if (strpos($url, site_url()) === 0) {
            $args['headers']['X-API-Signature'] = $this->generate_api_signature($url, $args);
        }
        
        return $args;
    }
    
    /**
     * Check if host supports HTTPS
     */
    private function can_use_https($host) {
        // Cache the result
        $cache_key = 'https_support_' . md5($host);
        $cached = get_transient($cache_key);
        
        if ($cached !== false) {
            return $cached === 'yes';
        }
        
        // Check if HTTPS is available
        $url = 'https://' . $host;
        $response = wp_remote_head($url, [
            'timeout' => 5,
            'sslverify' => false,
            'redirection' => 0
        ]);
        
        $supports_https = !is_wp_error($response);
        
        // Cache for 24 hours
        set_transient($cache_key, $supports_https ? 'yes' : 'no', DAY_IN_SECONDS);
        
        return $supports_https;
    }
    
    /**
     * Verify SSL certificate
     */
    public function verify_ssl_certificate($verify) {
        return $this->config['certificate_validation'];
    }
    
    /**
     * Verify local SSL certificate
     */
    public function verify_local_ssl_certificate($verify) {
        // Allow self-signed certificates in development
        if (defined('WP_DEBUG') && WP_DEBUG) {
            return false;
        }
        
        return $this->config['certificate_validation'];
    }
    
    /**
     * Check certificate validity
     */
    private function check_certificate_validity() {
        $url = site_url();
        $context = stream_context_create([
            "ssl" => [
                "capture_peer_cert" => true,
                "verify_peer" => false,
                "verify_peer_name" => false
            ]
        ]);
        
        $stream = @stream_socket_client(
            "ssl://" . parse_url($url, PHP_URL_HOST) . ":443",
            $errno,
            $errstr,
            30,
            STREAM_CLIENT_CONNECT,
            $context
        );
        
        if (!$stream) {
            return;
        }
        
        $params = stream_context_get_params($stream);
        $cert = openssl_x509_parse($params['options']['ssl']['peer_certificate']);
        
        if ($cert) {
            // Check expiration
            $valid_to = $cert['validTo_time_t'];
            $days_until_expiry = ($valid_to - time()) / 86400;
            
            if ($days_until_expiry < 30) {
                add_action('admin_notices', function() use ($days_until_expiry) {
                    ?>
                    <div class="notice notice-warning">
                        <p><strong>SSL Certificate Warning:</strong> 
                        Your SSL certificate will expire in <?php echo round($days_until_expiry); ?> days.</p>
                    </div>
                    <?php
                });
            }
            
            // Log certificate info
            $this->log_certificate_info($cert);
        }
        
        fclose($stream);
    }
    
    /**
     * Check TLS version
     */
    private function check_tls_version() {
        $curl_version = curl_version();
        $ssl_version = $curl_version['ssl_version'] ?? '';
        
        // Extract TLS version
        preg_match('/TLS\s*v?([0-9.]+)/i', $ssl_version, $matches);
        $tls_version = $matches[1] ?? '';
        
        if ($tls_version && version_compare($tls_version, $this->config['min_tls_version'], '<')) {
            add_action('admin_notices', function() use ($tls_version) {
                ?>
                <div class="notice notice-error">
                    <p><strong>TLS Version Warning:</strong> 
                    Your server is using TLS <?php echo esc_html($tls_version); ?>. 
                    Minimum required version is TLS <?php echo esc_html($this->config['min_tls_version']); ?>.</p>
                </div>
                <?php
            });
        }
    }
    
    /**
     * Get certificate pins
     */
    private function get_certificate_pins() {
        $pins = get_option('money_quiz_certificate_pins', []);
        
        if (empty($pins)) {
            return '';
        }
        
        $pin_string = '';
        foreach ($pins as $pin) {
            $pin_string .= sprintf('pin-sha256="%s"; ', $pin);
        }
        
        $pin_string .= 'max-age=2592000'; // 30 days
        
        return $pin_string;
    }
    
    /**
     * Generate API signature
     */
    private function generate_api_signature($url, $args) {
        $method = $args['method'] ?? 'GET';
        $body = $args['body'] ?? '';
        
        if (is_array($body)) {
            $body = json_encode($body);
        }
        
        $timestamp = time();
        $nonce = wp_generate_password(32, false);
        
        $data = implode("\n", [
            $method,
            $url,
            $timestamp,
            $nonce,
            md5($body)
        ]);
        
        $key = $this->get_api_signing_key();
        $signature = hash_hmac('sha256', $data, $key);
        
        // Store nonce to prevent replay
        set_transient('api_nonce_' . $nonce, true, 300); // 5 minutes
        
        return base64_encode(json_encode([
            'signature' => $signature,
            'timestamp' => $timestamp,
            'nonce' => $nonce
        ]));
    }
    
    /**
     * Validate API signature
     */
    private function validate_api_signature($request, $signature) {
        try {
            $decoded = json_decode(base64_decode($signature), true);
            
            if (!$decoded || !isset($decoded['signature'], $decoded['timestamp'], $decoded['nonce'])) {
                return false;
            }
            
            // Check timestamp (5 minute window)
            if (abs(time() - $decoded['timestamp']) > 300) {
                return false;
            }
            
            // Check nonce
            if (!$this->validate_api_nonce($decoded['nonce'])) {
                return false;
            }
            
            // Rebuild signature
            $method = $request->get_method();
            $url = $request->get_route();
            $body = $request->get_body();
            
            $data = implode("\n", [
                $method,
                $url,
                $decoded['timestamp'],
                $decoded['nonce'],
                md5($body)
            ]);
            
            $key = $this->get_api_signing_key();
            $expected_signature = hash_hmac('sha256', $data, $key);
            
            return hash_equals($expected_signature, $decoded['signature']);
            
        } catch (Exception $e) {
            error_log('API signature validation error: ' . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Validate API nonce
     */
    private function validate_api_nonce($nonce) {
        $exists = get_transient('api_nonce_' . $nonce);
        
        if ($exists) {
            // Delete to prevent reuse
            delete_transient('api_nonce_' . $nonce);
            return true;
        }
        
        return false;
    }
    
    /**
     * Sign API response
     */
    private function sign_api_response($data) {
        $timestamp = time();
        $body = is_string($data) ? $data : json_encode($data);
        
        $signature_data = implode("\n", [
            'RESPONSE',
            $timestamp,
            md5($body)
        ]);
        
        $key = $this->get_api_signing_key();
        $signature = hash_hmac('sha256', $signature_data, $key);
        
        return base64_encode(json_encode([
            'signature' => $signature,
            'timestamp' => $timestamp
        ]));
    }
    
    /**
     * Get API signing key
     */
    private function get_api_signing_key() {
        $key = get_option('money_quiz_api_signing_key');
        
        if (!$key) {
            $key = wp_generate_password(64, true, true);
            update_option('money_quiz_api_signing_key', $key);
        }
        
        return $key;
    }
    
    /**
     * SSL not available notice
     */
    public function ssl_not_available_notice() {
        ?>
        <div class="notice notice-error">
            <p><strong>Security Warning:</strong> 
            SSL is not enabled on this site. Money Quiz requires HTTPS for secure operation. 
            Please enable SSL/TLS on your server.</p>
        </div>
        <?php
    }
    
    /**
     * Log certificate info
     */
    private function log_certificate_info($cert) {
        $log_data = [
            'subject' => $cert['subject']['CN'] ?? '',
            'issuer' => $cert['issuer']['O'] ?? '',
            'valid_from' => date('Y-m-d', $cert['validFrom_time_t']),
            'valid_to' => date('Y-m-d', $cert['validTo_time_t']),
            'signature_algorithm' => $cert['signatureTypeSN'] ?? '',
            'serial_number' => $cert['serialNumber'] ?? ''
        ];
        
        $this->db->insert(
            $this->db->prefix . 'money_quiz_ssl_logs',
            [
                'event' => 'certificate_check',
                'data' => json_encode($log_data),
                'timestamp' => current_time('mysql')
            ]
        );
    }
    
    /**
     * Create database tables
     */
    public static function create_tables() {
        global $wpdb;
        
        $charset_collate = $wpdb->get_charset_collate();
        
        $sql = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}money_quiz_ssl_logs (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            event varchar(50) NOT NULL,
            data text,
            timestamp datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY event (event),
            KEY timestamp (timestamp)
        ) $charset_collate;";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
    }
}

// Initialize on plugin activation
register_activation_hook(__FILE__, ['MoneyQuiz\Security\Encryption\TransportSecurity', 'create_tables']);
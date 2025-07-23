<?php
/**
 * CORS Manager
 *
 * Manages Cross-Origin Resource Sharing with strict validation.
 *
 * @package MoneyQuiz\Security
 * @since   7.0.0
 */

namespace MoneyQuiz\Security;

use MoneyQuiz\Core\Logging\Logger;
use MoneyQuiz\Core\Config\ConfigManager;

// Security: Prevent direct access.
defined('ABSPATH') || exit;

/**
 * CORS manager with strict origin validation.
 *
 * @since 7.0.0
 */
class CORSManager {
    
    /**
     * Logger instance.
     *
     * @var Logger
     */
    private Logger $logger;
    
    /**
     * Config manager.
     *
     * @var ConfigManager
     */
    private ConfigManager $config;
    
    /**
     * Allowed origins cache.
     *
     * @var array|null
     */
    private ?array $allowed_origins = null;
    
    /**
     * Constructor.
     *
     * @param Logger        $logger Logger instance.
     * @param ConfigManager $config Config manager.
     */
    public function __construct(Logger $logger, ConfigManager $config) {
        $this->logger = $logger;
        $this->config = $config;
    }
    
    /**
     * Initialize CORS handling.
     */
    public function init(): void {
        // Only handle CORS for API endpoints
        add_action('rest_api_init', [$this, 'handle_preflight']);
        add_filter('rest_pre_serve_request', [$this, 'add_cors_headers'], 10, 3);
    }
    
    /**
     * Handle preflight requests.
     */
    public function handle_preflight(): void {
        if ($_SERVER['REQUEST_METHOD'] !== 'OPTIONS') {
            return;
        }
        
        $origin = $_SERVER['HTTP_ORIGIN'] ?? '';
        
        if ($this->is_origin_allowed($origin)) {
            $this->send_cors_headers($origin);
            exit;
        }
        
        // Log rejected preflight
        $this->logger->warning('CORS preflight rejected', [
            'origin' => $origin,
            'path' => $_SERVER['REQUEST_URI'] ?? ''
        ]);
    }
    
    /**
     * Add CORS headers to REST responses.
     *
     * @param bool             $served  Whether the request has been served.
     * @param \WP_HTTP_Response $result  Result to send.
     * @param \WP_REST_Request $request Request object.
     * @return bool Whether the request has been served.
     */
    public function add_cors_headers(bool $served, \WP_HTTP_Response $result, \WP_REST_Request $request): bool {
        $origin = $_SERVER['HTTP_ORIGIN'] ?? '';
        
        if ($this->is_origin_allowed($origin)) {
            $this->send_cors_headers($origin);
        }
        
        return $served;
    }
    
    /**
     * Check if origin is allowed.
     *
     * @param string $origin Origin to check.
     * @return bool True if allowed.
     */
    private function is_origin_allowed(string $origin): bool {
        if (empty($origin)) {
            return false;
        }
        
        // Load allowed origins
        if ($this->allowed_origins === null) {
            $this->load_allowed_origins();
        }
        
        // Check exact match
        if (in_array($origin, $this->allowed_origins, true)) {
            return true;
        }
        
        // Check wildcard patterns
        foreach ($this->allowed_origins as $allowed) {
            if ($this->match_origin_pattern($origin, $allowed)) {
                return true;
            }
        }
        
        return false;
    }
    
    /**
     * Load allowed origins from configuration.
     */
    private function load_allowed_origins(): void {
        // Default: only same origin
        $defaults = [
            site_url()
        ];
        
        // Get configured origins
        $configured = $this->config->get('cors_origins', []);
        
        // Validate each origin
        $this->allowed_origins = [];
        foreach (array_merge($defaults, $configured) as $origin) {
            if ($this->validate_origin($origin)) {
                $this->allowed_origins[] = $origin;
            }
        }
    }
    
    /**
     * Validate origin format.
     *
     * @param string $origin Origin to validate.
     * @return bool True if valid.
     */
    private function validate_origin(string $origin): bool {
        // Allow wildcard subdomain
        if (strpos($origin, '*.') === 0) {
            $domain = substr($origin, 2);
            return filter_var('http://' . $domain, FILTER_VALIDATE_URL) !== false;
        }
        
        // Must be valid URL
        return filter_var($origin, FILTER_VALIDATE_URL) !== false;
    }
    
    /**
     * Match origin against pattern.
     *
     * @param string $origin  Origin to check.
     * @param string $pattern Pattern to match.
     * @return bool True if matches.
     */
    private function match_origin_pattern(string $origin, string $pattern): bool {
        // Handle wildcard subdomain
        if (strpos($pattern, '*.') === 0) {
            $domain = substr($pattern, 2);
            return preg_match('/^https?:\/\/[^\/]+\.' . preg_quote($domain, '/') . '$/', $origin) === 1;
        }
        
        return $origin === $pattern;
    }
    
    /**
     * Send CORS headers.
     *
     * @param string $origin Allowed origin.
     */
    private function send_cors_headers(string $origin): void {
        header('Access-Control-Allow-Origin: ' . $origin);
        header('Access-Control-Allow-Credentials: true');
        header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
        header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With, X-WP-Nonce');
        header('Access-Control-Max-Age: 3600');
        
        $this->logger->debug('CORS headers sent', [
            'origin' => $origin
        ]);
    }
    
    /**
     * Add allowed origin.
     *
     * @param string $origin Origin to add.
     * @return bool Success status.
     */
    public function add_allowed_origin(string $origin): bool {
        if (!$this->validate_origin($origin)) {
            $this->logger->error('Invalid origin format', ['origin' => $origin]);
            return false;
        }
        
        $origins = $this->config->get('cors_origins', []);
        if (!in_array($origin, $origins, true)) {
            $origins[] = $origin;
            $this->config->set('cors_origins', $origins);
            $this->allowed_origins = null; // Clear cache
        }
        
        return true;
    }
    
    /**
     * Remove allowed origin.
     *
     * @param string $origin Origin to remove.
     * @return bool Success status.
     */
    public function remove_allowed_origin(string $origin): bool {
        $origins = $this->config->get('cors_origins', []);
        $origins = array_diff($origins, [$origin]);
        $this->config->set('cors_origins', array_values($origins));
        $this->allowed_origins = null; // Clear cache
        
        return true;
    }
}
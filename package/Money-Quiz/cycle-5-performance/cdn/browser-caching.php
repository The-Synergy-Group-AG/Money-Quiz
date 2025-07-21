<?php
/**
 * Money Quiz Plugin - Browser Caching
 * Worker 4: Cache Headers Optimization
 * 
 * Implements advanced browser caching strategies with proper cache headers,
 * ETags, conditional requests, and cache busting.
 * 
 * @package MoneyQuiz
 * @subpackage Performance\CDN
 * @since 5.0.0
 */

namespace MoneyQuiz\Performance\CDN;

/**
 * Browser Caching Class
 * 
 * Manages browser cache headers and client-side caching strategies
 */
class BrowserCaching {
    
    /**
     * Browser caching configuration
     * 
     * @var array
     */
    protected $config = array(
        'enabled' => true,
        'cache_control' => array(
            'html' => 'public, max-age=3600, must-revalidate',
            'css' => 'public, max-age=31536000, immutable',
            'js' => 'public, max-age=31536000, immutable',
            'images' => 'public, max-age=31536000, immutable',
            'fonts' => 'public, max-age=31536000, immutable',
            'json' => 'public, max-age=3600, must-revalidate',
            'xml' => 'public, max-age=3600, must-revalidate'
        ),
        'enable_etags' => true,
        'enable_last_modified' => true,
        'enable_vary_headers' => true,
        'enable_conditional_requests' => true,
        'cache_busting' => true,
        'security_headers' => true
    );
    
    /**
     * File type mappings
     * 
     * @var array
     */
    protected $file_types = array(
        'html' => array( 'html', 'htm' ),
        'css' => array( 'css' ),
        'js' => array( 'js' ),
        'images' => array( 'jpg', 'jpeg', 'png', 'gif', 'webp', 'svg', 'ico' ),
        'fonts' => array( 'woff', 'woff2', 'ttf', 'eot', 'otf' ),
        'json' => array( 'json' ),
        'xml' => array( 'xml', 'rss', 'atom' )
    );
    
    /**
     * Security headers
     * 
     * @var array
     */
    protected $security_headers = array(
        'X-Content-Type-Options' => 'nosniff',
        'X-Frame-Options' => 'SAMEORIGIN',
        'X-XSS-Protection' => '1; mode=block',
        'Referrer-Policy' => 'strict-origin-when-cross-origin',
        'Permissions-Policy' => 'geolocation=(), microphone=(), camera=()'
    );
    
    /**
     * Performance metrics
     * 
     * @var array
     */
    protected $metrics = array(
        'headers_sent' => 0,
        'conditional_requests' => 0,
        'cache_hits' => 0,
        'etags_generated' => 0
    );
    
    /**
     * Constructor
     */
    public function __construct() {
        $this->load_config();
        $this->init();
    }
    
    /**
     * Initialize browser caching
     */
    protected function init() {
        if ( ! $this->config['enabled'] ) {
            return;
        }
        
        // Register hooks
        $this->register_hooks();
        
        // Set up .htaccess rules
        $this->setup_htaccess_rules();
    }
    
    /**
     * Load configuration
     */
    protected function load_config() {
        $saved_config = get_option( 'money_quiz_browser_caching_config', array() );
        $this->config = wp_parse_args( $saved_config, $this->config );
    }
    
    /**
     * Register hooks
     */
    protected function register_hooks() {
        // Send headers for WordPress pages
        add_action( 'send_headers', array( $this, 'send_page_headers' ), 1 );
        
        // Handle conditional requests
        if ( $this->config['enable_conditional_requests'] ) {
            add_action( 'template_redirect', array( $this, 'handle_conditional_requests' ), 1 );
        }
        
        // Asset versioning
        if ( $this->config['cache_busting'] ) {
            add_filter( 'script_loader_src', array( $this, 'add_cache_busting' ), 10, 2 );
            add_filter( 'style_loader_src', array( $this, 'add_cache_busting' ), 10, 2 );
        }
        
        // AJAX endpoints
        add_action( 'wp_ajax_money_quiz_get_result', array( $this, 'send_ajax_headers' ), 1 );
        add_action( 'wp_ajax_nopriv_money_quiz_get_result', array( $this, 'send_ajax_headers' ), 1 );
        
        // REST API
        add_filter( 'rest_pre_serve_request', array( $this, 'send_rest_headers' ), 10, 3 );
        
        // Admin bar info
        if ( is_admin() ) {
            add_action( 'admin_bar_menu', array( $this, 'add_admin_bar_info' ), 100 );
        }
    }
    
    /**
     * Send page headers
     */
    public function send_page_headers() {
        // Skip admin pages
        if ( is_admin() ) {
            return;
        }
        
        // Determine content type
        $content_type = $this->get_content_type();
        
        // Send cache headers
        $this->send_cache_headers( $content_type );
        
        // Send security headers
        if ( $this->config['security_headers'] ) {
            $this->send_security_headers();
        }
        
        // Send vary headers
        if ( $this->config['enable_vary_headers'] ) {
            $this->send_vary_headers();
        }
        
        $this->metrics['headers_sent']++;
    }
    
    /**
     * Send cache headers
     * 
     * @param string $content_type Content type
     */
    protected function send_cache_headers( $content_type ) {
        // Get cache control for content type
        $cache_control = $this->config['cache_control'][ $content_type ] ?? 'public, max-age=3600';
        
        // Adjust for logged-in users
        if ( is_user_logged_in() ) {
            $cache_control = 'private, no-cache, must-revalidate';
        }
        
        // Send Cache-Control header
        header( 'Cache-Control: ' . $cache_control );
        
        // Parse max-age from cache control
        preg_match( '/max-age=(\d+)/', $cache_control, $matches );
        $max_age = isset( $matches[1] ) ? intval( $matches[1] ) : 3600;
        
        // Send Expires header
        header( 'Expires: ' . gmdate( 'D, d M Y H:i:s', time() + $max_age ) . ' GMT' );
        
        // Send ETag if enabled
        if ( $this->config['enable_etags'] ) {
            $etag = $this->generate_etag();
            if ( $etag ) {
                header( 'ETag: "' . $etag . '"' );
                $this->metrics['etags_generated']++;
            }
        }
        
        // Send Last-Modified if enabled
        if ( $this->config['enable_last_modified'] ) {
            $last_modified = $this->get_last_modified();
            if ( $last_modified ) {
                header( 'Last-Modified: ' . gmdate( 'D, d M Y H:i:s', $last_modified ) . ' GMT' );
            }
        }
    }
    
    /**
     * Send security headers
     */
    protected function send_security_headers() {
        foreach ( $this->security_headers as $header => $value ) {
            header( $header . ': ' . $value );
        }
        
        // Content Security Policy
        $csp = $this->generate_csp();
        if ( $csp ) {
            header( 'Content-Security-Policy: ' . $csp );
        }
    }
    
    /**
     * Send vary headers
     */
    protected function send_vary_headers() {
        $vary_headers = array( 'Accept-Encoding' );
        
        // Add WebP vary for images
        if ( $this->is_image_request() ) {
            $vary_headers[] = 'Accept';
        }
        
        // Add cookie vary for dynamic content
        if ( ! $this->is_static_resource() ) {
            $vary_headers[] = 'Cookie';
        }
        
        header( 'Vary: ' . implode( ', ', $vary_headers ) );
    }
    
    /**
     * Handle conditional requests
     */
    public function handle_conditional_requests() {
        // Check If-None-Match (ETag)
        if ( isset( $_SERVER['HTTP_IF_NONE_MATCH'] ) && $this->config['enable_etags'] ) {
            $client_etag = trim( $_SERVER['HTTP_IF_NONE_MATCH'], '"' );
            $server_etag = $this->generate_etag();
            
            if ( $client_etag === $server_etag ) {
                $this->send_not_modified();
                exit;
            }
        }
        
        // Check If-Modified-Since
        if ( isset( $_SERVER['HTTP_IF_MODIFIED_SINCE'] ) && $this->config['enable_last_modified'] ) {
            $client_modified = strtotime( $_SERVER['HTTP_IF_MODIFIED_SINCE'] );
            $server_modified = $this->get_last_modified();
            
            if ( $server_modified && $client_modified >= $server_modified ) {
                $this->send_not_modified();
                exit;
            }
        }
        
        $this->metrics['conditional_requests']++;
    }
    
    /**
     * Send 304 Not Modified response
     */
    protected function send_not_modified() {
        header( 'HTTP/1.1 304 Not Modified' );
        
        // Send minimal headers
        header( 'Cache-Control: ' . $this->get_cache_control() );
        
        if ( $this->config['enable_etags'] ) {
            $etag = $this->generate_etag();
            if ( $etag ) {
                header( 'ETag: "' . $etag . '"' );
            }
        }
        
        $this->metrics['cache_hits']++;
        
        // Remove content headers
        header_remove( 'Content-Type' );
        header_remove( 'Content-Length' );
    }
    
    /**
     * Send AJAX headers
     */
    public function send_ajax_headers() {
        // AJAX responses should have shorter cache
        header( 'Cache-Control: public, max-age=300, must-revalidate' );
        header( 'X-Content-Type-Options: nosniff' );
        
        // Add CORS headers if needed
        $this->send_cors_headers();
    }
    
    /**
     * Send REST API headers
     * 
     * @param bool             $served  Whether the request has been served
     * @param WP_HTTP_Response $result  Result to send
     * @param WP_REST_Request  $request Request object
     * @return bool
     */
    public function send_rest_headers( $served, $result, $request ) {
        // REST API cache headers
        header( 'Cache-Control: public, max-age=300, must-revalidate' );
        
        // Add ETag for GET requests
        if ( $request->get_method() === 'GET' && $this->config['enable_etags'] ) {
            $data = $result->get_data();
            $etag = md5( serialize( $data ) );
            header( 'ETag: "' . $etag . '"' );
        }
        
        return $served;
    }
    
    /**
     * Send CORS headers
     */
    protected function send_cors_headers() {
        $allowed_origins = get_option( 'money_quiz_cors_origins', array() );
        
        if ( empty( $allowed_origins ) ) {
            return;
        }
        
        $origin = $_SERVER['HTTP_ORIGIN'] ?? '';
        
        if ( in_array( $origin, $allowed_origins ) || in_array( '*', $allowed_origins ) ) {
            header( 'Access-Control-Allow-Origin: ' . $origin );
            header( 'Access-Control-Allow-Methods: GET, POST, OPTIONS' );
            header( 'Access-Control-Allow-Headers: Content-Type, Authorization' );
            header( 'Access-Control-Allow-Credentials: true' );
            header( 'Access-Control-Max-Age: 86400' );
        }
    }
    
    /**
     * Add cache busting to asset URLs
     * 
     * @param string $src    Asset URL
     * @param string $handle Asset handle
     * @return string Modified URL
     */
    public function add_cache_busting( $src, $handle ) {
        if ( strpos( $src, 'ver=' ) !== false ) {
            return $src;
        }
        
        // Get file path
        $file_path = $this->url_to_path( $src );
        
        if ( file_exists( $file_path ) ) {
            // Use file modification time for versioning
            $version = filemtime( $file_path );
            $src = add_query_arg( 'ver', $version, $src );
        }
        
        return $src;
    }
    
    /**
     * Get content type
     * 
     * @return string Content type
     */
    protected function get_content_type() {
        global $wp_query;
        
        // Check if it's an attachment
        if ( is_attachment() ) {
            $mime_type = get_post_mime_type();
            
            foreach ( $this->file_types as $type => $extensions ) {
                if ( strpos( $mime_type, $type ) !== false ) {
                    return $type;
                }
            }
        }
        
        // Check request URI
        $request_uri = $_SERVER['REQUEST_URI'] ?? '';
        $extension = pathinfo( parse_url( $request_uri, PHP_URL_PATH ), PATHINFO_EXTENSION );
        
        foreach ( $this->file_types as $type => $extensions ) {
            if ( in_array( $extension, $extensions ) ) {
                return $type;
            }
        }
        
        // Default to HTML for pages
        return 'html';
    }
    
    /**
     * Generate ETag
     * 
     * @return string|false ETag or false
     */
    protected function generate_etag() {
        global $wp_query, $post;
        
        $etag_parts = array();
        
        // Add post data if available
        if ( $post ) {
            $etag_parts[] = $post->ID;
            $etag_parts[] = $post->post_modified;
            $etag_parts[] = $post->comment_count;
        }
        
        // Add query vars
        if ( $wp_query ) {
            $etag_parts[] = serialize( $wp_query->query_vars );
        }
        
        // Add user state
        $etag_parts[] = is_user_logged_in() ? get_current_user_id() : 'guest';
        
        // Add Money Quiz specific data
        if ( $this->is_money_quiz_page() ) {
            $etag_parts[] = $this->get_money_quiz_version();
        }
        
        if ( empty( $etag_parts ) ) {
            return false;
        }
        
        return md5( implode( '-', $etag_parts ) );
    }
    
    /**
     * Get last modified time
     * 
     * @return int|false Timestamp or false
     */
    protected function get_last_modified() {
        global $post;
        
        if ( $post ) {
            return strtotime( $post->post_modified );
        }
        
        // For archives, get most recent post
        if ( is_archive() || is_home() ) {
            $recent_post = get_posts( array(
                'numberposts' => 1,
                'orderby' => 'modified',
                'order' => 'DESC'
            ) );
            
            if ( $recent_post ) {
                return strtotime( $recent_post[0]->post_modified );
            }
        }
        
        return false;
    }
    
    /**
     * Get cache control string
     * 
     * @return string Cache control
     */
    protected function get_cache_control() {
        $content_type = $this->get_content_type();
        return $this->config['cache_control'][ $content_type ] ?? 'public, max-age=3600';
    }
    
    /**
     * Generate Content Security Policy
     * 
     * @return string CSP header value
     */
    protected function generate_csp() {
        $directives = array(
            "default-src 'self'",
            "script-src 'self' 'unsafe-inline' 'unsafe-eval' https://www.google-analytics.com https://www.googletagmanager.com",
            "style-src 'self' 'unsafe-inline' https://fonts.googleapis.com",
            "img-src 'self' data: https: http:",
            "font-src 'self' https://fonts.gstatic.com",
            "connect-src 'self' https://www.google-analytics.com",
            "frame-ancestors 'none'",
            "base-uri 'self'",
            "form-action 'self'"
        );
        
        // Add CDN domains
        if ( defined( 'MONEY_QUIZ_CDN_URL' ) ) {
            $cdn_host = parse_url( MONEY_QUIZ_CDN_URL, PHP_URL_HOST );
            $directives[] = "script-src https://{$cdn_host}";
            $directives[] = "style-src https://{$cdn_host}";
            $directives[] = "img-src https://{$cdn_host}";
            $directives[] = "font-src https://{$cdn_host}";
        }
        
        return implode( '; ', $directives );
    }
    
    /**
     * Check if current request is for an image
     * 
     * @return bool
     */
    protected function is_image_request() {
        $request_uri = $_SERVER['REQUEST_URI'] ?? '';
        $extension = pathinfo( parse_url( $request_uri, PHP_URL_PATH ), PATHINFO_EXTENSION );
        
        return in_array( $extension, $this->file_types['images'] );
    }
    
    /**
     * Check if current request is for static resource
     * 
     * @return bool
     */
    protected function is_static_resource() {
        $request_uri = $_SERVER['REQUEST_URI'] ?? '';
        $extension = pathinfo( parse_url( $request_uri, PHP_URL_PATH ), PATHINFO_EXTENSION );
        
        $static_types = array_merge(
            $this->file_types['css'],
            $this->file_types['js'],
            $this->file_types['images'],
            $this->file_types['fonts']
        );
        
        return in_array( $extension, $static_types );
    }
    
    /**
     * Check if current page is Money Quiz page
     * 
     * @return bool
     */
    protected function is_money_quiz_page() {
        global $post;
        
        if ( ! $post ) {
            return false;
        }
        
        return has_shortcode( $post->post_content, 'money_quiz' ) ||
               has_shortcode( $post->post_content, 'money_quiz_results' );
    }
    
    /**
     * Get Money Quiz version for ETags
     * 
     * @return string Version
     */
    protected function get_money_quiz_version() {
        return defined( 'MONEY_QUIZ_VERSION' ) ? MONEY_QUIZ_VERSION : '1.0.0';
    }
    
    /**
     * Convert URL to file path
     * 
     * @param string $url URL
     * @return string File path
     */
    protected function url_to_path( $url ) {
        $site_url = site_url();
        $file_path = str_replace( $site_url, ABSPATH, $url );
        
        return parse_url( $file_path, PHP_URL_PATH );
    }
    
    /**
     * Setup .htaccess rules
     */
    protected function setup_htaccess_rules() {
        if ( ! $this->can_write_htaccess() ) {
            return;
        }
        
        $htaccess_file = ABSPATH . '.htaccess';
        $htaccess_content = file_get_contents( $htaccess_file );
        
        // Check if our rules already exist
        if ( strpos( $htaccess_content, '# BEGIN Money Quiz Browser Caching' ) !== false ) {
            return;
        }
        
        $cache_rules = $this->generate_htaccess_rules();
        
        // Add rules before WordPress rules
        $htaccess_content = $cache_rules . "\n" . $htaccess_content;
        
        file_put_contents( $htaccess_file, $htaccess_content );
    }
    
    /**
     * Generate .htaccess rules
     * 
     * @return string Htaccess rules
     */
    protected function generate_htaccess_rules() {
        $rules = "# BEGIN Money Quiz Browser Caching\n";
        $rules .= "<IfModule mod_expires.c>\n";
        $rules .= "ExpiresActive On\n";
        
        // HTML
        $rules .= "ExpiresByType text/html \"access plus 1 hour\"\n";
        
        // CSS and JavaScript
        $rules .= "ExpiresByType text/css \"access plus 1 year\"\n";
        $rules .= "ExpiresByType application/javascript \"access plus 1 year\"\n";
        $rules .= "ExpiresByType text/javascript \"access plus 1 year\"\n";
        
        // Images
        $rules .= "ExpiresByType image/jpeg \"access plus 1 year\"\n";
        $rules .= "ExpiresByType image/png \"access plus 1 year\"\n";
        $rules .= "ExpiresByType image/gif \"access plus 1 year\"\n";
        $rules .= "ExpiresByType image/webp \"access plus 1 year\"\n";
        $rules .= "ExpiresByType image/svg+xml \"access plus 1 year\"\n";
        $rules .= "ExpiresByType image/x-icon \"access plus 1 year\"\n";
        
        // Fonts
        $rules .= "ExpiresByType font/woff \"access plus 1 year\"\n";
        $rules .= "ExpiresByType font/woff2 \"access plus 1 year\"\n";
        $rules .= "ExpiresByType application/font-woff \"access plus 1 year\"\n";
        $rules .= "ExpiresByType application/font-woff2 \"access plus 1 year\"\n";
        
        $rules .= "</IfModule>\n";
        
        // Add deflate compression
        $rules .= "\n<IfModule mod_deflate.c>\n";
        $rules .= "AddOutputFilterByType DEFLATE text/html text/plain text/xml text/css\n";
        $rules .= "AddOutputFilterByType DEFLATE application/javascript text/javascript\n";
        $rules .= "AddOutputFilterByType DEFLATE application/json application/xml\n";
        $rules .= "AddOutputFilterByType DEFLATE image/svg+xml\n";
        $rules .= "</IfModule>\n";
        
        // Add cache headers
        $rules .= "\n<IfModule mod_headers.c>\n";
        
        // Remove ETags
        $rules .= "Header unset ETag\n";
        $rules .= "FileETag None\n";
        
        // Cache control for different file types
        $rules .= "<FilesMatch \"\.(css|js)$\">\n";
        $rules .= "Header set Cache-Control \"public, max-age=31536000, immutable\"\n";
        $rules .= "</FilesMatch>\n";
        
        $rules .= "<FilesMatch \"\.(jpg|jpeg|png|gif|webp|ico|svg)$\">\n";
        $rules .= "Header set Cache-Control \"public, max-age=31536000, immutable\"\n";
        $rules .= "</FilesMatch>\n";
        
        $rules .= "<FilesMatch \"\.(woff|woff2|ttf|eot|otf)$\">\n";
        $rules .= "Header set Cache-Control \"public, max-age=31536000, immutable\"\n";
        $rules .= "</FilesMatch>\n";
        
        // Security headers
        $rules .= "Header set X-Content-Type-Options \"nosniff\"\n";
        $rules .= "Header set X-Frame-Options \"SAMEORIGIN\"\n";
        $rules .= "Header set X-XSS-Protection \"1; mode=block\"\n";
        $rules .= "Header set Referrer-Policy \"strict-origin-when-cross-origin\"\n";
        
        $rules .= "</IfModule>\n";
        
        $rules .= "# END Money Quiz Browser Caching\n";
        
        return $rules;
    }
    
    /**
     * Check if we can write to .htaccess
     * 
     * @return bool
     */
    protected function can_write_htaccess() {
        if ( ! file_exists( ABSPATH . '.htaccess' ) ) {
            return is_writable( ABSPATH );
        }
        
        return is_writable( ABSPATH . '.htaccess' );
    }
    
    /**
     * Add admin bar info
     * 
     * @param WP_Admin_Bar $wp_admin_bar Admin bar object
     */
    public function add_admin_bar_info( $wp_admin_bar ) {
        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }
        
        $wp_admin_bar->add_node( array(
            'id' => 'money-quiz-caching',
            'title' => 'Browser Caching',
            'parent' => 'top-secondary'
        ) );
        
        $stats = $this->get_stats();
        
        $wp_admin_bar->add_node( array(
            'id' => 'money-quiz-caching-stats',
            'title' => sprintf( 
                'Headers: %d | Conditional: %d | Hits: %d',
                $stats['headers_sent'],
                $stats['conditional_requests'],
                $stats['cache_hits']
            ),
            'parent' => 'money-quiz-caching'
        ) );
    }
    
    /**
     * Get browser caching statistics
     * 
     * @return array Statistics
     */
    public function get_stats() {
        return array(
            'enabled' => $this->config['enabled'],
            'headers_sent' => $this->metrics['headers_sent'],
            'conditional_requests' => $this->metrics['conditional_requests'],
            'cache_hits' => $this->metrics['cache_hits'],
            'etags_generated' => $this->metrics['etags_generated'],
            'hit_rate' => $this->metrics['conditional_requests'] > 0 
                ? round( ( $this->metrics['cache_hits'] / $this->metrics['conditional_requests'] ) * 100, 2 )
                : 0
        );
    }
}

// Initialize browser caching
global $money_quiz_browser_caching;
$money_quiz_browser_caching = new BrowserCaching();
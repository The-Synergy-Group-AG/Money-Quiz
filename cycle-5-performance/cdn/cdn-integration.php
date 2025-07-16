<?php
/**
 * Money Quiz Plugin - CDN Integration
 * Worker 4: CloudFlare/CloudFront Integration
 * 
 * Implements CDN integration with support for multiple providers,
 * automatic URL rewriting, and cache purging capabilities.
 * 
 * @package MoneyQuiz
 * @subpackage Performance\CDN
 * @since 5.0.0
 */

namespace MoneyQuiz\Performance\CDN;

/**
 * CDN Integration Class
 * 
 * Manages CDN integration for optimal content delivery
 */
class CDNIntegration {
    
    /**
     * CDN configuration
     * 
     * @var array
     */
    protected $config = array(
        'enabled' => true,
        'provider' => 'cloudflare', // cloudflare, cloudfront, custom
        'cdn_url' => '',
        'include_dirs' => array( 'wp-content', 'wp-includes' ),
        'exclude_dirs' => array( 'wp-content/uploads/private' ),
        'extensions' => array( 'js', 'css', 'jpg', 'jpeg', 'png', 'gif', 'webp', 'svg', 'woff', 'woff2', 'ttf', 'eot' ),
        'enable_versioning' => true,
        'enable_preconnect' => true,
        'enable_push' => true,
        'purge_on_update' => true
    );
    
    /**
     * CDN providers
     * 
     * @var array
     */
    protected $providers = array(
        'cloudflare' => array(
            'name' => 'CloudFlare',
            'api_endpoint' => 'https://api.cloudflare.com/client/v4/',
            'purge_method' => 'api',
            'features' => array( 'purge', 'preload', 'analytics', 'polish', 'mirage' )
        ),
        'cloudfront' => array(
            'name' => 'Amazon CloudFront',
            'api_endpoint' => 'https://cloudfront.amazonaws.com/',
            'purge_method' => 'invalidation',
            'features' => array( 'purge', 'signed_urls', 'geo_restriction' )
        ),
        'maxcdn' => array(
            'name' => 'MaxCDN/StackPath',
            'api_endpoint' => 'https://api.stackpath.com/',
            'purge_method' => 'api',
            'features' => array( 'purge', 'prefetch', 'instant_purge' )
        ),
        'custom' => array(
            'name' => 'Custom CDN',
            'api_endpoint' => '',
            'purge_method' => 'none',
            'features' => array()
        )
    );
    
    /**
     * CDN API credentials
     * 
     * @var array
     */
    protected $credentials = array();
    
    /**
     * URL rewrite rules
     * 
     * @var array
     */
    protected $rewrite_rules = array();
    
    /**
     * Purge queue
     * 
     * @var array
     */
    protected $purge_queue = array();
    
    /**
     * Performance metrics
     * 
     * @var array
     */
    protected $metrics = array(
        'urls_rewritten' => 0,
        'bytes_offloaded' => 0,
        'purges_triggered' => 0,
        'api_calls' => 0
    );
    
    /**
     * Constructor
     */
    public function __construct() {
        $this->load_config();
        $this->init();
    }
    
    /**
     * Initialize CDN integration
     */
    protected function init() {
        if ( ! $this->config['enabled'] ) {
            return;
        }
        
        // Load provider-specific settings
        $this->load_provider_settings();
        
        // Set up URL rewriting
        $this->setup_url_rewriting();
        
        // Register hooks
        $this->register_hooks();
        
        // Set up API client
        $this->setup_api_client();
    }
    
    /**
     * Load configuration
     */
    protected function load_config() {
        $saved_config = get_option( 'money_quiz_cdn_config', array() );
        $this->config = wp_parse_args( $saved_config, $this->config );
        
        // Load credentials securely
        $this->load_credentials();
    }
    
    /**
     * Load credentials
     */
    protected function load_credentials() {
        // Try constants first
        if ( defined( 'MONEY_QUIZ_CDN_API_KEY' ) ) {
            $this->credentials['api_key'] = MONEY_QUIZ_CDN_API_KEY;
        }
        
        if ( defined( 'MONEY_QUIZ_CDN_API_SECRET' ) ) {
            $this->credentials['api_secret'] = MONEY_QUIZ_CDN_API_SECRET;
        }
        
        if ( defined( 'MONEY_QUIZ_CDN_ZONE_ID' ) ) {
            $this->credentials['zone_id'] = MONEY_QUIZ_CDN_ZONE_ID;
        }
        
        // Fall back to database (encrypted)
        if ( empty( $this->credentials ) ) {
            $encrypted = get_option( 'money_quiz_cdn_credentials', '' );
            if ( $encrypted ) {
                $this->credentials = $this->decrypt_credentials( $encrypted );
            }
        }
    }
    
    /**
     * Load provider-specific settings
     */
    protected function load_provider_settings() {
        $provider = $this->config['provider'];
        
        switch ( $provider ) {
            case 'cloudflare':
                $this->load_cloudflare_settings();
                break;
                
            case 'cloudfront':
                $this->load_cloudfront_settings();
                break;
                
            case 'maxcdn':
                $this->load_maxcdn_settings();
                break;
        }
    }
    
    /**
     * Load CloudFlare settings
     */
    protected function load_cloudflare_settings() {
        // Auto-detect CloudFlare
        if ( isset( $_SERVER['HTTP_CF_RAY'] ) ) {
            $this->config['cloudflare_active'] = true;
        }
        
        // Set CloudFlare-specific options
        if ( ! empty( $this->credentials['zone_id'] ) ) {
            $this->config['cdn_url'] = 'https://' . parse_url( home_url(), PHP_URL_HOST );
        }
    }
    
    /**
     * Load CloudFront settings
     */
    protected function load_cloudfront_settings() {
        // CloudFront distribution domain
        if ( defined( 'MONEY_QUIZ_CLOUDFRONT_DOMAIN' ) ) {
            $this->config['cdn_url'] = 'https://' . MONEY_QUIZ_CLOUDFRONT_DOMAIN;
        }
        
        // Distribution ID for invalidations
        if ( defined( 'MONEY_QUIZ_CLOUDFRONT_DIST_ID' ) ) {
            $this->credentials['distribution_id'] = MONEY_QUIZ_CLOUDFRONT_DIST_ID;
        }
    }
    
    /**
     * Load MaxCDN settings
     */
    protected function load_maxcdn_settings() {
        // MaxCDN/StackPath pull zone
        if ( defined( 'MONEY_QUIZ_MAXCDN_ZONE' ) ) {
            $this->config['cdn_url'] = MONEY_QUIZ_MAXCDN_ZONE;
        }
    }
    
    /**
     * Setup URL rewriting
     */
    protected function setup_url_rewriting() {
        if ( empty( $this->config['cdn_url'] ) ) {
            return;
        }
        
        // Build rewrite rules
        $this->build_rewrite_rules();
        
        // Apply filters for URL rewriting
        if ( ! is_admin() ) {
            add_filter( 'script_loader_src', array( $this, 'rewrite_url' ), 10, 2 );
            add_filter( 'style_loader_src', array( $this, 'rewrite_url' ), 10, 2 );
            add_filter( 'wp_get_attachment_url', array( $this, 'rewrite_url' ) );
            add_filter( 'theme_file_uri', array( $this, 'rewrite_url' ) );
            add_filter( 'plugins_url', array( $this, 'rewrite_url' ) );
            
            // Content filters
            add_filter( 'the_content', array( $this, 'rewrite_content_urls' ), 99 );
            add_filter( 'widget_text', array( $this, 'rewrite_content_urls' ), 99 );
            
            // Srcset for responsive images
            add_filter( 'wp_calculate_image_srcset', array( $this, 'rewrite_srcset' ), 10, 5 );
        }
    }
    
    /**
     * Build rewrite rules
     */
    protected function build_rewrite_rules() {
        $site_url = get_option( 'siteurl' );
        $cdn_url = $this->config['cdn_url'];
        
        foreach ( $this->config['include_dirs'] as $dir ) {
            $this->rewrite_rules[] = array(
                'pattern' => '#' . preg_quote( $site_url . '/' . $dir, '#' ) . '#',
                'replacement' => $cdn_url . '/' . $dir,
                'extensions' => $this->config['extensions']
            );
        }
    }
    
    /**
     * Rewrite URL to CDN
     * 
     * @param string $url Original URL
     * @param string $handle Optional handle
     * @return string Rewritten URL
     */
    public function rewrite_url( $url, $handle = '' ) {
        // Skip if CDN is disabled
        if ( ! $this->config['enabled'] || empty( $this->config['cdn_url'] ) ) {
            return $url;
        }
        
        // Skip admin URLs
        if ( is_admin() || is_preview() || is_user_logged_in() ) {
            return $url;
        }
        
        // Check if URL should be rewritten
        if ( ! $this->should_rewrite_url( $url ) ) {
            return $url;
        }
        
        // Apply rewrite rules
        foreach ( $this->rewrite_rules as $rule ) {
            if ( $this->matches_rule( $url, $rule ) ) {
                $url = preg_replace( $rule['pattern'], $rule['replacement'], $url );
                $this->metrics['urls_rewritten']++;
                break;
            }
        }
        
        // Add versioning if enabled
        if ( $this->config['enable_versioning'] ) {
            $url = $this->add_version_string( $url );
        }
        
        return $url;
    }
    
    /**
     * Check if URL should be rewritten
     * 
     * @param string $url URL to check
     * @return bool
     */
    protected function should_rewrite_url( $url ) {
        // Skip external URLs
        if ( ! $this->is_internal_url( $url ) ) {
            return false;
        }
        
        // Check excluded directories
        foreach ( $this->config['exclude_dirs'] as $dir ) {
            if ( strpos( $url, $dir ) !== false ) {
                return false;
            }
        }
        
        // Check file extension
        $extension = pathinfo( parse_url( $url, PHP_URL_PATH ), PATHINFO_EXTENSION );
        if ( ! in_array( $extension, $this->config['extensions'] ) ) {
            return false;
        }
        
        return true;
    }
    
    /**
     * Check if URL matches rewrite rule
     * 
     * @param string $url URL to check
     * @param array  $rule Rewrite rule
     * @return bool
     */
    protected function matches_rule( $url, $rule ) {
        // Check pattern match
        if ( ! preg_match( $rule['pattern'], $url ) ) {
            return false;
        }
        
        // Check extension
        $extension = pathinfo( parse_url( $url, PHP_URL_PATH ), PATHINFO_EXTENSION );
        return in_array( $extension, $rule['extensions'] );
    }
    
    /**
     * Check if URL is internal
     * 
     * @param string $url URL to check
     * @return bool
     */
    protected function is_internal_url( $url ) {
        $site_host = parse_url( get_site_url(), PHP_URL_HOST );
        $url_host = parse_url( $url, PHP_URL_HOST );
        
        return empty( $url_host ) || $url_host === $site_host;
    }
    
    /**
     * Add version string to URL
     * 
     * @param string $url URL
     * @return string Versioned URL
     */
    protected function add_version_string( $url ) {
        // Skip if already has query string
        if ( strpos( $url, '?' ) !== false ) {
            return $url;
        }
        
        // Add file modification time as version
        $file_path = $this->url_to_path( $url );
        if ( file_exists( $file_path ) ) {
            $version = filemtime( $file_path );
            $url .= '?v=' . $version;
        }
        
        return $url;
    }
    
    /**
     * Convert URL to file path
     * 
     * @param string $url URL
     * @return string File path
     */
    protected function url_to_path( $url ) {
        $site_url = get_site_url();
        $file_path = str_replace( $site_url, ABSPATH, $url );
        $file_path = str_replace( $this->config['cdn_url'], ABSPATH, $file_path );
        
        return parse_url( $file_path, PHP_URL_PATH );
    }
    
    /**
     * Rewrite URLs in content
     * 
     * @param string $content Content
     * @return string Content with rewritten URLs
     */
    public function rewrite_content_urls( $content ) {
        if ( ! $this->config['enabled'] || empty( $this->config['cdn_url'] ) ) {
            return $content;
        }
        
        // Find all URLs in content
        preg_match_all( '/(https?:)?\/\/[^\s<>"\']+/i', $content, $matches );
        
        foreach ( $matches[0] as $url ) {
            $rewritten = $this->rewrite_url( $url );
            if ( $rewritten !== $url ) {
                $content = str_replace( $url, $rewritten, $content );
            }
        }
        
        return $content;
    }
    
    /**
     * Rewrite srcset URLs
     * 
     * @param array  $sources Sources array
     * @param array  $size_array Size array
     * @param string $image_src Image source
     * @param array  $image_meta Image metadata
     * @param int    $attachment_id Attachment ID
     * @return array Modified sources
     */
    public function rewrite_srcset( $sources, $size_array, $image_src, $image_meta, $attachment_id ) {
        foreach ( $sources as &$source ) {
            $source['url'] = $this->rewrite_url( $source['url'] );
        }
        
        return $sources;
    }
    
    /**
     * Register hooks
     */
    protected function register_hooks() {
        // Resource hints
        add_action( 'wp_head', array( $this, 'add_resource_hints' ), 1 );
        
        // HTTP/2 Server Push
        if ( $this->config['enable_push'] ) {
            add_action( 'send_headers', array( $this, 'add_push_headers' ) );
        }
        
        // Purge hooks
        if ( $this->config['purge_on_update'] ) {
            add_action( 'save_post', array( $this, 'queue_purge_post' ) );
            add_action( 'switch_theme', array( $this, 'queue_purge_all' ) );
            add_action( 'money_quiz_settings_updated', array( $this, 'queue_purge_all' ) );
        }
        
        // Process purge queue
        add_action( 'shutdown', array( $this, 'process_purge_queue' ) );
        
        // Admin interface
        if ( is_admin() ) {
            add_action( 'admin_bar_menu', array( $this, 'add_admin_bar_menu' ), 100 );
        }
    }
    
    /**
     * Add resource hints
     */
    public function add_resource_hints() {
        if ( ! $this->config['enable_preconnect'] || empty( $this->config['cdn_url'] ) ) {
            return;
        }
        
        $cdn_host = parse_url( $this->config['cdn_url'], PHP_URL_HOST );
        
        // DNS prefetch
        echo sprintf( '<link rel="dns-prefetch" href="//%s">' . "\n", esc_attr( $cdn_host ) );
        
        // Preconnect
        echo sprintf( '<link rel="preconnect" href="%s" crossorigin>' . "\n", esc_url( $this->config['cdn_url'] ) );
        
        // Provider-specific hints
        if ( $this->config['provider'] === 'cloudflare' ) {
            echo '<link rel="dns-prefetch" href="//cdnjs.cloudflare.com">' . "\n";
        }
    }
    
    /**
     * Add HTTP/2 push headers
     */
    public function add_push_headers() {
        if ( ! $this->is_http2() ) {
            return;
        }
        
        // Get critical assets
        $critical_assets = $this->get_critical_assets();
        
        foreach ( $critical_assets as $asset ) {
            $push_header = sprintf(
                'Link: <%s>; rel=preload; as=%s',
                esc_url( $asset['url'] ),
                $asset['type']
            );
            
            header( $push_header, false );
        }
    }
    
    /**
     * Check if HTTP/2 is supported
     * 
     * @return bool
     */
    protected function is_http2() {
        return isset( $_SERVER['SERVER_PROTOCOL'] ) && 
               version_compare( $_SERVER['SERVER_PROTOCOL'], 'HTTP/2.0', '>=' );
    }
    
    /**
     * Get critical assets for push
     * 
     * @return array Critical assets
     */
    protected function get_critical_assets() {
        $assets = array();
        
        // Money Quiz core CSS
        $assets[] = array(
            'url' => $this->rewrite_url( MONEY_QUIZ_URL . 'assets/css/money-quiz.min.css' ),
            'type' => 'style'
        );
        
        // Money Quiz core JS
        $assets[] = array(
            'url' => $this->rewrite_url( MONEY_QUIZ_URL . 'assets/js/money-quiz.min.js' ),
            'type' => 'script'
        );
        
        // Theme stylesheet
        $theme_css = get_stylesheet_uri();
        if ( $theme_css ) {
            $assets[] = array(
                'url' => $this->rewrite_url( $theme_css ),
                'type' => 'style'
            );
        }
        
        return apply_filters( 'money_quiz_cdn_push_assets', $assets );
    }
    
    /**
     * Setup API client
     */
    protected function setup_api_client() {
        $provider = $this->config['provider'];
        
        if ( ! isset( $this->providers[ $provider ] ) ) {
            return;
        }
        
        $provider_info = $this->providers[ $provider ];
        
        if ( empty( $provider_info['api_endpoint'] ) || empty( $this->credentials ) ) {
            return;
        }
        
        // Provider-specific setup
        switch ( $provider ) {
            case 'cloudflare':
                $this->setup_cloudflare_api();
                break;
                
            case 'cloudfront':
                $this->setup_cloudfront_api();
                break;
                
            case 'maxcdn':
                $this->setup_maxcdn_api();
                break;
        }
    }
    
    /**
     * Setup CloudFlare API
     */
    protected function setup_cloudflare_api() {
        if ( empty( $this->credentials['api_key'] ) || empty( $this->credentials['zone_id'] ) ) {
            return;
        }
        
        $this->api_headers = array(
            'X-Auth-Key' => $this->credentials['api_key'],
            'X-Auth-Email' => $this->credentials['api_email'] ?? '',
            'Content-Type' => 'application/json'
        );
    }
    
    /**
     * Setup CloudFront API
     */
    protected function setup_cloudfront_api() {
        // AWS SDK would be initialized here
        if ( ! class_exists( 'Aws\CloudFront\CloudFrontClient' ) ) {
            return;
        }
        
        // Initialize AWS client
        $this->cloudfront_client = new \Aws\CloudFront\CloudFrontClient( array(
            'version' => 'latest',
            'region' => $this->credentials['region'] ?? 'us-east-1',
            'credentials' => array(
                'key' => $this->credentials['aws_key'] ?? '',
                'secret' => $this->credentials['aws_secret'] ?? ''
            )
        ) );
    }
    
    /**
     * Setup MaxCDN API
     */
    protected function setup_maxcdn_api() {
        $this->api_headers = array(
            'Authorization' => 'Bearer ' . $this->credentials['api_key'],
            'Content-Type' => 'application/json'
        );
    }
    
    /**
     * Queue post purge
     * 
     * @param int $post_id Post ID
     */
    public function queue_purge_post( $post_id ) {
        $post = get_post( $post_id );
        
        if ( ! $post || $post->post_status !== 'publish' ) {
            return;
        }
        
        // Queue post URL
        $this->queue_purge_url( get_permalink( $post_id ) );
        
        // Queue home page
        $this->queue_purge_url( home_url( '/' ) );
        
        // Queue feed URLs
        $this->queue_purge_url( get_bloginfo( 'rss2_url' ) );
        
        // Queue category pages
        $categories = get_the_category( $post_id );
        foreach ( $categories as $category ) {
            $this->queue_purge_url( get_category_link( $category ) );
        }
    }
    
    /**
     * Queue purge all
     */
    public function queue_purge_all() {
        $this->purge_queue[] = array(
            'type' => 'all',
            'time' => time()
        );
    }
    
    /**
     * Queue purge URL
     * 
     * @param string $url URL to purge
     */
    public function queue_purge_url( $url ) {
        $this->purge_queue[] = array(
            'type' => 'url',
            'url' => $url,
            'time' => time()
        );
    }
    
    /**
     * Process purge queue
     */
    public function process_purge_queue() {
        if ( empty( $this->purge_queue ) ) {
            return;
        }
        
        $provider = $this->config['provider'];
        $purge_method = $this->providers[ $provider ]['purge_method'] ?? 'none';
        
        if ( $purge_method === 'none' ) {
            return;
        }
        
        // Group purges by type
        $purges = array(
            'all' => false,
            'urls' => array()
        );
        
        foreach ( $this->purge_queue as $item ) {
            if ( $item['type'] === 'all' ) {
                $purges['all'] = true;
                break;
            } else {
                $purges['urls'][] = $item['url'];
            }
        }
        
        // Execute purge
        if ( $purges['all'] ) {
            $this->purge_all();
        } elseif ( ! empty( $purges['urls'] ) ) {
            $this->purge_urls( array_unique( $purges['urls'] ) );
        }
        
        // Clear queue
        $this->purge_queue = array();
    }
    
    /**
     * Purge all CDN cache
     */
    protected function purge_all() {
        switch ( $this->config['provider'] ) {
            case 'cloudflare':
                $this->purge_cloudflare_all();
                break;
                
            case 'cloudfront':
                $this->purge_cloudfront_all();
                break;
                
            case 'maxcdn':
                $this->purge_maxcdn_all();
                break;
        }
        
        $this->metrics['purges_triggered']++;
    }
    
    /**
     * Purge specific URLs
     * 
     * @param array $urls URLs to purge
     */
    protected function purge_urls( array $urls ) {
        switch ( $this->config['provider'] ) {
            case 'cloudflare':
                $this->purge_cloudflare_urls( $urls );
                break;
                
            case 'cloudfront':
                $this->purge_cloudfront_urls( $urls );
                break;
                
            case 'maxcdn':
                $this->purge_maxcdn_urls( $urls );
                break;
        }
        
        $this->metrics['purges_triggered']++;
    }
    
    /**
     * Purge CloudFlare all
     */
    protected function purge_cloudflare_all() {
        if ( empty( $this->credentials['zone_id'] ) ) {
            return;
        }
        
        $api_url = $this->providers['cloudflare']['api_endpoint'] . 
                   'zones/' . $this->credentials['zone_id'] . '/purge_cache';
        
        $response = wp_remote_post( $api_url, array(
            'headers' => $this->api_headers,
            'body' => json_encode( array( 'purge_everything' => true ) )
        ) );
        
        $this->metrics['api_calls']++;
        
        if ( is_wp_error( $response ) ) {
            error_log( 'CloudFlare purge error: ' . $response->get_error_message() );
        }
    }
    
    /**
     * Purge CloudFlare URLs
     * 
     * @param array $urls URLs to purge
     */
    protected function purge_cloudflare_urls( array $urls ) {
        if ( empty( $this->credentials['zone_id'] ) ) {
            return;
        }
        
        // CloudFlare API limits to 30 URLs per request
        $chunks = array_chunk( $urls, 30 );
        
        foreach ( $chunks as $chunk ) {
            $api_url = $this->providers['cloudflare']['api_endpoint'] . 
                       'zones/' . $this->credentials['zone_id'] . '/purge_cache';
            
            $response = wp_remote_post( $api_url, array(
                'headers' => $this->api_headers,
                'body' => json_encode( array( 'files' => $chunk ) )
            ) );
            
            $this->metrics['api_calls']++;
            
            if ( is_wp_error( $response ) ) {
                error_log( 'CloudFlare purge error: ' . $response->get_error_message() );
            }
        }
    }
    
    /**
     * Purge CloudFront all
     */
    protected function purge_cloudfront_all() {
        if ( empty( $this->cloudfront_client ) || empty( $this->credentials['distribution_id'] ) ) {
            return;
        }
        
        try {
            $result = $this->cloudfront_client->createInvalidation( array(
                'DistributionId' => $this->credentials['distribution_id'],
                'InvalidationBatch' => array(
                    'CallerReference' => 'money-quiz-' . time(),
                    'Paths' => array(
                        'Quantity' => 1,
                        'Items' => array( '/*' )
                    )
                )
            ) );
            
            $this->metrics['api_calls']++;
        } catch ( \Exception $e ) {
            error_log( 'CloudFront invalidation error: ' . $e->getMessage() );
        }
    }
    
    /**
     * Purge CloudFront URLs
     * 
     * @param array $urls URLs to purge
     */
    protected function purge_cloudfront_urls( array $urls ) {
        if ( empty( $this->cloudfront_client ) || empty( $this->credentials['distribution_id'] ) ) {
            return;
        }
        
        // Convert URLs to paths
        $paths = array_map( function( $url ) {
            return parse_url( $url, PHP_URL_PATH );
        }, $urls );
        
        try {
            $result = $this->cloudfront_client->createInvalidation( array(
                'DistributionId' => $this->credentials['distribution_id'],
                'InvalidationBatch' => array(
                    'CallerReference' => 'money-quiz-' . time(),
                    'Paths' => array(
                        'Quantity' => count( $paths ),
                        'Items' => $paths
                    )
                )
            ) );
            
            $this->metrics['api_calls']++;
        } catch ( \Exception $e ) {
            error_log( 'CloudFront invalidation error: ' . $e->getMessage() );
        }
    }
    
    /**
     * Purge MaxCDN all
     */
    protected function purge_maxcdn_all() {
        if ( empty( $this->credentials['zone_id'] ) ) {
            return;
        }
        
        $api_url = $this->providers['maxcdn']['api_endpoint'] . 
                   'zones/pull/' . $this->credentials['zone_id'] . '/cache';
        
        $response = wp_remote_request( $api_url, array(
            'method' => 'DELETE',
            'headers' => $this->api_headers
        ) );
        
        $this->metrics['api_calls']++;
        
        if ( is_wp_error( $response ) ) {
            error_log( 'MaxCDN purge error: ' . $response->get_error_message() );
        }
    }
    
    /**
     * Purge MaxCDN URLs
     * 
     * @param array $urls URLs to purge
     */
    protected function purge_maxcdn_urls( array $urls ) {
        if ( empty( $this->credentials['zone_id'] ) ) {
            return;
        }
        
        $api_url = $this->providers['maxcdn']['api_endpoint'] . 
                   'zones/pull/' . $this->credentials['zone_id'] . '/cache';
        
        $response = wp_remote_request( $api_url, array(
            'method' => 'DELETE',
            'headers' => $this->api_headers,
            'body' => json_encode( array( 'files' => $urls ) )
        ) );
        
        $this->metrics['api_calls']++;
        
        if ( is_wp_error( $response ) ) {
            error_log( 'MaxCDN purge error: ' . $response->get_error_message() );
        }
    }
    
    /**
     * Add admin bar menu
     * 
     * @param WP_Admin_Bar $wp_admin_bar Admin bar object
     */
    public function add_admin_bar_menu( $wp_admin_bar ) {
        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }
        
        $wp_admin_bar->add_node( array(
            'id' => 'money-quiz-cdn',
            'title' => 'CDN',
            'parent' => 'top-secondary'
        ) );
        
        $wp_admin_bar->add_node( array(
            'id' => 'money-quiz-cdn-status',
            'title' => $this->config['enabled'] ? 'CDN: Active' : 'CDN: Inactive',
            'parent' => 'money-quiz-cdn'
        ) );
        
        if ( $this->config['enabled'] ) {
            $wp_admin_bar->add_node( array(
                'id' => 'money-quiz-cdn-provider',
                'title' => 'Provider: ' . $this->providers[ $this->config['provider'] ]['name'],
                'parent' => 'money-quiz-cdn'
            ) );
            
            $wp_admin_bar->add_node( array(
                'id' => 'money-quiz-cdn-stats',
                'title' => sprintf( 'URLs Rewritten: %d', $this->metrics['urls_rewritten'] ),
                'parent' => 'money-quiz-cdn'
            ) );
            
            $wp_admin_bar->add_node( array(
                'id' => 'money-quiz-cdn-purge',
                'title' => 'Purge CDN Cache',
                'parent' => 'money-quiz-cdn',
                'href' => wp_nonce_url( 
                    admin_url( 'admin-post.php?action=money_quiz_purge_cdn' ),
                    'purge_cdn'
                )
            ) );
        }
    }
    
    /**
     * Decrypt credentials
     * 
     * @param string $encrypted Encrypted credentials
     * @return array Decrypted credentials
     */
    protected function decrypt_credentials( $encrypted ) {
        if ( ! function_exists( 'openssl_decrypt' ) ) {
            return array();
        }
        
        $key = wp_salt( 'auth' );
        $method = 'AES-256-CBC';
        
        $data = base64_decode( $encrypted );
        $iv_length = openssl_cipher_iv_length( $method );
        $iv = substr( $data, 0, $iv_length );
        $encrypted = substr( $data, $iv_length );
        
        $decrypted = openssl_decrypt( $encrypted, $method, $key, 0, $iv );
        
        return $decrypted ? json_decode( $decrypted, true ) : array();
    }
    
    /**
     * Get CDN statistics
     * 
     * @return array Statistics
     */
    public function get_stats() {
        return array(
            'enabled' => $this->config['enabled'],
            'provider' => $this->config['provider'],
            'urls_rewritten' => $this->metrics['urls_rewritten'],
            'bytes_offloaded' => $this->metrics['bytes_offloaded'],
            'purges_triggered' => $this->metrics['purges_triggered'],
            'api_calls' => $this->metrics['api_calls']
        );
    }
}

// Initialize CDN integration
global $money_quiz_cdn;
$money_quiz_cdn = new CDNIntegration();
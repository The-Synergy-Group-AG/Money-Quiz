<?php
/**
 * Money Quiz Plugin - Page Cache System
 * Worker 3: Full Page Caching Implementation
 * 
 * Implements advanced full page caching with support for dynamic content,
 * user-specific variations, and intelligent cache preloading.
 * 
 * @package MoneyQuiz
 * @subpackage Performance\Caching
 * @since 5.0.0
 */

namespace MoneyQuiz\Performance\Caching;

use MoneyQuiz\Performance\CacheManager;

/**
 * Page Cache System Class
 * 
 * Provides full page caching capabilities with advanced features
 */
class PageCacheSystem {
    
    /**
     * Cache manager instance
     * 
     * @var CacheManager
     */
    protected $cache_manager;
    
    /**
     * Page cache configuration
     * 
     * @var array
     */
    protected $config = array(
        'enabled' => true,
        'ttl' => 3600,
        'exclude_logged_in' => true,
        'exclude_urls' => array(),
        'exclude_cookies' => array(),
        'exclude_user_agents' => array(),
        'cache_query_strings' => false,
        'compression' => true,
        'minify_html' => true
    );
    
    /**
     * Dynamic content markers
     * 
     * @var array
     */
    protected $dynamic_markers = array();
    
    /**
     * Cache variations
     * 
     * @var array
     */
    protected $variations = array();
    
    /**
     * Performance metrics
     * 
     * @var array
     */
    protected $metrics = array(
        'cache_hits' => 0,
        'cache_misses' => 0,
        'bytes_saved' => 0,
        'time_saved' => 0
    );
    
    /**
     * Output buffer level
     * 
     * @var int
     */
    protected $ob_level = 0;
    
    /**
     * Constructor
     */
    public function __construct() {
        $this->cache_manager = CacheManager::get_instance();
        $this->load_config();
        $this->init();
    }
    
    /**
     * Initialize page caching
     */
    protected function init() {
        if ( ! $this->config['enabled'] ) {
            return;
        }
        
        // Early cache check
        add_action( 'init', array( $this, 'maybe_serve_cached_page' ), 1 );
        
        // Late cache capture
        add_action( 'template_redirect', array( $this, 'start_page_cache' ), -999999 );
        
        // Cache preloading
        add_action( 'money_quiz_cache_preload', array( $this, 'preload_pages' ) );
        
        // Cache invalidation
        $this->register_invalidation_hooks();
        
        // Admin interface
        if ( is_admin() ) {
            $this->init_admin();
        }
    }
    
    /**
     * Maybe serve cached page
     */
    public function maybe_serve_cached_page() {
        // Skip if conditions aren't met
        if ( ! $this->should_cache_page() ) {
            return;
        }
        
        // Generate cache key
        $cache_key = $this->generate_cache_key();
        
        // Try to get cached page
        $cached_page = $this->cache_manager->get( $cache_key, 'pages' );
        
        if ( false !== $cached_page ) {
            // Validate cache
            if ( $this->validate_cached_page( $cached_page ) ) {
                $this->serve_cached_page( $cached_page );
                exit;
            }
        }
    }
    
    /**
     * Start page cache capture
     */
    public function start_page_cache() {
        if ( ! $this->should_cache_page() ) {
            return;
        }
        
        // Start output buffering
        $this->ob_level = ob_get_level();
        ob_start( array( $this, 'cache_page_output' ) );
        
        // Add cache info header
        header( 'X-Money-Quiz-Cache: MISS' );
        header( 'X-Money-Quiz-Cache-Key: ' . $this->generate_cache_key() );
    }
    
    /**
     * Cache page output
     * 
     * @param string $output Page output
     * @return string Modified output
     */
    public function cache_page_output( $output ) {
        // Only process if we're at the right buffer level
        if ( ob_get_level() !== $this->ob_level ) {
            return $output;
        }
        
        // Skip if output is empty or partial
        if ( empty( $output ) || strlen( $output ) < 100 ) {
            return $output;
        }
        
        // Skip if there were errors
        if ( ! empty( ob_get_status()['flags'] ) ) {
            return $output;
        }
        
        // Process output
        $processed_output = $this->process_output( $output );
        
        // Generate cache data
        $cache_data = array(
            'content' => $processed_output,
            'headers' => $this->get_cacheable_headers(),
            'created' => time(),
            'ttl' => $this->get_page_ttl(),
            'size' => strlen( $processed_output ),
            'variations' => $this->get_current_variations(),
            'dynamic_content' => $this->extract_dynamic_content( $output )
        );
        
        // Store in cache
        $cache_key = $this->generate_cache_key();
        $this->cache_manager->set( $cache_key, $cache_data, 'pages', $cache_data['ttl'] );
        
        // Update metrics
        $this->metrics['cache_misses']++;
        
        return $processed_output;
    }
    
    /**
     * Serve cached page
     * 
     * @param array $cached_page Cached page data
     */
    protected function serve_cached_page( array $cached_page ) {
        // Send cached headers
        foreach ( $cached_page['headers'] as $header ) {
            header( $header );
        }
        
        // Add cache headers
        header( 'X-Money-Quiz-Cache: HIT' );
        header( 'X-Money-Quiz-Cache-Age: ' . ( time() - $cached_page['created'] ) );
        header( 'X-Money-Quiz-Cache-Expires: ' . ( $cached_page['created'] + $cached_page['ttl'] - time() ) );
        
        // Process dynamic content
        $content = $this->process_dynamic_content( $cached_page['content'], $cached_page['dynamic_content'] );
        
        // Send content
        echo $content;
        
        // Update metrics
        $this->metrics['cache_hits']++;
        $this->metrics['bytes_saved'] += $cached_page['size'];
        $this->metrics['time_saved'] += microtime( true ) - $_SERVER['REQUEST_TIME_FLOAT'];
        
        // Log cache hit
        if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
            error_log( sprintf(
                'Money Quiz Page Cache HIT: %s (Age: %ds, Size: %s)',
                $_SERVER['REQUEST_URI'],
                time() - $cached_page['created'],
                size_format( $cached_page['size'] )
            ) );
        }
    }
    
    /**
     * Should cache page
     * 
     * @return bool
     */
    protected function should_cache_page() {
        // Check if caching is enabled
        if ( ! $this->config['enabled'] ) {
            return false;
        }
        
        // Skip POST requests
        if ( $_SERVER['REQUEST_METHOD'] !== 'GET' ) {
            return false;
        }
        
        // Skip admin pages
        if ( is_admin() ) {
            return false;
        }
        
        // Skip AJAX requests
        if ( wp_doing_ajax() ) {
            return false;
        }
        
        // Skip if user is logged in (if configured)
        if ( $this->config['exclude_logged_in'] && is_user_logged_in() ) {
            return false;
        }
        
        // Check excluded URLs
        $current_url = $_SERVER['REQUEST_URI'];
        foreach ( $this->config['exclude_urls'] as $pattern ) {
            if ( preg_match( $pattern, $current_url ) ) {
                return false;
            }
        }
        
        // Check excluded cookies
        foreach ( $this->config['exclude_cookies'] as $cookie ) {
            if ( isset( $_COOKIE[ $cookie ] ) ) {
                return false;
            }
        }
        
        // Check excluded user agents
        $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? '';
        foreach ( $this->config['exclude_user_agents'] as $pattern ) {
            if ( preg_match( $pattern, $user_agent ) ) {
                return false;
            }
        }
        
        // Check query strings
        if ( ! $this->config['cache_query_strings'] && ! empty( $_SERVER['QUERY_STRING'] ) ) {
            return false;
        }
        
        // Allow filtering
        return apply_filters( 'money_quiz_should_cache_page', true );
    }
    
    /**
     * Generate cache key
     * 
     * @return string Cache key
     */
    protected function generate_cache_key() {
        $key_parts = array(
            'page',
            md5( $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'] )
        );
        
        // Add variations
        $variations = $this->get_current_variations();
        if ( ! empty( $variations ) ) {
            $key_parts[] = md5( serialize( $variations ) );
        }
        
        return implode( '_', $key_parts );
    }
    
    /**
     * Get current variations
     * 
     * @return array Variations
     */
    protected function get_current_variations() {
        $variations = array();
        
        // Mobile variation
        if ( wp_is_mobile() ) {
            $variations['mobile'] = 1;
        }
        
        // HTTPS variation
        if ( is_ssl() ) {
            $variations['https'] = 1;
        }
        
        // Language variation
        if ( function_exists( 'pll_current_language' ) ) {
            $variations['lang'] = pll_current_language();
        } elseif ( defined( 'ICL_LANGUAGE_CODE' ) ) {
            $variations['lang'] = ICL_LANGUAGE_CODE;
        }
        
        // Custom variations
        $variations = apply_filters( 'money_quiz_page_cache_variations', $variations );
        
        return $variations;
    }
    
    /**
     * Get cacheable headers
     * 
     * @return array Headers
     */
    protected function get_cacheable_headers() {
        $headers = array();
        $current_headers = headers_list();
        
        $cacheable_types = array(
            'Content-Type',
            'Content-Language',
            'X-Robots-Tag',
            'X-Content-Type-Options',
            'X-Frame-Options'
        );
        
        foreach ( $current_headers as $header ) {
            foreach ( $cacheable_types as $type ) {
                if ( stripos( $header, $type ) === 0 ) {
                    $headers[] = $header;
                    break;
                }
            }
        }
        
        return $headers;
    }
    
    /**
     * Get page TTL
     * 
     * @return int TTL in seconds
     */
    protected function get_page_ttl() {
        $ttl = $this->config['ttl'];
        
        // Shorter TTL for Money Quiz pages
        if ( $this->is_money_quiz_page() ) {
            $ttl = min( $ttl, 1800 ); // 30 minutes max
        }
        
        // Allow filtering
        return apply_filters( 'money_quiz_page_cache_ttl', $ttl );
    }
    
    /**
     * Check if current page is Money Quiz page
     * 
     * @return bool
     */
    protected function is_money_quiz_page() {
        // Check shortcodes
        global $post;
        if ( $post && has_shortcode( $post->post_content, 'money_quiz' ) ) {
            return true;
        }
        
        // Check URL patterns
        $patterns = array(
            '/money-quiz/',
            '/quiz/',
            '/results/'
        );
        
        $current_url = $_SERVER['REQUEST_URI'];
        foreach ( $patterns as $pattern ) {
            if ( strpos( $current_url, $pattern ) !== false ) {
                return true;
            }
        }
        
        return false;
    }
    
    /**
     * Process output
     * 
     * @param string $output Original output
     * @return string Processed output
     */
    protected function process_output( $output ) {
        // Minify HTML if enabled
        if ( $this->config['minify_html'] ) {
            $output = $this->minify_html( $output );
        }
        
        // Compress if enabled
        if ( $this->config['compression'] && ! ini_get( 'zlib.output_compression' ) ) {
            $output = gzencode( $output, 6 );
            header( 'Content-Encoding: gzip' );
        }
        
        return $output;
    }
    
    /**
     * Minify HTML
     * 
     * @param string $html HTML content
     * @return string Minified HTML
     */
    protected function minify_html( $html ) {
        // Preserve pre, textarea, and script content
        $preserved = array();
        $html = preg_replace_callback(
            '/<(pre|textarea|script)[^>]*>.*?<\/\1>/is',
            function( $matches ) use ( &$preserved ) {
                $key = '<!--PRESERVE' . count( $preserved ) . '-->';
                $preserved[ $key ] = $matches[0];
                return $key;
            },
            $html
        );
        
        // Minify
        $html = preg_replace( array(
            '/\s+/',                    // Multiple spaces
            '/>\s+</',                  // Spaces between tags
            '/<!--(?!PRESERVE)[^>]*?-->/'  // Comments (except preserved markers)
        ), array(
            ' ',
            '><',
            ''
        ), $html );
        
        // Restore preserved content
        foreach ( $preserved as $key => $content ) {
            $html = str_replace( $key, $content, $html );
        }
        
        return trim( $html );
    }
    
    /**
     * Extract dynamic content
     * 
     * @param string $output Page output
     * @return array Dynamic content markers
     */
    protected function extract_dynamic_content( $output ) {
        $dynamic = array();
        
        // Extract nonces
        preg_match_all( '/wp_nonce="([^"]+)"/', $output, $nonce_matches );
        if ( ! empty( $nonce_matches[1] ) ) {
            $dynamic['nonces'] = array_unique( $nonce_matches[1] );
        }
        
        // Extract CSRF tokens
        preg_match_all( '/<input[^>]+name="([^"]*token[^"]*)"[^>]+value="([^"]+)"/', $output, $token_matches );
        if ( ! empty( $token_matches[1] ) ) {
            $dynamic['tokens'] = array_combine( $token_matches[1], $token_matches[2] );
        }
        
        // Extract user-specific content
        if ( is_user_logged_in() ) {
            $user = wp_get_current_user();
            $dynamic['user'] = array(
                'id' => $user->ID,
                'name' => $user->display_name,
                'email' => $user->user_email
            );
        }
        
        return $dynamic;
    }
    
    /**
     * Process dynamic content
     * 
     * @param string $content Cached content
     * @param array  $dynamic Dynamic content markers
     * @return string Processed content
     */
    protected function process_dynamic_content( $content, array $dynamic ) {
        // Replace nonces
        if ( ! empty( $dynamic['nonces'] ) ) {
            foreach ( $dynamic['nonces'] as $old_nonce ) {
                $action = $this->guess_nonce_action( $old_nonce );
                $new_nonce = wp_create_nonce( $action );
                $content = str_replace( $old_nonce, $new_nonce, $content );
            }
        }
        
        // Replace tokens
        if ( ! empty( $dynamic['tokens'] ) ) {
            foreach ( $dynamic['tokens'] as $name => $old_token ) {
                $new_token = wp_create_nonce( 'money_quiz_' . $name );
                $content = str_replace( $old_token, $new_token, $content );
            }
        }
        
        // Replace user content
        if ( ! empty( $dynamic['user'] ) && is_user_logged_in() ) {
            $user = wp_get_current_user();
            $content = str_replace( array(
                '{{user_id}}',
                '{{user_name}}',
                '{{user_email}}'
            ), array(
                $user->ID,
                $user->display_name,
                $user->user_email
            ), $content );
        }
        
        return $content;
    }
    
    /**
     * Guess nonce action from nonce value
     * 
     * @param string $nonce Nonce value
     * @return string Guessed action
     */
    protected function guess_nonce_action( $nonce ) {
        // Common Money Quiz actions
        $actions = array(
            'money_quiz_submit',
            'money_quiz_results',
            'money_quiz_download',
            'money_quiz_share'
        );
        
        foreach ( $actions as $action ) {
            if ( wp_verify_nonce( $nonce, $action ) ) {
                return $action;
            }
        }
        
        return 'money_quiz_default';
    }
    
    /**
     * Validate cached page
     * 
     * @param array $cached_page Cached page data
     * @return bool Valid
     */
    protected function validate_cached_page( array $cached_page ) {
        // Check expiration
        if ( time() > $cached_page['created'] + $cached_page['ttl'] ) {
            return false;
        }
        
        // Check variations match
        $current_variations = $this->get_current_variations();
        if ( $current_variations !== $cached_page['variations'] ) {
            return false;
        }
        
        // Allow custom validation
        return apply_filters( 'money_quiz_validate_cached_page', true, $cached_page );
    }
    
    /**
     * Register invalidation hooks
     */
    protected function register_invalidation_hooks() {
        // Content changes
        add_action( 'save_post', array( $this, 'invalidate_post_pages' ) );
        add_action( 'delete_post', array( $this, 'invalidate_post_pages' ) );
        add_action( 'switch_theme', array( $this, 'invalidate_all_pages' ) );
        
        // Money Quiz specific
        add_action( 'money_quiz_settings_updated', array( $this, 'invalidate_all_pages' ) );
        add_action( 'money_quiz_archetype_updated', array( $this, 'invalidate_quiz_pages' ) );
        add_action( 'money_quiz_questions_updated', array( $this, 'invalidate_quiz_pages' ) );
    }
    
    /**
     * Invalidate post pages
     * 
     * @param int $post_id Post ID
     */
    public function invalidate_post_pages( $post_id ) {
        $post = get_post( $post_id );
        
        if ( ! $post || $post->post_status !== 'publish' ) {
            return;
        }
        
        // Invalidate post page
        $this->invalidate_url( get_permalink( $post_id ) );
        
        // Invalidate home page
        $this->invalidate_url( home_url( '/' ) );
        
        // Invalidate archives
        $this->invalidate_archives( $post );
    }
    
    /**
     * Invalidate URL
     * 
     * @param string $url URL to invalidate
     */
    public function invalidate_url( $url ) {
        $parsed = parse_url( $url );
        $cache_key = 'page_' . md5( $parsed['host'] . $parsed['path'] . ( $parsed['query'] ?? '' ) );
        
        // Delete all variations
        $this->cache_manager->delete( $cache_key . '*', 'pages' );
    }
    
    /**
     * Invalidate archives
     * 
     * @param WP_Post $post Post object
     */
    protected function invalidate_archives( $post ) {
        // Category archives
        $categories = get_the_category( $post->ID );
        foreach ( $categories as $category ) {
            $this->invalidate_url( get_category_link( $category ) );
        }
        
        // Tag archives
        $tags = get_the_tags( $post->ID );
        if ( $tags ) {
            foreach ( $tags as $tag ) {
                $this->invalidate_url( get_tag_link( $tag ) );
            }
        }
        
        // Date archives
        $this->invalidate_url( get_year_link( get_the_date( 'Y', $post ) ) );
        $this->invalidate_url( get_month_link( get_the_date( 'Y', $post ), get_the_date( 'm', $post ) ) );
    }
    
    /**
     * Invalidate all pages
     */
    public function invalidate_all_pages() {
        $this->cache_manager->flush_group( 'pages' );
    }
    
    /**
     * Invalidate quiz pages
     */
    public function invalidate_quiz_pages() {
        // Find all pages with Money Quiz shortcodes
        global $wpdb;
        
        $pages = $wpdb->get_results(
            "SELECT ID FROM {$wpdb->posts} 
             WHERE post_content LIKE '%[money_quiz%' 
             AND post_status = 'publish'",
            ARRAY_A
        );
        
        foreach ( $pages as $page ) {
            $this->invalidate_url( get_permalink( $page['ID'] ) );
        }
    }
    
    /**
     * Preload pages
     */
    public function preload_pages() {
        // Get URLs to preload
        $urls = $this->get_preload_urls();
        
        foreach ( $urls as $url ) {
            // Make internal request to warm cache
            wp_remote_get( $url, array(
                'timeout' => 30,
                'blocking' => false,
                'headers' => array(
                    'X-Money-Quiz-Preload' => '1'
                )
            ) );
        }
    }
    
    /**
     * Get URLs to preload
     * 
     * @return array URLs
     */
    protected function get_preload_urls() {
        $urls = array();
        
        // Home page
        $urls[] = home_url( '/' );
        
        // Money Quiz pages
        global $wpdb;
        $pages = $wpdb->get_results(
            "SELECT ID FROM {$wpdb->posts} 
             WHERE post_content LIKE '%[money_quiz%' 
             AND post_status = 'publish'
             LIMIT 10",
            ARRAY_A
        );
        
        foreach ( $pages as $page ) {
            $urls[] = get_permalink( $page['ID'] );
        }
        
        // Recent posts
        $recent_posts = get_posts( array(
            'posts_per_page' => 5,
            'post_status' => 'publish'
        ) );
        
        foreach ( $recent_posts as $post ) {
            $urls[] = get_permalink( $post );
        }
        
        return apply_filters( 'money_quiz_preload_urls', $urls );
    }
    
    /**
     * Load configuration
     */
    protected function load_config() {
        $saved_config = get_option( 'money_quiz_page_cache_config', array() );
        $this->config = wp_parse_args( $saved_config, $this->config );
    }
    
    /**
     * Initialize admin interface
     */
    protected function init_admin() {
        add_action( 'admin_bar_menu', array( $this, 'add_admin_bar_menu' ), 100 );
        add_action( 'admin_post_money_quiz_clear_page_cache', array( $this, 'handle_clear_cache' ) );
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
            'id' => 'money-quiz-page-cache',
            'title' => 'Page Cache',
            'parent' => 'money-quiz-cache'
        ) );
        
        $stats = $this->get_stats();
        
        $wp_admin_bar->add_node( array(
            'id' => 'money-quiz-page-cache-stats',
            'title' => sprintf( 'Hit Rate: %.1f%%', $stats['hit_rate'] ),
            'parent' => 'money-quiz-page-cache'
        ) );
        
        $wp_admin_bar->add_node( array(
            'id' => 'money-quiz-page-cache-clear',
            'title' => 'Clear Page Cache',
            'parent' => 'money-quiz-page-cache',
            'href' => wp_nonce_url( 
                admin_url( 'admin-post.php?action=money_quiz_clear_page_cache' ),
                'clear_page_cache'
            )
        ) );
    }
    
    /**
     * Handle clear cache request
     */
    public function handle_clear_cache() {
        check_admin_referer( 'clear_page_cache' );
        
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( 'Unauthorized' );
        }
        
        $this->invalidate_all_pages();
        
        wp_redirect( wp_get_referer() );
        exit;
    }
    
    /**
     * Get statistics
     * 
     * @return array Statistics
     */
    public function get_stats() {
        $total = $this->metrics['cache_hits'] + $this->metrics['cache_misses'];
        
        return array(
            'hits' => $this->metrics['cache_hits'],
            'misses' => $this->metrics['cache_misses'],
            'hit_rate' => $total > 0 ? ( $this->metrics['cache_hits'] / $total ) * 100 : 0,
            'bytes_saved' => $this->metrics['bytes_saved'],
            'time_saved' => $this->metrics['time_saved'],
            'avg_time_saved' => $this->metrics['cache_hits'] > 0 ? 
                $this->metrics['time_saved'] / $this->metrics['cache_hits'] : 0
        );
    }
}

// Initialize page cache system
global $money_quiz_page_cache;
$money_quiz_page_cache = new PageCacheSystem();
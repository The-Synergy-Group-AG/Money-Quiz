<?php
/**
 * Money Quiz Plugin - Fragment Cache
 * 
 * Provides easy-to-use fragment caching for partial page caching
 * 
 * @package MoneyQuiz
 * @subpackage Performance
 * @since 5.0.0
 */

namespace MoneyQuiz\Performance;

/**
 * Fragment Cache Class
 * 
 * Simplifies caching of template fragments
 */
class FragmentCache {
    
    /**
     * Cache manager instance
     * 
     * @var CacheManager
     */
    protected static $cache_manager;
    
    /**
     * Active fragment stack
     * 
     * @var array
     */
    protected static $fragment_stack = array();
    
    /**
     * Initialize fragment cache
     */
    public static function init() {
        self::$cache_manager = CacheManager::get_instance();
    }
    
    /**
     * Start fragment caching
     * 
     * @param string $key Unique fragment key
     * @param array  $args Arguments that affect the fragment
     * @param int    $expiration Cache expiration in seconds
     * @return bool True if cached content was found and output
     */
    public static function start( $key, array $args = array(), $expiration = 1800 ) {
        // Build cache key
        $cache_key = self::build_cache_key( $key, $args );
        
        // Try to get cached fragment
        $cached = self::$cache_manager->get( $cache_key, 'fragments' );
        
        if ( false !== $cached ) {
            // Output cached content
            echo $cached;
            return true;
        }
        
        // Start output buffering
        ob_start();
        
        // Push to stack
        self::$fragment_stack[] = array(
            'key' => $cache_key,
            'expiration' => $expiration
        );
        
        return false;
    }
    
    /**
     * End fragment caching
     */
    public static function end() {
        if ( empty( self::$fragment_stack ) ) {
            return;
        }
        
        // Get fragment info
        $fragment = array_pop( self::$fragment_stack );
        
        // Get buffered content
        $content = ob_get_clean();
        
        // Cache the content
        self::$cache_manager->set( 
            $fragment['key'], 
            $content, 
            'fragments', 
            $fragment['expiration'] 
        );
        
        // Output the content
        echo $content;
    }
    
    /**
     * Cache a fragment with a callback
     * 
     * @param string   $key Fragment key
     * @param callable $callback Function that generates the fragment
     * @param array    $args Arguments for cache key
     * @param int      $expiration Cache expiration
     * @return string Fragment content
     */
    public static function cache( $key, callable $callback, array $args = array(), $expiration = 1800 ) {
        $cache_key = self::build_cache_key( $key, $args );
        
        return self::$cache_manager->remember( 
            $cache_key, 
            $callback, 
            'fragments', 
            $expiration 
        );
    }
    
    /**
     * Clear fragment cache
     * 
     * @param string $key Fragment key (optional, clears all if not provided)
     * @param array  $args Arguments used when caching
     */
    public static function clear( $key = null, array $args = array() ) {
        if ( null === $key ) {
            // Clear all fragments
            self::$cache_manager->flush_group( 'fragments' );
        } else {
            // Clear specific fragment
            $cache_key = self::build_cache_key( $key, $args );
            self::$cache_manager->delete( $cache_key, 'fragments' );
        }
    }
    
    /**
     * Build cache key from fragment key and arguments
     * 
     * @param string $key Fragment key
     * @param array  $args Arguments
     * @return string Cache key
     */
    protected static function build_cache_key( $key, array $args ) {
        // Add common variables that might affect output
        $args['user_logged_in'] = is_user_logged_in();
        $args['user_role'] = wp_get_current_user()->roles[0] ?? 'guest';
        $args['locale'] = get_locale();
        $args['is_mobile'] = wp_is_mobile();
        
        // Sort args for consistent key generation
        ksort( $args );
        
        return $key . '_' . md5( serialize( $args ) );
    }
}

// Initialize on load
add_action( 'init', array( 'MoneyQuiz\Performance\FragmentCache', 'init' ) );

/**
 * Helper function for fragment caching in templates
 * 
 * Usage:
 * if ( ! money_quiz_fragment_cache( 'sidebar_stats', array( 'page' => $page_id ) ) ) : ?>
 *     <!-- expensive content generation -->
 * <?php money_quiz_fragment_cache_end(); endif;
 */
function money_quiz_fragment_cache( $key, $args = array(), $expiration = 1800 ) {
    return FragmentCache::start( $key, $args, $expiration );
}

function money_quiz_fragment_cache_end() {
    FragmentCache::end();
}

/**
 * Page Cache Implementation
 */
class PageCache {
    
    /**
     * Cache manager
     * 
     * @var CacheManager
     */
    protected $cache_manager;
    
    /**
     * Page cache enabled
     * 
     * @var bool
     */
    protected $enabled = true;
    
    /**
     * Cache key
     * 
     * @var string
     */
    protected $cache_key;
    
    /**
     * Constructor
     */
    public function __construct() {
        $this->cache_manager = CacheManager::get_instance();
        
        // Check if page cache is enabled
        $this->enabled = ! is_user_logged_in() && 
                        ! is_admin() && 
                        'GET' === $_SERVER['REQUEST_METHOD'] &&
                        ! defined( 'DOING_AJAX' );
        
        if ( $this->enabled ) {
            $this->init();
        }
    }
    
    /**
     * Initialize page cache
     */
    protected function init() {
        // Build cache key
        $this->cache_key = $this->build_cache_key();
        
        // Try to serve from cache
        add_action( 'init', array( $this, 'serve_from_cache' ), 1 );
        
        // Start output buffering to capture page
        add_action( 'template_redirect', array( $this, 'start_buffering' ), -999 );
        
        // Save to cache on shutdown
        add_action( 'shutdown', array( $this, 'save_to_cache' ), 999 );
    }
    
    /**
     * Serve page from cache if available
     */
    public function serve_from_cache() {
        if ( ! $this->should_cache() ) {
            return;
        }
        
        $cached = $this->cache_manager->get( $this->cache_key, 'pages' );
        
        if ( false !== $cached && ! empty( $cached['content'] ) ) {
            // Send headers
            if ( ! empty( $cached['headers'] ) ) {
                foreach ( $cached['headers'] as $header ) {
                    header( $header );
                }
            }
            
            // Send cached headers
            header( 'X-Money-Quiz-Cache: HIT' );
            header( 'X-Money-Quiz-Cache-Time: ' . $cached['time'] );
            
            // Output content
            echo $cached['content'];
            
            // Stop execution
            exit;
        }
    }
    
    /**
     * Start output buffering
     */
    public function start_buffering() {
        if ( ! $this->should_cache() ) {
            return;
        }
        
        ob_start();
    }
    
    /**
     * Save page to cache
     */
    public function save_to_cache() {
        if ( ! $this->should_cache() || ! ob_get_level() ) {
            return;
        }
        
        $content = ob_get_contents();
        
        // Only cache successful responses
        if ( http_response_code() !== 200 || empty( $content ) ) {
            return;
        }
        
        // Don't cache if there are errors
        if ( strpos( $content, 'Fatal error' ) !== false || 
             strpos( $content, 'Warning:' ) !== false ) {
            return;
        }
        
        // Prepare cache data
        $cache_data = array(
            'content' => $content,
            'headers' => headers_list(),
            'time' => current_time( 'mysql' )
        );
        
        // Save to cache
        $expiration = $this->get_cache_expiration();
        $this->cache_manager->set( $this->cache_key, $cache_data, 'pages', $expiration );
        
        // Add cache headers
        header( 'X-Money-Quiz-Cache: MISS' );
    }
    
    /**
     * Check if current page should be cached
     * 
     * @return bool
     */
    protected function should_cache() {
        // Don't cache if disabled
        if ( ! $this->enabled ) {
            return false;
        }
        
        // Don't cache search results
        if ( is_search() ) {
            return false;
        }
        
        // Don't cache 404 pages
        if ( is_404() ) {
            return false;
        }
        
        // Don't cache if there are GET parameters (except allowed ones)
        $allowed_params = array( 'page', 'paged' );
        $get_params = array_diff( array_keys( $_GET ), $allowed_params );
        if ( ! empty( $get_params ) ) {
            return false;
        }
        
        // Check if Money Quiz page
        return $this->is_money_quiz_page();
    }
    
    /**
     * Build cache key for current page
     * 
     * @return string
     */
    protected function build_cache_key() {
        $parts = array(
            'url' => $_SERVER['REQUEST_URI'],
            'host' => $_SERVER['HTTP_HOST'],
            'mobile' => wp_is_mobile() ? 'mobile' : 'desktop',
            'ssl' => is_ssl() ? 'https' : 'http'
        );
        
        return 'page_' . md5( serialize( $parts ) );
    }
    
    /**
     * Get cache expiration time
     * 
     * @return int
     */
    protected function get_cache_expiration() {
        // Shorter expiration for quiz pages (they might change more often)
        if ( strpos( $_SERVER['REQUEST_URI'], 'quiz' ) !== false ) {
            return 1800; // 30 minutes
        }
        
        // Longer expiration for static pages
        return 3600; // 1 hour
    }
    
    /**
     * Check if current page is Money Quiz page
     * 
     * @return bool
     */
    protected function is_money_quiz_page() {
        // Check by URL patterns
        $patterns = array( 'money-quiz', 'quiz', 'personality-test' );
        
        foreach ( $patterns as $pattern ) {
            if ( strpos( $_SERVER['REQUEST_URI'], $pattern ) !== false ) {
                return true;
            }
        }
        
        // Check by shortcode (would need to be set earlier in execution)
        if ( defined( 'MONEY_QUIZ_PAGE' ) && MONEY_QUIZ_PAGE ) {
            return true;
        }
        
        return false;
    }
}

// Initialize page cache
if ( ! is_admin() && ! defined( 'DOING_AJAX' ) && ! defined( 'DOING_CRON' ) ) {
    new PageCache();
}

/**
 * Cache Warmer
 * 
 * Pre-populates cache with commonly accessed data
 */
class CacheWarmer {
    
    /**
     * URLs to warm
     * 
     * @var array
     */
    protected $urls = array();
    
    /**
     * Warm the cache
     */
    public function warm() {
        // Get URLs to warm
        $this->urls = $this->get_urls_to_warm();
        
        // Schedule warming in background
        if ( ! wp_next_scheduled( 'money_quiz_warm_cache' ) ) {
            wp_schedule_event( time(), 'hourly', 'money_quiz_warm_cache' );
        }
        
        add_action( 'money_quiz_warm_cache', array( $this, 'process_warming' ) );
    }
    
    /**
     * Get URLs that should be warmed
     * 
     * @return array
     */
    protected function get_urls_to_warm() {
        $urls = array();
        
        // Quiz pages
        $quiz_pages = get_posts( array(
            'post_type' => 'page',
            'meta_key' => '_money_quiz_page',
            'posts_per_page' => -1
        ));
        
        foreach ( $quiz_pages as $page ) {
            $urls[] = get_permalink( $page );
        }
        
        // Popular landing pages
        $urls[] = home_url( '/quiz/' );
        $urls[] = home_url( '/personality-test/' );
        $urls[] = home_url( '/money-personality/' );
        
        return apply_filters( 'money_quiz_cache_warm_urls', $urls );
    }
    
    /**
     * Process cache warming
     */
    public function process_warming() {
        foreach ( $this->urls as $url ) {
            // Make HTTP request to warm cache
            wp_remote_get( $url, array(
                'timeout' => 30,
                'blocking' => false,
                'headers' => array(
                    'X-Money-Quiz-Cache-Warm' => '1'
                )
            ));
            
            // Small delay between requests
            usleep( 100000 ); // 0.1 second
        }
    }
}
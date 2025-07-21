<?php
/**
 * Money Quiz Plugin - Advanced Fragment Caching
 * Worker 3: Fragment Caching System
 * 
 * Implements advanced fragment caching with support for ESI (Edge Side Includes),
 * AJAX-based lazy loading, and intelligent cache invalidation.
 * 
 * @package MoneyQuiz
 * @subpackage Performance\Caching
 * @since 5.0.0
 */

namespace MoneyQuiz\Performance\Caching;

use MoneyQuiz\Performance\CacheManager;

/**
 * Advanced Fragment Cache Class
 * 
 * Provides sophisticated fragment caching capabilities
 */
class FragmentCacheAdvanced {
    
    /**
     * Cache manager instance
     * 
     * @var CacheManager
     */
    protected $cache_manager;
    
    /**
     * Fragment stack for nested caching
     * 
     * @var array
     */
    protected $fragment_stack = array();
    
    /**
     * ESI enabled flag
     * 
     * @var bool
     */
    protected $esi_enabled = false;
    
    /**
     * Fragment dependencies
     * 
     * @var array
     */
    protected $dependencies = array();
    
    /**
     * Cache variations
     * 
     * @var array
     */
    protected $variations = array(
        'user_role' => true,
        'device_type' => true,
        'language' => true,
        'geo_location' => false
    );
    
    /**
     * Fragment statistics
     * 
     * @var array
     */
    protected $stats = array(
        'rendered' => 0,
        'cached' => 0,
        'esi' => 0,
        'ajax' => 0
    );
    
    /**
     * Constructor
     */
    public function __construct() {
        $this->cache_manager = CacheManager::get_instance();
        $this->init();
    }
    
    /**
     * Initialize fragment caching
     */
    protected function init() {
        // Check ESI support
        $this->esi_enabled = $this->check_esi_support();
        
        // Register hooks
        $this->register_hooks();
        
        // Set up AJAX handlers
        $this->setup_ajax_handlers();
    }
    
    /**
     * Start fragment caching
     * 
     * @param string $fragment_id Fragment identifier
     * @param array  $args       Fragment arguments
     * @param array  $options    Caching options
     * @return bool True if cached content was output
     */
    public function start( $fragment_id, array $args = array(), array $options = array() ) {
        $defaults = array(
            'ttl' => 1800,
            'vary_by' => array(),
            'dependencies' => array(),
            'esi' => false,
            'ajax' => false,
            'preload' => true
        );
        
        $options = wp_parse_args( $options, $defaults );
        
        // Build cache key
        $cache_key = $this->build_cache_key( $fragment_id, $args, $options['vary_by'] );
        
        // Check for ESI request
        if ( $options['esi'] && $this->esi_enabled ) {
            $this->output_esi_tag( $fragment_id, $args, $options );
            $this->stats['esi']++;
            return true;
        }
        
        // Check for AJAX lazy loading
        if ( $options['ajax'] && ! $this->is_ajax_request() ) {
            $this->output_ajax_placeholder( $fragment_id, $args, $options );
            $this->stats['ajax']++;
            return true;
        }
        
        // Try to get cached content
        $cached_content = $this->cache_manager->get( $cache_key, 'fragments' );
        
        if ( false !== $cached_content && $this->validate_dependencies( $cached_content['dependencies'] ?? array() ) ) {
            echo $cached_content['content'];
            $this->stats['cached']++;
            return true;
        }
        
        // Start output buffering
        ob_start();
        
        // Push fragment onto stack
        $this->fragment_stack[] = array(
            'id' => $fragment_id,
            'key' => $cache_key,
            'args' => $args,
            'options' => $options,
            'dependencies' => array()
        );
        
        $this->stats['rendered']++;
        
        return false;
    }
    
    /**
     * End fragment caching
     * 
     * @return string Cached content
     */
    public function end() {
        if ( empty( $this->fragment_stack ) ) {
            return '';
        }
        
        // Get content
        $content = ob_get_clean();
        
        // Pop fragment from stack
        $fragment = array_pop( $this->fragment_stack );
        
        // Prepare cache data
        $cache_data = array(
            'content' => $content,
            'dependencies' => $fragment['dependencies'],
            'created' => time(),
            'vary' => $this->get_current_variations( $fragment['options']['vary_by'] )
        );
        
        // Store in cache
        $this->cache_manager->set( 
            $fragment['key'], 
            $cache_data, 
            'fragments', 
            $fragment['options']['ttl'] 
        );
        
        // Store dependencies
        $this->store_dependencies( $fragment['id'], $fragment['dependencies'] );
        
        // Output content
        echo $content;
        
        return $content;
    }
    
    /**
     * Cache fragment with callback
     * 
     * @param string   $fragment_id Fragment identifier
     * @param callable $callback    Callback to generate content
     * @param array    $args        Fragment arguments
     * @param array    $options     Caching options
     * @return string Fragment content
     */
    public function cache( $fragment_id, callable $callback, array $args = array(), array $options = array() ) {
        if ( ! $this->start( $fragment_id, $args, $options ) ) {
            call_user_func( $callback, $args );
            $this->end();
        }
    }
    
    /**
     * Invalidate fragment cache
     * 
     * @param string $fragment_id Fragment identifier
     * @param array  $args        Optional specific arguments
     */
    public function invalidate( $fragment_id, array $args = null ) {
        if ( null === $args ) {
            // Invalidate all variations of this fragment
            $this->invalidate_all_variations( $fragment_id );
        } else {
            // Invalidate specific variation
            $cache_key = $this->build_cache_key( $fragment_id, $args );
            $this->cache_manager->delete( $cache_key, 'fragments' );
        }
        
        // Clear dependencies
        $this->clear_dependencies( $fragment_id );
    }
    
    /**
     * Add dependency to current fragment
     * 
     * @param string $type Dependency type
     * @param mixed  $id   Dependency ID
     */
    public function add_dependency( $type, $id ) {
        if ( empty( $this->fragment_stack ) ) {
            return;
        }
        
        $current = &$this->fragment_stack[ count( $this->fragment_stack ) - 1 ];
        $current['dependencies'][] = array(
            'type' => $type,
            'id' => $id,
            'timestamp' => time()
        );
    }
    
    /**
     * Build cache key
     * 
     * @param string $fragment_id Fragment identifier
     * @param array  $args        Fragment arguments
     * @param array  $vary_by     Variation factors
     * @return string Cache key
     */
    protected function build_cache_key( $fragment_id, array $args, array $vary_by = array() ) {
        $key_parts = array( $fragment_id );
        
        // Add arguments to key
        if ( ! empty( $args ) ) {
            $key_parts[] = md5( serialize( $args ) );
        }
        
        // Add variations
        $variations = $this->get_current_variations( $vary_by );
        if ( ! empty( $variations ) ) {
            $key_parts[] = md5( serialize( $variations ) );
        }
        
        return 'fragment_' . implode( '_', $key_parts );
    }
    
    /**
     * Get current variations
     * 
     * @param array $custom_vary Additional variation factors
     * @return array Current variations
     */
    protected function get_current_variations( array $custom_vary = array() ) {
        $variations = array();
        
        // Default variations
        if ( $this->variations['user_role'] ) {
            $user = wp_get_current_user();
            $variations['role'] = $user->roles[0] ?? 'guest';
        }
        
        if ( $this->variations['device_type'] ) {
            $variations['device'] = $this->detect_device_type();
        }
        
        if ( $this->variations['language'] ) {
            $variations['lang'] = get_locale();
        }
        
        if ( $this->variations['geo_location'] ) {
            $variations['geo'] = $this->get_geo_location();
        }
        
        // Custom variations
        foreach ( $custom_vary as $vary ) {
            if ( is_callable( $vary ) ) {
                $variations[ 'custom_' . md5( serialize( $vary ) ) ] = call_user_func( $vary );
            } else {
                $variations[ $vary ] = $this->get_vary_value( $vary );
            }
        }
        
        return $variations;
    }
    
    /**
     * Get variation value
     * 
     * @param string $vary Variation name
     * @return mixed Variation value
     */
    protected function get_vary_value( $vary ) {
        switch ( $vary ) {
            case 'user_id':
                return get_current_user_id();
                
            case 'url':
                return $_SERVER['REQUEST_URI'];
                
            case 'query_string':
                return $_SERVER['QUERY_STRING'] ?? '';
                
            case 'is_mobile':
                return wp_is_mobile();
                
            case 'is_logged_in':
                return is_user_logged_in();
                
            default:
                return apply_filters( 'money_quiz_fragment_vary_value', null, $vary );
        }
    }
    
    /**
     * Detect device type
     * 
     * @return string Device type
     */
    protected function detect_device_type() {
        if ( wp_is_mobile() ) {
            $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? '';
            
            if ( strpos( $user_agent, 'iPad' ) !== false || strpos( $user_agent, 'tablet' ) !== false ) {
                return 'tablet';
            }
            
            return 'mobile';
        }
        
        return 'desktop';
    }
    
    /**
     * Get geo location
     * 
     * @return string Geo location identifier
     */
    protected function get_geo_location() {
        // Check for CloudFlare geo header
        if ( isset( $_SERVER['HTTP_CF_IPCOUNTRY'] ) ) {
            return $_SERVER['HTTP_CF_IPCOUNTRY'];
        }
        
        // Check for other geo headers
        $geo_headers = array(
            'HTTP_X_COUNTRY_CODE',
            'HTTP_X_GEO_COUNTRY',
            'GEOIP_COUNTRY_CODE'
        );
        
        foreach ( $geo_headers as $header ) {
            if ( isset( $_SERVER[ $header ] ) ) {
                return $_SERVER[ $header ];
            }
        }
        
        return 'unknown';
    }
    
    /**
     * Check ESI support
     * 
     * @return bool
     */
    protected function check_esi_support() {
        // Check for Varnish or other ESI-capable proxies
        $headers = array(
            'HTTP_X_VARNISH',
            'HTTP_X_NGINX_ESI',
            'HTTP_SURROGATE_CAPABILITY'
        );
        
        foreach ( $headers as $header ) {
            if ( isset( $_SERVER[ $header ] ) ) {
                return true;
            }
        }
        
        // Check if ESI is explicitly enabled
        return defined( 'MONEY_QUIZ_ENABLE_ESI' ) && MONEY_QUIZ_ENABLE_ESI;
    }
    
    /**
     * Output ESI tag
     * 
     * @param string $fragment_id Fragment identifier
     * @param array  $args        Fragment arguments
     * @param array  $options     Fragment options
     */
    protected function output_esi_tag( $fragment_id, array $args, array $options ) {
        $esi_url = add_query_arg( array(
            'money_quiz_esi' => 1,
            'fragment' => $fragment_id,
            'args' => base64_encode( serialize( $args ) ),
            'nonce' => wp_create_nonce( 'money_quiz_esi_' . $fragment_id )
        ), home_url( '/money-quiz-esi/' ) );
        
        echo sprintf(
            '<esi:include src="%s" onerror="continue" />',
            esc_url( $esi_url )
        );
    }
    
    /**
     * Output AJAX placeholder
     * 
     * @param string $fragment_id Fragment identifier
     * @param array  $args        Fragment arguments
     * @param array  $options     Fragment options
     */
    protected function output_ajax_placeholder( $fragment_id, array $args, array $options ) {
        $placeholder_id = 'mq-fragment-' . md5( $fragment_id . serialize( $args ) );
        
        $data = array(
            'fragment' => $fragment_id,
            'args' => $args,
            'nonce' => wp_create_nonce( 'money_quiz_fragment_' . $fragment_id )
        );
        
        if ( $options['preload'] ) {
            // Add to preload queue
            add_action( 'wp_footer', function() use ( $data ) {
                ?>
                <script>
                window.moneyQuizFragments = window.moneyQuizFragments || [];
                window.moneyQuizFragments.push(<?php echo json_encode( $data ); ?>);
                </script>
                <?php
            }, 5 );
        }
        
        echo sprintf(
            '<div id="%s" class="mq-fragment-placeholder" data-fragment=\'%s\'><div class="mq-fragment-loader"></div></div>',
            esc_attr( $placeholder_id ),
            esc_attr( json_encode( $data ) )
        );
    }
    
    /**
     * Check if current request is AJAX
     * 
     * @return bool
     */
    protected function is_ajax_request() {
        return wp_doing_ajax() || ( isset( $_SERVER['HTTP_X_REQUESTED_WITH'] ) && 
            strtolower( $_SERVER['HTTP_X_REQUESTED_WITH'] ) === 'xmlhttprequest' );
    }
    
    /**
     * Validate dependencies
     * 
     * @param array $dependencies Dependencies to validate
     * @return bool Valid
     */
    protected function validate_dependencies( array $dependencies ) {
        foreach ( $dependencies as $dependency ) {
            if ( ! $this->is_dependency_valid( $dependency ) ) {
                return false;
            }
        }
        
        return true;
    }
    
    /**
     * Check if dependency is valid
     * 
     * @param array $dependency Dependency data
     * @return bool Valid
     */
    protected function is_dependency_valid( array $dependency ) {
        $type = $dependency['type'];
        $id = $dependency['id'];
        $timestamp = $dependency['timestamp'];
        
        switch ( $type ) {
            case 'post':
                $post = get_post( $id );
                return $post && strtotime( $post->post_modified ) <= $timestamp;
                
            case 'term':
                $term = get_term( $id );
                return $term && ! is_wp_error( $term );
                
            case 'user':
                $user = get_user_by( 'id', $id );
                return $user !== false;
                
            case 'option':
                // Check if option was updated after cache
                global $wpdb;
                $updated = $wpdb->get_var( $wpdb->prepare(
                    "SELECT MAX(option_id) FROM {$wpdb->options} WHERE option_name = %s",
                    $id
                ) );
                return $updated !== null;
                
            case 'money_quiz':
                return $this->validate_money_quiz_dependency( $id, $timestamp );
                
            default:
                return apply_filters( 'money_quiz_validate_fragment_dependency', true, $dependency );
        }
    }
    
    /**
     * Validate Money Quiz specific dependency
     * 
     * @param string $id        Dependency ID
     * @param int    $timestamp Dependency timestamp
     * @return bool Valid
     */
    protected function validate_money_quiz_dependency( $id, $timestamp ) {
        global $wpdb;
        
        if ( strpos( $id, 'archetype_' ) === 0 ) {
            $archetype_id = str_replace( 'archetype_', '', $id );
            $updated = $wpdb->get_var( $wpdb->prepare(
                "SELECT UNIX_TIMESTAMP(updated_at) FROM {$wpdb->prefix}mq_archetypes WHERE id = %d",
                $archetype_id
            ) );
            return $updated && $updated <= $timestamp;
        }
        
        if ( strpos( $id, 'result_' ) === 0 ) {
            $result_id = str_replace( 'result_', '', $id );
            $updated = $wpdb->get_var( $wpdb->prepare(
                "SELECT UNIX_TIMESTAMP(created_at) FROM {$wpdb->prefix}mq_results WHERE id = %d",
                $result_id
            ) );
            return $updated && $updated <= $timestamp;
        }
        
        return true;
    }
    
    /**
     * Store dependencies
     * 
     * @param string $fragment_id  Fragment identifier
     * @param array  $dependencies Dependencies
     */
    protected function store_dependencies( $fragment_id, array $dependencies ) {
        if ( empty( $dependencies ) ) {
            return;
        }
        
        $stored = get_option( 'money_quiz_fragment_dependencies', array() );
        
        foreach ( $dependencies as $dep ) {
            $key = $dep['type'] . '_' . $dep['id'];
            
            if ( ! isset( $stored[ $key ] ) ) {
                $stored[ $key ] = array();
            }
            
            $stored[ $key ][] = $fragment_id;
        }
        
        update_option( 'money_quiz_fragment_dependencies', $stored, false );
    }
    
    /**
     * Clear dependencies
     * 
     * @param string $fragment_id Fragment identifier
     */
    protected function clear_dependencies( $fragment_id ) {
        $stored = get_option( 'money_quiz_fragment_dependencies', array() );
        
        foreach ( $stored as $key => &$fragments ) {
            $fragments = array_diff( $fragments, array( $fragment_id ) );
            
            if ( empty( $fragments ) ) {
                unset( $stored[ $key ] );
            }
        }
        
        update_option( 'money_quiz_fragment_dependencies', $stored, false );
    }
    
    /**
     * Invalidate all variations
     * 
     * @param string $fragment_id Fragment identifier
     */
    protected function invalidate_all_variations( $fragment_id ) {
        // This would require tracking all variations
        // For now, we'll use a simpler approach
        $this->cache_manager->delete( 'fragment_' . $fragment_id . '*', 'fragments' );
    }
    
    /**
     * Register hooks
     */
    protected function register_hooks() {
        // Post updates
        add_action( 'save_post', array( $this, 'handle_post_update' ), 10, 2 );
        add_action( 'delete_post', array( $this, 'handle_post_delete' ) );
        
        // Term updates
        add_action( 'edited_term', array( $this, 'handle_term_update' ), 10, 3 );
        add_action( 'delete_term', array( $this, 'handle_term_delete' ), 10, 3 );
        
        // User updates
        add_action( 'profile_update', array( $this, 'handle_user_update' ) );
        add_action( 'deleted_user', array( $this, 'handle_user_delete' ) );
        
        // Option updates
        add_action( 'updated_option', array( $this, 'handle_option_update' ) );
        
        // Money Quiz specific
        add_action( 'money_quiz_archetype_updated', array( $this, 'handle_archetype_update' ) );
        add_action( 'money_quiz_result_saved', array( $this, 'handle_result_saved' ) );
    }
    
    /**
     * Handle post update
     * 
     * @param int     $post_id Post ID
     * @param WP_Post $post    Post object
     */
    public function handle_post_update( $post_id, $post ) {
        $this->invalidate_by_dependency( 'post', $post_id );
    }
    
    /**
     * Handle post delete
     * 
     * @param int $post_id Post ID
     */
    public function handle_post_delete( $post_id ) {
        $this->invalidate_by_dependency( 'post', $post_id );
    }
    
    /**
     * Handle term update
     * 
     * @param int    $term_id  Term ID
     * @param int    $tt_id    Term taxonomy ID
     * @param string $taxonomy Taxonomy
     */
    public function handle_term_update( $term_id, $tt_id, $taxonomy ) {
        $this->invalidate_by_dependency( 'term', $term_id );
    }
    
    /**
     * Handle term delete
     * 
     * @param int    $term_id  Term ID
     * @param int    $tt_id    Term taxonomy ID
     * @param string $taxonomy Taxonomy
     */
    public function handle_term_delete( $term_id, $tt_id, $taxonomy ) {
        $this->invalidate_by_dependency( 'term', $term_id );
    }
    
    /**
     * Handle user update
     * 
     * @param int $user_id User ID
     */
    public function handle_user_update( $user_id ) {
        $this->invalidate_by_dependency( 'user', $user_id );
    }
    
    /**
     * Handle user delete
     * 
     * @param int $user_id User ID
     */
    public function handle_user_delete( $user_id ) {
        $this->invalidate_by_dependency( 'user', $user_id );
    }
    
    /**
     * Handle option update
     * 
     * @param string $option Option name
     */
    public function handle_option_update( $option ) {
        $this->invalidate_by_dependency( 'option', $option );
    }
    
    /**
     * Handle archetype update
     * 
     * @param int $archetype_id Archetype ID
     */
    public function handle_archetype_update( $archetype_id ) {
        $this->invalidate_by_dependency( 'money_quiz', 'archetype_' . $archetype_id );
    }
    
    /**
     * Handle result saved
     * 
     * @param int $result_id Result ID
     */
    public function handle_result_saved( $result_id ) {
        $this->invalidate_by_dependency( 'money_quiz', 'result_' . $result_id );
    }
    
    /**
     * Invalidate by dependency
     * 
     * @param string $type Dependency type
     * @param mixed  $id   Dependency ID
     */
    protected function invalidate_by_dependency( $type, $id ) {
        $stored = get_option( 'money_quiz_fragment_dependencies', array() );
        $key = $type . '_' . $id;
        
        if ( isset( $stored[ $key ] ) ) {
            foreach ( $stored[ $key ] as $fragment_id ) {
                $this->invalidate( $fragment_id );
            }
        }
    }
    
    /**
     * Setup AJAX handlers
     */
    protected function setup_ajax_handlers() {
        add_action( 'wp_ajax_money_quiz_load_fragment', array( $this, 'ajax_load_fragment' ) );
        add_action( 'wp_ajax_nopriv_money_quiz_load_fragment', array( $this, 'ajax_load_fragment' ) );
        
        // Add frontend script
        add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_fragment_script' ) );
    }
    
    /**
     * AJAX handler to load fragment
     */
    public function ajax_load_fragment() {
        $fragment_id = $_POST['fragment'] ?? '';
        $args = $_POST['args'] ?? array();
        $nonce = $_POST['nonce'] ?? '';
        
        if ( ! wp_verify_nonce( $nonce, 'money_quiz_fragment_' . $fragment_id ) ) {
            wp_die( 'Invalid nonce' );
        }
        
        // Load fragment
        ob_start();
        do_action( 'money_quiz_render_fragment_' . $fragment_id, $args );
        $content = ob_get_clean();
        
        wp_send_json_success( array(
            'fragment' => $fragment_id,
            'content' => $content
        ) );
    }
    
    /**
     * Enqueue fragment loading script
     */
    public function enqueue_fragment_script() {
        wp_enqueue_script(
            'money-quiz-fragments',
            MONEY_QUIZ_URL . 'assets/js/fragments.js',
            array( 'jquery' ),
            MONEY_QUIZ_VERSION,
            true
        );
        
        wp_localize_script( 'money-quiz-fragments', 'moneyQuizFragment', array(
            'ajaxurl' => admin_url( 'admin-ajax.php' ),
            'action' => 'money_quiz_load_fragment'
        ) );
    }
    
    /**
     * Get fragment statistics
     * 
     * @return array Statistics
     */
    public function get_stats() {
        $total = $this->stats['rendered'] + $this->stats['cached'] + 
                 $this->stats['esi'] + $this->stats['ajax'];
        
        return array(
            'total' => $total,
            'rendered' => $this->stats['rendered'],
            'cached' => $this->stats['cached'],
            'esi' => $this->stats['esi'],
            'ajax' => $this->stats['ajax'],
            'cache_rate' => $total > 0 ? 
                round( ( $this->stats['cached'] / $total ) * 100, 2 ) : 0,
            'optimization_rate' => $total > 0 ? 
                round( ( ( $this->stats['cached'] + $this->stats['esi'] + $this->stats['ajax'] ) / $total ) * 100, 2 ) : 0
        );
    }
}

// Global fragment cache instance
global $money_quiz_fragment_cache;
$money_quiz_fragment_cache = new FragmentCacheAdvanced();

/**
 * Fragment cache helper functions
 */
if ( ! function_exists( 'mq_fragment_cache_start' ) ) {
    function mq_fragment_cache_start( $fragment_id, $args = array(), $options = array() ) {
        global $money_quiz_fragment_cache;
        return $money_quiz_fragment_cache->start( $fragment_id, $args, $options );
    }
}

if ( ! function_exists( 'mq_fragment_cache_end' ) ) {
    function mq_fragment_cache_end() {
        global $money_quiz_fragment_cache;
        return $money_quiz_fragment_cache->end();
    }
}

if ( ! function_exists( 'mq_fragment_cache' ) ) {
    function mq_fragment_cache( $fragment_id, $callback, $args = array(), $options = array() ) {
        global $money_quiz_fragment_cache;
        return $money_quiz_fragment_cache->cache( $fragment_id, $callback, $args, $options );
    }
}

if ( ! function_exists( 'mq_invalidate_fragment' ) ) {
    function mq_invalidate_fragment( $fragment_id, $args = null ) {
        global $money_quiz_fragment_cache;
        $money_quiz_fragment_cache->invalidate( $fragment_id, $args );
    }
}

if ( ! function_exists( 'mq_add_fragment_dependency' ) ) {
    function mq_add_fragment_dependency( $type, $id ) {
        global $money_quiz_fragment_cache;
        $money_quiz_fragment_cache->add_dependency( $type, $id );
    }
}
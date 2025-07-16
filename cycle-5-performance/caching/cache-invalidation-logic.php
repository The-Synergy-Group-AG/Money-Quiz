<?php
/**
 * Money Quiz Plugin - Cache Invalidation Logic
 * Worker 3: Smart Cache Invalidation System
 * 
 * Implements intelligent cache invalidation with dependency tracking,
 * cascading invalidation, and cache warming strategies.
 * 
 * @package MoneyQuiz
 * @subpackage Performance\Caching
 * @since 5.0.0
 */

namespace MoneyQuiz\Performance\Caching;

use MoneyQuiz\Performance\CacheManager;

/**
 * Cache Invalidation Logic Class
 * 
 * Manages intelligent cache invalidation and warming
 */
class CacheInvalidationLogic {
    
    /**
     * Cache manager instance
     * 
     * @var CacheManager
     */
    protected $cache_manager;
    
    /**
     * Invalidation rules
     * 
     * @var array
     */
    protected $invalidation_rules = array();
    
    /**
     * Dependency map
     * 
     * @var array
     */
    protected $dependency_map = array();
    
    /**
     * Invalidation queue
     * 
     * @var array
     */
    protected $invalidation_queue = array();
    
    /**
     * Warming queue
     * 
     * @var array
     */
    protected $warming_queue = array();
    
    /**
     * Invalidation statistics
     * 
     * @var array
     */
    protected $stats = array(
        'invalidations' => 0,
        'cascaded' => 0,
        'warmed' => 0,
        'rules_triggered' => 0
    );
    
    /**
     * Constructor
     */
    public function __construct() {
        $this->cache_manager = CacheManager::get_instance();
        $this->init();
    }
    
    /**
     * Initialize invalidation logic
     */
    protected function init() {
        // Load invalidation rules
        $this->load_invalidation_rules();
        
        // Register hooks
        $this->register_hooks();
        
        // Set up background processing
        $this->setup_background_processing();
    }
    
    /**
     * Load invalidation rules
     */
    protected function load_invalidation_rules() {
        // Core WordPress rules
        $this->add_rule( 'post_saved', array(
            'trigger' => array( 'save_post', 'delete_post' ),
            'invalidate' => array( $this, 'invalidate_post_caches' ),
            'warm' => array( $this, 'warm_post_caches' )
        ) );
        
        $this->add_rule( 'term_updated', array(
            'trigger' => array( 'edited_term', 'delete_term' ),
            'invalidate' => array( $this, 'invalidate_term_caches' ),
            'warm' => array( $this, 'warm_term_caches' )
        ) );
        
        $this->add_rule( 'user_updated', array(
            'trigger' => array( 'profile_update', 'user_register', 'delete_user' ),
            'invalidate' => array( $this, 'invalidate_user_caches' ),
            'warm' => array( $this, 'warm_user_caches' )
        ) );
        
        $this->add_rule( 'option_updated', array(
            'trigger' => array( 'updated_option', 'added_option', 'deleted_option' ),
            'invalidate' => array( $this, 'invalidate_option_caches' ),
            'warm' => array( $this, 'warm_option_caches' )
        ) );
        
        // Money Quiz specific rules
        $this->add_rule( 'archetype_updated', array(
            'trigger' => 'money_quiz_archetype_updated',
            'invalidate' => array( $this, 'invalidate_archetype_caches' ),
            'warm' => array( $this, 'warm_archetype_caches' ),
            'cascade' => array( 'results', 'analytics', 'fragments' )
        ) );
        
        $this->add_rule( 'questions_updated', array(
            'trigger' => 'money_quiz_questions_updated',
            'invalidate' => array( $this, 'invalidate_question_caches' ),
            'warm' => array( $this, 'warm_question_caches' ),
            'cascade' => array( 'quiz_pages', 'fragments' )
        ) );
        
        $this->add_rule( 'result_saved', array(
            'trigger' => 'money_quiz_result_saved',
            'invalidate' => array( $this, 'invalidate_result_caches' ),
            'warm' => array( $this, 'warm_result_caches' ),
            'cascade' => array( 'analytics', 'leaderboard' )
        ) );
        
        $this->add_rule( 'prospect_created', array(
            'trigger' => 'money_quiz_prospect_created',
            'invalidate' => array( $this, 'invalidate_prospect_caches' ),
            'warm' => array( $this, 'warm_prospect_caches' ),
            'cascade' => array( 'analytics' )
        ) );
        
        // Custom rules from filters
        $custom_rules = apply_filters( 'money_quiz_cache_invalidation_rules', array() );
        foreach ( $custom_rules as $name => $rule ) {
            $this->add_rule( $name, $rule );
        }
    }
    
    /**
     * Add invalidation rule
     * 
     * @param string $name Rule name
     * @param array  $rule Rule configuration
     */
    public function add_rule( $name, array $rule ) {
        $defaults = array(
            'trigger' => array(),
            'invalidate' => null,
            'warm' => null,
            'cascade' => array(),
            'priority' => 10,
            'async' => false
        );
        
        $this->invalidation_rules[ $name ] = wp_parse_args( $rule, $defaults );
    }
    
    /**
     * Register hooks
     */
    protected function register_hooks() {
        foreach ( $this->invalidation_rules as $name => $rule ) {
            $triggers = is_array( $rule['trigger'] ) ? $rule['trigger'] : array( $rule['trigger'] );
            
            foreach ( $triggers as $trigger ) {
                add_action( $trigger, function() use ( $name, $rule ) {
                    $this->trigger_rule( $name, func_get_args() );
                }, $rule['priority'] );
            }
        }
        
        // Process queues on shutdown
        add_action( 'shutdown', array( $this, 'process_queues' ) );
    }
    
    /**
     * Trigger invalidation rule
     * 
     * @param string $rule_name Rule name
     * @param array  $args      Hook arguments
     */
    protected function trigger_rule( $rule_name, array $args ) {
        $rule = $this->invalidation_rules[ $rule_name ];
        
        $this->stats['rules_triggered']++;
        
        // Add to invalidation queue
        if ( $rule['invalidate'] ) {
            $this->invalidation_queue[] = array(
                'rule' => $rule_name,
                'callback' => $rule['invalidate'],
                'args' => $args,
                'async' => $rule['async']
            );
        }
        
        // Add to warming queue
        if ( $rule['warm'] ) {
            $this->warming_queue[] = array(
                'rule' => $rule_name,
                'callback' => $rule['warm'],
                'args' => $args,
                'async' => $rule['async']
            );
        }
        
        // Handle cascading
        if ( ! empty( $rule['cascade'] ) ) {
            $this->handle_cascade( $rule['cascade'], $args );
        }
    }
    
    /**
     * Handle cascading invalidation
     * 
     * @param array $cascade_targets Cascade targets
     * @param array $args           Original arguments
     */
    protected function handle_cascade( array $cascade_targets, array $args ) {
        foreach ( $cascade_targets as $target ) {
            $this->stats['cascaded']++;
            
            switch ( $target ) {
                case 'results':
                    $this->invalidate_group( 'results' );
                    break;
                    
                case 'analytics':
                    $this->invalidate_group( 'analytics' );
                    break;
                    
                case 'fragments':
                    $this->invalidate_group( 'fragments' );
                    break;
                    
                case 'quiz_pages':
                    $this->invalidate_quiz_pages();
                    break;
                    
                case 'leaderboard':
                    $this->invalidate_leaderboard();
                    break;
                    
                default:
                    do_action( 'money_quiz_cascade_invalidation', $target, $args );
            }
        }
    }
    
    /**
     * Process invalidation and warming queues
     */
    public function process_queues() {
        // Process invalidations
        foreach ( $this->invalidation_queue as $item ) {
            if ( $item['async'] ) {
                $this->schedule_async_invalidation( $item );
            } else {
                call_user_func_array( $item['callback'], $item['args'] );
                $this->stats['invalidations']++;
            }
        }
        
        // Process warming (always async)
        foreach ( $this->warming_queue as $item ) {
            $this->schedule_async_warming( $item );
        }
        
        // Clear queues
        $this->invalidation_queue = array();
        $this->warming_queue = array();
    }
    
    /**
     * Invalidate post caches
     * 
     * @param int $post_id Post ID
     */
    public function invalidate_post_caches( $post_id ) {
        $post = get_post( $post_id );
        
        if ( ! $post ) {
            return;
        }
        
        // Clear post cache
        $this->cache_manager->delete( 'post_' . $post_id, 'posts' );
        $this->cache_manager->delete( 'post_meta_' . $post_id, 'post_meta' );
        
        // Clear related caches
        $this->invalidate_post_related( $post );
        
        // Track dependencies
        $this->track_dependency( 'post', $post_id );
    }
    
    /**
     * Warm post caches
     * 
     * @param int $post_id Post ID
     */
    public function warm_post_caches( $post_id ) {
        $post = get_post( $post_id );
        
        if ( ! $post || $post->post_status !== 'publish' ) {
            return;
        }
        
        // Cache post object
        $this->cache_manager->set( 'post_' . $post_id, $post, 'posts' );
        
        // Cache post meta
        $meta = get_post_meta( $post_id );
        $this->cache_manager->set( 'post_meta_' . $post_id, $meta, 'post_meta' );
        
        // Warm related caches
        $this->warm_post_related( $post );
        
        $this->stats['warmed']++;
    }
    
    /**
     * Invalidate post related caches
     * 
     * @param WP_Post $post Post object
     */
    protected function invalidate_post_related( $post ) {
        // Clear term relationships
        $taxonomies = get_object_taxonomies( $post->post_type );
        foreach ( $taxonomies as $taxonomy ) {
            $terms = wp_get_object_terms( $post->ID, $taxonomy, array( 'fields' => 'ids' ) );
            foreach ( $terms as $term_id ) {
                $this->cache_manager->delete( 'term_posts_' . $term_id, 'terms' );
            }
        }
        
        // Clear archives
        $this->cache_manager->delete( 'archive_' . $post->post_type, 'archives' );
        $this->cache_manager->delete( 'archive_date_' . get_the_date( 'Y-m', $post ), 'archives' );
    }
    
    /**
     * Warm post related caches
     * 
     * @param WP_Post $post Post object
     */
    protected function warm_post_related( $post ) {
        // Warm taxonomy caches
        $taxonomies = get_object_taxonomies( $post->post_type );
        foreach ( $taxonomies as $taxonomy ) {
            wp_get_object_terms( $post->ID, $taxonomy );
        }
    }
    
    /**
     * Invalidate term caches
     * 
     * @param int $term_id Term ID
     */
    public function invalidate_term_caches( $term_id ) {
        $term = get_term( $term_id );
        
        if ( ! $term || is_wp_error( $term ) ) {
            return;
        }
        
        // Clear term cache
        $this->cache_manager->delete( 'term_' . $term_id, 'terms' );
        $this->cache_manager->delete( 'term_posts_' . $term_id, 'terms' );
        
        // Clear taxonomy cache
        $this->cache_manager->delete( 'taxonomy_' . $term->taxonomy, 'taxonomies' );
        
        // Track dependencies
        $this->track_dependency( 'term', $term_id );
    }
    
    /**
     * Warm term caches
     * 
     * @param int $term_id Term ID
     */
    public function warm_term_caches( $term_id ) {
        $term = get_term( $term_id );
        
        if ( ! $term || is_wp_error( $term ) ) {
            return;
        }
        
        // Cache term object
        $this->cache_manager->set( 'term_' . $term_id, $term, 'terms' );
        
        // Cache term posts
        $posts = get_posts( array(
            'tax_query' => array(
                array(
                    'taxonomy' => $term->taxonomy,
                    'terms' => $term_id
                )
            ),
            'posts_per_page' => 100,
            'fields' => 'ids'
        ) );
        
        $this->cache_manager->set( 'term_posts_' . $term_id, $posts, 'terms' );
        
        $this->stats['warmed']++;
    }
    
    /**
     * Invalidate user caches
     * 
     * @param int $user_id User ID
     */
    public function invalidate_user_caches( $user_id ) {
        // Clear user cache
        $this->cache_manager->delete( 'user_' . $user_id, 'users' );
        $this->cache_manager->delete( 'user_meta_' . $user_id, 'user_meta' );
        
        // Clear user posts
        $this->cache_manager->delete( 'user_posts_' . $user_id, 'users' );
        
        // Track dependencies
        $this->track_dependency( 'user', $user_id );
    }
    
    /**
     * Warm user caches
     * 
     * @param int $user_id User ID
     */
    public function warm_user_caches( $user_id ) {
        $user = get_user_by( 'id', $user_id );
        
        if ( ! $user ) {
            return;
        }
        
        // Cache user object
        $this->cache_manager->set( 'user_' . $user_id, $user, 'users' );
        
        // Cache user meta
        $meta = get_user_meta( $user_id );
        $this->cache_manager->set( 'user_meta_' . $user_id, $meta, 'user_meta' );
        
        $this->stats['warmed']++;
    }
    
    /**
     * Invalidate option caches
     * 
     * @param string $option Option name
     */
    public function invalidate_option_caches( $option ) {
        // Clear specific option
        $this->cache_manager->delete( $option, 'options' );
        
        // Clear alloptions if necessary
        if ( ! wp_installing() ) {
            $alloptions = wp_load_alloptions();
            if ( isset( $alloptions[ $option ] ) ) {
                $this->cache_manager->delete( 'alloptions', 'options' );
            }
        }
        
        // Money Quiz specific options
        if ( strpos( $option, 'money_quiz_' ) === 0 ) {
            $this->invalidate_money_quiz_settings();
        }
    }
    
    /**
     * Warm option caches
     * 
     * @param string $option Option name
     */
    public function warm_option_caches( $option ) {
        // Get and cache option
        $value = get_option( $option );
        
        // Money Quiz options get longer TTL
        $ttl = strpos( $option, 'money_quiz_' ) === 0 ? 86400 : 3600;
        
        $this->cache_manager->set( $option, $value, 'options', $ttl );
        
        $this->stats['warmed']++;
    }
    
    /**
     * Invalidate archetype caches
     * 
     * @param int $archetype_id Archetype ID
     */
    public function invalidate_archetype_caches( $archetype_id ) {
        // Clear specific archetype
        $this->cache_manager->delete( 'archetype_' . $archetype_id, 'static' );
        
        // Clear all archetypes cache
        $this->cache_manager->delete( 'all_archetypes', 'static' );
        
        // Clear related results
        $this->cache_manager->delete( 'archetype_results_' . $archetype_id, 'results' );
        
        // Track dependencies
        $this->track_dependency( 'archetype', $archetype_id );
    }
    
    /**
     * Warm archetype caches
     * 
     * @param int $archetype_id Archetype ID
     */
    public function warm_archetype_caches( $archetype_id ) {
        global $wpdb;
        
        // Get and cache archetype
        $archetype = $wpdb->get_row( $wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}mq_archetypes WHERE id = %d",
            $archetype_id
        ), ARRAY_A );
        
        if ( $archetype ) {
            $this->cache_manager->set( 'archetype_' . $archetype_id, $archetype, 'static' );
        }
        
        // Refresh all archetypes cache
        $all_archetypes = $wpdb->get_results(
            "SELECT * FROM {$wpdb->prefix}mq_archetypes WHERE is_active = 1",
            ARRAY_A
        );
        
        $this->cache_manager->set( 'all_archetypes', $all_archetypes, 'static' );
        
        $this->stats['warmed']++;
    }
    
    /**
     * Invalidate question caches
     */
    public function invalidate_question_caches() {
        // Clear all questions
        $this->cache_manager->delete( 'all_questions', 'static' );
        
        // Clear individual question caches
        $this->invalidate_group( 'questions' );
    }
    
    /**
     * Warm question caches
     */
    public function warm_question_caches() {
        global $wpdb;
        
        // Get and cache all questions
        $questions = $wpdb->get_results(
            "SELECT * FROM {$wpdb->prefix}mq_questions WHERE is_active = 1 ORDER BY order_num",
            ARRAY_A
        );
        
        $this->cache_manager->set( 'all_questions', $questions, 'static' );
        
        // Cache individual questions
        foreach ( $questions as $question ) {
            $this->cache_manager->set( 'question_' . $question['id'], $question, 'static' );
        }
        
        $this->stats['warmed']++;
    }
    
    /**
     * Invalidate result caches
     * 
     * @param int $result_id Result ID
     */
    public function invalidate_result_caches( $result_id ) {
        // Clear specific result
        $this->cache_manager->delete( 'result_' . $result_id, 'results' );
        
        // Clear recent results
        $this->cache_manager->delete( 'recent_results_100', 'results' );
        
        // Clear statistics
        $this->invalidate_statistics();
        
        // Track dependencies
        $this->track_dependency( 'result', $result_id );
    }
    
    /**
     * Warm result caches
     * 
     * @param int $result_id Result ID
     */
    public function warm_result_caches( $result_id ) {
        global $wpdb;
        
        // Get and cache result
        $result = $wpdb->get_row( $wpdb->prepare(
            "SELECT r.*, p.Email, p.Name, a.name as archetype_name 
             FROM {$wpdb->prefix}mq_results r
             JOIN {$wpdb->prefix}mq_prospects p ON r.prospect_id = p.id
             JOIN {$wpdb->prefix}mq_archetypes a ON r.archetype_id = a.id
             WHERE r.id = %d",
            $result_id
        ), ARRAY_A );
        
        if ( $result ) {
            $this->cache_manager->set( 'result_' . $result_id, $result, 'results' );
        }
        
        // Refresh recent results
        $this->cache_manager->warmup_recent_results();
        
        $this->stats['warmed']++;
    }
    
    /**
     * Invalidate prospect caches
     * 
     * @param int $prospect_id Prospect ID
     */
    public function invalidate_prospect_caches( $prospect_id ) {
        // Clear specific prospect
        $this->cache_manager->delete( 'prospect_' . $prospect_id, 'prospects' );
        
        // Clear recent prospects
        $this->cache_manager->delete( 'recent_prospects', 'queries' );
        
        // Clear statistics
        $this->cache_manager->delete( 'stat_total_prospects', 'analytics' );
        
        // Track dependencies
        $this->track_dependency( 'prospect', $prospect_id );
    }
    
    /**
     * Warm prospect caches
     * 
     * @param int $prospect_id Prospect ID
     */
    public function warm_prospect_caches( $prospect_id ) {
        global $wpdb;
        
        // Get and cache prospect
        $prospect = $wpdb->get_row( $wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}mq_prospects WHERE id = %d",
            $prospect_id
        ), ARRAY_A );
        
        if ( $prospect ) {
            $this->cache_manager->set( 'prospect_' . $prospect_id, $prospect, 'prospects' );
        }
        
        $this->stats['warmed']++;
    }
    
    /**
     * Invalidate Money Quiz settings
     */
    protected function invalidate_money_quiz_settings() {
        // Clear all Money Quiz caches
        $this->invalidate_group( 'money_quiz' );
        
        // Clear page caches for quiz pages
        $this->invalidate_quiz_pages();
    }
    
    /**
     * Invalidate statistics
     */
    protected function invalidate_statistics() {
        $this->invalidate_group( 'analytics' );
    }
    
    /**
     * Invalidate quiz pages
     */
    protected function invalidate_quiz_pages() {
        global $money_quiz_page_cache;
        
        if ( $money_quiz_page_cache ) {
            $money_quiz_page_cache->invalidate_quiz_pages();
        }
    }
    
    /**
     * Invalidate leaderboard
     */
    protected function invalidate_leaderboard() {
        $this->cache_manager->delete( 'leaderboard_*', 'results' );
    }
    
    /**
     * Invalidate cache group
     * 
     * @param string $group Group name
     */
    public function invalidate_group( $group ) {
        $this->cache_manager->flush_group( $group );
        $this->stats['invalidations']++;
    }
    
    /**
     * Track dependency
     * 
     * @param string $type Entity type
     * @param mixed  $id   Entity ID
     */
    protected function track_dependency( $type, $id ) {
        $key = $type . '_' . $id;
        
        if ( ! isset( $this->dependency_map[ $key ] ) ) {
            $this->dependency_map[ $key ] = array();
        }
        
        $this->dependency_map[ $key ][] = array(
            'time' => time(),
            'action' => current_action(),
            'user' => get_current_user_id()
        );
        
        // Keep only last 10 entries
        $this->dependency_map[ $key ] = array_slice( $this->dependency_map[ $key ], -10 );
    }
    
    /**
     * Get dependency history
     * 
     * @param string $type Entity type
     * @param mixed  $id   Entity ID
     * @return array History
     */
    public function get_dependency_history( $type, $id ) {
        $key = $type . '_' . $id;
        return $this->dependency_map[ $key ] ?? array();
    }
    
    /**
     * Setup background processing
     */
    protected function setup_background_processing() {
        // Schedule background warming
        if ( ! wp_next_scheduled( 'money_quiz_cache_warming' ) ) {
            wp_schedule_event( time(), 'hourly', 'money_quiz_cache_warming' );
        }
        
        add_action( 'money_quiz_cache_warming', array( $this, 'run_background_warming' ) );
        
        // Handle async tasks
        add_action( 'money_quiz_async_invalidation', array( $this, 'handle_async_invalidation' ) );
        add_action( 'money_quiz_async_warming', array( $this, 'handle_async_warming' ) );
    }
    
    /**
     * Schedule async invalidation
     * 
     * @param array $item Queue item
     */
    protected function schedule_async_invalidation( $item ) {
        wp_schedule_single_event( time(), 'money_quiz_async_invalidation', array( $item ) );
    }
    
    /**
     * Schedule async warming
     * 
     * @param array $item Queue item
     */
    protected function schedule_async_warming( $item ) {
        wp_schedule_single_event( time() + 5, 'money_quiz_async_warming', array( $item ) );
    }
    
    /**
     * Handle async invalidation
     * 
     * @param array $item Queue item
     */
    public function handle_async_invalidation( $item ) {
        call_user_func_array( $item['callback'], $item['args'] );
        $this->stats['invalidations']++;
    }
    
    /**
     * Handle async warming
     * 
     * @param array $item Queue item
     */
    public function handle_async_warming( $item ) {
        call_user_func_array( $item['callback'], $item['args'] );
        $this->stats['warmed']++;
    }
    
    /**
     * Run background warming
     */
    public function run_background_warming() {
        // Warm critical caches
        $this->cache_manager->warmup();
        
        // Warm frequently accessed data
        $this->warm_popular_content();
    }
    
    /**
     * Warm popular content
     */
    protected function warm_popular_content() {
        global $wpdb;
        
        // Warm popular posts
        $popular_posts = $wpdb->get_results(
            "SELECT p.ID 
             FROM {$wpdb->posts} p
             WHERE p.post_status = 'publish'
             AND p.post_type = 'post'
             ORDER BY p.comment_count DESC
             LIMIT 20",
            ARRAY_A
        );
        
        foreach ( $popular_posts as $post ) {
            $this->warm_post_caches( $post['ID'] );
        }
        
        // Warm recent quiz results
        $recent_results = $wpdb->get_results(
            "SELECT id FROM {$wpdb->prefix}mq_results 
             ORDER BY created_at DESC 
             LIMIT 50",
            ARRAY_A
        );
        
        foreach ( $recent_results as $result ) {
            $this->warm_result_caches( $result['id'] );
        }
    }
    
    /**
     * Get invalidation statistics
     * 
     * @return array Statistics
     */
    public function get_stats() {
        return array(
            'invalidations' => $this->stats['invalidations'],
            'cascaded' => $this->stats['cascaded'],
            'warmed' => $this->stats['warmed'],
            'rules_triggered' => $this->stats['rules_triggered'],
            'rules_count' => count( $this->invalidation_rules ),
            'dependencies_tracked' => count( $this->dependency_map ),
            'queue_size' => count( $this->invalidation_queue ) + count( $this->warming_queue )
        );
    }
    
    /**
     * Reset statistics
     */
    public function reset_stats() {
        $this->stats = array(
            'invalidations' => 0,
            'cascaded' => 0,
            'warmed' => 0,
            'rules_triggered' => 0
        );
    }
}

// Initialize cache invalidation logic
global $money_quiz_cache_invalidation;
$money_quiz_cache_invalidation = new CacheInvalidationLogic();
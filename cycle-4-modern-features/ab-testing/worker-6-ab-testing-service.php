<?php
/**
 * Money Quiz Plugin - A/B Testing Service
 * Worker 6: A/B Testing Framework
 * 
 * Provides comprehensive A/B testing capabilities for optimizing
 * quiz performance, conversion rates, and user experience.
 * 
 * @package MoneyQuiz
 * @subpackage Services
 * @since 4.0.0
 */

namespace MoneyQuiz\Services;

use MoneyQuiz\Models\Settings;
use MoneyQuiz\Models\ActivityLog;
use MoneyQuiz\Utilities\CacheUtil;
use MoneyQuiz\Utilities\SecurityUtil;
use MoneyQuiz\Utilities\ArrayUtil;

/**
 * A/B Testing Service Class
 * 
 * Handles all A/B testing functionality
 */
class ABTestingService {
    
    /**
     * Database service
     * 
     * @var DatabaseService
     */
    protected $database;
    
    /**
     * Active experiments
     * 
     * @var array
     */
    protected $experiments = array();
    
    /**
     * User variations cache
     * 
     * @var array
     */
    protected $user_variations = array();
    
    /**
     * Constructor
     * 
     * @param DatabaseService $database
     */
    public function __construct( DatabaseService $database ) {
        $this->database = $database;
        $this->load_active_experiments();
        
        // Register hooks
        add_action( 'init', array( $this, 'init_session' ) );
        add_action( 'money_quiz_before_render', array( $this, 'apply_variations' ) );
        add_action( 'money_quiz_track_event', array( $this, 'track_conversion' ), 10, 2 );
        add_filter( 'money_quiz_get_content', array( $this, 'filter_content' ), 10, 2 );
    }
    
    /**
     * Initialize session for tracking
     */
    public function init_session() {
        if ( ! session_id() && ! headers_sent() ) {
            session_start();
        }
        
        // Initialize user ID if not exists
        if ( ! isset( $_SESSION['money_quiz_ab_user_id'] ) ) {
            $_SESSION['money_quiz_ab_user_id'] = $this->generate_user_id();
        }
    }
    
    /**
     * Create new A/B test experiment
     * 
     * @param array $config Experiment configuration
     * @return int Experiment ID
     * @throws \Exception
     */
    public function create_experiment( array $config ) {
        $defaults = array(
            'name' => '',
            'description' => '',
            'type' => 'split', // split, multivariate, bandit
            'status' => 'draft',
            'traffic_allocation' => 100, // Percentage of traffic
            'targeting' => array(),
            'variations' => array(),
            'goals' => array(),
            'start_date' => null,
            'end_date' => null,
            'created_by' => get_current_user_id()
        );
        
        $config = wp_parse_args( $config, $defaults );
        
        // Validate configuration
        $this->validate_experiment_config( $config );
        
        // Insert experiment
        $experiment_id = $this->database->insert( 'ab_experiments', array(
            'name' => $config['name'],
            'description' => $config['description'],
            'type' => $config['type'],
            'status' => $config['status'],
            'traffic_allocation' => $config['traffic_allocation'],
            'targeting_rules' => json_encode( $config['targeting'] ),
            'configuration' => json_encode( array(
                'variations' => $config['variations'],
                'goals' => $config['goals']
            )),
            'start_date' => $config['start_date'],
            'end_date' => $config['end_date'],
            'created_by' => $config['created_by']
        ));
        
        if ( ! $experiment_id ) {
            throw new \Exception( __( 'Failed to create experiment', 'money-quiz' ) );
        }
        
        // Create variations
        foreach ( $config['variations'] as $index => $variation ) {
            $this->database->insert( 'ab_variations', array(
                'experiment_id' => $experiment_id,
                'name' => $variation['name'],
                'key' => $variation['key'] ?? 'variation_' . $index,
                'changes' => json_encode( $variation['changes'] ),
                'traffic_percentage' => $variation['traffic_percentage'] ?? 0,
                'is_control' => $variation['is_control'] ?? ( $index === 0 )
            ));
        }
        
        // Log creation
        ActivityLog::log( 'ab_experiment_created', array(
            'experiment_id' => $experiment_id,
            'name' => $config['name']
        ));
        
        return $experiment_id;
    }
    
    /**
     * Get variation for user
     * 
     * @param int    $experiment_id Experiment ID
     * @param string $user_id User identifier
     * @return array|null Variation data
     */
    public function get_user_variation( $experiment_id, $user_id = null ) {
        if ( ! $user_id ) {
            $user_id = $this->get_user_id();
        }
        
        // Check cache
        $cache_key = "variation_{$experiment_id}_{$user_id}";
        if ( isset( $this->user_variations[ $cache_key ] ) ) {
            return $this->user_variations[ $cache_key ];
        }
        
        // Get experiment
        $experiment = $this->get_experiment( $experiment_id );
        if ( ! $experiment || $experiment['status'] !== 'running' ) {
            return null;
        }
        
        // Check targeting rules
        if ( ! $this->check_targeting( $experiment['targeting_rules'] ) ) {
            return null;
        }
        
        // Check traffic allocation
        if ( ! $this->is_in_experiment_traffic( $experiment['traffic_allocation'], $user_id ) ) {
            return null;
        }
        
        // Get or assign variation
        $variation = $this->get_assigned_variation( $experiment_id, $user_id );
        
        if ( ! $variation ) {
            $variation = $this->assign_variation( $experiment_id, $user_id, $experiment );
        }
        
        // Cache result
        $this->user_variations[ $cache_key ] = $variation;
        
        return $variation;
    }
    
    /**
     * Track conversion event
     * 
     * @param string $event_name Event name
     * @param array  $data Event data
     */
    public function track_conversion( $event_name, array $data = array() ) {
        $user_id = $this->get_user_id();
        
        // Get all active experiments
        foreach ( $this->experiments as $experiment ) {
            // Check if user is in experiment
            $variation = $this->get_user_variation( $experiment['id'], $user_id );
            if ( ! $variation ) {
                continue;
            }
            
            // Check if event matches experiment goals
            $goals = json_decode( $experiment['configuration'], true )['goals'] ?? array();
            
            foreach ( $goals as $goal ) {
                if ( $goal['event'] === $event_name ) {
                    $this->record_conversion(
                        $experiment['id'],
                        $variation['id'],
                        $goal['id'],
                        $data
                    );
                }
            }
        }
    }
    
    /**
     * Record conversion
     * 
     * @param int   $experiment_id Experiment ID
     * @param int   $variation_id Variation ID
     * @param string $goal_id Goal ID
     * @param array $data Conversion data
     */
    protected function record_conversion( $experiment_id, $variation_id, $goal_id, array $data = array() ) {
        $user_id = $this->get_user_id();
        
        // Check if already converted
        $existing = $this->database->get_row( 'ab_conversions', array(
            'experiment_id' => $experiment_id,
            'variation_id' => $variation_id,
            'user_id' => $user_id,
            'goal_id' => $goal_id
        ));
        
        if ( $existing ) {
            return; // Already converted
        }
        
        // Record conversion
        $this->database->insert( 'ab_conversions', array(
            'experiment_id' => $experiment_id,
            'variation_id' => $variation_id,
            'user_id' => $user_id,
            'goal_id' => $goal_id,
            'value' => $data['value'] ?? 1,
            'metadata' => json_encode( $data ),
            'converted_at' => current_time( 'mysql' )
        ));
        
        // Update variation statistics
        $this->update_variation_stats( $variation_id, 'conversions' );
    }
    
    /**
     * Get experiment results
     * 
     * @param int $experiment_id Experiment ID
     * @return array Results data
     */
    public function get_experiment_results( $experiment_id ) {
        $experiment = $this->get_experiment( $experiment_id );
        if ( ! $experiment ) {
            return array();
        }
        
        $results = array(
            'experiment' => $experiment,
            'duration' => $this->calculate_duration( $experiment ),
            'total_visitors' => 0,
            'variations' => array()
        );
        
        // Get variations with statistics
        $variations = $this->database->get_results( 'ab_variations', array(
            'where' => array( 'experiment_id' => $experiment_id )
        ));
        
        foreach ( $variations as $variation ) {
            $stats = $this->get_variation_statistics( $variation->id );
            
            $results['variations'][] = array(
                'id' => $variation->id,
                'name' => $variation->name,
                'is_control' => $variation->is_control,
                'visitors' => $stats['visitors'],
                'conversions' => $stats['conversions'],
                'conversion_rate' => $stats['conversion_rate'],
                'confidence' => $this->calculate_confidence( $stats, $results['variations'][0]['stats'] ?? null ),
                'improvement' => $this->calculate_improvement( $stats, $results['variations'][0]['stats'] ?? null )
            );
            
            $results['total_visitors'] += $stats['visitors'];
        }
        
        // Calculate statistical significance
        if ( count( $results['variations'] ) > 1 ) {
            $results['significance'] = $this->calculate_significance( $results['variations'] );
        }
        
        return $results;
    }
    
    /**
     * Get variation statistics
     * 
     * @param int $variation_id Variation ID
     * @return array Statistics
     */
    protected function get_variation_statistics( $variation_id ) {
        // Get visitor count
        $visitors = $this->database->query(
            "SELECT COUNT(DISTINCT user_id) as count 
             FROM {$this->database->get_table('ab_assignments')} 
             WHERE variation_id = %d",
            array( $variation_id )
        )[0]->count ?? 0;
        
        // Get conversion count
        $conversions = $this->database->query(
            "SELECT COUNT(DISTINCT user_id) as count 
             FROM {$this->database->get_table('ab_conversions')} 
             WHERE variation_id = %d",
            array( $variation_id )
        )[0]->count ?? 0;
        
        // Calculate conversion rate
        $conversion_rate = $visitors > 0 ? ( $conversions / $visitors ) * 100 : 0;
        
        return array(
            'visitors' => $visitors,
            'conversions' => $conversions,
            'conversion_rate' => round( $conversion_rate, 2 )
        );
    }
    
    /**
     * Calculate statistical significance
     * 
     * @param array $variations Variations data
     * @return array Significance data
     */
    protected function calculate_significance( array $variations ) {
        if ( count( $variations ) < 2 ) {
            return array( 'significant' => false );
        }
        
        // Get control and variant
        $control = null;
        $variant = null;
        
        foreach ( $variations as $variation ) {
            if ( $variation['is_control'] ) {
                $control = $variation;
            } else {
                $variant = $variation;
            }
        }
        
        if ( ! $control || ! $variant ) {
            return array( 'significant' => false );
        }
        
        // Calculate z-score
        $p1 = $control['conversion_rate'] / 100;
        $p2 = $variant['conversion_rate'] / 100;
        $n1 = $control['visitors'];
        $n2 = $variant['visitors'];
        
        if ( $n1 === 0 || $n2 === 0 ) {
            return array( 'significant' => false );
        }
        
        $p_pool = ( $control['conversions'] + $variant['conversions'] ) / ( $n1 + $n2 );
        $se = sqrt( $p_pool * ( 1 - $p_pool ) * ( 1 / $n1 + 1 / $n2 ) );
        
        if ( $se === 0 ) {
            return array( 'significant' => false );
        }
        
        $z_score = ( $p2 - $p1 ) / $se;
        $p_value = $this->calculate_p_value( $z_score );
        
        return array(
            'significant' => $p_value < 0.05,
            'p_value' => round( $p_value, 4 ),
            'confidence_level' => round( ( 1 - $p_value ) * 100, 2 ),
            'z_score' => round( $z_score, 4 )
        );
    }
    
    /**
     * Apply variations to content
     * 
     * @param array $content Content array
     */
    public function apply_variations( &$content ) {
        foreach ( $this->experiments as $experiment ) {
            $variation = $this->get_user_variation( $experiment['id'] );
            
            if ( ! $variation ) {
                continue;
            }
            
            // Apply variation changes
            $changes = json_decode( $variation['changes'], true ) ?? array();
            
            foreach ( $changes as $change ) {
                switch ( $change['type'] ) {
                    case 'text':
                        $content = $this->apply_text_change( $content, $change );
                        break;
                        
                    case 'style':
                        $content = $this->apply_style_change( $content, $change );
                        break;
                        
                    case 'element':
                        $content = $this->apply_element_change( $content, $change );
                        break;
                        
                    case 'redirect':
                        $this->apply_redirect_change( $change );
                        break;
                }
            }
        }
    }
    
    /**
     * Create multivariate test
     * 
     * @param array $config Test configuration
     * @return int Experiment ID
     */
    public function create_multivariate_test( array $config ) {
        $config['type'] = 'multivariate';
        
        // Generate all combinations
        $combinations = $this->generate_multivariate_combinations( $config['elements'] );
        
        // Create variations for each combination
        $config['variations'] = array();
        foreach ( $combinations as $index => $combination ) {
            $config['variations'][] = array(
                'name' => 'Combination ' . ( $index + 1 ),
                'key' => 'combo_' . $index,
                'changes' => $combination,
                'traffic_percentage' => 100 / count( $combinations )
            );
        }
        
        return $this->create_experiment( $config );
    }
    
    /**
     * Create multi-armed bandit test
     * 
     * @param array $config Test configuration
     * @return int Experiment ID
     */
    public function create_bandit_test( array $config ) {
        $config['type'] = 'bandit';
        
        // Initialize epsilon value for exploration
        $config['epsilon'] = $config['epsilon'] ?? 0.1;
        
        return $this->create_experiment( $config );
    }
    
    /**
     * Update bandit allocation
     * 
     * @param int $experiment_id Experiment ID
     */
    public function update_bandit_allocation( $experiment_id ) {
        $experiment = $this->get_experiment( $experiment_id );
        
        if ( ! $experiment || $experiment['type'] !== 'bandit' ) {
            return;
        }
        
        $variations = $this->database->get_results( 'ab_variations', array(
            'where' => array( 'experiment_id' => $experiment_id )
        ));
        
        $total_reward = 0;
        $variation_stats = array();
        
        // Calculate rewards for each variation
        foreach ( $variations as $variation ) {
            $stats = $this->get_variation_statistics( $variation->id );
            $reward = $stats['conversion_rate'];
            $variation_stats[ $variation->id ] = $reward;
            $total_reward += $reward;
        }
        
        // Update traffic allocation based on performance
        foreach ( $variations as $variation ) {
            $reward = $variation_stats[ $variation->id ];
            
            if ( $total_reward > 0 ) {
                // Thompson sampling approach
                $new_percentage = ( $reward / $total_reward ) * 100;
                
                // Apply epsilon-greedy exploration
                $epsilon = json_decode( $experiment['configuration'], true )['epsilon'] ?? 0.1;
                $new_percentage = ( 1 - $epsilon ) * $new_percentage + $epsilon * ( 100 / count( $variations ) );
            } else {
                $new_percentage = 100 / count( $variations );
            }
            
            $this->database->update( 'ab_variations',
                array( 'traffic_percentage' => round( $new_percentage, 2 ) ),
                array( 'id' => $variation->id )
            );
        }
    }
    
    /**
     * Get experiment analytics
     * 
     * @param int   $experiment_id Experiment ID
     * @param array $options Analytics options
     * @return array Analytics data
     */
    public function get_experiment_analytics( $experiment_id, array $options = array() ) {
        $defaults = array(
            'start_date' => '-30 days',
            'end_date' => 'now',
            'interval' => 'day'
        );
        
        $options = wp_parse_args( $options, $defaults );
        
        $analytics = array(
            'timeline' => $this->get_conversion_timeline( $experiment_id, $options ),
            'segments' => $this->get_segment_analysis( $experiment_id ),
            'funnel' => $this->get_funnel_analysis( $experiment_id ),
            'heatmap' => $this->get_interaction_heatmap( $experiment_id )
        );
        
        return $analytics;
    }
    
    /**
     * Pause experiment
     * 
     * @param int $experiment_id Experiment ID
     * @return bool Success
     */
    public function pause_experiment( $experiment_id ) {
        return $this->update_experiment_status( $experiment_id, 'paused' );
    }
    
    /**
     * Resume experiment
     * 
     * @param int $experiment_id Experiment ID
     * @return bool Success
     */
    public function resume_experiment( $experiment_id ) {
        return $this->update_experiment_status( $experiment_id, 'running' );
    }
    
    /**
     * Stop experiment
     * 
     * @param int $experiment_id Experiment ID
     * @param int $winning_variation_id Winning variation to implement
     * @return bool Success
     */
    public function stop_experiment( $experiment_id, $winning_variation_id = null ) {
        $this->database->start_transaction();
        
        try {
            // Update experiment status
            $this->update_experiment_status( $experiment_id, 'completed' );
            
            // If winner specified, mark it
            if ( $winning_variation_id ) {
                $this->database->update( 'ab_variations',
                    array( 'is_winner' => 1 ),
                    array( 'id' => $winning_variation_id )
                );
                
                // Optionally implement winning variation permanently
                $this->implement_winning_variation( $winning_variation_id );
            }
            
            $this->database->commit();
            
            // Clear cache
            $this->clear_experiment_cache( $experiment_id );
            
            return true;
            
        } catch ( \Exception $e ) {
            $this->database->rollback();
            return false;
        }
    }
    
    /**
     * Load active experiments
     */
    protected function load_active_experiments() {
        $cache_key = 'ab_active_experiments';
        
        $this->experiments = CacheUtil::remember( $cache_key, function() {
            return $this->database->get_results( 'ab_experiments', array(
                'where' => array( 'status' => 'running' ),
                'orderby' => 'priority',
                'order' => 'DESC'
            ), ARRAY_A );
        }, 300 ); // 5 minutes cache
    }
    
    /**
     * Get experiment by ID
     * 
     * @param int $experiment_id Experiment ID
     * @return array|null Experiment data
     */
    protected function get_experiment( $experiment_id ) {
        foreach ( $this->experiments as $experiment ) {
            if ( $experiment['id'] == $experiment_id ) {
                return $experiment;
            }
        }
        
        // Load from database if not in cache
        return $this->database->get_row( 'ab_experiments', array(
            'id' => $experiment_id
        ), ARRAY_A );
    }
    
    /**
     * Generate user ID
     * 
     * @return string User ID
     */
    protected function generate_user_id() {
        return SecurityUtil::generate_token( 32 );
    }
    
    /**
     * Get current user ID
     * 
     * @return string User ID
     */
    protected function get_user_id() {
        if ( is_user_logged_in() ) {
            return 'wp_user_' . get_current_user_id();
        }
        
        return $_SESSION['money_quiz_ab_user_id'] ?? $this->generate_user_id();
    }
    
    /**
     * Check if user is in experiment traffic
     * 
     * @param int    $traffic_percentage Traffic allocation percentage
     * @param string $user_id User ID
     * @return bool
     */
    protected function is_in_experiment_traffic( $traffic_percentage, $user_id ) {
        if ( $traffic_percentage >= 100 ) {
            return true;
        }
        
        // Use consistent hashing for deterministic assignment
        $hash = crc32( $user_id );
        $bucket = $hash % 100;
        
        return $bucket < $traffic_percentage;
    }
    
    /**
     * Check targeting rules
     * 
     * @param string $rules JSON targeting rules
     * @return bool
     */
    protected function check_targeting( $rules ) {
        $rules = json_decode( $rules, true );
        
        if ( empty( $rules ) ) {
            return true;
        }
        
        foreach ( $rules as $rule ) {
            if ( ! $this->evaluate_targeting_rule( $rule ) ) {
                return false;
            }
        }
        
        return true;
    }
    
    /**
     * Evaluate single targeting rule
     * 
     * @param array $rule Rule configuration
     * @return bool
     */
    protected function evaluate_targeting_rule( array $rule ) {
        switch ( $rule['type'] ) {
            case 'device':
                return $this->check_device_targeting( $rule['value'] );
                
            case 'location':
                return $this->check_location_targeting( $rule['value'] );
                
            case 'referrer':
                return $this->check_referrer_targeting( $rule['value'] );
                
            case 'user_type':
                return $this->check_user_type_targeting( $rule['value'] );
                
            case 'custom':
                return apply_filters( 'money_quiz_ab_targeting_' . $rule['key'], true, $rule['value'] );
                
            default:
                return true;
        }
    }
    
    /**
     * Assign variation to user
     * 
     * @param int    $experiment_id Experiment ID
     * @param string $user_id User ID
     * @param array  $experiment Experiment data
     * @return array|null Assigned variation
     */
    protected function assign_variation( $experiment_id, $user_id, array $experiment ) {
        $variations = $this->database->get_results( 'ab_variations', array(
            'where' => array( 'experiment_id' => $experiment_id ),
            'orderby' => 'id'
        ), ARRAY_A );
        
        if ( empty( $variations ) ) {
            return null;
        }
        
        // Select variation based on traffic percentage
        $selected = $this->select_variation_by_traffic( $variations, $user_id );
        
        if ( ! $selected ) {
            return null;
        }
        
        // Record assignment
        $this->database->insert( 'ab_assignments', array(
            'experiment_id' => $experiment_id,
            'variation_id' => $selected['id'],
            'user_id' => $user_id,
            'assigned_at' => current_time( 'mysql' )
        ));
        
        // Update variation statistics
        $this->update_variation_stats( $selected['id'], 'visitors' );
        
        return $selected;
    }
    
    /**
     * Select variation based on traffic allocation
     * 
     * @param array  $variations Available variations
     * @param string $user_id User ID for consistent hashing
     * @return array|null Selected variation
     */
    protected function select_variation_by_traffic( array $variations, $user_id ) {
        $total_percentage = array_sum( array_column( $variations, 'traffic_percentage' ) );
        
        if ( $total_percentage === 0 ) {
            // Equal distribution if no percentages set
            $index = crc32( $user_id ) % count( $variations );
            return $variations[ $index ];
        }
        
        // Use consistent hashing for selection
        $hash = crc32( $user_id ) % $total_percentage;
        $cumulative = 0;
        
        foreach ( $variations as $variation ) {
            $cumulative += $variation['traffic_percentage'];
            if ( $hash < $cumulative ) {
                return $variation;
            }
        }
        
        return end( $variations );
    }
    
    /**
     * Validate experiment configuration
     * 
     * @param array $config Configuration
     * @throws \Exception
     */
    protected function validate_experiment_config( array $config ) {
        if ( empty( $config['name'] ) ) {
            throw new \Exception( __( 'Experiment name is required', 'money-quiz' ) );
        }
        
        if ( empty( $config['variations'] ) || count( $config['variations'] ) < 2 ) {
            throw new \Exception( __( 'At least 2 variations are required', 'money-quiz' ) );
        }
        
        if ( empty( $config['goals'] ) ) {
            throw new \Exception( __( 'At least one goal is required', 'money-quiz' ) );
        }
        
        // Validate traffic allocation
        $total_traffic = 0;
        foreach ( $config['variations'] as $variation ) {
            $total_traffic += $variation['traffic_percentage'] ?? 0;
        }
        
        if ( $total_traffic > 0 && abs( $total_traffic - 100 ) > 0.01 ) {
            throw new \Exception( __( 'Variation traffic must total 100%', 'money-quiz' ) );
        }
    }
}

/**
 * A/B Testing Manager Class
 * 
 * Handles admin interface for A/B testing
 */
class ABTestingManager {
    
    /**
     * A/B Testing service
     * 
     * @var ABTestingService
     */
    protected $ab_service;
    
    /**
     * Constructor
     * 
     * @param ABTestingService $ab_service
     */
    public function __construct( ABTestingService $ab_service ) {
        $this->ab_service = $ab_service;
        
        // Register hooks
        add_action( 'admin_menu', array( $this, 'add_menu_page' ) );
        add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
        
        // AJAX handlers
        add_action( 'wp_ajax_money_quiz_create_experiment', array( $this, 'ajax_create_experiment' ) );
        add_action( 'wp_ajax_money_quiz_get_experiment_results', array( $this, 'ajax_get_results' ) );
        add_action( 'wp_ajax_money_quiz_update_experiment', array( $this, 'ajax_update_experiment' ) );
        add_action( 'wp_ajax_money_quiz_stop_experiment', array( $this, 'ajax_stop_experiment' ) );
    }
    
    /**
     * Add A/B testing menu page
     */
    public function add_menu_page() {
        add_submenu_page(
            'money-quiz',
            __( 'A/B Testing', 'money-quiz' ),
            __( 'A/B Testing', 'money-quiz' ),
            'manage_options',
            'money-quiz-ab-testing',
            array( $this, 'render_page' )
        );
    }
    
    /**
     * Render A/B testing page
     */
    public function render_page() {
        ?>
        <div class="wrap money-quiz-ab-testing">
            <h1>
                <?php _e( 'A/B Testing', 'money-quiz' ); ?>
                <a href="#" class="page-title-action" id="create-experiment">
                    <?php _e( 'New Experiment', 'money-quiz' ); ?>
                </a>
            </h1>
            
            <div class="ab-testing-dashboard">
                <!-- Active experiments -->
                <div class="experiments-section">
                    <h2><?php _e( 'Active Experiments', 'money-quiz' ); ?></h2>
                    <div id="active-experiments">
                        <!-- Loaded via JavaScript -->
                    </div>
                </div>
                
                <!-- Completed experiments -->
                <div class="experiments-section">
                    <h2><?php _e( 'Completed Experiments', 'money-quiz' ); ?></h2>
                    <div id="completed-experiments">
                        <!-- Loaded via JavaScript -->
                    </div>
                </div>
            </div>
        </div>
        <?php
    }
}
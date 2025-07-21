<?php
/**
 * Money Quiz Plugin - Personalization Service
 * Worker 8: Advanced Personalization
 * 
 * Provides sophisticated personalization features including user profiling,
 * dynamic content adaptation, and behavior-based customization.
 * 
 * @package MoneyQuiz
 * @subpackage Services
 * @since 4.0.0
 */

namespace MoneyQuiz\Services;

use MoneyQuiz\Models\Prospect;
use MoneyQuiz\Models\QuizResult;
use MoneyQuiz\Models\Settings;
use MoneyQuiz\Utilities\CacheUtil;
use MoneyQuiz\Utilities\ArrayUtil;
use MoneyQuiz\Utilities\DebugUtil;

/**
 * Personalization Service Class
 * 
 * Handles all personalization functionality
 */
class PersonalizationService {
    
    /**
     * Database service
     * 
     * @var DatabaseService
     */
    protected $database;
    
    /**
     * AI service for intelligent personalization
     * 
     * @var AIService
     */
    protected $ai_service;
    
    /**
     * User profiles cache
     * 
     * @var array
     */
    protected $profiles = array();
    
    /**
     * Personalization rules
     * 
     * @var array
     */
    protected $rules = array();
    
    /**
     * Constructor
     * 
     * @param DatabaseService $database
     * @param AIService      $ai_service
     */
    public function __construct( DatabaseService $database, AIService $ai_service = null ) {
        $this->database = $database;
        $this->ai_service = $ai_service;
        
        $this->load_personalization_rules();
        
        // Register hooks
        add_filter( 'money_quiz_quiz_content', array( $this, 'personalize_quiz_content' ), 10, 2 );
        add_filter( 'money_quiz_email_content', array( $this, 'personalize_email_content' ), 10, 2 );
        add_filter( 'money_quiz_recommendations', array( $this, 'personalize_recommendations' ), 10, 2 );
        add_action( 'money_quiz_track_behavior', array( $this, 'track_user_behavior' ), 10, 2 );
        add_action( 'money_quiz_quiz_completed', array( $this, 'update_user_profile' ), 10, 3 );
    }
    
    /**
     * Get user profile
     * 
     * @param mixed $user_identifier User ID, email, or session ID
     * @return array User profile data
     */
    public function get_user_profile( $user_identifier ) {
        // Check cache
        if ( isset( $this->profiles[ $user_identifier ] ) ) {
            return $this->profiles[ $user_identifier ];
        }
        
        // Build comprehensive profile
        $profile = $this->build_user_profile( $user_identifier );
        
        // Cache profile
        $this->profiles[ $user_identifier ] = $profile;
        CacheUtil::set( 'user_profile_' . md5( $user_identifier ), $profile, HOUR_IN_SECONDS );
        
        return $profile;
    }
    
    /**
     * Build user profile
     * 
     * @param mixed $user_identifier User identifier
     * @return array Profile data
     */
    protected function build_user_profile( $user_identifier ) {
        $profile = array(
            'identifier' => $user_identifier,
            'demographics' => $this->get_user_demographics( $user_identifier ),
            'behavior' => $this->get_user_behavior( $user_identifier ),
            'preferences' => $this->get_user_preferences( $user_identifier ),
            'history' => $this->get_user_history( $user_identifier ),
            'segments' => $this->get_user_segments( $user_identifier ),
            'scores' => $this->calculate_profile_scores( $user_identifier ),
            'predictions' => array()
        );
        
        // Add AI-powered predictions if available
        if ( $this->ai_service && $this->ai_service->is_enabled() ) {
            $profile['predictions'] = $this->get_ai_predictions( $profile );
        }
        
        return $profile;
    }
    
    /**
     * Personalize quiz content
     * 
     * @param array $content Quiz content
     * @param mixed $user_identifier User identifier
     * @return array Personalized content
     */
    public function personalize_quiz_content( $content, $user_identifier ) {
        $profile = $this->get_user_profile( $user_identifier );
        
        // Personalize question order
        $content['questions'] = $this->personalize_question_order( 
            $content['questions'], 
            $profile 
        );
        
        // Personalize question text
        foreach ( $content['questions'] as &$question ) {
            $question['text'] = $this->personalize_text( 
                $question['text'], 
                $profile 
            );
        }
        
        // Add personalized intro
        $content['intro'] = $this->get_personalized_intro( $profile );
        
        // Adjust difficulty
        $content['difficulty'] = $this->get_personalized_difficulty( $profile );
        
        // Add skip logic
        $content['skip_logic'] = $this->get_skip_logic( $profile );
        
        return apply_filters( 'money_quiz_personalized_content', $content, $profile );
    }
    
    /**
     * Personalize recommendations
     * 
     * @param array $recommendations Base recommendations
     * @param array $context Context data
     * @return array Personalized recommendations
     */
    public function personalize_recommendations( $recommendations, $context ) {
        $profile = $this->get_user_profile( $context['user_identifier'] ?? null );
        
        // Score and rank recommendations
        $scored_recommendations = array();
        
        foreach ( $recommendations as $recommendation ) {
            $score = $this->calculate_recommendation_score( $recommendation, $profile );
            $scored_recommendations[] = array_merge( $recommendation, array(
                'relevance_score' => $score,
                'personalized' => true
            ));
        }
        
        // Sort by relevance
        usort( $scored_recommendations, function( $a, $b ) {
            return $b['relevance_score'] <=> $a['relevance_score'];
        });
        
        // Add personalized context
        foreach ( $scored_recommendations as &$rec ) {
            $rec['reason'] = $this->get_recommendation_reason( $rec, $profile );
            $rec['cta'] = $this->get_personalized_cta( $rec, $profile );
        }
        
        // Limit to top recommendations
        $limit = $context['limit'] ?? 5;
        $personalized = array_slice( $scored_recommendations, 0, $limit );
        
        // Add AI-enhanced recommendations
        if ( $this->ai_service && $profile['predictions']['needs'] ?? false ) {
            $personalized = $this->enhance_with_ai_recommendations( 
                $personalized, 
                $profile 
            );
        }
        
        return $personalized;
    }
    
    /**
     * Track user behavior
     * 
     * @param string $event Event name
     * @param array  $data Event data
     */
    public function track_user_behavior( $event, array $data ) {
        $user_identifier = $data['user_identifier'] ?? $this->get_current_user_identifier();
        
        // Store behavior event
        $this->database->insert( 'user_behavior', array(
            'user_identifier' => $user_identifier,
            'event' => $event,
            'data' => json_encode( $data ),
            'context' => json_encode( array(
                'url' => $_SERVER['REQUEST_URI'] ?? '',
                'referrer' => $_SERVER['HTTP_REFERER'] ?? '',
                'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? '',
                'ip' => $_SERVER['REMOTE_ADDR'] ?? ''
            )),
            'created_at' => current_time( 'mysql' )
        ));
        
        // Update real-time profile
        $this->update_real_time_profile( $user_identifier, $event, $data );
        
        // Trigger personalization rules
        $this->evaluate_behavior_rules( $user_identifier, $event, $data );
    }
    
    /**
     * Get personalized content blocks
     * 
     * @param string $location Content location
     * @param mixed  $user_identifier User identifier
     * @return array Content blocks
     */
    public function get_personalized_content_blocks( $location, $user_identifier ) {
        $profile = $this->get_user_profile( $user_identifier );
        $blocks = array();
        
        // Get base content blocks for location
        $available_blocks = $this->get_available_content_blocks( $location );
        
        foreach ( $available_blocks as $block ) {
            // Check if block should be shown to user
            if ( $this->should_show_block( $block, $profile ) ) {
                // Personalize block content
                $block['content'] = $this->personalize_block_content( 
                    $block['content'], 
                    $profile 
                );
                
                // Calculate priority
                $block['priority'] = $this->calculate_block_priority( $block, $profile );
                
                $blocks[] = $block;
            }
        }
        
        // Sort by priority
        usort( $blocks, function( $a, $b ) {
            return $b['priority'] <=> $a['priority'];
        });
        
        return $blocks;
    }
    
    /**
     * Create personalized journey
     * 
     * @param mixed $user_identifier User identifier
     * @return array Journey data
     */
    public function create_personalized_journey( $user_identifier ) {
        $profile = $this->get_user_profile( $user_identifier );
        
        $journey = array(
            'stages' => array(),
            'current_stage' => $this->determine_current_stage( $profile ),
            'next_actions' => array(),
            'milestones' => array(),
            'timeline' => array()
        );
        
        // Define journey stages based on profile
        $journey['stages'] = $this->define_journey_stages( $profile );
        
        // Get recommended next actions
        $journey['next_actions'] = $this->get_next_actions( $profile, $journey['current_stage'] );
        
        // Define milestones
        $journey['milestones'] = $this->define_milestones( $profile );
        
        // Create timeline
        $journey['timeline'] = $this->create_journey_timeline( $profile, $journey );
        
        // Store journey
        $this->save_user_journey( $user_identifier, $journey );
        
        return $journey;
    }
    
    /**
     * Get dynamic pricing
     * 
     * @param array $base_pricing Base pricing options
     * @param mixed $user_identifier User identifier
     * @return array Personalized pricing
     */
    public function get_dynamic_pricing( array $base_pricing, $user_identifier ) {
        $profile = $this->get_user_profile( $user_identifier );
        
        $personalized_pricing = array();
        
        foreach ( $base_pricing as $option ) {
            $personalized = $option;
            
            // Calculate personalized price
            $price_modifier = $this->calculate_price_modifier( $profile, $option );
            $personalized['price'] = $option['price'] * $price_modifier;
            
            // Add personalized messaging
            $personalized['message'] = $this->get_pricing_message( $profile, $option );
            
            // Add urgency factors
            $personalized['urgency'] = $this->calculate_urgency( $profile, $option );
            
            // Add social proof
            $personalized['social_proof'] = $this->get_social_proof( $profile, $option );
            
            $personalized_pricing[] = $personalized;
        }
        
        // Sort by relevance
        usort( $personalized_pricing, function( $a, $b ) use ( $profile ) {
            $score_a = $this->calculate_pricing_relevance( $a, $profile );
            $score_b = $this->calculate_pricing_relevance( $b, $profile );
            return $score_b <=> $score_a;
        });
        
        return $personalized_pricing;
    }
    
    /**
     * Get personalized email sequence
     * 
     * @param mixed $user_identifier User identifier
     * @param string $sequence_type Sequence type
     * @return array Email sequence
     */
    public function get_personalized_email_sequence( $user_identifier, $sequence_type ) {
        $profile = $this->get_user_profile( $user_identifier );
        
        // Get base sequence
        $base_sequence = $this->get_base_email_sequence( $sequence_type );
        
        $personalized_sequence = array();
        
        foreach ( $base_sequence as $email ) {
            // Check if email should be sent
            if ( $this->should_send_email( $email, $profile ) ) {
                // Personalize timing
                $email['send_after'] = $this->calculate_email_timing( $email, $profile );
                
                // Personalize subject
                $email['subject'] = $this->personalize_email_subject( 
                    $email['subject'], 
                    $profile 
                );
                
                // Personalize content
                $email['template_vars'] = $this->get_email_template_vars( $profile );
                
                // Add to sequence
                $personalized_sequence[] = $email;
            }
        }
        
        // Add dynamic emails based on behavior
        $dynamic_emails = $this->get_dynamic_emails( $profile, $sequence_type );
        $personalized_sequence = array_merge( $personalized_sequence, $dynamic_emails );
        
        // Sort by send time
        usort( $personalized_sequence, function( $a, $b ) {
            return $a['send_after'] <=> $b['send_after'];
        });
        
        return $personalized_sequence;
    }
    
    /**
     * Update user profile after quiz completion
     * 
     * @param int $result_id Result ID
     * @param int $prospect_id Prospect ID
     * @param int $archetype_id Archetype ID
     */
    public function update_user_profile( $result_id, $prospect_id, $archetype_id ) {
        $prospect = Prospect::find( $prospect_id );
        if ( ! $prospect ) {
            return;
        }
        
        $user_identifier = $prospect->Email;
        
        // Clear cached profile
        unset( $this->profiles[ $user_identifier ] );
        CacheUtil::delete( 'user_profile_' . md5( $user_identifier ) );
        
        // Update profile metrics
        $this->update_profile_metrics( $user_identifier, array(
            'quiz_completions' => 1,
            'last_archetype' => $archetype_id,
            'last_completion' => current_time( 'mysql' )
        ));
        
        // Update segments
        $this->update_user_segments( $user_identifier );
        
        // Trigger profile-based automations
        do_action( 'money_quiz_profile_updated', $user_identifier, $result_id );
    }
    
    /**
     * Get user demographics
     * 
     * @param mixed $user_identifier User identifier
     * @return array Demographics data
     */
    protected function get_user_demographics( $user_identifier ) {
        $demographics = array(
            'age_group' => null,
            'gender' => null,
            'location' => array(),
            'language' => get_locale(),
            'device' => $this->detect_device(),
            'timezone' => wp_timezone_string()
        );
        
        // Get from user meta if logged in
        if ( is_user_logged_in() && is_numeric( $user_identifier ) ) {
            $user_meta = get_user_meta( $user_identifier );
            $demographics['age_group'] = $user_meta['age_group'][0] ?? null;
            $demographics['gender'] = $user_meta['gender'][0] ?? null;
        }
        
        // Get from IP-based geolocation
        $demographics['location'] = $this->get_user_location();
        
        // Get from browser/device detection
        $demographics['device'] = $this->detect_device();
        
        return $demographics;
    }
    
    /**
     * Get user behavior data
     * 
     * @param mixed $user_identifier User identifier
     * @return array Behavior data
     */
    protected function get_user_behavior( $user_identifier ) {
        $behavior = array(
            'page_views' => 0,
            'session_duration' => 0,
            'bounce_rate' => 0,
            'engagement_score' => 0,
            'interaction_patterns' => array(),
            'content_preferences' => array(),
            'peak_activity_time' => null
        );
        
        // Get from behavior tracking table
        $behaviors = $this->database->get_results( 'user_behavior', array(
            'where' => array( 'user_identifier' => $user_identifier ),
            'orderby' => 'created_at',
            'order' => 'DESC',
            'limit' => 100
        ));
        
        if ( ! empty( $behaviors ) ) {
            $behavior = $this->analyze_behavior_data( $behaviors );
        }
        
        return $behavior;
    }
    
    /**
     * Get user preferences
     * 
     * @param mixed $user_identifier User identifier
     * @return array Preferences
     */
    protected function get_user_preferences( $user_identifier ) {
        $preferences = array(
            'communication' => array(
                'email_frequency' => 'weekly',
                'preferred_time' => 'morning',
                'channel' => 'email'
            ),
            'content' => array(
                'format' => 'detailed',
                'topics' => array(),
                'difficulty' => 'intermediate'
            ),
            'interaction' => array(
                'quiz_style' => 'detailed',
                'feedback_preference' => 'immediate',
                'guidance_level' => 'moderate'
            )
        );
        
        // Get saved preferences
        $saved = $this->database->get_row( 'user_preferences', array(
            'user_identifier' => $user_identifier
        ));
        
        if ( $saved ) {
            $preferences = array_merge( $preferences, json_decode( $saved->preferences, true ) );
        }
        
        // Infer from behavior
        $inferred = $this->infer_preferences_from_behavior( $user_identifier );
        $preferences = array_merge_recursive( $preferences, $inferred );
        
        return $preferences;
    }
    
    /**
     * Get user segments
     * 
     * @param mixed $user_identifier User identifier
     * @return array Segments
     */
    protected function get_user_segments( $user_identifier ) {
        $segments = array();
        $profile = $this->build_basic_profile( $user_identifier );
        
        // Behavioral segments
        if ( $profile['behavior']['engagement_score'] > 80 ) {
            $segments[] = 'highly_engaged';
        }
        
        if ( $profile['history']['quiz_count'] > 1 ) {
            $segments[] = 'repeat_user';
        }
        
        // Value segments
        if ( $profile['scores']['lifetime_value'] > 100 ) {
            $segments[] = 'high_value';
        }
        
        // Journey stage segments
        $segments[] = 'stage_' . $this->determine_journey_stage( $profile );
        
        // Custom segments from rules
        $custom_segments = $this->evaluate_segment_rules( $profile );
        $segments = array_merge( $segments, $custom_segments );
        
        return array_unique( $segments );
    }
    
    /**
     * Calculate recommendation score
     * 
     * @param array $recommendation Recommendation
     * @param array $profile User profile
     * @return float Score
     */
    protected function calculate_recommendation_score( $recommendation, $profile ) {
        $score = 0;
        
        // Base relevance
        if ( isset( $recommendation['archetype'] ) && 
             $recommendation['archetype'] === $profile['history']['last_archetype'] ) {
            $score += 30;
        }
        
        // Topic match
        $topic_match = array_intersect( 
            $recommendation['topics'] ?? array(),
            $profile['preferences']['content']['topics'] ?? array()
        );
        $score += count( $topic_match ) * 10;
        
        // Behavior alignment
        if ( $this->aligns_with_behavior( $recommendation, $profile['behavior'] ) ) {
            $score += 20;
        }
        
        // Timing relevance
        if ( $this->is_timely( $recommendation, $profile ) ) {
            $score += 15;
        }
        
        // AI boost
        if ( isset( $profile['predictions']['interests'] ) ) {
            foreach ( $profile['predictions']['interests'] as $interest => $weight ) {
                if ( stripos( $recommendation['title'] ?? '', $interest ) !== false ) {
                    $score += $weight * 10;
                }
            }
        }
        
        return min( $score, 100 );
    }
    
    /**
     * Load personalization rules
     */
    protected function load_personalization_rules() {
        $this->rules = CacheUtil::remember( 'personalization_rules', function() {
            return $this->database->get_results( 'personalization_rules', array(
                'where' => array( 'is_active' => 1 ),
                'orderby' => 'priority',
                'order' => 'DESC'
            ), ARRAY_A );
        }, HOUR_IN_SECONDS );
    }
    
    /**
     * Get current user identifier
     * 
     * @return string User identifier
     */
    protected function get_current_user_identifier() {
        if ( is_user_logged_in() ) {
            return 'wp_user_' . get_current_user_id();
        }
        
        // Use session ID or cookie
        if ( isset( $_COOKIE['money_quiz_user_id'] ) ) {
            return sanitize_text_field( $_COOKIE['money_quiz_user_id'] );
        }
        
        // Generate new identifier
        $identifier = 'anon_' . uniqid();
        setcookie( 'money_quiz_user_id', $identifier, time() + YEAR_IN_SECONDS, '/' );
        
        return $identifier;
    }
    
    /**
     * Get AI predictions for user
     * 
     * @param array $profile User profile
     * @return array Predictions
     */
    protected function get_ai_predictions( $profile ) {
        try {
            $predictions = $this->ai_service->get_user_predictions( $profile );
            
            return array(
                'interests' => $predictions['interests'] ?? array(),
                'needs' => $predictions['needs'] ?? array(),
                'next_action' => $predictions['next_action'] ?? null,
                'churn_risk' => $predictions['churn_risk'] ?? 0,
                'conversion_probability' => $predictions['conversion_probability'] ?? 0
            );
        } catch ( \Exception $e ) {
            DebugUtil::log( 'AI predictions error: ' . $e->getMessage(), 'error' );
            return array();
        }
    }
    
    /**
     * Detect user device
     * 
     * @return array Device info
     */
    protected function detect_device() {
        $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? '';
        
        return array(
            'type' => wp_is_mobile() ? 'mobile' : 'desktop',
            'os' => $this->detect_os( $user_agent ),
            'browser' => $this->detect_browser( $user_agent ),
            'viewport' => array(
                'width' => $_COOKIE['viewport_width'] ?? null,
                'height' => $_COOKIE['viewport_height'] ?? null
            )
        );
    }
}

/**
 * Personalization Engine Class
 * 
 * Advanced personalization algorithms
 */
class PersonalizationEngine {
    
    /**
     * Calculate user similarity
     * 
     * @param array $profile1 First user profile
     * @param array $profile2 Second user profile
     * @return float Similarity score (0-1)
     */
    public static function calculate_similarity( $profile1, $profile2 ) {
        $vectors = array();
        
        // Create feature vectors
        $features = array(
            'age_group', 'gender', 'location', 'archetype',
            'engagement_score', 'quiz_count', 'preferences'
        );
        
        foreach ( $features as $feature ) {
            $vectors[] = self::compare_feature( 
                ArrayUtil::get( $profile1, $feature ),
                ArrayUtil::get( $profile2, $feature )
            );
        }
        
        // Calculate cosine similarity
        return self::cosine_similarity( $vectors );
    }
    
    /**
     * Get collaborative filtering recommendations
     * 
     * @param array $user_profile User profile
     * @param array $all_profiles All user profiles
     * @param int   $k Number of neighbors
     * @return array Recommendations
     */
    public static function collaborative_filtering( $user_profile, $all_profiles, $k = 10 ) {
        $neighbors = array();
        
        // Find k nearest neighbors
        foreach ( $all_profiles as $profile ) {
            if ( $profile['identifier'] === $user_profile['identifier'] ) {
                continue;
            }
            
            $similarity = self::calculate_similarity( $user_profile, $profile );
            $neighbors[] = array(
                'profile' => $profile,
                'similarity' => $similarity
            );
        }
        
        // Sort by similarity
        usort( $neighbors, function( $a, $b ) {
            return $b['similarity'] <=> $a['similarity'];
        });
        
        // Get top k
        $top_neighbors = array_slice( $neighbors, 0, $k );
        
        // Aggregate recommendations from neighbors
        return self::aggregate_neighbor_preferences( $top_neighbors, $user_profile );
    }
    
    /**
     * Predict user churn
     * 
     * @param array $profile User profile
     * @return float Churn probability (0-1)
     */
    public static function predict_churn( $profile ) {
        $risk_factors = array(
            'days_since_last_visit' => 0.3,
            'declining_engagement' => 0.25,
            'incomplete_journeys' => 0.2,
            'negative_feedback' => 0.15,
            'low_email_engagement' => 0.1
        );
        
        $churn_score = 0;
        
        // Days since last visit
        if ( isset( $profile['history']['last_visit'] ) ) {
            $days = ( time() - strtotime( $profile['history']['last_visit'] ) ) / DAY_IN_SECONDS;
            if ( $days > 30 ) {
                $churn_score += $risk_factors['days_since_last_visit'] * min( $days / 90, 1 );
            }
        }
        
        // Declining engagement
        if ( $profile['behavior']['engagement_trend'] < 0 ) {
            $churn_score += $risk_factors['declining_engagement'];
        }
        
        // Incomplete journeys
        $incomplete_rate = $profile['history']['incomplete_quizzes'] / 
                          max( $profile['history']['total_quizzes'], 1 );
        $churn_score += $risk_factors['incomplete_journeys'] * $incomplete_rate;
        
        return min( $churn_score, 1 );
    }
}

/**
 * Content Personalization Helper
 */
class ContentPersonalizer {
    
    /**
     * Personalize text with merge tags
     * 
     * @param string $text Text with merge tags
     * @param array  $profile User profile
     * @return string Personalized text
     */
    public static function merge_tags( $text, $profile ) {
        $replacements = array(
            '{first_name}' => $profile['demographics']['first_name'] ?? 'there',
            '{archetype}' => $profile['history']['last_archetype_name'] ?? 'your personality type',
            '{score}' => $profile['history']['last_score'] ?? 'your score',
            '{next_step}' => $profile['journey']['next_action'] ?? 'the next step',
            '{achievement}' => self::get_latest_achievement( $profile ),
            '{comparison}' => self::get_peer_comparison( $profile )
        );
        
        return str_replace( 
            array_keys( $replacements ), 
            array_values( $replacements ), 
            $text 
        );
    }
    
    /**
     * Get dynamic content variant
     * 
     * @param array $variants Content variants
     * @param array $profile User profile
     * @return string Selected content
     */
    public static function select_variant( $variants, $profile ) {
        $best_variant = null;
        $best_score = -1;
        
        foreach ( $variants as $variant ) {
            $score = 0;
            
            // Check conditions
            if ( isset( $variant['conditions'] ) ) {
                if ( ! self::check_conditions( $variant['conditions'], $profile ) ) {
                    continue;
                }
            }
            
            // Calculate relevance score
            if ( isset( $variant['targets'] ) ) {
                foreach ( $variant['targets'] as $target => $weight ) {
                    if ( self::matches_target( $target, $profile ) ) {
                        $score += $weight;
                    }
                }
            }
            
            if ( $score > $best_score ) {
                $best_score = $score;
                $best_variant = $variant;
            }
        }
        
        return $best_variant ? $best_variant['content'] : $variants[0]['content'];
    }
}
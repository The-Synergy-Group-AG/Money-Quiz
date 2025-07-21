<?php
/**
 * Money Quiz Plugin - AI Service
 * Worker 1: AI Integration Service
 * 
 * Provides AI-powered features including result analysis, personalized
 * recommendations, and intelligent insights using multiple AI providers.
 * 
 * @package MoneyQuiz
 * @subpackage Services
 * @since 4.0.0
 */

namespace MoneyQuiz\Services;

use Exception;
use MoneyQuiz\Utilities\CacheUtil;
use MoneyQuiz\Utilities\DebugUtil;
use MoneyQuiz\Models\Settings;
use MoneyQuiz\Models\ActivityLog;

/**
 * AI Service Class
 * 
 * Handles AI-powered features and integrations
 */
class AIService {
    
    /**
     * AI provider instances
     * 
     * @var array
     */
    protected $providers = array();
    
    /**
     * Active provider
     * 
     * @var string
     */
    protected $active_provider;
    
    /**
     * Configuration
     * 
     * @var array
     */
    protected $config = array();
    
    /**
     * Cache duration for AI responses
     * 
     * @var int
     */
    protected $cache_duration = 3600; // 1 hour
    
    /**
     * Constructor
     */
    public function __construct() {
        $this->load_config();
        $this->init_providers();
    }
    
    /**
     * Load configuration
     */
    protected function load_config() {
        $this->config = array(
            'provider' => Settings::get( 'ai_provider', 'openai' ),
            'openai_api_key' => Settings::get( 'openai_api_key' ),
            'anthropic_api_key' => Settings::get( 'anthropic_api_key' ),
            'grok_api_key' => Settings::get( 'grok_api_key' ),
            'ai_features_enabled' => Settings::get( 'ai_features_enabled', true ),
            'cache_ai_responses' => Settings::get( 'cache_ai_responses', true ),
            'ai_model' => Settings::get( 'ai_model', 'gpt-4' ),
            'max_tokens' => Settings::get( 'ai_max_tokens', 1000 ),
            'temperature' => Settings::get( 'ai_temperature', 0.7 )
        );
        
        $this->active_provider = $this->config['provider'];
    }
    
    /**
     * Initialize AI providers
     */
    protected function init_providers() {
        // OpenAI provider
        if ( ! empty( $this->config['openai_api_key'] ) ) {
            $this->providers['openai'] = new Providers\OpenAIProvider( 
                $this->config['openai_api_key'],
                $this->config 
            );
        }
        
        // Anthropic Claude provider
        if ( ! empty( $this->config['anthropic_api_key'] ) ) {
            $this->providers['anthropic'] = new Providers\AnthropicProvider(
                $this->config['anthropic_api_key'],
                $this->config
            );
        }
        
        // Grok provider
        if ( ! empty( $this->config['grok_api_key'] ) ) {
            $this->providers['grok'] = new Providers\GrokProvider(
                $this->config['grok_api_key'],
                $this->config
            );
        }
    }
    
    /**
     * Get AI-powered result analysis
     * 
     * @param array $result_data Quiz result data
     * @return array Analysis data
     * @throws Exception
     */
    public function analyze_result( array $result_data ) {
        if ( ! $this->is_enabled() ) {
            throw new Exception( __( 'AI features are not enabled', 'money-quiz' ) );
        }
        
        $cache_key = 'ai_analysis_' . md5( json_encode( $result_data ) );
        
        // Check cache
        if ( $this->config['cache_ai_responses'] ) {
            $cached = CacheUtil::get( $cache_key );
            if ( $cached !== null ) {
                return $cached;
            }
        }
        
        try {
            // Prepare analysis prompt
            $prompt = $this->prepare_analysis_prompt( $result_data );
            
            // Get AI analysis
            $analysis = $this->get_completion( $prompt, array(
                'max_tokens' => 1500,
                'temperature' => 0.6
            ));
            
            // Parse and structure the response
            $structured_analysis = $this->parse_analysis( $analysis, $result_data );
            
            // Cache the result
            if ( $this->config['cache_ai_responses'] ) {
                CacheUtil::set( $cache_key, $structured_analysis, $this->cache_duration );
            }
            
            // Log activity
            ActivityLog::log( 'ai_analysis_generated', array(
                'provider' => $this->active_provider,
                'result_id' => $result_data['result_id'] ?? null,
                'archetype' => $result_data['archetype']['name'] ?? null
            ));
            
            return $structured_analysis;
            
        } catch ( Exception $e ) {
            DebugUtil::log( 'AI analysis error: ' . $e->getMessage(), 'error' );
            throw new Exception( __( 'Failed to generate AI analysis', 'money-quiz' ) );
        }
    }
    
    /**
     * Get personalized recommendations
     * 
     * @param array $profile User financial profile
     * @return array Recommendations
     * @throws Exception
     */
    public function get_recommendations( array $profile ) {
        if ( ! $this->is_enabled() ) {
            return $this->get_default_recommendations( $profile );
        }
        
        $cache_key = 'ai_recommendations_' . md5( json_encode( $profile ) );
        
        // Check cache
        if ( $this->config['cache_ai_responses'] ) {
            $cached = CacheUtil::get( $cache_key );
            if ( $cached !== null ) {
                return $cached;
            }
        }
        
        try {
            // Prepare recommendation prompt
            $prompt = $this->prepare_recommendation_prompt( $profile );
            
            // Get AI recommendations
            $response = $this->get_completion( $prompt, array(
                'max_tokens' => 2000,
                'temperature' => 0.7
            ));
            
            // Parse recommendations
            $recommendations = $this->parse_recommendations( $response, $profile );
            
            // Cache the result
            if ( $this->config['cache_ai_responses'] ) {
                CacheUtil::set( $cache_key, $recommendations, $this->cache_duration );
            }
            
            return $recommendations;
            
        } catch ( Exception $e ) {
            DebugUtil::log( 'AI recommendations error: ' . $e->getMessage(), 'error' );
            return $this->get_default_recommendations( $profile );
        }
    }
    
    /**
     * Generate quiz questions using AI
     * 
     * @param array $parameters Generation parameters
     * @return array Generated questions
     * @throws Exception
     */
    public function generate_questions( array $parameters ) {
        if ( ! $this->is_enabled() ) {
            throw new Exception( __( 'AI features are not enabled', 'money-quiz' ) );
        }
        
        try {
            // Prepare generation prompt
            $prompt = $this->prepare_question_generation_prompt( $parameters );
            
            // Get AI response
            $response = $this->get_completion( $prompt, array(
                'max_tokens' => 2500,
                'temperature' => 0.8
            ));
            
            // Parse questions
            $questions = $this->parse_generated_questions( $response );
            
            // Validate questions
            $validated_questions = $this->validate_questions( $questions );
            
            // Log activity
            ActivityLog::log( 'ai_questions_generated', array(
                'count' => count( $validated_questions ),
                'category' => $parameters['category'] ?? 'general',
                'provider' => $this->active_provider
            ));
            
            return $validated_questions;
            
        } catch ( Exception $e ) {
            DebugUtil::log( 'AI question generation error: ' . $e->getMessage(), 'error' );
            throw new Exception( __( 'Failed to generate questions', 'money-quiz' ) );
        }
    }
    
    /**
     * Get AI completion
     * 
     * @param string $prompt Prompt text
     * @param array  $options Options
     * @return string AI response
     * @throws Exception
     */
    protected function get_completion( $prompt, array $options = array() ) {
        if ( ! isset( $this->providers[ $this->active_provider ] ) ) {
            throw new Exception( __( 'AI provider not configured', 'money-quiz' ) );
        }
        
        $provider = $this->providers[ $this->active_provider ];
        
        return $provider->get_completion( $prompt, $options );
    }
    
    /**
     * Prepare analysis prompt
     * 
     * @param array $result_data Result data
     * @return string Prompt
     */
    protected function prepare_analysis_prompt( array $result_data ) {
        $archetype = $result_data['archetype']['name'] ?? 'Unknown';
        $score = $result_data['score'] ?? 0;
        $answers = $result_data['answers'] ?? array();
        
        $prompt = "Analyze the following money personality quiz results and provide insights:\n\n";
        $prompt .= "Archetype: {$archetype}\n";
        $prompt .= "Score: {$score}\n";
        $prompt .= "Total Questions: " . count( $answers ) . "\n\n";
        
        // Add answer patterns
        $prompt .= "Answer Patterns:\n";
        $categories = array();
        foreach ( $answers as $answer ) {
            $category = $answer['category'] ?? 'General';
            if ( ! isset( $categories[ $category ] ) ) {
                $categories[ $category ] = array();
            }
            $categories[ $category ][] = $answer['value'];
        }
        
        foreach ( $categories as $category => $values ) {
            $avg = array_sum( $values ) / count( $values );
            $prompt .= "- {$category}: " . number_format( $avg, 1 ) . "/8\n";
        }
        
        $prompt .= "\nPlease provide:\n";
        $prompt .= "1. A detailed personality analysis (2-3 paragraphs)\n";
        $prompt .= "2. Key strengths (3-5 points)\n";
        $prompt .= "3. Areas for improvement (3-5 points)\n";
        $prompt .= "4. Actionable next steps (5-7 specific actions)\n";
        $prompt .= "5. Recommended resources or tools\n";
        
        return $prompt;
    }
    
    /**
     * Prepare recommendation prompt
     * 
     * @param array $profile User profile
     * @return string Prompt
     */
    protected function prepare_recommendation_prompt( array $profile ) {
        $prompt = "Based on the following financial profile, provide personalized recommendations:\n\n";
        
        $prompt .= "Profile:\n";
        $prompt .= "- Archetype: " . ( $profile['archetype'] ?? 'Unknown' ) . "\n";
        $prompt .= "- Financial Goals: " . implode( ', ', $profile['goals'] ?? array() ) . "\n";
        $prompt .= "- Risk Tolerance: " . ( $profile['risk_tolerance'] ?? 'Medium' ) . "\n";
        $prompt .= "- Income Level: " . ( $profile['income_level'] ?? 'Not specified' ) . "\n";
        $prompt .= "- Age Group: " . ( $profile['age_group'] ?? 'Not specified' ) . "\n";
        
        $prompt .= "\nProvide recommendations in these categories:\n";
        $prompt .= "1. Budgeting strategies (3-4 specific techniques)\n";
        $prompt .= "2. Savings recommendations (including percentages and priorities)\n";
        $prompt .= "3. Investment suggestions (based on risk tolerance)\n";
        $prompt .= "4. Debt management (if applicable)\n";
        $prompt .= "5. Financial education resources\n";
        $prompt .= "6. Tools and apps recommendations\n";
        
        return $prompt;
    }
    
    /**
     * Prepare question generation prompt
     * 
     * @param array $parameters Parameters
     * @return string Prompt
     */
    protected function prepare_question_generation_prompt( array $parameters ) {
        $count = $parameters['count'] ?? 5;
        $category = $parameters['category'] ?? 'General';
        $difficulty = $parameters['difficulty'] ?? 'Medium';
        
        $prompt = "Generate {$count} money personality quiz questions for the '{$category}' category.\n\n";
        $prompt .= "Requirements:\n";
        $prompt .= "- Questions should assess financial attitudes and behaviors\n";
        $prompt .= "- Use a 1-8 scale (1=Strongly Disagree, 8=Completely Agree)\n";
        $prompt .= "- Difficulty level: {$difficulty}\n";
        $prompt .= "- Avoid yes/no questions\n";
        $prompt .= "- Focus on personal preferences, not knowledge\n\n";
        
        $prompt .= "Format each question as:\n";
        $prompt .= "Q: [Question text]\n";
        $prompt .= "Category: {$category}\n";
        $prompt .= "Weight: [1.0-2.0 based on importance]\n\n";
        
        return $prompt;
    }
    
    /**
     * Parse AI analysis response
     * 
     * @param string $response AI response
     * @param array  $result_data Original result data
     * @return array Structured analysis
     */
    protected function parse_analysis( $response, array $result_data ) {
        // Extract sections using patterns
        $sections = array(
            'personality_analysis' => $this->extract_section( $response, 'personality analysis', 'strengths' ),
            'strengths' => $this->extract_list( $response, 'strengths', 'improvement' ),
            'improvements' => $this->extract_list( $response, 'improvement', 'next steps' ),
            'next_steps' => $this->extract_list( $response, 'next steps', 'resources' ),
            'resources' => $this->extract_section( $response, 'resources', null )
        );
        
        return array(
            'archetype' => $result_data['archetype']['name'] ?? '',
            'score' => $result_data['score'] ?? 0,
            'analysis' => array_filter( $sections ),
            'generated_at' => current_time( 'mysql' ),
            'provider' => $this->active_provider
        );
    }
    
    /**
     * Parse AI recommendations
     * 
     * @param string $response AI response
     * @param array  $profile User profile
     * @return array Structured recommendations
     */
    protected function parse_recommendations( $response, array $profile ) {
        $categories = array(
            'budgeting' => $this->extract_list( $response, 'budgeting', 'savings' ),
            'savings' => $this->extract_list( $response, 'savings', 'investment' ),
            'investment' => $this->extract_list( $response, 'investment', 'debt' ),
            'debt_management' => $this->extract_list( $response, 'debt', 'education' ),
            'education' => $this->extract_list( $response, 'education', 'tools' ),
            'tools' => $this->extract_list( $response, 'tools', null )
        );
        
        return array(
            'profile' => $profile,
            'recommendations' => array_filter( $categories ),
            'priority_actions' => $this->extract_priority_actions( $categories ),
            'generated_at' => current_time( 'mysql' ),
            'provider' => $this->active_provider
        );
    }
    
    /**
     * Parse generated questions
     * 
     * @param string $response AI response
     * @return array Questions
     */
    protected function parse_generated_questions( $response ) {
        $questions = array();
        
        // Split by question markers
        $parts = preg_split( '/Q:\s*/i', $response );
        
        foreach ( $parts as $part ) {
            if ( empty( trim( $part ) ) ) {
                continue;
            }
            
            // Extract question text
            $lines = explode( "\n", $part );
            $question_text = trim( $lines[0] );
            
            if ( empty( $question_text ) ) {
                continue;
            }
            
            // Extract metadata
            $category = '';
            $weight = 1.0;
            
            foreach ( $lines as $line ) {
                if ( preg_match( '/Category:\s*(.+)/i', $line, $matches ) ) {
                    $category = trim( $matches[1] );
                }
                if ( preg_match( '/Weight:\s*([\d.]+)/i', $line, $matches ) ) {
                    $weight = floatval( $matches[1] );
                }
            }
            
            $questions[] = array(
                'text' => $question_text,
                'category' => $category,
                'weight' => $weight,
                'type' => 'scale'
            );
        }
        
        return $questions;
    }
    
    /**
     * Extract section from response
     * 
     * @param string $response Full response
     * @param string $start Start marker
     * @param string $end End marker
     * @return string Section content
     */
    protected function extract_section( $response, $start, $end ) {
        $pattern = '/' . preg_quote( $start, '/' ) . '.*?:(.*?)';
        if ( $end ) {
            $pattern .= '(?=' . preg_quote( $end, '/' ) . ')';
        }
        $pattern .= '/is';
        
        if ( preg_match( $pattern, $response, $matches ) ) {
            return trim( $matches[1] );
        }
        
        return '';
    }
    
    /**
     * Extract list items from response
     * 
     * @param string $response Full response
     * @param string $start Start marker
     * @param string $end End marker
     * @return array List items
     */
    protected function extract_list( $response, $start, $end ) {
        $section = $this->extract_section( $response, $start, $end );
        
        if ( empty( $section ) ) {
            return array();
        }
        
        // Extract bullet points or numbered items
        $items = array();
        $lines = explode( "\n", $section );
        
        foreach ( $lines as $line ) {
            $line = trim( $line );
            if ( preg_match( '/^[-*•\d.]\s*(.+)/', $line, $matches ) ) {
                $items[] = trim( $matches[1] );
            }
        }
        
        return $items;
    }
    
    /**
     * Extract priority actions
     * 
     * @param array $categories All recommendations
     * @return array Priority actions
     */
    protected function extract_priority_actions( array $categories ) {
        $priority_actions = array();
        
        // Take first item from each category
        foreach ( $categories as $category => $items ) {
            if ( ! empty( $items ) ) {
                $priority_actions[] = array(
                    'category' => $category,
                    'action' => $items[0]
                );
            }
        }
        
        // Limit to top 5
        return array_slice( $priority_actions, 0, 5 );
    }
    
    /**
     * Validate generated questions
     * 
     * @param array $questions Questions to validate
     * @return array Valid questions
     */
    protected function validate_questions( array $questions ) {
        $valid = array();
        
        foreach ( $questions as $question ) {
            // Check required fields
            if ( empty( $question['text'] ) || strlen( $question['text'] ) < 10 ) {
                continue;
            }
            
            // Validate weight
            if ( $question['weight'] < 0.5 || $question['weight'] > 3.0 ) {
                $question['weight'] = 1.0;
            }
            
            // Ensure category
            if ( empty( $question['category'] ) ) {
                $question['category'] = 'General';
            }
            
            $valid[] = $question;
        }
        
        return $valid;
    }
    
    /**
     * Get default recommendations
     * 
     * @param array $profile User profile
     * @return array Default recommendations
     */
    protected function get_default_recommendations( array $profile ) {
        $archetype = $profile['archetype'] ?? 'Unknown';
        
        $defaults = array(
            'The Spender' => array(
                'budgeting' => array(
                    'Use the 50/30/20 budget rule',
                    'Track daily expenses with an app',
                    'Set spending limits for categories'
                ),
                'savings' => array(
                    'Automate savings transfers',
                    'Start with 5% of income',
                    'Use a high-yield savings account'
                )
            ),
            'The Saver' => array(
                'budgeting' => array(
                    'Review and optimize fixed expenses',
                    'Look for additional income streams',
                    'Consider the opportunity cost of purchases'
                ),
                'investment' => array(
                    'Diversify beyond savings accounts',
                    'Consider index funds',
                    'Learn about compound interest'
                )
            ),
            'The Investor' => array(
                'investment' => array(
                    'Review portfolio allocation quarterly',
                    'Consider tax-efficient strategies',
                    'Stay informed about market trends'
                ),
                'education' => array(
                    'Read investment books and blogs',
                    'Join investment communities',
                    'Consider working with a financial advisor'
                )
            ),
            'The Balancer' => array(
                'budgeting' => array(
                    'Maintain your balanced approach',
                    'Regular financial check-ins',
                    'Teach others your strategies'
                ),
                'savings' => array(
                    'Build multiple savings goals',
                    'Consider laddering CDs',
                    'Plan for long-term goals'
                )
            )
        );
        
        $recommendations = $defaults[ $archetype ] ?? array();
        
        return array(
            'profile' => $profile,
            'recommendations' => $recommendations,
            'priority_actions' => array(),
            'generated_at' => current_time( 'mysql' ),
            'provider' => 'default'
        );
    }
    
    /**
     * Check if AI features are enabled
     * 
     * @return bool
     */
    public function is_enabled() {
        return $this->config['ai_features_enabled'] && 
               ! empty( $this->providers );
    }
    
    /**
     * Get available providers
     * 
     * @return array
     */
    public function get_available_providers() {
        return array_keys( $this->providers );
    }
    
    /**
     * Set active provider
     * 
     * @param string $provider Provider name
     * @throws Exception
     */
    public function set_provider( $provider ) {
        if ( ! isset( $this->providers[ $provider ] ) ) {
            throw new Exception( __( 'Invalid AI provider', 'money-quiz' ) );
        }
        
        $this->active_provider = $provider;
        Settings::set( 'ai_provider', $provider );
    }
    
    /**
     * Test provider connection
     * 
     * @param string $provider Provider name
     * @return bool
     */
    public function test_provider( $provider = null ) {
        $provider = $provider ?? $this->active_provider;
        
        if ( ! isset( $this->providers[ $provider ] ) ) {
            return false;
        }
        
        try {
            $response = $this->providers[ $provider ]->test_connection();
            return ! empty( $response );
        } catch ( Exception $e ) {
            DebugUtil::log( 'AI provider test failed: ' . $e->getMessage(), 'error' );
            return false;
        }
    }
}

/**
 * Base AI Provider Class
 */
namespace MoneyQuiz\Services\Providers;

abstract class BaseAIProvider {
    
    /**
     * API key
     * 
     * @var string
     */
    protected $api_key;
    
    /**
     * Configuration
     * 
     * @var array
     */
    protected $config;
    
    /**
     * Constructor
     * 
     * @param string $api_key API key
     * @param array  $config Configuration
     */
    public function __construct( $api_key, array $config = array() ) {
        $this->api_key = $api_key;
        $this->config = $config;
    }
    
    /**
     * Get completion from AI
     * 
     * @param string $prompt Prompt
     * @param array  $options Options
     * @return string Response
     */
    abstract public function get_completion( $prompt, array $options = array() );
    
    /**
     * Test connection
     * 
     * @return bool
     */
    abstract public function test_connection();
    
    /**
     * Make API request
     * 
     * @param string $url URL
     * @param array  $data Request data
     * @param array  $headers Headers
     * @return array Response
     * @throws Exception
     */
    protected function make_request( $url, array $data, array $headers = array() ) {
        $args = array(
            'body' => json_encode( $data ),
            'headers' => array_merge( array(
                'Content-Type' => 'application/json'
            ), $headers ),
            'timeout' => 30,
            'method' => 'POST'
        );
        
        $response = wp_remote_post( $url, $args );
        
        if ( is_wp_error( $response ) ) {
            throw new Exception( $response->get_error_message() );
        }
        
        $body = wp_remote_retrieve_body( $response );
        $data = json_decode( $body, true );
        
        if ( json_last_error() !== JSON_ERROR_NONE ) {
            throw new Exception( 'Invalid JSON response' );
        }
        
        return $data;
    }
}
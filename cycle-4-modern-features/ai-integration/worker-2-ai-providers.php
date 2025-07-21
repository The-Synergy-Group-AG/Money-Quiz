<?php
/**
 * Money Quiz Plugin - AI Provider Implementations
 * Worker 2: AI Provider Classes
 * 
 * Implements specific AI provider integrations including OpenAI,
 * Anthropic Claude, and Grok for the Money Quiz plugin.
 * 
 * @package MoneyQuiz
 * @subpackage Services\Providers
 * @since 4.0.0
 */

namespace MoneyQuiz\Services\Providers;

use Exception;
use MoneyQuiz\Utilities\DebugUtil;

/**
 * OpenAI Provider Class
 * 
 * Integrates with OpenAI's GPT models
 */
class OpenAIProvider extends BaseAIProvider {
    
    /**
     * API endpoint
     * 
     * @var string
     */
    const API_ENDPOINT = 'https://api.openai.com/v1/chat/completions';
    
    /**
     * Get completion from OpenAI
     * 
     * @param string $prompt Prompt
     * @param array  $options Options
     * @return string Response
     * @throws Exception
     */
    public function get_completion( $prompt, array $options = array() ) {
        $model = $options['model'] ?? $this->config['ai_model'] ?? 'gpt-4';
        $max_tokens = $options['max_tokens'] ?? $this->config['max_tokens'] ?? 1000;
        $temperature = $options['temperature'] ?? $this->config['temperature'] ?? 0.7;
        
        $data = array(
            'model' => $model,
            'messages' => array(
                array(
                    'role' => 'system',
                    'content' => 'You are a financial advisor AI assistant helping users understand their money personality and improve their financial habits.'
                ),
                array(
                    'role' => 'user',
                    'content' => $prompt
                )
            ),
            'max_tokens' => $max_tokens,
            'temperature' => $temperature,
            'top_p' => 1,
            'frequency_penalty' => 0,
            'presence_penalty' => 0
        );
        
        try {
            $response = $this->make_request( self::API_ENDPOINT, $data, array(
                'Authorization' => 'Bearer ' . $this->api_key
            ));
            
            if ( isset( $response['error'] ) ) {
                throw new Exception( $response['error']['message'] ?? 'Unknown OpenAI error' );
            }
            
            if ( ! isset( $response['choices'][0]['message']['content'] ) ) {
                throw new Exception( 'Invalid OpenAI response format' );
            }
            
            return $response['choices'][0]['message']['content'];
            
        } catch ( Exception $e ) {
            DebugUtil::log( 'OpenAI API error: ' . $e->getMessage(), 'error' );
            throw new Exception( __( 'Failed to get AI response from OpenAI', 'money-quiz' ) );
        }
    }
    
    /**
     * Test connection to OpenAI
     * 
     * @return bool
     */
    public function test_connection() {
        try {
            $response = $this->get_completion( 'Say "Hello"', array(
                'max_tokens' => 10,
                'model' => 'gpt-3.5-turbo'
            ));
            
            return ! empty( $response );
        } catch ( Exception $e ) {
            return false;
        }
    }
    
    /**
     * Get available models
     * 
     * @return array
     */
    public function get_available_models() {
        return array(
            'gpt-4' => 'GPT-4 (Most capable)',
            'gpt-4-turbo-preview' => 'GPT-4 Turbo (Faster)',
            'gpt-3.5-turbo' => 'GPT-3.5 Turbo (Cost-effective)',
            'gpt-3.5-turbo-16k' => 'GPT-3.5 Turbo 16K (Longer context)'
        );
    }
}

/**
 * Anthropic Claude Provider Class
 * 
 * Integrates with Anthropic's Claude models
 */
class AnthropicProvider extends BaseAIProvider {
    
    /**
     * API endpoint
     * 
     * @var string
     */
    const API_ENDPOINT = 'https://api.anthropic.com/v1/messages';
    
    /**
     * API version
     * 
     * @var string
     */
    const API_VERSION = '2023-06-01';
    
    /**
     * Get completion from Claude
     * 
     * @param string $prompt Prompt
     * @param array  $options Options
     * @return string Response
     * @throws Exception
     */
    public function get_completion( $prompt, array $options = array() ) {
        $model = $options['model'] ?? 'claude-3-opus-20240229';
        $max_tokens = $options['max_tokens'] ?? $this->config['max_tokens'] ?? 1000;
        $temperature = $options['temperature'] ?? $this->config['temperature'] ?? 0.7;
        
        $data = array(
            'model' => $model,
            'messages' => array(
                array(
                    'role' => 'user',
                    'content' => $prompt
                )
            ),
            'max_tokens' => $max_tokens,
            'temperature' => $temperature,
            'system' => 'You are Claude, a helpful AI assistant specializing in financial advice and money personality analysis. Provide clear, actionable insights to help users improve their financial habits.'
        );
        
        try {
            $response = $this->make_request( self::API_ENDPOINT, $data, array(
                'x-api-key' => $this->api_key,
                'anthropic-version' => self::API_VERSION,
                'Content-Type' => 'application/json'
            ));
            
            if ( isset( $response['error'] ) ) {
                throw new Exception( $response['error']['message'] ?? 'Unknown Anthropic error' );
            }
            
            if ( ! isset( $response['content'][0]['text'] ) ) {
                throw new Exception( 'Invalid Claude response format' );
            }
            
            return $response['content'][0]['text'];
            
        } catch ( Exception $e ) {
            DebugUtil::log( 'Anthropic API error: ' . $e->getMessage(), 'error' );
            throw new Exception( __( 'Failed to get AI response from Claude', 'money-quiz' ) );
        }
    }
    
    /**
     * Test connection to Anthropic
     * 
     * @return bool
     */
    public function test_connection() {
        try {
            $response = $this->get_completion( 'Say "Hello"', array(
                'max_tokens' => 10
            ));
            
            return ! empty( $response );
        } catch ( Exception $e ) {
            return false;
        }
    }
    
    /**
     * Get available models
     * 
     * @return array
     */
    public function get_available_models() {
        return array(
            'claude-3-opus-20240229' => 'Claude 3 Opus (Most capable)',
            'claude-3-sonnet-20240229' => 'Claude 3 Sonnet (Balanced)',
            'claude-3-haiku-20240307' => 'Claude 3 Haiku (Fastest)',
            'claude-2.1' => 'Claude 2.1 (Previous generation)'
        );
    }
}

/**
 * Grok Provider Class
 * 
 * Integrates with xAI's Grok models
 */
class GrokProvider extends BaseAIProvider {
    
    /**
     * API endpoint
     * 
     * @var string
     */
    const API_ENDPOINT = 'https://api.x.ai/v1/chat/completions';
    
    /**
     * Get completion from Grok
     * 
     * @param string $prompt Prompt
     * @param array  $options Options
     * @return string Response
     * @throws Exception
     */
    public function get_completion( $prompt, array $options = array() ) {
        $model = $options['model'] ?? 'grok-beta';
        $max_tokens = $options['max_tokens'] ?? $this->config['max_tokens'] ?? 1000;
        $temperature = $options['temperature'] ?? $this->config['temperature'] ?? 0.7;
        
        $data = array(
            'model' => $model,
            'messages' => array(
                array(
                    'role' => 'system',
                    'content' => 'You are Grok, an AI assistant with a good sense of humor but also serious expertise in financial advice. Help users understand their money personality with both wit and wisdom.'
                ),
                array(
                    'role' => 'user',
                    'content' => $prompt
                )
            ),
            'stream' => false,
            'temperature' => $temperature,
            'max_tokens' => $max_tokens
        );
        
        try {
            $response = $this->make_request( self::API_ENDPOINT, $data, array(
                'Authorization' => 'Bearer ' . $this->api_key,
                'Content-Type' => 'application/json'
            ));
            
            if ( isset( $response['error'] ) ) {
                throw new Exception( $response['error']['message'] ?? 'Unknown Grok error' );
            }
            
            if ( ! isset( $response['choices'][0]['message']['content'] ) ) {
                throw new Exception( 'Invalid Grok response format' );
            }
            
            return $response['choices'][0]['message']['content'];
            
        } catch ( Exception $e ) {
            DebugUtil::log( 'Grok API error: ' . $e->getMessage(), 'error' );
            throw new Exception( __( 'Failed to get AI response from Grok', 'money-quiz' ) );
        }
    }
    
    /**
     * Test connection to Grok
     * 
     * @return bool
     */
    public function test_connection() {
        try {
            $response = $this->get_completion( 'Say "Hello"', array(
                'max_tokens' => 10
            ));
            
            return ! empty( $response );
        } catch ( Exception $e ) {
            return false;
        }
    }
    
    /**
     * Get available models
     * 
     * @return array
     */
    public function get_available_models() {
        return array(
            'grok-beta' => 'Grok Beta (Latest)',
            'grok-1' => 'Grok 1 (Stable)'
        );
    }
}

/**
 * Local AI Provider Class
 * 
 * For self-hosted AI models
 */
class LocalAIProvider extends BaseAIProvider {
    
    /**
     * Local endpoint
     * 
     * @var string
     */
    protected $endpoint;
    
    /**
     * Constructor
     * 
     * @param string $endpoint Local AI endpoint
     * @param array  $config Configuration
     */
    public function __construct( $endpoint, array $config = array() ) {
        $this->endpoint = $endpoint;
        $this->config = $config;
    }
    
    /**
     * Get completion from local AI
     * 
     * @param string $prompt Prompt
     * @param array  $options Options
     * @return string Response
     * @throws Exception
     */
    public function get_completion( $prompt, array $options = array() ) {
        $data = array(
            'prompt' => $prompt,
            'max_tokens' => $options['max_tokens'] ?? 1000,
            'temperature' => $options['temperature'] ?? 0.7,
            'top_p' => $options['top_p'] ?? 1,
            'stream' => false
        );
        
        try {
            $response = $this->make_request( $this->endpoint . '/completions', $data );
            
            if ( isset( $response['error'] ) ) {
                throw new Exception( $response['error'] );
            }
            
            if ( ! isset( $response['choices'][0]['text'] ) ) {
                throw new Exception( 'Invalid local AI response format' );
            }
            
            return $response['choices'][0]['text'];
            
        } catch ( Exception $e ) {
            DebugUtil::log( 'Local AI error: ' . $e->getMessage(), 'error' );
            throw new Exception( __( 'Failed to get AI response from local model', 'money-quiz' ) );
        }
    }
    
    /**
     * Test connection to local AI
     * 
     * @return bool
     */
    public function test_connection() {
        try {
            $response = wp_remote_get( $this->endpoint . '/health', array(
                'timeout' => 5
            ));
            
            return ! is_wp_error( $response ) && wp_remote_retrieve_response_code( $response ) === 200;
        } catch ( Exception $e ) {
            return false;
        }
    }
}

/**
 * AI Response Cache Manager
 */
class AICacheManager {
    
    /**
     * Cache prefix
     * 
     * @var string
     */
    const CACHE_PREFIX = 'money_quiz_ai_';
    
    /**
     * Cache a response
     * 
     * @param string $key Cache key
     * @param mixed  $data Data to cache
     * @param int    $expiration Expiration in seconds
     * @return bool
     */
    public static function set( $key, $data, $expiration = 3600 ) {
        return set_transient( self::CACHE_PREFIX . $key, $data, $expiration );
    }
    
    /**
     * Get cached response
     * 
     * @param string $key Cache key
     * @return mixed|null
     */
    public static function get( $key ) {
        $data = get_transient( self::CACHE_PREFIX . $key );
        return $data !== false ? $data : null;
    }
    
    /**
     * Delete cached response
     * 
     * @param string $key Cache key
     * @return bool
     */
    public static function delete( $key ) {
        return delete_transient( self::CACHE_PREFIX . $key );
    }
    
    /**
     * Clear all AI cache
     * 
     * @return int Number of cleared items
     */
    public static function clear_all() {
        global $wpdb;
        
        $count = $wpdb->query(
            $wpdb->prepare(
                "DELETE FROM {$wpdb->options} 
                WHERE option_name LIKE %s 
                OR option_name LIKE %s",
                '_transient_' . self::CACHE_PREFIX . '%',
                '_transient_timeout_' . self::CACHE_PREFIX . '%'
            )
        );
        
        return $count / 2; // Each transient has 2 entries
    }
    
    /**
     * Get cache statistics
     * 
     * @return array
     */
    public static function get_stats() {
        global $wpdb;
        
        $count = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(*) FROM {$wpdb->options} 
                WHERE option_name LIKE %s",
                '_transient_' . self::CACHE_PREFIX . '%'
            )
        );
        
        $size = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT SUM(LENGTH(option_value)) FROM {$wpdb->options} 
                WHERE option_name LIKE %s",
                '_transient_' . self::CACHE_PREFIX . '%'
            )
        );
        
        return array(
            'count' => $count,
            'size' => $size,
            'size_formatted' => size_format( $size )
        );
    }
}

/**
 * AI Prompt Templates
 */
class AIPromptTemplates {
    
    /**
     * Get analysis prompt template
     * 
     * @param string $type Analysis type
     * @return string
     */
    public static function get_analysis_template( $type = 'standard' ) {
        $templates = array(
            'standard' => "Analyze the money personality quiz results for a {archetype} personality type with a score of {score}. Provide insights on their financial strengths, areas for improvement, and actionable recommendations.",
            
            'detailed' => "Provide a comprehensive financial personality analysis for someone identified as {archetype} with the following characteristics:\n- Score: {score}\n- Strengths in: {strengths}\n- Challenges with: {challenges}\n\nInclude psychological insights, behavioral patterns, and specific strategies for financial improvement.",
            
            'comparative' => "Compare the {archetype} money personality (score: {score}) with other personality types. Highlight unique characteristics, common pitfalls, and how they can leverage their strengths while addressing weaknesses.",
            
            'goal_oriented' => "For a {archetype} personality wanting to achieve {goals}, provide a customized financial roadmap. Consider their score of {score} and natural tendencies when suggesting strategies."
        );
        
        return $templates[ $type ] ?? $templates['standard'];
    }
    
    /**
     * Get recommendation prompt template
     * 
     * @param string $focus Recommendation focus
     * @return string
     */
    public static function get_recommendation_template( $focus = 'general' ) {
        $templates = array(
            'general' => "Provide personalized financial recommendations for a {archetype} personality type focusing on practical, actionable steps they can implement immediately.",
            
            'savings' => "Create a customized savings strategy for a {archetype} personality. Consider their natural tendencies and provide specific techniques to help them save more effectively.",
            
            'investing' => "Design an investment approach suitable for a {archetype} personality with {risk_tolerance} risk tolerance. Include asset allocation suggestions and investment vehicles that align with their personality.",
            
            'debt' => "Develop a debt reduction plan for a {archetype} personality with {debt_amount} in debt. Consider their psychological relationship with money when suggesting strategies.",
            
            'budgeting' => "Create a budgeting system that works with, not against, a {archetype} personality's natural tendencies. Make it sustainable and psychologically comfortable."
        );
        
        return $templates[ $focus ] ?? $templates['general'];
    }
    
    /**
     * Get question generation template
     * 
     * @param string $category Question category
     * @return string
     */
    public static function get_question_template( $category = 'general' ) {
        $templates = array(
            'general' => "Generate money personality assessment questions that reveal financial attitudes and behaviors without being too direct or judgmental.",
            
            'spending' => "Create questions that assess spending habits and attitudes toward purchases without making respondents feel guilty or defensive.",
            
            'saving' => "Develop questions that explore saving behaviors and motivations, including both practical and emotional aspects of saving money.",
            
            'risk' => "Generate questions that evaluate financial risk tolerance and comfort with uncertainty in financial decisions.",
            
            'planning' => "Create questions about financial planning habits, goal-setting, and long-term thinking patterns.",
            
            'values' => "Develop questions that uncover underlying money values and beliefs shaped by upbringing and experiences."
        );
        
        return $templates[ $category ] ?? $templates['general'];
    }
    
    /**
     * Format template with variables
     * 
     * @param string $template Template string
     * @param array  $variables Variables to replace
     * @return string
     */
    public static function format_template( $template, array $variables ) {
        foreach ( $variables as $key => $value ) {
            $template = str_replace( '{' . $key . '}', $value, $template );
        }
        
        return $template;
    }
}
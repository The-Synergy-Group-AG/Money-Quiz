<?php
/**
 * Asset Optimizer
 *
 * @package MoneyQuiz
 * @since 4.0.0
 */

namespace MoneyQuiz\Frontend;

/**
 * Handles asset optimization and performance
 */
class AssetOptimizer {
    
    /**
     * @var string Plugin version
     */
    private string $version;
    
    /**
     * @var array Critical CSS
     */
    private array $critical_css = [];
    
    /**
     * Constructor
     * 
     * @param string $version Plugin version
     */
    public function __construct( string $version ) {
        $this->version = $version;
    }
    
    /**
     * Initialize optimizer
     * 
     * @return void
     */
    public function init(): void {
        // Optimize script loading
        add_filter( 'script_loader_tag', [ $this, 'add_async_defer_attributes' ], 10, 3 );
        
        // Optimize style loading
        add_filter( 'style_loader_tag', [ $this, 'optimize_style_loading' ], 10, 4 );
        
        // Add resource hints
        add_action( 'wp_head', [ $this, 'add_resource_hints' ], 1 );
        
        // Inline critical CSS
        add_action( 'wp_head', [ $this, 'inline_critical_css' ], 5 );
        
        // Add performance headers
        add_action( 'send_headers', [ $this, 'add_performance_headers' ] );
    }
    
    /**
     * Add async/defer attributes to scripts
     * 
     * @param string $tag    Script tag
     * @param string $handle Script handle
     * @param string $src    Script source
     * @return string
     */
    public function add_async_defer_attributes( string $tag, string $handle, string $src ): string {
        // Don't modify admin scripts
        if ( is_admin() ) {
            return $tag;
        }
        
        // Scripts that should be deferred
        $defer_scripts = [
            'money-quiz',
            'money-quiz-admin',
        ];
        
        // Scripts that should be async
        $async_scripts = [
            'money-quiz-analytics',
        ];
        
        if ( in_array( $handle, $defer_scripts ) ) {
            return str_replace( ' src', ' defer src', $tag );
        }
        
        if ( in_array( $handle, $async_scripts ) ) {
            return str_replace( ' src', ' async src', $tag );
        }
        
        return $tag;
    }
    
    /**
     * Optimize style loading
     * 
     * @param string $tag    Style tag
     * @param string $handle Style handle
     * @param string $href   Style URL
     * @param string $media  Media attribute
     * @return string
     */
    public function optimize_style_loading( string $tag, string $handle, string $href, string $media ): string {
        // Non-critical styles to load asynchronously
        $async_styles = [
            'money-quiz-animations',
            'money-quiz-themes',
        ];
        
        if ( in_array( $handle, $async_styles ) ) {
            $tag = sprintf(
                '<link rel="preload" href="%s" as="style" onload="this.onload=null;this.rel=\'stylesheet\'" />',
                esc_url( $href )
            );
            
            // Add noscript fallback
            $tag .= sprintf(
                '<noscript><link rel="stylesheet" href="%s" /></noscript>',
                esc_url( $href )
            );
        }
        
        return $tag;
    }
    
    /**
     * Add resource hints for performance
     * 
     * @return void
     */
    public function add_resource_hints(): void {
        // Only on pages with quiz
        if ( ! $this->is_quiz_page() ) {
            return;
        }
        
        // Preconnect to external domains
        $domains = [
            'https://fonts.googleapis.com',
            'https://fonts.gstatic.com',
        ];
        
        foreach ( $domains as $domain ) {
            printf(
                '<link rel="preconnect" href="%s" crossorigin />',
                esc_url( $domain )
            );
        }
        
        // Prefetch critical resources
        $prefetch_urls = [
            plugins_url( 'assets/js/money-quiz.min.js', MONEY_QUIZ_PLUGIN_FILE ),
            plugins_url( 'assets/css/money-quiz.min.css', MONEY_QUIZ_PLUGIN_FILE ),
        ];
        
        foreach ( $prefetch_urls as $url ) {
            printf(
                '<link rel="prefetch" href="%s" />',
                esc_url( $url )
            );
        }
    }
    
    /**
     * Inline critical CSS
     * 
     * @return void
     */
    public function inline_critical_css(): void {
        if ( ! $this->is_quiz_page() ) {
            return;
        }
        
        $critical_css = $this->get_critical_css();
        
        if ( ! empty( $critical_css ) ) {
            printf(
                '<style id="money-quiz-critical">%s</style>',
                $this->minify_css( $critical_css )
            );
        }
    }
    
    /**
     * Get critical CSS
     * 
     * @return string
     */
    private function get_critical_css(): string {
        // Critical CSS for above-the-fold content
        return '
            .money-quiz-container {
                max-width: 800px;
                margin: 0 auto;
                padding: 20px;
            }
            
            .money-quiz-question {
                margin-bottom: 30px;
            }
            
            .money-quiz-question h3 {
                margin-bottom: 15px;
                font-size: 1.4em;
            }
            
            .money-quiz-options {
                list-style: none;
                padding: 0;
            }
            
            .money-quiz-option {
                margin-bottom: 10px;
                padding: 15px;
                border: 2px solid #e0e0e0;
                border-radius: 8px;
                cursor: pointer;
                transition: all 0.3s ease;
            }
            
            .money-quiz-option:hover {
                border-color: #007cba;
                background-color: #f5f5f5;
            }
            
            .money-quiz-option.selected {
                border-color: #007cba;
                background-color: #e5f3ff;
            }
            
            .money-quiz-progress {
                height: 8px;
                background-color: #e0e0e0;
                border-radius: 4px;
                margin-bottom: 30px;
                overflow: hidden;
            }
            
            .money-quiz-progress-bar {
                height: 100%;
                background-color: #007cba;
                transition: width 0.3s ease;
            }
            
            @media (max-width: 768px) {
                .money-quiz-container {
                    padding: 10px;
                }
                
                .money-quiz-option {
                    padding: 12px;
                    font-size: 0.95em;
                }
            }
        ';
    }
    
    /**
     * Add performance headers
     * 
     * @return void
     */
    public function add_performance_headers(): void {
        if ( ! $this->is_quiz_page() ) {
            return;
        }
        
        // Browser caching for static assets
        header( 'Cache-Control: public, max-age=31536000' );
        
        // Enable compression
        if ( ! headers_sent() && ob_get_level() == 0 ) {
            ob_start( 'ob_gzhandler' );
        }
    }
    
    /**
     * Minify CSS
     * 
     * @param string $css CSS to minify
     * @return string
     */
    private function minify_css( string $css ): string {
        // Remove comments
        $css = preg_replace( '!/\*[^*]*\*+([^/][^*]*\*+)*/!', '', $css );
        
        // Remove unnecessary whitespace
        $css = str_replace( ["\r\n", "\r", "\n", "\t"], '', $css );
        $css = preg_replace( '/\s+/', ' ', $css );
        
        // Remove unnecessary spaces
        $css = str_replace( [' {', '{ ', ' }', '} ', ': ', ' :', '; ', ' ;'], ['{', '{', '}', '}', ':', ':', ';', ';'], $css );
        
        return trim( $css );
    }
    
    /**
     * Minify JavaScript
     * 
     * @param string $js JavaScript to minify
     * @return string
     */
    public function minify_js( string $js ): string {
        // Basic minification (for production use a proper minifier)
        $js = preg_replace( '/(?:(?:\/\*(?:[^*]|(?:\*+[^*\/]))*\*+\/)|(?:\/\/.*))/', '', $js );
        $js = str_replace( ["\r\n", "\r", "\n", "\t"], '', $js );
        $js = preg_replace( '/\s+/', ' ', $js );
        
        return trim( $js );
    }
    
    /**
     * Generate minified asset files
     * 
     * @return void
     */
    public function generate_minified_assets(): void {
        $assets_dir = MONEY_QUIZ_PLUGIN_DIR . '/assets';
        
        // Minify CSS files
        $css_files = [
            'css/money-quiz.css' => 'css/money-quiz.min.css',
            'css/money-quiz-admin.css' => 'css/money-quiz-admin.min.css',
        ];
        
        foreach ( $css_files as $source => $dest ) {
            $source_path = $assets_dir . '/' . $source;
            $dest_path = $assets_dir . '/' . $dest;
            
            if ( file_exists( $source_path ) ) {
                $css = file_get_contents( $source_path );
                $minified = $this->minify_css( $css );
                file_put_contents( $dest_path, $minified );
            }
        }
        
        // Minify JS files
        $js_files = [
            'js/money-quiz.js' => 'js/money-quiz.min.js',
            'js/money-quiz-admin.js' => 'js/money-quiz-admin.min.js',
        ];
        
        foreach ( $js_files as $source => $dest ) {
            $source_path = $assets_dir . '/' . $source;
            $dest_path = $assets_dir . '/' . $dest;
            
            if ( file_exists( $source_path ) ) {
                $js = file_get_contents( $source_path );
                $minified = $this->minify_js( $js );
                file_put_contents( $dest_path, $minified );
            }
        }
    }
    
    /**
     * Check if current page has quiz
     * 
     * @return bool
     */
    private function is_quiz_page(): bool {
        if ( is_admin() ) {
            return false;
        }
        
        // Check if page has quiz shortcode
        global $post;
        
        if ( ! $post ) {
            return false;
        }
        
        return has_shortcode( $post->post_content, 'money_quiz' ) 
            || has_shortcode( $post->post_content, 'mq_questions' );
    }
    
    /**
     * Get optimized asset URL
     * 
     * @param string $path Asset path
     * @param string $type Asset type (css/js)
     * @return string
     */
    public function get_asset_url( string $path, string $type = 'css' ): string {
        // Use minified version in production
        if ( ! defined( 'WP_DEBUG' ) || ! WP_DEBUG ) {
            $path = str_replace( ".{$type}", ".min.{$type}", $path );
        }
        
        $url = plugins_url( $path, MONEY_QUIZ_PLUGIN_FILE );
        
        // Add version for cache busting
        return add_query_arg( 'ver', $this->version, $url );
    }
}
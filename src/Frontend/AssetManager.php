<?php
/**
 * Asset Manager
 *
 * @package MoneyQuiz
 * @since 4.0.0
 */

namespace MoneyQuiz\Frontend;

/**
 * Manages frontend assets (CSS/JS)
 */
class AssetManager {
    
    /**
     * @var string Plugin version
     */
    private string $version;
    
    /**
     * @var bool Whether assets have been enqueued
     */
    private bool $assets_enqueued = false;
    
    /**
     * Constructor
     * 
     * @param string $version Plugin version
     */
    public function __construct( string $version = MONEY_QUIZ_VERSION ) {
        $this->version = $version;
    }
    
    /**
     * Enqueue frontend assets
     * 
     * @return void
     */
    public function enqueue_assets(): void {
        // Check if we should load assets on this page
        if ( ! $this->should_load_assets() ) {
            return;
        }
        
        // Don't enqueue twice
        if ( $this->assets_enqueued ) {
            return;
        }
        
        // Check if legacy assets are already enqueued
        if ( wp_script_is( 'money-quiz-script', 'enqueued' ) || 
             wp_script_is( 'moneyquiz-script', 'enqueued' ) ) {
            return;
        }
        
        $this->enqueue_styles();
        $this->enqueue_scripts();
        
        $this->assets_enqueued = true;
    }
    
    /**
     * Check if assets should be loaded on current page
     * 
     * @return bool
     */
    private function should_load_assets(): bool {
        // Always load in admin for previews
        if ( is_admin() ) {
            global $pagenow;
            // Load on post editor pages
            if ( in_array( $pagenow, [ 'post.php', 'post-new.php' ] ) ) {
                return true;
            }
            // Load on Money Quiz admin pages
            if ( isset( $_GET['page'] ) && strpos( $_GET['page'], 'money-quiz' ) !== false ) {
                return true;
            }
        }
        
        // Check if current post/page contains our shortcodes
        if ( is_singular() ) {
            global $post;
            if ( $post && $this->has_shortcode( $post->post_content ) ) {
                return true;
            }
        }
        
        // Check if it's a quiz page (custom post type or specific page)
        if ( is_page( 'money-quiz' ) || is_page( 'quiz' ) ) {
            return true;
        }
        
        // Allow filtering
        return apply_filters( 'money_quiz_load_assets', false );
    }
    
    /**
     * Check if content has our shortcode
     * 
     * @param string $content Content to check
     * @return bool
     */
    private function has_shortcode( string $content ): bool {
        $shortcodes = [ 'money_quiz', 'money-quiz', 'mq_questions' ];
        
        foreach ( $shortcodes as $shortcode ) {
            if ( has_shortcode( $content, $shortcode ) ) {
                return true;
            }
        }
        
        return false;
    }
    
    /**
     * Enqueue styles
     * 
     * @return void
     */
    private function enqueue_styles(): void {
        // Main plugin styles
        wp_enqueue_style(
            'money-quiz-style',
            MONEY_QUIZ_PLUGIN_URL . 'assets/css/money-quiz.css',
            [],
            $this->version
        );
        
        // Check for legacy styles
        $legacy_css_paths = [
            'css/money-quiz.css',
            'assets/money-quiz.css',
            'money-quiz.css',
        ];
        
        foreach ( $legacy_css_paths as $path ) {
            if ( file_exists( MONEY_QUIZ_PLUGIN_DIR . $path ) ) {
                wp_enqueue_style(
                    'money-quiz-legacy-style',
                    MONEY_QUIZ_PLUGIN_URL . $path,
                    [],
                    $this->version
                );
                break;
            }
        }
        
        // Inline styles for critical CSS
        $inline_css = $this->get_inline_styles();
        if ( $inline_css ) {
            wp_add_inline_style( 'money-quiz-style', $inline_css );
        }
    }
    
    /**
     * Enqueue scripts
     * 
     * @return void
     */
    private function enqueue_scripts(): void {
        // jQuery dependency
        wp_enqueue_script( 'jquery' );
        
        // Main plugin script
        wp_enqueue_script(
            'money-quiz-script',
            MONEY_QUIZ_PLUGIN_URL . 'assets/js/money-quiz.js',
            [ 'jquery' ],
            $this->version,
            true
        );
        
        // Check for legacy scripts
        $legacy_js_paths = [
            'js/money-quiz.js',
            'assets/money-quiz.js',
            'money-quiz.js',
        ];
        
        foreach ( $legacy_js_paths as $path ) {
            if ( file_exists( MONEY_QUIZ_PLUGIN_DIR . $path ) ) {
                wp_enqueue_script(
                    'money-quiz-legacy-script',
                    MONEY_QUIZ_PLUGIN_URL . $path,
                    [ 'jquery' ],
                    $this->version,
                    true
                );
                break;
            }
        }
        
        // Localize script with AJAX data
        $this->localize_script();
    }
    
    /**
     * Localize script with necessary data
     * 
     * @return void
     */
    private function localize_script(): void {
        $localization_data = [
            'ajax_url' => admin_url( 'admin-ajax.php' ),
            'nonce' => wp_create_nonce( 'money_quiz_submit' ),
            'plugin_url' => MONEY_QUIZ_PLUGIN_URL,
            'is_user_logged_in' => is_user_logged_in(),
            'messages' => [
                'error' => __( 'An error occurred. Please try again.', 'money-quiz' ),
                'required' => __( 'Please answer all questions.', 'money-quiz' ),
                'invalid_email' => __( 'Please enter a valid email address.', 'money-quiz' ),
                'submitting' => __( 'Submitting...', 'money-quiz' ),
                'loading' => __( 'Loading...', 'money-quiz' ),
                'complete' => __( 'Thank you! Your results have been submitted.', 'money-quiz' ),
            ],
            'settings' => [
                'animation_speed' => 300,
                'auto_scroll' => true,
                'show_progress' => true,
                'debug' => defined( 'WP_DEBUG' ) && WP_DEBUG,
            ],
        ];
        
        // Allow filtering of localization data
        $localization_data = apply_filters( 'money_quiz_localization', $localization_data );
        
        wp_localize_script( 'money-quiz-script', 'money_quiz_ajax', $localization_data );
        
        // Also localize for legacy scripts
        wp_localize_script( 'money-quiz-legacy-script', 'money_quiz_ajax', $localization_data );
    }
    
    /**
     * Get inline styles
     * 
     * @return string
     */
    private function get_inline_styles(): string {
        $styles = '';
        
        // Add custom colors from settings
        $primary_color = get_option( 'money_quiz_primary_color', '#007cba' );
        $secondary_color = get_option( 'money_quiz_secondary_color', '#555' );
        
        if ( $primary_color !== '#007cba' || $secondary_color !== '#555' ) {
            $styles .= sprintf(
                ':root { --mq-primary-color: %s; --mq-secondary-color: %s; }',
                esc_attr( $primary_color ),
                esc_attr( $secondary_color )
            );
        }
        
        return $styles;
    }
    
    /**
     * Preload critical assets
     * 
     * @return void
     */
    public function preload_assets(): void {
        if ( ! $this->should_load_assets() ) {
            return;
        }
        
        // Preload critical CSS
        printf(
            '<link rel="preload" href="%s" as="style">',
            esc_url( MONEY_QUIZ_PLUGIN_URL . 'assets/css/money-quiz.css?ver=' . $this->version )
        );
        
        // Preload critical fonts if any
        $this->preload_fonts();
    }
    
    /**
     * Preload fonts
     * 
     * @return void
     */
    private function preload_fonts(): void {
        // Add font preloading if custom fonts are used
        $fonts = apply_filters( 'money_quiz_preload_fonts', [] );
        
        foreach ( $fonts as $font ) {
            printf(
                '<link rel="preload" href="%s" as="font" type="font/%s" crossorigin>',
                esc_url( $font['url'] ),
                esc_attr( $font['type'] )
            );
        }
    }
}
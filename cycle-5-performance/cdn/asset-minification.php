<?php
/**
 * Money Quiz Plugin - Asset Minification
 * Worker 4: CSS/JS Minification Pipeline
 * 
 * Implements advanced asset minification with support for combining files,
 * tree shaking, and critical CSS extraction.
 * 
 * @package MoneyQuiz
 * @subpackage Performance\CDN
 * @since 5.0.0
 */

namespace MoneyQuiz\Performance\CDN;

/**
 * Asset Minification Class
 * 
 * Handles CSS and JavaScript minification and optimization
 */
class AssetMinification {
    
    /**
     * Minification configuration
     * 
     * @var array
     */
    protected $config = array(
        'enabled' => true,
        'minify_css' => true,
        'minify_js' => true,
        'combine_css' => true,
        'combine_js' => true,
        'inline_critical_css' => true,
        'defer_non_critical_css' => true,
        'async_js' => true,
        'remove_unused_css' => true,
        'tree_shake_js' => true,
        'source_maps' => false,
        'cache_dir' => 'cache/minified'
    );
    
    /**
     * Asset queue
     * 
     * @var array
     */
    protected $asset_queue = array(
        'css' => array(),
        'js' => array()
    );
    
    /**
     * Critical CSS
     * 
     * @var string
     */
    protected $critical_css = '';
    
    /**
     * Minified assets
     * 
     * @var array
     */
    protected $minified_assets = array();
    
    /**
     * Performance metrics
     * 
     * @var array
     */
    protected $metrics = array(
        'original_size' => 0,
        'minified_size' => 0,
        'files_processed' => 0,
        'files_combined' => 0,
        'critical_css_size' => 0
    );
    
    /**
     * CSS minifier instance
     * 
     * @var object
     */
    protected $css_minifier;
    
    /**
     * JS minifier instance
     * 
     * @var object
     */
    protected $js_minifier;
    
    /**
     * Constructor
     */
    public function __construct() {
        $this->load_config();
        $this->init();
    }
    
    /**
     * Initialize asset minification
     */
    protected function init() {
        if ( ! $this->config['enabled'] ) {
            return;
        }
        
        // Set up cache directory
        $this->setup_cache_directory();
        
        // Initialize minifiers
        $this->init_minifiers();
        
        // Register hooks
        $this->register_hooks();
    }
    
    /**
     * Load configuration
     */
    protected function load_config() {
        $saved_config = get_option( 'money_quiz_minification_config', array() );
        $this->config = wp_parse_args( $saved_config, $this->config );
    }
    
    /**
     * Setup cache directory
     */
    protected function setup_cache_directory() {
        $upload_dir = wp_upload_dir();
        $cache_path = $upload_dir['basedir'] . '/' . $this->config['cache_dir'];
        
        if ( ! file_exists( $cache_path ) ) {
            wp_mkdir_p( $cache_path );
            
            // Add .htaccess for security
            $htaccess = $cache_path . '/.htaccess';
            if ( ! file_exists( $htaccess ) ) {
                file_put_contents( $htaccess, "Order Allow,Deny\nAllow from all" );
            }
        }
        
        $this->cache_dir = $cache_path;
        $this->cache_url = $upload_dir['baseurl'] . '/' . $this->config['cache_dir'];
    }
    
    /**
     * Initialize minifiers
     */
    protected function init_minifiers() {
        // CSS minifier
        if ( $this->config['minify_css'] ) {
            $this->css_minifier = new CSSMinifier();
        }
        
        // JS minifier
        if ( $this->config['minify_js'] ) {
            $this->js_minifier = new JSMinifier();
        }
    }
    
    /**
     * Register hooks
     */
    protected function register_hooks() {
        // High priority to catch all enqueued assets
        add_action( 'wp_enqueue_scripts', array( $this, 'capture_assets' ), 999999 );
        add_action( 'admin_enqueue_scripts', array( $this, 'capture_assets' ), 999999 );
        
        // Process assets before output
        add_filter( 'style_loader_tag', array( $this, 'process_style_tag' ), 10, 4 );
        add_filter( 'script_loader_tag', array( $this, 'process_script_tag' ), 10, 3 );
        
        // Critical CSS
        if ( $this->config['inline_critical_css'] ) {
            add_action( 'wp_head', array( $this, 'inline_critical_css' ), 1 );
        }
        
        // Clean up old cached files
        add_action( 'money_quiz_cleanup_minified_cache', array( $this, 'cleanup_cache' ) );
        
        if ( ! wp_next_scheduled( 'money_quiz_cleanup_minified_cache' ) ) {
            wp_schedule_event( time(), 'daily', 'money_quiz_cleanup_minified_cache' );
        }
    }
    
    /**
     * Capture enqueued assets
     */
    public function capture_assets() {
        global $wp_styles, $wp_scripts;
        
        // Capture CSS
        if ( $this->config['minify_css'] && isset( $wp_styles->queue ) ) {
            foreach ( $wp_styles->queue as $handle ) {
                if ( isset( $wp_styles->registered[ $handle ] ) ) {
                    $this->capture_style( $handle, $wp_styles->registered[ $handle ] );
                }
            }
        }
        
        // Capture JS
        if ( $this->config['minify_js'] && isset( $wp_scripts->queue ) ) {
            foreach ( $wp_scripts->queue as $handle ) {
                if ( isset( $wp_scripts->registered[ $handle ] ) ) {
                    $this->capture_script( $handle, $wp_scripts->registered[ $handle ] );
                }
            }
        }
        
        // Process combining if enabled
        if ( $this->config['combine_css'] || $this->config['combine_js'] ) {
            $this->process_combining();
        }
    }
    
    /**
     * Capture style for processing
     * 
     * @param string $handle Style handle
     * @param object $style  Style object
     */
    protected function capture_style( $handle, $style ) {
        if ( empty( $style->src ) ) {
            return;
        }
        
        // Skip external styles
        if ( ! $this->is_local_asset( $style->src ) ) {
            return;
        }
        
        // Skip already minified files
        if ( $this->is_minified( $style->src ) ) {
            return;
        }
        
        $this->asset_queue['css'][ $handle ] = array(
            'src' => $style->src,
            'deps' => $style->deps,
            'ver' => $style->ver,
            'media' => $style->args,
            'path' => $this->get_asset_path( $style->src )
        );
    }
    
    /**
     * Capture script for processing
     * 
     * @param string $handle Script handle
     * @param object $script Script object
     */
    protected function capture_script( $handle, $script ) {
        if ( empty( $script->src ) ) {
            return;
        }
        
        // Skip external scripts
        if ( ! $this->is_local_asset( $script->src ) ) {
            return;
        }
        
        // Skip already minified files
        if ( $this->is_minified( $script->src ) ) {
            return;
        }
        
        $this->asset_queue['js'][ $handle ] = array(
            'src' => $script->src,
            'deps' => $script->deps,
            'ver' => $script->ver,
            'in_footer' => isset( $script->extra['group'] ) && $script->extra['group'] === 1,
            'data' => isset( $script->extra['data'] ) ? $script->extra['data'] : '',
            'path' => $this->get_asset_path( $script->src )
        );
    }
    
    /**
     * Process style tag
     * 
     * @param string $tag    HTML tag
     * @param string $handle Style handle
     * @param string $href   Style URL
     * @param string $media  Media attribute
     * @return string Modified tag
     */
    public function process_style_tag( $tag, $handle, $href, $media ) {
        // Skip if not in queue
        if ( ! isset( $this->asset_queue['css'][ $handle ] ) ) {
            return $tag;
        }
        
        // Get minified URL
        $minified_url = $this->get_minified_url( $handle, 'css' );
        
        if ( $minified_url ) {
            $tag = str_replace( $href, $minified_url, $tag );
            
            // Add preload for non-critical CSS
            if ( $this->config['defer_non_critical_css'] && ! $this->is_critical_css( $handle ) ) {
                $tag = $this->make_css_deferred( $tag, $minified_url );
            }
        }
        
        return $tag;
    }
    
    /**
     * Process script tag
     * 
     * @param string $tag    HTML tag
     * @param string $handle Script handle
     * @param string $src    Script URL
     * @return string Modified tag
     */
    public function process_script_tag( $tag, $handle, $src ) {
        // Skip if not in queue
        if ( ! isset( $this->asset_queue['js'][ $handle ] ) ) {
            return $tag;
        }
        
        // Get minified URL
        $minified_url = $this->get_minified_url( $handle, 'js' );
        
        if ( $minified_url ) {
            $tag = str_replace( $src, $minified_url, $tag );
            
            // Add async/defer attributes
            if ( $this->config['async_js'] && $this->can_async_script( $handle ) ) {
                $tag = $this->make_script_async( $tag );
            }
        }
        
        return $tag;
    }
    
    /**
     * Get minified URL for asset
     * 
     * @param string $handle Asset handle
     * @param string $type   Asset type (css/js)
     * @return string|false Minified URL or false
     */
    protected function get_minified_url( $handle, $type ) {
        // Check if already minified
        if ( isset( $this->minified_assets[ $handle ] ) ) {
            return $this->minified_assets[ $handle ];
        }
        
        $asset = $this->asset_queue[ $type ][ $handle ];
        $cache_key = $this->get_cache_key( $asset );
        $cache_file = $this->cache_dir . '/' . $cache_key . '.' . $type;
        
        // Check if cached minified version exists
        if ( file_exists( $cache_file ) && $this->is_cache_valid( $cache_file, $asset['path'] ) ) {
            $minified_url = $this->cache_url . '/' . $cache_key . '.' . $type;
            $this->minified_assets[ $handle ] = $minified_url;
            return $minified_url;
        }
        
        // Minify asset
        $minified_content = $this->minify_asset( $asset, $type );
        
        if ( $minified_content === false ) {
            return false;
        }
        
        // Save minified content
        file_put_contents( $cache_file, $minified_content );
        
        // Update metrics
        $this->metrics['files_processed']++;
        $this->metrics['original_size'] += filesize( $asset['path'] );
        $this->metrics['minified_size'] += strlen( $minified_content );
        
        $minified_url = $this->cache_url . '/' . $cache_key . '.' . $type;
        $this->minified_assets[ $handle ] = $minified_url;
        
        return $minified_url;
    }
    
    /**
     * Minify asset content
     * 
     * @param array  $asset Asset data
     * @param string $type  Asset type
     * @return string|false Minified content or false
     */
    protected function minify_asset( $asset, $type ) {
        if ( ! file_exists( $asset['path'] ) ) {
            return false;
        }
        
        $content = file_get_contents( $asset['path'] );
        
        if ( $type === 'css' ) {
            return $this->minify_css( $content, $asset );
        } elseif ( $type === 'js' ) {
            return $this->minify_js( $content, $asset );
        }
        
        return false;
    }
    
    /**
     * Minify CSS content
     * 
     * @param string $content CSS content
     * @param array  $asset   Asset data
     * @return string Minified CSS
     */
    protected function minify_css( $content, $asset ) {
        // Update relative URLs
        $content = $this->update_css_urls( $content, $asset );
        
        // Remove unused CSS if enabled
        if ( $this->config['remove_unused_css'] ) {
            $content = $this->remove_unused_css( $content );
        }
        
        // Minify
        $minified = $this->css_minifier->minify( $content );
        
        // Add source map if enabled
        if ( $this->config['source_maps'] ) {
            $minified .= "\n/*# sourceMappingURL=" . basename( $asset['src'] ) . ".map */";
        }
        
        return $minified;
    }
    
    /**
     * Minify JavaScript content
     * 
     * @param string $content JS content
     * @param array  $asset   Asset data
     * @return string Minified JS
     */
    protected function minify_js( $content, $asset ) {
        // Tree shake if enabled
        if ( $this->config['tree_shake_js'] ) {
            $content = $this->tree_shake_js( $content );
        }
        
        // Minify
        $minified = $this->js_minifier->minify( $content );
        
        // Add source map if enabled
        if ( $this->config['source_maps'] ) {
            $minified .= "\n//# sourceMappingURL=" . basename( $asset['src'] ) . ".map";
        }
        
        return $minified;
    }
    
    /**
     * Update CSS URLs to absolute paths
     * 
     * @param string $content CSS content
     * @param array  $asset   Asset data
     * @return string Updated CSS
     */
    protected function update_css_urls( $content, $asset ) {
        $base_url = dirname( $asset['src'] );
        
        // Update url() references
        $content = preg_replace_callback(
            '/url\s*\(\s*[\'"]?([^\'"\)]+)[\'"]?\s*\)/i',
            function( $matches ) use ( $base_url ) {
                $url = $matches[1];
                
                // Skip absolute URLs and data URIs
                if ( preg_match( '/^(https?:|data:)/i', $url ) ) {
                    return $matches[0];
                }
                
                // Convert relative to absolute
                if ( strpos( $url, '../' ) === 0 ) {
                    $url = $base_url . '/' . $url;
                    $url = $this->normalize_path( $url );
                } elseif ( strpos( $url, '/' ) !== 0 ) {
                    $url = $base_url . '/' . $url;
                }
                
                return 'url(' . $url . ')';
            },
            $content
        );
        
        return $content;
    }
    
    /**
     * Remove unused CSS
     * 
     * @param string $content CSS content
     * @return string Cleaned CSS
     */
    protected function remove_unused_css( $content ) {
        // This would integrate with tools like PurgeCSS
        // For now, we'll remove some common unused selectors
        
        $unused_patterns = array(
            '/\.(?:wp-admin|customize-support)[^{]*\{[^}]*\}/s',
            '/\#wpadminbar[^{]*\{[^}]*\}/s',
            '/\.admin-bar[^{]*\{[^}]*\}/s'
        );
        
        foreach ( $unused_patterns as $pattern ) {
            $content = preg_replace( $pattern, '', $content );
        }
        
        return $content;
    }
    
    /**
     * Tree shake JavaScript
     * 
     * @param string $content JS content
     * @return string Optimized JS
     */
    protected function tree_shake_js( $content ) {
        // This would integrate with tools like Rollup or Webpack
        // For now, we'll remove some common unused code patterns
        
        // Remove console statements in production
        if ( ! defined( 'WP_DEBUG' ) || ! WP_DEBUG ) {
            $content = preg_replace( '/console\.(log|debug|info|warn|error)\([^;]*\);?/s', '', $content );
        }
        
        return $content;
    }
    
    /**
     * Process asset combining
     */
    protected function process_combining() {
        // Combine CSS
        if ( $this->config['combine_css'] && count( $this->asset_queue['css'] ) > 1 ) {
            $this->combine_assets( 'css' );
        }
        
        // Combine JS
        if ( $this->config['combine_js'] && count( $this->asset_queue['js'] ) > 1 ) {
            $this->combine_assets( 'js' );
        }
    }
    
    /**
     * Combine assets of type
     * 
     * @param string $type Asset type
     */
    protected function combine_assets( $type ) {
        $groups = $this->group_assets_for_combining( $type );
        
        foreach ( $groups as $group_key => $handles ) {
            $combined_content = '';
            $combined_key = 'combined_' . $group_key;
            $cache_file = $this->cache_dir . '/' . $combined_key . '.' . $type;
            
            // Check if already combined
            if ( file_exists( $cache_file ) && $this->is_combined_cache_valid( $cache_file, $handles, $type ) ) {
                continue;
            }
            
            // Combine assets
            foreach ( $handles as $handle ) {
                $asset = $this->asset_queue[ $type ][ $handle ];
                $content = $this->get_minified_content( $handle, $type );
                
                if ( $content ) {
                    if ( $type === 'css' ) {
                        $combined_content .= "\n/* Asset: {$handle} */\n" . $content;
                    } else {
                        $combined_content .= "\n/* Asset: {$handle} */\n" . $content . ";\n";
                    }
                }
            }
            
            // Save combined file
            file_put_contents( $cache_file, $combined_content );
            
            // Update metrics
            $this->metrics['files_combined'] += count( $handles );
            
            // Update asset URLs
            $combined_url = $this->cache_url . '/' . $combined_key . '.' . $type;
            
            foreach ( $handles as $handle ) {
                $this->minified_assets[ $handle ] = $combined_url;
            }
        }
    }
    
    /**
     * Group assets for combining
     * 
     * @param string $type Asset type
     * @return array Grouped assets
     */
    protected function group_assets_for_combining( $type ) {
        $groups = array();
        
        if ( $type === 'css' ) {
            // Group by media type
            foreach ( $this->asset_queue['css'] as $handle => $asset ) {
                $media = $asset['media'] ?: 'all';
                $groups[ $media ][] = $handle;
            }
        } else {
            // Group by location (header/footer)
            foreach ( $this->asset_queue['js'] as $handle => $asset ) {
                $location = $asset['in_footer'] ? 'footer' : 'header';
                $groups[ $location ][] = $handle;
            }
        }
        
        return $groups;
    }
    
    /**
     * Get minified content
     * 
     * @param string $handle Asset handle
     * @param string $type   Asset type
     * @return string|false Minified content
     */
    protected function get_minified_content( $handle, $type ) {
        $asset = $this->asset_queue[ $type ][ $handle ];
        
        // Check if already minified
        $cache_key = $this->get_cache_key( $asset );
        $cache_file = $this->cache_dir . '/' . $cache_key . '.' . $type;
        
        if ( file_exists( $cache_file ) ) {
            return file_get_contents( $cache_file );
        }
        
        return $this->minify_asset( $asset, $type );
    }
    
    /**
     * Make CSS deferred
     * 
     * @param string $tag HTML tag
     * @param string $url CSS URL
     * @return string Modified tag
     */
    protected function make_css_deferred( $tag, $url ) {
        // Convert to preload with onload
        $preload = sprintf(
            '<link rel="preload" href="%s" as="style" onload="this.onload=null;this.rel=\'stylesheet\'">',
            esc_url( $url )
        );
        
        // Add noscript fallback
        $noscript = '<noscript>' . $tag . '</noscript>';
        
        return $preload . $noscript;
    }
    
    /**
     * Make script async
     * 
     * @param string $tag HTML tag
     * @return string Modified tag
     */
    protected function make_script_async( $tag ) {
        // Add async attribute if not present
        if ( strpos( $tag, 'async' ) === false && strpos( $tag, 'defer' ) === false ) {
            $tag = str_replace( '<script', '<script async', $tag );
        }
        
        return $tag;
    }
    
    /**
     * Check if asset is local
     * 
     * @param string $url Asset URL
     * @return bool
     */
    protected function is_local_asset( $url ) {
        $site_url = site_url();
        
        // Check if URL starts with site URL or is relative
        return strpos( $url, $site_url ) === 0 || strpos( $url, '/' ) === 0;
    }
    
    /**
     * Check if asset is already minified
     * 
     * @param string $url Asset URL
     * @return bool
     */
    protected function is_minified( $url ) {
        return strpos( $url, '.min.' ) !== false;
    }
    
    /**
     * Check if CSS is critical
     * 
     * @param string $handle CSS handle
     * @return bool
     */
    protected function is_critical_css( $handle ) {
        $critical_handles = array(
            'money-quiz-critical',
            'money-quiz-above-fold',
            'wp-block-library'
        );
        
        return in_array( $handle, $critical_handles );
    }
    
    /**
     * Check if script can be async
     * 
     * @param string $handle Script handle
     * @return bool
     */
    protected function can_async_script( $handle ) {
        // Scripts that should not be async
        $no_async = array(
            'jquery',
            'jquery-core',
            'jquery-migrate',
            'wp-hooks',
            'wp-i18n'
        );
        
        return ! in_array( $handle, $no_async );
    }
    
    /**
     * Get asset path from URL
     * 
     * @param string $url Asset URL
     * @return string File path
     */
    protected function get_asset_path( $url ) {
        // Remove query string
        $url = strtok( $url, '?' );
        
        // Convert URL to path
        $path = str_replace( site_url(), ABSPATH, $url );
        
        // Handle protocol-relative URLs
        if ( strpos( $url, '//' ) === 0 ) {
            $path = str_replace( '//' . $_SERVER['HTTP_HOST'], ABSPATH, $url );
        }
        
        return $path;
    }
    
    /**
     * Get cache key for asset
     * 
     * @param array $asset Asset data
     * @return string Cache key
     */
    protected function get_cache_key( $asset ) {
        $key_parts = array(
            pathinfo( $asset['src'], PATHINFO_FILENAME ),
            md5( $asset['src'] . $asset['ver'] ),
            filemtime( $asset['path'] )
        );
        
        return implode( '-', $key_parts );
    }
    
    /**
     * Check if cache is valid
     * 
     * @param string $cache_file Cached file path
     * @param string $source_file Source file path
     * @return bool
     */
    protected function is_cache_valid( $cache_file, $source_file ) {
        if ( ! file_exists( $cache_file ) || ! file_exists( $source_file ) ) {
            return false;
        }
        
        return filemtime( $cache_file ) > filemtime( $source_file );
    }
    
    /**
     * Check if combined cache is valid
     * 
     * @param string $cache_file Cached file path
     * @param array  $handles    Asset handles
     * @param string $type       Asset type
     * @return bool
     */
    protected function is_combined_cache_valid( $cache_file, $handles, $type ) {
        if ( ! file_exists( $cache_file ) ) {
            return false;
        }
        
        $cache_time = filemtime( $cache_file );
        
        foreach ( $handles as $handle ) {
            $asset = $this->asset_queue[ $type ][ $handle ];
            if ( filemtime( $asset['path'] ) > $cache_time ) {
                return false;
            }
        }
        
        return true;
    }
    
    /**
     * Normalize path
     * 
     * @param string $path Path to normalize
     * @return string Normalized path
     */
    protected function normalize_path( $path ) {
        $parts = explode( '/', $path );
        $normalized = array();
        
        foreach ( $parts as $part ) {
            if ( $part === '..' && count( $normalized ) > 0 ) {
                array_pop( $normalized );
            } elseif ( $part !== '.' && $part !== '' ) {
                $normalized[] = $part;
            }
        }
        
        return implode( '/', $normalized );
    }
    
    /**
     * Inline critical CSS
     */
    public function inline_critical_css() {
        // Extract critical CSS if not already done
        if ( empty( $this->critical_css ) ) {
            $this->extract_critical_css();
        }
        
        if ( ! empty( $this->critical_css ) ) {
            echo '<style id="money-quiz-critical-css">' . $this->critical_css . '</style>';
            $this->metrics['critical_css_size'] = strlen( $this->critical_css );
        }
    }
    
    /**
     * Extract critical CSS
     */
    protected function extract_critical_css() {
        // Load pre-generated critical CSS if available
        $critical_file = $this->cache_dir . '/critical.css';
        
        if ( file_exists( $critical_file ) ) {
            $this->critical_css = file_get_contents( $critical_file );
            return;
        }
        
        // Generate critical CSS for Money Quiz
        $critical_rules = array(
            // Reset and base styles
            'body { margin: 0; padding: 0; }',
            '*, *::before, *::after { box-sizing: border-box; }',
            
            // Money Quiz container
            '.money-quiz-container { max-width: 800px; margin: 0 auto; padding: 20px; }',
            '.money-quiz-question { margin-bottom: 30px; }',
            '.money-quiz-answer { margin-bottom: 15px; padding: 15px; border: 1px solid #ddd; cursor: pointer; }',
            '.money-quiz-answer:hover { background-color: #f5f5f5; }',
            '.money-quiz-answer.selected { background-color: #e3f2fd; border-color: #2196f3; }',
            
            // Progress bar
            '.money-quiz-progress { height: 20px; background-color: #f0f0f0; border-radius: 10px; overflow: hidden; }',
            '.money-quiz-progress-bar { height: 100%; background-color: #4caf50; transition: width 0.3s ease; }',
            
            // Buttons
            '.money-quiz-button { display: inline-block; padding: 10px 20px; background-color: #2196f3; color: white; text-decoration: none; border: none; border-radius: 4px; cursor: pointer; }',
            '.money-quiz-button:hover { background-color: #1976d2; }',
            '.money-quiz-button:disabled { opacity: 0.6; cursor: not-allowed; }',
            
            // Loading state
            '.money-quiz-loading { text-align: center; padding: 40px; }',
            '.money-quiz-spinner { display: inline-block; width: 40px; height: 40px; border: 4px solid #f3f3f3; border-top: 4px solid #3498db; border-radius: 50%; animation: spin 1s linear infinite; }',
            '@keyframes spin { 0% { transform: rotate(0deg); } 100% { transform: rotate(360deg); } }'
        );
        
        $this->critical_css = $this->css_minifier->minify( implode( "\n", $critical_rules ) );
        
        // Cache for future use
        file_put_contents( $critical_file, $this->critical_css );
    }
    
    /**
     * Clean up old cached files
     */
    public function cleanup_cache() {
        $max_age = 7 * DAY_IN_SECONDS; // 7 days
        $files = glob( $this->cache_dir . '/*' );
        
        foreach ( $files as $file ) {
            if ( is_file( $file ) && ( time() - filemtime( $file ) ) > $max_age ) {
                unlink( $file );
            }
        }
    }
    
    /**
     * Get minification statistics
     * 
     * @return array Statistics
     */
    public function get_stats() {
        $compression_ratio = $this->metrics['original_size'] > 0 
            ? ( 1 - ( $this->metrics['minified_size'] / $this->metrics['original_size'] ) ) * 100 
            : 0;
        
        return array(
            'files_processed' => $this->metrics['files_processed'],
            'files_combined' => $this->metrics['files_combined'],
            'original_size' => $this->metrics['original_size'],
            'minified_size' => $this->metrics['minified_size'],
            'compression_ratio' => round( $compression_ratio, 2 ),
            'critical_css_size' => $this->metrics['critical_css_size'],
            'space_saved' => $this->metrics['original_size'] - $this->metrics['minified_size']
        );
    }
}

/**
 * CSS Minifier Class
 */
class CSSMinifier {
    
    /**
     * Minify CSS
     * 
     * @param string $css CSS content
     * @return string Minified CSS
     */
    public function minify( $css ) {
        // Remove comments
        $css = preg_replace( '!/\*[^*]*\*+([^/][^*]*\*+)*/!', '', $css );
        
        // Remove unnecessary whitespace
        $css = preg_replace( '/\s+/', ' ', $css );
        
        // Remove whitespace around selectors
        $css = preg_replace( '/\s*([{}|:;,])\s+/', '$1', $css );
        $css = preg_replace( '/\s+([{}|:;,])/', '$1', $css );
        
        // Remove trailing semicolon
        $css = str_replace( ';}', '}', $css );
        
        // Remove empty rules
        $css = preg_replace( '/[^{}]+\{\s*\}/', '', $css );
        
        // Optimize common values
        $css = str_replace( array( ' 0px', ' 0em', ' 0rem', ' 0%' ), ' 0', $css );
        $css = preg_replace( '/(:| )0\.(\d+)/', '$1.$2', $css );
        
        // Optimize color values
        $css = preg_replace( '/#([a-f0-9])\1([a-f0-9])\2([a-f0-9])\3/i', '#$1$2$3', $css );
        
        return trim( $css );
    }
}

/**
 * JavaScript Minifier Class
 */
class JSMinifier {
    
    /**
     * Minify JavaScript
     * 
     * @param string $js JavaScript content
     * @return string Minified JS
     */
    public function minify( $js ) {
        // Remove single-line comments
        $js = preg_replace( '/(?:(?:\/\/(?:[^\n\r]*)))/m', '', $js );
        
        // Remove multi-line comments
        $js = preg_replace( '/\/\*(?:(?!\*\/)[\s\S])*\*\//', '', $js );
        
        // Remove unnecessary whitespace
        $js = preg_replace( '/\s+/', ' ', $js );
        
        // Remove whitespace around operators
        $js = preg_replace( '/\s*([=+\-*\/%&|^!~<>?:,;{}()\[\]])\s*/', '$1', $js );
        
        // Preserve space after keywords
        $keywords = array( 'var', 'let', 'const', 'function', 'return', 'if', 'else', 'for', 'while', 'do', 'switch', 'case', 'default', 'break', 'continue', 'new', 'typeof', 'instanceof', 'void', 'delete', 'throw', 'try', 'catch', 'finally' );
        
        foreach ( $keywords as $keyword ) {
            $js = preg_replace( '/\b' . $keyword . '([^a-zA-Z0-9_$])/', $keyword . ' $1', $js );
        }
        
        // Remove extra semicolons
        $js = preg_replace( '/;+/', ';', $js );
        $js = preg_replace( '/;}/', '}', $js );
        
        return trim( $js );
    }
}

// Initialize asset minification
global $money_quiz_minification;
$money_quiz_minification = new AssetMinification();
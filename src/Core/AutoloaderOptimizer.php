<?php
/**
 * Autoloader Optimizer
 *
 * @package MoneyQuiz
 * @since 4.0.0
 */

namespace MoneyQuiz\Core;

/**
 * Optimizes class autoloading for production
 */
class AutoloaderOptimizer {
    
    /**
     * @var array Class map cache
     */
    private static array $classmap = [];
    
    /**
     * @var bool Whether optimizer is enabled
     */
    private static bool $enabled = false;
    
    /**
     * Initialize the optimizer
     * 
     * @return void
     */
    public static function init(): void {
        if ( self::$enabled ) {
            return;
        }
        
        // Check if we should use optimized autoloading
        if ( ! defined( 'WP_DEBUG' ) || ! WP_DEBUG ) {
            self::enable_optimization();
        }
        
        // Register shutdown function to save classmap
        register_shutdown_function( [ __CLASS__, 'save_classmap' ] );
        
        self::$enabled = true;
    }
    
    /**
     * Enable autoloader optimization
     * 
     * @return void
     */
    private static function enable_optimization(): void {
        // Load cached classmap
        self::load_classmap();
        
        // Prepend optimized autoloader
        spl_autoload_register( [ __CLASS__, 'optimized_autoload' ], true, true );
    }
    
    /**
     * Optimized autoloader
     * 
     * @param string $class Class name
     * @return void
     */
    public static function optimized_autoload( string $class ): void {
        // Check if class is in our namespace
        if ( strpos( $class, 'MoneyQuiz\\' ) !== 0 ) {
            return;
        }
        
        // Check classmap first
        if ( isset( self::$classmap[$class] ) ) {
            $file = self::$classmap[$class];
            if ( file_exists( $file ) ) {
                require_once $file;
                return;
            }
        }
        
        // Fall back to standard PSR-4 loading
        $relative_class = substr( $class, 10 ); // Remove 'MoneyQuiz\'
        $file = MONEY_QUIZ_PLUGIN_DIR . 'src/' . str_replace( '\\', '/', $relative_class ) . '.php';
        
        if ( file_exists( $file ) ) {
            require_once $file;
            // Add to classmap for next time
            self::$classmap[$class] = $file;
        }
    }
    
    /**
     * Load classmap from cache
     * 
     * @return void
     */
    private static function load_classmap(): void {
        $cache_file = self::get_cache_file();
        
        if ( file_exists( $cache_file ) ) {
            $data = include $cache_file;
            if ( is_array( $data ) ) {
                self::$classmap = $data;
            }
        }
    }
    
    /**
     * Save classmap to cache
     * 
     * @return void
     */
    public static function save_classmap(): void {
        if ( empty( self::$classmap ) ) {
            return;
        }
        
        $cache_file = self::get_cache_file();
        $cache_dir = dirname( $cache_file );
        
        // Create cache directory if needed
        if ( ! file_exists( $cache_dir ) ) {
            wp_mkdir_p( $cache_dir );
        }
        
        // Write classmap
        $content = "<?php\n// Generated classmap - do not edit\nreturn " . var_export( self::$classmap, true ) . ";\n";
        file_put_contents( $cache_file, $content );
    }
    
    /**
     * Get cache file path
     * 
     * @return string
     */
    private static function get_cache_file(): string {
        $upload_dir = wp_upload_dir();
        return $upload_dir['basedir'] . '/money-quiz-cache/classmap.php';
    }
    
    /**
     * Generate full classmap for all classes
     * 
     * @return array
     */
    public static function generate_classmap(): array {
        $classmap = [];
        $src_dir = MONEY_QUIZ_PLUGIN_DIR . 'src/';
        
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator( $src_dir )
        );
        
        foreach ( $iterator as $file ) {
            if ( $file->isFile() && $file->getExtension() === 'php' ) {
                $relative_path = str_replace( $src_dir, '', $file->getPathname() );
                $class = 'MoneyQuiz\\' . str_replace( '/', '\\', substr( $relative_path, 0, -4 ) );
                $classmap[$class] = $file->getPathname();
            }
        }
        
        return $classmap;
    }
    
    /**
     * Warm up the classmap cache
     * 
     * @return void
     */
    public static function warmup(): void {
        self::$classmap = self::generate_classmap();
        self::save_classmap();
    }
    
    /**
     * Clear the classmap cache
     * 
     * @return void
     */
    public static function clear_cache(): void {
        $cache_file = self::get_cache_file();
        if ( file_exists( $cache_file ) ) {
            unlink( $cache_file );
        }
        self::$classmap = [];
    }
    
    /**
     * Check if APCu is available and should be used
     * 
     * @return bool
     */
    private static function should_use_apcu(): bool {
        return function_exists( 'apcu_enabled' ) && apcu_enabled() && ! wp_using_ext_object_cache();
    }
    
    /**
     * Get class file from APCu cache
     * 
     * @param string $class Class name
     * @return string|false
     */
    private static function get_from_apcu( string $class ) {
        if ( ! self::should_use_apcu() ) {
            return false;
        }
        
        $key = 'money_quiz_class_' . $class;
        return apcu_fetch( $key );
    }
    
    /**
     * Store class file in APCu cache
     * 
     * @param string $class Class name
     * @param string $file  File path
     * @return void
     */
    private static function store_in_apcu( string $class, string $file ): void {
        if ( ! self::should_use_apcu() ) {
            return;
        }
        
        $key = 'money_quiz_class_' . $class;
        apcu_store( $key, $file, 3600 ); // 1 hour TTL
    }
}
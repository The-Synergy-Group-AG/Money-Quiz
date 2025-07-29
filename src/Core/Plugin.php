<?php
/**
 * Main Plugin Class
 *
 * @package MoneyQuiz
 * @since 4.0.0
 */

namespace MoneyQuiz\Core;

use MoneyQuiz\Interfaces\PluginInterface;
use MoneyQuiz\Traits\SingletonTrait;

/**
 * Main plugin orchestrator class
 * 
 * This class follows the Facade pattern to provide a simple interface
 * to the complex plugin subsystem.
 */
final class Plugin implements PluginInterface {
    use SingletonTrait;
    
    /**
     * @var Container Dependency injection container
     */
    private Container $container;
    
    /**
     * @var string Plugin version
     */
    private string $version;
    
    /**
     * @var bool Plugin initialization status
     */
    private bool $initialized = false;
    
    /**
     * Initialize the plugin
     */
    protected function __construct() {
        $this->version = MONEY_QUIZ_VERSION;
        $this->container = new Container();
        $this->register_services();
    }
    
    /**
     * Run the plugin
     * 
     * @return void
     * @throws \RuntimeException If plugin is already initialized
     */
    public function run(): void {
        if ( $this->initialized ) {
            throw new \RuntimeException( 'Plugin is already initialized.' );
        }
        
        $this->load_dependencies();
        $this->set_locale();
        $this->define_admin_hooks();
        $this->define_frontend_hooks();
        $this->define_api_hooks();
        
        $this->initialized = true;
    }
    
    /**
     * Register services in the container
     * 
     * @return void
     */
    private function register_services(): void {
        // Core services
        $this->container->bind( 'loader', function( $c ) {
            return new Loader();
        });
        
        $this->container->bind( 'activator', function( $c ) {
            return new Activator( $c->get( 'database.migrator' ) );
        });
        
        $this->container->bind( 'deactivator', function( $c ) {
            return new Deactivator();
        });
        
        // Database services
        $this->container->bind( 'database.migrator', function( $c ) {
            return new \MoneyQuiz\Database\Migrator();
        });
        
        $this->container->bind( 'database.connection', function( $c ) {
            global $wpdb;
            return $wpdb;
        }, false ); // Not a singleton
        
        // Repository services
        $this->container->bind( 'repository.quiz', function( $c ) {
            return new \MoneyQuiz\Database\Repositories\QuizRepository( 
                $c->get( 'database.connection' ) 
            );
        });
        
        $this->container->bind( 'repository.archetype', function( $c ) {
            return new \MoneyQuiz\Database\Repositories\ArchetypeRepository( 
                $c->get( 'database.connection' ) 
            );
        });
        
        $this->container->bind( 'repository.prospect', function( $c ) {
            return new \MoneyQuiz\Database\Repositories\ProspectRepository( 
                $c->get( 'database.connection' ) 
            );
        });
        
        // Service layer - only register what exists
        $this->container->bind( 'service.cache', function( $c ) {
            return new \MoneyQuiz\Services\CacheService();
        });
        
        $this->container->bind( 'service.quiz', function( $c ) {
            return new \MoneyQuiz\Services\QuizService(
                $c->get( 'repository.quiz' ),
                $c->get( 'repository.archetype' ),
                $c->get( 'service.cache' )
            );
        });
        
        // Security services
        $this->container->bind( 'security.csrf', function( $c ) {
            return new \MoneyQuiz\Security\CsrfManager();
        });
        
        // Email service
        $this->container->bind( 'service.email', function( $c ) {
            return new \MoneyQuiz\Services\EmailService();
        });
        
        // Frontend services
        $this->container->bind( 'frontend.shortcode', function( $c ) {
            return new \MoneyQuiz\Frontend\ShortcodeManager(
                $c->get( 'service.quiz' ),
                $c->get( 'security.csrf' )
            );
        });
        
        $this->container->bind( 'frontend.assets', function( $c ) {
            return new \MoneyQuiz\Frontend\AssetManager( $this->version );
        });
        
        $this->container->bind( 'frontend.ajax', function( $c ) {
            return new \MoneyQuiz\Frontend\AjaxHandler(
                $c->get( 'service.quiz' ),
                $c->get( 'service.email' ),
                $c->get( 'security.csrf' )
            );
        });
        
        // Admin services
        $this->container->bind( 'admin.menu', function( $c ) {
            return new \MoneyQuiz\Admin\MenuManager();
        });
        
        $this->container->bind( 'admin.settings', function( $c ) {
            return new \MoneyQuiz\Admin\SettingsManager();
        });
    }
    
    /**
     * Load plugin dependencies
     * 
     * @return void
     */
    private function load_dependencies(): void {
        $loader = $this->container->get( 'loader' );
        
        // Activation/Deactivation hooks
        register_activation_hook( MONEY_QUIZ_PLUGIN_FILE, [ $this, 'activate' ] );
        register_deactivation_hook( MONEY_QUIZ_PLUGIN_FILE, [ $this, 'deactivate' ] );
        
        // Load text domain
        $loader->add_action( 'plugins_loaded', $this, 'load_textdomain' );
        
        // Register AJAX handlers (needs to be loaded globally)
        $ajax_handler = $this->container->get( 'frontend.ajax' );
        $loader->add_action( 'init', $ajax_handler, 'register_handlers' );
    }
    
    /**
     * Set plugin locale
     * 
     * @return void
     */
    private function set_locale(): void {
        $loader = $this->container->get( 'loader' );
        $i18n = new I18n();
        
        $loader->add_action( 'plugins_loaded', $i18n, 'load_plugin_textdomain' );
    }
    
    /**
     * Register admin hooks
     * 
     * @return void
     */
    private function define_admin_hooks(): void {
        if ( ! is_admin() ) {
            return;
        }
        
        $loader = $this->container->get( 'loader' );
        $menu_manager = $this->container->get( 'admin.menu' );
        $settings_manager = $this->container->get( 'admin.settings' );
        
        // Register admin menu
        $loader->add_action( 'admin_menu', $menu_manager, 'register_menus' );
        
        // Initialize settings
        $settings_manager->init();
    }
    
    /**
     * Register frontend hooks
     * 
     * @return void
     */
    private function define_frontend_hooks(): void {
        if ( is_admin() ) {
            return;
        }
        
        $loader = $this->container->get( 'loader' );
        $shortcode_manager = $this->container->get( 'frontend.shortcode' );
        $asset_manager = $this->container->get( 'frontend.assets' );
        
        // Register shortcodes
        $loader->add_action( 'init', $shortcode_manager, 'register_shortcodes' );
        
        // Enqueue assets
        $loader->add_action( 'wp_enqueue_scripts', $asset_manager, 'enqueue_assets' );
        
        // Preload critical assets
        $loader->add_action( 'wp_head', $asset_manager, 'preload_assets', 2 );
    }
    
    /**
     * Register API hooks
     * 
     * @return void
     */
    private function define_api_hooks(): void {
        // TODO: Implement API hooks when REST controller is created
        // For now, let the legacy code handle API functionality
    }
    
    /**
     * Load plugin text domain
     * 
     * @return void
     */
    public function load_textdomain(): void {
        load_plugin_textdomain(
            'money-quiz',
            false,
            dirname( plugin_basename( MONEY_QUIZ_PLUGIN_FILE ) ) . '/languages/'
        );
    }
    
    /**
     * Activate the plugin
     * 
     * @return void
     */
    public function activate(): void {
        $activator = $this->container->get( 'activator' );
        $activator->activate();
    }
    
    /**
     * Deactivate the plugin
     * 
     * @return void
     */
    public function deactivate(): void {
        $deactivator = $this->container->get( 'deactivator' );
        $deactivator->deactivate();
    }
    
    /**
     * Get the dependency injection container
     * 
     * @return Container
     */
    public function get_container(): Container {
        return $this->container;
    }
    
    /**
     * Get a service from the container
     * 
     * @param string $service Service identifier
     * @return mixed
     */
    public function get( string $service ) {
        return $this->container->get( $service );
    }
    
    /**
     * Get plugin version
     * 
     * @return string
     */
    public function get_version(): string {
        return $this->version;
    }
}
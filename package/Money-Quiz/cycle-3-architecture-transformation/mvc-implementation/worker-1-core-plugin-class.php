<?php
/**
 * Money Quiz Plugin - Core Plugin Class
 * Worker 1: MVC Implementation - Core Architecture
 * 
 * Implements main plugin class following MVC pattern with proper
 * separation of concerns and dependency injection.
 * 
 * @package MoneyQuiz
 * @subpackage Core
 * @since 4.0.0
 */

namespace MoneyQuiz\Core;

/**
 * Main Plugin Class
 * 
 * Coordinates all plugin functionality using MVC pattern
 */
class Plugin {
    
    /**
     * Plugin version
     * 
     * @var string
     */
    const VERSION = '4.0.0';
    
    /**
     * Plugin slug
     * 
     * @var string
     */
    const SLUG = 'money-quiz';
    
    /**
     * Loader instance
     * 
     * @var Loader
     */
    protected $loader;
    
    /**
     * Container instance for dependency injection
     * 
     * @var Container
     */
    protected $container;
    
    /**
     * Plugin path
     * 
     * @var string
     */
    protected $plugin_path;
    
    /**
     * Plugin URL
     * 
     * @var string
     */
    protected $plugin_url;
    
    /**
     * Initialize the plugin
     * 
     * @param string $plugin_file Main plugin file path
     */
    public function __construct( $plugin_file ) {
        $this->plugin_path = plugin_dir_path( $plugin_file );
        $this->plugin_url = plugin_dir_url( $plugin_file );
        
        $this->loader = new Loader();
        $this->container = new Container();
        
        $this->register_services();
        $this->define_admin_hooks();
        $this->define_public_hooks();
        $this->define_api_hooks();
    }
    
    /**
     * Register all services in the container
     */
    protected function register_services() {
        // Register core services
        $this->container->register( 'database', function() {
            return new \MoneyQuiz\Services\DatabaseService();
        });
        
        $this->container->register( 'validation', function() {
            return new \MoneyQuiz\Services\ValidationService();
        });
        
        $this->container->register( 'email', function() {
            return new \MoneyQuiz\Services\EmailService(
                $this->container->get( 'validation' )
            );
        });
        
        $this->container->register( 'quiz', function() {
            return new \MoneyQuiz\Services\QuizService(
                $this->container->get( 'database' ),
                $this->container->get( 'validation' )
            );
        });
        
        // Register controllers
        $this->container->register( 'admin_controller', function() {
            return new \MoneyQuiz\Controllers\AdminController(
                $this->container->get( 'quiz' ),
                $this->container->get( 'database' )
            );
        });
        
        $this->container->register( 'quiz_controller', function() {
            return new \MoneyQuiz\Controllers\QuizController(
                $this->container->get( 'quiz' ),
                $this->container->get( 'email' ),
                $this->container->get( 'validation' )
            );
        });
        
        $this->container->register( 'api_controller', function() {
            return new \MoneyQuiz\Controllers\ApiController(
                $this->container->get( 'quiz' ),
                $this->container->get( 'validation' )
            );
        });
    }
    
    /**
     * Register admin hooks
     */
    protected function define_admin_hooks() {
        $admin = $this->container->get( 'admin_controller' );
        
        // Admin menu
        $this->loader->add_action( 'admin_menu', $admin, 'add_admin_menu' );
        
        // Admin scripts and styles
        $this->loader->add_action( 'admin_enqueue_scripts', $admin, 'enqueue_admin_assets' );
        
        // AJAX handlers with proper nonce verification
        $this->loader->add_action( 'wp_ajax_mq_save_question', $admin, 'ajax_save_question' );
        $this->loader->add_action( 'wp_ajax_mq_delete_question', $admin, 'ajax_delete_question' );
        $this->loader->add_action( 'wp_ajax_mq_export_data', $admin, 'ajax_export_data' );
        
        // Admin notices
        $this->loader->add_action( 'admin_notices', $admin, 'display_admin_notices' );
    }
    
    /**
     * Register public hooks
     */
    protected function define_public_hooks() {
        $quiz = $this->container->get( 'quiz_controller' );
        
        // Shortcodes
        $this->loader->add_shortcode( 'money_quiz', $quiz, 'render_quiz_shortcode' );
        $this->loader->add_shortcode( 'money_quiz_results', $quiz, 'render_results_shortcode' );
        
        // Public scripts and styles
        $this->loader->add_action( 'wp_enqueue_scripts', $quiz, 'enqueue_public_assets' );
        
        // AJAX handlers for quiz
        $this->loader->add_action( 'wp_ajax_mq_submit_quiz', $quiz, 'ajax_submit_quiz' );
        $this->loader->add_action( 'wp_ajax_nopriv_mq_submit_quiz', $quiz, 'ajax_submit_quiz' );
        
        // Form processing
        $this->loader->add_action( 'init', $quiz, 'process_quiz_form' );
    }
    
    /**
     * Register API hooks
     */
    protected function define_api_hooks() {
        $api = $this->container->get( 'api_controller' );
        
        // REST API endpoints
        $this->loader->add_action( 'rest_api_init', $api, 'register_rest_routes' );
    }
    
    /**
     * Run the plugin
     */
    public function run() {
        $this->loader->run();
    }
    
    /**
     * Get plugin version
     * 
     * @return string
     */
    public function get_version() {
        return self::VERSION;
    }
    
    /**
     * Get plugin path
     * 
     * @return string
     */
    public function get_plugin_path() {
        return $this->plugin_path;
    }
    
    /**
     * Get plugin URL
     * 
     * @return string
     */
    public function get_plugin_url() {
        return $this->plugin_url;
    }
    
    /**
     * Get container instance
     * 
     * @return Container
     */
    public function get_container() {
        return $this->container;
    }
}

/**
 * Loader Class
 * 
 * Manages all hooks and filters
 */
class Loader {
    
    /**
     * Array of actions
     * 
     * @var array
     */
    protected $actions = array();
    
    /**
     * Array of filters
     * 
     * @var array
     */
    protected $filters = array();
    
    /**
     * Array of shortcodes
     * 
     * @var array
     */
    protected $shortcodes = array();
    
    /**
     * Add action
     * 
     * @param string $hook
     * @param object $component
     * @param string $callback
     * @param int    $priority
     * @param int    $accepted_args
     */
    public function add_action( $hook, $component, $callback, $priority = 10, $accepted_args = 1 ) {
        $this->actions = $this->add( $this->actions, $hook, $component, $callback, $priority, $accepted_args );
    }
    
    /**
     * Add filter
     * 
     * @param string $hook
     * @param object $component
     * @param string $callback
     * @param int    $priority
     * @param int    $accepted_args
     */
    public function add_filter( $hook, $component, $callback, $priority = 10, $accepted_args = 1 ) {
        $this->filters = $this->add( $this->filters, $hook, $component, $callback, $priority, $accepted_args );
    }
    
    /**
     * Add shortcode
     * 
     * @param string $tag
     * @param object $component
     * @param string $callback
     */
    public function add_shortcode( $tag, $component, $callback ) {
        $this->shortcodes[] = array(
            'tag' => $tag,
            'component' => $component,
            'callback' => $callback
        );
    }
    
    /**
     * Add hook to collection
     * 
     * @param array  $hooks
     * @param string $hook
     * @param object $component
     * @param string $callback
     * @param int    $priority
     * @param int    $accepted_args
     * @return array
     */
    private function add( $hooks, $hook, $component, $callback, $priority, $accepted_args ) {
        $hooks[] = array(
            'hook' => $hook,
            'component' => $component,
            'callback' => $callback,
            'priority' => $priority,
            'accepted_args' => $accepted_args
        );
        
        return $hooks;
    }
    
    /**
     * Register all hooks
     */
    public function run() {
        // Register filters
        foreach ( $this->filters as $hook ) {
            add_filter( 
                $hook['hook'], 
                array( $hook['component'], $hook['callback'] ), 
                $hook['priority'], 
                $hook['accepted_args'] 
            );
        }
        
        // Register actions
        foreach ( $this->actions as $hook ) {
            add_action( 
                $hook['hook'], 
                array( $hook['component'], $hook['callback'] ), 
                $hook['priority'], 
                $hook['accepted_args'] 
            );
        }
        
        // Register shortcodes
        foreach ( $this->shortcodes as $shortcode ) {
            add_shortcode(
                $shortcode['tag'],
                array( $shortcode['component'], $shortcode['callback'] )
            );
        }
    }
}

/**
 * Container Class
 * 
 * Simple dependency injection container
 */
class Container {
    
    /**
     * Registered services
     * 
     * @var array
     */
    protected $services = array();
    
    /**
     * Resolved instances
     * 
     * @var array
     */
    protected $instances = array();
    
    /**
     * Register a service
     * 
     * @param string   $name
     * @param callable $resolver
     */
    public function register( $name, $resolver ) {
        $this->services[ $name ] = $resolver;
    }
    
    /**
     * Get a service instance
     * 
     * @param string $name
     * @return mixed
     * @throws \Exception
     */
    public function get( $name ) {
        if ( ! isset( $this->services[ $name ] ) ) {
            throw new \Exception( "Service {$name} not found in container" );
        }
        
        if ( ! isset( $this->instances[ $name ] ) ) {
            $resolver = $this->services[ $name ];
            $this->instances[ $name ] = $resolver();
        }
        
        return $this->instances[ $name ];
    }
    
    /**
     * Check if service exists
     * 
     * @param string $name
     * @return bool
     */
    public function has( $name ) {
        return isset( $this->services[ $name ] );
    }
}
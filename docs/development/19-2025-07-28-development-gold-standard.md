# WordPress Plugin Development Gold Standard (2025)

This comprehensive guide represents the gold standard for professional WordPress plugin development, combining security, performance, modern PHP practices, and WordPress coding standards.

## Table of Contents
1. [Foundation Requirements](#foundation-requirements)
2. [Project Structure](#project-structure)
3. [Security Standards](#security-standards)
4. [Coding Standards](#coding-standards)
5. [Modern PHP Integration](#modern-php-integration)
6. [Database Operations](#database-operations)
7. [Performance Optimization](#performance-optimization)
8. [Testing and Quality Assurance](#testing-and-quality-assurance)
9. [Deployment and Maintenance](#deployment-and-maintenance)
10. [Critical Checklist](#critical-checklist)

## Foundation Requirements

### PHP Version Support
- **Minimum**: PHP 7.4 (for modern features while maintaining compatibility)
- **Recommended**: PHP 8.1+ (for optimal performance and features)
- **WordPress**: 5.8+ (for modern block editor support)

### Essential Setup Files

#### composer.json
```json
{
    "name": "vendor/plugin-name",
    "description": "Professional WordPress Plugin",
    "type": "wordpress-plugin",
    "license": "GPL-2.0-or-later",
    "minimum-stability": "stable",
    "require": {
        "php": ">=7.4",
        "composer/installers": "^2.0"
    },
    "require-dev": {
        "phpunit/phpunit": "^9.5",
        "mockery/mockery": "^1.5",
        "brain/monkey": "^2.6",
        "squizlabs/php_codesniffer": "^3.7",
        "wp-coding-standards/wpcs": "^3.0",
        "phpstan/phpstan": "^1.10",
        "phpcompatibility/php-compatibility": "^9.3"
    },
    "autoload": {
        "psr-4": {
            "VendorName\\PluginName\\": "src/"
        }
    },
    "autoload-dev": {
        "psr-4": {
            "VendorName\\PluginName\\Tests\\": "tests/"
        }
    },
    "scripts": {
        "test": "phpunit",
        "phpcs": "phpcs",
        "phpstan": "phpstan analyse",
        "lint": ["@phpcs", "@phpstan"],
        "fix": "phpcbf"
    },
    "config": {
        "allow-plugins": {
            "composer/installers": true,
            "dealerdirect/phpcodesniffer-composer-installer": true
        }
    }
}
```

#### Main Plugin File
```php
<?php
/**
 * Plugin Name:       My Professional Plugin
 * Plugin URI:        https://example.com/plugin
 * Description:       A professional WordPress plugin following best practices
 * Version:           1.0.0
 * Requires at least: 5.8
 * Requires PHP:      7.4
 * Author:            Your Name
 * Author URI:        https://example.com
 * License:           GPL v2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       plugin-textdomain
 * Domain Path:       /languages
 */

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// Define plugin constants
define( 'MY_PLUGIN_VERSION', '1.0.0' );
define( 'MY_PLUGIN_FILE', __FILE__ );
define( 'MY_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'MY_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

// Load Composer autoloader with error handling
$autoloader = MY_PLUGIN_DIR . 'vendor/autoload.php';
if ( ! file_exists( $autoloader ) ) {
    add_action( 'admin_notices', function() {
        printf(
            '<div class="notice notice-error"><p>%s</p></div>',
            esc_html__( 'Plugin Name: Composer dependencies not installed. Please run "composer install".', 'plugin-textdomain' )
        );
    } );
    return;
}
require_once $autoloader;

// Initialize plugin with comprehensive error handling
try {
    $plugin = \VendorName\PluginName\Core\Plugin::instance();
    $plugin->run();
} catch ( \Exception $e ) {
    if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
        error_log( sprintf( 'Plugin Name Error: %s in %s:%d', $e->getMessage(), $e->getFile(), $e->getLine() ) );
    }
    add_action( 'admin_notices', function() {
        printf(
            '<div class="notice notice-error"><p>%s</p></div>',
            esc_html__( 'Plugin failed to initialize. Please check the error logs.', 'plugin-textdomain' )
        );
    } );
}
```

## Project Structure

```
plugin-name/
├── plugin-name.php              # Main plugin file (minimal bootstrapping)
├── composer.json                # Composer configuration
├── phpcs.xml                    # PHP CodeSniffer configuration
├── phpstan.neon                 # PHPStan configuration
├── phpunit.xml                  # PHPUnit configuration
├── uninstall.php               # Clean uninstallation
├── .gitignore                  # Git ignore file
├── README.md                   # Documentation
├── src/                        # Source code (PSR-4 autoloaded)
│   ├── Core/
│   │   ├── Plugin.php          # Main plugin class (singleton)
│   │   ├── Container.php       # Dependency injection container
│   │   └── ErrorHandler.php    # Centralized error handling
│   ├── Admin/
│   │   ├── AdminManager.php    # Admin functionality coordinator
│   │   ├── Settings.php        # Settings page handler
│   │   └── Ajax/               # AJAX handlers
│   ├── Frontend/
│   │   ├── FrontendManager.php # Frontend functionality
│   │   └── Shortcodes/         # Shortcode implementations
│   ├── Api/
│   │   └── RestController.php  # REST API endpoints
│   ├── Database/
│   │   ├── Migrations.php      # Database versioning
│   │   └── Repositories/       # Data access layer
│   ├── Services/               # Business logic
│   ├── Models/                 # Data models
│   └── Helpers/                # Utility functions
├── assets/                     # Static assets
│   ├── css/
│   ├── js/
│   └── images/
├── templates/                  # PHP templates
├── languages/                  # Translation files
├── tests/                      # Test suite
│   ├── Unit/
│   ├── Integration/
│   └── bootstrap.php
└── vendor/                     # Composer dependencies (git-ignored)
```

## Security Standards

### Input Validation and Sanitization

```php
namespace VendorName\PluginName\Services;

class DataProcessor {
    
    /**
     * Process form submission with comprehensive validation
     */
    public function process_form_submission( array $data ): \WP_Error|array {
        // Verify nonce first
        if ( ! isset( $data['_wpnonce'] ) || ! wp_verify_nonce( $data['_wpnonce'], 'process_form' ) ) {
            return new \WP_Error( 'invalid_nonce', __( 'Security verification failed.', 'plugin-textdomain' ) );
        }
        
        // Check capabilities
        if ( ! current_user_can( 'edit_posts' ) ) {
            return new \WP_Error( 'insufficient_permissions', __( 'You do not have permission to perform this action.', 'plugin-textdomain' ) );
        }
        
        // Sanitize and validate data
        $clean_data = array();
        
        // Text field
        $clean_data['name'] = isset( $data['name'] ) ? sanitize_text_field( $data['name'] ) : '';
        if ( empty( $clean_data['name'] ) ) {
            return new \WP_Error( 'missing_name', __( 'Name is required.', 'plugin-textdomain' ) );
        }
        
        // Email field
        $clean_data['email'] = isset( $data['email'] ) ? sanitize_email( $data['email'] ) : '';
        if ( ! is_email( $clean_data['email'] ) ) {
            return new \WP_Error( 'invalid_email', __( 'Please provide a valid email address.', 'plugin-textdomain' ) );
        }
        
        // URL field
        $clean_data['website'] = isset( $data['website'] ) ? esc_url_raw( $data['website'] ) : '';
        
        // Integer field
        $clean_data['age'] = isset( $data['age'] ) ? absint( $data['age'] ) : 0;
        if ( $clean_data['age'] < 18 || $clean_data['age'] > 120 ) {
            return new \WP_Error( 'invalid_age', __( 'Age must be between 18 and 120.', 'plugin-textdomain' ) );
        }
        
        // Textarea with allowed HTML
        $allowed_html = array(
            'a' => array( 'href' => array(), 'title' => array() ),
            'br' => array(),
            'em' => array(),
            'strong' => array(),
            'p' => array(),
        );
        $clean_data['bio'] = isset( $data['bio'] ) ? wp_kses( $data['bio'], $allowed_html ) : '';
        
        // Select field with whitelist validation
        $valid_options = array( 'option1', 'option2', 'option3' );
        $clean_data['option'] = isset( $data['option'] ) && in_array( $data['option'], $valid_options, true ) 
            ? $data['option'] 
            : 'option1';
        
        return $clean_data;
    }
}
```

### Output Escaping

```php
namespace VendorName\PluginName\Frontend;

class TemplateRenderer {
    
    /**
     * Render user profile with proper escaping
     */
    public function render_profile( array $user_data ): void {
        ?>
        <div class="user-profile">
            <h2><?php echo esc_html( $user_data['name'] ); ?></h2>
            <p class="bio"><?php echo wp_kses_post( $user_data['bio'] ); ?></p>
            <a href="<?php echo esc_url( $user_data['website'] ); ?>" 
               title="<?php echo esc_attr( sprintf( __( 'Visit %s\'s website', 'plugin-textdomain' ), $user_data['name'] ) ); ?>">
                <?php esc_html_e( 'Visit Website', 'plugin-textdomain' ); ?>
            </a>
            <script>
                var userData = <?php echo wp_json_encode( $user_data ); ?>;
            </script>
        </div>
        <?php
    }
}
```

### Database Security

```php
namespace VendorName\PluginName\Database\Repositories;

class UserRepository {
    
    private \wpdb $db;
    private string $table;
    
    public function __construct() {
        global $wpdb;
        $this->db = $wpdb;
        $this->table = $wpdb->prefix . 'plugin_users';
    }
    
    /**
     * Get users with proper prepared statements
     */
    public function get_users_by_status( string $status, int $limit = 10 ): array {
        $query = $this->db->prepare(
            "SELECT * FROM {$this->table} 
             WHERE status = %s 
             ORDER BY created_at DESC 
             LIMIT %d",
            $status,
            $limit
        );
        
        return $this->db->get_results( $query, ARRAY_A );
    }
    
    /**
     * Insert user with proper sanitization
     */
    public function insert_user( array $data ): int|\WP_Error {
        $result = $this->db->insert(
            $this->table,
            array(
                'name' => $data['name'],
                'email' => $data['email'],
                'status' => $data['status'],
                'created_at' => current_time( 'mysql' ),
            ),
            array( '%s', '%s', '%s', '%s' )
        );
        
        if ( false === $result ) {
            return new \WP_Error( 'db_insert_error', $this->db->last_error );
        }
        
        return $this->db->insert_id;
    }
}
```

## Coding Standards

### WordPress PHP Standards

```php
namespace VendorName\PluginName\Services;

/**
 * Example service following WordPress coding standards
 */
class ExampleService {
    
    /**
     * Process data following WordPress naming conventions
     *
     * @param array $input_data The data to process.
     * @return array|\WP_Error Processed data or error.
     */
    public function process_data( array $input_data ) {
        // Use Yoda conditions
        if ( true === $input_data['enabled'] ) {
            // Use real tabs for indentation
            $processed_data = array();
            
            // Space usage around operators and after commas
            foreach ( $input_data['items'] as $key => $value ) {
                // Always use braces, even for single-line statements
                if ( ! empty( $value ) ) {
                    $processed_data[ $key ] = $this->transform_value( $value );
                }
            }
            
            // Use long array syntax for WordPress core compatibility
            return array(
                'status' => 'success',
                'data'   => $processed_data,
            );
        }
        
        return new \WP_Error( 
            'processing_disabled', 
            __( 'Data processing is disabled.', 'plugin-textdomain' ) 
        );
    }
    
    /**
     * Transform a single value
     *
     * @param mixed $value The value to transform.
     * @return mixed The transformed value.
     */
    private function transform_value( $value ) {
        // Type checking with strict comparison
        if ( is_string( $value ) ) {
            return sanitize_text_field( $value );
        } elseif ( is_array( $value ) ) {
            return array_map( array( $this, 'transform_value' ), $value );
        }
        
        return $value;
    }
}
```

### Modern PHP with WordPress Compatibility

```php
namespace VendorName\PluginName\Core;

/**
 * Service container with modern PHP features
 */
class Container {
    
    /**
     * @var array<string, object> Resolved services
     */
    private array $services = array();
    
    /**
     * @var array<string, callable> Service factories
     */
    private array $factories = array();
    
    /**
     * @var array<string, bool> Singleton flags
     */
    private array $singletons = array();
    
    /**
     * Register a service
     *
     * @param string   $id       Service identifier.
     * @param callable $factory  Service factory.
     * @param bool     $singleton Whether to create singleton.
     * @return void
     */
    public function bind( string $id, callable $factory, bool $singleton = true ): void {
        $this->factories[ $id ] = $factory;
        $this->singletons[ $id ] = $singleton;
    }
    
    /**
     * Get a service
     *
     * @template T
     * @param string $id Service identifier.
     * @param class-string<T>|null $className Expected class name for type hinting.
     * @return T|object
     * @throws \RuntimeException If service not found.
     */
    public function get( string $id, ?string $className = null ) {
        if ( ! isset( $this->factories[ $id ] ) ) {
            throw new \RuntimeException( sprintf( 'Service "%s" not found.', $id ) );
        }
        
        // Return existing singleton instance
        if ( isset( $this->services[ $id ] ) && $this->singletons[ $id ] ) {
            return $this->services[ $id ];
        }
        
        // Create new instance
        $service = call_user_func( $this->factories[ $id ], $this );
        
        // Store singleton instances
        if ( $this->singletons[ $id ] ) {
            $this->services[ $id ] = $service;
        }
        
        return $service;
    }
}
```

## Modern PHP Integration

### Error Handling

```php
namespace VendorName\PluginName\Core;

/**
 * Centralized error handler
 */
class ErrorHandler {
    
    /**
     * @var bool Whether debug mode is enabled
     */
    private bool $debug;
    
    /**
     * @var string Log file path
     */
    private string $log_file;
    
    public function __construct() {
        $this->debug = defined( 'WP_DEBUG' ) && WP_DEBUG;
        $this->log_file = WP_CONTENT_DIR . '/debug.log';
    }
    
    /**
     * Register error handlers
     */
    public function register(): void {
        set_error_handler( array( $this, 'handle_error' ) );
        set_exception_handler( array( $this, 'handle_exception' ) );
        register_shutdown_function( array( $this, 'handle_shutdown' ) );
    }
    
    /**
     * Handle PHP errors
     */
    public function handle_error( int $errno, string $errstr, string $errfile, int $errline ): bool {
        // Don't handle suppressed errors
        if ( ! ( error_reporting() & $errno ) ) {
            return false;
        }
        
        // Convert to exception
        throw new \ErrorException( $errstr, 0, $errno, $errfile, $errline );
    }
    
    /**
     * Handle uncaught exceptions
     */
    public function handle_exception( \Throwable $exception ): void {
        $this->log_error( $exception );
        
        if ( $this->debug ) {
            $this->display_error( $exception );
        } else {
            $this->display_generic_error();
        }
    }
    
    /**
     * Handle fatal errors
     */
    public function handle_shutdown(): void {
        $error = error_get_last();
        
        if ( null !== $error && in_array( $error['type'], array( E_ERROR, E_CORE_ERROR, E_COMPILE_ERROR, E_PARSE ), true ) ) {
            $exception = new \ErrorException( $error['message'], 0, $error['type'], $error['file'], $error['line'] );
            $this->handle_exception( $exception );
        }
    }
    
    /**
     * Log error details
     */
    private function log_error( \Throwable $exception ): void {
        $message = sprintf(
            "[%s] %s: %s in %s:%d\nStack trace:\n%s\n",
            current_time( 'mysql' ),
            get_class( $exception ),
            $exception->getMessage(),
            $exception->getFile(),
            $exception->getLine(),
            $exception->getTraceAsString()
        );
        
        error_log( $message, 3, $this->log_file );
    }
    
    /**
     * Safe file loading with error handling
     */
    public static function safe_require( string $file ): bool {
        if ( ! file_exists( $file ) ) {
            error_log( sprintf( 'Plugin Name: Required file not found: %s', $file ) );
            return false;
        }
        
        try {
            require_once $file;
            return true;
        } catch ( \Throwable $e ) {
            error_log( sprintf( 'Plugin Name: Failed to load file %s: %s', $file, $e->getMessage() ) );
            return false;
        }
    }
}
```

### Type Safety and Modern Features

```php
namespace VendorName\PluginName\Models;

/**
 * User model with type declarations
 */
class User {
    
    private int $id;
    private string $email;
    private string $name;
    private ?string $website;
    private array $metadata;
    private \DateTimeImmutable $created_at;
    
    public function __construct(
        int $id,
        string $email,
        string $name,
        ?string $website = null,
        array $metadata = array(),
        ?\DateTimeImmutable $created_at = null
    ) {
        $this->id = $id;
        $this->email = $email;
        $this->name = $name;
        $this->website = $website;
        $this->metadata = $metadata;
        $this->created_at = $created_at ?? new \DateTimeImmutable();
    }
    
    /**
     * Create from WordPress user
     */
    public static function from_wp_user( \WP_User $wp_user ): self {
        return new self(
            $wp_user->ID,
            $wp_user->user_email,
            $wp_user->display_name,
            $wp_user->user_url ?: null,
            array(
                'login' => $wp_user->user_login,
                'registered' => $wp_user->user_registered,
            ),
            new \DateTimeImmutable( $wp_user->user_registered )
        );
    }
    
    /**
     * Get user data as array
     *
     * @return array{
     *     id: int,
     *     email: string,
     *     name: string,
     *     website: ?string,
     *     created_at: string
     * }
     */
    public function to_array(): array {
        return array(
            'id' => $this->id,
            'email' => $this->email,
            'name' => $this->name,
            'website' => $this->website,
            'created_at' => $this->created_at->format( 'Y-m-d H:i:s' ),
        );
    }
    
    // Getters with return type declarations
    public function get_id(): int {
        return $this->id;
    }
    
    public function get_email(): string {
        return $this->email;
    }
    
    public function get_display_name(): string {
        return $this->name;
    }
    
    public function has_website(): bool {
        return null !== $this->website;
    }
}
```

## Database Operations

### Migration System

```php
namespace VendorName\PluginName\Database;

/**
 * Database migration handler
 */
class Migrations {
    
    private const DB_VERSION_OPTION = 'plugin_name_db_version';
    private const CURRENT_VERSION = '1.0.0';
    
    private \wpdb $db;
    
    public function __construct() {
        global $wpdb;
        $this->db = $wpdb;
    }
    
    /**
     * Run migrations
     */
    public function run(): void {
        $installed_version = get_option( self::DB_VERSION_OPTION, '0.0.0' );
        
        if ( version_compare( $installed_version, self::CURRENT_VERSION, '<' ) ) {
            $this->migrate( $installed_version );
        }
    }
    
    /**
     * Execute migrations based on version
     */
    private function migrate( string $from_version ): void {
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        
        // Run migrations in order
        if ( version_compare( $from_version, '1.0.0', '<' ) ) {
            $this->migration_1_0_0();
        }
        
        // Update version
        update_option( self::DB_VERSION_OPTION, self::CURRENT_VERSION );
    }
    
    /**
     * Version 1.0.0 migration
     */
    private function migration_1_0_0(): void {
        $charset_collate = $this->db->get_charset_collate();
        
        // Users table
        $sql = "CREATE TABLE {$this->db->prefix}plugin_users (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            user_id bigint(20) UNSIGNED NOT NULL,
            email varchar(100) NOT NULL,
            status varchar(20) NOT NULL DEFAULT 'active',
            metadata longtext,
            created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY email (email),
            KEY user_id (user_id),
            KEY status (status),
            KEY created_at (created_at)
        ) $charset_collate;";
        
        dbDelta( $sql );
        
        // Activity log table
        $sql = "CREATE TABLE {$this->db->prefix}plugin_activity_log (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            user_id bigint(20) UNSIGNED NOT NULL,
            action varchar(50) NOT NULL,
            object_type varchar(50),
            object_id bigint(20) UNSIGNED,
            meta longtext,
            ip_address varchar(45),
            user_agent text,
            created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY user_id (user_id),
            KEY action (action),
            KEY created_at (created_at),
            KEY object_lookup (object_type, object_id)
        ) $charset_collate;";
        
        dbDelta( $sql );
    }
}
```

### Repository Pattern

```php
namespace VendorName\PluginName\Database\Repositories;

/**
 * Base repository class
 */
abstract class BaseRepository {
    
    protected \wpdb $db;
    protected string $table;
    protected string $primary_key = 'id';
    
    public function __construct() {
        global $wpdb;
        $this->db = $wpdb;
    }
    
    /**
     * Find by ID
     */
    public function find( int $id ): ?array {
        $query = $this->db->prepare(
            "SELECT * FROM {$this->table} WHERE {$this->primary_key} = %d",
            $id
        );
        
        $result = $this->db->get_row( $query, ARRAY_A );
        
        return $result ?: null;
    }
    
    /**
     * Find all with pagination
     */
    public function find_all( int $page = 1, int $per_page = 20 ): array {
        $offset = ( $page - 1 ) * $per_page;
        
        $query = $this->db->prepare(
            "SELECT * FROM {$this->table} 
             ORDER BY {$this->primary_key} DESC 
             LIMIT %d OFFSET %d",
            $per_page,
            $offset
        );
        
        return $this->db->get_results( $query, ARRAY_A );
    }
    
    /**
     * Count total records
     */
    public function count( array $where = array() ): int {
        $sql = "SELECT COUNT(*) FROM {$this->table}";
        
        if ( ! empty( $where ) ) {
            $conditions = $this->build_where_clause( $where );
            $sql .= " WHERE {$conditions['clause']}";
            $query = $this->db->prepare( $sql, ...$conditions['values'] );
        } else {
            $query = $sql;
        }
        
        return (int) $this->db->get_var( $query );
    }
    
    /**
     * Insert record
     */
    public function insert( array $data ): int|\WP_Error {
        $formats = $this->get_formats( $data );
        
        $result = $this->db->insert( $this->table, $data, $formats );
        
        if ( false === $result ) {
            return new \WP_Error( 'db_insert_error', $this->db->last_error );
        }
        
        return $this->db->insert_id;
    }
    
    /**
     * Update record
     */
    public function update( int $id, array $data ): bool|\WP_Error {
        $formats = $this->get_formats( $data );
        
        $result = $this->db->update(
            $this->table,
            $data,
            array( $this->primary_key => $id ),
            $formats,
            array( '%d' )
        );
        
        if ( false === $result ) {
            return new \WP_Error( 'db_update_error', $this->db->last_error );
        }
        
        return true;
    }
    
    /**
     * Delete record
     */
    public function delete( int $id ): bool|\WP_Error {
        $result = $this->db->delete(
            $this->table,
            array( $this->primary_key => $id ),
            array( '%d' )
        );
        
        if ( false === $result ) {
            return new \WP_Error( 'db_delete_error', $this->db->last_error );
        }
        
        return true;
    }
    
    /**
     * Build WHERE clause from array
     */
    protected function build_where_clause( array $where ): array {
        $conditions = array();
        $values = array();
        
        foreach ( $where as $column => $value ) {
            if ( is_array( $value ) ) {
                $placeholders = array_fill( 0, count( $value ), '%s' );
                $conditions[] = sprintf( '%s IN (%s)', $column, implode( ',', $placeholders ) );
                $values = array_merge( $values, $value );
            } else {
                $conditions[] = "{$column} = %s";
                $values[] = $value;
            }
        }
        
        return array(
            'clause' => implode( ' AND ', $conditions ),
            'values' => $values,
        );
    }
    
    /**
     * Get format specifiers for data
     */
    abstract protected function get_formats( array $data ): array;
}
```

## Performance Optimization

### Caching Strategy

```php
namespace VendorName\PluginName\Services;

/**
 * Caching service
 */
class CacheService {
    
    private const CACHE_GROUP = 'plugin_name';
    private const DEFAULT_EXPIRATION = 3600; // 1 hour
    
    /**
     * Get cached value
     */
    public function get( string $key ) {
        // Try object cache first
        $value = wp_cache_get( $key, self::CACHE_GROUP );
        
        if ( false !== $value ) {
            return $value;
        }
        
        // Fall back to transient
        return get_transient( $this->get_transient_key( $key ) );
    }
    
    /**
     * Set cached value
     */
    public function set( string $key, $value, int $expiration = self::DEFAULT_EXPIRATION ): bool {
        // Set in object cache
        wp_cache_set( $key, $value, self::CACHE_GROUP, $expiration );
        
        // Also set as transient for persistence
        return set_transient( $this->get_transient_key( $key ), $value, $expiration );
    }
    
    /**
     * Delete cached value
     */
    public function delete( string $key ): bool {
        wp_cache_delete( $key, self::CACHE_GROUP );
        return delete_transient( $this->get_transient_key( $key ) );
    }
    
    /**
     * Flush all plugin cache
     */
    public function flush(): bool {
        // Flush object cache group
        wp_cache_flush_group( self::CACHE_GROUP );
        
        // Delete all plugin transients
        global $wpdb;
        
        $transient_prefix = $this->get_transient_key( '' );
        $like = $wpdb->esc_like( '_transient_' . $transient_prefix ) . '%';
        
        return $wpdb->query(
            $wpdb->prepare(
                "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s",
                $like
            )
        );
    }
    
    /**
     * Remember callback result
     */
    public function remember( string $key, callable $callback, int $expiration = self::DEFAULT_EXPIRATION ) {
        $value = $this->get( $key );
        
        if ( false === $value ) {
            $value = call_user_func( $callback );
            $this->set( $key, $value, $expiration );
        }
        
        return $value;
    }
    
    /**
     * Get transient key
     */
    private function get_transient_key( string $key ): string {
        return 'plugin_name_' . $key;
    }
}
```

### Asset Management

```php
namespace VendorName\PluginName\Core;

/**
 * Asset management with optimization
 */
class AssetManager {
    
    private string $version;
    private string $plugin_url;
    private bool $debug;
    
    public function __construct( string $version ) {
        $this->version = $version;
        $this->plugin_url = MY_PLUGIN_URL;
        $this->debug = defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG;
    }
    
    /**
     * Register hooks
     */
    public function init(): void {
        add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_frontend_assets' ) );
        add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_assets' ) );
    }
    
    /**
     * Enqueue frontend assets
     */
    public function enqueue_frontend_assets(): void {
        // Only load where needed
        if ( ! $this->should_load_frontend_assets() ) {
            return;
        }
        
        $suffix = $this->debug ? '' : '.min';
        
        // CSS
        wp_enqueue_style(
            'plugin-name-frontend',
            $this->plugin_url . "assets/css/frontend{$suffix}.css",
            array(),
            $this->version
        );
        
        // JavaScript with dependencies
        wp_enqueue_script(
            'plugin-name-frontend',
            $this->plugin_url . "assets/js/frontend{$suffix}.js",
            array( 'jquery' ),
            $this->version,
            true // Load in footer
        );
        
        // Localize script
        wp_localize_script( 'plugin-name-frontend', 'pluginName', array(
            'ajaxUrl' => admin_url( 'admin-ajax.php' ),
            'nonce' => wp_create_nonce( 'plugin-name-frontend' ),
            'i18n' => array(
                'loading' => __( 'Loading...', 'plugin-textdomain' ),
                'error' => __( 'An error occurred.', 'plugin-textdomain' ),
            ),
        ) );
    }
    
    /**
     * Enqueue admin assets
     */
    public function enqueue_admin_assets( string $hook_suffix ): void {
        // Only load on plugin pages
        if ( ! $this->is_plugin_admin_page( $hook_suffix ) ) {
            return;
        }
        
        $suffix = $this->debug ? '' : '.min';
        
        // Admin CSS
        wp_enqueue_style(
            'plugin-name-admin',
            $this->plugin_url . "assets/css/admin{$suffix}.css",
            array(),
            $this->version
        );
        
        // Admin JavaScript
        wp_enqueue_script(
            'plugin-name-admin',
            $this->plugin_url . "assets/js/admin{$suffix}.js",
            array( 'jquery', 'wp-color-picker' ),
            $this->version,
            true
        );
        
        // Add inline script for critical data
        wp_add_inline_script(
            'plugin-name-admin',
            sprintf( 'var pluginNameAdmin = %s;', wp_json_encode( array(
                'ajaxUrl' => admin_url( 'admin-ajax.php' ),
                'nonce' => wp_create_nonce( 'plugin-name-admin' ),
            ) ) ),
            'before'
        );
    }
    
    /**
     * Check if should load frontend assets
     */
    private function should_load_frontend_assets(): bool {
        // Load on specific pages/posts
        if ( is_singular() && has_shortcode( get_post()->post_content, 'plugin_shortcode' ) ) {
            return true;
        }
        
        // Load on specific post types
        if ( is_singular( 'custom_post_type' ) ) {
            return true;
        }
        
        // Allow filtering
        return apply_filters( 'plugin_name_load_frontend_assets', false );
    }
    
    /**
     * Check if current page is plugin admin page
     */
    private function is_plugin_admin_page( string $hook_suffix ): bool {
        $plugin_pages = array(
            'toplevel_page_plugin-name',
            'plugin-name_page_settings',
        );
        
        return in_array( $hook_suffix, $plugin_pages, true );
    }
}
```

## Testing and Quality Assurance

### PHPUnit Test Example

```php
namespace VendorName\PluginName\Tests\Unit\Services;

use PHPUnit\Framework\TestCase;
use VendorName\PluginName\Services\DataProcessor;
use Brain\Monkey;
use Brain\Monkey\Functions;

/**
 * Data processor tests
 */
class DataProcessorTest extends TestCase {
    
    private DataProcessor $processor;
    
    protected function setUp(): void {
        parent::setUp();
        Monkey\setUp();
        
        // Mock WordPress functions
        Functions\stubs( array(
            'sanitize_text_field' => function( $str ) {
                return trim( strip_tags( $str ) );
            },
            'sanitize_email' => function( $email ) {
                return filter_var( $email, FILTER_SANITIZE_EMAIL );
            },
            'is_email' => function( $email ) {
                return filter_var( $email, FILTER_VALIDATE_EMAIL ) !== false;
            },
            'esc_url_raw' => function( $url ) {
                return filter_var( $url, FILTER_SANITIZE_URL );
            },
            'absint' => function( $value ) {
                return abs( intval( $value ) );
            },
            '__' => function( $text, $domain ) {
                return $text;
            },
            'current_user_can' => function( $capability ) {
                return true; // Mock as authorized
            },
        ) );
        
        $this->processor = new DataProcessor();
    }
    
    protected function tearDown(): void {
        Monkey\tearDown();
        parent::tearDown();
    }
    
    /**
     * Test successful form processing
     */
    public function test_process_form_with_valid_data(): void {
        Functions\expect( 'wp_verify_nonce' )
            ->once()
            ->with( 'test_nonce', 'process_form' )
            ->andReturn( true );
        
        Functions\expect( 'wp_kses' )
            ->once()
            ->andReturnFirstArg();
        
        $input = array(
            '_wpnonce' => 'test_nonce',
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'website' => 'https://example.com',
            'age' => '25',
            'bio' => 'Test bio',
            'option' => 'option2',
        );
        
        $result = $this->processor->process_form_submission( $input );
        
        $this->assertIsArray( $result );
        $this->assertEquals( 'John Doe', $result['name'] );
        $this->assertEquals( 'john@example.com', $result['email'] );
        $this->assertEquals( 'https://example.com', $result['website'] );
        $this->assertEquals( 25, $result['age'] );
        $this->assertEquals( 'option2', $result['option'] );
    }
    
    /**
     * Test nonce verification failure
     */
    public function test_process_form_with_invalid_nonce(): void {
        Functions\expect( 'wp_verify_nonce' )
            ->once()
            ->andReturn( false );
        
        $input = array(
            '_wpnonce' => 'invalid_nonce',
            'name' => 'John Doe',
        );
        
        $result = $this->processor->process_form_submission( $input );
        
        $this->assertInstanceOf( \WP_Error::class, $result );
        $this->assertEquals( 'invalid_nonce', $result->get_error_code() );
    }
    
    /**
     * Test email validation
     */
    public function test_process_form_with_invalid_email(): void {
        Functions\expect( 'wp_verify_nonce' )
            ->once()
            ->andReturn( true );
        
        $input = array(
            '_wpnonce' => 'test_nonce',
            'name' => 'John Doe',
            'email' => 'invalid-email',
        );
        
        $result = $this->processor->process_form_submission( $input );
        
        $this->assertInstanceOf( \WP_Error::class, $result );
        $this->assertEquals( 'invalid_email', $result->get_error_code() );
    }
}
```

### Integration Test Example

```php
namespace VendorName\PluginName\Tests\Integration;

use WP_UnitTestCase;
use VendorName\PluginName\Database\Repositories\UserRepository;

/**
 * User repository integration tests
 */
class UserRepositoryTest extends WP_UnitTestCase {
    
    private UserRepository $repository;
    
    public function setUp(): void {
        parent::setUp();
        
        // Create tables
        $migrations = new \VendorName\PluginName\Database\Migrations();
        $migrations->run();
        
        $this->repository = new UserRepository();
    }
    
    /**
     * Test user creation and retrieval
     */
    public function test_create_and_find_user(): void {
        // Create test user
        $user_data = array(
            'name' => 'Test User',
            'email' => 'test@example.com',
            'status' => 'active',
        );
        
        $user_id = $this->repository->insert_user( $user_data );
        
        // Assert creation
        $this->assertIsInt( $user_id );
        $this->assertGreaterThan( 0, $user_id );
        
        // Retrieve user
        $users = $this->repository->get_users_by_status( 'active' );
        
        $this->assertCount( 1, $users );
        $this->assertEquals( 'test@example.com', $users[0]['email'] );
    }
    
    /**
     * Test duplicate email handling
     */
    public function test_duplicate_email_returns_error(): void {
        // Create first user
        $user_data = array(
            'name' => 'First User',
            'email' => 'duplicate@example.com',
            'status' => 'active',
        );
        
        $first_id = $this->repository->insert_user( $user_data );
        $this->assertIsInt( $first_id );
        
        // Try to create duplicate
        $user_data['name'] = 'Second User';
        $result = $this->repository->insert_user( $user_data );
        
        $this->assertInstanceOf( \WP_Error::class, $result );
        $this->assertEquals( 'db_insert_error', $result->get_error_code() );
    }
}
```

### Code Quality Configuration

#### phpcs.xml
```xml
<?xml version="1.0"?>
<ruleset xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" name="Plugin Name">
    <description>Coding standards for Plugin Name</description>

    <!-- Files to check -->
    <file>.</file>
    
    <!-- Exclude paths -->
    <exclude-pattern>*/vendor/*</exclude-pattern>
    <exclude-pattern>*/node_modules/*</exclude-pattern>
    <exclude-pattern>*/tests/*</exclude-pattern>
    <exclude-pattern>*/assets/*</exclude-pattern>
    
    <!-- Use WordPress coding standards -->
    <rule ref="WordPress-Core">
        <!-- Allow namespaces -->
        <exclude name="PSR1.Classes.ClassDeclaration.MissingNamespace"/>
    </rule>
    <rule ref="WordPress-Docs"/>
    <rule ref="WordPress-Extra"/>
    
    <!-- PHP compatibility -->
    <config name="testVersion" value="7.4-"/>
    <rule ref="PHPCompatibilityWP"/>
    
    <!-- Minimum WordPress version -->
    <config name="minimum_supported_wp_version" value="5.8"/>
</ruleset>
```

#### phpstan.neon
```yaml
parameters:
    level: 6
    paths:
        - src
    bootstrapFiles:
        - tests/phpstan-bootstrap.php
    excludePaths:
        - src/deprecated/*
    ignoreErrors:
        # Ignore WordPress global variables
        - '#^Variable \$wpdb might not be defined#'
    treatPhpDocTypesAsCertain: false
```

## Deployment and Maintenance

### CI/CD with GitHub Actions

```yaml
# .github/workflows/ci.yml
name: CI

on:
  push:
    branches: [ main, develop ]
  pull_request:
    branches: [ main ]

jobs:
  test:
    runs-on: ubuntu-latest
    
    strategy:
      matrix:
        php: ['7.4', '8.0', '8.1', '8.2']
        wordpress: ['5.8', '6.0', 'latest']
    
    steps:
    - uses: actions/checkout@v3
    
    - name: Setup PHP
      uses: shivammathur/setup-php@v2
      with:
        php-version: ${{ matrix.php }}
        extensions: mbstring, intl, mysqli
        coverage: xdebug
        tools: composer:v2
    
    - name: Validate composer.json
      run: composer validate --strict
    
    - name: Install dependencies
      run: composer install --prefer-dist --no-progress
    
    - name: Run PHP CodeSniffer
      run: composer run phpcs
    
    - name: Run PHPStan
      run: composer run phpstan
    
    - name: Setup WordPress test suite
      run: |
        bash bin/install-wp-tests.sh wordpress_test root root localhost ${{ matrix.wordpress }}
    
    - name: Run PHPUnit tests
      run: composer run test -- --coverage-clover coverage.xml
    
    - name: Upload coverage to Codecov
      uses: codecov/codecov-action@v3
      with:
        file: ./coverage.xml

  build:
    needs: test
    runs-on: ubuntu-latest
    
    steps:
    - uses: actions/checkout@v3
    
    - name: Setup Node.js
      uses: actions/setup-node@v3
      with:
        node-version: '18'
        cache: 'npm'
    
    - name: Install Node dependencies
      run: npm ci
    
    - name: Build assets
      run: npm run build
    
    - name: Create release artifact
      run: |
        composer install --no-dev --optimize-autoloader
        zip -r plugin-name.zip . -x ".*" -x "*.log" -x "node_modules/*" -x "tests/*" -x "*.xml" -x "*.yml" -x "*.json" -x "bin/*"
    
    - name: Upload artifact
      uses: actions/upload-artifact@v3
      with:
        name: plugin-name
        path: plugin-name.zip
```

### Release Checklist

```markdown
## Pre-Release Checklist

- [ ] Update version numbers in:
  - [ ] Main plugin file header
  - [ ] `composer.json`
  - [ ] `package.json`
  - [ ] README files
- [ ] Update changelog
- [ ] Run all tests locally
- [ ] Test on minimum supported WordPress/PHP versions
- [ ] Test on latest WordPress/PHP versions
- [ ] Security audit: `composer audit`
- [ ] Check for deprecated functions
- [ ] Verify all text strings are translatable
- [ ] Generate/update POT file
- [ ] Build production assets
- [ ] Test upgrade process from previous version
- [ ] Update documentation
- [ ] Tag release in Git

## Post-Release

- [ ] Submit to WordPress.org repository (if applicable)
- [ ] Update demo/documentation site
- [ ] Announce release
- [ ] Monitor error reports
```

## Critical Checklist

### Security Checklist ✓
- [ ] All user inputs sanitized
- [ ] All outputs properly escaped
- [ ] Nonces used for all forms and AJAX
- [ ] Capability checks implemented
- [ ] SQL queries use prepared statements
- [ ] No hardcoded secrets or API keys
- [ ] File uploads validated and restricted
- [ ] CSRF protection implemented
- [ ] XSS prevention measures in place
- [ ] No use of eval() or create_function()

### Performance Checklist ✓
- [ ] Database queries optimized with indexes
- [ ] Caching implemented for expensive operations
- [ ] Assets minified and optimized
- [ ] Assets loaded only where needed
- [ ] Images lazy-loaded where appropriate
- [ ] Database cleanup on uninstall
- [ ] Transients used for temporary data
- [ ] AJAX used for better UX where applicable

### Code Quality Checklist ✓
- [ ] Composer autoloading configured
- [ ] PSR-4 namespace structure
- [ ] WordPress coding standards followed
- [ ] Comprehensive error handling
- [ ] PHPDoc comments for all methods
- [ ] Unit tests for critical functionality
- [ ] Integration tests for WordPress interactions
- [ ] No PHP warnings or notices
- [ ] Compatible with minimum PHP version
- [ ] No deprecated WordPress functions

### Development Workflow Checklist ✓
- [ ] Version control (Git) properly configured
- [ ] `.gitignore` excludes vendor/node_modules
- [ ] CI/CD pipeline configured
- [ ] Code quality tools integrated
- [ ] Automated testing on push
- [ ] Documentation up to date
- [ ] Release process documented
- [ ] Error logging implemented
- [ ] Debug mode configurations

### WordPress Integration Checklist ✓
- [ ] Proper activation/deactivation hooks
- [ ] Clean uninstall process
- [ ] Internationalization implemented
- [ ] Multisite compatibility tested
- [ ] Admin notices for errors
- [ ] Settings API used for options
- [ ] Proper hook priorities
- [ ] No conflicts with common plugins
- [ ] Graceful degradation
- [ ] Backward compatibility maintained

---

*This document represents the gold standard for WordPress plugin development as of 2025, incorporating security best practices, modern PHP features, and professional development workflows.*
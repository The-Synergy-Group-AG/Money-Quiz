# PHP Best Practices for WordPress Development (2025)

This comprehensive guide covers the latest PHP best practices, coding standards, and modern techniques for WordPress development as of 2025.

## Table of Contents
1. [WordPress PHP Version Support](#wordpress-php-version-support)
2. [Official WordPress PHP Coding Standards](#official-wordpress-php-coding-standards)
3. [Modern PHP Features in WordPress](#modern-php-features-in-wordpress)
4. [Security Best Practices](#security-best-practices)
5. [Performance Optimization](#performance-optimization)
6. [Database Best Practices](#database-best-practices)
7. [Error Handling and Debugging](#error-handling-and-debugging)
8. [Testing and Quality Assurance](#testing-and-quality-assurance)
9. [Development Tools and Workflow](#development-tools-and-workflow)
10. [Code Organization and Architecture](#code-organization-and-architecture)

## WordPress PHP Version Support

### Current Support Status (2025)
- **Minimum PHP Version**: 7.2.24+
- **Recommended PHP Version**: 8.1 or higher
- **Full Support**: 
  - WordPress 6.3+: PHP 8.0 and 8.1
  - WordPress 6.6+: PHP 8.2
  - WordPress 6.8+: PHP 8.3
- **Beta Support**: PHP 8.4
- **Usage Statistics**: 8.9% of WordPress sites use PHP 8.3+

### Version Compatibility Guidelines
```php
// Check PHP version compatibility
if (version_compare(PHP_VERSION, '7.4', '<')) {
    add_action('admin_notices', function() {
        echo '<div class="notice notice-error"><p>';
        esc_html_e('This plugin requires PHP 7.4 or higher.', 'textdomain');
        echo '</p></div>';
    });
    return;
}
```

## Official WordPress PHP Coding Standards

### Core Principles
1. **Readability Over Cleverness**: Write code that people can read
2. **Consistency**: Code should appear as if written by one person
3. **Maintainability**: Code should be easy to update and debug
4. **Security First**: Always consider security implications

### Naming Conventions

#### Functions and Variables
```php
// ✅ Correct: lowercase with underscores
function get_user_display_name($user_id) {
    $user_name = get_userdata($user_id);
    return $user_name->display_name;
}

// ❌ Incorrect: camelCase
function getUserDisplayName($userId) {
    // Don't use camelCase in WordPress
}
```

#### Classes
```php
// ✅ Correct: Capitalized words with underscores
class WP_User_Manager {
    public function get_users() {
        // Class methods use lowercase with underscores
    }
}

// ❌ Incorrect: PSR naming
class WpUserManager {
    // Don't use PSR-style naming in WordPress Core
}
```

#### Constants
```php
// ✅ Correct: All uppercase with underscores
define('WP_USER_CAPABILITY', 'manage_options');
const DEFAULT_USER_ROLE = 'subscriber';

// ❌ Incorrect: Mixed case
define('wpUserCapability', 'manage_options');
```

#### File Naming
```php
// Class files
class-wp-user-manager.php    // ✅ Correct
WpUserManager.php            // ❌ Incorrect

// Include files
user-functions.php           // ✅ Correct
userFunctions.php           // ❌ Incorrect
```

### Indentation and Spacing

#### Tabs for Indentation
```php
// ✅ Correct: Use real tabs for indentation
function process_user_data($user_id) {
→   $user = get_userdata($user_id);
→   if ($user) {
→   →   return $user->display_name;
→   }
→   return false;
}

// ❌ Incorrect: Spaces for indentation
function process_user_data($user_id) {
    $user = get_userdata($user_id);
    if ($user) {
        return $user->display_name;
    }
}
```

#### Space Usage
```php
// ✅ Correct spacing
$array = array( 'key' => 'value' );
if ( $condition ) {
    do_something();
}
foreach ( $items as $item ) {
    process_item( $item );
}

// ❌ Incorrect spacing
$array=array('key'=>'value');
if($condition){
    do_something();
}
```

### Control Structures

#### If Statements
```php
// ✅ Correct: Always use braces
if ( $condition ) {
    do_something();
} elseif ( $another_condition ) {
    do_something_else();
} else {
    do_default();
}

// ❌ Incorrect: Missing braces
if ( $condition )
    do_something();
```

#### Switch Statements
```php
// ✅ Correct
switch ( $variable ) {
    case 'value1':
        do_something();
        break;
    
    case 'value2':
    case 'value3':
        do_something_else();
        break;
    
    default:
        do_default();
        break;
}
```

### Arrays
```php
// ✅ Correct: Long array syntax for WordPress
$simple_array = array( 'apple', 'banana', 'orange' );
$assoc_array = array(
    'name'  => 'John Doe',
    'email' => 'john@example.com',
    'role'  => 'administrator',
);

// ❌ Incorrect: Short array syntax (avoid in WordPress Core)
$array = ['apple', 'banana'];
```

## Modern PHP Features in WordPress

### PHP 7.4+ Features (Safe to Use)

#### Type Declarations
```php
// ✅ Function parameter types (PHP 7.0+)
function calculate_total( int $quantity, float $price ): float {
    return $quantity * $price;
}

// ✅ Property types (PHP 7.4+)
class Product {
    public int $id;
    public string $name;
    public ?float $price = null;
    
    public function __construct( int $id, string $name ) {
        $this->id = $id;
        $this->name = $name;
    }
}

// ✅ Return type declarations
function get_users(): array {
    return get_users( array( 'role' => 'subscriber' ) );
}

function find_user( int $id ): ?WP_User {
    $user = get_user_by( 'id', $id );
    return $user instanceof WP_User ? $user : null;
}
```

#### Null Coalescing Operator
```php
// ✅ PHP 7.0+
$username = $_POST['username'] ?? 'guest';

// ✅ PHP 7.4+ Null coalescing assignment
$options['cache_ttl'] ??= 3600;
```

#### Arrow Functions (PHP 7.4+)
```php
// ✅ Use in appropriate contexts
$numbers = array( 1, 2, 3, 4, 5 );
$squared = array_map( fn( $n ) => $n * $n, $numbers );

// For WordPress hooks, traditional syntax is often clearer
add_filter( 'the_title', function( $title ) {
    return strtoupper( $title );
} );
```

### PHP 8.0+ Features (Use with Caution)

#### Union Types
```php
// ✅ Can use in plugins/themes targeting PHP 8.0+
class User_Handler {
    public function process_user( int|string $user_id ): ?WP_User {
        if ( is_numeric( $user_id ) ) {
            return get_user_by( 'id', $user_id );
        }
        return get_user_by( 'login', $user_id );
    }
}
```

#### Named Arguments
```php
// ✅ Useful for complex function calls
wp_mail(
    to: 'user@example.com',
    subject: 'Welcome to our site',
    message: $email_body,
    headers: array( 'Content-Type: text/html; charset=UTF-8' )
);
```

#### Match Expressions
```php
// ✅ More concise than switch for simple cases
$status_label = match( $post->post_status ) {
    'publish' => __( 'Published', 'textdomain' ),
    'draft' => __( 'Draft', 'textdomain' ),
    'pending' => __( 'Pending Review', 'textdomain' ),
    default => __( 'Unknown', 'textdomain' ),
};
```

### Important Note on Strict Types
```php
// ⚠️ Not recommended for WordPress Core
declare( strict_types=1 ); // Avoid in WordPress Core

// ✅ OK for plugins/themes with clear PHP requirements
// But consider WordPress's loose typing nature
```

## Security Best Practices

### Input Validation and Sanitization

#### Sanitizing User Input
```php
// ✅ Text fields
$clean_text = sanitize_text_field( $_POST['user_input'] );

// ✅ Email addresses
$clean_email = sanitize_email( $_POST['email'] );

// ✅ URLs
$clean_url = esc_url_raw( $_POST['website'] );

// ✅ Textareas
$clean_content = sanitize_textarea_field( $_POST['description'] );

// ✅ HTML content (with allowed tags)
$allowed_html = array(
    'a' => array(
        'href' => array(),
        'title' => array(),
    ),
    'br' => array(),
    'em' => array(),
    'strong' => array(),
);
$clean_html = wp_kses( $_POST['content'], $allowed_html );

// ✅ File names
$clean_filename = sanitize_file_name( $_FILES['upload']['name'] );
```

#### Escaping Output
```php
// ✅ HTML content
echo esc_html( $user_input );

// ✅ Attributes
echo '<input type="text" value="' . esc_attr( $value ) . '" />';

// ✅ URLs
echo '<a href="' . esc_url( $link ) . '">Link</a>';

// ✅ JavaScript
echo '<script>var data = ' . wp_json_encode( $data ) . ';</script>';

// ✅ SQL queries (use $wpdb->prepare())
$results = $wpdb->get_results(
    $wpdb->prepare(
        "SELECT * FROM {$wpdb->posts} WHERE post_author = %d AND post_status = %s",
        $user_id,
        'publish'
    )
);
```

### Nonce Verification
```php
// ✅ Creating and verifying nonces
// In form
wp_nonce_field( 'save_user_meta', 'user_meta_nonce' );

// In processing
if ( ! isset( $_POST['user_meta_nonce'] ) || 
     ! wp_verify_nonce( $_POST['user_meta_nonce'], 'save_user_meta' ) ) {
    wp_die( __( 'Security check failed', 'textdomain' ) );
}
```

### Capability Checks
```php
// ✅ Always check user capabilities
if ( ! current_user_can( 'manage_options' ) ) {
    wp_die( __( 'You do not have permission to access this page.', 'textdomain' ) );
}

// ✅ Check specific object capabilities
if ( ! current_user_can( 'edit_post', $post_id ) ) {
    return new WP_Error( 'forbidden', __( 'You cannot edit this post.', 'textdomain' ) );
}
```

### File Operations
```php
// ✅ Safe file uploads
function handle_file_upload( $file ) {
    // Check file type
    $allowed_types = array( 'jpg', 'jpeg', 'png', 'pdf' );
    $file_type = wp_check_filetype( $file['name'], $allowed_types );
    
    if ( ! $file_type['type'] ) {
        return new WP_Error( 'invalid_file_type', __( 'Invalid file type', 'textdomain' ) );
    }
    
    // Use WordPress upload handling
    require_once( ABSPATH . 'wp-admin/includes/file.php' );
    $upload = wp_handle_upload( $file, array( 'test_form' => false ) );
    
    if ( isset( $upload['error'] ) ) {
        return new WP_Error( 'upload_error', $upload['error'] );
    }
    
    return $upload;
}
```

## Performance Optimization

### Efficient Database Queries

#### Use WordPress Functions When Possible
```php
// ✅ Good: Use WordPress functions
$posts = get_posts( array(
    'post_type' => 'product',
    'meta_key' => 'featured',
    'meta_value' => 'yes',
    'posts_per_page' => 10,
) );

// ❌ Avoid: Direct database queries when unnecessary
$posts = $wpdb->get_results(
    "SELECT * FROM {$wpdb->posts} 
     WHERE post_type = 'product' 
     AND ID IN (SELECT post_id FROM {$wpdb->postmeta} 
                WHERE meta_key = 'featured' AND meta_value = 'yes')
     LIMIT 10"
);
```

#### Cache Expensive Operations
```php
// ✅ Use transients for expensive queries
function get_expensive_data() {
    $cache_key = 'expensive_data_cache';
    $data = get_transient( $cache_key );
    
    if ( false === $data ) {
        // Expensive operation
        $data = perform_expensive_calculation();
        
        // Cache for 1 hour
        set_transient( $cache_key, $data, HOUR_IN_SECONDS );
    }
    
    return $data;
}

// ✅ Object caching
function get_user_stats( $user_id ) {
    $cache_key = 'user_stats_' . $user_id;
    $stats = wp_cache_get( $cache_key, 'user_data' );
    
    if ( false === $stats ) {
        $stats = calculate_user_stats( $user_id );
        wp_cache_set( $cache_key, $stats, 'user_data', 300 ); // 5 minutes
    }
    
    return $stats;
}
```

### Optimize Loops
```php
// ✅ Good: Single query for multiple items
$user_ids = array( 1, 2, 3, 4, 5 );
$users = get_users( array(
    'include' => $user_ids,
    'fields' => array( 'ID', 'display_name', 'user_email' ),
) );

// ❌ Bad: Multiple queries in a loop
foreach ( $user_ids as $user_id ) {
    $user = get_userdata( $user_id ); // Separate query each time
}
```

### Lazy Loading
```php
// ✅ Load resources only when needed
class Heavy_Feature {
    private $data = null;
    
    public function get_data() {
        if ( null === $this->data ) {
            $this->data = $this->load_heavy_data();
        }
        return $this->data;
    }
    
    private function load_heavy_data() {
        // Expensive operation here
    }
}
```

## Database Best Practices

### Using $wpdb Properly

#### Prepared Statements
```php
global $wpdb;

// ✅ Always use prepare() for dynamic queries
$results = $wpdb->get_results(
    $wpdb->prepare(
        "SELECT * FROM {$wpdb->prefix}custom_table 
         WHERE user_id = %d AND status = %s 
         ORDER BY created_at DESC 
         LIMIT %d",
        $user_id,
        'active',
        10
    )
);

// ✅ Insert data safely
$wpdb->insert(
    $wpdb->prefix . 'custom_table',
    array(
        'user_id' => $user_id,
        'data' => $data,
        'created_at' => current_time( 'mysql' ),
    ),
    array( '%d', '%s', '%s' ) // Format specifiers
);

// ✅ Update data safely
$wpdb->update(
    $wpdb->prefix . 'custom_table',
    array( 'status' => 'completed' ), // Data to update
    array( 'id' => $record_id ), // Where clause
    array( '%s' ), // Data format
    array( '%d' ) // Where format
);
```

#### Creating Custom Tables
```php
function create_custom_table() {
    global $wpdb;
    
    $table_name = $wpdb->prefix . 'custom_table';
    $charset_collate = $wpdb->get_charset_collate();
    
    $sql = "CREATE TABLE $table_name (
        id bigint(20) NOT NULL AUTO_INCREMENT,
        user_id bigint(20) NOT NULL,
        data longtext NOT NULL,
        status varchar(20) DEFAULT 'pending',
        created_at datetime DEFAULT CURRENT_TIMESTAMP,
        updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        KEY user_id (user_id),
        KEY status (status)
    ) $charset_collate;";
    
    require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
    dbDelta( $sql );
    
    // Store version for future upgrades
    add_option( 'custom_table_db_version', '1.0' );
}
```

### Query Optimization
```php
// ✅ Select only needed columns
$user_names = $wpdb->get_col(
    "SELECT display_name FROM {$wpdb->users} WHERE user_status = 0"
);

// ✅ Use proper indexes in WHERE clauses
// When creating tables, add indexes for frequently queried columns

// ✅ Limit results when possible
$recent_posts = $wpdb->get_results(
    $wpdb->prepare(
        "SELECT ID, post_title FROM {$wpdb->posts} 
         WHERE post_status = %s 
         ORDER BY post_date DESC 
         LIMIT %d",
        'publish',
        5
    )
);
```

## Error Handling and Debugging

### WordPress Debug Configuration
```php
// In wp-config.php for development
define( 'WP_DEBUG', true );
define( 'WP_DEBUG_LOG', true );
define( 'WP_DEBUG_DISPLAY', false );
define( 'SCRIPT_DEBUG', true );
define( 'SAVEQUERIES', true );
```

### Error Logging
```php
// ✅ Log errors for debugging
if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
    error_log( 'Custom Plugin: Error processing user data - ' . print_r( $error, true ) );
}

// ✅ Custom error handling
function log_plugin_error( $message, $data = null ) {
    if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
        $log_message = sprintf(
            '[%s] %s: %s',
            current_time( 'mysql' ),
            'My Plugin',
            $message
        );
        
        if ( $data ) {
            $log_message .= ' - Data: ' . print_r( $data, true );
        }
        
        error_log( $log_message );
    }
}
```

### Using WP_Error
```php
// ✅ Return WP_Error for error handling
function process_data( $data ) {
    if ( empty( $data ) ) {
        return new WP_Error(
            'empty_data',
            __( 'No data provided', 'textdomain' ),
            array( 'status' => 400 )
        );
    }
    
    // Process data
    $result = perform_operation( $data );
    
    if ( ! $result ) {
        return new WP_Error(
            'operation_failed',
            __( 'Operation failed', 'textdomain' ),
            array( 'status' => 500 )
        );
    }
    
    return $result;
}

// Check for errors
$result = process_data( $input );
if ( is_wp_error( $result ) ) {
    wp_die( $result->get_error_message() );
}
```

## Testing and Quality Assurance

### PHPUnit Testing
```php
// tests/test-sample.php
class Test_Sample extends WP_UnitTestCase {
    
    public function setUp(): void {
        parent::setUp();
        // Set up test environment
    }
    
    public function tearDown(): void {
        // Clean up
        parent::tearDown();
    }
    
    public function test_user_creation() {
        $user_id = wp_create_user( 'testuser', 'password', 'test@example.com' );
        
        $this->assertIsInt( $user_id );
        $this->assertGreaterThan( 0, $user_id );
        
        $user = get_user_by( 'id', $user_id );
        $this->assertEquals( 'testuser', $user->user_login );
    }
    
    public function test_custom_function() {
        $result = my_custom_function( 'input' );
        $this->assertEquals( 'expected_output', $result );
    }
}
```

### Code Quality Tools

#### PHP_CodeSniffer Configuration
```xml
<!-- phpcs.xml -->
<?xml version="1.0"?>
<ruleset name="WordPress Plugin">
    <description>WordPress Coding Standards for Plugins</description>
    
    <rule ref="WordPress-Core" />
    <rule ref="WordPress-Docs" />
    <rule ref="WordPress-Extra" />
    
    <config name="minimum_supported_wp_version" value="5.8" />
    
    <exclude-pattern>*/vendor/*</exclude-pattern>
    <exclude-pattern>*/tests/*</exclude-pattern>
</ruleset>
```

#### Running Code Quality Checks
```bash
# Install WordPress Coding Standards
composer require --dev dealerdirect/phpcodesniffer-composer-installer
composer require --dev wp-coding-standards/wpcs

# Run PHP_CodeSniffer
vendor/bin/phpcs --standard=WordPress plugin-file.php

# Auto-fix issues
vendor/bin/phpcbf --standard=WordPress plugin-file.php
```

## Development Tools and Workflow

### Recommended IDEs and Editors
1. **PhpStorm** - Full-featured IDE with WordPress support
2. **Visual Studio Code** - With PHP and WordPress extensions
3. **Sublime Text** - Lightweight with WordPress packages

### Essential Tools
```json
{
    "require-dev": {
        "squizlabs/php_codesniffer": "^3.7",
        "wp-coding-standards/wpcs": "^3.0",
        "phpunit/phpunit": "^9.5",
        "mockery/mockery": "^1.5",
        "phpstan/phpstan": "^1.10",
        "phpcompatibility/php-compatibility": "^9.3"
    }
}
```

### Development Workflow
```bash
# 1. Check PHP compatibility
vendor/bin/phpcs -p . --standard=PHPCompatibility --runtime-set testVersion 7.4-

# 2. Run coding standards check
vendor/bin/phpcs --standard=WordPress .

# 3. Run static analysis
vendor/bin/phpstan analyse

# 4. Run tests
vendor/bin/phpunit

# 5. Check for security issues
# Use tools like WordPress Plugin Security Scanner
```

## Code Organization and Architecture

### Plugin Structure
```
my-plugin/
├── my-plugin.php              # Main plugin file
├── uninstall.php             # Cleanup on uninstall
├── includes/                 # Core plugin files
│   ├── class-plugin.php      # Main plugin class
│   ├── class-admin.php       # Admin functionality
│   ├── class-public.php      # Public functionality
│   ├── class-activator.php   # Activation hooks
│   └── class-deactivator.php # Deactivation hooks
├── admin/                    # Admin-specific files
│   ├── css/
│   ├── js/
│   └── partials/            # Admin view files
├── public/                   # Public-facing files
│   ├── css/
│   ├── js/
│   └── partials/            # Public view files
├── languages/               # Translation files
├── assets/                  # Images, fonts, etc.
└── tests/                   # Unit tests
```

### Main Plugin File Structure
```php
<?php
/**
 * Plugin Name: My Awesome Plugin
 * Plugin URI: https://example.com/
 * Description: A brief description of the plugin.
 * Version: 1.0.0
 * Author: Your Name
 * Author URI: https://example.com/
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: my-plugin
 * Domain Path: /languages
 * Requires at least: 5.8
 * Requires PHP: 7.4
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
    die;
}

// Define plugin constants
define( 'MY_PLUGIN_VERSION', '1.0.0' );
define( 'MY_PLUGIN_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'MY_PLUGIN_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

// Activation and deactivation hooks
register_activation_hook( __FILE__, array( 'My_Plugin_Activator', 'activate' ) );
register_deactivation_hook( __FILE__, array( 'My_Plugin_Deactivator', 'deactivate' ) );

// Include the main plugin class
require plugin_dir_path( __FILE__ ) . 'includes/class-plugin.php';

// Initialize the plugin
function run_my_plugin() {
    $plugin = new My_Plugin();
    $plugin->run();
}
run_my_plugin();
```

### Singleton Pattern (When Appropriate)
```php
class My_Plugin {
    
    /**
     * The single instance of the class.
     *
     * @var My_Plugin
     */
    protected static $_instance = null;
    
    /**
     * Main plugin instance.
     *
     * @return My_Plugin
     */
    public static function instance() {
        if ( is_null( self::$_instance ) ) {
            self::$_instance = new self();
        }
        return self::$_instance;
    }
    
    /**
     * Constructor.
     */
    private function __construct() {
        $this->define_constants();
        $this->includes();
        $this->init_hooks();
    }
    
    /**
     * Prevent cloning.
     */
    private function __clone() {}
    
    /**
     * Prevent unserializing.
     */
    public function __wakeup() {
        throw new Exception( 'Cannot unserialize singleton' );
    }
}
```

## Best Practices Summary

### Do's ✅
1. Follow WordPress coding standards consistently
2. Sanitize all input data
3. Escape all output
4. Use nonces for form submissions
5. Check user capabilities
6. Use WordPress functions when available
7. Cache expensive operations
8. Handle errors gracefully with WP_Error
9. Write unit tests for critical functionality
10. Document your code thoroughly
11. Use proper version control (Git)
12. Keep WordPress, themes, and plugins updated

### Don'ts ❌
1. Don't trust user input
2. Don't use extract() on untrusted data
3. Don't suppress errors with @
4. Don't use eval() or create_function()
5. Don't hardcode database table names
6. Don't use global variables unnecessarily
7. Don't ignore WordPress coding standards
8. Don't skip security checks
9. Don't use deprecated functions
10. Don't mix tabs and spaces for indentation
11. Don't use short PHP tags
12. Don't commit sensitive data to version control

## Resources and References

### Official Documentation
- [WordPress Coding Standards](https://developer.wordpress.org/coding-standards/)
- [WordPress Plugin Handbook](https://developer.wordpress.org/plugins/)
- [WordPress Security Best Practices](https://developer.wordpress.org/plugins/security/)
- [WordPress PHP Documentation](https://developer.wordpress.org/reference/)

### Tools and Utilities
- [WordPress Coding Standards for PHP_CodeSniffer](https://github.com/WordPress/WordPress-Coding-Standards)
- [Query Monitor Plugin](https://wordpress.org/plugins/query-monitor/)
- [Debug Bar Plugin](https://wordpress.org/plugins/debug-bar/)
- [PHPCompatibility](https://github.com/PHPCompatibility/PHPCompatibility)

### Community Resources
- [Make WordPress Core](https://make.wordpress.org/core/)
- [WordPress Stack Exchange](https://wordpress.stackexchange.com/)
- [Advanced WordPress Facebook Group](https://www.facebook.com/groups/advancedwp/)

---

*Last Updated: January 2025*  
*Based on WordPress 6.8+ and PHP 8.3 compatibility*
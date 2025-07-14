# WordPress Plugin Development Best Practices Guide

**Version:** 2025 Edition  
**Sources:** WordPress.org Developer Handbook, Industry Best Practices  
**Last Updated:** January 14, 2025

---

## Table of Contents

1. [Security Best Practices](#1-security-best-practices)
2. [Coding Standards](#2-coding-standards)
3. [Plugin Architecture](#3-plugin-architecture)
4. [Naming Conventions](#4-naming-conventions)
5. [File Organization](#5-file-organization)
6. [Performance Optimization](#6-performance-optimization)
7. [Internationalization](#7-internationalization)
8. [Testing and Quality Assurance](#8-testing-and-quality-assurance)
9. [Documentation](#9-documentation)
10. [Modern Development Tools](#10-modern-development-tools)

---

## 1. Security Best Practices

### 1.1 Input Validation and Sanitization

**Always validate and sanitize user input:**

```php
// Bad - Direct use of user input
$email = $_POST['email'];
$wpdb->query("SELECT * FROM table WHERE email = '$email'");

// Good - Sanitized and prepared
$email = sanitize_email($_POST['email']);
$results = $wpdb->get_results(
    $wpdb->prepare(
        "SELECT * FROM %s WHERE email = %s",
        $table_name,
        $email
    )
);
```

### 1.2 Output Escaping

**Escape all output to prevent XSS:**

```php
// Bad
echo $user_input;
echo '<div class="' . $class . '">' . $content . '</div>';

// Good
echo esc_html($user_input);
echo '<div class="' . esc_attr($class) . '">' . esc_html($content) . '</div>';

// For URLs
echo esc_url($url);

// For JavaScript
echo esc_js($javascript_data);

// For HTML content (with allowed tags)
echo wp_kses_post($html_content);
```

### 1.3 Nonce Protection (CSRF)

**Implement nonces for all forms and AJAX requests:**

```php
// Creating a nonce field in forms
wp_nonce_field('my_action_nonce', 'security_nonce');

// Verifying nonce in form handler
if (!isset($_POST['security_nonce']) || 
    !wp_verify_nonce($_POST['security_nonce'], 'my_action_nonce')) {
    wp_die('Security check failed');
}

// For AJAX requests
add_action('wp_ajax_my_action', 'handle_my_action');
function handle_my_action() {
    check_ajax_referer('my_action_nonce', 'security');
    // Process request
}
```

### 1.4 Capability Checks

**Always verify user permissions:**

```php
// Bad - No permission check
if (isset($_POST['delete'])) {
    delete_option('my_option');
}

// Good - With capability check
if (isset($_POST['delete']) && current_user_can('manage_options')) {
    delete_option('my_option');
}

// For custom capabilities
if (current_user_can('edit_my_plugin_settings')) {
    // Allow action
}
```

### 1.5 SQL Security

**Use WordPress database API with prepared statements:**

```php
// Bad - SQL injection vulnerable
$wpdb->query("DELETE FROM $table WHERE id = " . $_GET['id']);

// Good - Using prepare()
$wpdb->query(
    $wpdb->prepare(
        "DELETE FROM %s WHERE id = %d",
        $table,
        intval($_GET['id'])
    )
);

// Using WordPress functions when possible
get_post($id);  // Instead of custom SQL
update_post_meta($post_id, $key, $value);  // Instead of direct DB update
```

### 1.6 File Security

**Prevent direct file access:**

```php
// Add to the top of every PHP file
if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

// Or more descriptive
defined('ABSPATH') or die('No direct access allowed');
```

---

## 2. Coding Standards

### 2.1 PHP Standards

**Follow WordPress PHP coding standards:**

```php
// Spacing and indentation
if ( $condition ) {
    // Use tabs for indentation
    do_something();
} elseif ( $another_condition ) {
    // Space inside parentheses
    do_something_else();
} else {
    // Braces on same line as condition
    do_default();
}

// Yoda conditions (constant first)
if ( true === $the_force ) {
    // Prevents accidental assignment
}

// Array syntax
$args = array(
    'post_type'      => 'page',
    'posts_per_page' => 10,
    'orderby'        => 'title',
    'order'          => 'ASC',
);

// Alternative PHP 5.4+ syntax (acceptable)
$args = [
    'post_type'      => 'page',
    'posts_per_page' => 10,
];
```

### 2.2 Naming Conventions

```php
// Functions: lowercase with underscores
function my_plugin_activate() {
    // Function body
}

// Classes: Capitalized words with underscores
class My_Plugin_Admin {
    // Class body
}

// Constants: uppercase with underscores
define( 'MY_PLUGIN_VERSION', '1.0.0' );

// Variables: lowercase with underscores
$my_variable = 'value';

// Actions and filters: lowercase with underscores
add_action( 'init', 'my_plugin_init' );
add_filter( 'the_content', 'my_plugin_filter_content' );
```

### 2.3 Documentation Standards

```php
/**
 * Short description of function.
 *
 * Long description with more details about what the function
 * does and any important notes.
 *
 * @since  1.0.0
 * @access public
 *
 * @param  string $param1 Description of first parameter.
 * @param  int    $param2 Description of second parameter.
 * @return bool           Description of return value.
 */
function my_plugin_function( $param1, $param2 = 0 ) {
    // Function implementation
}
```

---

## 3. Plugin Architecture

### 3.1 Object-Oriented Approach (Recommended)

```php
/**
 * Main plugin class
 */
class My_Plugin {
    
    /**
     * Instance of this class
     */
    private static $instance = null;
    
    /**
     * Plugin version
     */
    const VERSION = '1.0.0';
    
    /**
     * Get singleton instance
     */
    public static function get_instance() {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Constructor
     */
    private function __construct() {
        $this->define_constants();
        $this->include_files();
        $this->init_hooks();
    }
    
    /**
     * Define plugin constants
     */
    private function define_constants() {
        define( 'MY_PLUGIN_VERSION', self::VERSION );
        define( 'MY_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
        define( 'MY_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
    }
    
    /**
     * Include required files
     */
    private function include_files() {
        require_once MY_PLUGIN_DIR . 'includes/class-admin.php';
        require_once MY_PLUGIN_DIR . 'includes/class-public.php';
    }
    
    /**
     * Initialize hooks
     */
    private function init_hooks() {
        add_action( 'init', array( $this, 'init' ) );
        add_action( 'admin_init', array( $this, 'admin_init' ) );
    }
}

// Initialize plugin
add_action( 'plugins_loaded', array( 'My_Plugin', 'get_instance' ) );
```

### 3.2 Separation of Concerns

```php
// Separate admin functionality
if ( is_admin() ) {
    require_once MY_PLUGIN_DIR . 'admin/class-admin.php';
    new My_Plugin_Admin();
}

// Separate public functionality
if ( ! is_admin() ) {
    require_once MY_PLUGIN_DIR . 'public/class-public.php';
    new My_Plugin_Public();
}

// Separate AJAX handlers
require_once MY_PLUGIN_DIR . 'includes/class-ajax.php';
new My_Plugin_Ajax();
```

---

## 4. Naming Conventions

### 4.1 Avoiding Collisions

```php
// Bad - Too generic, might conflict
function init() { }
function save_data() { }
class Admin { }

// Good - Unique prefix (minimum 4-5 characters)
function myplugin_init() { }
function myplugin_save_data() { }
class MyPlugin_Admin { }

// Using namespaces (PHP 5.3+)
namespace MyPlugin;

class Admin {
    // Class implementation
}
```

### 4.2 Prohibited Prefixes

Never use these prefixes as they're reserved:
- `__` (double underscore)
- `wp_`
- `WordPress`
- `_` (single underscore)

---

## 5. File Organization

### 5.1 Recommended Structure

```
/my-plugin
│   my-plugin.php          # Main plugin file
│   uninstall.php          # Uninstall handler
│   readme.txt             # WordPress.org readme
│   LICENSE               # License file
│
├── /includes             # Core functionality
│   ├── class-plugin.php
│   ├── class-activator.php
│   ├── class-deactivator.php
│   └── functions.php
│
├── /admin               # Admin-specific files
│   ├── class-admin.php
│   ├── partials/       # Admin views
│   ├── css/
│   └── js/
│
├── /public             # Public-facing files
│   ├── class-public.php
│   ├── partials/      # Public views
│   ├── css/
│   └── js/
│
├── /languages          # Translation files
│   └── my-plugin.pot
│
├── /assets            # Screenshots for WordPress.org
│   ├── screenshot-1.png
│   └── banner-772x250.png
│
└── /tests            # Unit tests
    └── test-plugin.php
```

### 5.2 Main Plugin File Header

```php
<?php
/**
 * Plugin Name:       My Plugin Name
 * Plugin URI:        https://example.com/plugins/my-plugin/
 * Description:       Brief description of what the plugin does.
 * Version:           1.0.0
 * Requires at least: 5.9
 * Requires PHP:      7.4
 * Author:            Your Name
 * Author URI:        https://example.com/
 * License:           GPL v2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       my-plugin
 * Domain Path:       /languages
 */

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}
```

---

## 6. Performance Optimization

### 6.1 Database Queries

```php
// Bad - Multiple queries in a loop
foreach ( $post_ids as $id ) {
    $post = get_post( $id );
    // Process post
}

// Good - Single query
$posts = get_posts( array(
    'post__in'       => $post_ids,
    'posts_per_page' => -1,
) );

// Use caching for expensive operations
$data = get_transient( 'my_plugin_expensive_data' );
if ( false === $data ) {
    $data = perform_expensive_operation();
    set_transient( 'my_plugin_expensive_data', $data, HOUR_IN_SECONDS );
}
```

### 6.2 Asset Loading

```php
// Load scripts and styles only when needed
add_action( 'wp_enqueue_scripts', 'my_plugin_enqueue_scripts' );
function my_plugin_enqueue_scripts() {
    // Only load on specific pages
    if ( is_page( 'my-plugin-page' ) ) {
        wp_enqueue_script(
            'my-plugin-script',
            MY_PLUGIN_URL . 'public/js/script.js',
            array( 'jquery' ),
            MY_PLUGIN_VERSION,
            true // Load in footer
        );
        
        // Localize script for AJAX
        wp_localize_script( 'my-plugin-script', 'my_plugin_ajax', array(
            'ajax_url' => admin_url( 'admin-ajax.php' ),
            'nonce'    => wp_create_nonce( 'my_plugin_nonce' ),
        ) );
    }
}
```

### 6.3 Options and Metadata

```php
// Bad - Multiple option calls
$option1 = get_option( 'my_plugin_option1' );
$option2 = get_option( 'my_plugin_option2' );
$option3 = get_option( 'my_plugin_option3' );

// Good - Single option with array
$options = get_option( 'my_plugin_options', array(
    'option1' => 'default1',
    'option2' => 'default2',
    'option3' => 'default3',
) );

// Autoload only necessary options
add_option( 'my_plugin_options', $value, '', 'yes' ); // Autoload
add_option( 'my_plugin_large_data', $value, '', 'no' ); // Don't autoload
```

---

## 7. Internationalization

### 7.1 Text Domain

```php
// Load text domain
add_action( 'plugins_loaded', 'my_plugin_load_textdomain' );
function my_plugin_load_textdomain() {
    load_plugin_textdomain(
        'my-plugin',
        false,
        dirname( plugin_basename( __FILE__ ) ) . '/languages'
    );
}

// Using translations
__( 'Hello World', 'my-plugin' );              // Return translated string
_e( 'Hello World', 'my-plugin' );              // Echo translated string
_n( '%s item', '%s items', $count, 'my-plugin' ); // Plural forms

// With context
_x( 'Post', 'noun', 'my-plugin' );
_x( 'Post', 'verb', 'my-plugin' );

// Escape and translate
esc_html__( 'Hello World', 'my-plugin' );
esc_attr__( 'Title attribute', 'my-plugin' );
```

### 7.2 JavaScript Translations

```php
// Register script with translations
wp_register_script(
    'my-plugin-script',
    MY_PLUGIN_URL . 'js/script.js',
    array( 'wp-i18n' ),
    MY_PLUGIN_VERSION
);

// Set script translations
wp_set_script_translations(
    'my-plugin-script',
    'my-plugin',
    MY_PLUGIN_DIR . 'languages'
);
```

---

## 8. Testing and Quality Assurance

### 8.1 Unit Testing

```php
// PHPUnit test example
class Test_My_Plugin extends WP_UnitTestCase {
    
    public function setUp() {
        parent::setUp();
        // Set up test environment
    }
    
    public function test_plugin_activation() {
        activate_my_plugin();
        $this->assertTrue( is_plugin_active( 'my-plugin/my-plugin.php' ) );
    }
    
    public function test_option_creation() {
        $this->assertNotFalse( get_option( 'my_plugin_options' ) );
    }
}
```

### 8.2 Integration Testing

```php
// Test with WordPress functions
public function test_custom_post_type_registration() {
    $this->assertTrue( post_type_exists( 'my_custom_type' ) );
}

public function test_shortcode_output() {
    $output = do_shortcode( '[my_plugin_shortcode]' );
    $this->assertContains( 'expected content', $output );
}
```

---

## 9. Documentation

### 9.1 Inline Documentation

```php
/**
 * Register custom post type.
 *
 * @since 1.0.0
 */
function my_plugin_register_post_type() {
    $labels = array(
        'name'               => _x( 'Books', 'post type general name', 'my-plugin' ),
        'singular_name'      => _x( 'Book', 'post type singular name', 'my-plugin' ),
        // More labels...
    );
    
    $args = array(
        'labels'             => $labels,
        'public'             => true,
        'publicly_queryable' => true,
        'show_ui'            => true,
        'show_in_menu'       => true,
        'query_var'          => true,
        'rewrite'            => array( 'slug' => 'book' ),
        'capability_type'    => 'post',
        'has_archive'        => true,
        'hierarchical'       => false,
        'menu_position'      => null,
        'supports'           => array( 'title', 'editor', 'author', 'thumbnail' ),
    );
    
    register_post_type( 'book', $args );
}
add_action( 'init', 'my_plugin_register_post_type' );
```

### 9.2 README.txt Format

```
=== My Plugin Name ===
Contributors: username
Donate link: https://example.com/
Tags: tag1, tag2
Requires at least: 5.9
Tested up to: 6.4
Stable tag: 1.0.0
Requires PHP: 7.4
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Brief description of the plugin in 150 characters or less.

== Description ==

Longer description explaining features, benefits, and use cases.

== Installation ==

1. Upload the plugin files to `/wp-content/plugins/my-plugin`
2. Activate the plugin through the 'Plugins' screen in WordPress
3. Use the Settings->My Plugin screen to configure the plugin

== Frequently Asked Questions ==

= How do I use this plugin? =

Answer to the question.

== Screenshots ==

1. This screen shot description corresponds to screenshot-1.(png|jpg|jpeg|gif).
2. This is the second screen shot

== Changelog ==

= 1.0.0 =
* Initial release

== Upgrade Notice ==

= 1.0.0 =
Initial release
```

---

## 10. Modern Development Tools

### 10.1 Development Environment

```json
// package.json for build tools
{
  "name": "my-plugin",
  "version": "1.0.0",
  "scripts": {
    "build": "wp-scripts build",
    "start": "wp-scripts start",
    "lint:js": "wp-scripts lint-js",
    "lint:css": "wp-scripts lint-style",
    "lint:php": "composer run-script phpcs",
    "test": "wp-scripts test-unit-js"
  },
  "devDependencies": {
    "@wordpress/scripts": "^26.0.0"
  }
}
```

### 10.2 Composer Configuration

```json
// composer.json
{
  "name": "vendor/my-plugin",
  "description": "My WordPress Plugin",
  "type": "wordpress-plugin",
  "require": {
    "php": ">=7.4"
  },
  "require-dev": {
    "phpunit/phpunit": "^9.0",
    "wp-coding-standards/wpcs": "^3.0",
    "phpcompatibility/phpcompatibility-wp": "*"
  },
  "scripts": {
    "phpcs": "phpcs --standard=WordPress .",
    "phpcbf": "phpcbf --standard=WordPress .",
    "test": "phpunit"
  }
}
```

### 10.3 GitHub Actions CI/CD

```yaml
# .github/workflows/ci.yml
name: CI

on: [push, pull_request]

jobs:
  test:
    runs-on: ubuntu-latest
    
    steps:
    - uses: actions/checkout@v2
    
    - name: Setup PHP
      uses: shivammathur/setup-php@v2
      with:
        php-version: '7.4'
        
    - name: Install dependencies
      run: composer install
      
    - name: Run PHPCS
      run: composer run-script phpcs
      
    - name: Run PHPUnit
      run: composer run-script test
```

---

## Best Practices Summary

### ✅ DO:
- Prefix everything uniquely
- Escape all output
- Validate all input
- Use WordPress APIs
- Follow coding standards
- Document your code
- Test thoroughly
- Keep security first

### ❌ DON'T:
- Use generic names
- Trust user input
- Access database directly
- Load resources globally
- Ignore errors
- Skip documentation
- Deploy without testing
- Hardcode credentials

---

## Resources

- [WordPress Plugin Handbook](https://developer.wordpress.org/plugins/)
- [WordPress Coding Standards](https://developer.wordpress.org/coding-standards/)
- [WordPress Security Best Practices](https://developer.wordpress.org/plugins/security/)
- [WordPress APIs](https://developer.wordpress.org/apis/)
- [WordPress Plugin Boilerplate](https://github.com/DevinVinson/WordPress-Plugin-Boilerplate)

---

**Last Updated:** January 14, 2025  
**Maintained By:** WordPress Community
# WordPress Plugin Development - Essential Highlights

This document provides quick reference highlights from the WordPress Plugin Development Gold Standard and Deployment & Operations Guide.

## 🚀 Quick Start Checklist

### Initial Setup
```bash
# 1. Create plugin structure
mkdir -p my-plugin/{src,assets,templates,languages,tests}

# 2. Initialize Composer
composer init
composer require --dev phpunit/phpunit squizlabs/php_codesniffer wp-coding-standards/wpcs

# 3. Create main plugin file with safeguards
# See "Critical Plugin Bootstrap" section below
```

## 🔒 Security Essentials

### Always Sanitize Input
```php
// ✅ ALWAYS DO THIS
$clean_text = sanitize_text_field( $_POST['input'] );
$clean_email = sanitize_email( $_POST['email'] );
$clean_url = esc_url_raw( $_POST['url'] );

// ❌ NEVER DO THIS
$value = $_POST['input']; // Direct usage - DANGEROUS!
```

### Always Escape Output
```php
// ✅ ALWAYS DO THIS
echo esc_html( $user_input );
echo esc_attr( $attribute );
echo esc_url( $link );
echo wp_kses_post( $content_with_html );

// ❌ NEVER DO THIS
echo $user_input; // Unescaped output - XSS RISK!
```

### Always Use Nonces
```php
// In form
wp_nonce_field( 'my_action', 'my_nonce' );

// In processing
if ( ! wp_verify_nonce( $_POST['my_nonce'], 'my_action' ) ) {
    wp_die( 'Security check failed' );
}
```

### Always Use Prepared Statements
```php
// ✅ CORRECT
$results = $wpdb->get_results(
    $wpdb->prepare(
        "SELECT * FROM {$wpdb->prefix}my_table WHERE user_id = %d AND status = %s",
        $user_id,
        'active'
    )
);

// ❌ WRONG - SQL INJECTION RISK
$results = $wpdb->get_results(
    "SELECT * FROM {$wpdb->prefix}my_table WHERE user_id = $user_id"
);
```

## 📦 Critical Plugin Bootstrap

```php
<?php
/**
 * Plugin Name: My Plugin
 * Version: 1.0.0
 * Requires PHP: 7.4
 */

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// Prevent duplicate loading
if ( defined( 'MY_PLUGIN_LOADED' ) ) {
    return;
}
define( 'MY_PLUGIN_LOADED', true );

// Check and load dependencies
$autoloader = __DIR__ . '/vendor/autoload.php';
if ( ! file_exists( $autoloader ) ) {
    add_action( 'admin_notices', function() {
        echo '<div class="notice notice-error"><p>Plugin requires Composer. Run: <code>composer install</code></p></div>';
    });
    return;
}
require_once $autoloader;

// Initialize with error handling
try {
    $plugin = \VendorName\PluginName\Core\Plugin::instance();
    $plugin->run();
} catch ( \Exception $e ) {
    error_log( 'Plugin Error: ' . $e->getMessage() );
    add_action( 'admin_notices', function() {
        echo '<div class="notice notice-error"><p>Plugin failed to initialize. Check error logs.</p></div>';
    });
}
```

## 🏗️ Modern Plugin Structure

```
my-plugin/
├── my-plugin.php          # Minimal bootstrap (above)
├── composer.json          # Dependencies & autoloading
├── src/                   # PSR-4 autoloaded classes
│   ├── Core/
│   │   ├── Plugin.php     # Main plugin class
│   │   ├── Container.php  # Dependency injection
│   │   └── ErrorHandler.php
│   ├── Admin/            # Admin functionality
│   ├── Frontend/         # Public functionality
│   ├── Api/              # REST endpoints
│   ├── Database/         # Repositories & migrations
│   └── Services/         # Business logic
├── assets/               # CSS, JS, images
├── templates/            # PHP templates
├── tests/                # PHPUnit tests
└── deployment/           # Deploy scripts
```

## 🛡️ Error Handling Pattern

```php
class MyService {
    public function process( $data ) {
        try {
            // Validate
            if ( empty( $data ) ) {
                throw new \InvalidArgumentException( 'Data cannot be empty' );
            }
            
            // Process
            $result = $this->dangerous_operation( $data );
            
            return $result;
            
        } catch ( \InvalidArgumentException $e ) {
            // Known error - return WP_Error
            return new \WP_Error( 'invalid_data', $e->getMessage() );
            
        } catch ( \Exception $e ) {
            // Unknown error - log and return generic error
            error_log( sprintf( '[MyPlugin] Error in %s: %s', __METHOD__, $e->getMessage() ) );
            return new \WP_Error( 'processing_failed', __( 'An error occurred', 'textdomain' ) );
        }
    }
}

// Usage
$result = $service->process( $data );
if ( is_wp_error( $result ) ) {
    // Handle error
    wp_die( $result->get_error_message() );
}
```

## 🚨 Admin Notice System

```php
// Critical error (red)
$notice_manager->add_notice(
    'critical_error',
    '<strong>Critical:</strong> Database connection failed',
    'error',
    ['dismissible' => false]
);

// Warning (yellow)
$notice_manager->add_notice(
    'php_warning',
    '<strong>Warning:</strong> PHP version is outdated',
    'warning',
    ['dismissible' => true, 'dismissal_duration' => 7 * DAY_IN_SECONDS]
);

// Info with action button
$notice_manager->add_notice(
    'update_available',
    'New version available',
    'info',
    [
        'action_button' => [
            'text' => 'Update Now',
            'url' => admin_url( 'plugins.php' )
        ]
    ]
);
```

## 🚀 Deployment Commands

```bash
# Run deployment checker
php deployment/check-deployment.php --verbose

# Deploy to production
php deployment/deploy.php production

# Deploy with options
php deployment/deploy.php staging --skip-tests --dry-run

# Generate JSON report
php deployment/check-deployment.php --json > deployment-report.json
```

## 🔍 Database Best Practices

```php
// Repository pattern
class UserRepository {
    private \wpdb $db;
    
    public function __construct() {
        global $wpdb;
        $this->db = $wpdb;
        $this->table = $wpdb->prefix . 'my_users';
    }
    
    public function find( int $id ): ?array {
        return $this->db->get_row(
            $this->db->prepare(
                "SELECT * FROM {$this->table} WHERE id = %d",
                $id
            ),
            ARRAY_A
        );
    }
    
    public function create( array $data ): int|\WP_Error {
        $result = $this->db->insert(
            $this->table,
            [
                'email' => $data['email'],
                'name' => $data['name'],
                'created_at' => current_time( 'mysql' )
            ],
            ['%s', '%s', '%s']
        );
        
        return false === $result 
            ? new \WP_Error( 'db_error', $this->db->last_error )
            : $this->db->insert_id;
    }
}
```

## ⚡ Performance Optimization

```php
// Cache expensive operations
function get_expensive_data() {
    $cache_key = 'expensive_data';
    $data = get_transient( $cache_key );
    
    if ( false === $data ) {
        $data = perform_expensive_operation();
        set_transient( $cache_key, $data, HOUR_IN_SECONDS );
    }
    
    return $data;
}

// Load assets conditionally
add_action( 'wp_enqueue_scripts', function() {
    if ( ! is_singular( 'my_post_type' ) ) {
        return; // Don't load if not needed
    }
    
    wp_enqueue_script( 'my-script', /* ... */ );
});
```

## 🧪 Testing Pattern

```php
class MyServiceTest extends \PHPUnit\Framework\TestCase {
    protected function setUp(): void {
        parent::setUp();
        \Brain\Monkey\setUp();
        
        // Mock WordPress functions
        \Brain\Monkey\Functions\stubs([
            'get_option' => fn($key) => 'test_value',
            'current_time' => fn() => '2025-01-01 00:00:00'
        ]);
    }
    
    protected function tearDown(): void {
        \Brain\Monkey\tearDown();
        parent::tearDown();
    }
    
    public function test_process_valid_data(): void {
        $service = new MyService();
        $result = $service->process(['valid' => 'data']);
        
        $this->assertIsArray($result);
        $this->assertArrayHasKey('success', $result);
    }
}
```

## 🚑 Emergency Recovery

### White Screen of Death
```bash
# 1. Rename plugin folder via FTP/SSH
mv wp-content/plugins/my-plugin wp-content/plugins/my-plugin-disabled

# 2. Check error logs
tail -f wp-content/debug.log

# 3. Re-enable after fixing
mv wp-content/plugins/my-plugin-disabled wp-content/plugins/my-plugin
```

### Missing Dependencies
```bash
cd wp-content/plugins/my-plugin
composer install --no-dev --optimize-autoloader
```

### Database Issues
```sql
-- Check tables
SHOW TABLES LIKE 'wp_my_plugin_%';

-- Repair table
REPAIR TABLE wp_my_plugin_table;

-- Remove if needed
DROP TABLE IF EXISTS wp_my_plugin_table;
```

## 📋 Pre-Deployment Checklist

- [ ] ✅ All tests passing (`composer test`)
- [ ] ✅ No PHP syntax errors (`php -l` on all files)
- [ ] ✅ Code standards check (`composer phpcs`)
- [ ] ✅ Dependencies installed (`composer install --no-dev`)
- [ ] ✅ Assets built (`npm run build`)
- [ ] ✅ Version numbers updated (plugin header, composer.json)
- [ ] ✅ Changelog updated
- [ ] ✅ Database migrations ready
- [ ] ✅ Deployment checker passed
- [ ] ✅ Backup created

## 🎯 Critical Security Checklist

- [ ] ❗ All inputs sanitized
- [ ] ❗ All outputs escaped
- [ ] ❗ All forms use nonces
- [ ] ❗ All database queries use prepared statements
- [ ] ❗ Capability checks on all admin functions
- [ ] ❗ No hardcoded secrets or API keys
- [ ] ❗ File uploads validated
- [ ] ❗ No use of eval() or create_function()
- [ ] ❗ No direct file access allowed
- [ ] ❗ Error messages don't expose sensitive info

## 🔗 Quick References

### WordPress Functions
- [Sanitization](https://developer.wordpress.org/themes/theme-security/data-sanitization-escaping/)
- [Escaping](https://developer.wordpress.org/themes/theme-security/data-sanitization-escaping/#escaping-securing-output)
- [Nonces](https://developer.wordpress.org/themes/theme-security/using-nonces/)
- [Database](https://developer.wordpress.org/reference/classes/wpdb/)

### Tools
- [PHP_CodeSniffer](https://github.com/squizlabs/PHP_CodeSniffer)
- [WordPress Coding Standards](https://github.com/WordPress/WordPress-Coding-Standards)
- [PHPUnit](https://phpunit.de/)
- [Brain Monkey](https://brain-wp.github.io/BrainMonkey/)

### Commands
```bash
# Install dependencies
composer install

# Run tests
composer test

# Check code standards
composer phpcs

# Fix code standards
composer phpcbf

# Build assets
npm run build

# Watch for changes
npm run watch
```

---

*This highlights document provides essential quick-reference information. For complete details, see the [WordPress Plugin Development Gold Standard](wordpress-plugin-development-gold-standard.md) and [Deployment & Operations Guide](wordpress-plugin-deployment-operations-guide.md).*
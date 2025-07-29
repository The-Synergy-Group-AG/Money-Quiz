# Modern PHP Development Practices for WordPress (2025)

This guide covers modern PHP development practices that should be used alongside traditional WordPress best practices for professional plugin and theme development.

## Table of Contents
1. [Composer and Dependency Management](#composer-and-dependency-management)
2. [PSR Standards](#psr-standards)
3. [Modern PHP Features](#modern-php-features)
4. [Error Handling and Exceptions](#error-handling-and-exceptions)
5. [Dependency Injection and Service Containers](#dependency-injection-and-service-containers)
6. [Testing Infrastructure](#testing-infrastructure)
7. [Continuous Integration](#continuous-integration)
8. [Code Architecture Patterns](#code-architecture-patterns)

## Composer and Dependency Management

### Why Composer Matters
Composer is the de facto dependency manager for PHP and brings professional package management to WordPress development.

### Setting Up Composer

**1. Create composer.json in your plugin/theme root:**
```json
{
    "name": "vendor/plugin-name",
    "description": "Professional WordPress Plugin",
    "type": "wordpress-plugin",
    "license": "GPL-2.0-or-later",
    "require": {
        "php": ">=7.4",
        "composer/installers": "^2.0"
    },
    "require-dev": {
        "phpunit/phpunit": "^9.5",
        "mockery/mockery": "^1.5",
        "brain/monkey": "^2.6",
        "phpstan/phpstan": "^1.0",
        "squizlabs/php_codesniffer": "^3.6"
    },
    "autoload": {
        "psr-4": {
            "YourVendor\\PluginName\\": "src/"
        }
    },
    "autoload-dev": {
        "psr-4": {
            "YourVendor\\PluginName\\Tests\\": "tests/"
        }
    },
    "scripts": {
        "test": "phpunit",
        "phpcs": "phpcs",
        "phpstan": "phpstan analyse",
        "lint": ["@phpcs", "@phpstan"]
    }
}
```

**2. Integrate autoloader in main plugin file:**
```php
// plugin-name.php
if (!defined('ABSPATH')) {
    exit;
}

// Load Composer autoloader with graceful fallback
$autoloader = __DIR__ . '/vendor/autoload.php';
if (file_exists($autoloader)) {
    require_once $autoloader;
} else {
    add_action('admin_notices', function() {
        printf(
            '<div class="notice notice-error"><p>%s</p></div>',
            esc_html__('Plugin Name: Composer dependencies not found. Please run "composer install".', 'plugin-textdomain')
        );
    });
    return;
}

// Initialize plugin with error handling
try {
    $plugin = new \YourVendor\PluginName\Plugin();
    $plugin->run();
} catch (\Exception $e) {
    if (defined('WP_DEBUG') && WP_DEBUG) {
        error_log('Plugin Name Error: ' . $e->getMessage());
    }
}
```

### Managing Dependencies
```bash
# Install production dependencies
composer install --no-dev --optimize-autoloader

# Install all dependencies (including dev)
composer install

# Update dependencies
composer update

# Add a new package
composer require monolog/monolog

# Add a dev dependency
composer require --dev phpunit/phpunit
```

## PSR Standards

### PSR-1: Basic Coding Standard
- Files MUST use only `<?php` tags
- Files MUST use UTF-8 without BOM
- Files SHOULD either declare symbols OR execute logic, not both
- Namespaces and classes MUST follow PSR-4 autoloading
- Class names MUST be declared in StudlyCaps
- Method names MUST be declared in camelCase

### PSR-2/PSR-12: Extended Coding Style
```php
<?php

declare(strict_types=1);

namespace YourVendor\PluginName\Services;

use YourVendor\PluginName\Contracts\ServiceInterface;
use YourVendor\PluginName\Exceptions\ServiceException;

class ExampleService implements ServiceInterface
{
    private const VERSION = '1.0.0';
    
    private string $apiKey;
    private ?CacheInterface $cache;
    
    public function __construct(string $apiKey, ?CacheInterface $cache = null)
    {
        $this->apiKey = $apiKey;
        $this->cache = $cache;
    }
    
    public function process(array $data): array
    {
        try {
            $validated = $this->validate($data);
            return $this->transform($validated);
        } catch (\InvalidArgumentException $e) {
            throw new ServiceException('Invalid data provided', 0, $e);
        }
    }
    
    private function validate(array $data): array
    {
        // Validation logic
        return $data;
    }
}
```

### PSR-4: Autoloading Standard
```
plugin-root/
├── src/
│   ├── Admin/
│   │   ├── Settings.php         # YourVendor\PluginName\Admin\Settings
│   │   └── MenuManager.php      # YourVendor\PluginName\Admin\MenuManager
│   ├── Api/
│   │   └── RestController.php   # YourVendor\PluginName\Api\RestController
│   ├── Core/
│   │   ├── Plugin.php          # YourVendor\PluginName\Core\Plugin
│   │   └── Container.php       # YourVendor\PluginName\Core\Container
│   └── Services/
│       └── QuizService.php     # YourVendor\PluginName\Services\QuizService
```

## Modern PHP Features

### PHP 7.4+ Features

**1. Typed Properties:**
```php
class User {
    public int $id;
    public string $name;
    public ?string $email = null;
    private array $metadata = [];
}
```

**2. Arrow Functions:**
```php
$numbers = [1, 2, 3, 4, 5];
$squared = array_map(fn($n) => $n * $n, $numbers);
```

**3. Null Coalescing Assignment:**
```php
$config['cache_ttl'] ??= 3600;
```

### PHP 8.0+ Features

**1. Constructor Property Promotion:**
```php
class Point {
    public function __construct(
        public float $x = 0.0,
        public float $y = 0.0,
        public float $z = 0.0,
    ) {}
}
```

**2. Named Arguments:**
```php
$service->sendEmail(
    to: 'user@example.com',
    subject: 'Welcome',
    attachments: ['invoice.pdf']
);
```

**3. Match Expressions:**
```php
$status = match($code) {
    200, 201 => 'success',
    400, 404 => 'client_error',
    500, 503 => 'server_error',
    default => 'unknown'
};
```

**4. Union Types:**
```php
class Number {
    private int|float $value;
    
    public function __construct(int|float $value) {
        $this->value = $value;
    }
}
```

## Error Handling and Exceptions

### Comprehensive Error Handling

**1. Create custom exception classes:**
```php
namespace YourVendor\PluginName\Exceptions;

class PluginException extends \Exception {}
class ValidationException extends PluginException {}
class DatabaseException extends PluginException {}
class ApiException extends PluginException {}
```

**2. Implement error handler:**
```php
namespace YourVendor\PluginName\Core;

class ErrorHandler
{
    private bool $debug;
    
    public function __construct(bool $debug = false)
    {
        $this->debug = $debug;
    }
    
    public function register(): void
    {
        set_error_handler([$this, 'handleError']);
        set_exception_handler([$this, 'handleException']);
        register_shutdown_function([$this, 'handleShutdown']);
    }
    
    public function handleError(int $errno, string $errstr, string $errfile, int $errline): bool
    {
        if (!(error_reporting() & $errno)) {
            return false;
        }
        
        throw new \ErrorException($errstr, 0, $errno, $errfile, $errline);
    }
    
    public function handleException(\Throwable $exception): void
    {
        $this->logError($exception);
        
        if ($this->debug) {
            $this->displayError($exception);
        } else {
            $this->displayGenericError();
        }
    }
    
    public function handleShutdown(): void
    {
        $error = error_get_last();
        if ($error && in_array($error['type'], [E_ERROR, E_CORE_ERROR, E_COMPILE_ERROR, E_PARSE])) {
            $this->handleException(new \ErrorException(
                $error['message'],
                0,
                $error['type'],
                $error['file'],
                $error['line']
            ));
        }
    }
    
    private function logError(\Throwable $exception): void
    {
        error_log(sprintf(
            '[%s] %s: %s in %s:%d',
            get_class($exception),
            date('Y-m-d H:i:s'),
            $exception->getMessage(),
            $exception->getFile(),
            $exception->getLine()
        ));
    }
}
```

**3. Use try-catch blocks strategically:**
```php
class DatabaseService
{
    public function saveData(array $data): bool
    {
        try {
            $this->validate($data);
            $this->beginTransaction();
            
            try {
                $id = $this->insert($data);
                $this->updateRelations($id, $data['relations'] ?? []);
                $this->commit();
                
                return true;
            } catch (\Exception $e) {
                $this->rollback();
                throw new DatabaseException('Failed to save data', 0, $e);
            }
        } catch (ValidationException $e) {
            // Log validation errors differently
            do_action('plugin_validation_error', $e->getMessage());
            throw $e;
        }
    }
}
```

## Dependency Injection and Service Containers

### Simple Service Container Implementation

```php
namespace YourVendor\PluginName\Core;

class Container
{
    private array $services = [];
    private array $factories = [];
    private array $shared = [];
    
    public function bind(string $id, callable $factory, bool $shared = true): void
    {
        $this->factories[$id] = $factory;
        $this->shared[$id] = $shared;
    }
    
    public function singleton(string $id, callable $factory): void
    {
        $this->bind($id, $factory, true);
    }
    
    public function factory(string $id, callable $factory): void
    {
        $this->bind($id, $factory, false);
    }
    
    public function get(string $id)
    {
        if (!isset($this->factories[$id])) {
            throw new \Exception("Service {$id} not found");
        }
        
        if (isset($this->services[$id]) && $this->shared[$id]) {
            return $this->services[$id];
        }
        
        $service = $this->factories[$id]($this);
        
        if ($this->shared[$id]) {
            $this->services[$id] = $service;
        }
        
        return $service;
    }
    
    public function has(string $id): bool
    {
        return isset($this->factories[$id]);
    }
}
```

### Using the Container

```php
// In your main plugin file or service provider
$container = new Container();

// Register services
$container->singleton('database', function($container) {
    return new DatabaseService($GLOBALS['wpdb']);
});

$container->singleton('cache', function($container) {
    return new CacheService(wp_cache_get_group('plugin-name'));
});

$container->singleton('quiz.service', function($container) {
    return new QuizService(
        $container->get('database'),
        $container->get('cache')
    );
});

$container->singleton('admin.settings', function($container) {
    return new AdminSettings(
        $container->get('quiz.service')
    );
});

// Usage
$quizService = $container->get('quiz.service');
```

### Service Provider Pattern

```php
namespace YourVendor\PluginName\Providers;

abstract class ServiceProvider
{
    protected Container $container;
    
    public function __construct(Container $container)
    {
        $this->container = $container;
    }
    
    abstract public function register(): void;
    
    public function boot(): void
    {
        // Override in child classes if needed
    }
}

class CoreServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->container->singleton('logger', function() {
            return new Logger(WP_CONTENT_DIR . '/plugin-logs');
        });
        
        $this->container->singleton('validator', function() {
            return new Validator();
        });
    }
}
```

## Testing Infrastructure

### PHPUnit Setup

**1. Create phpunit.xml:**
```xml
<?xml version="1.0" encoding="UTF-8"?>
<phpunit 
    bootstrap="tests/bootstrap.php"
    colors="true"
    convertErrorsToExceptions="true"
    convertNoticesToExceptions="true"
    convertWarningsToExceptions="true"
    stopOnFailure="false">
    
    <testsuites>
        <testsuite name="Unit">
            <directory>tests/Unit</directory>
        </testsuite>
        <testsuite name="Integration">
            <directory>tests/Integration</directory>
        </testsuite>
    </testsuites>
    
    <coverage processUncoveredFiles="true">
        <include>
            <directory suffix=".php">src</directory>
        </include>
    </coverage>
    
    <php>
        <const name="WP_TESTS_DOMAIN" value="example.org"/>
        <const name="WP_TESTS_EMAIL" value="admin@example.org"/>
        <const name="WP_TESTS_TITLE" value="Test Blog"/>
        <const name="WP_PHP_BINARY" value="php"/>
    </php>
</phpunit>
```

**2. Create bootstrap file:**
```php
// tests/bootstrap.php
require_once dirname(__DIR__) . '/vendor/autoload.php';

// Load WordPress test environment or mock it
if (defined('WP_TESTS_DIR')) {
    require_once WP_TESTS_DIR . '/includes/bootstrap.php';
} else {
    // Use Brain Monkey for unit tests without WordPress
    \Brain\Monkey\setUp();
}
```

**3. Write tests:**
```php
namespace YourVendor\PluginName\Tests\Unit;

use PHPUnit\Framework\TestCase;
use YourVendor\PluginName\Services\QuizService;
use Brain\Monkey\Functions;

class QuizServiceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        \Brain\Monkey\setUp();
    }
    
    protected function tearDown(): void
    {
        \Brain\Monkey\tearDown();
        parent::tearDown();
    }
    
    public function testCalculateScore(): void
    {
        // Mock WordPress functions
        Functions\expect('get_option')
            ->with('quiz_settings')
            ->andReturn(['passing_score' => 70]);
        
        $service = new QuizService();
        $score = $service->calculateScore([
            ['answer' => 'a', 'correct' => 'a'],
            ['answer' => 'b', 'correct' => 'b'],
            ['answer' => 'c', 'correct' => 'a'],
        ]);
        
        $this->assertEquals(66.67, $score);
    }
}
```

### Integration Testing

```php
namespace YourVendor\PluginName\Tests\Integration;

use WP_UnitTestCase;

class QuizIntegrationTest extends WP_UnitTestCase
{
    private QuizService $service;
    
    public function setUp(): void
    {
        parent::setUp();
        
        // Set up test data
        $this->factory()->post->create([
            'post_type' => 'quiz',
            'post_title' => 'Test Quiz'
        ]);
        
        $this->service = new QuizService();
    }
    
    public function testQuizCreation(): void
    {
        $quiz_id = $this->service->createQuiz([
            'title' => 'New Quiz',
            'questions' => [
                ['text' => 'Question 1', 'answers' => ['A', 'B', 'C']]
            ]
        ]);
        
        $this->assertIsInt($quiz_id);
        $this->assertGreaterThan(0, $quiz_id);
        
        $quiz = get_post($quiz_id);
        $this->assertEquals('New Quiz', $quiz->post_title);
    }
}
```

## Continuous Integration

### GitHub Actions Workflow

```yaml
# .github/workflows/ci.yml
name: CI

on: [push, pull_request]

jobs:
  test:
    runs-on: ubuntu-latest
    strategy:
      matrix:
        php: ['7.4', '8.0', '8.1', '8.2']
        wordpress: ['5.9', '6.0', '6.1', 'latest']
    
    steps:
    - uses: actions/checkout@v3
    
    - name: Setup PHP
      uses: shivammathur/setup-php@v2
      with:
        php-version: ${{ matrix.php }}
        coverage: xdebug
    
    - name: Install dependencies
      run: composer install --prefer-dist --no-progress
    
    - name: Run PHP CodeSniffer
      run: composer run phpcs
    
    - name: Run PHPStan
      run: composer run phpstan
    
    - name: Run PHPUnit
      run: composer run test -- --coverage-clover coverage.xml
    
    - name: Upload coverage
      uses: codecov/codecov-action@v3
```

## Code Architecture Patterns

### Repository Pattern

```php
namespace YourVendor\PluginName\Repositories;

interface QuizRepositoryInterface
{
    public function find(int $id): ?Quiz;
    public function findAll(): array;
    public function save(Quiz $quiz): void;
    public function delete(int $id): void;
}

class WordPressQuizRepository implements QuizRepositoryInterface
{
    private string $postType = 'quiz';
    
    public function find(int $id): ?Quiz
    {
        $post = get_post($id);
        
        if (!$post || $post->post_type !== $this->postType) {
            return null;
        }
        
        return $this->postToQuiz($post);
    }
    
    public function save(Quiz $quiz): void
    {
        $data = [
            'post_title' => $quiz->getTitle(),
            'post_content' => $quiz->getDescription(),
            'post_type' => $this->postType,
            'post_status' => 'publish',
            'meta_input' => [
                'quiz_questions' => $quiz->getQuestions(),
                'quiz_settings' => $quiz->getSettings(),
            ]
        ];
        
        if ($quiz->getId()) {
            $data['ID'] = $quiz->getId();
            wp_update_post($data);
        } else {
            $id = wp_insert_post($data);
            $quiz->setId($id);
        }
    }
}
```

### Factory Pattern

```php
namespace YourVendor\PluginName\Factories;

class NotificationFactory
{
    public function create(string $type, array $data): NotificationInterface
    {
        switch ($type) {
            case 'email':
                return new EmailNotification(
                    $data['to'],
                    $data['subject'],
                    $data['message']
                );
                
            case 'sms':
                return new SmsNotification(
                    $data['phone'],
                    $data['message']
                );
                
            case 'webhook':
                return new WebhookNotification(
                    $data['url'],
                    $data['payload']
                );
                
            default:
                throw new \InvalidArgumentException("Unknown notification type: {$type}");
        }
    }
}
```

### Observer Pattern with WordPress Hooks

```php
namespace YourVendor\PluginName\Events;

class EventManager
{
    private array $listeners = [];
    
    public function attach(string $event, callable $listener, int $priority = 10): void
    {
        add_action("plugin_name_{$event}", $listener, $priority, 10);
    }
    
    public function detach(string $event, callable $listener): void
    {
        remove_action("plugin_name_{$event}", $listener);
    }
    
    public function trigger(string $event, ...$args): void
    {
        do_action("plugin_name_{$event}", ...$args);
    }
}

// Usage
$events = new EventManager();

$events->attach('quiz_completed', function($quizId, $userId, $score) {
    // Send notification
    // Update statistics
    // Trigger webhooks
});

$events->trigger('quiz_completed', $quizId, $userId, $score);
```

## Best Practices Summary

### Do's
1. ✅ Always use Composer for dependency management
2. ✅ Implement comprehensive error handling
3. ✅ Use dependency injection for testability
4. ✅ Write tests for critical functionality
5. ✅ Follow PSR standards alongside WordPress standards
6. ✅ Use type declarations and return types
7. ✅ Implement proper logging mechanisms
8. ✅ Use modern PHP features appropriately
9. ✅ Structure code using design patterns
10. ✅ Set up CI/CD pipelines

### Don'ts
1. ❌ Don't use global variables unnecessarily
2. ❌ Don't suppress errors with @
3. ❌ Don't mix concerns in single classes
4. ❌ Don't skip error handling
5. ❌ Don't hardcode dependencies
6. ❌ Don't ignore PHP warnings and notices
7. ❌ Don't use outdated PHP versions
8. ❌ Don't skip testing critical paths
9. ❌ Don't violate SOLID principles
10. ❌ Don't ignore security best practices

---

*This guide complements the WordPress Best Practices document with modern PHP development practices essential for professional WordPress development in 2025.*
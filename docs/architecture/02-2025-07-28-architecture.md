# Money Quiz Plugin - Modern Architecture Documentation

## Overview

The Money Quiz plugin has been refactored to follow WordPress Gold Standard practices with a modern, maintainable architecture. This document outlines the architectural decisions, patterns, and structure implemented.

## Architecture Principles

### 1. **SOLID Principles**
- **S**ingle Responsibility: Each class has one clear purpose
- **O**pen/Closed: Classes are open for extension, closed for modification
- **L**iskov Substitution: Interfaces ensure substitutability
- **I**nterface Segregation: Small, focused interfaces
- **D**ependency Inversion: Depend on abstractions, not concretions

### 2. **PSR Standards**
- PSR-4 Autoloading for namespace structure
- PSR-12 Coding style (WordPress adapted)
- PSR-11 Container interface principles

### 3. **Design Patterns**
- **Singleton**: For main plugin instance
- **Dependency Injection**: For loose coupling
- **Repository**: For data access abstraction
- **Service Layer**: For business logic
- **Facade**: For simplified API access

## Directory Structure

```
money-quiz/
├── src/                        # PSR-4 autoloaded source code
│   ├── Core/                   # Core plugin functionality
│   │   ├── Plugin.php          # Main plugin class (Singleton)
│   │   ├── Container.php       # Dependency injection container
│   │   ├── Loader.php          # Hook loader
│   │   ├── I18n.php           # Internationalization
│   │   ├── Activator.php       # Activation logic
│   │   └── Deactivator.php     # Deactivation logic
│   │
│   ├── Admin/                  # Admin functionality
│   │   ├── MenuManager.php     # Admin menu management
│   │   ├── SettingsManager.php # Settings pages
│   │   └── AjaxHandler.php     # AJAX request handling
│   │
│   ├── Frontend/               # Frontend functionality
│   │   ├── ShortcodeManager.php # Shortcode registration
│   │   └── AssetManager.php     # Asset enqueuing
│   │
│   ├── Api/                    # REST API
│   │   └── RestController.php   # REST endpoints
│   │
│   ├── Database/               # Database layer
│   │   ├── Migrator.php        # Database migrations
│   │   └── Repositories/       # Repository pattern
│   │       ├── BaseRepository.php
│   │       ├── QuizRepository.php
│   │       └── ArchetypeRepository.php
│   │
│   ├── Services/               # Business logic
│   │   ├── QuizService.php     # Quiz operations
│   │   ├── EmailService.php    # Email handling
│   │   ├── CacheService.php    # Caching layer
│   │   └── ValidationService.php # Data validation
│   │
│   ├── Models/                 # Data models
│   │   ├── Quiz.php
│   │   ├── Question.php
│   │   └── QuizResult.php
│   │
│   ├── Interfaces/             # Contract definitions
│   │   ├── PluginInterface.php
│   │   ├── ContainerInterface.php
│   │   └── RepositoryInterface.php
│   │
│   ├── Traits/                 # Reusable traits
│   │   └── SingletonTrait.php
│   │
│   ├── Exceptions/             # Custom exceptions
│   │   ├── ContainerException.php
│   │   ├── NotFoundException.php
│   │   └── QuizException.php
│   │
│   └── functions.php           # Global helper functions
│
├── composer.json               # Composer configuration
├── money-quiz.php             # Main plugin file (minimal)
└── vendor/                    # Composer dependencies (git-ignored)
```

## Key Components

### 1. Dependency Injection Container

The `Container` class provides dependency injection functionality:

```php
// Register a service
$container->bind('service.quiz', function($c) {
    return new QuizService(
        $c->get('repository.quiz'),
        $c->get('repository.archetype'),
        $c->get('service.cache')
    );
});

// Retrieve a service
$quizService = $container->get('service.quiz');
```

### 2. Repository Pattern

Repositories abstract database operations:

```php
class QuizRepository extends BaseRepository {
    protected string $table = 'money_quiz_quizzes';
    
    public function get_active(array $args = []): array {
        return $this->where(['is_active' => 1], $args);
    }
}
```

### 3. Service Layer

Services contain business logic:

```php
class QuizService {
    public function process_submission(
        int $quiz_id, 
        array $answers, 
        array $user_data = []
    ): QuizResult {
        // Validate, calculate, save, notify
    }
}
```

### 4. Modern PHP Features

#### PHP 7.4+ Features Used:
- **Typed Properties**: `private string $version;`
- **Arrow Functions**: For simple callbacks
- **Null Coalescing Assignment**: `$value ??= $default;`
- **Array Spread Operator**: `[...$array1, ...$array2]`
- **Numeric Literal Separator**: `1_000_000`

#### Type Declarations:
- **Parameter Types**: `function get(string $id)`
- **Return Types**: `function all(): array`
- **Nullable Types**: `function find(int $id): ?object`
- **Union Types** (PHP 8.0+): Ready for upgrade

### 5. Error Handling

Comprehensive error handling with custom exceptions:

```php
try {
    $result = $quizService->process_submission($quiz_id, $answers);
} catch (QuizException $e) {
    // Handle quiz-specific errors
} catch (Throwable $e) {
    // Handle all other errors
    $logger->error($e->getMessage());
}
```

## Database Schema

### Tables Structure

1. **money_quiz_quizzes** - Quiz definitions
2. **money_quiz_questions** - Quiz questions
3. **money_quiz_archetypes** - Money personality archetypes
4. **money_quiz_results** - Quiz completion results
5. **money_quiz_prospects** - Lead capture data
6. **money_quiz_email_log** - Email sending history
7. **money_quiz_templates** - Template configurations

### Migration System

Database migrations are version-controlled:

```php
class Migrator {
    private const CURRENT_VERSION = '4.0.0';
    
    public function migrate(): void {
        $installed = get_option('money_quiz_db_version', '0.0.0');
        
        if (version_compare($installed, self::CURRENT_VERSION, '<')) {
            $this->run_migrations($installed);
        }
    }
}
```

## API Design

### Global Functions

Helper functions for easy access:

```php
// Get plugin instance
$plugin = money_quiz();

// Get a service
$service = money_quiz_get('service.quiz');

// Get plugin version
$version = money_quiz_version();
```

### REST API Endpoints

```
GET  /wp-json/money-quiz/v1/quizzes
GET  /wp-json/money-quiz/v1/quizzes/{id}
POST /wp-json/money-quiz/v1/quizzes/{id}/submit
GET  /wp-json/money-quiz/v1/results/{id}
```

## Performance Optimizations

### 1. Caching Strategy

Multi-layer caching approach:
- **Object Cache**: For runtime performance
- **Transients**: For persistent cache
- **Query Optimization**: Indexed columns, prepared statements

### 2. Lazy Loading

Services are instantiated only when needed:
```php
$container->bind('service.heavy', function($c) {
    // Only created when first requested
    return new HeavyService();
});
```

### 3. Asset Management

- Conditional loading based on page context
- Minified assets in production
- Version-based cache busting

## Security Measures

### 1. Input Validation
- Type checking with PHP 7.4+ types
- WordPress sanitization functions
- Custom validation service

### 2. SQL Injection Prevention
- Prepared statements throughout
- Repository pattern abstracts queries
- No raw SQL in business logic

### 3. CSRF Protection
- Nonce verification for all forms
- Capability checks for admin actions

### 4. XSS Prevention
- Output escaping in templates
- Context-aware escaping functions

## Testing Strategy

### 1. Unit Tests
- PHPUnit for isolated class testing
- Mockery for dependency mocking
- Brain Monkey for WordPress function mocking

### 2. Integration Tests
- Test database interactions
- Test WordPress hook integrations
- Test API endpoints

### 3. Code Quality
- PHP CodeSniffer with WordPress standards
- PHPStan for static analysis
- Automated checks in CI/CD

## Backward Compatibility

The architecture maintains backward compatibility:

1. **Legacy Loader**: Falls back if modern architecture fails
2. **Graceful Degradation**: Works without Composer
3. **Version Detection**: Adapts to PHP/WordPress versions

## Future Enhancements

### Phase 1 (Immediate)
- Complete CSRF implementation
- Add comprehensive logging
- Implement rate limiting

### Phase 2 (Short-term)
- GraphQL API support
- Advanced caching with Redis
- WebSocket support for real-time

### Phase 3 (Long-term)
- Microservices architecture
- Kubernetes deployment
- Multi-tenant support

## Development Workflow

### Setup
```bash
# Install dependencies
composer install

# Run tests
composer test

# Check code standards
composer lint

# Fix code standards
composer fix
```

### Adding New Features

1. Create service in `src/Services/`
2. Register in `Plugin::register_services()`
3. Add repository if needed
4. Create tests
5. Update documentation

## Conclusion

This modern architecture provides:

- **Maintainability**: Clear structure and separation of concerns
- **Testability**: Dependency injection enables easy testing
- **Performance**: Optimized queries and caching
- **Security**: Multiple layers of protection
- **Scalability**: Ready for growth and enhancement

The implementation follows WordPress best practices while leveraging modern PHP features, creating a robust foundation for future development.
# Coding Standards

## Document Control
- **Version**: 1.0
- **Last Updated**: 2025-07-23
- **Status**: Active
- **Owner**: Technical Lead

## Overview
This document defines the coding standards for Money Quiz v7.0 to ensure consistency, maintainability, and quality across the codebase.

## PHP Coding Standards

### General Rules
1. **PSR-12**: Follow PSR-12 coding style
2. **WordPress Standards**: Comply with WordPress PHP coding standards
3. **PHP Version**: Target PHP 7.4+ features
4. **Strict Types**: Use declare(strict_types=1) in all files

### File Structure
```php
<?php
declare(strict_types=1);

namespace MoneyQuiz\Domain\Entities;

use MoneyQuiz\Domain\ValueObjects\QuizId;

/**
 * Quiz entity representing a quiz in the domain
 * 
 * @package MoneyQuiz
 * @since 7.0.0
 */
class Quiz
{
    // Class implementation
}
```

### Naming Conventions

#### Classes and Interfaces
- **Classes**: PascalCase (e.g., `QuizService`)
- **Interfaces**: PascalCase with Interface suffix (e.g., `QuizRepositoryInterface`)
- **Abstract Classes**: PascalCase with Abstract prefix (e.g., `AbstractEntity`)
- **Traits**: PascalCase with Trait suffix (e.g., `TimestampableTrait`)

#### Methods and Functions
- **Methods**: camelCase (e.g., `getQuizById()`)
- **Private/Protected**: Leading underscore optional but discouraged
- **WordPress Hooks**: snake_case (e.g., `money_quiz_init`)

#### Variables and Properties
- **Variables**: camelCase (e.g., `$quizTitle`)
- **Constants**: UPPER_SNAKE_CASE (e.g., `MAX_QUESTIONS`)
- **Properties**: camelCase (e.g., `private string $title`)

### Type Declarations
```php
// Always use type declarations
public function calculateScore(array $answers, float $passingScore): Result
{
    // Implementation
}

// Use union types where appropriate (PHP 8.0+)
public function findQuiz(int|string $identifier): ?Quiz
{
    // Implementation
}
```

### Documentation Standards

#### File Headers
```php
/**
 * Quiz management service
 *
 * Handles all quiz-related business logic including creation,
 * modification, and deletion of quizzes.
 *
 * @package    MoneyQuiz
 * @subpackage Application\Services
 * @since      7.0.0
 * @author     Money Quiz Team
 * @license    GPL-2.0+
 */
```

#### Method Documentation
```php
/**
 * Creates a new quiz with the provided data
 *
 * @param array $data Quiz data including title, description, and questions
 * @param int   $userId ID of the user creating the quiz
 * 
 * @return Quiz The created quiz entity
 * 
 * @throws ValidationException If quiz data is invalid
 * @throws AuthorizationException If user lacks permission
 * 
 * @since 7.0.0
 */
public function createQuiz(array $data, int $userId): Quiz
{
    // Implementation
}
```

## JavaScript/TypeScript Standards

### General Rules
1. **ESLint**: Follow project ESLint configuration
2. **TypeScript**: Prefer TypeScript for complex modules
3. **ES6+**: Use modern JavaScript features

### Code Style
```javascript
// Use const/let, never var
const quizConfig = {
    maxAttempts: 3,
    timeLimit: 1800
};

// Arrow functions for callbacks
const results = quizzes.map(quiz => ({
    id: quiz.id,
    title: quiz.title
}));

// Async/await over promises
async function loadQuiz(id) {
    try {
        const response = await api.get(`/quizzes/${id}`);
        return response.data;
    } catch (error) {
        console.error('Failed to load quiz:', error);
        throw error;
    }
}
```

## CSS/SCSS Standards

### Structure
```scss
// Component-based structure
.money-quiz {
    &__header {
        // Header styles
    }
    
    &__content {
        // Content styles
    }
    
    &--active {
        // Modifier styles
    }
}
```

### Best Practices
1. **BEM Methodology**: Use BEM for class naming
2. **Variables**: Use CSS custom properties
3. **Responsive**: Mobile-first approach
4. **Specificity**: Keep specificity low

## Database Standards

### Naming Conventions
- **Tables**: Plural, snake_case (e.g., `money_quiz_quizzes`)
- **Columns**: Singular, snake_case (e.g., `user_id`)
- **Indexes**: idx_table_columns (e.g., `idx_quizzes_status`)
- **Foreign Keys**: fk_table_reference (e.g., `fk_questions_quiz`)

### Query Standards
```php
// Always use prepared statements
$results = $wpdb->get_results(
    $wpdb->prepare(
        "SELECT * FROM {$wpdb->prefix}money_quiz_quizzes 
         WHERE status = %s AND user_id = %d",
        'published',
        $userId
    )
);
```

## Git Standards

### Commit Messages
```
type(scope): subject

body

footer
```

Examples:
- `feat(quiz): add quiz duplication feature`
- `fix(api): correct scoring calculation`
- `docs(readme): update installation instructions`
- `refactor(service): extract quiz validation logic`

### Branch Naming
- Feature: `feature/add-quiz-timer`
- Bugfix: `fix/scoring-calculation`
- Hotfix: `hotfix/security-patch`
- Release: `release/v7.1.0`

## Code Quality Rules

### Complexity Limits
- **Method Length**: Max 50 lines
- **Class Length**: Max 500 lines
- **Cyclomatic Complexity**: Max 10
- **File Length**: Max 150 lines (per Grok requirement)

### Security Standards
1. **Input Validation**: Always validate and sanitize
2. **Output Escaping**: Escape all output
3. **SQL Injection**: Use prepared statements
4. **XSS Prevention**: Use proper escaping functions
5. **CSRF Protection**: Use nonces

```php
// Input validation
$title = sanitize_text_field($_POST['quiz_title'] ?? '');

// Output escaping
echo esc_html($quiz->getTitle());

// Nonce verification
if (!wp_verify_nonce($_POST['_wpnonce'], 'create_quiz')) {
    wp_die('Security check failed');
}
```

## Testing Standards

### Test Naming
```php
public function test_it_creates_quiz_with_valid_data(): void
{
    // Test implementation
}

public function test_it_throws_exception_for_invalid_data(): void
{
    // Test implementation
}
```

### Test Structure
```php
// Arrange
$quizData = $this->createValidQuizData();
$service = new QuizService();

// Act
$result = $service->createQuiz($quizData);

// Assert
$this->assertInstanceOf(Quiz::class, $result);
$this->assertEquals('Test Quiz', $result->getTitle());
```

## Performance Standards

### Best Practices
1. **Lazy Loading**: Load data only when needed
2. **Caching**: Cache expensive operations
3. **Batch Operations**: Process in batches
4. **Early Returns**: Exit early when possible

```php
public function processQuizzes(array $quizIds): array
{
    // Early return for empty input
    if (empty($quizIds)) {
        return [];
    }
    
    // Check cache first
    $cached = wp_cache_get('processed_quizzes_' . md5(serialize($quizIds)));
    if ($cached !== false) {
        return $cached;
    }
    
    // Process and cache
    $results = $this->batchProcess($quizIds);
    wp_cache_set('processed_quizzes_' . md5(serialize($quizIds)), $results, '', 3600);
    
    return $results;
}
```

## Code Review Checklist

### Before Submitting PR
- [ ] Code follows style guidelines
- [ ] Self-review completed
- [ ] Tests written and passing
- [ ] Documentation updated
- [ ] No debugging code left
- [ ] Security considerations addressed
- [ ] Performance impact considered
- [ ] File length under 150 lines

## Tools and Automation

### Required Tools
- **PHP_CodeSniffer**: WordPress coding standards
- **PHPStan**: Level 6 static analysis
- **ESLint**: JavaScript linting
- **Prettier**: Code formatting

### Pre-commit Hooks
```bash
#!/bin/sh
# Run PHP_CodeSniffer
vendor/bin/phpcs

# Run PHPStan
vendor/bin/phpstan analyse

# Run ESLint
npm run lint

# Run tests
vendor/bin/phpunit
```

## Related Documents
- [Security Guidelines](./02-security-guidelines.md)
- [Testing Strategy](./03-testing-strategy.md)
- [Architecture Overview](../30-architecture/00-architecture-overview.md)
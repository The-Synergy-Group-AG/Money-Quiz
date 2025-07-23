# Money Quiz v7.0 - Getting Started Guide

## Welcome, Developer! 👋

This guide will help you get up and running with Money Quiz v7.0 development.

## Prerequisites

### Required Software
- **PHP**: 8.2 or higher
- **MySQL**: 5.7+ or MariaDB 10.3+
- **WordPress**: 6.0 or higher
- **Composer**: Latest version
- **Node.js**: 18.x or higher
- **Git**: Version control

### Recommended Tools
- **IDE**: VS Code or PhpStorm
- **Local Environment**: LocalWP, Docker, or XAMPP
- **API Client**: Postman or Insomnia
- **Database Tool**: phpMyAdmin or TablePlus

## Quick Start

### 1. Clone the Repository
```bash
git clone https://github.com/your-org/money-quiz-v7.git
cd money-quiz-v7
```

### 2. Install Dependencies
```bash
# PHP dependencies
composer install

# JavaScript dependencies
npm install
```

### 3. Environment Setup
```bash
# Copy environment template
cp .env.example .env

# Edit with your settings
nano .env
```

### 4. Build Assets
```bash
# Development build
npm run dev

# Production build
npm run build

# Watch mode
npm run watch
```

### 5. Install in WordPress
```bash
# Create symlink (recommended for development)
ln -s /path/to/money-quiz-v7 /path/to/wordpress/wp-content/plugins/money-quiz

# Or copy files
cp -r /path/to/money-quiz-v7 /path/to/wordpress/wp-content/plugins/money-quiz
```

### 6. Activate Plugin
1. Log into WordPress admin
2. Navigate to Plugins
3. Find "Money Quiz" and click "Activate"

## Project Structure

```
money-quiz-v7/
├── src/                  # PHP source code
│   ├── Core/            # Core functionality
│   ├── Admin/           # Admin interface
│   ├── Frontend/        # Public interface
│   ├── API/             # REST API
│   ├── Database/        # Database layer
│   └── Security/        # Security components
├── assets/              # Frontend assets
│   ├── js/             # JavaScript files
│   ├── css/            # Stylesheets
│   └── images/         # Images
├── tests/              # Test files
│   ├── Unit/           # Unit tests
│   ├── Integration/    # Integration tests
│   └── E2E/            # End-to-end tests
├── docs/               # Documentation
├── templates/          # PHP templates
└── money-quiz.php      # Main plugin file
```

## Development Workflow

### 1. Create Feature Branch
```bash
git checkout -b feature/your-feature-name
```

### 2. Make Changes
Follow our [Coding Standards](01-coding-standards.md)

### 3. Run Tests
```bash
# PHP tests
composer test

# JavaScript tests
npm test

# All tests
npm run test:all
```

### 4. Check Code Quality
```bash
# PHP CodeSniffer
composer run phpcs

# PHPStan
composer run phpstan

# ESLint
npm run lint
```

### 5. Commit Changes
```bash
git add .
git commit -m "feat: Add feature description"
```

Commit format:
- `feat:` New feature
- `fix:` Bug fix
- `docs:` Documentation
- `style:` Formatting
- `refactor:` Code refactoring
- `test:` Test additions
- `chore:` Maintenance

### 6. Push and Create PR
```bash
git push origin feature/your-feature-name
```

## Key Development Concepts

### Service Container
```php
// Get service from container
$logger = $container->get(Logger::class);
$logger->info('Development started!');
```

### Hooks System
```php
// Add action
add_action('money_quiz_init', function() {
    // Your code
});

// Add filter
add_filter('money_quiz_settings', function($settings) {
    return $settings;
});
```

### Security First
```php
// Always validate input
$validator = $container->get(InputValidator::class);
$validated = $validator->validate($_POST, [
    'email' => 'required|email',
    'name' => 'required|string|max:255'
]);

// Always escape output
echo esc_html($user_input);
echo esc_url($url);
echo esc_attr($attribute);
```

## Common Tasks

### Add New Service
1. Create service class in appropriate namespace
2. Register in service provider
3. Add to container
4. Write tests

### Add Database Table
1. Create migration file
2. Define schema
3. Run migration
4. Update models

### Add Admin Page
1. Create admin controller
2. Register menu item
3. Create view template
4. Add JavaScript/CSS

### Add REST Endpoint
1. Create endpoint class
2. Define route
3. Add validation
4. Write documentation

## Debugging

### Enable Debug Mode
```php
// wp-config.php
define('WP_DEBUG', true);
define('WP_DEBUG_LOG', true);
define('WP_DEBUG_DISPLAY', false);
define('SCRIPT_DEBUG', true);
```

### Use Logger
```php
$logger = $container->get(Logger::class);
$logger->debug('Variable value', ['var' => $variable]);
```

### Browser DevTools
- Network tab for API calls
- Console for JavaScript errors
- Elements for DOM inspection

## Testing

### Run Specific Tests
```bash
# Single test file
vendor/bin/phpunit tests/Unit/Core/ContainerTest.php

# Single test method
vendor/bin/phpunit --filter testServiceRegistration

# Test coverage
vendor/bin/phpunit --coverage-html coverage
```

### Write New Test
```php
class YourTest extends TestCase {
    public function testFeature(): void {
        // Arrange
        $service = new YourService();
        
        // Act
        $result = $service->doSomething();
        
        // Assert
        $this->assertEquals('expected', $result);
    }
}
```

## Getting Help

### Resources
- [Architecture Overview](../30-architecture/00-architecture-overview.md)
- [API Reference](04-api-reference.md)
- [Troubleshooting](07-troubleshooting.md)

### Contact
- **Slack**: #money-quiz-dev
- **GitHub Issues**: Report bugs
- **Wiki**: Extended documentation

## Next Steps

1. Read [Coding Standards](01-coding-standards.md)
2. Review [Security Guidelines](02-security-guidelines.md)
3. Explore [Architecture](../30-architecture/00-architecture-overview.md)
4. Start with a small task from [Task Tracker](../10-control/02-task-tracker.md)

---
*Happy coding! Remember: Security First, Quality Always* 🚀
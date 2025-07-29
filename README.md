# Money Quiz WordPress Plugin

A modern, enterprise-grade WordPress plugin for creating and managing money personality quizzes. Built with best practices, PSR-4 autoloading, and comprehensive testing.

**Developed by:** The Synergy Group AG  
**Website:** https://thesynergygroup.ch  
**Contact:** Andre@thesynergygroup.ch

## Features

- **8 Money Archetypes**: Comprehensive personality assessment system
- **Multiple Quiz Lengths**: Blitz (24), Short (56), Full (112), Classic (84) questions
- **Modern Architecture**: PSR-4 autoloading, dependency injection, service layers
- **Performance Optimized**: Lazy loading, asset minification, query optimization
- **Security First**: CSRF protection, nonce verification, prepared statements
- **Extensible**: Hook system, filters, custom integrations
- **Tested**: Unit tests, integration tests, security tests

## Requirements

- PHP 7.4 or higher
- WordPress 5.8 or higher
- MySQL 5.7+ or MariaDB 10.2+
- Composer (for development)

## Installation

### From ZIP File

1. Download the plugin ZIP file
2. Navigate to WordPress Admin → Plugins → Add New
3. Click "Upload Plugin" and select the ZIP file
4. Click "Install Now" and then "Activate"

### From Source

```bash
# Clone the repository
git clone https://github.com/businessinsights/money-quiz.git
cd money-quiz

# Install dependencies
composer install --no-dev

# Build assets (if needed)
npm install && npm run build
```

## Quick Start

1. **Activate the Plugin**
   - Go to WordPress Admin → Plugins
   - Find "Money Quiz" and click "Activate"

2. **Configure Basic Settings**
   - Navigate to Money Quiz → Settings
   - Enter your license key
   - Configure coach information

3. **Add Quiz to Page**
   - Create or edit a page
   - Add shortcode: `[money_quiz]` or `[mq_questions]`
   - Publish the page

## Architecture

### Directory Structure

```
money-quiz/
├── src/                    # PHP source code (PSR-4)
│   ├── Admin/             # Admin functionality
│   ├── Core/              # Core classes
│   ├── Database/          # Database operations
│   ├── Frontend/          # Frontend functionality
│   ├── Models/            # Data models
│   ├── Repositories/      # Data access layer
│   └── Services/          # Business logic
├── tests/                 # PHPUnit tests
├── assets/                # CSS, JS, images
├── templates/             # View templates
├── docs/                  # Documentation
└── vendor/                # Composer dependencies
```

### Key Components

- **Plugin Container**: Dependency injection container
- **Service Layer**: Business logic separation
- **Repository Pattern**: Database abstraction
- **Event System**: WordPress hooks integration
- **Asset Pipeline**: Optimized resource loading

## Development

### Setup Development Environment

```bash
# Install development dependencies
composer install

# Run tests
composer test

# Run code standards check
composer phpcs

# Fix code standards
composer phpcbf
```

### Available Hooks

#### Actions
- `money_quiz_before_render` - Before quiz renders
- `money_quiz_after_submit` - After quiz submission
- `money_quiz_result_calculated` - After results calculated

#### Filters
- `money_quiz_questions` - Modify quiz questions
- `money_quiz_archetypes` - Modify archetypes
- `money_quiz_email_content` - Customize email content

### Creating Extensions

```php
// Add custom functionality
add_action('money_quiz_after_submit', function($result_id, $answers) {
    // Your custom code here
}, 10, 2);

// Modify quiz behavior
add_filter('money_quiz_questions', function($questions, $quiz_id) {
    // Modify questions
    return $questions;
}, 10, 2);
```

## Performance

### Optimization Features

- **Lazy Loading**: Questions load as needed
- **Asset Minification**: CSS/JS minified in production
- **Query Optimization**: Indexed database queries
- **Caching Layer**: Transient-based caching
- **CDN Ready**: Proper asset URLs for CDN integration

### Performance Tips

1. Enable object caching (Redis/Memcached)
2. Use a CDN for static assets
3. Enable lazy loading for large quizzes
4. Monitor with performance tools

## Security

### Built-in Protection

- CSRF token validation
- SQL injection prevention
- XSS protection
- Nonce verification
- Capability checks
- Input sanitization

### Security Best Practices

1. Keep WordPress and plugins updated
2. Use strong passwords
3. Limit admin access
4. Regular security audits
5. Monitor access logs

## API Reference

### PHP Classes

```php
// Get quiz service
$quiz_service = MoneyQuiz\Core\Plugin::get_instance()
    ->get_container()
    ->get('quiz_service');

// Get quiz by ID
$quiz = $quiz_service->get_quiz(1);

// Get user results
$results = $quiz_service->get_user_results($user_email);
```

### JavaScript API

```javascript
// Listen for quiz completion
jQuery(document).on('money-quiz-completed', function(event, data) {
    console.log('Quiz completed:', data);
});

// Programmatically submit quiz
window.MoneyQuiz.submitQuiz();
```

### REST API Endpoints

- `GET /wp-json/money-quiz/v1/quizzes` - List quizzes
- `GET /wp-json/money-quiz/v1/quiz/{id}` - Get quiz
- `POST /wp-json/money-quiz/v1/submit` - Submit quiz
- `GET /wp-json/money-quiz/v1/results/{id}` - Get results

## Troubleshooting

### Common Issues

1. **Quiz not displaying**
   - Check shortcode syntax
   - Verify JavaScript conflicts
   - Check console for errors

2. **Submission failures**
   - Verify AJAX URL
   - Check nonce expiration
   - Review server logs

3. **Performance issues**
   - Enable caching
   - Optimize database
   - Check query monitor

### Debug Mode

```php
// Enable debug logging in wp-config.php
define('WP_DEBUG', true);
define('WP_DEBUG_LOG', true);
define('MONEY_QUIZ_DEBUG', true);
```

## Contributing

We welcome contributions! Please see our [Contributing Guidelines](CONTRIBUTING.md) for details.

### Development Workflow

1. Fork the repository
2. Create feature branch
3. Write tests for new features
4. Ensure all tests pass
5. Submit pull request

## Changelog

See [CHANGELOG.md](CHANGELOG.md) for version history.

## Support

- **Documentation**: [Full documentation](https://docs.moneyquiz.com)
- **Email**: andre@101BusinessInsights.info
- **Issues**: [GitHub Issues](https://github.com/businessinsights/money-quiz/issues)

## License

This plugin is proprietary software owned by Business Insights Group AG, Zurich, Switzerland.

## Credits

Developed by Business Insights Group AG
Visit our [Business Tools Online Shop](https://www.101businessinsights.com)

---

**Note**: For the complete user guide with screenshots and detailed configuration instructions, please see [README-original.md](README-original.md).
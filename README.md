# Money Quiz v7.0

A secure, enterprise-grade financial assessment quiz plugin for WordPress.

## Overview

Money Quiz v7.0 is a complete rewrite focusing on security, performance, and WordPress coding standards compliance. This version implements a multi-layer security architecture and follows PSR standards while maintaining full WordPress compatibility.

## Key Features

- **Multi-layer Security Architecture**: Request → Authentication → Validation → Business Logic → Data → Output
- **PSR-11 Dependency Injection**: Modern container-based architecture
- **OWASP Top 10 Compliance**: Protection against common vulnerabilities
- **Database-backed Rate Limiting**: Scalable protection against abuse
- **Comprehensive Admin Interface**: Intuitive quiz management system
- **REST API**: Full-featured API with authentication
- **Analytics & Reporting**: Built-in analytics with export capabilities

## Requirements

- PHP 7.4 or higher
- WordPress 5.9 or higher
- MySQL 5.7+ or MariaDB 10.3+
- PHP Extensions: json, mbstring

## Installation

1. Upload the `money-quiz` folder to `/wp-content/plugins/`
2. Run `composer install` in the plugin directory
3. Activate the plugin through the WordPress admin
4. Navigate to Money Quiz → Settings to configure

## Architecture

### Core Components

- **Container (PSR-11)**: Dependency injection container
- **Service Providers**: Modular service registration
- **Security Layer**: Input validation, output escaping, CSRF protection
- **Database Layer**: Query builder with prepared statements
- **API Layer**: RESTful API with authentication middleware

### Security Features

- Prepared statements for all database queries
- Context-aware output escaping
- CSRF protection with double-submit cookies
- Rate limiting on all endpoints
- Input validation and sanitization
- Security headers (CSP, X-Frame-Options, etc.)

## Development

### Setup

```bash
# Install dependencies
composer install
npm install

# Run tests
composer test

# Code analysis
composer analyze

# Fix coding standards
composer fix
```

### Coding Standards

- WordPress Coding Standards (WPCS)
- PHPStan level 6
- PSR-4 autoloading
- Comprehensive PHPDoc blocks

## Documentation

- [Admin Guide](docs/admin-guide.md)
- [Developer Guide](docs/developer-guide.md)
- [API Reference](docs/api-reference.md)
- [Security Overview](docs/security.md)

## License

GPL v2 or later

## Support

For support, please visit [https://support.moneyquiz.com](https://support.moneyquiz.com)

---

Built with security and performance in mind by The Synergy Group AG.
# Money Quiz Logging Security Policy

## Overview

Money Quiz implements a secure logging system that automatically sanitizes sensitive data to prevent information leakage. This policy outlines the security measures and best practices for logging within the plugin.

## Automatic Sanitization

### Sensitive Data Patterns

The Logger class automatically sanitizes the following patterns:

1. **Authentication Credentials**
   - Passwords: `password: [REDACTED]`
   - API Keys: `api_key: [REDACTED]`
   - Tokens: `token: [REDACTED]`
   - Secrets: `secret: [REDACTED]`
   - Nonces: `nonce: [REDACTED]`
   - Cookies: `cookie: [REDACTED]`

2. **Personal Information**
   - Email addresses: `[EMAIL]`
   - Credit card numbers: `[CREDIT_CARD]`
   - Social Security Numbers: `[SSN]`

### Array Sanitization

When logging arrays or objects, the Logger:
- Recursively sanitizes all string values
- Replaces sensitive keys entirely with `[REDACTED]`
- Preserves structure for debugging while removing sensitive data

## Usage Guidelines

### DO Log
- General application flow
- Non-sensitive error messages
- Performance metrics without user data
- Sanitized request parameters
- Plugin configuration (non-sensitive)

### DON'T Log
- Raw user input
- Database query results with user data
- Authentication tokens or session IDs
- Payment information
- Personal identifiable information (PII)

## Implementation

```php
// Good - Logger automatically sanitizes
$logger->error( 'Login failed', [
    'username' => $username,
    'password' => $password, // Will be sanitized to [REDACTED]
] );

// Bad - Never log sensitive data directly
error_log( "User password: " . $password ); // NEVER DO THIS
```

## Log Retention

- Logs are automatically rotated when they reach 10MB
- Old logs are deleted after 30 days
- Logs are stored in protected directories with .htaccess

## Security Measures

1. **File Protection**: Log directories include .htaccess to prevent direct access
2. **Sanitization**: All logged data passes through sanitization filters
3. **Debug Mode**: Debug logs only written when WP_DEBUG is enabled
4. **No Remote Logging**: Logs stay on the server, no external transmission

## Adding Custom Patterns

To add custom sanitization patterns:

```php
$logger->add_sanitization_pattern(
    '/custom_secret["\']?\s*[:=]\s*["\']?[^"\'\\s]+/i',
    'custom_secret: [REDACTED]'
);
```

## Compliance

This logging policy ensures compliance with:
- GDPR (no PII in logs)
- PCI DSS (no payment data in logs)
- HIPAA (no health information in logs)
- WordPress security best practices

## Review Process

1. All new logging statements must be reviewed for sensitive data
2. Regular audits of log files to ensure sanitization is working
3. Update sanitization patterns as new sensitive data types are identified

Last Updated: 2025-07-23
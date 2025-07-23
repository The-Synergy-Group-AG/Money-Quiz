# Comprehensive Logging Policy

## Overview
This policy defines what should be logged, what should never be logged, and how logs should be managed to prevent information leakage while maintaining security visibility.

## Logging Levels

### EMERGENCY - System is unusable
- Database connection completely failed
- Critical security breach detected
- File system corruption

### ALERT - Immediate action required
- Multiple authentication failures from same IP
- Detected malicious payload
- Rate limit severely exceeded

### CRITICAL - Critical conditions
- Plugin activation failure
- Security component initialization failure
- Database migration failure

### ERROR - Error conditions
- Failed API requests
- Invalid input that bypassed validation
- File upload failures
- Email sending failures

### WARNING - Warning conditions
- Invalid nonce detected
- Rate limit approaching
- Deprecated function usage
- Missing configuration

### NOTICE - Normal but significant
- User login/logout
- Settings changed
- Data exported
- Quiz completed

### INFO - Informational
- Page views
- API requests
- Cache hits/misses
- Background job completion

### DEBUG - Debug information
- Function entry/exit
- Variable values (sanitized)
- SQL queries (parameterized)
- Performance metrics

## What TO Log

### Security Events (ALWAYS)
- Authentication attempts (success/failure)
- Authorization failures
- Nonce verification (failures only)
- Rate limiting triggers
- Session regeneration
- CORS rejections
- Input validation failures
- File upload attempts

### User Actions
- Login/logout with timestamp
- Settings modifications
- Data exports
- Quiz creation/modification/deletion
- Bulk operations
- Permission changes

### System Events
- Plugin activation/deactivation
- Database operations (schema changes)
- Cache operations
- Email send attempts
- API calls (external)
- Background job execution

### Performance Metrics
- Slow queries (>1 second)
- Memory usage spikes
- Request duration
- Cache effectiveness

## What NOT to Log (NEVER)

### Sensitive Data
- ❌ Passwords (raw or hashed)
- ❌ Authentication tokens
- ❌ Session IDs
- ❌ API keys or secrets
- ❌ Credit card information
- ❌ Social security numbers
- ❌ Personal health information

### User Privacy
- ❌ Email content
- ❌ Quiz answers (unless anonymized)
- ❌ IP addresses (hash if needed)
- ❌ User-generated content (full)
- ❌ Referrer URLs with sensitive params

### System Security
- ❌ Encryption keys
- ❌ Salt values
- ❌ Full file paths
- ❌ Database credentials
- ❌ Internal network topology

## Sanitization Requirements

### Before Logging
```php
// Example sanitization
$logger->info('User action', [
    'user_id' => $user_id,
    'email' => substr($email, 0, 3) . '***@' . substr(strrchr($email, '@'), 1),
    'ip' => hash('sha256', $_SERVER['REMOTE_ADDR']),
    'action' => sanitize_text_field($action)
]);
```

### Automatic Sanitization
- Email addresses: Show first 3 chars + domain
- IP addresses: Hash with daily salt
- URLs: Remove query parameters
- File paths: Show relative paths only
- User input: Truncate to 200 chars

## Log Retention

### Retention Periods
- **Security logs**: 90 days
- **Error logs**: 30 days
- **Info logs**: 7 days
- **Debug logs**: 24 hours

### Log Rotation
- Rotate daily at midnight
- Compress logs older than 7 days
- Delete logs past retention period
- Maximum 1GB total log storage

## Log Storage

### File Locations
```
/wp-content/uploads/money-quiz-logs/
├── security/
│   └── security-2025-01-23.log
├── errors/
│   └── error-2025-01-23.log
├── info/
│   └── info-2025-01-23.log
└── debug/
    └── debug-2025-01-23.log
```

### Permissions
- Directory: 750 (rwxr-x---)
- Log files: 640 (rw-r-----)
- Owner: Web server user
- No web access (.htaccess deny)

## Log Format

### Standard Format
```
[2025-01-23 14:30:45] LEVEL: Message {context_json}
```

### Example Entry
```
[2025-01-23 14:30:45] WARNING: Invalid nonce detected {
    "action": "delete_quiz",
    "user_id": 123,
    "ip_hash": "a665a45920422f9d417e4867efdc4fb8a04a1f3fff1fa07e998e86f7f7a27ae3",
    "request_id": "abc123"
}
```

## Access Control

### Who Can Access Logs
- **Full access**: Super Admins only
- **Read security logs**: Administrators
- **No access**: All other roles

### Log Viewing
- Must use plugin interface
- No direct file access
- Filtered by capability
- Audit log for log access

## Monitoring & Alerts

### Automated Monitoring
- Failed login threshold (5 in 10 minutes)
- Error rate spike (>10 per minute)
- Security event patterns
- Disk usage warnings

### Alert Channels
- Email to admin for critical events
- Dashboard notifications
- Optional webhook integration
- Daily summary email

## Compliance

### GDPR Compliance
- Hash personal identifiers
- Provide data export
- Honor deletion requests
- Document retention policy

### Security Standards
- OWASP logging guidelines
- WordPress coding standards
- PCI DSS requirements (if applicable)
- HIPAA considerations

## Implementation Checklist

- [ ] Configure log levels per environment
- [ ] Set up log rotation
- [ ] Implement sanitization functions
- [ ] Create .htaccess protection
- [ ] Set up monitoring alerts
- [ ] Document emergency procedures
- [ ] Train team on policy
- [ ] Regular policy review

---
*Last Updated: 2025-07-23*
*Version: 1.0*
*Review Frequency: Quarterly*
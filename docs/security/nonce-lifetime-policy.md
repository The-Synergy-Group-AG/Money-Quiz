# Nonce Lifetime Policy

## Overview
This document defines the nonce lifetime policy for Money Quiz v7.0 to ensure consistent security practices across the plugin.

## Default Lifetime
- **Standard Actions**: 1 hour (3600 seconds)
- **Rationale**: Balances security with user experience

## Critical Action Lifetimes

### Data Modification (5 minutes / 300 seconds)
- `delete_quiz` - Permanently deletes quiz data
- `reset_data` - Resets all plugin data
- `delete_user_data` - Removes user information
- `bulk_delete` - Mass deletion operations

### Export Operations (10 minutes / 600 seconds)
- `export_data` - Exports sensitive data
- `export_quiz` - Exports quiz content
- `export_results` - Exports user results
- `manage_users` - User management actions

### Settings Changes (15 minutes / 900 seconds)
- `change_settings` - Modifies plugin settings
- `update_security` - Updates security configuration
- `modify_permissions` - Changes access permissions

### Extended Operations (30 minutes / 1800 seconds)
- `import_data` - Large data imports
- `backup_restore` - Backup restoration
- `migration` - Data migration operations

## Implementation Guidelines

### 1. Setting Lifetimes
```php
// In service provider or initialization
$nonce_manager->set_critical_lifetime('delete_quiz', 300);
$nonce_manager->set_critical_lifetime('export_data', 600);
$nonce_manager->set_critical_lifetime('change_settings', 900);
```

### 2. Checking Lifetimes
```php
// Get appropriate lifetime for action
$lifetime = $nonce_manager->get_lifetime('delete_quiz'); // Returns 300
```

### 3. User Notification
- Warn users about time limits for critical actions
- Display countdown timers for short-lived nonces
- Provide clear error messages when nonces expire

### 4. Refresh Strategy
- Auto-refresh nonces via AJAX for long forms
- Prompt user to confirm before critical actions
- Never auto-extend critical action nonces

## Security Considerations

### Why Different Lifetimes?
1. **Risk Mitigation**: Shorter lifetimes for dangerous actions
2. **Attack Window**: Reduces time for CSRF exploitation
3. **User Friction**: Balanced against usability needs

### Monitoring
- Log all critical action attempts
- Track nonce expiration events
- Alert on suspicious patterns

## Exceptions

### Never Use Extended Lifetimes For:
- Password changes
- Permission modifications
- Data deletion
- Security settings

### Consider Shorter Lifetimes When:
- User is on public/shared computer
- Elevated security mode is active
- Multiple failed attempts detected

## Review Schedule
This policy should be reviewed:
- Quarterly for effectiveness
- After any security incident
- When adding new critical actions

---
*Last Updated: 2025-07-23*
*Version: 1.0*
# Money Quiz Integration Setup Guide

## Quick Start

The Money Quiz integration features are now fully implemented and ready to use. Follow these steps to enable and configure them.

## Step 1: Enable Error Logging

Add to your `wp-config.php`:

```php
// Enable WordPress debugging
define( 'WP_DEBUG', true );
define( 'WP_DEBUG_LOG', true );
define( 'WP_DEBUG_DISPLAY', false );

// Enable Money Quiz error logging
define( 'MONEY_QUIZ_ERROR_LOGGING', true );
```

## Step 2: Access Integration Settings

1. Log in to WordPress admin
2. Navigate to **Money Quiz → Integration**
3. You'll see the integration dashboard with:
   - Overall health status
   - Safe query percentage
   - Error rate monitoring
   - Performance metrics

## Step 3: Configure Feature Flags

### Start Conservative (Recommended)

1. Set **Modern Implementation Rollout** to `10%`
2. Enable only low-risk functions:
   - ✅ `mq_get_quiz_questions`
   - ✅ `mq_calculate_archetype`
   - ✅ `mq_get_archetypes`
   - ✅ `mq_get_setting`

3. Keep all safety features enabled:
   - ✅ Database query protection
   - ✅ Automatic input sanitization
   - ✅ CSRF protection
   - ✅ Enhanced error logging

### Monitor for 24-48 Hours

Check these metrics:
- Error rate should remain low (< 5/hour)
- Page load time should stay under 2 seconds
- Safe query percentage should be above 80%

### Gradually Increase

After successful monitoring:
1. Increase rollout to `30%`
2. Enable medium-risk functions
3. Monitor for another 24 hours
4. Continue increasing until 100%

## Step 4: Run Database Migration

### Dry Run First (Required)

```bash
cd /path/to/wordpress/wp-content/plugins/money-quiz
php tools/migrate-database-queries.php --dry-run
```

Review the output to see what will be changed.

### Apply Migration

If the dry run looks good:

```bash
php tools/migrate-database-queries.php
```

Or use the UI:
1. Go to **Money Quiz → Integration**
2. Find "Database Query Migration" section
3. Click "Run Dry Run" first
4. Review results
5. Click "Run Migration" if satisfied

## Step 5: Test Critical Functions

### Manual Testing Checklist

1. **Quiz Display**
   - [ ] Visit a page with `[money_quiz]` shortcode
   - [ ] Verify quiz displays correctly
   - [ ] Check console for JavaScript errors

2. **Quiz Submission**
   - [ ] Complete and submit a quiz
   - [ ] Verify results are calculated
   - [ ] Check if email is received

3. **Admin Functions**
   - [ ] View prospects list
   - [ ] Export prospects to CSV
   - [ ] Save settings
   - [ ] Delete a test prospect

4. **Security Tests**
   - [ ] Try submitting form with SQL injection
   - [ ] Try XSS in input fields
   - [ ] Verify CSRF tokens are present

## Step 6: Monitor Health

### Daily Checks

1. **Admin Bar Indicator**
   - Green shield = All good
   - Yellow shield = Minor issues
   - Red shield = Critical issues

2. **Integration Dashboard**
   - Check error rate
   - Review performance metrics
   - Look for any warnings

3. **Error Logs**
   - Check `wp-content/debug.log`
   - Review `wp-content/money-quiz-logs/`

### Weekly Reviews

1. Increase modern rollout by 20%
2. Enable more function flags
3. Review and clean old logs
4. Check for any security events

## Troubleshooting

### High Error Rate

1. Check recent changes
2. Review error logs for patterns
3. Temporarily reduce rollout percentage
4. Disable recently enabled functions

### Performance Issues

1. Check slow query log
2. Review caching settings
3. Look for memory limit warnings
4. Consider disabling performance monitoring temporarily

### Security Warnings

1. Review security event log
2. Check for repeated failed attempts
3. Verify all forms have CSRF tokens
4. Run security scan

## Emergency Rollback

If critical issues occur:

### Option 1: Disable Integration
```php
// In wp-config.php
define( 'MONEY_QUIZ_DISABLE_INTEGRATION', true );
```

### Option 2: Force Legacy Mode
```php
// In wp-config.php
define( 'MONEY_QUIZ_LEGACY_MODE', true );
```

### Option 3: Disable Specific Features
1. Go to Integration settings
2. Set rollout to 0%
3. Disable all function flags
4. Save settings

## Advanced Configuration

### Custom Settings

Create `wp-content/money-quiz-config.php`:

```php
<?php
return [
    'modern_rollout' => 50,
    'safety_features' => [
        'query_protection' => true,
        'input_sanitization' => true,
        'csrf_protection' => true,
        'error_logging' => true
    ],
    'function_flags' => [
        'mq_get_quiz_questions' => true,
        'mq_save_quiz_result' => true,
        // ... other functions
    ]
];
```

### Performance Tuning

For high-traffic sites:

```php
// Increase cache TTL
define( 'MONEY_QUIZ_CACHE_TTL', 7200 ); // 2 hours

// Increase rate limits
define( 'MONEY_QUIZ_RATE_LIMIT', 120 ); // 120 requests/minute

// Disable verbose logging
define( 'MONEY_QUIZ_VERBOSE_LOG', false );
```

## Next Steps

1. ✅ Complete initial setup
2. ✅ Run tests
3. ✅ Monitor for 48 hours
4. 📈 Gradually increase modern usage
5. 🎯 Achieve 100% modern implementation
6. 🧹 Remove legacy code (Phase 4)

## Support

- Check integration dashboard for real-time status
- Review error logs for detailed information
- Use Site Health for system diagnostics
- File issues on GitHub with logs attached

---

**Remember**: Start slow, monitor carefully, and increase gradually. The system is designed to fail safely back to legacy code if issues occur.
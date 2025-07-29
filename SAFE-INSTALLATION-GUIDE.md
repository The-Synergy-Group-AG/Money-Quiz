# Money Quiz Safe Installation Guide

## ⚠️ IMPORTANT: Safety First!

The Money Quiz plugin has been identified with **critical security vulnerabilities**. This guide ensures you can test it safely without risking your production site.

## Overview of Safety Features

This safe installation package includes:

1. **Safe Wrapper** (`money-quiz-safe-wrapper.php`) - Protects your site while running the plugin
2. **Pre-Installation Safety Checker** (`money-quiz-safety-check.php`) - Verifies safety before installation
3. **Quarantine Mode** - Prevents dangerous operations
4. **Real-time Monitoring** - Tracks all plugin activities
5. **One-Click Recovery** - Easy rollback if issues occur

## Step-by-Step Safe Installation

### Step 1: Pre-Installation Safety Check

1. **Upload the safety checker** to your WordPress root directory:
   ```
   /public_html/money-quiz-safety-check.php
   ```

2. **Run the safety check** by visiting:
   ```
   https://yoursite.com/money-quiz-safety-check.php
   ```

3. **Review the results**:
   - ✅ Score 80%+ = Relatively safe (still use caution)
   - ⚠️ Score 50-79% = Significant concerns (staging only)
   - ❌ Score <50% = DO NOT INSTALL

### Step 2: Prepare Safe Environment

1. **Create full backup**:
   ```bash
   # Database backup
   wp db export backup-before-moneyquiz.sql
   
   # Files backup
   tar -czf backup-before-moneyquiz.tar.gz wp-content/
   ```

2. **Set up staging site** (STRONGLY RECOMMENDED):
   - Never test on production first
   - Use a subdomain or local environment

### Step 3: Install with Safe Wrapper

1. **Upload the safe package**:
   ```
   /wp-content/plugins/money-quiz/
   ├── money-quiz-safe-wrapper.php  (main safe loader)
   ├── includes/
   │   ├── class-error-handler.php
   │   ├── class-notice-manager.php
   │   └── class-dependency-monitor.php
   ├── moneyquiz.php (original plugin)
   └── [other original files]
   ```

2. **Activate ONLY the safe wrapper**:
   - Go to Plugins page
   - Find "Money Quiz Safe Wrapper"
   - Click "Activate"
   - DO NOT activate the original "Money Quiz" plugin

### Step 4: Monitor Safe Mode

After activation, you'll see:

1. **Admin Notices**:
   - 🔴 **Critical (Red)**: Immediate action required
   - 🟡 **Warning (Yellow)**: Needs attention
   - 🔵 **Info (Blue)**: General information
   - ✅ **Success (Green)**: Things working correctly

2. **Safety Report Page**:
   - Go to: **Dashboard > MQ Safety Report**
   - View all safety checks and activity logs

### Step 5: Testing in Quarantine Mode

If critical issues are detected, the plugin enters **Quarantine Mode**:

- Original plugin code is NOT loaded
- Dangerous operations are blocked
- You can safely review issues
- Site remains protected

## What Each Safety Feature Does

### 1. Error Handler
- Catches all PHP errors from the plugin
- Prevents white screen of death
- Logs errors for debugging
- Shows friendly error messages

### 2. Notice Manager
- Real-time dependency monitoring
- Critical vs warning notifications
- Dismissible notices (7-day default)
- Action buttons for quick fixes

### 3. Dependency Monitor
- Checks PHP version (7.4+ required)
- Verifies WordPress compatibility
- Monitors file integrity
- Checks memory and execution limits

### 4. Safe Mode Features
- Sanitizes all $_GET, $_POST, $_REQUEST data
- Blocks dangerous SQL queries
- Monitors all WordPress actions
- Prevents unauthorized option changes

## Recovery Procedures

### If Something Goes Wrong:

1. **Via FTP/File Manager**:
   ```bash
   # Rename plugin folder to disable
   mv wp-content/plugins/money-quiz wp-content/plugins/money-quiz-disabled
   ```

2. **Via WP-CLI**:
   ```bash
   wp plugin deactivate money-quiz-safe-wrapper
   ```

3. **Database Cleanup** (if needed):
   ```sql
   -- Remove plugin options
   DELETE FROM wp_options WHERE option_name LIKE 'mq_%';
   DELETE FROM wp_options WHERE option_name LIKE 'moneyquiz_%';
   ```

## Monitoring Dashboard

The Safe Wrapper provides a monitoring dashboard showing:

- **Safety Score**: Real-time safety assessment
- **Activity Log**: All plugin actions
- **Resource Usage**: Memory and performance impact
- **Security Events**: Blocked operations

## Testing Checklist

Before using in production:

- [ ] Safety check score is acceptable (80%+)
- [ ] No critical errors in admin notices
- [ ] All plugin features work as expected
- [ ] No performance degradation
- [ ] No security warnings in logs
- [ ] Backup restoration tested
- [ ] Monitoring shows normal activity

## When NOT to Install

DO NOT install if you see:

- 🔴 SQL injection vulnerabilities detected
- 🔴 Dangerous PHP functions (eval, exec, etc.)
- 🔴 Hardcoded API keys or passwords
- 🔴 File permission errors
- 🔴 Safety score below 50%

## Support and Reporting

If you encounter issues:

1. **Check the Safety Report**: Dashboard > MQ Safety Report
2. **Review Error Logs**: `wp-content/debug.log`
3. **Export Activity Log**: For developer review
4. **Document Issues**: Screenshot errors and warnings

## Final Safety Tips

1. **Never skip the safety check**
2. **Always test on staging first**
3. **Keep backups before any changes**
4. **Monitor for 24-48 hours after activation**
5. **Have a rollback plan ready**

---

## Quick Commands Reference

```bash
# Check plugin status
wp plugin list | grep money-quiz

# View recent errors
tail -f wp-content/debug.log | grep -i "money quiz"

# Disable via database
wp db query "UPDATE wp_options SET option_value='a:0:{}' WHERE option_name='active_plugins'"

# Emergency disable
mv wp-content/plugins/money-quiz wp-content/plugins/money-quiz.disabled
```

---

**Remember**: This wrapper makes the plugin SAFER but not SAFE. The underlying security issues still exist and should be fixed by the developer before production use.

Last Updated: January 2025
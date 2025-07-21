# MoneyQuiz Plugin Deployment Guide

## 🚨 Critical Failure Prevention

This guide ensures that the critical failure that occurred previously will **NEVER** happen again. All deployment steps include comprehensive checks and safeguards.

## Pre-Deployment Checklist

### 1. Dependencies Check
- ✅ Composer dependencies installed (`composer install`)
- ✅ Vendor directory present
- ✅ Autoloader file exists (`vendor/autoload.php`)
- ✅ All critical PHP files have valid syntax

### 2. Environment Requirements
- ✅ PHP 7.4 or higher
- ✅ WordPress 5.0 or higher (recommended)
- ✅ Composer installed on server
- ✅ Proper file permissions

## Automated Deployment Process

### Option 1: Using the Deployment Script (Recommended)

```bash
# Make script executable (if not already)
chmod +x deploy.sh

# Run the deployment script
./deploy.sh
```

The deployment script will:
1. ✅ Check PHP version compatibility
2. ✅ Verify Composer availability
3. ✅ Install/update Composer dependencies
4. ✅ Verify vendor directory and autoloader
5. ✅ Run PHP syntax checks on all critical files
6. ✅ Execute comprehensive deployment checker
7. ✅ Verify file permissions
8. ✅ Create deployment manifest
9. ✅ Final verification

### Option 2: Manual Deployment

```bash
# 1. Install dependencies
composer install --no-dev --optimize-autoloader

# 2. Run deployment checker
php deployment-checker.php

# 3. Verify critical files
ls -la vendor/autoload.php moneyquiz.php
```

## Admin Notices System

The plugin now includes a comprehensive admin notice system that will warn you about missing dependencies **before** they cause critical failures:

### Critical Notices (Red)
- 🚨 Missing Composer autoloader
- 🚨 Missing vendor directory
- 🚨 Missing critical plugin files

### Warning Notices (Yellow)
- ⚠️ PHP version compatibility issues
- ⚠️ WordPress version compatibility issues
- ⚠️ Missing database tables

### Features
- ✅ **One-click Composer install** from admin panel
- ✅ **Dismissible notices** (dismissed for 7 days)
- ✅ **Real-time dependency monitoring**
- ✅ **Detailed error reporting**

## Deployment Safeguards

### 1. Error Handling
All enhanced features are wrapped in try-catch blocks:
```php
try {
    // Load features
    Money_Quiz_Integration_Loader::load_features();
} catch (Exception $e) {
    error_log('MoneyQuiz Error: ' . $e->getMessage());
    // Plugin continues to work without enhanced features
}
```

### 2. Safe File Loading
All dynamic file includes use safe loading:
```php
private static function safe_require_once($file_path) {
    if (file_exists($file_path)) {
        try {
            require_once $file_path;
        } catch (Exception $e) {
            error_log('MoneyQuiz: Failed to load ' . $file_path);
        }
    }
}
```

### 3. Graceful Degradation
If enhanced features fail to load, the core plugin continues to function:
- ✅ Core quiz functionality remains available
- ✅ Admin interface continues to work
- ✅ Database operations remain functional
- ✅ Only enhanced features are disabled

## Post-Deployment Verification

### 1. Check Admin Notices
After deployment, check the WordPress admin for any dependency notices:
- Go to WordPress Admin → Money Quiz
- Look for any red or yellow notices
- Click "Run Composer Install" if needed

### 2. Test Core Functionality
- ✅ Plugin activates without errors
- ✅ Admin menu appears
- ✅ Quiz shortcode works
- ✅ Database tables are created

### 3. Monitor Error Logs
Check for any MoneyQuiz-related errors:
```bash
tail -f /path/to/wordpress/wp-content/debug.log
```

## Troubleshooting

### Critical Error: "Missing Composer Autoloader"
**Solution:**
```bash
cd /path/to/wordpress/wp-content/plugins/money-quiz
composer install --no-dev --optimize-autoloader
```

### Critical Error: "Vendor Directory Missing"
**Solution:**
```bash
cd /path/to/wordpress/wp-content/plugins/money-quiz
composer install
```

### Plugin Won't Activate
**Check:**
1. PHP version (must be 7.4+)
2. WordPress version (must be 5.0+)
3. File permissions (must be readable)
4. Syntax errors in PHP files

### Enhanced Features Not Working
**Check:**
1. Admin notices for missing dependencies
2. Error logs for specific failure reasons
3. Run deployment checker: `php deployment-checker.php`

## CI/CD Integration

### GitHub Actions Example
```yaml
name: Deploy MoneyQuiz Plugin
on:
  push:
    branches: [main]

jobs:
  deploy:
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v2
      - uses: actions/setup-php@v2
        with:
          php-version: '7.4'
      - name: Install Composer
        run: |
          curl -sS https://getcomposer.org/installer | php
          sudo mv composer.phar /usr/local/bin/composer
      - name: Deploy Plugin
        run: |
          cd money-quiz
          chmod +x deploy.sh
          ./deploy.sh
```

### Manual Deployment Checklist
- [ ] Run `./deploy.sh` or `composer install`
- [ ] Verify `vendor/autoload.php` exists
- [ ] Check for admin notices after activation
- [ ] Test core functionality
- [ ] Monitor error logs for 24 hours

## Emergency Recovery

If a critical failure occurs:

1. **Immediate Action:**
   ```bash
   cd /path/to/wordpress/wp-content/plugins/money-quiz
   composer install --no-dev --optimize-autoloader
   ```

2. **Check Admin Notices:**
   - Go to WordPress Admin
   - Look for MoneyQuiz dependency notices
   - Click "Run Composer Install" if available

3. **Verify Recovery:**
   ```bash
   php deployment-checker.php
   ```

4. **If Still Failing:**
   - Check error logs
   - Verify file permissions
   - Ensure PHP 7.4+ is installed
   - Contact support with error details

## Support

If you encounter any issues:
1. Check this deployment guide
2. Run the deployment checker: `php deployment-checker.php`
3. Check admin notices in WordPress
4. Review error logs
5. Contact support with specific error messages

---

**Remember:** The critical failure prevention system is designed to catch issues **before** they cause site crashes. Always run the deployment checks and monitor admin notices after deployment. 
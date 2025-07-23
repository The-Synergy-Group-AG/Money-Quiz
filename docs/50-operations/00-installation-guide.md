# Money Quiz v7.0 - Installation Guide

## Document Control
- **Version**: 1.0
- **Last Updated**: 2025-07-23
- **Status**: Draft
- **Audience**: System Administrators

## Requirements

### Minimum Requirements
- **WordPress**: 6.0 or higher
- **PHP**: 8.0 or higher (8.2+ recommended)
- **MySQL**: 5.7+ or MariaDB 10.3+
- **Memory**: 128MB PHP memory limit
- **Disk Space**: 50MB available

### Recommended Requirements
- **WordPress**: Latest version
- **PHP**: 8.2 or higher
- **MySQL**: 8.0+ or MariaDB 10.5+
- **Memory**: 256MB PHP memory limit
- **PHP Extensions**: 
  - curl
  - json
  - mbstring
  - openssl

## Installation Methods

### Method 1: WordPress Admin (Recommended)

1. **Download Plugin**
   - Download `money-quiz-v7.zip` from official source

2. **Upload via Admin**
   - Navigate to **Plugins → Add New**
   - Click **Upload Plugin**
   - Choose `money-quiz-v7.zip`
   - Click **Install Now**

3. **Activate Plugin**
   - Click **Activate Plugin** after installation
   - Or navigate to **Plugins** and activate

### Method 2: FTP Upload

1. **Extract Files**
   ```bash
   unzip money-quiz-v7.zip
   ```

2. **Upload to Server**
   ```bash
   # Using FTP client or command line
   ftp> cd /wp-content/plugins/
   ftp> put -r money-quiz/
   ```

3. **Set Permissions**
   ```bash
   chmod -R 755 money-quiz/
   chmod -R 644 money-quiz/*.php
   ```

4. **Activate in Admin**
   - Go to **Plugins** in WordPress admin
   - Find **Money Quiz** and click **Activate**

### Method 3: Composer

1. **Add to composer.json**
   ```json
   {
       "require": {
           "moneyquiz/money-quiz": "^7.0"
       }
   }
   ```

2. **Install**
   ```bash
   composer update
   ```

3. **Activate**
   ```bash
   wp plugin activate money-quiz
   ```

## Post-Installation Setup

### 1. Verify Installation
- Check **Plugins** page for active status
- Look for **Money Quiz** in admin menu
- Verify no error messages

### 2. Initial Configuration
1. Navigate to **Money Quiz → Settings**
2. Configure basic settings:
   - Site name
   - Admin email
   - Default options

### 3. Database Setup
- Tables created automatically on activation
- Verify tables exist:
  ```sql
  SHOW TABLES LIKE 'wp_money_quiz_%';
  ```

### 4. File Permissions
Ensure these directories are writable:
```bash
wp-content/uploads/money-quiz-logs/
wp-content/uploads/money-quiz-exports/
wp-content/uploads/money-quiz-temp/
```

## Multisite Installation

### Network Activation
1. **Network Admin**: Navigate to **Plugins**
2. Click **Network Activate** for Money Quiz
3. Configure per-site or network-wide settings

### Per-Site Activation
1. Go to individual site admin
2. Navigate to **Plugins**
3. Activate Money Quiz for specific site

## Troubleshooting Installation

### Common Issues

#### White Screen of Death
- **Cause**: PHP memory limit
- **Fix**: Increase memory limit in wp-config.php
  ```php
  define('WP_MEMORY_LIMIT', '256M');
  ```

#### Database Errors
- **Cause**: Missing CREATE privileges
- **Fix**: Grant proper database permissions
  ```sql
  GRANT ALL PRIVILEGES ON database.* TO 'user'@'localhost';
  ```

#### Missing Admin Menu
- **Cause**: Capability issues
- **Fix**: Re-save permalinks, check user role

#### PHP Version Error
- **Cause**: PHP < 8.0
- **Fix**: Upgrade PHP version

### Debug Mode
Enable for troubleshooting:
```php
// wp-config.php
define('WP_DEBUG', true);
define('WP_DEBUG_LOG', true);
define('MONEY_QUIZ_DEBUG', true);
```

## Uninstallation

### Preserve Data
1. Navigate to **Money Quiz → Settings**
2. Uncheck "Remove data on uninstall"
3. Deactivate and delete plugin

### Complete Removal
1. Navigate to **Money Quiz → Settings**
2. Check "Remove data on uninstall"
3. Deactivate plugin
4. Delete plugin
5. All data will be removed

### Manual Cleanup
If needed, remove manually:
```sql
DROP TABLE IF EXISTS wp_money_quiz_quizzes;
DROP TABLE IF EXISTS wp_money_quiz_questions;
DROP TABLE IF EXISTS wp_money_quiz_results;
-- etc for all tables

DELETE FROM wp_options WHERE option_name LIKE 'money_quiz_%';
```

## Migration from Previous Versions

### From v3.x to v7.0
1. **Backup Everything** ⚠️
2. Export data using v3 export tool
3. Deactivate v3
4. Install v7
5. Use migration tool in **Money Quiz → Tools → Migrate**

### Data Compatibility
- v7.0 can import v3.x data
- Some features may need reconfiguration
- Custom code needs updating

## Server Optimization

### PHP Configuration
```ini
memory_limit = 256M
max_execution_time = 300
post_max_size = 64M
upload_max_filesize = 64M
```

### MySQL Optimization
```sql
-- Add indexes for performance
ALTER TABLE wp_money_quiz_results ADD INDEX idx_user_date (user_id, created_at);
ALTER TABLE wp_money_quiz_responses ADD INDEX idx_result_question (result_id, question_id);
```

### Caching
- Object caching recommended (Redis/Memcached)
- Page caching compatible
- CDN friendly

## Health Check

After installation, verify:
- ✅ Plugin activated
- ✅ Database tables created
- ✅ No PHP errors
- ✅ Admin menu accessible
- ✅ Cron jobs scheduled
- ✅ Email sending works

---
*For additional help, see [Troubleshooting Guide](../40-implementation/07-troubleshooting.md)*
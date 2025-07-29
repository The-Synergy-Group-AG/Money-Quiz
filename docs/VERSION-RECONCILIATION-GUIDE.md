# Money Quiz Version Reconciliation System

## Overview

The Money Quiz Version Reconciliation System is designed to address the version chaos issue by detecting and reconciling version mismatches between different components of the plugin. It provides automatic alignment of all versions to v4.0.0.

## Components

### 1. Version Manager (`class-version-manager.php`)
- Detects versions across all plugin components
- Identifies version mismatches
- Provides reconciliation plans
- Executes version alignment

### 2. Version Migration (`class-version-migration.php`)
- Handles progressive migration through version history
- Manages the upgrade path: v1.4 → v2.x → v3.3 → v4.0
- Executes database schema updates
- Migrates settings and data

### 3. Database Version Tracker (`class-database-version-tracker.php`)
- Tracks database schema versions
- Detects schema inconsistencies
- Provides database integrity verification
- Handles database repairs

### 4. Version Consistency Checker (`class-version-consistency-checker.php`)
- Monitors version consistency across components
- Performs regular health checks
- Generates consistency scores
- Provides recommendations for fixes

### 5. Version Reconciliation Initializer (`class-version-reconciliation-init.php`)
- Initializes the reconciliation system
- Manages admin interface
- Handles automatic reconciliation
- Provides AJAX endpoints

## Version Detection Sources

The system detects versions from multiple sources:

1. **Plugin Header**: Version declared in the main plugin file
2. **Code Implementation**: Based on classes, functions, and constants present
3. **Database Schema**: Based on tables and columns present
4. **Stored Options**: Version values stored in WordPress options
5. **File Structure**: Based on directory and file organization

## Usage

### Admin Interface

Navigate to **Money Quiz → Version Management** in the WordPress admin to:
- View current version status
- Check consistency scores
- Run reconciliation
- Repair database issues

### WP-CLI Commands

```bash
# Check version alignment
wp money-quiz version check

# Reconcile versions (dry run)
wp money-quiz version reconcile --dry-run

# Execute reconciliation
wp money-quiz version reconcile

# Run consistency check
wp money-quiz version consistency

# Check database version
wp money-quiz version database

# Repair database
wp money-quiz version repair-database

# View migration history
wp money-quiz version history
```

### Programmatic Usage

```php
// Get version manager instance
$version_manager = Money_Quiz_Version_Manager::instance();

// Check versions
$report = $version_manager->get_version_report();

// Run reconciliation
$results = $version_manager->reconcile_versions();

// Check consistency
$consistency_checker = Money_Quiz_Version_Consistency_Checker::instance();
$consistency_results = $consistency_checker->run_check();
```

## Version Mapping

The system recognizes these version progressions:

- **v1.4**: Original version with basic quiz functionality
- **v2.0-2.5**: Added reporting, integrations, custom fields
- **v3.0-3.3**: Multi-quiz support, analytics, API integration
- **v4.0**: Modern architecture with safety features

## Automatic Reconciliation

The system can automatically reconcile versions when:
- Critical mismatches are detected
- Plugin is activated or updated
- Manual trigger via admin or CLI

## Safety Features

1. **Backup Recommendations**: Always backup before reconciliation
2. **Dry Run Mode**: Test reconciliation without making changes
3. **Rollback Support**: Version history tracking for recovery
4. **Progressive Migration**: Step-by-step upgrades to prevent data loss

## Troubleshooting

### Common Issues

1. **Version Detection Fails**
   - Check file permissions
   - Ensure all required files are present
   - Review error logs

2. **Database Migration Errors**
   - Verify database user permissions
   - Check for table locks
   - Review MySQL version compatibility

3. **Reconciliation Timeout**
   - Increase PHP execution time
   - Run reconciliation via CLI
   - Break into smaller migrations

### Debug Mode

Enable debug logging:
```php
define( 'WP_DEBUG', true );
define( 'WP_DEBUG_LOG', true );
```

Check logs at: `wp-content/debug.log`

## Best Practices

1. **Regular Checks**: Run consistency checks weekly
2. **Before Updates**: Always check versions before plugin updates
3. **After Migration**: Verify all components after reconciliation
4. **Monitor Logs**: Review activity logs for issues

## API Reference

### Version Manager Methods

```php
// Detect all versions
$versions = $version_manager->detect_versions();

// Get mismatches
$mismatches = $version_manager->get_mismatches();

// Get reconciliation plan
$plan = $version_manager->get_reconciliation_plan();

// Execute reconciliation
$results = $version_manager->reconcile_versions();
```

### Database Tracker Methods

```php
// Get current database version
$version = $db_tracker->get_current_version();

// Verify integrity
$integrity = $db_tracker->verify_integrity();

// Repair database
$repairs = $db_tracker->repair_database();

// Get version history
$history = $db_tracker->get_version_history();
```

### Consistency Checker Methods

```php
// Run consistency check
$results = $consistency_checker->run_check();

// Get last check results
$last_results = $consistency_checker->get_last_check_results();
```

## Support

For issues or questions:
1. Check the troubleshooting section
2. Review error logs
3. Use WP-CLI commands for detailed diagnostics
4. Contact support with version report export
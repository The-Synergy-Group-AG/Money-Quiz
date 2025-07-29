# Money Quiz Menu Redesign Documentation

## Version 4.2.0

### Table of Contents
1. [Overview](#overview)
2. [Architecture](#architecture)
3. [Menu Structure](#menu-structure)
4. [Implementation Guide](#implementation-guide)
5. [Safety Features](#safety-features)
6. [Migration Guide](#migration-guide)
7. [Developer Reference](#developer-reference)
8. [Troubleshooting](#troubleshooting)

---

## Overview

The Money Quiz menu redesign introduces a workflow-centric navigation system that organizes features into logical sections, improving user experience and productivity.

### Key Benefits
- **Workflow-Based Organization**: Features grouped by common tasks
- **Enhanced Navigation**: Hierarchical structure with clear pathways
- **Improved Discoverability**: Related features are easier to find
- **Modern UI**: Clean, responsive design with better visual hierarchy
- **Safe Rollout**: Gradual deployment with rollback capabilities

### Design Principles
1. **Task-Oriented**: Menu organized around user workflows
2. **Progressive Disclosure**: Show relevant options at the right time
3. **Consistency**: Uniform navigation patterns across sections
4. **Performance**: Fast loading with optimized assets
5. **Accessibility**: WCAG 2.1 compliant navigation

---

## Architecture

### File Structure
```
includes/admin/
├── class-menu-redesign.php           # Main menu implementation
└── menu-redesign/
    ├── class-compatibility-layer.php  # Legacy support
    ├── class-migration-notice.php     # User notifications
    ├── test-quiz-functionality.php    # Functionality tests
    ├── test-menu-navigation.php       # Navigation tests
    ├── safe-mode-config.php          # Testing configuration
    └── templates/
        ├── dashboard-*.php           # Dashboard templates
        ├── quizzes-*.php            # Quiz management templates
        ├── audience-*.php           # Audience templates
        ├── marketing-*.php          # Marketing templates
        └── settings-*.php           # Settings templates

assets/
├── css/
│   ├── menu-redesign.css            # Main styles
│   ├── menu-redesign.min.css        # Minified styles
│   └── migration-notice.css         # Notice styles
└── js/
    ├── menu-redesign.js             # Main JavaScript
    ├── menu-redesign.min.js         # Minified JS
    └── migration-notice.js          # Notice functionality
```

### Class Structure

#### Menu_Redesign
Main class handling menu registration and management.

```php
class Menu_Redesign {
    private $menu_structure = [];      // Menu hierarchy definition
    private $legacy_mappings = [];     // Old to new URL mappings
    
    public function init()             // Initialize menu system
    public function register_menus()   // Register WordPress menus
    public function handle_legacy_redirects() // Handle old URLs
}
```

#### MoneyQuiz_Compatibility_Layer
Ensures backward compatibility and safe operation.

```php
class MoneyQuiz_Compatibility_Layer {
    public function verify_critical_functionality()  // Pre-flight checks
    public function handle_legacy_urls()            // URL redirection
    public function emergency_disable()             // Kill switch
}
```

---

## Menu Structure

### Hierarchy

```
Money Quiz
├── Dashboard
│   ├── Overview          (Main dashboard view)
│   ├── Recent Activity   (Activity timeline)
│   ├── Quick Stats       (Performance metrics)
│   └── System Health     (System status)
│
├── Quizzes
│   ├── All Quizzes      (Quiz management)
│   ├── Add New          (Create quiz)
│   ├── Questions        (Question bank)
│   └── Archetypes       (Result types)
│
├── Audience
│   ├── Results          (Analytics & reports)
│   ├── Prospects/Leads  (Lead management)
│   └── Campaigns        (Email campaigns)
│
├── Marketing
│   ├── Call-to-Actions  (CTA management)
│   └── Pop-ups          (Popup builder)
│
└── Settings
    ├── General          (Basic settings)
    ├── Email            (Email configuration)
    ├── Integrations     (Third-party services)
    ├── Security         (Security settings)
    └── Advanced         (Advanced options)
```

### Legacy Menu Mapping

| Old Menu Item | New Location |
|---------------|--------------|
| money_quiz | Dashboard → Overview |
| mq_question | Quizzes → Questions |
| mq_archetypes | Quizzes → Archetypes |
| mq_leads | Audience → Prospects |
| mq_stats | Audience → Results |
| mq_integration | Settings → Integrations |
| email_setting | Settings → Email |

---

## Implementation Guide

### Enabling the New Menu

#### Method 1: Through Settings
1. Navigate to **Money Quiz → Settings → General**
2. Check "Enable redesigned menu system"
3. Set rollout percentage (0-100%)
4. Save settings

#### Method 2: Programmatically
```php
// Enable for all users
update_option( 'money_quiz_menu_redesign_enabled', 1 );
update_option( 'money_quiz_menu_rollout_percentage', 100 );

// Enable for specific percentage
update_option( 'money_quiz_menu_rollout_percentage', 25 ); // 25% of users
```

#### Method 3: Constants
```php
// In wp-config.php or plugin file
define( 'MONEY_QUIZ_NEW_MENU', true );
define( 'MONEY_QUIZ_MENU_ROLLOUT', 50 ); // 50% rollout
```

### Safe Mode Testing

Enable safe mode for controlled testing:

```php
// Time-limited testing (24 hours)
update_option( 'mq_safe_mode_enabled', true );
update_option( 'mq_safe_mode_expiry', time() + DAY_IN_SECONDS );

// User-specific testing
update_option( 'mq_safe_mode_users', [ 1, 42, 99 ] ); // User IDs

// Role-based testing
update_option( 'mq_safe_mode_roles', [ 'administrator', 'editor' ] );
```

### Emergency Disable

Three ways to disable the new menu in emergencies:

1. **URL Parameter**: Add `?mq_emergency_disable=1` to any admin URL
2. **Constant**: Define `MONEY_QUIZ_EMERGENCY_DISABLE` as true
3. **Database**: Set option `money_quiz_menu_emergency_disable` to 1

---

## Safety Features

### Pre-Flight Checks
Before enabling, the system verifies:
- Database tables exist and are accessible
- Core quiz functionality is operational
- Required files are present
- No critical errors in error log

### Rollback Mechanisms
- Automatic rollback on critical errors
- Manual rollback via emergency disable
- Gradual rollout with percentage control
- User-specific enable/disable

### Error Monitoring
```php
// Error threshold configuration
define( 'MONEY_QUIZ_ERROR_THRESHOLD', 10 ); // Errors before auto-disable
define( 'MONEY_QUIZ_ERROR_WINDOW', 300 );   // 5-minute window
```

### Compatibility Layer Features
- Legacy URL redirection
- Function aliasing for backward compatibility
- Database query fallbacks
- Hook compatibility maintenance

---

## Migration Guide

### For Users

#### First Time Setup
1. **Backup**: Always backup before major changes
2. **Enable Safe Mode**: Test with limited users first
3. **Run Tests**: Use built-in test suites
4. **Gradual Rollout**: Start with 10-25% of users
5. **Monitor**: Check for issues and user feedback
6. **Full Deployment**: Increase to 100% when stable

#### What Changes
- Menu location in WordPress admin
- Some pages have new URLs
- Enhanced features on existing pages
- New quick actions and shortcuts

#### What Stays the Same
- All quiz functionality
- Database structure
- Shortcodes and frontend
- API endpoints
- Email functionality

### For Developers

#### Hook Changes
```php
// Old hook
do_action( 'money_quiz_menu_page' );

// New hook with backward compatibility
do_action( 'money_quiz_dashboard_overview' );
do_action( 'money_quiz_menu_page' ); // Still fires for compatibility
```

#### Function Updates
```php
// Old function
money_quiz_get_menu_url( 'mq_leads' );

// New function with fallback
money_quiz_get_menu_url( 'audience/prospects' );
// OR use legacy slug - automatically mapped
money_quiz_get_menu_url( 'mq_leads' ); // Returns new URL
```

#### Custom Integrations
If you have custom code hooking into Money Quiz menus:

1. Update menu slugs to new structure
2. Or rely on automatic mapping
3. Test thoroughly in safe mode
4. Update documentation

---

## Developer Reference

### Actions & Filters

#### Filters
```php
// Control menu enablement
add_filter( 'money_quiz_menu_enabled', function( $enabled ) {
    return $enabled && current_user_can( 'manage_options' );
});

// Modify menu structure
add_filter( 'money_quiz_menu_structure', function( $structure ) {
    // Add custom section
    $structure['custom'] = [
        'title' => 'Custom Section',
        'capability' => 'manage_options',
        // ... configuration
    ];
    return $structure;
});

// Control rollout percentage
add_filter( 'money_quiz_menu_rollout_percentage', function( $percentage ) {
    return is_super_admin() ? 100 : $percentage;
});
```

#### Actions
```php
// Before menu registration
do_action( 'money_quiz_before_menu_register' );

// After menu registration
do_action( 'money_quiz_after_menu_register' );

// On legacy redirect
do_action( 'money_quiz_legacy_redirect', $old_slug, $new_slug );

// On menu page render
do_action( 'money_quiz_render_page', $page_slug );
```

### JavaScript API

#### Global Object
```javascript
window.MoneyQuizAdmin = {
    Dashboard: {},    // Dashboard functionality
    Quizzes: {},     // Quiz management
    Results: {},     // Results/audience features
    Marketing: {},   // Marketing tools
    Settings: {},    // Settings management
    Utils: {}        // Utility functions
};
```

#### Key Functions
```javascript
// Refresh dashboard stats
MoneyQuizAdmin.Dashboard.refreshStats();

// Add new question
MoneyQuizAdmin.Quizzes.addQuestion(questionData);

// Export results
MoneyQuizAdmin.Results.exportResults('csv');

// Show notification
MoneyQuizAdmin.Utils.showNotice('Success!', 'success');
```

### CSS Classes

#### Layout Classes
- `.mq-card` - Card container
- `.mq-dashboard-grid` - Dashboard grid layout
- `.mq-stat-card` - Statistics card
- `.mq-quick-actions` - Quick action buttons

#### State Classes
- `.mq-loading` - Loading state
- `.mq-success` - Success state
- `.mq-error` - Error state
- `.mq-disabled` - Disabled state

#### Section Classes
- `.mq-section-dashboard` - Dashboard pages
- `.mq-section-quizzes` - Quiz pages
- `.mq-section-audience` - Audience pages
- `.mq-section-marketing` - Marketing pages
- `.mq-section-settings` - Settings pages

---

## Troubleshooting

### Common Issues

#### Menu Not Appearing
1. Check if menu is enabled in settings
2. Verify user has required capabilities
3. Check rollout percentage includes current user
4. Look for JavaScript errors in console
5. Verify no plugin conflicts

#### Legacy URLs Not Redirecting
1. Ensure compatibility layer is loaded
2. Check legacy mappings are defined
3. Verify no redirect loops
4. Test with `?mq_debug=1` parameter

#### Performance Issues
1. Check if assets are loading correctly
2. Verify no duplicate script loading
3. Review browser console for errors
4. Test with minimal plugins active

### Debug Mode

Enable debug mode for detailed logging:

```php
// In wp-config.php
define( 'MONEY_QUIZ_DEBUG', true );
define( 'MONEY_QUIZ_DEBUG_MENU', true );
```

This will:
- Log all menu operations
- Show detailed error messages
- Display performance metrics
- Enable verbose JavaScript logging

### Testing Commands

#### Run Navigation Tests
```
/wp-admin/admin.php?page=money-quiz-navigation-tests
```

#### Run Functionality Tests
```
/wp-admin/admin.php?page=money-quiz-tests
```

#### Force Specific Menu Version
```
// Force new menu
/wp-admin/admin.php?mq_force_new_menu=1

// Force legacy menu
/wp-admin/admin.php?mq_force_legacy_menu=1
```

### Getting Help

1. **Check Error Logs**: Look in `/wp-content/debug.log`
2. **Run Test Suites**: Use built-in testing tools
3. **Enable Debug Mode**: Get detailed information
4. **Review Documentation**: Check this guide
5. **Contact Support**: Include test results and logs

### Rollback Procedure

If issues occur:

1. **Emergency Disable**: Add `?mq_emergency_disable=1` to URL
2. **Or via Database**:
   ```sql
   UPDATE wp_options 
   SET option_value = '0' 
   WHERE option_name = 'money_quiz_menu_redesign_enabled';
   ```
3. **Or via Code**:
   ```php
   define( 'MONEY_QUIZ_EMERGENCY_DISABLE', true );
   ```

---

## Best Practices

### Deployment
1. Always test in staging first
2. Use safe mode for initial testing
3. Start with small rollout percentage
4. Monitor error logs during rollout
5. Have rollback plan ready

### Customization
1. Use provided hooks and filters
2. Don't modify core files
3. Test customizations thoroughly
4. Document your changes
5. Follow WordPress coding standards

### Performance
1. Enqueue assets only where needed
2. Use minified versions in production
3. Leverage browser caching
4. Optimize database queries
5. Monitor page load times

---

## Changelog

### Version 4.2.0
- Initial menu redesign implementation
- Added hierarchical menu structure
- Implemented compatibility layer
- Created migration notice system
- Added comprehensive test suites
- Included emergency disable features

---

## Support

For additional help:
- Review test results at `/wp-admin/admin.php?page=money-quiz-tests`
- Check navigation tests at `/wp-admin/admin.php?page=money-quiz-navigation-tests`
- Enable debug mode for detailed logging
- Contact support with test results and error logs

---

*Last updated: Version 4.2.0*
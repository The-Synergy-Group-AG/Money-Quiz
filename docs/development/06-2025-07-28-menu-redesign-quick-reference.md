# Money Quiz Menu Redesign - Quick Reference

## Enable New Menu
```php
// Via Settings
Money Quiz → Settings → General → Enable redesigned menu system

// Via Code
update_option( 'money_quiz_menu_redesign_enabled', 1 );

// Via Constant
define( 'MONEY_QUIZ_NEW_MENU', true );
```

## Emergency Disable
```php
// Method 1: URL
/wp-admin/?mq_emergency_disable=1

// Method 2: Constant
define( 'MONEY_QUIZ_EMERGENCY_DISABLE', true );

// Method 3: Database
update_option( 'money_quiz_menu_emergency_disable', 1 );
```

## Menu Structure
```
Money Quiz
├── Dashboard (Overview, Activity, Stats, Health)
├── Quizzes (All, Add New, Questions, Archetypes)
├── Audience (Results, Prospects, Campaigns)
├── Marketing (CTAs, Pop-ups)
└── Settings (General, Email, Integrations, Security)
```

## Legacy URL Mapping
| Old | New |
|-----|-----|
| `admin.php?page=money_quiz` | `admin.php?page=money-quiz-dashboard-overview` |
| `admin.php?page=mq_question` | `admin.php?page=money-quiz-quizzes-questions` |
| `admin.php?page=mq_leads` | `admin.php?page=money-quiz-audience-prospects` |

## Key Functions
```php
// Check if new menu is enabled
if ( get_option( 'money_quiz_menu_redesign_enabled' ) ) {
    // New menu active
}

// Get new URL from legacy
$new_url = money_quiz_get_menu_url( 'mq_leads' ); // Returns new URL

// Add custom menu item
add_filter( 'money_quiz_menu_structure', function( $structure ) {
    $structure['custom'] = [ /* ... */ ];
    return $structure;
});
```

## JavaScript API
```javascript
// Refresh stats
MoneyQuizAdmin.Dashboard.refreshStats();

// Add question
MoneyQuizAdmin.Quizzes.addQuestion(data);

// Show notice
MoneyQuizAdmin.Utils.showNotice('Message', 'success');
```

## CSS Classes
- `.mq-card` - Card container
- `.mq-section-{name}` - Section identifier
- `.mq-loading` - Loading state
- `.mq-stat-card` - Statistics card

## Testing URLs
- Navigation Tests: `/wp-admin/admin.php?page=money-quiz-navigation-tests`
- Functionality Tests: `/wp-admin/admin.php?page=money-quiz-tests`
- Safe Mode Config: `/wp-admin/admin.php?page=money-quiz-safe-mode`

## Debug Mode
```php
define( 'MONEY_QUIZ_DEBUG', true );
define( 'MONEY_QUIZ_DEBUG_MENU', true );
```

## Keyboard Shortcuts
- `Ctrl/Cmd + K` - Global search
- `Ctrl/Cmd + N` - New quiz

## Support
- Always backup before enabling
- Test in safe mode first
- Start with 10-25% rollout
- Monitor error logs
- Have rollback plan ready
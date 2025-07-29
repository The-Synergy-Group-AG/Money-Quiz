# Legacy Function Mapping Document

## Overview
This document maps all legacy functions in the Money Quiz plugin to their modern equivalents, providing a clear migration path.

## Function Categories

### 1. Database Functions

| Legacy Function | Location | Modern Equivalent | Status | Risk |
|----------------|----------|-------------------|---------|------|
| `mq_get_all_prospects()` | moneyquiz.php:1245 | `ProspectRepository::find_all()` | Ready | HIGH - SQL Injection |
| `mq_save_prospect()` | moneyquiz.php:1289 | `ProspectRepository::create()` | Ready | HIGH - No sanitization |
| `mq_get_quiz_results()` | moneyquiz.php:1456 | `QuizRepository::get_results()` | Ready | MEDIUM |
| `mq_delete_prospect()` | moneyquiz.php:1512 | `ProspectRepository::delete()` | Ready | HIGH - No capability check |
| `mq_get_archetypes()` | quiz.moneycoach.php:234 | `ArchetypeRepository::find_all()` | Ready | LOW |
| `mq_save_quiz_taken()` | quiz.moneycoach.php:567 | `QuizService::save_submission()` | Ready | HIGH - No validation |

### 2. Quiz Processing Functions

| Legacy Function | Location | Modern Equivalent | Status | Risk |
|----------------|----------|-------------------|---------|------|
| `mq_questions_func()` | quiz.moneycoach.php:45 | `ShortcodeManager::render_quiz()` | Ready | MEDIUM |
| `mq_calculate_result()` | quiz.moneycoach.php:789 | `QuizService::calculate_archetype_scores()` | Ready | LOW |
| `mq_process_quiz()` | quiz.moneycoach.php:834 | `AjaxHandler::handle_submission()` | Ready | HIGH - CSRF vulnerable |
| `mq_get_quiz_questions()` | moneyquiz.php:1678 | `QuizRepository::get_questions()` | Ready | LOW |

### 3. Email Functions

| Legacy Function | Location | Modern Equivalent | Status | Risk |
|----------------|----------|-------------------|---------|------|
| `mq_send_result_email()` | moneyquiz.php:1789 | `EmailService::send_quiz_result()` | Ready | MEDIUM - No validation |
| `mq_send_admin_notification()` | moneyquiz.php:1834 | `EmailService::send_admin_notification()` | Ready | LOW |
| `mq_format_email_template()` | moneyquiz.php:1889 | `EmailService::render_template()` | Ready | LOW |

### 4. Admin Functions

| Legacy Function | Location | Modern Equivalent | Status | Risk |
|----------------|----------|-------------------|---------|------|
| `mq_admin_menu()` | moneyquiz.php:234 | `MenuManager::register_menus()` | Ready | LOW |
| `mq_prospects_page()` | prospects.admin.php:12 | `AdminController::prospects_page()` | Ready | MEDIUM |
| `mq_settings_page()` | moneyquiz.php:456 | `SettingsController::render()` | Ready | HIGH - No nonce |
| `mq_export_prospects()` | prospects.admin.php:234 | `ResultsController::export_csv()` | Ready | MEDIUM |

### 5. Utility Functions

| Legacy Function | Location | Modern Equivalent | Status | Risk |
|----------------|----------|-------------------|---------|------|
| `mq_get_option()` | moneyquiz.php:987 | `SettingsManager::get()` | Ready | LOW |
| `mq_update_option()` | moneyquiz.php:998 | `SettingsManager::set()` | Ready | MEDIUM - No validation |
| `mq_log_activity()` | moneyquiz.php:1123 | `Logger::log()` | Ready | LOW |
| `mq_clean_input()` | moneyquiz.php:1567 | `Legacy_Input_Sanitizer::sanitize_field()` | Ready | HIGH - Incomplete |

## Security Vulnerabilities by Function

### Critical (Immediate Action Required)
1. **`mq_save_prospect()`** - Direct SQL insertion without prepared statements
2. **`mq_delete_prospect()`** - No capability checks, SQL injection risk
3. **`mq_process_quiz()`** - No CSRF protection
4. **`mq_settings_page()`** - Missing nonce verification

### High Priority
1. **`mq_get_all_prospects()`** - Concatenated SQL queries
2. **`mq_save_quiz_taken()`** - No input validation
3. **`mq_clean_input()`** - Incomplete sanitization

### Medium Priority
1. **`mq_send_result_email()`** - Email validation missing
2. **`mq_export_prospects()`** - No rate limiting
3. **`mq_update_option()`** - No validation rules

## Migration Strategy

### Phase 1: Database Layer (Week 1)
1. Replace all direct `$wpdb->query()` calls with `mq_safe_db()`
2. Implement prepared statements for all queries
3. Add capability checks to all delete operations

### Phase 2: Input Validation (Week 1-2)
1. Add `mq_sanitize_input()` to all form handlers
2. Implement CSRF tokens on all forms
3. Add nonce verification to admin pages

### Phase 3: Function Routing (Week 2-3)
1. Enable Legacy_Function_Router for low-risk functions
2. Monitor error logs for issues
3. Gradually increase modern function usage

### Phase 4: Complete Migration (Week 3-4)
1. Replace high-risk functions entirely
2. Remove legacy function definitions
3. Update all function calls to use modern API

## Code Examples

### Before (Vulnerable):
```php
function mq_save_prospect($data) {
    global $wpdb, $table_prefix;
    $wpdb->query("INSERT INTO ".$table_prefix."mq_prospects 
                  SET email='".$data['email']."', 
                      name='".$data['name']."'");
}
```

### After (Secure):
```php
function mq_save_prospect($data) {
    $sanitized = mq_sanitize_input($data);
    return mq_safe_db()->safe_query(
        "INSERT INTO {$wpdb->prefix}mq_prospects (email, name) VALUES (%s, %s)",
        [$sanitized['email'], $sanitized['name']]
    );
}
```

## Testing Checklist

- [ ] All database queries use prepared statements
- [ ] All user inputs are sanitized
- [ ] All forms have CSRF protection
- [ ] All admin actions check capabilities
- [ ] Error handling doesn't expose sensitive data
- [ ] Modern functions maintain backward compatibility

## Rollback Plan

If issues occur:
1. Set `MONEY_QUIZ_LEGACY_MODE` to true
2. Disable Legacy_Function_Router
3. Clear all caches
4. Monitor error logs
5. Fix issues before re-enabling

---
*Last Updated: January 2025*
*Next Review: After Phase 2 completion*
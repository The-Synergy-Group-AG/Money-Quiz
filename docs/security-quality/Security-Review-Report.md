# Money Quiz Plugin Security Review Report

## Executive Summary
This security review identified several critical vulnerabilities in the Money Quiz WordPress plugin that require immediate attention. The most severe issues include SQL injection vulnerabilities, lack of proper input sanitization, missing CSRF protection, and potential XSS vulnerabilities.

## Critical Vulnerabilities Found

### 1. SQL Injection Vulnerabilities

#### Location: quiz.moneycoach.php
**Line 303:** Direct SQL query with unsanitized email input
```php
$results = $wpdb->get_row( "SELECT * FROM ".$table_prefix.TABLE_MQ_PROSPECTS." WHERE Email = '".$Email."'", OBJECT );
```
**Risk:** High - Allows attackers to execute arbitrary SQL commands
**Fix:** Use `$wpdb->prepare()` for all queries with user input

**Line 366:** Multiple unsanitized variables in SQL query
```php
$sql_qry = "SELECT mq_q.Master_ID, mq_q.Archetype, mq_q.Question, mq_q.ID_Unique, mq_r.Results_ID,  mq_r.Score, mq_r.Taken_ID FROM ".$table_prefix.TABLE_MQ_RESULTS." as mq_r LEFT JOIN ".$table_prefix.TABLE_MQ_MASTER." as mq_q on mq_q.Master_ID=mq_r.Master_ID WHERE  mq_r.Prospect_ID=".$prospect." and mq_r.Taken_ID IN($tid) ORDER BY mq_r.Taken_ID ASC ";
```

**Lines 335, 344:** Direct use of `$_GET['tid']` without sanitization
```php
array( 'Taken_ID' => $_GET['tid'] )
```

### 2. Cross-Site Scripting (XSS) Vulnerabilities

#### Multiple Locations
- **quiz.moneycoach.php Line 463:** Unescaped output in email content
```php
$header_image_email = "<div class='mq-header-image-container' ><img src='".$rs->Value."' class='mq-header-image' width='893' style='width:941px;'/></div>";
```

- **Throughout the codebase:** Direct output of database values without escaping
```php
$body = "Dear ".$Name.",<br>"; // Line 459
```

### 3. Missing CSRF Protection

#### Location: moneyquiz.php
**Lines 780-1080:** Multiple POST handlers without CSRF verification
```php
if(isset($_POST['is_display_email_signature_banner_image'])){
    $is_display_banner_image = $_POST['is_display_email_signature_banner_image'];
    // No CSRF check
}
```

While `wp_nonce_field()` is used in some forms, there's no corresponding `check_admin_referer()` or `wp_verify_nonce()` validation in the POST handlers.

### 4. Direct File Access Vulnerabilities

#### Location: Multiple files
The plugin files check for direct access but use a weak method:
```php
if ( !function_exists( 'add_action' ) ) {
    echo 'direct access is not allowed.';
    exit;
}
```
This still reveals that WordPress is being used and the file exists.

### 5. Input Validation Issues

#### Location: moneyquiz.php
**Lines 791-800:** No validation before database updates
```php
foreach($_POST['signature_email'] as $key_id=>$new_val){
    $new_val1 = $new_val;
    $wpdb->update( 
        $table_prefix.EMAIL_SIGNATURE, 
        array( 
            'value' => $new_val1
        ), 
        array( 'id' => $key_id )
    );
}
```

### 6. Insecure Data Handling

#### Location: moneyquiz.php
**Lines 35-36:** Hardcoded sensitive information
```php
define( 'MONEYQUIZ_BUSINESS_INSIGHTS_EMAIL', 'andre@101businessinsights.info' );
```

**Line 38:** Hardcoded API key
```php
define('MONEYQUIZ_SPECIAL_SECRET_KEY', '5bcd52f5276855.46942741');
```

### 7. Uninstall Security Issue

#### Location: moneyquiz.php
**Lines 79-84:** Direct table drops without permission check
```php
$wpdb->query( "DROP TABLE ".$table_prefix.TABLE_MQ_PROSPECTS );
```

### 8. JavaScript Security Issues

#### Location: assets/js/mq.js
**Lines 153-156, 172-176:** Hardcoded external URLs
```php
<img src='https://mindfulmoneycoaching.online/wp-content/plugins/moneyquiz/assets/images/mind-full-preloader.webp'
```
This creates a dependency on external resources and potential privacy issues.

## Medium Risk Issues

### 1. Weak Direct Access Prevention
All files use a basic check that still reveals information about the application structure.

### 2. No Data Sanitization in JavaScript
The JavaScript code doesn't properly sanitize user inputs before processing.

### 3. Missing Authorization Checks
Admin functions don't verify user capabilities before processing sensitive operations.

## Recommendations

### Immediate Actions Required:

1. **Fix SQL Injections:**
   - Use `$wpdb->prepare()` for ALL database queries with variables
   - Never concatenate user input directly into SQL strings

2. **Implement CSRF Protection:**
   - Add `check_admin_referer()` to all POST handlers
   - Verify nonces before processing any form submissions

3. **Sanitize All Inputs:**
   - Use WordPress sanitization functions appropriately
   - `sanitize_text_field()` for text inputs
   - `sanitize_email()` for email addresses
   - `intval()` for numeric inputs

4. **Escape All Output:**
   - Use `esc_html()`, `esc_attr()`, `esc_url()` for all dynamic output
   - Never trust data from the database

5. **Remove Hardcoded Sensitive Data:**
   - Move API keys to wp-config.php or use WordPress options
   - Remove hardcoded email addresses

6. **Implement Proper Authorization:**
   - Check user capabilities with `current_user_can()` before sensitive operations
   - Verify admin status for all admin functions

7. **Improve Direct Access Protection:**
   ```php
   if (!defined('ABSPATH')) {
       die;
   }
   ```

8. **Update JavaScript Security:**
   - Host all resources locally
   - Implement client-side input validation
   - Remove hardcoded URLs

## Code Examples for Fixes

### SQL Injection Fix:
```php
// Instead of:
$results = $wpdb->get_row( "SELECT * FROM ".$table_prefix.TABLE_MQ_PROSPECTS." WHERE Email = '".$Email."'", OBJECT );

// Use:
$results = $wpdb->get_row( 
    $wpdb->prepare(
        "SELECT * FROM {$table_prefix}mq_prospects WHERE Email = %s",
        $Email
    )
);
```

### CSRF Protection Fix:
```php
// In form:
wp_nonce_field('money_quiz_action', 'money_quiz_nonce');

// In handler:
if (!isset($_POST['money_quiz_nonce']) || !wp_verify_nonce($_POST['money_quiz_nonce'], 'money_quiz_action')) {
    die('Security check failed');
}
```

### XSS Prevention Fix:
```php
// Instead of:
echo $archetypes_data[1];

// Use:
echo esc_html($archetypes_data[1]);
```

## Conclusion

The Money Quiz plugin has several critical security vulnerabilities that could lead to complete site compromise. These issues should be addressed immediately before the plugin is used in a production environment. The most critical issues are the SQL injection vulnerabilities and missing CSRF protection, which could allow attackers to manipulate or extract sensitive data from the database.
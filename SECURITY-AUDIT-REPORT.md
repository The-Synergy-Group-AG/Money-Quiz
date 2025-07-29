# Money Quiz Plugin Security Audit Report

## Executive Summary
This security audit has identified multiple critical vulnerabilities in the Money Quiz plugin that pose significant risks to WordPress installations. The plugin contains severe SQL injection vulnerabilities, lacks proper input sanitization, and has inadequate CSRF protection in legacy code.

## Critical Findings

### 1. SQL Injection Vulnerabilities (CRITICAL)

#### Finding 1.1: Direct SQL Injection in Email Lookup
**File:** `quiz.moneycoach.php`
**Line:** 303
```php
$results = $wpdb->get_row( "SELECT * FROM ".$table_prefix.TABLE_MQ_PROSPECTS." WHERE Email = '".$Email."'", OBJECT );
```
**Issue:** User-supplied email is directly concatenated into SQL query without proper escaping.
**Risk:** Allows attackers to extract entire database contents or execute arbitrary SQL commands.

#### Finding 1.2: SQL Injection in Results Query
**File:** `quiz.moneycoach.php`
**Line:** 2326
```php
$sql_qry = "SELECT ... WHERE mq_r.Prospect_ID=".$prospect." and mq_r.Taken_ID IN($tid) ORDER BY mq_r.Taken_ID ASC ";
```
**Issue:** `$_REQUEST['prospect']` and `$_REQUEST['tid']` are used directly in SQL without sanitization.
**Risk:** Complete database compromise possible.

#### Finding 1.3: Multiple Unescaped Queries
**File:** `quiz.moneycoach.php`
**Lines:** 1247, 3103
- Similar patterns of direct variable concatenation in SQL queries
- No use of `$wpdb->prepare()` for parameterized queries

### 2. Cross-Site Scripting (XSS) Vulnerabilities (HIGH)

#### Finding 2.1: Unsanitized Request Variables
**File:** `quiz.moneycoach.php`
**Lines:** 2324-2325
```php
$tid = $_REQUEST['tid'];
$prospect = $_REQUEST['prospect'];
```
**Issue:** Direct use of `$_REQUEST` without sanitization
**Risk:** Allows injection of malicious JavaScript

#### Finding 2.2: Missing Output Escaping
Multiple files display user data without proper escaping functions like `esc_html()`, `esc_attr()`, or `esc_js()`.

### 3. CSRF Protection Issues (HIGH)

#### Finding 3.1: Missing Nonce Verification
- Legacy admin files lack proper nonce verification
- Form submissions can be forged by attackers
- Files affected: `prospects.admin.php`, `quiz.admin.php`, `questions.admin.php`

### 4. Authentication & Authorization Flaws (MEDIUM)

#### Finding 4.1: Insufficient Capability Checks
- Some admin functions lack proper `current_user_can()` checks
- Direct file access not consistently prevented

### 5. Exposed Sensitive Information (MEDIUM)

#### Finding 5.1: Database Structure Exposure
- Table creation queries in `class.moneyquiz.php` reveal complete database schema
- No obfuscation of table names or structure

#### Finding 5.2: File Paths Exposed
- Full server paths exposed in error messages
- Plugin directory structure visible in URLs

### 6. Input Validation Issues (HIGH)

#### Finding 6.1: Insufficient Email Validation
**File:** `quiz.moneycoach.php`
**Line:** 300
```php
$newmal = sanitize_text_field( $prospect_data['Email'], true );
```
**Issue:** `sanitize_text_field()` is insufficient for email validation
**Risk:** Allows invalid or malicious email formats

### 7. File Security Issues (MEDIUM)

#### Finding 7.1: Direct File Access
- Some PHP files lack proper `ABSPATH` checks
- Files can be accessed directly via URL

## Recommended Actions

### Immediate Actions Required:

1. **Fix SQL Injections**
   - Replace all direct SQL queries with prepared statements
   - Use `$wpdb->prepare()` for all database queries
   - Example fix:
   ```php
   $results = $wpdb->get_row( 
       $wpdb->prepare("SELECT * FROM %s WHERE Email = %s", 
           $table_prefix.TABLE_MQ_PROSPECTS, 
           $Email
       )
   );
   ```

2. **Implement Input Sanitization**
   - Use WordPress sanitization functions:
     - `sanitize_email()` for emails
     - `intval()` for IDs
     - `sanitize_text_field()` for text inputs
   - Validate all inputs before use

3. **Add CSRF Protection**
   - Implement nonce verification for all forms
   - Use `wp_nonce_field()` and `wp_verify_nonce()`

4. **Escape Output**
   - Use appropriate escaping functions for all output
   - `esc_html()`, `esc_attr()`, `esc_js()`, `esc_url()`

### Long-term Improvements:

1. **Security Review Process**
   - Implement code review for security
   - Use automated security scanning tools
   - Regular security audits

2. **Modern Architecture**
   - The new wrapper system shows improvement
   - Continue migration from legacy code
   - Implement proper MVC structure

3. **Documentation**
   - Document all security measures
   - Create security guidelines for developers
   - Maintain security changelog

## Severity Rating

- **Critical**: SQL Injection vulnerabilities require immediate patching
- **High**: XSS and CSRF issues need urgent attention
- **Medium**: Other issues should be addressed in next update

## Conclusion

The Money Quiz plugin contains severe security vulnerabilities that could lead to complete site compromise. The legacy code (particularly `quiz.moneycoach.php`) is the primary source of vulnerabilities. The newer wrapper system shows security improvements but the legacy code must be secured or removed entirely.

**Recommendation**: Do not use this plugin in production until all critical vulnerabilities are patched.

---
*Audit Date: January 29, 2025*
*Auditor: Security Analysis System*
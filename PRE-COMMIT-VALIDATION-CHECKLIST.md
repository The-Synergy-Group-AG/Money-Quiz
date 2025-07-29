# Pre-Commit Validation Checklist for Money Quiz v4.0

## 🔍 Essential Validation Workflows

### 1. Code Quality Checks

#### PHP Syntax Validation
```bash
# Check all PHP files for syntax errors
find . -name "*.php" -not -path "./vendor/*" -exec php -l {} \;
```

#### WordPress Coding Standards
```bash
# Run PHPCS with WordPress standards
./vendor/bin/phpcs --standard=WordPress --extensions=php --ignore=vendor,node_modules .
```

#### Static Analysis
```bash
# Run PHPStan for code quality
./vendor/bin/phpstan analyse --level=5 src includes
```

### 2. Security Validation

#### Security Scan
- [ ] No hardcoded passwords/keys
- [ ] All database queries use prepared statements
- [ ] Input sanitization on all user inputs
- [ ] Output escaping on all displays
- [ ] Nonce verification on all forms
- [ ] File upload restrictions in place

#### Vulnerability Check
```bash
# Check for known vulnerabilities
grep -r "eval(" --include="*.php" .
grep -r "exec(" --include="*.php" .
grep -r "system(" --include="*.php" .
grep -r "shell_exec(" --include="*.php" .
grep -r "\$_GET\[" --include="*.php" . | grep -v "sanitize"
grep -r "\$_POST\[" --include="*.php" . | grep -v "sanitize"
```

### 3. Functionality Testing

#### Core Features Test
- [ ] Plugin activates without errors
- [ ] Admin menu loads correctly
- [ ] Quiz creation works
- [ ] Quiz display functions
- [ ] Results calculation accurate
- [ ] Email notifications send (if enabled)

#### Routing System Test
- [ ] Modern system handles requests
- [ ] Fallback to legacy works
- [ ] Error monitoring active
- [ ] Rollback mechanism triggers

### 4. File Completeness Check

#### Required Files Present
- [ ] Main plugin file (money-quiz.php)
- [ ] Uninstall script (uninstall.php)
- [ ] README files
- [ ] License file
- [ ] All class files referenced
- [ ] All assets (CSS/JS) included
- [ ] All templates present

#### Build Requirements
- [ ] composer.json valid
- [ ] package.json (if needed)
- [ ] Build scripts documented

### 5. Documentation Validation

#### User Documentation
- [ ] Installation guide complete
- [ ] User manual updated
- [ ] Changelog current
- [ ] Known issues documented

#### Developer Documentation
- [ ] Code comments adequate
- [ ] API documentation
- [ ] Architecture diagrams
- [ ] Database schema

### 6. Version Consistency

#### Version Numbers Match
- [ ] Plugin header: 4.0.0
- [ ] README version: 4.0.0
- [ ] Constant definitions: 4.0.0
- [ ] Database version: 4.0.0
- [ ] Documentation: 4.0.0

### 7. Performance Validation

#### Resource Usage
- [ ] Memory usage < 128MB
- [ ] Page load time < 3s
- [ ] Database queries optimized
- [ ] No unnecessary autoloads

### 8. Compatibility Testing

#### WordPress Compatibility
- [ ] Tested with WP 5.8+
- [ ] No deprecated functions
- [ ] Multisite compatible
- [ ] No conflicts with common plugins

#### PHP Compatibility
- [ ] Works with PHP 7.4+
- [ ] No PHP 8.0 errors
- [ ] Type declarations consistent

## 🚀 Automated Validation Script

Create and run `validate-before-commit.sh`:

```bash
#!/bin/bash
echo "=== Money Quiz Pre-Commit Validation ==="

# 1. PHP Syntax Check
echo "Checking PHP syntax..."
find . -name "*.php" -not -path "./vendor/*" -exec php -l {} \; | grep -E "error|Error"

# 2. File Permissions
echo "Checking file permissions..."
find . -type f -name "*.php" -not -path "./vendor/*" ! -perm 644 -ls

# 3. Debug Code
echo "Checking for debug code..."
grep -r "var_dump\|print_r\|console\.log\|error_log" --include="*.php" --include="*.js" . | grep -v "^Binary"

# 4. TODO Comments
echo "Checking for TODO comments..."
grep -r "TODO\|FIXME\|XXX\|HACK" --include="*.php" . | wc -l

# 5. Version Consistency
echo "Checking version consistency..."
grep -r "Version:" --include="*.php" . | grep -v "4.0.0"

echo "=== Validation Complete ==="
```

## 📋 Manual Review Checklist

### Code Review
- [ ] No sensitive information exposed
- [ ] Proper error handling throughout
- [ ] Consistent coding style
- [ ] No code duplication
- [ ] Clear variable/function names

### Security Review
- [ ] SQL injection prevention verified
- [ ] XSS protection confirmed
- [ ] CSRF tokens implemented
- [ ] File permissions appropriate
- [ ] No backdoors or suspicious code

### Business Logic
- [ ] Quiz scoring algorithms correct
- [ ] Archetype assignments accurate
- [ ] Email templates appropriate
- [ ] Data privacy maintained
- [ ] No debug mode in production

## 🔧 Fix Common Issues

### If PHP Syntax Errors Found:
```bash
# Fix specific file
php -l path/to/file.php
```

### If Coding Standards Violations:
```bash
# Auto-fix where possible
./vendor/bin/phpcbf --standard=WordPress file.php
```

### If Missing Files:
```bash
# Check for broken references
grep -r "require\|include" --include="*.php" . | grep -v "vendor"
```

## ✅ Final Pre-Commit Checks

1. **All Tests Pass**: Unit, integration, and manual tests
2. **No Console Errors**: Check browser developer console
3. **Database Clean**: No test data in export
4. **Assets Optimized**: CSS/JS minified
5. **Images Compressed**: No large uncompressed images
6. **Secrets Removed**: No API keys or passwords
7. **License Headers**: All files have proper headers

## 🎯 Ready to Commit Criteria

Only proceed with commit when:
- [ ] All automated checks pass
- [ ] Manual testing complete
- [ ] Security review done
- [ ] Documentation updated
- [ ] Version numbers consistent
- [ ] No critical TODOs remain
- [ ] Performance acceptable
- [ ] Code review complete

---

**Note**: This validation should be run before EVERY commit to ensure code quality and security standards are maintained.
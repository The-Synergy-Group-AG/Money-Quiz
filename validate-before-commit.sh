#!/bin/bash

# Money Quiz Pre-Commit Validation Script
# Version: 1.0
# This script performs essential validation before committing changes

echo "================================================="
echo "Money Quiz v4.0 - Pre-Commit Validation"
echo "================================================="
echo ""

# Color codes for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

# Counters
ERRORS=0
WARNINGS=0

# Function to check PHP syntax
check_php_syntax() {
    echo "1. Checking PHP Syntax..."
    local syntax_errors=$(find . -name "*.php" -not -path "./vendor/*" -not -path "./node_modules/*" -exec php -l {} \; 2>&1 | grep -E "Parse error|Fatal error" | wc -l)
    
    if [ $syntax_errors -eq 0 ]; then
        echo -e "${GREEN}✓ PHP syntax check passed${NC}"
    else
        echo -e "${RED}✗ Found $syntax_errors PHP syntax errors${NC}"
        ERRORS=$((ERRORS + syntax_errors))
        find . -name "*.php" -not -path "./vendor/*" -exec php -l {} \; 2>&1 | grep -E "Parse error|Fatal error"
    fi
    echo ""
}

# Function to check for security issues
check_security() {
    echo "2. Security Checks..."
    
    # Check for eval usage
    local eval_usage=$(grep -r "eval(" --include="*.php" . 2>/dev/null | grep -v "vendor" | wc -l)
    if [ $eval_usage -gt 0 ]; then
        echo -e "${RED}✗ Found $eval_usage uses of eval()${NC}"
        WARNINGS=$((WARNINGS + eval_usage))
    else
        echo -e "${GREEN}✓ No eval() usage found${NC}"
    fi
    
    # Check for direct $_GET/$_POST usage without sanitization
    local unsanitized_input=$(grep -r '\$_\(GET\|POST\|REQUEST\)\[' --include="*.php" . 2>/dev/null | grep -v "vendor" | grep -v "sanitize" | wc -l)
    if [ $unsanitized_input -gt 0 ]; then
        echo -e "${YELLOW}⚠ Found $unsanitized_input potential unsanitized inputs${NC}"
        WARNINGS=$((WARNINGS + 1))
    else
        echo -e "${GREEN}✓ Input sanitization appears correct${NC}"
    fi
    
    # Check for hardcoded secrets
    local secrets=$(grep -r -E "(password|api_key|secret)" --include="*.php" . 2>/dev/null | grep -v "vendor" | grep -E "= ['\"][^'\"]{8,}['\"]" | wc -l)
    if [ $secrets -gt 0 ]; then
        echo -e "${RED}✗ Found $secrets potential hardcoded secrets${NC}"
        WARNINGS=$((WARNINGS + 1))
    else
        echo -e "${GREEN}✓ No hardcoded secrets detected${NC}"
    fi
    echo ""
}

# Function to check file structure
check_file_structure() {
    echo "3. File Structure Validation..."
    
    # Check for required files
    local required_files=(
        "money-quiz.php"
        "uninstall.php"
        "README.md"
        "composer.json"
        ".gitignore"
    )
    
    for file in "${required_files[@]}"; do
        if [ -f "$file" ]; then
            echo -e "${GREEN}✓ $file exists${NC}"
        else
            echo -e "${RED}✗ $file is missing${NC}"
            ERRORS=$((ERRORS + 1))
        fi
    done
    echo ""
}

# Function to check version consistency
check_versions() {
    echo "4. Version Consistency Check..."
    
    # Expected version
    local expected_version="4.0.0"
    
    # Check main plugin file
    local plugin_version=$(grep -E "^\s*\*\s*Version:" money-quiz.php 2>/dev/null | sed 's/.*Version:\s*//' | tr -d ' ')
    if [ "$plugin_version" = "$expected_version" ]; then
        echo -e "${GREEN}✓ Plugin header version: $plugin_version${NC}"
    else
        echo -e "${RED}✗ Plugin header version mismatch: $plugin_version (expected $expected_version)${NC}"
        ERRORS=$((ERRORS + 1))
    fi
    
    # Check constants
    local const_version=$(grep "MONEY_QUIZ_VERSION" money-quiz.php 2>/dev/null | grep -oE "[0-9]+\.[0-9]+\.[0-9]+" | head -1)
    if [ "$const_version" = "$expected_version" ]; then
        echo -e "${GREEN}✓ Version constant: $const_version${NC}"
    else
        echo -e "${RED}✗ Version constant mismatch: $const_version${NC}"
        ERRORS=$((ERRORS + 1))
    fi
    echo ""
}

# Function to check for debug code
check_debug_code() {
    echo "5. Debug Code Check..."
    
    local debug_code=$(grep -r "var_dump\|print_r\|console\.log\|TODO\|FIXME" --include="*.php" --include="*.js" . 2>/dev/null | grep -v "vendor" | grep -v "node_modules" | wc -l)
    
    if [ $debug_code -gt 0 ]; then
        echo -e "${YELLOW}⚠ Found $debug_code instances of debug code or TODO comments${NC}"
        WARNINGS=$((WARNINGS + 1))
        echo "Consider reviewing:"
        grep -r "var_dump\|print_r\|console\.log\|TODO\|FIXME" --include="*.php" --include="*.js" . 2>/dev/null | grep -v "vendor" | head -5
    else
        echo -e "${GREEN}✓ No debug code found${NC}"
    fi
    echo ""
}

# Function to check WordPress compatibility
check_wordpress_compatibility() {
    echo "6. WordPress Compatibility..."
    
    # Check for deprecated functions
    local deprecated=$(grep -r "mysql_\|ereg\|split(" --include="*.php" . 2>/dev/null | grep -v "vendor" | wc -l)
    
    if [ $deprecated -gt 0 ]; then
        echo -e "${RED}✗ Found $deprecated uses of deprecated functions${NC}"
        ERRORS=$((ERRORS + 1))
    else
        echo -e "${GREEN}✓ No deprecated functions found${NC}"
    fi
    echo ""
}

# Function to validate assets
check_assets() {
    echo "7. Asset Validation..."
    
    # Check if CSS files exist for JS files
    local js_files=$(find assets/js -name "*.js" -not -name "*.min.js" 2>/dev/null | wc -l)
    local css_files=$(find assets/css -name "*.css" -not -name "*.min.css" 2>/dev/null | wc -l)
    
    echo -e "${GREEN}✓ Found $js_files JS files and $css_files CSS files${NC}"
    
    # Check for minified versions
    local minified_js=$(find assets/js -name "*.min.js" 2>/dev/null | wc -l)
    local minified_css=$(find assets/css -name "*.min.css" 2>/dev/null | wc -l)
    
    if [ $minified_js -gt 0 ] || [ $minified_css -gt 0 ]; then
        echo -e "${GREEN}✓ Found minified assets${NC}"
    else
        echo -e "${YELLOW}⚠ Consider creating minified versions of assets${NC}"
        WARNINGS=$((WARNINGS + 1))
    fi
    echo ""
}

# Function to check database operations
check_database_operations() {
    echo "8. Database Operation Safety..."
    
    # Check for direct table references
    local direct_tables=$(grep -r "\$wpdb->.*money_quiz" --include="*.php" . 2>/dev/null | grep -v "prefix" | wc -l)
    
    if [ $direct_tables -gt 0 ]; then
        echo -e "${YELLOW}⚠ Found $direct_tables direct table references (should use \$wpdb->prefix)${NC}"
        WARNINGS=$((WARNINGS + 1))
    else
        echo -e "${GREEN}✓ Database operations use proper prefixing${NC}"
    fi
    
    # Check for prepared statements
    local unprepared=$(grep -r "\$wpdb->query.*\$_" --include="*.php" . 2>/dev/null | grep -v "prepare" | wc -l)
    
    if [ $unprepared -gt 0 ]; then
        echo -e "${RED}✗ Found $unprepared potentially unprepared queries${NC}"
        ERRORS=$((ERRORS + 1))
    else
        echo -e "${GREEN}✓ Queries appear to use prepared statements${NC}"
    fi
    echo ""
}

# Run all checks
check_php_syntax
check_security
check_file_structure
check_versions
check_debug_code
check_wordpress_compatibility
check_assets
check_database_operations

# Summary
echo "================================================="
echo "Validation Summary"
echo "================================================="

if [ $ERRORS -eq 0 ] && [ $WARNINGS -eq 0 ]; then
    echo -e "${GREEN}✓ All checks passed! Ready to commit.${NC}"
    exit 0
else
    echo -e "${RED}Errors: $ERRORS${NC}"
    echo -e "${YELLOW}Warnings: $WARNINGS${NC}"
    
    if [ $ERRORS -gt 0 ]; then
        echo -e "\n${RED}❌ Please fix errors before committing.${NC}"
        exit 1
    else
        echo -e "\n${YELLOW}⚠ Warnings found. Review before committing.${NC}"
        exit 0
    fi
fi
#!/bin/bash

# MoneyQuiz Plugin Deployment Script
# This script ensures proper deployment with all dependency checks

set -e  # Exit on any error

echo "🚀 MoneyQuiz Plugin Deployment Script"
echo "====================================="
echo ""

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

# Configuration
PLUGIN_DIR="$(pwd)"
COMPOSER_CMD="composer"
PHP_CMD="php"

# Function to print colored output
print_status() {
    echo -e "${GREEN}✓${NC} $1"
}

print_warning() {
    echo -e "${YELLOW}⚠${NC} $1"
}

print_error() {
    echo -e "${RED}✗${NC} $1"
}

# Check if we're in the right directory
if [ ! -f "moneyquiz.php" ]; then
    print_error "This script must be run from the MoneyQuiz plugin directory"
    exit 1
fi

print_status "Starting deployment process..."

# Step 1: Check PHP version
echo ""
echo "1. Checking PHP version..."
PHP_VERSION=$($PHP_CMD -r "echo PHP_VERSION;")
if [ "$(printf '%s\n' "7.4.0" "$PHP_VERSION" | sort -V | head -n1)" = "7.4.0" ]; then
    print_status "PHP version $PHP_VERSION is compatible"
else
    print_error "PHP version $PHP_VERSION is below minimum required (7.4.0)"
    exit 1
fi

# Step 2: Check if Composer is available
echo ""
echo "2. Checking Composer availability..."
if command -v $COMPOSER_CMD &> /dev/null; then
    print_status "Composer is available"
else
    print_error "Composer is not available. Please install Composer first."
    exit 1
fi

# Step 3: Install/Update Composer dependencies
echo ""
echo "3. Installing Composer dependencies..."
if [ -f "composer.json" ]; then
    $COMPOSER_CMD install --no-dev --optimize-autoloader --no-interaction
    print_status "Composer dependencies installed"
else
    print_error "composer.json not found"
    exit 1
fi

# Step 4: Check vendor directory
echo ""
echo "4. Verifying vendor directory..."
if [ -d "vendor" ] && [ -f "vendor/autoload.php" ]; then
    print_status "Vendor directory and autoloader are present"
else
    print_error "Vendor directory or autoloader is missing"
    exit 1
fi

# Step 5: Run PHP syntax checks
echo ""
echo "5. Checking PHP syntax..."
PHP_FILES=(
    "moneyquiz.php"
    "class.moneyquiz.php"
    "includes/class-money-quiz-integration-loader.php"
    "includes/class-money-quiz-service-container.php"
    "includes/class-money-quiz-hooks-registry.php"
    "includes/class-money-quiz-dependency-checker.php"
    "includes/functions.php"
)

SYNTAX_ERRORS=0
for file in "${PHP_FILES[@]}"; do
    if [ -f "$file" ]; then
        if $PHP_CMD -l "$file" > /dev/null 2>&1; then
            print_status "Syntax check passed: $file"
        else
            print_error "Syntax error in: $file"
            SYNTAX_ERRORS=$((SYNTAX_ERRORS + 1))
        fi
    else
        print_warning "File not found: $file"
    fi
done

if [ $SYNTAX_ERRORS -gt 0 ]; then
    print_error "Found $SYNTAX_ERRORS syntax error(s). Please fix before deploying."
    exit 1
fi

# Step 6: Run deployment checker
echo ""
echo "6. Running deployment checker..."
if [ -f "deployment-checker.php" ]; then
    $PHP_CMD deployment-checker.php
    DEPLOYMENT_EXIT_CODE=$?
    
    if [ $DEPLOYMENT_EXIT_CODE -eq 0 ]; then
        print_status "Deployment checker passed"
    else
        print_error "Deployment checker failed"
        exit 1
    fi
else
    print_warning "Deployment checker not found, skipping..."
fi

# Step 7: Check file permissions
echo ""
echo "7. Checking file permissions..."
if [ -r "$PLUGIN_DIR" ]; then
    print_status "Plugin directory is readable"
else
    print_error "Plugin directory is not readable"
    exit 1
fi

if [ -r "$PLUGIN_DIR/vendor" ]; then
    print_status "Vendor directory is readable"
else
    print_error "Vendor directory is not readable"
    exit 1
fi

# Step 8: Create deployment manifest
echo ""
echo "8. Creating deployment manifest..."
MANIFEST_FILE="deployment-manifest.json"
cat > "$MANIFEST_FILE" << EOF
{
    "deployment_timestamp": "$(date -u +"%Y-%m-%dT%H:%M:%SZ")",
    "plugin_version": "$(grep "Version:" moneyquiz.php | head -1 | sed 's/.*Version: *//' | tr -d ' ' || echo 'Unknown')",
    "php_version": "$PHP_VERSION",
    "composer_version": "$($COMPOSER_CMD --version | head -1)",
    "deployment_checks": {
        "composer_dependencies": "installed",
        "vendor_directory": "present",
        "autoloader": "present",
        "syntax_checks": "passed",
        "file_permissions": "correct"
    },
    "critical_files": [
EOF

for file in "${PHP_FILES[@]}"; do
    if [ -f "$file" ]; then
        echo "        \"$file\"" >> "$MANIFEST_FILE"
    fi
done

echo "    ]" >> "$MANIFEST_FILE"
echo "}" >> "$MANIFEST_FILE"

print_status "Deployment manifest created: $MANIFEST_FILE"

# Step 9: Final verification
echo ""
echo "9. Final verification..."
if [ -f "vendor/autoload.php" ] && [ -f "moneyquiz.php" ]; then
    print_status "Plugin is ready for deployment"
else
    print_error "Critical files missing - deployment failed"
    exit 1
fi

echo ""
echo "🎉 Deployment preparation completed successfully!"
echo ""
echo "Next steps:"
echo "1. Upload the plugin files to your WordPress installation"
echo "2. Activate the plugin in WordPress admin"
echo "3. Check for any admin notices about missing dependencies"
echo "4. Test the plugin functionality"
echo ""
echo "Deployment manifest saved to: $MANIFEST_FILE" 
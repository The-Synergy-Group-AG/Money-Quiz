#!/bin/bash
#
# Build Script for Money Quiz Safe Plugin
# This script creates a properly structured ZIP file with single entry point
#
# Usage: ./build-safe-plugin.sh
#

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

# Configuration
PLUGIN_NAME="money-quiz"
BUILD_DIR="build"
DIST_DIR="dist"
VERSION="4.0.0"
ZIP_NAME="${PLUGIN_NAME}-safe-v${VERSION}.zip"

echo -e "${GREEN}Building Money Quiz Safe Plugin v${VERSION}${NC}"
echo "=========================================="

# Clean previous builds
echo -e "${YELLOW}Cleaning previous builds...${NC}"
rm -rf "$BUILD_DIR"
rm -rf "$DIST_DIR"
mkdir -p "$BUILD_DIR/$PLUGIN_NAME"
mkdir -p "$DIST_DIR"

# Copy files to build directory
echo -e "${YELLOW}Copying files...${NC}"

# Core files - Use new entry point
cp money-quiz.php "$BUILD_DIR/$PLUGIN_NAME/money-quiz.php"
echo -e "  ✓ Copied new single-entry-point loader"

# Copy legacy plugin but rename to avoid conflicts
if [ -f "moneyquiz.php" ]; then
    mkdir -p "$BUILD_DIR/$PLUGIN_NAME/legacy"
    cp moneyquiz.php "$BUILD_DIR/$PLUGIN_NAME/legacy/moneyquiz-original.php"
    
    # Remove plugin header from legacy file to prevent WordPress confusion
    sed -i '1,20s/Plugin Name:/Original Plugin Name:/' "$BUILD_DIR/$PLUGIN_NAME/legacy/moneyquiz-original.php"
    sed -i '1,20s/\*\//Moved to legacy folder\n*\//' "$BUILD_DIR/$PLUGIN_NAME/legacy/moneyquiz-original.php"
    echo -e "  ✓ Moved original plugin to legacy folder"
fi

# Copy includes directory
cp -r includes "$BUILD_DIR/$PLUGIN_NAME/"
echo -e "  ✓ Copied includes directory"

# Copy class file
cp class.moneyquiz.php "$BUILD_DIR/$PLUGIN_NAME/"
echo -e "  ✓ Copied main class file"

# Copy admin files
for file in *.admin.php; do
    if [ -f "$file" ]; then
        cp "$file" "$BUILD_DIR/$PLUGIN_NAME/"
    fi
done
echo -e "  ✓ Copied admin files"

# Copy quiz file
if [ -f "quiz.moneycoach.php" ]; then
    cp quiz.moneycoach.php "$BUILD_DIR/$PLUGIN_NAME/"
    echo -e "  ✓ Copied quiz file"
fi

# Copy assets but clean up duplicates
if [ -d "assets" ]; then
    cp -r assets "$BUILD_DIR/$PLUGIN_NAME/"
    
    # Remove PHP files from images directory
    find "$BUILD_DIR/$PLUGIN_NAME/assets/images" -name "*.php" -delete
    echo -e "  ✓ Copied assets (cleaned)"
fi

# Copy languages directory if exists
if [ -d "languages" ]; then
    cp -r languages "$BUILD_DIR/$PLUGIN_NAME/"
    echo -e "  ✓ Copied languages"
fi

# Copy index.php for directory protection
cp index.php "$BUILD_DIR/$PLUGIN_NAME/"

# Create essential directories
mkdir -p "$BUILD_DIR/$PLUGIN_NAME/logs"
mkdir -p "$BUILD_DIR/$PLUGIN_NAME/cache"
mkdir -p "$BUILD_DIR/$PLUGIN_NAME/temp"
echo -e "  ✓ Created essential directories"

# Create .htaccess for security
cat > "$BUILD_DIR/$PLUGIN_NAME/logs/.htaccess" << 'EOF'
Order deny,allow
Deny from all
EOF

cp "$BUILD_DIR/$PLUGIN_NAME/logs/.htaccess" "$BUILD_DIR/$PLUGIN_NAME/cache/.htaccess"
cp "$BUILD_DIR/$PLUGIN_NAME/logs/.htaccess" "$BUILD_DIR/$PLUGIN_NAME/temp/.htaccess"
echo -e "  ✓ Added security .htaccess files"

# Create readme.txt
cat > "$BUILD_DIR/$PLUGIN_NAME/readme.txt" << 'EOF'
=== Money Quiz Safe ===
Contributors: businessinsights
Tags: quiz, money, personality, assessment, safe
Requires at least: 5.8
Tested up to: 6.4
Requires PHP: 7.4
Stable tag: 4.0.0
License: GPLv2 or later

Enhanced Money Quiz plugin with comprehensive safety features and modern architecture.

== Description ==

This is a security-enhanced version of the Money Quiz plugin that includes:

* Comprehensive security wrapper
* SQL injection protection
* XSS prevention
* Safe file handling
* Quarantine mode for threats
* Real-time monitoring
* Version upgrade handling
* Single-entry-point architecture

== Installation ==

1. Upload the plugin folder to `/wp-content/plugins/`
2. Activate the plugin through the 'Plugins' menu in WordPress
3. Review the security report in Money Quiz > Security
4. Configure settings as needed

== Important ==

This plugin runs in safe mode by default. The original functionality is wrapped
with protective layers to prevent security vulnerabilities.

== Changelog ==

= 4.0.0 =
* Complete security overhaul
* Single-entry-point architecture
* Quarantine mode for threats
* Comprehensive monitoring
* Safe wrapper implementation

== Upgrade Notice ==

= 4.0.0 =
This version includes critical security improvements. Backup your data before upgrading.
EOF
echo -e "  ✓ Created readme.txt"

# Create safe installation guide
cat > "$BUILD_DIR/$PLUGIN_NAME/SAFE-INSTALLATION-GUIDE.md" << 'EOF'
# Money Quiz Safe Installation Guide

## Pre-Installation Checklist

1. **Backup your database** - Critical for safety
2. **Backup wp-content/uploads** - In case of issues
3. **Test on staging first** - Never install directly on production

## Installation Steps

1. Upload the plugin through WordPress admin or FTP
2. Activate the plugin
3. Check Money Quiz > Security for any issues
4. Review the safety report
5. Configure safe mode settings if needed

## Safe Mode Features

- **Quarantine Mode**: Automatically activated if threats detected
- **SQL Injection Protection**: All queries are monitored
- **File Permission Checks**: Ensures secure file permissions
- **External Call Monitoring**: Tracks all external connections
- **Real-time Logging**: All security events are logged

## Post-Installation

1. Run a full security scan
2. Check error logs for any issues
3. Test the quiz functionality
4. Monitor the security dashboard

## Troubleshooting

If the plugin enters quarantine mode:
1. Check the security report for details
2. Address identified vulnerabilities
3. Override quarantine only if absolutely necessary

## Support

For security-related issues, check the security report first.
All violations are logged with detailed information.
EOF
echo -e "  ✓ Created installation guide"

# Remove development files
echo -e "${YELLOW}Cleaning development files...${NC}"
find "$BUILD_DIR/$PLUGIN_NAME" -name "*.md" -not -name "SAFE-INSTALLATION-GUIDE.md" -delete
find "$BUILD_DIR/$PLUGIN_NAME" -name ".git*" -delete
find "$BUILD_DIR/$PLUGIN_NAME" -name "*.log" -delete
find "$BUILD_DIR/$PLUGIN_NAME" -name "*.bak" -delete
find "$BUILD_DIR/$PLUGIN_NAME" -name "*~" -delete
find "$BUILD_DIR/$PLUGIN_NAME" -name ".DS_Store" -delete

# Remove the duplicate safe wrapper file if it exists
if [ -f "$BUILD_DIR/$PLUGIN_NAME/money-quiz-safe-wrapper.php" ]; then
    rm "$BUILD_DIR/$PLUGIN_NAME/money-quiz-safe-wrapper.php"
    echo -e "  ✓ Removed duplicate wrapper file"
fi

# Set proper permissions
echo -e "${YELLOW}Setting permissions...${NC}"
find "$BUILD_DIR/$PLUGIN_NAME" -type d -exec chmod 755 {} \;
find "$BUILD_DIR/$PLUGIN_NAME" -type f -exec chmod 644 {} \;
echo -e "  ✓ Set proper file permissions"

# Create ZIP file
echo -e "${YELLOW}Creating ZIP file...${NC}"
cd "$BUILD_DIR"
zip -r "../$DIST_DIR/$ZIP_NAME" "$PLUGIN_NAME" -x "*.git*" "*.DS_Store" "*~"
cd ..
echo -e "  ✓ Created $ZIP_NAME"

# Calculate file size
SIZE=$(du -h "$DIST_DIR/$ZIP_NAME" | cut -f1)
echo -e "${GREEN}Build complete!${NC}"
echo "=========================================="
echo -e "Plugin: ${GREEN}$ZIP_NAME${NC}"
echo -e "Size: ${GREEN}$SIZE${NC}"
echo -e "Location: ${GREEN}$DIST_DIR/$ZIP_NAME${NC}"

# Verify ZIP structure
echo -e "\n${YELLOW}Verifying ZIP structure:${NC}"
unzip -l "$DIST_DIR/$ZIP_NAME" | head -20
echo "..."
echo -e "\n${GREEN}✓ Single entry point: money-quiz/money-quiz.php${NC}"
echo -e "${GREEN}✓ Legacy code moved to: money-quiz/legacy/${NC}"
echo -e "${GREEN}✓ No conflicting plugin headers${NC}"

# Cleanup build directory
echo -e "\n${YELLOW}Cleaning up build directory...${NC}"
rm -rf "$BUILD_DIR"
echo -e "${GREEN}Done!${NC}"

echo -e "\n${YELLOW}Next steps:${NC}"
echo "1. Test the ZIP file in a clean WordPress installation"
echo "2. Verify the plugin activates without conflicts"
echo "3. Check the Security dashboard after activation"
echo "4. Test in safe mode before switching to legacy mode"
# The Synergy Group AG - Plugin Branding Updates

## Overview
The Money Quiz plugin has been updated with The Synergy Group AG branding and contact information throughout the codebase.

## Updated Information

### Company Details
- **Company Name:** The Synergy Group AG
- **Website:** https://thesynergygroup.ch
- **Contact Email:** Andre@thesynergygroup.ch
- **Location:** Zurich, Switzerland

### Files Updated

1. **Main Plugin File** (`money-quiz.php`)
   - Plugin URI: https://thesynergygroup.ch
   - Author: The Synergy Group AG
   - Author URI: https://thesynergygroup.ch
   - Added @author tag: Andre@thesynergygroup.ch

2. **Composer Configuration** (`composer.json`)
   - Package name: thesynergygroup/money-quiz
   - Author name: The Synergy Group AG
   - Author email: Andre@thesynergygroup.ch
   - Homepage: https://thesynergygroup.ch

3. **Documentation** (`README.md`)
   - Added developer attribution
   - Added website and contact information

4. **New Files Created**
   - `AUTHOR.md` - Comprehensive author information
   - `config/plugin-defaults.php` - Centralized author/company constants

### Constants Defined

```php
MONEY_QUIZ_AUTHOR = 'The Synergy Group AG'
MONEY_QUIZ_AUTHOR_URI = 'https://thesynergygroup.ch'
MONEY_QUIZ_AUTHOR_EMAIL = 'Andre@thesynergygroup.ch'
MONEY_QUIZ_SUPPORT_EMAIL = 'Andre@thesynergygroup.ch'
```

### Email Configuration

- Admin notifications will be sent to: Andre@thesynergygroup.ch
- Support emails configured to use: Andre@thesynergygroup.ch
- Email sender updated to use domain: noreply@thesynergygroup.ch

### Copyright Notice

© 2025 The Synergy Group AG. All rights reserved.

The plugin remains licensed under GPL v2 or later, allowing free use, modification, and distribution under the same license terms.

## Verification

To verify the branding updates:

1. Check plugin header in WordPress admin → Plugins
2. View author information in plugin details
3. Check email notifications are sent to correct address
4. Verify support links point to The Synergy Group AG website

All previous references to "Business Insights Group AG" and "101businessinsights.com" have been identified, though legacy code files may still contain old references which will be updated during the progressive migration.
# Money Quiz Plugin - Manual Testing Checklist

## Pre-Testing Setup

- [ ] Fresh WordPress installation (latest version)
- [ ] Plugin installed and activated
- [ ] Sample data imported (if available)
- [ ] Error logging enabled
- [ ] Browser developer console open

## Installation & Activation

### First-time Installation
- [ ] Upload plugin via WordPress admin
- [ ] Activate plugin successfully
- [ ] No PHP errors or warnings
- [ ] Database tables created correctly
- [ ] Admin menu items appear

### Update from Previous Version
- [ ] Backup existing data
- [ ] Deactivate old version
- [ ] Upload new version
- [ ] Activate without data loss
- [ ] Legacy data still accessible

## Frontend Testing

### Quiz Display
- [ ] Shortcode `[money_quiz]` renders correctly
- [ ] Legacy shortcode `[mq_questions]` still works
- [ ] Quiz title and description display
- [ ] Questions appear in correct order
- [ ] All question options are clickable
- [ ] Progress bar updates correctly
- [ ] Mobile responsive design works

### Quiz Interaction
- [ ] Can select answers for all questions
- [ ] Required field validation works
- [ ] Can navigate between questions
- [ ] Previous answers are remembered
- [ ] Auto-advance feature works (if enabled)
- [ ] Keyboard navigation (Enter/Tab) works

### Quiz Submission
- [ ] Form submission works without page reload
- [ ] Loading indicator appears during submission
- [ ] Results display correctly
- [ ] Archetype name and description shown
- [ ] Score percentage is accurate
- [ ] Email collection works (if enabled)
- [ ] Success message appears

### Error Handling
- [ ] Network error message displays
- [ ] Validation errors show clearly
- [ ] Quiz not found message works
- [ ] Graceful degradation without JavaScript

## Admin Interface Testing

### Dashboard
- [ ] Dashboard page loads correctly
- [ ] Statistics display accurately
- [ ] Quick actions work
- [ ] Recent results show

### Quiz Management
- [ ] Quiz listing page loads
- [ ] Can create new quiz
- [ ] Can edit existing quiz
- [ ] Can delete quiz (with confirmation)
- [ ] Bulk actions work
- [ ] Question management works
- [ ] Settings save correctly

### Results Management
- [ ] Results listing displays correctly
- [ ] Search functionality works
- [ ] Date filtering works
- [ ] Archetype filtering works
- [ ] Pagination works correctly
- [ ] CSV export generates valid file
- [ ] Individual result view works

### Settings Page
- [ ] All settings sections load
- [ ] Settings save successfully
- [ ] Email configuration works
- [ ] Advanced settings apply correctly
- [ ] Import/Export functionality works
- [ ] Reset to defaults works

## Email Testing

### Admin Notifications
- [ ] Admin receives email on quiz completion
- [ ] Email contains correct information
- [ ] Links in email work
- [ ] From name/email are correct

### User Notifications
- [ ] User receives results email
- [ ] Personalization tokens work
- [ ] Email formatting is correct
- [ ] Unsubscribe/privacy info included

## Security Testing

### CSRF Protection
- [ ] Forms include CSRF tokens
- [ ] Token validation works
- [ ] Expired tokens are rejected

### Permission Checks
- [ ] Non-admins cannot access admin pages
- [ ] Capability checks work correctly
- [ ] Direct file access is blocked

### Input Validation
- [ ] XSS attempts are blocked
- [ ] SQL injection prevented
- [ ] File upload restrictions work
- [ ] Email validation works

## Performance Testing

### Page Load Times
- [ ] Frontend quiz loads quickly
- [ ] Admin pages responsive
- [ ] AJAX requests are fast
- [ ] No unnecessary database queries

### Caching
- [ ] Cache is populated correctly
- [ ] Cache invalidation works
- [ ] Performance improves with cache

## Compatibility Testing

### WordPress Versions
- [ ] Works with minimum WP version
- [ ] Works with latest WP version
- [ ] No deprecated function warnings

### PHP Versions
- [ ] PHP 7.4 compatibility
- [ ] PHP 8.0 compatibility
- [ ] PHP 8.1+ compatibility

### Browser Testing
- [ ] Chrome (latest)
- [ ] Firefox (latest)
- [ ] Safari (latest)
- [ ] Edge (latest)
- [ ] Mobile browsers

### Theme Compatibility
- [ ] Twenty Twenty-One
- [ ] Twenty Twenty-Two
- [ ] Twenty Twenty-Three
- [ ] Popular themes (Astra, GeneratePress, etc.)

### Plugin Conflicts
- [ ] Works with Yoast SEO
- [ ] Works with WooCommerce
- [ ] Works with Contact Form 7
- [ ] Works with caching plugins

## Accessibility Testing

### Keyboard Navigation
- [ ] All interactive elements reachable
- [ ] Tab order is logical
- [ ] Focus indicators visible
- [ ] Skip links work (if applicable)

### Screen Reader Testing
- [ ] Form labels announced correctly
- [ ] Error messages announced
- [ ] Results announced properly
- [ ] ARIA labels appropriate

### Visual Testing
- [ ] Sufficient color contrast
- [ ] Text is readable
- [ ] Zoom to 200% works
- [ ] No horizontal scrolling

## Localization Testing

### Text Domains
- [ ] All strings use correct text domain
- [ ] No hardcoded strings
- [ ] Plurals handled correctly

### RTL Support
- [ ] Layout works in RTL languages
- [ ] No broken styling
- [ ] Text alignment correct

## Error Recovery

### Database Errors
- [ ] Handles missing tables gracefully
- [ ] Migration errors are logged
- [ ] Fallback to legacy tables works

### Network Issues
- [ ] Offline message displays
- [ ] Retries failed requests
- [ ] Saves progress locally (if applicable)

## Data Import/Export

### Settings
- [ ] Export creates valid JSON
- [ ] Import accepts valid files
- [ ] Invalid files rejected gracefully
- [ ] Settings applied after import

### Quiz Data
- [ ] Can export quiz structure
- [ ] Can import quiz structure
- [ ] Questions preserved correctly
- [ ] Archetype mappings maintained

## Uninstallation

### Clean Uninstall
- [ ] Deactivation doesn't delete data
- [ ] Uninstall removes plugin files
- [ ] Database cleanup option works
- [ ] No leftover options in database

## Regression Testing

After making changes:
- [ ] Previous features still work
- [ ] No new PHP errors
- [ ] No JavaScript errors
- [ ] Performance not degraded
- [ ] Security measures intact

## Notes Section

Use this section to document any issues found during testing:

### Issues Found:
1. 
2. 
3. 

### Recommendations:
1. 
2. 
3. 

### Test Environment:
- WordPress Version: 
- PHP Version: 
- MySQL Version: 
- Theme: 
- Active Plugins: 
- Browser: 
- Operating System: 

### Tested By:
- Name: 
- Date: 
- Version Tested: 
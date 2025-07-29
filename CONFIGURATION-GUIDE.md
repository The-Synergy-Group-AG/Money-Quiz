# Money Quiz Configuration Guide

## Environment Configuration

To ensure security and proper functionality, the Money Quiz plugin requires certain configuration values to be set. These should NEVER be hardcoded in the plugin files.

### Method 1: Using wp-config.php (Recommended)

Add the following constants to your `wp-config.php` file:

```php
// Money Quiz Configuration
define( 'MQ_ADMIN_EMAIL', 'andre@thesynergygroup.ch' );
define( 'MQ_LICENSE_API_KEY', 'your-license-api-key-here' );
define( 'MQ_LICENSE_SERVER_URL', 'https://your-license-server.com' );
```

### Method 2: Using WordPress Options

If you cannot modify `wp-config.php`, you can set these values in the WordPress database:

```php
// Run once in your theme's functions.php or via WP CLI
update_option( 'money_quiz_license_api_key', 'your-license-api-key-here' );
update_option( 'money_quiz_license_server_url', 'https://your-license-server.com' );
```

### Method 3: Using Environment Variables

For containerized or cloud deployments:

```bash
export MQ_ADMIN_EMAIL="andre@thesynergygroup.ch"
export MQ_LICENSE_API_KEY="your-license-api-key-here"
export MQ_LICENSE_SERVER_URL="https://your-license-server.com"
```

## Required Configuration Values

| Setting | Description | Example |
|---------|-------------|---------|
| `MQ_ADMIN_EMAIL` | Admin email for notifications | andre@thesynergygroup.ch |
| `MQ_LICENSE_API_KEY` | API key for license validation | (obtain from license server) |
| `MQ_LICENSE_SERVER_URL` | URL of the license server | https://license.thesynergygroup.ch |

## Security Notes

1. **NEVER commit API keys or secrets to version control**
2. Use strong, randomly generated API keys
3. Restrict file permissions on wp-config.php (chmod 600)
4. Consider using a secrets management service for production

## Verification

After configuration, verify your setup:

1. Check plugin activation works without errors
2. Verify license validation (if applicable)
3. Test email sending functionality
4. Review error logs for any configuration issues

## Migration from Hardcoded Values

If upgrading from a version with hardcoded values:

1. Add the configuration values as shown above
2. Deactivate and reactivate the plugin
3. Test all functionality
4. Remove any local modifications that contain hardcoded secrets

## Support

For configuration assistance, contact:
- Email: support@thesynergygroup.ch
- Website: https://thesynergygroup.ch
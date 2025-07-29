<?php
/**
 * Integration Test Page
 * 
 * Quick test page to verify integration is working
 * Access this file directly in your browser after placing in WordPress root
 * 
 * @package MoneyQuiz
 */

// Load WordPress
require_once 'wp-load.php';

// Check if user is admin
if ( ! current_user_can( 'manage_options' ) ) {
    wp_die( 'You must be an administrator to view this page.' );
}

// Get integration status
$integration_active = class_exists( '\MoneyQuiz\Integration\Legacy_Integration' );
$error_logging = defined( 'MONEY_QUIZ_ERROR_LOGGING' ) && MONEY_QUIZ_ERROR_LOGGING;
$modern_rollout = get_option( 'money_quiz_modern_rollout', 0 );
$function_flags = get_option( 'money_quiz_feature_flags', [] );

?>
<!DOCTYPE html>
<html>
<head>
    <title>Money Quiz Integration Test</title>
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
            line-height: 1.6;
            max-width: 800px;
            margin: 40px auto;
            padding: 20px;
            background: #f5f5f5;
        }
        .container {
            background: white;
            padding: 30px;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        h1 {
            color: #23282d;
            margin-bottom: 30px;
        }
        .status {
            padding: 15px;
            margin: 10px 0;
            border-radius: 4px;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }
        .status.success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        .status.warning {
            background: #fff3cd;
            color: #856404;
            border: 1px solid #ffeaa7;
        }
        .status.error {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        .icon {
            font-size: 20px;
            margin-right: 10px;
        }
        .button {
            display: inline-block;
            padding: 10px 20px;
            background: #007cba;
            color: white;
            text-decoration: none;
            border-radius: 4px;
            margin-top: 20px;
        }
        .button:hover {
            background: #005a87;
        }
        code {
            background: #f0f0f0;
            padding: 2px 6px;
            border-radius: 3px;
            font-family: Consolas, Monaco, monospace;
        }
        .test-section {
            margin: 30px 0;
            padding: 20px;
            background: #f8f9fa;
            border-radius: 4px;
        }
        .test-result {
            margin: 10px 0;
            padding: 8px;
            background: white;
            border-radius: 3px;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>Money Quiz Integration Status</h1>
        
        <h2>Core Components</h2>
        
        <div class="status <?php echo $integration_active ? 'success' : 'error'; ?>">
            <span>
                <span class="icon"><?php echo $integration_active ? '✓' : '✗'; ?></span>
                Integration Classes
            </span>
            <span><?php echo $integration_active ? 'Loaded' : 'Not Loaded'; ?></span>
        </div>
        
        <div class="status <?php echo $error_logging ? 'success' : 'warning'; ?>">
            <span>
                <span class="icon"><?php echo $error_logging ? '✓' : '⚠'; ?></span>
                Error Logging
            </span>
            <span><?php echo $error_logging ? 'Enabled' : 'Disabled'; ?></span>
        </div>
        
        <div class="status <?php echo $modern_rollout > 0 ? 'success' : 'warning'; ?>">
            <span>
                <span class="icon"><?php echo $modern_rollout > 0 ? '✓' : '⚠'; ?></span>
                Modern Rollout
            </span>
            <span><?php echo $modern_rollout; ?>%</span>
        </div>
        
        <h2>Function Tests</h2>
        
        <div class="test-section">
            <?php
            // Test database wrapper
            $db_test = false;
            if ( function_exists( 'mq_safe_db' ) ) {
                try {
                    $db_wrapper = mq_safe_db();
                    $db_test = true;
                } catch ( Exception $e ) {
                    $db_test = false;
                }
            }
            ?>
            <div class="test-result">
                <strong>Database Wrapper:</strong> 
                <?php echo $db_test ? '<span style="color: green;">✓ Working</span>' : '<span style="color: red;">✗ Not Working</span>'; ?>
            </div>
            
            <?php
            // Test input sanitizer
            $sanitizer_test = false;
            if ( function_exists( 'mq_sanitize_input' ) ) {
                $test_data = [ 'test' => '<script>alert("xss")</script>' ];
                $clean = mq_sanitize_input( $test_data );
                $sanitizer_test = ( $clean['test'] === 'alert("xss")' );
            }
            ?>
            <div class="test-result">
                <strong>Input Sanitizer:</strong> 
                <?php echo $sanitizer_test ? '<span style="color: green;">✓ Working</span>' : '<span style="color: red;">✗ Not Working</span>'; ?>
            </div>
            
            <?php
            // Test version manager
            $version_test = class_exists( '\MoneyQuiz\Core\Version_Manager' );
            ?>
            <div class="test-result">
                <strong>Version Manager:</strong> 
                <?php echo $version_test ? '<span style="color: green;">✓ Available</span>' : '<span style="color: red;">✗ Not Available</span>'; ?>
            </div>
        </div>
        
        <h2>Enabled Functions</h2>
        
        <div class="test-section">
            <?php if ( empty( $function_flags ) ) : ?>
                <p>No function flags configured yet.</p>
            <?php else : ?>
                <?php foreach ( $function_flags as $func => $enabled ) : ?>
                    <div class="test-result">
                        <code><?php echo esc_html( $func ); ?></code>: 
                        <?php echo $enabled ? '<span style="color: green;">Enabled</span>' : '<span style="color: gray;">Disabled</span>'; ?>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
        
        <h2>Quick Actions</h2>
        
        <p>
            <a href="<?php echo admin_url( 'admin.php?page=moneyquiz-integration' ); ?>" class="button">
                Open Integration Settings
            </a>
        </p>
        
        <?php if ( ! $error_logging ) : ?>
            <div class="status warning" style="margin-top: 30px;">
                <span>
                    <span class="icon">⚠</span>
                    <strong>Action Required:</strong> Add the following to your wp-config.php:
                </span>
            </div>
            <pre style="background: #f0f0f0; padding: 15px; border-radius: 4px; overflow-x: auto;">
define( 'WP_DEBUG', true );
define( 'WP_DEBUG_LOG', true );
define( 'WP_DEBUG_DISPLAY', false );
define( 'MONEY_QUIZ_ERROR_LOGGING', true );</pre>
        <?php endif; ?>
        
        <h2>System Information</h2>
        
        <div class="test-section">
            <div class="test-result">
                <strong>PHP Version:</strong> <?php echo PHP_VERSION; ?>
            </div>
            <div class="test-result">
                <strong>WordPress Version:</strong> <?php echo get_bloginfo( 'version' ); ?>
            </div>
            <div class="test-result">
                <strong>Money Quiz Version:</strong> <?php echo defined( 'MONEY_QUIZ_VERSION' ) ? MONEY_QUIZ_VERSION : 'Unknown'; ?>
            </div>
            <div class="test-result">
                <strong>Memory Limit:</strong> <?php echo ini_get( 'memory_limit' ); ?>
            </div>
        </div>
        
        <p style="margin-top: 40px; text-align: center; color: #666;">
            This is a temporary test page. Delete this file when testing is complete.
        </p>
    </div>
</body>
</html>
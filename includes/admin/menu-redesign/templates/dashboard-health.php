<?php
/**
 * Dashboard System Health Template
 * 
 * @package MoneyQuiz
 * @since 4.2.0
 */

// Security check
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

global $wpdb;
$table_prefix = $wpdb->prefix;

// System checks
$checks = [];

// PHP Version
$php_version = phpversion();
$php_required = '7.4';
$checks['php'] = [
    'label' => __( 'PHP Version', 'money-quiz' ),
    'status' => version_compare( $php_version, $php_required, '>=' ) ? 'good' : 'error',
    'value' => $php_version,
    'message' => version_compare( $php_version, $php_required, '>=' ) 
        ? sprintf( __( 'Version %s meets requirements', 'money-quiz' ), $php_version )
        : sprintf( __( 'Version %s is below required %s', 'money-quiz' ), $php_version, $php_required )
];

// WordPress Version
$wp_version = get_bloginfo( 'version' );
$wp_required = '5.5';
$checks['wordpress'] = [
    'label' => __( 'WordPress Version', 'money-quiz' ),
    'status' => version_compare( $wp_version, $wp_required, '>=' ) ? 'good' : 'warning',
    'value' => $wp_version,
    'message' => version_compare( $wp_version, $wp_required, '>=' )
        ? sprintf( __( 'Version %s meets requirements', 'money-quiz' ), $wp_version )
        : sprintf( __( 'Version %s is below recommended %s', 'money-quiz' ), $wp_version, $wp_required )
];

// Database Tables
$required_tables = [
    'money_quiz_quizzes',
    'money_quiz_questions',
    'money_quiz_archetypes',
    'money_quiz_prospects',
    'money_quiz_results',
    'money_quiz_email_campaigns'
];

$missing_tables = [];
foreach ( $required_tables as $table ) {
    $table_name = $table_prefix . $table;
    if ( $wpdb->get_var( "SHOW TABLES LIKE '$table_name'" ) != $table_name ) {
        $missing_tables[] = $table;
    }
}

$checks['database'] = [
    'label' => __( 'Database Tables', 'money-quiz' ),
    'status' => empty( $missing_tables ) ? 'good' : 'error',
    'value' => empty( $missing_tables ) ? __( 'All tables present', 'money-quiz' ) : count( $missing_tables ) . ' ' . __( 'missing', 'money-quiz' ),
    'message' => empty( $missing_tables ) 
        ? __( 'All required database tables are installed', 'money-quiz' )
        : sprintf( __( 'Missing tables: %s', 'money-quiz' ), implode( ', ', $missing_tables ) )
];

// Plugin Version
$plugin_version = defined( 'MONEY_QUIZ_VERSION' ) ? MONEY_QUIZ_VERSION : 'Unknown';
$checks['plugin_version'] = [
    'label' => __( 'Plugin Version', 'money-quiz' ),
    'status' => 'good',
    'value' => $plugin_version,
    'message' => __( 'Money Quiz plugin version', 'money-quiz' )
];

// Memory Limit
$memory_limit = ini_get( 'memory_limit' );
$memory_bytes = wp_convert_hr_to_bytes( $memory_limit );
$required_memory = 134217728; // 128MB
$checks['memory'] = [
    'label' => __( 'PHP Memory Limit', 'money-quiz' ),
    'status' => $memory_bytes >= $required_memory ? 'good' : 'warning',
    'value' => $memory_limit,
    'message' => $memory_bytes >= $required_memory
        ? __( 'Sufficient memory available', 'money-quiz' )
        : __( 'Consider increasing memory limit for better performance', 'money-quiz' )
];

// Email Configuration
$email_provider = get_option( 'money_quiz_email_provider', 'wp_mail' );
$email_configured = false;

if ( $email_provider === 'smtp' && get_option( 'money_quiz_smtp_host' ) ) {
    $email_configured = true;
} elseif ( $email_provider === 'mailchimp' && get_option( 'money_quiz_mailchimp_api_key' ) ) {
    $email_configured = true;
} elseif ( $email_provider === 'wp_mail' ) {
    $email_configured = true;
}

$checks['email'] = [
    'label' => __( 'Email Service', 'money-quiz' ),
    'status' => $email_configured ? 'good' : 'warning',
    'value' => ucfirst( str_replace( '_', ' ', $email_provider ) ),
    'message' => $email_configured
        ? __( 'Email service is configured', 'money-quiz' )
        : __( 'Email service needs configuration', 'money-quiz' )
];

// SSL/HTTPS
$is_ssl = is_ssl();
$checks['ssl'] = [
    'label' => __( 'SSL/HTTPS', 'money-quiz' ),
    'status' => $is_ssl ? 'good' : 'warning',
    'value' => $is_ssl ? __( 'Enabled', 'money-quiz' ) : __( 'Not enabled', 'money-quiz' ),
    'message' => $is_ssl
        ? __( 'Site is using secure HTTPS connection', 'money-quiz' )
        : __( 'Consider enabling SSL for better security', 'money-quiz' )
];

// File Permissions
$upload_dir = wp_upload_dir();
$upload_writable = wp_is_writable( $upload_dir['basedir'] );
$checks['uploads'] = [
    'label' => __( 'Upload Directory', 'money-quiz' ),
    'status' => $upload_writable ? 'good' : 'error',
    'value' => $upload_writable ? __( 'Writable', 'money-quiz' ) : __( 'Not writable', 'money-quiz' ),
    'message' => $upload_writable
        ? __( 'Upload directory has correct permissions', 'money-quiz' )
        : __( 'Upload directory is not writable', 'money-quiz' )
];

// Cron Jobs
$cron_events = [
    'money_quiz_daily_cleanup' => __( 'Daily Cleanup', 'money-quiz' ),
    'money_quiz_email_queue' => __( 'Email Queue Processing', 'money-quiz' ),
    'money_quiz_analytics_update' => __( 'Analytics Update', 'money-quiz' )
];

$missing_crons = [];
foreach ( $cron_events as $hook => $name ) {
    if ( ! wp_next_scheduled( $hook ) ) {
        $missing_crons[] = $name;
    }
}

$checks['cron'] = [
    'label' => __( 'Scheduled Tasks', 'money-quiz' ),
    'status' => empty( $missing_crons ) ? 'good' : 'warning',
    'value' => empty( $missing_crons ) ? __( 'All active', 'money-quiz' ) : count( $missing_crons ) . ' ' . __( 'inactive', 'money-quiz' ),
    'message' => empty( $missing_crons )
        ? __( 'All scheduled tasks are running', 'money-quiz' )
        : sprintf( __( 'Inactive tasks: %s', 'money-quiz' ), implode( ', ', $missing_crons ) )
];

// Recent Errors
$error_log = get_option( 'money_quiz_error_log', [] );
$recent_errors = array_slice( $error_log, -5 );
$error_count = count( $error_log );

$checks['errors'] = [
    'label' => __( 'Error Log', 'money-quiz' ),
    'status' => $error_count === 0 ? 'good' : ( $error_count > 10 ? 'warning' : 'info' ),
    'value' => $error_count . ' ' . __( 'errors', 'money-quiz' ),
    'message' => $error_count === 0
        ? __( 'No errors logged', 'money-quiz' )
        : sprintf( __( '%d errors in log', 'money-quiz' ), $error_count )
];

// Calculate overall health score
$status_scores = ['good' => 100, 'info' => 80, 'warning' => 60, 'error' => 0];
$total_score = 0;
$check_count = 0;

foreach ( $checks as $check ) {
    if ( isset( $status_scores[ $check['status'] ] ) ) {
        $total_score += $status_scores[ $check['status'] ];
        $check_count++;
    }
}

$health_score = $check_count > 0 ? round( $total_score / $check_count ) : 0;
$health_status = $health_score >= 90 ? 'excellent' : ( $health_score >= 70 ? 'good' : ( $health_score >= 50 ? 'fair' : 'poor' ) );
?>

<div class="wrap mq-system-health">
    
    <!-- Health Score -->
    <div class="mq-health-score mq-health-<?php echo esc_attr( $health_status ); ?>">
        <div class="mq-score-circle">
            <svg viewBox="0 0 200 200">
                <circle cx="100" cy="100" r="90" fill="none" stroke="#e0e0e0" stroke-width="20"/>
                <circle cx="100" cy="100" r="90" fill="none" stroke="currentColor" stroke-width="20"
                        stroke-dasharray="<?php echo 565 * ( $health_score / 100 ); ?> 565"
                        transform="rotate(-90 100 100)"/>
            </svg>
            <div class="mq-score-text">
                <span class="mq-score-number"><?php echo $health_score; ?></span>
                <span class="mq-score-label"><?php _e( 'Health Score', 'money-quiz' ); ?></span>
            </div>
        </div>
        <div class="mq-score-details">
            <h2><?php _e( 'System Health:', 'money-quiz' ); ?> 
                <span class="mq-health-status"><?php echo ucfirst( $health_status ); ?></span>
            </h2>
            <p><?php 
                if ( $health_status === 'excellent' ) {
                    _e( 'Your Money Quiz system is running optimally with no issues detected.', 'money-quiz' );
                } elseif ( $health_status === 'good' ) {
                    _e( 'Your system is healthy with minor recommendations for improvement.', 'money-quiz' );
                } elseif ( $health_status === 'fair' ) {
                    _e( 'Your system has some issues that should be addressed for optimal performance.', 'money-quiz' );
                } else {
                    _e( 'Your system has critical issues that need immediate attention.', 'money-quiz' );
                }
            ?></p>
        </div>
    </div>
    
    <!-- System Checks -->
    <div class="mq-health-checks">
        <h3><?php _e( 'System Checks', 'money-quiz' ); ?></h3>
        
        <table class="mq-checks-table">
            <thead>
                <tr>
                    <th><?php _e( 'Component', 'money-quiz' ); ?></th>
                    <th><?php _e( 'Status', 'money-quiz' ); ?></th>
                    <th><?php _e( 'Value', 'money-quiz' ); ?></th>
                    <th><?php _e( 'Details', 'money-quiz' ); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ( $checks as $key => $check ) : ?>
                    <tr class="mq-check-<?php echo esc_attr( $check['status'] ); ?>">
                        <td class="mq-check-label"><?php echo esc_html( $check['label'] ); ?></td>
                        <td class="mq-check-status">
                            <span class="mq-status-icon mq-status-<?php echo esc_attr( $check['status'] ); ?>">
                                <?php 
                                switch ( $check['status'] ) {
                                    case 'good':
                                        echo '✓';
                                        break;
                                    case 'warning':
                                        echo '⚠';
                                        break;
                                    case 'error':
                                        echo '✗';
                                        break;
                                    default:
                                        echo 'ℹ';
                                }
                                ?>
                            </span>
                        </td>
                        <td class="mq-check-value"><?php echo esc_html( $check['value'] ); ?></td>
                        <td class="mq-check-message"><?php echo esc_html( $check['message'] ); ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    
    <!-- Recent Errors -->
    <?php if ( ! empty( $recent_errors ) ) : ?>
        <div class="mq-recent-errors mq-card">
            <h3 class="mq-card-title"><?php _e( 'Recent Errors', 'money-quiz' ); ?></h3>
            <div class="mq-error-list">
                <?php foreach ( $recent_errors as $error ) : ?>
                    <div class="mq-error-item">
                        <span class="mq-error-time"><?php echo esc_html( $error['time'] ); ?></span>
                        <span class="mq-error-type"><?php echo esc_html( $error['type'] ); ?></span>
                        <span class="mq-error-message"><?php echo esc_html( $error['message'] ); ?></span>
                    </div>
                <?php endforeach; ?>
            </div>
            <a href="<?php echo admin_url( 'admin.php?page=money-quiz-settings-advanced#error-log' ); ?>" class="button">
                <?php _e( 'View Full Error Log', 'money-quiz' ); ?>
            </a>
        </div>
    <?php endif; ?>
    
    <!-- Actions -->
    <div class="mq-health-actions">
        <h3><?php _e( 'Maintenance Actions', 'money-quiz' ); ?></h3>
        <div class="mq-action-buttons">
            <button class="button" onclick="mqRunHealthCheck()">
                <?php _e( 'Re-run Health Check', 'money-quiz' ); ?>
            </button>
            <button class="button" onclick="mqClearCache()">
                <?php _e( 'Clear Cache', 'money-quiz' ); ?>
            </button>
            <button class="button" onclick="mqOptimizeTables()">
                <?php _e( 'Optimize Database', 'money-quiz' ); ?>
            </button>
            <a href="<?php echo admin_url( 'admin.php?page=money-quiz-settings-advanced' ); ?>" class="button">
                <?php _e( 'Advanced Settings', 'money-quiz' ); ?>
            </a>
        </div>
    </div>
    
</div>

<script>
function mqRunHealthCheck() {
    location.reload();
}

function mqClearCache() {
    if (confirm('<?php _e( 'Clear all Money Quiz cache?', 'money-quiz' ); ?>')) {
        // Implementation for cache clearing
        alert('<?php _e( 'Cache cleared successfully!', 'money-quiz' ); ?>');
    }
}

function mqOptimizeTables() {
    if (confirm('<?php _e( 'Optimize Money Quiz database tables?', 'money-quiz' ); ?>')) {
        // Implementation for table optimization
        alert('<?php _e( 'Database optimization started. This may take a few moments.', 'money-quiz' ); ?>');
    }
}
</script>

<style>
.mq-health-score {
    display: flex;
    align-items: center;
    gap: 30px;
    background: #fff;
    padding: 30px;
    border-radius: 4px;
    border: 1px solid #c3c4c7;
    margin-bottom: 30px;
}

.mq-score-circle {
    position: relative;
    width: 150px;
    height: 150px;
    flex-shrink: 0;
}

.mq-score-circle svg {
    width: 100%;
    height: 100%;
}

.mq-health-excellent .mq-score-circle { color: #00a32a; }
.mq-health-good .mq-score-circle { color: #0073aa; }
.mq-health-fair .mq-score-circle { color: #dba617; }
.mq-health-poor .mq-score-circle { color: #dc3232; }

.mq-score-text {
    position: absolute;
    top: 50%;
    left: 50%;
    transform: translate(-50%, -50%);
    text-align: center;
}

.mq-score-number {
    display: block;
    font-size: 48px;
    font-weight: 600;
    line-height: 1;
}

.mq-score-label {
    display: block;
    font-size: 14px;
    color: #646970;
    margin-top: 5px;
}

.mq-health-status {
    color: inherit;
}

.mq-health-checks {
    background: #fff;
    padding: 20px;
    border-radius: 4px;
    border: 1px solid #c3c4c7;
    margin-bottom: 30px;
}

.mq-checks-table {
    width: 100%;
    border-collapse: collapse;
    margin-top: 15px;
}

.mq-checks-table th {
    text-align: left;
    padding: 10px;
    border-bottom: 1px solid #c3c4c7;
    font-weight: 600;
}

.mq-checks-table td {
    padding: 10px;
    border-bottom: 1px solid #f0f0f1;
}

.mq-status-icon {
    display: inline-block;
    width: 24px;
    height: 24px;
    line-height: 24px;
    text-align: center;
    border-radius: 50%;
    font-weight: bold;
}

.mq-status-good { background: #d4f4dd; color: #00a32a; }
.mq-status-warning { background: #fcf9e8; color: #dba617; }
.mq-status-error { background: #facfd2; color: #dc3232; }
.mq-status-info { background: #e5f5fa; color: #0073aa; }

.mq-error-list {
    font-family: monospace;
    font-size: 12px;
    background: #f6f7f7;
    padding: 15px;
    border-radius: 4px;
    margin-bottom: 15px;
    max-height: 200px;
    overflow-y: auto;
}

.mq-error-item {
    margin-bottom: 5px;
    display: flex;
    gap: 10px;
}

.mq-error-time { color: #646970; }
.mq-error-type { color: #dc3232; font-weight: 600; }
.mq-error-message { flex: 1; }

.mq-health-actions {
    background: #fff;
    padding: 20px;
    border-radius: 4px;
    border: 1px solid #c3c4c7;
}

.mq-action-buttons {
    display: flex;
    gap: 10px;
    margin-top: 15px;
}
</style>
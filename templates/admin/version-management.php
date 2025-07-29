<?php
/**
 * Admin Template: Version Management
 * 
 * @package MoneyQuiz
 * @since 4.0.0
 */

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// Get data passed from controller
$version_report = $version_report ?? array();
$consistency_results = $consistency_results ?? array();
$database_integrity = $database_integrity ?? array();
?>

<div class="wrap money-quiz-version-management">
    <h1><?php _e( 'Money Quiz Version Management', 'money-quiz' ); ?></h1>
    
    <?php if ( ! empty( $version_report['summary']['needs_reconciliation'] ) ) : ?>
        <div class="notice notice-warning">
            <p>
                <strong><?php _e( 'Version Reconciliation Required', 'money-quiz' ); ?></strong>
                <?php _e( 'Version mismatches have been detected. Click the button below to reconcile all versions.', 'money-quiz' ); ?>
            </p>
            <p>
                <button type="button" class="button button-primary" id="reconcile-versions">
                    <?php _e( 'Reconcile Versions Now', 'money-quiz' ); ?>
                </button>
            </p>
        </div>
    <?php endif; ?>
    
    <!-- Version Overview -->
    <div class="money-quiz-admin-section">
        <h2><?php _e( 'Version Overview', 'money-quiz' ); ?></h2>
        
        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th><?php _e( 'Component', 'money-quiz' ); ?></th>
                    <th><?php _e( 'Detected Version', 'money-quiz' ); ?></th>
                    <th><?php _e( 'Confidence', 'money-quiz' ); ?></th>
                    <th><?php _e( 'Status', 'money-quiz' ); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php if ( ! empty( $version_report['details'] ) ) : ?>
                    <?php foreach ( $version_report['details'] as $source => $data ) : ?>
                        <tr>
                            <td>
                                <strong><?php echo esc_html( ucwords( str_replace( '_', ' ', $source ) ) ); ?></strong>
                            </td>
                            <td>
                                <?php echo esc_html( $data['version'] ?? 'Unknown' ); ?>
                            </td>
                            <td>
                                <?php 
                                $confidence = $data['confidence'] ?? 'unknown';
                                $confidence_class = '';
                                switch ( $confidence ) {
                                    case 'high':
                                        $confidence_class = 'dashicons-yes-alt';
                                        break;
                                    case 'medium':
                                        $confidence_class = 'dashicons-warning';
                                        break;
                                    case 'low':
                                        $confidence_class = 'dashicons-dismiss';
                                        break;
                                    default:
                                        $confidence_class = 'dashicons-editor-help';
                                }
                                ?>
                                <span class="dashicons <?php echo esc_attr( $confidence_class ); ?>" 
                                      title="<?php echo esc_attr( ucfirst( $confidence ) ); ?>"></span>
                                <?php echo esc_html( ucfirst( $confidence ) ); ?>
                            </td>
                            <td>
                                <?php
                                $target_version = $version_report['summary']['target_version'] ?? '4.0.0';
                                $is_aligned = version_compare( $data['version'] ?? '0', $target_version, '=' );
                                ?>
                                <?php if ( $is_aligned ) : ?>
                                    <span class="dashicons dashicons-yes" style="color: #46b450;"></span>
                                    <?php _e( 'Aligned', 'money-quiz' ); ?>
                                <?php else : ?>
                                    <span class="dashicons dashicons-no" style="color: #dc3232;"></span>
                                    <?php _e( 'Misaligned', 'money-quiz' ); ?>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
    
    <!-- Consistency Check Results -->
    <?php if ( ! empty( $consistency_results ) ) : ?>
    <div class="money-quiz-admin-section">
        <h2><?php _e( 'Consistency Check Results', 'money-quiz' ); ?></h2>
        
        <div class="consistency-summary">
            <div class="consistency-score">
                <h3><?php _e( 'Consistency Score', 'money-quiz' ); ?></h3>
                <div class="score-circle" data-score="<?php echo esc_attr( $consistency_results['summary']['consistency_score'] ?? 0 ); ?>">
                    <span class="score-value"><?php echo esc_html( $consistency_results['summary']['consistency_score'] ?? 0 ); ?>%</span>
                </div>
            </div>
            
            <div class="issue-summary">
                <h3><?php _e( 'Issues Found', 'money-quiz' ); ?></h3>
                <ul>
                    <li>
                        <span class="issue-count critical"><?php echo esc_html( $consistency_results['summary']['critical_issues'] ?? 0 ); ?></span>
                        <?php _e( 'Critical', 'money-quiz' ); ?>
                    </li>
                    <li>
                        <span class="issue-count high"><?php echo esc_html( $consistency_results['summary']['high_issues'] ?? 0 ); ?></span>
                        <?php _e( 'High Priority', 'money-quiz' ); ?>
                    </li>
                    <li>
                        <span class="issue-count medium"><?php echo esc_html( $consistency_results['summary']['medium_issues'] ?? 0 ); ?></span>
                        <?php _e( 'Medium Priority', 'money-quiz' ); ?>
                    </li>
                    <li>
                        <span class="issue-count low"><?php echo esc_html( $consistency_results['summary']['low_issues'] ?? 0 ); ?></span>
                        <?php _e( 'Low Priority', 'money-quiz' ); ?>
                    </li>
                </ul>
            </div>
        </div>
        
        <?php if ( ! empty( $consistency_results['recommendations'] ) ) : ?>
        <h3><?php _e( 'Recommendations', 'money-quiz' ); ?></h3>
        <div class="recommendations">
            <?php foreach ( $consistency_results['recommendations'] as $recommendation ) : ?>
                <div class="recommendation-item priority-<?php echo esc_attr( $recommendation['priority'] ); ?>">
                    <h4><?php echo esc_html( $recommendation['action'] ); ?></h4>
                    <p><?php echo esc_html( $recommendation['description'] ); ?></p>
                </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </div>
    <?php endif; ?>
    
    <!-- Database Integrity -->
    <div class="money-quiz-admin-section">
        <h2><?php _e( 'Database Integrity', 'money-quiz' ); ?></h2>
        
        <?php if ( ! empty( $database_integrity['is_valid'] ) ) : ?>
            <div class="notice notice-success inline">
                <p><?php _e( 'Database schema is valid and up to date.', 'money-quiz' ); ?></p>
            </div>
        <?php else : ?>
            <div class="notice notice-warning inline">
                <p>
                    <?php 
                    printf( 
                        __( 'Database schema issues detected. %d issues found.', 'money-quiz' ),
                        count( $database_integrity['issues'] ?? array() )
                    ); 
                    ?>
                </p>
                <p>
                    <button type="button" class="button" id="repair-database">
                        <?php _e( 'Repair Database', 'money-quiz' ); ?>
                    </button>
                </p>
            </div>
            
            <?php if ( ! empty( $database_integrity['issues'] ) ) : ?>
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th><?php _e( 'Issue Type', 'money-quiz' ); ?></th>
                        <th><?php _e( 'Table', 'money-quiz' ); ?></th>
                        <th><?php _e( 'Details', 'money-quiz' ); ?></th>
                        <th><?php _e( 'Severity', 'money-quiz' ); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ( $database_integrity['issues'] as $issue ) : ?>
                        <tr>
                            <td><?php echo esc_html( str_replace( '_', ' ', $issue['type'] ) ); ?></td>
                            <td><?php echo esc_html( $issue['table'] ); ?></td>
                            <td>
                                <?php 
                                if ( isset( $issue['column'] ) ) {
                                    echo esc_html( $issue['column'] );
                                } elseif ( isset( $issue['index'] ) ) {
                                    echo esc_html( $issue['index'] );
                                }
                                ?>
                            </td>
                            <td>
                                <span class="severity-badge severity-<?php echo esc_attr( $issue['severity'] ); ?>">
                                    <?php echo esc_html( ucfirst( $issue['severity'] ) ); ?>
                                </span>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <?php endif; ?>
        <?php endif; ?>
    </div>
    
    <!-- Actions -->
    <div class="money-quiz-admin-section">
        <h2><?php _e( 'Actions', 'money-quiz' ); ?></h2>
        
        <div class="action-buttons">
            <button type="button" class="button" id="run-consistency-check">
                <?php _e( 'Run Consistency Check', 'money-quiz' ); ?>
            </button>
            
            <button type="button" class="button" id="check-versions">
                <?php _e( 'Re-check Versions', 'money-quiz' ); ?>
            </button>
            
            <button type="button" class="button" id="export-report">
                <?php _e( 'Export Report', 'money-quiz' ); ?>
            </button>
        </div>
    </div>
</div>

<style>
.money-quiz-version-management {
    max-width: 1200px;
}

.money-quiz-admin-section {
    background: #fff;
    border: 1px solid #ccd0d4;
    box-shadow: 0 1px 1px rgba(0,0,0,.04);
    margin: 20px 0;
    padding: 20px;
}

.consistency-summary {
    display: flex;
    gap: 40px;
    margin: 20px 0;
}

.consistency-score {
    text-align: center;
}

.score-circle {
    width: 120px;
    height: 120px;
    border-radius: 50%;
    border: 8px solid #ddd;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 24px;
    font-weight: bold;
    margin: 10px auto;
}

.score-circle[data-score="100"] { border-color: #46b450; }
.score-circle[data-score^="9"] { border-color: #46b450; }
.score-circle[data-score^="8"] { border-color: #00a0d2; }
.score-circle[data-score^="7"] { border-color: #ffb900; }
.score-circle[data-score^="6"] { border-color: #dc3232; }

.issue-summary ul {
    list-style: none;
    padding: 0;
}

.issue-summary li {
    margin: 10px 0;
    display: flex;
    align-items: center;
    gap: 10px;
}

.issue-count {
    display: inline-block;
    width: 40px;
    height: 40px;
    line-height: 40px;
    text-align: center;
    border-radius: 50%;
    font-weight: bold;
    color: #fff;
}

.issue-count.critical { background: #dc3232; }
.issue-count.high { background: #f56e28; }
.issue-count.medium { background: #ffb900; }
.issue-count.low { background: #00a0d2; }

.severity-badge {
    padding: 2px 8px;
    border-radius: 3px;
    font-size: 12px;
    color: #fff;
}

.severity-critical { background: #dc3232; }
.severity-high { background: #f56e28; }
.severity-medium { background: #ffb900; }
.severity-low { background: #00a0d2; }

.recommendation-item {
    border-left: 4px solid #ddd;
    padding: 10px 15px;
    margin: 10px 0;
}

.recommendation-item.priority-immediate { border-color: #dc3232; }
.recommendation-item.priority-high { border-color: #f56e28; }
.recommendation-item.priority-medium { border-color: #ffb900; }

.action-buttons {
    display: flex;
    gap: 10px;
}
</style>

<script>
jQuery(document).ready(function($) {
    // Reconcile versions
    $('#reconcile-versions').on('click', function() {
        var $button = $(this);
        $button.prop('disabled', true).text('<?php _e( 'Reconciling...', 'money-quiz' ); ?>');
        
        $.post(ajaxurl, {
            action: 'mq_reconcile_versions',
            nonce: '<?php echo wp_create_nonce( 'money_quiz_admin' ); ?>'
        }, function(response) {
            if (response.success) {
                location.reload();
            } else {
                alert('<?php _e( 'Reconciliation failed. Please check the error log.', 'money-quiz' ); ?>');
                $button.prop('disabled', false).text('<?php _e( 'Reconcile Versions Now', 'money-quiz' ); ?>');
            }
        });
    });
    
    // Check versions
    $('#check-versions').on('click', function() {
        var $button = $(this);
        $button.prop('disabled', true).text('<?php _e( 'Checking...', 'money-quiz' ); ?>');
        
        $.post(ajaxurl, {
            action: 'mq_check_versions',
            nonce: '<?php echo wp_create_nonce( 'money_quiz_admin' ); ?>'
        }, function(response) {
            location.reload();
        });
    });
    
    // Run consistency check
    $('#run-consistency-check').on('click', function() {
        var $button = $(this);
        $button.prop('disabled', true).text('<?php _e( 'Running Check...', 'money-quiz' ); ?>');
        
        // This would trigger a consistency check
        // For now, just reload to show latest results
        location.reload();
    });
    
    // Repair database
    $('#repair-database').on('click', function() {
        if (!confirm('<?php _e( 'Are you sure you want to repair the database? This action cannot be undone.', 'money-quiz' ); ?>')) {
            return;
        }
        
        var $button = $(this);
        $button.prop('disabled', true).text('<?php _e( 'Repairing...', 'money-quiz' ); ?>');
        
        // This would trigger database repair
        // Implementation would go here
    });
    
    // Export report
    $('#export-report').on('click', function() {
        // This would export the version report
        alert('<?php _e( 'Report export feature coming soon.', 'money-quiz' ); ?>');
    });
});
</script>
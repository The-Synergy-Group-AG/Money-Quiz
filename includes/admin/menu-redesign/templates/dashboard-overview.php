<?php
/**
 * Dashboard Overview Template
 * 
 * @package MoneyQuiz
 * @since 4.2.0
 */

// Security check
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// Get stats
global $wpdb;
$table_prefix = $wpdb->prefix;

// Active quizzes
$active_quizzes = $wpdb->get_var( "SELECT COUNT(*) FROM {$table_prefix}money_quiz_quizzes WHERE status = 'active'" );
if ( ! $active_quizzes ) {
    // Fallback to legacy table
    $active_quizzes = $wpdb->get_var( "SELECT COUNT(DISTINCT quiz_id) FROM {$table_prefix}mq_master WHERE status = 1" );
}

// Total leads
$total_leads = $wpdb->get_var( "SELECT COUNT(*) FROM {$table_prefix}money_quiz_prospects" );
if ( ! $total_leads ) {
    // Fallback to legacy table
    $total_leads = $wpdb->get_var( "SELECT COUNT(*) FROM {$table_prefix}mq_prospects" );
}

// This week's leads
$week_start = date( 'Y-m-d', strtotime( 'monday this week' ) );
$week_leads = $wpdb->get_var( $wpdb->prepare(
    "SELECT COUNT(*) FROM {$table_prefix}money_quiz_prospects WHERE created_at >= %s",
    $week_start
) );
if ( ! $week_leads ) {
    // Fallback to legacy table
    $week_leads = $wpdb->get_var( $wpdb->prepare(
        "SELECT COUNT(*) FROM {$table_prefix}mq_prospects WHERE date >= %s",
        $week_start
    ) );
}

// Completion rate
$total_starts = $wpdb->get_var( "SELECT COUNT(*) FROM {$table_prefix}money_quiz_results" );
if ( ! $total_starts ) {
    $total_starts = $wpdb->get_var( "SELECT COUNT(*) FROM {$table_prefix}mq_taken" );
}
$completion_rate = $total_starts > 0 ? round( ( $total_leads / $total_starts ) * 100, 1 ) : 0;

// Recent activity
$recent_activities = [];

// Get recent quiz completions
$recent_results = $wpdb->get_results(
    "SELECT p.first_name, p.last_name, p.created_at, 'completed' as action 
     FROM {$table_prefix}money_quiz_prospects p 
     ORDER BY p.created_at DESC 
     LIMIT 5"
);

if ( empty( $recent_results ) ) {
    // Fallback to legacy
    $recent_results = $wpdb->get_results(
        "SELECT name as first_name, '' as last_name, date as created_at, 'completed' as action 
         FROM {$table_prefix}mq_prospects 
         ORDER BY date DESC 
         LIMIT 5"
    );
}

foreach ( $recent_results as $result ) {
    $recent_activities[] = [
        'user' => trim( $result->first_name . ' ' . $result->last_name ) ?: 'Anonymous',
        'action' => 'completed Money Quiz',
        'time' => human_time_diff( strtotime( $result->created_at ), current_time( 'timestamp' ) ) . ' ago'
    ];
}
?>

<div class="wrap mq-dashboard">
    
    <!-- Stats Cards -->
    <div class="mq-dashboard-grid">
        
        <!-- Active Quizzes -->
        <div class="mq-card">
            <div class="mq-card-header">
                <h3 class="mq-card-title"><?php _e( 'Active Quizzes', 'money-quiz' ); ?></h3>
                <span class="mq-card-icon">📊</span>
            </div>
            <div class="mq-stat-value"><?php echo number_format( $active_quizzes ); ?></div>
            <div class="mq-stat-label"><?php _e( 'Currently active', 'money-quiz' ); ?></div>
        </div>
        
        <!-- Total Leads -->
        <div class="mq-card">
            <div class="mq-card-header">
                <h3 class="mq-card-title"><?php _e( 'Total Leads', 'money-quiz' ); ?></h3>
                <span class="mq-card-icon">👥</span>
            </div>
            <div class="mq-stat-value"><?php echo number_format( $total_leads ); ?></div>
            <div class="mq-stat-label">
                <?php echo sprintf( __( '+%d this week', 'money-quiz' ), $week_leads ); ?>
            </div>
        </div>
        
        <!-- Completion Rate -->
        <div class="mq-card">
            <div class="mq-card-header">
                <h3 class="mq-card-title"><?php _e( 'Completion Rate', 'money-quiz' ); ?></h3>
                <span class="mq-card-icon">📈</span>
            </div>
            <div class="mq-stat-value"><?php echo $completion_rate; ?>%</div>
            <div class="mq-stat-label"><?php _e( 'Quiz completion rate', 'money-quiz' ); ?></div>
        </div>
        
    </div>
    
    <!-- Quick Actions & Recent Activity -->
    <div class="mq-dashboard-grid" style="margin-top: 20px;">
        
        <!-- Quick Actions -->
        <div class="mq-card">
            <h3 class="mq-card-title"><?php _e( '🎯 Quick Actions', 'money-quiz' ); ?></h3>
            <div class="mq-quick-actions-grid">
                <a href="<?php echo admin_url( 'admin.php?page=money-quiz-quizzes-add-new' ); ?>" class="mq-quick-action">
                    <span class="mq-quick-action-icon">➕</span>
                    <span class="mq-quick-action-label"><?php _e( 'New Quiz', 'money-quiz' ); ?></span>
                </a>
                <a href="<?php echo admin_url( 'admin.php?page=money-quiz-audience-results' ); ?>" class="mq-quick-action">
                    <span class="mq-quick-action-icon">📊</span>
                    <span class="mq-quick-action-label"><?php _e( 'View Results', 'money-quiz' ); ?></span>
                </a>
                <a href="<?php echo admin_url( 'admin.php?page=money-quiz-quizzes-questions' ); ?>" class="mq-quick-action">
                    <span class="mq-quick-action-icon">❓</span>
                    <span class="mq-quick-action-label"><?php _e( 'Questions', 'money-quiz' ); ?></span>
                </a>
                <a href="<?php echo admin_url( 'admin.php?page=money-quiz-marketing-campaigns' ); ?>" class="mq-quick-action">
                    <span class="mq-quick-action-icon">📧</span>
                    <span class="mq-quick-action-label"><?php _e( 'Email Campaign', 'money-quiz' ); ?></span>
                </a>
                <a href="<?php echo admin_url( 'admin.php?page=money-quiz-settings-general' ); ?>" class="mq-quick-action">
                    <span class="mq-quick-action-icon">⚙️</span>
                    <span class="mq-quick-action-label"><?php _e( 'Settings', 'money-quiz' ); ?></span>
                </a>
                <a href="<?php echo admin_url( 'admin.php?page=money-quiz-audience-export' ); ?>" class="mq-quick-action">
                    <span class="mq-quick-action-icon">📥</span>
                    <span class="mq-quick-action-label"><?php _e( 'Export Data', 'money-quiz' ); ?></span>
                </a>
            </div>
        </div>
        
        <!-- Recent Activity -->
        <div class="mq-card">
            <h3 class="mq-card-title"><?php _e( '📈 Recent Activity', 'money-quiz' ); ?></h3>
            <?php if ( ! empty( $recent_activities ) ) : ?>
                <ul class="mq-activity-list">
                    <?php foreach ( $recent_activities as $activity ) : ?>
                        <li>
                            <strong><?php echo esc_html( $activity['user'] ); ?></strong>
                            <?php echo esc_html( $activity['action'] ); ?>
                            <span class="mq-activity-time"><?php echo esc_html( $activity['time'] ); ?></span>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php else : ?>
                <p><?php _e( 'No recent activity to show.', 'money-quiz' ); ?></p>
            <?php endif; ?>
        </div>
        
    </div>
    
    <!-- System Status -->
    <div class="mq-card" style="margin-top: 20px;">
        <h3 class="mq-card-title"><?php _e( '🔧 System Status', 'money-quiz' ); ?></h3>
        <div class="mq-system-status">
            <div class="mq-status-item">
                <span class="mq-status-label"><?php _e( 'Plugin Version:', 'money-quiz' ); ?></span>
                <span class="mq-status-value"><?php echo MONEY_QUIZ_VERSION; ?></span>
            </div>
            <div class="mq-status-item">
                <span class="mq-status-label"><?php _e( 'Database Tables:', 'money-quiz' ); ?></span>
                <span class="mq-status-value mq-status-good">✓ <?php _e( 'All OK', 'money-quiz' ); ?></span>
            </div>
            <div class="mq-status-item">
                <span class="mq-status-label"><?php _e( 'Email Service:', 'money-quiz' ); ?></span>
                <span class="mq-status-value mq-status-good">✓ <?php _e( 'Connected', 'money-quiz' ); ?></span>
            </div>
        </div>
    </div>
    
</div>

<style>
.mq-activity-list {
    list-style: none;
    padding: 0;
    margin: 0;
}

.mq-activity-list li {
    padding: 10px 0;
    border-bottom: 1px solid #f0f0f1;
}

.mq-activity-list li:last-child {
    border-bottom: none;
}

.mq-activity-time {
    color: #646970;
    font-size: 12px;
    float: right;
}

.mq-system-status {
    display: grid;
    gap: 10px;
}

.mq-status-item {
    display: flex;
    justify-content: space-between;
    padding: 8px 0;
}

.mq-status-label {
    color: #646970;
}

.mq-status-good {
    color: #00a32a;
}

.mq-status-warning {
    color: #dba617;
}

.mq-status-error {
    color: #dc3232;
}
</style>
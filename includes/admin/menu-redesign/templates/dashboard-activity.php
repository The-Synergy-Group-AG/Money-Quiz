<?php
/**
 * Dashboard Activity Template
 * 
 * @package MoneyQuiz
 * @since 4.2.0
 */

// Security check
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// Get activity data
global $wpdb;
$table_prefix = $wpdb->prefix;

// Time filters
$time_filter = isset( $_GET['time'] ) ? sanitize_text_field( $_GET['time'] ) : '7days';
$start_date = '';

switch ( $time_filter ) {
    case 'today':
        $start_date = date( 'Y-m-d 00:00:00' );
        break;
    case 'yesterday':
        $start_date = date( 'Y-m-d 00:00:00', strtotime( '-1 day' ) );
        $end_date = date( 'Y-m-d 23:59:59', strtotime( '-1 day' ) );
        break;
    case '7days':
        $start_date = date( 'Y-m-d 00:00:00', strtotime( '-7 days' ) );
        break;
    case '30days':
        $start_date = date( 'Y-m-d 00:00:00', strtotime( '-30 days' ) );
        break;
}

// Fetch all activity types
$activities = [];

// Quiz completions
$quiz_completions = $wpdb->get_results( $wpdb->prepare(
    "SELECT 
        p.first_name, 
        p.last_name, 
        p.email,
        p.created_at as activity_time,
        'quiz_completion' as activity_type,
        q.name as quiz_name
     FROM {$table_prefix}money_quiz_prospects p
     LEFT JOIN {$table_prefix}money_quiz_quizzes q ON p.quiz_id = q.id
     WHERE p.created_at >= %s
     ORDER BY p.created_at DESC
     LIMIT 50",
    $start_date
) );

// Add to activities
foreach ( $quiz_completions as $completion ) {
    $activities[] = [
        'time' => strtotime( $completion->activity_time ),
        'type' => 'quiz_completion',
        'user' => trim( $completion->first_name . ' ' . $completion->last_name ) ?: $completion->email,
        'details' => sprintf( __( 'completed "%s" quiz', 'money-quiz' ), $completion->quiz_name ?: __( 'Unknown Quiz', 'money-quiz' ) ),
        'icon' => '✅'
    ];
}

// Quiz starts (from results table)
$quiz_starts = $wpdb->get_results( $wpdb->prepare(
    "SELECT 
        r.created_at as activity_time,
        'quiz_start' as activity_type,
        q.name as quiz_name
     FROM {$table_prefix}money_quiz_results r
     LEFT JOIN {$table_prefix}money_quiz_quizzes q ON r.quiz_id = q.id
     WHERE r.created_at >= %s
     ORDER BY r.created_at DESC
     LIMIT 50",
    $start_date
) );

foreach ( $quiz_starts as $start ) {
    $activities[] = [
        'time' => strtotime( $start->activity_time ),
        'type' => 'quiz_start',
        'user' => __( 'Visitor', 'money-quiz' ),
        'details' => sprintf( __( 'started "%s" quiz', 'money-quiz' ), $start->quiz_name ?: __( 'Unknown Quiz', 'money-quiz' ) ),
        'icon' => '▶️'
    ];
}

// Email campaigns sent
$email_campaigns = $wpdb->get_results( $wpdb->prepare(
    "SELECT 
        sent_at as activity_time,
        campaign_name,
        recipients_count
     FROM {$table_prefix}money_quiz_email_campaigns
     WHERE sent_at >= %s
     ORDER BY sent_at DESC
     LIMIT 20",
    $start_date
) );

foreach ( $email_campaigns as $campaign ) {
    $activities[] = [
        'time' => strtotime( $campaign->activity_time ),
        'type' => 'email_campaign',
        'user' => __( 'System', 'money-quiz' ),
        'details' => sprintf( 
            __( 'sent "%s" campaign to %d recipients', 'money-quiz' ), 
            $campaign->campaign_name,
            $campaign->recipients_count
        ),
        'icon' => '📧'
    ];
}

// Sort all activities by time
usort( $activities, function( $a, $b ) {
    return $b['time'] - $a['time'];
} );

// Limit to 100 most recent
$activities = array_slice( $activities, 0, 100 );

// Group by date
$grouped_activities = [];
foreach ( $activities as $activity ) {
    $date = date( 'Y-m-d', $activity['time'] );
    if ( ! isset( $grouped_activities[ $date ] ) ) {
        $grouped_activities[ $date ] = [];
    }
    $grouped_activities[ $date ][] = $activity;
}
?>

<div class="wrap mq-dashboard-activity">
    
    <!-- Time Filter -->
    <div class="mq-activity-filters">
        <a href="?page=money-quiz-dashboard-activity&time=today" 
           class="<?php echo $time_filter === 'today' ? 'current' : ''; ?>">
            <?php _e( 'Today', 'money-quiz' ); ?>
        </a>
        <a href="?page=money-quiz-dashboard-activity&time=yesterday" 
           class="<?php echo $time_filter === 'yesterday' ? 'current' : ''; ?>">
            <?php _e( 'Yesterday', 'money-quiz' ); ?>
        </a>
        <a href="?page=money-quiz-dashboard-activity&time=7days" 
           class="<?php echo $time_filter === '7days' ? 'current' : ''; ?>">
            <?php _e( 'Last 7 Days', 'money-quiz' ); ?>
        </a>
        <a href="?page=money-quiz-dashboard-activity&time=30days" 
           class="<?php echo $time_filter === '30days' ? 'current' : ''; ?>">
            <?php _e( 'Last 30 Days', 'money-quiz' ); ?>
        </a>
    </div>
    
    <!-- Activity Timeline -->
    <div class="mq-activity-timeline">
        <?php if ( empty( $grouped_activities ) ) : ?>
            <div class="mq-empty-state">
                <span class="mq-empty-icon">📊</span>
                <h3><?php _e( 'No activity found', 'money-quiz' ); ?></h3>
                <p><?php _e( 'There is no activity to display for the selected time period.', 'money-quiz' ); ?></p>
            </div>
        <?php else : ?>
            <?php foreach ( $grouped_activities as $date => $day_activities ) : ?>
                <div class="mq-activity-day">
                    <h3 class="mq-activity-date">
                        <?php 
                        if ( $date === date( 'Y-m-d' ) ) {
                            _e( 'Today', 'money-quiz' );
                        } elseif ( $date === date( 'Y-m-d', strtotime( '-1 day' ) ) ) {
                            _e( 'Yesterday', 'money-quiz' );
                        } else {
                            echo date_i18n( 'F j, Y', strtotime( $date ) );
                        }
                        ?>
                    </h3>
                    
                    <div class="mq-activity-items">
                        <?php foreach ( $day_activities as $activity ) : ?>
                            <div class="mq-activity-item mq-activity-<?php echo esc_attr( $activity['type'] ); ?>">
                                <span class="mq-activity-icon"><?php echo $activity['icon']; ?></span>
                                <div class="mq-activity-content">
                                    <strong><?php echo esc_html( $activity['user'] ); ?></strong>
                                    <?php echo esc_html( $activity['details'] ); ?>
                                    <span class="mq-activity-time">
                                        <?php echo date_i18n( 'g:i A', $activity['time'] ); ?>
                                    </span>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
    
    <!-- Activity Summary -->
    <?php if ( ! empty( $activities ) ) : ?>
        <div class="mq-activity-summary mq-card">
            <h3 class="mq-card-title"><?php _e( 'Activity Summary', 'money-quiz' ); ?></h3>
            <?php
            $type_counts = array_count_values( array_column( $activities, 'type' ) );
            ?>
            <div class="mq-summary-grid">
                <div class="mq-summary-item">
                    <span class="mq-summary-value"><?php echo $type_counts['quiz_completion'] ?? 0; ?></span>
                    <span class="mq-summary-label"><?php _e( 'Quiz Completions', 'money-quiz' ); ?></span>
                </div>
                <div class="mq-summary-item">
                    <span class="mq-summary-value"><?php echo $type_counts['quiz_start'] ?? 0; ?></span>
                    <span class="mq-summary-label"><?php _e( 'Quiz Starts', 'money-quiz' ); ?></span>
                </div>
                <div class="mq-summary-item">
                    <span class="mq-summary-value"><?php echo $type_counts['email_campaign'] ?? 0; ?></span>
                    <span class="mq-summary-label"><?php _e( 'Emails Sent', 'money-quiz' ); ?></span>
                </div>
            </div>
        </div>
    <?php endif; ?>
    
</div>

<style>
.mq-activity-filters {
    margin-bottom: 20px;
    display: flex;
    gap: 15px;
}

.mq-activity-filters a {
    text-decoration: none;
    color: #646970;
    padding: 5px 10px;
    border-radius: 3px;
}

.mq-activity-filters a:hover {
    background: #f0f0f1;
}

.mq-activity-filters a.current {
    background: #0073aa;
    color: #fff;
}

.mq-activity-timeline {
    background: #fff;
    border: 1px solid #c3c4c7;
    padding: 20px;
    border-radius: 4px;
}

.mq-activity-day {
    margin-bottom: 30px;
}

.mq-activity-day:last-child {
    margin-bottom: 0;
}

.mq-activity-date {
    color: #1d2327;
    font-size: 16px;
    margin-bottom: 15px;
    padding-bottom: 10px;
    border-bottom: 1px solid #f0f0f1;
}

.mq-activity-item {
    display: flex;
    align-items: flex-start;
    gap: 15px;
    padding: 10px 0;
    border-bottom: 1px solid #f0f0f1;
}

.mq-activity-item:last-child {
    border-bottom: none;
}

.mq-activity-icon {
    font-size: 20px;
    width: 30px;
    text-align: center;
    flex-shrink: 0;
}

.mq-activity-content {
    flex: 1;
}

.mq-activity-time {
    color: #8c8f94;
    font-size: 12px;
    margin-left: 10px;
}

.mq-empty-state {
    text-align: center;
    padding: 60px 20px;
}

.mq-empty-icon {
    font-size: 48px;
    display: block;
    margin-bottom: 20px;
}

.mq-empty-state h3 {
    color: #1d2327;
    margin-bottom: 10px;
}

.mq-empty-state p {
    color: #646970;
}

.mq-summary-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
    gap: 20px;
    margin-top: 15px;
}

.mq-summary-item {
    text-align: center;
}

.mq-summary-value {
    display: block;
    font-size: 24px;
    font-weight: 600;
    color: #1d2327;
}

.mq-summary-label {
    display: block;
    font-size: 12px;
    color: #646970;
    margin-top: 5px;
}
</style>
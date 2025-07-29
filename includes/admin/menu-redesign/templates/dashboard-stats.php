<?php
/**
 * Dashboard Quick Stats Template
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

// Date ranges
$today = date( 'Y-m-d' );
$yesterday = date( 'Y-m-d', strtotime( '-1 day' ) );
$last_week = date( 'Y-m-d', strtotime( '-7 days' ) );
$last_month = date( 'Y-m-d', strtotime( '-30 days' ) );

// Quiz stats
$total_quizzes = $wpdb->get_var( "SELECT COUNT(*) FROM {$table_prefix}money_quiz_quizzes" );
$active_quizzes = $wpdb->get_var( "SELECT COUNT(*) FROM {$table_prefix}money_quiz_quizzes WHERE status = 'active'" );

// Lead stats
$total_leads = $wpdb->get_var( "SELECT COUNT(*) FROM {$table_prefix}money_quiz_prospects" );
$today_leads = $wpdb->get_var( $wpdb->prepare(
    "SELECT COUNT(*) FROM {$table_prefix}money_quiz_prospects WHERE DATE(created_at) = %s",
    $today
) );
$week_leads = $wpdb->get_var( $wpdb->prepare(
    "SELECT COUNT(*) FROM {$table_prefix}money_quiz_prospects WHERE created_at >= %s",
    $last_week
) );
$month_leads = $wpdb->get_var( $wpdb->prepare(
    "SELECT COUNT(*) FROM {$table_prefix}money_quiz_prospects WHERE created_at >= %s",
    $last_month
) );

// Quiz performance
$total_starts = $wpdb->get_var( "SELECT COUNT(*) FROM {$table_prefix}money_quiz_results" );
$completion_rate = $total_starts > 0 ? round( ( $total_leads / $total_starts ) * 100, 1 ) : 0;

// Top performing quizzes
$top_quizzes = $wpdb->get_results(
    "SELECT 
        q.id,
        q.name,
        COUNT(p.id) as lead_count,
        COUNT(DISTINCT r.id) as start_count
     FROM {$table_prefix}money_quiz_quizzes q
     LEFT JOIN {$table_prefix}money_quiz_prospects p ON q.id = p.quiz_id
     LEFT JOIN {$table_prefix}money_quiz_results r ON q.id = r.quiz_id
     WHERE q.status = 'active'
     GROUP BY q.id
     ORDER BY lead_count DESC
     LIMIT 5"
);

// Lead sources
$lead_sources = $wpdb->get_results(
    "SELECT 
        source,
        COUNT(*) as count
     FROM {$table_prefix}money_quiz_prospects
     WHERE source IS NOT NULL
     GROUP BY source
     ORDER BY count DESC
     LIMIT 5"
);

// Daily leads for chart (last 30 days)
$daily_leads = $wpdb->get_results(
    "SELECT 
        DATE(created_at) as date,
        COUNT(*) as count
     FROM {$table_prefix}money_quiz_prospects
     WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
     GROUP BY DATE(created_at)
     ORDER BY date ASC"
);

// Conversion funnel
$funnel_data = [
    ['stage' => 'Page Views', 'count' => $total_starts * 3], // Estimate
    ['stage' => 'Quiz Starts', 'count' => $total_starts],
    ['stage' => 'Quiz Completions', 'count' => $total_leads],
    ['stage' => 'Email Opt-ins', 'count' => $wpdb->get_var( "SELECT COUNT(*) FROM {$table_prefix}money_quiz_prospects WHERE email_consent = 1" )],
];
?>

<div class="wrap mq-dashboard-stats">
    
    <!-- Summary Cards -->
    <div class="mq-stats-grid">
        <div class="mq-stat-card">
            <h3><?php _e( 'Total Leads', 'money-quiz' ); ?></h3>
            <div class="mq-stat-number"><?php echo number_format( $total_leads ); ?></div>
            <div class="mq-stat-comparison">
                <span class="mq-stat-trend <?php echo $week_leads > 0 ? 'positive' : ''; ?>">
                    <?php echo $week_leads > 0 ? '↑' : '−'; ?> <?php echo number_format( $week_leads ); ?>
                </span>
                <?php _e( 'this week', 'money-quiz' ); ?>
            </div>
        </div>
        
        <div class="mq-stat-card">
            <h3><?php _e( 'Today\'s Leads', 'money-quiz' ); ?></h3>
            <div class="mq-stat-number"><?php echo number_format( $today_leads ); ?></div>
            <div class="mq-stat-comparison">
                <?php 
                $yesterday_leads = $wpdb->get_var( $wpdb->prepare(
                    "SELECT COUNT(*) FROM {$table_prefix}money_quiz_prospects WHERE DATE(created_at) = %s",
                    $yesterday
                ) );
                $change = $today_leads - $yesterday_leads;
                ?>
                <span class="mq-stat-trend <?php echo $change >= 0 ? 'positive' : 'negative'; ?>">
                    <?php echo $change >= 0 ? '↑' : '↓'; ?> <?php echo abs( $change ); ?>
                </span>
                <?php _e( 'vs yesterday', 'money-quiz' ); ?>
            </div>
        </div>
        
        <div class="mq-stat-card">
            <h3><?php _e( 'Completion Rate', 'money-quiz' ); ?></h3>
            <div class="mq-stat-number"><?php echo $completion_rate; ?>%</div>
            <div class="mq-stat-comparison">
                <?php echo number_format( $total_leads ); ?> / <?php echo number_format( $total_starts ); ?>
            </div>
        </div>
        
        <div class="mq-stat-card">
            <h3><?php _e( 'Active Quizzes', 'money-quiz' ); ?></h3>
            <div class="mq-stat-number"><?php echo number_format( $active_quizzes ); ?></div>
            <div class="mq-stat-comparison">
                <?php echo sprintf( __( 'of %d total', 'money-quiz' ), $total_quizzes ); ?>
            </div>
        </div>
    </div>
    
    <!-- Charts Row -->
    <div class="mq-charts-row">
        
        <!-- Lead Trend Chart -->
        <div class="mq-card mq-chart-card">
            <h3 class="mq-card-title"><?php _e( '📈 Lead Trend (30 Days)', 'money-quiz' ); ?></h3>
            <canvas id="leadTrendChart" height="300"></canvas>
        </div>
        
        <!-- Conversion Funnel -->
        <div class="mq-card mq-chart-card">
            <h3 class="mq-card-title"><?php _e( '🔄 Conversion Funnel', 'money-quiz' ); ?></h3>
            <div class="mq-funnel">
                <?php foreach ( $funnel_data as $index => $stage ) : ?>
                    <div class="mq-funnel-stage" style="width: <?php echo 100 - ($index * 20); ?>%;">
                        <div class="mq-funnel-label"><?php echo esc_html( $stage['stage'] ); ?></div>
                        <div class="mq-funnel-count"><?php echo number_format( $stage['count'] ); ?></div>
                        <?php if ( $index > 0 ) : ?>
                            <div class="mq-funnel-rate">
                                <?php 
                                $rate = $funnel_data[$index-1]['count'] > 0 
                                    ? round( ( $stage['count'] / $funnel_data[$index-1]['count'] ) * 100, 1 )
                                    : 0;
                                echo $rate . '%';
                                ?>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
        
    </div>
    
    <!-- Top Performance Tables -->
    <div class="mq-performance-row">
        
        <!-- Top Quizzes -->
        <div class="mq-card">
            <h3 class="mq-card-title"><?php _e( '🏆 Top Performing Quizzes', 'money-quiz' ); ?></h3>
            <?php if ( ! empty( $top_quizzes ) ) : ?>
                <table class="mq-simple-table">
                    <thead>
                        <tr>
                            <th><?php _e( 'Quiz Name', 'money-quiz' ); ?></th>
                            <th><?php _e( 'Leads', 'money-quiz' ); ?></th>
                            <th><?php _e( 'Conversion', 'money-quiz' ); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ( $top_quizzes as $quiz ) : ?>
                            <tr>
                                <td>
                                    <a href="<?php echo admin_url( 'admin.php?page=money-quiz-quizzes-edit&id=' . $quiz->id ); ?>">
                                        <?php echo esc_html( $quiz->name ); ?>
                                    </a>
                                </td>
                                <td><?php echo number_format( $quiz->lead_count ); ?></td>
                                <td>
                                    <?php 
                                    $conversion = $quiz->start_count > 0 
                                        ? round( ( $quiz->lead_count / $quiz->start_count ) * 100, 1 )
                                        : 0;
                                    echo $conversion . '%';
                                    ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php else : ?>
                <p><?php _e( 'No quiz data available yet.', 'money-quiz' ); ?></p>
            <?php endif; ?>
        </div>
        
        <!-- Lead Sources -->
        <div class="mq-card">
            <h3 class="mq-card-title"><?php _e( '🌐 Lead Sources', 'money-quiz' ); ?></h3>
            <?php if ( ! empty( $lead_sources ) ) : ?>
                <table class="mq-simple-table">
                    <thead>
                        <tr>
                            <th><?php _e( 'Source', 'money-quiz' ); ?></th>
                            <th><?php _e( 'Leads', 'money-quiz' ); ?></th>
                            <th><?php _e( 'Percentage', 'money-quiz' ); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ( $lead_sources as $source ) : ?>
                            <tr>
                                <td><?php echo esc_html( $source->source ?: __( 'Direct', 'money-quiz' ) ); ?></td>
                                <td><?php echo number_format( $source->count ); ?></td>
                                <td>
                                    <?php 
                                    $percentage = $total_leads > 0 
                                        ? round( ( $source->count / $total_leads ) * 100, 1 )
                                        : 0;
                                    echo $percentage . '%';
                                    ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php else : ?>
                <p><?php _e( 'No source data available yet.', 'money-quiz' ); ?></p>
            <?php endif; ?>
        </div>
        
    </div>
    
</div>

<script>
// Lead Trend Chart - Simple implementation without external library
document.addEventListener('DOMContentLoaded', function() {
    var canvas = document.getElementById('leadTrendChart');
    if (!canvas) return;
    
    var leadData = <?php echo json_encode( array_map( function($item) {
        return [
            'date' => $item->date,
            'count' => intval($item->count)
        ];
    }, $daily_leads ) ); ?>;
    
    // Simple chart rendering (placeholder - would need proper charting library)
    var ctx = canvas.getContext('2d');
    ctx.fillStyle = '#f0f0f1';
    ctx.fillRect(0, 0, canvas.width, canvas.height);
    
    ctx.font = '14px sans-serif';
    ctx.fillStyle = '#646970';
    ctx.textAlign = 'center';
    ctx.fillText('Lead trend visualization', canvas.width / 2, canvas.height / 2);
    ctx.font = '12px sans-serif';
    ctx.fillText('(Chart.js integration needed)', canvas.width / 2, canvas.height / 2 + 20);
});
</script>

<style>
.mq-stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
    gap: 20px;
    margin-bottom: 30px;
}

.mq-stat-card {
    background: #fff;
    border: 1px solid #c3c4c7;
    border-radius: 4px;
    padding: 20px;
}

.mq-stat-card h3 {
    margin: 0 0 10px 0;
    color: #646970;
    font-size: 14px;
    font-weight: 400;
}

.mq-stat-number {
    font-size: 32px;
    font-weight: 600;
    color: #1d2327;
    margin: 10px 0;
}

.mq-stat-comparison {
    font-size: 13px;
    color: #646970;
}

.mq-stat-trend {
    font-weight: 600;
    margin-right: 5px;
}

.mq-stat-trend.positive {
    color: #00a32a;
}

.mq-stat-trend.negative {
    color: #dc3232;
}

.mq-charts-row {
    display: grid;
    grid-template-columns: 2fr 1fr;
    gap: 20px;
    margin-bottom: 30px;
}

.mq-chart-card {
    min-height: 400px;
}

.mq-funnel {
    display: flex;
    flex-direction: column;
    align-items: center;
    padding: 20px;
}

.mq-funnel-stage {
    background: #f0f0f1;
    padding: 15px;
    margin: 5px 0;
    text-align: center;
    position: relative;
    border-radius: 4px;
}

.mq-funnel-label {
    font-weight: 600;
    color: #1d2327;
}

.mq-funnel-count {
    font-size: 24px;
    color: #0073aa;
    margin: 5px 0;
}

.mq-funnel-rate {
    font-size: 12px;
    color: #646970;
    position: absolute;
    right: -40px;
    top: 50%;
    transform: translateY(-50%);
}

.mq-performance-row {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 20px;
}

.mq-simple-table {
    width: 100%;
    border-collapse: collapse;
}

.mq-simple-table th {
    text-align: left;
    padding: 10px;
    border-bottom: 1px solid #c3c4c7;
    font-weight: 600;
    color: #1d2327;
}

.mq-simple-table td {
    padding: 10px;
    border-bottom: 1px solid #f0f0f1;
}

.mq-simple-table tr:last-child td {
    border-bottom: none;
}

@media (max-width: 1200px) {
    .mq-charts-row,
    .mq-performance-row {
        grid-template-columns: 1fr;
    }
}
</style>
<?php
/**
 * Results & Analytics Template
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

// Get filter parameters
$quiz_id = isset( $_GET['quiz_id'] ) ? intval( $_GET['quiz_id'] ) : 0;
$date_range = isset( $_GET['date_range'] ) ? sanitize_text_field( $_GET['date_range'] ) : '30days';
$archetype_filter = isset( $_GET['archetype'] ) ? sanitize_text_field( $_GET['archetype'] ) : '';

// Date range calculation
$date_sql = '';
switch ( $date_range ) {
    case '7days':
        $date_sql = "AND p.date >= DATE_SUB(NOW(), INTERVAL 7 DAY)";
        break;
    case '30days':
        $date_sql = "AND p.date >= DATE_SUB(NOW(), INTERVAL 30 DAY)";
        break;
    case '90days':
        $date_sql = "AND p.date >= DATE_SUB(NOW(), INTERVAL 90 DAY)";
        break;
    case 'all':
    default:
        $date_sql = '';
}

// Get all quizzes for filter
$all_quizzes = $wpdb->get_results( "SELECT id, quiz_name FROM {$table_prefix}mq_master ORDER BY quiz_name" );

// Build query
$where_clauses = [];
if ( $quiz_id > 0 ) {
    $where_clauses[] = $wpdb->prepare( "p.quiz_id = %d", $quiz_id );
}
if ( ! empty( $archetype_filter ) ) {
    $where_clauses[] = $wpdb->prepare( "a.name = %s", $archetype_filter );
}

$where_sql = ! empty( $where_clauses ) ? 'WHERE ' . implode( ' AND ', $where_clauses ) : '';

// Get results with fallback to legacy tables
$results_query = "
    SELECT 
        p.id,
        p.name as prospect_name,
        p.email,
        p.date as completion_date,
        p.quiz_id,
        m.quiz_name,
        a.name as archetype_name,
        p.score,
        p.source,
        p.email_consent
    FROM {$table_prefix}mq_prospects p
    LEFT JOIN {$table_prefix}mq_master m ON p.quiz_id = m.id
    LEFT JOIN {$table_prefix}mq_archetypes a ON p.archetype_id = a.id
    $where_sql
    $date_sql
    ORDER BY p.date DESC
    LIMIT 100
";

$results = $wpdb->get_results( $results_query );

// If no results from new tables, try legacy
if ( empty( $results ) ) {
    $legacy_query = "
        SELECT 
            p.id,
            CONCAT(p.first_name, ' ', p.last_name) as prospect_name,
            p.email,
            p.created_at as completion_date,
            p.quiz_id,
            q.name as quiz_name,
            a.name as archetype_name,
            r.total_score as score,
            p.source,
            p.email_consent
        FROM {$table_prefix}money_quiz_prospects p
        LEFT JOIN {$table_prefix}money_quiz_quizzes q ON p.quiz_id = q.id
        LEFT JOIN {$table_prefix}money_quiz_archetypes a ON p.archetype_id = a.id
        LEFT JOIN {$table_prefix}money_quiz_results r ON p.result_id = r.id
        $where_sql
        ORDER BY p.created_at DESC
        LIMIT 100
    ";
    
    $results = $wpdb->get_results( $legacy_query );
}

// Get summary statistics
$total_results = $wpdb->get_var( "SELECT COUNT(*) FROM {$table_prefix}mq_prospects" ) ?: 
                $wpdb->get_var( "SELECT COUNT(*) FROM {$table_prefix}money_quiz_prospects" );

$quiz_completions = $wpdb->get_results( "
    SELECT 
        m.quiz_name,
        COUNT(p.id) as completion_count,
        AVG(p.score) as avg_score
    FROM {$table_prefix}mq_prospects p
    LEFT JOIN {$table_prefix}mq_master m ON p.quiz_id = m.id
    GROUP BY p.quiz_id
    ORDER BY completion_count DESC
" );

// Get archetype distribution
$archetype_distribution = $wpdb->get_results( "
    SELECT 
        a.name as archetype_name,
        COUNT(p.id) as count,
        m.quiz_name
    FROM {$table_prefix}mq_prospects p
    LEFT JOIN {$table_prefix}mq_archetypes a ON p.archetype_id = a.id
    LEFT JOIN {$table_prefix}mq_master m ON p.quiz_id = m.id
    WHERE a.name IS NOT NULL
    GROUP BY a.id
    ORDER BY count DESC
" );

// Export functionality
if ( isset( $_GET['export'] ) && $_GET['export'] === 'csv' ) {
    header( 'Content-Type: text/csv' );
    header( 'Content-Disposition: attachment; filename="quiz-results-' . date( 'Y-m-d' ) . '.csv"' );
    
    $output = fopen( 'php://output', 'w' );
    fputcsv( $output, ['Name', 'Email', 'Quiz', 'Archetype', 'Score', 'Date', 'Source', 'Email Consent'] );
    
    foreach ( $results as $result ) {
        fputcsv( $output, [
            $result->prospect_name,
            $result->email,
            $result->quiz_name,
            $result->archetype_name,
            $result->score,
            $result->completion_date,
            $result->source,
            $result->email_consent ? 'Yes' : 'No'
        ] );
    }
    
    fclose( $output );
    exit;
}
?>

<div class="wrap mq-results-analytics">
    
    <!-- Filters -->
    <div class="mq-results-filters">
        <form method="get" class="filters-form">
            <input type="hidden" name="page" value="money-quiz-audience-results" />
            
            <select name="quiz_id" onchange="this.form.submit()">
                <option value="0"><?php _e( 'All Quizzes', 'money-quiz' ); ?></option>
                <?php foreach ( $all_quizzes as $quiz ) : ?>
                    <option value="<?php echo $quiz->id; ?>" <?php selected( $quiz_id, $quiz->id ); ?>>
                        <?php echo esc_html( $quiz->quiz_name ); ?>
                    </option>
                <?php endforeach; ?>
            </select>
            
            <select name="date_range" onchange="this.form.submit()">
                <option value="7days" <?php selected( $date_range, '7days' ); ?>><?php _e( 'Last 7 Days', 'money-quiz' ); ?></option>
                <option value="30days" <?php selected( $date_range, '30days' ); ?>><?php _e( 'Last 30 Days', 'money-quiz' ); ?></option>
                <option value="90days" <?php selected( $date_range, '90days' ); ?>><?php _e( 'Last 90 Days', 'money-quiz' ); ?></option>
                <option value="all" <?php selected( $date_range, 'all' ); ?>><?php _e( 'All Time', 'money-quiz' ); ?></option>
            </select>
            
            <a href="<?php echo add_query_arg( 'export', 'csv' ); ?>" class="button">
                <?php _e( 'Export CSV', 'money-quiz' ); ?>
            </a>
        </form>
    </div>
    
    <!-- Summary Cards -->
    <div class="mq-summary-grid">
        <div class="mq-summary-card">
            <h3><?php _e( 'Total Results', 'money-quiz' ); ?></h3>
            <div class="mq-summary-value"><?php echo number_format( $total_results ); ?></div>
        </div>
        
        <div class="mq-summary-card">
            <h3><?php _e( 'Active Quizzes', 'money-quiz' ); ?></h3>
            <div class="mq-summary-value"><?php echo count( $all_quizzes ); ?></div>
        </div>
        
        <div class="mq-summary-card">
            <h3><?php _e( 'Avg. Completion Rate', 'money-quiz' ); ?></h3>
            <div class="mq-summary-value">
                <?php 
                $total_starts = $wpdb->get_var( "SELECT COUNT(*) FROM {$table_prefix}mq_taken" );
                $completion_rate = $total_starts > 0 ? round( ( $total_results / $total_starts ) * 100, 1 ) : 0;
                echo $completion_rate . '%';
                ?>
            </div>
        </div>
        
        <div class="mq-summary-card">
            <h3><?php _e( 'Email Consent Rate', 'money-quiz' ); ?></h3>
            <div class="mq-summary-value">
                <?php 
                $consents = $wpdb->get_var( "SELECT COUNT(*) FROM {$table_prefix}mq_prospects WHERE email_consent = 1" );
                $consent_rate = $total_results > 0 ? round( ( $consents / $total_results ) * 100, 1 ) : 0;
                echo $consent_rate . '%';
                ?>
            </div>
        </div>
    </div>
    
    <!-- Quiz Performance -->
    <?php if ( ! empty( $quiz_completions ) ) : ?>
        <div class="mq-card">
            <h2><?php _e( 'Quiz Performance', 'money-quiz' ); ?></h2>
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th><?php _e( 'Quiz Name', 'money-quiz' ); ?></th>
                        <th><?php _e( 'Completions', 'money-quiz' ); ?></th>
                        <th><?php _e( 'Average Score', 'money-quiz' ); ?></th>
                        <th><?php _e( 'Actions', 'money-quiz' ); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ( $quiz_completions as $quiz ) : ?>
                        <tr>
                            <td><?php echo esc_html( $quiz->quiz_name ); ?></td>
                            <td><?php echo number_format( $quiz->completion_count ); ?></td>
                            <td><?php echo number_format( $quiz->avg_score, 1 ); ?></td>
                            <td>
                                <a href="?page=money-quiz-audience-results&quiz_id=<?php echo $quiz->quiz_id; ?>" class="button button-small">
                                    <?php _e( 'View Results', 'money-quiz' ); ?>
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
    
    <!-- Archetype Distribution -->
    <?php if ( ! empty( $archetype_distribution ) ) : ?>
        <div class="mq-card">
            <h2><?php _e( 'Archetype Distribution', 'money-quiz' ); ?></h2>
            <div class="mq-archetype-chart">
                <?php 
                $total_with_archetype = array_sum( array_column( $archetype_distribution, 'count' ) );
                foreach ( $archetype_distribution as $archetype ) : 
                    $percentage = $total_with_archetype > 0 ? round( ( $archetype->count / $total_with_archetype ) * 100, 1 ) : 0;
                ?>
                    <div class="mq-archetype-bar">
                        <div class="mq-archetype-info">
                            <span class="mq-archetype-name"><?php echo esc_html( $archetype->archetype_name ); ?></span>
                            <span class="mq-archetype-quiz"><?php echo esc_html( $archetype->quiz_name ); ?></span>
                        </div>
                        <div class="mq-archetype-progress">
                            <div class="mq-progress-bar" style="width: <?php echo $percentage; ?>%"></div>
                            <span class="mq-progress-text"><?php echo $archetype->count; ?> (<?php echo $percentage; ?>%)</span>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    <?php endif; ?>
    
    <!-- Recent Results -->
    <div class="mq-card">
        <h2><?php _e( 'Recent Results', 'money-quiz' ); ?></h2>
        
        <?php if ( empty( $results ) ) : ?>
            <p><?php _e( 'No results found for the selected criteria.', 'money-quiz' ); ?></p>
        <?php else : ?>
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th><?php _e( 'Name', 'money-quiz' ); ?></th>
                        <th><?php _e( 'Email', 'money-quiz' ); ?></th>
                        <th><?php _e( 'Quiz', 'money-quiz' ); ?></th>
                        <th><?php _e( 'Archetype', 'money-quiz' ); ?></th>
                        <th><?php _e( 'Score', 'money-quiz' ); ?></th>
                        <th><?php _e( 'Date', 'money-quiz' ); ?></th>
                        <th><?php _e( 'Actions', 'money-quiz' ); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ( $results as $result ) : ?>
                        <tr>
                            <td><?php echo esc_html( $result->prospect_name ); ?></td>
                            <td><?php echo esc_html( $result->email ); ?></td>
                            <td><?php echo esc_html( $result->quiz_name ); ?></td>
                            <td><?php echo esc_html( $result->archetype_name ?: '-' ); ?></td>
                            <td><?php echo esc_html( $result->score ?: '-' ); ?></td>
                            <td><?php echo date_i18n( get_option( 'date_format' ), strtotime( $result->completion_date ) ); ?></td>
                            <td>
                                <a href="?page=money-quiz-audience-prospects&action=view&id=<?php echo $result->id; ?>" class="button button-small">
                                    <?php _e( 'View', 'money-quiz' ); ?>
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>
    
</div>

<style>
.mq-results-filters {
    margin-bottom: 20px;
}

.filters-form {
    display: flex;
    gap: 10px;
    align-items: center;
}

.mq-summary-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 20px;
    margin-bottom: 30px;
}

.mq-summary-card {
    background: #fff;
    border: 1px solid #c3c4c7;
    border-radius: 4px;
    padding: 20px;
    text-align: center;
}

.mq-summary-card h3 {
    margin: 0 0 10px 0;
    font-size: 14px;
    color: #646970;
}

.mq-summary-value {
    font-size: 32px;
    font-weight: 600;
    color: #23282d;
}

.mq-archetype-chart {
    margin-top: 20px;
}

.mq-archetype-bar {
    margin-bottom: 15px;
}

.mq-archetype-info {
    display: flex;
    justify-content: space-between;
    margin-bottom: 5px;
}

.mq-archetype-name {
    font-weight: 600;
}

.mq-archetype-quiz {
    font-size: 12px;
    color: #666;
}

.mq-archetype-progress {
    position: relative;
    background: #e0e0e0;
    height: 30px;
    border-radius: 4px;
    overflow: hidden;
}

.mq-progress-bar {
    background: #0073aa;
    height: 100%;
    transition: width 0.3s ease;
}

.mq-progress-text {
    position: absolute;
    top: 50%;
    left: 10px;
    transform: translateY(-50%);
    font-size: 12px;
    font-weight: 600;
    color: #fff;
}
</style>
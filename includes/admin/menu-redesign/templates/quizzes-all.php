<?php
/**
 * All Quizzes Template
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

// Handle bulk actions
if ( isset( $_POST['action'] ) && $_POST['action'] !== '-1' ) {
    check_admin_referer( 'bulk-quizzes' );
    
    $action = sanitize_text_field( $_POST['action'] );
    $quiz_ids = isset( $_POST['quiz'] ) ? array_map( 'intval', $_POST['quiz'] ) : [];
    
    if ( ! empty( $quiz_ids ) ) {
        switch ( $action ) {
            case 'delete':
                foreach ( $quiz_ids as $quiz_id ) {
                    // Delete quiz and related data
                    $wpdb->delete( "{$table_prefix}mq_master", ['id' => $quiz_id] );
                    $wpdb->delete( "{$table_prefix}mq_questions", ['quiz_id' => $quiz_id] );
                    $wpdb->delete( "{$table_prefix}mq_archetypes", ['quiz_id' => $quiz_id] );
                }
                echo '<div class="notice notice-success"><p>' . sprintf( __( '%d quiz(es) deleted.', 'money-quiz' ), count( $quiz_ids ) ) . '</p></div>';
                break;
                
            case 'activate':
                $wpdb->query( $wpdb->prepare(
                    "UPDATE {$table_prefix}mq_master SET status = 1 WHERE id IN (" . implode(',', $quiz_ids) . ")"
                ) );
                echo '<div class="notice notice-success"><p>' . sprintf( __( '%d quiz(es) activated.', 'money-quiz' ), count( $quiz_ids ) ) . '</p></div>';
                break;
                
            case 'deactivate':
                $wpdb->query( $wpdb->prepare(
                    "UPDATE {$table_prefix}mq_master SET status = 0 WHERE id IN (" . implode(',', $quiz_ids) . ")"
                ) );
                echo '<div class="notice notice-success"><p>' . sprintf( __( '%d quiz(es) deactivated.', 'money-quiz' ), count( $quiz_ids ) ) . '</p></div>';
                break;
        }
    }
}

// Get filter parameters
$status_filter = isset( $_GET['status'] ) ? sanitize_text_field( $_GET['status'] ) : 'all';
$search = isset( $_GET['s'] ) ? sanitize_text_field( $_GET['s'] ) : '';

// Build query
$where_clauses = [];
if ( $status_filter === 'active' ) {
    $where_clauses[] = "status = 1";
} elseif ( $status_filter === 'inactive' ) {
    $where_clauses[] = "status = 0";
}

if ( ! empty( $search ) ) {
    $where_clauses[] = $wpdb->prepare( "quiz_name LIKE %s", '%' . $wpdb->esc_like( $search ) . '%' );
}

$where_sql = ! empty( $where_clauses ) ? 'WHERE ' . implode( ' AND ', $where_clauses ) : '';

// Get quizzes
$quizzes = $wpdb->get_results( "
    SELECT 
        q.*,
        (SELECT COUNT(*) FROM {$table_prefix}mq_questions WHERE quiz_id = q.id) as question_count,
        (SELECT COUNT(*) FROM {$table_prefix}mq_prospects WHERE quiz_id = q.id) as response_count,
        (SELECT COUNT(*) FROM {$table_prefix}mq_taken WHERE quiz_id = q.id) as take_count
    FROM {$table_prefix}mq_master q
    $where_sql
    ORDER BY q.id DESC
" );

// Get counts for filters
$total_count = $wpdb->get_var( "SELECT COUNT(*) FROM {$table_prefix}mq_master" );
$active_count = $wpdb->get_var( "SELECT COUNT(*) FROM {$table_prefix}mq_master WHERE status = 1" );
$inactive_count = $wpdb->get_var( "SELECT COUNT(*) FROM {$table_prefix}mq_master WHERE status = 0" );
?>

<div class="wrap mq-quizzes-list">
    
    <!-- Filters -->
    <div class="mq-list-filters">
        <ul class="subsubsub">
            <li>
                <a href="?page=money-quiz-quizzes-all" class="<?php echo $status_filter === 'all' ? 'current' : ''; ?>">
                    <?php _e( 'All', 'money-quiz' ); ?> <span class="count">(<?php echo $total_count; ?>)</span>
                </a> |
            </li>
            <li>
                <a href="?page=money-quiz-quizzes-all&status=active" class="<?php echo $status_filter === 'active' ? 'current' : ''; ?>">
                    <?php _e( 'Active', 'money-quiz' ); ?> <span class="count">(<?php echo $active_count; ?>)</span>
                </a> |
            </li>
            <li>
                <a href="?page=money-quiz-quizzes-all&status=inactive" class="<?php echo $status_filter === 'inactive' ? 'current' : ''; ?>">
                    <?php _e( 'Inactive', 'money-quiz' ); ?> <span class="count">(<?php echo $inactive_count; ?>)</span>
                </a>
            </li>
        </ul>
        
        <form method="get" class="search-box">
            <input type="hidden" name="page" value="money-quiz-quizzes-all" />
            <label class="screen-reader-text" for="quiz-search"><?php _e( 'Search Quizzes', 'money-quiz' ); ?></label>
            <input type="search" id="quiz-search" name="s" value="<?php echo esc_attr( $search ); ?>" placeholder="<?php _e( 'Search quizzes...', 'money-quiz' ); ?>" />
            <input type="submit" class="button" value="<?php _e( 'Search', 'money-quiz' ); ?>" />
        </form>
    </div>
    
    <form method="post">
        <?php wp_nonce_field( 'bulk-quizzes' ); ?>
        
        <!-- Bulk Actions -->
        <div class="tablenav top">
            <div class="alignleft actions bulkactions">
                <label for="bulk-action-selector-top" class="screen-reader-text"><?php _e( 'Select bulk action', 'money-quiz' ); ?></label>
                <select name="action" id="bulk-action-selector-top">
                    <option value="-1"><?php _e( 'Bulk Actions', 'money-quiz' ); ?></option>
                    <option value="activate"><?php _e( 'Activate', 'money-quiz' ); ?></option>
                    <option value="deactivate"><?php _e( 'Deactivate', 'money-quiz' ); ?></option>
                    <option value="delete"><?php _e( 'Delete', 'money-quiz' ); ?></option>
                </select>
                <input type="submit" class="button action" value="<?php _e( 'Apply', 'money-quiz' ); ?>" />
            </div>
        </div>
        
        <!-- Quiz Table -->
        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <td class="manage-column column-cb check-column">
                        <label class="screen-reader-text" for="cb-select-all-1"><?php _e( 'Select All', 'money-quiz' ); ?></label>
                        <input id="cb-select-all-1" type="checkbox" />
                    </td>
                    <th class="manage-column column-title column-primary"><?php _e( 'Quiz Title', 'money-quiz' ); ?></th>
                    <th class="manage-column"><?php _e( 'Questions', 'money-quiz' ); ?></th>
                    <th class="manage-column"><?php _e( 'Responses', 'money-quiz' ); ?></th>
                    <th class="manage-column"><?php _e( 'Conversion', 'money-quiz' ); ?></th>
                    <th class="manage-column"><?php _e( 'Status', 'money-quiz' ); ?></th>
                    <th class="manage-column"><?php _e( 'Shortcode', 'money-quiz' ); ?></th>
                    <th class="manage-column"><?php _e( 'Actions', 'money-quiz' ); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php if ( empty( $quizzes ) ) : ?>
                    <tr>
                        <td colspan="8" class="no-items">
                            <?php _e( 'No quizzes found.', 'money-quiz' ); ?>
                            <a href="<?php echo admin_url( 'admin.php?page=money-quiz-quizzes-add-new' ); ?>">
                                <?php _e( 'Create your first quiz', 'money-quiz' ); ?>
                            </a>
                        </td>
                    </tr>
                <?php else : ?>
                    <?php foreach ( $quizzes as $quiz ) : ?>
                        <tr>
                            <th scope="row" class="check-column">
                                <input type="checkbox" name="quiz[]" value="<?php echo $quiz->id; ?>" />
                            </th>
                            <td class="title column-title column-primary">
                                <strong>
                                    <a href="<?php echo admin_url( 'admin.php?page=money-quiz-quizzes-edit&quiz_id=' . $quiz->id ); ?>">
                                        <?php echo esc_html( $quiz->quiz_name ); ?>
                                    </a>
                                </strong>
                                <div class="row-actions">
                                    <span class="edit">
                                        <a href="<?php echo admin_url( 'admin.php?page=money-quiz-quizzes-edit&quiz_id=' . $quiz->id ); ?>">
                                            <?php _e( 'Edit', 'money-quiz' ); ?>
                                        </a> |
                                    </span>
                                    <span class="preview">
                                        <a href="<?php echo home_url( '?preview_quiz=' . $quiz->id ); ?>" target="_blank">
                                            <?php _e( 'Preview', 'money-quiz' ); ?>
                                        </a> |
                                    </span>
                                    <span class="duplicate">
                                        <a href="<?php echo wp_nonce_url( admin_url( 'admin.php?page=money-quiz-quizzes-all&action=duplicate&quiz_id=' . $quiz->id ), 'duplicate-quiz-' . $quiz->id ); ?>">
                                            <?php _e( 'Duplicate', 'money-quiz' ); ?>
                                        </a> |
                                    </span>
                                    <span class="trash">
                                        <a href="<?php echo wp_nonce_url( admin_url( 'admin.php?page=money-quiz-quizzes-all&action=delete&quiz_id=' . $quiz->id ), 'delete-quiz-' . $quiz->id ); ?>" class="delete-quiz" onclick="return confirm('<?php _e( 'Are you sure you want to delete this quiz?', 'money-quiz' ); ?>');">
                                            <?php _e( 'Delete', 'money-quiz' ); ?>
                                        </a>
                                    </span>
                                </div>
                                <button type="button" class="toggle-row">
                                    <span class="screen-reader-text"><?php _e( 'Show more details', 'money-quiz' ); ?></span>
                                </button>
                            </td>
                            <td><?php echo number_format( $quiz->question_count ); ?></td>
                            <td><?php echo number_format( $quiz->response_count ); ?></td>
                            <td>
                                <?php 
                                $conversion = $quiz->take_count > 0 
                                    ? round( ( $quiz->response_count / $quiz->take_count ) * 100, 1 ) 
                                    : 0;
                                echo $conversion . '%';
                                ?>
                            </td>
                            <td>
                                <?php if ( $quiz->status == 1 ) : ?>
                                    <span class="mq-status-active"><?php _e( 'Active', 'money-quiz' ); ?></span>
                                <?php else : ?>
                                    <span class="mq-status-inactive"><?php _e( 'Inactive', 'money-quiz' ); ?></span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <code>[money_quiz id="<?php echo $quiz->id; ?>"]</code>
                                <button type="button" class="button-small copy-shortcode" data-shortcode='[money_quiz id="<?php echo $quiz->id; ?>"]'>
                                    <?php _e( 'Copy', 'money-quiz' ); ?>
                                </button>
                            </td>
                            <td>
                                <div class="mq-quiz-actions">
                                    <a href="<?php echo admin_url( 'admin.php?page=money-quiz-quizzes-questions&quiz_id=' . $quiz->id ); ?>" class="button button-small">
                                        <?php _e( 'Questions', 'money-quiz' ); ?>
                                    </a>
                                    <a href="<?php echo admin_url( 'admin.php?page=money-quiz-audience-results&quiz_id=' . $quiz->id ); ?>" class="button button-small">
                                        <?php _e( 'Results', 'money-quiz' ); ?>
                                    </a>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </form>
    
</div>

<script>
jQuery(document).ready(function($) {
    // Copy shortcode functionality
    $('.copy-shortcode').on('click', function(e) {
        e.preventDefault();
        var shortcode = $(this).data('shortcode');
        
        // Create temporary input
        var $temp = $('<input>');
        $('body').append($temp);
        $temp.val(shortcode).select();
        document.execCommand('copy');
        $temp.remove();
        
        // Show feedback
        $(this).text('<?php _e( 'Copied!', 'money-quiz' ); ?>');
        setTimeout(() => {
            $(this).text('<?php _e( 'Copy', 'money-quiz' ); ?>');
        }, 2000);
    });
});
</script>

<style>
.mq-quizzes-list .search-box {
    float: right;
    margin-top: 0;
}

.mq-status-active {
    color: #00a32a;
    font-weight: 600;
}

.mq-status-inactive {
    color: #dc3232;
}

.mq-quiz-actions {
    display: flex;
    gap: 5px;
}

.copy-shortcode {
    margin-left: 5px;
    cursor: pointer;
}

.no-items {
    text-align: center;
    padding: 40px !important;
}

.no-items a {
    margin-left: 5px;
}
</style>
<?php
/**
 * Prospects/Leads Management Template
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

// Handle single prospect view
if ( isset( $_GET['action'] ) && $_GET['action'] === 'view' && isset( $_GET['id'] ) ) {
    $prospect_id = intval( $_GET['id'] );
    include plugin_dir_path( __FILE__ ) . 'audience-prospect-single.php';
    return;
}

// Handle bulk actions
if ( isset( $_POST['action'] ) && $_POST['action'] !== '-1' ) {
    check_admin_referer( 'bulk-prospects' );
    
    $action = sanitize_text_field( $_POST['action'] );
    $prospect_ids = isset( $_POST['prospect'] ) ? array_map( 'intval', $_POST['prospect'] ) : [];
    
    if ( ! empty( $prospect_ids ) ) {
        switch ( $action ) {
            case 'delete':
                // Try new table first
                $deleted = $wpdb->query( 
                    "DELETE FROM {$table_prefix}mq_prospects WHERE id IN (" . implode(',', $prospect_ids) . ")"
                );
                
                // Fallback to legacy table
                if ( ! $deleted ) {
                    $deleted = $wpdb->query( 
                        "DELETE FROM {$table_prefix}money_quiz_prospects WHERE id IN (" . implode(',', $prospect_ids) . ")"
                    );
                }
                
                echo '<div class="notice notice-success"><p>' . sprintf( __( '%d prospect(s) deleted.', 'money-quiz' ), count( $prospect_ids ) ) . '</p></div>';
                break;
                
            case 'export':
                // Export selected prospects
                $prospects = $wpdb->get_results( 
                    "SELECT * FROM {$table_prefix}mq_prospects WHERE id IN (" . implode(',', $prospect_ids) . ")"
                );
                
                if ( empty( $prospects ) ) {
                    $prospects = $wpdb->get_results( 
                        "SELECT * FROM {$table_prefix}money_quiz_prospects WHERE id IN (" . implode(',', $prospect_ids) . ")"
                    );
                }
                
                // Generate CSV
                header( 'Content-Type: text/csv' );
                header( 'Content-Disposition: attachment; filename="prospects-export-' . date( 'Y-m-d' ) . '.csv"' );
                
                $output = fopen( 'php://output', 'w' );
                fputcsv( $output, array_keys( (array) $prospects[0] ) );
                
                foreach ( $prospects as $prospect ) {
                    fputcsv( $output, (array) $prospect );
                }
                
                fclose( $output );
                exit;
                break;
                
            case 'add_tag':
                $tag = sanitize_text_field( $_POST['tag_name'] );
                foreach ( $prospect_ids as $id ) {
                    update_post_meta( $id, '_mq_tags', $tag );
                }
                echo '<div class="notice notice-success"><p>' . __( 'Tag added to selected prospects.', 'money-quiz' ) . '</p></div>';
                break;
        }
    }
}

// Get filter parameters
$search = isset( $_GET['s'] ) ? sanitize_text_field( $_GET['s'] ) : '';
$quiz_filter = isset( $_GET['quiz_id'] ) ? intval( $_GET['quiz_id'] ) : 0;
$archetype_filter = isset( $_GET['archetype'] ) ? sanitize_text_field( $_GET['archetype'] ) : '';
$consent_filter = isset( $_GET['consent'] ) ? sanitize_text_field( $_GET['consent'] ) : '';
$page_num = isset( $_GET['paged'] ) ? max( 1, intval( $_GET['paged'] ) ) : 1;
$per_page = 50;

// Build query
$where_clauses = [];
if ( ! empty( $search ) ) {
    $where_clauses[] = $wpdb->prepare( 
        "(p.name LIKE %s OR p.email LIKE %s)",
        '%' . $wpdb->esc_like( $search ) . '%',
        '%' . $wpdb->esc_like( $search ) . '%'
    );
}
if ( $quiz_filter > 0 ) {
    $where_clauses[] = $wpdb->prepare( "p.quiz_id = %d", $quiz_filter );
}
if ( ! empty( $archetype_filter ) ) {
    $where_clauses[] = $wpdb->prepare( "a.name = %s", $archetype_filter );
}
if ( $consent_filter !== '' ) {
    $where_clauses[] = $wpdb->prepare( "p.email_consent = %d", $consent_filter );
}

$where_sql = ! empty( $where_clauses ) ? 'WHERE ' . implode( ' AND ', $where_clauses ) : '';

// Get total count
$total_prospects = $wpdb->get_var( "
    SELECT COUNT(*)
    FROM {$table_prefix}mq_prospects p
    LEFT JOIN {$table_prefix}mq_archetypes a ON p.archetype_id = a.id
    $where_sql
" );

// If no results, try legacy table
if ( ! $total_prospects ) {
    $total_prospects = $wpdb->get_var( "
        SELECT COUNT(*)
        FROM {$table_prefix}money_quiz_prospects p
        LEFT JOIN {$table_prefix}money_quiz_archetypes a ON p.archetype_id = a.id
        $where_sql
    " );
}

// Calculate pagination
$total_pages = ceil( $total_prospects / $per_page );
$offset = ( $page_num - 1 ) * $per_page;

// Get prospects
$prospects = $wpdb->get_results( $wpdb->prepare( "
    SELECT 
        p.*,
        m.quiz_name,
        a.name as archetype_name,
        p.date as signup_date
    FROM {$table_prefix}mq_prospects p
    LEFT JOIN {$table_prefix}mq_master m ON p.quiz_id = m.id
    LEFT JOIN {$table_prefix}mq_archetypes a ON p.archetype_id = a.id
    $where_sql
    ORDER BY p.date DESC
    LIMIT %d OFFSET %d
", $per_page, $offset ) );

// If no results, try legacy table structure
if ( empty( $prospects ) ) {
    $prospects = $wpdb->get_results( $wpdb->prepare( "
        SELECT 
            p.id,
            CONCAT(p.first_name, ' ', p.last_name) as name,
            p.email,
            p.phone,
            p.quiz_id,
            q.name as quiz_name,
            a.name as archetype_name,
            p.created_at as signup_date,
            p.email_consent,
            p.source,
            p.custom_fields
        FROM {$table_prefix}money_quiz_prospects p
        LEFT JOIN {$table_prefix}money_quiz_quizzes q ON p.quiz_id = q.id
        LEFT JOIN {$table_prefix}money_quiz_archetypes a ON p.archetype_id = a.id
        $where_sql
        ORDER BY p.created_at DESC
        LIMIT %d OFFSET %d
    ", $per_page, $offset ) );
}

// Get all quizzes for filter
$all_quizzes = $wpdb->get_results( "SELECT id, quiz_name FROM {$table_prefix}mq_master ORDER BY quiz_name" );
if ( empty( $all_quizzes ) ) {
    $all_quizzes = $wpdb->get_results( "SELECT id, name as quiz_name FROM {$table_prefix}money_quiz_quizzes ORDER BY name" );
}

// Get all archetypes for filter
$all_archetypes = $wpdb->get_results( "SELECT DISTINCT name FROM {$table_prefix}mq_archetypes ORDER BY name" );
if ( empty( $all_archetypes ) ) {
    $all_archetypes = $wpdb->get_results( "SELECT DISTINCT name FROM {$table_prefix}money_quiz_archetypes ORDER BY name" );
}
?>

<div class="wrap mq-prospects-manager">
    
    <!-- Search and Filters -->
    <div class="mq-prospects-filters">
        <form method="get" class="search-form">
            <input type="hidden" name="page" value="money-quiz-audience-prospects" />
            
            <p class="search-box">
                <label class="screen-reader-text" for="prospect-search"><?php _e( 'Search Prospects', 'money-quiz' ); ?></label>
                <input type="search" id="prospect-search" name="s" value="<?php echo esc_attr( $search ); ?>" placeholder="<?php _e( 'Search by name or email...', 'money-quiz' ); ?>" />
                <input type="submit" class="button" value="<?php _e( 'Search', 'money-quiz' ); ?>" />
            </p>
        </form>
        
        <div class="filter-controls">
            <form method="get" class="filters-form">
                <input type="hidden" name="page" value="money-quiz-audience-prospects" />
                
                <select name="quiz_id" onchange="this.form.submit()">
                    <option value="0"><?php _e( 'All Quizzes', 'money-quiz' ); ?></option>
                    <?php foreach ( $all_quizzes as $quiz ) : ?>
                        <option value="<?php echo $quiz->id; ?>" <?php selected( $quiz_filter, $quiz->id ); ?>>
                            <?php echo esc_html( $quiz->quiz_name ); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                
                <select name="archetype" onchange="this.form.submit()">
                    <option value=""><?php _e( 'All Archetypes', 'money-quiz' ); ?></option>
                    <?php foreach ( $all_archetypes as $archetype ) : ?>
                        <option value="<?php echo esc_attr( $archetype->name ); ?>" <?php selected( $archetype_filter, $archetype->name ); ?>>
                            <?php echo esc_html( $archetype->name ); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                
                <select name="consent" onchange="this.form.submit()">
                    <option value=""><?php _e( 'All Consent Status', 'money-quiz' ); ?></option>
                    <option value="1" <?php selected( $consent_filter, '1' ); ?>><?php _e( 'Email Consent Given', 'money-quiz' ); ?></option>
                    <option value="0" <?php selected( $consent_filter, '0' ); ?>><?php _e( 'No Email Consent', 'money-quiz' ); ?></option>
                </select>
            </form>
        </div>
    </div>
    
    <form method="post">
        <?php wp_nonce_field( 'bulk-prospects' ); ?>
        
        <!-- Bulk Actions -->
        <div class="tablenav top">
            <div class="alignleft actions bulkactions">
                <label for="bulk-action-selector-top" class="screen-reader-text"><?php _e( 'Select bulk action', 'money-quiz' ); ?></label>
                <select name="action" id="bulk-action-selector-top">
                    <option value="-1"><?php _e( 'Bulk Actions', 'money-quiz' ); ?></option>
                    <option value="export"><?php _e( 'Export Selected', 'money-quiz' ); ?></option>
                    <option value="delete"><?php _e( 'Delete', 'money-quiz' ); ?></option>
                    <option value="add_tag"><?php _e( 'Add Tag', 'money-quiz' ); ?></option>
                </select>
                <input type="text" name="tag_name" placeholder="<?php _e( 'Tag name', 'money-quiz' ); ?>" style="display: none;" class="tag-input" />
                <input type="submit" class="button action" value="<?php _e( 'Apply', 'money-quiz' ); ?>" />
            </div>
            
            <div class="tablenav-pages">
                <span class="displaying-num"><?php echo sprintf( __( '%d prospects', 'money-quiz' ), $total_prospects ); ?></span>
                <?php
                echo paginate_links( [
                    'base' => add_query_arg( 'paged', '%#%' ),
                    'format' => '',
                    'prev_text' => __( '&laquo;' ),
                    'next_text' => __( '&raquo;' ),
                    'total' => $total_pages,
                    'current' => $page_num
                ] );
                ?>
            </div>
        </div>
        
        <!-- Prospects Table -->
        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <td class="manage-column column-cb check-column">
                        <label class="screen-reader-text" for="cb-select-all-1"><?php _e( 'Select All', 'money-quiz' ); ?></label>
                        <input id="cb-select-all-1" type="checkbox" />
                    </td>
                    <th class="manage-column column-primary"><?php _e( 'Name', 'money-quiz' ); ?></th>
                    <th class="manage-column"><?php _e( 'Email', 'money-quiz' ); ?></th>
                    <th class="manage-column"><?php _e( 'Quiz', 'money-quiz' ); ?></th>
                    <th class="manage-column"><?php _e( 'Archetype', 'money-quiz' ); ?></th>
                    <th class="manage-column"><?php _e( 'Date', 'money-quiz' ); ?></th>
                    <th class="manage-column"><?php _e( 'Consent', 'money-quiz' ); ?></th>
                    <th class="manage-column"><?php _e( 'Actions', 'money-quiz' ); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php if ( empty( $prospects ) ) : ?>
                    <tr>
                        <td colspan="8" class="no-items">
                            <?php _e( 'No prospects found.', 'money-quiz' ); ?>
                        </td>
                    </tr>
                <?php else : ?>
                    <?php foreach ( $prospects as $prospect ) : ?>
                        <tr>
                            <th scope="row" class="check-column">
                                <input type="checkbox" name="prospect[]" value="<?php echo $prospect->id; ?>" />
                            </th>
                            <td class="column-primary">
                                <strong>
                                    <a href="?page=money-quiz-audience-prospects&action=view&id=<?php echo $prospect->id; ?>">
                                        <?php echo esc_html( $prospect->name ?: __( 'Anonymous', 'money-quiz' ) ); ?>
                                    </a>
                                </strong>
                                <div class="row-actions">
                                    <span class="view">
                                        <a href="?page=money-quiz-audience-prospects&action=view&id=<?php echo $prospect->id; ?>">
                                            <?php _e( 'View', 'money-quiz' ); ?>
                                        </a> |
                                    </span>
                                    <span class="email">
                                        <a href="mailto:<?php echo esc_attr( $prospect->email ); ?>">
                                            <?php _e( 'Email', 'money-quiz' ); ?>
                                        </a> |
                                    </span>
                                    <span class="delete">
                                        <a href="<?php echo wp_nonce_url( admin_url( 'admin.php?page=money-quiz-audience-prospects&action=delete&id=' . $prospect->id ), 'delete-prospect-' . $prospect->id ); ?>" onclick="return confirm('<?php _e( 'Are you sure?', 'money-quiz' ); ?>');">
                                            <?php _e( 'Delete', 'money-quiz' ); ?>
                                        </a>
                                    </span>
                                </div>
                                <button type="button" class="toggle-row">
                                    <span class="screen-reader-text"><?php _e( 'Show more details', 'money-quiz' ); ?></span>
                                </button>
                            </td>
                            <td><?php echo esc_html( $prospect->email ); ?></td>
                            <td><?php echo esc_html( $prospect->quiz_name ?: '-' ); ?></td>
                            <td><?php echo esc_html( $prospect->archetype_name ?: '-' ); ?></td>
                            <td><?php echo date_i18n( get_option( 'date_format' ), strtotime( $prospect->signup_date ) ); ?></td>
                            <td>
                                <?php if ( $prospect->email_consent ) : ?>
                                    <span class="consent-yes">✓ <?php _e( 'Yes', 'money-quiz' ); ?></span>
                                <?php else : ?>
                                    <span class="consent-no">✗ <?php _e( 'No', 'money-quiz' ); ?></span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <button type="button" class="button button-small add-note" data-prospect-id="<?php echo $prospect->id; ?>">
                                    <?php _e( 'Add Note', 'money-quiz' ); ?>
                                </button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </form>
    
    <!-- Export Options -->
    <div class="mq-card">
        <h2><?php _e( 'Export Options', 'money-quiz' ); ?></h2>
        <p><?php _e( 'Export all prospects or filtered results to various formats.', 'money-quiz' ); ?></p>
        
        <div class="export-buttons">
            <a href="<?php echo wp_nonce_url( admin_url( 'admin.php?page=money-quiz-audience-export&format=csv' ), 'export-prospects' ); ?>" class="button">
                <?php _e( 'Export All to CSV', 'money-quiz' ); ?>
            </a>
            <a href="<?php echo wp_nonce_url( admin_url( 'admin.php?page=money-quiz-audience-export&format=excel' ), 'export-prospects' ); ?>" class="button">
                <?php _e( 'Export All to Excel', 'money-quiz' ); ?>
            </a>
            <a href="<?php echo wp_nonce_url( admin_url( 'admin.php?page=money-quiz-audience-export&format=json' ), 'export-prospects' ); ?>" class="button">
                <?php _e( 'Export All to JSON', 'money-quiz' ); ?>
            </a>
        </div>
    </div>
    
</div>

<script>
jQuery(document).ready(function($) {
    // Show/hide tag input based on bulk action selection
    $('#bulk-action-selector-top').on('change', function() {
        if ($(this).val() === 'add_tag') {
            $('.tag-input').show();
        } else {
            $('.tag-input').hide();
        }
    });
    
    // Add note functionality
    $('.add-note').on('click', function() {
        var prospectId = $(this).data('prospect-id');
        var note = prompt('<?php _e( 'Enter note:', 'money-quiz' ); ?>');
        if (note) {
            // Would send AJAX request to save note
            alert('Note functionality would be implemented here');
        }
    });
});
</script>

<style>
.mq-prospects-filters {
    display: flex;
    justify-content: space-between;
    margin-bottom: 20px;
}

.filter-controls .filters-form {
    display: flex;
    gap: 10px;
}

.consent-yes {
    color: #00a32a;
}

.consent-no {
    color: #dc3232;
}

.export-buttons {
    display: flex;
    gap: 10px;
    margin-top: 15px;
}

.no-items {
    text-align: center;
    padding: 40px !important;
}
</style>
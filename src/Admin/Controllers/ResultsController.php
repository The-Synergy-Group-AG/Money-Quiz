<?php
/**
 * Results Controller
 *
 * @package MoneyQuiz
 * @since 4.0.0
 */

namespace MoneyQuiz\Admin\Controllers;

use MoneyQuiz\Database\Repositories\ProspectRepository;

/**
 * Handles quiz results management in admin
 */
class ResultsController {
    
    /**
     * @var ProspectRepository
     */
    private ProspectRepository $prospect_repository;
    
    /**
     * Constructor
     */
    public function __construct() {
        $container = \MoneyQuiz\Core\Plugin::instance()->get_container();
        $this->prospect_repository = $container->get( 'repository.prospect' );
    }
    
    /**
     * Display results listing
     * 
     * @return void
     */
    public function index(): void {
        // Handle export
        if ( isset( $_GET['export'] ) && $_GET['export'] === 'csv' ) {
            $this->export_csv();
            return;
        }
        
        // Handle bulk actions
        if ( isset( $_POST['action'] ) && $_POST['action'] !== '-1' ) {
            $this->handle_bulk_action();
        }
        
        // Get filter parameters
        $filters = $this->get_filters();
        
        // Get results
        $results = $this->get_results( $filters );
        $total_results = $this->count_results( $filters );
        
        // Display the results page
        $this->render_results_page( $results, $total_results, $filters );
    }
    
    /**
     * Get filter parameters
     * 
     * @return array
     */
    private function get_filters(): array {
        return [
            'search' => sanitize_text_field( $_GET['s'] ?? '' ),
            'archetype' => sanitize_text_field( $_GET['archetype'] ?? '' ),
            'date_from' => sanitize_text_field( $_GET['date_from'] ?? '' ),
            'date_to' => sanitize_text_field( $_GET['date_to'] ?? '' ),
            'orderby' => sanitize_text_field( $_GET['orderby'] ?? 'taken_date' ),
            'order' => strtoupper( sanitize_text_field( $_GET['order'] ?? 'DESC' ) ),
            'paged' => absint( $_GET['paged'] ?? 1 ),
        ];
    }
    
    /**
     * Get results based on filters
     * 
     * @param array $filters Filter parameters
     * @return array
     */
    private function get_results( array $filters ): array {
        global $wpdb;
        
        // Check for legacy or modern tables
        $legacy_table = $wpdb->prefix . 'mq_results';
        $modern_table = $wpdb->prefix . 'money_quiz_results';
        
        $use_legacy = $wpdb->get_var( "SHOW TABLES LIKE '{$legacy_table}'" );
        
        if ( $use_legacy ) {
            return $this->get_legacy_results( $filters );
        }
        
        // Build modern query
        $query = "SELECT r.*, p.email, p.name, p.phone, p.company, a.name as archetype_name 
                  FROM {$modern_table} r
                  LEFT JOIN {$wpdb->prefix}money_quiz_prospects p ON r.prospect_id = p.id
                  LEFT JOIN {$wpdb->prefix}money_quiz_archetypes a ON r.archetype_id = a.id
                  WHERE 1=1";
        
        $query_args = [];
        
        // Apply filters
        if ( ! empty( $filters['search'] ) ) {
            $query .= " AND (p.email LIKE %s OR p.name LIKE %s)";
            $search_term = '%' . $wpdb->esc_like( $filters['search'] ) . '%';
            $query_args[] = $search_term;
            $query_args[] = $search_term;
        }
        
        if ( ! empty( $filters['archetype'] ) ) {
            $query .= " AND r.archetype_id = %d";
            $query_args[] = $filters['archetype'];
        }
        
        if ( ! empty( $filters['date_from'] ) ) {
            $query .= " AND r.completed_at >= %s";
            $query_args[] = $filters['date_from'] . ' 00:00:00';
        }
        
        if ( ! empty( $filters['date_to'] ) ) {
            $query .= " AND r.completed_at <= %s";
            $query_args[] = $filters['date_to'] . ' 23:59:59';
        }
        
        // Add ordering
        $allowed_orderby = [ 'taken_date', 'email', 'name', 'archetype_name', 'score' ];
        $orderby = in_array( $filters['orderby'], $allowed_orderby ) ? $filters['orderby'] : 'taken_date';
        $order = $filters['order'] === 'ASC' ? 'ASC' : 'DESC';
        
        if ( $orderby === 'taken_date' ) {
            $orderby = 'r.completed_at';
        }
        
        $query .= " ORDER BY {$orderby} {$order}";
        
        // Add pagination
        $per_page = 20;
        $offset = ( $filters['paged'] - 1 ) * $per_page;
        $query .= " LIMIT {$per_page} OFFSET {$offset}";
        
        // Execute query
        if ( ! empty( $query_args ) ) {
            $query = $wpdb->prepare( $query, $query_args );
        }
        
        return $wpdb->get_results( $query );
    }
    
    /**
     * Get legacy results
     * 
     * @param array $filters Filter parameters
     * @return array
     */
    private function get_legacy_results( array $filters ): array {
        global $wpdb;
        
        $query = "SELECT 
                    r.Taken_ID as id,
                    r.Prospect_ID as prospect_id,
                    p.Name as name,
                    p.email_id as email,
                    p.Phone as phone,
                    p.Company as company,
                    r.Taken_Date as completed_at,
                    GROUP_CONCAT(DISTINCT a.Archetype) as archetype_name,
                    COUNT(DISTINCT r.Master_ID) as questions_answered
                  FROM {$wpdb->prefix}mq_results r
                  LEFT JOIN {$wpdb->prefix}mq_prospect_master p ON r.Prospect_ID = p.Prospect_ID
                  LEFT JOIN {$wpdb->prefix}mq_master a ON r.Master_ID = a.Master_ID
                  WHERE 1=1";
        
        $query_args = [];
        
        // Apply filters
        if ( ! empty( $filters['search'] ) ) {
            $query .= " AND (p.email_id LIKE %s OR p.Name LIKE %s)";
            $search_term = '%' . $wpdb->esc_like( $filters['search'] ) . '%';
            $query_args[] = $search_term;
            $query_args[] = $search_term;
        }
        
        if ( ! empty( $filters['date_from'] ) ) {
            $query .= " AND r.Taken_Date >= %s";
            $query_args[] = $filters['date_from'] . ' 00:00:00';
        }
        
        if ( ! empty( $filters['date_to'] ) ) {
            $query .= " AND r.Taken_Date <= %s";
            $query_args[] = $filters['date_to'] . ' 23:59:59';
        }
        
        // Group by taken ID
        $query .= " GROUP BY r.Taken_ID, r.Prospect_ID";
        
        // Add ordering
        $orderby = $filters['orderby'] === 'taken_date' ? 'r.Taken_Date' : 'p.Name';
        $order = $filters['order'] === 'ASC' ? 'ASC' : 'DESC';
        $query .= " ORDER BY {$orderby} {$order}";
        
        // Add pagination
        $per_page = 20;
        $offset = ( $filters['paged'] - 1 ) * $per_page;
        $query .= " LIMIT {$per_page} OFFSET {$offset}";
        
        // Execute query
        if ( ! empty( $query_args ) ) {
            $query = $wpdb->prepare( $query, $query_args );
        }
        
        return $wpdb->get_results( $query );
    }
    
    /**
     * Count results based on filters
     * 
     * @param array $filters Filter parameters
     * @return int
     */
    private function count_results( array $filters ): int {
        global $wpdb;
        
        // Similar logic to get_results but with COUNT
        $legacy_table = $wpdb->prefix . 'mq_results';
        $use_legacy = $wpdb->get_var( "SHOW TABLES LIKE '{$legacy_table}'" );
        
        if ( $use_legacy ) {
            $query = "SELECT COUNT(DISTINCT Taken_ID) FROM {$legacy_table}";
        } else {
            $query = "SELECT COUNT(*) FROM {$wpdb->prefix}money_quiz_results";
        }
        
        return (int) $wpdb->get_var( $query );
    }
    
    /**
     * Handle bulk actions
     * 
     * @return void
     */
    private function handle_bulk_action(): void {
        $action = $_POST['action'];
        $result_ids = $_POST['result'] ?? [];
        
        if ( empty( $result_ids ) ) {
            return;
        }
        
        // Verify nonce
        if ( ! wp_verify_nonce( $_POST['_wpnonce'] ?? '', 'bulk-results' ) ) {
            wp_die( __( 'Security check failed.', 'money-quiz' ) );
        }
        
        switch ( $action ) {
            case 'delete':
                $this->bulk_delete( $result_ids );
                break;
                
            case 'export':
                $this->export_selected( $result_ids );
                break;
        }
        
        wp_redirect( add_query_arg( [
            'page' => 'money-quiz-results',
            'message' => $action . '_success',
        ], admin_url( 'admin.php' ) ) );
        exit;
    }
    
    /**
     * Bulk delete results
     * 
     * @param array $result_ids Result IDs
     * @return void
     */
    private function bulk_delete( array $result_ids ): void {
        global $wpdb;
        
        foreach ( $result_ids as $id ) {
            $wpdb->delete(
                $wpdb->prefix . 'money_quiz_results',
                [ 'id' => absint( $id ) ]
            );
        }
    }
    
    /**
     * Export results to CSV
     * 
     * @return void
     */
    private function export_csv(): void {
        // Check capability
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( __( 'You do not have permission to export results.', 'money-quiz' ) );
        }
        
        // Get all results (no pagination)
        $filters = $this->get_filters();
        unset( $filters['paged'] ); // Remove pagination for export
        
        global $wpdb;
        
        // Get results based on table structure
        $legacy_table = $wpdb->prefix . 'mq_results';
        $use_legacy = $wpdb->get_var( "SHOW TABLES LIKE '{$legacy_table}'" );
        
        if ( $use_legacy ) {
            $results = $this->get_all_legacy_results( $filters );
        } else {
            $results = $this->get_all_modern_results( $filters );
        }
        
        // Set headers for CSV download
        header( 'Content-Type: text/csv; charset=utf-8' );
        header( 'Content-Disposition: attachment; filename=money-quiz-results-' . date( 'Y-m-d' ) . '.csv' );
        
        // Create output stream
        $output = fopen( 'php://output', 'w' );
        
        // Add BOM for Excel UTF-8 compatibility
        fprintf( $output, chr(0xEF) . chr(0xBB) . chr(0xBF) );
        
        // Add headers
        fputcsv( $output, [
            'ID',
            'Date',
            'Name',
            'Email',
            'Phone',
            'Company',
            'Archetype',
            'Score',
            'Questions Answered',
        ] );
        
        // Add data rows
        foreach ( $results as $result ) {
            fputcsv( $output, [
                $result->id ?? '',
                $result->completed_at ?? '',
                $result->name ?? '',
                $result->email ?? '',
                $result->phone ?? '',
                $result->company ?? '',
                $result->archetype_name ?? '',
                $result->score ?? '',
                $result->questions_answered ?? '',
            ] );
        }
        
        fclose( $output );
        exit;
    }
    
    /**
     * Get all legacy results for export
     * 
     * @param array $filters Filters
     * @return array
     */
    private function get_all_legacy_results( array $filters ): array {
        global $wpdb;
        
        $query = "SELECT 
                    r.Taken_ID as id,
                    p.Name as name,
                    p.email_id as email,
                    p.Phone as phone,
                    p.Company as company,
                    MAX(r.Taken_Date) as completed_at,
                    GROUP_CONCAT(DISTINCT a.Archetype) as archetype_name,
                    COUNT(DISTINCT r.Master_ID) as questions_answered,
                    AVG(r.Score) as score
                  FROM {$wpdb->prefix}mq_results r
                  LEFT JOIN {$wpdb->prefix}mq_prospect_master p ON r.Prospect_ID = p.Prospect_ID
                  LEFT JOIN {$wpdb->prefix}mq_master a ON r.Master_ID = a.Master_ID
                  GROUP BY r.Taken_ID, r.Prospect_ID
                  ORDER BY completed_at DESC";
        
        return $wpdb->get_results( $query );
    }
    
    /**
     * Get all modern results for export
     * 
     * @param array $filters Filters
     * @return array
     */
    private function get_all_modern_results( array $filters ): array {
        global $wpdb;
        
        $query = "SELECT 
                    r.*,
                    p.email,
                    p.name,
                    p.phone,
                    p.company,
                    a.name as archetype_name
                  FROM {$wpdb->prefix}money_quiz_results r
                  LEFT JOIN {$wpdb->prefix}money_quiz_prospects p ON r.prospect_id = p.id
                  LEFT JOIN {$wpdb->prefix}money_quiz_archetypes a ON r.archetype_id = a.id
                  ORDER BY r.completed_at DESC";
        
        return $wpdb->get_results( $query );
    }
    
    /**
     * Render results page
     * 
     * @param array $results       Results to display
     * @param int   $total_results Total count
     * @param array $filters       Active filters
     * @return void
     */
    private function render_results_page( array $results, int $total_results, array $filters ): void {
        // Get archetypes for filter dropdown
        $archetypes = $this->get_archetypes();
        
        ?>
        <div class="wrap">
            <h1 class="wp-heading-inline"><?php _e( 'Quiz Results', 'money-quiz' ); ?></h1>
            <a href="<?php echo add_query_arg( 'export', 'csv', $_SERVER['REQUEST_URI'] ); ?>" 
               class="page-title-action">
                <?php _e( 'Export CSV', 'money-quiz' ); ?>
            </a>
            
            <hr class="wp-header-end">
            
            <?php $this->render_filters( $filters, $archetypes ); ?>
            
            <?php $this->render_analytics_summary( $total_results ); ?>
            
            <form method="post">
                <?php wp_nonce_field( 'bulk-results' ); ?>
                
                <div class="tablenav top">
                    <div class="alignleft actions bulkactions">
                        <select name="action">
                            <option value="-1"><?php _e( 'Bulk Actions', 'money-quiz' ); ?></option>
                            <option value="delete"><?php _e( 'Delete', 'money-quiz' ); ?></option>
                            <option value="export"><?php _e( 'Export Selected', 'money-quiz' ); ?></option>
                        </select>
                        <input type="submit" class="button action" value="<?php esc_attr_e( 'Apply', 'money-quiz' ); ?>">
                    </div>
                    
                    <?php $this->render_pagination( $total_results, $filters['paged'] ); ?>
                </div>
                
                <table class="wp-list-table widefat fixed striped">
                    <thead>
                        <tr>
                            <td class="manage-column column-cb check-column">
                                <input type="checkbox" />
                            </td>
                            <th><?php _e( 'Date', 'money-quiz' ); ?></th>
                            <th><?php _e( 'Name', 'money-quiz' ); ?></th>
                            <th><?php _e( 'Email', 'money-quiz' ); ?></th>
                            <th><?php _e( 'Archetype', 'money-quiz' ); ?></th>
                            <th><?php _e( 'Score', 'money-quiz' ); ?></th>
                            <th><?php _e( 'Actions', 'money-quiz' ); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ( empty( $results ) ) : ?>
                            <tr>
                                <td colspan="7"><?php _e( 'No results found.', 'money-quiz' ); ?></td>
                            </tr>
                        <?php else : ?>
                            <?php foreach ( $results as $result ) : ?>
                                <tr>
                                    <th scope="row" class="check-column">
                                        <input type="checkbox" name="result[]" value="<?php echo esc_attr( $result->id ); ?>" />
                                    </th>
                                    <td><?php echo esc_html( date_i18n( get_option( 'date_format' ), strtotime( $result->completed_at ) ) ); ?></td>
                                    <td><?php echo esc_html( $result->name ?: __( 'Anonymous', 'money-quiz' ) ); ?></td>
                                    <td><?php echo esc_html( $result->email ?: '-' ); ?></td>
                                    <td><?php echo esc_html( $result->archetype_name ?: '-' ); ?></td>
                                    <td><?php echo esc_html( isset( $result->score ) ? round( $result->score, 1 ) . '%' : '-' ); ?></td>
                                    <td>
                                        <a href="<?php echo add_query_arg( [ 'page' => 'money-quiz-results', 'action' => 'view', 'id' => $result->id ], admin_url( 'admin.php' ) ); ?>" 
                                           class="button button-small">
                                            <?php _e( 'View', 'money-quiz' ); ?>
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
                
                <div class="tablenav bottom">
                    <?php $this->render_pagination( $total_results, $filters['paged'] ); ?>
                </div>
            </form>
        </div>
        <?php
    }
    
    /**
     * Render filters
     * 
     * @param array $filters    Active filters
     * @param array $archetypes Available archetypes
     * @return void
     */
    private function render_filters( array $filters, array $archetypes ): void {
        ?>
        <form method="get" class="results-filters">
            <input type="hidden" name="page" value="money-quiz-results" />
            
            <div class="tablenav top">
                <div class="alignleft actions">
                    <input type="search" 
                           name="s" 
                           value="<?php echo esc_attr( $filters['search'] ); ?>" 
                           placeholder="<?php esc_attr_e( 'Search by name or email...', 'money-quiz' ); ?>" />
                    
                    <select name="archetype">
                        <option value=""><?php _e( 'All Archetypes', 'money-quiz' ); ?></option>
                        <?php foreach ( $archetypes as $archetype ) : ?>
                            <option value="<?php echo esc_attr( $archetype->id ); ?>" 
                                    <?php selected( $filters['archetype'], $archetype->id ); ?>>
                                <?php echo esc_html( $archetype->name ); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    
                    <input type="date" 
                           name="date_from" 
                           value="<?php echo esc_attr( $filters['date_from'] ); ?>" 
                           placeholder="<?php esc_attr_e( 'From date', 'money-quiz' ); ?>" />
                    
                    <input type="date" 
                           name="date_to" 
                           value="<?php echo esc_attr( $filters['date_to'] ); ?>" 
                           placeholder="<?php esc_attr_e( 'To date', 'money-quiz' ); ?>" />
                    
                    <input type="submit" class="button" value="<?php esc_attr_e( 'Filter', 'money-quiz' ); ?>" />
                    
                    <?php if ( array_filter( $filters ) ) : ?>
                        <a href="<?php echo admin_url( 'admin.php?page=money-quiz-results' ); ?>" class="button">
                            <?php _e( 'Clear Filters', 'money-quiz' ); ?>
                        </a>
                    <?php endif; ?>
                </div>
            </div>
        </form>
        <?php
    }
    
    /**
     * Render analytics summary
     * 
     * @param int $total_results Total results count
     * @return void
     */
    private function render_analytics_summary( int $total_results ): void {
        global $wpdb;
        
        // Get basic analytics
        $today_count = $wpdb->get_var( 
            "SELECT COUNT(*) FROM {$wpdb->prefix}money_quiz_results 
             WHERE DATE(completed_at) = CURDATE()" 
        );
        
        $this_week_count = $wpdb->get_var( 
            "SELECT COUNT(*) FROM {$wpdb->prefix}money_quiz_results 
             WHERE YEARWEEK(completed_at) = YEARWEEK(CURDATE())" 
        );
        
        $this_month_count = $wpdb->get_var( 
            "SELECT COUNT(*) FROM {$wpdb->prefix}money_quiz_results 
             WHERE YEAR(completed_at) = YEAR(CURDATE()) 
             AND MONTH(completed_at) = MONTH(CURDATE())" 
        );
        
        ?>
        <div class="analytics-summary">
            <div class="stat-box">
                <h3><?php echo number_format( $total_results ); ?></h3>
                <p><?php _e( 'Total Results', 'money-quiz' ); ?></p>
            </div>
            <div class="stat-box">
                <h3><?php echo number_format( $today_count ?: 0 ); ?></h3>
                <p><?php _e( 'Today', 'money-quiz' ); ?></p>
            </div>
            <div class="stat-box">
                <h3><?php echo number_format( $this_week_count ?: 0 ); ?></h3>
                <p><?php _e( 'This Week', 'money-quiz' ); ?></p>
            </div>
            <div class="stat-box">
                <h3><?php echo number_format( $this_month_count ?: 0 ); ?></h3>
                <p><?php _e( 'This Month', 'money-quiz' ); ?></p>
            </div>
        </div>
        
        <style>
        .analytics-summary {
            display: flex;
            gap: 20px;
            margin: 20px 0;
        }
        .stat-box {
            background: #fff;
            border: 1px solid #ccd0d4;
            padding: 20px;
            text-align: center;
            flex: 1;
        }
        .stat-box h3 {
            margin: 0 0 10px 0;
            font-size: 2em;
            color: #23282d;
        }
        .stat-box p {
            margin: 0;
            color: #666;
        }
        </style>
        <?php
    }
    
    /**
     * Render pagination
     * 
     * @param int $total_items Total items
     * @param int $current_page Current page
     * @return void
     */
    private function render_pagination( int $total_items, int $current_page ): void {
        $per_page = 20;
        $total_pages = ceil( $total_items / $per_page );
        
        if ( $total_pages <= 1 ) {
            return;
        }
        
        $page_links = paginate_links( [
            'base' => add_query_arg( 'paged', '%#%' ),
            'format' => '',
            'prev_text' => '&laquo;',
            'next_text' => '&raquo;',
            'total' => $total_pages,
            'current' => $current_page,
        ] );
        
        if ( $page_links ) {
            echo '<div class="tablenav-pages">';
            echo '<span class="displaying-num">' . 
                 sprintf( _n( '%s item', '%s items', $total_items, 'money-quiz' ), number_format( $total_items ) ) . 
                 '</span>';
            echo '<span class="pagination-links">' . $page_links . '</span>';
            echo '</div>';
        }
    }
    
    /**
     * Get archetypes for filtering
     * 
     * @return array
     */
    private function get_archetypes(): array {
        global $wpdb;
        
        // Try modern table first
        $modern_table = $wpdb->prefix . 'money_quiz_archetypes';
        if ( $wpdb->get_var( "SHOW TABLES LIKE '{$modern_table}'" ) ) {
            return $wpdb->get_results( "SELECT id, name FROM {$modern_table} ORDER BY name" );
        }
        
        // Fall back to legacy
        $legacy_table = $wpdb->prefix . 'mq_archetype_master';
        if ( $wpdb->get_var( "SHOW TABLES LIKE '{$legacy_table}'" ) ) {
            return $wpdb->get_results( 
                "SELECT Archetype_ID as id, Archetype as name 
                 FROM {$legacy_table} 
                 WHERE Status = 'Active' 
                 ORDER BY Archetype" 
            );
        }
        
        return [];
    }
}
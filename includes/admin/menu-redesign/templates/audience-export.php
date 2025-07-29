<?php
/**
 * Export/Import Template
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

// Handle export
if ( isset( $_GET['export_action'] ) ) {
    check_admin_referer( 'export_data' );
    
    $export_type = sanitize_text_field( $_GET['export_type'] );
    $format = sanitize_text_field( $_GET['format'] );
    
    switch ( $export_type ) {
        case 'prospects':
            export_prospects( $format );
            break;
        case 'quizzes':
            export_quizzes( $format );
            break;
        case 'results':
            export_results( $format );
            break;
        case 'all':
            export_all_data( $format );
            break;
    }
    exit;
}

// Handle import
if ( isset( $_POST['import_action'] ) ) {
    check_admin_referer( 'import_data' );
    
    if ( ! empty( $_FILES['import_file']['tmp_name'] ) ) {
        $import_type = sanitize_text_field( $_POST['import_type'] );
        $file_content = file_get_contents( $_FILES['import_file']['tmp_name'] );
        
        $result = false;
        switch ( $import_type ) {
            case 'prospects':
                $result = import_prospects( $file_content );
                break;
            case 'quizzes':
                $result = import_quizzes( $file_content );
                break;
            case 'questions':
                $result = import_questions( $file_content );
                break;
        }
        
        if ( $result ) {
            echo '<div class="notice notice-success"><p>' . sprintf( __( 'Successfully imported %d items.', 'money-quiz' ), $result ) . '</p></div>';
        } else {
            echo '<div class="notice notice-error"><p>' . __( 'Import failed. Please check your file format.', 'money-quiz' ) . '</p></div>';
        }
    }
}

// Export functions
function export_prospects( $format ) {
    global $wpdb, $table_prefix;
    
    $prospects = $wpdb->get_results( "
        SELECT 
            p.*,
            m.quiz_name,
            a.name as archetype_name
        FROM {$table_prefix}mq_prospects p
        LEFT JOIN {$table_prefix}mq_master m ON p.quiz_id = m.id
        LEFT JOIN {$table_prefix}mq_archetypes a ON p.archetype_id = a.id
        ORDER BY p.date DESC
    ", ARRAY_A );
    
    // Fallback to legacy table
    if ( empty( $prospects ) ) {
        $prospects = $wpdb->get_results( "
            SELECT 
                p.*,
                q.name as quiz_name,
                a.name as archetype_name
            FROM {$table_prefix}money_quiz_prospects p
            LEFT JOIN {$table_prefix}money_quiz_quizzes q ON p.quiz_id = q.id
            LEFT JOIN {$table_prefix}money_quiz_archetypes a ON p.archetype_id = a.id
            ORDER BY p.created_at DESC
        ", ARRAY_A );
    }
    
    output_data( $prospects, 'prospects', $format );
}

function export_quizzes( $format ) {
    global $wpdb, $table_prefix;
    
    $quizzes = $wpdb->get_results( "
        SELECT 
            q.*,
            COUNT(DISTINCT qu.id) as question_count,
            COUNT(DISTINCT a.id) as archetype_count,
            COUNT(DISTINCT p.id) as prospect_count
        FROM {$table_prefix}mq_master q
        LEFT JOIN {$table_prefix}mq_questions qu ON q.id = qu.quiz_id
        LEFT JOIN {$table_prefix}mq_archetypes a ON q.id = a.quiz_id
        LEFT JOIN {$table_prefix}mq_prospects p ON q.id = p.quiz_id
        GROUP BY q.id
    ", ARRAY_A );
    
    output_data( $quizzes, 'quizzes', $format );
}

function export_results( $format ) {
    global $wpdb, $table_prefix;
    
    $results = $wpdb->get_results( "
        SELECT 
            p.name,
            p.email,
            m.quiz_name,
            a.name as archetype_name,
            p.score,
            p.date,
            p.source
        FROM {$table_prefix}mq_prospects p
        LEFT JOIN {$table_prefix}mq_master m ON p.quiz_id = m.id
        LEFT JOIN {$table_prefix}mq_archetypes a ON p.archetype_id = a.id
        ORDER BY p.date DESC
    ", ARRAY_A );
    
    output_data( $results, 'results', $format );
}

function export_all_data( $format ) {
    global $wpdb, $table_prefix;
    
    $all_data = [
        'quizzes' => $wpdb->get_results( "SELECT * FROM {$table_prefix}mq_master", ARRAY_A ),
        'questions' => $wpdb->get_results( "SELECT * FROM {$table_prefix}mq_questions", ARRAY_A ),
        'archetypes' => $wpdb->get_results( "SELECT * FROM {$table_prefix}mq_archetypes", ARRAY_A ),
        'prospects' => $wpdb->get_results( "SELECT * FROM {$table_prefix}mq_prospects", ARRAY_A ),
        'export_date' => current_time( 'mysql' ),
        'plugin_version' => MONEY_QUIZ_VERSION
    ];
    
    output_data( $all_data, 'money-quiz-complete', $format );
}

function output_data( $data, $filename, $format ) {
    switch ( $format ) {
        case 'csv':
            header( 'Content-Type: text/csv' );
            header( 'Content-Disposition: attachment; filename="' . $filename . '-' . date( 'Y-m-d' ) . '.csv"' );
            
            if ( ! empty( $data ) && is_array( $data[0] ) ) {
                $output = fopen( 'php://output', 'w' );
                fputcsv( $output, array_keys( $data[0] ) );
                foreach ( $data as $row ) {
                    fputcsv( $output, $row );
                }
                fclose( $output );
            }
            break;
            
        case 'json':
            header( 'Content-Type: application/json' );
            header( 'Content-Disposition: attachment; filename="' . $filename . '-' . date( 'Y-m-d' ) . '.json"' );
            echo json_encode( $data, JSON_PRETTY_PRINT );
            break;
            
        case 'excel':
            // Would require PHPSpreadsheet library
            // For now, output as CSV with Excel mime type
            header( 'Content-Type: application/vnd.ms-excel' );
            header( 'Content-Disposition: attachment; filename="' . $filename . '-' . date( 'Y-m-d' ) . '.xls"' );
            
            if ( ! empty( $data ) && is_array( $data[0] ) ) {
                echo '<table border="1">';
                echo '<tr>';
                foreach ( array_keys( $data[0] ) as $header ) {
                    echo '<th>' . htmlspecialchars( $header ) . '</th>';
                }
                echo '</tr>';
                
                foreach ( $data as $row ) {
                    echo '<tr>';
                    foreach ( $row as $value ) {
                        echo '<td>' . htmlspecialchars( $value ) . '</td>';
                    }
                    echo '</tr>';
                }
                echo '</table>';
            }
            break;
    }
}

// Import functions
function import_prospects( $content ) {
    global $wpdb, $table_prefix;
    
    $lines = explode( "\n", $content );
    $headers = str_getcsv( array_shift( $lines ) );
    
    $imported = 0;
    foreach ( $lines as $line ) {
        if ( empty( trim( $line ) ) ) continue;
        
        $data = str_getcsv( $line );
        $row = array_combine( $headers, $data );
        
        if ( isset( $row['email'] ) && ! empty( $row['email'] ) ) {
            // Check if prospect already exists
            $exists = $wpdb->get_var( $wpdb->prepare(
                "SELECT id FROM {$table_prefix}mq_prospects WHERE email = %s",
                $row['email']
            ) );
            
            if ( ! $exists ) {
                $wpdb->insert(
                    "{$table_prefix}mq_prospects",
                    [
                        'name' => $row['name'] ?? '',
                        'email' => $row['email'],
                        'quiz_id' => $row['quiz_id'] ?? 0,
                        'archetype_id' => $row['archetype_id'] ?? 0,
                        'score' => $row['score'] ?? 0,
                        'date' => $row['date'] ?? current_time( 'mysql' ),
                        'email_consent' => $row['email_consent'] ?? 1
                    ]
                );
                $imported++;
            }
        }
    }
    
    return $imported;
}

// Get statistics
$stats = [
    'total_prospects' => $wpdb->get_var( "SELECT COUNT(*) FROM {$table_prefix}mq_prospects" ) ?: 
                        $wpdb->get_var( "SELECT COUNT(*) FROM {$table_prefix}money_quiz_prospects" ),
    'total_quizzes' => $wpdb->get_var( "SELECT COUNT(*) FROM {$table_prefix}mq_master" ) ?:
                      $wpdb->get_var( "SELECT COUNT(*) FROM {$table_prefix}money_quiz_quizzes" ),
    'total_questions' => $wpdb->get_var( "SELECT COUNT(*) FROM {$table_prefix}mq_questions" ) ?:
                        $wpdb->get_var( "SELECT COUNT(*) FROM {$table_prefix}money_quiz_questions" ),
    'total_archetypes' => $wpdb->get_var( "SELECT COUNT(*) FROM {$table_prefix}mq_archetypes" ) ?:
                         $wpdb->get_var( "SELECT COUNT(*) FROM {$table_prefix}money_quiz_archetypes" )
];
?>

<div class="wrap mq-export-import">
    
    <!-- Export Section -->
    <div class="mq-card">
        <h2><?php _e( '📥 Export Data', 'money-quiz' ); ?></h2>
        <p><?php _e( 'Export your Money Quiz data in various formats for backup, analysis, or migration.', 'money-quiz' ); ?></p>
        
        <form method="get" class="export-form">
            <input type="hidden" name="page" value="money-quiz-audience-export" />
            <input type="hidden" name="export_action" value="1" />
            <?php wp_nonce_field( 'export_data' ); ?>
            
            <table class="form-table">
                <tr>
                    <th scope="row">
                        <label><?php _e( 'Export Type', 'money-quiz' ); ?></label>
                    </th>
                    <td>
                        <select name="export_type" id="export_type">
                            <option value="prospects"><?php _e( 'Prospects/Leads Only', 'money-quiz' ); ?></option>
                            <option value="quizzes"><?php _e( 'Quizzes Only', 'money-quiz' ); ?></option>
                            <option value="results"><?php _e( 'Quiz Results Only', 'money-quiz' ); ?></option>
                            <option value="all"><?php _e( 'Complete Database Export', 'money-quiz' ); ?></option>
                        </select>
                        
                        <div class="export-stats">
                            <span><?php echo sprintf( __( '%d prospects', 'money-quiz' ), $stats['total_prospects'] ); ?></span> •
                            <span><?php echo sprintf( __( '%d quizzes', 'money-quiz' ), $stats['total_quizzes'] ); ?></span> •
                            <span><?php echo sprintf( __( '%d questions', 'money-quiz' ), $stats['total_questions'] ); ?></span> •
                            <span><?php echo sprintf( __( '%d archetypes', 'money-quiz' ), $stats['total_archetypes'] ); ?></span>
                        </div>
                    </td>
                </tr>
                
                <tr>
                    <th scope="row">
                        <label><?php _e( 'Export Format', 'money-quiz' ); ?></label>
                    </th>
                    <td>
                        <label>
                            <input type="radio" name="format" value="csv" checked />
                            <?php _e( 'CSV (Excel compatible)', 'money-quiz' ); ?>
                        </label><br>
                        
                        <label>
                            <input type="radio" name="format" value="json" />
                            <?php _e( 'JSON (for developers)', 'money-quiz' ); ?>
                        </label><br>
                        
                        <label>
                            <input type="radio" name="format" value="excel" />
                            <?php _e( 'Excel (XLS)', 'money-quiz' ); ?>
                        </label>
                    </td>
                </tr>
                
                <tr>
                    <th scope="row">
                        <label><?php _e( 'Export Options', 'money-quiz' ); ?></label>
                    </th>
                    <td>
                        <label>
                            <input type="checkbox" name="include_personal" value="1" />
                            <?php _e( 'Include personal information (email, name)', 'money-quiz' ); ?>
                        </label><br>
                        
                        <label>
                            <input type="checkbox" name="anonymize" value="1" />
                            <?php _e( 'Anonymize data (replace names with IDs)', 'money-quiz' ); ?>
                        </label>
                    </td>
                </tr>
            </table>
            
            <p class="submit">
                <input type="submit" class="button button-primary" value="<?php _e( 'Export Data', 'money-quiz' ); ?>" />
            </p>
        </form>
    </div>
    
    <!-- Import Section -->
    <div class="mq-card">
        <h2><?php _e( '📤 Import Data', 'money-quiz' ); ?></h2>
        <p><?php _e( 'Import prospects, quizzes, or questions from a CSV or JSON file.', 'money-quiz' ); ?></p>
        
        <form method="post" enctype="multipart/form-data">
            <?php wp_nonce_field( 'import_data' ); ?>
            <input type="hidden" name="import_action" value="1" />
            
            <table class="form-table">
                <tr>
                    <th scope="row">
                        <label for="import_type"><?php _e( 'Import Type', 'money-quiz' ); ?></label>
                    </th>
                    <td>
                        <select name="import_type" id="import_type">
                            <option value="prospects"><?php _e( 'Import Prospects/Leads', 'money-quiz' ); ?></option>
                            <option value="quizzes"><?php _e( 'Import Quizzes', 'money-quiz' ); ?></option>
                            <option value="questions"><?php _e( 'Import Questions', 'money-quiz' ); ?></option>
                        </select>
                    </td>
                </tr>
                
                <tr>
                    <th scope="row">
                        <label for="import_file"><?php _e( 'Select File', 'money-quiz' ); ?></label>
                    </th>
                    <td>
                        <input type="file" name="import_file" id="import_file" accept=".csv,.json" required />
                        <p class="description"><?php _e( 'Supported formats: CSV, JSON', 'money-quiz' ); ?></p>
                    </td>
                </tr>
                
                <tr>
                    <th scope="row">
                        <label><?php _e( 'Import Options', 'money-quiz' ); ?></label>
                    </th>
                    <td>
                        <label>
                            <input type="checkbox" name="skip_duplicates" value="1" checked />
                            <?php _e( 'Skip duplicate entries', 'money-quiz' ); ?>
                        </label><br>
                        
                        <label>
                            <input type="checkbox" name="update_existing" value="1" />
                            <?php _e( 'Update existing entries', 'money-quiz' ); ?>
                        </label>
                    </td>
                </tr>
            </table>
            
            <p class="submit">
                <input type="submit" class="button button-primary" value="<?php _e( 'Import Data', 'money-quiz' ); ?>" />
            </p>
        </form>
        
        <div class="import-templates">
            <h3><?php _e( 'Download Import Templates', 'money-quiz' ); ?></h3>
            <p><?php _e( 'Use these templates to format your data correctly for import:', 'money-quiz' ); ?></p>
            <a href="<?php echo plugins_url( 'templates/import-prospects.csv', dirname( __FILE__ ) ); ?>" class="button">
                <?php _e( 'Prospects Template', 'money-quiz' ); ?>
            </a>
            <a href="<?php echo plugins_url( 'templates/import-questions.csv', dirname( __FILE__ ) ); ?>" class="button">
                <?php _e( 'Questions Template', 'money-quiz' ); ?>
            </a>
        </div>
    </div>
    
    <!-- Scheduled Exports -->
    <div class="mq-card">
        <h2><?php _e( '⏰ Scheduled Exports', 'money-quiz' ); ?></h2>
        <p><?php _e( 'Set up automatic exports for regular backups.', 'money-quiz' ); ?></p>
        
        <table class="form-table">
            <tr>
                <th scope="row">
                    <label><?php _e( 'Enable Scheduled Export', 'money-quiz' ); ?></label>
                </th>
                <td>
                    <label>
                        <input type="checkbox" name="enable_scheduled" value="1" 
                               <?php checked( get_option( 'mq_scheduled_export_enabled' ) ); ?> />
                        <?php _e( 'Automatically export data on schedule', 'money-quiz' ); ?>
                    </label>
                </td>
            </tr>
            
            <tr>
                <th scope="row">
                    <label><?php _e( 'Schedule', 'money-quiz' ); ?></label>
                </th>
                <td>
                    <select name="export_schedule">
                        <option value="daily"><?php _e( 'Daily', 'money-quiz' ); ?></option>
                        <option value="weekly"><?php _e( 'Weekly', 'money-quiz' ); ?></option>
                        <option value="monthly"><?php _e( 'Monthly', 'money-quiz' ); ?></option>
                    </select>
                </td>
            </tr>
            
            <tr>
                <th scope="row">
                    <label><?php _e( 'Export Destination', 'money-quiz' ); ?></label>
                </th>
                <td>
                    <label>
                        <input type="radio" name="export_destination" value="email" checked />
                        <?php _e( 'Email to admin', 'money-quiz' ); ?>
                    </label><br>
                    
                    <label>
                        <input type="radio" name="export_destination" value="ftp" />
                        <?php _e( 'FTP/SFTP server', 'money-quiz' ); ?>
                    </label><br>
                    
                    <label>
                        <input type="radio" name="export_destination" value="cloud" />
                        <?php _e( 'Cloud storage (Dropbox, Google Drive)', 'money-quiz' ); ?>
                    </label>
                </td>
            </tr>
        </table>
        
        <p class="submit">
            <input type="submit" class="button" value="<?php _e( 'Save Schedule Settings', 'money-quiz' ); ?>" />
        </p>
    </div>
    
</div>

<style>
.export-stats {
    margin-top: 10px;
    font-size: 13px;
    color: #666;
}

.export-stats span {
    margin: 0 5px;
}

.import-templates {
    margin-top: 30px;
    padding-top: 20px;
    border-top: 1px solid #ddd;
}

.import-templates .button {
    margin-right: 10px;
}
</style>
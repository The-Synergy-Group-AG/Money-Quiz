<?php
/**
 * Money Quiz Plugin - Export/Import Service
 * Worker 10: Data Export/Import
 * 
 * Provides comprehensive data portability features including export to multiple
 * formats, bulk import capabilities, and data migration tools.
 * 
 * @package MoneyQuiz
 * @subpackage Services
 * @since 4.0.0
 */

namespace MoneyQuiz\Services;

use MoneyQuiz\Models\Prospect;
use MoneyQuiz\Models\QuizResult;
use MoneyQuiz\Models\Settings;
use MoneyQuiz\Utilities\SecurityUtil;
use MoneyQuiz\Utilities\FileUtil;
use MoneyQuiz\Utilities\DebugUtil;
use ZipArchive;
use Exception;

/**
 * Export/Import Service Class
 * 
 * Handles all data portability functionality
 */
class ExportImportService {
    
    /**
     * Database service
     * 
     * @var DatabaseService
     */
    protected $database;
    
    /**
     * Supported export formats
     * 
     * @var array
     */
    protected $export_formats = array(
        'csv' => 'CSV (Comma Separated Values)',
        'json' => 'JSON (JavaScript Object Notation)',
        'xml' => 'XML (Extensible Markup Language)',
        'excel' => 'Excel Spreadsheet',
        'pdf' => 'PDF Report',
        'sql' => 'SQL Database Dump'
    );
    
    /**
     * Export configuration
     * 
     * @var array
     */
    protected $export_config = array();
    
    /**
     * Import configuration
     * 
     * @var array
     */
    protected $import_config = array();
    
    /**
     * Constructor
     * 
     * @param DatabaseService $database
     */
    public function __construct( DatabaseService $database ) {
        $this->database = $database;
        
        // Initialize export/import directory
        $this->init_data_directory();
        
        // Register hooks
        add_action( 'init', array( $this, 'register_export_endpoints' ) );
        add_action( 'admin_post_money_quiz_export', array( $this, 'handle_export_request' ) );
        add_action( 'admin_post_money_quiz_import', array( $this, 'handle_import_request' ) );
        
        // Schedule cleanup
        if ( ! wp_next_scheduled( 'money_quiz_cleanup_exports' ) ) {
            wp_schedule_event( time(), 'daily', 'money_quiz_cleanup_exports' );
        }
        add_action( 'money_quiz_cleanup_exports', array( $this, 'cleanup_old_exports' ) );
    }
    
    /**
     * Export data
     * 
     * @param array $options Export options
     * @return array Export result with file info
     */
    public function export_data( array $options ) {
        $defaults = array(
            'format' => 'csv',
            'data_types' => array( 'prospects', 'results' ),
            'date_range' => array(),
            'filters' => array(),
            'include_personal_data' => false,
            'compress' => false,
            'split_files' => false,
            'max_records_per_file' => 10000
        );
        
        $options = wp_parse_args( $options, $defaults );
        $this->export_config = $options;
        
        // Validate options
        $this->validate_export_options( $options );
        
        // Check permissions
        if ( ! current_user_can( 'export' ) ) {
            throw new Exception( __( 'Insufficient permissions to export data', 'money-quiz' ) );
        }
        
        // Collect data
        $data = $this->collect_export_data( $options );
        
        // Generate export based on format
        $result = $this->generate_export( $data, $options );
        
        // Log export
        $this->log_export( $result, $options );
        
        return $result;
    }
    
    /**
     * Import data
     * 
     * @param string $file_path Path to import file
     * @param array  $options Import options
     * @return array Import result with statistics
     */
    public function import_data( $file_path, array $options = array() ) {
        $defaults = array(
            'format' => 'auto', // Auto-detect from file
            'mode' => 'append', // append, replace, update
            'mapping' => array(), // Field mapping
            'validate' => true,
            'batch_size' => 100,
            'skip_duplicates' => true,
            'update_existing' => false,
            'dry_run' => false
        );
        
        $options = wp_parse_args( $options, $defaults );
        $this->import_config = $options;
        
        // Check permissions
        if ( ! current_user_can( 'import' ) ) {
            throw new Exception( __( 'Insufficient permissions to import data', 'money-quiz' ) );
        }
        
        // Validate file
        $this->validate_import_file( $file_path );
        
        // Detect format if auto
        if ( $options['format'] === 'auto' ) {
            $options['format'] = $this->detect_file_format( $file_path );
        }
        
        // Parse file
        $data = $this->parse_import_file( $file_path, $options['format'] );
        
        // Validate data if requested
        if ( $options['validate'] ) {
            $validation_result = $this->validate_import_data( $data, $options );
            if ( ! $validation_result['valid'] ) {
                return array(
                    'success' => false,
                    'errors' => $validation_result['errors']
                );
            }
        }
        
        // Process import
        if ( ! $options['dry_run'] ) {
            $result = $this->process_import( $data, $options );
        } else {
            $result = $this->simulate_import( $data, $options );
        }
        
        // Log import
        $this->log_import( $result, $options );
        
        return $result;
    }
    
    /**
     * Export prospects
     * 
     * @param array $filters Filters to apply
     * @param string $format Export format
     * @return string File path
     */
    public function export_prospects( array $filters = array(), $format = 'csv' ) {
        $prospects = $this->get_filtered_prospects( $filters );
        
        $data = array();
        foreach ( $prospects as $prospect ) {
            $data[] = $this->prepare_prospect_export( $prospect );
        }
        
        return $this->create_export_file( $data, 'prospects', $format );
    }
    
    /**
     * Export quiz results
     * 
     * @param array $filters Filters to apply
     * @param string $format Export format
     * @return string File path
     */
    public function export_results( array $filters = array(), $format = 'csv' ) {
        $results = $this->get_filtered_results( $filters );
        
        $data = array();
        foreach ( $results as $result ) {
            $data[] = $this->prepare_result_export( $result );
        }
        
        return $this->create_export_file( $data, 'results', $format );
    }
    
    /**
     * Export analytics data
     * 
     * @param array $options Analytics export options
     * @return string File path
     */
    public function export_analytics( array $options = array() ) {
        $analytics_service = new AnalyticsService( $this->database );
        
        $data = array(
            'overview' => $analytics_service->get_dashboard_overview( $options ),
            'trends' => $analytics_service->get_trend_data( $options ),
            'demographics' => $analytics_service->get_demographics_data( $options ),
            'performance' => $analytics_service->get_performance_metrics( $options )
        );
        
        $format = $options['format'] ?? 'json';
        
        return $this->create_export_file( $data, 'analytics', $format );
    }
    
    /**
     * Bulk export all data
     * 
     * @param array $options Export options
     * @return string Archive file path
     */
    public function bulk_export( array $options = array() ) {
        $export_files = array();
        
        // Export each data type
        if ( in_array( 'prospects', $options['data_types'] ) ) {
            $export_files['prospects'] = $this->export_prospects( 
                $options['filters'] ?? array(), 
                'csv' 
            );
        }
        
        if ( in_array( 'results', $options['data_types'] ) ) {
            $export_files['results'] = $this->export_results( 
                $options['filters'] ?? array(), 
                'csv' 
            );
        }
        
        if ( in_array( 'settings', $options['data_types'] ) ) {
            $export_files['settings'] = $this->export_settings();
        }
        
        if ( in_array( 'analytics', $options['data_types'] ) ) {
            $export_files['analytics'] = $this->export_analytics( $options );
        }
        
        // Create archive
        $archive_path = $this->create_export_archive( $export_files, $options );
        
        // Clean up individual files
        foreach ( $export_files as $file ) {
            if ( file_exists( $file ) ) {
                unlink( $file );
            }
        }
        
        return $archive_path;
    }
    
    /**
     * Create export file
     * 
     * @param array  $data Data to export
     * @param string $type Data type
     * @param string $format Export format
     * @return string File path
     */
    protected function create_export_file( array $data, $type, $format ) {
        $filename = $this->generate_export_filename( $type, $format );
        $file_path = $this->get_export_path( $filename );
        
        switch ( $format ) {
            case 'csv':
                $this->write_csv_file( $file_path, $data );
                break;
                
            case 'json':
                $this->write_json_file( $file_path, $data );
                break;
                
            case 'xml':
                $this->write_xml_file( $file_path, $data, $type );
                break;
                
            case 'excel':
                $this->write_excel_file( $file_path, $data, $type );
                break;
                
            case 'pdf':
                $this->write_pdf_report( $file_path, $data, $type );
                break;
                
            default:
                throw new Exception( sprintf( 
                    __( 'Unsupported export format: %s', 'money-quiz' ), 
                    $format 
                ));
        }
        
        return $file_path;
    }
    
    /**
     * Write CSV file
     * 
     * @param string $file_path File path
     * @param array  $data Data to write
     */
    protected function write_csv_file( $file_path, array $data ) {
        $handle = fopen( $file_path, 'w' );
        
        if ( ! $handle ) {
            throw new Exception( __( 'Failed to create CSV file', 'money-quiz' ) );
        }
        
        // Write UTF-8 BOM for Excel compatibility
        fprintf( $handle, chr(0xEF) . chr(0xBB) . chr(0xBF) );
        
        // Write headers
        if ( ! empty( $data ) ) {
            fputcsv( $handle, array_keys( reset( $data ) ) );
        }
        
        // Write data
        foreach ( $data as $row ) {
            fputcsv( $handle, $row );
        }
        
        fclose( $handle );
    }
    
    /**
     * Write JSON file
     * 
     * @param string $file_path File path
     * @param array  $data Data to write
     */
    protected function write_json_file( $file_path, array $data ) {
        $json = json_encode( $data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE );
        
        if ( false === file_put_contents( $file_path, $json ) ) {
            throw new Exception( __( 'Failed to create JSON file', 'money-quiz' ) );
        }
    }
    
    /**
     * Write XML file
     * 
     * @param string $file_path File path
     * @param array  $data Data to write
     * @param string $root_element Root element name
     */
    protected function write_xml_file( $file_path, array $data, $root_element = 'data' ) {
        $xml = new \SimpleXMLElement( '<?xml version="1.0" encoding="UTF-8"?><' . $root_element . '/>' );
        
        $this->array_to_xml( $data, $xml );
        
        // Format output
        $dom = dom_import_simplexml( $xml )->ownerDocument;
        $dom->formatOutput = true;
        
        if ( false === $dom->save( $file_path ) ) {
            throw new Exception( __( 'Failed to create XML file', 'money-quiz' ) );
        }
    }
    
    /**
     * Write Excel file
     * 
     * @param string $file_path File path
     * @param array  $data Data to write
     * @param string $sheet_name Sheet name
     */
    protected function write_excel_file( $file_path, array $data, $sheet_name = 'Data' ) {
        if ( ! class_exists( 'PhpOffice\PhpSpreadsheet\Spreadsheet' ) ) {
            // Fall back to CSV if PHPSpreadsheet not available
            $csv_path = str_replace( '.xlsx', '.csv', $file_path );
            $this->write_csv_file( $csv_path, $data );
            rename( $csv_path, $file_path );
            return;
        }
        
        $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle( $sheet_name );
        
        // Write headers
        if ( ! empty( $data ) ) {
            $headers = array_keys( reset( $data ) );
            $col = 1;
            foreach ( $headers as $header ) {
                $sheet->setCellValueByColumnAndRow( $col, 1, $header );
                $col++;
            }
            
            // Style headers
            $sheet->getStyle( 'A1:' . $sheet->getHighestColumn() . '1' )
                ->getFont()->setBold( true );
        }
        
        // Write data
        $row = 2;
        foreach ( $data as $record ) {
            $col = 1;
            foreach ( $record as $value ) {
                $sheet->setCellValueByColumnAndRow( $col, $row, $value );
                $col++;
            }
            $row++;
        }
        
        // Auto-size columns
        foreach ( range( 'A', $sheet->getHighestColumn() ) as $column ) {
            $sheet->getColumnDimension( $column )->setAutoSize( true );
        }
        
        // Save file
        $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx( $spreadsheet );
        $writer->save( $file_path );
    }
    
    /**
     * Import prospects from file
     * 
     * @param string $file_path File path
     * @param array  $options Import options
     * @return array Import results
     */
    public function import_prospects( $file_path, array $options = array() ) {
        $options['data_type'] = 'prospects';
        
        return $this->import_data( $file_path, $options );
    }
    
    /**
     * Import quiz results
     * 
     * @param string $file_path File path
     * @param array  $options Import options
     * @return array Import results
     */
    public function import_results( $file_path, array $options = array() ) {
        $options['data_type'] = 'results';
        
        return $this->import_data( $file_path, $options );
    }
    
    /**
     * Process import data
     * 
     * @param array $data Parsed data
     * @param array $options Import options
     * @return array Import results
     */
    protected function process_import( array $data, array $options ) {
        $results = array(
            'total' => count( $data ),
            'imported' => 0,
            'updated' => 0,
            'skipped' => 0,
            'errors' => array()
        );
        
        // Start transaction for atomic import
        $this->database->start_transaction();
        
        try {
            // Process in batches
            $batches = array_chunk( $data, $options['batch_size'] );
            
            foreach ( $batches as $batch_index => $batch ) {
                foreach ( $batch as $row_index => $row ) {
                    $result = $this->import_single_record( $row, $options );
                    
                    switch ( $result['status'] ) {
                        case 'imported':
                            $results['imported']++;
                            break;
                        case 'updated':
                            $results['updated']++;
                            break;
                        case 'skipped':
                            $results['skipped']++;
                            break;
                        case 'error':
                            $results['errors'][] = array(
                                'row' => ( $batch_index * $options['batch_size'] ) + $row_index + 1,
                                'error' => $result['message']
                            );
                            break;
                    }
                }
                
                // Allow other processes to run
                if ( $batch_index % 10 === 0 ) {
                    $this->database->commit();
                    $this->database->start_transaction();
                }
            }
            
            $this->database->commit();
            
        } catch ( Exception $e ) {
            $this->database->rollback();
            throw $e;
        }
        
        return $results;
    }
    
    /**
     * Import single record
     * 
     * @param array $record Record data
     * @param array $options Import options
     * @return array Import result
     */
    protected function import_single_record( array $record, array $options ) {
        // Apply field mapping
        if ( ! empty( $options['mapping'] ) ) {
            $record = $this->apply_field_mapping( $record, $options['mapping'] );
        }
        
        // Sanitize data
        $record = $this->sanitize_import_data( $record );
        
        // Check for duplicates
        if ( $options['skip_duplicates'] || $options['update_existing'] ) {
            $existing = $this->find_existing_record( $record, $options['data_type'] );
            
            if ( $existing ) {
                if ( $options['skip_duplicates'] && ! $options['update_existing'] ) {
                    return array( 'status' => 'skipped', 'message' => 'Duplicate record' );
                }
                
                if ( $options['update_existing'] ) {
                    return $this->update_existing_record( $existing, $record, $options );
                }
            }
        }
        
        // Insert new record
        return $this->insert_new_record( $record, $options );
    }
    
    /**
     * Migrate data between versions
     * 
     * @param string $from_version Source version
     * @param string $to_version Target version
     * @return array Migration results
     */
    public function migrate_data( $from_version, $to_version ) {
        $migrations = $this->get_migration_steps( $from_version, $to_version );
        
        $results = array(
            'success' => true,
            'steps_completed' => 0,
            'messages' => array()
        );
        
        foreach ( $migrations as $migration ) {
            try {
                $step_result = $this->run_migration_step( $migration );
                $results['steps_completed']++;
                $results['messages'][] = sprintf(
                    __( 'Migration %s completed successfully', 'money-quiz' ),
                    $migration['version']
                );
            } catch ( Exception $e ) {
                $results['success'] = false;
                $results['messages'][] = sprintf(
                    __( 'Migration %s failed: %s', 'money-quiz' ),
                    $migration['version'],
                    $e->getMessage()
                );
                break;
            }
        }
        
        return $results;
    }
    
    /**
     * Create data backup
     * 
     * @param array $options Backup options
     * @return string Backup file path
     */
    public function create_backup( array $options = array() ) {
        $defaults = array(
            'include_uploads' => true,
            'include_settings' => true,
            'compress' => true,
            'encrypt' => false
        );
        
        $options = wp_parse_args( $options, $defaults );
        
        // Export all data
        $backup_data = array(
            'version' => MONEY_QUIZ_VERSION,
            'created_at' => current_time( 'mysql' ),
            'site_url' => get_site_url(),
            'data' => array()
        );
        
        // Export database tables
        $tables = $this->get_plugin_tables();
        foreach ( $tables as $table ) {
            $backup_data['data'][ $table ] = $this->export_table( $table );
        }
        
        // Export settings
        if ( $options['include_settings'] ) {
            $backup_data['settings'] = $this->export_all_settings();
        }
        
        // Create backup file
        $backup_file = $this->create_backup_file( $backup_data, $options );
        
        return $backup_file;
    }
    
    /**
     * Restore from backup
     * 
     * @param string $backup_file Backup file path
     * @param array  $options Restore options
     * @return array Restore results
     */
    public function restore_backup( $backup_file, array $options = array() ) {
        $defaults = array(
            'verify_version' => true,
            'clear_existing' => false,
            'dry_run' => false
        );
        
        $options = wp_parse_args( $options, $defaults );
        
        // Validate backup file
        $backup_data = $this->validate_backup_file( $backup_file );
        
        // Check version compatibility
        if ( $options['verify_version'] ) {
            $this->verify_backup_compatibility( $backup_data['version'] );
        }
        
        // Create restore point
        if ( ! $options['dry_run'] ) {
            $restore_point = $this->create_restore_point();
        }
        
        try {
            // Restore data
            $result = $this->restore_backup_data( $backup_data, $options );
            
            if ( ! $options['dry_run'] ) {
                // Clean up restore point on success
                $this->cleanup_restore_point( $restore_point );
            }
            
            return $result;
            
        } catch ( Exception $e ) {
            if ( ! $options['dry_run'] && isset( $restore_point ) ) {
                // Rollback to restore point
                $this->rollback_to_restore_point( $restore_point );
            }
            
            throw $e;
        }
    }
    
    /**
     * Generate export filename
     * 
     * @param string $type Data type
     * @param string $format File format
     * @return string Filename
     */
    protected function generate_export_filename( $type, $format ) {
        $date = date( 'Y-m-d-His' );
        $site_name = sanitize_file_name( get_bloginfo( 'name' ) );
        
        return sprintf( 'money-quiz-%s-%s-%s.%s', $site_name, $type, $date, $format );
    }
    
    /**
     * Get export directory path
     * 
     * @param string $filename Optional filename
     * @return string Full path
     */
    protected function get_export_path( $filename = '' ) {
        $upload_dir = wp_upload_dir();
        $export_dir = $upload_dir['basedir'] . '/money-quiz-exports';
        
        if ( ! file_exists( $export_dir ) ) {
            wp_mkdir_p( $export_dir );
            
            // Add .htaccess for security
            file_put_contents( $export_dir . '/.htaccess', 'deny from all' );
        }
        
        return $filename ? $export_dir . '/' . $filename : $export_dir;
    }
    
    /**
     * Initialize data directory
     */
    protected function init_data_directory() {
        $this->get_export_path();
    }
    
    /**
     * Clean up old export files
     */
    public function cleanup_old_exports() {
        $export_dir = $this->get_export_path();
        $max_age = apply_filters( 'money_quiz_export_retention_days', 7 ) * DAY_IN_SECONDS;
        
        if ( ! is_dir( $export_dir ) ) {
            return;
        }
        
        $files = glob( $export_dir . '/*' );
        $now = time();
        
        foreach ( $files as $file ) {
            if ( is_file( $file ) && ( $now - filemtime( $file ) ) > $max_age ) {
                unlink( $file );
            }
        }
    }
}

/**
 * Data Migration Manager
 * 
 * Handles version migrations and data transformations
 */
class DataMigrationManager {
    
    /**
     * Export/Import service
     * 
     * @var ExportImportService
     */
    protected $export_import_service;
    
    /**
     * Migration definitions
     * 
     * @var array
     */
    protected $migrations = array(
        '1.0.0' => array(
            'description' => 'Initial data structure',
            'handler' => 'migrate_to_1_0_0'
        ),
        '2.0.0' => array(
            'description' => 'Add analytics tables',
            'handler' => 'migrate_to_2_0_0'
        ),
        '3.0.0' => array(
            'description' => 'MVC architecture migration',
            'handler' => 'migrate_to_3_0_0'
        ),
        '4.0.0' => array(
            'description' => 'Modern features migration',
            'handler' => 'migrate_to_4_0_0'
        )
    );
    
    /**
     * Run pending migrations
     * 
     * @return array Migration results
     */
    public function run_migrations() {
        $current_version = get_option( 'money_quiz_db_version', '0.0.0' );
        $target_version = MONEY_QUIZ_VERSION;
        
        if ( version_compare( $current_version, $target_version, '>=' ) ) {
            return array(
                'success' => true,
                'message' => __( 'Database is already up to date', 'money-quiz' )
            );
        }
        
        $results = array(
            'success' => true,
            'migrations' => array()
        );
        
        foreach ( $this->migrations as $version => $migration ) {
            if ( version_compare( $current_version, $version, '<' ) && 
                 version_compare( $version, $target_version, '<=' ) ) {
                
                $result = $this->run_migration( $version, $migration );
                $results['migrations'][ $version ] = $result;
                
                if ( ! $result['success'] ) {
                    $results['success'] = false;
                    break;
                }
                
                update_option( 'money_quiz_db_version', $version );
            }
        }
        
        return $results;
    }
    
    /**
     * Run single migration
     * 
     * @param string $version Version number
     * @param array  $migration Migration config
     * @return array Migration result
     */
    protected function run_migration( $version, array $migration ) {
        $handler = $migration['handler'];
        
        if ( ! method_exists( $this, $handler ) ) {
            return array(
                'success' => false,
                'message' => sprintf( 
                    __( 'Migration handler %s not found', 'money-quiz' ), 
                    $handler 
                )
            );
        }
        
        try {
            $this->$handler();
            
            return array(
                'success' => true,
                'message' => sprintf(
                    __( 'Migration to version %s completed', 'money-quiz' ),
                    $version
                )
            );
            
        } catch ( Exception $e ) {
            return array(
                'success' => false,
                'message' => $e->getMessage()
            );
        }
    }
}

/**
 * Export/Import Manager UI
 * 
 * Handles admin interface for data portability
 */
class ExportImportManager {
    
    /**
     * Export/Import service
     * 
     * @var ExportImportService
     */
    protected $service;
    
    /**
     * Constructor
     * 
     * @param ExportImportService $service
     */
    public function __construct( ExportImportService $service ) {
        $this->service = $service;
        
        // Register hooks
        add_action( 'admin_menu', array( $this, 'add_menu_page' ) );
        add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
        
        // AJAX handlers
        add_action( 'wp_ajax_money_quiz_preview_export', array( $this, 'ajax_preview_export' ) );
        add_action( 'wp_ajax_money_quiz_validate_import', array( $this, 'ajax_validate_import' ) );
        add_action( 'wp_ajax_money_quiz_get_export_progress', array( $this, 'ajax_get_progress' ) );
    }
    
    /**
     * Add menu page
     */
    public function add_menu_page() {
        add_submenu_page(
            'money-quiz',
            __( 'Export/Import', 'money-quiz' ),
            __( 'Export/Import', 'money-quiz' ),
            'manage_options',
            'money-quiz-export-import',
            array( $this, 'render_page' )
        );
    }
    
    /**
     * Render export/import page
     */
    public function render_page() {
        ?>
        <div class="wrap money-quiz-export-import">
            <h1><?php _e( 'Data Export/Import', 'money-quiz' ); ?></h1>
            
            <div class="nav-tab-wrapper">
                <a href="#export" class="nav-tab nav-tab-active"><?php _e( 'Export', 'money-quiz' ); ?></a>
                <a href="#import" class="nav-tab"><?php _e( 'Import', 'money-quiz' ); ?></a>
                <a href="#backup" class="nav-tab"><?php _e( 'Backup', 'money-quiz' ); ?></a>
                <a href="#migrate" class="nav-tab"><?php _e( 'Migrate', 'money-quiz' ); ?></a>
            </div>
            
            <!-- Export Tab -->
            <div id="export-tab" class="tab-content">
                <?php $this->render_export_section(); ?>
            </div>
            
            <!-- Import Tab -->
            <div id="import-tab" class="tab-content" style="display: none;">
                <?php $this->render_import_section(); ?>
            </div>
            
            <!-- Backup Tab -->
            <div id="backup-tab" class="tab-content" style="display: none;">
                <?php $this->render_backup_section(); ?>
            </div>
            
            <!-- Migrate Tab -->
            <div id="migrate-tab" class="tab-content" style="display: none;">
                <?php $this->render_migrate_section(); ?>
            </div>
        </div>
        <?php
    }
    
    /**
     * Render export section
     */
    protected function render_export_section() {
        ?>
        <div class="export-section">
            <form method="post" action="<?php echo admin_url( 'admin-post.php' ); ?>" id="export-form">
                <?php wp_nonce_field( 'money_quiz_export', 'export_nonce' ); ?>
                <input type="hidden" name="action" value="money_quiz_export">
                
                <table class="form-table">
                    <tr>
                        <th scope="row"><?php _e( 'Export Type', 'money-quiz' ); ?></th>
                        <td>
                            <label>
                                <input type="checkbox" name="data_types[]" value="prospects" checked>
                                <?php _e( 'Prospects', 'money-quiz' ); ?>
                            </label><br>
                            <label>
                                <input type="checkbox" name="data_types[]" value="results" checked>
                                <?php _e( 'Quiz Results', 'money-quiz' ); ?>
                            </label><br>
                            <label>
                                <input type="checkbox" name="data_types[]" value="analytics">
                                <?php _e( 'Analytics Data', 'money-quiz' ); ?>
                            </label><br>
                            <label>
                                <input type="checkbox" name="data_types[]" value="settings">
                                <?php _e( 'Settings', 'money-quiz' ); ?>
                            </label>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row"><?php _e( 'Format', 'money-quiz' ); ?></th>
                        <td>
                            <select name="format" id="export-format">
                                <option value="csv"><?php _e( 'CSV', 'money-quiz' ); ?></option>
                                <option value="json"><?php _e( 'JSON', 'money-quiz' ); ?></option>
                                <option value="xml"><?php _e( 'XML', 'money-quiz' ); ?></option>
                                <option value="excel"><?php _e( 'Excel', 'money-quiz' ); ?></option>
                            </select>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row"><?php _e( 'Date Range', 'money-quiz' ); ?></th>
                        <td>
                            <input type="date" name="date_from" id="date-from">
                            <?php _e( 'to', 'money-quiz' ); ?>
                            <input type="date" name="date_to" id="date-to">
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row"><?php _e( 'Options', 'money-quiz' ); ?></th>
                        <td>
                            <label>
                                <input type="checkbox" name="include_personal_data" value="1">
                                <?php _e( 'Include personal data (GDPR)', 'money-quiz' ); ?>
                            </label><br>
                            <label>
                                <input type="checkbox" name="compress" value="1">
                                <?php _e( 'Compress files (ZIP)', 'money-quiz' ); ?>
                            </label>
                        </td>
                    </tr>
                </table>
                
                <p class="submit">
                    <button type="button" class="button" id="preview-export">
                        <?php _e( 'Preview', 'money-quiz' ); ?>
                    </button>
                    <button type="submit" class="button button-primary">
                        <?php _e( 'Export Data', 'money-quiz' ); ?>
                    </button>
                </p>
            </form>
            
            <div id="export-preview" style="display: none;">
                <!-- Preview content loaded via AJAX -->
            </div>
        </div>
        <?php
    }
}
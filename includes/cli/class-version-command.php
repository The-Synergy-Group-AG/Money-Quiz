<?php
/**
 * WP-CLI Command for Version Management
 * 
 * @package MoneyQuiz
 * @since 4.0.0
 */

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

if ( ! class_exists( 'WP_CLI' ) ) {
    return;
}

/**
 * Manage Money Quiz versions and reconciliation
 */
class Money_Quiz_Version_Command {
    
    /**
     * Check version alignment across all components
     * 
     * ## EXAMPLES
     * 
     *     wp money-quiz version check
     * 
     * @when after_wp_load
     */
    public function check() {
        WP_CLI::log( 'Checking Money Quiz version alignment...' );
        
        // Load required classes
        $this->load_dependencies();
        
        $version_manager = Money_Quiz_Version_Manager::instance();
        $report = $version_manager->get_version_report();
        
        WP_CLI::log( "\nVersion Report:" );
        WP_CLI::log( sprintf( 'Target Version: %s', $report['summary']['target_version'] ) );
        WP_CLI::log( sprintf( 'Version Sources: %d', $report['summary']['version_sources'] ) );
        WP_CLI::log( sprintf( 'Mismatches: %d', $report['summary']['mismatches'] ) );
        
        // Display version details
        WP_CLI::log( "\nDetected Versions:" );
        foreach ( $report['details'] as $source => $data ) {
            $status = $data['version'] === $report['summary']['target_version'] ? WP_CLI::colorize( '%g✓%n' ) : WP_CLI::colorize( '%r✗%n' );
            WP_CLI::log( sprintf( 
                '  %s %s: %s (confidence: %s)',
                $status,
                str_pad( ucwords( str_replace( '_', ' ', $source ) ), 20 ),
                str_pad( $data['version'] ?? 'unknown', 10 ),
                $data['confidence'] ?? 'unknown'
            ) );
        }
        
        // Display mismatches
        if ( ! empty( $report['mismatches'] ) ) {
            WP_CLI::log( "\nVersion Mismatches:" );
            foreach ( $report['mismatches'] as $mismatch ) {
                WP_CLI::warning( sprintf(
                    '%s vs %s: %s (severity: %s)',
                    $mismatch['sources'][0],
                    $mismatch['sources'][1],
                    implode( ' vs ', $mismatch['versions'] ),
                    $mismatch['severity']
                ) );
            }
        }
        
        if ( $report['summary']['needs_reconciliation'] ) {
            WP_CLI::warning( 'Version reconciliation is needed!' );
        } else {
            WP_CLI::success( 'All versions are aligned!' );
        }
    }
    
    /**
     * Reconcile version mismatches
     * 
     * ## OPTIONS
     * 
     * [--dry-run]
     * : Run without making changes
     * 
     * ## EXAMPLES
     * 
     *     wp money-quiz version reconcile
     *     wp money-quiz version reconcile --dry-run
     * 
     * @when after_wp_load
     */
    public function reconcile( $args, $assoc_args ) {
        $dry_run = WP_CLI\Utils\get_flag_value( $assoc_args, 'dry-run', false );
        
        WP_CLI::log( 'Starting version reconciliation...' );
        
        // Load required classes
        $this->load_dependencies();
        
        $version_manager = Money_Quiz_Version_Manager::instance();
        
        if ( $dry_run ) {
            WP_CLI::log( 'Running in dry-run mode (no changes will be made)' );
            $plan = $version_manager->get_reconciliation_plan();
            
            WP_CLI::log( "\nReconciliation Plan:" );
            foreach ( $plan['actions'] as $action ) {
                WP_CLI::log( sprintf(
                    '  - %s: %s %s → %s (priority: %s)',
                    ucfirst( $action['type'] ),
                    $action['source'],
                    $action['from_version'] ?? 'unknown',
                    $action['to_version'] ?? 'investigate',
                    $action['priority']
                ) );
            }
            
            return;
        }
        
        // Execute reconciliation
        WP_CLI::log( 'Executing reconciliation...' );
        $results = $version_manager->reconcile_versions();
        
        // Display results
        if ( ! empty( $results['success'] ) ) {
            WP_CLI::log( "\nSuccessful Actions:" );
            foreach ( $results['success'] as $success ) {
                WP_CLI::success( sprintf( '%s: %s', $success['action'], $success['source'] ) );
            }
        }
        
        if ( ! empty( $results['errors'] ) ) {
            WP_CLI::log( "\nErrors:" );
            foreach ( $results['errors'] as $error ) {
                WP_CLI::error( sprintf( '%s: %s', $error['action']['source'], $error['error'] ), false );
            }
        }
        
        if ( empty( $results['errors'] ) ) {
            WP_CLI::success( 'Version reconciliation completed successfully!' );
        } else {
            WP_CLI::warning( 'Version reconciliation completed with errors.' );
        }
    }
    
    /**
     * Run consistency check
     * 
     * ## OPTIONS
     * 
     * [--format=<format>]
     * : Output format (table, json, csv)
     * ---
     * default: table
     * ---
     * 
     * ## EXAMPLES
     * 
     *     wp money-quiz version consistency
     *     wp money-quiz version consistency --format=json
     * 
     * @when after_wp_load
     */
    public function consistency( $args, $assoc_args ) {
        $format = WP_CLI\Utils\get_flag_value( $assoc_args, 'format', 'table' );
        
        WP_CLI::log( 'Running consistency check...' );
        
        // Load required classes
        $this->load_dependencies();
        
        $consistency_checker = Money_Quiz_Version_Consistency_Checker::instance();
        $results = $consistency_checker->run_check();
        
        // Display summary
        WP_CLI::log( "\nConsistency Check Summary:" );
        WP_CLI::log( sprintf( 'Consistency Score: %d%%', $results['summary']['consistency_score'] ) );
        WP_CLI::log( sprintf( 'Total Checks: %d', $results['summary']['total_checks'] ) );
        WP_CLI::log( sprintf( 'Passed Checks: %d', $results['summary']['passed_checks'] ) );
        
        // Display issues
        if ( ! empty( $results['issues'] ) ) {
            WP_CLI::log( "\nIssues Found:" );
            
            $issue_data = array();
            foreach ( $results['issues'] as $issue ) {
                $issue_data[] = array(
                    'Type' => $issue['type'],
                    'Severity' => $issue['severity'],
                    'Description' => $issue['description'],
                );
            }
            
            WP_CLI\Utils\format_items( $format, $issue_data, array( 'Type', 'Severity', 'Description' ) );
        }
        
        // Display recommendations
        if ( ! empty( $results['recommendations'] ) ) {
            WP_CLI::log( "\nRecommendations:" );
            foreach ( $results['recommendations'] as $rec ) {
                WP_CLI::log( sprintf(
                    '  [%s] %s: %s',
                    strtoupper( $rec['priority'] ),
                    $rec['action'],
                    $rec['description']
                ) );
            }
        }
        
        if ( $results['summary']['critical_issues'] > 0 ) {
            WP_CLI::error( 'Critical issues detected!', false );
        } elseif ( $results['summary']['consistency_score'] < 80 ) {
            WP_CLI::warning( 'Consistency score is below 80%. Action recommended.' );
        } else {
            WP_CLI::success( 'System consistency is good!' );
        }
    }
    
    /**
     * Show database version info
     * 
     * ## EXAMPLES
     * 
     *     wp money-quiz version database
     * 
     * @when after_wp_load
     */
    public function database() {
        WP_CLI::log( 'Checking database version...' );
        
        // Load required classes
        $this->load_dependencies();
        
        $db_tracker = Money_Quiz_Database_Version_Tracker::instance();
        $current_version = $db_tracker->get_current_version();
        $integrity = $db_tracker->verify_integrity();
        
        WP_CLI::log( sprintf( "\nDatabase Version: %s", $current_version ) );
        WP_CLI::log( sprintf( 'Schema Valid: %s', $integrity['is_valid'] ? 'Yes' : 'No' ) );
        
        if ( ! empty( $integrity['issues'] ) ) {
            WP_CLI::log( "\nDatabase Issues:" );
            foreach ( $integrity['issues'] as $issue ) {
                WP_CLI::warning( sprintf(
                    '%s: %s %s (severity: %s)',
                    str_replace( '_', ' ', $issue['type'] ),
                    $issue['table'],
                    isset( $issue['column'] ) ? '.' . $issue['column'] : '',
                    $issue['severity']
                ) );
            }
            
            WP_CLI::log( "\nRun 'wp money-quiz version repair-database' to fix these issues." );
        } else {
            WP_CLI::success( 'Database schema is valid!' );
        }
    }
    
    /**
     * Repair database issues
     * 
     * ## OPTIONS
     * 
     * [--yes]
     * : Skip confirmation
     * 
     * ## EXAMPLES
     * 
     *     wp money-quiz version repair-database
     *     wp money-quiz version repair-database --yes
     * 
     * @when after_wp_load
     */
    public function repair_database( $args, $assoc_args ) {
        $skip_confirm = WP_CLI\Utils\get_flag_value( $assoc_args, 'yes', false );
        
        if ( ! $skip_confirm ) {
            WP_CLI::confirm( 'Are you sure you want to repair the database? This action cannot be undone.' );
        }
        
        WP_CLI::log( 'Repairing database...' );
        
        // Load required classes
        $this->load_dependencies();
        
        $db_tracker = Money_Quiz_Database_Version_Tracker::instance();
        $results = $db_tracker->repair_database();
        
        if ( ! empty( $results['repaired'] ) ) {
            WP_CLI::log( "\nRepaired Issues:" );
            foreach ( $results['repaired'] as $repair ) {
                WP_CLI::success( sprintf(
                    'Fixed %s: %s %s',
                    str_replace( '_', ' ', $repair['type'] ),
                    $repair['table'],
                    isset( $repair['column'] ) ? '.' . $repair['column'] : ''
                ) );
            }
        }
        
        if ( ! empty( $results['failed'] ) ) {
            WP_CLI::log( "\nFailed Repairs:" );
            foreach ( $results['failed'] as $failure ) {
                WP_CLI::error( sprintf(
                    'Failed to fix %s: %s - %s',
                    str_replace( '_', ' ', $failure['type'] ),
                    $failure['table'],
                    $failure['error']
                ), false );
            }
        }
        
        if ( empty( $results['failed'] ) ) {
            WP_CLI::success( 'Database repair completed successfully!' );
        } else {
            WP_CLI::warning( 'Database repair completed with some failures.' );
        }
    }
    
    /**
     * Show migration history
     * 
     * ## OPTIONS
     * 
     * [--limit=<limit>]
     * : Number of records to show
     * ---
     * default: 10
     * ---
     * 
     * ## EXAMPLES
     * 
     *     wp money-quiz version history
     *     wp money-quiz version history --limit=20
     * 
     * @when after_wp_load
     */
    public function history( $args, $assoc_args ) {
        $limit = (int) WP_CLI\Utils\get_flag_value( $assoc_args, 'limit', 10 );
        
        WP_CLI::log( 'Fetching version history...' );
        
        // Load required classes
        $this->load_dependencies();
        
        $db_tracker = Money_Quiz_Database_Version_Tracker::instance();
        $history = $db_tracker->get_version_history( null, $limit );
        
        if ( empty( $history ) ) {
            WP_CLI::log( 'No version history found.' );
            return;
        }
        
        $history_data = array();
        foreach ( $history as $record ) {
            $history_data[] = array(
                'Date' => $record->migrated_at,
                'Component' => $record->component,
                'From' => $record->previous_version ?: '-',
                'To' => $record->version,
                'Status' => isset( $record->migration_data['status'] ) ? $record->migration_data['status'] : 'completed',
            );
        }
        
        WP_CLI\Utils\format_items( 'table', $history_data, array( 'Date', 'Component', 'From', 'To', 'Status' ) );
    }
    
    /**
     * Load required dependencies
     */
    private function load_dependencies() {
        $files = array(
            'includes/class-version-manager.php',
            'includes/class-version-migration.php',
            'includes/class-database-version-tracker.php',
            'includes/class-version-consistency-checker.php',
            'includes/class-version-reconciliation-init.php',
        );
        
        foreach ( $files as $file ) {
            $file_path = MONEY_QUIZ_PLUGIN_DIR . $file;
            if ( file_exists( $file_path ) ) {
                require_once $file_path;
            }
        }
    }
}

// Register command
WP_CLI::add_command( 'money-quiz version', 'Money_Quiz_Version_Command' );
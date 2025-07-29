<?php
/**
 * Version Consistency Checker for Money Quiz
 * 
 * Monitors and ensures version consistency across all plugin components
 * 
 * @package MoneyQuiz
 * @since 4.0.0
 */

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Version Consistency Checker Class
 */
class Money_Quiz_Version_Consistency_Checker {
    
    /**
     * @var Money_Quiz_Version_Consistency_Checker
     */
    private static $instance = null;
    
    /**
     * @var array Consistency check results
     */
    private $check_results = array();
    
    /**
     * @var array Critical components that must be version-aligned
     */
    private $critical_components = array(
        'plugin_header',
        'database_schema',
        'core_classes',
    );
    
    /**
     * @var array Version dependencies
     */
    private $version_dependencies = array(
        '4.0.0' => array(
            'php' => '7.4',
            'wordpress' => '5.8',
            'mysql' => '5.6',
        ),
        '3.3.0' => array(
            'php' => '7.2',
            'wordpress' => '5.0',
            'mysql' => '5.5',
        ),
        '2.0.0' => array(
            'php' => '7.0',
            'wordpress' => '4.7',
            'mysql' => '5.0',
        ),
    );
    
    /**
     * Get instance
     */
    public static function instance() {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Constructor
     */
    private function __construct() {
        // Initialize
    }
    
    /**
     * Run consistency check
     */
    public function run_check() {
        $this->check_results = array(
            'timestamp' => current_time( 'mysql' ),
            'checks' => array(),
            'issues' => array(),
            'recommendations' => array(),
        );
        
        // Run various checks
        $this->check_version_alignment();
        $this->check_environment_compatibility();
        $this->check_file_integrity();
        $this->check_database_integrity();
        $this->check_dependencies();
        $this->check_migration_status();
        
        // Analyze results
        $this->analyze_results();
        
        return $this->check_results;
    }
    
    /**
     * Check version alignment
     */
    private function check_version_alignment() {
        $version_manager = Money_Quiz_Version_Manager::instance();
        $versions = $version_manager->detect_versions();
        $mismatches = $version_manager->get_mismatches();
        
        $this->check_results['checks']['version_alignment'] = array(
            'status' => empty( $mismatches ) ? 'pass' : 'fail',
            'versions' => $versions,
            'mismatches' => $mismatches,
        );
        
        if ( ! empty( $mismatches ) ) {
            foreach ( $mismatches as $mismatch ) {
                $this->add_issue(
                    'version_mismatch',
                    $mismatch['severity'],
                    sprintf(
                        'Version mismatch between %s (%s) and %s (%s)',
                        $mismatch['sources'][0],
                        $mismatch['versions'][0],
                        $mismatch['sources'][1],
                        $mismatch['versions'][1]
                    )
                );
            }
        }
    }
    
    /**
     * Check environment compatibility
     */
    private function check_environment_compatibility() {
        $current_version = defined( 'MONEY_QUIZ_VERSION' ) ? MONEY_QUIZ_VERSION : '4.0.0';
        $requirements = $this->version_dependencies[ $current_version ] ?? $this->version_dependencies['4.0.0'];
        
        $compatibility = array(
            'php' => array(
                'required' => $requirements['php'],
                'current' => PHP_VERSION,
                'compatible' => version_compare( PHP_VERSION, $requirements['php'], '>=' ),
            ),
            'wordpress' => array(
                'required' => $requirements['wordpress'],
                'current' => get_bloginfo( 'version' ),
                'compatible' => version_compare( get_bloginfo( 'version' ), $requirements['wordpress'], '>=' ),
            ),
            'mysql' => array(
                'required' => $requirements['mysql'],
                'current' => $this->get_mysql_version(),
                'compatible' => version_compare( $this->get_mysql_version(), $requirements['mysql'], '>=' ),
            ),
        );
        
        $all_compatible = true;
        foreach ( $compatibility as $component => $check ) {
            if ( ! $check['compatible'] ) {
                $all_compatible = false;
                $this->add_issue(
                    'environment_incompatible',
                    'high',
                    sprintf(
                        '%s version %s is below required %s',
                        ucfirst( $component ),
                        $check['current'],
                        $check['required']
                    )
                );
            }
        }
        
        $this->check_results['checks']['environment_compatibility'] = array(
            'status' => $all_compatible ? 'pass' : 'fail',
            'details' => $compatibility,
        );
    }
    
    /**
     * Check file integrity
     */
    private function check_file_integrity() {
        $required_files = array(
            'money-quiz.php' => 'critical',
            'includes/class-upgrade-handler.php' => 'critical',
            'includes/class-version-manager.php' => 'high',
            'includes/class-version-migration.php' => 'high',
            'includes/class-database-version-tracker.php' => 'high',
        );
        
        $missing_files = array();
        $file_issues = array();
        
        foreach ( $required_files as $file => $importance ) {
            $file_path = MONEY_QUIZ_PLUGIN_DIR . $file;
            
            if ( ! file_exists( $file_path ) ) {
                $missing_files[] = $file;
                $this->add_issue(
                    'missing_file',
                    $importance === 'critical' ? 'critical' : 'high',
                    "Required file missing: $file"
                );
            } else {
                // Check file readability
                if ( ! is_readable( $file_path ) ) {
                    $file_issues[] = $file;
                    $this->add_issue(
                        'file_not_readable',
                        'high',
                        "File not readable: $file"
                    );
                }
                
                // Check file size (potential corruption)
                $size = filesize( $file_path );
                if ( $size === 0 ) {
                    $file_issues[] = $file;
                    $this->add_issue(
                        'empty_file',
                        'high',
                        "File is empty: $file"
                    );
                }
            }
        }
        
        $this->check_results['checks']['file_integrity'] = array(
            'status' => empty( $missing_files ) && empty( $file_issues ) ? 'pass' : 'fail',
            'missing_files' => $missing_files,
            'file_issues' => $file_issues,
        );
    }
    
    /**
     * Check database integrity
     */
    private function check_database_integrity() {
        $db_tracker = Money_Quiz_Database_Version_Tracker::instance();
        $integrity = $db_tracker->verify_integrity();
        
        $this->check_results['checks']['database_integrity'] = array(
            'status' => $integrity['is_valid'] ? 'pass' : 'fail',
            'version' => $integrity['version'],
            'issues' => $integrity['issues'],
        );
        
        foreach ( $integrity['issues'] as $issue ) {
            $this->add_issue(
                'database_' . $issue['type'],
                $issue['severity'],
                sprintf(
                    'Database %s: %s %s',
                    str_replace( '_', ' ', $issue['type'] ),
                    $issue['table'],
                    isset( $issue['column'] ) ? '.' . $issue['column'] : ''
                )
            );
        }
    }
    
    /**
     * Check dependencies
     */
    private function check_dependencies() {
        $dependencies = array(
            'composer' => array(
                'file' => 'vendor/autoload.php',
                'importance' => 'medium',
            ),
            'legacy_integration' => array(
                'file' => 'includes/class-legacy-integration.php',
                'importance' => 'high',
            ),
        );
        
        $missing_dependencies = array();
        
        foreach ( $dependencies as $name => $dependency ) {
            $file_path = MONEY_QUIZ_PLUGIN_DIR . $dependency['file'];
            
            if ( ! file_exists( $file_path ) ) {
                $missing_dependencies[] = $name;
                $this->add_issue(
                    'missing_dependency',
                    $dependency['importance'],
                    "Missing dependency: $name"
                );
            }
        }
        
        // Check PHP extensions
        $required_extensions = array( 'mysqli', 'json', 'mbstring' );
        $missing_extensions = array();
        
        foreach ( $required_extensions as $extension ) {
            if ( ! extension_loaded( $extension ) ) {
                $missing_extensions[] = $extension;
                $this->add_issue(
                    'missing_php_extension',
                    'high',
                    "Missing PHP extension: $extension"
                );
            }
        }
        
        $this->check_results['checks']['dependencies'] = array(
            'status' => empty( $missing_dependencies ) && empty( $missing_extensions ) ? 'pass' : 'fail',
            'missing_dependencies' => $missing_dependencies,
            'missing_extensions' => $missing_extensions,
        );
    }
    
    /**
     * Check migration status
     */
    private function check_migration_status() {
        global $wpdb;
        
        // Check if there are pending migrations
        $current_version = get_option( 'money_quiz_version', '0' );
        $target_version = defined( 'MONEY_QUIZ_VERSION' ) ? MONEY_QUIZ_VERSION : '4.0.0';
        
        $needs_migration = version_compare( $current_version, $target_version, '<' );
        
        // Check for incomplete migrations
        $incomplete_migrations = array();
        
        $migration_table = $wpdb->prefix . 'mq_version_history';
        if ( $wpdb->get_var( "SHOW TABLES LIKE '$migration_table'" ) ) {
            $incomplete = $wpdb->get_results(
                "SELECT * FROM $migration_table 
                WHERE migration_data LIKE '%\"status\":\"incomplete\"%' 
                ORDER BY migrated_at DESC"
            );
            
            foreach ( $incomplete as $migration ) {
                $incomplete_migrations[] = array(
                    'version' => $migration->version,
                    'component' => $migration->component,
                    'date' => $migration->migrated_at,
                );
            }
        }
        
        $this->check_results['checks']['migration_status'] = array(
            'status' => ! $needs_migration && empty( $incomplete_migrations ) ? 'pass' : 'fail',
            'current_version' => $current_version,
            'target_version' => $target_version,
            'needs_migration' => $needs_migration,
            'incomplete_migrations' => $incomplete_migrations,
        );
        
        if ( $needs_migration ) {
            $this->add_issue(
                'pending_migration',
                'high',
                sprintf(
                    'Migration needed from %s to %s',
                    $current_version,
                    $target_version
                )
            );
        }
        
        foreach ( $incomplete_migrations as $migration ) {
            $this->add_issue(
                'incomplete_migration',
                'critical',
                sprintf(
                    'Incomplete migration for %s to version %s',
                    $migration['component'],
                    $migration['version']
                )
            );
        }
    }
    
    /**
     * Analyze results and generate recommendations
     */
    private function analyze_results() {
        $critical_issues = 0;
        $high_issues = 0;
        $medium_issues = 0;
        $low_issues = 0;
        
        foreach ( $this->check_results['issues'] as $issue ) {
            switch ( $issue['severity'] ) {
                case 'critical':
                    $critical_issues++;
                    break;
                case 'high':
                    $high_issues++;
                    break;
                case 'medium':
                    $medium_issues++;
                    break;
                case 'low':
                    $low_issues++;
                    break;
            }
        }
        
        $this->check_results['summary'] = array(
            'total_checks' => count( $this->check_results['checks'] ),
            'passed_checks' => count( array_filter( $this->check_results['checks'], function( $check ) {
                return $check['status'] === 'pass';
            } ) ),
            'critical_issues' => $critical_issues,
            'high_issues' => $high_issues,
            'medium_issues' => $medium_issues,
            'low_issues' => $low_issues,
            'consistency_score' => $this->calculate_consistency_score(),
        );
        
        // Generate recommendations
        $this->generate_recommendations();
    }
    
    /**
     * Calculate consistency score
     */
    private function calculate_consistency_score() {
        $total_checks = count( $this->check_results['checks'] );
        $passed_checks = count( array_filter( $this->check_results['checks'], function( $check ) {
            return $check['status'] === 'pass';
        } ) );
        
        if ( $total_checks === 0 ) {
            return 0;
        }
        
        $base_score = ( $passed_checks / $total_checks ) * 100;
        
        // Deduct points for issues
        foreach ( $this->check_results['issues'] as $issue ) {
            switch ( $issue['severity'] ) {
                case 'critical':
                    $base_score -= 20;
                    break;
                case 'high':
                    $base_score -= 10;
                    break;
                case 'medium':
                    $base_score -= 5;
                    break;
                case 'low':
                    $base_score -= 2;
                    break;
            }
        }
        
        return max( 0, min( 100, round( $base_score ) ) );
    }
    
    /**
     * Generate recommendations
     */
    private function generate_recommendations() {
        $recommendations = array();
        
        // Check for critical issues
        if ( $this->check_results['summary']['critical_issues'] > 0 ) {
            $recommendations[] = array(
                'priority' => 'immediate',
                'action' => 'Fix critical issues',
                'description' => 'Address all critical issues immediately to prevent plugin malfunction.',
            );
        }
        
        // Version alignment
        if ( $this->check_results['checks']['version_alignment']['status'] === 'fail' ) {
            $recommendations[] = array(
                'priority' => 'high',
                'action' => 'Align versions',
                'description' => 'Run version reconciliation to align all component versions.',
            );
        }
        
        // Database integrity
        if ( $this->check_results['checks']['database_integrity']['status'] === 'fail' ) {
            $recommendations[] = array(
                'priority' => 'high',
                'action' => 'Repair database',
                'description' => 'Run database repair to fix schema issues.',
            );
        }
        
        // Pending migrations
        if ( isset( $this->check_results['checks']['migration_status']['needs_migration'] ) &&
             $this->check_results['checks']['migration_status']['needs_migration'] ) {
            $recommendations[] = array(
                'priority' => 'high',
                'action' => 'Run migrations',
                'description' => 'Execute pending migrations to update to the latest version.',
            );
        }
        
        // Environment compatibility
        if ( $this->check_results['checks']['environment_compatibility']['status'] === 'fail' ) {
            $recommendations[] = array(
                'priority' => 'medium',
                'action' => 'Update environment',
                'description' => 'Update PHP, WordPress, or MySQL to meet minimum requirements.',
            );
        }
        
        // Missing dependencies
        if ( $this->check_results['checks']['dependencies']['status'] === 'fail' ) {
            $recommendations[] = array(
                'priority' => 'medium',
                'action' => 'Install dependencies',
                'description' => 'Install missing dependencies using Composer or enable required PHP extensions.',
            );
        }
        
        $this->check_results['recommendations'] = $recommendations;
    }
    
    /**
     * Add issue to results
     */
    private function add_issue( $type, $severity, $description ) {
        $this->check_results['issues'][] = array(
            'type' => $type,
            'severity' => $severity,
            'description' => $description,
            'detected_at' => current_time( 'mysql' ),
        );
    }
    
    /**
     * Get MySQL version
     */
    private function get_mysql_version() {
        global $wpdb;
        $version = $wpdb->get_var( "SELECT VERSION()" );
        
        // Extract version number
        if ( preg_match( '/^(\d+\.\d+\.\d+)/', $version, $matches ) ) {
            return $matches[1];
        }
        
        return '5.0.0'; // Default fallback
    }
    
    /**
     * Schedule periodic checks
     */
    public function schedule_checks() {
        if ( ! wp_next_scheduled( 'money_quiz_version_consistency_check' ) ) {
            wp_schedule_event( time(), 'daily', 'money_quiz_version_consistency_check' );
        }
        
        add_action( 'money_quiz_version_consistency_check', array( $this, 'scheduled_check' ) );
    }
    
    /**
     * Run scheduled check
     */
    public function scheduled_check() {
        $results = $this->run_check();
        
        // Log results
        if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
            error_log( sprintf(
                '[Money Quiz Consistency Check] Score: %d%%, Issues: %d critical, %d high',
                $results['summary']['consistency_score'],
                $results['summary']['critical_issues'],
                $results['summary']['high_issues']
            ) );
        }
        
        // Store results
        update_option( 'money_quiz_last_consistency_check', $results );
        
        // Send admin notification if critical issues
        if ( $results['summary']['critical_issues'] > 0 ) {
            $this->send_admin_notification( $results );
        }
    }
    
    /**
     * Send admin notification
     */
    private function send_admin_notification( $results ) {
        $admin_email = get_option( 'admin_email' );
        $subject = sprintf(
            '[%s] Money Quiz: Critical Version Consistency Issues',
            get_bloginfo( 'name' )
        );
        
        $message = "Critical version consistency issues have been detected in the Money Quiz plugin.\n\n";
        $message .= sprintf( "Consistency Score: %d%%\n", $results['summary']['consistency_score'] );
        $message .= sprintf( "Critical Issues: %d\n", $results['summary']['critical_issues'] );
        $message .= sprintf( "High Priority Issues: %d\n\n", $results['summary']['high_issues'] );
        
        $message .= "Critical Issues:\n";
        foreach ( $results['issues'] as $issue ) {
            if ( $issue['severity'] === 'critical' ) {
                $message .= "- " . $issue['description'] . "\n";
            }
        }
        
        $message .= "\nPlease log in to your WordPress admin panel to address these issues.";
        
        wp_mail( $admin_email, $subject, $message );
    }
    
    /**
     * Get last check results
     */
    public function get_last_check_results() {
        return get_option( 'money_quiz_last_consistency_check', array() );
    }
}
<?php
/**
 * Version Manager
 * 
 * Handles version reconciliation and upgrades
 * 
 * @package MoneyQuiz
 * @since 4.1.0
 */

namespace MoneyQuiz\Core;

class Version_Manager {
    
    /**
     * Current plugin version (source of truth)
     */
    const CURRENT_VERSION = '4.1.0';
    
    /**
     * Version history for migration
     */
    const VERSION_HISTORY = [
        '1.0' => 'initial_release',
        '1.4' => 'added_archetypes', 
        '2.0' => 'ui_improvements',
        '3.0' => 'security_updates',
        '3.3' => 'legacy_final',
        '4.0' => 'modern_architecture',
        '4.1' => 'safety_integration'
    ];
    
    /**
     * @var array Version sources to check
     */
    private $version_sources = [
        'plugin_header' => null,
        'constant' => null,
        'database' => null,
        'legacy_db' => null
    ];
    
    /**
     * Initialize version manager
     */
    public function init() {
        $this->detect_versions();
        $this->reconcile_versions();
        $this->maybe_run_upgrades();
    }
    
    /**
     * Detect all version numbers in the system
     */
    private function detect_versions() {
        // Get plugin header version
        if ( ! function_exists( 'get_plugin_data' ) ) {
            require_once ABSPATH . 'wp-admin/includes/plugin.php';
        }
        $plugin_data = get_plugin_data( MONEY_QUIZ_PLUGIN_FILE );
        $this->version_sources['plugin_header'] = $plugin_data['Version'] ?? null;
        
        // Get constant version
        if ( defined( 'MONEYQUIZ_VERSION' ) ) {
            $this->version_sources['constant'] = MONEYQUIZ_VERSION;
        }
        
        // Get database versions
        $this->version_sources['database'] = get_option( 'money_quiz_version', null );
        $this->version_sources['legacy_db'] = get_option( 'mq_money_coach_plugin_version', null );
    }
    
    /**
     * Reconcile different version numbers
     */
    private function reconcile_versions() {
        $versions = array_filter( $this->version_sources );
        
        if ( empty( $versions ) ) {
            // Fresh installation
            $this->set_unified_version( self::CURRENT_VERSION );
            return;
        }
        
        // Find the highest version (most likely the real one)
        $highest_version = '0';
        foreach ( $versions as $source => $version ) {
            if ( version_compare( $version, $highest_version, '>' ) ) {
                $highest_version = $version;
            }
        }
        
        // Log version discrepancies
        if ( count( array_unique( $versions ) ) > 1 ) {
            $this->log_version_mismatch( $versions );
        }
        
        // Update all version locations to match
        $this->set_unified_version( self::CURRENT_VERSION );
    }
    
    /**
     * Set unified version across all storage locations
     */
    private function set_unified_version( $version ) {
        // Update database options
        update_option( 'money_quiz_version', $version );
        update_option( 'mq_money_coach_plugin_version', $version );
        
        // Store reconciliation timestamp
        update_option( 'money_quiz_version_reconciled', current_time( 'mysql' ) );
    }
    
    /**
     * Check if upgrades are needed and run them
     */
    private function maybe_run_upgrades() {
        $last_version = get_option( 'money_quiz_last_upgraded_from', '0' );
        $current_version = self::CURRENT_VERSION;
        
        if ( version_compare( $last_version, $current_version, '<' ) ) {
            $this->run_version_upgrades( $last_version, $current_version );
        }
    }
    
    /**
     * Run version-specific upgrades
     */
    private function run_version_upgrades( $from_version, $to_version ) {
        $upgrade_paths = [
            '1.4' => [ $this, 'upgrade_to_2_0' ],
            '2.0' => [ $this, 'upgrade_to_3_0' ],
            '3.0' => [ $this, 'upgrade_to_3_3' ],
            '3.3' => [ $this, 'upgrade_to_4_0' ],
            '4.0' => [ $this, 'upgrade_to_4_1' ],
        ];
        
        foreach ( $upgrade_paths as $version => $callback ) {
            if ( version_compare( $from_version, $version, '<' ) && 
                 version_compare( $version, $to_version, '<=' ) ) {
                call_user_func( $callback );
            }
        }
        
        // Update last upgraded from version
        update_option( 'money_quiz_last_upgraded_from', $from_version );
    }
    
    /**
     * Upgrade to version 2.0
     */
    private function upgrade_to_2_0() {
        // Add any missing database columns
        global $wpdb;
        $table = $wpdb->prefix . 'mq_prospects';
        
        // Check if company column exists
        $column_exists = $wpdb->get_results( 
            $wpdb->prepare( 
                "SHOW COLUMNS FROM `$table` LIKE %s", 
                'company' 
            ) 
        );
        
        if ( empty( $column_exists ) ) {
            $wpdb->query( "ALTER TABLE `$table` ADD COLUMN `company` VARCHAR(255) NULL AFTER `email`" );
        }
    }
    
    /**
     * Upgrade to version 3.0
     */
    private function upgrade_to_3_0() {
        // Security improvements - ensure indexes exist
        global $wpdb;
        
        // Add index on email for prospects table
        $table = $wpdb->prefix . 'mq_prospects';
        $wpdb->query( "ALTER TABLE `$table` ADD INDEX `idx_email` (`email`)" );
    }
    
    /**
     * Upgrade to version 3.3
     */
    private function upgrade_to_3_3() {
        // Clean up old transients
        delete_transient( 'mq_cache_timeout' );
        delete_transient( 'mq_question_cache' );
    }
    
    /**
     * Upgrade to version 4.0
     */
    private function upgrade_to_4_0() {
        // Modern architecture migration
        // This is already handled by the Migrator class
        if ( class_exists( '\MoneyQuiz\Database\Migrator' ) ) {
            $migrator = new \MoneyQuiz\Database\Migrator();
            $migrator->run();
        }
    }
    
    /**
     * Upgrade to version 4.1
     */
    private function upgrade_to_4_1() {
        // Add safety features
        update_option( 'money_quiz_safety_enabled', true );
        update_option( 'money_quiz_legacy_mode', 'hybrid' );
    }
    
    /**
     * Log version mismatches for debugging
     */
    private function log_version_mismatch( $versions ) {
        $log_entry = [
            'detected_at' => current_time( 'mysql' ),
            'versions' => $versions,
            'reconciled_to' => self::CURRENT_VERSION
        ];
        
        $version_log = get_option( 'money_quiz_version_log', [] );
        $version_log[] = $log_entry;
        
        // Keep only last 10 entries
        if ( count( $version_log ) > 10 ) {
            $version_log = array_slice( $version_log, -10 );
        }
        
        update_option( 'money_quiz_version_log', $version_log );
    }
    
    /**
     * Get version report
     */
    public function get_version_report() {
        return [
            'current_version' => self::CURRENT_VERSION,
            'detected_versions' => $this->version_sources,
            'version_log' => get_option( 'money_quiz_version_log', [] ),
            'last_reconciled' => get_option( 'money_quiz_version_reconciled', 'never' ),
            'last_upgraded_from' => get_option( 'money_quiz_last_upgraded_from', 'unknown' )
        ];
    }
    
    /**
     * Force version reconciliation
     */
    public function force_reconciliation() {
        $this->detect_versions();
        $this->reconcile_versions();
        return $this->get_version_report();
    }
}
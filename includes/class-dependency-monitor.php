<?php
/**
 * Dependency Monitor for Money Quiz Safe Wrapper
 */

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Dependency Monitor Class
 */
class MoneyQuiz_Dependency_Monitor {
    
    /**
     * @var MoneyQuiz_Notice_Manager
     */
    private $notice_manager;
    
    /**
     * @var array Dependency check results
     */
    private $check_results = array();
    
    /**
     * Constructor
     */
    public function __construct( MoneyQuiz_Notice_Manager $notice_manager ) {
        $this->notice_manager = $notice_manager;
        
        add_action( 'admin_init', array( $this, 'check_dependencies' ) );
        add_action( 'wp_ajax_mq_install_composer', array( $this, 'ajax_install_composer' ) );
        
        // Schedule periodic checks
        if ( ! wp_next_scheduled( 'mq_check_dependencies' ) ) {
            wp_schedule_event( time(), 'hourly', 'mq_check_dependencies' );
        }
        add_action( 'mq_check_dependencies', array( $this, 'check_dependencies' ) );
    }
    
    /**
     * Check all dependencies
     */
    public function check_dependencies() {
        $checks = array(
            'php_version' => $this->check_php_version(),
            'wordpress_version' => $this->check_wordpress_version(),
            'required_extensions' => $this->check_php_extensions(),
            'composer_dependencies' => $this->check_composer(),
            'critical_files' => $this->check_critical_files(),
            'write_permissions' => $this->check_write_permissions(),
            'memory_limit' => $this->check_memory_limit(),
            'execution_time' => $this->check_execution_time(),
        );
        
        $this->check_results = $checks;
        
        // Process results and show notices
        $this->process_check_results( $checks );
    }
    
    /**
     * Check PHP version
     */
    private function check_php_version() {
        $required = '7.4.0';
        $current = PHP_VERSION;
        
        $result = array(
            'passed' => version_compare( $current, $required, '>=' ),
            'required' => $required,
            'current' => $current,
            'severity' => 'critical',
        );
        
        if ( ! $result['passed'] ) {
            $this->notice_manager->add_notice(
                'mq_php_version',
                sprintf(
                    __( '<strong>Critical:</strong> Money Quiz requires PHP %s or higher. You are running PHP %s. Please upgrade PHP immediately.', 'money-quiz' ),
                    $required,
                    $current
                ),
                'error',
                array(
                    'dismissible' => false,
                    'priority' => 100,
                    'action_button' => array(
                        'text' => __( 'How to Upgrade PHP', 'money-quiz' ),
                        'url' => 'https://wordpress.org/support/update-php/',
                    ),
                )
            );
        }
        
        return $result;
    }
    
    /**
     * Check WordPress version
     */
    private function check_wordpress_version() {
        $required = '5.8';
        $recommended = '6.0';
        $current = get_bloginfo( 'version' );
        
        $result = array(
            'passed' => version_compare( $current, $required, '>=' ),
            'optimal' => version_compare( $current, $recommended, '>=' ),
            'required' => $required,
            'recommended' => $recommended,
            'current' => $current,
            'severity' => 'warning',
        );
        
        if ( ! $result['passed'] ) {
            $this->notice_manager->add_notice(
                'mq_wp_version',
                sprintf(
                    __( '<strong>Warning:</strong> Money Quiz requires WordPress %s or higher. You are running WordPress %s. Some features may not work correctly.', 'money-quiz' ),
                    $required,
                    $current
                ),
                'warning',
                array(
                    'priority' => 90,
                    'action_button' => array(
                        'text' => __( 'Update WordPress', 'money-quiz' ),
                        'url' => admin_url( 'update-core.php' ),
                    ),
                )
            );
        } elseif ( ! $result['optimal'] ) {
            $this->notice_manager->add_notice(
                'mq_wp_version_recommended',
                sprintf(
                    __( '<strong>Notice:</strong> WordPress %s or higher is recommended for optimal performance. You are running WordPress %s.', 'money-quiz' ),
                    $recommended,
                    $current
                ),
                'info',
                array(
                    'priority' => 50,
                )
            );
        }
        
        return $result;
    }
    
    /**
     * Check PHP extensions
     */
    private function check_php_extensions() {
        $required_extensions = array(
            'mysqli' => __( 'MySQL Improved Extension', 'money-quiz' ),
            'json' => __( 'JSON Extension', 'money-quiz' ),
            'mbstring' => __( 'Multibyte String Extension', 'money-quiz' ),
        );
        
        $missing = array();
        
        foreach ( $required_extensions as $extension => $name ) {
            if ( ! extension_loaded( $extension ) ) {
                $missing[ $extension ] = $name;
            }
        }
        
        $result = array(
            'passed' => empty( $missing ),
            'missing' => $missing,
            'severity' => 'critical',
        );
        
        if ( ! $result['passed'] ) {
            $this->notice_manager->add_notice(
                'mq_php_extensions',
                sprintf(
                    __( '<strong>Critical:</strong> Required PHP extensions missing: %s. Contact your hosting provider to enable these extensions.', 'money-quiz' ),
                    implode( ', ', $missing )
                ),
                'error',
                array(
                    'dismissible' => false,
                    'priority' => 95,
                )
            );
        }
        
        return $result;
    }
    
    /**
     * Check Composer dependencies
     */
    private function check_composer() {
        $vendor_dir = MQ_SAFE_WRAPPER_DIR . 'vendor';
        $autoload_file = $vendor_dir . '/autoload.php';
        
        $result = array(
            'passed' => file_exists( $autoload_file ),
            'vendor_exists' => is_dir( $vendor_dir ),
            'autoload_exists' => file_exists( $autoload_file ),
            'severity' => 'warning',
        );
        
        // For now, Money Quiz doesn't use Composer, so we'll skip the notice
        // This is where we'd add the one-click install button if needed
        
        return $result;
    }
    
    /**
     * Check critical files
     */
    private function check_critical_files() {
        $critical_files = array(
            'moneyquiz.php' => __( 'Main plugin file', 'money-quiz' ),
            'class.moneyquiz.php' => __( 'Plugin class file', 'money-quiz' ),
            'includes/class-error-handler.php' => __( 'Error handler', 'money-quiz' ),
            'includes/class-notice-manager.php' => __( 'Notice manager', 'money-quiz' ),
            'includes/class-dependency-monitor.php' => __( 'Dependency monitor', 'money-quiz' ),
        );
        
        $missing = array();
        $corrupted = array();
        
        foreach ( $critical_files as $file => $description ) {
            $full_path = MQ_SAFE_WRAPPER_DIR . $file;
            
            if ( ! file_exists( $full_path ) ) {
                $missing[ $file ] = $description;
            } elseif ( ! is_readable( $full_path ) ) {
                $corrupted[ $file ] = $description;
            } elseif ( filesize( $full_path ) === 0 ) {
                $corrupted[ $file ] = $description . ' ' . __( '(empty file)', 'money-quiz' );
            }
        }
        
        $result = array(
            'passed' => empty( $missing ) && empty( $corrupted ),
            'missing' => $missing,
            'corrupted' => $corrupted,
            'severity' => 'critical',
        );
        
        if ( ! empty( $missing ) ) {
            $this->notice_manager->add_notice(
                'mq_missing_files',
                sprintf(
                    __( '<strong>Critical:</strong> Required files missing: %s. Please reinstall the plugin.', 'money-quiz' ),
                    implode( ', ', array_keys( $missing ) )
                ),
                'error',
                array(
                    'dismissible' => false,
                    'priority' => 100,
                )
            );
        }
        
        if ( ! empty( $corrupted ) ) {
            $this->notice_manager->add_notice(
                'mq_corrupted_files',
                sprintf(
                    __( '<strong>Critical:</strong> Files corrupted or unreadable: %s. Please check file permissions.', 'money-quiz' ),
                    implode( ', ', array_keys( $corrupted ) )
                ),
                'error',
                array(
                    'dismissible' => false,
                    'priority' => 100,
                )
            );
        }
        
        return $result;
    }
    
    /**
     * Check write permissions
     */
    private function check_write_permissions() {
        $paths_to_check = array(
            'logs' => MQ_SAFE_WRAPPER_DIR . 'logs',
            'cache' => MQ_SAFE_WRAPPER_DIR . 'cache',
        );
        
        $not_writable = array();
        
        foreach ( $paths_to_check as $name => $path ) {
            // Create directory if it doesn't exist
            if ( ! file_exists( $path ) ) {
                wp_mkdir_p( $path );
            }
            
            if ( ! is_writable( $path ) ) {
                $not_writable[ $name ] = $path;
            }
        }
        
        $result = array(
            'passed' => empty( $not_writable ),
            'not_writable' => $not_writable,
            'severity' => 'warning',
        );
        
        if ( ! $result['passed'] ) {
            $this->notice_manager->add_notice(
                'mq_write_permissions',
                sprintf(
                    __( '<strong>Warning:</strong> Some directories are not writable: %s. This may affect plugin functionality.', 'money-quiz' ),
                    implode( ', ', array_keys( $not_writable ) )
                ),
                'warning',
                array(
                    'priority' => 70,
                )
            );
        }
        
        return $result;
    }
    
    /**
     * Check memory limit
     */
    private function check_memory_limit() {
        $memory_limit = $this->parse_size( ini_get( 'memory_limit' ) );
        $required = 64 * 1024 * 1024; // 64MB
        $recommended = 128 * 1024 * 1024; // 128MB
        
        $result = array(
            'passed' => $memory_limit >= $required,
            'optimal' => $memory_limit >= $recommended,
            'current' => size_format( $memory_limit ),
            'required' => '64M',
            'recommended' => '128M',
            'severity' => 'warning',
        );
        
        if ( ! $result['passed'] ) {
            $this->notice_manager->add_notice(
                'mq_memory_limit',
                sprintf(
                    __( '<strong>Warning:</strong> PHP memory limit is too low (%s). At least 64MB is required, 128MB recommended.', 'money-quiz' ),
                    $result['current']
                ),
                'warning',
                array(
                    'priority' => 60,
                )
            );
        }
        
        return $result;
    }
    
    /**
     * Check execution time
     */
    private function check_execution_time() {
        $max_execution_time = ini_get( 'max_execution_time' );
        $required = 30;
        $recommended = 60;
        
        $result = array(
            'passed' => $max_execution_time >= $required || $max_execution_time == 0,
            'optimal' => $max_execution_time >= $recommended || $max_execution_time == 0,
            'current' => $max_execution_time,
            'required' => $required,
            'recommended' => $recommended,
            'severity' => 'info',
        );
        
        if ( ! $result['passed'] ) {
            $this->notice_manager->add_notice(
                'mq_execution_time',
                sprintf(
                    __( '<strong>Notice:</strong> PHP max execution time is low (%s seconds). This may cause timeouts during intensive operations.', 'money-quiz' ),
                    $max_execution_time
                ),
                'info',
                array(
                    'priority' => 40,
                )
            );
        }
        
        return $result;
    }
    
    /**
     * Process check results
     */
    private function process_check_results( $checks ) {
        $critical_failures = 0;
        $warnings = 0;
        
        foreach ( $checks as $check => $result ) {
            if ( ! $result['passed'] ) {
                if ( $result['severity'] === 'critical' ) {
                    $critical_failures++;
                } elseif ( $result['severity'] === 'warning' ) {
                    $warnings++;
                }
            }
        }
        
        // Show summary notice if there are issues
        if ( $critical_failures > 0 || $warnings > 0 ) {
            $message = sprintf(
                __( '<strong>Money Quiz Dependency Check:</strong> %d critical issues, %d warnings found. ', 'money-quiz' ),
                $critical_failures,
                $warnings
            );
            
            $this->notice_manager->add_notice(
                'mq_dependency_summary',
                $message,
                $critical_failures > 0 ? 'error' : 'warning',
                array(
                    'dismissible' => $critical_failures === 0,
                    'priority' => 110,
                    'action_button' => array(
                        'text' => __( 'View Details', 'money-quiz' ),
                        'url' => admin_url( 'admin.php?page=money-quiz-safety-report' ),
                    ),
                )
            );
        } else {
            // Remove summary notice if all checks pass
            $this->notice_manager->remove_notice( 'mq_dependency_summary' );
        }
    }
    
    /**
     * AJAX handler for Composer installation
     */
    public function ajax_install_composer() {
        check_ajax_referer( 'mq_install_composer' );
        
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( array(
                'message' => __( 'Insufficient permissions', 'money-quiz' ),
            ) );
        }
        
        // Check if composer is available
        exec( 'which composer', $output, $return );
        
        if ( $return !== 0 ) {
            wp_send_json_error( array(
                'message' => __( 'Composer is not installed on the server. Please contact your hosting provider.', 'money-quiz' ),
            ) );
        }
        
        // Change to plugin directory
        chdir( MQ_SAFE_WRAPPER_DIR );
        
        // Run composer install
        $output = array();
        exec( 'composer install --no-dev --optimize-autoloader 2>&1', $output, $return );
        
        if ( $return === 0 ) {
            // Clear dependency check cache
            delete_transient( 'mq_dependency_check_results' );
            
            wp_send_json_success( array(
                'message' => __( 'Dependencies installed successfully!', 'money-quiz' ),
                'output' => implode( "\n", $output ),
            ) );
        } else {
            wp_send_json_error( array(
                'message' => __( 'Failed to install dependencies. See output for details.', 'money-quiz' ),
                'output' => implode( "\n", $output ),
            ) );
        }
    }
    
    /**
     * Parse size string to bytes
     */
    private function parse_size( $size ) {
        $unit = preg_replace( '/[^bkmgtpezy]/i', '', $size );
        $size = preg_replace( '/[^0-9\.]/', '', $size );
        
        if ( $unit ) {
            return round( $size * pow( 1024, stripos( 'bkmgtpezy', $unit[0] ) ) );
        } else {
            return round( $size );
        }
    }
    
    /**
     * Get check results
     */
    public function get_check_results() {
        return $this->check_results;
    }
}
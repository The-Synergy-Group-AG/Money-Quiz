<?php
/**
 * Money Quiz Pre-Installation Safety Checker
 * 
 * Run this script BEFORE installing the Money Quiz plugin to ensure it's safe.
 * 
 * Usage: 
 * 1. Upload this file to your WordPress root directory
 * 2. Access it via: http://yoursite.com/money-quiz-safety-check.php
 * 3. Review the results before proceeding with plugin installation
 * 
 * @version 1.0.0
 */

// Basic security check
if ( ! defined( 'ABSPATH' ) ) {
    define( 'ABSPATH', dirname( __FILE__ ) . '/' );
}

// Load WordPress
if ( file_exists( ABSPATH . 'wp-load.php' ) ) {
    require_once ABSPATH . 'wp-load.php';
} else {
    die( 'WordPress not found. Please place this file in your WordPress root directory.' );
}

// Check if user is admin
if ( ! current_user_can( 'manage_options' ) ) {
    wp_die( 'You must be logged in as an administrator to run this safety check.' );
}

/**
 * Safety Checker Class
 */
class MoneyQuiz_Safety_Checker {
    
    private $plugin_path;
    private $results = array();
    private $score = 100;
    
    public function __construct() {
        $this->plugin_path = WP_PLUGIN_DIR . '/money-quiz/';
    }
    
    public function run() {
        ?>
        <!DOCTYPE html>
        <html>
        <head>
            <title>Money Quiz Safety Check</title>
            <style>
                body {
                    font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
                    line-height: 1.6;
                    color: #333;
                    max-width: 800px;
                    margin: 40px auto;
                    padding: 20px;
                    background: #f5f5f5;
                }
                .container {
                    background: white;
                    padding: 30px;
                    border-radius: 8px;
                    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
                }
                h1 {
                    color: #23282d;
                    border-bottom: 3px solid #0073aa;
                    padding-bottom: 10px;
                }
                .status {
                    display: inline-block;
                    padding: 5px 10px;
                    border-radius: 3px;
                    font-weight: bold;
                    margin-left: 10px;
                }
                .pass { background: #46b450; color: white; }
                .fail { background: #dc3232; color: white; }
                .warning { background: #ffb900; color: white; }
                .info { background: #00a0d2; color: white; }
                .check-item {
                    margin: 20px 0;
                    padding: 15px;
                    border-left: 4px solid #ddd;
                    background: #fafafa;
                }
                .check-item.pass { border-color: #46b450; }
                .check-item.fail { border-color: #dc3232; background: #ffeaea; }
                .check-item.warning { border-color: #ffb900; background: #fff8e5; }
                .details {
                    margin-top: 10px;
                    font-size: 14px;
                    color: #666;
                }
                .score {
                    font-size: 48px;
                    font-weight: bold;
                    text-align: center;
                    margin: 30px 0;
                }
                .score.safe { color: #46b450; }
                .score.caution { color: #ffb900; }
                .score.danger { color: #dc3232; }
                .recommendation {
                    padding: 20px;
                    margin: 20px 0;
                    border-radius: 5px;
                    font-weight: bold;
                }
                .recommendation.safe { background: #ecf7ed; color: #1e4e21; }
                .recommendation.caution { background: #fff8e5; color: #a36200; }
                .recommendation.danger { background: #ffeaea; color: #640000; }
                .action-buttons {
                    margin-top: 30px;
                    text-align: center;
                }
                .button {
                    display: inline-block;
                    padding: 10px 20px;
                    margin: 0 10px;
                    border-radius: 3px;
                    text-decoration: none;
                    font-weight: bold;
                }
                .button-primary {
                    background: #0073aa;
                    color: white;
                }
                .button-secondary {
                    background: #555;
                    color: white;
                }
                code {
                    background: #eee;
                    padding: 2px 5px;
                    border-radius: 3px;
                    font-family: monospace;
                }
                .issue-list {
                    margin: 10px 0;
                    padding-left: 20px;
                }
                .issue-list li {
                    margin: 5px 0;
                    color: #dc3232;
                }
            </style>
        </head>
        <body>
            <div class="container">
                <h1>Money Quiz Plugin Safety Check</h1>
                <p><strong>Checking:</strong> <?php echo esc_html( $this->plugin_path ); ?></p>
                
                <?php $this->run_checks(); ?>
                
                <?php $this->display_results(); ?>
                
                <?php $this->display_recommendation(); ?>
                
                <div class="action-buttons">
                    <a href="<?php echo admin_url( 'plugins.php' ); ?>" class="button button-secondary">
                        Back to Plugins
                    </a>
                    <?php if ( $this->score >= 70 ) : ?>
                        <a href="<?php echo admin_url( 'plugin-install.php' ); ?>" class="button button-primary">
                            Proceed with Caution
                        </a>
                    <?php endif; ?>
                </div>
                
                <p style="margin-top: 40px; text-align: center; color: #666;">
                    <small>Safety check completed on <?php echo date( 'Y-m-d H:i:s' ); ?></small>
                </p>
            </div>
        </body>
        </html>
        <?php
    }
    
    private function run_checks() {
        // Check if plugin exists
        if ( ! file_exists( $this->plugin_path ) ) {
            $this->add_result( 'plugin_exists', false, 'Plugin not found', 
                'The Money Quiz plugin is not installed in the expected location.' );
            return;
        }
        
        // Run all safety checks
        $this->check_dangerous_functions();
        $this->check_sql_injection();
        $this->check_xss_vulnerabilities();
        $this->check_file_operations();
        $this->check_external_connections();
        $this->check_hardcoded_secrets();
        $this->check_database_operations();
        $this->check_file_permissions();
        $this->check_code_quality();
        $this->check_licensing();
    }
    
    private function check_dangerous_functions() {
        $dangerous_functions = array(
            'eval' => 'Remote code execution risk',
            'create_function' => 'Deprecated and dangerous',
            'exec' => 'System command execution',
            'system' => 'System command execution',
            'passthru' => 'System command execution',
            'shell_exec' => 'System command execution',
            'proc_open' => 'Process execution',
            'popen' => 'Process execution',
            'assert' => 'Code injection risk',
            'preg_replace.*\/e' => 'Code injection via regex',
        );
        
        $found = $this->scan_for_patterns( $dangerous_functions );
        
        if ( empty( $found ) ) {
            $this->add_result( 'dangerous_functions', true, 'No dangerous functions found' );
        } else {
            $this->add_result( 'dangerous_functions', false, 
                count( $found ) . ' dangerous function(s) found', 
                $this->format_found_issues( $found ), 
                30 );
        }
    }
    
    private function check_sql_injection() {
        $vulnerable_patterns = array(
            '\$wpdb->query\s*\([^)]*\.\s*\$_(GET|POST|REQUEST)' => 'Direct user input in query',
            '\$wpdb->get_results\s*\([^)]*\.\s*\$_(GET|POST|REQUEST)' => 'Direct user input in query',
            'WHERE\s+.*=\s*["\']?\s*\.\s*\$_(GET|POST|REQUEST)' => 'Unescaped WHERE clause',
            '\$wpdb->query\s*\([^)]*\$[a-zA-Z_]+[^)]*\)(?!.*prepare)' => 'Unprepared query with variables',
        );
        
        $found = $this->scan_for_patterns( $vulnerable_patterns );
        
        if ( empty( $found ) ) {
            $this->add_result( 'sql_injection', true, 'No SQL injection vulnerabilities found' );
        } else {
            $this->add_result( 'sql_injection', false, 
                count( $found ) . ' potential SQL injection vulnerabilities', 
                $this->format_found_issues( $found ), 
                40 );
        }
    }
    
    private function check_xss_vulnerabilities() {
        $xss_patterns = array(
            'echo\s+\$_(GET|POST|REQUEST)\[' => 'Direct output of user input',
            'print\s+\$_(GET|POST|REQUEST)\[' => 'Direct output of user input',
            'echo\s+\$[a-zA-Z_]+(?!.*esc_)' => 'Unescaped variable output',
            '<\?=\s*\$[a-zA-Z_]+(?!.*esc_)' => 'Unescaped short echo',
        );
        
        $found = $this->scan_for_patterns( $xss_patterns );
        
        // Count actual unescaped outputs
        $unescaped_count = 0;
        foreach ( $found as $file => $issues ) {
            foreach ( $issues as $issue ) {
                if ( ! preg_match( '/esc_html|esc_attr|esc_url|wp_kses|intval|absint/', $issue['line'] ) ) {
                    $unescaped_count++;
                }
            }
        }
        
        if ( $unescaped_count === 0 ) {
            $this->add_result( 'xss', true, 'No XSS vulnerabilities found' );
        } else {
            $this->add_result( 'xss', false, 
                $unescaped_count . ' potential XSS vulnerabilities', 
                $this->format_found_issues( $found ), 
                30 );
        }
    }
    
    private function check_file_operations() {
        $file_operations = array(
            'file_get_contents\s*\(\s*\$_(GET|POST|REQUEST)' => 'User-controlled file read',
            'include\s*\(\s*\$_(GET|POST|REQUEST)' => 'User-controlled file inclusion',
            'require\s*\(\s*\$_(GET|POST|REQUEST)' => 'User-controlled file inclusion',
            'fopen\s*\(\s*\$_(GET|POST|REQUEST)' => 'User-controlled file access',
            'file_put_contents' => 'File write operation',
            'move_uploaded_file' => 'File upload handling',
        );
        
        $found = $this->scan_for_patterns( $file_operations );
        
        if ( empty( $found ) ) {
            $this->add_result( 'file_operations', true, 'No dangerous file operations found' );
        } else {
            $this->add_result( 'file_operations', 'warning', 
                count( $found ) . ' file operations found', 
                'Review these for proper validation', 
                10 );
        }
    }
    
    private function check_external_connections() {
        $connection_patterns = array(
            'wp_remote_' => 'External API calls',
            'curl_' => 'cURL operations',
            'file_get_contents\s*\(\s*[\'"]http' => 'Remote file access',
            'fsockopen' => 'Socket connections',
        );
        
        $found = $this->scan_for_patterns( $connection_patterns );
        
        if ( empty( $found ) ) {
            $this->add_result( 'external_connections', true, 'No external connections found' );
        } else {
            $this->add_result( 'external_connections', 'warning', 
                count( $found ) . ' external connections found', 
                'Verify these are legitimate', 
                5 );
        }
    }
    
    private function check_hardcoded_secrets() {
        $secret_patterns = array(
            'api_key\s*=\s*[\'"][a-zA-Z0-9]{20,}[\'"]' => 'Hardcoded API key',
            'secret\s*=\s*[\'"][a-zA-Z0-9]{20,}[\'"]' => 'Hardcoded secret',
            'password\s*=\s*[\'"][^\'"\s]+[\'"]' => 'Hardcoded password',
            'define\s*\(\s*[\'"][^\'"]*(KEY|SECRET)[^\'"]*[\'"]\s*,\s*[\'"][a-zA-Z0-9]{10,}[\'"]' => 'Hardcoded constant',
        );
        
        $found = $this->scan_for_patterns( $secret_patterns );
        
        if ( empty( $found ) ) {
            $this->add_result( 'hardcoded_secrets', true, 'No hardcoded secrets found' );
        } else {
            $this->add_result( 'hardcoded_secrets', false, 
                count( $found ) . ' hardcoded secrets found', 
                'These should be stored securely', 
                20 );
        }
    }
    
    private function check_database_operations() {
        $db_patterns = array(
            'DROP\s+TABLE(?!\s+IF\s+EXISTS)' => 'Unsafe DROP TABLE',
            'TRUNCATE\s+TABLE' => 'Data deletion operation',
            'DELETE\s+FROM.*WHERE\s+1' => 'Mass deletion',
            '\$wpdb->query\s*\(\s*[\'"]CREATE\s+TABLE' => 'Direct table creation',
        );
        
        $found = $this->scan_for_patterns( $db_patterns );
        
        if ( empty( $found ) ) {
            $this->add_result( 'database_operations', true, 'Database operations appear safe' );
        } else {
            $this->add_result( 'database_operations', 'warning', 
                count( $found ) . ' potentially unsafe database operations', 
                'Review these carefully', 
                10 );
        }
    }
    
    private function check_file_permissions() {
        $writable_files = array();
        
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator( $this->plugin_path )
        );
        
        foreach ( $iterator as $file ) {
            if ( $file->isFile() && $file->getExtension() === 'php' ) {
                if ( is_writable( $file->getPathname() ) ) {
                    $perms = substr( sprintf( '%o', $file->getPerms() ), -4 );
                    if ( $perms !== '0644' && $perms !== '0444' ) {
                        $writable_files[] = str_replace( $this->plugin_path, '', $file->getPathname() ) . ' (' . $perms . ')';
                    }
                }
            }
        }
        
        if ( empty( $writable_files ) ) {
            $this->add_result( 'file_permissions', true, 'File permissions are secure' );
        } else {
            $this->add_result( 'file_permissions', 'warning', 
                count( $writable_files ) . ' files have loose permissions', 
                implode( ', ', array_slice( $writable_files, 0, 5 ) ) . 
                ( count( $writable_files ) > 5 ? ' and ' . ( count( $writable_files ) - 5 ) . ' more' : '' ), 
                5 );
        }
    }
    
    private function check_code_quality() {
        $quality_issues = array();
        
        // Check for basic code quality indicators
        $quality_patterns = array(
            '@package' => 'Missing package documentation',
            'wp_enqueue_script' => 'No proper script enqueueing',
            'wp_enqueue_style' => 'No proper style enqueueing',
            '__\(' => 'No internationalization',
        );
        
        $has_package = false;
        $has_enqueue = false;
        $has_i18n = false;
        
        $files = $this->get_php_files();
        foreach ( $files as $file ) {
            $content = file_get_contents( $file );
            if ( strpos( $content, '@package' ) !== false ) $has_package = true;
            if ( strpos( $content, 'wp_enqueue_script' ) !== false || 
                 strpos( $content, 'wp_enqueue_style' ) !== false ) $has_enqueue = true;
            if ( strpos( $content, '__(' ) !== false || 
                 strpos( $content, '_e(' ) !== false ) $has_i18n = true;
        }
        
        $score = 0;
        if ( $has_package ) $score += 33;
        if ( $has_enqueue ) $score += 33;
        if ( $has_i18n ) $score += 34;
        
        if ( $score >= 66 ) {
            $this->add_result( 'code_quality', true, 'Code quality appears good' );
        } else {
            $this->add_result( 'code_quality', 'warning', 
                'Code quality issues detected', 
                'Missing standard WordPress practices', 
                10 );
        }
    }
    
    private function check_licensing() {
        $license_indicators = array(
            'license_key',
            'activation_key',
            'verify_license',
            'check_license',
        );
        
        $has_licensing = false;
        $files = $this->get_php_files();
        
        foreach ( $files as $file ) {
            $content = strtolower( file_get_contents( $file ) );
            foreach ( $license_indicators as $indicator ) {
                if ( strpos( $content, $indicator ) !== false ) {
                    $has_licensing = true;
                    break 2;
                }
            }
        }
        
        if ( $has_licensing ) {
            $this->add_result( 'licensing', 'info', 
                'Plugin uses licensing system', 
                'Ensure you have a valid license' );
        } else {
            $this->add_result( 'licensing', true, 'No licensing restrictions found' );
        }
    }
    
    private function scan_for_patterns( $patterns ) {
        $found = array();
        $files = $this->get_php_files();
        
        foreach ( $files as $file ) {
            $content = file_get_contents( $file );
            $lines = explode( "\n", $content );
            
            foreach ( $patterns as $pattern => $description ) {
                foreach ( $lines as $line_num => $line ) {
                    if ( preg_match( '/' . $pattern . '/i', $line ) ) {
                        $relative_path = str_replace( $this->plugin_path, '', $file );
                        if ( ! isset( $found[ $relative_path ] ) ) {
                            $found[ $relative_path ] = array();
                        }
                        $found[ $relative_path ][] = array(
                            'line_number' => $line_num + 1,
                            'line' => trim( $line ),
                            'description' => $description,
                        );
                    }
                }
            }
        }
        
        return $found;
    }
    
    private function get_php_files() {
        $files = array();
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator( $this->plugin_path )
        );
        
        foreach ( $iterator as $file ) {
            if ( $file->isFile() && $file->getExtension() === 'php' ) {
                $files[] = $file->getPathname();
            }
        }
        
        return $files;
    }
    
    private function format_found_issues( $found ) {
        $output = '<ul class="issue-list">';
        $count = 0;
        foreach ( $found as $file => $issues ) {
            foreach ( $issues as $issue ) {
                $output .= '<li><code>' . esc_html( $file ) . ':' . $issue['line_number'] . '</code> - ' . 
                          esc_html( $issue['description'] ) . '</li>';
                $count++;
                if ( $count >= 5 ) {
                    $output .= '<li>... and ' . ( count( $found ) - 5 ) . ' more</li>';
                    break 2;
                }
            }
        }
        $output .= '</ul>';
        return $output;
    }
    
    private function add_result( $check, $status, $message, $details = '', $penalty = 0 ) {
        $this->results[] = array(
            'check' => $check,
            'status' => $status,
            'message' => $message,
            'details' => $details,
        );
        
        if ( $status === false ) {
            $this->score -= $penalty;
        } elseif ( $status === 'warning' ) {
            $this->score -= ( $penalty / 2 );
        }
        
        $this->score = max( 0, $this->score );
    }
    
    private function display_results() {
        foreach ( $this->results as $result ) {
            $status_class = $result['status'] === true ? 'pass' : 
                           ( $result['status'] === false ? 'fail' : $result['status'] );
            ?>
            <div class="check-item <?php echo $status_class; ?>">
                <strong><?php echo esc_html( $result['message'] ); ?></strong>
                <span class="status <?php echo $status_class; ?>">
                    <?php echo $status_class === 'pass' ? 'PASS' : 
                              ( $status_class === 'fail' ? 'FAIL' : strtoupper( $status_class ) ); ?>
                </span>
                <?php if ( $result['details'] ) : ?>
                    <div class="details"><?php echo $result['details']; ?></div>
                <?php endif; ?>
            </div>
            <?php
        }
    }
    
    private function display_recommendation() {
        $score_class = $this->score >= 80 ? 'safe' : 
                      ( $this->score >= 50 ? 'caution' : 'danger' );
        ?>
        <div class="score <?php echo $score_class; ?>">
            Safety Score: <?php echo $this->score; ?>%
        </div>
        
        <?php if ( $this->score >= 80 ) : ?>
            <div class="recommendation safe">
                ✅ This plugin appears to be relatively safe to install. 
                However, always maintain backups and monitor its behavior.
            </div>
        <?php elseif ( $this->score >= 50 ) : ?>
            <div class="recommendation caution">
                ⚠️ This plugin has some security concerns that should be addressed. 
                Install only on a staging site first and monitor carefully.
            </div>
        <?php else : ?>
            <div class="recommendation danger">
                ❌ This plugin has critical security issues and should NOT be installed 
                on a production site. Contact the developer for fixes first.
            </div>
        <?php endif; ?>
        <?php
    }
}

// Run the safety check
$checker = new MoneyQuiz_Safety_Checker();
$checker->run();
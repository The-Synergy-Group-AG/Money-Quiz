# WordPress Plugin Deployment & Operations Guide

This guide complements the WordPress Plugin Development Gold Standard with specific operational procedures for deployment, monitoring, and recovery.

## Table of Contents
1. [Admin Notice System](#admin-notice-system)
2. [Automated Deployment Script](#automated-deployment-script)
3. [Deployment Checker Tool](#deployment-checker-tool)
4. [Critical Safeguards](#critical-safeguards)
5. [Recovery Procedures](#recovery-procedures)
6. [Operational Monitoring](#operational-monitoring)

## Admin Notice System

### Enhanced Admin Notice Manager

```php
namespace VendorName\PluginName\Admin;

/**
 * Advanced admin notice system with dependency monitoring
 */
class NoticeManager {
    
    private const OPTION_KEY = 'plugin_name_admin_notices';
    private const DISMISSAL_KEY = 'plugin_name_dismissed_notices';
    
    private array $notices = array();
    
    public function __construct() {
        add_action( 'admin_notices', array( $this, 'display_notices' ) );
        add_action( 'wp_ajax_dismiss_plugin_notice', array( $this, 'ajax_dismiss_notice' ) );
        add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_notice_scripts' ) );
    }
    
    /**
     * Add a notice
     */
    public function add_notice( string $id, string $message, string $type = 'info', array $options = array() ): void {
        $defaults = array(
            'dismissible' => true,
            'dismissal_duration' => 7 * DAY_IN_SECONDS,
            'capability' => 'manage_options',
            'screens' => array(), // Empty = all screens
            'action_button' => null,
        );
        
        $options = wp_parse_args( $options, $defaults );
        
        $this->notices[ $id ] = array(
            'message' => $message,
            'type' => $type,
            'options' => $options,
        );
        
        $this->save_notices();
    }
    
    /**
     * Display notices
     */
    public function display_notices(): void {
        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }
        
        $screen = get_current_screen();
        $dismissed = get_option( self::DISMISSAL_KEY, array() );
        
        foreach ( $this->get_notices() as $id => $notice ) {
            // Check if dismissed
            if ( isset( $dismissed[ $id ] ) && $dismissed[ $id ] > time() ) {
                continue;
            }
            
            // Check screen restrictions
            if ( ! empty( $notice['options']['screens'] ) && 
                 ! in_array( $screen->id, $notice['options']['screens'], true ) ) {
                continue;
            }
            
            // Check capability
            if ( ! current_user_can( $notice['options']['capability'] ) ) {
                continue;
            }
            
            $this->render_notice( $id, $notice );
        }
    }
    
    /**
     * Render a single notice
     */
    private function render_notice( string $id, array $notice ): void {
        $classes = array(
            'notice',
            'notice-' . $notice['type'],
            'plugin-name-notice',
        );
        
        if ( $notice['options']['dismissible'] ) {
            $classes[] = 'is-dismissible';
        }
        
        ?>
        <div class="<?php echo esc_attr( implode( ' ', $classes ) ); ?>" 
             data-notice-id="<?php echo esc_attr( $id ); ?>">
            <p><?php echo wp_kses_post( $notice['message'] ); ?></p>
            
            <?php if ( $notice['options']['action_button'] ) : ?>
                <p>
                    <a href="<?php echo esc_url( $notice['options']['action_button']['url'] ); ?>" 
                       class="button button-primary">
                        <?php echo esc_html( $notice['options']['action_button']['text'] ); ?>
                    </a>
                </p>
            <?php endif; ?>
        </div>
        <?php
    }
    
    /**
     * Enqueue notice scripts
     */
    public function enqueue_notice_scripts(): void {
        wp_add_inline_script( 'jquery', "
            jQuery(document).ready(function($) {
                $('.plugin-name-notice.is-dismissible').on('click', '.notice-dismiss', function() {
                    var noticeId = $(this).parent().data('notice-id');
                    $.post(ajaxurl, {
                        action: 'dismiss_plugin_notice',
                        notice_id: noticeId,
                        _wpnonce: '" . wp_create_nonce( 'dismiss_notice' ) . "'
                    });
                });
            });
        " );
    }
    
    /**
     * AJAX handler for dismissing notices
     */
    public function ajax_dismiss_notice(): void {
        check_ajax_referer( 'dismiss_notice' );
        
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die();
        }
        
        $notice_id = sanitize_key( $_POST['notice_id'] );
        $notices = $this->get_notices();
        
        if ( isset( $notices[ $notice_id ] ) ) {
            $dismissed = get_option( self::DISMISSAL_KEY, array() );
            $duration = $notices[ $notice_id ]['options']['dismissal_duration'];
            $dismissed[ $notice_id ] = time() + $duration;
            update_option( self::DISMISSAL_KEY, $dismissed );
        }
        
        wp_die();
    }
    
    /**
     * Get all notices
     */
    private function get_notices(): array {
        return get_option( self::OPTION_KEY, array() );
    }
    
    /**
     * Save notices
     */
    private function save_notices(): void {
        update_option( self::OPTION_KEY, $this->notices );
    }
}
```

### Dependency Monitor

```php
namespace VendorName\PluginName\Admin;

/**
 * Real-time dependency monitoring
 */
class DependencyMonitor {
    
    private NoticeManager $notice_manager;
    
    public function __construct( NoticeManager $notice_manager ) {
        $this->notice_manager = $notice_manager;
        
        add_action( 'admin_init', array( $this, 'check_dependencies' ) );
        add_action( 'wp_ajax_install_composer_dependencies', array( $this, 'ajax_install_composer' ) );
    }
    
    /**
     * Check all dependencies
     */
    public function check_dependencies(): void {
        // Check Composer autoloader
        if ( ! file_exists( PLUGIN_DIR . 'vendor/autoload.php' ) ) {
            $this->notice_manager->add_notice(
                'missing_autoloader',
                sprintf(
                    __( '<strong>Critical:</strong> Composer dependencies are not installed. %s', 'plugin-textdomain' ),
                    $this->get_install_button()
                ),
                'error',
                array(
                    'dismissible' => false,
                    'action_button' => array(
                        'text' => __( 'Install Dependencies', 'plugin-textdomain' ),
                        'url' => wp_nonce_url( 
                            admin_url( 'admin-ajax.php?action=install_composer_dependencies' ), 
                            'install_composer' 
                        ),
                    ),
                )
            );
        }
        
        // Check PHP version
        if ( version_compare( PHP_VERSION, '7.4', '<' ) ) {
            $this->notice_manager->add_notice(
                'php_version',
                sprintf(
                    __( '<strong>Warning:</strong> Your PHP version (%s) is below the recommended version (7.4+). Some features may not work correctly.', 'plugin-textdomain' ),
                    PHP_VERSION
                ),
                'warning'
            );
        }
        
        // Check WordPress version
        if ( version_compare( get_bloginfo( 'version' ), '5.8', '<' ) ) {
            $this->notice_manager->add_notice(
                'wp_version',
                __( '<strong>Warning:</strong> Your WordPress version is outdated. Please update to WordPress 5.8 or higher.', 'plugin-textdomain' ),
                'warning'
            );
        }
        
        // Check critical files
        $critical_files = array(
            'src/Core/Plugin.php',
            'src/Core/Container.php',
            'src/Core/ErrorHandler.php',
        );
        
        foreach ( $critical_files as $file ) {
            if ( ! file_exists( PLUGIN_DIR . $file ) ) {
                $this->notice_manager->add_notice(
                    'missing_file_' . md5( $file ),
                    sprintf(
                        __( '<strong>Critical:</strong> Required file missing: %s', 'plugin-textdomain' ),
                        $file
                    ),
                    'error',
                    array( 'dismissible' => false )
                );
            }
        }
    }
    
    /**
     * AJAX handler for Composer installation
     */
    public function ajax_install_composer(): void {
        check_admin_referer( 'install_composer' );
        
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( __( 'Insufficient permissions', 'plugin-textdomain' ) );
        }
        
        $output = array();
        $return_var = 0;
        
        // Change to plugin directory
        chdir( PLUGIN_DIR );
        
        // Run composer install
        exec( 'composer install --no-dev --optimize-autoloader 2>&1', $output, $return_var );
        
        if ( 0 === $return_var ) {
            wp_send_json_success( array(
                'message' => __( 'Dependencies installed successfully!', 'plugin-textdomain' ),
                'output' => implode( "\n", $output ),
            ) );
        } else {
            wp_send_json_error( array(
                'message' => __( 'Failed to install dependencies.', 'plugin-textdomain' ),
                'output' => implode( "\n", $output ),
            ) );
        }
    }
}
```

## Automated Deployment Script

### deployment/deploy.php

```php
#!/usr/bin/env php
<?php
/**
 * WordPress Plugin Deployment Script
 * 
 * Usage: php deploy.php [environment] [options]
 * 
 * Options:
 *   --skip-tests     Skip running tests
 *   --skip-build     Skip building assets
 *   --dry-run        Run without making changes
 *   --verbose        Show detailed output
 */

class PluginDeployer {
    
    private string $environment;
    private array $options;
    private array $manifest = array();
    private int $exit_code = 0;
    
    public function __construct( array $argv ) {
        $this->parse_arguments( $argv );
    }
    
    /**
     * Run deployment
     */
    public function run(): int {
        $this->output( "Starting deployment to {$this->environment}...\n", 'info' );
        
        $steps = array(
            'verify_environment' => 'Verifying environment',
            'check_dependencies' => 'Checking dependencies',
            'verify_php_syntax' => 'Verifying PHP syntax',
            'run_tests' => 'Running tests',
            'build_assets' => 'Building assets',
            'create_package' => 'Creating deployment package',
            'generate_manifest' => 'Generating manifest',
            'deploy_package' => 'Deploying package',
            'verify_deployment' => 'Verifying deployment',
        );
        
        foreach ( $steps as $method => $description ) {
            if ( $this->should_skip( $method ) ) {
                $this->output( "Skipping: {$description}\n", 'warning' );
                continue;
            }
            
            $this->output( "{$description}... " );
            
            try {
                $result = $this->$method();
                if ( true === $result ) {
                    $this->output( "✓\n", 'success' );
                } else {
                    throw new Exception( $result ?: 'Unknown error' );
                }
            } catch ( Exception $e ) {
                $this->output( "✗\n", 'error' );
                $this->output( "Error: {$e->getMessage()}\n", 'error' );
                $this->exit_code = 1;
                
                if ( ! $this->options['force'] ) {
                    break;
                }
            }
        }
        
        if ( 0 === $this->exit_code ) {
            $this->output( "\nDeployment completed successfully!\n", 'success' );
        } else {
            $this->output( "\nDeployment failed with errors.\n", 'error' );
        }
        
        return $this->exit_code;
    }
    
    /**
     * Verify environment
     */
    private function verify_environment() {
        $required_commands = array( 'php', 'composer', 'npm', 'git' );
        
        foreach ( $required_commands as $cmd ) {
            exec( "which {$cmd}", $output, $return );
            if ( 0 !== $return ) {
                throw new Exception( "{$cmd} is not installed" );
            }
        }
        
        // Check PHP version
        if ( version_compare( PHP_VERSION, '7.4', '<' ) ) {
            throw new Exception( 'PHP 7.4 or higher is required' );
        }
        
        return true;
    }
    
    /**
     * Check dependencies
     */
    private function check_dependencies() {
        // Check Composer dependencies
        if ( ! file_exists( 'vendor/autoload.php' ) ) {
            $this->output( "\nInstalling Composer dependencies... " );
            exec( 'composer install --no-dev --optimize-autoloader 2>&1', $output, $return );
            if ( 0 !== $return ) {
                throw new Exception( 'Failed to install Composer dependencies' );
            }
        }
        
        // Verify composer.lock is in sync
        exec( 'composer validate --no-check-all 2>&1', $output, $return );
        if ( 0 !== $return ) {
            throw new Exception( 'composer.json and composer.lock are not in sync' );
        }
        
        // Check npm dependencies
        if ( file_exists( 'package.json' ) && ! file_exists( 'node_modules' ) ) {
            $this->output( "\nInstalling npm dependencies... " );
            exec( 'npm ci 2>&1', $output, $return );
            if ( 0 !== $return ) {
                throw new Exception( 'Failed to install npm dependencies' );
            }
        }
        
        return true;
    }
    
    /**
     * Verify PHP syntax
     */
    private function verify_php_syntax() {
        $files = $this->get_php_files();
        $errors = array();
        
        foreach ( $files as $file ) {
            exec( "php -l {$file} 2>&1", $output, $return );
            if ( 0 !== $return ) {
                $errors[] = $file;
            }
        }
        
        if ( ! empty( $errors ) ) {
            throw new Exception( 'PHP syntax errors in: ' . implode( ', ', $errors ) );
        }
        
        $this->manifest['php_files_checked'] = count( $files );
        
        return true;
    }
    
    /**
     * Run tests
     */
    private function run_tests() {
        if ( $this->options['skip-tests'] ) {
            return true;
        }
        
        // Run PHPUnit
        exec( 'vendor/bin/phpunit 2>&1', $output, $return );
        if ( 0 !== $return ) {
            throw new Exception( 'PHPUnit tests failed' );
        }
        
        // Run PHP CodeSniffer
        exec( 'vendor/bin/phpcs 2>&1', $output, $return );
        if ( 0 !== $return && ! $this->options['ignore-cs'] ) {
            throw new Exception( 'PHP CodeSniffer found issues' );
        }
        
        return true;
    }
    
    /**
     * Build assets
     */
    private function build_assets() {
        if ( $this->options['skip-build'] || ! file_exists( 'package.json' ) ) {
            return true;
        }
        
        exec( 'npm run build 2>&1', $output, $return );
        if ( 0 !== $return ) {
            throw new Exception( 'Asset build failed' );
        }
        
        $this->manifest['assets_built'] = true;
        
        return true;
    }
    
    /**
     * Create deployment package
     */
    private function create_package() {
        $excludes = array(
            '.git',
            '.github',
            'node_modules',
            'tests',
            '.env',
            '*.log',
            '.DS_Store',
            'Thumbs.db',
            'phpunit.xml',
            'phpcs.xml',
            'composer.json',
            'composer.lock',
            'package.json',
            'package-lock.json',
            'webpack.config.js',
            '.gitignore',
            'deployment',
        );
        
        $exclude_args = array_map( function( $item ) {
            return "--exclude='{$item}'";
        }, $excludes );
        
        $filename = "plugin-{$this->environment}-" . date( 'YmdHis' ) . '.zip';
        
        $cmd = sprintf(
            "zip -r %s . %s",
            $filename,
            implode( ' ', $exclude_args )
        );
        
        if ( $this->options['dry-run'] ) {
            $this->output( "\nWould create: {$filename}\n" );
            return true;
        }
        
        exec( $cmd . ' 2>&1', $output, $return );
        if ( 0 !== $return ) {
            throw new Exception( 'Failed to create deployment package' );
        }
        
        $this->manifest['package'] = $filename;
        $this->manifest['package_size'] = filesize( $filename );
        
        return true;
    }
    
    /**
     * Generate deployment manifest
     */
    private function generate_manifest() {
        $this->manifest['deployment_id'] = uniqid( 'deploy_' );
        $this->manifest['timestamp'] = date( 'c' );
        $this->manifest['environment'] = $this->environment;
        $this->manifest['deployer'] = get_current_user();
        $this->manifest['php_version'] = PHP_VERSION;
        $this->manifest['git_commit'] = trim( shell_exec( 'git rev-parse HEAD' ) );
        $this->manifest['git_branch'] = trim( shell_exec( 'git rev-parse --abbrev-ref HEAD' ) );
        
        $manifest_file = "manifest-{$this->manifest['deployment_id']}.json";
        
        if ( ! $this->options['dry-run'] ) {
            file_put_contents( 
                $manifest_file, 
                json_encode( $this->manifest, JSON_PRETTY_PRINT )
            );
        }
        
        $this->output( "\nManifest: {$manifest_file}\n", 'info' );
        
        return true;
    }
    
    /**
     * Deploy package
     */
    private function deploy_package() {
        if ( $this->options['dry-run'] ) {
            $this->output( "\nDry run - skipping actual deployment\n" );
            return true;
        }
        
        // Implementation depends on deployment target
        // Could use rsync, FTP, S3, etc.
        
        return true;
    }
    
    /**
     * Verify deployment
     */
    private function verify_deployment() {
        // Run deployment checker on target
        // Implementation depends on deployment method
        
        return true;
    }
    
    /**
     * Get all PHP files
     */
    private function get_php_files(): array {
        $files = array();
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator( '.' )
        );
        
        foreach ( $iterator as $file ) {
            if ( $file->isFile() && $file->getExtension() === 'php' ) {
                $path = $file->getPathname();
                if ( ! preg_match( '#/(vendor|node_modules|tests)/#', $path ) ) {
                    $files[] = $path;
                }
            }
        }
        
        return $files;
    }
    
    /**
     * Parse command line arguments
     */
    private function parse_arguments( array $argv ): void {
        $this->environment = $argv[1] ?? 'production';
        
        $this->options = array(
            'skip-tests' => in_array( '--skip-tests', $argv, true ),
            'skip-build' => in_array( '--skip-build', $argv, true ),
            'dry-run' => in_array( '--dry-run', $argv, true ),
            'verbose' => in_array( '--verbose', $argv, true ),
            'force' => in_array( '--force', $argv, true ),
            'ignore-cs' => in_array( '--ignore-cs', $argv, true ),
        );
    }
    
    /**
     * Check if step should be skipped
     */
    private function should_skip( string $step ): bool {
        $skip_map = array(
            'run_tests' => 'skip-tests',
            'build_assets' => 'skip-build',
        );
        
        return isset( $skip_map[ $step ] ) && $this->options[ $skip_map[ $step ] ];
    }
    
    /**
     * Output message
     */
    private function output( string $message, string $type = 'info' ): void {
        $colors = array(
            'info' => "\033[0m",      // Default
            'success' => "\033[32m",  // Green
            'warning' => "\033[33m",  // Yellow
            'error' => "\033[31m",    // Red
        );
        
        $reset = "\033[0m";
        $color = $colors[ $type ] ?? $colors['info'];
        
        if ( $this->options['verbose'] || 'error' === $type ) {
            echo $color . $message . $reset;
        }
    }
}

// Run deployment
$deployer = new PluginDeployer( $argv );
exit( $deployer->run() );
```

## Deployment Checker Tool

### deployment/check-deployment.php

```php
#!/usr/bin/env php
<?php
/**
 * WordPress Plugin Deployment Checker
 * 
 * Standalone tool to verify deployment readiness
 * 
 * Usage: php check-deployment.php [--json] [--verbose]
 */

class DeploymentChecker {
    
    private array $results = array();
    private bool $json_output;
    private bool $verbose;
    private int $exit_code = 0;
    
    public function __construct( array $argv ) {
        $this->json_output = in_array( '--json', $argv, true );
        $this->verbose = in_array( '--verbose', $argv, true );
    }
    
    /**
     * Run all checks
     */
    public function run(): int {
        $checks = array(
            'PHP Version' => array( $this, 'check_php_version' ),
            'WordPress Compatibility' => array( $this, 'check_wordpress_compatibility' ),
            'Composer Dependencies' => array( $this, 'check_composer_dependencies' ),
            'Required Files' => array( $this, 'check_required_files' ),
            'File Permissions' => array( $this, 'check_file_permissions' ),
            'PHP Syntax' => array( $this, 'check_php_syntax' ),
            'Database Requirements' => array( $this, 'check_database_requirements' ),
            'External Services' => array( $this, 'check_external_services' ),
            'Security Audit' => array( $this, 'check_security' ),
            'Performance Metrics' => array( $this, 'check_performance' ),
        );
        
        if ( ! $this->json_output ) {
            $this->output( "WordPress Plugin Deployment Checker\n" );
            $this->output( str_repeat( '=', 50 ) . "\n\n" );
        }
        
        foreach ( $checks as $name => $callback ) {
            $this->run_check( $name, $callback );
        }
        
        $this->generate_report();
        
        return $this->exit_code;
    }
    
    /**
     * Run individual check
     */
    private function run_check( string $name, callable $callback ): void {
        if ( ! $this->json_output ) {
            $this->output( sprintf( "%-30s ", $name ) );
        }
        
        $start_time = microtime( true );
        
        try {
            $result = call_user_func( $callback );
            $status = $result['status'] ?? 'pass';
            $message = $result['message'] ?? '';
            
            $this->results[ $name ] = array(
                'status' => $status,
                'message' => $message,
                'duration' => round( microtime( true ) - $start_time, 3 ),
                'details' => $result['details'] ?? array(),
            );
            
            if ( 'fail' === $status ) {
                $this->exit_code = 1;
            }
            
            if ( ! $this->json_output ) {
                $this->output_status( $status, $message );
            }
            
        } catch ( Exception $e ) {
            $this->results[ $name ] = array(
                'status' => 'error',
                'message' => $e->getMessage(),
                'duration' => round( microtime( true ) - $start_time, 3 ),
            );
            
            $this->exit_code = 2;
            
            if ( ! $this->json_output ) {
                $this->output_status( 'error', $e->getMessage() );
            }
        }
    }
    
    /**
     * Check PHP version
     */
    private function check_php_version(): array {
        $required = '7.4.0';
        $current = PHP_VERSION;
        
        if ( version_compare( $current, $required, '>=' ) ) {
            return array(
                'status' => 'pass',
                'message' => "PHP {$current}",
                'details' => array(
                    'current' => $current,
                    'required' => $required,
                ),
            );
        }
        
        return array(
            'status' => 'fail',
            'message' => "PHP {$current} < {$required}",
            'details' => array(
                'current' => $current,
                'required' => $required,
            ),
        );
    }
    
    /**
     * Check WordPress compatibility
     */
    private function check_wordpress_compatibility(): array {
        if ( ! file_exists( 'readme.txt' ) ) {
            return array(
                'status' => 'warning',
                'message' => 'readme.txt not found',
            );
        }
        
        $readme = file_get_contents( 'readme.txt' );
        preg_match( '/Requires at least:\s*(.+)$/m', $readme, $matches );
        $min_wp = $matches[1] ?? '5.8';
        
        preg_match( '/Tested up to:\s*(.+)$/m', $readme, $matches );
        $tested_wp = $matches[1] ?? 'Unknown';
        
        return array(
            'status' => 'pass',
            'message' => "WP {$min_wp} - {$tested_wp}",
            'details' => array(
                'minimum' => $min_wp,
                'tested' => $tested_wp,
            ),
        );
    }
    
    /**
     * Check Composer dependencies
     */
    private function check_composer_dependencies(): array {
        if ( ! file_exists( 'composer.json' ) ) {
            return array(
                'status' => 'fail',
                'message' => 'composer.json not found',
            );
        }
        
        if ( ! file_exists( 'vendor/autoload.php' ) ) {
            return array(
                'status' => 'fail',
                'message' => 'Dependencies not installed',
            );
        }
        
        // Check if composer.lock is in sync
        exec( 'composer validate --no-check-all 2>&1', $output, $return );
        
        if ( 0 !== $return ) {
            return array(
                'status' => 'warning',
                'message' => 'composer.lock out of sync',
            );
        }
        
        // Count dependencies
        $lock = json_decode( file_get_contents( 'composer.lock' ), true );
        $count = count( $lock['packages'] ?? array() );
        
        return array(
            'status' => 'pass',
            'message' => "{$count} packages installed",
            'details' => array(
                'packages' => $count,
            ),
        );
    }
    
    /**
     * Check required files
     */
    private function check_required_files(): array {
        $required_files = array(
            'Main plugin file' => $this->find_main_plugin_file(),
            'Composer autoloader' => 'vendor/autoload.php',
            'License file' => array( 'LICENSE', 'LICENSE.txt', 'license.txt' ),
            'Readme file' => array( 'README.md', 'readme.txt' ),
        );
        
        $missing = array();
        
        foreach ( $required_files as $name => $paths ) {
            $found = false;
            $paths = (array) $paths;
            
            foreach ( $paths as $path ) {
                if ( file_exists( $path ) ) {
                    $found = true;
                    break;
                }
            }
            
            if ( ! $found ) {
                $missing[] = $name;
            }
        }
        
        if ( empty( $missing ) ) {
            return array(
                'status' => 'pass',
                'message' => 'All required files present',
            );
        }
        
        return array(
            'status' => 'fail',
            'message' => 'Missing: ' . implode( ', ', $missing ),
            'details' => array(
                'missing' => $missing,
            ),
        );
    }
    
    /**
     * Check file permissions
     */
    private function check_file_permissions(): array {
        $issues = array();
        
        // Check if critical files are writable (they shouldn't be in production)
        $should_not_be_writable = array(
            $this->find_main_plugin_file(),
            'vendor/autoload.php',
        );
        
        foreach ( $should_not_be_writable as $file ) {
            if ( file_exists( $file ) && is_writable( $file ) ) {
                $issues[] = "{$file} is writable";
            }
        }
        
        if ( empty( $issues ) ) {
            return array(
                'status' => 'pass',
                'message' => 'File permissions OK',
            );
        }
        
        return array(
            'status' => 'warning',
            'message' => implode( '; ', $issues ),
            'details' => array(
                'issues' => $issues,
            ),
        );
    }
    
    /**
     * Check PHP syntax
     */
    private function check_php_syntax(): array {
        $errors = 0;
        $checked = 0;
        
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator( '.' )
        );
        
        foreach ( $iterator as $file ) {
            if ( $file->isFile() && $file->getExtension() === 'php' ) {
                $path = $file->getPathname();
                
                // Skip vendor and test files
                if ( preg_match( '#/(vendor|node_modules|tests)/#', $path ) ) {
                    continue;
                }
                
                $checked++;
                exec( "php -l {$path} 2>&1", $output, $return );
                
                if ( 0 !== $return ) {
                    $errors++;
                    if ( $this->verbose ) {
                        $this->output( "\n  Syntax error in: {$path}" );
                    }
                }
            }
        }
        
        if ( 0 === $errors ) {
            return array(
                'status' => 'pass',
                'message' => "{$checked} files OK",
                'details' => array(
                    'files_checked' => $checked,
                ),
            );
        }
        
        return array(
            'status' => 'fail',
            'message' => "{$errors} syntax errors in {$checked} files",
            'details' => array(
                'files_checked' => $checked,
                'errors' => $errors,
            ),
        );
    }
    
    /**
     * Check database requirements
     */
    private function check_database_requirements(): array {
        // Check for database migration files
        $migration_files = glob( 'src/Database/Migrations/*.php' );
        $migration_count = count( $migration_files );
        
        return array(
            'status' => 'pass',
            'message' => "{$migration_count} migrations found",
            'details' => array(
                'migrations' => $migration_count,
            ),
        );
    }
    
    /**
     * Check external services
     */
    private function check_external_services(): array {
        $services = array();
        
        // Check for API keys or external service configurations
        if ( file_exists( '.env.example' ) ) {
            $env_example = file_get_contents( '.env.example' );
            preg_match_all( '/^([A-Z_]+)=/m', $env_example, $matches );
            $services = $matches[1] ?? array();
        }
        
        if ( empty( $services ) ) {
            return array(
                'status' => 'pass',
                'message' => 'No external services configured',
            );
        }
        
        return array(
            'status' => 'info',
            'message' => count( $services ) . ' services configured',
            'details' => array(
                'services' => $services,
            ),
        );
    }
    
    /**
     * Check security
     */
    private function check_security(): array {
        $issues = array();
        
        // Check for common security issues
        $patterns = array(
            'eval\s*\(' => 'eval() usage',
            'create_function\s*\(' => 'create_function() usage',
            '\$_(GET|POST|REQUEST)\s*\[.*\]\s*;' => 'Unvalidated input',
            'echo\s+\$_' => 'Unescaped output',
        );
        
        foreach ( glob( 'src/**/*.php' ) as $file ) {
            $content = file_get_contents( $file );
            
            foreach ( $patterns as $pattern => $issue ) {
                if ( preg_match( '/' . $pattern . '/i', $content ) ) {
                    $issues[] = "{$issue} in " . basename( $file );
                }
            }
        }
        
        if ( empty( $issues ) ) {
            return array(
                'status' => 'pass',
                'message' => 'No security issues found',
            );
        }
        
        return array(
            'status' => 'warning',
            'message' => count( $issues ) . ' potential issues',
            'details' => array(
                'issues' => $issues,
            ),
        );
    }
    
    /**
     * Check performance metrics
     */
    private function check_performance(): array {
        $metrics = array();
        
        // Check asset sizes
        $css_size = 0;
        $js_size = 0;
        
        foreach ( glob( 'assets/css/*.css' ) as $file ) {
            $css_size += filesize( $file );
        }
        
        foreach ( glob( 'assets/js/*.js' ) as $file ) {
            $js_size += filesize( $file );
        }
        
        $metrics['css_size'] = $this->format_bytes( $css_size );
        $metrics['js_size'] = $this->format_bytes( $js_size );
        
        // Count database queries in activation
        // This would need actual analysis of the code
        
        return array(
            'status' => 'info',
            'message' => "CSS: {$metrics['css_size']}, JS: {$metrics['js_size']}",
            'details' => $metrics,
        );
    }
    
    /**
     * Generate final report
     */
    private function generate_report(): void {
        if ( $this->json_output ) {
            echo json_encode( array(
                'timestamp' => date( 'c' ),
                'overall_status' => 0 === $this->exit_code ? 'pass' : 'fail',
                'checks' => $this->results,
            ), JSON_PRETTY_PRINT );
            return;
        }
        
        $this->output( "\n" . str_repeat( '=', 50 ) . "\n" );
        
        $pass = 0;
        $fail = 0;
        $warning = 0;
        
        foreach ( $this->results as $result ) {
            switch ( $result['status'] ) {
                case 'pass':
                    $pass++;
                    break;
                case 'fail':
                case 'error':
                    $fail++;
                    break;
                case 'warning':
                    $warning++;
                    break;
            }
        }
        
        $total = count( $this->results );
        
        $this->output( sprintf(
            "Results: %d/%d passed, %d warnings, %d failed\n",
            $pass,
            $total,
            $warning,
            $fail
        ) );
        
        if ( 0 === $this->exit_code ) {
            $this->output( "\n✅ Deployment check PASSED\n", 'success' );
        } else {
            $this->output( "\n❌ Deployment check FAILED\n", 'error' );
        }
    }
    
    /**
     * Find main plugin file
     */
    private function find_main_plugin_file(): ?string {
        foreach ( glob( '*.php' ) as $file ) {
            $content = file_get_contents( $file );
            if ( preg_match( '/^\s*\*\s*Plugin Name:/m', $content ) ) {
                return $file;
            }
        }
        return null;
    }
    
    /**
     * Format bytes
     */
    private function format_bytes( int $bytes ): string {
        $units = array( 'B', 'KB', 'MB', 'GB' );
        $i = 0;
        
        while ( $bytes >= 1024 && $i < count( $units ) - 1 ) {
            $bytes /= 1024;
            $i++;
        }
        
        return round( $bytes, 2 ) . ' ' . $units[ $i ];
    }
    
    /**
     * Output status
     */
    private function output_status( string $status, string $message = '' ): void {
        $symbols = array(
            'pass' => '✅',
            'fail' => '❌',
            'warning' => '⚠️ ',
            'error' => '🚫',
            'info' => 'ℹ️ ',
        );
        
        $symbol = $symbols[ $status ] ?? '?';
        
        $this->output( $symbol );
        
        if ( $message && ( $this->verbose || 'pass' !== $status ) ) {
            $this->output( " ({$message})" );
        }
        
        $this->output( "\n" );
    }
    
    /**
     * Output message
     */
    private function output( string $message, string $type = 'info' ): void {
        if ( $this->json_output ) {
            return;
        }
        
        $colors = array(
            'success' => "\033[32m",
            'error' => "\033[31m",
            'warning' => "\033[33m",
        );
        
        $color = $colors[ $type ] ?? '';
        $reset = $color ? "\033[0m" : '';
        
        echo $color . $message . $reset;
    }
}

// Run checker
$checker = new DeploymentChecker( $argv );
exit( $checker->run() );
```

## Critical Safeguards

### Enhanced Plugin Bootstrap

```php
<?php
/**
 * Plugin Name: My Plugin
 * Version: 1.0.0
 */

namespace VendorName\PluginName;

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// Prevent duplicate loading
if ( defined( 'MY_PLUGIN_LOADED' ) ) {
    return;
}
define( 'MY_PLUGIN_LOADED', true );

// Define constants
define( 'MY_PLUGIN_FILE', __FILE__ );
define( 'MY_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'MY_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

/**
 * Critical safeguards loader
 */
class PluginLoader {
    
    private static $instance = null;
    private $errors = array();
    private $dependencies_loaded = false;
    
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
     * Initialize plugin with safeguards
     */
    public function init() {
        // Check PHP version first
        if ( ! $this->check_php_version() ) {
            return;
        }
        
        // Load Composer dependencies
        if ( ! $this->load_dependencies() ) {
            $this->show_dependency_notice();
            return;
        }
        
        // Initialize error handler
        if ( ! $this->init_error_handler() ) {
            return;
        }
        
        // Load core plugin
        if ( ! $this->load_plugin() ) {
            $this->show_error_notice();
            return;
        }
    }
    
    /**
     * Check PHP version
     */
    private function check_php_version() {
        if ( version_compare( PHP_VERSION, '7.4', '<' ) ) {
            add_action( 'admin_notices', function() {
                ?>
                <div class="notice notice-error">
                    <p><?php printf( 
                        esc_html__( 'Plugin Name requires PHP 7.4 or higher. You are running PHP %s.', 'plugin-textdomain' ),
                        PHP_VERSION 
                    ); ?></p>
                </div>
                <?php
            } );
            return false;
        }
        return true;
    }
    
    /**
     * Load Composer dependencies
     */
    private function load_dependencies() {
        $autoloader = MY_PLUGIN_DIR . 'vendor/autoload.php';
        
        // Check if autoloader exists
        if ( ! file_exists( $autoloader ) ) {
            $this->errors[] = 'Composer autoloader not found';
            return false;
        }
        
        // Check if vendor directory has content
        $vendor_dir = MY_PLUGIN_DIR . 'vendor';
        if ( ! is_dir( $vendor_dir ) || count( scandir( $vendor_dir ) ) <= 2 ) {
            $this->errors[] = 'Vendor directory is empty';
            return false;
        }
        
        // Try to load autoloader
        try {
            require_once $autoloader;
            $this->dependencies_loaded = true;
            return true;
        } catch ( \Exception $e ) {
            $this->errors[] = 'Failed to load autoloader: ' . $e->getMessage();
            return false;
        }
    }
    
    /**
     * Initialize error handler
     */
    private function init_error_handler() {
        if ( ! $this->dependencies_loaded ) {
            return false;
        }
        
        try {
            if ( class_exists( '\VendorName\PluginName\Core\ErrorHandler' ) ) {
                $error_handler = new \VendorName\PluginName\Core\ErrorHandler();
                $error_handler->register();
            }
            return true;
        } catch ( \Exception $e ) {
            $this->errors[] = 'Failed to initialize error handler: ' . $e->getMessage();
            return false;
        }
    }
    
    /**
     * Load main plugin
     */
    private function load_plugin() {
        if ( ! $this->dependencies_loaded ) {
            return false;
        }
        
        try {
            // Check if main plugin class exists
            if ( ! class_exists( '\VendorName\PluginName\Core\Plugin' ) ) {
                $this->errors[] = 'Main plugin class not found';
                return false;
            }
            
            // Initialize plugin
            $plugin = \VendorName\PluginName\Core\Plugin::instance();
            $plugin->run();
            
            return true;
            
        } catch ( \Exception $e ) {
            $this->errors[] = 'Plugin initialization failed: ' . $e->getMessage();
            
            if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
                error_log( sprintf(
                    '[Plugin Name] Fatal error: %s in %s:%d',
                    $e->getMessage(),
                    $e->getFile(),
                    $e->getLine()
                ) );
            }
            
            return false;
        }
    }
    
    /**
     * Show dependency notice
     */
    private function show_dependency_notice() {
        add_action( 'admin_notices', function() {
            $notice_manager = new \VendorName\PluginName\Admin\NoticeManager();
            $notice_manager->add_notice(
                'missing_dependencies',
                '<strong>Plugin Name:</strong> Dependencies are not properly installed. ' . 
                'Please run <code>composer install</code> in the plugin directory.',
                'error',
                array(
                    'dismissible' => false,
                    'action_button' => array(
                        'text' => __( 'View Instructions', 'plugin-textdomain' ),
                        'url' => admin_url( 'plugins.php?page=plugin-name-help' ),
                    ),
                )
            );
        } );
    }
    
    /**
     * Show error notice
     */
    private function show_error_notice() {
        add_action( 'admin_notices', function() {
            ?>
            <div class="notice notice-error">
                <p><strong><?php esc_html_e( 'Plugin Name Error:', 'plugin-textdomain' ); ?></strong></p>
                <?php if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) : ?>
                    <ul>
                        <?php foreach ( $this->errors as $error ) : ?>
                            <li><?php echo esc_html( $error ); ?></li>
                        <?php endforeach; ?>
                    </ul>
                <?php else : ?>
                    <p><?php esc_html_e( 'The plugin could not be loaded. Please check the error logs.', 'plugin-textdomain' ); ?></p>
                <?php endif; ?>
            </div>
            <?php
        } );
    }
}

// Initialize plugin with safeguards
add_action( 'plugins_loaded', function() {
    PluginLoader::instance()->init();
}, 5 );

// Register activation/deactivation hooks with error handling
register_activation_hook( __FILE__, function() {
    try {
        if ( class_exists( '\VendorName\PluginName\Core\Activator' ) ) {
            \VendorName\PluginName\Core\Activator::activate();
        }
    } catch ( \Exception $e ) {
        error_log( 'Plugin activation error: ' . $e->getMessage() );
        wp_die( 
            esc_html( $e->getMessage() ), 
            esc_html__( 'Plugin Activation Error', 'plugin-textdomain' ),
            array( 'back_link' => true )
        );
    }
} );

register_deactivation_hook( __FILE__, function() {
    try {
        if ( class_exists( '\VendorName\PluginName\Core\Deactivator' ) ) {
            \VendorName\PluginName\Core\Deactivator::deactivate();
        }
    } catch ( \Exception $e ) {
        error_log( 'Plugin deactivation error: ' . $e->getMessage() );
    }
} );
```

## Recovery Procedures

### Emergency Recovery Guide

```markdown
# Plugin Emergency Recovery Procedures

## Common Issues and Solutions

### 1. White Screen of Death (WSOD)

**Symptoms:**
- Blank white screen
- No error messages
- Site completely inaccessible

**Recovery Steps:**
1. Access site via FTP/SSH
2. Navigate to `/wp-content/plugins/`
3. Rename plugin folder to `plugin-name-disabled`
4. Site should now load
5. Check error logs at `/wp-content/debug.log`

### 2. PHP Fatal Errors

**Symptoms:**
- Fatal error messages
- Partial page loads
- "There has been a critical error" message

**Recovery Steps:**
1. Enable WordPress debug mode in `wp-config.php`:
   ```php
   define( 'WP_DEBUG', true );
   define( 'WP_DEBUG_LOG', true );
   define( 'WP_DEBUG_DISPLAY', false );
   ```

2. Check debug log for specific error
3. Common fixes:
   - Missing dependencies: Run `composer install`
   - Syntax errors: Restore from backup
   - Memory exhausted: Increase PHP memory limit

### 3. Database Errors

**Symptoms:**
- "Error establishing database connection"
- Missing data
- Corrupted tables

**Recovery Steps:**
1. Access phpMyAdmin or database CLI
2. Check for plugin tables with prefix
3. Run repair:
   ```sql
   REPAIR TABLE wp_plugin_table_name;
   ```
4. If corrupted beyond repair, restore from backup

### 4. Dependency Issues

**Symptoms:**
- "Class not found" errors
- Autoloader failures
- Missing vendor directory

**Recovery Steps:**
1. SSH into server
2. Navigate to plugin directory
3. Run:
   ```bash
   composer install --no-dev --optimize-autoloader
   ```
4. Verify vendor directory exists and contains files

### 5. Permission Issues

**Symptoms:**
- Cannot write to files
- Upload failures
- Update failures

**Recovery Steps:**
1. Check file ownership:
   ```bash
   ls -la wp-content/plugins/plugin-name/
   ```

2. Fix permissions:
   ```bash
   find wp-content/plugins/plugin-name/ -type d -exec chmod 755 {} \;
   find wp-content/plugins/plugin-name/ -type f -exec chmod 644 {} \;
   ```

3. Fix ownership (adjust user/group as needed):
   ```bash
   chown -R www-data:www-data wp-content/plugins/plugin-name/
   ```

## Rollback Procedures

### Manual Rollback

1. **Backup current state** (even if broken)
2. **Download previous version** from backups
3. **Remove current plugin**:
   ```bash
   rm -rf wp-content/plugins/plugin-name/
   ```
4. **Upload previous version**
5. **Clear caches**:
   - Object cache
   - Page cache
   - CDN cache
   - Browser cache

### Database Rollback

If plugin updated database schema:

1. **Identify changes** from migration logs
2. **Restore from database backup** if available
3. **Manual reversion** if needed:
   ```sql
   -- Example: Remove added column
   ALTER TABLE wp_plugin_table DROP COLUMN new_column;
   
   -- Example: Restore modified column
   ALTER TABLE wp_plugin_table MODIFY column_name VARCHAR(255);
   ```

## Prevention Checklist

### Before Deployment
- [ ] Full site backup (files + database)
- [ ] Test in staging environment
- [ ] Run deployment checker
- [ ] Verify all dependencies
- [ ] Check file permissions

### During Deployment
- [ ] Monitor error logs
- [ ] Check site functionality
- [ ] Verify critical features
- [ ] Test with different user roles

### After Deployment
- [ ] Clear all caches
- [ ] Monitor for 24 hours
- [ ] Check error reports
- [ ] Gather user feedback
- [ ] Document any issues

## Emergency Contacts

- **Hosting Support**: [Your host's support]
- **Developer Team**: [Contact info]
- **System Admin**: [Contact info]
- **Database Admin**: [Contact info]

## Useful Commands

```bash
# Check PHP errors
tail -f /var/log/php-error.log

# Monitor WordPress debug log
tail -f wp-content/debug.log

# Find recently modified files
find . -type f -mtime -1 -ls

# Check disk space
df -h

# Check memory usage
free -m

# List running processes
ps aux | grep php

# Kill stuck processes
kill -9 [PID]
```
```

## Operational Monitoring

### Health Check Endpoint

```php
namespace VendorName\PluginName\Api;

/**
 * Health check endpoint for monitoring
 */
class HealthCheck {
    
    public function __construct() {
        add_action( 'rest_api_init', array( $this, 'register_routes' ) );
    }
    
    /**
     * Register REST routes
     */
    public function register_routes() {
        register_rest_route( 'plugin-name/v1', '/health', array(
            'methods' => 'GET',
            'callback' => array( $this, 'health_check' ),
            'permission_callback' => '__return_true',
        ) );
    }
    
    /**
     * Health check endpoint
     */
    public function health_check( \WP_REST_Request $request ) {
        $checks = array(
            'plugin_active' => $this->check_plugin_active(),
            'dependencies' => $this->check_dependencies(),
            'database' => $this->check_database(),
            'cache' => $this->check_cache(),
            'filesystem' => $this->check_filesystem(),
        );
        
        $healthy = ! in_array( false, $checks, true );
        
        return new \WP_REST_Response( array(
            'status' => $healthy ? 'healthy' : 'unhealthy',
            'timestamp' => current_time( 'c' ),
            'version' => MY_PLUGIN_VERSION,
            'checks' => $checks,
        ), $healthy ? 200 : 503 );
    }
    
    /**
     * Check if plugin is active
     */
    private function check_plugin_active() {
        return class_exists( '\VendorName\PluginName\Core\Plugin' );
    }
    
    /**
     * Check dependencies
     */
    private function check_dependencies() {
        return file_exists( MY_PLUGIN_DIR . 'vendor/autoload.php' );
    }
    
    /**
     * Check database
     */
    private function check_database() {
        global $wpdb;
        
        try {
            $result = $wpdb->get_var( "SELECT 1" );
            return '1' === $result;
        } catch ( \Exception $e ) {
            return false;
        }
    }
    
    /**
     * Check cache
     */
    private function check_cache() {
        $key = 'health_check_' . uniqid();
        $value = 'test';
        
        wp_cache_set( $key, $value, 'plugin-name', 60 );
        $retrieved = wp_cache_get( $key, 'plugin-name' );
        wp_cache_delete( $key, 'plugin-name' );
        
        return $value === $retrieved;
    }
    
    /**
     * Check filesystem
     */
    private function check_filesystem() {
        $test_file = MY_PLUGIN_DIR . 'health_check_' . uniqid() . '.tmp';
        
        try {
            file_put_contents( $test_file, 'test' );
            $success = file_exists( $test_file );
            unlink( $test_file );
            return $success;
        } catch ( \Exception $e ) {
            return false;
        }
    }
}
```

---

*This operational guide provides the specific deployment, monitoring, and recovery procedures that complement the WordPress Plugin Development Gold Standard.*
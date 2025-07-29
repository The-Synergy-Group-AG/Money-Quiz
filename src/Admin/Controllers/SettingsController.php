<?php
/**
 * Settings Controller
 *
 * @package MoneyQuiz
 * @since 4.0.0
 */

namespace MoneyQuiz\Admin\Controllers;

use MoneyQuiz\Admin\SettingsManager;

/**
 * Handles settings page in admin
 */
class SettingsController {
    
    /**
     * @var SettingsManager
     */
    private SettingsManager $settings_manager;
    
    /**
     * Constructor
     */
    public function __construct() {
        $this->settings_manager = new SettingsManager();
        $this->settings_manager->init();
    }
    
    /**
     * Display settings page
     * 
     * @return void
     */
    public function index(): void {
        // Handle export
        if ( isset( $_GET['export'] ) && $_GET['export'] === 'settings' ) {
            $this->export_settings();
            return;
        }
        
        // Handle import
        if ( isset( $_POST['import_settings'] ) ) {
            $this->import_settings();
        }
        
        // Handle reset
        if ( isset( $_POST['reset_settings'] ) ) {
            $this->reset_settings();
        }
        
        // Display the settings page
        $this->render_settings_page();
    }
    
    /**
     * Export settings
     * 
     * @return void
     */
    private function export_settings(): void {
        // Check capability
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( __( 'You do not have permission to export settings.', 'money-quiz' ) );
        }
        
        // Verify nonce
        if ( ! wp_verify_nonce( $_GET['_wpnonce'] ?? '', 'export_settings' ) ) {
            wp_die( __( 'Security check failed.', 'money-quiz' ) );
        }
        
        $json = $this->settings_manager->export_settings();
        
        // Set headers for download
        header( 'Content-Type: application/json' );
        header( 'Content-Disposition: attachment; filename=money-quiz-settings-' . date( 'Y-m-d' ) . '.json' );
        header( 'Cache-Control: no-cache, no-store, must-revalidate' );
        
        echo $json;
        exit;
    }
    
    /**
     * Import settings
     * 
     * @return void
     */
    private function import_settings(): void {
        // Verify nonce
        if ( ! wp_verify_nonce( $_POST['_wpnonce'] ?? '', 'import_settings' ) ) {
            add_settings_error(
                'money_quiz_settings',
                'invalid_nonce',
                __( 'Security check failed.', 'money-quiz' )
            );
            return;
        }
        
        // Check file upload
        if ( empty( $_FILES['import_file'] ) || $_FILES['import_file']['error'] !== UPLOAD_ERR_OK ) {
            add_settings_error(
                'money_quiz_settings',
                'upload_error',
                __( 'Please select a valid file to import.', 'money-quiz' )
            );
            return;
        }
        
        // Read file
        $json = file_get_contents( $_FILES['import_file']['tmp_name'] );
        
        if ( ! $json ) {
            add_settings_error(
                'money_quiz_settings',
                'read_error',
                __( 'Could not read the import file.', 'money-quiz' )
            );
            return;
        }
        
        // Import settings
        if ( $this->settings_manager->import_settings( $json ) ) {
            add_settings_error(
                'money_quiz_settings',
                'import_success',
                __( 'Settings imported successfully.', 'money-quiz' ),
                'success'
            );
        } else {
            add_settings_error(
                'money_quiz_settings',
                'import_error',
                __( 'Failed to import settings. Please check the file format.', 'money-quiz' )
            );
        }
    }
    
    /**
     * Reset settings
     * 
     * @return void
     */
    private function reset_settings(): void {
        // Verify nonce
        if ( ! wp_verify_nonce( $_POST['_wpnonce'] ?? '', 'reset_settings' ) ) {
            add_settings_error(
                'money_quiz_settings',
                'invalid_nonce',
                __( 'Security check failed.', 'money-quiz' )
            );
            return;
        }
        
        // Get section to reset
        $section = $_POST['reset_section'] ?? null;
        
        if ( $this->settings_manager->reset_to_defaults( $section ) ) {
            $message = $section 
                ? sprintf( __( '%s settings reset to defaults.', 'money-quiz' ), ucfirst( $section ) )
                : __( 'All settings reset to defaults.', 'money-quiz' );
                
            add_settings_error(
                'money_quiz_settings',
                'reset_success',
                $message,
                'success'
            );
        } else {
            add_settings_error(
                'money_quiz_settings',
                'reset_error',
                __( 'Failed to reset settings.', 'money-quiz' )
            );
        }
    }
    
    /**
     * Render settings page
     * 
     * @return void
     */
    private function render_settings_page(): void {
        // Check capability
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( __( 'You do not have permission to manage settings.', 'money-quiz' ) );
        }
        
        ?>
        <div class="wrap">
            <h1><?php echo esc_html( get_admin_page_title() ); ?></h1>
            
            <?php settings_errors( 'money_quiz_settings' ); ?>
            
            <div class="nav-tab-wrapper">
                <a href="#general" class="nav-tab nav-tab-active" data-tab="general">
                    <?php _e( 'General', 'money-quiz' ); ?>
                </a>
                <a href="#email" class="nav-tab" data-tab="email">
                    <?php _e( 'Email', 'money-quiz' ); ?>
                </a>
                <a href="#advanced" class="nav-tab" data-tab="advanced">
                    <?php _e( 'Advanced', 'money-quiz' ); ?>
                </a>
                <a href="#integrations" class="nav-tab" data-tab="integrations">
                    <?php _e( 'Integrations', 'money-quiz' ); ?>
                </a>
                <a href="#tools" class="nav-tab" data-tab="tools">
                    <?php _e( 'Tools', 'money-quiz' ); ?>
                </a>
            </div>
            
            <form method="post" action="options.php" id="settings-form">
                <?php settings_fields( 'money-quiz-settings' ); ?>
                
                <div class="tab-content tab-general">
                    <?php do_settings_sections( 'money-quiz-settings' ); ?>
                </div>
                
                <?php submit_button(); ?>
            </form>
            
            <div class="tab-content tab-tools" style="display: none;">
                <h2><?php _e( 'Import/Export Settings', 'money-quiz' ); ?></h2>
                
                <div class="card">
                    <h3><?php _e( 'Export Settings', 'money-quiz' ); ?></h3>
                    <p><?php _e( 'Export your current settings to a JSON file.', 'money-quiz' ); ?></p>
                    <p>
                        <a href="<?php echo wp_nonce_url( add_query_arg( 'export', 'settings' ), 'export_settings' ); ?>" 
                           class="button button-secondary">
                            <?php _e( 'Export Settings', 'money-quiz' ); ?>
                        </a>
                    </p>
                </div>
                
                <div class="card">
                    <h3><?php _e( 'Import Settings', 'money-quiz' ); ?></h3>
                    <p><?php _e( 'Import settings from a JSON file.', 'money-quiz' ); ?></p>
                    <form method="post" enctype="multipart/form-data">
                        <?php wp_nonce_field( 'import_settings' ); ?>
                        <input type="file" name="import_file" accept=".json" required />
                        <input type="submit" name="import_settings" class="button button-secondary" 
                               value="<?php esc_attr_e( 'Import Settings', 'money-quiz' ); ?>" />
                    </form>
                </div>
                
                <div class="card">
                    <h3><?php _e( 'Reset Settings', 'money-quiz' ); ?></h3>
                    <p><?php _e( 'Reset settings to their default values.', 'money-quiz' ); ?></p>
                    <form method="post" onsubmit="return confirm('<?php esc_attr_e( 'Are you sure you want to reset settings? This action cannot be undone.', 'money-quiz' ); ?>');">
                        <?php wp_nonce_field( 'reset_settings' ); ?>
                        <select name="reset_section">
                            <option value=""><?php _e( 'All Settings', 'money-quiz' ); ?></option>
                            <option value="general"><?php _e( 'General Settings', 'money-quiz' ); ?></option>
                            <option value="email"><?php _e( 'Email Settings', 'money-quiz' ); ?></option>
                            <option value="advanced"><?php _e( 'Advanced Settings', 'money-quiz' ); ?></option>
                            <option value="integrations"><?php _e( 'Integration Settings', 'money-quiz' ); ?></option>
                        </select>
                        <input type="submit" name="reset_settings" class="button button-secondary" 
                               value="<?php esc_attr_e( 'Reset Settings', 'money-quiz' ); ?>" />
                    </form>
                </div>
                
                <div class="card">
                    <h3><?php _e( 'System Information', 'money-quiz' ); ?></h3>
                    <?php $this->render_system_info(); ?>
                </div>
            </div>
        </div>
        
        <style>
        .nav-tab-wrapper {
            margin-bottom: 20px;
        }
        .tab-content {
            display: none;
        }
        .tab-content.tab-general {
            display: block;
        }
        .card {
            max-width: 100%;
            margin-top: 20px;
            padding: 20px;
            background: #fff;
            border: 1px solid #ccd0d4;
            box-shadow: 0 1px 1px rgba(0,0,0,.04);
        }
        .card h3 {
            margin-top: 0;
        }
        .system-info {
            background: #f1f1f1;
            padding: 10px;
            border: 1px solid #ddd;
            font-family: monospace;
            white-space: pre-wrap;
        }
        </style>
        
        <script>
        jQuery(document).ready(function($) {
            // Tab navigation
            $('.nav-tab').on('click', function(e) {
                e.preventDefault();
                
                var tab = $(this).data('tab');
                
                // Update active tab
                $('.nav-tab').removeClass('nav-tab-active');
                $(this).addClass('nav-tab-active');
                
                // Show/hide content
                if (tab === 'tools') {
                    $('#settings-form').hide();
                    $('.tab-tools').show();
                } else {
                    $('#settings-form').show();
                    $('.tab-tools').hide();
                    
                    // Filter settings sections
                    $('#settings-form .form-table').each(function() {
                        var section = $(this).closest('div').prev('h2').text().toLowerCase();
                        if (tab === 'general' || section.includes(tab)) {
                            $(this).closest('div').show();
                            $(this).closest('div').prev('h2').show();
                        } else {
                            $(this).closest('div').hide();
                            $(this).closest('div').prev('h2').hide();
                        }
                    });
                }
            });
            
            // Trigger initial tab
            $('.nav-tab-active').trigger('click');
        });
        </script>
        <?php
    }
    
    /**
     * Render system information
     * 
     * @return void
     */
    private function render_system_info(): void {
        global $wpdb;
        
        $info = [];
        
        // Plugin version
        $info[] = sprintf( 'Money Quiz Version: %s', MONEY_QUIZ_VERSION );
        
        // WordPress version
        $info[] = sprintf( 'WordPress Version: %s', get_bloginfo( 'version' ) );
        
        // PHP version
        $info[] = sprintf( 'PHP Version: %s', PHP_VERSION );
        
        // MySQL version
        $info[] = sprintf( 'MySQL Version: %s', $wpdb->db_version() );
        
        // Active theme
        $theme = wp_get_theme();
        $info[] = sprintf( 'Active Theme: %s %s', $theme->get( 'Name' ), $theme->get( 'Version' ) );
        
        // Memory limit
        $info[] = sprintf( 'PHP Memory Limit: %s', ini_get( 'memory_limit' ) );
        
        // Upload max size
        $info[] = sprintf( 'Upload Max Size: %s', ini_get( 'upload_max_filesize' ) );
        
        // Debug mode
        $info[] = sprintf( 'WP_DEBUG: %s', defined( 'WP_DEBUG' ) && WP_DEBUG ? 'Enabled' : 'Disabled' );
        
        // Database tables
        $tables = [
            'money_quiz_quizzes',
            'money_quiz_questions', 
            'money_quiz_archetypes',
            'money_quiz_results',
            'money_quiz_prospects',
            'mq_master',
            'mq_results',
            'mq_prospect_master',
        ];
        
        $info[] = "\nDatabase Tables:";
        foreach ( $tables as $table ) {
            $full_table = $wpdb->prefix . $table;
            $exists = $wpdb->get_var( "SHOW TABLES LIKE '{$full_table}'" );
            $info[] = sprintf( '  %s: %s', $table, $exists ? 'Exists' : 'Not Found' );
        }
        
        echo '<div class="system-info">' . esc_html( implode( "\n", $info ) ) . '</div>';
    }
}
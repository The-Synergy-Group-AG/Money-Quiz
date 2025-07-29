<?php
/**
 * Legacy Patch: Admin Security
 * 
 * Patches admin pages to add proper security checks
 * 
 * @package MoneyQuiz
 * @since 4.1.0
 */

// Patch admin menu registration
add_action( 'admin_menu', function() {
    // Get all registered Money Quiz menu pages
    global $submenu, $menu;
    
    // Add capability checks to all Money Quiz pages
    if ( isset( $submenu['moneyquiz'] ) ) {
        foreach ( $submenu['moneyquiz'] as &$item ) {
            // Ensure proper capability
            if ( empty( $item[1] ) || $item[1] === 'read' ) {
                $item[1] = 'manage_options';
            }
        }
    }
}, 999 ); // Run after plugin registers menus

// Add security to all admin form submissions
add_action( 'admin_init', function() {
    // Check if this is a Money Quiz admin page
    if ( ! isset( $_GET['page'] ) || strpos( $_GET['page'], 'moneyquiz' ) === false ) {
        return;
    }
    
    // Add security checks for POST requests
    if ( $_SERVER['REQUEST_METHOD'] === 'POST' ) {
        // Check capabilities
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( __( 'You do not have sufficient permissions to access this page.', 'money-quiz' ) );
        }
        
        // Verify nonce if not already verified
        if ( ! empty( $_POST ) && ! isset( $_POST['_mq_verified'] ) ) {
            $action = $_POST['action'] ?? 'mq_admin_action';
            
            if ( ! mq_verify_security( $action ) ) {
                wp_die( __( 'Security check failed. Please try again.', 'money-quiz' ) );
            }
            
            // Mark as verified to prevent double-checking
            $_POST['_mq_verified'] = true;
        }
        
        // Sanitize all inputs
        $_POST = mq_sanitize_input( $_POST );
    }
} );

// Patch settings save function
if ( ! function_exists( 'mq_save_settings_patched' ) ) {
    function mq_save_settings_patched() {
        // Security check
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( [ 'message' => 'Insufficient permissions' ], 403 );
        }
        
        if ( ! mq_verify_security( 'mq_save_settings' ) ) {
            wp_send_json_error( [ 'message' => 'Security check failed' ], 403 );
        }
        
        // Use modern settings manager if available
        if ( class_exists( '\MoneyQuiz\Admin\SettingsManager' ) ) {
            $settings_manager = new \MoneyQuiz\Admin\SettingsManager();
            $settings_manager->save_settings();
            return;
        }
        
        // Sanitize settings
        $settings = mq_sanitize_input( $_POST['settings'] ?? [], [
            'email_subject' => 'text',
            'email_from' => 'email',
            'admin_email' => 'email',
            'success_message' => 'textarea',
            'recaptcha_key' => 'text',
            'recaptcha_secret' => 'text',
            'mailchimp_api' => 'text',
            'mailchimp_list' => 'text'
        ] );
        
        // Save each setting
        foreach ( $settings as $key => $value ) {
            update_option( 'mq_' . $key, $value );
        }
        
        wp_send_json_success( [ 'message' => __( 'Settings saved successfully', 'money-quiz' ) ] );
    }
}

// Patch prospect deletion
if ( ! function_exists( 'mq_delete_prospect_patched' ) ) {
    function mq_delete_prospect_patched() {
        global $wpdb;
        
        // Security checks
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( [ 'message' => 'Insufficient permissions' ], 403 );
        }
        
        if ( ! mq_verify_security( 'mq_delete_prospect' ) ) {
            wp_send_json_error( [ 'message' => 'Security check failed' ], 403 );
        }
        
        $prospect_id = absint( $_POST['prospect_id'] ?? 0 );
        
        if ( ! $prospect_id ) {
            wp_send_json_error( [ 'message' => 'Invalid prospect ID' ] );
        }
        
        // Use modern repository if available
        if ( class_exists( '\MoneyQuiz\Core\Plugin' ) ) {
            try {
                $container = \MoneyQuiz\Core\Plugin::instance()->get_container();
                $prospect_repo = $container->get( 'prospect_repository' );
                $result = $prospect_repo->delete( $prospect_id );
                
                if ( $result ) {
                    wp_send_json_success( [ 'message' => 'Prospect deleted successfully' ] );
                } else {
                    wp_send_json_error( [ 'message' => 'Failed to delete prospect' ] );
                }
            } catch ( \Exception $e ) {
                // Fall through to legacy
            }
        }
        
        // Safe deletion
        $result = $wpdb->delete(
            $wpdb->prefix . 'mq_prospects',
            [ 'id' => $prospect_id ],
            [ '%d' ]
        );
        
        if ( $result ) {
            wp_send_json_success( [ 'message' => 'Prospect deleted successfully' ] );
        } else {
            wp_send_json_error( [ 'message' => 'Failed to delete prospect' ] );
        }
    }
}

// Patch CSV export
if ( ! function_exists( 'mq_export_csv_patched' ) ) {
    function mq_export_csv_patched() {
        // Security checks
        if ( ! current_user_can( 'export' ) ) {
            wp_die( 'Insufficient permissions' );
        }
        
        if ( ! mq_verify_security( 'mq_export_csv' ) ) {
            wp_die( 'Security check failed' );
        }
        
        // Use modern export if available
        if ( class_exists( '\MoneyQuiz\Admin\Controllers\ResultsController' ) ) {
            $controller = new \MoneyQuiz\Admin\Controllers\ResultsController();
            $controller->export_csv();
            return;
        }
        
        // Get data safely
        global $wpdb;
        
        $results = mq_safe_get_results(
            "SELECT p.*, a.title as archetype 
             FROM {$wpdb->prefix}mq_prospects p
             LEFT JOIN {$wpdb->prefix}mq_archetypes a ON p.archetype_id = a.id
             ORDER BY p.created_at DESC"
        );
        
        // Set headers
        header( 'Content-Type: text/csv; charset=utf-8' );
        header( 'Content-Disposition: attachment; filename="money-quiz-prospects-' . date( 'Y-m-d' ) . '.csv"' );
        
        // Open output stream
        $output = fopen( 'php://output', 'w' );
        
        // Add BOM for Excel
        fprintf( $output, chr(0xEF) . chr(0xBB) . chr(0xBF) );
        
        // Headers
        fputcsv( $output, [
            'ID',
            'Email',
            'First Name',
            'Last Name',
            'Phone',
            'Company',
            'Archetype',
            'Score',
            'Created Date',
            'Last Quiz Date'
        ] );
        
        // Data
        foreach ( $results as $row ) {
            fputcsv( $output, [
                $row->id,
                $row->email,
                $row->first_name,
                $row->last_name,
                $row->phone,
                $row->company,
                $row->archetype,
                $row->score,
                $row->created_at,
                $row->last_quiz_date
            ] );
        }
        
        fclose( $output );
        exit;
    }
}

// Replace original AJAX handlers
add_action( 'init', function() {
    // Settings
    remove_action( 'wp_ajax_mq_save_settings', 'mq_save_settings' );
    add_action( 'wp_ajax_mq_save_settings', 'mq_save_settings_patched' );
    
    // Prospect deletion
    remove_action( 'wp_ajax_mq_delete_prospect', 'mq_delete_prospect' );
    add_action( 'wp_ajax_mq_delete_prospect', 'mq_delete_prospect_patched' );
    
    // CSV export
    remove_action( 'wp_ajax_mq_export_csv', 'mq_export_csv' );
    add_action( 'wp_ajax_mq_export_csv', 'mq_export_csv_patched' );
}, 20 );

// Add nonce fields to admin forms automatically
add_action( 'admin_footer', function() {
    if ( ! isset( $_GET['page'] ) || strpos( $_GET['page'], 'moneyquiz' ) === false ) {
        return;
    }
    ?>
    <script>
    jQuery(document).ready(function($) {
        // Add nonce to all forms without one
        $('form').each(function() {
            var $form = $(this);
            if (!$form.find('input[name="_wpnonce"]').length) {
                $form.append('<?php wp_nonce_field( 'mq_admin_action', '_wpnonce', false ); ?>');
                <?php if ( class_exists( '\MoneyQuiz\Security\CsrfManager' ) ) : ?>
                    <?php $csrf = new \MoneyQuiz\Security\CsrfManager(); ?>
                    $form.append('<?php echo $csrf->get_token_field( 'admin_form' ); ?>');
                <?php endif; ?>
            }
        });
        
        // Add security headers to AJAX requests
        $(document).ajaxSend(function(event, xhr, settings) {
            if (settings.url.indexOf('admin-ajax.php') !== -1) {
                xhr.setRequestHeader('X-WP-Nonce', '<?php echo wp_create_nonce( 'wp_rest' ); ?>');
                <?php if ( class_exists( '\MoneyQuiz\Security\CsrfManager' ) ) : ?>
                    var csrfToken = $('input[name="_mq_csrf_token"]').first().val();
                    if (csrfToken) {
                        xhr.setRequestHeader('X-CSRF-Token', csrfToken);
                    }
                <?php endif; ?>
            }
        });
    });
    </script>
    <?php
} );
<?php
/**
 * Notice Manager for Money Quiz Safe Wrapper
 */

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Notice Manager Class
 */
class MoneyQuiz_Notice_Manager {
    
    /**
     * @var array Notices to display
     */
    private $notices = array();
    
    /**
     * @var string Option key for persistent notices
     */
    private $option_key = 'mq_safe_wrapper_notices';
    
    /**
     * @var string Option key for dismissed notices
     */
    private $dismissal_key = 'mq_safe_wrapper_dismissed';
    
    /**
     * Constructor
     */
    public function __construct() {
        add_action( 'admin_notices', array( $this, 'display_notices' ) );
        add_action( 'wp_ajax_mq_dismiss_notice', array( $this, 'ajax_dismiss_notice' ) );
        add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_scripts' ) );
        
        // Load persistent notices
        $this->load_notices();
    }
    
    /**
     * Add a notice
     */
    public function add_notice( $id, $message, $type = 'info', $options = array() ) {
        $defaults = array(
            'dismissible' => true,
            'dismissal_duration' => 7 * DAY_IN_SECONDS,
            'capability' => 'manage_options',
            'screens' => array(), // Empty = all screens
            'persistent' => true, // Store in database
            'action_button' => null,
            'priority' => 10,
        );
        
        $options = wp_parse_args( $options, $defaults );
        
        $this->notices[ $id ] = array(
            'message' => $message,
            'type' => $type,
            'options' => $options,
            'timestamp' => current_time( 'timestamp' ),
        );
        
        if ( $options['persistent'] ) {
            $this->save_notices();
        }
    }
    
    /**
     * Display notices
     */
    public function display_notices() {
        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }
        
        $screen = get_current_screen();
        $dismissed = get_option( $this->dismissal_key, array() );
        
        // Sort notices by priority
        uasort( $this->notices, function( $a, $b ) {
            return $b['options']['priority'] - $a['options']['priority'];
        } );
        
        foreach ( $this->notices as $id => $notice ) {
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
    private function render_notice( $id, $notice ) {
        $classes = array(
            'notice',
            'notice-' . $notice['type'],
            'mq-safe-wrapper-notice',
        );
        
        if ( $notice['options']['dismissible'] ) {
            $classes[] = 'is-dismissible';
        }
        
        // Add special styling for critical notices
        if ( $notice['type'] === 'error' ) {
            $classes[] = 'notice-alt';
        }
        
        ?>
        <div class="<?php echo esc_attr( implode( ' ', $classes ) ); ?>" 
             data-notice-id="<?php echo esc_attr( $id ); ?>"
             data-dismissal-duration="<?php echo esc_attr( $notice['options']['dismissal_duration'] ); ?>">
            
            <?php if ( $notice['type'] === 'error' ) : ?>
                <p><span class="dashicons dashicons-warning" style="color: #dc3232;"></span> 
            <?php elseif ( $notice['type'] === 'warning' ) : ?>
                <p><span class="dashicons dashicons-info" style="color: #ffb900;"></span> 
            <?php elseif ( $notice['type'] === 'success' ) : ?>
                <p><span class="dashicons dashicons-yes" style="color: #46b450;"></span> 
            <?php else : ?>
                <p>
            <?php endif; ?>
            
            <?php echo wp_kses_post( $notice['message'] ); ?></p>
            
            <?php if ( $notice['options']['action_button'] ) : ?>
                <p>
                    <a href="<?php echo esc_url( $notice['options']['action_button']['url'] ); ?>" 
                       class="button button-primary">
                        <?php echo esc_html( $notice['options']['action_button']['text'] ); ?>
                    </a>
                    
                    <?php if ( isset( $notice['options']['action_button']['secondary'] ) ) : ?>
                        <a href="<?php echo esc_url( $notice['options']['action_button']['secondary']['url'] ); ?>" 
                           class="button">
                            <?php echo esc_html( $notice['options']['action_button']['secondary']['text'] ); ?>
                        </a>
                    <?php endif; ?>
                </p>
            <?php endif; ?>
            
            <?php if ( $notice['options']['dismissible'] ) : ?>
                <button type="button" class="notice-dismiss">
                    <span class="screen-reader-text"><?php _e( 'Dismiss this notice.', 'money-quiz' ); ?></span>
                </button>
            <?php endif; ?>
        </div>
        <?php
    }
    
    /**
     * Enqueue scripts
     */
    public function enqueue_scripts() {
        wp_add_inline_script( 'jquery', "
            jQuery(document).ready(function($) {
                $('.mq-safe-wrapper-notice.is-dismissible').on('click', '.notice-dismiss', function() {
                    var notice = $(this).parent();
                    var noticeId = notice.data('notice-id');
                    var duration = notice.data('dismissal-duration');
                    
                    $.post(ajaxurl, {
                        action: 'mq_dismiss_notice',
                        notice_id: noticeId,
                        duration: duration,
                        _wpnonce: '" . wp_create_nonce( 'mq_dismiss_notice' ) . "'
                    });
                });
            });
        " );
    }
    
    /**
     * AJAX handler for dismissing notices
     */
    public function ajax_dismiss_notice() {
        check_ajax_referer( 'mq_dismiss_notice' );
        
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die();
        }
        
        $notice_id = sanitize_key( $_POST['notice_id'] );
        $duration = absint( $_POST['duration'] );
        
        if ( $notice_id ) {
            $dismissed = get_option( $this->dismissal_key, array() );
            $dismissed[ $notice_id ] = time() + $duration;
            update_option( $this->dismissal_key, $dismissed );
            
            // Remove from persistent notices if non-critical
            if ( isset( $this->notices[ $notice_id ] ) && 
                 $this->notices[ $notice_id ]['type'] !== 'error' ) {
                unset( $this->notices[ $notice_id ] );
                $this->save_notices();
            }
        }
        
        wp_die();
    }
    
    /**
     * Load persistent notices
     */
    private function load_notices() {
        $saved_notices = get_option( $this->option_key, array() );
        
        if ( is_array( $saved_notices ) ) {
            $this->notices = array_merge( $this->notices, $saved_notices );
        }
    }
    
    /**
     * Save persistent notices
     */
    private function save_notices() {
        // Only save persistent notices
        $persistent_notices = array_filter( $this->notices, function( $notice ) {
            return $notice['options']['persistent'];
        } );
        
        update_option( $this->option_key, $persistent_notices );
    }
    
    /**
     * Get all notices
     */
    public function get_notices() {
        return $this->notices;
    }
    
    /**
     * Clear all notices
     */
    public function clear_notices() {
        $this->notices = array();
        delete_option( $this->option_key );
        delete_option( $this->dismissal_key );
    }
    
    /**
     * Remove a specific notice
     */
    public function remove_notice( $id ) {
        if ( isset( $this->notices[ $id ] ) ) {
            unset( $this->notices[ $id ] );
            $this->save_notices();
        }
    }
}
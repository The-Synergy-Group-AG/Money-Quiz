<?php
/**
 * Settings Manager
 *
 * @package MoneyQuiz
 * @since 4.0.0
 */

namespace MoneyQuiz\Admin;

/**
 * Manages plugin settings using WordPress Settings API
 */
class SettingsManager {
    
    /**
     * @var string Settings option name
     */
    private const OPTION_NAME = 'money_quiz_settings';
    
    /**
     * @var string Settings page slug
     */
    private const PAGE_SLUG = 'money-quiz-settings';
    
    /**
     * @var array Default settings
     */
    private array $defaults = [
        'general' => [
            'company_name' => '',
            'admin_email' => '',
            'from_email' => '',
            'from_name' => 'Money Quiz',
            'enable_notifications' => true,
        ],
        'email' => [
            'send_to_admin' => true,
            'send_to_prospect' => true,
            'admin_subject' => 'New Money Quiz Submission',
            'prospect_subject' => 'Your Money Quiz Results',
            'admin_template' => '',
            'prospect_template' => '',
        ],
        'advanced' => [
            'delete_data_on_uninstall' => false,
            'enable_debug_mode' => false,
            'cache_duration' => 3600, // 1 hour
            'results_per_page' => 20,
            'enable_recaptcha' => false,
            'recaptcha_site_key' => '',
            'recaptcha_secret_key' => '',
        ],
        'integrations' => [
            'mailchimp_api_key' => '',
            'mailchimp_list_id' => '',
            'zapier_webhook_url' => '',
            'enable_crm_sync' => false,
        ],
    ];
    
    /**
     * @var array Cached settings
     */
    private ?array $settings = null;
    
    /**
     * Initialize settings
     * 
     * @return void
     */
    public function init(): void {
        add_action( 'admin_init', [ $this, 'register_settings' ] );
    }
    
    /**
     * Register settings
     * 
     * @return void
     */
    public function register_settings(): void {
        // Register main setting
        register_setting(
            self::PAGE_SLUG,
            self::OPTION_NAME,
            [
                'type' => 'array',
                'sanitize_callback' => [ $this, 'sanitize_settings' ],
                'default' => $this->defaults,
            ]
        );
        
        // Add sections
        $this->add_general_section();
        $this->add_email_section();
        $this->add_advanced_section();
        $this->add_integrations_section();
    }
    
    /**
     * Add general settings section
     * 
     * @return void
     */
    private function add_general_section(): void {
        add_settings_section(
            'money_quiz_general',
            __( 'General Settings', 'money-quiz' ),
            [ $this, 'render_general_section' ],
            self::PAGE_SLUG
        );
        
        // Company Name
        add_settings_field(
            'company_name',
            __( 'Company Name', 'money-quiz' ),
            [ $this, 'render_text_field' ],
            self::PAGE_SLUG,
            'money_quiz_general',
            [
                'label_for' => 'company_name',
                'section' => 'general',
                'description' => __( 'Your company name for email signatures', 'money-quiz' ),
            ]
        );
        
        // Admin Email
        add_settings_field(
            'admin_email',
            __( 'Admin Email', 'money-quiz' ),
            [ $this, 'render_email_field' ],
            self::PAGE_SLUG,
            'money_quiz_general',
            [
                'label_for' => 'admin_email',
                'section' => 'general',
                'description' => __( 'Email address for admin notifications', 'money-quiz' ),
            ]
        );
        
        // From Email
        add_settings_field(
            'from_email',
            __( 'From Email', 'money-quiz' ),
            [ $this, 'render_email_field' ],
            self::PAGE_SLUG,
            'money_quiz_general',
            [
                'label_for' => 'from_email',
                'section' => 'general',
                'description' => __( 'Email address used as sender', 'money-quiz' ),
            ]
        );
        
        // From Name
        add_settings_field(
            'from_name',
            __( 'From Name', 'money-quiz' ),
            [ $this, 'render_text_field' ],
            self::PAGE_SLUG,
            'money_quiz_general',
            [
                'label_for' => 'from_name',
                'section' => 'general',
                'description' => __( 'Name used as email sender', 'money-quiz' ),
            ]
        );
        
        // Enable Notifications
        add_settings_field(
            'enable_notifications',
            __( 'Enable Notifications', 'money-quiz' ),
            [ $this, 'render_checkbox_field' ],
            self::PAGE_SLUG,
            'money_quiz_general',
            [
                'label_for' => 'enable_notifications',
                'section' => 'general',
                'description' => __( 'Enable email notifications for quiz submissions', 'money-quiz' ),
            ]
        );
    }
    
    /**
     * Add email settings section
     * 
     * @return void
     */
    private function add_email_section(): void {
        add_settings_section(
            'money_quiz_email',
            __( 'Email Settings', 'money-quiz' ),
            [ $this, 'render_email_section' ],
            self::PAGE_SLUG
        );
        
        // Send to Admin
        add_settings_field(
            'send_to_admin',
            __( 'Notify Admin', 'money-quiz' ),
            [ $this, 'render_checkbox_field' ],
            self::PAGE_SLUG,
            'money_quiz_email',
            [
                'label_for' => 'send_to_admin',
                'section' => 'email',
                'description' => __( 'Send notification to admin on quiz completion', 'money-quiz' ),
            ]
        );
        
        // Send to Prospect
        add_settings_field(
            'send_to_prospect',
            __( 'Send Results to User', 'money-quiz' ),
            [ $this, 'render_checkbox_field' ],
            self::PAGE_SLUG,
            'money_quiz_email',
            [
                'label_for' => 'send_to_prospect',
                'section' => 'email',
                'description' => __( 'Send quiz results to the user', 'money-quiz' ),
            ]
        );
        
        // Admin Subject
        add_settings_field(
            'admin_subject',
            __( 'Admin Email Subject', 'money-quiz' ),
            [ $this, 'render_text_field' ],
            self::PAGE_SLUG,
            'money_quiz_email',
            [
                'label_for' => 'admin_subject',
                'section' => 'email',
                'description' => __( 'Subject line for admin notification emails', 'money-quiz' ),
            ]
        );
        
        // Prospect Subject
        add_settings_field(
            'prospect_subject',
            __( 'User Email Subject', 'money-quiz' ),
            [ $this, 'render_text_field' ],
            self::PAGE_SLUG,
            'money_quiz_email',
            [
                'label_for' => 'prospect_subject',
                'section' => 'email',
                'description' => __( 'Subject line for user result emails', 'money-quiz' ),
            ]
        );
        
        // Admin Template
        add_settings_field(
            'admin_template',
            __( 'Admin Email Template', 'money-quiz' ),
            [ $this, 'render_textarea_field' ],
            self::PAGE_SLUG,
            'money_quiz_email',
            [
                'label_for' => 'admin_template',
                'section' => 'email',
                'description' => __( 'Template for admin notification emails. Available tags: {name}, {email}, {archetype}, {score}', 'money-quiz' ),
                'rows' => 10,
            ]
        );
        
        // Prospect Template
        add_settings_field(
            'prospect_template',
            __( 'User Email Template', 'money-quiz' ),
            [ $this, 'render_textarea_field' ],
            self::PAGE_SLUG,
            'money_quiz_email',
            [
                'label_for' => 'prospect_template',
                'section' => 'email',
                'description' => __( 'Template for user result emails. Available tags: {name}, {email}, {archetype}, {score}, {results_link}', 'money-quiz' ),
                'rows' => 10,
            ]
        );
    }
    
    /**
     * Add advanced settings section
     * 
     * @return void
     */
    private function add_advanced_section(): void {
        add_settings_section(
            'money_quiz_advanced',
            __( 'Advanced Settings', 'money-quiz' ),
            [ $this, 'render_advanced_section' ],
            self::PAGE_SLUG
        );
        
        // Delete Data on Uninstall
        add_settings_field(
            'delete_data_on_uninstall',
            __( 'Delete Data on Uninstall', 'money-quiz' ),
            [ $this, 'render_checkbox_field' ],
            self::PAGE_SLUG,
            'money_quiz_advanced',
            [
                'label_for' => 'delete_data_on_uninstall',
                'section' => 'advanced',
                'description' => __( 'Remove all plugin data when uninstalling', 'money-quiz' ),
            ]
        );
        
        // Debug Mode
        add_settings_field(
            'enable_debug_mode',
            __( 'Enable Debug Mode', 'money-quiz' ),
            [ $this, 'render_checkbox_field' ],
            self::PAGE_SLUG,
            'money_quiz_advanced',
            [
                'label_for' => 'enable_debug_mode',
                'section' => 'advanced',
                'description' => __( 'Enable debug logging for troubleshooting', 'money-quiz' ),
            ]
        );
        
        // Cache Duration
        add_settings_field(
            'cache_duration',
            __( 'Cache Duration', 'money-quiz' ),
            [ $this, 'render_number_field' ],
            self::PAGE_SLUG,
            'money_quiz_advanced',
            [
                'label_for' => 'cache_duration',
                'section' => 'advanced',
                'description' => __( 'Cache duration in seconds (default: 3600)', 'money-quiz' ),
                'min' => 0,
                'max' => 86400,
            ]
        );
        
        // Results per Page
        add_settings_field(
            'results_per_page',
            __( 'Results per Page', 'money-quiz' ),
            [ $this, 'render_number_field' ],
            self::PAGE_SLUG,
            'money_quiz_advanced',
            [
                'label_for' => 'results_per_page',
                'section' => 'advanced',
                'description' => __( 'Number of results to show per page in admin', 'money-quiz' ),
                'min' => 10,
                'max' => 100,
            ]
        );
        
        // reCAPTCHA
        add_settings_field(
            'enable_recaptcha',
            __( 'Enable reCAPTCHA', 'money-quiz' ),
            [ $this, 'render_checkbox_field' ],
            self::PAGE_SLUG,
            'money_quiz_advanced',
            [
                'label_for' => 'enable_recaptcha',
                'section' => 'advanced',
                'description' => __( 'Enable Google reCAPTCHA for spam protection', 'money-quiz' ),
            ]
        );
        
        // reCAPTCHA Site Key
        add_settings_field(
            'recaptcha_site_key',
            __( 'reCAPTCHA Site Key', 'money-quiz' ),
            [ $this, 'render_text_field' ],
            self::PAGE_SLUG,
            'money_quiz_advanced',
            [
                'label_for' => 'recaptcha_site_key',
                'section' => 'advanced',
                'description' => __( 'Google reCAPTCHA site key', 'money-quiz' ),
            ]
        );
        
        // reCAPTCHA Secret Key
        add_settings_field(
            'recaptcha_secret_key',
            __( 'reCAPTCHA Secret Key', 'money-quiz' ),
            [ $this, 'render_password_field' ],
            self::PAGE_SLUG,
            'money_quiz_advanced',
            [
                'label_for' => 'recaptcha_secret_key',
                'section' => 'advanced',
                'description' => __( 'Google reCAPTCHA secret key', 'money-quiz' ),
            ]
        );
    }
    
    /**
     * Add integrations section
     * 
     * @return void
     */
    private function add_integrations_section(): void {
        add_settings_section(
            'money_quiz_integrations',
            __( 'Integrations', 'money-quiz' ),
            [ $this, 'render_integrations_section' ],
            self::PAGE_SLUG
        );
        
        // Mailchimp API Key
        add_settings_field(
            'mailchimp_api_key',
            __( 'Mailchimp API Key', 'money-quiz' ),
            [ $this, 'render_password_field' ],
            self::PAGE_SLUG,
            'money_quiz_integrations',
            [
                'label_for' => 'mailchimp_api_key',
                'section' => 'integrations',
                'description' => __( 'Your Mailchimp API key for list integration', 'money-quiz' ),
            ]
        );
        
        // Mailchimp List ID
        add_settings_field(
            'mailchimp_list_id',
            __( 'Mailchimp List ID', 'money-quiz' ),
            [ $this, 'render_text_field' ],
            self::PAGE_SLUG,
            'money_quiz_integrations',
            [
                'label_for' => 'mailchimp_list_id',
                'section' => 'integrations',
                'description' => __( 'The Mailchimp list ID to add subscribers to', 'money-quiz' ),
            ]
        );
        
        // Zapier Webhook
        add_settings_field(
            'zapier_webhook_url',
            __( 'Zapier Webhook URL', 'money-quiz' ),
            [ $this, 'render_url_field' ],
            self::PAGE_SLUG,
            'money_quiz_integrations',
            [
                'label_for' => 'zapier_webhook_url',
                'section' => 'integrations',
                'description' => __( 'Zapier webhook URL for sending quiz data', 'money-quiz' ),
            ]
        );
        
        // CRM Sync
        add_settings_field(
            'enable_crm_sync',
            __( 'Enable CRM Sync', 'money-quiz' ),
            [ $this, 'render_checkbox_field' ],
            self::PAGE_SLUG,
            'money_quiz_integrations',
            [
                'label_for' => 'enable_crm_sync',
                'section' => 'integrations',
                'description' => __( 'Sync quiz results with your CRM', 'money-quiz' ),
            ]
        );
    }
    
    /**
     * Render general section description
     * 
     * @return void
     */
    public function render_general_section(): void {
        echo '<p>' . __( 'Configure general plugin settings.', 'money-quiz' ) . '</p>';
    }
    
    /**
     * Render email section description
     * 
     * @return void
     */
    public function render_email_section(): void {
        echo '<p>' . __( 'Configure email notifications and templates.', 'money-quiz' ) . '</p>';
    }
    
    /**
     * Render advanced section description
     * 
     * @return void
     */
    public function render_advanced_section(): void {
        echo '<p>' . __( 'Advanced configuration options.', 'money-quiz' ) . '</p>';
    }
    
    /**
     * Render integrations section description
     * 
     * @return void
     */
    public function render_integrations_section(): void {
        echo '<p>' . __( 'Configure third-party integrations.', 'money-quiz' ) . '</p>';
    }
    
    /**
     * Render text field
     * 
     * @param array $args Field arguments
     * @return void
     */
    public function render_text_field( array $args ): void {
        $value = $this->get_option( $args['section'], $args['label_for'] );
        ?>
        <input type="text" 
               id="<?php echo esc_attr( $args['label_for'] ); ?>" 
               name="<?php echo esc_attr( self::OPTION_NAME . '[' . $args['section'] . '][' . $args['label_for'] . ']' ); ?>"
               value="<?php echo esc_attr( $value ); ?>"
               class="regular-text" />
        <?php if ( ! empty( $args['description'] ) ) : ?>
            <p class="description"><?php echo esc_html( $args['description'] ); ?></p>
        <?php endif;
    }
    
    /**
     * Render email field
     * 
     * @param array $args Field arguments
     * @return void
     */
    public function render_email_field( array $args ): void {
        $value = $this->get_option( $args['section'], $args['label_for'] );
        ?>
        <input type="email" 
               id="<?php echo esc_attr( $args['label_for'] ); ?>" 
               name="<?php echo esc_attr( self::OPTION_NAME . '[' . $args['section'] . '][' . $args['label_for'] . ']' ); ?>"
               value="<?php echo esc_attr( $value ); ?>"
               class="regular-text" />
        <?php if ( ! empty( $args['description'] ) ) : ?>
            <p class="description"><?php echo esc_html( $args['description'] ); ?></p>
        <?php endif;
    }
    
    /**
     * Render URL field
     * 
     * @param array $args Field arguments
     * @return void
     */
    public function render_url_field( array $args ): void {
        $value = $this->get_option( $args['section'], $args['label_for'] );
        ?>
        <input type="url" 
               id="<?php echo esc_attr( $args['label_for'] ); ?>" 
               name="<?php echo esc_attr( self::OPTION_NAME . '[' . $args['section'] . '][' . $args['label_for'] . ']' ); ?>"
               value="<?php echo esc_url( $value ); ?>"
               class="regular-text" />
        <?php if ( ! empty( $args['description'] ) ) : ?>
            <p class="description"><?php echo esc_html( $args['description'] ); ?></p>
        <?php endif;
    }
    
    /**
     * Render password field
     * 
     * @param array $args Field arguments
     * @return void
     */
    public function render_password_field( array $args ): void {
        $value = $this->get_option( $args['section'], $args['label_for'] );
        ?>
        <input type="password" 
               id="<?php echo esc_attr( $args['label_for'] ); ?>" 
               name="<?php echo esc_attr( self::OPTION_NAME . '[' . $args['section'] . '][' . $args['label_for'] . ']' ); ?>"
               value="<?php echo esc_attr( $value ); ?>"
               class="regular-text" />
        <?php if ( ! empty( $args['description'] ) ) : ?>
            <p class="description"><?php echo esc_html( $args['description'] ); ?></p>
        <?php endif;
    }
    
    /**
     * Render number field
     * 
     * @param array $args Field arguments
     * @return void
     */
    public function render_number_field( array $args ): void {
        $value = $this->get_option( $args['section'], $args['label_for'] );
        ?>
        <input type="number" 
               id="<?php echo esc_attr( $args['label_for'] ); ?>" 
               name="<?php echo esc_attr( self::OPTION_NAME . '[' . $args['section'] . '][' . $args['label_for'] . ']' ); ?>"
               value="<?php echo esc_attr( $value ); ?>"
               min="<?php echo esc_attr( $args['min'] ?? '' ); ?>"
               max="<?php echo esc_attr( $args['max'] ?? '' ); ?>"
               class="small-text" />
        <?php if ( ! empty( $args['description'] ) ) : ?>
            <p class="description"><?php echo esc_html( $args['description'] ); ?></p>
        <?php endif;
    }
    
    /**
     * Render checkbox field
     * 
     * @param array $args Field arguments
     * @return void
     */
    public function render_checkbox_field( array $args ): void {
        $value = $this->get_option( $args['section'], $args['label_for'] );
        ?>
        <input type="checkbox" 
               id="<?php echo esc_attr( $args['label_for'] ); ?>" 
               name="<?php echo esc_attr( self::OPTION_NAME . '[' . $args['section'] . '][' . $args['label_for'] . ']' ); ?>"
               value="1"
               <?php checked( $value, true ); ?> />
        <?php if ( ! empty( $args['description'] ) ) : ?>
            <label for="<?php echo esc_attr( $args['label_for'] ); ?>">
                <?php echo esc_html( $args['description'] ); ?>
            </label>
        <?php endif;
    }
    
    /**
     * Render textarea field
     * 
     * @param array $args Field arguments
     * @return void
     */
    public function render_textarea_field( array $args ): void {
        $value = $this->get_option( $args['section'], $args['label_for'] );
        ?>
        <textarea id="<?php echo esc_attr( $args['label_for'] ); ?>" 
                  name="<?php echo esc_attr( self::OPTION_NAME . '[' . $args['section'] . '][' . $args['label_for'] . ']' ); ?>"
                  rows="<?php echo esc_attr( $args['rows'] ?? 5 ); ?>"
                  class="large-text"><?php echo esc_textarea( $value ); ?></textarea>
        <?php if ( ! empty( $args['description'] ) ) : ?>
            <p class="description"><?php echo esc_html( $args['description'] ); ?></p>
        <?php endif;
    }
    
    /**
     * Sanitize settings
     * 
     * @param array $input Raw input
     * @return array Sanitized settings
     */
    public function sanitize_settings( array $input ): array {
        $sanitized = [];
        
        // Sanitize general settings
        if ( isset( $input['general'] ) ) {
            $sanitized['general'] = [
                'company_name' => sanitize_text_field( $input['general']['company_name'] ?? '' ),
                'admin_email' => sanitize_email( $input['general']['admin_email'] ?? '' ),
                'from_email' => sanitize_email( $input['general']['from_email'] ?? '' ),
                'from_name' => sanitize_text_field( $input['general']['from_name'] ?? '' ),
                'enable_notifications' => ! empty( $input['general']['enable_notifications'] ),
            ];
        }
        
        // Sanitize email settings
        if ( isset( $input['email'] ) ) {
            $sanitized['email'] = [
                'send_to_admin' => ! empty( $input['email']['send_to_admin'] ),
                'send_to_prospect' => ! empty( $input['email']['send_to_prospect'] ),
                'admin_subject' => sanitize_text_field( $input['email']['admin_subject'] ?? '' ),
                'prospect_subject' => sanitize_text_field( $input['email']['prospect_subject'] ?? '' ),
                'admin_template' => wp_kses_post( $input['email']['admin_template'] ?? '' ),
                'prospect_template' => wp_kses_post( $input['email']['prospect_template'] ?? '' ),
            ];
        }
        
        // Sanitize advanced settings
        if ( isset( $input['advanced'] ) ) {
            $sanitized['advanced'] = [
                'delete_data_on_uninstall' => ! empty( $input['advanced']['delete_data_on_uninstall'] ),
                'enable_debug_mode' => ! empty( $input['advanced']['enable_debug_mode'] ),
                'cache_duration' => absint( $input['advanced']['cache_duration'] ?? 3600 ),
                'results_per_page' => absint( $input['advanced']['results_per_page'] ?? 20 ),
                'enable_recaptcha' => ! empty( $input['advanced']['enable_recaptcha'] ),
                'recaptcha_site_key' => sanitize_text_field( $input['advanced']['recaptcha_site_key'] ?? '' ),
                'recaptcha_secret_key' => sanitize_text_field( $input['advanced']['recaptcha_secret_key'] ?? '' ),
            ];
        }
        
        // Sanitize integrations
        if ( isset( $input['integrations'] ) ) {
            $sanitized['integrations'] = [
                'mailchimp_api_key' => sanitize_text_field( $input['integrations']['mailchimp_api_key'] ?? '' ),
                'mailchimp_list_id' => sanitize_text_field( $input['integrations']['mailchimp_list_id'] ?? '' ),
                'zapier_webhook_url' => esc_url_raw( $input['integrations']['zapier_webhook_url'] ?? '' ),
                'enable_crm_sync' => ! empty( $input['integrations']['enable_crm_sync'] ),
            ];
        }
        
        // Merge with existing settings to preserve other sections
        $existing = $this->get_all_settings();
        return array_merge( $existing, $sanitized );
    }
    
    /**
     * Get all settings
     * 
     * @return array
     */
    public function get_all_settings(): array {
        if ( is_null( $this->settings ) ) {
            $this->settings = get_option( self::OPTION_NAME, $this->defaults );
            
            // Ensure all default keys exist
            foreach ( $this->defaults as $section => $fields ) {
                if ( ! isset( $this->settings[ $section ] ) ) {
                    $this->settings[ $section ] = $fields;
                } else {
                    $this->settings[ $section ] = array_merge( $fields, $this->settings[ $section ] );
                }
            }
        }
        
        return $this->settings;
    }
    
    /**
     * Get specific option value
     * 
     * @param string $section Settings section
     * @param string $option  Option name
     * @param mixed  $default Default value
     * @return mixed
     */
    public function get_option( string $section, string $option, $default = null ) {
        $settings = $this->get_all_settings();
        
        if ( isset( $settings[ $section ][ $option ] ) ) {
            return $settings[ $section ][ $option ];
        }
        
        return $default ?? ( $this->defaults[ $section ][ $option ] ?? null );
    }
    
    /**
     * Update specific option
     * 
     * @param string $section Settings section
     * @param string $option  Option name
     * @param mixed  $value   Option value
     * @return bool
     */
    public function update_option( string $section, string $option, $value ): bool {
        $settings = $this->get_all_settings();
        
        if ( ! isset( $settings[ $section ] ) ) {
            $settings[ $section ] = [];
        }
        
        $settings[ $section ][ $option ] = $value;
        
        $updated = update_option( self::OPTION_NAME, $settings );
        
        if ( $updated ) {
            $this->settings = $settings;
        }
        
        return $updated;
    }
    
    /**
     * Reset settings to defaults
     * 
     * @param string|null $section Specific section to reset, or null for all
     * @return bool
     */
    public function reset_to_defaults( ?string $section = null ): bool {
        if ( $section && isset( $this->defaults[ $section ] ) ) {
            $settings = $this->get_all_settings();
            $settings[ $section ] = $this->defaults[ $section ];
            $updated = update_option( self::OPTION_NAME, $settings );
        } else {
            $updated = update_option( self::OPTION_NAME, $this->defaults );
        }
        
        if ( $updated ) {
            $this->settings = null; // Clear cache
        }
        
        return $updated;
    }
    
    /**
     * Check if debug mode is enabled
     * 
     * @return bool
     */
    public function is_debug_enabled(): bool {
        return (bool) $this->get_option( 'advanced', 'enable_debug_mode', false );
    }
    
    /**
     * Get cache duration
     * 
     * @return int
     */
    public function get_cache_duration(): int {
        return (int) $this->get_option( 'advanced', 'cache_duration', 3600 );
    }
    
    /**
     * Check if notifications are enabled
     * 
     * @return bool
     */
    public function are_notifications_enabled(): bool {
        return (bool) $this->get_option( 'general', 'enable_notifications', true );
    }
    
    /**
     * Get email configuration
     * 
     * @return array
     */
    public function get_email_config(): array {
        return [
            'from_email' => $this->get_option( 'general', 'from_email', get_option( 'admin_email' ) ),
            'from_name' => $this->get_option( 'general', 'from_name', 'Money Quiz' ),
            'admin_email' => $this->get_option( 'general', 'admin_email', get_option( 'admin_email' ) ),
            'send_to_admin' => $this->get_option( 'email', 'send_to_admin', true ),
            'send_to_prospect' => $this->get_option( 'email', 'send_to_prospect', true ),
            'admin_subject' => $this->get_option( 'email', 'admin_subject', 'New Money Quiz Submission' ),
            'prospect_subject' => $this->get_option( 'email', 'prospect_subject', 'Your Money Quiz Results' ),
            'admin_template' => $this->get_option( 'email', 'admin_template', '' ),
            'prospect_template' => $this->get_option( 'email', 'prospect_template', '' ),
        ];
    }
    
    /**
     * Export settings
     * 
     * @return string JSON encoded settings
     */
    public function export_settings(): string {
        return json_encode( $this->get_all_settings(), JSON_PRETTY_PRINT );
    }
    
    /**
     * Import settings
     * 
     * @param string $json JSON encoded settings
     * @return bool
     */
    public function import_settings( string $json ): bool {
        $settings = json_decode( $json, true );
        
        if ( ! is_array( $settings ) ) {
            return false;
        }
        
        // Validate structure
        foreach ( $settings as $section => $fields ) {
            if ( ! isset( $this->defaults[ $section ] ) || ! is_array( $fields ) ) {
                return false;
            }
        }
        
        // Sanitize and save
        $sanitized = $this->sanitize_settings( $settings );
        $updated = update_option( self::OPTION_NAME, $sanitized );
        
        if ( $updated ) {
            $this->settings = null; // Clear cache
        }
        
        return $updated;
    }
}
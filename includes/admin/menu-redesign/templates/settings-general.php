<?php
/**
 * General Settings Template
 * 
 * @package MoneyQuiz
 * @since 4.2.0
 */

// Security check
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// Handle settings save
if ( isset( $_POST['save_settings'] ) ) {
    check_admin_referer( 'money_quiz_general_settings' );
    
    // Company settings
    update_option( 'mq_company_title', sanitize_text_field( $_POST['company_title'] ) );
    update_option( 'mq_company_logo', esc_url_raw( $_POST['company_logo'] ) );
    update_option( 'mq_company_website', esc_url_raw( $_POST['company_website'] ) );
    update_option( 'mq_support_email', sanitize_email( $_POST['support_email'] ) );
    
    // Quiz settings
    update_option( 'mq_default_quiz_style', sanitize_text_field( $_POST['quiz_style'] ) );
    update_option( 'mq_results_display', sanitize_text_field( $_POST['results_display'] ) );
    update_option( 'mq_enable_progress_bar', isset( $_POST['enable_progress_bar'] ) ? 1 : 0 );
    update_option( 'mq_enable_timer', isset( $_POST['enable_timer'] ) ? 1 : 0 );
    update_option( 'mq_questions_per_page', intval( $_POST['questions_per_page'] ) );
    
    // Lead capture settings
    update_option( 'mq_require_email', isset( $_POST['require_email'] ) ? 1 : 0 );
    update_option( 'mq_email_position', sanitize_text_field( $_POST['email_position'] ) );
    update_option( 'mq_collect_phone', isset( $_POST['collect_phone'] ) ? 1 : 0 );
    update_option( 'mq_gdpr_enabled', isset( $_POST['gdpr_enabled'] ) ? 1 : 0 );
    update_option( 'mq_gdpr_text', wp_kses_post( $_POST['gdpr_text'] ) );
    
    // Menu redesign settings
    update_option( 'money_quiz_menu_redesign_enabled', isset( $_POST['menu_redesign_enabled'] ) ? 1 : 0 );
    update_option( 'money_quiz_menu_rollout_percentage', intval( $_POST['menu_rollout_percentage'] ) );
    
    echo '<div class="notice notice-success"><p>' . __( 'Settings saved successfully.', 'money-quiz' ) . '</p></div>';
}

// Get current settings with fallbacks
$company_title = get_option( 'mq_company_title', get_bloginfo( 'name' ) );
$company_logo = get_option( 'mq_company_logo', '' );
$company_website = get_option( 'mq_company_website', home_url() );
$support_email = get_option( 'mq_support_email', get_option( 'admin_email' ) );

$quiz_style = get_option( 'mq_default_quiz_style', 'standard' );
$results_display = get_option( 'mq_results_display', 'immediate' );
$enable_progress_bar = get_option( 'mq_enable_progress_bar', 1 );
$enable_timer = get_option( 'mq_enable_timer', 0 );
$questions_per_page = get_option( 'mq_questions_per_page', 1 );

$require_email = get_option( 'mq_require_email', 1 );
$email_position = get_option( 'mq_email_position', 'before_results' );
$collect_phone = get_option( 'mq_collect_phone', 0 );
$gdpr_enabled = get_option( 'mq_gdpr_enabled', 0 );
$gdpr_text = get_option( 'mq_gdpr_text', __( 'I agree to receive emails and accept the privacy policy.', 'money-quiz' ) );

$menu_redesign_enabled = get_option( 'money_quiz_menu_redesign_enabled', 0 );
$menu_rollout_percentage = get_option( 'money_quiz_menu_rollout_percentage', 0 );
?>

<div class="wrap mq-general-settings">
    
    <form method="post" class="settings-form">
        <?php wp_nonce_field( 'money_quiz_general_settings' ); ?>
        
        <!-- Company Information -->
        <div class="mq-card">
            <h2><?php _e( '🏢 Company Information', 'money-quiz' ); ?></h2>
            
            <table class="form-table">
                <tr>
                    <th scope="row">
                        <label for="company_title"><?php _e( 'Company Name', 'money-quiz' ); ?></label>
                    </th>
                    <td>
                        <input type="text" name="company_title" id="company_title" class="regular-text" 
                               value="<?php echo esc_attr( $company_title ); ?>" />
                        <p class="description"><?php _e( 'Your company or brand name', 'money-quiz' ); ?></p>
                    </td>
                </tr>
                
                <tr>
                    <th scope="row">
                        <label for="company_logo"><?php _e( 'Company Logo', 'money-quiz' ); ?></label>
                    </th>
                    <td>
                        <input type="url" name="company_logo" id="company_logo" class="large-text" 
                               value="<?php echo esc_url( $company_logo ); ?>" />
                        <button type="button" class="button" onclick="selectLogo()">
                            <?php _e( 'Select Logo', 'money-quiz' ); ?>
                        </button>
                        <?php if ( $company_logo ) : ?>
                            <div class="logo-preview" style="margin-top: 10px;">
                                <img src="<?php echo esc_url( $company_logo ); ?>" style="max-height: 60px;" />
                            </div>
                        <?php endif; ?>
                    </td>
                </tr>
                
                <tr>
                    <th scope="row">
                        <label for="company_website"><?php _e( 'Company Website', 'money-quiz' ); ?></label>
                    </th>
                    <td>
                        <input type="url" name="company_website" id="company_website" class="regular-text" 
                               value="<?php echo esc_url( $company_website ); ?>" />
                    </td>
                </tr>
                
                <tr>
                    <th scope="row">
                        <label for="support_email"><?php _e( 'Support Email', 'money-quiz' ); ?></label>
                    </th>
                    <td>
                        <input type="email" name="support_email" id="support_email" class="regular-text" 
                               value="<?php echo esc_attr( $support_email ); ?>" />
                        <p class="description"><?php _e( 'Email address for quiz notifications and support', 'money-quiz' ); ?></p>
                    </td>
                </tr>
            </table>
        </div>
        
        <!-- Quiz Display Settings -->
        <div class="mq-card">
            <h2><?php _e( '📊 Quiz Display Settings', 'money-quiz' ); ?></h2>
            
            <table class="form-table">
                <tr>
                    <th scope="row">
                        <label for="quiz_style"><?php _e( 'Default Quiz Style', 'money-quiz' ); ?></label>
                    </th>
                    <td>
                        <select name="quiz_style" id="quiz_style">
                            <option value="standard" <?php selected( $quiz_style, 'standard' ); ?>>
                                <?php _e( 'Standard (One question per page)', 'money-quiz' ); ?>
                            </option>
                            <option value="single" <?php selected( $quiz_style, 'single' ); ?>>
                                <?php _e( 'Single Page (All questions visible)', 'money-quiz' ); ?>
                            </option>
                            <option value="stepped" <?php selected( $quiz_style, 'stepped' ); ?>>
                                <?php _e( 'Multi-step with Progress Bar', 'money-quiz' ); ?>
                            </option>
                        </select>
                    </td>
                </tr>
                
                <tr>
                    <th scope="row">
                        <label for="results_display"><?php _e( 'Results Display', 'money-quiz' ); ?></label>
                    </th>
                    <td>
                        <select name="results_display" id="results_display">
                            <option value="immediate" <?php selected( $results_display, 'immediate' ); ?>>
                                <?php _e( 'Show immediately after quiz', 'money-quiz' ); ?>
                            </option>
                            <option value="email_only" <?php selected( $results_display, 'email_only' ); ?>>
                                <?php _e( 'Email results only', 'money-quiz' ); ?>
                            </option>
                            <option value="both" <?php selected( $results_display, 'both' ); ?>>
                                <?php _e( 'Show on page and email', 'money-quiz' ); ?>
                            </option>
                        </select>
                    </td>
                </tr>
                
                <tr>
                    <th scope="row">
                        <label for="questions_per_page"><?php _e( 'Questions Per Page', 'money-quiz' ); ?></label>
                    </th>
                    <td>
                        <input type="number" name="questions_per_page" id="questions_per_page" 
                               value="<?php echo esc_attr( $questions_per_page ); ?>" min="1" max="10" class="small-text" />
                        <p class="description"><?php _e( 'For multi-step quiz style', 'money-quiz' ); ?></p>
                    </td>
                </tr>
                
                <tr>
                    <th scope="row"><?php _e( 'Quiz Features', 'money-quiz' ); ?></th>
                    <td>
                        <fieldset>
                            <label>
                                <input type="checkbox" name="enable_progress_bar" value="1" 
                                       <?php checked( $enable_progress_bar, 1 ); ?> />
                                <?php _e( 'Show progress bar', 'money-quiz' ); ?>
                            </label><br>
                            
                            <label>
                                <input type="checkbox" name="enable_timer" value="1" 
                                       <?php checked( $enable_timer, 1 ); ?> />
                                <?php _e( 'Enable quiz timer', 'money-quiz' ); ?>
                            </label>
                        </fieldset>
                    </td>
                </tr>
            </table>
        </div>
        
        <!-- Lead Capture Settings -->
        <div class="mq-card">
            <h2><?php _e( '📧 Lead Capture Settings', 'money-quiz' ); ?></h2>
            
            <table class="form-table">
                <tr>
                    <th scope="row"><?php _e( 'Email Collection', 'money-quiz' ); ?></th>
                    <td>
                        <label>
                            <input type="checkbox" name="require_email" value="1" 
                                   <?php checked( $require_email, 1 ); ?> />
                            <?php _e( 'Require email address', 'money-quiz' ); ?>
                        </label>
                    </td>
                </tr>
                
                <tr>
                    <th scope="row">
                        <label for="email_position"><?php _e( 'Email Form Position', 'money-quiz' ); ?></label>
                    </th>
                    <td>
                        <select name="email_position" id="email_position">
                            <option value="before_quiz" <?php selected( $email_position, 'before_quiz' ); ?>>
                                <?php _e( 'Before quiz starts', 'money-quiz' ); ?>
                            </option>
                            <option value="before_results" <?php selected( $email_position, 'before_results' ); ?>>
                                <?php _e( 'Before showing results', 'money-quiz' ); ?>
                            </option>
                            <option value="with_results" <?php selected( $email_position, 'with_results' ); ?>>
                                <?php _e( 'With results display', 'money-quiz' ); ?>
                            </option>
                        </select>
                    </td>
                </tr>
                
                <tr>
                    <th scope="row"><?php _e( 'Additional Fields', 'money-quiz' ); ?></th>
                    <td>
                        <label>
                            <input type="checkbox" name="collect_phone" value="1" 
                                   <?php checked( $collect_phone, 1 ); ?> />
                            <?php _e( 'Collect phone number', 'money-quiz' ); ?>
                        </label>
                    </td>
                </tr>
                
                <tr>
                    <th scope="row"><?php _e( 'GDPR Compliance', 'money-quiz' ); ?></th>
                    <td>
                        <label>
                            <input type="checkbox" name="gdpr_enabled" value="1" 
                                   <?php checked( $gdpr_enabled, 1 ); ?> onchange="toggleGDPRText()" />
                            <?php _e( 'Enable GDPR consent checkbox', 'money-quiz' ); ?>
                        </label>
                        
                        <div id="gdpr-text-wrapper" style="margin-top: 10px; <?php echo $gdpr_enabled ? '' : 'display:none;'; ?>">
                            <label for="gdpr_text"><?php _e( 'Consent Text:', 'money-quiz' ); ?></label><br>
                            <textarea name="gdpr_text" id="gdpr_text" rows="3" class="large-text"><?php echo esc_textarea( $gdpr_text ); ?></textarea>
                        </div>
                    </td>
                </tr>
            </table>
        </div>
        
        <!-- Menu Redesign Settings -->
        <div class="mq-card" id="menu-options">
            <h2><?php _e( '🎨 Menu Redesign Settings', 'money-quiz' ); ?></h2>
            
            <table class="form-table">
                <tr>
                    <th scope="row"><?php _e( 'New Menu System', 'money-quiz' ); ?></th>
                    <td>
                        <label>
                            <input type="checkbox" name="menu_redesign_enabled" value="1" 
                                   <?php checked( $menu_redesign_enabled, 1 ); ?> />
                            <?php _e( 'Enable redesigned menu system', 'money-quiz' ); ?>
                        </label>
                        <p class="description"><?php _e( 'Activates the new organized menu structure', 'money-quiz' ); ?></p>
                    </td>
                </tr>
                
                <tr>
                    <th scope="row">
                        <label for="menu_rollout_percentage"><?php _e( 'Gradual Rollout', 'money-quiz' ); ?></label>
                    </th>
                    <td>
                        <input type="number" name="menu_rollout_percentage" id="menu_rollout_percentage" 
                               value="<?php echo esc_attr( $menu_rollout_percentage ); ?>" min="0" max="100" class="small-text" />%
                        <p class="description"><?php _e( 'Percentage of users who will see the new menu (0-100)', 'money-quiz' ); ?></p>
                    </td>
                </tr>
                
                <?php if ( $menu_redesign_enabled ) : ?>
                    <tr>
                        <th scope="row"><?php _e( 'Menu Status', 'money-quiz' ); ?></th>
                        <td>
                            <p class="mq-status-info">
                                ✅ <?php _e( 'New menu is active', 'money-quiz' ); ?><br>
                                <?php 
                                $migration_status = get_option( 'money_quiz_menu_migration_status', 'unknown' );
                                printf( __( 'Migration status: %s', 'money-quiz' ), $migration_status );
                                ?>
                            </p>
                            <a href="<?php echo admin_url( 'admin.php?page=money-quiz-tests' ); ?>" class="button">
                                <?php _e( 'Run Functionality Tests', 'money-quiz' ); ?>
                            </a>
                        </td>
                    </tr>
                <?php endif; ?>
            </table>
        </div>
        
        <p class="submit">
            <input type="submit" name="save_settings" class="button button-primary" 
                   value="<?php _e( 'Save Settings', 'money-quiz' ); ?>" />
            <a href="<?php echo admin_url( 'admin.php?page=money-quiz-settings-advanced' ); ?>" class="button">
                <?php _e( 'Advanced Settings', 'money-quiz' ); ?>
            </a>
        </p>
    </form>
    
</div>

<script>
function selectLogo() {
    if (typeof wp !== 'undefined' && wp.media) {
        var frame = wp.media({
            title: '<?php _e( 'Select Company Logo', 'money-quiz' ); ?>',
            button: { text: '<?php _e( 'Use Logo', 'money-quiz' ); ?>' },
            multiple: false
        });
        
        frame.on('select', function() {
            var attachment = frame.state().get('selection').first().toJSON();
            document.getElementById('company_logo').value = attachment.url;
            
            // Update preview
            var preview = document.querySelector('.logo-preview');
            if (!preview) {
                preview = document.createElement('div');
                preview.className = 'logo-preview';
                preview.style.marginTop = '10px';
                document.getElementById('company_logo').parentNode.appendChild(preview);
            }
            preview.innerHTML = '<img src="' + attachment.url + '" style="max-height: 60px;" />';
        });
        
        frame.open();
    } else {
        alert('<?php _e( 'Media library not available', 'money-quiz' ); ?>');
    }
}

function toggleGDPRText() {
    var wrapper = document.getElementById('gdpr-text-wrapper');
    var checkbox = document.querySelector('input[name="gdpr_enabled"]');
    wrapper.style.display = checkbox.checked ? 'block' : 'none';
}
</script>

<style>
.mq-general-settings .form-table th {
    width: 200px;
}

.mq-status-info {
    background: #f0f8ff;
    padding: 10px;
    border-radius: 4px;
    margin-bottom: 10px;
}
</style>
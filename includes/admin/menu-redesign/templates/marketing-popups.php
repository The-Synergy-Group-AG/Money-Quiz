<?php
/**
 * Pop-ups Management Template
 * 
 * @package MoneyQuiz
 * @since 4.2.0
 */

// Security check
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

global $wpdb;
$table_prefix = $wpdb->prefix;

// Handle popup actions
if ( isset( $_POST['action'] ) ) {
    check_admin_referer( 'manage_popups' );
    
    switch ( $_POST['action'] ) {
        case 'create_popup':
            $popup_name = sanitize_text_field( $_POST['popup_name'] );
            $trigger_type = sanitize_text_field( $_POST['trigger_type'] );
            $trigger_value = intval( $_POST['trigger_value'] );
            $content = wp_kses_post( $_POST['popup_content'] );
            $display_pages = isset( $_POST['display_pages'] ) ? array_map( 'sanitize_text_field', $_POST['display_pages'] ) : [];
            
            // Try new table structure
            $result = $wpdb->insert(
                "{$table_prefix}mq_popups",
                [
                    'name' => $popup_name,
                    'trigger_type' => $trigger_type,
                    'trigger_value' => $trigger_value,
                    'content' => $content,
                    'display_pages' => json_encode( $display_pages ),
                    'status' => 1,
                    'created_date' => current_time( 'mysql' )
                ]
            );
            
            // Fallback to options
            if ( ! $result ) {
                $popups = get_option( 'money_quiz_popups', [] );
                $popups[] = [
                    'id' => time(),
                    'name' => $popup_name,
                    'trigger_type' => $trigger_type,
                    'trigger_value' => $trigger_value,
                    'content' => $content,
                    'display_pages' => $display_pages,
                    'status' => 1,
                    'impressions' => 0,
                    'conversions' => 0
                ];
                update_option( 'money_quiz_popups', $popups );
                $result = true;
            }
            
            if ( $result ) {
                echo '<div class="notice notice-success"><p>' . __( 'Pop-up created successfully.', 'money-quiz' ) . '</p></div>';
            }
            break;
            
        case 'toggle_popup':
            $popup_id = intval( $_POST['popup_id'] );
            $current_status = intval( $_POST['current_status'] );
            $new_status = $current_status ? 0 : 1;
            
            // Try database
            $updated = $wpdb->update(
                "{$table_prefix}mq_popups",
                ['status' => $new_status],
                ['id' => $popup_id]
            );
            
            // Fallback to options
            if ( ! $updated ) {
                $popups = get_option( 'money_quiz_popups', [] );
                foreach ( $popups as &$popup ) {
                    if ( $popup['id'] == $popup_id ) {
                        $popup['status'] = $new_status;
                        break;
                    }
                }
                update_option( 'money_quiz_popups', $popups );
            }
            
            echo '<div class="notice notice-success"><p>' . __( 'Pop-up status updated.', 'money-quiz' ) . '</p></div>';
            break;
    }
}

// Get popups
$popups = $wpdb->get_results( "
    SELECT 
        p.*,
        COUNT(DISTINCT pi.id) as impression_count,
        COUNT(DISTINCT pc.id) as conversion_count
    FROM {$table_prefix}mq_popups p
    LEFT JOIN {$table_prefix}mq_popup_impressions pi ON p.id = pi.popup_id
    LEFT JOIN {$table_prefix}mq_popup_conversions pc ON p.id = pc.popup_id
    GROUP BY p.id
    ORDER BY p.created_date DESC
" );

// Fallback to options
if ( empty( $popups ) ) {
    $option_popups = get_option( 'money_quiz_popups', [] );
    if ( ! empty( $option_popups ) ) {
        $popups = array_map( function($popup) {
            return (object) array_merge( $popup, [
                'impression_count' => $popup['impressions'] ?? 0,
                'conversion_count' => $popup['conversions'] ?? 0
            ]);
        }, $option_popups );
    }
}

// Trigger types
$trigger_types = [
    'time_delay' => __( 'Time Delay', 'money-quiz' ),
    'scroll_percentage' => __( 'Scroll Percentage', 'money-quiz' ),
    'exit_intent' => __( 'Exit Intent', 'money-quiz' ),
    'quiz_completion' => __( 'Quiz Completion', 'money-quiz' ),
    'page_views' => __( 'Page Views', 'money-quiz' ),
    'inactivity' => __( 'User Inactivity', 'money-quiz' )
];

// Display page options
$display_options = [
    'all' => __( 'All Pages', 'money-quiz' ),
    'home' => __( 'Homepage Only', 'money-quiz' ),
    'quiz' => __( 'Quiz Pages', 'money-quiz' ),
    'blog' => __( 'Blog Posts', 'money-quiz' ),
    'specific' => __( 'Specific Pages', 'money-quiz' )
];
?>

<div class="wrap mq-popup-manager">
    
    <!-- Popup Stats -->
    <div class="mq-popup-stats">
        <div class="mq-stat-box">
            <h3><?php _e( 'Total Pop-ups', 'money-quiz' ); ?></h3>
            <div class="mq-stat-number"><?php echo count( $popups ); ?></div>
        </div>
        <div class="mq-stat-box">
            <h3><?php _e( 'Active Pop-ups', 'money-quiz' ); ?></h3>
            <div class="mq-stat-number">
                <?php echo count( array_filter( $popups, function($p) { return $p->status == 1; } ) ); ?>
            </div>
        </div>
        <div class="mq-stat-box">
            <h3><?php _e( 'Total Impressions', 'money-quiz' ); ?></h3>
            <div class="mq-stat-number">
                <?php echo number_format( array_sum( array_column( $popups, 'impression_count' ) ) ); ?>
            </div>
        </div>
        <div class="mq-stat-box">
            <h3><?php _e( 'Avg. Conversion', 'money-quiz' ); ?></h3>
            <div class="mq-stat-number">
                <?php 
                $total_impressions = array_sum( array_column( $popups, 'impression_count' ) );
                $total_conversions = array_sum( array_column( $popups, 'conversion_count' ) );
                $conversion_rate = $total_impressions > 0 ? round( ( $total_conversions / $total_impressions ) * 100, 2 ) : 0;
                echo $conversion_rate . '%';
                ?>
            </div>
        </div>
    </div>
    
    <!-- Create New Popup -->
    <div class="mq-card">
        <h2><?php _e( 'Create New Pop-up', 'money-quiz' ); ?></h2>
        
        <form method="post" class="popup-form">
            <?php wp_nonce_field( 'manage_popups' ); ?>
            <input type="hidden" name="action" value="create_popup" />
            
            <table class="form-table">
                <tr>
                    <th scope="row">
                        <label for="popup_name"><?php _e( 'Pop-up Name', 'money-quiz' ); ?></label>
                    </th>
                    <td>
                        <input type="text" name="popup_name" id="popup_name" class="regular-text" required />
                        <p class="description"><?php _e( 'Internal name for this pop-up', 'money-quiz' ); ?></p>
                    </td>
                </tr>
                
                <tr>
                    <th scope="row">
                        <label for="trigger_type"><?php _e( 'Trigger Type', 'money-quiz' ); ?></label>
                    </th>
                    <td>
                        <select name="trigger_type" id="trigger_type" onchange="updateTriggerOptions()">
                            <?php foreach ( $trigger_types as $value => $label ) : ?>
                                <option value="<?php echo esc_attr( $value ); ?>">
                                    <?php echo esc_html( $label ); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        
                        <div id="trigger-options" style="margin-top: 10px;">
                            <label>
                                <?php _e( 'Delay (seconds):', 'money-quiz' ); ?>
                                <input type="number" name="trigger_value" value="5" min="0" class="small-text" />
                            </label>
                        </div>
                    </td>
                </tr>
                
                <tr>
                    <th scope="row">
                        <label for="popup_content"><?php _e( 'Pop-up Content', 'money-quiz' ); ?></label>
                    </th>
                    <td>
                        <?php
                        wp_editor( '', 'popup_content', [
                            'media_buttons' => true,
                            'textarea_rows' => 10,
                            'teeny' => false
                        ] );
                        ?>
                        <p class="description">
                            <?php _e( 'Design your pop-up content. Use shortcodes: [quiz_button], [email_form]', 'money-quiz' ); ?>
                        </p>
                    </td>
                </tr>
                
                <tr>
                    <th scope="row">
                        <label><?php _e( 'Display On', 'money-quiz' ); ?></label>
                    </th>
                    <td>
                        <fieldset>
                            <?php foreach ( $display_options as $value => $label ) : ?>
                                <label style="display: block; margin-bottom: 5px;">
                                    <input type="checkbox" name="display_pages[]" value="<?php echo esc_attr( $value ); ?>" />
                                    <?php echo esc_html( $label ); ?>
                                </label>
                            <?php endforeach; ?>
                        </fieldset>
                    </td>
                </tr>
                
                <tr>
                    <th scope="row">
                        <label><?php _e( 'Advanced Settings', 'money-quiz' ); ?></label>
                    </th>
                    <td>
                        <label>
                            <input type="checkbox" name="show_once" value="1" />
                            <?php _e( 'Show only once per session', 'money-quiz' ); ?>
                        </label><br>
                        
                        <label>
                            <input type="checkbox" name="mobile_disabled" value="1" />
                            <?php _e( 'Disable on mobile devices', 'money-quiz' ); ?>
                        </label><br>
                        
                        <label>
                            <input type="checkbox" name="logged_in_only" value="1" />
                            <?php _e( 'Show only to logged-in users', 'money-quiz' ); ?>
                        </label>
                    </td>
                </tr>
            </table>
            
            <p class="submit">
                <input type="submit" class="button button-primary" value="<?php _e( 'Create Pop-up', 'money-quiz' ); ?>" />
                <button type="button" class="button" onclick="previewPopup()">
                    <?php _e( 'Preview', 'money-quiz' ); ?>
                </button>
            </p>
        </form>
    </div>
    
    <!-- Popup Templates -->
    <div class="mq-card">
        <h2><?php _e( 'Pop-up Templates', 'money-quiz' ); ?></h2>
        
        <div class="mq-popup-templates">
            <div class="mq-template-item" onclick="useTemplate('quiz_prompt')">
                <h3><?php _e( '📊 Quiz Prompt', 'money-quiz' ); ?></h3>
                <p><?php _e( 'Encourage visitors to take your quiz', 'money-quiz' ); ?></p>
            </div>
            
            <div class="mq-template-item" onclick="useTemplate('email_capture')">
                <h3><?php _e( '📧 Email Capture', 'money-quiz' ); ?></h3>
                <p><?php _e( 'Collect emails with a compelling offer', 'money-quiz' ); ?></p>
            </div>
            
            <div class="mq-template-item" onclick="useTemplate('discount_offer')">
                <h3><?php _e( '💰 Special Offer', 'money-quiz' ); ?></h3>
                <p><?php _e( 'Present a limited-time discount', 'money-quiz' ); ?></p>
            </div>
            
            <div class="mq-template-item" onclick="useTemplate('announcement')">
                <h3><?php _e( '📢 Announcement', 'money-quiz' ); ?></h3>
                <p><?php _e( 'Share important news or updates', 'money-quiz' ); ?></p>
            </div>
        </div>
    </div>
    
    <!-- Existing Popups -->
    <div class="mq-card">
        <h2><?php _e( 'Manage Pop-ups', 'money-quiz' ); ?></h2>
        
        <?php if ( empty( $popups ) ) : ?>
            <p><?php _e( 'No pop-ups created yet.', 'money-quiz' ); ?></p>
        <?php else : ?>
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th><?php _e( 'Name', 'money-quiz' ); ?></th>
                        <th><?php _e( 'Trigger', 'money-quiz' ); ?></th>
                        <th><?php _e( 'Display On', 'money-quiz' ); ?></th>
                        <th><?php _e( 'Impressions', 'money-quiz' ); ?></th>
                        <th><?php _e( 'Conversions', 'money-quiz' ); ?></th>
                        <th><?php _e( 'Conv. Rate', 'money-quiz' ); ?></th>
                        <th><?php _e( 'Status', 'money-quiz' ); ?></th>
                        <th><?php _e( 'Actions', 'money-quiz' ); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ( $popups as $popup ) : ?>
                        <tr>
                            <td>
                                <strong><?php echo esc_html( $popup->name ); ?></strong>
                                <div class="row-actions">
                                    <span class="edit">
                                        <a href="?page=money-quiz-marketing-popups&action=edit&id=<?php echo $popup->id; ?>">
                                            <?php _e( 'Edit', 'money-quiz' ); ?>
                                        </a> |
                                    </span>
                                    <span class="duplicate">
                                        <a href="?page=money-quiz-marketing-popups&action=duplicate&id=<?php echo $popup->id; ?>">
                                            <?php _e( 'Duplicate', 'money-quiz' ); ?>
                                        </a> |
                                    </span>
                                    <span class="stats">
                                        <a href="?page=money-quiz-marketing-popups&action=stats&id=<?php echo $popup->id; ?>">
                                            <?php _e( 'Stats', 'money-quiz' ); ?>
                                        </a>
                                    </span>
                                </div>
                            </td>
                            <td>
                                <?php 
                                echo esc_html( $trigger_types[$popup->trigger_type] ?? $popup->trigger_type );
                                if ( $popup->trigger_value ) {
                                    echo ' (' . $popup->trigger_value . ')';
                                }
                                ?>
                            </td>
                            <td>
                                <?php 
                                $pages = json_decode( $popup->display_pages, true ) ?: [];
                                echo esc_html( implode( ', ', $pages ) ?: __( 'All Pages', 'money-quiz' ) );
                                ?>
                            </td>
                            <td><?php echo number_format( $popup->impression_count ); ?></td>
                            <td><?php echo number_format( $popup->conversion_count ); ?></td>
                            <td>
                                <?php 
                                $conv_rate = $popup->impression_count > 0 
                                    ? round( ( $popup->conversion_count / $popup->impression_count ) * 100, 2 ) 
                                    : 0;
                                echo $conv_rate . '%';
                                ?>
                            </td>
                            <td>
                                <form method="post" style="display:inline;">
                                    <?php wp_nonce_field( 'manage_popups' ); ?>
                                    <input type="hidden" name="action" value="toggle_popup" />
                                    <input type="hidden" name="popup_id" value="<?php echo $popup->id; ?>" />
                                    <input type="hidden" name="current_status" value="<?php echo $popup->status; ?>" />
                                    <?php if ( $popup->status ) : ?>
                                        <button type="submit" class="button button-small">
                                            <?php _e( 'Active', 'money-quiz' ); ?>
                                        </button>
                                    <?php else : ?>
                                        <button type="submit" class="button button-small button-secondary">
                                            <?php _e( 'Inactive', 'money-quiz' ); ?>
                                        </button>
                                    <?php endif; ?>
                                </form>
                            </td>
                            <td>
                                <a href="#" onclick="testPopup(<?php echo $popup->id; ?>); return false;" class="button button-small">
                                    <?php _e( 'Test', 'money-quiz' ); ?>
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>
    
    <!-- Best Practices -->
    <div class="mq-card">
        <h2><?php _e( '💡 Pop-up Best Practices', 'money-quiz' ); ?></h2>
        
        <div class="mq-best-practices">
            <div class="practice-item">
                <h3><?php _e( '⏱️ Timing is Everything', 'money-quiz' ); ?></h3>
                <p><?php _e( 'Wait at least 5-10 seconds before showing a pop-up to new visitors.', 'money-quiz' ); ?></p>
            </div>
            
            <div class="practice-item">
                <h3><?php _e( '🎯 Clear Value Proposition', 'money-quiz' ); ?></h3>
                <p><?php _e( 'Make it immediately clear what visitors will get by engaging with your pop-up.', 'money-quiz' ); ?></p>
            </div>
            
            <div class="practice-item">
                <h3><?php _e( '📱 Mobile-Friendly', 'money-quiz' ); ?></h3>
                <p><?php _e( 'Ensure pop-ups are responsive and don\'t overwhelm mobile screens.', 'money-quiz' ); ?></p>
            </div>
            
            <div class="practice-item">
                <h3><?php _e( '🚪 Easy Exit', 'money-quiz' ); ?></h3>
                <p><?php _e( 'Always provide a clear, easy way to close the pop-up.', 'money-quiz' ); ?></p>
            </div>
        </div>
    </div>
    
</div>

<script>
function updateTriggerOptions() {
    var triggerType = document.getElementById('trigger_type').value;
    var optionsHtml = '';
    
    switch(triggerType) {
        case 'time_delay':
            optionsHtml = '<label><?php _e( "Delay (seconds):", "money-quiz" ); ?> <input type="number" name="trigger_value" value="5" min="0" class="small-text" /></label>';
            break;
        case 'scroll_percentage':
            optionsHtml = '<label><?php _e( "Scroll percentage:", "money-quiz" ); ?> <input type="number" name="trigger_value" value="50" min="0" max="100" class="small-text" />%</label>';
            break;
        case 'page_views':
            optionsHtml = '<label><?php _e( "Number of pages:", "money-quiz" ); ?> <input type="number" name="trigger_value" value="3" min="1" class="small-text" /></label>';
            break;
        case 'inactivity':
            optionsHtml = '<label><?php _e( "Inactivity (seconds):", "money-quiz" ); ?> <input type="number" name="trigger_value" value="30" min="1" class="small-text" /></label>';
            break;
        default:
            optionsHtml = '<input type="hidden" name="trigger_value" value="0" />';
    }
    
    document.getElementById('trigger-options').innerHTML = optionsHtml;
}

function previewPopup() {
    // Would open preview
    alert('Pop-up preview functionality would be implemented here');
}

function testPopup(popupId) {
    // Would trigger test popup
    alert('Pop-up test functionality would trigger popup ID: ' + popupId);
}

function useTemplate(templateType) {
    var templates = {
        quiz_prompt: {
            name: 'Quiz Prompt Popup',
            content: '<h2>Discover Your Money Personality!</h2><p>Take our 2-minute quiz and get personalized financial insights.</p>[quiz_button text="Start Quiz Now" class="popup-cta-button"]'
        },
        email_capture: {
            name: 'Email Capture Popup',
            content: '<h2>Get Your Free Financial Guide!</h2><p>Join 10,000+ subscribers and get our exclusive guide.</p>[email_form button_text="Get My Guide"]'
        },
        discount_offer: {
            name: 'Special Offer Popup',
            content: '<h2>Limited Time: 25% Off!</h2><p>Take the quiz today and save on our premium financial course.</p>[quiz_button text="Take Quiz & Save"]<p><small>Offer expires in 48 hours</small></p>'
        },
        announcement: {
            name: 'Announcement Popup',
            content: '<h2>New Quiz Available!</h2><p>We just launched our Investment Style Quiz. Find out your investor profile in just 5 minutes.</p>[quiz_button text="Try It Now"]'
        }
    };
    
    if (templates[templateType]) {
        document.getElementById('popup_name').value = templates[templateType].name;
        if (typeof tinymce !== 'undefined' && tinymce.get('popup_content')) {
            tinymce.get('popup_content').setContent(templates[templateType].content);
        }
    }
}
</script>

<style>
.mq-popup-stats {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 20px;
    margin-bottom: 30px;
}

.mq-stat-box {
    background: #fff;
    border: 1px solid #c3c4c7;
    border-radius: 4px;
    padding: 20px;
    text-align: center;
}

.mq-popup-templates {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
    gap: 20px;
    margin-top: 20px;
}

.mq-template-item {
    background: #f0f8ff;
    padding: 20px;
    border-radius: 4px;
    cursor: pointer;
    transition: all 0.3s ease;
}

.mq-template-item:hover {
    background: #e0f0ff;
    transform: translateY(-2px);
    box-shadow: 0 4px 8px rgba(0,0,0,0.1);
}

.mq-template-item h3 {
    margin: 0 0 10px 0;
    font-size: 16px;
}

.mq-template-item p {
    margin: 0;
    color: #666;
    font-size: 13px;
}

.mq-best-practices {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
    gap: 20px;
    margin-top: 20px;
}

.practice-item {
    background: #f9f9f9;
    padding: 20px;
    border-radius: 4px;
}

.practice-item h3 {
    margin: 0 0 10px 0;
    font-size: 16px;
}

.practice-item p {
    margin: 0;
    color: #666;
    font-size: 13px;
}
</style>
<?php
/**
 * Email Campaigns Template
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

// Handle campaign actions
if ( isset( $_POST['action'] ) ) {
    check_admin_referer( 'manage_campaigns' );
    
    switch ( $_POST['action'] ) {
        case 'create_campaign':
            $campaign_name = sanitize_text_field( $_POST['campaign_name'] );
            $subject = sanitize_text_field( $_POST['subject'] );
            $content = wp_kses_post( $_POST['email_content'] );
            $quiz_id = intval( $_POST['quiz_id'] );
            $archetype = sanitize_text_field( $_POST['archetype'] );
            $send_time = sanitize_text_field( $_POST['send_time'] );
            
            // Create campaign
            $result = $wpdb->insert(
                "{$table_prefix}mq_email_campaigns",
                [
                    'campaign_name' => $campaign_name,
                    'subject' => $subject,
                    'content' => $content,
                    'quiz_id' => $quiz_id,
                    'archetype_filter' => $archetype,
                    'scheduled_time' => $send_time === 'schedule' ? $_POST['scheduled_date'] : null,
                    'status' => $send_time === 'now' ? 'sending' : 'scheduled',
                    'created_date' => current_time( 'mysql' )
                ]
            );
            
            if ( $result ) {
                $campaign_id = $wpdb->insert_id;
                
                if ( $send_time === 'now' ) {
                    // Queue emails for sending
                    $this->queue_campaign_emails( $campaign_id );
                }
                
                echo '<div class="notice notice-success"><p>' . __( 'Campaign created successfully.', 'money-quiz' ) . '</p></div>';
            }
            break;
            
        case 'pause_campaign':
            $campaign_id = intval( $_POST['campaign_id'] );
            $wpdb->update(
                "{$table_prefix}mq_email_campaigns",
                ['status' => 'paused'],
                ['id' => $campaign_id]
            );
            echo '<div class="notice notice-success"><p>' . __( 'Campaign paused.', 'money-quiz' ) . '</p></div>';
            break;
            
        case 'resume_campaign':
            $campaign_id = intval( $_POST['campaign_id'] );
            $wpdb->update(
                "{$table_prefix}mq_email_campaigns",
                ['status' => 'sending'],
                ['id' => $campaign_id]
            );
            echo '<div class="notice notice-success"><p>' . __( 'Campaign resumed.', 'money-quiz' ) . '</p></div>';
            break;
    }
}

// Get campaigns with fallback
$campaigns_query = "
    SELECT 
        c.*,
        COUNT(DISTINCT ce.id) as total_recipients,
        COUNT(DISTINCT CASE WHEN ce.status = 'sent' THEN ce.id END) as sent_count,
        COUNT(DISTINCT CASE WHEN ce.opened_at IS NOT NULL THEN ce.id END) as open_count,
        COUNT(DISTINCT CASE WHEN ce.clicked_at IS NOT NULL THEN ce.id END) as click_count
    FROM {$table_prefix}mq_email_campaigns c
    LEFT JOIN {$table_prefix}mq_campaign_emails ce ON c.id = ce.campaign_id
    GROUP BY c.id
    ORDER BY c.created_date DESC
";

$campaigns = $wpdb->get_results( $campaigns_query );

// If no campaigns in new table, check for legacy email data
if ( empty( $campaigns ) ) {
    // Check if legacy email functionality exists
    $legacy_campaigns = [];
    
    // Try to get sent emails from legacy system
    $legacy_emails = $wpdb->get_results( "
        SELECT 
            'Legacy Campaign' as campaign_name,
            COUNT(*) as total_recipients,
            COUNT(*) as sent_count,
            0 as open_count,
            0 as click_count,
            'completed' as status,
            MAX(date) as created_date
        FROM {$table_prefix}mq_email_log
        GROUP BY DATE(date)
        ORDER BY date DESC
        LIMIT 10
    " );
    
    if ( ! empty( $legacy_emails ) ) {
        $campaigns = $legacy_emails;
    }
}

// Get email templates
$email_templates = [
    'welcome' => [
        'name' => __( 'Welcome Email', 'money-quiz' ),
        'subject' => __( 'Welcome! Your quiz results are ready', 'money-quiz' ),
        'content' => __( 'Thank you for taking our quiz. Your personalized results...', 'money-quiz' )
    ],
    'follow_up' => [
        'name' => __( 'Follow-up Email', 'money-quiz' ),
        'subject' => __( 'How are you progressing with your financial goals?', 'money-quiz' ),
        'content' => __( 'It\'s been a week since you took our quiz...', 'money-quiz' )
    ],
    'educational' => [
        'name' => __( 'Educational Series', 'money-quiz' ),
        'subject' => __( 'Financial tip of the week', 'money-quiz' ),
        'content' => __( 'Based on your quiz results, here\'s a tip...', 'money-quiz' )
    ]
];

// Get stats
$total_sent = $wpdb->get_var( "SELECT COUNT(*) FROM {$table_prefix}mq_campaign_emails WHERE status = 'sent'" ) ?: 0;
$total_opens = $wpdb->get_var( "SELECT COUNT(*) FROM {$table_prefix}mq_campaign_emails WHERE opened_at IS NOT NULL" ) ?: 0;
$open_rate = $total_sent > 0 ? round( ( $total_opens / $total_sent ) * 100, 1 ) : 0;

// Queue campaign emails function
function queue_campaign_emails( $campaign_id ) {
    global $wpdb, $table_prefix;
    
    $campaign = $wpdb->get_row( $wpdb->prepare(
        "SELECT * FROM {$table_prefix}mq_email_campaigns WHERE id = %d",
        $campaign_id
    ) );
    
    if ( ! $campaign ) {
        return false;
    }
    
    // Get recipients based on filters
    $where_clauses = [];
    if ( $campaign->quiz_id > 0 ) {
        $where_clauses[] = $wpdb->prepare( "quiz_id = %d", $campaign->quiz_id );
    }
    if ( ! empty( $campaign->archetype_filter ) ) {
        $where_clauses[] = $wpdb->prepare( "archetype_id IN (SELECT id FROM {$table_prefix}mq_archetypes WHERE name = %s)", $campaign->archetype_filter );
    }
    $where_clauses[] = "email_consent = 1"; // Only send to those who consented
    
    $where_sql = ! empty( $where_clauses ) ? 'WHERE ' . implode( ' AND ', $where_clauses ) : '';
    
    $recipients = $wpdb->get_results( "
        SELECT id, email, name 
        FROM {$table_prefix}mq_prospects 
        $where_sql
    " );
    
    // Queue emails
    foreach ( $recipients as $recipient ) {
        $wpdb->insert(
            "{$table_prefix}mq_campaign_emails",
            [
                'campaign_id' => $campaign_id,
                'prospect_id' => $recipient->id,
                'email' => $recipient->email,
                'status' => 'queued',
                'queued_at' => current_time( 'mysql' )
            ]
        );
    }
    
    // Schedule cron job to process queue
    if ( ! wp_next_scheduled( 'mq_process_email_queue' ) ) {
        wp_schedule_event( time(), 'hourly', 'mq_process_email_queue' );
    }
    
    return count( $recipients );
}
?>

<div class="wrap mq-email-campaigns">
    
    <!-- Campaign Stats -->
    <div class="mq-campaign-stats">
        <div class="mq-stat-box">
            <h3><?php _e( 'Total Campaigns', 'money-quiz' ); ?></h3>
            <div class="mq-stat-number"><?php echo count( $campaigns ); ?></div>
        </div>
        <div class="mq-stat-box">
            <h3><?php _e( 'Emails Sent', 'money-quiz' ); ?></h3>
            <div class="mq-stat-number"><?php echo number_format( $total_sent ); ?></div>
        </div>
        <div class="mq-stat-box">
            <h3><?php _e( 'Open Rate', 'money-quiz' ); ?></h3>
            <div class="mq-stat-number"><?php echo $open_rate; ?>%</div>
        </div>
        <div class="mq-stat-box">
            <h3><?php _e( 'Active Campaigns', 'money-quiz' ); ?></h3>
            <div class="mq-stat-number">
                <?php echo count( array_filter( $campaigns, function($c) { return $c->status === 'sending'; } ) ); ?>
            </div>
        </div>
    </div>
    
    <!-- Create New Campaign -->
    <div class="mq-card">
        <h2><?php _e( 'Create New Campaign', 'money-quiz' ); ?></h2>
        
        <form method="post" class="campaign-form">
            <?php wp_nonce_field( 'manage_campaigns' ); ?>
            <input type="hidden" name="action" value="create_campaign" />
            
            <table class="form-table">
                <tr>
                    <th scope="row">
                        <label for="campaign_name"><?php _e( 'Campaign Name', 'money-quiz' ); ?></label>
                    </th>
                    <td>
                        <input type="text" name="campaign_name" id="campaign_name" class="regular-text" required />
                        <p class="description"><?php _e( 'Internal name for this campaign', 'money-quiz' ); ?></p>
                    </td>
                </tr>
                
                <tr>
                    <th scope="row">
                        <label for="template"><?php _e( 'Template', 'money-quiz' ); ?></label>
                    </th>
                    <td>
                        <select name="template" id="template" onchange="loadTemplate(this.value)">
                            <option value=""><?php _e( 'Select a template', 'money-quiz' ); ?></option>
                            <?php foreach ( $email_templates as $key => $template ) : ?>
                                <option value="<?php echo esc_attr( $key ); ?>">
                                    <?php echo esc_html( $template['name'] ); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </td>
                </tr>
                
                <tr>
                    <th scope="row">
                        <label for="subject"><?php _e( 'Subject Line', 'money-quiz' ); ?></label>
                    </th>
                    <td>
                        <input type="text" name="subject" id="subject" class="large-text" required />
                        <p class="description"><?php _e( 'Use {name} for personalization', 'money-quiz' ); ?></p>
                    </td>
                </tr>
                
                <tr>
                    <th scope="row">
                        <label for="email_content"><?php _e( 'Email Content', 'money-quiz' ); ?></label>
                    </th>
                    <td>
                        <?php
                        wp_editor( '', 'email_content', [
                            'media_buttons' => true,
                            'textarea_rows' => 15,
                            'teeny' => false
                        ] );
                        ?>
                        <p class="description">
                            <?php _e( 'Available merge tags: {name}, {email}, {quiz_name}, {archetype}, {score}', 'money-quiz' ); ?>
                        </p>
                    </td>
                </tr>
                
                <tr>
                    <th scope="row">
                        <label><?php _e( 'Recipients', 'money-quiz' ); ?></label>
                    </th>
                    <td>
                        <fieldset>
                            <label>
                                <input type="radio" name="recipient_type" value="all" checked />
                                <?php _e( 'All prospects with email consent', 'money-quiz' ); ?>
                            </label><br>
                            
                            <label>
                                <input type="radio" name="recipient_type" value="quiz" />
                                <?php _e( 'Specific quiz:', 'money-quiz' ); ?>
                                <select name="quiz_id">
                                    <option value="0"><?php _e( 'Select quiz', 'money-quiz' ); ?></option>
                                    <?php
                                    $quizzes = $wpdb->get_results( "SELECT id, quiz_name FROM {$table_prefix}mq_master" );
                                    foreach ( $quizzes as $quiz ) :
                                    ?>
                                        <option value="<?php echo $quiz->id; ?>">
                                            <?php echo esc_html( $quiz->quiz_name ); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </label><br>
                            
                            <label>
                                <input type="radio" name="recipient_type" value="archetype" />
                                <?php _e( 'Specific archetype:', 'money-quiz' ); ?>
                                <select name="archetype">
                                    <option value=""><?php _e( 'Select archetype', 'money-quiz' ); ?></option>
                                    <?php
                                    $archetypes = $wpdb->get_results( "SELECT DISTINCT name FROM {$table_prefix}mq_archetypes" );
                                    foreach ( $archetypes as $archetype ) :
                                    ?>
                                        <option value="<?php echo esc_attr( $archetype->name ); ?>">
                                            <?php echo esc_html( $archetype->name ); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </label>
                        </fieldset>
                    </td>
                </tr>
                
                <tr>
                    <th scope="row">
                        <label><?php _e( 'Send Time', 'money-quiz' ); ?></label>
                    </th>
                    <td>
                        <label>
                            <input type="radio" name="send_time" value="now" checked />
                            <?php _e( 'Send immediately', 'money-quiz' ); ?>
                        </label><br>
                        
                        <label>
                            <input type="radio" name="send_time" value="schedule" />
                            <?php _e( 'Schedule for:', 'money-quiz' ); ?>
                            <input type="datetime-local" name="scheduled_date" />
                        </label>
                    </td>
                </tr>
            </table>
            
            <p class="submit">
                <input type="submit" class="button button-primary" value="<?php _e( 'Create Campaign', 'money-quiz' ); ?>" />
                <button type="button" class="button" onclick="previewEmail()">
                    <?php _e( 'Preview', 'money-quiz' ); ?>
                </button>
            </p>
        </form>
    </div>
    
    <!-- Campaigns List -->
    <div class="mq-card">
        <h2><?php _e( 'Email Campaigns', 'money-quiz' ); ?></h2>
        
        <?php if ( empty( $campaigns ) ) : ?>
            <p><?php _e( 'No campaigns created yet.', 'money-quiz' ); ?></p>
        <?php else : ?>
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th><?php _e( 'Campaign', 'money-quiz' ); ?></th>
                        <th><?php _e( 'Status', 'money-quiz' ); ?></th>
                        <th><?php _e( 'Recipients', 'money-quiz' ); ?></th>
                        <th><?php _e( 'Sent', 'money-quiz' ); ?></th>
                        <th><?php _e( 'Opens', 'money-quiz' ); ?></th>
                        <th><?php _e( 'Clicks', 'money-quiz' ); ?></th>
                        <th><?php _e( 'Created', 'money-quiz' ); ?></th>
                        <th><?php _e( 'Actions', 'money-quiz' ); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ( $campaigns as $campaign ) : ?>
                        <tr>
                            <td>
                                <strong><?php echo esc_html( $campaign->campaign_name ); ?></strong>
                            </td>
                            <td>
                                <?php
                                $status_labels = [
                                    'draft' => __( 'Draft', 'money-quiz' ),
                                    'scheduled' => __( 'Scheduled', 'money-quiz' ),
                                    'sending' => __( 'Sending', 'money-quiz' ),
                                    'paused' => __( 'Paused', 'money-quiz' ),
                                    'completed' => __( 'Completed', 'money-quiz' )
                                ];
                                ?>
                                <span class="campaign-status status-<?php echo esc_attr( $campaign->status ); ?>">
                                    <?php echo $status_labels[ $campaign->status ] ?? $campaign->status; ?>
                                </span>
                            </td>
                            <td><?php echo number_format( $campaign->total_recipients ); ?></td>
                            <td><?php echo number_format( $campaign->sent_count ); ?></td>
                            <td>
                                <?php 
                                if ( $campaign->sent_count > 0 ) {
                                    $open_rate = round( ( $campaign->open_count / $campaign->sent_count ) * 100, 1 );
                                    echo $campaign->open_count . ' (' . $open_rate . '%)';
                                } else {
                                    echo '-';
                                }
                                ?>
                            </td>
                            <td>
                                <?php 
                                if ( $campaign->sent_count > 0 ) {
                                    $click_rate = round( ( $campaign->click_count / $campaign->sent_count ) * 100, 1 );
                                    echo $campaign->click_count . ' (' . $click_rate . '%)';
                                } else {
                                    echo '-';
                                }
                                ?>
                            </td>
                            <td><?php echo date_i18n( get_option( 'date_format' ), strtotime( $campaign->created_date ) ); ?></td>
                            <td>
                                <?php if ( $campaign->status === 'sending' ) : ?>
                                    <form method="post" style="display:inline;">
                                        <?php wp_nonce_field( 'manage_campaigns' ); ?>
                                        <input type="hidden" name="action" value="pause_campaign" />
                                        <input type="hidden" name="campaign_id" value="<?php echo $campaign->id; ?>" />
                                        <button type="submit" class="button button-small">
                                            <?php _e( 'Pause', 'money-quiz' ); ?>
                                        </button>
                                    </form>
                                <?php elseif ( $campaign->status === 'paused' ) : ?>
                                    <form method="post" style="display:inline;">
                                        <?php wp_nonce_field( 'manage_campaigns' ); ?>
                                        <input type="hidden" name="action" value="resume_campaign" />
                                        <input type="hidden" name="campaign_id" value="<?php echo $campaign->id; ?>" />
                                        <button type="submit" class="button button-small">
                                            <?php _e( 'Resume', 'money-quiz' ); ?>
                                        </button>
                                    </form>
                                <?php endif; ?>
                                
                                <a href="?page=money-quiz-audience-campaigns&action=view&id=<?php echo $campaign->id; ?>" class="button button-small">
                                    <?php _e( 'View', 'money-quiz' ); ?>
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>
    
</div>

<script>
var emailTemplates = <?php echo json_encode( $email_templates ); ?>;

function loadTemplate(templateKey) {
    if (templateKey && emailTemplates[templateKey]) {
        var template = emailTemplates[templateKey];
        document.getElementById('subject').value = template.subject;
        if (typeof tinymce !== 'undefined' && tinymce.get('email_content')) {
            tinymce.get('email_content').setContent(template.content);
        } else {
            document.getElementById('email_content').value = template.content;
        }
    }
}

function previewEmail() {
    // Would open preview modal
    alert('Email preview functionality would be implemented here');
}
</script>

<style>
.mq-campaign-stats {
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

.mq-stat-box h3 {
    margin: 0 0 10px 0;
    font-size: 14px;
    color: #646970;
}

.mq-stat-number {
    font-size: 32px;
    font-weight: 600;
    color: #23282d;
}

.campaign-status {
    display: inline-block;
    padding: 3px 8px;
    border-radius: 3px;
    font-size: 12px;
    font-weight: 600;
}

.status-draft { background: #ddd; color: #666; }
.status-scheduled { background: #2271b1; color: #fff; }
.status-sending { background: #00a32a; color: #fff; }
.status-paused { background: #dba617; color: #fff; }
.status-completed { background: #646970; color: #fff; }
</style>
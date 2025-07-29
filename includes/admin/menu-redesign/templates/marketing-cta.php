<?php
/**
 * Call-to-Actions Management Template
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

// Handle CTA actions
if ( isset( $_POST['action'] ) ) {
    check_admin_referer( 'manage_ctas' );
    
    switch ( $_POST['action'] ) {
        case 'create_cta':
            $cta_name = sanitize_text_field( $_POST['cta_name'] );
            $cta_type = sanitize_text_field( $_POST['cta_type'] );
            $button_text = sanitize_text_field( $_POST['button_text'] );
            $button_url = esc_url_raw( $_POST['button_url'] );
            $button_color = sanitize_hex_color( $_POST['button_color'] );
            $position = sanitize_text_field( $_POST['position'] );
            $quiz_id = intval( $_POST['quiz_id'] );
            
            // Try new table structure first
            $result = $wpdb->insert(
                "{$table_prefix}mq_ctas",
                [
                    'name' => $cta_name,
                    'type' => $cta_type,
                    'button_text' => $button_text,
                    'button_url' => $button_url,
                    'button_color' => $button_color,
                    'position' => $position,
                    'quiz_id' => $quiz_id,
                    'status' => 1,
                    'created_date' => current_time( 'mysql' )
                ]
            );
            
            // Fallback to options table
            if ( ! $result ) {
                $ctas = get_option( 'money_quiz_ctas', [] );
                $ctas[] = [
                    'id' => time(),
                    'name' => $cta_name,
                    'type' => $cta_type,
                    'button_text' => $button_text,
                    'button_url' => $button_url,
                    'button_color' => $button_color,
                    'position' => $position,
                    'quiz_id' => $quiz_id,
                    'status' => 1
                ];
                update_option( 'money_quiz_ctas', $ctas );
                $result = true;
            }
            
            if ( $result ) {
                echo '<div class="notice notice-success"><p>' . __( 'Call-to-Action created successfully.', 'money-quiz' ) . '</p></div>';
            }
            break;
            
        case 'toggle_status':
            $cta_id = intval( $_POST['cta_id'] );
            $current_status = intval( $_POST['current_status'] );
            $new_status = $current_status ? 0 : 1;
            
            // Try database update
            $updated = $wpdb->update(
                "{$table_prefix}mq_ctas",
                ['status' => $new_status],
                ['id' => $cta_id]
            );
            
            // Fallback to options
            if ( ! $updated ) {
                $ctas = get_option( 'money_quiz_ctas', [] );
                foreach ( $ctas as &$cta ) {
                    if ( $cta['id'] == $cta_id ) {
                        $cta['status'] = $new_status;
                        break;
                    }
                }
                update_option( 'money_quiz_ctas', $ctas );
            }
            
            echo '<div class="notice notice-success"><p>' . __( 'CTA status updated.', 'money-quiz' ) . '</p></div>';
            break;
            
        case 'delete_cta':
            $cta_id = intval( $_POST['cta_id'] );
            
            // Try database delete
            $deleted = $wpdb->delete( "{$table_prefix}mq_ctas", ['id' => $cta_id] );
            
            // Fallback to options
            if ( ! $deleted ) {
                $ctas = get_option( 'money_quiz_ctas', [] );
                $ctas = array_filter( $ctas, function($cta) use ($cta_id) {
                    return $cta['id'] != $cta_id;
                });
                update_option( 'money_quiz_ctas', array_values( $ctas ) );
            }
            
            echo '<div class="notice notice-success"><p>' . __( 'CTA deleted.', 'money-quiz' ) . '</p></div>';
            break;
    }
}

// Get CTAs from database
$ctas = $wpdb->get_results( "
    SELECT 
        c.*,
        m.quiz_name,
        COUNT(DISTINCT cc.id) as click_count,
        COUNT(DISTINCT ci.id) as impression_count
    FROM {$table_prefix}mq_ctas c
    LEFT JOIN {$table_prefix}mq_master m ON c.quiz_id = m.id
    LEFT JOIN {$table_prefix}mq_cta_clicks cc ON c.id = cc.cta_id
    LEFT JOIN {$table_prefix}mq_cta_impressions ci ON c.id = ci.cta_id
    GROUP BY c.id
    ORDER BY c.created_date DESC
" );

// Fallback to options if no database results
if ( empty( $ctas ) ) {
    $option_ctas = get_option( 'money_quiz_ctas', [] );
    if ( ! empty( $option_ctas ) ) {
        $ctas = array_map( function($cta) {
            return (object) array_merge( $cta, [
                'quiz_name' => '',
                'click_count' => 0,
                'impression_count' => 0
            ]);
        }, $option_ctas );
    }
}

// Get all quizzes for dropdown
$all_quizzes = $wpdb->get_results( "SELECT id, quiz_name FROM {$table_prefix}mq_master ORDER BY quiz_name" );
if ( empty( $all_quizzes ) ) {
    $all_quizzes = $wpdb->get_results( "SELECT id, name as quiz_name FROM {$table_prefix}money_quiz_quizzes ORDER BY name" );
}

// CTA position options
$position_options = [
    'after_quiz' => __( 'After Quiz Completion', 'money-quiz' ),
    'before_results' => __( 'Before Results Display', 'money-quiz' ),
    'with_results' => __( 'With Results', 'money-quiz' ),
    'email_footer' => __( 'Email Footer', 'money-quiz' ),
    'popup' => __( 'As Popup', 'money-quiz' ),
    'sidebar' => __( 'Sidebar Widget', 'money-quiz' )
];

// CTA type options
$type_options = [
    'button' => __( 'Button', 'money-quiz' ),
    'banner' => __( 'Banner', 'money-quiz' ),
    'inline' => __( 'Inline Text', 'money-quiz' ),
    'form' => __( 'Form', 'money-quiz' ),
    'video' => __( 'Video', 'money-quiz' )
];
?>

<div class="wrap mq-cta-manager">
    
    <!-- CTA Performance Overview -->
    <div class="mq-cta-stats">
        <div class="mq-stat-box">
            <h3><?php _e( 'Total CTAs', 'money-quiz' ); ?></h3>
            <div class="mq-stat-number"><?php echo count( $ctas ); ?></div>
        </div>
        <div class="mq-stat-box">
            <h3><?php _e( 'Active CTAs', 'money-quiz' ); ?></h3>
            <div class="mq-stat-number">
                <?php echo count( array_filter( $ctas, function($c) { return $c->status == 1; } ) ); ?>
            </div>
        </div>
        <div class="mq-stat-box">
            <h3><?php _e( 'Total Clicks', 'money-quiz' ); ?></h3>
            <div class="mq-stat-number">
                <?php echo array_sum( array_column( $ctas, 'click_count' ) ); ?>
            </div>
        </div>
        <div class="mq-stat-box">
            <h3><?php _e( 'Avg. CTR', 'money-quiz' ); ?></h3>
            <div class="mq-stat-number">
                <?php 
                $total_impressions = array_sum( array_column( $ctas, 'impression_count' ) );
                $total_clicks = array_sum( array_column( $ctas, 'click_count' ) );
                $ctr = $total_impressions > 0 ? round( ( $total_clicks / $total_impressions ) * 100, 2 ) : 0;
                echo $ctr . '%';
                ?>
            </div>
        </div>
    </div>
    
    <!-- Create New CTA -->
    <div class="mq-card">
        <h2><?php _e( 'Create New Call-to-Action', 'money-quiz' ); ?></h2>
        
        <form method="post" class="cta-form">
            <?php wp_nonce_field( 'manage_ctas' ); ?>
            <input type="hidden" name="action" value="create_cta" />
            
            <table class="form-table">
                <tr>
                    <th scope="row">
                        <label for="cta_name"><?php _e( 'CTA Name', 'money-quiz' ); ?></label>
                    </th>
                    <td>
                        <input type="text" name="cta_name" id="cta_name" class="regular-text" required />
                        <p class="description"><?php _e( 'Internal name for this CTA', 'money-quiz' ); ?></p>
                    </td>
                </tr>
                
                <tr>
                    <th scope="row">
                        <label for="cta_type"><?php _e( 'CTA Type', 'money-quiz' ); ?></label>
                    </th>
                    <td>
                        <select name="cta_type" id="cta_type">
                            <?php foreach ( $type_options as $value => $label ) : ?>
                                <option value="<?php echo esc_attr( $value ); ?>">
                                    <?php echo esc_html( $label ); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </td>
                </tr>
                
                <tr>
                    <th scope="row">
                        <label for="button_text"><?php _e( 'Button Text', 'money-quiz' ); ?></label>
                    </th>
                    <td>
                        <input type="text" name="button_text" id="button_text" class="regular-text" placeholder="<?php _e( 'Get Started Now', 'money-quiz' ); ?>" />
                    </td>
                </tr>
                
                <tr>
                    <th scope="row">
                        <label for="button_url"><?php _e( 'Button URL', 'money-quiz' ); ?></label>
                    </th>
                    <td>
                        <input type="url" name="button_url" id="button_url" class="large-text" placeholder="https://" />
                        <p class="description"><?php _e( 'Where should the button link to?', 'money-quiz' ); ?></p>
                    </td>
                </tr>
                
                <tr>
                    <th scope="row">
                        <label for="button_color"><?php _e( 'Button Color', 'money-quiz' ); ?></label>
                    </th>
                    <td>
                        <input type="color" name="button_color" id="button_color" value="#0073aa" />
                    </td>
                </tr>
                
                <tr>
                    <th scope="row">
                        <label for="position"><?php _e( 'Display Position', 'money-quiz' ); ?></label>
                    </th>
                    <td>
                        <select name="position" id="position">
                            <?php foreach ( $position_options as $value => $label ) : ?>
                                <option value="<?php echo esc_attr( $value ); ?>">
                                    <?php echo esc_html( $label ); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </td>
                </tr>
                
                <tr>
                    <th scope="row">
                        <label for="quiz_id"><?php _e( 'Assign to Quiz', 'money-quiz' ); ?></label>
                    </th>
                    <td>
                        <select name="quiz_id" id="quiz_id">
                            <option value="0"><?php _e( 'All Quizzes', 'money-quiz' ); ?></option>
                            <?php foreach ( $all_quizzes as $quiz ) : ?>
                                <option value="<?php echo $quiz->id; ?>">
                                    <?php echo esc_html( $quiz->quiz_name ); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </td>
                </tr>
            </table>
            
            <p class="submit">
                <input type="submit" class="button button-primary" value="<?php _e( 'Create CTA', 'money-quiz' ); ?>" />
                <button type="button" class="button" onclick="previewCTA()">
                    <?php _e( 'Preview', 'money-quiz' ); ?>
                </button>
            </p>
        </form>
    </div>
    
    <!-- CTA List -->
    <div class="mq-card">
        <h2><?php _e( 'Manage Call-to-Actions', 'money-quiz' ); ?></h2>
        
        <?php if ( empty( $ctas ) ) : ?>
            <p><?php _e( 'No CTAs created yet.', 'money-quiz' ); ?></p>
        <?php else : ?>
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th><?php _e( 'Name', 'money-quiz' ); ?></th>
                        <th><?php _e( 'Type', 'money-quiz' ); ?></th>
                        <th><?php _e( 'Position', 'money-quiz' ); ?></th>
                        <th><?php _e( 'Quiz', 'money-quiz' ); ?></th>
                        <th><?php _e( 'Impressions', 'money-quiz' ); ?></th>
                        <th><?php _e( 'Clicks', 'money-quiz' ); ?></th>
                        <th><?php _e( 'CTR', 'money-quiz' ); ?></th>
                        <th><?php _e( 'Status', 'money-quiz' ); ?></th>
                        <th><?php _e( 'Actions', 'money-quiz' ); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ( $ctas as $cta ) : ?>
                        <tr>
                            <td>
                                <strong><?php echo esc_html( $cta->name ); ?></strong>
                                <div class="row-actions">
                                    <span class="edit">
                                        <a href="?page=money-quiz-marketing-cta&action=edit&id=<?php echo $cta->id; ?>">
                                            <?php _e( 'Edit', 'money-quiz' ); ?>
                                        </a> |
                                    </span>
                                    <span class="duplicate">
                                        <a href="?page=money-quiz-marketing-cta&action=duplicate&id=<?php echo $cta->id; ?>">
                                            <?php _e( 'Duplicate', 'money-quiz' ); ?>
                                        </a> |
                                    </span>
                                    <span class="delete">
                                        <a href="#" onclick="deleteCTA(<?php echo $cta->id; ?>); return false;">
                                            <?php _e( 'Delete', 'money-quiz' ); ?>
                                        </a>
                                    </span>
                                </div>
                            </td>
                            <td><?php echo esc_html( $type_options[$cta->type] ?? $cta->type ); ?></td>
                            <td><?php echo esc_html( $position_options[$cta->position] ?? $cta->position ); ?></td>
                            <td><?php echo $cta->quiz_id ? esc_html( $cta->quiz_name ) : __( 'All Quizzes', 'money-quiz' ); ?></td>
                            <td><?php echo number_format( $cta->impression_count ); ?></td>
                            <td><?php echo number_format( $cta->click_count ); ?></td>
                            <td>
                                <?php 
                                $ctr = $cta->impression_count > 0 
                                    ? round( ( $cta->click_count / $cta->impression_count ) * 100, 2 ) 
                                    : 0;
                                echo $ctr . '%';
                                ?>
                            </td>
                            <td>
                                <form method="post" style="display:inline;">
                                    <?php wp_nonce_field( 'manage_ctas' ); ?>
                                    <input type="hidden" name="action" value="toggle_status" />
                                    <input type="hidden" name="cta_id" value="<?php echo $cta->id; ?>" />
                                    <input type="hidden" name="current_status" value="<?php echo $cta->status; ?>" />
                                    <?php if ( $cta->status ) : ?>
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
                                <a href="#" onclick="showCTACode(<?php echo $cta->id; ?>); return false;" class="button button-small">
                                    <?php _e( 'Get Code', 'money-quiz' ); ?>
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>
    
    <!-- A/B Testing -->
    <div class="mq-card">
        <h2><?php _e( 'A/B Testing', 'money-quiz' ); ?></h2>
        <p><?php _e( 'Create variations of your CTAs to test which performs better.', 'money-quiz' ); ?></p>
        
        <div class="mq-ab-test-info">
            <h3><?php _e( 'How it works:', 'money-quiz' ); ?></h3>
            <ol>
                <li><?php _e( 'Create multiple versions of a CTA', 'money-quiz' ); ?></li>
                <li><?php _e( 'Set traffic distribution percentage', 'money-quiz' ); ?></li>
                <li><?php _e( 'Monitor performance metrics', 'money-quiz' ); ?></li>
                <li><?php _e( 'Choose the winning variation', 'money-quiz' ); ?></li>
            </ol>
            
            <a href="?page=money-quiz-marketing-ab-testing" class="button button-primary">
                <?php _e( 'Create A/B Test', 'money-quiz' ); ?>
            </a>
        </div>
    </div>
    
</div>

<!-- CTA Code Modal -->
<div id="cta-code-modal" class="mq-modal" style="display:none;">
    <div class="mq-modal-content">
        <h2><?php _e( 'CTA Implementation Code', 'money-quiz' ); ?></h2>
        <div id="cta-code-content"></div>
        <button type="button" class="button" onclick="closeCTACodeModal()">
            <?php _e( 'Close', 'money-quiz' ); ?>
        </button>
    </div>
</div>

<script>
function previewCTA() {
    // Would open preview modal
    alert('CTA preview functionality would be implemented here');
}

function deleteCTA(ctaId) {
    if (confirm('<?php _e( 'Are you sure you want to delete this CTA?', 'money-quiz' ); ?>')) {
        var form = document.createElement('form');
        form.method = 'post';
        form.innerHTML = '<?php wp_nonce_field( 'manage_ctas' ); ?>' +
            '<input type="hidden" name="action" value="delete_cta" />' +
            '<input type="hidden" name="cta_id" value="' + ctaId + '" />';
        document.body.appendChild(form);
        form.submit();
    }
}

function showCTACode(ctaId) {
    var shortcode = '[money_quiz_cta id="' + ctaId + '"]';
    var phpCode = '<?php echo do_shortcode(\'[money_quiz_cta id="' + ctaId + '"]\'); ?>';
    
    var content = '<h3><?php _e( 'Shortcode:', 'money-quiz' ); ?></h3>' +
                  '<code>' + shortcode + '</code>' +
                  '<h3><?php _e( 'PHP Code:', 'money-quiz' ); ?></h3>' +
                  '<code>' + phpCode.replace(/</g, '&lt;').replace(/>/g, '&gt;') + '</code>' +
                  '<h3><?php _e( 'Widget:', 'money-quiz' ); ?></h3>' +
                  '<p><?php _e( 'You can also add this CTA using the Money Quiz CTA widget.', 'money-quiz' ); ?></p>';
    
    document.getElementById('cta-code-content').innerHTML = content;
    document.getElementById('cta-code-modal').style.display = 'block';
}

function closeCTACodeModal() {
    document.getElementById('cta-code-modal').style.display = 'none';
}
</script>

<style>
.mq-cta-stats {
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

.mq-ab-test-info {
    background: #f0f8ff;
    padding: 20px;
    border-radius: 4px;
    margin-top: 20px;
}

.mq-ab-test-info ol {
    margin: 15px 0 20px 30px;
}

.mq-modal {
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(0,0,0,0.5);
    z-index: 999999;
    display: flex;
    align-items: center;
    justify-content: center;
}

.mq-modal-content {
    background: white;
    padding: 30px;
    border-radius: 8px;
    max-width: 600px;
    width: 90%;
}

.mq-modal-content code {
    display: block;
    padding: 10px;
    background: #f0f0f0;
    margin: 10px 0;
    border-radius: 4px;
}
</style>
<?php
/**
 * Archetypes Management Template
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

// Get quiz ID if specified
$quiz_id = isset( $_GET['quiz_id'] ) ? intval( $_GET['quiz_id'] ) : 0;

// Handle archetype actions
if ( isset( $_POST['action'] ) ) {
    check_admin_referer( 'manage_archetypes' );
    
    switch ( $_POST['action'] ) {
        case 'add_archetype':
            $name = sanitize_text_field( $_POST['archetype_name'] );
            $description = sanitize_textarea_field( $_POST['archetype_description'] );
            $quiz_id_new = intval( $_POST['quiz_id'] );
            $score_range = sanitize_text_field( $_POST['score_range'] );
            $recommendations = sanitize_textarea_field( $_POST['recommendations'] );
            
            if ( ! empty( $name ) && $quiz_id_new > 0 ) {
                $wpdb->insert(
                    "{$table_prefix}mq_archetypes",
                    [
                        'quiz_id' => $quiz_id_new,
                        'name' => $name,
                        'description' => $description,
                        'score_range' => $score_range,
                        'recommendations' => $recommendations,
                        'created_date' => current_time( 'mysql' )
                    ]
                );
                
                echo '<div class="notice notice-success"><p>' . __( 'Archetype added successfully.', 'money-quiz' ) . '</p></div>';
            }
            break;
            
        case 'update_archetype':
            $archetype_id = intval( $_POST['archetype_id'] );
            $name = sanitize_text_field( $_POST['archetype_name'] );
            $description = sanitize_textarea_field( $_POST['archetype_description'] );
            $score_range = sanitize_text_field( $_POST['score_range'] );
            $recommendations = sanitize_textarea_field( $_POST['recommendations'] );
            
            $wpdb->update(
                "{$table_prefix}mq_archetypes",
                [
                    'name' => $name,
                    'description' => $description,
                    'score_range' => $score_range,
                    'recommendations' => $recommendations
                ],
                ['id' => $archetype_id]
            );
            
            echo '<div class="notice notice-success"><p>' . __( 'Archetype updated successfully.', 'money-quiz' ) . '</p></div>';
            break;
            
        case 'delete_archetype':
            $archetype_id = intval( $_POST['archetype_id'] );
            $wpdb->delete( "{$table_prefix}mq_archetypes", ['id' => $archetype_id] );
            echo '<div class="notice notice-success"><p>' . __( 'Archetype deleted.', 'money-quiz' ) . '</p></div>';
            break;
    }
}

// Get all quizzes
$all_quizzes = $wpdb->get_results( "SELECT id, quiz_name FROM {$table_prefix}mq_master ORDER BY quiz_name" );

// Get archetypes
$where_sql = $quiz_id > 0 ? $wpdb->prepare( "WHERE a.quiz_id = %d", $quiz_id ) : "";
$archetypes = $wpdb->get_results( "
    SELECT a.*, m.quiz_name,
           (SELECT COUNT(*) FROM {$table_prefix}mq_prospects WHERE archetype_id = a.id) as lead_count
    FROM {$table_prefix}mq_archetypes a
    LEFT JOIN {$table_prefix}mq_master m ON a.quiz_id = m.id
    $where_sql
    ORDER BY a.quiz_id, a.name
" );

// Group archetypes by quiz
$archetypes_by_quiz = [];
foreach ( $archetypes as $archetype ) {
    if ( ! isset( $archetypes_by_quiz[ $archetype->quiz_id ] ) ) {
        $archetypes_by_quiz[ $archetype->quiz_id ] = [
            'quiz_name' => $archetype->quiz_name,
            'archetypes' => []
        ];
    }
    $archetypes_by_quiz[ $archetype->quiz_id ]['archetypes'][] = $archetype;
}

// Get archetype templates
$archetype_templates = [
    'money_personality' => [
        ['name' => 'The Saver', 'description' => 'Values security and building wealth slowly'],
        ['name' => 'The Spender', 'description' => 'Enjoys life now and focuses on experiences'],
        ['name' => 'The Investor', 'description' => 'Actively grows wealth through calculated risks'],
        ['name' => 'The Balancer', 'description' => 'Maintains equilibrium between saving and spending']
    ],
    'risk_tolerance' => [
        ['name' => 'Conservative', 'description' => 'Prefers stability and guaranteed returns'],
        ['name' => 'Moderate', 'description' => 'Balanced approach to risk and reward'],
        ['name' => 'Aggressive', 'description' => 'Comfortable with high risk for potential high returns']
    ],
    'financial_knowledge' => [
        ['name' => 'Beginner', 'description' => 'Just starting the financial education journey'],
        ['name' => 'Intermediate', 'description' => 'Solid foundation with room to grow'],
        ['name' => 'Expert', 'description' => 'Advanced knowledge and experience']
    ]
];
?>

<div class="wrap mq-archetypes-manager">
    
    <!-- Filter by Quiz -->
    <div class="mq-archetypes-filter">
        <form method="get" class="quiz-filter-form">
            <input type="hidden" name="page" value="money-quiz-quizzes-archetypes" />
            <label for="quiz_filter"><?php _e( 'Filter by Quiz:', 'money-quiz' ); ?></label>
            <select name="quiz_id" id="quiz_filter" onchange="this.form.submit()">
                <option value="0"><?php _e( 'All Quizzes', 'money-quiz' ); ?></option>
                <?php foreach ( $all_quizzes as $quiz ) : ?>
                    <option value="<?php echo $quiz->id; ?>" <?php selected( $quiz_id, $quiz->id ); ?>>
                        <?php echo esc_html( $quiz->quiz_name ); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </form>
    </div>
    
    <!-- Add New Archetype -->
    <div class="mq-card">
        <h2><?php _e( 'Add New Archetype', 'money-quiz' ); ?></h2>
        
        <form method="post" class="add-archetype-form">
            <?php wp_nonce_field( 'manage_archetypes' ); ?>
            <input type="hidden" name="action" value="add_archetype" />
            
            <table class="form-table">
                <tr>
                    <th scope="row">
                        <label for="archetype_name"><?php _e( 'Archetype Name', 'money-quiz' ); ?></label>
                    </th>
                    <td>
                        <input type="text" name="archetype_name" id="archetype_name" class="regular-text" required />
                        <p class="description"><?php _e( 'e.g., "The Saver", "Risk Taker", "Conservative Investor"', 'money-quiz' ); ?></p>
                    </td>
                </tr>
                <tr>
                    <th scope="row">
                        <label for="archetype_description"><?php _e( 'Description', 'money-quiz' ); ?></label>
                    </th>
                    <td>
                        <textarea name="archetype_description" id="archetype_description" rows="3" class="large-text"></textarea>
                        <p class="description"><?php _e( 'Describe the characteristics of this archetype', 'money-quiz' ); ?></p>
                    </td>
                </tr>
                <tr>
                    <th scope="row">
                        <label for="quiz_id_new"><?php _e( 'Quiz', 'money-quiz' ); ?></label>
                    </th>
                    <td>
                        <select name="quiz_id" id="quiz_id_new" required>
                            <option value=""><?php _e( 'Select a quiz', 'money-quiz' ); ?></option>
                            <?php foreach ( $all_quizzes as $quiz ) : ?>
                                <option value="<?php echo $quiz->id; ?>" <?php selected( $quiz_id, $quiz->id ); ?>>
                                    <?php echo esc_html( $quiz->quiz_name ); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </td>
                </tr>
                <tr>
                    <th scope="row">
                        <label for="score_range"><?php _e( 'Score Range', 'money-quiz' ); ?></label>
                    </th>
                    <td>
                        <input type="text" name="score_range" id="score_range" class="regular-text" placeholder="e.g., 0-25, 26-50" />
                        <p class="description"><?php _e( 'Score range that triggers this archetype', 'money-quiz' ); ?></p>
                    </td>
                </tr>
                <tr>
                    <th scope="row">
                        <label for="recommendations"><?php _e( 'Recommendations', 'money-quiz' ); ?></label>
                    </th>
                    <td>
                        <textarea name="recommendations" id="recommendations" rows="4" class="large-text"></textarea>
                        <p class="description"><?php _e( 'Personalized recommendations for this archetype', 'money-quiz' ); ?></p>
                    </td>
                </tr>
            </table>
            
            <p class="submit">
                <input type="submit" class="button button-primary" value="<?php _e( 'Add Archetype', 'money-quiz' ); ?>" />
                <button type="button" class="button" onclick="showArchetypeTemplates()">
                    <?php _e( 'Use Template', 'money-quiz' ); ?>
                </button>
            </p>
        </form>
    </div>
    
    <!-- Archetypes List -->
    <?php if ( empty( $archetypes_by_quiz ) ) : ?>
        <div class="mq-card">
            <p><?php _e( 'No archetypes found. Create your first archetype above.', 'money-quiz' ); ?></p>
        </div>
    <?php else : ?>
        <?php foreach ( $archetypes_by_quiz as $quiz_id => $quiz_data ) : ?>
            <div class="mq-card mq-quiz-archetypes">
                <h2>
                    <?php echo esc_html( $quiz_data['quiz_name'] ); ?>
                    <span class="archetype-count"><?php echo sprintf( __( '%d archetypes', 'money-quiz' ), count( $quiz_data['archetypes'] ) ); ?></span>
                </h2>
                
                <div class="mq-archetypes-grid">
                    <?php foreach ( $quiz_data['archetypes'] as $archetype ) : ?>
                        <div class="mq-archetype-card" data-archetype-id="<?php echo $archetype->id; ?>">
                            <div class="mq-archetype-header">
                                <h3><?php echo esc_html( $archetype->name ); ?></h3>
                                <div class="mq-archetype-actions">
                                    <a href="#" class="edit-archetype" data-archetype-id="<?php echo $archetype->id; ?>">
                                        <?php _e( 'Edit', 'money-quiz' ); ?>
                                    </a>
                                    <a href="#" class="delete-archetype" data-archetype-id="<?php echo $archetype->id; ?>">
                                        <?php _e( 'Delete', 'money-quiz' ); ?>
                                    </a>
                                </div>
                            </div>
                            
                            <p class="mq-archetype-description"><?php echo esc_html( $archetype->description ); ?></p>
                            
                            <?php if ( ! empty( $archetype->score_range ) ) : ?>
                                <div class="mq-archetype-meta">
                                    <span class="meta-label"><?php _e( 'Score Range:', 'money-quiz' ); ?></span>
                                    <span class="meta-value"><?php echo esc_html( $archetype->score_range ); ?></span>
                                </div>
                            <?php endif; ?>
                            
                            <div class="mq-archetype-meta">
                                <span class="meta-label"><?php _e( 'Leads:', 'money-quiz' ); ?></span>
                                <span class="meta-value"><?php echo number_format( $archetype->lead_count ); ?></span>
                            </div>
                            
                            <?php if ( ! empty( $archetype->recommendations ) ) : ?>
                                <div class="mq-archetype-recommendations">
                                    <strong><?php _e( 'Recommendations:', 'money-quiz' ); ?></strong>
                                    <p><?php echo esc_html( substr( $archetype->recommendations, 0, 100 ) ); ?>...</p>
                                </div>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>
    
    <!-- Archetype Analytics -->
    <div class="mq-card">
        <h2><?php _e( 'Archetype Distribution', 'money-quiz' ); ?></h2>
        
        <?php
        // Get archetype distribution
        $distribution = $wpdb->get_results( "
            SELECT a.name, a.quiz_id, m.quiz_name, COUNT(p.id) as count
            FROM {$table_prefix}mq_archetypes a
            LEFT JOIN {$table_prefix}mq_prospects p ON a.id = p.archetype_id
            LEFT JOIN {$table_prefix}mq_master m ON a.quiz_id = m.id
            GROUP BY a.id
            ORDER BY count DESC
        " );
        ?>
        
        <?php if ( ! empty( $distribution ) ) : ?>
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th><?php _e( 'Archetype', 'money-quiz' ); ?></th>
                        <th><?php _e( 'Quiz', 'money-quiz' ); ?></th>
                        <th><?php _e( 'Lead Count', 'money-quiz' ); ?></th>
                        <th><?php _e( 'Percentage', 'money-quiz' ); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php 
                    $total = array_sum( array_column( $distribution, 'count' ) );
                    foreach ( $distribution as $item ) : 
                        $percentage = $total > 0 ? round( ( $item->count / $total ) * 100, 1 ) : 0;
                    ?>
                        <tr>
                            <td><strong><?php echo esc_html( $item->name ); ?></strong></td>
                            <td><?php echo esc_html( $item->quiz_name ); ?></td>
                            <td><?php echo number_format( $item->count ); ?></td>
                            <td>
                                <div class="mq-percentage-bar">
                                    <div class="mq-percentage-fill" style="width: <?php echo $percentage; ?>%"></div>
                                    <span class="mq-percentage-text"><?php echo $percentage; ?>%</span>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php else : ?>
            <p><?php _e( 'No archetype data available yet.', 'money-quiz' ); ?></p>
        <?php endif; ?>
    </div>
    
</div>

<!-- Archetype Templates Modal -->
<div id="archetype-templates-modal" class="mq-modal" style="display: none;">
    <div class="mq-modal-content">
        <h2><?php _e( 'Archetype Templates', 'money-quiz' ); ?></h2>
        <div class="mq-templates-grid">
            <?php foreach ( $archetype_templates as $template_key => $templates ) : ?>
                <div class="mq-template-group">
                    <h3><?php echo ucwords( str_replace( '_', ' ', $template_key ) ); ?></h3>
                    <?php foreach ( $templates as $template ) : ?>
                        <div class="mq-template-item" onclick="useArchetypeTemplate('<?php echo esc_attr( json_encode( $template ) ); ?>')">
                            <strong><?php echo esc_html( $template['name'] ); ?></strong>
                            <p><?php echo esc_html( $template['description'] ); ?></p>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endforeach; ?>
        </div>
        <button type="button" class="button" onclick="closeArchetypeTemplates()"><?php _e( 'Close', 'money-quiz' ); ?></button>
    </div>
</div>

<script>
function showArchetypeTemplates() {
    document.getElementById('archetype-templates-modal').style.display = 'block';
}

function closeArchetypeTemplates() {
    document.getElementById('archetype-templates-modal').style.display = 'none';
}

function useArchetypeTemplate(templateJson) {
    var template = JSON.parse(templateJson);
    document.getElementById('archetype_name').value = template.name;
    document.getElementById('archetype_description').value = template.description;
    closeArchetypeTemplates();
}

jQuery(document).ready(function($) {
    // Delete archetype
    $('.delete-archetype').on('click', function(e) {
        e.preventDefault();
        if (confirm('<?php _e( 'Are you sure you want to delete this archetype?', 'money-quiz' ); ?>')) {
            var archetypeId = $(this).data('archetype-id');
            $('<form method="post">' +
              '<?php wp_nonce_field( 'manage_archetypes' ); ?>' +
              '<input type="hidden" name="action" value="delete_archetype" />' +
              '<input type="hidden" name="archetype_id" value="' + archetypeId + '" />' +
              '</form>').appendTo('body').submit();
        }
    });
});
</script>

<style>
.archetype-count {
    font-size: 14px;
    color: #666;
    font-weight: normal;
    margin-left: 10px;
}

.mq-archetypes-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
    gap: 20px;
    margin-top: 20px;
}

.mq-archetype-card {
    background: #f9f9f9;
    border: 1px solid #ddd;
    border-radius: 8px;
    padding: 20px;
    position: relative;
}

.mq-archetype-header {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    margin-bottom: 10px;
}

.mq-archetype-card h3 {
    margin: 0;
    color: #23282d;
}

.mq-archetype-actions {
    display: flex;
    gap: 10px;
    font-size: 13px;
}

.mq-archetype-description {
    color: #666;
    margin: 10px 0;
}

.mq-archetype-meta {
    display: flex;
    justify-content: space-between;
    margin: 5px 0;
    font-size: 13px;
}

.meta-label {
    color: #666;
}

.meta-value {
    font-weight: 600;
}

.mq-archetype-recommendations {
    margin-top: 15px;
    padding-top: 15px;
    border-top: 1px solid #e0e0e0;
    font-size: 13px;
}

.mq-percentage-bar {
    position: relative;
    background: #e0e0e0;
    height: 20px;
    border-radius: 3px;
    overflow: hidden;
}

.mq-percentage-fill {
    background: #0073aa;
    height: 100%;
    transition: width 0.3s ease;
}

.mq-percentage-text {
    position: absolute;
    top: 50%;
    left: 10px;
    transform: translateY(-50%);
    font-size: 12px;
    font-weight: 600;
}

.mq-modal {
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(0,0,0,0.5);
    z-index: 999999;
}

.mq-modal-content {
    background: white;
    max-width: 800px;
    margin: 50px auto;
    padding: 30px;
    border-radius: 8px;
}

.mq-template-item {
    background: #f0f0f0;
    padding: 15px;
    margin: 10px 0;
    border-radius: 4px;
    cursor: pointer;
}

.mq-template-item:hover {
    background: #e0e0e0;
}
</style>
<?php
/**
 * Add New Quiz Template
 * 
 * @package MoneyQuiz
 * @since 4.2.0
 */

// Security check
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

global $wpdb;

// Handle form submission
if ( isset( $_POST['save_quiz'] ) ) {
    check_admin_referer( 'create_new_quiz' );
    
    $quiz_name = sanitize_text_field( $_POST['quiz_name'] );
    $quiz_description = sanitize_textarea_field( $_POST['quiz_description'] );
    $template = sanitize_text_field( $_POST['quiz_template'] );
    $status = isset( $_POST['quiz_status'] ) ? 1 : 0;
    
    if ( ! empty( $quiz_name ) ) {
        // Create new quiz
        $result = $wpdb->insert(
            $wpdb->prefix . 'mq_master',
            [
                'quiz_name' => $quiz_name,
                'quiz_description' => $quiz_description,
                'status' => $status,
                'created_date' => current_time( 'mysql' ),
                'modified_date' => current_time( 'mysql' )
            ]
        );
        
        if ( $result ) {
            $quiz_id = $wpdb->insert_id;
            
            // Apply template if selected
            if ( $template !== 'blank' ) {
                apply_quiz_template( $quiz_id, $template );
            }
            
            // Redirect to edit page
            wp_redirect( admin_url( 'admin.php?page=money-quiz-quizzes-edit&quiz_id=' . $quiz_id . '&message=created' ) );
            exit;
        } else {
            $error = __( 'Failed to create quiz. Please try again.', 'money-quiz' );
        }
    } else {
        $error = __( 'Quiz name is required.', 'money-quiz' );
    }
}

// Get available templates
$templates = [
    'blank' => [
        'name' => __( 'Blank Quiz', 'money-quiz' ),
        'description' => __( 'Start with a completely blank quiz', 'money-quiz' ),
        'icon' => '📄'
    ],
    'personality' => [
        'name' => __( 'Money Personality Quiz', 'money-quiz' ),
        'description' => __( 'Discover your financial personality type', 'money-quiz' ),
        'icon' => '🧠',
        'questions' => 10,
        'archetypes' => 4
    ],
    'knowledge' => [
        'name' => __( 'Financial Knowledge Test', 'money-quiz' ),
        'description' => __( 'Test financial literacy and knowledge', 'money-quiz' ),
        'icon' => '📚',
        'questions' => 15,
        'archetypes' => 3
    ],
    'goals' => [
        'name' => __( 'Financial Goals Assessment', 'money-quiz' ),
        'description' => __( 'Identify and prioritize financial goals', 'money-quiz' ),
        'icon' => '🎯',
        'questions' => 8,
        'archetypes' => 5
    ],
    'risk' => [
        'name' => __( 'Risk Tolerance Quiz', 'money-quiz' ),
        'description' => __( 'Assess investment risk tolerance', 'money-quiz' ),
        'icon' => '📊',
        'questions' => 12,
        'archetypes' => 4
    ]
];

/**
 * Apply quiz template
 */
function apply_quiz_template( $quiz_id, $template ) {
    global $wpdb;
    
    switch ( $template ) {
        case 'personality':
            // Add sample questions for personality quiz
            $questions = [
                ['question' => 'How do you feel about budgeting?', 'type' => 'multiple'],
                ['question' => 'What\'s your approach to saving money?', 'type' => 'multiple'],
                ['question' => 'How do you make financial decisions?', 'type' => 'multiple'],
                ['question' => 'What\'s your biggest financial challenge?', 'type' => 'multiple'],
                ['question' => 'How do you view investing?', 'type' => 'multiple'],
                ['question' => 'What motivates your financial goals?', 'type' => 'multiple'],
                ['question' => 'How do you handle unexpected expenses?', 'type' => 'multiple'],
                ['question' => 'What\'s your relationship with credit?', 'type' => 'multiple'],
                ['question' => 'How important is financial security to you?', 'type' => 'scale'],
                ['question' => 'What\'s your ideal financial future?', 'type' => 'multiple']
            ];
            
            // Add archetypes
            $archetypes = [
                ['name' => 'The Saver', 'description' => 'Focused on security and building wealth slowly'],
                ['name' => 'The Spender', 'description' => 'Enjoys life now and worries less about the future'],
                ['name' => 'The Investor', 'description' => 'Actively grows wealth through calculated risks'],
                ['name' => 'The Balancer', 'description' => 'Maintains equilibrium between saving and spending']
            ];
            
            break;
            
        case 'knowledge':
            $questions = [
                ['question' => 'What is compound interest?', 'type' => 'multiple'],
                ['question' => 'How does inflation affect purchasing power?', 'type' => 'multiple'],
                ['question' => 'What is diversification in investing?', 'type' => 'multiple'],
                // Add more knowledge questions...
            ];
            
            $archetypes = [
                ['name' => 'Financial Expert', 'description' => 'High level of financial knowledge'],
                ['name' => 'Learning Enthusiast', 'description' => 'Good foundation with room to grow'],
                ['name' => 'Financial Beginner', 'description' => 'Just starting the financial journey']
            ];
            break;
            
        // Add other template cases...
    }
    
    // Insert questions if defined
    if ( isset( $questions ) ) {
        foreach ( $questions as $index => $q ) {
            $wpdb->insert(
                $wpdb->prefix . 'mq_questions',
                [
                    'quiz_id' => $quiz_id,
                    'question' => $q['question'],
                    'question_order' => $index + 1,
                    'question_type' => $q['type'] ?? 'multiple'
                ]
            );
        }
    }
    
    // Insert archetypes if defined
    if ( isset( $archetypes ) ) {
        foreach ( $archetypes as $archetype ) {
            $wpdb->insert(
                $wpdb->prefix . 'mq_archetypes',
                [
                    'quiz_id' => $quiz_id,
                    'name' => $archetype['name'],
                    'description' => $archetype['description']
                ]
            );
        }
    }
}
?>

<div class="wrap mq-add-new-quiz">
    
    <?php if ( isset( $error ) ) : ?>
        <div class="notice notice-error">
            <p><?php echo esc_html( $error ); ?></p>
        </div>
    <?php endif; ?>
    
    <form method="post" id="create-quiz-form">
        <?php wp_nonce_field( 'create_new_quiz' ); ?>
        
        <!-- Quiz Details -->
        <div class="mq-card">
            <h2><?php _e( 'Quiz Details', 'money-quiz' ); ?></h2>
            
            <table class="form-table">
                <tr>
                    <th scope="row">
                        <label for="quiz_name"><?php _e( 'Quiz Name', 'money-quiz' ); ?> <span class="required">*</span></label>
                    </th>
                    <td>
                        <input type="text" name="quiz_name" id="quiz_name" class="regular-text" required />
                        <p class="description"><?php _e( 'Enter a descriptive name for your quiz', 'money-quiz' ); ?></p>
                    </td>
                </tr>
                
                <tr>
                    <th scope="row">
                        <label for="quiz_description"><?php _e( 'Description', 'money-quiz' ); ?></label>
                    </th>
                    <td>
                        <textarea name="quiz_description" id="quiz_description" rows="3" class="large-text"></textarea>
                        <p class="description"><?php _e( 'Optional description for internal reference', 'money-quiz' ); ?></p>
                    </td>
                </tr>
                
                <tr>
                    <th scope="row">
                        <label for="quiz_status"><?php _e( 'Status', 'money-quiz' ); ?></label>
                    </th>
                    <td>
                        <label>
                            <input type="checkbox" name="quiz_status" id="quiz_status" value="1" checked />
                            <?php _e( 'Active (quiz will be available to users)', 'money-quiz' ); ?>
                        </label>
                    </td>
                </tr>
            </table>
        </div>
        
        <!-- Quiz Template -->
        <div class="mq-card">
            <h2><?php _e( 'Choose a Template', 'money-quiz' ); ?></h2>
            <p><?php _e( 'Start with a pre-built template or create from scratch', 'money-quiz' ); ?></p>
            
            <div class="mq-template-grid">
                <?php foreach ( $templates as $key => $template ) : ?>
                    <label class="mq-template-option">
                        <input type="radio" name="quiz_template" value="<?php echo esc_attr( $key ); ?>" <?php checked( $key, 'blank' ); ?> />
                        <div class="mq-template-card">
                            <div class="mq-template-icon"><?php echo $template['icon']; ?></div>
                            <h3><?php echo esc_html( $template['name'] ); ?></h3>
                            <p><?php echo esc_html( $template['description'] ); ?></p>
                            <?php if ( isset( $template['questions'] ) ) : ?>
                                <div class="mq-template-meta">
                                    <span><?php echo sprintf( __( '%d questions', 'money-quiz' ), $template['questions'] ); ?></span>
                                    <span><?php echo sprintf( __( '%d archetypes', 'money-quiz' ), $template['archetypes'] ); ?></span>
                                </div>
                            <?php endif; ?>
                        </div>
                    </label>
                <?php endforeach; ?>
            </div>
        </div>
        
        <!-- Advanced Options -->
        <div class="mq-card mq-advanced-options" style="display: none;">
            <h2><?php _e( 'Advanced Options', 'money-quiz' ); ?></h2>
            
            <table class="form-table">
                <tr>
                    <th scope="row">
                        <label for="quiz_style"><?php _e( 'Quiz Style', 'money-quiz' ); ?></label>
                    </th>
                    <td>
                        <select name="quiz_style" id="quiz_style">
                            <option value="standard"><?php _e( 'Standard (One question per page)', 'money-quiz' ); ?></option>
                            <option value="single"><?php _e( 'Single Page (All questions visible)', 'money-quiz' ); ?></option>
                            <option value="stepped"><?php _e( 'Multi-step (Progress bar)', 'money-quiz' ); ?></option>
                        </select>
                    </td>
                </tr>
                
                <tr>
                    <th scope="row">
                        <label for="result_display"><?php _e( 'Result Display', 'money-quiz' ); ?></label>
                    </th>
                    <td>
                        <select name="result_display" id="result_display">
                            <option value="immediate"><?php _e( 'Show results immediately', 'money-quiz' ); ?></option>
                            <option value="email"><?php _e( 'Email results only', 'money-quiz' ); ?></option>
                            <option value="both"><?php _e( 'Show and email results', 'money-quiz' ); ?></option>
                        </select>
                    </td>
                </tr>
            </table>
        </div>
        
        <!-- Submit -->
        <p class="submit">
            <input type="submit" name="save_quiz" class="button button-primary button-large" value="<?php _e( 'Create Quiz', 'money-quiz' ); ?>" />
            <a href="<?php echo admin_url( 'admin.php?page=money-quiz-quizzes-all' ); ?>" class="button button-large">
                <?php _e( 'Cancel', 'money-quiz' ); ?>
            </a>
            <button type="button" class="button button-link" onclick="jQuery('.mq-advanced-options').toggle();">
                <?php _e( 'Advanced Options', 'money-quiz' ); ?>
            </button>
        </p>
    </form>
    
</div>

<style>
.mq-add-new-quiz .required {
    color: #dc3232;
}

.mq-template-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
    gap: 20px;
    margin-top: 20px;
}

.mq-template-option {
    cursor: pointer;
    position: relative;
}

.mq-template-option input[type="radio"] {
    position: absolute;
    opacity: 0;
}

.mq-template-card {
    border: 2px solid #ddd;
    border-radius: 8px;
    padding: 20px;
    text-align: center;
    transition: all 0.3s ease;
    background: #fff;
}

.mq-template-option input[type="radio"]:checked + .mq-template-card {
    border-color: #0073aa;
    background: #f0f8ff;
}

.mq-template-card:hover {
    border-color: #0073aa;
    transform: translateY(-2px);
    box-shadow: 0 4px 8px rgba(0,0,0,0.1);
}

.mq-template-icon {
    font-size: 48px;
    margin-bottom: 10px;
}

.mq-template-card h3 {
    margin: 10px 0;
    font-size: 16px;
}

.mq-template-card p {
    font-size: 13px;
    color: #666;
    margin: 0;
}

.mq-template-meta {
    margin-top: 15px;
    padding-top: 15px;
    border-top: 1px solid #eee;
    font-size: 12px;
    color: #999;
}

.mq-template-meta span {
    margin: 0 5px;
}

.mq-advanced-options {
    margin-top: 20px;
}
</style>
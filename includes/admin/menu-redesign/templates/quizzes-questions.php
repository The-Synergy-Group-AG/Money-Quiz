<?php
/**
 * Questions Bank Template
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

// Handle question actions
if ( isset( $_POST['action'] ) ) {
    check_admin_referer( 'manage_questions' );
    
    switch ( $_POST['action'] ) {
        case 'add_question':
            $question = sanitize_textarea_field( $_POST['question'] );
            $question_type = sanitize_text_field( $_POST['question_type'] );
            $quiz_id_new = intval( $_POST['quiz_id'] );
            
            if ( ! empty( $question ) && $quiz_id_new > 0 ) {
                // Get max order
                $max_order = $wpdb->get_var( $wpdb->prepare(
                    "SELECT MAX(question_order) FROM {$table_prefix}mq_questions WHERE quiz_id = %d",
                    $quiz_id_new
                ) );
                
                $wpdb->insert(
                    "{$table_prefix}mq_questions",
                    [
                        'quiz_id' => $quiz_id_new,
                        'question' => $question,
                        'question_type' => $question_type,
                        'question_order' => ( $max_order + 1 )
                    ]
                );
                
                echo '<div class="notice notice-success"><p>' . __( 'Question added successfully.', 'money-quiz' ) . '</p></div>';
            }
            break;
            
        case 'update_order':
            if ( isset( $_POST['question_order'] ) ) {
                foreach ( $_POST['question_order'] as $question_id => $order ) {
                    $wpdb->update(
                        "{$table_prefix}mq_questions",
                        ['question_order' => intval( $order )],
                        ['id' => intval( $question_id )]
                    );
                }
                echo '<div class="notice notice-success"><p>' . __( 'Question order updated.', 'money-quiz' ) . '</p></div>';
            }
            break;
            
        case 'delete_question':
            $question_id = intval( $_POST['question_id'] );
            $wpdb->delete( "{$table_prefix}mq_questions", ['id' => $question_id] );
            echo '<div class="notice notice-success"><p>' . __( 'Question deleted.', 'money-quiz' ) . '</p></div>';
            break;
    }
}

// Get all quizzes for dropdown
$all_quizzes = $wpdb->get_results( "SELECT id, quiz_name FROM {$table_prefix}mq_master ORDER BY quiz_name" );

// Get questions
$where_sql = $quiz_id > 0 ? $wpdb->prepare( "WHERE quiz_id = %d", $quiz_id ) : "";
$questions = $wpdb->get_results( "
    SELECT q.*, m.quiz_name 
    FROM {$table_prefix}mq_questions q
    LEFT JOIN {$table_prefix}mq_master m ON q.quiz_id = m.id
    $where_sql
    ORDER BY q.quiz_id, q.question_order
" );

// Group questions by quiz
$questions_by_quiz = [];
foreach ( $questions as $question ) {
    if ( ! isset( $questions_by_quiz[ $question->quiz_id ] ) ) {
        $questions_by_quiz[ $question->quiz_id ] = [
            'quiz_name' => $question->quiz_name,
            'questions' => []
        ];
    }
    $questions_by_quiz[ $question->quiz_id ]['questions'][] = $question;
}
?>

<div class="wrap mq-questions-bank">
    
    <!-- Filter by Quiz -->
    <div class="mq-questions-filter">
        <form method="get" class="quiz-filter-form">
            <input type="hidden" name="page" value="money-quiz-quizzes-questions" />
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
    
    <!-- Add New Question -->
    <div class="mq-card">
        <h2><?php _e( 'Add New Question', 'money-quiz' ); ?></h2>
        
        <form method="post" class="add-question-form">
            <?php wp_nonce_field( 'manage_questions' ); ?>
            <input type="hidden" name="action" value="add_question" />
            
            <table class="form-table">
                <tr>
                    <th scope="row">
                        <label for="new_question"><?php _e( 'Question', 'money-quiz' ); ?></label>
                    </th>
                    <td>
                        <textarea name="question" id="new_question" rows="3" class="large-text" required></textarea>
                    </td>
                </tr>
                <tr>
                    <th scope="row">
                        <label for="question_type"><?php _e( 'Question Type', 'money-quiz' ); ?></label>
                    </th>
                    <td>
                        <select name="question_type" id="question_type">
                            <option value="multiple"><?php _e( 'Multiple Choice', 'money-quiz' ); ?></option>
                            <option value="single"><?php _e( 'Single Choice', 'money-quiz' ); ?></option>
                            <option value="scale"><?php _e( 'Scale (1-10)', 'money-quiz' ); ?></option>
                            <option value="text"><?php _e( 'Text Input', 'money-quiz' ); ?></option>
                            <option value="email"><?php _e( 'Email Capture', 'money-quiz' ); ?></option>
                        </select>
                    </td>
                </tr>
                <tr>
                    <th scope="row">
                        <label for="quiz_id_new"><?php _e( 'Add to Quiz', 'money-quiz' ); ?></label>
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
            </table>
            
            <p class="submit">
                <input type="submit" class="button button-primary" value="<?php _e( 'Add Question', 'money-quiz' ); ?>" />
            </p>
        </form>
    </div>
    
    <!-- Questions List -->
    <?php if ( empty( $questions_by_quiz ) ) : ?>
        <div class="mq-card">
            <p><?php _e( 'No questions found. Add your first question above.', 'money-quiz' ); ?></p>
        </div>
    <?php else : ?>
        <?php foreach ( $questions_by_quiz as $quiz_id => $quiz_data ) : ?>
            <div class="mq-card mq-quiz-questions">
                <h2>
                    <?php echo esc_html( $quiz_data['quiz_name'] ); ?>
                    <span class="question-count"><?php echo sprintf( __( '%d questions', 'money-quiz' ), count( $quiz_data['questions'] ) ); ?></span>
                </h2>
                
                <form method="post" class="questions-list-form">
                    <?php wp_nonce_field( 'manage_questions' ); ?>
                    <input type="hidden" name="action" value="update_order" />
                    
                    <div class="mq-questions-list" data-quiz-id="<?php echo $quiz_id; ?>">
                        <?php foreach ( $quiz_data['questions'] as $index => $question ) : ?>
                            <div class="mq-question-item" data-question-id="<?php echo $question->id; ?>">
                                <div class="mq-question-header">
                                    <span class="mq-drag-handle">☰</span>
                                    <span class="mq-question-number"><?php echo $index + 1; ?></span>
                                    <input type="hidden" name="question_order[<?php echo $question->id; ?>]" value="<?php echo $question->question_order; ?>" class="question-order" />
                                </div>
                                
                                <div class="mq-question-content">
                                    <div class="mq-question-text">
                                        <?php echo esc_html( $question->question ); ?>
                                    </div>
                                    <div class="mq-question-meta">
                                        <span class="question-type"><?php echo ucfirst( $question->question_type ); ?></span>
                                        <?php if ( $question->is_required ) : ?>
                                            <span class="required-badge"><?php _e( 'Required', 'money-quiz' ); ?></span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                
                                <div class="mq-question-actions">
                                    <a href="#" class="edit-question" data-question-id="<?php echo $question->id; ?>">
                                        <?php _e( 'Edit', 'money-quiz' ); ?>
                                    </a>
                                    <a href="#" class="duplicate-question" data-question-id="<?php echo $question->id; ?>">
                                        <?php _e( 'Duplicate', 'money-quiz' ); ?>
                                    </a>
                                    <a href="#" class="delete-question" data-question-id="<?php echo $question->id; ?>">
                                        <?php _e( 'Delete', 'money-quiz' ); ?>
                                    </a>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    
                    <p class="submit">
                        <input type="submit" class="button" value="<?php _e( 'Save Order', 'money-quiz' ); ?>" />
                        <a href="<?php echo admin_url( 'admin.php?page=money-quiz-quizzes-edit&quiz_id=' . $quiz_id ); ?>" class="button">
                            <?php _e( 'Edit Quiz', 'money-quiz' ); ?>
                        </a>
                    </p>
                </form>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>
    
    <!-- Import/Export -->
    <div class="mq-card">
        <h2><?php _e( 'Import/Export Questions', 'money-quiz' ); ?></h2>
        
        <div class="mq-import-export">
            <div class="export-section">
                <h3><?php _e( 'Export Questions', 'money-quiz' ); ?></h3>
                <p><?php _e( 'Export questions to CSV for backup or sharing.', 'money-quiz' ); ?></p>
                <a href="<?php echo wp_nonce_url( admin_url( 'admin.php?page=money-quiz-quizzes-questions&action=export' ), 'export-questions' ); ?>" class="button">
                    <?php _e( 'Export All Questions', 'money-quiz' ); ?>
                </a>
            </div>
            
            <div class="import-section">
                <h3><?php _e( 'Import Questions', 'money-quiz' ); ?></h3>
                <p><?php _e( 'Import questions from a CSV file.', 'money-quiz' ); ?></p>
                <form method="post" enctype="multipart/form-data">
                    <?php wp_nonce_field( 'import-questions' ); ?>
                    <input type="hidden" name="action" value="import_questions" />
                    <input type="file" name="import_file" accept=".csv" required />
                    <input type="submit" class="button" value="<?php _e( 'Import Questions', 'money-quiz' ); ?>" />
                </form>
            </div>
        </div>
    </div>
    
</div>

<script>
jQuery(document).ready(function($) {
    // Make questions sortable
    if ($.fn.sortable) {
        $('.mq-questions-list').sortable({
            handle: '.mq-drag-handle',
            items: '.mq-question-item',
            update: function(event, ui) {
                // Update order values
                $(this).find('.mq-question-item').each(function(index) {
                    $(this).find('.question-order').val(index + 1);
                    $(this).find('.mq-question-number').text(index + 1);
                });
            }
        });
    }
    
    // Delete question
    $('.delete-question').on('click', function(e) {
        e.preventDefault();
        if (confirm('<?php _e( 'Are you sure you want to delete this question?', 'money-quiz' ); ?>')) {
            var questionId = $(this).data('question-id');
            $('<form method="post">' +
              '<?php wp_nonce_field( 'manage_questions' ); ?>' +
              '<input type="hidden" name="action" value="delete_question" />' +
              '<input type="hidden" name="question_id" value="' + questionId + '" />' +
              '</form>').appendTo('body').submit();
        }
    });
    
    // Edit question (would open modal in full implementation)
    $('.edit-question').on('click', function(e) {
        e.preventDefault();
        alert('Edit functionality would open a modal here');
    });
    
    // Duplicate question
    $('.duplicate-question').on('click', function(e) {
        e.preventDefault();
        alert('Duplicate functionality would be implemented here');
    });
});
</script>

<style>
.mq-questions-filter {
    margin-bottom: 20px;
}

.quiz-filter-form {
    display: flex;
    align-items: center;
    gap: 10px;
}

.question-count {
    font-size: 14px;
    color: #666;
    font-weight: normal;
    margin-left: 10px;
}

.mq-questions-list {
    margin: 20px 0;
}

.mq-question-item {
    background: #f9f9f9;
    border: 1px solid #ddd;
    border-radius: 4px;
    margin-bottom: 10px;
    padding: 15px;
    display: flex;
    align-items: center;
    gap: 15px;
}

.mq-question-item:hover {
    background: #f5f5f5;
}

.mq-drag-handle {
    cursor: move;
    color: #999;
    font-size: 20px;
}

.mq-question-number {
    background: #0073aa;
    color: white;
    width: 30px;
    height: 30px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: bold;
}

.mq-question-content {
    flex: 1;
}

.mq-question-text {
    font-size: 14px;
    margin-bottom: 5px;
}

.mq-question-meta {
    font-size: 12px;
    color: #666;
}

.question-type {
    background: #e0e0e0;
    padding: 2px 8px;
    border-radius: 3px;
    margin-right: 5px;
}

.required-badge {
    background: #ff6900;
    color: white;
    padding: 2px 8px;
    border-radius: 3px;
}

.mq-question-actions {
    display: flex;
    gap: 10px;
}

.mq-question-actions a {
    text-decoration: none;
    color: #0073aa;
}

.mq-question-actions a:hover {
    text-decoration: underline;
}

.mq-import-export {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 30px;
    margin-top: 20px;
}

.export-section, .import-section {
    padding: 20px;
    background: #f9f9f9;
    border-radius: 4px;
}
</style>
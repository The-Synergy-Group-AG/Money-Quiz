<?php
/**
 * Quiz Controller
 *
 * @package MoneyQuiz
 * @since 4.0.0
 */

namespace MoneyQuiz\Admin\Controllers;

use MoneyQuiz\Services\QuizService;
use MoneyQuiz\Database\Repositories\QuizRepository;

/**
 * Handles quiz management in admin
 */
class QuizController {
    
    /**
     * @var QuizService
     */
    private QuizService $quiz_service;
    
    /**
     * @var QuizRepository
     */
    private QuizRepository $quiz_repository;
    
    /**
     * Constructor
     */
    public function __construct() {
        $container = \MoneyQuiz\Core\Plugin::instance()->get_container();
        $this->quiz_service = $container->get( 'service.quiz' );
        $this->quiz_repository = $container->get( 'repository.quiz' );
    }
    
    /**
     * Display quiz listing
     * 
     * @return void
     */
    public function index(): void {
        // Handle bulk actions
        if ( isset( $_POST['action'] ) && $_POST['action'] !== '-1' ) {
            $this->handle_bulk_action();
        }
        
        // Get quizzes
        $quizzes = $this->get_quizzes();
        
        // Display list table
        $this->render_list_view( $quizzes );
    }
    
    /**
     * Create new quiz
     * 
     * @return void
     */
    public function create(): void {
        // Handle form submission
        if ( $_SERVER['REQUEST_METHOD'] === 'POST' ) {
            $this->handle_create();
            return;
        }
        
        // Display create form
        $this->render_form();
    }
    
    /**
     * Edit quiz
     * 
     * @param int $id Quiz ID
     * @return void
     */
    public function edit( int $id ): void {
        // Get quiz
        $quiz = $this->quiz_service->get_quiz( $id );
        
        if ( ! $quiz ) {
            wp_die( __( 'Quiz not found.', 'money-quiz' ) );
        }
        
        // Handle form submission
        if ( $_SERVER['REQUEST_METHOD'] === 'POST' ) {
            $this->handle_update( $id );
            return;
        }
        
        // Display edit form
        $this->render_form( $quiz );
    }
    
    /**
     * Delete quiz
     * 
     * @param int $id Quiz ID
     * @return void
     */
    public function delete( int $id ): void {
        // Verify nonce
        if ( ! wp_verify_nonce( $_GET['_wpnonce'] ?? '', 'delete_quiz_' . $id ) ) {
            wp_die( __( 'Security check failed.', 'money-quiz' ) );
        }
        
        // Check capability
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( __( 'You do not have permission to delete quizzes.', 'money-quiz' ) );
        }
        
        // Delete quiz
        $result = $this->quiz_repository->delete( $id );
        
        if ( $result ) {
            wp_redirect( add_query_arg( [
                'page' => 'money-quiz-quizzes',
                'message' => 'deleted',
            ], admin_url( 'admin.php' ) ) );
            exit;
        } else {
            wp_die( __( 'Failed to delete quiz.', 'money-quiz' ) );
        }
    }
    
    /**
     * Get quizzes with pagination
     * 
     * @return array
     */
    private function get_quizzes(): array {
        // Check if we're dealing with legacy structure
        global $wpdb;
        $legacy_table = $wpdb->prefix . 'mq_master';
        $legacy_exists = $wpdb->get_var( "SHOW TABLES LIKE '{$legacy_table}'" );
        
        if ( $legacy_exists ) {
            // Get legacy quiz (single quiz system)
            $legacy_quiz = $this->quiz_service->get_quiz( 1 );
            return $legacy_quiz ? [ $legacy_quiz ] : [];
        }
        
        // Get modern quizzes
        $args = [
            'orderby' => $_GET['orderby'] ?? 'created_at',
            'order' => $_GET['order'] ?? 'DESC',
            'limit' => 20,
            'offset' => ( ( $_GET['paged'] ?? 1 ) - 1 ) * 20,
        ];
        
        return $this->quiz_repository->get_active( $args );
    }
    
    /**
     * Handle bulk action
     * 
     * @return void
     */
    private function handle_bulk_action(): void {
        $action = $_POST['action'];
        $quiz_ids = $_POST['quiz'] ?? [];
        
        if ( empty( $quiz_ids ) ) {
            return;
        }
        
        // Verify nonce
        if ( ! wp_verify_nonce( $_POST['_wpnonce'] ?? '', 'bulk-quizzes' ) ) {
            wp_die( __( 'Security check failed.', 'money-quiz' ) );
        }
        
        switch ( $action ) {
            case 'delete':
                foreach ( $quiz_ids as $id ) {
                    $this->quiz_repository->delete( absint( $id ) );
                }
                $message = 'deleted_bulk';
                break;
                
            case 'activate':
                foreach ( $quiz_ids as $id ) {
                    $this->quiz_repository->update( absint( $id ), [ 'is_active' => 1 ] );
                }
                $message = 'activated';
                break;
                
            case 'deactivate':
                foreach ( $quiz_ids as $id ) {
                    $this->quiz_repository->update( absint( $id ), [ 'is_active' => 0 ] );
                }
                $message = 'deactivated';
                break;
                
            default:
                return;
        }
        
        wp_redirect( add_query_arg( [
            'page' => 'money-quiz-quizzes',
            'message' => $message,
        ], admin_url( 'admin.php' ) ) );
        exit;
    }
    
    /**
     * Handle create form submission
     * 
     * @return void
     */
    private function handle_create(): void {
        // Verify nonce
        if ( ! wp_verify_nonce( $_POST['_wpnonce'] ?? '', 'create_quiz' ) ) {
            wp_die( __( 'Security check failed.', 'money-quiz' ) );
        }
        
        // Validate data
        $data = $this->validate_quiz_data( $_POST );
        
        // Create quiz
        $quiz_id = $this->quiz_repository->create( $data );
        
        if ( $quiz_id ) {
            // Save questions
            $this->save_questions( $quiz_id, $_POST['questions'] ?? [] );
            
            wp_redirect( add_query_arg( [
                'page' => 'money-quiz-quizzes',
                'action' => 'edit',
                'id' => $quiz_id,
                'message' => 'created',
            ], admin_url( 'admin.php' ) ) );
            exit;
        } else {
            wp_die( __( 'Failed to create quiz.', 'money-quiz' ) );
        }
    }
    
    /**
     * Handle update form submission
     * 
     * @param int $id Quiz ID
     * @return void
     */
    private function handle_update( int $id ): void {
        // Verify nonce
        if ( ! wp_verify_nonce( $_POST['_wpnonce'] ?? '', 'edit_quiz_' . $id ) ) {
            wp_die( __( 'Security check failed.', 'money-quiz' ) );
        }
        
        // Validate data
        $data = $this->validate_quiz_data( $_POST );
        
        // Update quiz
        $result = $this->quiz_repository->update( $id, $data );
        
        if ( $result ) {
            // Update questions
            $this->save_questions( $id, $_POST['questions'] ?? [] );
            
            wp_redirect( add_query_arg( [
                'page' => 'money-quiz-quizzes',
                'action' => 'edit',
                'id' => $id,
                'message' => 'updated',
            ], admin_url( 'admin.php' ) ) );
            exit;
        } else {
            wp_die( __( 'Failed to update quiz.', 'money-quiz' ) );
        }
    }
    
    /**
     * Validate quiz data
     * 
     * @param array $data Raw data
     * @return array Validated data
     */
    private function validate_quiz_data( array $data ): array {
        return [
            'title' => sanitize_text_field( $data['title'] ?? '' ),
            'description' => wp_kses_post( $data['description'] ?? '' ),
            'is_active' => ! empty( $data['is_active'] ) ? 1 : 0,
            'settings' => $this->validate_settings( $data['settings'] ?? [] ),
        ];
    }
    
    /**
     * Validate quiz settings
     * 
     * @param array $settings Raw settings
     * @return string JSON encoded settings
     */
    private function validate_settings( array $settings ): string {
        $validated = [
            'randomize_questions' => ! empty( $settings['randomize_questions'] ),
            'randomize_answers' => ! empty( $settings['randomize_answers'] ),
            'show_progress' => ! empty( $settings['show_progress'] ),
            'require_email' => ! empty( $settings['require_email'] ),
            'redirect_url' => esc_url_raw( $settings['redirect_url'] ?? '' ),
        ];
        
        return json_encode( $validated );
    }
    
    /**
     * Save quiz questions
     * 
     * @param int   $quiz_id   Quiz ID
     * @param array $questions Questions data
     * @return void
     */
    private function save_questions( int $quiz_id, array $questions ): void {
        global $wpdb;
        $table = $wpdb->prefix . 'money_quiz_questions';
        
        // Delete existing questions
        $wpdb->delete( $table, [ 'quiz_id' => $quiz_id ] );
        
        // Insert new questions
        foreach ( $questions as $order => $question ) {
            if ( empty( $question['text'] ) ) {
                continue;
            }
            
            $wpdb->insert( $table, [
                'quiz_id' => $quiz_id,
                'question_text' => sanitize_text_field( $question['text'] ),
                'question_type' => 'multiple_choice',
                'order_num' => $order,
                'options' => json_encode( $this->validate_options( $question['options'] ?? [] ) ),
            ] );
        }
    }
    
    /**
     * Validate question options
     * 
     * @param array $options Raw options
     * @return array
     */
    private function validate_options( array $options ): array {
        $validated = [];
        
        foreach ( $options as $option ) {
            if ( ! empty( $option['label'] ) ) {
                $validated[] = [
                    'value' => sanitize_text_field( $option['value'] ?? '' ),
                    'label' => sanitize_text_field( $option['label'] ),
                    'archetype' => sanitize_text_field( $option['archetype'] ?? '' ),
                ];
            }
        }
        
        return $validated;
    }
    
    /**
     * Render list view
     * 
     * @param array $quizzes Quizzes to display
     * @return void
     */
    private function render_list_view( array $quizzes ): void {
        // Handle messages
        $this->display_admin_notice();
        ?>
        <div class="wrap">
            <h1 class="wp-heading-inline"><?php _e( 'Quizzes', 'money-quiz' ); ?></h1>
            <a href="<?php echo admin_url( 'admin.php?page=money-quiz-quizzes&action=new' ); ?>" 
               class="page-title-action">
                <?php _e( 'Add New', 'money-quiz' ); ?>
            </a>
            
            <?php if ( empty( $quizzes ) ) : ?>
                <p><?php _e( 'No quizzes found.', 'money-quiz' ); ?></p>
            <?php else : ?>
                <form method="post">
                    <?php wp_nonce_field( 'bulk-quizzes' ); ?>
                    
                    <div class="tablenav top">
                        <div class="alignleft actions bulkactions">
                            <select name="action">
                                <option value="-1"><?php _e( 'Bulk Actions', 'money-quiz' ); ?></option>
                                <option value="delete"><?php _e( 'Delete', 'money-quiz' ); ?></option>
                                <option value="activate"><?php _e( 'Activate', 'money-quiz' ); ?></option>
                                <option value="deactivate"><?php _e( 'Deactivate', 'money-quiz' ); ?></option>
                            </select>
                            <input type="submit" class="button action" value="<?php esc_attr_e( 'Apply', 'money-quiz' ); ?>">
                        </div>
                    </div>
                    
                    <table class="wp-list-table widefat fixed striped">
                        <thead>
                            <tr>
                                <td class="manage-column column-cb check-column">
                                    <input type="checkbox" />
                                </td>
                                <th><?php _e( 'Title', 'money-quiz' ); ?></th>
                                <th><?php _e( 'Questions', 'money-quiz' ); ?></th>
                                <th><?php _e( 'Status', 'money-quiz' ); ?></th>
                                <th><?php _e( 'Created', 'money-quiz' ); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ( $quizzes as $quiz ) : ?>
                                <tr>
                                    <th scope="row" class="check-column">
                                        <input type="checkbox" name="quiz[]" value="<?php echo esc_attr( $quiz->id ); ?>" />
                                    </th>
                                    <td>
                                        <strong>
                                            <a href="<?php echo admin_url( 'admin.php?page=money-quiz-quizzes&action=edit&id=' . $quiz->id ); ?>">
                                                <?php echo esc_html( $quiz->title ); ?>
                                            </a>
                                        </strong>
                                        <div class="row-actions">
                                            <span class="edit">
                                                <a href="<?php echo admin_url( 'admin.php?page=money-quiz-quizzes&action=edit&id=' . $quiz->id ); ?>">
                                                    <?php _e( 'Edit', 'money-quiz' ); ?>
                                                </a> |
                                            </span>
                                            <span class="trash">
                                                <a href="<?php echo wp_nonce_url( admin_url( 'admin.php?page=money-quiz-quizzes&action=delete&id=' . $quiz->id ), 'delete_quiz_' . $quiz->id ); ?>"
                                                   onclick="return confirm('<?php esc_attr_e( 'Are you sure you want to delete this quiz?', 'money-quiz' ); ?>');">
                                                    <?php _e( 'Delete', 'money-quiz' ); ?>
                                                </a>
                                            </span>
                                        </div>
                                    </td>
                                    <td><?php echo count( $quiz->questions ?? [] ); ?></td>
                                    <td>
                                        <?php if ( ! empty( $quiz->is_active ) ) : ?>
                                            <span class="dashicons dashicons-yes" style="color: green;"></span>
                                            <?php _e( 'Active', 'money-quiz' ); ?>
                                        <?php else : ?>
                                            <span class="dashicons dashicons-no" style="color: red;"></span>
                                            <?php _e( 'Inactive', 'money-quiz' ); ?>
                                        <?php endif; ?>
                                    </td>
                                    <td><?php echo esc_html( $quiz->created_at ?? '' ); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </form>
            <?php endif; ?>
        </div>
        <?php
    }
    
    /**
     * Render quiz form
     * 
     * @param object|null $quiz Quiz object for editing
     * @return void
     */
    private function render_form( $quiz = null ): void {
        $is_edit = ! is_null( $quiz );
        $title = $is_edit ? __( 'Edit Quiz', 'money-quiz' ) : __( 'Add New Quiz', 'money-quiz' );
        ?>
        <div class="wrap">
            <h1><?php echo esc_html( $title ); ?></h1>
            
            <form method="post" id="quiz-form">
                <?php 
                if ( $is_edit ) {
                    wp_nonce_field( 'edit_quiz_' . $quiz->id );
                } else {
                    wp_nonce_field( 'create_quiz' );
                }
                ?>
                
                <div id="poststuff">
                    <div id="post-body" class="metabox-holder columns-2">
                        <div id="post-body-content">
                            <div class="postbox">
                                <h2 class="hndle"><?php _e( 'Quiz Details', 'money-quiz' ); ?></h2>
                                <div class="inside">
                                    <table class="form-table">
                                        <tr>
                                            <th><label for="title"><?php _e( 'Title', 'money-quiz' ); ?></label></th>
                                            <td>
                                                <input type="text" 
                                                       id="title" 
                                                       name="title" 
                                                       value="<?php echo esc_attr( $quiz->title ?? '' ); ?>" 
                                                       class="large-text" 
                                                       required />
                                            </td>
                                        </tr>
                                        <tr>
                                            <th><label for="description"><?php _e( 'Description', 'money-quiz' ); ?></label></th>
                                            <td>
                                                <?php 
                                                wp_editor( 
                                                    $quiz->description ?? '', 
                                                    'description', 
                                                    [ 'textarea_rows' => 5 ] 
                                                ); 
                                                ?>
                                            </td>
                                        </tr>
                                    </table>
                                </div>
                            </div>
                            
                            <div class="postbox">
                                <h2 class="hndle"><?php _e( 'Questions', 'money-quiz' ); ?></h2>
                                <div class="inside">
                                    <div id="questions-container">
                                        <?php 
                                        if ( $is_edit && ! empty( $quiz->questions ) ) {
                                            foreach ( $quiz->questions as $index => $question ) {
                                                $this->render_question_fields( $index, $question );
                                            }
                                        } else {
                                            $this->render_question_fields( 0 );
                                        }
                                        ?>
                                    </div>
                                    <p>
                                        <button type="button" class="button" id="add-question">
                                            <?php _e( 'Add Question', 'money-quiz' ); ?>
                                        </button>
                                    </p>
                                </div>
                            </div>
                        </div>
                        
                        <div id="postbox-container-1" class="postbox-container">
                            <div class="postbox">
                                <h2 class="hndle"><?php _e( 'Publish', 'money-quiz' ); ?></h2>
                                <div class="inside">
                                    <div class="submitbox">
                                        <div id="minor-publishing">
                                            <label>
                                                <input type="checkbox" 
                                                       name="is_active" 
                                                       value="1" 
                                                       <?php checked( ! $is_edit || ! empty( $quiz->is_active ) ); ?> />
                                                <?php _e( 'Active', 'money-quiz' ); ?>
                                            </label>
                                        </div>
                                        <div id="major-publishing-actions">
                                            <input type="submit" 
                                                   class="button button-primary button-large" 
                                                   value="<?php echo $is_edit ? esc_attr__( 'Update', 'money-quiz' ) : esc_attr__( 'Publish', 'money-quiz' ); ?>" />
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="postbox">
                                <h2 class="hndle"><?php _e( 'Settings', 'money-quiz' ); ?></h2>
                                <div class="inside">
                                    <?php $settings = $is_edit && ! empty( $quiz->settings ) ? json_decode( $quiz->settings, true ) : []; ?>
                                    
                                    <p>
                                        <label>
                                            <input type="checkbox" 
                                                   name="settings[randomize_questions]" 
                                                   value="1" 
                                                   <?php checked( ! empty( $settings['randomize_questions'] ) ); ?> />
                                            <?php _e( 'Randomize Questions', 'money-quiz' ); ?>
                                        </label>
                                    </p>
                                    <p>
                                        <label>
                                            <input type="checkbox" 
                                                   name="settings[show_progress]" 
                                                   value="1" 
                                                   <?php checked( ! isset( $settings['show_progress'] ) || $settings['show_progress'] ); ?> />
                                            <?php _e( 'Show Progress Bar', 'money-quiz' ); ?>
                                        </label>
                                    </p>
                                    <p>
                                        <label>
                                            <input type="checkbox" 
                                                   name="settings[require_email]" 
                                                   value="1" 
                                                   <?php checked( ! empty( $settings['require_email'] ) ); ?> />
                                            <?php _e( 'Require Email', 'money-quiz' ); ?>
                                        </label>
                                    </p>
                                    <p>
                                        <label for="redirect_url"><?php _e( 'Redirect URL', 'money-quiz' ); ?></label>
                                        <input type="url" 
                                               id="redirect_url"
                                               name="settings[redirect_url]" 
                                               value="<?php echo esc_url( $settings['redirect_url'] ?? '' ); ?>" 
                                               class="widefat" />
                                    </p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </form>
        </div>
        
        <script>
        jQuery(document).ready(function($) {
            var questionIndex = <?php echo $is_edit ? count( $quiz->questions ?? [] ) : 1; ?>;
            
            $('#add-question').on('click', function() {
                var template = $('#question-template').html();
                template = template.replace(/\{\{index\}\}/g, questionIndex);
                $('#questions-container').append(template);
                questionIndex++;
            });
            
            $(document).on('click', '.remove-question', function() {
                $(this).closest('.question-item').remove();
            });
            
            $(document).on('click', '.add-option', function() {
                var questionItem = $(this).closest('.question-item');
                var optionIndex = questionItem.find('.option-item').length;
                var questionIdx = questionItem.data('index');
                
                var optionHtml = '<div class="option-item">' +
                    '<input type="text" name="questions[' + questionIdx + '][options][' + optionIndex + '][label]" placeholder="<?php esc_attr_e( 'Option text', 'money-quiz' ); ?>" />' +
                    '<input type="text" name="questions[' + questionIdx + '][options][' + optionIndex + '][value]" placeholder="<?php esc_attr_e( 'Value', 'money-quiz' ); ?>" />' +
                    '<button type="button" class="button remove-option"><?php _e( 'Remove', 'money-quiz' ); ?></button>' +
                    '</div>';
                
                questionItem.find('.options-container').append(optionHtml);
            });
            
            $(document).on('click', '.remove-option', function() {
                $(this).closest('.option-item').remove();
            });
        });
        </script>
        
        <script type="text/template" id="question-template">
            <?php $this->render_question_fields( '{{index}}' ); ?>
        </script>
        <?php
    }
    
    /**
     * Render question fields
     * 
     * @param int|string $index    Question index
     * @param object     $question Question object
     * @return void
     */
    private function render_question_fields( $index, $question = null ): void {
        ?>
        <div class="question-item" data-index="<?php echo esc_attr( $index ); ?>">
            <h4><?php printf( __( 'Question %s', 'money-quiz' ), $index + 1 ); ?></h4>
            <p>
                <input type="text" 
                       name="questions[<?php echo esc_attr( $index ); ?>][text]" 
                       value="<?php echo esc_attr( $question->text ?? '' ); ?>" 
                       placeholder="<?php esc_attr_e( 'Question text', 'money-quiz' ); ?>"
                       class="large-text" />
            </p>
            <div class="options-container">
                <h5><?php _e( 'Options', 'money-quiz' ); ?></h5>
                <?php 
                if ( $question && ! empty( $question->options ) ) {
                    foreach ( $question->options as $opt_index => $option ) {
                        ?>
                        <div class="option-item">
                            <input type="text" 
                                   name="questions[<?php echo esc_attr( $index ); ?>][options][<?php echo esc_attr( $opt_index ); ?>][label]" 
                                   value="<?php echo esc_attr( $option->label ?? '' ); ?>" 
                                   placeholder="<?php esc_attr_e( 'Option text', 'money-quiz' ); ?>" />
                            <input type="text" 
                                   name="questions[<?php echo esc_attr( $index ); ?>][options][<?php echo esc_attr( $opt_index ); ?>][value]" 
                                   value="<?php echo esc_attr( $option->value ?? '' ); ?>" 
                                   placeholder="<?php esc_attr_e( 'Value', 'money-quiz' ); ?>" />
                            <button type="button" class="button remove-option"><?php _e( 'Remove', 'money-quiz' ); ?></button>
                        </div>
                        <?php
                    }
                } else {
                    // Default empty options
                    for ( $i = 0; $i < 2; $i++ ) {
                        ?>
                        <div class="option-item">
                            <input type="text" 
                                   name="questions[<?php echo esc_attr( $index ); ?>][options][<?php echo $i; ?>][label]" 
                                   placeholder="<?php esc_attr_e( 'Option text', 'money-quiz' ); ?>" />
                            <input type="text" 
                                   name="questions[<?php echo esc_attr( $index ); ?>][options][<?php echo $i; ?>][value]" 
                                   placeholder="<?php esc_attr_e( 'Value', 'money-quiz' ); ?>" />
                            <button type="button" class="button remove-option"><?php _e( 'Remove', 'money-quiz' ); ?></button>
                        </div>
                        <?php
                    }
                }
                ?>
            </div>
            <p>
                <button type="button" class="button add-option"><?php _e( 'Add Option', 'money-quiz' ); ?></button>
                <button type="button" class="button remove-question"><?php _e( 'Remove Question', 'money-quiz' ); ?></button>
            </p>
            <hr />
        </div>
        <?php
    }
    
    /**
     * Display admin notice
     * 
     * @return void
     */
    private function display_admin_notice(): void {
        $message = $_GET['message'] ?? '';
        
        $messages = [
            'created' => __( 'Quiz created successfully.', 'money-quiz' ),
            'updated' => __( 'Quiz updated successfully.', 'money-quiz' ),
            'deleted' => __( 'Quiz deleted successfully.', 'money-quiz' ),
            'deleted_bulk' => __( 'Quizzes deleted successfully.', 'money-quiz' ),
            'activated' => __( 'Quizzes activated successfully.', 'money-quiz' ),
            'deactivated' => __( 'Quizzes deactivated successfully.', 'money-quiz' ),
        ];
        
        if ( isset( $messages[ $message ] ) ) {
            printf(
                '<div class="notice notice-success is-dismissible"><p>%s</p></div>',
                esc_html( $messages[ $message ] )
            );
        }
    }
}
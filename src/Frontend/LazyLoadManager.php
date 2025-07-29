<?php
/**
 * Lazy Load Manager
 *
 * @package MoneyQuiz
 * @since 4.0.0
 */

namespace MoneyQuiz\Frontend;

/**
 * Handles lazy loading of quiz questions for better performance
 */
class LazyLoadManager {
    
    /**
     * @var int Questions per page
     */
    private int $questions_per_page = 5;
    
    /**
     * @var bool Enable lazy loading
     */
    private bool $enabled = true;
    
    /**
     * Initialize lazy loading
     * 
     * @return void
     */
    public function init(): void {
        if ( ! $this->enabled ) {
            return;
        }
        
        // Add AJAX handlers
        add_action( 'wp_ajax_money_quiz_load_questions', [ $this, 'ajax_load_questions' ] );
        add_action( 'wp_ajax_nopriv_money_quiz_load_questions', [ $this, 'ajax_load_questions' ] );
        
        // Modify quiz rendering
        add_filter( 'money_quiz_render_questions', [ $this, 'render_lazy_questions' ], 10, 2 );
        
        // Add lazy loading scripts
        add_action( 'money_quiz_after_scripts', [ $this, 'add_lazy_scripts' ] );
    }
    
    /**
     * AJAX handler for loading questions
     * 
     * @return void
     */
    public function ajax_load_questions(): void {
        // Verify nonce
        if ( ! wp_verify_nonce( $_POST['nonce'] ?? '', 'money_quiz_ajax' ) ) {
            wp_send_json_error( [ 'message' => __( 'Security check failed.', 'money-quiz' ) ] );
        }
        
        $quiz_id = absint( $_POST['quiz_id'] ?? 0 );
        $page = absint( $_POST['page'] ?? 1 );
        
        if ( ! $quiz_id ) {
            wp_send_json_error( [ 'message' => __( 'Invalid quiz ID.', 'money-quiz' ) ] );
        }
        
        // Get questions for this page
        $questions = $this->get_questions_page( $quiz_id, $page );
        
        if ( empty( $questions ) ) {
            wp_send_json_error( [ 'message' => __( 'No more questions.', 'money-quiz' ) ] );
        }
        
        // Render questions HTML
        ob_start();
        foreach ( $questions as $question ) {
            $this->render_question( $question );
        }
        $html = ob_get_clean();
        
        wp_send_json_success( [
            'html' => $html,
            'has_more' => $this->has_more_questions( $quiz_id, $page ),
            'total' => $this->get_total_questions( $quiz_id ),
        ] );
    }
    
    /**
     * Get questions for a specific page
     * 
     * @param int $quiz_id Quiz ID
     * @param int $page    Page number
     * @return array
     */
    private function get_questions_page( int $quiz_id, int $page ): array {
        global $wpdb;
        
        $offset = ( $page - 1 ) * $this->questions_per_page;
        
        $questions = $wpdb->get_results( $wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}money_quiz_questions 
             WHERE quiz_id = %d AND is_active = 1 
             ORDER BY sort_order ASC, id ASC 
             LIMIT %d OFFSET %d",
            $quiz_id,
            $this->questions_per_page,
            $offset
        ) );
        
        // Decode JSON fields
        foreach ( $questions as &$question ) {
            $question->options = json_decode( $question->options, true ) ?: [];
            $question->archetype_weights = json_decode( $question->archetype_weights, true ) ?: [];
        }
        
        return $questions;
    }
    
    /**
     * Check if there are more questions
     * 
     * @param int $quiz_id Quiz ID
     * @param int $page    Current page
     * @return bool
     */
    private function has_more_questions( int $quiz_id, int $page ): bool {
        $total = $this->get_total_questions( $quiz_id );
        $loaded = $page * $this->questions_per_page;
        
        return $loaded < $total;
    }
    
    /**
     * Get total number of questions
     * 
     * @param int $quiz_id Quiz ID
     * @return int
     */
    private function get_total_questions( int $quiz_id ): int {
        global $wpdb;
        
        return (int) $wpdb->get_var( $wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->prefix}money_quiz_questions 
             WHERE quiz_id = %d AND is_active = 1",
            $quiz_id
        ) );
    }
    
    /**
     * Render lazy-loaded questions
     * 
     * @param string $html    Original HTML
     * @param object $quiz    Quiz object
     * @return string
     */
    public function render_lazy_questions( string $html, object $quiz ): string {
        if ( ! $this->should_lazy_load( $quiz ) ) {
            return $html;
        }
        
        // Get first page of questions
        $questions = $this->get_questions_page( $quiz->id, 1 );
        
        ob_start();
        ?>
        <div class="money-quiz-questions" data-quiz-id="<?php echo esc_attr( $quiz->id ); ?>">
            <div class="money-quiz-questions-container">
                <?php foreach ( $questions as $question ) : ?>
                    <?php $this->render_question( $question ); ?>
                <?php endforeach; ?>
            </div>
            
            <?php if ( $this->has_more_questions( $quiz->id, 1 ) ) : ?>
                <div class="money-quiz-load-more" data-page="2">
                    <button type="button" class="button load-more-questions">
                        <?php _e( 'Load More Questions', 'money-quiz' ); ?>
                    </button>
                    <div class="loading-spinner" style="display: none;">
                        <span class="spinner is-active"></span>
                    </div>
                </div>
            <?php endif; ?>
        </div>
        <?php
        
        return ob_get_clean();
    }
    
    /**
     * Render a single question
     * 
     * @param object $question Question object
     * @return void
     */
    private function render_question( object $question ): void {
        static $question_num = 0;
        $question_num++;
        ?>
        <div class="money-quiz-question" data-question="<?php echo esc_attr( $question->id ); ?>">
            <h3 class="question-title">
                <?php echo esc_html( $question->question ); ?>
                <?php if ( $question->is_required ) : ?>
                    <span class="required">*</span>
                <?php endif; ?>
            </h3>
            
            <div class="question-options">
                <?php foreach ( $question->options as $index => $option ) : ?>
                    <label class="option-label">
                        <input type="radio" 
                               name="question_<?php echo esc_attr( $question->id ); ?>" 
                               value="<?php echo esc_attr( $option['value'] ); ?>"
                               class="option-input"
                               <?php echo $question->is_required ? 'required' : ''; ?> />
                        <span class="option-text"><?php echo esc_html( $option['label'] ); ?></span>
                    </label>
                <?php endforeach; ?>
            </div>
        </div>
        <?php
    }
    
    /**
     * Add lazy loading scripts
     * 
     * @return void
     */
    public function add_lazy_scripts(): void {
        if ( ! $this->enabled ) {
            return;
        }
        ?>
        <script>
        (function($) {
            'use strict';
            
            var LazyLoader = {
                init: function() {
                    this.bindEvents();
                    this.observeScroll();
                },
                
                bindEvents: function() {
                    var self = this;
                    
                    // Manual load more button
                    $(document).on('click', '.load-more-questions', function() {
                        var container = $(this).closest('.money-quiz-load-more');
                        self.loadQuestions(container);
                    });
                },
                
                observeScroll: function() {
                    if ('IntersectionObserver' in window) {
                        var self = this;
                        var observer = new IntersectionObserver(function(entries) {
                            entries.forEach(function(entry) {
                                if (entry.isIntersecting) {
                                    var container = $(entry.target);
                                    if (container.data('auto-load') !== false) {
                                        self.loadQuestions(container);
                                    }
                                }
                            });
                        }, {
                            rootMargin: '100px'
                        });
                        
                        $('.money-quiz-load-more').each(function() {
                            observer.observe(this);
                        });
                    }
                },
                
                loadQuestions: function(container) {
                    if (container.hasClass('loading')) return;
                    
                    container.addClass('loading');
                    container.find('.loading-spinner').show();
                    container.find('.load-more-questions').hide();
                    
                    var quizId = $('.money-quiz-questions').data('quiz-id');
                    var page = container.data('page');
                    
                    $.ajax({
                        url: window.money_quiz_ajax.ajax_url,
                        type: 'POST',
                        data: {
                            action: 'money_quiz_load_questions',
                            nonce: window.money_quiz_ajax.nonce,
                            quiz_id: quizId,
                            page: page
                        },
                        success: function(response) {
                            if (response.success) {
                                // Add new questions
                                $('.money-quiz-questions-container').append(response.data.html);
                                
                                // Update page number
                                container.data('page', page + 1);
                                
                                // Update UI
                                container.removeClass('loading');
                                container.find('.loading-spinner').hide();
                                
                                if (response.data.has_more) {
                                    container.find('.load-more-questions').show();
                                } else {
                                    container.remove();
                                }
                                
                                // Trigger event
                                $(document).trigger('money-quiz-questions-loaded', [response.data]);
                            }
                        },
                        error: function() {
                            container.removeClass('loading');
                            container.find('.loading-spinner').hide();
                            container.find('.load-more-questions').show();
                        }
                    });
                }
            };
            
            $(document).ready(function() {
                LazyLoader.init();
            });
            
        })(jQuery);
        </script>
        <?php
    }
    
    /**
     * Check if lazy loading should be used
     * 
     * @param object $quiz Quiz object
     * @return bool
     */
    private function should_lazy_load( object $quiz ): bool {
        // Don't lazy load if disabled
        if ( ! $this->enabled ) {
            return false;
        }
        
        // Don't lazy load for small quizzes
        $total_questions = count( $quiz->questions ?? [] );
        if ( $total_questions <= $this->questions_per_page ) {
            return false;
        }
        
        // Check quiz settings
        $settings = json_decode( $quiz->settings, true ) ?: [];
        if ( isset( $settings['disable_lazy_load'] ) && $settings['disable_lazy_load'] ) {
            return false;
        }
        
        return true;
    }
    
    /**
     * Set questions per page
     * 
     * @param int $count Questions per page
     * @return void
     */
    public function set_questions_per_page( int $count ): void {
        $this->questions_per_page = max( 1, $count );
    }
    
    /**
     * Enable or disable lazy loading
     * 
     * @param bool $enabled Enable lazy loading
     * @return void
     */
    public function set_enabled( bool $enabled ): void {
        $this->enabled = $enabled;
    }
}
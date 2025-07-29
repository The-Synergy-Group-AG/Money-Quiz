<?php
/**
 * Quiz Display Template
 *
 * @package MoneyQuiz
 * @since 4.0.0
 * 
 * Available variables:
 * - $quiz: Quiz object with questions
 * - $attributes: Shortcode attributes
 * - $csrf_token: CSRF token for form submission
 * - $ajax_url: AJAX URL for form submission
 * - $nonce: WordPress nonce
 */

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}
?>

<div class="<?php echo esc_attr( $attributes['class'] ); ?>" id="money-quiz-<?php echo esc_attr( $quiz->id ); ?>">
    
    <?php if ( 'true' === $attributes['title'] && ! empty( $quiz->title ) ) : ?>
        <h2 class="money-quiz-title"><?php echo esc_html( $quiz->title ); ?></h2>
    <?php endif; ?>
    
    <?php if ( 'true' === $attributes['description'] && ! empty( $quiz->description ) ) : ?>
        <div class="money-quiz-description">
            <?php echo wp_kses_post( $quiz->description ); ?>
        </div>
    <?php endif; ?>
    
    <?php if ( 'true' === $attributes['progress'] ) : ?>
        <div class="money-quiz-progress">
            <div class="progress-bar">
                <div class="progress-fill" style="width: 0%;"></div>
            </div>
            <div class="progress-text">
                <span class="current-question">0</span> / <span class="total-questions"><?php echo count( $quiz->questions ); ?></span>
            </div>
        </div>
    <?php endif; ?>
    
    <form class="money-quiz-form" method="post" action="<?php echo esc_url( $ajax_url ); ?>">
        <input type="hidden" name="action" value="money_quiz_submit" />
        <input type="hidden" name="quiz_id" value="<?php echo esc_attr( $quiz->id ); ?>" />
        <input type="hidden" name="nonce" value="<?php echo esc_attr( $nonce ); ?>" />
        <input type="hidden" name="csrf_token" value="<?php echo esc_attr( $csrf_token ); ?>" />
        
        <div class="money-quiz-questions">
            <?php 
            $question_number = 0;
            foreach ( $quiz->questions as $question ) : 
                $question_number++;
                $field_name = 'answers[' . $question->id . ']';
            ?>
                <div class="money-quiz-question" data-question="<?php echo esc_attr( $question_number ); ?>">
                    <h3 class="question-title">
                        <?php echo esc_html( $question_number . '. ' . $question->text ); ?>
                    </h3>
                    
                    <div class="question-options">
                        <?php foreach ( $question->options as $option ) : ?>
                            <label class="option-label">
                                <input type="radio" 
                                       name="<?php echo esc_attr( $field_name ); ?>" 
                                       value="<?php echo esc_attr( $option->value ); ?>"
                                       class="option-input"
                                       required />
                                <span class="option-text"><?php echo esc_html( $option->label ); ?></span>
                            </label>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
        
        <div class="money-quiz-navigation">
            <button type="button" class="quiz-button quiz-prev" style="display: none;">
                <?php _e( 'Previous', 'money-quiz' ); ?>
            </button>
            <button type="button" class="quiz-button quiz-next">
                <?php _e( 'Next', 'money-quiz' ); ?>
            </button>
            <button type="submit" class="quiz-button quiz-submit" style="display: none;">
                <?php _e( 'Get My Results', 'money-quiz' ); ?>
            </button>
        </div>
    </form>
    
    <div class="money-quiz-results" style="display: none;">
        <!-- Results will be displayed here after submission -->
    </div>
</div>
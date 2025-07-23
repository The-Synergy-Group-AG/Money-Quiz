<?php
declare(strict_types=1);

namespace MoneyQuiz\Features\Quiz\Display;

use MoneyQuiz\Security\OutputEscaper;

/**
 * Renders quiz templates
 */
class QuizRenderer
{
    private string $templatePath;

    public function __construct(
        private OutputEscaper $escaper,
        string $pluginDir
    ) {
        $this->templatePath = $pluginDir . '/templates/';
    }

    /**
     * Render a template with data
     */
    public function renderTemplate(string $template, array $data = []): string
    {
        $templateFile = $this->getTemplatePath($template);
        
        if (!file_exists($templateFile)) {
            return $this->renderDefaultTemplate($template, $data);
        }

        // Extract data for template
        extract($data, EXTR_SKIP);
        
        // Capture output
        ob_start();
        include $templateFile;
        return ob_get_clean();
    }

    /**
     * Get template file path
     */
    private function getTemplatePath(string $template): string
    {
        // Allow themes to override templates
        $themeTemplate = get_stylesheet_directory() . '/money-quiz/' . $template . '.php';
        if (file_exists($themeTemplate)) {
            return $themeTemplate;
        }

        // Check parent theme
        $parentTemplate = get_template_directory() . '/money-quiz/' . $template . '.php';
        if (file_exists($parentTemplate)) {
            return $parentTemplate;
        }

        // Use plugin template
        return $this->templatePath . $template . '.php';
    }

    /**
     * Render default template when custom template not found
     */
    private function renderDefaultTemplate(string $template, array $data): string
    {
        switch ($template) {
            case 'quiz-start':
                return $this->renderStartTemplate($data);
            case 'quiz-questions-all':
                return $this->renderAllQuestionsTemplate($data);
            case 'quiz-question-single':
                return $this->renderSingleQuestionTemplate($data);
            default:
                return '';
        }
    }

    /**
     * Default start page template
     */
    private function renderStartTemplate(array $data): string
    {
        $quiz = $data['quiz'] ?? [];
        
        return sprintf(
            '<div class="mq-quiz-start">
                <h2>%s</h2>
                <p>%s</p>
                <form method="post" class="mq-start-form">
                    %s
                    <button type="submit" name="start_quiz" value="1">Start Quiz</button>
                </form>
            </div>',
            $quiz['title'] ?? '',
            $quiz['description'] ?? '',
            wp_nonce_field('mq_start_quiz', 'mq_nonce', true, false)
        );
    }

    /**
     * Default all questions template
     */
    private function renderAllQuestionsTemplate(array $data): string
    {
        $html = '<form method="post" class="mq-quiz-form">';
        $html .= $this->renderProgress($data['progress'] ?? []);
        $html .= $this->renderTimer($data['timer'] ?? null);
        
        foreach ($data['questions'] ?? [] as $question) {
            $html .= $this->renderQuestion($question);
        }
        
        $html .= wp_nonce_field('mq_submit_quiz', 'mq_nonce', true, false);
        $html .= '<button type="submit">Submit Quiz</button>';
        $html .= '</form>';
        
        return $html;
    }

    /**
     * Default single question template
     */
    private function renderSingleQuestionTemplate(array $data): string
    {
        $html = '<form method="post" class="mq-quiz-form">';
        $html .= $this->renderProgress($data['progress'] ?? []);
        $html .= $this->renderTimer($data['timer'] ?? null);
        $html .= $this->renderQuestion($data['question'] ?? []);
        $html .= $this->renderNavigation($data);
        $html .= wp_nonce_field('mq_submit_answer', 'mq_nonce', true, false);
        $html .= '</form>';
        
        return $html;
    }

    /**
     * Render progress indicator
     */
    private function renderProgress(array $progress): string
    {
        if (empty($progress)) {
            return '';
        }
        
        $percentage = $progress['percentage'] ?? 0;
        
        return sprintf(
            '<div class="mq-progress">
                <div class="mq-progress-bar" style="width: %d%%"></div>
                <span class="mq-progress-text">%d of %d completed</span>
            </div>',
            $percentage,
            $progress['completed'] ?? 0,
            $progress['total'] ?? 0
        );
    }

    /**
     * Render timer
     */
    private function renderTimer(?array $timer): string
    {
        if (!$timer) {
            return '';
        }
        
        return sprintf(
            '<div class="mq-timer" data-remaining="%d">
                Time remaining: <span class="mq-timer-display">%s</span>
            </div>',
            $timer['remaining_seconds'] ?? 0,
            $timer['display'] ?? ''
        );
    }

    /**
     * Render question
     */
    private function renderQuestion(array $question): string
    {
        $html = sprintf(
            '<div class="mq-question" data-question-id="%d">
                <h3>%s</h3>',
            $question['id'] ?? 0,
            $question['text'] ?? ''
        );
        
        if (!empty($question['description'])) {
            $html .= sprintf('<p class="mq-question-desc">%s</p>', $question['description']);
        }
        
        $html .= $this->renderQuestionOptions($question);
        $html .= '</div>';
        
        return $html;
    }

    /**
     * Render question options based on type
     */
    private function renderQuestionOptions(array $question): string
    {
        switch ($question['type'] ?? '') {
            case 'multiple_choice':
                return $this->renderMultipleChoice($question);
            case 'true_false':
                return $this->renderTrueFalse($question);
            case 'ranking':
                return $this->renderRanking($question);
            default:
                return '';
        }
    }

    /**
     * Render navigation buttons
     */
    private function renderNavigation(array $data): string
    {
        $html = '<div class="mq-navigation">';
        
        if ($data['can_go_back'] ?? false) {
            $html .= '<button type="submit" name="previous" value="1">Previous</button>';
        }
        
        if ($data['is_last_page'] ?? false) {
            $html .= '<button type="submit" name="submit" value="1">Submit Quiz</button>';
        } else {
            $html .= '<button type="submit" name="next" value="1">Next</button>';
        }
        
        $html .= '</div>';
        return $html;
    }

    /**
     * Render multiple choice options
     */
    private function renderMultipleChoice(array $question): string
    {
        $html = '<div class="mq-options">';
        
        foreach ($question['options'] ?? [] as $option) {
            $html .= sprintf(
                '<label><input type="radio" name="answer[%d]" value="%s"> %s</label>',
                $question['id'],
                $option['value'],
                $option['text']
            );
        }
        
        $html .= '</div>';
        return $html;
    }

    /**
     * Render true/false options
     */
    private function renderTrueFalse(array $question): string
    {
        return sprintf(
            '<div class="mq-options">
                <label><input type="radio" name="answer[%d]" value="true"> True</label>
                <label><input type="radio" name="answer[%d]" value="false"> False</label>
            </div>',
            $question['id'],
            $question['id']
        );
    }

    /**
     * Render ranking options
     */
    private function renderRanking(array $question): string
    {
        $html = '<div class="mq-ranking" data-question-id="' . $question['id'] . '">';
        
        foreach ($question['options'] ?? [] as $index => $option) {
            $html .= sprintf(
                '<div class="mq-ranking-item" data-value="%s">
                    <span class="mq-rank">%d</span>
                    <span class="mq-text">%s</span>
                </div>',
                $option['value'],
                $index + 1,
                $option['text']
            );
        }
        
        $html .= '</div>';
        return $html;
    }
}
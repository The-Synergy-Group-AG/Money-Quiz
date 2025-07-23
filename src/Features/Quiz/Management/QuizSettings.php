<?php
declare(strict_types=1);

namespace MoneyQuiz\Features\Quiz\Management;

/**
 * Quiz configuration settings
 */
class QuizSettings
{
    private array $settings;

    public function __construct(array $settings = [])
    {
        $this->settings = $this->validateAndNormalize($settings);
    }

    /**
     * Get display options
     */
    public function getDisplayOptions(): array
    {
        return $this->settings['display_options'] ?? [
            'show_progress' => true,
            'show_timer' => false,
            'allow_back' => false,
            'show_question_numbers' => true
        ];
    }

    /**
     * Get question display mode
     */
    public function getQuestionDisplay(): string
    {
        return $this->settings['question_display'] ?? 'one_per_page';
    }

    /**
     * Get answer label configuration
     */
    public function getAnswerLabels(): array
    {
        return $this->settings['answer_labels'] ?? [
            'type' => 'letters', // letters, numbers, bullets
            'style' => 'uppercase'
        ];
    }

    /**
     * Get template layout
     */
    public function getTemplateLayout(): string
    {
        return $this->settings['template_layout'] ?? 'default';
    }

    /**
     * Get custom CSS
     */
    public function getCustomCss(): string
    {
        return $this->settings['custom_css'] ?? '';
    }

    /**
     * Get redirect URL after completion
     */
    public function getRedirectUrl(): string
    {
        return $this->settings['redirect_url'] ?? '';
    }

    /**
     * Get completion message
     */
    public function getCompletionMessage(): string
    {
        return $this->settings['completion_message'] ?? 'Thank you for completing the quiz!';
    }

    /**
     * Convert to array
     */
    public function toArray(): array
    {
        return $this->settings;
    }

    /**
     * Create from array
     */
    public static function fromArray(array $settings): self
    {
        return new self($settings);
    }

    /**
     * Validate and normalize settings
     */
    private function validateAndNormalize(array $settings): array
    {
        // Ensure all expected keys exist with defaults
        return array_merge([
            'display_options' => [],
            'question_display' => 'one_per_page',
            'answer_labels' => [],
            'template_layout' => 'default',
            'custom_css' => '',
            'redirect_url' => '',
            'completion_message' => ''
        ], $settings);
    }
}
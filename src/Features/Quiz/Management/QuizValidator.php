<?php
declare(strict_types=1);

namespace MoneyQuiz\Features\Quiz\Management;

use MoneyQuiz\Security\InputValidator;
use MoneyQuiz\Application\Exceptions\ServiceException;

/**
 * Validates quiz data for create and update operations
 */
class QuizValidator
{
    private array $createRules = [
        'title' => ['required' => true, 'type' => 'string', 'max_length' => 200],
        'description' => ['type' => 'string', 'max_length' => 1000],
        'quiz_type' => ['required' => true, 'type' => 'string', 'in' => ['personality', 'assessment', 'survey']],
        'settings' => ['type' => 'array'],
        'time_limit' => ['type' => 'integer', 'min' => 0],
        'passing_score' => ['type' => 'integer', 'min' => 0, 'max' => 100],
        'randomize_questions' => ['type' => 'boolean'],
        'show_results' => ['type' => 'boolean'],
        'require_registration' => ['type' => 'boolean'],
        'multi_step' => ['type' => 'boolean'],
        'archive_tagline' => ['type' => 'string', 'max_length' => 500]
    ];

    private array $updateRules = [
        'title' => ['type' => 'string', 'max_length' => 200],
        'description' => ['type' => 'string', 'max_length' => 1000],
        'quiz_type' => ['type' => 'string', 'in' => ['personality', 'assessment', 'survey']],
        'settings' => ['type' => 'array'],
        'time_limit' => ['type' => 'integer', 'min' => 0],
        'passing_score' => ['type' => 'integer', 'min' => 0, 'max' => 100],
        'randomize_questions' => ['type' => 'boolean'],
        'show_results' => ['type' => 'boolean'],
        'require_registration' => ['type' => 'boolean'],
        'multi_step' => ['type' => 'boolean'],
        'archive_tagline' => ['type' => 'string', 'max_length' => 500],
        'status' => ['type' => 'string', 'in' => ['draft', 'published', 'archived']]
    ];

    public function __construct(
        private InputValidator $validator
    ) {}

    /**
     * Validate quiz creation data
     */
    public function validateCreate(array $data): array
    {
        $validated = $this->validator->validateArray($data, $this->createRules);

        // Set defaults
        $validated['description'] = $validated['description'] ?? '';
        $validated['settings'] = $this->validateSettings($validated['settings'] ?? []);
        $validated['time_limit'] = $validated['time_limit'] ?? 0;
        $validated['passing_score'] = $validated['passing_score'] ?? 0;
        $validated['randomize_questions'] = $validated['randomize_questions'] ?? false;
        $validated['show_results'] = $validated['show_results'] ?? true;
        $validated['require_registration'] = $validated['require_registration'] ?? false;
        $validated['multi_step'] = $validated['multi_step'] ?? false;
        $validated['archive_tagline'] = $validated['archive_tagline'] ?? '';
        $validated['status'] = 'draft';

        return $validated;
    }

    /**
     * Validate quiz update data
     */
    public function validateUpdate(array $data): array
    {
        $validated = $this->validator->validateArray($data, $this->updateRules);

        if (isset($validated['settings'])) {
            $validated['settings'] = $this->validateSettings($validated['settings']);
        }

        return $validated;
    }

    /**
     * Validate quiz settings
     */
    private function validateSettings(array $settings): array
    {
        $settingsRules = [
            'display_options' => ['type' => 'array'],
            'question_display' => ['type' => 'string', 'in' => ['all', 'one_per_page']],
            'answer_labels' => ['type' => 'array'],
            'custom_css' => ['type' => 'string', 'max_length' => 5000],
            'template_layout' => ['type' => 'string', 'max_length' => 50],
            'redirect_url' => ['type' => 'url'],
            'completion_message' => ['type' => 'string', 'max_length' => 1000]
        ];

        try {
            return $this->validator->validateArray($settings, $settingsRules);
        } catch (\Exception $e) {
            // Return empty array if settings validation fails
            return [];
        }
    }
}
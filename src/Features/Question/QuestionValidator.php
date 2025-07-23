<?php
declare(strict_types=1);

namespace MoneyQuiz\Features\Question;

use MoneyQuiz\Security\InputValidator;
use MoneyQuiz\Application\Exceptions\ServiceException;

/**
 * Validates question data based on question type
 */
class QuestionValidator
{
    private array $baseRules = [
        'type' => ['required' => true, 'type' => 'string', 'in' => ['multiple_choice', 'true_false', 'ranking']],
        'text' => ['required' => true, 'type' => 'string', 'max_length' => 1000],
        'description' => ['type' => 'string', 'max_length' => 500],
        'points' => ['type' => 'integer', 'min' => 0, 'max' => 100],
        'required' => ['type' => 'boolean'],
        'options' => ['type' => 'array'],
        'correct_answer' => ['type' => 'string'],
        'feedback' => ['type' => 'string', 'max_length' => 500],
        'category' => ['type' => 'string', 'max_length' => 100],
        'tags' => ['type' => 'array']
    ];

    public function __construct(
        private InputValidator $validator
    ) {}

    /**
     * Validate question data
     */
    public function validate(array $data, bool $isCreate = true): array
    {
        // For updates, make all fields optional
        $rules = $isCreate ? $this->baseRules : $this->makeOptional($this->baseRules);
        
        $validated = $this->validator->validateArray($data, $rules);
        
        // Validate type-specific requirements
        if (isset($validated['type'])) {
            $this->validateTypeSpecific($validated);
        }
        
        // Set defaults for create
        if ($isCreate) {
            $validated['description'] = $validated['description'] ?? '';
            $validated['points'] = $validated['points'] ?? 1;
            $validated['required'] = $validated['required'] ?? true;
            $validated['feedback'] = $validated['feedback'] ?? '';
            $validated['category'] = $validated['category'] ?? '';
            $validated['tags'] = $validated['tags'] ?? [];
        }
        
        return $validated;
    }

    /**
     * Validate options array
     */
    public function validateOptions(array $options): array
    {
        $optionRules = [
            'text' => ['required' => true, 'type' => 'string', 'max_length' => 500],
            'value' => ['type' => 'string', 'max_length' => 100],
            'is_correct' => ['type' => 'boolean'],
            'points' => ['type' => 'integer', 'min' => 0],
            'order' => ['type' => 'integer', 'min' => 0]
        ];
        
        $validated = [];
        
        foreach ($options as $index => $option) {
            try {
                $validated[] = $this->validator->validateArray($option, $optionRules);
            } catch (\Exception $e) {
                throw new ServiceException("Invalid option at index {$index}: " . $e->getMessage());
            }
        }
        
        return $validated;
    }

    /**
     * Validate type-specific requirements
     */
    private function validateTypeSpecific(array &$validated): void
    {
        switch ($validated['type']) {
            case 'multiple_choice':
                $this->validateMultipleChoice($validated);
                break;
                
            case 'true_false':
                $this->validateTrueFalse($validated);
                break;
                
            case 'ranking':
                $this->validateRanking($validated);
                break;
        }
    }

    /**
     * Validate multiple choice question
     */
    private function validateMultipleChoice(array &$validated): void
    {
        if (!isset($validated['options']) || count($validated['options']) < 2) {
            throw new ServiceException('Multiple choice questions require at least 2 options');
        }
        
        $validated['options'] = $this->validateOptions($validated['options']);
        
        // Ensure at least one correct answer
        $hasCorrect = false;
        foreach ($validated['options'] as $option) {
            if ($option['is_correct'] ?? false) {
                $hasCorrect = true;
                break;
            }
        }
        
        if (!$hasCorrect) {
            throw new ServiceException('Multiple choice questions require at least one correct answer');
        }
    }

    /**
     * Validate true/false question
     */
    private function validateTrueFalse(array &$validated): void
    {
        if (!isset($validated['correct_answer'])) {
            throw new ServiceException('True/false questions require a correct answer');
        }
        
        if (!in_array($validated['correct_answer'], ['true', 'false'])) {
            throw new ServiceException('True/false correct answer must be "true" or "false"');
        }
    }

    /**
     * Validate ranking question
     */
    private function validateRanking(array &$validated): void
    {
        if (!isset($validated['options']) || count($validated['options']) < 2) {
            throw new ServiceException('Ranking questions require at least 2 options');
        }
        
        $validated['options'] = $this->validateOptions($validated['options']);
    }

    /**
     * Make rules optional for updates
     */
    private function makeOptional(array $rules): array
    {
        $optional = [];
        foreach ($rules as $field => $rule) {
            $optional[$field] = array_merge($rule, ['required' => false]);
        }
        return $optional;
    }
}
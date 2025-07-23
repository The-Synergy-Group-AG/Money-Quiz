<?php
declare(strict_types=1);

namespace MoneyQuiz\Features\Question\Types;

use MoneyQuiz\Application\Exceptions\ServiceException;

/**
 * Factory for creating question type handlers
 */
class QuestionTypeFactory
{
    private array $types = [];

    public function __construct()
    {
        $this->registerDefaultTypes();
    }

    /**
     * Create question type handler
     */
    public function create(string $type): QuestionTypeInterface
    {
        if (!isset($this->types[$type])) {
            throw new ServiceException("Unknown question type: {$type}");
        }

        return new $this->types[$type]();
    }

    /**
     * Register a custom question type
     */
    public function registerType(string $type, string $className): void
    {
        if (!class_exists($className)) {
            throw new ServiceException("Question type class not found: {$className}");
        }

        if (!in_array(QuestionTypeInterface::class, class_implements($className))) {
            throw new ServiceException("Question type must implement QuestionTypeInterface");
        }

        $this->types[$type] = $className;
    }

    /**
     * Get available question types
     */
    public function getAvailableTypes(): array
    {
        return array_keys($this->types);
    }

    /**
     * Register default question types
     */
    private function registerDefaultTypes(): void
    {
        $this->types = [
            'multiple_choice' => MultipleChoiceType::class,
            'true_false' => TrueFalseType::class,
            'ranking' => RankingType::class
        ];
    }
}
<?php
declare(strict_types=1);

namespace MoneyQuiz\Features\Question\Types;

use MoneyQuiz\Domain\Entities\Question;

/**
 * True/False question type handler
 */
class TrueFalseType implements QuestionTypeInterface
{
    /**
     * Validate answer for true/false question
     */
    public function validateAnswer(Question $question, $answer): bool
    {
        return in_array($answer, ['true', 'false'], true);
    }

    /**
     * Calculate score for the answer
     */
    public function calculateScore(Question $question, $answer): int
    {
        if (!$this->isCorrect($question, $answer)) {
            return 0;
        }
        
        return $question->getPoints();
    }

    /**
     * Format answer for display
     */
    public function formatAnswer($answer): string
    {
        return $answer === 'true' ? 'True' : 'False';
    }

    /**
     * Get answer options for display
     */
    public function getAnswerOptions(Question $question): array
    {
        return [
            ['value' => 'true', 'text' => 'True'],
            ['value' => 'false', 'text' => 'False']
        ];
    }

    /**
     * Check if answer is correct
     */
    public function isCorrect(Question $question, $answer): bool
    {
        return $answer === $question->getCorrectAnswer();
    }

    /**
     * Get type identifier
     */
    public function getType(): string
    {
        return 'true_false';
    }

    /**
     * Get display name
     */
    public function getDisplayName(): string
    {
        return 'True/False';
    }
}
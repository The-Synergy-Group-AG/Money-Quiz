<?php
declare(strict_types=1);

namespace MoneyQuiz\Features\Question\Types;

use MoneyQuiz\Domain\Entities\Question;

/**
 * Interface for question type handlers
 */
interface QuestionTypeInterface
{
    /**
     * Validate answer for this question type
     */
    public function validateAnswer(Question $question, $answer): bool;

    /**
     * Calculate score for the answer
     */
    public function calculateScore(Question $question, $answer): int;

    /**
     * Format answer for display
     */
    public function formatAnswer($answer): string;

    /**
     * Get answer options for display
     */
    public function getAnswerOptions(Question $question): array;

    /**
     * Check if answer is correct
     */
    public function isCorrect(Question $question, $answer): bool;

    /**
     * Get type identifier
     */
    public function getType(): string;

    /**
     * Get display name
     */
    public function getDisplayName(): string;
}
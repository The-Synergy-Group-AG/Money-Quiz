<?php
declare(strict_types=1);

namespace MoneyQuiz\Features\Question\Types;

use MoneyQuiz\Domain\Entities\Question;

/**
 * Ranking/Ordering question type handler
 */
class RankingType implements QuestionTypeInterface
{
    /**
     * Validate answer for ranking question
     */
    public function validateAnswer(Question $question, $answer): bool
    {
        if (!is_array($answer)) {
            return false;
        }
        
        $options = $question->getOptions();
        $validValues = array_column($options, 'value');
        
        // Check all options are present
        if (count($answer) !== count($validValues)) {
            return false;
        }
        
        // Check for valid option IDs
        foreach ($answer as $rankedId) {
            if (!in_array($rankedId, $validValues)) {
                return false;
            }
        }
        
        // Check for duplicates
        return count($answer) === count(array_unique($answer));
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
        if (!is_array($answer)) {
            return '';
        }
        
        return implode(' > ', $answer);
    }

    /**
     * Get answer options for display
     */
    public function getAnswerOptions(Question $question): array
    {
        return $question->getOptions();
    }

    /**
     * Check if answer is correct
     */
    public function isCorrect(Question $question, $answer): bool
    {
        if (!is_array($answer)) {
            return false;
        }
        
        // Get correct order from question
        $correctOrder = json_decode($question->getCorrectAnswer(), true);
        
        if (!is_array($correctOrder)) {
            return false;
        }
        
        return $answer === $correctOrder;
    }

    /**
     * Get type identifier
     */
    public function getType(): string
    {
        return 'ranking';
    }

    /**
     * Get display name
     */
    public function getDisplayName(): string
    {
        return 'Ranking/Ordering';
    }
}
<?php
declare(strict_types=1);

namespace MoneyQuiz\Features\Question\Types;

use MoneyQuiz\Domain\Entities\Question;

/**
 * Multiple choice question type handler
 */
class MultipleChoiceType implements QuestionTypeInterface
{
    /**
     * Validate answer for multiple choice question
     */
    public function validateAnswer(Question $question, $answer): bool
    {
        $options = $question->getOptions();
        $validValues = array_column($options, 'value');
        
        if (is_array($answer)) {
            // Multiple selection
            foreach ($answer as $selection) {
                if (!in_array($selection, $validValues)) {
                    return false;
                }
            }
            return !empty($answer);
        } else {
            // Single selection
            return in_array($answer, $validValues);
        }
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
        if (is_array($answer)) {
            return implode(', ', $answer);
        }
        
        return (string) $answer;
    }

    /**
     * Get answer options for display
     */
    public function getAnswerOptions(Question $question): array
    {
        $options = $question->getOptions();
        
        // Sort by order if specified
        usort($options, function($a, $b) {
            return ($a['order'] ?? 0) <=> ($b['order'] ?? 0);
        });
        
        return $options;
    }

    /**
     * Check if answer is correct
     */
    public function isCorrect(Question $question, $answer): bool
    {
        $options = $question->getOptions();
        $correctValues = [];
        
        foreach ($options as $option) {
            if ($option['is_correct'] ?? false) {
                $correctValues[] = $option['value'];
            }
        }
        
        if (is_array($answer)) {
            // For multiple selection, all must match
            sort($answer);
            sort($correctValues);
            return $answer === $correctValues;
        } else {
            // For single selection
            return in_array($answer, $correctValues);
        }
    }

    /**
     * Get type identifier
     */
    public function getType(): string
    {
        return 'multiple_choice';
    }

    /**
     * Get display name
     */
    public function getDisplayName(): string
    {
        return 'Multiple Choice';
    }
}
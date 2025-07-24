<?php
declare(strict_types=1);

namespace MoneyQuiz\Domain\ValueObjects;

/**
 * Adapter for Answer value object to provide camelCase method names
 */
class AnswerAdapter
{
    public function __construct(
        private Answer $answer
    ) {}
    
    /**
     * Get question ID
     */
    public function getQuestionId(): int
    {
        // Answer value object stores question_id in its data
        $data = $this->answer->to_array();
        return $data['question_id'] ?? 0;
    }
    
    /**
     * Get answer value
     */
    public function getValue()
    {
        return $this->answer->get_value();
    }
    
    /**
     * Get timestamp
     */
    public function getTimestamp(): ?\DateTimeImmutable
    {
        // If Answer has timestamp support
        $data = $this->answer->to_array();
        if (isset($data['timestamp'])) {
            return new \DateTimeImmutable($data['timestamp']);
        }
        return null;
    }
    
    /**
     * Get the underlying Answer value object
     */
    public function getValueObject(): Answer
    {
        return $this->answer;
    }
}
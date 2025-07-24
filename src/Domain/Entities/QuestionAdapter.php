<?php
declare(strict_types=1);

namespace MoneyQuiz\Domain\Entities;

/**
 * Adapter for Question entity to provide camelCase method names
 * This bridges the gap between Phase 4 code expectations and existing entity methods
 */
class QuestionAdapter
{
    public function __construct(
        private Question $question
    ) {}
    
    /**
     * Delegate to underlying Question entity
     */
    public function __call(string $method, array $args)
    {
        // Map camelCase to snake_case
        $snakeMethod = $this->camelToSnake($method);
        
        if (method_exists($this->question, $snakeMethod)) {
            return call_user_func_array([$this->question, $snakeMethod], $args);
        }
        
        // Try original method name
        if (method_exists($this->question, $method)) {
            return call_user_func_array([$this->question, $method], $args);
        }
        
        throw new \BadMethodCallException("Method {$method} not found on Question entity");
    }
    
    // Explicit method mappings for IDE support and type safety
    
    public function getId(): ?int
    {
        return $this->question->get_id();
    }
    
    public function getQuizId(): int
    {
        return $this->question->get_quiz_id();
    }
    
    public function getType(): string
    {
        return $this->question->get_type();
    }
    
    public function getText(): string
    {
        return $this->question->get_text();
    }
    
    public function getPoints(): int
    {
        return $this->question->get_points();
    }
    
    public function getOrder(): int
    {
        return $this->question->get_order();
    }
    
    public function isRequired(): bool
    {
        return $this->question->is_required();
    }
    
    public function getMetadata(): array
    {
        return $this->question->get_metadata();
    }
    
    /**
     * Get options for multiple choice questions
     */
    public function getOptions(): array
    {
        $answers = $this->question->get_answers();
        $options = [];
        
        foreach ($answers as $index => $answer) {
            $options[] = [
                'value' => (string)$index,
                'text' => $answer->get_text(),
                'is_correct' => $answer->is_correct(),
                'order' => $index
            ];
        }
        
        return $options;
    }
    
    /**
     * Get correct answer
     */
    public function getCorrectAnswer()
    {
        $answers = $this->question->get_answers();
        
        // For true/false, return 'true' or 'false'
        if ($this->question->get_type() === Question::TYPE_TRUE_FALSE) {
            foreach ($answers as $answer) {
                if ($answer->is_correct()) {
                    return strtolower($answer->get_text());
                }
            }
        }
        
        // For multiple choice, return array of correct indices
        $correctIndices = [];
        foreach ($answers as $index => $answer) {
            if ($answer->is_correct()) {
                $correctIndices[] = (string)$index;
            }
        }
        
        // Return single value if only one correct answer
        if (count($correctIndices) === 1) {
            return $correctIndices[0];
        }
        
        return $correctIndices;
    }
    
    /**
     * Get description (from metadata)
     */
    public function getDescription(): string
    {
        $metadata = $this->question->get_metadata();
        return $metadata['description'] ?? '';
    }
    
    /**
     * Get feedback (from metadata)
     */
    public function getFeedback(): string
    {
        $metadata = $this->question->get_metadata();
        return $metadata['feedback'] ?? '';
    }
    
    /**
     * Get category (from metadata)
     */
    public function getCategory(): string
    {
        $metadata = $this->question->get_metadata();
        return $metadata['category'] ?? 'general';
    }
    
    /**
     * Get tags (from metadata)
     */
    public function getTags(): array
    {
        $metadata = $this->question->get_metadata();
        return $metadata['tags'] ?? [];
    }
    
    /**
     * Get the underlying Question entity
     */
    public function getEntity(): Question
    {
        return $this->question;
    }
    
    /**
     * Convert camelCase to snake_case
     */
    private function camelToSnake(string $input): string
    {
        return strtolower(preg_replace('/(?<!^)[A-Z]/', '_$0', $input));
    }
}
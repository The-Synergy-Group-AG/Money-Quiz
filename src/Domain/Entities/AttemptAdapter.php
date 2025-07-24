<?php
declare(strict_types=1);

namespace MoneyQuiz\Domain\Entities;

/**
 * Adapter for Attempt entity to provide camelCase method names
 */
class AttemptAdapter
{
    public function __construct(
        private Attempt $attempt
    ) {}
    
    /**
     * Delegate to underlying Attempt entity
     */
    public function __call(string $method, array $args)
    {
        // Map camelCase to snake_case
        $snakeMethod = $this->camelToSnake($method);
        
        if (method_exists($this->attempt, $snakeMethod)) {
            return call_user_func_array([$this->attempt, $snakeMethod], $args);
        }
        
        // Try original method name
        if (method_exists($this->attempt, $method)) {
            return call_user_func_array([$this->attempt, $method], $args);
        }
        
        throw new \BadMethodCallException("Method {$method} not found on Attempt entity");
    }
    
    // Explicit method mappings
    
    public function getId(): ?int
    {
        return $this->attempt->get_id();
    }
    
    public function getQuizId(): int
    {
        return $this->attempt->get_quiz_id();
    }
    
    public function getUserId(): ?int
    {
        return $this->attempt->get_user_id();
    }
    
    public function getStatus(): string
    {
        return $this->attempt->get_status();
    }
    
    public function isCompleted(): bool
    {
        return $this->attempt->is_completed();
    }
    
    public function getStartedAt(): \DateTimeImmutable
    {
        return $this->attempt->get_started_at();
    }
    
    public function getCompletedAt(): ?\DateTimeImmutable
    {
        return $this->attempt->get_completed_at();
    }
    
    public function getScore(): ?float
    {
        return $this->attempt->get_score();
    }
    
    /**
     * Get the underlying Attempt entity
     */
    public function getEntity(): Attempt
    {
        return $this->attempt;
    }
    
    /**
     * Convert camelCase to snake_case
     */
    private function camelToSnake(string $input): string
    {
        return strtolower(preg_replace('/(?<!^)[A-Z]/', '_$0', $input));
    }
}
<?php
declare(strict_types=1);

namespace MoneyQuiz\Domain\Entities;

/**
 * Adapter for Quiz entity to provide camelCase method names
 */
class QuizAdapter
{
    public function __construct(
        private Quiz $quiz
    ) {}
    
    /**
     * Delegate to underlying Quiz entity
     */
    public function __call(string $method, array $args)
    {
        // Map camelCase to snake_case
        $snakeMethod = $this->camelToSnake($method);
        
        if (method_exists($this->quiz, $snakeMethod)) {
            return call_user_func_array([$this->quiz, $snakeMethod], $args);
        }
        
        // Try original method name
        if (method_exists($this->quiz, $method)) {
            return call_user_func_array([$this->quiz, $method], $args);
        }
        
        throw new \BadMethodCallException("Method {$method} not found on Quiz entity");
    }
    
    // Explicit method mappings
    
    public function getId(): ?int
    {
        return $this->quiz->get_id();
    }
    
    public function getTitle(): string
    {
        return $this->quiz->get_title();
    }
    
    public function getDescription(): string
    {
        return $this->quiz->get_description();
    }
    
    public function getStatus(): string
    {
        return $this->quiz->get_status();
    }
    
    public function getSettings(): array
    {
        return $this->quiz->get_settings();
    }
    
    public function getType(): string
    {
        $settings = $this->quiz->get_settings();
        return $settings['quiz_type'] ?? 'personality';
    }
    
    public function getTimeLimit(): int
    {
        $settings = $this->quiz->get_settings();
        return (int)($settings['time_limit'] ?? 0);
    }
    
    public function getPassingScore(): int
    {
        $settings = $this->quiz->get_settings();
        return (int)($settings['passing_score'] ?? 0);
    }
    
    public function shouldRandomizeQuestions(): bool
    {
        $settings = $this->quiz->get_settings();
        return (bool)($settings['randomize_questions'] ?? false);
    }
    
    public function shouldShowResults(): bool
    {
        $settings = $this->quiz->get_settings();
        return (bool)($settings['show_results'] ?? true);
    }
    
    public function requiresRegistration(): bool
    {
        $settings = $this->quiz->get_settings();
        return (bool)($settings['require_registration'] ?? false);
    }
    
    public function isMultiStep(): bool
    {
        $settings = $this->quiz->get_settings();
        return (bool)($settings['multi_step'] ?? false);
    }
    
    public function getArchiveTagline(): string
    {
        $settings = $this->quiz->get_settings();
        return $settings['archive_tagline'] ?? '';
    }
    
    public function hasQuestions(): bool
    {
        // This would need to be checked via repository
        return true;
    }
    
    /**
     * Get the underlying Quiz entity
     */
    public function getEntity(): Quiz
    {
        return $this->quiz;
    }
    
    /**
     * Convert camelCase to snake_case
     */
    private function camelToSnake(string $input): string
    {
        return strtolower(preg_replace('/(?<!^)[A-Z]/', '_$0', $input));
    }
}
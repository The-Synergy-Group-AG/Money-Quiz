<?php
declare(strict_types=1);

namespace MoneyQuiz\Domain\Entities;

/**
 * Adapter for Archetype entity to provide camelCase method names
 */
class ArchetypeAdapter
{
    public function __construct(
        private Archetype $archetype
    ) {}
    
    /**
     * Delegate to underlying Archetype entity
     */
    public function __call(string $method, array $args)
    {
        // Map camelCase to snake_case
        $snakeMethod = $this->camelToSnake($method);
        
        if (method_exists($this->archetype, $snakeMethod)) {
            return call_user_func_array([$this->archetype, $snakeMethod], $args);
        }
        
        // Try original method name
        if (method_exists($this->archetype, $method)) {
            return call_user_func_array([$this->archetype, $method], $args);
        }
        
        throw new \BadMethodCallException("Method {$method} not found on Archetype entity");
    }
    
    // Explicit method mappings
    
    public function getId(): ?int
    {
        return $this->archetype->get_id();
    }
    
    public function getKey(): string
    {
        return $this->archetype->get_slug();
    }
    
    public function getName(): string
    {
        return $this->archetype->get_name();
    }
    
    public function getDescription(): string
    {
        return $this->archetype->get_description();
    }
    
    public function getTraits(): array
    {
        $characteristics = $this->archetype->get_characteristics();
        return $characteristics['traits'] ?? [];
    }
    
    public function getRecommendations(): array
    {
        $characteristics = $this->archetype->get_characteristics();
        return $characteristics['recommendations'] ?? [];
    }
    
    public function getInsights(): array
    {
        $characteristics = $this->archetype->get_characteristics();
        return $characteristics['insights'] ?? [];
    }
    
    /**
     * Get the underlying Archetype entity
     */
    public function getEntity(): Archetype
    {
        return $this->archetype;
    }
    
    /**
     * Convert camelCase to snake_case
     */
    private function camelToSnake(string $input): string
    {
        return strtolower(preg_replace('/(?<!^)[A-Z]/', '_$0', $input));
    }
}
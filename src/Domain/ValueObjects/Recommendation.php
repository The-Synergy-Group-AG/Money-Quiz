<?php
/**
 * Recommendation Value Object
 *
 * Immutable value object representing a personalized recommendation.
 *
 * @package MoneyQuiz\Domain\ValueObjects
 * @since   7.0.0
 */

namespace MoneyQuiz\Domain\ValueObjects;

use MoneyQuiz\Domain\Exceptions\ValueObjectException;

// Security: Prevent direct access.
defined('ABSPATH') || exit;

/**
 * Recommendation value object class.
 *
 * @since 7.0.0
 */
final class Recommendation {
    
    /**
     * Recommendation types.
     */
    public const TYPE_GENERAL = 'general';
    public const TYPE_ACTION = 'action';
    public const TYPE_RESOURCE = 'resource';
    public const TYPE_WARNING = 'warning';
    public const TYPE_INSIGHT = 'insight';
    
    /**
     * Valid types.
     *
     * @var array<string>
     */
    private const VALID_TYPES = [
        self::TYPE_GENERAL,
        self::TYPE_ACTION,
        self::TYPE_RESOURCE,
        self::TYPE_WARNING,
        self::TYPE_INSIGHT
    ];
    
    /**
     * Recommendation title.
     *
     * @var string
     */
    private string $title;
    
    /**
     * Recommendation description.
     *
     * @var string
     */
    private string $description;
    
    /**
     * Recommendation type.
     *
     * @var string
     */
    private string $type;
    
    /**
     * Priority (0-100).
     *
     * @var int
     */
    private int $priority;
    
    /**
     * Call to action text.
     *
     * @var string
     */
    private string $cta_text;
    
    /**
     * Call to action URL.
     *
     * @var string
     */
    private string $cta_url;
    
    /**
     * Additional metadata.
     *
     * @var array
     */
    private array $metadata;
    
    /**
     * Constructor.
     *
     * @param string $title       Recommendation title.
     * @param string $description Detailed description.
     * @param string $type        Recommendation type.
     * @param int    $priority    Priority (0-100).
     * @param array  $metadata    Additional metadata.
     * @param string $cta_text    Call to action text.
     * @param string $cta_url     Call to action URL.
     */
    public function __construct(
        string $title,
        string $description,
        string $type = self::TYPE_GENERAL,
        int $priority = 50,
        array $metadata = [],
        string $cta_text = '',
        string $cta_url = ''
    ) {
        $this->title = $title;
        $this->description = $description;
        $this->type = $type;
        $this->priority = $priority;
        $this->metadata = $metadata;
        $this->cta_text = $cta_text;
        $this->cta_url = $cta_url;
        
        $this->validate();
    }
    
    /**
     * Validate recommendation.
     *
     * @throws ValueObjectException If validation fails.
     * @return void
     */
    private function validate(): void {
        if (empty($this->title)) {
            throw new ValueObjectException('Recommendation title is required');
        }
        
        if (strlen($this->title) > 200) {
            throw new ValueObjectException('Title must not exceed 200 characters');
        }
        
        if (empty($this->description)) {
            throw new ValueObjectException('Recommendation description is required');
        }
        
        if (strlen($this->description) > 2000) {
            throw new ValueObjectException('Description must not exceed 2000 characters');
        }
        
        if (!in_array($this->type, self::VALID_TYPES, true)) {
            throw new ValueObjectException("Invalid recommendation type: {$this->type}");
        }
        
        if ($this->priority < 0 || $this->priority > 100) {
            throw new ValueObjectException('Priority must be between 0 and 100');
        }
        
        if (!empty($this->cta_url) && !filter_var($this->cta_url, FILTER_VALIDATE_URL)) {
            throw new ValueObjectException('Invalid CTA URL');
        }
        
        if (strlen($this->cta_text) > 100) {
            throw new ValueObjectException('CTA text must not exceed 100 characters');
        }
    }
    
    /**
     * Get title.
     *
     * @return string Title.
     */
    public function get_title(): string {
        return $this->title;
    }
    
    /**
     * Get description.
     *
     * @return string Description.
     */
    public function get_description(): string {
        return $this->description;
    }
    
    /**
     * Get type.
     *
     * @return string Type.
     */
    public function get_type(): string {
        return $this->type;
    }
    
    /**
     * Get priority.
     *
     * @return int Priority.
     */
    public function get_priority(): int {
        return $this->priority;
    }
    
    /**
     * Get CTA text.
     *
     * @return string CTA text.
     */
    public function get_cta_text(): string {
        return $this->cta_text;
    }
    
    /**
     * Get CTA URL.
     *
     * @return string CTA URL.
     */
    public function get_cta_url(): string {
        return $this->cta_url;
    }
    
    /**
     * Get metadata.
     *
     * @return array Metadata.
     */
    public function get_metadata(): array {
        return $this->metadata;
    }
    
    /**
     * Check if has CTA.
     *
     * @return bool True if has CTA.
     */
    public function has_cta(): bool {
        return !empty($this->cta_text) && !empty($this->cta_url);
    }
    
    /**
     * Convert to array.
     *
     * @return array Recommendation data.
     */
    public function to_array(): array {
        return [
            'title' => $this->title,
            'description' => $this->description,
            'type' => $this->type,
            'priority' => $this->priority,
            'cta_text' => $this->cta_text,
            'cta_url' => $this->cta_url,
            'metadata' => $this->metadata
        ];
    }
    
    /**
     * Create from array.
     *
     * @param array $data Recommendation data.
     * @return self New instance.
     */
    public static function from_array(array $data): self {
        return new self(
            $data['title'] ?? '',
            $data['description'] ?? '',
            $data['type'] ?? self::TYPE_GENERAL,
            $data['priority'] ?? 50,
            $data['metadata'] ?? [],
            $data['cta_text'] ?? '',
            $data['cta_url'] ?? ''
        );
    }
}
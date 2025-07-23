<?php
/**
 * Serializable Entity Trait
 *
 * Provides serialization functionality for domain entities.
 *
 * @package MoneyQuiz\Domain\Traits
 * @since   7.0.0
 */

namespace MoneyQuiz\Domain\Traits;

// Security: Prevent direct access.
defined('ABSPATH') || exit;

/**
 * Trait for entity serialization.
 *
 * @since 7.0.0
 */
trait SerializableEntity {
    
    /**
     * Convert entity to array.
     *
     * @return array Entity data.
     */
    abstract public function to_array(): array;
    
    /**
     * Create entity from array.
     *
     * @param array $data Entity data.
     * @return static Entity instance.
     */
    abstract public static function from_array(array $data): self;
    
    /**
     * Convert timestamps to array format.
     *
     * @return array Timestamp data.
     */
    protected function timestamps_to_array(): array {
        return [
            'created_at' => $this->created_at?->format('Y-m-d H:i:s'),
            'updated_at' => $this->updated_at?->format('Y-m-d H:i:s')
        ];
    }
    
    /**
     * Set timestamps from array data.
     *
     * @param array $data Data containing timestamps.
     * @return void
     */
    protected function set_timestamps_from_array(array $data): void {
        if (isset($data['created_at'])) {
            $this->created_at = new \DateTimeImmutable($data['created_at']);
        }
        
        if (isset($data['updated_at'])) {
            $this->updated_at = new \DateTimeImmutable($data['updated_at']);
        }
    }
    
    /**
     * Encode array or object to JSON.
     *
     * @param mixed $data Data to encode.
     * @return string JSON string.
     */
    protected function encode_json($data): string {
        return wp_json_encode($data) ?: '{}';
    }
    
    /**
     * Decode JSON to array.
     *
     * @param string $json JSON string.
     * @param mixed  $default Default value if decode fails.
     * @return mixed Decoded data.
     */
    protected function decode_json(string $json, $default = []) {
        $decoded = json_decode($json, true);
        return $decoded !== null ? $decoded : $default;
    }
}
<?php
/**
 * Archetype Serializer
 *
 * Handles serialization for Archetype entities.
 *
 * @package MoneyQuiz\Domain\Serializers
 * @since   7.0.0
 */

namespace MoneyQuiz\Domain\Serializers;

use MoneyQuiz\Domain\Entities\Archetype;
use MoneyQuiz\Domain\ValueObjects\ArchetypeCriteria;

// Security: Prevent direct access.
defined('ABSPATH') || exit;

/**
 * Archetype serializer class.
 *
 * @since 7.0.0
 */
class ArchetypeSerializer {
    
    /**
     * Convert Archetype to array.
     *
     * @param Archetype $archetype Archetype entity.
     * @return array Archetype data.
     */
    public static function to_array(Archetype $archetype): array {
        // Use reflection to access private properties
        $reflection = new \ReflectionClass($archetype);
        
        $data = [
            'id' => $archetype->get_id(),
            'name' => $archetype->get_name(),
            'slug' => $archetype->get_slug(),
            'description' => $archetype->get_description(),
            'characteristics' => $archetype->get_characteristics(),
            'criteria' => $archetype->get_criteria()->to_array(),
            'order' => $archetype->get_order(),
            'is_active' => $archetype->is_active()
        ];
        
        // Get recommendation_templates
        $prop = $reflection->getProperty('recommendation_templates');
        $prop->setAccessible(true);
        $data['recommendation_templates'] = $prop->getValue($archetype);
        
        // Get timestamps from parent Entity class
        $created_at = $reflection->getParentClass()->getProperty('created_at');
        $created_at->setAccessible(true);
        $data['created_at'] = $created_at->getValue($archetype)?->format('Y-m-d H:i:s');
        
        $updated_at = $reflection->getParentClass()->getProperty('updated_at');
        $updated_at->setAccessible(true);
        $data['updated_at'] = $updated_at->getValue($archetype)?->format('Y-m-d H:i:s');
        
        return $data;
    }
    
    /**
     * Create Archetype from array.
     *
     * @param array $data Archetype data.
     * @return Archetype Archetype instance.
     */
    public static function from_array(array $data): Archetype {
        $archetype = new Archetype(
            $data['name'],
            $data['slug'],
            $data['description'],
            $data['characteristics'],
            ArchetypeCriteria::from_array($data['criteria']),
            $data['recommendation_templates'] ?? [],
            $data['order'] ?? 0,
            $data['is_active'] ?? true
        );
        
        // Set persisted properties
        if (isset($data['id'])) {
            $archetype->set_id((int) $data['id']);
        }
        
        // Use reflection to set timestamps
        $reflection = new \ReflectionClass($archetype);
        
        if (isset($data['created_at'])) {
            $prop = $reflection->getParentClass()->getProperty('created_at');
            $prop->setAccessible(true);
            $prop->setValue($archetype, new \DateTimeImmutable($data['created_at']));
        }
        
        if (isset($data['updated_at'])) {
            $prop = $reflection->getParentClass()->getProperty('updated_at');
            $prop->setAccessible(true);
            $prop->setValue($archetype, new \DateTimeImmutable($data['updated_at']));
        }
        
        return $archetype;
    }
}
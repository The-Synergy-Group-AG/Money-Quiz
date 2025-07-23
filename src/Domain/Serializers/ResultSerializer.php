<?php
/**
 * Result Serializer
 *
 * Handles serialization for Result entities.
 *
 * @package MoneyQuiz\Domain\Serializers
 * @since   7.0.0
 */

namespace MoneyQuiz\Domain\Serializers;

use MoneyQuiz\Domain\Entities\Result;
use MoneyQuiz\Domain\Entities\Archetype;
use MoneyQuiz\Domain\ValueObjects\Score;
use MoneyQuiz\Domain\ValueObjects\Recommendation;

// Security: Prevent direct access.
defined('ABSPATH') || exit;

/**
 * Result serializer class.
 *
 * @since 7.0.0
 */
class ResultSerializer {
    
    /**
     * Convert Result to array.
     *
     * @param Result $result Result entity.
     * @return array Result data.
     */
    public static function to_array(Result $result): array {
        // Use reflection to access private properties
        $reflection = new \ReflectionClass($result);
        
        $data = [
            'id' => $result->get_id(),
            'attempt_id' => $result->get_attempt_id(),
            'quiz_id' => $result->get_quiz_id(),
            'user_id' => $result->get_user_id(),
            'score' => $result->get_score()->to_array(),
            'archetype_id' => $result->get_archetype_id(),
            'archetype' => $result->get_archetype()?->to_array(),
            'recommendations' => array_map(
                fn($rec) => $rec->to_array(),
                $result->get_recommendations()
            ),
            'calculated_at' => $result->get_calculated_at()->format('Y-m-d H:i:s'),
            'metadata' => $result->get_metadata()
        ];
        
        // Get timestamps from parent Entity class
        $created_at = $reflection->getParentClass()->getProperty('created_at');
        $created_at->setAccessible(true);
        $data['created_at'] = $created_at->getValue($result)?->format('Y-m-d H:i:s');
        
        $updated_at = $reflection->getParentClass()->getProperty('updated_at');
        $updated_at->setAccessible(true);
        $data['updated_at'] = $updated_at->getValue($result)?->format('Y-m-d H:i:s');
        
        return $data;
    }
    
    /**
     * Create Result from array.
     *
     * @param array $data Result data.
     * @return Result Result instance.
     */
    public static function from_array(array $data): Result {
        $result = new Result(
            $data['attempt_id'],
            $data['quiz_id'],
            $data['user_id'],
            Score::from_array($data['score']),
            $data['metadata'] ?? []
        );
        
        // Use reflection to set private properties
        $reflection = new \ReflectionClass($result);
        
        // Set persisted properties
        if (isset($data['id'])) {
            $result->set_id((int) $data['id']);
        }
        
        if (isset($data['archetype_id'])) {
            $prop = $reflection->getProperty('archetype_id');
            $prop->setAccessible(true);
            $prop->setValue($result, (int) $data['archetype_id']);
        }
        
        if (isset($data['archetype']) && is_array($data['archetype'])) {
            $prop = $reflection->getProperty('archetype');
            $prop->setAccessible(true);
            $prop->setValue($result, Archetype::from_array($data['archetype']));
        }
        
        if (isset($data['recommendations'])) {
            $recommendations = array_map(
                fn($rec_data) => Recommendation::from_array($rec_data),
                $data['recommendations']
            );
            $prop = $reflection->getProperty('recommendations');
            $prop->setAccessible(true);
            $prop->setValue($result, $recommendations);
        }
        
        if (isset($data['calculated_at'])) {
            $prop = $reflection->getProperty('calculated_at');
            $prop->setAccessible(true);
            $prop->setValue($result, new \DateTimeImmutable($data['calculated_at']));
        }
        
        // Set timestamps on parent class
        if (isset($data['created_at'])) {
            $prop = $reflection->getParentClass()->getProperty('created_at');
            $prop->setAccessible(true);
            $prop->setValue($result, new \DateTimeImmutable($data['created_at']));
        }
        
        if (isset($data['updated_at'])) {
            $prop = $reflection->getParentClass()->getProperty('updated_at');
            $prop->setAccessible(true);
            $prop->setValue($result, new \DateTimeImmutable($data['updated_at']));
        }
        
        return $result;
    }
}
<?php
/**
 * Attempt Serializer
 *
 * Handles serialization for Attempt entities.
 *
 * @package MoneyQuiz\Domain\Serializers
 * @since   7.0.0
 */

namespace MoneyQuiz\Domain\Serializers;

use MoneyQuiz\Domain\Entities\Attempt;

// Security: Prevent direct access.
defined('ABSPATH') || exit;

/**
 * Attempt serializer class.
 *
 * @since 7.0.0
 */
class AttemptSerializer {
    
    /**
     * Convert Attempt to array.
     *
     * @param Attempt $attempt Attempt entity.
     * @return array Attempt data.
     */
    public static function to_array(Attempt $attempt): array {
        // Use reflection to access private properties
        $reflection = new \ReflectionClass($attempt);
        
        $data = [
            'id' => $attempt->get_id(),
            'quiz_id' => $attempt->get_quiz_id(),
            'user_id' => $attempt->get_user_id(),
            'user_email' => $attempt->get_user_email(),
            'status' => $attempt->get_status(),
            'started_at' => $attempt->get_started_at()->format('Y-m-d H:i:s'),
            'completed_at' => $attempt->get_completed_at()?->format('Y-m-d H:i:s'),
            'answers' => $attempt->get_answers(),
            'questions' => $attempt->get_questions(),
            'time_taken' => $attempt->get_time_taken(),
            'result_id' => $attempt->get_result_id(),
            'session_token' => $attempt->get_session_token(),
            'metadata' => $attempt->get_metadata()
        ];
        
        // Get private properties
        $ip_address = $reflection->getProperty('ip_address');
        $ip_address->setAccessible(true);
        $data['ip_address'] = $ip_address->getValue($attempt);
        
        $user_agent = $reflection->getProperty('user_agent');
        $user_agent->setAccessible(true);
        $data['user_agent'] = $user_agent->getValue($attempt);
        
        // Get timestamps from parent Entity class
        $created_at = $reflection->getParentClass()->getProperty('created_at');
        $created_at->setAccessible(true);
        $data['created_at'] = $created_at->getValue($attempt)?->format('Y-m-d H:i:s');
        
        $updated_at = $reflection->getParentClass()->getProperty('updated_at');
        $updated_at->setAccessible(true);
        $data['updated_at'] = $updated_at->getValue($attempt)?->format('Y-m-d H:i:s');
        
        return $data;
    }
    
    /**
     * Create Attempt from array.
     *
     * @param array $data Attempt data.
     * @return Attempt Attempt instance.
     */
    public static function from_array(array $data): Attempt {
        $attempt = new Attempt(
            $data['quiz_id'],
            $data['user_id'] ?? null,
            $data['user_email'] ?? null,
            $data['questions'] ?? [],
            $data['metadata'] ?? []
        );
        
        // Use reflection to set private properties
        $reflection = new \ReflectionClass($attempt);
        
        // Set persisted properties
        if (isset($data['id'])) {
            $attempt->set_id((int) $data['id']);
        }
        
        if (isset($data['status'])) {
            $prop = $reflection->getProperty('status');
            $prop->setAccessible(true);
            $prop->setValue($attempt, $data['status']);
        }
        
        if (isset($data['answers'])) {
            $prop = $reflection->getProperty('answers');
            $prop->setAccessible(true);
            $prop->setValue($attempt, $data['answers']);
        }
        
        if (isset($data['time_taken'])) {
            $prop = $reflection->getProperty('time_taken');
            $prop->setAccessible(true);
            $prop->setValue($attempt, (int) $data['time_taken']);
        }
        
        if (isset($data['result_id'])) {
            $prop = $reflection->getProperty('result_id');
            $prop->setAccessible(true);
            $prop->setValue($attempt, (int) $data['result_id']);
        }
        
        if (isset($data['session_token'])) {
            $prop = $reflection->getProperty('session_token');
            $prop->setAccessible(true);
            $prop->setValue($attempt, $data['session_token']);
        }
        
        if (isset($data['ip_address'])) {
            $prop = $reflection->getProperty('ip_address');
            $prop->setAccessible(true);
            $prop->setValue($attempt, $data['ip_address']);
        }
        
        if (isset($data['user_agent'])) {
            $prop = $reflection->getProperty('user_agent');
            $prop->setAccessible(true);
            $prop->setValue($attempt, $data['user_agent']);
        }
        
        if (isset($data['started_at'])) {
            $prop = $reflection->getProperty('started_at');
            $prop->setAccessible(true);
            $prop->setValue($attempt, new \DateTimeImmutable($data['started_at']));
        }
        
        if (isset($data['completed_at'])) {
            $prop = $reflection->getProperty('completed_at');
            $prop->setAccessible(true);
            $prop->setValue($attempt, new \DateTimeImmutable($data['completed_at']));
        }
        
        // Set timestamps on parent class
        if (isset($data['created_at'])) {
            $prop = $reflection->getParentClass()->getProperty('created_at');
            $prop->setAccessible(true);
            $prop->setValue($attempt, new \DateTimeImmutable($data['created_at']));
        }
        
        if (isset($data['updated_at'])) {
            $prop = $reflection->getParentClass()->getProperty('updated_at');
            $prop->setAccessible(true);
            $prop->setValue($attempt, new \DateTimeImmutable($data['updated_at']));
        }
        
        return $attempt;
    }
}
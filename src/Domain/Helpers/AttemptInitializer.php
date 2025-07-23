<?php
/**
 * Attempt Initializer Helper
 *
 * Handles initialization logic for Attempt entity.
 *
 * @package MoneyQuiz\Domain\Helpers
 * @since   7.0.0
 */

namespace MoneyQuiz\Domain\Helpers;

use MoneyQuiz\Domain\Entities\Attempt;
use MoneyQuiz\Domain\Events\AttemptStarted;

// Security: Prevent direct access.
defined('ABSPATH') || exit;

/**
 * Attempt initializer class.
 *
 * @since 7.0.0
 */
class AttemptInitializer {
    
    /**
     * Initialize attempt properties.
     *
     * @param Attempt     $attempt    The attempt entity.
     * @param int         $quiz_id    Quiz ID.
     * @param int|null    $user_id    User ID or null for anonymous.
     * @param string|null $user_email User email for anonymous users.
     * @param array       $questions  Questions for this attempt.
     * @param array       $metadata   Additional metadata.
     * @return void
     */
    public static function initialize(
        Attempt $attempt,
        int $quiz_id,
        ?int $user_id,
        ?string $user_email,
        array $questions,
        array $metadata
    ): void {
        // Use reflection to set private properties
        $reflection = new \ReflectionClass($attempt);
        
        $props = [
            'quiz_id' => $quiz_id,
            'user_id' => $user_id,
            'user_email' => $user_email,
            'questions' => $questions,
            'metadata' => $metadata,
            'status' => 'started',
            'started_at' => new \DateTimeImmutable('now', wp_timezone()),
            'answers' => []
        ];
        
        foreach ($props as $name => $value) {
            $prop = $reflection->getProperty($name);
            $prop->setAccessible(true);
            $prop->setValue($attempt, $value);
        }
        
        // Generate session token for anonymous users
        if (!$user_id) {
            $prop = $reflection->getProperty('session_token');
            $prop->setAccessible(true);
            $prop->setValue($attempt, wp_generate_password(32, false));
        }
        
        // Capture request info
        $prop = $reflection->getProperty('ip_address');
        $prop->setAccessible(true);
        $prop->setValue($attempt, self::get_client_ip());
        
        $prop = $reflection->getProperty('user_agent');
        $prop->setAccessible(true);
        $prop->setValue($attempt, $_SERVER['HTTP_USER_AGENT'] ?? null);
        
        // Call validation and update timestamps
        $attempt->validate();
        $attempt->update_timestamps(true);
        
        // Record event
        $attempt->record_event(new AttemptStarted($attempt));
    }
    
    /**
     * Get client IP address.
     *
     * @return string|null IP address or null.
     */
    private static function get_client_ip(): ?string {
        $ip_keys = ['HTTP_CF_CONNECTING_IP', 'HTTP_X_FORWARDED_FOR', 'REMOTE_ADDR'];
        
        foreach ($ip_keys as $key) {
            if (!empty($_SERVER[$key])) {
                $ips = explode(',', $_SERVER[$key]);
                $ip = trim($ips[0]);
                
                if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                    return $ip;
                }
            }
        }
        
        return $_SERVER['REMOTE_ADDR'] ?? null;
    }
}
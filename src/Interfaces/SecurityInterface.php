<?php
/**
 * Security Interface
 *
 * @package MoneyQuiz
 * @since 4.1.0
 */

namespace MoneyQuiz\Interfaces;

/**
 * Interface for security components
 */
interface SecurityInterface {
    
    /**
     * Verify security requirements for request
     * 
     * @param string $action Action identifier
     * @throws \MoneyQuiz\Exceptions\SecurityException If verification fails
     */
    public function verify_request( string $action = 'default' ): void;
}
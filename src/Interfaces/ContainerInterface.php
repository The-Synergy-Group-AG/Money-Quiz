<?php
/**
 * Container Interface
 *
 * @package MoneyQuiz
 * @since 4.0.0
 */

namespace MoneyQuiz\Interfaces;

/**
 * Interface for dependency injection container
 * Based on PSR-11 ContainerInterface principles
 */
interface ContainerInterface {
    
    /**
     * Get a service from the container
     * 
     * @param string $id Service identifier
     * @return mixed
     * @throws \MoneyQuiz\Exceptions\NotFoundException If service not found
     * @throws \MoneyQuiz\Exceptions\ContainerException If service cannot be resolved
     */
    public function get( string $id );
    
    /**
     * Check if a service exists in the container
     * 
     * @param string $id Service identifier
     * @return bool
     */
    public function has( string $id ): bool;
}
<?php
/**
 * Repository Interface
 *
 * @package MoneyQuiz
 * @since 4.0.0
 */

namespace MoneyQuiz\Interfaces;

/**
 * Interface for repository classes
 */
interface RepositoryInterface {
    
    /**
     * Find a record by ID
     * 
     * @param int $id Record ID
     * @return object|null
     */
    public function find( int $id ): ?object;
    
    /**
     * Get all records
     * 
     * @param array $args Query arguments
     * @return array
     */
    public function all( array $args = [] ): array;
    
    /**
     * Insert a record
     * 
     * @param array $data Data to insert
     * @return int|false Insert ID or false on failure
     */
    public function insert( array $data );
    
    /**
     * Update a record
     * 
     * @param int   $id   Record ID
     * @param array $data Data to update
     * @return bool
     */
    public function update( int $id, array $data ): bool;
    
    /**
     * Delete a record
     * 
     * @param int $id Record ID
     * @return bool
     */
    public function delete( int $id ): bool;
    
    /**
     * Count records
     * 
     * @param array $where Where conditions
     * @return int
     */
    public function count( array $where = [] ): int;
    
    /**
     * Check if a record exists
     * 
     * @param int $id Record ID
     * @return bool
     */
    public function exists( int $id ): bool;
}
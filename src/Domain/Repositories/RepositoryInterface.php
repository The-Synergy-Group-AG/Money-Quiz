<?php
/**
 * Repository Interface
 *
 * Base interface for all repositories.
 *
 * @package MoneyQuiz\Domain\Repositories
 * @since   7.0.0
 */

namespace MoneyQuiz\Domain\Repositories;

use MoneyQuiz\Domain\Entities\Entity;

// Security: Prevent direct access.
defined('ABSPATH') || exit;

/**
 * Repository interface.
 *
 * Defines the contract for data access layer implementations.
 * All repositories must implement this interface.
 *
 * @since 7.0.0
 */
interface RepositoryInterface {
    
    /**
     * Find entity by ID.
     *
     * @param int $id Entity ID.
     * @return Entity|null Entity instance or null if not found.
     */
    public function find(int $id): ?Entity;
    
    /**
     * Find entity by ID or throw exception.
     *
     * @param int $id Entity ID.
     * @return Entity Entity instance.
     * @throws \RuntimeException If entity not found.
     */
    public function find_or_fail(int $id): Entity;
    
    /**
     * Find all entities.
     *
     * @param array $criteria Optional criteria.
     * @param array $order_by Optional ordering.
     * @param int   $limit    Optional limit.
     * @param int   $offset   Optional offset.
     * @return array<Entity> Array of entities.
     */
    public function find_all(
        array $criteria = [],
        array $order_by = [],
        int $limit = 0,
        int $offset = 0
    ): array;
    
    /**
     * Find entities by criteria.
     *
     * @param array $criteria Search criteria.
     * @return array<Entity> Array of entities.
     */
    public function find_by(array $criteria): array;
    
    /**
     * Find single entity by criteria.
     *
     * @param array $criteria Search criteria.
     * @return Entity|null Entity instance or null.
     */
    public function find_one_by(array $criteria): ?Entity;
    
    /**
     * Save entity.
     *
     * @param Entity $entity Entity to save.
     * @return Entity Saved entity with ID.
     */
    public function save(Entity $entity): Entity;
    
    /**
     * Delete entity.
     *
     * @param Entity $entity Entity to delete.
     * @return bool True if deleted.
     */
    public function delete(Entity $entity): bool;
    
    /**
     * Delete by ID.
     *
     * @param int $id Entity ID.
     * @return bool True if deleted.
     */
    public function delete_by_id(int $id): bool;
    
    /**
     * Count entities.
     *
     * @param array $criteria Optional criteria.
     * @return int Total count.
     */
    public function count(array $criteria = []): int;
    
    /**
     * Check if entity exists.
     *
     * @param int $id Entity ID.
     * @return bool True if exists.
     */
    public function exists(int $id): bool;
    
    /**
     * Begin transaction.
     *
     * @return void
     */
    public function begin_transaction(): void;
    
    /**
     * Commit transaction.
     *
     * @return void
     */
    public function commit(): void;
    
    /**
     * Rollback transaction.
     *
     * @return void
     */
    public function rollback(): void;
}
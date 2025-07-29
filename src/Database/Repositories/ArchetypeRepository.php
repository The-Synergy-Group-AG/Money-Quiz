<?php
/**
 * Archetype Repository
 *
 * @package MoneyQuiz
 * @since 4.0.0
 */

namespace MoneyQuiz\Database\Repositories;

/**
 * Repository for archetype data access
 */
class ArchetypeRepository extends BaseRepository {
    
    /**
     * Get table name
     * 
     * @return string
     */
    protected function get_table_name(): string {
        return $this->db->prefix . 'money_quiz_archetypes';
    }
    
    /**
     * Find archetype by ID
     * 
     * @param int $id Archetype ID
     * @return object|null
     */
    public function find( int $id ) {
        $query = $this->db->prepare(
            "SELECT * FROM {$this->get_table_name()} WHERE id = %d",
            $id
        );
        
        return $this->db->get_row( $query );
    }
    
    /**
     * Get all archetypes
     * 
     * @return array
     */
    public function all(): array {
        $query = "SELECT * FROM {$this->get_table_name()} ORDER BY name ASC";
        return $this->db->get_results( $query );
    }
    
    /**
     * Get archetype by slug
     * 
     * @param string $slug Archetype slug
     * @return object|null
     */
    public function find_by_slug( string $slug ) {
        $query = $this->db->prepare(
            "SELECT * FROM {$this->get_table_name()} WHERE slug = %s",
            $slug
        );
        
        return $this->db->get_row( $query );
    }
    
    /**
     * Create new archetype
     * 
     * @param array $data Archetype data
     * @return int|false Insert ID or false on failure
     */
    public function create( array $data ) {
        $result = $this->db->insert(
            $this->get_table_name(),
            [
                'name' => $data['name'],
                'slug' => $data['slug'] ?? sanitize_title( $data['name'] ),
                'description' => $data['description'] ?? '',
                'color' => $data['color'] ?? '#000000',
                'icon' => $data['icon'] ?? '',
                'created_at' => current_time( 'mysql' ),
                'updated_at' => current_time( 'mysql' ),
            ],
            [ '%s', '%s', '%s', '%s', '%s', '%s', '%s' ]
        );
        
        return $result ? $this->db->insert_id : false;
    }
    
    /**
     * Update archetype
     * 
     * @param int   $id   Archetype ID
     * @param array $data Update data
     * @return bool
     */
    public function update( int $id, array $data ): bool {
        $data['updated_at'] = current_time( 'mysql' );
        
        return (bool) $this->db->update(
            $this->get_table_name(),
            $data,
            [ 'id' => $id ],
            null,
            [ '%d' ]
        );
    }
    
    /**
     * Delete archetype
     * 
     * @param int $id Archetype ID
     * @return bool
     */
    public function delete( int $id ): bool {
        return (bool) $this->db->delete(
            $this->get_table_name(),
            [ 'id' => $id ],
            [ '%d' ]
        );
    }
    
    /**
     * Count archetypes
     * 
     * @return int
     */
    public function count(): int {
        $query = "SELECT COUNT(*) FROM {$this->get_table_name()}";
        return (int) $this->db->get_var( $query );
    }
}
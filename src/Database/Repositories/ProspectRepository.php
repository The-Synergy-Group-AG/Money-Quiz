<?php
/**
 * Prospect Repository
 *
 * @package MoneyQuiz
 * @since 4.0.0
 */

namespace MoneyQuiz\Database\Repositories;

/**
 * Repository for prospect/lead data access
 */
class ProspectRepository extends BaseRepository {
    
    /**
     * Get table name
     * 
     * @return string
     */
    protected function get_table_name(): string {
        return $this->db->prefix . 'money_quiz_prospects';
    }
    
    /**
     * Find prospect by ID
     * 
     * @param int $id Prospect ID
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
     * Find prospect by email
     * 
     * @param string $email Email address
     * @return object|null
     */
    public function find_by_email( string $email ) {
        $query = $this->db->prepare(
            "SELECT * FROM {$this->get_table_name()} WHERE email = %s",
            $email
        );
        
        return $this->db->get_row( $query );
    }
    
    /**
     * Get all prospects
     * 
     * @param array $args Query arguments
     * @return array
     */
    public function all( array $args = [] ): array {
        $defaults = [
            'orderby' => 'created_at',
            'order' => 'DESC',
            'limit' => 100,
            'offset' => 0,
        ];
        
        $args = wp_parse_args( $args, $defaults );
        
        $query = $this->db->prepare(
            "SELECT * FROM {$this->get_table_name()} 
             ORDER BY {$args['orderby']} {$args['order']} 
             LIMIT %d OFFSET %d",
            $args['limit'],
            $args['offset']
        );
        
        return $this->db->get_results( $query );
    }
    
    /**
     * Create new prospect
     * 
     * @param array $data Prospect data
     * @return int|false Insert ID or false on failure
     */
    public function create( array $data ) {
        // Check if prospect already exists
        $existing = $this->find_by_email( $data['email'] );
        if ( $existing ) {
            return $this->update( $existing->id, $data ) ? $existing->id : false;
        }
        
        $result = $this->db->insert(
            $this->get_table_name(),
            [
                'email' => $data['email'],
                'name' => $data['name'] ?? '',
                'phone' => $data['phone'] ?? '',
                'company' => $data['company'] ?? '',
                'result_id' => $data['result_id'] ?? null,
                'archetype_id' => $data['archetype_id'] ?? null,
                'ip_address' => $data['ip_address'] ?? $_SERVER['REMOTE_ADDR'] ?? '',
                'user_agent' => $data['user_agent'] ?? $_SERVER['HTTP_USER_AGENT'] ?? '',
                'referrer' => $data['referrer'] ?? $_SERVER['HTTP_REFERER'] ?? '',
                'utm_source' => $data['utm_source'] ?? '',
                'utm_medium' => $data['utm_medium'] ?? '',
                'utm_campaign' => $data['utm_campaign'] ?? '',
                'created_at' => current_time( 'mysql' ),
                'updated_at' => current_time( 'mysql' ),
            ],
            [ '%s', '%s', '%s', '%s', '%d', '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s' ]
        );
        
        return $result ? $this->db->insert_id : false;
    }
    
    /**
     * Update prospect
     * 
     * @param int   $id   Prospect ID
     * @param array $data Update data
     * @return bool
     */
    public function update( int $id, array $data ): bool {
        $data['updated_at'] = current_time( 'mysql' );
        
        // Remove email from update if it exists (shouldn't change email)
        unset( $data['email'] );
        
        return (bool) $this->db->update(
            $this->get_table_name(),
            $data,
            [ 'id' => $id ],
            null,
            [ '%d' ]
        );
    }
    
    /**
     * Delete prospect
     * 
     * @param int $id Prospect ID
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
     * Count prospects
     * 
     * @param array $where Where conditions
     * @return int
     */
    public function count( array $where = [] ): int {
        $query = "SELECT COUNT(*) FROM {$this->get_table_name()}";
        
        if ( ! empty( $where ) ) {
            $conditions = [];
            foreach ( $where as $column => $value ) {
                $conditions[] = $this->db->prepare( "$column = %s", $value );
            }
            $query .= " WHERE " . implode( ' AND ', $conditions );
        }
        
        return (int) $this->db->get_var( $query );
    }
    
    /**
     * Get prospects by archetype
     * 
     * @param int $archetype_id Archetype ID
     * @return array
     */
    public function get_by_archetype( int $archetype_id ): array {
        $query = $this->db->prepare(
            "SELECT * FROM {$this->get_table_name()} 
             WHERE archetype_id = %d 
             ORDER BY created_at DESC",
            $archetype_id
        );
        
        return $this->db->get_results( $query );
    }
}
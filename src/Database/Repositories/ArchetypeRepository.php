<?php
/**
 * Archetype Repository
 *
 * Handles archetype data persistence and retrieval.
 *
 * @package MoneyQuiz\Database\Repositories
 * @since   7.0.0
 */

namespace MoneyQuiz\Database\Repositories;

use MoneyQuiz\Database\AbstractRepository;
use MoneyQuiz\Domain\Repositories\ArchetypeRepository as ArchetypeRepositoryInterface;
use MoneyQuiz\Domain\Entities\Archetype;
use MoneyQuiz\Domain\ValueObjects\ArchetypeCriteria;

// Security: Prevent direct access.
defined('ABSPATH') || exit;

/**
 * Archetype repository class.
 *
 * @since 7.0.0
 */
class ArchetypeRepository extends AbstractRepository implements ArchetypeRepositoryInterface {
    
    /**
     * Set table name.
     *
     * @since 7.0.0
     *
     * @return void
     */
    protected function set_table_name(): void {
        $this->table = $this->db->prefix . 'money_quiz_archetypes';
    }
    
    /**
     * Find active archetypes.
     *
     * @since 7.0.0
     *
     * @return array<Archetype> Active archetypes ordered by priority.
     */
    public function find_active(): array {
        try {
            $results = $this->query()
                ->where('is_active', 1)
                ->orderBy('order', 'ASC')
                ->orderBy('name', 'ASC')
                ->get();
            
            return array_map([$this, 'hydrate'], $results);
        } catch (\Exception $e) {
            $this->logger->error('ArchetypeRepository find_active error', [
                'error' => $e->getMessage()
            ]);
            return [];
        }
    }
    
    /**
     * Find archetype by slug.
     *
     * @since 7.0.0
     *
     * @param string $slug Archetype slug.
     * @return Archetype|null Archetype or null.
     */
    public function find_by_slug(string $slug): ?Archetype {
        try {
            $data = $this->query()
                ->where('slug', $slug)
                ->first();
            
            return $data ? $this->hydrate($data) : null;
        } catch (\Exception $e) {
            $this->logger->error('ArchetypeRepository find_by_slug error', [
                'slug' => $slug,
                'error' => $e->getMessage()
            ]);
            return null;
        }
    }
    
    /**
     * Get archetype usage count.
     *
     * @since 7.0.0
     *
     * @param int $archetype_id Archetype ID.
     * @param int $days         Days to look back (0 = all time).
     * @return int Usage count.
     */
    public function get_usage_count(int $archetype_id, int $days = 0): int {
        try {
            $query = $this->db->prepare(
                "SELECT COUNT(*) FROM {$this->db->prefix}money_quiz_results WHERE archetype_id = %d",
                $archetype_id
            );
            
            if ($days > 0) {
                $since = gmdate('Y-m-d H:i:s', time() - ($days * DAY_IN_SECONDS));
                $query = $this->db->prepare(
                    "SELECT COUNT(*) FROM {$this->db->prefix}money_quiz_results 
                    WHERE archetype_id = %d AND calculated_at >= %s",
                    $archetype_id,
                    $since
                );
            }
            
            return (int) $this->db->get_var($query);
        } catch (\Exception $e) {
            $this->logger->error('ArchetypeRepository get_usage_count error', [
                'archetype_id' => $archetype_id,
                'days' => $days,
                'error' => $e->getMessage()
            ]);
            return 0;
        }
    }
    
    /**
     * Find archetypes by quiz.
     *
     * @since 7.0.0
     *
     * @param int $quiz_id Quiz ID.
     * @return array<Archetype> Archetypes used with quiz.
     */
    public function find_by_quiz(int $quiz_id): array {
        try {
            $results = $this->db->get_results(
                $this->db->prepare(
                    "SELECT DISTINCT a.* 
                    FROM {$this->table} a
                    INNER JOIN {$this->db->prefix}money_quiz_results r ON a.id = r.archetype_id
                    WHERE r.quiz_id = %d AND a.is_active = 1
                    ORDER BY a.order ASC, a.name ASC",
                    $quiz_id
                ),
                ARRAY_A
            );
            
            return array_map([$this, 'hydrate'], $results);
        } catch (\Exception $e) {
            $this->logger->error('ArchetypeRepository find_by_quiz error', [
                'quiz_id' => $quiz_id,
                'error' => $e->getMessage()
            ]);
            return [];
        }
    }
    
    /**
     * Find archetype by ID.
     *
     * @since 7.0.0
     *
     * @param int $id Archetype ID.
     * @return Archetype|null Archetype or null.
     */
    public function find_by_id(int $id): ?Archetype {
        try {
            $data = $this->find($id);
            return $data ? $this->hydrate($data) : null;
        } catch (\Exception $e) {
            $this->logger->error('ArchetypeRepository find_by_id error', [
                'id' => $id,
                'error' => $e->getMessage()
            ]);
            return null;
        }
    }
    
    /**
     * Find all archetypes.
     *
     * @since 7.0.0
     *
     * @param array $criteria Search criteria.
     * @return array<Archetype> All archetypes.
     */
    public function find_all(array $criteria = []): array {
        try {
            $query = $this->query();
            
            if (!empty($criteria['is_active'])) {
                $query->where('is_active', (int) $criteria['is_active']);
            }
            
            if (!empty($criteria['search'])) {
                $search = '%' . $this->db->esc_like($criteria['search']) . '%';
                $query->where(function($q) use ($search) {
                    $q->where('name', $search, 'LIKE')
                      ->orWhere('description', $search, 'LIKE');
                });
            }
            
            $order_by = $criteria['order_by'] ?? 'order';
            $order_dir = $criteria['order_dir'] ?? 'ASC';
            $query->orderBy($order_by, $order_dir);
            
            if (isset($criteria['limit'])) {
                $query->limit((int) $criteria['limit']);
                
                if (isset($criteria['offset'])) {
                    $query->offset((int) $criteria['offset']);
                }
            }
            
            $results = $query->get();
            return array_map([$this, 'hydrate'], $results);
        } catch (\Exception $e) {
            $this->logger->error('ArchetypeRepository find_all error', [
                'criteria' => $criteria,
                'error' => $e->getMessage()
            ]);
            return [];
        }
    }
    
    /**
     * Save archetype.
     *
     * @since 7.0.0
     *
     * @param Archetype $archetype Archetype to save.
     * @return bool True on success.
     */
    public function save(Archetype $archetype): bool {
        try {
            $data = $this->dehydrate($archetype);
            
            if ($archetype->get_id()) {
                return $this->update($archetype->get_id(), $data);
            } else {
                $id = $this->create($data);
                if ($id) {
                    $archetype->set_id($id);
                    return true;
                }
            }
            
            return false;
        } catch (\Exception $e) {
            $this->logger->error('ArchetypeRepository save error', [
                'archetype' => $archetype->to_array(),
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }
    
    /**
     * Delete archetype.
     *
     * @since 7.0.0
     *
     * @param int $id Archetype ID.
     * @return bool True on success.
     */
    public function delete_by_id(int $id): bool {
        try {
            // Check if archetype is in use
            $usage_count = $this->get_usage_count($id);
            if ($usage_count > 0) {
                // Soft delete by deactivating instead
                return $this->update($id, ['is_active' => 0]);
            }
            
            return $this->delete($id);
        } catch (\Exception $e) {
            $this->logger->error('ArchetypeRepository delete_by_id error', [
                'id' => $id,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }
    
    /**
     * Count archetypes.
     *
     * @since 7.0.0
     *
     * @param array $criteria Count criteria.
     * @return int Count.
     */
    public function count(array $criteria = []): int {
        try {
            $query = $this->query();
            
            if (!empty($criteria['is_active'])) {
                $query->where('is_active', (int) $criteria['is_active']);
            }
            
            return $query->count();
        } catch (\Exception $e) {
            $this->logger->error('ArchetypeRepository count error', [
                'criteria' => $criteria,
                'error' => $e->getMessage()
            ]);
            return 0;
        }
    }
    
    /**
     * Hydrate archetype from array.
     *
     * @since 7.0.0
     *
     * @param array $data Raw data.
     * @return Archetype Archetype entity.
     */
    private function hydrate(array $data): Archetype {
        // Decode JSON fields
        $data['characteristics'] = json_decode($data['characteristics'] ?? '[]', true) ?: [];
        $data['criteria'] = json_decode($data['criteria'] ?? '{}', true) ?: [];
        $data['recommendation_templates'] = json_decode($data['recommendation_templates'] ?? '[]', true) ?: [];
        
        return Archetype::from_array($data);
    }
    
    /**
     * Dehydrate archetype to array.
     *
     * @since 7.0.0
     *
     * @param Archetype $archetype Archetype entity.
     * @return array Raw data for storage.
     */
    private function dehydrate(Archetype $archetype): array {
        $data = $archetype->to_array();
        
        // Remove computed fields
        unset($data['id'], $data['created_at'], $data['updated_at']);
        
        // Encode JSON fields
        $data['characteristics'] = wp_json_encode($data['characteristics']);
        $data['criteria'] = wp_json_encode($data['criteria']);
        $data['recommendation_templates'] = wp_json_encode($data['recommendation_templates']);
        
        return $data;
    }
}
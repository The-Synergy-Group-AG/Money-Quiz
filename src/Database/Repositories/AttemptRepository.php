<?php
/**
 * Attempt Repository
 *
 * Handles attempt data persistence and retrieval.
 *
 * @package MoneyQuiz\Database\Repositories
 * @since   7.0.0
 */

namespace MoneyQuiz\Database\Repositories;

use MoneyQuiz\Database\AbstractRepository;
use MoneyQuiz\Domain\Repositories\AttemptRepository as AttemptRepositoryInterface;
use MoneyQuiz\Domain\Entities\Attempt;

// Security: Prevent direct access.
defined('ABSPATH') || exit;

/**
 * Attempt repository class.
 *
 * @since 7.0.0
 */
class AttemptRepository extends AbstractRepository implements AttemptRepositoryInterface {
    
    /**
     * Set table name.
     *
     * @since 7.0.0
     *
     * @return void
     */
    protected function set_table_name(): void {
        $this->table = $this->db->prefix . 'money_quiz_attempts';
    }
    
    /**
     * Find attempt by session token.
     *
     * @since 7.0.0
     *
     * @param string $token Session token.
     * @return Attempt|null Attempt or null.
     */
    public function find_by_session_token(string $token): ?Attempt {
        try {
            $data = $this->query()
                ->where('session_token', $token)
                ->first();
            
            return $data ? $this->hydrate($data) : null;
        } catch (\Exception $e) {
            $this->logger->error('AttemptRepository find_by_session_token error', [
                'token' => $token,
                'error' => $e->getMessage()
            ]);
            return null;
        }
    }
    
    /**
     * Find active attempts by user.
     *
     * @since 7.0.0
     *
     * @param int $user_id User ID.
     * @return array<Attempt> Active attempts.
     */
    public function find_active_by_user(int $user_id): array {
        try {
            $results = $this->query()
                ->where('user_id', $user_id)
                ->whereIn('status', ['started', 'in_progress'])
                ->orderBy('started_at', 'DESC')
                ->get();
            
            return array_map([$this, 'hydrate'], $results);
        } catch (\Exception $e) {
            $this->logger->error('AttemptRepository find_active_by_user error', [
                'user_id' => $user_id,
                'error' => $e->getMessage()
            ]);
            return [];
        }
    }
    
    /**
     * Find attempts by quiz.
     *
     * @since 7.0.0
     *
     * @param int   $quiz_id Quiz ID.
     * @param array $filters Optional filters.
     * @return array<Attempt> Attempts.
     */
    public function find_by_quiz(int $quiz_id, array $filters = []): array {
        try {
            $query = $this->query()
                ->where('quiz_id', $quiz_id);
            
            // Apply filters
            if (!empty($filters['status'])) {
                $query->where('status', $filters['status']);
            }
            
            if (!empty($filters['user_id'])) {
                $query->where('user_id', $filters['user_id']);
            }
            
            if (!empty($filters['date_from'])) {
                $query->where('started_at', $filters['date_from'], '>=');
            }
            
            if (!empty($filters['date_to'])) {
                $query->where('started_at', $filters['date_to'], '<=');
            }
            
            // Apply ordering
            $order_by = $filters['order_by'] ?? 'started_at';
            $order_dir = $filters['order_dir'] ?? 'DESC';
            $query->orderBy($order_by, $order_dir);
            
            // Apply pagination
            if (isset($filters['limit'])) {
                $query->limit((int) $filters['limit']);
                
                if (isset($filters['offset'])) {
                    $query->offset((int) $filters['offset']);
                }
            }
            
            $results = $query->get();
            return array_map([$this, 'hydrate'], $results);
        } catch (\Exception $e) {
            $this->logger->error('AttemptRepository find_by_quiz error', [
                'quiz_id' => $quiz_id,
                'filters' => $filters,
                'error' => $e->getMessage()
            ]);
            return [];
        }
    }
    
    /**
     * Find attempts by user.
     *
     * @since 7.0.0
     *
     * @param int   $user_id User ID.
     * @param array $filters Optional filters.
     * @return array<Attempt> Attempts.
     */
    public function find_by_user(int $user_id, array $filters = []): array {
        try {
            $query = $this->query()
                ->where('user_id', $user_id);
            
            // Apply filters
            if (!empty($filters['quiz_id'])) {
                $query->where('quiz_id', $filters['quiz_id']);
            }
            
            if (!empty($filters['status'])) {
                $query->where('status', $filters['status']);
            }
            
            // Apply ordering
            $order_by = $filters['order_by'] ?? 'started_at';
            $order_dir = $filters['order_dir'] ?? 'DESC';
            $query->orderBy($order_by, $order_dir);
            
            // Apply pagination
            if (isset($filters['limit'])) {
                $query->limit((int) $filters['limit']);
                
                if (isset($filters['offset'])) {
                    $query->offset((int) $filters['offset']);
                }
            }
            
            $results = $query->get();
            return array_map([$this, 'hydrate'], $results);
        } catch (\Exception $e) {
            $this->logger->error('AttemptRepository find_by_user error', [
                'user_id' => $user_id,
                'filters' => $filters,
                'error' => $e->getMessage()
            ]);
            return [];
        }
    }
    
    /**
     * Clean up abandoned attempts.
     *
     * @since 7.0.0
     *
     * @param int $hours Hours after which to consider abandoned.
     * @return int Number of attempts cleaned.
     */
    public function cleanup_abandoned(int $hours = 24): int {
        try {
            $cutoff = gmdate('Y-m-d H:i:s', time() - ($hours * HOUR_IN_SECONDS));
            
            // Update status to abandoned
            $affected = $this->db->update(
                $this->table,
                ['status' => 'abandoned', 'updated_at' => current_time('mysql')],
                [
                    'status' => ['started', 'in_progress'],
                    'started_at <' => $cutoff
                ]
            );
            
            if ($affected > 0) {
                $this->logger->info('Abandoned attempts cleaned up', [
                    'count' => $affected,
                    'hours' => $hours
                ]);
            }
            
            return $affected;
        } catch (\Exception $e) {
            $this->logger->error('AttemptRepository cleanup_abandoned error', [
                'hours' => $hours,
                'error' => $e->getMessage()
            ]);
            return 0;
        }
    }
    
    /**
     * Get attempt statistics.
     *
     * @since 7.0.0
     *
     * @param int    $quiz_id Quiz ID (0 for all).
     * @param string $period  Time period.
     * @return array Statistics.
     */
    public function get_statistics(int $quiz_id = 0, string $period = 'day'): array {
        try {
            // Determine date range
            switch ($period) {
                case 'hour':
                    $since = gmdate('Y-m-d H:i:s', time() - HOUR_IN_SECONDS);
                    break;
                case 'day':
                    $since = gmdate('Y-m-d H:i:s', time() - DAY_IN_SECONDS);
                    break;
                case 'week':
                    $since = gmdate('Y-m-d H:i:s', time() - WEEK_IN_SECONDS);
                    break;
                case 'month':
                    $since = gmdate('Y-m-d H:i:s', time() - MONTH_IN_SECONDS);
                    break;
                default:
                    $since = null;
            }
            
            $where_quiz = $quiz_id > 0 ? $this->db->prepare(' AND quiz_id = %d', $quiz_id) : '';
            $where_date = $since ? $this->db->prepare(' AND started_at >= %s', $since) : '';
            
            // Get statistics
            $stats = $this->db->get_row(
                "SELECT 
                    COUNT(*) as total_attempts,
                    COUNT(CASE WHEN status = 'completed' THEN 1 END) as completed,
                    COUNT(CASE WHEN status = 'abandoned' THEN 1 END) as abandoned,
                    COUNT(CASE WHEN status IN ('started', 'in_progress') THEN 1 END) as active,
                    COUNT(DISTINCT user_id) as unique_users,
                    COUNT(CASE WHEN user_id IS NULL THEN 1 END) as anonymous,
                    AVG(CASE WHEN status = 'completed' THEN time_taken END) as avg_time,
                    MIN(CASE WHEN status = 'completed' THEN time_taken END) as min_time,
                    MAX(CASE WHEN status = 'completed' THEN time_taken END) as max_time
                FROM {$this->table}
                WHERE 1=1 {$where_quiz} {$where_date}",
                ARRAY_A
            );
            
            // Calculate completion rate
            $stats['completion_rate'] = $stats['total_attempts'] > 0 
                ? round(($stats['completed'] / $stats['total_attempts']) * 100, 2)
                : 0;
            
            return $stats;
        } catch (\Exception $e) {
            $this->logger->error('AttemptRepository get_statistics error', [
                'quiz_id' => $quiz_id,
                'period' => $period,
                'error' => $e->getMessage()
            ]);
            return [];
        }
    }
    
    /**
     * Find attempt by ID.
     *
     * @since 7.0.0
     *
     * @param int $id Attempt ID.
     * @return Attempt|null Attempt or null.
     */
    public function find_by_id(int $id): ?Attempt {
        try {
            $data = $this->find($id);
            return $data ? $this->hydrate($data) : null;
        } catch (\Exception $e) {
            $this->logger->error('AttemptRepository find_by_id error', [
                'id' => $id,
                'error' => $e->getMessage()
            ]);
            return null;
        }
    }
    
    /**
     * Find all attempts.
     *
     * @since 7.0.0
     *
     * @param array $criteria Search criteria.
     * @return array<Attempt> All attempts.
     */
    public function find_all(array $criteria = []): array {
        try {
            $query = $this->query();
            
            // Apply criteria
            foreach ($criteria as $field => $value) {
                if (in_array($field, ['quiz_id', 'user_id', 'status'])) {
                    $query->where($field, $value);
                }
            }
            
            $results = $query->get();
            return array_map([$this, 'hydrate'], $results);
        } catch (\Exception $e) {
            $this->logger->error('AttemptRepository find_all error', [
                'criteria' => $criteria,
                'error' => $e->getMessage()
            ]);
            return [];
        }
    }
    
    /**
     * Save attempt.
     *
     * @since 7.0.0
     *
     * @param Attempt $attempt Attempt to save.
     * @return bool True on success.
     */
    public function save(Attempt $attempt): bool {
        try {
            $data = $this->dehydrate($attempt);
            
            if ($attempt->get_id()) {
                return $this->update($attempt->get_id(), $data);
            } else {
                $id = $this->create($data);
                if ($id) {
                    $attempt->set_id($id);
                    return true;
                }
            }
            
            return false;
        } catch (\Exception $e) {
            $this->logger->error('AttemptRepository save error', [
                'attempt' => $attempt->to_array(),
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }
    
    /**
     * Delete attempt.
     *
     * @since 7.0.0
     *
     * @param int $id Attempt ID.
     * @return bool True on success.
     */
    public function delete_by_id(int $id): bool {
        try {
            return $this->delete($id);
        } catch (\Exception $e) {
            $this->logger->error('AttemptRepository delete_by_id error', [
                'id' => $id,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }
    
    /**
     * Count attempts.
     *
     * @since 7.0.0
     *
     * @param array $criteria Count criteria.
     * @return int Count.
     */
    public function count(array $criteria = []): int {
        try {
            $query = $this->query();
            
            // Apply criteria
            foreach ($criteria as $field => $value) {
                $query->where($field, $value);
            }
            
            return $query->count();
        } catch (\Exception $e) {
            $this->logger->error('AttemptRepository count error', [
                'criteria' => $criteria,
                'error' => $e->getMessage()
            ]);
            return 0;
        }
    }
    
    /**
     * Hydrate attempt from array.
     *
     * @since 7.0.0
     *
     * @param array $data Raw data.
     * @return Attempt Attempt entity.
     */
    private function hydrate(array $data): Attempt {
        // Decode JSON fields
        $data['answers'] = json_decode($data['answers'] ?? '[]', true) ?: [];
        $data['questions'] = json_decode($data['questions'] ?? '[]', true) ?: [];
        $data['metadata'] = json_decode($data['metadata'] ?? '{}', true) ?: [];
        
        return Attempt::from_array($data);
    }
    
    /**
     * Dehydrate attempt to array.
     *
     * @since 7.0.0
     *
     * @param Attempt $attempt Attempt entity.
     * @return array Raw data for storage.
     */
    private function dehydrate(Attempt $attempt): array {
        $data = $attempt->to_array();
        
        // Remove computed fields
        unset($data['id'], $data['created_at'], $data['updated_at']);
        
        // Encode JSON fields
        $data['answers'] = wp_json_encode($data['answers']);
        $data['questions'] = wp_json_encode($data['questions']);
        $data['metadata'] = wp_json_encode($data['metadata']);
        
        return $data;
    }
}
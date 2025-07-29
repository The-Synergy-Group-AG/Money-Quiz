<?php
/**
 * Quiz Repository
 *
 * @package MoneyQuiz
 * @since 4.0.0
 */

namespace MoneyQuiz\Database\Repositories;

use MoneyQuiz\Models\Quiz;

/**
 * Repository for quiz data access
 */
class QuizRepository extends BaseRepository {
    
    /**
     * @var string Table name
     */
    protected string $table = 'money_quiz_quizzes';
    
    /**
     * Get table name
     * 
     * @return string
     */
    protected function get_table_name(): string {
        return $this->db->prefix . $this->table;
    }
    
    /**
     * Get active quizzes
     * 
     * @param array $args Query arguments
     * @return array
     */
    public function get_active( array $args = [] ): array {
        $defaults = [
            'orderby' => 'created_at',
            'order' => 'DESC',
        ];
        
        $args = wp_parse_args( $args, $defaults );
        
        return $this->where( [ 'is_active' => 1 ], $args );
    }
    
    /**
     * Find quiz by slug
     * 
     * @param string $slug Quiz slug
     * @return object|null
     */
    public function find_by_slug( string $slug ): ?object {
        return $this->find_by( 'slug', $slug );
    }
    
    /**
     * Get quiz with questions
     * 
     * @param int $quiz_id Quiz ID
     * @return object|null
     */
    public function get_with_questions( int $quiz_id ): ?object {
        $quiz = $this->find( $quiz_id );
        
        if ( ! $quiz ) {
            return null;
        }
        
        // Get questions
        $questions_table = $this->db->prefix . 'money_quiz_questions';
        $query = $this->db->prepare(
            "SELECT * FROM {$questions_table} 
            WHERE quiz_id = %d AND is_active = 1 
            ORDER BY sort_order ASC, id ASC",
            $quiz_id
        );
        
        $quiz->questions = $this->db->get_results( $query );
        
        // Decode JSON fields
        foreach ( $quiz->questions as &$question ) {
            $question->options = json_decode( $question->options, true ) ?: [];
            $question->archetype_weights = json_decode( $question->archetype_weights, true ) ?: [];
        }
        
        // Decode quiz settings
        $quiz->settings = json_decode( $quiz->settings, true ) ?: [];
        
        return $quiz;
    }
    
    /**
     * Create a new quiz
     * 
     * @param array $data Quiz data
     * @return int|false Insert ID or false
     */
    public function create( array $data ) {
        // Ensure slug is unique
        if ( isset( $data['slug'] ) ) {
            $data['slug'] = $this->generate_unique_slug( $data['slug'] );
        }
        
        // Encode settings if array
        if ( isset( $data['settings'] ) && is_array( $data['settings'] ) ) {
            $data['settings'] = json_encode( $data['settings'] );
        }
        
        return $this->insert( $data );
    }
    
    /**
     * Update quiz
     * 
     * @param int   $id   Quiz ID
     * @param array $data Quiz data
     * @return bool
     */
    public function update_quiz( int $id, array $data ): bool {
        // Encode settings if array
        if ( isset( $data['settings'] ) && is_array( $data['settings'] ) ) {
            $data['settings'] = json_encode( $data['settings'] );
        }
        
        // Update timestamp
        $data['updated_at'] = current_time( 'mysql' );
        
        return $this->update( $id, $data );
    }
    
    /**
     * Duplicate a quiz
     * 
     * @param int $quiz_id Quiz ID to duplicate
     * @return int|false New quiz ID or false
     */
    public function duplicate( int $quiz_id ) {
        $quiz = $this->find( $quiz_id );
        
        if ( ! $quiz ) {
            return false;
        }
        
        // Prepare data for new quiz
        $data = [
            'title' => $quiz->title . ' (Copy)',
            'slug' => $this->generate_unique_slug( $quiz->slug . '-copy' ),
            'description' => $quiz->description,
            'settings' => $quiz->settings,
            'is_active' => 0, // Start as inactive
        ];
        
        $new_quiz_id = $this->insert( $data );
        
        if ( ! $new_quiz_id ) {
            return false;
        }
        
        // Duplicate questions
        $questions_table = $this->db->prefix . 'money_quiz_questions';
        $query = $this->db->prepare(
            "INSERT INTO {$questions_table} 
            (quiz_id, question, question_type, options, archetype_weights, sort_order, is_required, is_active)
            SELECT %d, question, question_type, options, archetype_weights, sort_order, is_required, is_active
            FROM {$questions_table}
            WHERE quiz_id = %d",
            $new_quiz_id,
            $quiz_id
        );
        
        $this->db->query( $query );
        
        return $new_quiz_id;
    }
    
    /**
     * Get quiz statistics
     * 
     * @param int $quiz_id Quiz ID
     * @return array
     */
    public function get_statistics( int $quiz_id ): array {
        $results_table = $this->db->prefix . 'money_quiz_results';
        
        // Total completions
        $total = $this->db->get_var( $this->db->prepare(
            "SELECT COUNT(*) FROM {$results_table} WHERE quiz_id = %d",
            $quiz_id
        ) );
        
        // Completions by date
        $by_date = $this->db->get_results( $this->db->prepare(
            "SELECT DATE(completed_at) as date, COUNT(*) as count 
            FROM {$results_table} 
            WHERE quiz_id = %d 
            AND completed_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
            GROUP BY DATE(completed_at)
            ORDER BY date ASC",
            $quiz_id
        ) );
        
        // Archetype distribution
        $archetypes_table = $this->db->prefix . 'money_quiz_archetypes';
        $archetype_dist = $this->db->get_results( $this->db->prepare(
            "SELECT a.name, a.slug, COUNT(r.id) as count
            FROM {$results_table} r
            JOIN {$archetypes_table} a ON r.archetype_id = a.id
            WHERE r.quiz_id = %d
            GROUP BY r.archetype_id
            ORDER BY count DESC",
            $quiz_id
        ) );
        
        // Average score
        $avg_score = $this->db->get_var( $this->db->prepare(
            "SELECT AVG(score) FROM {$results_table} WHERE quiz_id = %d",
            $quiz_id
        ) );
        
        return [
            'total_completions' => (int) $total,
            'completions_by_date' => $by_date,
            'archetype_distribution' => $archetype_dist,
            'average_score' => round( (float) $avg_score, 2 ),
        ];
    }
    
    /**
     * Generate unique slug
     * 
     * @param string $slug Base slug
     * @return string Unique slug
     */
    private function generate_unique_slug( string $slug ): string {
        $original_slug = $slug;
        $counter = 1;
        
        while ( $this->find_by( 'slug', $slug ) ) {
            $slug = $original_slug . '-' . $counter;
            $counter++;
        }
        
        return $slug;
    }
}
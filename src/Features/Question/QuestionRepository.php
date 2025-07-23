<?php
declare(strict_types=1);

namespace MoneyQuiz\Features\Question;

use MoneyQuiz\Database\AbstractRepository;
use MoneyQuiz\Domain\Entities\Question;

/**
 * Repository for question data access
 */
class QuestionRepository extends AbstractRepository
{
    protected string $table = 'mq_questions';
    protected string $entityClass = Question::class;

    /**
     * Find questions by quiz ID
     */
    public function findByQuizId(int $quizId): array
    {
        return $this->queryBuilder
            ->select('*')
            ->where('quiz_id', '=', $quizId)
            ->orderBy('order', 'ASC')
            ->get();
    }

    /**
     * Get maximum order for quiz
     */
    public function getMaxOrder(int $quizId): int
    {
        $result = $this->queryBuilder
            ->select('MAX(order) as max_order')
            ->where('quiz_id', '=', $quizId)
            ->first();
            
        return (int) ($result['max_order'] ?? 0);
    }

    /**
     * Update question order
     */
    public function updateOrder(int $quizId, array $order): void
    {
        foreach ($order as $position => $questionId) {
            $this->queryBuilder
                ->where('id', '=', $questionId)
                ->where('quiz_id', '=', $quizId)
                ->update(['order' => $position]);
        }
    }

    /**
     * Reindex question order
     */
    public function reindex(int $quizId): void
    {
        $questions = $this->findByQuizId($quizId);
        
        foreach ($questions as $index => $question) {
            $this->update($question->getId(), ['order' => $index + 1]);
        }
    }

    /**
     * Delete questions by quiz ID
     */
    public function deleteByQuizId(int $quizId): int
    {
        return $this->queryBuilder
            ->where('quiz_id', '=', $quizId)
            ->delete();
    }

    /**
     * Count questions by quiz ID
     */
    public function countByQuizId(int $quizId): int
    {
        $result = $this->queryBuilder
            ->select('COUNT(*) as count')
            ->where('quiz_id', '=', $quizId)
            ->first();
            
        return (int) ($result['count'] ?? 0);
    }

    /**
     * Find questions by category
     */
    public function findByCategory(int $quizId, string $category): array
    {
        return $this->queryBuilder
            ->select('*')
            ->where('quiz_id', '=', $quizId)
            ->where('category', '=', $category)
            ->orderBy('order', 'ASC')
            ->get();
    }

    /**
     * Override create to handle options
     */
    public function create(array $data): Question
    {
        if (isset($data['options'])) {
            $data['options'] = json_encode($data['options']);
        }
        
        if (isset($data['tags'])) {
            $data['tags'] = json_encode($data['tags']);
        }
        
        return parent::create($data);
    }

    /**
     * Override update to handle options
     */
    public function update(int $id, array $data): ?Question
    {
        if (isset($data['options'])) {
            $data['options'] = json_encode($data['options']);
        }
        
        if (isset($data['tags'])) {
            $data['tags'] = json_encode($data['tags']);
        }
        
        return parent::update($id, $data);
    }

    /**
     * Override hydrate to handle JSON fields
     */
    protected function hydrate(array $row): Question
    {
        if (isset($row['options'])) {
            $row['options'] = json_decode($row['options'], true) ?: [];
        }
        
        if (isset($row['tags'])) {
            $row['tags'] = json_decode($row['tags'], true) ?: [];
        }
        
        return parent::hydrate($row);
    }
}
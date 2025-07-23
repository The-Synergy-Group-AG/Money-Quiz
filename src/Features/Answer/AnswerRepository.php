<?php
declare(strict_types=1);

namespace MoneyQuiz\Features\Answer;

use MoneyQuiz\Database\AbstractRepository;
use MoneyQuiz\Domain\ValueObjects\Answer;
use MoneyQuiz\Database\QueryBuilder;

/**
 * Repository for answer data storage
 */
class AnswerRepository extends AbstractRepository
{
    protected string $table = 'mq_answers';

    /**
     * Save answer for attempt
     */
    public function save(int $attemptId, Answer $answer): Answer
    {
        $data = [
            'attempt_id' => $attemptId,
            'question_id' => $answer->getQuestionId(),
            'answer_value' => $this->serializeValue($answer->getValue()),
            'answered_at' => $answer->getTimestamp()
        ];

        $existing = $this->findByAttemptAndQuestion($attemptId, $answer->getQuestionId());
        
        if ($existing) {
            $this->queryBuilder
                ->where('attempt_id', '=', $attemptId)
                ->where('question_id', '=', $answer->getQuestionId())
                ->update($data);
        } else {
            $this->queryBuilder->insert($data);
        }

        return $answer;
    }

    /**
     * Update answer
     */
    public function update(int $attemptId, Answer $answer): Answer
    {
        return $this->save($attemptId, $answer);
    }

    /**
     * Find answers by attempt ID
     */
    public function findByAttemptId(int $attemptId): array
    {
        $rows = $this->queryBuilder
            ->select('*')
            ->where('attempt_id', '=', $attemptId)
            ->orderBy('answered_at', 'ASC')
            ->get();

        return array_map([$this, 'hydrateAnswer'], $rows);
    }

    /**
     * Find answer by attempt and question
     */
    public function findByAttemptAndQuestion(int $attemptId, int $questionId): ?Answer
    {
        $row = $this->queryBuilder
            ->select('*')
            ->where('attempt_id', '=', $attemptId)
            ->where('question_id', '=', $questionId)
            ->first();

        return $row ? $this->hydrateAnswer($row) : null;
    }

    /**
     * Delete answers by attempt ID
     */
    public function deleteByAttemptId(int $attemptId): int
    {
        return $this->queryBuilder
            ->where('attempt_id', '=', $attemptId)
            ->delete();
    }

    /**
     * Count answers by attempt ID
     */
    public function countByAttemptId(int $attemptId): int
    {
        $result = $this->queryBuilder
            ->select('COUNT(*) as count')
            ->where('attempt_id', '=', $attemptId)
            ->first();

        return (int) ($result['count'] ?? 0);
    }

    /**
     * Get latest answer timestamp for attempt
     */
    public function getLatestAnswerTime(int $attemptId): ?string
    {
        $result = $this->queryBuilder
            ->select('MAX(answered_at) as latest')
            ->where('attempt_id', '=', $attemptId)
            ->first();

        return $result['latest'] ?? null;
    }

    /**
     * Serialize answer value for storage
     */
    private function serializeValue($value): string
    {
        if (is_array($value)) {
            return json_encode($value);
        }
        
        return (string) $value;
    }

    /**
     * Deserialize answer value from storage
     */
    private function deserializeValue(string $value)
    {
        $decoded = json_decode($value, true);
        
        return json_last_error() === JSON_ERROR_NONE ? $decoded : $value;
    }

    /**
     * Hydrate Answer value object
     */
    private function hydrateAnswer(array $row): Answer
    {
        return new Answer(
            (int) $row['question_id'],
            $this->deserializeValue($row['answer_value']),
            $row['answered_at']
        );
    }
}
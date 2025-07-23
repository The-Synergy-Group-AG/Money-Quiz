<?php
declare(strict_types=1);

namespace MoneyQuiz\Features\Question;

/**
 * Handles question duplication for quiz copying
 */
class QuestionDuplicator
{
    public function __construct(
        private QuestionRepository $repository
    ) {}

    /**
     * Duplicate all questions from one quiz to another
     */
    public function duplicateQuestions(int $sourceQuizId, int $targetQuizId): array
    {
        $questions = $this->repository->findByQuizId($sourceQuizId);
        $duplicated = [];
        
        foreach ($questions as $question) {
            $questionData = [
                'quiz_id' => $targetQuizId,
                'type' => $question->getType(),
                'text' => $question->getText(),
                'description' => $question->getDescription(),
                'points' => $question->getPoints(),
                'required' => $question->isRequired(),
                'options' => $question->getOptions(),
                'correct_answer' => $question->getCorrectAnswer(),
                'feedback' => $question->getFeedback(),
                'category' => $question->getCategory(),
                'tags' => $question->getTags(),
                'order' => $question->getOrder(),
                'created_at' => current_time('mysql'),
                'updated_at' => current_time('mysql')
            ];
            
            $duplicated[] = $this->repository->create($questionData);
        }
        
        return $duplicated;
    }
}
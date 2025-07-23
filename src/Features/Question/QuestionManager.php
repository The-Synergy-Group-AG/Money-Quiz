<?php
declare(strict_types=1);

namespace MoneyQuiz\Features\Question;

use MoneyQuiz\Domain\Entities\Question;
use MoneyQuiz\Domain\Repositories\QuizRepository;
use MoneyQuiz\Security\InputValidator;
use MoneyQuiz\Security\Authorization\Authorizer;
use MoneyQuiz\Application\Exceptions\ServiceException;
use MoneyQuiz\Features\Question\Types\QuestionTypeFactory;

/**
 * Manages question operations with type support
 */
class QuestionManager
{
    public function __construct(
        private QuizRepository $quizRepository,
        private QuestionRepository $questionRepository,
        private InputValidator $validator,
        private Authorizer $authorizer,
        private QuestionValidator $questionValidator,
        private QuestionTypeFactory $typeFactory
    ) {}

    /**
     * Add question to quiz
     */
    public function addQuestion(int $quizId, array $data, int $userId): Question
    {
        $quiz = $this->quizRepository->findById($quizId);
        
        if (!$quiz) {
            throw new ServiceException('Quiz not found');
        }

        if (!$this->authorizer->can('edit_quiz', $userId, $quiz)) {
            throw new ServiceException('Unauthorized to edit quiz');
        }

        $validated = $this->questionValidator->validate($data);
        $questionType = $this->typeFactory->create($validated['type']);
        
        return $this->questionRepository->create(array_merge($validated, [
            'quiz_id' => $quizId,
            'order' => $this->getNextOrder($quizId),
            'created_at' => current_time('mysql'),
            'updated_at' => current_time('mysql')
        ]));
    }

    /**
     * Update existing question
     */
    public function updateQuestion(int $questionId, array $data, int $userId): Question
    {
        $question = $this->questionRepository->findById($questionId);
        
        if (!$question) {
            throw new ServiceException('Question not found');
        }

        $quiz = $this->quizRepository->findById($question->getQuizId());
        
        if (!$this->authorizer->can('edit_quiz', $userId, $quiz)) {
            throw new ServiceException('Unauthorized to edit question');
        }

        $validated = $this->questionValidator->validate($data, false);
        
        return $this->questionRepository->update($questionId, array_merge($validated, [
            'updated_at' => current_time('mysql')
        ]));
    }

    /**
     * Delete question
     */
    public function deleteQuestion(int $questionId, int $userId): bool
    {
        $question = $this->questionRepository->findById($questionId);
        
        if (!$question) {
            throw new ServiceException('Question not found');
        }

        $quiz = $this->quizRepository->findById($question->getQuizId());
        
        if (!$this->authorizer->can('edit_quiz', $userId, $quiz)) {
            throw new ServiceException('Unauthorized to delete question');
        }

        $result = $this->questionRepository->delete($questionId);
        
        if ($result) {
            $this->reorderQuestions($question->getQuizId());
        }
        
        return $result;
    }

    /**
     * Reorder questions
     */
    public function reorderQuestions(int $quizId, array $order = null, int $userId = null): void
    {
        if ($userId) {
            $quiz = $this->quizRepository->findById($quizId);
            
            if (!$this->authorizer->can('edit_quiz', $userId, $quiz)) {
                throw new ServiceException('Unauthorized to reorder questions');
            }
        }

        if ($order) {
            $this->questionRepository->updateOrder($quizId, $order);
        } else {
            $this->questionRepository->reindex($quizId);
        }
    }

    /**
     * Get next order position
     */
    private function getNextOrder(int $quizId): int
    {
        return $this->questionRepository->getMaxOrder($quizId) + 1;
    }

    /**
     * Get questions for quiz
     */
    public function getQuizQuestions(int $quizId, int $userId): array
    {
        $quiz = $this->quizRepository->findById($quizId);
        
        if (!$quiz) {
            throw new ServiceException('Quiz not found');
        }

        if (!$this->authorizer->can('view_quiz', $userId, $quiz)) {
            throw new ServiceException('Unauthorized to view questions');
        }

        return $this->questionRepository->findByQuizId($quizId);
    }
}
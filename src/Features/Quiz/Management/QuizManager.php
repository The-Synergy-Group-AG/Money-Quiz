<?php
declare(strict_types=1);

namespace MoneyQuiz\Features\Quiz\Management;

use MoneyQuiz\Application\Services\QuizService;
use MoneyQuiz\Domain\Entities\Quiz;
use MoneyQuiz\Domain\Repositories\QuizRepository;
use MoneyQuiz\Security\InputValidator;
use MoneyQuiz\Security\Authorization\Authorizer;
use MoneyQuiz\Application\Exceptions\ServiceException;

/**
 * Manages quiz operations with security and validation
 */
class QuizManager
{
    public function __construct(
        private QuizService $quizService,
        private QuizRepository $repository,
        private InputValidator $validator,
        private Authorizer $authorizer,
        private QuizValidator $quizValidator,
        private QuizDuplicator $duplicator
    ) {}

    /**
     * Create a new quiz
     */
    public function createQuiz(array $data, int $userId): Quiz
    {
        if (!$this->authorizer->can('create_quiz', $userId)) {
            throw new ServiceException('Unauthorized to create quiz');
        }

        $validated = $this->quizValidator->validateCreate($data);
        
        return $this->quizService->createQuiz(array_merge($validated, [
            'author_id' => $userId,
            'created_at' => current_time('mysql'),
            'updated_at' => current_time('mysql')
        ]));
    }

    /**
     * Update existing quiz
     */
    public function updateQuiz(int $quizId, array $data, int $userId): Quiz
    {
        $quiz = $this->repository->findById($quizId);
        
        if (!$quiz) {
            throw new ServiceException('Quiz not found');
        }

        if (!$this->authorizer->can('edit_quiz', $userId, $quiz)) {
            throw new ServiceException('Unauthorized to edit quiz');
        }

        $validated = $this->quizValidator->validateUpdate($data);
        
        return $this->quizService->updateQuiz($quizId, array_merge($validated, [
            'updated_at' => current_time('mysql')
        ]));
    }

    /**
     * Delete a quiz
     */
    public function deleteQuiz(int $quizId, int $userId): bool
    {
        $quiz = $this->repository->findById($quizId);
        
        if (!$quiz) {
            throw new ServiceException('Quiz not found');
        }

        if (!$this->authorizer->can('delete_quiz', $userId, $quiz)) {
            throw new ServiceException('Unauthorized to delete quiz');
        }

        return $this->repository->delete($quizId);
    }

    /**
     * Duplicate a quiz
     */
    public function duplicateQuiz(int $quizId, int $userId): Quiz
    {
        $quiz = $this->repository->findById($quizId);
        
        if (!$quiz) {
            throw new ServiceException('Quiz not found');
        }

        if (!$this->authorizer->can('duplicate_quiz', $userId, $quiz)) {
            throw new ServiceException('Unauthorized to duplicate quiz');
        }

        return $this->duplicator->duplicate($quiz, $userId);
    }

    /**
     * Get quiz by ID
     */
    public function getQuiz(int $quizId, int $userId): ?Quiz
    {
        $quiz = $this->repository->findById($quizId);
        
        if (!$quiz) {
            return null;
        }

        if (!$this->authorizer->can('view_quiz', $userId, $quiz)) {
            throw new ServiceException('Unauthorized to view quiz');
        }

        return $quiz;
    }

    /**
     * List quizzes with pagination
     */
    public function listQuizzes(array $filters, int $userId): array
    {
        if (!$this->authorizer->can('list_quizzes', $userId)) {
            throw new ServiceException('Unauthorized to list quizzes');
        }

        $validated = $this->validator->validateArray($filters, [
            'page' => ['type' => 'integer', 'min' => 1],
            'per_page' => ['type' => 'integer', 'min' => 1, 'max' => 100],
            'status' => ['type' => 'string', 'in' => ['draft', 'published', 'archived']],
            'search' => ['type' => 'string', 'max_length' => 100]
        ]);

        return $this->repository->findWithFilters($validated);
    }
}
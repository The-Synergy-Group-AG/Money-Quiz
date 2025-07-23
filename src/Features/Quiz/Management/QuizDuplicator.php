<?php
declare(strict_types=1);

namespace MoneyQuiz\Features\Quiz\Management;

use MoneyQuiz\Domain\Entities\Quiz;
use MoneyQuiz\Domain\Repositories\QuizRepository;
use MoneyQuiz\Application\Services\QuizService;
use MoneyQuiz\Application\Exceptions\ServiceException;

/**
 * Handles quiz duplication with all related data
 */
class QuizDuplicator
{
    public function __construct(
        private QuizService $quizService,
        private QuizRepository $repository,
        private QuestionDuplicator $questionDuplicator
    ) {}

    /**
     * Duplicate a quiz with all questions and settings
     */
    public function duplicate(Quiz $originalQuiz, int $userId): Quiz
    {
        try {
            // Prepare new quiz data
            $quizData = $this->prepareQuizData($originalQuiz, $userId);
            
            // Create the new quiz
            $newQuiz = $this->quizService->createQuiz($quizData);
            
            // Duplicate questions if any
            if ($originalQuiz->hasQuestions()) {
                $this->questionDuplicator->duplicateQuestions(
                    $originalQuiz->getId(),
                    $newQuiz->getId()
                );
            }
            
            // Copy quiz settings
            $this->copyQuizSettings($originalQuiz, $newQuiz);
            
            return $newQuiz;
            
        } catch (\Exception $e) {
            throw new ServiceException('Failed to duplicate quiz: ' . $e->getMessage());
        }
    }

    /**
     * Prepare quiz data for duplication
     */
    private function prepareQuizData(Quiz $quiz, int $userId): array
    {
        $title = $this->generateUniqueTitle($quiz->getTitle());
        
        return [
            'title' => $title,
            'description' => $quiz->getDescription(),
            'quiz_type' => $quiz->getType(),
            'settings' => $quiz->getSettings()->toArray(),
            'time_limit' => $quiz->getTimeLimit(),
            'passing_score' => $quiz->getPassingScore(),
            'randomize_questions' => $quiz->shouldRandomizeQuestions(),
            'show_results' => $quiz->shouldShowResults(),
            'require_registration' => $quiz->requiresRegistration(),
            'multi_step' => $quiz->isMultiStep(),
            'archive_tagline' => $quiz->getArchiveTagline(),
            'author_id' => $userId,
            'status' => 'draft',
            'created_at' => current_time('mysql'),
            'updated_at' => current_time('mysql')
        ];
    }

    /**
     * Generate unique title for duplicated quiz
     */
    private function generateUniqueTitle(string $originalTitle): string
    {
        $baseTitle = preg_replace('/ \(Copy( \d+)?\)$/', '', $originalTitle);
        $copyNumber = 1;
        $newTitle = $baseTitle . ' (Copy)';
        
        while ($this->repository->findByTitle($newTitle)) {
            $copyNumber++;
            $newTitle = $baseTitle . ' (Copy ' . $copyNumber . ')';
        }
        
        return $newTitle;
    }

    /**
     * Copy additional quiz settings
     */
    private function copyQuizSettings(Quiz $original, Quiz $new): void
    {
        // Copy any additional metadata
        $metadata = [
            'duplicated_from' => $original->getId(),
            'duplicated_at' => current_time('mysql')
        ];
        
        $this->repository->updateMetadata($new->getId(), $metadata);
    }
}
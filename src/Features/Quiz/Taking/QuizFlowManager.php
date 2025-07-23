<?php
declare(strict_types=1);

namespace MoneyQuiz\Features\Quiz\Taking;

use MoneyQuiz\Domain\Entities\Attempt;
use MoneyQuiz\Domain\Entities\Quiz;
use MoneyQuiz\Features\Question\QuestionRepository;
use MoneyQuiz\Features\Answer\AnswerRepository;
use MoneyQuiz\Features\Quiz\Display\ProgressTracker;

/**
 * Manages quiz navigation flow
 */
class QuizFlowManager
{
    public function __construct(
        private QuestionRepository $questionRepository,
        private AnswerRepository $answerRepository,
        private ProgressTracker $progressTracker
    ) {}

    /**
     * Get current page/question for attempt
     */
    public function getCurrentPage(Attempt $attempt): int
    {
        // Get next unanswered question
        $nextQuestion = $this->progressTracker->getNextQuestion($attempt);
        
        if ($nextQuestion !== null) {
            return $nextQuestion;
        }
        
        // If all answered, go to last question
        $questionCount = $this->questionRepository->countByQuizId($attempt->getQuizId());
        return max(1, $questionCount);
    }

    /**
     * Navigate to next/previous page
     */
    public function navigate(Attempt $attempt, string $direction): int
    {
        $currentPage = $this->getCurrentPage($attempt);
        $totalQuestions = $this->questionRepository->countByQuizId($attempt->getQuizId());
        
        switch ($direction) {
            case 'next':
                return min($currentPage + 1, $totalQuestions);
                
            case 'previous':
                return max($currentPage - 1, 1);
                
            case 'first':
                return 1;
                
            case 'last':
                return $totalQuestions;
                
            default:
                return $currentPage;
        }
    }

    /**
     * Check if can navigate in direction
     */
    public function canNavigate(Quiz $quiz, Attempt $attempt, string $direction): bool
    {
        $settings = $quiz->getSettings();
        $currentPage = $this->getCurrentPage($attempt);
        $totalQuestions = $this->questionRepository->countByQuizId($quiz->getId());
        
        switch ($direction) {
            case 'next':
                return $currentPage < $totalQuestions;
                
            case 'previous':
                return $currentPage > 1 && ($settings['display_options']['allow_back'] ?? false);
                
            default:
                return false;
        }
    }

    /**
     * Get dynamic flow based on answers
     */
    public function getDynamicFlow(Attempt $attempt): array
    {
        // This can be extended for conditional logic
        // For now, return linear flow
        $questions = $this->questionRepository->findByQuizId($attempt->getQuizId());
        
        return array_map(fn($q) => $q->getId(), $questions);
    }

    /**
     * Should skip question based on conditions
     */
    public function shouldSkipQuestion(Attempt $attempt, int $questionId): bool
    {
        // This can be extended for conditional logic
        // For now, no questions are skipped
        return false;
    }
}
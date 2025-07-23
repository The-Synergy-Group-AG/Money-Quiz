<?php
declare(strict_types=1);

namespace MoneyQuiz\Features\Quiz\Display;

use MoneyQuiz\Domain\Entities\Attempt;
use MoneyQuiz\Features\Answer\AnswerRepository;
use MoneyQuiz\Features\Question\QuestionRepository;

/**
 * Tracks quiz progress
 */
class ProgressTracker
{
    public function __construct(
        private AnswerRepository $answerRepository,
        private QuestionRepository $questionRepository
    ) {}

    /**
     * Get progress data for attempt
     */
    public function getProgress(Attempt $attempt): array
    {
        $totalQuestions = $this->questionRepository->countByQuizId($attempt->getQuizId());
        $answeredQuestions = $this->answerRepository->countByAttemptId($attempt->getId());
        
        $percentage = $totalQuestions > 0 
            ? round(($answeredQuestions / $totalQuestions) * 100) 
            : 0;

        return [
            'total' => $totalQuestions,
            'completed' => $answeredQuestions,
            'remaining' => $totalQuestions - $answeredQuestions,
            'percentage' => $percentage,
            'is_complete' => $answeredQuestions >= $totalQuestions
        ];
    }

    /**
     * Get next unanswered question
     */
    public function getNextQuestion(Attempt $attempt): ?int
    {
        $questions = $this->questionRepository->findByQuizId($attempt->getQuizId());
        $answers = $this->answerRepository->findByAttemptId($attempt->getId());
        
        $answeredQuestionIds = array_map(fn($a) => $a->getQuestionId(), $answers);
        
        foreach ($questions as $question) {
            if (!in_array($question->getId(), $answeredQuestionIds)) {
                return $question->getOrder();
            }
        }
        
        return null;
    }

    /**
     * Get question completion status
     */
    public function getQuestionStatus(Attempt $attempt): array
    {
        $questions = $this->questionRepository->findByQuizId($attempt->getQuizId());
        $answers = $this->answerRepository->findByAttemptId($attempt->getId());
        
        $answeredQuestionIds = array_map(fn($a) => $a->getQuestionId(), $answers);
        $status = [];
        
        foreach ($questions as $question) {
            $status[$question->getId()] = [
                'answered' => in_array($question->getId(), $answeredQuestionIds),
                'required' => $question->isRequired(),
                'order' => $question->getOrder()
            ];
        }
        
        return $status;
    }

    /**
     * Check if can proceed to next question
     */
    public function canProceed(Attempt $attempt, int $currentQuestionId): bool
    {
        $question = $this->questionRepository->findById($currentQuestionId);
        
        if (!$question || !$question->isRequired()) {
            return true;
        }
        
        $answer = $this->answerRepository->findByAttemptAndQuestion(
            $attempt->getId(), 
            $currentQuestionId
        );
        
        return $answer !== null;
    }
}
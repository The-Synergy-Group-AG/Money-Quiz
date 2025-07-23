<?php
declare(strict_types=1);

namespace MoneyQuiz\Features\Quiz\Taking;

use MoneyQuiz\Domain\Entities\Quiz;
use MoneyQuiz\Domain\Entities\Attempt;
use MoneyQuiz\Domain\Repositories\QuizRepository;
use MoneyQuiz\Application\Services\AttemptService;
use MoneyQuiz\Features\Answer\AnswerManager;
use MoneyQuiz\Features\Quiz\Display\TimerManager;
use MoneyQuiz\Security\NonceManager;
use MoneyQuiz\Application\Exceptions\ServiceException;

/**
 * Manages the quiz taking process
 */
class QuizTaker
{
    public function __construct(
        private QuizRepository $quizRepository,
        private AttemptService $attemptService,
        private AnswerManager $answerManager,
        private TimerManager $timerManager,
        private NonceManager $nonceManager,
        private QuizFlowManager $flowManager,
        private ResultsProcessor $resultsProcessor
    ) {}

    /**
     * Start a new quiz attempt
     */
    public function startQuiz(int $quizId, ?int $userId = null, array $userData = []): Attempt
    {
        $quiz = $this->quizRepository->findById($quizId);
        
        if (!$quiz) {
            throw new ServiceException('Quiz not found');
        }
        
        if ($quiz->getStatus() !== 'published') {
            throw new ServiceException('Quiz is not available');
        }
        
        // Create new attempt
        $attemptData = [
            'quiz_id' => $quizId,
            'user_id' => $userId,
            'user_name' => $userData['name'] ?? '',
            'user_email' => $userData['email'] ?? '',
            'started_at' => current_time('mysql'),
            'status' => 'in_progress'
        ];
        
        return $this->attemptService->createAttempt($attemptData);
    }

    /**
     * Process answer submission
     */
    public function submitAnswer(int $attemptId, int $questionId, $answer, string $nonce): bool
    {
        // Verify nonce
        if (!$this->nonceManager->verify($nonce, 'mq_submit_answer')) {
            throw new ServiceException('Invalid security token');
        }
        
        $attempt = $this->attemptService->getAttempt($attemptId);
        
        if (!$attempt) {
            throw new ServiceException('Invalid attempt');
        }
        
        if ($attempt->isCompleted()) {
            throw new ServiceException('Quiz already completed');
        }
        
        // Check timer
        $quiz = $this->quizRepository->findById($attempt->getQuizId());
        if ($quiz->getTimeLimit() > 0 && $this->timerManager->isExpired($attempt, $quiz->getTimeLimit())) {
            throw new ServiceException('Time limit exceeded');
        }
        
        // Save answer
        $this->answerManager->saveAnswer($attemptId, $questionId, $answer);
        
        return true;
    }

    /**
     * Complete quiz attempt
     */
    public function completeQuiz(int $attemptId, string $nonce): array
    {
        // Verify nonce
        if (!$this->nonceManager->verify($nonce, 'mq_submit_quiz')) {
            throw new ServiceException('Invalid security token');
        }
        
        $attempt = $this->attemptService->getAttempt($attemptId);
        
        if (!$attempt) {
            throw new ServiceException('Invalid attempt');
        }
        
        if ($attempt->isCompleted()) {
            throw new ServiceException('Quiz already completed');
        }
        
        // Check if all required questions answered
        if (!$this->answerManager->hasAllRequiredAnswers($attemptId)) {
            throw new ServiceException('Please answer all required questions');
        }
        
        // Process results
        $results = $this->resultsProcessor->processResults($attempt);
        
        // Update attempt status
        $this->attemptService->updateAttempt($attemptId, [
            'status' => 'completed',
            'completed_at' => current_time('mysql'),
            'score' => $results['score']['total_score'],
            'result_data' => json_encode($results)
        ]);
        
        return $results;
    }

    /**
     * Get quiz state for resuming
     */
    public function getQuizState(int $attemptId): array
    {
        $attempt = $this->attemptService->getAttempt($attemptId);
        
        if (!$attempt) {
            throw new ServiceException('Invalid attempt');
        }
        
        $quiz = $this->quizRepository->findById($attempt->getQuizId());
        $answers = $this->answerManager->getAttemptAnswers($attemptId);
        
        return [
            'attempt' => $attempt,
            'quiz' => $quiz,
            'answers' => $answers,
            'current_page' => $this->flowManager->getCurrentPage($attempt),
            'can_resume' => !$attempt->isCompleted() && 
                           (!$quiz->getTimeLimit() || !$this->timerManager->isExpired($attempt, $quiz->getTimeLimit()))
        ];
    }

    /**
     * Navigate quiz pages
     */
    public function navigate(int $attemptId, string $direction, string $nonce): int
    {
        if (!$this->nonceManager->verify($nonce, 'mq_navigate')) {
            throw new ServiceException('Invalid security token');
        }
        
        $attempt = $this->attemptService->getAttempt($attemptId);
        
        if (!$attempt || $attempt->isCompleted()) {
            throw new ServiceException('Invalid attempt');
        }
        
        return $this->flowManager->navigate($attempt, $direction);
    }
}
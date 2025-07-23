<?php
/**
 * Attempt Service
 *
 * Manages quiz attempts and their lifecycle.
 *
 * @package MoneyQuiz\Application\Services
 * @since   7.0.0
 */

namespace MoneyQuiz\Application\Services;

use MoneyQuiz\Domain\Entities\Attempt;
use MoneyQuiz\Domain\Entities\Result;
use MoneyQuiz\Domain\Repositories\AttemptRepository;
use MoneyQuiz\Domain\Repositories\QuizRepository;
use MoneyQuiz\Domain\Events\EventDispatcher;
use MoneyQuiz\Security\Contracts\AuthorizationInterface;
use MoneyQuiz\Core\Logging\Logger;
use MoneyQuiz\Core\Performance\PerformanceMonitor;
use MoneyQuiz\Core\Session\SessionManager;
use MoneyQuiz\Domain\Exceptions\EntityException;

// Security: Prevent direct access.
defined('ABSPATH') || exit;

/**
 * Attempt service class.
 *
 * @since 7.0.0
 */
class AttemptService {
    
    /**
     * Attempt repository.
     *
     * @var AttemptRepository
     */
    private AttemptRepository $attempt_repository;
    
    /**
     * Quiz repository.
     *
     * @var QuizRepository
     */
    private QuizRepository $quiz_repository;
    
    /**
     * Result calculation service.
     *
     * @var ResultCalculationService
     */
    private ResultCalculationService $result_service;
    
    /**
     * Event dispatcher.
     *
     * @var EventDispatcher
     */
    private EventDispatcher $event_dispatcher;
    
    /**
     * Authorization service.
     *
     * @var AuthorizationInterface
     */
    private AuthorizationInterface $authorization;
    
    /**
     * Logger instance.
     *
     * @var Logger
     */
    private Logger $logger;
    
    /**
     * Performance monitor.
     *
     * @var PerformanceMonitor
     */
    private PerformanceMonitor $monitor;
    
    /**
     * Constructor.
     *
     * @param AttemptRepository        $attempt_repository Attempt repository.
     * @param QuizRepository          $quiz_repository    Quiz repository.
     * @param ResultCalculationService $result_service     Result calculation service.
     * @param EventDispatcher         $event_dispatcher   Event dispatcher.
     * @param AuthorizationInterface  $authorization      Authorization service.
     * @param Logger                  $logger             Logger instance.
     */
    public function __construct(
        AttemptRepository $attempt_repository,
        QuizRepository $quiz_repository,
        ResultCalculationService $result_service,
        EventDispatcher $event_dispatcher,
        AuthorizationInterface $authorization,
        Logger $logger
    ) {
        $this->attempt_repository = $attempt_repository;
        $this->quiz_repository = $quiz_repository;
        $this->result_service = $result_service;
        $this->event_dispatcher = $event_dispatcher;
        $this->authorization = $authorization;
        $this->logger = $logger;
        $this->monitor = new PerformanceMonitor($logger);
    }
    
    /**
     * Start a new quiz attempt.
     *
     * @param int         $quiz_id    Quiz ID.
     * @param int|null    $user_id    User ID or null for anonymous.
     * @param string|null $user_email User email for anonymous users.
     * @return Attempt The started attempt.
     * @throws EntityException If validation fails.
     */
    public function start_attempt(int $quiz_id, ?int $user_id = null, ?string $user_email = null): Attempt {
        $measurement_id = $this->monitor->start('start_attempt', [
            'quiz_id' => $quiz_id,
            'user_id' => $user_id
        ]);
        
        try {
            // Check authorization
            if (!$this->authorization->can_take_quiz($user_id, $quiz_id)) {
                throw new EntityException('Unauthorized to take this quiz');
            }
            
            // Get quiz
            $quiz = $this->quiz_repository->find_by_id($quiz_id);
            if (!$quiz) {
                throw new EntityException('Quiz not found');
            }
            
            if ($quiz->get_status() !== 'published') {
                throw new EntityException('Quiz is not available');
            }
            
            // Check for active attempts
            if ($user_id) {
                $active_attempts = $this->attempt_repository->find_active_by_user($user_id);
                foreach ($active_attempts as $attempt) {
                    if ($attempt->get_quiz_id() === $quiz_id) {
                        // Return existing active attempt
                        $this->logger->info('Returning existing active attempt', [
                            'attempt_id' => $attempt->get_id(),
                            'user_id' => $user_id,
                            'quiz_id' => $quiz_id
                        ]);
                        return $attempt;
                    }
                }
            }
            
            // Get quiz questions
            $questions = $quiz->get_questions();
            if (empty($questions)) {
                throw new EntityException('Quiz has no questions');
            }
            
            // Prepare questions data
            $questions_data = array_map(function($question) {
                return [
                    'id' => $question->get_id(),
                    'text' => $question->get_text(),
                    'type' => $question->get_type(),
                    'options' => $question->get_options(),
                    'order' => $question->get_order()
                ];
            }, $questions);
            
            // Create attempt
            $attempt = new Attempt(
                $quiz_id,
                $user_id,
                $user_email,
                $questions_data,
                [
                    'quiz_title' => $quiz->get_title(),
                    'quiz_version' => $quiz->get_version()
                ]
            );
            
            // Save attempt
            if (!$this->attempt_repository->save($attempt)) {
                throw new EntityException('Failed to save attempt');
            }
            
            // Dispatch events
            foreach ($attempt->release_events() as $event) {
                $this->event_dispatcher->dispatch($event);
            }
            
            $this->logger->info('Quiz attempt started', [
                'attempt_id' => $attempt->get_id(),
                'quiz_id' => $quiz_id,
                'user_id' => $user_id
            ]);
            
            $this->monitor->end($measurement_id, true, [
                'attempt_id' => $attempt->get_id()
            ]);
            
            return $attempt;
            
        } catch (\Exception $e) {
            $this->logger->error('Failed to start attempt', [
                'quiz_id' => $quiz_id,
                'user_id' => $user_id,
                'error' => $e->getMessage()
            ]);
            
            $this->monitor->end($measurement_id, false, [
                'error' => $e->getMessage()
            ]);
            
            throw $e;
        }
    }
    
    /**
     * Submit answers for an attempt.
     *
     * @param int      $attempt_id Attempt ID.
     * @param array    $answers    Array of answers.
     * @param int|null $user_id    User ID for authorization.
     * @return void
     * @throws EntityException If validation fails.
     */
    public function submit_answers(int $attempt_id, array $answers, ?int $user_id = null): void {
        try {
            // Get attempt
            $attempt = $this->attempt_repository->find_by_id($attempt_id);
            if (!$attempt) {
                throw new EntityException('Attempt not found');
            }
            
            // Check authorization
            if (!$this->can_access_attempt($attempt, $user_id)) {
                throw new EntityException('Unauthorized to access this attempt');
            }
            
            // Validate and format answers
            $formatted_answers = [];
            foreach ($answers as $answer) {
                if (!isset($answer['question_id'])) {
                    throw new EntityException('Question ID is required for each answer');
                }
                
                $formatted_answers[$answer['question_id']] = [
                    'answer_id' => $answer['answer_id'] ?? null,
                    'answer_text' => $answer['answer_text'] ?? null,
                    'time_taken' => $answer['time_taken'] ?? 0
                ];
            }
            
            // Submit answers
            $attempt->submit_answers($formatted_answers);
            
            // Save attempt
            if (!$this->attempt_repository->save($attempt)) {
                throw new EntityException('Failed to save answers');
            }
            
            $this->logger->info('Answers submitted', [
                'attempt_id' => $attempt_id,
                'answer_count' => count($answers)
            ]);
            
        } catch (\Exception $e) {
            $this->logger->error('Failed to submit answers', [
                'attempt_id' => $attempt_id,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }
    
    /**
     * Complete an attempt and calculate result.
     *
     * @param int      $attempt_id Attempt ID.
     * @param int|null $user_id    User ID for authorization.
     * @return Result The calculated result.
     * @throws EntityException If validation fails.
     */
    public function complete_attempt(int $attempt_id, ?int $user_id = null): Result {
        $measurement_id = $this->monitor->start('complete_attempt', [
            'attempt_id' => $attempt_id,
            'user_id' => $user_id
        ]);
        
        try {
            // Get attempt
            $attempt = $this->attempt_repository->find_by_id($attempt_id);
            if (!$attempt) {
                throw new EntityException('Attempt not found');
            }
            
            // Check authorization
            if (!$this->can_access_attempt($attempt, $user_id)) {
                throw new EntityException('Unauthorized to access this attempt');
            }
            
            // Check if already completed
            if ($attempt->is_completed()) {
                // Return existing result
                if ($attempt->get_result_id()) {
                    $result = $this->result_service->get_result($attempt->get_result_id(), $user_id);
                    if ($result) {
                        return $result;
                    }
                }
                throw new EntityException('Attempt already completed but result not found');
            }
            
            // Calculate result
            $result = $this->result_service->calculate_result(
                $attempt->get_quiz_id(),
                $attempt->get_answers(),
                $attempt->get_user_id() ?? 0,
                [
                    'attempt_id' => $attempt_id,
                    'user_email' => $attempt->get_user_email(),
                    'time_taken' => $attempt->get_time_taken()
                ]
            );
            
            // Complete attempt
            $attempt->complete($result->get_id());
            
            // Save attempt
            if (!$this->attempt_repository->save($attempt)) {
                throw new EntityException('Failed to save completed attempt');
            }
            
            // Dispatch events
            foreach ($attempt->release_events() as $event) {
                $this->event_dispatcher->dispatch($event);
            }
            
            $this->logger->info('Attempt completed', [
                'attempt_id' => $attempt_id,
                'result_id' => $result->get_id()
            ]);
            
            $this->monitor->end($measurement_id, true, [
                'result_id' => $result->get_id(),
                'archetype' => $result->get_archetype()?->get_slug()
            ]);
            
            return $result;
            
        } catch (\Exception $e) {
            $this->logger->error('Failed to complete attempt', [
                'attempt_id' => $attempt_id,
                'error' => $e->getMessage()
            ]);
            
            $this->monitor->end($measurement_id, false, [
                'error' => $e->getMessage()
            ]);
            
            throw $e;
        }
    }
    
    /**
     * Get attempt by ID.
     *
     * @param int      $attempt_id Attempt ID.
     * @param int|null $user_id    User ID for authorization.
     * @return Attempt|null Attempt or null.
     */
    public function get_attempt(int $attempt_id, ?int $user_id = null): ?Attempt {
        try {
            $attempt = $this->attempt_repository->find_by_id($attempt_id);
            
            if ($attempt && !$this->can_access_attempt($attempt, $user_id)) {
                $this->logger->warning('Unauthorized attempt access', [
                    'attempt_id' => $attempt_id,
                    'user_id' => $user_id
                ]);
                return null;
            }
            
            return $attempt;
            
        } catch (\Exception $e) {
            $this->logger->error('Failed to get attempt', [
                'attempt_id' => $attempt_id,
                'error' => $e->getMessage()
            ]);
            return null;
        }
    }
    
    /**
     * Get attempt by session token.
     *
     * @param string $token Session token.
     * @return Attempt|null Attempt or null.
     */
    public function get_attempt_by_token(string $token): ?Attempt {
        try {
            return $this->attempt_repository->find_by_session_token($token);
        } catch (\Exception $e) {
            $this->logger->error('Failed to get attempt by token', [
                'error' => $e->getMessage()
            ]);
            return null;
        }
    }
    
    /**
     * Clean up abandoned attempts.
     *
     * @param int $hours Hours after which to consider abandoned.
     * @return int Number of attempts cleaned.
     */
    public function cleanup_abandoned_attempts(int $hours = 24): int {
        try {
            $count = $this->attempt_repository->cleanup_abandoned($hours);
            
            if ($count > 0) {
                $this->logger->info('Abandoned attempts cleaned up', [
                    'count' => $count,
                    'hours' => $hours
                ]);
            }
            
            return $count;
            
        } catch (\Exception $e) {
            $this->logger->error('Failed to cleanup abandoned attempts', [
                'error' => $e->getMessage()
            ]);
            return 0;
        }
    }
    
    /**
     * Check if user can access attempt.
     *
     * @param Attempt  $attempt Attempt to check.
     * @param int|null $user_id User ID.
     * @return bool True if can access.
     */
    private function can_access_attempt(Attempt $attempt, ?int $user_id): bool {
        // Admin can access all
        if ($user_id && $this->authorization->can_manage_quizzes($user_id)) {
            return true;
        }
        
        // User can access their own attempts
        if ($user_id && $attempt->get_user_id() === $user_id) {
            return true;
        }
        
        // For anonymous attempts, would need session validation
        // This is simplified for now
        return !$attempt->get_user_id();
    }
}
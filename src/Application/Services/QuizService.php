<?php
/**
 * Quiz Service
 *
 * Handles quiz business logic and orchestration.
 *
 * @package MoneyQuiz\Application\Services
 * @since   7.0.0
 */

namespace MoneyQuiz\Application\Services;

use MoneyQuiz\Core\Logging\Logger;
use MoneyQuiz\Domain\Entities\Quiz;
use MoneyQuiz\Domain\Entities\Question;
use MoneyQuiz\Domain\Repositories\QuizRepository;
use MoneyQuiz\Domain\Events\EventDispatcher;
use MoneyQuiz\Domain\ValueObjects\QuizSettings;
use MoneyQuiz\Security\Contracts\AuthorizationInterface;
use MoneyQuiz\Application\Exceptions\ServiceException;

// Security: Prevent direct access.
defined('ABSPATH') || exit;

/**
 * Quiz service class.
 *
 * @since 7.0.0
 */
class QuizService {
    
    /**
     * Quiz repository.
     *
     * @var QuizRepository
     */
    private QuizRepository $repository;
    
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
     * Constructor.
     *
     * @param QuizRepository         $repository       Quiz repository.
     * @param EventDispatcher        $event_dispatcher Event dispatcher.
     * @param AuthorizationInterface $authorization    Authorization service.
     * @param Logger                 $logger           Logger instance.
     */
    public function __construct(
        QuizRepository $repository,
        EventDispatcher $event_dispatcher,
        AuthorizationInterface $authorization,
        Logger $logger
    ) {
        $this->repository = $repository;
        $this->event_dispatcher = $event_dispatcher;
        $this->authorization = $authorization;
        $this->logger = $logger;
    }
    
    /**
     * Create new quiz.
     *
     * @param array $data Quiz data.
     * @param int   $user_id Creator user ID.
     * @return Quiz Created quiz.
     * @throws ServiceException If creation fails.
     */
    public function create_quiz(array $data, int $user_id): Quiz {
        // Check authorization
        if (!$this->authorization->can($user_id, 'create_quiz')) {
            throw new ServiceException('Unauthorized to create quiz');
        }
        
        try {
            // Start transaction
            $this->repository->begin_transaction();
            
            // Create quiz entity
            $settings = isset($data['settings']) 
                ? QuizSettings::from_array($data['settings']) 
                : new QuizSettings();
            
            $quiz = new Quiz(
                $data['title'] ?? '',
                $data['description'] ?? '',
                $user_id,
                $settings
            );
            
            // Save to repository
            $quiz = $this->repository->save($quiz);
            
            // Dispatch events
            foreach ($quiz->release_events() as $event) {
                $this->event_dispatcher->dispatch($event);
            }
            
            // Commit transaction
            $this->repository->commit();
            
            $this->logger->info('Quiz created', [
                'quiz_id' => $quiz->get_id(),
                'user_id' => $user_id
            ]);
            
            return $quiz;
            
        } catch (\Exception $e) {
            $this->repository->rollback();
            
            $this->logger->error('Failed to create quiz', [
                'error' => $e->getMessage(),
                'user_id' => $user_id
            ]);
            
            throw new ServiceException('Failed to create quiz: ' . $e->getMessage(), 0, $e);
        }
    }
    
    /**
     * Update quiz.
     *
     * @param int   $quiz_id Quiz ID.
     * @param array $data    Update data.
     * @param int   $user_id User making the update.
     * @return Quiz Updated quiz.
     * @throws ServiceException If update fails.
     */
    public function update_quiz(int $quiz_id, array $data, int $user_id): Quiz {
        // Find quiz
        $quiz = $this->repository->find_or_fail($quiz_id);
        
        // Check authorization
        if (!$this->authorization->can($user_id, 'edit_quiz', $quiz_id)) {
            throw new ServiceException('Unauthorized to edit quiz');
        }
        
        try {
            $this->repository->begin_transaction();
            
            // Update quiz
            if (isset($data['title']) || isset($data['description'])) {
                $quiz->update(
                    $data['title'] ?? $quiz->get_title(),
                    $data['description'] ?? $quiz->get_description()
                );
            }
            
            // Save changes
            $quiz = $this->repository->save($quiz);
            
            // Dispatch events
            foreach ($quiz->release_events() as $event) {
                $this->event_dispatcher->dispatch($event);
            }
            
            $this->repository->commit();
            
            $this->logger->info('Quiz updated', [
                'quiz_id' => $quiz_id,
                'user_id' => $user_id
            ]);
            
            return $quiz;
            
        } catch (\Exception $e) {
            $this->repository->rollback();
            
            $this->logger->error('Failed to update quiz', [
                'quiz_id' => $quiz_id,
                'error' => $e->getMessage()
            ]);
            
            throw new ServiceException('Failed to update quiz: ' . $e->getMessage(), 0, $e);
        }
    }
    
    /**
     * Publish quiz.
     *
     * @param int $quiz_id Quiz ID.
     * @param int $user_id User publishing.
     * @return Quiz Published quiz.
     * @throws ServiceException If publish fails.
     */
    public function publish_quiz(int $quiz_id, int $user_id): Quiz {
        $quiz = $this->repository->find_with_questions($quiz_id);
        
        if (!$quiz) {
            throw new ServiceException('Quiz not found');
        }
        
        // Check authorization
        if (!$this->authorization->can($user_id, 'publish_quiz', $quiz_id)) {
            throw new ServiceException('Unauthorized to publish quiz');
        }
        
        try {
            $this->repository->begin_transaction();
            
            // Publish quiz
            $quiz->publish();
            
            // Save
            $quiz = $this->repository->save($quiz);
            
            // Dispatch events
            foreach ($quiz->release_events() as $event) {
                $this->event_dispatcher->dispatch($event);
            }
            
            $this->repository->commit();
            
            $this->logger->info('Quiz published', [
                'quiz_id' => $quiz_id,
                'user_id' => $user_id
            ]);
            
            return $quiz;
            
        } catch (\Exception $e) {
            $this->repository->rollback();
            
            $this->logger->error('Failed to publish quiz', [
                'quiz_id' => $quiz_id,
                'error' => $e->getMessage()
            ]);
            
            throw new ServiceException('Failed to publish quiz: ' . $e->getMessage(), 0, $e);
        }
    }
    
    /**
     * Get quiz by ID.
     *
     * @param int $quiz_id Quiz ID.
     * @param int $user_id User requesting.
     * @return Quiz|null Quiz instance or null.
     */
    public function get_quiz(int $quiz_id, int $user_id): ?Quiz {
        $quiz = $this->repository->find($quiz_id);
        
        if (!$quiz) {
            return null;
        }
        
        // Check authorization
        if (!$this->authorization->can($user_id, 'view_quiz', $quiz_id)) {
            $this->logger->warning('Unauthorized quiz access attempt', [
                'quiz_id' => $quiz_id,
                'user_id' => $user_id
            ]);
            return null;
        }
        
        return $quiz;
    }
    
    /**
     * List quizzes.
     *
     * @param array $filters Filters.
     * @param int   $user_id User requesting.
     * @param int   $limit   Limit.
     * @param int   $offset  Offset.
     * @return array<Quiz> Quizzes.
     */
    public function list_quizzes(
        array $filters = [],
        int $user_id = 0,
        int $limit = 10,
        int $offset = 0
    ): array {
        // Apply authorization filters
        if ($user_id && !$this->authorization->can($user_id, 'view_all_quizzes')) {
            // Limit to user's own quizzes or published
            $filters['user_or_published'] = $user_id;
        }
        
        return $this->repository->find_all($filters, ['updated_at' => 'DESC'], $limit, $offset);
    }
}
<?php
/**
 * Result Calculation Service
 *
 * Handles quiz result calculation and archetype assignment.
 *
 * @package MoneyQuiz\Application\Services
 * @since   7.0.0
 */

namespace MoneyQuiz\Application\Services;

use MoneyQuiz\Core\Logging\Logger;
use MoneyQuiz\Core\Performance\PerformanceMonitor;
use MoneyQuiz\Domain\Entities\Quiz;
use MoneyQuiz\Domain\Entities\Result;
use MoneyQuiz\Domain\Entities\Archetype;
use MoneyQuiz\Domain\ValueObjects\Score;
use MoneyQuiz\Domain\Repositories\QuizRepository;
use MoneyQuiz\Domain\Repositories\ArchetypeRepository;
use MoneyQuiz\Domain\Repositories\ResultRepository;
use MoneyQuiz\Domain\Events\EventDispatcher;
use MoneyQuiz\Application\Exceptions\ServiceException;

// Security: Prevent direct access.
defined('ABSPATH') || exit;

/**
 * Result calculation service class.
 *
 * @since 7.0.0
 */
class ResultCalculationService {
    
    /**
     * Quiz repository.
     *
     * @var QuizRepository
     */
    private QuizRepository $quiz_repository;
    
    /**
     * Archetype repository.
     *
     * @var ArchetypeRepository
     */
    private ArchetypeRepository $archetype_repository;
    
    /**
     * Result repository.
     *
     * @var ResultRepository
     */
    private ResultRepository $result_repository;
    
    /**
     * Event dispatcher.
     *
     * @var EventDispatcher
     */
    private EventDispatcher $event_dispatcher;
    
    /**
     * Logger instance.
     *
     * @var Logger
     */
    private Logger $logger;
    
    /**
     * Constructor.
     *
     * @param QuizRepository      $quiz_repository      Quiz repository.
     * @param ArchetypeRepository $archetype_repository Archetype repository.
     * @param ResultRepository    $result_repository    Result repository.
     * @param EventDispatcher     $event_dispatcher     Event dispatcher.
     * @param Logger              $logger               Logger instance.
     */
    public function __construct(
        QuizRepository $quiz_repository,
        ArchetypeRepository $archetype_repository,
        ResultRepository $result_repository,
        EventDispatcher $event_dispatcher,
        Logger $logger
    ) {
        $this->quiz_repository = $quiz_repository;
        $this->archetype_repository = $archetype_repository;
        $this->result_repository = $result_repository;
        $this->event_dispatcher = $event_dispatcher;
        $this->logger = $logger;
    }
    
    /**
     * Calculate result for quiz attempt.
     *
     * @param int   $attempt_id Attempt ID.
     * @param int   $quiz_id    Quiz ID.
     * @param int   $user_id    User ID.
     * @param array $answers    User answers [question_id => answer_id].
     * @return Result Calculated result.
     * @throws ServiceException If calculation fails.
     */
    public function calculate_result(
        int $attempt_id,
        int $quiz_id,
        int $user_id,
        array $answers
    ): Result {
        try {
            // Load quiz with questions
            $quiz = $this->quiz_repository->find_with_questions($quiz_id);
            if (!$quiz) {
                throw new ServiceException('Quiz not found');
            }
            
            // Calculate score
            $score = $this->calculate_score($quiz, $answers);
            
            // Create result
            $result = new Result(
                $attempt_id,
                $quiz_id,
                $user_id,
                $score,
                ['answer_count' => count($answers)]
            );
            
            // Determine archetype
            $archetype = $this->determine_archetype($score);
            if ($archetype) {
                $result->assign_archetype($archetype);
            }
            
            // Save result
            $this->result_repository->begin_transaction();
            
            $result = $this->result_repository->save($result);
            
            // Dispatch events
            foreach ($result->release_events() as $event) {
                $this->event_dispatcher->dispatch($event);
            }
            
            $this->result_repository->commit();
            
            $this->logger->info('Result calculated', [
                'result_id' => $result->get_id(),
                'quiz_id' => $quiz_id,
                'user_id' => $user_id,
                'score' => $score->get_total(),
                'archetype' => $archetype?->get_slug()
            ]);
            
            return $result;
            
        } catch (\Exception $e) {
            if (isset($this->result_repository)) {
                $this->result_repository->rollback();
            }
            
            $this->logger->error('Failed to calculate result', [
                'attempt_id' => $attempt_id,
                'error' => $e->getMessage()
            ]);
            
            throw new ServiceException('Failed to calculate result: ' . $e->getMessage(), 0, $e);
        }
    }
    
    /**
     * Calculate score from answers.
     *
     * @param Quiz  $quiz    Quiz with questions.
     * @param array $answers User answers.
     * @return Score Calculated score.
     */
    private function calculate_score(Quiz $quiz, array $answers): Score {
        $total_score = 0;
        $max_score = 0;
        $dimension_scores = [];
        
        foreach ($quiz->get_questions() as $question) {
            $question_id = $question->get_id();
            $max_score += $question->get_points();
            
            // Skip if no answer provided
            if (!isset($answers[$question_id])) {
                continue;
            }
            
            $answer_id = $answers[$question_id];
            
            // Find selected answer
            foreach ($question->get_answers() as $answer) {
                if ($answer->get_id() === $answer_id) {
                    $points = $answer->get_value();
                    $total_score += $points;
                    
                    // Track dimension scores if metadata exists
                    $metadata = $question->get_metadata();
                    if (isset($metadata['dimension'])) {
                        $dimension = $metadata['dimension'];
                        $dimension_scores[$dimension] = ($dimension_scores[$dimension] ?? 0) + $points;
                    }
                    
                    break;
                }
            }
        }
        
        return new Score($total_score, $max_score, $dimension_scores);
    }
    
    /**
     * Determine archetype based on score.
     *
     * @param Score $score Quiz score.
     * @return Archetype|null Matching archetype or null.
     */
    private function determine_archetype(Score $score): ?Archetype {
        // Get active archetypes
        $archetypes = $this->archetype_repository->find_active();
        
        // Find matching archetype
        foreach ($archetypes as $archetype) {
            if ($archetype->matches_score($score)) {
                return $archetype;
            }
        }
        
        // No match found
        $this->logger->warning('No archetype matched score', [
            'score' => $score->get_total(),
            'percentage' => $score->get_percentage()
        ]);
        
        return null;
    }
    
    /**
     * Recalculate result.
     *
     * @param int $result_id Result ID to recalculate.
     * @return Result Updated result.
     * @throws ServiceException If recalculation fails.
     */
    public function recalculate_result(int $result_id): Result {
        $result = $this->result_repository->find_or_fail($result_id);
        
        // Get attempt data
        $attempt_data = $this->get_attempt_data($result->get_attempt_id());
        
        // Recalculate
        return $this->calculate_result(
            $result->get_attempt_id(),
            $result->get_quiz_id(),
            $result->get_user_id(),
            $attempt_data['answers']
        );
    }
    
    /**
     * Get attempt data.
     *
     * @param int $attempt_id Attempt ID.
     * @return array Attempt data with answers.
     */
    private function get_attempt_data(int $attempt_id): array {
        // This would typically load from an AttemptRepository
        // For now, returning mock data
        return [
            'answers' => []
        ];
    }
}
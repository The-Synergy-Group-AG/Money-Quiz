<?php
declare(strict_types=1);

namespace MoneyQuiz\Features\Quiz\Taking;

use MoneyQuiz\Domain\Entities\Attempt;
use MoneyQuiz\Domain\Entities\Result;
use MoneyQuiz\Application\Services\ResultCalculationService;
use MoneyQuiz\Features\Answer\AnswerManager;
use MoneyQuiz\Features\Archetype\ArchetypeCalculator;
use MoneyQuiz\Domain\Repositories\QuizRepository;
use MoneyQuiz\Domain\Repositories\ResultRepository;

/**
 * Processes quiz results and calculates outcomes
 */
class ResultsProcessor
{
    public function __construct(
        private ResultCalculationService $calculationService,
        private AnswerManager $answerManager,
        private ArchetypeCalculator $archetypeCalculator,
        private QuizRepository $quizRepository,
        private ResultRepository $resultRepository
    ) {}

    /**
     * Process results for completed attempt
     */
    public function processResults(Attempt $attempt): array
    {
        $quiz = $this->quizRepository->findById($attempt->getQuizId());
        
        // Calculate score
        $scoreData = $this->answerManager->calculateScore($attempt->getId());
        
        // Process based on quiz type
        $results = match($quiz->getType()) {
            'personality' => $this->processPersonalityResults($attempt, $scoreData),
            'assessment' => $this->processAssessmentResults($attempt, $scoreData),
            'survey' => $this->processSurveyResults($attempt, $scoreData),
            default => $this->processGenericResults($attempt, $scoreData)
        };
        
        // Save result
        $this->saveResult($attempt, $results);
        
        return $results;
    }

    /**
     * Process personality quiz results
     */
    private function processPersonalityResults(Attempt $attempt, array $scoreData): array
    {
        $answers = $this->answerManager->getAttemptAnswers($attempt->getId());
        
        // Calculate archetype
        $archetype = $this->archetypeCalculator->calculate($answers);
        
        return [
            'type' => 'personality',
            'score' => $scoreData,
            'archetype' => $archetype,
            'recommendations' => $this->archetypeCalculator->getRecommendations($archetype),
            'insights' => $this->archetypeCalculator->getInsights($archetype)
        ];
    }

    /**
     * Process assessment quiz results
     */
    private function processAssessmentResults(Attempt $attempt, array $scoreData): array
    {
        $quiz = $this->quizRepository->findById($attempt->getQuizId());
        $passingScore = $quiz->getPassingScore();
        
        return [
            'type' => 'assessment',
            'score' => $scoreData,
            'passed' => $scoreData['percentage'] >= $passingScore,
            'passing_score' => $passingScore,
            'feedback' => $this->getScoreFeedback($scoreData['percentage'], $passingScore)
        ];
    }

    /**
     * Process survey results
     */
    private function processSurveyResults(Attempt $attempt, array $scoreData): array
    {
        $answers = $this->answerManager->getAttemptAnswers($attempt->getId());
        
        return [
            'type' => 'survey',
            'responses' => count($answers),
            'summary' => $this->summarizeSurveyResponses($answers),
            'completion_rate' => $scoreData['percentage']
        ];
    }

    /**
     * Process generic quiz results
     */
    private function processGenericResults(Attempt $attempt, array $scoreData): array
    {
        return [
            'type' => 'generic',
            'score' => $scoreData,
            'completion_time' => $this->calculateCompletionTime($attempt)
        ];
    }

    /**
     * Save result to database
     */
    private function saveResult(Attempt $attempt, array $results): void
    {
        $resultData = [
            'attempt_id' => $attempt->getId(),
            'quiz_id' => $attempt->getQuizId(),
            'user_id' => $attempt->getUserId(),
            'score' => $results['score']['total_score'] ?? 0,
            'percentage' => $results['score']['percentage'] ?? 0,
            'result_data' => json_encode($results),
            'created_at' => current_time('mysql')
        ];
        
        if (isset($results['archetype'])) {
            $resultData['archetype_id'] = $results['archetype']['id'];
        }
        
        $this->resultRepository->create($resultData);
    }

    /**
     * Get feedback based on score
     */
    private function getScoreFeedback(float $percentage, int $passingScore): string
    {
        if ($percentage >= 90) {
            return 'Excellent! You have mastered this topic.';
        } elseif ($percentage >= $passingScore) {
            return 'Good job! You passed the assessment.';
        } elseif ($percentage >= $passingScore - 10) {
            return 'Almost there! Review the material and try again.';
        } else {
            return 'Keep studying! You can improve with more practice.';
        }
    }

    /**
     * Summarize survey responses
     */
    private function summarizeSurveyResponses(array $answers): array
    {
        // Basic summary - can be extended
        return [
            'total_responses' => count($answers),
            'response_time' => $this->getAverageResponseTime($answers)
        ];
    }

    /**
     * Calculate completion time
     */
    private function calculateCompletionTime(Attempt $attempt): int
    {
        $start = strtotime($attempt->getStartedAt());
        $end = time();
        
        return $end - $start;
    }

    /**
     * Get average response time
     */
    private function getAverageResponseTime(array $answers): float
    {
        if (empty($answers)) {
            return 0;
        }
        
        $times = [];
        $previousTime = null;
        
        foreach ($answers as $answer) {
            $currentTime = strtotime($answer->getTimestamp());
            
            if ($previousTime !== null) {
                $times[] = $currentTime - $previousTime;
            }
            
            $previousTime = $currentTime;
        }
        
        return !empty($times) ? array_sum($times) / count($times) : 0;
    }
}
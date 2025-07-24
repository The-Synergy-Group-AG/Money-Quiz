<?php
declare(strict_types=1);

namespace MoneyQuiz\Features\Archetype;

use MoneyQuiz\Domain\Entities\Archetype;
use MoneyQuiz\Domain\Repositories\ArchetypeRepository;
use MoneyQuiz\Features\Question\QuestionRepository;
use MoneyQuiz\Security\InputValidator;
use MoneyQuiz\Application\Exceptions\ServiceException;

/**
 * Calculates financial personality archetype based on quiz answers
 */
class ArchetypeCalculator
{
    // Tie-breaking priority order (higher priority wins ties)
    private array $tieBreakingPriority = [
        'magician' => 8,    // Highest priority - balanced approach
        'warrior' => 7,     // Goal-oriented
        'creator' => 6,     // Creative solutions
        'innocent' => 5,    // Learning mindset
        'fool' => 4,        // Risk-taking
        'martyr' => 3,      // Service-oriented
        'tyrant' => 2,      // Control-focused
        'victim' => 1       // Lowest priority
    ];

    public function __construct(
        private ArchetypeRepository $archetypeRepository,
        private QuestionRepository $questionRepository,
        private ArchetypeScorer $scorer,
        private InputValidator $validator
    ) {}

    /**
     * Calculate archetype based on answers with validation
     */
    public function calculate(array $answers, int $quizId): array
    {
        // Validate answers against quiz questions
        $this->validateAnswers($answers, $quizId);
        
        // Get archetypes from database
        $archetypes = $this->loadArchetypesFromDatabase();
        
        // Calculate scores
        $scores = $this->scorer->calculateScores($answers);
        
        // Handle edge case: all scores are 0
        if (array_sum($scores) === 0) {
            throw new ServiceException('Unable to calculate archetype - no valid responses');
        }
        
        // Determine dominant with tie-breaking
        $dominantArchetype = $this->determineDominantArchetype($scores, $archetypes);
        
        return [
            'id' => $dominantArchetype->getId(),
            'key' => $dominantArchetype->getKey(),
            'name' => $dominantArchetype->getName(),
            'score' => $scores[$dominantArchetype->getKey()] ?? 0,
            'scores' => $scores,
            'traits' => $dominantArchetype->getTraits(),
            'percentage_match' => $this->calculateMatchPercentage($scores, $dominantArchetype->getKey()),
            'recommendations' => $dominantArchetype->getRecommendations(),
            'insights' => $dominantArchetype->getInsights()
        ];
    }

    /**
     * Validate answers against quiz questions
     */
    private function validateAnswers(array $answers, int $quizId): void
    {
        // Validate answer structure
        $validated = $this->validator->validateArray($answers, [
            '*' => ['type' => 'array']
        ]);
        
        // Get quiz questions
        $questions = $this->questionRepository->findByQuizId($quizId);
        
        if (empty($questions)) {
            throw new ServiceException('Quiz has no questions');
        }
        
        // Extract question IDs from answers
        $answeredQuestionIds = array_map(fn($a) => $a->getQuestionId(), $answers);
        $questionIds = array_map(fn($q) => $q->getId(), $questions);
        
        // Check all required questions are answered
        foreach ($questions as $question) {
            if ($question->isRequired() && !in_array($question->getId(), $answeredQuestionIds)) {
                throw new ServiceException(
                    sprintf('Required question %d not answered', $question->getId())
                );
            }
        }
        
        // Check no extra answers
        foreach ($answeredQuestionIds as $answeredId) {
            if (!in_array($answeredId, $questionIds)) {
                throw new ServiceException(
                    sprintf('Answer for non-existent question %d', $answeredId)
                );
            }
        }
    }

    /**
     * Load archetypes from database
     */
    private function loadArchetypesFromDatabase(): array
    {
        $archetypes = $this->archetypeRepository->findAll();
        
        if (empty($archetypes)) {
            throw new ServiceException('No archetypes configured in database');
        }
        
        // Index by key for easy access
        $indexed = [];
        foreach ($archetypes as $archetype) {
            $indexed[$archetype->getKey()] = $archetype;
        }
        
        return $indexed;
    }

    /**
     * Determine dominant archetype with tie-breaking logic
     */
    private function determineDominantArchetype(array $scores, array $archetypes): Archetype
    {
        $maxScore = max($scores);
        $topArchetypes = [];
        
        // Find all archetypes with max score (handles ties)
        foreach ($scores as $key => $score) {
            if ($score === $maxScore && isset($archetypes[$key])) {
                $topArchetypes[] = $key;
            }
        }
        
        // If no tie, return the single winner
        if (count($topArchetypes) === 1) {
            return $archetypes[$topArchetypes[0]];
        }
        
        // Apply tie-breaking logic
        $winner = null;
        $highestPriority = 0;
        
        foreach ($topArchetypes as $key) {
            $priority = $this->tieBreakingPriority[$key] ?? 0;
            if ($priority > $highestPriority) {
                $highestPriority = $priority;
                $winner = $key;
            }
        }
        
        if (!$winner || !isset($archetypes[$winner])) {
            throw new ServiceException('Unable to determine dominant archetype');
        }
        
        return $archetypes[$winner];
    }

    /**
     * Calculate match percentage
     */
    private function calculateMatchPercentage(array $scores, string $dominantKey): float
    {
        $totalScore = array_sum($scores);
        
        if ($totalScore === 0) {
            return 0;
        }
        
        return round(($scores[$dominantKey] / $totalScore) * 100, 1);
    }
}
<?php
declare(strict_types=1);

namespace MoneyQuiz\Features\Archetype;

use MoneyQuiz\Features\Question\QuestionRepository;
use MoneyQuiz\Domain\ValueObjects\Answer;

/**
 * Calculates archetype scores based on answer patterns
 */
class ArchetypeScorer
{
    // Answer patterns that indicate each archetype
    private array $archetypePatterns = [
        'innocent' => [
            'keywords' => ['trust', 'hope', 'faith', 'optimistic'],
            'behaviors' => ['avoids_conflict', 'seeks_safety', 'follows_advice']
        ],
        'victim' => [
            'keywords' => ['unfair', 'stuck', 'blame', 'helpless'],
            'behaviors' => ['external_locus', 'complains', 'passive']
        ],
        'warrior' => [
            'keywords' => ['goal', 'win', 'achieve', 'compete'],
            'behaviors' => ['sets_goals', 'tracks_progress', 'competitive']
        ],
        'martyr' => [
            'keywords' => ['sacrifice', 'help', 'give', 'others_first'],
            'behaviors' => ['gives_away', 'neglects_self', 'rescues']
        ],
        'fool' => [
            'keywords' => ['fun', 'spontaneous', 'enjoy', 'present'],
            'behaviors' => ['impulsive', 'avoids_planning', 'seeks_pleasure']
        ],
        'creator' => [
            'keywords' => ['create', 'imagine', 'passion', 'artistic'],
            'behaviors' => ['values_meaning', 'irregular_income', 'creative']
        ],
        'tyrant' => [
            'keywords' => ['control', 'power', 'dominate', 'mine'],
            'behaviors' => ['controls_money', 'uses_money_power', 'aggressive']
        ],
        'magician' => [
            'keywords' => ['transform', 'balance', 'wisdom', 'conscious'],
            'behaviors' => ['balanced_approach', 'teaches_others', 'empowered']
        ]
    ];

    public function __construct(
        private QuestionRepository $questionRepository
    ) {}

    /**
     * Calculate scores for each archetype
     */
    public function calculateScores(array $answers): array
    {
        $scores = array_fill_keys(array_keys($this->archetypePatterns), 0);
        
        foreach ($answers as $answer) {
            $question = $this->questionRepository->findById($answer->getQuestionId());
            
            if (!$question) {
                continue;
            }
            
            // Analyze answer based on question category
            $category = $question->getCategory();
            $answerValue = $answer->getValue();
            
            switch ($category) {
                case 'money_beliefs':
                    $this->scoreMoneyBeliefs($answerValue, $scores);
                    break;
                    
                case 'financial_behavior':
                    $this->scoreFinancialBehavior($answerValue, $scores);
                    break;
                    
                case 'emotional_patterns':
                    $this->scoreEmotionalPatterns($answerValue, $scores);
                    break;
                    
                case 'decision_making':
                    $this->scoreDecisionMaking($answerValue, $scores);
                    break;
            }
        }
        
        return $this->normalizeScores($scores);
    }

    /**
     * Score money beliefs questions
     */
    private function scoreMoneyBeliefs($answer, array &$scores): void
    {
        // Map specific answers to archetype tendencies
        $beliefMappings = [
            'money_is_root_of_evil' => ['innocent' => 3, 'victim' => 2],
            'money_equals_power' => ['tyrant' => 3, 'warrior' => 2],
            'money_should_be_shared' => ['martyr' => 3, 'magician' => 1],
            'money_is_for_enjoyment' => ['fool' => 3, 'creator' => 1],
            'money_requires_sacrifice' => ['warrior' => 2, 'martyr' => 2],
            'money_flows_naturally' => ['magician' => 3, 'innocent' => 1]
        ];
        
        if (isset($beliefMappings[$answer])) {
            foreach ($beliefMappings[$answer] as $archetype => $points) {
                $scores[$archetype] += $points;
            }
        }
    }

    /**
     * Score financial behavior questions
     */
    private function scoreFinancialBehavior($answer, array &$scores): void
    {
        $behaviorMappings = [
            'saves_regularly' => ['warrior' => 3, 'magician' => 2],
            'spends_impulsively' => ['fool' => 3, 'innocent' => 1],
            'gives_to_others' => ['martyr' => 3, 'magician' => 1],
            'controls_spending' => ['tyrant' => 3, 'warrior' => 1],
            'avoids_money_tasks' => ['victim' => 3, 'innocent' => 2],
            'creative_income' => ['creator' => 3, 'magician' => 1]
        ];
        
        if (isset($behaviorMappings[$answer])) {
            foreach ($behaviorMappings[$answer] as $archetype => $points) {
                $scores[$archetype] += $points;
            }
        }
    }

    /**
     * Score emotional pattern questions
     */
    private function scoreEmotionalPatterns($answer, array &$scores): void
    {
        $emotionMappings = [
            'anxious_about_money' => ['victim' => 3, 'innocent' => 1],
            'excited_by_money' => ['fool' => 2, 'creator' => 2],
            'guilty_having_money' => ['martyr' => 3, 'victim' => 1],
            'empowered_by_money' => ['warrior' => 2, 'tyrant' => 2, 'magician' => 2],
            'indifferent_to_money' => ['innocent' => 2, 'fool' => 1],
            'obsessed_with_money' => ['tyrant' => 3, 'warrior' => 1]
        ];
        
        if (isset($emotionMappings[$answer])) {
            foreach ($emotionMappings[$answer] as $archetype => $points) {
                $scores[$archetype] += $points;
            }
        }
    }

    /**
     * Score decision making questions
     */
    private function scoreDecisionMaking($answer, array &$scores): void
    {
        $decisionMappings = [
            'logical_analysis' => ['warrior' => 3, 'magician' => 2],
            'gut_feeling' => ['fool' => 2, 'creator' => 2, 'innocent' => 1],
            'others_advice' => ['innocent' => 3, 'victim' => 2],
            'control_outcome' => ['tyrant' => 3, 'warrior' => 1],
            'avoid_decisions' => ['victim' => 3, 'innocent' => 1],
            'consider_impact' => ['martyr' => 2, 'magician' => 3]
        ];
        
        if (isset($decisionMappings[$answer])) {
            foreach ($decisionMappings[$answer] as $archetype => $points) {
                $scores[$archetype] += $points;
            }
        }
    }

    /**
     * Normalize scores to percentages
     */
    private function normalizeScores(array $scores): array
    {
        $total = array_sum($scores);
        
        if ($total === 0) {
            return $scores;
        }
        
        foreach ($scores as $archetype => $score) {
            $scores[$archetype] = round(($score / $total) * 100, 2);
        }
        
        return $scores;
    }
}
<?php
declare(strict_types=1);

namespace MoneyQuiz\Features\Archetype;

use MoneyQuiz\Domain\Entities\Archetype;
use MoneyQuiz\Domain\Repositories\ArchetypeRepository;
use MoneyQuiz\Features\Question\QuestionRepository;

/**
 * Calculates financial personality archetype based on quiz answers
 */
class ArchetypeCalculator
{
    private array $archetypes = [
        'innocent' => ['id' => 1, 'name' => 'Innocent', 'traits' => ['trusting', 'optimistic', 'naive']],
        'victim' => ['id' => 2, 'name' => 'Victim', 'traits' => ['passive', 'blaming', 'powerless']],
        'warrior' => ['id' => 3, 'name' => 'Warrior', 'traits' => ['disciplined', 'goal-oriented', 'competitive']],
        'martyr' => ['id' => 4, 'name' => 'Martyr', 'traits' => ['self-sacrificing', 'rescuing', 'neglectful']],
        'fool' => ['id' => 5, 'name' => 'Fool', 'traits' => ['playful', 'impulsive', 'restless']],
        'creator' => ['id' => 6, 'name' => 'Creator/Artist', 'traits' => ['imaginative', 'passionate', 'artistic']],
        'tyrant' => ['id' => 7, 'name' => 'Tyrant', 'traits' => ['controlling', 'dominating', 'aggressive']],
        'magician' => ['id' => 8, 'name' => 'Magician', 'traits' => ['transformative', 'balanced', 'empowered']]
    ];

    public function __construct(
        private ArchetypeRepository $archetypeRepository,
        private QuestionRepository $questionRepository,
        private ArchetypeScorer $scorer
    ) {}

    /**
     * Calculate archetype based on answers
     */
    public function calculate(array $answers): array
    {
        $scores = $this->scorer->calculateScores($answers);
        $dominantArchetype = $this->determineDominantArchetype($scores);
        
        return [
            'id' => $dominantArchetype['id'],
            'key' => $dominantArchetype['key'],
            'name' => $dominantArchetype['name'],
            'score' => $dominantArchetype['score'],
            'scores' => $scores,
            'traits' => $this->archetypes[$dominantArchetype['key']]['traits'],
            'percentage_match' => $this->calculateMatchPercentage($scores, $dominantArchetype['key'])
        ];
    }

    /**
     * Get recommendations for archetype
     */
    public function getRecommendations(array $archetype): array
    {
        $key = $archetype['key'] ?? '';
        
        $recommendations = [
            'innocent' => [
                'financial_focus' => 'Building financial literacy and awareness',
                'action_steps' => [
                    'Start tracking expenses',
                    'Create a simple budget',
                    'Learn basic investment concepts'
                ],
                'caution' => 'Avoid being too trusting with financial advisors'
            ],
            'victim' => [
                'financial_focus' => 'Taking responsibility for financial situation',
                'action_steps' => [
                    'Identify one small financial goal',
                    'Celebrate financial wins',
                    'Seek supportive financial education'
                ],
                'caution' => 'Avoid blaming others for financial challenges'
            ],
            'warrior' => [
                'financial_focus' => 'Strategic wealth building',
                'action_steps' => [
                    'Set aggressive savings goals',
                    'Diversify investment portfolio',
                    'Track ROI on all investments'
                ],
                'caution' => 'Remember to enjoy life while building wealth'
            ],
            'martyr' => [
                'financial_focus' => 'Self-care and boundaries',
                'action_steps' => [
                    'Pay yourself first',
                    'Set limits on financial help to others',
                    'Build emergency fund'
                ],
                'caution' => 'Your financial security matters too'
            ],
            'fool' => [
                'financial_focus' => 'Creating structure and discipline',
                'action_steps' => [
                    'Automate savings',
                    'Use spending limits',
                    'Find fun in budgeting'
                ],
                'caution' => 'Balance spontaneity with planning'
            ],
            'creator' => [
                'financial_focus' => 'Monetizing creativity',
                'action_steps' => [
                    'Value your creative work appropriately',
                    'Build multiple income streams',
                    'Invest in your craft'
                ],
                'caution' => 'Don\'t undervalue your talents'
            ],
            'tyrant' => [
                'financial_focus' => 'Collaborative wealth building',
                'action_steps' => [
                    'Share financial decisions with partners',
                    'Invest in others\' success',
                    'Practice financial generosity'
                ],
                'caution' => 'Control can limit opportunities'
            ],
            'magician' => [
                'financial_focus' => 'Conscious wealth creation',
                'action_steps' => [
                    'Align money with values',
                    'Teach others financial wisdom',
                    'Create transformative wealth'
                ],
                'caution' => 'Stay grounded in practical matters'
            ]
        ];

        return $recommendations[$key] ?? [];
    }

    /**
     * Get insights for archetype
     */
    public function getInsights(array $archetype): array
    {
        $key = $archetype['key'] ?? '';
        
        $insights = [
            'innocent' => [
                'strengths' => ['Optimistic outlook', 'Trust in abundance', 'Open to learning'],
                'challenges' => ['May avoid financial reality', 'Can be taken advantage of', 'Lacks financial boundaries'],
                'growth_path' => 'Develop financial awareness while maintaining optimism'
            ],
            'victim' => [
                'strengths' => ['Awareness of obstacles', 'Empathy for struggles', 'Resourcefulness'],
                'challenges' => ['Gives away power', 'Blames external factors', 'Stuck in scarcity'],
                'growth_path' => 'Reclaim financial power through small victories'
            ],
            'warrior' => [
                'strengths' => ['Goal achievement', 'Discipline', 'Strategic thinking'],
                'challenges' => ['Work-life imbalance', 'Win-at-all-costs', 'Neglects relationships'],
                'growth_path' => 'Balance achievement with fulfillment'
            ],
            'martyr' => [
                'strengths' => ['Generous spirit', 'Caring nature', 'Service-oriented'],
                'challenges' => ['Self-neglect', 'Resentment', 'Poor boundaries'],
                'growth_path' => 'Practice receiving and self-care'
            ],
            'fool' => [
                'strengths' => ['Enjoys life', 'Risk-taker', 'Present-focused'],
                'challenges' => ['Poor planning', 'Impulsive spending', 'Avoids responsibility'],
                'growth_path' => 'Add structure without losing joy'
            ],
            'creator' => [
                'strengths' => ['Innovative thinking', 'Passionate work', 'Unique perspective'],
                'challenges' => ['Irregular income', 'Undervaluing work', 'Poor business sense'],
                'growth_path' => 'Merge creativity with business acumen'
            ],
            'tyrant' => [
                'strengths' => ['Leadership ability', 'Wealth creation', 'Decisive action'],
                'challenges' => ['Control issues', 'Relationship strain', 'Never enough'],
                'growth_path' => 'Use power to empower others'
            ],
            'magician' => [
                'strengths' => ['Balanced approach', 'Transforms situations', 'Wisdom'],
                'challenges' => ['May seem detached', 'Complex thinking', 'High expectations'],
                'growth_path' => 'Share wisdom while staying connected'
            ]
        ];

        return $insights[$key] ?? [];
    }

    /**
     * Determine dominant archetype from scores
     */
    private function determineDominantArchetype(array $scores): array
    {
        $maxScore = 0;
        $dominant = null;
        
        foreach ($scores as $key => $score) {
            if ($score > $maxScore) {
                $maxScore = $score;
                $dominant = $key;
            }
        }
        
        return [
            'key' => $dominant,
            'id' => $this->archetypes[$dominant]['id'],
            'name' => $this->archetypes[$dominant]['name'],
            'score' => $maxScore
        ];
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
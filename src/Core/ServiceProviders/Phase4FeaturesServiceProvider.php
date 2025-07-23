<?php
declare(strict_types=1);

namespace MoneyQuiz\Core\ServiceProviders;

use MoneyQuiz\Core\AbstractServiceProvider;

/**
 * Service provider for Phase 4 features
 */
class Phase4FeaturesServiceProvider extends AbstractServiceProvider
{
    /**
     * Register services
     */
    public function register(): void
    {
        // Load feature-specific providers
        $this->loadFeatureProviders();
    }

    /**
     * Load feature-specific service providers
     */
    private function loadFeatureProviders(): void
    {
        $providers = [
            'MoneyQuiz\Features\Quiz\Providers\QuizServiceProvider',
            'MoneyQuiz\Features\Question\Providers\QuestionServiceProvider',
            'MoneyQuiz\Features\Answer\Providers\AnswerServiceProvider',
            'MoneyQuiz\Features\Archetype\Providers\ArchetypeServiceProvider'
        ];

        foreach ($providers as $providerClass) {
            if (class_exists($providerClass)) {
                $provider = new $providerClass($this->container);
                $provider->register();
            }
        }
    }

    /**
     * Boot services
     */
    public function boot(): void
    {
        add_action('init', [$this, 'initializeFeatures']);
    }

    /**
     * Initialize features
     */
    public function initializeFeatures(): void
    {
        // Features will be initialized as needed
    }
}
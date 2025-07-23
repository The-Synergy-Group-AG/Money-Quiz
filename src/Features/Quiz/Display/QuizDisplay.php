<?php
declare(strict_types=1);

namespace MoneyQuiz\Features\Quiz\Display;

use MoneyQuiz\Domain\Entities\Quiz;
use MoneyQuiz\Domain\Entities\Attempt;
use MoneyQuiz\Features\Question\QuestionRepository;
use MoneyQuiz\Features\Quiz\Management\QuizSettings;
use MoneyQuiz\Security\OutputEscaper;

/**
 * Handles quiz display and rendering
 */
class QuizDisplay
{
    public function __construct(
        private QuestionRepository $questionRepository,
        private OutputEscaper $escaper,
        private QuizRenderer $renderer,
        private ProgressTracker $progressTracker,
        private TimerManager $timerManager
    ) {}

    /**
     * Display quiz start page
     */
    public function renderStartPage(Quiz $quiz): string
    {
        $data = [
            'quiz' => $this->prepareQuizData($quiz),
            'settings' => $quiz->getSettings(),
            'requires_registration' => $quiz->requiresRegistration()
        ];

        return $this->renderer->renderTemplate('quiz-start', $data);
    }

    /**
     * Display quiz question(s)
     */
    public function renderQuestions(Quiz $quiz, Attempt $attempt, int $currentPage = 1): string
    {
        $questions = $this->questionRepository->findByQuizId($quiz->getId());
        $settings = QuizSettings::fromArray($quiz->getSettings());
        
        // Get progress data
        $progress = $this->progressTracker->getProgress($attempt);
        
        // Get timer data if enabled
        $timerData = null;
        if ($quiz->getTimeLimit() > 0) {
            $timerData = $this->timerManager->getTimerData($attempt, $quiz->getTimeLimit());
        }

        // Prepare questions based on display mode
        if ($settings->getQuestionDisplay() === 'all') {
            return $this->renderAllQuestions($quiz, $questions, $progress, $timerData);
        } else {
            return $this->renderPagedQuestions($quiz, $questions, $currentPage, $progress, $timerData);
        }
    }

    /**
     * Render all questions on one page
     */
    private function renderAllQuestions($quiz, $questions, $progress, $timerData): string
    {
        $data = [
            'quiz' => $this->prepareQuizData($quiz),
            'questions' => array_map([$this, 'prepareQuestionData'], $questions),
            'progress' => $progress,
            'timer' => $timerData,
            'display_mode' => 'all'
        ];

        return $this->renderer->renderTemplate('quiz-questions-all', $data);
    }

    /**
     * Render questions with pagination
     */
    private function renderPagedQuestions($quiz, $questions, $page, $progress, $timerData): string
    {
        $questionsPerPage = 1; // One question per page
        $totalPages = count($questions);
        $currentQuestion = $questions[$page - 1] ?? null;

        if (!$currentQuestion) {
            throw new \Exception('Invalid question page');
        }

        $data = [
            'quiz' => $this->prepareQuizData($quiz),
            'question' => $this->prepareQuestionData($currentQuestion),
            'current_page' => $page,
            'total_pages' => $totalPages,
            'progress' => $progress,
            'timer' => $timerData,
            'display_mode' => 'paged',
            'can_go_back' => $this->canGoBack($quiz, $page),
            'is_last_page' => $page === $totalPages
        ];

        return $this->renderer->renderTemplate('quiz-question-single', $data);
    }

    /**
     * Prepare quiz data for display
     */
    private function prepareQuizData(Quiz $quiz): array
    {
        return [
            'id' => $quiz->getId(),
            'title' => $this->escaper->escapeHtml($quiz->getTitle()),
            'description' => $this->escaper->escapeHtml($quiz->getDescription()),
            'type' => $quiz->getType(),
            'time_limit' => $quiz->getTimeLimit(),
            'settings' => $quiz->getSettings()
        ];
    }

    /**
     * Prepare question data for display
     */
    private function prepareQuestionData($question): array
    {
        return [
            'id' => $question->getId(),
            'text' => $this->escaper->escapeHtml($question->getText()),
            'description' => $this->escaper->escapeHtml($question->getDescription()),
            'type' => $question->getType(),
            'required' => $question->isRequired(),
            'options' => $this->prepareOptions($question->getOptions()),
            'order' => $question->getOrder()
        ];
    }

    /**
     * Prepare question options for display
     */
    private function prepareOptions(array $options): array
    {
        return array_map(function($option) {
            return [
                'value' => $this->escaper->escapeAttr($option['value'] ?? ''),
                'text' => $this->escaper->escapeHtml($option['text'] ?? ''),
                'order' => $option['order'] ?? 0
            ];
        }, $options);
    }

    /**
     * Check if user can navigate back
     */
    private function canGoBack(Quiz $quiz, int $currentPage): bool
    {
        $settings = QuizSettings::fromArray($quiz->getSettings());
        $displayOptions = $settings->getDisplayOptions();
        
        return $currentPage > 1 && ($displayOptions['allow_back'] ?? false);
    }
}
<?php
declare(strict_types=1);

namespace MoneyQuiz\Features\Answer;

use MoneyQuiz\Domain\ValueObjects\Answer;
use MoneyQuiz\Domain\Repositories\AttemptRepository;
use MoneyQuiz\Features\Question\QuestionRepository;
use MoneyQuiz\Features\Question\Types\QuestionTypeFactory;
use MoneyQuiz\Security\InputValidator;
use MoneyQuiz\Application\Exceptions\ServiceException;

/**
 * Manages answer collection and validation
 */
class AnswerManager
{
    public function __construct(
        private AttemptRepository $attemptRepository,
        private QuestionRepository $questionRepository,
        private AnswerRepository $answerRepository,
        private QuestionTypeFactory $typeFactory,
        private InputValidator $validator,
        private AnswerValidator $answerValidator
    ) {}

    /**
     * Save answer for a question
     */
    public function saveAnswer(int $attemptId, int $questionId, $answer): Answer
    {
        $attempt = $this->attemptRepository->findById($attemptId);
        
        if (!$attempt) {
            throw new ServiceException('Attempt not found');
        }
        
        if ($attempt->isCompleted()) {
            throw new ServiceException('Cannot save answer for completed attempt');
        }
        
        $question = $this->questionRepository->findById($questionId);
        
        if (!$question) {
            throw new ServiceException('Question not found');
        }
        
        if ($question->getQuizId() !== $attempt->getQuizId()) {
            throw new ServiceException('Question does not belong to this quiz');
        }
        
        // Validate answer based on question type
        $validated = $this->answerValidator->validate($question, $answer);
        
        // Create answer value object
        $answerVO = new Answer(
            $questionId,
            $validated,
            current_time('mysql')
        );
        
        // Save to repository
        return $this->answerRepository->save($attemptId, $answerVO);
    }

    /**
     * Get all answers for an attempt
     */
    public function getAttemptAnswers(int $attemptId): array
    {
        return $this->answerRepository->findByAttemptId($attemptId);
    }

    /**
     * Get answer for specific question in attempt
     */
    public function getAnswer(int $attemptId, int $questionId): ?Answer
    {
        return $this->answerRepository->findByAttemptAndQuestion($attemptId, $questionId);
    }

    /**
     * Update existing answer
     */
    public function updateAnswer(int $attemptId, int $questionId, $answer): Answer
    {
        $existing = $this->getAnswer($attemptId, $questionId);
        
        if (!$existing) {
            return $this->saveAnswer($attemptId, $questionId, $answer);
        }
        
        $attempt = $this->attemptRepository->findById($attemptId);
        
        if ($attempt->isCompleted()) {
            throw new ServiceException('Cannot update answer for completed attempt');
        }
        
        $question = $this->questionRepository->findById($questionId);
        $validated = $this->answerValidator->validate($question, $answer);
        
        $answerVO = new Answer(
            $questionId,
            $validated,
            $existing->getTimestamp()
        );
        
        return $this->answerRepository->update($attemptId, $answerVO);
    }

    /**
     * Check if all required questions are answered
     */
    public function hasAllRequiredAnswers(int $attemptId): bool
    {
        $attempt = $this->attemptRepository->findById($attemptId);
        
        if (!$attempt) {
            throw new ServiceException('Attempt not found');
        }
        
        $questions = $this->questionRepository->findByQuizId($attempt->getQuizId());
        $answers = $this->getAttemptAnswers($attemptId);
        
        $answeredQuestions = array_map(fn($a) => $a->getQuestionId(), $answers);
        
        foreach ($questions as $question) {
            if ($question->isRequired() && !in_array($question->getId(), $answeredQuestions)) {
                return false;
            }
        }
        
        return true;
    }

    /**
     * Calculate score for answers
     */
    public function calculateScore(int $attemptId): array
    {
        $answers = $this->getAttemptAnswers($attemptId);
        
        if (empty($answers)) {
            return [
                'total_score' => 0,
                'max_score' => 0,
                'percentage' => 0,
                'details' => []
            ];
        }
        
        // Get attempt to find quiz ID
        $attempt = $this->attemptRepository->findById($attemptId);
        if (!$attempt) {
            throw new ServiceException('Attempt not found');
        }
        
        // Load all questions at once for better performance
        $questions = $this->questionRepository->findByQuizId($attempt->getQuizId());
        $questionMap = [];
        foreach ($questions as $question) {
            $questionMap[$question->getId()] = $question;
        }
        
        // Cache question types to avoid recreating them
        $typeCache = [];
        
        $totalScore = 0;
        $maxScore = 0;
        $details = [];
        
        foreach ($answers as $answer) {
            $questionId = $answer->getQuestionId();
            
            if (!isset($questionMap[$questionId])) {
                continue; // Skip answers for deleted questions
            }
            
            $question = $questionMap[$questionId];
            $questionType = $question->getType();
            
            // Use cached type instance or create new one
            if (!isset($typeCache[$questionType])) {
                $typeCache[$questionType] = $this->typeFactory->create($questionType);
            }
            $type = $typeCache[$questionType];
            
            $score = $type->calculateScore($question, $answer->getValue());
            $isCorrect = $type->isCorrect($question, $answer->getValue());
            
            $totalScore += $score;
            $maxScore += $question->getPoints();
            
            $details[] = [
                'question_id' => $question->getId(),
                'score' => $score,
                'max_score' => $question->getPoints(),
                'is_correct' => $isCorrect
            ];
        }
        
        return [
            'total_score' => $totalScore,
            'max_score' => $maxScore,
            'percentage' => $maxScore > 0 ? round(($totalScore / $maxScore) * 100, 2) : 0,
            'details' => $details
        ];
    }
}
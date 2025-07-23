<?php
declare(strict_types=1);

namespace MoneyQuiz\Features\Answer;

use MoneyQuiz\Domain\Entities\Question;
use MoneyQuiz\Security\InputValidator;
use MoneyQuiz\Application\Exceptions\ServiceException;
use MoneyQuiz\Features\Question\Types\QuestionTypeFactory;

/**
 * Validates answers based on question type
 */
class AnswerValidator
{
    public function __construct(
        private InputValidator $validator,
        private QuestionTypeFactory $typeFactory
    ) {}

    /**
     * Validate answer for a question
     */
    public function validate(Question $question, $answer)
    {
        // Basic validation
        if ($question->isRequired() && $this->isEmpty($answer)) {
            throw new ServiceException('Answer is required for this question');
        }

        // Get question type handler
        $type = $this->typeFactory->create($question->getType());

        // Type-specific validation
        if (!$type->validateAnswer($question, $answer)) {
            throw new ServiceException('Invalid answer format for question type: ' . $question->getType());
        }

        // Additional validation based on question type
        switch ($question->getType()) {
            case 'multiple_choice':
                return $this->validateMultipleChoice($question, $answer);
                
            case 'true_false':
                return $this->validateTrueFalse($answer);
                
            case 'ranking':
                return $this->validateRanking($question, $answer);
                
            default:
                return $answer;
        }
    }

    /**
     * Validate multiple choice answer
     */
    private function validateMultipleChoice(Question $question, $answer)
    {
        if (is_array($answer)) {
            // Multiple selection
            $validOptions = array_column($question->getOptions(), 'value');
            
            foreach ($answer as $selection) {
                if (!in_array($selection, $validOptions)) {
                    throw new ServiceException('Invalid option selected: ' . $selection);
                }
            }
            
            return $answer;
        } else {
            // Single selection
            $validOptions = array_column($question->getOptions(), 'value');
            
            if (!in_array($answer, $validOptions)) {
                throw new ServiceException('Invalid option selected: ' . $answer);
            }
            
            return $answer;
        }
    }

    /**
     * Validate true/false answer
     */
    private function validateTrueFalse($answer): string
    {
        $validated = $this->validator->validateString($answer, [
            'type' => 'string',
            'in' => ['true', 'false']
        ]);
        
        return $validated;
    }

    /**
     * Validate ranking answer
     */
    private function validateRanking(Question $question, $answer): array
    {
        if (!is_array($answer)) {
            throw new ServiceException('Ranking answer must be an array');
        }
        
        $options = $question->getOptions();
        $validIds = array_column($options, 'value');
        
        // Check all options are present
        if (count($answer) !== count($validIds)) {
            throw new ServiceException('All options must be ranked');
        }
        
        // Check for valid option IDs
        foreach ($answer as $rankedId) {
            if (!in_array($rankedId, $validIds)) {
                throw new ServiceException('Invalid option in ranking: ' . $rankedId);
            }
        }
        
        // Check for duplicates
        if (count($answer) !== count(array_unique($answer))) {
            throw new ServiceException('Duplicate options in ranking');
        }
        
        return $answer;
    }

    /**
     * Check if answer is empty
     */
    private function isEmpty($answer): bool
    {
        if (is_null($answer)) {
            return true;
        }
        
        if (is_string($answer)) {
            return trim($answer) === '';
        }
        
        if (is_array($answer)) {
            return empty($answer);
        }
        
        return false;
    }
}
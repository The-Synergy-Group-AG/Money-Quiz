# Entity Adapters

## Purpose

The adapter classes in this directory bridge the naming convention gap between:
- **Existing entities** (Phases 1-3): Use snake_case methods (e.g., `get_type()`, `is_required()`)
- **Phase 4 code**: Expects camelCase methods (e.g., `getType()`, `isRequired()`)

## Adapters Created

1. **QuestionAdapter**: Maps Question entity methods
   - `getType()` → `get_type()`
   - `isRequired()` → `is_required()`
   - Adds `getOptions()` for multiple choice formatting
   - Adds `getCorrectAnswer()` for answer checking

2. **QuizAdapter**: Maps Quiz entity methods
   - Extracts settings into individual methods
   - `getTimeLimit()` from settings['time_limit']
   - `requiresRegistration()` from settings['require_registration']

3. **AttemptAdapter**: Maps Attempt entity methods
   - `isCompleted()` → `is_completed()`
   - `getUserId()` → `get_user_id()`

4. **ArchetypeAdapter**: Maps Archetype entity methods
   - `getKey()` → `get_slug()`
   - Extracts characteristics into individual methods

5. **AnswerAdapter**: Maps Answer value object
   - Provides missing methods expected by Phase 4

## Usage

Instead of using entities directly in Phase 4 code:

```php
// Don't do this - will fail with undefined method
$question->getType(); 

// Do this - use adapter
$adapter = new QuestionAdapter($question);
$adapter->getType();
```

## Future Considerations

These adapters are a temporary solution. In a future refactoring:
1. Standardize on one naming convention (preferably camelCase for PSR compliance)
2. Update all entity methods to use consistent naming
3. Remove these adapter classes

For now, they ensure Phase 4 code works without modifying the existing entity structure.
# API Specification

## Document Control
- **Version**: 1.0
- **Last Updated**: 2025-07-23
- **Status**: Active
- **Owner**: Technical Architect

## Overview
This document defines the REST API and internal API specifications for Money Quiz v7.0, including endpoints, data formats, authentication, and integration patterns.

## API Architecture

### Design Principles
1. **RESTful**: Follow REST conventions
2. **Consistent**: Uniform response formats
3. **Secure**: Authentication and authorization
4. **Versioned**: Support API evolution
5. **Documented**: Self-documenting responses

### Base Configuration
```php
namespace MoneyQuiz\API;

class APIConfig {
    const VERSION = 'v1';
    const NAMESPACE = 'money-quiz/v1';
    const BASE_URL = '/wp-json/money-quiz/v1';
}
```

## REST API Endpoints

### Authentication
All endpoints require WordPress authentication unless specified as public.

```http
Authorization: Bearer {token}
X-WP-Nonce: {nonce}
```

### Quiz Endpoints

#### List Quizzes
```http
GET /wp-json/money-quiz/v1/quizzes
```

**Parameters:**
- `page` (int): Page number
- `per_page` (int): Items per page (max 100)
- `status` (string): draft|published|archived
- `category` (int): Category ID
- `search` (string): Search term

**Response:**
```json
{
    "success": true,
    "data": {
        "quizzes": [
            {
                "id": 1,
                "title": "Financial Literacy Quiz",
                "slug": "financial-literacy-quiz",
                "description": "Test your knowledge",
                "status": "published",
                "question_count": 10,
                "attempt_count": 150,
                "average_score": 75.5,
                "categories": [1, 3],
                "created_at": "2025-07-23T10:00:00Z",
                "updated_at": "2025-07-23T10:00:00Z"
            }
        ],
        "pagination": {
            "total": 25,
            "pages": 3,
            "current_page": 1,
            "per_page": 10
        }
    }
}
```

#### Get Single Quiz
```http
GET /wp-json/money-quiz/v1/quizzes/{id}
```

**Response:**
```json
{
    "success": true,
    "data": {
        "quiz": {
            "id": 1,
            "title": "Financial Literacy Quiz",
            "slug": "financial-literacy-quiz",
            "description": "Test your knowledge",
            "status": "published",
            "settings": {
                "time_limit": 1800,
                "passing_score": 70,
                "randomize_questions": true,
                "randomize_answers": true,
                "show_results": true,
                "allow_retakes": true
            },
            "questions": [
                {
                    "id": 1,
                    "question_text": "What is compound interest?",
                    "question_type": "multiple_choice",
                    "points": 1,
                    "answers": [
                        {
                            "id": 1,
                            "answer_text": "Interest on interest",
                            "order": 1
                        }
                    ]
                }
            ],
            "metadata": {
                "author": "Admin",
                "difficulty": "medium",
                "duration_estimate": "30 minutes"
            }
        }
    }
}
```

#### Create Quiz
```http
POST /wp-json/money-quiz/v1/quizzes
```

**Request Body:**
```json
{
    "title": "New Quiz",
    "description": "Quiz description",
    "status": "draft",
    "settings": {
        "time_limit": 1800,
        "passing_score": 70
    },
    "questions": [
        {
            "question_text": "Question 1?",
            "question_type": "multiple_choice",
            "points": 1,
            "answers": [
                {
                    "answer_text": "Answer 1",
                    "is_correct": true
                }
            ]
        }
    ]
}
```

#### Update Quiz
```http
PUT /wp-json/money-quiz/v1/quizzes/{id}
```

#### Delete Quiz
```http
DELETE /wp-json/money-quiz/v1/quizzes/{id}
```

### Question Endpoints

#### Add Question to Quiz
```http
POST /wp-json/money-quiz/v1/quizzes/{quiz_id}/questions
```

#### Update Question
```http
PUT /wp-json/money-quiz/v1/questions/{id}
```

#### Delete Question
```http
DELETE /wp-json/money-quiz/v1/questions/{id}
```

#### Reorder Questions
```http
POST /wp-json/money-quiz/v1/quizzes/{quiz_id}/questions/reorder
```

**Request Body:**
```json
{
    "order": [
        {"id": 3, "position": 1},
        {"id": 1, "position": 2},
        {"id": 2, "position": 3}
    ]
}
```

### Quiz Taking Endpoints (Public)

#### Start Quiz Attempt
```http
POST /wp-json/money-quiz/v1/public/quizzes/{id}/start
```

**Request Body:**
```json
{
    "user_info": {
        "name": "John Doe",
        "email": "john@example.com"
    }
}
```

**Response:**
```json
{
    "success": true,
    "data": {
        "attempt_id": "uuid-here",
        "session_token": "token-here",
        "quiz": {
            "id": 1,
            "title": "Financial Literacy Quiz",
            "time_limit": 1800,
            "question_count": 10
        },
        "first_question": {
            "id": 1,
            "question_text": "What is compound interest?",
            "answers": [...]
        }
    }
}
```

#### Submit Answer
```http
POST /wp-json/money-quiz/v1/public/attempts/{attempt_id}/answer
```

**Headers:**
```http
X-Quiz-Session: {session_token}
```

**Request Body:**
```json
{
    "question_id": 1,
    "answer_id": 3,
    "time_taken": 45
}
```

#### Get Next Question
```http
GET /wp-json/money-quiz/v1/public/attempts/{attempt_id}/next
```

#### Complete Quiz
```http
POST /wp-json/money-quiz/v1/public/attempts/{attempt_id}/complete
```

**Response:**
```json
{
    "success": true,
    "data": {
        "score": 8,
        "total": 10,
        "percentage": 80,
        "passed": true,
        "time_taken": 1250,
        "certificate_url": "/certificates/uuid.pdf"
    }
}
```

### Analytics Endpoints

#### Quiz Statistics
```http
GET /wp-json/money-quiz/v1/quizzes/{id}/statistics
```

**Parameters:**
- `period`: day|week|month|year|all
- `from`: ISO date
- `to`: ISO date

**Response:**
```json
{
    "success": true,
    "data": {
        "overview": {
            "total_attempts": 500,
            "completed_attempts": 450,
            "average_score": 75.5,
            "average_time": 890,
            "pass_rate": 68.5
        },
        "score_distribution": [
            {"range": "0-20", "count": 10},
            {"range": "21-40", "count": 25},
            {"range": "41-60", "count": 50},
            {"range": "61-80", "count": 150},
            {"range": "81-100", "count": 215}
        ],
        "daily_attempts": [
            {"date": "2025-07-23", "attempts": 45, "completions": 42}
        ]
    }
}
```

#### User Progress
```http
GET /wp-json/money-quiz/v1/users/{user_id}/progress
```

### Export/Import Endpoints

#### Export Quiz
```http
GET /wp-json/money-quiz/v1/quizzes/{id}/export
```

**Parameters:**
- `format`: json|csv|xml

#### Import Quiz
```http
POST /wp-json/money-quiz/v1/import
```

**Request:**
- Multipart form data with file upload

## Internal PHP APIs

### Service Layer APIs

#### Quiz Service
```php
namespace MoneyQuiz\Application\Services;

interface QuizServiceInterface {
    public function createQuiz(CreateQuizDTO $dto): Quiz;
    public function updateQuiz(int $id, UpdateQuizDTO $dto): Quiz;
    public function deleteQuiz(int $id): bool;
    public function getQuiz(int $id): ?Quiz;
    public function listQuizzes(QueryParams $params): QuizCollection;
    public function duplicateQuiz(int $id): Quiz;
    public function publishQuiz(int $id): bool;
}
```

#### Question Service
```php
interface QuestionServiceInterface {
    public function addQuestion(int $quizId, CreateQuestionDTO $dto): Question;
    public function updateQuestion(int $id, UpdateQuestionDTO $dto): Question;
    public function deleteQuestion(int $id): bool;
    public function reorderQuestions(int $quizId, array $order): bool;
    public function duplicateQuestion(int $id): Question;
}
```

#### Attempt Service
```php
interface AttemptServiceInterface {
    public function startAttempt(int $quizId, ?array $userInfo = null): Attempt;
    public function submitAnswer(string $attemptId, SubmitAnswerDTO $dto): bool;
    public function completeAttempt(string $attemptId): AttemptResult;
    public function getAttemptProgress(string $attemptId): AttemptProgress;
    public function abandonAttempt(string $attemptId): bool;
}
```

### Repository APIs

#### Quiz Repository
```php
namespace MoneyQuiz\Infrastructure\Repositories;

interface QuizRepositoryInterface {
    public function find(int $id): ?Quiz;
    public function findBySlug(string $slug): ?Quiz;
    public function findAll(array $criteria = []): array;
    public function save(Quiz $quiz): Quiz;
    public function delete(Quiz $quiz): bool;
    public function count(array $criteria = []): int;
}
```

### Event APIs

#### Quiz Events
```php
namespace MoneyQuiz\Domain\Events;

class QuizCreated extends DomainEvent {
    public function __construct(
        public readonly int $quizId,
        public readonly string $title,
        public readonly int $createdBy
    ) {}
}

class QuizCompleted extends DomainEvent {
    public function __construct(
        public readonly string $attemptId,
        public readonly int $quizId,
        public readonly float $score,
        public readonly bool $passed
    ) {}
}
```

## Error Handling

### Error Response Format
```json
{
    "success": false,
    "error": {
        "code": "QUIZ_NOT_FOUND",
        "message": "Quiz with ID 123 not found",
        "details": {
            "quiz_id": 123
        }
    }
}
```

### Error Codes
- `UNAUTHORIZED`: Authentication required
- `FORBIDDEN`: Insufficient permissions
- `NOT_FOUND`: Resource not found
- `VALIDATION_ERROR`: Invalid input data
- `RATE_LIMITED`: Too many requests
- `SERVER_ERROR`: Internal server error

## Rate Limiting

### Configuration
```php
'rate_limits' => [
    'public' => [
        'attempts' => 100,
        'window' => 3600 // 1 hour
    ],
    'authenticated' => [
        'attempts' => 1000,
        'window' => 3600
    ]
]
```

### Headers
```http
X-RateLimit-Limit: 100
X-RateLimit-Remaining: 95
X-RateLimit-Reset: 1627580400
```

## Webhooks

### Webhook Events
- `quiz.created`
- `quiz.updated`
- `quiz.deleted`
- `attempt.started`
- `attempt.completed`
- `user.enrolled`

### Webhook Payload
```json
{
    "event": "attempt.completed",
    "timestamp": "2025-07-23T10:00:00Z",
    "data": {
        "attempt_id": "uuid",
        "quiz_id": 1,
        "user_id": 5,
        "score": 85,
        "passed": true
    }
}
```

## API Versioning

### Version Strategy
- URL path versioning: `/v1/`, `/v2/`
- Backward compatibility for 2 major versions
- Deprecation warnings in headers

### Deprecation Header
```http
X-API-Deprecation: true
X-API-Deprecation-Date: 2026-01-01
X-API-Deprecation-Info: https://docs.moneyquiz.com/api/v2
```

## Security

### Authentication Methods
1. **WordPress Nonce**: For logged-in users
2. **API Keys**: For external integrations
3. **OAuth 2.0**: For third-party apps (future)

### Permissions
```php
'permissions' => [
    'manage_quizzes' => ['administrator', 'editor'],
    'create_quizzes' => ['administrator', 'editor', 'author'],
    'take_quizzes' => ['all'],
    'view_analytics' => ['administrator']
]
```

## Testing

### API Testing with PHPUnit
```php
public function test_create_quiz_api() {
    $response = $this->actingAs($this->admin)
        ->postJson('/wp-json/money-quiz/v1/quizzes', [
            'title' => 'Test Quiz',
            'status' => 'draft'
        ]);
    
    $response->assertStatus(201)
        ->assertJson(['success' => true]);
}
```

## Related Documents
- [Architecture Overview](./00-architecture-overview.md)
- [Database Schema](./02-database-schema.md)
- [Security Architecture](./01-security-architecture.md)
- [Integration Design](./04-integration-design.md)
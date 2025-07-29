# Money Quiz Microservices Architecture

## Overview

This document outlines the strategic extraction of Money Quiz functionality into microservices, enabling scalability, independent deployment, and technology flexibility.

## Architecture Vision

```
┌─────────────────────────────────────────────────────────────────┐
│                         API Gateway                              │
│                    (GraphQL + REST + gRPC)                       │
└─────────────┬───────────────┬───────────────┬──────────────────┘
              │               │               │
              ▼               ▼               ▼
        ┌──────────┐    ┌──────────┐    ┌──────────┐
        │  Quiz    │    │ Analytics│    │  Email   │
        │ Service  │    │ Service  │    │ Service  │
        └──────────┘    └──────────┘    └──────────┘
              │               │               │
              ▼               ▼               ▼
        ┌──────────┐    ┌──────────┐    ┌──────────┐
        │ MongoDB  │    │ClickHouse│    │  Redis   │
        └──────────┘    └──────────┘    └──────────┘
```

## Service Decomposition

### 1. Quiz Service
**Responsibility**: Core quiz functionality
**Technology**: PHP 8.1 + Swoole
**Database**: MongoDB
**Communication**: gRPC + REST

```yaml
# docker-compose.yml
quiz-service:
  build: ./services/quiz
  environment:
    - MONGODB_URI=mongodb://mongo:27017/quiz
    - GRPC_PORT=50051
    - HTTP_PORT=8001
  ports:
    - "50051:50051"
    - "8001:8001"
```

**Key Components**:
- Quiz management
- Question handling
- Answer processing
- Result calculation

### 2. Analytics Service
**Responsibility**: Data analytics and reporting
**Technology**: Python + FastAPI
**Database**: ClickHouse
**Communication**: GraphQL + Kafka

```yaml
analytics-service:
  build: ./services/analytics
  environment:
    - CLICKHOUSE_URI=clickhouse://clickhouse:8123
    - KAFKA_BROKERS=kafka:9092
    - HTTP_PORT=8002
  ports:
    - "8002:8002"
```

**Key Components**:
- Real-time analytics
- Historical reporting
- Machine learning insights
- Data aggregation

### 3. Email Service
**Responsibility**: All email communications
**Technology**: Node.js + TypeScript
**Queue**: RabbitMQ
**Communication**: AMQP + REST

```yaml
email-service:
  build: ./services/email
  environment:
    - RABBITMQ_URI=amqp://rabbitmq:5672
    - SMTP_HOST=${SMTP_HOST}
    - HTTP_PORT=8003
  ports:
    - "8003:8003"
```

**Key Components**:
- Template management
- Queue processing
- Delivery tracking
- Bounce handling

### 4. User Service
**Responsibility**: User and prospect management
**Technology**: Go + Gin
**Database**: PostgreSQL
**Communication**: gRPC + REST

```yaml
user-service:
  build: ./services/user
  environment:
    - POSTGRES_URI=postgres://postgres:5432/users
    - GRPC_PORT=50052
    - HTTP_PORT=8004
  ports:
    - "50052:50052"
    - "8004:8004"
```

**Key Components**:
- User profiles
- Authentication
- Prospect management
- GDPR compliance

### 5. Storage Service
**Responsibility**: File and media management
**Technology**: Rust + Actix
**Storage**: S3-compatible
**Communication**: REST + gRPC

```yaml
storage-service:
  build: ./services/storage
  environment:
    - S3_ENDPOINT=${S3_ENDPOINT}
    - S3_BUCKET=${S3_BUCKET}
    - HTTP_PORT=8005
  ports:
    - "8005:8005"
```

## Service Communication

### 1. Synchronous Communication

#### gRPC Protocol Buffers
```protobuf
// quiz.proto
syntax = "proto3";

package moneyquiz.quiz.v1;

service QuizService {
  rpc GetQuiz(GetQuizRequest) returns (Quiz);
  rpc SubmitQuiz(SubmitQuizRequest) returns (QuizResult);
  rpc GetStatistics(GetStatisticsRequest) returns (Statistics);
}

message Quiz {
  string id = 1;
  string title = 2;
  repeated Question questions = 3;
  QuizSettings settings = 4;
}

message Question {
  string id = 1;
  string text = 2;
  repeated Option options = 3;
}
```

### 2. Asynchronous Communication

#### Event Bus (Kafka)
```php
// Event Publisher
class QuizCompletedPublisher {
    public function publish(QuizResult $result): void {
        $event = [
            'event_type' => 'quiz.completed',
            'event_id' => Uuid::v4(),
            'timestamp' => time(),
            'data' => [
                'quiz_id' => $result->getQuizId(),
                'user_id' => $result->getUserId(),
                'archetype_id' => $result->getArchetypeId(),
                'score' => $result->getScore(),
            ],
        ];
        
        $this->kafka->produce('quiz-events', $event);
    }
}
```

### 3. Service Discovery

#### Consul Integration
```php
// Service Registration
class ServiceRegistry {
    public function register(): void {
        $this->consul->register([
            'ID' => 'quiz-service-' . gethostname(),
            'Name' => 'quiz-service',
            'Tags' => ['v1', 'grpc', 'rest'],
            'Address' => getenv('SERVICE_IP'),
            'Port' => (int)getenv('GRPC_PORT'),
            'Check' => [
                'GRPC' => getenv('SERVICE_IP') . ':' . getenv('GRPC_PORT'),
                'Interval' => '10s',
            ],
        ]);
    }
}
```

## Data Management

### 1. Database per Service

Each service owns its data:
- **Quiz Service**: MongoDB for flexible schema
- **Analytics Service**: ClickHouse for time-series
- **User Service**: PostgreSQL for relational data
- **Email Service**: Redis for queue state

### 2. Event Sourcing

```php
// Event Store
class QuizEventStore {
    public function append(DomainEvent $event): void {
        $this->eventStore->appendToStream(
            'quiz-' . $event->getAggregateId(),
            [
                'event_type' => $event->getType(),
                'event_data' => $event->getData(),
                'metadata' => [
                    'user_id' => $event->getUserId(),
                    'timestamp' => $event->getTimestamp(),
                ],
            ]
        );
    }
}
```

### 3. CQRS Pattern

```php
// Command Handler
class SubmitQuizCommandHandler {
    public function handle(SubmitQuizCommand $command): void {
        // Business logic
        $result = $this->quizService->process($command);
        
        // Emit events
        $this->eventBus->publish(new QuizCompletedEvent($result));
        
        // Update read model
        $this->projectionService->project($result);
    }
}

// Query Handler
class GetQuizStatisticsQueryHandler {
    public function handle(GetStatisticsQuery $query): Statistics {
        return $this->readModel->getStatistics(
            $query->getQuizId(),
            $query->getDateRange()
        );
    }
}
```

## Deployment Strategy

### 1. Container Orchestration (Kubernetes)

```yaml
# quiz-service-deployment.yaml
apiVersion: apps/v1
kind: Deployment
metadata:
  name: quiz-service
spec:
  replicas: 3
  selector:
    matchLabels:
      app: quiz-service
  template:
    metadata:
      labels:
        app: quiz-service
    spec:
      containers:
      - name: quiz-service
        image: moneyquiz/quiz-service:latest
        ports:
        - containerPort: 50051
          name: grpc
        - containerPort: 8001
          name: http
        env:
        - name: MONGODB_URI
          valueFrom:
            secretKeyRef:
              name: mongodb-secret
              key: uri
        livenessProbe:
          grpc:
            port: 50051
          initialDelaySeconds: 10
        readinessProbe:
          httpGet:
            path: /health
            port: 8001
          initialDelaySeconds: 5
```

### 2. Service Mesh (Istio)

```yaml
# virtual-service.yaml
apiVersion: networking.istio.io/v1beta1
kind: VirtualService
metadata:
  name: quiz-service
spec:
  hosts:
  - quiz-service
  http:
  - match:
    - headers:
        x-version:
          exact: v2
    route:
    - destination:
        host: quiz-service
        subset: v2
      weight: 100
  - route:
    - destination:
        host: quiz-service
        subset: v1
      weight: 90
    - destination:
        host: quiz-service
        subset: v2
      weight: 10
```

### 3. CI/CD Pipeline

```yaml
# .github/workflows/deploy.yml
name: Deploy Microservices

on:
  push:
    branches: [main]

jobs:
  deploy:
    runs-on: ubuntu-latest
    steps:
    - uses: actions/checkout@v3
    
    - name: Build and Push Quiz Service
      run: |
        docker build -t moneyquiz/quiz-service:${{ github.sha }} ./services/quiz
        docker push moneyquiz/quiz-service:${{ github.sha }}
    
    - name: Deploy to Kubernetes
      run: |
        kubectl set image deployment/quiz-service \
          quiz-service=moneyquiz/quiz-service:${{ github.sha }}
```

## Monitoring and Observability

### 1. Distributed Tracing (Jaeger)

```php
// Trace Initialization
class TracingMiddleware {
    public function handle($request, $next) {
        $span = $this->tracer->startSpan('quiz.submit', [
            'tags' => [
                'quiz.id' => $request->getQuizId(),
                'user.id' => $request->getUserId(),
            ],
        ]);
        
        try {
            $response = $next($request);
            $span->setTag('result.archetype', $response->getArchetype());
            return $response;
        } finally {
            $span->finish();
        }
    }
}
```

### 2. Metrics (Prometheus)

```php
// Metrics Collection
class MetricsCollector {
    private Histogram $quizDuration;
    private Counter $quizCompletions;
    
    public function recordQuizCompletion(float $duration, string $archetype): void {
        $this->quizDuration->observe($duration, ['archetype' => $archetype]);
        $this->quizCompletions->inc(['archetype' => $archetype]);
    }
}
```

### 3. Centralized Logging (ELK Stack)

```php
// Structured Logging
class StructuredLogger {
    public function logQuizEvent(string $event, array $context): void {
        $this->logger->info($event, [
            'service' => 'quiz-service',
            'version' => getenv('SERVICE_VERSION'),
            'trace_id' => $this->getTraceId(),
            'user_id' => $context['user_id'] ?? null,
            'quiz_id' => $context['quiz_id'] ?? null,
            'timestamp' => microtime(true),
        ]);
    }
}
```

## Security Architecture

### 1. API Gateway Security

```nginx
# nginx.conf
location /api/v1/ {
    # Rate limiting
    limit_req zone=api_limit burst=20 nodelay;
    
    # JWT validation
    auth_jwt "API";
    auth_jwt_key_file /etc/nginx/jwt.key;
    
    # CORS headers
    add_header 'Access-Control-Allow-Origin' '$http_origin';
    
    # Security headers
    add_header X-Content-Type-Options nosniff;
    add_header X-Frame-Options DENY;
    add_header X-XSS-Protection "1; mode=block";
    
    proxy_pass http://api-gateway:8000;
}
```

### 2. Service-to-Service Authentication

```php
// mTLS Implementation
class ServiceAuthMiddleware {
    public function authenticate($request): bool {
        $cert = $request->getClientCertificate();
        
        if (!$cert) {
            return false;
        }
        
        // Verify certificate chain
        return $this->certificateValidator->validate($cert) &&
               $this->certificateValidator->isServiceAllowed(
                   $cert->getSubject(),
                   $request->getTargetService()
               );
    }
}
```

### 3. Secret Management (Vault)

```php
// Secret Retrieval
class VaultSecretManager {
    public function getDatabaseCredentials(string $service): array {
        $path = "secret/data/{$service}/database";
        $response = $this->vault->read($path);
        
        return [
            'host' => $response['data']['host'],
            'username' => $response['data']['username'],
            'password' => $response['data']['password'],
        ];
    }
}
```

## Migration Strategy

### Phase 1: Strangler Fig Pattern
1. Deploy microservices alongside monolith
2. Route new features to microservices
3. Gradually migrate existing features

### Phase 2: Data Migration
1. Set up change data capture (CDC)
2. Sync data to microservice databases
3. Switch reads to microservices
4. Switch writes to microservices

### Phase 3: Monolith Retirement
1. Remove migrated code from monolith
2. Convert monolith to thin WordPress plugin
3. Maintain only presentation layer

## Benefits

1. **Scalability**: Scale services independently
2. **Technology Diversity**: Use best tool for each job
3. **Deployment Independence**: Deploy services separately
4. **Fault Isolation**: Service failures don't cascade
5. **Team Autonomy**: Teams own their services

## Challenges & Solutions

| Challenge | Solution |
|-----------|----------|
| Network Latency | Service mesh, caching, GraphQL batching |
| Data Consistency | Event sourcing, saga pattern |
| Service Discovery | Consul, Kubernetes DNS |
| Monitoring Complexity | Distributed tracing, centralized logging |
| Development Complexity | Docker Compose, Skaffold |

## Cost Analysis

### Infrastructure Costs (Monthly)
- Kubernetes Cluster: $300
- Database Services: $200
- Monitoring Stack: $150
- CI/CD Pipeline: $50
- **Total**: ~$700/month

### ROI Calculation
- Reduced downtime: $5,000/month saved
- Faster feature delivery: 40% increase
- Reduced maintenance: 30% decrease
- **Payback Period**: 2 months

## Conclusion

The microservices architecture provides Money Quiz with:
- **Scalability** for millions of users
- **Flexibility** for rapid feature development
- **Reliability** through fault isolation
- **Performance** through specialized services

This architecture positions Money Quiz for long-term growth while maintaining the ability to evolve with changing requirements.
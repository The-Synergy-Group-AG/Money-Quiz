# Database Schema

## Document Control
- **Version**: 1.0
- **Last Updated**: 2025-07-23
- **Status**: Active
- **Owner**: Technical Architect

## Overview
This document defines the database schema for Money Quiz v7.0, designed for optimal performance, security, and maintainability within WordPress.

## Schema Design Principles

### Core Principles
1. **WordPress Integration**: Leverage WordPress tables where appropriate
2. **Normalization**: 3NF for data integrity
3. **Performance**: Optimized indexes for common queries
4. **Security**: No sensitive data in plain text
5. **Scalability**: Designed for growth

### Naming Conventions
- Table prefix: `{wp_prefix}money_quiz_`
- Column names: `snake_case`
- Foreign keys: `{table}_id`
- Timestamps: `created_at`, `updated_at`

## Core Tables

### 1. Quizzes Table
```sql
CREATE TABLE {prefix}money_quiz_quizzes (
    id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
    title varchar(255) NOT NULL,
    description text,
    slug varchar(255) NOT NULL,
    status varchar(20) NOT NULL DEFAULT 'draft',
    settings longtext, -- JSON
    created_by bigint(20) unsigned NOT NULL,
    created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY slug (slug),
    KEY status (status),
    KEY created_by (created_by),
    CONSTRAINT fk_quiz_creator FOREIGN KEY (created_by) 
        REFERENCES {prefix}users(ID) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

### 2. Questions Table
```sql
CREATE TABLE {prefix}money_quiz_questions (
    id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
    quiz_id bigint(20) unsigned NOT NULL,
    question_text text NOT NULL,
    question_type varchar(50) NOT NULL DEFAULT 'multiple_choice',
    explanation text,
    order_position int(11) NOT NULL DEFAULT 0,
    points decimal(10,2) NOT NULL DEFAULT 1.00,
    time_limit int(11) DEFAULT NULL,
    metadata longtext, -- JSON
    created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY quiz_id (quiz_id),
    KEY order_position (order_position),
    CONSTRAINT fk_question_quiz FOREIGN KEY (quiz_id) 
        REFERENCES {prefix}money_quiz_quizzes(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

### 3. Answers Table
```sql
CREATE TABLE {prefix}money_quiz_answers (
    id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
    question_id bigint(20) unsigned NOT NULL,
    answer_text text NOT NULL,
    is_correct tinyint(1) NOT NULL DEFAULT 0,
    order_position int(11) NOT NULL DEFAULT 0,
    feedback text,
    created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY question_id (question_id),
    KEY is_correct (is_correct),
    CONSTRAINT fk_answer_question FOREIGN KEY (question_id) 
        REFERENCES {prefix}money_quiz_questions(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

### 4. Quiz Attempts Table
```sql
CREATE TABLE {prefix}money_quiz_attempts (
    id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
    quiz_id bigint(20) unsigned NOT NULL,
    user_id bigint(20) unsigned DEFAULT NULL,
    session_id varchar(64) NOT NULL,
    status varchar(20) NOT NULL DEFAULT 'in_progress',
    score decimal(10,2) DEFAULT NULL,
    percentage decimal(5,2) DEFAULT NULL,
    time_taken int(11) DEFAULT NULL,
    ip_address varchar(45) DEFAULT NULL,
    user_agent text,
    started_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
    completed_at datetime DEFAULT NULL,
    metadata longtext, -- JSON
    PRIMARY KEY (id),
    KEY quiz_id (quiz_id),
    KEY user_id (user_id),
    KEY session_id (session_id),
    KEY status (status),
    KEY started_at (started_at),
    CONSTRAINT fk_attempt_quiz FOREIGN KEY (quiz_id) 
        REFERENCES {prefix}money_quiz_quizzes(id) ON DELETE CASCADE,
    CONSTRAINT fk_attempt_user FOREIGN KEY (user_id) 
        REFERENCES {prefix}users(ID) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

### 5. Attempt Answers Table
```sql
CREATE TABLE {prefix}money_quiz_attempt_answers (
    id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
    attempt_id bigint(20) unsigned NOT NULL,
    question_id bigint(20) unsigned NOT NULL,
    answer_id bigint(20) unsigned DEFAULT NULL,
    answer_text text, -- For open-ended questions
    is_correct tinyint(1) NOT NULL DEFAULT 0,
    points_earned decimal(10,2) NOT NULL DEFAULT 0.00,
    time_taken int(11) DEFAULT NULL,
    answered_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY attempt_question (attempt_id, question_id),
    KEY answer_id (answer_id),
    CONSTRAINT fk_attempt_answer_attempt FOREIGN KEY (attempt_id) 
        REFERENCES {prefix}money_quiz_attempts(id) ON DELETE CASCADE,
    CONSTRAINT fk_attempt_answer_question FOREIGN KEY (question_id) 
        REFERENCES {prefix}money_quiz_questions(id) ON DELETE CASCADE,
    CONSTRAINT fk_attempt_answer_answer FOREIGN KEY (answer_id) 
        REFERENCES {prefix}money_quiz_answers(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

### 6. Categories Table
```sql
CREATE TABLE {prefix}money_quiz_categories (
    id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
    name varchar(255) NOT NULL,
    slug varchar(255) NOT NULL,
    description text,
    parent_id bigint(20) unsigned DEFAULT NULL,
    created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY slug (slug),
    KEY parent_id (parent_id),
    CONSTRAINT fk_category_parent FOREIGN KEY (parent_id) 
        REFERENCES {prefix}money_quiz_categories(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

### 7. Quiz Categories Junction Table
```sql
CREATE TABLE {prefix}money_quiz_quiz_categories (
    quiz_id bigint(20) unsigned NOT NULL,
    category_id bigint(20) unsigned NOT NULL,
    PRIMARY KEY (quiz_id, category_id),
    KEY category_id (category_id),
    CONSTRAINT fk_quiz_cat_quiz FOREIGN KEY (quiz_id) 
        REFERENCES {prefix}money_quiz_quizzes(id) ON DELETE CASCADE,
    CONSTRAINT fk_quiz_cat_category FOREIGN KEY (category_id) 
        REFERENCES {prefix}money_quiz_categories(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

### 8. Email Templates Table
```sql
CREATE TABLE {prefix}money_quiz_email_templates (
    id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
    name varchar(255) NOT NULL,
    slug varchar(255) NOT NULL,
    subject varchar(255) NOT NULL,
    body longtext NOT NULL,
    variables text, -- JSON array of available variables
    is_active tinyint(1) NOT NULL DEFAULT 1,
    created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY slug (slug),
    KEY is_active (is_active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

## Indexes Strategy

### Performance Indexes
```sql
-- Common queries optimization
CREATE INDEX idx_quiz_status_created ON {prefix}money_quiz_quizzes(status, created_at);
CREATE INDEX idx_attempt_user_date ON {prefix}money_quiz_attempts(user_id, started_at);
CREATE INDEX idx_attempt_quiz_status ON {prefix}money_quiz_attempts(quiz_id, status);
```

### Reporting Indexes
```sql
-- Analytics and reporting
CREATE INDEX idx_attempt_completed ON {prefix}money_quiz_attempts(completed_at) 
    WHERE completed_at IS NOT NULL;
CREATE INDEX idx_attempt_score ON {prefix}money_quiz_attempts(quiz_id, percentage) 
    WHERE status = 'completed';
```

## Data Types Justification

### ID Fields
- `bigint(20) unsigned`: Supports large scale, matches WordPress

### String Fields
- `varchar(255)`: Standard for titles, names
- `text`: Unlimited for content
- `longtext`: For JSON data and large content

### Numeric Fields
- `decimal(10,2)`: Precise scoring
- `int(11)`: Time in seconds, positions

### Date Fields
- `datetime`: Timezone handled at application level

## Migration Strategy

### Initial Setup
```php
public function create_tables() {
    global $wpdb;
    $charset_collate = $wpdb->get_charset_collate();
    
    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    
    // Create tables in dependency order
    dbDelta($this->get_quizzes_table_sql());
    dbDelta($this->get_categories_table_sql());
    dbDelta($this->get_questions_table_sql());
    // ... etc
}
```

### Version Management
```php
public function check_db_version() {
    $current_version = get_option('money_quiz_db_version', '0');
    
    if (version_compare($current_version, MONEY_QUIZ_DB_VERSION, '<')) {
        $this->run_migrations($current_version, MONEY_QUIZ_DB_VERSION);
    }
}
```

## Security Considerations

### Data Protection
1. **No plain text passwords**: Use WordPress user system
2. **IP anonymization**: Optional GDPR compliance
3. **Data retention**: Configurable cleanup policies
4. **Prepared statements**: All queries use placeholders

### Access Control
```sql
-- Row-level security via application layer
-- Example: User can only see their attempts
SELECT * FROM {prefix}money_quiz_attempts 
WHERE user_id = %d OR session_id = %s
```

## Performance Optimizations

### Query Patterns
```sql
-- Efficient quiz loading with questions
SELECT q.*, 
    COUNT(DISTINCT qu.id) as question_count,
    COUNT(DISTINCT a.id) as attempt_count
FROM {prefix}money_quiz_quizzes q
LEFT JOIN {prefix}money_quiz_questions qu ON q.id = qu.quiz_id
LEFT JOIN {prefix}money_quiz_attempts a ON q.id = a.quiz_id
WHERE q.status = 'published'
GROUP BY q.id
```

### Caching Strategy
1. **Object Cache**: Quiz structure
2. **Transients**: Leaderboards, statistics
3. **Query Cache**: Via MySQL

## Maintenance Operations

### Regular Cleanup
```sql
-- Remove old incomplete attempts
DELETE FROM {prefix}money_quiz_attempts 
WHERE status = 'in_progress' 
AND started_at < DATE_SUB(NOW(), INTERVAL 24 HOUR);

-- Archive old data
INSERT INTO {prefix}money_quiz_attempts_archive 
SELECT * FROM {prefix}money_quiz_attempts 
WHERE completed_at < DATE_SUB(NOW(), INTERVAL 1 YEAR);
```

### Statistics Tables (Optional)
```sql
-- Denormalized for performance
CREATE TABLE {prefix}money_quiz_statistics (
    quiz_id bigint(20) unsigned NOT NULL,
    stat_date date NOT NULL,
    attempts_count int(11) NOT NULL DEFAULT 0,
    completions_count int(11) NOT NULL DEFAULT 0,
    average_score decimal(5,2) DEFAULT NULL,
    average_time int(11) DEFAULT NULL,
    PRIMARY KEY (quiz_id, stat_date)
) ENGINE=InnoDB;
```

## Related Documents
- [Architecture Overview](./00-architecture-overview.md)
- [API Specification](./03-api-specification.md)
- [Performance Design](./05-performance-design.md)
- [Security Architecture](./01-security-architecture.md)
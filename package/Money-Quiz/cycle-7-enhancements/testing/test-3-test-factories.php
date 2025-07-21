<?php
/**
 * Test Data Factories
 * 
 * @package MoneyQuiz\Testing
 * @version 1.0.0
 */

namespace MoneyQuiz\Testing;

/**
 * Test Factory Manager
 */
class TestFactories {
    
    private static $instance = null;
    private $factories = [];
    
    private function __construct() {
        $this->registerFactories();
    }
    
    /**
     * Get singleton instance
     */
    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Register factories
     */
    private function registerFactories() {
        $this->factories = [
            'quiz' => new QuizFactory(),
            'question' => new QuestionFactory(),
            'result' => new ResultFactory(),
            'user' => new UserFactory()
        ];
    }
    
    /**
     * Get factory
     */
    public function get($type) {
        return $this->factories[$type] ?? null;
    }
    
    /**
     * Create entity
     */
    public function create($type, $args = []) {
        $factory = $this->get($type);
        return $factory ? $factory->create($args) : null;
    }
    
    /**
     * Create multiple entities
     */
    public function createMany($type, $count, $args = []) {
        $factory = $this->get($type);
        return $factory ? $factory->createMany($count, $args) : [];
    }
}

/**
 * Base Factory
 */
abstract class BaseFactory {
    
    protected $defaults = [];
    protected $sequences = [];
    
    /**
     * Create entity
     */
    public function create($args = []) {
        $data = $this->generate($args);
        return $this->persist($data);
    }
    
    /**
     * Create multiple
     */
    public function createMany($count, $args = []) {
        $entities = [];
        for ($i = 0; $i < $count; $i++) {
            $entities[] = $this->create($args);
        }
        return $entities;
    }
    
    /**
     * Generate data
     */
    protected function generate($args = []) {
        $data = array_merge($this->defaults, $args);
        
        // Apply sequences
        foreach ($this->sequences as $field => $callback) {
            if (!isset($args[$field])) {
                $data[$field] = call_user_func($callback);
            }
        }
        
        return $data;
    }
    
    /**
     * Persist entity
     */
    abstract protected function persist($data);
    
    /**
     * Add sequence
     */
    protected function sequence($field, $callback) {
        $this->sequences[$field] = $callback;
    }
}

/**
 * Quiz Factory
 */
class QuizFactory extends BaseFactory {
    
    private $counter = 0;
    
    public function __construct() {
        $this->defaults = [
            'status' => 'published',
            'settings' => [],
            'created_at' => current_time('mysql')
        ];
        
        $this->sequence('title', function() {
            return 'Test Quiz ' . ++$this->counter;
        });
        
        $this->sequence('description', function() {
            return 'Description for test quiz ' . $this->counter;
        });
    }
    
    protected function persist($data) {
        global $wpdb;
        
        $data['settings'] = json_encode($data['settings']);
        $data['created_by'] = $data['created_by'] ?? get_current_user_id();
        
        $wpdb->insert($wpdb->prefix . 'money_quiz_quizzes', $data);
        
        return $wpdb->insert_id;
    }
    
    /**
     * Create with questions
     */
    public function createWithQuestions($questionCount = 5, $args = []) {
        $quiz_id = $this->create($args);
        
        $questionFactory = new QuestionFactory();
        for ($i = 1; $i <= $questionCount; $i++) {
            $questionFactory->create([
                'quiz_id' => $quiz_id,
                'order_num' => $i
            ]);
        }
        
        return $quiz_id;
    }
}

/**
 * Question Factory
 */
class QuestionFactory extends BaseFactory {
    
    private $counter = 0;
    
    public function __construct() {
        $this->defaults = [
            'type' => 'multiple',
            'points' => 10,
            'order_num' => 1
        ];
        
        $this->sequence('text', function() {
            return 'Test Question ' . ++$this->counter . '?';
        });
        
        $this->sequence('options', function() {
            return ['Option A', 'Option B', 'Option C', 'Option D'];
        });
        
        $this->sequence('correct_answer', function() {
            return 'Option A';
        });
    }
    
    protected function persist($data) {
        global $wpdb;
        
        if (is_array($data['options'])) {
            $data['options'] = json_encode($data['options']);
        }
        
        $wpdb->insert($wpdb->prefix . 'money_quiz_questions', $data);
        
        return $wpdb->insert_id;
    }
}

/**
 * Result Factory
 */
class ResultFactory extends BaseFactory {
    
    public function __construct() {
        $this->defaults = [
            'score' => 80,
            'time_taken' => 300,
            'ip_address' => '127.0.0.1',
            'user_agent' => 'Mozilla/5.0 Test',
            'completed_at' => current_time('mysql')
        ];
    }
    
    protected function persist($data) {
        global $wpdb;
        
        // Ensure required fields
        if (!isset($data['quiz_id'])) {
            $quizFactory = new QuizFactory();
            $data['quiz_id'] = $quizFactory->create();
        }
        
        if (!isset($data['user_id'])) {
            $data['user_id'] = get_current_user_id() ?: 0;
        }
        
        if (!isset($data['answers'])) {
            $data['answers'] = $this->generateAnswers($data['quiz_id']);
        }
        
        if (is_array($data['answers'])) {
            $data['answers'] = json_encode($data['answers']);
        }
        
        $wpdb->insert($wpdb->prefix . 'money_quiz_results', $data);
        
        return $wpdb->insert_id;
    }
    
    /**
     * Generate random answers
     */
    private function generateAnswers($quiz_id) {
        global $wpdb;
        
        $questions = $wpdb->get_results($wpdb->prepare(
            "SELECT id, options FROM {$wpdb->prefix}money_quiz_questions WHERE quiz_id = %d",
            $quiz_id
        ));
        
        $answers = [];
        foreach ($questions as $question) {
            $options = json_decode($question->options, true);
            $answers[$question->id] = $options[array_rand($options)];
        }
        
        return $answers;
    }
}

/**
 * User Factory
 */
class UserFactory extends BaseFactory {
    
    private $counter = 0;
    
    public function __construct() {
        $this->defaults = [
            'role' => 'subscriber',
            'user_pass' => 'password123'
        ];
        
        $this->sequence('user_login', function() {
            return 'testuser' . ++$this->counter;
        });
        
        $this->sequence('user_email', function() {
            return 'testuser' . $this->counter . '@example.com';
        });
        
        $this->sequence('display_name', function() {
            return 'Test User ' . $this->counter;
        });
    }
    
    protected function persist($data) {
        $user_id = wp_insert_user($data);
        
        if (!is_wp_error($user_id)) {
            // Mark as test user
            add_user_meta($user_id, 'test_user', true);
        }
        
        return $user_id;
    }
    
    /**
     * Create with quiz results
     */
    public function createWithResults($resultCount = 3) {
        $user_id = $this->create();
        
        $resultFactory = new ResultFactory();
        for ($i = 0; $i < $resultCount; $i++) {
            $resultFactory->create([
                'user_id' => $user_id,
                'score' => rand(50, 100)
            ]);
        }
        
        return $user_id;
    }
}

/**
 * Factory helper functions
 */
if (!function_exists('money_quiz_test_factory')) {
    function money_quiz_test_factory($type) {
        return TestFactories::getInstance()->get($type);
    }
}

if (!function_exists('money_quiz_test_create')) {
    function money_quiz_test_create($type, $args = []) {
        return TestFactories::getInstance()->create($type, $args);
    }
}

if (!function_exists('money_quiz_test_create_many')) {
    function money_quiz_test_create_many($type, $count, $args = []) {
        return TestFactories::getInstance()->createMany($type, $count, $args);
    }
}
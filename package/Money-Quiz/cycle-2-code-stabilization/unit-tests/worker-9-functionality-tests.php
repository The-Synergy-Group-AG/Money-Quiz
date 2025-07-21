<?php
/**
 * Worker 9: Core Functionality Unit Tests
 * Framework: PHPUnit
 * Focus: Testing core business logic and stability improvements
 */

use PHPUnit\Framework\TestCase;

class MoneyQuizFunctionalityTest extends TestCase {
    
    /**
     * Test percentage calculation (division by zero fix)
     */
    public function testPercentageCalculation() {
        // Test normal calculation
        $percentage = get_percentage(10, 80);
        $this->assertEquals(100, $percentage);
        
        // Test division by zero (previously crashed)
        $percentage = get_percentage(0, 0);
        $this->assertEquals(0, $percentage);
        
        // Test with negative values
        $percentage = get_percentage(-5, 40);
        $this->assertEquals(0, $percentage);
        
        // Test percentage bounds (0-100)
        $percentage = get_percentage(10, 100);
        $this->assertLessThanOrEqual(100, $percentage);
        $this->assertGreaterThanOrEqual(0, $percentage);
    }
    
    /**
     * Test safe math operations
     */
    public function testSafeMathOperations() {
        // Test safe division
        $result = MoneyQuizMathHelper::safeDivide(10, 2);
        $this->assertEquals(5, $result);
        
        // Test division by zero
        $result = MoneyQuizMathHelper::safeDivide(10, 0, -1);
        $this->assertEquals(-1, $result);
        
        // Test with non-numeric values
        $result = MoneyQuizMathHelper::safeDivide('abc', 'def', 0);
        $this->assertEquals(0, $result);
        
        // Test percentage calculation
        $percentage = MoneyQuizMathHelper::calculatePercentage(25, 100);
        $this->assertEquals(25, $percentage);
        
        // Test percentage with zero total
        $percentage = MoneyQuizMathHelper::calculatePercentage(25, 0);
        $this->assertEquals(0, $percentage);
        
        // Test average calculation
        $avg = MoneyQuizMathHelper::calculateAverage([10, 20, 30]);
        $this->assertEquals(20, $avg);
        
        // Test average with empty array
        $avg = MoneyQuizMathHelper::calculateAverage([]);
        $this->assertEquals(0, $avg);
    }
    
    /**
     * Test input validation
     */
    public function testInputValidation() {
        $validator = MoneyQuizFrontendValidator::getInstance();
        
        // Test valid prospect data
        $valid_data = array(
            'prospect_data' => array(
                'Name' => 'John',
                'Surname' => 'Doe',
                'Email' => 'john@example.com',
                'Telephone' => '+1234567890',
                'Newsletter' => '1',
                'Consultation' => '0'
            )
        );
        
        $is_valid = $validator->validateQuizSubmission($valid_data);
        $this->assertTrue($is_valid);
        
        // Test invalid email
        $invalid_data = $valid_data;
        $invalid_data['prospect_data']['Email'] = 'invalid-email';
        
        $is_valid = $validator->validateQuizSubmission($invalid_data);
        $this->assertFalse($is_valid);
        
        $errors = $validator->getErrors();
        $this->assertArrayHasKey('email', $errors);
        
        // Test disposable email
        $disposable_data = $valid_data;
        $disposable_data['prospect_data']['Email'] = 'test@tempmail.com';
        
        $is_valid = $validator->validateQuizSubmission($disposable_data);
        $this->assertFalse($is_valid);
    }
    
    /**
     * Test archetype score calculation
     */
    public function testArchetypeScoreCalculation() {
        // Mock quiz results
        $results = array(
            (object)array('Archetype' => 1, 'Score' => 8),
            (object)array('Archetype' => 1, 'Score' => 7),
            (object)array('Archetype' => 5, 'Score' => 9),
            (object)array('Archetype' => 5, 'Score' => 6),
            (object)array('Archetype' => 9, 'Score' => 10)
        );
        
        $scores = mq_get_archetype_scores($results, null);
        
        // Check scores are calculated correctly
        $this->assertEquals(15, $scores[1]); // 8 + 7
        $this->assertEquals(15, $scores[5]); // 9 + 6
        $this->assertEquals(10, $scores[9]); // 10
        $this->assertEquals(0, $scores[13]); // Not in results
        
        // Test with invalid input
        $scores = mq_get_archetype_scores('not_an_array', null);
        $this->assertIsArray($scores);
        $this->assertEquals(0, array_sum($scores));
    }
    
    /**
     * Test error handling
     */
    public function testErrorHandling() {
        // Test safe quiz execution
        $result = mq_safe_quiz_execution(function() {
            return 'success';
        });
        $this->assertEquals('success', $result);
        
        // Test with exception
        $result = mq_safe_quiz_execution(function() {
            throw new Exception('Test error');
        }, 'Custom error message');
        
        $this->assertInstanceOf(WP_Error::class, $result);
        $this->assertEquals('quiz_error', $result->get_error_code());
        $this->assertEquals('Custom error message', $result->get_error_message());
    }
    
    /**
     * Test safe array operations
     */
    public function testSafeArrayOperations() {
        // Test safe increment
        $array = array();
        mq_safe_increment($array, 'key1', 5);
        $this->assertEquals(5, $array['key1']);
        
        mq_safe_increment($array, 'key1', 3);
        $this->assertEquals(8, $array['key1']);
        
        // Test with non-array
        $not_array = 'string';
        mq_safe_increment($not_array, 'key', 1);
        $this->assertIsArray($not_array);
        $this->assertEquals(1, $not_array['key']);
        
        // Test safe array merge
        $arr1 = array('a' => 1, 'b' => 2);
        $arr2 = array('c' => 3, 'd' => 4);
        $merged = mq_safe_array_merge($arr1, $arr2);
        
        $this->assertCount(4, $merged);
        $this->assertEquals(1, $merged['a']);
        $this->assertEquals(4, $merged['d']);
        
        // Test with non-arrays
        $merged = mq_safe_array_merge('not_array', null);
        $this->assertIsArray($merged);
        $this->assertEmpty($merged);
    }
    
    /**
     * Test session handling
     */
    public function testSessionHandling() {
        // Test safe session start
        mq_safe_session_start();
        $this->assertNotEquals(PHP_SESSION_NONE, session_status());
        
        // Test session value operations
        mq_set_session_value('test_key', 'test_value');
        $value = mq_get_session_value('test_key');
        $this->assertEquals('test_value', $value);
        
        // Test non-existent key
        $value = mq_get_session_value('non_existent', 'default');
        $this->assertEquals('default', $value);
    }
    
    /**
     * Test URL parameter handling
     */
    public function testUrlParameterHandling() {
        // Mock $_GET
        $_GET['test_param'] = 'test_value';
        $_GET['int_param'] = '123';
        
        // Test text parameter
        $value = mq_get('test_param');
        $this->assertEquals('test_value', $value);
        
        // Test integer parameter
        $value = mq_get('int_param', 0, 'int');
        $this->assertIsInt($value);
        $this->assertEquals(123, $value);
        
        // Test non-existent parameter
        $value = mq_get('non_existent', 'default');
        $this->assertEquals('default', $value);
        
        // Test query var helper
        $_REQUEST['request_param'] = 'request_value';
        $value = mq_get_query_var('request_param');
        $this->assertEquals('request_value', $value);
    }
    
    /**
     * Test email functionality
     */
    public function testEmailFunctionality() {
        // Mock wp_mail
        if (!function_exists('wp_mail')) {
            function wp_mail($to, $subject, $message, $headers = '') {
                return filter_var($to, FILTER_VALIDATE_EMAIL) !== false;
            }
        }
        
        // Test successful email
        $result = mq_send_email_safe('test@example.com', 'Test Subject', 'Test Message');
        $this->assertTrue($result);
        
        // Test invalid email
        $result = mq_send_email_safe('invalid-email', 'Test Subject', 'Test Message');
        $this->assertFalse($result);
    }
    
    /**
     * Test JSON operations
     */
    public function testJsonOperations() {
        // Test valid JSON
        $data = array('key' => 'value', 'number' => 123);
        $json = json_encode($data);
        $decoded = mq_json_decode_safe($json);
        
        $this->assertIsArray($decoded);
        $this->assertEquals('value', $decoded['key']);
        $this->assertEquals(123, $decoded['number']);
        
        // Test invalid JSON
        $decoded = mq_json_decode_safe('invalid json');
        $this->assertNull($decoded);
        
        // Test non-string input
        $decoded = mq_json_decode_safe(array());
        $this->assertNull($decoded);
    }
}

/**
 * Test suite for admin functionality
 */
class MoneyQuizAdminFunctionalityTest extends TestCase {
    
    /**
     * Test admin validation
     */
    public function testAdminValidation() {
        $validator = MoneyQuizAdminValidator::getInstance();
        
        // Test valid question
        $valid_question = array(
            'question' => 'This is a valid test question?',
            'money_type' => 3,
            'archetype' => 13
        );
        
        $is_valid = $validator->validateQuestion($valid_question);
        $this->assertTrue($is_valid);
        
        // Test invalid question (too short)
        $invalid_question = array(
            'question' => 'Short?',
            'money_type' => 3,
            'archetype' => 13
        );
        
        $is_valid = $validator->validateQuestion($invalid_question);
        $this->assertFalse($is_valid);
        
        $errors = $validator->getErrors();
        $this->assertArrayHasKey('question', $errors);
    }
    
    /**
     * Test settings validation
     */
    public function testSettingsValidation() {
        $validator = MoneyQuizAdminValidator::getInstance();
        
        // Test valid settings
        $valid_settings = array(
            'admin_email' => 'admin@example.com',
            'questions_per_page' => 10,
            'quiz_time_limit' => 600
        );
        
        $is_valid = $validator->validateSettings($valid_settings);
        $this->assertTrue($is_valid);
        
        // Test invalid email
        $invalid_settings = $valid_settings;
        $invalid_settings['admin_email'] = 'not-an-email';
        
        $is_valid = $validator->validateSettings($invalid_settings);
        $this->assertFalse($is_valid);
    }
    
    /**
     * Test import validation
     */
    public function testImportValidation() {
        $validator = MoneyQuizAdminValidator::getInstance();
        
        // Create test CSV file
        $test_file = sys_get_temp_dir() . '/test_import.csv';
        $csv_content = "question,money_type,archetype\n";
        $csv_content .= "Test question 1?,1,5\n";
        $csv_content .= "Test question 2?,2,9\n";
        
        file_put_contents($test_file, $csv_content);
        
        // Test valid CSV
        $is_valid = $validator->validateImportData($test_file, 'csv');
        $this->assertTrue($is_valid);
        
        // Clean up
        unlink($test_file);
        
        // Test non-existent file
        $is_valid = $validator->validateImportData('/non/existent/file.csv', 'csv');
        $this->assertFalse($is_valid);
    }
}

/**
 * Test suite for database operations
 */
class MoneyQuizDatabaseTest extends TestCase {
    
    /**
     * Test safe database operations
     */
    public function testSafeDatabaseOperations() {
        global $wpdb;
        $wpdb = $this->createMock('wpdb');
        
        // Test successful operation
        $wpdb->last_error = '';
        $result = mq_safe_db_operation(function() {
            return array('test' => 'data');
        });
        
        $this->assertIsArray($result);
        $this->assertEquals('data', $result['test']);
        
        // Test failed operation
        $wpdb->last_error = 'Database error';
        $result = mq_safe_db_operation(function() {
            return false;
        }, 'default');
        
        $this->assertEquals('default', $result);
    }
    
    /**
     * Test quiz result processing
     */
    public function testQuizResultProcessing() {
        global $wpdb, $table_prefix;
        $wpdb = $this->createMock('wpdb');
        $table_prefix = 'wp_';
        
        // Mock database results
        $mock_results = array(
            (object)array('Score' => 8, 'Archetype' => 1),
            (object)array('Score' => 7, 'Archetype' => 5),
            (object)array('Score' => 9, 'Archetype' => 1),
            (object)array('Score' => 6, 'Archetype' => 9)
        );
        
        $wpdb->expects($this->once())
            ->method('get_results')
            ->willReturn($mock_results);
        
        $processed = mq_process_quiz_results(123);
        
        $this->assertIsArray($processed);
        $this->assertEquals(30, $processed['total_score']); // 8+7+9+6
        $this->assertEquals(4, $processed['question_count']);
        $this->assertEquals(75, $processed['percentage']); // 30/40 * 100
    }
}
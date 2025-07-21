<?php
/**
 * Database Security for MoneyQuiz Plugin
 * 
 * Enhanced database operations with prepared statements
 * 
 * @package MoneyQuiz
 * @version 1.0.0
 */

class Money_Quiz_Database_Security {
    
    /**
     * Insert record with prepared statement
     * 
     * @param string $table Table name
     * @param array $data Data to insert
     * @param array $format Format specifiers
     * @return int|false Insert ID or false on failure
     */
    public static function secure_insert($table, $data, $format = null) {
        global $wpdb;
        
        // Validate table name
        $table = self::validate_table_name($table);
        if (!$table) {
            return false;
        }
        
        // Sanitize data
        $data = self::sanitize_data($data);
        
        // Use WordPress prepared statement
        $result = $wpdb->insert($table, $data, $format);
        
        if ($result === false) {
            self::log_database_error($wpdb->last_error, 'INSERT', $table);
            return false;
        }
        
        return $wpdb->insert_id;
    }
    
    /**
     * Update record with prepared statement
     * 
     * @param string $table Table name
     * @param array $data Data to update
     * @param array $where Where conditions
     * @param array $format Format specifiers
     * @param array $where_format Where format specifiers
     * @return int|false Number of affected rows or false on failure
     */
    public static function secure_update($table, $data, $where, $format = null, $where_format = null) {
        global $wpdb;
        
        // Validate table name
        $table = self::validate_table_name($table);
        if (!$table) {
            return false;
        }
        
        // Sanitize data
        $data = self::sanitize_data($data);
        $where = self::sanitize_data($where);
        
        // Use WordPress prepared statement
        $result = $wpdb->update($table, $data, $where, $format, $where_format);
        
        if ($result === false) {
            self::log_database_error($wpdb->last_error, 'UPDATE', $table);
            return false;
        }
        
        return $result;
    }
    
    /**
     * Delete record with prepared statement
     * 
     * @param string $table Table name
     * @param array $where Where conditions
     * @param array $where_format Where format specifiers
     * @return int|false Number of affected rows or false on failure
     */
    public static function secure_delete($table, $where, $where_format = null) {
        global $wpdb;
        
        // Validate table name
        $table = self::validate_table_name($table);
        if (!$table) {
            return false;
        }
        
        // Sanitize where conditions
        $where = self::sanitize_data($where);
        
        // Use WordPress prepared statement
        $result = $wpdb->delete($table, $where, $where_format);
        
        if ($result === false) {
            self::log_database_error($wpdb->last_error, 'DELETE', $table);
            return false;
        }
        
        return $result;
    }
    
    /**
     * Get single row with prepared statement
     * 
     * @param string $query SQL query
     * @param array $args Query arguments
     * @param string $output_type Output type
     * @return object|array|null Row data or null on failure
     */
    public static function secure_get_row($query, $args = [], $output_type = OBJECT) {
        global $wpdb;
        
        // Validate query
        if (!self::validate_query($query)) {
            return null;
        }
        
        // Prepare and execute query
        $prepared_query = $wpdb->prepare($query, ...$args);
        $result = $wpdb->get_row($prepared_query, $output_type);
        
        if ($result === null && $wpdb->last_error) {
            self::log_database_error($wpdb->last_error, 'SELECT', 'get_row');
        }
        
        return $result;
    }
    
    /**
     * Get multiple rows with prepared statement
     * 
     * @param string $query SQL query
     * @param array $args Query arguments
     * @param string $output_type Output type
     * @return array|false Rows data or false on failure
     */
    public static function secure_get_results($query, $args = [], $output_type = OBJECT) {
        global $wpdb;
        
        // Validate query
        if (!self::validate_query($query)) {
            return false;
        }
        
        // Prepare and execute query
        $prepared_query = $wpdb->prepare($query, ...$args);
        $result = $wpdb->get_results($prepared_query, $output_type);
        
        if ($result === false && $wpdb->last_error) {
            self::log_database_error($wpdb->last_error, 'SELECT', 'get_results');
        }
        
        return $result;
    }
    
    /**
     * Get single value with prepared statement
     * 
     * @param string $query SQL query
     * @param array $args Query arguments
     * @return mixed Value or null on failure
     */
    public static function secure_get_var($query, $args = []) {
        global $wpdb;
        
        // Validate query
        if (!self::validate_query($query)) {
            return null;
        }
        
        // Prepare and execute query
        $prepared_query = $wpdb->prepare($query, ...$args);
        $result = $wpdb->get_var($prepared_query);
        
        if ($result === null && $wpdb->last_error) {
            self::log_database_error($wpdb->last_error, 'SELECT', 'get_var');
        }
        
        return $result;
    }
    
    /**
     * Execute query with prepared statement
     * 
     * @param string $query SQL query
     * @param array $args Query arguments
     * @return int|false Number of affected rows or false on failure
     */
    public static function secure_query($query, $args = []) {
        global $wpdb;
        
        // Validate query
        if (!self::validate_query($query)) {
            return false;
        }
        
        // Prepare and execute query
        $prepared_query = $wpdb->prepare($query, ...$args);
        $result = $wpdb->query($prepared_query);
        
        if ($result === false && $wpdb->last_error) {
            self::log_database_error($wpdb->last_error, 'QUERY', 'query');
        }
        
        return $result;
    }
    
    /**
     * Validate table name
     * 
     * @param string $table Table name
     * @return string|false Validated table name or false
     */
    private static function validate_table_name($table) {
        // Remove any SQL injection attempts
        $table = preg_replace('/[^a-zA-Z0-9_]/', '', $table);
        
        // Check if table exists in allowed list
        $allowed_tables = [
            'moneyquiz_coach',
            'moneyquiz_results',
            'moneyquiz_prospects',
            'moneyquiz_taken',
            'moneyquiz_master',
            'moneyquiz_archetypes',
            'moneyquiz_question_screen',
            'moneyquiz_money_layout',
            'moneyquiz_email_setting',
            'moneyquiz_quiz_result',
            'moneyquiz_recaptcha',
            'moneyquiz_cta',
            'moneyquiz_archive_type_tag_line',
            'moneyquiz_error_logs'
        ];
        
        $table_name = str_replace($wpdb->prefix, '', $table);
        
        if (!in_array($table_name, $allowed_tables)) {
            self::log_security_violation('Invalid table name: ' . $table);
            return false;
        }
        
        return $wpdb->prefix . $table_name;
    }
    
    /**
     * Validate SQL query
     * 
     * @param string $query SQL query
     * @return bool True if valid
     */
    private static function validate_query($query) {
        // Check for dangerous SQL keywords
        $dangerous_keywords = [
            'DROP',
            'TRUNCATE',
            'DELETE FROM',
            'UPDATE',
            'INSERT INTO',
            'CREATE',
            'ALTER',
            'EXEC',
            'EXECUTE'
        ];
        
        $query_upper = strtoupper($query);
        
        foreach ($dangerous_keywords as $keyword) {
            if (strpos($query_upper, $keyword) !== false) {
                self::log_security_violation('Dangerous SQL keyword detected: ' . $keyword);
                return false;
            }
        }
        
        return true;
    }
    
    /**
     * Sanitize data for database operations
     * 
     * @param array $data Data to sanitize
     * @return array Sanitized data
     */
    private static function sanitize_data($data) {
        $sanitized = [];
        
        foreach ($data as $key => $value) {
            $sanitized_key = sanitize_key($key);
            
            if (is_string($value)) {
                $sanitized[$sanitized_key] = sanitize_text_field($value);
            } elseif (is_int($value)) {
                $sanitized[$sanitized_key] = intval($value);
            } elseif (is_float($value)) {
                $sanitized[$sanitized_key] = floatval($value);
            } elseif (is_bool($value)) {
                $sanitized[$sanitized_key] = (bool) $value;
            } else {
                $sanitized[$sanitized_key] = sanitize_text_field((string) $value);
            }
        }
        
        return $sanitized;
    }
    
    /**
     * Log database error
     * 
     * @param string $error Error message
     * @param string $operation Operation type
     * @param string $table Table name
     */
    private static function log_database_error($error, $operation, $table) {
        if (class_exists('Money_Quiz_Error_Handler')) {
            Money_Quiz_Error_Handler::log_error(
                "Database error in $operation operation on table $table: $error",
                Money_Quiz_Error_Handler::SEVERITY_HIGH,
                [
                    'operation' => $operation,
                    'table' => $table,
                    'error' => $error
                ]
            );
        }
    }
    
    /**
     * Log security violation
     * 
     * @param string $violation Violation description
     */
    private static function log_security_violation($violation) {
        if (class_exists('Money_Quiz_Error_Handler')) {
            Money_Quiz_Error_Handler::log_error(
                "Security violation: $violation",
                Money_Quiz_Error_Handler::SEVERITY_CRITICAL,
                [
                    'violation' => $violation,
                    'ip_address' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
                    'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown'
                ]
            );
        }
    }
    
    /**
     * Create table with proper security
     * 
     * @param string $table_name Table name
     * @param string $sql SQL for table creation
     * @return bool True on success
     */
    public static function secure_create_table($table_name, $sql) {
        global $wpdb;
        
        // Validate table name
        $table_name = self::validate_table_name($table_name);
        if (!$table_name) {
            return false;
        }
        
        // Use WordPress dbDelta for safe table creation
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
        
        // Check if table was created successfully
        $table_exists = $wpdb->get_var("SHOW TABLES LIKE '$table_name'") == $table_name;
        
        if (!$table_exists) {
            self::log_database_error('Failed to create table', 'CREATE', $table_name);
            return false;
        }
        
        return true;
    }
    
    /**
     * Drop table with proper security
     * 
     * @param string $table_name Table name
     * @return bool True on success
     */
    public static function secure_drop_table($table_name) {
        global $wpdb;
        
        // Validate table name
        $table_name = self::validate_table_name($table_name);
        if (!$table_name) {
            return false;
        }
        
        // Use prepared statement for safe table deletion
        $result = $wpdb->query($wpdb->prepare("DROP TABLE IF EXISTS %s", $table_name));
        
        if ($result === false) {
            self::log_database_error('Failed to drop table', 'DROP', $table_name);
            return false;
        }
        
        return true;
    }
} 
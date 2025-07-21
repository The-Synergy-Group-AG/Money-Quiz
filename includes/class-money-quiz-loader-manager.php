<?php
/**
 * Money Quiz Loader Manager
 * 
 * Prevents duplicate loading of classes and provides comprehensive tracking
 * to ensure we NEVER have loading conflicts again.
 * 
 * @package MoneyQuiz
 * @version 1.0.0
 */

class Money_Quiz_Loader_Manager {
    
    /**
     * Track loaded classes to prevent duplicates
     */
    private static $loaded_classes = [];
    
    /**
     * Track loading attempts for debugging
     */
    private static $loading_attempts = [];
    
    /**
     * Singleton instance
     */
    private static $instance = null;
    
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
     * Safely load a class file with duplicate prevention
     * 
     * @param string $file_path Path to the class file
     * @param string $class_name Expected class name
     * @param string $context Context for debugging
     * @return bool Success status
     */
    public static function safeLoad($file_path, $class_name, $context = 'unknown') {
        $full_path = plugin_dir_path(dirname(__FILE__)) . $file_path;
        
        // Track loading attempt
        self::$loading_attempts[] = [
            'file' => $file_path,
            'class' => $class_name,
            'context' => $context,
            'timestamp' => current_time('mysql'),
            'backtrace' => debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 3)
        ];
        
        // Check if already loaded
        if (self::isClassLoaded($class_name)) {
            error_log("MoneyQuiz Loader: Class {$class_name} already loaded, skipping duplicate load from {$context}");
            return true; // Already loaded, consider it success
        }
        
        // Check if file exists
        if (!file_exists($full_path)) {
            error_log("MoneyQuiz Loader: File not found: {$full_path} (context: {$context})");
            return false;
        }
        
        // Load the file
        try {
            require_once($full_path);
            
            // Verify class exists after loading
            if (class_exists($class_name)) {
                self::$loaded_classes[$class_name] = [
                    'file' => $file_path,
                    'context' => $context,
                    'timestamp' => current_time('mysql'),
                    'success' => true
                ];
                
                error_log("MoneyQuiz Loader: Successfully loaded {$class_name} from {$file_path} (context: {$context})");
                return true;
            } else {
                error_log("MoneyQuiz Loader: File loaded but class {$class_name} not found in {$file_path} (context: {$context})");
                return false;
            }
        } catch (Exception $e) {
            error_log("MoneyQuiz Loader: Error loading {$class_name} from {$file_path}: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Check if a class is already loaded
     */
    public static function isClassLoaded($class_name) {
        return class_exists($class_name) || isset(self::$loaded_classes[$class_name]);
    }
    
    /**
     * Get loading statistics
     */
    public static function getLoadingStats() {
        return [
            'loaded_classes' => count(self::$loaded_classes),
            'loading_attempts' => count(self::$loading_attempts),
            'classes' => self::$loaded_classes,
            'attempts' => self::$loading_attempts
        ];
    }
    
    /**
     * Get detailed loading report
     */
    public static function getLoadingReport() {
        $report = "=== MoneyQuiz Loader Report ===\n";
        $report .= "Loaded Classes: " . count(self::$loaded_classes) . "\n";
        $report .= "Loading Attempts: " . count(self::$loading_attempts) . "\n\n";
        
        $report .= "LOADED CLASSES:\n";
        foreach (self::$loaded_classes as $class => $info) {
            $report .= "- {$class} (from {$info['file']}, context: {$info['context']})\n";
        }
        
        $report .= "\nLOADING ATTEMPTS:\n";
        foreach (self::$loading_attempts as $attempt) {
            $report .= "- {$attempt['class']} from {$attempt['file']} (context: {$attempt['context']})\n";
        }
        
        return $report;
    }
    
    /**
     * Reset loading tracking (for testing)
     */
    public static function reset() {
        self::$loaded_classes = [];
        self::$loading_attempts = [];
    }
    
    /**
     * Validate loading integrity
     */
    public static function validateIntegrity() {
        $issues = [];
        
        // Check for duplicate loading attempts
        $class_attempts = [];
        foreach (self::$loading_attempts as $attempt) {
            $class = $attempt['class'];
            if (!isset($class_attempts[$class])) {
                $class_attempts[$class] = [];
            }
            $class_attempts[$class][] = $attempt;
        }
        
        foreach ($class_attempts as $class => $attempts) {
            if (count($attempts) > 1) {
                $issues[] = "Multiple loading attempts for class: {$class} (prevented by loader manager)";
            }
        }
        
        // Check if any classes failed to load
        $failed_loads = array_filter(self::$loading_attempts, function($attempt) {
            return !isset(self::$loaded_classes[$attempt['class']]);
        });
        
        foreach ($failed_loads as $attempt) {
            $issues[] = "Failed to load class: {$attempt['class']} from {$attempt['file']}";
        }
        
        return [
            'valid' => empty($issues),
            'issues' => $issues,
            'total_classes' => count(self::$loaded_classes),
            'total_attempts' => count(self::$loading_attempts),
            'duplicate_attempts_prevented' => count(array_filter($class_attempts, function($attempts) {
                return count($attempts) > 1;
            }))
        ];
    }
} 
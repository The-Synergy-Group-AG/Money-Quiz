<?php
/**
 * Database Schema Updater for Enhanced Features
 * 
 * @package MoneyQuiz
 * @version 1.0.0
 */

class Money_Quiz_DB_Updater {
    
    private static $db_version = '2.0.0';
    
    /**
     * Run database updates
     */
    public static function update() {
        global $wpdb;
        
        $current_version = get_option('money_quiz_db_version', '1.0.0');
        
        if (version_compare($current_version, self::$db_version, '<')) {
            require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
            
            // Create new tables
            self::create_analytics_tables();
            self::create_ai_tables();
            self::create_security_tables();
            self::create_performance_tables();
            self::create_webhook_tables();
            
            // Update existing tables
            self::update_existing_tables();
            
            // Update version
            update_option('money_quiz_db_version', self::$db_version);
        }
    }
    
    /**
     * Create analytics tables
     */
    private static function create_analytics_tables() {
        global $wpdb;
        $charset_collate = $wpdb->get_charset_collate();
        
        // Analytics Events
        $table_name = $wpdb->prefix . 'money_quiz_analytics_events';
        $sql = "CREATE TABLE IF NOT EXISTS $table_name (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            event_type varchar(50) NOT NULL,
            event_data longtext,
            user_id bigint(20) DEFAULT 0,
            session_id varchar(100),
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY event_type (event_type),
            KEY user_id (user_id),
            KEY created_at (created_at)
        ) $charset_collate;";
        dbDelta($sql);
        
        // Analytics Metrics
        $table_name = $wpdb->prefix . 'money_quiz_analytics_metrics';
        $sql = "CREATE TABLE IF NOT EXISTS $table_name (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            metric_name varchar(100) NOT NULL,
            metric_value decimal(10,2),
            metric_date date NOT NULL,
            dimension varchar(100),
            PRIMARY KEY (id),
            UNIQUE KEY metric_unique (metric_name, metric_date, dimension),
            KEY metric_date (metric_date)
        ) $charset_collate;";
        dbDelta($sql);
    }
    
    /**
     * Create AI/ML tables
     */
    private static function create_ai_tables() {
        global $wpdb;
        $charset_collate = $wpdb->get_charset_collate();
        
        // AI Predictions
        $table_name = $wpdb->prefix . 'money_quiz_ai_predictions';
        $sql = "CREATE TABLE IF NOT EXISTS $table_name (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            user_id bigint(20) NOT NULL,
            prediction_type varchar(50) NOT NULL,
            prediction_value text,
            confidence decimal(5,2),
            model_version varchar(20),
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY user_id (user_id),
            KEY prediction_type (prediction_type)
        ) $charset_collate;";
        dbDelta($sql);
        
        // ML Training Data
        $table_name = $wpdb->prefix . 'money_quiz_ml_training';
        $sql = "CREATE TABLE IF NOT EXISTS $table_name (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            model_name varchar(100) NOT NULL,
            training_data longtext,
            accuracy decimal(5,2),
            trained_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY model_name (model_name)
        ) $charset_collate;";
        dbDelta($sql);
        
        // Pattern Recognition
        $table_name = $wpdb->prefix . 'money_quiz_patterns';
        $sql = "CREATE TABLE IF NOT EXISTS $table_name (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            pattern_type varchar(50) NOT NULL,
            pattern_data longtext,
            frequency int DEFAULT 1,
            last_seen datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY pattern_type (pattern_type)
        ) $charset_collate;";
        dbDelta($sql);
    }
    
    /**
     * Create security tables
     */
    private static function create_security_tables() {
        global $wpdb;
        $charset_collate = $wpdb->get_charset_collate();
        
        // Audit Log
        $table_name = $wpdb->prefix . 'money_quiz_audit_log';
        $sql = "CREATE TABLE IF NOT EXISTS $table_name (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            user_id bigint(20),
            action varchar(100) NOT NULL,
            object_type varchar(50),
            object_id bigint(20),
            ip_address varchar(45),
            user_agent text,
            metadata longtext,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY user_id (user_id),
            KEY action (action),
            KEY created_at (created_at)
        ) $charset_collate;";
        dbDelta($sql);
        
        // Rate Limiting
        $table_name = $wpdb->prefix . 'money_quiz_rate_limits';
        $sql = "CREATE TABLE IF NOT EXISTS $table_name (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            identifier varchar(100) NOT NULL,
            action varchar(50) NOT NULL,
            attempts int DEFAULT 1,
            last_attempt datetime DEFAULT CURRENT_TIMESTAMP,
            blocked_until datetime,
            PRIMARY KEY (id),
            UNIQUE KEY rate_limit_key (identifier, action),
            KEY blocked_until (blocked_until)
        ) $charset_collate;";
        dbDelta($sql);
        
        // Security Tokens
        $table_name = $wpdb->prefix . 'money_quiz_security_tokens';
        $sql = "CREATE TABLE IF NOT EXISTS $table_name (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            token varchar(64) NOT NULL,
            token_type varchar(20) NOT NULL,
            user_id bigint(20),
            expires_at datetime NOT NULL,
            used_at datetime,
            PRIMARY KEY (id),
            UNIQUE KEY token (token),
            KEY expires_at (expires_at)
        ) $charset_collate;";
        dbDelta($sql);
    }
    
    /**
     * Create performance tables
     */
    private static function create_performance_tables() {
        global $wpdb;
        $charset_collate = $wpdb->get_charset_collate();
        
        // Cache Entries
        $table_name = $wpdb->prefix . 'money_quiz_cache';
        $sql = "CREATE TABLE IF NOT EXISTS $table_name (
            cache_key varchar(255) NOT NULL,
            cache_value longtext,
            expires_at datetime,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            hit_count int DEFAULT 0,
            PRIMARY KEY (cache_key),
            KEY expires_at (expires_at)
        ) $charset_collate;";
        dbDelta($sql);
        
        // Background Jobs
        $table_name = $wpdb->prefix . 'money_quiz_jobs';
        $sql = "CREATE TABLE IF NOT EXISTS $table_name (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            job_type varchar(50) NOT NULL,
            payload longtext,
            status varchar(20) DEFAULT 'pending',
            priority int DEFAULT 0,
            attempts int DEFAULT 0,
            scheduled_at datetime,
            started_at datetime,
            completed_at datetime,
            error_message text,
            PRIMARY KEY (id),
            KEY status_priority (status, priority),
            KEY scheduled_at (scheduled_at)
        ) $charset_collate;";
        dbDelta($sql);
    }
    
    /**
     * Create webhook tables
     */
    private static function create_webhook_tables() {
        global $wpdb;
        $charset_collate = $wpdb->get_charset_collate();
        
        // Webhook Endpoints
        $table_name = $wpdb->prefix . 'money_quiz_webhooks';
        $sql = "CREATE TABLE IF NOT EXISTS $table_name (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            name varchar(100) NOT NULL,
            url varchar(500) NOT NULL,
            events text,
            secret varchar(64),
            is_active tinyint(1) DEFAULT 1,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY is_active (is_active)
        ) $charset_collate;";
        dbDelta($sql);
        
        // Webhook Deliveries
        $table_name = $wpdb->prefix . 'money_quiz_webhook_deliveries';
        $sql = "CREATE TABLE IF NOT EXISTS $table_name (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            webhook_id bigint(20) NOT NULL,
            event varchar(50) NOT NULL,
            payload longtext,
            response_code int,
            response_body text,
            attempts int DEFAULT 1,
            delivered_at datetime,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY webhook_id (webhook_id),
            KEY event (event)
        ) $charset_collate;";
        dbDelta($sql);
    }
    
    /**
     * Update existing tables
     */
    private static function update_existing_tables() {
        global $wpdb;
        
        // Add missing mq_question_screen_setting table
        $charset_collate = $wpdb->get_charset_collate();
        $table_name = $wpdb->prefix . 'mq_question_screen_setting';
        
        $sql = "CREATE TABLE IF NOT EXISTS $table_name (
            id int(11) NOT NULL AUTO_INCREMENT,
            setting_name varchar(100) NOT NULL,
            setting_value longtext,
            quiz_id int(11),
            PRIMARY KEY (id),
            KEY quiz_id (quiz_id)
        ) $charset_collate;";
        dbDelta($sql);
        
        // Add indexes for performance
        $wpdb->query("ALTER TABLE {$wpdb->prefix}mq_taken ADD INDEX user_id (user_id)");
        $wpdb->query("ALTER TABLE {$wpdb->prefix}mq_results ADD INDEX created_at (createdat)");
        
        // Add new columns if they don't exist
        $wpdb->query("ALTER TABLE {$wpdb->prefix}mq_prospects 
                     ADD COLUMN IF NOT EXISTS ai_score decimal(5,2),
                     ADD COLUMN IF NOT EXISTS last_activity datetime");
    }
    
    /**
     * Check if update is needed
     */
    public static function needs_update() {
        $current_version = get_option('money_quiz_db_version', '1.0.0');
        return version_compare($current_version, self::$db_version, '<');
    }
}
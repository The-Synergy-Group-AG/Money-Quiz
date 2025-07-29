<?php
/**
 * Database Migrator
 *
 * @package MoneyQuiz
 * @since 4.0.0
 */

namespace MoneyQuiz\Database;

/**
 * Handles database migrations and schema updates
 */
class Migrator {
    
    /**
     * @var string Current database version
     */
    private const CURRENT_VERSION = '4.0.0';
    
    /**
     * @var string Option name for database version
     */
    private const VERSION_OPTION = 'money_quiz_db_version';
    
    /**
     * @var \wpdb WordPress database object
     */
    private \wpdb $db;
    
    /**
     * @var string Table prefix
     */
    private string $prefix;
    
    /**
     * Constructor
     */
    public function __construct() {
        global $wpdb;
        $this->db = $wpdb;
        $this->prefix = $wpdb->prefix;
    }
    
    /**
     * Run migrations
     * 
     * @return void
     */
    public function migrate(): void {
        $installed_version = get_option( self::VERSION_OPTION, '0.0.0' );
        
        if ( version_compare( $installed_version, self::CURRENT_VERSION, '<' ) ) {
            $this->run_migrations( $installed_version );
            update_option( self::VERSION_OPTION, self::CURRENT_VERSION );
        }
    }
    
    /**
     * Get current database version
     * 
     * @return string
     */
    public function get_current_version(): string {
        return self::CURRENT_VERSION;
    }
    
    /**
     * Run migrations based on version
     * 
     * @param string $from_version Starting version
     * @return void
     */
    private function run_migrations( string $from_version ): void {
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        
        // Always ensure core tables exist
        $this->create_tables();
        
        // Run version-specific migrations
        if ( version_compare( $from_version, '1.0.0', '<' ) ) {
            $this->migrate_to_1_0_0();
        }
        
        if ( version_compare( $from_version, '2.0.0', '<' ) ) {
            $this->migrate_to_2_0_0();
        }
        
        if ( version_compare( $from_version, '3.0.0', '<' ) ) {
            $this->migrate_to_3_0_0();
        }
        
        if ( version_compare( $from_version, '4.0.0', '<' ) ) {
            $this->migrate_to_4_0_0();
        }
    }
    
    /**
     * Create all tables
     * 
     * @return void
     */
    private function create_tables(): void {
        $charset_collate = $this->db->get_charset_collate();
        
        // Quiz results table
        $sql[] = "CREATE TABLE {$this->prefix}money_quiz_results (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            user_id bigint(20) UNSIGNED DEFAULT NULL,
            quiz_id bigint(20) UNSIGNED NOT NULL,
            archetype_id bigint(20) UNSIGNED NOT NULL,
            answers longtext NOT NULL,
            score decimal(5,2) NOT NULL DEFAULT '0.00',
            ip_address varchar(45) DEFAULT NULL,
            user_agent text,
            completed_at datetime NOT NULL,
            created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY user_id (user_id),
            KEY quiz_id (quiz_id),
            KEY archetype_id (archetype_id),
            KEY completed_at (completed_at)
        ) $charset_collate;";
        
        // Archetypes table
        $sql[] = "CREATE TABLE {$this->prefix}money_quiz_archetypes (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            name varchar(100) NOT NULL,
            slug varchar(100) NOT NULL,
            description text,
            characteristics longtext,
            recommendations longtext,
            image_url varchar(255) DEFAULT NULL,
            color varchar(7) DEFAULT NULL,
            sort_order int(11) NOT NULL DEFAULT '0',
            is_active tinyint(1) NOT NULL DEFAULT '1',
            created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY slug (slug),
            KEY is_active (is_active),
            KEY sort_order (sort_order)
        ) $charset_collate;";
        
        // Questions table
        $sql[] = "CREATE TABLE {$this->prefix}money_quiz_questions (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            quiz_id bigint(20) UNSIGNED NOT NULL,
            question text NOT NULL,
            question_type varchar(20) NOT NULL DEFAULT 'single_choice',
            options longtext,
            archetype_weights longtext,
            sort_order int(11) NOT NULL DEFAULT '0',
            is_required tinyint(1) NOT NULL DEFAULT '1',
            is_active tinyint(1) NOT NULL DEFAULT '1',
            created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY quiz_id (quiz_id),
            KEY is_active (is_active),
            KEY sort_order (sort_order)
        ) $charset_collate;";
        
        // Quizzes table
        $sql[] = "CREATE TABLE {$this->prefix}money_quiz_quizzes (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            title varchar(200) NOT NULL,
            slug varchar(200) NOT NULL,
            description text,
            settings longtext,
            is_active tinyint(1) NOT NULL DEFAULT '1',
            created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY slug (slug),
            KEY is_active (is_active)
        ) $charset_collate;";
        
        // Prospects table
        $sql[] = "CREATE TABLE {$this->prefix}money_quiz_prospects (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            result_id bigint(20) UNSIGNED NOT NULL,
            first_name varchar(100) DEFAULT NULL,
            last_name varchar(100) DEFAULT NULL,
            email varchar(100) NOT NULL,
            phone varchar(20) DEFAULT NULL,
            company varchar(100) DEFAULT NULL,
            consent_marketing tinyint(1) NOT NULL DEFAULT '0',
            consent_terms tinyint(1) NOT NULL DEFAULT '1',
            metadata longtext,
            created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY email (email),
            KEY result_id (result_id),
            KEY created_at (created_at)
        ) $charset_collate;";
        
        // Email log table
        $sql[] = "CREATE TABLE {$this->prefix}money_quiz_email_log (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            result_id bigint(20) UNSIGNED DEFAULT NULL,
            recipient_email varchar(100) NOT NULL,
            email_type varchar(50) NOT NULL,
            subject varchar(255) NOT NULL,
            status varchar(20) NOT NULL DEFAULT 'pending',
            attempts int(11) NOT NULL DEFAULT '0',
            sent_at datetime DEFAULT NULL,
            error_message text,
            created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY result_id (result_id),
            KEY recipient_email (recipient_email),
            KEY status (status),
            KEY created_at (created_at)
        ) $charset_collate;";
        
        // Template settings table
        $sql[] = "CREATE TABLE {$this->prefix}money_quiz_templates (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            template_key varchar(100) NOT NULL,
            field varchar(100) NOT NULL,
            value longtext,
            created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY template_field (template_key, field),
            KEY template_key (template_key)
        ) $charset_collate;";
        
        foreach ( $sql as $query ) {
            dbDelta( $query );
        }
    }
    
    /**
     * Migration to version 1.0.0
     * 
     * @return void
     */
    private function migrate_to_1_0_0(): void {
        // Initial installation - tables created in create_tables()
        $this->seed_default_data();
    }
    
    /**
     * Migration to version 2.0.0
     * 
     * @return void
     */
    private function migrate_to_2_0_0(): void {
        // Add indexes for performance
        $this->db->query( "ALTER TABLE {$this->prefix}money_quiz_results ADD INDEX user_quiz (user_id, quiz_id)" );
        $this->db->query( "ALTER TABLE {$this->prefix}money_quiz_prospects ADD INDEX email_created (email, created_at)" );
    }
    
    /**
     * Migration to version 3.0.0
     * 
     * @return void
     */
    private function migrate_to_3_0_0(): void {
        // Add metadata column to results
        $this->db->query( "ALTER TABLE {$this->prefix}money_quiz_results ADD COLUMN metadata longtext AFTER user_agent" );
    }
    
    /**
     * Migration to version 4.0.0
     * 
     * @return void
     */
    private function migrate_to_4_0_0(): void {
        // Add GDPR compliance fields
        $this->db->query( "ALTER TABLE {$this->prefix}money_quiz_prospects ADD COLUMN gdpr_consent_date datetime AFTER consent_terms" );
        $this->db->query( "ALTER TABLE {$this->prefix}money_quiz_prospects ADD COLUMN data_retention_date datetime AFTER gdpr_consent_date" );
        
        // Update existing records with consent date
        $this->db->query( "UPDATE {$this->prefix}money_quiz_prospects SET gdpr_consent_date = created_at WHERE consent_terms = 1" );
    }
    
    /**
     * Seed default data
     * 
     * @return void
     */
    private function seed_default_data(): void {
        // Insert default archetypes
        $archetypes = [
            [
                'name' => 'The Ruler',
                'slug' => 'ruler',
                'description' => 'Takes charge of money with confidence and control',
                'color' => '#8B4513',
                'sort_order' => 1,
            ],
            [
                'name' => 'The Innocent',
                'slug' => 'innocent',
                'description' => 'Trusts that money will work out naturally',
                'color' => '#87CEEB',
                'sort_order' => 2,
            ],
            [
                'name' => 'The Warrior',
                'slug' => 'warrior',
                'description' => 'Fights for financial success and security',
                'color' => '#DC143C',
                'sort_order' => 3,
            ],
            [
                'name' => 'The Fool',
                'slug' => 'fool',
                'description' => 'Takes financial risks with playful spontaneity',
                'color' => '#FFD700',
                'sort_order' => 4,
            ],
            [
                'name' => 'The Victim',
                'slug' => 'victim',
                'description' => 'Feels powerless over financial circumstances',
                'color' => '#9370DB',
                'sort_order' => 5,
            ],
            [
                'name' => 'The Magician',
                'slug' => 'magician',
                'description' => 'Transforms money situations with creativity',
                'color' => '#4B0082',
                'sort_order' => 6,
            ],
            [
                'name' => 'The Tyrant',
                'slug' => 'tyrant',
                'description' => 'Controls money and others through fear',
                'color' => '#8B0000',
                'sort_order' => 7,
            ],
            [
                'name' => 'The Martyr',
                'slug' => 'martyr',
                'description' => 'Sacrifices financial wellbeing for others',
                'color' => '#2F4F4F',
                'sort_order' => 8,
            ],
        ];
        
        foreach ( $archetypes as $archetype ) {
            $exists = $this->db->get_var( 
                $this->db->prepare( 
                    "SELECT id FROM {$this->prefix}money_quiz_archetypes WHERE slug = %s", 
                    $archetype['slug'] 
                ) 
            );
            
            if ( ! $exists ) {
                $this->db->insert( 
                    $this->prefix . 'money_quiz_archetypes', 
                    $archetype 
                );
            }
        }
        
        // Create default quiz
        $quiz_exists = $this->db->get_var( "SELECT id FROM {$this->prefix}money_quiz_quizzes WHERE slug = 'money-personality'" );
        
        if ( ! $quiz_exists ) {
            $this->db->insert(
                $this->prefix . 'money_quiz_quizzes',
                [
                    'title' => 'Money Personality Quiz',
                    'slug' => 'money-personality',
                    'description' => 'Discover your money archetype and transform your relationship with wealth',
                    'settings' => json_encode([
                        'show_progress' => true,
                        'allow_back' => true,
                        'randomize_questions' => false,
                        'time_limit' => 0,
                        'passing_score' => 0,
                        'result_display' => 'detailed',
                    ]),
                ]
            );
        }
    }
    
    /**
     * Drop all plugin tables
     * 
     * @return void
     */
    public function drop_tables(): void {
        $tables = [
            'money_quiz_email_log',
            'money_quiz_prospects',
            'money_quiz_results',
            'money_quiz_questions',
            'money_quiz_archetypes',
            'money_quiz_quizzes',
            'money_quiz_templates',
        ];
        
        foreach ( $tables as $table ) {
            $this->db->query( "DROP TABLE IF EXISTS {$this->prefix}{$table}" );
        }
    }
}
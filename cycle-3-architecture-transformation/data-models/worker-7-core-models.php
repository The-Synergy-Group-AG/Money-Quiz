<?php
/**
 * Money Quiz Plugin - Core Data Models
 * Worker 7: Data Models - Core Entities
 * 
 * Implements object-oriented data models for the Money Quiz plugin,
 * providing a clean abstraction over database tables.
 * 
 * @package MoneyQuiz
 * @subpackage Models
 * @since 4.0.0
 */

namespace MoneyQuiz\Models;

use MoneyQuiz\Services\DatabaseService;
use DateTime;
use JsonSerializable;

/**
 * Base Model Class
 * 
 * Provides common functionality for all models
 */
abstract class BaseModel implements JsonSerializable {
    
    /**
     * Database service instance
     * 
     * @var DatabaseService
     */
    protected static $database;
    
    /**
     * Table name
     * 
     * @var string
     */
    protected static $table;
    
    /**
     * Primary key field
     * 
     * @var string
     */
    protected static $primary_key = 'id';
    
    /**
     * Model attributes
     * 
     * @var array
     */
    protected $attributes = array();
    
    /**
     * Original attributes
     * 
     * @var array
     */
    protected $original = array();
    
    /**
     * Fillable fields
     * 
     * @var array
     */
    protected $fillable = array();
    
    /**
     * Hidden fields
     * 
     * @var array
     */
    protected $hidden = array();
    
    /**
     * Date fields
     * 
     * @var array
     */
    protected $dates = array( 'created', 'updated' );
    
    /**
     * Constructor
     * 
     * @param array $attributes
     */
    public function __construct( array $attributes = array() ) {
        $this->fill( $attributes );
        $this->sync_original();
    }
    
    /**
     * Set database service
     * 
     * @param DatabaseService $database
     */
    public static function set_database( DatabaseService $database ) {
        static::$database = $database;
    }
    
    /**
     * Create new instance
     * 
     * @param array $attributes
     * @return static
     */
    public static function create( array $attributes ) {
        $model = new static( $attributes );
        $model->save();
        return $model;
    }
    
    /**
     * Find by ID
     * 
     * @param int $id
     * @return static|null
     */
    public static function find( $id ) {
        $data = static::$database->get_row( static::$table, array(
            static::$primary_key => $id
        ), ARRAY_A );
        
        return $data ? new static( $data ) : null;
    }
    
    /**
     * Find by attributes
     * 
     * @param array $where
     * @return static|null
     */
    public static function find_by( array $where ) {
        $data = static::$database->get_row( static::$table, $where, ARRAY_A );
        return $data ? new static( $data ) : null;
    }
    
    /**
     * Get all records
     * 
     * @param array $args
     * @return array
     */
    public static function all( array $args = array() ) {
        $results = static::$database->get_results( static::$table, $args, ARRAY_A );
        
        return array_map( function( $data ) {
            return new static( $data );
        }, $results );
    }
    
    /**
     * Where clause
     * 
     * @param array $where
     * @param array $args
     * @return array
     */
    public static function where( array $where, array $args = array() ) {
        $args['where'] = $where;
        return static::all( $args );
    }
    
    /**
     * Count records
     * 
     * @param array $where
     * @return int
     */
    public static function count( array $where = array() ) {
        return static::$database->count( static::$table, $where );
    }
    
    /**
     * Fill attributes
     * 
     * @param array $attributes
     * @return $this
     */
    public function fill( array $attributes ) {
        foreach ( $attributes as $key => $value ) {
            if ( $this->is_fillable( $key ) ) {
                $this->set_attribute( $key, $value );
            }
        }
        
        return $this;
    }
    
    /**
     * Save model
     * 
     * @return bool
     */
    public function save() {
        if ( $this->exists() ) {
            return $this->update();
        }
        
        return $this->insert();
    }
    
    /**
     * Insert new record
     * 
     * @return bool
     */
    protected function insert() {
        $this->fire_event( 'creating' );
        
        // Add timestamps
        if ( $this->uses_timestamps() ) {
            $now = current_time( 'mysql' );
            $this->set_attribute( 'created', $now );
            $this->set_attribute( 'updated', $now );
        }
        
        $data = $this->get_attributes_for_save();
        
        $id = static::$database->insert( static::$table, $data );
        
        if ( $id ) {
            $this->set_attribute( static::$primary_key, $id );
            $this->sync_original();
            $this->fire_event( 'created' );
            return true;
        }
        
        return false;
    }
    
    /**
     * Update existing record
     * 
     * @return bool
     */
    protected function update() {
        if ( ! $this->is_dirty() ) {
            return true;
        }
        
        $this->fire_event( 'updating' );
        
        // Update timestamp
        if ( $this->uses_timestamps() ) {
            $this->set_attribute( 'updated', current_time( 'mysql' ) );
        }
        
        $data = $this->get_dirty_attributes();
        $where = array( static::$primary_key => $this->get_key() );
        
        $result = static::$database->update( static::$table, $data, $where );
        
        if ( $result !== false ) {
            $this->sync_original();
            $this->fire_event( 'updated' );
            return true;
        }
        
        return false;
    }
    
    /**
     * Delete record
     * 
     * @return bool
     */
    public function delete() {
        if ( ! $this->exists() ) {
            return false;
        }
        
        $this->fire_event( 'deleting' );
        
        $result = static::$database->delete( static::$table, array(
            static::$primary_key => $this->get_key()
        ));
        
        if ( $result ) {
            $this->fire_event( 'deleted' );
            return true;
        }
        
        return false;
    }
    
    /**
     * Get attribute
     * 
     * @param string $key
     * @return mixed
     */
    public function get_attribute( $key ) {
        if ( array_key_exists( $key, $this->attributes ) ) {
            $value = $this->attributes[ $key ];
            
            // Cast dates
            if ( in_array( $key, $this->dates ) && ! empty( $value ) ) {
                return new DateTime( $value );
            }
            
            return $value;
        }
        
        return null;
    }
    
    /**
     * Set attribute
     * 
     * @param string $key
     * @param mixed  $value
     * @return $this
     */
    public function set_attribute( $key, $value ) {
        // Handle date objects
        if ( in_array( $key, $this->dates ) && $value instanceof DateTime ) {
            $value = $value->format( 'Y-m-d H:i:s' );
        }
        
        $this->attributes[ $key ] = $value;
        return $this;
    }
    
    /**
     * Get all attributes
     * 
     * @return array
     */
    public function get_attributes() {
        return $this->attributes;
    }
    
    /**
     * Get attributes for save
     * 
     * @return array
     */
    protected function get_attributes_for_save() {
        $attributes = $this->attributes;
        
        // Remove hidden fields
        foreach ( $this->hidden as $hidden ) {
            unset( $attributes[ $hidden ] );
        }
        
        return $attributes;
    }
    
    /**
     * Get dirty attributes
     * 
     * @return array
     */
    protected function get_dirty_attributes() {
        $dirty = array();
        
        foreach ( $this->attributes as $key => $value ) {
            if ( ! array_key_exists( $key, $this->original ) || 
                 $value !== $this->original[ $key ] ) {
                $dirty[ $key ] = $value;
            }
        }
        
        return $dirty;
    }
    
    /**
     * Check if attribute is fillable
     * 
     * @param string $key
     * @return bool
     */
    protected function is_fillable( $key ) {
        // If fillable is empty, all attributes are fillable
        if ( empty( $this->fillable ) ) {
            return ! in_array( $key, $this->hidden );
        }
        
        return in_array( $key, $this->fillable );
    }
    
    /**
     * Check if model exists in database
     * 
     * @return bool
     */
    public function exists() {
        return ! empty( $this->get_key() );
    }
    
    /**
     * Get primary key value
     * 
     * @return mixed
     */
    public function get_key() {
        return $this->get_attribute( static::$primary_key );
    }
    
    /**
     * Check if model is dirty
     * 
     * @return bool
     */
    public function is_dirty() {
        return count( $this->get_dirty_attributes() ) > 0;
    }
    
    /**
     * Sync original attributes
     * 
     * @return $this
     */
    protected function sync_original() {
        $this->original = $this->attributes;
        return $this;
    }
    
    /**
     * Check if model uses timestamps
     * 
     * @return bool
     */
    protected function uses_timestamps() {
        return ! empty( $this->dates );
    }
    
    /**
     * Fire model event
     * 
     * @param string $event
     */
    protected function fire_event( $event ) {
        $hook = "money_quiz_model_{$event}";
        do_action( $hook, $this );
        
        $hook = "money_quiz_" . static::$table . "_{$event}";
        do_action( $hook, $this );
    }
    
    /**
     * Convert to array
     * 
     * @return array
     */
    public function to_array() {
        $array = $this->attributes;
        
        // Remove hidden fields
        foreach ( $this->hidden as $hidden ) {
            unset( $array[ $hidden ] );
        }
        
        // Convert dates to strings
        foreach ( $this->dates as $date ) {
            if ( isset( $array[ $date ] ) && $array[ $date ] instanceof DateTime ) {
                $array[ $date ] = $array[ $date ]->format( 'Y-m-d H:i:s' );
            }
        }
        
        return $array;
    }
    
    /**
     * JSON serialize
     * 
     * @return array
     */
    public function jsonSerialize() {
        return $this->to_array();
    }
    
    /**
     * Magic getter
     * 
     * @param string $key
     * @return mixed
     */
    public function __get( $key ) {
        return $this->get_attribute( $key );
    }
    
    /**
     * Magic setter
     * 
     * @param string $key
     * @param mixed  $value
     */
    public function __set( $key, $value ) {
        $this->set_attribute( $key, $value );
    }
    
    /**
     * Magic isset
     * 
     * @param string $key
     * @return bool
     */
    public function __isset( $key ) {
        return isset( $this->attributes[ $key ] );
    }
}

/**
 * Prospect Model
 * 
 * Represents a quiz prospect/lead
 */
class Prospect extends BaseModel {
    
    /**
     * Table name
     * 
     * @var string
     */
    protected static $table = 'prospects';
    
    /**
     * Primary key
     * 
     * @var string
     */
    protected static $primary_key = 'Prospect_ID';
    
    /**
     * Fillable fields
     * 
     * @var array
     */
    protected $fillable = array(
        'Email', 'FirstName', 'LastName', 'Phone',
        'IP_Address', 'User_Agent', 'Referrer', 'Status'
    );
    
    /**
     * Date fields
     * 
     * @var array
     */
    protected $dates = array( 'Created', 'Updated' );
    
    /**
     * Get full name
     * 
     * @return string
     */
    public function get_full_name() {
        return trim( $this->FirstName . ' ' . $this->LastName );
    }
    
    /**
     * Get quiz results
     * 
     * @return array
     */
    public function get_results() {
        return QuizResult::where( array( 'Prospect_ID' => $this->get_key() ) );
    }
    
    /**
     * Get latest result
     * 
     * @return QuizResult|null
     */
    public function get_latest_result() {
        $results = QuizResult::where( 
            array( 'Prospect_ID' => $this->get_key() ),
            array( 'orderby' => 'Completed', 'order' => 'DESC', 'limit' => 1 )
        );
        
        return ! empty( $results ) ? $results[0] : null;
    }
    
    /**
     * Check if email exists
     * 
     * @param string $email
     * @return bool
     */
    public static function email_exists( $email ) {
        return static::count( array( 'Email' => $email ) ) > 0;
    }
    
    /**
     * Find or create by email
     * 
     * @param string $email
     * @param array  $attributes
     * @return static
     */
    public static function find_or_create_by_email( $email, array $attributes = array() ) {
        $prospect = static::find_by( array( 'Email' => $email ) );
        
        if ( ! $prospect ) {
            $attributes['Email'] = $email;
            $prospect = static::create( $attributes );
        }
        
        return $prospect;
    }
    
    /**
     * Is active
     * 
     * @return bool
     */
    public function is_active() {
        return $this->Status === 'active';
    }
    
    /**
     * Mark as unsubscribed
     * 
     * @return bool
     */
    public function unsubscribe() {
        $this->Status = 'unsubscribed';
        return $this->save();
    }
}

/**
 * Quiz Result Model
 * 
 * Represents a completed quiz
 */
class QuizResult extends BaseModel {
    
    /**
     * Table name
     * 
     * @var string
     */
    protected static $table = 'taken';
    
    /**
     * Primary key
     * 
     * @var string
     */
    protected static $primary_key = 'Taken_ID';
    
    /**
     * Fillable fields
     * 
     * @var array
     */
    protected $fillable = array(
        'Prospect_ID', 'Quiz_ID', 'Score_Total', 
        'Archetype_ID', 'Status', 'Duration'
    );
    
    /**
     * Date fields
     * 
     * @var array
     */
    protected $dates = array( 'Started', 'Completed' );
    
    /**
     * Get prospect
     * 
     * @return Prospect|null
     */
    public function get_prospect() {
        return Prospect::find( $this->Prospect_ID );
    }
    
    /**
     * Get archetype
     * 
     * @return Archetype|null
     */
    public function get_archetype() {
        return Archetype::find( $this->Archetype_ID );
    }
    
    /**
     * Get answers
     * 
     * @return array
     */
    public function get_answers() {
        return Answer::where( array( 'Taken_ID' => $this->get_key() ) );
    }
    
    /**
     * Get completion percentage
     * 
     * @return float
     */
    public function get_completion_percentage() {
        if ( empty( $this->Score_Total ) ) {
            return 0;
        }
        
        // Assuming max score is based on number of questions * 8
        $question_count = Question::count( array( 'Is_Active' => 1 ) );
        $max_score = $question_count * 8;
        
        return round( ( $this->Score_Total / $max_score ) * 100, 2 );
    }
    
    /**
     * Is completed
     * 
     * @return bool
     */
    public function is_completed() {
        return $this->Status === 'completed';
    }
    
    /**
     * Mark as completed
     * 
     * @param int $archetype_id
     * @param float $score
     * @return bool
     */
    public function complete( $archetype_id, $score ) {
        $this->Status = 'completed';
        $this->Completed = current_time( 'mysql' );
        $this->Archetype_ID = $archetype_id;
        $this->Score_Total = $score;
        
        // Calculate duration
        if ( $this->Started ) {
            $start = new DateTime( $this->Started );
            $end = new DateTime( $this->Completed );
            $this->Duration = $end->getTimestamp() - $start->getTimestamp();
        }
        
        return $this->save();
    }
}

/**
 * Question Model
 * 
 * Represents a quiz question
 */
class Question extends BaseModel {
    
    /**
     * Table name
     * 
     * @var string
     */
    protected static $table = 'questions';
    
    /**
     * Primary key
     * 
     * @var string
     */
    protected static $primary_key = 'Question_ID';
    
    /**
     * Fillable fields
     * 
     * @var array
     */
    protected $fillable = array(
        'Question_Text', 'Question_Category', 'Question_Type',
        'Display_Order', 'Is_Active'
    );
    
    /**
     * Date fields
     * 
     * @var array
     */
    protected $dates = array( 'Created', 'Updated' );
    
    /**
     * Get active questions
     * 
     * @return array
     */
    public static function get_active() {
        return static::where( 
            array( 'Is_Active' => 1 ),
            array( 'orderby' => 'Display_Order', 'order' => 'ASC' )
        );
    }
    
    /**
     * Get by category
     * 
     * @param string $category
     * @return array
     */
    public static function get_by_category( $category ) {
        return static::where( array(
            'Question_Category' => $category,
            'Is_Active' => 1
        ));
    }
    
    /**
     * Activate question
     * 
     * @return bool
     */
    public function activate() {
        $this->Is_Active = 1;
        return $this->save();
    }
    
    /**
     * Deactivate question
     * 
     * @return bool
     */
    public function deactivate() {
        $this->Is_Active = 0;
        return $this->save();
    }
    
    /**
     * Reorder questions
     * 
     * @param array $order Array of question IDs in order
     */
    public static function reorder( array $order ) {
        foreach ( $order as $position => $question_id ) {
            static::$database->update( 
                static::$table,
                array( 'Display_Order' => $position ),
                array( static::$primary_key => $question_id )
            );
        }
    }
}

/**
 * Answer Model
 * 
 * Represents a quiz answer
 */
class Answer extends BaseModel {
    
    /**
     * Table name
     * 
     * @var string
     */
    protected static $table = 'results';
    
    /**
     * Primary key
     * 
     * @var string
     */
    protected static $primary_key = 'Result_ID';
    
    /**
     * Fillable fields
     * 
     * @var array
     */
    protected $fillable = array(
        'Taken_ID', 'Prospect_ID', 'Question_ID',
        'Answer_Value', 'Answer_Text', 'Weight'
    );
    
    /**
     * Date fields
     * 
     * @var array
     */
    protected $dates = array( 'Created' );
    
    /**
     * Get question
     * 
     * @return Question|null
     */
    public function get_question() {
        return Question::find( $this->Question_ID );
    }
    
    /**
     * Get weighted score
     * 
     * @return float
     */
    public function get_weighted_score() {
        return $this->Answer_Value * $this->Weight;
    }
}

/**
 * Archetype Model
 * 
 * Represents a personality archetype
 */
class Archetype extends BaseModel {
    
    /**
     * Table name
     * 
     * @var string
     */
    protected static $table = 'archetypes';
    
    /**
     * Primary key
     * 
     * @var string
     */
    protected static $primary_key = 'Archetype_ID';
    
    /**
     * Fillable fields
     * 
     * @var array
     */
    protected $fillable = array(
        'Name', 'Description', 'Score_Range_Min', 'Score_Range_Max',
        'Color', 'Icon', 'Recommendations', 'Display_Order', 'Is_Active'
    );
    
    /**
     * Get active archetypes
     * 
     * @return array
     */
    public static function get_active() {
        return static::where(
            array( 'Is_Active' => 1 ),
            array( 'orderby' => 'Display_Order', 'order' => 'ASC' )
        );
    }
    
    /**
     * Find by score
     * 
     * @param float $score
     * @return static|null
     */
    public static function find_by_score( $score ) {
        $archetypes = static::get_active();
        
        foreach ( $archetypes as $archetype ) {
            if ( $score >= $archetype->Score_Range_Min && 
                 $score <= $archetype->Score_Range_Max ) {
                return $archetype;
            }
        }
        
        return null;
    }
    
    /**
     * Get results count
     * 
     * @return int
     */
    public function get_results_count() {
        return QuizResult::count( array( 
            'Archetype_ID' => $this->get_key(),
            'Status' => 'completed'
        ));
    }
    
    /**
     * Get percentage of total results
     * 
     * @return float
     */
    public function get_percentage() {
        $total = QuizResult::count( array( 'Status' => 'completed' ) );
        
        if ( $total === 0 ) {
            return 0;
        }
        
        $count = $this->get_results_count();
        return round( ( $count / $total ) * 100, 2 );
    }
}
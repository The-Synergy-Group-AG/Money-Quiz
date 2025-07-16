<?php
/**
 * Money Quiz Plugin - Additional Data Models
 * Worker 8: Data Models - Supporting Entities
 * 
 * Implements additional data models for CTAs, email logs,
 * activity tracking, and configuration management.
 * 
 * @package MoneyQuiz
 * @subpackage Models
 * @since 4.0.0
 */

namespace MoneyQuiz\Models;

use DateTime;

/**
 * CTA (Call to Action) Model
 * 
 * Represents customizable CTAs for quiz results
 */
class CTA extends BaseModel {
    
    /**
     * Table name
     * 
     * @var string
     */
    protected static $table = 'cta';
    
    /**
     * Primary key
     * 
     * @var string
     */
    protected static $primary_key = 'CTA_ID';
    
    /**
     * Fillable fields
     * 
     * @var array
     */
    protected $fillable = array(
        'Name', 'Type', 'Content', 'Button_Text', 'Button_URL',
        'Target_Archetype', 'Display_Rules', 'Style_Settings',
        'Conversion_Count', 'View_Count', 'Is_Active', 'Display_Order'
    );
    
    /**
     * Date fields
     * 
     * @var array
     */
    protected $dates = array( 'Created', 'Updated' );
    
    /**
     * Get CTAs for archetype
     * 
     * @param int $archetype_id
     * @return array
     */
    public static function get_for_archetype( $archetype_id ) {
        return static::where( array(
            'Target_Archetype' => $archetype_id,
            'Is_Active' => 1
        ), array(
            'orderby' => 'Display_Order',
            'order' => 'ASC'
        ));
    }
    
    /**
     * Get active CTAs
     * 
     * @return array
     */
    public static function get_active() {
        return static::where( 
            array( 'Is_Active' => 1 ),
            array( 'orderby' => 'Display_Order' )
        );
    }
    
    /**
     * Record view
     * 
     * @return bool
     */
    public function record_view() {
        $this->View_Count = ( $this->View_Count ?? 0 ) + 1;
        return $this->save();
    }
    
    /**
     * Record conversion
     * 
     * @return bool
     */
    public function record_conversion() {
        $this->Conversion_Count = ( $this->Conversion_Count ?? 0 ) + 1;
        return $this->save();
    }
    
    /**
     * Get conversion rate
     * 
     * @return float
     */
    public function get_conversion_rate() {
        if ( empty( $this->View_Count ) ) {
            return 0;
        }
        
        return round( ( $this->Conversion_Count / $this->View_Count ) * 100, 2 );
    }
    
    /**
     * Get display rules array
     * 
     * @return array
     */
    public function get_display_rules() {
        return json_decode( $this->Display_Rules, true ) ?? array();
    }
    
    /**
     * Set display rules
     * 
     * @param array $rules
     * @return $this
     */
    public function set_display_rules( array $rules ) {
        $this->Display_Rules = json_encode( $rules );
        return $this;
    }
    
    /**
     * Get style settings
     * 
     * @return array
     */
    public function get_style_settings() {
        return json_decode( $this->Style_Settings, true ) ?? array();
    }
    
    /**
     * Set style settings
     * 
     * @param array $settings
     * @return $this
     */
    public function set_style_settings( array $settings ) {
        $this->Style_Settings = json_encode( $settings );
        return $this;
    }
    
    /**
     * Should display for result
     * 
     * @param QuizResult $result
     * @return bool
     */
    public function should_display_for_result( QuizResult $result ) {
        if ( ! $this->Is_Active ) {
            return false;
        }
        
        // Check archetype targeting
        if ( $this->Target_Archetype && $this->Target_Archetype != $result->Archetype_ID ) {
            return false;
        }
        
        // Check display rules
        $rules = $this->get_display_rules();
        
        // Score range rule
        if ( isset( $rules['min_score'] ) && $result->Score_Total < $rules['min_score'] ) {
            return false;
        }
        
        if ( isset( $rules['max_score'] ) && $result->Score_Total > $rules['max_score'] ) {
            return false;
        }
        
        // Time-based rules
        if ( isset( $rules['start_date'] ) && time() < strtotime( $rules['start_date'] ) ) {
            return false;
        }
        
        if ( isset( $rules['end_date'] ) && time() > strtotime( $rules['end_date'] ) ) {
            return false;
        }
        
        return true;
    }
}

/**
 * Email Log Model
 * 
 * Tracks all emails sent by the plugin
 */
class EmailLog extends BaseModel {
    
    /**
     * Table name
     * 
     * @var string
     */
    protected static $table = 'email_log';
    
    /**
     * Primary key
     * 
     * @var string
     */
    protected static $primary_key = 'Log_ID';
    
    /**
     * Fillable fields
     * 
     * @var array
     */
    protected $fillable = array(
        'Email_To', 'Email_Type', 'Subject', 'Result_ID',
        'Provider', 'Status', 'Error_Message', 'Opened_At',
        'Clicked_At', 'Metadata'
    );
    
    /**
     * Date fields
     * 
     * @var array
     */
    protected $dates = array( 'Sent_At', 'Opened_At', 'Clicked_At' );
    
    /**
     * Log email sent
     * 
     * @param array $data
     * @return static
     */
    public static function log_sent( array $data ) {
        $data['Status'] = 'sent';
        $data['Sent_At'] = current_time( 'mysql' );
        
        return static::create( $data );
    }
    
    /**
     * Log email failed
     * 
     * @param array  $data
     * @param string $error
     * @return static
     */
    public static function log_failed( array $data, $error ) {
        $data['Status'] = 'failed';
        $data['Error_Message'] = $error;
        $data['Sent_At'] = current_time( 'mysql' );
        
        return static::create( $data );
    }
    
    /**
     * Mark as opened
     * 
     * @return bool
     */
    public function mark_opened() {
        if ( ! $this->Opened_At ) {
            $this->Opened_At = current_time( 'mysql' );
            return $this->save();
        }
        
        return true;
    }
    
    /**
     * Mark as clicked
     * 
     * @return bool
     */
    public function mark_clicked() {
        if ( ! $this->Clicked_At ) {
            $this->Clicked_At = current_time( 'mysql' );
            
            // Also mark as opened if not already
            if ( ! $this->Opened_At ) {
                $this->Opened_At = current_time( 'mysql' );
            }
            
            return $this->save();
        }
        
        return true;
    }
    
    /**
     * Get emails by type
     * 
     * @param string $type
     * @param array  $args
     * @return array
     */
    public static function get_by_type( $type, array $args = array() ) {
        $args['where'] = array( 'Email_Type' => $type );
        return static::all( $args );
    }
    
    /**
     * Get email stats
     * 
     * @param string $period
     * @return array
     */
    public static function get_stats( $period = '7days' ) {
        $stats = array(
            'total_sent' => 0,
            'total_opened' => 0,
            'total_clicked' => 0,
            'open_rate' => 0,
            'click_rate' => 0
        );
        
        // Calculate date range
        switch ( $period ) {
            case '24hours':
                $start_date = date( 'Y-m-d H:i:s', strtotime( '-24 hours' ) );
                break;
            case '7days':
                $start_date = date( 'Y-m-d H:i:s', strtotime( '-7 days' ) );
                break;
            case '30days':
                $start_date = date( 'Y-m-d H:i:s', strtotime( '-30 days' ) );
                break;
            default:
                $start_date = '2000-01-01 00:00:00';
        }
        
        $emails = static::$database->get_results( static::$table, array(
            'where' => array( 'Status' => 'sent' ),
            'fields' => 'COUNT(*) as total, 
                        SUM(CASE WHEN Opened_At IS NOT NULL THEN 1 ELSE 0 END) as opened,
                        SUM(CASE WHEN Clicked_At IS NOT NULL THEN 1 ELSE 0 END) as clicked'
        ), ARRAY_A );
        
        if ( ! empty( $emails[0] ) ) {
            $stats['total_sent'] = $emails[0]['total'];
            $stats['total_opened'] = $emails[0]['opened'];
            $stats['total_clicked'] = $emails[0]['clicked'];
            
            if ( $stats['total_sent'] > 0 ) {
                $stats['open_rate'] = round( ( $stats['total_opened'] / $stats['total_sent'] ) * 100, 2 );
                $stats['click_rate'] = round( ( $stats['total_clicked'] / $stats['total_sent'] ) * 100, 2 );
            }
        }
        
        return $stats;
    }
}

/**
 * Activity Log Model
 * 
 * Tracks all significant actions in the plugin
 */
class ActivityLog extends BaseModel {
    
    /**
     * Table name
     * 
     * @var string
     */
    protected static $table = 'activity_log';
    
    /**
     * Primary key
     * 
     * @var string
     */
    protected static $primary_key = 'Activity_ID';
    
    /**
     * Fillable fields
     * 
     * @var array
     */
    protected $fillable = array(
        'Action', 'Object_Type', 'Object_ID', 'User_ID',
        'Prospect_ID', 'Data', 'IP_Address', 'User_Agent'
    );
    
    /**
     * Date fields
     * 
     * @var array
     */
    protected $dates = array( 'Created' );
    
    /**
     * Log activity
     * 
     * @param string $action
     * @param array  $data
     * @return static
     */
    public static function log( $action, array $data = array() ) {
        $log_data = array(
            'Action' => $action,
            'Object_Type' => $data['object_type'] ?? null,
            'Object_ID' => $data['object_id'] ?? null,
            'User_ID' => get_current_user_id() ?: null,
            'Prospect_ID' => $data['prospect_id'] ?? null,
            'Data' => json_encode( $data ),
            'IP_Address' => $_SERVER['REMOTE_ADDR'] ?? '',
            'User_Agent' => $_SERVER['HTTP_USER_AGENT'] ?? ''
        );
        
        return static::create( $log_data );
    }
    
    /**
     * Get activity data
     * 
     * @return array
     */
    public function get_data() {
        return json_decode( $this->Data, true ) ?? array();
    }
    
    /**
     * Get activities by action
     * 
     * @param string $action
     * @param array  $args
     * @return array
     */
    public static function get_by_action( $action, array $args = array() ) {
        $args['where'] = array( 'Action' => $action );
        return static::all( $args );
    }
    
    /**
     * Get activities for object
     * 
     * @param string $type
     * @param int    $id
     * @return array
     */
    public static function get_for_object( $type, $id ) {
        return static::where( array(
            'Object_Type' => $type,
            'Object_ID' => $id
        ), array(
            'orderby' => 'Created',
            'order' => 'DESC'
        ));
    }
    
    /**
     * Get user activities
     * 
     * @param int $user_id
     * @return array
     */
    public static function get_user_activities( $user_id ) {
        return static::where( 
            array( 'User_ID' => $user_id ),
            array( 'orderby' => 'Created', 'order' => 'DESC' )
        );
    }
    
    /**
     * Clean old logs
     * 
     * @param int $days Days to keep
     * @return int Number of deleted records
     */
    public static function clean_old_logs( $days = 90 ) {
        $cutoff_date = date( 'Y-m-d H:i:s', strtotime( "-{$days} days" ) );
        
        return static::$database->query(
            "DELETE FROM " . static::$database->get_table( static::$table ) . 
            " WHERE Created < %s",
            $cutoff_date
        );
    }
}

/**
 * Settings Model
 * 
 * Manages plugin settings and configuration
 */
class Settings extends BaseModel {
    
    /**
     * Table name
     * 
     * @var string
     */
    protected static $table = 'settings';
    
    /**
     * Primary key
     * 
     * @var string
     */
    protected static $primary_key = 'Setting_ID';
    
    /**
     * Fillable fields
     * 
     * @var array
     */
    protected $fillable = array(
        'Setting_Key', 'Setting_Value', 'Setting_Type',
        'Is_Autoloaded'
    );
    
    /**
     * Settings cache
     * 
     * @var array
     */
    protected static $cache = array();
    
    /**
     * Get setting value
     * 
     * @param string $key
     * @param mixed  $default
     * @return mixed
     */
    public static function get( $key, $default = null ) {
        // Check cache first
        if ( isset( static::$cache[ $key ] ) ) {
            return static::$cache[ $key ];
        }
        
        $setting = static::find_by( array( 'Setting_Key' => $key ) );
        
        if ( ! $setting ) {
            return $default;
        }
        
        $value = $setting->get_typed_value();
        static::$cache[ $key ] = $value;
        
        return $value;
    }
    
    /**
     * Set setting value
     * 
     * @param string $key
     * @param mixed  $value
     * @param string $type
     * @return bool
     */
    public static function set( $key, $value, $type = 'string' ) {
        $setting = static::find_by( array( 'Setting_Key' => $key ) );
        
        if ( ! $setting ) {
            $setting = new static( array(
                'Setting_Key' => $key,
                'Setting_Type' => $type
            ));
        }
        
        $setting->set_typed_value( $value );
        $result = $setting->save();
        
        // Update cache
        if ( $result ) {
            static::$cache[ $key ] = $value;
        }
        
        return $result;
    }
    
    /**
     * Delete setting
     * 
     * @param string $key
     * @return bool
     */
    public static function delete_setting( $key ) {
        $setting = static::find_by( array( 'Setting_Key' => $key ) );
        
        if ( $setting ) {
            $result = $setting->delete();
            
            // Clear from cache
            if ( $result ) {
                unset( static::$cache[ $key ] );
            }
            
            return $result;
        }
        
        return false;
    }
    
    /**
     * Get typed value
     * 
     * @return mixed
     */
    public function get_typed_value() {
        switch ( $this->Setting_Type ) {
            case 'boolean':
                return (bool) $this->Setting_Value;
                
            case 'integer':
                return (int) $this->Setting_Value;
                
            case 'float':
                return (float) $this->Setting_Value;
                
            case 'array':
            case 'object':
                return json_decode( $this->Setting_Value, true );
                
            case 'string':
            default:
                return $this->Setting_Value;
        }
    }
    
    /**
     * Set typed value
     * 
     * @param mixed $value
     * @return $this
     */
    public function set_typed_value( $value ) {
        switch ( $this->Setting_Type ) {
            case 'boolean':
                $this->Setting_Value = $value ? '1' : '0';
                break;
                
            case 'array':
            case 'object':
                $this->Setting_Value = json_encode( $value );
                break;
                
            default:
                $this->Setting_Value = (string) $value;
        }
        
        return $this;
    }
    
    /**
     * Get all settings
     * 
     * @param bool $autoloaded_only
     * @return array
     */
    public static function get_all( $autoloaded_only = false ) {
        $where = array();
        
        if ( $autoloaded_only ) {
            $where['Is_Autoloaded'] = 1;
        }
        
        $settings = static::where( $where );
        $result = array();
        
        foreach ( $settings as $setting ) {
            $result[ $setting->Setting_Key ] = $setting->get_typed_value();
        }
        
        return $result;
    }
    
    /**
     * Load autoloaded settings into cache
     */
    public static function load_autoloaded() {
        $settings = static::get_all( true );
        static::$cache = array_merge( static::$cache, $settings );
    }
}

/**
 * Error Log Model
 * 
 * Tracks errors and exceptions
 */
class ErrorLog extends BaseModel {
    
    /**
     * Table name
     * 
     * @var string
     */
    protected static $table = 'error_log';
    
    /**
     * Primary key
     * 
     * @var string
     */
    protected static $primary_key = 'Error_ID';
    
    /**
     * Fillable fields
     * 
     * @var array
     */
    protected $fillable = array(
        'Error_Type', 'Error_Message', 'Error_File',
        'Error_Line', 'Error_Context', 'User_ID',
        'URL', 'Stack_Trace', 'Is_Resolved'
    );
    
    /**
     * Date fields
     * 
     * @var array
     */
    protected $dates = array( 'Created', 'Resolved_At' );
    
    /**
     * Log error
     * 
     * @param \Exception|\Error $exception
     * @param array            $context
     * @return static
     */
    public static function log_exception( $exception, array $context = array() ) {
        $data = array(
            'Error_Type' => get_class( $exception ),
            'Error_Message' => $exception->getMessage(),
            'Error_File' => $exception->getFile(),
            'Error_Line' => $exception->getLine(),
            'Error_Context' => json_encode( $context ),
            'Stack_Trace' => $exception->getTraceAsString(),
            'User_ID' => get_current_user_id() ?: null,
            'URL' => $_SERVER['REQUEST_URI'] ?? ''
        );
        
        return static::create( $data );
    }
    
    /**
     * Mark as resolved
     * 
     * @return bool
     */
    public function resolve() {
        $this->Is_Resolved = 1;
        $this->Resolved_At = current_time( 'mysql' );
        return $this->save();
    }
    
    /**
     * Get unresolved errors
     * 
     * @return array
     */
    public static function get_unresolved() {
        return static::where( 
            array( 'Is_Resolved' => 0 ),
            array( 'orderby' => 'Created', 'order' => 'DESC' )
        );
    }
    
    /**
     * Get errors by type
     * 
     * @param string $type
     * @return array
     */
    public static function get_by_type( $type ) {
        return static::where( array( 'Error_Type' => $type ) );
    }
    
    /**
     * Get error summary
     * 
     * @return array
     */
    public static function get_summary() {
        $results = static::$database->get_results( static::$table, array(
            'fields' => 'Error_Type, COUNT(*) as count, MAX(Created) as last_seen',
            'groupby' => 'Error_Type',
            'orderby' => 'count',
            'order' => 'DESC'
        ), ARRAY_A );
        
        return $results;
    }
}

/**
 * Blacklist Model
 * 
 * Manages email and IP blacklists
 */
class Blacklist extends BaseModel {
    
    /**
     * Table name
     * 
     * @var string
     */
    protected static $table = 'blacklist';
    
    /**
     * Primary key
     * 
     * @var string
     */
    protected static $primary_key = 'Blacklist_ID';
    
    /**
     * Fillable fields
     * 
     * @var array
     */
    protected $fillable = array(
        'Type', 'Value', 'Reason', 'Added_By', 'Is_Active'
    );
    
    /**
     * Date fields
     * 
     * @var array
     */
    protected $dates = array( 'Created', 'Updated' );
    
    /**
     * Check if email is blacklisted
     * 
     * @param string $email
     * @return bool
     */
    public static function is_email_blacklisted( $email ) {
        return static::count( array(
            'Type' => 'email',
            'Value' => $email,
            'Is_Active' => 1
        )) > 0;
    }
    
    /**
     * Check if IP is blacklisted
     * 
     * @param string $ip
     * @return bool
     */
    public static function is_ip_blacklisted( $ip ) {
        return static::count( array(
            'Type' => 'ip',
            'Value' => $ip,
            'Is_Active' => 1
        )) > 0;
    }
    
    /**
     * Add to blacklist
     * 
     * @param string $type
     * @param string $value
     * @param string $reason
     * @return static
     */
    public static function add( $type, $value, $reason = '' ) {
        return static::create( array(
            'Type' => $type,
            'Value' => $value,
            'Reason' => $reason,
            'Added_By' => get_current_user_id() ?: 0,
            'Is_Active' => 1
        ));
    }
    
    /**
     * Remove from blacklist
     * 
     * @param string $type
     * @param string $value
     * @return bool
     */
    public static function remove( $type, $value ) {
        $entry = static::find_by( array(
            'Type' => $type,
            'Value' => $value
        ));
        
        if ( $entry ) {
            $entry->Is_Active = 0;
            return $entry->save();
        }
        
        return false;
    }
    
    /**
     * Get active blacklist entries
     * 
     * @param string $type Optional type filter
     * @return array
     */
    public static function get_active( $type = null ) {
        $where = array( 'Is_Active' => 1 );
        
        if ( $type ) {
            $where['Type'] = $type;
        }
        
        return static::where( $where );
    }
}
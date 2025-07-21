<?php
/**
 * Money Quiz Plugin - Analytics Service
 * Worker 3: Advanced Analytics and Reporting
 * 
 * Provides comprehensive analytics, reporting, and data visualization
 * capabilities for the Money Quiz plugin.
 * 
 * @package MoneyQuiz
 * @subpackage Services
 * @since 4.0.0
 */

namespace MoneyQuiz\Services;

use Exception;
use MoneyQuiz\Models\QuizResult;
use MoneyQuiz\Models\Prospect;
use MoneyQuiz\Models\ActivityLog;
use MoneyQuiz\Models\Archetype;
use MoneyQuiz\Utilities\CacheUtil;
use MoneyQuiz\Utilities\DateUtil;
use MoneyQuiz\Utilities\ArrayUtil;

/**
 * Analytics Service Class
 * 
 * Handles all analytics and reporting functionality
 */
class AnalyticsService {
    
    /**
     * Database service
     * 
     * @var DatabaseService
     */
    protected $database;
    
    /**
     * Cache duration for analytics data
     * 
     * @var int
     */
    protected $cache_duration = 900; // 15 minutes
    
    /**
     * Constructor
     * 
     * @param DatabaseService $database
     */
    public function __construct( DatabaseService $database ) {
        $this->database = $database;
    }
    
    /**
     * Get dashboard overview data
     * 
     * @param array $filters Optional filters
     * @return array Dashboard data
     */
    public function get_dashboard_overview( array $filters = array() ) {
        $cache_key = 'analytics_dashboard_' . md5( json_encode( $filters ) );
        
        return CacheUtil::remember( $cache_key, function() use ( $filters ) {
            $period = $filters['period'] ?? '30days';
            $date_range = $this->get_date_range( $period );
            
            return array(
                'summary' => $this->get_summary_stats( $date_range ),
                'trends' => $this->get_trend_data( $date_range ),
                'archetype_distribution' => $this->get_archetype_distribution( $date_range ),
                'conversion_funnel' => $this->get_conversion_funnel( $date_range ),
                'engagement_metrics' => $this->get_engagement_metrics( $date_range ),
                'top_performers' => $this->get_top_performers( $date_range ),
                'recent_activity' => $this->get_recent_activity( 10 )
            );
        }, $this->cache_duration );
    }
    
    /**
     * Get summary statistics
     * 
     * @param array $date_range Date range
     * @return array Summary stats
     */
    public function get_summary_stats( array $date_range ) {
        $stats = array();
        
        // Total quizzes completed
        $stats['total_completed'] = QuizResult::count( array(
            'Status' => 'completed'
        ));
        
        // Period quizzes
        $stats['period_completed'] = $this->database->query(
            "SELECT COUNT(*) as count FROM {$this->database->get_table('taken')} 
             WHERE Status = 'completed' 
             AND Completed BETWEEN %s AND %s",
            array( $date_range['start'], $date_range['end'] )
        )[0]->count ?? 0;
        
        // Conversion rate
        $total_started = QuizResult::count();
        $stats['conversion_rate'] = $total_started > 0 
            ? round( ( $stats['total_completed'] / $total_started ) * 100, 2 )
            : 0;
        
        // Average score
        $avg_score = $this->database->get_var( 'taken', 'AVG(Score_Total)', array(
            'Status' => 'completed'
        ));
        $stats['average_score'] = round( $avg_score ?? 0, 2 );
        
        // Total prospects
        $stats['total_prospects'] = Prospect::count();
        
        // Email subscribers
        $stats['email_subscribers'] = Prospect::count( array(
            'Status' => 'active'
        ));
        
        // Period growth
        $last_period = $this->get_previous_period_stats( $date_range );
        $stats['growth'] = $this->calculate_growth( $stats, $last_period );
        
        return $stats;
    }
    
    /**
     * Get trend data for charts
     * 
     * @param array $date_range Date range
     * @return array Trend data
     */
    public function get_trend_data( array $date_range ) {
        $interval = $this->determine_interval( $date_range );
        $trends = array();
        
        // Completions over time
        $trends['completions'] = $this->get_time_series_data(
            'taken',
            'Completed',
            $date_range,
            $interval,
            array( 'Status' => 'completed' )
        );
        
        // New prospects over time
        $trends['prospects'] = $this->get_time_series_data(
            'prospects',
            'Created',
            $date_range,
            $interval
        );
        
        // Average scores over time
        $trends['scores'] = $this->get_average_scores_trend( $date_range, $interval );
        
        // Conversion rate trend
        $trends['conversion'] = $this->get_conversion_trend( $date_range, $interval );
        
        return $trends;
    }
    
    /**
     * Get archetype distribution
     * 
     * @param array $date_range Date range
     * @return array Distribution data
     */
    public function get_archetype_distribution( array $date_range ) {
        $query = "SELECT 
                    a.Archetype_ID,
                    a.Name,
                    a.Color,
                    COUNT(t.Taken_ID) as count,
                    AVG(t.Score_Total) as avg_score
                  FROM {$this->database->get_table('archetypes')} a
                  LEFT JOIN {$this->database->get_table('taken')} t 
                    ON a.Archetype_ID = t.Archetype_ID
                    AND t.Status = 'completed'
                    AND t.Completed BETWEEN %s AND %s
                  WHERE a.Is_Active = 1
                  GROUP BY a.Archetype_ID
                  ORDER BY count DESC";
        
        $results = $this->database->query( $query, array(
            $date_range['start'],
            $date_range['end']
        ));
        
        $total = array_sum( array_column( $results, 'count' ) );
        $distribution = array();
        
        foreach ( $results as $result ) {
            $distribution[] = array(
                'id' => $result->Archetype_ID,
                'name' => $result->Name,
                'color' => $result->Color,
                'count' => (int) $result->count,
                'percentage' => $total > 0 ? round( ( $result->count / $total ) * 100, 2 ) : 0,
                'avg_score' => round( $result->avg_score ?? 0, 2 )
            );
        }
        
        return $distribution;
    }
    
    /**
     * Get conversion funnel data
     * 
     * @param array $date_range Date range
     * @return array Funnel data
     */
    public function get_conversion_funnel( array $date_range ) {
        $funnel = array();
        
        // Page views (from activity log)
        $page_views = ActivityLog::count( array(
            'Action' => 'quiz_page_view'
        ));
        
        // Quiz starts
        $quiz_starts = $this->database->query(
            "SELECT COUNT(*) as count FROM {$this->database->get_table('taken')} 
             WHERE Started BETWEEN %s AND %s",
            array( $date_range['start'], $date_range['end'] )
        )[0]->count ?? 0;
        
        // Questions answered (at least one)
        $with_answers = $this->database->query(
            "SELECT COUNT(DISTINCT t.Taken_ID) as count 
             FROM {$this->database->get_table('taken')} t
             INNER JOIN {$this->database->get_table('results')} r ON t.Taken_ID = r.Taken_ID
             WHERE t.Started BETWEEN %s AND %s",
            array( $date_range['start'], $date_range['end'] )
        )[0]->count ?? 0;
        
        // Quiz completions
        $completions = $this->database->query(
            "SELECT COUNT(*) as count FROM {$this->database->get_table('taken')} 
             WHERE Status = 'completed' AND Completed BETWEEN %s AND %s",
            array( $date_range['start'], $date_range['end'] )
        )[0]->count ?? 0;
        
        // Email submissions
        $email_submissions = $this->database->query(
            "SELECT COUNT(DISTINCT p.Prospect_ID) as count 
             FROM {$this->database->get_table('prospects')} p
             INNER JOIN {$this->database->get_table('taken')} t ON p.Prospect_ID = t.Prospect_ID
             WHERE p.Email NOT LIKE 'anonymous_%' 
             AND t.Completed BETWEEN %s AND %s",
            array( $date_range['start'], $date_range['end'] )
        )[0]->count ?? 0;
        
        $funnel = array(
            array(
                'stage' => 'Page Views',
                'count' => $page_views,
                'rate' => 100
            ),
            array(
                'stage' => 'Quiz Started',
                'count' => $quiz_starts,
                'rate' => $page_views > 0 ? round( ( $quiz_starts / $page_views ) * 100, 2 ) : 0
            ),
            array(
                'stage' => 'Questions Answered',
                'count' => $with_answers,
                'rate' => $quiz_starts > 0 ? round( ( $with_answers / $quiz_starts ) * 100, 2 ) : 0
            ),
            array(
                'stage' => 'Quiz Completed',
                'count' => $completions,
                'rate' => $quiz_starts > 0 ? round( ( $completions / $quiz_starts ) * 100, 2 ) : 0
            ),
            array(
                'stage' => 'Email Provided',
                'count' => $email_submissions,
                'rate' => $completions > 0 ? round( ( $email_submissions / $completions ) * 100, 2 ) : 0
            )
        );
        
        return $funnel;
    }
    
    /**
     * Get engagement metrics
     * 
     * @param array $date_range Date range
     * @return array Engagement data
     */
    public function get_engagement_metrics( array $date_range ) {
        $metrics = array();
        
        // Average time to complete
        $avg_duration = $this->database->query(
            "SELECT AVG(Duration) as avg_duration 
             FROM {$this->database->get_table('taken')} 
             WHERE Status = 'completed' 
             AND Duration > 0
             AND Completed BETWEEN %s AND %s",
            array( $date_range['start'], $date_range['end'] )
        )[0]->avg_duration ?? 0;
        
        $metrics['avg_completion_time'] = array(
            'value' => round( $avg_duration / 60, 1 ),
            'unit' => 'minutes'
        );
        
        // Question response patterns
        $metrics['response_patterns'] = $this->get_response_patterns( $date_range );
        
        // Repeat quiz takers
        $repeat_takers = $this->database->query(
            "SELECT COUNT(*) as count FROM (
                SELECT Prospect_ID, COUNT(*) as quiz_count 
                FROM {$this->database->get_table('taken')} 
                WHERE Status = 'completed'
                GROUP BY Prospect_ID 
                HAVING quiz_count > 1
            ) as repeat_users"
        )[0]->count ?? 0;
        
        $total_users = $this->database->query(
            "SELECT COUNT(DISTINCT Prospect_ID) as count 
             FROM {$this->database->get_table('taken')} 
             WHERE Status = 'completed'"
        )[0]->count ?? 0;
        
        $metrics['repeat_rate'] = $total_users > 0 
            ? round( ( $repeat_takers / $total_users ) * 100, 2 )
            : 0;
        
        // Device breakdown
        $metrics['devices'] = $this->get_device_breakdown( $date_range );
        
        // Peak activity times
        $metrics['peak_times'] = $this->get_peak_activity_times( $date_range );
        
        return $metrics;
    }
    
    /**
     * Get top performing content
     * 
     * @param array $date_range Date range
     * @return array Top performers
     */
    public function get_top_performers( array $date_range ) {
        $performers = array();
        
        // Most selected archetype
        $performers['popular_archetype'] = $this->database->query(
            "SELECT a.Name, COUNT(*) as count 
             FROM {$this->database->get_table('taken')} t
             INNER JOIN {$this->database->get_table('archetypes')} a 
                ON t.Archetype_ID = a.Archetype_ID
             WHERE t.Status = 'completed' 
             AND t.Completed BETWEEN %s AND %s
             GROUP BY t.Archetype_ID 
             ORDER BY count DESC 
             LIMIT 1",
            array( $date_range['start'], $date_range['end'] )
        )[0] ?? null;
        
        // Highest converting question set
        $performers['best_questions'] = $this->get_best_performing_questions( $date_range );
        
        // Best referral sources
        $performers['top_referrers'] = $this->get_top_referrers( $date_range );
        
        // Highest value prospects
        $performers['valuable_prospects'] = $this->get_valuable_prospects( $date_range );
        
        return $performers;
    }
    
    /**
     * Get recent activity
     * 
     * @param int $limit Number of items
     * @return array Recent activities
     */
    public function get_recent_activity( $limit = 10 ) {
        $activities = ActivityLog::where( 
            array(),
            array(
                'orderby' => 'Created',
                'order' => 'DESC',
                'limit' => $limit
            )
        );
        
        $formatted = array();
        foreach ( $activities as $activity ) {
            $data = $activity->get_data();
            
            $formatted[] = array(
                'id' => $activity->Activity_ID,
                'action' => $activity->Action,
                'description' => $this->format_activity_description( $activity ),
                'time' => DateUtil::relative( $activity->Created ),
                'timestamp' => $activity->Created,
                'data' => $data
            );
        }
        
        return $formatted;
    }
    
    /**
     * Get custom report data
     * 
     * @param array $config Report configuration
     * @return array Report data
     */
    public function generate_custom_report( array $config ) {
        $report = array(
            'meta' => array(
                'generated_at' => current_time( 'mysql' ),
                'period' => $config['period'] ?? '30days',
                'filters' => $config['filters'] ?? array()
            ),
            'data' => array()
        );
        
        $date_range = $this->get_date_range( $config['period'] );
        
        // Add requested sections
        foreach ( $config['sections'] ?? array() as $section ) {
            switch ( $section ) {
                case 'demographics':
                    $report['data']['demographics'] = $this->get_demographic_data( $date_range );
                    break;
                    
                case 'behavior':
                    $report['data']['behavior'] = $this->get_behavioral_data( $date_range );
                    break;
                    
                case 'performance':
                    $report['data']['performance'] = $this->get_performance_data( $date_range );
                    break;
                    
                case 'questions':
                    $report['data']['questions'] = $this->get_question_analytics( $date_range );
                    break;
                    
                case 'cohorts':
                    $report['data']['cohorts'] = $this->get_cohort_analysis( $date_range );
                    break;
            }
        }
        
        return $report;
    }
    
    /**
     * Get time series data
     * 
     * @param string $table Table name
     * @param string $date_field Date field
     * @param array  $date_range Date range
     * @param string $interval Interval
     * @param array  $where Additional conditions
     * @return array Time series
     */
    protected function get_time_series_data( $table, $date_field, $date_range, $interval, $where = array() ) {
        $group_by = $this->get_date_group_by( $interval );
        
        $where_sql = '';
        if ( ! empty( $where ) ) {
            $conditions = array();
            foreach ( $where as $field => $value ) {
                $conditions[] = sprintf( "%s = '%s'", $field, esc_sql( $value ) );
            }
            $where_sql = ' AND ' . implode( ' AND ', $conditions );
        }
        
        $query = "SELECT 
                    DATE_FORMAT({$date_field}, '{$group_by}') as period,
                    COUNT(*) as count 
                  FROM {$this->database->get_table($table)}
                  WHERE {$date_field} BETWEEN %s AND %s
                  {$where_sql}
                  GROUP BY period
                  ORDER BY period ASC";
        
        $results = $this->database->query( $query, array(
            $date_range['start'],
            $date_range['end']
        ));
        
        // Fill in missing periods
        return $this->fill_time_series_gaps( $results, $date_range, $interval );
    }
    
    /**
     * Get date range from period
     * 
     * @param string $period Period string
     * @return array Date range
     */
    protected function get_date_range( $period ) {
        $end = current_time( 'mysql' );
        
        switch ( $period ) {
            case '7days':
                $start = DateUtil::add( $end, '-7 days' );
                break;
            case '30days':
                $start = DateUtil::add( $end, '-30 days' );
                break;
            case '90days':
                $start = DateUtil::add( $end, '-90 days' );
                break;
            case '12months':
                $start = DateUtil::add( $end, '-12 months' );
                break;
            case 'custom':
                // Handle custom date range
                $start = $_GET['start_date'] ?? DateUtil::add( $end, '-30 days' );
                $end = $_GET['end_date'] ?? $end;
                break;
            default:
                $start = DateUtil::add( $end, '-30 days' );
        }
        
        return array(
            'start' => $start,
            'end' => $end
        );
    }
    
    /**
     * Determine appropriate interval
     * 
     * @param array $date_range Date range
     * @return string Interval
     */
    protected function determine_interval( array $date_range ) {
        $start = new \DateTime( $date_range['start'] );
        $end = new \DateTime( $date_range['end'] );
        $diff = $start->diff( $end );
        
        if ( $diff->days <= 7 ) {
            return 'hour';
        } elseif ( $diff->days <= 30 ) {
            return 'day';
        } elseif ( $diff->days <= 90 ) {
            return 'week';
        } else {
            return 'month';
        }
    }
    
    /**
     * Get date GROUP BY format
     * 
     * @param string $interval Interval
     * @return string Date format
     */
    protected function get_date_group_by( $interval ) {
        switch ( $interval ) {
            case 'hour':
                return '%Y-%m-%d %H:00:00';
            case 'day':
                return '%Y-%m-%d';
            case 'week':
                return '%Y-%u';
            case 'month':
                return '%Y-%m';
            default:
                return '%Y-%m-%d';
        }
    }
    
    /**
     * Fill time series gaps
     * 
     * @param array  $results Query results
     * @param array  $date_range Date range
     * @param string $interval Interval
     * @return array Filled series
     */
    protected function fill_time_series_gaps( $results, $date_range, $interval ) {
        $filled = array();
        $existing = array();
        
        // Create lookup
        foreach ( $results as $result ) {
            $existing[ $result->period ] = (int) $result->count;
        }
        
        // Generate all periods
        $current = new \DateTime( $date_range['start'] );
        $end = new \DateTime( $date_range['end'] );
        
        while ( $current <= $end ) {
            $period_key = $current->format( $this->get_php_date_format( $interval ) );
            
            $filled[] = array(
                'period' => $period_key,
                'count' => $existing[ $period_key ] ?? 0
            );
            
            // Increment based on interval
            switch ( $interval ) {
                case 'hour':
                    $current->modify( '+1 hour' );
                    break;
                case 'day':
                    $current->modify( '+1 day' );
                    break;
                case 'week':
                    $current->modify( '+1 week' );
                    break;
                case 'month':
                    $current->modify( '+1 month' );
                    break;
            }
        }
        
        return $filled;
    }
    
    /**
     * Get PHP date format for interval
     * 
     * @param string $interval Interval
     * @return string PHP date format
     */
    protected function get_php_date_format( $interval ) {
        switch ( $interval ) {
            case 'hour':
                return 'Y-m-d H:00:00';
            case 'day':
                return 'Y-m-d';
            case 'week':
                return 'Y-W';
            case 'month':
                return 'Y-m';
            default:
                return 'Y-m-d';
        }
    }
    
    /**
     * Get response patterns
     * 
     * @param array $date_range Date range
     * @return array Response patterns
     */
    protected function get_response_patterns( array $date_range ) {
        $query = "SELECT 
                    q.Question_Category,
                    AVG(r.Answer_Value) as avg_response,
                    COUNT(*) as response_count
                  FROM {$this->database->get_table('results')} r
                  INNER JOIN {$this->database->get_table('questions')} q 
                    ON r.Question_ID = q.Question_ID
                  INNER JOIN {$this->database->get_table('taken')} t 
                    ON r.Taken_ID = t.Taken_ID
                  WHERE t.Completed BETWEEN %s AND %s
                  GROUP BY q.Question_Category
                  ORDER BY avg_response DESC";
        
        $results = $this->database->query( $query, array(
            $date_range['start'],
            $date_range['end']
        ));
        
        $patterns = array();
        foreach ( $results as $result ) {
            $patterns[] = array(
                'category' => $result->Question_Category ?: 'General',
                'avg_response' => round( $result->avg_response, 2 ),
                'response_count' => (int) $result->response_count,
                'strength' => $this->calculate_response_strength( $result->avg_response )
            );
        }
        
        return $patterns;
    }
    
    /**
     * Calculate response strength
     * 
     * @param float $avg_response Average response
     * @return string Strength level
     */
    protected function calculate_response_strength( $avg_response ) {
        if ( $avg_response >= 7 ) {
            return 'very_strong';
        } elseif ( $avg_response >= 5.5 ) {
            return 'strong';
        } elseif ( $avg_response >= 4.5 ) {
            return 'moderate';
        } elseif ( $avg_response >= 3 ) {
            return 'weak';
        } else {
            return 'very_weak';
        }
    }
    
    /**
     * Format activity description
     * 
     * @param ActivityLog $activity Activity log entry
     * @return string Formatted description
     */
    protected function format_activity_description( $activity ) {
        $data = $activity->get_data();
        
        switch ( $activity->Action ) {
            case 'quiz_completed':
                return sprintf( 
                    __( 'Quiz completed with %s archetype', 'money-quiz' ),
                    $data['archetype'] ?? 'Unknown'
                );
                
            case 'email_sent':
                return sprintf(
                    __( 'Email sent to %s', 'money-quiz' ),
                    $data['email'] ?? 'user'
                );
                
            case 'prospect_created':
                return __( 'New prospect registered', 'money-quiz' );
                
            case 'ai_analysis_generated':
                return sprintf(
                    __( 'AI analysis generated using %s', 'money-quiz' ),
                    $data['provider'] ?? 'AI'
                );
                
            default:
                return $activity->Action;
        }
    }
    
    /**
     * Export analytics data
     * 
     * @param string $format Export format
     * @param array  $data Data to export
     * @return string File path or URL
     */
    public function export_analytics( $format, array $data ) {
        $export_service = new ExportService();
        
        switch ( $format ) {
            case 'csv':
                return $export_service->export_to_csv( $data, 'analytics' );
                
            case 'json':
                return $export_service->export_to_json( $data, 'analytics' );
                
            case 'pdf':
                return $export_service->export_to_pdf( $data, 'analytics' );
                
            default:
                throw new Exception( __( 'Invalid export format', 'money-quiz' ) );
        }
    }
}
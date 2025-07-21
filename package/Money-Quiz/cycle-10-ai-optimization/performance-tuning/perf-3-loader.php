<?php
/**
 * Performance Tuning Loader
 * 
 * @package MoneyQuiz\AI\Performance
 * @version 1.0.0
 */

namespace MoneyQuiz\AI\Performance;

require_once __DIR__ . '/perf-1-optimizer.php';
require_once __DIR__ . '/perf-2-query-optimizer.php';

/**
 * Performance Manager
 */
class PerformanceManager {
    
    private static $instance = null;
    private $optimizer;
    private $query_optimizer;
    
    private function __construct() {
        $this->optimizer = AIPerformanceOptimizer::getInstance();
        $this->query_optimizer = QueryOptimizer::getInstance();
    }
    
    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    public static function init() {
        $instance = self::getInstance();
        
        // Add monitoring
        add_action('money_quiz_ai_operation_start', [$instance, 'startMonitoring']);
        add_action('money_quiz_ai_operation_end', [$instance, 'endMonitoring']);
        
        // Admin page
        add_action('admin_menu', [$instance, 'addAdminPage']);
        
        // Optimization cron
        if (!wp_next_scheduled('money_quiz_ai_performance_check')) {
            wp_schedule_event(time(), 'hourly', 'money_quiz_ai_performance_check');
        }
        
        add_action('money_quiz_ai_performance_check', [$instance, 'runPerformanceCheck']);
        
        // Filter for insights
        add_filter('money_quiz_ai_performance_tuning_insights', [$instance, 'getInsights']);
    }
    
    public function startMonitoring($operation) {
        $context = $this->optimizer->startMonitoring($operation);
        set_transient('ai_monitoring_' . $operation, $context, 300);
    }
    
    public function endMonitoring($operation) {
        $context = get_transient('ai_monitoring_' . $operation);
        if ($context) {
            $this->optimizer->endMonitoring($context);
            delete_transient('ai_monitoring_' . $operation);
        }
    }
    
    public function addAdminPage() {
        add_submenu_page(
            'money-quiz-ai',
            'Performance',
            'Performance',
            'manage_options',
            'money-quiz-ai-performance',
            [$this, 'renderAdminPage']
        );
    }
    
    public function renderAdminPage() {
        $report = $this->optimizer->getPerformanceReport();
        $suggestions = $this->optimizer->suggestOptimizations();
        $query_report = $this->query_optimizer->getOptimizationReport();
        ?>
        <div class="wrap">
            <h1>AI Performance Tuning</h1>
            
            <div class="card">
                <h2>Operation Performance</h2>
                <table class="widefat">
                    <thead>
                        <tr>
                            <th>Operation</th>
                            <th>Avg Time</th>
                            <th>Avg Memory</th>
                            <th>Avg Queries</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($report as $op => $metrics): ?>
                        <tr>
                            <td><?php echo esc_html($op); ?></td>
                            <td><?php echo round($metrics['avg_time'], 3); ?>s</td>
                            <td><?php echo size_format($metrics['avg_memory']); ?></td>
                            <td><?php echo round($metrics['avg_queries']); ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            
            <?php if (!empty($suggestions)): ?>
            <div class="card">
                <h2>Optimization Suggestions</h2>
                <ul>
                <?php foreach ($suggestions as $suggestion): ?>
                    <li><?php echo esc_html($suggestion); ?></li>
                <?php endforeach; ?>
                </ul>
            </div>
            <?php endif; ?>
            
            <div class="card">
                <h2>Query Optimization</h2>
                <p>Cache Hit Rate: <?php echo $query_report['cache_hit_rate']; ?>%</p>
                
                <?php if (!empty($query_report['top_missing_indexes'])): ?>
                <h3>Suggested Indexes</h3>
                <ul>
                <?php foreach ($query_report['top_missing_indexes'] as $column => $count): ?>
                    <li><?php echo esc_html($column); ?> (<?php echo $count; ?> queries)</li>
                <?php endforeach; ?>
                </ul>
                <?php endif; ?>
            </div>
        </div>
        <?php
    }
    
    public function runPerformanceCheck() {
        $suggestions = $this->optimizer->suggestOptimizations();
        
        if (!empty($suggestions)) {
            // Log critical performance issues
            foreach ($suggestions as $suggestion) {
                error_log('Money Quiz AI Performance: ' . $suggestion);
            }
        }
        
        // Clean up old performance data
        $this->cleanupOldData();
    }
    
    private function cleanupOldData() {
        $metrics = get_option('money_quiz_ai_performance', []);
        
        // Keep only last 1000 entries per metric
        foreach (['response_times', 'memory_usage', 'query_counts'] as $type) {
            foreach ($metrics[$type] as $op => &$data) {
                if (count($data) > 1000) {
                    $data = array_slice($data, -1000);
                }
            }
        }
        
        update_option('money_quiz_ai_performance', $metrics);
    }
    
    public function getInsights() {
        $report = $this->optimizer->getPerformanceReport();
        $query_report = $this->query_optimizer->getOptimizationReport();
        
        return [
            'operations_monitored' => count($report),
            'avg_response_time' => $this->calculateOverallAvg($report, 'avg_time'),
            'cache_hit_rate' => $query_report['cache_hit_rate'],
            'optimization_suggestions' => count($this->optimizer->suggestOptimizations())
        ];
    }
    
    private function calculateOverallAvg($report, $metric) {
        if (empty($report)) return 0;
        
        $sum = array_sum(array_column($report, $metric));
        return $sum / count($report);
    }
}

// Initialize
add_action('plugins_loaded', [PerformanceManager::class, 'init']);

// Helper function
if (!function_exists('money_quiz_ai_optimize')) {
    function money_quiz_ai_optimize($operation, $callback, $args = []) {
        return AIPerformanceOptimizer::getInstance()->optimize($operation, $callback, $args);
    }
}
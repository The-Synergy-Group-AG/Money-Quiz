<?php
/**
 * Analytics Report Generator
 * 
 * @package MoneyQuiz\Analytics
 * @version 1.0.0
 */

namespace MoneyQuiz\Analytics;

/**
 * Report Generator
 */
class ReportGenerator {
    
    private static $instance = null;
    private $processor;
    private $templates = [];
    
    private function __construct() {
        $this->processor = MetricProcessor::getInstance();
        $this->registerTemplates();
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
     * Register report templates
     */
    private function registerTemplates() {
        $this->templates = [
            'dashboard' => [
                'name' => 'Dashboard Overview',
                'metrics' => ['total_quizzes', 'active_users', 'completion_rate', 'average_score'],
                'period' => 'month'
            ],
            'quiz_performance' => [
                'name' => 'Quiz Performance Report',
                'metrics' => ['completion_rate', 'average_score', 'time_metrics', 'question_performance'],
                'requires' => ['quiz_id']
            ],
            'user_engagement' => [
                'name' => 'User Engagement Report',
                'metrics' => ['active_users', 'user_engagement', 'popular_quizzes'],
                'period' => 'week'
            ],
            'weekly_summary' => [
                'name' => 'Weekly Summary',
                'metrics' => ['total_quizzes', 'active_users', 'completion_rate', 'average_score'],
                'period' => 'week'
            ]
        ];
    }
    
    /**
     * Generate report
     */
    public function generate($template, $params = []) {
        if (!isset($this->templates[$template])) {
            throw new \Exception("Unknown report template: {$template}");
        }
        
        $config = $this->templates[$template];
        
        // Check required params
        if (isset($config['requires'])) {
            foreach ($config['requires'] as $required) {
                if (!isset($params[$required])) {
                    throw new \Exception("Missing required parameter: {$required}");
                }
            }
        }
        
        // Set default period
        if (!isset($params['period']) && isset($config['period'])) {
            $params['period'] = $config['period'];
        }
        
        // Generate report data
        $report = [
            'name' => $config['name'],
            'generated_at' => current_time('mysql'),
            'parameters' => $params,
            'data' => []
        ];
        
        // Process metrics
        foreach ($config['metrics'] as $metric) {
            $report['data'][$metric] = $this->processor->process($metric, $params);
        }
        
        // Add visualizations
        $report['visualizations'] = $this->generateVisualizations($template, $report['data']);
        
        // Store report
        $this->storeReport($report);
        
        return $report;
    }
    
    /**
     * Generate visualizations
     */
    private function generateVisualizations($template, $data) {
        $visualizations = [];
        
        switch ($template) {
            case 'dashboard':
                $visualizations = [
                    'quiz_stats' => [
                        'type' => 'stats',
                        'data' => [
                            ['label' => 'Total Quizzes', 'value' => $data['total_quizzes']['total']],
                            ['label' => 'Active Users', 'value' => $data['active_users']['total']],
                            ['label' => 'Completion Rate', 'value' => $data['completion_rate']['rate'] . '%'],
                            ['label' => 'Avg Score', 'value' => $data['average_score']['average']]
                        ]
                    ],
                    'engagement_chart' => [
                        'type' => 'line',
                        'data' => $this->prepareEngagementChart($data)
                    ]
                ];
                break;
                
            case 'quiz_performance':
                $visualizations = [
                    'score_distribution' => [
                        'type' => 'histogram',
                        'data' => $this->prepareScoreDistribution($data)
                    ],
                    'question_difficulty' => [
                        'type' => 'bar',
                        'data' => $this->prepareQuestionDifficulty($data)
                    ]
                ];
                break;
        }
        
        return $visualizations;
    }
    
    /**
     * Prepare engagement chart data
     */
    private function prepareEngagementChart($data) {
        // Would process actual data for charting
        return [
            'labels' => ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'],
            'datasets' => [
                [
                    'label' => 'Active Users',
                    'data' => [45, 52, 38, 65, 58, 42, 35]
                ],
                [
                    'label' => 'Quizzes Completed',
                    'data' => [28, 35, 22, 48, 40, 25, 20]
                ]
            ]
        ];
    }
    
    /**
     * Prepare score distribution
     */
    private function prepareScoreDistribution($data) {
        // Would calculate actual distribution
        return [
            'labels' => ['0-20', '21-40', '41-60', '61-80', '81-100'],
            'data' => [5, 12, 25, 35, 23]
        ];
    }
    
    /**
     * Prepare question difficulty
     */
    private function prepareQuestionDifficulty($data) {
        if (!isset($data['question_performance'])) {
            return [];
        }
        
        $questions = [];
        foreach ($data['question_performance'] as $q) {
            $success_rate = $q->total_answers > 0 
                ? round(($q->correct_answers / $q->total_answers) * 100, 2)
                : 0;
            
            $questions[] = [
                'question' => substr($q->question, 0, 50) . '...',
                'success_rate' => $success_rate
            ];
        }
        
        return $questions;
    }
    
    /**
     * Store report
     */
    private function storeReport($report) {
        global $wpdb;
        
        $wpdb->insert(
            $wpdb->prefix . 'money_quiz_analytics_reports',
            [
                'name' => $report['name'],
                'data' => json_encode($report),
                'created_at' => $report['generated_at']
            ]
        );
        
        return $wpdb->insert_id;
    }
    
    /**
     * Get saved reports
     */
    public function getSavedReports($limit = 10) {
        global $wpdb;
        
        return $wpdb->get_results($wpdb->prepare("
            SELECT id, name, created_at
            FROM {$wpdb->prefix}money_quiz_analytics_reports
            ORDER BY created_at DESC
            LIMIT %d
        ", $limit));
    }
    
    /**
     * Get report by ID
     */
    public function getReport($id) {
        global $wpdb;
        
        $report = $wpdb->get_row($wpdb->prepare("
            SELECT * FROM {$wpdb->prefix}money_quiz_analytics_reports
            WHERE id = %d
        ", $id));
        
        if ($report) {
            $report->data = json_decode($report->data, true);
        }
        
        return $report;
    }
    
    /**
     * Export report
     */
    public function export($report_id, $format = 'pdf') {
        $report = $this->getReport($report_id);
        
        if (!$report) {
            throw new \Exception('Report not found');
        }
        
        switch ($format) {
            case 'pdf':
                return $this->exportPdf($report);
            case 'csv':
                return $this->exportCsv($report);
            case 'json':
                return $this->exportJson($report);
            default:
                throw new \Exception("Unsupported format: {$format}");
        }
    }
    
    /**
     * Export as PDF (simplified)
     */
    private function exportPdf($report) {
        // Would use PDF library
        $html = $this->renderReportHtml($report);
        
        // For now, return HTML
        header('Content-Type: application/pdf');
        header('Content-Disposition: attachment; filename="report.pdf"');
        echo $html;
        exit;
    }
    
    /**
     * Export as CSV
     */
    private function exportCsv($report) {
        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="report.csv"');
        
        $output = fopen('php://output', 'w');
        
        // Headers
        fputcsv($output, ['Metric', 'Value']);
        
        // Data
        foreach ($report->data['data'] as $metric => $values) {
            if (is_array($values)) {
                foreach ($values as $key => $value) {
                    fputcsv($output, ["{$metric}_{$key}", $value]);
                }
            }
        }
        
        fclose($output);
        exit;
    }
    
    /**
     * Export as JSON
     */
    private function exportJson($report) {
        header('Content-Type: application/json');
        header('Content-Disposition: attachment; filename="report.json"');
        
        echo json_encode($report->data, JSON_PRETTY_PRINT);
        exit;
    }
    
    /**
     * Render report HTML
     */
    private function renderReportHtml($report) {
        ob_start();
        ?>
        <html>
        <head>
            <title><?php echo esc_html($report->data['name']); ?></title>
            <style>
                body { font-family: Arial, sans-serif; }
                .metric { margin: 20px 0; }
                .metric h3 { color: #333; }
                .value { font-size: 24px; color: #0073aa; }
            </style>
        </head>
        <body>
            <h1><?php echo esc_html($report->data['name']); ?></h1>
            <p>Generated: <?php echo esc_html($report->data['generated_at']); ?></p>
            
            <?php foreach ($report->data['data'] as $metric => $values): ?>
                <div class="metric">
                    <h3><?php echo esc_html(ucwords(str_replace('_', ' ', $metric))); ?></h3>
                    <?php if (is_array($values)): ?>
                        <?php foreach ($values as $key => $value): ?>
                            <p><?php echo esc_html($key); ?>: <span class="value"><?php echo esc_html($value); ?></span></p>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        </body>
        </html>
        <?php
        return ob_get_clean();
    }
}
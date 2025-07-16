<?php
/**
 * Analytics Export Engine
 * 
 * @package MoneyQuiz\Analytics
 * @version 1.0.0
 */

namespace MoneyQuiz\Analytics;

/**
 * Export Engine
 */
class ExportEngine {
    
    private static $instance = null;
    private $processor;
    
    private function __construct() {
        $this->processor = MetricProcessor::getInstance();
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
     * Export data
     */
    public function export($type, $format, $params = []) {
        $data = $this->getData($type, $params);
        
        switch ($format) {
            case 'csv':
                return $this->exportCsv($data, $type);
            case 'json':
                return $this->exportJson($data);
            case 'excel':
                return $this->exportExcel($data, $type);
            case 'pdf':
                return $this->exportPdf($data, $type);
            default:
                throw new \Exception("Unsupported format: {$format}");
        }
    }
    
    /**
     * Get data for export
     */
    private function getData($type, $params) {
        global $wpdb;
        
        switch ($type) {
            case 'results':
                return $this->getResultsData($params);
            case 'users':
                return $this->getUsersData($params);
            case 'quizzes':
                return $this->getQuizzesData($params);
            case 'analytics':
                return $this->getAnalyticsData($params);
            default:
                throw new \Exception("Unknown export type: {$type}");
        }
    }
    
    /**
     * Get results data
     */
    private function getResultsData($params) {
        global $wpdb;
        
        $where = '1=1';
        if (isset($params['quiz_id'])) {
            $where .= $wpdb->prepare(' AND r.quiz_id = %d', $params['quiz_id']);
        }
        if (isset($params['date_from'])) {
            $where .= $wpdb->prepare(' AND r.completed_at >= %s', $params['date_from']);
        }
        if (isset($params['date_to'])) {
            $where .= $wpdb->prepare(' AND r.completed_at <= %s', $params['date_to']);
        }
        
        return $wpdb->get_results("
            SELECT 
                r.*,
                q.title as quiz_title,
                u.display_name as user_name,
                u.user_email
            FROM {$wpdb->prefix}money_quiz_results r
            LEFT JOIN {$wpdb->prefix}money_quiz_quizzes q ON r.quiz_id = q.id
            LEFT JOIN {$wpdb->users} u ON r.user_id = u.ID
            WHERE {$where}
            ORDER BY r.completed_at DESC
        ");
    }
    
    /**
     * Get users data
     */
    private function getUsersData($params) {
        global $wpdb;
        
        return $wpdb->get_results("
            SELECT 
                u.ID,
                u.user_login,
                u.display_name,
                u.user_email,
                u.user_registered,
                COUNT(DISTINCT r.quiz_id) as quizzes_taken,
                COUNT(r.id) as total_attempts,
                AVG(r.score) as avg_score,
                MAX(r.completed_at) as last_activity
            FROM {$wpdb->users} u
            LEFT JOIN {$wpdb->prefix}money_quiz_results r ON u.ID = r.user_id
            GROUP BY u.ID
            HAVING quizzes_taken > 0
            ORDER BY avg_score DESC
        ");
    }
    
    /**
     * Get quizzes data
     */
    private function getQuizzesData($params) {
        global $wpdb;
        
        return $wpdb->get_results("
            SELECT 
                q.*,
                COUNT(DISTINCT r.user_id) as unique_users,
                COUNT(r.id) as total_attempts,
                AVG(r.score) as avg_score,
                MIN(r.score) as min_score,
                MAX(r.score) as max_score
            FROM {$wpdb->prefix}money_quiz_quizzes q
            LEFT JOIN {$wpdb->prefix}money_quiz_results r ON q.id = r.quiz_id
            GROUP BY q.id
            ORDER BY q.created_at DESC
        ");
    }
    
    /**
     * Get analytics data
     */
    private function getAnalyticsData($params) {
        $metrics = $params['metrics'] ?? ['total_quizzes', 'active_users', 'completion_rate'];
        $data = [];
        
        foreach ($metrics as $metric) {
            $data[$metric] = $this->processor->process($metric, $params);
        }
        
        return $data;
    }
    
    /**
     * Export as CSV
     */
    private function exportCsv($data, $type) {
        $filename = "money-quiz-{$type}-" . date('Y-m-d') . ".csv";
        
        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        
        $output = fopen('php://output', 'w');
        
        // Write headers based on type
        switch ($type) {
            case 'results':
                fputcsv($output, [
                    'ID', 'Quiz', 'User', 'Email', 'Score', 'Time Taken', 'Completed At'
                ]);
                break;
            case 'users':
                fputcsv($output, [
                    'ID', 'Username', 'Name', 'Email', 'Quizzes Taken', 'Avg Score', 'Last Activity'
                ]);
                break;
            case 'quizzes':
                fputcsv($output, [
                    'ID', 'Title', 'Status', 'Attempts', 'Unique Users', 'Avg Score', 'Created'
                ]);
                break;
        }
        
        // Write data
        foreach ($data as $row) {
            $this->writeCsvRow($output, $row, $type);
        }
        
        fclose($output);
        exit;
    }
    
    /**
     * Write CSV row
     */
    private function writeCsvRow($output, $row, $type) {
        switch ($type) {
            case 'results':
                fputcsv($output, [
                    $row->id,
                    $row->quiz_title,
                    $row->user_name ?: 'Guest',
                    $row->user_email ?: 'N/A',
                    $row->score . '%',
                    $row->time_taken . 's',
                    $row->completed_at
                ]);
                break;
            case 'users':
                fputcsv($output, [
                    $row->ID,
                    $row->user_login,
                    $row->display_name,
                    $row->user_email,
                    $row->quizzes_taken,
                    round($row->avg_score, 2) . '%',
                    $row->last_activity
                ]);
                break;
            case 'quizzes':
                fputcsv($output, [
                    $row->id,
                    $row->title,
                    $row->status,
                    $row->total_attempts,
                    $row->unique_users,
                    round($row->avg_score, 2) . '%',
                    $row->created_at
                ]);
                break;
        }
    }
    
    /**
     * Export as JSON
     */
    private function exportJson($data) {
        $filename = "money-quiz-export-" . date('Y-m-d') . ".json";
        
        header('Content-Type: application/json');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        
        echo json_encode($data, JSON_PRETTY_PRINT);
        exit;
    }
    
    /**
     * Export as Excel (simplified)
     */
    private function exportExcel($data, $type) {
        // For now, export as CSV with Excel mime type
        header('Content-Type: application/vnd.ms-excel');
        header('Content-Disposition: attachment; filename="export.xls"');
        
        return $this->exportCsv($data, $type);
    }
    
    /**
     * Export as PDF (simplified)
     */
    private function exportPdf($data, $type) {
        $html = $this->generatePdfHtml($data, $type);
        
        // Would use PDF library like TCPDF or DomPDF
        header('Content-Type: application/pdf');
        header('Content-Disposition: attachment; filename="export.pdf"');
        
        echo $html; // Simplified - would convert to PDF
        exit;
    }
    
    /**
     * Generate PDF HTML
     */
    private function generatePdfHtml($data, $type) {
        ob_start();
        ?>
        <html>
        <head>
            <title>Money Quiz Export - <?php echo ucfirst($type); ?></title>
            <style>
                body { font-family: Arial, sans-serif; }
                table { width: 100%; border-collapse: collapse; }
                th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
                th { background-color: #f2f2f2; }
            </style>
        </head>
        <body>
            <h1>Money Quiz <?php echo ucfirst($type); ?> Export</h1>
            <p>Generated: <?php echo date('Y-m-d H:i:s'); ?></p>
            
            <table>
                <?php $this->renderPdfTable($data, $type); ?>
            </table>
        </body>
        </html>
        <?php
        return ob_get_clean();
    }
    
    /**
     * Render PDF table
     */
    private function renderPdfTable($data, $type) {
        // Headers
        echo '<thead><tr>';
        switch ($type) {
            case 'results':
                echo '<th>Quiz</th><th>User</th><th>Score</th><th>Date</th>';
                break;
            case 'users':
                echo '<th>Name</th><th>Email</th><th>Quizzes</th><th>Avg Score</th>';
                break;
            case 'quizzes':
                echo '<th>Title</th><th>Attempts</th><th>Avg Score</th><th>Status</th>';
                break;
        }
        echo '</tr></thead>';
        
        // Body
        echo '<tbody>';
        foreach ($data as $row) {
            echo '<tr>';
            switch ($type) {
                case 'results':
                    echo "<td>{$row->quiz_title}</td>";
                    echo "<td>{$row->user_name}</td>";
                    echo "<td>{$row->score}%</td>";
                    echo "<td>{$row->completed_at}</td>";
                    break;
                case 'users':
                    echo "<td>{$row->display_name}</td>";
                    echo "<td>{$row->user_email}</td>";
                    echo "<td>{$row->quizzes_taken}</td>";
                    echo "<td>" . round($row->avg_score, 2) . "%</td>";
                    break;
                case 'quizzes':
                    echo "<td>{$row->title}</td>";
                    echo "<td>{$row->total_attempts}</td>";
                    echo "<td>" . round($row->avg_score, 2) . "%</td>";
                    echo "<td>{$row->status}</td>";
                    break;
            }
            echo '</tr>';
        }
        echo '</tbody>';
    }
    
    /**
     * Schedule export
     */
    public function scheduleExport($type, $format, $params, $schedule) {
        // Would implement scheduled exports
        wp_schedule_event(time(), $schedule, 'money_quiz_export', [$type, $format, $params]);
    }
}
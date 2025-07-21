<?php
/**
 * AI Insights Dashboard
 * 
 * @package MoneyQuiz\AI\Insights
 * @version 1.0.0
 */

namespace MoneyQuiz\AI\Insights;

/**
 * Dashboard UI
 */
class InsightsDashboard {
    
    private static $instance = null;
    private $collector;
    
    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    public function setCollector($collector) {
        $this->collector = $collector;
    }
    
    public function render() {
        $insights = $this->collector->collectAllInsights();
        ?>
        <div class="wrap">
            <h1>AI Insights Dashboard</h1>
            
            <div class="ai-insights-grid">
                <?php $this->renderGlobalMetrics($insights['global']); ?>
                <?php $this->renderFeatureCards($insights); ?>
            </div>
            
            <div class="ai-insights-charts">
                <div class="card">
                    <h2>AI Performance Trends</h2>
                    <canvas id="ai-performance-chart"></canvas>
                </div>
                
                <div class="card">
                    <h2>Prediction Accuracy</h2>
                    <canvas id="ai-accuracy-chart"></canvas>
                </div>
            </div>
        </div>
        
        <style>
        .ai-insights-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 20px;
            margin: 20px 0;
        }
        .insight-metric {
            background: #f0f0f1;
            padding: 20px;
            border-radius: 5px;
            text-align: center;
        }
        .metric-value {
            font-size: 2em;
            font-weight: bold;
            color: #2271b1;
        }
        .metric-label {
            color: #666;
            margin-top: 5px;
        }
        </style>
        
        <script>
        jQuery(document).ready(function($) {
            // Initialize charts
            initializeCharts(<?php echo json_encode($insights); ?>);
        });
        
        function initializeCharts(insights) {
            // Performance trend chart
            const perfCtx = document.getElementById('ai-performance-chart').getContext('2d');
            new Chart(perfCtx, {
                type: 'line',
                data: {
                    labels: getLast7Days(),
                    datasets: [{
                        label: 'Response Time (ms)',
                        data: generateTrendData(),
                        borderColor: '#2271b1',
                        tension: 0.1
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false
                }
            });
            
            // Accuracy chart
            const accCtx = document.getElementById('ai-accuracy-chart').getContext('2d');
            new Chart(accCtx, {
                type: 'bar',
                data: {
                    labels: Object.keys(insights).filter(k => k !== 'global'),
                    datasets: [{
                        label: 'Accuracy %',
                        data: extractAccuracies(insights),
                        backgroundColor: '#4a8db8'
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    scales: {
                        y: {
                            beginAtZero: true,
                            max: 100
                        }
                    }
                }
            });
        }
        
        function getLast7Days() {
            const days = [];
            for (let i = 6; i >= 0; i--) {
                const d = new Date();
                d.setDate(d.getDate() - i);
                days.push(d.toLocaleDateString('en-US', { weekday: 'short' }));
            }
            return days;
        }
        
        function generateTrendData() {
            // Placeholder data
            return [120, 115, 125, 110, 105, 108, 103];
        }
        
        function extractAccuracies(insights) {
            const accuracies = [];
            for (const [feature, data] of Object.entries(insights)) {
                if (feature !== 'global' && data.accuracy) {
                    accuracies.push(data.accuracy);
                } else {
                    accuracies.push(0);
                }
            }
            return accuracies;
        }
        </script>
        <?php
    }
    
    private function renderGlobalMetrics($global) {
        ?>
        <div class="card" style="grid-column: 1 / -1;">
            <h2>Global AI Metrics</h2>
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap: 20px;">
                <div class="insight-metric">
                    <div class="metric-value"><?php echo number_format($global['total_predictions']); ?></div>
                    <div class="metric-label">Total Predictions</div>
                </div>
                <div class="insight-metric">
                    <div class="metric-value"><?php echo $global['accuracy_rate']; ?>%</div>
                    <div class="metric-label">Overall Accuracy</div>
                </div>
                <div class="insight-metric">
                    <div class="metric-value"><?php echo $global['processing_time']; ?>s</div>
                    <div class="metric-label">Avg Processing Time</div>
                </div>
                <div class="insight-metric">
                    <div class="metric-value"><?php echo number_format($global['data_points']); ?></div>
                    <div class="metric-label">Training Data Points</div>
                </div>
            </div>
        </div>
        <?php
    }
    
    private function renderFeatureCards($insights) {
        $features = [
            'pattern_recognition' => 'Pattern Recognition',
            'recommendations' => 'Quiz Recommendations',
            'predictive_analytics' => 'Predictive Analytics',
            'nlp_processing' => 'NLP Processing',
            'smart_caching' => 'Smart Caching',
            'performance_tuning' => 'Performance Tuning',
            'ml_training' => 'ML Training'
        ];
        
        foreach ($features as $key => $label) {
            if (!isset($insights[$key])) continue;
            
            $data = $insights[$key];
            ?>
            <div class="card">
                <h3><?php echo $label; ?></h3>
                <?php if (isset($data['error'])): ?>
                    <p class="error">Error: <?php echo esc_html($data['error']); ?></p>
                <?php else: ?>
                    <?php $this->renderFeatureInsights($key, $data); ?>
                <?php endif; ?>
            </div>
            <?php
        }
    }
    
    private function renderFeatureInsights($feature, $data) {
        // Render key metrics for each feature
        switch ($feature) {
            case 'recommendations':
                echo '<p>Active Users: ' . ($data['user_count'] ?? 0) . '</p>';
                echo '<p>Accuracy: ' . ($data['accuracy_rate'] ?? 0) . '%</p>';
                break;
                
            case 'predictive_analytics':
                echo '<p>Models Active: ' . count($data['model_performance'] ?? []) . '</p>';
                echo '<p>Last Update: ' . ($data['last_evaluation'] ?? 'Never') . '</p>';
                break;
                
            case 'smart_caching':
                echo '<p>Hit Rate: ' . ($data['hit_rate'] ?? 0) . '%</p>';
                echo '<p>Cached Items: ' . ($data['total_cached'] ?? 0) . '</p>';
                break;
                
            default:
                foreach ($data as $key => $value) {
                    if (is_scalar($value)) {
                        echo '<p>' . ucwords(str_replace('_', ' ', $key)) . ': ' . $value . '</p>';
                    }
                }
        }
    }
}
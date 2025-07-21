<?php
/**
 * ML Training Loader
 * 
 * @package MoneyQuiz\AI\Training
 * @version 1.0.0
 */

namespace MoneyQuiz\AI\Training;

require_once __DIR__ . '/train-1-pipeline.php';
require_once __DIR__ . '/train-2-scheduler.php';

/**
 * Training Manager
 */
class MLTrainingManager {
    
    private static $instance = null;
    private $pipeline;
    private $scheduler;
    
    private function __construct() {
        $this->pipeline = MLTrainingPipeline::getInstance();
        $this->scheduler = TrainingScheduler::getInstance();
        $this->scheduler->setPipeline($this->pipeline);
    }
    
    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    public static function init() {
        $instance = self::getInstance();
        
        // Register models
        $instance->registerModels();
        
        // Setup training schedule
        $instance->scheduler->scheduleTraining('daily');
        
        // Training action
        add_action('money_quiz_ml_training', [$instance->scheduler, 'runScheduledTraining']);
        
        // Admin page
        add_action('admin_menu', [$instance, 'addAdminPage']);
        
        // REST endpoints
        add_action('rest_api_init', [$instance, 'registerEndpoints']);
        
        // CLI commands
        if (defined('WP_CLI') && WP_CLI) {
            \WP_CLI::add_command('money-quiz train', [$instance, 'cliTrain']);
        }
        
        // Filter for insights
        add_filter('money_quiz_ai_ml_training_insights', [$instance, 'getInsights']);
    }
    
    private function registerModels() {
        // Register all AI models for training
        do_action('money_quiz_register_ml_models', $this->pipeline);
    }
    
    public function addAdminPage() {
        add_submenu_page(
            'money-quiz-ai',
            'ML Training',
            'ML Training',
            'manage_options',
            'money-quiz-ml-training',
            [$this, 'renderAdminPage']
        );
    }
    
    public function renderAdminPage() {
        $status = $this->pipeline->getTrainingStatus();
        $schedule = $this->scheduler->getScheduleInfo();
        ?>
        <div class="wrap">
            <h1>Machine Learning Training</h1>
            
            <div class="card">
                <h2>Training Status</h2>
                <p>Last Training: <?php echo $status['last_training']; ?></p>
                <p>Models Trained: <?php echo $status['models_trained']; ?></p>
                <p>Data Points: <?php echo $status['data_points']; ?></p>
                <p>Duration: <?php echo $status['duration']; ?>s</p>
                
                <p>
                    <button class="button button-primary" onclick="runTraining()">
                        Run Training Now
                    </button>
                </p>
            </div>
            
            <div class="card">
                <h2>Schedule</h2>
                <p>Status: <?php echo $schedule['enabled'] ? 'Enabled' : 'Disabled'; ?></p>
                <p>Frequency: <?php echo $schedule['frequency']; ?></p>
                <p>Next Run: <?php echo $schedule['next_run']; ?></p>
            </div>
        </div>
        
        <script>
        function runTraining() {
            jQuery.post('<?php echo rest_url('money-quiz/v1/training/run'); ?>', {
                _wpnonce: '<?php echo wp_create_nonce('wp_rest'); ?>'
            }, function(response) {
                alert('Training completed!');
                location.reload();
            });
        }
        </script>
        <?php
    }
    
    public function registerEndpoints() {
        register_rest_route('money-quiz/v1', '/training/run', [
            'methods' => 'POST',
            'callback' => [$this, 'runTraining'],
            'permission_callback' => function() {
                return current_user_can('manage_options');
            }
        ]);
        
        register_rest_route('money-quiz/v1', '/training/status', [
            'methods' => 'GET',
            'callback' => [$this, 'getStatus'],
            'permission_callback' => function() {
                return current_user_can('manage_options');
            }
        ]);
    }
    
    public function runTraining() {
        $results = $this->pipeline->train();
        
        return [
            'success' => true,
            'results' => $results
        ];
    }
    
    public function getStatus() {
        return [
            'training' => $this->pipeline->getTrainingStatus(),
            'schedule' => $this->scheduler->getScheduleInfo()
        ];
    }
    
    public function cliTrain($args, $assoc_args) {
        $model = $args[0] ?? null;
        
        \WP_CLI::log('Starting ML training...');
        
        $results = $this->pipeline->train($model);
        
        foreach ($results as $model_name => $result) {
            if (isset($result['error'])) {
                \WP_CLI::warning("$model_name: {$result['error']}");
            } else {
                \WP_CLI::success("$model_name: Training completed");
            }
        }
    }
    
    public function getInsights() {
        $status = $this->pipeline->getTrainingStatus();
        $log = get_option('money_quiz_ml_training_log', []);
        
        return [
            'last_training' => $status['last_training'],
            'total_sessions' => count($log),
            'avg_duration' => $this->getAvgDuration($log),
            'models_active' => count($this->pipeline->models ?? [])
        ];
    }
    
    private function getAvgDuration($log) {
        if (empty($log)) return 0;
        
        $durations = array_column($log, 'duration');
        return round(array_sum($durations) / count($durations), 2);
    }
}

// Initialize
add_action('plugins_loaded', [MLTrainingManager::class, 'init']);
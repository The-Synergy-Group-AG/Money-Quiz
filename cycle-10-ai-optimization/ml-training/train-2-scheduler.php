<?php
/**
 * Training Scheduler
 * 
 * @package MoneyQuiz\AI\Training
 * @version 1.0.0
 */

namespace MoneyQuiz\AI\Training;

/**
 * Schedules ML training
 */
class TrainingScheduler {
    
    private static $instance = null;
    private $pipeline;
    
    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    public function setPipeline($pipeline) {
        $this->pipeline = $pipeline;
    }
    
    public function scheduleTraining($frequency = 'daily') {
        $hook = 'money_quiz_ml_training';
        
        // Clear existing schedule
        wp_clear_scheduled_hook($hook);
        
        // Schedule new
        switch ($frequency) {
            case 'hourly':
                wp_schedule_event(time(), 'hourly', $hook);
                break;
            case 'twice_daily':
                wp_schedule_event(time(), 'twicedaily', $hook);
                break;
            case 'weekly':
                wp_schedule_event(time(), 'weekly', $hook);
                break;
            default:
                wp_schedule_event(time(), 'daily', $hook);
        }
    }
    
    public function runScheduledTraining() {
        if (!$this->shouldTrain()) {
            return;
        }
        
        // Run training
        $results = $this->pipeline->train();
        
        // Notify admins if issues
        if ($this->hasTrainingIssues($results)) {
            $this->notifyAdmins($results);
        }
        
        // Update next training time
        update_option('money_quiz_last_ml_training', current_time('mysql'));
    }
    
    private function shouldTrain() {
        // Check if enough new data
        $new_data_count = $this->getNewDataCount();
        
        if ($new_data_count < 100) {
            return false;
        }
        
        // Check if not recently trained
        $last_training = get_option('money_quiz_last_ml_training');
        if ($last_training && (time() - strtotime($last_training)) < 3600) {
            return false;
        }
        
        return true;
    }
    
    private function getNewDataCount() {
        global $wpdb;
        
        $last_training = get_option('money_quiz_last_ml_training', '2000-01-01');
        
        return $wpdb->get_var($wpdb->prepare("
            SELECT COUNT(*) FROM {$wpdb->prefix}money_quiz_results
            WHERE completed_at > %s
        ", $last_training));
    }
    
    private function hasTrainingIssues($results) {
        foreach ($results as $model => $result) {
            if (isset($result['error'])) {
                return true;
            }
        }
        return false;
    }
    
    private function notifyAdmins($results) {
        $admin_email = get_option('admin_email');
        $subject = 'Money Quiz ML Training Issue';
        $message = "ML Training encountered issues:\n\n";
        
        foreach ($results as $model => $result) {
            if (isset($result['error'])) {
                $message .= "Model: $model\nError: {$result['error']}\n\n";
            }
        }
        
        wp_mail($admin_email, $subject, $message);
    }
    
    public function getScheduleInfo() {
        $next_scheduled = wp_next_scheduled('money_quiz_ml_training');
        
        return [
            'enabled' => $next_scheduled !== false,
            'next_run' => $next_scheduled ? date('Y-m-d H:i:s', $next_scheduled) : 'Not scheduled',
            'frequency' => $this->getCurrentFrequency(),
            'last_run' => get_option('money_quiz_last_ml_training', 'Never')
        ];
    }
    
    private function getCurrentFrequency() {
        $schedules = wp_get_schedules();
        $next = wp_next_scheduled('money_quiz_ml_training');
        
        if (!$next) return 'Not scheduled';
        
        $schedule = wp_get_schedule('money_quiz_ml_training');
        
        return $schedules[$schedule]['display'] ?? $schedule;
    }
}
<?php
/**
 * Legacy Handler - Routes requests to original Money Quiz implementation
 * 
 * @package MoneyQuiz
 * @subpackage Routing\Handlers
 * @since 1.5.0
 */

namespace MoneyQuiz\Routing\Handlers;

if (!defined('ABSPATH')) {
    exit;
}

class LegacyHandler {
    
    /**
     * Handle request using legacy system
     * 
     * @param string $action
     * @param array $data
     * @return array
     */
    public function handle($action, $data) {
        // Map new action names to legacy function calls
        $legacy_map = [
            'quiz_display' => 'moneyquiz_display_quiz',
            'quiz_list' => 'moneyquiz_list_quizzes',
            'quiz_submit' => 'moneyquiz_submit_quiz',
            'quiz_results' => 'moneyquiz_show_results',
            'archetype_fetch' => 'moneyquiz_get_archetype',
            'archetype_list' => 'moneyquiz_list_archetypes',
            'statistics_view' => 'moneyquiz_view_statistics',
            'statistics_summary' => 'moneyquiz_statistics_summary'
        ];
        
        $legacy_function = $legacy_map[$action] ?? null;
        
        if (!$legacy_function || !function_exists($legacy_function)) {
            return [
                'success' => false,
                'error' => 'Legacy function not found',
                'action' => $action
            ];
        }
        
        // Call legacy function with data
        try {
            ob_start();
            $result = call_user_func($legacy_function, $data);
            $output = ob_get_clean();
            
            return [
                'success' => true,
                'data' => $result,
                'output' => $output,
                'handler' => 'legacy'
            ];
            
        } catch (\Exception $e) {
            ob_end_clean();
            throw $e;
        }
    }
    
    /**
     * Handle quiz display using legacy system
     * 
     * @param string $uri
     * @return array
     */
    public function handle_quiz_display_legacy($uri) {
        // Extract quiz ID from URI
        preg_match('/\/money-quiz\/display\/([0-9]+)/', $uri, $matches);
        $quiz_id = $matches[1] ?? 0;
        
        return $this->handle('quiz_display', ['quiz_id' => $quiz_id]);
    }
    
    /**
     * Handle quiz submission using legacy system
     * 
     * @param string $uri
     * @return array
     */
    public function handle_quiz_submit_legacy($uri) {
        return $this->handle('quiz_submit', $_POST);
    }
    
    /**
     * Handle quiz results using legacy system
     * 
     * @param string $uri
     * @return array
     */
    public function handle_quiz_results_legacy($uri) {
        // Extract result ID from URI
        preg_match('/\/money-quiz\/results\/([0-9]+)/', $uri, $matches);
        $result_id = $matches[1] ?? 0;
        
        return $this->handle('quiz_results', ['result_id' => $result_id]);
    }
}
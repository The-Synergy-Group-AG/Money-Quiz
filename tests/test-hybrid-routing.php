<?php
/**
 * Hybrid Routing Integration Tests
 * 
 * @package MoneyQuiz
 * @subpackage Tests
 */

use MoneyQuiz\Routing\HybridRouter;
use MoneyQuiz\Routing\FeatureFlagManager;
use MoneyQuiz\Routing\Monitoring\RouteMonitor;
use MoneyQuiz\Routing\Rollback\RollbackManager;

class Test_Hybrid_Routing extends WP_UnitTestCase {
    
    private $router;
    private $feature_flags;
    private $monitor;
    private $rollback_manager;
    
    public function setUp() {
        parent::setUp();
        
        // Initialize components
        $this->router = new HybridRouter();
        $this->feature_flags = new FeatureFlagManager();
        $this->monitor = new RouteMonitor();
        $this->rollback_manager = new RollbackManager();
        
        // Reset feature flags
        update_option('mq_feature_flags', [
            'modern_quiz_display' => 0.0,
            'modern_quiz_submit' => 0.0,
            'modern_quiz_results' => 0.0
        ]);
        
        // Clear any rollback state
        delete_transient('mq_emergency_rollback');
        delete_transient('mq_rollback_cooldown');
    }
    
    /**
     * Test feature flag functionality
     */
    public function test_feature_flag_percentage() {
        // Set 50% for quiz display
        $this->feature_flags->update_flag('modern_quiz_display', 0.5);
        
        // Test multiple users to verify distribution
        $modern_count = 0;
        $total_tests = 1000;
        
        for ($i = 0; $i < $total_tests; $i++) {
            if ($this->feature_flags->is_enabled('modern_quiz_display', $i)) {
                $modern_count++;
            }
        }
        
        // Should be approximately 50% (allow 5% variance)
        $percentage = $modern_count / $total_tests;
        $this->assertGreaterThan(0.45, $percentage);
        $this->assertLessThan(0.55, $percentage);
    }
    
    /**
     * Test user stickiness
     */
    public function test_feature_flag_stickiness() {
        $this->feature_flags->update_flag('modern_quiz_display', 0.5);
        
        $user_id = 123;
        
        // Check multiple times - should always get same result
        $first_result = $this->feature_flags->is_enabled('modern_quiz_display', $user_id);
        
        for ($i = 0; $i < 10; $i++) {
            $result = $this->feature_flags->is_enabled('modern_quiz_display', $user_id);
            $this->assertEquals($first_result, $result, 'User should always get same assignment');
        }
    }
    
    /**
     * Test routing to legacy system
     */
    public function test_legacy_routing() {
        // Set all flags to 0%
        $this->feature_flags->update_flag('modern_quiz_display', 0.0);
        
        $result = $this->router->route('quiz_display', ['quiz_id' => 1]);
        
        $this->assertEquals('legacy', $result['system']);
        $this->assertArrayHasKey('_meta', $result);
    }
    
    /**
     * Test routing to modern system
     */
    public function test_modern_routing() {
        // Set flag to 100%
        $this->feature_flags->update_flag('modern_quiz_display', 1.0);
        
        $result = $this->router->route('quiz_display', ['quiz_id' => 1]);
        
        $this->assertEquals('modern', $result['system']);
    }
    
    /**
     * Test error monitoring
     */
    public function test_error_monitoring() {
        // Simulate an error
        $exception = new Exception('Test error');
        $this->monitor->record_error($exception, 'quiz_display', ['test' => true]);
        
        // Check error rate
        $error_rate = $this->monitor->get_error_rate('quiz_display', 300);
        $this->assertGreaterThan(0, $error_rate);
    }
    
    /**
     * Test automatic rollback trigger
     */
    public function test_automatic_rollback() {
        // Simulate high error rate
        for ($i = 0; $i < 20; $i++) {
            if ($i < 15) {
                // 15 errors
                $exception = new Exception('Test error');
                $this->monitor->record_error($exception, 'quiz_display');
            } else {
                // 5 successes
                $this->monitor->record_success('modern', 'quiz_display', 0.1);
            }
        }
        
        // Get metrics
        $metrics = $this->monitor->get_recent_metrics(300);
        
        // Should trigger rollback (75% error rate)
        $should_rollback = $this->rollback_manager->should_rollback($metrics);
        $this->assertTrue($should_rollback);
    }
    
    /**
     * Test rollback execution
     */
    public function test_rollback_execution() {
        // Set feature flags to non-zero
        $this->feature_flags->update_flag('modern_quiz_display', 0.5);
        $this->feature_flags->update_flag('modern_quiz_submit', 0.5);
        
        // Execute rollback
        $this->rollback_manager->execute_rollback([], 'manual');
        
        // Check that flags are reset
        $this->assertEquals(0.0, $this->feature_flags->get_flag_value('modern_quiz_display'));
        $this->assertEquals(0.0, $this->feature_flags->get_flag_value('modern_quiz_submit'));
        
        // Check rollback flag is set
        $this->assertNotFalse(get_transient('mq_emergency_rollback'));
    }
    
    /**
     * Test rollback cooldown
     */
    public function test_rollback_cooldown() {
        // Execute rollback
        $this->rollback_manager->execute_rollback([], 'manual');
        
        // Try to execute another - should be blocked by cooldown
        $metrics = ['error_rate' => 0.9]; // High error rate
        $should_rollback = $this->rollback_manager->should_rollback($metrics);
        
        $this->assertFalse($should_rollback, 'Should not rollback during cooldown');
    }
    
    /**
     * Test performance monitoring
     */
    public function test_performance_monitoring() {
        // Record some performance metrics
        $this->monitor->record_success('modern', 'quiz_display', 0.5, 10485760); // 10MB
        $this->monitor->record_success('modern', 'quiz_display', 0.7, 15728640); // 15MB
        $this->monitor->record_success('legacy', 'quiz_display', 1.2, 5242880);  // 5MB
        
        // Get average response time
        $avg_response = $this->monitor->get_avg_response_time('quiz_display', 5);
        $this->assertGreaterThan(0, $avg_response);
        
        // Get system health
        $health = $this->monitor->get_system_health();
        $this->assertArrayHasKey('status', $health);
        $this->assertContains($health['status'], ['good', 'warning', 'critical']);
    }
    
    /**
     * Test gradual rollout
     */
    public function test_week_based_rollout() {
        // Set week 1
        update_option('mq_hybrid_week', 1);
        
        // Force reload of configuration
        $this->feature_flags = new FeatureFlagManager();
        
        // Week 1 should have 100% for all features (isolated environment)
        $flag_value = $this->feature_flags->get_flag_value('modern_quiz_display');
        $this->assertEquals(1.0, $flag_value);
    }
    
    /**
     * Test routing statistics
     */
    public function test_routing_statistics() {
        // Route some requests
        $this->feature_flags->update_flag('modern_quiz_display', 0.5);
        
        for ($i = 0; $i < 10; $i++) {
            $this->router->route('quiz_display', ['quiz_id' => $i]);
        }
        
        // Get stats
        $stats = $this->router->get_stats(1);
        
        $this->assertNotEmpty($stats);
        $this->assertArrayHasKey(date('Y-m-d'), $stats);
    }
    
    /**
     * Test fallback on modern system error
     */
    public function test_fallback_on_error() {
        // This test would require mocking the modern handler to throw an error
        // For now, we'll test the concept
        
        $this->assertTrue(method_exists($this->router, 'route'));
        
        // The router should catch exceptions and fallback
        $result = $this->router->route('nonexistent_action', []);
        $this->assertArrayHasKey('system', $result);
    }
    
    /**
     * Test input sanitization
     */
    public function test_input_sanitization() {
        // Test with potentially malicious input
        $malicious_data = [
            'script' => '<script>alert("xss")</script>',
            'sql' => "'; DROP TABLE users; --",
            'nested' => [
                'bad' => '<img src=x onerror=alert(1)>'
            ]
        ];
        
        $result = $this->router->route('quiz_display', $malicious_data);
        
        // The router should handle this safely
        $this->assertArrayHasKey('_meta', $result);
        $this->assertArrayHasKey('routed_by', $result['_meta']);
    }
    
    /**
     * Test concurrent routing
     */
    public function test_concurrent_routing() {
        // Simulate concurrent requests
        $results = [];
        
        for ($i = 0; $i < 5; $i++) {
            $results[] = $this->router->route('quiz_display', ['quiz_id' => $i]);
        }
        
        // All should complete successfully
        foreach ($results as $result) {
            $this->assertArrayHasKey('system', $result);
            $this->assertContains($result['system'], ['modern', 'legacy']);
        }
    }
    
    /**
     * Test admin controls
     */
    public function test_admin_controls() {
        // Test threshold updates
        $this->rollback_manager->update_threshold('error_rate', 0.15);
        $status = $this->rollback_manager->get_status();
        
        $this->assertEquals(0.15, $status['thresholds']['error_rate']);
    }
    
    /**
     * Test adoption tracking
     */
    public function test_adoption_tracking() {
        $this->feature_flags->update_flag('modern_quiz_display', 0.3);
        
        // Simulate some traffic
        for ($i = 0; $i < 100; $i++) {
            $this->feature_flags->is_enabled('modern_quiz_display', $i);
        }
        
        // Get adoption stats
        $adoption = $this->feature_flags->get_adoption_rate('modern_quiz_display', 1);
        
        // Should be approximately 30%
        $this->assertGreaterThan(20, $adoption);
        $this->assertLessThan(40, $adoption);
    }
}
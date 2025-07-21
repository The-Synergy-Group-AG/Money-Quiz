<?php
/**
 * Performance Regression Testing System
 * 
 * Automatically detects performance regressions by comparing current
 * performance metrics against historical baselines.
 */

namespace MoneyQuiz\Performance\Monitoring;

use Exception;

class PerformanceRegressionTester {
    private array $config;
    private BaselineStorage $baselineStorage;
    private MetricsCollector $metricsCollector;
    private array $testSuites = [];
    private array $results = [];
    
    public function __construct(array $config = []) {
        $this->config = array_merge([
            'baseline_window' => 7, // days
            'regression_threshold' => 0.1, // 10% degradation
            'confidence_level' => 0.95,
            'min_samples' => 30,
            'outlier_detection' => true,
            'auto_baseline_update' => false,
            'storage_backend' => 'file' // file, database, redis
        ], $config);
        
        $this->initializeStorage();
        $this->metricsCollector = new MetricsCollector();
    }
    
    /**
     * Register a performance test suite
     */
    public function registerSuite(string $name, PerformanceTestSuite $suite): void {
        $this->testSuites[$name] = $suite;
    }
    
    /**
     * Run regression tests
     */
    public function runTests(array $suiteNames = []): RegressionTestResults {
        if (empty($suiteNames)) {
            $suiteNames = array_keys($this->testSuites);
        }
        
        $this->results = [];
        
        foreach ($suiteNames as $suiteName) {
            if (!isset($this->testSuites[$suiteName])) {
                continue;
            }
            
            $suite = $this->testSuites[$suiteName];
            $this->results[$suiteName] = $this->runSuite($suite);
        }
        
        return new RegressionTestResults($this->results);
    }
    
    /**
     * Run a single test suite
     */
    private function runSuite(PerformanceTestSuite $suite): array {
        $results = [];
        
        foreach ($suite->getTests() as $test) {
            $testResult = $this->runTest($test);
            $results[$test->getName()] = $testResult;
        }
        
        return $results;
    }
    
    /**
     * Run a single performance test
     */
    private function runTest(PerformanceTest $test): array {
        // Warm up
        for ($i = 0; $i < $test->getWarmupIterations(); $i++) {
            $test->run();
        }
        
        // Collect samples
        $samples = [];
        $iterations = $test->getIterations();
        
        for ($i = 0; $i < $iterations; $i++) {
            $startTime = microtime(true);
            $startMemory = memory_get_usage(true);
            
            try {
                $test->run();
                
                $duration = (microtime(true) - $startTime) * 1000; // ms
                $memoryUsed = memory_get_usage(true) - $startMemory;
                
                $samples[] = [
                    'duration' => $duration,
                    'memory' => $memoryUsed,
                    'timestamp' => time(),
                    'success' => true
                ];
            } catch (Exception $e) {
                $samples[] = [
                    'duration' => (microtime(true) - $startTime) * 1000,
                    'memory' => 0,
                    'timestamp' => time(),
                    'success' => false,
                    'error' => $e->getMessage()
                ];
            }
            
            // Small delay between iterations
            if ($i < $iterations - 1) {
                usleep(10000); // 10ms
            }
        }
        
        // Remove outliers if configured
        if ($this->config['outlier_detection']) {
            $samples = $this->removeOutliers($samples);
        }
        
        // Calculate statistics
        $stats = $this->calculateStatistics($samples);
        
        // Get baseline
        $baseline = $this->baselineStorage->getBaseline($test->getName());
        
        // Compare with baseline
        $comparison = $this->compareWithBaseline($stats, $baseline);
        
        // Store current results
        $this->storeResults($test->getName(), $stats);
        
        return [
            'test' => $test->getName(),
            'samples' => count($samples),
            'statistics' => $stats,
            'baseline' => $baseline,
            'comparison' => $comparison,
            'regression_detected' => $comparison['regression_detected'] ?? false
        ];
    }
    
    /**
     * Calculate statistics from samples
     */
    private function calculateStatistics(array $samples): array {
        $durations = array_column($samples, 'duration');
        $memories = array_column($samples, 'memory');
        $successCount = count(array_filter($samples, fn($s) => $s['success']));
        
        sort($durations);
        
        return [
            'duration' => [
                'mean' => $this->mean($durations),
                'median' => $this->median($durations),
                'stddev' => $this->standardDeviation($durations),
                'min' => min($durations),
                'max' => max($durations),
                'p50' => $this->percentile($durations, 50),
                'p75' => $this->percentile($durations, 75),
                'p90' => $this->percentile($durations, 90),
                'p95' => $this->percentile($durations, 95),
                'p99' => $this->percentile($durations, 99)
            ],
            'memory' => [
                'mean' => $this->mean($memories),
                'median' => $this->median($memories),
                'stddev' => $this->standardDeviation($memories),
                'min' => min($memories),
                'max' => max($memories)
            ],
            'success_rate' => (count($samples) > 0) ? $successCount / count($samples) : 0,
            'sample_count' => count($samples),
            'timestamp' => time()
        ];
    }
    
    /**
     * Compare current statistics with baseline
     */
    private function compareWithBaseline(array $current, ?array $baseline): array {
        if (!$baseline || !isset($baseline['statistics'])) {
            return [
                'regression_detected' => false,
                'message' => 'No baseline available for comparison'
            ];
        }
        
        $baselineStats = $baseline['statistics'];
        $comparison = [
            'regression_detected' => false,
            'metrics' => []
        ];
        
        // Compare duration metrics
        $durationComparison = $this->compareMetric(
            $current['duration']['mean'],
            $baselineStats['duration']['mean'],
            $current['duration']['stddev'],
            $baselineStats['duration']['stddev']
        );
        
        $comparison['metrics']['duration'] = $durationComparison;
        
        if ($durationComparison['regression']) {
            $comparison['regression_detected'] = true;
        }
        
        // Compare memory metrics
        $memoryComparison = $this->compareMetric(
            $current['memory']['mean'],
            $baselineStats['memory']['mean'],
            $current['memory']['stddev'],
            $baselineStats['memory']['stddev']
        );
        
        $comparison['metrics']['memory'] = $memoryComparison;
        
        if ($memoryComparison['regression']) {
            $comparison['regression_detected'] = true;
        }
        
        // Compare success rate
        if ($current['success_rate'] < $baselineStats['success_rate'] * 0.95) {
            $comparison['regression_detected'] = true;
            $comparison['metrics']['success_rate'] = [
                'regression' => true,
                'current' => $current['success_rate'],
                'baseline' => $baselineStats['success_rate'],
                'change' => $current['success_rate'] - $baselineStats['success_rate']
            ];
        }
        
        return $comparison;
    }
    
    /**
     * Compare individual metric
     */
    private function compareMetric(float $currentMean, float $baselineMean, 
                                  float $currentStdDev, float $baselineStdDev): array {
        $change = $currentMean - $baselineMean;
        $changePercent = ($baselineMean > 0) ? ($change / $baselineMean) * 100 : 0;
        
        // Calculate if change is statistically significant
        $pooledStdDev = sqrt(($currentStdDev ** 2 + $baselineStdDev ** 2) / 2);
        $effectSize = ($pooledStdDev > 0) ? abs($change) / $pooledStdDev : 0;
        
        // Check for regression
        $regression = false;
        if ($changePercent > $this->config['regression_threshold'] * 100) {
            // Performance degradation
            if ($effectSize > 0.5) { // Medium effect size
                $regression = true;
            }
        }
        
        return [
            'regression' => $regression,
            'current' => $currentMean,
            'baseline' => $baselineMean,
            'change' => $change,
            'change_percent' => $changePercent,
            'effect_size' => $effectSize,
            'significant' => $effectSize > 0.5
        ];
    }
    
    /**
     * Remove outliers using IQR method
     */
    private function removeOutliers(array $samples): array {
        $durations = array_column($samples, 'duration');
        sort($durations);
        
        $q1 = $this->percentile($durations, 25);
        $q3 = $this->percentile($durations, 75);
        $iqr = $q3 - $q1;
        
        $lowerBound = $q1 - (1.5 * $iqr);
        $upperBound = $q3 + (1.5 * $iqr);
        
        return array_filter($samples, function ($sample) use ($lowerBound, $upperBound) {
            return $sample['duration'] >= $lowerBound && $sample['duration'] <= $upperBound;
        });
    }
    
    /**
     * Store test results
     */
    private function storeResults(string $testName, array $statistics): void {
        $this->metricsCollector->record($testName, $statistics);
        
        // Update baseline if configured
        if ($this->config['auto_baseline_update']) {
            $this->updateBaseline($testName, $statistics);
        }
    }
    
    /**
     * Update baseline with new results
     */
    public function updateBaseline(string $testName, array $statistics = null): void {
        if ($statistics === null) {
            // Get recent statistics
            $statistics = $this->calculateBaselineFromHistory($testName);
        }
        
        $this->baselineStorage->saveBaseline($testName, [
            'statistics' => $statistics,
            'updated_at' => time(),
            'version' => $this->getApplicationVersion()
        ]);
    }
    
    /**
     * Calculate baseline from historical data
     */
    private function calculateBaselineFromHistory(string $testName): array {
        $window = time() - ($this->config['baseline_window'] * 86400);
        $history = $this->metricsCollector->getHistory($testName, $window);
        
        if (count($history) < $this->config['min_samples']) {
            throw new Exception("Insufficient historical data for baseline calculation");
        }
        
        // Aggregate historical statistics
        $durations = [];
        $memories = [];
        
        foreach ($history as $record) {
            $durations[] = $record['statistics']['duration']['mean'];
            $memories[] = $record['statistics']['memory']['mean'];
        }
        
        return [
            'duration' => [
                'mean' => $this->mean($durations),
                'median' => $this->median($durations),
                'stddev' => $this->standardDeviation($durations),
                'min' => min($durations),
                'max' => max($durations),
                'p50' => $this->percentile($durations, 50),
                'p75' => $this->percentile($durations, 75),
                'p90' => $this->percentile($durations, 90),
                'p95' => $this->percentile($durations, 95),
                'p99' => $this->percentile($durations, 99)
            ],
            'memory' => [
                'mean' => $this->mean($memories),
                'median' => $this->median($memories),
                'stddev' => $this->standardDeviation($memories),
                'min' => min($memories),
                'max' => max($memories)
            ],
            'sample_count' => count($history)
        ];
    }
    
    /**
     * Initialize storage backend
     */
    private function initializeStorage(): void {
        switch ($this->config['storage_backend']) {
            case 'file':
                $this->baselineStorage = new FileBaselineStorage($this->config);
                break;
            case 'database':
                $this->baselineStorage = new DatabaseBaselineStorage($this->config);
                break;
            case 'redis':
                $this->baselineStorage = new RedisBaselineStorage($this->config);
                break;
            default:
                throw new Exception("Unknown storage backend: {$this->config['storage_backend']}");
        }
    }
    
    /**
     * Get application version
     */
    private function getApplicationVersion(): string {
        // This would typically read from a version file or git
        return '1.0.0';
    }
    
    // Statistical functions
    private function mean(array $values): float {
        return count($values) > 0 ? array_sum($values) / count($values) : 0;
    }
    
    private function median(array $values): float {
        $count = count($values);
        if ($count === 0) return 0;
        
        sort($values);
        $middle = floor($count / 2);
        
        if ($count % 2) {
            return $values[$middle];
        } else {
            return ($values[$middle - 1] + $values[$middle]) / 2;
        }
    }
    
    private function standardDeviation(array $values): float {
        $count = count($values);
        if ($count === 0) return 0;
        
        $mean = $this->mean($values);
        $variance = 0;
        
        foreach ($values as $value) {
            $variance += pow($value - $mean, 2);
        }
        
        return sqrt($variance / $count);
    }
    
    private function percentile(array $values, int $percentile): float {
        $count = count($values);
        if ($count === 0) return 0;
        
        $index = ceil(($percentile / 100) * $count) - 1;
        return $values[$index];
    }
}

/**
 * Performance test interface
 */
interface PerformanceTest {
    public function getName(): string;
    public function run(): void;
    public function getIterations(): int;
    public function getWarmupIterations(): int;
}

/**
 * Performance test suite
 */
class PerformanceTestSuite {
    private string $name;
    private array $tests = [];
    private array $setup = [];
    private array $teardown = [];
    
    public function __construct(string $name) {
        $this->name = $name;
    }
    
    public function addTest(PerformanceTest $test): void {
        $this->tests[] = $test;
    }
    
    public function addSetup(callable $callback): void {
        $this->setup[] = $callback;
    }
    
    public function addTeardown(callable $callback): void {
        $this->teardown[] = $callback;
    }
    
    public function getTests(): array {
        return $this->tests;
    }
    
    public function runSetup(): void {
        foreach ($this->setup as $callback) {
            $callback();
        }
    }
    
    public function runTeardown(): void {
        foreach ($this->teardown as $callback) {
            $callback();
        }
    }
}

/**
 * Simple performance test implementation
 */
class SimplePerformanceTest implements PerformanceTest {
    private string $name;
    private callable $callback;
    private int $iterations;
    private int $warmupIterations;
    
    public function __construct(string $name, callable $callback, int $iterations = 100, int $warmupIterations = 10) {
        $this->name = $name;
        $this->callback = $callback;
        $this->iterations = $iterations;
        $this->warmupIterations = $warmupIterations;
    }
    
    public function getName(): string {
        return $this->name;
    }
    
    public function run(): void {
        call_user_func($this->callback);
    }
    
    public function getIterations(): int {
        return $this->iterations;
    }
    
    public function getWarmupIterations(): int {
        return $this->warmupIterations;
    }
}

/**
 * Regression test results
 */
class RegressionTestResults {
    private array $results;
    
    public function __construct(array $results) {
        $this->results = $results;
    }
    
    public function hasRegressions(): bool {
        foreach ($this->results as $suite) {
            foreach ($suite as $test) {
                if ($test['regression_detected']) {
                    return true;
                }
            }
        }
        return false;
    }
    
    public function getRegressions(): array {
        $regressions = [];
        
        foreach ($this->results as $suiteName => $suite) {
            foreach ($suite as $testName => $test) {
                if ($test['regression_detected']) {
                    $regressions[$suiteName][$testName] = $test;
                }
            }
        }
        
        return $regressions;
    }
    
    public function generateReport(): string {
        $report = "Performance Regression Test Report\n";
        $report .= "==================================\n\n";
        
        foreach ($this->results as $suiteName => $suite) {
            $report .= "Suite: {$suiteName}\n";
            $report .= str_repeat('-', strlen($suiteName) + 7) . "\n";
            
            foreach ($suite as $testName => $test) {
                $status = $test['regression_detected'] ? '✗ REGRESSION' : '✓ PASS';
                $report .= sprintf("  %s %s\n", $status, $testName);
                
                if ($test['regression_detected']) {
                    foreach ($test['comparison']['metrics'] as $metric => $data) {
                        if ($data['regression']) {
                            $report .= sprintf(
                                "    - %s: %.2f → %.2f (%.1f%% worse)\n",
                                $metric,
                                $data['baseline'],
                                $data['current'],
                                abs($data['change_percent'])
                            );
                        }
                    }
                }
            }
            
            $report .= "\n";
        }
        
        return $report;
    }
}

/**
 * Baseline storage interface
 */
interface BaselineStorage {
    public function getBaseline(string $testName): ?array;
    public function saveBaseline(string $testName, array $data): void;
    public function deleteBaseline(string $testName): void;
    public function getAllBaselines(): array;
}

/**
 * File-based baseline storage
 */
class FileBaselineStorage implements BaselineStorage {
    private string $directory;
    
    public function __construct(array $config) {
        $this->directory = $config['baseline_directory'] ?? '/tmp/performance_baselines';
        
        if (!is_dir($this->directory)) {
            mkdir($this->directory, 0755, true);
        }
    }
    
    public function getBaseline(string $testName): ?array {
        $filename = $this->getFilename($testName);
        
        if (!file_exists($filename)) {
            return null;
        }
        
        $data = file_get_contents($filename);
        return json_decode($data, true);
    }
    
    public function saveBaseline(string $testName, array $data): void {
        $filename = $this->getFilename($testName);
        file_put_contents($filename, json_encode($data, JSON_PRETTY_PRINT));
    }
    
    public function deleteBaseline(string $testName): void {
        $filename = $this->getFilename($testName);
        if (file_exists($filename)) {
            unlink($filename);
        }
    }
    
    public function getAllBaselines(): array {
        $baselines = [];
        $files = glob($this->directory . '/*.json');
        
        foreach ($files as $file) {
            $testName = basename($file, '.json');
            $baselines[$testName] = $this->getBaseline($testName);
        }
        
        return $baselines;
    }
    
    private function getFilename(string $testName): string {
        $safeName = preg_replace('/[^a-zA-Z0-9_-]/', '_', $testName);
        return $this->directory . '/' . $safeName . '.json';
    }
}

/**
 * Metrics collector for historical data
 */
class MetricsCollector {
    private array $storage = [];
    
    public function record(string $testName, array $statistics): void {
        if (!isset($this->storage[$testName])) {
            $this->storage[$testName] = [];
        }
        
        $this->storage[$testName][] = [
            'timestamp' => time(),
            'statistics' => $statistics
        ];
        
        // Limit storage
        if (count($this->storage[$testName]) > 1000) {
            array_shift($this->storage[$testName]);
        }
    }
    
    public function getHistory(string $testName, int $since = null): array {
        if (!isset($this->storage[$testName])) {
            return [];
        }
        
        if ($since === null) {
            return $this->storage[$testName];
        }
        
        return array_filter($this->storage[$testName], function ($record) use ($since) {
            return $record['timestamp'] >= $since;
        });
    }
}
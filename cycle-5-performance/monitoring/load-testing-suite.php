<?php
/**
 * Automated Load Testing Suite
 * 
 * Comprehensive load testing framework for performance validation,
 * stress testing, and capacity planning.
 */

namespace MoneyQuiz\Performance\Monitoring;

use Exception;
use GuzzleHttp\Client;
use GuzzleHttp\Promise;
use GuzzleHttp\Pool;
use GuzzleHttp\Psr7\Request;

class LoadTestingSuite {
    private Client $httpClient;
    private array $config;
    private array $results = [];
    private array $metrics = [];
    private $progressCallback;
    private array $scenarios = [];
    
    public function __construct(array $config = []) {
        $this->config = array_merge([
            'base_url' => 'http://localhost',
            'timeout' => 30,
            'concurrent_users' => 10,
            'duration' => 60, // seconds
            'ramp_up_time' => 10, // seconds
            'think_time' => 1, // seconds between requests
            'user_agent' => 'LoadTestingSuite/1.0',
            'collect_body' => false,
            'follow_redirects' => true,
            'verify_ssl' => false
        ], $config);
        
        $this->httpClient = new Client([
            'base_uri' => $this->config['base_url'],
            'timeout' => $this->config['timeout'],
            'verify' => $this->config['verify_ssl'],
            'allow_redirects' => $this->config['follow_redirects'],
            'headers' => [
                'User-Agent' => $this->config['user_agent']
            ]
        ]);
    }
    
    /**
     * Define a load test scenario
     */
    public function scenario(string $name, callable $definition): void {
        $scenario = new LoadScenario($name);
        $definition($scenario);
        $this->scenarios[$name] = $scenario;
    }
    
    /**
     * Run a specific scenario
     */
    public function runScenario(string $name, array $options = []): TestResults {
        if (!isset($this->scenarios[$name])) {
            throw new Exception("Scenario '{$name}' not found");
        }
        
        $scenario = $this->scenarios[$name];
        $options = array_merge($this->config, $options);
        
        $this->results = [];
        $this->metrics = [
            'total_requests' => 0,
            'successful_requests' => 0,
            'failed_requests' => 0,
            'total_bytes' => 0,
            'start_time' => microtime(true),
            'end_time' => null
        ];
        
        // Execute scenario
        $this->executeScenario($scenario, $options);
        
        $this->metrics['end_time'] = microtime(true);
        
        return new TestResults($this->results, $this->metrics);
    }
    
    /**
     * Execute a load test scenario
     */
    private function executeScenario(LoadScenario $scenario, array $options): void {
        $startTime = microtime(true);
        $endTime = $startTime + $options['duration'];
        $rampUpInterval = $options['ramp_up_time'] / $options['concurrent_users'];
        
        $promises = [];
        $activeUsers = 0;
        
        while (microtime(true) < $endTime) {
            // Ramp up users
            if ($activeUsers < $options['concurrent_users']) {
                $usersToAdd = min(
                    ceil((microtime(true) - $startTime) / $rampUpInterval) - $activeUsers,
                    $options['concurrent_users'] - $activeUsers
                );
                
                for ($i = 0; $i < $usersToAdd; $i++) {
                    $promises[] = $this->simulateUser($scenario, $endTime, $options);
                    $activeUsers++;
                }
            }
            
            // Process completed requests
            Promise\settle($promises)->wait(false);
            
            // Small sleep to prevent CPU spinning
            usleep(10000); // 10ms
        }
        
        // Wait for remaining requests
        Promise\settle($promises)->wait();
    }
    
    /**
     * Simulate a single user
     */
    private function simulateUser(LoadScenario $scenario, float $endTime, array $options): Promise\PromiseInterface {
        return Promise\coroutine(function () use ($scenario, $endTime, $options) {
            $userId = uniqid('user_', true);
            $context = ['user_id' => $userId];
            
            while (microtime(true) < $endTime) {
                foreach ($scenario->getSteps() as $step) {
                    if (microtime(true) >= $endTime) {
                        break;
                    }
                    
                    yield $this->executeStep($step, $context);
                    
                    // Think time
                    if ($options['think_time'] > 0) {
                        yield Promise\promise(function ($resolve) use ($options) {
                            $this->sleep($options['think_time']);
                            $resolve(null);
                        });
                    }
                }
            }
        });
    }
    
    /**
     * Execute a single step
     */
    private function executeStep(array $step, array &$context): Promise\PromiseInterface {
        $startTime = microtime(true);
        
        // Build request
        $request = $this->buildRequest($step, $context);
        
        return $this->httpClient->sendAsync($request)->then(
            function ($response) use ($step, $startTime, &$context) {
                $endTime = microtime(true);
                
                $result = [
                    'step' => $step['name'],
                    'method' => $step['method'],
                    'url' => $step['url'],
                    'status_code' => $response->getStatusCode(),
                    'duration' => ($endTime - $startTime) * 1000, // ms
                    'bytes' => strlen($response->getBody()),
                    'timestamp' => $startTime,
                    'success' => true
                ];
                
                // Extract data if needed
                if (isset($step['extract'])) {
                    $this->extractData($response, $step['extract'], $context);
                }
                
                // Validate response
                if (isset($step['validate'])) {
                    $result['validation'] = $this->validateResponse($response, $step['validate']);
                    $result['success'] = $result['validation']['passed'];
                }
                
                $this->recordResult($result);
                
                return $response;
            },
            function ($error) use ($step, $startTime) {
                $endTime = microtime(true);
                
                $result = [
                    'step' => $step['name'],
                    'method' => $step['method'],
                    'url' => $step['url'],
                    'status_code' => 0,
                    'duration' => ($endTime - $startTime) * 1000,
                    'bytes' => 0,
                    'timestamp' => $startTime,
                    'success' => false,
                    'error' => $error->getMessage()
                ];
                
                $this->recordResult($result);
                
                return null;
            }
        );
    }
    
    /**
     * Build HTTP request
     */
    private function buildRequest(array $step, array $context): Request {
        $method = $step['method'];
        $url = $this->interpolateVariables($step['url'], $context);
        $headers = $step['headers'] ?? [];
        $body = null;
        
        if (isset($step['body'])) {
            if (is_array($step['body'])) {
                $body = json_encode($this->interpolateVariables($step['body'], $context));
                $headers['Content-Type'] = 'application/json';
            } else {
                $body = $this->interpolateVariables($step['body'], $context);
            }
        }
        
        return new Request($method, $url, $headers, $body);
    }
    
    /**
     * Interpolate variables in strings
     */
    private function interpolateVariables($value, array $context) {
        if (is_string($value)) {
            return preg_replace_callback('/\{\{(\w+)\}\}/', function ($matches) use ($context) {
                return $context[$matches[1]] ?? $matches[0];
            }, $value);
        }
        
        if (is_array($value)) {
            foreach ($value as $key => $item) {
                $value[$key] = $this->interpolateVariables($item, $context);
            }
        }
        
        return $value;
    }
    
    /**
     * Extract data from response
     */
    private function extractData($response, array $extractors, array &$context): void {
        $body = (string)$response->getBody();
        
        foreach ($extractors as $name => $extractor) {
            if ($extractor['type'] === 'json') {
                $json = json_decode($body, true);
                $value = $this->getNestedValue($json, $extractor['path']);
                $context[$name] = $value;
            } elseif ($extractor['type'] === 'header') {
                $context[$name] = $response->getHeaderLine($extractor['name']);
            } elseif ($extractor['type'] === 'regex') {
                if (preg_match($extractor['pattern'], $body, $matches)) {
                    $context[$name] = $matches[$extractor['group'] ?? 1];
                }
            }
        }
    }
    
    /**
     * Validate response
     */
    private function validateResponse($response, array $validators): array {
        $results = [];
        $passed = true;
        
        foreach ($validators as $name => $validator) {
            $result = ['name' => $name, 'passed' => false];
            
            switch ($validator['type']) {
                case 'status':
                    $result['passed'] = $response->getStatusCode() === $validator['value'];
                    $result['actual'] = $response->getStatusCode();
                    $result['expected'] = $validator['value'];
                    break;
                    
                case 'header':
                    $actual = $response->getHeaderLine($validator['name']);
                    $result['passed'] = $this->matchValue($actual, $validator['value']);
                    $result['actual'] = $actual;
                    $result['expected'] = $validator['value'];
                    break;
                    
                case 'json':
                    $json = json_decode((string)$response->getBody(), true);
                    $actual = $this->getNestedValue($json, $validator['path']);
                    $result['passed'] = $this->matchValue($actual, $validator['value']);
                    $result['actual'] = $actual;
                    $result['expected'] = $validator['value'];
                    break;
                    
                case 'response_time':
                    // This would need to be passed in
                    break;
            }
            
            $results[] = $result;
            $passed = $passed && $result['passed'];
        }
        
        return ['passed' => $passed, 'details' => $results];
    }
    
    /**
     * Match value with operator support
     */
    private function matchValue($actual, $expected): bool {
        if (is_array($expected) && isset($expected['operator'])) {
            switch ($expected['operator']) {
                case 'equals':
                    return $actual == $expected['value'];
                case 'contains':
                    return strpos($actual, $expected['value']) !== false;
                case 'greater_than':
                    return $actual > $expected['value'];
                case 'less_than':
                    return $actual < $expected['value'];
                case 'regex':
                    return preg_match($expected['value'], $actual);
            }
        }
        
        return $actual == $expected;
    }
    
    /**
     * Get nested value from array
     */
    private function getNestedValue(array $data, string $path) {
        $parts = explode('.', $path);
        $value = $data;
        
        foreach ($parts as $part) {
            if (!isset($value[$part])) {
                return null;
            }
            $value = $value[$part];
        }
        
        return $value;
    }
    
    /**
     * Record test result
     */
    private function recordResult(array $result): void {
        $this->results[] = $result;
        
        $this->metrics['total_requests']++;
        
        if ($result['success']) {
            $this->metrics['successful_requests']++;
        } else {
            $this->metrics['failed_requests']++;
        }
        
        $this->metrics['total_bytes'] += $result['bytes'];
        
        if ($this->progressCallback) {
            call_user_func($this->progressCallback, $result);
        }
    }
    
    /**
     * Set progress callback
     */
    public function onProgress(callable $callback): void {
        $this->progressCallback = $callback;
    }
    
    /**
     * Sleep for specified seconds
     */
    private function sleep(float $seconds): void {
        usleep((int)($seconds * 1000000));
    }
    
    /**
     * Run stress test
     */
    public function stressTest(string $url, array $options = []): TestResults {
        $options = array_merge([
            'method' => 'GET',
            'concurrent_users' => 100,
            'requests_per_user' => 10,
            'ramp_up_time' => 0
        ], $options);
        
        $scenario = new LoadScenario('stress_test');
        $scenario->step('request', [
            'method' => $options['method'],
            'url' => $url
        ]);
        
        for ($i = 0; $i < $options['requests_per_user']; $i++) {
            $scenario->step("request_{$i}", [
                'method' => $options['method'],
                'url' => $url
            ]);
        }
        
        return $this->runScenario('stress_test', $options);
    }
    
    /**
     * Run spike test
     */
    public function spikeTest(string $url, array $options = []): TestResults {
        $options = array_merge([
            'normal_users' => 10,
            'spike_users' => 100,
            'spike_duration' => 30,
            'total_duration' => 120
        ], $options);
        
        // Implementation would gradually increase users to spike level
        // then maintain for spike_duration, then return to normal
        
        return $this->stressTest($url, $options);
    }
    
    /**
     * Run soak test (endurance test)
     */
    public function soakTest(string $url, array $options = []): TestResults {
        $options = array_merge([
            'concurrent_users' => 50,
            'duration' => 3600, // 1 hour
            'ramp_up_time' => 300 // 5 minutes
        ], $options);
        
        return $this->stressTest($url, $options);
    }
}

/**
 * Load test scenario builder
 */
class LoadScenario {
    private string $name;
    private array $steps = [];
    private array $setup = [];
    private array $teardown = [];
    
    public function __construct(string $name) {
        $this->name = $name;
    }
    
    /**
     * Add a step to the scenario
     */
    public function step(string $name, array $config): self {
        $this->steps[] = array_merge(['name' => $name], $config);
        return $this;
    }
    
    /**
     * Add GET request step
     */
    public function get(string $url, array $options = []): self {
        return $this->step($options['name'] ?? 'GET ' . $url, array_merge([
            'method' => 'GET',
            'url' => $url
        ], $options));
    }
    
    /**
     * Add POST request step
     */
    public function post(string $url, $body = null, array $options = []): self {
        return $this->step($options['name'] ?? 'POST ' . $url, array_merge([
            'method' => 'POST',
            'url' => $url,
            'body' => $body
        ], $options));
    }
    
    /**
     * Add validation to last step
     */
    public function validate(array $validators): self {
        if (!empty($this->steps)) {
            $lastIndex = count($this->steps) - 1;
            $this->steps[$lastIndex]['validate'] = $validators;
        }
        return $this;
    }
    
    /**
     * Extract data from last step response
     */
    public function extract(array $extractors): self {
        if (!empty($this->steps)) {
            $lastIndex = count($this->steps) - 1;
            $this->steps[$lastIndex]['extract'] = $extractors;
        }
        return $this;
    }
    
    /**
     * Get scenario steps
     */
    public function getSteps(): array {
        return $this->steps;
    }
}

/**
 * Test results analyzer
 */
class TestResults {
    private array $results;
    private array $metrics;
    
    public function __construct(array $results, array $metrics) {
        $this->results = $results;
        $this->metrics = $metrics;
    }
    
    /**
     * Get summary statistics
     */
    public function getSummary(): array {
        $duration = $this->metrics['end_time'] - $this->metrics['start_time'];
        
        return [
            'total_requests' => $this->metrics['total_requests'],
            'successful_requests' => $this->metrics['successful_requests'],
            'failed_requests' => $this->metrics['failed_requests'],
            'success_rate' => $this->metrics['total_requests'] > 0 
                ? ($this->metrics['successful_requests'] / $this->metrics['total_requests']) * 100 
                : 0,
            'duration' => $duration,
            'requests_per_second' => $duration > 0 
                ? $this->metrics['total_requests'] / $duration 
                : 0,
            'bytes_per_second' => $duration > 0 
                ? $this->metrics['total_bytes'] / $duration 
                : 0,
            'average_response_time' => $this->getAverageResponseTime(),
            'min_response_time' => $this->getMinResponseTime(),
            'max_response_time' => $this->getMaxResponseTime(),
            'percentiles' => $this->getPercentiles()
        ];
    }
    
    /**
     * Get response time percentiles
     */
    public function getPercentiles(): array {
        $responseTimes = array_column($this->results, 'duration');
        sort($responseTimes);
        
        $count = count($responseTimes);
        if ($count === 0) {
            return [];
        }
        
        return [
            'p50' => $this->percentile($responseTimes, 50),
            'p75' => $this->percentile($responseTimes, 75),
            'p90' => $this->percentile($responseTimes, 90),
            'p95' => $this->percentile($responseTimes, 95),
            'p99' => $this->percentile($responseTimes, 99)
        ];
    }
    
    /**
     * Get errors summary
     */
    public function getErrors(): array {
        $errors = [];
        
        foreach ($this->results as $result) {
            if (!$result['success']) {
                $key = $result['error'] ?? 'Unknown error';
                if (!isset($errors[$key])) {
                    $errors[$key] = 0;
                }
                $errors[$key]++;
            }
        }
        
        return $errors;
    }
    
    /**
     * Get results by step
     */
    public function getResultsByStep(): array {
        $byStep = [];
        
        foreach ($this->results as $result) {
            $step = $result['step'];
            
            if (!isset($byStep[$step])) {
                $byStep[$step] = [
                    'count' => 0,
                    'success' => 0,
                    'failed' => 0,
                    'total_duration' => 0,
                    'min_duration' => PHP_FLOAT_MAX,
                    'max_duration' => 0
                ];
            }
            
            $byStep[$step]['count']++;
            
            if ($result['success']) {
                $byStep[$step]['success']++;
            } else {
                $byStep[$step]['failed']++;
            }
            
            $byStep[$step]['total_duration'] += $result['duration'];
            $byStep[$step]['min_duration'] = min($byStep[$step]['min_duration'], $result['duration']);
            $byStep[$step]['max_duration'] = max($byStep[$step]['max_duration'], $result['duration']);
        }
        
        // Calculate averages
        foreach ($byStep as &$stats) {
            $stats['avg_duration'] = $stats['count'] > 0 
                ? $stats['total_duration'] / $stats['count'] 
                : 0;
            $stats['success_rate'] = $stats['count'] > 0 
                ? ($stats['success'] / $stats['count']) * 100 
                : 0;
        }
        
        return $byStep;
    }
    
    /**
     * Generate HTML report
     */
    public function generateReport(string $filename = null): string {
        $summary = $this->getSummary();
        $stepResults = $this->getResultsByStep();
        $errors = $this->getErrors();
        
        $html = $this->renderHTMLReport($summary, $stepResults, $errors);
        
        if ($filename) {
            file_put_contents($filename, $html);
        }
        
        return $html;
    }
    
    private function getAverageResponseTime(): float {
        $times = array_column($this->results, 'duration');
        return count($times) > 0 ? array_sum($times) / count($times) : 0;
    }
    
    private function getMinResponseTime(): float {
        $times = array_column($this->results, 'duration');
        return count($times) > 0 ? min($times) : 0;
    }
    
    private function getMaxResponseTime(): float {
        $times = array_column($this->results, 'duration');
        return count($times) > 0 ? max($times) : 0;
    }
    
    private function percentile(array $values, int $percentile): float {
        $index = ceil(($percentile / 100) * count($values)) - 1;
        return $values[$index] ?? 0;
    }
    
    private function renderHTMLReport(array $summary, array $stepResults, array $errors): string {
        // Simple HTML report template
        return <<<HTML
<!DOCTYPE html>
<html>
<head>
    <title>Load Test Report</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; }
        h1, h2 { color: #333; }
        table { border-collapse: collapse; width: 100%; margin: 20px 0; }
        th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
        th { background-color: #f2f2f2; }
        .success { color: green; }
        .error { color: red; }
    </style>
</head>
<body>
    <h1>Load Test Report</h1>
    
    <h2>Summary</h2>
    <table>
        <tr><th>Metric</th><th>Value</th></tr>
        <tr><td>Total Requests</td><td>{$summary['total_requests']}</td></tr>
        <tr><td>Successful Requests</td><td class="success">{$summary['successful_requests']}</td></tr>
        <tr><td>Failed Requests</td><td class="error">{$summary['failed_requests']}</td></tr>
        <tr><td>Success Rate</td><td>{$summary['success_rate']}%</td></tr>
        <tr><td>Test Duration</td><td>{$summary['duration']}s</td></tr>
        <tr><td>Requests/Second</td><td>{$summary['requests_per_second']}</td></tr>
        <tr><td>Average Response Time</td><td>{$summary['average_response_time']}ms</td></tr>
    </table>
    
    <h2>Response Time Percentiles</h2>
    <table>
        <tr><th>Percentile</th><th>Response Time (ms)</th></tr>
        <tr><td>50th (Median)</td><td>{$summary['percentiles']['p50']}</td></tr>
        <tr><td>75th</td><td>{$summary['percentiles']['p75']}</td></tr>
        <tr><td>90th</td><td>{$summary['percentiles']['p90']}</td></tr>
        <tr><td>95th</td><td>{$summary['percentiles']['p95']}</td></tr>
        <tr><td>99th</td><td>{$summary['percentiles']['p99']}</td></tr>
    </table>
</body>
</html>
HTML;
    }
}
<?php
/**
 * Real-time Performance Metrics Tracking System
 * 
 * Captures and tracks comprehensive performance metrics with real-time
 * analysis, alerting, and historical trend detection.
 */

namespace MoneyQuiz\Performance\Monitoring;

use Exception;

class PerformanceMetrics {
    private array $metrics = [];
    private array $timers = [];
    private array $counters = [];
    private array $gauges = [];
    private array $histograms = [];
    private array $config;
    private $storage;
    
    public function __construct(array $config = []) {
        $this->config = array_merge([
            'storage_backend' => 'redis', // redis, influxdb, prometheus
            'retention_period' => 86400, // 24 hours
            'aggregation_intervals' => [60, 300, 3600], // 1min, 5min, 1hour
            'percentiles' => [50, 75, 90, 95, 99],
            'enable_realtime' => true,
            'batch_size' => 1000,
            'flush_interval' => 10 // seconds
        ], $config);
        
        $this->initializeStorage();
        $this->startFlusher();
    }
    
    /**
     * Start timing an operation
     */
    public function startTimer(string $name, array $tags = []): string {
        $timerId = uniqid('timer_', true);
        
        $this->timers[$timerId] = [
            'name' => $name,
            'start' => microtime(true),
            'tags' => $tags
        ];
        
        return $timerId;
    }
    
    /**
     * Stop timing and record duration
     */
    public function stopTimer(string $timerId): float {
        if (!isset($this->timers[$timerId])) {
            throw new Exception("Timer {$timerId} not found");
        }
        
        $timer = $this->timers[$timerId];
        $duration = (microtime(true) - $timer['start']) * 1000; // Convert to milliseconds
        
        $this->recordTiming($timer['name'], $duration, $timer['tags']);
        
        unset($this->timers[$timerId]);
        
        return $duration;
    }
    
    /**
     * Time a callback execution
     */
    public function time(string $name, callable $callback, array $tags = []) {
        $timerId = $this->startTimer($name, $tags);
        
        try {
            $result = $callback();
            $this->stopTimer($timerId);
            return $result;
        } catch (Exception $e) {
            $this->stopTimer($timerId);
            $this->increment($name . '.errors', 1, array_merge($tags, ['error' => get_class($e)]));
            throw $e;
        }
    }
    
    /**
     * Record a timing metric
     */
    public function recordTiming(string $name, float $value, array $tags = []): void {
        $metric = [
            'name' => $name,
            'type' => 'timing',
            'value' => $value,
            'tags' => $tags,
            'timestamp' => microtime(true)
        ];
        
        $this->metrics[] = $metric;
        
        // Update histogram
        $this->updateHistogram($name, $value, $tags);
        
        // Real-time processing
        if ($this->config['enable_realtime']) {
            $this->processRealtimeMetric($metric);
        }
    }
    
    /**
     * Increment a counter
     */
    public function increment(string $name, int $value = 1, array $tags = []): void {
        $key = $this->getMetricKey($name, $tags);
        
        if (!isset($this->counters[$key])) {
            $this->counters[$key] = [
                'name' => $name,
                'value' => 0,
                'tags' => $tags
            ];
        }
        
        $this->counters[$key]['value'] += $value;
        
        $metric = [
            'name' => $name,
            'type' => 'counter',
            'value' => $value,
            'tags' => $tags,
            'timestamp' => microtime(true)
        ];
        
        $this->metrics[] = $metric;
        
        if ($this->config['enable_realtime']) {
            $this->processRealtimeMetric($metric);
        }
    }
    
    /**
     * Set a gauge value
     */
    public function gauge(string $name, float $value, array $tags = []): void {
        $key = $this->getMetricKey($name, $tags);
        
        $this->gauges[$key] = [
            'name' => $name,
            'value' => $value,
            'tags' => $tags,
            'timestamp' => microtime(true)
        ];
        
        $metric = [
            'name' => $name,
            'type' => 'gauge',
            'value' => $value,
            'tags' => $tags,
            'timestamp' => microtime(true)
        ];
        
        $this->metrics[] = $metric;
        
        if ($this->config['enable_realtime']) {
            $this->processRealtimeMetric($metric);
        }
    }
    
    /**
     * Update histogram data
     */
    private function updateHistogram(string $name, float $value, array $tags): void {
        $key = $this->getMetricKey($name, $tags);
        
        if (!isset($this->histograms[$key])) {
            $this->histograms[$key] = [
                'name' => $name,
                'tags' => $tags,
                'values' => [],
                'count' => 0,
                'sum' => 0,
                'min' => PHP_FLOAT_MAX,
                'max' => PHP_FLOAT_MIN
            ];
        }
        
        $histogram = &$this->histograms[$key];
        $histogram['values'][] = $value;
        $histogram['count']++;
        $histogram['sum'] += $value;
        $histogram['min'] = min($histogram['min'], $value);
        $histogram['max'] = max($histogram['max'], $value);
        
        // Limit stored values for memory efficiency
        if (count($histogram['values']) > 10000) {
            $histogram['values'] = array_slice($histogram['values'], -5000);
        }
    }
    
    /**
     * Calculate percentiles for histogram
     */
    public function getPercentiles(string $name, array $tags = []): array {
        $key = $this->getMetricKey($name, $tags);
        
        if (!isset($this->histograms[$key])) {
            return [];
        }
        
        $histogram = $this->histograms[$key];
        $values = $histogram['values'];
        sort($values);
        
        $result = [
            'count' => $histogram['count'],
            'sum' => $histogram['sum'],
            'min' => $histogram['min'],
            'max' => $histogram['max'],
            'mean' => $histogram['count'] > 0 ? $histogram['sum'] / $histogram['count'] : 0
        ];
        
        foreach ($this->config['percentiles'] as $percentile) {
            $index = (int)ceil(($percentile / 100) * count($values)) - 1;
            $result["p{$percentile}"] = $values[$index] ?? 0;
        }
        
        return $result;
    }
    
    /**
     * Get metric key for grouping
     */
    private function getMetricKey(string $name, array $tags): string {
        ksort($tags);
        return $name . ':' . http_build_query($tags);
    }
    
    /**
     * Process real-time metric
     */
    private function processRealtimeMetric(array $metric): void {
        // Check for anomalies
        $this->detectAnomalies($metric);
        
        // Update aggregations
        foreach ($this->config['aggregation_intervals'] as $interval) {
            $this->updateAggregation($metric, $interval);
        }
        
        // Check thresholds
        $this->checkThresholds($metric);
    }
    
    /**
     * Detect anomalies in metrics
     */
    private function detectAnomalies(array $metric): void {
        if ($metric['type'] !== 'timing') {
            return;
        }
        
        $key = $this->getMetricKey($metric['name'], $metric['tags']);
        
        if (isset($this->histograms[$key]) && $this->histograms[$key]['count'] > 100) {
            $stats = $this->getPercentiles($metric['name'], $metric['tags']);
            
            // Simple anomaly detection based on percentiles
            if ($metric['value'] > $stats['p99'] * 2) {
                $this->recordAnomaly([
                    'metric' => $metric['name'],
                    'value' => $metric['value'],
                    'expected_range' => [0, $stats['p99']],
                    'severity' => 'warning',
                    'tags' => $metric['tags']
                ]);
            }
        }
    }
    
    /**
     * Update aggregated metrics
     */
    private function updateAggregation(array $metric, int $interval): void {
        $bucket = floor($metric['timestamp'] / $interval) * $interval;
        $key = $this->getMetricKey($metric['name'], $metric['tags']) . ":{$interval}:{$bucket}";
        
        if (!isset($this->aggregations[$key])) {
            $this->aggregations[$key] = [
                'name' => $metric['name'],
                'tags' => $metric['tags'],
                'interval' => $interval,
                'bucket' => $bucket,
                'count' => 0,
                'sum' => 0,
                'min' => PHP_FLOAT_MAX,
                'max' => PHP_FLOAT_MIN
            ];
        }
        
        $agg = &$this->aggregations[$key];
        $agg['count']++;
        
        if (is_numeric($metric['value'])) {
            $agg['sum'] += $metric['value'];
            $agg['min'] = min($agg['min'], $metric['value']);
            $agg['max'] = max($agg['max'], $metric['value']);
        }
    }
    
    /**
     * Check metric thresholds
     */
    private function checkThresholds(array $metric): void {
        // Implementation depends on configured thresholds
        // This would check against predefined limits and trigger alerts
    }
    
    /**
     * Record an anomaly
     */
    private function recordAnomaly(array $anomaly): void {
        $anomaly['timestamp'] = microtime(true);
        $this->anomalies[] = $anomaly;
        
        // Trigger alert if configured
        if (isset($this->config['anomaly_callback'])) {
            call_user_func($this->config['anomaly_callback'], $anomaly);
        }
    }
    
    /**
     * Initialize storage backend
     */
    private function initializeStorage(): void {
        switch ($this->config['storage_backend']) {
            case 'redis':
                $this->storage = new RedisMetricStorage($this->config);
                break;
            case 'influxdb':
                $this->storage = new InfluxDBMetricStorage($this->config);
                break;
            case 'prometheus':
                $this->storage = new PrometheusMetricStorage($this->config);
                break;
            default:
                $this->storage = new InMemoryMetricStorage($this->config);
        }
    }
    
    /**
     * Start background flusher
     */
    private function startFlusher(): void {
        if (!$this->config['flush_interval']) {
            return;
        }
        
        // In production, this would use a proper job scheduler
        register_tick_function(function () {
            static $lastFlush = 0;
            
            if (time() - $lastFlush >= $this->config['flush_interval']) {
                $this->flush();
                $lastFlush = time();
            }
        });
    }
    
    /**
     * Flush metrics to storage
     */
    public function flush(): void {
        if (empty($this->metrics)) {
            return;
        }
        
        // Batch metrics
        $batches = array_chunk($this->metrics, $this->config['batch_size']);
        
        foreach ($batches as $batch) {
            $this->storage->store($batch);
        }
        
        // Clear flushed metrics
        $this->metrics = [];
        
        // Store aggregations
        if (!empty($this->aggregations)) {
            $this->storage->storeAggregations($this->aggregations);
            $this->aggregations = [];
        }
    }
    
    /**
     * Query metrics
     */
    public function query(string $name, array $tags = [], int $start = null, int $end = null): array {
        $start = $start ?: time() - 3600; // Default to last hour
        $end = $end ?: time();
        
        return $this->storage->query($name, $tags, $start, $end);
    }
    
    /**
     * Get current snapshot of all metrics
     */
    public function getSnapshot(): array {
        $snapshot = [
            'timestamp' => microtime(true),
            'counters' => $this->counters,
            'gauges' => $this->gauges,
            'histograms' => []
        ];
        
        foreach ($this->histograms as $key => $histogram) {
            $snapshot['histograms'][$key] = $this->getPercentiles(
                $histogram['name'],
                $histogram['tags']
            );
        }
        
        return $snapshot;
    }
    
    /**
     * Export metrics in various formats
     */
    public function export(string $format = 'json'): string {
        $snapshot = $this->getSnapshot();
        
        switch ($format) {
            case 'prometheus':
                return $this->exportPrometheus($snapshot);
            case 'graphite':
                return $this->exportGraphite($snapshot);
            case 'json':
            default:
                return json_encode($snapshot, JSON_PRETTY_PRINT);
        }
    }
    
    /**
     * Export in Prometheus format
     */
    private function exportPrometheus(array $snapshot): string {
        $output = [];
        
        // Counters
        foreach ($snapshot['counters'] as $key => $counter) {
            $name = str_replace('.', '_', $counter['name']);
            $labels = $this->formatPrometheusLabels($counter['tags']);
            $output[] = "# TYPE {$name} counter";
            $output[] = "{$name}{$labels} {$counter['value']}";
        }
        
        // Gauges
        foreach ($snapshot['gauges'] as $key => $gauge) {
            $name = str_replace('.', '_', $gauge['name']);
            $labels = $this->formatPrometheusLabels($gauge['tags']);
            $output[] = "# TYPE {$name} gauge";
            $output[] = "{$name}{$labels} {$gauge['value']}";
        }
        
        // Histograms
        foreach ($snapshot['histograms'] as $key => $histogram) {
            $name = str_replace('.', '_', $histogram['name']);
            $labels = $this->formatPrometheusLabels($histogram['tags'] ?? []);
            
            $output[] = "# TYPE {$name} summary";
            $output[] = "{$name}_count{$labels} {$histogram['count']}";
            $output[] = "{$name}_sum{$labels} {$histogram['sum']}";
            
            foreach ($this->config['percentiles'] as $percentile) {
                $quantile = $percentile / 100;
                $value = $histogram["p{$percentile}"];
                $output[] = "{$name}{$labels}{{quantile=\"{$quantile}\"}} {$value}";
            }
        }
        
        return implode("\n", $output);
    }
    
    /**
     * Format Prometheus labels
     */
    private function formatPrometheusLabels(array $tags): string {
        if (empty($tags)) {
            return '';
        }
        
        $labels = [];
        foreach ($tags as $key => $value) {
            $labels[] = "{$key}=\"{$value}\"";
        }
        
        return '{' . implode(',', $labels) . '}';
    }
    
    /**
     * Export in Graphite format
     */
    private function exportGraphite(array $snapshot): string {
        $output = [];
        $timestamp = (int)$snapshot['timestamp'];
        
        // Counters
        foreach ($snapshot['counters'] as $counter) {
            $path = $this->formatGraphitePath($counter['name'], $counter['tags']);
            $output[] = "{$path} {$counter['value']} {$timestamp}";
        }
        
        // Gauges
        foreach ($snapshot['gauges'] as $gauge) {
            $path = $this->formatGraphitePath($gauge['name'], $gauge['tags']);
            $output[] = "{$path} {$gauge['value']} {$timestamp}";
        }
        
        // Histograms
        foreach ($snapshot['histograms'] as $histogram) {
            $path = $this->formatGraphitePath($histogram['name'], $histogram['tags'] ?? []);
            
            $output[] = "{$path}.count {$histogram['count']} {$timestamp}";
            $output[] = "{$path}.mean {$histogram['mean']} {$timestamp}";
            $output[] = "{$path}.min {$histogram['min']} {$timestamp}";
            $output[] = "{$path}.max {$histogram['max']} {$timestamp}";
            
            foreach ($this->config['percentiles'] as $percentile) {
                $value = $histogram["p{$percentile}"];
                $output[] = "{$path}.p{$percentile} {$value} {$timestamp}";
            }
        }
        
        return implode("\n", $output);
    }
    
    /**
     * Format Graphite metric path
     */
    private function formatGraphitePath(string $name, array $tags): string {
        $parts = [$name];
        
        foreach ($tags as $key => $value) {
            $parts[] = "{$key}.{$value}";
        }
        
        return implode('.', $parts);
    }
}

/**
 * Base metric storage interface
 */
interface MetricStorageInterface {
    public function store(array $metrics): void;
    public function storeAggregations(array $aggregations): void;
    public function query(string $name, array $tags, int $start, int $end): array;
}

/**
 * Redis metric storage
 */
class RedisMetricStorage implements MetricStorageInterface {
    private $redis;
    private array $config;
    
    public function __construct(array $config) {
        $this->config = $config;
        $this->redis = new \Redis();
        $this->redis->connect($config['redis_host'] ?? '127.0.0.1', $config['redis_port'] ?? 6379);
    }
    
    public function store(array $metrics): void {
        $pipeline = $this->redis->multi(\Redis::PIPELINE);
        
        foreach ($metrics as $metric) {
            $key = $this->getMetricKey($metric);
            $value = json_encode($metric);
            
            // Store in sorted set by timestamp
            $pipeline->zadd($key, $metric['timestamp'], $value);
            
            // Set expiration
            $pipeline->expire($key, $this->config['retention_period']);
        }
        
        $pipeline->exec();
    }
    
    public function storeAggregations(array $aggregations): void {
        $pipeline = $this->redis->multi(\Redis::PIPELINE);
        
        foreach ($aggregations as $key => $agg) {
            $redisKey = "agg:{$key}";
            $pipeline->hMSet($redisKey, $agg);
            $pipeline->expire($redisKey, $this->config['retention_period']);
        }
        
        $pipeline->exec();
    }
    
    public function query(string $name, array $tags, int $start, int $end): array {
        $pattern = $this->getMetricKey(['name' => $name, 'tags' => $tags]);
        $keys = $this->redis->keys($pattern . '*');
        
        $results = [];
        foreach ($keys as $key) {
            $values = $this->redis->zRangeByScore($key, $start, $end);
            foreach ($values as $value) {
                $results[] = json_decode($value, true);
            }
        }
        
        return $results;
    }
    
    private function getMetricKey(array $metric): string {
        $tags = $metric['tags'] ?? [];
        ksort($tags);
        $tagString = http_build_query($tags);
        
        return "metrics:{$metric['name']}:{$tagString}";
    }
}

/**
 * In-memory metric storage (for testing)
 */
class InMemoryMetricStorage implements MetricStorageInterface {
    private array $metrics = [];
    private array $aggregations = [];
    
    public function store(array $metrics): void {
        $this->metrics = array_merge($this->metrics, $metrics);
    }
    
    public function storeAggregations(array $aggregations): void {
        $this->aggregations = array_merge($this->aggregations, $aggregations);
    }
    
    public function query(string $name, array $tags, int $start, int $end): array {
        return array_filter($this->metrics, function ($metric) use ($name, $tags, $start, $end) {
            return $metric['name'] === $name &&
                   $metric['timestamp'] >= $start &&
                   $metric['timestamp'] <= $end &&
                   $this->tagsMatch($metric['tags'] ?? [], $tags);
        });
    }
    
    private function tagsMatch(array $metricTags, array $queryTags): bool {
        foreach ($queryTags as $key => $value) {
            if (!isset($metricTags[$key]) || $metricTags[$key] !== $value) {
                return false;
            }
        }
        return true;
    }
}
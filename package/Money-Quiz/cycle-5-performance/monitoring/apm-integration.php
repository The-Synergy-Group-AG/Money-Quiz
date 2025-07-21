<?php
/**
 * Application Performance Monitoring (APM) Integration
 * 
 * Integrates with New Relic, Datadog, and other APM providers for
 * comprehensive performance monitoring and alerting.
 */

namespace MoneyQuiz\Performance\Monitoring;

use Exception;

interface APMProviderInterface {
    public function startTransaction(string $name, array $attributes = []): void;
    public function endTransaction(): void;
    public function recordMetric(string $name, float $value, array $tags = []): void;
    public function recordError(Exception $error, array $context = []): void;
    public function addCustomAttribute(string $key, $value): void;
    public function createSpan(string $name, callable $callback, array $attributes = []);
}

/**
 * New Relic APM Provider
 */
class NewRelicProvider implements APMProviderInterface {
    private bool $enabled;
    private array $config;
    private array $transactionStack = [];
    
    public function __construct(array $config = []) {
        $this->config = array_merge([
            'app_name' => 'MoneyQuiz',
            'license_key' => '',
            'distributed_tracing' => true,
            'transaction_tracer' => true,
            'error_collector' => true,
            'custom_insights_events' => true
        ], $config);
        
        $this->enabled = extension_loaded('newrelic');
        
        if ($this->enabled) {
            $this->initialize();
        }
    }
    
    private function initialize(): void {
        if ($this->config['app_name']) {
            newrelic_set_appname($this->config['app_name']);
        }
        
        if ($this->config['distributed_tracing']) {
            newrelic_create_distributed_trace_payload();
        }
    }
    
    public function startTransaction(string $name, array $attributes = []): void {
        if (!$this->enabled) return;
        
        newrelic_start_transaction($this->config['app_name']);
        newrelic_name_transaction($name);
        
        foreach ($attributes as $key => $value) {
            newrelic_add_custom_parameter($key, $value);
        }
        
        $this->transactionStack[] = $name;
    }
    
    public function endTransaction(): void {
        if (!$this->enabled || empty($this->transactionStack)) return;
        
        array_pop($this->transactionStack);
        newrelic_end_transaction();
    }
    
    public function recordMetric(string $name, float $value, array $tags = []): void {
        if (!$this->enabled) return;
        
        newrelic_custom_metric("Custom/{$name}", $value);
        
        if ($this->config['custom_insights_events'] && !empty($tags)) {
            newrelic_record_custom_event($name, array_merge(['value' => $value], $tags));
        }
    }
    
    public function recordError(Exception $error, array $context = []): void {
        if (!$this->enabled) return;
        
        newrelic_notice_error($error->getMessage(), $error);
        
        foreach ($context as $key => $value) {
            newrelic_add_custom_parameter("error_{$key}", $value);
        }
    }
    
    public function addCustomAttribute(string $key, $value): void {
        if (!$this->enabled) return;
        
        newrelic_add_custom_parameter($key, $value);
    }
    
    public function createSpan(string $name, callable $callback, array $attributes = []) {
        if (!$this->enabled) {
            return $callback();
        }
        
        $segmentName = "Custom/{$name}";
        
        return newrelic_segment($segmentName, function () use ($callback, $attributes) {
            foreach ($attributes as $key => $value) {
                newrelic_add_custom_parameter($key, $value);
            }
            
            return $callback();
        });
    }
    
    /**
     * Record database query
     */
    public function recordDatabaseQuery(string $query, float $duration, array $params = []): void {
        if (!$this->enabled) return;
        
        newrelic_record_datastore_segment(function () {}, [
            'product' => 'MySQL',
            'collection' => $this->extractTableName($query),
            'operation' => $this->extractOperation($query),
            'query' => $query,
            'duration' => $duration
        ]);
    }
    
    /**
     * Mark transaction as background job
     */
    public function markAsBackgroundJob(): void {
        if (!$this->enabled) return;
        
        newrelic_background_job(true);
    }
    
    private function extractTableName(string $query): string {
        if (preg_match('/(?:FROM|INTO|UPDATE)\s+`?(\w+)`?/i', $query, $matches)) {
            return $matches[1];
        }
        return 'unknown';
    }
    
    private function extractOperation(string $query): string {
        $operation = strtoupper(substr(trim($query), 0, 6));
        return in_array($operation, ['SELECT', 'INSERT', 'UPDATE', 'DELETE']) ? $operation : 'OTHER';
    }
}

/**
 * Datadog APM Provider
 */
class DatadogProvider implements APMProviderInterface {
    private bool $enabled;
    private array $config;
    private $tracer;
    private array $spanStack = [];
    
    public function __construct(array $config = []) {
        $this->config = array_merge([
            'service_name' => 'moneyquiz',
            'env' => 'production',
            'version' => '1.0.0',
            'tags' => [],
            'sampling_rate' => 1.0,
            'profiling_enabled' => true
        ], $config);
        
        $this->enabled = class_exists('\DDTrace\GlobalTracer');
        
        if ($this->enabled) {
            $this->initialize();
        }
    }
    
    private function initialize(): void {
        if ($this->enabled) {
            $this->tracer = \DDTrace\GlobalTracer::get();
            
            // Set global tags
            \DDTrace\add_global_tag('service.name', $this->config['service_name']);
            \DDTrace\add_global_tag('env', $this->config['env']);
            \DDTrace\add_global_tag('version', $this->config['version']);
            
            foreach ($this->config['tags'] as $key => $value) {
                \DDTrace\add_global_tag($key, $value);
            }
        }
    }
    
    public function startTransaction(string $name, array $attributes = []): void {
        if (!$this->enabled) return;
        
        $span = $this->tracer->startSpan($name);
        
        foreach ($attributes as $key => $value) {
            $span->setTag($key, $value);
        }
        
        $this->spanStack[] = $span;
    }
    
    public function endTransaction(): void {
        if (!$this->enabled || empty($this->spanStack)) return;
        
        $span = array_pop($this->spanStack);
        $span->finish();
    }
    
    public function recordMetric(string $name, float $value, array $tags = []): void {
        if (!$this->enabled) return;
        
        // Send custom metric to Datadog
        $metric = [
            'metric' => $name,
            'points' => [[time(), $value]],
            'type' => 'gauge',
            'tags' => $this->formatTags($tags)
        ];
        
        $this->sendMetric($metric);
    }
    
    public function recordError(Exception $error, array $context = []): void {
        if (!$this->enabled) return;
        
        if (!empty($this->spanStack)) {
            $span = end($this->spanStack);
            $span->setError($error);
            
            foreach ($context as $key => $value) {
                $span->setTag("error.context.{$key}", $value);
            }
        }
    }
    
    public function addCustomAttribute(string $key, $value): void {
        if (!$this->enabled || empty($this->spanStack)) return;
        
        $span = end($this->spanStack);
        $span->setTag($key, $value);
    }
    
    public function createSpan(string $name, callable $callback, array $attributes = []) {
        if (!$this->enabled) {
            return $callback();
        }
        
        $span = $this->tracer->startSpan($name);
        
        foreach ($attributes as $key => $value) {
            $span->setTag($key, $value);
        }
        
        try {
            $result = $callback();
            return $result;
        } catch (Exception $e) {
            $span->setError($e);
            throw $e;
        } finally {
            $span->finish();
        }
    }
    
    /**
     * Record HTTP request
     */
    public function recordHttpRequest(string $method, string $url, int $statusCode, float $duration): void {
        if (!$this->enabled) return;
        
        $span = $this->tracer->startSpan('http.request');
        $span->setTag('http.method', $method);
        $span->setTag('http.url', $url);
        $span->setTag('http.status_code', $statusCode);
        $span->setTag('duration', $duration);
        $span->finish();
    }
    
    /**
     * Enable profiling
     */
    public function enableProfiling(): void {
        if (!$this->enabled || !function_exists('\DDTrace\enable_profiling')) return;
        
        \DDTrace\enable_profiling();
    }
    
    private function formatTags(array $tags): array {
        $formatted = [];
        foreach ($tags as $key => $value) {
            $formatted[] = "{$key}:{$value}";
        }
        return $formatted;
    }
    
    private function sendMetric(array $metric): void {
        // In production, this would send to Datadog API
        // For now, we'll just log it
        error_log('Datadog metric: ' . json_encode($metric));
    }
}

/**
 * APM Manager - Manages multiple APM providers
 */
class APMManager {
    private array $providers = [];
    private array $config;
    private bool $enabled = true;
    private array $globalAttributes = [];
    
    public function __construct(array $config = []) {
        $this->config = array_merge([
            'providers' => ['newrelic', 'datadog'],
            'async_metrics' => true,
            'batch_size' => 100,
            'flush_interval' => 60
        ], $config);
        
        $this->initializeProviders();
    }
    
    private function initializeProviders(): void {
        foreach ($this->config['providers'] as $provider => $settings) {
            if (is_string($settings)) {
                $provider = $settings;
                $settings = [];
            }
            
            switch ($provider) {
                case 'newrelic':
                    $this->providers[] = new NewRelicProvider($settings);
                    break;
                case 'datadog':
                    $this->providers[] = new DatadogProvider($settings);
                    break;
                default:
                    if (class_exists($provider)) {
                        $this->providers[] = new $provider($settings);
                    }
            }
        }
    }
    
    /**
     * Start transaction across all providers
     */
    public function startTransaction(string $name, array $attributes = []): void {
        if (!$this->enabled) return;
        
        $attributes = array_merge($this->globalAttributes, $attributes);
        
        foreach ($this->providers as $provider) {
            $provider->startTransaction($name, $attributes);
        }
    }
    
    /**
     * End transaction across all providers
     */
    public function endTransaction(): void {
        if (!$this->enabled) return;
        
        foreach ($this->providers as $provider) {
            $provider->endTransaction();
        }
    }
    
    /**
     * Record metric across all providers
     */
    public function recordMetric(string $name, float $value, array $tags = []): void {
        if (!$this->enabled) return;
        
        foreach ($this->providers as $provider) {
            $provider->recordMetric($name, $value, $tags);
        }
    }
    
    /**
     * Record error across all providers
     */
    public function recordError(Exception $error, array $context = []): void {
        if (!$this->enabled) return;
        
        $context = array_merge($this->globalAttributes, $context);
        
        foreach ($this->providers as $provider) {
            $provider->recordError($error, $context);
        }
    }
    
    /**
     * Add custom attribute across all providers
     */
    public function addCustomAttribute(string $key, $value): void {
        if (!$this->enabled) return;
        
        $this->globalAttributes[$key] = $value;
        
        foreach ($this->providers as $provider) {
            $provider->addCustomAttribute($key, $value);
        }
    }
    
    /**
     * Create span across all providers
     */
    public function createSpan(string $name, callable $callback, array $attributes = []) {
        if (!$this->enabled) {
            return $callback();
        }
        
        $attributes = array_merge($this->globalAttributes, $attributes);
        
        // Use the first provider for the actual span execution
        if (!empty($this->providers)) {
            return $this->providers[0]->createSpan($name, function () use ($callback, $name, $attributes) {
                // Notify other providers
                for ($i = 1; $i < count($this->providers); $i++) {
                    $this->providers[$i]->startTransaction($name, $attributes);
                }
                
                try {
                    $result = $callback();
                    
                    // End transactions for other providers
                    for ($i = 1; $i < count($this->providers); $i++) {
                        $this->providers[$i]->endTransaction();
                    }
                    
                    return $result;
                } catch (Exception $e) {
                    // Record error for other providers
                    for ($i = 1; $i < count($this->providers); $i++) {
                        $this->providers[$i]->recordError($e);
                        $this->providers[$i]->endTransaction();
                    }
                    throw $e;
                }
            }, $attributes);
        }
        
        return $callback();
    }
    
    /**
     * Enable/disable APM
     */
    public function setEnabled(bool $enabled): void {
        $this->enabled = $enabled;
    }
    
    /**
     * Middleware for automatic transaction tracking
     */
    public function middleware(string $transactionName = null): callable {
        return function ($request, $handler) use ($transactionName) {
            $name = $transactionName ?: $this->getTransactionName($request);
            
            $this->startTransaction($name, [
                'http.method' => $request->getMethod(),
                'http.url' => $request->getUri()->getPath(),
                'http.host' => $request->getUri()->getHost()
            ]);
            
            try {
                $response = $handler($request);
                
                $this->addCustomAttribute('http.status_code', $response->getStatusCode());
                $this->addCustomAttribute('response.size', strlen($response->getBody()));
                
                return $response;
            } catch (Exception $e) {
                $this->recordError($e);
                throw $e;
            } finally {
                $this->endTransaction();
            }
        };
    }
    
    private function getTransactionName($request): string {
        $method = $request->getMethod();
        $path = $request->getUri()->getPath();
        
        // Normalize path parameters
        $path = preg_replace('/\/\d+/', '/{id}', $path);
        $path = preg_replace('/\/[a-f0-9]{8}-[a-f0-9]{4}-[a-f0-9]{4}-[a-f0-9]{4}-[a-f0-9]{12}/', '/{uuid}', $path);
        
        return "{$method} {$path}";
    }
}

/**
 * Performance monitoring decorators
 */
trait APMMonitoring {
    protected static ?APMManager $apm = null;
    
    /**
     * Monitor method execution
     */
    protected function monitor(string $name, callable $callback, array $attributes = []) {
        if (!self::$apm) {
            return $callback();
        }
        
        return self::$apm->createSpan($name, $callback, $attributes);
    }
    
    /**
     * Record a metric
     */
    protected function metric(string $name, float $value, array $tags = []): void {
        if (self::$apm) {
            self::$apm->recordMetric($name, $value, $tags);
        }
    }
    
    /**
     * Set APM instance
     */
    public static function setAPM(APMManager $apm): void {
        self::$apm = $apm;
    }
}

/**
 * Database query monitoring
 */
class APMDatabaseMonitor {
    private APMManager $apm;
    
    public function __construct(APMManager $apm) {
        $this->apm = $apm;
    }
    
    /**
     * Monitor query execution
     */
    public function monitorQuery(string $query, callable $executor, array $params = []) {
        $operation = $this->extractOperation($query);
        $table = $this->extractTable($query);
        
        return $this->apm->createSpan("db.{$operation}", function () use ($executor, $query, $params) {
            $startTime = microtime(true);
            
            try {
                $result = $executor();
                
                $duration = microtime(true) - $startTime;
                $this->apm->recordMetric('db.query.duration', $duration * 1000, [
                    'operation' => $this->extractOperation($query),
                    'table' => $this->extractTable($query)
                ]);
                
                return $result;
            } catch (Exception $e) {
                $this->apm->recordError($e, [
                    'query' => $query,
                    'params' => $params
                ]);
                throw $e;
            }
        }, [
            'db.statement' => $query,
            'db.operation' => $operation,
            'db.table' => $table
        ]);
    }
    
    private function extractOperation(string $query): string {
        if (preg_match('/^\s*(SELECT|INSERT|UPDATE|DELETE|CREATE|DROP|ALTER)/i', $query, $matches)) {
            return strtolower($matches[1]);
        }
        return 'other';
    }
    
    private function extractTable(string $query): string {
        if (preg_match('/(?:FROM|INTO|UPDATE|TABLE)\s+`?(\w+)`?/i', $query, $matches)) {
            return $matches[1];
        }
        return 'unknown';
    }
}
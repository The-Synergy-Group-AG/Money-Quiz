<?php
/**
 * Request Batching Implementation
 * Batches multiple API calls to reduce network overhead
 */

class RequestBatcher {
    private $config = [
        'batch_size' => 10,
        'batch_timeout' => 50, // milliseconds
        'max_batch_size' => 50,
        'retry_attempts' => 3,
        'retry_delay' => 1000, // milliseconds
        'compression' => true,
        'endpoints' => [
            'batch' => '/api/batch',
            'graphql' => '/api/graphql',
            'rest' => '/api/v1'
        ],
        'priority_levels' => [
            'critical' => 0,
            'high' => 1,
            'normal' => 2,
            'low' => 3
        ]
    ];
    
    private $performance_monitor;
    private $request_queue = [];
    private $batch_timers = [];
    private $response_cache = [];
    private $stats = [
        'total_requests' => 0,
        'batched_requests' => 0,
        'network_calls' => 0,
        'cache_hits' => 0
    ];
    
    public function __construct() {
        $this->performance_monitor = new PerformanceMonitor();
    }
    
    /**
     * Add request to batch queue
     */
    public function addRequest($request) {
        $defaults = [
            'id' => uniqid('req_'),
            'method' => 'GET',
            'endpoint' => '',
            'params' => [],
            'headers' => [],
            'priority' => 'normal',
            'callback' => null,
            'cache_ttl' => 300, // 5 minutes
            'retry' => true
        ];
        
        $request = array_merge($defaults, $request);
        
        // Check cache first
        $cacheKey = $this->generateCacheKey($request);
        if (isset($this->response_cache[$cacheKey]) && 
            $this->response_cache[$cacheKey]['expires'] > time()) {
            $this->stats['cache_hits']++;
            
            if ($request['callback']) {
                call_user_func($request['callback'], $this->response_cache[$cacheKey]['data']);
            }
            
            return $this->response_cache[$cacheKey]['data'];
        }
        
        // Add to queue
        $priority = $this->config['priority_levels'][$request['priority']] ?? 2;
        $this->request_queue[$priority][] = $request;
        $this->stats['total_requests']++;
        
        // Schedule batch processing
        $this->scheduleBatch();
        
        return $request['id'];
    }
    
    /**
     * Schedule batch processing
     */
    private function scheduleBatch() {
        $queueSize = array_sum(array_map('count', $this->request_queue));
        
        // Process immediately if batch size reached
        if ($queueSize >= $this->config['batch_size']) {
            $this->processBatch();
            return;
        }
        
        // Schedule delayed processing
        if (!isset($this->batch_timers['default'])) {
            $this->batch_timers['default'] = time() + ($this->config['batch_timeout'] / 1000);
        }
    }
    
    /**
     * Process batch requests
     */
    public function processBatch() {
        if (empty($this->request_queue)) {
            return;
        }
        
        $startTime = microtime(true);
        $batch = $this->prepareBatch();
        
        if (empty($batch)) {
            return;
        }
        
        // Send batch request
        $responses = $this->sendBatchRequest($batch);
        
        // Process responses
        $this->processResponses($batch, $responses);
        
        // Clear processed requests from queue
        $this->clearProcessedRequests($batch);
        
        // Record metrics
        $processingTime = microtime(true) - $startTime;
        $this->performance_monitor->recordMetric('batch_processing', [
            'batch_size' => count($batch),
            'processing_time' => $processingTime,
            'success_rate' => $this->calculateSuccessRate($responses)
        ]);
        
        $this->stats['batched_requests'] += count($batch);
        $this->stats['network_calls']++;
    }
    
    /**
     * Prepare batch from queue
     */
    private function prepareBatch() {
        $batch = [];
        $batchSize = 0;
        
        // Sort by priority
        ksort($this->request_queue);
        
        foreach ($this->request_queue as $priority => $requests) {
            foreach ($requests as $request) {
                if ($batchSize >= $this->config['max_batch_size']) {
                    break 2;
                }
                
                $batch[] = [
                    'id' => $request['id'],
                    'method' => $request['method'],
                    'endpoint' => $request['endpoint'],
                    'params' => $request['params'],
                    'headers' => $request['headers']
                ];
                
                $batchSize++;
            }
        }
        
        return $batch;
    }
    
    /**
     * Send batch request
     */
    private function sendBatchRequest($batch) {
        $payload = [
            'batch' => $batch,
            'timestamp' => time(),
            'client_id' => session_id()
        ];
        
        // Compress payload if enabled
        if ($this->config['compression']) {
            $payload = $this->compressPayload($payload);
        }
        
        // Simulate API call (in production, use actual HTTP client)
        $response = $this->makeHttpRequest(
            $this->config['endpoints']['batch'],
            'POST',
            $payload,
            ['Content-Type' => 'application/json']
        );
        
        return $response['responses'] ?? [];
    }
    
    /**
     * Process batch responses
     */
    private function processResponses($batch, $responses) {
        $responseMap = [];
        
        // Map responses by request ID
        foreach ($responses as $response) {
            if (isset($response['id'])) {
                $responseMap[$response['id']] = $response;
            }
        }
        
        // Process each request's response
        foreach ($batch as $request) {
            $requestId = $request['id'];
            $response = $responseMap[$requestId] ?? null;
            
            if ($response) {
                // Cache successful responses
                if ($response['status'] === 'success') {
                    $this->cacheResponse($request, $response['data']);
                }
                
                // Execute callback
                $originalRequest = $this->findOriginalRequest($requestId);
                if ($originalRequest && $originalRequest['callback']) {
                    call_user_func(
                        $originalRequest['callback'],
                        $response['data'] ?? null,
                        $response['error'] ?? null
                    );
                }
            } else {
                // Handle missing response
                $this->handleMissingResponse($request);
            }
        }
    }
    
    /**
     * Find original request by ID
     */
    private function findOriginalRequest($requestId) {
        foreach ($this->request_queue as $priority => $requests) {
            foreach ($requests as $request) {
                if ($request['id'] === $requestId) {
                    return $request;
                }
            }
        }
        return null;
    }
    
    /**
     * Cache response
     */
    private function cacheResponse($request, $data) {
        $cacheKey = $this->generateCacheKey($request);
        $this->response_cache[$cacheKey] = [
            'data' => $data,
            'expires' => time() + $request['cache_ttl'],
            'request' => $request
        ];
    }
    
    /**
     * Generate cache key
     */
    private function generateCacheKey($request) {
        return md5(json_encode([
            'method' => $request['method'],
            'endpoint' => $request['endpoint'],
            'params' => $request['params']
        ]));
    }
    
    /**
     * Clear processed requests
     */
    private function clearProcessedRequests($batch) {
        $processedIds = array_column($batch, 'id');
        
        foreach ($this->request_queue as $priority => &$requests) {
            $requests = array_filter($requests, function($request) use ($processedIds) {
                return !in_array($request['id'], $processedIds);
            });
        }
        
        // Clean up empty priority levels
        $this->request_queue = array_filter($this->request_queue);
    }
    
    /**
     * Handle missing response
     */
    private function handleMissingResponse($request) {
        $originalRequest = $this->findOriginalRequest($request['id']);
        
        if ($originalRequest && $originalRequest['retry']) {
            // Re-add to queue for retry
            $originalRequest['retry_count'] = ($originalRequest['retry_count'] ?? 0) + 1;
            
            if ($originalRequest['retry_count'] < $this->config['retry_attempts']) {
                $this->addRequest($originalRequest);
            } else {
                // Max retries reached
                if ($originalRequest['callback']) {
                    call_user_func(
                        $originalRequest['callback'],
                        null,
                        ['error' => 'Max retries exceeded']
                    );
                }
            }
        }
    }
    
    /**
     * Calculate success rate
     */
    private function calculateSuccessRate($responses) {
        if (empty($responses)) {
            return 0;
        }
        
        $successful = array_filter($responses, function($response) {
            return isset($response['status']) && $response['status'] === 'success';
        });
        
        return (count($successful) / count($responses)) * 100;
    }
    
    /**
     * Compress payload
     */
    private function compressPayload($payload) {
        $json = json_encode($payload);
        return [
            'compressed' => true,
            'algorithm' => 'gzip',
            'data' => base64_encode(gzencode($json, 9))
        ];
    }
    
    /**
     * Make HTTP request (simplified)
     */
    private function makeHttpRequest($url, $method, $data, $headers = []) {
        // In production, use proper HTTP client (Guzzle, cURL, etc.)
        // This is a simplified simulation
        
        $responses = [];
        foreach ($data['batch'] as $request) {
            $responses[] = [
                'id' => $request['id'],
                'status' => 'success',
                'data' => [
                    'result' => 'Sample response for ' . $request['endpoint'],
                    'timestamp' => time()
                ]
            ];
        }
        
        return ['responses' => $responses];
    }
    
    /**
     * Generate client-side batching script
     */
    public function generateClientScript() {
        return '
// Request Batching Client
class RequestBatcher {
    constructor(config = {}) {
        this.config = {
            batchSize: 10,
            batchTimeout: 50,
            maxBatchSize: 50,
            endpoint: "/api/batch",
            compression: true,
            ...config
        };
        
        this.queue = new Map();
        this.timers = new Map();
        this.cache = new Map();
        this.stats = {
            totalRequests: 0,
            batchedRequests: 0,
            networkCalls: 0,
            cacheHits: 0
        };
    }
    
    /**
     * Add request to batch
     */
    async request(options) {
        const request = {
            id: `req_${Date.now()}_${Math.random().toString(36).substr(2, 9)}`,
            method: "GET",
            priority: "normal",
            cacheTTL: 300000, // 5 minutes
            ...options
        };
        
        // Check cache
        const cacheKey = this.getCacheKey(request);
        const cached = this.cache.get(cacheKey);
        
        if (cached && cached.expires > Date.now()) {
            this.stats.cacheHits++;
            return Promise.resolve(cached.data);
        }
        
        // Create promise for request
        const promise = new Promise((resolve, reject) => {
            request.resolve = resolve;
            request.reject = reject;
        });
        
        // Add to queue
        const priority = this.getPriorityValue(request.priority);
        if (!this.queue.has(priority)) {
            this.queue.set(priority, []);
        }
        this.queue.get(priority).push(request);
        
        this.stats.totalRequests++;
        this.scheduleBatch();
        
        return promise;
    }
    
    /**
     * Schedule batch processing
     */
    scheduleBatch() {
        const queueSize = Array.from(this.queue.values())
            .reduce((sum, requests) => sum + requests.length, 0);
        
        if (queueSize >= this.config.batchSize) {
            this.processBatch();
            return;
        }
        
        // Schedule delayed processing
        if (!this.timers.has("default")) {
            const timer = setTimeout(() => {
                this.timers.delete("default");
                this.processBatch();
            }, this.config.batchTimeout);
            
            this.timers.set("default", timer);
        }
    }
    
    /**
     * Process batch
     */
    async processBatch() {
        const batch = this.prepareBatch();
        
        if (batch.length === 0) {
            return;
        }
        
        try {
            const responses = await this.sendBatch(batch);
            this.processResponses(batch, responses);
            this.stats.batchedRequests += batch.length;
            this.stats.networkCalls++;
        } catch (error) {
            this.handleBatchError(batch, error);
        }
    }
    
    /**
     * Prepare batch from queue
     */
    prepareBatch() {
        const batch = [];
        const priorities = Array.from(this.queue.keys()).sort();
        
        for (const priority of priorities) {
            const requests = this.queue.get(priority) || [];
            
            while (requests.length > 0 && batch.length < this.config.maxBatchSize) {
                batch.push(requests.shift());
            }
            
            if (requests.length === 0) {
                this.queue.delete(priority);
            }
        }
        
        return batch;
    }
    
    /**
     * Send batch request
     */
    async sendBatch(batch) {
        const payload = {
            batch: batch.map(req => ({
                id: req.id,
                method: req.method,
                endpoint: req.endpoint,
                params: req.params,
                headers: req.headers
            })),
            timestamp: Date.now()
        };
        
        let body = JSON.stringify(payload);
        
        // Compress if enabled
        if (this.config.compression && "CompressionStream" in window) {
            const stream = new Blob([body]).stream();
            const compressed = stream.pipeThrough(new CompressionStream("gzip"));
            body = await new Response(compressed).blob();
        }
        
        const response = await fetch(this.config.endpoint, {
            method: "POST",
            headers: {
                "Content-Type": "application/json",
                "X-Batch-Request": "true"
            },
            body
        });
        
        if (!response.ok) {
            throw new Error(`Batch request failed: ${response.statusText}`);
        }
        
        const data = await response.json();
        return data.responses || [];
    }
    
    /**
     * Process responses
     */
    processResponses(batch, responses) {
        const responseMap = new Map(
            responses.map(res => [res.id, res])
        );
        
        for (const request of batch) {
            const response = responseMap.get(request.id);
            
            if (response) {
                if (response.status === "success") {
                    // Cache successful response
                    const cacheKey = this.getCacheKey(request);
                    this.cache.set(cacheKey, {
                        data: response.data,
                        expires: Date.now() + request.cacheTTL
                    });
                    
                    request.resolve(response.data);
                } else {
                    request.reject(new Error(response.error || "Request failed"));
                }
            } else {
                request.reject(new Error("No response received"));
            }
        }
    }
    
    /**
     * Handle batch error
     */
    handleBatchError(batch, error) {
        for (const request of batch) {
            request.reject(error);
        }
    }
    
    /**
     * Get cache key
     */
    getCacheKey(request) {
        return JSON.stringify({
            method: request.method,
            endpoint: request.endpoint,
            params: request.params
        });
    }
    
    /**
     * Get priority value
     */
    getPriorityValue(priority) {
        const priorities = {
            critical: 0,
            high: 1,
            normal: 2,
            low: 3
        };
        return priorities[priority] || 2;
    }
    
    /**
     * Get statistics
     */
    getStats() {
        return {
            ...this.stats,
            cacheSize: this.cache.size,
            queueSize: Array.from(this.queue.values())
                .reduce((sum, requests) => sum + requests.length, 0)
        };
    }
    
    /**
     * Clear cache
     */
    clearCache() {
        this.cache.clear();
    }
}

// Global instance
window.batcher = new RequestBatcher();

// Usage example:
// const data = await batcher.request({
//     endpoint: "/api/users/123",
//     method: "GET",
//     priority: "high"
// });
';
    }
    
    /**
     * Get statistics
     */
    public function getStats() {
        return array_merge($this->stats, [
            'cache_size' => count($this->response_cache),
            'queue_size' => array_sum(array_map('count', $this->request_queue)),
            'efficiency' => $this->stats['network_calls'] > 0 
                ? round($this->stats['batched_requests'] / $this->stats['network_calls'], 2)
                : 0
        ]);
    }
    
    /**
     * Process pending batches (for cron/scheduled execution)
     */
    public function processPendingBatches() {
        $processed = 0;
        
        while (!empty($this->request_queue)) {
            $this->processBatch();
            $processed++;
        }
        
        return $processed;
    }
}

// Example usage
$batcher = new RequestBatcher();

// Add multiple requests
$requests = [
    [
        'endpoint' => '/api/users/123',
        'method' => 'GET',
        'priority' => 'high',
        'callback' => function($data, $error) {
            if ($error) {
                echo "Error: " . $error['error'] . "\n";
            } else {
                echo "User data received: " . json_encode($data) . "\n";
            }
        }
    ],
    [
        'endpoint' => '/api/products',
        'method' => 'GET',
        'params' => ['category' => 'electronics'],
        'priority' => 'normal'
    ],
    [
        'endpoint' => '/api/analytics',
        'method' => 'POST',
        'params' => ['event' => 'page_view'],
        'priority' => 'low'
    ]
];

// Add requests to batcher
foreach ($requests as $request) {
    $batcher->addRequest($request);
}

// Process batch
$batcher->processBatch();

// Get statistics
$stats = $batcher->getStats();
echo "\nBatching Statistics:\n";
echo "Total Requests: {$stats['total_requests']}\n";
echo "Batched Requests: {$stats['batched_requests']}\n";
echo "Network Calls: {$stats['network_calls']}\n";
echo "Cache Hits: {$stats['cache_hits']}\n";
echo "Efficiency: {$stats['efficiency']} requests per call\n";

// Generate client script
file_put_contents('request-batcher.js', $batcher->generateClientScript());

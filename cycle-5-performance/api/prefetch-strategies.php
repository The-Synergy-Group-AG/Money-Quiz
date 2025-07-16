<?php
/**
 * Smart Data Prefetching Strategies
 * Implements intelligent prefetching to reduce perceived latency
 */

class PrefetchStrategies {
    private $config = [
        'strategies' => [
            'predictive' => true,      // ML-based prediction
            'pattern' => true,         // User pattern analysis
            'priority' => true,        // Priority-based prefetch
            'intersection' => true,    // Intersection Observer
            'hover' => true,           // Hover intent detection
            'idle' => true            // Idle time prefetch
        ],
        'limits' => [
            'max_concurrent' => 3,
            'max_queue_size' => 20,
            'bandwidth_threshold' => 0.5, // 50% of available
            'cache_size' => 52428800     // 50MB
        ],
        'ml_config' => [
            'min_confidence' => 0.7,
            'history_window' => 30,    // days
            'update_interval' => 3600  // seconds
        ],
        'resource_types' => [
            'api' => ['priority' => 1, 'cache_ttl' => 300],
            'image' => ['priority' => 2, 'cache_ttl' => 3600],
            'script' => ['priority' => 3, 'cache_ttl' => 86400],
            'style' => ['priority' => 3, 'cache_ttl' => 86400],
            'font' => ['priority' => 4, 'cache_ttl' => 604800]
        ]
    ];
    
    private $performance_monitor;
    private $prefetch_queue = [];
    private $active_prefetches = [];
    private $user_patterns = [];
    private $ml_model = null;
    private $cache = [];
    private $stats = [
        'total_prefetches' => 0,
        'successful_prefetches' => 0,
        'cache_hits' => 0,
        'bandwidth_saved' => 0
    ];
    
    public function __construct() {
        $this->performance_monitor = new PerformanceMonitor();
        $this->initializeMLModel();
    }
    
    /**
     * Initialize machine learning model for predictive prefetching
     */
    private function initializeMLModel() {
        // Simplified ML model - in production use proper ML library
        $this->ml_model = [
            'user_paths' => [],
            'resource_correlations' => [],
            'time_patterns' => [],
            'device_patterns' => []
        ];
        
        // Load historical data
        $this->loadHistoricalData();
    }
    
    /**
     * Analyze current context and determine what to prefetch
     */
    public function analyzeAndPrefetch($context) {
        $startTime = microtime(true);
        
        $prefetchCandidates = [];
        
        // Apply different strategies
        if ($this->config['strategies']['predictive']) {
            $prefetchCandidates = array_merge(
                $prefetchCandidates,
                $this->predictivePrefetch($context)
            );
        }
        
        if ($this->config['strategies']['pattern']) {
            $prefetchCandidates = array_merge(
                $prefetchCandidates,
                $this->patternBasedPrefetch($context)
            );
        }
        
        if ($this->config['strategies']['priority']) {
            $prefetchCandidates = array_merge(
                $prefetchCandidates,
                $this->priorityBasedPrefetch($context)
            );
        }
        
        // Deduplicate and score candidates
        $scoredCandidates = $this->scoreAndDeduplicate($prefetchCandidates);
        
        // Check resource constraints
        $finalCandidates = $this->applyResourceConstraints($scoredCandidates);
        
        // Queue prefetches
        foreach ($finalCandidates as $candidate) {
            $this->queuePrefetch($candidate);
        }
        
        // Process queue
        $this->processQueue();
        
        $this->performance_monitor->recordMetric('prefetch_analysis', [
            'candidates_found' => count($prefetchCandidates),
            'candidates_queued' => count($finalCandidates),
            'analysis_time' => microtime(true) - $startTime
        ]);
        
        return $finalCandidates;
    }
    
    /**
     * Predictive prefetching using ML
     */
    private function predictivePrefetch($context) {
        $predictions = [];
        
        // Analyze user journey
        $currentPath = $context['current_page'];
        $userHistory = $context['history'] ?? [];
        
        // Find similar patterns in historical data
        $similarPatterns = $this->findSimilarPatterns($userHistory);
        
        foreach ($similarPatterns as $pattern) {
            $nextLikelyPages = $this->predictNextPages($pattern, $currentPath);
            
            foreach ($nextLikelyPages as $page => $confidence) {
                if ($confidence >= $this->config['ml_config']['min_confidence']) {
                    $predictions[] = [
                        'type' => 'page',
                        'url' => $page,
                        'confidence' => $confidence,
                        'strategy' => 'predictive',
                        'resources' => $this->getPageResources($page)
                    ];
                }
            }
        }
        
        // Predict API calls
        $apiPredictions = $this->predictAPICalls($context);
        $predictions = array_merge($predictions, $apiPredictions);
        
        return $predictions;
    }
    
    /**
     * Pattern-based prefetching
     */
    private function patternBasedPrefetch($context) {
        $patterns = [];
        
        // Time-based patterns
        $timeOfDay = date('H');
        $dayOfWeek = date('w');
        
        $timePatterns = $this->ml_model['time_patterns'][$timeOfDay][$dayOfWeek] ?? [];
        foreach ($timePatterns as $resource => $probability) {
            if ($probability > 0.5) {
                $patterns[] = [
                    'type' => 'resource',
                    'url' => $resource,
                    'confidence' => $probability,
                    'strategy' => 'time_pattern'
                ];
            }
        }
        
        // Device-based patterns
        $deviceType = $this->detectDeviceType($context);
        $devicePatterns = $this->ml_model['device_patterns'][$deviceType] ?? [];
        
        foreach ($devicePatterns as $resource => $probability) {
            if ($probability > 0.5) {
                $patterns[] = [
                    'type' => 'resource',
                    'url' => $resource,
                    'confidence' => $probability,
                    'strategy' => 'device_pattern'
                ];
            }
        }
        
        // Navigation patterns
        $navPatterns = $this->analyzeNavigationPatterns($context);
        $patterns = array_merge($patterns, $navPatterns);
        
        return $patterns;
    }
    
    /**
     * Priority-based prefetching
     */
    private function priorityBasedPrefetch($context) {
        $priorities = [];
        
        // Critical resources for current page
        $currentPage = $context['current_page'];
        $pageConfig = $this->getPageConfiguration($currentPage);
        
        foreach ($pageConfig['critical_resources'] ?? [] as $resource) {
            $priorities[] = [
                'type' => $this->detectResourceType($resource),
                'url' => $resource,
                'confidence' => 1.0,
                'strategy' => 'critical',
                'priority' => 1
            ];
        }
        
        // Next likely interactions
        $likelyInteractions = $this->analyzeLikelyInteractions($context);
        
        foreach ($likelyInteractions as $interaction) {
            $resources = $this->getInteractionResources($interaction);
            foreach ($resources as $resource) {
                $priorities[] = [
                    'type' => $this->detectResourceType($resource),
                    'url' => $resource,
                    'confidence' => $interaction['probability'],
                    'strategy' => 'interaction',
                    'priority' => 2
                ];
            }
        }
        
        return $priorities;
    }
    
    /**
     * Score and deduplicate candidates
     */
    private function scoreAndDeduplicate($candidates) {
        $uniqueCandidates = [];
        $seen = [];
        
        foreach ($candidates as $candidate) {
            $key = $candidate['url'];
            
            if (!isset($seen[$key])) {
                $candidate['score'] = $this->calculateScore($candidate);
                $uniqueCandidates[] = $candidate;
                $seen[$key] = true;
            } else {
                // Update score if higher
                foreach ($uniqueCandidates as &$existing) {
                    if ($existing['url'] === $key) {
                        $newScore = $this->calculateScore($candidate);
                        if ($newScore > $existing['score']) {
                            $existing['score'] = $newScore;
                            $existing['confidence'] = max($existing['confidence'], $candidate['confidence']);
                        }
                        break;
                    }
                }
            }
        }
        
        // Sort by score
        usort($uniqueCandidates, function($a, $b) {
            return $b['score'] <=> $a['score'];
        });
        
        return $uniqueCandidates;
    }
    
    /**
     * Calculate prefetch score
     */
    private function calculateScore($candidate) {
        $score = $candidate['confidence'] * 100;
        
        // Adjust by resource type priority
        $resourceType = $candidate['type'];
        if (isset($this->config['resource_types'][$resourceType])) {
            $priority = $this->config['resource_types'][$resourceType]['priority'];
            $score *= (5 - $priority) / 4; // Higher priority = higher score
        }
        
        // Adjust by strategy
        $strategyWeights = [
            'predictive' => 1.2,
            'critical' => 1.5,
            'interaction' => 1.1,
            'time_pattern' => 0.9,
            'device_pattern' => 0.8
        ];
        
        $weight = $strategyWeights[$candidate['strategy']] ?? 1.0;
        $score *= $weight;
        
        // Penalize if already in cache
        if ($this->isInCache($candidate['url'])) {
            $score *= 0.1;
        }
        
        return $score;
    }
    
    /**
     * Apply resource constraints
     */
    private function applyResourceConstraints($candidates) {
        $filtered = [];
        $estimatedBandwidth = $this->estimateBandwidthUsage($candidates);
        $availableBandwidth = $this->getAvailableBandwidth();
        
        foreach ($candidates as $candidate) {
            // Check concurrent limit
            if (count($this->active_prefetches) >= $this->config['limits']['max_concurrent']) {
                break;
            }
            
            // Check queue size
            if (count($filtered) >= $this->config['limits']['max_queue_size']) {
                break;
            }
            
            // Check bandwidth
            $resourceSize = $this->estimateResourceSize($candidate);
            if ($estimatedBandwidth + $resourceSize > $availableBandwidth * $this->config['limits']['bandwidth_threshold']) {
                continue;
            }
            
            // Check cache size
            if ($this->getCacheSize() + $resourceSize > $this->config['limits']['cache_size']) {
                $this->evictFromCache($resourceSize);
            }
            
            $filtered[] = $candidate;
            $estimatedBandwidth += $resourceSize;
        }
        
        return $filtered;
    }
    
    /**
     * Queue prefetch request
     */
    private function queuePrefetch($candidate) {
        $this->prefetch_queue[] = [
            'candidate' => $candidate,
            'queued_at' => microtime(true),
            'attempts' => 0,
            'status' => 'queued'
        ];
    }
    
    /**
     * Process prefetch queue
     */
    private function processQueue() {
        while (!empty($this->prefetch_queue) && 
               count($this->active_prefetches) < $this->config['limits']['max_concurrent']) {
            
            $item = array_shift($this->prefetch_queue);
            $this->executePrefetch($item);
        }
    }
    
    /**
     * Execute prefetch
     */
    private function executePrefetch($item) {
        $candidate = $item['candidate'];
        $url = $candidate['url'];
        
        // Check cache first
        if ($this->isInCache($url)) {
            $this->stats['cache_hits']++;
            return;
        }
        
        $this->active_prefetches[$url] = [
            'start_time' => microtime(true),
            'candidate' => $candidate
        ];
        
        // Simulate async prefetch (in production, use proper async HTTP client)
        $this->simulatePrefetch($url, function($success, $data) use ($url, $candidate) {
            unset($this->active_prefetches[$url]);
            
            if ($success) {
                $this->addToCache($url, $data, $candidate['type']);
                $this->stats['successful_prefetches']++;
                $this->updateMLModel($candidate, true);
            } else {
                $this->updateMLModel($candidate, false);
            }
            
            // Process next in queue
            $this->processQueue();
        });
        
        $this->stats['total_prefetches']++;
    }
    
    /**
     * Simulate prefetch (placeholder for actual implementation)
     */
    private function simulatePrefetch($url, $callback) {
        // In production, use actual HTTP client
        $data = "Prefetched content for: $url";
        $success = rand(0, 100) > 10; // 90% success rate
        
        // Simulate network delay
        usleep(rand(10000, 50000)); // 10-50ms
        
        $callback($success, $data);
    }
    
    /**
     * Update ML model based on prefetch result
     */
    private function updateMLModel($candidate, $success) {
        // Update model based on success/failure
        $strategy = $candidate['strategy'];
        $confidence = $candidate['confidence'];
        
        // Simple feedback mechanism
        if ($success) {
            $this->ml_model['resource_correlations'][$candidate['url']] = 
                ($this->ml_model['resource_correlations'][$candidate['url']] ?? 0) + 0.1;
        } else {
            $this->ml_model['resource_correlations'][$candidate['url']] = 
                ($this->ml_model['resource_correlations'][$candidate['url']] ?? 0) - 0.05;
        }
        
        // Persist model updates periodically
        if (time() % $this->config['ml_config']['update_interval'] === 0) {
            $this->persistMLModel();
        }
    }
    
    // Helper methods
    
    private function findSimilarPatterns($history) {
        // Simplified pattern matching
        $patterns = [];
        foreach ($this->ml_model['user_paths'] as $path) {
            $similarity = $this->calculateSimilarity($history, $path['history']);
            if ($similarity > 0.7) {
                $patterns[] = $path;
            }
        }
        return $patterns;
    }
    
    private function calculateSimilarity($history1, $history2) {
        // Simple similarity calculation
        $common = array_intersect($history1, $history2);
        $total = count(array_unique(array_merge($history1, $history2)));
        return $total > 0 ? count($common) / $total : 0;
    }
    
    private function predictNextPages($pattern, $currentPage) {
        // Predict next pages based on pattern
        $predictions = [];
        if (isset($pattern['transitions'][$currentPage])) {
            $predictions = $pattern['transitions'][$currentPage];
        }
        return $predictions;
    }
    
    private function predictAPICalls($context) {
        // Predict API calls based on context
        $predictions = [];
        
        // Example: Predict user data API call
        if (isset($context['user_id']) && !$this->isInCache("/api/user/{$context['user_id']}")) {
            $predictions[] = [
                'type' => 'api',
                'url' => "/api/user/{$context['user_id']}",
                'confidence' => 0.8,
                'strategy' => 'predictive'
            ];
        }
        
        return $predictions;
    }
    
    private function getPageResources($page) {
        // Get resources associated with a page
        // In production, this would be from a resource map
        return [
            "/css/$page.css",
            "/js/$page.js",
            "/api/$page/data"
        ];
    }
    
    private function detectDeviceType($context) {
        $userAgent = $context['user_agent'] ?? '';
        if (stripos($userAgent, 'mobile') !== false) return 'mobile';
        if (stripos($userAgent, 'tablet') !== false) return 'tablet';
        return 'desktop';
    }
    
    private function analyzeNavigationPatterns($context) {
        // Analyze navigation patterns
        $patterns = [];
        
        // Example: Common navigation paths
        $commonPaths = [
            '/home' => ['/products' => 0.7, '/about' => 0.3],
            '/products' => ['/product/*' => 0.8, '/cart' => 0.2],
            '/product/*' => ['/cart' => 0.6, '/products' => 0.4]
        ];
        
        $currentPage = $context['current_page'];
        foreach ($commonPaths as $page => $transitions) {
            if (fnmatch($page, $currentPage)) {
                foreach ($transitions as $nextPage => $probability) {
                    $patterns[] = [
                        'type' => 'navigation',
                        'url' => $nextPage,
                        'confidence' => $probability,
                        'strategy' => 'navigation_pattern'
                    ];
                }
            }
        }
        
        return $patterns;
    }
    
    private function getPageConfiguration($page) {
        // Get page-specific configuration
        return [
            'critical_resources' => [
                "/css/critical.css",
                "/js/core.js"
            ]
        ];
    }
    
    private function analyzeLikelyInteractions($context) {
        // Analyze likely user interactions
        return [
            ['action' => 'click_menu', 'probability' => 0.8],
            ['action' => 'scroll_down', 'probability' => 0.9],
            ['action' => 'click_cta', 'probability' => 0.6]
        ];
    }
    
    private function getInteractionResources($interaction) {
        // Get resources needed for interaction
        $resourceMap = [
            'click_menu' => ['/api/menu', '/css/menu.css'],
            'scroll_down' => ['/api/content/next', '/images/lazy/*'],
            'click_cta' => ['/js/modal.js', '/css/modal.css']
        ];
        
        return $resourceMap[$interaction['action']] ?? [];
    }
    
    private function detectResourceType($url) {
        $extension = pathinfo($url, PATHINFO_EXTENSION);
        $typeMap = [
            'js' => 'script',
            'css' => 'style',
            'jpg' => 'image',
            'jpeg' => 'image',
            'png' => 'image',
            'webp' => 'image',
            'woff' => 'font',
            'woff2' => 'font'
        ];
        
        if (isset($typeMap[$extension])) {
            return $typeMap[$extension];
        }
        
        if (strpos($url, '/api/') !== false) {
            return 'api';
        }
        
        return 'other';
    }
    
    private function isInCache($url) {
        return isset($this->cache[$url]) && 
               $this->cache[$url]['expires'] > time();
    }
    
    private function addToCache($url, $data, $type) {
        $ttl = $this->config['resource_types'][$type]['cache_ttl'] ?? 300;
        $this->cache[$url] = [
            'data' => $data,
            'size' => strlen($data),
            'type' => $type,
            'expires' => time() + $ttl,
            'hits' => 0
        ];
    }
    
    private function getCacheSize() {
        return array_sum(array_column($this->cache, 'size'));
    }
    
    private function evictFromCache($neededSize) {
        // LRU eviction
        uasort($this->cache, function($a, $b) {
            return $a['hits'] <=> $b['hits'];
        });
        
        $freedSize = 0;
        foreach ($this->cache as $url => $item) {
            unset($this->cache[$url]);
            $freedSize += $item['size'];
            
            if ($freedSize >= $neededSize) {
                break;
            }
        }
    }
    
    private function estimateBandwidthUsage($candidates) {
        return array_sum(array_map([$this, 'estimateResourceSize'], $candidates));
    }
    
    private function estimateResourceSize($candidate) {
        // Estimate based on resource type
        $estimates = [
            'api' => 5000,      // 5KB
            'image' => 100000,  // 100KB
            'script' => 50000,  // 50KB
            'style' => 30000,   // 30KB
            'font' => 100000    // 100KB
        ];
        
        return $estimates[$candidate['type']] ?? 10000;
    }
    
    private function getAvailableBandwidth() {
        // In production, use Network Information API
        return 10000000; // 10Mbps
    }
    
    private function loadHistoricalData() {
        // Load from database or file
        // This is simplified example data
        $this->ml_model['user_paths'] = [
            [
                'history' => ['/home', '/products', '/product/123'],
                'transitions' => [
                    '/product/123' => ['/cart' => 0.8, '/products' => 0.2]
                ]
            ]
        ];
    }
    
    private function persistMLModel() {
        // Save model to database or file
        file_put_contents('ml_model.json', json_encode($this->ml_model));
    }
    
    /**
     * Generate client-side prefetch script
     */
    public function generateClientScript() {
        return '
// Smart Prefetch Client
class SmartPrefetch {
    constructor(config = {}) {
        this.config = {
            enablePredictive: true,
            enableHover: true,
            enableIntersection: true,
            enableIdle: true,
            hoverDelay: 200,
            intersectionThreshold: 0.5,
            maxConcurrent: 3,
            cacheTTL: 300000, // 5 minutes
            ...config
        };
        
        this.cache = new Map();
        this.prefetchQueue = [];
        this.activePrefetches = new Map();
        this.userHistory = [];
        this.hoverTimers = new Map();
        
        this.initialize();
    }
    
    initialize() {
        // Track user navigation
        this.trackNavigation();
        
        // Set up prefetch strategies
        if (this.config.enableHover) {
            this.setupHoverPrefetch();
        }
        
        if (this.config.enableIntersection) {
            this.setupIntersectionPrefetch();
        }
        
        if (this.config.enableIdle) {
            this.setupIdlePrefetch();
        }
        
        if (this.config.enablePredictive) {
            this.setupPredictivePrefetch();
        }
    }
    
    /**
     * Track user navigation
     */
    trackNavigation() {
        // Record page views
        this.userHistory.push({
            page: window.location.pathname,
            timestamp: Date.now()
        });
        
        // Listen for navigation changes
        window.addEventListener("popstate", () => {
            this.userHistory.push({
                page: window.location.pathname,
                timestamp: Date.now()
            });
            
            this.updatePredictions();
        });
    }
    
    /**
     * Set up hover prefetch
     */
    setupHoverPrefetch() {
        document.addEventListener("mouseover", (e) => {
            const link = e.target.closest("a[href]");
            if (!link) return;
            
            const href = link.href;
            if (this.shouldPrefetch(href)) {
                // Start timer
                const timer = setTimeout(() => {
                    this.prefetch(href, "hover");
                }, this.config.hoverDelay);
                
                this.hoverTimers.set(link, timer);
            }
        });
        
        document.addEventListener("mouseout", (e) => {
            const link = e.target.closest("a[href]");
            if (!link) return;
            
            // Cancel timer
            const timer = this.hoverTimers.get(link);
            if (timer) {
                clearTimeout(timer);
                this.hoverTimers.delete(link);
            }
        });
    }
    
    /**
     * Set up intersection observer prefetch
     */
    setupIntersectionPrefetch() {
        if (!("IntersectionObserver" in window)) return;
        
        const observer = new IntersectionObserver((entries) => {
            entries.forEach(entry => {
                if (entry.isIntersecting && entry.intersectionRatio >= this.config.intersectionThreshold) {
                    const link = entry.target;
                    const href = link.href;
                    
                    if (this.shouldPrefetch(href)) {
                        this.prefetch(href, "intersection");
                    }
                }
            });
        }, {
            threshold: this.config.intersectionThreshold
        });
        
        // Observe all links
        document.querySelectorAll("a[href]").forEach(link => {
            observer.observe(link);
        });
    }
    
    /**
     * Set up idle prefetch
     */
    setupIdlePrefetch() {
        if (!("requestIdleCallback" in window)) return;
        
        const idlePrefetch = () => {
            requestIdleCallback((deadline) => {
                while (deadline.timeRemaining() > 0 && this.prefetchQueue.length > 0) {
                    const item = this.prefetchQueue.shift();
                    this.executePrefetch(item);
                }
                
                // Schedule next idle callback
                if (this.prefetchQueue.length > 0) {
                    idlePrefetch();
                }
            }, {
                timeout: 2000
            });
        };
        
        // Start idle prefetching
        idlePrefetch();
    }
    
    /**
     * Set up predictive prefetch
     */
    setupPredictivePrefetch() {
        // Analyze user patterns and prefetch likely resources
        this.updatePredictions();
        
        // Update predictions periodically
        setInterval(() => this.updatePredictions(), 60000); // Every minute
    }
    
    /**
     * Update predictions based on user behavior
     */
    updatePredictions() {
        const currentPage = window.location.pathname;
        const predictions = this.predictNextPages(currentPage);
        
        predictions.forEach(({ url, confidence }) => {
            if (confidence > 0.7) {
                this.queuePrefetch(url, "predictive", confidence);
            }
        });
    }
    
    /**
     * Predict next pages
     */
    predictNextPages(currentPage) {
        // Simple prediction based on common patterns
        const patterns = {
            "/": [{ url: "/products", confidence: 0.8 }, { url: "/about", confidence: 0.5 }],
            "/products": [{ url: "/product/*", confidence: 0.9 }],
            "/product/*": [{ url: "/cart", confidence: 0.7 }]
        };
        
        for (const [pattern, predictions] of Object.entries(patterns)) {
            if (this.matchPattern(pattern, currentPage)) {
                return predictions;
            }
        }
        
        return [];
    }
    
    /**
     * Check if URL should be prefetched
     */
    shouldPrefetch(url) {
        // Skip if already cached
        if (this.cache.has(url)) {
            const cached = this.cache.get(url);
            if (cached.expires > Date.now()) {
                return false;
            }
        }
        
        // Skip if already prefetching
        if (this.activePrefetches.has(url)) {
            return false;
        }
        
        // Skip external URLs
        try {
            const urlObj = new URL(url);
            if (urlObj.origin !== window.location.origin) {
                return false;
            }
        } catch (e) {
            return false;
        }
        
        // Skip certain file types
        const skipExtensions = [".pdf", ".zip", ".exe"];
        if (skipExtensions.some(ext => url.endsWith(ext))) {
            return false;
        }
        
        return true;
    }
    
    /**
     * Prefetch resource
     */
    prefetch(url, strategy, priority = 0.5) {
        if (!this.shouldPrefetch(url)) return;
        
        // Queue prefetch
        this.queuePrefetch(url, strategy, priority);
        
        // Process queue
        this.processQueue();
    }
    
    /**
     * Queue prefetch
     */
    queuePrefetch(url, strategy, priority) {
        const item = { url, strategy, priority, timestamp: Date.now() };
        
        // Insert in priority order
        const index = this.prefetchQueue.findIndex(i => i.priority < priority);
        if (index === -1) {
            this.prefetchQueue.push(item);
        } else {
            this.prefetchQueue.splice(index, 0, item);
        }
    }
    
    /**
     * Process prefetch queue
     */
    processQueue() {
        while (this.activePrefetches.size < this.config.maxConcurrent && this.prefetchQueue.length > 0) {
            const item = this.prefetchQueue.shift();
            this.executePrefetch(item);
        }
    }
    
    /**
     * Execute prefetch
     */
    async executePrefetch(item) {
        const { url, strategy } = item;
        
        this.activePrefetches.set(url, item);
        
        try {
            // Use link prefetch
            const link = document.createElement("link");
            link.rel = "prefetch";
            link.href = url;
            link.as = this.detectResourceType(url);
            
            document.head.appendChild(link);
            
            // Also fetch to cache
            const response = await fetch(url, {
                mode: "cors",
                credentials: "same-origin"
            });
            
            if (response.ok) {
                const data = await response.text();
                
                // Cache response
                this.cache.set(url, {
                    data,
                    headers: Object.fromEntries(response.headers.entries()),
                    expires: Date.now() + this.config.cacheTTL
                });
                
                console.log(`Prefetched: ${url} (${strategy})`);
            }
        } catch (error) {
            console.error(`Prefetch failed: ${url}`, error);
        } finally {
            this.activePrefetches.delete(url);
            this.processQueue();
        }
    }
    
    /**
     * Detect resource type
     */
    detectResourceType(url) {
        const extension = url.split(".").pop().toLowerCase();
        const typeMap = {
            js: "script",
            css: "style",
            jpg: "image",
            jpeg: "image",
            png: "image",
            webp: "image",
            woff: "font",
            woff2: "font"
        };
        
        return typeMap[extension] || "fetch";
    }
    
    /**
     * Match URL pattern
     */
    matchPattern(pattern, url) {
        if (pattern === url) return true;
        if (pattern.includes("*")) {
            const regex = new RegExp(pattern.replace(/\*/g, ".*"));
            return regex.test(url);
        }
        return false;
    }
    
    /**
     * Get cache statistics
     */
    getStats() {
        return {
            cacheSize: this.cache.size,
            queueLength: this.prefetchQueue.length,
            activePrefetches: this.activePrefetches.size,
            totalCached: Array.from(this.cache.values())
                .reduce((sum, item) => sum + (item.data?.length || 0), 0)
        };
    }
}

// Initialize smart prefetch
const prefetch = new SmartPrefetch();

// Export for use
window.smartPrefetch = prefetch;
';
    }
    
    /**
     * Get prefetch statistics
     */
    public function getStats() {
        return array_merge($this->stats, [
            'cache_size' => count($this->cache),
            'queue_length' => count($this->prefetch_queue),
            'active_prefetches' => count($this->active_prefetches),
            'hit_rate' => $this->stats['total_prefetches'] > 0 
                ? round(($this->stats['cache_hits'] / $this->stats['total_prefetches']) * 100, 2)
                : 0
        ]);
    }
}

// Example usage
$prefetcher = new PrefetchStrategies();

// Analyze current context
$context = [
    'current_page' => '/products',
    'history' => ['/home', '/about', '/products'],
    'user_id' => '123',
    'user_agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) Chrome/91.0',
    'timestamp' => time()
];

$candidates = $prefetcher->analyzeAndPrefetch($context);

echo "Prefetch Analysis Results:\n\n";
echo "Candidates found: " . count($candidates) . "\n\n";

foreach ($candidates as $candidate) {
    echo "URL: {$candidate['url']}\n";
    echo "Type: {$candidate['type']}\n";
    echo "Strategy: {$candidate['strategy']}\n";
    echo "Confidence: " . round($candidate['confidence'] * 100) . "%\n";
    echo "Score: " . round($candidate['score'], 2) . "\n\n";
}

// Get statistics
$stats = $prefetcher->getStats();
echo "Prefetch Statistics:\n";
echo "Total Prefetches: {$stats['total_prefetches']}\n";
echo "Successful: {$stats['successful_prefetches']}\n";
echo "Cache Hits: {$stats['cache_hits']}\n";
echo "Hit Rate: {$stats['hit_rate']}%\n";

// Generate client script
file_put_contents('smart-prefetch.js', $prefetcher->generateClientScript());

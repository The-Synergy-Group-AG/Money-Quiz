<?php
/**
 * Code Splitting Implementation for Dynamic Module Loading
 * Reduces initial bundle size by splitting code into chunks
 */

class CodeSplitter {
    private $config = [
        'chunk_size_limit' => 244000, // 244KB per chunk
        'vendor_chunks' => [
            'react' => ['react', 'react-dom'],
            'lodash' => ['lodash'],
            'charts' => ['chart.js', 'd3'],
            'utils' => ['moment', 'axios']
        ],
        'route_chunks' => [
            'dashboard' => ['Dashboard', 'DashboardWidgets'],
            'quiz' => ['QuizEngine', 'QuizResults'],
            'admin' => ['AdminPanel', 'UserManagement']
        ]
    ];
    
    private $performance_monitor;
    private $cache_dir = '/tmp/code-chunks/';
    
    public function __construct() {
        $this->performance_monitor = new PerformanceMonitor();
        if (!is_dir($this->cache_dir)) {
            mkdir($this->cache_dir, 0777, true);
        }
    }
    
    /**
     * Generate webpack configuration for code splitting
     */
    public function generateWebpackConfig() {
        return [
            'optimization' => [
                'splitChunks' => [
                    'chunks' => 'all',
                    'minSize' => 20000,
                    'maxSize' => $this->config['chunk_size_limit'],
                    'minRemainingSize' => 0,
                    'minChunks' => 1,
                    'maxAsyncRequests' => 30,
                    'maxInitialRequests' => 30,
                    'enforceSizeThreshold' => 50000,
                    'cacheGroups' => $this->generateCacheGroups()
                ],
                'runtimeChunk' => 'single',
                'moduleIds' => 'deterministic'
            ],
            'output' => [
                'filename' => '[name].[contenthash].js',
                'chunkFilename' => '[name].[contenthash].chunk.js',
                'path' => __DIR__ . '/dist'
            ]
        ];
    }
    
    /**
     * Generate cache groups for vendor splitting
     */
    private function generateCacheGroups() {
        $cacheGroups = [
            'vendor' => [
                'test' => '/[\\/]node_modules[\\/]/',
                'priority' => 10,
                'reuseExistingChunk' => true
            ]
        ];
        
        foreach ($this->config['vendor_chunks'] as $name => $modules) {
            $cacheGroups[$name] = [
                'test' => $this->createModuleTest($modules),
                'name' => $name,
                'priority' => 20,
                'reuseExistingChunk' => true
            ];
        }
        
        return $cacheGroups;
    }
    
    /**
     * Create test function for module matching
     */
    private function createModuleTest($modules) {
        $pattern = '(' . implode('|', array_map('preg_quote', $modules)) . ')';
        return '/[\\/]node_modules[\\/]' . $pattern . '/';
    }
    
    /**
     * Implement dynamic imports for route-based splitting
     */
    public function generateDynamicImports($routes) {
        $imports = [];
        
        foreach ($routes as $route => $component) {
            $imports[$route] = [
                'component' => "() => import(/* webpackChunkName: '{$route}' */ './{$component}')",
                'loading' => 'LoadingComponent',
                'error' => 'ErrorBoundary',
                'delay' => 300,
                'timeout' => 10000
            ];
        }
        
        return $imports;
    }
    
    /**
     * Analyze bundle for splitting opportunities
     */
    public function analyzeBundleForSplitting($bundlePath) {
        $startTime = microtime(true);
        
        // Read and parse bundle
        $bundleContent = file_get_contents($bundlePath);
        $bundleSize = strlen($bundleContent);
        
        // Extract module boundaries
        preg_match_all('/\/\*\*\*\/ "([^"]+)"/', $bundleContent, $modules);
        
        $analysis = [
            'total_size' => $bundleSize,
            'module_count' => count($modules[1]),
            'opportunities' => [],
            'recommendations' => []
        ];
        
        // Analyze module sizes and dependencies
        foreach ($modules[1] as $module) {
            $moduleSize = $this->estimateModuleSize($bundleContent, $module);
            
            if ($moduleSize > 50000) { // 50KB threshold
                $analysis['opportunities'][] = [
                    'module' => $module,
                    'size' => $moduleSize,
                    'potential_saving' => $moduleSize * 0.7 // Estimated after splitting
                ];
            }
        }
        
        // Generate recommendations
        if ($bundleSize > 1000000) { // 1MB
            $analysis['recommendations'][] = 'Bundle size exceeds 1MB. Consider aggressive code splitting.';
        }
        
        if (count($analysis['opportunities']) > 5) {
            $analysis['recommendations'][] = 'Multiple large modules detected. Implement route-based splitting.';
        }
        
        $this->performance_monitor->recordMetric('bundle_analysis', [
            'duration' => microtime(true) - $startTime,
            'bundle_size' => $bundleSize,
            'opportunities_found' => count($analysis['opportunities'])
        ]);
        
        return $analysis;
    }
    
    /**
     * Estimate module size within bundle
     */
    private function estimateModuleSize($bundleContent, $module) {
        $modulePattern = preg_quote($module, '/');
        $nextModulePattern = '\/\*\*\*\/ "[^"]+"';
        
        if (preg_match(
            "/{$modulePattern}.*?(?={$nextModulePattern}|$)/s",
            $bundleContent,
            $match
        )) {
            return strlen($match[0]);
        }
        
        return 0;
    }
    
    /**
     * Generate prefetch hints for critical chunks
     */
    public function generatePrefetchHints($currentRoute) {
        $hints = [];
        $routeConfig = $this->config['route_chunks'][$currentRoute] ?? [];
        
        // Prefetch related route chunks
        $relatedRoutes = $this->getRelatedRoutes($currentRoute);
        foreach ($relatedRoutes as $route) {
            if (isset($this->config['route_chunks'][$route])) {
                $hints[] = [
                    'rel' => 'prefetch',
                    'href' => "/dist/{$route}.chunk.js",
                    'as' => 'script'
                ];
            }
        }
        
        // Preload critical vendor chunks
        $criticalVendors = ['react', 'utils'];
        foreach ($criticalVendors as $vendor) {
            $hints[] = [
                'rel' => 'preload',
                'href' => "/dist/{$vendor}.chunk.js",
                'as' => 'script'
            ];
        }
        
        return $hints;
    }
    
    /**
     * Get related routes for prefetching
     */
    private function getRelatedRoutes($currentRoute) {
        $routeMap = [
            'dashboard' => ['quiz', 'profile'],
            'quiz' => ['dashboard', 'results'],
            'admin' => ['users', 'settings']
        ];
        
        return $routeMap[$currentRoute] ?? [];
    }
    
    /**
     * Implement chunk loading with retry logic
     */
    public function generateChunkLoader() {
        return '
const chunkLoadingMap = new Map();
const maxRetries = 3;
const retryDelay = 1000;

function loadChunk(chunkName, retries = 0) {
    if (chunkLoadingMap.has(chunkName)) {
        return chunkLoadingMap.get(chunkName);
    }
    
    const promise = import(
        /* webpackChunkName: "[request]" */
        /* webpackPrefetch: true */
        `./${chunkName}`
    ).catch(error => {
        if (retries < maxRetries) {
            console.warn(`Retrying chunk load: ${chunkName} (${retries + 1}/${maxRetries})`);
            return new Promise(resolve => {
                setTimeout(() => resolve(loadChunk(chunkName, retries + 1)), retryDelay);
            });
        }
        throw error;
    });
    
    chunkLoadingMap.set(chunkName, promise);
    return promise;
}

// Intersection Observer for lazy loading
const chunkObserver = new IntersectionObserver((entries) => {
    entries.forEach(entry => {
        if (entry.isIntersecting) {
            const chunkName = entry.target.dataset.chunk;
            if (chunkName) {
                loadChunk(chunkName);
                chunkObserver.unobserve(entry.target);
            }
        }
    });
}, {
    rootMargin: "50px"
});

// Observe elements that need chunk loading
document.querySelectorAll("[data-chunk]").forEach(el => {
    chunkObserver.observe(el);
});
';
    }
    
    /**
     * Monitor chunk loading performance
     */
    public function monitorChunkPerformance() {
        return '
if ("PerformanceObserver" in window) {
    const chunkObserver = new PerformanceObserver((list) => {
        for (const entry of list.getEntries()) {
            if (entry.initiatorType === "script" && entry.name.includes(".chunk.js")) {
                const chunkName = entry.name.match(/([^/]+)\.chunk\.js/)?.[1];
                const loadTime = entry.responseEnd - entry.startTime;
                
                // Send metrics to server
                fetch("/api/metrics/chunk-performance", {
                    method: "POST",
                    headers: { "Content-Type": "application/json" },
                    body: JSON.stringify({
                        chunk: chunkName,
                        loadTime: loadTime,
                        size: entry.transferSize,
                        cached: entry.transferSize === 0
                    })
                });
            }
        }
    });
    
    chunkObserver.observe({ entryTypes: ["resource"] });
}
';
    }
}

// Example usage
$splitter = new CodeSplitter();

// Generate webpack config
$webpackConfig = $splitter->generateWebpackConfig();
file_put_contents('webpack.config.json', json_encode($webpackConfig, JSON_PRETTY_PRINT));

// Analyze existing bundle
if (file_exists('dist/main.bundle.js')) {
    $analysis = $splitter->analyzeBundleForSplitting('dist/main.bundle.js');
    echo "Bundle Analysis:\n";
    echo "Total Size: " . number_format($analysis['total_size'] / 1024, 2) . " KB\n";
    echo "Splitting Opportunities: " . count($analysis['opportunities']) . "\n";
    
    foreach ($analysis['recommendations'] as $recommendation) {
        echo "- $recommendation\n";
    }
}

// Generate dynamic imports
$routes = [
    'dashboard' => 'components/Dashboard',
    'quiz' => 'components/Quiz',
    'admin' => 'components/Admin'
];

$dynamicImports = $splitter->generateDynamicImports($routes);
file_put_contents('dynamic-imports.json', json_encode($dynamicImports, JSON_PRETTY_PRINT));

// Generate chunk loader
file_put_contents('chunk-loader.js', $splitter->generateChunkLoader());

// Generate performance monitoring
file_put_contents('chunk-monitor.js', $splitter->monitorChunkPerformance());

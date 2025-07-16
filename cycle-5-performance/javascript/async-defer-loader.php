<?php
/**
 * Async/Defer Script Loading Optimization
 * Optimizes JavaScript loading with proper async/defer attributes
 */

class AsyncDeferLoader {
    private $config = [
        // Scripts that can be deferred (DOM-dependent)
        'defer_scripts' => [
            'app.js',
            'components.js',
            'widgets.js',
            'ui-handlers.js'
        ],
        // Scripts that can be async (independent)
        'async_scripts' => [
            'analytics.js',
            'tracking.js',
            'error-reporter.js',
            'performance-monitor.js'
        ],
        // Scripts that must be synchronous
        'sync_scripts' => [
            'polyfills.js',
            'critical-path.js'
        ],
        // Scripts to preload
        'preload_scripts' => [
            'vendor.js',
            'runtime.js'
        ]
    ];
    
    private $performance_monitor;
    private $script_registry = [];
    
    public function __construct() {
        $this->performance_monitor = new PerformanceMonitor();
    }
    
    /**
     * Register a script with loading strategy
     */
    public function registerScript($src, $options = []) {
        $defaults = [
            'loading' => 'auto', // auto, async, defer, sync
            'priority' => 'normal', // high, normal, low
            'dependencies' => [],
            'condition' => null,
            'preload' => false,
            'module' => false,
            'nomodule' => false,
            'integrity' => null,
            'crossorigin' => null
        ];
        
        $options = array_merge($defaults, $options);
        
        // Auto-detect loading strategy if not specified
        if ($options['loading'] === 'auto') {
            $options['loading'] = $this->detectLoadingStrategy($src);
        }
        
        $this->script_registry[$src] = $options;
        
        return $this;
    }
    
    /**
     * Detect optimal loading strategy based on script name
     */
    private function detectLoadingStrategy($src) {
        $filename = basename($src);
        
        if (in_array($filename, $this->config['async_scripts'])) {
            return 'async';
        } elseif (in_array($filename, $this->config['defer_scripts'])) {
            return 'defer';
        } elseif (in_array($filename, $this->config['sync_scripts'])) {
            return 'sync';
        }
        
        // Default strategy based on content analysis
        if (strpos($src, 'analytics') !== false || strpos($src, 'tracking') !== false) {
            return 'async';
        }
        
        return 'defer';
    }
    
    /**
     * Generate optimized script tags
     */
    public function generateScriptTags() {
        $output = [];
        $preloads = [];
        
        // Sort scripts by priority and dependencies
        $sortedScripts = $this->sortScriptsByDependencies();
        
        foreach ($sortedScripts as $src => $options) {
            // Generate preload hints
            if ($options['preload'] || in_array(basename($src), $this->config['preload_scripts'])) {
                $preloads[] = $this->generatePreloadTag($src, $options);
            }
            
            // Generate script tag
            $output[] = $this->generateScriptTag($src, $options);
        }
        
        return [
            'preloads' => implode("\n", $preloads),
            'scripts' => implode("\n", $output)
        ];
    }
    
    /**
     * Generate a single script tag with optimizations
     */
    private function generateScriptTag($src, $options) {
        $attributes = ['src' => $src];
        
        // Add loading strategy
        switch ($options['loading']) {
            case 'async':
                $attributes['async'] = true;
                break;
            case 'defer':
                $attributes['defer'] = true;
                break;
            case 'module':
                $attributes['type'] = 'module';
                if ($options['loading'] !== 'sync') {
                    $attributes['defer'] = true;
                }
                break;
        }
        
        // Add module/nomodule attributes
        if ($options['module']) {
            $attributes['type'] = 'module';
        }
        if ($options['nomodule']) {
            $attributes['nomodule'] = true;
        }
        
        // Add security attributes
        if ($options['integrity']) {
            $attributes['integrity'] = $options['integrity'];
        }
        if ($options['crossorigin']) {
            $attributes['crossorigin'] = $options['crossorigin'];
        }
        
        // Add custom data attributes for monitoring
        $attributes['data-priority'] = $options['priority'];
        $attributes['data-load-time'] = 'pending';
        
        // Build tag
        $tag = '<script';
        foreach ($attributes as $key => $value) {
            if ($value === true) {
                $tag .= " $key";
            } else {
                $tag .= " $key=\"" . htmlspecialchars($value) . "\"";
            }
        }
        $tag .= '></script>';
        
        // Wrap with condition if needed
        if ($options['condition']) {
            $tag = "<!--[if {$options['condition']}]>\n$tag\n<![endif]-->";
        }
        
        return $tag;
    }
    
    /**
     * Generate preload tag
     */
    private function generatePreloadTag($src, $options) {
        $attributes = [
            'rel' => 'preload',
            'as' => 'script',
            'href' => $src
        ];
        
        if ($options['crossorigin']) {
            $attributes['crossorigin'] = $options['crossorigin'];
        }
        
        if ($options['module']) {
            $attributes['as'] = 'script';
            $attributes['type'] = 'module';
        }
        
        $tag = '<link';
        foreach ($attributes as $key => $value) {
            $tag .= " $key=\"" . htmlspecialchars($value) . "\"";
        }
        $tag .= '>';
        
        return $tag;
    }
    
    /**
     * Sort scripts by dependencies
     */
    private function sortScriptsByDependencies() {
        $sorted = [];
        $visited = [];
        
        foreach ($this->script_registry as $src => $options) {
            $this->visitScript($src, $sorted, $visited);
        }
        
        return $sorted;
    }
    
    /**
     * Visit script for dependency sorting (DFS)
     */
    private function visitScript($src, &$sorted, &$visited) {
        if (isset($visited[$src])) {
            return;
        }
        
        $visited[$src] = true;
        $options = $this->script_registry[$src] ?? [];
        
        // Visit dependencies first
        foreach ($options['dependencies'] ?? [] as $dep) {
            if (isset($this->script_registry[$dep])) {
                $this->visitScript($dep, $sorted, $visited);
            }
        }
        
        $sorted[$src] = $options;
    }
    
    /**
     * Generate loading performance monitoring script
     */
    public function generatePerformanceMonitor() {
        return '
// Script Loading Performance Monitor
(function() {
    const scriptTimings = new Map();
    const scriptLoadOrder = [];
    
    // Monitor script loading
    document.addEventListener("DOMContentLoaded", function() {
        const scripts = document.querySelectorAll("script[src]");
        
        scripts.forEach(script => {
            const src = script.src;
            const startTime = performance.now();
            
            script.addEventListener("load", function() {
                const loadTime = performance.now() - startTime;
                scriptTimings.set(src, {
                    loadTime: loadTime,
                    async: script.async,
                    defer: script.defer,
                    module: script.type === "module",
                    priority: script.dataset.priority || "normal"
                });
                
                scriptLoadOrder.push(src);
                script.dataset.loadTime = loadTime.toFixed(2);
                
                // Log slow scripts
                if (loadTime > 1000) {
                    console.warn(`Slow script load: ${src} took ${loadTime.toFixed(2)}ms`);
                }
            });
            
            script.addEventListener("error", function() {
                console.error(`Failed to load script: ${src}`);
                script.dataset.loadTime = "error";
            });
        });
    });
    
    // Report metrics after page load
    window.addEventListener("load", function() {
        setTimeout(function() {
            const metrics = {
                totalScripts: scriptTimings.size,
                loadOrder: scriptLoadOrder,
                timings: Array.from(scriptTimings.entries()).map(([src, data]) => ({
                    src: src.split("/").pop(),
                    ...data
                })),
                totalLoadTime: Array.from(scriptTimings.values())
                    .reduce((sum, data) => sum + data.loadTime, 0)
            };
            
            // Send to analytics
            if (window.analytics && window.analytics.track) {
                window.analytics.track("Script Loading Performance", metrics);
            }
            
            // Log to console in development
            if (window.location.hostname === "localhost") {
                console.table(metrics.timings);
                console.log(`Total script load time: ${metrics.totalLoadTime.toFixed(2)}ms`);
            }
        }, 1000);
    });
})();
';
    }
    
    /**
     * Generate dynamic script loader with optimization
     */
    public function generateDynamicLoader() {
        return '
class DynamicScriptLoader {
    constructor() {
        this.loadedScripts = new Set();
        this.loadingScripts = new Map();
        this.scriptCache = new Map();
    }
    
    /**
     * Load a script dynamically with optimization
     */
    async loadScript(src, options = {}) {
        // Check if already loaded
        if (this.loadedScripts.has(src)) {
            return Promise.resolve();
        }
        
        // Check if currently loading
        if (this.loadingScripts.has(src)) {
            return this.loadingScripts.get(src);
        }
        
        // Default options
        const defaults = {
            async: true,
            defer: false,
            module: false,
            preload: true,
            timeout: 10000,
            retries: 3,
            cache: true
        };
        
        options = { ...defaults, ...options };
        
        // Create loading promise
        const loadPromise = this._loadScriptImpl(src, options);
        this.loadingScripts.set(src, loadPromise);
        
        try {
            await loadPromise;
            this.loadedScripts.add(src);
            this.loadingScripts.delete(src);
        } catch (error) {
            this.loadingScripts.delete(src);
            throw error;
        }
        
        return loadPromise;
    }
    
    async _loadScriptImpl(src, options) {
        // Check cache first
        if (options.cache && this.scriptCache.has(src)) {
            const cachedScript = this.scriptCache.get(src);
            eval(cachedScript);
            return;
        }
        
        // Preload if requested
        if (options.preload) {
            this._preloadScript(src);
        }
        
        return new Promise((resolve, reject) => {
            const script = document.createElement("script");
            script.src = src;
            
            // Set attributes
            if (options.async) script.async = true;
            if (options.defer) script.defer = true;
            if (options.module) script.type = "module";
            
            // Set up timeout
            const timeout = setTimeout(() => {
                reject(new Error(`Script load timeout: ${src}`));
                script.remove();
            }, options.timeout);
            
            // Handle load success
            script.onload = () => {
                clearTimeout(timeout);
                resolve();
                
                // Cache if enabled
                if (options.cache) {
                    fetch(src)
                        .then(res => res.text())
                        .then(text => this.scriptCache.set(src, text));
                }
            };
            
            // Handle load error with retry
            script.onerror = async () => {
                clearTimeout(timeout);
                script.remove();
                
                if (options.retries > 0) {
                    console.warn(`Retrying script load: ${src} (${options.retries} retries left)`);
                    await new Promise(r => setTimeout(r, 1000));
                    return this._loadScriptImpl(src, { ...options, retries: options.retries - 1 });
                }
                
                reject(new Error(`Failed to load script: ${src}`));
            };
            
            // Append to document
            (document.head || document.documentElement).appendChild(script);
        });
    }
    
    _preloadScript(src) {
        const link = document.createElement("link");
        link.rel = "preload";
        link.as = "script";
        link.href = src;
        document.head.appendChild(link);
    }
    
    /**
     * Load multiple scripts in parallel
     */
    async loadScripts(scripts) {
        return Promise.all(
            scripts.map(script => 
                typeof script === "string" 
                    ? this.loadScript(script)
                    : this.loadScript(script.src, script.options)
            )
        );
    }
    
    /**
     * Load scripts in sequence
     */
    async loadScriptsSequential(scripts) {
        for (const script of scripts) {
            if (typeof script === "string") {
                await this.loadScript(script);
            } else {
                await this.loadScript(script.src, script.options);
            }
        }
    }
}

// Global instance
window.scriptLoader = new DynamicScriptLoader();
';
    }
    
    /**
     * Analyze current page scripts for optimization opportunities
     */
    public function analyzePageScripts($html) {
        $dom = new DOMDocument();
        @$dom->loadHTML($html);
        
        $scripts = $dom->getElementsByTagName('script');
        $analysis = [
            'total_scripts' => $scripts->length,
            'sync_scripts' => 0,
            'async_scripts' => 0,
            'defer_scripts' => 0,
            'inline_scripts' => 0,
            'external_scripts' => 0,
            'optimization_opportunities' => []
        ];
        
        foreach ($scripts as $script) {
            if ($script->hasAttribute('src')) {
                $analysis['external_scripts']++;
                
                if ($script->hasAttribute('async')) {
                    $analysis['async_scripts']++;
                } elseif ($script->hasAttribute('defer')) {
                    $analysis['defer_scripts']++;
                } else {
                    $analysis['sync_scripts']++;
                    
                    // Check if this could be optimized
                    $src = $script->getAttribute('src');
                    $filename = basename($src);
                    
                    if (!in_array($filename, $this->config['sync_scripts'])) {
                        $analysis['optimization_opportunities'][] = [
                            'script' => $src,
                            'current' => 'sync',
                            'recommended' => $this->detectLoadingStrategy($src),
                            'impact' => 'high'
                        ];
                    }
                }
            } else {
                $analysis['inline_scripts']++;
            }
        }
        
        // Calculate optimization score
        $optimizedScripts = $analysis['async_scripts'] + $analysis['defer_scripts'];
        $analysis['optimization_score'] = $analysis['external_scripts'] > 0
            ? round(($optimizedScripts / $analysis['external_scripts']) * 100, 2)
            : 100;
        
        return $analysis;
    }
}

// Example usage
$loader = new AsyncDeferLoader();

// Register scripts with optimal loading strategies
$loader->registerScript('/js/vendor.js', [
    'loading' => 'defer',
    'priority' => 'high',
    'preload' => true
]);

$loader->registerScript('/js/app.js', [
    'loading' => 'defer',
    'priority' => 'high',
    'dependencies' => ['/js/vendor.js']
]);

$loader->registerScript('/js/analytics.js', [
    'loading' => 'async',
    'priority' => 'low'
]);

$loader->registerScript('/js/polyfills.js', [
    'loading' => 'sync',
    'priority' => 'critical',
    'condition' => 'lt IE 11'
]);

// Generate optimized script tags
$tags = $loader->generateScriptTags();
echo "<!-- Preload Links -->\n";
echo $tags['preloads'] . "\n\n";
echo "<!-- Script Tags -->\n";
echo $tags['scripts'] . "\n";

// Generate monitoring scripts
file_put_contents('script-monitor.js', $loader->generatePerformanceMonitor());
file_put_contents('dynamic-loader.js', $loader->generateDynamicLoader());

// Analyze existing HTML
if (file_exists('index.html')) {
    $html = file_get_contents('index.html');
    $analysis = $loader->analyzePageScripts($html);
    
    echo "\nScript Analysis:\n";
    echo "Total Scripts: {$analysis['total_scripts']}\n";
    echo "Optimization Score: {$analysis['optimization_score']}%\n";
    echo "Optimization Opportunities: " . count($analysis['optimization_opportunities']) . "\n";
}

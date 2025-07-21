<?php
/**
 * Bundle Size Optimization
 * Reduces JavaScript bundle sizes through various optimization techniques
 */

class BundleOptimizer {
    private $config = [
        'target_bundle_size' => 200000, // 200KB target size
        'compression' => [
            'gzip' => true,
            'brotli' => true,
            'level' => 9
        ],
        'minification' => [
            'remove_comments' => true,
            'remove_whitespace' => true,
            'shorten_variables' => true,
            'inline_functions' => true
        ],
        'optimization' => [
            'tree_shaking' => true,
            'scope_hoisting' => true,
            'constant_folding' => true,
            'dead_code_elimination' => true
        ]
    ];
    
    private $performance_monitor;
    private $cache_dir = '/tmp/optimized-bundles/';
    
    public function __construct() {
        $this->performance_monitor = new PerformanceMonitor();
        if (!is_dir($this->cache_dir)) {
            mkdir($this->cache_dir, 0777, true);
        }
    }
    
    /**
     * Optimize a JavaScript bundle
     */
    public function optimizeBundle($inputPath, $outputPath = null) {
        $startTime = microtime(true);
        
        if (!$outputPath) {
            $outputPath = str_replace('.js', '.min.js', $inputPath);
        }
        
        // Read original bundle
        $originalContent = file_get_contents($inputPath);
        $originalSize = strlen($originalContent);
        
        // Apply optimizations
        $optimizedContent = $originalContent;
        
        // Step 1: Remove dead code
        if ($this->config['optimization']['dead_code_elimination']) {
            $optimizedContent = $this->eliminateDeadCode($optimizedContent);
        }
        
        // Step 2: Minify
        $optimizedContent = $this->minifyJavaScript($optimizedContent);
        
        // Step 3: Apply advanced optimizations
        $optimizedContent = $this->applyAdvancedOptimizations($optimizedContent);
        
        // Step 4: Compress
        $compressionResults = $this->compressBundle($optimizedContent, $outputPath);
        
        // Write optimized bundle
        file_put_contents($outputPath, $optimizedContent);
        
        // Calculate metrics
        $optimizedSize = strlen($optimizedContent);
        $reduction = (($originalSize - $optimizedSize) / $originalSize) * 100;
        
        $results = [
            'original_size' => $originalSize,
            'optimized_size' => $optimizedSize,
            'reduction_percentage' => round($reduction, 2),
            'compression' => $compressionResults,
            'optimization_time' => microtime(true) - $startTime
        ];
        
        $this->performance_monitor->recordMetric('bundle_optimization', $results);
        
        return $results;
    }
    
    /**
     * Minify JavaScript code
     */
    private function minifyJavaScript($code) {
        if (!$this->config['minification']['remove_comments']) {
            return $code;
        }
        
        // Remove single-line comments
        $code = preg_replace('/\/\/(?![\s\S]*?\*\/).*$/m', '', $code);
        
        // Remove multi-line comments
        $code = preg_replace('/\/\*[\s\S]*?\*\//', '', $code);
        
        if ($this->config['minification']['remove_whitespace']) {
            // Remove unnecessary whitespace
            $code = preg_replace('/\s+/', ' ', $code);
            $code = preg_replace('/\s*([{}()\[\];:,])\s*/', '$1', $code);
        }
        
        if ($this->config['minification']['shorten_variables']) {
            // Simple variable name shortening (in production, use proper AST-based tools)
            $code = $this->shortenVariableNames($code);
        }
        
        return trim($code);
    }
    
    /**
     * Shorten variable names
     */
    private function shortenVariableNames($code) {
        // This is a simplified version - in production use proper AST parsing
        $varMap = [];
        $counter = 0;
        $chars = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
        
        // Find all variable declarations
        preg_match_all('/(?:var|let|const)\s+([a-zA-Z_$][a-zA-Z0-9_$]*)/', $code, $matches);
        
        foreach (array_unique($matches[1]) as $varName) {
            // Skip short names and globals
            if (strlen($varName) <= 2 || in_array($varName, ['window', 'document', 'console'])) {
                continue;
            }
            
            $shortName = $chars[$counter % strlen($chars)];
            if ($counter >= strlen($chars)) {
                $shortName .= floor($counter / strlen($chars));
            }
            
            $varMap[$varName] = $shortName;
            $counter++;
        }
        
        // Replace variable names
        foreach ($varMap as $original => $short) {
            $code = preg_replace("/\b$original\b/", $short, $code);
        }
        
        return $code;
    }
    
    /**
     * Eliminate dead code
     */
    private function eliminateDeadCode($code) {
        // Remove unreachable code after return statements
        $code = preg_replace('/return[^;]*;[^}]*(?=})/s', 'return$1;', $code);
        
        // Remove empty functions
        $code = preg_replace('/function\s+\w+\s*\([^)]*\)\s*\{\s*\}/', '', $code);
        
        // Remove unused variables (simplified)
        $code = preg_replace('/(?:var|let|const)\s+\w+\s*;/', '', $code);
        
        // Remove console.log in production
        if (!defined('DEBUG') || !DEBUG) {
            $code = preg_replace('/console\.\w+\([^)]*\);?/', '', $code);
        }
        
        return $code;
    }
    
    /**
     * Apply advanced optimizations
     */
    private function applyAdvancedOptimizations($code) {
        // Constant folding
        if ($this->config['optimization']['constant_folding']) {
            $code = $this->foldConstants($code);
        }
        
        // Inline small functions
        if ($this->config['minification']['inline_functions']) {
            $code = $this->inlineSmallFunctions($code);
        }
        
        // Optimize loops
        $code = $this->optimizeLoops($code);
        
        return $code;
    }
    
    /**
     * Fold constants
     */
    private function foldConstants($code) {
        // Simple arithmetic operations
        $code = preg_replace_callback('/\b(\d+)\s*\+\s*(\d+)\b/', function($matches) {
            return $matches[1] + $matches[2];
        }, $code);
        
        $code = preg_replace_callback('/\b(\d+)\s*\*\s*(\d+)\b/', function($matches) {
            return $matches[1] * $matches[2];
        }, $code);
        
        // Boolean operations
        $code = str_replace('true && true', 'true', $code);
        $code = str_replace('false || false', 'false', $code);
        $code = str_replace('!true', 'false', $code);
        $code = str_replace('!false', 'true', $code);
        
        return $code;
    }
    
    /**
     * Inline small functions
     */
    private function inlineSmallFunctions($code) {
        // Find small functions (less than 50 characters)
        preg_match_all('/function\s+(\w+)\s*\(([^)]*)\)\s*\{([^}]{1,50})\}/', $code, $matches, PREG_SET_ORDER);
        
        foreach ($matches as $match) {
            $funcName = $match[1];
            $params = $match[2];
            $body = trim($match[3]);
            
            // Only inline if it's a simple return statement
            if (preg_match('/^return\s+(.+);?$/', $body, $returnMatch)) {
                $returnExpr = $returnMatch[1];
                
                // Replace function calls with inline expression
                $pattern = "/$funcName\s*\(([^)]*)\)/";
                $code = preg_replace_callback($pattern, function($callMatch) use ($params, $returnExpr) {
                    // Simple parameter substitution
                    $args = explode(',', $callMatch[1]);
                    $paramNames = explode(',', $params);
                    
                    $inlinedExpr = $returnExpr;
                    for ($i = 0; $i < count($paramNames) && $i < count($args); $i++) {
                        $paramName = trim($paramNames[$i]);
                        $argValue = trim($args[$i]);
                        $inlinedExpr = str_replace($paramName, "($argValue)", $inlinedExpr);
                    }
                    
                    return "($inlinedExpr)";
                }, $code);
                
                // Remove the function definition
                $code = str_replace($match[0], '', $code);
            }
        }
        
        return $code;
    }
    
    /**
     * Optimize loops
     */
    private function optimizeLoops($code) {
        // Cache array length in for loops
        $code = preg_replace(
            '/for\s*\(([^;]+);\s*([^;]+)\.length\s*;/i',
            'for($1,_len=$2.length;$2<_len;',
            $code
        );
        
        // Convert forEach to for loops for better performance
        $code = preg_replace_callback(
            '/([\w.]+)\.forEach\(function\s*\(([^)]*)\)\s*\{([^}]+)\}\)/',
            function($matches) {
                $array = $matches[1];
                $params = $matches[2];
                $body = $matches[3];
                
                $paramList = explode(',', $params);
                $itemVar = trim($paramList[0]);
                
                return "for(var _i=0,_len={$array}.length;_i<_len;_i++){var {$itemVar}={$array}[_i];{$body}}";
            },
            $code
        );
        
        return $code;
    }
    
    /**
     * Compress bundle with gzip/brotli
     */
    private function compressBundle($content, $basePath) {
        $results = [];
        
        if ($this->config['compression']['gzip']) {
            $gzipContent = gzencode($content, $this->config['compression']['level']);
            file_put_contents($basePath . '.gz', $gzipContent);
            $results['gzip'] = [
                'size' => strlen($gzipContent),
                'ratio' => round((strlen($gzipContent) / strlen($content)) * 100, 2)
            ];
        }
        
        if ($this->config['compression']['brotli'] && function_exists('brotli_compress')) {
            $brotliContent = brotli_compress($content, $this->config['compression']['level']);
            file_put_contents($basePath . '.br', $brotliContent);
            $results['brotli'] = [
                'size' => strlen($brotliContent),
                'ratio' => round((strlen($brotliContent) / strlen($content)) * 100, 2)
            ];
        }
        
        return $results;
    }
    
    /**
     * Analyze bundle for optimization opportunities
     */
    public function analyzeBundle($bundlePath) {
        $content = file_get_contents($bundlePath);
        $analysis = [
            'size' => strlen($content),
            'issues' => [],
            'recommendations' => [],
            'metrics' => []
        ];
        
        // Check for common issues
        
        // Large comments
        preg_match_all('/\/\*[\s\S]*?\*\//', $content, $comments);
        $commentSize = array_sum(array_map('strlen', $comments[0]));
        if ($commentSize > 10000) {
            $analysis['issues'][] = [
                'type' => 'large_comments',
                'size' => $commentSize,
                'impact' => 'medium'
            ];
            $analysis['recommendations'][] = 'Remove comments in production builds';
        }
        
        // Console.log statements
        $consoleCount = substr_count($content, 'console.');
        if ($consoleCount > 10) {
            $analysis['issues'][] = [
                'type' => 'console_statements',
                'count' => $consoleCount,
                'impact' => 'low'
            ];
            $analysis['recommendations'][] = 'Remove console statements in production';
        }
        
        // Duplicate code detection
        $duplicates = $this->findDuplicateCode($content);
        if (count($duplicates) > 0) {
            $analysis['issues'][] = [
                'type' => 'duplicate_code',
                'instances' => count($duplicates),
                'impact' => 'high'
            ];
            $analysis['recommendations'][] = 'Extract duplicate code into shared functions';
        }
        
        // Large functions
        preg_match_all('/function[^{]*\{([^{}]*(?:\{[^{}]*\}[^{}]*)*)\}/', $content, $functions);
        $largeFunctions = array_filter($functions[1], function($func) {
            return strlen($func) > 1000;
        });
        
        if (count($largeFunctions) > 0) {
            $analysis['issues'][] = [
                'type' => 'large_functions',
                'count' => count($largeFunctions),
                'impact' => 'medium'
            ];
            $analysis['recommendations'][] = 'Break down large functions into smaller, reusable pieces';
        }
        
        // Calculate metrics
        $analysis['metrics'] = [
            'total_functions' => count($functions[0]),
            'average_function_size' => count($functions[1]) > 0 
                ? round(array_sum(array_map('strlen', $functions[1])) / count($functions[1]))
                : 0,
            'minification_potential' => round((($commentSize + ($consoleCount * 20)) / strlen($content)) * 100, 2),
            'estimated_gzip_size' => round(strlen($content) * 0.3), // Rough estimate
            'estimated_brotli_size' => round(strlen($content) * 0.25) // Rough estimate
        ];
        
        return $analysis;
    }
    
    /**
     * Find duplicate code blocks
     */
    private function findDuplicateCode($content) {
        $duplicates = [];
        $minLength = 100; // Minimum length for duplicate detection
        
        // Simple substring matching for demonstration
        // In production, use more sophisticated algorithms
        $lines = explode("\n", $content);
        $codeBlocks = [];
        
        for ($i = 0; $i < count($lines) - 5; $i++) {
            $block = implode("\n", array_slice($lines, $i, 5));
            if (strlen($block) >= $minLength) {
                if (isset($codeBlocks[$block])) {
                    $duplicates[] = [
                        'line1' => $codeBlocks[$block],
                        'line2' => $i,
                        'content' => substr($block, 0, 50) . '...'
                    ];
                } else {
                    $codeBlocks[$block] = $i;
                }
            }
        }
        
        return $duplicates;
    }
    
    /**
     * Generate webpack optimization config
     */
    public function generateWebpackOptimizationConfig() {
        return [
            'optimization' => [
                'minimize' => true,
                'minimizer' => [
                    [
                        'plugin' => 'TerserPlugin',
                        'options' => [
                            'terserOptions' => [
                                'parse' => ['ecma' => 8],
                                'compress' => [
                                    'ecma' => 5,
                                    'warnings' => false,
                                    'comparisons' => false,
                                    'inline' => 2,
                                    'drop_console' => true,
                                    'drop_debugger' => true,
                                    'pure_funcs' => ['console.log', 'console.info']
                                ],
                                'mangle' => [
                                    'safari10' => true
                                ],
                                'output' => [
                                    'ecma' => 5,
                                    'comments' => false,
                                    'ascii_only' => true
                                ]
                            ],
                            'parallel' => true,
                            'cache' => true
                        ]
                    ],
                    [
                        'plugin' => 'OptimizeCSSAssetsPlugin',
                        'options' => [
                            'cssProcessorPluginOptions' => [
                                'preset' => ['default', ['discardComments' => ['removeAll' => true]]]
                            ]
                        ]
                    ]
                ],
                'runtimeChunk' => 'single',
                'moduleIds' => 'hashed',
                'sideEffects' => false,
                'usedExports' => true,
                'concatenateModules' => true
            ],
            'performance' => [
                'hints' => 'warning',
                'maxEntrypointSize' => $this->config['target_bundle_size'],
                'maxAssetSize' => $this->config['target_bundle_size']
            ]
        ];
    }
}

// Example usage
$optimizer = new BundleOptimizer();

// Optimize a bundle
if (file_exists('dist/app.js')) {
    $results = $optimizer->optimizeBundle('dist/app.js', 'dist/app.min.js');
    
    echo "Bundle Optimization Results:\n";
    echo "Original Size: " . number_format($results['original_size'] / 1024, 2) . " KB\n";
    echo "Optimized Size: " . number_format($results['optimized_size'] / 1024, 2) . " KB\n";
    echo "Size Reduction: {$results['reduction_percentage']}%\n";
    
    if (isset($results['compression']['gzip'])) {
        echo "Gzip Size: " . number_format($results['compression']['gzip']['size'] / 1024, 2) . " KB\n";
    }
    
    if (isset($results['compression']['brotli'])) {
        echo "Brotli Size: " . number_format($results['compression']['brotli']['size'] / 1024, 2) . " KB\n";
    }
}

// Analyze bundle for issues
if (file_exists('dist/app.js')) {
    $analysis = $optimizer->analyzeBundle('dist/app.js');
    
    echo "\nBundle Analysis:\n";
    echo "Total Size: " . number_format($analysis['size'] / 1024, 2) . " KB\n";
    echo "Issues Found: " . count($analysis['issues']) . "\n";
    
    foreach ($analysis['recommendations'] as $recommendation) {
        echo "- $recommendation\n";
    }
}

// Generate webpack config
$webpackConfig = $optimizer->generateWebpackOptimizationConfig();
file_put_contents('webpack.optimization.json', json_encode($webpackConfig, JSON_PRETTY_PRINT));

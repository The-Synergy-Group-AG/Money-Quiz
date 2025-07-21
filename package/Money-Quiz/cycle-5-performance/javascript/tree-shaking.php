<?php
/**
 * Tree Shaking Implementation for Dead Code Elimination
 * Removes unused code from JavaScript bundles
 */

class TreeShaker {
    private $config = [
        'aggressive_mode' => true,
        'preserve_patterns' => [
            '/^__/', // Preserve __webpack internals
            '/^Symbol/', // Preserve Symbol references
            '/^global/', // Preserve global references
        ],
        'side_effect_free_modules' => [
            'lodash-es',
            'date-fns',
            'ramda',
            'utils/pure'
        ],
        'external_modules' => [
            'react',
            'react-dom',
            'vue',
            'angular'
        ]
    ];
    
    private $performance_monitor;
    private $ast_cache = [];
    private $dependency_graph = [];
    private $export_map = [];
    private $import_map = [];
    
    public function __construct() {
        $this->performance_monitor = new PerformanceMonitor();
    }
    
    /**
     * Analyze module for tree shaking opportunities
     */
    public function analyzeModule($modulePath) {
        $startTime = microtime(true);
        
        $content = file_get_contents($modulePath);
        $analysis = [
            'module' => $modulePath,
            'exports' => [],
            'imports' => [],
            'used_exports' => [],
            'unused_exports' => [],
            'side_effects' => false,
            'size' => strlen($content),
            'potential_savings' => 0
        ];
        
        // Parse exports
        $analysis['exports'] = $this->parseExports($content);
        
        // Parse imports
        $analysis['imports'] = $this->parseImports($content);
        
        // Detect side effects
        $analysis['side_effects'] = $this->detectSideEffects($content);
        
        // Build export/import maps
        $this->export_map[$modulePath] = $analysis['exports'];
        $this->import_map[$modulePath] = $analysis['imports'];
        
        $this->performance_monitor->recordMetric('module_analysis', [
            'module' => $modulePath,
            'duration' => microtime(true) - $startTime,
            'exports_count' => count($analysis['exports']),
            'imports_count' => count($analysis['imports'])
        ]);
        
        return $analysis;
    }
    
    /**
     * Parse exports from module
     */
    private function parseExports($content) {
        $exports = [];
        
        // ES6 named exports
        preg_match_all('/export\s+(?:const|let|var|function|class)\s+([\w$]+)/', $content, $matches);
        foreach ($matches[1] as $export) {
            $exports[] = ['name' => $export, 'type' => 'named'];
        }
        
        // ES6 export statements
        preg_match_all('/export\s*\{([^}]+)\}/', $content, $matches);
        foreach ($matches[1] as $exportList) {
            $names = array_map('trim', explode(',', $exportList));
            foreach ($names as $name) {
                // Handle 'as' aliases
                if (strpos($name, ' as ') !== false) {
                    list($original, $alias) = array_map('trim', explode(' as ', $name));
                    $exports[] = ['name' => $alias, 'original' => $original, 'type' => 'named'];
                } else {
                    $exports[] = ['name' => $name, 'type' => 'named'];
                }
            }
        }
        
        // Default export
        if (preg_match('/export\s+default/', $content)) {
            $exports[] = ['name' => 'default', 'type' => 'default'];
        }
        
        // CommonJS exports
        preg_match_all('/(?:module\.)?exports\.([\w$]+)\s*=/', $content, $matches);
        foreach ($matches[1] as $export) {
            $exports[] = ['name' => $export, 'type' => 'commonjs'];
        }
        
        return $exports;
    }
    
    /**
     * Parse imports from module
     */
    private function parseImports($content) {
        $imports = [];
        
        // ES6 imports
        preg_match_all('/import\s+(?:\{([^}]*)\}|([\w$]+)|\*\s+as\s+([\w$]+))\s+from\s+["\']([^"\']+)["\']/', $content, $matches, PREG_SET_ORDER);
        
        foreach ($matches as $match) {
            $module = $match[4];
            
            if ($match[1]) { // Named imports
                $names = array_map('trim', explode(',', $match[1]));
                foreach ($names as $name) {
                    if (strpos($name, ' as ') !== false) {
                        list($original, $alias) = array_map('trim', explode(' as ', $name));
                        $imports[] = ['name' => $original, 'alias' => $alias, 'module' => $module, 'type' => 'named'];
                    } else {
                        $imports[] = ['name' => $name, 'module' => $module, 'type' => 'named'];
                    }
                }
            } elseif ($match[2]) { // Default import
                $imports[] = ['name' => 'default', 'alias' => $match[2], 'module' => $module, 'type' => 'default'];
            } elseif ($match[3]) { // Namespace import
                $imports[] = ['name' => '*', 'alias' => $match[3], 'module' => $module, 'type' => 'namespace'];
            }
        }
        
        // Dynamic imports
        preg_match_all('/import\s*\(["\']([^"\']+)["\']\)/', $content, $matches);
        foreach ($matches[1] as $module) {
            $imports[] = ['module' => $module, 'type' => 'dynamic'];
        }
        
        // CommonJS requires
        preg_match_all('/require\s*\(["\']([^"\']+)["\']\)/', $content, $matches);
        foreach ($matches[1] as $module) {
            $imports[] = ['module' => $module, 'type' => 'commonjs'];
        }
        
        return $imports;
    }
    
    /**
     * Detect side effects in module
     */
    private function detectSideEffects($content) {
        // Check if module is in side-effect-free list
        foreach ($this->config['side_effect_free_modules'] as $module) {
            if (strpos($content, $module) !== false) {
                return false;
            }
        }
        
        // Patterns that indicate side effects
        $sideEffectPatterns = [
            '/\bwindow\.[\w$]+\s*=/', // Global assignments
            '/\bdocument\.[\w$]+\s*=/', // DOM modifications
            '/\.prototype\.[\w$]+\s*=/', // Prototype modifications
            '/\bconsole\./', // Console operations (can be configurable)
            '/\beval\s*\(/', // eval usage
            '/new\s+Function\s*\(/', // Function constructor
            '/\.__defineGetter__/', // Getter/setter definitions
            '/\.__defineSetter__/',
            '/Object\.defineProperty\s*\(\s*(?:window|global)/', // Global property definitions
        ];
        
        foreach ($sideEffectPatterns as $pattern) {
            if (preg_match($pattern, $content)) {
                return true;
            }
        }
        
        // Check for top-level function calls (potential side effects)
        $lines = explode("\n", $content);
        foreach ($lines as $line) {
            $line = trim($line);
            // Skip comments and imports/exports
            if (strpos($line, '//') === 0 || 
                strpos($line, '/*') === 0 ||
                strpos($line, 'import ') === 0 ||
                strpos($line, 'export ') === 0) {
                continue;
            }
            
            // Check for function calls at top level
            if (preg_match('/^[\w$]+\s*\(/', $line) && !preg_match('/^(?:var|let|const|function|class)\b/', $line)) {
                return true;
            }
        }
        
        return false;
    }
    
    /**
     * Build dependency graph for all modules
     */
    public function buildDependencyGraph($entryPoint) {
        $visited = [];
        $this->dependency_graph = [];
        
        $this->traverseDependencies($entryPoint, $visited);
        
        return $this->dependency_graph;
    }
    
    /**
     * Traverse module dependencies
     */
    private function traverseDependencies($modulePath, &$visited) {
        if (isset($visited[$modulePath])) {
            return;
        }
        
        $visited[$modulePath] = true;
        
        // Analyze module if not already done
        if (!isset($this->import_map[$modulePath])) {
            $this->analyzeModule($modulePath);
        }
        
        $imports = $this->import_map[$modulePath] ?? [];
        $this->dependency_graph[$modulePath] = [];
        
        foreach ($imports as $import) {
            $importPath = $this->resolveModulePath($import['module'], $modulePath);
            if ($importPath && !in_array($import['module'], $this->config['external_modules'])) {
                $this->dependency_graph[$modulePath][] = [
                    'module' => $importPath,
                    'imports' => [$import]
                ];
                
                // Recursively traverse
                $this->traverseDependencies($importPath, $visited);
            }
        }
    }
    
    /**
     * Resolve module path
     */
    private function resolveModulePath($importPath, $fromModule) {
        // Simplified path resolution
        if (strpos($importPath, './') === 0 || strpos($importPath, '../') === 0) {
            $basePath = dirname($fromModule);
            $resolved = realpath($basePath . '/' . $importPath);
            
            // Try with .js extension
            if (!$resolved || !file_exists($resolved)) {
                $resolved = realpath($basePath . '/' . $importPath . '.js');
            }
            
            return $resolved;
        }
        
        // Node modules or absolute paths
        return null; // Simplified - would need proper module resolution
    }
    
    /**
     * Mark used exports based on dependency graph
     */
    public function markUsedExports() {
        $usedExports = [];
        
        foreach ($this->dependency_graph as $module => $dependencies) {
            foreach ($dependencies as $dep) {
                $depModule = $dep['module'];
                
                foreach ($dep['imports'] as $import) {
                    if (!isset($usedExports[$depModule])) {
                        $usedExports[$depModule] = [];
                    }
                    
                    if ($import['type'] === 'namespace' || $import['name'] === '*') {
                        // Mark all exports as used
                        $usedExports[$depModule] = array_merge(
                            $usedExports[$depModule],
                            array_column($this->export_map[$depModule] ?? [], 'name')
                        );
                    } else {
                        $usedExports[$depModule][] = $import['name'];
                    }
                }
            }
        }
        
        return $usedExports;
    }
    
    /**
     * Generate tree-shaken bundle
     */
    public function generateTreeShakenBundle($entryPoint, $outputPath) {
        $startTime = microtime(true);
        
        // Build dependency graph
        $this->buildDependencyGraph($entryPoint);
        
        // Mark used exports
        $usedExports = $this->markUsedExports();
        
        // Generate optimized bundle
        $bundle = "// Tree-shaken bundle generated at " . date('Y-m-d H:i:s') . "\n\n";
        $includedModules = [];
        $totalOriginalSize = 0;
        $totalOptimizedSize = 0;
        
        // Process modules in dependency order
        foreach ($this->dependency_graph as $module => $deps) {
            $content = file_get_contents($module);
            $originalSize = strlen($content);
            $totalOriginalSize += $originalSize;
            
            // Skip modules with no used exports (unless they have side effects)
            $moduleUsedExports = $usedExports[$module] ?? [];
            $moduleSideEffects = $this->detectSideEffects($content);
            
            if (empty($moduleUsedExports) && !$moduleSideEffects) {
                continue;
            }
            
            // Remove unused exports
            if (!empty($moduleUsedExports) && !$moduleSideEffects) {
                $content = $this->removeUnusedExports($content, $moduleUsedExports);
            }
            
            $optimizedSize = strlen($content);
            $totalOptimizedSize += $optimizedSize;
            
            $bundle .= "// Module: " . basename($module) . "\n";
            $bundle .= $content . "\n\n";
            
            $includedModules[] = [
                'module' => $module,
                'original_size' => $originalSize,
                'optimized_size' => $optimizedSize,
                'used_exports' => $moduleUsedExports,
                'reduction' => round((($originalSize - $optimizedSize) / $originalSize) * 100, 2)
            ];
        }
        
        // Write bundle
        file_put_contents($outputPath, $bundle);
        
        $results = [
            'modules_analyzed' => count($this->dependency_graph),
            'modules_included' => count($includedModules),
            'total_original_size' => $totalOriginalSize,
            'total_optimized_size' => $totalOptimizedSize,
            'size_reduction' => round((($totalOriginalSize - $totalOptimizedSize) / $totalOriginalSize) * 100, 2),
            'processing_time' => microtime(true) - $startTime,
            'included_modules' => $includedModules
        ];
        
        $this->performance_monitor->recordMetric('tree_shaking', $results);
        
        return $results;
    }
    
    /**
     * Remove unused exports from module content
     */
    private function removeUnusedExports($content, $usedExports) {
        $lines = explode("\n", $content);
        $outputLines = [];
        $inUnusedExport = false;
        $bracketCount = 0;
        
        foreach ($lines as $line) {
            $trimmedLine = trim($line);
            
            // Check if this is an unused export
            if (preg_match('/export\s+(?:const|let|var|function|class)\s+([\w$]+)/', $line, $matches)) {
                if (!in_array($matches[1], $usedExports)) {
                    $inUnusedExport = true;
                    $bracketCount = substr_count($line, '{') - substr_count($line, '}');
                    continue;
                }
            }
            
            // Track brackets for multi-line exports
            if ($inUnusedExport) {
                $bracketCount += substr_count($line, '{') - substr_count($line, '}');
                if ($bracketCount <= 0) {
                    $inUnusedExport = false;
                }
                continue;
            }
            
            // Check for export statements
            if (preg_match('/export\s*\{([^}]+)\}/', $line, $matches)) {
                $exports = array_map('trim', explode(',', $matches[1]));
                $usedExportsInLine = [];
                
                foreach ($exports as $export) {
                    $exportName = $export;
                    if (strpos($export, ' as ') !== false) {
                        list($original, $alias) = array_map('trim', explode(' as ', $export));
                        $exportName = $alias;
                    }
                    
                    if (in_array($exportName, $usedExports)) {
                        $usedExportsInLine[] = $export;
                    }
                }
                
                if (empty($usedExportsInLine)) {
                    continue; // Remove entire export statement
                } else {
                    $line = str_replace($matches[1], implode(', ', $usedExportsInLine), $line);
                }
            }
            
            $outputLines[] = $line;
        }
        
        return implode("\n", $outputLines);
    }
    
    /**
     * Generate webpack sideEffects configuration
     */
    public function generateSideEffectsConfig($rootPath) {
        $sideEffectsMap = [];
        
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($rootPath)
        );
        
        foreach ($iterator as $file) {
            if ($file->isFile() && $file->getExtension() === 'js') {
                $relativePath = str_replace($rootPath . '/', '', $file->getPathname());
                $content = file_get_contents($file->getPathname());
                
                $hasSideEffects = $this->detectSideEffects($content);
                
                if (!$hasSideEffects) {
                    $sideEffectsMap[] = $relativePath;
                }
            }
        }
        
        return [
            'sideEffects' => $sideEffectsMap
        ];
    }
    
    /**
     * Generate rollup tree shaking config
     */
    public function generateRollupConfig() {
        return [
            'treeshake' => [
                'moduleSideEffects' => false,
                'propertyReadSideEffects' => false,
                'tryCatchDeoptimization' => false,
                'unknownGlobalSideEffects' => false,
                'correctVarValueBeforeDeclaration' => false,
                'annotations' => true,
                'preset' => 'recommended'
            ],
            'output' => [
                'compact' => true,
                'hoistTransitiveImports' => true,
                'minifyInternalExports' => true
            ],
            'external' => $this->config['external_modules'],
            'preserveModules' => false
        ];
    }
}

// Example usage
$treeShaker = new TreeShaker();

// Analyze a module
if (file_exists('src/index.js')) {
    $analysis = $treeShaker->analyzeModule('src/index.js');
    
    echo "Module Analysis:\n";
    echo "Exports: " . count($analysis['exports']) . "\n";
    echo "Imports: " . count($analysis['imports']) . "\n";
    echo "Has Side Effects: " . ($analysis['side_effects'] ? 'Yes' : 'No') . "\n";
}

// Generate tree-shaken bundle
if (file_exists('src/index.js')) {
    $results = $treeShaker->generateTreeShakenBundle('src/index.js', 'dist/bundle.tree-shaken.js');
    
    echo "\nTree Shaking Results:\n";
    echo "Modules Analyzed: {$results['modules_analyzed']}\n";
    echo "Modules Included: {$results['modules_included']}\n";
    echo "Original Size: " . number_format($results['total_original_size'] / 1024, 2) . " KB\n";
    echo "Optimized Size: " . number_format($results['total_optimized_size'] / 1024, 2) . " KB\n";
    echo "Size Reduction: {$results['size_reduction']}%\n";
}

// Generate configurations
$rollupConfig = $treeShaker->generateRollupConfig();
file_put_contents('rollup.treeshake.json', json_encode($rollupConfig, JSON_PRETTY_PRINT));

if (file_exists('src')) {
    $sideEffectsConfig = $treeShaker->generateSideEffectsConfig('src');
    file_put_contents('sideEffects.json', json_encode($sideEffectsConfig, JSON_PRETTY_PRINT));
}

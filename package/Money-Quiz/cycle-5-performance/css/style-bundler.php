<?php
/**
 * Smart CSS Bundling and Optimization
 * Intelligently bundles CSS files based on usage patterns
 */

class StyleBundler {
    private $config = [
        'bundle_strategy' => 'route', // route, component, or global
        'max_bundle_size' => 100000, // 100KB per bundle
        'inline_threshold' => 2000, // Inline if less than 2KB
        'async_load_threshold' => 50000, // Async load if more than 50KB
        'bundle_splitting' => [
            'critical' => ['reset', 'typography', 'layout', 'above-fold'],
            'vendor' => ['bootstrap', 'animate', 'fontawesome'],
            'components' => ['buttons', 'forms', 'cards', 'modals'],
            'themes' => ['light', 'dark', 'high-contrast']
        ],
        'optimization' => [
            'merge_media_queries' => true,
            'combine_selectors' => true,
            'remove_duplicates' => true,
            'optimize_fonts' => true
        ]
    ];
    
    private $performance_monitor;
    private $bundle_map = [];
    private $dependency_graph = [];
    private $usage_stats = [];
    
    public function __construct() {
        $this->performance_monitor = new PerformanceMonitor();
    }
    
    /**
     * Create optimized CSS bundles
     */
    public function createBundles($cssFiles, $outputDir) {
        $startTime = microtime(true);
        
        // Analyze CSS files
        $analysis = $this->analyzeCSSFiles($cssFiles);
        
        // Build dependency graph
        $this->buildDependencyGraph($analysis);
        
        // Generate bundles based on strategy
        $bundles = $this->generateBundles($analysis);
        
        // Optimize each bundle
        $optimizedBundles = [];
        foreach ($bundles as $bundleName => $bundleContent) {
            $optimized = $this->optimizeBundle($bundleContent);
            $optimizedBundles[$bundleName] = $optimized;
            
            // Write bundle to file
            $outputPath = $outputDir . '/' . $bundleName . '.css';
            file_put_contents($outputPath, $optimized['css']);
            
            // Generate compressed versions
            $this->generateCompressedVersions($outputPath, $optimized['css']);
        }
        
        // Generate bundle manifest
        $manifest = $this->generateManifest($optimizedBundles);
        file_put_contents($outputDir . '/bundle-manifest.json', json_encode($manifest, JSON_PRETTY_PRINT));
        
        $results = [
            'bundles_created' => count($optimizedBundles),
            'total_original_size' => array_sum(array_column($analysis, 'size')),
            'total_bundled_size' => array_sum(array_map(function($b) { return strlen($b['css']); }, $optimizedBundles)),
            'processing_time' => microtime(true) - $startTime,
            'manifest' => $manifest
        ];
        
        $this->performance_monitor->recordMetric('css_bundling', $results);
        
        return $results;
    }
    
    /**
     * Analyze CSS files for bundling
     */
    private function analyzeCSSFiles($cssFiles) {
        $analysis = [];
        
        foreach ($cssFiles as $file) {
            $content = file_get_contents($file);
            $parsed = $this->parseCSS($content);
            
            $analysis[$file] = [
                'size' => strlen($content),
                'rules' => count($parsed['rules']),
                'selectors' => $parsed['selectors'],
                'imports' => $parsed['imports'],
                'media_queries' => $parsed['media_queries'],
                'fonts' => $parsed['fonts'],
                'variables' => $parsed['variables'],
                'category' => $this->categorizeFile($file, $parsed)
            ];
        }
        
        return $analysis;
    }
    
    /**
     * Parse CSS content
     */
    private function parseCSS($content) {
        $parsed = [
            'rules' => [],
            'selectors' => [],
            'imports' => [],
            'media_queries' => [],
            'fonts' => [],
            'variables' => []
        ];
        
        // Remove comments
        $content = preg_replace('/\/\*[^*]*\*+([^\/*][^*]*\*+)*\//', '', $content);
        
        // Parse @import
        preg_match_all('/@import\s+(?:url\()?["\']([^"\']*)["\']/i', $content, $imports);
        $parsed['imports'] = $imports[1];
        
        // Parse @font-face
        preg_match_all('/@font-face\s*\{([^}]+)\}/s', $content, $fonts);
        foreach ($fonts[1] as $font) {
            preg_match('/font-family:\s*["\']?([^;"\']+)/', $font, $name);
            if (isset($name[1])) {
                $parsed['fonts'][] = trim($name[1]);
            }
        }
        
        // Parse CSS variables
        preg_match_all('/--([a-zA-Z0-9-]+):\s*([^;]+);/', $content, $vars);
        foreach ($vars[1] as $i => $varName) {
            $parsed['variables'][$varName] = trim($vars[2][$i]);
        }
        
        // Parse media queries
        preg_match_all('/@media([^{]+)\{/', $content, $mediaQueries);
        $parsed['media_queries'] = array_map('trim', $mediaQueries[1]);
        
        // Parse rules and selectors
        preg_match_all('/([^{]+)\{([^}]+)\}/s', $content, $rules);
        foreach ($rules[1] as $i => $selector) {
            $selector = trim($selector);
            if (strpos($selector, '@') !== 0) { // Skip at-rules
                $parsed['rules'][] = [
                    'selector' => $selector,
                    'declarations' => trim($rules[2][$i])
                ];
                
                // Extract individual selectors
                $selectors = preg_split('/\s*,\s*/', $selector);
                $parsed['selectors'] = array_merge($parsed['selectors'], $selectors);
            }
        }
        
        $parsed['selectors'] = array_unique($parsed['selectors']);
        
        return $parsed;
    }
    
    /**
     * Categorize CSS file based on content
     */
    private function categorizeFile($filename, $parsed) {
        $basename = basename($filename, '.css');
        
        // Check bundle splitting config
        foreach ($this->config['bundle_splitting'] as $category => $patterns) {
            foreach ($patterns as $pattern) {
                if (stripos($basename, $pattern) !== false) {
                    return $category;
                }
            }
        }
        
        // Analyze content for categorization
        if (count($parsed['fonts']) > 0) {
            return 'fonts';
        }
        
        if (count($parsed['variables']) > 20) {
            return 'variables';
        }
        
        if (count($parsed['media_queries']) > 5) {
            return 'responsive';
        }
        
        // Default category based on selectors
        $componentSelectors = array_filter($parsed['selectors'], function($sel) {
            return strpos($sel, '.') === 0 && !strpos($sel, ' ');
        });
        
        if (count($componentSelectors) > count($parsed['selectors']) * 0.7) {
            return 'components';
        }
        
        return 'global';
    }
    
    /**
     * Build dependency graph
     */
    private function buildDependencyGraph($analysis) {
        foreach ($analysis as $file => $data) {
            $this->dependency_graph[$file] = [];
            
            // Add import dependencies
            foreach ($data['imports'] as $import) {
                $importPath = $this->resolveImportPath($import, dirname($file));
                if ($importPath && isset($analysis[$importPath])) {
                    $this->dependency_graph[$file][] = $importPath;
                }
            }
        }
    }
    
    /**
     * Resolve import path
     */
    private function resolveImportPath($import, $basePath) {
        // Remove url() wrapper if present
        $import = preg_replace('/^url\(["\']?([^"\')]+)["\']?\)$/', '$1', $import);
        
        // Handle relative paths
        if (strpos($import, './') === 0 || strpos($import, '../') === 0) {
            $resolved = realpath($basePath . '/' . $import);
            if ($resolved && file_exists($resolved)) {
                return $resolved;
            }
        }
        
        // Try with .css extension
        if (!preg_match('/\.css$/i', $import)) {
            return $this->resolveImportPath($import . '.css', $basePath);
        }
        
        return null;
    }
    
    /**
     * Generate bundles based on strategy
     */
    private function generateBundles($analysis) {
        switch ($this->config['bundle_strategy']) {
            case 'route':
                return $this->generateRouteBundles($analysis);
            case 'component':
                return $this->generateComponentBundles($analysis);
            case 'global':
                return $this->generateGlobalBundle($analysis);
            default:
                return $this->generateSmartBundles($analysis);
        }
    }
    
    /**
     * Generate route-based bundles
     */
    private function generateRouteBundles($analysis) {
        $bundles = [];
        
        // Group by category first
        $categories = [];
        foreach ($analysis as $file => $data) {
            $categories[$data['category']][] = $file;
        }
        
        // Create bundles for each category
        foreach ($categories as $category => $files) {
            $bundleContent = '';
            $bundleSize = 0;
            $bundleIndex = 1;
            
            foreach ($files as $file) {
                $content = file_get_contents($file);
                $size = strlen($content);
                
                // Check if adding this file would exceed max bundle size
                if ($bundleSize + $size > $this->config['max_bundle_size'] && $bundleSize > 0) {
                    $bundles["{$category}-{$bundleIndex}"] = $bundleContent;
                    $bundleIndex++;
                    $bundleContent = '';
                    $bundleSize = 0;
                }
                
                $bundleContent .= "\n/* Source: $file */\n" . $content;
                $bundleSize += $size;
            }
            
            if ($bundleContent) {
                $bundleName = $bundleIndex > 1 ? "{$category}-{$bundleIndex}" : $category;
                $bundles[$bundleName] = $bundleContent;
            }
        }
        
        return $bundles;
    }
    
    /**
     * Generate component-based bundles
     */
    private function generateComponentBundles($analysis) {
        $bundles = [];
        
        // Group components by similarity
        $components = [];
        foreach ($analysis as $file => $data) {
            if ($data['category'] === 'components') {
                $componentName = $this->extractComponentName($file, $data);
                $components[$componentName][] = $file;
            }
        }
        
        // Create bundle for each component group
        foreach ($components as $component => $files) {
            $bundleContent = '';
            foreach ($files as $file) {
                $bundleContent .= "\n/* Component: $component - $file */\n";
                $bundleContent .= file_get_contents($file);
            }
            $bundles["component-{$component}"] = $bundleContent;
        }
        
        // Add non-component files to separate bundles
        foreach ($analysis as $file => $data) {
            if ($data['category'] !== 'components') {
                $bundles[$data['category']] = ($bundles[$data['category']] ?? '') . file_get_contents($file);
            }
        }
        
        return $bundles;
    }
    
    /**
     * Generate single global bundle
     */
    private function generateGlobalBundle($analysis) {
        $content = '';
        
        // Sort files by dependency order
        $sortedFiles = $this->topologicalSort(array_keys($analysis));
        
        foreach ($sortedFiles as $file) {
            $content .= "\n/* Source: $file */\n";
            $content .= file_get_contents($file);
        }
        
        return ['global' => $content];
    }
    
    /**
     * Generate smart bundles based on usage patterns
     */
    private function generateSmartBundles($analysis) {
        $bundles = [];
        
        // Critical CSS bundle
        $criticalBundle = '';
        foreach ($analysis as $file => $data) {
            if ($data['category'] === 'critical' || $data['size'] < $this->config['inline_threshold']) {
                $criticalBundle .= file_get_contents($file);
            }
        }
        if ($criticalBundle) {
            $bundles['critical'] = $criticalBundle;
        }
        
        // Vendor bundle
        $vendorBundle = '';
        foreach ($analysis as $file => $data) {
            if ($data['category'] === 'vendor') {
                $vendorBundle .= file_get_contents($file);
            }
        }
        if ($vendorBundle) {
            $bundles['vendor'] = $vendorBundle;
        }
        
        // Main bundle (everything else)
        $mainBundle = '';
        foreach ($analysis as $file => $data) {
            if (!in_array($data['category'], ['critical', 'vendor'])) {
                $mainBundle .= file_get_contents($file);
            }
        }
        if ($mainBundle) {
            $bundles['main'] = $mainBundle;
        }
        
        return $bundles;
    }
    
    /**
     * Extract component name from file
     */
    private function extractComponentName($file, $data) {
        // Try to extract from filename
        $basename = basename($file, '.css');
        
        // Remove common prefixes/suffixes
        $basename = preg_replace('/^(component-|comp-|c-)/', '', $basename);
        $basename = preg_replace('/(-component|-comp|-styles?)$/', '', $basename);
        
        // Try to extract from main class names
        $mainClasses = array_filter($data['selectors'], function($sel) {
            return preg_match('/^\.[a-zA-Z][a-zA-Z0-9-]*$/', $sel);
        });
        
        if (!empty($mainClasses)) {
            $mainClass = reset($mainClasses);
            return trim($mainClass, '.');
        }
        
        return $basename;
    }
    
    /**
     * Topological sort for dependency ordering
     */
    private function topologicalSort($files) {
        $sorted = [];
        $visited = [];
        
        foreach ($files as $file) {
            $this->topologicalSortVisit($file, $visited, $sorted);
        }
        
        return $sorted;
    }
    
    private function topologicalSortVisit($file, &$visited, &$sorted) {
        if (isset($visited[$file])) {
            return;
        }
        
        $visited[$file] = true;
        
        if (isset($this->dependency_graph[$file])) {
            foreach ($this->dependency_graph[$file] as $dep) {
                $this->topologicalSortVisit($dep, $visited, $sorted);
            }
        }
        
        $sorted[] = $file;
    }
    
    /**
     * Optimize bundle content
     */
    private function optimizeBundle($content) {
        $original = $content;
        
        if ($this->config['optimization']['remove_duplicates']) {
            $content = $this->removeDuplicateRules($content);
        }
        
        if ($this->config['optimization']['merge_media_queries']) {
            $content = $this->mergeMediaQueries($content);
        }
        
        if ($this->config['optimization']['combine_selectors']) {
            $content = $this->combineSelectors($content);
        }
        
        if ($this->config['optimization']['optimize_fonts']) {
            $content = $this->optimizeFontDeclarations($content);
        }
        
        // Minify
        $content = $this->minifyCSS($content);
        
        return [
            'css' => $content,
            'original_size' => strlen($original),
            'optimized_size' => strlen($content),
            'reduction' => round((1 - strlen($content) / strlen($original)) * 100, 2)
        ];
    }
    
    /**
     * Remove duplicate CSS rules
     */
    private function removeDuplicateRules($css) {
        $rules = [];
        preg_match_all('/([^{]+)\{([^}]+)\}/s', $css, $matches, PREG_SET_ORDER);
        
        $uniqueRules = [];
        foreach (array_reverse($matches) as $match) {
            $selector = trim($match[1]);
            $declarations = trim($match[2]);
            $key = $selector . '{' . $declarations . '}';
            
            if (!isset($uniqueRules[$key])) {
                $uniqueRules[$key] = $match[0];
            }
        }
        
        return implode("\n", array_reverse($uniqueRules));
    }
    
    /**
     * Merge media queries
     */
    private function mergeMediaQueries($css) {
        $mediaQueries = [];
        
        // Extract media queries
        preg_match_all('/@media([^{]+)\{([^{}]*(?:\{[^}]*\}[^{}]*)*)\}/s', $css, $matches, PREG_SET_ORDER);
        
        foreach ($matches as $match) {
            $query = trim($match[1]);
            $content = trim($match[2]);
            
            if (!isset($mediaQueries[$query])) {
                $mediaQueries[$query] = [];
            }
            $mediaQueries[$query][] = $content;
        }
        
        // Remove original media queries
        $css = preg_replace('/@media[^{]+\{[^{}]*(?:\{[^}]*\}[^{}]*)*\}/s', '', $css);
        
        // Add merged media queries at the end
        foreach ($mediaQueries as $query => $contents) {
            $css .= "\n@media $query {" . implode("\n", $contents) . "}";
        }
        
        return $css;
    }
    
    /**
     * Combine selectors with same declarations
     */
    private function combineSelectors($css) {
        $rules = [];
        preg_match_all('/([^{]+)\{([^}]+)\}/s', $css, $matches, PREG_SET_ORDER);
        
        $declarationMap = [];
        foreach ($matches as $match) {
            $selector = trim($match[1]);
            $declarations = trim($match[2]);
            
            if (!isset($declarationMap[$declarations])) {
                $declarationMap[$declarations] = [];
            }
            $declarationMap[$declarations][] = $selector;
        }
        
        $combined = '';
        foreach ($declarationMap as $declarations => $selectors) {
            $combined .= implode(',', $selectors) . '{' . $declarations . '}';
        }
        
        return $combined;
    }
    
    /**
     * Optimize font declarations
     */
    private function optimizeFontDeclarations($css) {
        // Combine font properties into shorthand
        $css = preg_replace_callback(
            '/([^{]+)\{([^}]*?)font-style:\s*([^;]+);[^}]*?font-weight:\s*([^;]+);[^}]*?font-size:\s*([^;]+);[^}]*?line-height:\s*([^;]+);[^}]*?font-family:\s*([^;]+);([^}]*)\}/',
            function($matches) {
                $selector = $matches[1];
                $before = $matches[2];
                $style = trim($matches[3]);
                $weight = trim($matches[4]);
                $size = trim($matches[5]);
                $lineHeight = trim($matches[6]);
                $family = trim($matches[7]);
                $after = $matches[8];
                
                // Remove individual properties
                $before = preg_replace('/font-(style|weight|size|family):[^;]+;?/', '', $before);
                $after = preg_replace('/font-(style|weight|size|family):[^;]+;?/', '', $after);
                $after = preg_replace('/line-height:[^;]+;?/', '', $after);
                
                return $selector . '{' . $before . "font:$style $weight $size/$lineHeight $family;" . $after . '}';
            },
            $css
        );
        
        return $css;
    }
    
    /**
     * Minify CSS
     */
    private function minifyCSS($css) {
        // Remove comments
        $css = preg_replace('/\/\*[^*]*\*+([^\/*][^*]*\*+)*\//', '', $css);
        
        // Remove unnecessary whitespace
        $css = preg_replace('/\s+/', ' ', $css);
        $css = preg_replace('/\s*([{}:;,>+~])\s*/', '$1', $css);
        
        // Remove last semicolon before closing brace
        $css = preg_replace('/;}/', '}', $css);
        
        // Remove empty rules
        $css = preg_replace('/[^{}]+\{\s*\}/', '', $css);
        
        return trim($css);
    }
    
    /**
     * Generate compressed versions
     */
    private function generateCompressedVersions($filepath, $content) {
        // Gzip
        $gzipContent = gzencode($content, 9);
        file_put_contents($filepath . '.gz', $gzipContent);
        
        // Brotli (if available)
        if (function_exists('brotli_compress')) {
            $brotliContent = brotli_compress($content, 11);
            file_put_contents($filepath . '.br', $brotliContent);
        }
    }
    
    /**
     * Generate bundle manifest
     */
    private function generateManifest($bundles) {
        $manifest = [
            'version' => date('Y-m-d H:i:s'),
            'bundles' => []
        ];
        
        foreach ($bundles as $name => $data) {
            $manifest['bundles'][$name] = [
                'size' => $data['optimized_size'],
                'original_size' => $data['original_size'],
                'reduction' => $data['reduction'] . '%',
                'load_strategy' => $this->determineLoadStrategy($name, $data),
                'dependencies' => $this->bundle_map[$name]['dependencies'] ?? [],
                'hash' => substr(md5($data['css']), 0, 8)
            ];
        }
        
        return $manifest;
    }
    
    /**
     * Determine load strategy for bundle
     */
    private function determineLoadStrategy($name, $data) {
        if ($name === 'critical' || strpos($name, 'above-fold') !== false) {
            return 'inline';
        }
        
        if ($data['optimized_size'] < $this->config['inline_threshold']) {
            return 'inline';
        }
        
        if ($data['optimized_size'] > $this->config['async_load_threshold']) {
            return 'async';
        }
        
        if ($name === 'vendor' || strpos($name, 'theme') !== false) {
            return 'preload';
        }
        
        return 'normal';
    }
    
    /**
     * Generate HTML loader for bundles
     */
    public function generateLoader($manifest) {
        $html = "<!-- CSS Bundle Loader -->\n";
        
        foreach ($manifest['bundles'] as $name => $bundle) {
            $href = "/css/bundles/{$name}.css?v={$bundle['hash']}";
            
            switch ($bundle['load_strategy']) {
                case 'inline':
                    $css = file_get_contents("css/bundles/{$name}.css");
                    $html .= "<style id=\"bundle-{$name}\">\n{$css}\n</style>\n";
                    break;
                    
                case 'async':
                    $html .= "<link rel=\"preload\" href=\"{$href}\" as=\"style\" onload=\"this.onload=null;this.rel='stylesheet'\">\n";
                    $html .= "<noscript><link rel=\"stylesheet\" href=\"{$href}\"></noscript>\n";
                    break;
                    
                case 'preload':
                    $html .= "<link rel=\"preload\" href=\"{$href}\" as=\"style\">\n";
                    $html .= "<link rel=\"stylesheet\" href=\"{$href}\">\n";
                    break;
                    
                default:
                    $html .= "<link rel=\"stylesheet\" href=\"{$href}\">\n";
            }
        }
        
        return $html;
    }
}

// Example usage
$bundler = new StyleBundler();

// Configure bundling
$bundler->config['bundle_strategy'] = 'smart';

// Find CSS files
$cssFiles = glob('css/*.css');

if (!empty($cssFiles)) {
    // Create output directory
    $outputDir = 'css/bundles';
    if (!is_dir($outputDir)) {
        mkdir($outputDir, 0777, true);
    }
    
    // Create bundles
    $results = $bundler->createBundles($cssFiles, $outputDir);
    
    echo "CSS Bundling Results:\n";
    echo "Bundles Created: {$results['bundles_created']}\n";
    echo "Original Size: " . number_format($results['total_original_size'] / 1024, 2) . " KB\n";
    echo "Bundled Size: " . number_format($results['total_bundled_size'] / 1024, 2) . " KB\n";
    echo "Processing Time: " . round($results['processing_time'], 2) . "s\n\n";
    
    echo "Bundle Details:\n";
    foreach ($results['manifest']['bundles'] as $name => $bundle) {
        echo "- $name: " . number_format($bundle['size'] / 1024, 2) . " KB ({$bundle['reduction']} reduction, {$bundle['load_strategy']} loading)\n";
    }
    
    // Generate loader HTML
    $loaderHTML = $bundler->generateLoader($results['manifest']);
    file_put_contents('css-bundle-loader.html', $loaderHTML);
}

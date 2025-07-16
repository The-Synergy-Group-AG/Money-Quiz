<?php
/**
 * Unused CSS Remover - PurgeCSS Implementation
 * Removes unused CSS rules to reduce file size
 */

class UnusedCSSRemover {
    private $config = [
        'content_extensions' => ['html', 'php', 'js', 'jsx', 'ts', 'tsx', 'vue'],
        'css_extensions' => ['css', 'scss', 'sass', 'less'],
        'safelist' => [
            // Always keep these selectors
            'html', 'body', ':root',
            '/^::-/', // Pseudo-elements
            '/^\[data-/', // Data attributes
            '/^aria-/', // Accessibility
            'is-active', 'is-open', 'is-visible',
            'error', 'success', 'warning'
        ],
        'blocklist' => [
            // Remove these selectors
            '/\.todo-/',
            '/\.deprecated-/'
        ],
        'extract_patterns' => [
            // Patterns to extract from content files
            'html' => '/<[^>]*class=["\'][^"\']*/gi',
            'js' => '/(?:className|classList)[\s]*[=\.][\s]*["\'][^"\']*/gi',
            'dynamic' => '/(?:add|remove|toggle)Class\(["\'][^"\']*/gi'
        ],
        'keyframes' => true,
        'font_face' => true,
        'variables' => true
    ];
    
    private $performance_monitor;
    private $used_selectors = [];
    private $used_keyframes = [];
    private $used_fonts = [];
    private $stats = [];
    
    public function __construct() {
        $this->performance_monitor = new PerformanceMonitor();
    }
    
    /**
     * Remove unused CSS from files
     */
    public function purgeCSS($cssPath, $contentPaths, $outputPath = null) {
        $startTime = microtime(true);
        
        // Extract used selectors from content
        $this->extractUsedSelectors($contentPaths);
        
        // Process CSS file
        $originalCSS = file_get_contents($cssPath);
        $originalSize = strlen($originalCSS);
        
        $purgedCSS = $this->processCSSContent($originalCSS);
        $purgedSize = strlen($purgedCSS);
        
        // Save purged CSS
        if (!$outputPath) {
            $outputPath = str_replace('.css', '.purged.css', $cssPath);
        }
        file_put_contents($outputPath, $purgedCSS);
        
        // Calculate statistics
        $this->stats = [
            'original_size' => $originalSize,
            'purged_size' => $purgedSize,
            'removed_size' => $originalSize - $purgedSize,
            'reduction_percentage' => round((($originalSize - $purgedSize) / $originalSize) * 100, 2),
            'selectors_found' => count($this->used_selectors),
            'processing_time' => microtime(true) - $startTime,
            'removed_rules' => $this->stats['removed_rules'] ?? 0,
            'kept_rules' => $this->stats['kept_rules'] ?? 0
        ];
        
        $this->performance_monitor->recordMetric('css_purge', $this->stats);
        
        return $this->stats;
    }
    
    /**
     * Extract used selectors from content files
     */
    private function extractUsedSelectors($contentPaths) {
        foreach ($contentPaths as $path) {
            if (is_dir($path)) {
                $this->extractFromDirectory($path);
            } else {
                $this->extractFromFile($path);
            }
        }
        
        // Add safelist selectors
        foreach ($this->config['safelist'] as $safe) {
            if (strpos($safe, '/') === 0) {
                // It's a regex pattern
                $this->used_selectors['regex'][] = $safe;
            } else {
                $this->used_selectors['exact'][] = $safe;
            }
        }
    }
    
    /**
     * Extract selectors from directory
     */
    private function extractFromDirectory($directory) {
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($directory)
        );
        
        foreach ($iterator as $file) {
            if ($file->isFile() && in_array($file->getExtension(), $this->config['content_extensions'])) {
                $this->extractFromFile($file->getPathname());
            }
        }
    }
    
    /**
     * Extract selectors from file
     */
    private function extractFromFile($filePath) {
        $content = file_get_contents($filePath);
        $extension = pathinfo($filePath, PATHINFO_EXTENSION);
        
        // Extract class names
        $this->extractClasses($content, $extension);
        
        // Extract IDs
        $this->extractIds($content);
        
        // Extract tag names
        $this->extractTags($content);
        
        // Extract attribute selectors
        $this->extractAttributes($content);
        
        // Extract keyframe and font names
        $this->extractSpecialRules($content);
    }
    
    /**
     * Extract class names from content
     */
    private function extractClasses($content, $extension) {
        $patterns = [];
        
        // HTML class attributes
        if (in_array($extension, ['html', 'php', 'vue'])) {
            $patterns[] = '/class=["\']([^"\']*)["\']/i';
        }
        
        // JavaScript className
        if (in_array($extension, ['js', 'jsx', 'ts', 'tsx'])) {
            $patterns[] = '/className=["\']([^"\']*)["\']/i';
            $patterns[] = '/classList\.(?:add|remove|toggle)\(["\']([^"\']*)["\']/i';
            $patterns[] = '/\bclass:\s*["\']([^"\']*)["\']/i';
        }
        
        // Dynamic class generation
        $patterns[] = '/["\']([\w-]+(?:\s+[\w-]+)*)["\']/i';
        
        foreach ($patterns as $pattern) {
            preg_match_all($pattern, $content, $matches);
            
            foreach ($matches[1] as $classString) {
                $classes = preg_split('/\s+/', trim($classString));
                foreach ($classes as $class) {
                    if ($class && $this->isValidClassName($class)) {
                        $this->used_selectors['classes'][$class] = true;
                    }
                }
            }
        }
    }
    
    /**
     * Extract IDs from content
     */
    private function extractIds($content) {
        // HTML id attributes
        preg_match_all('/id=["\']([^"\']*)["\']/i', $content, $matches);
        foreach ($matches[1] as $id) {
            if ($id && $this->isValidId($id)) {
                $this->used_selectors['ids'][$id] = true;
            }
        }
        
        // JavaScript getElementById
        preg_match_all('/getElementById\(["\']([^"\']*)["\']/i', $content, $matches);
        foreach ($matches[1] as $id) {
            if ($id && $this->isValidId($id)) {
                $this->used_selectors['ids'][$id] = true;
            }
        }
    }
    
    /**
     * Extract tag names from content
     */
    private function extractTags($content) {
        // HTML tags
        preg_match_all('/<([a-zA-Z][a-zA-Z0-9-]*)/', $content, $matches);
        foreach ($matches[1] as $tag) {
            $this->used_selectors['tags'][strtolower($tag)] = true;
        }
        
        // Common semantic tags
        $semanticTags = ['header', 'nav', 'main', 'article', 'section', 'aside', 'footer'];
        foreach ($semanticTags as $tag) {
            if (stripos($content, $tag) !== false) {
                $this->used_selectors['tags'][$tag] = true;
            }
        }
    }
    
    /**
     * Extract attribute selectors
     */
    private function extractAttributes($content) {
        // Data attributes
        preg_match_all('/data-([a-zA-Z][a-zA-Z0-9-]*)=["\']/', $content, $matches);
        foreach ($matches[1] as $attr) {
            $this->used_selectors['attributes']['data-' . $attr] = true;
        }
        
        // ARIA attributes
        preg_match_all('/aria-([a-zA-Z][a-zA-Z0-9-]*)=["\']/', $content, $matches);
        foreach ($matches[1] as $attr) {
            $this->used_selectors['attributes']['aria-' . $attr] = true;
        }
    }
    
    /**
     * Extract special rules (keyframes, fonts)
     */
    private function extractSpecialRules($content) {
        // Animation names
        preg_match_all('/animation(?:-name)?:\s*([\w-]+)/', $content, $matches);
        foreach ($matches[1] as $animation) {
            $this->used_keyframes[$animation] = true;
        }
        
        // Font family names
        preg_match_all('/font-family:\s*["\']?([^;"\']+)/', $content, $matches);
        foreach ($matches[1] as $font) {
            $this->used_fonts[trim($font)] = true;
        }
    }
    
    /**
     * Check if class name is valid
     */
    private function isValidClassName($class) {
        return preg_match('/^[a-zA-Z_-][a-zA-Z0-9_-]*$/', $class) && 
               strlen($class) < 100 && 
               !is_numeric($class);
    }
    
    /**
     * Check if ID is valid
     */
    private function isValidId($id) {
        return preg_match('/^[a-zA-Z][a-zA-Z0-9_-]*$/', $id) && 
               strlen($id) < 100;
    }
    
    /**
     * Process CSS content and remove unused rules
     */
    private function processCSSContent($css) {
        // Remove comments first
        $css = preg_replace('/\/\*[^*]*\*+([^\/*][^*]*\*+)*\//', '', $css);
        
        $output = '';
        $this->stats['removed_rules'] = 0;
        $this->stats['kept_rules'] = 0;
        
        // Process @rules
        $css = $this->processAtRules($css, $output);
        
        // Process regular rules
        preg_match_all('/([^{]+)\{([^}]+)\}/s', $css, $matches, PREG_SET_ORDER);
        
        foreach ($matches as $match) {
            $selector = trim($match[1]);
            $declarations = trim($match[2]);
            
            if ($this->shouldKeepRule($selector)) {
                $output .= $selector . '{' . $declarations . '}';
                $this->stats['kept_rules']++;
            } else {
                $this->stats['removed_rules']++;
            }
        }
        
        return $this->optimizeOutput($output);
    }
    
    /**
     * Process @rules (media queries, keyframes, etc.)
     */
    private function processAtRules($css, &$output) {
        // Process @font-face
        if ($this->config['font_face']) {
            preg_match_all('/@font-face\s*\{([^}]+)\}/s', $css, $fontFaces);
            foreach ($fontFaces[0] as $fontFace) {
                if ($this->shouldKeepFontFace($fontFace)) {
                    $output .= $fontFace;
                }
            }
        }
        
        // Process @keyframes
        if ($this->config['keyframes']) {
            preg_match_all('/@(?:-webkit-|-moz-|-o-)?keyframes\s+([\w-]+)\s*\{([^{}]*(?:\{[^}]*\}[^{}]*)*)\}/s', $css, $keyframes, PREG_SET_ORDER);
            foreach ($keyframes as $keyframe) {
                $name = $keyframe[1];
                if (isset($this->used_keyframes[$name])) {
                    $output .= $keyframe[0];
                }
            }
        }
        
        // Process @media queries
        preg_match_all('/@media[^{]+\{([^{}]*(?:\{[^}]*\}[^{}]*)*)\}/s', $css, $mediaQueries, PREG_SET_ORDER);
        foreach ($mediaQueries as $mq) {
            $mediaContent = $this->processCSSContent($mq[1]);
            if (trim($mediaContent)) {
                $output .= str_replace($mq[1], $mediaContent, $mq[0]);
            }
        }
        
        // Process CSS variables
        if ($this->config['variables']) {
            preg_match_all('/:root\s*\{([^}]+)\}/', $css, $roots);
            foreach ($roots[0] as $root) {
                $output .= $root;
            }
        }
        
        // Remove processed @rules from CSS
        $css = preg_replace('/@font-face\s*\{[^}]+\}/s', '', $css);
        $css = preg_replace('/@(?:-webkit-|-moz-|-o-)?keyframes\s+[\w-]+\s*\{[^{}]*(?:\{[^}]*\}[^{}]*)*\}/s', '', $css);
        $css = preg_replace('/@media[^{]+\{[^{}]*(?:\{[^}]*\}[^{}]*)*\}/s', '', $css);
        $css = preg_replace('/:root\s*\{[^}]+\}/', '', $css);
        
        return $css;
    }
    
    /**
     * Check if font-face should be kept
     */
    private function shouldKeepFontFace($fontFace) {
        preg_match('/font-family:\s*["\']?([^;"\']+)/', $fontFace, $match);
        
        if (isset($match[1])) {
            $fontName = trim($match[1]);
            return isset($this->used_fonts[$fontName]);
        }
        
        return false;
    }
    
    /**
     * Check if rule should be kept
     */
    private function shouldKeepRule($selector) {
        // Check blocklist first
        foreach ($this->config['blocklist'] as $blocked) {
            if (strpos($blocked, '/') === 0) {
                // Regex pattern
                $pattern = substr($blocked, 1, -1);
                if (preg_match($pattern, $selector)) {
                    return false;
                }
            } elseif (strpos($selector, $blocked) !== false) {
                return false;
            }
        }
        
        // Split complex selectors
        $selectors = preg_split('/\s*,\s*/', $selector);
        
        foreach ($selectors as $sel) {
            if ($this->isSelectorUsed($sel)) {
                return true;
            }
        }
        
        return false;
    }
    
    /**
     * Check if individual selector is used
     */
    private function isSelectorUsed($selector) {
        // Check safelist patterns
        foreach ($this->used_selectors['regex'] ?? [] as $pattern) {
            $regex = substr($pattern, 1, -1);
            if (preg_match($regex, $selector)) {
                return true;
            }
        }
        
        // Parse selector
        $parts = $this->parseSelector($selector);
        
        // Check each part
        foreach ($parts as $part) {
            if ($part['type'] === 'class' && isset($this->used_selectors['classes'][$part['value']])) {
                return true;
            }
            if ($part['type'] === 'id' && isset($this->used_selectors['ids'][$part['value']])) {
                return true;
            }
            if ($part['type'] === 'tag' && isset($this->used_selectors['tags'][$part['value']])) {
                return true;
            }
            if ($part['type'] === 'attribute' && isset($this->used_selectors['attributes'][$part['value']])) {
                return true;
            }
        }
        
        // Check pseudo-elements and pseudo-classes
        if (preg_match('/::?[a-z-]+/', $selector)) {
            // Keep common pseudo-selectors if base selector is used
            $baseSelector = preg_replace('/::?[a-z-]+.*$/', '', $selector);
            if ($baseSelector && $this->isSelectorUsed($baseSelector)) {
                return true;
            }
        }
        
        return false;
    }
    
    /**
     * Parse selector into components
     */
    private function parseSelector($selector) {
        $parts = [];
        
        // Remove pseudo-elements/classes for parsing
        $cleanSelector = preg_replace('/::?[a-z-]+(?:\([^)]*\))?/', '', $selector);
        
        // Extract classes
        preg_match_all('/\.([a-zA-Z0-9_-]+)/', $cleanSelector, $classes);
        foreach ($classes[1] as $class) {
            $parts[] = ['type' => 'class', 'value' => $class];
        }
        
        // Extract IDs
        preg_match_all('/#([a-zA-Z0-9_-]+)/', $cleanSelector, $ids);
        foreach ($ids[1] as $id) {
            $parts[] = ['type' => 'id', 'value' => $id];
        }
        
        // Extract attributes
        preg_match_all('/\[([a-zA-Z0-9_-]+)/', $cleanSelector, $attrs);
        foreach ($attrs[1] as $attr) {
            $parts[] = ['type' => 'attribute', 'value' => $attr];
        }
        
        // Extract tag if no other identifiers
        if (empty($parts)) {
            preg_match('/^([a-zA-Z][a-zA-Z0-9-]*)/', $cleanSelector, $tag);
            if (isset($tag[1])) {
                $parts[] = ['type' => 'tag', 'value' => strtolower($tag[1])];
            }
        }
        
        return $parts;
    }
    
    /**
     * Optimize output CSS
     */
    private function optimizeOutput($css) {
        // Remove empty rules
        $css = preg_replace('/[^{}]+\{\s*\}/', '', $css);
        
        // Minify
        $css = preg_replace('/\s+/', ' ', $css);
        $css = preg_replace('/\s*([{}:;,])\s*/', '$1', $css);
        
        // Remove duplicate rules
        $rules = [];
        preg_match_all('/([^{]+)\{([^}]+)\}/', $css, $matches, PREG_SET_ORDER);
        
        $uniqueRules = [];
        foreach (array_reverse($matches) as $match) {
            $key = $match[1] . $match[2];
            if (!isset($uniqueRules[$key])) {
                $uniqueRules[$key] = $match[0];
            }
        }
        
        return implode('', array_reverse($uniqueRules));
    }
    
    /**
     * Generate report of removed CSS
     */
    public function generateReport() {
        return [
            'summary' => [
                'original_size' => $this->formatBytes($this->stats['original_size']),
                'purged_size' => $this->formatBytes($this->stats['purged_size']),
                'removed_size' => $this->formatBytes($this->stats['removed_size']),
                'reduction' => $this->stats['reduction_percentage'] . '%',
                'processing_time' => round($this->stats['processing_time'], 2) . 's'
            ],
            'details' => [
                'total_rules' => $this->stats['kept_rules'] + $this->stats['removed_rules'],
                'kept_rules' => $this->stats['kept_rules'],
                'removed_rules' => $this->stats['removed_rules'],
                'selectors_found' => $this->stats['selectors_found']
            ],
            'selectors' => [
                'classes' => count($this->used_selectors['classes'] ?? []),
                'ids' => count($this->used_selectors['ids'] ?? []),
                'tags' => count($this->used_selectors['tags'] ?? []),
                'attributes' => count($this->used_selectors['attributes'] ?? [])
            ]
        ];
    }
    
    /**
     * Format bytes to human readable
     */
    private function formatBytes($bytes) {
        $units = ['B', 'KB', 'MB'];
        $i = 0;
        
        while ($bytes >= 1024 && $i < count($units) - 1) {
            $bytes /= 1024;
            $i++;
        }
        
        return round($bytes, 2) . ' ' . $units[$i];
    }
}

// Example usage
$remover = new UnusedCSSRemover();

// Configure custom safelist
$remover->config['safelist'][] = 'my-custom-class';
$remover->config['safelist'][] = '/^animate-/';

// Purge CSS
if (file_exists('css/main.css')) {
    $contentPaths = ['./src', './templates', './index.html'];
    
    $stats = $remover->purgeCSS('css/main.css', $contentPaths, 'css/main.purged.css');
    
    echo "PurgeCSS Results:\n";
    echo "Original Size: " . number_format($stats['original_size'] / 1024, 2) . " KB\n";
    echo "Purged Size: " . number_format($stats['purged_size'] / 1024, 2) . " KB\n";
    echo "Size Reduction: {$stats['reduction_percentage']}%\n";
    echo "Rules Removed: {$stats['removed_rules']}\n";
    echo "Processing Time: " . round($stats['processing_time'], 2) . "s\n";
    
    // Generate detailed report
    $report = $remover->generateReport();
    file_put_contents('purgecss-report.json', json_encode($report, JSON_PRETTY_PRINT));
}

<?php
/**
 * Critical CSS Extractor for Above-the-Fold Content
 * Extracts and inlines critical CSS for faster initial render
 */

class CriticalCSSExtractor {
    private $config = [
        'viewport' => [
            'width' => 1366,
            'height' => 768
        ],
        'include_selectors' => [
            'body', 'html', ':root',
            'header', 'nav', '.hero',
            '.above-fold', '[data-critical]'
        ],
        'exclude_selectors' => [
            '.lazy-load', '[data-lazy]',
            '.below-fold', '.footer'
        ],
        'max_critical_size' => 50000, // 50KB max for critical CSS
        'fonts_strategy' => 'preload', // preload, inline, or swap
        'extract_media_queries' => true
    ];
    
    private $performance_monitor;
    private $critical_css = '';
    private $remaining_css = '';
    
    public function __construct() {
        $this->performance_monitor = new PerformanceMonitor();
    }
    
    /**
     * Extract critical CSS from HTML and CSS files
     */
    public function extractCritical($htmlPath, $cssPath) {
        $startTime = microtime(true);
        
        // Parse HTML to find above-the-fold elements
        $criticalSelectors = $this->identifyCriticalSelectors($htmlPath);
        
        // Parse CSS and extract matching rules
        $css = file_get_contents($cssPath);
        $this->processCSSRules($css, $criticalSelectors);
        
        // Optimize critical CSS
        $this->critical_css = $this->optimizeCriticalCSS($this->critical_css);
        
        // Ensure critical CSS size limit
        if (strlen($this->critical_css) > $this->config['max_critical_size']) {
            $this->critical_css = $this->reduceCriticalSize($this->critical_css);
        }
        
        $results = [
            'critical_size' => strlen($this->critical_css),
            'remaining_size' => strlen($this->remaining_css),
            'extraction_time' => microtime(true) - $startTime,
            'selectors_found' => count($criticalSelectors),
            'compression_ratio' => round((strlen($this->critical_css) / strlen($css)) * 100, 2)
        ];
        
        $this->performance_monitor->recordMetric('critical_css_extraction', $results);
        
        return [
            'critical' => $this->critical_css,
            'remaining' => $this->remaining_css,
            'metrics' => $results
        ];
    }
    
    /**
     * Identify critical selectors from HTML
     */
    private function identifyCriticalSelectors($htmlPath) {
        $html = file_get_contents($htmlPath);
        $dom = new DOMDocument();
        @$dom->loadHTML($html);
        
        $xpath = new DOMXPath($dom);
        $criticalSelectors = $this->config['include_selectors'];
        
        // Find elements in viewport
        $elements = $xpath->query('//*[@data-critical or @class or @id]');
        
        foreach ($elements as $element) {
            // Get element position (simplified - in real implementation use headless browser)
            $classes = $element->getAttribute('class');
            $id = $element->getAttribute('id');
            
            if ($classes) {
                foreach (explode(' ', $classes) as $class) {
                    if ($class && !in_array('.' . $class, $this->config['exclude_selectors'])) {
                        $criticalSelectors[] = '.' . $class;
                    }
                }
            }
            
            if ($id && !in_array('#' . $id, $this->config['exclude_selectors'])) {
                $criticalSelectors[] = '#' . $id;
            }
            
            // Add tag selectors for structural elements
            $tagName = strtolower($element->tagName);
            if (in_array($tagName, ['header', 'nav', 'main', 'h1', 'h2', 'h3'])) {
                $criticalSelectors[] = $tagName;
            }
        }
        
        // Add viewport-specific selectors
        $criticalSelectors = array_merge($criticalSelectors, [
            '*[class*="hero"]',
            '*[class*="banner"]',
            '*[class*="above"]',
            'img[loading!="lazy"]',
            'style', 'link[rel="stylesheet"]'
        ]);
        
        return array_unique($criticalSelectors);
    }
    
    /**
     * Process CSS rules and separate critical from non-critical
     */
    private function processCSSRules($css, $criticalSelectors) {
        // Remove comments
        $css = preg_replace('/\/\*[^*]*\*+([^\/*][^*]*\*+)*\//', '', $css);
        
        // Split into rules
        $rules = $this->parseCSS($css);
        
        foreach ($rules as $rule) {
            if ($this->isCriticalRule($rule, $criticalSelectors)) {
                $this->critical_css .= $rule['full'] . "\n";
            } else {
                $this->remaining_css .= $rule['full'] . "\n";
            }
        }
        
        // Handle @font-face rules
        $this->extractFontRules($css);
        
        // Handle critical media queries
        if ($this->config['extract_media_queries']) {
            $this->extractCriticalMediaQueries($css, $criticalSelectors);
        }
    }
    
    /**
     * Parse CSS into individual rules
     */
    private function parseCSS($css) {
        $rules = [];
        
        // Match CSS rules (simplified parser)
        preg_match_all('/([^{]+)\{([^}]+)\}/s', $css, $matches, PREG_SET_ORDER);
        
        foreach ($matches as $match) {
            $selector = trim($match[1]);
            $declarations = trim($match[2]);
            
            $rules[] = [
                'selector' => $selector,
                'declarations' => $declarations,
                'full' => $match[0],
                'specificity' => $this->calculateSpecificity($selector)
            ];
        }
        
        // Sort by specificity for proper cascade
        usort($rules, function($a, $b) {
            return $a['specificity'] <=> $b['specificity'];
        });
        
        return $rules;
    }
    
    /**
     * Check if a rule is critical
     */
    private function isCriticalRule($rule, $criticalSelectors) {
        $selector = $rule['selector'];
        
        // Check for at-rules
        if (strpos($selector, '@') === 0) {
            return $this->isCriticalAtRule($selector);
        }
        
        // Check against critical selectors
        foreach ($criticalSelectors as $critical) {
            if ($this->selectorMatches($selector, $critical)) {
                return true;
            }
        }
        
        // Check for critical properties
        $criticalProperties = ['font-family', 'font-size', 'line-height', 'color', 'background'];
        foreach ($criticalProperties as $prop) {
            if (strpos($rule['declarations'], $prop) !== false && 
                $this->isHighSpecificity($rule['specificity'])) {
                return true;
            }
        }
        
        return false;
    }
    
    /**
     * Check if selectors match
     */
    private function selectorMatches($ruleSelector, $criticalSelector) {
        // Direct match
        if (strpos($ruleSelector, $criticalSelector) !== false) {
            return true;
        }
        
        // Handle complex selectors
        $ruleParts = preg_split('/[\s>+~]/', $ruleSelector);
        foreach ($ruleParts as $part) {
            if ($part === $criticalSelector || 
                strpos($part, $criticalSelector) !== false) {
                return true;
            }
        }
        
        return false;
    }
    
    /**
     * Calculate CSS specificity
     */
    private function calculateSpecificity($selector) {
        $specificity = [0, 0, 0];
        
        // Count IDs
        $specificity[0] = substr_count($selector, '#');
        
        // Count classes, attributes, pseudo-classes
        $specificity[1] = substr_count($selector, '.');
        $specificity[1] += substr_count($selector, '[');
        $specificity[1] += substr_count($selector, ':');
        
        // Count elements
        $elements = preg_match_all('/\b[a-z]+\b/i', $selector);
        $specificity[2] = $elements;
        
        return $specificity[0] * 100 + $specificity[1] * 10 + $specificity[2];
    }
    
    /**
     * Check if specificity is high
     */
    private function isHighSpecificity($specificity) {
        return $specificity >= 20; // 2 classes or 1 ID
    }
    
    /**
     * Check if at-rule is critical
     */
    private function isCriticalAtRule($atRule) {
        $criticalAtRules = ['@charset', '@import', '@font-face'];
        
        foreach ($criticalAtRules as $critical) {
            if (strpos($atRule, $critical) === 0) {
                return true;
            }
        }
        
        // Check for critical media queries
        if (strpos($atRule, '@media') === 0) {
            return strpos($atRule, 'min-width') !== false && 
                   preg_match('/(\d+)px/', $atRule, $matches) && 
                   intval($matches[1]) <= $this->config['viewport']['width'];
        }
        
        return false;
    }
    
    /**
     * Extract font rules for critical CSS
     */
    private function extractFontRules($css) {
        preg_match_all('/@font-face\s*\{[^}]+\}/s', $css, $fontFaces);
        
        foreach ($fontFaces[0] as $fontFace) {
            // Check if font is used in critical CSS
            preg_match('/font-family\s*:\s*["\']?([^"\';]+)/', $fontFace, $fontName);
            
            if (isset($fontName[1]) && strpos($this->critical_css, $fontName[1]) !== false) {
                if ($this->config['fonts_strategy'] === 'inline') {
                    $this->critical_css = $fontFace . "\n" . $this->critical_css;
                }
            }
        }
    }
    
    /**
     * Extract critical media queries
     */
    private function extractCriticalMediaQueries($css, $criticalSelectors) {
        preg_match_all('/@media[^{]+\{([^{}]*(?:\{[^}]*\}[^{}]*)*)\}/s', $css, $mediaQueries, PREG_SET_ORDER);
        
        foreach ($mediaQueries as $mq) {
            $mediaRule = $mq[0];
            $mediaContent = $mq[1];
            
            // Check if media query is relevant for viewport
            if ($this->isRelevantMediaQuery($mediaRule)) {
                $criticalMediaContent = '';
                $rules = $this->parseCSS($mediaContent);
                
                foreach ($rules as $rule) {
                    if ($this->isCriticalRule($rule, $criticalSelectors)) {
                        $criticalMediaContent .= $rule['full'];
                    }
                }
                
                if ($criticalMediaContent) {
                    $this->critical_css .= "\n" . str_replace($mediaContent, $criticalMediaContent, $mediaRule);
                }
            }
        }
    }
    
    /**
     * Check if media query is relevant for current viewport
     */
    private function isRelevantMediaQuery($mediaQuery) {
        // Extract conditions
        preg_match_all('/(min|max)-(width|height)\s*:\s*(\d+)px/', $mediaQuery, $conditions, PREG_SET_ORDER);
        
        foreach ($conditions as $condition) {
            $type = $condition[1]; // min or max
            $dimension = $condition[2]; // width or height
            $value = intval($condition[3]);
            
            $viewportValue = $this->config['viewport'][$dimension];
            
            if ($type === 'min' && $viewportValue < $value) {
                return false;
            }
            if ($type === 'max' && $viewportValue > $value) {
                return false;
            }
        }
        
        return true;
    }
    
    /**
     * Optimize critical CSS
     */
    private function optimizeCriticalCSS($css) {
        // Remove duplicate rules
        $rules = $this->parseCSS($css);
        $uniqueRules = [];
        $seenSelectors = [];
        
        foreach (array_reverse($rules) as $rule) {
            $selector = $rule['selector'];
            if (!in_array($selector, $seenSelectors)) {
                $uniqueRules[] = $rule['full'];
                $seenSelectors[] = $selector;
            }
        }
        
        $css = implode("\n", array_reverse($uniqueRules));
        
        // Minify
        $css = preg_replace('/\s+/', ' ', $css);
        $css = preg_replace('/\s*([{}:;,])\s*/', '$1', $css);
        
        return trim($css);
    }
    
    /**
     * Reduce critical CSS size if over limit
     */
    private function reduceCriticalSize($css) {
        $rules = $this->parseCSS($css);
        
        // Sort by importance
        usort($rules, function($a, $b) {
            // Prioritize certain selectors
            $prioritySelectors = ['body', 'html', ':root', 'header', 'nav'];
            $aPriority = 0;
            $bPriority = 0;
            
            foreach ($prioritySelectors as $idx => $selector) {
                if (strpos($a['selector'], $selector) !== false) {
                    $aPriority = count($prioritySelectors) - $idx;
                }
                if (strpos($b['selector'], $selector) !== false) {
                    $bPriority = count($prioritySelectors) - $idx;
                }
            }
            
            if ($aPriority !== $bPriority) {
                return $bPriority - $aPriority;
            }
            
            // Then by specificity
            return $b['specificity'] - $a['specificity'];
        });
        
        // Keep only most important rules
        $criticalCSS = '';
        $currentSize = 0;
        
        foreach ($rules as $rule) {
            $ruleSize = strlen($rule['full']);
            if ($currentSize + $ruleSize > $this->config['max_critical_size']) {
                break;
            }
            $criticalCSS .= $rule['full'] . "\n";
            $currentSize += $ruleSize;
        }
        
        return $criticalCSS;
    }
    
    /**
     * Generate inline critical CSS HTML
     */
    public function generateInlineHTML($criticalCSS) {
        $html = "<style id=\"critical-css\">\n";
        $html .= $criticalCSS;
        $html .= "\n</style>\n";
        
        // Add preload for remaining CSS
        $html .= '<link rel="preload" href="/css/main.css" as="style" onload="this.onload=null;this.rel=\'stylesheet\'">' . "\n";
        $html .= '<noscript><link rel="stylesheet" href="/css/main.css"></noscript>' . "\n";
        
        // Add font preloading if configured
        if ($this->config['fonts_strategy'] === 'preload') {
            preg_match_all('/url\(["\']?([^"\')]+\.(woff2?|ttf|otf))["\']?\)/', $criticalCSS, $fonts);
            foreach (array_unique($fonts[1]) as $font) {
                $html .= "<link rel=\"preload\" href=\"$font\" as=\"font\" type=\"font/" . pathinfo($font, PATHINFO_EXTENSION) . "\" crossorigin>\n";
            }
        }
        
        return $html;
    }
    
    /**
     * Generate critical CSS loading script
     */
    public function generateLoadingScript() {
        return '
// Critical CSS loader
(function() {
    // Load non-critical CSS
    var loadCSS = function(href) {
        var link = document.createElement("link");
        link.rel = "stylesheet";
        link.href = href;
        link.media = "only x";
        
        document.head.appendChild(link);
        
        // Set media back to all after load
        link.onload = function() {
            link.media = "all";
        };
    };
    
    // Remove critical CSS after main CSS loads
    var removeCritical = function() {
        var critical = document.getElementById("critical-css");
        if (critical) {
            critical.remove();
        }
    };
    
    // Load main CSS
    if ("requestIdleCallback" in window) {
        requestIdleCallback(function() {
            loadCSS("/css/main.css");
            setTimeout(removeCritical, 100);
        });
    } else {
        setTimeout(function() {
            loadCSS("/css/main.css");
            setTimeout(removeCritical, 100);
        }, 1);
    }
})();
';
    }
}

// Example usage
$extractor = new CriticalCSSExtractor();

// Extract critical CSS
if (file_exists('index.html') && file_exists('css/main.css')) {
    $result = $extractor->extractCritical('index.html', 'css/main.css');
    
    // Save critical CSS
    file_put_contents('css/critical.css', $result['critical']);
    file_put_contents('css/remaining.css', $result['remaining']);
    
    echo "Critical CSS Extraction Results:\n";
    echo "Critical CSS Size: " . number_format($result['metrics']['critical_size'] / 1024, 2) . " KB\n";
    echo "Remaining CSS Size: " . number_format($result['metrics']['remaining_size'] / 1024, 2) . " KB\n";
    echo "Compression Ratio: {$result['metrics']['compression_ratio']}%\n";
    echo "Selectors Found: {$result['metrics']['selectors_found']}\n";
    
    // Generate inline HTML
    $inlineHTML = $extractor->generateInlineHTML($result['critical']);
    file_put_contents('critical-css-inline.html', $inlineHTML);
    
    // Generate loading script
    file_put_contents('critical-css-loader.js', $extractor->generateLoadingScript());
}

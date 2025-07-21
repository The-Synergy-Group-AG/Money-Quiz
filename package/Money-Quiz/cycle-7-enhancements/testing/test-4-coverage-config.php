<?php
/**
 * Code Coverage Configuration
 * 
 * @package MoneyQuiz\Testing
 * @version 1.0.0
 */

namespace MoneyQuiz\Testing;

/**
 * Coverage Config
 */
class CoverageConfig {
    
    /**
     * Generate coverage configuration
     */
    public static function generate() {
        return [
            'enabled' => true,
            'minCoverage' => [
                'lines' => 80,
                'functions' => 80,
                'classes' => 80,
                'methods' => 80,
                'branches' => 70,
                'paths' => 70
            ],
            'exclude' => [
                'vendor/',
                'tests/',
                'node_modules/',
                'build/',
                'assets/vendor/',
                'languages/',
                'views/'
            ],
            'include' => [
                'includes/',
                'cycle-6-security/',
                'cycle-7-enhancements/'
            ],
            'reports' => [
                'html' => 'build/coverage/html',
                'clover' => 'build/coverage/clover.xml',
                'cobertura' => 'build/coverage/cobertura.xml',
                'text' => 'build/coverage/coverage.txt',
                'json' => 'build/coverage/coverage.json'
            ]
        ];
    }
    
    /**
     * Generate PHPUnit coverage config
     */
    public static function generatePHPUnitConfig() {
        $config = self::generate();
        
        return sprintf(
            '<coverage processUncoveredFiles="true">
    <include>
        %s
    </include>
    <exclude>
        %s
    </exclude>
    <report>
        <clover outputFile="%s"/>
        <cobertura outputFile="%s"/>
        <html outputDirectory="%s" lowUpperBound="50" highLowerBound="90"/>
        <text outputFile="%s" showUncoveredFiles="true" showOnlySummary="false"/>
    </report>
</coverage>',
            implode("\n        ", array_map(function($dir) {
                return "<directory suffix=\".php\">{$dir}</directory>";
            }, $config['include'])),
            implode("\n        ", array_map(function($dir) {
                return "<directory>{$dir}</directory>";
            }, $config['exclude'])),
            $config['reports']['clover'],
            $config['reports']['cobertura'],
            $config['reports']['html'],
            $config['reports']['text']
        );
    }
    
    /**
     * Generate Jest coverage config
     */
    public static function generateJestConfig() {
        $config = self::generate();
        
        return [
            'collectCoverage' => true,
            'coverageDirectory' => 'coverage',
            'coverageReporters' => [
                'html',
                'text',
                'lcov',
                'json'
            ],
            'coverageThreshold' => [
                'global' => [
                    'branches' => $config['minCoverage']['branches'],
                    'functions' => $config['minCoverage']['functions'],
                    'lines' => $config['minCoverage']['lines'],
                    'statements' => $config['minCoverage']['lines']
                ]
            ],
            'collectCoverageFrom' => [
                'assets/js/**/*.{js,jsx}',
                'cycle-7-enhancements/react-admin/src/**/*.{js,jsx}',
                '!**/*.test.js',
                '!**/node_modules/**',
                '!**/vendor/**'
            ]
        ];
    }
    
    /**
     * Check coverage thresholds
     */
    public static function checkThresholds($coverageData) {
        $config = self::generate();
        $failures = [];
        
        foreach ($config['minCoverage'] as $metric => $threshold) {
            if (isset($coverageData[$metric])) {
                $actual = $coverageData[$metric];
                if ($actual < $threshold) {
                    $failures[] = sprintf(
                        '%s coverage is %s%% (minimum: %s%%)',
                        ucfirst($metric),
                        $actual,
                        $threshold
                    );
                }
            }
        }
        
        return $failures;
    }
    
    /**
     * Generate coverage badge
     */
    public static function generateBadge($percentage, $type = 'coverage') {
        $color = 'red';
        
        if ($percentage >= 90) {
            $color = 'brightgreen';
        } elseif ($percentage >= 80) {
            $color = 'green';
        } elseif ($percentage >= 70) {
            $color = 'yellow';
        } elseif ($percentage >= 50) {
            $color = 'orange';
        }
        
        return sprintf(
            'https://img.shields.io/badge/%s-%s%%25-%s',
            $type,
            $percentage,
            $color
        );
    }
    
    /**
     * Parse coverage report
     */
    public static function parseCoverageReport($file) {
        if (!file_exists($file)) {
            return null;
        }
        
        $content = file_get_contents($file);
        
        // Parse different formats
        if (strpos($file, '.json') !== false) {
            return json_decode($content, true);
        } elseif (strpos($file, '.xml') !== false) {
            return self::parseXmlCoverage($content);
        } else {
            return self::parseTextCoverage($content);
        }
    }
    
    /**
     * Parse XML coverage
     */
    private static function parseXmlCoverage($xml) {
        $data = simplexml_load_string($xml);
        
        $metrics = [
            'lines' => 0,
            'methods' => 0,
            'classes' => 0,
            'statements' => 0
        ];
        
        // Extract metrics from XML
        foreach ($data->project->metrics[0]->attributes() as $key => $value) {
            if (isset($metrics[$key])) {
                $metrics[$key] = (float) $value;
            }
        }
        
        return $metrics;
    }
    
    /**
     * Parse text coverage
     */
    private static function parseTextCoverage($text) {
        $metrics = [];
        
        // Extract percentages from text report
        if (preg_match('/Lines:\s*(\d+\.\d+)%/', $text, $matches)) {
            $metrics['lines'] = (float) $matches[1];
        }
        if (preg_match('/Functions:\s*(\d+\.\d+)%/', $text, $matches)) {
            $metrics['functions'] = (float) $matches[1];
        }
        if (preg_match('/Classes:\s*(\d+\.\d+)%/', $text, $matches)) {
            $metrics['classes'] = (float) $matches[1];
        }
        
        return $metrics;
    }
}
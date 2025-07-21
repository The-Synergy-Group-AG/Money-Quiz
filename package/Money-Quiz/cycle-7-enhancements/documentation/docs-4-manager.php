<?php
/**
 * Documentation Manager
 * 
 * @package MoneyQuiz\Documentation
 * @version 1.0.0
 */

namespace MoneyQuiz\Documentation;

/**
 * Documentation Manager
 */
class DocumentationManager {
    
    private static $instance = null;
    private $generators = [];
    
    private function __construct() {
        $this->generators = [
            'api' => ApiDocGenerator::getInstance(),
            'code' => CodeParser::getInstance(),
            'user' => UserGuideGenerator::getInstance()
        ];
    }
    
    /**
     * Get singleton instance
     */
    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Generate documentation
     */
    public function generate($type = 'all', $format = 'markdown') {
        if ($type === 'all') {
            return $this->generateAll($format);
        }
        
        if (!isset($this->generators[$type])) {
            throw new \Exception("Unknown documentation type: $type");
        }
        
        return $this->generators[$type]->generate($format);
    }
    
    /**
     * Generate all documentation
     */
    private function generateAll($format) {
        $docs = [];
        
        foreach ($this->generators as $type => $generator) {
            try {
                $docs[$type] = $generator->generate($format);
            } catch (\Exception $e) {
                $docs[$type] = "Error generating $type documentation: " . $e->getMessage();
            }
        }
        
        return $docs;
    }
    
    /**
     * Save documentation
     */
    public function save($type, $format, $destination) {
        $content = $this->generate($type, $format);
        
        if (is_array($content)) {
            // Save multiple files
            foreach ($content as $doc_type => $doc_content) {
                $filename = $destination . '/' . $doc_type . '.' . $this->getExtension($format);
                file_put_contents($filename, $doc_content);
            }
        } else {
            // Save single file
            file_put_contents($destination, $content);
        }
        
        return true;
    }
    
    /**
     * Get file extension
     */
    private function getExtension($format) {
        $extensions = [
            'markdown' => 'md',
            'html' => 'html',
            'pdf' => 'pdf',
            'openapi' => 'json'
        ];
        
        return $extensions[$format] ?? 'txt';
    }
    
    /**
     * Build documentation site
     */
    public function buildSite($output_dir) {
        // Create directory structure
        $dirs = ['api', 'guides', 'reference', 'assets'];
        foreach ($dirs as $dir) {
            wp_mkdir_p($output_dir . '/' . $dir);
        }
        
        // Generate index
        $this->generateIndex($output_dir);
        
        // Generate API docs
        $api_docs = $this->generators['api']->generate('html');
        file_put_contents($output_dir . '/api/index.html', $api_docs);
        
        // Generate user guides
        $user_guide = $this->generators['user']->generate('html');
        file_put_contents($output_dir . '/guides/index.html', $user_guide);
        
        // Generate code reference
        $code_docs = $this->generators['code']->generate('html');
        file_put_contents($output_dir . '/reference/index.html', $code_docs);
        
        // Copy assets
        $this->copyAssets($output_dir . '/assets');
        
        return true;
    }
    
    /**
     * Generate index page
     */
    private function generateIndex($output_dir) {
        $index = <<<HTML
<!DOCTYPE html>
<html>
<head>
    <title>Money Quiz Documentation</title>
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
            margin: 0;
            padding: 0;
            background: #f5f5f5;
        }
        .header {
            background: #2c3e50;
            color: white;
            padding: 30px 0;
            text-align: center;
        }
        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 40px 20px;
        }
        .cards {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 30px;
            margin-top: 40px;
        }
        .card {
            background: white;
            border-radius: 8px;
            padding: 30px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            transition: transform 0.2s;
        }
        .card:hover {
            transform: translateY(-5px);
        }
        .card h2 {
            color: #2c3e50;
            margin-top: 0;
        }
        .card a {
            display: inline-block;
            margin-top: 20px;
            color: #3498db;
            text-decoration: none;
            font-weight: bold;
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>Money Quiz Documentation</h1>
        <p>Complete documentation for the Money Quiz WordPress plugin</p>
    </div>
    
    <div class="container">
        <div class="cards">
            <div class="card">
                <h2>User Guide</h2>
                <p>Learn how to use Money Quiz to create and manage quizzes.</p>
                <a href="guides/">Read User Guide →</a>
            </div>
            
            <div class="card">
                <h2>API Reference</h2>
                <p>Complete REST API documentation for developers.</p>
                <a href="api/">View API Docs →</a>
            </div>
            
            <div class="card">
                <h2>Code Reference</h2>
                <p>Detailed documentation of classes and functions.</p>
                <a href="reference/">Browse Code →</a>
            </div>
        </div>
        
        <div style="margin-top: 60px; text-align: center;">
            <p>Version 1.0.0 | <a href="https://github.com/moneyquiz/plugin">GitHub</a></p>
        </div>
    </div>
</body>
</html>
HTML;
        
        file_put_contents($output_dir . '/index.html', $index);
    }
    
    /**
     * Copy assets
     */
    private function copyAssets($assets_dir) {
        // Would copy CSS, JS, images etc.
        // For now, create a simple CSS file
        $css = <<<CSS
body {
    font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
    line-height: 1.6;
    color: #333;
}

h1, h2, h3 {
    color: #2c3e50;
}

code {
    background: #f4f4f4;
    padding: 2px 5px;
    border-radius: 3px;
}

pre {
    background: #f4f4f4;
    padding: 15px;
    border-radius: 5px;
    overflow-x: auto;
}

.note {
    background: #e3f2fd;
    border-left: 4px solid #2196f3;
    padding: 15px;
    margin: 20px 0;
}

.warning {
    background: #fff3cd;
    border-left: 4px solid #ffc107;
    padding: 15px;
    margin: 20px 0;
}
CSS;
        
        file_put_contents($assets_dir . '/style.css', $css);
    }
    
    /**
     * Generate changelog
     */
    public function generateChangelog() {
        $changelog = <<<MARKDOWN
# Changelog

All notable changes to Money Quiz will be documented in this file.

## [1.0.0] - 2024-01-15

### Added
- Initial release
- Quiz creation and management
- Multiple question types
- REST API endpoints
- React-based admin interface
- Analytics dashboard
- Webhook integration
- Security hardening
- Comprehensive testing suite

### Security
- CSRF protection
- XSS prevention
- SQL injection prevention
- Rate limiting
- Security headers

### Documentation
- User guide
- API documentation
- Code reference
- Installation guide

MARKDOWN;
        
        return $changelog;
    }
    
    /**
     * Generate README
     */
    public function generateReadme() {
        $readme = <<<MARKDOWN
# Money Quiz

A powerful WordPress plugin for creating and managing quizzes.

## Features

- 📝 Multiple question types
- 📊 Advanced analytics
- 🔒 Security-focused
- 🚀 REST API
- ⚛️ React admin interface
- 🔗 Webhook integration
- 📱 Mobile responsive

## Installation

1. Upload the plugin to `/wp-content/plugins/`
2. Activate through the WordPress admin
3. Navigate to Money Quiz to get started

## Requirements

- WordPress 5.9+
- PHP 7.4+
- MySQL 5.7+

## Documentation

- [User Guide](docs/guides/)
- [API Reference](docs/api/)
- [Code Reference](docs/reference/)

## Support

For support, please visit our [support forum](https://support.moneyquiz.com).

## License

GPL v2 or later

MARKDOWN;
        
        return $readme;
    }
}
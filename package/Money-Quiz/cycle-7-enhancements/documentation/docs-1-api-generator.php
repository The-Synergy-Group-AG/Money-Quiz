<?php
/**
 * API Documentation Generator
 * 
 * @package MoneyQuiz\Documentation
 * @version 1.0.0
 */

namespace MoneyQuiz\Documentation;

/**
 * API Documentation Generator
 */
class ApiDocGenerator {
    
    private static $instance = null;
    private $endpoints = [];
    private $schemas = [];
    
    private function __construct() {
        $this->collectEndpoints();
        $this->collectSchemas();
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
     * Collect API endpoints
     */
    private function collectEndpoints() {
        $routes = rest_get_server()->get_routes();
        
        foreach ($routes as $route => $handlers) {
            if (strpos($route, '/money-quiz/') !== 0) {
                continue;
            }
            
            $this->endpoints[$route] = $this->parseEndpoint($route, $handlers);
        }
    }
    
    /**
     * Parse endpoint
     */
    private function parseEndpoint($route, $handlers) {
        $endpoint = [
            'path' => $route,
            'methods' => []
        ];
        
        foreach ($handlers as $handler) {
            if (!isset($handler['methods'])) {
                continue;
            }
            
            foreach ($handler['methods'] as $method => $enabled) {
                if (!$enabled) continue;
                
                $endpoint['methods'][$method] = [
                    'description' => $handler['description'] ?? '',
                    'permission_callback' => $this->getPermissionInfo($handler),
                    'args' => $handler['args'] ?? [],
                    'schema' => $handler['schema'] ?? null
                ];
            }
        }
        
        return $endpoint;
    }
    
    /**
     * Get permission info
     */
    private function getPermissionInfo($handler) {
        if (!isset($handler['permission_callback'])) {
            return 'Public';
        }
        
        $callback = $handler['permission_callback'];
        
        if (is_string($callback)) {
            return $callback;
        } elseif (is_array($callback)) {
            return implode('::', $callback);
        } else {
            return 'Custom callback';
        }
    }
    
    /**
     * Collect schemas
     */
    private function collectSchemas() {
        $this->schemas = [
            'Quiz' => [
                'type' => 'object',
                'properties' => [
                    'id' => ['type' => 'integer', 'description' => 'Quiz ID'],
                    'title' => ['type' => 'string', 'description' => 'Quiz title'],
                    'description' => ['type' => 'string', 'description' => 'Quiz description'],
                    'status' => ['type' => 'string', 'enum' => ['draft', 'published']],
                    'created_at' => ['type' => 'string', 'format' => 'date-time']
                ]
            ],
            'Question' => [
                'type' => 'object',
                'properties' => [
                    'id' => ['type' => 'integer'],
                    'quiz_id' => ['type' => 'integer'],
                    'text' => ['type' => 'string'],
                    'type' => ['type' => 'string', 'enum' => ['multiple', 'true_false']],
                    'options' => ['type' => 'array', 'items' => ['type' => 'string']],
                    'correct_answer' => ['type' => 'string'],
                    'points' => ['type' => 'integer']
                ]
            ],
            'Result' => [
                'type' => 'object',
                'properties' => [
                    'id' => ['type' => 'integer'],
                    'quiz_id' => ['type' => 'integer'],
                    'user_id' => ['type' => 'integer'],
                    'score' => ['type' => 'number'],
                    'answers' => ['type' => 'object'],
                    'completed_at' => ['type' => 'string', 'format' => 'date-time']
                ]
            ]
        ];
    }
    
    /**
     * Generate documentation
     */
    public function generate($format = 'markdown') {
        switch ($format) {
            case 'markdown':
                return $this->generateMarkdown();
            case 'html':
                return $this->generateHtml();
            case 'openapi':
                return $this->generateOpenApi();
            default:
                throw new \Exception("Unknown format: $format");
        }
    }
    
    /**
     * Generate Markdown documentation
     */
    private function generateMarkdown() {
        $doc = "# Money Quiz API Documentation\n\n";
        $doc .= "## Base URL\n\n";
        $doc .= "```\n" . rest_url('money-quiz/v1') . "\n```\n\n";
        
        $doc .= "## Authentication\n\n";
        $doc .= "Most endpoints require authentication using WordPress nonces.\n\n";
        
        $doc .= "## Endpoints\n\n";
        
        foreach ($this->endpoints as $path => $endpoint) {
            $doc .= "### " . $path . "\n\n";
            
            foreach ($endpoint['methods'] as $method => $info) {
                $doc .= "#### $method\n\n";
                
                if ($info['description']) {
                    $doc .= $info['description'] . "\n\n";
                }
                
                $doc .= "**Permission:** " . $info['permission_callback'] . "\n\n";
                
                if (!empty($info['args'])) {
                    $doc .= "**Parameters:**\n\n";
                    foreach ($info['args'] as $name => $arg) {
                        $doc .= "- `$name` ";
                        $doc .= "({$arg['type']})";
                        if (isset($arg['required']) && $arg['required']) {
                            $doc .= " **required**";
                        }
                        if (isset($arg['description'])) {
                            $doc .= " - {$arg['description']}";
                        }
                        $doc .= "\n";
                    }
                    $doc .= "\n";
                }
            }
        }
        
        $doc .= "## Schemas\n\n";
        
        foreach ($this->schemas as $name => $schema) {
            $doc .= "### $name\n\n";
            $doc .= "```json\n";
            $doc .= json_encode($schema, JSON_PRETTY_PRINT);
            $doc .= "\n```\n\n";
        }
        
        return $doc;
    }
    
    /**
     * Generate HTML documentation
     */
    private function generateHtml() {
        $html = <<<HTML
<!DOCTYPE html>
<html>
<head>
    <title>Money Quiz API Documentation</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 40px; }
        h1, h2, h3 { color: #333; }
        code { background: #f4f4f4; padding: 2px 4px; }
        pre { background: #f4f4f4; padding: 10px; overflow-x: auto; }
        .endpoint { border: 1px solid #ddd; padding: 15px; margin: 10px 0; }
        .method { display: inline-block; padding: 4px 8px; color: white; font-weight: bold; }
        .GET { background: #61affe; }
        .POST { background: #49cc90; }
        .PUT { background: #fca130; }
        .DELETE { background: #f93e3e; }
    </style>
</head>
<body>
    <h1>Money Quiz API Documentation</h1>
HTML;
        
        $html .= $this->generateHtmlContent();
        
        $html .= "</body></html>";
        
        return $html;
    }
    
    /**
     * Generate HTML content
     */
    private function generateHtmlContent() {
        ob_start();
        
        foreach ($this->endpoints as $path => $endpoint) {
            ?>
            <div class="endpoint">
                <h3><?php echo esc_html($path); ?></h3>
                <?php foreach ($endpoint['methods'] as $method => $info): ?>
                    <span class="method <?php echo esc_attr($method); ?>">
                        <?php echo esc_html($method); ?>
                    </span>
                    <?php if ($info['description']): ?>
                        <p><?php echo esc_html($info['description']); ?></p>
                    <?php endif; ?>
                <?php endforeach; ?>
            </div>
            <?php
        }
        
        return ob_get_clean();
    }
    
    /**
     * Generate OpenAPI specification
     */
    private function generateOpenApi() {
        $spec = [
            'openapi' => '3.0.0',
            'info' => [
                'title' => 'Money Quiz API',
                'version' => '1.0.0',
                'description' => 'REST API for Money Quiz WordPress plugin'
            ],
            'servers' => [
                ['url' => rest_url('money-quiz/v1')]
            ],
            'paths' => $this->generateOpenApiPaths(),
            'components' => [
                'schemas' => $this->schemas,
                'securitySchemes' => [
                    'wpNonce' => [
                        'type' => 'apiKey',
                        'in' => 'header',
                        'name' => 'X-WP-Nonce'
                    ]
                ]
            ]
        ];
        
        return json_encode($spec, JSON_PRETTY_PRINT);
    }
    
    /**
     * Generate OpenAPI paths
     */
    private function generateOpenApiPaths() {
        $paths = [];
        
        foreach ($this->endpoints as $path => $endpoint) {
            $pathKey = str_replace('/money-quiz/v1', '', $path);
            $paths[$pathKey] = [];
            
            foreach ($endpoint['methods'] as $method => $info) {
                $paths[$pathKey][strtolower($method)] = [
                    'summary' => $info['description'] ?: 'No description',
                    'parameters' => $this->generateOpenApiParams($info['args']),
                    'responses' => [
                        '200' => ['description' => 'Success'],
                        '401' => ['description' => 'Unauthorized'],
                        '404' => ['description' => 'Not found']
                    ]
                ];
            }
        }
        
        return $paths;
    }
    
    /**
     * Generate OpenAPI parameters
     */
    private function generateOpenApiParams($args) {
        $params = [];
        
        foreach ($args as $name => $arg) {
            $params[] = [
                'name' => $name,
                'in' => 'query',
                'required' => $arg['required'] ?? false,
                'schema' => ['type' => $arg['type'] ?? 'string'],
                'description' => $arg['description'] ?? ''
            ];
        }
        
        return $params;
    }
}
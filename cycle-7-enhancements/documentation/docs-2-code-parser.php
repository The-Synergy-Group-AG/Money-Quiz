<?php
/**
 * Code Documentation Parser
 * 
 * @package MoneyQuiz\Documentation
 * @version 1.0.0
 */

namespace MoneyQuiz\Documentation;

/**
 * Code Parser
 */
class CodeParser {
    
    private static $instance = null;
    private $files = [];
    private $classes = [];
    private $functions = [];
    
    private function __construct() {}
    
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
     * Parse directory
     */
    public function parseDirectory($dir, $recursive = true) {
        $iterator = $recursive ? 
            new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($dir)) :
            new \DirectoryIterator($dir);
        
        foreach ($iterator as $file) {
            if ($file->isFile() && $file->getExtension() === 'php') {
                $this->parseFile($file->getPathname());
            }
        }
        
        return [
            'files' => count($this->files),
            'classes' => count($this->classes),
            'functions' => count($this->functions)
        ];
    }
    
    /**
     * Parse file
     */
    public function parseFile($filepath) {
        $content = file_get_contents($filepath);
        $tokens = token_get_all($content);
        
        $file_info = [
            'path' => $filepath,
            'docblock' => $this->extractFileDocblock($tokens),
            'namespace' => $this->extractNamespace($tokens),
            'classes' => [],
            'functions' => []
        ];
        
        $this->parseTokens($tokens, $file_info);
        
        $this->files[$filepath] = $file_info;
    }
    
    /**
     * Extract file docblock
     */
    private function extractFileDocblock($tokens) {
        foreach ($tokens as $i => $token) {
            if (is_array($token) && $token[0] === T_DOC_COMMENT) {
                return $this->parseDocblock($token[1]);
            }
            if (is_array($token) && $token[0] === T_NAMESPACE) {
                break;
            }
        }
        return null;
    }
    
    /**
     * Extract namespace
     */
    private function extractNamespace($tokens) {
        $namespace = '';
        $in_namespace = false;
        
        foreach ($tokens as $token) {
            if (is_array($token) && $token[0] === T_NAMESPACE) {
                $in_namespace = true;
                continue;
            }
            
            if ($in_namespace) {
                if (is_string($token) && $token === ';') {
                    break;
                }
                if (is_array($token) && $token[0] === T_STRING) {
                    $namespace .= $token[1];
                }
                if (is_array($token) && $token[0] === T_NS_SEPARATOR) {
                    $namespace .= '\\';
                }
            }
        }
        
        return $namespace;
    }
    
    /**
     * Parse tokens
     */
    private function parseTokens($tokens, &$file_info) {
        $current_class = null;
        $current_function = null;
        $current_docblock = null;
        
        for ($i = 0; $i < count($tokens); $i++) {
            $token = $tokens[$i];
            
            if (!is_array($token)) continue;
            
            // Capture docblocks
            if ($token[0] === T_DOC_COMMENT) {
                $current_docblock = $this->parseDocblock($token[1]);
            }
            
            // Parse classes
            if ($token[0] === T_CLASS) {
                $class_info = $this->parseClass($tokens, $i, $current_docblock);
                if ($class_info) {
                    $file_info['classes'][] = $class_info;
                    $this->classes[$class_info['name']] = $class_info;
                    $current_class = $class_info['name'];
                }
                $current_docblock = null;
            }
            
            // Parse functions
            if ($token[0] === T_FUNCTION) {
                $function_info = $this->parseFunction($tokens, $i, $current_docblock);
                if ($function_info) {
                    if ($current_class) {
                        $function_info['class'] = $current_class;
                    }
                    $file_info['functions'][] = $function_info;
                    $this->functions[] = $function_info;
                }
                $current_docblock = null;
            }
        }
    }
    
    /**
     * Parse docblock
     */
    private function parseDocblock($comment) {
        $docblock = [
            'description' => '',
            'tags' => []
        ];
        
        $lines = explode("\n", $comment);
        $description_lines = [];
        
        foreach ($lines as $line) {
            $line = trim($line, " \t\n\r\0\x0B*/");
            
            if (empty($line)) continue;
            
            if (preg_match('/^@(\w+)(.*)$/', $line, $matches)) {
                $tag = $matches[1];
                $value = trim($matches[2]);
                
                if (!isset($docblock['tags'][$tag])) {
                    $docblock['tags'][$tag] = [];
                }
                
                $docblock['tags'][$tag][] = $value;
            } else {
                $description_lines[] = $line;
            }
        }
        
        $docblock['description'] = implode(' ', $description_lines);
        
        return $docblock;
    }
    
    /**
     * Parse class
     */
    private function parseClass($tokens, &$i, $docblock) {
        $class = [
            'name' => '',
            'docblock' => $docblock,
            'extends' => null,
            'implements' => [],
            'methods' => []
        ];
        
        // Skip to class name
        while ($i < count($tokens) && (!is_array($tokens[$i]) || $tokens[$i][0] !== T_STRING)) {
            $i++;
        }
        
        if ($i < count($tokens)) {
            $class['name'] = $tokens[$i][1];
        }
        
        // Check for extends/implements
        while ($i < count($tokens) && $tokens[$i] !== '{') {
            if (is_array($tokens[$i])) {
                if ($tokens[$i][0] === T_EXTENDS) {
                    $i++;
                    while ($i < count($tokens) && (!is_array($tokens[$i]) || $tokens[$i][0] !== T_STRING)) {
                        $i++;
                    }
                    if ($i < count($tokens)) {
                        $class['extends'] = $tokens[$i][1];
                    }
                } elseif ($tokens[$i][0] === T_IMPLEMENTS) {
                    $i++;
                    $class['implements'] = $this->parseImplementsList($tokens, $i);
                }
            }
            $i++;
        }
        
        return $class;
    }
    
    /**
     * Parse function
     */
    private function parseFunction($tokens, &$i, $docblock) {
        $function = [
            'name' => '',
            'docblock' => $docblock,
            'params' => [],
            'return' => null,
            'visibility' => 'public'
        ];
        
        // Check visibility
        if ($i > 0) {
            $prev = $tokens[$i - 1];
            if (is_array($prev)) {
                if ($prev[0] === T_PUBLIC) $function['visibility'] = 'public';
                elseif ($prev[0] === T_PROTECTED) $function['visibility'] = 'protected';
                elseif ($prev[0] === T_PRIVATE) $function['visibility'] = 'private';
            }
        }
        
        // Skip to function name
        while ($i < count($tokens) && (!is_array($tokens[$i]) || $tokens[$i][0] !== T_STRING)) {
            $i++;
        }
        
        if ($i < count($tokens)) {
            $function['name'] = $tokens[$i][1];
        }
        
        // Parse parameters
        while ($i < count($tokens) && $tokens[$i] !== '(') {
            $i++;
        }
        
        if ($i < count($tokens)) {
            $function['params'] = $this->parseParams($tokens, $i);
        }
        
        return $function;
    }
    
    /**
     * Parse implements list
     */
    private function parseImplementsList($tokens, &$i) {
        $implements = [];
        $current = '';
        
        while ($i < count($tokens) && $tokens[$i] !== '{') {
            if (is_array($tokens[$i]) && $tokens[$i][0] === T_STRING) {
                $current .= $tokens[$i][1];
            } elseif ($tokens[$i] === ',') {
                if ($current) {
                    $implements[] = $current;
                    $current = '';
                }
            }
            $i++;
        }
        
        if ($current) {
            $implements[] = $current;
        }
        
        return $implements;
    }
    
    /**
     * Parse parameters
     */
    private function parseParams($tokens, &$i) {
        $params = [];
        $current_param = '';
        $in_params = false;
        
        while ($i < count($tokens)) {
            if ($tokens[$i] === '(') {
                $in_params = true;
            } elseif ($tokens[$i] === ')') {
                if ($current_param) {
                    $params[] = trim($current_param);
                }
                break;
            } elseif ($in_params) {
                if ($tokens[$i] === ',') {
                    if ($current_param) {
                        $params[] = trim($current_param);
                        $current_param = '';
                    }
                } else {
                    if (is_array($tokens[$i])) {
                        $current_param .= $tokens[$i][1] . ' ';
                    } else {
                        $current_param .= $tokens[$i];
                    }
                }
            }
            $i++;
        }
        
        return $params;
    }
    
    /**
     * Generate documentation
     */
    public function generateDocs($format = 'markdown') {
        switch ($format) {
            case 'markdown':
                return $this->generateMarkdownDocs();
            case 'html':
                return $this->generateHtmlDocs();
            default:
                throw new \Exception("Unknown format: $format");
        }
    }
    
    /**
     * Generate markdown documentation
     */
    private function generateMarkdownDocs() {
        $doc = "# Code Documentation\n\n";
        
        // Table of contents
        $doc .= "## Table of Contents\n\n";
        foreach ($this->classes as $class) {
            $doc .= "- [{$class['name']}](#{$class['name']})\n";
        }
        $doc .= "\n";
        
        // Classes
        foreach ($this->classes as $class) {
            $doc .= "## {$class['name']}\n\n";
            
            if ($class['docblock'] && $class['docblock']['description']) {
                $doc .= $class['docblock']['description'] . "\n\n";
            }
            
            if ($class['extends']) {
                $doc .= "**Extends:** {$class['extends']}\n\n";
            }
            
            if (!empty($class['implements'])) {
                $doc .= "**Implements:** " . implode(', ', $class['implements']) . "\n\n";
            }
        }
        
        return $doc;
    }
    
    /**
     * Generate HTML documentation
     */
    private function generateHtmlDocs() {
        ob_start();
        ?>
        <!DOCTYPE html>
        <html>
        <head>
            <title>Code Documentation</title>
            <style>
                body { font-family: Arial, sans-serif; margin: 40px; }
                .class { border: 1px solid #ddd; padding: 20px; margin: 20px 0; }
                .method { margin-left: 20px; padding: 10px; background: #f9f9f9; }
                code { background: #f4f4f4; padding: 2px 4px; }
            </style>
        </head>
        <body>
            <h1>Code Documentation</h1>
            <?php foreach ($this->classes as $class): ?>
                <div class="class">
                    <h2><?php echo esc_html($class['name']); ?></h2>
                    <?php if ($class['docblock']): ?>
                        <p><?php echo esc_html($class['docblock']['description']); ?></p>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        </body>
        </html>
        <?php
        return ob_get_clean();
    }
}
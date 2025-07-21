<?php
/**
 * Documentation System Loader
 * 
 * @package MoneyQuiz\Documentation
 * @version 1.0.0
 */

namespace MoneyQuiz\Documentation;

// Load documentation components
require_once __DIR__ . '/docs-1-api-generator.php';
require_once __DIR__ . '/docs-2-code-parser.php';
require_once __DIR__ . '/docs-3-user-guide.php';
require_once __DIR__ . '/docs-4-manager.php';

/**
 * Documentation System
 */
class DocumentationSystem {
    
    private static $instance = null;
    private $manager;
    
    private function __construct() {
        $this->manager = DocumentationManager::getInstance();
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
     * Initialize documentation system
     */
    public static function init() {
        $instance = self::getInstance();
        
        // Add admin menu
        add_action('admin_menu', [$instance, 'addAdminMenu']);
        
        // Register CLI commands
        if (defined('WP_CLI') && WP_CLI) {
            \WP_CLI::add_command('money-quiz docs', [$instance, 'cliCommand']);
        }
        
        // Add help tabs
        add_action('current_screen', [$instance, 'addHelpTabs']);
        
        // Register REST endpoint
        add_action('rest_api_init', [$instance, 'registerEndpoints']);
    }
    
    /**
     * Add admin menu
     */
    public function addAdminMenu() {
        if (!current_user_can('manage_options')) {
            return;
        }
        
        add_submenu_page(
            'money-quiz',
            'Documentation',
            'Documentation',
            'manage_options',
            'money-quiz-docs',
            [$this, 'renderDocsPage']
        );
    }
    
    /**
     * Render documentation page
     */
    public function renderDocsPage() {
        ?>
        <div class="wrap">
            <h1>Money Quiz Documentation</h1>
            
            <div class="card">
                <h2>Generate Documentation</h2>
                <form method="post" action="">
                    <?php wp_nonce_field('money_quiz_docs', 'docs_nonce'); ?>
                    
                    <table class="form-table">
                        <tr>
                            <th>Documentation Type</th>
                            <td>
                                <select name="doc_type">
                                    <option value="all">All Documentation</option>
                                    <option value="api">API Documentation</option>
                                    <option value="code">Code Reference</option>
                                    <option value="user">User Guide</option>
                                </select>
                            </td>
                        </tr>
                        <tr>
                            <th>Format</th>
                            <td>
                                <select name="doc_format">
                                    <option value="markdown">Markdown</option>
                                    <option value="html">HTML</option>
                                    <option value="pdf">PDF</option>
                                </select>
                            </td>
                        </tr>
                    </table>
                    
                    <p class="submit">
                        <button type="submit" name="generate_docs" class="button button-primary">
                            Generate Documentation
                        </button>
                        <button type="submit" name="build_site" class="button">
                            Build Documentation Site
                        </button>
                    </p>
                </form>
            </div>
            
            <?php $this->handleFormSubmission(); ?>
            
            <div class="card">
                <h2>Quick Links</h2>
                <ul>
                    <li><a href="#" onclick="viewDocs('user')">View User Guide</a></li>
                    <li><a href="#" onclick="viewDocs('api')">View API Docs</a></li>
                    <li><a href="#" onclick="viewDocs('code')">View Code Reference</a></li>
                </ul>
            </div>
        </div>
        
        <script>
        function viewDocs(type) {
            window.open('<?php echo admin_url('admin-ajax.php?action=money_quiz_view_docs&type='); ?>' + type);
        }
        </script>
        <?php
    }
    
    /**
     * Handle form submission
     */
    private function handleFormSubmission() {
        if (!isset($_POST['docs_nonce']) || !wp_verify_nonce($_POST['docs_nonce'], 'money_quiz_docs')) {
            return;
        }
        
        if (isset($_POST['generate_docs'])) {
            $type = $_POST['doc_type'] ?? 'all';
            $format = $_POST['doc_format'] ?? 'markdown';
            
            try {
                $docs = $this->manager->generate($type, $format);
                
                // Save to uploads directory
                $upload_dir = wp_upload_dir();
                $docs_dir = $upload_dir['basedir'] . '/money-quiz-docs';
                wp_mkdir_p($docs_dir);
                
                $this->manager->save($type, $format, $docs_dir);
                
                echo '<div class="notice notice-success"><p>Documentation generated successfully!</p></div>';
            } catch (\Exception $e) {
                echo '<div class="notice notice-error"><p>Error: ' . esc_html($e->getMessage()) . '</p></div>';
            }
        }
        
        if (isset($_POST['build_site'])) {
            try {
                $upload_dir = wp_upload_dir();
                $site_dir = $upload_dir['basedir'] . '/money-quiz-docs-site';
                
                $this->manager->buildSite($site_dir);
                
                echo '<div class="notice notice-success"><p>Documentation site built successfully!</p></div>';
            } catch (\Exception $e) {
                echo '<div class="notice notice-error"><p>Error: ' . esc_html($e->getMessage()) . '</p></div>';
            }
        }
    }
    
    /**
     * CLI command handler
     */
    public function cliCommand($args, $assoc_args) {
        $subcommand = $args[0] ?? 'help';
        
        switch ($subcommand) {
            case 'generate':
                $this->cliGenerate($assoc_args);
                break;
                
            case 'build':
                $this->cliBuild($assoc_args);
                break;
                
            case 'parse':
                $this->cliParse($assoc_args);
                break;
                
            default:
                $this->cliHelp();
        }
    }
    
    /**
     * CLI generate command
     */
    private function cliGenerate($args) {
        $type = $args['type'] ?? 'all';
        $format = $args['format'] ?? 'markdown';
        $output = $args['output'] ?? './docs';
        
        \WP_CLI::log("Generating $type documentation in $format format...");
        
        try {
            $this->manager->save($type, $format, $output);
            \WP_CLI::success("Documentation generated at: $output");
        } catch (\Exception $e) {
            \WP_CLI::error($e->getMessage());
        }
    }
    
    /**
     * CLI build command
     */
    private function cliBuild($args) {
        $output = $args['output'] ?? './docs-site';
        
        \WP_CLI::log("Building documentation site...");
        
        try {
            $this->manager->buildSite($output);
            \WP_CLI::success("Documentation site built at: $output");
        } catch (\Exception $e) {
            \WP_CLI::error($e->getMessage());
        }
    }
    
    /**
     * CLI parse command
     */
    private function cliParse($args) {
        $dir = $args['dir'] ?? '.';
        
        \WP_CLI::log("Parsing code in: $dir");
        
        $parser = CodeParser::getInstance();
        $stats = $parser->parseDirectory($dir);
        
        \WP_CLI::success("Parsed {$stats['files']} files, found {$stats['classes']} classes");
    }
    
    /**
     * CLI help
     */
    private function cliHelp() {
        \WP_CLI::log("
Money Quiz Documentation Commands:

  wp money-quiz docs generate [--type=<type>] [--format=<format>] [--output=<path>]
    Generate documentation
    
  wp money-quiz docs build [--output=<path>]
    Build documentation website
    
  wp money-quiz docs parse [--dir=<directory>]
    Parse code for documentation

Options:
  --type     Documentation type: all, api, code, user (default: all)
  --format   Output format: markdown, html, pdf (default: markdown)
  --output   Output directory (default: ./docs)
  --dir      Directory to parse (default: current directory)
");
    }
    
    /**
     * Add help tabs
     */
    public function addHelpTabs($screen) {
        if (strpos($screen->id, 'money-quiz') === false) {
            return;
        }
        
        $guide = UserGuideGenerator::getInstance();
        $help = $guide->generateContextualHelp($screen->id);
        
        if (!empty($help)) {
            $screen->add_help_tab([
                'id' => 'money-quiz-help',
                'title' => $help['title'],
                'content' => '<p>' . $help['content'] . '</p>' . 
                           '<ul><li>' . implode('</li><li>', $help['tips']) . '</li></ul>'
            ]);
        }
    }
    
    /**
     * Register REST endpoints
     */
    public function registerEndpoints() {
        register_rest_route('money-quiz/v1', '/documentation', [
            'methods' => 'GET',
            'callback' => [$this, 'getDocumentation'],
            'permission_callback' => function() {
                return current_user_can('edit_posts');
            },
            'args' => [
                'type' => [
                    'default' => 'api',
                    'validate_callback' => function($param) {
                        return in_array($param, ['api', 'code', 'user']);
                    }
                ],
                'format' => [
                    'default' => 'json',
                    'validate_callback' => function($param) {
                        return in_array($param, ['json', 'html']);
                    }
                ]
            ]
        ]);
    }
    
    /**
     * Get documentation via REST
     */
    public function getDocumentation($request) {
        $type = $request->get_param('type');
        $format = $request->get_param('format');
        
        try {
            $docs = $this->manager->generate($type, $format === 'json' ? 'markdown' : $format);
            
            return [
                'success' => true,
                'data' => $docs
            ];
        } catch (\Exception $e) {
            return new \WP_Error('generation_failed', $e->getMessage(), ['status' => 500]);
        }
    }
}

// Initialize documentation system
add_action('plugins_loaded', [DocumentationSystem::class, 'init']);

// AJAX handler for viewing docs
add_action('wp_ajax_money_quiz_view_docs', function() {
    $type = $_GET['type'] ?? 'user';
    $manager = DocumentationManager::getInstance();
    
    try {
        $content = $manager->generate($type, 'html');
        echo $content;
    } catch (\Exception $e) {
        wp_die('Error generating documentation: ' . $e->getMessage());
    }
    
    exit;
});
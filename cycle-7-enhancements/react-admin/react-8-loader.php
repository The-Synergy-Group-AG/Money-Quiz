<?php
/**
 * React Admin Loader
 * 
 * @package MoneyQuiz\React
 * @version 1.0.0
 */

namespace MoneyQuiz\React;

// Load React components
require_once __DIR__ . '/react-1-build-config.php';
require_once __DIR__ . '/react-2-api-client.php';
require_once __DIR__ . '/react-3-auth-provider.php';
require_once __DIR__ . '/react-4-data-provider.php';
require_once __DIR__ . '/react-5-component-registry.php';
require_once __DIR__ . '/react-6-route-config.php';
require_once __DIR__ . '/react-7-integration.php';

/**
 * React Manager
 */
class ReactManager {
    
    private static $instance = null;
    
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
     * Initialize React admin
     */
    public static function init() {
        // Check if admin area
        if (!is_admin()) {
            return;
        }
        
        // Initialize components
        BuildConfig::init();
        AuthProvider::register();
        ApiClient::registerScript();
        ComponentRegistry::register();
        RouteConfig::registerAdminPages();
        Integration::init();
        
        // Add initialization action
        add_action('admin_init', [__CLASS__, 'setupReactAdmin']);
        
        // Generate build files
        add_action('admin_init', [__CLASS__, 'generateBuildFiles']);
    }
    
    /**
     * Setup React admin
     */
    public static function setupReactAdmin() {
        // Create source directory
        $src_dir = plugin_dir_path(__FILE__) . 'src/';
        if (!file_exists($src_dir)) {
            wp_mkdir_p($src_dir);
        }
        
        // Generate main app file
        self::generateAppFile($src_dir);
        
        // Generate index file
        self::generateIndexFile($src_dir);
        
        // Generate styles
        self::generateStyles($src_dir);
    }
    
    /**
     * Generate app file
     */
    private static function generateAppFile($src_dir) {
        $app_content = <<<'JS'
// Main App Component
import React from 'react';
import { AuthProvider } from './auth-provider';
import { StoreProvider } from './data-provider';
import AppRouter from './route-config';
import './styles.css';

export default function App() {
    return (
        <AuthProvider>
            <StoreProvider>
                <AppRouter />
            </StoreProvider>
        </AuthProvider>
    );
}
JS;
        
        file_put_contents($src_dir . 'App.js', $app_content);
    }
    
    /**
     * Generate index file
     */
    private static function generateIndexFile($src_dir) {
        $index_content = <<<'JS'
// React Entry Point
import React from 'react';
import ReactDOM from 'react-dom';
import App from './App';

// Wait for DOM ready
document.addEventListener('DOMContentLoaded', () => {
    const root = document.getElementById('money-quiz-react-root');
    
    if (root) {
        ReactDOM.render(<App />, root);
        
        // Initialize WordPress integration
        if (window.MoneyQuizAdmin) {
            window.MoneyQuizAdmin.init();
        }
    }
});
JS;
        
        file_put_contents($src_dir . 'index.js', $index_content);
    }
    
    /**
     * Generate styles
     */
    private static function generateStyles($src_dir) {
        $styles = <<<'CSS'
/* Money Quiz React Admin Styles */
.money-quiz-react-admin {
    --mq-primary: #0073aa;
    --mq-secondary: #23282d;
    --mq-success: #46b450;
    --mq-danger: #dc3232;
    --mq-warning: #ffb900;
}

.mq-admin-layout {
    display: flex;
    flex-direction: column;
    min-height: 100vh;
}

.mq-main {
    display: flex;
    flex: 1;
}

.mq-sidebar {
    width: 250px;
    background: var(--mq-secondary);
    color: white;
    padding: 20px;
}

.mq-content {
    flex: 1;
    padding: 20px;
}

.mq-loading {
    text-align: center;
    padding: 40px;
}

.mq-error {
    background: #fee;
    border: 1px solid #fcc;
    padding: 20px;
    margin: 20px 0;
    border-radius: 4px;
}

/* Component styles */
.mq-dashboard .stat-card {
    background: white;
    border: 1px solid #ddd;
    padding: 20px;
    margin: 10px 0;
    border-radius: 4px;
}

.mq-quiz-list table {
    width: 100%;
    border-collapse: collapse;
}

.mq-quiz-list th,
.mq-quiz-list td {
    padding: 10px;
    border-bottom: 1px solid #ddd;
    text-align: left;
}
CSS;
        
        file_put_contents($src_dir . 'styles.css', $styles);
    }
    
    /**
     * Generate build files
     */
    public static function generateBuildFiles() {
        $build_dir = plugin_dir_path(__FILE__) . 'build/';
        
        if (!file_exists($build_dir)) {
            wp_mkdir_p($build_dir);
            
            // Generate asset file
            $asset_data = [
                'dependencies' => ['react', 'react-dom', 'wp-element', 'wp-components', 'wp-api-fetch'],
                'version' => '1.0.0'
            ];
            
            file_put_contents(
                $build_dir . 'index.asset.php',
                '<?php return ' . var_export($asset_data, true) . ';'
            );
        }
    }
}

// Initialize React Manager
add_action('plugins_loaded', [ReactManager::class, 'init']);
<?php
/**
 * React Build Configuration
 * 
 * @package MoneyQuiz\React
 * @version 1.0.0
 */

namespace MoneyQuiz\React;

/**
 * Build Configuration
 */
class BuildConfig {
    
    /**
     * Initialize build configuration
     */
    public static function init() {
        add_action('init', [__CLASS__, 'registerAssets']);
        add_action('admin_enqueue_scripts', [__CLASS__, 'enqueueAdminAssets']);
        add_filter('script_loader_tag', [__CLASS__, 'addModuleType'], 10, 3);
    }
    
    /**
     * Register React assets
     */
    public static function registerAssets() {
        $asset_file = plugin_dir_path(__FILE__) . 'build/index.asset.php';
        
        if (file_exists($asset_file)) {
            $asset = require $asset_file;
        } else {
            $asset = [
                'dependencies' => ['react', 'react-dom', 'wp-element', 'wp-components'],
                'version' => '1.0.0'
            ];
        }
        
        // Register React build
        wp_register_script(
            'money-quiz-react-admin',
            plugin_dir_url(__FILE__) . 'build/index.js',
            $asset['dependencies'],
            $asset['version'],
            true
        );
        
        // Register styles
        wp_register_style(
            'money-quiz-react-admin',
            plugin_dir_url(__FILE__) . 'build/index.css',
            ['wp-components'],
            $asset['version']
        );
        
        // Localize script
        wp_localize_script('money-quiz-react-admin', 'moneyQuizAdmin', [
            'apiUrl' => rest_url('money-quiz/v1'),
            'nonce' => wp_create_nonce('wp_rest'),
            'userId' => get_current_user_id(),
            'adminUrl' => admin_url(),
            'pluginUrl' => plugin_dir_url(dirname(__FILE__, 2)),
            'version' => $asset['version'],
            'debug' => defined('WP_DEBUG') && WP_DEBUG
        ]);
    }
    
    /**
     * Enqueue admin assets
     */
    public static function enqueueAdminAssets($hook) {
        // Only load on our admin pages
        if (!strpos($hook, 'money-quiz')) {
            return;
        }
        
        wp_enqueue_script('money-quiz-react-admin');
        wp_enqueue_style('money-quiz-react-admin');
        
        // Add inline script for initialization
        wp_add_inline_script('money-quiz-react-admin', '
            window.MoneyQuizAdmin = window.MoneyQuizAdmin || {};
            window.MoneyQuizAdmin.init = function() {
                console.log("Money Quiz React Admin initialized");
            };
        ', 'before');
    }
    
    /**
     * Add module type to script tags
     */
    public static function addModuleType($tag, $handle, $src) {
        if ($handle === 'money-quiz-react-admin') {
            $tag = '<script type="module" src="' . esc_url($src) . '"></script>';
        }
        return $tag;
    }
    
    /**
     * Get webpack configuration
     */
    public static function getWebpackConfig() {
        return [
            'entry' => './src/index.js',
            'output' => [
                'path' => __DIR__ . '/build',
                'filename' => 'index.js'
            ],
            'module' => [
                'rules' => [
                    [
                        'test' => '/\.(js|jsx|ts|tsx)$/',
                        'exclude' => '/node_modules/',
                        'use' => 'babel-loader'
                    ],
                    [
                        'test' => '/\.css$/',
                        'use' => ['style-loader', 'css-loader']
                    ]
                ]
            ],
            'externals' => [
                'react' => 'React',
                'react-dom' => 'ReactDOM',
                '@wordpress/element' => 'wp.element',
                '@wordpress/components' => 'wp.components',
                '@wordpress/api-fetch' => 'wp.apiFetch'
            ]
        ];
    }
    
    /**
     * Generate package.json
     */
    public static function generatePackageJson() {
        return [
            'name' => 'money-quiz-react-admin',
            'version' => '1.0.0',
            'scripts' => [
                'build' => 'webpack --mode production',
                'dev' => 'webpack --mode development --watch',
                'analyze' => 'webpack-bundle-analyzer build/stats.json'
            ],
            'dependencies' => [
                'react' => '^17.0.2',
                'react-dom' => '^17.0.2',
                'react-router-dom' => '^6.0.0',
                'axios' => '^0.24.0'
            ],
            'devDependencies' => [
                '@babel/core' => '^7.16.0',
                '@babel/preset-react' => '^7.16.0',
                'babel-loader' => '^8.2.3',
                'webpack' => '^5.64.0',
                'webpack-cli' => '^4.9.1'
            ]
        ];
    }
}
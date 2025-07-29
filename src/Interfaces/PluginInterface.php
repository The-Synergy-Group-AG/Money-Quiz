<?php
/**
 * Plugin Interface
 *
 * @package MoneyQuiz
 * @since 4.0.0
 */

namespace MoneyQuiz\Interfaces;

/**
 * Interface for main plugin class
 */
interface PluginInterface {
    
    /**
     * Run the plugin
     * 
     * @return void
     */
    public function run(): void;
    
    /**
     * Get plugin version
     * 
     * @return string
     */
    public function get_version(): string;
}
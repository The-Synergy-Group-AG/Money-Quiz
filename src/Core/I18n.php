<?php
/**
 * Internationalization functionality
 *
 * @package MoneyQuiz
 * @since 4.0.0
 */

namespace MoneyQuiz\Core;

/**
 * Define the internationalization functionality
 * 
 * Loads and defines the internationalization files for this plugin
 * so that it is ready for translation.
 */
class I18n {
    
    /**
     * @var string Text domain
     */
    private string $domain = 'money-quiz';
    
    /**
     * Load the plugin text domain for translation
     * 
     * @return void
     */
    public function load_plugin_textdomain(): void {
        load_plugin_textdomain(
            $this->domain,
            false,
            dirname( dirname( plugin_basename( MONEY_QUIZ_PLUGIN_FILE ) ) ) . '/languages/'
        );
    }
    
    /**
     * Get the text domain
     * 
     * @return string
     */
    public function get_domain(): string {
        return $this->domain;
    }
}
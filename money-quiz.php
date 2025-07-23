<?php
/**
 * Money Quiz
 *
 * @package           MoneyQuiz
 * @author            The Synergy Group AG
 * @copyright         2025 The Synergy Group AG
 * @license           GPL-2.0-or-later
 *
 * @wordpress-plugin
 * Plugin Name:       Money Quiz
 * Plugin URI:        https://thesynergygroup.ch/money-quiz
 * Description:       A secure, enterprise-grade financial assessment quiz plugin for WordPress
 * Version:           7.0.0
 * Requires at least: 5.9
 * Requires PHP:      7.4
 * Author:            The Synergy Group AG
 * Author URI:        https://thesynergygroup.ch
 * Text Domain:       money-quiz
 * Domain Path:       /languages
 * License:           GPL v2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Update URI:        https://thesynergygroup.ch/money-quiz
 */

namespace MoneyQuiz;

// Security: Prevent direct access.
defined( 'ABSPATH' ) || exit( 'Direct access is not allowed.' );

// Define plugin constants.
define( 'MONEY_QUIZ_VERSION', '7.0.0' );
define( 'MONEY_QUIZ_MINIMUM_WP_VERSION', '5.9' );
define( 'MONEY_QUIZ_MINIMUM_PHP_VERSION', '7.4' );
define( 'MONEY_QUIZ_PLUGIN_FILE', __FILE__ );
define( 'MONEY_QUIZ_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'MONEY_QUIZ_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'MONEY_QUIZ_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );

// Check PHP version compatibility.
if ( version_compare( PHP_VERSION, MONEY_QUIZ_MINIMUM_PHP_VERSION, '<' ) ) {
	add_action(
		'admin_notices',
		function() {
			printf(
				'<div class="notice notice-error"><p>%s</p></div>',
				sprintf(
					/* translators: %s: Required PHP version */
					esc_html__( 'Money Quiz requires PHP version %s or higher. Please upgrade PHP to use this plugin.', 'money-quiz' ),
					MONEY_QUIZ_MINIMUM_PHP_VERSION
				)
			);
		}
	);
	return;
}

// Check if Composer autoloader exists.
if ( ! file_exists( MONEY_QUIZ_PLUGIN_DIR . 'vendor/autoload.php' ) ) {
	add_action(
		'admin_notices',
		function() {
			printf(
				'<div class="notice notice-error"><p>%s</p></div>',
				esc_html__( 'Money Quiz is missing required dependencies. Please run "composer install" in the plugin directory.', 'money-quiz' )
			);
		}
	);
	return;
}

// Load Composer autoloader.
require_once MONEY_QUIZ_PLUGIN_DIR . 'vendor/autoload.php';

// Initialize plugin manager.
$plugin_manager = new Core\PluginManager();

// Register all plugin hooks through the manager.
$plugin_manager->register_hooks();

// Store reference for access.
Core\Container::set_plugin_manager( $plugin_manager );

// Note: Uninstall logic is handled in uninstall.php for security.
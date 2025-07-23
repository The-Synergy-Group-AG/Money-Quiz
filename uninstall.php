<?php
/**
 * Uninstall Money Quiz
 *
 * Removes all plugin data when uninstalled.
 *
 * @package MoneyQuiz
 * @since   7.0.0
 */

// Security check: Exit if uninstall not called from WordPress.
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

// Load only the UninstallHandler class to handle cleanup.
require_once __DIR__ . '/src/Core/UninstallHandler.php';

// Call the uninstall method which handles all cleanup securely.
MoneyQuiz\Core\UninstallHandler::uninstall();
<?php
/**
 * Hook Manager
 *
 * Manages WordPress hooks and filters for the plugin.
 *
 * @package MoneyQuiz\Core\Hooks
 * @since   7.0.0
 */

namespace MoneyQuiz\Core\Hooks;

// Security: Prevent direct access.
defined( 'ABSPATH' ) || exit;

/**
 * Hook manager class.
 *
 * @since 7.0.0
 */
class HookManager {

	/**
	 * Registered hooks.
	 *
	 * @var array<string, array>
	 */
	private array $hooks = [];

	/**
	 * Initialize hook manager.
	 *
	 * @since 7.0.0
	 *
	 * @return void
	 */
	public function init(): void {
		// Process registered hooks.
		$this->process_hooks();
	}

	/**
	 * Add action hook.
	 *
	 * @since 7.0.0
	 *
	 * @param string   $tag      Hook tag.
	 * @param callable $callback Callback function.
	 * @param int      $priority Priority.
	 * @param int      $args     Number of arguments.
	 * @return void
	 */
	public function add_action( string $tag, callable $callback, int $priority = 10, int $args = 1 ): void {
		$this->hooks[] = [
			'type' => 'action',
			'tag' => $tag,
			'callback' => $callback,
			'priority' => $priority,
			'args' => $args,
		];
	}

	/**
	 * Add filter hook.
	 *
	 * @since 7.0.0
	 *
	 * @param string   $tag      Hook tag.
	 * @param callable $callback Callback function.
	 * @param int      $priority Priority.
	 * @param int      $args     Number of arguments.
	 * @return void
	 */
	public function add_filter( string $tag, callable $callback, int $priority = 10, int $args = 1 ): void {
		$this->hooks[] = [
			'type' => 'filter',
			'tag' => $tag,
			'callback' => $callback,
			'priority' => $priority,
			'args' => $args,
		];
	}

	/**
	 * Process all registered hooks.
	 *
	 * @since 7.0.0
	 *
	 * @return void
	 */
	private function process_hooks(): void {
		foreach ( $this->hooks as $hook ) {
			if ( $hook['type'] === 'action' ) {
				add_action( $hook['tag'], $hook['callback'], $hook['priority'], $hook['args'] );
			} else {
				add_filter( $hook['tag'], $hook['callback'], $hook['priority'], $hook['args'] );
			}
		}
	}

	/**
	 * Remove all hooks.
	 *
	 * @since 7.0.0
	 *
	 * @return void
	 */
	public function remove_all(): void {
		foreach ( $this->hooks as $hook ) {
			if ( $hook['type'] === 'action' ) {
				remove_action( $hook['tag'], $hook['callback'], $hook['priority'] );
			} else {
				remove_filter( $hook['tag'], $hook['callback'], $hook['priority'] );
			}
		}
		$this->hooks = [];
	}

	/**
	 * Get registered hooks.
	 *
	 * @since 7.0.0
	 *
	 * @return array<string, array> Registered hooks.
	 */
	public function get_hooks(): array {
		return $this->hooks;
	}
}
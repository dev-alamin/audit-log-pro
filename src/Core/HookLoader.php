<?php
namespace Amin\AuditLogPro\Core;

/**
 * Hook Loader
 *
 * Wrapper for all hooks for testing
 *
 * @since 1.0.0
 * @package AuditLogPro
 */
class HookLoader {
	/**
	 * All actions
	 *
	 * @var array
	 */
	private array $actions = array();

	/**
	 * Add action wrapper
	 *
	 * Store all actions for running later
	 *
	 * @param string   $hook
	 * @param callable $callback
	 * @param integer  $priority
	 * @param integer  $accepted_args
	 * @return void
	 */
	public function add_action( string $hook, callable $callback, int $priority = 10, int $accepted_args = 1 ): void {
		$this->actions[] = compact( 'hook', 'callback', 'priority', 'accepted_args' );
	}

	/**
	 * Run the registered action
	 *
	 * @return void
	 */
	public function run(): void {
		foreach ( $this->actions as $action ) {
			add_action( $action['hook'], $action['callback'], $action['priority'], $action['accepted_args'] );
		}
	}
}

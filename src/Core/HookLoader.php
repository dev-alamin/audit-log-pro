<?php

namespace Amin\AuditLogPro\Core;

/**
 * Hook Loader.
 *
 * Stores WordPress hooks and registers them later.
 *
 * @since 1.0.0
 * @package AuditLogPro
 */
class HookLoader {

	/**
	 * Registered actions.
	 *
	 * @var array<int, array{
	 *     hook: string,
	 *     callback: callable,
	 *     priority: int,
	 *     accepted_args: int
	 * }>
	 */
	private array $actions = array();

	/**
	 * Queue an action for registration.
	 *
	 * @param string   $hook          Hook name.
	 * @param callable $callback      Callback function.
	 * @param int      $priority      Hook priority.
	 * @param int      $accepted_args Number of accepted arguments.
	 * @return void
	 */
	public function add_action(
		string $hook,
		callable $callback,
		int $priority = 10,
		int $accepted_args = 1
	): void {
		$this->actions[] = array(
			'hook'          => $hook,
			'callback'      => $callback,
			'priority'      => $priority,
			'accepted_args' => $accepted_args,
		);
	}

	/**
	 * Register all queued actions with WordPress.
	 *
	 * @return void
	 */
	public function run(): void {
		foreach ( $this->actions as $action ) {
			add_action(
				$action['hook'],
				$action['callback'],
				$action['priority'],
				$action['accepted_args']
			);
		}
	}
}

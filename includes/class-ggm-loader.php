<?php
/**
 * Hook loader — collects actions and filters and registers them with WordPress.
 *
 * @package GGM_Member_Dashboard
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class GGM_Loader
 *
 * Maintains a list of all actions and filters registered for the plugin,
 * then runs them all on demand.
 */
class GGM_Loader {

	/**
	 * @var array $actions Registered action hooks.
	 */
	protected $actions = array();

	/**
	 * @var array $filters Registered filter hooks.
	 */
	protected $filters = array();

	/**
	 * Add an action hook.
	 *
	 * @param string $hook          The name of the WordPress action.
	 * @param object $component     The object instance.
	 * @param string $callback      The method on the instance to invoke.
	 * @param int    $priority      Priority at which the function should be fired.
	 * @param int    $accepted_args Number of arguments the function accepts.
	 */
	public function add_action( $hook, $component, $callback, $priority = 10, $accepted_args = 1 ) {
		$this->actions = $this->add( $this->actions, $hook, $component, $callback, $priority, $accepted_args );
	}

	/**
	 * Add a filter hook.
	 *
	 * @param string $hook          The name of the WordPress filter.
	 * @param object $component     The object instance.
	 * @param string $callback      The method on the instance to invoke.
	 * @param int    $priority      Priority at which the function should be fired.
	 * @param int    $accepted_args Number of arguments the function accepts.
	 */
	public function add_filter( $hook, $component, $callback, $priority = 10, $accepted_args = 1 ) {
		$this->filters = $this->add( $this->filters, $hook, $component, $callback, $priority, $accepted_args );
	}

	/**
	 * Utility: add entry to array.
	 *
	 * @param array  $hooks
	 * @param string $hook
	 * @param object $component
	 * @param string $callback
	 * @param int    $priority
	 * @param int    $accepted_args
	 * @return array
	 */
	private function add( $hooks, $hook, $component, $callback, $priority, $accepted_args ) {
		$hooks[] = array(
			'hook'          => $hook,
			'component'     => $component,
			'callback'      => $callback,
			'priority'      => $priority,
			'accepted_args' => $accepted_args,
		);
		return $hooks;
	}

	/**
	 * Register all hooks with WordPress.
	 */
	public function run() {
		foreach ( $this->filters as $hook ) {
			add_filter(
				$hook['hook'],
				array( $hook['component'], $hook['callback'] ),
				$hook['priority'],
				$hook['accepted_args']
			);
		}
		foreach ( $this->actions as $hook ) {
			add_action(
				$hook['hook'],
				array( $hook['component'], $hook['callback'] ),
				$hook['priority'],
				$hook['accepted_args']
			);
		}
	}
}

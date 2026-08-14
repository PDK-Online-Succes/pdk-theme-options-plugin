<?php
/**
 * Registreert WordPress actions en filters op een centrale plek.
 * Modules voegen hun hooks hier aan toe; run() registreert ze allemaal.
 */

defined( 'ABSPATH' ) || exit;

class PDK_Loader {

	/** @var array<array{hook:string,component:object|string,callback:string,priority:int,args:int}> */
	private array $actions = [];

	/** @var array<array{hook:string,component:object|string,callback:string,priority:int,args:int}> */
	private array $filters = [];

	public function add_action(
		string $hook,
		object|string $component,
		string $callback,
		int $priority = 10,
		int $args = 1
	): void {
		$this->actions[] = compact( 'hook', 'component', 'callback', 'priority', 'args' );
	}

	public function add_filter(
		string $hook,
		object|string $component,
		string $callback,
		int $priority = 10,
		int $args = 1
	): void {
		$this->filters[] = compact( 'hook', 'component', 'callback', 'priority', 'args' );
	}

	/** Registreert alle verzamelde hooks bij WordPress. */
	public function run(): void {
		foreach ( $this->actions as $a ) {
			add_action( $a['hook'], [ $a['component'], $a['callback'] ], $a['priority'], $a['args'] );
		}
		foreach ( $this->filters as $f ) {
			add_filter( $f['hook'], [ $f['component'], $f['callback'] ], $f['priority'], $f['args'] );
		}
	}
}

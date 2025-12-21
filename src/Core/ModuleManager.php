<?php

namespace Elemacy\Core;

use WP_Error;

class ModuleManager {
	private array $modules = [];
	private array $active_modules = [];
	private array $initialized_modules = [];

	public function register( Module $module ): void {
		$module_name = $module->get_name();
		$this->modules[ $module_name ] = $module;
	}

	public function get_modules(): array {
		return $this->modules;
	}

	public function get_module( string $name ): ?Module {
		return $this->modules[ $name ] ?? null;
	}

	public function get_active_modules(): array {
		return $this->active_modules;
	}

	public function is_active( string $module_name ): bool {
		return in_array( $module_name, $this->active_modules, true );
	}

	public function enable_module( string $module_name ) {
		if ( ! isset( $this->modules[ $module_name ] ) ) {
			return new WP_Error( 'module_not_found', __( 'Module not found.', 'elemacy' ) );
		}

		$dependencies_met = $this->check_dependencies( $module_name );
		if ( is_wp_error( $dependencies_met ) ) {
			return $dependencies_met;
		}

		$active_modules = get_option( 'elemacy_active_modules', [] );
		if ( ! in_array( $module_name, $active_modules, true ) ) {
			$active_modules[] = $module_name;
			update_option( 'elemacy_active_modules', $active_modules );
		}

		$this->load_active_modules();

		return true;
	}

	public function disable_module( string $module_name ) {
		$dependents = $this->get_dependent_modules( $module_name );
		if ( ! empty( $dependents ) ) {
			return new WP_Error(
				'has_dependents',
				sprintf(
					__( 'Cannot disable module. The following modules depend on it: %s', 'elemacy' ),
					implode( ', ', $dependents )
				)
			);
		}

		$active_modules = get_option( 'elemacy_active_modules', [] );
		$active_modules = array_diff( $active_modules, array( $module_name ) );
		update_option( 'elemacy_active_modules', $active_modules );

		$this->load_active_modules();

		return true;
	}

	public function init_modules(): void {
		$this->load_active_modules();
		$this->initialize_modules();
	}

	private function load_active_modules(): void {
		$active_module_names = get_option( 'elemacy_active_modules', [] );
		$this->active_modules = [];

		foreach ( $active_module_names as $module_name ) {
			if ( isset( $this->modules[ $module_name ] ) ) {
				$this->active_modules[ $module_name ] = $this->modules[ $module_name ];
			}
		}
	}

	private function initialize_modules(): void {
		$sorted_modules = $this->sort_by_dependencies( $this->active_modules );

		foreach ( $sorted_modules as $module ) {
			$module_name = $module->get_name();
			if ( ! isset( $this->initialized_modules[ $module_name ] ) ) {
				$module->init();
				$module->register_routes();
				$module->register_assets();
				$this->initialized_modules[ $module_name ] = true;
			}
		}
	}

	private function check_dependencies( string $module_name ) {
		$module = $this->modules[ $module_name ];
		$dependencies = $module->get_dependencies();

		foreach ( $dependencies as $dependency ) {
			if ( ! isset( $this->modules[ $dependency ] ) ) {
				return new WP_Error(
					'dependency_not_found',
					sprintf( __( 'Required dependency "%s" not found.', 'elemacy' ), $dependency )
				);
			}

			if ( ! $this->is_active( $dependency ) ) {
				return new WP_Error(
					'dependency_not_active',
					sprintf( __( 'Required dependency "%s" is not active.', 'elemacy' ), $dependency )
				);
			}
		}

		return true;
	}

	private function get_dependent_modules( string $module_name ): array {
		$dependents = [];

		foreach ( $this->active_modules as $name => $module ) {
			if ( in_array( $module_name, $module->get_dependencies(), true ) ) {
				$dependents[] = $name;
			}
		}

		return $dependents;
	}

	private function sort_by_dependencies( array $modules ): array {
		$sorted = [];
		$visited = [];

		foreach ( $modules as $module ) {
			$this->visit_module( $module, $modules, $visited, $sorted );
		}

		return $sorted;
	}

	private function visit_module( Module $module, array $all_modules, array &$visited, array &$sorted ): void {
		$module_name = $module->get_name();

		if ( isset( $visited[ $module_name ] ) ) {
			return;
		}

		$visited[ $module_name ] = true;

		foreach ( $module->get_dependencies() as $dependency ) {
			if ( isset( $all_modules[ $dependency ] ) ) {
				$this->visit_module( $all_modules[ $dependency ], $all_modules, $visited, $sorted );
			}
		}

		$sorted[] = $module;
	}
}

<?php

namespace Elemacy\Core;

class Elemacy
{
	protected static $instance = null;
	protected ModuleManager $module_manager;

	protected function __construct()
	{
		add_action('init', [$this, 'load_textdomain']);
		add_action('plugins_loaded', [$this, 'init']);
	}

	public static function get_instance(): self
	{
		if (static::$instance === null) {
			static::$instance = new static();
		}

		return static::$instance;
	}

	public function init()
	{
		$this->check_requirements();
		$this->init_core_components();
		$this->load_modules();
		$this->init_modules();
		$this->init_routes();
		$this->register_rest_routes();
		$this->register_ajax_routes();
	}

	public function load_textdomain()
	{
		load_plugin_textdomain('elemacy', false, dirname(ELEMACY_PLUGIN_BASE) . '/languages');
	}

	public function init_core_components()
	{
		new AdminMenu();
		new AdminScripts();
		new FrontendScripts();

		$this->module_manager = new ModuleManager();
	}

	protected function load_modules()
	{
		$modules = require_once ELEMACY_PATH . 'src/Config/modules.php';
		foreach ($modules as $module_class) {
			if (!class_exists($module_class)) {
				continue;
			}

			$module = new $module_class();

			if (is_subclass_of($module, Module::class)) {
				$this->module_manager->register($module);
			}
		}
	}

	protected function init_modules()
	{
		$this->module_manager->init_modules();
	}

	protected function init_routes()
	{
		Route::set_namespace('elemacy');
		require_once ELEMACY_PATH . 'src/Config/api.php';
	}

	protected function register_rest_routes()
	{
		add_action('rest_api_init', function () {
			foreach (Route::get_routes() as $route) {
				$route->register();
			}
		});
	}

	protected function register_ajax_routes()
	{
		add_action('init', function () {
			AjaxRouter::register();
		});
	}

	public function get_module_manager(): ModuleManager
	{
		return $this->module_manager;
	}

	public function check_requirements(): void
	{
		if (!did_action('elementor/loaded')) {
			add_action('admin_notices', [$this, 'elementor_missing_notice']);
		}

		if (version_compare(PHP_VERSION, '7.4', '<')) {
			add_action('admin_notices', [$this, 'php_version_notice']);
		}
	}

	public function elementor_missing_notice(): void
	{
		$message = sprintf(
			/* translators: %s: Elementor */
			__('Elemacy requires %s to be installed and activated.', 'elemacy'),
			'<strong>' . __('Elementor', 'elemacy') . '</strong>'
		);

		printf('<div class="notice notice-error"><p>%s</p></div>', wp_kses_post($message));
	}

	public function php_version_notice(): void
	{
		$message = sprintf(
			/* translators: %s: PHP version */
			__('Elemacy requires PHP version %s or greater.', 'elemacy'),
			'<strong>7.4</strong>'
		);

		printf('<div class="notice notice-error"><p>%s</p></div>', wp_kses_post($message));
	}

	public static function boot()
	{
		return static::get_instance();
	}
}

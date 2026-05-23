<?php

namespace Elemacy\Core;

if (!defined('ABSPATH')) {
	exit;
}

use Elemacy\Core\Constants\OptionKeys;
use Elemacy\Core\DTO\MenuDTO;
use Elemacy\Core\DTO\SubMenuDTO;
use Elemacy\Core\Hooks;

class Elemacy
{
	protected static $instance = null;
	protected ModuleManager $module_manager;

	protected function __construct()
	{
		$this->activation_actions();
		add_action('plugins_loaded', [$this, 'init']);
	}

	public static function get_instance(): self
	{
		if (static::$instance === null) {
			static::$instance = new static();
		}

		return static::$instance;
	}

	public function activation_actions()
	{
		register_activation_hook(ELEMACY_FILE, function () {
			add_option(OptionKeys::ACTIVE_MODULES, ['theme-builder', 'widgets']);
		});
	}

	public function init()
	{
		$this->load_textdomain();
		$this->handle_version_update();
		$this->check_requirements();
		$this->init_core_components();
		$this->load_modules();
		$this->init_admin_menus();
		$this->init_modules();
		$this->init_routes();
		$this->register_rest_routes();
		$this->register_ajax_routes();
		$this->register_admin_menus();

		do_action(Hooks::LOADED);
	}

	public function load_textdomain()
	{
		load_plugin_textdomain('elemacy', false, dirname(ELEMACY_PLUGIN_BASE) . '/languages');
	}

	function handle_version_update()
	{
		$installed_version = get_option(OptionKeys::DB_VERSION, false);

		if ($installed_version === ELEMACY_VERSION) {
			return;
		}

		if (version_compare($installed_version, ELEMACY_VERSION, '<')) {
			// @todo: implement later if needed
		}

		update_option(OptionKeys::DB_VERSION, ELEMACY_VERSION);
	}

	public function init_core_components()
	{
		new AdminScripts();
		new FrontendScripts();

		$this->module_manager = new ModuleManager();
	}

	public function get_module_manager(): ModuleManager
	{
		return $this->module_manager;
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

		do_action(Hooks::REGISTER_MODULES, $this->module_manager);
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

	protected function init_admin_menus()
	{
		add_action('init', function () {
			$plugin_menu_dto = new MenuDTO();
			$plugin_menu_dto->page_title = __('Elemacy', 'elemacy');
			$plugin_menu_dto->menu_title = __('Elemacy', 'elemacy');
			$plugin_menu_dto->menu_slug = 'elemacy';
			AdminMenu::add_menu($plugin_menu_dto);

			$overview_menu_dto = new SubMenuDTO();
			$overview_menu_dto->page_title = __('Overview', 'elemacy');
			$overview_menu_dto->menu_title = __('Overview', 'elemacy');
			$overview_menu_dto->menu_slug = '';
			AdminMenu::add_submenu($overview_menu_dto);

			$modules_menu_dto = new SubMenuDTO();
			$modules_menu_dto->page_title = __('Modules', 'elemacy');
			$modules_menu_dto->menu_title = __('Modules', 'elemacy');
			$modules_menu_dto->menu_slug = 'modules';
			AdminMenu::add_submenu($modules_menu_dto);
		});
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

	protected function register_admin_menus()
	{
		add_action('admin_menu', function () {
			AdminMenu::register();
		});
	}

	public function check_requirements(): void
	{
		CompatibilityChecker::instance()->check();
	}

	public static function boot()
	{
		return static::get_instance();
	}
}

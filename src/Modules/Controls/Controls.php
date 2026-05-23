<?php

namespace Elemacy\Modules\Controls;

defined('ABSPATH') || exit;

use Elemacy\Core\Hooks;
use Elemacy\Core\Module;
use Elemacy\Modules\Controls\Services\ControlRegistry;
use Elemacy\Modules\Controls\Services\FrontendAssets;

class Controls extends Module
{
    protected ?ControlRegistry $registry = null;

    public function get_name(): string
    {
        return 'controls';
    }

    public function get_title(): string
    {
        return __('Controls', 'elemacy');
    }

    public function get_icon(): string
    {
        return 'file-cog';
    }

    public function get_description(): string
    {
        return __('Additional controls for Elementor.', 'elemacy');
    }

    public function is_headless(): bool
    {
        return true;
    }

    public function init(): void
    {
        $this->registry = new ControlRegistry();

        foreach ($this->load_internal_controls() as $class) {
            $this->registry->register(new $class());
        }

        // Defer firing REGISTER_CONTROLS until `elemacy_loaded` so other modules
        // (notably pro modules, which init after free) have a chance to attach
        // their listener during their own init().
        add_action(Hooks::LOADED, function () {
            do_action(Hooks::REGISTER_CONTROLS, $this->registry);
            $this->registry->attach_listeners();
        });

        new FrontendAssets($this->registry);
    }

    public function get_registry(): ?ControlRegistry
    {
        return $this->registry;
    }

    /**
     * @return array<int, class-string>
     */
    protected function load_internal_controls(): array
    {
        $file = __DIR__ . '/Config/controls.php';

        if (!file_exists($file)) {
            return [];
        }

        return (array) require $file;
    }
}

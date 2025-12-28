<?php

namespace Elemacy\Modules\ThemeBuilder;

use Elemacy\Core\Module;
use Elemacy\Modules\ThemeBuilder\PostTypes\TemplatePostType;

class ThemeBuilder extends Module {
    public function get_name(): string {
        return 'theme-builder';
    }

    public function get_title(): string {
        return 'Theme Builder';
    }

    public function get_description(): string {
        return 'Theme Builder Module';
    }

    public function get_dependencies(): array {
        return [];
    }

    public function init(): void {
        TemplatePostType::register();
    }

    public function register_routes() {
        require_once __DIR__ . '/Config/api.php';   
    }
}
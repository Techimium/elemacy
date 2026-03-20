<?php

namespace Elemacy\Modules\Widgets\Bridges;

use Elemacy\Modules\ThemeBuilder\DTO\TemplateListFilterDTO;
use Elemacy\Modules\ThemeBuilder\Services\TemplateService;

class ThemeBuilderBridge
{
    protected static $instance = null;
    protected $template_service;

    protected function __construct()
    {
        $this->template_service = new TemplateService();
    }

    public static function get_instance()
    {
        if (is_null(self::$instance)) {
            self::$instance = new static();
        }

        return self::$instance;
    }

    public function get_templates($type = 'loop')
    {
        return $this->template_service->get_all(TemplateListFilterDTO::from_array([
            'type' => $type,
        ]));
    }
}
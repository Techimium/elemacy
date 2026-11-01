<?php

namespace Elemacy\Widgets;

defined('ABSPATH') || exit;

use Elementor\Widget_Base;

/**
 * @api
 */
abstract class BaseWidget extends Widget_Base
{
    public function get_categories()
    {
        return ['elemacy'];
    }

    public function get_icon()
    {
        return 'eicon-code';
    }
}

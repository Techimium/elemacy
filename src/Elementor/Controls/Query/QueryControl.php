<?php

namespace Elemacy\Elementor\Controls\Query;

defined('ABSPATH') || exit;

use Elementor\Control_Select2;

/**
 * A select2 control with live, server-backed search — the editor fetches
 * matching options over AJAX as the user types instead of pre-rendering a
 * fixed option list, the way Elementor Pro's "query" control works.
 */
class QueryControl extends Control_Select2
{
    public const TYPE = 'elemacy_query';

    public function get_type()
    {
        return self::TYPE;
    }

    protected function get_default_settings()
    {
        return array_merge(parent::get_default_settings(), [
            'autocomplete' => [
                'object' => QueryService::OBJECT_POST,
                'query'  => [],
            ],
        ]);
    }
}

<?php

namespace Elemacy\Core\Controls\Query;

defined('ABSPATH') || exit;

use Elementor\Control_Select2;

/**
 * A select2 control with live, server-backed search — the editor fetches matching
 * options over AJAX as the user types instead of pre-rendering a fixed option list,
 * the way Elementor Pro's "query" control works.
 *
 * Reuse it from any document/widget by adding a control of this type and passing an
 * `autocomplete` config describing what to search:
 *
 *   $this->add_control('my_post', [
 *       'type'         => QueryControl::TYPE,
 *       'label_block'  => true,
 *       'autocomplete' => ['object' => QueryService::OBJECT_POST, 'query' => ['post_type' => ['post']]],
 *   ]);
 *
 * The matching AJAX endpoints and editor view live in QueryControlManager and
 * assets/editor/query-control.js.
 */
class QueryControl extends Control_Select2
{
    const TYPE = 'elemacy_query';

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

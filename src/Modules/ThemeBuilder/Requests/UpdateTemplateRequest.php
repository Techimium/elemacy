<?php

namespace Elemacy\Modules\ThemeBuilder\Requests;

defined('ABSPATH') || exit;

use Elemacy\Core\Hooks;
use Elemacy\Core\Http\Request;
use Elemacy\Core\Sanitizer;

class UpdateTemplateRequest extends Request
{
    public function rules()
    {
        return apply_filters(
            Hooks::THEME_BUILDER_REQUEST_RULES_FILTER,
            [
                'id' => 'required|integer',
                'title' => 'required|string',
                'type' => 'required|string',
                'status' => 'nullable|string',
                'extras' => 'nullable|array',
            ],
            'update'
        );
    }

    public function filters()
    {
        return apply_filters(
            Hooks::THEME_BUILDER_REQUEST_FILTERS_FILTER,
            [
                'id' => Sanitizer::INT,
                'title' => Sanitizer::TEXT,
                'type' => Sanitizer::TEXT,
                'status' => Sanitizer::TEXT,
                'extras' => Sanitizer::ARRAY,
            ],
            'update'
        );
    }
}

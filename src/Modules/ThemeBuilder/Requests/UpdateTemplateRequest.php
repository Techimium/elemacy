<?php

namespace Elemacy\Modules\ThemeBuilder\Requests;

defined('ABSPATH') || exit;

use Elemacy\Core\Http\Request;
use Elemacy\Core\Sanitizer;

class UpdateTemplateRequest extends Request
{
    public function rules()
    {
        return [
            'id' => 'required|integer',
            'title' => 'required|string',
            'type' => 'required|string',
            'status' => 'nullable|string|in:publish,draft,trash',
            'conditions' => 'nullable|array',
            'conditions.*.id' => 'string',
            'conditions.*.type' => 'string',
            'conditions.*.operator' => 'required|string|in:include,exclude',
            'conditions.*.value' => 'string',
        ];
    }

    public function filters()
    {
        return [
            'id' => Sanitizer::INT,
            'title' => Sanitizer::TEXT,
            'type' => Sanitizer::TEXT,
            'status' => Sanitizer::TEXT,
            'conditions' => Sanitizer::ARRAY,
            'conditions.*.id' => Sanitizer::TEXT,
            'conditions.*.type' => Sanitizer::TEXT,
            'conditions.*.operator' => Sanitizer::TEXT,
            'conditions.*.value' => Sanitizer::TEXT,
        ];
    }
}

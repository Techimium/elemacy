<?php

namespace Elemacy\Modules\ThemeBuilder\Requests;

defined('ABSPATH') || exit;

use Elemacy\Core\Http\Request;
use Elemacy\Core\Sanitizer;
use Elemacy\TemplateLibrary\TypeRegistry;

class UpdateTemplateRequest extends Request
{
    public function rules()
    {
        return [
            'id' => 'required|integer',
            'title' => 'required|string',
            // Derived from the registry so new theme-group types are accepted
            // without touching this rule (types register on init, before REST dispatch).
            'type' => 'required|string|in:' . implode(',', TypeRegistry::instance()->names_in_group('theme')),
            'status' => 'nullable|string',
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

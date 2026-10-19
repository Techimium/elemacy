<?php

namespace Elemacy\Modules\ThemeBuilder\Requests;

defined('ABSPATH') || exit;

use Elemacy\Http\Request;
use Elemacy\Http\Sanitizer;
use Elemacy\TemplateLibrary\TypeRegistry;

class CreateTemplateRequest extends Request
{
    public function rules()
    {
        return [
            'title' => 'required|string',
            // Derived from the registry so new theme-group types are accepted
            // without touching this rule (types register on init, before REST dispatch).
            'type' => 'required|string|in:' . implode(',', TypeRegistry::instance()->names_in_group('theme')),
            // Trash is reached by deleting; private, pending and scheduled
            // statuses are Elementor's to set, never this form's.
            'status' => 'nullable|string|in:publish,draft',
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

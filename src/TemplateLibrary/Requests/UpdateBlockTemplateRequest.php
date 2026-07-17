<?php

namespace Elemacy\TemplateLibrary\Requests;

defined('ABSPATH') || exit;

use Elemacy\Core\Http\Request;
use Elemacy\Core\Sanitizer;
use Elemacy\TemplateLibrary\TypeRegistry;

class UpdateBlockTemplateRequest extends Request
{
    public function rules()
    {
        return [
            'id' => 'required|integer',
            'title' => 'required|string',
            // Derived from the registry so new block-group types are accepted
            // without touching this rule (types register on init, before REST dispatch).
            'type' => 'required|string|in:' . implode(',', TypeRegistry::instance()->names_in_group('block')),
            'status' => 'nullable|string',
        ];
    }

    public function filters()
    {
        return [
            'id' => Sanitizer::INT,
            'title' => Sanitizer::TEXT,
            'type' => Sanitizer::TEXT,
            'status' => Sanitizer::TEXT,
        ];
    }
}

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
            'type' => 'required|string|in:' . implode(',', TypeRegistry::instance()->names_in_group('block')),
            'status' => 'nullable|string|in:publish,draft,trash',
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

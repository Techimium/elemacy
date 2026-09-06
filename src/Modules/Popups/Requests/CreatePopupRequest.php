<?php

namespace Elemacy\Modules\Popups\Requests;

defined('ABSPATH') || exit;

use Elemacy\Core\Http\Request;
use Elemacy\Core\Sanitizer;
use Elemacy\TemplateLibrary\TypeRegistry;

class CreatePopupRequest extends Request
{
    public function rules()
    {
        return [
            'title' => 'required|string',
            'type' => 'required|string|in:' . implode(',', TypeRegistry::instance()->names_in_group('popup')),
            'status' => 'nullable|string|in:publish,draft,trash',
            'conditions' => 'nullable|array',
            'conditions.*.id' => 'string',
            'conditions.*.type' => 'string',
            'conditions.*.operator' => 'required|string|in:include,exclude',
            'conditions.*.value' => 'string',
            'triggers' => 'nullable|array',
            'triggers.*.id' => 'string',
            'triggers.*.type' => 'required|string',
            'triggers.*.params' => 'nullable|array',
            'rules' => 'nullable|array',
            'rules.*.id' => 'string',
            'rules.*.type' => 'required|string',
            'rules.*.params' => 'nullable|array',
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
            'triggers' => Sanitizer::ARRAY,
            'triggers.*.id' => Sanitizer::TEXT,
            'triggers.*.type' => Sanitizer::TEXT,
            'triggers.*.params' => Sanitizer::ARRAY_DEEP,
            'rules' => Sanitizer::ARRAY,
            'rules.*.id' => Sanitizer::TEXT,
            'rules.*.type' => Sanitizer::TEXT,
            'rules.*.params' => Sanitizer::ARRAY_DEEP,
        ];
    }
}

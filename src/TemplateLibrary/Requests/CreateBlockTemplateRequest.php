<?php

namespace Elemacy\TemplateLibrary\Requests;

defined('ABSPATH') || exit;

use Elemacy\Core\Http\Request;
use Elemacy\Core\Sanitizer;

class CreateBlockTemplateRequest extends Request
{
    public function rules()
    {
        return [
            'title' => 'required|string',
            'type' => 'required|string',
            'status' => 'nullable|string',
        ];
    }

    public function filters()
    {
        return [
            'title' => Sanitizer::TEXT,
            'type' => Sanitizer::TEXT,
            'status' => Sanitizer::TEXT,
        ];
    }
}

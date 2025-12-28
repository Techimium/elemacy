<?php

namespace Elemacy\Modules\ThemeBuilder\Requests;

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
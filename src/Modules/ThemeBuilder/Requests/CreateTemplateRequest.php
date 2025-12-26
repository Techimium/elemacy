<?php

namespace Elemacy\Modules\ThemeBuilder\Requests;

use Elemacy\Core\Http\Request;
use Elemacy\Core\Sanitizer;

class CreateTemplateRequest extends Request {
    public function rules() {
        return [
            'name' => 'required|string',
            'content' => 'required|string',
        ];
    }

    public function filters() {
        return [
            'name' => Sanitizer::TEXT,
            'content' => Sanitizer::TEXT,
        ];
    }
}
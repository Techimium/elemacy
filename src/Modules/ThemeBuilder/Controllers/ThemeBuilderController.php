<?php

namespace Elemacy\Modules\ThemeBuilder\Controllers;

use Elemacy\Core\Http\Response;

class ThemeBuilderController {
    public function index() {
        return Response::create()->json([
            'message' => 'Theme Builder Controller'
        ]); 
    }
}
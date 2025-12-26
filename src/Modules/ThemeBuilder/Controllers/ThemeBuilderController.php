<?php

namespace Elemacy\Modules\ThemeBuilder\Controllers;

use Elemacy\Core\Http\Request;
use Elemacy\Core\Http\Response;

class ThemeBuilderController {
    public function index(Request $request) {
        return Response::create()->json([
            'message' => 'Theme Builder Controller',
            'data' => $request->all()
        ]); 
    }
}
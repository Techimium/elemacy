<?php

namespace Elemacy\Core\Controllers;

use Elemacy\Core\Http\Response;

class ModuleController {
    public function index() {
        return Response::create()->json([
            'message' => 'Module Controller'
        ]); 
    }
}
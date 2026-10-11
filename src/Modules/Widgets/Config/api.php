<?php
if (!defined('ABSPATH')) {
    exit;
}

use Elemacy\Http\Middlewares\AdminMiddleware;
use Elemacy\Http\Route;
use Elemacy\Modules\Widgets\Controllers\WidgetsApiController;

Route::get('/widgets', [WidgetsApiController::class, 'index'])->middleware(AdminMiddleware::class);
Route::put('/widgets/{name}/toggle', [WidgetsApiController::class, 'toggle'])->middleware(AdminMiddleware::class);

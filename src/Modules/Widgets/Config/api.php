<?php

use Elemacy\Core\Middlewares\API\AdminMiddleware;
use Elemacy\Core\Route;
use Elemacy\Modules\Widgets\Controllers\WidgetsApiController;

Route::get('/widgets', [WidgetsApiController::class, 'index'])->middleware(AdminMiddleware::class);
Route::put('/widgets/{name}/toggle', [WidgetsApiController::class, 'toggle'])->middleware(AdminMiddleware::class);

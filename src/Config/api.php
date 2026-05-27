<?php
if (!defined('ABSPATH')) {
    exit;
}

use Elemacy\Core\Middlewares\API\AdminMiddleware;
use Elemacy\Core\Route;
use Elemacy\Core\Controllers\ModuleController;

Route::get('/modules', [ModuleController::class, 'index'])->middleware(AdminMiddleware::class);
Route::put('/modules/{name}', [ModuleController::class, 'toggle'])->middleware(AdminMiddleware::class);

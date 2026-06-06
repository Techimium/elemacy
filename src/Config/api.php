<?php
if (!defined('ABSPATH')) {
    exit;
}

use Elemacy\Conditions\Controllers\ConditionController;
use Elemacy\Core\Middlewares\API\AdminMiddleware;
use Elemacy\Core\Route;
use Elemacy\Core\Controllers\ModuleController;

Route::get('/modules', [ModuleController::class, 'index'])->middleware(AdminMiddleware::class);
Route::put('/modules/{name}', [ModuleController::class, 'toggle'])->middleware(AdminMiddleware::class);

Route::get('/conditions/types', [ConditionController::class, 'types'])->middleware(AdminMiddleware::class);
Route::get('/conditions/search', [ConditionController::class, 'search'])->middleware(AdminMiddleware::class);

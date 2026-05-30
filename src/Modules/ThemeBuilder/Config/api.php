<?php
if (!defined('ABSPATH')) {
    exit;
}

use Elemacy\Core\Middlewares\API\AdminMiddleware;
use Elemacy\Core\Route;
use Elemacy\Modules\ThemeBuilder\Controllers\ConditionController;
use Elemacy\Modules\ThemeBuilder\Controllers\ThemeBuilderController;

Route::get('/theme-builder/templates', [ThemeBuilderController::class, 'index'])->middleware(AdminMiddleware::class);
Route::get('/theme-builder/templates/{id}', [ThemeBuilderController::class, 'show'])->middleware(AdminMiddleware::class);
Route::post('/theme-builder/templates', [ThemeBuilderController::class, 'store'])->middleware(AdminMiddleware::class);
Route::put('/theme-builder/templates/{id}', [ThemeBuilderController::class, 'update'])->middleware(AdminMiddleware::class);
Route::post('/theme-builder/templates/{id}/duplicate', [ThemeBuilderController::class, 'duplicate'])->middleware(AdminMiddleware::class);
Route::delete('/theme-builder/templates/{id}', [ThemeBuilderController::class, 'destroy'])->middleware(AdminMiddleware::class);

Route::get('/conditions/types', [ConditionController::class, 'types'])->middleware(AdminMiddleware::class);
Route::get('/conditions/search', [ConditionController::class, 'search'])->middleware(AdminMiddleware::class);

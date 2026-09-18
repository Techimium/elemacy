<?php
if (!defined('ABSPATH')) {
    exit;
}

use Elemacy\Core\Middlewares\API\AdminMiddleware;
use Elemacy\Core\Route;
use Elemacy\Modules\ThemeBuilder\Controllers\ThemeBuilderController;

Route::get('/theme-builder/template-types', [ThemeBuilderController::class, 'types'])->require_capability('manage_options')->middleware(AdminMiddleware::class);
Route::get('/theme-builder/templates', [ThemeBuilderController::class, 'index'])->require_capability('manage_options')->middleware(AdminMiddleware::class);
Route::get('/theme-builder/templates/{id}', [ThemeBuilderController::class, 'show'])->require_capability('manage_options')->middleware(AdminMiddleware::class);
Route::post('/theme-builder/templates', [ThemeBuilderController::class, 'store'])->require_capability('manage_options')->middleware(AdminMiddleware::class);
Route::put('/theme-builder/templates/{id}', [ThemeBuilderController::class, 'update'])->require_capability('manage_options')->middleware(AdminMiddleware::class);
Route::post('/theme-builder/templates/{id}/duplicate', [ThemeBuilderController::class, 'duplicate'])->require_capability('manage_options')->middleware(AdminMiddleware::class);
Route::delete('/theme-builder/templates/{id}', [ThemeBuilderController::class, 'destroy'])->require_capability('manage_options')->middleware(AdminMiddleware::class);

<?php
if (!defined('ABSPATH')) {
    exit;
}

use Elemacy\Core\Route;
use Elemacy\Modules\ThemeBuilder\Controllers\ThemeBuilderController;

Route::get('/theme-builder/templates', [ThemeBuilderController::class, 'index']);
Route::get('/theme-builder/templates/{id}', [ThemeBuilderController::class, 'show']);
Route::post('/theme-builder/templates', [ThemeBuilderController::class, 'store']);
Route::put('/theme-builder/templates/{id}', [ThemeBuilderController::class, 'update']);
Route::delete('/theme-builder/templates/{id}', [ThemeBuilderController::class, 'destroy']);

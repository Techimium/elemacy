<?php
if (!defined('ABSPATH')) {
    exit;
}

use Elemacy\Conditions\Controllers\ConditionController;
use Elemacy\Core\Middlewares\API\AdminMiddleware;
use Elemacy\Core\Route;
use Elemacy\Core\Controllers\ModuleController;
use Elemacy\TemplateLibrary\Controllers\LibraryController;

Route::get('/modules', [ModuleController::class, 'index'])->middleware(AdminMiddleware::class);
Route::put('/modules/{name}', [ModuleController::class, 'toggle'])->middleware(AdminMiddleware::class);

Route::get('/conditions/types', [ConditionController::class, 'types'])->middleware(AdminMiddleware::class);
Route::get('/conditions/search', [ConditionController::class, 'search'])->middleware(AdminMiddleware::class);

// Block library items (Loop, …) — Core, always available regardless of active modules.
Route::get('/library/template-types', [LibraryController::class, 'types'])->middleware(AdminMiddleware::class);
Route::get('/library/templates', [LibraryController::class, 'index'])->middleware(AdminMiddleware::class);
Route::get('/library/templates/{id}', [LibraryController::class, 'show'])->middleware(AdminMiddleware::class);
Route::post('/library/templates', [LibraryController::class, 'store'])->middleware(AdminMiddleware::class);
Route::put('/library/templates/{id}', [LibraryController::class, 'update'])->middleware(AdminMiddleware::class);
Route::post('/library/templates/{id}/duplicate', [LibraryController::class, 'duplicate'])->middleware(AdminMiddleware::class);
Route::delete('/library/templates/{id}', [LibraryController::class, 'destroy'])->middleware(AdminMiddleware::class);

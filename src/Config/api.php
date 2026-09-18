<?php
if (!defined('ABSPATH')) {
    exit;
}

use Elemacy\Conditions\Controllers\ConditionController;
use Elemacy\Core\Middlewares\API\AdminMiddleware;
use Elemacy\Core\Route;
use Elemacy\Core\Controllers\ModuleController;
use Elemacy\TemplateLibrary\Controllers\LibraryController;

Route::get('/modules', [ModuleController::class, 'index'])->require_capability('manage_options')->middleware(AdminMiddleware::class);
Route::put('/modules/{name}', [ModuleController::class, 'toggle'])->require_capability('manage_options')->middleware(AdminMiddleware::class);

Route::get('/conditions/types', [ConditionController::class, 'types'])->require_capability('manage_options')->middleware(AdminMiddleware::class);
Route::get('/conditions/search', [ConditionController::class, 'search'])->require_capability('manage_options')->middleware(AdminMiddleware::class);

// Block library items (Loop, …) — Core, always available regardless of active modules.
Route::get('/library/template-types', [LibraryController::class, 'types'])->require_capability('manage_options')->middleware(AdminMiddleware::class);
Route::get('/library/templates', [LibraryController::class, 'index'])->require_capability('manage_options')->middleware(AdminMiddleware::class);
Route::get('/library/templates/{id}', [LibraryController::class, 'show'])->require_capability('manage_options')->middleware(AdminMiddleware::class);
Route::post('/library/templates', [LibraryController::class, 'store'])->require_capability('manage_options')->middleware(AdminMiddleware::class);
Route::put('/library/templates/{id}', [LibraryController::class, 'update'])->require_capability('manage_options')->middleware(AdminMiddleware::class);
Route::post('/library/templates/{id}/duplicate', [LibraryController::class, 'duplicate'])->require_capability('manage_options')->middleware(AdminMiddleware::class);
Route::delete('/library/templates/{id}', [LibraryController::class, 'destroy'])->require_capability('manage_options')->middleware(AdminMiddleware::class);

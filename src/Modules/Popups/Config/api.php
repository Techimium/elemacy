<?php
if (!defined('ABSPATH')) {
    exit;
}

use Elemacy\Core\Middlewares\API\AdminMiddleware;
use Elemacy\Core\Route;
use Elemacy\Modules\Popups\Controllers\PopupController;
use Elemacy\Modules\Popups\Controllers\PopupSettingsController;

// Static routes first so they are not swallowed by the `{id}` catch-all.
Route::get('/popups/types', [PopupSettingsController::class, 'types'])->require_capability('manage_options')->middleware(AdminMiddleware::class);
Route::get('/popups/triggers', [PopupSettingsController::class, 'triggers'])->require_capability('manage_options')->middleware(AdminMiddleware::class);
Route::get('/popups/rules', [PopupSettingsController::class, 'rules'])->require_capability('manage_options')->middleware(AdminMiddleware::class);
Route::get('/popups', [PopupController::class, 'index'])->require_capability('manage_options')->middleware(AdminMiddleware::class);
Route::get('/popups/{id}', [PopupController::class, 'show'])->require_capability('manage_options')->middleware(AdminMiddleware::class);
Route::post('/popups', [PopupController::class, 'store'])->require_capability('manage_options')->middleware(AdminMiddleware::class);
Route::put('/popups/{id}', [PopupController::class, 'update'])->require_capability('manage_options')->middleware(AdminMiddleware::class);
Route::post('/popups/{id}/duplicate', [PopupController::class, 'duplicate'])->require_capability('manage_options')->middleware(AdminMiddleware::class);
Route::delete('/popups/{id}', [PopupController::class, 'destroy'])->require_capability('manage_options')->middleware(AdminMiddleware::class);

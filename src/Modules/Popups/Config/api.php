<?php
if (!defined('ABSPATH')) {
    exit;
}

use Elemacy\Core\Middlewares\API\AdminMiddleware;
use Elemacy\Core\Route;
use Elemacy\Modules\Popups\Controllers\PopupController;
use Elemacy\Modules\Popups\Controllers\PopupSettingsController;

// Static routes first so they are not swallowed by the `{id}` catch-all.
Route::get('/popups/triggers', [PopupSettingsController::class, 'triggers'])->middleware(AdminMiddleware::class);
Route::get('/popups/rules', [PopupSettingsController::class, 'rules'])->middleware(AdminMiddleware::class);
Route::get('/popups', [PopupController::class, 'index'])->middleware(AdminMiddleware::class);
Route::get('/popups/{id}', [PopupController::class, 'show'])->middleware(AdminMiddleware::class);
Route::post('/popups', [PopupController::class, 'store'])->middleware(AdminMiddleware::class);
Route::put('/popups/{id}', [PopupController::class, 'update'])->middleware(AdminMiddleware::class);
Route::post('/popups/{id}/duplicate', [PopupController::class, 'duplicate'])->middleware(AdminMiddleware::class);
Route::delete('/popups/{id}', [PopupController::class, 'destroy'])->middleware(AdminMiddleware::class);

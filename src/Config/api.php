<?php

use Elemacy\Core\Route;
use Elemacy\Core\Controllers\ModuleController;

Route::get('/modules', [ModuleController::class, 'index']);
Route::get('/modules/{name}', [ModuleController::class, 'show']);
Route::put('/modules/{name}', [ModuleController::class, 'toggle']);

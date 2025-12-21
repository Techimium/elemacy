<?php

use Elemacy\Core\Route;
use Elemacy\Core\Controllers\ModuleController;

Route::get( '/modules', [ModuleController::class, 'index']);
Route::put( '/modules', [ModuleController::class, 'toggle']);
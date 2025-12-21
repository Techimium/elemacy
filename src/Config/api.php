<?php

use Elemacy\Core\Route;
use Elemacy\Core\Controllers\ModuleController;

Route::get( '/hello', [ModuleController::class, 'index']);
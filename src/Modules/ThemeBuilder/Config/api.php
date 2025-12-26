<?php

use Elemacy\Core\Route;
use Elemacy\Modules\ThemeBuilder\Controllers\ThemeBuilderController;
use Elemacy\Core\Middlewares\API\AuthMiddleware;

Route::get( '/templates', [ThemeBuilderController::class, 'index']);
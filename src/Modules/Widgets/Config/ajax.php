<?php
if (!defined('ABSPATH')) {
    exit;
}

use Elemacy\Http\AjaxRouter;
use Elemacy\Modules\Widgets\Controllers\AjaxPaginationController;

AjaxRouter::add_action('elemacy_widgets_loop_grid_pagination', [AjaxPaginationController::class, 'index'])->for_public();

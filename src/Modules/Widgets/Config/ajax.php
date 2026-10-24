<?php
if (!defined('ABSPATH')) {
    exit;
}

use Elemacy\Http\AjaxRouter;
use Elemacy\Modules\Widgets\Controllers\AjaxPaginationController;
use Elemacy\Support\Utils;

AjaxRouter::add_action('elemacy_widgets_loop_grid_pagination', [AjaxPaginationController::class, 'index'])->for_public()->with_nonce(Utils::with_prefix('ajax_nonce'));

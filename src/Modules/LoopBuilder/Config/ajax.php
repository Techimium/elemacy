<?php
if (!defined('ABSPATH')) {
    exit;
}

use Elemacy\Http\AjaxRouter;
use Elemacy\Modules\LoopBuilder\Controllers\AjaxPaginationController;

AjaxRouter::add_action('elemacy_loop_builder_pagination', [AjaxPaginationController::class, 'index'])->for_public();

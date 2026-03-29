<?php
if (!defined('ABSPATH')) {
    exit;
}

use Elemacy\Core\AjaxRouter;
use Elemacy\Modules\Widgets\Controllers\AjaxPaginationController;
use Elemacy\Modules\Widgets\Controllers\AjaxFormController;
use Elemacy\Support\Utils;

AjaxRouter::add_action('elemacy_loop_grid_pagination', [AjaxPaginationController::class, 'index'])->with_nonce(Utils::with_prefix('ajax_nonce'));
AjaxRouter::add_action('elemacy_form_submit', [AjaxFormController::class, 'index'])->with_nonce('elemacy_form_submit');

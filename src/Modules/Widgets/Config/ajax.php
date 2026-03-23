<?php

use Elemacy\Core\AjaxRouter;
use Elemacy\Modules\Widgets\Controllers\AjaxPaginationController;
use Elemacy\Support\Utils;

AjaxRouter::add_action('elemacy_loop_grid_pagination', [AjaxPaginationController::class, 'index'])->with_nonce(Utils::with_prefix('ajax_nonce'));

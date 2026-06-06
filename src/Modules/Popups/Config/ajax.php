<?php

if (!defined('ABSPATH')) {
    exit;
}

use Elemacy\Core\AjaxRouter;
use Elemacy\Modules\Popups\Controllers\PopupTrackController;

AjaxRouter::add_action('elemacy_popup_track', [PopupTrackController::class, 'index'])
    ->for_guest()
    ->for_authenticated()
    ->with_nonce('elemacy_ajax_nonce');

<?php

namespace Elemacy\Modules\Popups\Controllers;

defined('ABSPATH') || exit;

use Elemacy\Core\Http\SiteRequest;
use Elemacy\Core\Http\SiteResponse;
use Elemacy\Modules\Popups\Services\AnalyticsService;

/**
 * Front-end (admin-ajax) endpoint that records popup analytics events.
 */
class PopupTrackController
{
    public function index(SiteRequest $request)
    {
        $popup_id = (int) $request->get_int('popup_id', 0);
        $event    = (string) $request->get_string('event', '');

        $service = new AnalyticsService();

        if ($event === AnalyticsService::EVENT_CONVERSION) {
            $service->record_conversion($popup_id);
        } else {
            $service->record_impression($popup_id);
        }

        SiteResponse::instance()->success([
            'popup_id' => $popup_id,
            'event'    => $event,
        ]);
    }
}

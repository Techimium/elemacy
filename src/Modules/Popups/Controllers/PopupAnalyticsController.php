<?php

namespace Elemacy\Modules\Popups\Controllers;

defined('ABSPATH') || exit;

use Elemacy\Core\Http\Request;
use Elemacy\Core\Http\Response;

class PopupAnalyticsController
{
    protected const ANALYTICS_META_KEY = '_elemacy_popup_analytics';

    public function show(Request $request, $id)
    {
        $analytics = get_post_meta((int) $id, self::ANALYTICS_META_KEY, true);

        $data = is_array($analytics) ? $analytics : [
            'impressions' => 0,
            'conversions' => 0,
        ];

        return Response::create()->json([
            'data' => $data,
        ]);
    }
}

<?php

namespace Elemacy\Modules\Popups\Services;

defined('ABSPATH') || exit;

use Elemacy\Core\Hooks;

/**
 * Analytics scaffolding (Milestone 7).
 *
 * The free build does not persist any counts: each method is a no-op besides
 * firing {@see Hooks::POPUP_TRACK_ACTION} so elemacy-pro can hook in and record
 * the event. Pro extends/overrides this service to provide real tracking.
 */
class AnalyticsService
{
    public const EVENT_IMPRESSION = 'impression';
    public const EVENT_CONVERSION = 'conversion';

    public function record_impression(int $popup_id): void
    {
        $this->track($popup_id, self::EVENT_IMPRESSION);
    }

    public function record_conversion(int $popup_id): void
    {
        $this->track($popup_id, self::EVENT_CONVERSION);
    }

    /**
     * Fire the tracking action. No-op in free beyond the action itself.
     */
    protected function track(int $popup_id, string $event): void
    {
        /**
         * Fires when a popup analytics event occurs.
         *
         * @param int    $popup_id The popup post ID.
         * @param string $event    The event type ('impression' | 'conversion').
         */
        do_action(Hooks::POPUP_TRACK_ACTION, $popup_id, $event);
    }
}

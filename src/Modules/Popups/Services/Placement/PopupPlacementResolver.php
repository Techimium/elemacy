<?php

namespace Elemacy\Modules\Popups\Services\Placement;

defined('ABSPATH') || exit;

use Elemacy\Modules\Popups\Services\PopupManager;
use Elemacy\Loop\Contracts\PlacementResolverInterface;

/** Proves a popup document is one the server actually matched for this request. */
final class PopupPlacementResolver implements PlacementResolverInterface
{
    public function get_key(): string
    {
        return 'popup';
    }

    public function resolve(int $owner_id): ?array
    {
        if (!in_array($owner_id, PopupManager::instance()->get_matched_ids(), true)) {
            return null;
        }

        return ['popup_id' => $owner_id];
    }
}

<?php

namespace Elemacy\Modules\Popups\Services\Placement;

defined('ABSPATH') || exit;

use Elemacy\Modules\Popups\Services\PopupManager;
use Elemacy\Modules\Popups\Services\PopupDisplayResolver;
use Elemacy\Modules\Widgets\Contracts\PublicContextPlacementResolverInterface;

/** Proves a popup document is one the server actually matched for this request. */
final class PopupPlacementResolver implements PublicContextPlacementResolverInterface
{
    public function get_key(): string
    {
        return 'popup';
    }

    public function resolve_public_context(int $owner_id): ?array
    {
        foreach (PopupDisplayResolver::instance()->resolve_all() as $popup) {
            if ((int) $popup->id === $owner_id) {
                return ['popup_id' => $owner_id];
            }
        }

        return null;
    }

    public function resolve(int $owner_id): ?array
    {
        if (!in_array($owner_id, PopupManager::instance()->get_matched_ids(), true)) {
            return null;
        }

        return ['popup_id' => $owner_id];
    }
}

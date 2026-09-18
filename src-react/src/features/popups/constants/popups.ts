import { __ } from '@wordpress/i18n';
import { AlignStartVertical, GalleryThumbnails, PanelTop, Puzzle, Square } from 'lucide-react';

/**
 * Purely cosmetic per-type icon + description for the built-in popup types,
 * keyed by the server-registered `value` (task 10.4). Not authoritative:
 * which types exist and their labels come from `GET /popups/types`
 * (usePopupTypes()), so an extension-contributed type with no entry here
 * still renders correctly — just with the fallback icon and no description.
 */
export const POPUP_TYPE_HINTS: Record<
    string,
    { icon: React.ComponentType<{ className?: string }>; description: string }
> = {
    popup: { icon: Square, description: __('A classic centered modal popup.', 'elemacy') },
    topbar: {
        icon: PanelTop,
        description: __('A sticky bar at the top or bottom of the screen.', 'elemacy'),
    },
    floating: {
        icon: AlignStartVertical,
        description: __('A floating element anchored to a corner.', 'elemacy'),
    },
    banner: {
        icon: GalleryThumbnails,
        description: __('A floating banner you can place anywhere on screen.', 'elemacy'),
    },
};

export const POPUP_TYPE_FALLBACK_ICON = Puzzle;

export const STATUS_OPTIONS = [
    { value: 'publish', label: __('Published', 'elemacy') },
    { value: 'draft', label: __('Draft', 'elemacy') },
] as const;

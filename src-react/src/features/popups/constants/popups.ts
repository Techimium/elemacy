import { __ } from '@wordpress/i18n';

/** Popup types — value must match the PHP `type` enum. */
export const POPUP_TYPES = [
    {
        value: 'popup',
        label: __('Popup', 'elemacy'),
        description: __('A classic centered modal popup.', 'elemacy'),
    },
    {
        value: 'topbar',
        label: __('Top/Bottom Bar', 'elemacy'),
        description: __('A sticky bar at the top or bottom of the screen.', 'elemacy'),
    },
    {
        value: 'floating',
        label: __('Floating Element', 'elemacy'),
        description: __('A floating element anchored to a corner.', 'elemacy'),
    },
    {
        value: 'banner',
        label: __('Banner', 'elemacy'),
        description: __('A floating banner you can place anywhere on screen.', 'elemacy'),
    },
] as const;

export type PopupType = (typeof POPUP_TYPES)[number]['value'];

export const POPUP_TYPE_VALUES = POPUP_TYPES.map((t) => t.value) as [
    PopupType,
    ...PopupType[],
];

export const STATUS_OPTIONS = [
    { value: 'publish', label: __('Published', 'elemacy') },
    { value: 'draft', label: __('Draft', 'elemacy') },
] as const;

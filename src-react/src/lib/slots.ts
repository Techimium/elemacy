export const Slots = {
    POPUP_CARD_FOOTER: 'popup-card-footer',
    DASHBOARD_PRO: 'dashboard-pro-section',
} as const;

export type SlotName = (typeof Slots)[keyof typeof Slots];

/** Maps each slot name to the props fills registered for that slot will receive. */
export interface SlotPropsMap {
    'popup-card-footer': {
        popupId: number;
    };
    'dashboard-pro-section': Record<string, never>;
}

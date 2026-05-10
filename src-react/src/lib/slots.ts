export const Slots = {
    TEMPLATE_CONDITIONS: 'template-conditions',
} as const;

export type SlotName = (typeof Slots)[keyof typeof Slots];

/** Maps each slot name to the props fills registered for that slot will receive. */
export interface SlotPropsMap {
    'template-conditions': { templateId: number };
}

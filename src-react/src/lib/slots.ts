export const Slots = {
    TEMPLATE_EXTRAS: 'template-extras',
} as const;

export type SlotName = (typeof Slots)[keyof typeof Slots];

/** Maps each slot name to the props fills registered for that slot will receive. */
export interface SlotPropsMap {
    'template-extras': {
        value: Record<string, unknown>;
        onChange: (value: Record<string, unknown>) => void;
        templateId?: number;
    };
}

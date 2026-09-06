import type { ParamDescriptor } from '@/features/popups/schemas/trigger-rule-types';

/** Builds the initial params bag for a selected type from its schema defaults. */
export function seedParams(schema: ParamDescriptor[]): Record<string, unknown> {
    const params: Record<string, unknown> = {};
    for (const descriptor of schema) {
        if (descriptor.default !== undefined) {
            params[descriptor.key] = descriptor.default;
        } else if (descriptor.type === 'checkbox-group') {
            params[descriptor.key] = [];
        }
    }
    return params;
}

/** Generates a stable client-side id for a new trigger/rule entry. */
export function generateEntryId(prefix: string): string {
    return `${prefix}_${Date.now()}_${Math.random().toString(36).slice(2)}`;
}

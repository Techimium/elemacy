import { z } from 'zod';
import { AvailabilitySchema } from '@/schemas/availability';

export const ConditionRuleSchema = z.object({
    id: z.string(),
    type: z.string(),
    operator: z.enum(['include', 'exclude']),
    value: z.string(),
    availability: AvailabilitySchema.optional(),
});

export const ConditionSubValueSchema = z.object({
    value: z.string(),
    label: z.string(),
});

export const ConditionValueType = z.enum(['none', 'select', 'search']);

export const ConditionTypeSchema = z.object({
    value: z.string(),
    label: z.string(),
    value_type: ConditionValueType,
    sub_values: z.array(ConditionSubValueSchema),
    search: z.record(z.string(), z.unknown()).optional(),
    is_mock: z.boolean(),
});

export const ConditionGroupSchema = z.object({
    type: z.string(),
    label: z.string(),
    conditions: z.array(ConditionTypeSchema),
});

export type ConditionRule = z.infer<typeof ConditionRuleSchema>;

/** A fresh client-side id for a rule row; the server keeps whatever id it is sent. */
export function newConditionRuleId(): string {
    return `cond_${Date.now()}_${Math.random().toString(36).slice(2)}`;
}
export type ConditionSubValue = z.infer<typeof ConditionSubValueSchema>;
export type ConditionType = z.infer<typeof ConditionTypeSchema>;
export type ConditionGroup = z.infer<typeof ConditionGroupSchema>;

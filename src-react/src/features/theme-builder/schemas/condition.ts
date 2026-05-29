import { z } from 'zod';

export const ConditionRuleSchema = z.object({
    id: z.string(),
    type: z.string(),
    operator: z.enum(['include', 'exclude']),
    value: z.string(),
});

export const ConditionSubValueSchema = z.object({
    value: z.string(),
    label: z.string(),
});

export const ConditionTypeSchema = z.object({
    value: z.string(),
    label: z.string(),
    has_value: z.boolean(),
    sub_values: z.array(ConditionSubValueSchema),
    is_mock: z.boolean(),
});

export const ConditionGroupSchema = z.object({
    type: z.string(),
    label: z.string(),
    conditions: z.array(ConditionTypeSchema),
});

export type ConditionRule = z.infer<typeof ConditionRuleSchema>;
export type ConditionSubValue = z.infer<typeof ConditionSubValueSchema>;
export type ConditionType = z.infer<typeof ConditionTypeSchema>;
export type ConditionGroup = z.infer<typeof ConditionGroupSchema>;

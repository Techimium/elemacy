import { z } from 'zod';

/** Option for `select` / `checkbox-group` params. */
export const ParamOptionSchema = z.object({
    value: z.string(),
    label: z.string(),
});

/**
 * Describes a single param input for a trigger/rule type. The server drives the
 * form: each descriptor renders the matching control in `ParamFields`.
 */
export const ParamDescriptorSchema = z.object({
    key: z.string(),
    label: z.string().optional(),
    type: z.enum(['number', 'text', 'datetime', 'select', 'boolean', 'checkbox-group']),
    default: z.unknown().optional(),
    min: z.number().optional(),
    max: z.number().optional(),
    unit: z.string().optional(),
    options: z.array(ParamOptionSchema).optional(),
});

/** A selectable trigger type returned by `GET /popups/triggers`. */
export const TriggerTypeSchema = z.object({
    value: z.string(),
    label: z.string(),
    is_mock: z.boolean(),
    params_schema: z.array(ParamDescriptorSchema),
});

/** A selectable rule type returned by `GET /popups/rules`. */
export const RuleTypeSchema = TriggerTypeSchema.extend({
    is_server_evaluable: z.boolean(),
});

export type ParamOption = z.infer<typeof ParamOptionSchema>;
export type ParamDescriptor = z.infer<typeof ParamDescriptorSchema>;
export type TriggerType = z.infer<typeof TriggerTypeSchema>;
export type RuleType = z.infer<typeof RuleTypeSchema>;

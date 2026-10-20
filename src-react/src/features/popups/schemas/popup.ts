import { z } from 'zod';
import { __ } from '@wordpress/i18n';
import { ConditionRuleSchema, ConditionRulesHaveRequiredValues } from '@/schemas/condition';
import { AvailabilitySchema } from '@/schemas/availability';

const StatusSchema = z.enum(['publish', 'draft', 'trash']);

// Triggers and advanced rules share the same stored shape: a typed entry with
// an opaque params bag whose keys are driven by the type's `params_schema`.
export const TriggerRuleSchema = z.object({
    id: z.string(),
    type: z.string(),
    params: z.record(z.string(), z.unknown()),
    availability: AvailabilitySchema.optional(),
});

const TriggerSchema = TriggerRuleSchema;
const RuleSchema = TriggerRuleSchema;

export type TriggerRule = z.infer<typeof TriggerRuleSchema>;

/**
 * A selectable popup type returned by `GET /popups/types`:
 * registry-backed, not a hardcoded compile-time enum, so an extension type
 * needs no edit here to become choosable.
 */
export const PopupTypeSchema = z.object({
    value: z.string(),
    label: z.string(),
    availability: AvailabilitySchema,
});

export type PopupTypeOption = z.infer<typeof PopupTypeSchema>;

// `type` is a plain non-empty string, not a compile-time enum: the registry
// (fetched via usePopupTypes()) is authoritative for which values are
// currently selectable, and the server is authoritative for which values are
// accepted on save. A structural check here only rejects an obviously
// malformed response/submission, never a value this build doesn't yet know
// about — an unknown *stored* type (e.g. a deactivated extension) must still
// parse so its popup remains visible and editable for repair.
const PopupTypeValueSchema = z.string().min(1);

export const PopupListItemSchema = z.object({
    id: z.number(),
    title: z.string(),
    type: PopupTypeValueSchema,
    status: StatusSchema,
    date: z.string().nullable().optional(),
    edit_with_elementor: z.string(),
});

export const PopupSchema = z.object({
    id: z.number(),
    title: z.string(),
    type: PopupTypeValueSchema,
    // Present when the server evaluated this popup's own stored type against
    // the registry — absent for other consumers of this shape
    // (e.g. a freshly-built CreatePopup value that was never fetched back).
    type_availability: AvailabilitySchema.optional(),
    status: StatusSchema,
    author: z.number().optional(),
    date: z.string().nullable().optional(),
    conditions: z.array(ConditionRuleSchema).default([]),
    triggers: z.array(TriggerSchema).default([]),
    rules: z.array(RuleSchema).default([]),
    edit_with_elementor: z.string(),
});

export const CreatePopupSchema = z.object({
    title: z.string().min(1, __('Title is required.', 'elemacy')),
    type: PopupTypeValueSchema,
    status: StatusSchema,
    conditions: z.array(ConditionRuleSchema).refine(ConditionRulesHaveRequiredValues, {
        message: __('Choose a post for each "Specific Post / Page" condition.', 'elemacy'),
    }),
    triggers: z.array(TriggerSchema),
    rules: z.array(RuleSchema),
});

export const UpdatePopupSchema = CreatePopupSchema;

export type PopupListItem = z.infer<typeof PopupListItemSchema>;
export type Popup = z.infer<typeof PopupSchema>;
export type CreatePopup = z.infer<typeof CreatePopupSchema>;
export type UpdatePopup = z.infer<typeof UpdatePopupSchema>;

import { z } from 'zod';
import { __ } from '@wordpress/i18n';
import { ConditionRuleSchema } from '@/schemas/condition';
import { POPUP_TYPE_VALUES } from '@/features/popups/constants/popups';

const StatusSchema = z.enum(['publish', 'draft', 'trash']);

// Triggers and advanced rules share the same stored shape: a typed entry with
// an opaque params bag whose keys are driven by the type's `params_schema`.
export const TriggerRuleSchema = z.object({
    id: z.string(),
    type: z.string(),
    params: z.record(z.string(), z.unknown()),
});

const TriggerSchema = TriggerRuleSchema;
const RuleSchema = TriggerRuleSchema;

export type TriggerRule = z.infer<typeof TriggerRuleSchema>;

export const PopupListItemSchema = z.object({
    id: z.number(),
    title: z.string(),
    type: z.enum(POPUP_TYPE_VALUES),
    status: StatusSchema,
    date: z.string().nullable().optional(),
    edit_with_elementor: z.string(),
});

export const PopupSchema = z.object({
    id: z.number(),
    title: z.string(),
    type: z.enum(POPUP_TYPE_VALUES),
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
    type: z.enum(POPUP_TYPE_VALUES),
    status: StatusSchema,
    conditions: z.array(ConditionRuleSchema),
    triggers: z.array(TriggerSchema),
    rules: z.array(RuleSchema),
});

export const UpdatePopupSchema = CreatePopupSchema;

export type PopupListItem = z.infer<typeof PopupListItemSchema>;
export type Popup = z.infer<typeof PopupSchema>;
export type CreatePopup = z.infer<typeof CreatePopupSchema>;
export type UpdatePopup = z.infer<typeof UpdatePopupSchema>;

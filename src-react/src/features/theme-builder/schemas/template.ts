import { z } from 'zod';
import { __ } from '@wordpress/i18n';
import { ConditionRuleSchema } from '@/schemas/condition';

const TemplateTypeSchema = z.object({
    value: z.string(),
    label: z.string(),
});

const TemplateFilterSchema = z.object({
    search: z.string().optional(),
    type: z.string().optional(),
    status: z.enum(['publish', 'draft', 'trash']).optional()
});

const TemplateSchema = z.object({
    id: z.number(),
    title: z.string(),
    type: z.string(),
    status: z.enum(['publish', 'draft', 'trash']),
    author: z.number(),
    edit_with_elementor: z.string(),
    date: z.string().nullable().optional(),
    conditions: z.array(ConditionRuleSchema).optional(),
});

const CreateTemplateSchema = z.object({
    title: z.string().min(1, __('Title is required', 'elemacy')),
    type: z.string().min(1, __('Type is required', 'elemacy')),
    status: z.enum(['publish', 'draft', 'trash']),
    // A stored rule carries `availability` and is let through even when its
    // condition is unknown, so it can be repaired; a rule added in this form
    // must name a condition.
    conditions: z.array(ConditionRuleSchema).optional().refine(
        (rules) => (rules ?? []).every((rule) => rule.type !== '' || rule.availability !== undefined),
        { message: __('Choose a condition for each row, or remove it.', 'elemacy') },
    ),
});

const UpdateTemplateSchema = CreateTemplateSchema;

type TemplateType = z.infer<typeof TemplateTypeSchema>;
type TemplateFilter = z.infer<typeof TemplateFilterSchema>;
type Template = z.infer<typeof TemplateSchema>;
type CreateTemplate = z.infer<typeof CreateTemplateSchema>;
type UpdateTemplate = z.infer<typeof UpdateTemplateSchema>;

export {
    TemplateTypeSchema, type TemplateType,
    TemplateFilterSchema, type TemplateFilter,
    TemplateSchema, type Template,
    CreateTemplateSchema, type CreateTemplate,
    UpdateTemplateSchema, type UpdateTemplate,
};